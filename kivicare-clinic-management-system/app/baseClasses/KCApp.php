<?php

namespace App\baseClasses;

use App\admin\AdminMenu;
use App\admin\KCDashboardPermalinkHandler;
use App\controllers\KCRestAPI;
use App\controllers\filters\KCCacheExclusionFilter;
use App\controllers\filters\KCDoctorControllerFilters;
use App\controllers\filters\KCPatientControllerFilters;
use App\controllers\api\AppointmentsController;
use App\emails\KCEmailNotificationInit;
use App\emails\KCEmailTemplateManager;
use App\shortcodes\KCBookAppointment;
use App\shortcodes\KCBookAppointmentButton;
use App\shortcodes\KCRegisterLogin;
use App\shortcodes\KCClinicListShortcode;
use App\shortcodes\KCDoctorListShortcode;
use KiviCare\Migrations\MigrateDashboardSidebar;
use App\elementor\widgets\ClinicListWidget;
use App\elementor\widgets\DoctorListWidget;
use App\services\KCAppointmentReminderService;
use App\models\KCOption;
use App\blocks\KCBlocksRegister;
use App\baseClasses\KCBase;
use App\models\KCClinic;
use App\baseClasses\KCMediaHandler;

/**
 * The code that runs during plugin activation
 */
defined('ABSPATH') or die('Something went wrong');

final class KCApp
{
    public function init()
    {
        (new AdminMenu())->register();

        // Initialize permalink handler for dashboard routes
        KCDashboardPermalinkHandler::instance();

        // Cache-plugin exclusions (LiteSpeed, WP Rocket, W3TC, etc.)
        // Kept separate so adding/removing a cache integration never touches routing code.
        KCCacheExclusionFilter::get_instance();

        // Initialize REST API
        new KCEmailTemplateManager();
        $this->load_depandencies();

        // Register shortcodes
        add_action('init', [$this, 'register_shortcodes']);

        add_action('init', [KCPaymentGatewayFactory::class, 'init']);

        add_action('init', [KCRestAPI::class, 'get_instance']);

        add_action('init', [KCTelemedFactory::class, 'init']);
        add_action('init', [AppointmentsController::class, 'initAutoCloseAppointmentCron']);

        add_action('rest_api_init', [KCPatientControllerFilters::class, 'get_instance']);
        add_action('rest_api_init', [KCDoctorControllerFilters::class, 'get_instance']);

        // Initialize the email notification system
        add_action('plugins_loaded', [KCEmailNotificationInit::class, 'get_instance']);

        // Initialize Gutenberg blocks
        add_action('init', [KCBlocksRegister::class, 'register']);

        // Initialize Elementor integration
        add_action('plugins_loaded', [$this, 'init_elementor_integration']);

        add_filter('authenticate', [$this, 'prevent_inactive_user_login'], 30, 3);
        
        // Check user status on every authenticated request (for already logged-in users)
        add_filter('determine_current_user', [$this, 'validate_current_user_status'], 99);

        // WooCommerce integration
        add_filter('woocommerce_rest_check_permissions', [$this, 'kc_patient_woocommerce_permissions'], 10, 4);
        add_filter('woocommerce_prevent_admin_access', [$this, 'allow_kivicare_report_media_upload_access'], 10, 1);

        
        // Register custom cron schedule for 5-minute intervals (more frequent checking)
        // Register the filter during WordPress 'init' so it's available at the right time.
        add_action('init', function () {
            add_filter('cron_schedules', function ($schedules) {
                if (!isset($schedules['every_five_minutes'])) {
                    $schedules['every_five_minutes'] = array(
                        'interval' => 300, // 5 minutes
                        'display' => __('Every 5 Minutes', 'kivicare-clinic-management-system')
                    );
                }
                return $schedules;
            });
        });

        // Redirect user to dashboard after login
		add_filter( 'login_redirect', [$this, 'kc_redirect_kivicare_user_to_dashboard'], 999, 3 );
        add_filter('logout_redirect', [$this, 'kc_redirect_kivicare_user_to_login'], 999, 3);



        // Restrict media library visibility
        add_filter('ajax_query_attachments_args', [$this, 'restrict_media_library']);

        // Force-disable admin bar for all KiviCare non-admin roles,
        // even if `edit_posts` is granted via User Role Editor or similar.
        add_filter('show_admin_bar', [$this, 'kc_hide_admin_bar_for_kc_roles'], 20);
    
        // Initialize media handler to consolidate KiviCare uploads
        KCMediaHandler::get_instance();
    
    }

