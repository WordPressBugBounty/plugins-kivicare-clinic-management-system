<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\models\KCClinic;
use App\models\KCMedicalHistory;
use App\models\KCPatient;
use App\models\KCPatientEncounter;
use App\models\KCReceptionistClinicMapping;
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
            'permission_callback' => [$this, 'checkListPermission'],
            'args' => $this->getListEndpointArgs()
        ]);

        // Get single medical history record
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getMedicalHistory'],
            'permission_callback' => [$this, 'checkSinglePermission'],
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
            'permission_callback' => [$this, 'checkUpdatePermissionWithOwnership'],
            'args' => $this->getUpdateEndpointArgs()
        ]);

        // Delete medical history
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteMedicalHistory'],
            'permission_callback' => [$this, 'checkDeletePermissionWithOwnership'],
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

    public function checkListPermission($request)
    {
        if (!$this->checkCapability('medical_records_list')) {
            return false;
        }

        if ($request->get_param('encounter_id') > 0) {
            $encounter = KCPatientEncounter::find($request->get_param('encounter_id'));
            if (!$encounter) {
                return false;
            }

            $targetPatientId = $request->get_param('patient_id') > 0 ? $request->get_param('patient_id') : (int) $encounter->patientId;
            return $this->canAccessMedicalHistoryTarget($targetPatientId, $request->get_param('encounter_id'));
        }

        if ($request->get_param('patient_id') > 0 && $this->kcbase->getLoginUserRole() === $this->kcbase->getPatientRole()) {
            return $request->get_param('patient_id') === (int) get_current_user_id();
        }

        return true;
    }

    public function checkSinglePermission($request)
    {
        if (!$this->checkCapability('medical_records_list')) {
            return false;
        }

        return $this->canAccessMedicalHistoryId($request->get_param('id'));
    }

    public function checkCreatePermission($request)
    {
        if (!$this->checkCapability('medical_records_add')) {
            return false;
        }

        return $this->canAccessMedicalHistoryTarget(
            $request->get_param('patient_id'),
            $request->get_param('encounter_id')
        );
    }

    public function checkUpdatePermissionWithOwnership($request)
    {
        if (!$this->checkCapability('medical_records_add')) {
            return false;
        }

        $history = KCMedicalHistory::find($request->get_param('id'));
        if (!$history || !$this->canAccessMedicalHistory($history)) {
            return false;
        }

        $targetPatientId = $request->get_param('patient_id') !== null
            ? $request->get_param('patient_id')
            : (int) $history->patientId;
        $targetEncounterId = $request->get_param('encounter_id') !== null
            ? $request->get_param('encounter_id')
            : (int) $history->encounterId;

        return $this->canAccessMedicalHistoryTarget($targetPatientId, $targetEncounterId);
    }

    public function checkDeletePermissionWithOwnership($request)
    {
        if (!$this->checkCapability('medical_records_delete')) {
            return false;
        }

        return $this->canAccessMedicalHistoryId(absint($request->get_param('id')));
    }

    /**
     * Restrict medical history list query to records owned by the current user's role scope.
     *
     * @param mixed $query KC query builder instance.
     * @return void
     */
    private function scopeMedicalHistoryQueryByCurrentUser($query): void
    {
        $role = $this->kcbase->getLoginUserRole();
        $currentUserId = get_current_user_id();

        if ($role === 'administrator') {
            return;
        }

        if ($role === $this->kcbase->getPatientRole()) {
            $query->where('patientId', $currentUserId);
            return;
        }

        $allowedEncounterIds = $this->getCurrentUserAllowedEncounterIds();
        $query->whereIn('encounterId', $allowedEncounterIds);
    }

    /**
     * Check whether the current user may access a medical history record.
     */
    private function canAccessMedicalHistory(KCMedicalHistory $history): bool
    {
        return $this->canAccessMedicalHistoryTarget((int) $history->patientId, (int) $history->encounterId);
    }

    /**
     * Check whether the current user may access a medical history record by ID.
     */
    private function canAccessMedicalHistoryId(int $historyId): bool
    {
        if ($historyId <= 0) {
            return false;
        }

        $history = KCMedicalHistory::find($historyId);
        return $history && $this->canAccessMedicalHistory($history);
    }

    /**
     * Check whether the current user may access a patient/encounter target.
     */
    private function canAccessMedicalHistoryTarget(int $patientId, int $encounterId): bool
    {
        if ($patientId <= 0 || $encounterId <= 0) {
            return false;
        }

        $role = $this->kcbase->getLoginUserRole();
        $currentUserId = get_current_user_id();

        if ($role === 'administrator') {
            return true;
        }

        $encounter = KCPatientEncounter::find($encounterId);
        if (!$encounter || (int) $encounter->patientId !== $patientId) {
            return false;
        }

        if ($role === $this->kcbase->getPatientRole()) {
            return $patientId === (int) $currentUserId && (int) $encounter->patientId === (int) $currentUserId;
        }

        if ($role === $this->kcbase->getDoctorRole()) {
            return (int) $encounter->doctorId === (int) $currentUserId;
        }

        if ($role === $this->kcbase->getReceptionistRole()) {
            return in_array((int) $encounter->clinicId, $this->getReceptionistClinicIds($currentUserId), true);
        }

        if ($role === $this->kcbase->getClinicAdminRole()) {
            $clinic = KCClinic::find((int) $encounter->clinicId);
            return $clinic && (int) $clinic->clinicAdminId === (int) $currentUserId;
        }

        return false;
    }

    /**
     * Get encounter IDs visible to the current doctor/receptionist/clinic admin.
     *
     * @return int[]
     */
    private function getCurrentUserAllowedEncounterIds(): array
    {
        $role = $this->kcbase->getLoginUserRole();
        $currentUserId = get_current_user_id();
        $query = KCPatientEncounter::query()->select(['id']);

        if ($role === $this->kcbase->getDoctorRole()) {
            $query->where('doctorId', $currentUserId);
        } elseif ($role === $this->kcbase->getReceptionistRole()) {
            $clinicIds = $this->getReceptionistClinicIds($currentUserId);
            if (empty($clinicIds)) {
                return [];
            }

            $query->whereIn('clinicId', $clinicIds);
        } elseif ($role === $this->kcbase->getClinicAdminRole()) {
            $clinicIds = KCClinic::query()
                ->where('clinicAdminId', $currentUserId)
                ->select(['id'])
                ->get()
                ->map(fn($clinic) => (int) $clinic->id)
                ->toArray();

            if (empty($clinicIds)) {
                return [];
            }

            $query->whereIn('clinicId', $clinicIds);
        } else {
            return [];
        }

        return $query->get()
            ->map(fn($encounter) => (int) $encounter->id)
            ->toArray();
    }

    /**
     * Get clinic IDs assigned to a receptionist.
     *
     * @return int[]
     */
    private function getReceptionistClinicIds(int $receptionistId): array
    {
        return KCReceptionistClinicMapping::query()
            ->where('receptionistId', $receptionistId)
            ->select(['clinic_id'])
            ->get()
            ->map(fn($row) => (int) $row->clinicId)
            ->toArray();
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

        $this->scopeMedicalHistoryQueryByCurrentUser($query);

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

        if (!$this->canAccessMedicalHistory($history)) {
            return $this->response(null, __('You do not have permission to access this medical history.', 'kivicare-clinic-management-system'), false, 403);
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

        if (!$this->canAccessMedicalHistoryTarget((int) $params['patient_id'], (int) $params['encounter_id'])) {
            return $this->response(null, __('You do not have permission to add medical history for this patient encounter.', 'kivicare-clinic-management-system'), false, 403);
        }

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

        if (!$this->canAccessMedicalHistory($history)) {
            return $this->response(null, __('You do not have permission to update this medical history.', 'kivicare-clinic-management-system'), false, 403);
        }

        $params = $request->get_params();

        $targetPatientId = isset($params['patient_id']) ? (int) $params['patient_id'] : (int) $history->patientId;
        $targetEncounterId = isset($params['encounter_id']) ? (int) $params['encounter_id'] : (int) $history->encounterId;

        if (!$this->canAccessMedicalHistoryTarget($targetPatientId, $targetEncounterId)) {
            return $this->response(null, __('You do not have permission to move this medical history to the selected patient encounter.', 'kivicare-clinic-management-system'), false, 403);
        }

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

        if (!$this->canAccessMedicalHistory($history)) {
            return $this->response(null, __('You do not have permission to delete this medical history.', 'kivicare-clinic-management-system'), false, 403);
        }

        if (!$history->delete()) {
            return $this->response(null, __('Failed to delete medical history', 'kivicare-clinic-management-system'), false, 500);
        }

        return $this->response(['id' => $id], __('Medical history deleted successfully', 'kivicare-clinic-management-system'));
    }
}
