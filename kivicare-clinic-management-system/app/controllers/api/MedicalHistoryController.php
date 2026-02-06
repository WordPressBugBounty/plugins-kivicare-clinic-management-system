<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\models\KCMedicalHistory;
use App\models\KCPatient;
use App\models\KCPatientEncounter;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class MedicalHistoryController
 * 
 * API Controller for Medical History endpoints
 */
class MedicalHistoryController extends KCBaseController
{
    protected $route = 'medical-history';

    public function registerRoutes()
    {
        // List all medical history records
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getMedicalHistories'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getListEndpointArgs()
        ]);

        // Get single medical history record
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getMedicalHistory'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getSingleEndpointArgs()
        ]);

        // Create new medical history
        $this->registerRoute('/' . $this->route, [
            'methods' => 'POST',
            'callback' => [$this, 'createMedicalHistory'],
            'permission_callback' => [$this, 'checkCreatePermission'],
            'args' => $this->getCreateEndpointArgs()
        ]);

        // Update medical history
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateMedicalHistory'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getUpdateEndpointArgs()
        ]);

        // Delete medical history
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteMedicalHistory'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getSingleEndpointArgs()
        ]);
    }

    /**
     * Checks if a specific encounter sub-module is enabled.
     * @param string $moduleName e.g., 'problem', 'observation', 'note'
     * @return bool
     */
    private function isEncounterModuleEnabled($moduleName)
    {
        $settings = get_option(KIVI_CARE_PRO_PREFIX . 'enocunter_modules', []);
        $encounter_modules = is_string($settings) && !empty($settings) ? json_decode($settings)->encounter_module_config ?? [] : [];
        foreach ($encounter_modules as $module) {
            if (isset($module->name) && $module->name === $moduleName) {
                return isset($module->status) && $module->status == 1;
            }
        }

        return true;
    }

    private function getListEndpointArgs()
    {
        return [
            'patient_id' => [
                'description' => 'Filter by patient ID',
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'encounter_id' => [
                'description' => 'Filter by encounter ID',
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'type' => [
                'description' => 'Type of medical history',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'page' => [
                'description' => 'Current page of results',
                'type' => 'integer',
                'default' => 1,
                'sanitize_callback' => 'absint',
            ],
            'perPage' => [
                'description' => 'Number of results per page',
                'type' => 'integer',
                'default' => 10,
                'sanitize_callback' => 'absint',
            ]
        ];
    }

    private function getSingleEndpointArgs()
    {
        return [
            'id' => [
                'description' => 'Medical history ID',
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint',
            ]
        ];
    }

    private function getCreateEndpointArgs()
    {
        return [
            'patient_id' => [
                'description' => 'Patient ID',
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint',
            ],
            'encounter_id' => [
                'description' => 'Encounter ID',
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint',
            ],
            'type' => [
                'description' => 'Type of medical history',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'title' => [
                'description' => 'Title/description',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'is_from_template' => [
                'description' => 'Is from template',
                'type' => 'integer',
                'required' => false,
                'sanitize_callback' => 'absint',
            ]
        ];
    }

    private function getUpdateEndpointArgs()
    {
        $args = $this->getCreateEndpointArgs();
        foreach ($args as $key => $arg) {
            unset($args[$key]['required']);
        }
        $args['id'] = [
            'description' => 'Medical history ID',
            'type' => 'integer',
            'required' => true,
            'sanitize_callback' => 'absint',
        ];
        return $args;
    }

    public function checkPermission($request)
    {
        return $this->checkCapability('medical_records_list');
    }
    public function checkCreatePermission($request)
    {
        return $this->checkCapability('medical_records_add');
    }
    public function checkUpdatePermission($request)
    {
        return $this->checkCapability('medical_records_add');
    }
    public function checkDeletePermission($request)
    {
        return $this->checkCapability('medical_records_delete');
    }

    public function getMedicalHistories(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();
        $query = KCMedicalHistory::query();

        if (!empty($params['patient_id'])) {
            $query->where('patientId', '=', $params['patient_id']);
        }
        if (!empty($params['encounter_id'])) {
            $query->where('encounterId', '=', $params['encounter_id']);
        }
        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }

        if (!empty($params['type'])) {
            $module_map = [
                'problem' => 'problem',
                'observation' => 'observation',
                'note' => 'note'
            ];

            if (array_key_exists($params['type'], $module_map)) {
                $module_name = $module_map[$params['type']];
                if (!$this->isEncounterModuleEnabled($module_name)) {
                    /* translators: %s: Medical history type */
                    $message = sprintf(__("'%s' module is disabled.", 'kivicare-clinic-management-system'), ucfirst($params['type']));
                    return $this->response(['histories' => []], $message);
                }
            }
        }

        $histories = $query->get();
        $data = [];
        foreach ($histories as $history) {
            $data[] = [
                'id' => $history->id,
                'patient_id' => $history->patientId,
                'encounter_id' => $history->encounterId,
                'type' => $history->type,
                'title' => $history->title,
                'added_by' => $history->addedBy,
                'created_at' => $history->createdAt,
                'is_from_template' => $history->isFromTemplate,
            ];
        }

        return $this->response([
            'histories' => $data,
        ], __('Medical history retrieved successfully', 'kivicare-clinic-management-system'));
    }

    public function getMedicalHistory(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $history = KCMedicalHistory::find($id);
        if (!$history) {
            return $this->response(null, __('Medical history not found', 'kivicare-clinic-management-system'), false, 404);
        }

        $data = [
            'id' => $history->id,
            'patient_id' => $history->patientId,
            'encounter_id' => $history->encounterId,
            'type' => $history->type,
            'title' => $history->title,
            'added_by' => $history->addedBy,
            'created_at' => $history->createdAt,
            'is_from_template' => $history->isFromTemplate,
        ];

        return $this->response($data, __('Medical history retrieved successfully', 'kivicare-clinic-management-system'));
    }

    public function createMedicalHistory(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();

        if (isset($params['type'])) {
            $module_map = [
                'problem' => 'problem',
                'observation' => 'observation',
                'note' => 'note'
            ];

            if (array_key_exists($params['type'], $module_map)) {
                $module_name = $module_map[$params['type']];
                if (!$this->isEncounterModuleEnabled($module_name)) {
                    /* translators: %s: Medical history type */
                    $message = sprintf(__("'%s' module is disabled. Cannot add new entry.", 'kivicare-clinic-management-system'), ucfirst($params['type']));
                    return $this->response(null, $message, false, 403);
                }
            }
        }

        // Check for duplicate
        $existing = KCMedicalHistory::query()
            ->where('patientId', $params['patient_id'])
            ->where('encounterId', $params['encounter_id'])
            ->where('type', $params['type'])
            ->where('title', $params['title'] ?? '')
            ->first();

        if ($existing) {
            return $this->response(null, __('This entry already exists.', 'kivicare-clinic-management-system'), false, 409);
        }

        $history = new KCMedicalHistory();
        $history->patientId = $params['patient_id'];
        $history->encounterId = $params['encounter_id'];
        $history->type = $params['type'];
        $history->title = $params['title'] ?? '';
        $history->addedBy = get_current_user_id();
        $history->createdAt = current_time('mysql');
        $history->isFromTemplate = $params['is_from_template'] ?? 0;

        if (!$history->save()) {
            return $this->response(null, __('Failed to create medical history', 'kivicare-clinic-management-system'), false, 500);
        }

        $data = [
            'id' => $history->id,
            'patient_id' => $history->patientId,
            'encounter_id' => $history->encounterId,
            'type' => $history->type,
            'title' => $history->title,
            'added_by' => $history->addedBy,
            'created_at' => $history->createdAt,
            'is_from_template' => $history->isFromTemplate,
        ];

        return $this->response($data, __('Medical history created successfully', 'kivicare-clinic-management-system'), true, 201);
    }

    public function updateMedicalHistory(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $history = KCMedicalHistory::find($id);

        if (!$history) {
            return $this->response(null, __('Medical history not found', 'kivicare-clinic-management-system'), false, 404);
        }

        $params = $request->get_params();

        if (isset($params['patient_id']))
            $history->patientId = $params['patient_id'];
        if (isset($params['encounter_id']))
            $history->encounterId = $params['encounter_id'];
        if (isset($params['type']))
            $history->type = $params['type'];
        if (isset($params['title']))
            $history->title = $params['title'];
        if (isset($params['is_from_template']))
            $history->isFromTemplate = $params['is_from_template'];

        if (!$history->save()) {
            return $this->response(null, __('Failed to update medical history', 'kivicare-clinic-management-system'), false, 500);
        }

        $data = [
            'id' => $history->id,
            'patient_id' => $history->patientId,
            'encounter_id' => $history->encounterId,
            'type' => $history->type,
            'title' => $history->title,
            'added_by' => $history->addedBy,
            'created_at' => $history->createdAt,
            'is_from_template' => $history->isFromTemplate,
        ];

        return $this->response($data, __('Medical history updated successfully', 'kivicare-clinic-management-system'));
    }

    public function deleteMedicalHistory(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $history = KCMedicalHistory::find($id);

        if (!$history) {
            return $this->response(null, __('Medical history not found', 'kivicare-clinic-management-system'), false, 404);
        }

        if (!$history->delete()) {
            return $this->response(null, __('Failed to delete medical history', 'kivicare-clinic-management-system'), false, 500);
        }

        return $this->response(['id' => $id], __('Medical history deleted successfully', 'kivicare-clinic-management-system'));
    }
}