<?php

namespace App\controllers\api\SettingsController;

use App\controllers\api\SettingsController;
use App\models\KCOption;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit('Restricted access');

/**
 * Class PatientSetting
 * Handles API for patient unique ID settings.
 *
 * @package App\controllers\api\SettingsController
 */
class PatientSetting extends SettingsController
{
    private static $instance = null;
    protected $route = 'settings/patient-setting';

    public function __construct()
    {
        parent::__construct();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registers API routes.
     */
    public function registerRoutes(): void
    {
        $this->registerRoute("/{$this->route}", [
            'methods' => 'GET',
            'callback' => [$this, 'getPatientSetting'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        $this->registerRoute("/{$this->route}", [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'updatePatientSetting'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
        ]);
    }

    /**
     * Retrieves patient ID settings.
     */
    public function getPatientSetting(WP_REST_Request $request): WP_REST_Response
    {
        $response = KCOption::get('patient_id_setting', []);

        // Ensure response is an array before accessing array keys
        if (!is_array($response)) {
            $response = [];
        }

        // Convert string values to boolean
        $response['enable'] = in_array((string) $response['enable'], ['true', '1'], true);
        $response['only_number'] = in_array((string) $response['only_number'], ['true', '1'], true);

        return $this->response($response, __('Patient Setting retrieved successfully', 'kivicare-clinic-management-system'));
    }

    /**
     * Updates patient ID settings.
     */
    public function updatePatientSetting(WP_REST_Request $request): WP_REST_Response
    {
        $setting = $request->get_json_params();

        try {
            if (!empty($setting)) {
                $config = [
                    'prefix_value'  => $setting['prefix_value'] ?? '',
                    'postfix_value' => $setting['postfix_value'] ?? '',
                    'enable'        => (bool) $setting['enable'],
                    'only_number'   => (bool) $setting['only_number'],
                ];

                KCOption::set('patient_id_setting', $config);
                return $this->response(null, esc_html__('Unique ID setting saved successfully', 'kivicare-clinic-management-system'));
            }

            return $this->response(null, esc_html__('Setting update failed', 'kivicare-clinic-management-system'), false);
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to save Unique ID settings.', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }
}