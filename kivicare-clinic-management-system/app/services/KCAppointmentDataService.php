<?php
namespace App\services;

use App\models\KCClinicSession;
use App\models\KCClinicSchedule;
use App\models\KCAppointment;
use App\models\KCDoctor;
use App\models\KCServiceDoctorMapping;
use App\models\KCOption;
use App\baseClasses\KCErrorLogger;
use KCProApp\controllers\api\GoogleCalendarIntegration;
use DateTime;
use DateTimeZone;
use DateTimeImmutable;

defined('ABSPATH') or die('Something went wrong');

/**
 * Service to handle data retrieval for appointment slots
 * Now supports both single-day and batch-month retrieval strategies
 */
class KCAppointmentDataService
{
    /**
     * Get doctor sessions for a specific date (and clinic)
     *
     * @param int $doctorId
     * @param int $clinicId
     * @param string $date Y-m-d format
     * @return array
     */
    public static function getDoctorSessions($doctorId, $clinicId, $date)
    {
        // Resolve weekday in the doctor's local timezone (not GMT)
        $doctorTz = new \DateTimeZone(kcGetDoctorTimezone($doctorId));
        $dateObj = new \DateTime($date, $doctorTz);
        $weekday = strtolower($dateObj->format('l'));
        $shortWeekday = strtolower($dateObj->format('D'));

        return KCClinicSession::query()
            ->where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->where(function ($query) use ($weekday, $shortWeekday) {
                $query->where('day', $weekday)
                      ->orWhere('day', $shortWeekday);
            })
            ->get();
    }

    /**
     * Get service sessions for a specific mapping date (Pro addon)
     *
     * @param int $mappingId Service-doctor-clinic mapping ID
     * @param string $date Y-m-d format
     * @param string $doctorId Doctor ID for timezone resolution
     * @return array
     */
    public static function getServiceSessions($mappingId, $date, $doctorId)
    {
        // Check if KCServiceSession model exists (pro addon)
        if (!class_exists('KCProApp\\models\\KCServiceSession')) {
            return [];
        }

        // Resolve weekday in the doctor's local timezone (not GMT)
        $doctorTz = new \DateTimeZone(kcGetDoctorTimezone($doctorId));
        $dateObj = new \DateTime($date, $doctorTz);
        $weekday = strtolower($dateObj->format('l'));
        $shortWeekday = strtolower($dateObj->format('D'));

        // Use dynamic class reference to avoid strict type checking
        $serviceSessionClass = 'KCProApp\\models\\KCServiceSession';
        return $serviceSessionClass::query()
            ->where('mapping_id', $mappingId)
            ->where(function ($query) use ($weekday, $shortWeekday) {
                $query->where('day', $weekday)
                      ->orWhere('day', $shortWeekday);
            })
            ->get();
    }

    /**
     * Get existing appointments for a doctor on a specific date (collision check)
     * 
     * @param int $doctorId
     * @param int $clinicId 
     * @param string $date Y-m-d
     * @param int|null $excludeAppointmentId
     * @return array
     */
    public static function getExistingAppointments($doctorId, $clinicId, $date, $excludeAppointmentId = null)
    {
        // Convert the doctor-local day into a UTC range for querying
        $doctorTz = new \DateTimeZone(kcGetDoctorTimezone($doctorId));
        $utcTz = new \DateTimeZone('UTC');

        $dayStart = new \DateTime($date . ' 00:00:00', $doctorTz);
        $dayEnd = new \DateTime($date . ' 23:59:59', $doctorTz);
        $dayStart->setTimezone($utcTz);
        $dayEnd->setTimezone($utcTz);

        $dayStartUtc = $dayStart->format('Y-m-d H:i:s');
        $dayEndUtc = $dayEnd->format('Y-m-d H:i:s');

        // Query via indexed UTC columns — finds any appointment that
        // overlaps with the doctor-local day, regardless of UTC date boundary
        $appointmentQuery = KCAppointment::query()
            ->where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->where('appointment_start_utc', '<', $dayEndUtc)
            ->where('appointment_end_utc', '>', $dayStartUtc)
            ->where('status', '!=', KCAppointment::STATUS_CANCELLED);

        if ($excludeAppointmentId) {
            $appointmentQuery->where('id', '!=', $excludeAppointmentId);
        }

        return $appointmentQuery->get()->toArray();
    }
    
