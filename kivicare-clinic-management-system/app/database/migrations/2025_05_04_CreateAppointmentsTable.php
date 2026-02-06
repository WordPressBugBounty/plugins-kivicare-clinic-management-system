<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');

class CreateAppointmentsTable extends KCAbstractMigration {
    public function run() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_appointments';
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint NOT NULL AUTO_INCREMENT,
            appointment_start_date date DEFAULT NULL,
            appointment_start_time time DEFAULT NULL,
            appointment_end_date date DEFAULT NULL,
            appointment_end_time time DEFAULT NULL,
            visit_type varchar(191) DEFAULT NULL,
            clinic_id bigint UNSIGNED NOT NULL,
            doctor_id bigint UNSIGNED NOT NULL,
            patient_id bigint UNSIGNED NOT NULL,
            description text,
            status tinyint UNSIGNED DEFAULT '0',
            created_at datetime DEFAULT NULL,
            appointment_report longtext,
            PRIMARY KEY (id)
        ) " . $this->get_collation() . ";";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function rollback() {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'kc_appointments');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}
