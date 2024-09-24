<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$kc_charset_collate = $wpdb->get_charset_collate();

// do not forget about tables prefix
$table_name = $wpdb->prefix . 'kc_receptionist_clinic_mappings';

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    receptionist_id bigint(20)  UNSIGNED NOT NULL,
    clinic_id bigint(20)  UNSIGNED NOT NULL,
    created_at datetime NULL,    
    PRIMARY KEY  (id)
  ) $kc_charset_collate;";

maybe_create_table($table_name,$sql);