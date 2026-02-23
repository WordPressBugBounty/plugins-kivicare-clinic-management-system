<?php

namespace App\controllers\api\SettingsController;

use App\controllers\api\SettingsController;
use App\models\KCClinic;
use App\models\KCOption;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use App\baseClasses\KCBase;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class General
 * 
 * @package App\controllers\api\SettingsController
 */
class General extends SettingsController
{
    private static $get_instance = null;

    protected $route = 'settings/general';

    public function __construct()
    {
        $this->currentUserRole = KCBase::get_instance()->getLoginUserRole();
        parent::__construct();
    }

    /**
     * Registers the general settings routes.
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        $base_route = '/' . $this->route;
        $this->registerRoute($base_route, [
            'methods' => 'GET',
            'callback' => [$this, 'getGeneral'],
            'permission_callback' => [$this, 'checkViewPermission'],
            'args' => $this->getSettingsEndpointArgs(),
        ]);

        $this->registerRoute($base_route, [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'updateGeneral'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getSettingFieldSchema(),
        ]);
    }

    /**
     * Check if user has permission to access settings endpoints
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkViewPermission(): bool
    {
        if ($this->currentUserRole !== 'administrator') {
            return false;
        }

        return true;
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

        if ($this->currentUserRole !== 'administrator') {
            return false;
        }

        return $this->checkResourceAccess('settings', 'edit');
    }

    public function getSettingFieldSchema()
    {
        return [
            'countryCode' => [
                'description' => 'Country Code',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'kcSanitizeData',
                'validate_callback' => function ($value) {
                    return is_string($value);
                },
            ],
            'countryDialCode' => [
                'description' => 'International country dial code (e.g., 971 for UAE)',
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'kcSanitizeData',
                'validate_callback' => fn($value) =>
                    is_numeric($value) && intval($value) > 0,
            ],
            'currencyPostfix' => [
                'description' => 'Currency Postfix',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'kcSanitizeData',
                'validate_callback' => function ($value) {
                    return is_string($value);
                },
            ],
            'currencyPrefix' => [
                'description' => 'Currency Prefix',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'kcSanitizeData',
                'validate_callback' => function ($value) {
                    return is_string($value);
                },
            ],
            'enableRecaptcha' => [
                'description' => 'Enable reCAPTCHA',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'kcSanitizeData',
                'validate_callback' => function ($value) {
                    return in_array($value, ['on', 'off']);
                },
            ],
            'recaptchaSecretKey' => [
                'description' => 'Recaptcha Secret Key',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'kcSanitizeData',
                'validate_callback' => function ($value) {
                    return is_string($value);
                },
            ],
            'recaptchaSiteKey' => [
                'description' => 'Recaptcha Site Key',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'kcSanitizeData',
                'validate_callback' => function ($value) {
                    return is_string($value);
                },
            ],
            'hideLanguageSwitcher' => [
                'description' => 'Hide Header Language Switcher',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'kcSanitizeData',
                'validate_callback' => function ($value) {
                    return in_array($value, ['on', 'off']);
                },
            ],
            'loginRedirects' => [
                'description' => 'Login Redirect URLs per role',
                'type' => 'object',
                'required' => false,
                'sanitize_callback' => function ($value) {
                    // Sanitize and filter login redirects
                    if (!is_array($value)) {
                        return [];
                    }

                    // Get allowed roles from KCBase
                    $allowed_roles = KCBase::get_instance()->KCGetRoles();

                    $sanitized = [];
                    foreach ($value as $role => $url) {
                        // Only process allowed roles (exclude extra data)
                        if (in_array($role, $allowed_roles, true)) {
                            // Convert null to empty string to avoid deprecation warnings in PHP 8.1+
                            $url = $url ?? '';
                            // Sanitize URL - esc_url_raw removes invalid characters and validates URL format
                            $sanitized_url = esc_url_raw($url);
                            // Only add if URL is valid after sanitization
                            if (!empty($sanitized_url)) {
                                $sanitized[$role] = $sanitized_url;
                            }
                        }
                    }

                    return $sanitized;
                },
                'validate_callback' => function ($value) {
                    // Validate structure
                    if (!is_array($value)) {
                        return new WP_Error(
                            'invalid_type',
                            __('Login redirects must be an object/array.', 'kivicare-clinic-management-system')
                        );
                    }

                    // Get allowed roles from KCBase
                    $allowed_roles = KCBase::get_instance()->KCGetRoles();

                    // Validate each entry
                    foreach ($value as $role => $url) {
                        // Check if role is valid
                        if (!in_array($role, $allowed_roles, true)) {
                            return new WP_Error(
                                'invalid_role',
                                sprintf(
                                    /* translators: 1: Invalid role name, 2: List of allowed roles */
                                    __('Invalid role "%1$s". Allowed roles are: %2$s', 'kivicare-clinic-management-system'),
                                    $role,
                                    implode(', ', $allowed_roles)
                                )
                            );
                        }

                        // Check if URL is a string or null
                        if (!is_string($url) && !is_null($url)) {
                            return new WP_Error(
                                'invalid_url_type',
                                sprintf(
                                    /* translators: %s: role name */
                                    __('URL for role "%s" must be a string.', 'kivicare-clinic-management-system'),
                                    $role
                                )
                            );
                        }

                        // Validate URL format
                        if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
                            return new WP_Error(
                                'invalid_url_format',
                                sprintf(
                                    /* translators: %s: role name */
                                    __('Invalid URL format for role "%s".', 'kivicare-clinic-management-system'),
                                    $role
                                )
                            );
                        }
                    }

                    return true;
                },
            ],
            'logoutRedirects' => [
                'description' => 'Logout Redirect URLs per role',
                'type' => 'object',
                'required' => false,
                'sanitize_callback' => function ($value) {
                    // Sanitize and filter logout redirects
                    if (!is_array($value)) {
                        return [];
                    }

                    // Get allowed roles from KCBase
                    $allowed_roles = KCBase::get_instance()->KCGetRoles();

                    $sanitized = [];
                    foreach ($value as $role => $url) {
                        // Only process allowed roles (exclude extra data)
                        if (in_array($role, $allowed_roles, true)) {
                            // Convert null to empty string to avoid deprecation warnings in PHP 8.1+
                            $url = $url ?? '';
                            // Sanitize URL - esc_url_raw removes invalid characters and validates URL format
                            $sanitized_url = esc_url_raw($url);
                            // Only add if URL is valid after sanitization
                            if (!empty($sanitized_url)) {
                                $sanitized[$role] = $sanitized_url;
                            }
                        }
                    }

                    return $sanitized;
                },
                'validate_callback' => function ($value) {
                    // Validate structure
                    if (!is_array($value)) {
                        return new WP_Error(
                            'invalid_type',
                            __('Logout redirects must be an object/array.', 'kivicare-clinic-management-system')
                        );
                    }

                    // Get allowed roles from KCBase
                    $allowed_roles = KCBase::get_instance()->KCGetRoles();

                    // Validate each entry
                    foreach ($value as $role => $url) {
                        // Check if role is valid
                        if (!in_array($role, $allowed_roles, true)) {
                            return new WP_Error(
                                'invalid_role',
                                sprintf(
                                    /* translators: 1: Invalid role name, 2: List of allowed roles */
                                    __('Invalid role "%1$s". Allowed roles are: %2$s', 'kivicare-clinic-management-system'),
                                    $role,
                                    implode(', ', $allowed_roles)
                                )
                            );
                        }

                        // Check if URL is a string or null
                        if (!is_string($url) && !is_null($url)) {
                            return new WP_Error(
                                'invalid_url_type',
                                sprintf(
                                    /* translators: %s: role name */
                                    __('URL for role "%s" must be a string.', 'kivicare-clinic-management-system'),
                                    $role
                                )
                            );
                        }

                        // Validate URL format
                        if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
                            return new WP_Error(
                                'invalid_url_format',
                                /* translators: %s: role name */
                                sprintf(__('Invalid URL format for role "%s".', 'kivicare-clinic-management-system'), $role)
                            );
                        }
                    }

                    return true;
                },
            ],
            'role' => [
                'description' => 'Role permissions',
                'type' => 'object',
                'required' => false,
                'sanitize_callback' => 'kcSanitizeData',
                'validate_callback' => function ($value) {
                    return is_array($value);
                },
            ],
            'status' => [
                'description' => 'Status of user types',
                'type' => 'object',
                'required' => false,
                'sanitize_callback' => 'kcSanitizeData',
                'validate_callback' => function ($value) {
                    return is_array($value);
                },
            ]
        ];
    }



    /**
     * Get all settings
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getGeneral(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $option_keys = [
                'hideUtilityLinks' => 'request_helper_status',
                'countryCode' => 'country_code',
                'countryDialCode' => 'country_calling_code',
                'status' => 'user_registration_shortcode_setting',
                'role' => 'user_registration_shortcode_role_setting',
                'showOtherGender' => 'user_registration_form_setting',
                'loginRedirects' => 'login_redirect',
                'logoutRedirects' => 'logout_redirect',
                'allowEncounterEdit' => 'encounter_edit_after_close_status',
                'enableRecaptcha' => 'google_recaptcha',
                'hideLanguageSwitcher' => 'hide_language_switcher_status'
            ];

            // Get all options in one query
            $options = KCOption::getMultiple(array_values($option_keys));
            $response = [];
            foreach ($option_keys as $key => $wp_option_name) {
                $response[$key] = $options[$wp_option_name] ?? null;
            }

            // Set default values for missing settings
            $response['hideUtilityLinks'] = $response['hideUtilityLinks'] ?? 'off';
            $response['hideLanguageSwitcher'] = $response['hideLanguageSwitcher'] ?? 'off';
            $response['countryCode'] = $response['countryCode'] ?? 'us';
            $response['countryDialCode'] = $response['countryDialCode'] ?? '+44';
            $response['status'] = $response['status'] ?? [
                'doctor' => 'off',
                'receptionist' => 'off',
                'patient' => 'on'
            ];
            $response['role'] = $response['role'] ?? [
                'kiviCare_doctor' => 'on',
                'kiviCare_receptionist' => 'on',
                'kiviCare_patient' => 'on'
            ];
            $response['showOtherGender'] = $response['showOtherGender'] ?? 'on';
            $response['allowEncounterEdit'] = $response['allowEncounterEdit'] ?? 'off';

            // Sanitize login and logout redirects - only include valid KiviCare roles
            $allowed_roles = KCBase::get_instance()->KCGetRoles();
            $default_logout_url = wp_login_url();
            $dashboard_handler = \App\admin\KCDashboardPermalinkHandler::instance();

            // Initialize redirects with default URLs for all allowed roles if not set
            if (!isset($response['loginRedirects']) || !is_array($response['loginRedirects'])) {
                $response['loginRedirects'] = [];
            }
            if (!isset($response['logoutRedirects']) || !is_array($response['logoutRedirects'])) {
                $response['logoutRedirects'] = [];
            }

            // Ensure all roles exist in redirects with role-specific dashboard URLs
            foreach ($allowed_roles as $role) {
                if (!isset($response['loginRedirects'][$role])) {
                    $response['loginRedirects'][$role] = $dashboard_handler->get_dashboard_url($role);
                }
                if (!isset($response['logoutRedirects'][$role])) {
                    $response['logoutRedirects'][$role] = $default_logout_url;
                }
            }

            // Add administrator role
            if (!isset($response['loginRedirects']['administrator'])) {
                $response['loginRedirects']['administrator'] = $dashboard_handler->get_dashboard_url('administrator');
            }
            if (!isset($response['logoutRedirects']['administrator'])) {
                $response['logoutRedirects']['administrator'] = $default_logout_url;
            }

            $sanitized_login_redirects = [];
            foreach ($response['loginRedirects'] as $role => $url) {
                if (in_array($role, $allowed_roles, true)) {
                    $sanitized_login_redirects[$role] = !empty($url) ? esc_url_raw($url) : ($dashboard_handler->get_dashboard_url($role) ?? home_url());
                }
            }
            $response['loginRedirects'] = $sanitized_login_redirects;

            $sanitized_logout_redirects = [];
            foreach ($response['logoutRedirects'] as $role => $url) {
                if (in_array($role, $allowed_roles, true)) {
                    $sanitized_logout_redirects[$role] = !empty($url) ? esc_url_raw($url) : $default_logout_url;
                }
            }
            $response['logoutRedirects'] = $sanitized_logout_redirects;

            // Handle reCAPTCHA settings
            $recaptcha_settings = $options['google_recaptcha'] ?? ['status' => 'off', 'site_key' => '', 'secret_key' => ''];
            if (is_array($recaptcha_settings)) {
                $response['enableRecaptcha'] = $recaptcha_settings['status'] ?? 'off';
                $response['recaptchaSecretKey'] = $recaptcha_settings['secret_key'] ?? '';
                $response['recaptchaSiteKey'] = $recaptcha_settings['site_key'] ?? '';
            } else {
                $response['enableRecaptcha'] = 'off';
                $response['recaptchaSecretKey'] = '';
                $response['recaptchaSiteKey'] = '';
            }

            // Get currency data using ORM
            $clinic = KCClinic::getDefaultClinic();
            if ($clinic && !empty($clinic->extra)) {
                $currency_data = json_decode($clinic->extra, true);
                $response['currencyPostfix'] = $currency_data['currency_postfix'] ?? '';
                $response['currencyPrefix'] = $currency_data['currency_prefix'] ?? '';
            } else {
                $response['currencyPostfix'] = '';
                $response['currencyPrefix'] = '';
            }

            return $this->response($response, __('Settings retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve settings', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Update settings (bulk update)
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function updateGeneral(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $settings = $request->get_params();
            $settings = kcSanitizeData($settings);
            $updated = [];
            $errors = [];

            foreach ($settings as $key => $value) {
                if ($this->validateSettingKey($key) !== true && $key !== 'hideLanguageSwitcher') {
                    $errors[$key] = __('Invalid setting key', 'kivicare-clinic-management-system');
                    continue;
                }
            }

            $responseData = $this->updateSettingsSave($settings, $updated, $errors);
            $response = [
                'updated' => $responseData['updated'],
                'errors' => $responseData['errors'],
                'total_updated' => count($responseData['updated']),
                'total_errors' => count($responseData['errors'])
            ];

            if (empty($errors)) {
                return $this->response($response, __('Settings updated successfully', 'kivicare-clinic-management-system'));
            } else {
                return $this->response($response, __('Settings updated with some errors', 'kivicare-clinic-management-system'), true, 207);
            }
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update settings', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Save updated settings
     */
    public function updateSettingsSave($settings, $updated = [], $errors = [])
    {
        // Update simple options
        $this->updateOption('request_helper_status', strval($settings['hideUtilityLinks'] ?? 'off'), $updated, $errors);
        $this->updateOption('hide_language_switcher_status', strval($settings['hideLanguageSwitcher'] ?? 'off'), $updated, $errors);
        $this->updateOption('country_code', $settings['countryCode'] ?? 'us', $updated, $errors);
        $this->updateOption('country_calling_code', $settings['countryDialCode'] ?? '+1', $updated, $errors);
        $this->updateOption('user_registration_shortcode_setting', $settings['status'] ?? ['doctor' => 'off', 'receptionist' => 'off', 'patient' => 'on'], $updated, $errors);
        $this->updateOption('user_registration_shortcode_role_setting', $settings['role'] ?? ['kiviCare_doctor' => 'on', 'kiviCare_receptionist' => 'on', 'kiviCare_patient' => 'on'], $updated, $errors);
        $this->updateOption('user_registration_form_setting', $settings['showOtherGender'] ?? 'off', $updated, $errors);
        $this->updateOption('login_redirect', $settings['loginRedirects'] ?? [], $updated, $errors);
        $this->updateOption('logout_redirect', $settings['logoutRedirects'] ?? [], $updated, $errors);
        $this->updateOption('encounter_edit_after_close_status', $settings['allowEncounterEdit'] ?? 'off', $updated, $errors);

        // Update reCAPTCHA settings
        $recaptchaSettings = [
            'site_key' => $settings['recaptchaSiteKey'] ?? '',
            'secret_key' => $settings['recaptchaSecretKey'] ?? '',
            'status' => $settings['enableRecaptcha'] ?? 'off'
        ];
        $this->updateOption('google_recaptcha', $recaptchaSettings, $updated, $errors);

        // Update currency settings
        if (!empty($settings['currencyPostfix']) || !empty($settings['currencyPrefix'])) {
            $this->updateOption('clinic_currency', 'on', $updated, $errors);

            $currency = [
                'currency_prefix' => $settings['currencyPrefix'] ?? '',
                'currency_postfix' => $settings['currencyPostfix'] ?? '',
            ];

            $clinic = KCClinic::getDefaultClinic();
            if ($clinic) {
                $clinic->extra = json_encode($currency, JSON_UNESCAPED_UNICODE);
                $result = $clinic->save();
                if ($result !== false && !is_wp_error($result)) {
                    $updated['kc_clinics_currency'] = 'Updated';
                } else {
                    $errors['kc_clinics_currency'] = 'Failed';
                }
            } else {
                $errors['kc_clinics_currency'] = 'No clinic found';
            }
        } else {
            $this->updateOption('clinic_currency', 'off', $updated, $errors);
        }

        // Validate role settings
        if (
            isset($settings['role']) && is_array($settings['role']) &&
            ($settings['role']['kiviCare_patient'] ?? 'off') === 'off' &&
            ($settings['role']['kiviCare_doctor'] ?? 'off') === 'off' &&
            ($settings['role']['kiviCare_receptionist'] ?? 'off') === 'off'
        ) {
            $errors['roles'] = __('At least one user role should be enabled', 'kivicare-clinic-management-system');
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    /**
     * Helper method to update options using KCOption model
     */
    private function updateOption($key, $value, &$updated, &$errors)
    {
        try {
            $result = KCOption::set($key, $value);
            if ($result) {
                $updated[$key] = 'Updated';
            } else {
                $errors[$key] = 'Failed to update';
            }
        } catch (\Exception $e) {
            $errors[$key] = $e->getMessage();
        }
    }

    /**
     * Validate setting key
     */
    public function validateSettingKey($key): bool
    {
        $validKeys = [
            'hideUtilityLinks',
            'countryCode',
            'countryDialCode',
            'status',
            'role',
            'showOtherGender',
            'loginRedirects',
            'logoutRedirects',
            'allowEncounterEdit',
            'currencyPostfix',
            'currencyPrefix',
            'enableRecaptcha',
            'recaptchaSecretKey',
            'recaptchaSiteKey',
            'hideLanguageSwitcher'
        ];
        return in_array($key, $validKeys);
    }
}
