<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;
use App\baseClasses\KCErrorLogger;

defined('ABSPATH') or die('Something went wrong');

/**
 * Migration: Add appointment_timezone column to kc_appointments
 * 
 * Persists the IANA timezone used when an appointment was booked.
 * This is critical for:
 *   - Reconstructing the local time for display/notifications
 *   - Correct UTC conversion in populateUtcColumns()
 *   - Audit trail of which timezone frame was used at booking time
 */
class AddAppointmentTimezoneColumn extends KCAbstractMigration
{
    public function run()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kc_appointments';

        // Add column if not exists
        $row = $wpdb->get_row("SHOW COLUMNS FROM `{$table}` LIKE 'appointment_timezone'");
        if (!$row) {
            $wpdb->query(
                "ALTER TABLE `{$table}` 
                ADD COLUMN `appointment_timezone` VARCHAR(64) NOT NULL DEFAULT 'UTC' 
                COMMENT 'IANA timezone used when appointment was booked'
                AFTER `appointment_end_utc`"
            );

            KCErrorLogger::instance()->debug('KiviCare Migration: Added appointment_timezone column to ' . $table);
        }

        // Backfill existing rows: use doctor's timezone from usermeta
        $wpdb->query("
            UPDATE `{$table}` a
            INNER JOIN `{$wpdb->usermeta}` um 
                ON um.user_id = a.doctor_id 
                AND um.meta_key = 'timezone'
            SET a.appointment_timezone = um.meta_value
            WHERE a.appointment_timezone = 'UTC'
                AND um.meta_value IS NOT NULL
                AND um.meta_value != ''
                AND um.meta_value IN ('" . implode("','", array_map('esc_sql', timezone_identifiers_list())) . "')
        ");

        // For remaining rows (doctors without timezone meta), use WP timezone
        $wp_tz = wp_timezone_string();
        if (!empty($wp_tz) && in_array($wp_tz, timezone_identifiers_list(), true)) {
            $wpdb->query($wpdb->prepare(
                "UPDATE `{$table}` SET appointment_timezone = %s WHERE appointment_timezone = 'UTC'",
                $wp_tz
            ));
        }

        KCErrorLogger::instance()->debug('KiviCare Migration: Backfilled appointment_timezone on ' . $table);
    }

    public function rollback()
    {
        global $wpdb;
        $table = esc_sql($wpdb->prefix . 'kc_appointments');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("ALTER TABLE `{$table}` DROP COLUMN IF EXISTS `appointment_timezone`");
        KCErrorLogger::instance()->debug('KiviCare Migration: Rolled back appointment_timezone from ' . $table);
    }
}
