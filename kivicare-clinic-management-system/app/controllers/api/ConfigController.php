<?php

namespace App\controllers\api;

use App\baseClasses\KCBase;
use App\baseClasses\KCBaseController;
use App\baseClasses\KCPaymentGatewayFactory;
use App\models\KCClinic;
use App\models\KCOption;
use KCProApp\controllers\api\KCProPermissionSetting;
use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use App\controllers\api\SettingsController\AppointmentSetting;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class ConfigController
 * 
 * API Controller for Configuration-related endpoints
 * 
 * @package App\controllers\api
 */
class ConfigController extends KCBaseController
{
    /**
     * @var string The base route for this controller
     */
    protected $route = 'config';

    /**
     * Get common arguments used across multiple endpoints
     *
     * @return array
     */
    private function getCommonArgs()
    {
        return [
            'key' => [
                'description' => 'Configuration key',
                'type' => 'string',
                'validate_callback' => [$this, 'validateConfigKey'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'value' => [
                'description' => 'Configuration value',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'group' => [
                'description' => 'Configuration group',
                'type' => 'string',
                'validate_callback' => [$this, 'validateConfigGroup'],
                'sanitize_callback' => 'sanitize_text_field',
            ]
        ];
    }

    /**
     * Validate configuration key
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateConfigKey($param)
    {
        if (empty($param)) {
            return new WP_Error('invalid_config_key', __('Configuration key is required', 'kivicare-clinic-management-system'));
        }

        // Allow alphanumeric, underscore, and dash
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $param)) {
            return new WP_Error('invalid_config_key', __('Configuration key can only contain letters, numbers, underscores, and dashes', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Validate configuration group
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateConfigGroup($param)
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
            'addon',
            'system'
        ];

        if (!in_array($param, $allowed_groups)) {
            return new WP_Error('invalid_config_group', __('Invalid configuration group', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        $this->registerRoute('/' . $this->route . '/switch-language', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'switchLanguage'],
            'permission_callback' => [$this, 'checkPermission'],
            'validate_callback' => function ($request) {
                $language = $request['language'] ?? '';
                if (empty($language)) {
                    return new WP_Error('invalid_language', __('Language parameter is required', 'kivicare-clinic-management-system'));
                }
                if (!array_find(getAvailableLanguages(), fn($lang) => $lang['lang'] === $language)) {
                    return new WP_Error('invalid_language', __('Invalid language code', 'kivicare-clinic-management-system'));
                }
                return true;
            },
            'args' => [
                'language' => [
                    'description' => 'Language code to switch to',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ]
            ]
        ]);

        // Get all configuration data
        $this->registerRoute('/' . $this->route, [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getConfig'],
            'permission_callback' => '__return_true',
            'args' => $this->getConfigEndpointArgs()
        ]);

        // Get specific configuration by group
        $this->registerRoute('/' . $this->route . '/(?P<group>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getConfigByGroup'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getGroupEndpointArgs()
        ]);

        // Update configuration
        $this->registerRoute('/' . $this->route, [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'updateConfig'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getUpdateEndpointArgs()
        ]);

        // Get system status
        $this->registerRoute('/' . $this->route . '/system-status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getSystemStatus'],
            'permission_callback' => [$this, 'checkAdminPermission'],
            'args' => []
        ]);

        // Get user preferences
        $this->registerRoute('/' . $this->route . '/user-preferences', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getUserPreferences'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => []
        ]);

        // Update user preferences
        $this->registerRoute('/' . $this->route . '/user-preferences', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'updateUserPreferences'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getUserPreferencesEndpointArgs()
        ]);
    }

    /**
     * Get arguments for the config endpoint
     *
     * @return array
     */
    private function getConfigEndpointArgs()
    {
        return [
            'group' => [
                'description' => 'Filter by configuration group',
                'type' => 'string',
                'validate_callback' => [$this, 'validateConfigGroup'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'include_addons' => [
                'description' => 'Include addon information',
                'type' => 'boolean',
                'default' => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'include_modules' => [
                'description' => 'Include module information',
                'type' => 'boolean',
                'default' => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ]
        ];
    }

    /**
     * Get arguments for the group endpoint
     *
     * @return array
     */
    private function getGroupEndpointArgs()
    {
        return [
            'group' => array_merge($this->getCommonArgs()['group'], ['required' => true])
        ];
    }

    /**
     * Get arguments for the update endpoint
     *
     * @return array
     */
    private function getUpdateEndpointArgs()
    {
        return [
            'configs' => [
                'description' => 'Array of configuration key-value pairs',
                'type' => 'object',
                'required' => true,
                'validate_callback' => [$this, 'validateConfigData'],
            ]
        ];
    }

    /**
     * Get arguments for user preferences endpoint
     *
     * @return array
     */
    private function getUserPreferencesEndpointArgs()
    {
        return [
            'preferences' => [
                'description' => 'User preference data',
                'type' => 'object',
                'required' => true,
                'validate_callback' => [$this, 'validateUserPreferences'],
            ]
        ];
    }

    /**
     * Validate configuration data
     *
     * @param mixed $param
     * @return bool|WP_Error
     */
    public function validateConfigData($param)
    {
        if (!is_array($param) && !is_object($param)) {
            return new WP_Error('invalid_config_data', __('Configuration data must be an object', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Validate user preferences
     *
     * @param mixed $param
     * @return bool|WP_Error
     */
    public function validateUserPreferences($param)
    {
        if (!is_array($param) && !is_object($param)) {
            return new WP_Error('invalid_preferences', __('User preferences must be an object', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Get system configuration and user data
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getConfig(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();

            if (!function_exists('get_plugins')) {
                include_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $currentLoginUserRole = $this->kcbase->getLoginUserRole();
            $response = [];
            $user_id = is_user_logged_in() ? get_current_user_id() : null;

            // Get all options in a single query for better performance
            $options = KCOption::getMultiple([
                'site_logo',
                'site_mini_logo',
                'theme_color',
                'rtl_style',
                'country_code',
                'user_registration_form_setting',
                'setup_config',
                'google_cal_setting',
                'google_meet_setting',
                'patient_cal_setting',
                'patient_id_setting',
                'wordpress_logo',
                'wordpress_logo_status',
                'copyrightText',
                'restrict_only_same_day_book_appointment',
                'restrict_appointment',
                'appointment_description_config_data',
                'request_helper_status'
            ]);

            // Basic configuration
            $response['current_user_role'] = $currentLoginUserRole;

            // Get module configuration from kivicare_modules option
            $modules_data = kcGetModules();
            $module_config_array = $modules_data['module_config'] ?? [];

            // Convert array format to object format for frontend compatibility
            // Frontend expects: {'custom_fields': '1', 'billing': '0', etc.}
            $module_config_object = [];
            foreach ($module_config_array as $module) {
                if (isset($module['name']) && isset($module['status'])) {
                    // Keep as '1'/'0' for frontend compatibility
                    $module_config_object[$module['name']] = ($module['status'] === '1' || $module['status'] === 1) ? '1' : '0';
                }
            }

            $response['module_config'] = $module_config_object;
            $response['countryCode'] = $options['country_code'] ?? 'us';
            $response['hideUtilityLinks'] = $options['request_helper_status'] ?? 'off';
            $response['showOtherGender'] = $options['user_registration_form_setting'] ?? 'off';
            $response['site_logo'] = !empty($options['site_logo'])
                ? wp_get_attachment_url($options['site_logo'])
                : KIVI_CARE_DIR_URI . 'assets/images/logo.png';
            $response['site_mini_logo'] = !empty($options['site_mini_logo'])
                ? wp_get_attachment_url($options['site_mini_logo'])
                : KIVI_CARE_DIR_URI . 'assets/images/logo-mini.png';
            $response['theme_color'] = $options['theme_color'] ?? '#4874dc';
            $response['is_rtl'] = $options['rtl_style'] ?? false;
            $response['appointment_file_upload_enabled'] = kcAppointmentMultiFileUploadEnable();
            $response['currency_detail'] = KCClinic::getClinicCurrencyPrefixAndPostfix();
            $response['sidebar_data'] = kcDashboardSidebarArray([$currentLoginUserRole]);
            $response['setup_config'] = kcGetSetupWizardOptions();
            $response['copyrightText'] = $options['copyrightText'] ?? '';

            $wp_logo_id = $options['wordpress_logo'] ?? '';
            $response['wordpressLogo'] = [
                'id' => $wp_logo_id,
                'url' => $wp_logo_id ? wp_get_attachment_url($wp_logo_id) : '',
            ];
            $response['wordpressLogoStatus'] = (($options['wordpress_logo_status'] ?? 'off') === 'on') ? 1 : 0;

            // Add addon information
            $response['addOns'] = $this->getAddonInformation();

            // Add module information
            $response['modules'] = $this->getModuleInformation();

            /**
             * Payment methods with detailed configuration.
             */
            $response['payment_methods'] = $this->getPaymentMethodsArray();

            // Define the sub-module configurations to process
            $response['encounter_modules'] = apply_filters('kcpro_get_encounter_list',[]);
            $response['prescription_modules'] = apply_filters('kcpro_get_prescription_list',[]);

            // Add configuration settings
            $app_config = get_option('kiviCare_onesignal_config', []);
            $response['server_client_id'] = $app_config['server_client_id'] ?? '';

            // Only add user-specific data if logged in
            if ($user_id && is_user_logged_in()) {
                $userObj = new \WP_User($user_id);
                $final_capabilities = $userObj->allcaps;
                $user_role_key = \App\baseClasses\KCPermissions::get_user_role($user_id);

                if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
                    $lookup_key = ($user_role_key === 'admin') ? 'administrator' : str_replace(KIVI_CARE_PREFIX, '', $user_role_key);

                    // Get the final, correct state of all Kivicare permissions
                    $permission_instance = KCProPermissionSetting::getInstance();
                    $saved_settings = $permission_instance->getPermissionSetting($request)->get_data()['data'] ?? [];
                    $role_defaults = $permission_instance->buildDefaultPermissions()[$lookup_key]['capabilities'] ?? [];
                    $role_saved = $saved_settings[$lookup_key]['capabilities'] ?? [];
                    $merged_capabilities = array_replace_recursive($role_defaults, $role_saved);

                    // Directly overwrite the base capabilities with our merged, correct ones.
                    $prefix = KIVI_CARE_PREFIX;
                    foreach ($merged_capabilities as $module => $permissions) {
                        if (is_array($permissions)) {
                            foreach ($permissions as $perm_key => $is_allowed) {
                                $final_capabilities[$prefix . $perm_key] = (bool) $is_allowed;
                            }
                        }
                    }
                }

                $response['user_capabilities'] = $final_capabilities;
                $response['user_email'] = $userObj->user_email;
                $response['user_name'] = $userObj->display_name;

                // Add user profile image
                $profile_image_url = null;
                $profile_image_meta_key = '';

                // Determine the correct meta key based on user role
                switch ($currentLoginUserRole) {
                    case $this->kcbase->getDoctorRole():
                        $profile_image_meta_key = 'doctor_profile_image';
                        break;
                    case $this->kcbase->getPatientRole():
                        $profile_image_meta_key = 'patient_profile_image';
                        break;
                    case $this->kcbase->getReceptionistRole():
                        $profile_image_meta_key = 'receptionist_profile_image';
                        break;
                    case $this->kcbase->getClinicAdminRole():
                        $profile_image_meta_key = 'clinic_admin_profile_image';
                        break;
                }

                $imageKey = '';
                if ($profile_image_meta_key) {
                    $profile_image_id = get_user_meta($user_id, $profile_image_meta_key, true);
                    if ($profile_image_id) {
                        $profile_image_url = wp_get_attachment_url($profile_image_id);
                    }

                    // Determine the correct image key based on role
                    if ($currentLoginUserRole === $this->kcbase->getDoctorRole()) {
                        $imageKey = 'doctor_image_url';
                    } elseif ($currentLoginUserRole === $this->kcbase->getPatientRole()) {
                        $imageKey = 'patient_image_url';
                    } elseif ($currentLoginUserRole === $this->kcbase->getReceptionistRole()) {
                        $imageKey = 'receptionist_image_url';
                    } elseif ($currentLoginUserRole === $this->kcbase->getClinicAdminRole()) {
                        $imageKey = 'clinic_admin_image_url';
                    }
                }

                // Always return user_profile_image for backward compatibility
                $response['user_profile_image'] = $profile_image_url;

                // Also add role-specific image key for consistency
                if (!empty($imageKey)) {
                    $response[$imageKey] = $profile_image_url;
                }

                // Add doctor_id if user is a doctor
                if ($currentLoginUserRole === $this->kcbase->getDoctorRole()) {
                    $response['doctor_id'] = get_current_user_id();
                }

                // Add current user id
                $response['current_user_id'] = $user_id;

                // Add default clinic ID if not using KiviCare Pro
                if (isKiviCareProActive()) {
                    if ($currentLoginUserRole == $this->kcbase->getClinicAdminRole()) {
                        $response['clinic_id'] = KCClinic::getClinicIdOfClinicAdmin();
                    } elseif ($currentLoginUserRole == $this->kcbase->getReceptionistRole()) {
                        $response['clinic_id'] = KCClinic::getClinicIdOfReceptionist();
                    } elseif ($currentLoginUserRole == 'administrator') {
                        $response['clinic_id'] = KCClinic::kcGetDefaultClinicId();
                    }
                } else {
                    // Default clinic id if pro not active
                    $response['clinic_id'] = KCClinic::kcGetDefaultClinicId();
                }

                if ($this->kcbase->getLoginUserRole() === $this->kcbase->getPatientRole()) {
                    $response['payment_gateway'] = KCPaymentGatewayFactory::get_available_gateways(true);
                }

                $wc_data = kc_woo_generate_client_auth('kivicare_app', $user_id, 'read_write');
                $response = array_merge($response, $wc_data);

                // Add permission module structure
                $response['permission_module'] = $this->getPermissionModuleStructure($request, $user_id, $user_role_key);
            }

            // Add home page URL for QR code functionality  
            $response['home_page'] = home_url();

            // Add Google Calendar settings
            $googleCalData = $options['google_cal_setting'] ?? [];
            $response['google_calendar'] = !empty($googleCalData['enableCal']) && !empty($googleCalData['client_id']) && !empty($googleCalData['client_secret']) && !empty($googleCalData['app_name']) ? 'yes' : 'no';

            $patientCalData = $options['patient_cal_setting'] ?? 'no';
            $response['patient_calendar'] = $patientCalData;

            $googleMeetData = $options['google_meet_setting'] ?? [];
            $response['google_meet'] = (
                isset($googleMeetData['enableCal'], $googleMeetData['client_id'], $googleMeetData['client_secret'], $googleMeetData['app_name']) &&
                strtolower($googleMeetData['enableCal']) === 'yes' &&
                !empty($googleMeetData['client_id']) &&
                !empty($googleMeetData['client_secret']) &&
                !empty($googleMeetData['app_name'])
            ) ? 'yes' : 'no';

            $zoomSetting = get_option(KIVI_CARE_PREFIX . 'zoom_telemed_setting', []);

            // Define the legacy key name. Use the constant if it exists, otherwise use the string literal.
            $legacy_s2s_key = defined('KIVICARE_TELEMED_PREFIX') ? KIVICARE_TELEMED_PREFIX . 'zoom_telemed_server_to_server_oauth_status' : 'kiviCare_zoom_telemed_server_to_server_oauth_status';

            // FOR UPGRADE COMPATIBILITY: Check the separate legacy Server-to-Server status key.
            $legacy_s2s_status = get_option($legacy_s2s_key, false);
            $is_telemed_active = ($legacy_s2s_status === 'Yes') || (!empty($zoomSetting['enableCal']) && $zoomSetting['enableCal'] === 'Yes');
            $response['is_telemed_activated'] = $is_telemed_active ? 'yes' : 'no';

            // Add patient unique ID settings
            $patientIdSetting = $options['patient_id_setting'] ?? [];
            $response['patient_unique_id_setting'] = [
                'enable' => isset($patientIdSetting['enable']) ? (bool)$patientIdSetting['enable'] : false,
                'only_number' => isset($patientIdSetting['only_number']) ? (bool)$patientIdSetting['only_number'] : false,
                'prefix_value' => $patientIdSetting['prefix_value'] ?? '',
                'postfix_value' => $patientIdSetting['postfix_value'] ?? ''
            ];

            // Add available language translations
            $response['kc_available_translations'] = getAvailableLanguages();
            $response['current_language'] = $user_id ? get_user_locale() : get_locale();

            if (!empty($params['group'])) {
                $response = $this->filterConfigByGroup($response, $params['group']);
            }

            $min = 60 * get_option('gmt_offset');
            $sign = $min < 0 ? "-" : "+";
            $absmin = abs($min);
            $response['UTC'] = sprintf("%s%02d:%02d", $sign, $absmin / 60, $absmin % 60);
            $response['is_uploadfile_appointment'] = KCOption::get('multifile_appointment', 'off');
            $response['global_date_format'] = get_option('date_format', 'D-MM-YYYY');
            $allowEncounterEdit = KCOption::get('encounter_edit_after_close_status', 'off') === 'on';
            $response['allowEncounterEdit'] = $allowEncounterEdit ? 'on' : 'off';
            $country_calling_code = KCOption::get('country_calling_code', '');
            $country_code = KCOption::get('country_code', '');
            $response['default_calling_country_code'] = $country_calling_code;
            $response['default_country_code'] = $country_code;

            $appointment_settings_response = AppointmentSetting::getInstance()->getAppointmentSetting($request);
            if ($appointment_settings_response->get_data()['status']) {
                $response = array_merge($response, $appointment_settings_response->get_data()['data']);
            }

            $response['wp_timezone'] = wp_timezone_string();
            $response['current_server_time'] = current_time('mysql');

            $response['only_same_day_book'] = $options['restrict_only_same_day_book_appointment'] ?? 'off';
            $response['pre_book'] = $options['restrict_appointment']['pre'] ?? 0;
            $response['post_book'] = $options['restrict_appointment']['post'] ?? 365;
            $response['appointmentDescription'] = is_bool($options['appointment_description_config_data'])
                ? 'off'
                : $options['appointment_description_config_data'];

            // get allow meme types
            $response['allowed_mime_types'] = get_allowed_mime_types();
            return $this->response($response, __('Configuration data retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve configuration data', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Get configuration by specific group
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getConfigByGroup(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $group = $request->get_param('group');
            $config = [];

            switch ($group) {
                case 'general':
                    $config = $this->getGeneralConfig();
                    break;
                case 'appearance':
                    $config = $this->getAppearanceConfig();
                    break;
                case 'modules':
                    $config = $this->getModuleInformation();
                    break;
                case 'addon':
                    $config = $this->getAddonInformation();
                    break;
                case 'system':
                    $config = $this->getSystemConfig();
                    break;
                default:
                    return $this->response(null, __('Invalid configuration group', 'kivicare-clinic-management-system'), false, 400);
            }

            /* translators: %s: Configuration group name */
            return $this->response($config, sprintf(__('%s configuration retrieved successfully', 'kivicare-clinic-management-system'), ucfirst($group)));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve configuration group', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Update configuration settings
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function updateConfig(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();
            $configs = $params['configs'];
            $updated = [];
            $errors = [];

            foreach ($configs as $key => $value) {
                // Validate and sanitize the key
                if ($this->validateConfigKey($key) !== true) {
                    $errors[$key] = 'Invalid configuration key';
                    continue;
                }

                // Update the configuration
                $result = KCOption::set($key, $value);

                if ($result) {
                    $updated[$key] = $value;
                } else {
                    $errors[$key] = 'Failed to update configuration';
                }
            }

            $response = [
                'updated' => $updated,
                'errors' => $errors,
                'total_updated' => count($updated),
                'total_errors' => count($errors)
            ];

            if (empty($errors)) {
                return $this->response($response, __('Configuration updated successfully', 'kivicare-clinic-management-system'));
            } else {
                return $this->response($response, __('Configuration updated with some errors', 'kivicare-clinic-management-system'), true, 207);
            }
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update configuration', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Get system status information
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getSystemStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $status = [
                'wordpress' => [
                    'version' => get_bloginfo('version'),
                    'multisite' => is_multisite(),
                    'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
                ],
                'server' => [
                    'php_version' => phpversion(),
                    'mysql_version' => $this->getMySQLVersion(),
                    'server_info' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown',
                    'max_execution_time' => ini_get('max_execution_time'),
                    'memory_limit' => ini_get('memory_limit'),
                    'post_max_size' => ini_get('post_max_size'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                ],
                'kivicare' => [
                    'version' => KIVI_CARE_VERSION,
                    'database_version' => KCOption::get('database_version'),
                    'addons' => $this->getAddonInformation(),
                ],
                'theme' => [
                    'name' => wp_get_theme()->get('Name'),
                    'version' => wp_get_theme()->get('Version'),
                    'active_theme' => get_option('stylesheet'),
                ]
            ];

            return $this->response($status, __('System status retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve system status', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Get user preferences
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getUserPreferences(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $user_id = get_current_user_id();

            if (!$user_id) {
                return $this->response(null, __('User not logged in', 'kivicare-clinic-management-system'), false, 401);
            }

            $preferences = get_user_meta($user_id, 'kc_user_preferences', true);

            if (empty($preferences)) {
                $preferences = $this->getDefaultUserPreferences();
            }

            return $this->response($preferences, __('User preferences retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve user preferences', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Update user preferences
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function updateUserPreferences(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $user_id = get_current_user_id();

            if (!$user_id) {
                return $this->response(null, __('User not logged in', 'kivicare-clinic-management-system'), false, 401);
            }

            $params = $request->get_params();
            $preferences = $params['preferences'];

            $result = update_user_meta($user_id, 'kc_user_preferences', $preferences);

            if ($result !== false) {
                return $this->response($preferences, __('User preferences updated successfully', 'kivicare-clinic-management-system'));
            } else {
                return $this->response(null, __('Failed to update user preferences', 'kivicare-clinic-management-system'), false, 500);
            }
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update user preferences', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Get addon information
     *
     * @return array
     */
    private function getAddonInformation(): array
    {

        return [
            'kiviPro' => [
                'active' => isKiviCareProActive(),
                'version' => isKiviCareProActive() ? getKiviCareProVersion() : null
            ],
            'telemed' => [
                'active' => isKiviCareTelemedActive(),
                'version' => isKiviCareTelemedActive() ? kcGetAddonVersion('kivicare-telemed') : null,
            ],
            'googlemeet' => [
                'active' => isKiviCareGoogleMeetActive(),
                'version' => isKiviCareGoogleMeetActive() ? kcGetAddonVersion('kivicare-googlemeet') : null
            ],
            'razorpay' => [
                'active' => isKiviCareRazorpayActive(),
                'version' => isKiviCareRazorpayActive() ? kcGetAddonVersion('kivicare-razorpay') : null
            ],
            'stripepay' => [
                'active' => isKiviCareStripepayActive(),
                'version' => isKiviCareStripepayActive() ? kcGetAddonVersion('kivicare-stripepay') : null
            ],
            'api' => [
                'active' => isKiviCareAPIActive(),
                'version' => isKiviCareAPIActive() ? kcGetAddonVersion('kivicare-api') : null
            ],
            'bodyChart' => [
                'active' => isKiviCareBodyChartActive(),
                'version' => isKiviCareBodyChartActive() ? kcGetAddonVersion('kivicare-bodychart') : null
            ],
            'webhooks' => [
                'active' => isKiviCareWebhooksAddonActive(),
                'version' => isKiviCareWebhooksAddonActive() ? kcGetAddonVersion('kivicare-webhooks') : null
            ],
        ];
    }

    /**
     * Get module information
     *
     * @return array
     */
    private function getModuleInformation(): array
    {
        try {
            // Get module settings in one query
            $moduleOptions = KCOption::getMultiple(['modules', 'enocunter_modules', 'prescription_module']);

            if (!is_array($moduleOptions)) {
                return [
                    'encounter_module' => 0,
                    'prescription_module' => 0,
                    'enabled_modules' => [],
                ];
            }

            return [
                'encounter_module' => !empty($moduleOptions['enocunter_modules']) && is_string($moduleOptions['enocunter_modules']) ? (json_decode($moduleOptions['enocunter_modules']) ?? 0) : 0,
                'prescription_module' => !empty($moduleOptions['prescription_module']) && is_string($moduleOptions['prescription_module']) ? (json_decode($moduleOptions['prescription_module']) ?? 0) : 0,
                'enabled_modules' => !empty($moduleOptions['modules']) && is_string($moduleOptions['modules']) ? (json_decode($moduleOptions['modules'], true) ?? []) : [],
            ];
        } catch (\Exception $e) {
            return [
                'encounter_module' => 0,
                'prescription_module' => 0,
                'enabled_modules' => [],
            ];
        }
    }

    /**
     * Get payment methods array with detailed configuration
     * Only returns enabled payment methods
     *
     * @return array
     */
    private function getPaymentMethodsArray(): array
    {
        $payment_methods = [];
        $gateways = \App\baseClasses\KCPaymentGatewayFactory::get_available_gateways();

        // Map gateway IDs to user's expected format
        $gateway_id_mapping = [
            'razorpay' => 'razorPay',
            'stripe' => 'stripe',
            'stripepay' => 'stripe',
            'woocommerce' => 'wooCommerce',
            'manual' => 'offline',
            'paypal' => 'paypal'
        ];

        foreach ($gateways as $gateway_id => $gateway_data) {
            $instance = $gateway_data['instance'] ?? null;

            if (!$instance) {
                continue;
            }

            // Only include enabled payment methods
            $is_enabled = $instance->is_enabled() ?? false;
            if (!$is_enabled) {
                continue;
            }

            $mapped_id = $gateway_id_mapping[$gateway_id] ?? $gateway_id;
            // Use get_settings() method to properly retrieve settings
            $settings = method_exists($instance, 'get_settings') ? $instance->get_settings() : ($instance->settings ?? []);

            // Build payment method object
            $payment_method = [
                'paymentMethod' => $mapped_id,
            ];

            // For offline/manual payment, only include paymentMethod
            if ($gateway_id === 'manual') {
                unset($payment_method['paymentURL']); // Remove URL for offline to match desired output
                $payment_methods[] = $payment_method;
                continue;
            }
            // Same for woocommerce? User JSON had "paymentMethod": "wooCommerce" and nothing else.
            if ($gateway_id === 'woocommerce') {
                // $checkout_page_id = get_option('woocommerce_checkout_page_id');
                // $payment_method['paymentURL'] = $checkout_page_id ? get_permalink($checkout_page_id) : site_url();
                $payment_methods[] = $payment_method;
                continue;
            }

            // For gateways that support environment/mode
            $environment = 'live';
            $secret_key = '';
            $public_key = '';
            $payment_url = '';

            switch ($gateway_id) {
                case 'razorpay':
                    // Razorpay - get settings from instance first, then fallback to option
                    $razorpay_settings = $settings;

                    // If settings are empty or missing credentials, try getting directly from option
                    if (empty($razorpay_settings) || empty($razorpay_settings['key_id']) || empty($razorpay_settings['key_secret'])) {
                        $option_settings = get_option(KIVI_CARE_PREFIX . 'razorpay_setting', []);
                        if (is_string($option_settings)) {
                            $option_settings = json_decode($option_settings, true) ?? [];
                        }
                        // Merge option settings with instance settings (option takes precedence)
                        if (!empty($option_settings)) {
                            $razorpay_settings = array_merge($razorpay_settings, $option_settings);
                        }
                    }

                    $mode = $razorpay_settings['mode'] ?? 'sandbox';
                    $environment = ($mode === 'sandbox') ? 'test' : 'live';
                    $secret_key = $razorpay_settings['key_secret'] ?? '';
                    $public_key = $razorpay_settings['key_id'] ?? '';
                    $payment_url = 'https://api.razorpay.com/v1';
                    break;

                case 'paypal':
                    // PayPal uses 'mode' with id 0 (sandbox) or 1 (live)
                    $mode = $settings['mode'] ?? [];
                    if (is_array($mode) && isset($mode['id'])) {
                        $environment = ($mode['id'] == '0' || $mode['id'] === 0) ? 'test' : 'live';
                    } else {
                        $environment = ($mode == '0' || $mode === 0) ? 'test' : 'live';
                    }
                    $secret_key = $settings['client_secret'] ?? '';
                    $public_key = $settings['client_id'] ?? '';
                    $payment_url = ($environment === 'test')
                        ? 'https://api.sandbox.paypal.com'
                        : 'https://api.paypal.com';
                    break;

                case 'stripe':
                case 'stripepay':
                    // Stripe - get settings from instance or directly from options
                    $stripe_settings = $settings;
                    
                    // Fallback to option if empty
                    if (empty($stripe_settings) || (empty($stripe_settings['api_key']) && empty($stripe_settings['publishable_key']))) {
                         $option_key = defined('KIVI_CARE_PREFIX') ? KIVI_CARE_PREFIX . 'stripepay_setting' : 'kiviCare_stripepay_setting';
                         $option_settings = get_option($option_key, []);
                         if (is_string($option_settings)) {
                            $option_settings = json_decode($option_settings, true) ?? [];
                         }
                         if (!empty($option_settings)) {
                             $stripe_settings = array_merge($stripe_settings, $option_settings);
                         }
                    }
                    
                    $mode = $stripe_settings['mode'] ?? 'sandbox';
                    $environment = ($mode === 'sandbox' || $mode === 'test') ? 'test' : 'live';
                    // Stripe addon uses 'api_key' for secret, 'publishable_key' for public
                    $secret_key = $stripe_settings['api_key'] ?? $stripe_settings['secret_key'] ?? $stripe_settings['secretKey'] ?? '';
                    $public_key = $stripe_settings['publishable_key'] ?? $stripe_settings['publishableKey'] ?? $stripe_settings['public_key'] ?? '';
                    $payment_url = 'https://api.stripe.com/v1';
                    break;
            }

            // Populate fields - always include structure if it's one of the processed gateways
            $payment_method['environment'] = $environment;
            $payment_method['secretKey'] = $secret_key;
            $payment_method['publicKey'] = $public_key;
            $payment_method['paymentURL'] = $payment_url;

            $payment_methods[] = $payment_method;
        }

        return $payment_methods;
    }

    /**
     * Get general configuration
     *
     * @return array
     */
    private function getGeneralConfig(): array
    {
        return [
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url(),
            'admin_email' => get_option('admin_email'),
            'timezone' => get_option('timezone_string'),
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
        ];
    }

    /**
     * Get appearance configuration
     *
     * @return array
     */
    private function getAppearanceConfig(): array
    {
        // Get all appearance options in a single query
        $options = KCOption::getMultiple(['site_logo', 'theme_color', 'rtl_style']);

        return [
            'site_logo' => !empty($options['site_logo'])
                ? wp_get_attachment_url($options['site_logo'])
                : KIVI_CARE_DIR_URI . 'assets/images/logo.png',
            'theme_color' => $options['theme_color'] ?? '#4874dc',
            'is_rtl' => $options['rtl_style'] ?? false,
        ];
    }

    /**
     * Get system configuration
     *
     * @return array
     */
    private function getSystemConfig(): array
    {
        // Get system options in a single query
        $databaseVersion = KCOption::get('database_version');

        return [
            'version' => KIVI_CARE_VERSION,
            'database_version' => $databaseVersion,
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            'cron_enabled' => !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
        ];
    }

    public function switchLanguage(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();
            $language = $params['language'];

            // Update user locale
            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), 'locale', $language);
                switch_to_locale($language);
            } else {
                return $this->response(null, __('User not logged in', 'kivicare-clinic-management-system'), false, 401);
            }
            $locale_data_kc = function_exists('kc_get_jed_locale_data') ? kc_get_jed_locale_data('kivicare-clinic-management-system') : [];
            $theme_mode = KCOption::get('theme_mode', 'false');
            $is_rtl_language = is_rtl();

            if ($theme_mode == 'true') {
                $is_rtl = true;
                $dir = 'rtl';
            } else {
                $is_rtl = $is_rtl_language;
                $dir = $is_rtl_language ? 'rtl' : 'ltr';
            }

            // Prepare response data
            $response_data = [
                'locale_data' => $locale_data_kc,
                'is_rtl' => $is_rtl,
                'direction' => $dir,
                'locale' => $language
            ];

            return $this->response($response_data, __('Language switched successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to switch language', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Get default user preferences
     *
     * @return array
     */
    private function getDefaultUserPreferences(): array
    {
        return [
            'language' => 'en',
            'notifications' => [
                'email' => true,
                'browser' => true,
            ],
            'dashboard' => [
                'widgets' => ['appointments', 'patients', 'revenue'],
                'layout' => 'grid'
            ]
        ];
    }

    /**
     * Filter configuration by group
     *
     * @param array $config
     * @param string $group
     * @return array
     */
    private function filterConfigByGroup(array $config, string $group): array
    {
        switch ($group) {
            case 'general':
                return array_intersect_key($config, array_flip(['current_user_role', 'setup_config']));
            case 'appearance':
                return array_intersect_key($config, array_flip(['site_logo', 'theme_color', 'is_rtl']));
            case 'modules':
                return isset($config['modules']) ? $config['modules'] : [];
            case 'addon':
                return isset($config['addOns']) ? $config['addOns'] : [];
            default:
                return $config;
        }
    }

    /**
     * Get MySQL version
     *
     * @return string
     */
    private function getMySQLVersion(): string
    {
        global $wpdb;
        return $wpdb->get_var('SELECT VERSION()');
    }

    /**
     * Get permission module structure
     *
     * @param WP_REST_Request $request
     * @param int $user_id
     * @param string $user_role_key
     * @return array
     */
    private function getPermissionModuleStructure(WP_REST_Request $request, int $user_id, string $user_role_key): array
    {
        // Check if KiviCare Pro is active before accessing permission settings
        if (!isKiviCareProActive() || !class_exists('KCProApp\controllers\api\KCProPermissionSetting')) {
            return ['roles' => []];
        }

        $permission_instance = KCProPermissionSetting::getInstance();
        $default_permissions = $permission_instance->buildDefaultPermissions();
        $saved_settings = $permission_instance->getPermissionSetting($request)->get_data()['data'] ?? [];

        // Build module labels mapping (minimal, only for module names)
        $module_labels = $this->generateLabel(['appointment', 'billing', 'clinic', 'clinical_detail', 'custom_field', 'dashboard', 'doctor', 'encounter', 'encounters_template', 'holiday', 'other', 'patient', 'patient_report', 'patient_review', 'prescription', 'receptionist', 'service', 'session', 'static_data'], true);

        $roles = [];
        $prefix = KIVI_CARE_PREFIX;

        foreach ($default_permissions as $role_key => $role_data) {
            $role_capabilities = $saved_settings[$role_key]['capabilities'] ?? $role_data['capabilities'] ?? [];

            $modules = [];
            foreach ($role_capabilities as $module_key => $permissions) {
                if (!is_array($permissions)) {
                    continue;
                }

                $module_permissions = [];
                foreach ($permissions as $perm_key => $is_enabled) {
                    $is_active = (bool) $is_enabled;
                    $permission_label = $this->generateLabel($perm_key);
                    $module_permissions[] = [
                        'key' => $perm_key,
                        'label' => $permission_label,
                        'status' => $is_active ? 'active' : 'inactive',
                        'enabled' => $is_active
                    ];
                }

                if (!empty($module_permissions)) {
                    $module_label = $module_labels[$module_key] ?? $this->generateLabel($module_key, true);
                    $modules[] = [
                        'key' => $module_key,
                        'label' => $module_label,
                        'permissions' => $module_permissions
                    ];
                }
            }

            $roles[] = [
                'key' => $role_key,
                'name' => $role_data['name'] ?? ucwords(str_replace('_', ' ', $role_key)),
                'modules' => $modules
            ];
        }

        return [
            'roles' => $roles
        ];
    }

    /**
     * Generate label(s) from key(s)
     *
     * @param string|array $key
     * @param bool $isModule
     * @return string|array
     */
    private function generateLabel($key, bool $isModule = false)
    {
        if (is_array($key)) {
            $labels = [];
            foreach ($key as $k) {
                $full_key = $isModule ? $k . '_module' : $k;
                $labels[$full_key] = $this->generateLabel($full_key, $isModule);
            }
            return $labels;
        }

        if ($isModule) {
            // Remove _module suffix if present
            $key = str_replace('_module', '', $key);
        }
        // Convert snake_case to Title Case
        $label = ucwords(str_replace('_', ' ', $key));
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
        return __($label, 'kivicare-clinic-management-system');
    }

    /**
     * Check if user has permission to access config endpoints
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkPermission($request): bool
    {
        return (bool) $this->kcbase->getLoginUserRole();
    }

    /**
     * Check if user has permission to update configuration
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkUpdatePermission($request): bool
    {
        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Check configuration update permission
        return $this->checkResourceAccess('config', 'edit');
    }

    /**
     * Check if user has admin permission
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkAdminPermission($request): bool
    {
        return current_user_can('manage_options');
    }
}
