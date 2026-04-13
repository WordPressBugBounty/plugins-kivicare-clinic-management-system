<?php

namespace App\database\migrations;

use App\database\classes\KCAbstractMigration;
use App\baseClasses\KCBase;
use App\baseClasses\KCErrorLogger;

defined('ABSPATH') or die('Something went wrong');

/**
 * Migration: Move existing KiviCare media to the 'kivicare' subfolder.
 */
class MoveExistingMediaToKivicareFolder extends KCAbstractMigration
{
    private $migrated_count = 0;
    private $failed_count = 0;

    public function run()
    {
        global $wpdb;

        $this->log("=== Starting Media Migration to 'kivicare' folder ===");

        // Ensure kivicare-reports folder and security files exist
        $upload_dir = wp_upload_dir();
        $reports_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'kivicare-reports';
        if (!file_exists($reports_dir)) {
            wp_mkdir_p($reports_dir);
        }
        
        $htaccess = $reports_dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all");
        }
        
        $index = $reports_dir . DIRECTORY_SEPARATOR . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php // Silence is golden");
        }

        // 1. Identify Report Attachment IDs from KiviCare Tables
        $report_ids = [];
        
        // From kc_appointments
        $appointment_reports = $wpdb->get_col("SELECT appointment_report FROM {$wpdb->prefix}kc_appointments WHERE appointment_report IS NOT NULL AND appointment_report != ''");
        foreach ($appointment_reports as $json) {
            $ids = json_decode($json, true);
            if (is_array($ids)) {
                $report_ids = array_merge($report_ids, $ids);
            }
        }
        
        // From kc_patient_medical_report
        $medical_reports = $wpdb->get_col("SELECT upload_report FROM {$wpdb->prefix}kc_patient_medical_report WHERE upload_report IS NOT NULL AND upload_report != ''");
        foreach ($medical_reports as $id) {
            if (is_numeric($id)) {
                $report_ids[] = (int)$id;
            }
        }
        
        $report_ids = array_unique(array_filter($report_ids));
        $this->log("Identified " . count($report_ids) . " report attachments to be moved to flat 'kivicare-reports' folder.");

        // 3. Migrate Reports
        foreach ($report_ids as $attachment_id) {
            $this->migrate_report($attachment_id);
        }

        $this->log("=== Media Migration Complete ===");
        $this->log("Successfully migrated: {$this->migrated_count}");
        $this->log("Failed: {$this->failed_count}");
    }

    /**
     * Migrate a single report to the flat kivicare-reports folder.
     * 
     * @param int $attachment_id
     */
    private function migrate_report($attachment_id)
    {
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $basedir = $upload_dir['basedir'];
        
        // Relative path from basedir (e.g., 2026/03/file.jpg or kivicare/2026/03/file.jpg)
        $relative_path = str_replace($basedir . DIRECTORY_SEPARATOR, '', $file);

        // Check if already in kivicare-reports folder
        if (strpos($relative_path, 'kivicare-reports' . DIRECTORY_SEPARATOR) === 0) {
            return;
        }
        
        // Flat structure: kivicare-reports/file.jpg
        $filename = basename($file);
        $new_relative_path = 'kivicare-reports' . DIRECTORY_SEPARATOR . $filename;

        $new_file = $basedir . DIRECTORY_SEPARATOR . $new_relative_path;

        // Collision handling for flat structure
        if (file_exists($new_file)) {
            $path_info = pathinfo($new_file);
            $new_relative_path = 'kivicare-reports' . DIRECTORY_SEPARATOR . $path_info['filename'] . '_' . $attachment_id . '.' . $path_info['extension'];
            $new_file = $basedir . DIRECTORY_SEPARATOR . $new_relative_path;
        }

        // Ensure directory exists
        $new_dir = dirname($new_file);
        if (!file_exists($new_dir)) {
            wp_mkdir_p($new_dir);
        }

        // Move main file
        if (rename($file, $new_file)) {
            // Update main file path in DB
            update_attached_file($attachment_id, $new_relative_path);

            // Update GUID
            global $wpdb;
            
            // GUIDs usually match the full URL. If old was .../2026/03/file.jpg and new is .../kivicare-reports/file.jpg
            $new_guid = $upload_dir['baseurl'] . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $new_relative_path);

            $wpdb->update($wpdb->posts, ['guid' => $new_guid], ['ID' => $attachment_id]);

            // Handle thumbnails
            $this->move_thumbnails($file, $new_file, $attachment_id);

            $this->migrated_count++;
            $this->log("Migrated report attachment ID $attachment_id: $relative_path -> $new_relative_path");
        } else {
            $this->failed_count++;
            $this->log("ERROR: Failed to move file for report attachment ID $attachment_id");
        }
    }

    private function move_thumbnails($old_file, $new_file, $attachment_id)
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata['sizes'])) {
            $base_dir = dirname($old_file);
            $new_base_dir = dirname($new_file);

            foreach ($metadata['sizes'] as $size => $size_info) {
                $thumb_file = $base_dir . DIRECTORY_SEPARATOR . $size_info['file'];
                $new_thumb_file = $new_base_dir . DIRECTORY_SEPARATOR . $size_info['file'];

                if (file_exists($thumb_file)) {
                    rename($thumb_file, $new_thumb_file);
                }
            }
        }
    }

    private function log($message)
    {
        error_log("[MoveMediaToKivicareFolder] $message");
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::log($message);
        }
    }

    public function rollback()
    {
        return true;
    }
}
