<?php

namespace App\models;

use App\baseClasses\KCBaseModel;

defined('ABSPATH') or die('Something went wrong');

class KCCustomField extends KCBaseModel
{

    /**
     * Define table structure and properties
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_custom_fields',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'int',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                // 'name' => [
                //     'column' => 'name',
                //     'type' => 'string',
                //     'nullable' => false,
                //     'sanitizers' => ['sanitize_text_field'],
                        // 'validators' => [
                        //     [self::class, 'validateName']
                        // ],
                // ],
                // 'type' => [
                //     'column' => 'type',
                //     'type' => 'string',
                //     'nullable' => false,
                //     'sanitizers' => ['sanitize_text_field'],
                        // 'validators' => [
                        //     [self::class, 'validateType']
                        // ],
                // ],
                // 'module' => [
                //     'column' => 'module',
                //     'type' => 'string',
                //     'nullable' => false,
                //     'sanitizers' => ['sanitize_text_field'],
                        // 'validators' => [
                        //     [self::class, 'validateModule']
                        // ],
                // ],
                'moduleType' => [
                    'column' => 'module_type',
                    'type' => 'string',
                    'nullable' => false,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'fields' => [
                    'column' => 'fields',
                    'type' => 'json',
                    'nullable' => false,
                ],
                'moduleId' => [
                    'column' => 'module_id',
                    'type' => 'int',
                    'nullable' => false,
                    'default' => 1
                ],
                // 'options' => [
                //     'column' => 'options',
                //     'type' => 'json',
                //     'nullable' => true,
                // ],
                'status' => [
                    'column' => 'status',
                    'type' => 'int',
                    'nullable' => false,
                    'default' => 1,
                    'validators' => [
                        [self::class, 'validateStatus']
                    ],
                ],
                // 'required' => [
                //     'column' => 'required',
                //     'type' => 'int',
                //     'nullable' => false,
                //     'default' => 0,
                        // 'validators' => [
                        //     [self::class, 'validateRequired']
                        // ],
                // ],
                // 'placeholder' => [
                //     'column' => 'placeholder',
                //     'type' => 'string',
                //     'nullable' => true,
                //     'sanitizers' => ['sanitize_text_field'],
                // ],
                // 'order' => [
                //     'column' => 'order',
                //     'type' => 'int',
                //     'nullable' => true,
                //     'default' => 0,
                //     'sanitizers' => ['intval'],
                // ],
                'createdAt' => [
                    'column' => 'created_at',
                    'type' => 'datetime',
                    'nullable' => false,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
            ],
            // 'timestamps' => true,
            'soft_deletes' => false,
        ];
    }

    /**
     * Get all data associated with this custom field
     */
    public function getData()
    {
        return KCCustomFieldData::query()
            ->where('fieldId', $this->id)
            ->get();
    }

    /**
     * Get options as array
     */
    public function getOptions(): array
    {
        return json_decode($this->options, true) ?? [];
    }

    // ==========================
    // Validator Methods
    // ==========================

    public static function validateName($value)
    {
        return !empty($value) ? true : 'Field name is required';
    }

    public static function validateType($value)
    {
        $valid = ['text', 'number', 'date', 'time', 'textarea', 'select', 'checkbox', 'radio'];
        return in_array($value, $valid) ? true : 'Invalid field type';
    }

    public static function validateModule($value)
    {
        $valid = ['patient', 'doctor', 'appointment', 'clinic', 'prescription'];
        return in_array($value, $valid) ? true : 'Invalid module';
    }

    public static function validateStatus($value)
    {
        return in_array($value, [0, 1]) ? true : 'Status must be 0 or 1';
    }

    public static function validateRequired($value)
    {
        return in_array($value, [0, 1]) ? true : 'Required must be 0 or 1';
    }
}
