<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


global $wpdb;
$kc_charset_collate = $wpdb->get_charset_collate();


// do not forget about tables prefix.
$table_name = $wpdb->prefix . 'kc_tax_data';

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    module_type varchar(191),
    module_id bigint(20),    
    name varchar(191),   
    charges varchar(191),     
    tax_value varchar(191),
    tax_type varchar(191), 
    PRIMARY KEY  (id)
  ) $kc_charset_collate;";

maybe_create_table($table_name,$sql);
