<?php
namespace App\services;

use DateTimeImmutable;
use DateTime;
use DateInterval;
use Exception;
use App\baseClasses\KCBase;

defined('ABSPATH') or die('Something went wrong');

/**
 * Slot Generator Class
 * Handles the generation of available slots for doctors using direct data values
 */
class KCTimeSlotService
{
    private $date;
    private $doctorId;
    private $clinicId;
    private $slots = [];
    private $serviceDurationSum = 0;
    private $weekday;
    private $shortWeekday;
    private $sessions = [];
    private $appointments = [];
    private $appointmentIdToSkip = null;
    private $onlyAvailableSlots = false;
    private $isNonPatient = false;

    private $sourceTimezone;
    private $targetTimezone;

    // Configuration constants
    const WIDGET_TYPE_PHP = 'phpWidget';

    /**
     * Constructor
     * 
     * @param array $params Direct parameters from controller
     */
    public function __construct(array $params)
    {
        // Set required parameters
        $this->date = $params['date'];
        $this->doctorId = $params['doctor_id'];
        $this->clinicId = $params['clinic_id'];
        $this->serviceDurationSum = $params['service_duration_sum'];
        $this->sessions = $params['sessions'] ?? [];
        $this->appointments = $params['appointments'] ?? [];
        
        // Set optional parameters
        $this->appointmentIdToSkip = $params['appointment_id'] ?? null;
        $this->onlyAvailableSlots = $params['only_available_slots'] ?? false;

        // Timezone Setup — use doctor's timezone as the source of truth
        $doctorTzString = kcGetDoctorTimezone((int) $this->doctorId);
        $this->sourceTimezone = new \DateTimeZone($doctorTzString);

        $targetTzString = $params['target_timezone'] ?? $doctorTzString;
        try {
            $this->targetTimezone = new \DateTimeZone($targetTzString);
        } catch (\Exception $e) {
            $this->targetTimezone = $this->sourceTimezone;
        }

        $kcBase = KCBase::get_instance();
        $userRole = $kcBase->getLoginUserRole();
        // Only these roles can see Google busy slots
        $this->isNonPatient = !empty($userRole) && in_array($userRole, ['administrator', $kcBase->getClinicAdminRole(), $kcBase->getDoctorRole(), $kcBase->getReceptionistRole()]);

        $this->validateInputs();
        $this->determineDay();
    }

    /**
     * Main method to generate appointment slots
     * 
     * @return array Generated slots array
     * @throws Exception If validation fails or required data is missing
     */
    public function generateSlots(): array
    {
        try {
            $this->createSessionDetails();
            $this->generateSlotsFromSessions();

            return $this->slots;

        } catch (Exception $e) {
            throw new Exception("Slot generation failed: " . esc_html($e->getMessage()));
        }
    }

