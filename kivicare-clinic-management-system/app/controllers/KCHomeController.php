<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCAppointment;
use App\models\KCBill;
use App\models\KCServiceDoctorMapping;
use App\models\KCStaticData;
use App\models\KCUser;
use App\models\KCPatientEncounter;
use App\models\KCDoctorClinicMapping;
use App\models\KCReceptionistClinicMapping;
use App\models\KCPatientClinicMapping;
use App\models\KCClinicSchedule;
use App\models\KCClinicSession;
use App\models\KCAppointmentServiceMapping;
use App\models\KCCustomFieldData;
use App\models\KCClinic;
use App\models\KCTax;
use App\models\KCEncounterTemplateMappingModel;
use stdClass;
use WP_User;

class KCHomeController extends KCBase
{

    /**
     * @var KCRequest
     */
    public $db;

    private $request;

    public function __construct()
    {
        global $wpdb;

        $this->db = $wpdb;

        $this->request = new KCRequest();

        parent::__construct();
    }

    public function logout()
    {
        wp_logout();
	    wp_send_json([
            'status' => true,
            'message' => esc_html__('Logout successful.', 'kc-lang'),
        ]);
    }

    public function getUser()
    {

        if (!function_exists('get_plugins')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $currentLoginUserRole = $this->getLoginUserRole();
        $proPluginActive = isKiviCareProActive();
        $telemedZoomPluginActive = isKiviCareTelemedActive();
        $telemedGooglemeetActive = isKiviCareGoogleMeetActive();
        $razorpayPluginActive = isKiviCareRazorpayActive();
        $stripepayPluginActive = isKiviCareStripepayActive();
        $apiPluginActive = isKiviCareAPIActive();
        $bodyChartPluginActive = isKiviCareBodyChartActive();
        $webhooksAddonPluginActive = isKiviCareWebhooksAddonActive();
        $user = new \stdClass();
        $user_clinic_id = '';
        $prefix = KIVI_CARE_PREFIX;
        $config_options = kc_get_multiple_option("
            '{$prefix}admin_lang',
            '{$this->getSetupSteps()}',
            '{$prefix}site_logo',
            '{$prefix}enocunter_modules',
            '{$prefix}prescription_module',
            '{$prefix}theme_color',
            '{$prefix}theme_mode',
            '{$prefix}patient_cal_setting',
            '{$prefix}google_cal_setting',
            '{$prefix}google_meet_setting',
            'setup_step_1',
            '{$prefix}fullcalendar_setting'
        ");

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $userObj = new WP_User($user_id);
            $get_user_language = get_user_meta($user_id, 'defualt_lang');
            $user = $userObj->data;
            unset($user->user_pass);
            $get_admin_language = !empty($config_options[KIVI_CARE_PREFIX . 'admin_lang']) ? $config_options[KIVI_CARE_PREFIX . 'admin_lang'] : 'en';
            if (current_user_can('administrator')) {
                $user->get_lang = $get_admin_language;
            } else {
                $user->get_lang = isset($get_user_language[0]) ? $get_user_language[0] : $get_admin_language;
            }

            $user->permissions = $userObj->allcaps;
            $user->roles = array_values($userObj->roles);

            $image_attachment_id = '';
            $kivicare_user = false;
            switch ($this->getLoginUserRole()) {
                case $this->getReceptionistRole():
                    $image_attachment_id = get_user_meta($user_id, 'receptionist_profile_image', true);
                    $user_clinic_id = kcGetClinicIdOfReceptionist();
                    $kivicare_user = true;
                    break;
                case $this->getDoctorRole():
                    $image_attachment_id = get_user_meta($user_id, 'doctor_profile_image', true);
                    $user_clinic_id = kcGetClinicIdOfDoctor();
                    $kivicare_user = true;
                    break;
                case $this->getPatientRole():
                    $image_attachment_id = get_user_meta($user_id, 'patient_profile_image', true);
                    $kivicare_user = true;
                    break;
                case $this->getClinicAdminRole():
                    $image_attachment_id = get_user_meta($user_id, 'clinic_admin_profile_image', true);
                    $user_clinic_id = kcGetClinicIdOfClinicAdmin();
                    $kivicare_user = true;
                    break;
                case 'administrator':
                    $kivicare_user = true;
                    break;
                default:
                    # code...
                    break;
            }

            if (!$kivicare_user) {
	            wp_send_json(kcUnauthorizeAccessResponse(403));
            }
            $user->profile_photo = !empty($image_attachment_id) ? wp_get_attachment_url($image_attachment_id) : '';
            $setup_step_count = !empty($config_options[$this->getSetupSteps()]) ? $config_options[$this->getSetupSteps()] : '';
            $steps = [];
            for ($i = 0; $i < $setup_step_count; $i++) {
                $get_setup_options = get_option('setup_step_' . ($i + 1));
                if ($get_setup_options) {
                    $steps[$i] = json_decode($get_setup_options);
                }
            }
            $user->steps = $steps;
            $user->module = kcGetModules();
            $user->step_config = kcGetStepConfig();
            $user->start_of_week = kcGetStartOfWeek();

            $user->default_clinic_id = kcGetDefaultClinicId();
            $user->unquie_id_status = (bool)kcPatientUniqueIdEnable('status');
            $user->unquie_id_value = generatePatientUniqueIdRegister();

            if ($proPluginActive) {
                $enableEncounter = !empty($config_options[KIVI_CARE_PREFIX . 'enocunter_modules']) ? json_decode($config_options[KIVI_CARE_PREFIX . 'enocunter_modules']) : [];
                $enablePrescription = !empty($config_options[KIVI_CARE_PREFIX . 'prescription_module']) ? json_decode($config_options[KIVI_CARE_PREFIX . 'prescription_module']) : [];
                $user->encounter_enable_module = isset($enableEncounter->encounter_module_config) ? $enableEncounter->encounter_module_config : 0;
                $user->prescription_module_config = isset($enablePrescription->prescription_module_config) ? $enablePrescription->prescription_module_config : 0;
                $user->encounter_enable_count = $this->getEnableEncounterModule($enableEncounter);
                $user->theme_color = !empty($config_options[KIVI_CARE_PREFIX . 'theme_color']) ? $config_options[KIVI_CARE_PREFIX . 'theme_color'] : '#4874dc';
                $user->theme_mode =!empty($config_options[KIVI_CARE_PREFIX . 'theme_mode']) ? $config_options[KIVI_CARE_PREFIX . 'theme_mode'] : '';
                $user->site_logo  = !empty($config_options[KIVI_CARE_PREFIX . 'site_logo']) ? wp_get_attachment_url($config_options[KIVI_CARE_PREFIX . 'site_logo']) : -1;
                $user->pro_version = getKiviCareProVersion();
                $user->is_patient_enable = !empty($config_options[KIVI_CARE_PREFIX . 'patient_cal_setting']) &&  in_array((string)$config_options[KIVI_CARE_PREFIX . 'patient_cal_setting'], ['1', 'true']) ? 'on' : 'off';
                $get_googlecal_config = !empty($config_options[KIVI_CARE_PREFIX . 'google_cal_setting']) ? $config_options[KIVI_CARE_PREFIX . 'google_cal_setting'] : '';
                $get_googlecal_config = unserialize($get_googlecal_config);
                $user->is_enable_google_cal = !empty($get_googlecal_config['enableCal']) && in_array((string)$get_googlecal_config['enableCal'], ['1', 'true']) ? 'on' : 'off';
                $user->google_client_id = !empty($get_googlecal_config['client_id']) ? trim($get_googlecal_config['client_id']) : 0;
                $user->google_app_name = !empty($get_googlecal_config['app_name']) ? trim($get_googlecal_config['app_name']) : 0;

                if ($currentLoginUserRole == $this->getDoctorRole() || $currentLoginUserRole == $this->getReceptionistRole()) {
                    $user->is_enable_doctor_gcal = get_user_meta($user_id, KIVI_CARE_PREFIX . 'google_cal_connect', true) == 'on' ? 'on' : 'off';
                }
            }
            $user->is_enable_googleMeet = 'off';
            if ($telemedGooglemeetActive) {
                if ($currentLoginUserRole == $this->getDoctorRole()) {
                    $user->is_enable_doctor_gmeet = get_user_meta($user_id, KIVI_CARE_PREFIX . 'google_meet_connect', true) == 'on' ? 'on' : 'off';
                    $user->telemed_service_id = '';
                    $user->doctor_telemed_price = '';
                }
                $googleMeet = !empty($config_options[KIVI_CARE_PREFIX . 'google_meet_setting']) ? $config_options[KIVI_CARE_PREFIX . 'google_meet_setting'] : [];
                $googleMeet = !empty($googleMeet) ? unserialize($googleMeet) : [];
                $user->is_enable_googleMeet = !empty($googleMeet['enableCal']) && in_array((string)$googleMeet['enableCal'], ['1', 'true', 'Yes']) ? 'on' : 'off';
                $user->googlemeet_client_id = !empty($googleMeet['client_id']) ? trim($googleMeet['client_id']) : 0;
                $user->googlemeet_app_name = !empty($googleMeet['app_name']) ? trim($googleMeet['app_name']) : 0;
            }

            $zoomWarningStatus = $telemedStatus = true;
            if ($telemedZoomPluginActive) {
                if ($currentLoginUserRole === $this->getDoctorRole()) {
                    $zoomWarningStatus = $telemedStatus = false;
                    $zoomConfigData = apply_filters('kct_get_zoom_configuration', [
                        'user_id' => $user->ID,
                    ]);
                    if (isset($zoomConfigData['data']) && !empty($zoomConfigData['data'])) {
                        
                        $zoomConfig = $zoomConfigData['data'];
                        $telemedStatus = !empty($zoomConfig->enableTeleMed) && strval($zoomConfig->enableTeleMed) == 'true';
                        $zoomWarningStatus = !empty($zoomConfig->api_key) || !empty($zoomConfig->api_secret);
                    }
                    $user->is_enable_doctor_zoom_telemed = get_user_meta($user_id, KIVI_CARE_PREFIX . 'zoom_telemed_connect', true) == 'on' ? 'on' : 'off';
                    $user->is_zoom_config_enabled = get_option(KIVI_CARE_PREFIX . 'zoom_telemed_setting')['enableCal'] == 'Yes' ? 'on' :  'off';
                    $user->is_zoom_server_to_server_oauth_enabled = get_option( KIVI_CARE_PREFIX . 'zoom_telemed_server_to_server_oauth_status');

                    $user->is_enable_doctor_zoom_server_to_server_config = false;

                    $doctor_zoom_server_to_server_oauth_config = get_user_meta( $user_id, 'zoom_server_to_server_oauth_config_data', true );
                   
                    if ( !empty($doctor_zoom_server_to_server_oauth_config) ) {
                        $doctor_zoom_server_to_server_oauth_config = json_decode( $doctor_zoom_server_to_server_oauth_config );
                        $user->is_enable_doctor_zoom_server_to_server_config = $doctor_zoom_server_to_server_oauth_config->enableServerToServerOauthconfig;
                    }
                    

                    if($user->is_enable_doctor_zoom_telemed == 'on'){
                        $zoomWarningStatus =  true;
                    }
                }

            }
            $user->enableTeleMed = !$zoomWarningStatus;
            $user->telemedConfigOn = $currentLoginUserRole === $this->getDoctorRole() ? kcDoctorTelemedServiceEnable($user->ID) : false;
            $user->teleMedStatus = $telemedStatus;
            $user->teleMedWarning = !$zoomWarningStatus;

            $user_data = get_user_meta($user->ID, 'basic_data', true);
            $user->timeSlot = '';
            $user->basicData = [];

            if (!empty($user_data)) {
                $user_data = json_decode($user_data);
                $user->timeSlot = isset($user_data->time_slot) ? $user_data->time_slot : "";
                $user->basicData = $user_data;
            }
        }

        $user->appointmentMultiFile = kcAppointmentMultiFileUploadEnable();
        $user->woocommercePayment = kcWoocommercePaymentGatewayEnable();
        $user->addOns = [
            'telemed' => $telemedZoomPluginActive,
            'kiviPro' => $proPluginActive,
            'googlemeet' => $telemedGooglemeetActive,
            'razorpay' => $razorpayPluginActive,
            'stripepay' => $stripepayPluginActive,
            "api" =>  $apiPluginActive,
            'bodyChart' => $bodyChartPluginActive,
            'webhooks' => $webhooksAddonPluginActive,
        ];
        $option_data = [];
        if (!empty($config_options['setup_step_1'])) {
            $option_data = json_decode($config_options['setup_step_1'], true);
        }
        $user->default_clinic = !empty($option_data['id'][0]) ? $option_data['id'][0] : '';
        $user->all_payment_method = kcAllPaymentMethodList();
        $user->fullcalendar_key = !empty($config_options[KIVI_CARE_PREFIX . 'fullcalendar_setting']) ? $config_options[KIVI_CARE_PREFIX . 'fullcalendar_setting'] : '';
        $user->doctor_rating_by_patient = $proPluginActive;
        $user->doctor_available = $user->doctor_service_available = $user->doctor_session_available = true;
        if ($currentLoginUserRole === 'administrator') {
            $user->doctor_available = count(get_users(['role' => $this->getDoctorRole(), 'fields' => ['ID']])) > 0;
            $user->doctor_service_available = $this->db->get_var("SELECT COUNT(*) FROM {$this->db->prefix}kc_service_doctor_mapping") > 0;
            $user->doctor_session_available = $this->db->get_var("SELECT COUNT(*) FROM {$this->db->prefix}kc_clinic_sessions") > 0;
        }
        $user->head_external_toolbar = [];
        $user->user_clinic_id = $user_clinic_id;
        $user->clinic_currency_detail = kcGetClinicCurrenyPrefixAndPostfix();
        $user->dashboard_sidebar_data = kcDashboardSidebarArray([$this->getLoginUserRole()]);
        if (has_filter('kivicare_head_external_toolbar')) {
            $user->head_external_toolbar = apply_filters('kivicare_head_external_toolbar', []);
            $user->head_external_toolbar = !empty($user->head_external_toolbar) && is_array($user->head_external_toolbar) ? $user->head_external_toolbar : [];
        }

        $user = apply_filters('kivicare_user_data', $user);


        $plugin_avilable_language = wp_get_installed_translations('plugins');
        
        $kc_lang_array = [['lang'=> 'en_US','label'=> 'English (United States)']];
        if(isset($plugin_avilable_language['kc-lang'])){
            require_once ABSPATH . 'wp-admin/includes/translation-install.php';
            $wp_avilable_language= wp_get_available_translations();
            foreach ($plugin_avilable_language['kc-lang'] as $key => $value) {
                if($key =='en_US' ) continue;
                array_push($kc_lang_array,['label' => $wp_avilable_language[$key]['native_name'],'lang' =>  $key]);
            }
        } 
        $user->kc_available_translations = $kc_lang_array;



	    wp_send_json([
            'status' => true,
            'message' => esc_html__('User data', 'kc-lang'),
            'data' => $user
        ]);
    }

    public function changePassword()
    {

        $request_data = $this->request->getInputs();

        $current_user = wp_get_current_user();

        $result = wp_check_password($request_data['currentPassword'], $current_user->user_pass, $current_user->ID);

        if ($result) {
            if ( !empty($current_user->ID) ) {
                wp_set_password($request_data['newPassword'], $current_user->ID);
                $status = true;
                $message = __('Password successfully changed', 'kc-lang');
                wp_logout();
            } else {
                $status = false;
                $message = __('Password change failed.', 'kc-lang');
            }
        } else {
            $status = false;
            $message = __('Current password is wrong!!', 'kc-lang');
        }

	    wp_send_json([
            'status'  => $status,
            'data' => $result,
            'message' => $message,
        ]);
    }

    public function getDashboard()
    {
        $clinicCurrency = kcGetClinicCurrenyPrefixAndPostfix();
        $clinic_prefix = !empty($clinicCurrency['prefix']) ? $clinicCurrency['prefix'] : '';
        $clinic_postfix = !empty($clinicCurrency['postfix']) ? $clinicCurrency['postfix'] : '';

        if (isKiviCareProActive()) {

            $response = apply_filters('kcpro_get_doctor_dashboard_detail', [
                'user_id' => get_current_user_id(),
                'clinic_prefix' => $clinic_prefix,
                'clinic_postfix' => $clinic_postfix
            ]);
            $response['data']['is_email_working'] = get_option(KIVI_CARE_PREFIX . 'is_email_working');
            wp_send_json($response);
        } else {
            if ($this->getLoginUserRole() == $this->getDoctorRole()) {
                $doctor_id = get_current_user_id();

                $todayAppointments = $appointments = [];
                if (kcCheckPermission('dashboard_total_today_appointment') || kcCheckPermission('dashboard_total_appointment')) {
                    $appointments = collect((new KCAppointment())->get_by(['doctor_id' => $doctor_id]));
                    if (kcCheckPermission('dashboard_total_today_appointment')) {
                        $today = date("Y-m-d");
                        $todayAppointments = $appointments->where('appointment_start_date', $today);
                    }
                    if (!kcCheckPermission('dashboard_total_appointment')) {
                        $appointments = [];
                    }
                }

                $patient_count = 0;
                if (kcCheckPermission('dashboard_total_patient')) {
                    $patient_count_id   = kcDoctorPatientList();
                    $patient_count_id   = implode(',', $patient_count_id); // Convert array to a comma-separated string without quotes
                    if(!empty($patient_count_id)){
                        $patient_count      = $this->db->get_var("SELECT count(*) FROM {$this->db->prefix}users WHERE `user_status` = 0 AND `ID` IN ($patient_count_id)");
                    }
                }
                $service_count = 0;
                if (kcCheckPermission('dashboard_total_service')) {
                    $service_table = $this->db->prefix . 'kc_service_doctor_mapping';
                    $service_name_table = $this->db->prefix . 'kc_services';
                    if (kcDoctorTelemedServiceEnable($doctor_id)) {
                        $service = "SELECT  count(*) FROM {$service_table} WHERE `doctor_id` = {$doctor_id}";
                    } else {
                        $service = "SELECT  count(*) FROM {$service_table} join {$service_name_table} on {$service_name_table}.id= {$service_table}.service_id  WHERE {$service_table}.doctor_id = {$doctor_id} AND ( {$service_table}.telemed_service != 'yes' || {$service_table}.telemed_service IS NULL) AND {$service_table}.status = 1 ";
                    }
                    $service_count = $this->db->get_var($service);
                }
                $data = [
                    'patient_count' => $patient_count,
                    'appointment_count' => count($appointments),
                    'today_count' => count($todayAppointments),
                    'service' => $service_count,
                ];

	            wp_send_json([
                    'data' => $data,
                    'status' => true,
                    'message' => esc_html__('doctor dashboard', 'kc-lang')
                ]);
            }

            $patients = [];
            if (kcCheckPermission('dashboard_total_patient')) {
                $patients = get_users([
                    'role' => $this->getPatientRole(),
                    'fields' => ['ID'],
                    'user_status' => 0
                ]);
            }
            $doctors = [];
            if (kcCheckPermission('dashboard_total_doctor')) {
                $doctors = get_users([
                    'role' => $this->getDoctorRole(),
                    'fields' => ['ID'],
                    'user_status' => 0
                ]);
            }
            $appointment = 0;
            if (kcCheckPermission('dashboard_total_appointment')) {
                $appointment = collect((new KCAppointment())->get_all())->count();
            }


            $bills = 0;
            if (kcCheckPermission('dashboard_total_revenue')) {
                $config = kcGetModules();
                $modules = collect($config->module_config)->where('name', 'billing')->where('status', 1)->count();
                if ($modules > 0) {
                    if (!empty(get_option(KIVI_CARE_PREFIX . 'reset_revenue'))) {
                        $reset_revenue_date = get_option(KIVI_CARE_PREFIX . 'reset_revenue');
                        $bills = collect((new KCBill())->get_all())->where('payment_status', '=', 'paid')->where('created_at', '>', $reset_revenue_date)->sum('actual_amount');
                    } else {
                        $bills = collect((new KCBill())->get_all())->where('payment_status', '=', 'paid')->sum('actual_amount');
                    }
                }
            }


            $change_log = get_option('is_read_change_log');

            $telemed_change_log = get_option('is_telemed_read_change_log');

            $data = [
                'patient_count' => !empty($patients) ? count($patients) : 0,
                'doctor_count' => !empty($doctors) ? count($doctors) : 0,
                'appointment_count' => !empty($appointment) ? $appointment : 0,
                'revenue' => $clinic_prefix . $bills . $clinic_postfix,
                'change_log' => $change_log == 1,
                'telemed_log' => !($telemed_change_log == 1),
                'is_email_working' => get_option(KIVI_CARE_PREFIX . 'is_email_working')
            ];

	        wp_send_json([
                'status' => true,
                'data' => $data,
                'message' => esc_html__('admin dashboard', 'kc-lang'),
            ]);
        }
    }

    public  function getWeeklyAppointment()
    {

        $appointments_table = $this->db->prefix . 'kc_' . 'appointments';
        $request_data = $this->request->getInputs();
        $clinic_condition = ' ';
        $current_user_login = $this->getLoginUserRole();
        if (!(in_array($current_user_login, ['administrator', $this->getClinicAdminRole()]))) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        if ($current_user_login == $this->getClinicAdminRole()) {
            $clinic_condition = " AND clinic_id =" . kcGetClinicIdOfClinicAdmin();
        }

        if (!empty($request_data['filterType']) && $request_data['filterType'] === 'monthly') {
            $monthQuery = "SELECT appointment_start_date AS `x`, COUNT(appointment_start_date) AS `y`  
                        FROM {$appointments_table} WHERE MONTH(appointment_start_date) = MONTH(CURRENT_DATE())
                        AND YEAR(appointment_start_date) = YEAR(CURRENT_DATE())  {$clinic_condition}
                          GROUP BY appointment_start_date ORDER BY appointment_start_date";

            $data = collect($this->db->get_results($monthQuery))->map(function ($v) {
                $v->x = !empty($v->x) ? date(get_option('date_format'), strtotime($v->x)) : $v->x;
                return $v;
            })->toArray();
        } else {
            $sunday = strtotime("last monday");
            $sunday = date('w', $sunday) === date('w') ? $sunday + 7 * 86400 : $sunday;
            $monday = strtotime(date("Y-m-d", $sunday) . " +6 days");

            $week_start = date("Y-m-d", $sunday);
            $week_end = date("Y-m-d", $monday);

            $appointments = "SELECT DAYNAME(appointment_start_date)  AS `x`,
                         COUNT(DAYNAME(appointment_start_date)) AS `y`  
                        FROM {$appointments_table} WHERE appointment_start_date BETWEEN '{$week_start}' AND '{$week_end}' {$clinic_condition}
                          GROUP BY appointment_start_date";


            $arrday = [
                "Monday" => esc_html__("Monday", 'kc-lang'),
                "Tuesday" => esc_html__("Tuesday", 'kc-lang'),
                "Wednesday" => esc_html__("Wednesday", 'kc-lang'),
                "Thursday" => esc_html__("Thursday", 'kc-lang'),
                "Friday" => esc_html__("Friday", 'kc-lang'),
                "Saturday" => esc_html__("Saturday", 'kc-lang'),
                "Sunday" => esc_html__("Sunday", 'kc-lang')
            ];

            $data = collect($this->db->get_results($appointments))->toArray();
            $temp = [];
            $all_value_empty = empty($data);
            $temp = $this->convertWeekDaysToLang($data, $arrday);
            $data = $all_value_empty ? [] : $temp;
        }

        wp_send_json([
	        'status'  => true,
	        'data' => $data,
	        'message' => (!empty($request_data['filterType']) && $request_data['filterType'] === 'monthly' ? esc_html__("Monthly ","kc-lang") : esc_html__("Weekly ","kc-lang")) . esc_html__(' appointment', 'kc-lang'),
        ]);
    }

    public function convertWeekDaysToLang($data, $arrday)
    {
        $newData = array();
        foreach ($data as $object) {
            $newObject = new stdClass();
            $newObject->x = $arrday[$object->x];
            $newObject->y = $object->y;
            $newData[] = $newObject;
        }
        return $newData;
    }

    public function getTest()
    {
	    wp_send_json([
            'status' => true,
            'message' => 'Test'
        ]);
    }

	/**
	 * Resends user credentials and sends an email with a new password.
	 *
	 * @param int|false $id The ID of the user to resend credentials for. If false, it is retrieved from the input data.
	 *
	 * @return array|void The response array or sends a JSON response.
	 */
    public function resendUserCredential($id = false)
    {
	    // Error response template
	    $error_response = [
		    'status'      => false,
		    'status_code' => 403,
		    'message'     => $this->permission_message,
		    'data'        => []
	    ];

	    // Get input data
	    $data = $this->request->getInputs();

	    // Determine the user ID to resend credentials for
	    $resend_id = (int) ($id !== false ? $id : $data['id']);

	    // Retrieve user data
	    $user_data = get_userdata($resend_id);
	    $resend_id_role = $this->getUserRoleById($resend_id);

	    // Check if user data is valid and role is not empty
	    if (!isset($user_data->data) || empty($resend_id_role)) {
		    $response = [
			    'status'  => false,
			    'message' => esc_html__('Requested user not found', 'kc-lang')
		    ];
		    if (!$id) {
			    return $response;
		    }
		    wp_send_json($response);
	    }

	    // Get the login user ID
	    $login_user_id = get_current_user_id();

	    // Check if the resend ID role is allowed
	    if (!in_array($resend_id_role, [
		    $this->getReceptionistRole(),
		    $this->getClinicAdminRole(),
		    $this->getDoctorRole(),
		    $this->getPatientRole()
	    ])) {
		    if ($id !== false) {
			    return $error_response;
		    }
		    wp_send_json($error_response);
	    }

	    // Set the initial permission flag to false
	    $permission = false;

	    if ( $resend_id === $login_user_id ) {
		    $permission = true; // Same ID, grant permission
	    } else {
		    // Switch based on the role of the target user (resend_id)
		    switch ( $resend_id_role ) {
			    case $this->getClinicAdminRole():
				    $permission = kcCheckPermission( 'clinic_add' ) || kcCheckPermission( 'clinic_edit' );
				    break;
			    case $this->getReceptionistRole():
					$permission = (new KCUser())->receptionistPermissionUserWise($resend_id);
				    break;
			    case $this->getDoctorRole():
					$permission = (new KCUser())->doctorPermissionUserWise($resend_id);
				    break;
			    case $this->getPatientRole():
				    $permission = (new KCUser())->patientPermissionUserWise($resend_id);
				    break;
		    }
	    }

	    // Check if permission was granted
	    if ( ! $permission ) {
		    $error_response = [
			    'status'      => false,
			    'status_code' => 403,
			    'message'     => $this->permission_message,
			    'data'        => []
		    ];
		    if ( $id !== false ) {
			    return $error_response;
		    }
		    wp_send_json( $error_response );
	    }

	    // Generate a new password
	    $password = kcGenerateString(12);

		// Prepare email parameters for sending the user credentials
	    $user_email_param = [
		    'id' => $user_data->data->ID,
		    'username' => $user_data->data->user_login,
		    'user_email' => $user_data->data->user_email,
		    'password' => $password,
		    'patient_name' => $user_data->data->display_name,
		    'email_template_type' => 'resend_user_credential',
	    ];

		// Send the email with the new credentials and get the status
	    $status = kcSendEmail($user_email_param);

        // Set the new password for the user only if the email is sent
        if($status == true){
            // Set the new password for the user
	        wp_set_password($password, $user_data->data->ID);
        }

		// Send an SMS if SMS or WhatsApp options are enabled
	    if (kcCheckSmsOptionEnable() || kcCheckWhatsappOptionEnable()) {
		    $sms = apply_filters('kcpro_send_sms', [
			    'type' => 'resend_user_credential',
			    'user_data' => $user_email_param,
		    ]);
	    }

		// Prepare the response
	    $response = [
		    'status' => $status,
		    'data' => [],
		    'message' => $status ? esc_html__('Password Resend Successfully', 'kc-lang') : esc_html__('Password Resend Failed', 'kc-lang')
	    ];

		// Return the response if not in an AJAX request
	    if ($id !== false) {
		    return $response;
	    }

		// Send the response as JSON in an AJAX request
	    wp_send_json($response);

    }

    public function getEnableEncounterModule($data)
    {
        $encounter = collect($data->encounter_module_config);
        $encounter_enable = $encounter->where('status', 1)->count();
        if ($encounter_enable == 1) {
            $class = "12";
        } elseif ($encounter_enable == 2) {
            $class = "6";
        } else {
            $class = "4";
        }
        return $class;
    }

    public function renderShortcode()
    {
        $request_data = $this->request->getInputs();
        $shortcode_params = ' popup="on"';
        if (!empty($request_data['doctor_id'])) {
            $shortcode_params .= " doctor_id={$request_data['doctor_id']} ";
        }
        if (!empty($request_data['clinic_id'])) {
            $shortcode_params .= " clinic_id={$request_data['clinic_id']} ";
        }
	    wp_send_json([
            'status' => true,
            'data'   => do_shortcode("[kivicareBookAppointment {$shortcode_params}]")
        ]);
    }

    public function changeModuleValueStatus()
    {
        $request_data = $this->request->getInputs();
        $rules = [
            'module_type' => 'required',
            'id' => 'required',
            'value' => 'required',
        ];

        $errors = kcValidateRequest($rules, $request_data);
        if (!empty(count($errors))) {
	        wp_send_json([
                'status' => false,
                'message' => $errors[0]
            ]);
        }
        $request_data['value'] = esc_sql($request_data['value']);
        $request_data['id'] = (int)$request_data['id'];
        $current_user_role = $this->getLoginUserRole();
        switch ($request_data['module_type']) {
            case 'static_data':
                if (!(kcCheckPermission('static_data_edit'))) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
                $this->db->update($this->db->prefix . 'kc_static_data', ['status' => $request_data['value']], ['id' => $request_data['id']]);
                break;
            case 'custom_field':
                if (!(kcCheckPermission('custom_field_edit'))) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
                $customFieldTable = $this->db->prefix . 'kc_custom_fields';
                $results = $this->db->get_var("SELECT fields FROM {$customFieldTable} WHERE id={$request_data['id']}");
                if (!empty($results)) {
                    $results = json_decode($results);
                    $results->status = strval($request_data['value']);
                    $this->db->update($customFieldTable, ['status' => $request_data['value'], 'fields' => json_encode($results)], ['id' => $request_data['id']]);
                }
                break;
            case 'doctor_service':
                if (!(kcCheckPermission('service_edit'))) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
				if(!(new KCServiceDoctorMapping())->serviceUserPermission($request_data['id'])){
					wp_send_json(kcUnauthorizeAccessResponse());
				}
                $this->db->update($this->db->prefix . 'kc_service_doctor_mapping', ['status' => $request_data['value']], ['id' => $request_data['id']]);
                break;
            case 'clinics':
                if (!kcCheckPermission('clinic_edit')) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }

                if( $current_user_role === $this->getClinicAdminRole() && $request_data['id'] !== kcGetClinicIdOfClinicAdmin()){
                    wp_send_json(kcUnauthorizeAccessResponse(403));
                }
                $this->db->update($this->db->prefix . 'kc_clinics', ['status' => $request_data['value']], ['id' => $request_data['id']]);
                do_action('kcpro_clinic_update',$request_data['id']);
                break;
            case 'doctors':
                if (!(kcCheckPermission('doctor_edit'))) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
	            if(!(new KCUser())->doctorPermissionUserWise($request_data['id'])){
		            wp_send_json(kcUnauthorizeAccessResponse());
	            }
                $this->db->update($this->db->base_prefix . 'users', ['user_status' => $request_data['value']], ['ID' => $request_data['id']]);
                do_action('kc_doctor_update',$request_data['id']);
                break;
            case 'receptionists':
                if (!(kcCheckPermission('receptionist_edit'))) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
	            if(!(new KCUser())->receptionistPermissionUserWise($request_data['id'])){
		            wp_send_json(kcUnauthorizeAccessResponse());
	            }
                $this->db->update($this->db->base_prefix . 'users', ['user_status' => $request_data['value']], ['ID' => $request_data['id']]);
                do_action( 'kc_receptionist_update', $request_data['id']);
                break;
            case 'patients':
                if (!(kcCheckPermission('patient_edit'))) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
	            if(!(new KCUser())->patientPermissionUserWise($request_data['id'])){
		            wp_send_json(kcUnauthorizeAccessResponse());
	            }
                $this->db->update($this->db->base_prefix . 'users', ['user_status' => $request_data['value']], ['ID' => $request_data['id']]);
                do_action('kc_patient_update',$request_data['id']);
                break;
            case 'tax':
                if (!(kcCheckPermission('tax_edit'))) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
                $this->db->update($this->db->prefix . 'kc_taxes', ['status' => $request_data['value']], ['id' => $request_data['id']]);
                break;
            case 'custom_form':
                if (!(kcCheckPermission('custom_form_edit'))) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
                $this->db->update($this->db->prefix . 'kc_custom_forms', ['status' => $request_data['value']], ['id' => $request_data['id']]);
                break;
            default:
                do_action('kc_change_module_value_status' , $request_data);
                break;
        }
        wp_send_json([
            'status' => true,
            'message' => __("Status Changes Successfully", "kc-lang")
        ]);
    }

