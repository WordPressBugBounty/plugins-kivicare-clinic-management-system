<?php

namespace App\emails\listeners;

use App\baseClasses\KCErrorLogger;
use App\emails\KCEmailSender;
use App\models\KCReceptionist;
use App\models\KCClinic;
use App\models\KCReceptionistClinicMapping;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class KCReceptionistNotificationListener
{
    private KCEmailSender $emailSender;
    private static ?KCReceptionistNotificationListener $instance = null;

    public function __construct()
    {
        $this->emailSender = KCEmailSender::get_instance();
        $this->initializeHooks();
    }

    public static function get_instance(): KCReceptionistNotificationListener
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeHooks(): void
    {
        // Hook into receptionist creation
        add_action('kc_receptionist_save', [$this, 'handleReceptionistRegistered'], 10, 1);

    }

    public function handleReceptionistRegistered(array $receptionistData): void
    {
        try {

            // Get complete receptionist data for email
            $fullReceptionistData = [
                'receptionist' => [
                    'id' => $receptionistData['id'],
                    'email' => $receptionistData['email'],
                    'username' => $receptionistData['user_name'],
                    'display_name' => $receptionistData['first_name'] . ' ' . $receptionistData['last_name'],
                    'first_name' => $receptionistData['first_name'],
                    'last_name' => $receptionistData['last_name'],
                    'mobile_number' => $receptionistData['contact_number'] ?? '',
                    'gender' => $receptionistData['gender'] ?? '',
                    'dob' => $receptionistData['dob'] ?? '',
                    'address' => $receptionistData['address'] ?? '',
                    'city' => $receptionistData['city'] ?? '',
                    'country' => $receptionistData['country'] ?? '',
                    'postal_code' => $receptionistData['postal_code'] ?? '',
                    'temp_password' => $receptionistData['user_password']
                ],
                'registration_date' => $receptionistData['created_at'],
                'site_url' => get_site_url(),
                'login_url' => wp_login_url()
            ];


            if (!$fullReceptionistData) {
                KCErrorLogger::instance()->error("Failed to get receptionist data for ID: " . $receptionistData['id']);
                return;
            }

            // Send notifications
            $receptionistResult = $this->sendReceptionistWelcomeNotification($fullReceptionistData);

            $adminResult = $this->sendAdminNotification($fullReceptionistData);

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error sending receptionist registration notifications: " . $e->getMessage());
        }
    }

    private function sendReceptionistWelcomeNotification(array $receptionistData): bool
    {
        $receptionistId = $receptionistData['receptionist']['id'] ?? null;
        $tempPassword = $receptionistData['receptionist']['temp_password'] ?? '';

        if (empty($receptionistId)) {
            return false;
        }

        return $this->emailSender->sendEmailWithContext(
            KIVI_CARE_PREFIX . 'receptionist_register',
            'user',
            (int) $receptionistId,
            'patient',
            [
                'custom_data' => [
                    'user_password' => $tempPassword,
                    'temp_password' => $tempPassword,
                    'user_role' => 'Receptionist',
                    'user_email' => $receptionistData['receptionist']['email'] ?? '',
                    'current_date' => gmdate('Y-m-d'),
                    'current_date_time' => current_time('mysql'),
                    'site_url' => get_site_url(),
                    'login_url' => wp_login_url()
                ]
            ]
        );
    }

    private function sendAdminNotification(array $receptionistData): bool
    {
        $receptionistId = $receptionistData['receptionist']['id'] ?? null;

        if (empty($receptionistId)) {
            return false;
        }

        return $this->emailSender->sendEmailWithContext(
            KIVI_CARE_PREFIX . 'admin_new_user_register',
            'user',
            (int) $receptionistId,
            'patient',
            [
                'to_override' => get_option('admin_email'),
                'custom_data' => [
                    'user_role' => 'Receptionist',
                    'site_url' => get_site_url(),
                    'current_date' => gmdate('Y-m-d'),
                    'registration_date' => $receptionistData['registration_date'] ?? current_time('mysql'),
                    'login_url' => wp_login_url()
                ]
            ]
        );
    }
}