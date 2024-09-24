<?php

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$table_name = $wpdb->prefix . 'posts';

$update_table_data_query = "UPDATE  {$table_name} SET post_status = 'publish' WHERE post_type = 'kivicare_mail_tmp' " ;

$wpdb->query($update_table_data_query);