    /**
     * Hides the WordPress admin bar for all KiviCare non-admin roles.
     */
    function kc_hide_admin_bar_for_kc_roles($show_admin_bar) {
        return ( current_user_can( 'administrator' ) ) ? $show_admin_bar : false;
    }



    /**
     * Redirects KiviCare users to their appropriate dashboard after login.
     *
     * This function hooks into the 'login_redirect' filter to customize the redirect URL
     * based on the user's role. It first checks for custom redirects configured in the
     * plugin settings, and falls back to role-based default dashboard URLs.
     *
     * @param string $redirect_to The default redirect URL provided by WordPress.
     * @param string $requested_redirect_to The URL the user originally requested to redirect to.
     * @param \WP_User|\WP_Error $user The user object or WP_Error if login failed.
     * @return string The final redirect URL after applying role-based logic and filters.
     */
    public function kc_redirect_kivicare_user_to_dashboard( $redirect_to, $requested_redirect_to, $user ) {
        if ( is_wp_error( $user ) ) {
            return $redirect_to;
        }
        $login_redirects = KCOption::get('login_redirect', []);
        $role = $this->kc_get_user_app_role($user);

        if (empty($role)) {
            return $redirect_to;
        }

        apply_filters('kc_login_redirect_role', $role, $user, KCDashboardPermalinkHandler::instance()->get_dashboard_url($role));
        // Check if a custom redirect is set for this role
        if (!empty($login_redirects[$role])) {
            error_log(apply_filters('kc_login_redirect_url', $login_redirects[$role], $role));
            return apply_filters('kc_login_redirect_url', $login_redirects[$role], $role);
        }
        // Default redirects based on role
        return apply_filters('kc_login_redirect_url', KCDashboardPermalinkHandler::instance()->get_dashboard_url($role) ?? home_url(), $role);
    }

    /**
     * Redirect KiviCare users to the application login page after logout.
     *
     * @param string $redirect_to Redirect URL.
     * @param string $requested_redirect_to Requested redirect URL.
     * @param \WP_User|null $user Logged-out user.
     * @return string
     */
    public function kc_redirect_kivicare_user_to_login($redirect_to, $requested_redirect_to, $user) {
        if (!$user instanceof \WP_User) {
            return $redirect_to;
        }

        $role = $this->kc_get_user_app_role($user);

        if (empty($role)) {
            return $redirect_to;
        }

        $logout_redirects = KCOption::get('logout_redirect', []);
		$allowed_roles = array_merge(['administrator'], KCBase::get_instance()->KCGetRoles());

        if (!in_array($role, $allowed_roles, true)) {
            return $redirect_to;
        }

        if (!empty($logout_redirects[$role]) && $this->kc_is_valid_application_logout_redirect($logout_redirects[$role])) {
            return apply_filters('kc_logout_redirect_url', $logout_redirects[$role], $role);
        }

        return apply_filters('kc_logout_redirect_url', $this->kc_get_application_login_url(), $role);
    }

    /**
     * Get the KiviCare application login/register page URL.
     *
     * @return string
     */
    public function kc_get_application_login_url() {
        return wp_login_url();
    }

    /**
     * Get the KiviCare application role for a user.
     *
     * @param \WP_User $user User object.
     * @return string
     */
    private function kc_get_user_app_role($user) {
        $allowed_roles = array_merge(['administrator'], KCBase::get_instance()->KCGetRoles());
        $roles = array_intersect($allowed_roles, (array) $user->roles);

        return !empty($roles) ? array_shift($roles) : '';
    }

    /**
     * Check whether a saved logout redirect should be honored.
     *
     * @param string $url Redirect URL.
     * @return bool
     */
    private function kc_is_valid_application_logout_redirect($url) {
        $path = wp_parse_url($url, PHP_URL_PATH);

        if (empty($path)) {
            return false;
        }

        $basename = basename(untrailingslashit($path));

        return $basename !== 'wp-login.php' && $basename !== 'wp-admin';
    }

    public function load_depandencies()
    {
        require_once KIVI_CARE_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
    }


    public function register_shortcodes()
    {
        $shortcodes = [
            KCBookAppointment::class,
            KCBookAppointmentButton::class,
            KCRegisterLogin::class,
            KCClinicListShortcode::class,
            KCDoctorListShortcode::class,
        ];

        foreach ($shortcodes as $shortcode_class) {
            new $shortcode_class();
        }
    }



