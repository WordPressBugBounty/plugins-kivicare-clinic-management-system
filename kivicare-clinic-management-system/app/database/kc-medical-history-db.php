<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$kc_charset_collate = $wpdb->get_charset_collate();

// do not forget about tables prefix
$table_name = $wpdb->prefix . 'kc_medical_history';

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    encounter_id bigint(20)  UNSIGNED NOT NULL,
    patient_id bigint(20)  UNSIGNED NOT NULL,
    type varchar(191)  NOT NULL,
    title varchar(191)  NOT NULL,
    added_by bigint(20)  UNSIGNED NOT NULL,
    created_at datetime NULL,    
    PRIMARY KEY  (id)
  ) $kc_charset_collate;";

maybe_create_table($table_name,$sql);
