<?php

namespace App\emails\listeners;

use App\baseClasses\KCErrorLogger;
use App\emails\KCEmailSender;
use App\models\KCPatient;
use App\models\KCClinic;
use App\models\kcPatientClinicMapping;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class KCPatientNotificationListener
{
    private KCEmailSender $emailSender;
    private static ?KCPatientNotificationListener $instance = null;

    public function __construct()
    {
        $this->emailSender = KCEmailSender::get_instance();
        $this->initializeHooks();
    }

    public static function get_instance(): KCPatientNotificationListener
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeHooks(): void
    {
        // Hook into patient creation/registration
        add_action('kc_patient_save', [$this, 'handlePatientRegistered'], 10, 1);
    }

    public function handlePatientRegistered(array $patientData): void
    {
        try {

            // Get complete patient data for email
            // Use the data directly from the hook (includes password)
            $fullPatientData = [
                'patient' => [
                    'id' => $patientData['id'],
                    'email' => $patientData['email'],
                    'username' => $patientData['username'],
                    'display_name' => $patientData['first_name'] . ' ' . $patientData['last_name'],
                    'first_name' => $patientData['first_name'],
                    'last_name' => $patientData['last_name'],
                    'mobile_number' => $patientData['contact_number'] ?? '',
                    'gender' => $patientData['gender'] ?? '',
                    'dob' => $patientData['dob'] ?? '',
                    'address' => $patientData['address'] ?? '',
                    'city' => $patientData['city'] ?? '',
                    'country' => $patientData['country'] ?? '',
                    'postal_code' => $patientData['postal_code'] ?? '',
                    'temp_password' => $patientData['temp_password']
                ],
                'registration_date' => $patientData['created_at'],
                'site_url' => get_site_url(),
                'login_url' => wp_login_url()
            ];

            if (!$fullPatientData) {
                KCErrorLogger::instance()->error("Failed to get patient data for ID: " . $patientData['id']);
                return;
            }

            // Send notifications
            $patientResult = $this->sendPatientWelcomeNotification($fullPatientData);

            $adminResult = $this->sendAdminNotification($fullPatientData);
            // $clinicResult = $this->sendClinicNotification($fullPatientData);

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error sending patient registration notifications: " . $e->getMessage());
        }
    }

    private function sendPatientWelcomeNotification(array $patientData): bool
    {
        $patientId = $patientData['patient']['id'] ?? null;
        $tempPassword = $patientData['patient']['temp_password'] ?? '';

        if (empty($patientId)) {
            return false;
        }

        // Use context-based email with temp password as custom data
        return $this->emailSender->sendEmailWithContext(
            KIVI_CARE_PREFIX . 'patient_register',
            'user',
            (int) $patientId,
            'patient',
            [
                'custom_data' => [
                    'user_password' => $tempPassword,
                    'user_email' => $patientData['patient']['email'],
                    'temp_password' => $tempPassword,
                    'user_role' => 'Patient',
                    'registration_date' => $patientData['registration_date'] ?? current_time('mysql'),
                    'site_url' => get_site_url(),
                    'login_url' => wp_login_url()
                ]
            ]
        );
    }

    private function sendClinicNotification(array $patientData): bool
    {
        if (empty($patientData['clinic']['email'])) {
            return false;
        }

        return $this->emailSender->sendEmailByTemplate(
            KIVI_CARE_PREFIX . 'clinic_new_patient',
            $patientData['clinic']['email'],
            $patientData
        );
    }

    private function sendAdminNotification(array $patientData): bool
    {
        $patientId = $patientData['patient']['id'] ?? null;

        if (empty($patientId)) {
            return false;
        }

        // Use context-based email for admin notification
        return $this->emailSender->sendEmailWithContext(
            KIVI_CARE_PREFIX . 'admin_new_user_register',
            'user',
            (int) $patientId,
            'patient',
            [
                'to_override' => get_option('admin_email'), // Override recipient to admin
                'custom_data' => [
                    'user_role' => 'Patient',
                    'registration_date' => $patientData['registration_date'] ?? current_time('mysql'),
                    'site_url' => get_site_url(),
                    'login_url' => wp_login_url()
                ]
            ]
        );
    }
}