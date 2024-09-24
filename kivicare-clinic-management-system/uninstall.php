<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://iqonic.design
 * @since      2.2.4
 *
 * @package    kiviCare-clinic-&-patient-management-system
 */

    if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit ;

    global $wpdb;
    $table_name = $wpdb->prefix . 'options' ;
   //  $setup_wizard_reset = "DELETE FROM {$table_name} WHERE option_name IN ( 'kiviCare_setup_config', 'common_setting', 'clinic_setup_wizard', 'kiviCare_modules', 'kiviCare_setup_steps', 'setup_step_1', 'setup_step_2' , 'setup_step_3', 'setup_step_4' )";
   //  $results = $wpdb->get_results($setup_wizard_reset);
   //  $kivicare_table_prefix  = $wpdb->prefix . 'kc_' ;
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}appointments " );
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}bill_items " );
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}clinic_schedule " );
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}clinic_sessions " );
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}clinics " );
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}custom_fields " );
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}custom_fields_data " );
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}doctor_clinic_mappings " );
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}medical_problems " );
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}medical_history " );
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}patient_encounters " );
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}prescription " );
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}receptionist_clinic_mappings " );
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}services " );
   //  $wpdb->query( "DROP TABLE IF EXISTS {$kivicare_table_prefix}static_data " );


