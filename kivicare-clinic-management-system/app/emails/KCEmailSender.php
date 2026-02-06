<?php

namespace App\emails;

use App\baseClasses\KCErrorLogger;
use WP_User;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Email Sender - Handles sending emails with template processing
 */
class KCEmailSender
{
    private KCEmailTemplateManager $templateManager;
    private KCEmailTemplateProcessor $templateProcessor;
    private array $defaultHeaders;
    private string $fromEmail;
    private string $fromName;

    private static ?KCEmailSender $instance = null;

    public function __construct()
    {
        $this->templateManager = new KCEmailTemplateManager();
        $this->templateProcessor = new KCEmailTemplateProcessor();

        $this->initializeDefaults();
        $this->setupHooks();
    }


    public static function get_instance(): KCEmailSender|null
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize default settings
     */
    private function initializeDefaults(): void
    {
        $this->fromEmail = get_option('admin_email', 'noreply@' . wp_parse_url(home_url(), PHP_URL_HOST));
        $this->fromName = get_option('blogname', 'KiviCare');

        $this->defaultHeaders = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>'
        ];
    }

    /**
     * Setup WordPress hooks
     */
    private function setupHooks(): void
    {
        add_filter('wp_mail_from', [$this, 'setFromEmail']);
        add_filter('wp_mail_from_name', [$this, 'setFromName']);
    }

    /**
     * Prepare email context for queue storage
     * 
     * @param string $templateName Email template name
     * @param string $module Module type (appointment, patient, doctor, etc.)
     * @param int $entityId Primary entity ID
     * @param string $recipientType Recipient type (patient, doctor, clinic)
     * @param array $options Additional options
     * @return array Email context
     */
    public function prepareEmailContext(
        string $templateName,
        string $module,
        int $entityId,
        string $recipientType = 'patient',
        array $options = []
    ): array {
        return [
            'module' => $module,
            'entity_id' => $entityId,
            'template_name' => $templateName,
            'recipient_type' => $recipientType,
            'options' => $options
        ];
    }

    /**
     * Send email using context (module, entity_id, template)
     * This method queues the email with minimal context to avoid storing sensitive data
     * 
     * @param string $templateName Email template name
     * @param string $module Module type (appointment, patient, doctor, etc.)
     * @param int $entityId Primary entity ID
     * @param string $recipientType Recipient type (patient, doctor, clinic)
     * @param array $options Additional options
     * @return bool
     */
    public function sendEmailWithContext(
        string $templateName,
        string $module,
        int $entityId,
        string $recipientType = 'patient',
        array $options = []
    ): bool {
        try {
            // Prepare minimal context
            $context = $this->prepareEmailContext(
                $templateName,
                $module,
                $entityId,
                $recipientType,
                $options
            );

            // Use Action Scheduler if available
            if (function_exists('as_enqueue_async_action')) {
                as_enqueue_async_action(
                    'kivicare_send_scheduled_email',
                    ['context' => $context],
                    'kivicare-emails'
                );
                return true;
            } else {
                // If Action Scheduler is not available, we need to fetch the data and send immediately
                // This will be handled by KCEmailNotificationInit::rebuildAndSendEmail
                return apply_filters('kivicare_send_email_from_context', false, $context);
            }
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Email context preparation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email using template
     */
    public function sendEmailByTemplate(
        string $templateName,
        string|array $to,
        array $data = [],
        array $options = []
    ): bool {
        try {
            // Get email template
            $template = $this->templateManager->getTemplate($templateName, 'mail');

            if (!$template) {
                KCErrorLogger::instance()->error("Email template not found: {$templateName}");
                return false;
            }

            // Process template content
            $processedContent = $this->templateProcessor->processTemplate($template->post_content, $data);

            // Process template subject with dynamic keys
            $templateSubject = $this->templateProcessor->processTemplate($template->post_title, $data);
            
            // Prepare email data - use processed template subject instead of hardcoded one
            $subject = $options['subject'] ?? $templateSubject;
            $headers = $options['headers'] ?? $this->defaultHeaders;
            $attachments = $options['attachments'] ?? [];

            // Use Action Scheduler if available, otherwise send synchronously
            // NOTE: This method stores full email content in queue (for backward compatibility)
            // For new implementations, use sendEmailWithContext() instead
            if (function_exists('as_enqueue_async_action')) {
                $args = [
                    'to' => $to,
                    'subject' => $subject,
                    'content' => $processedContent,
                    'headers' => $headers,
                    'attachments' => $attachments
                ];
                as_enqueue_async_action('kivicare_send_scheduled_email', ['args' => $args], 'kivicare-emails');
                return true; // Assume success as it's queued
            } else {
                $result = wp_mail($to, $subject, $processedContent, $headers, $attachments);
            }

            if (!$result) {
                KCErrorLogger::instance()->error("Failed to send email to: " . (is_array($to) ? implode(', ', $to) : $to));
                return false;
            }

            return true;

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Email sending error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send appointment notification email using context
     * 
     * @param string $templateName Email template name
     * @param array $appointmentData Appointment data (must contain appointment['id'])
     * @param string $recipientType Recipient type (patient, doctor, clinic)
     * @return bool
     */
    public function sendAppointmentNotification(
        string $templateName,
        array $appointmentData,
        string $recipientType = 'patient'
    ): bool {
        // Extract appointment ID from data
        $appointmentId = $appointmentData['appointment']['id'] ?? null;

        if (empty($appointmentId)) {
            KCErrorLogger::instance()->error('sendAppointmentNotification: Missing appointment ID');
            return false;
        }

        // Extract custom data that might have been added dynamically and isn't in the DB fetch
        $customData = array_intersect_key($appointmentData, array_flip([
            'meet_link',
            'meet_event_link',
            'zoom_link',
            'add_doctor_zoom_link',
            'prescription',
            'payment'
        ]));

        // Use context-based email sending without overriding subject
        return $this->sendEmailWithContext(
            $templateName,
            'appointment',
            (int) $appointmentId,
            $recipientType,
            [
                'custom_data' => $customData
            ] 
        );
    }

    /**
     * Send user registration email using context
     * 
     * @param string $templateName Email template name
     * @param array $userData User data (must contain user['id'] or ID)
     * @return bool
     */
    public function sendUserRegistrationEmail(string $templateName, array $userData): bool
    {
        // Extract user ID from data
        $userId = $userData['user']['id'] ?? $userData['ID'] ?? $userData['id'] ?? null;

        if (empty($userId)) {
            KCErrorLogger::instance()->error('sendUserRegistrationEmail: Missing user ID');
            return false;
        }

        // Use context-based email sending
        return $this->sendEmailWithContext(
            $templateName,
            'user',
            (int) $userId,
            'patient', // Default to patient, can be overridden
            ['subject' => $this->generateRegistrationSubject($templateName, $userData)]
        );
    }

    /**
     * Send bulk emails
     */
    public function sendBulkEmails(string $templateName, array $recipients, array $data = []): array
    {
        $results = [];

        foreach ($recipients as $recipient) {
            $email = is_array($recipient) ? $recipient['email'] : $recipient;
            $recipientData = is_array($recipient) ? array_merge($data, $recipient) : $data;

            $results[$email] = $this->sendEmailByTemplate($templateName, $email, $recipientData);
        }

        return $results;
    }

    /**
     * Send reminder emails
     */
    public function sendReminderEmails(array $appointments): array
    {
        $results = [];

        foreach ($appointments as $appointment) {
            // Send patient reminder
            $patientResult = $this->sendAppointmentNotification(
                KIVI_CARE_PREFIX . 'book_appointment_reminder',
                $appointment,
                'patient'
            );

            // Send doctor reminder if enabled
            $doctorResult = $this->sendAppointmentNotification(
                KIVI_CARE_PREFIX . 'book_appointment_reminder_for_doctor',
                $appointment,
                'doctor'
            );

            $results[$appointment['appointment']['id']] = [
                'patient' => $patientResult,
                'doctor' => $doctorResult
            ];
        }

        return $results;
    }

    /**
     * Send video conference links
     */
    public function sendVideoConferenceLink(
        string $type,
        array $appointmentData,
        string $link,
        string $recipientType = 'patient'
    ): bool {
        $templateName = $type === 'zoom'
            ? ($recipientType === 'doctor' ? KIVI_CARE_PREFIX . 'add_doctor_zoom_link' : KIVI_CARE_PREFIX . 'zoom_link')
            : ($recipientType === 'doctor' ? KIVI_CARE_PREFIX . 'add_doctor_meet_link' : KIVI_CARE_PREFIX . 'meet_link');

        // Add link to appointment data
        $linkKey = $type === 'zoom'
            ? ($recipientType === 'doctor' ? 'add_doctor_zoom_link' : 'zoom_link')
            : 'meet_link';

        $appointmentData[$linkKey] = $link;

        return $this->sendAppointmentNotification($templateName, $appointmentData, $recipientType);
    }

    /**
     * Get recipient from appointment data
     */
    private function getRecipientFromAppointmentData(array $appointmentData, string $recipientType): ?array
    {
        switch ($recipientType) {
            case 'patient':
                return $appointmentData['patient'] ?? null;
            case 'doctor':
                return $appointmentData['doctor'] ?? null;
            case 'clinic':
                return $appointmentData['clinic'] ?? null;
            default:
                return null;
        }
    }

    /**
     * Generate appointment email subject
     */
    private function generateAppointmentSubject(string $templateName, array $appointmentData): string
    {
        $appointment = $appointmentData['appointment'] ?? [];
        $date = $appointment['appointment_start_date'] ?? 'N/A';

        $subjects = [
            KIVI_CARE_PREFIX . 'book_appointment' => 'Appointment Confirmed - ' . $date,
            KIVI_CARE_PREFIX . 'cancel_appointment' => 'Appointment Cancelled - ' . $date,
            KIVI_CARE_PREFIX . 'doctor_book_appointment' => 'New Appointment - ' . $date,
            KIVI_CARE_PREFIX . 'clinic_book_appointment' => 'New Clinic Appointment - ' . $date,
            KIVI_CARE_PREFIX . 'book_appointment_reminder' => 'Appointment Reminder - ' . $date,
            KIVI_CARE_PREFIX . 'zoom_link' => 'Video Conference Link - ' . $date,
            KIVI_CARE_PREFIX . 'meet_link' => 'Google Meet Link - ' . $date,
        ];

        return $subjects[$templateName] ?? 'KiviCare Notification';
    }

    /**
     * Generate registration email subject
     */
    private function generateRegistrationSubject(string $templateName, array $userData): string
    {
        $subjects = [
            KIVI_CARE_PREFIX . 'patient_register' => 'Welcome to KiviCare - Registration Successful',
            KIVI_CARE_PREFIX . 'doctor_registration' => 'Doctor Registration - Welcome to KiviCare',
            KIVI_CARE_PREFIX . 'receptionist_register' => 'Receptionist Registration - Welcome to KiviCare',
            KIVI_CARE_PREFIX . 'clinic_admin_registration' => 'Clinic Admin Registration - Welcome to KiviCare',
            KIVI_CARE_PREFIX . 'resend_user_credential' => 'Your KiviCare Account Credentials',
        ];

        return $subjects[$templateName] ?? 'KiviCare Registration';
    }

    /**
     * Set from email filter callback
     */
    public function setFromEmail(string $email): string
    {
        return $this->fromEmail;
    }

    /**
     * Set from name filter callback
     */
    public function setFromName(string $name): string
    {
        return $this->fromName;
    }

    /**
     * Test email configuration by sending a test email
     *
     * @param string $recipientEmail Email address to send test email to
     * @return bool True if email was sent successfully, false otherwise
     */
    public function testEmailConfiguration(string $recipientEmail): bool
    {
        try {
            // Validate email
            if (!is_email($recipientEmail)) {
                KCErrorLogger::instance()->error("Invalid email provided for test: {$recipientEmail}");
                return false;
            }

            // Prepare test email
            $subject = __('KiviCare Email Configuration Test', 'kivicare-clinic-management-system');

            $html_content = '<html><body>';
            $html_content .= '<h2>' . __('Email Configuration Test', 'kivicare-clinic-management-system') . '</h2>';
            $html_content .= '<p>' . __('This is a test email to verify your email configuration is working correctly.', 'kivicare-clinic-management-system') . '</p>';
            $html_content .= '<hr />';
            $html_content .= '<p><strong>' . __('Test Details:', 'kivicare-clinic-management-system') . '</strong></p>';
            $html_content .= '<ul>';
            $html_content .= '<li><strong>' . __('Sent from:', 'kivicare-clinic-management-system') . '</strong> ' . esc_html($this->fromName) . ' &lt;' . esc_html($this->fromEmail) . '&gt;</li>';
            $html_content .= '<li><strong>' . __('Sent to:', 'kivicare-clinic-management-system') . '</strong> ' . esc_html($recipientEmail) . '</li>';
            $html_content .= '<li><strong>' . __('Test Time:', 'kivicare-clinic-management-system') . '</strong> ' . current_time('Y-m-d H:i:s') . '</li>';
            $html_content .= '<li><strong>' . __('Site URL:', 'kivicare-clinic-management-system') . '</strong> ' . esc_url(home_url()) . '</li>';
            $html_content .= '</ul>';
            $html_content .= '<hr />';
            $html_content .= '<p>' . __('If you received this email, your email configuration is working correctly.', 'kivicare-clinic-management-system') . '</p>';
            $html_content .= '<p><small>' . __('Powered by KiviCare Medical Management System', 'kivicare-clinic-management-system') . '</small></p>';
            $html_content .= '</body></html>';

            // Send email
            $result = wp_mail(
                $recipientEmail,
                $subject,
                $html_content,
                $this->defaultHeaders
            );

            if ($result) {
                KCErrorLogger::instance()->error("Test email sent successfully to: {$recipientEmail}");
                return true;
            } else {
                KCErrorLogger::instance()->error("Failed to send test email to: {$recipientEmail}");
                return false;
            }

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error during test email configuration: " . $e->getMessage());
            return false;
        }
    }
}

