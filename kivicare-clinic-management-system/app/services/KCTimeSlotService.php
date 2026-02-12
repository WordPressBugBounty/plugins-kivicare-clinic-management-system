<?php
namespace App\services;

use DateTime;
use DateInterval;
use Exception;

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
     */
    private function determineDay(): void
    {
        $this->weekday = strtolower(gmdate('l', strtotime($this->date)));
        $this->shortWeekday = strtolower(gmdate('D', strtotime($this->date)));
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

            // Convert times to DateTime objects for easier manipulation
            $session->start_datetime = new DateTime($this->date . ' ' . $session->startTime,  wp_timezone());
            $session->end_datetime = new DateTime($this->date . ' ' . $session->endTime,  wp_timezone());
        }
    }

    /**
     * Generate slots from sessions considering existing appointments
     */
    private function generateSlotsFromSessions(): void
    {
        $currentTime = new DateTime('now', wp_timezone());
        
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
        $sessionStart = clone $session->start_datetime;
        $sessionEnd = clone $session->end_datetime;

        // Get appointments that overlap with this session
        $overlappingAppointments = $this->getOverlappingAppointments($session);

        // Sort appointments by start time
        usort($overlappingAppointments, function ($a, $b) {
            $aStart = is_array($a) ? $a['appointmentStartDate'] . ' ' . $a['appointmentStartTime']
                : $a->appointmentStartDate . ' ' . $a->appointmentStartTime;
            $bStart = is_array($b) ? $b['appointmentStartDate'] . ' ' . $b['appointmentStartTime']
                : $b->appointmentStartDate . ' ' . $b->appointmentStartTime;
            return strtotime($aStart) - strtotime($bStart);
        });

        $currentStart = clone $sessionStart;

        foreach ($overlappingAppointments as $appointment) {
            $appointment = (object) $appointment;
            $appointmentStart = new DateTime($appointment->appointmentStartDate . ' ' . $appointment->appointmentStartTime, wp_timezone());
            $appointmentEnd = new DateTime($appointment->appointmentEndDate . ' ' . $appointment->appointmentEndTime, wp_timezone());
            // If there's a gap before this appointment, add it as a chunk
            if ($currentStart < $appointmentStart) {
                $chunks[] = [
                    'start' => clone $currentStart,
                    'end' => clone $appointmentStart
                ];
            }

            // Move current start to after this appointment
            $currentStart = $appointmentEnd > $currentStart ? clone $appointmentEnd : clone $currentStart;
        }

        // Add remaining time after last appointment
        if (!empty($overlappingAppointments) && $currentStart < $sessionEnd) {
            $chunks[] = [
                'start' => clone $currentStart,
                'end' => clone $sessionEnd
            ];
        }

        // If no appointments, the entire session is available
        if (empty($overlappingAppointments)) {
            $chunks[] = [
                'start' => clone $sessionStart,
                'end' => clone $sessionEnd
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
            $appointmentStart = new DateTime($appointment->appointmentStartDate . ' ' . $appointment->appointmentStartTime, wp_timezone());
            $appointmentEnd = new DateTime($appointment->appointmentEndDate . ' ' . $appointment->appointmentEndTime, wp_timezone());

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
     * @param DateTime $currentTime Current time for future validation
     * @return array Generated slots
     */
    private function generateSlotsFromChunk(array $chunk, $session, DateTime $currentTime): array
    {
        $slots = [];
        $slotDuration = $this->serviceDurationSum ?: $session->timeSlot;

        $slotStart = clone $chunk['start'];
        while ($slotStart < $chunk['end']) {
            $slotEnd = clone $slotStart;
            $slotEnd->add(new DateInterval('PT' . $slotDuration . 'M'));

            // Check if slot fits within chunk
            if ($slotEnd <= $chunk['end']) {
                // Generate slot based on only_available_slots flag
                // Note: Booked appointment times never reach here because splitSessionIntoChunks()
                // creates time chunks that exclude existing appointments
                // 
                // The 'only_available_slots' flag controls whether to include PAST slots:
                // - If true: Only return future slots (slotStart > currentTime)
                // - If false: Return all slots (future and past), but mark past ones with available=false
                if (!$this->onlyAvailableSlots || $slotStart > $currentTime) { 
                    $slots[] = [
                        'time' => $this->formatTime($slotStart),
                        'available' => $slotStart > $currentTime, // True only if slot is in the future
                        'datetime' => $slotStart->format('Y-m-d H:i:s'),
                        'session_id' => $session->id ?? null
                    ];
                }
            }

            // Move to next slot
            $slotStart->add(new DateInterval('PT' . $slotDuration . 'M'));
        }

        return $slots;
    }

    /**
     * Generate all possible slots for a session and mark booked ones as unavailable
     * Used when only_available_slots is false to get accurate total slot counts
     * 
     * @param object $session Session data
     * @param DateTime $currentTime Current time for future validation
     * @return array Generated slots with booking status
     */
    private function generateAllSlotsWithBookingStatus($session, DateTime $currentTime): array
    {
        $slots = [];
        $slotDuration = $this->serviceDurationSum ?: $session->timeSlot;
        
        $slotStart = clone $session->start_datetime;
        $sessionEnd = clone $session->end_datetime;
        
        while ($slotStart < $sessionEnd) {
            $slotEnd = clone $slotStart;
            $slotEnd->add(new DateInterval('PT' . $slotDuration . 'M'));
            
            // Check if slot fits within session
            if ($slotEnd <= $sessionEnd) {
                // Check if this slot overlaps with any booked appointment
                $isBooked = $this->isSlotBooked($slotStart, $slotEnd);
                
                // Determine if slot is available:
                // - Must be in the future (slotStart > currentTime)
                // - Must not be booked
                $isAvailable = ($slotStart > $currentTime) && !$isBooked;
                
                $slots[] = [
                    'time' => $this->formatTime($slotStart),
                    'available' => $isAvailable,
                    'booked' => $isBooked,
                    'datetime' => $slotStart->format('Y-m-d H:i:s'),
                    'session_id' => $session->id ?? null
                ];
            }
            
            // Move to next slot
            $slotStart->add(new DateInterval('PT' . $slotDuration . 'M'));
        }
        
        return $slots;
    }
    
    /**
     * Check if a specific time slot overlaps with any booked appointment
     * 
     * @param DateTime $slotStart Slot start time
     * @param DateTime $slotEnd Slot end time
     * @return bool True if slot is booked
     */
    private function isSlotBooked(DateTime $slotStart, DateTime $slotEnd): bool
    {
        foreach ($this->appointments as $appointment) {
            $appointment = (object) $appointment;
            
            // Skip the appointment we're editing (if any)
            if ($this->appointmentIdToSkip && isset($appointment->id) && $appointment->id == $this->appointmentIdToSkip) {
                continue;
            }
            
            $appointmentStart = new DateTime($appointment->appointmentStartDate . ' ' . $appointment->appointmentStartTime, wp_timezone());
            $appointmentEnd = new DateTime($appointment->appointmentEndDate . ' ' . $appointment->appointmentEndTime, wp_timezone());
            
            // Check for overlap
            if ($slotStart < $appointmentEnd && $slotEnd > $appointmentStart) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Format time using kcGetFormatedTime function or fallback
     * 
     * @param DateTime $dateTime DateTime object
     * @return string Formatted time
     */
    private function formatTime(DateTime $dateTime): string
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
     * @return DateTime Current time
     */
    protected function getCurrentTime(): DateTime
    {
        return new DateTime();
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
            'slots_count' => array_sum(array_map('count', $this->slots))
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
        $slotStart = new DateTime($slotDateTime, wp_timezone());
        $slotEnd = clone $slotStart;
        $slotEnd->add(new DateInterval('PT' . $this->serviceDurationSum . 'M'));

        // Check against existing appointments
        foreach ($this->appointments as $appointment) {
            $appointment = (object) $appointment;
            $appointmentStart = new DateTime($appointment->appointmentStartDate . ' ' . $appointment->appointmentStartTime,  wp_timezone());
            $appointmentEnd = new DateTime($appointment->appointmentEndDate . ' ' . $appointment->appointmentEndTime,  wp_timezone());

            // Check for overlap
            if ($slotStart < $appointmentEnd && $slotEnd > $appointmentStart) {
                return false;
            }
        }

        return true;
    }
}

