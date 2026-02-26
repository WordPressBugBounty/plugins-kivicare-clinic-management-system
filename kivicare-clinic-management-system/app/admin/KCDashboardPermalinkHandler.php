<?php

namespace App\admin;

use App\baseClasses\KCBase;
use App\baseClasses\KCPermissions;
use App\models\KCOption;
use function Iqonic\Vite\iqonic_enqueue_asset;

defined('ABSPATH') || exit;

/**
 * KiviCare Dashboard Permalink Handler
 * 
 * Handles custom dashboard routes for different user roles
 * 
 * @package KiviCare
 * @version 3.0.0
 */
class KCDashboardPermalinkHandler
{
    /**
     * The single instance of the class.
     *
     * @var KCDashboardPermalinkHandler
     */
    protected static $instance = null;

    /**
     * Dashboard routes configuration
     */
    private $dashboard_routes = [];

    public function __construct()
    {
        add_filter('rewrite_rules_array', [$this, 'add_dashboard_rewrite_rules']);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_filter('template_include', [$this, 'handle_template_include'], 99);
        add_action('init', [$this, 'add_permalink_tags']);


        // Get roles from KCBase and transform into dashboard routes
        $roles = array_merge(KCBase::get_instance()->KCGetRoles(), [
            'administrator'
        ]);
        $this->dashboard_routes = [];

        $pro_slugs = KCOption::getMultiple([
            'dashboard_slug_admin',
            'dashboard_slug_clinic_admin',
            'dashboard_slug_doctor',
            'dashboard_slug_receptionist',
            'dashboard_slug_patient',
        ]);
        foreach ($roles as $role) {
            // Remove 'kiviCare_' prefix and use as both key and value
            $clean_role = str_replace(KIVI_CARE_PREFIX, '', $role);

            // Map the clean role to pro slug option key
            $pro_slug_key = match ($clean_role) {
                'administrator' => 'dashboard_slug_admin',
                'clinic_admin' => 'dashboard_slug_clinic_admin',
                'doctor' => 'dashboard_slug_doctor',
                'receptionist' => 'dashboard_slug_receptionist',
                'patient' => 'dashboard_slug_patient',
            };

            // Use pro slug if available, otherwise use default
            if (!empty($pro_slug_key) && !empty($pro_slugs[$pro_slug_key])) {
                $this->dashboard_routes[$role] = $pro_slugs[$pro_slug_key];
            } else {
                $this->dashboard_routes[$role] = 'kivicare-' . $clean_role . '-dashboard';
            }
        }

    }

    /**
     * Get singleton instance
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add dashboard rewrite rules
     */
    public function add_dashboard_rewrite_rules($rules)
    {
        $roles = array_merge(KCBase::get_instance()->KCGetRoles(), ['administrator']);
        $dashboard_routes = [];
        $pro_slugs = KCOption::getMultiple([
            'dashboard_slug_admin',
            'dashboard_slug_clinic_admin',
            'dashboard_slug_doctor',
            'dashboard_slug_receptionist',
            'dashboard_slug_patient',
        ]);

        foreach ($roles as $role) {
            $clean_role = str_replace(KIVI_CARE_PREFIX, '', $role);
            $pro_slug_key = match ($clean_role) {
                'administrator' => 'dashboard_slug_admin',
                'clinic_admin' => 'dashboard_slug_clinic_admin',
                'doctor' => 'dashboard_slug_doctor',
                'receptionist' => 'dashboard_slug_receptionist',
                'patient' => 'dashboard_slug_patient',
                default => ''
            };

            if (!empty($pro_slug_key) && !empty($pro_slugs[$pro_slug_key])) {
                $dashboard_routes[$role] = $pro_slugs[$pro_slug_key];
            } else {
                $dashboard_routes[$role] = 'kivicare-' . $clean_role . '-dashboard';
            }
        }

        $new_rules = [];
        foreach ($dashboard_routes as $route => $slug) {
            if (empty($slug))
                continue;

            $new_rules[$slug . '(?:/(.*))?/?$'] = 'index.php?kc_dashboard_type=' . $route . '&kc_dashboard_path=$matches[1]';
        }
        return $new_rules + $rules; // Prepend our rules
    }

