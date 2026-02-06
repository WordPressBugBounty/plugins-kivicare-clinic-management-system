<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');
class CreateClinicsTable extends KCAbstractMigration {
    public function run() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_clinics';
        $sql = "CREATE TABLE {$table_name} (
            id bigint NOT NULL AUTO_INCREMENT,
            name varchar(191) DEFAULT NULL,
            email varchar(191) DEFAULT NULL,
            telephone_no varchar(191) DEFAULT NULL,
            specialties longtext,
            address text,
            city varchar(191) DEFAULT NULL,
            state varchar(191) DEFAULT NULL,
            country varchar(191) DEFAULT NULL,
            postal_code varchar(191) DEFAULT NULL,
            status tinyint UNSIGNED DEFAULT '0',
            clinic_admin_id bigint NOT NULL DEFAULT '0',
            clinic_logo bigint NOT NULL DEFAULT '0',
            profile_image bigint DEFAULT NULL,
            extra longtext,
            country_code varchar(10) DEFAULT NULL,
            created_at datetime DEFAULT NULL,
            country_calling_code varchar(10) DEFAULT NULL,
            PRIMARY KEY (id)
        ) " . $this->get_collation() . ";";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    public function rollback() {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'kc_clinics');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}
