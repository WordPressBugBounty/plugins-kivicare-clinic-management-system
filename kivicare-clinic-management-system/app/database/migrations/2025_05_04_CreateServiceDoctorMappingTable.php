<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');
class CreateServiceDoctorMappingTable extends KCAbstractMigration {
    public function run() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_service_doctor_mapping';
        $sql = "CREATE TABLE {$table_name} (
            id bigint NOT NULL AUTO_INCREMENT,
            service_id int DEFAULT '0',
            doctor_id bigint UNSIGNED DEFAULT '0',
            clinic_id int DEFAULT '0',
            charges varchar(50) DEFAULT NULL,
            extra longtext,
            telemed_service varchar(10) DEFAULT NULL,
            service_name_alias varchar(191) DEFAULT NULL,
            multiple varchar(191) DEFAULT NULL,
            image bigint DEFAULT NULL,
            status int DEFAULT '1',
            created_at datetime NOT NULL,
            duration int DEFAULT NULL,
            PRIMARY KEY (id)
        ) " . $this->get_collation() . ";";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    public function rollback() {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'kc_service_doctor_mapping');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}