    /**
     * Add permalink tags
     */
    public function add_permalink_tags()
    {
        add_rewrite_tag('%kc_dashboard_type%', '([^&]+)');
        add_rewrite_tag('%kc_dashboard_action%', '([^&]+)');
        add_rewrite_tag('%kc_dashboard_path%', '(.+?)');
    }

    /**
     * Register custom query variables
     */
    public function register_query_vars($vars)
    {
        $vars[] = 'kc_dashboard_type';
        $vars[] = 'kc_dashboard_action';
        $vars[] = 'kc_dashboard_path';  // Add this new query var
        return $vars;
    }

    /**
     * Handle template include
     */
    public function handle_template_include($template)
    {

        global $wp, $wp_query;

        $dashboard_type = get_query_var('kc_dashboard_type');
        $dashboard_action = get_query_var('kc_dashboard_action');
        $dashboard_path = get_query_var('kc_dashboard_path');  // Get the full path

        // Check if this is a KiviCare dashboard request
        if (!empty($dashboard_type) && in_array($dashboard_type, array_keys($this->dashboard_routes))) {

            // Verify user permissions for the requested dashboard type
            if (!$this->verify_dashboard_access($dashboard_type)) {
                // Redirect to login or show access denied
                if (!is_user_logged_in()) {
                    wp_safe_redirect(wp_login_url(home_url($wp->request)));
                    exit;
                } else {
                    wp_die(esc_html__('You do not have sufficient permissions to access this dashboard.', 'kivicare-clinic-management-system'), esc_html__('Access Denied', 'kivicare-clinic-management-system'), ['response' => 403]);
                }
            }

            // Set up global variables for the template
            $GLOBALS['kc_dashboard_type'] = $dashboard_type;
            $GLOBALS['kc_dashboard_action'] = $dashboard_action;
            $GLOBALS['kc_dashboard_path'] = $dashboard_path;  // Make path available to template
            $GLOBALS['kc_current_user_role'] = KCBase::get_instance()->KCGetRoles();

            // Prevent 404
            $wp_query->is_404 = false;
            status_header(200);

            // Remove admin bar inline CSS
            add_filter('show_admin_bar', '__return_false');
            remove_action('wp_head', '_admin_bar_bump_cb');

            // Enqueue dashboard assets
            add_action('wp_enqueue_scripts', function () use ($dashboard_type) {
                global $wp_styles, $wp_scripts;

                $allowed_styles = apply_filters('kc_allowed_styles', ['kc-dashboard-style', 'wp-admin', 'wp-components', 'dashicons', 'thickbox', 'imgareaselect', 'media-views', 'media-editor']);
                // fix: added 'jquery-ui-core' to prevent deregistration conflict with Qi Addons for Elementor
                $allowed_scripts = apply_filters('kc_allowed_scripts', ['kc-dashboard-script', 'heartbeat', 'wp-util', 'wp-api-request', 'underscore', 'backbone', 'clipboard', 'media-views', 'media-editor', 'media-models', 'thickbox', 'imgareaselect', 'jquery-ui-core']);

                foreach ($wp_styles->queue as $handle) {
                    if (
                        !in_array($handle, $allowed_styles) &&
                        strpos($handle, 'wp-') !== 0 &&
                        strpos($handle, 'media-') !== 0
                    ) {
                        wp_dequeue_style($handle);
                        wp_deregister_style($handle);
                    }
                }

                foreach ($wp_scripts->queue as $handle) {

                    if (
                        !in_array($handle, $allowed_scripts) &&
                        strpos($handle, 'wp-') !== 0 &&
                        strpos($handle, 'media-') !== 0  &&
                        $handle !== 'jquery' &&
                        $handle !== 'jquery-core' &&
                        $handle !== 'jquery-migrate'
                    ) {
                        wp_dequeue_script($handle);
                        wp_deregister_script($handle);
                    }
                }


                // Now enqueue only your needed assets
                $this->enqueue_dashboard_assets($dashboard_type);
            }, 999); // Priority 999 ensures this runs after everyone else


            // Return the KiviCare dashboard template
            $dashboard_template = KIVI_CARE_DIR . '/templates/html-kc-dashboard.php';

            if (file_exists($dashboard_template)) {
                add_filter('heartbeat_settings', function ($settings) {
                    $settings['interval'] = 5; // seconds
                    return $settings;
                });
                switch_to_locale(get_user_locale());

                return $dashboard_template;
            }
        }

        return $template;
    }

