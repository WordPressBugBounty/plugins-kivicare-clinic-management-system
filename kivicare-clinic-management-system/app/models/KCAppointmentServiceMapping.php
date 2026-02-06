<?php

namespace App\models;

use App\baseClasses\KCBaseModel;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class KCAppointmentServiceMapping
 * 
 * @property int $id
 * @property int $appointmentId
 * @property int $serviceId
 * @property int $status
 * @property string $createdAt
 */
class KCAppointmentServiceMapping extends KCBaseModel
{
    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_appointment_service_mapping',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'appointmentId' => [
                    'column' => 'appointment_id',
                    'type' => 'bigint',
                    'nullable' => true,
                    'default' => 0,
                    'sanitizers' => ['intval'],
                ],
                'serviceId' => [
                    'column' => 'service_id',
                    'type' => 'int',
                    'nullable' => true,
                    'default' => 0,
                    'sanitizers' => ['intval'],
                ],
                'status' => [
                    'column' => 'status',
                    'type' => 'int',
                    'nullable' => true,
                    'default' => 0,
                    'validators' => [
                        fn($value) => is_numeric($value) ? true : 'Status must be numeric'
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
     * Get the appointment associated with this mapping
     */
    public function getAppointment()
    {
        // Use the correct field name 'id' instead of 'appointmentId'
        return KCAppointment::query()
            ->where('id', '=', $this->appointmentId)
            ->first();
    }

    /**
     * Get the service associated with this mapping
     */
    public function getService()
    {
        return KCService::find($this->serviceId);
    }
}