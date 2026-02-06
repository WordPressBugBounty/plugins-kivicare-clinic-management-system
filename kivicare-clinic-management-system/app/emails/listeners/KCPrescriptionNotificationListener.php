<?php

namespace App\emails\listeners;

use App\baseClasses\KCErrorLogger;
use App\emails\KCEmailSender;
use App\models\KCPatient;
use App\models\KCDoctor;
use App\models\KCClinic;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Prescription Notification Listener - Handles email notifications when prescriptions are created
 */
class KCPrescriptionNotificationListener
{
    private KCEmailSender $emailSender;
    private static ?KCPrescriptionNotificationListener $instance = null;

    public function __construct()
    {
        $this->emailSender = KCEmailSender::get_instance();
        $this->initializeHooks();
    }

    public static function get_instance(): KCPrescriptionNotificationListener
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeHooks(): void
    {
        // Hook into prescription creation event
        add_action('kc_prescription_created', [$this, 'handlePrescriptionCreated'], 10, 1);

        // Hook into prescription updated event
        add_action('kc_prescription_updated', [$this, 'handlePrescriptionUpdated'], 10, 1);
    }

    /**
     * Handle prescription created event
     * 
     * @param array $prescriptionData Contains prescription, patient, and doctor details
     */
    public function handlePrescriptionCreated(array $prescriptionData): void
    {
        try {
            if (empty($prescriptionData['patient_id'])) {
                KCErrorLogger::instance()->error('KCPrescriptionNotificationListener: Missing patient_id in prescription data');
                return;
            }

            // Get patient details
            $patient = KCPatient::find($prescriptionData['patient_id']);
            if (!$patient) {
                KCErrorLogger::instance()->error('KCPrescriptionNotificationListener: Patient not found for ID: ' . $prescriptionData['patient_id']);
                return;
            }

            // Get patient user data
            $patientUser = get_userdata($patient->user_id);
            if (!$patientUser) {
                KCErrorLogger::instance()->error('KCPrescriptionNotificationListener: Patient user not found');
                return;
            }

            // Get doctor details
            $doctorName = 'N/A';
            if (!empty($prescriptionData['doctor_id'])) {
                $doctor = KCDoctor::find($prescriptionData['doctor_id']);
                if ($doctor) {
                    $doctorUser = get_userdata($doctor->user_id);
                    $doctorName = $doctorUser ? $doctorUser->display_name : 'N/A';
                }
            }

            // Get clinic details
            $clinicName = 'N/A';
            if (!empty($prescriptionData['clinic_id'])) {
                $clinic = KCClinic::find($prescriptionData['clinic_id']);
                $clinicName = $clinic ? $clinic->name : 'N/A';
            }

            // Format prescription details (assuming it's an array of medicines)
            $prescriptionText = $this->formatPrescription($prescriptionData['prescription'] ?? []);

            // Prepare template data
            $templateData = [
                'patient_name' => $patientUser->display_name,
                'doctor_name' => $doctorName,
                'clinic_name' => $clinicName,
                'prescription' => $prescriptionText,
                'prescription_date' => $prescriptionData['prescription_date'] ?? current_time('mysql'),
                'current_date' => current_time('mysql'),
                'notes' => $prescriptionData['notes'] ?? '',
                'patient' => [
                    'id' => $patient->id,
                    'email' => $patientUser->user_email,
                    'name' => $patientUser->display_name,
                ],
                'doctor' => [
                    'name' => $doctorName,
                ],
                'clinic' => [
                    'name' => $clinicName,
                ]
            ];

            // Prepare attachments if prescription PDF path is provided
            $attachments = [];
            if (!empty($prescriptionData['prescription_pdf_path']) && file_exists($prescriptionData['prescription_pdf_path'])) {
                $attachments[] = $prescriptionData['prescription_pdf_path'];
            }

            // Send notification to patient
            $result = $this->emailSender->sendEmailByTemplate(
                KIVI_CARE_PREFIX . 'patient_prescription',
                $patientUser->user_email,
                $templateData,
                [
                    'attachments' => $attachments
                ]
            );

            if ($result) {
                KCErrorLogger::instance()->error('KCPrescriptionNotificationListener: Prescription notification sent successfully to: ' . $patientUser->user_email);
            } else {
                KCErrorLogger::instance()->error('KCPrescriptionNotificationListener: Failed to send prescription notification to: ' . $patientUser->user_email);
            }

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KCPrescriptionNotificationListener Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle prescription updated event
     * 
     * @param array $prescriptionData Contains updated prescription details
     */
    public function handlePrescriptionUpdated(array $prescriptionData): void
    {
        // Send updated prescription notification
        KCErrorLogger::instance()->error('KCPrescriptionNotificationListener: Prescription updated event received for patient ID: ' . ($prescriptionData['patient_id'] ?? 'unknown'));

        // Use the same handler to send updated prescription
        $this->handlePrescriptionCreated($prescriptionData);
    }

    /**
     * Format prescription data into readable text
     * 
     * @param array|string $prescription Prescription data (array of medicines or text)
     * @return string Formatted prescription text
     */
    private function formatPrescription($prescription): string
    {
        if (is_string($prescription)) {
            return $prescription;
        }

        if (is_array($prescription) && !empty($prescription)) {
            $formattedPrescription = '<ul>';
            foreach ($prescription as $medicine) {
                if (is_array($medicine)) {
                    $name = $medicine['name'] ?? 'N/A';
                    $dosage = $medicine['dosage'] ?? '';
                    $frequency = $medicine['frequency'] ?? '';
                    $duration = $medicine['duration'] ?? '';

                    $formattedPrescription .= '<li><strong>' . esc_html($name) . '</strong>';
                    if ($dosage) {
                        $formattedPrescription .= ' - ' . esc_html($dosage);
                    }
                    if ($frequency) {
                        $formattedPrescription .= ', ' . esc_html($frequency);
                    }
                    if ($duration) {
                        $formattedPrescription .= ' for ' . esc_html($duration);
                    }
                    $formattedPrescription .= '</li>';
                } else {
                    $formattedPrescription .= '<li>' . esc_html($medicine) . '</li>';
                }
            }
            $formattedPrescription .= '</ul>';
            return $formattedPrescription;
        }

        return 'No prescription details available.';
    }
}
