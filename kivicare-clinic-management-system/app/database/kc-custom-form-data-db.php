<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$kc_charset_collate = $wpdb->get_charset_collate();

// do not forget about tables prefix
$table_name = $wpdb->prefix . 'kc_custom_form_data';

// Define the SQL query for creating the table
$sql = "CREATE TABLE {$table_name} (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    form_id bigint(20) NULL,
    form_data longtext NULL,
    module_id bigint(20) NULL, 
    PRIMARY KEY  (id)
) $kc_charset_collate;";

maybe_create_table($table_name,$sql);
