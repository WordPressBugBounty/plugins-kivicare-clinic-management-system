<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\baseClasses\KCPermissions;
use App\models\KCBill;
use App\models\KCPatient;
use App\models\KCPatientClinicMapping;
use App\models\KCAppointment;
use App\models\KCPatientEncounter;
use App\models\KCMedicalHistory;
use App\models\KCMedicalProblem;
use App\models\KCClinic;
use App\models\KCOption;
use App\models\KCCustomFieldData;
use App\models\KCUserMeta;
use App\baseClasses\KCErrorLogger;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class PatientController
 * 
 * API Controller for Patient-related endpoints
 * 
 * @package App\controllers\api
 */
class PatientController extends KCBaseController
{

    /**
     * @var string The base route for this controller
     */
    protected $route = 'patients';

    /**
     * Get common arguments used across multiple endpoints
     *
     * @return array
     */
    private function getCommonArgs()
    {
        return [
            'id' => [
                'description' => 'Patient ID',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
            'first_name' => [
                'description' => 'Patient first name',
                'type' => 'string',
                'validate_callback' => [$this, 'validateName'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'last_name' => [
                'description' => 'Patient last name',
                'type' => 'string',
                'validate_callback' => [$this, 'validateName'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'email' => [
                'description' => 'Patient email',
                'type' => 'string',
                'validate_callback' => [$this, 'validateEmail'],
                'sanitize_callback' => 'sanitize_email',
            ],
            'contact_number' => [
                'description' => 'Patient contact number',
                'type' => 'string',
                'validate_callback' => [$this, 'validateContact'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'description' => 'Patient status (0: Inactive, 1: Active)',
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
     * Validate patient name
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
     * Validate patient status
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
     * Validate date of birth
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateDob($param)
    {
        if (empty($param)) {
            return new WP_Error('invalid_dob', __('Date of birth is required', 'kivicare-clinic-management-system'));
        }
        $date = \DateTime::createFromFormat('Y-m-d', $param);
        if (!$date || $date->format('Y-m-d') !== $param) {
            return new WP_Error('invalid_dob', __('Invalid date format. Use YYYY-MM-DD', 'kivicare-clinic-management-system'));
        }

        $today = new \DateTime();

        // Check if DOB is in the future
        if ($date > $today) {
            return new WP_Error('invalid_dob', __('Date of birth cannot be in the future', 'kivicare-clinic-management-system'));
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
            'contact_number',
            'dob',
            'gender',
            'status',
            'created_at',
            'registered_on'
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
        // // Get all patients
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getPatients'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getListEndpointArgs()
        ]);

        // Get single patient
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getPatient'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getSingleEndpointArgs()
        ]);

        // Create patient
        $this->registerRoute('/' . $this->route, [
            'methods' => 'POST',
            'callback' => [$this, 'createPatient'],
            'permission_callback' => [$this, 'checkCreatePermission'],
            'args' => $this->getCreateEndpointArgs()
        ]);

        // Update patient
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updatePatient'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getUpdateEndpointArgs()
        ]);

        // Delete patient
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deletePatient'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getDeleteEndpointArgs()
        ]);

        // Update Patient status
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'updatePatientStatus'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getStatusUpdateEndpointArgs()
        ]);

        // Resend Patient credentials
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/resend-credentials', [
            'methods' => 'POST',
            'callback' => [$this, 'resendPatientCredentials'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getSingleEndpointArgs()
        ]);

