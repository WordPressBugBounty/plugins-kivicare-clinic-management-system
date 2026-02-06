<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');
class CreateAppointmentReminderMappingDataTable extends KCAbstractMigration {
    public function run() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_appointment_reminder_mapping';
        $sql = "CREATE TABLE `{$table_name}` (
            id bigint(20) NOT NULL AUTO_INCREMENT,    
            appointment_id bigint(20) default 0,
            msg_send_date date NULL, 
            email_status int(1) default 0,
            sms_status int(1) default 0,
            whatsapp_status int(1) default 0,
            extra longtext NULL,
            PRIMARY KEY  (id)
        ) " . $this->get_collation() . ";";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    public function rollback() {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'kc_appointment_reminder_mapping');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}