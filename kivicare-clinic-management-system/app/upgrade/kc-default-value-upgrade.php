<?php

use App\baseClasses\KCBase;
use App\baseClasses\KCActivate;

if ( is_admin() && !get_option( 'is_upgrade_2.0.0')) {

    add_option('is_read_change_log', 0);

    // Add New Fields
    require KIVI_CARE_DIR . 'app/database/kc-clinic-db.php';
    require KIVI_CARE_DIR . 'app/database/kc-bill-db.php';
    require KIVI_CARE_DIR . 'app/database/kc-custom-field-db.php';

    // New Table
    require KIVI_CARE_DIR . 'app/database/kc-appointment-service-mapping-db.php';
    require KIVI_CARE_DIR . 'app/database/kc-service-doctor-mapping-db.php';

    // Doctor service mapping
    require KIVI_CARE_DIR . 'app/upgrade/kc-service-doctor-entry.php';
    require KIVI_CARE_DIR . 'app/upgrade/kc-wp-post-entry.php';
    require KIVI_CARE_DIR . 'app/upgrade/kc-telemed-entry.php' ;

    add_option('is_upgrade_2.0.0', 1);

    (new KCActivate())->migratePermissions();

}

if ( is_admin() && !get_option( 'is_upgrade_2.0.1')) {
			
    require_once( ABSPATH . "wp-includes/pluggable.php" );

    $prefix = KIVI_CARE_PREFIX;

    $mail_template = $prefix.'mail_tmp' ;

    $default_email_template = [
        [
            'post_name' => $prefix.'doctor_book_appointment',
            'post_content' => '<p> New appointment </p><p> Your have new appointment on </p><p> Date: {{appointment_date}}  , Time : {{appointment_time}} ,Patient : {{patient_name}} </p><p> Thank you. </p>',
            'post_title' => 'Doctor Booked Appointment Template',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
    ];

    foreach ($default_email_template as $email_template) {
        wp_insert_post($email_template) ;
    }

    add_option('is_upgrade_2.0.1', 1);

    (new KCActivate)->migratePermissions();

}

if ( is_admin() && !get_option( 'is_upgrade_2.0.2')) {
			
    require_once( ABSPATH . "wp-includes/pluggable.php" );

    $prefix = KIVI_CARE_PREFIX;

    $mail_template = $prefix.'mail_tmp' ;

    $default_email_template = [
        'post_name' => $prefix.'zoom_link',
        'post_content' => '<p> Zoom video conference </p><p> Your have new appointment on </p><p> Date: {{appointment_date}}  , Time : {{appointment_time}} ,Patient : {{patient_name}} , Zoom Link : {{zoom_link}} </p><p> Thank you. </p>',
        'post_title' => 'Video Conference appointment Template',
        'post_type' => $mail_template,
        'post_status' => 'publish',
    ];

    if ( get_page_by_title( $default_email_template['post_title'] ) == null ) {

        wp_insert_post($default_email_template) ;

    }

    add_option('is_upgrade_2.0.2', 1);

    (new KCActivate)->migratePermissions();

}