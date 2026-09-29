<?php

defined('WP_UNINSTALL_PLUGIN') or die('Something went wrong');

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
	require_once __DIR__ . '/vendor/autoload.php';
}

if (!defined('KIVI_CARE_VERSION')) {
	define('KIVI_CARE_VERSION', '4.5.6');
}

if (!defined('KIVI_CARE_PULSE_API_URL')) {
	define('KIVI_CARE_PULSE_API_URL', 'https://tracker-wordpress.iqonic.design/wp-json');
}

if (class_exists('\\App\\services\\KCPulse\\KCPulseTracker')) {
	(new \App\services\KCPulse\KCPulseTracker(
		'kivicare-clinic-management-system',
		KIVI_CARE_VERSION,
		__FILE__
	))->on_uninstall();
}
