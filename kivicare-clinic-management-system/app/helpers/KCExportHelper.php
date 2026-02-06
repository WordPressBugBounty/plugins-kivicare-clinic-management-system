<?php

namespace App\helpers;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class KCExportHelper
 * 
 * Helper class for export functionality
 * 
 * @package App\helpers
 */
class KCExportHelper {
    
    /**
     * Initialize export functionality
     */
    public static function init() {
        // Create export directory on plugin activation
        add_action('wp_loaded', [self::class, 'createExportDirectory']);
        
        // Clean up old export files
        add_action('kivicare_cleanup_exports', [self::class, 'cleanupOldExports']);
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('kivicare_cleanup_exports')) {
            wp_schedule_event(time(), 'daily', 'kivicare_cleanup_exports');
        }
        
        // Add MIME types for export files
        add_filter('upload_mimes', [self::class, 'addExportMimeTypes']);
        
        // Handle export file downloads
        add_action('init', [self::class, 'handleExportDownload']);
    }
    
    /**
     * Create export directory
     */
    public static function createExportDirectory() {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/kivicare-exports/';
        
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
            
            // Create .htaccess file to protect directory
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files *.csv>\n";
            $htaccess_content .= "    ForceType application/octet-stream\n";
            $htaccess_content .= "    Header set Content-Disposition attachment\n";
            $htaccess_content .= "</Files>\n";
            $htaccess_content .= "<Files *.xlsx>\n";
            $htaccess_content .= "    ForceType application/octet-stream\n";
            $htaccess_content .= "    Header set Content-Disposition attachment\n";
            $htaccess_content .= "</Files>\n";
            $htaccess_content .= "<Files *.pdf>\n";
            $htaccess_content .= "    ForceType application/octet-stream\n";
            $htaccess_content .= "    Header set Content-Disposition attachment\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($export_dir . '.htaccess', $htaccess_content);
            
            // Create index.php to prevent directory listing
            file_put_contents($export_dir . 'index.php', '<?php // Silence is golden');
        }
    }
    
    /**
     * Clean up old export files (older than 24 hours)
     */
    public static function cleanupOldExports() {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/kivicare-exports/';
        
        if (!file_exists($export_dir)) {
            return;
        }
        
        $files = glob($export_dir . '*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $file_time = filemtime($file);
                // Delete files older than 24 hours
                if ($now - $file_time >= 24 * 3600) {
                    wp_delete_file( $file );
                }
            }
        }
    }
    
    /**
     * Add export MIME types
     */
    public static function addExportMimeTypes($mimes) {
        $mimes['csv'] = 'text/csv';
        $mimes['xlsx'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        return $mimes;
    }
    
    /**
     * Handle export file downloads with security checks
     */
    public static function handleExportDownload() {
        global $wp_filesystem;

        if (!isset($_GET['kc_export_download']) || !isset($_GET['file'])) {
            return;
        }
        
        // Verify nonce for security
        $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'kc_export_download' ) ) {
            wp_die(esc_html__('Security check failed', 'kivicare-clinic-management-system'));
        }
        
        // Check user permissions
        if (!current_user_can('read')) {
            wp_die(esc_html__('Insufficient permissions', 'kivicare-clinic-management-system'));
        }
        
        $filename = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/kivicare-exports/' . $filename;
        
        // Security check: ensure file is in export directory
        $real_path = realpath($file_path);
        $export_dir = realpath($upload_dir['basedir'] . '/kivicare-exports/');
        
        if (!$real_path || strpos($real_path, $export_dir) !== 0) {
            wp_die(esc_html__('Invalid file path', 'kivicare-clinic-management-system'));
        }
        
        if (!file_exists($file_path)) {
            wp_die(esc_html__('File not found', 'kivicare-clinic-management-system'));
        }
        
        // Determine content type
        $file_extension = pathinfo($filename, PATHINFO_EXTENSION);
        $content_types = [
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            'html' => 'text/html'
        ];
        
        $content_type = isset($content_types[$file_extension]) 
            ? $content_types[$file_extension] 
            : 'application/octet-stream';
        
        // Send headers
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( $wp_filesystem->exists( $file_path ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Outputting file content for download
            echo $wp_filesystem->get_contents( $file_path );
        }
        
        // Delete file after download for security
        wp_delete_file( $file_path );
        
        exit;
    }
    
    /**
     * Generate secure download URL
     */
    public static function generateDownloadUrl($filename) {
        $nonce = wp_create_nonce('kc_export_download');
        return add_query_arg([
            'kc_export_download' => '1',
            'file' => $filename,
            'nonce' => $nonce
        ], home_url());
    }
    
    /**
     * Validate export permissions for module
     */
    public static function canExportModule($module, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Check basic read permission
        if (!user_can($user_id, 'read')) {
            return false;
        }
        
        // Module-specific permission checks
        $permission_map = [
            'doctors' => 'doctor_view',
            'clinics' => 'clinic_view', 
            'patients' => 'patient_view',
            'appointments' => 'appointment_view',
            'receptionists' => 'receptionist_view'
        ];
        
        if (isset($permission_map[$module])) {
            // Use KiviCare's permission system if available
            if (function_exists('kcCheckPermission')) {
                return kcCheckPermission($permission_map[$module], $user_id);
            }
        }
        
        return true; // Default to allow if no specific permission system
    }
    
    /**
     * Log export activity
     */
    public static function logExportActivity($module, $format, $record_count, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $log_data = [
            'user_id' => $user_id,
            'module' => $module,
            'format' => $format,
            'record_count' => $record_count,
            'timestamp' => current_time('mysql'),
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''
        ];
        
        // Store in WordPress options or custom table
        $existing_logs = get_option('kivicare_export_logs', []);
        $existing_logs[] = $log_data;
        
        // Keep only last 100 logs
        if (count($existing_logs) > 100) {
            $existing_logs = array_slice($existing_logs, -100);
        }
        
        update_option('kivicare_export_logs', $existing_logs);
        
        // Fire action for other plugins to hook into
        do_action('kivicare_export_logged', $log_data);
    }
    
    /**
     * Get export statistics
     */
    public static function getExportStats($days = 30) {
        $logs = get_option('kivicare_export_logs', []);
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $recent_logs = array_filter($logs, function($log) use ($cutoff_date) {
            return $log['timestamp'] >= $cutoff_date;
        });
        
        $stats = [
            'total_exports' => count($recent_logs),
            'by_module' => [],
            'by_format' => [],
            'total_records' => 0
        ];
        
        foreach ($recent_logs as $log) {
            // Count by module
            if (!isset($stats['by_module'][$log['module']])) {
                $stats['by_module'][$log['module']] = 0;
            }
            $stats['by_module'][$log['module']]++;
            
            // Count by format
            if (!isset($stats['by_format'][$log['format']])) {
                $stats['by_format'][$log['format']] = 0;
            }
            $stats['by_format'][$log['format']]++;
            
            // Total records
            $stats['total_records'] += intval($log['record_count']);
        }
        
        return $stats;
    }
}

// Initialize the helper
KCExportHelper::init();