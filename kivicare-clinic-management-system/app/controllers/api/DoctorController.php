<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\models\KCDoctor;
use App\models\KCDoctorClinicMapping;
use App\models\KCAppointment;
use App\models\KCClinic;
use App\models\KCCustomFieldData;
use App\models\KCUserMeta;
use App\models\KCServiceDoctorMapping;
use App\models\KCClinicSession;
use App\models\KCClinicSchedule;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class DoctorController
 * 
 * API Controller for Doctor-related endpoints
 * 
 * @package App\controllers\api
 */
class DoctorController extends KCBaseController
{

    /**
     * @var string The base route for this controller
     */
    protected $route = 'doctors';

    /**
     * Get common arguments used across multiple endpoints
     *
     * @return array
     */
    private function getCommonArgs()
    {
        return [
            'id' => [
                'description' => 'Doctor ID',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
            'first_name' => [
                'description' => 'Doctor first name',
                'type' => 'string',
                'validate_callback' => [$this, 'validateName'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'last_name' => [
                'description' => 'Doctor last name',
                'type' => 'string',
                'validate_callback' => [$this, 'validateName'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'email' => [
                'description' => 'Doctor email',
                'type' => 'string',
                'validate_callback' => [$this, 'validateEmail'],
                'sanitize_callback' => 'sanitize_email',
            ],
            'mobile_number' => [
                'description' => 'Doctor mobile number',
                'type' => 'string',
                'validate_callback' => [$this, 'validateMobileNumber'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'description' => 'Doctor status (0: Inactive, 1: Active)',
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
     * Validate name fields
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateName($param)
    {
        if (empty($param)) {
            return new WP_Error('invalid_name', __('Name is required', 'kivicare-clinic-management-system'));
        }

        if (strlen($param) < 2) {
            return new WP_Error('invalid_name', __('Name must be at least 2 characters long', 'kivicare-clinic-management-system'));
        }

        if (strlen($param) > 50) {
            return new WP_Error('invalid_name', __('Name cannot exceed 50 characters', 'kivicare-clinic-management-system'));
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
     * Validate mobile number
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateMobileNumber($param)
    {
        if (empty($param)) {
            return new WP_Error('invalid_mobile', __('Mobile number is required', 'kivicare-clinic-management-system'));
        }

        if (!preg_match('/^[\d\s\-\+\(\)]+$/', $param)) {
            return new WP_Error('invalid_mobile', __('Please enter a valid mobile number', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Validate doctor status
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
     * Validate gender
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateGender($param)
    {
        $allowed_genders = ['male', 'female', 'other'];
        if (!in_array(strtolower($param), $allowed_genders)) {
            return new WP_Error('invalid_gender', __('Gender must be male, female, or other', 'kivicare-clinic-management-system'));
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
            'first_name',
            'last_name',
            'email',
            'mobile_number',
            'gender',
            'dob',
            'status',
            'created_at'
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
        // Get all doctors
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getDoctors'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getListEndpointArgs()
        ]);

        // Get single doctor
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getDoctor'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getSingleEndpointArgs()
        ]);

        // Create doctor
        $this->registerRoute('/' . $this->route, [
            'methods' => 'POST',
            'callback' => [$this, 'createDoctor'],
            'permission_callback' => [$this, 'checkCreatePermission'],
            'args' => $this->getCreateEndpointArgs()
        ]);

        // Update doctor
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateDoctor'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getUpdateEndpointArgs()
        ]);


        // Delete doctor
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteDoctor'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getDeleteEndpointArgs()
        ]);

        // Update doctor status
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateDoctorStatus'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getStatusUpdateEndpointArgs()
        ]);

        // Resend doctor credentials
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/resend-credentials', [
            'methods' => 'POST',
            'callback' => [$this, 'resendDoctorCredentials'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getSingleEndpointArgs()
        ]);

        // Bulk delete doctors
        $this->registerRoute('/' . $this->route . '/bulk/delete', [
            'methods' => 'POST',
            'callback' => [$this, 'bulkDeleteDoctors'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getBulkActionEndpointArgs()
        ]);

        // Bulk resend credentials
        $this->registerRoute('/' . $this->route . '/bulk/resend-credentials', [
            'methods' => 'POST',
            'callback' => [$this, 'bulkResendCredentials'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getBulkActionEndpointArgs()
        ]);

        // Bulk update doctor status
        $this->registerRoute('/' . $this->route . '/bulk/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'bulkUpdateDoctorStatus'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getBulkStatusUpdateEndpointArgs()
        ]);

        // Export doctors data
        $this->registerRoute('/' . $this->route . '/export', [
            'methods' => 'GET',
            'callback' => [$this, 'exportDoctors'],
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
            'clinic_id' => [
                'description' => 'Filter by clinic ID',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
            'gender' => [
                'description' => 'Filter by gender',
                'type' => 'string',
                'validate_callback' => [$this, 'validateGender'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
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
     * Get arguments for paginated endpoints
     *
     * @return array
     */
    private function getPaginatedEndpointArgs()
    {
        return [
            'id' => array_merge($this->getCommonArgs()['id'], ['required' => true]),
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
     * Get arguments for the create endpoint
     *
     * @return array
     */
    private function getCreateEndpointArgs()
    {
        return [
            'first_name' => array_merge($this->getCommonArgs()['first_name'], ['required' => true]),
            'last_name' => array_merge($this->getCommonArgs()['last_name'], ['required' => true]),
            'email' => array_merge($this->getCommonArgs()['email'], ['required' => true]),
            'mobile_number' => array_merge($this->getCommonArgs()['mobile_number'], ['required' => true]),
            'gender' => [
                'description' => 'Doctor gender',
                'type' => 'string',
                'validate_callback' => [$this, 'validateGender'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'dob' => [
                'description' => 'Doctor date of birth (YYYY-MM-DD)',
                'type' => 'string',
                'validate_callback' => function ($param) {
                    if (!empty($param) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $param)) {
                        return new WP_Error('invalid_date', __('Date of birth must be in YYYY-MM-DD format', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'blood_group' => [
                'description' => 'Doctor blood group',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'address' => [
                'description' => 'Doctor address',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'city' => [
                'description' => 'Doctor city',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'postal_code' => [
                'description' => 'Doctor postal/ZIP code',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'country' => [
                'description' => 'Doctor country',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            // Updated specialties field to accept an array
            'specialties' => [
                'description' => 'Doctor specialties',
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'label' => [
                            'type' => 'string',
                            'required' => true
                        ]
                    ]
                ],
                'sanitize_callback' => function ($param) {
                    if (is_string($param)) {
                        // Handle case where it might still be passed as a string
                        return json_decode($param, true) ?: [];
                    }
                    return $param;
                },
            ],
            'qualifications' => [
                'description' => 'Doctor qualifications',
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'degree' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'university' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'year' => [
                            'type' => 'integer',
                            'required' => true
                        ]
                    ]
                ],
                'sanitize_callback' => function ($param) {
                    if (is_string($param)) {
                        // Handle case where it might still be passed as a string
                        return json_decode($param, true) ?: [];
                    }
                    return $param;
                },
            ],
            'experience_years' => [
                'description' => 'Years of experience',
                'type' => 'float',
                'validate_callback' => function ($param) {
                    if (!empty($param) && (!is_numeric($param) || $param < 0)) {
                        return new WP_Error('invalid_experience', __('Experience years must be a positive number', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
            'clinic_id' => [
                'description' => 'Doctor clinic IDs',
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                        ],
                        'value' => [
                            'type' => 'string',
                        ],
                        'label' => [
                            'type' => 'string',
                        ]
                    ]
                ],
                'validate_callback' => function ($param) {
                    if (!is_array($param)) {
                        return new WP_Error('invalid_clinic_id', __('Clinic ID must be an array', 'kivicare-clinic-management-system'));
                    }
                    foreach ($param as $clinic) {
                        if (!is_numeric($clinic['id']) || intval($clinic['id']) <= 0) {
                            return new WP_Error('invalid_clinic_id_value', __('Clinic ID must be a positive integer', 'kivicare-clinic-management-system'));
                        }
                    }
                    return true;
                },
                'sanitize_callback' => function ($param) {
                    if (is_string($param)) {
                        // Handle case where it might still be passed as a string
                        return json_decode($param, true) ?: [];
                    }
                    return $param;
                },
            ],
            'profile_image' => [
                'description' => 'Profile image attachment ID',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && (!is_numeric($param) || $param <= 0)) {
                        return new WP_Error('invalid_image_id', __('Invalid image ID', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
            'status' => array_merge($this->getCommonArgs()['status'], ['default' => 0]),
            'description' => [
                'description' => 'Doctor description/bio',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'signature' => [
                'description' => 'Doctor Signature',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ]
        ];

        return apply_filters('kc_doctor_create_endpoint_args', $args);
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

        // Remove username and password from update (handle separately)
        unset($args['username'], $args['password']);

        // Add ID parameter as required
        $args['id'] = array_merge($this->getCommonArgs()['id'], ['required' => true]);

        return apply_filters('kc_doctor_update_endpoint_args', $args);
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
     * Check if user has permission to access doctor endpoints
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

        $current_user_id = get_current_user_id();
        $current_user_role = $this->kcbase->getLoginUserRole();

        // If it's a doctor role and accessing single doctor endpoint
        if ($current_user_role === $this->kcbase->getDoctorRole()) {
            $doctor_id = $request->get_param('id');
            // Allow doctor to access their own data
            if ($doctor_id && intval($doctor_id) === $current_user_id) {
                return true;
            }

            // Allow doctor to see other doctor data of that clinic
            if ($current_user_role == $this->kcbase->getDoctorRole()) {
                return true;
            }
        }

        return $this->checkResourceAccess('doctor', 'view');
    }

    /**
     * Check if user has permission to create a doctor
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkCreatePermission($request)
    {
        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Check doctor add permission
        return $this->checkResourceAccess('doctor', 'add');
    }

    /**
     * Check if user has permission to update a doctor
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

        $current_user_id = get_current_user_id();
        $current_user_role = $this->kcbase->getLoginUserRole();

        // If it's a doctor role, allow them to update their own profile only
        if ($current_user_role === $this->kcbase->getDoctorRole()) {
            $doctor_id = $request->get_param('id');
            // Allow doctor to update their own data only
            if ($doctor_id && intval($doctor_id) === $current_user_id) {
                return true;
            }
            // Deny access to other doctors' data
            return false;
        }

        return $this->checkResourceAccess('doctor', 'edit');
    }

    /**
     * Check if user has permission to delete a doctor
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

        return $this->checkResourceAccess('doctor', 'delete');
    }

    /**
     * Get all doctors
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getDoctors(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Get query parameters
            $params = $request->get_params();

            // Set defaults
            $page = isset($params['page']) ? (int) $params['page'] : 1;
            $perPageParam = isset($params['perPage']) ? $params['perPage'] : 10;

            // Handle "all" option for perPage
            $showAll = (strtolower($perPageParam) === 'all');
            $perPage = $showAll ? null : (int) $perPageParam;
            $search = isset($params['search']) ? sanitize_text_field($params['search']) : '';
            $clinic = isset($params['clinic_id']) ? (int) $params['clinic_id'] : null;
            $doctor_id = isset($params['id']) ? (int) $params['id'] : null;
            $status = isset($params['status']) ? (int) ($params['status']) : null;
            $specialization = isset($params['specialization']) ? sanitize_text_field($params['specialization']) : '';
            $doctorAddress = isset($params['doctorAddress']) ? sanitize_text_field($params['doctorAddress']) : '';

            // Check if current user is receptionist and filter by their clinic
            $current_user_role = $this->kcbase->getLoginUserRole();
            if ($current_user_role === $this->kcbase->getReceptionistRole()) {
                $clinic = KCClinic::getClinicIdOfReceptionist();
            }
            // Build the base query for doctors
            $query = KCDoctor::table('d')
                ->select([
                    "d.*",
                    "um_first.meta_value as first_name",
                    "um_last.meta_value as last_name",
                    "bd.meta_value as basic_data",
                    "pi.meta_value as profile_image_id",
                    "GROUP_CONCAT(CONCAT('{\"clinic_id\":', c.id, ',\"clinic_name\":\"', REPLACE(REPLACE(c.name, '\"', '\\\"'), '\\\\', '\\\\\\\\'), '\",\"clinic_email\":\"', c.email, '\",\"clinic_contact_number\":\"', IFNULL(c.telephone_no, ''), '\",\"clinic_address\":\"', REPLACE(REPLACE(CONCAT_WS(', ', NULLIF(c.address, ''), NULLIF(c.city, ''), NULLIF(c.state, ''), NULLIF(c.country, ''), NULLIF(c.postal_code, '')), '\"', '\\\"'), '\\\\', '\\\\\\\\'), '\",\"clinic_image_id\":', IFNULL(c.profile_image, 'null'), '}') SEPARATOR '||') as clinic_data",
                    "c.name as clinic_name",
                    "c.email as clinic_email",
                    "c.id as clinic_id",
                    "c.profile_image as clinic_profile_image",
                    "dd.meta_value as doctor_description",
                ])
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'um_first.user_id')
                        ->onRaw("um_first.meta_key = 'first_name'");
                }, null, null, 'um_first')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'um_last.user_id')
                        ->onRaw("um_last.meta_key = 'last_name'");
                }, null, null, 'um_last')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'bd.user_id')
                        ->onRaw("bd.meta_key = 'basic_data'");
                }, null, null, 'bd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'pi.user_id')
                        ->onRaw("pi.meta_key = 'doctor_profile_image'");
                }, null, null, 'pi')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'dd.user_id')
                        ->onRaw("dd.meta_key = 'doctor_description'");
                }, null, null, 'dd');

            // Check if current user is clinic admin and filter by their clinic
            $current_user_role = $this->kcbase->getLoginUserRole();
            if ($current_user_role === $this->kcbase->getClinicAdminRole() && empty($clinic)) {
                $clinic = KCClinic::getClinicIdOfClinicAdmin();
            }

            // If you need to filter by clinic
            if ($clinic) {
                $query->join(KCDoctorClinicMapping::class, 'd.ID', '=', 'dcm.doctor_id', 'dcm')
                    ->join(KCClinic::class, 'dcm.clinic_id', '=', 'c.id', 'c')
                    ->where('c.id', '=', $clinic)
                    ->groupBy('d.ID');
            } else {
                // Optional left join if you want clinic info for all doctors
                $query->leftJoin(KCDoctorClinicMapping::class, 'd.ID', '=', 'dcm.doctor_id', 'dcm')
                    ->leftJoin(KCClinic::class, 'dcm.clinic_id', '=', 'c.id', 'c')
                    ->groupBy('d.ID');
            }
            // Apply filters
            if (!empty($params['search'])) {
                $query->where(function ($q) use ($search) {
                    $q->where("um_first.meta_value", 'LIKE', '%' . $search . '%')
                        ->orWhere("d.display_name", 'LIKE', '%' . $search . '%')
                        ->orWhere("um_last.meta_value", 'LIKE', '%' . $search . '%')
                        ->orWhere("d.user_email", 'LIKE', '%' . $search . '%')
                        ->orWhere("c.email", 'LIKE', '%' . $search . '%')
                        ->orWhere("c.name", 'LIKE', '%' . $search . '%')
                        ->orWhere("bd.meta_value", 'LIKE', '%' . $search . '%')
                        ->orWhere("dd.meta_value", 'LIKE', '%' . $search . '%');
                });
            }

            if (!empty($params['doctorName'])) {
                $doctorName = sanitize_text_field($params['doctorName']);
                $query->where(function ($q) use ($doctorName) {
                    $q->where("um_first.meta_value", 'LIKE', '%' . $doctorName . '%')
                        ->orWhere("um_last.meta_value", 'LIKE', '%' . $doctorName . '%')
                        ->orWhere("d.display_name", 'LIKE', '%' . $doctorName . '%');
                });
            }

            if (!empty($doctor_id) && is_numeric($doctor_id)) {
                $query->where('d.id', '=', (int) $doctor_id);
            }

            if (!empty($specialization)) {
                $query->where("bd.meta_value", 'LIKE', '%' . $specialization . '%');
            }

            if (!empty($doctorAddress)) {
                $query->where(function ($q) use ($doctorAddress) {
                    $q->where("bd.meta_value", 'LIKE', '%' . $doctorAddress . '%');
                });
            }


            if (isset($status) && $status !== '') {
                $query->where("d.user_status", '=', $status);
            }

            if (!empty($params['gender'])) {
                $query->where("d.gender", '=', $params['gender']);
            }

            // Apply sorting
            if (!empty($params['orderby'])) {
                $orderby = $params['orderby'];
                $direction = !empty($params['order']) && strtolower($params['order']) === 'desc' ? 'DESC' : 'ASC';

                switch ($orderby) {
                    case 'name':
                        $query->orderBy("d.display_name", $direction);
                        break;
                    case 'email':
                        $query->orderBy("d.user_email", $direction);
                        break;
                    case 'status':
                        $query->orderBy("d.user_status", $direction);
                        break;
                    case 'id':
                    default:
                        $query->orderBy("d.ID", $direction);
                        break;
                }
            } else {
                $query->orderBy("d.ID", 'DESC');
            }

            $totalQuery = clone $query;
            $totalQuery->removeGroupBy();
            $total = $totalQuery->countDistinct('d.ID');

            $perPage = isset($params['perPage']) ? (int) $params['perPage'] : 10;
            if ($perPage <= 0) {
                $perPage = 10;
            }

            $page = isset($params['page']) ? (int) $params['page'] : 1;
            if ($page <= 0) {
                $page = 1;
            }

            if ($showAll) {
                $perPage = $total > 0 ? $total : 1;
                $page = 1;
            }

            $totalPages = $perPage > 0 ? ceil($total / $perPage) : 1;
            $offset = ($page - 1) * $perPage;

            if (!$showAll) {
                $query->limit($perPage)->offset($offset);
            }

            // Get paginated results
            $doctors = $query->get();

            // Prepare the doctor data
            $doctorsData = [];
            foreach ($doctors as $doctor) {
                $basicData = !empty($doctor->basic_data) ? json_decode($doctor->basic_data, true) : [];

                // Get attachment URL only if attachment ID exists
                $profileImageUrl = !empty($doctor->profile_image_id) ?
                    wp_get_attachment_url($doctor->profile_image_id) : '';


                $clinics = [];
                if (!empty($doctor->clinic_data)) {
                    $clinicStrings = explode('||', $doctor->clinic_data);
                    foreach ($clinicStrings as $clinicStr) {
                        $clinic = json_decode($clinicStr, true);
                        if ($clinic) {
                            $clinic['clinic_image_url'] = !empty($clinic['clinic_image_id']) ? wp_get_attachment_url($clinic['clinic_image_id']) : '';
                            $clinics[] = $clinic;
                        }
                    }
                }
                $doctorData = [
                    'id' => $doctor->id,
                    'name' => $doctor->display_name,
                    'email' => $doctor->email,
                    'mobile_number' => $basicData['mobile_number'] ?? '',
                    'doctor_image_url' => $profileImageUrl,
                    'doctor_image_id' => !empty($doctor->profile_image_id) ?
                        (int) $doctor->profile_image_id : null,
                    'clinics' => $clinics,
                    'gender' => $basicData['gender'] ?? '',
                    'specialties' => $basicData['specialties'] ?? '',
                    'doctor_description' => !empty($doctor->doctor_description) ? sanitize_textarea_field($doctor->doctor_description) : '',
                    'status' => (int) $doctor->status,
                ];

                // Allow pro plugin or other plugins to modify doctor data
                $doctorData = apply_filters('kc_doctor_list_item_data', $doctorData, $doctor, $request);

                $doctorsData[] = $doctorData;
            }

            // Return the formatted data with pagination
            $data = [
                'doctors' => $doctorsData,
                'pagination' => [
                    'total' => $total,
                    'perPage' => $perPage,
                    'currentPage' => $page,
                    'lastPage' => $totalPages,
                ],
            ];

            return $this->response($data, __('Doctors retrieved successfully', 'kivicare-clinic-management-system'));

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve doctors', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Get single doctor by ID
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getDoctor(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Get doctor ID
            $id = $request->get_param('id');

            // Check clinic access for clinic admin and receptionist
            $current_user_role = $this->kcbase->getLoginUserRole();
            $clinic_id = null;
            if ($current_user_role === $this->kcbase->getClinicAdminRole()) {
                $clinic_id = KCClinic::getClinicIdOfClinicAdmin();
            } elseif ($current_user_role === $this->kcbase->getReceptionistRole()) {
                $clinic_id = KCClinic::getClinicIdOfReceptionist();
            }
            if ($clinic_id) {
                $doctorClinicMapping = KCDoctorClinicMapping::table('dcm')
                    ->where('dcm.doctor_id', '=', $id)
                    ->where('dcm.clinic_id', '=', $clinic_id)
                    ->first();

                if (!$doctorClinicMapping) {
                    return $this->response(null, __('Access denied. Doctor not found in your clinic.', 'kivicare-clinic-management-system'), false, 403);
                }
            }

            // Find the doctor
            $doctor = KCDoctor::table('d')
                ->select([
                    "d.*"
                ])
                ->where('d.id', '=', $id)
                ->first();

            if (!$doctor) {
                return $this->response(null, __('Doctor not found', 'kivicare-clinic-management-system'), false, 404);
            }

            // Build optimized query to get doctor data
            $doctorData = KCDoctor::table('d')
                ->select([
                    "d.*",
                    "um_first.meta_value as first_name",
                    "um_last.meta_value as last_name",
                    "bd.meta_value as basic_data",
                    "ds.meta_value as doctor_signature",
                    "dd.meta_value as doctor_description",
                    "pi.meta_value as profile_image_id"
                ])
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'um_first.user_id')
                        ->onRaw("um_first.meta_key = 'first_name'");
                }, null, null, 'um_first')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'um_last.user_id')
                        ->onRaw("um_last.meta_key = 'last_name'");
                }, null, null, 'um_last')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'bd.user_id')
                        ->onRaw("bd.meta_key = 'basic_data'");
                }, null, null, 'bd')
                ->leftJoin(KCUserMeta::class, function($join) {
                    $join->on('d.ID', '=', 'dd.user_id')
                        ->onRaw("dd.meta_key = 'doctor_description'");
                }, null, null, 'dd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'ds.user_id')
                        ->onRaw("ds.meta_key = 'doctor_signature'");
                }, null, null, 'ds')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'pi.user_id')
                        ->onRaw("pi.meta_key = 'doctor_profile_image'");
                }, null, null, 'pi')
                ->where('d.ID', '=', $id)
                ->first();

            // Get all clinics for this doctor
            $doctorClinics = KCDoctorClinicMapping::table('dcm')
                ->select([
                    "c.id as clinicId",
                    "c.name as clinic_name",
                    "c.email as clinic_email",
                    "c.profile_image as clinic_profile_image"
                ])
                ->leftJoin(KCClinic::class, 'dcm.clinic_id', '=', 'c.id', 'c')
                ->where('dcm.doctor_id', '=', $id)
                ->get();
            // Return error if doctor not found
            if (!$doctorData) {
                return $this->response(null, __('Doctor not found', 'kivicare-clinic-management-system'), false, 404);
            }

            // Parse basic data JSON
            $basicData = !empty($doctorData->basic_data) ? json_decode($doctorData->basic_data, true) : [];

            // Get attachment URL only if attachment ID exists
            $profileImageUrl = !empty($doctorData->profile_image_id) ?
                wp_get_attachment_url($doctorData->profile_image_id) : '';

            $allUserMetaData = get_user_meta($doctorData->id);

            // Calculate service count 
            $serviceCount = 0;
            $serviceMappingsQuery = KCServiceDoctorMapping::query()
                ->where('doctor_id', '=', $id)
                ->where('status', '=', 1);

            // Filter by clinic if user is clinic admin or receptionist
            if ($clinic_id) {
                $serviceMappingsQuery->where('clinic_id', '=', $clinic_id);
            }

            $serviceMappings = $serviceMappingsQuery->select(['service_id'])->get();

            if ($serviceMappings->isNotEmpty()) {
                $serviceCount = $serviceMappings->pluck('serviceId')->unique()->count();
            }

            // Calculate review count (if pro plugin is active)
            $reviewCount = 0;
            if (isKiviCareProActive() && class_exists(\KCProApp\models\KCPPatientReview::class)) {
                $reviewCount = \KCProApp\models\KCPPatientReview::query()
                    ->where('doctorId', '=', $id)
                    ->count();
            }

            // Build response object using null coalescing operators for cleaner code
            $doctorObj = [
                'id' => $doctorData->id,
                'first_name' => $doctorData->first_name ?? '',
                'last_name' => $doctorData->last_name ?? '',
                'display_name' => $doctorData->display_name,
                'email' => $doctorData->email,
                'doctor_image_url' => $profileImageUrl,
                'doctor_image_id' => !empty($doctorData->profile_image_id) ?
                    (int) $doctorData->profile_image_id : null,
                'clinics' => array_map(function ($clinic) {
                    return [
                        'clinic_id' => $clinic->clinicId,
                        'id' => $clinic->clinicId,
                        'value' => $clinic->clinicId,
                        'clinic_name' => $clinic->clinic_name,
                        'label' => $clinic->clinic_name,
                        'clinic_email' => $clinic->clinic_email,
                        'clinic_image_url' => !empty($clinic->clinic_profile_image) ?
                            wp_get_attachment_url($clinic->clinic_profile_image) : '',
                        'clinic_image_id' => $clinic->clinic_profile_image,
                    ];
                }, $doctorClinics->toArray()),
                'status' => (int) $doctorData->status,
                'contact_number' => $basicData['mobile_number'] ?? '',
                'gender' => $basicData['gender'] ?? '',
                'dob' => $basicData['dob'] ?? '',
                'address' => $basicData['address'] ?? '',
                'city' => $basicData['city'] ?? '',
                'country' => $basicData['country'] ?? '',
                'postal_code' => $basicData['postal_code'] ?? '',
                'specialties' => $basicData['specialties'] ?? '',
                'qualifications' => $basicData['qualifications'] ?? '',
                'no_of_experience' => $basicData['no_of_experience'] ?? null,
                'doctor_signature' => !empty($doctorData->doctor_signature) ?
                    sanitize_text_field($doctorData->doctor_signature) : '',
                'doctor_description' => !empty($doctorData->doctor_description) ? 
                    sanitize_textarea_field($doctorData->doctor_description) : '',

                'created_at' => $doctorData->user_registered,
                'service_count' => (int) $serviceCount,
                'review_count' => (int) $reviewCount
            ];

            $doctorObj = apply_filters('kc_doctor_data', $doctorObj, $doctor->id);

            return $this->response($doctorObj, __('Doctor retrieved successfully', 'kivicare-clinic-management-system'));

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve doctor', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Create new doctor
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function createDoctor(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        try {
            $params = $request->get_params();

            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Check if doctor with same email already exists
            $existingDoctor = KCDoctor::table('d')
                ->where('d.user_email', '=', $params['email'])
                ->first();

            if ($existingDoctor) {
                return $this->response(
                    null,
                    __('A doctor with this email already exists', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Determine clinic IDs
            $current_user_role = $this->kcbase->getLoginUserRole();

            if (isKiviCareProActive()) {
                if (empty($params['clinic_id'])) {
                    // Fall back to the current user's clinic
                    if ($current_user_role == $this->kcbase->getClinicAdminRole()) {
                        $clinic_id = KCClinic::getClinicIdOfClinicAdmin();
                        $params['clinic_id'] = [['id' => $clinic_id]];
                    } elseif ($current_user_role == $this->kcbase->getReceptionistRole()) {
                        $clinic_id = KCClinic::getClinicIdOfReceptionist();
                        $params['clinic_id'] = [['id' => $clinic_id]];
                    } else {
                        // Leave empty
                        $params['clinic_id'] = [];
                    }
                }
            } else {
                // Always fall back to default clinic
                $default_clinic_id = KCClinic::kcGetDefaultClinicId();
                $params['clinic_id'] = [['id' => $default_clinic_id]];
            }

            // Create new KCDoctor instance
            $doctor = new KCDoctor();

            // Set doctor properties directly
            $doctor->username = kcGenerateUsername($params['first_name']);
            $doctor->password = kcGenerateRandomString(12);
            $doctor->email = sanitize_email($params['email']);
            $doctor->firstName = $params['first_name'];
            $doctor->lastName = $params['last_name'];
            $doctor->displayName = $params['first_name'] . ' ' . $params['last_name'];
            $doctor->gender = $params['gender'];
            $doctor->bloodGroup = $params['blood_group'];
            $doctor->contactNumber = $params['mobile_number'];
            $doctor->dob = $params['dob'];
            $doctor->experience = $params['experience_years'];
            $doctor->signature = $params['doctor_signature'] ?? '';
            $doctor->description = $params['description'];
            $doctor->address = $params['address'];
            $doctor->city = $params['city'];
            $doctor->country = $params['country'];
            $doctor->postalCode = $params['postal_code'];
            $doctor->status = $params['status'];
            $doctor->qualifications = $params['qualifications'];
            $doctor->specialties = $params['specialties'];

            if (array_key_exists('profile_image', $params)) {
                if (!empty($params['profile_image'])) {
                    $doctor->profileImage = (int) $params['profile_image'];
                } else {
                    if (!empty($doctor->id)) {
                        delete_user_meta($doctor->id, 'doctor_profile_image');
                    }
                    $doctor->profileImage = null;
                }
            }

            // Now call save 
            $saveResult = $doctor->save();
            if (is_wp_error($saveResult)) {
                $wpdb->query('ROLLBACK');
                return $this->response(
                    null,
                    $saveResult->get_error_message(),
                    false,
                    400
                );
            }
            
            if (!$saveResult) {
                $wpdb->query('ROLLBACK');
                return $this->response(
                    null,
                    __('Failed to create doctor', 'kivicare-clinic-management-system'),
                    false,
                    500
                );
            }

            // Save doctor clinic mapping - use the actual clinic ID from params
            $this->saveDoctorClinic($doctor->id, $params['clinic_id']);

            // Get clinic information if pro is active
            $clinic_info = [];
            if (isKiviCareProActive() && !empty($params['clinic_id'])) {
                $clinic_info = array_map(function ($clinic_item) {
                    $clinic = KCClinic::find($clinic_item['id']);
                    if ($clinic) {
                        return [
                            'clinic_id' => $clinic->id,
                            'clinic_name' => $clinic->name,
                            'clinic_email' => $clinic->email
                        ];
                    }
                    return null;
                }, $params['clinic_id']);
                $clinic_info = array_filter($clinic_info); // Remove null values
            }

            // Get the new doctor data with clinic info
            $doctorData = [
                'id' => $doctor->id,
                'user_id' => $doctor->id,
                'first_name' => $doctor->firstName,
                'last_name' => $doctor->lastName,
                'email' => $doctor->email,
                'username' => $doctor->username,
                'temp_password' => $doctor->password,
                'contact_number' => $doctor->contactNumber,
                'clinics' => $clinic_info, // Return clinic details
                'dob' => $doctor->dob,
                'gender' => $doctor->gender,
                'blood_group' => $doctor->bloodGroup,
                'experience' => $doctor->experience,
                'signature' => $doctor->signature,
                'description' => $doctor->description,
                'address' => $doctor->address,
                'city' => $doctor->city,
                'country' => $doctor->country,
                'postal_code' => $doctor->postalCode,
                'status' => $doctor->status,
                'qualifications' => $doctor->qualifications,
                'specialties' => $doctor->specialties,
                'doctor_image_url' => $doctor->profileImage ? wp_get_attachment_url($doctor->profileImage) : '',
                'created_at' => current_time('mysql')
            ];

            // Merge clinic info if available
            if (!empty($clinic_info)) {
                $doctorData = array_merge($doctorData, $clinic_info);
            }

            // Fire action hook for doctor creation
            do_action('kc_doctor_save', $doctorData, $request);
            do_action('kc_doctor_register', $doctorData);

            // Commit transaction on success
            $wpdb->query('COMMIT');

            return $this->response($doctorData, __('Doctor created successfully', 'kivicare-clinic-management-system'), true, 201);
        } catch (\Exception $e) {
            // Rollback transaction on error
            $wpdb->query('ROLLBACK');

            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to create doctor', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }

    /**
     * Save doctor clinic mapping
     * 
     * @param int $doctor_id
     * @param mixed $clinic_id
     */
    public function saveDoctorClinic($doctor_id, $clinic_id)
    {
        // Save/update doctor clinic mappings
        if (is_array($clinic_id)) {
            foreach ($clinic_id as $value) {
                $new_temp = [
                    'doctorId' => (int) $doctor_id,
                    'clinicId' => $value['id'],
                    'createdAt' => current_time('mysql')
                ];
                // Create doctor clinic mappings
                KCDoctorClinicMapping::create($new_temp);
            }
        } else {
            $new_temp = [
                'doctorId' => (int) $doctor_id,
                'clinicId' => $clinic_id,
                'createdAt' => current_time('mysql')
            ];
            // Create doctor clinic mappings
            KCDoctorClinicMapping::create($new_temp);
        }
    }

    /**
     * Update existing doctor
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function updateDoctor(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();
            $id = (int) $params['id'];
            if (!$this->validateId($id)) {
                return $this->response(null, __('Invalid doctor ID', 'kivicare-clinic-management-system'), false, 400);
            }

            // Check clinic access for clinic admin and receptionist
            $current_user_role = $this->kcbase->getLoginUserRole();
            $clinic_id = null;
            if ($current_user_role === $this->kcbase->getClinicAdminRole()) {
                $clinic_id = KCClinic::getClinicIdOfClinicAdmin();
            } elseif ($current_user_role === $this->kcbase->getReceptionistRole()) {
                $clinic_id = KCClinic::getClinicIdOfReceptionist();
            }
            if ($clinic_id) {
                $doctorClinicMapping = KCDoctorClinicMapping::table('dcm')
                    ->where('dcm.doctor_id', '=', $id)
                    ->where('dcm.clinic_id', '=', $clinic_id)
                    ->first();

                if (!$doctorClinicMapping) {
                    return $this->response(null, __('Access denied. Doctor not found in your clinic.', 'kivicare-clinic-management-system'), false, 403);
                }
            }

            // Find the doctor
            $doctor = KCDoctor::find($id);
            if (!$doctor) {
                return $this->response(null, __('Doctor not found', 'kivicare-clinic-management-system'), false, 404);
            }

            // Load existing basic_data into model properties to preserve values during partial update
            $existingBasicData = get_user_meta($id, 'basic_data', true);
            $basicData = !empty($existingBasicData) ? json_decode($existingBasicData, true) : [];
            $doctor->description = get_user_meta($id, 'doctor_description', true) ?: '';

            // map the basic data keys to the model properties
            $basicDataMap = [
                'mobile_number' => 'contactNumber',
                'gender' => 'gender',
                'dob' => 'dob',
                'address' => 'address',
                'city' => 'city',
                'country' => 'country',
                'postal_code' => 'postalCode',
                'qualifications' => 'qualifications',
                'no_of_experience' => 'experience',
                'specialties' => 'specialties',
            ];

            // Load existing basic_data values to the model properties
            foreach ($basicDataMap as $dataKey => $prop) {
                $doctor->$prop = $basicData[$dataKey] ?? (in_array($dataKey, ['qualifications', 'specialties']) ? [] : '');
            }

            // Load existing meta values 
            $doctor->firstName = get_user_meta($id, 'first_name', true) ?: '';
            $doctor->lastName = get_user_meta($id, 'last_name', true) ?: '';

            // Check email uniqueness
            if (isset($params['email']) && $params['email'] !== $doctor->email) {
                $email = sanitize_email($params['email']);

                // Check in WP Users
                $user_by_email = get_user_by('email', $email);
                if ($user_by_email && $user_by_email->ID != $id) {
                    return $this->response(
                        null,
                        __('Email already exists', 'kivicare-clinic-management-system'),
                        false,
                        400
                    );
                }
            }
            $doctor->displayName = trim(($doctor->firstName ?? '') . ' ' . ($doctor->lastName ?? ''));
            $doctor->signature = get_user_meta($id, 'doctor_signature', true) ?: '';
            $doctor->profileImage = get_user_meta($id, 'doctor_profile_image', true) ?: '';

            // Mapping: request param => [model property, sanitizer, meta_key (for deletion when empty)]
            $fieldMap = [
                'first_name' => ['firstName', 'sanitize_text_field', null],
                'last_name' => ['lastName', 'sanitize_text_field', null],
                'email' => ['email', 'sanitize_email', null],
                'mobile_number' => ['contactNumber', 'sanitize_text_field', null],
                'dob' => ['dob', 'sanitize_text_field', null],
                'address' => ['address', 'sanitize_text_field', null],
                'city' => ['city', 'sanitize_text_field', null],
                'country' => ['country', 'sanitize_text_field', null],
                'postal_code' => ['postalCode', 'sanitize_text_field', null],
                'gender' => ['gender', null, null],
                'experience_years' => ['experience', null, null],
                'specialties' => ['specialties', null, null],
                'qualifications' => ['qualifications', null, null],
                'description' => ['description', 'sanitize_textarea_field', 'doctor_description'],
                'doctor_signature' => ['signature', null, 'doctor_signature'],
                'profile_image' => ['profileImage', 'absint', 'doctor_profile_image'],
                'doctor_image_id' => ['profileImage', 'absint', 'doctor_profile_image'],
                'status' => ['status', null, null],
            ];

            // Apply partial updates
            foreach ($fieldMap as $paramKey => list($prop, $sanitizer, $metaKey)) {
                if (array_key_exists($paramKey, $params)) {
                    $value = $params[$paramKey];
                    // If empty/null and has meta key, delete the meta
                    if (($value === '' || $value === null) && $metaKey) {
                        delete_user_meta($id, $metaKey);
                        $doctor->$prop = null;
                    } else {
                        $doctor->$prop = $sanitizer ? $sanitizer($value) : $value;
                    }
                }
            }

            // Update displayName if first_name or last_name changed
            if (array_key_exists('first_name', $params) || array_key_exists('last_name', $params)) {
                $doctor->displayName = trim(($doctor->firstName ?? '') . ' ' . ($doctor->lastName ?? ''));
            }

            // Save the doctor - the method returns ID on success or WP_Error on failure
            $result = $doctor->save();

            if (is_wp_error($result)) {
                return $this->response(
                    null,
                    $result->get_error_message(),
                    false,
                    400
                );
            }

            if (!$result) {
                return $this->response(
                    null,
                    __('Failed to update doctor', 'kivicare-clinic-management-system'),
                    false,
                    500
                );
            }

            // If clinic ID is provided, update doctor-clinic mapping
            if (isset($params['clinic_id'])) {
                $current_user_role = $this->kcbase->getLoginUserRole();

                // If clinic_id is empty, fall back to the current user's clinic
                if (empty($params['clinic_id']) && isKiviCareProActive()) {
                    if ($current_user_role === $this->kcbase->getClinicAdminRole()) {
                        $clinic_id = KCClinic::getClinicIdOfClinicAdmin();
                        $params['clinic_id'] = [['id' => $clinic_id]];
                    } elseif ($current_user_role === $this->kcbase->getReceptionistRole()) {
                        $clinic_id = KCClinic::getClinicIdOfReceptionist();
                        $params['clinic_id'] = [['id' => $clinic_id]];
                    }
                }

                // First delete existing mappings
                KCDoctorClinicMapping::table('dcm')
                    ->where('doctor_id', '=', $id)
                    ->delete();

                // Create new mapping(s)
                $this->saveDoctorClinic($id, $params['clinic_id']);
            }

            // Get updated doctor data
            $doctorData = KCDoctor::table('d')
                ->select([
                    "d.*",
                    "um_first.meta_value as first_name",
                    "um_last.meta_value as last_name",
                    "bd.meta_value as basic_data",
                    "pi.meta_value as profile_image_id"
                ])
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'um_first.user_id')
                        ->onRaw("um_first.meta_key = 'first_name'");
                }, null, null, 'um_first')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'um_last.user_id')
                        ->onRaw("um_last.meta_key = 'last_name'");
                }, null, null, 'um_last')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'bd.user_id')
                        ->onRaw("bd.meta_key = 'basic_data'");
                }, null, null, 'bd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'pi.user_id')
                        ->onRaw("pi.meta_key = 'doctor_profile_image'");
                }, null, null, 'pi')
                ->where('d.ID', '=', $id)
                ->first();

            // Get all clinics for this doctor
            $doctorClinics = KCDoctorClinicMapping::table('dcm')
                ->select([
                    "c.id as clinic_id",
                    "c.name as clinic_name",
                    "c.email as clinic_email",
                    "c.profile_image as clinic_profile_image"
                ])
                ->leftJoin(KCClinic::class, 'dcm.clinic_id', '=', 'c.id', 'c')
                ->where('dcm.doctor_id', '=', $id)
                ->get();

            // Parse basic data
            $basicData = !empty($doctorData->basic_data) ? json_decode($doctorData->basic_data, true) : [];

            // Build response data
            $responseData = [
                'id' => $doctorData->id,
                'first_name' => $doctorData->first_name,
                'last_name' => $doctorData->last_name,
                'display_name' => $doctorData->display_name,
                'email' => $doctorData->email,
                'clinics' => array_map(function ($clinic) {
                    return [
                        'clinic_id' => $clinic->clinicId,
                        'clinic_name' => $clinic->clinic_name,
                        'clinic_email' => $clinic->clinic_email,
                        'clinic_image_url' => !empty($clinic->clinic_profile_image)
                            ? wp_get_attachment_url($clinic->clinic_profile_image)
                            : '',
                        'clinic_image_id' => $clinic->clinic_profile_image,
                    ];
                }, $doctorClinics->toArray()),
                'contact_number' => $basicData['mobile_number'] ?? '',
                'gender' => $basicData['gender'] ?? '',
                'dob' => $basicData['dob'] ?? '',
                'address' => $basicData['address'] ?? '',
                'city' => $basicData['city'] ?? '',
                'country' => $basicData['country'] ?? '',
                'postal_code' => $basicData['postal_code'] ?? '',
                'specialties' => $basicData['specialties'] ?? [],
                'qualifications' => $basicData['qualifications'] ?? [],
                'experience_years' => $basicData['no_of_experience'] ?? '',
                'status' => (int) $doctorData->user_status,
                'doctor_image_url' => !empty($doctorData->profile_image_id) ?
                    wp_get_attachment_url($doctorData->profile_image_id) : '',
                'doctor_image_id' => !empty($doctorData->profile_image_id) ?
                    (int) $doctorData->profile_image_id : null,
                'updated_at' => current_time('mysql')
            ];

            do_action('kc_doctor_update', $responseData['id'], $request);

            return $this->response($responseData, __('Doctor updated successfully', 'kivicare-clinic-management-system'));

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update doctor', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }


    /**
     * Delete doctor
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function deleteDoctor(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');

            // Find the doctor
            $doctor = KCDoctor::find($id);

            if (!$doctor) {
                return $this->response(null, __('Doctor not found', 'kivicare-clinic-management-system'), false, 404);
            }

            // Delete custom field data
            KCCustomFieldData::query()
                ->where('module_type', '=', 'doctor_module')
                ->where('module_id', '=', $id)
                ->delete();

            // Delete doctor clinic mappings
            KCDoctorClinicMapping::table('dcm')
                ->where('doctor_id', '=', $id)
                ->delete();

            // Delete the doctor record
            $result = $doctor->delete();

            if (!$result) {
                return $this->response(null, __('Failed to delete doctor', 'kivicare-clinic-management-system'), false, 500);
            }

            // Delete WordPress user
            wp_delete_user($doctor->ID);

            return $this->response(['id' => $id], __('Doctor deleted successfully', 'kivicare-clinic-management-system'));

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to delete doctor', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }

    /**
     * Update doctor status
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function updateDoctorStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');
            $status = $request->get_param('status');

            $doctor = KCDoctor::find($id);

            if (!$doctor) {
                return $this->response(null, __('Doctor not found', 'kivicare-clinic-management-system'), false, 404);
            }

            $doctor->status = $status;
            $updated = $doctor->save();

            if ($updated) {
                return $this->response(
                    ['id' => $id, 'status' => $status],
                    __('Doctor status updated successfully', 'kivicare-clinic-management-system'),
                    true,
                    200
                );
            } else {
                return $this->response(
                    null,
                    __('Failed to update doctor status', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
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
     * Resend doctor credentials
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function resendDoctorCredentials(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');

            // Get user data directly from WordPress users table
            $user_data = get_userdata($id);

            if (!$user_data) {
                return $this->response(null, __('Doctor not found', 'kivicare-clinic-management-system'), false, 404);
            }

            // Verify user has doctor role
            if (!in_array(KIVI_CARE_PREFIX . 'doctor', $user_data->roles)) {
                return $this->response(null, __('Doctor user not found', 'kivicare-clinic-management-system'), false, 400);
            }

            // Generate new password
            $password = wp_generate_password(12, true, false);
            wp_set_password($password, $user_data->ID);

            // Prepare email data
            $email_data = [
                'user_name' => $user_data->user_login,
                'user_email' => $user_data->user_email,
                'user_password' => $password,
                'doctor_name' => $user_data->display_name,
                'user_role' => 'Doctor'
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
                    __('Doctor credentials resent successfully', 'kivicare-clinic-management-system'),
                    true,
                    200
                );
            } else {
                return $this->response(
                    null,
                    __('Failed to resend doctor credentials', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

    /**
     * Bulk delete doctors
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function bulkDeleteDoctors(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $ids = $request->get_param('ids');

            $success_count = 0;
            $failed_count = 0;
            $failed_ids = [];

            foreach ($ids as $id) {
                $doctor = KCDoctor::find($id);

                if (!$doctor) {
                    $failed_count++;
                    $failed_ids[] = [
                        'id' => $id,
                        'reason' => __('Doctor not found', 'kivicare-clinic-management-system')
                    ];
                    continue;
                }

                // Check if doctor has appointments
                $appointmentCount = KCAppointment::table('a')
                    ->where('a.doctor_id', '=', $id)
                    ->count();

                if ($appointmentCount > 0) {
                    $failed_count++;
                    $failed_ids[] = [
                        'id' => $id,
                        'reason' => __('Doctor has existing appointments', 'kivicare-clinic-management-system')
                    ];
                    continue;
                }

                // Delete custom field data
                KCCustomFieldData::query()
                    ->where('module_type', '=', 'doctor_module')
                    ->where('module_id', '=', $id)
                    ->delete();

                // Delete doctor clinic mappings
                KCDoctorClinicMapping::table('dcm')
                    ->where('doctor_id', '=', $id)
                    ->delete();

                // Delete the doctor record
                $result = $doctor->delete();

                if (!$result) {
                    $failed_count++;
                    $failed_ids[] = [
                        'id' => $id,
                        'reason' => __('Failed to delete doctor', 'kivicare-clinic-management-system')
                    ];
                    continue;
                }

                // Delete WordPress user
                wp_delete_user($doctor->ID);

                $success_count++;
            }

            if ($success_count > 0) {
                return $this->response(
                    [
                        'success_count' => $success_count,
                        'failed_count' => $failed_count,
                        'failed_ids' => $failed_ids
                    ],
                    sprintf(
                        /* translators: %d: number of doctors */
                        __('%d doctors deleted successfully', 'kivicare-clinic-management-system'),
                        $success_count
                    ),
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
                    __('Failed to delete doctors', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
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
                'description' => 'Array of doctor IDs',
                'type' => 'array',
                'items' => [
                    'type' => 'integer',
                ],
                'required' => true,
                'validate_callback' => function ($param) {
                    if (!is_array($param) || empty($param)) {
                        return new WP_Error('invalid_ids', __('Doctor IDs are required', 'kivicare-clinic-management-system'));
                    }
                    foreach ($param as $id) {
                        if (!is_numeric($id) || intval($id) <= 0) {
                            return new WP_Error('invalid_id', __('Invalid doctor ID in array', 'kivicare-clinic-management-system'));
                        }
                    }
                    return true;
                }
            ]
        ];
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
                $doctor = KCDoctor::find($id);
                if (!$doctor) {
                    $failed_count++;
                    $failed_ids[] = $id;
                    continue;
                }

                $user_data = get_userdata($doctor->id);

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
                    'doctor_name' => $doctor->displayName,
                    'user_role' => 'Doctor'
                ];

                // Use email sender with template
                $email_status = $this->emailSender->sendEmailByTemplate(
                    KIVI_CARE_PREFIX . 'resend_user_credential',
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
                    /* translators: %d: number of doctors */
                    sprintf(__('Credentials resent for %d doctors', 'kivicare-clinic-management-system'), $success_count),
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
     * Bulk update doctor status
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function bulkUpdateDoctorStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $ids = $request->get_param('ids');
            $status = $request->get_param('status');

            $success_count = 0;
            $failed_count = 0;
            $failed_ids = [];

            foreach ($ids as $id) {
                $doctor = KCDoctor::find($id);

                if (!$doctor) {
                    $failed_count++;
                    $failed_ids[] = $id;
                    continue;
                }

                $updated = $doctor->updateStatus($status);

                if ($updated) {
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
                    /* translators: %d: number of doctors */
                    sprintf(__('Status updated for %d doctors', 'kivicare-clinic-management-system'), $success_count),
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
                    __('Failed to update doctor status', 'kivicare-clinic-management-system'),
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
                    if (!in_array(strtolower($param), ['csv', 'xls', 'pdf'])) {
                        return new WP_Error('invalid_format', __('Format must be csv, xls, or pdf', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => function ($param) {
                    return strtolower(sanitize_text_field($param));
                },
            ],
            'search' => [
                'description' => 'Search term to filter results',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => $this->getCommonArgs()['status'],
            'clinic_id' => [
                'description' => 'Filter by clinic ID',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Export doctors data
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function exportDoctors(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $format = $request->get_param('format');
            $params = $request->get_params();

            // Build query to get all doctors matching filters
            $query = KCDoctor::table('d')
                ->select([
                    "d.ID as id",
                    "um_first.meta_value as first_name",
                    "um_last.meta_value as last_name",
                    "d.user_email as email",
                    "bd.meta_value as basic_data",
                    "d.user_status as user_status",
                    "c.name as clinic_name",
                    "c.id as clinic_id",
                ])
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'um_first.user_id')
                        ->onRaw("um_first.meta_key = 'first_name'");
                }, null, null, 'um_first')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'um_last.user_id')
                        ->onRaw("um_last.meta_key = 'last_name'");
                }, null, null, 'um_last')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'bd.user_id')
                        ->onRaw("bd.meta_key = 'basic_data'");
                }, null, null, 'bd')
                ->leftJoin(KCDoctorClinicMapping::class, 'd.ID', '=', 'dcm.doctor_id', 'dcm')
                ->leftJoin(KCClinic::class, 'dcm.clinic_id', '=', 'c.id', 'c')
                ->groupBy('d.ID');

            // Apply filters
            if (!empty($params['search'])) {
                $search = sanitize_text_field($params['search']);
                $query->where(function ($q) use ($search) {
                    $q->where("um_first.meta_value", 'LIKE', '%' . $search . '%')
                        ->orWhere("um_last.meta_value", 'LIKE', '%' . $search . '%')
                        ->orWhere("d.user_email", 'LIKE', '%' . $search . '%');
                });
            }

            if (isset($params['status']) && $params['status'] !== '') {
                $query->where("d.user_status", '=', intval($params['status']));
            }

            if (!empty($params['clinic_id'])) {
                $query->where('c.id', '=', intval($params['clinic_id']));
            }

            $doctors = $query->get();

            // Process doctors data
            $exportData = [];
            foreach ($doctors as $doctor) {
                $basicData = !empty($doctor->basic_data) ? json_decode($doctor->basic_data, true) : [];

                // Get specialties
                $specialties = [];
                if (!empty($basicData['specialties'])) {
                    if (is_array($basicData['specialties'])) {
                        $specialties = array_map(function ($spec) {
                            return is_array($spec) ? ($spec['label'] ?? '') : $spec;
                        }, $basicData['specialties']);
                    }
                }
                $specialtiesStr = !empty($specialties) ? implode(', ', $specialties) : '#';

                // Build full address
                $addressParts = array_filter([
                    $basicData['address'] ?? '',
                    $basicData['city'] ?? '',
                    $basicData['state'] ?? '',
                    $basicData['country'] ?? '',
                    $basicData['postal_code'] ?? ''
                ]);
                $fullAddress = !empty($addressParts) ? implode(', ', $addressParts) : '#';

                $exportData[] = [
                    'id' => $doctor->id,
                    'name' => trim(($doctor->first_name ?? '') . ' ' . ($doctor->last_name ?? '')),
                    'clinic_name' => $doctor->clinic_name ?? '',
                    'clinic_id' => $doctor->clinic_id ?? '',
                    'email' => $doctor->email ?? '',
                    'user_status' => $doctor->user_status == 0 ? 'Active' : 'Inactive',
                    'mobile_number' => $basicData['mobile_number'] ?? '',
                    'gender' => $basicData['gender'] ?? '',
                    'dob' => $basicData['dob'] ?? '',
                    'specialties' => $specialtiesStr,
                    'full_address' => $fullAddress,
                ];
            }
            return $this->response(
                ['doctors' => $exportData],
                __('Doctors data retrieved successfully', 'kivicare-clinic-management-system'),
                true,
                200
            );

        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

}