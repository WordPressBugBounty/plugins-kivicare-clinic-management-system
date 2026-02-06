<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');
class CreateReceptionistClinicMappingsTable extends KCAbstractMigration {
    public function run() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_receptionist_clinic_mappings';
        $sql = "CREATE TABLE {$table_name} (
            id bigint NOT NULL AUTO_INCREMENT,
            receptionist_id bigint UNSIGNED NOT NULL,
            clinic_id bigint UNSIGNED NOT NULL,
            created_at datetime DEFAULT NULL,
            PRIMARY KEY (id)
        ) " . $this->get_collation() . ";";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    public function rollback() {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'kc_receptionist_clinic_mappings');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}
