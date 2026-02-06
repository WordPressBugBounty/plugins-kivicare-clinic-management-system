<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');
class CreatePrescriptionTable extends KCAbstractMigration {
    public function run() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_prescription';
        $sql = "CREATE TABLE {$table_name} (
            id bigint NOT NULL AUTO_INCREMENT,
            encounter_id bigint UNSIGNED NOT NULL,
            patient_id bigint UNSIGNED NOT NULL,
            name text,
            frequency varchar(199) DEFAULT NULL,
            duration varchar(199) DEFAULT NULL,
            instruction text,
            added_by bigint UNSIGNED NOT NULL,
            created_at datetime DEFAULT NULL,
            is_from_template tinyint DEFAULT '0',
            PRIMARY KEY (id)
        ) " . $this->get_collation() . ";";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    public function rollback() {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'kc_prescription');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}