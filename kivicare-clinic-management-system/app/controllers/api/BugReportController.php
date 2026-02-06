<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\baseClasses\KCErrorLogger;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_REST_Server;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class BugReportController
 * 
 * API Controller for handling bug reports and proxying them to n8n
 * 
 * @package App\controllers\api
 */
class BugReportController extends KCBaseController
{
    /**
     * @var string The base route for this controller
     */
    protected $route = 'system/bug-report';

    /**
     * @var string The n8n webhook URL
     */
    private const WEBHOOK_URL = 'https://n8n-agent.iqonic.design/webhook/6dda2806-6934-4dc5-ba5c-aef5d690b649';

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        $this->registerRoute('/' . $this->route . '/submit', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'submitBugReport'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'title' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'content' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'wp_kses_post',
                ],
            ]
        ]);
    }

    /**
     * Submit bug report to n8n webhook
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function submitBugReport(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $title = $request->get_param('title');
            $email = $request->get_param('email');
            $content = $request->get_param('content');

            // Enrich content with site info
            $enriched_content = $content . PHP_EOL . PHP_EOL;
            $enriched_content .= '--------------------------------' . PHP_EOL;
            $enriched_content .= 'Site URL: ' . get_site_url() . PHP_EOL;
            $enriched_content .= 'KiviCare Version: ' . KIVI_CARE_VERSION . PHP_EOL;
            $enriched_content .= '--------------------------------';

            // Construct multipart/form-data payload
            $boundary = wp_generate_password(24, false);
            $payload = '';

            $fields = [
                'title'   => $title,
                'email'   => $email,
                'content' => $enriched_content,
            ];

            foreach ($fields as $name => $value) {
                $payload .= '--' . $boundary . "\r\n";
                $payload .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
                $payload .= $value . "\r\n";
            }

            // Add logs as attachment
            $log_data = $this->getLatestLogsData();
            if ($log_data) {
                $payload .= '--' . $boundary . "\r\n";
                $payload .= 'Content-Disposition: form-data; name="logs"; filename="kc-log-' . gmdate('Y-m-d') . '.log"' . "\r\n";
                $payload .= 'Content-Type: text/plain' . "\r\n\r\n";
                $payload .= $log_data . "\r\n";
            }

            $payload .= '--' . $boundary . '--' . "\r\n";

            $response = wp_remote_post(self::WEBHOOK_URL, [
                'headers' => [
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                ],
                'body'    => $payload,
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                KCErrorLogger::instance()->error('Bug Report Proxy Error: ' . $response->get_error_message());
                return new WP_Error(
                    'bug_report_failed',
                    __('Failed to submit bug report to server.', 'kivicare-clinic-management-system'),
                    ['status' => 500]
                );
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                KCErrorLogger::instance()->error('Bug Report Proxy Server Error: ' . $response_code . ' - ' . $response_body);
                return new WP_Error(
                    'bug_report_server_error',
                    __('Bug report server returned an error.', 'kivicare-clinic-management-system'),
                    ['status' => $response_code]
                );
            }

            return $this->response(
                json_decode($response_body, true),
                __('Bug report submitted successfully.', 'kivicare-clinic-management-system')
            );

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Bug Report Submission Exception: ' . $e->getMessage());
            return new WP_Error(
                'bug_report_exception',
                __('An error occurred while submitting the bug report.', 'kivicare-clinic-management-system'),
                ['status' => 500]
            );
        }
    }

    /**
     * Get the latest Kivicare error logs data
     *
     * @return string|false
     */
    private function getLatestLogsData(): string|false
    {
        try {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/kc-logs';
            $date = gmdate('Y-m-d');
            $file_path = $log_dir . '/kc-log-' . $date . '.log';

            if (!file_exists($file_path)) {
                return false;
            }

            // Read the file and get the last 100 lines
            $log_content = file_get_contents($file_path);
            if (empty($log_content)) {
                return false;
            }

            $lines = explode(PHP_EOL, $log_content);
            $last_lines = array_slice($lines, -100);
            
            return implode(PHP_EOL, $last_lines);

        } catch (\Exception $e) {
            return false;
        }
    }
}
