<?php

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


global $wpdb;
$kc_charset_collate = $wpdb->get_charset_collate();

// do not forget about tables prefix.
$table_name = $wpdb->prefix . 'kc_payments_appointment_mappings';

$sql = "CREATE TABLE {$table_name} (
`id` int NOT NULL AUTO_INCREMENT,
`payment_mode` varchar(255) DEFAULT '',
`payment_id` varchar(255) DEFAULT '',
`payer_id` varchar(255) DEFAULT '',
`payer_email` varchar(255) DEFAULT '',
`appointment_id` bigint(20),
`amount` bigint(20),
`currency` varchar(255) DEFAULT '',
`payment_status` varchar(255) DEFAULT '',
`request_page_url` varchar(255) DEFAULT '',
`extra` longtext,
`notification_status` bigint(20) DEFAULT 0,
`created_at` datetime DEFAULT NULL,    
`updated_at` datetime DEFAULT NULL,
PRIMARY KEY (`id`)
) {$kc_charset_collate}";

maybe_create_table($table_name,$sql);