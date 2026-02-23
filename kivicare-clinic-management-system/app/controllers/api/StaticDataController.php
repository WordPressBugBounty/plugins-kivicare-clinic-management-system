<?php

namespace App\controllers\api;

use App\baseClasses\KCBase;
use App\baseClasses\KCBaseController;
use App\baseClasses\KCErrorLogger;
use App\baseClasses\KCTelemedFactory;
use App\models\KCDoctorClinicMapping;
use App\models\KCOption;
use App\models\KCServiceDoctorMapping;
use App\models\KCStaticData;
use App\models\KCClinic;
use App\models\KCClinicAdmin;
use App\models\KCDoctor;
use App\models\KCPatient;
use App\models\KCPatientClinicMapping;
use App\models\KCReceptionist;
use App\models\KCReceptionistClinicMapping;
use App\models\KCService;
use App\models\KCUser;
use App\models\KCAppointment;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use App\models\KCUserMeta;

defined('ABSPATH') or die('Something went wrong');

class StaticDataController extends KCBaseController
{
    protected $route = 'static-data';

    public function registerRoutes()
    {
        // Add specialty endpoint
        $this->registerRoute('/' . $this->route . '/add-specialty', [
            'methods' => 'POST',
            'callback' => [$this, 'addSpecialty'],
            'args' => [
                'label' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => __('Specialty label', 'kivicare-clinic-management-system'),
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'type' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => __('Static data type (e.g. specialization)', 'kivicare-clinic-management-system'),
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => 'specialization',
                ],
            ],
            'permission_callback' => function (WP_REST_Request $request) {
                return true; // Allow all users to add specialties
            },
        ]);

        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getStaticData'],
            'args' => [
                'dataType' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => __('Type of static data to retrieve', 'kivicare-clinic-management-system'),
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($param, $request, $key) {
                        return in_array($param, ['clinicList', 'doctorList', 'patientList', 'staticData']);
                    },
                ],
                'staticDataType' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => __('Type of static data to retrieve', 'kivicare-clinic-management-system'),
                    'validate_callback' => function ($param, $request, $key) {
                        $allowed_types = [
                            "staticData",
                            "staticDataWithLabel",
                            "staticDataTypes",
                            "clinics",
                            "clinicsWithAllDetails",
                            "patientClinic",
                            "doctors",
                            "doctorsWithAllDetails",
                            "patients",
                            "defaultClinic",
                            "servicesWithPrice",
                            "servicesWithAllDetails",
                            "prescriptions",
                            "emailTemplateType",
                            "emailTemplateKey",
                            "getUsersByClinic",
                            "clinicDoctors",
                            "users"
                        ];
                        $allowed_types = apply_filters('kc_static_data_allowed_types', $allowed_types);
                        return in_array($param, $allowed_types);
                    },
                ],
                'type' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => __('Static data type filter', 'kivicare-clinic-management-system'),
                ],
                'clinic_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => !isKiviCareProActive() ? KCClinic::kcGetDefaultClinicId() : 0,
                    'sanitize_callback' => function ($val) {
                        return absint(!isKiviCareProActive() ? KCClinic::kcGetDefaultClinicId() : $val);
                    },
                    'description' => __('Clinic ID filter', 'kivicare-clinic-management-system'),
                ],
                'clinic_ids' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => __('Clinic IDs filter', 'kivicare-clinic-management-system'),
                ],
                'patient_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'description' => __('Patient ID filter', 'kivicare-clinic-management-system'),
                ],
                'role' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => __('User role filter', 'kivicare-clinic-management-system'),
                ],
                'doctor_id' => [
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint',
                ],
                'doctor_ids' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => __('Doctor IDs filter', 'kivicare-clinic-management-system'),
                ],
                'search' => [
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return is_string($param);
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'preselected_service' => [
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return is_string($param);
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'service_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'description' => __('Service ID filter (optional)', 'kivicare-clinic-management-system'),
                ],
                'exclude_with_sessions' => [
                    'required' => false,
                    'type' => 'boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'description' => __('Exclude clinics where doctor already has sessions', 'kivicare-clinic-management-system'),
                ],
                'page' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                    'sanitize_callback' => function ($val) {
                        // Allow -1 as a special flag to fetch all data
                        $page = intval($val);
                        return $page === -1 ? -1 : max(1, absint($page));
                    },
                    'description' => __('Page number for pagination (-1 to fetch all records)', 'kivicare-clinic-management-system'),
                ],
                'per_page' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 10,
                    'sanitize_callback' => function ($val) {
                        return min(100, max(1, absint($val)));
                    },
                    'description' => __('Number of items per page (max 100)', 'kivicare-clinic-management-system'),
                ],
                'is_app' => [
                    'required' => false,
                    'type' => 'boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'description' => __('Whether the request is from the mobile app (allows inactive clinics in some cases)', 'kivicare-clinic-management-system'),
                ],
            ],
            'permission_callback' => function (WP_REST_Request $request) {
                // For now, allowing all requests - can be customized later
                return true;
            },
        ]);
    }

    public function getStaticData(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $dataType = $request->get_param('dataType');
            $staticDataType = $request->get_param('staticDataType');

            $response = [];

            switch ($dataType) {
                case 'clinicList':
                    $response = $this->getClinicList($request);
                    break;

                case 'doctorList':
                    $response = $this->getDoctorsList($request->get_param('clinic_id') ?? $request->get_param('clinic_ids') ?? 0, $request);
                    break;

                case 'patientList':
                    $response = $this->getPatientsList($request->get_param('clinic_id') ?? $request->get_param('clinic_ids') ?? 0, $request);
                    break;

                case 'staticData':
                    if (empty($staticDataType)) {
                        return rest_ensure_response([
                            'status' => false,
                            'message' => __('staticDataType parameter is required for staticData requests', 'kivicare-clinic-management-system'),
                            'data' => []
                        ]);
                    }

                    $response = $this->handleStaticDataType($staticDataType, $request);
                    break;

                default:
                    return rest_ensure_response([
                        'status' => false,
                        'message' => __('Invalid dataType parameter', 'kivicare-clinic-management-system'),
                        'data' => []
                    ]);
            }

            // Handle pagination response format
            $finalResponse = [
                'status' => true,
                'message' => __('Data retrieved successfully', 'kivicare-clinic-management-system'),
            ];

            if (is_array($response) && isset($response['data']) && isset($response['pagination'])) {
                // Response includes pagination metadata
                $finalResponse['data'] = $response['data'];
                $finalResponse['pagination'] = $response['pagination'];
            } else {
                // Regular response without pagination
                $finalResponse['data'] = $response;
            }

            return rest_ensure_response($finalResponse);
        } catch (\Exception $e) {
            return rest_ensure_response([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => []
            ]);
        }
    }

    /**
     * Handle different types of static data requests
     */
    private function handleStaticDataType(string $staticDataType, WP_REST_Request $request): array
    {
        switch ($staticDataType) {
            case 'staticData':
                return $this->getStaticDataByType($request->get_param('type') ?? '', $request);

            case 'staticDataWithLabel':
                return $this->getStaticDataWithLabel($request->get_param('type') ?? '', $request);

            case 'staticDataTypes':
                return $this->getStaticDataTypes();

            case 'clinics':
                return $this->getClinicList($request);

            case 'clinicsWithAllDetails':
                return $this->getClinicsWithAllDetails($request);

            case 'patientClinic':
                return $this->getPatientClinics($request->get_param('patient_id') ?? 0);

            case 'doctors':
                return $this->getDoctorsList($request->get_param('clinic_ids') ?? 0, $request);

            case 'doctorsWithAllDetails':
                return $this->getDoctorsWithAllDetails($request);

            case 'patients':
                return $this->getPatientsList($request->get_param('clinic_ids') ?? 0, $request);

            case 'defaultClinic':
                return $this->getDefaultClinic();

            case 'servicesWithPrice':
                $currentUserRole = $this->kcbase->getLoginUserRole();
                $currentUserId = get_current_user_id();

                // Role-based permission filtering
                switch ($currentUserRole) {
                    case $this->kcbase->getClinicAdminRole():
                    case $this->kcbase->getReceptionistRole():
                        $request->set_param('clinic_id', KCClinic::getClinicIdForCurrentUser());
                        break;
                    case $this->kcbase->getDoctorRole():
                        $request->set_param('doctor_id', $currentUserId);
                        break;
                }

                return $this->getServicesWithPrice($request->get_param('clinic_id') ?? -1, $request);
            case 'servicesWithAllDetails':
                return $this->getServicesWithAllDetails($request);
            case 'prescriptions':
                return $this->getPrescriptions();

            case 'emailTemplateType':
                return $this->getEmailTemplateTypes();

            case 'emailTemplateKey':
                return $this->getEmailTemplateKeys();

            case 'getUsersByClinic':
                return $this->getUsersByClinic($request->get_param('clinic_id') ?? 0, $request);

            case 'clinicDoctors':
                $clinicId = $request->get_param('clinic_id');
                if (!$clinicId) {
                    $clinicId = $request->get_param('clinic_ids') ?? 0;
                }

                return $this->getClinicDoctors($clinicId, $request);

            case 'users':
                return $this->getUsers($request->get_param('role') ?? '', $request);

            default:
                // Allow custom types through filter
                $custom_data = apply_filters('kc_static_data_custom_type', [], $staticDataType, $request);
                if (!empty($custom_data)) {
                    return $custom_data;
                }

                throw new \Exception(esc_html__('Invalid staticDataType parameter', 'kivicare-clinic-management-system'));
        }
    }

    private function getServicesWithAllDetails($request): array
    {
        try {
            $doctorId = $request->get_param('doctor_id');
            $doctorIds = $request->get_param('doctor_ids');

            $clinicId = $request->get_param('clinic_id');
            $search = $request->get_param('search');
            $preselectedService = $request->get_param('preselected_service');
            $serviceId = $request->get_param('service_id');
            $category = $request->get_param('category_id');

            // Use the KCServiceDoctorMapping model's query builder with proper joins
            $query = KCServiceDoctorMapping::table('sdm')
                ->select([
                    'sdm.*',
                    'sdm.doctor_id as doctorId',
                    'sdm.charges as service_base_price',
                    's.name as name',
                    'sd.label as service_type',
                    'sd.id as category_id',
                    's.created_at as created_at',
                    'u.display_name as doctor_name',
                    'c.name as clinic_name',
                    'c.profile_image as profile_image',
                    'c.telephone_no as clinic_telephone_no',
                    'c.country_calling_code as clinic_country_calling_code',
                    'c.address as clinic_address',
                    'c.city as clinic_city',
                    'c.state as clinic_state',
                    'c.country as clinic_country',
                    'c.postal_code as clinic_postal_code',
                ])
                ->leftJoin(KCService::class, 'sdm.service_id', '=', 's.id', 's')
                ->leftJoin(KCClinic::class, 'sdm.clinic_id', '=', 'c.id', 'c')
                ->leftJoin('users', 'u.ID', '=', 'sdm.doctor_id', 'u')
                ->leftJoin('kc_doctor_clinic_mappings', 'dcm.doctor_id', '=', 'sdm.doctor_id', 'dcm')
                ->leftJoin(KCStaticData::class, function ($join) {
                    $join->on('s.type', '=', 'sd.value')
                        ->onRaw("sd.type = 'service_type'");
                }, null, null, 'sd')
                ->whereRaw('dcm.clinic_id = sdm.clinic_id')
                ->where('s.status', 1) // Only active services
                ->where('sdm.status', 1); // Only active service mappings

            if (!$request->has_param('is_app') || ($request->get_param('is_app') !== 'true' && $request->get_param('is_app') !== true)) {
                $query->where('c.status', 1); // Only active clinics
            }

            $query->orderBy('sdm.id', 'DESC');

            // Apply filters
            if (!empty($doctorId)) {
                //single doctor
                $query->where('sdm.doctor_id', (int) $doctorId);
            } elseif (!empty($doctorIds)) {
                // Multiple doctors
                $doctorIdsArray = array_filter(
                    array_map('absint', explode(',', $doctorIds))
                );
                if (!empty($doctorIdsArray)) {
                    $query->whereIn('sdm.doctor_id', $doctorIdsArray);
                } else {
                    return [];
                }
            }

            if (!empty($clinicId)) {
                $query->where('sdm.clinic_id', (int) $clinicId);
            }

            if (!empty($search)) {
                $query->whereRaw("(s.name LIKE %s OR s.type LIKE %s OR sdm.charges LIKE %s)", [
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%'
                ]);
            }

            if (!empty($preselectedService)) {
                $preselectedServiceIds = array_filter(array_map('absint', explode(',', $preselectedService)));
                if (!empty($preselectedServiceIds)) {
                    $query->whereIn('sdm.service_id', $preselectedServiceIds);
                }
            }

            if (!empty($serviceId)) {
                $query->where('sdm.service_id', (int) $serviceId);
            }

            if (!empty($category)) {
                $query->where('sd.id', '=', (int) $category);
            }

            // Execute query
            $results = $query->get();

            if ($results->isEmpty()) {
                return [];
            }

            // Telemed Filter
            $zoomPrefix = defined('KIVICARE_TELEMED_PREFIX') ? KIVICARE_TELEMED_PREFIX : 'kiviCare_';
            $zoomGlobal = maybe_unserialize(\App\models\KCOption::get('zoom_telemed_setting'));
            $isZoomOn = isKiviCareTelemedActive() &&
                (get_option($zoomPrefix . 'zoom_telemed_server_to_server_oauth_status') === 'Yes' || (!empty($zoomGlobal['enableCal']) && $zoomGlobal['enableCal'] === 'Yes'));

            $gmeetGlobal = \App\models\KCOption::get('google_meet_setting');
            $isGMeetOn = isKiviCareGoogleMeetActive() && is_array($gmeetGlobal) &&
                (filter_var($gmeetGlobal['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN) || ($gmeetGlobal['auth_type'] ?? '') === 'oauth' || in_array($gmeetGlobal['enableCal'] ?? '', ['Yes', 'on']));

            $docCache = [];
            $results = $results->filter(function ($s) use ($isZoomOn, $isGMeetOn, &$docCache) {
                // Check both property names to be safe
                $isTelemed = ($s->telemed_service ?? 'no') === 'yes' || ($s->telemedService ?? 'no') === 'yes';

                if (!$isTelemed)
                    return true;
                if (empty($s->doctorId))
                    return false;

                if (!isset($docCache[$s->doctorId])) {
                    $zConn = $isZoomOn && (get_user_meta($s->doctorId, 'kiviCare_zoom_telemed_connect', true) === 'on' ||
                        ((json_decode(get_user_meta($s->doctorId, 'zoom_server_to_server_oauth_config_data', true), true)['enableServerToServerOauthconfig'] ?? '') == 'true'));
                    $gConn = $isGMeetOn && (get_user_meta($s->doctorId, 'kiviCare_google_meet_connect', true) === 'on');
                    $docCache[$s->doctorId] = $zConn || $gConn;
                }
                return $docCache[$s->doctorId];
            });

            if ($results->isEmpty())
                return [];

            // Get clinic currency settings
            $clinicCurrencySetting = KCClinic::getClinicCurrencyPrefixAndPostfix();
            $clinicPrefix = $clinicCurrencySetting['prefix'] ?? '';
            $clinicPostfix = $clinicCurrencySetting['postfix'] ?? '';


            $services = $results->map(function ($service) use ($clinicPrefix, $clinicPostfix) {

                // Round charges
                $charges = round((float) $service->charges, 2);
                $serviceBasePrice = round((float) $service->service_base_price, 2);

                // Decode clinic name
                $clinicName = KCdecodeHtmlEntities($service->clinic_name);

                // Service image
                $serviceImage = !empty($service->image) ? wp_get_attachment_url($service->image) : '';

                // Clinic image
                $clinicImage = !empty($service->profile_image) ? wp_get_attachment_url($service->profile_image) : '';

                // Clinic contact number (formatted with country calling code if available)
                $clinicContactNumber = '';
                if (!empty($service->clinic_telephone_no)) {
                    $rawNumber = trim($service->clinic_telephone_no);
                    if (str_starts_with(ltrim($rawNumber), '+')) {
                        $clinicContactNumber = $rawNumber;
                    } else {
                        $callingCode = $service->clinic_country_calling_code ?? '';
                        if (!empty($callingCode)) {
                            $clinicContactNumber = '+' . $callingCode . $rawNumber;
                        } else {
                            $clinicContactNumber = $rawNumber;
                        }
                    }
                }

                // Clinic address (combined)
                $addressParts = array_filter([
                    $service->clinic_address ?? '',
                    $service->clinic_city ?? '',
                    $service->clinic_state ?? '',
                    $service->clinic_country ?? '',
                    $service->clinic_postal_code ?? '',
                ]);
                $clinicFullAddress = implode(', ', $addressParts);

                // Doctor profile image
                $doctorImageUrl = '';
                if (!empty($service->doctorId)) {
                    $profileImageId = get_user_meta($service->doctorId, 'doctor_profile_image', true);
                    if ($profileImageId) {
                        $doctorImageUrl = wp_get_attachment_url($profileImageId);
                    }
                }

                // Format service type
                $serviceType = !empty($service->service_type) ? kcGetStaticDataTypeLabel($service->service_type) : "";

                // Handle telemed services
                if (($service->telemed_service ?? 'no') === 'yes') {
                    $serviceType = !empty($service->service_name_alias) ?
                        str_replace("_", " ", $service->service_name_alias) : $serviceType;
                }

                // Format charges with currency
                $formattedCharges = $clinicPrefix . $charges . $clinicPostfix;

                // Add formatted service data
                return [
                    'id' => $service->id,
                    'service_id' => $service->serviceId,
                    'name' => $service->name,
                    'service_type' => $serviceType,
                    'category_id' => $service->category_id,
                    'charges' => $formattedCharges,
                    'service_base_price' => $serviceBasePrice,
                    'doctor_id' => $service->doctorId,
                    'doctor_name' => $service->doctor_name,
                    'doctor_image_url' => $doctorImageUrl,
                    'clinic_id' => $service->clinicId,
                    'clinic_name' => $clinicName,
                    'clinic_image' => $clinicImage,
                    'clinic_contact_number' => $clinicContactNumber,
                    'clinic_address' => $clinicFullAddress,
                    'duration' => $service->duration ?? 0,
                    'status' => $service->status,
                    'telemed_service' => $service->telemedService ?? 'no',
                    'multiple' => $service->multiple ?? 'yes',
                    'service_image_url' => $serviceImage,
                ];
            })->filter(function ($service) {
                return $service !== null; // Remove any null values
            })->values()->toArray();


            return $services;

        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'status' => 500];
        }
    }

    /**
     * Get clinic list
     */
    private function getClinicList(?WP_REST_Request $request = null): array
    {
        try {
            $currentUserRole = $this->kcbase->getLoginUserRole();
            $currentUserId = get_current_user_id();
            $clinicsQuery = KCClinic::query();
            if (!is_user_logged_in()) {
                $currentUserRole = 'logged_out_user';
            }

            // Role-based permission filtering
            switch ($currentUserRole) {
                case $this->kcbase->getClinicAdminRole():
                    $clinicsQuery->where('clinic_admin_id', $currentUserId);
                    break;
                case $this->kcbase->getDoctorRole():
                    $clinicIds = KCDoctorClinicMapping::query()
                        ->where('doctor_id', $currentUserId)
                        ->select(['clinic_id'])
                        ->get()
                        ->map(fn($row) => $row->clinicId)
                        ->toArray();

                    $clinicIds = array_filter($clinicIds);

                    if (!empty($clinicIds)) {
                        $clinicsQuery->whereIn('id', $clinicIds);
                    } else {
                        $clinicsQuery->whereRaw('0=1');
                    }
                    break;
                case $this->kcbase->getReceptionistRole():
                    $clinicIds = KCReceptionistClinicMapping::query()
                        ->where('receptionist_id', $currentUserId)
                        ->select(['clinic_id'])
                        ->get()
                        ->map(fn($row) => $row->clinicId)
                        ->toArray();

                    $clinicIds = array_filter($clinicIds);

                    if (!empty($clinicIds)) {
                        $clinicsQuery->whereIn('id', $clinicIds);
                    } else {
                        $clinicsQuery->whereRaw('0=1');
                    }
                    break;
                case $this->kcbase->getPatientRole():
                    // No restriction for patients
                    break;
                case 'administrator':
                    // No restriction for admin
                    break;
                case 'logged_out_user':
                    $clinicsQuery = KCClinic::query();
                    break;
                default:
                    $clinicsQuery->whereRaw('0=1');
            }

            // Only show inactive clinics if is_app is set to true
            if (!$request->has_param('is_app') || ($request->get_param('is_app') !== 'true' && $request->get_param('is_app') !== true)) {
                 $clinicsQuery->where('status', 1);
            }

            // Handle search
            if ($request instanceof WP_REST_Request && !empty($request->get_param('search'))) {
                $search = $request->get_param('search');
                if ($search !== 'undefined' && $search !== 'null') {
                    $clinicsQuery->where('name', 'LIKE', '%' . $search . '%');
                }
            }

            // Filter by service_id
            if ($request instanceof WP_REST_Request) {
                $serviceId = $request->get_param('service_id');
                if (!empty($serviceId)) {
                    $serviceClinicIds = KCServiceDoctorMapping::query()
                        ->where('service_id', absint($serviceId))
                        ->where('status', 1)
                        ->select(['clinic_id'])
                        ->groupBy('clinic_id')
                        ->get()
                        ->map(fn($row) => $row->clinicId)
                        ->toArray();

                    $serviceClinicIds = array_filter($serviceClinicIds);

                    if (!empty($serviceClinicIds)) {
                        $clinicsQuery->whereIn('id', $serviceClinicIds);
                    } else {
                        // Service not found in any clinic
                        $clinicsQuery->whereRaw('0=1');
                    }
                }
            }

            // Filter by doctor_id and exclude clinics with existing sessions
            if ($request instanceof WP_REST_Request) {
                $doctorId = $request->get_param('doctor_id');
                $excludeWithSessions = $request->get_param('exclude_with_sessions');

                if (!empty($doctorId)) {

                    // First, filter to only clinics where this doctor works
                    $doctorClinicIds = KCDoctorClinicMapping::query()
                        ->where('doctor_id', absint($doctorId))
                        ->select(['clinic_id'])
                        ->get()
                        ->map(fn($row) => $row->clinicId)
                        ->toArray();

                    $doctorClinicIds = array_filter($doctorClinicIds);


                    if (!empty($doctorClinicIds)) {
                        $clinicsQuery->whereIn('id', $doctorClinicIds);

                        // If exclude_with_sessions is true, exclude clinics where doctor has sessions
                        if ($excludeWithSessions === true) {
                            // Get clinic IDs where this doctor already has sessions
                            $sessionClinicIds = \App\models\KCClinicSession::query()
                                ->where('doctorId', absint($doctorId))
                                ->select(['clinic_id'])
                                ->groupBy('clinic_id')
                                ->get()
                                ->map(fn($row) => $row->clinicId)
                                ->toArray();

                            $sessionClinicIds = array_filter($sessionClinicIds);

                            if (!empty($sessionClinicIds)) {
                                // Exclude clinics where doctor already has sessions
                                $clinicsQuery->whereNotIn('id', $sessionClinicIds);
                            }
                        }
                    } else {
                        // Doctor not associated with any clinic
                        $clinicsQuery->whereRaw('0=1');
                    }
                }
            }

            // Get total count before pagination
            $totalCount = $clinicsQuery->count();

            // Handle pagination
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $offset = ($page - 1) * $perPage;
                if ($page !== -1) {
                    $clinicsQuery->limit($perPage)->offset($offset);
                }
            }
            $clinics = $clinicsQuery->get();

            // Fetch active holidays for clinics
            $holidayClinicIds = \App\models\KCClinicSchedule::getActiveHolidaysByModule('clinic');

            $result = $clinics->map(function ($clinic) use ($holidayClinicIds) {

                // Build full address dynamically
                $fullAddressParts = [];

                if (!empty($clinic->address)) {
                    $fullAddressParts[] = $clinic->address;
                }
                if (!empty($clinic->city)) {
                    $fullAddressParts[] = $clinic->city;
                }
                if (!empty($clinic->country)) {
                    $fullAddressParts[] = $clinic->country;
                }

                $fullAddress = implode(', ', $fullAddressParts);

                $clinicImageUrl = '';
                if ($clinic->clinicLogo) {
                    $clinicImageUrl = wp_get_attachment_url($clinic->clinicLogo);
                }

                $status = $clinic->status;
                // If clinic is in the holiday list, add active holiday key in response
                $is_holiday = in_array((int)$clinic->id, $holidayClinicIds, true);

                return [
                    'id' => $clinic->id,
                    'label' => $clinic->name,
                    'value' => $clinic->id,
                    'address' => $fullAddress,      // full formatted address
                    'clinic_logo' => $clinicImageUrl,
                    'status' => $status,
                    'is_holiday' => $is_holiday,
                ];
            })->toArray();

            // Add pagination metadata if request provided
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $totalPages = ceil($totalCount / $perPage);

                return [
                    'data' => $result,
                    'pagination' => [
                        'total' => $totalCount,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'has_more' => $page < $totalPages,
                    ]
                ];
            }

            return $result;
        } catch (\Exception $e) {
            if ($request instanceof WP_REST_Request) {
                return ['data' => [], 'pagination' => ['total' => 0, 'per_page' => 10, 'current_page' => 1, 'total_pages' => 0, 'has_more' => false]];
            }
            return [];
        }
    }

    /**
     * Get static data by type
     */
    private function getStaticDataByType(string $type, ?WP_REST_Request $request = null): array
    {
        if (empty($type)) {
            return $request instanceof WP_REST_Request ? ['data' => [], 'pagination' => ['total' => 0, 'per_page' => 10, 'current_page' => 1, 'total_pages' => 0, 'has_more' => false]] : [];
        }
        try {
            $query = KCStaticData::query()
                ->select(['id', 'label', 'value'])
                ->where('type', $type)
                ->where('status', 1);

            // Handle search
            if ($request instanceof WP_REST_Request && !empty($request->get_param('search'))) {
                $search = $request->get_param('search');
                $query->where(function ($q) use ($search) {
                    $q->where('label', 'LIKE', '%' . $search . '%')
                        ->orWhere('value', 'LIKE', '%' . $search . '%');
                });
            }

            // Get total count before pagination
            $totalCount = $query->count();

            // Handle pagination
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $offset = ($page - 1) * $perPage;

                $query->limit($perPage)->offset($offset);
            }

            $data = $query->get();

            $result = $data->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'label' => $item->label,
                    'value' => $item->value,
                ];
            })->toArray();

            // Add pagination metadata if request provided
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $totalPages = ceil($totalCount / $perPage);

                return [
                    'data' => $result,
                    'pagination' => [
                        'total' => $totalCount,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'has_more' => $page < $totalPages,
                    ]
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return $request instanceof WP_REST_Request ? ['data' => [], 'pagination' => ['total' => 0, 'per_page' => 10, 'current_page' => 1, 'total_pages' => 0, 'has_more' => false]] : [];
        }
    }

    /**
     * Get static data with label formatting
     */
    private function getStaticDataWithLabel(string $type, ?WP_REST_Request $request = null): array
    {
        if (empty($type)) {
            return $request instanceof WP_REST_Request ? ['data' => [], 'pagination' => ['total' => 0, 'per_page' => 10, 'current_page' => 1, 'total_pages' => 0, 'has_more' => false]] : [];
        }

        try {
            $query = KCStaticData::query()
                ->select(['id', 'label', 'value'])
                ->where('type', $type)
                ->where('status', 1);

            // Handle search
            if ($request instanceof WP_REST_Request && !empty($request->get_param('search'))) {
                $search = $request->get_param('search');
                $query->where(function ($q) use ($search) {
                    $q->where('label', 'LIKE', '%' . $search . '%')
                        ->orWhere('value', 'LIKE', '%' . $search . '%');
                });
            }

            // Get total count before pagination
            $totalCount = $query->count();

            // Handle pagination
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $offset = ($page - 1) * $perPage;

                $query->limit($perPage)->offset($offset);
            }

            $data = $query->get();

            $result = $data->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'label' => $item->label ?: $item->value,
                ];
            })->toArray();

            // Add pagination metadata if request provided
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $totalPages = ceil($totalCount / $perPage);

                return [
                    'data' => $result,
                    'pagination' => [
                        'total' => $totalCount,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'has_more' => $page < $totalPages,
                    ]
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return $request instanceof WP_REST_Request ? ['data' => [], 'pagination' => ['total' => 0, 'per_page' => 10, 'current_page' => 1, 'total_pages' => 0, 'has_more' => false]] : [];
        }
    }

    /**
     * Get all static data types
     */
    private function getStaticDataTypes(): array
    {
        try {
            $types = KCStaticData::query()
                ->where('status', 1)
                ->orderBy('type')
                ->groupBy('type') // Use groupBy to get unique values
                ->get(['type'])
                ->pluck('type')
                ->toArray();

            return array_map(function ($type) {
                return [
                    'label' => kcGetStaticDataTypeLabel($type),
                    'value' => $type,
                ];
            }, $types);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get patient clinics
     */
    private function getPatientClinics(int $patientId): array
    {
        // This would need to be implemented based on your patient-clinic mapping logic
        return [];
    }

    /**
     * Get doctors list
     */
    private function getDoctorsList($clinicId = 0, ?WP_REST_Request $request = null): array
    {
        try {
            $query = KCDoctor::query()->where('user_status', 0);

            // If user is a patient and no clinic_id provided, filter by patient's assigned clinics
            if (empty($clinicId) && is_user_logged_in()) {
                $currentUserId = get_current_user_id();
                $currentUserRole = $this->kcbase->getLoginUserRole();

                if ($currentUserRole === $this->kcbase->getPatientRole()) {
                    $patientClinicIds = KCPatientClinicMapping::query()
                        ->where('patientId', $currentUserId)
                        ->select(['clinic_id'])
                        ->get()
                        ->map(fn($row) => $row->clinicId)
                        ->toArray();

                    if (!empty($patientClinicIds)) {
                        $clinicId = implode(',', $patientClinicIds);
                    }
                }
            }

            // Convert comma-separated string to array if needed
            if (!empty($clinicId)) {
                if (is_string($clinicId)) {
                    $clinicIdArr = array_filter(array_map('intval', explode(',', $clinicId)));
                } elseif (is_array($clinicId)) {
                    $clinicIdArr = array_map('intval', $clinicId);
                } else {
                    $clinicIdArr = [(int) $clinicId];
                }

                if (!empty($clinicIdArr)) {
                    $query->leftJoin(KCDoctorClinicMapping::class, function ($join) {
                        $join->on('dcm.doctor_id', '=', 'doctors.id');
                    }, null, null, 'dcm');
                    $query->whereIn('dcm.clinic_id', $clinicIdArr);
                }
            }

            // Filter by service_id if provided
            $service_id = $request ? $request->get_param('service_id') : null;
            if (!empty($service_id)) {
                $query->leftJoin(KCServiceDoctorMapping::class, function ($join) {
                    $join->on('sdm.doctor_id', '=', 'doctors.id');
                }, null, null, 'sdm');
                $query->where('sdm.service_id', $service_id);
            }

            // Telemed filtering logic
            $telemed_service = $request ? $request->get_param('telemed_service') : null;

            if ($telemed_service === 'yes' && (isKiviCareTelemedActive() || isKiviCareGoogleMeetActive())) {

                $telemed_doctor_ids = [];

                // Check Zoom
                if (isKiviCareTelemedActive()) {
                    $zoom_global = \App\models\KCOption::get('zoom_telemed_setting');
                    $zoom_global = maybe_unserialize($zoom_global);
                    $legacy_s2s_key = defined('KIVICARE_TELEMED_PREFIX') ? KIVICARE_TELEMED_PREFIX . 'zoom_telemed_server_to_server_oauth_status' : 'kiviCare_zoom_telemed_server_to_server_oauth_status';
                    $legacy_s2s_status = get_option($legacy_s2s_key, false);
                    $zoom_globally_enabled = ($legacy_s2s_status === 'Yes') || (!empty($zoom_global['enableCal']) && $zoom_global['enableCal'] === 'Yes');

                    if ($zoom_globally_enabled) {
                        // Find doctors with OAuth connected
                        $zoom_oauth_doctors = KCUserMeta::query()
                            ->where('metaKey', 'kiviCare_zoom_telemed_connect')
                            ->where('metaValue', 'on')
                            ->get(['userId'])->pluck('userId')->toArray();
                        $telemed_doctor_ids = array_merge($telemed_doctor_ids, $zoom_oauth_doctors);

                        // Find doctors with S2S connected
                        $zoom_s2s_doctors = KCUserMeta::query()
                            ->where('metaKey', 'zoom_server_to_server_oauth_config_data')
                            ->where('metaValue', 'LIKE', '%"enableServerToServerOauthconfig":"true"%')
                            ->get(['userId'])->pluck('userId')->toArray();
                        $telemed_doctor_ids = array_merge($telemed_doctor_ids, $zoom_s2s_doctors);
                    }
                }

                // Check Google Meet
                if (isKiviCareGoogleMeetActive()) {
                    $gmeet_global = \App\models\KCOption::get('google_meet_setting');
                    $gmeet_globally_enabled = (
                        (!empty($gmeet_global['enabled']) && filter_var($gmeet_global['enabled'], FILTER_VALIDATE_BOOLEAN)) ||
                        (!empty($gmeet_global['auth_type']) && $gmeet_global['auth_type'] === 'oauth') ||
                        (!empty($gmeet_global['enableCal']) && in_array($gmeet_global['enableCal'], ['Yes', 'on']))
                    );

                    if ($gmeet_globally_enabled) {
                        // Find Google Meet connected doctors
                        $gmeet_doctors = KCUserMeta::query()
                            ->where('metaKey', 'kiviCare_google_meet_connect')
                            ->where('metaValue', 'on')
                            ->get(['userId'])->pluck('userId')->toArray();
                        $telemed_doctor_ids = array_merge($telemed_doctor_ids, $gmeet_doctors);
                    }
                }

                $telemed_doctor_ids = array_unique(array_map('intval', $telemed_doctor_ids));

                if (!empty($telemed_doctor_ids)) {
                    $query->whereIn('doctors.ID', $telemed_doctor_ids);
                } else {
                    // If telemed is requested but no doctors are configured, return no doctors.
                    $query->whereRaw('0=1');
                }
            }


            // Handle search
            if ($request instanceof WP_REST_Request && !empty($request->get_param('search'))) {
                $search = $request->get_param('search');
                $query->where(function ($q) use ($search) {
                    $q->orWhere('display_name', 'LIKE', '%' . $search . '%');
                });
            }

            // Exclude doctors with existing sessions at specified clinic(s)
            if ($request instanceof WP_REST_Request) {
                $excludeWithSessions = $request->get_param('exclude_with_sessions');

                if ($excludeWithSessions === true && !empty($clinicIdArr)) {
                    // Get doctor IDs that already have sessions at these clinic(s)
                    $doctorIdsWithSessions = \App\models\KCClinicSession::query()
                        ->whereIn('clinic_id', $clinicIdArr)
                        ->whereNull('parent_session_id') // Only get parent sessions
                        ->select(['doctor_id'])
                        ->groupBy('doctor_id')
                        ->get()
                        ->map(fn($row) => $row->doctorId)
                        ->toArray();

                    $doctorIdsWithSessions = array_filter($doctorIdsWithSessions);

                    if (!empty($doctorIdsWithSessions)) {
                        // Exclude these doctors from the results
                        $query->whereNotIn('doctors.ID', $doctorIdsWithSessions);
                    }
                }
            }

            // Get total count before pagination
            $totalCount = $query->count();

            // Handle pagination
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $offset = ($page - 1) * $perPage;

                if ($page !== -1) {
                    $query->limit($perPage)->offset($offset);
                }
            }

            // Group by doctor ID to avoid duplicate entries
            $query->groupBy('doctors.id');

            $doctors = $query->get();
            $result = $doctors->map(function ($doctor) use ($request) {
                // Doctor profile image
                $doctorImage = '';
                $profileImageId = get_user_meta($doctor->user_id, 'doctor_profile_image', true);
                if ($profileImageId) {
                    $doctorImage = wp_get_attachment_url($profileImageId);
                }

                // Doctor specialties from basic_data user meta
                $basicData = [];
                $basicDataJson = get_user_meta($doctor->user_id, 'basic_data', true);
                if (!empty($basicDataJson) && is_string($basicDataJson)) {
                    $decoded = json_decode($basicDataJson, true);
                    if (is_array($decoded)) {
                        $basicData = $decoded;
                    }
                }
                $specialties = isset($basicData['specialties']) && is_array($basicData['specialties'])
                    ? $basicData['specialties']
                    : [];

                $doctorData = [
                    'id' => (int) $doctor->user_id,
                    'label' => $doctor->display_name ?? trim(($doctor->first_name ?? '') . ' ' . ($doctor->last_name ?? '')),
                    'value' => (int) $doctor->user_id,
                    'doctor_image_url' => $doctorImage ?: '',
                    'specialties' => $specialties,
                ];

                // Allow Pro to append data such as average_rating
                $doctorData = apply_filters('kc_doctor_list_item_data', $doctorData, $doctor, $request);

                return $doctorData;
            })->toArray();

            // Add pagination metadata if request provided
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $totalPages = ceil($totalCount / $perPage);

                return [
                    'data' => $result,
                    'pagination' => [
                        'total' => $totalCount,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'has_more' => $page < $totalPages,
                    ]
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return $request instanceof WP_REST_Request ? ['data' => [], 'pagination' => ['total' => 0, 'per_page' => 10, 'current_page' => 1, 'total_pages' => 0, 'has_more' => false]] : [];
        }
    }

    /**
     * Get patients list
     */
    private function getPatientsList($clinicId = 0, ?WP_REST_Request $request = null): array
    {
        try {
            $query = KCPatient::query()->setTableAlias('user')->where('user.user_status', 0);

            // Add patient unique ID settings check
            $patientIdSetting = KCOption::get('patient_id_setting', []);
            $isPatientIdEnabled = isset($patientIdSetting['enable']) && in_array((string)$patientIdSetting['enable'], ['true', '1', 'on'], true);

            if ($isPatientIdEnabled) {
                // Join usermeta to get patient_unique_id with a unique alias 'puid'
                $query->leftJoin('usermeta', function ($join) {
                    $join->on('puid.user_id', '=', 'user.ID')
                        ->onRaw("puid.meta_key = 'patient_unique_id'");
                }, null, null, 'puid');

                $query->select(['user.*', 'puid.meta_value as patient_unique_id']);
            } else {
                $query->select(['user.*']);
            }

 
            // Convert comma-separated string to array if needed
            if (!empty($clinicId)) {
                if (is_string($clinicId)) {
                    $clinicIdArr = array_filter(array_map('intval', explode(',', $clinicId)));
                } elseif (is_array($clinicId)) {
                    $clinicIdArr = array_map('intval', $clinicId);
                } else {
                    $clinicIdArr = [(int) $clinicId];
                }

                if (!empty($clinicIdArr)) {
                    $query->leftJoin(KCPatientClinicMapping::class, function ($join) {
                        $join->on('pcm.patient_id', '=', 'user.ID');
                    }, null, null, 'pcm');
                    $query->whereIn('pcm.clinic_id', $clinicIdArr);
                }
            }

            if ($request instanceof WP_REST_Request && $request->get_param('patient_id')) {
                $patientIdsParam = $request->get_param('patient_id');
                $query->where('user.ID', $patientIdsParam);
            }

            // Filter by doctor_id if provided
            if ($request instanceof WP_REST_Request && $request->get_param('doctor_id')) {
                $doctorId = $request->get_param('doctor_id');

                // Get patients associated with this doctor (via appointments)
                $associatedPatientIds = KCAppointment::query()
                    ->where('doctor_id', $doctorId)
                    ->select(['patient_id'])
                    ->groupBy('patient_id')
                    ->get()
                    ->pluck('patientId')
                    ->toArray();

                if (!empty($associatedPatientIds)) {
                    // Filter to include ONLY these patients
                    $query->whereIn('user.ID', $associatedPatientIds);
                } else {
                    // If the doctor has no appointments/patients, return empty result
                    $query->whereRaw('1 = 0');
                }
            }

            // Handle search
            if ($request instanceof WP_REST_Request && !empty($request->get_param('search'))) {
                $search = $request->get_param('search');
                $query->where(function ($q) use ($search, $isPatientIdEnabled) {
                    $q->orWhere('user.display_name', 'LIKE', '%' . $search . '%');
                    if ($isPatientIdEnabled) {
                        $q->orWhere('puid.meta_value', 'LIKE', '%' . $search . '%');
                    }
                });
            }

            // Get total count before pagination
            $totalCount = $query->count();

            // Handle pagination
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $offset = ($page - 1) * $perPage;

                if ($page !== -1) {
                    $query->limit($perPage)->offset($offset);
                }
            }

            $patients = $query->get();
            $result = $patients->map(function ($patient) use ($isPatientIdEnabled, $request) {
                $patientImage = '';
                $profileImageId = get_user_meta($patient->id, 'patient_profile_image', true);
                if ($profileImageId) {
                    $patientImage = wp_get_attachment_url($profileImageId);
                }

                $displayName = $patient->display_name ?? $patient->first_name . ' ' . $patient->last_name;
                $uniqueId = $isPatientIdEnabled ? $patient->patient_unique_id : '';

                $label = $displayName;
                if ($isPatientIdEnabled && !empty($uniqueId)) {
                    $label .= ' (' . $uniqueId . ')';
                }

                $patientData = [
                    'id' => $patient->id,
                    'label' => $label,
                    'value' => $patient->id,
                    'patient_image_url' => $patientImage ?: '',
                    'patient_unique_id' => $uniqueId,
                ];
                return apply_filters('kc_patient_list_item_data', $patientData, $patient, $request);
                
            })->toArray();

            // Add pagination metadata if request provided
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $totalPages = ceil($totalCount / $perPage);

                return [
                    'data' => $result,
                    'pagination' => [
                        'total' => $totalCount,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'has_more' => $page < $totalPages,
                    ]
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return $request instanceof WP_REST_Request ? ['data' => [], 'pagination' => ['total' => 0, 'per_page' => 10, 'current_page' => 1, 'total_pages' => 0, 'has_more' => false]] : [];
        }
    }

    /**
     * Get default clinic
     */
    private function getDefaultClinic(): array
    {
        try {
            $clinic = KCClinic::query()
                ->where('status', 1)
                ->first();

            if ($clinic) {
                $clinicImageUrl = '';
                if ($clinic->clinicLogo) {
                    $clinicImageUrl = wp_get_attachment_url($clinic->clinicLogo);
                }

                return [
                    'id' => $clinic->id,
                    'label' => $clinic->name,
                    'value' => $clinic->id,
                    'image' => $clinicImageUrl ?: '',
                ];
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get services with price
     */
    private function getServicesWithPrice(int $clinic_id = 0, ?WP_REST_Request $request = null): array
    {
        try {

            $is_telemed = null;

            // Get doctor IDs from request
            $doctor_id = $request ? $request->get_param('doctor_id') : 0;
            $doctor_ids_param = $request ? $request->get_param('doctor_ids') : null;
            $doctorIds = null;

            if ($doctor_ids_param) {
                $doctorIds = array_map('intval', explode(',', $doctor_ids_param));
            } elseif ($doctor_id > 0) {
                $doctorIds = (int) $doctor_id;
            }

            if (!isKiviCareTelemedActive() && !isKiviCareGoogleMeetActive()) {
                $is_telemed = false;
            } elseif (is_int($doctorIds) && $doctorIds > 0) {
                // Single doctor telemed logic
                $doctor_id = $doctorIds;

                // Get Global Settings
                $zoom_global = \App\models\KCOption::get('zoom_telemed_setting');
                $zoom_global = maybe_unserialize($zoom_global); // Ensure it's an array

                // Get the separate legacy key for Server-to-Server status
                $legacy_s2s_key = defined('KIVICARE_TELEMED_PREFIX') ? KIVICARE_TELEMED_PREFIX . 'zoom_telemed_server_to_server_oauth_status' : 'kiviCare_zoom_telemed_server_to_server_oauth_status';
                $legacy_s2s_status = get_option($legacy_s2s_key, false);

                // Determine Global Enabled Status by checking both legacy keys
                $zoom_globally_enabled = ($legacy_s2s_status === 'Yes') || (!empty($zoom_global['enableCal']) && $zoom_global['enableCal'] === 'Yes');

                // Get Doctor's connection status for both OAuth and S2S
                $is_oauth_connected = (get_user_meta($doctor_id, 'kiviCare_zoom_telemed_connect', true) === 'on');

                $is_s2s_connected = false;
                $legacy_s2s_config_json = get_user_meta($doctor_id, 'zoom_server_to_server_oauth_config_data', true);
                if (!empty($legacy_s2s_config_json)) {
                    $s2s_data = json_decode($legacy_s2s_config_json, true);
                    if (isset($s2s_data['enableServerToServerOauthconfig']) && ($s2s_data['enableServerToServerOauthconfig'] === 'true' || $s2s_data['enableServerToServerOauthconfig'] === true)) {
                        $is_s2s_connected = true;
                    }
                }

                $is_doctor_connected_to_zoom = $is_oauth_connected || $is_s2s_connected;

                $gmeet_global = \App\models\KCOption::get('google_meet_setting');

                $gmeet_globally_enabled = (
                    (!empty($gmeet_global['enabled']) && filter_var($gmeet_global['enabled'], FILTER_VALIDATE_BOOLEAN)) ||
                    (!empty($gmeet_global['auth_type']) && $gmeet_global['auth_type'] === 'oauth') ||
                    (!empty($gmeet_global['enableCal']) && in_array($gmeet_global['enableCal'], ['Yes', 'on']))
                );

                $gmeet_doctor_val = get_user_meta($doctor_id, 'kiviCare_google_meet_connect', true);

                // Calculate Effective Status
                $is_zoom_on = $zoom_globally_enabled && $is_doctor_connected_to_zoom;
                $is_gmeet_on = $gmeet_globally_enabled && $gmeet_doctor_val === 'on';

                // If NEITHER is on, hide telemed services
                if (!$is_zoom_on && !$is_gmeet_on) {
                    $is_telemed = false;
                }
            }
            // For multiple doctors, $is_telemed remains null to include all services

            $services = KCServiceDoctorMapping::getActiveDoctorServices($doctorIds, $clinic_id === -1 ? 0 : $clinic_id, $is_telemed);

            // Convert to collection for easier manipulation
            $servicesCollection = collect($services);

            // Handle search
            if ($request instanceof WP_REST_Request && !empty($request->get_param('search'))) {
                $search = $request->get_param('search');
                $servicesCollection = $servicesCollection->filter(function ($service) use ($search) {
                    return stripos($service->service_name ?? '', $search) !== false;
                });
            }

            $totalCount = $servicesCollection->count();

            // Handle pagination
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $offset = ($page - 1) * $perPage;

                $servicesCollection = $servicesCollection->slice($offset, $perPage);
            }

            $result = $servicesCollection->map(function ($service) {
                return [
                    'id' => $service->id,
                    'service_id' => $service->serviceId,
                    'label' => $service->service_name,
                    'price' => $service->price ?? 0,
                    'is_telemed' => $service->isTelemed,
                    'allow_multiple' => $service->allowMultiple,
                ];
            })->values()->toArray();

            // Add pagination metadata if request provided
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $totalPages = ceil($totalCount / $perPage);

                return [
                    'data' => $result,
                    'pagination' => [
                        'total' => $totalCount,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'has_more' => $page < $totalPages,
                    ]
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return $request instanceof WP_REST_Request ? ['data' => [], 'pagination' => ['total' => 0, 'per_page' => 10, 'current_page' => 1, 'total_pages' => 0, 'has_more' => false]] : [];
        }
    }

    /**
     * Get prescriptions
     */
    private function getPrescriptions(): array
    {
        // This would return prescription templates or common prescriptions
        return [];
    }

    /**
     * Get email template types
     */
    private function getEmailTemplateTypes(): array
    {
        return [
            ['label' => 'Appointment Confirmation', 'value' => 'appointment_confirmation'],
            ['label' => 'Appointment Reminder', 'value' => 'appointment_reminder'],
            ['label' => 'Patient Registration', 'value' => 'patient_registration'],
            ['label' => 'Doctor Registration', 'value' => 'doctor_registration'],
        ];
    }

    /**
     * Get email template keys
     */
    private function getEmailTemplateKeys(): array
    {
        return [
            ['label' => 'Patient Name', 'value' => '{{patient_name}}'],
            ['label' => 'Doctor Name', 'value' => '{{doctor_name}}'],
            ['label' => 'Appointment Date', 'value' => '{{appointment_date}}'],
            ['label' => 'Appointment Time', 'value' => '{{appointment_time}}'],
            ['label' => 'Clinic Name', 'value' => '{{clinic_name}}'],
        ];
    }

    /**
     * Get users by clinic
     */
    private function getUsersByClinic(int $clinicId, ?WP_REST_Request $request = null): array
    {
        try {
            $userIds = [];

            if ($clinicId > 0) {
                // Get doctors associated with the clinic
                $doctorIds = KCDoctorClinicMapping::query()
                    ->where('clinic_id', $clinicId)
                    ->select(['doctor_id'])
                    ->get()
                    ->map(fn($row) => $row->doctorId)
                    ->toArray();
                $userIds = array_merge($userIds, $doctorIds);

                // Get patients associated with the clinic
                $patientIds = KCPatientClinicMapping::query()
                    ->where('clinic_id', $clinicId)
                    ->select(['patient_id'])
                    ->get()
                    ->map(fn($row) => $row->patientId)
                    ->toArray();
                $userIds = array_merge($userIds, $patientIds);

                // Get receptionists associated with the clinic
                $receptionistIds = KCReceptionistClinicMapping::query()
                    ->where('clinic_id', $clinicId)
                    ->select(['receptionist_id'])
                    ->get()
                    ->map(fn($row) => $row->receptionistId)
                    ->toArray();
                $userIds = array_merge($userIds, $receptionistIds);

                // Get clinic admin for the clinic
                $clinic = KCClinic::find($clinicId);
                if ($clinic && !empty($clinic->clinicAdminId)) {
                    $userIds[] = $clinic->clinicAdminId;
                }

                // Remove duplicates and filter out empty values
                $userIds = array_unique(array_filter($userIds));

                // If no users found for the clinic, return empty result
                if (empty($userIds)) {
                    return $request instanceof WP_REST_Request ? [
                        'data' => [],
                        'pagination' => [
                            'total' => 0,
                            'per_page' => $request->get_param('per_page') ?: 10,
                            'current_page' => $request->get_param('page') ?: 1,
                            'total_pages' => 0,
                            'has_more' => false
                        ]
                    ] : [];
                }
            }

            // Build query for users
            $query = KCUser::query()->where('status', 0); // 0 means active in WordPress

            // Filter by user IDs if clinic ID was provided
            if ($clinicId > 0 && !empty($userIds)) {
                $query->whereIn('id', $userIds);
            }

            // Handle search
            if ($request instanceof WP_REST_Request && !empty($request->get_param('search'))) {
                $search = $request->get_param('search');
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', '%' . $search . '%')
                        ->orWhere('last_name', 'LIKE', '%' . $search . '%')
                        ->orWhere('display_name', 'LIKE', '%' . $search . '%')
                        ->orWhere('user_email', 'LIKE', '%' . $search . '%');
                });
            }

            // Get total count before pagination
            $totalCount = $query->count();

            // Handle pagination
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $offset = ($page - 1) * $perPage;

                if ($page !== -1) {
                    $query->limit($perPage)->offset($offset);
                }
            }

            $users = $query->get();

            // Get user roles for each user
            $kcBase = KCBase::get_instance();
            $doctorRole = $kcBase->getDoctorRole();
            $patientRole = $kcBase->getPatientRole();
            $receptionistRole = $kcBase->getReceptionistRole();
            $clinicAdminRole = $kcBase->getClinicAdminRole();

            $result = $users->map(function ($user) use ($doctorRole, $patientRole, $receptionistRole, $clinicAdminRole) {
                // Get user role from WordPress
                $wpUser = get_userdata($user->id);
                $userRole = '';
                if ($wpUser && !empty($wpUser->roles)) {
                    $roles = $wpUser->roles;
                    // Get the first KiviCare role found
                    foreach ($roles as $role) {
                        if (in_array($role, [$doctorRole, $patientRole, $receptionistRole, $clinicAdminRole])) {
                            $userRole = $role;
                            break;
                        }
                    }
                }

                // Get profile image based on user role
                $profileImage = '';
                $imageKey = '';
                if (!empty($userRole)) {
                    $profileImageMetaKey = '';
                    if ($userRole === $doctorRole) {
                        $profileImageMetaKey = 'doctor_profile_image';
                        $imageKey = 'doctor_image_url';
                    } elseif ($userRole === $patientRole) {
                        $profileImageMetaKey = 'patient_profile_image';
                        $imageKey = 'patient_image_url';
                    } elseif ($userRole === $receptionistRole) {
                        $profileImageMetaKey = 'receptionist_profile_image';
                        $imageKey = 'receptionist_image_url';
                    } elseif ($userRole === $clinicAdminRole) {
                        $profileImageMetaKey = 'clinic_admin_profile_image';
                        $imageKey = 'clinic_admin_image_url';
                    }

                    if (!empty($profileImageMetaKey)) {
                        $profileImageId = get_user_meta($user->id, $profileImageMetaKey, true);
                        if ($profileImageId) {
                            $profileImage = wp_get_attachment_url($profileImageId) ?: '';
                        }
                    }
                }

                // return empty string if no profile image
                if (empty($profileImage)) {
                    $profileImage = '';
                }

                // Build return array with appropriate image key
                $returnArray = [
                    'id' => $user->id,
                    'label' => $user->displayName ?? ($user->firstName . ' ' . $user->lastName),
                    'value' => $user->id,
                    'role' => $userRole,
                ];

                // Add the appropriate image key based on role
                if (!empty($imageKey)) {
                    $returnArray[$imageKey] = $profileImage;
                } else {
                    // Fallback for unknown roles
                    $returnArray['doctor_image_url'] = $profileImage;
                }

                return $returnArray;
            })->toArray();

            // Add pagination metadata if request provided
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $totalPages = ceil($totalCount / $perPage);

                return [
                    'data' => $result,
                    'pagination' => [
                        'total' => $totalCount,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'has_more' => $page < $totalPages,
                    ]
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return $request instanceof WP_REST_Request ? ['data' => [], 'pagination' => ['total' => 0, 'per_page' => 10, 'current_page' => 1, 'total_pages' => 0, 'has_more' => false]] : [];
        }
    }

    /**
     * Get clinic doctors
     */
    private function getClinicDoctors($clinicId, ?WP_REST_Request $request = null): array
    {
        return $this->getDoctorsList($clinicId, $request);
    }

    /**
     * Get users by role
     */
    private function getUsers(string $role = '', ?WP_REST_Request $request = null): array
    {
        try {
            $kcBase = KCBase::get_instance();

            switch ($role) {
                case $kcBase->getDoctorRole():
                    $query = KCDoctor::query()->where('user_status', 0);
                    break;

                case $kcBase->getPatientRole():
                    $query = KCPatient::query()->where('user_status', 0);
                    break;

                case $kcBase->getReceptionistRole():
                    $query = KCReceptionist::query()->where('user_status', 0);
                    break;

                case $kcBase->getClinicAdminRole():
                    $query = KCClinicAdmin::query()->where('user_status', 0);
                    break;

                default:
                    return $request instanceof WP_REST_Request ? ['data' => [], 'pagination' => ['total' => 0, 'per_page' => 10, 'current_page' => 1, 'total_pages' => 0, 'has_more' => false]] : [];
            }

            // Handle clinic filter
            $clinicIdParam = $request instanceof WP_REST_Request
                ? ($request->get_param('clinic_ids') ?? $request->get_param('clinic_id'))
                : null;

            if (!empty($clinicIdParam) && $clinicIdParam !== 'undefined' && $clinicIdParam !== 'null') {
                $clinicIds = is_array($clinicIdParam) ? $clinicIdParam : explode(',', (string) $clinicIdParam);
                $clinicIds = array_filter(array_map('intval', $clinicIds));

                if (!empty($clinicIds)) {
                    if ($role === $kcBase->getPatientRole()) {
                        $query->select(['user.*'])
                            ->leftJoin(KCPatientClinicMapping::class, 'user.ID', '=', 'pcm.patient_id', 'pcm')
                            ->whereIn('pcm.clinic_id', $clinicIds);
                    } elseif ($role === $kcBase->getDoctorRole()) {
                        $query->select(['doctors.*'])
                            ->leftJoin(KCDoctorClinicMapping::class, 'doctors.ID', '=', 'dcm.doctor_id', 'dcm')
                            ->whereIn('dcm.clinic_id', $clinicIds);
                    }
                }
            }

            // Handle search
            if ($request instanceof WP_REST_Request && !empty($request->get_param('search'))) {
                $search = $request->get_param('search');
                $query->where(function ($q) use ($search) {
                    $q->where('display_name', 'LIKE', '%' . $search . '%');
                });
            }

            // Get total count before pagination
            $totalCount = $query->count();

            // Handle pagination
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $offset = ($page - 1) * $perPage;

                $query->limit($perPage)->offset($offset);
            }

            $users = $query->get();

            $result = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'label' => $user->displayName ?? ($user->firstName . ' ' . $user->lastName),
                    'value' => $user->id,
                ];
            })->toArray();

            // Add pagination metadata if request provided
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $totalPages = ceil($totalCount / $perPage);

                return [
                    'data' => $result,
                    'pagination' => [
                        'total' => $totalCount,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'has_more' => $page < $totalPages,
                    ]
                ];
            }

            return $result;
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Error fetching users: ' . $e->getMessage());
            return $request instanceof WP_REST_Request ? ['data' => [], 'pagination' => ['total' => 0, 'per_page' => 10, 'current_page' => 1, 'total_pages' => 0, 'has_more' => false]] : [];
        }
    }



    /**
     * Get clinics with all details for frontend booking form
     */
    private function getClinicsWithAllDetails(?WP_REST_Request $request = null): array
    {
        try {
            $clinicId = $request ? $request->get_param('clinic_id') : null;
            $doctorId = $request ? $request->get_param('doctor_id') : null;
            $search = $request ? $request->get_param('search') : null;

            $clinicsQuery = KCClinic::query();
            
            // Only show inactive clinics if is_app is set to true
            if (!$request->has_param('is_app') || ($request->get_param('is_app') !== 'true' && $request->get_param('is_app') !== true)) {
                 $clinicsQuery->where('status', 1);
            }

            if (!empty($clinicId)) {
                $clinicsQuery->where('id', $clinicId);
            } elseif (!empty($doctorId)) {
                $clinicIds = KCDoctorClinicMapping::query()
                    ->where('doctor_id', $doctorId)
                    ->select(['clinic_id'])
                    ->get()
                    ->map(fn($row) => $row->clinicId)
                    ->toArray();

                if (!empty($clinicIds)) {
                    $clinicsQuery->whereIn('id', $clinicIds);
                } else {
                    return ['clinics' => [], 'pagination' => null, 'settings' => ['showClinicImage' => false, 'showClinicAddress' => false, 'clinicContactDetails' => null]];
                }
            }

            // Handle search
            if (!empty($search)) {
                $clinicsQuery->where('name', 'LIKE', '%' . $search . '%');
            }

            // Get total count before pagination
            $totalCount = $clinicsQuery->count();

            // Handle pagination
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $offset = ($page - 1) * $perPage;

                if ($page !== -1) {
                    $clinicsQuery->limit($perPage)->offset($offset);
                }
            }

            $allClinics = $clinicsQuery->get();

            $serviceCounts = [];
            $doctorCounts = [];
            $appointmentCounts = [];

            if ($allClinics->isNotEmpty()) {
                $clinicIds = $allClinics->pluck('id')->toArray();

                $serviceCounts = KCServiceDoctorMapping::query()
                    ->whereIn('clinic_id', $clinicIds)
                    ->where('status', 1)
                    ->select(['clinic_id', 'service_id'])
                    ->get()
                    ->groupBy('clinicId')
                    ->map(function ($services) {
                        return $services->pluck('serviceId')->unique()->count();
                    })
                    ->toArray();

                $doctorCounts = KCDoctorClinicMapping::table('dcm')
                    ->leftJoin(KCDoctor::class, 'dcm.doctor_id', '=', 'd.ID', 'd')
                    ->whereIn('dcm.clinic_id', $clinicIds)
                    ->where('d.user_status', 0)
                    ->select(['dcm.clinic_id', 'dcm.doctor_id'])
                    ->get()
                    ->groupBy('clinicId')
                    ->map(function ($doctors) {
                        return $doctors->pluck('doctorId')->unique()->count();
                    })
                    ->toArray();

                // Total appointments per clinic
                $appointmentCounts = KCAppointment::query()
                    ->whereIn('clinic_id', $clinicIds)
                    ->select(['clinic_id'])
                    ->get()
                    ->groupBy('clinicId')
                    ->map(function ($appointments) {
                        return $appointments->count();
                    })
                    ->toArray();
            }

            $clinics = $allClinics->map(function ($clinic) use ($serviceCounts, $doctorCounts, $appointmentCounts) {
                $clinicImage = KIVI_CARE_DIR_URI . 'assets/images/demo-img.png';
                if ($clinic->clinicLogo) {
                    $clinicImage = wp_get_attachment_url($clinic->clinicLogo);
                } elseif ($clinic->profileImage) {
                    $clinicImage = wp_get_attachment_url($clinic->profileImage);
                }

                $addressParts = array_filter([
                    $clinic->address,
                    $clinic->city,
                    $clinic->state,
                    $clinic->country
                ]);
                $fullAddress = implode(', ', $addressParts);

                $clinicData = [
                    'id' => $clinic->id,
                    'label' => $clinic->name,
                    'name' => $clinic->name,
                    'email' => $clinic->email,
                    'address' => $fullAddress,
                    'image' => $clinicImage ?: '',
                    'contact_no' => $clinic->telephoneNo,
                    'service_count' => $serviceCounts[$clinic->id] ?? 0,
                    'doctor_count' => $doctorCounts[$clinic->id] ?? 0,
                    'total_appointments' => (int) ($appointmentCounts[$clinic->id] ?? 0),
                    'total_satisfaction' => 0.0,
                ];

                // Allow Pro plugin (and others) to modify clinic data, e.g. add total_satisfaction
                $clinicData = apply_filters('kc_clinic_data', $clinicData, (int) $clinic->id);

                return $clinicData;
            })->toArray();

            $response_data = [
                'clinics' => $clinics,
                'pagination' => null,
            ];

            // Add pagination metadata if request provided
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $totalPages = ceil($totalCount / $perPage);

                $response_data['pagination'] = [
                    'total' => $totalCount,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'has_more' => $page < $totalPages,
                ];
            }

            return $response_data;

        } catch (\Exception $e) {
            return ['clinics' => [], 'pagination' => null];
        }
    }

    /**
     * Get doctors with all details for frontend booking form
     */
    private function getDoctorsWithAllDetails(?WP_REST_Request $request = null): array
    {
        try {
            $clinicId = $request ? $request->get_param('clinic_id') : null;
            $doctorId = $request ? $request->get_param('doctor_id') : null;
            $search = $request ? $request->get_param('search') : null;
            $serviceId = $request ? $request->get_param('service_id') : null;

            // Start with basic doctor query for active doctors
            $doctorsQuery = KCDoctor::query()->where('user_status', 0); // 0 means active in WordPress

            // Filter by specific doctor ID if provided
            if (!empty($doctorId)) {
                $doctorsQuery->where('ID', $doctorId);
            }

            // Handle search BEFORE pagination - search across multiple fields
            if (!empty($search)) {
                $doctorsQuery->where(function ($q) use ($search) {
                    $q->where('display_name', 'LIKE', '%' . $search . '%')
                        ->orWhere('user_email', 'LIKE', '%' . $search . '%');
                });
            }

            if (!empty($serviceId)) {
                $doctorIdsFromService = KCServiceDoctorMapping::query()
                    ->where('service_id', (int) $serviceId)
                    ->select(['doctor_id'])
                    ->get()
                    ->map(fn($row) => $row->doctorId)
                    ->unique()
                    ->toArray();

                if (!empty($doctorIdsFromService)) {
                    $doctorsQuery->whereIn('ID', $doctorIdsFromService);
                } else {
                    return ['doctors' => []];
                }
            }

            if (!empty($clinicId)) {
                $doctorIds = KCDoctorClinicMapping::query()
                    ->where('clinic_id', $clinicId)
                    ->select(['doctor_id'])
                    ->get()
                    ->map(fn($row) => $row->doctorId)
                    ->toArray();

                if (!empty($doctorIds)) {
                    $doctorsQuery->whereIn('ID', $doctorIds);
                } else {
                    return ['doctors' => []];
                }
            }

            // Get total count before pagination
            $totalCount = $doctorsQuery->count();

            // Handle pagination
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $offset = ($page - 1) * $perPage;

                if ($page !== -1) {
                    $doctorsQuery->limit($perPage)->offset($offset);
                }
            }

            $ratings = collect([]);
            $reviewCounts = [];
            if (isKiviCareProActive() && class_exists(\KCProApp\models\KCPPatientReview::class)) {
                $all_reviews = \KCProApp\models\KCPPatientReview::query()->select(['doctor_id', 'review'])->get();
                $valid_reviews = $all_reviews->filter(function ($review) {
                    return !empty($review->doctorId) && $review->doctorId > 0;
                });

                if ($valid_reviews->isNotEmpty()) {
                    $reviews_by_doctor = $valid_reviews->groupBy('doctorId');
                    $reviews_by_doctor->each(function ($reviews, $docId) use (&$ratings, &$reviewCounts) {
                        $ratings->put($docId, $reviews->avg('review'));
                        $reviewCounts[$docId] = $reviews->count();
                    });
                }
            }

            // Telemed Filter
            $zoomPrefix = defined('KIVICARE_TELEMED_PREFIX') ? KIVICARE_TELEMED_PREFIX : 'kiviCare_';
            $zoomGlobal = maybe_unserialize(\App\models\KCOption::get('zoom_telemed_setting'));
            $isZoomOn = isKiviCareTelemedActive() &&
                (get_option($zoomPrefix . 'zoom_telemed_server_to_server_oauth_status') === 'Yes' || (!empty($zoomGlobal['enableCal']) && $zoomGlobal['enableCal'] === 'Yes'));

            $gmeetGlobal = \App\models\KCOption::get('google_meet_setting');
            $isGMeetOn = isKiviCareGoogleMeetActive() && is_array($gmeetGlobal) &&
                (filter_var($gmeetGlobal['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN) || ($gmeetGlobal['auth_type'] ?? '') === 'oauth' || in_array($gmeetGlobal['enableCal'] ?? '', ['Yes', 'on']));

            $doctorsCollection = $doctorsQuery->get();

            $serviceCounts = [];
            if ($doctorsCollection->isNotEmpty()) {
                $doctorIds = $doctorsCollection->pluck('id')->toArray();
                $serviceCounts = KCServiceDoctorMapping::query()
                    ->whereIn('doctor_id', $doctorIds)
                    ->where('status', 1)
                    ->select(['doctor_id', 'service_id'])
                    ->get()
                    ->groupBy('doctorId')
                    ->map(function ($services) {
                        return $services->pluck('serviceId')->unique()->count();
                    })
                    ->toArray();
            }

            $doctors = $doctorsCollection->map(function ($doctor) use ($ratings, $serviceCounts, $reviewCounts, $isZoomOn, $isGMeetOn) {
                // Get doctor profile image
                $doctorImage = KIVI_CARE_DIR_URI . 'assets/images/demo-img.png';
                $profileImageId = $doctor->getMeta('doctor_profile_image', true);
                if ($profileImageId) {
                    $doctorImage = wp_get_attachment_url($profileImageId);
                }

                // Get basic data from user meta
                $basicData = $doctor->getMeta('basic_data', true);
                $basicData = $basicData ? json_decode($basicData, true) : [];

                // Get specialties and qualifications
                $specialties = $basicData['specialties'] ?? [];

                // Ensure specialties have IDs if they only have labels
                if (!empty($specialties) && is_array($specialties)) {
                    foreach ($specialties as $key => $spec) {
                        // Check if ID is missing or invalid but label exists
                        if (empty($spec['id']) && empty($spec['value']) && !empty($spec['label'])) {
                            // Find the record using first()
                            $foundRecord = KCStaticData::query()
                                ->where('type', 'specialization')
                                ->where('label', $spec['label'])
                                ->first();

                            // If a record was found, get its ID
                            $found_id = $foundRecord ? $foundRecord->id : null;

                            if ($found_id) {
                                $specialties[$key]['id'] = (int) $found_id;
                                $specialties[$key]['value'] = (int) $found_id;
                            }
                        } else {
                            // Ensure existing IDs are integers for consistent JSON output
                            if (isset($spec['id']))
                                $specialties[$key]['id'] = (int) $spec['id'];
                            if (isset($spec['value']))
                                $specialties[$key]['value'] = (int) $spec['value'];
                        }
                    }
                }

                $qualifications = $basicData['qualifications'] ?? [];

                // Build complete address
                $addressParts = array_filter([
                    $basicData['address'] ?? '',
                    $basicData['city'] ?? '',
                    $basicData['country'] ?? ''
                ]);
                $fullAddress = implode(', ', $addressParts);

                $doctorData = [
                    'id' => $doctor->id,
                    'label' => $doctor->display_name,
                    'name' => $doctor->display_name,
                    'firstName' => $doctor->getMeta('first_name', true),
                    'lastName' => $doctor->getMeta('last_name', true),
                    'email' => $doctor->email,
                    'contactNumber' => $basicData['mobile_number'] ?? '',
                    'address' => $fullAddress,
                    'specialties' => $specialties,
                    'qualifications' => $qualifications,
                    'experience' => $basicData['no_of_experience'] ?? '',
                    'image' => $doctorImage ?: '',
                    'gender' => $basicData['gender'] ?? '',
                    'doctor_description' => $doctor->getMeta('doctor_description', true) ?: '',
                    'rating' => round($ratings->get($doctor->id, 0), 1),
                    'service_count' => $serviceCounts[$doctor->id] ?? 0,
                    'review_count' => $reviewCounts[$doctor->id] ?? 0,
                    'is_telemed_connected' => ($isZoomOn && (get_user_meta($doctor->id, 'kiviCare_zoom_telemed_connect', true) === 'on' ||
                        ((json_decode(get_user_meta($doctor->id, 'zoom_server_to_server_oauth_config_data', true), true)['enableServerToServerOauthconfig'] ?? '') == 'true'))) ||
                        ($isGMeetOn && (get_user_meta($doctor->id, 'kiviCare_google_meet_connect', true) === 'on')),
                ];

                return apply_filters(
                    'kivicare_static_doctor_details',
                    $doctorData,
                    $doctor,
                    [
                        'ratings' => $ratings,
                        'service_counts' => $serviceCounts,
                        'review_counts' => $reviewCounts,
                    ]
                );
            })->toArray();

            $response_data = [
                'doctors' => $doctors,
            ];

            // Add pagination metadata if request provided
            if ($request instanceof WP_REST_Request) {
                $page = $request->get_param('page') ?: 1;
                $perPage = $request->get_param('per_page') ?: 10;
                $totalPages = ceil($totalCount / $perPage);

                $response_data['pagination'] = [
                    'total' => $totalCount,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'has_more' => $page < $totalPages,
                ];
            }

            return $response_data;
        } catch (\Exception $e) {
            return ['doctors' => []];
        }
    }

    /**
     * Add a new specialty to the static data
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function addSpecialty(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Log request details

            $label = $request->get_param('label');
            $type = $request->get_param('type') ?: 'specialization';

            if (empty($label)) {
                return $this->response([
                    'status' => false,
                    'message' => __('Label is required', 'kivicare-clinic-management-system'),
                ], 400);
            }

            // Check if this specialty already exists
            $existing = KCStaticData::query()
                ->where('type', $type)
                ->where('label', $label)
                ->first();

            if ($existing) {
                KCErrorLogger::instance()->error('Specialty already exists: ' . json_encode($existing));
                // Return the existing specialty
                return $this->response([
                    'status' => true,
                    'message' => __('Specialty already exists', 'kivicare-clinic-management-system'),
                    'data' => $existing,
                ]);
            }

            // Create the new specialty with all required fields
            $data = [
                'type' => $type,
                'label' => $label,
                'value' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $label)), // Create a slug for the value
                'status' => 1, // Active
                'created_at' => current_time('mysql')
            ];

            $specialty = KCStaticData::create($data);

            if (!$specialty) {
                KCErrorLogger::instance()->error('Failed to create specialty, returning error response');
                return $this->response([
                    'status' => false,
                    'message' => __('Failed to create specialty', 'kivicare-clinic-management-system'),
                ], 500);
            }
            $data = KCStaticData::find($specialty);

            return $this->response([
                'status' => true,
                'message' => __('Specialty added successfully', 'kivicare-clinic-management-system'),
                'data' => ['id' => $data->id, 'label' => $data->label, 'value' => $data->value],
            ]);
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Exception in addSpecialty: ' . $e->getMessage());
            KCErrorLogger::instance()->error('Exception trace: ' . $e->getTraceAsString());

            return $this->response([
                'status' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}
