<?php

namespace App\emails;

use App\baseClasses\KCErrorLogger;
use App\baseClasses\KCNotificationDynamicKeys;
use App\emails\listeners\KCAppointmentNotificationListener;
use App\emails\listeners\KCPatientNotificationListener;
use App\emails\listeners\KCReceptionistNotificationListener;
use App\emails\listeners\KCDoctorNotificationListener;
use App\emails\listeners\KCEncounterNotificationListener;
use App\emails\listeners\KCUserVerificationNotificationListener;
use App\emails\listeners\KCPatientCheckInNotificationListener;
use App\emails\listeners\KCPaymentNotificationListener;
use App\emails\listeners\KCInvoiceNotificationListener;
use App\emails\listeners\KCPrescriptionNotificationListener;
use KCProApp\email\KCClinicAdminNotificationListener;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Enhanced Email Notification System Initializer with Dynamic Keys Integration
 */
class KCEmailNotificationInit
{
    private static ?KCEmailNotificationInit $instance = null;
    private KCNotificationDynamicKeys $dynamicKeys;

    public function __construct()
    {
        $this->dynamicKeys = new KCNotificationDynamicKeys();
        $this->init();
    }

    public static function get_instance(): KCEmailNotificationInit
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the email notification system
     */
    private function init(): void
    {
        // Initialize core components
        add_action('init', [$this, 'initializeNotificationListeners']);

        // Initialize dynamic keys system
        add_action('init', [$this, 'initializeDynamicKeysSystem']);

        // Add filters for custom key processing
        add_filter('kivicare_custom_email_key_value', [$this, 'handleCustomEmailKeys'], 10, 3);

        // Add hook for scheduled email sending
        add_action('kivicare_send_scheduled_email', [$this, 'execute_scheduled_email'], 10, 1);
    }

    /**
     * Initialize notification listeners
     */
    public function initializeNotificationListeners(): void
    {
        // Initialize appointment notification listener
        KCAppointmentNotificationListener::get_instance();

        // Initialize patient notification listener
        KCPatientNotificationListener::get_instance();

        // Initialize doctor notification listener
        KCDoctorNotificationListener::get_instance();

        // Initialize receptionist notification listener
        KCReceptionistNotificationListener::get_instance();

        // Initialize encounter notification listener
        KCEncounterNotificationListener::get_instance();

        // Initialize user verification notification listener
        KCUserVerificationNotificationListener::get_instance();

        // Initialize patient check-in notification listener
        KCPatientCheckInNotificationListener::get_instance();

        // Initialize payment notification listener
        KCPaymentNotificationListener::get_instance();

        // Initialize invoice notification listener
        KCInvoiceNotificationListener::get_instance();

        // Initialize prescription notification listener
        KCPrescriptionNotificationListener::get_instance();

        // Initialize clinic admin notification listener if Pro version is active
        if (isKiviCareProActive()) {
            //KCClinicAdminNotificationListener::get_instance();
        }

        do_action('kivicare_email_notification_listeners_initialized');

    }

    /**
     * Execute scheduled email sending via Action Scheduler
     * Supports both legacy args format and new context format
     * 
     * @param array $params Parameters containing either 'args' (legacy) or 'context' (new)
     */
    public function execute_scheduled_email(array $params): void
    {
        // Check if this is the new context-based format
        // Context format has: module, entity_id, template_name
        if (isset($params['module']) && isset($params['entity_id']) && isset($params['template_name'])) {
            $this->rebuildAndSendEmail($params);
            return;
        }

        // Legacy format - backward compatibility
        $args = $params['args'] ?? $params;
        if (empty($args['to']) || empty($args['subject']) || !isset($args['content'])) {
            KCErrorLogger::instance()->error('KiviCare Scheduled Email Error: Missing required arguments.');
            return;
        }

        $to = $args['to'];
        $subject = $args['subject'];
        $content = $args['content'];
        $headers = $args['headers'] ?? [];
        $attachments = $args['attachments'] ?? [];

        // Ensure the KCEmailSender is instantiated so its wp_mail filters are active.
        KCEmailSender::get_instance();

        $result = wp_mail($to, $subject, $content, $headers, $attachments);

        if (!$result) {
            $recipient = is_array($to) ? implode(', ', $to) : $to;
            KCErrorLogger::instance()->error("KiviCare Scheduled Email Error: wp_mail failed for recipient: {$recipient}");
        }
    }

