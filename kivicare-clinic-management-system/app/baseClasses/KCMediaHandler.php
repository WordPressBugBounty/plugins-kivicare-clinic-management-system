<?php

namespace App\baseClasses;

use App\baseClasses\KCBase;

defined('ABSPATH') or die('Something went wrong');

class KCMediaHandler
{
    /**
     * @var KCMediaHandler The instance of the class
     */
    private static $instance = null;

    /**
     * Get the instance of the class
     * 
     * @return KCMediaHandler
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        // Add filter to modify the upload directory
        add_filter('upload_dir', [$this, 'modify_upload_dir']);
    }

    /**
     * Modify the upload directory for KiviCare users.
     * 
     * @param array $uploads Array of upload directory data.
     * @return array Modified array.
     */
    public function modify_upload_dir($uploads)
    {
        $kcBase = KCBase::get_instance();
        
        // Only modify if user has KiviCare role
        if (!$kcBase->userHasKivicareRole()) {
            return $uploads;
        }

        // Detect context (Medical Report or Encounter)
        $isReport = false;
        
        // 1. Check X-KC-View-Path header (from KiviCare Dashboard)
        $viewPath = $_SERVER['HTTP_X_KC_VIEW_PATH'] ?? '';
        if (strpos($viewPath, 'medical-report') !== false || strpos($viewPath, 'encounter') !== false) {
            $isReport = true;
        }

        // 2. Check Referer as fallback
        if (!$isReport && isset($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
            if (strpos($referer, 'medical-report') !== false || strpos($referer, 'encounter') !== false) {
                $isReport = true;
            }
        }

        // 3. Check for specific KiviCare report actions or markers in request
        if (!$isReport && (isset($_REQUEST['action']) && $_REQUEST['action'] === 'kc_upload_report')) {
            $isReport = true;
        }

        if ($isReport) {
            // Flat structure for reports: uploads/kivicare-reports/filename.ext
            $reportSubDir = '/kivicare-reports';
            $uploads['path']   = $uploads['basedir'] . $reportSubDir;
            $uploads['url']    = $uploads['baseurl'] . $reportSubDir;
            $uploads['subdir'] = ''; // Disable year/month subfolders
            
            // Ensure the directory exists
            $this->ensure_dir_exists($uploads['path']);
        }

        return $uploads;
    }

    /**
     * Ensure the directory exists.
     * 
     * @param string $path Full path to the directory.
     */
    private function ensure_dir_exists($path)
    {
        if (!file_exists($path)) {
            wp_mkdir_p($path);
        }

        // Add security files if it's the kivicare-reports folder
        if (strpos($path, 'kivicare-reports') !== false) {
            $htaccess = $path . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'Deny from all');
            }

            $index = $path . '/index.php';
            if (!file_exists($index)) {
                file_put_contents($index, '<?php // Silence is golden');
            }
        }
    }
}
