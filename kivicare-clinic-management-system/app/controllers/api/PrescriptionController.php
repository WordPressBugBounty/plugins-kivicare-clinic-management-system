<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\models\KCPrescription;
use App\emails\KCEmailTemplateManager;
use App\emails\KCEmailTemplateProcessor;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class PrescriptionController
 * 
 * API Controller for Prescriptions
 */
class PrescriptionController extends KCBaseController
{
    protected $route = 'prescriptions';

    public function __construct()
    {
        parent::__construct();

        if (!$this->isModuleEnabled('prescription')) {
            wp_send_json([
                'status'  => false,
                'message' => __('"Prescription" module is disabled.', 'kivicare-clinic-management-system'),
                'data'    => []
            ], 403);
            exit;
        }
    }

    public function registerRoutes()
    {
        // List prescriptions for a patient or encounter
        $this->registerRoute('/' . $this->route, [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPrescriptions'],
            'permission_callback' => [$this, 'checkPermission'],
            'args'                => $this->getListEndpointArgs()
        ]);

        // Get single prescription
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPrescription'],
            'permission_callback' => [$this, 'checkPermission'],
            'args'                => $this->getSingleEndpointArgs()
        ]);

        // Create prescription
        $this->registerRoute('/' . $this->route, [
            'methods'             => 'POST',
            'callback'            => [$this, 'createPrescription'],
            'permission_callback' => [$this, 'checkCreatePermission'],
            'args'                => $this->getCreateEndpointArgs()
        ]);

        // Update prescription
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'updatePrescription'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args'                => $this->getUpdateEndpointArgs()
        ]);

        // Delete prescription
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'deletePrescription'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args'                => $this->getSingleEndpointArgs()
        ]);

        // Export prescriptions
        $this->registerRoute('/' . $this->route . '/export', [
            'methods'             => 'GET',
            'callback'            => [$this, 'exportPrescriptions'],
            'permission_callback' => [$this, 'checkPermission'],
            'args'                => $this->getExportEndpointArgs()
        ]);

        // Email prescription
        $this->registerRoute('/' . $this->route . '/(?P<encounter_id>\d+)/email', [
            'methods'             => 'POST',
            'callback'            => [$this, 'emailPrescription'],
            'permission_callback' => [$this, 'checkPermission'],
            'args'                => [
                'encounter_id' => [
                    'description'       => 'Encounter ID',
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ]
            ]
        ]);
    }

    private function getListEndpointArgs()
    {
        return [
            'patient_id' => [
                'description'       => 'Patient ID',
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => 'absint',
            ],
            'encounter_id' => [
                'description'       => 'Encounter ID',
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    private function getSingleEndpointArgs()
    {
        return [
            'id' => [
                'description'       => 'Prescription ID',
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    private function getCreateEndpointArgs()
    {
        return [
            'encounter_id' => [
                'description'       => 'Encounter ID',
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
            ],
            'patient_id' => [
                'description'       => 'Patient ID',
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
            ],
            'name' => [
                'description'       => 'Prescription name',
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'frequency' => [
                'description'       => 'Frequency',
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'duration' => [
                'description'       => 'Duration',
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'instruction' => [
                'description'       => 'Instruction',
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'added_by' => [
                'description'       => 'Added by user ID',
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
            ],
            'is_from_template' => [
                'description'       => 'Is from template',
                'type'              => 'boolean',
                'required'          => false,
                'sanitize_callback' => 'boolval',
            ],
        ];
    }

    private function getUpdateEndpointArgs()
    {
        $args = $this->getCreateEndpointArgs();
        $args['id'] = [
            'description'       => 'Prescription ID',
            'type'              => 'integer',
            'required'          => true,
            'sanitize_callback' => 'absint',
        ];
        return $args;
    }

    private function getExportEndpointArgs()
    {
        return [
            'format' => [
                'description'       => 'Export format',
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'patient_id' => [
                'description'       => 'Patient ID',
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => 'absint',
            ],
            'encounter_id' => [
                'description'       => 'Encounter ID',
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    public function checkPermission($request)
    {
        return $this->checkCapability('read');
    }
    public function checkCreatePermission($request)
    {
        return $this->checkCapability('read');
    }
    public function checkUpdatePermission($request)
    {
        return $this->checkCapability('read');
    }
    public function checkDeletePermission($request)
    {
        return $this->checkCapability('read');
    }

    public function getPrescriptions(WP_REST_Request $request): WP_REST_Response
    {
        $patientId = $request->get_param('patient_id');
        $encounterId = $request->get_param('encounter_id');
        $query = KCPrescription::query();
        if ($patientId) $query->where('patient_id', $patientId);
        if ($encounterId) $query->where('encounter_id', $encounterId);
        $prescriptions = $query->get();
        $data = [];
        foreach ($prescriptions as $prescription) {
            $data[] = $this->formatPrescription($prescription);
        }
        return $this->response($data, __('Prescriptions retrieved successfully', 'kivicare-clinic-management-system'));
    }

    public function getPrescription(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $prescription = KCPrescription::find($id);
        if (!$prescription) {
            return $this->response(null, __('Prescription not found', 'kivicare-clinic-management-system'), false, 404);
        }
        return $this->response($this->formatPrescription($prescription), __('Prescription retrieved successfully', 'kivicare-clinic-management-system'));
    }

    public function createPrescription(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();
        $prescription = new KCPrescription();
        $prescription->encounterId = $params['encounter_id'];
        $prescription->patientId = $params['patient_id'];
        $prescription->name = $params['name'];
        $prescription->frequency = $params['frequency'] ?? '';
        $prescription->duration = $params['duration'] ?? '';
        $prescription->instruction = $params['instruction'] ?? '';
        $prescription->addedBy = $params['added_by'];
        $prescription->isFromTemplate = $params['is_from_template'] ?? 0;
        $prescription->createdAt = current_time('mysql');
        if (!$prescription->save()) {
            return $this->response(null, __('Failed to create prescription', 'kivicare-clinic-management-system'), false, 500);
        }
        return $this->response($this->formatPrescription($prescription), __('Prescription created successfully', 'kivicare-clinic-management-system'), true, 201);
    }

    public function updatePrescription(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $params = $request->get_params();
        $prescription = KCPrescription::find($id);
        if (!$prescription) {
            return $this->response(null, __('Prescription not found', 'kivicare-clinic-management-system'), false, 404);
        }
        if (isset($params['name'])) $prescription->name = $params['name'];
        if (isset($params['frequency'])) $prescription->frequency = $params['frequency'];
        if (isset($params['duration'])) $prescription->duration = $params['duration'];
        if (isset($params['instruction'])) $prescription->instruction = $params['instruction'];
        if (isset($params['is_from_template'])) $prescription->isFromTemplate = $params['is_from_template'];
        if (!$prescription->save()) {
            return $this->response(null, __('Failed to update prescription', 'kivicare-clinic-management-system'), false, 500);
        }
        return $this->response($this->formatPrescription($prescription), __('Prescription updated successfully', 'kivicare-clinic-management-system'));
    }

    public function deletePrescription(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $prescription = KCPrescription::find($id);
        if (!$prescription) {
            return $this->response(null, __('Prescription not found', 'kivicare-clinic-management-system'), false, 404);
        }
        if (!$prescription->delete()) {
            return $this->response(null, __('Failed to delete prescription', 'kivicare-clinic-management-system'), false, 500);
        }
        return $this->response(['id' => $id], __('Prescription deleted successfully', 'kivicare-clinic-management-system'));
    }

    private function formatPrescription($prescription)
    {
        return [
            'id'               => $prescription->id,
            'encounter_id'     => $prescription->encounterId,
            'patient_id'       => $prescription->patientId,
            'name'             => $prescription->name,
            'frequency'        => $prescription->frequency,
            'duration'         => $prescription->duration,
            'instruction'      => $prescription->instruction,
            'added_by'         => $prescription->addedBy,
            'created_at'       => $prescription->createdAt,
            'is_from_template' => $prescription->isFromTemplate,
        ];
    }

    public function exportPrescriptions(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();
            $patientId = isset($params['patient_id']) ? (int)$params['patient_id'] : null;
            $encounterId = isset($params['encounter_id']) ? (int)$params['encounter_id'] : null;

            $query = KCPrescription::query();
            if ($patientId) $query->where('patient_id', $patientId);
            if ($encounterId) $query->where('encounter_id', $encounterId);

            $prescriptions = $query->get();

            $exportData = [];
            foreach ($prescriptions as $prescription) {
                $exportData[] = [
                    'name'        => $prescription->name ?: '-',
                    'frequency'   => $prescription->frequency ?: '-',
                    'duration'    => $prescription->duration ? $prescription->duration . ' days' : '-',
                    'instruction' => $prescription->instruction ?: 'No instruction',
                ];
            }

            return $this->response(
                ['prescriptions' => $exportData],
                __('Prescriptions data retrieved successfully', 'kivicare-clinic-management-system'),
                true,
                200
            );
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to export prescriptions', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    public function emailPrescription(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $encounterId = $request->get_param('encounter_id');
            $prescriptions = KCPrescription::query()->where('encounter_id', $encounterId)->get();

            if ($prescriptions->isEmpty()) {
                return $this->response(false, __('No prescriptions found for this encounter', 'kivicare-clinic-management-system'), false, 404);
            }

            $encounter = $prescriptions->first()->getEncounter();
            if (!$encounter) {
                return $this->response(false, __('Encounter not found', 'kivicare-clinic-management-system'), false, 404);
            }

            $patient = $encounter->getPatient();
            if (!$patient || empty($patient->email)) {
                return $this->response(false, __('Patient email not found', 'kivicare-clinic-management-system'), false, 400);
            }

            $clinic = $encounter->getClinic();
            $doctor = $encounter->getDoctor();

            ob_start();
            include KIVI_CARE_DIR . '/templates/PrescriptionEmailTable.php';
            $prescriptionText = ob_get_clean();

            $templateManager = KCEmailTemplateManager::getInstance();
            $template = $templateManager->getTemplate(KIVI_CARE_PREFIX . 'patient_prescription');

            if ($template) {
                $templateProcessor = new KCEmailTemplateProcessor();
                $emailData = [
                    'prescription' => $prescriptionText,
                    'clinic_name' => $clinic ? $clinic->name : '',
                    'doctor_name' => $doctor ? $doctor->display_name : '',
                    'current_date' => current_time('Y-m-d'),
                ];
                $subject = $templateProcessor->processTemplate($template->post_title, $emailData);
                $message = $templateProcessor->processTemplate($template->post_content, $emailData);
            } else {
                $subject = __('Your Prescription', 'kivicare-clinic-management-system');
                $message = '<p>' . __('Your prescriptions:', 'kivicare-clinic-management-system') . '</p><p>' . $prescriptionText . '</p>';
            }

            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent = wp_mail($patient->email, $subject, $message, $headers);

            if ($sent) {
                return $this->response(true, __('Prescription email sent successfully', 'kivicare-clinic-management-system'));
            }
            return $this->response(false, __('Failed to send prescription email', 'kivicare-clinic-management-system'), false, 500);
        } catch (\Exception $e) {
            return $this->response(false, $e->getMessage(), false, 500);
        }
    }
}
