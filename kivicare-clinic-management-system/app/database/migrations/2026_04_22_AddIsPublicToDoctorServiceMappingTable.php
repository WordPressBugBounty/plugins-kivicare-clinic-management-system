<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;
use App\baseClasses\KCErrorLogger;

defined('ABSPATH') or die('Something went wrong');

/**
 * Migration: Add is_public column to kc_service_doctor_mapping
 * 
 * This column determines if the doctor service mapping is publicly visible.
 */
class AddIsPublicToDoctorServiceMappingTable extends KCAbstractMigration
{
    public function run()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kc_service_doctor_mapping';

        // Add column if not exists
        $row = $wpdb->get_row("SHOW COLUMNS FROM `{$table}` LIKE 'is_public'");
        if (!$row) {
            $wpdb->query(
                "ALTER TABLE `{$table}` 
                ADD COLUMN `is_public` INT(1) NOT NULL DEFAULT 1 
                COMMENT '0: Private, 1: Public'
                AFTER `status`"
            );

            KCErrorLogger::instance()->debug('KiviCare Migration: Added is_public column to ' . $table);
        }
    }

    public function rollback()
    {
        global $wpdb;
        $table = esc_sql($wpdb->prefix . 'kc_service_doctor_mapping');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("ALTER TABLE `{$table}` DROP COLUMN IF EXISTS `is_public`");
        KCErrorLogger::instance()->debug('KiviCare Migration: Rolled back is_public from ' . $table);
    }
}
