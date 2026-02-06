<?php

namespace App\models;

use App\baseClasses\KCBaseModel;

defined('ABSPATH') or die('Something went wrong');

class KCMedicalProblem extends KCBaseModel
{
    /**
     * Define table structure and properties
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_medical_problems',
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
                'startDate' => [
                    'column' => 'start_date',
                    'type' => 'date',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'endDate' => [
                    'column' => 'end_date',
                    'type' => 'date',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'description' => [
                    'column' => 'description',
                    'type' => 'text',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'problemType' => [
                    'column' => 'problem_type',
                    'type' => 'varchar',
                    'nullable' => false,
                    'sanitizers' => ['sanitize_text_field'],
                    'validators' => [
                        fn($value) => !empty($value) ? true : 'Problem type is required'
                    ],
                ],
                'outcome' => [
                    'column' => 'outcome',
                    'type' => 'varchar',
                    'nullable' => false,
                    'sanitizers' => ['sanitize_text_field'],
                    'validators' => [
                        fn($value) => !empty($value) ? true : 'Outcome is required'
                    ],
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
            ],
            'timestamps' => false, // We'll handle created_at manually
            'soft_deletes' => false,
        ];
    }

    /**
     * Get the patient this medical problem belongs to
     */
    public function getPatient()
    {
        return KCPatient::find($this->patientId);
    }

    /**
     * Get the encounter this medical problem belongs to
     */
    public function getEncounter()
    {
        return KCPatientEncounter::find($this->encounterId);
    }
}