    /**
     * Validate input parameters
     * 
     * @throws Exception If required parameters are missing or invalid
     */
    private function validateInputs(): void
    {
        // Validate required parameters
        $requiredFields = ['date', 'doctor_id', 'clinic_id', 'service_duration_sum'];

        $data = [
            'date' => $this->date,
            'doctor_id' => $this->doctorId,
            'clinic_id' => $this->clinicId,
            'service_duration_sum' => $this->serviceDurationSum
        ];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception(sprintf("Required field '%s' is missing or empty", esc_html($field)));
            }
        }

        // Validate date format
        if (!$this->isValidDate($this->date)) {
            throw new Exception("Invalid date format provided");
        }

        // Validate service duration
        if ($this->serviceDurationSum <= 0) {
            throw new Exception("Invalid service duration provided");
        }

        // Initialize slots array
        $this->slots = [];
    }

    /**
     * Determine day of the week from date
     * Uses the doctor's timezone so the weekday is correct for their local context
     */
    private function determineDay(): void
    {
        $dateObj = new DateTime($this->date, $this->sourceTimezone);
        $this->weekday = strtolower($dateObj->format('l'));
        $this->shortWeekday = strtolower($dateObj->format('D'));
    }

    /**
     * Create clinic session details with adjusted durations
     */
    private function createSessionDetails(): void
    {
        foreach ($this->sessions as &$session) {
            // Convert array to object for easier manipulation
            $session = (object) $session;

            // Adjust session for invalid durations
            if (!isset($session->timeSlot) || $session->timeSlot <= 0) {
                $session->timeSlot = $this->serviceDurationSum;
            }

            // Convert times to DateTimeImmutable using Source Timezone (Doctor's Timezone)
            $session->start_datetime = new DateTimeImmutable($this->date . ' ' . $session->startTime, $this->sourceTimezone);
            $session->end_datetime = new DateTimeImmutable($this->date . ' ' . $session->endTime, $this->sourceTimezone);

            // Cross-midnight detection: if end_time <= start_time, the session
            // spans midnight (e.g., 22:00–02:00). Push end to the next day.
            if ($session->end_datetime <= $session->start_datetime) {
                $session->end_datetime = $session->end_datetime->modify('+1 day');
            }
        }
    }

    /**
     * Generate slots from sessions considering existing appointments
     */
    private function generateSlotsFromSessions(): void
    {
        $currentTime = new DateTimeImmutable('now');
        
        foreach ($this->sessions as $sessionIndex => $session) {
            $sessionSlots = [];

            if ($this->onlyAvailableSlots) {
                // Original behavior: Split session into available chunks (excludes booked times)
                $availableChunks = $this->splitSessionIntoChunks($session);
                
                // Generate slots from each chunk
                foreach ($availableChunks as $chunk) {
                    $chunkSlots = $this->generateSlotsFromChunk($chunk, $session, $currentTime);
                    $sessionSlots = array_merge($sessionSlots, $chunkSlots);
                }
            } else {
                // New behavior: Generate ALL possible slots and mark booked ones as unavailable
                $sessionSlots = $this->generateAllSlotsWithBookingStatus($session, $currentTime);
            }

            $this->slots[$sessionIndex] = $sessionSlots;
        }
    }

    /**
     * Split session into available chunks based on existing appointments
     * 
     * @param object $session Session data
     * @return array Array of available time chunks
     */
    private function splitSessionIntoChunks($session): array
    {
        $chunks = [];
        $sessionStart = $session->start_datetime;
        $sessionEnd = $session->end_datetime;

        // Get appointments that overlap with this session
        $overlappingAppointments = $this->getOverlappingAppointments($session);

        // Sort appointments by start time
        usort($overlappingAppointments, function ($a, $b) {
            $aStart = (is_object($a) && isset($a->appointmentStartUtc)) ? $a->appointmentStartUtc : (is_object($a) && isset($a->appointment_start_utc) ? $a->appointment_start_utc : (is_array($a) && isset($a['appointment_start_utc']) ? $a['appointment_start_utc'] : (is_array($a) ? $a['appointmentStartDate'] . ' ' . $a['appointmentStartTime'] : $a->appointmentStartDate . ' ' . $a->appointmentStartTime)));
            $bStart = (is_object($b) && isset($b->appointmentStartUtc)) ? $b->appointmentStartUtc : (is_object($b) && isset($b->appointment_start_utc) ? $b->appointment_start_utc : (is_array($b) && isset($b['appointment_start_utc']) ? $b['appointment_start_utc'] : (is_array($b) ? $b['appointmentStartDate'] . ' ' . $b['appointmentStartTime'] : $b->appointmentStartDate . ' ' . $b->appointmentStartTime)));
            return strtotime($aStart) - strtotime($bStart);
        });

        $currentStart = $sessionStart;

        foreach ($overlappingAppointments as $appointment) {
            $appointment = (object) $appointment;
            
            // If it's a Google busy block, don't remove it from the available chunks
            // so it remains visible but disabled in the frontend.
            if (($appointment->status ?? '') === 'google_busy_block') {
                continue;
            }
            $utcTimezone = new \DateTimeZone('UTC');
            $startUtcStr = isset($appointment->appointmentStartUtc) ? $appointment->appointmentStartUtc : (isset($appointment->appointment_start_utc) ? $appointment->appointment_start_utc : null);
            $endUtcStr = isset($appointment->appointmentEndUtc) ? $appointment->appointmentEndUtc : (isset($appointment->appointment_end_utc) ? $appointment->appointment_end_utc : null);

            if ($startUtcStr && $endUtcStr) {
                // Use UTC fields and convert to sourceTimezone for comparison
                $appointmentStartObj = new \DateTimeImmutable($startUtcStr, $utcTimezone);
                $appointmentStart = $appointmentStartObj->setTimezone($this->sourceTimezone);
                
                $appointmentEndObj = new \DateTimeImmutable($endUtcStr, $utcTimezone);
                $appointmentEnd = $appointmentEndObj->setTimezone($this->sourceTimezone);
            } else {
                // Fallback to old behavior if UTC columns are mysteriously unavailable
                $appointmentStart = new DateTimeImmutable($appointment->appointmentStartDate . ' ' . $appointment->appointmentStartTime, $this->sourceTimezone);
                $appointmentEnd = new DateTimeImmutable($appointment->appointmentEndDate . ' ' . $appointment->appointmentEndTime, $this->sourceTimezone);
            }

            // If there's a gap before this appointment, add it as a chunk
            if ($currentStart < $appointmentStart) {
                $chunks[] = [
                    'start' => $currentStart,
                    'end' => $appointmentStart
                ];
            }

            // Move current start to after this appointment
            $currentStart = $appointmentEnd > $currentStart ? $appointmentEnd : $currentStart;
        }

        // Add remaining time after last appointment
        if (!empty($overlappingAppointments) && $currentStart < $sessionEnd) {
            $chunks[] = [
                'start' => $currentStart,
                'end' => $sessionEnd
            ];
        }

        // If no appointments, the entire session is available
        if (empty($overlappingAppointments)) {
            $chunks[] = [
                'start' => $sessionStart,
                'end' => $sessionEnd
            ];
        }

        return $chunks;
    }

    /**
     * Get appointments that overlap with the given session
     * 
     * @param object $session Session data
     * @return array Overlapping appointments
     */
    private function getOverlappingAppointments($session): array
    {
        $overlapping = [];

        foreach ($this->appointments as $appointment) {
            $appointment = (object) $appointment;
            
            $utcTimezone = new \DateTimeZone('UTC');
            $startUtcStr = isset($appointment->appointmentStartUtc) ? $appointment->appointmentStartUtc : (isset($appointment->appointment_start_utc) ? $appointment->appointment_start_utc : null);
            $endUtcStr = isset($appointment->appointmentEndUtc) ? $appointment->appointmentEndUtc : (isset($appointment->appointment_end_utc) ? $appointment->appointment_end_utc : null);

            if ($startUtcStr && $endUtcStr) {
                // Use UTC fields and convert to sourceTimezone for comparison
                $appointmentStartObj = new \DateTimeImmutable($startUtcStr, $utcTimezone);
                $appointmentStart = $appointmentStartObj->setTimezone($this->sourceTimezone);
                
                $appointmentEndObj = new \DateTimeImmutable($endUtcStr, $utcTimezone);
                $appointmentEnd = $appointmentEndObj->setTimezone($this->sourceTimezone);
            } else {
                // Fallback to old behavior
                $appointmentStart = new DateTimeImmutable($appointment->appointmentStartDate . ' ' . $appointment->appointmentStartTime, $this->sourceTimezone);
                $appointmentEnd = new DateTimeImmutable($appointment->appointmentEndDate . ' ' . $appointment->appointmentEndTime, $this->sourceTimezone);
            }

            // Check if appointment overlaps with session
            if ($appointmentStart < $session->end_datetime && $appointmentEnd > $session->start_datetime) {
                $overlapping[] = $appointment;
            }
        }

        return $overlapping;
    }

    /**
     * Generate slots from a time chunk
     * 
     * @param array $chunk Time chunk with start and end
     * @param object $session Session data
     * @param DateTimeImmutable $currentTime Current time for future validation
     * @return array Generated slots
     */
    private function generateSlotsFromChunk(array $chunk, $session, DateTimeImmutable $currentTime): array
    {
        $slots = [];
        $slotDuration = $this->serviceDurationSum ?: $session->timeSlot;
        $interval = new DateInterval('PT' . $slotDuration . 'M');

        $slotStart = $chunk['start'];
        while ($slotStart < $chunk['end']) {
            $slotEnd = $slotStart->add($interval);

            // Check if slot fits within chunk
            if ($slotEnd <= $chunk['end']) {
                // Check if this slot overlaps with any booked appointment (e.g. Google busy blocks)
                $booking = $this->getBookingForSlot($slotStart, $slotEnd);
                $isBooked = !empty($booking);

                // Availability check uses strict absolute time comparison
                $isFuture = $slotStart > $currentTime;
                $isAvailable = $isFuture && !$isBooked;

                if (!$this->onlyAvailableSlots || $isFuture) { 
                    // Convert to Target Timezone for display (immutable — returns new object)
                    $displayStart = $slotStart->setTimezone($this->targetTimezone);

                    $slotData = [
                        'time' => $this->formatTime($displayStart),
                        'available' => $isAvailable,
                        'booked' => $isBooked,
                        'datetime' => $displayStart->format('Y-m-d H:i:s'),
                        'session_id' => $session->id ?? null,
                        'source_datetime' => $slotStart->format('Y-m-d H:i:s'),
                        'selected_date_time' => $displayStart->format('Y-m-d H:i:s'),
                        'time_format' => $displayStart->format('H:i:s')
                    ];

                    if ($isBooked) {
                        $slotData['booked_status'] = $booking->status ?? null;
                        if (($booking->status ?? '') === 'google_busy_block') {
                            $slotData['is_google_event'] = true;
                            $slotData['google_event_name'] = $booking->google_event_name ?? __('Busy on Google Calendar', 'kivicare-clinic-management-system');
                        }
                    }

                    // Exclude google_busy_block if user is a patient
                    if ($isBooked && (($booking->status ?? '') === 'google_busy_block') && !$this->isNonPatient) {
                        // Move to next slot iteration early
                        $slotStart = $slotStart->add($interval);
                        continue;
                    }

                    $slots[] = $slotData;
                }
            }

            // Move to next slot (immutable — returns new object)
            $slotStart = $slotStart->add($interval);
        }

        return $slots;
    }

    /**
     * Generate all possible slots for a session and mark booked ones as unavailable
     * Used when only_available_slots is false to get accurate total slot counts
     * 
     * @param object $session Session data
     * @param DateTimeImmutable $currentTime Current time for future validation
     * @return array Generated slots with booking status
     */
    private function generateAllSlotsWithBookingStatus($session, DateTimeImmutable $currentTime): array
    {
        $slots = [];
        $slotDuration = $this->serviceDurationSum ?: $session->timeSlot;
        $interval = new DateInterval('PT' . $slotDuration . 'M');
        
        $slotStart = $session->start_datetime;
        $sessionEnd = $session->end_datetime;
        
        while ($slotStart < $sessionEnd) {
            $slotEnd = $slotStart->add($interval);
            
            // Check if slot fits within session
            if ($slotEnd <= $sessionEnd) {
                // Check if this slot overlaps with any booked appointment
                $booking = $this->getBookingForSlot($slotStart, $slotEnd);
                $isBooked = !empty($booking);
                
                // Determine if slot is available (absolute time comparison)
                $isAvailable = ($slotStart > $currentTime) && !$isBooked;
                
                // Convert to Target Timezone for display (immutable — returns new object)
                $displayStart = $slotStart->setTimezone($this->targetTimezone);

                $slotData = [
                    'time' => $this->formatTime($displayStart),
                    'available' => $isAvailable,
                    'booked' => $isBooked,
                    'datetime' => $displayStart->format('Y-m-d H:i:s'),
                    'session_id' => $session->id ?? null,
                    'source_datetime' => $slotStart->format('Y-m-d H:i:s'),
                    'selected_date_time' => $displayStart->format('Y-m-d H:i:s'),
                    'time_format' => $displayStart->format('H:i:s')
                ];

                if ($isBooked) {
                    $slotData['booked_status'] = $booking->status ?? null;
                    if (($booking->status ?? '') === 'google_busy_block') {
                        $slotData['is_google_event'] = true;
                    }
                }

                // Exclude google_busy_block if user is a patient
                if ($isBooked && (($booking->status ?? '') === 'google_busy_block') && !$this->isNonPatient) {
                    $slotStart = $slotStart->add($interval);
                    continue;
                }

                $slots[] = $slotData;
            }
            
            // Move to next slot (immutable — returns new object)
            $slotStart = $slotStart->add($interval);
        }
        
        return $slots;
    }
    
    /**
     * Check if a specific time slot overlaps with any booked appointment and return the booking
     * 
     * @param DateTimeImmutable $slotStart Slot start time
     * @param DateTimeImmutable $slotEnd Slot end time
     * @return object|null The appointment object if booked, null otherwise
     */
    private function getBookingForSlot(DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd)
    {
        foreach ($this->appointments as $appointment) {
            $appointment = (object) $appointment;
            
            // Skip the appointment we're editing (if any)
            if ($this->appointmentIdToSkip && isset($appointment->id) && $appointment->id == $this->appointmentIdToSkip) {
                continue;
            }
            
            $utcTimezone = new \DateTimeZone('UTC');
            $startUtcStr = isset($appointment->appointmentStartUtc) ? $appointment->appointmentStartUtc : (isset($appointment->appointment_start_utc) ? $appointment->appointment_start_utc : null);
            $endUtcStr = isset($appointment->appointmentEndUtc) ? $appointment->appointmentEndUtc : (isset($appointment->appointment_end_utc) ? $appointment->appointment_end_utc : null);

            if ($startUtcStr && $endUtcStr) {
                // Use UTC fields and convert to sourceTimezone for comparison
                $appointmentStartObj = new \DateTimeImmutable($startUtcStr, $utcTimezone);
                $appointmentStart = $appointmentStartObj->setTimezone($this->sourceTimezone);
                
                $appointmentEndObj = new \DateTimeImmutable($endUtcStr, $utcTimezone);
                $appointmentEnd = $appointmentEndObj->setTimezone($this->sourceTimezone);
            } else {
                // Fallback to old behavior
                $appointmentStart = new DateTimeImmutable($appointment->appointmentStartDate . ' ' . $appointment->appointmentStartTime, $this->sourceTimezone);
                $appointmentEnd = new DateTimeImmutable($appointment->appointmentEndDate . ' ' . $appointment->appointmentEndTime, $this->sourceTimezone);
            }
            
            // Check for overlap
            if ($slotStart < $appointmentEnd && $slotEnd > $appointmentStart) {
                return $appointment;
            }
        }
        
        return null;
    }

    /**
     * Format time using kcGetFormatedTime function or fallback
     * 
     * @param DateTimeImmutable $dateTime DateTime object
     * @return string Formatted time
     */
    private function formatTime(DateTimeImmutable $dateTime): string
    {
        // Use kcGetFormatedTime if available, otherwise use default format
        if (function_exists('kcGetFormatedTime')) {
            return kcGetFormatedTime($dateTime->format('H:i:s'));
        }

        return $dateTime->format('g:i A');
    }

    /**
     * Validate date format
     * 
     * @param string $date Date string
     * @return bool True if valid
     */
    private function isValidDate(string $date): bool
    {
        $formats = ['Y-m-d', 'd-m-Y', 'm/d/Y', 'Y/m/d'];

        foreach ($formats as $format) {
            $dateTime = DateTime::createFromFormat($format, $date);
            if ($dateTime && $dateTime->format($format) === $date) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current time (can be overridden for testing)
     * 
     * @return DateTimeImmutable Current time
     */
    protected function getCurrentTime(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->sourceTimezone);
    }

    /**
     * Get generated slots
     * 
     * @return array Slots array
     */
    public function getSlots(): array
    {
        return $this->slots;
    }

    /**
     * Get service duration sum
     * 
     * @return int Total service duration in minutes
     */
    public function getServiceDurationSum(): int
    {
        return $this->serviceDurationSum;
    }

    /**
     * Get sessions data
     * 
     * @return array Sessions array
     */
    public function getSessions(): array
    {
        return $this->sessions;
    }

    /**
     * Debug method to get all internal data
     * 
     * @return array Debug information
     */
    public function getDebugInfo(): array
    {
        return [
            'date' => $this->date,
            'doctor_id' => $this->doctorId,
            'clinic_id' => $this->clinicId,
            'weekday' => $this->weekday,
            'short_weekday' => $this->shortWeekday,
            'service_duration_sum' => $this->serviceDurationSum,
            'sessions_count' => count($this->sessions),
            'appointments_count' => count($this->appointments),
            'slots_count' => array_sum(array_map('count', $this->slots)),
            'source_timezone' => $this->sourceTimezone->getName(),
            'target_timezone' => $this->targetTimezone->getName()
        ];
    }

    /**
     * Check if a specific time slot is available
     * 
     * @param string $slotDateTime Datetime string for the slot
     * @return bool True if available
     */
    public function isSlotAvailable(string $slotDateTime): bool
    {
        $slotStart = new \DateTimeImmutable($slotDateTime, $this->sourceTimezone);
        $slotEnd = $slotStart->add(new \DateInterval('PT' . $this->serviceDurationSum . 'M'));

        // Check against existing appointments
        return empty($this->getBookingForSlot($slotStart, $slotEnd));
    }

}

