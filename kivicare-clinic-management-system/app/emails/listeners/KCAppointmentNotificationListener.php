<?php

namespace App\emails\listeners;

use App\baseClasses\KCErrorLogger;
use App\emails\KCEmailSender;
use App\models\KCAppointment;
use App\models\KCDoctor;
use App\models\KCPatient;
use App\models\KCClinic;
use App\models\KCService;
use App\models\KCAppointmentServiceMapping;
use App\models\KCServiceDoctorMapping;
use App\models\KCAppointmentReminderMapping;
use App\models\KCOption;
use DateTime;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Enhanced Appointment Notification Listener - Handles email notifications for appointment events with dynamic keys
 */
class KCAppointmentNotificationListener
{
    private KCEmailSender $emailSender;
    private static ?KCAppointmentNotificationListener $instance = null;

    public function __construct()
    {
        $this->emailSender = KCEmailSender::get_instance();
        $this->initializeHooks();
    }

    public static function get_instance(): KCAppointmentNotificationListener
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize WordPress hooks
     */
    private function initializeHooks(): void
    {
        // Main appointment booking hook
        add_action('kc_after_create_appointment', [$this, 'handleAppointmentBooked'], 10, 2);

        // Other appointment-related hooks
        add_action('kc_appointment_cancelled', [$this, 'handleAppointmentCancelled'], 10, 1);
        
        // Added kc_appointment_payment_completed to handle notifications after successful patient payment
        add_action('kivicare_after_payment_processed', [$this, 'handlePaymentProcessed'], 10, 2);
        add_action('kivicare_after_payment_processed', [$this, 'handleAppointmentBooked'], 10, 2);
        add_action('kc_appointment_payment_completed', [$this, 'handlePaymentProcessed'], 10, 2);
        add_action('kc_appointment_payment_completed', [$this, 'handleAppointmentBooked'], 10, 2);

        // Additional hooks for better integration
        add_action('kivicare_appointment_updated', [$this, 'handleAppointmentUpdated'], 10, 2);
        add_action('kivicare_appointment_confirmed', [$this, 'handleAppointmentConfirmed'], 10, 1);

        // Appointment Reminder Hooks
        add_action('kivicare_appointment_reminder', [$this, 'handleAppointmentReminder'], 10, 1);
    }


    /**
     * Handle appointment reminder - Processes reminders for a specific appointment
     * This is called both from the cron job and individually for specific appointments
     */
    public function handleAppointmentReminder(int $appointmentId): void
    {

        try {
            // Validate appointment exists and get its data
            $appointment = KCAppointment::find($appointmentId);
            if (!$appointment) {
                return;
            }

            // Check if appointment is valid for reminder (not cancelled)
            if ($appointment->status === KCAppointment::STATUS_CANCELLED) {
                return;
            }

            // Get reminder settings
            $reminderSettings = KCOption::get('email_appointment_reminder', []);

            if (!is_array($reminderSettings) || !isset($reminderSettings['status']) || $reminderSettings['status'] !== true) {
                return;
            }

            // Get appointment data
            $fullAppointmentData = $this->getAppointmentDataForEmail($appointmentId);
            if (!$fullAppointmentData) {
                return;
            }

            // Check if reminder should be sent based on appointment time
            if (!$this->shouldSendReminder($appointment, $reminderSettings)) {
                return;
            }

            // Send email reminder to patient
            if ($this->shouldSendEmailReminder($reminderSettings)) {
                $templateName = KIVI_CARE_PREFIX . 'book_appointment_reminder';
                $patientResult = $this->emailSender->sendAppointmentNotification(
                    $templateName,
                    $fullAppointmentData,
                    'patient',
                );
   
            }
            // Send email reminder to doctor
            if ($this->shouldSendEmailReminder($reminderSettings)) {
                $templateName = KIVI_CARE_PREFIX . 'book_appointment_reminder_for_doctor';
                $doctorResult = $this->emailSender->sendAppointmentNotification(
                    $templateName,
                    $fullAppointmentData,
                    'doctor',
                );
            }

            // Update reminder mapping to track what's been sent
            $this->updateReminderMapping($appointmentId, [
                'emailSent' => true,
                'sentAt' => current_time('mysql'),
            ]);

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error sending appointment reminder for ID {$appointmentId}: " . $e->getMessage());
        }
    }