    /**
     * Get total duration of services
     * 
     * @param mixed $serviceIds
     * @param int $doctorId
     * @param int $clinicId
     * @return int Duration in minutes
     */
    public static function getServiceDurationSum($serviceIds, $doctorId, $clinicId)
    {
        if (empty($serviceIds)) {
            return 0;
        }
        
        // Ensure array format
        $serviceIds = is_array($serviceIds) ? $serviceIds : explode(',', $serviceIds); 
        
        $durations = KCServiceDoctorMapping::query()
            ->where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->whereIn('id', $serviceIds)
            ->get();

        $sum = 0;
        foreach ($durations as $mapping) {
            $sum += (int) $mapping->duration;
        }

        return $sum;
    }

    /**
     * Prepare all data required for slot generation for a SINGLE day
     * 
     * @param array $requestData Request parameters
     * @return array Data ready for KCTimeSlotService
     */
    public static function prepareSlotGenerationData($requestData)
    {
        $doctorId = (int) $requestData['doctor_id'];
        $clinicId = (int) $requestData['clinic_id'];
        $date = $requestData['date'];
        $appointmentIdToSkip = isset($requestData['appointment_id']) ? (int) $requestData['appointment_id'] : null;

        // 1. Check for full-day holidays (Doctor or Clinic)
        $isHoliday = self::checkForLeaves($doctorId, $clinicId, $date);
        
        if ($isHoliday) {
            throw new \Exception(__('Doctor is on leave for this date.', 'kivicare-clinic-management-system'));
        }        
        
        $serviceDurationSum = self::getServiceDurationSum($requestData['service_id'], $doctorId, $clinicId);
        
        // Check if strict_service_session is enabled
        $strict_service_session = KCOption::get('strict_service_session') ?? 'off';

        // Fetch doctor sessions for this day
        $doctorSessions = self::getDoctorSessions($doctorId, $clinicId, $date);
        $sessions = $doctorSessions;
        
        // PRIORITY: Check for service sessions first (if service mapping exists)
        $serviceId = $requestData['service_id'];
        if ($serviceId) {
            // Get service-doctor-clinic mapping
            $mappings = KCServiceDoctorMapping::query()
                ->where('doctor_id', $doctorId)
                ->where('clinic_id', $clinicId)
                ->whereIn('service_id', is_array($serviceId) ? $serviceId : [$serviceId])
                ->get();

            if (count($mappings) > 0) {
                $serviceSessions = [];
                $mapping_ids = [];
                
                foreach ($mappings as $mapping) {
                    $mapping_ids[] = $mapping->id;
                    $serviceSessionResult = self::getServiceSessions($mapping->id, $date, $doctorId);
                    $serviceSessionArray = is_array($serviceSessionResult) ? $serviceSessionResult : ($serviceSessionResult ? $serviceSessionResult->toArray() : []);
                    $serviceSessions = array_merge($serviceSessions, $serviceSessionArray);
                }

                // Check if they have ANY session defined across ANY day (for strict mode check)
                $hasAnyServiceSessionAtAll = false;
                if (!empty($mapping_ids) && class_exists('KCProApp\\models\\KCServiceSession')) {
                    $serviceSessionClass = 'KCProApp\\models\\KCServiceSession';
                    $hasAnyServiceSessionAtAll = $serviceSessionClass::query()->whereIn('mapping_id', $mapping_ids)->count() > 0;
                }

                if (!empty($serviceSessions)) {
                    // Service sessions exist for THIS day, use them instead of doctor sessions
                    $sessions = $serviceSessions;
                } else if ($strict_service_session === 'on' && $hasAnyServiceSessionAtAll) {
                    // Strict mode: they have service sessions globally, but none for THIS day.
                    // Wipe doctor sessions so no slots are generated.
                    $sessions = [];
                }
            }
        }

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

        // Resolve doctor timezone once for any holiday TZ conversions
        $doctorTzString = kcGetDoctorTimezone($doctorId);

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
                // Use the holiday's stored timezone, falling back to doctor's timezone
                $holidayTz = $holiday->timezone ?: $doctorTzString;

                // Holiday times are in the holiday's timezone frame.
                // Since session times are in the doctor's timezone, we need to
                // convert holiday times to the doctor's timezone for consistent comparison.
                $holidayTzObj = new \DateTimeZone($holidayTz);
                $doctorTzObj = new \DateTimeZone($doctorTzString);

                $blockStart = new \DateTime($date . ' ' . $holiday->startTime, $holidayTzObj);
                $blockEnd = new \DateTime($date . ' ' . $holiday->endTime, $holidayTzObj);

                // Convert to doctor's timezone for consistency with session times
                $blockStart->setTimezone($doctorTzObj);
                $blockEnd->setTimezone($doctorTzObj);

                $appointments[] = [
                    'appointmentStartDate' => $blockStart->format('Y-m-d'),
                    'appointmentStartTime' => $blockStart->format('H:i:s'),
                    'appointmentEndDate'   => $blockEnd->format('Y-m-d'),
                    'appointmentEndTime'   => $blockEnd->format('H:i:s'),
                    'status'               => 'holiday_block',
                ];
            }
        }

        // 3. Inject External Busy Slots (e.g., Google Calendar via Pro plugin)
        $appointments = self::injectExternalBusySlots($doctorId, $date, $appointments);

        return [
            'date' => $date,
            'doctor_id' => $doctorId,
            'clinic_id' => $clinicId,
            'service_duration_sum' =>  $serviceDurationSum > 0 ? $serviceDurationSum : ($sessions[0]->timeSlot ?? 10),
            'sessions' => $sessions,
            'appointments' => $appointments,
            'appointment_id' => $appointmentIdToSkip,
            'only_available_slots' => $requestData['only_available_slots'] ?? false,
            'target_timezone' => $requestData['target_timezone'] ?? null
        ];
    }
    
    /**
     * Batch fetch ALL data needed for a full month of slot generation.
     * Reduces 30+ DB queries to ~4 optimized queries.
     *
     * @param int $doctorId
     * @param int $clinicId
     * @param int $month
     * @param int $year
     * @param mixed $serviceId Service ID or array of service IDs (optional, for service sessions)
     * @return array Prefetched data grouped by type
     */
    public static function batchFetchMonthData($doctorId, $clinicId, $month, $year, $serviceId = null) 
    {
        $doctorTzString = kcGetDoctorTimezone($doctorId);
        $doctorTzObj = new DateTimeZone($doctorTzString);
        $utcTzObj = new DateTimeZone('UTC');

        // Calculate month boundaries in Doctor's Timezone
        // Start: First day of month 00:00:00 minus 3 days for timezone overspill
        $monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $doctorTzObj);
        $monthStart = $monthStart->modify('-3 days');
        // End: Last day of month 23:59:59 plus 3 days for timezone overspill
        $monthEnd = (new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $doctorTzObj))->modify('last day of this month')->setTime(23, 59, 59)->modify('+3 days');

        // Convert to UTC for database querying
        $monthStartUtc = $monthStart->setTimezone($utcTzObj)->format('Y-m-d H:i:s');
        $monthEndUtc = $monthEnd->setTimezone($utcTzObj)->format('Y-m-d H:i:s');
        
        // 1. Fetch ALL appointments overlapping this month range
        $appointments = KCAppointment::query()
            ->where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->where('appointment_start_utc', '<', $monthEndUtc)
            ->where('appointment_end_utc', '>', $monthStartUtc)
            ->where('status', '!=', KCAppointment::STATUS_CANCELLED)
            ->get();

        // 2. Fetch ALL holidays overlapping this month range
        $localStartDate = $monthStart->format('Y-m-d');
        $localEndDate = $monthEnd->format('Y-m-d');
        
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
            ->where('start_date', '<=', $localEndDate)
            ->where('end_date', '>=', $localStartDate)
            ->get();

        // 3. Fetch ALL session templates (recurring weekly schedule)
        $sessions = KCClinicSession::query()
            ->where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->get();

        // 4. Fetch service sessions if service ID provided (Pro addon)
        $serviceSessions = apply_filters('kc_fetch_service_sessions', collect([]), $serviceId, $doctorId, $clinicId);

        // 4. Fetch external busy slots for the month range (e.g., Google Calendar via Pro plugin)
        $externalBusySlots = apply_filters('kc_external_busy_slots', [], $doctorId, $localStartDate, $localEndDate);

        return [
            'appointments' => $appointments,
            'holidays' => $holidays,
            'sessions' => $sessions,
            'service_sessions' => $serviceSessions,
            'external_busy_slots' => $externalBusySlots,
            'doctor_timezone' => $doctorTzString
        ];
    }

    /**
     * Prepare slot generation data using cached month data (No DB queries)
     * 
     * @param array $requestData
     * @param array $cachedData Result from batchFetchMonthData
     * @return array
     */
    public static function prepareSlotGenerationDataFromCache($requestData, $cachedData)
    {
        $doctorId = (int) $requestData['doctor_id'];
        $clinicId = (int) $requestData['clinic_id'];
        $date = $requestData['date']; // Y-m-d
        $appointmentIdToSkip = isset($requestData['appointment_id']) ? (int) $requestData['appointment_id'] : null;

        $doctorTzString = $cachedData['doctor_timezone'];
        $doctorTzObj = new DateTimeZone($doctorTzString);
        
        // Resolve weekday
        $dateObj = new \DateTime($date, $doctorTzObj);
        $weekday = strtolower($dateObj->format('l'));
        $shortWeekday = strtolower($dateObj->format('D'));

        // 1. Sessions: Filter from cache
        $strict_service_session = KCOption::get('strict_service_session') ?? 'off';

        // PRIORITY: Check service sessions first (if available)
        $sessions = [];
        $hasAnyServiceSessionAtAll = !empty($cachedData['service_sessions']) && count($cachedData['service_sessions']) > 0;

        if ($hasAnyServiceSessionAtAll) {
            // Service sessions exist globally - filter for this day
            foreach ($cachedData['service_sessions'] as $session) {
                if ($session['day'] === $weekday || $session['day'] === $shortWeekday) {
                    $sessions[] = $session;
                }
            }
        }

        // Fallback logic
        $shouldFallbackToDoctor = false;
        
        if (empty($sessions)) {
            if ($strict_service_session === 'on') {
                // Strict mode ON: only fallback if there are NO service sessions AT ALL in the DB
                if (!$hasAnyServiceSessionAtAll) {
                    $shouldFallbackToDoctor = true;
                }
            } else {
                // Strict mode OFF: fallback whenever there are no service sessions for THIS specific day
                $shouldFallbackToDoctor = true;
            }
        }

        if ($shouldFallbackToDoctor && !empty($cachedData['sessions'])) {
            foreach ($cachedData['sessions'] as $session) {
                if ($session->day === $weekday || $session->day === $shortWeekday) {
                    $sessions[] = $session;
                }
            }
        }

        // 2. Holidays: Filter from cache
        $fullDayHolidayFound = false;
        $partialHolidays = [];

        foreach ($cachedData['holidays'] as $holiday) {
            // Check date range overlap in local time logic
            if ($holiday->startDate <= $date && $holiday->endDate >= $date) {
                // Check selection mode
                $selectionMode = $holiday->selectionMode ?? 'range';
                $isDateMatch = false;

                if ($selectionMode === 'multiple') {
                    $selectedDates = $holiday->selectedDates ? json_decode($holiday->selectedDates, true) : [];
                    if (is_array($selectedDates) && in_array($date, $selectedDates)) {
                        $isDateMatch = true;
                    }
                } else {
                    $isDateMatch = true;
                }

                if ($isDateMatch) {
                    if (!$holiday->timeSpecific) {
                        $fullDayHolidayFound = true;
                        break; // Stop if full day holiday found
                    } else {
                        $partialHolidays[] = $holiday;
                    }
                }
            }
        }

        if ($fullDayHolidayFound) {
            throw new \Exception(__('Doctor is on leave for this date.', 'kivicare-clinic-management-system'));
        }

        // 3. Appointments: Filter from cache
        // We need to determine the UTC range for this specific day to filter relevant appointments
        $dayStart = new \DateTime($date . ' 00:00:00', $doctorTzObj);
        $dayEnd = new \DateTime($date . ' 23:59:59', $doctorTzObj);
        $utcTz = new \DateTimeZone('UTC');
        
        $dayStartUtc = (clone $dayStart)->setTimezone($utcTz);
        $dayEndUtc = (clone $dayEnd)->setTimezone($utcTz);

        $dayAppointments = [];
        foreach ($cachedData['appointments'] as $apt) {
            if ($appointmentIdToSkip && $apt->id == $appointmentIdToSkip) {
                continue;
            }

            // Convert stored UTC strings to DateTime objects for comparison
            $aptStart = new \DateTime($apt->appointmentStartUtc, $utcTz);
            $aptEnd = new \DateTime($apt->appointmentEndUtc, $utcTz);

            // Check overlap: Start < End AND End > Start
            if ($aptStart < $dayEndUtc && $aptEnd > $dayStartUtc) {
                $dayAppointments[] = $apt;
            }
        }

        // 4. Inject Partial Holidays
        foreach ($partialHolidays as $holiday) {
            $holidayTz = $holiday->timezone ?: $doctorTzString;
            $holidayTzObj = new \DateTimeZone($holidayTz);
            
            $blockStart = new \DateTime($date . ' ' . $holiday->startTime, $holidayTzObj);
            $blockEnd = new \DateTime($date . ' ' . $holiday->endTime, $holidayTzObj);

            // Convert to doctor's timezone
            $blockStart->setTimezone($doctorTzObj);
            $blockEnd->setTimezone($doctorTzObj);

            $dayAppointments[] = [
                'appointmentStartDate' => $blockStart->format('Y-m-d'),
                'appointmentStartTime' => $blockStart->format('H:i:s'),
                'appointmentEndDate'   => $blockEnd->format('Y-m-d'),
                'appointmentEndTime'   => $blockEnd->format('H:i:s'),
                'status'               => 'holiday_block',
            ];
        }

        // 5. Inject External Busy Slots
        // Use pre-fetched slots from cache if available to avoid network requests
        $dayAppointments = self::injectExternalBusySlots($doctorId, $date, $dayAppointments, $cachedData['external_busy_slots'] ?? null);
        $serviceDurationSum = self::getServiceDurationSum($requestData['service_id'], $doctorId, $clinicId);

        return [
            'date' => $date,
            'doctor_id' => $doctorId,
            'clinic_id' => $clinicId,
            'service_duration_sum' =>  $serviceDurationSum > 0 ? $serviceDurationSum : ($sessions[0]->timeSlot ?? 10),
            'sessions' => $sessions,
            'appointments' => $dayAppointments,
            'external_busy_slots' => $cachedData['external_busy_slots'] ?? [],
            'appointment_id' => $appointmentIdToSkip,
            'only_available_slots' => $requestData['only_available_slots'] ?? false,
            'target_timezone' => $requestData['target_timezone'] ?? null,
            'source_timezone_string' => $doctorTzString // Pass this to avoid re-resolving
        ];
    }
    
    /**
     * Check if doctor is on leave for a specific date (Full day)
     * 
     * @param int $doctorId
     * @param int $clinicId
     * @param string $date Y-m-d
     * @return bool
     */
    public static function checkForLeaves($doctorId, $clinicId, $date)
    {
        $leaves = KCClinicSchedule::query()
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

        foreach ($leaves as $leave) {
            
            // Check selection mode
            $selectionMode = $leave->selectionMode ?? 'range';
            $isDateMatch = false;

            if ($selectionMode === 'multiple') {
                $selectedDates = $leave->selectedDates ? json_decode($leave->selectedDates, true) : [];
                if (is_array($selectedDates) && in_array($date, $selectedDates)) {
                    $isDateMatch = true;
                }
            } else {
                // range or single is handled by SQL query
                $isDateMatch = true; 
            }

            // Only block if it is a FULL DAY holiday (time_specific = 0 or false)
            // Time specific holidays are handled as virtual booked slots
            if ($isDateMatch && (!$leave->timeSpecific || $leave->timeSpecific == 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inject external busy slots as virtual appointments
     * 
     * @param int $doctorId
     * @param string $date Y-m-d
     * @param array $appointments
     * @param array|null $preFetchedBusySlots Optional pre-fetched slots to avoid network request
     * @return array
     */
    private static function injectExternalBusySlots($doctorId, $date, $appointments, $preFetchedBusySlots = null)
    {
        try {
            $busySlots = [];
            if ($preFetchedBusySlots !== null) {
                // Filter pre-fetched slots for this specific date
                foreach ($preFetchedBusySlots as $busy) {
                    if (substr($busy['start'], 0, 10) === $date) {
                        $busySlots[] = $busy;
                    }
                }
            } else {
                // Fallback to single-date network request if not pre-fetched
                $busySlots = apply_filters('kc_external_busy_slots_single', [], $doctorId, $date);
            }

            if (!empty($busySlots)) {
                foreach ($busySlots as $busy) {
                    $appointments[] = [
                        'appointmentStartDate' => substr($busy['start'], 0, 10),
                        'appointmentStartTime' => substr($busy['start'], 11),
                        'appointmentEndDate'   => substr($busy['end'], 0, 10),
                        'appointmentEndTime'   => substr($busy['end'], 11),
                        'status'               => 'google_busy_block',
                        'google_event_name'    => $busy['name'] ?? __('Busy', 'kivicare-clinic-management-system'),
                    ];
                }
            }
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Error injecting external busy slots: ' . $e->getMessage());
        }

        return $appointments;
    }
}
