<?php
namespace App\controllers\api;

use App\admin\KCDashboardPermalinkHandler;
use App\baseClasses\KCBase;
use App\baseClasses\KCBaseController;
use App\baseClasses\KCErrorLogger;
use App\emails\KCEmailSender;
use App\models\KCPatient;
use App\models\KCDoctor;
use App\models\KCReceptionist;
use App\models\KCClinic;
use App\models\KCOption;
use App\models\KCPatientClinicMapping;
use App\models\KCDoctorClinicMapping;
use App\models\KCReceptionistClinicMapping;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Authentication Controller for handling login and registration
 */
class AuthController extends KCBaseController
{
    /**
     * @var string The base route for this controller
     */
    protected $route = 'auth';


    /**
     * Get common arguments used across multiple endpoints
     *
     * @return array
     */
    private function getCommonArgs()
    {
        return [
            'username' => [
                'description' => 'Username for authentication',
                'type' => 'string',
                'required' => true,
                'validate_callback' => [$this, 'validateUsername'],
                'sanitize_callback' => 'sanitize_user',
            ],
            'password' => [
                'description' => 'Password for authentication',
                'type' => 'string',
                'required' => true,
                'validate_callback' => [$this, 'validatePassword'],
            ],
            'email' => [
                'description' => 'Email address',
                'type' => 'string',
                'required' => true,
                'validate_callback' => [$this, 'validateEmail'],
                'sanitize_callback' => 'sanitize_email',
            ],
            'recaptchaToken' => [
                'description' => 'Google reCAPTCHA response token',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ]
        ];
    }

