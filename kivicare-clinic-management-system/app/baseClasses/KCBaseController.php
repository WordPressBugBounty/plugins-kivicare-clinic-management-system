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
}