    /**
     * Verify if current user has access to the requested dashboard
     */
    private function verify_dashboard_access($dashboard_type)
    {
        if (!is_user_logged_in()) {
            return false;
        }
        // Check role-based access
        return match ($dashboard_type) {
            'administrator' => current_user_can('administrator') || KCPermissions::has_permission('administrator_dashboard'),
            KCBase::get_instance()->getClinicAdminRole() => KCPermissions::has_permission('clinic_admin_dashboard'),
            KCBase::get_instance()->getReceptionistRole() => KCPermissions::has_permission('receptionist_dashboard'),
            KCBase::get_instance()->getDoctorRole() => KCPermissions::has_permission('doctor_dashboard'),
            KCBase::get_instance()->getPatientRole() => KCPermissions::has_permission('patient_dashboard'),
            default => false,
        };
    }

    /**
     * Get current user's KiviCare role
     */
    private function get_user_role()
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $current_user = wp_get_current_user();

        // Check KiviCare specific role first
        $kc_role = get_user_meta($current_user->ID, 'kivicare_user_role', true);
        if (!empty($kc_role)) {
            return $kc_role;
        }

        // Fallback to WordPress roles
        if (current_user_can('administrator')) {
            return 'administrator';
        }

        // Default role based on capabilities
        if (KCPermissions::has_permission('clinic_admin_dashboard')) {
            return 'clinic_admin';
        } elseif (KCPermissions::has_permission('doctor_dashboard')) {
            return 'doctor';
        } elseif (KCPermissions::has_permission('receptionist_dashboard')) {
            return 'receptionist';
        } elseif (KCPermissions::has_permission('patient_dashboard')) {
            return 'patient';
        }

