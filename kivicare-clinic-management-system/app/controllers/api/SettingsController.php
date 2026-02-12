<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\baseClasses\KCBase;
use App\baseClasses\KCModuleRegistry;
/*Settings Controller APIs*/
use App\controllers\api\SettingsController\CommonSettings;


use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_REST_Server;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class SettingsController
 * 
 * API Controller for Settings-related endpoints
 * 
 * @package App\controllers\api
 */
class SettingsController extends KCBaseController
{

    /**
     * @var string The base route for this controller
     */
    protected $route = 'settings';
    public $db;
    public $request;
    public $currentUserRole = null;
    /**
     * Module registry instance
     *
     * @var KCModuleRegistry
     */
    private KCModuleRegistry $moduleRegistry;
    /**
     * Validate setting key
     *
     * @param string $param
     * @return bool|WP_Error
     */

    public function __construct()
    {
        parent::__construct();
        $this->currentUserRole = $this->kcbase->getLoginUserRole();
    }
    public function validateSettingKey($param)
    {
        if (empty($param)) {
            return new WP_Error('invalid_setting_key', __('Setting key is required', 'kivicare-clinic-management-system'));
        }

        // Allow alphanumeric, underscore, and dash
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $param)) {
            return new WP_Error('invalid_setting_key', __('Setting key can only contain letters, numbers, underscores, and dashes', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Validate setting group
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateSettingGroup($param)
    {
        $allowed_groups = [
            'general',
            'appearance',
            'modules',
            'payment',
            'notification',
            'appointment',
            'clinic',
            'user',
            'security',
            'email-template',
            'sms',
            'currency',
            'timezone',
            'language',
            'backup',
            'integrations'
        ];

        if (!in_array($param, $allowed_groups)) {
            return new WP_Error('invalid_setting_group', __('Invalid setting group', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {

        // Change Module Value Status
        $this->registerRoute('/' . $this->route . '/change-module-status', [
            'methods' => ['PUT', 'POST'],
            'callback' => [CommonSettings::getInstance(), 'changeModuleValueStatus'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getUpdateEndpointArgs()
        ]);
    }

    /**
     * Get arguments for the settings endpoint
     * @return array
     */
    public function getSettingsEndpointArgs()
    {
        return [
            'group' => [
                'description' => 'Filter by setting group',
                'type' => 'string',
                'validate_callback' => [$this, 'validateSettingGroup'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'include_private' => [
                'description' => 'Include private/sensitive settings',
                'type' => 'boolean',
                'default' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ]
        ];
    }

    /**
     * Get arguments for update endpoints
     *
     * @return array
     */
    public function getUpdateEndpointArgs()
    {
        return [
            'settings' => [
                'description' => 'Array of setting key-value pairs',
                'type' => 'object',
                'required' => true,
                'validate_callback' => [$this, 'validateSettingsData'],
                'sanitize_callback' => 'kcSanitizeData',
            ]
        ];
    }

    /**
     * Validate settings data
     *
     * @param mixed $param
     * @return bool|WP_Error
     */
    public function validateSettingsData($param)
    {
        if (!is_array($param) && !is_object($param)) {
            return new WP_Error('invalid_settings_data', __('Settings data must be an object', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Check if user has permission to access settings endpoints
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkPermission($request): bool
    {
        if (strpos($request->get_route(), 'custom-field') !== false) {
            if (!$this->isModuleEnabled('custom_fields')) {
                return false;
            }
        }

        if (!$this->checkCapability('read')) {
            return false;
        }

        return $this->checkResourceAccess('settings', 'view');
    }


    public function checkUpdatePermission($request): bool
    {
        if (strpos($request->get_route(), 'custom-field') !== false) {
            if (!$this->isModuleEnabled('custom_fields')) {
                return false;
            }
        }

        if (!$this->checkCapability('read')) {
            return false;
        }

        return $this->checkResourceAccess('settings', 'edit');
    }


    
    protected function getSettingFieldSchema()
    {
        return [
            'holiday' => [
                'module_id' => [
                    'description' => 'Module ID object',
                    'type' => 'object',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($value) => is_array($value) && isset($value['id']) && isset($value['label']),
                ],
                'module_type' => [
                    'description' => 'Module Type object',
                    'type' => 'object',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($value) => is_array($value) && isset($value['id']) && isset($value['label']),
                ],
                'scheduleDate' => [
                    'description' => 'Schedule Date range',
                    'type' => 'object',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($value) => is_array($value) && isset($value['start']) && isset($value['end']),
                ],
                'selectionMode' => [
                    'description' => 'Date selection mode: single, multiple, or range',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => fn($value) => in_array($value, ['single', 'multiple', 'range']),
                ],
                'selectedDates' => [
                    'description' => 'Array of selected dates for multiple mode',
                    'type' => 'array',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($value) => is_array($value),
                ],
                'timeSpecific' => [
                    'description' => 'Whether the holiday is time-specific',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
                'start_time' => [
                    'description' => 'Start time for time-specific holiday',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'end_time' => [
                    'description' => 'End time for time-specific holiday',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
            'holiday_delete' => [
                'id' => [
                    'description' => 'ID of the object',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_numeric($value);
                    },
                ],
            ],
            'configurations' => [
                'module_config' => [
                    'description' => 'List of configured modules',
                    'type' => 'array',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_array($value) && array_reduce($value, function ($carry, $item) {
                            return $carry && is_array($item) &&
                                isset($item['name'], $item['label'], $item['status']);
                        }, true);
                    },
                ],
                'encounter_modules' => [
                    'description' => 'Modules related to encounters',
                    'type' => 'array',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_array($value) && array_reduce($value, function ($carry, $item) {
                            return $carry && is_array($item) &&
                                isset($item['name'], $item['label'], $item['status']);
                        }, true);
                    },
                ],
                'prescription_modules' => [
                    'description' => 'Modules related to prescriptions',
                    'type' => 'array',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_array($value) && array_reduce($value, function ($carry, $item) {
                            return $carry && is_array($item) &&
                                isset($item['name'], $item['label'], $item['status']);
                        }, true);
                    },
                ],
            ],
            'app-configuration' => [
                'clientEmail' => [
                    'description' => 'Firebase client email',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                    },
                ],
                'privateKey' => [
                    'description' => 'Firebase private key',
                    'type' => 'string',
                    'required' => true,
                    // 'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_string($value) && strlen($value) > 0;
                    },
                ],
                'projectId' => [
                    'description' => 'Firebase project ID',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_string($value) && strlen($value) > 0;
                    },
                ],
                'serverKey' => [
                    'description' => 'Firebase server key',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_string($value) && strlen($value) > 0;
                    },
                ],
            ],
            'email_template' => [
                'ID' => [
                    'description' => 'Post ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_numeric($value);
                    },
                ],
                'post_status' => [
                    'description' => 'Post status',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return in_array($value, ['publish', 'draft', 'pending', 'private'], true);
                    },
                ],
                'post_title' => [
                    'description' => 'Email subject/title',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_string($value) && strlen($value) > 0;
                    },
                ],
                'post_content' => [
                    'description' => 'Email body content (may contain HTML and placeholders)',
                    'type' => 'string',
                    'required' => false,
                    'validate_callback' => function ($value) {
                        return is_string($value);
                    },
                ],
            ],
            'sms_whatsapp_template' => [
                'ID' => [
                    'description' => 'Post ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_numeric($value);
                    },
                ],
                'post_status' => [
                    'description' => 'Post status',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return in_array($value, ['publish', 'draft', 'pending', 'private'], true);
                    },
                ],
                'content_sid' => [
                    'description' => 'Email subject/title',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_string($value) && strlen($value) > 0;
                    },
                ],
                'post_content' => [
                    'description' => 'Email body content (may contain HTML and placeholders)',
                    'type' => 'string',
                    'required' => false,
                    'validate_callback' => function ($value) {
                        return is_string($value);
                    },
                ],
            ],
            'patient_setting' => [
                'enable' => [
                    'description' => 'Enable the feature',
                    'type' => 'boolean',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($value) => is_bool($value),
                ],
                'only_number' => [
                    'description' => 'Allow only numbers',
                    'type' => 'boolean',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($value) => is_bool($value),
                ],
                'postfix_value' => [
                    'description' => 'Value to append after the main value',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($value) => is_string($value),
                ],
                'prefix_value' => [
                    'description' => 'Value to prepend before the main value',
                    'type' => 'string', // or 'integer' if it must be numeric
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($value) => is_string($value) || is_numeric($value),
                ],
            ],
            'widget_setting' => [
                'afterWoocommerceRedirect' => [
                    'description' => 'Redirect after WooCommerce action',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($value) => is_bool($value),
                ],
                'clinicContactDetails' => [
                    'description' => 'Clinic contact detail object',
                    'type' => 'object',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_array($v) && isset($v['id'], $v['label']),
                ],
                'doctorContactDetails' => [
                    'description' => 'Doctor contact detail object',
                    'type' => 'object',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_array($v) && isset($v['id'], $v['label']),
                ],
                'primaryColor' => [
                    'description' => 'Primary color code',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v),
                ],
                'primaryHoverColor' => [
                    'description' => 'Primary hover color code',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v),
                ],
                'secondaryColor' => [
                    'description' => 'Secondary color code',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v),
                ],
                'secondaryHoverColor' => [
                    'description' => 'Secondary hover color code',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v),
                ],
                'showClinic' => [
                    'description' => 'Show clinic section',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'showClinicAddress' => [
                    'description' => 'Show clinic address',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'showClinicImage' => [
                    'description' => 'Show clinic image',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'showDoctorDegree' => [
                    'description' => 'Show doctor degree',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'showDoctorExperience' => [
                    'description' => 'Show doctor experience',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'showDoctorImage' => [
                    'description' => 'Show doctor image',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'showDoctorRating' => [
                    'description' => 'Show doctor rating',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'showDoctorSpeciality' => [
                    'description' => 'Show doctor speciality',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'showServiceDuration' => [
                    'description' => 'Show service duration',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'showServiceImage' => [
                    'description' => 'Show service image',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'showServicePrice' => [
                    'description' => 'Show service price',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'showServicetype' => [
                    'description' => 'Show service type',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'skip_service_when_single' => [
                    'description' => 'Skip service selection if only one',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'widgetOrder' => [
                    'description' => 'Widget order configuration',
                    'type' => 'array',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_array($value) && array_reduce($value, function ($carry, $item) {
                            return $carry && is_array($item) &&
                                isset($item['att_name'], $item['name'], $item['fixed']);
                        }, true);
                    },
                ],
                'widget_loader' => [
                    'description' => 'Widget loader setting',
                    'type' => 'null',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_null($v),
                ],
                'widget_print' => [
                    'description' => 'Enable printing the widget',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_bool($v),
                ],
            ],
            'google_event_template' => [
                'ID' => [
                    'description' => 'Post ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($value) => is_numeric($value),
                ],
                'post_title' => [
                    'description' => 'Post title with optional placeholders',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($value) => is_string($value) && strlen($value) > 0,
                ],
                'post_content' => [
                    'description' => 'Post content with optional HTML and placeholders',
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => fn($value) => is_string($value),
                ],
            ],
            'googlemeet' => [
                'status' => [
                    'description' => 'Overall status of the operation',
                    'type' => 'boolean',
                    'required' => false,
                    'validate_callback' => fn($value) => is_bool($value),
                ],
                'message' => [
                    'description' => 'Response message',
                    'type' => 'string',
                    'required' => false,
                    'validate_callback' => fn($value) => is_string($value),
                ],
                'data' => [
                    'description' => 'Nested response data',
                    'type' => 'object',
                    'required' => false,
                    'validate_callback' => function ($value) {
                        return is_array($value) &&
                            isset($value['google_meet_event_template']) &&
                            is_array($value['google_meet_event_template']) &&
                            isset($value['google_meet_event_template']['status']) &&
                            isset($value['google_meet_event_template']['message']) &&
                            is_bool($value['google_meet_event_template']['status']) &&
                            is_string($value['google_meet_event_template']['message']);
                    },
                ],
            ],
            'zoom_telemed' => [
                'client_id' => [
                    'description' => 'Client ID',
                    'type' => 'string',
                    'required' => false,
                    'validate_callback' => fn($v) => is_string($v) || is_numeric($v),
                ],
                'client_secret' => [
                    'description' => 'Client Secret',
                    'type' => 'string', // or 'integer' if strictly numeric
                    'required' => false,
                    'validate_callback' => fn($v) => is_string($v) || is_numeric($v),
                ],
                'enableCal' => [
                    'description' => 'Enable calendar integration',
                    'type' => 'string', // or convert to boolean if you're treating "Yes"/"No" that way
                    'required' => false,
                    'validate_callback' => fn($v) => in_array($v, ['Yes', 'No'], true),
                ],
                'redirect_url' => [
                    'description' => 'Redirect URL or ID',
                    'type' => 'string', // or 'integer' if it’s an ID
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_string($v) || is_numeric($v),
                ],
            ],
            'listing_delete' => [
                'id' => [
                    'description' => 'ID of the object',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_numeric($value);
                    },
                ],
            ],
            'listing_update' => [
                'id' => [
                    'description' => 'Record ID',
                    'type' => 'integer',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($value) => is_numeric($value),
                ],
                'label' => [
                    'description' => 'Display label',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_string($v) && strlen($v) > 0,
                ],
                'status' => [
                    'description' => 'Status object with ID and label',
                    'type' => 'object',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($v) {
                        return is_array($v)
                            && isset($v['id'], $v['label'])
                            && is_numeric($v['id'])
                            && is_string($v['label']);
                    },
                ],
                'type' => [
                    'description' => 'Type of record',
                    'type' => 'object',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($v) {
                        return is_array($v) &&
                            isset($v['label'], $v['value']) &&
                            is_string($v['label']) &&
                            is_string($v['value']);
                    },
                ],
            ],
            'custom_field_update' => [
                'id' => [
                    'description' => 'Field ID (can be empty for new)',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_string($v),
                ],
                'label' => [
                    'description' => 'Field label',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_string($v) && strlen($v) > 0,
                ],
                // 'isRequired' => [
                //     'description' => 'Whether the field is required',
                //     'type' => 'boolean',
                //     'required' => false,
                //     // 'sanitize_callback' => 'kcSanitizeData',
                //     'validate_callback' => fn($v) => is_bool($v),
                // ],
                'module_id' => [
                    'description' => 'Array of selected modules',
                    'type' => 'array',
                    'required' => false,
                    // 'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($v) {
                        if (!empty($v)) {
                            return is_array($v) && array_reduce($v, function ($carry, $item) {
                                return $carry &&
                                    is_array($item) &&
                                    isset($item['id'], $item['label'], $item['value']) &&
                                    is_string($item['id']) &&
                                    is_string($item['label']) &&
                                    is_string($item['value']);
                            }, true);
                        }
                        return true;
                    },
                ],
                'module_type' => [
                    'description' => 'Module type object',
                    'type' => 'object',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) =>
                        is_array($v) &&
                        isset($v['id'], $v['label']) &&
                        is_string($v['id']) &&
                        is_string($v['label']),
                ],
                'placeholder' => [
                    'description' => 'Field placeholder text',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_string($v),
                ],
                'status' => [
                    'description' => 'Status object',
                    'type' => 'object',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) =>
                        is_array($v) &&
                        isset($v['id'], $v['label']) &&
                        is_numeric($v['id']) &&
                        is_string($v['label']),
                ],
                'type' => [
                    'description' => 'Field type object',
                    'type' => 'object',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) =>
                        is_array($v) &&
                        isset($v['value'], $v['label']) &&
                        is_string($v['value']) &&
                        is_string($v['label']),
                ],
                'options' => [
                    'description' => 'Field options (string or array)',
                    'type' => 'mixed',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_string($v) || is_array($v),
                ],
                'fields' => [
                    'description' => 'List of sub-fields (if any)',
                    'type' => 'array',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($v) {
                        return is_array($v) && array_reduce($v, function ($carry, $item) {
                            return $carry
                                && isset($item['label'], $item['type'], $item['options'], $item['isRequired'])
                                && is_string($item['label'])
                                && is_array($item['type']) && isset($item['type']['value'], $item['type']['label'])
                                && (is_array($item['options']) || is_string($item['options']))
                                && is_bool($item['isRequired']);
                        }, true);
                    },
                ],
            ],
            'custom_field_delete' => [
                'id' => [
                    'description' => 'ID of the object',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_numeric($value);
                    },
                ],
            ],
            'pro_settings' => [
                'appName' => [
                    'description' => 'Application name',
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => fn($v) => is_string($v),
                ],
                'clinicEncounterPrint' => [
                    'description' => 'Enable print for clinic encounters',
                    'type' => 'boolean',
                    'required' => true,
                    'sanitize_callback' => fn($v) => (bool) $v,
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'doctorEncounterPrint' => [
                    'description' => 'Enable print for doctor encounters',
                    'type' => 'boolean',
                    'required' => true,
                    'sanitize_callback' => fn($v) => (bool) $v,
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'hideEncounterClinicalDetails' => [
                    'description' => 'Hide clinical details in encounters',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => fn($v) => (bool) $v,
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'rtl' => [
                    'description' => 'Right-to-left layout',
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => fn($v) => (bool) $v,
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'themeColor' => [
                    'description' => 'Hex color code for theme',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_hex_color',
                    'validate_callback' => fn($v) => preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $v),
                ],
                'copyrightText' => [
                    'description' => 'Footer copyright text',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => fn($v) => is_string($v),
                ],
                'customNotification' => [
                    'description' => 'Notification methods and status',
                    'type' => 'array',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($v) {
                        return is_array($v) && array_reduce($v, function ($carry, $item) {
                            return $carry &&
                                isset($item['type'], $item['status']) &&
                                is_string($item['type']) &&
                                in_array($item['status'], ['yes', 'no'], true);
                        }, true);
                    },
                ],
                'enableTwilioSms' => [
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => fn($v) => (bool) $v,
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'enableTwilioWhatsApp' => [
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => fn($v) => (bool) $v,
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'enabledCustomizableSms' => [
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => fn($v) => (bool) $v,
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'enabledCustomizableWhatsApp' => [
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => fn($v) => (bool) $v,
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'patientCalendarEvent' => [
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => fn($v) => (bool) $v,
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'googleCalendarConfiguration' => [
                    'type' => 'boolean',
                    'required' => false,
                    'sanitize_callback' => fn($v) => (bool) $v,
                    'validate_callback' => fn($v) => is_bool($v),
                ],
                'googleAccount' => [
                    'description' => 'Google account configuration',
                    'type' => 'object',
                    'required' => false,
                    'validate_callback' => fn($v) =>
                        is_array($v) &&
                        isset($v['client_id'], $v['client_secret'], $v['app_name'], $v['enableCal']) &&
                        is_string($v['client_id']) &&
                        is_string($v['client_secret']) &&
                        is_string($v['app_name']) &&
                        is_bool($v['enableCal']),
                ],
                'googleCalendarClientId' => [
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => fn($v) => is_string($v),
                ],
                'googleCalendarClientSecret' => [
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => fn($v) => is_string($v),
                ],
                'twilioSms' => [
                    'type' => 'object',
                    'required' => true,
                    'validate_callback' => function ($v) {
                        return is_array($v) &&
                            isset($v['account_id'], $v['auth_token'], $v['to_number'], $v['enableSMS']) &&
                            is_string($v['account_id']) &&
                            is_string($v['auth_token']) &&
                            is_string($v['to_number']) &&
                            is_bool($v['enableSMS']);
                    },
                ],
                'twilioWhatsApp' => [
                    'type' => 'object',
                    'required' => false,
                    'validate_callback' => fn($v) =>
                        is_array($v) &&
                        isset($v['wa_account_id'], $v['wa_auth_token'], $v['wa_to_number'], $v['enableWhatsApp']) &&
                        is_string($v['wa_account_id']) &&
                        is_string($v['wa_auth_token']) &&
                        is_string($v['wa_to_number']) &&
                        is_bool($v['enableWhatsApp']),
                ],
                'twilioSmsAccountSID' => [
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => fn($v) => is_string($v),
                ],
                'twilioSmsAuthToken' => [
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => fn($v) => is_string($v),
                ],
                'twilioSmsPhoneNumber' => [
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => fn($v) => is_string($v),
                ],
                'twilioWhatsAppAccountSID' => [
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => fn($v) => is_string($v),
                ],
                'twilioWhatsAppAuthToken' => [
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => fn($v) => is_string($v),
                ],
                'twilioWhatsAppPhoneNumber' => [
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => fn($v) => is_string($v),
                ],
                'wordpressLogoStatus' => [
                    'type' => 'boolean',
                    'sanitize_callback' => fn($v) => (bool) $v,
                    'validate_callback' => fn($v) => is_bool($v),
                ],
            ],
            'appointment_setting' => [
                'cancellationBufferHours' => [
                    'description' => 'Cancellation buffer time in hours',
                    'type' => 'mixed',
                    'required' => false,
                    'sanitize_callback' => fn($v) => (string) $v,
                    'validate_callback' => fn($v) => is_numeric($v) || $v === '',
                ],

                'emailReminderHours' => [
                    'description' => 'Email reminder hours before appointment',
                    'type' => 'mixed',
                    'required' => false,
                    'sanitize_callback' => fn($v) => (string) $v,
                    'validate_callback' => fn($v) => is_numeric($v) || $v === '',
                ],
                'pre_book' => [
                    'description' => 'Minimum hours before appointment can be booked',
                    'type' => 'mixed',
                    'required' => false,
                    'sanitize_callback' => fn($v) => $v === '' ? '' : (string) $v,
                    'validate_callback' => fn($v) => $v === '' || is_numeric($v),
                ],
                'post_book' => [
                    'description' => 'Maximum hours after current time to allow booking',
                    'type' => 'mixed',
                    'required' => false,
                    'sanitize_callback' => fn($v) => $v === '' ? '' : (string) $v,
                    'validate_callback' => fn($v) => $v === '' || is_numeric($v),
                ],
            ],
            'permission_setting' => [
                'type' => [
                    'description' => 'User role type (e.g., administrator, editor)',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => fn($v) => is_string($v) && strlen($v) > 0,
                ],
                'data' => [
                    'description' => 'Role data with capabilities',
                    'type' => 'object',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($v) {
                        return is_array($v) &&
                            array_reduce(array_keys($v), function ($carry, $roleKey) use ($v) {
                                $role = $v[$roleKey];
                                return $carry &&
                                    isset($role['name'], $role['capabilities']) &&
                                    is_string($role['name']) &&
                                    is_array($role['capabilities']);
                            }, true);
                    },
                ],
            ]
        ];
    }
}
