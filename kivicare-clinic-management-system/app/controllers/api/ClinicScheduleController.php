<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\baseClasses\KCErrorLogger;
use App\models\KCAppointment;
use App\models\KCClinicSchedule;
use App\models\KCClinic;
use App\models\KCClinicSession;
use App\models\KCDoctor;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class ClinicScheduleController
 * 
 * API Controller for Clinic Schedule endpoints
 */
class ClinicScheduleController extends KCBaseController
{
    protected $route = 'clinic-schedules';

    public function registerRoutes()
    {
        // List all schedules
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getSchedules'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'module_type' => [
                    'description' => 'Module Type (doctor or clinic)',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'required' => false,
                ],
                'module_id' => [
                    'description' => 'Module ID',
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'required' => false,
                ],
                'start_date' => [
                    'description' => 'Filter by start date (YYYY-MM-DD)',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'required' => false,
                ],
                'end_date' => [
                    'description' => 'Filter by end date (YYYY-MM-DD)',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'required' => false,
                ],
                'status' => [
                    'description' => 'Status filter',
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'required' => false,
                ],
            ],
        ]);

        // Get unavailable schedule
        $this->registerRoute('/' . $this->route . '/get-unavailable-schedule', [
            'methods' => 'GET',
            'callback' => [$this, 'getUnavailableSchedule'],
            'permission_callback' => '__return_true',
            'args' => [
                'clinic_id' => [
                    'description' => 'Clinic ID',
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'required' => false,
                ],
                'doctor_id' => [
                    'description' => 'Doctor ID',
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'required' => false,
                ],
            ],
        ]);

        // Get single schedule
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getSchedule'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'id' => [
                    'description' => 'Schedule ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Create schedule
        $this->registerRoute('/' . $this->route, [
            'methods' => 'POST',
            'callback' => [$this, 'createSchedule'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'startDate' => [
                    'description' => 'Start Date (YYYY-MM-DD)',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'endDate' => [
                    'description' => 'End Date (YYYY-MM-DD)',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'moduleType' => [
                    'description' => 'Module Type (doctor or clinic)',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'moduleId' => [
                    'description' => 'Module ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'description' => [
                    'description' => 'Schedule Description',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'status' => [
                    'description' => 'Status',
                    'type' => 'integer',
                    'required' => false,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Update schedule
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateSchedule'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'id' => [
                    'description' => 'Schedule ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'startDate' => [
                    'description' => 'Start Date (YYYY-MM-DD)',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'endDate' => [
                    'description' => 'End Date (YYYY-MM-DD)',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'moduleType' => [
                    'description' => 'Module Type (doctor or clinic)',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'moduleId' => [
                    'description' => 'Module ID',
                    'type' => 'integer',
                    'required' => false,
                    'sanitize_callback' => 'absint',
                ],
                'description' => [
                    'description' => 'Schedule Description',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'status' => [
                    'description' => 'Status',
                    'type' => 'integer',
                    'required' => false,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Delete schedule
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteSchedule'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'id' => [
                    'description' => 'Schedule ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Get schedules by module (convenience endpoint)
        $this->registerRoute('/' . $this->route . '/module/(?P<module_type>doctor|clinic)/(?P<module_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getSchedulesByModule'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'module_type' => [
                    'description' => 'Module Type',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'module_id' => [
                    'description' => 'Module ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Get active schedules for date range
        $this->registerRoute('/' . $this->route . '/active', [
            'methods' => 'GET',
            'callback' => [$this, 'getActiveSchedules'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'start_date' => [
                    'description' => 'Start Date (YYYY-MM-DD)',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'end_date' => [
                    'description' => 'End Date (YYYY-MM-DD)',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public function checkPermission($request)
    {
        // Adjust as needed for your roles/capabilities
        return $this->checkCapability('read');
    }

    /**
     * Generate an array of dates between two dates (inclusive).
     *
     * @param string $start_date Format: Y-m-d.
     * @param string $end_date   Format: Y-m-d.
     * @return array Array of dates from start to end date.
     */
    private function kc_generate_date_range( $start_date, $end_date ) {
        $dates = array();

        try {
            $start = new \DateTime( $start_date );
            $end   = new \DateTime( $end_date );
            $end->modify( '+1 day' );

            $interval = new \DateInterval( 'P1D' );
            $period   = new \DatePeriod( $start, $interval, $end );

            foreach ( $period as $date ) {
                $dates[] = $date->format( 'Y-m-d' );
            }
        } catch ( \Exception $e ) {
            KCErrorLogger::instance()->error( $e->getMessage() );
        }

        return $dates;
    }

    public function getUnavailableSchedule(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();
        $unavailable_schedule = [];

        $current_user_role = $this->kcbase->getLoginUserRole();
        if($current_user_role === $this->kcbase->getDoctorRole()){
            $params['doctor_id'] = get_current_user_id() ;
        }
        $clinicId = $params['clinic_id'];
        if ($current_user_role === $this->kcbase->getClinicAdminRole()) {
            $clinicId = KCClinic::getClinicIdOfClinicAdmin();
        } elseif ($current_user_role === $this->kcbase->getReceptionistRole()) {
            $clinicId = KCClinic::getClinicIdOfReceptionist();
        }

        // get doctor session days
        $doctor_session = KCClinicSession::query()
                ->setTableAlias('kc_doctor_sessions')
                ->select([
                    'kc_doctor_sessions.day',
                ])
                ->where('clinic_id',$clinicId)
                ->where('doctor_id',$params['doctor_id'])
                ->get()->pluck('day')->toArray();
        $days = ['0' => 'sun', '1' => 'mon', '2' =>'tue', '3' => 'wed', '4' => 'thu', '5' => 'fri', '6' => 'sat'];
        if(count($doctor_session) > 0){
            // get unavilable  days
            $doctor_session = array_diff(array_values($days),$doctor_session);
            //get key of unavilable days
            $doctor_session = array_map(function ($v) use ($days){
                // converted in string because in flatpickr only accept string
                return (string)array_search($v,$days);
            },$doctor_session);
            $doctor_session = array_values($doctor_session);
        }
        else{
            //get all days keys
            $doctor_session = array_keys($days);
        }

        $unavailable_schedule['off_days'] = $doctor_session;
        
        // Now get specific day off and full booked day date.
        $leaves = KCClinicSchedule::query()
            ->setTableAlias('clinic_schedule')
            ->select(['clinic_schedule.*'])
            ->where('status', 1);

        $leaves->where(function($q) use ($params, $clinicId) {
            $q->where(function($sq) use ($params) {
                $sq->where('module_type','doctor')
                ->where('module_id',$params['doctor_id']);
            })->orWhere(function($sq) use ($clinicId){
                $sq->where('module_type','clinic')
                ->where('module_id',$clinicId);
            });
        });

        $leaves = $leaves->get();
        $all_leaves = [];
        $doctor_holidays = [];
        $clinic_holidays = [];
        foreach ($leaves as $leave) {
            // Exclude time-specific holidays from the calendar disabled list
            // because they only block partial hours, not the entire day.
            if ((bool) ($leave->timeSpecific ?? false)) {
                continue;
            }

            $selectionMode = $leave->selectionMode ?? 'range';
            if ($selectionMode === 'multiple') {
                $selectedDates = $leave->selectedDates ? json_decode($leave->selectedDates, true) : [];
                if (is_array($selectedDates)) {
                    $all_leaves = array_merge($all_leaves, $selectedDates);
                    if (($leave->moduleType ?? $leave->module_type ?? null) === 'clinic') {
                        $clinic_holidays = array_merge($clinic_holidays, $selectedDates);
                    } else {
                        $doctor_holidays = array_merge($doctor_holidays, $selectedDates);
                    }
                }
            } else {
                // 'single' or 'range'
                $dates = $this->kc_generate_date_range($leave->startDate, $leave->endDate);
                $all_leaves = array_merge($all_leaves, $dates);
                if (($leave->moduleType ?? $leave->module_type ?? null) === 'clinic') {
                    $clinic_holidays = array_merge($clinic_holidays, $dates);
                } else {
                    $doctor_holidays = array_merge($doctor_holidays, $dates);
                }
            }
        }

        $clinic_sessions = KCClinicSession::query()
            ->select(['day', 'start_time', 'end_time', 'time_slot'])
            ->where('clinicId',$clinicId)
            ->where('doctorId',$params['doctor_id'])
            ->get();
        $slots_per_day = [];
        foreach ($clinic_sessions as $session) {
            $day = $session->day;
            if (!isset($slots_per_day[$day])) {
                $slots_per_day[$day] = 0;
            }
            // Calculate number of slots in this session
            $start = strtotime("2000-01-01 " . $session->startTime);
            $end = strtotime("2000-01-01 " . $session->endTime);
            $duration = $end - $start;
            $slots_in_session = $duration / ($session->timeSlot * 60);
            $slots_per_day[$day] += $slots_in_session;
        }
        // Get appointments and organize them by date
        $appointments = KCAppointment::query()
            ->select(['appointment_start_date','appointment_start_time','appointment_end_time'])
            ->where('doctorId',$params['doctor_id'])
            ->where('clinicId',$clinicId)
            ->where('status','!=',0)
            ->whereRaw('appointment_start_date >= CURDATE()')
            ->get();
        $appointments_per_date = [];
        $fully_booked_dates = [];

        foreach ($appointments as $appointment) {
            $date = $appointment->appointmentStartDate;
            $day_name = strtolower(gmdate('D', strtotime($date))); // Get day name (mon, tue, etc.)

            if (!isset($appointments_per_date[$date])) {
                $appointments_per_date[$date] = 0;
            }

            // Count this appointment
            $appointments_per_date[$date]++;

            // Check if this date is now fully booked
            if (
                isset($slots_per_day[$day_name]) &&
                $appointments_per_date[$date] >= $slots_per_day[$day_name]
            ) {
                $fully_booked_dates[] = $date;
            }
        }

        $all_leaves = array_merge( $all_leaves, $fully_booked_dates );

        $unavailable_schedule['holidays'] = array_unique($all_leaves);
        $unavailable_schedule['clinic_holidays'] = array_values(array_unique($clinic_holidays));
        $unavailable_schedule['doctor_holidays'] = array_values(array_unique($doctor_holidays));

        return $this->response($unavailable_schedule, __('Clinic schedules retrieved', 'kivicare-clinic-management-system'));
    }

    public function getSchedules(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();
        $query = KCClinicSchedule::query();

        if (!empty($params['module_type'])) {
            $query->where('module_type', $params['module_type']);
        }
        if (!empty($params['module_id'])) {
            $query->where('module_id', $params['module_id']);
        }
        if (!empty($params['start_date'])) {
            $query->where('start_date', '>=', $params['start_date']);
        }
        if (!empty($params['end_date'])) {
            $query->where('end_date', '<=', $params['end_date']);
        }
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        $schedules = $query->first();

        return $this->response($schedules, __('Clinic schedules retrieved', 'kivicare-clinic-management-system'));
    }

    public function getSchedule(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $schedule = KCClinicSchedule::find($id);

        if (!$schedule) {
            return $this->response(null, __('Schedule not found', 'kivicare-clinic-management-system'), false, 404);
        }

        // Enrich with module data
        $schedule->module = $schedule->getModule();

        return $this->response($schedule, __('Clinic schedule retrieved', 'kivicare-clinic-management-system'));
    }

    public function createSchedule(WP_REST_Request $request): WP_REST_Response
    {
        $data = $request->get_params();
        
        // Validate module exists
        if (!$this->validateModule($data['moduleType'], $data['moduleId'])) {
            return $this->response(null, __('Invalid module type or ID', 'kivicare-clinic-management-system'), false, 400);
        }

        // Validate date range
        if (!$this->validateDateRange($data['startDate'], $data['endDate'])) {
            return $this->response(null, __('Invalid date range', 'kivicare-clinic-management-system'), false, 400);
        }

        $schedule = KCClinicSchedule::create($data);

        if (!$schedule) {
            return $this->response(null, __('Failed to create schedule', 'kivicare-clinic-management-system'), false, 500);
        }

        // Enrich with module data
        $schedule->module = $schedule->getModule();

        return $this->response($schedule, __('Clinic schedule created', 'kivicare-clinic-management-system'), true, 201);
    }

    public function updateSchedule(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $data = $request->get_params();

        $schedule = KCClinicSchedule::find($id);
        if (!$schedule) {
            return $this->response(null, __('Schedule not found', 'kivicare-clinic-management-system'), false, 404);
        }

        // Validate module if provided
        if (isset($data['moduleType']) && isset($data['moduleId'])) {
            if (!$this->validateModule($data['moduleType'], $data['moduleId'])) {
                return $this->response(null, __('Invalid module type or ID', 'kivicare-clinic-management-system'), false, 400);
            }
        }

        // Validate date range if provided
        $startDate = $data['startDate'] ?? $schedule->startDate;
        $endDate = $data['endDate'] ?? $schedule->endDate;
        if (!$this->validateDateRange($startDate, $endDate)) {
            return $this->response(null, __('Invalid date range', 'kivicare-clinic-management-system'), false, 400);
        }

        $schedule->fill($data);
        $result = $schedule->save();

        if (!$result) {
            return $this->response(null, __('Failed to update schedule', 'kivicare-clinic-management-system'), false, 500);
        }

        // Enrich with module data
        $schedule->module = $schedule->getModule();

        return $this->response($schedule, __('Clinic schedule updated', 'kivicare-clinic-management-system'));
    }

    public function deleteSchedule(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $schedule = KCClinicSchedule::find($id);

        if (!$schedule) {
            return $this->response(null, __('Schedule not found', 'kivicare-clinic-management-system'), false, 404);
        }

        $result = $schedule->delete();

        if (!$result) {
            return $this->response(null, __('Failed to delete schedule', 'kivicare-clinic-management-system'), false, 500);
        }

        return $this->response(['id' => $id], __('Clinic schedule deleted', 'kivicare-clinic-management-system'));
    }

    public function getSchedulesByModule(WP_REST_Request $request): WP_REST_Response
    {
        $moduleType = $request->get_param('module_type');
        $moduleId = $request->get_param('module_id');

        // Validate module exists
        if (!$this->validateModule($moduleType, $moduleId)) {
            return $this->response(null, __('Invalid module type or ID', 'kivicare-clinic-management-system'), false, 400);
        }

        $schedules = KCClinicSchedule::getByModule($moduleType, $moduleId);

        // Enrich with module data
        foreach ($schedules as $schedule) {
            $schedule->module = $schedule->getModule();
        }

        return $this->response($schedules, __('Module schedules retrieved', 'kivicare-clinic-management-system'));
    }

    public function getActiveSchedules(WP_REST_Request $request): WP_REST_Response
    {
        $startDate = $request->get_param('start_date');
        $endDate = $request->get_param('end_date');

        if (!$this->validateDateRange($startDate, $endDate)) {
            return $this->response(null, __('Invalid date range', 'kivicare-clinic-management-system'), false, 400);
        }

        $schedules = KCClinicSchedule::getActiveSchedules($startDate, $endDate);

        // Enrich with module data
        foreach ($schedules as $schedule) {
            $schedule->module = $schedule->getModule();
        }

        return $this->response($schedules, __('Active schedules retrieved', 'kivicare-clinic-management-system'));
    }

    /**
     * Validate if the module exists
     */
    private function validateModule($moduleType, $moduleId): bool
    {
        switch ($moduleType) {
            case 'clinic':
                return KCClinic::find($moduleId) !== null;
            case 'doctor':
                return KCDoctor::find($moduleId) !== null;
            default:
                return false;
        }
    }

    /**
     * Validate date range
     */
    private function validateDateRange($startDate, $endDate): bool
    {
        $start = \DateTime::createFromFormat('Y-m-d', $startDate);
        $end = \DateTime::createFromFormat('Y-m-d', $endDate);

        if (!$start || !$end) {
            return false;
        }

        return $start <= $end;
    }
}