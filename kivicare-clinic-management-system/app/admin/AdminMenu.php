<?php
namespace App\admin;

use App\baseClasses\KCBase;
use function Iqonic\Vite\iqonic_enqueue_asset;

defined('ABSPATH') or die('Something went wrong');

class AdminMenu
{
    /**
     * Dashboard permalink handler instance
     */
    private $permalink_handler;

    public function register()
    {
        add_action('admin_menu', [$this, 'addMenuItems']);

        // Initialize permalink handler
        $this->permalink_handler = KCDashboardPermalinkHandler::instance();
    }

    public function addMenuItems()
    {
        $dashboard_url = $this->permalink_handler->get_menu_kivicare_url(KCBase::get_instance()->getLoginUserRole());
        if (empty($dashboard_url)) {
            add_menu_page(
                'KiviCare Dashboard',
                __('KiviCare','kivicare-clinic-management-system'),
                'read',
                'dashboard',
                [$this, 'renderDashboard'],
                KIVI_CARE_DIR_URI . 'assets/images/sidebar-logo.svg',
                99
            );
        } else {
            add_menu_page(
                'KiviCare Dashboard',
                __('KiviCare','kivicare-clinic-management-system'),
                'read',
                $dashboard_url,
                '',
                KIVI_CARE_DIR_URI . 'assets/images/sidebar-logo.svg',
                99
            );
        }
    }

    public function renderDashboard()
    {
        // Initialize permalink handler if not already done
        if (!$this->permalink_handler) {
            $this->permalink_handler = KCDashboardPermalinkHandler::instance();
        }

        // Check if this is a permalink-based dashboard request
        if ($this->permalink_handler->is_dashboard_request()) {
            // Let the permalink handler manage the template
            return;
        }

        // Legacy admin dashboard rendering
        $this->enqueue_dashboard_assets();
        echo '<div id="kc-dashboard"></div>';
    }
    /**
     * Enqueue dashboard assets
     */
    private function enqueue_dashboard_assets()
    {
        // Enqueue the script and style for Media Uploader
        wp_enqueue_media();

        iqonic_enqueue_asset(
            KIVI_CARE_DIR . '/dist',
            'app/dashboard/main.jsx',
            [
                'handle' => 'kc-dashboard',
                'dependencies' => apply_filters('kc_dashboard_script_dependencies', ['wp-i18n', 'wp-hooks']), // Optional script dependencies. Defaults to empty array.
                'css-dependencies' => [], // Optional style dependencies. Defaults to empty array.
                'css-media' => 'all', // Optional.
                'css-only' => false, // Optional. Set to true to only load style assets in production mode.
                'in-footer' => false, // Optional. Defaults to false.
            ]
        );

        // Get JED locale data and add it inline
        $locale_data_kc = kc_get_jed_locale_data('kivicare-clinic-management-system');

        add_action('admin_footer', function () {
            echo '<style>
            #wpcontent, #footer { margin-left: 0px !important;padding-left: 0px !important; }
            html.wp-toolbar { padding-top: 0px !important; }
            #adminmenuback, #adminmenuwrap, #wpadminbar, #wpfooter,#adminmenumain, #screen-meta { display: none !important; }
            #wpcontent .notice { display:none; }
            </style>';
        });

        wp_localize_script('kc-dashboard', 'kc_frontend', [
            'rest_url' => rest_url(),
            'nonce' => wp_create_nonce('wp_rest'),
            'locale_data' => $locale_data_kc,
            'prefix' => KIVI_CARE_PREFIX,
            'loader_image' => KIVI_CARE_DIR_URI . 'assets/images/loader.gif',
            'date_format' => get_option('date_format'),
        ]);
    }
}