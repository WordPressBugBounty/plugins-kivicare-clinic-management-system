<?php
namespace App\services;

use App\models\KCClinicSchedule;
use App\models\KCServiceDoctorMapping;
use App\models\KCClinicSession;
use App\models\KCAppointment;
use Exception;

defined('ABSPATH') or die('Something went wrong');

/**
 * Appointment Data Service Class
 * Handles data fetching for appointment slot generation
 */
class KCAppointmentDataService
{
    /**
     * Get service duration sum from service data
     * 
     * @param array $serviceData Service data array
     * @param int $doctorId Doctor ID
     * @param int $clinicId Clinic ID
     * @return int Total service duration in minutes
     */
    public static function getServiceDurationSum(array $serviceData, int $doctorId, int $clinicId): int
    {
        $serviceDurations = [];

        foreach ($serviceData as $serviceId) {
            if ($serviceId) {
                $duration = self::getServiceDuration((int) $serviceId, $doctorId, $clinicId);
                $serviceDurations[] = $duration;
            }
        }

        return array_sum($serviceDurations);
    }

    /**
     * Get service duration using KCServiceDoctorMapping model
     * 
     * @param int $serviceId Service ID
     * @param int $doctorId Doctor ID
     * @param int $clinicId Clinic ID
     * @return int Duration in minutes
     */
    private static function getServiceDuration(int $serviceId, int $doctorId, int $clinicId): int
    {
        $mapping = KCServiceDoctorMapping::query()
            ->where('id', $serviceId)
            ->where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->where('status', 1) // Active services only
            ->first();
        return $mapping ? (int) $mapping->duration : 0; // Default 30 minutes if not found
    }

    /**
     * Fetch doctor sessions using KCClinicSession model
     * 
     * @param int $doctorId Doctor ID
     * @param int $clinicId Clinic ID
     * @param string $date Date in Y-m-d format
     * @return array Sessions array
     */
    public static function getDoctorSessions(int $doctorId, int $clinicId, string $date): array
    {
        $weekday = strtolower(gmdate('l', strtotime($date)));
        $shortWeekday = strtolower(gmdate('D', strtotime($date)));

        return KCClinicSession::query()
            ->where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->where(function ($query) use ($shortWeekday, $weekday) {
                $query->where('day', $shortWeekday)
                    ->orWhere('day', $weekday);
            })
            ->orderBy('startTime')
            ->get()
            ->toArray();
    }

    /**
     * Fetch existing appointments for the given date, doctor, and clinic
     * 
     * @param int $doctorId Doctor ID
     * @param int $clinicId Clinic ID
     * @param string $date Date in Y-m-d format
     * @param int|null $appointmentIdToSkip Appointment ID to skip (for editing)
     * @return array Appointments array
     */
    public static function getExistingAppointments(int $doctorId, int $clinicId, string $date, ?int $appointmentIdToSkip = null): array
    {
        // Build appointment query
        $appointmentQuery = KCAppointment::query()
            ->where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->whereRaw('DATE(appointment_start_date) = "'.$date.'"')
            ->where('status', '!=', KCAppointment::STATUS_CANCELLED); // Exclude cancelled appointments

        // Skip specific appointment if editing
        if ($appointmentIdToSkip) {
            $appointmentQuery->where('id', '!=', $appointmentIdToSkip);
        }

        return $appointmentQuery->get()->toArray();
    }

    /**
     * Check for doctor or clinic leaves using KCClinicSchedule model
     * 
     * @param int $doctorId Doctor ID
     * @param int $clinicId Clinic ID
     * @param string $date Date in Y-m-d format
     * @throws Exception If there are leaves on the requested date
     */
    public static function checkForLeaves(int $doctorId, int $clinicId, string $date): void
    {
        // Check for doctor/clinic holidays
        $holidays = KCClinicSchedule::query()
            ->where(function ($q) use ($doctorId, $clinicId) {
                $q->where(function ($sq) use ($doctorId) {
                    $sq->where('module_type', 'doctor')
                        ->where('module_id', $doctorId);
                })->orWhere(function ($sq) use ($clinicId) {
                    $sq->where('module_type', 'clinic')
                        ->where('module_id', $clinicId);
                });
            })
            ->where('status', 1)
            ->whereRaw(sprintf('DATE(start_date) <= "%s" AND DATE(end_date) >= "%s"', $date, $date))
            ->get();

        foreach ($holidays as $holiday) {
            // 1. Handle Selection Mode
            $isDateMatch = false;
            $selectionMode = $holiday->selectionMode ?? 'range';

            if ($selectionMode === 'multiple') {
                $selectedDates = $holiday->selectedDates ? json_decode($holiday->selectedDates, true) : [];
                if (is_array($selectedDates) && in_array($date, $selectedDates)) {
                    $isDateMatch = true;
                }
            } else {
                // 'single' or 'range' - query already handles date bounds
                $isDateMatch = true;
            }

            if (!$isDateMatch) {
                continue;
            }

            // 2. Handle Time-Specific Holidays
            // If it's time-specific, it doesn't block the WHOLE day, so we don't throw exception here.
            // These will be handled in prepareSlotGenerationData by injecting virtual appointments.
            if ((bool) ($holiday->timeSpecific ?? false)) {
                continue;
            }

            // If we reach here, it's a full-day holiday on this date
            $moduleLabel = $holiday->moduleType === 'doctor' ? __('Doctor', 'kivicare-clinic-management-system') : __('Clinic', 'kivicare-clinic-management-system');
            throw new Exception(sprintf(__("%s is not available on %s", "kivicare-clinic-management-system"), $moduleLabel, esc_html($date)));
        }
    }

