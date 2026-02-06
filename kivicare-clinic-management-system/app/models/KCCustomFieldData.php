<?php

namespace App\models;

use App\baseClasses\KCBaseModel;

defined('ABSPATH') or die('Something went wrong');

class KCCustomFieldData extends KCBaseModel
{
    /**
     * Define table structure and properties
     */
    protected static array $schema = [
        'table_name' => 'kc_custom_fields_data',
        'primary_key' => 'id',
        'columns' => [
            'id' => [
                'column' => 'id',
                'type' => 'bigint',
                'nullable' => false,
                'auto_increment' => true,
            ],
            'moduleType' => [
                'column' => 'module_type',
                'type' => 'varchar',
                'nullable' => true,
                'sanitizers' => ['sanitize_text_field'],
            ],
            'moduleId' => [
                'column' => 'module_id',
                'type' => 'bigint',
                'nullable' => false,
                'sanitizers' => ['intval'],
                'validators' => [
                    [self::class, 'validateModuleId']
                ],
            ],
            'fieldsData' => [
                'column' => 'fields_data',
                'type' => 'longtext',
                'nullable' => false,
            ],
            'fieldId' => [
                'column' => 'field_id',
                'type' => 'bigint',
                'nullable' => true,
                'sanitizers' => ['intval'],
            ],
            'createdAt' => [
                'column' => 'created_at',
                'type' => 'datetime',
                'nullable' => true,
            ],
        ],
        'timestamps' => false, // We'll handle created_at manually
        'soft_deletes' => false,
    ];

    /**
     * Get the model's schema
     */
    public static function getSchema(): array
    {
        return static::$schema;
    }

    /**
     * Get the custom field this data belongs to
     */
    public function getField()
    {
        return KCCustomField::find($this->fieldId);
    }

    public static function validateModuleId($value)
    {
        return $value >= 0 ? true : 'Invalid module ID';
    }

}