    public function saveTermsCondition()
    {
	    if ($this->getLoginUserRole() !== 'administrator') {
		    wp_send_json(kcUnauthorizeAccessResponse());
	    }
        $request_data = $this->request->getInputs();

        delete_option('terms_condition_content');
        delete_option('is_term_condition_visible');

        add_option('terms_condition_content', $request_data['content']);
        add_option('is_term_condition_visible', $request_data['isVisible']);

        wp_send_json([
            'status' => true,
            'message' => esc_html__('Terms & Condition saved successfully', 'kc-lang')
        ]);
    }

    public function getTermsCondition()
    {
	    if ($this->getLoginUserRole() !== 'administrator') {
		    wp_send_json(kcUnauthorizeAccessResponse());
	    }
        $term_condition = get_option('terms_condition_content');
        $term_condition_status = get_option('is_term_condition_visible');
	    wp_send_json([
            'status' => true,
            'data' => array('isVisible' => $term_condition_status, 'content' => $term_condition)
        ]);
    }

    public function getCountryCurrencyList()
    {
        $country_currency_list = kcCountryCurrencyList();
	    wp_send_json([
            'status' => true,
            'data' => $country_currency_list,
            'message' => esc_html__('country list', 'kc-lang')
        ]);
    }

    public function getVersionData()
    {

        $data = array(
            'kivi_pro_version' => kcGetPluginVersion('kiviCare-clinic-&-patient-management-system-pro'),
            'kivi_telemed_version' => kcGetPluginVersion('kiviCare-telemed-addon'),
            'kivi_googlemeet_version' => kcGetPluginVersion('kc-googlemeet'),
        );

	    wp_send_json([
            'status' => true,
            'data' => $data,
            'message' => esc_html__('Terms & Condition saved successfully', 'kc-lang')
        ]);
    }

