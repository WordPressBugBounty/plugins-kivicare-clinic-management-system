<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();

$table_name = $wpdb->prefix . 'kc_patient_clinic_mappings'; // do not forget about tables prefix

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    patient_id bigint(20)  UNSIGNED NOT NULL,
    clinic_id bigint(20)  UNSIGNED NOT NULL,
    created_at datetime NULL,    
    PRIMARY KEY  (id)
  ) $charset_collate;";

maybe_create_table($table_name,$sql);