<?php

namespace App\models;

use App\baseClasses\KCBaseModel;
use KCGMApp\models\KCGMAppointmentGoogleMeetMapping;
use KCTApp\models\KCTAppointmentZoomMapping;
use App\baseClasses\KCBase;
use App\models\KCClinic;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class KCAppointment
 * 
 * @property int $id
 * @property string|null $appointment_start_date
 * @property string|null $appointment_start_time
 * @property string|null $appointment_end_date
 * @property string|null $appointment_end_time
 * @property string|null $visit_type
 * @property int $clinic_id
 * @property int $doctor_id
 * @property int $patient_id
 * @property string|null $description
 * @property int|null $status
 * @property string|null $created_at
 * @property string|null $appointment_report
 * 
 * @property KCClinic $clinic
 * @property KCAppointmentServiceMapping[] $services
 * @property KCGMAppointmentGoogleMeetMapping|null $googleMeet
 */
class KCAppointment extends KCBaseModel
{
    // Appointment status constants
    const STATUS_CANCELLED = 0;
    const STATUS_BOOKED = 1;
    const STATUS_PENDING = 2;
    const STATUS_CHECK_OUT = 3;
    const STATUS_CHECK_IN = 4;
    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_appointments',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'appointmentStartDate' => [
                    'column' => 'appointment_start_date',
                    'type' => 'date',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'appointmentStartTime' => [
                    'column' => 'appointment_start_time',
                    'type' => 'time',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'appointmentEndDate' => [
                    'column' => 'appointment_end_date',
                    'type' => 'date',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'appointmentEndTime' => [
                    'column' => 'appointment_end_time',
                    'type' => 'time',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'visitType' => [
                    'column' => 'visit_type',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'clinicId' => [
                    'column' => 'clinic_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        function ($value) {
                            return $value > 0 ? true : 'Invalid clinic ID';
                        }
                    ],
                ],
                'doctorId' => [
                    'column' => 'doctor_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        function ($value) {
                            return $value > 0 ? true : 'Invalid doctor ID';
                        }
                    ],
                ],
                'patientId' => [
                    'column' => 'patient_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        function ($value) {
                            return $value > 0 ? true : 'Invalid patient ID';
                        }
                    ],
                ],
                'description' => [
                    'column' => 'description',
                    'type' => 'text',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'status' => [
                    'column' => 'status',
                    'type' => 'tinyint',
                    'nullable' => true,
                    'default' => 0,
                    'validators' => [
                        function ($value) {
                            return is_numeric($value) ? true : 'Status must be numeric';
                        }
                    ],
                ],
                'createdAt' => [
                    'column' => 'created_at',
                    'type' => 'datetime',
                    'nullable' => true,
                ],
                'appointmentReport' => [
                    'column' => 'appointment_report',
                    'type' => 'longtext',
                    'nullable' => true,
                ],
            ],
            'timestamps' => false,
            'soft_deletes' => false,
        ];
    }

    /**
     * Get the clinic associated with this appointment
     */
    public function getClinic()
    {
        return KCClinic::find($this->clinicId);
    }

    /**
     * Get the doctor associated with this appointment
     */
    public function getDoctor()
    {
        return KCDoctor::find($this->doctorId);
    }

    /**
     * Get the patient associated with this appointment
     */
    public function getPatient()
    {
        return KCPatient::find($this->patientId);
    }

    /**
     * Get services associated with this appointment
     */
    public function getServices()
    {
        return KCAppointmentServiceMapping::query()
            ->where('appointmentId', $this->id)
            ->get()
            ->map(function ($mapping) {
                return $mapping->getService();
            });
    }

    /**
     * Get Zoom meeting details if available
     */
    public function getZoomMeeting()
    {
        return KCTAppointmentZoomMapping::query()
            ->where('appointmentId', $this->id)
            ->first();
    }

    /**
     * Get Google Meet details if available
     */
    public function getGoogleMeet()
    {
        return KCGMAppointmentGoogleMeetMapping::query()
            ->where('appointmentId', $this->id)
            ->first();
    }

    /**
     * Get appointment count with advanced filtering options
     * 
     * @param array $filters Array of filters to apply
     * @param string|null $user_role User role to filter by (optional)
     * @param int|null $user_id User ID to filter by (optional)
     * @return int
     */
    public static function getCount($filters = [], $user_role = null, $user_id = null)
    {
        $kcbase = KCBase::get_instance();
        // Use provided parameters or get current user info
        if ($user_role === null) {
            $user_role = $kcbase->getLoginUserRole();
        }
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $query = self::query();

        // Apply role-based filtering first
        switch ($user_role) {
            case $kcbase->getDoctorRole():
                $query->where('doctor_id', $user_id);
                break;

            case $kcbase->getPatientRole():
                $query->where('patient_id', $user_id);
                break;

            case $kcbase->getClinicAdminRole():
                $clinic_id = KCClinic::getClinicIdOfClinicAdmin($user_id);
                if ($clinic_id > 0) {
                    $query->where('clinic_id', $clinic_id);
                }
                break;

            case $kcbase->getReceptionistRole():
                $clinic_id = KCClinic::getClinicIdOfReceptionist($user_id);
                if ($clinic_id > 0) {
                    $query->where('clinic_id', $clinic_id);
                }
                break;

            case 'administrator':
                // Administrators can see all appointments
                break;

            default:
                // For unknown roles, return 0
                return 0;
        }

        // Apply additional filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['clinic_id'])) {
            $query->where('clinic_id', $filters['clinic_id']);
        }
        
        if (isset($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        if (isset($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }

        if (isset($filters['date'])) {
            $query->where('appointment_start_date', $filters['date']);
        }

        if (isset($filters['date_range'])) {
            if (isset($filters['date_range']['start_date'])) {
                $query->where('appointment_start_date', '>=', $filters['date_range']['start_date']);
            }
            if (isset($filters['date_range']['end_date'])) {
                $query->where('appointment_start_date', '<=', $filters['date_range']['end_date']);
            }
        }

        // Filter by time frame (today, upcoming, past)
        if (isset($filters['time_frame'])) {
            $currentDate = current_time('Y-m-d');
            $currentTime = current_time('H:i:s');

            switch ($filters['time_frame']) {
                case 'today':
                    $query->where('appointment_start_date', $currentDate);
                    break;

                case 'upcoming':
                    $query->where(function ($q) use ($currentDate, $currentTime) {
                        $q->where(function ($innerQ) use ($currentDate, $currentTime) {
                            // Today's appointments with time later than now
                            $innerQ->where('appointment_start_date', '=', $currentDate)
                                ->where('appointment_start_time', '>', $currentTime);
                        })->orWhere('appointment_start_date', '>', $currentDate);
                    });
                    break;

                case 'past':
                    $query->where(function ($q) use ($currentDate, $currentTime) {
                        $q->where('appointment_start_date', '<', $currentDate)
                            ->orWhere(function ($innerQ) use ($currentDate, $currentTime) {
                                $innerQ->where('appointment_start_date', '=', $currentDate)
                                    ->where('appointment_start_time', '<', $currentTime);
                            });
                    });
                    break;
            }
        }

        return $query->count();
    }

    /**
     * Get count of pending appointments
     * 
     * @param string|null $user_role User role to filter by (optional)
     * @param int|null $user_id User ID to filter by (optional)
     * @return int
     */
    public static function getPendingCount($user_role = null, $user_id = null, $filters = [])
    {
        $filters['status'] = self::STATUS_PENDING;
        return self::getCount($filters, $user_role, $user_id);
    }

    /**
     * Get count of completed/checked-out appointments
     * 
     * @param string|null $user_role User role to filter by (optional)
     * @param int|null $user_id User ID to filter by (optional)
     * @return int
     */
    public static function getCompletedCount($user_role = null, $user_id = null, $filters = [])
    {
        $filters['status'] = self::STATUS_CHECK_OUT;
        return self::getCount($filters, $user_role, $user_id);
    }

    /**
     * Get count of cancelled appointments
     * 
     * @param string|null $user_role User role to filter by (optional)
     * @param int|null $user_id User ID to filter by (optional)
     * @return int
     */
    public static function getCancelledCount($user_role = null, $user_id = null, $filters = [])
    {
        $filters['status'] = self::STATUS_CANCELLED;
        return self::getCount($filters, $user_role, $user_id);
    }

    /**
     * Get count of today's appointments
     * 
     * @param string|null $user_role User role to filter by (optional)
     * @param int|null $user_id User ID to filter by (optional)
     * @return int
     */
    public static function getTodayCount($user_role = null, $user_id = null)
    {
        return self::getCount(['time_frame' => 'today'], $user_role, $user_id);
    }

    /**
     * Get count of upcoming appointments
     * 
     * @param string|null $user_role User role to filter by (optional)
     * @param int|null $user_id User ID to filter by (optional)
     * @return int
     */
    public static function getUpcomingCount($user_role = null, $user_id = null)
    {
        return self::getCount([
            'time_frame' => 'upcoming',
            'status' => self::STATUS_BOOKED // Only count booked appointments
        ], $user_role, $user_id);
    }
}