    /**
     * Check if reminder should be sent based on appointment time and reminder hours setting
     */
    private function shouldSendReminder(KCAppointment $appointment, array $reminderSettings): bool
    {
        // Get reminder hours from settings (default 24 hours)
        $reminderHours = isset($reminderSettings['time']) ? intval($reminderSettings['time']) : 24;

        // Get appointment datetime in UTC
        $wpTimezone = wp_timezone();
        $appointmentDate = new DateTime($appointment->appointmentStartDate . ' ' . $appointment->appointmentStartTime, $wpTimezone);
        $appointmentDateTime = $appointmentDate->getTimestamp();
        $currentDateTime = current_time('timestamp', 1);
        $timeDifferenceHours = ($appointmentDateTime - $currentDateTime) / 3600;

        // Check if appointment is within the reminder window
        // Send reminder if appointment is within X hours from now
        return $timeDifferenceHours > 0 && $timeDifferenceHours <= $reminderHours;
    }

    /**
     * Check if email reminders are enabled
     */
    private function shouldSendEmailReminder(array $reminderSettings): bool
    {
        return isset($reminderSettings['status']) &&
            ($reminderSettings['status'] === 'on' || $reminderSettings['status'] === true);
    }

    /**
     * Update the reminder mapping table to track sent reminders
     */
    private function updateReminderMapping(int $appointmentId, array $data): void
    {
        try {
            // Try to find existing reminder mapping
            $reminderMapping = KCAppointmentReminderMapping::query()
                ->where('appointmentId', $appointmentId)
                ->first();

            if ($reminderMapping) {
                // Update existing record
                $reminderMapping->emailStatus = 1;
                $reminderMapping->msgSendDate = current_time('mysql', true);
                $reminderMapping->save();
            } else {
                // Create new record if it doesn't exist
                $newMapping = new KCAppointmentReminderMapping();
                $newMapping->appointmentId = $appointmentId;
                $newMapping->msgSendDate = current_time('mysql', true);
                $newMapping->emailStatus = 1;
                $newMapping->smsStatus = 0;
                $newMapping->whatsappStatus = 0;
                $newMapping->save();
            }
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error updating reminder mapping for appointment {$appointmentId}: " . $e->getMessage());
        }
    }



    /**
     * Handle appointment booked event - Main notification trigger
     */
    public function handleAppointmentBooked(int $appointmentId, array $appointmentData): void
    {
        try {
            $appointmentStatus = KCAppointment::find($appointmentId)?->status;
            if (in_array($appointmentStatus, [KCAppointment::STATUS_CANCELLED, KCAppointment::STATUS_PENDING]))
                return;
            // Get complete appointment data
            $fullAppointmentData = $this->getAppointmentDataForEmail($appointmentId);

            if (!$fullAppointmentData) {
                KCErrorLogger::instance()->error("Failed to get appointment data for ID: {$appointmentId}");
                return;
            }

            // Send notifications to different recipients
            $this->sendPatientBookingNotification($fullAppointmentData);
            $this->sendDoctorBookingNotification($fullAppointmentData);
            $this->sendClinicBookingNotification($fullAppointmentData);

            // Send video conference links if applicable
            $this->sendVideoConferenceLinks($fullAppointmentData);

            // Schedule appointment reminder
            $this->scheduleReminder($appointmentId, $fullAppointmentData);

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error sending appointment booking notifications: " . $e->getMessage());
        }
    }

