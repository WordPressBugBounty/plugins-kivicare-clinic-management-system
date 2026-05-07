<?php

namespace App\services;

use App\models\KCAppointment;
use App\models\KCPaymentsAppointmentMapping;
use App\models\KCAppointmentServiceMapping;
use App\models\KCService;
use App\models\KCServiceDoctorMapping;
use App\baseClasses\KCTelemedFactory;
use App\baseClasses\KCBase;
use App\baseClasses\KCErrorLogger;
use Exception;

defined('ABSPATH') or die('Something went wrong');

/**
 * Service to handle appointment payment confirmation and post-payment logic.
 * This centralizes logic shared between synchronous callbacks and asynchronous webhooks.
 */
class KCAppointmentPaymentService
{
    /**
     * Process a successful payment confirmation for an appointment.
     * This method is idempotent and safe to call multiple times for the same transaction.
     *
     * @param int    $appointmentId The ID of the appointment
     * @param string $gateway       The gateway ID (stripe, paypal, etc.)
     * @param array  $paymentData   Data from the gateway (transaction_id, amount, etc.)
     * @return bool True if processed or already confirmed, False otherwise
     */
    public static function confirmPayment(int $appointmentId, string $gateway, array $paymentData): bool
    {
        try {
            $appointment = KCAppointment::find($appointmentId);
            if (!$appointment) {
                error_log("[KCAppointmentPaymentService] Appointment not found: {$appointmentId}");
                return false;
            }

            // 1. Idempotency Check: If already confirmed, don't repeat business logic
            if ($appointment->status == 1) {
                error_log("[KCAppointmentPaymentService] Appointment {$appointmentId} already confirmed. Skipping.");
                return true;
            }

            // 2. Update Appointment Status
            $appointment->update([
                'status' => 1 // Confirmed/Booked
            ]);

            // 3. Update Payment Mapping
            $mappingData = [
                'paymentMode'   => $gateway,
                'paymentStatus' => 'completed',
                'transactionId' => $paymentData['transaction_id'] ?? null,
                'paymentId'     => $paymentData['payment_id'] ?? '',
                'updatedAt'     => current_time('mysql')
            ];

            $existingMapping = KCPaymentsAppointmentMapping::query()->where('appointment_id', $appointmentId)->first();
            if ($existingMapping) {
                $existingMapping->update($mappingData);
            } else {
                $mappingData['appointmentId'] = $appointmentId;
                $mappingData['createdAt']     = current_time('mysql');
                KCPaymentsAppointmentMapping::create($mappingData);
            }

            // 4. Telemedicine Meeting Creation
            self::handleTelemedCreation($appointment);

            // 5. Trigger Success Hook (Triggers notifications, etc.)
            do_action('kc_appointment_payment_completed', $appointmentId, $paymentData);

            // 6. Google Calendar Sync (Non-transactional, usually Pro)
            self::syncToGoogleCalendar($appointmentId, $appointment);

            return true;

        } catch (Exception $e) {
            error_log("[KCAppointmentPaymentService] Error confirming payment for {$appointmentId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if appointment has telemed services and create meeting if needed.
     */
    private static function handleTelemedCreation(KCAppointment $appointment): void
    {
        $telemed_services = KCAppointmentServiceMapping::table('asm')
            ->select(['sdm.*', 'asm.*'])
            ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
            ->leftJoin(KCServiceDoctorMapping::class, 'sdm.service_id', '=', 's.id', 'sdm')
            ->where('appointment_id', $appointment->id)
            ->where('sdm.telemed_service', '=', 'yes')
            ->where('sdm.clinic_id', '=', $appointment->clinicId)
            ->get();

        if ($telemed_services->count() > 0) {
            $telemedProvider = KCTelemedFactory::get_provider_by_doctor_id($appointment->doctorId);
            if ($telemedProvider) {
                $appointment_start_str = $appointment->appointmentStartDate . ' ' . $appointment->appointmentStartTime;
                $start_dt = new \DateTime($appointment->appointmentStartTime);
                $end_dt   = new \DateTime($appointment->appointmentEndTime);
                $duration_minutes = max(($end_dt->getTimestamp() - $start_dt->getTimestamp()) / 60, 30);

                $telemedProvider->create_meeting(array(
                    'topic'             => $telemed_services->map(fn($service) => $service->name)->join(', ') ?? 'Telemed Service',
                    'type'              => 'scheduled',
                    'start_time'        => $appointment_start_str,
                    'duration'          => $duration_minutes,
                    'timezone'          => wp_timezone_string(),
                    'password'          => '',
                    'waiting_room'      => false,
                    'auto_recording'    => false,
                    'host_video'        => true,
                    'participant_video' => true,
                    'mute_upon_entry'   => true,
                    'patient_id'        => $appointment->patientId,
                    'doctor_id'         => $appointment->doctorId,
                    'appointment_id'    => $appointment->id
                ));
            }
        }
    }

    /**
     * Sync appointment to external calendars.
     */
    private static function syncToGoogleCalendar(int $appointmentId, KCAppointment $appointment): void
    {
        if (function_exists('isKiviCareProActive') && isKiviCareProActive() && class_exists('\KCProApp\controllers\api\GoogleCalendarIntegration')) {
            try {
                $gcal = \KCProApp\controllers\api\GoogleCalendarIntegration::getInstance();
                
                $clinic   = \App\models\KCClinic::find($appointment->clinicId ?? 0);
                $patient  = \App\models\KCPatient::find($appointment->patientId ?? 0);
                $doctor   = \App\models\KCDoctor::find($appointment->doctorId ?? 0);
                
                $services = KCAppointmentServiceMapping::table('asm')
                    ->select(['s.*'])
                    ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
                    ->where('appointment_id', $appointmentId)
                    ->get();

                $gcal->addAppointmentToGoogleCalendars($appointmentId, $appointment->toArray(), $clinic, $patient, $doctor, $services);
            } catch (Exception $e) {
                error_log("[KCAppointmentPaymentService] Google Calendar Sync Error: " . $e->getMessage());
            }
        }
    }
}
