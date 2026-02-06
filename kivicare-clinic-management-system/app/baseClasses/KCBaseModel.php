<?php

namespace App\baseClasses;

use Illuminate\Support\Collection;
use WP_Error;

/**
 * Base Model class for KiviCare ORM
 */

defined('ABSPATH') or die('Something went wrong');
abstract class KCBaseModel
{
    /**
     * Define table structure and properties
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => '',
            'primary_key' => 'id',
            'columns' => [],
            'timestamps' => true,
            'soft_deletes' => false,
        ];
    }

    /**
     * Get the model's schema
     */
    public static function getSchema(): array
    {
        return static::initSchema();
    }

    // Properties will be dynamically accessed via magic methods
    private array $attributes = [];
    private array $dirtyAttributes = [];

    /**
     * The `query` function returns a new instance of `KCQueryBuilder` using the current class.
     * 
     * @return KCQueryBuilder An instance of the `KCQueryBuilder` class is being returned.
     */
    public static function query(): KCQueryBuilder
    {
        return new KCQueryBuilder(static::class);
    }

    /**
     * The `table` function returns a query builder instance with a custom table alias.
     * 
     * @param string $alias The custom table alias to use for the query.
     * @return KCQueryBuilder An instance of the `KCQueryBuilder` class with the custom alias.
     */
    public static function table(string $alias): KCQueryBuilder
    {
        return (new KCQueryBuilder(static::class))->setTableAlias($alias);
    }

    /**
     * Find by primary key
     */
    public static function find($id)
    {
        return static::query()->find($id);
    }

    /**
     * Get all records
     */
    public static function all(): Collection
    {
        return static::query()->get();
    }

    /**
     * Create a new record
     */
    public static function create(array $data)
    {
        return static::query()->create($data);
    }

    /**
     * Update the current model instance with new data
     * 
     * @param array $data The data to update
     * @return int|WP_Error Returns the primary key on success, WP_Error or false on failure
     */
    public function update(array $data): int|WP_Error
    {
        // Set the new values using the magic setter (which handles validation)
        foreach ($data as $property => $value) {
            $this->__set($property, $value);
        }

        // Save the model
        return $this->save();
    }
    /**
     * Magic getter for properties
     */
    public function __get(string $property)
    {
        if (array_key_exists($property, $this->attributes)) {
            return $this->attributes[$property];
        }

        // Check if it's a defined property with a default value
        $schema = static::getSchema();
        if (isset($schema['columns'][$property]['default'])) {
            return $schema['columns'][$property]['default'];
        }

        return null;
    }

    /**
     * Magic setter for properties
     */
    public function __set(string $property, $value)
    {
        $schema = static::getSchema();

        // Check if property exists in schema
        if (isset($schema['columns'][$property])) {
            $column = $schema['columns'][$property];

            // Apply sanitizers
            if (!empty($column['sanitizers'])) {
                foreach ($column['sanitizers'] as $sanitizer) {
                    $value = $sanitizer($value);
                }
            }

            // Check validators
            if (!empty($column['validators'])) {
                foreach ($column['validators'] as $validator) {
                    $result = is_callable($validator) ? call_user_func($validator, $value) : true;

                    // If result is a string, it's an error message
                    if (is_string($result) && $result !== '1' && $result !== 'true') {
                        throw new \InvalidArgumentException(esc_html($result));
                    }
                    // If result is false, throw a generic error
                    elseif ($result === false) {
                        throw new \InvalidArgumentException(sprintf("Invalid value for %s", esc_html($property)));
                    }
                }
            }

            // Mark as dirty if changing
            if (!isset($this->attributes[$property]) || $this->attributes[$property] !== $value) {
                $this->dirtyAttributes[$property] = true;
            }
        }

        // Store all properties in attributes, even if not in schema
        // This ensures joined columns from other tables are accessible
        $this->attributes[$property] = $value;
    }

    /**
     * Check if property exists
     */
    public function __isset(string $property): bool
    {
        $schema = static::getSchema();
        return isset($this->attributes[$property]) || isset($schema['columns'][$property]);
    }

