<?php

namespace App\controllers\api\SettingsController;

use App\baseClasses\KCBase;
use App\baseClasses\KCErrorLogger;
use App\controllers\api\SettingsController;
use App\models\KCCustomNotification;
use App\models\KCClinic;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_REST_Server;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class CustomNotification
 * 
 * API Controller for Custom Notification Service management
 * 
 * @package App\controllers\api\SettingsController
 */
class CustomNotification extends SettingsController
{
    private static $instance = null;

    protected $route = 'settings/custom-notifications';


    public function __construct()
    {
        parent::__construct();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function registerRoutes()
    {
        // Custom Notification Routes

        // Get list of custom notification services
        $this->registerRoute('/' . $this->route, [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getList'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'page' => [
                    'description' => 'Current page number',
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                    'sanitize_callback' => 'absint'
                ],
                'per_page' => [
                    'description' => 'Number of items per page',
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100,
                    'sanitize_callback' => 'absint'
                ],
                'search' => [
                    'description' => 'Search term',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return strlen($value) <= 100; // Limit search term length
                    }
                ],
                'server_type' => [
                    'description' => 'Filter by server type',
                    'type' => 'string',
                    'enum' => ['sms', 'email', 'webhook', 'custom-api', 'push-notification'],
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'is_active' => [
                    'description' => 'Filter by active status',
                    'type' => 'boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean'
                ]
            ]
        ]);

