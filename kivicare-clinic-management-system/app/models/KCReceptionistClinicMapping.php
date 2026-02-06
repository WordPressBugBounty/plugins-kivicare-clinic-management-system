<?php

namespace App\models;

use App\baseClasses\KCBaseModel;

defined('ABSPATH') or die('Something went wrong');

class KCReceptionistClinicMapping extends KCBaseModel
{
    /**
     * Define table structure and properties
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_receptionist_clinic_mappings',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'receptionistId' => [
                    'column' => 'receptionist_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        fn($value) => $value > 0 ? true : 'Invalid receptionist ID'
                    ],
                ],
                'clinicId' => [
                    'column' => 'clinic_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        fn($value) => $value > 0 ? true : 'Invalid clinic ID'
                    ],
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
    }
    /**
     * Get the receptionist associated with this mapping
     */
    public function getReceptionist()
    {
        return KCReceptionist::find($this->receptionistId);
    }

    /**
     * Get the clinic associated with this mapping
     */
    public function getClinic()
    {
        return KCClinic::find($this->clinicId);
    }

    /**
     * get clinic id by receptionist id
     */
    public static function getClinicIdByReceptionistId($receptionist_id)
    {
        $mapping = self::query()->where('receptionistId', $receptionist_id)->first();
        return $mapping ? $mapping->clinicId : null;    
    }
}