    /**
     * Handle appointment cancellation
     */
    public function handleAppointmentCancelled(int $appointmentId): void
    {
        try {
            $fullAppointmentData = $this->getAppointmentDataForEmail($appointmentId);

            if (!$fullAppointmentData) {
                return;
            }

            // Send cancellation notifications
            $this->sendCancellationNotifications($fullAppointmentData);

            // Unschedule any pending reminders
            $this->unscheduleReminder($appointmentId);

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error sending appointment cancellation notifications: " . $e->getMessage());
        }
    }

    /**
     * Handle payment processed
     */
    public function handlePaymentProcessed(int $appointmentId, array $paymentData): void
    {
        try {
            $fullAppointmentData = $this->getAppointmentDataForEmail($appointmentId);

            if (!$fullAppointmentData) {
                return;
            }

            // Add payment info to appointment data
            $fullAppointmentData['payment'] = $paymentData;

            // Send payment confirmation
            $this->sendPaymentConfirmation($fullAppointmentData);

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error sending payment confirmation: " . $e->getMessage());
        }
    }

    /**
     * Handle appointment updated
     */
    public function handleAppointmentUpdated(int $appointmentId, array $appointmentData): void
    {
        try {
            $fullAppointmentData = $this->getAppointmentDataForEmail($appointmentId);

            if (!$fullAppointmentData) {
                return;
            }

            // Send update notifications
            $this->sendAppointmentUpdateNotifications($fullAppointmentData);

            // Reschedule reminder
            $this->unscheduleReminder($appointmentId);
            $this->scheduleReminder($appointmentId, $fullAppointmentData);

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error sending appointment update notifications: " . $e->getMessage());
        }
    }

    /**
     * Handle appointment confirmed
     */
    public function handleAppointmentConfirmed(int $appointmentId): void
    {
        try {
            $fullAppointmentData = $this->getAppointmentDataForEmail($appointmentId);

            if (!$fullAppointmentData) {
                return;
            }

            // Send confirmation notifications
            $this->sendAppointmentConfirmationNotifications($fullAppointmentData);

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error sending appointment confirmation notifications: " . $e->getMessage());
        }
    }

    /**
     * Send patient booking notification
     */
    private function sendPatientBookingNotification(array $appointmentData): bool
    {
        $templateName = KIVI_CARE_PREFIX . 'book_appointment';

        return $this->emailSender->sendAppointmentNotification(
            $templateName,
            $appointmentData,
            'patient'
        );
    }

    /**
     * Send doctor booking notification
     */
    private function sendDoctorBookingNotification(array $appointmentData): bool
    {
        $templateName = KIVI_CARE_PREFIX . 'doctor_book_appointment';

        return $this->emailSender->sendAppointmentNotification(
            $templateName,
            $appointmentData,
            'doctor'
        );
    }

    /**
     * Send clinic booking notification
     */
    private function sendClinicBookingNotification(array $appointmentData): bool
    {
        // Check if clinic notifications are enabled
        if (!$this->isClinicNotificationEnabled($appointmentData['clinic']['id'] ?? 0)) {
            return false;
        }

        $templateName = KIVI_CARE_PREFIX . 'clinic_book_appointment';

        return $this->emailSender->sendAppointmentNotification(
            $templateName,
            $appointmentData,
            'clinic'
        );
    }

    /**
     * Send video conference links
     */
    private function sendVideoConferenceLinks(array $appointmentData): void
    {
        // Check if this is a telemed appointment
        if (!$this->isTelemedAppointment($appointmentData)) {
            return;
        }

        // Get video links from appointment data or generate them
        $zoomLink = $this->getZoomLink($appointmentData['appointment']['id']);
        $meetLink = $this->getMeetLink($appointmentData['appointment']['id']);

        // Send Zoom link if available
        if ($zoomLink) {
            // To patient
            $this->emailSender->sendVideoConferenceLink(
                'zoom',
                $appointmentData,
                $zoomLink,
                'patient'
            );

            // To doctor
            $this->emailSender->sendVideoConferenceLink(
                'zoom',
                $appointmentData,
                $zoomLink,
                'doctor'
            );
        }

        // Send Meet link if available
        if ($meetLink) {
            $meetEventLink = $this->getMeetEventLink($appointmentData['appointment']['id']);
            if ($meetEventLink) {
                $appointmentData['meet_event_link'] = $meetEventLink;
            }

            // To patient
            $this->emailSender->sendVideoConferenceLink(
                'meet',
                $appointmentData,
                $meetLink,
                'patient'
            );

            // To doctor
            $this->emailSender->sendVideoConferenceLink(
                'meet',
                $appointmentData,
                $meetLink,
                'doctor'
            );
        }
    }