        // Create new custom notification service
        $this->registerRoute('/' . $this->route, [
            'methods' => ['POST'],
            'callback' => [$this, 'createService'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => [
                'server_type' => [
                    'description' => 'Type of notification server',
                    'type' => 'string',
                    'required' => true,
                    'enum' => ['sms', 'email', 'webhook', 'custom-api', 'push-notification'],
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'server_name' => [
                    'description' => 'Display name for the service',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return !empty(trim($value));
                    }
                ],
                'server_url' => [
                    'description' => 'API endpoint URL',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'esc_url_raw',
                    'validate_callback' => function ($value) {
                        return filter_var($value, FILTER_VALIDATE_URL) !== false;
                    }
                ],
                'port' => [
                    'description' => 'Port number',
                    'type' => 'integer',
                    'default' => 443,
                    'minimum' => 1,
                    'maximum' => 65535,
                    'sanitize_callback' => 'absint'
                ],
                'http_method' => [
                    'description' => 'HTTP method',
                    'type' => 'string',
                    'default' => 'POST',
                    'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'auth_method' => [
                    'description' => 'Authentication method',
                    'type' => 'string',
                    'default' => 'none',
                    'enum' => ['none', 'apikey', 'bearer', 'oauth2', 'basic'],
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'auth_config' => [
                    'description' => 'Authentication configuration',
                    'type' => 'object',
                    'sanitize_callback' => 'kcSanitizeData'
                ],
                'sender_name' => [
                    'description' => 'Sender name',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'sender_email' => [
                    'description' => 'Sender email or phone number',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'enable_ssl' => [
                    'description' => 'Enable SSL verification',
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean'
                ],
                'content_type' => [
                    'description' => 'Content type for requests',
                    'type' => 'string',
                    'default' => 'application/json',
                    'enum' => ['application/json', 'application/x-www-form-urlencoded', 'text/plain', 'application/xml'],
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'custom_headers' => [
                    'description' => 'Custom headers for requests',
                    'type' => 'array',
                    'sanitize_callback' => 'kcSanitizeData'
                ],
                'query_params' => [
                    'description' => 'Query parameters for requests',
                    'type' => 'array',
                    'sanitize_callback' => 'kcSanitizeData'
                ],
                'request_body' => [
                    'description' => 'Request body template',
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw'
                ],
                'is_active' => [
                    'description' => 'Service active status',
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean'
                ],
                'clinic_id' => [
                    'description' => 'Clinic ID (null for global)',
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);

        // Get single custom notification service
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => ['GET'],
            'callback' => [$this, 'getService'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'id' => [
                    'description' => 'Service ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);

        // Update custom notification service
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => ['PUT', 'PATCH'],
            'callback' => [$this, 'updateService'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => [
                'id' => [
                    'description' => 'Service ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ],
                'server_type' => [
                    'description' => 'Type of notification server',
                    'type' => 'string',
                    'enum' => ['sms', 'email', 'webhook', 'custom-api', 'push-notification'],
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'server_name' => [
                    'description' => 'Display name for the service',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return $value === null || !empty(trim($value));
                    }
                ],
                'server_url' => [
                    'description' => 'API endpoint URL',
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                    'validate_callback' => function ($value) {
                        return $value === null || filter_var($value, FILTER_VALIDATE_URL) !== false;
                    }
                ],
                'port' => [
                    'description' => 'Port number',
                    'type' => 'integer',
                    'default' => 443,
                    'minimum' => 1,
                    'maximum' => 65535,
                    'sanitize_callback' => 'absint'
                ],
                'http_method' => [
                    'description' => 'HTTP method',
                    'type' => 'string',
                    'default' => 'POST',
                    'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'auth_method' => [
                    'description' => 'Authentication method',
                    'type' => 'string',
                    'default' => 'none',
                    'enum' => ['none', 'apikey', 'bearer', 'oauth2', 'basic'],
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'auth_config' => [
                    'description' => 'Authentication configuration',
                    'type' => 'object',
                    'sanitize_callback' => 'kcSanitizeData'
                ],
                'sender_name' => [
                    'description' => 'Sender name',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'sender_email' => [
                    'description' => 'Sender email or phone number',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'enable_ssl' => [
                    'description' => 'Enable SSL verification',
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean'
                ],
                'content_type' => [
                    'description' => 'Content type for requests',
                    'type' => 'string',
                    'default' => 'application/json',
                    'enum' => ['application/json', 'application/x-www-form-urlencoded', 'text/plain', 'application/xml'],
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'custom_headers' => [
                    'description' => 'Custom headers for requests',
                    'type' => 'array',
                    'sanitize_callback' => 'kcSanitizeData'
                ],
                'query_params' => [
                    'description' => 'Query parameters for requests',
                    'type' => 'array',
                    'sanitize_callback' => 'kcSanitizeData'
                ],
                'request_body' => [
                    'description' => 'Request body template',
                    'type' => 'string',
                    'sanitize_callback' => 'wp_unslash'
                ],
                'is_active' => [
                    'description' => 'Service active status',
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean'
                ],
                'clinic_id' => [
                    'description' => 'Clinic ID (null for global)',
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);

        // Delete custom notification service
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => ['DELETE'],
            'callback' => [$this, 'deleteService'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => [
                'id' => [
                    'description' => 'Service ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);

        // Test custom notification service
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/test', [
            'methods' => ['POST'],
            'callback' => [$this, 'testService'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => [
                'id' => [
                    'description' => 'Service ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ],
                'recipient' => [
                    'description' => 'Test recipient (email or phone number)',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [$this, 'validateTestRecipient']
                ],
                'message' => [
                    'description' => 'Test message content',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return !empty(trim($value));
                    }
                ],
                'test_data' => [
                    'description' => 'Additional test data for variables',
                    'type' => 'object',
                    'default' => [],
                    'sanitize_callback' => 'kcSanitizeData'
                ]
            ]
        ]);

        // Update custom notification service status
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/status', [
            'methods' => ['PUT', 'PATCH'],
            'callback' => [$this, 'updateServiceStatus'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => [
                'id' => [
                    'description' => 'Service ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ],
                'status' => [
                    'description' => 'Active status (true/false)',
                    'type' => 'boolean',
                    'required' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean'
                ]
            ]
        ]);
    }

    /**
     * Get list of custom notification services
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getList(WP_REST_Request $request)
    {
        try {
            // Get query parameters
            $page = $request->get_param('page') ?: 1;
            $per_page = $request->get_param('per_page') ?: 10;
            $search = $request->get_param('search') ?: '';
            $server_type = $request->get_param('server_type') ?: '';
            $is_active = $request->get_param('is_active');

            // Validate pagination
            $page = max(1, intval($page));
            $per_page = max(1, min(100, intval($per_page))); // Limit to 100 per page
            $offset = ($page - 1) * $per_page;

            // Build query using query builder
            $query = KCCustomNotification::query();

            // Search filter
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('server_name', 'LIKE', '%' . $search . '%')
                        ->orWhere('server_url', 'LIKE', '%' . $search . '%');
                });
            }

            // Server type filter
            if (!empty($server_type)) {
                $query->where('server_type', $server_type);
            }

            // Active status filter
            if ($is_active !== null) {
                $query->where('is_active', $is_active ? 1 : 0);
            }

            // Clinic filter (if multi-clinic setup)
            $current_user_role = $this->kcbase->getLoginUserRole();
            if ($current_user_role !== 'administrator') {
                $clinic_id = $this->getCurrentUserClinicId();
                if ($clinic_id) {
                    $query->where(function ($q) use ($clinic_id) {
                        $q->where('clinic_id', $clinic_id)
                            ->orWhereNull('clinic_id');
                    });
                }
            }

            // Get total count for pagination
            $total_items = $query->count();

            // Get paginated results
            $services = $query->orderBy('created_at', 'DESC')
                ->limit($per_page)
                ->offset($offset)
                ->get();

            // Transform results
            $services_data = $services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'server_type' => $service->server_type,
                    'server_name' => $service->server_name,
                    'server_url' => $service->server_url,
                    'port' => $service->port,
                    'http_method' => $service->http_method,
                    'auth_method' => $service->auth_method,
                    'sender_name' => $service->sender_name,
                    'sender_email' => $service->sender_email,
                    'enable_ssl' => (bool) $service->enable_ssl,
                    'content_type' => $service->content_type,
                    'is_active' => (bool) $service->is_active,
                    'clinic_id' => $service->clinic_id,
                    'created_by' => $service->created_by,
                    'created_at' => $service->created_at,
                    'updated_at' => $service->updated_at,
                    'type_label' => $this->getServerTypeLabel($service->server_type),
                    'auth_label' => $this->getAuthMethodLabel($service->auth_method)
                ];
            })->toArray();

            // Calculate pagination info
            $total_pages = ceil($total_items / $per_page);

            return new WP_REST_Response([
                'data' => $services_data,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_items' => intval($total_items),
                    'total_pages' => intval($total_pages),
                    'has_next' => $page < $total_pages,
                    'has_prev' => $page > 1
                ],
                'filters' => [
                    'search' => $search,
                    'server_type' => $server_type,
                    'is_active' => $is_active
                ]
            ], 200);

        } catch (\Exception $e) {
            return new WP_Error('custom_notification_list_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Get single custom notification service
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getService(WP_REST_Request $request)
    {
        try {
            $id = $request->get_param('id'); // Already validated by args

            $service = KCCustomNotification::find($id);

            if (!$service) {
                return new WP_Error('service_not_found', __('Service not found', 'kivicare-clinic-management-system'), ['status' => 404]);
            }

            // Check permissions (if multi-clinic setup)
            $current_user_role = $this->kcbase->getLoginUserRole();
            if ($current_user_role !== 'administrator' && $service->clinic_id) {
                $user_clinic_id = $this->getCurrentUserClinicId();
                if ($service->clinic_id != $user_clinic_id) {
                    return new WP_Error('access_denied', __('Access denied', 'kivicare-clinic-management-system'), ['status' => 403]);
                }
            }

            // Prepare response data
            $data = [
                'id' => $service->id,
                'server_type' => $service->server_type,
                'server_name' => $service->server_name,
                'server_url' => $service->server_url,
                'port' => $service->port,
                'http_method' => $service->http_method,
                'auth_method' => $service->auth_method,
                'auth_config' => $service->getAuthConfigArray(),
                'sender_name' => $service->sender_name,
                'sender_email' => $service->sender_email,
                'enable_ssl' => $service->enable_ssl,
                'content_type' => $service->content_type,
                'custom_headers' => $service->getCustomHeadersArray(),
                'query_params' => $service->getQueryParamsArray(),
                'request_body' => $service->request_body,
                'is_active' => $service->is_active,
                'clinic_id' => $service->clinic_id,
                'created_by' => $service->created_by,
                'created_at' => $service->created_at,
                'updated_at' => $service->updated_at
            ];

            return new WP_REST_Response(['data' => $data], 200);

        } catch (\Exception $e) {
            return new WP_Error('custom_notification_get_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Create new custom notification service
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function createService(WP_REST_Request $request)
    {
        try {
            // Get validated request data
            $data = $this->prepareRequestData($request);

            // Set clinic ID if applicable
            $current_user_role = $this->kcbase->getLoginUserRole();
            if ($current_user_role !== 'administrator') {
                $data['clinic_id'] = $this->getCurrentUserClinicId();
            } else {
                $data['clinic_id'] = $data['clinic_id'] ?? null;
            }

            // Set created_by
            $data['created_by'] = get_current_user_id();

            // Create new service using model
            $service = KCCustomNotification::create($data);

            if (!$service) {
                return new WP_Error('save_failed', __('Failed to save service', 'kivicare-clinic-management-system'), ['status' => 500]);
            }

            // If service is created as active, deactivate other services of the same type
            if (!empty($data['is_active']) && $service && $service->id) {
                $this->deactivateOtherServicesOfSameType($data['server_type'], $service->id, $data['clinic_id']);
            }

            return new WP_REST_Response([
                'message' => __('Service created successfully', 'kivicare-clinic-management-system'),
                'data' => ['id' => $service->id]
            ], 201);

        } catch (\Exception $e) {
            return new WP_Error('custom_notification_create_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Update existing custom notification service
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function updateService(WP_REST_Request $request)
    {
        try {
            $id = $request->get_param('id'); // Already validated by args

            $service = KCCustomNotification::find($id);

            if (!$service) {
                return new WP_Error('service_not_found', __('Service not found', 'kivicare-clinic-management-system'), ['status' => 404]);
            }

            // Check permissions
            $current_user_role = $this->kcbase->getLoginUserRole();
            if ($current_user_role !== 'administrator' && $service->clinic_id) {
                $user_clinic_id = $this->getCurrentUserClinicId();
                if ($service->clinic_id != $user_clinic_id) {
                    return new WP_Error('access_denied', __('Access denied', 'kivicare-clinic-management-system'), ['status' => 403]);
                }
            }

            // Get and validate request data
            $data = $this->prepareRequestData($request, true); // true = update mode

            // Update clinic ID if admin
            if ($current_user_role === 'administrator' && isset($data['clinic_id'])) {
                // Keep the clinic_id from request data - it's already in $data
            }

            // Update the service using model's update method
            $result = $service->update($data);

            if (!$result) {
                return new WP_Error('save_failed', __('Failed to update service', 'kivicare-clinic-management-system'), ['status' => 500]);
            }

            return new WP_REST_Response([
                'message' => __('Service updated successfully', 'kivicare-clinic-management-system'),
                'data' => ['id' => $service->id]
            ], 200);

        } catch (\Exception $e) {
            return new WP_Error('custom_notification_update_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Delete custom notification service
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function deleteService(WP_REST_Request $request)
    {
        try {
            $id = $request->get_param('id'); // Already validated by args

            $service = KCCustomNotification::find($id);

            if (!$service) {
                return new WP_Error('service_not_found', __('Service not found', 'kivicare-clinic-management-system'), ['status' => 404]);
            }

            // Check permissions
            $current_user_role = $this->kcbase->getLoginUserRole();
            if ($current_user_role !== 'administrator' && $service->clinic_id) {
                $user_clinic_id = $this->getCurrentUserClinicId();
                if ($service->clinic_id != $user_clinic_id) {
                    return new WP_Error('access_denied', __('Access denied', 'kivicare-clinic-management-system'), ['status' => 403]);
                }
            }

            // Delete the service
            if (!$service->delete()) {
                return new WP_Error('delete_failed', __('Failed to delete service', 'kivicare-clinic-management-system'), ['status' => 500]);
            }

            return new WP_REST_Response([
                'message' => __('Service deleted successfully', 'kivicare-clinic-management-system')
            ], 200);

        } catch (\Exception $e) {
            return new WP_Error('custom_notification_delete_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Test custom notification service connection
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function testService(WP_REST_Request $request)
    {
        try {
            $id = $request->get_param('id'); // Already validated by args

            $service = KCCustomNotification::find($id);

            if (!$service) {
                return new WP_Error('service_not_found', __('Service not found', 'kivicare-clinic-management-system'), ['status' => 404]);
            }

            // Check permissions
            $current_user_role = $this->kcbase->getLoginUserRole();
            if ($current_user_role !== 'administrator' && $service->clinic_id) {
                $user_clinic_id = $this->getCurrentUserClinicId();
                if ($service->clinic_id != $user_clinic_id) {
                    return new WP_Error('access_denied', __('Access denied', 'kivicare-clinic-management-system'), ['status' => 403]);
                }
            }

            // Get test data from request (already validated by args)
            $recipient = $request->get_param('recipient');
            $message = $request->get_param('message');

            // Test the notification service
            $test_result = $this->sendTestNotification($service, $recipient, $message);

            if ($test_result['success']) {
                // Update last_tested timestamp
                $service->update(['last_tested_at' => current_time('mysql')]);

                return new WP_REST_Response([
                    'message' => __('Test notification sent successfully', 'kivicare-clinic-management-system'),
                    'data' => [
                        'recipient' => $recipient,
                        'message' => $message,
                        'response' => $test_result['response'] ?? null,
                        'sent_at' => current_time('mysql')
                    ]
                ], 200);
            } else {
                return new WP_Error('test_failed', $test_result['message'] ?? __('Test notification failed', 'kivicare-clinic-management-system'), [
                    'status' => 400,
                    'data' => [
                        'error_details' => $test_result['error_details'] ?? null,
                        'response' => $test_result['response'] ?? null
                    ]
                ]);
            }

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Custom notification test error: ' . $e->getMessage());
            return new WP_Error('custom_notification_test_error', __('An error occurred while testing the notification service', 'kivicare-clinic-management-system'), ['status' => 500]);
        }
    }

    /**
     * Update custom notification service status only
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function updateServiceStatus(WP_REST_Request $request)
    {
        try {
            $id = $request->get_param('id'); // Already validated by args
            $status = $request->get_param('status'); // Already validated by args

            $service = KCCustomNotification::find($id);

            if (!$service) {
                return new WP_Error('service_not_found', __('Service not found', 'kivicare-clinic-management-system'), ['status' => 404]);
            }

            // Check permissions
            $current_user_role = $this->kcbase->getLoginUserRole();
            if ($current_user_role !== 'administrator' && $service->clinic_id) {
                $user_clinic_id = $this->getCurrentUserClinicId();
                if ($service->clinic_id != $user_clinic_id) {
                    return new WP_Error('access_denied', __('Access denied', 'kivicare-clinic-management-system'), ['status' => 403]);
                }
            }

            // If activating this service, deactivate all other services of the same type
            if ($status) {
                $this->deactivateOtherServicesOfSameType($service->server_type, $id, $service->clinic_id);
            }

            // Update only the status field
            $result = $service->update(['is_active' => (bool) $status]);

            if (!$result) {
                return new WP_Error('save_failed', __('Failed to update service status', 'kivicare-clinic-management-system'), ['status' => 500]);
            }

            $response_data = [
                'message' => __('Service status updated successfully', 'kivicare-clinic-management-system'),
                'data' => [
                    'id' => $service->id,
                    'is_active' => (bool) $service->is_active
                ]
            ];

            // If we deactivated other services, include that information
            if ($status) {
                $response_data['message'] = __('Service activated successfully. Other services of the same type have been deactivated.', 'kivicare-clinic-management-system');
            }

            return new WP_REST_Response($response_data, 200);

        } catch (\Exception $e) {
            return new WP_Error('custom_notification_status_update_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Deactivate all other services of the same server type
     * 
     * @param string $server_type
     * @param int $exclude_id ID of service to exclude from deactivation
     * @param int|null $clinic_id Clinic ID to scope the deactivation
     * @return void
     */
    private function deactivateOtherServicesOfSameType(string $server_type, ?int $exclude_id, ?int $clinic_id = null): void
    {
        try {
            // Build query to find other active services of the same type
            $query = KCCustomNotification::query()
                ->where('server_type', $server_type)
                ->where('is_active', 1);

            // Only exclude if we have a valid exclude_id
            if ($exclude_id !== null) {
                $query->where('id', '!=', $exclude_id);
            }

            // If clinic_id is specified, scope to that clinic
            // If clinic_id is null (global), deactivate global services only
            if ($clinic_id) {
                $query->where('clinic_id', $clinic_id);
            } else {
                $query->whereNull('clinic_id');
            }

            $services_to_deactivate = $query->get();

            // Deactivate each service
            foreach ($services_to_deactivate as $service) {
                $service->update(['is_active' => 0]);
            }

        } catch (\Exception $e) {
            // Log the error but don't fail the main operation
            KCErrorLogger::instance()->error('Failed to deactivate other services of same type: ' . $e->getMessage());
        }
    }

    /**
     * Prepare request data for create/update operations
     * 
     * @param WP_REST_Request $request
     * @param bool $is_update Whether this is an update operation
     * @return array
     */
    private function prepareRequestData(WP_REST_Request $request, bool $is_update = false): array
    {
        $data = [];

        // Get all possible fields
        $fields = [
            'server_type',
            'server_name',
            'server_url',
            'port',
            'http_method',
            'auth_method',
            'auth_config',
            'sender_name',
            'sender_email',
            'enable_ssl',
            'content_type',
            'custom_headers',
            'query_params',
            'request_body',
            'is_active',
            'clinic_id'
        ];

        foreach ($fields as $field) {
            $value = $request->get_param($field);

            // For update, only include provided fields
            if ($is_update && $value === null) {
                continue;
            }

            // Handle different field types
            if (in_array($field, ['auth_config', 'custom_headers', 'query_params'])) {
                // JSON fields - convert to JSON string for model
                if (is_array($value)) {
                    $data[$field] = json_encode($value);
                } elseif ($value !== null) {
                    $data[$field] = $value; // Already JSON string
                }
            } elseif (in_array($field, ['enable_ssl', 'is_active'])) {
                // Boolean fields
                $data[$field] = (bool) $value;
            } elseif ($field === 'clinic_id') {
                // Integer field
                $data[$field] = $value ? intval($value) : null;
            } elseif ($field === 'port') {
                // Integer field
                $data[$field] = intval($value);
            } else {
                // String fields
                $data[$field] = $value;
            }
        }

        return $data;
    }

    /**
     * Validate test recipient based on service server type
     * 
     * @param mixed $value
     * @param WP_REST_Request $request
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateTestRecipient($value, $request, $param)
    {
        if (empty(trim($value))) {
            return new WP_Error('invalid_recipient', __('Recipient cannot be empty', 'kivicare-clinic-management-system'), ['status' => 400]);
        }

        // Get service to determine server type
        $service_id = $request->get_param('id');
        if ($service_id) {
            $service = KCCustomNotification::find($service_id);
            if ($service) {
                return $this->validateRecipient($value, $service->server_type);
            }
        }

        // If service not found or no ID, do basic validation
        return !empty(trim($value));
    }

    /**
     * Get clinic ID for current user based on their role
     * 
     * @return int|null
     */
    private function getCurrentUserClinicId(): ?int
    {
        $user_role = $this->kcbase->getLoginUserRole();
        $user_id = get_current_user_id();

        switch ($user_role) {
            case 'administrator':
                return null; // Admin can access all clinics
            case 'clinic_admin':
                return KCClinic::getClinicIdOfClinicAdmin($user_id);
            case 'receptionist':
                return KCClinic::getClinicIdOfReceptionist($user_id);
            case 'doctor':
                return KCClinic::getClinicIdOfDoctor($user_id);
            default:
                return null;
        }
    }

    /**
     * Get human-readable server type label
     * 
     * @param string $server_type
     * @return string
     */
    private function getServerTypeLabel(string $server_type): string
    {
        $labels = [
            'sms' => __('SMS', 'kivicare-clinic-management-system'),
            'email' => __('Email', 'kivicare-clinic-management-system'),
            'webhook' => __('Webhook', 'kivicare-clinic-management-system'),
            'custom-api' => __('Custom API', 'kivicare-clinic-management-system'),
            'push-notification' => __('Push Notification', 'kivicare-clinic-management-system')
        ];

        return $labels[$server_type] ?? $server_type;
    }

    /**
     * Get human-readable auth method label
     * 
     * @param string $auth_method
     * @return string
     */
    private function getAuthMethodLabel(string $auth_method): string
    {
        $labels = [
            'none' => __('No Authentication', 'kivicare-clinic-management-system'),
            'apikey' => __('API Key', 'kivicare-clinic-management-system'),
            'bearer' => __('Bearer Token', 'kivicare-clinic-management-system'),
            'oauth2' => __('OAuth2', 'kivicare-clinic-management-system'),
            'basic' => __('Basic Auth', 'kivicare-clinic-management-system')
        ];

        return $labels[$auth_method] ?? $auth_method;
    }

    /**
     * Validate recipient based on server type
     * 
     * @param string $recipient
     * @param string $server_type
     * @return bool|WP_Error
     */
    private function validateRecipient(string $recipient, string $server_type)
    {
        switch ($server_type) {
            case 'email':
                if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    return new WP_Error('invalid_email', __('Invalid email address', 'kivicare-clinic-management-system'), ['status' => 400]);
                }
                break;

            case 'sms':
                // Basic phone number validation (allow various formats)
                if (!preg_match('/^[\+]?[0-9\s\-\(\)]{10,15}$/', $recipient)) {
                    return new WP_Error('invalid_phone', __('Invalid phone number format', 'kivicare-clinic-management-system'), ['status' => 400]);
                }
                break;

            case 'push-notification':
                // Basic validation for device tokens (should be alphanumeric)
                if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $recipient)) {
                    return new WP_Error('invalid_token', __('Invalid device token format', 'kivicare-clinic-management-system'), ['status' => 400]);
                }
                break;

            case 'webhook':
            case 'custom-api':
                // For webhooks and APIs, recipient can be flexible (URL, identifier, etc.)
                if (empty(trim($recipient))) {
                    return new WP_Error('invalid_recipient', __('Recipient cannot be empty', 'kivicare-clinic-management-system'), ['status' => 400]);
                }
                break;

            default:
                // Generic validation
                if (empty(trim($recipient))) {
                    return new WP_Error('invalid_recipient', __('Recipient cannot be empty', 'kivicare-clinic-management-system'), ['status' => 400]);
                }
        }

        return true;
    }

    /**
     * Send test notification through the configured service
     * 
     * @param KCCustomNotification $service
     * @param string $recipient
     * @param string $message
     * @return array
     */
    private function sendTestNotification($service, string $recipient, string $message): array
    {
        try {
            // Prepare request headers
            $headers = ['Content-Type' => $service->content_type ?: 'application/json'];

            // Add authentication headers
            $auth_config = json_decode($service->auth_config, true) ?: [];
            $this->addAuthenticationHeaders($headers, $service->auth_method, $auth_config);

            // Add custom headers
            if (!empty($service->custom_headers)) {
                $custom_headers = json_decode($service->custom_headers, true);
                if (is_array($custom_headers)) {
                    foreach ($custom_headers as $header) {
                        if (!empty($header['key']) && !empty($header['value'])) {
                            $headers[$header['key']] = $header['value'];
                        }
                    }
                }
            }

            // Prepare request body
            $body = $this->prepareRequestBody($service, $recipient, $message);



            // Prepare query parameters
            $query_params = [];
            if (!empty($service->query_params)) {
                $params = json_decode($service->query_params, true);
                if (is_array($params)) {
                    foreach ($params as $param) {
                        if (!empty($param['key']) && !empty($param['value'])) {
                            $query_params[$param['key']] = $param['value'];
                        }
                    }
                }
            }

            // Build final URL with query parameters
            $url = $service->server_url;
            if (!empty($query_params)) {
                $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($query_params);
            }

            // Configure request arguments
            $args = [
                'method' => $service->http_method ?: 'POST',
                'headers' => $headers,
                'timeout' => 30,
                'sslverify' => $service->enable_ssl ?? true
            ];

            // Add body for POST/PUT/PATCH requests
            if (in_array($service->http_method, ['POST', 'PUT', 'PATCH']) && !empty($body)) {
                $content_type = $args['headers']['Content-Type'] ?? 'application/x-www-form-urlencoded';

                if (stripos($content_type, 'application/json') !== false) {
                    // Ensure body is JSON
                    if (!is_array($body)) {
                        $decoded = json_decode($body, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $args['body'] = wp_json_encode($decoded);
                        } else {
                            $args['body'] = $body; // already raw JSON string
                        }
                    } else {
                        $args['body'] = wp_json_encode($body);
                    }

                } elseif (stripos($content_type, 'application/x-www-form-urlencoded') !== false) {
                    // Ensure proper URL encoding
                    if (is_string($body)) {
                        // replace + with %2B before parsing to avoid losing +
                        $safe_body = str_replace('+', '%2B', $body);
                        parse_str($safe_body, $parsed);
                        $args['body'] = http_build_query($parsed);
                    } else {
                        $args['body'] = http_build_query((array) $body);
                    }
                } else {
                    // Fallback: send raw
                    $args['body'] = $body;
                }

            }

            // Send the request
            $response = wp_remote_request($url, $args);

            // Check for errors
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => __('HTTP request failed', 'kivicare-clinic-management-system') . ': ' . $response->get_error_message(),
                    'error_details' => $response->get_error_messages()
                ];
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            // Consider 2xx status codes as success
            if ($response_code >= 200 && $response_code < 300) {
                return [
                    'success' => true,
                    'message' => __('Test notification sent successfully', 'kivicare-clinic-management-system'),
                    'response' => [
                        'status_code' => $response_code,
                        'body' => $response_body,
                        'headers' => wp_remote_retrieve_headers($response)->getAll()
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => __('Server returned error status', 'kivicare-clinic-management-system') . ': ' . $response_code,
                    'response' => [
                        'status_code' => $response_code,
                        'body' => $response_body,
                        'headers' => wp_remote_retrieve_headers($response)->getAll()
                    ]
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Exception occurred', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                'error_details' => $e->getTraceAsString()
            ];
        }
    }

    /**
     * Add authentication headers based on auth method
     * 
     * @param array &$headers
     * @param string $auth_method
     * @param array $auth_config
     */
    private function addAuthenticationHeaders(array &$headers, string $auth_method, array $auth_config): void
    {
        switch ($auth_method) {
            case 'apikey':
                $api_key = $auth_config['api_key'] ?? '';
                $key_location = $auth_config['key_location'] ?? 'header';
                $param_name = $auth_config['header_param_name'] ?? 'Authorization';

                if ($key_location === 'header' && !empty($api_key)) {
                    $headers[$param_name] = $api_key;
                }
                break;

            case 'basic':
                $username = $auth_config['username'] ?? '';
                $password = $auth_config['password'] ?? '';

                if (!empty($username) && !empty($password)) {
                    $headers['Authorization'] = 'Basic ' . base64_encode($username . ':' . $password);
                }
                break;

            case 'bearer':
                $access_token = $auth_config['access_token'] ?? '';
                $token_type = $auth_config['token_type'] ?? 'Bearer';

                if (!empty($access_token)) {
                    $headers['Authorization'] = $token_type . ' ' . $access_token;
                }
                break;

            case 'jwt':
                // For JWT, you might want to generate the token here
                // For now, we'll assume the token is pre-generated and stored
                $jwt_token = $auth_config['jwt_token'] ?? '';
                if (!empty($jwt_token)) {
                    $headers['Authorization'] = 'Bearer ' . $jwt_token;
                }
                break;

            case 'hmac':
                // HMAC authentication implementation would go here
                // This is complex and depends on the specific HMAC implementation
                break;
        }
    }

    /**
     * Prepare request body with dynamic variable replacement
     * 
     * @param KCCustomNotification $service
     * @param string $recipient
     * @param string $message
     * @return string
     */
    private function prepareRequestBody($service, string $recipient, string $message): string
    {
        $body = urldecode($service->request_body) ?: '';


        // Replace dynamic variables
        $replacements = [
            '{{receiver_number}}' => $recipient,
            '{{content}}' => $message,
            '{{contentid}}' => 'test_' . time() . '_' . wp_generate_uuid4(),
            '{{appointment_id}}' => 'apt_test_' . time(),
            '{{patient_name}}' => 'Test Patient',
            '{{doctor_name}}' => 'Dr. Test Doctor',
            '{{clinic_name}}' => 'Test Clinic',
            '{{appointment_date}}' => gmdate('Y-m-d'),
            '{{appointment_time}}' => gmdate('H:i A')
        ];

        $body = str_replace(array_keys($replacements), array_values($replacements), $body);


        // If no custom body is set, create a default based on content type
        if (empty($body)) {
            switch ($service->content_type) {
                case 'application/json':
                    $body = json_encode([
                        'to' => $recipient,
                        'message' => $message,
                        'test' => true
                    ]);
                    break;

                case 'application/x-www-form-urlencoded':
                    $body = http_build_query([
                        'to' => $recipient,
                        'message' => $message,
                        'test' => '1'
                    ]);
                    break;

                default:
                    $body = "to={$recipient}&message=" . urlencode($message) . "&test=1";
            }
        }

        return $body;
    }
}