    /**
     * Rebuild email from context and send
     * 
     * @param array $context Email context containing module, entity_id, template_name, etc.
     */
    private function rebuildAndSendEmail(array $context): void
    {
        try {
            // Validate context
            if (empty($context['module']) || empty($context['entity_id']) || empty($context['template_name'])) {
                KCErrorLogger::instance()->error('KiviCare Email Context Error: Missing required context fields.');
                return;
            }

            // Fetch data based on module
            $data = $this->fetchDataByModule($context['module'], $context['entity_id']);

            if (empty($data)) {
                KCErrorLogger::instance()->error("KiviCare Email Context Error: No data found for module '{$context['module']}' with ID {$context['entity_id']}");
                return;
            }

            // Merge custom data if provided
            if (!empty($context['options']['custom_data'])) {
                $data = array_merge($data, $context['options']['custom_data']);
            }

            // Determine recipient email (with override support)
            $recipientEmail = $context['options']['to_override'] ??
                $this->getRecipientEmail($data, $context['recipient_type'] ?? 'patient');

            if (empty($recipientEmail)) {
                KCErrorLogger::instance()->error("KiviCare Email Context Error: Could not determine recipient email for type '{$context['recipient_type']}'");
                return;
            }

            // Get template
            $templateManager = new KCEmailTemplateManager();
            $template = $templateManager->getTemplate($context['template_name'], 'mail');

            if (!$template) {
                KCErrorLogger::instance()->error("KiviCare Email Context Error: Template '{$context['template_name']}' not found.");
                return;
            }

            // Process template
            $templateProcessor = new KCEmailTemplateProcessor();
            $processedContent = $templateProcessor->processTemplate($template->post_content, $data);
            
            // Process template subject with dynamic keys
            $processedSubject = $templateProcessor->processTemplate($template->post_title, $data);

            // Prepare email options
            $subject = $context['options']['subject'] ?? $processedSubject;
            $headers = $context['options']['headers'] ?? [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
            ];
            $attachments = $context['options']['attachments'] ?? [];

            // Ensure KCEmailSender filters are active
            KCEmailSender::get_instance();

            // Send email
            $result = wp_mail($recipientEmail, $subject, $processedContent, $headers, $attachments);

            if (!$result) {
                KCErrorLogger::instance()->error("KiviCare Email Context Error: wp_mail failed for recipient: {$recipientEmail}");
            }

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KiviCare Email Context Error: ' . $e->getMessage());
        }
    }

    /**
     * Fetch data by module type
     * 
     * @param string $module Module type
     * @param int $entityId Entity ID
     * @return array|null Data array or null if not found
     */
    private function fetchDataByModule(string $module, int $entityId): ?array
    {
        switch ($module) {
            case 'appointment':
                return $this->fetchAppointmentData($entityId);

            case 'patient':
                return $this->fetchPatientData($entityId);

            case 'doctor':
                return $this->fetchDoctorData($entityId);

            case 'encounter':
                return $this->fetchEncounterData($entityId);

            case 'invoice':
            case 'bill':
                return $this->fetchInvoiceData($entityId);

            case 'prescription':
                return $this->fetchPrescriptionData($entityId);

            case 'user':
                return $this->fetchUserData($entityId);

            default:
                // Allow custom modules via filter
                return apply_filters('kivicare_fetch_email_data_by_module', null, $module, $entityId);
        }
    }

    /**
     * Initialize dynamic keys system
     */
    public function initializeDynamicKeysSystem(): void
    {
        // Register custom dynamic keys if needed
        add_action('kivicare_register_custom_dynamic_keys', [$this, 'registerCustomDynamicKeys']);
    }

    /**
     * Register custom dynamic keys
     */
    public function registerCustomDynamicKeys(): void
    {
        // Allow plugins to register custom dynamic keys
        $customKeys = apply_filters('kivicare_register_email_dynamic_keys', []);

        foreach ($customKeys as $templateName => $keys) {
            if (is_array($keys)) {
                $this->dynamicKeys->addDynamicKeys($templateName, $keys);
            }
        }
    }

    /**
     * Handle custom email keys
     */
    public function handleCustomEmailKeys(?string $value, string $keyName, array $data): ?string
    {
        // Handle special computed keys
        switch ($keyName) {
            case 'clinic_full_address':
                if (isset($data['clinic'])) {
                    return $this->formatFullClinicAddress($data['clinic']);
                }
                break;

            case 'appointment_duration':
                if (
                    isset($data['appointment']['appointment_start_time']) &&
                    isset($data['appointment']['appointment_end_time'])
                ) {
                    return $this->calculateAppointmentDuration(
                        $data['appointment']['appointment_start_time'],
                        $data['appointment']['appointment_end_time']
                    );
                }
                break;

            case 'next_appointment_date':
                if (isset($data['patient']['email'])) {
                    return $this->getNextAppointmentDate($data['patient']['email']);
                }
                break;
        }

        return $value;
    }

