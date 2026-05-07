<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;
use App\baseClasses\KCErrorLogger;
use App\models\KCService;
use App\models\KCStaticData;

defined('ABSPATH') or die('Something went wrong');

/**
 * Migration: Add category JSON column to kc_services and backfill snapshots
 */
class AddCategoryJsonToServices extends KCAbstractMigration
{
    public function run()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kc_services';
        $static_data_table = $wpdb->prefix . 'kc_static_data';

        // 1. Add the column if it doesn't exist
        $row = $wpdb->get_row("SHOW COLUMNS FROM `{$table}` LIKE 'category'");
        if (!$row) {
            $wpdb->query(
                "ALTER TABLE `{$table}` 
                ADD COLUMN `category` LONGTEXT NULL 
                COMMENT 'JSON snapshot of category metadata (id, label, value)'
                AFTER `type`"
            );
            KCErrorLogger::instance()->debug('KiviCare Migration: Added category column to ' . $table);
        }

        // 2. Backfill existing records
        // We use a query to get matches and then update each row to ensure JSON is correctly formed
        $services = $wpdb->get_results("
            SELECT s.id, sd.id as cat_id, sd.label as cat_label, sd.value as cat_value
            FROM `{$table}` s
            INNER JOIN `{$static_data_table}` sd ON s.type = sd.value
            WHERE sd.type = 'service_type' AND (s.category IS NULL OR s.category = '')
        ");

        if (!empty($services)) {
            foreach ($services as $service) {
                $category_data = json_encode([
                    'id' => (int) $service->cat_id,
                    'label' => $service->cat_label,
                    'value' => $service->cat_value
                ]);

                $wpdb->update(
                    $table,
                    ['category' => $category_data],
                    ['id' => $service->id]
                );
            }
            KCErrorLogger::instance()->debug('KiviCare Migration: Backfilled ' . count($services) . ' services with category snapshots.');
        }
    }

    public function rollback()
    {
        global $wpdb;
        $table = esc_sql($wpdb->prefix . 'kc_services');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("ALTER TABLE `{$table}` DROP COLUMN IF EXISTS `category`");
        KCErrorLogger::instance()->debug('KiviCare Migration: Rolled back category column from ' . $table);
    }
}
