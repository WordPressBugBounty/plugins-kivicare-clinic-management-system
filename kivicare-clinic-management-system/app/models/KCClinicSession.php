<?php

namespace App\models;

use App\baseClasses\KCBaseModel;
use App\baseClasses\KCErrorLogger;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

class KCClinicSession extends KCBaseModel
{

    /**
     * Define table structure and properties
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_clinic_sessions',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'clinicId' => [
                    'column' => 'clinic_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                ],
                'doctorId' => [
                    'column' => 'doctor_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                ],
                'day' => [
                    'column' => 'day',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'startTime' => [
                    'column' => 'start_time',
                    'type' => 'time',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'endTime' => [
                    'column' => 'end_time',
                    'type' => 'time',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'timeSlot' => [
                    'column' => 'time_slot',
                    'type' => 'int',
                    'nullable' => true,
                    'default' => 5,
                    'sanitizers' => ['intval'],
                ],
                'parentId' => [
                    'column' => 'parent_id',
                    'type' => 'bigint',
                    'nullable' => true,
                    'sanitizers' => ['intval'],
                ],
                'createdAt' => [
                    'column' => 'created_at',
                    'type' => 'datetime',
                    'nullable' => true,
                ],
            ],
            'timestamps' => false,
            'soft_deletes' => false,
        ];
    }

    public static function updateDoctorSession(array $params, $parent_session_id)
    {
        global $wpdb;

        try {
            // Validate required parameters
            if (empty($params['doctor_id']) || empty($params['clinic_id']) || empty($params['days'])) {
                return new \WP_Error(
                    'missing_parameters',
                    __('Missing required parameters: doctor_id, clinic_id, or days', 'kivicare-clinic-management-system'),
                    ['status' => 400]
                );
            }

            $doctor_id = (int) $params['doctor_id'];
            $clinic_id = (int) $params['clinic_id'];
            $time_slot = isset($params['time_slot']) ? static::convertTimeToMinutes($params['time_slot']) : 30;
            $days = $params['days'];

            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Get all existing sessions for this doctor-clinic combination
            $existing_sessions = static::query()
                ->where('doctorId', $doctor_id)
                ->where('clinicId', $clinic_id)
                ->get()
                ->toArray();

            // Find all enabled days
            $enabled_days = array_filter($days, function ($day) {
                return !empty($day['enabled']);
            });

            if (!empty($enabled_days)) {
                // Process each enabled day
                foreach ($enabled_days as $day) {
                    $day_id = $day['id'];
                    $breaks = $day['breaks'] ?? [];

                    // Find existing sessions for this day
                    $day_sessions = array_filter($existing_sessions, function ($session) use ($day_id) {
                        return $session->day === $day_id;
                    });

                    // If there are breaks, create split sessions
                    if (!empty($breaks)) {
                        // Delete existing sessions for this day
                        foreach ($day_sessions as $session) {
                            $session->delete();
                        }

                        // Create new split sessions
                        $split_sessions = static::createSplitSessions(
                            $doctor_id,
                            $clinic_id,
                            $day,
                            $time_slot,
                            $day_id === reset($enabled_days)['id'] ? null : $parent_session_id
                        );

                        if (is_wp_error($split_sessions)) {
                            throw new \Exception($split_sessions->get_error_message());
                        }
                    } else {
                        // No breaks - update or create single session
                        $session_data = [
                            'doctorId' => $doctor_id,
                            'clinicId' => $clinic_id,
                            'day' => $day_id,
                            'startTime' => static::formatTimeForDatabase($day['main_session']['start']),
                            'endTime' => static::formatTimeForDatabase($day['main_session']['end']),
                            'timeSlot' => $time_slot,
                            'parentId' => $day_id === reset($enabled_days)['id'] ? null : $parent_session_id,
                            'createdAt' => current_time('mysql')
                        ];

                        // Update existing session or create new one
                        $existing_session = reset($day_sessions);
                        if ($existing_session) {
                            foreach ($session_data as $key => $value) {
                                $existing_session->$key = $value;
                            }
                            $existing_session->save();
                        } else {
                            static::create($session_data);
                        }
                    }
                }

                // Remove sessions for days that are no longer enabled
                $enabled_day_ids = array_map(function ($day) {
                    return $day['id'];
                }, $enabled_days);

                foreach ($existing_sessions as $session) {
                    if (!in_array($session->day, $enabled_day_ids)) {
                        $session->delete();
                    }
                }
            }

            $wpdb->query('COMMIT');

            return [
                'success' => true,
                'message' => __('Doctor session updated successfully', 'kivicare-clinic-management-system'),
                'data' => [
                    'parent_session_id' => $parent_session_id,
                    'doctor_id' => $doctor_id,
                    'clinic_id' => $clinic_id,
                ]
            ];

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            KCErrorLogger::instance()->error('KCClinicSession::updateDoctorSession Error: ' . $e->getMessage());

            return new \WP_Error(
                'session_update_failed',
                __('Error updating doctor session: ', 'kivicare-clinic-management-system') . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Create doctor sessions for weekly schedule
     * 
     * @param array $params Request parameters containing doctor_id, clinic_id, time_slot, and days
     * @return array|WP_Error Response with success status and data
     */
    public static function createDoctorSession(array $params)
    {
        global $wpdb;

        try {
            // Validate required parameters
            if (empty($params['doctor_id']) || empty($params['clinic_id']) || empty($params['days'])) {
                return new \WP_Error(
                    'missing_parameters',
                    __('Missing required parameters: doctor_id, clinic_id, or days', 'kivicare-clinic-management-system'),
                    ['status' => 400]
                );
            }

            $doctor_id = (int) $params['doctor_id'];
            $clinic_id = (int) $params['clinic_id'];
            $time_slot = isset($params['time_slot']) ? static::convertTimeToMinutes($params['time_slot']) : 30;
            $days = $params['days'];

            // Start transaction
            $wpdb->query('START TRANSACTION');

            $inserted_records = [];
            $enabled_days = [];

            // Collect all enabled days
            foreach ($days as $day) {
                if (isset($day['enabled']) && $day['enabled'] === true) {
                    $enabled_days[] = $day;
                }
            }

            // Process each enabled day
            foreach ($enabled_days as $day) {
                $day_id = sanitize_text_field($day['id']); // e.g., 'monday'
                $main_session = $day['main_session'] ?? null;

                // Skip if no main session defined for an enabled day
                if (!$main_session || empty($main_session['start']) || empty($main_session['end'])) {
                    KCErrorLogger::instance()->error("Skipping day {$day_id} for doctor {$doctor_id}, clinic {$clinic_id}: Missing or invalid main session.");
                    continue;
                }

                $breaks = $day['breaks'] ?? [];

                // Logic Based on Presence of Breaks for THIS DAY
                $day_sessions = [];

                if (empty($breaks)) {
                    $session_data = [
                        'doctorId' => $doctor_id,
                        'clinicId' => $clinic_id,
                        'day' => $day_id,
                        'startTime' => static::formatTimeForDatabase($main_session['start']), // Use helper from your class
                        'endTime' => static::formatTimeForDatabase($main_session['end']),   // Use helper from your class
                        'timeSlot' => $time_slot,
                        'parentId' => null, // Primary session for the day
                        'createdAt' => current_time('mysql')
                    ];

                    $session_id = static::create($session_data); // Use the existing create method

                    if (is_wp_error($session_id)) {
                        // Handle potential error from static::create
                        KCErrorLogger::instance()->error("Failed to create single session for day {$day_id}: " . $session_id->get_error_message());
                        // Depending on requirements, you might want to throw an exception or continue
                        $day_sessions = $session_id; // Assign error to be caught later
                    } else {
                        $day_sessions = [array_merge($session_data, ['id' => $session_id, 'type' => 'primary_session'])];
                    }
                } else {
                    // With Breaks for this day: Create split sessions.
                    // Pass NULL initially; the first session will become the parent.
                    $day_sessions = static::createSplitSessions($doctor_id, $clinic_id, $day, $time_slot, null);
                }

                if (is_wp_error($day_sessions)) {
                    throw new \Exception($day_sessions->get_error_message());
                }

                $inserted_records = array_merge($inserted_records, $day_sessions);
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            return [
                'success' => true,
                'message' => __('Doctor sessions created successfully based on old logic', 'kivicare-clinic-management-system'),
                'data' => [
                    'doctor_id' => $doctor_id,
                    'clinic_id' => $clinic_id,
                    'time_slot' => $time_slot,
                    'total_sessions' => count($inserted_records),
                    'sessions' => $inserted_records
                ]
            ];

        } catch (\Exception $e) {
            // Rollback transaction on error
            $wpdb->query('ROLLBACK');
            KCErrorLogger::instance()->error('KCClinicSession::createDoctorSession (Refactored) Error: ' . $e->getMessage());

            return new \WP_Error(
                'session_creation_failed',
                __('Error creating doctor sessions (refactored): ', 'kivicare-clinic-management-system') . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Create split sessions for a day based on breaks
     * 
     * @param int $doctor_id
     * @param int $clinic_id
     * @param array $day
     * @param int $time_slot
     * @param int $parent_session_id
     * @return array
     */
    private static function createSplitSessions($doctor_id, $clinic_id, $day, $time_slot, $parent_session_id = null): array
    {
        $sessions = [];
        $day_id = sanitize_text_field($day['id']);
        $main_session = $day['main_session'];

        // Guard clause for missing main session
        if (empty($main_session['start']) || empty($main_session['end'])) {
            KCErrorLogger::instance()->error("Cannot create split sessions for day {$day_id}: Missing main session times.");
            return $sessions; // Return empty array
        }

        $main_start = $main_session['start'];
        $main_end = $main_session['end'];
        $breaks = $day['breaks'] ?? [];

        // Convert main session times to minutes for calculation
        $main_start_minutes = static::timeToMinutes($main_start);
        $main_end_minutes = static::timeToMinutes($main_end);

        // 1. Process and sort breaks
        $processed_breaks = [];
        foreach ($breaks as $break) {
            if (!empty($break['start']) && !empty($break['end'])) {
                $break_start = static::timeToMinutes($break['start']);
                $break_end = static::timeToMinutes($break['end']);

                // Only include breaks that are fully within the main session
                if ($break_start >= $main_start_minutes && $break_end <= $main_end_minutes && $break_start < $break_end) {
                    $processed_breaks[] = [
                        'start' => $break_start,
                        'end' => $break_end,
                    ];
                } else {
                    KCErrorLogger::instance()->error("Skipping invalid break for day {$day_id}: Start: {$break['start']}, End: {$break['end']}. Main Session: {$main_start} - {$main_end}");
                }
            }
        }

        // Sort breaks by start time
        usort($processed_breaks, function ($a, $b) {
            return $a['start'] - $b['start'];
        });

        // 2. Identify and create session slots between breaks
        $current_start_minutes = $main_start_minutes;
        $first_session_created = false; // Flag to track if the first session is created
        $parent_session_id_for_day = null; // To store the ID of the first session

        foreach ($processed_breaks as $break) {
            // Create session before the current break (if there's time)
            if ($current_start_minutes < $break['start']) {
                $session_data = [
                    'doctorId' => $doctor_id,
                    'clinicId' => $clinic_id,
                    'day' => $day_id,
                    'startTime' => static::formatTimeForDatabase(static::minutesToTime($current_start_minutes)),
                    'endTime' => static::formatTimeForDatabase(static::minutesToTime($break['start'])),
                    'timeSlot' => $time_slot,
                    'parentId' => $parent_session_id_for_day, // Use the first session's ID as parent
                    'createdAt' => current_time('mysql')
                ];

                $session_id = static::create($session_data);
                if (!is_wp_error($session_id)) {
                    $sessions[] = array_merge($session_data, ['id' => $session_id, 'type' => 'split_session']);

                    // Set the parent ID for subsequent sessions if this is the first session
                    if (!$first_session_created) {
                        $parent_session_id_for_day = $session_id;
                        $first_session_created = true;
                    }
                } else {
                    KCErrorLogger::instance()->error("Failed to create split session (before break) for day {$day_id}: " . $session_id->get_error_message());
                }
            }

            // Move the pointer for the next potential session start to after the current break
            $current_start_minutes = $break['end'];
        }

        // 3. Create the final session slot after the last break (if time remains)
        if ($current_start_minutes < $main_end_minutes) {
            $session_data = [
                'doctorId' => $doctor_id,
                'clinicId' => $clinic_id,
                'day' => $day_id,
                'startTime' => static::formatTimeForDatabase(static::minutesToTime($current_start_minutes)),
                'endTime' => static::formatTimeForDatabase($main_end), // Use original formatted end time
                'timeSlot' => $time_slot,
                'parentId' => $parent_session_id_for_day, // Use the first session's ID as parent
                'createdAt' => current_time('mysql')
            ];

            $session_id = static::create($session_data);
            if (!is_wp_error($session_id)) {
                $sessions[] = array_merge($session_data, ['id' => $session_id, 'type' => 'split_session']);
            } else {
                KCErrorLogger::instance()->error("Failed to create final split session (after last break) for day {$day_id}: " . $session_id->get_error_message());
            }
        }

        return $sessions;
    }

    /**
     * Convert time string (HH:MM) to minutes since midnight
     */
    private static function timeToMinutes($time)
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches)) {
            return (int) $matches[1] * 60 + (int) $matches[2];
        }
        return 0;
    }

    /**
     * Convert minutes since midnight to time string (HH:MM)
     */
    private static function minutesToTime($minutes)
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * Convert time slot string (HH:MM) to minutes integer
     */
    private static function convertTimeToMinutes($timeString)
    {
        if (is_numeric($timeString)) {
            return (int) $timeString;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeString, $matches)) {
            return (int) $matches[1] * 60 + (int) $matches[2];
        }

        return 30; // Default to 30 minutes
    }

