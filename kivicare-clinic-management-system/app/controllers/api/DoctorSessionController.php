<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\baseClasses\KCErrorLogger;
use App\models\KCClinic;
use App\models\KCClinicSession;
use App\models\KCDoctor;
use App\models\KCDoctorSession;
use App\models\KCUserMeta;
use Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class DoctorSessionController
 * 
 * API Controller for Doctor Session-related endpoints
 * 
 * @package App\controllers\api
 */
class DoctorSessionController extends KCBaseController
{
    /**
     * @var string The base route for this controller
     */
    protected $route = 'doctor-sessions';

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        // Get all doctor sessions
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getDoctorSessions'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getListEndpointArgs()
        ]);

        // Get single doctor session
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getDoctorSession'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getSingleEndpointArgs()
        ]);

        // Create doctor session
        $this->registerRoute('/' . $this->route, [
            'methods' => 'POST',
            'callback' => [$this, 'createDoctorSession'],
            'permission_callback' => [$this, 'checkCreatePermission'],
            'args' => $this->getCreateEndpointArgs()
        ]);

        // Update doctor session
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateDoctorSession'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getUpdateEndpointArgs()
        ]);

        // Delete doctor session
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteDoctorSession'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getDeleteEndpointArgs()
        ]);

        // Bulk delete doctor sessions
        $this->registerRoute('/' . $this->route . '/bulk/delete', [
            'methods' => 'POST',
            'callback' => [$this, 'bulkDeleteDoctorSessions'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getBulkActionEndpointArgs()
        ]);

        // Export doctor sessions
        $this->registerRoute('/' . $this->route . '/export', [
            'methods' => 'GET',
            'callback' => [$this, 'exportDoctorSessions'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getExportEndpointArgs()
        ]);

    }

    /**
     * Get arguments for the list endpoint
     *
     * @return array
     */
    private function getListEndpointArgs()
    {
        return [
            'search' => [
                'description' => 'Search term to filter results',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'description' => 'Session status (0: Inactive, 1: Active)',
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'orderby' => [
                'description' => 'Sort results by specified field',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'order' => [
                'description' => 'Sort direction (asc or desc)',
                'type' => 'string',
                'sanitize_callback' => function ($param) {
                    return strtolower(sanitize_text_field($param));
                },
            ],
            'page' => [
                'description' => 'Current page of results',
                'type' => 'integer',
                'default' => 1,
                'sanitize_callback' => 'absint',
            ],
            'perPage' => [
                'description' => 'Number of results per page',
                'type' => 'string',
                'default' => 10,
                'sanitize_callback' => function ($param) {
                    return strtolower($param) === 'all' ? 'all' : absint($param);
                },
            ]
        ];
    }


    /**
     * Custom validation function for days schedule
     */
    public function validate_days_schedule($param, $request, $key)
    {
        if (!is_array($param)) {
            return new WP_Error('invalid_days', 'Days must be an array');
        }

        if (count($param) !== 7) {
            return new WP_Error('invalid_days_count', 'Days array must contain exactly 7 days');
        }

        $required_days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $provided_days = array_column($param, 'id');

        if (array_diff($required_days, $provided_days)) {
            return new WP_Error('missing_days', 'All 7 days of the week must be provided');
        }

        foreach ($param as $day) {
            // Validate day structure
            if (!isset($day['id'], $day['enabled'], $day['main_session'], $day['breaks'])) {
                return new WP_Error('invalid_day_structure', 'Each day must have id, enabled, main_session, and breaks');
            }

            // Validate day ID
            if (!in_array($day['id'], $required_days)) {
                return new WP_Error('invalid_day_id', 'Invalid day ID: ' . $day['id']);
            }

            // Validate enabled flag
            if (!is_bool($day['enabled'])) {
                return new WP_Error('invalid_enabled_flag', 'Enabled must be a boolean');
            }

            // Validate main session
            if (!is_array($day['main_session']) || !isset($day['main_session']['start'], $day['main_session']['end'])) {
                return new WP_Error('invalid_main_session', 'Main session must have start and end times');
            }

            // If day is enabled, validate time format and logic
            if ($day['enabled']) {
                $start = $day['main_session']['start'];
                $end = $day['main_session']['end'];

                if (empty($start) || empty($end)) {
                    return new WP_Error('empty_session_times', 'Start and end times are required for enabled days');
                }

                if (!validate_time_format($start) || !validate_time_format($end)) {
                    return new WP_Error('invalid_time_format', 'Time must be in HH:MM format');
                }

                if (strtotime($start) >= strtotime($end)) {
                    return new WP_Error('invalid_time_range', 'Start time must be before end time');
                }
            }

            // Validate breaks
            if (!is_array($day['breaks'])) {
                return new WP_Error('invalid_breaks', 'Breaks must be an array');
            }

            foreach ($day['breaks'] as $break) {
                if (!isset($break['start'], $break['end'])) {
                    return new WP_Error('invalid_break_structure', 'Each break must have start and end times');
                }

                if (!validate_time_format($break['start']) || !validate_time_format($break['end'])) {
                    return new WP_Error('invalid_break_time_format', 'Break times must be in HH:MM format');
                }

                if (strtotime($break['start']) >= strtotime($break['end'])) {
                    return new WP_Error('invalid_break_range', 'Break start time must be before end time');
                }

                // Validate break is within main session (if day is enabled)
                if ($day['enabled'] && !empty($day['main_session']['start']) && !empty($day['main_session']['end'])) {
                    $session_start = strtotime($day['main_session']['start']);
                    $session_end = strtotime($day['main_session']['end']);
                    $break_start = strtotime($break['start']);
                    $break_end = strtotime($break['end']);

                    if ($break_start < $session_start || $break_end > $session_end) {
                        return new WP_Error('break_outside_session', 'Break times must be within main session hours');
                    }
                }
            }
        }

        return true;
    }

    /**
     * Custom sanitization function for days schedule
     */
    public function sanitize_days_schedule($param)
    {
        if (!is_array($param)) {
            return [];
        }

        $sanitized = [];

        foreach ($param as $day) {
            if (!is_array($day)) {
                continue;
            }

            $sanitized_day = [
                'id' => sanitize_text_field($day['id'] ?? ''),
                'enabled' => (bool) ($day['enabled'] ?? false),
                'main_session' => [
                    'start' => sanitize_text_field($day['main_session']['start'] ?? ''),
                    'end' => sanitize_text_field($day['main_session']['end'] ?? '')
                ],
                'breaks' => []
            ];

            if (isset($day['breaks']) && is_array($day['breaks'])) {
                foreach ($day['breaks'] as $break) {
                    if (is_array($break) && isset($break['start'], $break['end'])) {
                        $sanitized_day['breaks'][] = [
                            'start' => sanitize_text_field($break['start']),
                            'end' => sanitize_text_field($break['end'])
                        ];
                    }
                }
            }

            $sanitized[] = $sanitized_day;
        }

        return $sanitized;
    }


    /**
     * Get arguments for single item endpoints
     *
     * @return array
     */
    private function getSingleEndpointArgs()
    {
        return [
            'id' => [
                'description' => 'Doctor Session ID',
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint',
            ]
        ];
    }

    /**
     * Get arguments for the create endpoint
     *
     * @return array
     */
    protected function getCreateEndpointArgs()
    {
        return [
            'doctor_id' => [
                'description' => 'Doctor ID',
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint',
            ],
            'clinic_id' => [
                'description' => 'Clinic ID',
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint',
            ],
            'days' => [
                'description' => 'Weekly schedule configuration',
                'type' => 'array',
                'required' => true,
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'enum' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
                            'required' => true
                        ],
                        'enabled' => [
                            'type' => 'boolean',
                            'required' => true
                        ],
                        'main_session' => [
                            'type' => 'object',
                            'properties' => [
                                'start' => [
                                    'type' => 'string',
                                    'pattern' => '^([01]?[0-9]|2[0-3]):[0-5][0-9]$|^$'
                                ],
                                'end' => [
                                    'type' => 'string',
                                    'pattern' => '^([01]?[0-9]|2[0-3]):[0-5][0-9]$|^$'
                                ]
                            ],
                            'required' => true
                        ],
                        'breaks' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'start' => [
                                        'type' => 'string',
                                        'pattern' => '^([01]?[0-9]|2[0-3]):[0-5][0-9]$'
                                    ],
                                    'end' => [
                                        'type' => 'string',
                                        'pattern' => '^([01]?[0-9]|2[0-3]):[0-5][0-9]$'
                                    ]
                                ],
                                'required' => ['start', 'end']
                            ],
                            'default' => []
                        ]
                    ],
                    'required' => ['id', 'enabled', 'main_session', 'breaks']
                ],
                'validate_callback' => [$this, 'validate_days_schedule'],
                'sanitize_callback' => [$this, 'sanitize_days_schedule'],
            ],
            'time_slot' => [
                'description' => 'Time slot',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ]
        ];
    }

    /**
     * Get arguments for the update endpoint
     *
     * @return array
     */
    private function getUpdateEndpointArgs()
    {
        $args = $this->getCreateEndpointArgs();
        $args['id'] = [
            'description' => 'Doctor Session ID',
            'type' => 'integer',
            'required' => true,
            'sanitize_callback' => 'absint',
        ];
        return $args;
    }

    /**
     * Get arguments for the delete endpoint
     *
     * @return array
     */
    private function getDeleteEndpointArgs()
    {
        return [
            'id' => [
                'description' => 'Doctor Session ID',
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint',
            ]
        ];
    }

    /**
     * Get arguments for bulk action endpoints
     *
     * @return array
     */
    private function getBulkActionEndpointArgs()
    {
        return [
            'ids' => [
                'description' => 'Array of Doctor Session IDs',
                'type' => 'array',
                'required' => true,
            ]
        ];
    }

    /**
     * Get arguments for bulk status update endpoint
     *
     * @return array
     */
    private function getBulkStatusUpdateEndpointArgs()
    {
        return array_merge(
            $this->getBulkActionEndpointArgs(),
            [
                'status' => [
                    'description' => 'Session status (0: Inactive, 1: Active)',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ]
            ]
        );
    }

    /**
     * Get arguments for export endpoint
     *
     * @return array
     */
    private function getExportEndpointArgs()
    {
        return [
            'format' => [
                'description' => 'Export format (csv, xls, pdf)',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'csv'
            ],
            'search' => [
                'description' => 'Search term to filter results',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'doctor_id' => [
                'description' => 'Filter by doctor ID',
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'clinic_id' => [
                'description' => 'Filter by clinic ID',
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'status' => [
                'description' => 'Filter by status',
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ]
        ];
    }

    /**
     * Check if user has permission to access doctor session endpoints
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkPermission($request)
    {
        // Example: Only allow users with 'read' capability
        return $this->checkCapability('doctor_session_list');
    }

    /**
     * Check if user has permission to create a doctor session
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkCreatePermission($request)
    {
        // Example: Only allow users with 'doctor_session_add' capability
        return $this->checkCapability('doctor_session_add');
    }

    /**
     * Check if user has permission to update a doctor session
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkUpdatePermission($request)
    {
        // Example: Only allow users with 'doctor_session_add' capability
        return $this->checkCapability('doctor_session_add');
    }

    /**
     * Check if user has permission to delete a doctor session
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkDeletePermission($request)
    {
        // Example: Only allow users with 'delete_posts' capability
        return $this->checkCapability('doctor_session_delete');
    }
    public function getDoctorSessions(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();
            $current_user_role = $this->kcbase->getLoginUserRole();

            $query = KCClinicSession::query()
                ->setTableAlias('kc_doctor_sessions')
                ->select([
                    'kc_doctor_sessions.*',
                    'kc_doctors.display_name as doctor_name',
                    'kc_doctors.user_email as doctor_email',
                    'doctor_meta.meta_value as doctor_profile_image',
                    'kc_clinics.name as clinic_name',
                    'kc_clinics.email as clinic_email',
                    // Use clinic profile image stored on clinics table (matches other modules)
                    'kc_clinics.profile_image as clinic_profile_image',
                ])
                ->leftJoin(KCDoctor::class, 'kc_doctor_sessions.doctor_id', '=', 'kc_doctors.id', 'kc_doctors')
                ->leftJoin(KCUserMeta::class, function ($query) {
                    $query
                        ->on('doctor_meta.user_id', '=', 'kc_doctors.id')
                        ->on('doctor_meta.meta_key', '=', "'doctor_profile_image'");
                }, null, null, 'doctor_meta')
                ->leftJoin(KCClinic::class, 'kc_doctor_sessions.clinic_id', '=', 'kc_clinics.id', 'kc_clinics')
                ->where(function ($q) {
                    $q->where('kc_doctor_sessions.start_time', '!=', '00:00:00')
                        ->orWhere('kc_doctor_sessions.end_time', '!=', '00:00:00');
                });
                /*

                commented out to show all sessions days old new replacement isue 

                // Only get parent sessions or standalone sessions
                // ->where(function ($q) {
                //     $q->where('kc_doctor_sessions.parent_id', 0)
                //         ->orWhereNull('kc_doctor_sessions.parent_id');
                // });

                */

            // Filter by doctor role - doctors can only see their own sessions
            if ($current_user_role === $this->kcbase->getDoctorRole()) {
                $current_doctor_id = get_current_user_id();
                $query->where('kc_doctor_sessions.doctor_id', $current_doctor_id);
            }

            // Filter by clinic for receptionist role
            $clinic_id = null;
            if ($current_user_role === $this->kcbase->getReceptionistRole()) {
                $clinic_id = KCClinic::getClinicIdOfReceptionist();
            } elseif ($current_user_role === $this->kcbase->getClinicAdminRole()) {
                $clinic_id = KCClinic::getClinicIdOfClinicAdmin();
            }

            if ($clinic_id) {
                $query->where('kc_doctor_sessions.clinic_id', '=', $clinic_id);
            }


            // Apply search filter
            if (!empty($params['search'])) {
                $search = esc_sql($params['search']);
                $query->where(function ($q) use ($search) {
                    $q->where('kc_doctors.display_name', 'LIKE', "%{$search}%")
                        ->orWhere('kc_clinics.name', 'LIKE', "%{$search}%");
                });
            }

            // Apply status filter
            if (isset($params['status']) && $params['status'] !== '') {
                $query->where('kc_doctor_sessions.status', (int) $params['status']);
            }

            // Apply doctor filter
            if (!empty($params['doctor_id'])) {
                $query->where('kc_doctor_sessions.doctor_id', (int) $params['doctor_id']);
            }

            // Apply clinic filter
            if (!empty($params['clinic_id'])) {
                $query->where('kc_doctor_sessions.clinic_id', (int) $params['clinic_id']);
            }

            // Apply sorting with proper field mapping
            $order = !empty($params['order']) && strtolower($params['order']) === 'desc' ? 'DESC' : 'ASC';
            if (!empty($params['orderby'])) {
                $orderby = $params['orderby'];

                // Map frontend field names to database fields
                $fieldMapping = [
                    'doctor_name' => 'kc_doctors.display_name',
                    'clinic_name' => 'kc_clinics.name',
                    'time_slot' => 'kc_doctor_sessions.time_slot'
                ];

                // Use mapped field if exists, otherwise use the field as-is with table prefix
                $sortField = isset($fieldMapping[$orderby])
                    ? $fieldMapping[$orderby]
                    : 'kc_doctor_sessions.' . $orderby;

                $query->orderBy($sortField, $order);
            } else {
                $query->orderBy('kc_doctor_sessions.doctor_id', 'DESC')
                    ->orderBy('kc_doctor_sessions.clinic_id', 'DESC');
            }

            // Get all sessions
            $allSessions = $query->get();

            // Group sessions by doctor-clinic combination
            $groupedSessions = [];

            // Day translation map - translate at source
            $dayTranslations = [
                'MON' => __('Mon', 'kivicare-clinic-management-system'),
                'TUE' => __('Tue', 'kivicare-clinic-management-system'),
                'WED' => __('Wed', 'kivicare-clinic-management-system'),
                'THU' => __('Thu', 'kivicare-clinic-management-system'),
                'FRI' => __('Fri', 'kivicare-clinic-management-system'),
                'SAT' => __('Sat', 'kivicare-clinic-management-system'),
                'SUN' => __('Sun', 'kivicare-clinic-management-system'),
            ];

            foreach ($allSessions as $session) {
                $key = $session->doctorId . '_' . $session->clinicId;

                if (!isset($groupedSessions[$key])) {
                    $groupedSessions[$key] = [
                        'id' => $session->id, // Use first session ID as group ID
                        'doctor' => [
                            'id' => $session->doctorId,
                            'name' => $session->doctor_name,
                            'email' => $session->doctor_email,
                            'doctor_image_url' => wp_get_attachment_url($session->doctor_profile_image ?? 0),
                        ],
                        'clinic' => [
                            'id' => $session->clinicId,
                            'name' => $session->clinic_name,
                            'email' => $session->clinic_email,
                            'clinic_image_url' => $session->clinic_profile_image
                                ? wp_get_attachment_url((int) $session->clinic_profile_image)
                                : '',
                        ],
                        'days' => [],
                        'time_slot' => $session->timeSlot,
                    ];
                }

                // Translate day immediately at source
                $dayCode = strtoupper($session->day);
                $translatedDay = isset($dayTranslations[$dayCode]) ? $dayTranslations[$dayCode] : $dayCode;
                $groupedSessions[$key]['days'][] = $translatedDay;
            }

            // Convert to array and format days (already translated)
            $processedSessions = [];
            foreach ($groupedSessions as $group) {
                $group['days'] = implode(', ', array_unique($group['days']));
                $processedSessions[] = $group;
            }

            // Pagination
            $total = count($processedSessions);
            $perPageParam = isset($params['perPage']) ? $params['perPage'] : (isset($params['per_page']) ? $params['per_page'] : 10);

            // Handle "all" option for perPage
            $showAll = (is_string($perPageParam) && strtolower($perPageParam) === 'all');
            $perPage = $showAll ? $total : (int) $perPageParam;

            $page = isset($params['page']) ? (int) $params['page'] : 1;

            // Validate pagination inputs
            if (!$showAll && $perPage <= 0) {
                $perPage = 10;
            }
            if ($page <= 0) {
                $page = 1;
            }

            $totalPages = ($total > 0 && $perPage > 0) ? ceil($total / $perPage) : 1;
            $offset = ($page - 1) * $perPage;

            // Slice data safely
            if ($showAll) {
                $paginatedSessions = $processedSessions;
            } else {
                $paginatedSessions = array_slice($processedSessions, $offset, $perPage);
            }

            $data = [
                'sessions' => $paginatedSessions,
                'pagination' => [
                    'total' => $total,
                    'lastPage' => $totalPages,
                    'perPage' => $perPage,
                    'currentPage' => $page,
                ]
            ];

            return $this->response($data, __('Doctor sessions retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('getDoctorSessions Error: ' . $e->getMessage());
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

    /**
     * Get single doctor session by ID
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getDoctorSession(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');
            $parent = KCClinicSession::find($id);
            if (!$parent) {
                return $this->response(null, __('Doctor session not found', 'kivicare-clinic-management-system'), false, 404);
            }
            $clinic = KCClinic::find($parent->clinicId);
            $doctor = KCDoctor::find($parent->doctorId);

            // Fetch all sessions for this doctor-clinic combination
            $sessions = KCClinicSession::query()
                ->where('doctorId', $parent->doctorId)
                ->where('clinicId', $parent->clinicId)
                ->where(function ($q) {
                    $q->where('startTime', '!=', '00:00:00')
                        ->orWhere('endTime', '!=', '00:00:00');
                })
                ->orderBy('day', 'ASC')
                ->orderBy('startTime', 'ASC')
                ->get();

            // Group sessions by day
            $dayOrder = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
            $sessionsByDay = [];
            foreach ($sessions as $session) {
                $day = $session->day;
                if (!isset($sessionsByDay[$day])) {
                    $sessionsByDay[$day] = [];
                }
                $sessionsByDay[$day][] = $session;
            }

            uksort($sessionsByDay, function ($a, $b) use ($dayOrder) {
                return array_search($a, $dayOrder) <=> array_search($b, $dayOrder);
            });

            $days = [];
            foreach ($sessionsByDay as $day => $daySessions) {
                // Sort by startTime just in case
                usort($daySessions, function ($a, $b) {
                    return strcmp($a->startTime, $b->startTime);
                });

                // Main session is the first session's start to last session's end
                $mainStart = substr($daySessions[0]->startTime, 0, 5);
                $mainEnd = substr($daySessions[count($daySessions) - 1]->endTime, 0, 5);

                // Only calculate breaks if there are 2 or more sessions for this day
                $breaks = [];
                if (count($daySessions) > 1) {
                    for ($i = 0; $i < count($daySessions) - 1; $i++) {
                        // Always add a break if there is a gap between sessions
                        $currentEnd = substr($daySessions[$i]->endTime, 0, 5);
                        $nextStart = substr($daySessions[$i + 1]->startTime, 0, 5);
                        if (strtotime($currentEnd) < strtotime($nextStart)) {
                            $breaks[] = [
                                'start' => $currentEnd,
                                'end' => $nextStart,
                            ];
                        }
                    }
                }

                $days[] = [
                    'id' => $day,
                    'enabled' => true,
                    'main_session' => [
                        'start' => $mainStart,
                        'end' => $mainEnd,
                    ],
                    'breaks' => $breaks,
                ];
            }

            $sessionData = array(
                'id' => $parent->id,
                'doctorId' => $parent->doctorId,
                'doctorName' => $doctor->displayName,
                'clinicId' => $parent->clinicId,
                'clinicName' => $clinic->name,
                'days' => $days,
                'time_slot' => gmdate('H:i:s', (int) $parent->timeSlot * 60),
                'start_time' => $parent->startTime,
                'end_time' => $parent->endTime,
            );
            return $this->response($sessionData, __('Doctor session retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }


    /**
     * Check for overlapping sessions for a doctor in a clinic for given days.
     * Optionally exclude a session ID (for update).
     *
     * @param int $doctor_id
     * @param int $clinic_id
     * @param array $days
     * @param int|null $exclude_session_id
     * @return WP_Error|true
     */
    private function checkSessionOverlap($doctor_id, $clinic_id, $days, $exclude_session_id = null)
    {
        foreach ($days as $day) {
            if (isset($day['enabled']) && $day['enabled'] === true) {
                $query = KCClinicSession::query()
                    ->setTableAlias('kc_doctor_sessions')
                    ->select(['kc_doctor_sessions.*', 'kc_doctors.display_name as doctor_name'])
                    ->leftJoin(KCDoctor::class, 'kc_doctor_sessions.doctor_id', '=', 'kc_doctors.id', 'kc_doctors')
                    ->where('kc_doctor_sessions.doctor_id', $doctor_id)
                    ->where('kc_doctor_sessions.clinic_id', $clinic_id)
                    ->where('kc_doctor_sessions.day', $day['id'])
                    ->where(function ($q) {
                        $q->where('kc_doctor_sessions.start_time', '!=', '00:00:00')
                            ->orWhere('kc_doctor_sessions.end_time', '!=', '00:00:00');
                    });

                if ($exclude_session_id) {
                    $query->where('kc_doctor_sessions.id', '!=', $exclude_session_id);
                    $query->where('kc_doctor_sessions.parent_id', '!=', $exclude_session_id);
                }

                $existingSessions = $query->get();
                foreach ($existingSessions as $session) {
                    $startTime = $session->startTime;
                    $endTime = $session->endTime;
                    $mainStart = $day['main_session']['start'];
                    $mainEnd = $day['main_session']['end'];

                    // Check if the new session overlaps with existing session
                    if (
                        (strtotime($mainStart) < strtotime($endTime) && strtotime($mainEnd) > strtotime($startTime)) ||
                        (strtotime($mainStart) >= strtotime($startTime) && strtotime($mainStart) < strtotime($endTime)) ||
                        (strtotime($mainEnd) > strtotime($startTime) && strtotime($mainEnd) <= strtotime($endTime))
                    ) {
                        return new WP_Error(
                            'session_overlap',
                            sprintf(
                                /* translators: 1: Doctor's name, 2: Day of the week, 3: Session start time, 4: Session end time */
                                __('A session already exists for %1$s on %2$s from %3$s to %4$s', 'kivicare-clinic-management-system'),
                                $session->doctor_name,
                                $day['id'],
                                $startTime,
                                $endTime
                            ),
                            ['status' => 400]
                        );
                    }
                }
            }
        }
        return true;
    }

    /**
     * Create new doctor session
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */

    public function createDoctorSession(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();
            // Determine clinic ID based on user role
            $current_user_role = $this->kcbase->getLoginUserRole();

            if (isKiviCareProActive()) {
                $selectedClinicId = $params['clinic_id'];  // Keep frontend value
                if ($current_user_role == $this->kcbase->getClinicAdminRole()) {
                    $adminClinicId = KCClinic::getClinicIdOfClinicAdmin();
                    $params['clinic_id'] = $adminClinicId ?: $selectedClinicId;  // Fallback to selected
                } elseif ($current_user_role == $this->kcbase->getReceptionistRole()) {
                    $recepClinicId = KCClinic::getClinicIdOfReceptionist();
                    $params['clinic_id'] = ($recepClinicId && $recepClinicId == $selectedClinicId) ? $recepClinicId : $selectedClinicId;  // Only override if matches selected
                } else {
                    $params['clinic_id'] = $selectedClinicId;
                }
            } else {
                $params['clinic_id'] = KCClinic::kcGetDefaultClinicId();
            }
            // Check for overlapping sessions
            $overlap = $this->checkSessionOverlap($params['doctor_id'], $params['clinic_id'], $params['days']);
            if (is_wp_error($overlap)) {
                return $this->response(null, $overlap->get_error_message(), false, $overlap->get_error_data()['status'] ?? 400);
            }

            // Use the KCClinicSession model's createDoctorSession method
            $result = KCClinicSession::createDoctorSession($params);

            if (is_wp_error($result)) {
                return $this->response(
                    null,
                    $result->get_error_message(),
                    false,
                    $result->get_error_data()['status'] ?? 500
                );
            }

            if (!$result['success']) {
                return $this->response(null, $result['message'], false, 400);
            }

            return $this->response(
                $result['data'],
                $result['message'],
                true,
                201
            );

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('createDoctorSession Error: ' . $e->getMessage());
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

    /**
     * Update doctor session
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function updateDoctorSession(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        try {
            $id = $request->get_param('id');
            $session = KCClinicSession::find($id);

            if (!$session) {
                return $this->response(null, __('Doctor session not found', 'kivicare-clinic-management-system'), false, 404);
            }

            // Check if doctor can only update their own sessions
            $current_user_role = $this->kcbase->getLoginUserRole();
            if ($current_user_role === $this->kcbase->getDoctorRole()) {
                $current_doctor_id = get_current_user_id();
                if ($session->doctorId != $current_doctor_id) {
                    return $this->response(null, __('You can only update your own sessions', 'kivicare-clinic-management-system'), false, 403);
                }
            }

            $params = $request->get_params();

            // If user is a doctor, ensure they can only update sessions for themselves
            if ($current_user_role === $this->kcbase->getDoctorRole()) {
                $params['doctor_id'] = get_current_user_id();
            }

            $wpdb->query('START TRANSACTION');

            try {
                // Delete all existing sessions for this doctor-clinic combination
                KCClinicSession::query()
                    ->where('doctorId', $session->doctorId)
                    ->where('clinicId', $session->clinicId)
                    ->delete();

                // Create new sessions with updated data
                $result = KCClinicSession::createDoctorSession($params);

                if (is_wp_error($result)) {
                    $wpdb->query('ROLLBACK');
                    return $this->response(
                        null,
                        $result->get_error_message(),
                        false,
                        $result->get_error_data()['status'] ?? 500
                    );
                }

                if (!$result['success']) {
                    $wpdb->query('ROLLBACK');
                    return $this->response(null, $result['message'], false, 400);
                }

                $wpdb->query('COMMIT');

                return $this->response(
                    $result['data'],
                    __('Doctor session updated successfully', 'kivicare-clinic-management-system'),
                    true,
                    200
                );

            } catch (\Exception $e) {
                $wpdb->query('ROLLBACK');
                throw $e;
            }

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('updateDoctorSession Error: ' . $e->getMessage());
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

    /**
     * Delete doctor session
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function deleteDoctorSession(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        try {
            $id = $request->get_param('id');

            $wpdb->query('START TRANSACTION');

            $session = KCClinicSession::find($id);
            if (!$session) {
                $wpdb->query('ROLLBACK');
                return $this->response(null, __('Doctor session not found', 'kivicare-clinic-management-system'), false, 404);
            }

            // Check if doctor can only delete their own sessions
            $current_user_role = $this->kcbase->getLoginUserRole();
            if ($current_user_role === $this->kcbase->getDoctorRole()) {
                $current_doctor_id = get_current_user_id();
                if ($session->doctorId != $current_doctor_id) {
                    $wpdb->query('ROLLBACK');
                    return $this->response(null, __('You can only delete your own sessions', 'kivicare-clinic-management-system'), false, 403);
                }
            }

            // Delete all sessions for this doctor-clinic combination
            KCClinicSession::query()
                ->where('doctorId', $session->doctorId)
                ->where('clinicId', $session->clinicId)
                ->delete();

            $wpdb->query('COMMIT');

            return $this->response(null, __('Doctor sessions deleted successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

    /**
     * Bulk delete doctor sessions
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function bulkDeleteDoctorSessions(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        try {
            $ids = $request->get_param('ids');
            $success_count = 0;
            $failed_ids = [];

            $wpdb->query('START TRANSACTION');

            foreach ($ids as $id) {
                $session = KCClinicSession::find($id);
                if ($session) {
                    // Delete all sessions for this doctor-clinic combination
                    $deleted = KCClinicSession::query()
                        ->where('doctorId', $session->doctorId)
                        ->where('clinicId', $session->clinicId)
                        ->delete();

                    if ($deleted) {
                        $success_count++;
                    } else {
                        $failed_ids[] = $id;
                    }
                } else {
                    $failed_ids[] = $id;
                }
            }

            $wpdb->query('COMMIT');

            if ($success_count > 0) {
                return $this->response([
                    'deleted' => $success_count,
                    'failed_ids' => $failed_ids
                ], __('Doctor sessions deleted successfully', 'kivicare-clinic-management-system'));
            } else {
                return $this->response(null, __('No doctor sessions deleted', 'kivicare-clinic-management-system'), false, 400);
            }
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }


    /**
     * Export doctor sessions data
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function exportDoctorSessions(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();
            $current_user_role = $this->kcbase->getLoginUserRole();

            // Use the same query logic as getDoctorSessions but without pagination and including all sessions
            $query = KCClinicSession::query()
                ->setTableAlias('kc_doctor_sessions')
                ->select([
                    'kc_doctor_sessions.*',
                    'kc_doctors.display_name as doctor_name',
                    'kc_doctors.user_email as doctor_email',
                    'doctor_meta.meta_value as doctor_profile_image',
                    'kc_clinics.name as clinic_name',
                    'kc_clinics.email as clinic_email',
                    // Use clinic profile image stored on clinics table (matches other modules)
                    'kc_clinics.profile_image as clinic_profile_image',
                ])
                ->leftJoin(KCDoctor::class, 'kc_doctor_sessions.doctor_id', '=', 'kc_doctors.id', 'kc_doctors')
                ->leftJoin(KCUserMeta::class, function ($query) {
                    $query
                        ->on('doctor_meta.user_id', '=', 'kc_doctors.id')
                        ->on('doctor_meta.meta_key', '=', "'doctor_profile_image'");
                }, null, null, 'doctor_meta')
                ->leftJoin(KCClinic::class, 'kc_doctor_sessions.clinic_id', '=', 'kc_clinics.id', 'kc_clinics')
                ->where(function ($q) {
                    $q->where('kc_doctor_sessions.start_time', '!=', '00:00:00')
                        ->orWhere('kc_doctor_sessions.end_time', '!=', '00:00:00');
                });

            // Filter by doctor role - doctors can only export their own sessions
            if ($current_user_role === $this->kcbase->getDoctorRole()) {
                $current_doctor_id = get_current_user_id();
                $query->where('kc_doctor_sessions.doctor_id', $current_doctor_id);
            }

            // Filter by clinic for receptionist and clinic admin roles
            $clinic_id = null;
            if ($current_user_role === $this->kcbase->getReceptionistRole()) {
                $clinic_id = KCClinic::getClinicIdOfReceptionist();
            } elseif ($current_user_role === $this->kcbase->getClinicAdminRole()) {
                $clinic_id = KCClinic::getClinicIdOfClinicAdmin();
            }

            if ($clinic_id) {
                $query->where('kc_doctor_sessions.clinic_id', '=', $clinic_id);
            }

            // Apply search filter
            if (!empty($params['search'])) {
                $search = esc_sql($params['search']);
                $query->where(function ($q) use ($search) {
                    $q->where('kc_doctors.display_name', 'LIKE', "%{$search}%")
                        ->orWhere('kc_clinics.name', 'LIKE', "%{$search}%")
                        ->orWhere('kc_doctor_sessions.day', 'LIKE', "%{$search}%");
                });
            }

            // Apply status filter
            if (isset($params['status']) && $params['status'] !== '') {
                $query->where('kc_doctor_sessions.status', (int) $params['status']);
            }

            // Apply doctor filter
            if (!empty($params['doctor_id'])) {
                $query->where('kc_doctor_sessions.doctor_id', (int) $params['doctor_id']);
            }

            // Apply clinic filter
            if (!empty($params['clinic_id'])) {
                $query->where('kc_doctor_sessions.clinic_id', (int) $params['clinic_id']);
            }

            // Apply sorting
            $order = !empty($params['order']) && strtolower($params['order']) === 'desc' ? 'desc' : 'asc';
            if (!empty($params['orderby'])) {
                $query->orderBy($params['orderby'], $order);
            } else {
                $query->orderBy('kc_doctor_sessions.id', 'DESC');
            }

            // Get all sessions for export
            $allSessions = $query->get();

            // Group sessions by doctor-clinic combination
            $groupedSessions = [];
            foreach ($allSessions as $session) {
                $key = $session->doctorId . '_' . $session->clinicId;

                if (!isset($groupedSessions[$key])) {
                    $groupedSessions[$key] = [
                        'id' => $session->id,
                        'clinic_id' => $session->clinicId,
                        'clinic_name' => $session->clinic_name,
                        'days' => [],
                        'doctor_id' => $session->doctorId,
                        'doctor_name' => $session->doctor_name,
                        'time_slot' => $session->timeSlot,
                        'morning_sessions' => [],
                        'evening_sessions' => []
                    ];
                }

                $groupedSessions[$key]['days'][] = strtoupper($session->day);

                // Categorize sessions by time
                $startHour = (int) gmdate('H', strtotime($session->startTime));
                $timeRange = substr($session->startTime, 0, 5) . ' - ' . substr($session->endTime, 0, 5);

                if ($startHour < 12) {
                    $groupedSessions[$key]['morning_sessions'][] = $timeRange;
                } else {
                    $groupedSessions[$key]['evening_sessions'][] = $timeRange;
                }
            }

            // Process grouped sessions
            $processedSessions = [];
            $dayOrder = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];

            foreach ($groupedSessions as $group) {
                // Sort days in proper order
                $uniqueDays = array_unique($group['days']);
                $sortedDays = [];
                foreach ($dayOrder as $day) {
                    if (in_array($day, $uniqueDays)) {
                        $sortedDays[] = $day;
                    }
                }

                $group['days'] = implode(', ', $sortedDays);
                $group['morning_session'] = implode(', ', array_unique($group['morning_sessions']));
                $group['evening_session'] = implode(', ', array_unique($group['evening_sessions']));

                // Remove temporary arrays
                unset($group['morning_sessions'], $group['evening_sessions']);

                $processedSessions[] = $group;
            }

            $data = [
                'sessions' => $processedSessions,
                'total' => count($processedSessions)
            ];

            return $this->response($data, __('Doctor sessions export data retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('exportDoctorSessions Error: ' . $e->getMessage());
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

}