    /**
     * Validate username format
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateUsername($param)
    {
        if (empty($param)) {
            return new WP_Error('invalid_username', __('Username is required', 'kivicare-clinic-management-system'));
        }

        if (strlen($param) < 3) {
            return new WP_Error('invalid_username', __('Username must be at least 3 characters long', 'kivicare-clinic-management-system'));
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $param)) {
            return new WP_Error('invalid_username', __('Username can only contain letters, numbers, dots, dashes and underscores', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Validate username or email format for login
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateUsernameOrEmail($param)
    {
        if (empty($param)) {
            return new WP_Error('invalid_login', __('Username or email is required', 'kivicare-clinic-management-system'));
        }

        // If it contains @ symbol, validate as email
        if (strpos($param, '@') !== false) {
            if (!is_email($param)) {
                return new WP_Error('invalid_email', __('Please enter a valid email address', 'kivicare-clinic-management-system'));
            }
        } else {
            // Validate as username
            if (strlen($param) < 3) {
                return new WP_Error('invalid_username', __('Username must be at least 3 characters long', 'kivicare-clinic-management-system'));
            }

            if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $param)) {
                return new WP_Error('invalid_username', __('Username can only contain letters, numbers, dots, dashes and underscores', 'kivicare-clinic-management-system'));
            }
        }

        return true;
    }

    /**
     * Validate password strength
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validatePassword($param)
    {
        if (empty($param)) {
            return new WP_Error('invalid_password', __('Password is required', 'kivicare-clinic-management-system'));
        }
        if (strlen($param) < 4) {
            return new WP_Error('invalid_password', __('Password must be at least 4 characters long', 'kivicare-clinic-management-system'));
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

    private function validateRecaptcha($token)
    {
        // Get reCAPTCHA settings
        $recaptcha_settings = KCOption::get('google_recaptcha') ?? ['status' => 'off', 'secret_key' => ''];

        KCErrorLogger::instance()->error("reCAPTCHA Debug - Settings Status: " . $recaptcha_settings['status']);
        KCErrorLogger::instance()->error("reCAPTCHA Debug - Secret Key exists: " . (!empty($recaptcha_settings['secret_key']) ? 'Yes' : 'No'));
        KCErrorLogger::instance()->error("reCAPTCHA Debug - Token received: " . (!empty($token) ? 'Yes' : 'No'));

        // If reCAPTCHA is disabled, skip verification
        if ($recaptcha_settings['status'] !== 'on') {
            KCErrorLogger::instance()->error("reCAPTCHA Debug - Skipped (disabled)");
            return true;
        }

        // If enabled but no token provided
        if (empty($token)) {
            KCErrorLogger::instance()->error("reCAPTCHA Debug - Failed (no token)");
            return new WP_Error('recaptcha_required', __('reCAPTCHA verification is required', 'kivicare-clinic-management-system'));
        }

        // If enabled but no secret key
        if (empty($recaptcha_settings['secret_key'])) {
            KCErrorLogger::instance()->error("reCAPTCHA Debug - Failed (no secret key)");
            return new WP_Error('recaptcha_misconfigured', __('reCAPTCHA is enabled but not properly configured', 'kivicare-clinic-management-system'));
        }

        // Verify with Google
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $recaptcha_settings['secret_key'],
                'response' => $token,
                'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            KCErrorLogger::instance()->error("reCAPTCHA Debug - API Error: " . $response->get_error_message());
            return new WP_Error('recaptcha_error', __('reCAPTCHA verification failed', 'kivicare-clinic-management-system'));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        KCErrorLogger::instance()->error("reCAPTCHA Debug - Google Response: " . wp_json_encode( $body ));

        if (!$body['success']) {
            $error_codes = $body['error-codes'] ?? ['unknown-error'];
            KCErrorLogger::instance()->error("reCAPTCHA Debug - Failed: " . implode(', ', $error_codes));
            return new WP_Error('invalid_recaptcha', __('Invalid reCAPTCHA response', 'kivicare-clinic-management-system'));
        }

        // Optional: Check score for reCAPTCHA v3
        if (isset($body['score']) && $body['score'] < 0.5) {
            KCErrorLogger::instance()->error("reCAPTCHA Debug - Score too low: " . $body['score']);
            return new WP_Error('recaptcha_low_score', __('reCAPTCHA verification failed', 'kivicare-clinic-management-system'));
        }

        KCErrorLogger::instance()->error("reCAPTCHA Debug - Success");
        return true;
    }

    public function registerRoutes()
    {
        // Login endpoint
        $this->registerRoute('/' . $this->route . '/login', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true',
            'args' => $this->getLoginEndpointArgs()
        ]);

        // Register endpoint
        $this->registerRoute('/' . $this->route . '/register', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'register'],
            'permission_callback' => [$this, 'checkRegistrationPermission'],
            'args' => $this->getRegisterEndpointArgs()
        ]);

        // Logout endpoint
        $this->registerRoute('/' . $this->route . '/logout', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'logout'],
            'permission_callback' => [$this, 'checkLogoutPermission'],
            'args' => []
        ]);

        // Forgot password endpoint
        $this->registerRoute('/' . $this->route . '/forgot-password', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'forgotPassword'],
            'permission_callback' => '__return_true',
            'args' => $this->getForgotPasswordEndpointArgs()
        ]);

        // Reset password endpoint
        $this->registerRoute('/' . $this->route . '/reset-password', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'resetPassword'],
            'permission_callback' => '__return_true',
            'args' => $this->getResetPasswordEndpointArgs()
        ]);


        // Change password endpoint
        register_rest_route($this->namespace, '/auth/change-password', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'changePassword'],
            'permission_callback' => 'is_user_logged_in',
            'args' => $this->getChangePasswordEndpointArgs()
        ]);

        // Delete account endpoint (delete logged-in user)
        $this->registerRoute('/' . $this->route . '/delete-account', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'deleteCurrentUserAccount'],
            'permission_callback' => 'is_user_logged_in',
            'args' => []
        ]);

        // Patient social login endpoint
        $this->registerRoute('/' . $this->route . '/patient/social-login', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'patientSocialLogin'],
            'permission_callback' => '__return_true',
            'args' => $this->getSocialLoginEndpointArgs()
        ]);
    }

    private function getLoginEndpointArgs()
    {
        return [
            'username' => [
                'description' => 'Username or email address for authentication',
                'type' => 'string',
                'required' => true,
                'validate_callback' => [$this, 'validateUsernameOrEmail'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'password' => $this->getCommonArgs()['password'],
            'remember' => [
                'description' => 'Remember user login',
                'type' => 'boolean',
                'default' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'recaptchaToken' => $this->getCommonArgs()['recaptchaToken'],
        ];
    }

    /**
     * Get arguments for the register endpoint
     *
     * @return array
     */
    private function getRegisterEndpointArgs()
    {
        $args = [
            'username' => $this->getCommonArgs()['username'],
            'email' => $this->getCommonArgs()['email'],
            'password' => $this->getCommonArgs()['password'],
            'first_name' => [
                'description' => 'User first name',
                'type' => 'string',
                'required' => false,
                'validate_callback' => function ($param) {
                    // Allow empty for basic registration
                    if (empty($param)) {
                        return true;
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'last_name' => [
                'description' => 'User last name',
                'type' => 'string',
                'required' => false,
                'validate_callback' => function ($param) {
                    // Allow empty for basic registration
                    if (empty($param)) {
                        return true;
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'mobile_number' => [
                'description' => 'User mobile number',
                'type' => 'string',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return new WP_Error('invalid_mobile_number', __('Mobile number is required', 'kivicare-clinic-management-system'));
                    }
                    if (!preg_match('/^[\d\s\-\+\(\)]+$/', $param)) {
                        return new WP_Error('invalid_contact', __('Please enter a valid contact number', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'gender' => [
                'description' => 'User gender',
                'type' => 'string',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return new WP_Error('invalid_gender', __('Gender is required', 'kivicare-clinic-management-system'));
                    }
                    if (!in_array($param, ['male', 'female', 'other'])) {
                        return new WP_Error('invalid_gender', __('Invalid gender value', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'user_role' => [
                'description' => 'User role',
                'type' => 'string',
                'required' => false, // Make it optional since it can be determined from patient_role_only
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return true; // Allow empty, will be set to default
                    }
                    if (!in_array($param, [$this->kcbase->getReceptionistRole(), $this->kcbase->getDoctorRole(), $this->kcbase->getPatientRole()])) {
                        return new WP_Error('invalid_user_role', __('Invalid user role', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'user_clinic' => [
                'description' => 'Clinic ID',
                'type' => 'integer',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return new WP_Error('invalid_clinic', __('Clinic is required', 'kivicare-clinic-management-system'));
                    }
                    $clinic = KCClinic::find($param);
                    if (!$clinic) {
                        return new WP_Error('invalid_clinic', __('Invalid clinic ID', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
            'country_code' => [
                'description' => 'Country code for mobile number',
                'type' => 'string',
                'required' => false,
                'validate_callback' => function ($param) {
                    // Allow empty for basic registration
                    if (empty($param)) {
                        return true;
                    }
                    if (!preg_match('/^\+\d{1,4}$/', $param)) {
                        return new WP_Error('invalid_country_code', __('Invalid country code format', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'recaptchaToken' => $this->getCommonArgs()['recaptchaToken'],
            'patient_role_only' => [
                'description' => 'Register as patient only',
                'type' => 'string',
                'default' => 'yes',
                'validate_callback' => function ($param) {
                    return in_array($param, ['yes', 'no']);
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'dob' => [
                'description' => 'Date of birth (YYYY-MM-DD)',
                'type' => 'string',
                'required' => false,
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return true;
                    }
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $param)) {
                        return new WP_Error('invalid_date', __('Date of birth must be in YYYY-MM-DD format', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'blood_group' => [
                'description' => 'User blood group',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'address' => [
                'description' => 'User address',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ]
        ];

        return $args;
    }

    /**
     * Get arguments for the social login endpoint
     *
     * @return array
     */
    private function getSocialLoginEndpointArgs()
    {
        return [
            'login_type' => [
                'description' => 'Social login provider type (google, apple)',
                'type' => 'string',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return new WP_Error('invalid_login_type', __('Login type is required', 'kivicare-clinic-management-system'));
                    }
                    $allowed_types = ['google', 'apple'];
                    if (!in_array(strtolower($param), $allowed_types)) {
                        /* translators: %s: List of allowed login types */
                        return new WP_Error('invalid_login_type', sprintf(__('Invalid login type. Allowed types: %s', 'kivicare-clinic-management-system'), implode(', ', $allowed_types)));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'email' => [
                'description' => 'Email address',
                'type' => 'string',
                'required' => false,
                'validate_callback' => function ($param, $request) {
                    // Email is optional if contact_number is provided
                    $contact_number = $request->get_param('contact_number');
                    if (empty($param) && empty($contact_number)) {
                        return new WP_Error('invalid_email', __('Email or contact number is required', 'kivicare-clinic-management-system'));
                    }
                    if (!empty($param) && !is_email($param)) {
                        return new WP_Error('invalid_email', __('Please enter a valid email address', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_email',
            ],
            'contact_number' => [
                'description' => 'Contact number',
                'type' => 'string',
                'required' => false,
                'validate_callback' => function ($param, $request) {
                    // Contact number is optional if email is provided
                    $email = $request->get_param('email');
                    if (empty($param) && empty($email)) {
                        return new WP_Error('invalid_contact_number', __('Email or contact number is required', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'password' => [
                'description' => 'Access token from social provider (used as password)',
                'type' => 'string',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return new WP_Error('invalid_password', __('Password/access token is required', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
            ],
            'first_name' => [
                'description' => 'First name',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'last_name' => [
                'description' => 'Last name',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'username' => [
                'description' => 'Username (optional, will be generated from email if not provided)',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_user',
            ]
        ];
    }

    /**
     * Get arguments for the forgot password endpoint
     *
     * @return array
     */
    private function getForgotPasswordEndpointArgs()
    {
        return [
            'user_login' => [
                'description' => 'Username or email address',
                'type' => 'string',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return new WP_Error('invalid_user_login', __('Username or email is required', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'recaptchaToken' => $this->getCommonArgs()['recaptchaToken']
        ];
    }

    /**
     * Get arguments for the reset password endpoint
     *
     * @return array
     */
    private function getResetPasswordEndpointArgs()
    {
        return [
            'key' => [
                'description' => 'Password reset key',
                'type' => 'string',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return new WP_Error('invalid_key', __('Reset key is required', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'login' => [
                'description' => 'Username',
                'type' => 'string',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return new WP_Error('invalid_login', __('Username is required', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_user',
            ],
            'password' => array_merge($this->getCommonArgs()['password'], ['required' => true])
        ];
    }

    /**
     * Get arguments for the change password endpoint
     *
     * @return array
     */
    private function getChangePasswordEndpointArgs()
    {
        return [
            'current_password' => [
                'description' => 'Current password',
                'type' => 'string',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return new WP_Error('invalid_current_password', __('Current password is required', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
            ],
            'new_password' => [
                'description' => 'New password',
                'type' => 'string',
                'required' => true,
                'validate_callback' => [$this, 'validatePassword'],
            ],
            'confirm_password' => [
                'description' => 'Confirm new password',
                'type' => 'string',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return new WP_Error('invalid_confirm_password', __('Confirm password is required', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
            ]
        ];
    }

    /**
     * Check if user has permission to register
     * 
     * @param WP_REST_Request $request
     * @return bool
     */
    public function checkRegistrationPermission($request)
    {
        KCErrorLogger::instance()->error("AuthController: checkRegistrationPermission called");

        // Allow registration if WordPress registration is enabled OR if KiviCare registration is specifically enabled
        if (get_option('users_can_register')) {
            KCErrorLogger::instance()->error("AuthController: WordPress registration enabled");
            return true;
        }

        // Determine user role
        $user_role = $request->get_param('user_role') ?? $this->kcbase->getPatientRole();
        KCErrorLogger::instance()->error("Requested user role: " . $user_role);

        // Validate if the selected role is enabled in settings
        $role_settings = KCOption::get('user_registration_shortcode_role_setting') ?? [];
        if (isset($role_settings[$user_role]) && $role_settings[$user_role] !== 'on') {
            return new WP_Error(
                'role_not_allowed',
                __('Selected user role is not enabled for registration', 'kivicare-clinic-management-system'),
                ['status' => 400]
            );
        }

        // Default to allowing registration for KiviCare (since this is a medical system)
        KCErrorLogger::instance()->error("AuthController: Allowing registration by default");
        return true;
    }

    public function checkLogoutPermission($request)
    {
        return is_user_logged_in();
    }

    /**
     * Logout endpoint handler
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function logout(WP_REST_Request $request): WP_REST_Response
    {

        $role = KCBase::get_instance()->getLoginUserRole();

        $logout_redirects = KCOption::get('logout_redirect', []);

        // Initialize redirect_url with empty string
        $redirect_url = '';

        // Check if a custom redirect is set for this role using the short key
        if (!empty($logout_redirects[$role])) {
            $redirect_url = apply_filters('kc_logout_redirect_url', $logout_redirects[$role], $role);
        }

        wp_logout();
        return $this->response(
            $redirect_url,
            __('Logout successful', 'kivicare-clinic-management-system'),
            true,
            200
        );
    }

    /**
     * Login endpoint handler
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function login(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();
        $containerId = $params['containerId'] ?? null;
        KCErrorLogger::instance()->error("Login - Container ID: " . ($containerId ?? 'None'));
        // Validate reCAPTCHA first
        if (isset($params['recaptchaToken'])) {
            KCErrorLogger::instance()->error("Login - reCAPTCHA Token received");
            $recaptcha_result = $this->validateRecaptcha($params['recaptchaToken']);
            if (is_wp_error($recaptcha_result)) {
                KCErrorLogger::instance()->error("Login - reCAPTCHA Failed: " . $recaptcha_result->get_error_message());
                return $this->response(
                    null,
                    $recaptcha_result->get_error_message(),
                    false,
                    400
                );
            }
        } else {
            KCErrorLogger::instance()->error("Login - No reCAPTCHA Token");
        }

        $username_or_email = $params['username'];
        $user_login = $username_or_email;

        // If it contains @ symbol, it's an email - get the username
        if (strpos($username_or_email, '@') !== false) {
            $user = get_user_by('email', $username_or_email);
            if ($user) {
                $user_login = $user->user_login;
            }
        }

        $creds = [
            'user_login' => $user_login,
            'user_password' => $params['password'],
            'remember' => $params['remember'] ?? false
        ];

        $user = wp_signon($creds, false);

        if (is_wp_error($user)) {
            return $this->response(
                null,
                $user->get_error_message(),
                false,
                401
            );
        }

        // Check if container ID is from KiviCare booking widget
        if (!empty($containerId)) {
            KCErrorLogger::instance()->error("Login - KiviCare booking widget detected, checking patient role");

            // Only allow kivicare patient role for booking widget
            $patient_role = $this->kcbase->getPatientRole();
            if (!in_array($patient_role, $user->roles)) {
                wp_logout();
                KCErrorLogger::instance()->error("Login - User does not have patient role: " . implode(', ', $user->roles));
                return $this->response(
                    null,
                    __('Only patients can login through the booking system.', 'kivicare-clinic-management-system'),
                    false,
                    403
                );
            }
            KCErrorLogger::instance()->error("Login - Patient role validated successfully");
        } else {
            // Check if user has a valid role for the system (normal login)
            if (!$this->hasValidRole($user)) {
                wp_logout();
                return $this->response(
                    null,
                    __('You do not have permission to access this site.', 'kivicare-clinic-management-system'),
                    false,
                    403
                );
            }
        }

        wp_clear_auth_cookie();
        header_remove('Set-Cookie');
        wp_set_current_user($user->ID);
        // Set cookie for subsequent access if needed
        add_action('set_logged_in_cookie', function ($logged_in_cookie) {
            $_COOKIE[LOGGED_IN_COOKIE] = $logged_in_cookie;
        });
        wp_set_auth_cookie($user->ID);

        // Get redirect URL based on user role
        $redirect_url = $this->getLoginRedirectUrl($user->roles[0] ?? '');
        KCErrorLogger::instance()->error("Login - Redirect URL: " . $redirect_url);

        // Get clinic data based on user role
        $clinic_data = $this->getUserClinicData($user->ID, $user->roles);

        $userData = [
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'user_email' => $user->user_email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'mobile_number' => get_user_meta($user->ID, 'mobile_number', true),
            'roles' => $user->roles,
            'profileImageUrl' => $this->getUserProfileImageUrl($user->ID, $user->roles[0] ?? ''),
            'nonce' => wp_create_nonce('wp_rest'),
            'redirect_url' => $redirect_url,
            'clinics' => $clinic_data
        ];


        return $this->response(
            $userData,
            __('Login successful', 'kivicare-clinic-management-system'),
            true,
            200
        );
    }

    /**
     * Get login redirect URL based on user role
     */
    private function getLoginRedirectUrl($role): string
    {
        $login_redirects = KCOption::get('login_redirect', []);

        // Redirect to wp-admin if admin
        if($role == 'administrator'){
            return home_url('wp-admin');
        }
        // Check if a custom redirect is set for this role using the short key
        if (!empty($login_redirects[$role])) {
            return apply_filters('kc_login_redirect_url', $login_redirects[$role], $role);
        }
        // Default redirects based on role
        return apply_filters('kc_login_redirect_url', KCDashboardPermalinkHandler::instance()->get_dashboard_url($role) ?? home_url(), $role);
    }

    /**
     * Check if user has a valid role for the KiviCare system
     * 
     * @param WP_User $user
     * @return bool
     */
    private function hasValidRole($user): bool
    {
        if (!$user || !isset($user->roles) || !is_array($user->roles)) {
            return false;
        }

        foreach ($user->roles as $role) {
            if (in_array($role, $this->kcbase->KCGetRoles())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get clinic data for a user based on their role
     *
     * @param int $user_id
     * @param array $user_roles
     * @return array
     */
    private function getUserClinicData($user_id, $user_roles)
    {
        $clinics = [];

        // Check user roles and get clinic data accordingly
        if (in_array($this->kcbase->getDoctorRole(), $user_roles)) {
            // Get doctor's clinic mappings
            $doctor_mappings = KCDoctorClinicMapping::table('dcm')
                ->where('dcm.doctor_id', '=', $user_id)
                ->get();

            foreach ($doctor_mappings as $mapping) {
                $clinic = KCClinic::find($mapping->clinicId);
                if ($clinic) {
                    $clinics[] = [
                        'id' => $clinic->id,
                        'label' => $clinic->name
                    ];
                }
            }
        } elseif (in_array($this->kcbase->getPatientRole(), $user_roles)) {
            // Get patient's clinic mappings
            $patient_mappings = KCPatientClinicMapping::table('pcm')
                ->where('pcm.patient_id', '=', $user_id)
                ->get();

            foreach ($patient_mappings as $mapping) {
                $clinic = KCClinic::find($mapping->clinicId);
                if ($clinic) {
                    $clinics[] = [
                        'id' => $clinic->id,
                        'label' => $clinic->name
                    ];
                }
            }
        } elseif (in_array($this->kcbase->getReceptionistRole(), $user_roles)) {
            // Get receptionist's clinic mappings
            $receptionist_mappings = KCReceptionistClinicMapping::table('rcm')
                ->where('rcm.receptionist_id', '=', $user_id)
                ->get();

            foreach ($receptionist_mappings as $mapping) {
                $clinic = KCClinic::find($mapping->clinicId);
                if ($clinic) {
                    $clinics[] = [
                        'id' => $clinic->id,
                        'label' => $clinic->name
                    ];
                }
            }
        } elseif (in_array($this->kcbase->getClinicAdminRole(), $user_roles)) {
            // For clinic admin, get all clinics they own
            $clinic_mappings = KCClinic::table('c')
                ->where('c.clinic_admin_id', '=', $user_id)
                ->get();

            foreach ($clinic_mappings as $clinic) {
                $clinics[] = [
                    'id' => $clinic->id,
                    'label' => $clinic->name
                ];
            }
        } elseif (in_array('administrator', $user_roles)) {
            // Administrator might have access to all clinics
            $clinic_mappings = KCClinic::table('c')->get();

            foreach ($clinic_mappings as $clinic) {
                $clinics[] = [
                    'id' => $clinic->id,
                    'label' => $clinic->name
                ];
            }
        }

        return $clinics;
    }

    /**
     * Send welcome email to new user
     */
    private function sendWelcomeEmail($user_id, $username, $password)
    {
        $user = get_userdata($user_id);

        if (!$user) {
            return false;
        }

        // Prepare user data for template
        $userData = [
            'user_name' => $user->display_name ?: $username,
            'user_email' => $user->user_email,
            'user_password' => $password,
            'login_url' => wp_login_url(),
            'widgets_login_url' => wp_login_url(),
            'appointment_page_url' => home_url('/appointments'),
            'current_date' => current_time('Y-m-d'),
            'current_date_time' => current_time('Y-m-d H:i:s')
        ];

        // Determine template based on user role
        $templateName = KIVI_CARE_PREFIX . 'patient_register';
        $userRoles = $user->roles;

        if (in_array('kivicare_doctor', $userRoles)) {
            $templateName = KIVI_CARE_PREFIX . 'doctor_registration';
        } elseif (in_array('kivicare_receptionist', $userRoles)) {
            $templateName = KIVI_CARE_PREFIX . 'receptionist_register';
        } elseif (in_array('kivicare_clinic_admin', $userRoles)) {
            $templateName = KIVI_CARE_PREFIX . 'clinic_admin_registration';
        }

        // Send email using template
        return $this->emailSender->sendUserRegistrationEmail($templateName, $userData);
    }

    /**
     * Send admin notification for new user registration
     */
    private function sendAdminNewUserNotification($user_id)
    {
        try {
            $user = get_userdata($user_id);
            if (!$user) {
                return false;
            }

            $admin_email = get_option('admin_email');
            $subject = __('New User Registration - KiviCare', 'kivicare-clinic-management-system');
            $message = sprintf(
                /* translators: 1: User's display name, 2: User's username, 3: User's email, 4: User's roles */
                __('A new user has registered on your KiviCare site.

                    User Details:
                    Name: %1$s
                    Username: %2$s
                    Email: %3$s
                    Role: %4$s

                    You can manage this user from the admin dashboard.', 'kivicare-clinic-management-system'),
                $user->display_name,
                $user->user_login,
                $user->user_email,
                implode(', ', $user->roles)
            );

            return wp_mail($admin_email, $subject, $message);
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Admin notification email error: ' . $e->getMessage());
            return false;
        }
    }
    private function sendPasswordResetEmail($user, $key)
    {
        $reset_url = add_query_arg([
            'action' => 'rp',
            'key' => $key,
            'login' => rawurlencode($user->user_login)
        ], wp_login_url());

        // Prepare user data for template
        $userData = [
            'user_name' => $user->display_name ?: $user->user_login,
            'user_email' => $user->user_email,
            'reset_url' => $reset_url,
            'current_date' => current_time('Y-m-d'),
            'current_date_time' => current_time('Y-m-d H:i:s')
        ];

        // Try to use password reset template, fallback to WordPress default
        $templateSent = $this->emailSender->sendUserRegistrationEmail(
            KIVI_CARE_PREFIX . 'password_reset',
            $userData
        );

        // If template email fails, fallback to WordPress default password reset
        if (!$templateSent) {
            // Use WordPress default password reset email
            $subject = __('Password Reset Request - KiviCare', 'kivicare-clinic-management-system');
            $message = sprintf(
                /* translators: %s: Password reset URL */
                __('Someone has requested a password reset for your account.\n\nTo reset your password, visit the following address:\n%s\n\nIf this was a mistake, just ignore this email and nothing will happen.', 'kivicare-clinic-management-system'),
                $reset_url
            );

            return wp_mail($user->user_email, $subject, $message);
        }

        return $templateSent;
    }

    /**
     * Send user credentials email (for resending credentials)
     * 
     * @param int $user_id
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function sendUserCredentials($user_id, $username, $password)
    {
        $user = get_userdata($user_id);

        if (!$user) {
            return false;
        }

        $userData = [
            'user_name' => $user->display_name ?: $username,
            'user_email' => $user->user_email,
            'user_password' => $password,
            'login_url' => wp_login_url(),
            'current_date' => current_time('Y-m-d'),
            'current_date_time' => current_time('Y-m-d H:i:s')
        ];

        return $this->emailSender->sendUserRegistrationEmail(
            KIVI_CARE_PREFIX . 'resend_user_credential',
            $userData
        );
    }

    /**
     * Send user verification email
     * 
     * @param int $user_id
     * @return bool
     */
    public function sendUserVerificationEmail($user_id)
    {
        $user = get_userdata($user_id);

        if (!$user) {
            return false;
        }

        $userData = [
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'current_date' => current_time('Y-m-d'),
            'login_url' => wp_login_url(),
            'current_date_time' => current_time('Y-m-d H:i:s')
        ];

        return $this->emailSender->sendUserRegistrationEmail(
            KIVI_CARE_PREFIX . 'user_verified',
            $userData
        );
    }

    public function register(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();

        // Validate reCAPTCHA first
        if (isset($params['recaptchaToken'])) {
            $recaptcha_result = $this->validateRecaptcha($params['recaptchaToken']);
            if (is_wp_error($recaptcha_result)) {
                KCErrorLogger::instance()->error("Registration - reCAPTCHA Failed: " . $recaptcha_result->get_error_message());
                return $this->response(
                    null,
                    $recaptcha_result->get_error_message(),
                    false,
                    400
                );
            }
        }

        // Check if username or email exists
        if (username_exists($params['username'])) {
            return $this->response(
                null,
                __('Username already exists', 'kivicare-clinic-management-system'),
                false,
                400
            );
        }
        if (email_exists($params['email'])) {
            return $this->response(
                null,
                __('Email already exists', 'kivicare-clinic-management-system'),
                false,
                400
            );
        }

        if (empty($params['mobile_number'])) {
            return $this->response(
                null,
                __('Contact number is required', 'kivicare-clinic-management-system'),
                false,
                400
            );
        }

        $mobile_number = preg_replace('/[^0-9+]/', '', $params['mobile_number']);

        if (empty($mobile_number) || $mobile_number[0] !== '+') {
            return $this->response(
                null,
                __('Invalid contact number format', 'kivicare-clinic-management-system'),
                false,
                400
            );
        }

        $existing_user = get_users([
            'meta_key' => 'mobile_number',
            'meta_value' => $mobile_number,
            'meta_compare' => '=',
            'number' => 1
        ]);

        if (!empty($existing_user)) {
            return $this->response(
                null,
                __('Mobile number already exists', 'kivicare-clinic-management-system'),
                false,
                400
            );
        }

        // Determine user role
        $user_role = $params['user_role'] ?? $this->kcbase->getPatientRole();

        // Validate if the selected role is enabled in settings
        $role_settings = KCOption::get('user_registration_shortcode_role_setting') ?? [];
        if (isset($role_settings[$user_role]) && $role_settings[$user_role] !== 'on') {
            return $this->response(
                null,
                __('Selected user role is not enabled for registration', 'kivicare-clinic-management-system'),
                false,
                400
            );
        }

        // Get clinic ID
        $clinic_id = $params['user_clinic'];

        // Validate clinic exists
        $clinic = KCClinic::find($clinic_id);
        if (!$clinic) {
            return $this->response(
                null,
                __('Invalid clinic selected', 'kivicare-clinic-management-system'),
                false,
                400
            );
        }

        try {
            $user_id = null;
            /**
             * Role-to-class mapping.
             */
            $role_map = [
                $this->kcbase->getPatientRole() => [
                    'model' => KCPatient::class,
                    'mapping' => KCPatientClinicMapping::class,
                    'keys' => ['patientId' => 'id']
                ],
                $this->kcbase->getDoctorRole() => [
                    'model' => KCDoctor::class,
                    'mapping' => KCDoctorClinicMapping::class,
                    'keys' => ['doctorId' => 'id', 'owner' => 0]
                ],
                $this->kcbase->getReceptionistRole() => [
                    'model' => KCReceptionist::class,
                    'mapping' => KCReceptionistClinicMapping::class,
                    'keys' => ['receptionistId' => 'id']
                ],
            ];

            /**
             * Validate role.
             */
            if (!isset($role_map[$user_role])) {
                return $this->response(null, __('Invalid user role', 'kivicare-clinic-management-system'), false, 400);
            }

            $role_config = $role_map[$user_role];
            $model_class = $role_config['model'];
            $mapping_class = $role_config['mapping'];

            $model = new $model_class();

            /**
             * Get default status from settings
             */
            $registration_status_settings = KCOption::get('user_registration_shortcode_setting') ?? [];

            // Map role to setting key
            $role_key_map = [
                $this->kcbase->getDoctorRole() => 'doctor',
                $this->kcbase->getReceptionistRole() => 'receptionist',
                $this->kcbase->getPatientRole() => 'patient'
            ];

            $setting_key = $role_key_map[$user_role] ?? 'patient';
            // Default to 'on' (Active/0) if setting is missing, to ensure users can login immediately
            $default_status = ($registration_status_settings[$setting_key] ?? 'on') === 'on' ? 0 : 1;

            /**
             * Shared user fields.
             */
            $shared_fields = [
                'username' => $params['username'],
                'email' => $params['email'],
                'password' => $params['password'],
                'firstName' => $params['first_name'] ?? '',
                'lastName' => $params['last_name'] ?? '',
                'displayName' => trim(($params['first_name'] ?? '') . ' ' . ($params['last_name'] ?? '')),
                'contactNumber' => $mobile_number,
                'gender' => $params['gender'],
                'status' => $default_status,
            ];

            /**
             * Assign shared fields to model.
             */
            foreach ($shared_fields as $key => $value) {
                $model->{$key} = $value;
            }

            /**
             * Optional demographic fields.
             */
            if (!empty($params['dob'])) {
                $model->dob = $params['dob'];
            }

            if (!empty($params['address'])) {
                $model->address = $params['address'];
            }

            // Blood group is only applicable to patients
            if (!empty($params['blood_group']) && $user_role === $this->kcbase->getPatientRole()) {
                $model->bloodGroup = $params['blood_group'];
            }

            /**
             * Save user.
             */
            $result = $model->save();

            if (is_wp_error($result)) {
                return $this->response(null, $result->get_error_message(), false, 400);
            }

            $user_id = $result;

            /**
             * Mapping creation.
             */
            $mapping = new $mapping_class();
            $mapping->clinicId = $clinic_id;
            $mapping->createdAt = current_time('mysql');

            /** Assign role-specific mapping keys */
            foreach ($role_config['keys'] as $mappingKey => $sourceKey) {
                $mapping->{$mappingKey} = ($sourceKey === 'id') ? $user_id : $sourceKey;
            }

            $mapping->save();

            /**
             * Final response.
             */
            if (!$user_id) {
                return $this->response(null, __('Failed to create user', 'kivicare-clinic-management-system'), false, 400);
            }

            // Auto-login the user
            wp_clear_auth_cookie();
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);

            // Set cookie for subsequent access if needed in current request
            add_action('set_logged_in_cookie', function ($logged_in_cookie) {
                $_COOKIE[LOGGED_IN_COOKIE] = $logged_in_cookie;
            });

            // Send welcome email
            $this->sendWelcomeEmail($user_id, $params['username'], $params['password']);
            $this->sendAdminNewUserNotification($user_id);

            // Get user data for response
            $wpUser = get_userdata($user_id);
            $userData = [
                'user_id' => $user_id,
                'username' => $wpUser->user_login,
                'email' => $wpUser->user_email,
                'display_name' => $wpUser->display_name,
                'first_name' => $wpUser->first_name,
                'last_name' => $wpUser->last_name,
                'mobile_number' => $mobile_number,
                'gender' => $params['gender'],
                'user_role' => $user_role,
                'clinic_id' => $clinic_id,
                'roles' => $wpUser->roles,
                'profileImageUrl' => $this->getUserProfileImageUrl($user_id, $user_role),
                'nonce' => wp_create_nonce('wp_rest')
            ];

            return $this->response(
                $userData,
                __('Registration successful.', 'kivicare-clinic-management-system'),
                true,
                201
            );

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Registration error: ' . $e->getMessage());
            return $this->response(
                null,
                __('Registration failed. Please try again.', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    public function forgotPassword(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();

        // Validate reCAPTCHA
        if (isset($params['recaptchaToken'])) {
            $recaptcha_result = $this->validateRecaptcha($params['recaptchaToken']);
            if (is_wp_error($recaptcha_result)) {
                return $this->response(
                    null,
                    $recaptcha_result->get_error_message(),
                    false,
                    400
                );
            }
        }

        $user_login = $params['user_login'];

        if (strpos($user_login, '@')) {
            $user = get_user_by('email', $user_login);
        } else {
            $user = get_user_by('login', $user_login);
        }

        if (!$user) {
            return $this->response(
                null,
                __('User not found', 'kivicare-clinic-management-system'),
                false,
                404
            );
        }

        $key = get_password_reset_key($user);

        if (is_wp_error($key)) {
            return $this->response(
                null,
                $key->get_error_message(),
                false,
                400
            );
        }

        // $this->sendPasswordResetEmail($user, $key);

        return $this->response(
            null,
            __('Password reset email sent successfully', 'kivicare-clinic-management-system'),
            true,
            200
        );
    }

    public function resetPassword(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();
        $user = check_password_reset_key($params['key'], $params['login']);

        if (is_wp_error($user)) {
            return $this->response(
                null,
                $user->get_error_message(),
                false,
                400
            );
        }

        reset_password($user, $params['password']);

        // Re-authenticate user to maintain session and prevent cookie invalidation
        // This fixes the "cookie check failed" error when changing password multiple times
        wp_set_auth_cookie($user->ID);

        // Return new nonce for subsequent authenticated requests
        $response_data = [
            'nonce' => wp_create_nonce('wp_rest')
        ];

        return $this->response(
            $response_data,
            __('Password reset successful', 'kivicare-clinic-management-system'),
            true,
            200
        );
    }

    /**
     * Change user password
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function changePassword(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();
        $current_user = wp_get_current_user();

        if (!$current_user || $current_user->ID === 0) {
            return $this->response(
                null,
                __('User not authenticated', 'kivicare-clinic-management-system'),
                false,
                401
            );
        }

        // Verify current password
        if (!wp_check_password($params['current_password'], $current_user->user_pass, $current_user->ID)) {
            return $this->response(
                null,
                __('Current password is incorrect', 'kivicare-clinic-management-system'),
                false,
                400
            );
        }

        // Check if new password matches confirm password
        if ($params['new_password'] !== $params['confirm_password']) {
            return $this->response(
                null,
                __('New password and confirm password do not match', 'kivicare-clinic-management-system'),
                false,
                400
            );
        }

        // Check if new password is different from current password
        if (wp_check_password($params['new_password'], $current_user->user_pass, $current_user->ID)) {
            return $this->response(
                null,
                __('New password must be different from current password', 'kivicare-clinic-management-system'),
                false,
                400
            );
        }

        /**
         * Update password using wp_update_user() instead of wp_set_password()
         * 
         * CRITICAL: wp_set_password() destroys all user sessions for security,
         * which would log out the user immediately after changing their password.
         * 
         * wp_update_user() is the WordPress core method used in wp-admin to update
         * user data (including passwords) while maintaining the current session.
         * This allows users to change their password and continue using the app
         * without being logged out or encountering "cookie check failed" errors.
         */
        $user_id = wp_update_user([
            'ID' => $current_user->ID,
            'user_pass' => $params['new_password']
        ]);

        if (is_wp_error($user_id)) {
            return $this->response(
                null,
                $user_id->get_error_message(),
                false,
                400
            );
        }

        // Return new nonce for subsequent authenticated requests
        // The frontend API interceptor will automatically update window.kc_frontend.nonce
        $response_data = [
            'nonce' => wp_create_nonce('wp_rest')
        ];

        return $this->response(
            $response_data,
            __('Password changed successfully', 'kivicare-clinic-management-system'),
            true,
            200
        );
    }

    /**
     * Delete currently logged-in user's account
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function deleteCurrentUserAccount(WP_REST_Request $request): WP_REST_Response
    {
        // Use global WordPress functions from the root namespace
        $current_user_id = \get_current_user_id();

        if (!$current_user_id) {
            return $this->response(
                null,
                __('User not authenticated', 'kivicare-clinic-management-system'),
                false,
                401
            );
        }

        // Ensure the user deletion functions are available
        if (!\function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $deleted = \wp_delete_user($current_user_id);

        if ($deleted) {
            // Ensure user is logged out after account deletion
            \wp_logout();

            return $this->response(
                null,
                __('Your account has been deleted successfully', 'kivicare-clinic-management-system'),
                true,
                200
            );
        }

        return $this->response(
            null,
            __('Something went wrong while deleting your account', 'kivicare-clinic-management-system'),
            false,
            500
        );
    }

    /**
     * Download image from URL and create WordPress attachment
     * 
     * @param string $image_url The URL of the image to download
     * @param int $user_id The user ID to associate with the image
     * @return int|null Attachment ID on success, null on failure
     */
    private function downloadImageAndCreateAttachment(string $image_url, int $user_id): ?int
    {
        if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
            return null;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Primary: core helper.
        $attachment_id = media_sideload_image($image_url, 0, '', 'id');
        if (!is_wp_error($attachment_id)) {
            update_post_meta($attachment_id, '_source_url', esc_url_raw($image_url));
            wp_update_post([
                'ID' => $attachment_id,
                'post_author' => $user_id,
                'post_title' => sanitize_file_name('patient-' . $user_id . '-' . time()),
            ]);
            return (int) $attachment_id;
        }

        // Fallback: download then sideload.
        $temp = download_url($image_url, 20);
        if (is_wp_error($temp)) {
            return null;
        }

        $max_size = 10 * 1024 * 1024; // 10MB cap
        $size = @filesize($temp);
        if ($size === false || $size > $max_size) {
            wp_delete_file( $temp );
            return null;
        }

        $info = @getimagesize($temp);
        if ($info === false) {
            wp_delete_file( $temp );
            return null;
        }

        $mime_to_ext = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $ext = $mime_to_ext[$info['mime']] ?? 'jpg';
        $filename = sanitize_file_name('patient-' . $user_id . '-' . time() . '.' . $ext);

        $file_array = [
            'name' => $filename,
            'tmp_name' => $temp,
        ];

        $attachment_id = media_handle_sideload($file_array, 0, '', [
            'post_author' => $user_id,
            'post_title' => sanitize_file_name('patient-' . $user_id . '-' . time()),
        ]);

        if (is_wp_error($attachment_id)) {
            wp_delete_file( $temp );
            return null;
        }

        update_post_meta($attachment_id, '_source_url', esc_url_raw($image_url));
        return (int) $attachment_id;
    }

    /**
     * Get profile image URL for a user based on their role
     */
    private function getUserProfileImageUrl(int $userId, string $userRole = ''): string
    {
        $profileImageMetaKey = '';
        if (empty($userRole)) {
            $user = get_userdata($userId);
            $userRole = $user->roles[0] ?? '';
        }

        if ($userRole === $this->kcbase->getDoctorRole()) {
            $profileImageMetaKey = 'doctor_profile_image';
        } elseif ($userRole === $this->kcbase->getPatientRole()) {
            $profileImageMetaKey = 'patient_profile_image';
        } elseif ($userRole === $this->kcbase->getReceptionistRole()) {
            $profileImageMetaKey = 'receptionist_profile_image';
        } elseif ($userRole === $this->kcbase->getClinicAdminRole()) {
            $profileImageMetaKey = 'clinic_admin_profile_image';
        }

        if ($profileImageMetaKey) {
            $profileImageId = get_user_meta($userId, $profileImageMetaKey, true);
            if ($profileImageId) {
                $profileImageUrl = wp_get_attachment_url($profileImageId);
                if ($profileImageUrl) {
                    return $profileImageUrl;
                }
            }
        }

        return '';
    }

    /**
     * Patient social login endpoint handler
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function patientSocialLogin(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();
        $login_type = strtolower($params['login_type']);
        $email = !empty($params['email']) ? sanitize_email($params['email']) : '';
        $contact_number = !empty($params['contact_number']) ? sanitize_text_field($params['contact_number']) : '';
        $access_token = !empty($params['password']) ? $params['password'] : ''; // Access token from social provider
        $first_name = !empty($params['first_name']) ? sanitize_text_field($params['first_name']) : '';
        $last_name = !empty($params['last_name']) ? sanitize_text_field($params['last_name']) : '';
        $username = !empty($params['username']) ? sanitize_user($params['username']) : '';
        $profile_image_url = !empty($params['profile_image_url']) ? esc_url_raw($params['profile_image_url']) : '';

        // Validate that at least email or contact_number is provided
        if (empty($email) && empty($contact_number)) {
            return $this->response(
                null,
                __('Email or contact number is required', 'kivicare-clinic-management-system'),
                false,
                400
            );
        }

        // Find existing user by email or contact number
        $user = null;
        $user_id = null;

        if (!empty($email)) {
            $user = get_user_by('email', $email);
        }

        // If not found by email, try contact number
        if (!$user && !empty($contact_number)) {
            $users = get_users([
                'meta_key' => 'mobile_number',
                'meta_value' => $contact_number,
                'meta_compare' => '=',
                'number' => 1
            ]);
            if (!empty($users)) {
                $user = $users[0];
            }
        }

        $is_new_user = !$user;

        try {
            if ($is_new_user) {
                // Create new patient user
                $patient = new KCPatient();

                // Generate username if not provided
                if (empty($username)) {
                    if (!empty($email)) {
                        $username = sanitize_user(substr($email, 0, strpos($email, '@')));
                    } else {
                        $username = 'patient_' . time() . '_' . wp_generate_password(6, false);
                    }

                    // Ensure username is unique
                    $original_username = $username;
                    $counter = 1;
                    while (username_exists($username)) {
                        $username = $original_username . '_' . $counter;
                        $counter++;
                    }
                } else {
                    // Check if username already exists
                    if (username_exists($username)) {
                        return $this->response(
                            null,
                            __('Username already exists', 'kivicare-clinic-management-system'),
                            false,
                            400
                        );
                    }
                }

                // Generate unique secure password for social login user
                $generated_password = wp_generate_password(16, true, true);

                // Set patient properties
                $patient->username = $username;
                $patient->email = !empty($email) ? $email : $username . '@social.local';
                $patient->password = $generated_password;
                $patient->firstName = $first_name;
                $patient->lastName = $last_name;
                $patient->displayName = trim($first_name . ' ' . $last_name) ?: $username;
                $patient->contactNumber = $contact_number;
                $patient->status = 0; // Active by default

                // Save patient
                $user_id = $patient->save();

                if (is_wp_error($user_id)) {
                    return $this->response(
                        null,
                        $user_id->get_error_message(),
                        false,
                        400
                    );
                }

                // Get default clinic ID
                $clinic_id = KCClinic::kcGetDefaultClinicId();

                // Create patient-clinic mapping
                if ($clinic_id) {
                    $mapping = new KCPatientClinicMapping();
                    $mapping->patientId = $user_id;
                    $mapping->clinicId = $clinic_id;
                    $mapping->createdAt = current_time('mysql');
                    $mapping->save();
                }

                // Store login type and access token in user meta
                update_user_meta($user_id, 'login_type', $login_type);
                if (!empty($access_token)) {
                    update_user_meta($user_id, 'social_access_token', $access_token);
                    update_user_meta($user_id, 'social_access_token_updated', current_time('mysql'));
                }

                // Download and save profile image from social provider if provided
                if (!empty($profile_image_url)) {
                    $profile_image_id = $this->downloadImageAndCreateAttachment($profile_image_url, $user_id);
                    if ($profile_image_id) {
                        update_user_meta($user_id, 'patient_profile_image', $profile_image_id);
                    }
                }

                $message = __('User account created successfully via social login', 'kivicare-clinic-management-system');
            } else {
                // Existing user - update profile info (don't change password)
                $user_id = $user->ID;
                // Generate new password if user doesn't have one set
                $wp_user = get_userdata($user_id);
                if (empty($wp_user->user_pass) || $wp_user->user_pass === '*') {
                    // User has no password set, generate one
                    $generated_password = wp_generate_password(16, true, true);
                    wp_set_password($generated_password, $user_id);
                }

                // Update user meta
                if (!empty($first_name)) {
                    update_user_meta($user_id, 'first_name', $first_name);
                }
                if (!empty($last_name)) {
                    update_user_meta($user_id, 'last_name', $last_name);
                }
                if (!empty($contact_number)) {
                    update_user_meta($user_id, 'mobile_number', $contact_number);
                }

                // Update display name
                $display_name = trim($first_name . ' ' . $last_name);
                if (!empty($display_name)) {
                    wp_update_user([
                        'ID' => $user_id,
                        'display_name' => $display_name
                    ]);
                }

                // Store login type and access token in user meta
                update_user_meta($user_id, 'login_type', $login_type);
                if (!empty($access_token)) {
                    update_user_meta($user_id, 'social_access_token', $access_token);
                    update_user_meta($user_id, 'social_access_token_updated', current_time('mysql'));
                }

                // Update profile image if provided
                if (!empty($profile_image_url)) {
                    $existing_profile_image = get_user_meta($user_id, 'patient_profile_image', true);
                    // Update profile image if not set, or always update from social provider
                    if (empty($existing_profile_image)) {
                        $profile_image_id = $this->downloadImageAndCreateAttachment($profile_image_url, $user_id);
                        if ($profile_image_id) {
                            update_user_meta($user_id, 'patient_profile_image', $profile_image_id);
                        }
                    }
                }

                $message = __('User logged in successfully via social login', 'kivicare-clinic-management-system');
            }

            // Log in the user
            wp_clear_auth_cookie();
            header_remove('Set-Cookie');
            wp_set_current_user($user_id);
            add_action('set_logged_in_cookie', function ($logged_in_cookie) {
                $_COOKIE[LOGGED_IN_COOKIE] = $logged_in_cookie;
            });
            wp_set_auth_cookie($user_id);

            // Get user data
            $wp_user = get_userdata($user_id);

            // Check if user has patient role
            if (!in_array($this->kcbase->getPatientRole(), $wp_user->roles)) {
                return $this->response(
                    null,
                    __('This account is not a patient account', 'kivicare-clinic-management-system'),
                    false,
                    403
                );
            }

            // Get redirect URL
            $redirect_url = $this->getLoginRedirectUrl($this->kcbase->getPatientRole());

            // Get clinic data
            $clinic_data = $this->getUserClinicData($user_id, $wp_user->roles);

            // Build response data (same format as login endpoint)
            $userData = [
                'user_id' => $user_id,
                'username' => $wp_user->user_login,
                'display_name' => $wp_user->display_name,
                'user_email' => $wp_user->user_email,
                'first_name' => get_user_meta($user_id, 'first_name', true),
                'last_name' => get_user_meta($user_id, 'last_name', true),
                'mobile_number' => get_user_meta($user_id, 'mobile_number', true),
                'roles' => $wp_user->roles,
                'profileImageUrl' => $this->getUserProfileImageUrl($user_id, $this->kcbase->getPatientRole()),
                'nonce' => wp_create_nonce('wp_rest'),
                'redirect_url' => $redirect_url,
                'clinics' => $clinic_data
            ];

            // Add WooCommerce data if available
            if (function_exists('kc_woo_generate_client_auth')) {
                $wc_data = kc_woo_generate_client_auth('kivicare_app', $user_id, 'read_write');
                $userData = array_merge($userData, $wc_data);
            }

            return $this->response(
                $userData,
                $message,
                true,
                200
            );

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Social login error: ' . $e->getMessage());
            return $this->response(
                null,
                __('Social login failed. Please try again.', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

}