    /**
     * Send cancellation notifications
     */
    private function sendCancellationNotifications(array $appointmentData): void
    {
        $templateName = KIVI_CARE_PREFIX . 'cancel_appointment';

        // Send to patient
        $this->emailSender->sendAppointmentNotification(
            $templateName,
            $appointmentData,
            'patient'
        );

        // Send to doctor
        $this->emailSender->sendAppointmentNotification(
            $templateName,
            $appointmentData,
            'doctor'
        );
    }

    /**
     * Send payment confirmation
     */
    private function sendPaymentConfirmation(array $appointmentData): void
    {
        $templateName = KIVI_CARE_PREFIX . 'payment_confirmation';

        $this->emailSender->sendAppointmentNotification(
            $templateName,
            $appointmentData,
            'patient'
        );
    }

    /**
     * Send appointment update notifications
     */
    private function sendAppointmentUpdateNotifications(array $appointmentData): void
    {
        $templateName = KIVI_CARE_PREFIX . 'appointment_updated';

        // Send to patient
        $this->emailSender->sendAppointmentNotification(
            $templateName,
            $appointmentData,
            'patient'
        );

        // Send to doctor
        $this->emailSender->sendAppointmentNotification(
            $templateName,
            $appointmentData,
            'doctor'
        );
    }

    /**
     * Send appointment confirmation notifications
     */
    private function sendAppointmentConfirmationNotifications(array $appointmentData): void
    {
        $templateName = KIVI_CARE_PREFIX . 'appointment_confirmed';

        // Send to patient
        $this->emailSender->sendAppointmentNotification(
            $templateName,
            $appointmentData,
            'patient'
        );
    }

