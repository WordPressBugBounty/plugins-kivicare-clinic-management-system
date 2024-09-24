<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$kc_charset_collate = $wpdb->get_charset_collate();

// do not forget about tables prefix
$table_name = $wpdb->prefix . 'kc_patient_encounters';

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    encounter_date date NULL, 
    clinic_id bigint(20)  UNSIGNED NOT NULL,
    doctor_id bigint(20)  UNSIGNED NOT NULL,
    patient_id bigint(20)  UNSIGNED NOT NULL,
    appointment_id bigint(20)  UNSIGNED NULL,
    description text  NULL,
    status tinyint(1) UNSIGNED NULL DEFAULT 0,    
    added_by bigint(20)  UNSIGNED NOT NULL,
    created_at datetime NULL,    
    PRIMARY KEY  (id)
  ) $kc_charset_collate;";

maybe_create_table($table_name,$sql);