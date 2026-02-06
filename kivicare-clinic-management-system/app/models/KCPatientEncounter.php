<?php

namespace App\models;

use App\baseClasses\KCBaseModel;

defined('ABSPATH') or die('Something went wrong');

class KCPatientEncounter extends KCBaseModel
{
    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_patient_encounters',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'encounterDate' => [
                    'column' => 'encounter_date',
                    'type' => 'date',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
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
                'doctorId' => [
                    'column' => 'doctor_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        fn($value) => $value > 0 ? true : 'Invalid doctor ID'
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
                'appointmentId' => [
                    'column' => 'appointment_id',
                    'type' => 'bigint',
                    'nullable' => true,
                    'sanitizers' => ['intval'],
                ],
                'description' => [
                    'column' => 'description',
                    'type' => 'text',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'status' => [
                    'column' => 'status',
                    'type' => 'tinyint',
                    'nullable' => true,
                    'default' => 0,
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
                'templateId' => [
                    'column' => 'template_id',
                    'type' => 'bigint',
                    'nullable' => true,
                    'sanitizers' => ['intval'],
                ],
            ],
            'timestamps' => false, // We'll handle created_at manually
            'soft_deletes' => false,
        ];
    }

    /**
     * Get the patient this encounter belongs to
     */
    public function getPatient()
    {
        return KCPatient::find($this->patientId);
    }

    /**
     * Get the clinic this encounter belongs to
     */
    public function getClinic()
    {
        return KCClinic::find($this->clinicId);
    }

    /**
     * Get the doctor associated with this encounter
     */
    public function getDoctor()
    {
        return KCDoctor::find($this->doctorId);
    }

    /**
     * Get the appointment associated with this encounter
     */
    public function getAppointment()
    {
        return KCAppointment::find($this->appointmentId);
    }

    /**
     * Get all medical problems for this encounter
     */
    public function getMedicalProblems()
    {
        return KCMedicalProblem::query()->where('encounterId', $this->id)->get();
    }

    /**
     * Get all prescriptions for this encounter
     */
    public function getPrescriptions()
    {
        return KCPrescription::query()->where('encounterId', $this->id)->get();
    }

    /**
     * Get all body charts for this encounter
     */
    public function getBodyCharts()
    {
        return KCEncounterBodyChart::query()->where('encounterId', $this->id)->get();
    }
}