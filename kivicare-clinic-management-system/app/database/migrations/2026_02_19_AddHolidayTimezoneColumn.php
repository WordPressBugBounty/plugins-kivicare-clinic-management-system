<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;
use App\baseClasses\KCErrorLogger;

defined('ABSPATH') or die('Something went wrong');

/**
 * Migration: Add timezone column to kc_clinic_schedule (holidays)
 * 
 * Holidays with time_specific=1 need timezone context to correctly
 * convert their start_time/end_time for collision detection.
 * Full-day holidays also benefit from explicit timezone for cross-TZ queries.
 */
class AddHolidayTimezoneColumn extends KCAbstractMigration
{
    public function run()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kc_clinic_schedule';

        $row = $wpdb->get_row("SHOW COLUMNS FROM `{$table}` LIKE 'timezone'");
        if (!$row) {
            $wpdb->query(
                "ALTER TABLE `{$table}` 
                ADD COLUMN `timezone` VARCHAR(64) NULL 
                COMMENT 'IANA timezone of the module owner when holiday was created'
                AFTER `end_time`"
            );

            KCErrorLogger::instance()->debug('KiviCare Migration: Added timezone column to ' . $table);
        }

        // Backfill doctor holidays with the doctor's timezone
        $wpdb->query("
            UPDATE `{$table}` cs
            INNER JOIN `{$wpdb->usermeta}` um 
                ON um.user_id = cs.module_id 
                AND um.meta_key = 'timezone'
            SET cs.timezone = um.meta_value
            WHERE cs.module_type = 'doctor'
                AND cs.timezone IS NULL
                AND um.meta_value IS NOT NULL
                AND um.meta_value != ''
        ");

        // Backfill clinic holidays (and any remaining) with WP timezone
        $wp_tz = wp_timezone_string();
        if (!empty($wp_tz)) {
            $wpdb->query($wpdb->prepare(
                "UPDATE `{$table}` SET timezone = %s WHERE timezone IS NULL",
                $wp_tz
            ));
        }

        KCErrorLogger::instance()->debug('KiviCare Migration: Backfilled timezone on ' . $table);
    }

    public function rollback()
    {
        global $wpdb;
        $table = esc_sql($wpdb->prefix . 'kc_clinic_schedule');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("ALTER TABLE `{$table}` DROP COLUMN IF EXISTS `timezone`");
        KCErrorLogger::instance()->debug('KiviCare Migration: Rolled back timezone from ' . $table);
    }
}