    /**
     * Get complete appointment data for email templates - PUBLIC method for external access
     */
    public function getAppointmentDataForEmail(int $appointmentId): ?array
    {
        try {
            // Get appointment
            $appointment = KCAppointment::find($appointmentId);
            if (!$appointment) {
                return null;
            }

            // Get patient data
            $patient = KCPatient::find($appointment->patientId);
            $patientUser = $patient ? get_userdata($patient->id) : null;
            $patientBasicData = $patient ? json_decode(get_user_meta($patient->id, 'basic_data', true) ?: '{}', true) : [];

            // Get doctor data
            $doctor = KCDoctor::find($appointment->doctorId);
            $doctorUser = $doctor ? get_userdata($doctor->id) : null;
            $doctorBasicData = $doctor ? json_decode(get_user_meta($doctor->id, 'basic_data', true) ?: '{}', true) : [];

            // Get clinic data
            $clinic = KCClinic::find($appointment->clinicId);
            $clinicAdminBasicData = $clinic ? json_decode(get_user_meta($clinic->clinicAdminId, 'basic_data', true) ?: '{}', true) : [];

            // Get services
            $services = $this->getAppointmentServices($appointmentId);

            // Prepare comprehensive data structure for email templates with dynamic keys
            return [
                'appointment' => [
                    'id' => $appointment->id,
                    'appointment_start_date' => $appointment->appointmentStartDate,
                    'appointment_start_time' => gmdate('g:i A', strtotime($appointment->appointmentStartTime)),
                    'appointment_end_date' => $appointment->appointmentEndDate,
                    'appointment_end_time' => gmdate('g:i A', strtotime($appointment->appointmentEndTime)),
                    'appointment_date' => $appointment->appointmentStartDate, // Alias for templates
                    'appointment_time' => gmdate('g:i A', strtotime($appointment->appointmentStartTime)), // Alias for templates
                    'description' => $appointment->description,
                    'status' => $appointment->status,
                    'service_name' => implode(', ', array_column($services, 'name')),
                    'total_amount' => number_format(array_sum(array_column($services, 'charges')), 2)
                ],
                'patient' => [
                    'id' => $patient->id ?? 0,
                    'email' => $patientUser->user_email ?? '',
                    'display_name' => $patientUser->display_name ?? '',
                    'user_nicename' => $patientUser->user_nicename ?? '',
                    'mobile_number' => $this->format_phone($patientBasicData['mobile_number'] ?? ''),
                    'patient_name' => $patientUser->display_name ?? '', // Alias for templates
                    'patient_email' => $patientUser->user_email ?? '', // Alias for templates
                ],
                'doctor' => [
                    'id' => $doctor->id ?? 0,
                    'email' => $doctorUser->user_email ?? '',
                    'display_name' => $doctorUser->display_name ?? '',
                    'user_nicename' => $doctorUser->user_nicename ?? '',
                    'doctor_name' => $doctorUser->display_name ?? '', // Alias for templates
                    'doctor_email' => $doctorUser->user_email ?? '', // Alias for templates
                    'mobile_number' => $this->format_phone($doctorBasicData['mobile_number'] ?? '') // Alias for templates
                ],
                'clinic' => [
                    'id' => $clinic->id ?? 0,
                    'name' => $clinic->name ?? '',
                    'email' => $clinic->email ?? '',
                    'telephone_no' => $clinic->telephone_no ?? '',
                    'address' => $clinic->address ?? '',
                    'city' => $clinic->city ?? '',
                    'postal_code' => $clinic->postal_code ?? '',
                    'country' => $clinic->country ?? '',
                    'clinic_name' => $clinic->name ?? '', // Alias for templates
                    'clinic_email' => $clinic->email ?? '', // Alias for templates
                    'mobile_number' => $this->format_phone($clinicAdminBasicData['mobile_number'] ?? ''), // Alias for templates
                    'clinic_address' => $this->formatClinicAddress($clinic) // Formatted address
                ],
                'services' => $services,

                // Additional dynamic keys that might be needed
                'current_date' => current_time('Y-m-d'),
                'current_date_time' => current_time('Y-m-d H:i:s'),
                'site_url' => get_site_url(),
                'login_url' => wp_login_url(),
            ];

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error getting appointment data: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get appointment services
     */
    private function getAppointmentServices(int $appointmentId): array
    {
        $serviceMapping = KCAppointmentServiceMapping::query()
            ->where('appointmentId', $appointmentId)
            ->get();

        $services = [];
        foreach ($serviceMapping as $mapping) {
            $service = KCServiceDoctorMapping::table('sdm')
                ->select(['sdm.*', 's.name'])
                ->leftJoin(KCService::class, 'sdm.service_id', '=', 's.id', 's')
                ->where('s.id', $mapping->serviceId)
                ->first();

            if ($service) {
                $services[] = [
                    'id' => $service->id,
                    'name' => $service->name,
                    'charges' => (float) $service->charges
                ];
            }
        }

        return $services;
    }

    /**
     * Format clinic address for email templates
     */
    private function formatClinicAddress($clinic): string
    {
        if (!$clinic) {
            return '';
        }

        $addressParts = array_filter([
            $clinic->address ?? '',
            $clinic->city ?? '',
            $clinic->postal_code ?? '',
            $clinic->country ?? ''
        ]);

        return implode(', ', $addressParts);
    }

    /**
     * Check if this is a telemed appointment
     */
    private function isTelemedAppointment(array $appointmentData): bool
    {
        foreach ($appointmentData['services'] as $service) {
            // Check if any service is a telemed service
            $serviceDetails = KCServiceDoctorMapping::find($service['id']);

            if ($serviceDetails && $serviceDetails->telemedService === 'yes') {
                return true;
            }
        }
        return false;
    }

    /**
     * Get Zoom link for appointment
     */
    private function getZoomLink(int $appointmentId): ?string
    {
        // Check if Zoom Telemed addon is active
        if (!class_exists('KCTApp\\models\\KCTAppointmentZoomMapping')) {
            return null;
        }

        try {
            $zoomMapping = \KCTApp\models\KCTAppointmentZoomMapping::query()
                ->where('appointmentId', $appointmentId)
                ->first();

            if ($zoomMapping && !empty($zoomMapping->joinUrl)) {
                return $zoomMapping->joinUrl;
            }
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error fetching Zoom link: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Get Meet link for appointment
     */
    private function getMeetLink(int $appointmentId): ?string
    {
        // Check if Google Meet addon is active
        if (!class_exists('KCGMApp\\models\\KCGMAppointmentGoogleMeetMapping')) {
            return null;
        }

        try {
            $meetMapping = \KCGMApp\models\KCGMAppointmentGoogleMeetMapping::query()
                ->where('appointmentId', $appointmentId)
                ->first();

            if ($meetMapping && !empty($meetMapping->url)) {
                return $meetMapping->url;
            }
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error fetching Google Meet link: " . $e->getMessage());
        }

        return null;
    }


    /**
     * Get Meet event link for appointment
     */
    private function getMeetEventLink(int $appointmentId): ?string
    {
        // Check if Google Meet addon is active
        if (!class_exists('KCGMApp\\models\\KCGMAppointmentGoogleMeetMapping')) {
            return null;
        }

        try {
            $meetMapping = \KCGMApp\models\KCGMAppointmentGoogleMeetMapping::query()
                ->where('appointmentId', $appointmentId)
                ->first();

            if ($meetMapping && !empty($meetMapping->eventUrl)) {
                return $meetMapping->eventUrl;
            }
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error fetching Google Meet event link: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Check if clinic notifications are enabled
     */
    private function isClinicNotificationEnabled(int $clinicId): bool
    {
        // Check clinic settings for notification preferences
        $enabled = get_option('kivicare_clinic_notifications_enabled_' . $clinicId, true);
        return (bool) $enabled;
    }

    /**
     * Schedule an appointment reminder
     */
    private function scheduleReminder(int $appointmentId, array $appointmentData): void
    {
        if (!function_exists('as_schedule_single_action')) {
            return;
        }

        try {
            // Get reminder settings
            $reminderSettings = KCOption::get('email_appointment_reminder', []);

            // Check if reminders are enabled
            $isEmailEnabled = isset($reminderSettings['status']) && ($reminderSettings['status'] === 'on' || $reminderSettings['status'] === true);
            $isSmsEnabled = isset($reminderSettings['sms_status']) && ($reminderSettings['sms_status'] === 'on' || $reminderSettings['sms_status'] === true);
            $isWhatsappEnabled = isset($reminderSettings['whatapp_status']) && ($reminderSettings['whatapp_status'] === 'on' || $reminderSettings['whatapp_status'] === true);

            if (!$isEmailEnabled && !$isSmsEnabled && !$isWhatsappEnabled) {
                return;
            }

            $reminderHours = isset($reminderSettings['time']) ? intval($reminderSettings['time']) : 24;

            // Appointment datetime string (stored in WP timezone)
            $appointmentStart = $appointmentData['appointment']['appointment_start_date'] . ' ' . $appointmentData['appointment']['appointment_time'];

            // WordPress timezone
            $wpTimezone = wp_timezone();

            // Create DateTime in WP timezone
            $appointmentDateTime = new DateTime($appointmentStart, $wpTimezone);

            // Subtract reminder hours (handles + / − automatically)
            $appointmentDateTime->modify("-{$reminderHours} hours");

            // Convert to UTC timestamp (required by Action Scheduler)
            $reminderTimestamp = $appointmentDateTime->getTimestamp();

            // Only schedule if in future
            if ($reminderTimestamp > time()) {
                as_schedule_single_action(
                    $reminderTimestamp,
                    'kivicare_appointment_reminder',
                    [$appointmentId],
                    'kivicare-reminders'
                );
            }


        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error scheduling reminder for appointment {$appointmentId}: " . $e->getMessage());
        }
    }

    /**
     * Unschedule an appointment reminder
     */
    private function unscheduleReminder(int $appointmentId): void
    {
        if (!function_exists('as_unschedule_action')) {
            return;
        }

        try {
            as_unschedule_action(
                'kivicare_appointment_reminder',
                [$appointmentId],
                'kivicare-reminders'
            );
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Error unscheduling reminder for appointment {$appointmentId}: " . $e->getMessage());
        }
    }
    /**
     * Send prescription notification
     */
    public function sendPrescriptionNotification(int $appointmentId, string $prescriptionContent): bool
    {
        $appointmentData = $this->getAppointmentDataForEmail($appointmentId);

        if (!$appointmentData) {
            return false;
        }

        // Add prescription to appointment data
        $appointmentData['prescription'] = $prescriptionContent;

        return $this->emailSender->sendAppointmentNotification(
            KIVI_CARE_PREFIX . 'book_prescription',
            $appointmentData,
            'patient'
        );
    }

    /**
     * Send invoice notification
     */
    public function sendInvoiceNotification(int $appointmentId, array $invoiceData = []): bool
    {
        $appointmentData = $this->getAppointmentDataForEmail($appointmentId);

        if (!$appointmentData) {
            return false;
        }

        // Add invoice data to appointment data
        $appointmentData = array_merge($appointmentData, $invoiceData);

        return $this->emailSender->sendAppointmentNotification(
            KIVI_CARE_PREFIX . 'patient_invoice',
            $appointmentData,
            'patient'
        );
    }

    /**
     * Send encounter close notification
     */
    public function sendEncounterCloseNotification(int $appointmentId, array $encounterData = []): bool
    {
        $appointmentData = $this->getAppointmentDataForEmail($appointmentId);

        if (!$appointmentData) {
            return false;
        }

        // Add encounter data to appointment data
        $appointmentData = array_merge($appointmentData, $encounterData);

        return $this->emailSender->sendAppointmentNotification(
            KIVI_CARE_PREFIX . 'encounter_close',
            $appointmentData,
            'patient'
        );
    }

    /**
     * Get notification history for an appointment
     */
    public function getNotificationHistory(int $appointmentId): array
    {
        // This would integrate with your logging system
        return get_option('kivicare_notification_history_' . $appointmentId, []);
    }

    /**
     * Log notification sent
     */
    private function logNotification(int $appointmentId, string $templateName, string $recipient, bool $success): void
    {
        $history = $this->getNotificationHistory($appointmentId);

        $history[] = [
            'template' => $templateName,
            'recipient' => $recipient,
            'success' => $success,
            'timestamp' => current_time('mysql'),
            'datetime' => current_time('Y-m-d H:i:s')
        ];

        // Keep only last 50 notifications
        if (count($history) > 50) {
            $history = array_slice($history, -50);
        }

        update_option('kivicare_notification_history_' . $appointmentId, $history);
    }
    /**
     * Format phone number to E.164 format for Twilio
     *
     * @param string $phone_number Raw phone number
     * @return string Formatted phone number in E.164 format
     */
    public function format_phone($phone_number)
    {
        // Remove spaces, dashes, brackets, or other non-digit characters, but keep leading +
        $number = trim($phone_number);
        $number = preg_replace('/[^0-9+]/', '', $number);

        // If number starts with '+', return as is
        if (strpos($number, '+') === 0) {
            return $number;
        }

        // Otherwise, prepend default country code
        return '+' . $number;
    }
}
