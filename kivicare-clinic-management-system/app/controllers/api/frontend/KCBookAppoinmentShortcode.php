<?php
namespace App\controllers\api\frontend;

use App\baseClasses\KCBaseController;
use App\models\KCClinic;
use App\models\KCPatientClinicMapping;
use App\models\KCDoctor;
use App\models\KCDoctorClinicMapping;
use App\models\KCService;
use App\models\KCServiceDoctorMapping;
use App\models\KCPatient;
use App\models\KCOption;
use App\models\KCUserMeta;
use KCProApp\models\KCPPatientReview;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class KCBookAppoinmentShortcode extends KCBaseController
{
    /**
     * @var string The base route for this controller
     */
    protected $route = 'frontend/kc-book-appointment';

    /**
     * Register the routes for this controller
     */
    public function registerRoutes()
    {
        // Note: get-clinics and get-doctors endpoints have been moved to static-data API
        // Using staticDataType: 'clinicsWithAllDetails' and 'doctorsWithAllDetails' for better consistency


        $this->registerRoute('/' . $this->route . '/get-appointment-confirmation', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getAppointmentConfirmation'],
            'permission_callback' => fn() => $this->kcbase->getLoginUserRole() === $this->kcbase->getPatientRole(),
            'args' => [
                'clinic_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                    'sanitize_callback' => 'absint',
                ],
                'doctor_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                    'sanitize_callback' => 'absint',
                ],
                'service_id' => [
                    'required' => true,
                    'validate_callback' => function ($param, $request, $key) {
                        if (!is_array($param)) {
                            return new WP_Error('rest_invalid_param', __('service_id must be an array of IDs.', 'kivicare-clinic-management-system'));
                        }

                        foreach ($param as $id) {
                            if (!is_numeric($id)) {
                                return new WP_Error('rest_invalid_param', __('All service_id values must be numeric.', 'kivicare-clinic-management-system'));
                            }
                        }

                        return true;
                    },
                    'sanitize_callback' => function ($param, $request, $key) {
                        // Convert all values to integers
                        return array_map('absint', (array) $param);
                    },
                ],
                'appointment_date' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_string($param) && strtotime($param) !== false;
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'appointment_time' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_string($param) && strtotime($param) !== false;
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ]
        ]);

        // Get all widget settings combined
        $this->registerRoute('/' . $this->route . '/get-widget-settings', [
            'methods' => 'GET',
            'callback' => [$this, 'getWidgetSettings'],
            'permission_callback' => '__return_true'
        ]);

        // Upload medical report endpoint
        $this->registerRoute('/' . $this->route . '/upload-medical-report', [
            'methods' => 'POST',
            'callback' => [$this, 'handleFileUpload'],
            'permission_callback' => function () {
                return wp_verify_nonce(
                    isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
                    'wp_rest'
                );
            },
        ]);
    }

    public function  getAppointmentConfirmation(WP_REST_Request $request){
        $patient_id = get_current_user_id();
        $clinic = KCClinic::getClinicDetailById($request->get_param('clinic_id'));
        $patient = KCPatient::table('p')
            ->select([
                "p.*",
                "fn.meta_value as first_name",
                "ln.meta_value as last_name",
                "bd.meta_value as basic_data"
            ])
            ->leftJoin(KCUserMeta::class, function($join) {
                $join->on('p.ID', '=', 'bd.user_id')
                        ->onRaw("bd.meta_key = 'basic_data'");
            }, null,null, 'bd')
            ->leftJoin(KCUserMeta::class, function($join) {
                $join->on('p.ID', '=', 'fn.user_id')
                    ->onRaw("fn.meta_key = 'first_name'");
            }, null, null, 'fn')
            ->leftJoin(KCUserMeta::class, function($join) {
                $join->on('p.ID', '=', 'ln.user_id')
                    ->onRaw("ln.meta_key = 'last_name'");
            }, null, null, 'ln')
            ->where('p.ID', '=', $patient_id)
            ->groupBy('p.ID')
            ->first();

        // Decode basic_data JSON
        $patientBasicData = [];
        if (!empty($patient->basic_data)) {
            $patientBasicData = json_decode($patient->basic_data, true);
        }

        // Build optimized query to get receptionist data
        $doctorData = KCDoctor::table('d')
            ->select([
                "d.*",
                "um_first.meta_value as first_name",
                "um_last.meta_value as last_name"
            ])
            ->leftJoin(KCUserMeta::class, function($join) {
                $join->on('d.ID', '=', 'um_first.user_id')
                    ->onRaw("um_first.meta_key = 'first_name'");
            }, null, null, 'um_first')
            ->leftJoin(KCUserMeta::class, function($join) {
                $join->on('d.ID', '=', 'um_last.user_id')
                    ->onRaw("um_last.meta_key = 'last_name'");
            }, null, null, 'um_last')
            ->where('d.ID', '=', $request->get_param('doctor_id'))
            ->first();

        // Return error if receptionist not found
        if (!$doctorData) {
            return $this->response(null, __('Doctor not found', 'kivicare-clinic-management-system'), false, 404);
        }

        // Validate doctor exists
        $doctor = KCDoctor::find($request->get_param('doctor_id'));
        if (!$doctor) {
            return $this->response(
                ['error' => 'Doctor not found'],
                __('Doctor not found', 'kivicare-clinic-management-system'),
                false,
                404
            );
        }

        // Validate clinic exists
        if (!$clinic) {
            return $this->response(
                ['error' => 'Clinic not found'],
                __('Clinic not found', 'kivicare-clinic-management-system'),
                false,
                404
            );
        }

        // Get services details
        $services = KCServiceDoctorMapping::table('dsm')
            ->leftJoin(KCService::class, 'dsm.service_id', '=', 'c.id', 'c')
            ->whereIn('dsm.id', $request->get_param('service_id'))->get();
        if ($services->isEmpty()) {
            return $this->response(
                ['error' => 'No valid services found'],
                __('No valid services found for the provided IDs', 'kivicare-clinic-management-system'),
                false,
                404
            );
        }

        // Calculate totals
        $subtotal = 0;
        $serviceDetails = [];

        $services->each(function ($service) use (&$subtotal, &$serviceDetails) {
            $serviceDetails[] = [
                'title' => $service->name,
                'price' => $service->charges
            ];
            $subtotal += $service->charges;
        });

        $summaryData = [
            'services'    => $serviceDetails,
            'subtotal'    => $subtotal,
            'grand_total' => $subtotal
        ];

        $params = $request->get_params();
        $mapped_params = array(
            'doctorId' => isset($params['doctor_id']) ? (int) $params['doctor_id'] : null,
            'clinicId' => isset($params['clinic_id']) ? (int) $params['clinic_id'] : null,
            'services' => isset($params['service_id']) ? (array) $params['service_id'] : array(),
        );

        do_action_ref_array('kc_appointment_summary_data', [&$summaryData, $mapped_params, $services]);
        // Get clinic currency settings
        $clinicCurrencySetting = KCClinic::getClinicCurrencyPrefixAndPostfix();
        $clinicPrefix = $clinicCurrencySetting['prefix'] ?? '';
        $clinicPostfix = $clinicCurrencySetting['postfix'] ?? '';

        // Format prices in summaryData
        foreach ($summaryData['services'] as &$service) {
            $service['price'] = $clinicPrefix . number_format($service['price'], 2) . $clinicPostfix;
        }
        $summaryData['subtotal'] = $clinicPrefix . number_format($summaryData['subtotal'], 2) . $clinicPostfix;
        $summaryData['grand_total'] = $clinicPrefix . number_format($summaryData['grand_total'], 2) . $clinicPostfix;
        if (isset($summaryData['tax'])) {
            $summaryData['tax'] = $clinicPrefix . number_format($summaryData['tax'], 2) . $clinicPostfix;
        }
        if (isset($summaryData['applied_taxes'])) {
            foreach ($summaryData['applied_taxes'] as &$tax) {
            $tax['tax_amount'] = $clinicPrefix . number_format($tax['tax_amount'], 2) . $clinicPostfix;
            }
        }

        return $this->response([
            'clinic'  => [
                'name'    => $clinic->name,
                'address' => implode(
                    ', ',
                    [$clinic->address, $clinic->city, $clinic->state, $clinic->country, $clinic->postalCode]
                ),
            ],
            'patient' => [
                'id'             => $patient->id,
                'full_name'      => trim(($patient->first_name ?? '') . ' ' . ($patient->last_name ?? '')),
                'email'          => $patient->email,
                'contact_number' => $patientBasicData['mobile_number'] ?? null,
            ],
            'doctor'  => [
                'id'        => $doctorData->id,
                'full_name' => ($doctorData->first_name ?? '') . ' ' . ($doctorData->last_name ?? ''),
            ],
            ...$summaryData,
            'appointment_date' => $request->get_param('appointment_date'),
            'appointment_time' => $request->get_param('appointment_time'),
        ]);

    }

    /**
     * Get all widget settings combined for clinics, doctors, and services
     */
    public function getWidgetSettings()
    {
        try {
            // Get widget settings from database
            $widget_setting_options = KCOption::get('widgetSetting');
            $widget_setting = [];
            $is_uploadfile_appointment = KCOption::get('multifile_appointment','off');
            $is_appointment_description_config_data = KCOption::get('appointment_description_config_data','');
          
            if (!empty($widget_setting_options)) {
                $widget_setting = is_string($widget_setting_options) ? json_decode($widget_setting_options, true) : $widget_setting_options;
            }

            // Clinic settings
            $clinic_settings = [
                'showClinicImage'      => isset($widget_setting['showClinicImage']) ? filter_var($widget_setting['showClinicImage'], FILTER_VALIDATE_BOOLEAN) : false,
                'showClinicAddress'    => isset($widget_setting['showClinicAddress']) ? filter_var($widget_setting['showClinicAddress'], FILTER_VALIDATE_BOOLEAN) : false,
                'clinicContactDetails' => $widget_setting['clinicContactDetails'] ?? ['id' => 1],
            ];

            // Doctor settings
            $doctor_settings = [
                'showDoctorImage'       => isset($widget_setting['showDoctorImage']) ? filter_var($widget_setting['showDoctorImage'], FILTER_VALIDATE_BOOLEAN) : false,
                'showDoctorExperience'  => isset($widget_setting['showDoctorExperience']) ? filter_var($widget_setting['showDoctorExperience'], FILTER_VALIDATE_BOOLEAN) : false,
                'showDoctorSpeciality'  => isset($widget_setting['showDoctorSpeciality']) ? filter_var($widget_setting['showDoctorSpeciality'], FILTER_VALIDATE_BOOLEAN) : false,
                'showDoctorDegree'      => isset($widget_setting['showDoctorDegree']) ? filter_var($widget_setting['showDoctorDegree'], FILTER_VALIDATE_BOOLEAN) : false,
                'doctorContactDetails'  => $widget_setting['doctorContactDetails'] ?? ['id' => 1],
                'showDoctorRating'      => isset($widget_setting['showDoctorRating']) ? filter_var($widget_setting['showDoctorRating'], FILTER_VALIDATE_BOOLEAN) : false,
            ];

            // Service settings
            $service_settings = [
                'showServiceImage'          => isset($widget_setting['showServiceImage']) ? filter_var($widget_setting['showServiceImage'], FILTER_VALIDATE_BOOLEAN) : false,
                'showServicetype'           => isset($widget_setting['showServicetype']) ? filter_var($widget_setting['showServicetype'], FILTER_VALIDATE_BOOLEAN) : false,
                'showServicePrice'          => isset($widget_setting['showServicePrice']) ? filter_var($widget_setting['showServicePrice'], FILTER_VALIDATE_BOOLEAN) : false,
                'showServiceDuration'       => isset($widget_setting['showServiceDuration']) ? filter_var($widget_setting['showServiceDuration'], FILTER_VALIDATE_BOOLEAN) : false,
                'skip_service_when_single'  => isset($widget_setting['skip_service_when_single']) ? filter_var($widget_setting['skip_service_when_single'], FILTER_VALIDATE_BOOLEAN) : false,
            ];

            $appointment_settings = [
                'is_uploadfile_appointment' => $is_uploadfile_appointment ? filter_var($is_uploadfile_appointment, FILTER_VALIDATE_BOOLEAN) : false,
                'is_appointment_description_config_data' => $is_appointment_description_config_data ? filter_var($is_appointment_description_config_data, FILTER_VALIDATE_BOOLEAN) : false,
            ];

            return $this->response([
                'clinic'  => $clinic_settings,
                'doctor'  => $doctor_settings,
                'service' => $service_settings,
                'appointment' => $appointment_settings,
            ]);

        } catch (\Exception $e) {
            return new WP_Error(
                'kc_get_widget_settings_error',
                __('Failed to retrieve widget settings', 'kivicare-clinic-management-system'),
                ['status' => 500]
            );
        }
    }

    /**
     * Handle file upload for medical reports
     */
    public function handleFileUpload(WP_REST_Request $request)
    {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Step 1: Validate and retrieve file content
        $validation_result = $this->validateFileContent();
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        $file_content = $validation_result;

        // Step 2: Extract and sanitize filename
        $filename = $this->extractFilename($request);

        // Step 3: Validate file type
        $type_validation = $this->validateFileType($filename);
        if (is_wp_error($type_validation)) {
            return $type_validation;
        }

        // Step 4: Create and validate temporary file
        $tmp_file_result = $this->createTempFile($filename, $file_content);
        if (is_wp_error($tmp_file_result)) {
            return $tmp_file_result;
        }
        list($tmp_file, $allowed_types) = $tmp_file_result;

        // Step 5: Verify file content integrity
        $content_validation = $this->verifyFileContent($tmp_file, $allowed_types);
        if (is_wp_error($content_validation)) {
            wp_delete_file($tmp_file);
            return $content_validation;
        }

        // Step 6: Upload file to WordPress
        $upload_result = $this->uploadFile($filename, $tmp_file, $file_content);
        if (is_wp_error($upload_result)) {
            return $upload_result;
        }

        // Step 7: Create attachment and return response
        return $this->createAttachment($upload_result);
    }

    /**
     * Validate file content from request body
     * 
     * @return string|WP_Error File content or error
     */
    private function validateFileContent()
    {
        $file_content = file_get_contents('php://input');
        
        if (empty($file_content)) {
            return new WP_Error('no_file', __('No file provided', 'kivicare-clinic-management-system'), ['status' => 400]);
        }

        $allowed_file_size = wp_max_upload_size();
        if (strlen($file_content) > $allowed_file_size) {
            return new WP_Error('file_too_large', __('File size exceeds maximum allowed size', 'kivicare-clinic-management-system'), ['status' => 400]);
        }

        return $file_content;
    }

    /**
     * Extract and sanitize filename from request header
     * 
     * @param WP_REST_Request $request
     * @return string Sanitized filename
     */
    private function extractFilename(WP_REST_Request $request)
    {
        $content_disposition = $request->get_header('content_disposition');
        $filename = 'uploaded_file';
        
        if ($content_disposition && preg_match('/filename="([^"]+)"/', $content_disposition, $matches)) {
            $filename = sanitize_file_name($matches[1]);
            $filename = basename($filename); // Prevent path traversal
            $filename = $filename ?: 'uploaded_file'; // Fallback if empty after sanitization
        }

        return $filename;
    }

    /**
     * Validate file type against WordPress allowed MIME types
     * 
     * @param string $filename
     * @return true|WP_Error True if valid, WP_Error otherwise
     */
    private function validateFileType($filename)
    {
        $file_type_info = wp_check_filetype($filename);
        $allowed_types = get_allowed_mime_types();
        
        if (empty($file_type_info['type']) || !in_array($file_type_info['type'], $allowed_types, true)) {
            return new WP_Error('invalid_file_type', __('File type not supported', 'kivicare-clinic-management-system'), ['status' => 400]);
        }

        return true;
    }

    /**
     * Create temporary file and write content
     * 
     * @param string $filename
     * @param string $file_content
     * @return array|WP_Error Array with [tmp_file, allowed_types] or WP_Error
     */
    private function createTempFile($filename, $file_content)
    {
        $tmp_file = wp_tempnam($filename);
        if (!$tmp_file) {
            return new WP_Error('temp_file_error', __('Failed to create temporary file', 'kivicare-clinic-management-system'), ['status' => 500]);
        }
        
        if (file_put_contents($tmp_file, $file_content) === false) {
            wp_delete_file($tmp_file);
            return new WP_Error('temp_file_write_error', __('Failed to write temporary file', 'kivicare-clinic-management-system'), ['status' => 500]);
        }

        return [$tmp_file, get_allowed_mime_types()];
    }

    /**
     * Verify file content matches declared MIME type
     * 
     * @param string $tmp_file Temporary file path
     * @param array $allowed_types Allowed MIME types
     * @return true|WP_Error True if valid, WP_Error otherwise
     */
    private function verifyFileContent($tmp_file, $allowed_types)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return new WP_Error('file_validation_error', __('Unable to validate file', 'kivicare-clinic-management-system'), ['status' => 500]);
        }
        
        $actual_mime = finfo_file($finfo, $tmp_file);
        finfo_close($finfo);
        
        if ($actual_mime === false) {
            return new WP_Error('invalid_file_content', __('File content does not match declared type', 'kivicare-clinic-management-system'), ['status' => 400]);
        }

        // Office Open XML formats (DOCX, XLSX, PPTX) are ZIP archives internally
        // finfo detects them as application/zip, so we need to allow both
        $office_xml_formats = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // DOCX
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',       // XLSX
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' // PPTX
        ];

        // If detected as ZIP and any Office format is allowed, accept it
        if ($actual_mime === 'application/zip') {
            foreach ($office_xml_formats as $office_format) {
                if (in_array($office_format, $allowed_types, true)) {
                    return true; // ZIP is valid for Office formats
                }
            }
        }

        // Standard validation: check if detected MIME type is in allowed list
        if (!in_array($actual_mime, $allowed_types, true)) {
            return new WP_Error('invalid_file_content', __('File content does not match declared type', 'kivicare-clinic-management-system'), ['status' => 400]);
        }

        return true;
    }

    /**
     * Upload file using WordPress sideload
     * 
     * @param string $filename
     * @param string $tmp_file
     * @param string $file_content
     * @return array|WP_Error Upload result array or WP_Error
     */
    private function uploadFile($filename, $tmp_file, $file_content)
    {
        $file_array = [
            'name' => $filename,
            'tmp_name' => $tmp_file,
            'size' => strlen($file_content),
            'error' => 0
        ];

        $uploaded = wp_handle_sideload($file_array, ['test_form' => false]);

        // Clean up temp file
        if (file_exists($tmp_file)) {
            wp_delete_file($tmp_file);
        }

        if (isset($uploaded['error'])) {
            return new WP_Error('upload_error', $uploaded['error'], ['status' => 500]);
        }

        if (empty($uploaded['file'])) {
            return new WP_Error('upload_error', __('Upload failed - no file path returned', 'kivicare-clinic-management-system'), ['status' => 500]);
        }

        // Validate uploaded file path
        $uploaded_file_real = realpath($uploaded['file']);
        $upload_dir_real = realpath(wp_upload_dir()['basedir']);
        
        if (!$uploaded_file_real || !$upload_dir_real || 
            strpos($uploaded_file_real, $upload_dir_real) !== 0 || 
            !is_file($uploaded_file_real)) {
            
            if ($uploaded_file_real && file_exists($uploaded_file_real)) {
                wp_delete_file($uploaded_file_real);
            }
            return new WP_Error('invalid_upload_path', __('Invalid file path detected', 'kivicare-clinic-management-system'), ['status' => 500]);
        }

        $uploaded['file_real'] = $uploaded_file_real;
        return $uploaded;
    }

    /**
     * Create WordPress attachment and return response
     * 
     * @param array $uploaded Upload result with file path and type
     * @return array|WP_Error Response data or WP_Error
     */
    private function createAttachment($uploaded)
    {
        $uploaded_file_real = $uploaded['file_real'];
        
        $attachment = [
            'post_mime_type' => $uploaded['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($uploaded_file_real)),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attachment_id = wp_insert_attachment($attachment, $uploaded_file_real);

        if (is_wp_error($attachment_id)) {
            wp_delete_file($uploaded_file_real);
            return $attachment_id;
        }

        // Generate and update attachment metadata
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file_real);
        if ($attachment_data) {
            wp_update_attachment_metadata($attachment_id, $attachment_data);
        }

        return $this->response([
            'id' => $attachment_id,
            'source_url' => wp_get_attachment_url($attachment_id),
            'title' => ['raw' => get_the_title($attachment_id)],
            'media_type' => $uploaded['type'],
            'media_details' => [
                'filesize' => filesize($uploaded_file_real) ?: 0
            ]
        ]);
    }
}
