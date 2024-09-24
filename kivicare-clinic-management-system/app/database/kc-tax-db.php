<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


global $wpdb;
$kc_charset_collate = $wpdb->get_charset_collate();


// do not forget about tables prefix.
$table_name = $wpdb->prefix . 'kc_taxes';

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    name varchar(191),   
    tax_type varchar(191),     
    tax_value varchar(191),
    clinic_id bigint(20),
    doctor_id bigint(20),
    service_id bigint(20),
    added_by bigint(20),
    status tinyint(1) UNSIGNED DEFAULT 0,
    created_at datetime NOT NULL,  
    PRIMARY KEY  (id)
  ) $kc_charset_collate;";

maybe_create_table($table_name,$sql);
