<?php

namespace App\models;

use App\baseClasses\KCBaseModel;

defined('ABSPATH') or die('Something went wrong');

class KCMedicalHistory extends KCBaseModel
{
    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_medical_history',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'encounterId' => [
                    'column' => 'encounter_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        fn($value) => $value > 0 ? true : 'Invalid encounter ID'
                    ],
                ],
                'patientId' => [
                    'column' => 'patient_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        fn($value) => $value > 0 ? true : 'Invalid patient ID'
                    ],
                ],
                'type' => [
                    'column' => 'type',
                    'type' => 'varchar',
                    'nullable' => false,
                    'sanitizers' => ['sanitize_text_field'],
                    'validators' => [
                        fn($value) => !empty($value) ? true : 'Type is required'
                    ],
                ],
                'title' => [
                    'column' => 'title',
                    'type' => 'text',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'addedBy' => [
                    'column' => 'added_by',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        fn($value) => $value > 0 ? true : 'Invalid added by ID'
                    ],
                ],
                'createdAt' => [
                    'column' => 'created_at',
                    'type' => 'datetime',
                    'nullable' => true,
                ],
                'isFromTemplate' => [
                    'column' => 'is_from_template',
                    'type' => 'tinyint',
                    'nullable' => true,
                    'default' => 0,
                    'sanitizers' => ['intval'],
                ],
            ],
            'timestamps' => false, // We'll handle created_at manually
            'soft_deletes' => false,
        ];
    }

    /**
     * Get the patient this medical history belongs to
     */
    public function getPatient()
    {
        return KCPatient::find($this->patientId);
    }

    /**
     * Get the encounter this medical history belongs to
     */
    public function getEncounter()
    {
        return KCPatientEncounter::find($this->encounterId);
    }
}