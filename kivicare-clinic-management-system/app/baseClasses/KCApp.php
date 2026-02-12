<?php

namespace App\baseClasses;

use App\admin\AdminMenu;
use App\admin\KCDashboardPermalinkHandler;
use App\controllers\KCRestAPI;
use App\controllers\filters\KCDoctorControllerFilters;
use App\controllers\filters\KCPatientControllerFilters;
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

        // Initialize REST API
        new KCEmailTemplateManager();
        $this->load_depandencies();

        // Register shortcodes
        add_action('init', [$this, 'register_shortcodes']);

        add_action('init', [KCPaymentGatewayFactory::class, 'init']);

        add_action('init', [KCRestAPI::class, 'get_instance']);

        add_action('init', [KCTelemedFactory::class, 'init']);

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
        add_filter('woocommerce_rest_check_permissions', function ($permission) {

            if (isset($_SERVER['HTTP_APP_VERSION'])) {

                $app_version = sanitize_text_field( wp_unslash( $_SERVER['HTTP_APP_VERSION'] ) ) ?? '';

                // Compare with your constant
                if (defined('KIVI_CARE_API_VERSION') && $app_version === KIVI_CARE_API_VERSION) {
                    return true;
                }
            }

            return $permission;
        });
        
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

        add_filter('init',[$this,'kivicare_migrate_apt_booking_steps']);

        // Restrict media library visibility
        add_filter('ajax_query_attachments_args', [$this, 'restrict_media_library']);
    }

    public function kivicare_migrate_apt_booking_steps(){
        if (empty(get_option(KIVI_CARE_PREFIX.'is_appointment_widget_migrated'))) {
            // Migrate appointment widget order if needed
            $widget_order_list = get_option(KIVI_CARE_PREFIX . 'widget_order_list');
            if (!empty($widget_order_list)) {
                $detail_info_index = -1;
                $file_uploads_custom_index = -1;
                foreach ($widget_order_list as $index => $step) {
                    if ($step['att_name'] === 'detail-info') {
                        $detail_info_index = $index;
                    } elseif ($step['att_name'] === 'file-uploads-custom') {
                        $file_uploads_custom_index = $index;
                    }
                }

                if ($detail_info_index !== -1 && $file_uploads_custom_index !== -1 && $file_uploads_custom_index < $detail_info_index) {
                    $file_uploads_custom_step = $widget_order_list[$file_uploads_custom_index];
                    unset($widget_order_list[$file_uploads_custom_index]);
                    $widget_order_list = array_values($widget_order_list);

                    // Re-find detail-info index as it might have shifted
                    foreach ($widget_order_list as $index => $step) {
                        if ($step['att_name'] === 'detail-info') {
                            $detail_info_index = $index;
                            break;
                        }
                    }

                    array_splice($widget_order_list, $detail_info_index + 1, 0, [$file_uploads_custom_step]);
                    update_option(KIVI_CARE_PREFIX . 'widget_order_list', $widget_order_list);
                }
            }
            update_option(KIVI_CARE_PREFIX.'is_appointment_widget_migrated', 'yes');
        }
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
        $role = $user->roles[0] ?? '';

        apply_filters('kc_login_redirect_role', $role, $user, KCDashboardPermalinkHandler::instance()->get_dashboard_url($role));
        // Redirect admin to WordPress dashboard by default, but allow customization via filter
        if($role == 'administrator'){
            return apply_filters('kc_login_redirect_admin', home_url('wp-admin'), $user);
        }
        // Check if a custom redirect is set for this role
        if (!empty($login_redirects[$role])) {
            error_log(apply_filters('kc_login_redirect_url', $login_redirects[$role], $role));
            return apply_filters('kc_login_redirect_url', $login_redirects[$role], $role);
        }
        // Default redirects based on role
        return apply_filters('kc_login_redirect_url', KCDashboardPermalinkHandler::instance()->get_dashboard_url($role) ?? home_url(), $role);
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
}
