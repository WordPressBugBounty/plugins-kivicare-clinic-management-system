<?php

namespace App\models;

use App\baseClasses\KCBaseModel;

defined('ABSPATH') or die('Something went wrong');

class KCDoctorClinicMapping extends KCBaseModel
{
     /**
     * Define table structure and properties
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_doctor_clinic_mappings',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'doctorId' => [
                    'column' => 'doctor_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        fn($value) => $value > 0 ? true : 'Invalid doctor ID'
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
                'owner' => [
                    'column' => 'owner',
                    'type' => 'tinyint',
                    'nullable' => true,
                    'default' => 0,
                    'validators' => [
                        fn($value) => in_array($value, [0, 1]) ? true : 'Owner must be 0 or 1'
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
     * Get the doctor associated with this mapping
     */
    public function getDoctor()
    {
        return KCDoctor::find($this->doctorId);
    }

    /**
     * Get the clinic associated with this mapping
     */
    public function getClinic()
    {
        return KCClinic::find($this->clinicId);
    }
}