    /**
     * Initialize Elementor integration
     * Checks if Elementor is active and hooks into proper events
     *
     * @return void
     */
    public function init_elementor_integration()
    {
        // Check if Elementor is installed and activated
        if (!did_action('elementor/loaded')) {
            return;
        }

        // Register custom category
        add_action('elementor/elements/categories_registered', [$this, 'register_elementor_category']);

        // Register widgets (Modern Elementor 3.5+ API)
        add_action('elementor/widgets/register', [$this, 'register_elementor_widgets']);
    }

    /**
     * Register KiviCare category in Elementor
     *
     * @param \Elementor\Elements_Manager $elements_manager
     * @return void
     */
    public function register_elementor_category($elements_manager)
    {
        $elements_manager->add_category(
            'kivicare',
            [
                'title' => __('KiviCare', 'kivicare-clinic-management-system'),
                'icon' => 'fa fa-heartbeat',
            ]
        );
    }

    /**
     * Register KiviCare widgets in Elementor
     * Uses modern Elementor 3.5+ API
     *
     * @param \Elementor\Widgets_Manager $widgets_manager
     * @return void
     */
    public function register_elementor_widgets($widgets_manager)
    {
        // Register widget classes using modern API
        $widgets_manager->register(new ClinicListWidget());
        $widgets_manager->register(new DoctorListWidget());
    }

    /**
     * Prevents inactive users from logging in.
     * Hooks into the 'authenticate' filter.
     *
     * @param \WP_User|\WP_Error|null $user
     * @param string $username
     * @param string $password
     * @return \WP_User|\WP_Error|null
     */
    public function prevent_inactive_user_login($user, $username, $password)
    {
        if (is_wp_error($user) || !$user instanceof \WP_User) {
            return $user;
        }

        // Check for inactive user account logic (existing)
        if (isset($user->data->user_status) && $user->data->user_status == 1) {
            return new \WP_Error(
                'kc_account_inactive',
                __('<strong>ERROR</strong>: Your account is inactive. Please contact the administrator.', 'kivicare-clinic-management-system')
            );
        }

        // New Logic: Check if Clinic Admin's Clinic is Inactive
        if (in_array(KCBase::get_instance()->getClinicAdminRole(), $user->roles)) {
            $clinic_id = KCClinic::getClinicIdOfClinicAdmin($user->ID);
            if (!empty($clinic_id)) {
                $clinic = KCClinic::find($clinic_id);
                // status 0 means Inactive as per KCClinic model
                if ($clinic && isset($clinic->status) && $clinic->status == 0) {
                    return new \WP_Error(
                        'kc_clinic_inactive',
                        __('<strong>ERROR</strong>: Your clinic is inactive. Please contact the administrator.', 'kivicare-clinic-management-system')
                    );
                }
            }
        }

        return $user;
    }

    /**
     * Validate current user status on every authenticated request
     * This runs AFTER authentication cookies are validated but BEFORE user is set
     * If user is inactive, we invalidate their authentication
     * 
     * @param int|false $user_id User ID if authenticated, false otherwise
     * @return int|false User ID if valid, false to invalidate authentication
     */
    public function validate_current_user_status($user_id)
    {
        // If no user is authenticated, nothing to check
        if (!$user_id || $user_id < 1) {
            return $user_id;
        }

        // Get user object
        $user = get_userdata($user_id);
        if (!$user) {
            return $user_id;
        }

        // Check if user account is inactive (user_status = 1 means inactive)
        if (isset($user->data->user_status) && $user->data->user_status == 1) {
            // Clear auth cookies to force logout
            // wp_clear_auth_cookie();
            $sessions = \WP_Session_Tokens::get_instance($user_id);
            $sessions->destroy_all();
            // Return false to prevent user from being authenticated
            return false;
        }

        // Check if Clinic Admin's Clinic is Inactive
        if (in_array(KCBase::get_instance()->getClinicAdminRole(), (array)$user->roles)) {
            $clinic_id = KCClinic::getClinicIdOfClinicAdmin($user_id);
            if (!empty($clinic_id)) {
                $clinic = KCClinic::find($clinic_id);
                // status 0 means Inactive as per KCClinic model
                if ($clinic && isset($clinic->status) && $clinic->status == 0) {
                    // Clear auth cookies to force logout
                    // wp_clear_auth_cookie();
                    $sessions = \WP_Session_Tokens::get_instance($user_id);
                    $sessions->destroy_all();
                    // Return false to prevent user from being authenticated
                    return false;
                }
            }
        }

        return $user_id;
    }