    /**
     * Get multiple service durations at once
     * 
     * @param array $serviceIds Array of service IDs
     * @param int $doctorId Doctor ID
     * @param int $clinicId Clinic ID
     * @return array Array of durations keyed by service ID
     */
    public static function getMultipleServiceDurations(array $serviceIds, int $doctorId, int $clinicId): array
    {
        $mappings = KCServiceDoctorMapping::query()
            ->whereIn('service_id', $serviceIds)
            ->where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->where('status', 1)
            ->get();

        $durations = [];
        foreach ($mappings as $mapping) {
            $durations[$mapping->serviceId] = (int) $mapping->duration;
        }

        return $durations;
    }

    /**
     * Prepare all data needed for slot generation
     * 
     * @param array $requestData Original request data
     * @return array Prepared data for AppointmentSlotGenerator
     * @throws Exception If data fetching fails or leaves exist
     */
    public static function prepareSlotGenerationData(array $requestData): array
    {
        $doctorId = (int) $requestData['doctor_id'];
        $clinicId = (int) $requestData['clinic_id'];
        $date = $requestData['date'];
        $appointmentIdToSkip = $requestData['appointment_id'] ?? null;

        // Check for leaves first
        self::checkForLeaves($doctorId, $clinicId, $date);

        // Calculate service duration sum
        if (empty($requestData['service_id'])) {
            throw new Exception("Service data is required for slot generation");
        }

        $serviceDurationSum = self::getServiceDurationSum($requestData['service_id'], $doctorId, $clinicId);
        // Fetch sessions and appointments
        $sessions = self::getDoctorSessions($doctorId, $clinicId, $date);
        $appointments = self::getExistingAppointments($doctorId, $clinicId, $date, $appointmentIdToSkip);

        // Inject time-specific holidays as virtual appointments
        $partialHolidays = KCClinicSchedule::query()
            ->where(function ($q) use ($doctorId, $clinicId) {
                $q->where(function ($sq) use ($doctorId) {
                    $sq->where('module_type', 'doctor')
                        ->where('module_id', $doctorId);
                })->orWhere(function ($sq) use ($clinicId) {
                    $sq->where('module_type', 'clinic')
                        ->where('module_id', $clinicId);
                });
            })
            ->where('status', 1)
            ->where('time_specific', 1)
            ->whereRaw(sprintf('DATE(start_date) <= "%s" AND DATE(end_date) >= "%s"', $date, $date))
            ->get();

        foreach ($partialHolidays as $holiday) {
            $isDateMatch = false;
            $selectionMode = $holiday->selectionMode ?? 'range';

            if ($selectionMode === 'multiple') {
                $selectedDates = $holiday->selectedDates ? json_decode($holiday->selectedDates, true) : [];
                if (is_array($selectedDates) && in_array($date, $selectedDates)) {
                    $isDateMatch = true;
                }
            } else {
                $isDateMatch = true;
            }

            if ($isDateMatch && $holiday->startTime && $holiday->endTime) {
                $appointments[] = [
                    'appointmentStartDate' => $date,
                    'appointmentStartTime' => $holiday->startTime,
                    'appointmentEndDate'   => $date,
                    'appointmentEndTime'   => $holiday->endTime,
                    'status'               => 'holiday_block', // Custom status for identification
                ];
            }
        }

        return [
            'date' => $date,
            'doctor_id' => $doctorId,
            'clinic_id' => $clinicId,
            'service_duration_sum' =>  $serviceDurationSum > 0 ? $serviceDurationSum : ($sessions[0]->timeSlot ?? 10),
            'sessions' => $sessions,
            'appointments' => $appointments,
            'appointment_id' => $appointmentIdToSkip,
            'only_available_slots' => $requestData['only_available_slots'] ?? false
        ];
    }
}
