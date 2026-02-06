<?php

use App\baseClasses\KCPermissions;
use App\models\KCOption;

/**
 * KiviCare Dashboard Template
 * 
 * This template renders the main dashboard for KiviCare clinic management system
 * 
 * @package KiviCare
 * @version 3.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!KCPermissions::has_permission('dashboard')) {
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'kivicare-clinic-management-system'), esc_html__('Access Denied', 'kivicare-clinic-management-system'), array('response' => 403));
}

// Get dashboard configuration from globals or set defaults
$dashboard_type = isset($GLOBALS['kc_dashboard_type']) ? $GLOBALS['kc_dashboard_type'] : 'patient';
$dashboard_action = isset($GLOBALS['kc_dashboard_action']) ? $GLOBALS['kc_dashboard_action'] : '';
$current_user_role = isset($GLOBALS['kc_current_user_role']) ? $GLOBALS['kc_current_user_role'] : 'patient';

// Dashboard configuration
$dashboard_config = apply_filters('kivicare_dashboard_config', array(
    'theme' => get_option('kivicare_dashboard_theme', 'dark'),
    'layout' => get_option('kivicare_dashboard_layout', 'vertical'),
    'sidebar_type' => get_option('kivicare_sidebar_type', 'sidebar-mini'),
    'navbar_color' => get_option('kivicare_navbar_color', 'bg-primary'),
    'sidebar_color' => get_option('kivicare_sidebar_color', 'sidebar-white'),
    'sidebar_active_style' => get_option('kivicare_sidebar_active_style', 'rounded-pill'),
    'enable_rtl' => get_option('kivicare_enable_rtl', false),
    'enable_dark_mode' => get_option('kivicare_enable_dark_mode', false),
    'fluid_layout' => get_option('kivicare_fluid_layout', false),
    'dashboard_type' => $dashboard_type,
    'user_role' => $current_user_role,
));

?>
<!DOCTYPE html>
<?php
$theme_mode = KCOption::get('theme_mode', 'false');
$is_rtl_language = is_rtl();

if ($theme_mode == 'true') {
    $is_rtl = true;
    $dir = 'rtl';
} else {
    $is_rtl = $is_rtl_language;
    $dir = $is_rtl_language ? 'rtl' : 'ltr';
}
?>
<html <?php language_attributes(); ?> dir="<?php echo esc_attr($dir); ?>">

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">

    <!-- Security Headers -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">

    <title>
        <?php echo esc_html(apply_filters('kivicare_dashboard_title', __('KiviCare Dashboard', 'kivicare-clinic-management-system'), $current_user_role)); ?>
    </title>

    <?php // Hook for additional head content
    wp_head();


    do_action('kivicare_dashboard_head', $dashboard_config); ?>



</head>

<body data-bs-theme="light" <?php body_class('kivicare-dashboard') ?>>

    <!-- React Dashboard Mount Point -->
    <div id="kc-dashboard">

       
    </div>
    <style id="kivicare-dashboard-colors-style">
        [data-bs-theme="light"],
        :root {
            <?php
            $generatedColors = KCOption::get('generated_colors', []);
            if (!empty($generatedColors) && is_array($generatedColors)) {
                foreach ($generatedColors as $property => $value) {
                    echo esc_attr($property) . ': ' . esc_attr($value) . ';' . PHP_EOL . '                ';
                }
            } else {
                // Fallback to default colors if generated colors are not available
                ?>
                --bs-primary:
                    <?php echo esc_attr(KCOption::get('primary_color', '#5670CC')); ?>
                ;
                --bs-secondary:
                    <?php echo esc_attr(KCOption::get('secondary_color', '#f68685')); ?>
                ;
                --bs-success:
                    <?php echo esc_attr(KCOption::get('success_color', '#219653')); ?>
                ;
                --bs-warning:
                    <?php echo esc_attr(KCOption::get('warning_color', '#FAA100')); ?>
                ;
                --bs-danger:
                    <?php echo esc_attr(KCOption::get('danger_color', '#F54438')); ?>
                ;
                --bs-info:
                    <?php echo esc_attr(KCOption::get('info_color', '#007EA7')); ?>
                ;
                --bs-body-bg:
                    <?php echo esc_attr(KCOption::get('body_bg', '#f5f6fa')); ?>
                ;
                --bs-body-color:
                    <?php echo esc_attr(KCOption::get('body_color', '#828A90')); ?>
                ;
                --bs-border-color:
                    <?php echo esc_attr(KCOption::get('border_color', '#dbdfe7')); ?>
                ;
                --bs-heading-color:
                    <?php echo esc_attr(KCOption::get('heading_color', '#3F414D')); ?>
                ;
                --bs-card-color:
                    <?php echo esc_attr(KCOption::get('card_color', '#ffffff')); ?>
                ;
                --bs-theme-color:
                    <?php echo esc_attr(KCOption::get('theme_color', '#007bff')); ?>
                ;
            <?php } ?>
        }
    </style>

    <?php
    // Hook for additional body content
    do_action('kivicare_dashboard_footer', $dashboard_config);

    // WordPress footer
    wp_footer();

    // Final hook for cleanup
    do_action('kivicare_dashboard_end');
    ?>

</body>

</html>