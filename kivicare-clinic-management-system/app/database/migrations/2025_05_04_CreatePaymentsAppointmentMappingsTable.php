<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');
class CreatePaymentsAppointmentMappingsTable extends KCAbstractMigration {
    public function run() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_payments_appointment_mappings';
        $sql = "CREATE TABLE {$table_name} (
            id int NOT NULL AUTO_INCREMENT,
            payment_mode varchar(255) DEFAULT '',
            payment_id varchar(255) DEFAULT '',
            payer_id varchar(255) DEFAULT '',
            payer_email varchar(255) DEFAULT '',
            appointment_id bigint DEFAULT NULL,
            amount bigint DEFAULT NULL,
            currency varchar(255) DEFAULT '',
            payment_status varchar(255) DEFAULT '',
            request_page_url varchar(255) DEFAULT '',
            extra longtext,
            notification_status bigint DEFAULT '0',
            created_at datetime DEFAULT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id)
        ) " . $this->get_collation() . ";";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    public function rollback() {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'kc_payments_appointment_mappings');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}