<?php

namespace App\emails\listeners;

use App\baseClasses\KCErrorLogger;
use App\emails\KCEmailSender;
use App\models\KCAppointment;
use App\models\KCPatient;
use App\models\KCClinic;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Patient Check-In Notification Listener - Handles email notifications for patient clinic check-in/out
 */
class KCPatientCheckInNotificationListener
{
    private KCEmailSender $emailSender;
    private static ?KCPatientCheckInNotificationListener $instance = null;

    public function __construct()
    {
        $this->emailSender = KCEmailSender::get_instance();
        $this->initializeHooks();
    }

    public static function get_instance(): KCPatientCheckInNotificationListener
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeHooks(): void
    {
        // Hook into appointment status update event
        add_action('kc_appointment_status_update', [$this, 'handleAppointmentStatusUpdate'], 10, 3);
    }

    /**
     * Handle appointment status update event
     * 
     * @param int $appointmentId Appointment ID
     * @param int $status New status (1: Check-In, 2: Check-Out)
     * @param object $appointment Appointment object
     */
    public function handleAppointmentStatusUpdate(int $appointmentId, int $status, object $appointment): void
    {
        // Only proceed for Check-In (1) or Check-Out (2) status\
        if (!in_array($status, [KCAppointment::STATUS_CHECK_IN, KCAppointment::STATUS_CHECK_OUT])) {
            return;
        }

        try {
            // Get clinic ID from appointment
            $clinicId = $appointment->clinicId ?? $appointment->clinic_id ?? null;

            if (empty($clinicId)) {
                KCErrorLogger::instance()->error('KCPatientCheckInNotificationListener: Missing clinic_id in appointment data');
                return;
            }

            // Get clinic details
            $clinic = KCClinic::find($clinicId);
            if (!$clinic) {
                KCErrorLogger::instance()->error('KCPatientCheckInNotificationListener: Clinic not found for ID: ' . $clinicId);
                return;
            }

            // Determine event type
            $eventType = ($status === 1) ? 'Check-In' : 'Check-Out';

            // Get clinic email or default to admin
            $clinicEmail = $clinic->email ?? get_option('admin_email');

            // Send notification using context-based approach
            $result = $this->emailSender->sendEmailWithContext(
                KIVI_CARE_PREFIX . 'patient_clinic_check_in_check_out',
                'appointment',
                $appointmentId,
                'clinic',
                [
                    'to_override' => $clinicEmail, // Send to clinic admin
                    'custom_data' => [
                        'event_type' => $eventType,
                        'check_in_time' => current_time('mysql'),
                        'current_date' => current_time('mysql'),
                    ]
                ]
            );

            if ($result) {
                KCErrorLogger::instance()->error("KCPatientCheckInNotificationListener: {$eventType} notification sent successfully to clinic: " . $clinicEmail);
            } else {
                KCErrorLogger::instance()->error("KCPatientCheckInNotificationListener: Failed to send {$eventType} notification to clinic: " . $clinicEmail);
            }

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KCPatientCheckInNotificationListener Error: ' . $e->getMessage());
        }
    }
}
