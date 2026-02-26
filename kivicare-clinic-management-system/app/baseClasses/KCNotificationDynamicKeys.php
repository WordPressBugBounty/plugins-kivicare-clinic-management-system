<?php

namespace App\baseClasses;

/**
 * Dynamic Keys Manager - Handles dynamic placeholders for notification templates
 */
class KCNotificationDynamicKeys
{
    private array $dynamicKeys;

    public function __construct()
    {
        $this->initializeDynamicKeys();
    }

    /**
     * Initialize dynamic keys for email/SMS templates
     */
    private function initializeDynamicKeys(): void
    {
        $this->dynamicKeys = [
            'kivicare_book_prescription' => [
                '{{prescription}}',
                '{{clinic_name}}',
                '{{clinic_email}}',
                '{{clinic_contact_number}}',
                '{{clinic_address}}',
                '{{doctor_name}}',
                '{{doctor_email}}',
                '{{doctor_contact_number}}',
                '{{current_date}}',
                '{{current_date_time}}',
                '{{service_name}}',
            ],
            'kivicare_payment_pending' => [
                '{{current_date}}',
                '{{current_date_time}}'
            ],
            'kivicare_meet_link' => [
                '{{appointment_date}}',
                '{{appointment_time}}',
                '{{patient_name}}',
                '{{meet_link}}',
                '{{meet_event_link}}',
                '{{clinic_name}}',
                '{{clinic_email}}',
                '{{clinic_contact_number}}',
                '{{clinic_address}}',
                '{{doctor_name}}',
                '{{doctor_email}}',
                '{{doctor_contact_number}}',
                '{{current_date}}',
                '{{current_date_time}}',
            ],
            'kivicare_add_doctor_meet_link' => [
                '{{appointment_date}}',
                '{{appointment_time}}',
                '{{meet_link}}',
                '{{meet_event_link}}',
                '{{clinic_name}}',
                '{{clinic_email}}',
                '{{clinic_contact_number}}',
                '{{clinic_address}}',
                '{{patient_name}}',
                '{{patient_email}}',
                '{{patient_contact_number}}',
                '{{current_date}}',
                '{{current_date_time}}',
            ],
            'kivicare_book_appointment' => [
                '{{appointment_date}}',
                '{{appointment_time}}',
                '{{service_name}}',
                '{{clinic_name}}',
                '{{clinic_email}}',
                '{{clinic_contact_number}}',
                '{{clinic_address}}',
                '{{doctor_name}}',
                '{{doctor_email}}',
                '{{doctor_contact_number}}',
                '{{current_date}}',
                '{{current_date_time}}',
                '{{service_name}}',
            ],
            'kivicare_book_appointment_reminder' => [
                '{{appointment_date}}',
                '{{appointment_time}}',
                '{{clinic_name}}',
                '{{clinic_email}}',
                '{{clinic_contact_number}}',
                '{{clinic_address}}',
                '{{doctor_name}}',
                '{{doctor_email}}',
                '{{doctor_contact_number}}',
                '{{current_date}}',
                '{{current_date_time}}',
                '{{meet_link}}',
                '{{meet_event_link}}',
                '{{zoom_link}}',
            ],
            'kivicare_book_appointment_reminder_for_doctor' => [
                '{{appointment_date}}',
                '{{appointment_time}}',
                '{{clinic_name}}',
                '{{clinic_email}}',
                '{{clinic_contact_number}}',
                '{{clinic_address}}',
                '{{doctor_name}}',
                '{{doctor_email}}',
                '{{patient_name}}',
                '{{doctor_contact_number}}',
                '{{current_date}}',
                '{{current_date_time}}',
                '{{meet_link}}',
                '{{meet_event_link}}',
                '{{zoom_link}}',
            ],
            'kivicare_patient_register' => [
                '{{user_email}}',
                '{{user_password}}',
                '{{login_url}}',
                '{{widgets_login_url}}',
                '{{current_date}}',
                '{{current_date_time}}',
                '{{appointment_page_url}}'
            ],
            'kivicare_receptionist_register' => [
                '{{user_email}}',
                '{{user_password}}',
                '{{login_url}}',
                '{{current_date}}',
                '{{current_date_time}}'
            ],
            'kivicare_doctor_registration' => [
                '{{user_email}}',
                '{{user_name}}',
                '{{user_password}}',
                '{{login_url}}',
                '{{current_date}}',
                '{{current_date_time}}'
            ],
            'kivicare_doctor_book_appointment' => [
                '{{appointment_date}}',
                '{{appointment_time}}',
                '{{service_name}}',
                '{{patient_name}}',
                '{{patient_email}}',
                '{{clinic_name}}',
                '{{clinic_email}}',
                '{{clinic_contact_number}}',
                '{{clinic_address}}',
                '{{current_date}}',
                '{{current_date_time}}',
            ],
            'kivicare_resend_user_credential' => [
                '{{user_email}}',
                '{{user_name}}',
                '{{user_password}}',
                '{{login_url}}',
                '{{current_date}}',
                '{{current_date_time}}'
            ],
            'kivicare_cancel_appointment' => [
                '{{appointment_date}}',
                '{{appointment_time}}',
                '{{clinic_name}}',
                '{{clinic_email}}',
                '{{clinic_contact_number}}',
                '{{clinic_address}}',
                '{{doctor_name}}',
                '{{doctor_email}}',
                '{{doctor_contact_number}}',
                '{{current_date}}',
                '{{current_date_time}}',
            ],
            'kivicare_zoom_link' => [
                '{{appointment_date}}',
                '{{appointment_time}}',
                '{{zoom_link}}',
                '{{clinic_name}}',
                '{{clinic_email}}',
                '{{clinic_contact_number}}',
                '{{clinic_address}}',
                '{{doctor_name}}',
                '{{doctor_email}}',
                '{{doctor_contact_number}}',
                '{{current_date}}',
                '{{current_date_time}}',
            ],
            'kivicare_add_doctor_zoom_link' => [
                '{{appointment_date}}',
                '{{appointment_time}}',
                '{{add_doctor_zoom_link}}',
                '{{patient_name}}',
                '{{patient_email}}',
                '{{patient_contact_number}}',
                '{{clinic_name}}',
                '{{clinic_email}}',
                '{{clinic_contact_number}}',
                '{{clinic_address}}',
                '{{current_date}}',
                '{{current_date_time}}',
            ],
            'kivicare_clinic_admin_registration' => [
                '{{user_email}}',
                '{{user_name}}',
                '{{user_password}}',
                '{{login_url}}',
                '{{current_date}}',
                '{{current_date_time}}'
            ],
            'kivicare_clinic_book_appointment' => [
                '{{appointment_date}}',
                '{{appointment_time}}',
                '{{service_name}}',
                '{{patient_name}}',
                '{{patient_email}}',
                '{{patient_contact_number}}',
                '{{doctor_name}}',
                '{{doctor_email}}',
                '{{doctor_contact_number}}',
                '{{current_date}}',
                '{{current_date_time}}',
            ],
            'kivicare_encounter_close' => [
                '{{total_amount}}',
                '{{current_date}}',
                '{{current_date_time}}'
            ],
            'kivicare_patient_report' => [
                '{{current_date}}',
                '{{current_date_time}}',
            ],
            'kivicare_add_appointment' => [
                '{{appointment_date}}',
                '{{appointment_time}}',
                '{{current_date}}',
                '{{current_date_time}}',
                '{{clinic_name}}',
                '{{clinic_email}}',
                '{{clinic_contact_number}}',
                '{{clinic_address}}',
                '{{doctor_name}}',
                '{{doctor_email}}',
                '{{doctor_contact_number}}',
                '{{service_name}}',
            ],
            'kivicare_user_verified' => [
                '{{current_date}}',
                '{{login_url}}',
                '{{current_date_time}}'
            ],
            'kivicare_admin_new_user_register' => [
                '{{site_url}}',
                '{{current_date}}',
                '{{user_name}}',
                '{{user_email}}',
                '{{user_contact}}',
                '{{user_role}}',
                '{{current_date_time}}'
            ],
            'kivicare_patient_clinic_check_in_check_out' => [
                '{{patient_name}}',
                '{{patient_email}}',
                '{{current_date}}',
                '{{current_date_time}}'
            ],
            'kivicare_patient_invoice' => [
                '{{current_date}}',
                '{{current_date_time}}'
            ],
            'kivicare_patient_prescription' => [
                '{{prescription}}',
                '{{clinic_name}}',
                '{{clinic_email}}',
                '{{clinic_contact_number}}',
                '{{clinic_address}}',
                '{{doctor_name}}',
                '{{doctor_email}}',
                '{{doctor_contact_number}}',
                '{{current_date}}',
                '{{current_date_time}}',
            ],
            'kiviCare_default_event_template' => [
                '{{appointment_date}}',
                '{{appointment_time}}',
                '{{service_name}}',
                '{{patient_name}}',
                '{{patient_email}}',
                '{{doctor_name}}',
                '{{doctor_email}}',
                '{{clinic_name}}',
                '{{clinic_email}}',
                '{{clinic_address}}',
            ]
        ];

        $this->dynamicKeys = apply_filters('kivicare_template_dynamic_keys', $this->dynamicKeys);
    }