    /**
     * Format time for database storage (ensure HH:MM:SS format)
     */
    private static function formatTimeForDatabase($time)
    {
        if (empty($time)) {
            return null;
        }

        // If already in HH:MM:SS format, return as is
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return $time;
        }

        // If in HH:MM format, add seconds
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        return $time;
    }

    /**
     * Delete existing sessions for doctor-clinic combination
     */
    private static function deleteExistingSessions($doctor_id, $clinic_id)
    {
        return static::query()
            ->where('doctorId', $doctor_id)
            ->where('clinicId', $clinic_id)
            ->delete();
    }

    /**
     * Get sessions by doctor and clinic
     */
    public static function getSessionsByDoctorClinic($doctor_id, $clinic_id)
    {
        return static::query()
            ->where('doctorId', $doctor_id)
            ->where('clinicId', $clinic_id)
            ->orderBy('parentId', 'ASC')
            ->orderBy('day', 'ASC')
            ->get();
    }

    /**
     * Update session availability
     */
    public function updateAvailability($is_available)
    {
        $this->isAvailable = (bool) $is_available;
        return $this->save();
    }
    /**
     * Get the clinic this session belongs to
     */
    public function getClinic()
    {
        return KCClinic::find($this->clinicId);
    }

    /**
     * Get the doctor this session belongs to
     */
    public function getDoctor()
    {
        return KCDoctor::find($this->doctorId);
    }
}
