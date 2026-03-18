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

    // ─── Cache Registry Cache Group ───────────────────────────────────────────
    // DSA: We store an inverted index (hash map) of  rowId => [cacheKey, ...]
    // so that when a single row changes, we ONLY evict the cache entries that
    // actually reference that row.  Full-version bump is only the last resort.

    private static string $registryGroup = 'kc_cache_registry';

    /**
     * DSA: Targeted cache eviction for a known primary-key value.
     *
     * Complexity: O(k) where k is the number of unique queries that involved this row.
     * Falls back to a full version bump when the registry is unavailable.
     *
     * @param int|string $id  The primary-key value of the changed row.
     */
    public static function flushCacheForId($id): void
    {
        if (empty($id)) {
            static::flushCache(); // unknown id — full invalidation
            return;
        }

        $modelClass = static::class;
        $cacheGroup = 'kc_query_' . str_replace('\\', '_', $modelClass);
        $registryKey = static::getCacheRegistryKey($id);

        // Load the inverted index entry for this row
        $keys = wp_cache_get($registryKey, static::$registryGroup);
        if (false === $keys || !is_array($keys) || empty($keys)) {
            // No registry entry — fall back to full flush
            static::flushCache();
            return;
        }

        // Surgically delete only the cache keys that reference this row
        foreach ($keys as $cacheKey) {
            wp_cache_delete($cacheKey, $cacheGroup);
        }

        // Clear the registry entry itself
        wp_cache_delete($registryKey, static::$registryGroup);
    }

    /**
     * DSA: Patch a single cached row in a result-set without re-querying the DB.
     *
     * Locates every collection-level cache entry that contains this row and
     * replaces the stale row data in-place, avoiding a full re-fetch.
     *
     * @param int|string $id         Primary-key value of the row that changed.
     * @param array      $newData    Associative array of column => new value.
     */
    public static function patchCache($id, array $newData): void
    {
        if (empty($id) || empty($newData)) {
            return;
        }

        $modelClass = static::class;
        $cacheGroup = 'kc_query_' . str_replace('\\', '_', $modelClass);
        $registryKey = static::getCacheRegistryKey($id);
        $schema = static::getSchema();
        $pk = $schema['primary_key'];

        $keys = wp_cache_get($registryKey, static::$registryGroup);
        if (false === $keys || !is_array($keys)) {
            return;
        }

        foreach ($keys as $cacheKey) {
            $cached = wp_cache_get($cacheKey, $cacheGroup);
            if (!is_array($cached)) {
                continue;
            }

            $updated = false;
            foreach ($cached as &$row) {
                // Match by primary key column name
                $pkCol = $schema['columns'][$pk]['column'] ?? $pk;
                if (isset($row[$pkCol]) && (string) $row[$pkCol] === (string) $id) {
                    foreach ($newData as $col => $val) {
                        $row[$col] = $val;
                    }
                    $updated = true;
                }
            }
            unset($row);

            if ($updated) {
                // Re-write the patched result set into the same cache key
                wp_cache_set($cacheKey, $cached, $cacheGroup, 120);
            }
        }
    }

    /**
     * Flush ALL cached queries for this model (version bump strategy).
     *
     * Use this only for bulk operations or when a row ID is unknown.
     */
    public static function flushCache(): void
    {
        $modelClass = static::class;
        $cacheGroup = 'kc_query_' . str_replace('\\', '_', $modelClass);

        // Try native group flushing if supported (e.g. Redis Object Cache Pro)
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($cacheGroup);
        }

        // Bump version key — all existing cache keys with old version become stale
        wp_cache_set('kc_query_version_' . $modelClass, time(), 'kc_query_versions');
    }

    /**
     * Build the inverted-index registry key for a row.
     *
     * @param int|string $id
     * @return string
     */
    public static function getCacheRegistryKey($id): string
    {
        $modelClass = static::class;
        return 'kc_reg:' . str_replace('\\', '_', $modelClass) . ':' . $id;
    }

    /**
     * Register a cache key against a specific row ID so it can be surgically evicted later.
     * Called from KCQueryBuilder after a successful cache write.
     *
     * @param int|string $id        Primary-key value
     * @param string     $cacheKey  The cache key to register
     */
    public static function registerCacheKey($id, string $cacheKey): void
    {
        if (empty($id) || empty($cacheKey)) {
            return;
        }
        $registryKey = static::getCacheRegistryKey($id);
        $keys = wp_cache_get($registryKey, static::$registryGroup);
        if (!is_array($keys)) {
            $keys = [];
        }
        if (!in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            // Registry entries survive a bit longer than the data they index
            wp_cache_set($registryKey, $keys, static::$registryGroup, 300);
        }
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
                // New row: just flush everything (no prior cache entry to patch)
                static::flushCache();
                return $wpdb->insert_id;
            } else {
                KCErrorLogger::instance()->error('KCBaseModel::save - Insert failed - Error: ' . $wpdb->last_error);
                KCErrorLogger::instance()->error('KCBaseModel::save - Last query: ' . $wpdb->last_query);
            }
        } else {
            $rowId = $this->attributes[$schema['primary_key']];
            $result = $wpdb->update(
                $table,
                $data,
                [$schema['primary_key'] => $rowId]
            );

            if ($result !== false) {
                // DSA: Surgical eviction — only invalidate cache keys that reference this row
                static::flushCacheForId($rowId);
                return $rowId;
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
            $saved = $this->save();
            if ($saved !== false && !is_wp_error($saved)) {
                static::flushCache();
                return true;
            }
            return false;
        }

        $table = $schema['table_name'] ? ($wpdb->prefix . $schema['table_name']) : $schema['user_table_name'];
        $result = $wpdb->delete($table, [$pk => $id]);

        if ($result !== false) {
            // DSA: Targeted eviction for the deleted row, then also clear the version
            // so any listing queries (which wouldn't have this id in the registry) are also invalidated.
            static::flushCacheForId($id);
            static::flushCache(); // bump version so list/count queries refresh too
        }

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
