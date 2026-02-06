<?php

namespace App\controllers\api\SettingsController;

use App\controllers\api\SettingsController;
use App\models\KCOption;
use App\models\KCUser;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class Configurations
 * 
 * @package App\controllers\api\SettingsController
 */
class Configurations extends SettingsController
{
    protected $route = 'settings/configurations';


    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getConfigurations'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getSettingsEndpointArgs()
        ]);
        // Update Configurations settings
        $this->registerRoute('/' . $this->route, [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'updateConfigurations'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getSettingFieldSchema()
        ]);
    }

    public function getSettingFieldSchema()
    {
        return [
            'module_config' => [
                'description' => 'List of configured modules',
                'type' => 'array',
                'required' => false,
                'sanitize_callback' => 'kcSanitizeData',
                'validate_callback' => function ($value) {
                    return is_array($value) && array_reduce($value, function ($carry, $item) {
                        return $carry && is_array($item) &&
                            isset($item['name'], $item['label'], $item['status']);
                    }, true);
                },
            ],
            'encounter_modules' => [
                'description' => 'Modules related to encounters',
                'type' => 'array',
                'required' => false,
                'sanitize_callback' => 'kcSanitizeData',
                'validate_callback' => function ($value) {
                    return is_array($value) && array_reduce($value, function ($carry, $item) {
                        return $carry && is_array($item) &&
                            isset($item['name'], $item['label'], $item['status']);
                    }, true);
                },
            ],
            'prescription_modules' => [
                'description' => 'Modules related to prescriptions',
                'type' => 'array',
                'required' => false,
                'sanitize_callback' => 'kcSanitizeData',
                'validate_callback' => function ($value) {
                    return is_array($value) && array_reduce($value, function ($carry, $item) {
                        return $carry && is_array($item) &&
                            isset($item['name'], $item['label'], $item['status']);
                    }, true);
                },
            ],
        ];
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

    /**
     * Get Configurations settings
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getConfigurations(WP_REST_Request $request): WP_REST_Response
    {
        // Get the modules data from kivicare_modules option
        $modules_data = kcGetModules();
        $saved_module_config = $modules_data['module_config'] ?? [];

        // Convert the saved module config to the expected format for the frontend
        $final_module_config = array_map(function ($module) {
            return [
                'name' => $module['name'],
                'label' => $module['label'],
                'status' => ($module['status'] === '1' || $module['status'] === 1 || $module['status'] === true) ? 1 : 0
            ];
        }, $saved_module_config);

        $response_data = [
            'module_config' => $final_module_config,
        ];
        
        if(isKiviCareProActive()){
            $response_data['encounter_modules'] = $this->encounterModules();
            $response_data['prescription_modules'] = $this->prescriptionModules();
        }

        return $this->response($response_data, __('Module configuration retrieved.', 'kivicare-clinic-management-system'));
    }

    public function encounterModules()
    {
        if (isKiviCareProActive()) {
            $response = apply_filters('kcpro_get_encounter_list', []);
            return $response;
        }
    }

    public function prescriptionModules()
    {
        if (isKiviCareProActive()) {
            $response = apply_filters('kcpro_get_prescription_list', []);
            return $response;
        }
    }

    /**
     * Update Configurations settings
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function updateConfigurations(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $data = $request->get_params();

            $module_config_input = $data['module_config'] ?? [];
            $new_module_config = [];
            $receptionist_enabled = false;

            // Convert frontend format to storage format
            if (is_array($module_config_input)) {
                foreach ($module_config_input as $module) {
                    if (isset($module['name']) && isset($module['label'])) {
                        $new_module_config[] = [
                            'name' => $module['name'],
                            'label' => $module['label'],
                            'status' => isset($module['status']) && $module['status'] ? '1' : '0'
                        ];
                        
                        if ($module['name'] === 'receptionist' && isset($module['status']) && $module['status']) {
                            $receptionist_enabled = true;
                        }
                    }
                }
            }
            // Update module_config
            KCOption::set('modules', json_encode(['module_config' => $new_module_config]));
            
            $step_config = collect(kcGetStepConfig());
            $response = [];
            $errors = [];

            // Call filters for backward compatibility with Pro
            if (isKiviCareProActive()) {
                if (!empty($data['encounter_modules'])) {
                    $response['encounter_modules'] = apply_filters('kcpro_save_encounter_setting', [
                        'encounter_module_config' => $data['encounter_modules'],
                    ]);
                }
                if (!empty($data['prescription_modules'])) {
                    $response['prescription_modules'] = apply_filters('kcpro_save_prescription_setting', [
                        'prescription_module_config' => $data['prescription_modules'],
                    ]);
                }
            }

            KCUser::updateStatusByRole($this->kcbase->getReceptionistRole(), $receptionist_enabled ? 0 : 4);
            $receptionist_step_exists = $step_config->contains('name', 'receptionist');
            if ($receptionist_enabled && !$receptionist_step_exists) {
                $step_config->push([
                    'icon' => "fa fa-info fa-lg",
                    'name' => "receptionist",
                    'title' => "Receptionist",
                    'prevStep' => 'setup.step5',
                    'routeName' => 'setup.step6',
                    'nextStep' => 'finish',
                    'subtitle' => "",
                    'completed' => false,
                ]);
            } elseif (!$receptionist_enabled && $receptionist_step_exists) {
                $step_config = $step_config->where('name', '!=', 'receptionist')->values();
            }

            return empty($errors)
                ? $this->response($response, __('Module configuration updated successfully', 'kivicare-clinic-management-system'))
                : $this->response($response, __('Module configuration updated with some errors', 'kivicare-clinic-management-system'), true, 207);

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update settings', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }
}
