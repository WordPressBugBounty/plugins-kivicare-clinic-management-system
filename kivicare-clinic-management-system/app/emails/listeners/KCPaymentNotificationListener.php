<?php

namespace App\emails\listeners;

use App\baseClasses\KCErrorLogger;
use App\emails\KCEmailSender;
use App\models\KCAppointment;
use App\models\KCPatient;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Payment Notification Listener - Handles email notifications for payment-related events
 */
class KCPaymentNotificationListener
{
    private KCEmailSender $emailSender;
    private static ?KCPaymentNotificationListener $instance = null;

    public function __construct()
    {
        $this->emailSender = KCEmailSender::get_instance();
        $this->initializeHooks();
    }

    public static function get_instance(): KCPaymentNotificationListener
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeHooks(): void
    {
        // Hook into payment pending event
        add_action('kc_payment_pending', [$this, 'handlePaymentPending'], 10, 1);

        // Hook into payment overdue event
        add_action('kc_payment_overdue', [$this, 'handlePaymentOverdue'], 10, 1);
    }

    /**
     * Handle payment pending event
     * 
     * @param array $paymentData Contains appointment and payment details
     */
    public function handlePaymentPending(array $paymentData): void
    {
        try {
            if (empty($paymentData['appointment_id'])) {
                KCErrorLogger::instance()->error('KCPaymentNotificationListener: Missing appointment_id in payment data');
                return;
            }

            // Get appointment details
            $appointment = KCAppointment::find($paymentData['appointment_id']);
            if (!$appointment) {
                KCErrorLogger::instance()->error('KCPaymentNotificationListener: Appointment not found for ID: ' . $paymentData['appointment_id']);
                return;
            }

            // Get patient details
            $patient = KCPatient::find($appointment->patient_id);
            if (!$patient) {
                KCErrorLogger::instance()->error('KCPaymentNotificationListener: Patient not found for ID: ' . $appointment->patient_id);
                return;
            }

            // Get patient user data
            $patientUser = get_userdata($patient->user_id);
            if (!$patientUser) {
                KCErrorLogger::instance()->error('KCPaymentNotificationListener: Patient user not found');
                return;
            }

            // Prepare template data
            $templateData = [
                'patient_name' => $patientUser->display_name,
                'appointment_date' => gmdate('F j, Y', strtotime($appointment->appointment_start_date)),
                'appointment_time' => $appointment->appointment_start_time,
                'amount_due' => $paymentData['amount_due'] ?? '0.00',
                'due_date' => $paymentData['due_date'] ?? '',
                'payment_url' => $paymentData['payment_url'] ?? '',
                'appointment_id' => $appointment->id,
                'current_date' => current_time('mysql'),
                'patient' => [
                    'id' => $patient->id,
                    'email' => $patientUser->user_email,
                    'name' => $patientUser->display_name,
                ],
                'appointment' => [
                    'id' => $appointment->id,
                    'date' => $appointment->appointment_start_date,
                    'time' => $appointment->appointment_start_time,
                ]
            ];

            // Send notification to patient
            $result = $this->emailSender->sendEmailByTemplate(
                KIVI_CARE_PREFIX . 'payment_pending',
                $patientUser->user_email,
                $templateData
            );

            if ($result) {
                KCErrorLogger::instance()->error('KCPaymentNotificationListener: Payment pending notification sent successfully to: ' . $patientUser->user_email);
            } else {
                KCErrorLogger::instance()->error('KCPaymentNotificationListener: Failed to send payment pending notification to: ' . $patientUser->user_email);
            }

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KCPaymentNotificationListener Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle payment overdue event
     * 
     * @param array $paymentData Contains appointment and payment details
     */
    public function handlePaymentOverdue(array $paymentData): void
    {
        // Can use same template or create a separate overdue template
        KCErrorLogger::instance()->error('KCPaymentNotificationListener: Payment overdue event received for appointment ID: ' . ($paymentData['appointment_id'] ?? 'unknown'));

        // Optionally send a more urgent notification
        $this->handlePaymentPending($paymentData);
    }
}
