<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;
$kc_charset_collate = $wpdb->get_charset_collate();

// do not forget about tables prefix.
$table_name = $wpdb->prefix . 'kc_clinic_schedule';

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    start_date date NULL,   
    end_date date NULL,   
    module_type varchar(191) NULL,
    module_id bigint(20)  UNSIGNED NOT NULL,
    description text  NULL,
    status tinyint(1) UNSIGNED NULL DEFAULT 0,    
    created_at datetime NULL,    
    PRIMARY KEY  (id)
  ) $kc_charset_collate;";

maybe_create_table($table_name,$sql);
