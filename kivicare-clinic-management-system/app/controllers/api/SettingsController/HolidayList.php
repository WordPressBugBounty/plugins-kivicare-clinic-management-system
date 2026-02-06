<?php

namespace App\controllers\api\SettingsController;

use App\controllers\api\SettingsController;
use App\models\KCClinic;
use App\models\KCDoctor;
use App\models\KCClinicSchedule;
use App\models\KCAppointment;
use App\baseClasses\KCBase;
use App\models\KCUser;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class HolidayList
 * 
 * @package App\controllers\api\SettingsController
 */
class HolidayList extends SettingsController
{
    private static $instance = null;

    protected $route = 'settings/holidays';
    public $kcbase;

    public function __construct()
    {
        parent::__construct();

        global $wpdb;
        $this->db = $wpdb;
        $this->kcbase = KCBase::get_instance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        // Get Holiday List
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getHolidayList'],
            'permission_callback' => [$this, 'checkPermission'],
            // 'args' => $this->getSettingsEndpointArgs()
        ]);
        // Get Data Edit
        $this->registerRoute('/' . $this->route . '/edit', [
            'methods' => 'GET',
            'callback' => [$this, 'dataEdit'],
            'permission_callback' => [$this, 'checkPermission'],
            //  'args' => $this->getSettingsEndpointArgs()
        ]);
        // Data Delete
        $this->registerRoute('/' . $this->route . '/delete', [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'dataDelete'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            //  'args'     => $this->getSettingFieldSchema()['holiday_delete']
        ]);
        // Update Data
        $this->registerRoute('/' . $this->route . '/update', [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'dataUpdate'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            //'args'     => $this->getSettingFieldSchema()['holiday']
        ]);
        // Export Holidays
        $this->registerRoute('/' . $this->route . '/export', [
            'methods' => 'GET',
            'callback' => [$this, 'exportHolidays'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getExportEndpointArgs()
        ]);
    }

    /**
     * Check if user has permission to update settings
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkUpdatePermission($request): bool
    {
        if (!$this->checkCapability('read')) {
            return false;
        }

        return $this->checkResourceAccess('clinic_schedule', 'edit');
    }

    /**
     * Get HolidayList settings
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getHolidayList(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $request_data = $request->get_params();
        $per_page = !empty($request_data['perPage']) && $request_data['perPage'] !== 'all' ? (int) $request_data['perPage'] : 10;
        $page = !empty($request_data['page']) ? (int) $request_data['page'] : 1;
        $offset = ($page - 1) * $per_page;

        $query = KCClinicSchedule::query()->setTableAlias('s');

        // Join Tables
        $query->leftJoin(KCUser::class, 's.module_id', '=', 'd.ID', 'd');
        $query->leftJoin(KCClinic::class, 's.module_id', '=', 'c.id', 'c');

        // Role-based Filtering
        $current_user_id = get_current_user_id();
        $userRole = $this->kcbase->getLoginUserRole();
        $doctorRole = $this->kcbase->getDoctorRole();
        $clinicAdminRole = $this->kcbase->getClinicAdminRole();
        $receptionistRole = $this->kcbase->getReceptionistRole();

        if ($userRole === $doctorRole) {
            // Doctor: See own holidays AND holidays of their assigned Clinics

            // 1. Get clinics this doctor belongs to
            $doctorClinicIds = \App\models\KCDoctorClinicMapping::query()
                ->where('doctor_id', $current_user_id)
                ->get()
                ->pluck('clinicId')
                ->toArray();

            $query->where(function ($q) use ($current_user_id, $doctorClinicIds) {
                // A. Doctor's Own Holidays
                $q->where(function ($sq) use ($current_user_id) {
                    $sq->where('s.module_type', '=', 'doctor')
                        ->where('s.module_id', '=', $current_user_id);
                });

                // B. Clinic Holidays (Read Only view logic handled by frontend/backend checks)
                if (!empty($doctorClinicIds)) {
                    $q->orWhere(function ($sq) use ($doctorClinicIds) {
                        $sq->where('s.module_type', '=', 'clinic')
                            ->whereIn('s.module_id', $doctorClinicIds);
                    });
                }
            });

        } elseif ($userRole === $clinicAdminRole || $userRole === $receptionistRole) {
            // Clinic Admin & Receptionist: See Clinic holidays + Doctors of that Clinic

            // 1. Get Current User's Clinic ID
            $clinicId = KCClinic::getClinicIdForCurrentUser($current_user_id);
            $clinicId = is_array($clinicId) ? ($clinicId[0] ?? 0) : $clinicId;

            // 2. Get IDs of doctors assigned to this clinic
            $clinicDoctors = \App\models\KCDoctorClinicMapping::query()
                ->where('clinic_id', $clinicId)
                ->get()
                ->pluck('doctorId')
                ->toArray();

            // 3. Apply Scope Restriction
            $query->where(function ($q) use ($clinicId, $clinicDoctors) {
                $q->where(function ($sq) use ($clinicId) {
                    $sq->where('s.module_type', 'clinic')->where('s.module_id', $clinicId);
                });

                if (!empty($clinicDoctors)) {
                    $q->orWhere(function ($sq) use ($clinicDoctors) {
                        $sq->where('s.module_type', 'doctor')->whereIn('s.module_id', $clinicDoctors);
                    });
                }
            });

            // 4. Apply User Filters
            if (!empty($request_data['module_type'])) {
                $query->where('s.module_type', '=', $request_data['module_type']);
            }
            if (!empty($request_data['module_type']) && !empty($request_data['module_id'])) {
                $query->where('s.module_id', '=', (int) $request_data['module_id']);
            }

        } else {
            // Administrator
            if (!empty($request_data['module_type'])) {
                $query->where('s.module_type', '=', $request_data['module_type']);
            }
            if (!empty($request_data['module_type']) && !empty($request_data['module_id'])) {
                $query->where('s.module_id', '=', (int) $request_data['module_id']);
            }
        }

        // Search Logic
        if (!empty($request_data['searchTerm'])) {
            $search = trim($request_data['searchTerm']);

            // Find matching Doctor IDs
            $doctor_ids = KCDoctor::query()
                ->select(['ID'])
                ->where('display_name', 'LIKE', "%{$search}%")
                ->get()
                ->pluck('id')
                ->toArray();

            // Find matching Clinic IDs
            $clinic_ids = KCClinic::query()
                ->select(['id'])
                ->where('name', 'LIKE', "%{$search}%")
                ->get()
                ->pluck('id')
                ->toArray();

            // Build the Where Clause
            $query->where(function ($q) use ($search, $doctor_ids, $clinic_ids) {
                // Search by Date
                $q->where('s.start_date', 'LIKE', "%{$search}%")
                    ->orWhere('s.end_date', 'LIKE', "%{$search}%")
                    ->orWhere('s.module_type', 'LIKE', "%{$search}%");

                // Search by Doctor Name (via IDs)
                if (!empty($doctor_ids)) {
                    $q->orWhere(function ($subQ) use ($doctor_ids) {
                        $subQ->where('s.module_type', '=', 'doctor')
                            ->whereIn('s.module_id', $doctor_ids);
                    });
                }

                // Search by Clinic Name (via IDs)
                if (!empty($clinic_ids)) {
                    $q->orWhere(function ($subQ) use ($clinic_ids) {
                        $subQ->where('s.module_type', '=', 'clinic')
                            ->whereIn('s.module_id', $clinic_ids);
                    });
                }
            });
        }

        // Count Total Rows
        $countQuery = clone $query;
        $total_rows = $countQuery->count();

        // Sorting Logic
        $sort_by = $request_data['orderby'] ?? 'id';
        $sort_order = strtoupper($request_data['order'] ?? 'DESC');
        if (!in_array($sort_order, ['ASC', 'DESC']))
            $sort_order = 'DESC';

        if ($sort_by === 'name') {
            $query->orderBy("(CASE WHEN s.module_type = 'doctor' THEN d.display_name ELSE c.name END)", $sort_order);
        } elseif ($sort_by === 'module_type_label') {
            $query->orderBy('s.module_type', $sort_order);
        } elseif (in_array($sort_by, ['start_date', 'end_date', 'status'])) {
            $query->orderBy('s.' . $sort_by, $sort_order);
        } else {
            $query->orderBy('s.id', $sort_order);
        }

        // Pagination
        if ($per_page !== -1) {
            $query->limit($per_page)->offset($offset);
        }

        // Select Data
        $query->select([
            's.*',
            "(CASE WHEN s.module_type = 'doctor' THEN d.display_name ELSE c.name END) as name",
            'c.profile_image'
        ]);

        $holidays = $query->get();

        // Format Response
        $data = [];
        foreach ($holidays as $holiday) {
            $clinicImageUrl = '';
            $doctorImageUrl = '';

            if ($holiday->moduleType === 'clinic') {
                if (!empty($holiday->profile_image)) {
                    $clinicImageUrl = wp_get_attachment_url($holiday->profile_image);
                }
            } elseif ($holiday->moduleType === 'doctor') {
                $profileImageId = get_user_meta($holiday->moduleId, 'doctor_profile_image', true);
                if ($profileImageId) {
                    $doctorImageUrl = wp_get_attachment_url($profileImageId);
                }
            }

            $tempData = [
                'id' => $holiday->id,
                'module_type_label' => $holiday->moduleType,
                'module_id' => $holiday->moduleId,
                'description' => $holiday->description,
                'start_date' => $holiday->startDate,
                'end_date' => $holiday->endDate,
                'status' => $holiday->status,
                'name' => $holiday->name
            ];

            if ($holiday->moduleType === 'clinic') {
                $tempData['clinic_image_url'] = $clinicImageUrl;
            } elseif ($holiday->moduleType === 'doctor') {
                $tempData['doctor_image_url'] = $doctorImageUrl;
            }

            $data[] = $tempData;
        }

        $totalPages = $total_rows > 0 && $per_page !== -1 ? ceil($total_rows / $per_page) : 1;

        return $this->response(
            [
                'data' => $data,
                'pagination' => [
                    'total' => (int) $total_rows,
                    'perPage' => (int) $per_page,
                    'currentPage' => (int) $page,
                    'lastPage' => (int) $totalPages,
                ]
            ],
            esc_html__('Holiday retrieved successfully.', 'kivicare-clinic-management-system'),
            true
        );
    }

    /**
     * Data Delete
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function dataDelete(WP_REST_Request $request): WP_REST_Response
    {
        $request_data = $request->get_json_params();
        $id = isset($request_data['id']) ? (int) $request_data['id'] : 0;

        if ($id <= 0) {
            return $this->response(null, esc_html__('Invalid holiday ID.', 'kivicare-clinic-management-system'), false);
        }

        $clinic_schedule = KCClinicSchedule::find($id);

        if (!$clinic_schedule) {
            return $this->response(null, esc_html__('Holiday not found.', 'kivicare-clinic-management-system'), false);
        }

        // Doctor Permission
        $userRole = $this->kcbase->getLoginUserRole();
        $doctorRole = $this->kcbase->getDoctorRole();

        if ($userRole === $doctorRole) {
            // Cannot delete clinic holidays
            if ($clinic_schedule->moduleType === 'clinic') {
                return $this->response(null, esc_html__('You do not have permission to delete clinic holidays.', 'kivicare-clinic-management-system'), false, 403);
            }
            // Cannot delete other doctors' holidays
            if ($clinic_schedule->moduleType === 'doctor' && (int) $clinic_schedule->moduleId !== get_current_user_id()) {
                return $this->response(null, esc_html__('You do not have permission to delete this holiday.', 'kivicare-clinic-management-system'), false, 403);
            }
        }

        try {
            $clinic_schedule->delete();
            return $this->response(null, esc_html__('Holiday deleted successfully.', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                esc_html__('Failed to delete holiday.', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Update Data
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function dataUpdate(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $request_data = $request->get_json_params();
            $userRole = $this->kcbase->getLoginUserRole();
            $doctorRole = $this->kcbase->getDoctorRole();
            $currentUserId = get_current_user_id();

            // Helper to safely get ID from object or string
            $getVal = function ($val) {
                return is_array($val) ? ($val['id'] ?? '') : $val;
            };

            $moduleType = $getVal($request_data['module_type']);
            $moduleId = (int) $getVal($request_data['module_id']);

            // Fetch existing record ONCE
            $existing_holiday = null;
            if (isset($request_data['id'])) {
                $existing_holiday = KCClinicSchedule::find((int) $request_data['id']);
                if (!$existing_holiday) {
                    return $this->response(null, __('Holiday not found.', 'kivicare-clinic-management-system'), false);
                }
            }

            // Doctor Permission
            if ($userRole === $doctorRole) {
                // Check Existing Record (if editing)
                if ($existing_holiday) {
                    if ($existing_holiday->moduleType === 'clinic') {
                        return $this->response(null, esc_html__('You do not have permission to edit clinic holidays.', 'kivicare-clinic-management-system'), false, 403);
                    }
                    if ($existing_holiday->moduleType === 'doctor' && (int) $existing_holiday->moduleId !== $currentUserId) {
                        return $this->response(null, esc_html__('You do not have permission to edit this holiday.', 'kivicare-clinic-management-system'), false, 403);
                    }
                }

                // Check Incoming Data (prevent creating/updating TO restricted values)
                if ($moduleType === 'clinic') {
                    return $this->response(null, esc_html__('You do not have permission to create/update clinic holidays.', 'kivicare-clinic-management-system'), false, 403);
                }
                if ($moduleId !== $currentUserId) {
                    return $this->response(null, esc_html__('You cannot create holidays for other users.', 'kivicare-clinic-management-system'), false, 403);
                }
            }

            $status = true;
            $message = '';

            $temp = [
                'moduleType' => $moduleType,
                'startDate' => gmdate('Y-m-d', strtotime($request_data['scheduleDate']['start'])),
                'endDate' => gmdate('Y-m-d', strtotime($request_data['scheduleDate']['end'])),
                'moduleId' => $moduleId,
                'description' => !empty($request_data['description']) ? $request_data['description'] : '',
                'status' => 1
            ];

            $data = [
                'start_date' => $temp['startDate'],
                'end_date' => $temp['endDate']
            ];

            if ($temp['moduleType'] === 'doctor') {
                $data['doctor_id'] = $temp['moduleId'];
            } else {
                $data['clinic_id'] = $temp['moduleId'];
            }

            // Check if holiday already exists
            $exclude_id = isset($request_data['id']) ? (int) $request_data['id'] : null;
            $holiday_exists = $this->checkHolidayExists($temp, $exclude_id);

            if ($holiday_exists) {
                $entity_type = $temp['moduleType'] === 'doctor' ? 'Doctor' : 'Clinic';
                return $this->response(
                    null,
                    sprintf(
                        /* translators: %s: entity type (Doctor or Clinic) */
                        __('%s already has a holiday scheduled for this period.', 'kivicare-clinic-management-system'),
                        $entity_type
                    ),
                    false
                );
            }

            $this->kcCancelAppointments($data);

            if (!$existing_holiday) {
                // Create new
                $temp['createdAt'] = current_time('Y-m-d H:i:s');
                KCClinicSchedule::create($temp);
                $message = sprintf(
                    /* translators: %s: entity type (Doctor or Clinic) */
                    __('%s holiday scheduled added successfully.', 'kivicare-clinic-management-system'),
                    ($temp['moduleType'] === 'doctor' ? 'Doctor' : 'Clinic')
                );
            } else {
                // Update existing (Using the already fetched object)
                foreach ($temp as $key => $value) {
                    $existing_holiday->$key = $value;
                }
                $existing_holiday->save();
                $message = sprintf(
                    /* translators: %s: entity type (Doctor or Clinic) */
                    __('%s holiday schedule updated successfully.', 'kivicare-clinic-management-system'),
                    ($temp['moduleType'] === 'doctor' ? 'Doctor' : 'Clinic')
                );
            }

            return $this->response(null, $message, $status);
        } catch (\Exception $e) {
            return $this->response(['error' => $e->getMessage()], __('Holiday operation failed.', 'kivicare-clinic-management-system'), false, 500);
        }
    }

    /**
     * Check if holiday already exists for the given module and date range
     * Exclude the current holiday ID when updating
     */
    private function checkHolidayExists($holiday_data, $exclude_id = null)
    {
        $query = KCClinicSchedule::query()
            ->where('module_type', '=', $holiday_data['moduleType'])
            ->where('module_id', '=', $holiday_data['moduleId'])
            ->where(function ($query) use ($holiday_data) {
                $query->where(function ($q) use ($holiday_data) {
                    $q->where('start_date', '<=', $holiday_data['endDate'])
                        ->where('end_date', '>=', $holiday_data['startDate']);
                });
            });

        // Exclude current holiday ID when updating
        if ($exclude_id !== null) {
            $query->where('id', '!=', $exclude_id);
        }

        return $query->count() > 0;
    }

    function kcCancelAppointments($data)
    {
        $appointments = KCAppointment::query()
            ->where('appointment_start_date', '>=', $data['start_date'])
            ->where('appointment_start_date', '<=', $data['end_date'])
            ->where(function ($query) use ($data) {
                if (isset($data['doctor_id'])) {
                    $query->where('doctor_id', '=', $data['doctor_id']);
                }
                if (isset($data['clinic_id'])) {
                    $query->where('clinic_id', '=', $data['clinic_id']);
                }
            })
            ->where('status', '=', '1')
            ->get();

        foreach ($appointments as $appointment) {
            $appointment->status = 0;
            $appointment->save();
        }
    }

    /**
     * Get arguments for the export endpoint
     *
     * @return array
     */
    private function getExportEndpointArgs()
    {
        return [
            'format' => [
                'description' => 'Export format (csv, xls, pdf)',
                'type' => 'string',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (!in_array($param, ['csv', 'xls'])) {
                        return new WP_Error('invalid_format', __('Format must be csv, xls', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'searchTerm' => [
                'description' => 'Search term to filter results',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'module_type' => [
                'description' => 'Filter by module type (doctor, clinic)',
                'type' => 'string',
                'validate_callback' => function ($param) {
                    if (!empty($param) && !in_array($param, ['doctor', 'clinic'])) {
                        return new WP_Error('invalid_module_type', __('Module type must be doctor or clinic', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'module_id' => [
                'description' => 'Filter by module ID',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && (!is_numeric($param) || $param <= 0)) {
                        return new WP_Error('invalid_module_id', __('Invalid module ID', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Export holidays data
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function exportHolidays(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        try {
            $request_data = $request->get_params();

            $query = KCClinicSchedule::query()->setTableAlias('s');

            // Join Tables
            $query->leftJoin('users', 's.module_id', '=', 'd.ID', 'd');
            $query->leftJoin(KCClinic::class, 's.module_id', '=', 'c.id', 'c');

            // Role-based Filtering (reusing logic from getHolidayList)
            $current_user_id = get_current_user_id();
            $userRole = $this->kcbase->getLoginUserRole();
            $doctorRole = $this->kcbase->getDoctorRole();
            $clinicAdminRole = $this->kcbase->getClinicAdminRole();
            $receptionistRole = $this->kcbase->getReceptionistRole();

            if ($userRole === $doctorRole) {
                // Doctor: See own holidays AND holidays of their assigned Clinics
                $doctorClinicIds = \App\models\KCDoctorClinicMapping::query()
                    ->where('doctor_id', $current_user_id)
                    ->get()
                    ->pluck('clinicId')
                    ->toArray();

                $query->where(function ($q) use ($current_user_id, $doctorClinicIds) {
                    // A. Doctor's Own Holidays
                    $q->where(function ($sq) use ($current_user_id) {
                        $sq->where('s.module_type', '=', 'doctor')
                            ->where('s.module_id', '=', $current_user_id);
                    });

                    // B. Clinic Holidays
                    if (!empty($doctorClinicIds)) {
                        $q->orWhere(function ($sq) use ($doctorClinicIds) {
                            $sq->where('s.module_type', '=', 'clinic')
                                ->whereIn('s.module_id', $doctorClinicIds);
                        });
                    }
                });

            } elseif ($userRole === $clinicAdminRole || $userRole === $receptionistRole) {
                // Clinic Admin & Receptionist: See Clinic holidays + Doctors of that Clinic
                $clinicId = KCClinic::getClinicIdForCurrentUser($current_user_id);
                $clinicId = is_array($clinicId) ? ($clinicId[0] ?? 0) : $clinicId;

                $clinicDoctors = \App\models\KCDoctorClinicMapping::query()
                    ->where('clinic_id', $clinicId)
                    ->get()
                    ->pluck('doctorId')
                    ->toArray();

                $query->where(function ($q) use ($clinicId, $clinicDoctors) {
                    $q->where(function ($sq) use ($clinicId) {
                        $sq->where('s.module_type', 'clinic')->where('s.module_id', $clinicId);
                    });

                    if (!empty($clinicDoctors)) {
                        $q->orWhere(function ($sq) use ($clinicDoctors) {
                            $sq->where('s.module_type', 'doctor')->whereIn('s.module_id', $clinicDoctors);
                        });
                    }
                });

                // Apply User Filters
                if (!empty($request_data['module_type'])) {
                    $query->where('s.module_type', '=', $request_data['module_type']);
                }
                if (!empty($request_data['module_type']) && !empty($request_data['module_id'])) {
                    $query->where('s.module_id', '=', (int) $request_data['module_id']);
                }

            } else {
                // Administrator
                if (!empty($request_data['module_type'])) {
                    $query->where('s.module_type', '=', $request_data['module_type']);
                }
                if (!empty($request_data['module_type']) && !empty($request_data['module_id'])) {
                    $query->where('s.module_id', '=', (int) $request_data['module_id']);
                }
            }

            // Search Logic
            if (!empty($request_data['searchTerm'])) {
                $search = trim($request_data['searchTerm']);

                // Find matching Doctor IDs
                $doctor_ids = KCDoctor::query()
                    ->select(['ID'])
                    ->where('display_name', 'LIKE', "%{$search}%")
                    ->get()
                    ->pluck('id')
                    ->toArray();

                // Find matching Clinic IDs
                $clinic_ids = KCClinic::query()
                    ->select(['id'])
                    ->where('name', 'LIKE', "%{$search}%")
                    ->get()
                    ->pluck('id')
                    ->toArray();

                // Build the Where Clause
                $query->where(function ($q) use ($search, $doctor_ids, $clinic_ids) {
                    // Search by Date
                    $q->where('s.start_date', 'LIKE', "%{$search}%")
                        ->orWhere('s.end_date', 'LIKE', "%{$search}%")
                        ->orWhere('s.module_type', 'LIKE', "%{$search}%");

                    // Search by Doctor Name (via IDs)
                    if (!empty($doctor_ids)) {
                        $q->orWhere(function ($subQ) use ($doctor_ids) {
                            $subQ->where('s.module_type', '=', 'doctor')
                                ->whereIn('s.module_id', $doctor_ids);
                        });
                    }

                    // Search by Clinic Name (via IDs)
                    if (!empty($clinic_ids)) {
                        $q->orWhere(function ($subQ) use ($clinic_ids) {
                            $subQ->where('s.module_type', '=', 'clinic')
                                ->whereIn('s.module_id', $clinic_ids);
                        });
                    }
                });
            }

            // Select Data
            $query->select([
                's.*',
                "(CASE WHEN s.module_type = 'doctor' THEN d.display_name ELSE c.name END) as name"
            ]);

            // Order by ID
            $query->orderBy('s.id', 'DESC');

            $holidays = $query->get();

            if ($holidays->isEmpty()) {
                return $this->response(
                    ['holidays' => []],
                    __('No holidays found to export', 'kivicare-clinic-management-system'),
                    true
                );
            }

            // Format Response
            $exportData = [];
            foreach ($holidays as $holiday) {
                $exportData[] = [
                    'id' => $holiday->id,
                    'module_type' => ucfirst($holiday->moduleType),
                    'name' => $holiday->name,
                    'description' => $holiday->description ?? '',
                    'start_date' => $holiday->startDate,
                    'end_date' => $holiday->endDate,
                    'status' => $holiday->status == 1 ? 'Active' : 'Inactive',
                ];
            }

            return $this->response(
                ['holidays' => $exportData],
                __('Holidays data retrieved successfully for export', 'kivicare-clinic-management-system'),
                true
            );

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to export holidays data', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }
}
