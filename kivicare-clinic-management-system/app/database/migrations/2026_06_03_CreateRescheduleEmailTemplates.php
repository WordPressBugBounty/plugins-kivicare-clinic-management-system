<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;
use App\emails\KCEmailTemplateManager;

defined('ABSPATH') or die('Something went wrong');

class CreateRescheduleEmailTemplates extends KCAbstractMigration
{
    public function run()
    {
        $manager = KCEmailTemplateManager::getInstance();

        $templates = [
            [
                'post_name'    => KIVI_CARE_PREFIX . 'appointment_rescheduled',
                'post_content' => '<p>Dear {{patient_name}},</p><p>Your appointment has been rescheduled.</p><p><strong>New Appointment Details:</strong></p><p>Date: {{appointment_date}}<br>Time: {{appointment_time}}<br>Doctor: {{doctor_name}}<br>Service: {{service_name}}</p><p>Clinic: {{clinic_name}}<br>Address: {{clinic_address}}</p><p>If you have any questions, please contact us at {{clinic_phone}}.</p><p>Thank you,<br>{{clinic_name}}</p>',
                'post_title'   => 'Appointment Rescheduled (Patient)',
                'post_type'    => KIVI_CARE_PREFIX . 'mail_tmp',
                'post_status'  => 'publish',
            ],
            [
                'post_name'    => KIVI_CARE_PREFIX . 'doctor_appointment_rescheduled',
                'post_content' => '<p>Hello Dr. {{doctor_name}},</p><p>An appointment has been rescheduled.</p><p><strong>Updated Appointment Details:</strong></p><p>Patient: {{patient_name}}<br>Date: {{appointment_date}}<br>Time: {{appointment_time}}<br>Service: {{service_name}}<br>Clinic: {{clinic_name}}</p><p>Thank you.</p>',
                'post_title'   => 'Appointment Rescheduled (Doctor)',
                'post_type'    => KIVI_CARE_PREFIX . 'mail_tmp',
                'post_status'  => 'publish',
            ],
            [
                'post_name'    => KIVI_CARE_PREFIX . 'clinic_appointment_rescheduled',
                'post_content' => '<p>Appointment Rescheduled</p><p>An appointment at {{clinic_name}} has been rescheduled on {{current_date}}.</p><p>Patient: {{patient_name}}<br>Doctor: {{doctor_name}}<br>New Date: {{appointment_date}}<br>New Time: {{appointment_time}}<br>Service: {{service_name}}</p><p>Thank you.</p>',
                'post_title'   => 'Appointment Rescheduled (Clinic)',
                'post_type'    => KIVI_CARE_PREFIX . 'mail_tmp',
                'post_status'  => 'publish',
            ],
        ];

        foreach ($templates as $template) {
            if (!$manager->templateExists($template['post_name'])) {
                wp_insert_post($template);
            }
        }
    }
}
