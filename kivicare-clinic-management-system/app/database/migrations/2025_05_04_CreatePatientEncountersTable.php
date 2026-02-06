<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');
class CreatePatientEncountersTable extends KCAbstractMigration {
    public function run() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_patient_encounters';
        $sql = "CREATE TABLE {$table_name} (
            id bigint NOT NULL AUTO_INCREMENT,
            encounter_date date DEFAULT NULL,
            clinic_id bigint UNSIGNED NOT NULL,
            doctor_id bigint UNSIGNED NOT NULL,
            patient_id bigint UNSIGNED NOT NULL,
            appointment_id bigint UNSIGNED DEFAULT NULL,
            description text,
            status tinyint UNSIGNED DEFAULT '0',
            added_by bigint UNSIGNED NOT NULL,
            created_at datetime DEFAULT NULL,
            template_id bigint DEFAULT NULL,
            PRIMARY KEY (id)
        ) " . $this->get_collation() . ";";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    public function rollback() {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'kc_patient_encounters');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}
