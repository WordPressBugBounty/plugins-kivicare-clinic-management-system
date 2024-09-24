<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


global $wpdb;
$kc_charset_collate = $wpdb->get_charset_collate();


// do not forget about tables prefix.
$table_name = $wpdb->prefix . 'kc_appointments'; 

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    appointment_start_date date NULL,   
    appointment_start_time time NULL,     
    appointment_end_date date NULL,   
    appointment_end_time time NULL, 
    visit_type varchar(191) NULL,
    clinic_id bigint(20)  UNSIGNED NOT NULL,
    doctor_id bigint(20)  UNSIGNED NOT NULL,
    patient_id bigint(20)  UNSIGNED NOT NULL,
    description text  NULL,
    status tinyint(1) UNSIGNED NULL DEFAULT 0,
    created_at datetime NULL,    
    PRIMARY KEY  (id)
  ) $kc_charset_collate;";

maybe_create_table($table_name,$sql);
