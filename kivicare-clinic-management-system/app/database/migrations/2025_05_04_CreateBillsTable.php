<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');
class CreateBillsTable extends KCAbstractMigration {
    public function run() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_bills';
        $sql = "CREATE TABLE {$table_name} (
            id bigint NOT NULL AUTO_INCREMENT,
            encounter_id bigint UNSIGNED NOT NULL,
            appointment_id bigint UNSIGNED DEFAULT NULL,
            title varchar(191) DEFAULT NULL,
            total_amount varchar(50) DEFAULT NULL,
            discount varchar(50) DEFAULT NULL,
            actual_amount varchar(50) DEFAULT NULL,
            status bigint NOT NULL,
            payment_status varchar(10) DEFAULT NULL,
            created_at datetime NOT NULL,
            clinic_id bigint DEFAULT NULL,
            PRIMARY KEY (id)
        ) " . $this->get_collation() . ";";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    public function rollback() {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'kc_bills');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}
