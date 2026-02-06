<?php

namespace App\models;

use App\baseClasses\KCBaseModel;
use App\baseClasses\KCErrorLogger;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

class KCStaticData extends KCBaseModel
{
    /**
     * Define table structure and properties
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_static_data',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'type' => [
                    'column' => 'type',
                    'type' => 'varchar',
                    'nullable' => false,
                    'sanitizers' => ['sanitize_text_field'],
                    'validators' => [
                        function($value) {
                            if (empty($value)) {
                                return 'Type is required';
                            }
                            if (strlen($value) > 191) {
                                return 'Type cannot exceed 191 characters';
                            }
                            return true;
                        }
                    ],
                ],
                'label' => [
                    'column' => 'label',
                    'type' => 'text',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_textarea_field'],
                ],
                'value' => [
                    'column' => 'value',
                    'type' => 'text',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_textarea_field'],
                ],
                'parentId' => [
                    'column' => 'parent_id',
                    'type' => 'bigint',
                    'nullable' => true,
                    'sanitizers' => ['intval'],
                ],
                'status' => [
                    'column' => 'status',
                    'type' => 'bigint',
                    'nullable' => false,
                    'default' => 1,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        function($value) {
                            if (!is_numeric($value) || $value < 0) {
                                return 'Status must be a non-negative number';
                            }
                            return true;
                        }
                    ],
                ],
                'createdAt' => [
                    'column' => 'created_at',
                    'type' => 'datetime',
                    'nullable' => false,
                ],
            ],
            'timestamps' => false, // We'll handle created_at manually
            'soft_deletes' => false,
        ];
    }

    /**
     * Get the parent static data record
     */
    public function getParent()
    {
        if (empty($this->parentId)) {
            return null;
        }
        return static::find($this->parentId);
    }

    /**
     * Get all children of this static data record
     */
    public function getChildren()
    {
        return static::query()
            ->where('parent_id', $this->id)
            ->get();
    }

    /**
     * Get static data by type
     */
    public static function getByType(string $type)
    {
        return static::query()
            ->where('type', $type)
            ->get();
    }

    /**
     * Get active static data by type
     */
    public static function getActiveByType(string $type)
    {
        return static::query()
            ->select(['id', 'label', 'value'])
            ->where('type', $type)
            ->where('status', 1)
            ->get();
    }

    /**
     * Get static data hierarchy (parent-child relationships)
     */
    public static function getHierarchy(string $type)
    {
        $parents = static::query()
            ->where('type', $type)
            ->whereNull('parent_id')
            ->get();

        foreach ($parents as $parent) {
            $parent->children = $parent->getChildren();
        }

        return $parents;
    }

    /**
     * Create a new static data record with automatic created_at timestamp
     */
    public static function create(array $data)
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = current_time('mysql');
        }
        
        try {
            $result = parent::create($data);
            return $result;
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KCStaticData::create exception: ' . $e->getMessage());
            KCErrorLogger::instance()->error('KCStaticData::create exception trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Override save to handle created_at timestamp for new records
     */
    public function save(): int|WP_Error
    {
        global $wpdb;
        
        if (empty($this->id) && empty($this->createdAt)) {
            $this->createdAt = current_time('mysql');
        }
        
        
        try {
            $result = parent::save();
            
            if ($result) {
                KCErrorLogger::instance()->error('KCStaticData::save - Success - Result: ' . json_encode($result));
            } else {
                KCErrorLogger::instance()->error('KCStaticData::save - Failed - Last error: ' . $wpdb->last_error);
            }
            
            return $result;
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KCStaticData::save - Exception: ' . $e->getMessage());
            KCErrorLogger::instance()->error('KCStaticData::save - Exception trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
}
