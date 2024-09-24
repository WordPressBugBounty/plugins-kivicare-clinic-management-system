<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;

class KCGeneralSettingController extends KCBase
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

		if($this->getLoginUserRole() !== 'administrator'){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
    }

    public function saveRequestHelperStatus() {

        $request_data = $this->request->getInputs();
        $status = false;
        $data = '';

        if(isset($request_data['request_status'])) {
            update_option(KIVI_CARE_PREFIX.'request_helper_status',strval($request_data['request_status']));
            $status = true;
            $data = $request_data['request_status'];
        }

	    wp_send_json([
            'status'  => $status,
            'data' => $data
        ]);
    }


    public function getRequestHelperStatus() {

        $request_help = get_option(KIVI_CARE_PREFIX.'request_helper_status',true);

        $request_help = gettype($request_help)  != 'boolean' && $request_help == 'on' ? 'on' : 'off';

	    wp_send_json([
            'status'  => true,
            'data' => $request_help
        ] );
    }

    public function saveGoogleRecaptchaSetting(){
        $request_data = $this->request->getInputs();

        update_option(KIVI_CARE_PREFIX . 'google_recaptcha', [
                'site_key' => $request_data['site_key'],
                'secret_key' => $request_data['secret_key'],
                'status' => $request_data['status']
            ]
        );

	    wp_send_json( [
            'status'  => true,
            'message' => __("Google Recaptcha Setting Saved Successfully","kc-lang"),
        ] );
    }

    public function saveUserRegistrationFormSetting(){
        $request_data = $this->request->getInputs();

        update_option(KIVI_CARE_PREFIX . 'user_registration_form_setting', $request_data['status']);

	    wp_send_json( [
            'status'  => true,
            'message' => __("User Registration Form Setting Saved Successfully","kc-lang"),
        ] );
    }

    public function saveFullcalendarSetting(){

        $request_data = $this->request->getInputs();

        update_option(KIVI_CARE_PREFIX . 'fullcalendar_setting', !empty($request_data['fullcalendar_key']) ? $request_data['fullcalendar_key'] : '');

	    wp_send_json( [
            'status'  => true,
            'message' => __("Fullcalendar Setting Saved","kc-lang"),
        ] );

    }

    public function saveUserRegistrationShortcodeSetting(){

        $request_data = $this->request->getInputs();
       
        if($request_data['data']['user_role']['kiviCare_patient'] === 'off' && $request_data['data']['user_role']['kiviCare_doctor'] === 'off' && $request_data['data']['user_role']['kiviCare_receptionist'] === 'off'){
            wp_send_json( [
                'status'  => false,
                'message' => esc_html__("Atleast One user role should by enable","kc-lang")
            ] );
        }
        
        $rules = [
            'doctor'     => 'required',
            'receptionist' => 'required',
            'patient'        => 'required',
        ];

        $errors = kcValidateRequest( $rules, $request_data['data']['status'] );

        if ( count( $errors ) ) {
	        wp_send_json( [
                'status'  => false,
                'message' => $errors[0]
            ] );
        }

        $user_role_rules = [
            'kiviCare_doctor'       => 'required',
            'kiviCare_receptionist' => 'required',
            'kiviCare_patient'      => 'required',
        ];

        $user_role_errors = kcValidateRequest( $user_role_rules, $request_data['data']['user_role'] );

        if ( count( $user_role_errors ) ) {
	        wp_send_json( [
                'status'  => false,
                'message' => $user_role_errors[0]
            ] );
        }

        update_option(KIVI_CARE_PREFIX . 'user_registration_shortcode_setting', $request_data['data']['status']);
        update_option(KIVI_CARE_PREFIX . 'user_registration_shortcode_role_setting', $request_data['data']['user_role']);
        
	    wp_send_json( [
            'status'  => true,
            'message' => __("User Registration Shortcode Setting Saved","kc-lang"),
        ] );

    }

    public function saveLogoutRedirectSetting(){

        $request_data = $this->request->getInputs();

        $rules = [
            'clinic_admin'    => 'required',
            'doctor'     => 'required',
            'receptionist' => 'required',
            'patient'        => 'required',
        ];

        $errors = kcValidateRequest( $rules, $request_data['data'] );

        if ( count( $errors ) ) {
	        wp_send_json( [
                'status'  => false,
                'message' => $errors[0]
            ] );
        }

        update_option(KIVI_CARE_PREFIX.'logout_redirect',$request_data['data']);

	    wp_send_json( [
            'status'  => true,
            'message' => __("Logout Redirect Setting Saved","kc-lang"),
        ] );
    }

    public function saveLoginRedirectSetting(){

        $request_data = $this->request->getInputs();

        $rules = [
            'clinic_admin'    => 'required',
            'doctor'     => 'required',
            'receptionist' => 'required',
            'patient'        => 'required',
        ];

        $errors = kcValidateRequest( $rules, $request_data['data'] );

        if ( count( $errors ) ) {
	        wp_send_json( [
                'status'  => false,
                'message' => $errors[0]
            ] );
        }

        update_option(KIVI_CARE_PREFIX.'login_redirect',$request_data['data']);

	    wp_send_json( [
            'status'  => true,
            'message' => __("Login Redirect Setting Saved","kc-lang"),
        ] );
    }

    public function saveClinicCurrency(){
        $request_data = $this->request->getInputs();
        $status = false;
        $message = esc_html__("Failed To update Currency Setting","kc-lang");
        if(!empty($request_data['clinic_data'])){
            update_option(KIVI_CARE_PREFIX.'clinic_currency','on');
            $currencyData = $request_data['clinic_data'];
            $currency = [
                'currency_prefix' => (!empty($currencyData['currency_prefix']) && $currencyData['currency_prefix'] !== 'null') ? $currencyData['currency_prefix'] : '',
                'currency_postfix' => (!empty($currencyData['currency_postfix']) && $currencyData['currency_postfix'] !== 'null') ? $currencyData['currency_postfix'] : '',
                // 'decimal_point' => (!empty($currencyData['decimal_point'])) ? $currencyData['decimal_point'] : array( 'id'=> '2', 'label'=>'100.00')
            ];
            $this->db->query("UPDATE {$this->db->prefix}kc_clinics SET extra='".json_encode($currency, JSON_UNESCAPED_UNICODE)."'");
            $status = true;
            $message = esc_html__("Currency Setting Saved","kc-lang");
        }
	    wp_send_json(['status' => $status,"message" => $message]);
    }

    public function saveCountryCode()
    {
        $request_data = $this->request->getInputs();
        $status = false;
        $message = esc_html__("Failed To update Country Code Setting", "kc-lang");
        if (!empty($request_data['CountryCode'])) {
            update_option(KIVI_CARE_PREFIX . 'country_code', $request_data['CountryCode']['countrycode']);
            update_option(KIVI_CARE_PREFIX . 'country_calling_code', $request_data['CountryCode']['countryCallingCode']);
            $status = true;
            $message = esc_html__("Country Code Setting Saved", "kc-lang");
        }
	    wp_send_json(['status' => $status, "message" => $message]);
    }

    // public function saveDateFormat(){
    //     $request_data = $this->request->getInputs();
    //     $status = false;
    //     $message = esc_html__("Failed To update date format Setting","kc-lang");
    //     if(!empty($request_data['data'])){
    //         update_option(KIVI_CARE_PREFIX.'date_format',$request_data['data']);
    //         $message = esc_html__("Date Format Setting Saved","kc-lang");
    //         $status = true;
    //     }
	//     wp_send_json(['status' => $status,"message" => $message]);
    // }
    public function getAllGeneralSettingsData() {

        $currencyDetail = kcGetClinicCurrenyPrefixAndPostfix();
        $fullCalendarSetting = get_option(KIVI_CARE_PREFIX . 'fullcalendar_setting',true);
        $country_calling_code = get_option(KIVI_CARE_PREFIX . 'country_calling_code', '');
        $country_code = get_option(KIVI_CARE_PREFIX . 'country_code', '');
        $userRegistrationFormSetting = get_option(KIVI_CARE_PREFIX . 'user_registration_form_setting', 'on');
        $encounter_edit_after_close_status = get_option(KIVI_CARE_PREFIX . 'encounter_edit_after_close_status', 'off');
	    wp_send_json([
            'status' => true,
            'data' => [
                "currency_prefix" =>$currencyDetail['prefix'],
                "currency_postfix" => $currencyDetail['postfix'],
            ],
            'captcha_data' => [
                'site_key' => kcGoogleCaptchaData('site_key'),
                'secret_key' =>  kcGoogleCaptchaData('secret_key'),
                'status' =>   kcGoogleCaptchaData('status')
            ],
            'logout_redirect' => kcGetLogoutRedirectSetting('all'),
            'login_redirect' => kcGetLogoinRedirectSetting('all'),
            'fullcalendar' => gettype($fullCalendarSetting) !== 'boolean' && !empty($fullCalendarSetting) ? $fullCalendarSetting : '',
            'date_format' => kcGetDateFormat(),
            'userRegistrationShortcodeSetting' => [
                'status' => [
                    'patient' => kcGetUserRegistrationShortcodeSetting('patient'),
                    'doctor' => kcGetUserRegistrationShortcodeSetting('doctor'),
                    'receptionist' => kcGetUserRegistrationShortcodeSetting('receptionist')
                ],
                'user_role' => [
                    'kiviCare_patient' => kcGetUserRegistrationShortcodeSetting('kiviCare_patient'),
                    'kiviCare_doctor' => kcGetUserRegistrationShortcodeSetting('kiviCare_doctor'),
                    'kiviCare_receptionist' => kcGetUserRegistrationShortcodeSetting('kiviCare_receptionist'),
                ]
            ],
            'countryCodeData' => [
                'countrycode' => $country_code,
                'countryCallingCode' => $country_calling_code
            ],
            'userRegistrationFormSetting' => $userRegistrationFormSetting,
            'encounter_settings' => [
                'encounter_edit_after_close_status' => $encounter_edit_after_close_status
            ],
        ]);
    }

    public function saveEncounterSetting()
    {
        $request_data = $this->request->getInputs();

        update_option(KIVI_CARE_PREFIX . 'encounter_edit_after_close_status', $request_data['encounter_edit_after_close_status']);
    
        wp_send_json( [
            'status'  => true,
            'message' => __("Encounter Setting Saved Successfully","kc-lang"),
        ] );
    }

    public function KCResetPluginData() {
        $status = false;
        $message = esc_html__("Failed To reset plugin data","kc-lang");
        $request_data = $this->request->getInputs();
        $reset_all = false;

        if(isset($request_data['reset_plugin_data']['resetDoctorStatus']) && $request_data['reset_plugin_data']['resetDoctorStatus'] === 'on'){

            global $wpdb;
            // kivicare table names
            $doctor_tables = array('kc_clinic_sessions', 'kc_appointment_reminder_mapping', 'kc_appointment_service_mapping', 'kc_bills', 'kc_bill_items', 'kc_medical_history', 'kc_medical_problems', 'kc_patient_encounters', 'kc_payments_appointment_mappings', 'kc_prescription', 'kc_patient_review', 'kc_service_doctor_mapping', 'kc_appointment_zoom_mappings', 'kc_appointment_google_meet_mappings', 'kc_appointments', 'kc_doctor_clinic_mappings');

            $prefix = $wpdb->prefix;

            foreach ($doctor_tables as $table) {
                $table_name = $prefix . $table;
                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                    $wpdb->query("TRUNCATE TABLE $table_name");
                }
            }

            $doctors = collect(get_users(['role' => $this->getDoctorRole(),'fields' => ['ID']]))->pluck('ID')->toArray();
            foreach($doctors as $doctor_id){
                $this->db->delete( $this->db->prefix.'kc_custom_fields_data', array( 'module_id' => $doctor_id ) );
                $this->db->delete( $this->db->prefix.'kc_custom_fields', array( 'module_id' => $doctor_id ) );
                wp_delete_user( $doctor_id);
            }
            $this->deleteDoctorWoocommerceServiceProduct();
            $this->deleteDoctorWoocommerceAppointmentOrder();

            $status = true;
            $message = esc_html__("Data deleted successfully","kc-lang");
        }

        if(isset($request_data['reset_plugin_data']['resetPatientStatus']) && $request_data['reset_plugin_data']['resetPatientStatus'] === 'on'){

            global $wpdb;
            // kivicare table names
            $patient_tables = array('kc_appointments', 'kc_appointment_reminder_mapping', 'kc_appointment_service_mapping', 'kc_bills', 'kc_bill_items', 'kc_medical_history', 'kc_medical_problems', 'kc_patient_encounters', 'kc_payments_appointment_mappings', 'kc_prescription', 'kc_patient_review', 'kc_patient_clinic_mappings');

            $prefix = $wpdb->prefix;

            foreach ($patient_tables as $table) {
                $table_name = $prefix . $table;
                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                    $wpdb->query("TRUNCATE TABLE $table_name");
                }
            }

            $patients = collect(get_users(['role' => $this->getPatientRole(),'fields'=> ['ID']]))->pluck('ID')->toArray();
            foreach($patients as $patient_id){
                wp_delete_user( $patient_id);
            }
            $this->deleteDoctorWoocommerceAppointmentOrder();

            $status = true;
            $message = esc_html__("Data deleted successfully","kc-lang");
        }

        if(isset($request_data['reset_plugin_data']['resetReceptionistStatus']) && $request_data['reset_plugin_data']['resetReceptionistStatus'] === 'on'){

            $this->db->get_results("TRUNCATE TABLE {$this->db->prefix}kc_receptionist_clinic_mappings");
            
            $receptionists = collect(get_users(['role' => $this->getReceptionistRole(),'fields'=> ['ID']]))->pluck('ID')->toArray();
            foreach($receptionists as $receptionist_id){
                wp_delete_user( $receptionist_id);
            }
            
            $status = true;
            $message = esc_html__("Data deleted successfully","kc-lang");
        }

        if(isset($request_data['reset_plugin_data']['resetAppointmentEncounterStatus']) && $request_data['reset_plugin_data']['resetAppointmentEncounterStatus'] === 'on'){

            global $wpdb;
            // kivicare table names
            $encounter_tables = array('kc_appointments', 'kc_appointment_reminder_mapping', 'kc_appointment_service_mapping', 'kc_bills', 'kc_bill_items',  'kc_gcal_appointment_mapping', 'kc_medical_history', 'kc_medical_problems', 'kc_patient_encounters', 'kc_payments_appointment_mappings', 'kc_prescription', 'kc_appointment_google_meet_mappings', 'kc_appointment_zoom_mappings');

            $prefix = $wpdb->prefix;

            foreach ($encounter_tables as $table) {
                $table_name = $prefix . $table;
                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                    $wpdb->query("TRUNCATE TABLE $table_name");
                }
            }

            $this->db->delete( $this->db->prefix.'kc_custom_fields', array( 'module_type' => 'appointment_module' ) );
            $this->db->delete( $this->db->prefix.'kc_custom_fields_data', array( 'module_type' => 'appointment_module' ) );
            $this->deleteDoctorWoocommerceAppointmentOrder();
            $status = true;
            $message = esc_html__("Data deleted successfully","kc-lang");
        }

        if(isset($request_data['reset_plugin_data']['resetRevenueStatus']) && $request_data['reset_plugin_data']['resetRevenueStatus'] === 'on'){
            $status = update_option(KIVI_CARE_PREFIX.'reset_revenue', current_time("Y-m-d H:i:s"));

            if($status){
                $message = esc_html__("Data deleted successfully","kc-lang");
            }
        }

        if(isset($request_data['reset_plugin_data']['resetAllDataStatus']) && $request_data['reset_plugin_data']['resetAllDataStatus'] === 'on'){

            global $wpdb;
            // kivicare table names
            $all_tables = array('kc_appointments', 'kc_appointment_reminder_mapping', 'kc_appointment_service_mapping', 'kc_bills', 'kc_bill_items', 'kc_clinics', 'kc_clinic_schedule', 'kc_clinic_sessions', 'kc_custom_fields_data', 'kc_doctor_clinic_mappings', 'kc_gcal_appointment_mapping', 'kc_medical_history', 'kc_medical_problems', 'kc_patient_clinic_mappings', 'kc_patient_encounters', 'kc_patient_medical_report', 'kc_patient_review', 'kc_payments_appointment_mappings', 'kc_prescription', 'kc_receptionist_clinic_mappings', 'kc_appointment_google_meet_mappings', 'kc_service_doctor_mapping', 'kc_appointment_zoom_mappings', 'kc_static_data', 'kc_taxes', 'kc_tax_data');

            $prefix = $wpdb->prefix;

            foreach ($all_tables as $table) {
                $table_name = $prefix . $table;
                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                    $wpdb->query("TRUNCATE TABLE $table_name");
                }
            }
            
            $kivi_patients = collect(get_users(['role' => $this->getPatientRole(),'fields'=> ['ID']]))->pluck('ID')->toArray();
            $kivi_clinic_admin = collect(get_users(['role' => $this->getClinicAdminRole(),'fields'=> ['ID']]))->pluck('ID')->toArray();
            $kivi_receptionist = collect(get_users(['role' => $this->getReceptionistRole(),'fields'=> ['ID']]))->pluck('ID')->toArray();
            $kivi_doctor = collect(get_users(['role' => $this->getDoctorRole(),'fields'=> ['ID']]))->pluck('ID')->toArray();
            
            $kivi_users = array_merge($kivi_patients, $kivi_clinic_admin, $kivi_receptionist, $kivi_doctor);
            foreach($kivi_users as $kivi_user){
                wp_delete_user( $kivi_user);
            }
            
            delete_option('is_read_change_log');
            delete_option('is_telemed_read_change_log');
            delete_option('clinic_setup_wizard');
            delete_option('kivicare_session_first_time');
            delete_option('is_kivicarepro_upgrade_lang');
            delete_option('is_lang_version_2.3.7');
            delete_option('kivicare_version_2_3_0');
            delete_option('is_upgrade_2.1.5');
            delete_option('is_lang_version_2');
            delete_option('terms_condition_content');
            delete_option('is_term_condition_visible');
            delete_option('kiviCare_setup_steps');
            delete_option('setup_step_1');
            delete_option('setup_step_2');
            delete_option('setup_step_3');
            delete_option('setup_step_4');
            delete_option('common_setting');
            
            wp_clear_scheduled_hook("kivicare_patient_appointment_reminder");
            
            $this->db->query( "DELETE FROM {$this->db->options} WHERE `option_name` LIKE '%kiviCare%'" );

            $custom_type = ['kivicare_sms_tmp','kivicare_mail_tmp','kivicare_gcal_tmp','kivicare_gmeet_tmp'];
            foreach ($custom_type as $type){
                $allposts= get_posts( array('post_type'=> $type ,'numberposts'=>-1) );
                foreach ($allposts as $eachpost) {
                    wp_delete_post( $eachpost->ID, true );
                }
            }

            $this->deleteDoctorWoocommerceServiceProduct();
            $this->deleteDoctorWoocommerceAppointmentOrder();

            deactivate_plugins( KIVI_CARE_BASE_NAME );

            if(isKiviCareProActive()){
                deactivate_plugins( KIVI_CARE_PRO_BASE_PATH );
            }
            if(isKiviCareTelemedActive()){
                deactivate_plugins( KIVI_CARE_TELEMED_BASE_PATH );
            }
            if(isKiviCareGoogleMeetActive()){
                deactivate_plugins( KIVI_CARE_GOOGLE_BASE_NAME );
            }
            
            $status = true;
            $reset_all = true;
            $message = esc_html__("Data deleted successfully","kc-lang");

        }

	    wp_send_json(['status' => $status, 'reset_all' => $reset_all, "message" => $message]);
    }

    public function deleteDoctorWoocommerceServiceProduct(){
        $query = "SELECT p.ID FROM {$this->db->posts}  AS p  JOIN {$this->db->postmeta} AS pm ON pm.post_id = p.ID AND pm.meta_key = 'kivicare_service_id'";
        $product_id = collect($this->db->get_results($query))->pluck('ID')->toArray();
        if(!empty($product_id)){
            foreach ($product_id as $product){
                wp_delete_post($product,true);
            }
        }
    }

    public function deleteDoctorWoocommerceAppointmentOrder(){
        $query = "SELECT p.ID FROM {$this->db->posts}  AS p  JOIN {$this->db->postmeta} AS pm ON pm.post_id = p.ID AND pm.meta_key = 'kivicare_appointment_id'";
        $order_id = collect($this->db->get_results($query))->pluck('ID')->toArray();
        if(!empty($order_id)){
            foreach ($order_id as $order){
                wp_delete_post($order,true);
            }
        }
    }
    public function saveAppConfig()  {
        $request_data = $this->request->getInputs();
        do_action('kcapi_save_config',$request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare API', 'kc-lang'), 403);
    }
    public function getAppConfig()  {
        if($data= get_option(KIVI_CARE_PREFIX.'onesignal_config')){
            wp_send_json_success($data);
        }

        wp_send_json_error();
    }

}