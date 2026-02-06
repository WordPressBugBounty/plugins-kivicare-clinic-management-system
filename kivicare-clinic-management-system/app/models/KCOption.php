<?php

namespace App\models;

use App\baseClasses\KCBaseModel;

defined('ABSPATH') || exit;

class KCOption extends KCBaseModel
{
    /**
     * Define the option table schema
     */
    protected static function initSchema(): array
    {

        return [
            'table_name' => 'options',
            'primary_key' => 'option_id',
            'columns' => [
                'option_id' => [
                    'type' => 'bigint',
                    'auto_increment' => true,
                    'column' => 'option_id'
                ],
                'option_name' => [
                    'type' => 'varchar',
                    'column' => 'option_name',
                    'required' => true,
                    'sanitizers' => [
                        'sanitize_text_field'
                    ]
                ],
                'option_value' => [
                    'type' => 'varchar',
                    'column' => 'option_value',
                ],
                'autoload' => [
                    'type' => 'varchar',
                    'length' => 20,
                    'column' => 'autoload',
                    'default' => 'yes'
                ]
            ],
            'timestamps' => false,
            'soft_deletes' => false
        ];
    }

    /**
     * Get a single option with decoding and fallback
     *
     * @param string $key
     * @param mixed $default
     * @param string $base_prefix
     * @return mixed
     */
    public static function get(string $key, $default = null, string $base_prefix = KIVI_CARE_PREFIX)
    {
        $option = self::query()
            ->where('option_name', $base_prefix . $key)
            ->first();

        if (!$option || !isset($option->option_value)) {
            return $default;
        }

        return maybe_unserialize($option->option_value);
    }

    /**
     * Set an option value (handles serialization)
     *
     * @param string $key
     * @param mixed $value
     * @param string $autoload
     * @param string $base_prefix
     * @return bool
     */
    public static function set(string $key, $value, string $autoload = 'yes', string $base_prefix = KIVI_CARE_PREFIX): bool
    {
        $option = self::query()
            ->where('option_name', $base_prefix . $key)
            ->first();

        // Serialize explicitly once before passing to the model
        $serializedValue = maybe_serialize($value);

        if ($option) {
            // Update existing option
            $option->option_value = $serializedValue;
            return $option->save() !== false;
        } else {
            // Create new option
            $newOption = new self();
            $newOption->option_name = KIVI_CARE_PREFIX . $key;
            $newOption->option_value = $serializedValue;
            $newOption->autoload = $autoload;
            return $newOption->save() !== false;
        }
    }

    /**
     * Get multiple options in one query
     *
     * @param array $keys
     * @param string $base_prefix
     * @return array
     */
    public static function getMultiple(array $keys, string $base_prefix = KIVI_CARE_PREFIX): array
    {
        global $wpdb;

        $prefixedKeys = array_map(function ($key) use ($base_prefix) {
            return $base_prefix . $key;
        }, $keys);

        $placeholders = implode(',', array_fill(0, count($prefixedKeys), '%s'));
        $sql = "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($placeholders)";
        $prepared_sql = $wpdb->prepare( $sql, $prefixedKeys ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $prepared_sql, OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $options = [];

        foreach ($keys as $key) {
            $fullKey = $base_prefix . $key;

            if (isset($results[$fullKey])) {
                $options[$key] = maybe_unserialize($results[$fullKey]->option_value);
            } else {
                $options[$key] = null;
            }
        }

        return $options;
    }

    /**
     * Delete an option
     *
     * @param string $key
     * @param string $base_prefix
     * @return bool
     */
    public static function deleteOption(string $key, string $base_prefix = KIVI_CARE_PREFIX): bool
    {
        return self::query()
            ->where('option_name', $base_prefix . $key)
            ->delete() > 0;
    }
}
