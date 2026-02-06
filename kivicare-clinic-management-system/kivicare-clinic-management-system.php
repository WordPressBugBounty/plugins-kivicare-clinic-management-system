<?php
/**
 * Plugin Name: KiviCare - Clinic & Patient Management System (EHR)
 * Plugin URI: https://kivicare.io
 * Description: KiviCare is an impressive clinic and patient management plugin (EHR). It comes with powerful shortcodes for appointment booking and patient registration.
 * Version: 4.0.4
 * Author: iqonic design
 * Text Domain: kivicare-clinic-management-system
 * Domain Path: /languages
 * Author URI: https://iqonic.design
 **/
use App\baseClasses\KCActivate;
use App\baseClasses\KCApp;

defined('ABSPATH') or die('Something went wrong');
// Require once the Composer Autoload
if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
	require_once dirname(__FILE__) . '/vendor/autoload.php';
} else {
	die('Something went wrong');
}


if (!defined('KIVI_CARE_DIR')) {
	define('KIVI_CARE_DIR', plugin_dir_path(__FILE__));
}

if (!defined('KIVI_CARE_DIR_URI')) {
	define('KIVI_CARE_DIR_URI', plugin_dir_url(__FILE__));
}

if (!defined('KIVI_CARE_BASE_NAME')) {
	define('KIVI_CARE_BASE_NAME', plugin_basename(__FILE__));
}

if (!defined('KIVI_CARE_PLUGIN_FILE')) {
	define('KIVI_CARE_PLUGIN_FILE', __FILE__);
}

if (!defined('KIVI_CARE_NAMESPACE')) {
	define('KIVI_CARE_NAMESPACE', "kivi-care");
}

if (!defined('KIVI_CARE_PREFIX')) {
	define('KIVI_CARE_PREFIX', "kiviCare_");
}

if (!defined('KIVI_CARE_VERSION')) {
	define('KIVI_CARE_VERSION', "4.0.4");
}

if (!defined('KIVI_CARE_API_VERSION')) {
	define('KIVI_CARE_API_VERSION', "9.6.8");
}

if (!defined('KIVI_CARE_NAME')) {
	define('KIVI_CARE_NAME', "kivicare");
}

/**
 * The code that runs during plugin activation
 */
register_activation_hook(__FILE__, [KCActivate::class, 'activate']);

(new KCApp)->init();

/**
 * Check and update plugin version on init to ensure all WP core functions 
 * (like wp_insert_post) and rewrite globals are available.
 */
add_action('init', function () {
	$stored_version = get_option(KIVI_CARE_PREFIX . 'version');
	if ($stored_version !== KIVI_CARE_VERSION) {
		KCActivate::activate();
		update_option(KIVI_CARE_PREFIX . 'version', KIVI_CARE_VERSION);
	}
}, 1);

/**
 * Handle KiviCare Addon compatibility
 */
add_action('plugins_loaded', function () {
	if (is_admin()) {
		if (!function_exists('is_plugin_active')) {
			require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		}

		$addons = [
			'kivicare-pro/kivicare-clinic-management-system-pro.php' => [
				'name' => 'KiviCare Pro',
				'version' => '4.0.0',
				'constant' => 'KIVI_CARE_PRO_VERSION'
			],
			'kivicare-body-chart-addon/kivicare-body-chart.php' => [
				'name' => 'KiviCare Body Chart Addon',
				'version' => '4.0.0',
				'constant' => 'KIVICARE_BODYCHART_ADDON_VERSION'
			],
			'kivicare-google-meet/kivicare-googlemeet.php' => [
				'name' => 'KiviCare Google Meet Addon',
				'version' => '4.0.0',
				'constant' => 'KIVICARE_GOOGLE_MEET_ADDON_VERSION'
			],
			'kivicare-razorpay-addon/kivicare-razorpay-addon.php' => [
				'name' => 'KiviCare Razorpay Addon',
				'version' => '4.0.0',
				'constant' => 'KIVI_CARE_RAZORPAY_VERSION'
			],
			'kivicare-stripe-addon/kivicare-stripepay-addon.php' => [
				'name' => 'KiviCare Stripe Addon',
				'version' => '4.0.0',
				'constant' => 'KIVI_CARE_STRIPE_ADDON_VERSION'
			],
			'kivicare-telemed-addon/kivicare-telemed-addon.php' => [
				'name' => 'KiviCare Telemed Addon',
				'version' => '4.0.0',
				'constant' => 'KIVICARE_TELEMED_VERSION'
			],
			'kivicare-webhook-addon/kivicare-webhooks-addon.php' => [
				'name' => 'KiviCare Webhooks Addon',
				'version' => '4.0.0',
				'constant' => 'KIVICARE_WEBHOOKS_ADDON_VERSION'
			],
		];

		foreach ($addons as $path => $data) {
			if (is_plugin_active($path)) {
				$version = defined($data['constant']) ? constant($data['constant']) : '0.0.0';
				if ($version === '0.0.0' && file_exists(WP_PLUGIN_DIR . '/' . $path)) {
					$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $path);
					$version = $plugin_data['Version'];
				}

				if (version_compare($version, $data['version'], '<')) {
					deactivate_plugins($path);
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if (isset($_GET['activate']))
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						unset($_GET['activate']);
					add_action('admin_notices', function () use ($data) {
						?>
						<div class="notice notice-error is-dismissible">
							<p><?php
								/* translators: 1: Plugin Name, 2: Version number */
								printf( wp_kses( __('<strong>%1$s</strong> has been deactivated. Version %2$s or higher is required for compatibility with KiviCare Lite 4.0.0.', 'kivicare-clinic-management-system'),
										['strong' => []]
									),	
									esc_html($data['name']),
									esc_html($data['version'])
								); ?>
							</p>
						</div>
						<?php
					});
				}
			}
		}
	}
}, 1);

do_action('kivicare_loaded');