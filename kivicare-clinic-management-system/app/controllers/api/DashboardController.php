<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use Exception;
use App\baseClasses\KCBase;
use App\models\KCAppointment;
use App\models\KCClinic;
use App\models\KCDoctor;
use App\models\KCPatient;
use App\models\KCService;
use App\models\KCAppointmentServiceMapping;
use App\models\KCPaymentsAppointmentMapping;
use App\models\KCUser;
use App\models\KCPatientEncounter;
use App\models\KCServiceDoctorMapping;
use App\models\KCDoctorClinicMapping;
use App\models\KCBill;
use App\models\KCBillItem;
use App\baseClasses\KCErrorLogger;
use Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class DashboardController
 * 
 * API Controller for Dashboard-related endpoints
 * 
 * @package App\controllers\api
 */
class DashboardController extends KCBaseController
{

    /**
     * @var string The base route for this controller
     */
    protected $route = 'dashboard';

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        // Get all configuration data
        $this->registerRoute('/' . $this->route . '/upcoming-appointments', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getUpcomingAppointments'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'page' => [
                    'sanitize_callback' => 'absint',
                    'default' => 1
                ],
                'per_page' => [
                    'sanitize_callback' => 'absint',
                    'default' => 10
                ],
                'start_date' => [
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'end_date' => [
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'timezone' => [
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        // Dynamic statistics endpoint with component parameter
        $this->registerRoute('/' . $this->route . '/statistics/(?P<component>[a-zA-Z0-9-_]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getStatisticsCardCount'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'component' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Statistics component name (appointment, patient, doctor, etc.)',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($param) {
                        // Allow alphanumeric, hyphens, and underscores
                        return preg_match('/^[a-zA-Z0-9-_]+$/', $param);
                    }
                ]
            ]
        ]);

        // New route for top doctors
        $this->registerRoute('/' . $this->route . '/top-doctors', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getTopDoctors'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'limit' => [
                    'required' => false,
                    'default' => 5,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'description' => 'Number of top doctors to return',
                ],
            ]
        ]);
        // New route for recent payment history
        $this->registerRoute('/' . $this->route . '/recent-payments', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getRecentPayments'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'limit' => [
                    'required' => false,
                    'default' => 3,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'description' => 'Number of recent payments to return',
                ],
                'clinic_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'Filter by specific clinic ID',
                    'sanitize_callback' => 'absint',
                ],
            ]
        ]);
    }

    /**
     * Get recent payment history for dashboard
     *
     * Retrieves the most recent bills/payments with details
     * about patient, appointment, doctor, and services.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST API request object
     * @return WP_REST_Response|WP_Error Response with payment data or error
     */
    public function getRecentPayments(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            // Parse request parameters
            $limit = absint($request->get_param('limit') ?? 3);
            $clinic_id = absint($request->get_param('clinic_id') ?? 0);
            
            // Get date range parameters (if provided)
            $start_date = sanitize_text_field($request->get_param('start_date') ?? '');
            $end_date = sanitize_text_field($request->get_param('end_date') ?? '');

            // Get current user role for filtering
            $user_id = get_current_user_id();
            $user_role = $this->kcbase->getLoginUserRole();

            // Build query for bills
            $query = KCBill::table('b')
                ->select([
                    'b.*',
                    'e.id as encounter_id',
                    'e.encounter_date',
                    'a.id as appointment_id',
                    'a.appointment_start_date',
                    'a.appointment_start_time',
                    'a.doctor_id',
                    'a.patient_id',
                    'a.clinic_id',
                    'c.name as clinic_name',
                    'ce.name as encounter_clinic_name',
                    'ca.name as appointment_clinic_name',
                    'p.display_name as patient_name',
                    'p.user_email as patient_email',
                    'd.display_name as doctor_name',
                    'pam.payment_mode'  // Get payment mode from payments mapping
                ])
                ->leftJoin(KCPatientEncounter::class, 'b.encounter_id', '=', 'e.id', 'e')
                ->leftJoin(KCAppointment::class, 'b.appointment_id', '=', 'a.id', 'a')
                ->leftJoin(KCClinic::class, 'b.clinic_id', '=', 'c.id', 'c')
                ->leftJoin(KCClinic::class, 'e.clinic_id', '=', 'ce.id', 'ce')
                ->leftJoin(KCClinic::class, 'a.clinic_id', '=', 'ca.id', 'ca')
                ->leftJoin(KCUser::class, 'e.patient_id', '=', 'p.ID', 'p')
                ->leftJoin(KCUser::class, 'e.doctor_id', '=', 'd.ID', 'd')
                ->leftJoin(KCPaymentsAppointmentMapping::class, 'b.appointment_id', '=', 'pam.appointment_id', 'pam')
                ->orderBy('b.created_at', 'DESC')
                ->limit($limit);

            // Apply role-based filtering
            if ($user_role === $this->kcbase->getDoctorRole()) {
                // Doctors can only see bills for their appointments
                $query->where('e.doctor_id', '=', $user_id);
            } elseif ($user_role === $this->kcbase->getPatientRole()) {
                // Patients can only see their bills
                $query->where('e.patient_id', '=', $user_id);
            } elseif ($user_role === $this->kcbase->getClinicAdminRole()) {
                // Clinic admins can only see bills for their clinic
                if (!$clinic_id) {
                    $clinic_id = KCClinic::getClinicIdOfClinicAdmin($user_id);
                }
                if ($clinic_id) {
                    $query->where('b.clinic_id', '=', $clinic_id);
                }
            }

            // Apply clinic filter if provided
            if ($clinic_id > 0) {
                $query->where('b.clinic_id', '=', $clinic_id);
            }
            
            // Apply date range filters on bill creation date if provided
            if (!empty($start_date)) {
                $query->where('b.created_at', '>=', $start_date . ' 00:00:00');
            }
            
            if (!empty($end_date)) {
                $query->where('b.created_at', '<=', $end_date . ' 23:59:59');
            }

            // Execute query
            $bills = $query->get();

            $result = [];

            foreach ($bills as $bill) {
                // Get bill items to determine services
                $bill_items = KCBillItem::table('bi')
                    ->select(['bi.*', 's.name as service_name'])
                    ->leftJoin(KCService::class, 'bi.item_id', '=', 's.id', 's')
                    ->where('bi.bill_id', '=', $bill->id)
                    ->get();

                // Extract service names
                $services = [];
                foreach ($bill_items as $item) {
                    if (!empty($item->service_name)) {
                        $services[] = $item->service_name;
                    }
                }

                // Format bill creation date and time using WordPress date/time formatting functions
                $appointment_datetime = '';
                // Try both createdAt and created_at property names
                $bill_created_at = $bill->createdAt ?? $bill->created_at ?? null;
                
                if (!empty($bill_created_at)) {
                    $date = kcGetFormatedDate($bill_created_at);
                    $time = kcGetFormatedTime($bill_created_at);
                    $appointment_datetime = $date . ($time ? ', ' . $time : '');
                }

                // Determine status based on payment_status
                $status = strtolower($bill->paymentStatus ?? 'pending');
                // Map payment status to check-in/check-out for UI consistency
                $display_status = ($status === 'paid') ? 'check-out' : 'check-in';

                // Determine clinic name with fallback
                $clinic_name = $bill->clinic_name;
                if (empty($clinic_name)) {
                    $clinic_name = $bill->encounter_clinic_name;
                }
                if (empty($clinic_name)) {
                    $clinic_name = $bill->appointment_clinic_name;
                }

                // Format for response
                $result[] = [
                    'id' => absint($bill->id),
                    'patient' => [
                        'id' => absint($bill->patient_id),
                        'name' => sanitize_text_field($bill->patient_name ?? ''),
                        'email' => sanitize_email($bill->patient_email ?? ''),
                        'avatar' => get_user_meta($bill->patient_id, 'patient_profile_image', true) ? esc_url(wp_get_attachment_url((int) get_user_meta($bill->patient_id, 'patient_profile_image', true))) : ''
                    ],
                    'dateTime' => sanitize_text_field($appointment_datetime),
                    'doctor' => sanitize_text_field($bill->doctor_name ?? ''),
                    'clinic' => sanitize_text_field($clinic_name ?? ''),
                    'service' => !empty($services) ? sanitize_text_field(implode(', ', $services)) : __('General', 'kivicare-clinic-management-system'),
                    'charges' => sanitize_text_field($bill->actualAmount ? '$' . $bill->actualAmount . '/-' : '$0/-'),
                    'actualAmount' => floatval($bill->actualAmount ?? 0),
                    'totalAmount' => floatval($bill->totalAmount ?? 0),
                    'discount' => floatval($bill->discount ?? 0),
                    'paymentStatus' => sanitize_text_field($bill->paymentStatus ?? $bill->payment_status ?? 'pending'),
                    'paymentMode' => sanitize_text_field($bill->payment_mode ?? 'Manual'),
                    'status' => sanitize_text_field($display_status),
                    'encounter_id' => absint($bill->encounter_id),
                    'appointment_id' => absint($bill->appointment_id),
                    'created_at' => sanitize_text_field($bill->createdAt ?? '')
                ];
            }

                
            // Return response with date range parameters
            return $this->response([
                'data' => $result,
                'params' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'clinic_id' => $clinic_id,
                    'limit' => $limit
                ]
            ], __('Recent payments retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            // Log error for debugging
            KCErrorLogger::instance()->error('KiviCare Error (getRecentPayments): ' . $e->getMessage());

            // Return error response
            return new WP_Error(
                'payment_history_error',
                __('Error retrieving payment history', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }


    /**
     * Get top doctors sorted by appointment count
     *
     * Retrieves a list of doctors with the highest number of appointments,
     * with optional filtering by clinic and date range.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST API request object
     * @return WP_REST_Response|WP_Error Response with top doctors data or error
     */
    public function getTopDoctors(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            // Parse request parameters
            $limit = absint($request->get_param('limit') ?? 5);

            // Get date range parameters (if provided)
            $start_date = sanitize_text_field($request->get_param('start_date') ?? '');
            $end_date = sanitize_text_field($request->get_param('end_date') ?? '');

            // Build the base query
            $query = KCDoctor::table('d')
                ->select([
                    "d.id",
                    "d.display_name",
                    "d.user_email",
                    "COUNT(a.id) as appointment_count"
                ])
                ->rightJoin(KCAppointment::class, 'a.doctor_id', '=', 'd.id', 'a');

            // Apply date filters if provided
            if (!empty($start_date) && !empty($end_date)) {
                $query->where('a.appointment_start_date', '>=', $start_date)
                    ->where('a.appointment_end_date', '<=', $end_date);
            } elseif (!empty($start_date)) {
                $query->where('a.appointment_start_date', '>=', $start_date);
            } elseif (!empty($end_date)) {
                $query->where('a.appointment_end_date', '<=', $end_date);
            }

            // Complete the query
            $doctors = $query->groupBy('d.id')
                ->orderBy('appointment_count', 'DESC')
                ->limit($limit)
                ->get();

            $formatted_doctors = [];
            foreach ($doctors as $doctor) {
                // Get doctor's primary clinic
                $clinic_name = KCDoctorClinicMapping::table('dcm')
                    ->select(['c.name as clinic_name'])
                    ->join(KCClinic::class, 'dcm.clinic_id', '=', 'c.id', 'c')
                    ->where('dcm.doctor_id', '=', $doctor->id)
                    ->limit(1)
                    ->first();

                // Get clinic name or default text if not found
                $clinic_display_name = $clinic_name ? $clinic_name->clinic_name : __('Not assigned', 'kivicare-clinic-management-system');

                // Get doctor's speciality
                $speciality = get_user_meta($doctor->id, 'speciality', true);

                // Format doctor data for response
                $formatted_doctors[] = [
                    'id'                => absint($doctor->id),
                    'name'              => sanitize_text_field($doctor->display_name),
                    'email'             => sanitize_email($doctor->user_email),
                    'clinic'            => sanitize_text_field($clinic_display_name),
                    'appointment_count' => absint($doctor->appointment_count),
                    'appointments'      => $doctor->appointment_count,
                    'image'             => get_user_meta($doctor->id, 'doctor_profile_image', true) ? esc_url(wp_get_attachment_url((int) get_user_meta($doctor->id, 'doctor_profile_image', true))) : '',
                    'speciality'        => sanitize_text_field($speciality),
                ];
            }

            return $this->response($formatted_doctors, __('Top doctors retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            // Log error for debugging
            KCErrorLogger::instance()->error('KiviCare Error (getTopDoctors): ' . $e->getMessage());

            // Return error response
            return new WP_Error(
                'top_doctors_error',
                __('Error retrieving top doctors', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }


    /**
     * Get upcoming appointments for dashboard
     *
     * Retrieves appointments that are scheduled for today or future dates
     * with status "booked". Results are filtered by user capabilities.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST API request object
     * @return WP_REST_Response|WP_Error Response with appointments data or error
     */
    public function getUpcomingAppointments(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $page = $request->get_param('page');
            $per_page = $request->get_param('per_page');
            $offset = ($page - 1) * $per_page;

            $date_from = $request->get_param('start_date') ?? gmdate('Y-m-d');
            $date_to = $request->get_param('end_date') ?? null;
            $timezone = $request->get_param('timezone') ?? wp_timezone_string();
            
            $user_id = get_current_user_id();
            $user_role = $this->kcbase->getLoginUserRole();

            // Setup timezones
            try {
                $userTz = new \DateTimeZone($timezone);
                $utcTz = new \DateTimeZone('UTC');
            } catch (\Exception $e) {
                $userTz = new \DateTimeZone(wp_timezone_string());
                $utcTz = new \DateTimeZone('UTC');
            }

            // Build base query
            $query = KCAppointment::table('a')
                ->select([
                    "a.*",
                    'd.display_name as doctor_name',
                    'p.display_name as patient_name',
                    'clinic.name as clinic_name',
                    'clinic.email as clinic_email',
                    'p.ID as patient_user_id',
                    'd.ID as doctor_user_id',
                ])
                ->leftJoin(KCUser::class, 'a.doctor_id', '=', 'd.id', 'd')
                ->leftJoin(KCUser::class, 'a.patient_id', '=', 'p.id', 'p')
                ->leftJoin(KCClinic::class, 'a.clinic_id', '=', 'clinic.id', 'clinic')
                ->where('a.status', '=', KCAppointment::STATUS_BOOKED);

            // Filtering by date using UTC columns if possible
            if (!empty($date_from)) {
                $dtFrom = \DateTime::createFromFormat('Y-m-d', $date_from, $userTz);
                if ($dtFrom) {
                    $dtFrom->setTime(0, 0, 0);
                    $dtFrom->setTimezone($utcTz);
                    $query->where('a.appointment_start_utc', '>=', $dtFrom->format('Y-m-d H:i:s'));
                }
            }

            if (!empty($date_to)) {
                $dtTo = \DateTime::createFromFormat('Y-m-d', $date_to, $userTz);
                if ($dtTo) {
                    $dtTo->setTime(23, 59, 59);
                    $dtTo->setTimezone($utcTz);
                    $query->where('a.appointment_start_utc', '<=', $dtTo->format('Y-m-d H:i:s'));
                }
            }

            // Apply role-based filtering
            if ($user_role === $this->kcbase->getDoctorRole()) {
                $query->where('a.doctor_id', '=', $user_id);
            } elseif ($user_role === $this->kcbase->getPatientRole()) {
                $query->where('a.patient_id', '=', $user_id);
            }

            if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
                $clinic_id = null;
                switch ($user_role) {
                    case $this->kcbase->getClinicAdminRole():
                        $clinic_id = KCClinic::getClinicIdOfClinicAdmin();
                        break;
                    case $this->kcbase->getReceptionistRole():
                        $clinic_id = KCClinic::getClinicIdOfReceptionist();
                        break;
                }
                if (!empty($clinic_id)) {
                    $query->where("a.clinic_id", '=', $clinic_id);
                }
            }

            $query = apply_filters('kc_upcoming_appointments_query', $query, $request);

            // Get total count BEFORE applying limit/offset
            $total = (int)$query->count();

            // Apply pagination and ordering
            $query->orderBy('a.appointment_start_utc', 'ASC')
                  ->limit($per_page)
                  ->offset($offset);

            $appointments = $query->get();
            if ($appointments->isEmpty()) {
                return $this->response([
                    'appointments' => [],
                    'pagination' => ['total' => $total, 'page' => $page, 'per_page' => $per_page, 'total_pages' => ceil($total / $per_page)]
                ], __('No upcoming appointments found', 'kivicare-clinic-management-system'));
            }

            // Enrichment: Batch fetch services
            $appointmentIds = $appointments->pluck('id')->toArray();
            $services = KCAppointment::table('a')
                ->select(["a.id as appointment_id", "s.name AS service_name", "s.id AS service_id", "sd.charges", "a.clinic_id", "a.doctor_id"])
                ->leftJoin(KCAppointmentServiceMapping::class, 'a.id', '=', 'asm.appointment_id', 'asm')
                ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
                ->leftJoin(KCServiceDoctorMapping::class, 'sd.service_id', '=', 's.id', 'sd')
                ->whereIn('a.id', $appointmentIds)
                ->whereRaw('sd.clinic_id = a.clinic_id AND sd.doctor_id = a.doctor_id')
                ->get()->groupBy('appointment_id');

            // Format results
            $appointmentsData = [];
            foreach ($appointments as $appointment) {
                $aid = $appointment->id;
                $aptServices = $services->get($aid) ?? collect([]);
                
                $service_array = [];
                $service_list = [];
                $service_charges = 0;

                foreach ($aptServices as $service) {
                    if (!empty($service->service_name)) {
                        $service_array[] = $service->service_name;
                        $service_list[] = [
                            'service_id' => absint($service->service_id),
                            'name' => $service->service_name,
                            'charges' => round(floatval($service->charges), 3)
                        ];
                        $service_charges += floatval($service->charges);
                    }
                }

                // Convert UTC to user timezone
                $start_utc = $appointment->appointment_start_utc;
                $display_start_date = $appointment->appointmentStartDate;
                $display_start_time = $appointment->appointmentStartTime;

                if (!empty($start_utc)) {
                    try {
                        $dt = new \DateTime($start_utc, $utcTz);
                        $dt->setTimezone($userTz);
                        $display_start_date = $dt->format('Y-m-d');
                        $display_start_time = $dt->format('H:i:s');
                    } catch (\Exception $e) {
                        // Keep defaults
                    }
                }

                $appointmentsData[] = apply_filters('kc_get_upcoming_appointment_data', [
                    'id' => absint($aid),
                    'appointmentStartDate' => $display_start_date,
                    'appointmentStartDateFormated' => kcGetFormatedDate($display_start_date),
                    'appointmentStartTime' => $display_start_time ? gmdate('h:i A', strtotime($display_start_time)) : '',
                    'appointmentStartTimeFormated' => kcGetFormatedTime($display_start_time),
                    'visitType' => $service_list,
                    'serviceListName' => implode(', ', $service_array),
                    'totalCharges' => $service_charges,
                    'clinicId' => absint($appointment->clinicId),
                    'clinicName' => $appointment->clinic_name ?? '',
                    'doctorId' => absint($appointment->doctorId),
                    'doctorName' => $appointment->doctor_name ?? '',
                    'doctor_image_url' => $appointment->doctor_user_id ? esc_url(wp_get_attachment_url((int) get_user_meta($appointment->doctor_user_id, 'doctor_profile_image', true))) : '',
                    'patientId' => absint($appointment->patientId),
                    'patientName' => $appointment->patient_name ?? '',
                    'patient_image_url' => $appointment->patient_user_id ? esc_url(wp_get_attachment_url((int) get_user_meta($appointment->patient_user_id, 'patient_profile_image', true))) : '',
                    'status' => absint($appointment->status),
                    'video_consultation' => apply_filters('kc_is_video_consultation', false, absint($aid)),
                ], $appointment);
            }

            return $this->response([
                'appointments' => $appointmentsData,
                'pagination' => ['total' => $total, 'page' => $page, 'per_page' => $per_page, 'total_pages' => ceil($total / $per_page)]
            ], __('Appointments retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KiviCare Error (getUpcomingAppointments): ' . $e->getMessage());
            return new WP_Error('appointment_error', $e->getMessage(), ['status' => 500]);
        }
    }



    /**
     * Get statistics count for a specific component
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getStatisticsCardCount(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            // Get the component parameter from the URL
            $component = $request->get_param('component');

            if (empty($component)) {
                return new WP_Error('missing_component', 'Component parameter is required', ['status' => 400]);
            }

            // Get current user role for role-based filtering
            $current_user = wp_get_current_user();
            $user_role = !empty($current_user->roles) ? $current_user->roles[0] : 'patient';

            // Get date range parameters if provided
            $filters = [];
            $start_date = $request->get_param('start_date');
            $end_date = $request->get_param('end_date');

            if (!empty($start_date) && !empty($end_date)) {
                $filters['date_range'] = [
                    'start_date' => sanitize_text_field($start_date),
                    'end_date' => sanitize_text_field($end_date)
                ];
            }

            // Calculate statistics based on component type, passing date range
            $count = $this->calculateStatistics($component, $user_role, $filters);

            if ($count === false || $count === null) {
                return new WP_Error('invalid_component', "Invalid component: {$component}", ['status' => 400]);
            }

            // Prepare response data
            $response_data = [
                'component' => $component,
                'user_role' => $user_role
            ];

            // Handle array return type (used for formatted currency values)
            if (is_array($count) && isset($count['count'])) {
                $response_data = array_merge($response_data, $count);
            } else {
                $response_data['count'] = $count;
            }

            return $this->response($response_data, "Statistics for {$component} retrieved successfully");
        } catch (Exception $e) {
            return new WP_Error('statistics_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Calculate statistics based on component type and user role
     * 
     * @param string $component
     * @param string $user_role
     * @param array $date_range Optional date range parameters
     * @return int|array|false
     */
    private function calculateStatistics($component, $user_role, $date_range = [])
    {
        $kcbase = KCBase::get_instance();
        $current_user_id = get_current_user_id();
        $user_role = $kcbase->getLoginUserRole();
        switch ($component) {
            case 'appointment':
                return $this->getTotalAppointments($user_role, $current_user_id, $date_range);

            case 'today-appointment':
                return $this->getTodayAppointments($user_role, $current_user_id, $date_range);

            case 'patient':
                return $this->getTotalPatients($user_role, $current_user_id, $date_range);

            case 'doctor':
                return $this->getTotalDoctors($user_role, $current_user_id, $date_range);

            case 'clinic':
                return $this->getTotalClinics($user_role, $current_user_id, $date_range);

            case 'service':
                return $this->getTotalServices($user_role, $current_user_id, $date_range);

            case 'revenue':
                return $this->getTotalRevenue($user_role, $current_user_id, $date_range);

            case 'pending-appointment':
                return $this->getPendingAppointments($user_role, $current_user_id, $date_range);

            case 'completed-appointment':
                return $this->getCompletedAppointments($user_role, $current_user_id, $date_range);

            case 'cancelled-appointment':
                return $this->getCancelledAppointments($user_role, $current_user_id, $date_range);

            case 'booking-status':
                return $this->getBookingStatusStats($user_role, $current_user_id, $date_range);

            default:
                return false; // Invalid component
        }
    }

    /**
     * Get total appointments count based on user role
     */
    private function getTotalAppointments($user_role, $user_id, $filters = [])
    {
        return KCAppointment::getCount($filters, $user_role, $user_id);
    }

    /**
     * Get today's appointments count
     */
    private function getTodayAppointments($user_role, $user_id,  $filters = [])
    {
        return KCAppointment::getTodayCount($user_role, $user_id);
    }

    /**
     * Get total patients count
     */
    private function getTotalPatients($user_role, $user_id, $filters = [])
    {
        return KCPatient::getCount($user_role, $user_id, $filters);
    }

    /**
     * Get total doctors count
     */
    private function getTotalDoctors($user_role, $user_id, $filters = [])
    {
        // Pass date filter to the doctor count method
        return KCDoctor::getCount($user_role, $user_id, $filters);
    }

    /**
     * Get total clinics count
     */
    private function getTotalClinics($user_role, $user_id, $filters = [])
    {
        // Only administrators can see clinic counts
        if ($user_role !== 'administrator') {
            return 0;
        }

        // Start with a base query
        $query = KCClinic::query();

        // Check if date range filter is applied
        if (!empty($filters['date_range']) && !empty($filters['date_range']['start_date']) && !empty($filters['date_range']['end_date'])) {
            $start_date = sanitize_text_field($filters['date_range']['start_date']);
            $end_date = sanitize_text_field($filters['date_range']['end_date']);

            // Format for date comparison
            $start_date_sql = gmdate('Y-m-d 00:00:00', strtotime($start_date));
            $end_date_sql = gmdate('Y-m-d 23:59:59', strtotime($end_date));

            // Apply date filter to query
            $query->where('created_at', '>=', $start_date_sql)
                ->where('created_at', '<=', $end_date_sql);
        }

        // Execute the count query
        $count = $query->count();

        return (int) $count;
    }

    /**
     * Get total services count
     */
    private function getTotalServices($user_role, $user_id, $filters = [])
    {
        return KCService::getCount($user_role, $user_id, $filters);
    }

    /**
     * Get total revenue with formatted currency
     * 
     * @param string $user_role User role
     * @param int $user_id User ID
     * @return array Array with count and formatted_count with currency
     */
    private function getTotalRevenue($user_role, $user_id, $date_range = [])
    {
        return KCBill::getTotalRevenue($user_role, $user_id, $date_range);
    }

    /**
     * Get pending appointments count
     */
    private function getPendingAppointments($user_role, $user_id, $filters = [])
    {
        return KCAppointment::getPendingCount($user_role, $user_id, $filters);
    }

    /**
     * Get completed appointments count
     */
    private function getCompletedAppointments($user_role, $user_id, $filters = [])
    {
        return KCAppointment::getCompletedCount($user_role, $user_id, $filters);
    }

    /**
     * Get cancelled appointments count
     */
    private function getCancelledAppointments($user_role, $user_id, $filters = [])
    {
        return KCAppointment::getCancelledCount($user_role, $user_id, $filters);
    }

    /**
     * Get booking status statistics
     * 
     * @param string $user_role
     * @param int $user_id
     * @param array $filters
     * @return array
     */
    private function getBookingStatusStats($user_role, $user_id, $filters = [])
    {
        // Get counts for each status
        // Note: KCAppointment constants mismatch with Dashboard.jsx logic potentially, 
        // using KCAppointment constants as source of truth.
        
        $booked = KCAppointment::getCount(array_merge($filters, ['status' => KCAppointment::STATUS_BOOKED]), $user_role, $user_id);
        $check_in = KCAppointment::getCount(array_merge($filters, ['status' => KCAppointment::STATUS_CHECK_IN]), $user_role, $user_id);
        $check_out = KCAppointment::getCount(array_merge($filters, ['status' => KCAppointment::STATUS_CHECK_OUT]), $user_role, $user_id);
        $pending = KCAppointment::getCount(array_merge($filters, ['status' => KCAppointment::STATUS_PENDING]), $user_role, $user_id);
        $cancelled = KCAppointment::getCount(array_merge($filters, ['status' => KCAppointment::STATUS_CANCELLED]), $user_role, $user_id);
        
        $total = $booked + $check_in + $check_out + $pending + $cancelled;
        
        return [
            'count' => $total,
            'status_breakdown' => [
                'booked' => $booked,
                'check_in' => $check_in,
                'check_out' => $check_out,
                'pending' => $pending,
                'cancelled' => $cancelled
            ]
        ];
    }
}