    /**
     * Get dynamic keys for a specific template
     */
    public function getDynamicKeys(string $templateName): array
    {
        return $this->dynamicKeys[$templateName] ?? [];
    }

    /**
     * Get all dynamic keys
     */
    public function getAllDynamicKeys(): array
    {
        return $this->dynamicKeys;
    }

    /**
     * Add dynamic keys for a template
     */
    public function addDynamicKeys(string $templateName, array $keys): void
    {
        if (!isset($this->dynamicKeys[$templateName])) {
            $this->dynamicKeys[$templateName] = [];
        }
        
        $this->dynamicKeys[$templateName] = array_merge($this->dynamicKeys[$templateName], $keys);
        $this->dynamicKeys[$templateName] = array_unique($this->dynamicKeys[$templateName]);
    }

    /**
     * Remove dynamic keys for a template
     */
    public function removeDynamicKeys(string $templateName, array $keys): void
    {
        if (!isset($this->dynamicKeys[$templateName])) {
            return;
        }
        
        $this->dynamicKeys[$templateName] = array_diff($this->dynamicKeys[$templateName], $keys);
    }

    /**
     * Check if a template has specific dynamic keys
     */
    public function hasKeys(string $templateName, array $keys): bool
    {
        $templateKeys = $this->getDynamicKeys($templateName);
        return !empty(array_intersect($keys, $templateKeys));
    }

