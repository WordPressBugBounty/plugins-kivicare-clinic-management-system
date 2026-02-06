<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\models\KCPatientEncounter;
use App\baseClasses\KCBase;
use App\models\KCBill;
use App\models\KCBillItem;
use App\models\KCClinic;
use App\models\KCDoctor;
use App\models\KCMedicalHistory;
use App\models\KCPatient;
use App\models\KCUserMeta;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use App\models\KCAppointment;
use App\models\KCAppointmentServiceMapping;
use App\models\KCPaymentsAppointmentMapping;
use App\models\KCReceptionistClinicMapping;
use App\models\KCCustomFieldData;
use App\baseClasses\KCErrorLogger;


defined('ABSPATH') or die('Something went wrong');

/**
 * Class EncounterController
 * 
 * API Controller for Encounter-related endpoints
 * 
 * @package App\controllers\api
 */
class EncounterController extends KCBaseController
{

    /**
     * @var string The base route for this controller
     */
    protected $route = 'encounters';

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        // get encounter
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getEncounters'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Get single encounter by ID
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getEncounter'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'id' => [
                    'description' => 'Encounter ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                ],
            ],
        ]);

        // create encounter
        $this->registerRoute('/' . $this->route, [
            'methods' => 'POST',
            'callback' => [$this, 'createEncounter'],
            'permission_callback' => [$this, 'checkCreatePermission'],
            'args' => $this->getCreateEndpointArgs()

        ]);

        // Update encounter
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateEncounter'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getCreateEndpointArgs()
        ]);

        // Bulk update status
        $this->registerRoute('/' . $this->route . '/bulk/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'bulkUpdateStatus'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getBulkStatusUpdateEndpointArgs()
        ]);

        // Bulk actions
        $this->registerRoute('/' . $this->route . '/bulk/delete', [
            'methods' => 'POST',
            'callback' => [$this, 'bulkDeleteEncounters'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getBulkActionEndpointArgs()
        ]);

        // Delete encounter
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteEncounter'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getDeleteEndpointArgs()
        ]);
    }

    /**
     * Check if user has permission to create an encounter
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkCreatePermission($request)
    {
        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Check encounter add permission
        return $this->checkResourceAccess('patient_encounter', 'add');
    }

    /**
     * Get arguments for the create endpoint
     *
     * @return array
     */
    protected function getCreateEndpointArgs()
    {
        return [
            'clinic' => [
                'description' => 'Clinic ID',
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint',
            ],
            'doctor' => [
                'description' => 'Doctor ID',
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint',
            ],
            'patient' => [
                'description' => 'Patient ID',
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint',
            ],
            'encounterDate' => [
                'description' => 'Encounter date (Y-m-d)',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'description' => [
                'description' => 'Encounter description',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ]
        ];
    }

    /**
     * Get arguments for the delete endpoint
     *
     * @return array
     */
    private function getDeleteEndpointArgs()
    {
        return [
            'id' => array_merge($this->getCommonArgs()['id'], ['required' => true])
        ];
    }


    /**
     * Check if user has permission to update a encounter
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkUpdatePermission($request)
    {
        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Check encounter edit permission
        return $this->checkResourceAccess('patient_encounter', 'edit');
    }

    /**
     * Check if user has permission to delete a encounter
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkDeletePermission($request)
    {
        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Check encounter delete permission
        if (!$this->checkResourceAccess('patient_encounter', 'delete')) {
            return false;
        }

        $kcBase = KCBase::get_instance();
        $currentUserRole = $kcBase->getLoginUserRole();
        $currentUserId = get_current_user_id();

        // Support both single and bulk delete
        $ids = [];
        if ($request->get_param('id')) {
            $ids[] = $request->get_param('id');
        } elseif ($request->get_param('ids')) {
            $ids = $request->get_param('ids');
        }
        if (empty($ids)) {
            // No specific encounter(s) to check, allow
            return true;
        }
        foreach ($ids as $encounterId) {
            $encounter = KCPatientEncounter::find($encounterId);
            if (empty($encounter)) {
                return false;
            }
            $hasAccess = false;
            switch ($currentUserRole) {
                case $kcBase->getDoctorRole():
                    $hasAccess = ($encounter->doctorId == $currentUserId);
                    break;
                case $kcBase->getPatientRole():
                    $hasAccess = ($encounter->patientId == $currentUserId);
                    break;
                case $kcBase->getReceptionistRole():
                    $clinicIds = \App\models\KCReceptionistClinicMapping::query()
                        ->where('receptionistId', $currentUserId)
                        ->select(['clinic_id'])
                        ->get()
                        ->map(fn($row) => $row->clinicId)
                        ->toArray();
                    $hasAccess = in_array($encounter->clinicId, $clinicIds);
                    break;
                case $kcBase->getClinicAdminRole():
                    $clinic = KCClinic::find($encounter->clinicId);
                    $hasAccess = $clinic && $clinic->clinicAdminId == $currentUserId;
                    break;
                case 'administrator':
                    $hasAccess = true;
                    break;
                default:
                    $hasAccess = false;
            }
            if (!$hasAccess) {
                return false;
            }
        }
        return true;
    }
    /**
     * Get arguments for bulk status update endpoint
     *
     * @return array
     */
    private function getBulkStatusUpdateEndpointArgs()
    {
        return array_merge(
            $this->getBulkActionEndpointArgs(),
            [
                'status' => array_merge($this->getCommonArgs()['status'], ['required' => true])
            ]
        );
    }

    /**
     * Get common arguments used across multiple endpoints
     *
     * @return array
     */
    private function getCommonArgs()
    {
        return [
            'id' => [
                'description' => 'Enocunter ID',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
            'status' => [
                'description' => 'encounter status (0: Inactive, 1: Active)',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateStatus'],
                'sanitize_callback' => 'absint',
            ]
        ];
    }

    /**
     * Validate ID parameter
     *
     * @param mixed $param
     * @return bool|WP_Error
     */
    public function validateId($param)
    {
        if (!is_numeric($param) || $param <= 0) {
            return new WP_Error('invalid_id', __('Invalid ID parameter', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Validate encounter status
     *
     * @param mixed $param
     * @return bool|WP_Error
     */
    public function validateStatus($param)
    {
        if (!in_array(intval($param), [0, 1])) {
            return new WP_Error('invalid_status', __('Status must be 0 (inactive) or 1 (active)', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Get arguments for bulk action endpoints
     *
     * @return array
     */
    private function getBulkActionEndpointArgs()
    {
        return [
            'ids' => [
                'description' => 'Array of encounter IDs',
                'type' => 'array',
                'items' => [
                    'type' => 'integer',
                ],
                'required' => true,
                'validate_callback' => function ($param) {
                    if (!is_array($param) || empty($param)) {
                        return new WP_Error('invalid_ids', __('encounter IDs are required', 'kivicare-clinic-management-system'));
                    }

                    foreach ($param as $id) {
                        if (!is_numeric($id) || intval($id) <= 0) {
                            return new WP_Error('invalid_id', __('Invalid encounter ID in array', 'kivicare-clinic-management-system'));
                        }
                    }
                    return true;
                }
            ]
        ];
    }

    /**
     * Get encounter
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function getEncounter($request): \WP_REST_Response|\WP_Error
    {
        $id = intval($request->get_param('id'));
        $currentUserRole = $this->kcbase->getLoginUserRole();
        $currentUserId = get_current_user_id();

        try {
            $query = KCPatientEncounter::table('a')
                ->select([
                    'a.*',
                    'c.name as clinic_name',
                    'c.profile_image as clinic_profile_image',
                    'd.display_name as doctor_name',
                    'd.user_email as doctor_email',
                    'p.user_email as patient_email',
                    'p.display_name as patient_name',
                    'bd.meta_value as basic_data',
                    'um_doctor.meta_value as doctor_profile_image',
                    'um_patient.meta_value as patient_profile_image',
                    'appt.description as appointment_description',
                    'appt.appointment_report as appointment_report',
                ])
                ->leftJoin(KCClinic::class, 'a.clinic_id', '=', 'c.id', 'c')
                ->leftJoin(KCDoctor::class, "a.doctor_id", '=', 'd.id', 'd')
                ->leftJoin(KCPatient::class, "a.patient_id", '=', 'p.id', 'p')
                ->leftJoin(KCAppointment::class, "a.appointment_id", '=', 'appt.id', 'appt')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'bd.user_id')
                        ->onRaw("bd.meta_key = 'basic_data'");
                }, null, null, 'bd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.id', '=', 'um_doctor.user_id')
                        ->onRaw("um_doctor.meta_key = 'doctor_profile_image'");
                }, null, null, 'um_doctor')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'um_patient.user_id')
                        ->onRaw("um_patient.meta_key = 'patient_profile_image'");
                }, null, null, 'um_patient')
                ->where('a.id', $id);


            // Role-based filter (optional, similar to list)
            switch ($currentUserRole) {
                case $this->kcbase->getDoctorRole():
                    $query->where('a.doctor_id', $currentUserId);
                    break;
                case $this->kcbase->getPatientRole():
                    $query->where('a.patient_id', $currentUserId);
                    break;

                case $this->kcbase->getReceptionistRole():
                    // Receptionist: filter by clinics assigned in kc_receptionist_clinic_mappings
                    $clinicIds = KCReceptionistClinicMapping::query()
                        ->where('receptionistId', $currentUserId)
                        ->select(['clinic_id'])
                        ->get()
                        ->map(fn($row) => $row->clinicId)
                        ->toArray();
                    if (!empty($clinicIds)) {
                        $query->whereIn('a.clinic_id', $clinicIds);
                    } else {
                        // No clinics assigned, return empty
                        $query->whereRaw('0=1');
                    }
                    break;

                case $this->kcbase->getClinicAdminRole():
                    $query->where('c.clinic_admin_id', $currentUserId);
                    break;

                case 'administrator':
                    // No restriction for admin
                    break;

                default:
                    return new \WP_Error(
                        'kc_permission_denied',
                        __('You do not have permission to view encounters.', 'kivicare-clinic-management-system'),
                        []
                    );
            }

            $encounter = $query->first();

            if (!$encounter) {
                $error = new \WP_Error(
                    'kc_encounter_not_found',
                    __('Encounter not found.', 'kivicare-clinic-management-system'),
                    ['id' => $id]
                );
                // Set 403 status for not found (forbidden)
                return new \WP_REST_Response($error, 403);
            }
            $customFieldData = $this->getCustomFieldData($encounter->id, 'patient_encounter_module');

            $result = [
                'id' => $encounter->id,
                'encounterDate' => kcGetFormatedDate(gmdate('F j, Y', strtotime($encounter->encounterDate))),
                'clinicId' => $encounter->clinicId,
                'clinic_image_url' => $encounter->clinic_profile_image ? wp_get_attachment_url($encounter->clinic_profile_image) : '',
                'doctorId' => $encounter->doctorId,
                'patientId' => $encounter->patientId,
                'appointmentId' => $encounter->appointmentId,
                'description' => $encounter->description,
                'status' => (int) $encounter->status,
                'addedBy' => $encounter->addedBy,
                'createdAt' => $encounter->createdAt,
                'templateId' => $encounter->templateId,
                'clinicName' => $encounter->clinic_name,
                'doctorName' => $encounter->doctor_name,
                'doctor_image_url' => $encounter->doctor_profile_image ? wp_get_attachment_url($encounter->doctor_profile_image) : '',
                'patientName' => $encounter->patient_name,
                'patientEmail' => $encounter->patient_email,
                'patient_image_url' => $encounter->patient_profile_image ? wp_get_attachment_url($encounter->patient_profile_image) : '',
                'patient_data' => json_decode($encounter->basic_data, true) ?: [],
                'customfield_data' => $customFieldData,
            ];
            if ($encounter->appointment_report) {
                $reportIds = json_decode($encounter->appointment_report, true);
                if (is_array($reportIds)) {
                    $reports = [];
                    foreach ($reportIds as $id) {
                        $url = wp_get_attachment_url((int) $id);
                        $filename = get_the_title($id);
                        $reports[] = [
                            'id' => $id,
                            'url' => $url,
                            'filename' => $filename
                        ];
                    }
                    $result['appointmentReport'] = $reports;
                }
            }
            return $this->response($result, __('Encounter retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return new \WP_Error(
                'kc_encounter_fetch_failed',
                __('Failed to fetch encounter.', 'kivicare-clinic-management-system'),
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get custom field data for encounter
     * 
     * @param int $module_id Encounter ID
     * @param string $module_type Module type (patient_encounter_module)
     * @return array Custom field data formatted for frontend
     */
    private function getCustomFieldData($module_id, $module_type = 'patient_encounter_module')
    {
        try {
            $customFieldDataRecords = KCCustomFieldData::query()
                ->where('module_type', '=', $module_type)
                ->where('module_id', '=', $module_id)
                ->get();


            $customFieldData = [];

            foreach ($customFieldDataRecords as $record) {
                $value = $record->fieldsData;
                if (is_string($value) && $this->isJson($value)) {
                    $value = json_decode($value, true);
                }

                $customFieldData[] = [
                    'id' => $record->fieldId,
                    'value' => $value
                ];
            }
            return $customFieldData;
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Failed to get custom field data: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if a string is valid JSON
     * 
     * @param string $string
     * @return bool
     */
    private function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Save custom field data for encounter
     * 
     * @param array $params Request parameters
     * @param string $module_type Module type (encounter_module)
     * @param int $module_id Encounter ID
     * @return void
     */
    private function saveCustomFieldData($params, $module_type, $module_id)
    {
        try {
            $customFieldData = [];

            // Extract custom fields from 'customForm' array
            if (!empty($params['customForm']) && is_array($params['customForm'])) {
                foreach ($params['customForm'] as $field) {
                    if (!empty($field['id']) && isset($field['value'])) {
                        $fieldId = (int) $field['id'];
                        if ($fieldId > 0) {
                            $customFieldData[$fieldId] = $field['value'];
                        }
                    }
                }
            }

            // Save or update each custom field entry
            foreach ($customFieldData as $fieldId => $fieldValue) {
                $fieldsData = is_array($fieldValue) ? wp_json_encode($fieldValue) : $fieldValue;

                // Check if a record already exists
                $existing = KCCustomFieldData::query()
                    ->where('module_type', '=', $module_type)
                    ->where('module_id', '=', $module_id)
                    ->where('field_id', '=', $fieldId)
                    ->first();

                if ($existing) {
                    // Update existing record
                    $existing->upgmdate([
                        'fields_data' => $fieldsData,
                    ]);
                } else {
                    // Insert new record
                    KCCustomFieldData::create([
                        'module_type' => $module_type,
                        'module_id' => $module_id,
                        'field_id' => $fieldId,
                        'fields_data' => $fieldsData,
                        'created_at' => current_time('mysql'),
                    ]);
                }
            }
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Failed to save custom field data: ' . $e->getMessage());
        }
    }

    /**
     * Get encounter list
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function getEncounters($request)
    {
        $params = $request->get_params();
        $kcBase = KCBase::get_instance();
        $currentUserRole = $kcBase->getLoginUserRole();
        $currentUserId = get_current_user_id();


        try {

            $query = KCPatientEncounter::table('a')
                ->select([
                    'a.*',
                    'c.name as clinic_name',
                    'c.profile_image as clinic_profile_image',
                    'c.email as clinic_email',
                    'c.clinic_admin_id as clinic_admin_id',
                    'd.display_name as doctor_name',
                    'd.user_email as doctor_email',
                    'p.display_name as patient_name',
                    'p.user_email as patient_email',
                    'um_doctor.meta_value as doctor_profile_image',
                    'um_patient.meta_value as patient_profile_image',
                    'bill.id as bill_id',
                ])
                ->leftJoin(KCClinic::class, 'a.clinic_id', '=', 'c.id', 'c')
                ->leftJoin(KCDoctor::class, "a.doctor_id", '=', 'd.id', 'd')
                ->leftJoin(KCPatient::class, "a.patient_id", '=', 'p.id', 'p')
                ->leftJoin(KCBill::class, 'a.id', '=', 'bill.encounter_id', 'bill')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.id', '=', 'um_doctor.user_id')
                        ->onRaw("um_doctor.meta_key = 'doctor_profile_image'");
                }, null, null, 'um_doctor')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.id', '=', 'um_patient.user_id')
                        ->onRaw("um_patient.meta_key = 'patient_profile_image'");
                }, null, null, 'um_patient');

            if (isset($params['encounterDate']) && !empty($params['encounterDate'])) {
                $query->where("a.encounter_date", 'LIKE', '%' . $params['encounterDate'] . '%');
            }

            if (isset($params['encounterStatus']) && $params['encounterStatus'] !== "") {
                KCErrorLogger::instance()->error($params['encounterStatus']);
                $query->where("a.status", '=', $params['encounterStatus']);
            }

            if (isset($params['clinic']) && !empty($params['clinic'])) {
                $query->where("a.clinic_id ", '=', $params['clinic']);
            }

            if (isset($params['doctor']) && !empty($params['doctor'])) {
                $query->where("a.doctor_id ", '=', $params['doctor']);
            }

            if (isset($params['patient']) && !empty($params['patient'])) {
                $query->where("a.patient_id ", '=', $params['patient']);
            }
            if (isset($params['status']) && $params['status'] !== "") {
                $query->where("a.status ", '=', $params['status']);
            }

            if (!empty($params['startDate']) && strtotime($params['startDate'])) {
                $startDate = gmdate('Y-m-d', strtotime($params['startDate']));
                $query->where('a.encounter_date', '>=', $startDate);
            }

            if (!empty($params['endDate']) && strtotime($params['endDate'])) {
                $endDate = gmdate('Y-m-d', strtotime($params['endDate']));
                $query->where('a.encounter_date', '<=', $endDate);
            }


            if (isset($params['search']) && !empty($params['search'])) {
                $query->where(function ($q) use ($params) {
                    $q->where("a.id", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("p.user_email", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("p.display_name", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("d.user_email", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("d.display_name", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("c.name", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("a.encounter_date", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("c.email", 'LIKE', '%' . $params['search'] . '%');
                });
            }

            if (!empty($params['id'])) {
                $query->where('a.id', intval($params['id']));
            }

            // Filter by patientId if provided in params
            if (isset($params['patientId']) && !empty($params['patientId'])) {
                $query->where('a.patient_id', '=', intval($params['patientId']));
            }

            // Role-based filter
            switch ($currentUserRole) {
                case $kcBase->getDoctorRole():
                    $query->where('a.doctor_id', $currentUserId);
                    break;

                case $kcBase->getPatientRole():
                    $query->where('a.patient_id', $currentUserId);
                    break;

                case $kcBase->getReceptionistRole():
                    // Receptionist: filter by clinics assigned in kc_receptionist_clinic_mappings
                    $clinicIds = KCReceptionistClinicMapping::query()
                        ->where('receptionist_id', $currentUserId)
                        ->select(['clinic_id'])
                        ->get()
                        ->map(fn($row) => $row->clinicId)
                        ->toArray();
                    if (!empty($clinicIds)) {
                        $query->whereIn('a.clinic_id', $clinicIds);
                    } else {
                        // No clinics assigned, return empty
                        $query->whereRaw('0=1');
                    }
                    break;

                case $kcBase->getClinicAdminRole():
                    $query->where('c.clinic_admin_id', $currentUserId);
                    break;

                case 'administrator':
                    // No restriction for admin
                    break;

                default:
                    return new \WP_Error(
                        'kc_permission_denied',
                        __('You do not have permission to view encounters.', 'kivicare-clinic-management-system'),
                        []
                    );
            }

            // Handle sorting
            $sortBy = isset($params['sortBy']) ? $params['sortBy'] : 'created_at';
            $sortOrder = isset($params['sortOrder']) ? strtoupper($params['sortOrder']) : 'DESC';

            // Validate sort order
            if (!in_array($sortOrder, ['ASC', 'DESC'])) {
                $sortOrder = 'DESC';
            }

            // Map frontend column names to database columns
            $sortColumnMap = [
                'id' => 'a.id',
                'patientName' => 'p.display_name',
                'doctorName' => 'd.display_name',
                'clinicName' => 'c.name',
                'encounterDate' => 'a.encounter_date',
                'status' => 'a.status',
                'created_at' => 'a.created_at'
            ];

            // Use mapped column or default to created_at
            $sortColumn = isset($sortColumnMap[$sortBy]) ? $sortColumnMap[$sortBy] : 'a.created_at';

            // Add ordering
            $query->orderBy($sortColumn, $sortOrder);

            // PAGINATION 
            $total = $query->count();

            $perPageParam = isset($params['perPage']) ? $params['perPage'] : 10;

            // Handle "all" option for perPage
            $showAll = (strtolower($perPageParam) === 'all');
            $perPage = $showAll ? null : (int) $perPageParam;
            $page = isset($params['page']) ? (int) $params['page'] : 1;

            if ($perPage <= 0 && !$showAll) {
                $perPage = 10;
            }

            if ($showAll) {
                $perPage = $total > 0 ? $total : 1;
                $page = 1;
            }

            $totalPages = $perPage > 0 ? ceil($total / $perPage) : 1;
            $offset = ($page - 1) * $perPage;

            // Apply pagination - only apply limit/offset if not showing all
            if (!$showAll) {
                $query->limit($perPage)->offset($offset);
            }
            $encounters = $query->get();
            $results = $encounters->map(function ($encounter) {
                return [
                    'id' => $encounter->id,
                    'encounterDate' => kcGetFormatedDate(gmdate('F j, Y', strtotime($encounter->encounterDate))),
                    'clinicId' => $encounter->clinicId,
                    'doctorId' => $encounter->doctorId,
                    'patientId' => $encounter->patientId,
                    'appointmentId' => $encounter->appointmentId,
                    'description' => $encounter->description,
                    'status' => $encounter->status,
                    'addedBy' => $encounter->addedBy,
                    'createdAt' => $encounter->createdAt,
                    'templateId' => $encounter->templateId,

                    'clinicName' => $encounter->clinic_name,
                    'clinic_image_url' => $encounter->clinic_profile_image ? wp_get_attachment_url($encounter->clinic_profile_image) : '',
                    'clinicEmail' => $encounter->clinic_email,
                    'doctorName' => $encounter->doctor_name,
                    'doctorEmail' => $encounter->doctor_email,
                    'doctor_image_url' => $encounter->doctor_profile_image ? wp_get_attachment_url($encounter->doctor_profile_image) : '',
                    'patientName' => $encounter->patient_name,
                    'patientEmail' => $encounter->patient_email,
                    'patient_image_url' => $encounter->patient_profile_image ? wp_get_attachment_url($encounter->patient_profile_image) : '',
                    'billId' => $encounter->bill_id,
                ];
            })->toArray();

            return $this->response([
                'encounters' => $results,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
            ], __('Encounters retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return new \WP_Error(
                'kc_encounter_fetch_failed',
                __('Failed to fetch encounters.', 'kivicare-clinic-management-system'),
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Create new encounter
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function createEncounter($request)
    {
        $params = $request->get_params();

        try {
            $encounter = KCPatientEncounter::create([
                'encounterDate' => $params['encounterDate'] ?? '',
                'clinicId' => intval($params['clinic'] ?? 0),
                'doctorId' => intval($params['doctor'] ?? 0),
                'patientId' => intval($params['patient'] ?? 0),
                'appointmentId' => intval($params['appointmentId'] ?? 0),
                'description' => sanitize_text_field($params['description'] ?? ''),
                'status' => intval($params['status'] ?? 1),
                'addedBy' => get_current_user_id(),
                'createdAt' => current_time('mysql', true),
            ]);

            // Handle case where create() returns an ID directly or an Object
            $encounterId = $encounter->id ?? $encounter;

            // Save custom field data if provided
            if (isset($params['customForm']) && is_array($params['customForm'])) {
                $this->saveCustomFieldData($params, 'encounter_module', $encounterId);
            }


            do_action('kc_encounter_save', ['id'=>$encounterId]);
            return $this->response([
                'id' => $encounterId,
            ], __('Encounter created successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return new \WP_Error(
                'kc_encounter_create_failed',
                __('Failed to create encounter.', 'kivicare-clinic-management-system'),
                ['error' => $e->getMessage()]
            );
        }
    }



    /**
     * Update existing encounter
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function updateEncounter($request)
    {
        $id = intval($request->get_param('id'));
        $params = $request->get_params();

        try {
            // Find the encounter
            $encounter = KCPatientEncounter::find($id);

            if (!$encounter) {
                return new \WP_Error(
                    'kc_encounter_not_found',
                    __('Encounter not found.', 'kivicare-clinic-management-system'),
                    ['id' => $id]
                );
            }

            // Role-based update permission check
            $kcBase = KCBase::get_instance();
            $currentUserRole = $kcBase->getLoginUserRole();
            $currentUserId = get_current_user_id();
            $canUpdate = false;
            switch ($currentUserRole) {
                case $kcBase->getDoctorRole():
                    $canUpdate = ($encounter->doctorId == $currentUserId);
                    break;
                case $kcBase->getPatientRole():
                    $canUpdate = ($encounter->patientId == $currentUserId);
                    break;
                case $kcBase->getReceptionistRole():
                    $clinicIds = KCReceptionistClinicMapping::query()
                        ->where('receptionistId', $currentUserId)
                        ->select(['clinic_id'])
                        ->get()
                        ->map(fn($row) => $row->clinicId)
                        ->toArray();
                    $canUpdate = in_array($encounter->clinicId, $clinicIds);
                    break;
                case $kcBase->getClinicAdminRole():
                    $clinic = KCClinic::find($encounter->clinicId);
                    $canUpdate = $clinic && $clinic->clinicAdminId == $currentUserId;
                    break;
                case 'administrator':
                    $canUpdate = true;
                    break;
                default:
                    $canUpdate = false;
            }
            if (!$canUpdate) {
                return new \WP_REST_Response(new \WP_Error(
                    'kc_permission_denied',
                    __('You do not have permission to update this encounter.', 'kivicare-clinic-management-system'),
                    ['id' => $id]
                ), 403);
            }

            // Manually update fields if provided
            if (isset($params['encounterDate'])) {
                $encounter->encounterDate = sanitize_text_field($params['encounterDate']);
            }

            if (isset($params['clinic'])) {
                $encounter->clinicId = intval($params['clinic']);
            }

            if (isset($params['doctor'])) {
                $encounter->doctorId = intval($params['doctor']);
            }

            if (isset($params['patient'])) {
                $encounter->patientId = intval($params['patient']);
            }

            if (isset($params['appointmentId'])) {
                $encounter->appointmentId = intval($params['appointmentId']);
            }

            if (isset($params['description'])) {
                $encounter->description = sanitize_text_field($params['description']);
            }

            if (isset($params['status'])) {
                $encounter->status = intval($params['status']);
            }

            if (isset($params['templateId'])) {
                $encounter->templateId = intval($params['templateId']);
            }

            // Save the encounter
            $encounter->save();

            // Save custom field data if provided
            if (isset($params['customForm']) && is_array($params['customForm'])) {
                $this->saveCustomFieldData($params, 'encounter_module', $encounter->id);
            }

            do_action('kc_encounter_update', ['id' => $encounter->id]);

            return $this->response([
                'id' => $encounter->id,
            ], __('Encounter updated successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return new \WP_Error(
                'kc_encounter_update_failed',
                __('Failed to update encounter.', 'kivicare-clinic-management-system'),
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Bulk update encounter status
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function bulkUpdateStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $ids = $request->get_param('ids');
            $status = $request->get_param('status');
            $success_count = 0;
            $failed_count = 0;
            $failed_ids = [];

            foreach ($ids as $id) {
                // Check if encounter exists
                $encounter = KCPatientEncounter::find($id);
                if (empty($encounter)) {
                    $failed_count++;
                    $failed_ids[] = $id;
                    continue;
                }

                // Update status using the model
                $encounter->status = $status;
                $updated = $encounter->save();

                if (!is_wp_error($updated)) {
                    $success_count++;
                } else {
                    $failed_count++;
                    $failed_ids[] = $id;
                }
            }

            if ($success_count > 0) {
                return $this->response(
                    [
                        'success_count' => $success_count,
                        'failed_count' => $failed_count,
                        'failed_ids' => $failed_ids,
                        'status' => $status
                    ],
                    /* translators: %d: number of encounters */
                    sprintf(__('Status updated for %d encounters', 'kivicare-clinic-management-system'), $success_count),
                    true,
                    200
                );
            } else {
                return $this->response(
                    [
                        'success_count' => 0,
                        'failed_count' => $failed_count,
                        'failed_ids' => $failed_ids,
                        'status' => $status
                    ],
                    __('Failed to update encounter status', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

    /**
     * Bulk delete encounters
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function bulkDeleteEncounters(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $ids = $request->get_param('ids');
            $success_count = 0;
            $failed_count = 0;
            $failed_ids = [];
            global $wpdb;
            $wpdb->query('START TRANSACTION');
            try {
                foreach ($ids as $encounterId) {
                    try {
                        $encounter = KCPatientEncounter::find($encounterId);
                        if (empty($encounter)) {
                            $failed_count++;
                            $failed_ids[] = [
                                'id' => $encounterId,
                                'reason' => __('Encounter not found', 'kivicare-clinic-management-system')
                            ];
                            continue;
                        }
                        // First, get all bill IDs for this encounter
                        $bills = KCBill::query()->where('encounterId', $encounterId)->select(['id'])->get();
                        $billIds = $bills->map(function ($bill) {
                            return $bill->id;
                        })->toArray();

                        // Delete tax data before deleting bills
                        do_action('kc_before_delete_encounter', $encounterId);

                        // Delete bill items first (child records) - only if there are bills
                        if (!empty($billIds)) {
                            KCBillItem::query()->whereIn('billId', $billIds)->delete();
                        }
                        // Delete bills directly by encounter ID (more reliable approach)
                        KCBill::query()->where('encounterId', $encounterId)->delete();

                        KCMedicalHistory::query()->where('encounterId', $encounterId)->delete();

                        // Delete related appointment if exists
                        if ($encounter->appointmentId) {
                            KCAppointmentServiceMapping::query()->where('appointment_id', $encounter->appointmentId)->delete();
                            KCPaymentsAppointmentMapping::query()->where('appointment_id', $encounter->appointmentId)->delete();
                            KCAppointment::query()->where('id', $encounter->appointmentId)->delete();
                        }

                        $deleted = KCPatientEncounter::query()->where('id', $encounterId)->delete();
                        if ($deleted) {
                            $success_count++;
                        } else {
                            $failed_count++;
                            $failed_ids[] = [
                                'id' => $encounterId,
                                'reason' => __('Failed to delete encounter', 'kivicare-clinic-management-system')
                            ];
                        }
                    } catch (\Exception $e) {
                        $failed_count++;
                        $failed_ids[] = [
                            'id' => $encounterId,
                            'reason' => $e->getMessage()
                        ];
                    }
                }
                $wpdb->query('COMMIT');
                if ($success_count > 0) {
                    return $this->response(
                        [
                            'success_count' => $success_count,
                            'failed_count' => $failed_count,
                            'failed_ids' => $failed_ids
                        ],
                        /* translators: %d: number of encounters */
                        sprintf(__('%d encounters deleted successfully', 'kivicare-clinic-management-system'), $success_count),
                        true,
                        200
                    );
                } else {
                    return $this->response(
                        [
                            'success_count' => 0,
                            'failed_count' => $failed_count,
                            'failed_ids' => $failed_ids
                        ],
                        __('Failed to delete encounters', 'kivicare-clinic-management-system'),
                        false,
                        400
                    );
                }
            } catch (\Exception $e) {
                $wpdb->query('ROLLBACK');
                throw $e;
            }
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

    /**
     * Delete encounter
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function deleteEncounter(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');

            // Check if encounter exists
            $encounter = KCPatientEncounter::find($id);
            if (empty($encounter)) {
                return $this->response(
                    null,
                    __('Encounter not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            global $wpdb;
            $wpdb->query('START TRANSACTION');
            try {
                // First, get all bill IDs for this encounter
                $bills = KCBill::query()->where('encounterId', $id)->select(['id'])->get();
                $billIds = $bills->map(function ($bill) {
                    return $bill->id;
                })->toArray();

                // Delete tax data before deleting bills
                do_action('kc_before_delete_encounter', $id);

                // Delete bill items first (child records) - only if there are bills
                if (!empty($billIds)) {
                    KCBillItem::query()->whereIn('billId', $billIds)->delete();
                }
                // Delete bills directly by encounter ID (more reliable approach)
                KCBill::query()->where('encounterId', $id)->delete();

                // Delete related medical history
                KCMedicalHistory::query()->where('encounterId', $id)->delete();

                // Delete related appointment if exists
                if ($encounter->appointmentId) {
                    KCAppointmentServiceMapping::query()->where('appointment_id', $encounter->appointmentId)->delete();
                    KCPaymentsAppointmentMapping::query()->where('appointment_id', $encounter->appointmentId)->delete();
                    KCAppointment::query()->where('id', $encounter->appointmentId)->delete();
                }

                // Delete the encounter
                $deleted = $encounter->delete();
                if (!$deleted) {
                    throw new \Exception(__('Failed to delete encounter', 'kivicare-clinic-management-system'));
                }
                $wpdb->query('COMMIT');
                return $this->response(
                    ['id' => $id],
                    __('Encounter deleted successfully', 'kivicare-clinic-management-system'),
                    true,
                    200
                );
            } catch (\Exception $e) {
                $wpdb->query('ROLLBACK');
                throw $e;
            }
        } catch (\Exception $e) {
            return $this->response(
                null,
                $e->getMessage(),
                false,
                500
            );
        }
    }
}
