<?php

namespace App\models;

use App\baseClasses\KCBaseModel;

defined('ABSPATH') or die('Something went wrong');

class KCAppointmentReminderMapping extends KCBaseModel
{
    /**
     * Define table structure and properties
     */

    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_appointment_reminder_mapping',
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
                'msgSendDate' => [
                    'column' => 'msg_send_date',
                    'type' => 'date',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'emailStatus' => [
                    'column' => 'email_status',
                    'type' => 'int',
                    'nullable' => true,
                    'default' => 0,
                    'sanitizers' => ['intval'],
                ],
                'smsStatus' => [
                    'column' => 'sms_status',
                    'type' => 'int',
                    'nullable' => true,
                    'default' => 0,
                    'sanitizers' => ['intval'],
                ],
                'whatsappStatus' => [
                    'column' => 'whatsapp_status',
                    'type' => 'int',
                    'nullable' => true,
                    'default' => 0,
                    'sanitizers' => ['intval'],
                ],
                'extra' => [
                    'column' => 'extra',
                    'type' => 'longtext',
                    'nullable' => true,
                ],
            ],
            'timestamps' => false,
            'soft_deletes' => false,
        ];
    }

    /**
     * Get the appointment associated with this reminder.
     */
    public function getAppointment()
    {
        return KCAppointment::find($this->appointmentId);
    }

    /**
     * Get the extra data as an array.
     */
    public function getExtraData(): array
    {
        return json_decode($this->extra, true) ?? [];
    }
}