<?php

namespace App\baseClasses;

use App\emails\KCEmailSender;
use App\interfaces\KCIController;
use WP_REST_Request;
use WP_REST_Response;

defined("ABSPATH") or die("Something went wrong");

/**
 * Class KCBaseController
 *
 * Base controller for all API endpoints with module support
 *
 * @package App\baseClasses
 * @since 5.0.0
 */
abstract class KCBaseController implements KCIController
{
    /**
     * @var string The namespace for all REST API endpoints
     */
    protected $namespace = KIVI_CARE_NAME . "/v1";

    /**
     * @var string The module ID this controller belongs to
     */
    protected $moduleId;

    /**
     * @var string The controller ID within its module
     */
    protected $controllerId;

    /**
     * @var KCBase Base instance
     */
    protected $kcbase;

    /**
     * @var KCPermissions Permission handler
     */
    protected $permissions;

    /**
     * @var KCEmailSender Email sender instance
     */
    protected KCEmailSender $emailSender;

    /**
     * Initialize the controller
     *
     * @param string $moduleId The module ID this controller belongs to
     * @param string $controllerId The controller ID within the module
     */
    public function __construct($moduleId = "", $controllerId = "")
    {
        $this->moduleId = $moduleId;
        $this->controllerId = $controllerId;
        $this->kcbase = KCBase::get_instance();
        $this->permissions = KCPermissions::get_instance();
        $this->emailSender = KCEmailSender::get_instance();

        // Allow customizing the API namespace
        if (!empty($this->moduleId)) {
            $this->namespace = apply_filters(
                "kivicare_module_{$this->moduleId}_namespace",
                $this->namespace,
            );
        }
    }

    /**
     * Register routes for this controller
     * This method should be implemented by child classes
     */
    abstract public function registerRoutes();

    /**
     * Check if user has permission to access the endpoint
     *
     * @param WP_REST_Request $request Current request
     * @return bool Whether user has permission
     */
    public function checkPermission($request)
    {
        // Allow filtering permissions per module/controller
        return apply_filters(
            "kivicare_check_permission_{$this->moduleId}_{$this->controllerId}",
            current_user_can("read"),
            $request,
        );
    }

    /**
     * Helper method to register a REST route with proper timing and filtering
     *
     * @param string $route The route to register
     * @param array $args The route arguments
     * @return bool Whether registration was successful
     */
    protected function registerRoute($route, $args)
    {
        if (!did_action("rest_api_init")) {
            _doing_it_wrong(
                __METHOD__,
                sprintf(
                    "REST API routes must be registered on the rest_api_init action. Route %s was not registered correctly.",
                    esc_html($route),
                ),
                "5.0.0",
            );
            return false;
        }

        // Allow filtering route
        $route = apply_filters(
            "kivicare_route_{$this->moduleId}_{$this->controllerId}",
            $route,
        );

        // Allow filtering route arguments
        $args = apply_filters(
            "kivicare_route_args_{$this->moduleId}_{$this->controllerId}_{$route}",
            $args,
        );

        // Add module context to route
        if (!empty($this->moduleId)) {
            $route = $this->moduleId . "/" . ltrim($route, "/");
        }

        return register_rest_route($this->namespace, $route, $args);
    }

    /**
     * Format API response
     *
     * @param mixed $data Response data
     * @param string $message Response message
     * @param bool $status Response status
     * @param int $code HTTP status code
     * @return WP_REST_Response
     */
    protected function response(
        $data = null,
        $message = "",
        $status = true,
        $code = 200,
    ) {
        $response = [
            "status" => $status,
            "message" => $message,
            "data" => $data,
        ];

        // Allow filtering response
        $response = apply_filters(
            "kivicare_response_{$this->moduleId}_{$this->controllerId}",
            $response,
            $code,
        );

        return rest_ensure_response(new WP_REST_Response($response, $code));
    }

    /**
     * Return permission denied response
     *
     * @return WP_REST_Response
     */
    protected function permissionDeniedResponse()
    {
        return $this->response(
            null,
            __(
                "You do not have permission to access this resource.",
                "kivicare-clinic-management-system",
            ),
            false,
            403,
        );
    }

    /**
     * Check specific capability permission
     *
     * @param string $capability The capability to check
     * @param WP_REST_Request|null $request Current request
     * @return bool Whether user has capability
     */
    protected function checkCapability($capability, $request = null)
    {
        return apply_filters(
            "kivicare_check_capability_{$this->moduleId}_{$this->controllerId}",
            KCPermissions::has_permission($capability),
            $capability,
            $request,
        );
    }

    /**
     * Check resource access permission
     *
     * @param string $resource The resource type
     * @param string $action The action being performed
     * @param int|null $resource_id Optional resource ID
     * @param WP_REST_Request|null $request Current request
     * @return bool Whether user can access resource
     */
    protected function checkResourceAccess(
        $resource,
        $action,
        $resource_id = null,
        $request = null,
    ) {
        $permission_key = $resource . "_" . $action;

        return apply_filters(
            "kivicare_check_resource_access_{$this->moduleId}_{$this->controllerId}",
            KCPermissions::can_user_perform_action($permission_key),
            $resource,
            $action,
            $resource_id,
            $request,
        );
    }

    /**
     * Get the module ID for this controller
     *
     * @return string
     */
    public function getModuleId()
    {
        return $this->moduleId;
    }

    /**
     * Get the controller ID within its module
     *
     * @return string
     */
    public function getControllerId()
    {
        return $this->controllerId;
    }

