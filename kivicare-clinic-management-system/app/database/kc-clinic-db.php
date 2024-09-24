<?php

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$kc_charset_collate = $wpdb->get_charset_collate();

// do not forget about tables prefix.
$table_name = $wpdb->prefix . 'kc_clinics';

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    name varchar(191)  NULL,
    email varchar(191)  NULL,
    telephone_no varchar(191)  NULL,
    specialties longtext  NULL,
    address text  NULL,
    city varchar(191)  NULL,
    state varchar(191)  NULL,
    country varchar(191)  NULL,
    postal_code varchar(191)  NULL,
    status tinyint(1) UNSIGNED NULL DEFAULT 0,
    clinic_admin_id bigint(20)  NOT NULL DEFAULT 0,
    clinic_logo bigint(20)  NOT NULL DEFAULT 0,
    profile_image bigint(20)  NULL,
    extra longtext NULL,
    country_code varchar(10),     
    created_at datetime NULL,    
    PRIMARY KEY  (id)
  ) $kc_charset_collate;";

maybe_create_table($table_name,$sql);

$new_fields = [
    'extra'           => 'longtext NULL',
    'clinic_admin_id' => 'bigint(20) NOT NULL DEFAULT 0',
    'profile_image'   => 'bigint(20)  NULL'
];

kcUpdateFields($table_name,$new_fields);