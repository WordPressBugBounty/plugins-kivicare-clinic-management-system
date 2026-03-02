<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;
use App\baseClasses\KCErrorLogger;

defined('ABSPATH') or die('Something went wrong');

/**
 * Migration: Add composite indexes for timezone-safe queries
 * 
 * These indexes optimize the hot-path queries in slot generation:
 *   1. Appointment collision check (doctor + UTC range + status)
 *   2. Session lookup (doctor + clinic + day)
 *   3. Holiday lookup (module + date range + status)
 *   4. Dashboard appointment listing (clinic + UTC + status)
 */
class AddTimezonePerformanceIndexes extends KCAbstractMigration
{
    public function run()
    {
        global $wpdb;

        $indexes = [
            [
                'table' => $wpdb->prefix . 'kc_appointments',
                'name'  => 'idx_apt_doctor_utcrange_status',
                'cols'  => '(doctor_id, appointment_start_utc, appointment_end_utc, status)',
            ],
            [
                'table' => $wpdb->prefix . 'kc_clinic_sessions',
                'name'  => 'idx_session_doctor_clinic_day',
                'cols'  => '(doctor_id, clinic_id, day)',
            ],
            [
                'table' => $wpdb->prefix . 'kc_clinic_schedule',
                'name'  => 'idx_schedule_module_dates_status',
                'cols'  => '(module_type, module_id, start_date, end_date, status)',
            ],
            [
                'table' => $wpdb->prefix . 'kc_appointments',
                'name'  => 'idx_apt_clinic_start_status',
                'cols'  => '(clinic_id, appointment_start_utc, status)',
            ],
            [
                'table' => $wpdb->prefix . 'kc_appointments',
                'name'  => 'idx_apt_patient_start_status',
                'cols'  => '(patient_id, appointment_start_utc, status)',
            ],
        ];

        foreach ($indexes as $index) {
            $this->addIndexIfNotExists($index['table'], $index['name'], $index['cols']);
        }

        KCErrorLogger::instance()->debug('KiviCare Migration: Added timezone performance indexes');
    }

    /**
     * Add index only if it doesn't already exist
     */
    private function addIndexIfNotExists(string $table, string $indexName, string $columns): void
    {
        global $wpdb;

        // Check if index exists
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = DATABASE() AND table_name = %s AND index_name = %s",
                $table,
                $indexName
            )
        );

        if (!$exists) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("CREATE INDEX `{$indexName}` ON `{$table}` {$columns}");
            KCErrorLogger::instance()->debug("KiviCare Migration: Created index {$indexName} on {$table}");
        }
    }

    public function rollback()
    {
        global $wpdb;

        $drops = [
            [$wpdb->prefix . 'kc_appointments', 'idx_apt_doctor_utcrange_status'],
            [$wpdb->prefix . 'kc_clinic_sessions', 'idx_session_doctor_clinic_day'],
            [$wpdb->prefix . 'kc_clinic_schedule', 'idx_schedule_module_dates_status'],
            [$wpdb->prefix . 'kc_appointments', 'idx_apt_clinic_start_status'],
            [$wpdb->prefix . 'kc_appointments', 'idx_apt_patient_start_status'],
        ];

        foreach ($drops as [$table, $name]) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("DROP INDEX IF EXISTS `{$name}` ON `{$table}`");
        }

        KCErrorLogger::instance()->debug('KiviCare Migration: Rolled back timezone performance indexes');
    }
}
