<?php
namespace App\database\migrations;

use App\database\classes\KCAbstractMigration;
use App\baseClasses\KCErrorLogger;
use DateTime;
use DateTimeZone;
use Exception;

defined('ABSPATH') or die('Something went wrong');

/**
 * Migration: Migrate Appointments to UTC-based Storage
 * 
 * PHASE 1: Add nullable UTC columns (safe mode)
 * PHASE 2: Migrate data in deterministic batches
 * PHASE 3: Add indexes
 * 
 * This migration is idempotent and can be run multiple times safely.
 */
class MigrateAppointmentsToUTC extends KCAbstractMigration {

    private $batch_size = 500;
    private $migrated_count = 0;
    private $failed_count = 0;
    private $failed_ids = [];

    public function run() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_appointments';

        $this->log("=== Starting UTC Migration ===");
        
        // PHASE 1: Schema Update (Safe Mode)
        $this->add_utc_columns($table_name);

        // PHASE 2: Data Migration (Batch Based)
        $this->migrate_data_in_batches($table_name);

        // PHASE 3: Add Indexes
        $this->add_indexes($table_name);

        $this->log("=== UTC Migration Complete ===");
        $this->log("Total Migrated: {$this->migrated_count}");
        $this->log("Total Failed: {$this->failed_count}");
        
