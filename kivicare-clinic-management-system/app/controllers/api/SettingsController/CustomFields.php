<?php

namespace App\controllers\api\SettingsController;

use App\baseClasses\KCErrorLogger;
use App\controllers\api\SettingsController;
use App\models\KCStaticData;
use App\models\KCDoctorClinicMapping;
use App\models\KCCustomField;
use App\models\KCCustomFieldData;
use App\models\KCAppointment;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class CustomFields
 * 
 * @package App\controllers\api\SettingsController
 * @method bool isModuleEnabled(string $module_name)
 */
class CustomFields extends SettingsController
{
    private static $instance = null;

    protected $route = 'setting/custom-field';


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
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        // Get Custom Field List
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getCustomFields'],
            'permission_callback' => [$this, 'checkPermission'],
            //'args' => $this->getSettingsEndpointArgs()
        ]);
        // Get Custom Field File Upload
        $this->registerRoute('/' . $this->route . '/file-upload', [
            'methods' => 'GET',
            'callback' => [$this, 'fileUploadData'],
            'permission_callback' => [$this, 'checkPermission'],
            //'args' => $this->getSettingsEndpointArgs()
        ]);
        // Get Custom Field Edit
        $this->registerRoute('/' . $this->route . '/edit', [
            'methods' => 'GET',
            'callback' => [$this, 'dataEdit'],
            'permission_callback' => [$this, 'checkPermission'],
            //  'args' => $this->getSettingsEndpointArgs()
        ]);
        // Custom Field Delete
        $this->registerRoute('/' . $this->route . '/delete', [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'dataDelete'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            // 'args'     => $this->getSettingFieldSchema()['custom_field_delete']
        ]);
        // Update Custom Field
        $this->registerRoute('/' . $this->route . '/update', [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'dataUpdate'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            // 'args'     => $this->getSettingFieldSchema()['custom_field_update']
        ]);
        // Custom Fields Import
        $this->registerRoute('/' . $this->route . '/import', [
            'methods' => ['PUT', 'POST'],
            'callback' => ['dataImport'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            //'args' => $this->getUpdateEndpointArgs()
        ]);

        // Update Custom Field Status
        $this->registerRoute('/' . $this->route . '/status', [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'updateStatus'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
        ]);

        // Export custom fields
        $this->registerRoute('/' . $this->route . '/export', [
            'methods' => 'GET',
            'callback' => [$this, 'exportCustomFields'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getExportEndpointArgs()
        ]);

        // Get custom fields by module
        $this->registerRoute('/' . $this->route . '/by-module', [
            'methods' => 'GET',
            'callback' => [$this, 'getCustomFieldsByModule'],
            'permission_callback' => '__return_true'
        ]);

        // Save custom field data
        $this->registerRoute('/' . $this->route . '/save-data', [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'saveCustomFieldData'],
            'permission_callback' => function () {
                return $this->isModuleEnabled('custom_fields');
            },
        ]);

        // Get custom field data
        $this->registerRoute('/' . $this->route . '/get-data', [
            'methods' => 'GET',
            'callback' => [$this, 'getCustomFieldData'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

    }

    /**
     * Check if user has permission to access custom field read endpoints.
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkPermission($request): bool
    {
        if (!$this->isModuleEnabled('custom_fields')) {
            return false;
        }

        return parent::checkPermission($request);
    }

    /**
     * Check if user has permission to update custom fields.
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkUpdatePermission($request): bool
    {
        if (!$this->isModuleEnabled('custom_fields')) {
            return false;
        }

        return parent::checkUpdatePermission($request);
    }

    /**
     * Get CustomFields settings
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getCustomFields(WP_REST_Request $request): WP_REST_Response
    {

        $request_data = $request->get_params();
        $query = KCCustomField::query();

        // Exclude 'appointment_module' if not Pro
        if (!isKiviCareProActive()) {
            $query = $query->where('module_type', '!=', 'appointment_module');
        }

        // Search filter
        if (isset($request_data['searchTerm']) && trim($request_data['searchTerm']) !== '') {
            $searchTerm = esc_sql(strtolower(trim($request_data['searchTerm'])));
            $status = null;

            if (preg_match('/:(active|inactive)/i', $searchTerm, $matches)) {
                $status = $matches[1] === 'active' ? '1' : '0';
                $searchTerm = trim(preg_replace('/:(active|inactive)/i', '', $searchTerm));
            }

            $searchTermUnderscore = str_replace(' ', '_', $searchTerm);

            $query->whereRaw("(
                    id LIKE '%s' OR 
                    module_type LIKE '%s' OR 
                    module_type LIKE '%s' OR 
                    LOWER(JSON_UNQUOTE(JSON_EXTRACT(fields, '$.name'))) LIKE '%s' OR 
                    LOWER(JSON_UNQUOTE(JSON_EXTRACT(fields, '$.type'))) LIKE '%s'
                )", [
                '%' . $searchTerm . '%',
                '%' . $searchTerm . '%',
                '%' . $searchTermUnderscore . '%',
                '%' . $searchTerm . '%',
                '%' . $searchTerm . '%',
            ]);

            if (!is_null($status)) {
                $query = $query->where('status', '=', $status);
            }
        } else {
            // Column filters
            $filters = [
                'input_type' => $request_data['input_type'] ?? '',
                'module_type' => $request_data['module_type'] ?? '',
                'status' => $request_data['status'] ?? '',
            ];

            foreach ($filters as $key => $value) {
                $value = esc_sql(strtolower(trim($value)));
                if ($value === '')
                    continue;

                if ($key === 'input_type') {
                    $query = $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(fields, '$.type'))) = '{$value}'");
                } elseif ($key === 'status') {
                    $query = $query->where('status', '=', $value);
                } elseif ($key === 'fields') {
                    $query = $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(fields, '$.label'))) LIKE '%{$value}%'");
                } else {
                    $query = $query->where($key, 'LIKE', "%{$value}%");
                }
            }
        }

        // Sorting
        $sort_by = $request_data['orderby'] ?? 'id';
        $sort_order = strtoupper($request_data['order'] ?? 'DESC');

        if (!in_array($sort_order, ['ASC', 'DESC'])) {
            $sort_order = 'DESC';
        }

        switch ($sort_by) {
            case 'field':
                $query = $query->orderBy("JSON_UNQUOTE(JSON_EXTRACT(fields, '$.name'))", $sort_order);
                break;
            case 'inputType':
                $query = $query->orderBy("JSON_UNQUOTE(JSON_EXTRACT(fields, '$.type'))", $sort_order);
                break;
            case 'type':
                $query = $query->orderBy('module_type', $sort_order);
                break;
            case 'id':
            default:
                $query = $query->orderBy('id', $sort_order);
                break;
        }

        $total = $query->count();
        // Pagination
        $page = (int) ($request_data['page'] ?? 1);
        $per_page_param = $request_data['perPage'] ?? 10;
        $perPage = (int) $per_page_param;
        // When "all" is selected, (int)"all" becomes 0 — fetch all records
        if ($per_page_param === 'all' || $perPage <= 0) {
            $perPage = $total;
            $page = 1;
        }
        $offset = $perPage > 0 ? ($page - 1) * $perPage : 0;
        $query->limit($perPage)->offset($offset);

        $customFields = $query->get();

        // Prepare exact response fields
        $customFieldData = [];
        foreach ($customFields as $field) {
            $fields = is_array($field->fields) ? $field->fields : (is_string($field->fields) ? json_decode($field->fields, true) : []);
            
            // Rename multiselect to multi_select
            if (isset($fields['type']) && $fields['type'] === 'multiselect') {
                $fields['type'] = 'multi_select';
            }

            if (isset($fields['options']) && is_array($fields['options'])) {
                $fields['options'] = $this->normalizeCustomFieldOptions($fields['options']);
            }

            $customFieldData[] = [
                'id' => (string) $field->id,
                'module_type' => $field->moduleType ?? '',
                'module_id' => (string) ($field->moduleId ?? 0),
                'fields' => json_encode($fields),
                'status' => (string) $field->status,
                'created_at' => $field->createdAt ?? '',
            ];
        }
        // Return formatted response
        return $this->response([
            'data' => $customFieldData,
            'pagination' => [
                'total' => $total,
                'perPage' => $perPage,
                'currentPage' => $page,
                'lastPage' => max(1, ceil($total / max(1, $perPage)))
            ]
        ], __('Custom fields records', 'kivicare-clinic-management-system'));
    }

    /**
     * Data Delete
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function dataDelete(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $request_data = $request->get_json_params();

            if (!isset($request_data['id'])) {
                return $this->response(null, esc_html__('Data not found', 'kivicare-clinic-management-system'), false);
            }

            $id = (int) $request_data['id'];

            $results = KCCustomField::query()->where('id', '=', $id)->delete();
            $dataResults = KCCustomFieldData::query()->where('field_id', '=', $id)->delete();

            if ($results) {
                return $this->response(null, esc_html__('Custom field has been deleted successfully', 'kivicare-clinic-management-system'));
            } else {
                return $this->response(null, esc_html__('Custom field delete failed', 'kivicare-clinic-management-system'), false);
            }
        } catch (\Exception $e) {

            $code = $e->getCode();
            $message = $e->getMessage();


            return $this->response(null, $message, false);
        }
    }

    /**
     * Update Custom Field Status
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function updateStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $request_data = $request->get_json_params();

            if (!isset($request_data['id'])) {
                return $this->response(null, esc_html__('Custom field ID is required', 'kivicare-clinic-management-system'), false);
            }

            if (!isset($request_data['status'])) {
                return $this->response(null, esc_html__('Status is required', 'kivicare-clinic-management-system'), false);
            }

            $id = (int) $request_data['id'];
            $status = (int) $request_data['status'];

            // Validate status value
            if (!in_array($status, [0, 1])) {
                return $this->response(null, esc_html__('Status must be 0 (inactive) or 1 (active)', 'kivicare-clinic-management-system'), false);
            }

            $custom_field = KCCustomField::find($id);

            if (!$custom_field) {
                return $this->response(null, esc_html__('Custom field not found', 'kivicare-clinic-management-system'), false);
            }

            $custom_field->status = $status;
            $saveResult = $custom_field->save();

            if ($saveResult) {
                return $this->response(null, esc_html__('Custom field status has been updated successfully', 'kivicare-clinic-management-system'));
            } else {
                return $this->response(null, esc_html__('Failed to update custom field status', 'kivicare-clinic-management-system'), false);
            }
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update status', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * static data edit
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function dataEdit(WP_REST_Request $request): WP_REST_Response
    {
        try {

            $request_data = $request->get_params();

            if (!isset($request_data['id'])) {
                return $this->response(null, esc_html__('Data not found', 'kivicare-clinic-management-system'), false);
            }

            $id = (int) $request_data['id'];

            $custom_field = KCCustomField::get_by(['id' => $id], '=', true);

            if (empty($custom_field)) {
                return $this->response(null, esc_html__('Data not found', 'kivicare-clinic-management-system'), false);
            }

            if (!empty($custom_field->moduleId)) {
                $user_data = get_userdata((int) $custom_field->moduleId);
                if ($user_data) {
                    $custom_field->moduleId = [
                        'id' => $user_data->ID,
                        'label' => $user_data->display_name
                    ];
                }
            } else {
                $custom_field->moduleId = null;
            }

            $fields = is_string($custom_field->fields) ? json_decode($custom_field->fields) : (object) $custom_field->fields;

            // Rename multiselect to multi_select
            if (isset($fields->type) && $fields->type === 'multiselect') {
                $fields->type = 'multi_select';
            }

            $fields->type = [
                'id' => $fields->type,
                'label' => ucfirst(str_replace("_", " ", $fields->type))
            ];

            $fields->status = [
                'id' => in_array($custom_field->status, [1, '1']) ? 1 : 0,
                'label' => in_array($custom_field->status, [1, '1']) ? esc_html__('Active', 'kivicare-clinic-management-system') : esc_html__('Inactive', 'kivicare-clinic-management-system')
            ];

            if (isset($fields->options) && is_array($fields->options)) {
                $fields->options = $this->normalizeCustomFieldOptions($fields->options);
            }

            $temp = [
                'id' => $custom_field->id,
                'module_type' => [
                    'id' => $custom_field->moduleType,
                    'label' => str_replace("_", " ", $custom_field->moduleType)
                ],
                'module_id' => $custom_field->moduleId,
                'fields' => $fields,
                'status' => $custom_field->status
            ];

            return $this->response($temp, esc_html__('Custom field record', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();

            header("Status: $code $message");

            return $this->response(
                ['error' => $e->getMessage()],
                $message,
                false,
                500
            );
        }
    }

    /**
     * Update Data
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function dataUpdate(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $request_data = $request->get_json_params();
            $custome_field = new KCCustomField();
            $fields = [];

            if (isset($request_data['fields']) && is_array($request_data['fields'])) {
                foreach ($request_data['fields'] as $field) {
                    $fields[] = [
                        'label' => $field['label'],
                        'type' => $field['type'],
                        'name' => $field['label'], // Duplicate of label for compatibility
                        'options' => $field['options'] ?? [],
                        'file_upload_type' => $field['file_types'] ?? [],
                        'isRequired' => (string) ($field['isRequired'] ?? '0'),
                        'placeholder' => $field['placeholder'] ?? '',
                        'status' => (string) ($field['status'] ?? '1'),
                    ];
                }
            }

            $user_id = get_current_user_id();
            if ($this->kcbase->getLoginUserRole() == $this->kcbase->getDoctorRole()) {
                $module_types = ['patient_module', 'patient_encounter_module', 'appointment_module'];
                if (in_array($request_data['module_type']['id'], $module_types)) {
                    $request_data['module_id'] = $user_id;
                }
            }

            $status = isset($fields[0]['status']) ? $fields[0]['status'] : 1;

            if (isset($request_data['id']) && empty($request_data['id'])) {
                // Handle module_id - can be empty (all doctors) or specific doctor
                if (empty($request_data['module_id'])) {
                    // Empty module_id = apply to all doctors (module_id = 0)
                    $module_id = 0;
                    $this->savecustomField($request_data['module_type']['id'], $module_id, json_encode($fields[0]), $status);
                } else {
                    // Specific doctor selected
                    $doctor = $request_data['module_id'];
                    $module_id = is_array($doctor) ? ($doctor['id'] ?? 0) : (is_numeric($doctor) ? (int) $doctor : 0);

                    if ($module_id > 0) {
                        // Validate doctor exists
                        $user_data = get_userdata($module_id);

                        if (!$user_data || !in_array($this->kcbase->getDoctorRole(), $user_data->roles)) {
                            return $this->response(
                                null,
                                sprintf(
                                    /* translators: %d: doctor ID */
                                    esc_html__('Doctor with ID %d does not exist or is not a valid doctor', 'kivicare-clinic-management-system'),
                                    $module_id
                                ),
                                false
                            );
                        }
                    }
                    $this->savecustomField($request_data['module_type']['id'], $module_id, json_encode($fields[0]), $status);
                }
                $message = esc_html__('Custom fields have been saved successfully', 'kivicare-clinic-management-system');
            } else {
                // Update existing custom field
                if (empty($request_data['module_id'])) {
                    $module_id = 0;
                } else {
                    if (is_array($request_data['module_id']) && isset($request_data['module_id']['id'])) {
                        $module_id = (int) $request_data['module_id']['id'];
                    } else {
                        $module_id = is_numeric($request_data['module_id']) ? (int) $request_data['module_id'] : 0;
                    }

                    // Validate doctor if module_id is provided
                    if ($module_id > 0) {
                        $user_data = get_userdata($module_id);

                        if (!$user_data || !in_array($this->kcbase->getDoctorRole(), $user_data->roles)) {
                            return $this->response(
                                null,
                                sprintf(
                                    /* translators: %d: doctor ID */
                                    esc_html__('Doctor with ID %d does not exist or is not a valid doctor', 'kivicare-clinic-management-system'),
                                    $module_id
                                ),
                                false
                            );
                        }
                    }
                }

                $temp = [
                    'moduleType' => $request_data['module_type']['id'],
                    'moduleId' => $module_id,
                    'fields' => json_encode($fields[0]),
                    'status' => $status,
                ];

                $custome_field = KCCustomField::find((int) $request_data['id']);
                if ($custome_field) {
                    $custome_field->moduleType = $temp['moduleType'];
                    $custome_field->moduleId = $temp['moduleId'];
                    $custome_field->fields = $temp['fields'];
                    $custome_field->status = $temp['status'];
                    $saveResult = $custome_field->save();
                    $message = esc_html__('Custom fields have been updated successfully', 'kivicare-clinic-management-system');
                } else {
                    $message = esc_html__('Custom fields have not been updated.', 'kivicare-clinic-management-system');
                }
            }
            return $this->response(null, $message);
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Update Error: " . $e->getMessage());
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update settings', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    public function dataImport(WP_REST_Request $request): WP_REST_Response
    {


        $request_data = $request->get_json_params();

        $rules = [
            'module_type' => 'required',
        ];

        $errors = kcValidateRequest($rules, $request_data);

        if (count($errors)) {

            return $this->response(null, $errors[0], false);
        }

        $response = apply_filters('kcpro_import_demo_files', [
            'data' => $request_data,
        ]);

        wp_send_json($response);
    }

    public function fileUploadData(WP_REST_Request $request): WP_REST_Response
    {

        // Define the size options for file uploads
        $kb = 1024 * 1024;
        $max_file_size = wp_max_upload_size();
        $size_options = [
            ['text' => esc_html__("1MB", "kivicare-clinic-management-system"), 'id' => 1 * $kb],
            ['text' => esc_html__("2MB", "kivicare-clinic-management-system"), 'id' => 2 * $kb],
            ['text' => esc_html__("5MB", "kivicare-clinic-management-system"), 'id' => 5 * $kb],
            ['text' => esc_html__("10MB", "kivicare-clinic-management-system"), 'id' => 10 * $kb],
            ['text' => esc_html__("20MB", "kivicare-clinic-management-system"), 'id' => 20 * $kb],
            ['text' => esc_html__("50MB", "kivicare-clinic-management-system"), 'id' => 50 * $kb],
        ];

        // Allow filtering of size options
        $size_options = apply_filters("kivicare_custom_field_upload_file_size_options", $size_options);

        $size_options = array_map(function ($v) use ($max_file_size) {
            if ($v['id'] > $max_file_size) {
                $v['$isDisabled'] = true;
            }
            return $v;
        }, $size_options);
        // Prepare the type options for file uploads
        $type_options = [];
        foreach (get_allowed_mime_types() as $key => $value) {
            $type_options[] = [
                'text' => str_replace('|', ' ', $key),
                'id' => $value
            ];
        }
        global $wp_roles;
        $roles = $wp_roles->roles;
        $all_roles = [];
        foreach ($roles as $role_id => $role_info) {
            if (in_array($role_id, ['administrator', 'kiviCare_clinic_admin', 'kiviCare_doctor', 'kiviCare_patient', 'kiviCare_receptionist'])) {
                $all_roles[] = [
                    'id' => $role_id,
                    'name' => $role_info['name']
                ];
            }
        }

        $data = [
            'data' => [
                'file_size_options' => $size_options,
                'file_type_options' => $type_options,
                'allowed_size' => esc_html__("Current file upload size supported is limited to ", "kivicare-clinic-management-system") . $max_file_size / (1024 * 1024) . esc_html__("MB", "kivicare-clinic-management-system")
            ],
            'all_roles' => $all_roles
        ];
        // Send the JSON response
        return $this->response($data);
    }

    public function savecustomField($module_type, $module_id, $fields, $status)
    {
        $temp = [
            'moduleType' => $module_type,
            'moduleId' => $module_id,
            'fields' => $fields,
            'status' => $status
        ];
        $temp['createdAt'] = current_time('Y-m-d H:i:s');
        KCCustomField::create($temp);
    }

    /**
     * Get arguments for the export endpoint
     *
     * @return array
     */
    private function getExportEndpointArgs()
    {
        return [
            'format' => [
                'description' => 'Export format (csv, xls, pdf)',
                'type' => 'string',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (!in_array($param, ['csv', 'xls', 'pdf'])) {
                        return new WP_Error('invalid_format', __('Format must be csv, xls, or pdf', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'searchTerm' => [
                'description' => 'Search term to filter results',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'module_type' => [
                'description' => 'Filter by module type',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'input_type' => [
                'description' => 'Filter by input type',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'description' => 'Filter by status (0: Inactive, 1: Active)',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && !in_array(intval($param), [0, 1])) {
                        return new WP_Error('invalid_status', __('Status must be 0 (inactive) or 1 (active)', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Export custom fields data
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function exportCustomFields(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Process request parameters
            $params = $request->get_params();
            // $searchTerm = isset($params['searchTerm']) ? sanitize_text_field($params['searchTerm']) : '';
            $module_type = isset($params['module_type']) ? sanitize_text_field($params['module_type']) : '';
            $input_type = isset($params['input_type']) ? sanitize_text_field($params['input_type']) : '';
            // $status = isset($params['status']) ? (int)$params['status'] : null;

            // Build the base query for custom fields - same logic as getCustomFields
            $query = KCCustomField::query();

            // Exclude 'appointment_module' if not Pro
            if (!isKiviCareProActive()) {
                $query = $query->where('module_type', '!=', 'appointment_module');
            }

            // Execute query
            $results = $query->orderBy('id', 'DESC')->get();

            if (empty($results)) {
                return $this->response(
                    ['customFields' => []],
                    __('No custom fields found', 'kivicare-clinic-management-system'),
                    true,
                    200
                );
            }

            // Process results for export
            $exportData = [];
            foreach ($results as $field) {
                // Parse fields JSON
                $fieldsData = is_string($field->fields) ? json_decode($field->fields, true) : $field->fields;

                // Extract field information from JSON
                $label = isset($fieldsData['name']) ? $fieldsData['name'] : '-';
                $type = isset($fieldsData['type']) ? $fieldsData['type'] : '-';
                $placeholder = isset($fieldsData['placeholder']) ? $fieldsData['placeholder'] : '-';
                $required = isset($fieldsData['isRequired']) ? ($fieldsData['isRequired'] ? 'Yes' : 'No') : 'No';

                // Handle options for select/radio/checkbox fields - extract from JSON properly
                $options = '';
                if (isset($fieldsData['options'])) {
                    if (is_array($fieldsData['options']) && !empty($fieldsData['options'])) {
                        $optionLabels = [];
                        foreach ($fieldsData['options'] as $option) {
                            if (is_array($option)) {
                                // Handle different option structures: {label: "text"}, {text: "text"}, {value: "text"}
                                if (isset($option['label'])) {
                                    $optionLabels[] = $option['label'];
                                } elseif (isset($option['text'])) {
                                    $optionLabels[] = $option['text'];
                                } elseif (isset($option['value'])) {
                                    $optionLabels[] = $option['value'];
                                } else {
                                    // If it's an array but doesn't have expected keys, convert to string
                                    $optionLabels[] = implode(':', $option);
                                }
                            } elseif (is_string($option)) {
                                $optionLabels[] = $option;
                            } else {
                                // Convert other types to string
                                $optionLabels[] = (string) $option;
                            }
                        }
                        $options = !empty($optionLabels) ? implode(', ', $optionLabels) : '-';
                    } elseif (is_string($fieldsData['options']) && !empty($fieldsData['options'])) {
                        // Handle case where options might be stored as a string
                        $options = $fieldsData['options'];
                    }
                }

                $exportData[] = [
                    'id' => $field->id,
                    'module' => $field->moduleType,
                    'label' => $label,
                    'placeholder' => $placeholder,
                    'options' => $options,
                    'input_type' => $type,
                    'required' => $required,
                    'status' => (string) $field->status
                ];
            }

            return $this->response(
                ['customFields' => $exportData],
                __('Custom fields data retrieved successfully', 'kivicare-clinic-management-system'),
                true,
                200
            );

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to export custom fields data', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }

    /**
     * Get custom fields by module type
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getCustomFieldsByModule(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $request_data = $request->get_params();

            if (!isset($request_data['module_type'])) {
                return $this->response(null, esc_html__('Module type is required', 'kivicare-clinic-management-system'), false);
            }

            $module_type = sanitize_text_field($request_data['module_type']);
            $module_id = isset($request_data['module_id']) ? (int) $request_data['module_id'] : null;

            $query = KCCustomField::query()
                ->where('module_type', '=', $module_type)
                ->where('status', '=', 1);

            // If module_id is provided, filter by it (for doctor-specific fields)
            if ($module_id) {
                $query = $query->where(function ($q) use ($module_id) {
                    $q->where('module_id', '=', $module_id)
                        ->orWhere('module_id', '=', 0);
                });
            } else {
                $query = $query->where('module_id', '=', 0);
            }

            $customFields = $query->get();

            $fieldsData = [];
            foreach ($customFields as $field) {
                $fields = is_string($field->fields) ? json_decode($field->fields, true) : $field->fields;

                // Rename multiselect to multi_select
                $type = $fields['type'] ?? '';
                if ($type === 'multiselect') {
                    $type = 'multi_select';
                }

                $options = $fields['options'] ?? [];
                if (is_array($options)) {
                    $options = $this->normalizeCustomFieldOptions($options);
                }

                $fieldsData[] = [
                    'id' => $field->id,
                    'name' => $fields['name'] ?? '',
                    'type' => $type,
                    'placeholder' => $fields['placeholder'] ?? '',
                    'isRequired' => $fields['isRequired'] ?? false,
                    'options' => $options,
                    'file_upload_type' => $fields['file_upload_type'] ?? [],
                    'status' => $fields['status'] ?? 1,
                ];
            }

            return $this->response($fieldsData, esc_html__('Custom fields retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve custom fields', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Handle file upload for custom fields
     */
    private function handleFileUpload($file_data)
    {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        $upload = wp_handle_upload($file_data, ['test_form' => false]);

        if (isset($upload['error'])) {
            return false;
        }

        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name(basename($upload['file'])),
            'post_content' => '',
            'post_status' => 'inherit'
        ];
        KCErrorLogger::instance()->error("Uploading file: " . $upload['file']);

        $attachment_id = wp_insert_attachment($attachment, $upload['file']);

        if (!is_wp_error($attachment_id)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
            wp_update_attachment_metadata($attachment_id, $attachment_data);
            return [
                'id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
                'name' => get_the_title($attachment_id)
            ];
        }

        return false;
    }

    /**
     * Save custom field data for a module instance
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function saveCustomFieldData(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $request_data = $request->get_json_params();
            if (!isset($request_data['module_type']) || !isset($request_data['module_id']) || !isset($request_data['fields_data'])) {
                return $this->response(null, esc_html__('Required parameters missing', 'kivicare-clinic-management-system'), false);
            }
            $module_type = sanitize_text_field($request_data['module_type']);
            $module_id = (int) $request_data['module_id'];
            $fields_data = $request_data['fields_data'];
            // Validate appointment exists and get doctor_id (for appointment_module)
            $doctor_id = null;
            if ($module_type === 'appointment_module') {
                $appointment = KCAppointment::find($module_id);

                if (!$appointment) {
                    return $this->response(null, esc_html__('Appointment not found', 'kivicare-clinic-management-system'), false);
                }
                $doctor_id = (int) $appointment->doctorId;
                $user_data = get_userdata($doctor_id);

                if (empty($user_data) || !in_array($this->kcbase->getDoctorRole(), (array) $user_data->roles)) {
                    return $this->response(
                        null,
                        sprintf(
                            /* translators: %d: user ID */
                            esc_html__('User with ID %d is not a doctor', 'kivicare-clinic-management-system'),
                            $doctor_id
                        ),
                        false
                    );
                }
            }
            // Fetch allowed custom fields using get() + loop (your preferred method)
            $fields = KCCustomField::query()
                ->where('module_type', '=', $module_type)
                ->where('status', '=', 1)
                ->where(function ($q) use ($module_id, $module_type, $doctor_id) {
                    if ($module_type === 'appointment_module' && !empty($doctor_id)) {
                        $q->where('module_id', '=', 0)
                            ->orWhere('module_id', '=', $doctor_id);
                    } else {
                        $q->where('module_id', '=', 0);
                    }
                })
                ->get();
            $allowed_field_ids = [];
            foreach ($fields as $field) {
                $allowed_field_ids[] = (int) $field->id;
            }
            if (empty($allowed_field_ids)) {
                return $this->response(null, esc_html__('No custom fields defined for this module', 'kivicare-clinic-management-system'), false);
            }
            $saved_data = [];
            $saved_count = 0;
            foreach ($fields_data as $field_id => $field_value) {
                $field_id = (int) $field_id;

                if (!in_array($field_id, $allowed_field_ids)) {
                    continue;
                }

                // Get field definition to check if it's a file upload field
                $field_definition = null;
                foreach ($fields as $field) {
                    if ($field->id == $field_id) {
                        $field_definition = is_string($field->fields) ? json_decode($field->fields, true) : $field->fields;
                        break;
                    }
                }

                // Handle file upload fields
                if ($field_definition && isset($field_definition['type']) && $field_definition['type'] === 'file_upload') {
                    // For file uploads, the value should already be the file object from frontend
                    if (is_array($field_value) && (isset($field_value['id']) || isset($field_value['url']))) {
                        // File already uploaded via frontend, just store the metadata
                        $field_value = json_encode($field_value);
                    } else {
                        // Skip if no valid file data
                        continue;
                    }
                }

                $existing = KCCustomFieldData::query()
                    ->where('module_type', '=', $module_type)
                    ->where('module_id', '=', $module_id)
                    ->where('field_id', '=', $field_id)
                    ->first();
                $data = [
                    'moduleType' => $module_type,
                    'moduleId' => $module_id,
                    'fieldId' => $field_id,
                    'fieldsData' => is_array($field_value) ? json_encode($field_value) : $field_value,
                ];
                if ($existing) {
                    $existing->fieldsData = $data['fieldsData'];
                    $existing->save();
                } else {
                    $data['createdAt'] = current_time('mysql');
                    KCCustomFieldData::create($data);
                }
                $saved_data[$field_id] = $field_value;
                $saved_count++;
            }
            return $saved_count > 0
                ? $this->response($saved_data, esc_html__('Custom field data saved successfully', 'kivicare-clinic-management-system'))
                : $this->response(null, esc_html__('No valid fields were saved', 'kivicare-clinic-management-system'), false);
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('saveCustomFieldData Error: ' . $e->getMessage());
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to save custom field data', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Get custom field data for a module instance
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getCustomFieldData(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $request_data = $request->get_params();

            if (!isset($request_data['module_type']) || !isset($request_data['module_id'])) {
                return $this->response(null, esc_html__('Module type and ID are required', 'kivicare-clinic-management-system'), false);
            }

            $module_type = sanitize_text_field($request_data['module_type']);
            $module_id = (int) $request_data['module_id'];

            $data = KCCustomFieldData::query()
                ->where('module_type', '=', $module_type)
                ->where('module_id', '=', $module_id)
                ->get();

            $formattedData = [];
            foreach ($data as $item) {
                // Try to decode as JSON first, if it fails, use the raw value
                $decodedData = json_decode($item->fieldsData, true);
                $formattedData[$item->fieldId] = $decodedData !== null ? $decodedData : $item->fieldsData;
            }

            return $this->response($formattedData, esc_html__('Custom field data retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve custom field data', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Normalize custom field options to ensure id, text, value, and label are consistently present.
     * 
     * @param array $options
     * @return array
     */
    private function normalizeCustomFieldOptions(array $options): array
    {
        return array_map(function ($option) {
            if (is_array($option)) {
                // Sync id and value
                if (isset($option['id']) && !isset($option['value'])) {
                    $option['value'] = $option['id'];
                } elseif (!isset($option['id']) && isset($option['value'])) {
                    $option['id'] = $option['value'];
                }

                // Sync text and label
                if (isset($option['text']) && !isset($option['label'])) {
                    $option['label'] = $option['text'];
                } elseif (!isset($option['text']) && isset($option['label'])) {
                    $option['text'] = $option['label'];
                }
            } elseif (is_string($option)) {
                // If it's a plain string, convert to standard object structure
                $option = [
                    'id' => $option,
                    'value' => $option,
                    'text' => $option,
                    'label' => $option
                ];
            }
            return $option;
        }, $options);
    }
}
