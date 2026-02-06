<?php

namespace App\models;

use App\baseClasses\KCBaseModel;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class KCPatientMedicalReport
 * 
 * @property int $id
 * @property string|null $name
 * @property int $patient_id
 * @property string $upload_report
 * @property string|null $date
 */
class KCPatientMedicalReport extends KCBaseModel
{
    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_patient_medical_report',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'name' => [
                    'column' => 'name',
                    'type' => 'text',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
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
                'uploadReport' => [
                    'column' => 'upload_report',
                    'type' => 'varchar',
                    'nullable' => false,
                    'sanitizers' => ['sanitize_text_field'],
                    'validators' => [
                        fn($value) => !empty($value) ? true : 'Upload report value is required'
                    ],
                ],
                'date' => [
                    'column' => 'date',
                    'type' => 'datetime',
                    'nullable' => true,
                ],
            ],
            'timestamps' => false,
            'soft_deletes' => false,
        ];
    }

    /**
     * Get the patient this medical report belongs to
     */
    public function getPatient()
    {
        return KCPatient::find($this->patientId);
    }
}