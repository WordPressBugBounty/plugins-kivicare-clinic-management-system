<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\models\KCClinic;
use App\models\KCReceptionist;
use App\models\KCPatient;
use App\models\KCReceptionistClinicMapping;
use App\models\KCUserMeta;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_REST_Server;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class ReceptionistsController
 * 
 * API Controller for Receptionist-related endpoints
 * 
 * @package App\controllers\api
 */
class ReceptionistsController extends KCBaseController
{

    /**
     * @var string The base route for this controller
     */
    protected $route = 'receptionists';

    /**
     * Get common arguments used across multiple endpoints
     *
     * @return array
     */
    private function getCommonArgs()
    {
        return [
            'id' => [
                'description' => __('Receptionist ID', 'kivicare-clinic-management-system'),
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
            'first_name' => [
                'description' => __('First name', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'validate_callback' => [$this, 'validateName'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'last_name' => [
                'description' => __('Last name', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'validate_callback' => [$this, 'validateName'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'email' => [
                'description' => __('Email address', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'validate_callback' => [$this, 'validateEmail'],
                'sanitize_callback' => 'sanitize_email',
            ],
            'clinic_id' => [
                'description' => __('Clinic ID', 'kivicare-clinic-management-system'),
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
            'contact_number' => [
                'description' => __('Contact number', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'validate_callback' => [$this, 'validateContact'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'description' => __('Status (0: Inactive, 1: Active, 2: Pending, 3: Suspended)', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'validate_callback' => [$this, 'validateStatus'],
                'sanitize_callback' => 'sanitize_text_field',
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
     * Validate name
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

        if (strlen($param) > 100) {
            return new WP_Error('invalid_name', __('Name cannot exceed 100 characters', 'kivicare-clinic-management-system'));
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
     * Validate status
     *
     * @param mixed $param
     * @return bool|WP_Error
     */
    public function validateStatus($param)
    {
        // Handle status as an object from frontend { label: 'Active', value: 0 }
        if (is_array($param) && isset($param['value'])) {
            $param = $param['value'];
        } else if (is_string($param) && (strpos($param, '{') === 0 || strpos($param, '[') === 0)) {
            // Handle JSON string
            $decoded = json_decode($param, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['value'])) {
                $param = $decoded['value'];
            }
        }

        // Convert to integer for comparison if it's numeric
        if (is_numeric($param)) {
            $param = (int) $param;
        }

        $valid_statuses = [0, 1];

        if (!in_array($param, $valid_statuses, true)) {
            return new WP_Error('invalid_status', __('Invalid status value. Must be 0 or 1.', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        // Get all receptionists
        $this->registerRoute('/' . $this->route, [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getReceptionists'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getListEndpointArgs()
        ]);

        // Create new receptionist (existing code)
        $this->registerRoute('/' . $this->route, [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'createReceptionist'],
            'permission_callback' => [$this, 'checkCreatePermission'],
            'args' => $this->getCreateEndpointArgs()
        ]);

        // Get single receptionist
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getReceptionist'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getSingleEndpointArgs()
        ]);

        // Update receptionist
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'updateReceptionist'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getUpdateEndpointArgs()
        ]);

        // Delete receptionist
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'deleteReceptionist'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getDeleteEndpointArgs()
        ]);

        // Bulk delete receptionists
        $this->registerRoute('/' . $this->route . '/bulk-delete', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'bulkDeleteReceptionists'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getBulkDeleteEndpointArgs()
        ]);

        // Update receptionist status
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/status', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'updateReceptionistStatus'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getStatusUpdateEndpointArgs()
        ]);

        // Resend receptionist credentials
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/resend-credentials', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'resendReceptionistCredentials'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getSingleEndpointArgs()
        ]);

        // Bulk delete receptionists
        $this->registerRoute('/' . $this->route . '/bulk/delete', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'bulkDeleteReceptionists'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getBulkDeleteEndpointArgs()
        ]);

        // Bulk resend credentials
        $this->registerRoute('/' . $this->route . '/bulk/resend-credentials', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'bulkResendCredentials'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getBulkDeleteEndpointArgs()
        ]);

        // Bulk update receptionist status
        $this->registerRoute('/' . $this->route . '/bulk/status', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'bulkUpdateReceptionistStatus'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getBulkStatusUpdateEndpointArgs()
        ]);

        // Export receptionists data
        $this->registerRoute('/' . $this->route . '/export', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'exportReceptionists'],
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
                'description' => __('Search term to filter results', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'page' => [
                'description' => __('Current page of results', 'kivicare-clinic-management-system'),
                'type' => 'integer',
                'default' => 1,
                'sanitize_callback' => 'absint',
            ],
            'perPage' => [
                'description' => __('Number of results per page', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'default' => 10,
                'sanitize_callback' => function ($param) {
                    return strtolower($param) === 'all' ? 'all' : absint($param);
                },
            ],
            'clinic' => [
                'description' => __('Filter by clinic ID', 'kivicare-clinic-management-system'),
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'status' => [
                'description' => __('Filter by status', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'orderBy' => [
                'description' => __('Field to sort by', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'order' => [
                'description' => __('Sort direction (asc or desc)', 'kivicare-clinic-management-system'),
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
        return [
            'first_name' => array_merge($this->getCommonArgs()['first_name'], ['required' => true]),
            'last_name' => array_merge($this->getCommonArgs()['last_name'], ['required' => true]),
            'email' => array_merge($this->getCommonArgs()['email'], ['required' => true]),
            'contact_number' => array_merge($this->getCommonArgs()['contact_number'], ['required' => true]),
            'dob' => [
                'description' => __('Date of birth', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'gender' => [
                'description' => __('Gender', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'address' => [
                'description' => __('Address', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'city' => [
                'description' => __('City', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'country' => [
                'description' => __('Country', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'postal_code' => [
                'description' => __('Postal code', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => array_merge($this->getCommonArgs()['status'], ['default' => 'active']),
            'receptionist_image' => [
                'description' => __('Receptionist profile image URL', 'kivicare-clinic-management-system'),
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
            ],
            'receptionist_image_id' => [
                'description' => __('Receptionist profile image ID', 'kivicare-clinic-management-system'),
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ]
        ];
    }


    /**
     * Check if user has permission to access receptionist endpoints
     * 
     * @param WP_REST_Request $request
     * @return bool
     */
    public function checkPermission($request)
    {
        if (!$this->isModuleEnabled('receptionist')) {
            return false;
        }

        // Check basic read permission
        if (!$this->checkCapability('read')) {
            return false;
        }

        $method = $request->get_method();
        $current_user_id = get_current_user_id();
        $current_user_role = $this->kcbase->getLoginUserRole();

        // If it's a receptionist role and accessing single receptionist endpoint
        if ($current_user_role === $this->kcbase->getReceptionistRole() && $method === 'GET') {
            $receptionist_id = $request->get_param('id');
            // Allow receptionist to access their own data
            if ($receptionist_id && intval($receptionist_id) === $current_user_id) {
                return true;
            }
        }

        // Check resource access
        return $this->checkResourceAccess('receptionist', 'view');
    }

    /**
     * Check if user has permission to create a receptionist
     * 
     * @param WP_REST_Request $request
     * @return bool
     */
    public function checkCreatePermission($request)
    {

        if (!$this->isModuleEnabled('receptionist')) {
            return false;
        }

        // Check basic read permission
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Check create permission
        return $this->checkResourceAccess('receptionist', 'add');
    }

    /**
     * Check if user has permission to update a receptionist
     * 
     * @param WP_REST_Request $request
     * @return bool
     */
    public function checkUpdatePermission($request)
    {

        if (!$this->isModuleEnabled('receptionist')) {
            return false;
        }

        // Check basic read permission
        if (!$this->checkCapability('read')) {
            return false;
        }

        $current_user_id = get_current_user_id();
        $current_user_role = $this->kcbase->getLoginUserRole();

        // If it's a receptionist role, allow them to update their own profile
        if ($current_user_role === $this->kcbase->getReceptionistRole()) {
            $receptionist_id = $request->get_param('id');
            // Allow receptionist to update their own data
            if ($receptionist_id && intval($receptionist_id) === $current_user_id) {
                return true;
            }
        }

        // Check update permission
        return $this->checkResourceAccess('receptionist', 'edit');
    }

    /**
     * Check if user has permission to delete a receptionist
     * 
     * @param WP_REST_Request $request
     * @return bool
     */
    public function checkDeletePermission($request)
    {

        if (!$this->isModuleEnabled('receptionist')) {
            return false;
        }

        // Check basic read permission
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Check delete permission
        return $this->checkResourceAccess('receptionist', 'delete');
    }

    public function updateReceptionist(WP_REST_Request $request)
    {
        try {
            $params = $request->get_params();
            $id = (int) $params['id'];

            // Validate ID
            if (!$this->validateId($id)) {
                return $this->response(null, __('Invalid receptionist ID', 'kivicare-clinic-management-system'), false, 400);
            }

            // Find the receptionist
            $receptionist = KCReceptionist::find($id);
            if (!$receptionist) {
                return $this->response(null, __('Receptionist not found', 'kivicare-clinic-management-system'), false, 404);
            }

            // Check if email already exists
            $email_exists = email_exists($params['email']);
            if ($email_exists && $email_exists != $id) {
                return $this->response(
                    null,
                    __('Email already exists', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Update receptionist properties
            $receptionist->firstName = sanitize_text_field($params['first_name']);
            $receptionist->lastName = sanitize_text_field($params['last_name']);
            $receptionist->displayName = $params['first_name'] . ' ' . $params['last_name'];
            $receptionist->email = sanitize_email($params['email']);
            $receptionist->contactNumber = sanitize_text_field($params['contact_number']);
            $receptionist->dob = sanitize_text_field($params['dob']);
            $receptionist->address = sanitize_text_field($params['address']);
            $receptionist->city = sanitize_text_field($params['city']);
            $receptionist->country = sanitize_text_field($params['country']);
            $receptionist->postalCode = sanitize_text_field($params['postal_code']);
            $receptionist->gender = $params['gender'];
            $receptionist->status = $params['status'];

            // Handle profile image update/removal
            if (!empty($params['receptionist_image_id'])) {
                // New image selected
                $receptionist->profileImage = (int) $params['receptionist_image_id'];
                if (!empty($receptionist->id)) {
                    update_user_meta($receptionist->id, 'receptionist_profile_image', (int) $params['receptionist_image_id']);
                }
            } else {
                // No image ID provided → remove image meta (if any)
                if (!empty($receptionist->id)) {
                    delete_user_meta($receptionist->id, 'receptionist_profile_image');
                }
                $receptionist->profileImage = null;
            }

            // Save the receptionist
            if (!$receptionist->save()) {
                return $this->response(
                    null,
                    __('Failed to update receptionist', 'kivicare-clinic-management-system'),
                    false,
                    500
                );
            }

            // Get the WordPress user ID from the email - we need this since save() returns boolean
            $user = get_user_by('email', $params['email']);
            if (!$user) {
                return $this->response(
                    null,
                    __('Receptionist created but user data not found', 'kivicare-clinic-management-system'),
                    false,
                    500
                );
            }

            // Set the ID from the WordPress user
            $receptionistId = $user->ID;

            $current_user_role = $this->kcbase->getLoginUserRole();
            if (isKiviCareProActive()) {
                if ($current_user_role == $this->kcbase->getClinicAdminRole()) {
                    $params['clinic_id'] = KCClinic::getClinicIdOfClinicAdmin();
                } elseif ($current_user_role == $this->kcbase->getReceptionistRole()) {
                    $params['clinic_id'] = KCClinic::getClinicIdOfReceptionist();
                } else {
                    $params['clinic_id'] = (int) $params['clinic_id'];
                }
            } else {
                // Default clinic id if pro not active
                $params['clinic_id'] = KCClinic::kcGetDefaultClinicId();
            }

            // First delete all existing clinic mappings for this receptionist
            KCReceptionistClinicMapping::table('rcm')
                ->where('receptionist_id', '=', $id)
                ->delete();
            // Save receptionist clinic mapping
            $this->saveReceptionist($receptionistId, $params['clinic_id']);

            // Build response
            $receptionistData = [
                'id' => $receptionistId,
                'first_name' => $params['first_name'],
                'last_name' => $params['last_name'],
                'email' => $params['email'],
                'clinic_id' => $params['clinic_id'],
                'contact_number' => $params['contact_number'],
                'gender' => $params['gender'],
                'dob' => $params['dob'],
                'address' => $params['address'],
                'city' => $params['city'],
                'country' => $params['country'],
                'postal_code' => $params['postal_code'],
                'status' => $params['status'] ?? 1,
                'receptionist_image_url' => get_user_meta($receptionistId, 'receptionist_profile_image', true) ? wp_get_attachment_url((int) get_user_meta($receptionistId, 'receptionist_profile_image', true)) : '',
                'receptionist_image_id' => $params['receptionist_image_id'] ?? null,
                'created_at' => current_time('mysql')
            ];

            do_action('kc_receptionist_update', $receptionistData);

            return $this->response($receptionistData, __('Receptionist updated successfully', 'kivicare-clinic-management-system'), true, 201);
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update receptionist', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }

    /**
     * Delete a receptionist
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function deleteReceptionist(WP_REST_Request $request)
    {
        try {
            $id = (int) $request->get_param('id');
            // Find the clinic
            $receptionist = KCReceptionist::find($id);

            if (!$receptionist) {
                return $this->response(null, __('Receptionist not found', 'kivicare-clinic-management-system'), false, 404);
            }
            // print_r( KCReceptionistClinicMapping::table('rcm'));
            // First, delete the receptionist clinic mappings
            KCReceptionistClinicMapping::table('rcm')
                ->where('receptionist_id', '=', $id)
                ->delete();

            // Now delete the receptionist using the model's delete method
            $receptionist = new KCReceptionist();
            $receptionist->id = $id; // Set the ID property
            $result = $receptionist->delete(); // This will use the delete method from KCReceptionist

            if ($result) {
                do_action('kc_receptionist_delete', $id);
                return $this->response(
                    ['id' => $id],
                    __('Receptionist deleted successfully', 'kivicare-clinic-management-system'),
                    true
                );
            } else {
                return $this->response(
                    null,
                    __('Failed to delete receptionist', 'kivicare-clinic-management-system'),
                    false,
                    500
                );
            }

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to delete receptionist', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Create new receptionist
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function createReceptionist(WP_REST_Request $request)
    {
        try {
            $params = $request->get_params();

            // Check if email already exists
            if (email_exists($params['email'])) {
                return $this->response(
                    null,
                    __('Email already exists', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Determine clinic ID based on user role
            $current_user_role = $this->kcbase->getLoginUserRole();
            if (isKiviCareProActive()) {
                if ($current_user_role == $this->kcbase->getClinicAdminRole()) {
                    $params['clinic_id'] = KCClinic::getClinicIdOfClinicAdmin();
                } elseif ($current_user_role == $this->kcbase->getReceptionistRole()) {
                    $params['clinic_id'] = KCClinic::getClinicIdOfReceptionist();
                } else {
                    $params['clinic_id'] = (int) $params['clinic_id'];
                }
            } else {
                // Default clinic id if pro not active
                $params['clinic_id'] = KCClinic::kcGetDefaultClinicId();
            }

            // Validate clinic exists
            if (isset($params['clinic_id'])) {
                $clinic = KCClinic::find($params['clinic_id']);
                if (!$clinic) {
                    return $this->response(
                        null,
                        __('Selected clinic does not exist', 'kivicare-clinic-management-system'),
                        false,
                        400
                    );
                }
            }

            // Create new KCReceptionist instance
            $receptionist = new KCReceptionist();

            $username = kcGenerateUsername($params['first_name']);
            $password = wp_generate_password(12, true, true);

            $receptionist->username = $username;
            $receptionist->password = $password;
            $receptionist->email = sanitize_email($params['email']);
            $receptionist->firstName = $params['first_name'];
            $receptionist->lastName = $params['last_name'];
            $receptionist->displayName = $params['first_name'] . ' ' . $params['last_name'];
            $receptionist->gender = $params['gender'];
            $receptionist->contactNumber = $params['contact_number'];
            $receptionist->dob = $params['dob'];
            $receptionist->address = $params['address'];
            $receptionist->city = $params['city'];
            $receptionist->country = $params['country'];
            $receptionist->postalCode = $params['postal_code'];
            $receptionist->status = $params['status'];

            // Add profile image if provided
            if (!empty($params['receptionist_image_id'])) {
                $receptionist->profileImage = (int) $params['receptionist_image_id'];
            }

            // Save the receptionist - the method returns boolean, not ID
            if (!$receptionist->save()) {
                return $this->response(
                    null,
                    __('Failed to create receptionist', 'kivicare-clinic-management-system'),
                    false,
                    500
                );
            }

            // Get the WordPress user ID from the email - we need this since save() returns boolean
            $user = get_user_by('email', $params['email']);
            if (!$user) {
                return $this->response(
                    null,
                    __('Receptionist created but user data not found', 'kivicare-clinic-management-system'),
                    false,
                    500
                );
            }

            // Set the ID from the WordPress user
            $receptionistId = $user->ID;

            // Save receptionist clinic mapping
            $this->saveReceptionist($receptionistId, $params['clinic_id']);

            // Build response
            $receptionistData = [
                'id' => $receptionistId,
                'first_name' => $params['first_name'],
                'last_name' => $params['last_name'],
                'email' => $params['email'],
                'user_name' => $receptionist->username,
                'user_password' => $receptionist->password,
                'clinic_id' => $params['clinic_id'],
                'contact_number' => $params['contact_number'],
                'gender' => $params['gender'],
                'dob' => $params['dob'],
                'address' => $params['address'],
                'city' => $params['city'],
                'country' => $params['country'],
                'postal_code' => $params['postal_code'],
                'status' => $params['status'] ?? 1,
                'receptionist_image_url' => get_user_meta($receptionistId, 'receptionist_profile_image', true) ? wp_get_attachment_url((int) get_user_meta($receptionistId, 'receptionist_profile_image', true)) : '',
                'receptionist_image_id' => $params['receptionist_image_id'] ?? null,
                'created_at' => current_time('mysql')
            ];

            // Fire action hook for patient creation
            do_action('kc_receptionist_save', $receptionistData);
            do_action('kc_receptionist_created', $receptionistData);

            return $this->response($receptionistData, __('Receptionist created successfully', 'kivicare-clinic-management-system'), true, 201);
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to create receptionist', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }

    /**
     * Get all receptionists
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getReceptionists(WP_REST_Request $request)
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
            $receptionist_id = isset($params['id']) ? (int) $params['id'] : null;
            $search = isset($params['search']) ? sanitize_text_field($params['search']) : '';
            $clinic = isset($params['clinic']) ? (int) $params['clinic'] : null;
            $receptionistName = isset($params['receptionistName']) ? sanitize_text_field($params['receptionistName']) : '';
            $receptionistAddress = isset($params['receptionistAddress']) ? sanitize_text_field($params['receptionistAddress']) : null;
            $status = isset($params['status']) ? (int) ($params['status']) : null;
            $orderBy = isset($params['orderBy']) ? sanitize_text_field($params['orderBy']) : 'ID';
            $order = isset($params['order']) ? strtoupper(sanitize_text_field($params['order'])) : 'DESC';

            $current_user_role = $this->kcbase->getLoginUserRole();
            if ($current_user_role === $this->kcbase->getClinicAdminRole()) {
                $clinic = KCClinic::getClinicIdOfClinicAdmin();
            }

            // Build the base query for receptionists
            $query = KCReceptionist::table('r')
                ->select([
                    "r.*",
                    "um_first.meta_value as first_name",
                    "um_last.meta_value as last_name",
                    "bd.meta_value as basic_data",
                    "pi.meta_value as profile_image_id",
                    "c.name as clinic_name",
                    "c.email as clinic_email",
                    "c.id as clinic_id",
                    "c.profile_image as clinic_profile_image",
                ])
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('r.ID', '=', 'um_first.user_id')
                        ->onRaw("um_first.meta_key = 'first_name'");
                }, null, null, 'um_first')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('r.ID', '=', 'um_last.user_id')
                        ->onRaw("um_last.meta_key = 'last_name'");
                }, null, null, 'um_last')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('r.ID', '=', 'bd.user_id')
                        ->onRaw("bd.meta_key = 'basic_data'");
                }, null, null, 'bd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('r.ID', '=', 'pi.user_id')
                        ->onRaw("pi.meta_key = 'receptionist_profile_image'");
                }, null, null, 'pi');

            // If you need to filter by clinic
            if ($clinic) {
                $query->join(KCReceptionistClinicMapping::class, 'r.ID', '=', 'rcm.receptionist_id', 'rcm')
                    ->join(KCClinic::class, 'rcm.clinic_id', '=', 'c.id', 'c')
                    ->where('c.id', '=', $clinic)
                    ->groupBy('r.ID');
            } else {
                // Optional left join if you want clinic info for all receptionists
                $query->leftJoin(KCReceptionistClinicMapping::class, 'r.ID', '=', 'rcm.receptionist_id', 'rcm')
                    ->leftJoin(KCClinic::class, 'rcm.clinic_id', '=', 'c.id', 'c')
                    ->groupBy('r.ID');
            }
            if(!isKiviCareProActive()){
                if($this->kcbase->getLoginUserRole() == 'administrator'){
                    $query->where('c.id','=',KCClinic::kcGetDefaultClinicId());
                }
            }
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where("um_first.meta_value", 'LIKE', '%' . $search . '%')
                        ->orWhere("r.display_name", 'LIKE', '%' . $search . '%')
                        ->orWhere("um_last.meta_value", 'LIKE', '%' . $search . '%')
                        ->orWhere("r.user_email", 'LIKE', '%' . $search . '%')
                        ->orWhere("c.name", 'LIKE', '%' . $search . '%')
                        ->orWhere("r.id", 'LIKE', '%' . $search . '%')
                        ->orWhere("bd.meta_value", 'LIKE', '%' . $search . '%');
                });
            }

            if (!empty($receptionistName)) {
                $query->where(function ($q) use ($receptionistName) {
                    $q->where("um_first.meta_value", 'LIKE', '%' . $receptionistName . '%')
                        ->orWhere("um_last.meta_value", 'LIKE', '%' . $receptionistName . '%')
                        ->orWhere("r.display_name", 'LIKE', '%' . $receptionistName . '%');
                });
            }

            if (!empty($receptionist_id)) {
                $query->where('r.id', '=', $receptionist_id);
            }

            if (isset($status) && $status !== '') {
                $query->where("r.user_status", '=', $status);
            }

            if (!empty($receptionistAddress)) {
                $query->where(function ($q) use ($receptionistAddress) {
                    $q->where("bd.meta_value", 'LIKE', '%' . $receptionistAddress . '%');
                });
            }

            // Apply sorting if orderby parameter is provided
            if (!empty($params['orderby'])) {
                $orderby = $params['orderby'];
                $direction = !empty($params['order']) && strtolower($params['order']) === 'desc' ? 'DESC' : 'ASC';

                switch ($orderby) {
                    case 'display_name':
                    case 'name':
                        $query->orderBy("r.display_name", $direction);
                        break;
                    case 'email':
                        $query->orderBy("r.user_email", $direction);
                        break;
                    case 'clinic_name':
                        $query->orderBy("c.name", $direction);
                        break;
                    case 'contact_number':
                        // Contact number is in basic_data JSON, so we'll sort by the meta value
                        $query->orderBy("bd.meta_value", $direction);
                        break;
                    case 'user_status':
                    case 'status':
                        $query->orderBy("r.user_status", $direction);
                        break;
                    case 'id':
                    default:
                        $query->orderBy("r.ID", $direction);
                        break;
                }
            } else {
                // Default sorting by id descending if no sort specified
                $query->orderBy("r.id", 'DESC');
            }

            // Apply pagination with validation
            $page = max(1, intval($params['page'] ?? 1));
            $perPage = intval($params['perPage'] ?? 10);

            // Get total count for pagination
            $totalQuery = clone $query;
            $totalQuery->removeGroupBy();
            $total = $totalQuery->countDistinct('r.ID');

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
            $receptionistsData = $query->get();
            $receptionists = [];
            // Process each receptionist and get their metadata
            foreach ($receptionistsData as $key => $receptionist) {
                $userId = $receptionist->id;
                // Get basic data (contains contact_number, gender, dob, address, etc.)
                $basicData = $receptionist->basic_data;
                $basicData = !empty($basicData) ? json_decode($basicData, true) : [];
                $attachmentId = $receptionist->profile_image_id;
                // Build receptionist object with all data
                $receptionistObj = [
                    'id' => $userId,
                    'first_name' => $receptionist->first_name,
                    'last_name' => $receptionist->last_name,
                    'display_name' => $receptionist->display_name,
                    'email' => $receptionist->email,
                    'receptionist_image_url' => wp_get_attachment_url($attachmentId),
                    'receptionist_image_id' => $attachmentId,
                    'clinic' => array(
                        'clinic_id' => $receptionist->clinic_id,
                        'clinic_name' => $receptionist->clinic_name,
                        'clinic_email' => $receptionist->clinic_email,
                        'clinic_image_url' => wp_get_attachment_url($receptionist->clinic_profile_image),
                        'clinic_image_id' => $receptionist->clinic_profile_image,
                    ),
                    'status' => $receptionist->status,
                    'contact_number' => isset($basicData['mobile_number']) ? $basicData['mobile_number'] : '',
                    'created_at' => $receptionist->user_registered
                ];

                $receptionists[] = $receptionistObj;
            }

            // Create response with pagination
            $response = [
                'receptionists' => $receptionists,
                'pagination' => [
                    'total' => $total,
                    'perPage' => $perPage,
                    'currentPage' => $page,
                    'lastPage' => $totalPages,
                ],
            ];
            return $this->response($response, __('Receptionists retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to get receptionists', 'kivicare-clinic-management-system'),
                false,
                500
            );
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
     * Bulk delete receptionists
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function bulkDeleteReceptionists(WP_REST_Request $request)
    {
        try {
            $params = $request->get_params();
            $ids = $params['ids'] ?? [];

            if (empty($ids)) {
                return $this->response(
                    null,
                    __('No receptionist IDs provided for deletion', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            $deletedCount = 0;
            $errors = [];

            foreach ($ids as $id) {
                try {
                    $receptionist = KCReceptionist::find($id);

                    if (!$receptionist) {
                        /* translators: %d: Receptionist ID */
                        $errors[] = sprintf(__('Receptionist with ID %d not found', 'kivicare-clinic-management-system'), $id);
                        continue;
                    }

                    // Delete receptionist clinic mappings
                    KCReceptionistClinicMapping::table('rcm')
                        ->where('receptionist_id', '=', $id)
                        ->delete();

                    // Delete the receptionist
                    $receptionist = new KCReceptionist();
                    $receptionist->id = $id;
                    $result = $receptionist->delete();

                    if ($result) {
                        $deletedCount++;
                    } else {
                        /* translators: %d: Receptionist ID */
                        $errors[] = sprintf(__('Failed to delete receptionist with ID %d', 'kivicare-clinic-management-system'), $id);
                    }
                } catch (\Exception $e) {
                    /* translators: 1: Receptionist ID, 2: Error message */
                    $errors[] = sprintf(__('Error deleting receptionist with ID %1$d: %2$s', 'kivicare-clinic-management-system'), $id, $e->getMessage());
                }
            }

            /* translators: 1: number of deleted receptionists, 2: total number of receptionists */
            $message = sprintf(__('Receptionists deleted successfully', 'kivicare-clinic-management-system'), $deletedCount, count($ids));

            return $this->response([
                'deleted_count' => $deletedCount,
                'total' => count($ids),
                'errors' => $errors
            ], $message, $deletedCount > 0);
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to process bulk delete request', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Get arguments for the update endpoint
     *
     * @return array
     */
    private function getUpdateEndpointArgs()
    {
        $args = $this->getCreateEndpointArgs();

        // Remove required flag for update
        foreach ($args as $key => $arg) {
            if (isset($args[$key]['required'])) {
                unset($args[$key]['required']);
            }
        }

        // Add ID parameter
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
     * Get single receptionist by ID
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getReceptionist(WP_REST_Request $request)
    {
        try {
            // Get receptionist ID
            $id = (int) $request->get_param('id');

            if (empty($id)) {
                return $this->response(null, __('Invalid receptionist ID', 'kivicare-clinic-management-system'), false, 400);
            }

            // Build optimized query to get receptionist data
            $receptionistData = KCReceptionist::table('r')
                ->select([
                    "r.*",
                    "um_first.meta_value as first_name",
                    "um_last.meta_value as last_name",
                    "bd.meta_value as basic_data",
                    "pi.meta_value as profile_image_id",
                    "c.name as clinic_name",
                    "c.email as clinic_email",
                    "c.id as clinic_id",
                    "c.profile_image as clinic_profile_image",
                ])
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('r.ID', '=', 'um_first.user_id')
                        ->onRaw("um_first.meta_key = 'first_name'");
                }, null, null, 'um_first')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('r.ID', '=', 'um_last.user_id')
                        ->onRaw("um_last.meta_key = 'last_name'");
                }, null, null, 'um_last')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('r.ID', '=', 'bd.user_id')
                        ->onRaw("bd.meta_key = 'basic_data'");
                }, null, null, 'bd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('r.ID', '=', 'pi.user_id')
                        ->onRaw("pi.meta_key = 'receptionist_profile_image'");
                }, null, null, 'pi')
                ->leftJoin(KCReceptionistClinicMapping::class, 'r.ID', '=', 'rcm.receptionist_id', 'rcm')
                ->leftJoin(KCClinic::class, 'rcm.clinic_id', '=', 'c.id', 'c')
                ->where('r.ID', '=', $id)
                ->first();

            // Return error if receptionist not found
            if (!$receptionistData) {
                return $this->response(null, __('Receptionist not found', 'kivicare-clinic-management-system'), false, 404);
            }

            // Parse basic data JSON
            $basicData = !empty($receptionistData->basic_data) ? json_decode($receptionistData->basic_data, true) : [];

            // Get attachment URL only if attachment ID exists
            $profileImageUrl = !empty($receptionistData->profile_image_id) ?
                wp_get_attachment_url($receptionistData->profile_image_id) : '';

            $clinicProfileImageUrl = !empty($receptionistData->clinic_profile_image) ?
                wp_get_attachment_url($receptionistData->clinic_profile_image) : '';

            // Build response object using null coalescing operators for cleaner code
            $receptionistObj = [
                'id' => $receptionistData->id,
                'first_name' => $receptionistData->first_name ?? '',
                'last_name' => $receptionistData->last_name ?? '',
                'display_name' => $receptionistData->display_name,
                'email' => $receptionistData->email,
                'receptionist_image_url' => $profileImageUrl,
                'receptionist_image_id' => !empty($receptionistData->profile_image_id) ?
                    (int) $receptionistData->profile_image_id : null,
                'clinic' => !empty($receptionistData->clinic_id) ? [
                    'clinic_id' => $receptionistData->clinic_id,
                    'clinic_name' => $receptionistData->clinic_name,
                    'clinic_email' => $receptionistData->clinic_email,
                    'clinic_image_url' => $clinicProfileImageUrl,
                    'clinic_image_id' => $receptionistData->clinic_profile_image,
                ] : null,
                'status' => (int) $receptionistData->status,
                'contact_number' => $basicData['mobile_number'] ?? '',
                'gender' => $basicData['gender'] ?? '',
                'dob' => $basicData['dob'] ?? '',
                'address' => $basicData['address'] ?? '',
                'city' => $basicData['city'] ?? '',
                'country' => $basicData['country'] ?? '',
                'postal_code' => $basicData['postal_code'] ?? '',
                'created_at' => $receptionistData->user_registered
            ];

            return $this->response($receptionistObj, __('Receptionist retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to get receptionist', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Save receptionist clinic mapping
     * 
     * @param int $receptionist_id
     * @param mixed $clinic_id
     */
    public function saveReceptionist($receptionist_id, $clinic_id)
    {
        // Save/update receptionist clinic mappings
        if (is_array($clinic_id)) {
            foreach ($clinic_id as $value) {
                $new_temp = [
                    'receptionistId' => (int) $receptionist_id,
                    'clinicId' => $value['id'],
                    'createdAt' => current_time('mysql')
                ];
                // Create receptionist clinic mappings
                KCReceptionistClinicMapping::create($new_temp);
            }
        } else {
            $new_temp = [
                'receptionistId' => (int) $receptionist_id,
                'clinicId' => $clinic_id,
                'createdAt' => current_time('mysql')
            ];
            // Create receptionist clinic mappings
            KCReceptionistClinicMapping::create($new_temp);
        }
    }

    /**
     * Update Receptionist status
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function updateReceptionistStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');
            $status = $request->get_param('status');

            $receptionist = KCReceptionist::find($id);

            if (!$receptionist) {
                return $this->response(null, __('Receptionist not found', 'kivicare-clinic-management-system'), false, 404);
            }

            $updated = $receptionist->updateStatus($status);

            if ($updated) {
                return $this->response(
                    ['id' => $id, 'status' => $status],
                    __('Receptionist status updated successfully', 'kivicare-clinic-management-system'),
                    true,
                    200
                );
            } else {
                return $this->response(
                    null,
                    __('Failed to update Receptionist status', 'kivicare-clinic-management-system'),
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
     * Resend receptionist credentials
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function resendReceptionistCredentials(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');

            $receptionist = KCReceptionist::find($id);

            if (!$receptionist) {
                return $this->response(null, __('Receptionist not found', 'kivicare-clinic-management-system'), false, 404);
            }

            $user_data = get_userdata($receptionist->id);

            if (empty($user_data)) {
                return $this->response(null, __('Receptionist user not found', 'kivicare-clinic-management-system'), false, 404);
            }

            // Generate new password
            $password = wp_generate_password(12, true, false);
            wp_set_password($password, $user_data->ID);

            // Prepare email data
            $email_data = [
                'user_name' => $user_data->user_login,
                'user_email' => $user_data->user_email,
                'user_password' => $password,
                'doctor_name' => $receptionist->displayName,
                'user_role' => $receptionist->userRole,
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
     * Bulk delete doctors
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function bulkDeletePatients(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $ids = $request->get_param('ids');

            $success_count = 0;
            $failed_count = 0;
            $failed_ids = [];

            foreach ($ids as $id) {
                $receptionist = KCReceptionist::find($id);

                if (!$receptionist) {
                    $failed_count++;
                    $failed_ids[] = [
                        'id' => $id,
                        'reason' => __('Receptionist not found', 'kivicare-clinic-management-system')
                    ];
                    continue;
                }

                // Delete receptionist clinic mappings
                KCReceptionistClinicMapping::table('rcm')
                    ->where('receptionist_id', '=', $id)
                    ->delete();

                // Delete the receptionist record
                $result = $receptionist->delete();

                if (!$result) {
                    $failed_count++;
                    $failed_ids[] = [
                        'id' => $id,
                        'reason' => __('Failed to delete receptionist', 'kivicare-clinic-management-system')
                    ];
                    continue;
                }

                // Delete WordPress user
                wp_delete_user($receptionist->ID);

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
                        /* translators: %d: number of receptionists */
                        __('%d receptionists deleted successfully', 'kivicare-clinic-management-system'),
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
                    __('Failed to delete receptionists', 'kivicare-clinic-management-system'),
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
                $receptionist = KCReceptionist::find($id);
                if (!$receptionist) {
                    $failed_count++;
                    $failed_ids[] = $id;
                    continue;
                }

                $user_data = get_userdata($receptionist->id);

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
                    'doctor_name' => $receptionist->displayName,
                    'user_role' => $receptionist->userRole,
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
                    sprintf(
                        /* translators: %d: number of receptionists */
                        __('Credentials resent for %d receptionists', 'kivicare-clinic-management-system'),
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
     * Bulk update patient status
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function bulkUpdateReceptionistStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $ids = $request->get_param('ids');
            $status = $request->get_param('status');

            $success_count = 0;
            $failed_count = 0;
            $failed_ids = [];

            foreach ($ids as $id) {
                $receptionist = KCReceptionist::find($id);

                if (!$receptionist) {
                    $failed_count++;
                    $failed_ids[] = $id;
                    continue;
                }

                $updated = $receptionist->updateStatus($status);

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
                    /* translators: %d: number of receptionists */
                    sprintf(__('Status updated for %d receptionists', 'kivicare-clinic-management-system'), $success_count),
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
     * Export receptionists data
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function exportReceptionists(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $format = $request->get_param('format');
            $params = $request->get_params();

            // Build query to get all receptionists matching filters
            $query = KCReceptionist::table('r')
                ->select([
                    "r.ID as id",
                    "um_first.meta_value as first_name",
                    "um_last.meta_value as last_name",
                    "r.user_email as email",
                    "r.user_status as user_status",
                    "bd.meta_value as basic_data",
                    "c.name as clinic_name",
                    "c.id as clinic_id",
                ])
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('r.ID', '=', 'um_first.user_id')
                        ->onRaw("um_first.meta_key = 'first_name'");
                }, null, null, 'um_first')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('r.ID', '=', 'um_last.user_id')
                        ->onRaw("um_last.meta_key = 'last_name'");
                }, null, null, 'um_last')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('r.ID', '=', 'bd.user_id')
                        ->onRaw("bd.meta_key = 'basic_data'");
                }, null, null, 'bd')
                ->leftJoin(KCReceptionistClinicMapping::class, 'r.ID', '=', 'rcm.receptionist_id', 'rcm')
                ->leftJoin(KCClinic::class, 'rcm.clinic_id', '=', 'c.id', 'c')
                ->groupBy('r.ID');

            // Apply filters
            if (!empty($params['search'])) {
                $search = sanitize_text_field($params['search']);
                $query->where(function ($q) use ($search) {
                    $q->where("um_first.meta_value", 'LIKE', '%' . $search . '%')
                        ->orWhere("um_last.meta_value", 'LIKE', '%' . $search . '%')
                        ->orWhere("r.user_email", 'LIKE', '%' . $search . '%');
                });
            }

            if (isset($params['status']) && $params['status'] !== '') {
                $query->where("r.user_status", '=', intval($params['status']));
            }

            if (!empty($params['clinic_id'])) {
                $query->where('c.id', '=', intval($params['clinic_id']));
            }

            // Apply sorting
            if (!empty($params['orderby']) && !empty($params['order'])) {
                $orderBy = $params['orderby'];
                $order = strtoupper($params['order']) === 'DESC' ? 'DESC' : 'ASC';

                // Map frontend column IDs to database columns
                $columnMap = [
                    'id' => 'r.ID',
                    'profile' => 'first_name', // Sort by first name for profile column
                    'clinic_name' => 'c.name',
                    'contact_number' => 'basic_data', // This might need special handling if it's inside JSON
                    'status' => 'r.user_status',
                ];

                if (isset($columnMap[$orderBy])) {
                    $query->orderBy($columnMap[$orderBy], $order);
                }
            } else {
                // Default sort
                $query->orderBy('r.ID', 'DESC');
            }

            $receptionists = $query->get();

            // Process receptionists data
            $exportData = [];
            foreach ($receptionists as $receptionist) {
                $basicData = !empty($receptionist->basic_data) ? json_decode($receptionist->basic_data, true) : [];

                // Build full address
                $addressParts = array_filter([
                    $basicData['address'] ?? '',
                    $basicData['city'] ?? '',
                    $basicData['state'] ?? '',
                    $basicData['country'] ?? '',
                    $basicData['postal_code'] ?? ''
                ]);
                $fullAddress = !empty($addressParts) ? implode(', ', $addressParts) : '';

                $exportData[] = [
                    'id' => $receptionist->id,
                    'name' => trim(($receptionist->first_name ?? '') . ' ' . ($receptionist->last_name ?? '')),
                    'email' => $receptionist->email ?? '',
                    'user_status' => $receptionist->user_status == 0 ? 'Active' : 'Inactive',
                    'clinic_id' => $receptionist->clinic_id ?? '',
                    'clinic_name' => $receptionist->clinic_name ?? '',
                    'mobile_number' => $basicData['mobile_number'] ?? '',
                    'gender' => $basicData['gender'] ?? '',
                    'dob' => $basicData['dob'] ?? '',
                    'full_address' => $fullAddress,
                ];
            }

            return $this->response(
                ['receptionists' => $exportData],
                __('Receptionists data retrieved successfully', 'kivicare-clinic-management-system'),
                true,
                200
            );
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }
}
