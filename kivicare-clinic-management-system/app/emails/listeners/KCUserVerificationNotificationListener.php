<?php

namespace App\emails\listeners;

use App\baseClasses\KCErrorLogger;
use App\emails\KCEmailSender;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * User Verification Notification Listener - Handles email notifications when users are verified by admin
 */
class KCUserVerificationNotificationListener
{
    private KCEmailSender $emailSender;
    private static ?KCUserVerificationNotificationListener $instance = null;

    public function __construct()
    {
        $this->emailSender = KCEmailSender::get_instance();
        $this->initializeHooks();
    }

    public static function get_instance(): KCUserVerificationNotificationListener
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeHooks(): void
    {
        // Hook into user verification event
        add_action('kc_user_verified', [$this, 'handleUserVerified'], 10, 1);
    }

    /**
     * Handle user verified event
     * 
     * @param array $userData Contains user ID and verification details
     */
    public function handleUserVerified(array $userData): void
    {
        try {
            if (empty($userData['user_id'])) {
                KCErrorLogger::instance()->error('KCUserVerificationNotificationListener: Missing user_id in verification data');
                return;
            }

            // Get user data
            $user = get_userdata($userData['user_id']);
            if (!$user) {
                KCErrorLogger::instance()->error('KCUserVerificationNotificationListener: User not found for ID: ' . $userData['user_id']);
                return;
            }

            // Prepare template data
            $templateData = [
                'user_name' => $user->display_name,
                'user_email' => $user->user_email,
                'current_date' => current_time('mysql'),
                'login_url' => wp_login_url(),
                'site_url' => get_site_url(),
                'verification_date' => $userData['verification_date'] ?? current_time('mysql'),
                'verified_by' => $userData['verified_by'] ?? 'Admin',
            ];

            // Send notification to user
            $result = $this->emailSender->sendEmailByTemplate(
                KIVI_CARE_PREFIX . 'user_verified',
                $user->user_email,
                $templateData
            );

            if ($result) {
                KCErrorLogger::instance()->error('KCUserVerificationNotificationListener: Verification notification sent successfully to: ' . $user->user_email);
            } else {
                KCErrorLogger::instance()->error('KCUserVerificationNotificationListener: Failed to send verification notification to: ' . $user->user_email);
            }

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KCUserVerificationNotificationListener Error: ' . $e->getMessage());
        }
    }
}
