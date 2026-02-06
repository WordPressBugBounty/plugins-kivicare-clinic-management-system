<?php

namespace App\models;

use App\baseClasses\KCBaseModel;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

class KCUserMeta extends KCBaseModel
{

    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        global $wpdb;
        return [
            'user_table_name' => $wpdb->usermeta,
            'primary_key' => 'umeta_id',
            'columns' => [
                'umetaId' => [
                    'column' => 'umeta_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'userId' => [
                    'column' => 'user_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'validators' => [
                        fn($value) => !empty($value) && $value > 0 ? true : 'User ID is required and must be greater than 0'
                    ],
                ],
                'metaKey' => [
                    'column' => 'meta_key',
                    'type' => 'string',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_key'],
                ],
                'metaValue' => [
                    'column' => 'meta_value',
                    'type' => 'text',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
            ],
            'timestamps' => false, // WordPress handles this
            'soft_deletes' => false,
        ];
    }

}
