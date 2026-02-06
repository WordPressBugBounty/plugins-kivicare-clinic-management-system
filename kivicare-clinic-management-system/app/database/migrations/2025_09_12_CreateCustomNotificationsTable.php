<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');

/**
 * Migration to create the custom notifications table
 * This table stores third-party notification service configurations
 */
class CreateCustomNotificationsTable extends KCAbstractMigration 
{
    /**
     * Run the migration
     */
    public function run() 
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_custom_notifications';
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint NOT NULL AUTO_INCREMENT,
            server_type varchar(50) NOT NULL COMMENT 'Type: sms, email, webhook, custom-api, push-notification',
            server_name varchar(191) NOT NULL COMMENT 'Display name for the service',
            server_url varchar(500) NOT NULL COMMENT 'API endpoint URL',
            port int DEFAULT 443 COMMENT 'Port number for the service',
            http_method varchar(10) DEFAULT 'POST' COMMENT 'HTTP method: GET, POST, PUT, PATCH, DELETE',
            auth_method varchar(50) DEFAULT 'none' COMMENT 'Authentication: none, apikey, bearer, oauth2, basic',
            auth_config longtext DEFAULT NULL COMMENT 'JSON: API keys, OAuth credentials, etc.',
            sender_name varchar(191) DEFAULT NULL COMMENT 'Sender name or identifier',
            sender_email varchar(191) DEFAULT NULL COMMENT 'Sender email or phone number',
            enable_ssl tinyint(1) DEFAULT 1 COMMENT 'Enable SSL/TLS encryption',
            content_type varchar(100) DEFAULT 'application/json' COMMENT 'Request content type',
            custom_headers longtext DEFAULT NULL COMMENT 'JSON: Custom HTTP headers',
            query_params longtext DEFAULT NULL COMMENT 'JSON: Query parameters',
            request_body longtext DEFAULT NULL COMMENT 'Request body template with variables',
            is_active tinyint(1) DEFAULT 1 COMMENT 'Service active status',
            clinic_id bigint DEFAULT NULL COMMENT 'Clinic specific configuration',
            created_by bigint DEFAULT NULL COMMENT 'User who created this configuration',
            created_at datetime DEFAULT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_server_type (server_type),
            KEY idx_is_active (is_active),
            KEY idx_clinic_id (clinic_id),
            KEY idx_created_by (created_by)
        ) " . $this->get_collation() . ";";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Rollback the migration
     */
    public function rollback() 
    {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'kc_custom_notifications');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}