        // Bulk delete Patients
        $this->registerRoute('/' . $this->route . '/bulk/delete', [
            'methods' => 'POST',
            'callback' => [$this, 'bulkDeletePatients'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getBulkDeleteEndpointArgs()
        ]);

        // Bulk resend credentials
        $this->registerRoute('/' . $this->route . '/bulk/resend-credentials', [
            'methods' => 'POST',
            'callback' => [$this, 'bulkResendCredentials'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getBulkDeleteEndpointArgs()
        ]);

        // Bulk update Patient status
        $this->registerRoute('/' . $this->route . '/bulk/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'bulkUpdatePatientStatus'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getBulkStatusUpdateEndpointArgs()
        ]);

        // Get Patient unique ID
        $this->registerRoute('/' . $this->route . '/uniqueid', [
            'methods' => 'GET',
            'callback' => [$this, 'getPatientUniqueId'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Export patients
        $this->registerRoute('/' . $this->route . '/export', [
            'methods' => 'GET',
            'callback' => [$this, 'exportPatients'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getExportEndpointArgs()
        ]);

        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/statistics', [
            'methods' => 'GET',
            'callback' => [$this, 'getPatientStatistics'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getSingleEndpointArgs()
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
            'clinic' => [
                'description' => 'Filter by clinic ID',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
            'status' => $this->getCommonArgs()['status'],
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
            ],
            'doctor_id' => [
                'description' => 'Filter patients by doctor ID (for appointments)',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
            'patient_unique_id' => [
                'description' => 'Filter by patient unique ID',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
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
    private function getCreateEndpointArgs()
    {
        $args = [
            'first_name' => array_merge($this->getCommonArgs()['first_name'], ['required' => true]),
            'last_name' => array_merge($this->getCommonArgs()['last_name'], ['required' => true]),
            'email' => array_merge($this->getCommonArgs()['email'], ['required' => true]),
            'mobile_number' => array_merge($this->getCommonArgs()['contact_number'], ['required' => true]),
            'dob' => [
                'description' => 'Date of birth (YYYY-MM-DD)',
                'type' => 'string',
                'required' => true,
                'validate_callback' => [$this, 'validateDob'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'gender' => [
                'description' => 'Patient gender (male, female, other)',
                'type' => 'string',
                'required' => true,
                'validate_callback' => [$this, 'validateGender'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'clinics' => [
                'description' => 'Clinic IDs',
                'type' => 'array',
                'required' => true
            ],
            'blood_group' => [
                'description' => 'Patient blood group',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'address' => [
                'description' => 'Patient address',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'city' => [
                'description' => 'Patient city',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'country' => [
                'description' => 'Patient country',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'postal_code' => [
                'description' => 'Patient postal/ZIP code',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => array_merge($this->getCommonArgs()['status'], ['default' => 1]),
            'profile_image' => [
                'description' => 'Patient profile image ID',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && (!is_numeric($param) || $param <= 0)) {
                        return new WP_Error('invalid_image_id', __('Invalid image ID', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
            'patient_unique_id' => [
                'description' => 'Patient unique ID',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ]
        ];

        return apply_filters('kc_patient_create_endpoint_args', $args);
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

        return apply_filters('kc_patient_update_endpoint_args', $args);
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
            'clinic' => [
                'description' => 'Filter by clinic ID',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && (!is_numeric($param) || $param <= 0)) {
                        return new WP_Error('invalid_clinic_id', __('Invalid clinic ID', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
            'status' => [
                'description' => 'Filter by status (0: Active, 1: Inactive)',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && !in_array(intval($param), [0, 1])) {
                        return new WP_Error('invalid_status', __('Status must be 0 (active) or 1 (inactive)', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
            'doctor_id' => [
                'description' => 'Filter patients by doctor ID (for appointments)',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Check if user has permission
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkPermission($request)
    {
        return current_user_can('read');
    }

    /**
     * Check if user has permission to create a patient
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

        // Check patient add permission
        return $this->checkResourceAccess('patient', 'add');
    }

    /**
     * Check if user has permission to update a patient
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

        // If it's a patient role, allow them to update their own profile
        if ($current_user_role === $this->kcbase->getPatientRole()) {
            $patient_id = $request->get_param('id');
            // Allow patient to update their own data
            if ($patient_id && intval($patient_id) === $current_user_id) {
                return true;
            }
        }

        // Check patient edit permission
        return $this->checkResourceAccess('patient', 'edit');
    }

    /**
     * Check if user has permission to delete a patient
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

        // Check patient delete permission
        return $this->checkResourceAccess('patient', 'delete');
    }

    /**
     * Get all patients
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getPatients(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Process request parameters
            $params = $request->get_params();

            // Set defaults
            $page = isset($params['page']) ? (int) $params['page'] : 1;
            $perPageParam = isset($params['perPage']) ? $params['perPage'] : 10;

            // Handle "all" option for perPage
            $showAll = (strtolower($perPageParam) === 'all');
            $perPage = $showAll ? null : (int) $perPageParam;
            $search = isset($params['search']) ? sanitize_text_field($params['search']) : '';
            $patient_id = isset($params['id']) ? (int) $params['id'] : null;
            $patientName = isset($params['patientName']) ? sanitize_text_field($params['patientName']) : '';
            $clinicName = isset($params['clinicName']) ? sanitize_text_field($params['clinicName']) : '';
            $date_from = isset($params['date_from']) ? sanitize_text_field($params['date_from']) : '';
            $date_to = isset($params['date_to']) ? sanitize_text_field($params['date_to']) : '';
            $registeredOn = isset($params['registeredOn']) ? sanitize_text_field($params['registeredOn']) : '';
            $status = isset($params['status']) ? (int) ($params['status']) : null;
            $orderBy = isset($params['orderBy']) ? sanitize_text_field($params['orderBy']) : 'ID';
            $order = isset($params['order']) ? strtoupper(sanitize_text_field($params['order'])) : 'DESC';
            $patientUniqueId = isset($params['patient_unique_id']) ? sanitize_text_field($params['patient_unique_id']) : '';

            // Build the base query for patients
            $query = KCPatient::table('p')
                ->select([
                    "p.*",
                    "GROUP_CONCAT(DISTINCT CONCAT('{\"clinic_id\":', c.id, ',\"clinic_name\":\"', REPLACE(c.name, '\"', '\\\"'), '\",\"clinic_status\":\"', IFNULL(c.status, ''), '\",\"clinic_email\":\"', c.email, '\",\"clinic_contact_number\":\"', IFNULL(c.telephone_no, ''), '\",\"clinic_address\":\"', REPLACE(CONCAT_WS(', ', NULLIF(c.address, ''), NULLIF(c.city, ''), NULLIF(c.state, ''), NULLIF(c.country, ''), NULLIF(c.postal_code, '')), '\"', '\\\"'), '\",\"clinic_image_id\":', IFNULL(c.profile_image, 'null'), '}') SEPARATOR '||') as clinic_data",
                    "bd.meta_value as basic_data",
                    "pi.meta_value as profile_image",
                    "puid.meta_value as patient_unique_id"
                ])
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'bd.user_id')
                        ->onRaw("bd.meta_key = 'basic_data'");
                }, null, null, 'bd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'pi.user_id')
                        ->onRaw("pi.meta_key = 'patient_profile_image'");
                }, null, null, 'pi')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'puid.user_id')
                        ->onRaw("puid.meta_key = 'patient_unique_id'");
                }, null, null, 'puid')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'fn.user_id')
                        ->onRaw("fn.meta_key = 'first_name'");
                }, null, null, 'fn')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'ln.user_id')
                        ->onRaw("ln.meta_key = 'last_name'");
                }, null, null, 'ln')
                ->leftJoin(KCPatientClinicMapping::class, 'p.ID', '=', 'pcm.patient_id', 'pcm')
                ->leftJoin(KCClinic::class, 'pcm.clinic_id', '=', 'c.id', 'c')
                ->groupBy('p.ID'); // Add GROUP BY to handle the GROUP_CONCAT properly

            $current_user_role = $this->kcbase->getLoginUserRole();
            $current_user_id = get_current_user_id();

            if ($current_user_role === $this->kcbase->getDoctorRole()) {
                // For doctors, show patients who have appointments with this doctor OR patients created by this doctor
                $query->leftJoin(KCAppointment::class, 'p.ID', '=', 'app.patient_id', 'app')
                    ->leftJoin(KCUserMeta::class, function ($join) {
                        $join->on('p.ID', '=', 'pab.user_id')
                            ->onRaw("pab.meta_key = 'patient_added_by'");
                    }, null, null, 'pab')
                    ->where(function ($q) use ($current_user_id) {
                        $q->where('app.doctor_id', '=', $current_user_id)
                            ->orWhere('pab.meta_value', '=', $current_user_id);
                    });
            } elseif ($current_user_role === $this->kcbase->getClinicAdminRole()) {
                // For clinic admins, show only patients from their clinic
                $clinic_id = KCClinic::getClinicIdOfClinicAdmin($current_user_id);
                if ($clinic_id) {
                    $query->where('pcm.clinic_id', '=', $clinic_id);
                }
            }

            // for receptinoist, filter by their clinic 
            $clinic_id = null;
            if ($current_user_role === $this->kcbase->getReceptionistRole()) {
                $clinic_id = KCClinic::getClinicIdOfReceptionist();
            }

            if ($clinic_id) {
                // Filter by receptionist clinic
                $query->where('pcm.clinic_id', '=', $clinic_id);
            }

            // Apply filters
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where("p.display_name", 'LIKE', '%' . $search . '%')
                        ->orWhere("fn.meta_value", 'LIKE', '%' . $search . '%')
                        ->orWhere("ln.meta_value", 'LIKE', '%' . $search . '%')
                        ->orWhere("p.user_email", 'LIKE', '%' . $search . '%')
                        ->orWhere("p.ID", 'LIKE', '%' . $search . '%')
                        ->orWhere("c.name", 'LIKE', '%' . $search . '%')
                        ->orWhere("bd.meta_value", 'LIKE', '%' . $search . '%');
                });
            }

            if (!empty($patient_id)) {
                $query->where("p.ID", '=', $patient_id);
            }

            if (isset($patientName) && $patientName !== '') {
                $query->where(function ($q) use ($patientName) {
                    $q->where("p.display_name", 'LIKE', '%' . $patientName . '%')
                        ->orWhere("fn.meta_value", 'LIKE', '%' . $patientName . '%')
                        ->orWhere("ln.meta_value", 'LIKE', '%' . $patientName . '%');
                });
            }

            if (isset($clinicName) && $clinicName !== '') {
                $query->where("c.name", 'LIKE', '%' . $clinicName . '%');
            }

            if (isset($registeredOn) && $registeredOn !== '') {
                $query->where("p.user_registered", 'LIKE', '%' . $registeredOn . '%');
            }

            if (isset($date_from) && $date_from !== '') {
                $startDate = gmdate('Y-m-d 00:00:00', strtotime($date_from));
                $query->where("p.user_registered", '>=', $startDate);
            }

            if (isset($date_to) && $date_to !== '') {
                $endDate = gmdate('Y-m-d 23:59:59', strtotime($date_to));
                $query->where("p.user_registered", '<=', $endDate);
            }

            if (isset($status) && $status !== '') {
                $query->where("p.user_status", '=', $status);
            }

            if (!empty($params['clinic'])) {
                $query->where("c.id", '=', $params['clinic']);
            }

            if (!empty($patientUniqueId)) {
                $query->where("puid.meta_value", 'LIKE', '%' . $patientUniqueId . '%');
            }

            // Apply sorting if orderby parameter is provided
            if (!empty($params['orderby'])) {
                $orderby = $params['orderby'];
                $direction = !empty($params['order']) && strtolower($params['order']) === 'desc' ? 'DESC' : 'ASC';

                switch ($orderby) {
                    case 'name':
                        $query->orderBy("p.display_name", $direction);
                        break;
                    case 'email':
                        $query->orderBy("p.user_email", $direction);
                        break;
                    case 'registered_on':
                    case 'created_at':
                        $query->orderBy("p.user_registered", $direction);
                        break;
                    case 'status':
                        $query->orderBy("p.user_status", $direction);
                        break;
                    case 'id':
                    default:
                        $query->orderBy("p.ID", $direction);
                        break;
                }
            } else {
                // Default sorting by id descending if no sort specified
                $query->orderBy("p.id", 'DESC');
            }

            // Apply pagination with validation
            $page = max(1, intval($params['page'] ?? 1));
            $perPage = intval($params['perPage'] ?? 10);

            // Get total count for pagination
            $totalQuery = clone $query;
            $totalQuery->removeGroupBy();
            $total = $totalQuery->countDistinct('p.ID');
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

            $totalPages = $total > 0 ? ceil($total / $perPage) : 1;
            $offset = ($page - 1) * $perPage;

            if (!$showAll) {
                $query->limit($perPage)->offset($offset);
            }

            // Get paginated results
            $patients = $query->get();

            // Fetch active holidays for all clinics once
            $holidayClinicIds = \App\models\KCClinicSchedule::getActiveHolidaysByModule('clinic');

            // Prepare the patient data
            $patientsData = [];
            foreach ($patients as $patient) {

                // Decode basic_data JSON
                $basicData = [];
                if (!empty($patient->basic_data)) {
                    $basicData = json_decode($patient->basic_data, true);
                }

                // Get total encounters count for this patient
                $totalEncounters = KCPatientEncounter::query()
                    ->where('patient_id', '=', $patient->id)
                    ->count();

                $clinics = [];
                if (!empty($patient->clinic_data)) {
                    $clinicStrings = explode('||', $patient->clinic_data);
                    foreach ($clinicStrings as $clinicStr) {
                        $clinic = json_decode($clinicStr, true);
                        if ($clinic) {
                            $clinic['clinic_image_url'] = !empty($clinic['clinic_image_id']) ? wp_get_attachment_url($clinic['clinic_image_id']) : '';
                            $clinic['is_holiday'] = in_array((int)$clinic['clinic_id'], $holidayClinicIds, true);
                            $clinics[] = $clinic;
                        }
                    }
                }

                $patientData = [
                    'id' => $patient->id,
                    'name' => $patient->display_name,
                    'email' => $patient->email,
                    'mobileNumber' => $basicData['mobile_number'] ?? null,
                    'dob' => $basicData['dob'] ?? null,
                    'gender' => $basicData['gender'] ?? null,
                    'bloodGroup' => $basicData['blood_group'] ?? null,
                    'address' => $basicData['address'] ?? null,
                    'city' => $basicData['city'] ?? null,
                    'country' => $basicData['country'] ?? null,
                    'postalCode' => $basicData['postal_code'] ?? null,
                    'registeredOn' => kcGetFormatedDate(gmdate('Y-m-d', strtotime($patient->user_registered))),
                    'status' => (int) $patient->status,
                    'patient_image_url' => $patient->profile_image ? wp_get_attachment_url($patient->profile_image) : '',
                    'patient_image_id' => !empty($patient->profile_image) ? (int) $patient->profile_image : null,
                    'clinics' => $clinics,
                    // 'clinicName' => $patient->clinic_names ?: null,
                    'patient_unique_id' => $patient->patient_unique_id,
                    'total_encounters' => (int) $totalEncounters,
                ];

                $patientsData[] = $patientData;
            }

            // Return the formatted data with pagination
            $data = [
                'patients' => $patientsData,
                'pagination' => [
                    'total' => $total,
                    'perPage' => $perPage,
                    'currentPage' => $page,
                    'lastPage' => $totalPages,
                ],
            ];

            return $this->response($data, __('Patients retrieved successfully', 'kivicare-clinic-management-system'));

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve patients', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Get single patient by ID
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getPatient(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $patientId = $request->get_param('id');

            if (empty($patientId) || !is_numeric($patientId)) {
                return $this->response(
                    ['error' => 'Invalid patient ID'],
                    __('Invalid patient ID provided', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Check access restrictions for clinic admin and receptionist
            $current_user_role = $this->kcbase->getLoginUserRole();
            $clinic_id = null;

            if ($current_user_role === $this->kcbase->getClinicAdminRole()) {
                $clinic_id = KCClinic::getClinicIdOfClinicAdmin(get_current_user_id());
            } elseif ($current_user_role === $this->kcbase->getReceptionistRole()) {
                $clinic_id = KCClinic::getClinicIdOfReceptionist();
            }

            if ($clinic_id) {
                $patientClinicMapping = KCPatientClinicMapping::query()
                    ->where('patient_id', '=', $patientId)
                    ->where('clinic_id', '=', $clinic_id)
                    ->first();

                if (!$patientClinicMapping) {
                    return $this->response(null, __('Access denied. Patient not found in your clinic.', 'kivicare-clinic-management-system'), false, 403);
                }
            }

            $patient = KCPatient::table('p')
                ->select([
                    "p.*",
                    "fn.meta_value as first_name",
                    "ln.meta_value as last_name",
                    "bd.meta_value as basic_data",
                    "pi.meta_value as profile_image",
                    "puid.meta_value as patient_unique_id",
                    "GROUP_CONCAT(DISTINCT CONCAT_WS('::', pcm.clinic_id, c.name, IFNULL(c.status, '')) SEPARATOR '||') as clinic_mappings"
                ])
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'bd.user_id')
                        ->onRaw("bd.meta_key = 'basic_data'");
                }, null, null, 'bd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'fn.user_id')
                        ->onRaw("fn.meta_key = 'first_name'");
                }, null, null, 'fn')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'ln.user_id')
                        ->onRaw("ln.meta_key = 'last_name'");
                }, null, null, 'ln')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'pi.user_id')
                        ->onRaw("pi.meta_key = 'patient_profile_image'");
                }, null, null, 'pi')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'puid.user_id')
                        ->onRaw("puid.meta_key = 'patient_unique_id'");
                }, null, null, 'puid')
                ->leftJoin(KCPatientClinicMapping::class, 'p.ID', '=', 'pcm.patient_id', 'pcm')
                ->leftJoin(KCClinic::class, 'pcm.clinic_id', '=', 'c.id', 'c')
                ->where('p.ID', '=', $patientId)
                ->groupBy('p.ID')
                ->first();

            if (!$patient) {
                return $this->response(
                    null,
                    __('Patient not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            // Fetch active holidays for all clinics once
            $holidayClinicIds = \App\models\KCClinicSchedule::getActiveHolidaysByModule('clinic');

            $clinicsArray = [];
            if (!empty($patient->clinic_mappings)) {
                $clinicPairs = explode('||', $patient->clinic_mappings);
                foreach ($clinicPairs as $pair) {
                    [$clinicId, $clinicName, $clinicStatus] = array_pad(explode('::', $pair, 3), 3, null);
                    if ($clinicId !== null && $clinicId !== '') {
                        $clinicsArray[] = [
                            'value' => (int) $clinicId,
                            'label' => $clinicName,
                            'status' => $clinicStatus,
                            'is_holiday' => in_array((int)$clinicId, $holidayClinicIds, true)
                        ];
                    }
                }
            }

            // Decode basic_data JSON
            $basicData = [];
            if (!empty($patient->basic_data)) {
                $basicData = json_decode($patient->basic_data, true);
            }

            // Get total encounters count for this patient
            $totalEncounters = KCPatientEncounter::query()
                ->where('patient_id', '=', $patient->id)
                ->count();


            // Format patient data
            $patientData = [
                'id' => $patient->id,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'email' => $patient->email,
                'contact_number' => $basicData['mobile_number'] ?? null,
                'dob' => $basicData['dob'] ?? null,
                'gender' => $basicData['gender'] ?? null,
                'blood_group' => $basicData['blood_group'] ?? null,
                'address' => $basicData['address'] ?? null,
                'city' => $basicData['city'] ?? null,
                'country' => $basicData['country'] ?? null,
                'postal_code' => $basicData['postal_code'] ?? null,
                'status' => (int) $patient->status,
                'patient_image_url' => $patient->profile_image ? wp_get_attachment_url($patient->profile_image) : '',
                'patient_image_id' => $patient->profile_image,
                'patient_unique_id' => $patient->patient_unique_id,
                'total_encounters' => (int) $totalEncounters,
                'clinic' => $clinicsArray,
            ];

            $patientData = apply_filters('kc_patient_data', $patientData, $patient->id);

            return $this->response($patientData, __('Patient retrieved successfully', 'kivicare-clinic-management-system'));

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve patient', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Create new patient
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function createPatient(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();

            // Check if patient with same email already exists
            $existingPatient = KCPatient::table('p')
                ->where('p.user_email', '=', $params['email'])
                ->first();

            if ($existingPatient) {
                return $this->response(
                    null,
                    __('A patient with this email already exists. Please use a different email.', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Check if patient unique ID already exists
            if (!empty($params['patient_unique_id'])) {
                if ($this->isPatientUniqueIdExists($params['patient_unique_id'])) {
                    return $this->response(
                        null,
                        __('Patient unique ID is already used. Please use a different unique ID.', 'kivicare-clinic-management-system'),
                        false,
                        400
                    );
                }
            }

            // Clean contact number
            $params['mobile_number'] = preg_replace('/[^0-9+]/', '', $params['mobile_number']);

            // Determine clinic ID based on user role
            $current_user_role = $this->kcbase->getLoginUserRole();
            if (isKiviCareProActive()) {
                if ($current_user_role == $this->kcbase->getClinicAdminRole()) {
                    $clinic_id = KCClinic::getClinicIdOfClinicAdmin();
                } elseif ($current_user_role == $this->kcbase->getReceptionistRole()) {
                    $clinic_id = KCClinic::getClinicIdOfReceptionist();
                } else {
                    $clinic_id = $params['clinics'];
                }
            } else {
                // Default clinic id if pro not active
                $clinic_id = KCClinic::kcGetDefaultClinicId();
            }

            // Create new KCPatient instance
            $patient = new KCPatient();

            // Set patient properties directly
            $patient->username = kcGenerateUsername($params['first_name'], $params['email']);
            $patient->password = kcGenerateRandomString(12);
            $patient->email = sanitize_email($params['email']);
            $patient->firstName = $params['first_name'];
            $patient->lastName = $params['last_name'];
            $patient->displayName = $params['first_name'] . ' ' . $params['last_name'];
            $patient->status = $params['status'];
            $patient->gender = $params['gender'];
            $patient->bloodGroup = $params['blood_group'];
            $patient->contactNumber = $params['mobile_number'];
            $patient->dob = $params['dob'];
            $patient->address = $params['address'];
            $patient->city = $params['city'];
            $patient->country = $params['country'];
            $patient->postalCode = $params['postal_code'];

            // Add profile image if provided
            if (!empty($params['profile_image'])) {
                $patient->profileImage = (int) $params['profile_image'];
            }

            // Now call save 
            $saveResult = $patient->save();
            if (is_wp_error($saveResult)) {
                return $this->response(
                    ['error' => $saveResult->get_error_message()],
                    __('Failed to create patient', 'kivicare-clinic-management-system') . ': ' . $saveResult->get_error_message(),
                    false,
                    500
                );
            }

            // Save patient clinic mapping
            $this->savePatientClinic($patient->id, $clinic_id);

            // Store patient unique ID in usermeta if provided
            if (!empty($params['patient_unique_id'])) {
                update_user_meta($patient->id, 'patient_unique_id', sanitize_text_field($params['patient_unique_id']));
            }

            $patientData = [
                'id' => $patient->id,
                'first_name' => $patient->firstName,
                'last_name' => $patient->lastName,
                'email' => $patient->email,
                'username' => $patient->username,
                'contact_number' => $patient->contactNumber,
                'dob' => $patient->dob,
                'gender' => $patient->gender,
                'blood_group' => $patient->bloodGroup,
                'address' => $patient->address,
                'city' => $patient->city,
                'country' => $patient->country,
                'postal_code' => $patient->postalCode,
                'status' => (int) $patient->status,
                'patient_image_url' => $patient->profileImage ? wp_get_attachment_url($patient->profileImage) : '',
                'patient_image_id' => $patient->profileImage ?: null,
                'clinics' => $clinic_id,
                'created_at' => current_time('mysql'),
                'temp_password' => $patient->password,
                'patient_unique_id' => $params['patient_unique_id'] ?? null
            ];

            // Fire action hook for patient creation
            do_action('kc_patient_save', $patientData, $request);

            //New action for sms notification listener
            do_action('kivicare_patient_registered', $patientData); // For patient welcome SMS

            return $this->response($patientData, __('Patient created successfully', 'kivicare-clinic-management-system'), true, 201);

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to create patient', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }

    /**
     * Save patient clinic mapping
     * 
     * @param int $patient_id
     * @param mixed $clinic_id
     */
    public function savePatientClinic($patient_id, $clinics)
    {
        // Save/update patient clinic mappings
        if (is_array($clinics)) {
            foreach ($clinics as $value) {
                $new_temp = [
                    'patientId' => (int) $patient_id,
                    'clinicId' => (int) $value['value'],
                    'createdAt' => current_time('mysql')
                ];
                // Create patient clinic mappings
                KCPatientClinicMapping::create($new_temp);
            }
        } else {
            $new_temp = [
                'patientId' => (int) $patient_id,
                'clinicId' => $clinics,
                'createdAt' => current_time('mysql')
            ];
            // Create patient clinic mappings
            KCPatientClinicMapping::create($new_temp);
        }
    }

    /**
     * Update existing patient
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function updatePatient(WP_REST_Request $request): WP_REST_Response
    {
        try {

            $params = $request->get_params();

            $id = $request->get_param('id');

            // Find the patient
            $patient = KCPatient::find($id);

            if (!$patient) {
                return $this->response(null, __('Patient not found', 'kivicare-clinic-management-system'), false, 404);
            }

            // Check access restrictions for clinic admin and receptionist
            $current_user_role = $this->kcbase->getLoginUserRole();
            $clinic_id = null;

            if ($current_user_role === $this->kcbase->getClinicAdminRole()) {
                $clinic_id = KCClinic::getClinicIdOfClinicAdmin(get_current_user_id());
            } elseif ($current_user_role === $this->kcbase->getReceptionistRole()) {
                $clinic_id = KCClinic::getClinicIdOfReceptionist();
            }

            if ($clinic_id) {
                $patientClinicMapping = KCPatientClinicMapping::query()
                    ->where('patient_id', '=', $id)
                    ->where('clinic_id', '=', $clinic_id)
                    ->first();

                if (!$patientClinicMapping) {
                    return $this->response(null, __('Access denied. Patient not found in your clinic.', 'kivicare-clinic-management-system'), false, 403);
                }
            }

            // Check if patient unique ID already exists (excluding current patient)
            if (!empty($params['patient_unique_id'])) {
                if ($this->isPatientUniqueIdExists($params['patient_unique_id'], $id)) {
                    return $this->response(
                        null,
                        __('Patient unique ID is already used. Please use a different unique ID.', 'kivicare-clinic-management-system'),
                        false,
                        400
                    );
                }
            }

            // Check email uniqueness
            if (isset($params['email']) && $params['email'] !== $patient->email) {
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

            // Clean contact number
            $params['mobile_number'] = preg_replace('/[^0-9+]/', '', $params['mobile_number']);

            $patient->id = $params['id'];
            $patient->email = $params['email'];
            $patient->firstName = $params['first_name'];
            $patient->lastName = $params['last_name'];
            $patient->displayName = $params['first_name'] . ' ' . $params['last_name'];
            $patient->gender = $params['gender'];
            $patient->bloodGroup = $params['blood_group'];
            $patient->contactNumber = $params['mobile_number'];
            $patient->dob = $params['dob'];
            $patient->address = $params['address'];
            $patient->city = $params['city'];
            $patient->country = $params['country'];
            $patient->postalCode = $params['postal_code'];
            $patient->status = $params['status'];

            if (!empty($params['profile_image'])) {
                $patient->profileImage = (int) $params['profile_image'];
                update_user_meta($patient->id, 'patient_profile_image', $patient->profileImage);
            } else {
                delete_user_meta($patient->id, 'patient_profile_image');
            }

            // Update patient record
            if (!$patient->save()) {
                return $this->response(null, __('Failed to update patient', 'kivicare-clinic-management-system'), false, 500);
            }

            KCPatientClinicMapping::query()->where('patient_id', $patient->id)->delete();

            // Update patient clinic mapping if provided
            $this->savePatientClinic($patient->id, $params['clinics']);

            // Update patient unique ID in usermeta if provided
            if (isset($params['patient_unique_id'])) {
                if (!empty($params['patient_unique_id'])) {
                    update_user_meta($patient->id, 'patient_unique_id', sanitize_text_field($params['patient_unique_id']));
                } else {
                    delete_user_meta($patient->id, 'patient_unique_id');
                }
            }

            // Format patient data
            $patientData = [
                'id' => $patient->id,
                'first_name' => $patient->firstName,
                'last_name' => $patient->lastName,
                'email' => $patient->email,
                'contact_number' => $patient->contactNumber,
                'dob' => $patient->dob,
                'gender' => $patient->gender,
                'blood_group' => $patient->bloodGroup,
                'address' => $patient->address,
                'city' => $patient->city,
                'country' => $patient->country,
                'postal_code' => $patient->postalCode,
                'status' => (int) $patient->status,
                'patient_image_url' => $patient->profileImage ? wp_get_attachment_url($patient->profileImage) : '',
                'patient_image_id' => $patient->profileImage ?: null,
                'clinics' => $params['clinics'],
                'updated_at' => current_time('mysql'),
                'patient_unique_id' => $params['patient_unique_id'] ?? null
            ];
            do_action('kc_patient_update', $patientData, $request);

            return $this->response($patientData, __('Patient updated successfully', 'kivicare-clinic-management-system'));

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update patient', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }

    /**
     * Delete patient and all related data
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function deletePatient(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');

            // Find the patient
            $patient = KCPatient::find($id);

            if (!$patient) {
                return $this->response(null, __('Patient not found', 'kivicare-clinic-management-system'), false, 404);
            }

            KCMedicalHistory::query()->where('patient_id', $id)->delete();
            KCMedicalProblem::query()->where('patient_id', $id)->delete();
            KCPatientClinicMapping::query()->where('patient_id', $id)->delete();
            KCAppointment::query()->where('patient_id', $id)->delete();

            // Get encounter IDs for the patient
            $encounterIds = KCPatientEncounter::query()
                ->where('patient_id', $id)
                ->select(['id'])
                ->get()
                ->pluck('id')
                ->toArray();

            // Delete bills associated with these encounters
            if (!empty($encounterIds)) {
                KCBill::query()->whereIn('encounter_id', $encounterIds)->delete();
            }

            KCPatientEncounter::query()->where('patient_id', $id)->delete();

            // Delete custom field data
            KCCustomFieldData::query()
                ->where('module_type', '=', 'patient_module')
                ->where('module_id', '=', $id)
                ->delete();

            // Hook for patient delete (before actual user deletion)
            do_action('kc_patient_delete', $id);

            // Delete patient user meta
            if ($id) {
                delete_user_meta($id, 'basic_data');
                delete_user_meta($id, 'first_name');
                delete_user_meta($id, 'last_name');
                delete_user_meta($id, 'patient_profile_image');
            }

            // Delete the patient record from the patients table (if using custom table)
            $result = $patient->delete();

            if (!$result) {
                return $this->response(null, __('Failed to delete patient', 'kivicare-clinic-management-system'), false, 500);
            }

            return $this->response(
                ['id' => $id],
                __('Patient and all related data deleted successfully', 'kivicare-clinic-management-system')
            );

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to delete patient', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }
    /**`
     * Update patient status
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function updatePatientStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');
            $status = $request->get_param('status');

            $patient = KCPatient::find($id);

            if (!$patient) {
                return $this->response(null, __('Patient not found', 'kivicare-clinic-management-system'), false, 404);
            }

            $updated = $patient->updateStatus($status);

            if ($updated) {
                return $this->response(
                    ['id' => $id, 'status' => $status],
                    __('Patient status updated successfully', 'kivicare-clinic-management-system'),
                    true,
                    200
                );
            } else {
                return $this->response(
                    null,
                    __('Failed to update Patient status', 'kivicare-clinic-management-system'),
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
     * Resend patient credentials
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function resendPatientCredentials(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');

            $patient = KCPatient::find($id);

            if (!$patient) {
                return $this->response(null, __('Patient not found', 'kivicare-clinic-management-system'), false, 404);
            }

            $user_data = get_userdata($patient->id);

            if (empty($user_data)) {
                return $this->response(null, __('patient user not found', 'kivicare-clinic-management-system'), false, 404);
            }

            // Generate new password
            $password = wp_generate_password(12, true, false);
            wp_set_password($password, $user_data->ID);

            // Prepare email data
            $email_data = [
                'user_name' => $user_data->user_login,
                'user_email' => $user_data->user_email,
                'user_password' => $password,
                'patient_name' => $patient->displayName,
                'user_role' => $patient->userRole,
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
                    __('Patient credentials resent successfully', 'kivicare-clinic-management-system'),
                    true,
                    200
                );
            } else {
                return $this->response(
                    null,
                    __('Failed to resend patient credentials', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

    /**
     * Bulk delete patient
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function bulkDeletepatients(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $ids = $request->get_param('ids');

            $success_count = 0;
            $failed_count = 0;
            $failed_ids = [];

            foreach ($ids as $id) {
                $patient = KCPatient::find($id);

                if (!$patient) {
                    $failed_count++;
                    $failed_ids[] = [
                        'id' => $id,
                        'reason' => __('Patient not found', 'kivicare-clinic-management-system')
                    ];
                    continue;
                }

                // Delete receptionist clinic mappings
                KCPatientClinicMapping::table('pcm')
                    ->where('patientId', '=', $id)
                    ->delete();

                // Delete the receptionist record
                $result = $patient->delete();

                if (!$result) {
                    $failed_count++;
                    $failed_ids[] = [
                        'id' => $id,
                        'reason' => __('Failed to delete patient', 'kivicare-clinic-management-system')
                    ];
                    continue;
                }

                // Delete WordPress user
                wp_delete_user($patient->ID);

                $success_count++;
            }

            if ($success_count > 0) {
                return $this->response(
                    [
                        'success_count' => $success_count,
                        'failed_count' => $failed_count,
                        'failed_ids' => $failed_ids
                    ],
                    /* translators: %d: number of patients */
                    sprintf(__('%d patient deleted successfully', 'kivicare-clinic-management-system'), $success_count),
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
                    __('Failed to delete patient', 'kivicare-clinic-management-system'),
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
                'description' => 'Array of patient IDs',
                'type' => 'array',
                'items' => [
                    'type' => 'integer',
                ],
                'required' => true,
                'validate_callback' => function ($param) {
                    if (!is_array($param) || empty($param)) {
                        return new WP_Error('invalid_ids', __('Patient IDs are required', 'kivicare-clinic-management-system'));
                    }
                    foreach ($param as $id) {
                        if (!is_numeric($id) || intval($id) <= 0) {
                            return new WP_Error('invalid_id', __('Invalid patient ID in array', 'kivicare-clinic-management-system'));
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
                $patient = KCPatient::find($id);
                if (!$patient) {
                    $failed_count++;
                    $failed_ids[] = $id;
                    continue;
                }

                $user_data = get_userdata($patient->id);

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
                    'patient_name' => $patient->displayName,
                    'user_role' => $patient->userRole,
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
                    /* translators: %d: number of patients */
                    sprintf(__('Credentials resent for %d patient', 'kivicare-clinic-management-system'), $success_count),
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
     * Bulk update patient status
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function bulkUpdatePatientStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $ids = $request->get_param('ids');
            $status = $request->get_param('status');

            $success_count = 0;
            $failed_count = 0;
            $failed_ids = [];

            foreach ($ids as $id) {
                $patient = KCPatient::find($id);

                if (!$patient) {
                    $failed_count++;
                    $failed_ids[] = $id;
                    continue;
                }

                $updated = $patient->updateStatus($status);

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
                    /* translators: %d: number of patients */
                    sprintf(__('Status updated for %d patient', 'kivicare-clinic-management-system'), $success_count),
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
                    __('Failed to update patient status', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

    /**
     * Get arguments for the bulk delete endpoint
     *
     * @return array
     */
    private function getBulkDeleteEndpointArgs()
    {
        return [
            'ids' => [
                'description' => __('Array of receptionist IDs to delete', 'kivicare-clinic-management-system'),
                'type' => 'array',
                'required' => true,
                'items' => [
                    'type' => 'integer',
                ],
                'validate_callback' => function ($param) {
                    if (!is_array($param) || empty($param)) {
                        return new WP_Error('invalid_parameter', __('IDs parameter must be a non-empty array', 'kivicare-clinic-management-system'));
                    }
                    return true;
                }
            ]
        ];
    }

    /**
     * Generate unique patient ID with proper recursion handling
     * 
     * @return string
     */
    public function generatePatientUniqueIdRegister()
    {
        $max_attempts = 10; // Prevent infinite loops
        $attempts = 0;

        do {
            $patient_unique_id = $this->kcPatientUniqueIdEnable('value');
            
            // Use the first() method. It returns an object if found, or null if not.
            $patient_unique_id_exist = KCUserMeta::query()
                ->where('metaKey', 'patient_unique_id')
                ->where('metaValue', $patient_unique_id)
                ->first();

            $attempts++;

            // If the result is null (empty), the ID is available.
            if (!$patient_unique_id_exist) {
                return $patient_unique_id;
            }

            // If we've tried too many times, add timestamp to make it unique
            if ($attempts >= $max_attempts) {
                $patient_unique_id .= '_' . time();
                break;
            }

        } while ($patient_unique_id_exist && $attempts < $max_attempts);

        return $patient_unique_id;
    }

    /**
     * Check if patient unique ID is enabled and generate ID
     * 
     * @param string $type 'status' or 'value'
     * @return mixed
     */
    public function kcPatientUniqueIdEnable($type)
    {
        $get_unique_id = KCOption::get('patient_id_setting', []);
        $status = false;
        $patient_uid = '';

        if (!empty($get_unique_id)) {
            $status = !empty($get_unique_id['enable']) && in_array((string) $get_unique_id['enable'], ['1', 'true']);
            $randomValue = $this->kcGenerateString(6);

            if (!empty($get_unique_id['only_number']) && in_array((string) $get_unique_id['only_number'], ['1', 'true']) && $get_unique_id['only_number'] !== 'false') {
                $randomValue = sprintf("%06d", wp_rand(1, 999999));
            }

            if (!empty($get_unique_id['prefix_value'])) {
                $patient_uid .= $get_unique_id['prefix_value'] . $randomValue;
            } else {
                $patient_uid .= $randomValue;
            }

            if (!empty($get_unique_id['postfix_value'])) {
                $patient_uid .= $get_unique_id['postfix_value'];
            }
        }

        if ($type === 'status') {
            return $status;
        }

        return $patient_uid;
    }

    /**
     * Generate random string
     * 
     * @param int $length_of_string
     * @return string
     */
    public function kcGenerateString($length_of_string = 10)
    {
        // String of all alphanumeric character
        $str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        return substr(str_shuffle($str_result), 0, $length_of_string);
    }

    /**
     * Check if patient unique ID already exists
     * 
     * @param string $uniqueId
     * @param int $excludeUserId Optional user ID to exclude from check (for updates)
     * @return bool
     */
    private function isPatientUniqueIdExists($uniqueId, $excludeUserId = null)
    {
        /** @var \App\baseClasses\KCQueryBuilder $queryBuilder */
        $queryBuilder = KCUserMeta::query()
            ->where('metaKey', 'patient_unique_id')
            ->where('metaValue', $uniqueId)
            ->when($excludeUserId, function ($q) use ($excludeUserId) {
                $q->where('userId', '!=', (int) $excludeUserId);
            });

        // Use first() to check if at least one record exists.
        $result = $queryBuilder->first();

        // Return true if a result was found, false otherwise.
        return !empty($result);
    }

    /**
     * Get patient unique ID
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getPatientUniqueId(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Check if patient unique ID is enabled
            $isEnabled = $this->kcPatientUniqueIdEnable('status');

            if (!$isEnabled) {
                return $this->response(
                    ['unique_id' => null, 'enabled' => false],
                    __('Patient unique ID is not enabled', 'kivicare-clinic-management-system'),
                    false,
                    200
                );
            }

            // Generate unique patient ID
            $uniqueId = $this->generatePatientUniqueIdRegister();

            return $this->response(
                [
                    'unique_id' => $uniqueId,
                    'enabled' => true
                ],
                __('Patient unique ID generated successfully', 'kivicare-clinic-management-system')
            );

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to generate patient unique ID', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Export patients data
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function exportPatients(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Get request parameters
            $params = $request->get_params();
            $format = $params['format'];

            // Use the same query logic as getPatients but without pagination
            $search = isset($params['search']) ? sanitize_text_field($params['search']) : '';
            $clinic_id = isset($params['clinic']) ? (int) $params['clinic'] : null;
            $status = isset($params['status']) ? (int) $params['status'] : null;

            // Build the base query for patients - using exact same structure as getPatients
            $query = KCPatient::table('p')
                ->select([
                    "p.*",
                    "GROUP_CONCAT(DISTINCT pcm.clinic_id) as clinic_ids",
                    "GROUP_CONCAT(DISTINCT c.name) as clinic_names",
                    "bd.meta_value as basic_data",
                    "pi.meta_value as profile_image",
                    "puid.meta_value as patient_unique_id"
                ])
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'bd.user_id')
                        ->onRaw("bd.meta_key = 'basic_data'");
                }, null, null, 'bd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'pi.user_id')
                        ->onRaw("pi.meta_key = 'patient_profile_image'");
                }, null, null, 'pi')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'puid.user_id')
                        ->onRaw("puid.meta_key = 'patient_unique_id'");
                }, null, null, 'puid')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'fn.user_id')
                        ->onRaw("fn.meta_key = 'first_name'");
                }, null, null, 'fn')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'ln.user_id')
                        ->onRaw("ln.meta_key = 'last_name'");
                }, null, null, 'ln')
                ->leftJoin(KCPatientClinicMapping::class, 'p.ID', '=', 'pcm.patient_id', 'pcm')
                ->leftJoin(KCClinic::class, 'pcm.clinic_id', '=', 'c.id', 'c')
                ->groupBy('p.ID');

            // Apply doctor filtering for export if current user is doctor
            $current_user_role = $this->kcbase->getLoginUserRole();
            $current_user_id = get_current_user_id();

            if ($current_user_role === $this->kcbase->getDoctorRole()) {
                $query->leftJoin(KCAppointment::class, 'p.ID', '=', 'app.patient_id', 'app')
                    ->leftJoin(KCUserMeta::class, function ($join) {
                        $join->on('p.ID', '=', 'pab.user_id')
                            ->onRaw("pab.meta_key = 'patient_added_by'");
                    }, null, null, 'pab')
                    ->where(function ($q) use ($current_user_id) {
                        $q->where('app.doctor_id', '=', $current_user_id)
                            ->orWhere('pab.meta_value', '=', $current_user_id);
                    });
            } elseif ($current_user_role === $this->kcbase->getClinicAdminRole()) {
                // For clinic admins, show only patients from their clinic
                $clinic_id = KCClinic::getClinicIdOfClinicAdmin($current_user_id);
                if ($clinic_id) {
                    $query->where('pcm.clinic_id', '=', $clinic_id);
                }
            }

            // Apply filters - same as getPatients
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where("p.display_name", 'LIKE', '%' . $search . '%')
                        ->orWhere("fn.meta_value", 'LIKE', '%' . $search . '%')
                        ->orWhere("ln.meta_value", 'LIKE', '%' . $search . '%')
                        ->orWhere("p.user_email", 'LIKE', '%' . $search . '%')
                        ->orWhere("p.ID", 'LIKE', '%' . $search . '%')
                        ->orWhere("c.name", 'LIKE', '%' . $search . '%')
                        ->orWhere("bd.meta_value", 'LIKE', '%' . $search . '%');
                });
            }

            if ($clinic_id) {
                $query->where('pcm.clinic_id', '=', $clinic_id);
            }

            if ($status !== null) {
                $query->where('p.user_status', '=', $status);
            }

            // Apply doctor filter for export as well
            $doctor_id = isset($params['doctor_id']) ? (int) $params['doctor_id'] : null;
            if ($doctor_id) {
                $query->join(KCAppointment::class, 'p.ID', '=', 'app.patient_id', 'app')
                    ->where('app.doctor_id', '=', $doctor_id);
            }

            // Execute query
            $results = $query->get();

            if (empty($results)) {
                return $this->response(
                    ['patients' => []],
                    __('No patients found', 'kivicare-clinic-management-system'),
                    true,
                    200
                );
            }

            // Process results for export
            $exportData = [];
            foreach ($results as $patient) {
                // Parse basic data
                $basicData = !empty($patient->basic_data) ? json_decode($patient->basic_data, true) : [];

                // Get profile image URL
                $profileImageUrl = '';
                if (!empty($patient->profile_image)) {
                    $profileImageUrl = wp_get_attachment_url($patient->profile_image);
                }

                // Extract data from basic_data
                $mobile = isset($basicData['mobile_number']) ? $basicData['mobile_number'] : '';
                $gender = isset($basicData['gender']) ? $basicData['gender'] : '';
                $dob = isset($basicData['dob']) ? $basicData['dob'] : '';
                $bloodGroup = isset($basicData['blood_group']) ? $basicData['blood_group'] : '';

                // Build full address
                $addressParts = [];
                if (!empty($basicData['address']))
                    $addressParts[] = $basicData['address'];
                if (!empty($basicData['city']))
                    $addressParts[] = $basicData['city'];
                if (!empty($basicData['state']))
                    $addressParts[] = $basicData['state'];
                if (!empty($basicData['country']))
                    $addressParts[] = $basicData['country'];
                if (!empty($basicData['postal_code']))
                    $addressParts[] = $basicData['postal_code'];
                $fullAddress = !empty($addressParts) ? implode(', ', $addressParts) : '#';

                // Get patient name
                $firstName = '';
                $lastName = '';
                if (!empty($basicData['first_name'])) {
                    $firstName = $basicData['first_name'];
                } else {
                    // Fallback to display_name parsing
                    $nameParts = explode(' ', $patient->display_name);
                    $firstName = $nameParts[0] ?? '';
                    $lastName = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';
                }
                if (!empty($basicData['last_name'])) {
                    $lastName = $basicData['last_name'];
                }
                $fullName = trim($firstName . ' ' . $lastName) ?: $patient->display_name;

                $exportData[] = [
                    'id' => $patient->patient_unique_id ?: $patient->id,
                    'patient_image_url' => $profileImageUrl ?: '-',
                    'name' => $fullName ?: '-',
                    'clinic' => $patient->clinic_names ?: '-',
                    'email' => $patient->email ?: '-',
                    'mobile' => $mobile ?: '-',
                    'gender' => $gender ?: '-',
                    'dob' => $dob ?: '-',
                    'blood_group' => $bloodGroup ?: '-',
                    'registered_on' => $patient->user_registered ? gmdate('Y-m-d', strtotime($patient->user_registered)) : '-',
                    'status' => $patient->user_status == 0 ? 'Active' : 'Inactive',
                    'address' => $fullAddress
                ];
            }

            return $this->response(
                ['patients' => $exportData],
                __('Patients data retrieved successfully', 'kivicare-clinic-management-system'),
                true,
                200
            );

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to export patients data', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }

    /**
     * Get custom field data for patient
     * 
     * @param int $module_id Patient ID
     * @param string $module_type Module type (patient_module)
     * @return array Custom field data formatted for frontend
     */
    private function getCustomFieldData($module_id, $module_type = 'patient_module')
    {
        try {
            // Get all custom field data for this patient
            $customFieldDataRecords = KCCustomFieldData::query()
                ->where('module_type', '=', $module_type)
                ->where('module_id', '=', $module_id)
                ->get();

            $customFieldData = [];

            foreach ($customFieldDataRecords as $record) {
                // Decode the field value if it's JSON
                $value = $record->fieldsData;
                if (is_string($value) && $this->isJson($value)) {
                    $value = json_decode($value, true);
                }

                $customFieldData[] = [
                    'id' => $record->fieldId,
                    'value' => $value
                ];
            }

            return $customFieldData;
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Failed to get custom field data: ' . $e->getMessage());
            return [];
        }
    }

    public function getPatientStatistics(WP_REST_Request $request): WP_REST_Response
    {
        $patientId = (int) $request->get_param('id');

        try {
            // Total appointments
            $totalAppointments = KCAppointment::query()
                ->where('patient_id', '=', $patientId)
                ->count();

            // Completed appointments
            $completedAppointments = KCAppointment::query()
                ->where('patient_id', '=', $patientId)
                ->where('status', '=', KCAppointment::STATUS_CHECK_OUT)
                ->count();

            // Total encounters
            $totalEncounters = KCPatientEncounter::query()
                ->where('patient_id', '=', $patientId)
                ->count();

            // Get all encounter IDs for the patient
            $encounterIds = KCPatientEncounter::query()
                ->select(['id'])
                ->where('patient_id', '=', $patientId)
                ->get()
                ->pluck('id')
                ->toArray();

            // Calculate total billing amount
            $totalBillingAmount = 0;
            if (!empty($encounterIds)) {
                $bills = KCBill::query()
                    ->whereIn('encounter_id', $encounterIds)
                    ->get();

                foreach ($bills as $bill) {
                    $amount = floatval($bill->actualAmount ?: $bill->totalAmount ?: 0);
                    $totalBillingAmount += $amount;
                }
            }

            $statistics = [
                'total_appointments' => (int) $totalAppointments,
                'completed_appointments' => (int) $completedAppointments,
                'total_encounters' => (int) $totalEncounters,
                'total_billing_amount' => round($totalBillingAmount, 2),
            ];

            return $this->response(
                ['statistics' => $statistics],
                __('Patient statistics retrieved successfully', 'kivicare-clinic-management-system'),
                true,
                200
            );
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve patient statistics', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }


    /**
     * Check if a string is valid JSON
     * 
     * @param string $string
     * @return bool
     */
    private function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

}
