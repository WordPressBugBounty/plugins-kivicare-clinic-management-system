<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');
class CreateTaxesTable extends KCAbstractMigration {
    public function run() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_taxes';
        $sql = "CREATE TABLE {$table_name} (
            id bigint NOT NULL AUTO_INCREMENT,
            name varchar(191) DEFAULT NULL,
            tax_type varchar(191) DEFAULT NULL,
            tax_value varchar(191) DEFAULT NULL,
            clinic_id bigint DEFAULT NULL,
            doctor_id bigint DEFAULT NULL,
            service_id bigint DEFAULT NULL,
            added_by bigint DEFAULT NULL,
            status tinyint UNSIGNED DEFAULT '0',
            created_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) " . $this->get_collation() . ";";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    public function rollback() {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'kc_taxes');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}