    /**
     * Check if a specific module is enabled in settings.
     *
     * @param string $module_name The key for the module in module_config (e.g., 'receptionist', 'billing').
     * @return bool
     */
    protected function isModuleEnabled($module_name) {
        $kivicare_settings = get_option('kivicare_settings', []);
        $module_config = $kivicare_settings['module_config'] ?? [];

        $is_enabled = ($module_config[$module_name] ?? 'on') !== 'off';

        if (!$is_enabled) {
        }

        return $is_enabled;
    }

    /**
     * Get translated message based on request locale
     * 
     * @param string $message The message to translate
     * @param WP_REST_Request $request The current request
     * @param string $domain The text domain
     * @return string Translated message
     */
    protected function getTranslatedMessage($message, $request, $domain = 'kivicare-clinic-management-system')
    {
        $params = $request->get_params();
        $lang = $params['language_code'] ?? $params['lang'] ?? null;

        if (!empty($lang)) {
            switch_to_locale($lang);
            $translated = __($message, $domain);
            restore_previous_locale();
            return $translated;
        }

        return __($message, $domain);
    }

    /**
     * Helper to split a combined contact number into country_code and phone_number
     * 
     * @param string $contactNumber The full contact number with or without '+'
     * @return array Array containing 'country_code' and 'phone_number'
     */
    protected function splitContactNumber($contactNumber)
    {
        $result = [
            'country_code' => '',
            'phone_number' => $contactNumber,
        ];

        if (empty($contactNumber)) {
            return $result;
        }

        // Clean the number (remove dashes, spaces, brackets, but keep +)
        $cleanNumber = preg_replace('/[^\d+]/', '', $contactNumber);

        // If it doesn't start with +, return as is (could be a local number without country code)
        if (substr($cleanNumber, 0, 1) !== '+') {
            return $result;
        }

        // List of significant country codes ordered by length (4 to 1) to match longest first, including NANP area codes to avoid +1 conflicts.
        $countryCodes = [
            // Four digit codes (NANP mainly)
            '+1242', '+1246', '+1264', '+1268', '+1284', '+1340', '+1345', '+1441', '+1473', '+1649', '+1664', '+1670', '+1671', '+1684', '+1721', '+1758', '+1767', '+1784', '+1787', '+1809', '+1829', '+1849', '+1868', '+1869', '+1876', '+1939', '+4779',
            // Three digit codes
            '+358', '+355', '+376', '+374', '+420', '+421', '+359', '+375', '+372', '+354', '+371', '+370', '+373', '+389', '+356', '+382', '+381', '+386', '+387', '+377', '+378', '+350', '+351', '+352', '+353', '+357', '+380', '+385', '+213', '+244', '+229', '+267', '+226', '+257', '+237', '+238', '+236', '+235', '+269', '+242', '+243', '+253', '+240', '+291', '+251', '+241', '+220', '+233', '+224', '+245', '+225', '+254', '+266', '+231', '+218', '+261', '+265', '+223', '+222', '+230', '+212', '+258', '+264', '+227', '+234', '+250', '+239', '+221', '+248', '+232', '+252', '+211', '+249', '+268', '+260', '+263', '+256', '+255', '+216', '+228', '+212', '+218', '+254', '+260', '+263', '+93', '+973', '+880', '+975', '+673', '+855', '+86', '+886', '+995', '+91', '+62', '+98', '+964', '+972', '+81', '+962', '+7', '+965', '+996', '+856', '+961', '+853', '+60', '+960', '+976', '+95', '+977', '+850', '+968', '+92', '+970', '+63', '+974', '+966', '+65', '+82', '+94', '+963', '+886', '+992', '+66', '+670', '+90', '+993', '+971', '+998', '+84', '+967', '+501', '+502', '+503', '+504', '+505', '+506', '+507', '+508', '+509', '+590', '+591', '+592', '+593', '+594', '+595', '+596', '+597', '+598', '+599', '+670', '+672', '+673', '+674', '+675', '+676', '+677', '+678', '+679', '+680', '+681', '+682', '+683', '+685', '+686', '+687', '+688', '+689', '+690', '+691', '+692', '+852', '+853', '+855', '+856', '+880', '+886', '+960', '+961', '+962', '+963', '+964', '+965', '+966', '+967', '+968', '+970', '+971', '+972', '+973', '+974', '+975', '+976', '+977', '+992', '+993', '+994', '+995', '+996', '+998',
            // Two digit codes
            '+20', '+27', '+30', '+31', '+32', '+33', '+34', '+36', '+39', '+40', '+41', '+43', '+44', '+45', '+46', '+47', '+48', '+49', '+51', '+52', '+53', '+54', '+55', '+56', '+57', '+58', '+60', '+61', '+62', '+63', '+64', '+65', '+66', '+81', '+82', '+84', '+86', '+90', '+91', '+92', '+93', '+94', '+95', '+98',
            // Single digit codes
            '+1', '+7'
        ];

        foreach ($countryCodes as $code) {
            if (strpos($cleanNumber, $code) === 0) {
                // Determine length of code 
                $codeLength = strlen($code);
                
                $result['country_code'] = $code;
                $result['phone_number'] = substr($cleanNumber, $codeLength);
                return $result;
            }
        }

        // Fallback logic if no country code match is found in the predefined list.
        if (substr($cleanNumber, 0, 2) === '+1' || substr($cleanNumber, 0, 2) === '+7') {
            $result['country_code'] = substr($cleanNumber, 0, 2);
            $result['phone_number'] = substr($cleanNumber, 2);
        } else {
            // Defaulting fallback (which isn't ideal but better than breaking)
            $result['country_code'] = substr($cleanNumber, 0, 3);
            $result['phone_number'] = substr($cleanNumber, 3);
        }

        return $result;
    }
}