    /**
     * Validate if content contains required keys for template
     */
    public function validateTemplateKeys(string $templateName, string $content): array
    {
        $templateKeys = $this->getDynamicKeys($templateName);
        $missingKeys = [];
        
        foreach ($templateKeys as $key) {
            if (strpos($content, $key) === false) {
                $missingKeys[] = $key;
            }
        }
        
        return $missingKeys;
    }

    /**
     * Extract keys from content
     */
    public function extractKeysFromContent(string $content): array
    {
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
        return array_unique($matches[0]);
    }

    /**
     * Get common keys used across templates
     */
    public function getCommonKeys(): array
    {
        return [
            '{{current_date}}',
            '{{current_date_time}}',
            '{{clinic_name}}',
            '{{clinic_email}}',
            '{{clinic_contact_number}}',
            '{{clinic_address}}',
            '{{user_name}}',
            '{{user_email}}',
            '{{login_url}}',
            '{{site_url}}'
        ];
    }

    /**
     * Get appointment-related keys
     */
    public function getAppointmentKeys(): array
    {
        return [
            '{{appointment_date}}',
            '{{appointment_time}}',
            '{{appointment_id}}',
            '{{doctor_name}}',
            '{{doctor_email}}',
            '{{doctor_contact_number}}',
            '{{patient_name}}',
            '{{patient_email}}',
            '{{patient_contact_number}}',
            '{{service_name}}',
            '{{total_amount}}'
        ];
    }

    /**
     * Get user registration keys
     */
    public function getUserRegistrationKeys(): array
    {
        return [
            '{{user_name}}',
            '{{user_email}}',
            '{{user_password}}',
            '{{user_role}}',
            '{{user_contact}}',
            '{{login_url}}',
            '{{widgets_login_url}}',
            '{{appointment_page_url}}'
        ];
    }

    /**
     * Get video conference keys
     */
    public function getVideoConferenceKeys(): array
    {
        return [
            '{{zoom_link}}',
            '{{add_doctor_zoom_link}}',
            '{{meet_link}}',
            '{{meet_event_link}}'
        ];
    }
}