    /**
     * Restrict media library visibility to current user's uploads only.
     *
     * @param array $query
     * @return array
     */
    public function restrict_media_library($query)
    {
        $user_id = get_current_user_id();
        if ($user_id && KCBase::get_instance()->userHasKivicareRole($user_id)) {
            $query['author'] = $user_id;
        }
        return $query;
    }

    /**
     * Prevent WooCommerce from redirecting scoped KiviCare media uploads to My Account.
     *
     * WooCommerce blocks wp-admin for non-admin/non-shop-manager users. The WordPress
     * media modal uploads reports through wp-admin/async-upload.php, so allow only
     * KiviCare report/encounter media requests for users with upload permissions.
     *
     * @param bool $prevent Whether WooCommerce should prevent admin access.
     * @return bool
     */
    public function allow_kivicare_report_media_upload_access($prevent)
    {
        $user_id = get_current_user_id();

        if (!$user_id || !KCBase::get_instance()->userHasKivicareRole($user_id) || !current_user_can('upload_files')) {
            return $prevent;
        }

        return $this->is_kivicare_report_media_request() ? false : $prevent;
    }

    /**
     * Check whether the current request is a KiviCare report/encounter media request.
     */
    private function is_kivicare_report_media_request(): bool
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? basename(sanitize_text_field(wp_unslash($_SERVER['SCRIPT_NAME']))) : '';
        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';

        $is_media_endpoint = in_array($script, ['async-upload.php', 'media-upload.php'], true)
            || (defined('DOING_AJAX') && DOING_AJAX && in_array($action, ['upload-attachment', 'query-attachments', 'kc_upload_report'], true));

        if (!$is_media_endpoint) {
            return false;
        }

        $view_path = isset($_SERVER['HTTP_X_KC_VIEW_PATH']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_KC_VIEW_PATH'])) : '';
        $referer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';

        return strpos($view_path, 'medical-report') !== false
            || strpos($view_path, 'encounter') !== false
            || strpos($referer, 'medical-report') !== false
            || strpos($referer, 'encounter') !== false
            || $action === 'kc_upload_report';
    }

    /**
     * Grant scoped WooCommerce REST API access to authenticated patients.
     *
     * WC patient use-cases: read own orders (for online payment confirmation),
     * read products (to browse/book services). No write access. No cross-patient data.
     *
     * This filter runs AFTER WooCommerce has resolved OAuth / Application Password auth,
     * so get_current_user_id() is non-zero only when credentials are valid.
     *
     * @param bool   $permission  Current permission value from WC.
     * @param string $context     'read', 'create', 'edit', 'delete', 'batch'.
     * @param int    $object_id   Object being accessed (0 for collection requests).
     * @param string $post_type   WC post type (e.g., 'shop_order', 'product').
     * @return bool
     */
    public function kc_patient_woocommerce_permissions( $permission, $context, $object_id, $post_type ) {
        // Already permitted — don't interfere.
        if ( $permission ) {
            return $permission;
        }

        // Require an authenticated user. If OAuth/App-Password validation failed,
        // get_current_user_id() returns 0 and we deny immediately.
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }

        // Only apply to the patient role.
        $patient_role = KCBase::get_instance()->getPatientRole();
        if ( KCBase::get_instance()->getUserRoleById( $user_id ) !== $patient_role ) {
            return $permission;
        }

        // Patients get read-only access only.
        if ( $context !== 'read' ) {
            return false;
        }

        // Allowed endpoints: their own orders and publicly-readable products.
        switch ( $post_type ) {
            case 'shop_order':
                // Collection request (object_id = 0): WC automatically filters by
                // customer ID when ?customer=<id> is passed. We still grant permission
                // here; the controller enforces per-customer scoping via query args.
                if ( $object_id ) {
                    // Single-order access: enforce ownership.
                    $order = wc_get_order( $object_id );
                    if ( ! $order || (int) $order->get_customer_id() !== $user_id ) {
                        return false;
                    }
                }
                return true;

            case 'product':
                // Patients may read product listings (clinic services exposed as WC products).
                // No object-level ownership check needed — products are public catalog data.
                return true;
        }

        // Deny access to all other WC endpoints (customers, coupons, reports, webhooks, etc.).
        return false;
    }

}