    public function moduleWiseMultipleDataUpdate()
    {
        $request_data = $this->request->getInputs();       
        $rules = [
            'module' => 'required',
            'data' => 'required',
            'action_perform' => 'required',
        ];

        $errors = kcValidateRequest($rules, $request_data);
        if (!empty(count($errors))) {
	        wp_send_json([
                'status' => false,
                'message' => $errors[0]
            ]);
        }

        $field_pluck = in_array($request_data['module'], ['patient', 'doctors', 'receptionists']) ? 'ID' : 'id';
        $ids = collect($request_data['data']['selectedRows'])->pluck($field_pluck)->map(function ($v) {
            return (int)$v;
        })->toArray();
        switch ($request_data['module']) {
            case 'static_data':
                $static_data_model = ( new KCStaticData());
                $static_data_table = $static_data_model->get_table_name();

                if(empty($request_data['id'])){
                    $response = [
                        'status' => true,
                    ];
                    $implode_ids = !empty( $ids ) ?  implode(",", $ids) : '-1' ;
                    switch ($request_data['action_perform']) {
                        case 'delete':
                            if (!(kcCheckPermission('static_data_delete'))) {
                                wp_send_json(kcUnauthorizeAccessResponse(403));
                            }
                            wp_query_builder()->from( $static_data_table, false  )
                                ->where(['raw' => "id IN ({$implode_ids})",])->delete();
                            $response['message'] = esc_html__("Static data  deleted successfully", "kc-lang");
                            break;
                        case 'active':
                        case 'inactive':
                            if (!(kcCheckPermission('static_data_edit'))) {
                                wp_send_json(kcUnauthorizeAccessResponse(403));
                            }
                            wp_query_builder()->from( $static_data_table, false  )
                                ->set(['status' => $request_data['action_perform'] === 'active' ? 1 : 0 ])
                                ->where(['raw' => "id IN ({$implode_ids})",])->update();
                            $response['message'] = esc_html__("Static data status changed successfully", "kc-lang");
                            break;
                    }
                    wp_send_json($response);
                    break;
                }else{
                    if (!(kcCheckPermission('static_data_edit'))) {
                        wp_send_json(kcUnauthorizeAccessResponse(403));
                    }
                    $static_data_model->update( ['status' => $request_data['value']], ['id' => $request_data['id']] );
                }
                break;
            case 'custom_field':
                if (!(kcCheckPermission('custom_field_edit'))) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
                $customFieldTable = $this->db->prefix . 'kc_custom_fields';
                $results          = $this->db->get_var("SELECT fields FROM {$customFieldTable} WHERE id={$request_data['id']}");
                if (!empty($results)) {
                    $results         = json_decode($results);
                    $results->status = strval($request_data['value']);
                    $this->db->update($customFieldTable, [
                        'status' => $request_data['value'],
                        'fields' => json_encode($results)
                    ], ['id' => $request_data['id']]);
                }
                break;
            case 'doctor_service':
                $service_doctor_mapping = new KCServiceDoctorMapping();
				if (!(kcCheckPermission('service_edit')) ) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
                $this->db->update($this->db->prefix . 'kc_service_doctor_mapping', ['status' => $request_data['value']], ['id' => $request_data['id']]);
                
                foreach ($ids as $id) {
		            if(!(new KCServiceDoctorMapping())->serviceUserPermission($id)){
                        wp_send_json(kcUnauthorizeAccessResponse());
                    }
	            }
                
                $response = [
                    'status' => true,
                    'message' => esc_html__("Service status changed successfully", "kc-lang")
                ];

                foreach ($ids as $id) {
                    switch ($request_data['action_perform']) {
                        case 'delete':
                            $product_id = getProductIdOfServiceForMultipleBtn($id);
                            if(!empty($product_id) && get_post_status( $product_id )){
                                do_action( 'kc_woocoomerce_service_delete', $product_id );
                                wp_delete_post($product_id);
                            }
                            $service_doctor_mapping->delete( [ 'id' => $id ] );
                            $response['message'] = esc_html__("service deleted successfully", "kc-lang");
                            break;
                        case 'active':
                            $this->db->update($this->db->prefix . 'kc_service_doctor_mapping', ['status' => 1], ['ID' => $id]);
                            break;
                        case 'inactive':
                            $this->db->update($this->db->prefix . 'kc_service_doctor_mapping', ['status' => 0], ['ID' => $id]);
                            break;
                    }
                }
                if (isset($response['data']))
                    unset($response['data']);
                wp_send_json($response);
                break;
            case 'clinics':               
                if (!kcCheckPermission('clinic_edit')) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
                $clinic_admin_role = $this->getLoginUserRole() === $this->getClinicAdminRole();
                $admin_clinic_id = 0;
                if( $clinic_admin_role ){
                    $admin_clinic_id = kcGetClinicIdOfClinicAdmin();
                }
                if( $clinic_admin_role && $admin_clinic_id !== (int)$request_data['id']){
                    wp_send_json(kcUnauthorizeAccessResponse(403));
                }
                $this->db->update($this->db->prefix . 'kc_clinics', ['status' => $request_data['value']], ['id' => $request_data['id']]);
                
                $response = [
                    'status' => true,
                    'message' => esc_html__("Clinic status changed successfully", "kc-lang")
                ];

                foreach ($ids as $id) {
                    if($clinic_admin_role && $admin_clinic_id !== (int)$id){
                        wp_send_json(kcUnauthorizeAccessResponse(403));
                    }
                    switch ($request_data['action_perform']) {
                        case 'delete':
                            if (kcGetDefaultClinicId() == $id) {
                                wp_send_json( [
                                    'status'      => true,
                                    'message'     => esc_html__('You can not delete the default clinic.', 'kc-lang' ),
                                ] );
                            }else{
                                do_action( 'kcpro_clinic_delete', $id );
                                (new KCDoctorClinicMapping())->delete([ 'clinic_id' => $id]);
                                (new KCReceptionistClinicMapping())->delete([ 'clinic_id' => $id]);
                                (new KCPatientClinicMapping())->delete([ 'clinic_id' => $id]);
                                $clinic_admin_id = $this->db->get_var("SELECT clinic_admin_id FROM {$this->db->prefix}kc_clinics WHERE id={$id}");
                                if(!empty($clinic_admin_id)){
                                    wp_delete_user($clinic_admin_id);
                                }
                                (new KCClinicSchedule())->delete(['module_id' => $id, 'module_type' => 'clinic']);
                                (new KCClinicSession())->delete(['clinic_id' => $id]);
                                (new KCAppointment())->loopAndDelete(['clinic_id' => $id],false);
                                (new KCPatientEncounter())->loopAndDelete(['clinic_id' => $id],false);
                                (new KCClinic())->delete([ 'id' => $id]);
                            }
                            $response['message'] = esc_html__("Clinic deleted successfully", "kc-lang");                           
                            break;
                        case 'active':
                            $this->db->update($this->db->prefix . 'kc_clinics', ['status' => 1], ['ID' => $id]);
                            do_action( 'kcpro_clinic_update', $id );
                            break;
                        case 'inactive':
                            $this->db->update($this->db->prefix . 'kc_clinics', ['status' => 0], ['ID' => $id]);
                            do_action( 'kcpro_clinic_update', $id );
                            break;
                        case 'resend_credential':
                            $response = $this->resendUserCredential($id);
                            break;
                    }                                  
                }
                if (isset($response['data']))
                    unset($response['data']);
                wp_send_json($response);
                break;
            case 'receptionists':
                if (!(kcCheckPermission('receptionist_edit'))) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
	            foreach ($ids as $id) {
		            if(!(new KCUser())->receptionistPermissionUserWise($id)){
						wp_send_json(kcUnauthorizeAccessResponse());
		            }
	            }
                $response = [
                    'status' => true,
                    'message' => esc_html__("Receptionists status changes successfully", "kc-lang")
                ];

                switch ($request_data['action_perform']) {
                    case 'delete':
                        foreach ($ids as $key => $id) {
                            do_action( 'kc_receptionist_delete', $id);
                            wp_delete_user($id);
                        }
                        $response['message'] = esc_html__("Receptionists deleted successfully", "kc-lang");
                        break;
                    case 'active':
                    case 'inactive':
                        $implode_ids = implode(",", $ids);
                        $new_status = $request_data['action_perform'] === 'active' ? 0 : 1;
                        wp_query_builder()->from('users')->set(['user_status' => $new_status])->where([
                            'raw' => "ID IN ({$implode_ids})",
                        ])->update();
                        foreach ($ids as $id){
                            do_action('kc_receptionist_update',$id);
                        }
                        break;
                    case 'resend_credential':
                        foreach ($ids as $key => $id) {
                            $response = $this->resendUserCredential($id);
                        }
                        break;
                }
                wp_send_json($response);
                break;
            case 'doctors':
                if (!(kcCheckPermission('doctor_edit'))) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
	            foreach ($ids as $id) {
		            if(!(new KCUser())->doctorPermissionUserWise($id)){
			            wp_send_json(kcUnauthorizeAccessResponse());
		            }
	            }
                $response = [
                    'status' => true,
                    'message' => esc_html__("Doctor status changed successfully", "kc-lang")
                ];
                switch ($request_data['action_perform']) {
                    case 'delete':
                        foreach ($ids as $key => $id) {
                            (new KCAppointment())->loopAndDelete(['doctor_id' => $id],false);
                            (new KCPatientEncounter())->loopAndDelete(['doctor_id' => $id],false);
                            do_action('kc_doctor_delete',$id);
                            wp_delete_user($id);
                        }
                        $response['message'] = esc_html__("Doctor deleted successfully", "kc-lang");
                        break;
                    case 'active':
                    case 'inactive':
                        $implode_ids = implode(",", $ids);
                        $new_status = $request_data['action_perform'] === 'active' ? 0 : 1;
                        wp_query_builder()->from('users')->set(['user_status' => $new_status])->where([
                            'raw' => "ID IN ({$implode_ids})",
                        ])->update();
                        foreach ($ids as $id){
                            do_action('kc_doctor_update',$id);
                        }
                        break;
                    case 'resend_credential':
                        foreach ($ids as $key => $id) {
                            $response = $this->resendUserCredential($id);
                        }
                        break;
                }
                wp_send_json($response);
                break;
            case 'patient':
                if (!(kcCheckPermission('patient_edit'))) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
	            foreach ($ids as $id) {
		            if(!(new KCUser())->patientPermissionUserWise($id)){
			            wp_send_json(kcUnauthorizeAccessResponse());
		            }
	            }
                $response = [
                    'status' => true,
                    'message' => esc_html__("Patient status changed successfully", "kc-lang")
                ];
                foreach ($ids as $id) {
                    switch ($request_data['action_perform']) {
                        case 'delete':
                            (new KCAppointment())->loopAndDelete(['patient_id' => $id],false);
                            (new KCPatientEncounter())->loopAndDelete(['patient_id' => $id],false);
                            do_action('kc_patient_delete',$id);
                            wp_delete_user($id);
                            $response['message'] = esc_html__("Patient deleted successfully", "kc-lang");
                            break;
                        case 'active':
                            $this->db->update($this->db->base_prefix . 'users', ['user_status' => 0], ['ID' => $id]);
                            do_action('kc_patient_update',$id);
                            break;
                        case 'inactive':
                            $this->db->update($this->db->base_prefix . 'users', ['user_status' => 1], ['ID' => $id]);
                            do_action('kc_patient_update',$id);
                            break;
                        case 'resend_credential':
                            $response = $this->resendUserCredential($id);
                            break;
                    }
                }
                if (isset($response['data']))
                    unset($response['data']);
                wp_send_json($response);
                break;
            case 'patient_encounter_list':
                $patient_encounter = new KCPatientEncounter();
                if (!(kcCheckPermission('patient_encounter_edit'))) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }

                foreach ($ids as $id) {
                    if(!(new KCPatientEncounter())->encounterPermissionUserWise($id)){
                        wp_send_json(kcUnauthorizeAccessResponse());
                    }
                }

                foreach ($ids as $id) {
                    switch ($request_data['action_perform']) {
                        case 'delete':
                            $patient_encounter->loopAndDelete( [ 'id' => $id ],true );
                            $response = [
                                'status' => true,
                                'message' =>  esc_html__("Patient encounter deleted successfully", "kc-lang")
                            ];
                            break;
                    }
                }
                if (isset($response['data']))
                    unset($response['data']);
                wp_send_json(!empty($response) ? $response : []);

	            break;               
            case 'patient_encounter_template':
                foreach ($ids as $id) {
                    switch ($request_data['action_perform']) {
                        case 'delete':                           
                            do_action('kcpro_delete_multiple_encounter_temp',$id);
                            $response = [
                                'status' => true,
                                'message' =>  esc_html__("Patient encounter template deleted successfully", "kc-lang")
                            ];
                            break;
                    }
                }
                if (isset($response['data']))
                    unset($response['data']);
                wp_send_json($response);
	            break;      

            case 'tax':

                if (!(kcCheckPermission('tax_edit'))) {
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
	            foreach ($ids as $id) {
		            if(!(new KCTax())->checkUserRoleWisePermission($id)){
                        wp_send_json(kcUnauthorizeAccessResponse(403));
                    }
	            }
                $response = [
                    'status' => true,
                    'message' => esc_html__("Tax status changed successfully", "kc-lang")
                ];
                foreach ($ids as $id) {
                    switch ($request_data['action_perform']) {
                        case 'delete':
                            $response = apply_filters('kivicare_tax_delete', [
                                        'status'     => false,
                                        'message' => esc_html__("Tax data not found", "kc-lang"),
                                        'data'       => [],
                                    ] ,['id' => $id]);
                            break;
                        case 'active':
                            $this->db->update($this->db->prefix . 'kc_taxes', ['status' => 1], ['ID' => $id]);
                            break;
                        case 'inactive':
                            $this->db->update($this->db->prefix . 'kc_taxes', ['status' => 0], ['ID' => $id]);
                            break;
                    }
                }
                wp_send_json($response);
                break;          
        }
    }

    public function getCountryCodeSettingsData()
    {
        $country_calling_code = get_option(KIVI_CARE_PREFIX . 'country_calling_code', '');
        $country_code = get_option(KIVI_CARE_PREFIX . 'country_code', '');
        
        wp_send_json([
            'status' => true,
            'data' => [
                'country_code' => $country_code,
                'country_calling_code' => $country_calling_code
            ],
        ]);
    }

    public function getUserRegistrationFormSettingsData()
    {
        $userRegistrationFormSettingData = get_option(KIVI_CARE_PREFIX . 'user_registration_form_setting', 'on');
        
        wp_send_json([
            'status' => true,
            'data' => [
                'userRegistrationFormSettingData' => $userRegistrationFormSettingData
            ],
        ]);
    }
    public function refreshDashboardLocale()  {
        $request_data = $this->request->getInputs();
        do_action('kcpro_refresh_dashboard_locale',$request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare API', 'kc-lang'), 403);
    }
}
