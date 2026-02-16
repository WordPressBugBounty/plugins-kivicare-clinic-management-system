<?php

namespace KiviCare\Migrations;

use App\baseClasses\KCErrorLogger;
use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');

/**
 * Migration: Add enhanced holiday fields
 * Adds support for:
 * - Selection modes (single, multiple, range)
 * - Multiple dates storage
 * - Time-specific holidays
 */
class AddEnhancedHolidayFields extends KCAbstractMigration
{
    /**
     * Run the migration
     */
    public function run()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_clinic_schedule';

        // Add selection_mode column if it doesn't exist
        $row = $wpdb->get_row("SHOW COLUMNS FROM `{$table_name}` LIKE 'selection_mode'");
        if (!$row) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `selection_mode` VARCHAR(20) DEFAULT 'range' COMMENT 'Date selection mode: single, multiple, or range' AFTER `end_date`");
        }

        // Add selected_dates column if it doesn't exist
        $row = $wpdb->get_row("SHOW COLUMNS FROM `{$table_name}` LIKE 'selected_dates'");
        if (!$row) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `selected_dates` TEXT NULL COMMENT 'JSON array of selected dates for multiple mode' AFTER `selection_mode`");
        }

        // Add time_specific column if it doesn't exist
        $row = $wpdb->get_row("SHOW COLUMNS FROM `{$table_name}` LIKE 'time_specific'");
        if (!$row) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `time_specific` TINYINT(1) DEFAULT 0 COMMENT 'Whether holiday applies to specific time only' AFTER `selected_dates`");
        }

        // Add start_time column if it doesn't exist
        $row = $wpdb->get_row("SHOW COLUMNS FROM `{$table_name}` LIKE 'start_time'");
        if (!$row) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `start_time` TIME NULL COMMENT 'Start time for time-specific holidays' AFTER `time_specific`");
        }

        // Add end_time column if it doesn't exist
        $row = $wpdb->get_row("SHOW COLUMNS FROM `{$table_name}` LIKE 'end_time'");
        if (!$row) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `end_time` TIME NULL COMMENT 'End time for time-specific holidays' AFTER `start_time`");
        }

        KCErrorLogger::instance()->debug('KiviCare Migration: Successfully added enhanced holiday fields to ' . $table_name);
    }

    /**
     * Reverse the migration
     */
    public function rollback()
    {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'kc_clinic_schedule');

        // Remove the added columns
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("ALTER TABLE `{$table_name}` DROP COLUMN IF EXISTS `selection_mode`");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("ALTER TABLE `{$table_name}` DROP COLUMN IF EXISTS `selected_dates`");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("ALTER TABLE `{$table_name}` DROP COLUMN IF EXISTS `time_specific`");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("ALTER TABLE `{$table_name}` DROP COLUMN IF EXISTS `start_time`");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("ALTER TABLE `{$table_name}` DROP COLUMN IF EXISTS `end_time`");

        KCErrorLogger::instance()->debug('KiviCare Migration: Rolled back enhanced holiday fields from ' . $table_name);
    }
}