    /**
     * Helper methods
     */

    private function formatFullClinicAddress(array $clinic): string
    {
        $parts = array_filter([
            $clinic['address'] ?? '',
            $clinic['city'] ?? '',
            $clinic['postal_code'] ?? '',
            $clinic['country'] ?? ''
        ]);
        return implode(', ', $parts);
    }

    private function calculateAppointmentDuration(string $startTime, string $endTime): string
    {
        $start = strtotime($startTime);
        $end = strtotime($endTime);
        $duration = ($end - $start) / 60; // in minutes

        return $duration . ' minutes';
    }

    private function getNextAppointmentDate(string $patientEmail): string
    {
        // Implementation to get next appointment date for patient
        return 'N/A'; // Placeholder
    }

    /**
     * Get recipient email from data
     * 
     * @param array $data Email data
     * @param string $recipientType Recipient type (patient, doctor, clinic, user)
     * @return string|null Recipient email
     */
    private function getRecipientEmail(array $data, string $recipientType): ?string
    {
        // Special case: if data has 'user' key (for user module), use it directly
        if (isset($data['user']['email'])) {
            return $data['user']['email'];
        }

        // Otherwise use recipient type
        switch ($recipientType) {
            case 'patient':
                return $data['patient']['email'] ?? null;

            case 'doctor':
                return $data['doctor']['email'] ?? null;

            case 'clinic':
                return $data['clinic']['email'] ?? null;

            default:
                return null;
        }
    }

    /**
     * Fetch appointment data with related entities
     * 
     * @param int $appointmentId Appointment ID
     * @return array|null Appointment data with patient, doctor, clinic info
     */
    private function fetchAppointmentData(int $appointmentId): ?array
    {
        $appointment = \App\models\KCAppointment::find($appointmentId);

        if (!$appointment) {
            return null;
        }

        $patient = $appointment->getPatient();
        $doctor = $appointment->getDoctor();
        $clinic = $appointment->getClinic();

        return [
            'appointment' => [
                'id' => $appointment->id,
                'appointment_start_date' => $appointment->appointmentStartDate,
                'appointment_start_time' => $appointment->appointmentStartTime,
                'appointment_end_date' => $appointment->appointmentEndDate,
                'appointment_end_time' => $appointment->appointmentEndTime,
                'visit_type' => $appointment->visitType,
                'description' => $appointment->description,
                'status' => $appointment->status,
            ],
            'patient' => $patient ? [
                'id' => $patient->id,
                'email' => $patient->email,
                'first_name' => $patient->firstName,
                'last_name' => $patient->lastName,
                'display_name' => $patient->displayName,
                'contact_number' => $patient->contactNumber,
            ] : null,
            'doctor' => $doctor ? [
                'id' => $doctor->id,
                'email' => $doctor->email,
                'first_name' => $doctor->firstName,
                'last_name' => $doctor->lastName,
                'display_name' => $doctor->displayName,
                'contact_number' => $doctor->contactNumber,
            ] : null,
            'clinic' => $clinic ? [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'email' => $clinic->email,
                'address' => $clinic->address,
                'city' => $clinic->city,
                'country' => $clinic->country,
            ] : null,
        ];
    }

    /**
     * Fetch patient data
     * 
     * @param int $patientId Patient ID (user ID)
     * @return array|null Patient data
     */
    private function fetchPatientData(int $patientId): ?array
    {
        $patient = \App\models\KCPatient::find($patientId);

        if (!$patient) {
            return null;
        }

        return [
            'patient' => [
                'id' => $patient->id,
                'email' => $patient->email,
                'username' => $patient->username,
                'first_name' => $patient->firstName,
                'last_name' => $patient->lastName,
                'display_name' => $patient->displayName,
                'contact_number' => $patient->contactNumber,
                'gender' => $patient->gender,
                'blood_group' => $patient->bloodGroup,
                'address' => $patient->address,
                'city' => $patient->city,
                'country' => $patient->country,
                'postal_code' => $patient->postalCode,
            ]
        ];
    }

