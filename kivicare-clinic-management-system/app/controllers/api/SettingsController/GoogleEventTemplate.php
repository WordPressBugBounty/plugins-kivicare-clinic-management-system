<?php

namespace App\controllers\api\SettingsController;

use App\controllers\api\SettingsController;
use App\emails\KCEmailTemplateManager;
use App\baseClasses\KCNotificationDynamicKeys;
use App\models\KCOption;
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

        // Ensure default templates are created if they don't exist
        $manager = KCEmailTemplateManager::getInstance();
        $manager->createDefaultTemplates('gcal');

        $template_names = [
            'patient' => $prefix . 'patient_gcal_template',
            'doctor' => $prefix . 'doctor_gcal_template',
            'receptionist' => $prefix . 'receptionist_gcal_template',
            'default' => $prefix . 'default_event_template' // Fallback
        ];

        $data = [];
        $dynamicKeysClass = new KCNotificationDynamicKeys();
        $dynamicKeys = $dynamicKeysClass->getDynamicKeys($prefix . 'default_event_template');

        foreach ($template_names as $role => $post_name) {
            $template_posts = get_posts([
                'post_type' => $post_type,
                'name' => $post_name,
                'posts_per_page' => 1,
                'post_status' => 'publish'
            ]);

            if (!empty($template_posts)) {
                $template = $template_posts[0];
                $data[$role] = [
                    'ID' => $template->ID,
                    'post_title' => $template->post_title,
                    'post_content' => $template->post_content,
                ];
            }
        }

        $status_colors = KCOption::get('gcal_status_colors', self::getDefaultGcalStatusColors());

        $response_data = [
            'templates' => $data,
            'dynamic_keys' => $dynamicKeys,
            'status_colors' => $status_colors,
            'default_status_colors' => self::getDefaultGcalStatusColors(),
            'color_options' => self::getGcalColorOptions()
        ];

        return $this->response([
            'status' => true,
            'data' => $response_data
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
        
        $templates = isset($params['templates']) ? $params['templates'] : [];
        $status_colors = isset($params['status_colors']) ? $params['status_colors'] : [];

        if (empty($templates) || !is_array($templates)) {
            return $this->response([
                'status' => false,
                'message' => __('Invalid data provided', 'kivicare-clinic-management-system')
            ], 400);
        }

        foreach ($templates as $role => $template_data) {
            $id = isset($template_data['ID']) ? intval($template_data['ID']) : 0;
            $post_title = isset($template_data['post_title']) ? sanitize_text_field($template_data['post_title']) : '';
            $post_content = isset($template_data['post_content']) ? wp_kses_post($template_data['post_content']) : '';

            if ($id && !empty($post_title)) {
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
            }
        }

        if (!empty($status_colors) && is_array($status_colors)) {
            // Sanitize and save colors
            $sanitized_colors = [];
            foreach ($status_colors as $key => $val) {
                // Ensure key matches 'status_X' and val is a string/int representing color
                $sanitized_colors[sanitize_key($key)] = sanitize_text_field($val);
            }
            KCOption::set('gcal_status_colors', $sanitized_colors);
        }

        return $this->response([
            'status' => true,
            'message' => __('Templates updated successfully', 'kivicare-clinic-management-system')
        ], 200);
    }

    /**
     * Get default color IDs for various statuses
     * 
     * @return array
     */
    public static function getDefaultGcalStatusColors()
    {
        return [
            'status_0' => '11', // Red for Cancelled
            'status_1' => '9',  // Blue for Booked
            'status_2' => '5',  // Yellow for Pending
            'status_3' => '8',  // Grey for Check-Out
            'status_4' => '2',  // Green for Check-In
        ];
    }

    /**
     * Get the standardized GCal color UI mapping options
     * 
     * @return array
     */
    public static function getGcalColorOptions()
    {
        return [
            [ 'value' => '1',  'label' => __('Pale Blue', 'kivicare-clinic-management-system'), 'color' => '#7986cb', 'contrast' => '#ffffff' ],
            [ 'value' => '2',  'label' => __('Pale Green', 'kivicare-clinic-management-system'),    'color' => '#33b679', 'contrast' => '#ffffff' ],
            [ 'value' => '3',  'label' => __('Mauve', 'kivicare-clinic-management-system'),        'color' => '#8e24aa', 'contrast' => '#ffffff' ],
            [ 'value' => '4',  'label' => __('Pale Red', 'kivicare-clinic-management-system'),  'color' => '#e67c73', 'contrast' => '#000000' ],
            [ 'value' => '5',  'label' => __('Yellow', 'kivicare-clinic-management-system'),      'color' => '#f6bf26', 'contrast' => '#000000' ],
            [ 'value' => '6',  'label' => __('Orange', 'kivicare-clinic-management-system'),   'color' => '#f4511e', 'contrast' => '#000000' ],
            [ 'value' => '7',  'label' => __('Cyan', 'kivicare-clinic-management-system'),       'color' => '#039be5', 'contrast' => '#ffffff' ],
            [ 'value' => '8',  'label' => __('Gray', 'kivicare-clinic-management-system'),      'color' => '#616161', 'contrast' => '#ffffff' ],
            [ 'value' => '9',  'label' => __('Blue', 'kivicare-clinic-management-system'),     'color' => '#3f51b5', 'contrast' => '#ffffff' ],
            [ 'value' => '10', 'label' => __('Green', 'kivicare-clinic-management-system'),       'color' => '#0b8043', 'contrast' => '#ffffff' ],
            [ 'value' => '11', 'label' => __('Red', 'kivicare-clinic-management-system'),        'color' => '#d50000', 'contrast' => '#ffffff' ],
        ];
    }
}