        return 'patient'; // Default role
    }

    /**
     * Enqueue dashboard assets based on dashboard type
     */
    private function enqueue_dashboard_assets($dashboard_type)
    {
        // Enqueue media uploader
        wp_enqueue_media();



        // Use Vite to enqueue the main dashboard script
        if (function_exists(function: '\Iqonic\Vite\iqonic_enqueue_asset')) {
            // It's conflict with "speed optimization" plugin so we add prefix to handle
            $dependencies = ['wp-i18n', 'wp-hooks'];
            if (wp_script_is('heartbeat', 'registered')) {
                $dependencies[] = 'heartbeat';
            }

            iqonic_enqueue_asset(
                KIVI_CARE_DIR . '/dist',
                'app/dashboard/main.jsx',
                [
                    'handle' => 'kc-dashboard-' . $dashboard_type,
                    'dependencies' => apply_filters('kc_dashboard_script_dependencies', $dependencies),
                    'css-dependencies' => [],
                    'css-media' => 'all',
                    'css-only' => false,
                    'in-footer' => false,
                ]
            );
        }

        // Get JED locale data
        $locale_data_kc = function_exists('kc_get_jed_locale_data') ? kc_get_jed_locale_data('kivicare-clinic-management-system') : [];

        // Prepare dashboard data array
        $dashboard_data = [
            'rest_url' => rest_url(),
            'nonce' => wp_create_nonce('wp_rest'),
            'locale_data' => $locale_data_kc,
            'prefix' => defined('KIVI_CARE_PREFIX') ? KIVI_CARE_PREFIX : 'kc_',
            'loader_image' => (defined('KIVI_CARE_DIR_URI') ? KIVI_CARE_DIR_URI : '') . 'assets/images/loader.gif',
            'site_logo' => !empty(KCOption::get('site_logo')) ? wp_get_attachment_url(KCOption::get('site_logo')) : '',
            'dashboard_type' => $dashboard_type,
            'user_role' => KCBase::get_instance()->KCGetRoles(),
            'dashboard_url' => wp_make_link_relative($this->get_dashboard_url($dashboard_type)),
            'dashboard_uri' => $this->get_dashboard_url($dashboard_type),
            'api_url' => rest_url('kivicare/v1/'),
            'admin_url' => KCBase::get_instance()->getLoginUserRole() === 'administrator' ? admin_url() : '',
            'date_format' => get_option('date_format'),
            'start_of_week' => get_option('start_of_week'),
        ];
        // Apply filter to dashboard data
        $dashboard_data = apply_filters('kivicare_dashboard_data', $dashboard_data);

        // Localize script with dashboard data
        wp_localize_script('kc-dashboard-' . $dashboard_type, 'kc_frontend', $dashboard_data);
    }

    /**
     * Get the menu URL for a specific dashboard type
     */
    public function get_menu_kivicare_url($dashboard_type)
    {
        $home_url = $this->get_dashboard_url($dashboard_type);
        $path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH) : '';
        $segments = explode('/', trim($path, '/'));
        if (
            function_exists('kcGetSetupWizardOptions') &&
            is_array(kcGetSetupWizardOptions()) &&
            KCBase::get_instance()->getLoginUserRole() === 'administrator' &&
            !in_array('clinic-setup', $segments)
        ) {
            $setupClinicStep = [
                "getting_started" => 'clinic-setup',
                "clinic" => 'clinic-setup/basic-info',
                "clinic_admin" => 'clinic-setup/admin-info',
            ];
            $setup_config = kcGetSetupWizardOptions();
            foreach ($setup_config as $step) {
                if (isset($step['completed']) && $step['completed'] === false) {
                    if (isset($setupClinicStep[$step['name']])) {
                        return $home_url . '/' . $setupClinicStep[$step['name']];
                    }
                }
            }
        }
        return $home_url;
    }

    /**
     * Get dashboard URL for a specific type with optional path
     */
    public function get_dashboard_url($dashboard_type)
    {
        $route = $this->dashboard_routes[$dashboard_type] ?? '';
        if (!$route) {
            return home_url();
        }

        $permalink_structure = get_option('permalink_structure');

        if (empty($permalink_structure)) {
            // Plain permalinks
            return add_query_arg('kc_dashboard_type', $dashboard_type, home_url('/'));
        }

        $has_index_php = strpos($permalink_structure, '/index.php') !== false;

        if ($has_index_php) {
            return home_url('/index.php/' . $route);
        }

        return home_url('/' . $route);
    }


    /**
     * Get all dashboard routes
     */
    public function get_dashboard_routes()
    {
        return $this->dashboard_routes;
    }

    /**
     * Check if current request is for a KiviCare dashboard
     */
    public function is_dashboard_request()
    {
        return !empty(get_query_var('kc_dashboard_type'));
    }

    /**
     * Get current dashboard type
     */
    public function get_current_dashboard_type()
    {
        return get_query_var('kc_dashboard_type');
    }

    /**
     * Redirect user to their appropriate dashboard
     */
    public function redirect_to_user_dashboard()
    {
        $user_role = KCBase::get_instance()->KCGetRoles();
        $dashboard_url = $this->get_dashboard_url($user_role);
        if ($dashboard_url) {
            wp_safe_redirect($dashboard_url);
            exit;
        }
    }
    /**
     * Flushes the WordPress rewrite rules.
     *
     * This static method accesses the global $wp_rewrite object and calls its
     * flush_rules() method to regenerate the rewrite rules. This is typically
     * used when custom permalinks or rewrite rules have been added or modified,
     * ensuring that WordPress recognizes the changes.
     *
     * @return void
     */
    public static function flush_rewrite_rules()
    {
        global $wp_rewrite;
        $wp_rewrite->flush_rules(false); // Do not reset permalink structure
    }
}