        if (!empty($this->failed_ids)) {
            $this->log("Failed Record IDs: " . implode(', ', $this->failed_ids));
        }
    }

    /**
     * PHASE 1: Add UTC columns as NULLABLE (safe mode)
     */
    private function add_utc_columns($table_name) {
        global $wpdb;

        $this->log("PHASE 1: Adding UTC columns...");

        $columns = [
            'appointment_start_utc' => 'DATETIME NULL',
            'appointment_end_utc'   => 'DATETIME NULL',
            'created_at_utc'        => 'DATETIME NULL'
        ];

        foreach ($columns as $column => $definition) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                $wpdb->dbname, $table_name, $column
            ));

            if (empty($exists)) {
                $sql = "ALTER TABLE $table_name ADD COLUMN $column $definition";
                $wpdb->query($sql);
                $this->log("Added column: $column");
            } else {
                $this->log("Column already exists: $column");
            }
        }
    }

    /**
     * PHASE 2: Migrate data in deterministic batches
     */
    private function migrate_data_in_batches($table_name) {
        global $wpdb;

        $this->log("PHASE 2: Starting data migration...");

        // Get WordPress timezone
        $wp_timezone_string = wp_timezone_string();
        if (empty($wp_timezone_string) || !in_array($wp_timezone_string, timezone_identifiers_list())) {
            $wp_timezone_string = 'UTC';
            $this->log("WARNING: Invalid WordPress timezone, using UTC as fallback");
        }
        
        $this->log("WordPress Timezone: $wp_timezone_string");

        try {
            $wp_timezone = new DateTimeZone($wp_timezone_string);
            $utc_timezone = new DateTimeZone('UTC');
        } catch (Exception $e) {
            $this->log("ERROR: Failed to create timezone objects: " . $e->getMessage());
            return;
        }

        // Count total records needing migration
        $total_pending = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name 
             WHERE appointment_start_utc IS NULL 
             OR appointment_end_utc IS NULL"
        );

        $this->log("Total records to migrate: $total_pending");

        if ($total_pending == 0) {
            $this->log("No records need migration.");
            return;
        }

        // Count invalid records before migration
        $invalid_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name 
             WHERE (appointment_start_date IS NULL OR appointment_start_date = '0000-00-00')
             OR (appointment_start_time IS NULL OR appointment_start_time = '00:00:00')"
        );
        
        if ($invalid_count > 0) {
            $this->log("WARNING: Found $invalid_count records with invalid date/time data");
        }

        // Process in deterministic batches (ORDER BY id ASC)
        $last_id = 0;
        $batch_num = 1;

        while (true) {
            // Fetch batch with deterministic ordering
            $records = $wpdb->get_results($wpdb->prepare(
                "SELECT id, appointment_start_date, appointment_start_time, 
                        appointment_end_date, appointment_end_time, created_at 
                 FROM $table_name 
                 WHERE id > %d AND (appointment_start_utc IS NULL OR appointment_end_utc IS NULL)
                 ORDER BY id ASC
                 LIMIT %d",
                $last_id,
                $this->batch_size
            ));

            if (empty($records)) {
                break;
            }

            $this->log("Processing batch #$batch_num (" . count($records) . " records)...");

            // Process batch
            foreach ($records as $record) {
                $this->migrate_single_record($record, $table_name, $wp_timezone, $utc_timezone);
                $last_id = $record->id;
            }

            $batch_num++;
        }

        $this->log("Data migration complete.");
    }

    /**
     * Migrate a single appointment record
     */
    private function migrate_single_record($record, $table_name, $wp_timezone, $utc_timezone) {
        global $wpdb;

        try {
            // Convert start datetime
            $start_utc = $this->convert_to_utc(
                $record->appointment_start_date,
                $record->appointment_start_time,
                $wp_timezone,
                $utc_timezone
            );

            // Convert end datetime
            $end_utc = $this->convert_to_utc(
                $record->appointment_end_date,
                $record->appointment_end_time,
                $wp_timezone,
                $utc_timezone
            );

            // Convert created_at if valid
            $created_at_utc = null;
            if (!empty($record->created_at) && $record->created_at !== '0000-00-00 00:00:00') {
                $parts = explode(' ', $record->created_at);
                $created_at_utc = $this->convert_to_utc(
                    $parts[0] ?? '',
                    $parts[1] ?? '00:00:00',
                    $wp_timezone,
                    $utc_timezone
                );
            }

            // Validate conversion results
            if (!$start_utc || !$end_utc) {
                throw new Exception("Failed to convert datetime values");
            }

            // Update record
            $updated = $wpdb->update(
                $table_name,
                [
                    'appointment_start_utc' => $start_utc,
                    'appointment_end_utc'   => $end_utc,
                    'created_at_utc'        => $created_at_utc
                ],
                ['id' => $record->id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                throw new Exception("Database update failed: " . $wpdb->last_error);
            }

            $this->migrated_count++;

        } catch (Exception $e) {
            $this->failed_count++;
            $this->failed_ids[] = $record->id;
            
            $error_msg = "Failed to migrate record ID {$record->id}: " . $e->getMessage();
            $this->log("ERROR: $error_msg");
            
            // Log to WordPress error log
            error_log("[MigrateAppointmentsToUTC] $error_msg");
        }
    }

    /**
     * Convert local datetime to UTC
     * 
     * @param string $date Date in Y-m-d format
     * @param string $time Time in H:i:s format
     * @param DateTimeZone $source_tz Source timezone
     * @param DateTimeZone $target_tz Target timezone (UTC)
     * @return string|null UTC datetime string or null on failure
     */
    private function convert_to_utc($date, $time, DateTimeZone $source_tz, DateTimeZone $target_tz) {
        // Validate inputs
        if (empty($date) || $date === '0000-00-00') {
            return null;
        }

        if (empty($time)) {
            $time = '00:00:00';
        }

        try {
            $datetime_str = trim($date) . ' ' . trim($time);
            $datetime = new DateTime($datetime_str, $source_tz);
            $datetime->setTimezone($target_tz);
            return $datetime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * PHASE 3: Add indexes
     */
    private function add_indexes($table_name) {
        global $wpdb;

        $this->log("PHASE 3: Adding indexes...");

        $index_name = 'idx_appointment_start_utc';
        
        $exists = $wpdb->get_results(
            "SHOW INDEX FROM $table_name WHERE Key_name = '$index_name'"
        );

        if (empty($exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD INDEX $index_name (appointment_start_utc)");
            $this->log("Added index: $index_name");
        } else {
            $this->log("Index already exists: $index_name");
        }
    }

    /**
     * Log message to both WP_CLI and error log
     */
    private function log($message) {
        $log_message = "[MigrateAppointmentsToUTC] $message";
        
        // Log to WordPress error log
        error_log($log_message);
        
        // Log to WP-CLI if available
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::log($message);
        }
        
        // Log to KiviCare error logger if available
        if (class_exists('App\baseClasses\KCErrorLogger')) {
            KCErrorLogger::instance()->error($log_message);
        }
    }

    /**
     * Rollback migration (for safety, does not drop columns)
     */
    public function rollback() {
        $this->log("Rollback called - UTC columns will remain but can be manually dropped if needed");
        return true;
    }
}