    /**
     * Fetch doctor data
     * 
     * @param int $doctorId Doctor ID (user ID)
     * @return array|null Doctor data
     */
    private function fetchDoctorData(int $doctorId): ?array
    {
        $doctor = \App\models\KCDoctor::find($doctorId);

        if (!$doctor) {
            return null;
        }

        return [
            'doctor' => [
                'id' => $doctor->id,
                'email' => $doctor->email,
                'username' => $doctor->username,
                'first_name' => $doctor->firstName,
                'last_name' => $doctor->lastName,
                'display_name' => $doctor->displayName,
                'contact_number' => $doctor->contactNumber,
                'gender' => $doctor->gender,
                'address' => $doctor->address,
                'city' => $doctor->city,
                'country' => $doctor->country,
            ]
        ];
    }

    /**
     * Fetch encounter data with related entities
     * 
     * @param int $encounterId Encounter ID
     * @return array|null Encounter data
     */
    private function fetchEncounterData(int $encounterId): ?array
    {
        $encounter = \App\models\KCPatientEncounter::find($encounterId);

        if (!$encounter) {
            return null;
        }

        $patient = $encounter->getPatient();
        $doctor = $encounter->getDoctor();
        $clinic = $encounter->getClinic();

        return [
            'encounter' => [
                'id' => $encounter->id,
                'encounter_date' => $encounter->encounterDate,
                'description' => $encounter->description,
                'status' => $encounter->status,
            ],
            'patient' => $patient ? [
                'id' => $patient->id,
                'email' => $patient->email,
                'first_name' => $patient->firstName,
                'last_name' => $patient->lastName,
                'display_name' => $patient->displayName,
            ] : null,
            'doctor' => $doctor ? [
                'id' => $doctor->id,
                'email' => $doctor->email,
                'first_name' => $doctor->firstName,
                'last_name' => $doctor->lastName,
            ] : null,
            'clinic' => $clinic ? [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'email' => $clinic->email,
            ] : null,
        ];
    }

    /**
     * Fetch invoice/bill data
     * 
     * @param int $billId Bill ID
     * @return array|null Invoice data
     */
    private function fetchInvoiceData(int $billId): ?array
    {
        $bill = \App\models\KCBill::find($billId);

        if (!$bill) {
            return null;
        }

        // Get related patient
        $patient = \App\models\KCPatient::find($bill->patientId);

        return [
            'bill' => [
                'id' => $bill->id,
                'bill_number' => $bill->billNumber,
                'total_amount' => $bill->totalAmount,
                'discount' => $bill->discount,
                'actual_amount' => $bill->actualAmount,
                'status' => $bill->status,
                'payment_status' => $bill->paymentStatus,
            ],
            'patient' => $patient ? [
                'id' => $patient->id,
                'email' => $patient->email,
                'first_name' => $patient->firstName,
                'last_name' => $patient->lastName,
            ] : null,
        ];
    }

    /**
     * Fetch prescription data
     * 
     * @param int $prescriptionId Prescription ID
     * @return array|null Prescription data
     */
    private function fetchPrescriptionData(int $prescriptionId): ?array
    {
        $prescription = \App\models\KCPrescription::find($prescriptionId);

        if (!$prescription) {
            return null;
        }

        // Get related encounter and patient
        $encounter = \App\models\KCPatientEncounter::find($prescription->encounterId);
        $patient = $encounter ? $encounter->getPatient() : null;
        $doctor = $encounter ? $encounter->getDoctor() : null;

        return [
            'prescription' => [
                'id' => $prescription->id,
                'name' => $prescription->name,
                'frequency' => $prescription->frequency,
                'duration' => $prescription->duration,
                'instruction' => $prescription->instruction,
            ],
            'patient' => $patient ? [
                'id' => $patient->id,
                'email' => $patient->email,
                'first_name' => $patient->firstName,
                'last_name' => $patient->lastName,
            ] : null,
            'doctor' => $doctor ? [
                'id' => $doctor->id,
                'first_name' => $doctor->firstName,
                'last_name' => $doctor->lastName,
            ] : null,
        ];
    }

    /**
     * Fetch user data (for registration emails)
     * 
     * @param int $userId User ID
     * @return array|null User data
     */
    private function fetchUserData(int $userId): ?array
    {
        $user = get_userdata($userId);

        if (!$user) {
            return null;
        }

        return [
            'user' => [
                'id' => $user->ID,
                'email' => $user->user_email,
                'username' => $user->user_login,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'display_name' => $user->display_name,
            ]
        ];
    }

}
