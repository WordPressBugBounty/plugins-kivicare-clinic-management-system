<?php
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


global $wpdb;
$kc_charset_collate = $wpdb->get_charset_collate();


// do not forget about tables prefix.
$table_name = $wpdb->prefix . 'kc_patient_review'; 

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    review bigint(20)  UNSIGNED NOT NULL,
    review_description longtext,
    patient_id bigint(20)  UNSIGNED NOT NULL,
    doctor_id bigint(20)  UNSIGNED NOT NULL,
    created_at datetime NOT NULL DEFAULT current_timestamp(),    
    updated_at datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY  (id)
  ) $kc_charset_collate;";

maybe_create_table($table_name,$sql);
