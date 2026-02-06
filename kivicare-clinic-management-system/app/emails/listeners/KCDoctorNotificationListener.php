<?php

namespace App\emails\listeners;

use App\baseClasses\KCErrorLogger;
use App\emails\KCEmailSender;
use App\models\KCDoctor;
use App\models\KCClinic;
use App\models\KCDoctorClinicMapping;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class KCDoctorNotificationListener
{
    private KCEmailSender $emailSender;
    private static ?KCDoctorNotificationListener $instance = null;

    public function __construct()
    {
        $this->emailSender = KCEmailSender::get_instance();
        $this->initializeHooks();
    }

    public static function get_instance(): KCDoctorNotificationListener
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeHooks(): void
    {
        // Hook into doctor creation/registration
        add_action('kc_doctor_save', [$this, 'handleDoctorRegistered'], 10, 2);
    }

    public function handleDoctorRegistered(array $doctorData): void
    {
        try {
            // Send notifications using the data directly from API
            $this->sendNotifications($doctorData);

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error in doctor notification handler: " . $e->getMessage());
        }
    }

    private function sendNotifications(array $doctorData): void
    {
        try {
            $doctorResult = $this->sendDoctorWelcomeNotification($doctorData);
            KCErrorLogger::instance()->error("Doctor welcome notification result: " . ($doctorResult ? 'Success' : 'Failed'));

            $adminResult = $this->sendAdminNotification($doctorData);
            KCErrorLogger::instance()->error("Admin notification result: " . ($adminResult ? 'Success' : 'Failed'));

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error sending notifications: " . $e->getMessage());
        }
    }

    private function sendDoctorWelcomeNotification(array $doctorData): bool
    {
        $doctorId = $doctorData['id'] ?? null;
        $tempPassword = $doctorData['temp_password'] ?? '';

        if (empty($doctorId)) {
            KCErrorLogger::instance()->error('Doctor ID missing for welcome notification');
            return false;
        }

        return $this->emailSender->sendEmailWithContext(
            KIVI_CARE_PREFIX . 'doctor_registration',
            'doctor',
            (int) $doctorId,
            'doctor',
            [
                'custom_data' => [
                    'user_password' => $tempPassword,
                    'temp_password' => $tempPassword,
                    'user_name' => $doctorData['username'] ?? '',
                    'user_email' => $doctorData['email'] ?? '',
                    'login_url' => wp_login_url(),
                    'site_url' => get_site_url(),
                    'current_date' => current_time('mysql'),
                    'user_role' => 'Doctor'
                ]
            ]
        );
    }

    private function sendClinicNotification(array $doctorData): bool
    {
        // Get clinic email from clinic_id
        $clinic = KCClinic::find($doctorData['clinic_id']);
        if (!$clinic || empty($clinic->email)) {
            KCErrorLogger::instance()->error("Clinic not found or no email for ID: " . $doctorData['clinic_id']);
            return false;
        }

        $templateData = [
            'clinic_name' => $clinic->name,
            'doctor_name' => $doctorData['first_name'] . ' ' . $doctorData['last_name'],
            'doctor_email' => $doctorData['email'],
            'doctor_specialties' => $doctorData['specialties'] ?? [],
            'doctor_qualifications' => $doctorData['qualifications'] ?? [],
            'doctor_contact' => $doctorData['contact_number'] ?? '',
            'current_date' => current_time('mysql'),
            'site_url' => get_site_url()
        ];

        return $this->emailSender->sendEmailByTemplate(
            KIVI_CARE_PREFIX . 'clinic_new_doctor',
            $clinic->email,
            $templateData
        );
    }

    private function sendAdminNotification(array $doctorData): bool
    {
        $doctorId = $doctorData['id'] ?? null;

        if (empty($doctorId)) {
            KCErrorLogger::instance()->error('Doctor ID missing for admin notification');
            return false;
        }

        return $this->emailSender->sendEmailWithContext(
            KIVI_CARE_PREFIX . 'admin_new_user_register',
            'doctor',
            (int) $doctorId,
            'doctor',
            [
                'to_override' => get_option('admin_email'),
                'custom_data' => [
                    'site_url' => get_site_url(),
                    'current_date' => current_time('mysql'),
                    'user_role' => 'Doctor',
                ]
            ]
        );
    }
}