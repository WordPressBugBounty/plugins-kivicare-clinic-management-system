<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');

class AddGoogleMapUrlToClinics extends KCAbstractMigration
{
    public function run()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kc_clinics';

        $row = $wpdb->get_row("SHOW COLUMNS FROM `{$table}` LIKE 'google_map_url'");
        if (!$row) {
            $wpdb->query(
                "ALTER TABLE `{$table}` 
                ADD COLUMN `google_map_url` TEXT NULL 
                COMMENT 'Google Map URL for clinic location'
                AFTER `postal_code`"
            );
        }
    }

    public function rollback()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kc_clinics';
        $wpdb->query("ALTER TABLE `{$table}` DROP COLUMN IF EXISTS `google_map_url`");
    }
}