    /**
     * Save the model to the database
     */
    public function save(): int|WP_Error
    {
        global $wpdb;
        $schema = static::getSchema();
        $isNew = empty($this->attributes[$schema['primary_key']]);

        // Prepare data for insert/update
        $data = [];

        foreach ($schema['columns'] as $property => $config) {

            // Skip primary key for inserts if auto_increment
            if ($isNew && $property === $schema['primary_key'] && isset($config['auto_increment']) && $config['auto_increment']) {
                continue;
            }

            // Only include dirty attributes for updates
            if (!$isNew && !isset($this->dirtyAttributes[$property])) {
                continue;
            }
            // If property has value
            if (isset($this->attributes[$property])) {
                $data[$config['column']] = $this->prepareValueForDatabase($property, $this->attributes[$property]);
            }
            // Or has default and is new record
            elseif ($isNew && isset($config['default'])) {
                $data[$config['column']] = $this->prepareValueForDatabase($property, $config['default']);
            }
            // Or is nullable
            elseif (isset($config['nullable']) && $config['nullable']) {
                $data[$config['column']] = null;
            }
            // Required field with no value
            elseif (!$config['nullable']) {
                throw new \InvalidArgumentException(sprintf("Property %s is required", esc_html($property)));
            }
        }

        // Add timestamps
        if ($schema['timestamps']) {
            $now = current_time('mysql');
            if ($isNew) {
                $data['created_at'] = $now;
            }
            $data['updated_at'] = $now;
        }

        // Insert or update
        $table = $schema['table_name'] ? ($wpdb->prefix . $schema['table_name']) : $schema['user_table_name'];


        if ($isNew) {
            $result = $wpdb->insert($table, $data);

            if ($result) {
                // Set the new ID
                $this->attributes[$schema['primary_key']] = $wpdb->insert_id;
                return $wpdb->insert_id;
            } else {
                KCErrorLogger::instance()->error('KCBaseModel::save - Insert failed - Error: ' . $wpdb->last_error);
                KCErrorLogger::instance()->error('KCBaseModel::save - Last query: ' . $wpdb->last_query);
            }
        } else {
            $result = $wpdb->update(
                $table,
                $data,
                [$schema['primary_key'] => $this->attributes[$schema['primary_key']]]
            );

            if ($result !== false) {
                // KCErrorLogger::instance()->error('KCBaseModel::save - Update success - Rows affected: ' . $result);
                return $this->attributes[$schema['primary_key']];
            } else {
                KCErrorLogger::instance()->error('KCBaseModel::save - Update failed - Error: ' . $wpdb->last_error);
                KCErrorLogger::instance()->error('KCBaseModel::save - Last query: ' . $wpdb->last_query);
            }
        }

        return false;
    }

    /**
     * Delete the model
     */
    public function delete(): bool
    {
        global $wpdb;
        $schema = static::getSchema();

        if (empty($this->attributes[$schema['primary_key']])) {
            return false;
        }

        $pk = $schema['primary_key'];
        $id = $this->attributes[$pk];

        // Use soft deletes if enabled
        if ($schema['soft_deletes']) {
            $this->attributes['deleted_at'] = current_time('mysql');
            $this->dirtyAttributes['deleted_at'] = true;
            return $this->save();
        }

        $table = $schema['table_name'] ? ($wpdb->prefix . $schema['table_name']) : $schema['user_table_name'];
        $result = $wpdb->delete($table, [$pk => $id]);

        return $result !== false;
    }

    /**
     * Prepare a value for database storage based on its type
     */
    private function prepareValueForDatabase(string $property, $value)
    {
        $schema = static::getSchema();

        if (!isset($schema['columns'][$property])) {
            return $value;
        }

        $type = $schema['columns'][$property]['type'];
        $originalValue = $value;

        switch ($type) {
            case 'int':
            case 'bigint':
                $value = (int) $value;
                break;
            case 'float':
                $value = (float) $value;
                break;
            case 'bool':
                $value = (bool) $value ? 1 : 0;
                break;
            case 'datetime':
                // Ensure valid MySQL format
                if (!empty($value) && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
                    $value = gmdate('Y-m-d H:i:s', strtotime($value));
                }
                break;
            case 'json':
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                break;
            case 'string':
            case 'varchar':
            case 'text':
            default:
                // Keep as is
                break;
        }

        // Apply sanitizers if defined
        if (!empty($schema['columns'][$property]['sanitizers'])) {
            foreach ($schema['columns'][$property]['sanitizers'] as $sanitizer) {
                if (is_callable($sanitizer)) {
                    $value = $sanitizer($value);
                }
            }
        }

        return $value;
    }


    /**
     * Get all attributes as an array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

}
