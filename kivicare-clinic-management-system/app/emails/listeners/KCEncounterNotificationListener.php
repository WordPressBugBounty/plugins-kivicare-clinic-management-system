<?php

namespace App\emails\listeners;

use App\baseClasses\KCErrorLogger;
use App\emails\KCEmailSender;
use App\models\KCAppointment;
use App\models\KCDoctor;
use App\models\KCPatient;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Encounter Notification Listener - Handles email notifications for encounter events
 */
class KCEncounterNotificationListener
{
    private KCEmailSender $emailSender;
    private static ?KCEncounterNotificationListener $instance = null;

    public function __construct()
    {
        $this->emailSender = KCEmailSender::get_instance();
        $this->initializeHooks();
    }

    public static function get_instance(): KCEncounterNotificationListener
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeHooks(): void
    {
        // Hook into encounter close event
        add_action('kc_encounter_closed', [$this, 'handleEncounterClosed'], 10, 1);
    }

    /**
     * Handle encounter closed event
     * 
     * @param array $encounterData Contains encounter details including prescription
     */
    public function handleEncounterClosed(array $encounterData): void
    {
        try {
            if (empty($encounterData['appointment_id'])) {
                KCErrorLogger::instance()->error('KCEncounterNotificationListener: Missing appointment_id in encounter data');
                return;
            }

            // Get appointment details
            $appointment = KCAppointment::find($encounterData['appointment_id']);
            if (!$appointment) {
                KCErrorLogger::instance()->error('KCEncounterNotificationListener: Appointment not found for ID: ' . $encounterData['appointment_id']);
                return;
            }

            // Get patient details
            $patient = KCPatient::find($appointment->patient_id);
            if (!$patient) {
                KCErrorLogger::instance()->error('KCEncounterNotificationListener: Patient not found for ID: ' . $appointment->patient_id);
                return;
            }

            // Get patient user data
            $patientUser = get_userdata($patient->user_id);
            if (!$patientUser) {
                KCErrorLogger::instance()->error('KCEncounterNotificationListener: Patient user not found for user_id: ' . $patient->user_id);
                return;
            }

            // Get doctor details
            $doctor = KCDoctor::find($appointment->doctor_id);
            $doctorUser = $doctor ? get_userdata($doctor->user_id) : null;
            $doctorName = $doctorUser ? $doctorUser->display_name : 'N/A';

            // Prepare template data
            $templateData = [
                'patient_name' => $patientUser->display_name,
                'appointment_date' => gmdate('F j, Y', strtotime($appointment->appointment_start_date)),
                'doctor_name' => $doctorName,
                'prescription' => $encounterData['prescription'] ?? '',
                'encounter_notes' => $encounterData['notes'] ?? '',
                'clinic_name' => $encounterData['clinic_name'] ?? '',
                'current_date' => current_time('mysql'),
                'patient' => [
                    'id' => $patient->id,
                    'email' => $patientUser->user_email,
                    'name' => $patientUser->display_name,
                ],
                'doctor' => [
                    'name' => $doctorName,
                ],
                'appointment' => [
                    'date' => $appointment->appointment_start_date,
                    'time' => $appointment->appointment_start_time,
                ]
            ];

            // Send notification to patient
            $result = $this->emailSender->sendEmailByTemplate(
                KIVI_CARE_PREFIX . 'encounter_close',
                $patientUser->user_email,
                $templateData
            );

            if ($result) {
                KCErrorLogger::instance()->error('KCEncounterNotificationListener: Encounter close notification sent successfully to patient: ' . $patientUser->user_email);
            } else {
                KCErrorLogger::instance()->error('KCEncounterNotificationListener: Failed to send encounter close notification to patient: ' . $patientUser->user_email);
            }

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KCEncounterNotificationListener Error: ' . $e->getMessage());
        }
    }
}
