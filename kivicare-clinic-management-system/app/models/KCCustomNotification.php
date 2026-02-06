<?php

namespace App\models;

use App\baseClasses\KCBaseModel;
use App\baseClasses\KCBase;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class KCCustomNotification
 * 
 * Model for managing third-party notification service configurations
 * 
 * @property int $id
 * @property string $server_type Type: sms, email, webhook, custom-api, push-notification
 * @property string $server_name Display name for the service
 * @property string $server_url API endpoint URL
 * @property int $port Port number for the service
 * @property string $http_method HTTP method: GET, POST, PUT, PATCH, DELETE
 * @property string $auth_method Authentication: none, apikey, bearer, oauth2, basic
 * @property string|null $auth_config JSON: API keys, OAuth credentials, etc.
 * @property string|null $sender_name Sender name or identifier
 * @property string|null $sender_email Sender email or phone number
 * @property bool $enable_ssl Enable SSL/TLS encryption
 * @property string $content_type Request content type
 * @property string|null $custom_headers JSON: Custom HTTP headers
 * @property string|null $query_params JSON: Query parameters
 * @property string|null $request_body Request body template with variables
 * @property bool $is_active Service active status
 * @property int|null $clinic_id Clinic specific configuration
 * @property int|null $created_by User who created this configuration
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class KCCustomNotification extends KCBaseModel
{
    // Server type constants
    const TYPE_SMS = 'sms';
    const TYPE_EMAIL = 'email';
    const TYPE_WEBHOOK = 'webhook';
    const TYPE_CUSTOM_API = 'custom-api';
    const TYPE_PUSH_NOTIFICATION = 'push-notification';
    
    // Authentication method constants
    const AUTH_NONE = 'none';
    const AUTH_API_KEY = 'apikey';
    const AUTH_BEARER = 'bearer';
    const AUTH_OAUTH2 = 'oauth2';
    const AUTH_BASIC = 'basic';
    
    // HTTP method constants
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_PATCH = 'PATCH';
    const METHOD_DELETE = 'DELETE';

    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_custom_notifications',
            'primary_key' => 'id',
            'timestamps' => true,
            'soft_deletes' => false,
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'auto_increment' => true,
                    'primary_key' => true
                ],
                'server_type' => [
                    'column' => 'server_type',
                    'type' => 'varchar',
                    'length' => 50,
                    'nullable' => false,
                    'validators' => ['validateServerType']
                ],
                'server_name' => [
                    'column' => 'server_name',
                    'type' => 'varchar',
                    'length' => 191,
                    'nullable' => false,
                    'validators' => ['validateServerName']
                ],
                'server_url' => [
                    'column' => 'server_url',
                    'type' => 'varchar',
                    'length' => 500,
                    'nullable' => false,
                    'validators' => ['validateServerUrl']
                ],
                'port' => [
                    'column' => 'port',
                    'type' => 'int',
                    'default' => 443,
                    'validators' => ['validatePort']
                ],
                'http_method' => [
                    'column' => 'http_method',
                    'type' => 'varchar',
                    'length' => 10,
                    'default' => 'POST',
                    'validators' => ['validateHttpMethod']
                ],
                'auth_method' => [
                    'column' => 'auth_method',
                    'type' => 'varchar',
                    'length' => 50,
                    'default' => 'none',
                    'validators' => ['validateAuthMethod']
                ],
                'auth_config' => [
                    'column' => 'auth_config',
                    'type' => 'longtext',
                    'nullable' => true,
                    'validators' => ['validateAuthConfig']
                ],
                'sender_name' => [
                    'column' => 'sender_name',
                    'type' => 'varchar',
                    'length' => 191,
                    'nullable' => true
                ],
                'sender_email' => [
                    'column' => 'sender_email',
                    'type' => 'varchar',
                    'length' => 191,
                    'nullable' => true
                ],
                'enable_ssl' => [
                    'column' => 'enable_ssl',
                    'type' => 'tinyint',
                    'length' => 1,
                    'default' => 1
                ],
                'content_type' => [
                    'column' => 'content_type',
                    'type' => 'varchar',
                    'length' => 100,
                    'default' => 'application/json'
                ],
                'custom_headers' => [
                    'column' => 'custom_headers',
                    'type' => 'longtext',
                    'nullable' => true,
                    'validators' => ['validateJson']
                ],
                'query_params' => [
                    'column' => 'query_params',
                    'type' => 'longtext',
                    'nullable' => true,
                    'validators' => ['validateJson']
                ],
                'request_body' => [
                    'column' => 'request_body',
                    'type' => 'longtext',
                    'nullable' => true
                ],
                'is_active' => [
                    'column' => 'is_active',
                    'type' => 'tinyint',
                    'length' => 1,
                    'default' => 1
                ],
                'clinic_id' => [
                    'column' => 'clinic_id',
                    'type' => 'bigint',
                    'nullable' => true
                ],
                'created_by' => [
                    'column' => 'created_by',
                    'type' => 'bigint',
                    'nullable' => true
                ],
                'created_at' => [
                    'column' => 'created_at',
                    'type' => 'datetime',
                    'nullable' => true
                ],
                'updated_at' => [
                    'column' => 'updated_at',
                    'type' => 'datetime',
                    'nullable' => true
                ]
            ],
            'indexes' => [
                'idx_server_type' => ['server_type'],
                'idx_is_active' => ['is_active'],
                'idx_clinic_id' => ['clinic_id'],
                'idx_created_by' => ['created_by']
            ]
        ];
    }

    /**
     * Get allowed server types
     * 
     * @return array
     */
    public static function getAllowedServerTypes(): array
    {
        return [
            self::TYPE_SMS,
            self::TYPE_EMAIL,
            self::TYPE_WEBHOOK,
            self::TYPE_CUSTOM_API,
            self::TYPE_PUSH_NOTIFICATION
        ];
    }

    /**
     * Get allowed authentication methods
     * 
     * @return array
     */
    public static function getAllowedAuthMethods(): array
    {
        return [
            self::AUTH_NONE,
            self::AUTH_API_KEY,
            self::AUTH_BEARER,
            self::AUTH_OAUTH2,
            self::AUTH_BASIC
        ];
    }

    /**
     * Get allowed HTTP methods
     * 
     * @return array
     */
    public static function getAllowedHttpMethods(): array
    {
        return [
            self::METHOD_GET,
            self::METHOD_POST,
            self::METHOD_PUT,
            self::METHOD_PATCH,
            self::METHOD_DELETE
        ];
    }

    /**
     * Validate server type
     * 
     * @param string $value
     * @return bool|\WP_Error
     */
    public function validateServerType($value)
    {
        if (!in_array($value, self::getAllowedServerTypes())) {
            return new \WP_Error('invalid_server_type', __('Invalid server type', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Validate server name
     * 
     * @param string $value
     * @return bool|\WP_Error
     */
    public function validateServerName($value)
    {
        if (empty(trim($value))) {
            return new \WP_Error('empty_server_name', __('Server name is required', 'kivicare-clinic-management-system'));
        }
        
        if (strlen($value) > 191) {
            return new \WP_Error('server_name_too_long', __('Server name must be less than 191 characters', 'kivicare-clinic-management-system'));
        }
        
        return true;
    }

    /**
     * Validate server URL
     * 
     * @param string $value
     * @return bool|\WP_Error
     */
    public function validateServerUrl($value)
    {
        if (empty(trim($value))) {
            return new \WP_Error('empty_server_url', __('Server URL is required', 'kivicare-clinic-management-system'));
        }
        
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_server_url', __('Please enter a valid URL', 'kivicare-clinic-management-system'));
        }
        
        return true;
    }

    /**
     * Validate port number
     * 
     * @param int $value
     * @return bool|\WP_Error
     */
    public function validatePort($value)
    {
        if (!is_numeric($value) || $value < 1 || $value > 65535) {
            return new \WP_Error('invalid_port', __('Port must be between 1 and 65535', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Validate HTTP method
     * 
     * @param string $value
     * @return bool|\WP_Error
     */
    public function validateHttpMethod($value)
    {
        if (!in_array($value, self::getAllowedHttpMethods())) {
            return new \WP_Error('invalid_http_method', __('Invalid HTTP method', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Validate authentication method
     * 
     * @param string $value
     * @return bool|\WP_Error
     */
    public function validateAuthMethod($value)
    {
        if (!in_array($value, self::getAllowedAuthMethods())) {
            return new \WP_Error('invalid_auth_method', __('Invalid authentication method', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Validate auth config JSON
     * 
     * @param string $value
     * @return bool|\WP_Error
     */
    public function validateAuthConfig($value)
    {
        if (empty($value)) {
            return true; // Nullable field
        }
        
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('invalid_auth_config', __('Auth config must be valid JSON', 'kivicare-clinic-management-system'));
        }
        
        return true;
    }

    /**
     * Validate JSON field
     * 
     * @param string $value
     * @return bool|\WP_Error
     */
    public function validateJson($value)
    {
        if (empty($value)) {
            return true; // Nullable field
        }
        
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('invalid_json', __('Value must be valid JSON', 'kivicare-clinic-management-system'));
        }
        
        return true;
    }

    /**
     * Get auth config as array
     * 
     * @return array
     */
    public function getAuthConfigArray(): array
    {
        if (empty($this->auth_config)) {
            return [];
        }
        
        $config = json_decode($this->auth_config, true);
        return is_array($config) ? $config : [];
    }

    /**
     * Set auth config from array
     * 
     * @param array $config
     * @return void
     */
    public function setAuthConfigArray(array $config): void
    {
        $this->auth_config = json_encode($config);
    }

    /**
     * Get custom headers as array
     * 
     * @return array
     */
    public function getCustomHeadersArray(): array
    {
        if (empty($this->custom_headers)) {
            return [];
        }
        
        $headers = json_decode($this->custom_headers, true);
        return is_array($headers) ? $headers : [];
    }

    /**
     * Set custom headers from array
     * 
     * @param array $headers
     * @return void
     */
    public function setCustomHeadersArray(array $headers): void
    {
        $this->custom_headers = json_encode($headers);
    }

    /**
     * Get query params as array
     * 
     * @return array
     */
    public function getQueryParamsArray(): array
    {
        if (empty($this->query_params)) {
            return [];
        }
        
        $params = json_decode($this->query_params, true);
        return is_array($params) ? $params : [];
    }

    /**
     * Set query params from array
     * 
     * @param array $params
     * @return void
     */
    public function setQueryParamsArray(array $params): void
    {
        $this->query_params = json_encode($params);
    }

    /**
     * Before save hook - set timestamps
     * 
     * @return void
     */
    public function save(): int|\WP_Error
    {
        $now = current_time('mysql');
        
        if (empty($this->id)) {
            $this->created_at = $now;
        }
        
        $this->updated_at = $now;
        
        // Set created_by if not set
        if (empty($this->created_by)) {
            $this->created_by = get_current_user_id();
        }

        return parent::save();
    }

    /**
     * Test the notification service connection
     * 
     * @param array $test_data Optional test data
     * @return array|WP_Error
     */
    public function testConnection(array $test_data = []): array
    {
        try {
            // Build request URL with query params
            $url = $this->server_url;
            $query_params = $this->getQueryParamsArray();
            if (!empty($query_params)) {
                $url .= '?' . http_build_query($query_params);
            }

            // Build headers
            $headers = [
                'Content-Type' => $this->content_type,
                'User-Agent' => 'KiviCare-Custom-Notification/1.0'
            ];

            // Add custom headers
            $custom_headers = $this->getCustomHeadersArray();
            foreach ($custom_headers as $header) {
                if (!empty($header['key']) && !empty($header['value'])) {
                    $headers[$header['key']] = $header['value'];
                }
            }

            // Add authentication headers
            $auth_config = $this->getAuthConfigArray();
            switch ($this->auth_method) {
                case self::AUTH_API_KEY:
                    if (!empty($auth_config['api_key'])) {
                        $headers['Authorization'] = 'Bearer ' . $auth_config['api_key'];
                    }
                    break;
                case self::AUTH_BEARER:
                    if (!empty($auth_config['token'])) {
                        $headers['Authorization'] = 'Bearer ' . $auth_config['token'];
                    }
                    break;
                case self::AUTH_BASIC:
                    if (!empty($auth_config['username']) && !empty($auth_config['password'])) {
                        $headers['Authorization'] = 'Basic ' . base64_encode($auth_config['username'] . ':' . $auth_config['password']);
                    }
                    break;
            }

            // Build request body
            $body = '';
            if (in_array($this->http_method, [self::METHOD_POST, self::METHOD_PUT, self::METHOD_PATCH])) {
                $body = $this->request_body;
                
                // Replace test variables
                $test_variables = array_merge([
                    'receiver_number' => '+1234567890',
                    'content' => 'Test message from KiviCare',
                    'contentid' => 'test_' . time(),
                    'appointment_id' => 'apt_test_' . time(),
                    'patient_name' => 'Test Patient',
                    'doctor_name' => 'Dr. Test Doctor',
                    'clinic_name' => 'Test Clinic',
                    'appointment_date' => gmdate('Y-m-d'),
                    'appointment_time' => gmdate('H:i A')
                ], $test_data);

                foreach ($test_variables as $key => $value) {
                    $body = str_replace("{{$key}}", $value, $body);
                }
            }

            // Make the request
            $args = [
                'method' => $this->http_method,
                'headers' => $headers,
                'body' => $body,
                'timeout' => 30,
                'sslverify' => $this->enable_ssl
            ];

            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => $response->get_error_message(),
                    'error_code' => $response->get_error_code()
                ];
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_headers = wp_remote_retrieve_headers($response);

            return [
                'success' => $response_code >= 200 && $response_code < 300,
                'response_code' => $response_code,
                'response_body' => $response_body,
                'response_headers' => $response_headers->getAll(),
                'request_url' => $url,
                'request_headers' => $headers,
                'request_body' => $body
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'exception'
            ];
        }
    }
}
