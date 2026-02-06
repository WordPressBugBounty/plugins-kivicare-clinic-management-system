<?php

namespace App\controllers\api\SettingsController;

use App\controllers\api\SettingsController;
use App\emails\KCEmailTemplateManager;
use App\models\KCOption;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class EmailTemplate
 * 
 * @package App\controllers\api\SettingsController
 */
class EmailTemplate extends SettingsController
{

    protected $route = 'settings/email-template';


    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        // Get Email Template
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getEmailTemplate'],
            'permission_callback' => [$this, 'checkViewPermission'],
            'args' => $this->getSettingsEndpointArgs()
        ]);
        // Update Email Template
        $this->registerRoute('/' . $this->route, [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'updateEmailTemplate'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args'     => $this->getSettingFieldSchema()
        ]);

        // Test Email Template
        $this->registerRoute('/' . $this->route . '/test', [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'testEmailTemplate'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
        ]);
    }

    /**
     * Check if user has permission to access settings endpoints
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkViewPermission()
    {
        if (!$this->checkCapability('read')) {
            return false;
        }

        if ($this->currentUserRole !== 'administrator') {
            return false;
        }

        return $this->checkResourceAccess('settings', 'view');
    }


    /**
     * Check if user has permission to update settings
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkUpdatePermission($request): bool
    {
        if (!$this->checkCapability('read')) {
            return false;
        }

        if ($this->currentUserRole !== 'administrator') {
            return false;
        }

        return $this->checkResourceAccess('settings', 'edit');
    }

    public function getSettingFieldSchema(): array
    {
        return [
            'email_template' => [
                'ID' => [
                    'description' => 'Post ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_numeric($value);
                    },
                ],
                'post_status' => [
                    'description' => 'Post status',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return in_array($value, ['publish', 'draft', 'pending', 'private'], true);
                    },
                ],
                'post_title' => [
                    'description' => 'Email subject/title',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'kcSanitizeData',
                    'validate_callback' => function ($value) {
                        return is_string($value) && strlen($value) > 0;
                    },
                ],
                'post_content' => [
                    'description' => 'Email body content (may contain HTML and placeholders)',
                    'type' => 'string',
                    'required' => false,
                    'validate_callback' => function ($value) {
                        return is_string($value);
                    },
                ],
            ]
        ];
    }
    /**
     * Get EmailTemplate settings
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getEmailTemplate(WP_REST_Request $request): WP_REST_Response
    {
        $request_data = $request->get_params();
        $email_template_id = $request_data['id'] ?? null;
        $manager = new KCEmailTemplateManager();
        if ($email_template_id) {
            $data = $manager->getTemplateWithKeysById($email_template_id);
            return $this->response($data);
        }
        $data = [
            'data' =>  $manager->getTemplatesList('mail'),
            'labels' => [
                'patient' => __("Patient Templates", "kivicare-clinic-management-system"),
                'doctor' => __("Doctor Templates", "kivicare-clinic-management-system"),
                'clinic' => __("Clinic Templates", "kivicare-clinic-management-system"),
                'receptionist' => __("Receptionist Templates", "kivicare-clinic-management-system"),
                'common' => __("Common Templates", "kivicare-clinic-management-system"),
            ]
        ];
        return $this->response($data);
    }

    /**
     * Update EmailTemplate settings
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function updateEmailTemplate(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $request_data = $request->get_json_params() ?? [];
            if (isset($request_data['ID']) && !empty($request_data['ID'])) {
                wp_update_post($request_data);
                return $this->response(null, esc_html__('Email template saved successfully.', 'kivicare-clinic-management-system'));
            } else {
                return $this->response(null, esc_html__('Failed to update template.', 'kivicare-clinic-management-system'), false);
            }
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update settings', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    public function testEmailTemplate(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $request_data = $request->get_json_params() ?? [];

            // Validate and sanitize email
            $email = isset($request_data['email']) ? sanitize_email($request_data['email']) : '';
            $content = isset($request_data['content']) ? wp_kses_post($request_data['content']) : '';

            if (empty($email) || !is_email($email)) {
                return $this->response(
                    ['error' => __('Invalid email address.', 'kivicare-clinic-management-system')],
                    __('Invalid email address.', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Send test email
            $email_sent = wp_mail($email, 'Kivicare test mail', $content);

            if (!$email_sent) {
                return $this->response(
                    ['error' => __('Failed to send test email. Please check your SMTP setup.', 'kivicare-clinic-management-system')],
                    __('Failed to send test email. Please check your SMTP setup.', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Set option if mail sent successfully
            KCOption::set('is_email_working', true);

            return $this->response(
                null,
                __('Test email sent successfully.', 'kivicare-clinic-management-system'),
                true
            );
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update settings.', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }
}
