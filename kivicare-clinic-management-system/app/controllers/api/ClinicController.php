<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\models\KCAppointment;
use App\models\KCClinic;
use App\models\KCClinicAdmin;
use App\models\KCClinicSchedule;
use App\models\KCClinicSession;
use App\models\KCReceptionistClinicMapping;
use App\models\KCService;
use App\models\KCServiceDoctorMapping;
use App\models\KCStaticData;
use App\models\KCDoctorClinicMapping;
use App\models\KCPatientClinicMapping;
use App\models\KCUser;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class ClinicController
 * 
 * API Controller for Clinic-related endpoints
 * 
 * @package App\controllers\api
 */
class ClinicController extends KCBaseController
{

    /**
     * @var string The base route for this controller
     */
    protected $route = 'clinics';

    /**
     * Get common arguments used across multiple endpoints
     *
     * @return array
     */
    private function getCommonArgs()
    {
        return [
            'id' => [
                'description' => 'Clinic ID',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
                'default' => KCClinic::kcGetDefaultClinicId()
            ],
            'clinic_name' => [
                'description' => 'Clinic name',
                'type' => 'string',
                'validate_callback' => [$this, 'validateName'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'clinic_email' => [
                'description' => 'Clinic email',
                'type' => 'string',
                'validate_callback' => [$this, 'validateEmail'],
                'sanitize_callback' => 'sanitize_email',
            ],
            'clinic_contact' => [
                'description' => 'Clinic contact number',
                'type' => 'string',
                'validate_callback' => [$this, 'validateContact'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'description' => 'Clinic status (0: Inactive, 1: Active)',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateStatus'],
                'sanitize_callback' => 'absint',
            ]
        ];
    }

    /**
     * Validate ID parameter
     *
     * @param mixed $param
     * @return bool|WP_Error
     */
    public function validateId($param)
    {
        if (!is_numeric($param) || $param <= 0) {
            return new WP_Error('invalid_id', __('Invalid ID parameter', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Validate clinic name
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateName($param)
    {
        if (empty($param)) {
            return new WP_Error('invalid_name', __('Clinic name is required', 'kivicare-clinic-management-system'));
        }

        if (strlen($param) < 2) {
            return new WP_Error('invalid_name', __('Clinic name must be at least 2 characters long', 'kivicare-clinic-management-system'));
        }

        if (strlen($param) > 100) {
            return new WP_Error('invalid_name', __('Clinic name cannot exceed 100 characters', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Validate email format
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateEmail($param)
    {
        if (empty($param)) {
            return new WP_Error('invalid_email', __('Email is required', 'kivicare-clinic-management-system'));
        }

        if (!is_email($param)) {
            return new WP_Error('invalid_email', __('Please enter a valid email address', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Validate contact number
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateContact($param)
    {
        if (empty($param)) {
            return new WP_Error('invalid_contact', __('Contact number is required', 'kivicare-clinic-management-system'));
        }

        if (!preg_match('/^[\d\s\-\+\(\)]+$/', $param)) {
            return new WP_Error('invalid_contact', __('Please enter a valid contact number', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Validate clinic status
     *
     * @param mixed $param
     * @return bool|WP_Error
     */
    public function validateStatus($param)
    {
        if (!in_array(intval($param), [0, 1])) {
            return new WP_Error('invalid_status', __('Status must be 0 (inactive) or 1 (active)', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Validate orderby field
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateOrderBy($param)
    {
        $allowed_fields = [
            'id',
            'name',
            'email',
            'contact',
            'telephone_no',
            'clinic_admin_name',
            'admin',
            'address',
            'city',
            'postal_code',
            'country',
            'status',
            'created_at',
            'specialty'
        ];

        if (!in_array($param, $allowed_fields)) {
            return new WP_Error('invalid_orderby', __('Invalid sort field', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Validate order direction
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateOrder($param)
    {
        if (!in_array(strtolower($param), ['asc', 'desc'])) {
            return new WP_Error('invalid_order', __('Order must be asc or desc', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Validate per page parameter
     *
     * @param mixed $param
     * @return bool|WP_Error
     */
    public function validatePerPage($param)
    {
        // Allow "all" as a valid option
        if (strtolower($param) === 'all') {
            return true;
        }
        $allowed = [5, 10, 25, 50, 100];
        if (!in_array(intval($param), $allowed)) {
            return new WP_Error('invalid_per_page', __('Invalid per page value. Allowed values: 5, 10, 25, 50, 100', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        // Get all clinics
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getClinics'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getListEndpointArgs()
        ]);

        // Get single clinic
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getClinic'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getSingleEndpointArgs()
        ]);

        // Update clinic
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateClinic'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getUpdateEndpointArgs()
        ]);

        // Delete clinic
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteClinic'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getDeleteEndpointArgs()
        ]);

        // Update clinic status
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateClinicStatus'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getStatusUpdateEndpointArgs()
        ]);

        // Resend clinic credentials
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/resend-credentials', [
            'methods' => 'POST',
            'callback' => [$this, 'resendClinicCredentials'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getSingleEndpointArgs()
        ]);

        // Bulk actions
        $this->registerRoute('/' . $this->route . '/bulk/delete', [
            'methods' => 'POST',
            'callback' => [$this, 'bulkDeleteClinics'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getBulkActionEndpointArgs()
        ]);

        $this->registerRoute('/' . $this->route . '/bulk/resend-credentials', [
            'methods' => 'POST',
            'callback' => [$this, 'bulkResendCredentials'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getBulkActionEndpointArgs()
        ]);

        $this->registerRoute('/' . $this->route . '/bulk/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'bulkUpdateStatus'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getBulkStatusUpdateEndpointArgs()
        ]);

        // Export clinics
        $this->registerRoute('/' . $this->route . '/export', [
            'methods' => 'GET',
            'callback' => [$this, 'exportClinics'],
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
            'status' => $this->getCommonArgs()['status'],
            'orderby' => [
                'description' => 'Sort results by specified field',
                'type' => 'string',
                'validate_callback' => [$this, 'validateOrderBy'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'order' => [
                'description' => 'Sort direction (asc or desc)',
                'type' => 'string',
                'validate_callback' => [$this, 'validateOrder'],
                'sanitize_callback' => function ($param) {
                    return strtolower(sanitize_text_field($param));
                },
            ],
            'page' => [
                'description' => 'Current page of results',
                'type' => 'integer',
                'default' => 1,
                'validate_callback' => function ($param) {
                    return is_numeric($param) && $param > 0;
                },
                'sanitize_callback' => 'absint',
            ],
            'perPage' => [
                'description' => 'Number of results per page',
                'type' => 'string',
                'default' => 10,
                'validate_callback' => [$this, 'validatePerPage'],
                'sanitize_callback' => function ($param) {
                    return strtolower($param) === 'all' ? 'all' : absint($param);
                },
            ]
        ];
    }

    /**
     * Get arguments for single item endpoints
     *
     * @return array
     */
    private function getSingleEndpointArgs()
    {
        return [
            'id' => array_merge($this->getCommonArgs()['id'], ['required' => true])
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
            'clinic_name' => array_merge($this->getCommonArgs()['clinic_name'], ['required' => true]),
            'clinic_email' => array_merge($this->getCommonArgs()['clinic_email'], ['required' => true]),
            'clinic_contact' => array_merge($this->getCommonArgs()['clinic_contact'], ['required' => true]),
            'address' => [
                'description' => 'Clinic address',
                'type' => 'string',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return new WP_Error('invalid_address', __('Address is required', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'city' => [
                'description' => 'Clinic city',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'state' => [
                'description' => 'Clinic state/province',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'postal_code' => [
                'description' => 'Clinic postal/ZIP code',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'country' => [
                'description' => 'Clinic country',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'specialties' => [
                'description' => 'Clinic specialties (array of objects with id and label)',
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'description' => 'Specialty ID',
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'label' => [
                            'type' => 'string',
                            'description' => 'Specialty label',
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                    ],
                    'required' => ['id', 'label'],
                ],
                'validate_callback' => function ($param) {
                    if (!is_array($param) || empty($param)) {
                        return new WP_Error('invalid_specialties', __('Specialties are required and must be an array', 'kivicare-clinic-management-system'));
                    }
                    foreach ($param as $specialty) {
                        if (
                            !is_array($specialty) ||
                            empty($specialty['id']) ||
                            empty($specialty['label'])
                        ) {
                            return new WP_Error('invalid_specialty', __('Each specialty must have id and label', 'kivicare-clinic-management-system'));
                        }
                    }
                    return true;
                },
            ],
            'status' => array_merge($this->getCommonArgs()['status'], ['default' => 1]),
            'profile_image' => [
                'description' => 'Clinic profile image ID',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && (!is_numeric($param) || $param <= 0)) {
                        return new WP_Error('invalid_image_id', __('Invalid image ID', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
            'admin_id' => [
                'description' => 'Clinic administrator user ID',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && (!is_numeric($param) || $param <= 0)) {
                        return new WP_Error('invalid_admin_id', __('Invalid admin user ID', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
            'description' => [
                'description' => 'Clinic description',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
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

        // Remove required flag from all fields for update
        foreach ($args as $key => $arg) {
            if (isset($args[$key]['required'])) {
                unset($args[$key]['required']);
            }
        }

        // Add ID parameter as required
        $args['id'] = array_merge($this->getCommonArgs()['id'], ['required' => true]);

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
            'id' => array_merge($this->getCommonArgs()['id'], ['required' => true])
        ];
    }

    /**
     * Get arguments for status update endpoint
     *
     * @return array
     */
    private function getStatusUpdateEndpointArgs()
    {
        return [
            'id' => array_merge($this->getCommonArgs()['id'], ['required' => true]),
            'status' => array_merge($this->getCommonArgs()['status'], ['required' => true])
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
                'description' => 'Array of clinic IDs',
                'type' => 'array',
                'items' => [
                    'type' => 'integer',
                ],
                'required' => true,
                'validate_callback' => function ($param) {
                    if (!is_array($param) || empty($param)) {
                        return new WP_Error('invalid_ids', __('Clinic IDs are required', 'kivicare-clinic-management-system'));
                    }

                    foreach ($param as $id) {
                        if (!is_numeric($id) || intval($id) <= 0) {
                            return new WP_Error('invalid_id', __('Invalid clinic ID in array', 'kivicare-clinic-management-system'));
                        }
                    }
                    return true;
                }
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
                'status' => array_merge($this->getCommonArgs()['status'], ['required' => true])
            ]
        );
    }

    /**
     * Check if user has permission to access clinic endpoints
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkPermission($request)
    {
        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Allow doctors to view clinics
        if (current_user_can('kiviCare_doctor') || current_user_can('kiviCare_receptionist')) {
            return true;
        }

        return $this->checkResourceAccess('clinic', 'view');
    }

    /**
     * Check if user has permission to update a clinic
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkUpdatePermission($request)
    {
        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        $clinicId = $request->get_param('id');
        $clinic = KCClinic::find($clinicId);
        if(!isKiviCareProActive()) {
            if(KCClinic::kcGetDefaultClinicId() !== $clinicId){
                return false;
            }
            if($this->kcbase->getLoginUserRole() == $this->kcbase->getClinicAdminRole()){
                if($clinic->clinicAdminId == get_current_user_id()){
                    return true;
                }
            }else if($this->kcbase->getLoginUserRole() == 'administrator'){
                return true;
            }
            return false;
        }else{
            if($this->kcbase->getLoginUserRole() == $this->kcbase->getClinicAdminRole()){
                if($clinic->clinicAdminId == get_current_user_id()){
                    return true;
                }
            }
        }

        // Check clinic edit permission
        return $this->checkResourceAccess('clinic', 'edit') && isKiviCareProActive();
    }

    /**
     * Check if user has permission to delete a clinic
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkDeletePermission($request)
    {
        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Check clinic delete permission
        return $this->checkResourceAccess('clinic', 'delete') && isKiviCareProActive();
    }

    /**
     * Get all clinics
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getClinics(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Process request parameters
            $params = $request->get_params();
            // Apply pagination with validation
            $page = isset($params['page']) ? (int) $params['page'] : 1;
            $perPageParam = isset($params['perPage']) ? $params['perPage'] : 10;

            // Handle "all" option for perPage
            $showAll = (strtolower($perPageParam) === 'all');
            $perPage = $showAll ? null : (int) $perPageParam;
            // Build the base query for clinics
            $query = KCClinic::table('c')
                ->select([
                    "c.*",
                    "u.display_name as admin_display_name",
                    "u.user_email as admin_email"
                ])
                ->leftJoin(KCUser::class, 'c.clinic_admin_id', '=', 'u.id', 'u');

            // Filter clinics by doctor_id
            if (!empty($params['doctor_id'])) {
                $doctorId = (int) $params['doctor_id'];

                $doctorClinicIds = KCDoctorClinicMapping::query()
                    ->where('doctor_id', $doctorId)
                    ->get()
                    ->pluck('clinicId')
                    ->toArray();

                if (!empty($doctorClinicIds)) {
                    $query->whereIn('c.id', $doctorClinicIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            if(!isKiviCareProActive()){
                $default_clinic_id = KCClinic::kcGetDefaultClinicId();
                $query->where('c.id','=',$default_clinic_id);
            }else{
                if($this->kcbase->getLoginUserRole() == $this->kcbase->getClinicAdminRole()){
                    $query->where('c.clinic_admin_id','=',get_current_user_id());
                }
            }

            // Apply filters
            if (!empty($params['search'])) {
                $query->where(function ($q) use ($params) {
                    $q->where("c.name", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("c.email", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("u.display_name", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("c.address", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("c.city", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("c.country", 'LIKE', '%' . $params['search'] . '%');
                });
            }

            if (isset($params['status']) && $params['status'] !== '') {
                $query->where("c.status", '=', $params['status']);
            }

            if (!empty($params['id'])) {
                $query->where('c.id', '=', $params['id']);
            }
            if (!empty($params['clinicName'])) {
                if (is_numeric($params['clinicName'])) {
                    $query->where('c.id', '=', $params['clinicName']);
                } else {
                    $query->where('c.name', 'LIKE', '%' . $params['clinicName'] . '%');
                }
            }
            if (!empty($params['clinicAddress'])) {
                $query->where(function ($q) use ($params) {
                    $q->where('c.address', 'LIKE', '%' . $params['clinicAddress'] . '%')
                        ->orWhere('c.city', 'LIKE', '%' . $params['clinicAddress'] . '%')
                        ->orWhere('c.state', 'LIKE', '%' . $params['clinicAddress'] . '%')
                        ->orWhere('c.postal_code', 'LIKE', '%' . $params['clinicAddress'] . '%')
                        ->orWhere('c.country', 'LIKE', '%' . $params['clinicAddress'] . '%');
                });
            }
            if ($request->get_param('specialization')) {
                $specialization = $request->get_param('specialization');
                $query->where('c.specialties', 'LIKE', '%' . $specialization . '%');
            }

            // Apply sorting if orderby parameter is provided
            if (!empty($params['orderby'])) {
                $orderby = $params['orderby'];
                $direction = !empty($params['order']) && strtolower($params['order']) === 'desc' ? 'DESC' : 'ASC';

                switch ($orderby) {
                    case 'name':
                        $query->orderBy("c.name", $direction);
                        break;
                    case 'email':
                        $query->orderBy("c.email", $direction);
                        break;
                    case 'clinic_admin_name':
                    case 'admin':
                        // Sort by clinic admin display name from joined users table
                        $query->orderBy("u.display_name", $direction);
                        break;
                    case 'telephone_no':
                    case 'contact':
                        $query->orderBy("c.telephone_no", $direction);
                        break;
                    case 'status':
                        $query->orderBy("c.status", $direction);
                        break;
                    case 'id':
                    default:
                        $query->orderBy("c.id", $direction);
                        break;
                }
            } else {
                // Default sorting by id descending if no sort specified
                $query->orderBy("c.id", 'DESC');
            }

            // Get total count for pagination
            $totalQuery = clone $query;
            $total = $totalQuery->count();

            // Pagination logic
            if (!$showAll) {
                $totalPages = $total > 0 ? ceil($total / $perPage) : 1;
                $page = max(1, min($page, $totalPages));
                $offset = ($page - 1) * $perPage;
                $query->limit($perPage)->offset($offset);
            } else {
                // Show all results
                $page = 1;
                $perPage = $total;
                $totalPages = 1;
            }

            // Get paginated results
            $clinics = $query->get();


            // Prepare the clinic data
            $clinicsData = [];
           
            // Fetch active holidays for all clinics once
            $holidayClinicIds = \App\models\KCClinicSchedule::getActiveHolidaysByModule('clinic');

            foreach ($clinics as $clinic) {

                // Get clinic admin data from joined query
                $clinic_admin_name = $clinic->admin_display_name ?? '';
                $clinic_admin_email = $clinic->admin_email ?? '';
                $clinic_admin_image_url = null;

                if ($clinic->clinicAdminId) {
                    // Try to get basic_data from user meta for profile image and name override
                    $clinic_admin_data = json_decode(get_user_meta($clinic->clinicAdminId, 'basic_data', true) ?? '{}', true);
                    if (!empty($clinic_admin_data)) {
                        if (!empty($clinic_admin_data['first_name']) && !empty($clinic_admin_data['last_name'])) {
                            $clinic_admin_name = $clinic_admin_data['first_name'] . ' ' . $clinic_admin_data['last_name'];
                        }
                        if (!empty($clinic_admin_data['profile_image'])) {
                            $clinic_admin_image_url = wp_get_attachment_url($clinic_admin_data['profile_image']);
                        }
                    }
                }
                // Extract country_code and phone_number from clinic contact
                $splitContact = $this->splitContactNumber($clinic->telephoneNo);

                // Format clinic data
                $clinicData = [
                    'id' => $clinic->id,
                    'name' => $clinic->name,
                    'email' => $clinic->email,
                    'contact' => $clinic->telephoneNo,
                    'country_code' => $splitContact['country_code'],
                    'phone_number' => $splitContact['phone_number'],
                    'address' => $clinic->address,
                    'city' => $clinic->city,
                    'state' => $clinic->state,
                    'postal_code' => $clinic->postalCode,
                    'country' => $clinic->country,
                    'specialty' => $clinic->specialties,
                    'status' => (int) $clinic->status,
                    'is_holiday' => in_array((int)$clinic->id, $holidayClinicIds, true),
                    'clinic_image_url' => $clinic->profileImage ? wp_get_attachment_url($clinic->profileImage) : '',
                    'clinic_admin_image_url' => $clinic_admin_image_url,
                    'clinic_admin_name' => $clinic_admin_name,
                    'clinic_admin_email' => $clinic_admin_email,
                    'created_at' => $clinic->createdAt,
                    'updated_at' => $clinic->updatedAt
                ];

                $clinicsData[] = $clinicData;
            }

            // Return the formatted data with pagination
            $data = [
                'clinics' => $clinicsData,
                'pagination' => [
                    'total' => $total,
                    'perPage' => $perPage,
                    'currentPage' => $page,
                    'lastPage' => $totalPages
                ]
            ];

            return $this->response($data, __('Clinics retrieved successfully', 'kivicare-clinic-management-system'));

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve clinics', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Get single clinic by ID
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getClinic(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $clinicId = $request->get_param('id');

            if (empty($clinicId) || !is_numeric($clinicId)) {
                return $this->response(
                    ['error' => 'Invalid clinic ID'],
                    __('Invalid clinic ID provided', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Build query for single clinic with specialization data
            $clinic = KCClinic::table('c')
                ->select([
                    "c.*",
                    "sd.label as specialization_label",
                    "sd.value as specialization_value"
                ])
                ->leftJoin(KCStaticData::class, 'c.specialties', '=', 'sd.id', 'sd')
                ->where('c.id', '=', $clinicId)
                ->where(function ($q) {
                    $q->whereNull('sd.type')
                        ->orWhere('sd.type', '=', 'specialization');
                })
                ->first();

            if (!$clinic) {
                return $this->response(
                    ['error' => 'Clinic not found'],
                    __('Clinic not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            // Get clinic services
            $clinicServices = [];
            $query = KCServiceDoctorMapping::table('sdm')
                ->select([
                    'sdm.id',
                    'sdm.clinic_id',
                    'sdm.service_id',
                    'sdm.charges',
                    'sdm.telemed_service',
                    'sdm.multiple',
                    'sdm.duration',
                    'sdm.image',
                    's.name as service_name',
                    's.type as service_type',
                ])
                ->leftJoin(KCService::class, 'sdm.service_id', '=', 's.id', 's')
                ->where('sdm.clinic_id', '=', $clinicId)
                ->where('sdm.status', '=', 1);

            if ($this->kcbase->getLoginUserRole() == $this->kcbase->getDoctorRole()) {
                $query->where('sdm.doctor_id', '=', get_current_user_id());
            }

            $serviceMappings = $query->get();

            foreach ($serviceMappings as $service) {
                $serviceId = (int) ($service->serviceId ?? 0);

                if ($serviceId <= 0) {
                    continue;
                }

                $clinicServices[] = [
                    'id' => $serviceId,
                    'mapping_id' => (int) $service->id,
                    'name' => $service->service_name,
                    'type' => $service->service_type,
                    'service_image_url' => $service->image ? wp_get_attachment_url((int) $service->image) : '',
                    'charges' => $service->charges,
                    'telemed_service' => $service->telemedService,
                    'allow_multiple' => $service->multiple,
                    'duration' => $service->duration ? (int) $service->duration : null,
                ];
            }

            // Get admin data using WordPress functions
            $adminData = [];
            if (!empty($clinic->clinicAdminId)) {
                $adminId = $clinic->clinicAdminId;

                // Get WordPress user object
                $adminUser = get_userdata($adminId);
                if ($adminUser) {
                    $user_meta = json_decode(get_user_meta($adminId, 'basic_data', true) ?? '{}', true);

                    // Get user meta using WordPress functions
                    $dob = $user_meta['dob'] ?? '';
                    $firstName = $user_meta['first_name'] ?? '';
                    $lastName = $user_meta['last_name'] ?? '';
                    $gender = $user_meta['gender'] ?? '';
                    $contactNumber = isset($user_meta['mobile_number']) 
                        ? $user_meta['mobile_number'] 
                        : (isset($user_meta['contact_number']) ? $user_meta['contact_number'] : '');
                    $profileImageId = $user_meta['profile_image'] ?? '';

                    $profileImageUrl = '';
                    if ($profileImageId) {
                        $profileImageUrl = wp_get_attachment_url($profileImageId);
                    }

                    // If no profile image, use default placeholder
                    if (empty($profileImageUrl)) {
                        $profileImageUrl = null; // WooCommerce placeholder
                        if (empty($profileImageUrl)) {
                            // Fallback to WordPress default avatar
                            $profileImageUrl = '';
                        }
                        $profileImageId = ''; // No actual image ID for placeholder
                    }

                    $adminData = [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'admin_email' => $adminUser->user_email,
                        'admin_contact_number' => $contactNumber,
                        'dob' => $dob,
                        'gender' => $gender ?: 'male',
                        'clinic_admin_image_url' => $profileImageUrl,
                        'clinic_admin_image_id' => $profileImageId ?: '',
                    ];
                }
            }

            // Handle specialization - get the key/value instead of label
            $specialtyKey = '';
            if (!empty($clinic->specialties)) {
                // If we have specialization data, use the value as key
                $specialtyKey = $clinic->specialization_value ?: $clinic->specialties;
            }

            // Get clinic images
            $clinicImageUrl = '';
            $clinicImageId = '';
            if ($clinic->profileImage) {
                $clinicImageUrl = wp_get_attachment_url($clinic->profileImage);
                $clinicImageId = $clinic->profileImage;
            } elseif ($clinic->clinicLogo) {
                $clinicImageUrl = wp_get_attachment_url($clinic->clinicLogo);
                $clinicImageId = $clinic->clinicLogo;
            }

            // Get total appointments count for this clinic
            $totalAppointments = KCAppointment::query()
                ->where('clinic_id', '=', $clinicId)
                ->count();

            // Get total services count for this clinic
            $serviceCount = is_array($clinicServices) ? count($clinicServices) : 0;

            // Get total doctors count for this clinic
            $doctorCount = 0;
            if ($clinic instanceof KCClinic && method_exists($clinic, 'getDoctors')) {
                $doctors = $clinic->getDoctors();
                // $doctors can be a Collection or an array
                if (is_array($doctors)) {
                    $doctorCount = count($doctors);
                } elseif ($doctors instanceof \Countable) {
                    $doctorCount = $doctors->count();
                }
            }

            // Extract country_code and phone_number from clinic contact
            $splitContact = $this->splitContactNumber($clinic->telephoneNo);

            // Format clinic data according to your structure
            $clinicData = array_merge([
                'clinic_name' => $clinic->name,
                'clinic_email' => $clinic->email,
                'clinic_contact' => $clinic->telephoneNo,
                'country_code' => $splitContact['country_code'],
                'phone_number' => $splitContact['phone_number'],
                'status' => $clinic->status,
                'specialties' => $specialtyKey,
                'address' => $clinic->address,
                'country' => $clinic->country,
                'city' => $clinic->city,
                'postal_code' => $clinic->postalCode,
                'clinic_image' => $clinicImageUrl,
                'clinic_image_id' => $clinicImageId,
                'services' => $clinicServices,
                'service_count' => (int) $serviceCount,
                'doctor_count' => (int) $doctorCount,
                'total_appointments' => (int) $totalAppointments,
                'total_satisfaction' => 0.0, // Default value, Pro plugin will override if active
            ], $adminData);

            // Allow Pro plugin to modify clinic data
            $clinicData = apply_filters('kc_clinic_data', $clinicData, $clinicId);

            return $this->response($clinicData, __('Clinic retrieved successfully', 'kivicare-clinic-management-system'));

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve clinic', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Create new clinic
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function createClinic(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();

            // Check if clinic with same email already exists
            $existingClinic = KCClinic::table('c')
                ->whereIn('c.email', [$params['clinic_email'], $params['admin_email']])
                ->first();

            if ($existingClinic) {
                return $this->response(
                    null,
                    __('A clinic with this email already exists', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Check if admin email already exists (if admin data provided)
            $existing_user = get_user_by('email', $params['user_email']);
            if ($existing_user) {
                return $this->response(
                    null,
                    __('A user with this email already exists', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Clean mobile number
            $params['mobile_number'] = preg_replace('/[^0-9+]/', '', $params['mobile_number']);

            // Prepare clinic data
            $clinicData = [
                'name' => $params['name'],
                'email' => $params['email'],
                'specialties' => is_array($params['specialties']) ? json_encode($params['specialties']) : $params['specialties'],
                'status' => ($params['status'] === 'active') ? 1 : 0,
                'clinic_contact' => $params['clinic_contact'],
                'address' => $params['address'],
                'city' => $params['city'],
                'country' => $params['country'],
                'postal_code' => $params['postal_code'],
                'created_at' => current_time('mysql')
            ];

            // Add profile image if provided
            if (!empty($params['clinic_image_id'])) {
                $clinicData['profile_image'] = (int) $params['clinic_image_id'];
            }

            // Create the clinic
            $clinic = KCClinic::create($clinicData);

            if (!$clinic) {
                return $this->response(null, __('Failed to create clinic', 'kivicare-clinic-management-system'), false, 500);
            }

            $admin_user_id = null;

            // Create clinic admin user if admin data is provided
            $username = sanitize_user($params['first_name'] . '_' . $params['last_name'] . '_' . time());
            $password = wp_generate_password(12);

            $admin_user_id = wp_create_user($username, $password, $params['user_email']);

            if (is_wp_error($admin_user_id)) {
                // Rollback clinic creation
                $clinic->delete();
                return $this->response(
                    null,
                    __('Failed to create admin user: ', 'kivicare-clinic-management-system') . $admin_user_id->get_error_message(),
                    false,
                    500
                );
            }

            // Update clinic with admin ID
            $clinic->update(['clinic_admin_id' => $admin_user_id]);

            // Set user role to clinic admin
            $user = new \WP_User($admin_user_id);
            $user->set_role('clinic_admin');

            // Update user meta
            wp_update_user([
                'ID' => $admin_user_id,
                'display_name' => $params['first_name'] . ' ' . $params['last_name']
            ]);

            // Store admin data
            $admin_data = [
                'first_name' => $params['first_name'],
                'last_name' => $params['last_name'],
                'mobile_number' => $params['mobile_number'],
                'gender' => $params['gender'],
                'dob' => $params['dob'] ?? '',
                'clinic_id' => $clinic->id
            ];

            update_user_meta($admin_user_id, 'first_name', $params['first_name']);
            update_user_meta($admin_user_id, 'last_name', $params['last_name']);
            update_user_meta($admin_user_id, 'basic_data', json_encode($admin_data));
            update_user_meta($admin_user_id, 'clinic_id', $clinic->id);

            // Store admin profile image if provided
            if (!empty($params['admin_image_id'])) {
                update_user_meta($admin_user_id, 'clinic_admin_profile_image', (int) $params['admin_image_id']);
            }

            // Get the new clinic data
            $clinicData = [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'email' => $clinic->email,
                'clinic_contact' => $clinic->clinic_contact,
                'address' => $clinic->address,
                'city' => $clinic->city,
                'postal_code' => $clinic->postal_code,
                'country' => $clinic->country,
                'specialties' => $clinic->specialties,
                'status' => (int) $clinic->status,
                'clinic_image_url' => $clinic->profile_image ? wp_get_attachment_url($clinic->profile_image) : '',
                'created_at' => $clinic->created_at,
                'admin_id' => $admin_user_id
            ];

            return $this->response($clinicData, __('Clinic created successfully', 'kivicare-clinic-management-system'), true, 201);

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to create clinic', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }
    public function updateClinic(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');
            if (KCClinic::kcGetDefaultClinicId() != $id) {
                if (!$this->checkUpdatePermission($request)) {
                    return $this->response(null, __('You do not have permission to update this clinic', 'kivicare-clinic-management-system'), false, 403);
                }
            }
            if ($request->get_param('id') == 0) {
                return $this->response(null, __('Clinic ID is required', 'kivicare-clinic-management-system'), false, 400);
            }
            $params = $request->get_params();
            $clinic = KCClinic::find($id);

            if (!$clinic) {
                return $this->response(null, __('Clinic not found', 'kivicare-clinic-management-system'), false, 404);
            }

            $user = wp_get_current_user();
            if (!in_array('administrator', (array) $user->roles)) {
                if ($clinic->clinicAdminId !== $user->ID) {
                    return $this->response(
                        ['error' => 'Permission denied'],
                        __('You do not have permission to update this clinic.', 'kivicare-clinic-management-system'),
                        false,
                        403
                    );
                }
            }

            // Update clinic admin if exists
            $adminData = [];
            if ($clinic->clinicAdminId) {
                $admin = KCClinicAdmin::find($clinic->clinicAdminId);
                if ($admin) {
                    // Check admin email uniqueness
                    if (isset($params['admin_email']) && $params['admin_email'] !== $admin->email) {
                        $email = sanitize_email($params['admin_email']);

                        // Check in WP Users
                        $user_id = email_exists($email);
                        if ($user_id && $user_id != $admin->id) {
                            return $this->response(
                                null,
                                __('A user with this email already exists', 'kivicare-clinic-management-system'),
                                false,
                                400
                            );
                        }

                        $existingAdmin = KCClinicAdmin::query()
                            ->where('email', $email)
                            ->where('id', '!=', $admin->id)
                            ->first();

                        if ($existingAdmin) {
                            return $this->response(
                                null,
                                __('A user with this email already exists', 'kivicare-clinic-management-system'),
                                false,
                                400
                            );
                        }
                    }

                    // Prepare admin update data
                    $adminData = [
                        'first_name' => $params['first_name'] ?? null,
                        'last_name' => $params['last_name'] ?? null,
                        'user_email' => $params['admin_email'] ?? null,
                        'mobile_number' => $params['admin_contact_number'] ?? null,
                        'dob' => $params['dob'] ?? null,
                        'gender' => $params['gender'] ?? null,
                        'profile_image' => $params['admin_image_id'] ?? null,
                        'country_calling_code_admin' => $params['country_calling_code'] ?? '',
                        'country_code_admin' => $params['country_code'] ?? ''
                    ];

                    // Update admin model properties
                    if (isset($params['first_name']))
                        $admin->firstName = $params['first_name'];
                    if (isset($params['last_name']))
                        $admin->lastName = $params['last_name'];
                    if (isset($params['admin_email']))
                        $admin->email = $params['admin_email'];
                    if (isset($params['admin_contact_number']))
                        $admin->contactNumber = $params['admin_contact_number'];
                    if (isset($params['gender']))
                        $admin->gender = $params['gender'];

                    // Save admin
                    $saveResult = $admin->save();
                    if (is_wp_error($saveResult)) {
                        return $this->response(
                            null,
                            __('Failed to update clinic admin: ', 'kivicare-clinic-management-system') . $saveResult->get_error_message(),
                            false,
                            500
                        );
                    }

                    // Update admin meta fields
                    foreach ($adminData as $key => $value) {
                        if ($value !== null) {
                            // Handle special fields
                            if ($key === 'profile_image') {
                                $admin->updateMeta('clinic_admin_profile_image', $value);
                            } elseif ($key === 'dob') {
                                $admin->updateMeta('dob', $value);
                            }
                        }
                    }

                    // Update basic_data meta
                    $admin->updateMeta('basic_data', json_encode($adminData));
                }
            }

            // Check clinic email uniqueness
            if (isset($params['clinic_email']) && $params['clinic_email'] !== $clinic->email) {
                $email = sanitize_email($params['clinic_email']);

                // Check in WP Users (allow using the clinic admin's own email)
                $user_id = email_exists($email);
                if ($user_id && $user_id != $clinic->clinicAdminId) {
                    return $this->response(
                        null,
                        __('Email already exists', 'kivicare-clinic-management-system'),
                        false,
                        400
                    );
                }

                $existingClinic = KCClinic::query()
                    ->where('email', $email)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingClinic) {
                    return $this->response(
                        null,
                        __('A clinic with this email already exists', 'kivicare-clinic-management-system'),
                        false,
                        400
                    );
                }
            }

            // Map and update clinic fields
            $fieldMappings = [
                'clinic_name' => 'name',
                'clinic_email' => 'email',
                'clinic_contact' => 'telephoneNo',
                'address' => 'address',
                'city' => 'city',
                'state' => 'state',
                'country' => 'country',
                'postal_code' => 'postalCode',
                'specialties' => 'specialties',
                'status' => 'status',
                'clinic_image_id' => 'profileImage',
            ];

            // default clinic status is 1
            if(!isKiviCareProActive()){
                $params['status'] = 1;
            }

            foreach ($fieldMappings as $requestField => $modelField) {
                if ($requestField === 'clinic_image_id') {
                    // Handle clinic image update/removal
                    if (!empty($params['clinic_image_id'])) {
                        $image_id = (int) $params['clinic_image_id'];
                        $clinic->profileImage = $image_id;
                        $clinic->clinicLogo = $image_id; // keep logo in sync
                    } else {
                        // Remove image
                        $clinic->profileImage = null;
                        $clinic->clinicLogo = 0;
                    }
                } else {
                    if (isset($params[$requestField])) {
                        $clinic->{$modelField} = $params[$requestField];
                    }
                }
            }

            // Save clinic

            if (is_wp_error($result = $clinic->save())) {
                return $this->response(null, __('Failed to update clinic', 'kivicare-clinic-management-system'), false, 500);
            }

            // Get updated clinic data
            $updatedClinic = KCClinic::find($id);

            // Format response
            $clinicData = [
                'id' => $updatedClinic->id,
                'name' => $updatedClinic->name,
                'email' => $updatedClinic->email,
                'contact' => $updatedClinic->telephoneNo,
                'address' => $updatedClinic->address,
                'city' => $updatedClinic->city,
                'state' => $updatedClinic->state,
                'postal_code' => $updatedClinic->postalCode,
                'country' => $updatedClinic->country,
                'specialty' => $updatedClinic->specialties,
                'status' => (int) $updatedClinic->status,
                'clinic_image_url' => $updatedClinic->profileImage
                    ? wp_get_attachment_url($updatedClinic->profileImage)
                    : null,
                'description' => $updatedClinic->description,
                'updated_at' => $updatedClinic->updated_at,
                'admin' => $this->getFormattedAdminData($clinic->clinicAdminId)
            ];

            do_action('kcpro_clinic_update',$clinicData);

            return $this->response($clinicData, __('Clinic updated successfully', 'kivicare-clinic-management-system'));

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update clinic', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }

    /**
     * Get formatted admin data
     */
    private function getFormattedAdminData(?int $adminId): ?array
    {
        if (!$adminId)
            return null;

        $admin = KCClinicAdmin::find($adminId);
        if (!$admin)
            return null;

        // Get basic_data meta
        $basicData = json_decode($admin->getMeta('basic_data', true) ?? [], true);

        return [
            'id' => $admin->id,
            'first_name' => $admin->firstName ?? ($basicData['first_name'] ?? ''),
            'last_name' => $admin->lastName ?? ($basicData['last_name'] ?? ''),
            'email' => $admin->email,
            'contact_number' => $admin->contactNumber,
            'clinic_admin_image_url' => $admin->getMeta('clinic_admin_profile_image')
                ? wp_get_attachment_url($admin->getMeta('clinic_admin_profile_image'))
                : '',
            'dob' => $admin->getMeta('dob') ?? ($basicData['dob'] ?? ''),
            'gender' => $admin->gender ?? ($basicData['gender'] ?? '')
        ];
    }
    /**
     * Delete clinic
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function deleteClinic(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');

            // Check if it's the default clinic
            if ($id == KCClinic::kcGetDefaultClinicId()) {
                return $this->response(
                    null,
                    __('Default clinic cannot be deleted', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Check if clinic exists
            $clinic = KCClinic::find($id);

            if (empty($clinic)) {
                return $this->response(
                    null,
                    __('Clinic not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            $user = wp_get_current_user();
            if (!in_array('administrator', (array) $user->roles)) {
                if ($clinic->clinicAdminId !== $user->ID) {
                    return $this->response(
                        ['error' => 'Permission denied'],
                        __('You do not have permission to delete this clinic.', 'kivicare-clinic-management-system'),
                        false,
                        403
                    );
                }
            }

            // Get clinic admin to delete
            $admin_id = $clinic->clinicAdminId;
            if (!empty($admin_id)) {
                // Fetch clinic admin (WordPress user)
                $clinic_admin = KCClinicAdmin::query()
                    ->where('ID', $admin_id) // or whatever the primary key is
                    ->first();
            }

            // Begin transaction
            global $wpdb;
            $wpdb->query('START TRANSACTION');

            try {
                // Delete clinic record
                $deleted = $clinic->delete();

                if (!$deleted) {
                    throw new \Exception(__('Failed to delete clinic', 'kivicare-clinic-management-system'));
                }

                // Delete related records

                // 1. Delete clinic admin mapping if exists
                if (!empty($clinic_admin)) {
                    $clinic_admin->delete();
                }

                // 2. Delete doctor-clinic mappings
                KCDoctorClinicMapping::query()
                    ->where('clinic_id', $id)
                    ->get()
                    ->each(function ($mapping) {
                        $mapping->delete();
                    });

                // 3. Delete receptionist-clinic mappings
                KCReceptionistClinicMapping::query()
                    ->where('clinic_id', $id)
                    ->get()
                    ->each(function ($mapping) {
                        $mapping->delete();
                    });

                // 4. Delete patient-clinic mappings
                KCPatientClinicMapping::query()
                    ->where('clinic_id', $id)
                    ->get()
                    ->each(function ($mapping) {
                        $mapping->delete();
                    });

                // 5. Delete clinic services
                // KCService::query()
                //     ->where('clinicId', $id)
                //     ->get()
                //     ->each(function ($service) {
                //         $service->delete();
                //     });

                // 6. Delete appointments for this clinic
                KCAppointment::query()
                    ->where('clinic_id', $id)
                    ->get()
                    ->each(function ($appointment) {
                        $appointment->delete();
                    });

                // 7. Delete clinic schedules
                KCClinicSchedule::query()
                    ->where('module_type', 'clinic')
                    ->where('module_id', $id)
                    ->get()
                    ->each(function ($schedule) {
                        $schedule->delete();
                    });

                // 8. Delete clinic sessions
                KCClinicSession::query()
                    ->where('clinic_id', $id)
                    ->get()
                    ->each(function ($session) {
                        $session->delete();
                    });

                // Commit transaction
                $wpdb->query('COMMIT');

                do_action('kc_clinic_delete', $id);
                // Return success response
                return $this->response(
                    ['id' => $id],
                    __('Clinic deleted successfully', 'kivicare-clinic-management-system'),
                    true,
                    200
                );

            } catch (\Exception $e) {
                // Rollback on error
                $wpdb->query('ROLLBACK');
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->response(
                null,
                $e->getMessage(),
                false,
                500
            );
        }
    }

    /**
     * Update clinic status
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function updateClinicStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');
            $status = $request->get_param('status');

            // Get clinic by ID using the model
            $clinic = KCClinic::find($id);

            if (empty($clinic)) {
                return $this->response(null, __('Clinic not found', 'kivicare-clinic-management-system'), false, 404);
            }

            // if kivicare pro is not active, set status to 1
            if(!isKiviCareProActive()){
                $status = 1;
            }

            // Update status using the model
            $clinic->status = $status;
            $updated = $clinic->save();

            if ($updated) {
                return $this->response(
                    ['id' => $id, 'status' => $status],
                    __('Clinic status updated successfully', 'kivicare-clinic-management-system'),
                    true,
                    200
                );
            } else {
                return $this->response(
                    null,
                    __('Failed to update clinic status', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

    /**
     * Resend clinic admin credentials
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function resendClinicCredentials(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');

            // Check if clinic exists using model
            $clinic = KCClinic::find($id);

            if (empty($clinic)) {
                return $this->response(null, __('Clinic not found', 'kivicare-clinic-management-system'), false, 404);
            }

            // Get user data
            $user_data = get_userdata($clinic->clinicAdminId);

            if (empty($clinic)) {
                return $this->response(null, __('Clinic admin not found', 'kivicare-clinic-management-system'), false, 404);
            }
            // Generate new password
            $password = wp_generate_password(12, true, false);
            wp_set_password($password, $user_data->ID);

            // Prepare email data
            $email_data = [
                'user_name' => $user_data->user_login,
                'user_email' => $user_data->user_email,
                'user_password' => $password,
                'clinic_name' => $clinic->name,
                'user_role' => 'Clinic Admin'
            ];

            // Use email sender with template
            $email_status = $this->emailSender->sendEmailByTemplate(
                KIVI_CARE_PREFIX . 'resend_user_credential',
                $user_data->user_email,
                $email_data
            );

            if ($email_status) {
                return $this->response(
                    ['id' => $id],
                    __('Clinic credentials resent successfully', 'kivicare-clinic-management-system'),
                    true,
                    200
                );
            } else {
                return $this->response(
                    null,
                    __('Failed to resend clinic credentials', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

    // Method getCredentialEmailTemplate removed as we're now using the email template system

    /**
     * Bulk delete clinics
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function bulkDeleteClinics(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $ids = $request->get_param('ids');

            $success_count = 0;
            $failed_count = 0;
            $failed_ids = [];
            $default_clinic_id = KCClinic::kcGetDefaultClinicId();

            global $wpdb;
            // Begin transaction
            $wpdb->query('START TRANSACTION');

            try {
                foreach ($ids as $id) {
                    // Skip default clinic
                    if ($id == $default_clinic_id) {
                        $failed_count++;
                        $failed_ids[] = [
                            'id' => $id,
                            'reason' => __('Default clinic cannot be deleted', 'kivicare-clinic-management-system')
                        ];
                        continue;
                    }

                    // Check if clinic exists
                    $clinic = KCClinic::find($id);

                    if (empty($clinic)) {
                        $failed_count++;
                        $failed_ids[] = [
                            'id' => $id,
                            'reason' => __('Clinic not found', 'kivicare-clinic-management-system')
                        ];
                        continue;
                    }

                    // Delete clinic record
                    $clinic_deleted = $clinic->delete();

                    if (!$clinic_deleted) {
                        $failed_count++;
                        $failed_ids[] = [
                            'id' => $id,
                            'reason' => __('Failed to delete clinic', 'kivicare-clinic-management-system')
                        ];
                        continue;
                    }

                    // Delete related records

                    // 1. Delete clinic admin mapping
                    $admin_id = $clinic->clinicAdminId;
                    if (!empty($admin_id)) {
                        $clinic_admin = KCClinicAdmin::find($admin_id);
                        if ($clinic_admin) {
                            $clinic_admin->delete();
                        }
                    }

                    // 2. Delete doctor-clinic mappings
                    KCDoctorClinicMapping::query()
                        ->where('clinic_id', $id)
                        ->get()
                        ->each(function ($mapping) {
                            $mapping->delete();
                        });

                    // 3. Delete receptionist-clinic mappings
                    KCReceptionistClinicMapping::query()
                        ->where('clinic_id', $id)
                        ->get()
                        ->each(function ($mapping) {
                            $mapping->delete();
                        });

                    // 4. Delete patient-clinic mappings
                    KCPatientClinicMapping::query()
                        ->where('clinic_id', $id)
                        ->get()
                        ->each(function ($mapping) {
                            $mapping->delete();
                        });

                    // 5. Delete clinic services
                    // KCService::query()
                    //     ->where('clinicId', $id)
                    //     ->get()
                    //     ->each(function ($service) {
                    //         $service->delete();
                    //     });

                    // 6. Delete appointments for this clinic
                    KCAppointment::query()
                        ->where('clinic_id', $id)
                        ->get()
                        ->each(function ($appointment) {
                            $appointment->delete();
                        });

                    // 7. Delete clinic schedules
                    KCClinicSchedule::query()
                        ->where('module_type', 'clinic')
                        ->where('module_id', $id)
                        ->get()
                        ->each(function ($schedule) {
                            $schedule->delete();
                        });

                    // 8. Delete clinic sessions
                    KCClinicSession::query()
                        ->where('clinic_id', $id)
                        ->get()
                        ->each(function ($session) {
                            $session->delete();
                        });

                    $success_count++;
                }

                // Commit transaction
                $wpdb->query('COMMIT');

                if ($success_count > 0) {
                    return $this->response(
                        [
                            'success_count' => $success_count,
                            'failed_count' => $failed_count,
                            'failed_ids' => $failed_ids
                        ],
                        /* translators: %d: number of clinics */
                        sprintf(__('%d clinics deleted successfully', 'kivicare-clinic-management-system'), $success_count),
                        true,
                        200
                    );
                } else {
                    return $this->response(
                        [
                            'success_count' => 0,
                            'failed_count' => $failed_count,
                            'failed_ids' => $failed_ids
                        ],
                        __('Failed to delete clinics', 'kivicare-clinic-management-system'),
                        false,
                        400
                    );
                }
            } catch (\Exception $e) {
                // Rollback on error
                $wpdb->query('ROLLBACK');
                throw $e;
            }
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

    /**
     * Bulk resend credentials
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function bulkResendCredentials(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $ids = $request->get_param('ids');

            $success_count = 0;
            $failed_count = 0;
            $failed_ids = [];

            foreach ($ids as $id) {
                // Check if clinic exists using model
                $clinic = KCClinic::find($id);
                if (empty($clinic)) {
                    $failed_count++;
                    $failed_ids[] = $id;
                    continue;
                }

                // Get user data
                $user_data = get_userdata($clinic->clinicAdminId);

                if (empty($user_data)) {
                    $failed_count++;
                    $failed_ids[] = $id;
                    continue;
                }

                // Generate new password
                $password = wp_generate_password(12, true, false);
                wp_set_password($password, $user_data->ID);

                // Prepare email data
                $email_data = [
                    'user_name' => $user_data->user_login,
                    'user_email' => $user_data->user_email,
                    'user_password' => $password,
                    'clinic_name' => $clinic->name,
                    'user_role' => 'Clinic Admin'
                ];

                // Use email sender with template
                $email_status = $this->emailSender->sendEmailByTemplate(
                    'kivicare_resend_user_credential',
                    $user_data->user_email,
                    $email_data
                );

                if ($email_status) {
                    $success_count++;
                } else {
                    $failed_count++;
                    $failed_ids[] = $id;
                }
            }

            if ($success_count > 0) {
                return $this->response(
                    [
                        'success_count' => $success_count,
                        'failed_count' => $failed_count,
                        'failed_ids' => $failed_ids
                    ],
                    /* translators: %d: number of clinics */
                    sprintf(__('Credentials resent for %d clinics', 'kivicare-clinic-management-system'), $success_count),
                    true,
                    200
                );
            } else {
                return $this->response(
                    [
                        'success_count' => 0,
                        'failed_count' => $failed_count,
                        'failed_ids' => $failed_ids
                    ],
                    __('Failed to resend credentials', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

    /**
     * Bulk update clinic status
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function bulkUpdateStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $ids = $request->get_param('ids');
            $status = $request->get_param('status');

            $success_count = 0;
            $failed_count = 0;
            $failed_ids = [];

            foreach ($ids as $id) {
                // Check if clinic exists
                $clinic = KCClinic::find($id);

                if (empty($clinic)) {
                    $failed_count++;
                    $failed_ids[] = $id;
                    continue;
                }

                // Update status using the model
                $clinic->status = $status;
                $updated = $clinic->save();

                if (!is_wp_error($updated)) {
                    $success_count++;
                } else {
                    $failed_count++;
                    $failed_ids[] = $id;
                }
            }

            if ($success_count > 0) {
                return $this->response(
                    [
                        'success_count' => $success_count,
                        'failed_count' => $failed_count,
                        'failed_ids' => $failed_ids,
                        'status' => $status
                    ],
                    /* translators: %d: number of clinics */
                    sprintf(__('Status updated for %d clinics', 'kivicare-clinic-management-system'), $success_count),
                    true,
                    200
                );
            } else {
                return $this->response(
                    [
                        'success_count' => 0,
                        'failed_count' => $failed_count,
                        'failed_ids' => $failed_ids,
                        'status' => $status
                    ],
                    __('Failed to update clinic status', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
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
                    if (!in_array($param, ['csv', 'xls', 'pdf'])) {
                        return new WP_Error('invalid_format', __('Format must be csv, xls, or pdf', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'search' => [
                'description' => 'Search term to filter results',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'description' => 'Filter by status (0: Inactive, 1: Active)',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && !in_array(intval($param), [0, 1])) {
                        return new WP_Error('invalid_status', __('Status must be 0 (inactive) or 1 (active)', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Export clinics data
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function exportClinics(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Process request parameters
            $params = $request->get_params();
            $status = (isset($params['status']) && $params['status'] !== '' && $params['status'] !== null) ? (int) $params['status'] : null;

            // Build the base query for clinics
            $query = KCClinic::table('c')
                ->select([
                    "c.*"
                ]);

            // Execute query
            $results = $query->get();

            if (empty($results)) {
                return $this->response(
                    ['clinics' => []],
                    __('No clinics found', 'kivicare-clinic-management-system'),
                    true,
                    200
                );
            }

            // Process results for export
            $exportData = [];
            foreach ($results as $clinic) {
                // Get admin data if clinic_admin_id exists
                $adminEmail = '-';
                $profileImageUrl = '-';

                if (!empty($clinic->clinicAdminId)) {
                    $adminUser = get_userdata($clinic->clinicAdminId);
                    if ($adminUser) {
                        $adminEmail = $adminUser->user_email;

                        // Get profile image from user meta
                        $basicData = get_user_meta($clinic->clinicAdminId, 'basic_data', true);
                        if ($basicData) {
                            $basicDataArray = json_decode($basicData, true);
                            if (isset($basicDataArray['profile_image']) && !empty($basicDataArray['profile_image'])) {
                                $profileImageUrl = wp_get_attachment_url($basicDataArray['profile_image']) ?: '-';
                            }
                        }
                    }
                }

                // Build full address
                $addressParts = [];
                if (!empty($clinic->address))
                    $addressParts[] = $clinic->address;
                if (!empty($clinic->city))
                    $addressParts[] = $clinic->city;
                if (!empty($clinic->state))
                    $addressParts[] = $clinic->state;
                if (!empty($clinic->country))
                    $addressParts[] = $clinic->country;
                if (!empty($clinic->postal_code))
                    $addressParts[] = $clinic->postal_code;
                $fullAddress = !empty($addressParts) ? implode(', ', $addressParts) : '-';

                // Get specialties
                $specialties = '';
                if (!empty($clinic->specialties)) {
                    $specialtiesData = is_string($clinic->specialties) ? json_decode($clinic->specialties, true) : $clinic->specialties;
                    if (is_array($specialtiesData)) {
                        $labels = array_column($specialtiesData, 'label');
                        $specialties = implode(', ', array_filter($labels));
                    }
                }

                $exportData[] = [
                    'clinic_id' => $clinic->id,
                    'clinic_name' => $clinic->name,
                    'clinic_email' => $clinic->email,
                    'clinic_contact_no' => $clinic->telephoneNo,
                    'specialties' => $specialties,
                    'status' => $clinic->status == 1 ? 'Active' : 'Inactive',
                    'clinic_admin_id' => $clinic->clinicAdminId,
                    'clinic_admin_image_url' => $profileImageUrl,
                    'clinic_full_address' => $fullAddress,
                    'clinic_admin_email' => $adminEmail
                ];
            }

            return $this->response(
                ['clinics' => $exportData],
                __('Clinics data retrieved successfully', 'kivicare-clinic-management-system'),
                true,
                200
            );

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to export clinics data', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }
}