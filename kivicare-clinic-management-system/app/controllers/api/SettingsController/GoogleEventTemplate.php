<?php

namespace App\controllers\api\SettingsController;

use App\controllers\api\SettingsController;
use App\emails\KCEmailTemplateManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class GoogleEventTemplate
 * 
 * @package App\controllers\api\SettingsController
 */
class GoogleEventTemplate extends SettingsController
{
    private static $instance = null;

    protected $route = 'settings/google-event-template';


    public function __construct()
    {
        parent::__construct();
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getGoogleEventTemplate'],
            'permission_callback' => [$this, 'checkPermission'],
            //'args' => $this->getSettingsEndpointArgs()
        ]);
        // Update Google Event Template
        $this->registerRoute('/' . $this->route, [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'updateGoogleEventTemplate'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            //'args'     => $this->getSettingFieldSchema()['google_event_template']
        ]);
    }

    /**
     * Check if user has permission to access settings endpoints
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkPermission($request): bool
    {
        if (!$this->checkCapability('read')) {
            return false;
        }

        return true;
    }

    /**
     * Get GoogleEventTemplate settings
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getGoogleEventTemplate(WP_REST_Request $request): WP_REST_Response
    {
        $prefix = KIVI_CARE_PREFIX;
        $post_type = $prefix . 'gcal_tmp';
        $post_name = $prefix . 'default_event_template';

        // Ensure default templates are created if they don't exist
        $manager = KCEmailTemplateManager::getInstance();
        $manager->createDefaultTemplates('gcal');

        // Fetch the template post
        $template_posts = get_posts([
            'post_type' => $post_type,
            'name' => $post_name,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ]);

        if (empty($template_posts)) {
            return $this->response([
                'status' => false,
                'message' => __('Template not found', 'kivicare-clinic-management-system')
            ], 404);
        }

        $template = $template_posts[0];

        $data = [
            'ID' => $template->ID,
            'post_title' => $template->post_title,
            'post_content' => $template->post_content
        ];

        return $this->response([
            'status' => true,
            'data' => $data
        ], 200);
    }

    /**
     * Update GoogleEventTemplate settings
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function updateGoogleEventTemplate(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();

        $id = isset($params['ID']) ? intval($params['ID']) : 0;
        $post_title = isset($params['post_title']) ? sanitize_text_field($params['post_title']) : '';
        $post_content = isset($params['post_content']) ? wp_kses_post($params['post_content']) : '';

        if (!$id || empty($post_title)) {
            return $this->response([
                'status' => false,
                'message' => __('Invalid data provided', 'kivicare-clinic-management-system')
            ], 400);
        }

        // Update the post
        $update_result = wp_update_post([
            'ID' => $id,
            'post_title' => $post_title,
            'post_content' => $post_content
        ]);

        if (is_wp_error($update_result)) {
            return $this->response([
                'status' => false,
                'message' => $update_result->get_error_message()
            ], 500);
        }

        return $this->response([
            'status' => true,
            'message' => __('Template updated successfully', 'kivicare-clinic-management-system')
        ], 200);
    }
}
