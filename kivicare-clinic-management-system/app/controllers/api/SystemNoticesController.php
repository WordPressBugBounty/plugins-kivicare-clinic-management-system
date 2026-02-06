<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\emails\KCEmailSender;
use App\baseClasses\KCErrorLogger;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_REST_Server;

use function Avifinfo\read;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class SystemNoticesController
 * 
 * API Controller for system notices and alerts
 * 
 * @package App\controllers\api
 */
class SystemNoticesController extends KCBaseController
{
    /**
     * @var string The base route for this controller
     */
    protected $route = 'system/notices';

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        // Get all system notices
        $this->registerRoute('/' . $this->route, [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getSystemNotices'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        // Send test email
        $this->registerRoute('/' . $this->route . '/test-email', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'sendTestEmail'],
            'permission_callback' => [$this, 'checkAdminPermission'],
            'args' => [
                'email' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Email address to send the test to',
                    'sanitize_callback' => 'sanitize_email',
                ],
            ]
        ]);

        // Run pending migrations
        $this->registerRoute('/' . $this->route . '/run-migrations', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'runPendingMigrations'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    /**
     * Check if the current user has admin permissions
     *
     * @return bool
     */
    public function checkAdminPermission()
    {
        return current_user_can('manage_options');
    }

    /**
     * Get all system notices
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getSystemNotices(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $notices = [];

            // Check for version compatibility notices
            $version_notices = $this->getVersionNotices();
            if (!empty($version_notices)) {
                $notices = array_merge($notices, $version_notices);
            }

            // Check for SMTP configuration
            $smtp_notice = $this->checkSmtpConfiguration();
            if ($smtp_notice) {
                $notices[] = $smtp_notice;
            }

            // Check for pending migrations
            $migration_notice = $this->checkPendingMigrations();
            if ($migration_notice) {
                $notices[] = $migration_notice;
            }

            return $this->response($notices, __('System notices retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return new WP_Error(
                'system_notices_error',
                __('Error retrieving system notices', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Send a test email
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function sendTestEmail(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            // Get parameters from request
            $email = sanitize_email($request->get_param('email')) ?: get_option('admin_email');

            if (!is_email($email)) {
                return new WP_Error(
                    'invalid_email',
                    __('Invalid email address', 'kivicare-clinic-management-system'),
                    ['status' => 400]
                );
            }

            // Use the KCEmailSender testEmailConfiguration method
            $emailSender = KCEmailSender::get_instance();
            $result = $emailSender->testEmailConfiguration($email);

            if ($result) {
                // Update the option to indicate email is working
                update_option(KIVI_CARE_PREFIX . 'is_email_working', true);

                return $this->response(
                    ['email' => $email],
                    __('Test email sent successfully', 'kivicare-clinic-management-system')
                );
            } else {
                return $this->response(
                    ['status' => 'error'],
                    __('Failed to send test email. Please check your email configuration.', 'kivicare-clinic-management-system'),
                    false,
                    500
                );
            }
        } catch (\Exception $e) {
            return new WP_Error(
                'test_email_error',
                __('Error sending test email', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Run pending migrations
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function runPendingMigrations(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            // Get the migrator instance
            $migrator = \App\database\classes\KCMigrator::instance();

            // Setup migrations table if it doesn't exist
            $migrator->setup();

            // Run pending migrations
            $count = $migrator->run();

            if ($count > 0) {
                return $this->response(
                    ['migrations_run' => $count],
                    sprintf(
                        /* translators: %d: number of migrations */
                        __('%d migrations executed successfully', 'kivicare-clinic-management-system'),
                        $count
                    )
                );
            } else {
                return $this->response(
                    ['migrations_run' => 0],
                    __('No pending migrations found', 'kivicare-clinic-management-system')
                );
            }
        } catch (\Exception $e) {
            return new WP_Error(
                'migration_error',
                __('Error running migrations', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Check version compatibility and return notices if any
     *
     * @return array
     */
    private function getVersionNotices(): array
    {
        $notices = [];

        // Check if pro plugin is active 
        if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
            // Get pro version
            $pro_version = defined('KIVI_CARE_PRO_VERSION') ? KIVI_CARE_PRO_VERSION : '0.0.0';
            $required_pro_version = '2.5.8';

            // Compare versions
            if (version_compare($pro_version, $required_pro_version, '<')) {
                $notices[] = [
                    'id' => 'pro_version_outdated',
                    'type' => 'version',
                    'message' => sprintf(
                        /* translators: 1: Required KiviCare Pro version, 2: Current KiviCare Pro version */
                        __('The latest KiviCare Lite plugin requires KiviCare Pro Version %1$s or higher. Your current Pro version is %2$s.', 'kivicare-clinic-management-system'),
                        $required_pro_version,
                        $pro_version
                    ),
                    'dismissible' => true,
                    'actions' => [
                        [
                            'label' => __('Update Now', 'kivicare-clinic-management-system'),
                            'link' => admin_url('plugins.php'),
                            'type' => 'primary'
                        ]
                    ]
                ];
            }
        }

        return $notices;
    }

    /**
     * Check if SMTP is properly configured
     *
     * @return array|null
     */
    private function checkSmtpConfiguration(): ?array
    {
        // Check if any SMTP plugin is active
        $smtp_configured = $this->isSmtpConfigured();

        if (!$smtp_configured) {
            return [
                'id' => 'smtp_not_configured',
                'type' => 'smtp',
                'message' => __('Please make sure your server has Email Server (SMTP) setup! Without proper email configuration, system emails may not be delivered.', 'kivicare-clinic-management-system'),
                'dismissible' => true,
                'actions' => [
                    [
                        'label' => __('Send Test Email', 'kivicare-clinic-management-system'),
                        'action' => 'sendTestEmail',
                        'type' => 'secondary'
                    ],
                    [
                        'label' => __('Learn More', 'kivicare-clinic-management-system'),
                        'link' => 'https://kivicare.io/docs/email-configuration/',
                        'type' => 'link',
                        'target' => '_blank'
                    ]
                ]
            ];
        }

        return null;
    }

    /**
     * Check for pending migrations and return notice if any
     *
     * @return array|null
     */
    private function checkPendingMigrations(): ?array
    {
        try {
            global $wpdb;

            // Get migrations table name
            $migrations_table = esc_sql($wpdb->prefix . 'kc_migrations');

            // Check if migrations table exists, if not, we need to setup
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $sql = $wpdb->prepare('SHOW TABLES LIKE %s', $migrations_table );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $table_exists = $wpdb->get_var($sql) === $migrations_table;

            // Get all migration files from multiple paths
            $migration_files = $this->getAllMigrationFiles();

            if (empty($migration_files)) {
                return null; // No migration files found
            }

            $pending_migrations = [];
            $pending_by_plugin = [];

            if (!$table_exists) {
                // If table doesn't exist, all migrations are pending
                foreach ($migration_files as $file_info) {
                    $migration_name = basename($file_info['file'], '.php');
                    $pending_migrations[] = $migration_name;

                    // Group by plugin for better organization
                    if (!isset($pending_by_plugin[$file_info['plugin']])) {
                        $pending_by_plugin[$file_info['plugin']] = [];
                    }
                    $pending_by_plugin[$file_info['plugin']][] = $migration_name;
                }
            } else {
                // Get ran migrations from database
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $ran_migrations = $wpdb->get_col("SELECT name FROM $migrations_table");

                // Find pending migrations
                foreach ($migration_files as $file_info) {
                    $migration_name = basename($file_info['file'], '.php');
                    if (!in_array($migration_name, $ran_migrations)) {
                        $pending_migrations[] = $migration_name;

                        // Group by plugin for better organization
                        if (!isset($pending_by_plugin[$file_info['plugin']])) {
                            $pending_by_plugin[$file_info['plugin']] = [];
                        }
                        $pending_by_plugin[$file_info['plugin']][] = $migration_name;
                    }
                }
            }

            if (!empty($pending_migrations)) {
                $count = count($pending_migrations);
                $plugin_count = count($pending_by_plugin);

                $message = $plugin_count > 1
                    ? sprintf(
                        /* translators: 1: number of migrations, 2: number of plugins */
                        _n(
                            'There is %1$d pending database migration from %2$d plugin that needs to be run to ensure proper functionality.',
                            'There are %1$d pending database migrations from %2$d plugins that need to be run to ensure proper functionality.',
                            $count,
                            'kivicare-clinic-management-system'
                        ),
                        $count,
                        $plugin_count
                    )
                    : sprintf(
                        /* translators: %d: number of migrations */
                        _n(
                            'There is %d pending database migration that needs to be run to ensure proper functionality.',
                            'There are %d pending database migrations that need to be run to ensure proper functionality.',
                            $count,
                            'kivicare-clinic-management-system'
                        ),
                        $count
                    );

                return [
                    'id' => 'pending_migrations',
                    'type' => 'migration',
                    'message' => $message,
                    'dismissible' => false,
                    'priority' => 'high',
                    'actions' => [
                        [
                            'label' => __('Run Migrations', 'kivicare-clinic-management-system'),
                            'action' => 'runMigrations',
                            'type' => 'primary'
                        ],
                        [
                            'label' => __('Learn More', 'kivicare-clinic-management-system'),
                            'link' => 'https://kivicare.io/docs/database-migrations/',
                            'type' => 'link',
                            'target' => '_blank'
                        ]
                    ],
                    'details' => [
                        'pending_count' => $count,
                        'plugin_count' => $plugin_count,
                        'migrations' => $pending_migrations,
                        'migrations_by_plugin' => $pending_by_plugin
                    ]
                ];
            }

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("[SystemNoticesController] Error checking migrations: " . $e->getMessage());

            // Return a generic migration check error notice
            return [
                'id' => 'migration_check_error',
                'type' => 'error',
                'message' => __('Unable to check database migration status. Please contact support if this issue persists.', 'kivicare-clinic-management-system'),
                'dismissible' => true,
                'priority' => 'medium'
            ];
        }

        return null;
    }

    /**
     * Get all migration files from multiple plugin paths
     *
     * @return array Array of migration file info with 'file', 'plugin', and 'path' keys
     */
    private function getAllMigrationFiles(): array
    {
        $all_migration_files = [];

        // Define migration paths for different plugins
        $migration_paths = apply_filters('kc_wp_migrations_paths', [
            'kivicare' => [
                'path' => KIVI_CARE_DIR . '/app/database/migrations',
                'name' => 'KiviCare'
            ]
        ]);


        foreach ($migration_paths as $plugin_key => $plugin_info) {
            $path = $plugin_info['path'];
            $plugin_name = $plugin_info['name'];

            // Check if directory exists
            if (!is_dir($path)) {
                continue;
            }

            // Get migration files from this path
            $migration_files = glob(trailingslashit($path) . '*.php');

            if (!empty($migration_files)) {
                foreach ($migration_files as $file) {
                    $all_migration_files[] = [
                        'file' => $file,
                        'plugin' => $plugin_name,
                        'plugin_key' => $plugin_key,
                        'path' => $path
                    ];
                }
            }
        }

        return $all_migration_files;
    }

    /**
     * Check if SMTP is configured
     *
     * @return bool
     */
    private function isSmtpConfigured(): bool
    {
        return get_option(KIVI_CARE_PREFIX . 'is_email_working');
    }
}