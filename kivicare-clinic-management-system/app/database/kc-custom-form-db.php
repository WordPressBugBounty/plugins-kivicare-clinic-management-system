<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$kc_charset_collate = $wpdb->get_charset_collate();

// do not forget about tables prefix
$table_name = $wpdb->prefix . 'kc_custom_forms';

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    name longtext NULL,
    module_type varchar(191) NULL,
    fields longtext NULL,
    conditions longtext NULL,
    status tinyint(1) UNSIGNED NULL DEFAULT 0, 
    added_by bigint(20) NOT NULL,    
    created_at datetime NULL,    
    PRIMARY KEY  (id)
  ) $kc_charset_collate;";

maybe_create_table($table_name,$sql);
