<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$kc_charset_collate = $wpdb->get_charset_collate();

// do not forget about tables prefix
$table_name = $wpdb->prefix . 'kc_custom_fields_data';

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    module_type varchar(191) NULL,
    module_id bigint(20)  UNSIGNED NOT NULL,
    fields_data longtext NULL,     
    created_at datetime NULL,    
    PRIMARY KEY  (id)
  ) $kc_charset_collate;";

maybe_create_table($table_name,$sql);
$new_fields = [
  'field_id' => 'bigint(20) UNSIGNED NULL',
];

kcUpdateFields($table_name,$new_fields);