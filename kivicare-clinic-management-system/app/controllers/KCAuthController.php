<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCCustomField;
use Exception;
use WP_User;
use StdClass;

class KCAuthController extends KCBase {


	public $db;

	private $request;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->request = new KCRequest();

        parent::__construct();
	}

    //login patient by vue appointment shortcode and patient dashboard shortcode
    public function patientLogin()
    {

        $parameters = $this->request->getInputs();

        try {

            $errors = kcValidateRequest([
                'username' => 'required',
                'password' => 'required',
            ], $parameters);


            if (count($errors)) {
                wp_send_json(kcThrowExceptionResponse($errors[0], 422));
            }

            $auth_success = wp_authenticate($parameters['username'], $parameters['password']);

            if (is_wp_error($auth_success)) {
                wp_send_json([
                    'status' => false,
                    'message' => $auth_success->get_error_message(),
                ]);
            }

            $user_meta = get_userdata((int) $auth_success->data->ID);

            if ($this->getPatientRole() !== $user_meta->roles[0]) {
                wp_send_json([
                    'status' => false,
                    'message' => esc_html__('User not found user must be a patient.', 'kc-lang'),
                ]);
            }

            // Set WordPress auth
            wp_set_current_user($auth_success->data->ID, $auth_success->data->user_login);
            wp_set_auth_cookie($auth_success->data->ID);
            do_action('wp_login', $auth_success->data->user_login, $auth_success);

            $login_redirect_url = kcGetLogoinRedirectSetting('patient');

            wp_send_json([
                'status' => true,
                'message' => esc_html__('Logged in successfully', 'kc-lang'),
                'data' => $auth_success,
                'token' => [
                    'get' => wp_create_nonce('ajax_get'),
                    'post' => wp_create_nonce('ajax_post'),
                ],
                'login_redirect_url' => apply_filters(
                    'kivicare_login_redirect_url',
                    !empty($login_redirect_url)
                    ? esc_url($login_redirect_url)
                    : esc_url(admin_url('admin.php?page=dashboard')),
                    $auth_success
                ),
            ]);

        } catch (Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();

            header("Status: $code $message");

            wp_send_json([
                'status' => false,
                'message' => $message
            ]);
        }
    }

    //login patient by php appointment shortcode
    public function appointmentPatientLogin() {

        $parameters = $this->request->getInputs();

        try {
            if($parameters['username'] != '' && $parameters['password'] != ''){
                $errors = kcValidateRequest( [
                    'username' => 'required',
                    'password' => 'required',
                ], $parameters );


                if ( count( $errors ) ) {
	                wp_send_json(kcThrowExceptionResponse( $errors[0], 422 ));
                }

                $auth_success = wp_authenticate( $parameters['username'], $parameters['password'] );

	            if ( is_wp_error($auth_success) ) {
		            wp_send_json( [
			            'status'  => false,
			            'message' => $auth_success->get_error_message(),
		            ] );
	            }

                $user_meta = get_userdata($auth_success->data->ID);

                if($this->getPatientRole() !== $user_meta->roles[0] ) {
                    wp_send_json( [
                        'status'  => false,
                        'message' => esc_html__( 'User not found user must be a patient.', 'kc-lang' ),
                    ] );
                }

                wp_set_current_user( $auth_success->data->ID, $auth_success->data->user_login );
                wp_set_auth_cookie( $auth_success->data->ID );
                do_action( 'wp_login', $auth_success->data->user_login, $auth_success );

            }else{
                $auth_success = new StdClass();
                $auth_success->data->ID = get_current_user_id();
            }

            $auth_success->basic_data = get_user_meta($auth_success->data->ID, 'basic_data', true);
            $auth_success->mobile_number = !empty(json_decode($auth_success->basic_data)->mobile_number) ? json_decode($auth_success->basic_data)->mobile_number : '';
            $auth_success->first_name = get_user_meta($auth_success->data->ID, 'first_name', true);
            $auth_success->last_name = get_user_meta($auth_success->data->ID, 'last_name', true);
            $patient_data = get_userdata( $auth_success->data->ID );
            $auth_success->email = !empty($patient_data->user_email) ? $patient_data->user_email : '';
            $auth_success->display_name = !empty($patient_data->display_name) ? $patient_data->display_name : '';
            wp_send_json( [
                'status'  => true,
                'message' => esc_html__( 'Logged in successfully', 'kc-lang' ),
                'data'    => $auth_success,
                'token' => [
	                'get' => wp_create_nonce('ajax_get'),
	                'post' => wp_create_nonce('ajax_post'),
                ]
            ] );

        } catch ( Exception $e ) {

            $code    = $e->getCode();
            $message = $e->getMessage();

            header( "Status: $code $message" );

	        wp_send_json( [
                'status'  => false,
                'message' => $message
            ] );

        }

    }

    //registration patient by appointment shortcode or patient dashboard shortcode
	public function patientRegister() {

		$parameters = $this->request->getInputs();
        $parameters['custom_fields'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($parameters['custom_fields']),true));
        $parameters['clinic'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($parameters['clinic']),true));
        $countrycodedata = json_decode(stripslashes($parameters['country_code']), true);

        $json_country_code = file_get_contents(KIVI_CARE_DIR . 'assets/helper_assets/CountryCodes.json');
        $country_code = json_decode($json_country_code, true);

        if(strpos($countrycodedata['countryCallingCode'], '+') !== false){
            foreach ($country_code as $id => $code) {
                if ($countrycodedata['countryCallingCode'] === $code['dial_code'] && $countrycodedata['countryCode'] === $code['code']) {
                    $countrycodedata['countryCallingCode'] = ltrim($countrycodedata['countryCallingCode'], '+');
                }
            }
        }
        if ($countrycodedata['countryCallingCode'] == null) {
	        wp_send_json([
                'status'  => false,
                'message' => esc_html__("Invalid country code", 'kc-lang')
            ]);
        }

        if(!empty($parameters['widgettype']) && $parameters['widgettype'] === 'new_appointment_widget'){
            $recaptcha = $this->googleRecaptchaVerify($parameters);
            if(empty($recaptcha['status'])){
	            wp_send_json( $recaptcha );
            }
        }

        if(email_exists($parameters['user_email'])) {
	        wp_send_json([
                'status'  => false,
                'message' => esc_html__( "Email already exists. Please use a different email." , 'kc-lang' )
            ]);
        }

        // Remove parentheses
        $parameters['mobile_number'] = str_replace(['(', ')'], '', $parameters['mobile_number']);

        // Remove dashes
        $parameters['mobile_number'] = str_replace('-', '', $parameters['mobile_number']);

        // Remove extra spaces
        $parameters['mobile_number'] = preg_replace('/\s+/', '', $parameters['mobile_number']);

        $user_ids = [];
        $users = get_users();
        $user_meta_data = [];
        foreach ($users as $user) {
            $user_ids[] = $user->ID;
            $country_calling_code = get_user_meta($user->ID, 'country_calling_code', true);
            $basic_data_json = get_user_meta($user->ID, 'basic_data', true);
            $basic_data = !empty($basic_data_json) ? json_decode($basic_data_json, true) : null;
            $mobile_number = isset($basic_data['mobile_number']) ? $basic_data['mobile_number'] : null;
            $country_calling_code = isset($country_calling_code) ? $country_calling_code : null;
            $user_meta_data[] = [
                'user_id' => $user->ID,
                'mobile_number' => $mobile_number,
                'country_calling_code' => $country_calling_code
            ];
        }
        foreach ($user_meta_data as $user_data) {
            if (($user_data['mobile_number'] !== null && $user_data['mobile_number'] === $parameters['mobile_number']) &&
                ($user_data['country_calling_code'] !== null && $user_data['country_calling_code'] === $countrycodedata['countryCallingCode'])) {
                wp_send_json([
                    'status'  => false,
                    'message' => esc_html__( "User already exists with the same mobile number and country calling code.", 'kc-lang' )
                ]);
            }
        }

		try {

            $temp = [
                'mobile_number' => !empty($parameters['mobile_number']) ?   str_replace(' ', '', $parameters['mobile_number'])   : '',
                'dob'           => !empty($parameters['dob']) ? $parameters['dob'] : '',
                'address'       => !empty($parameters['address']) ? $parameters['address'] : '' ,
                'city'          => !empty($parameters['city']) ? $parameters['city'] : '' ,
                'state'         => '',
                'country'       => !empty($parameters['country']) ? $parameters['country'] : '' ,
                'postal_code'   => !empty($parameters['postal_code']) ? $parameters['postal_code'] : '',
                'gender'        => !empty($parameters['gender']) ? $parameters['gender'] : '',
            ];

			$username = kcGenerateUsername($parameters['first_name']);

			$password = kcGenerateString(12);

			$user = wp_create_user( $username, $password, sanitize_email($parameters['user_email'] ) );

			$u               = new WP_User( $user );

			$u->display_name = $parameters['first_name'] . ' ' .$parameters['last_name'];

			wp_insert_user( $u );

			$u->set_role( $this->getPatientRole() );
            update_user_meta($user, 'country_calling_code', $countrycodedata['countryCallingCode']);
            update_user_meta($user, 'country_code', $countrycodedata['countryCode']);

			update_user_meta( $user, 'basic_data', json_encode( $temp ) );

            update_user_meta($user, 'first_name',$parameters['first_name']);
            update_user_meta($user, 'last_name',$parameters['last_name']) ;

            $patient_clinic_map_temp = [
                'patient_id' => $u->ID,
                'created_at' => current_time('Y-m-d H:i:s')
            ];
            if(isKiviCareProActive() && !empty($parameters['clinic'][0]['id'])){
                $patient_clinic_map_temp['clinic_id'] = (int)$parameters['clinic'][0]['id'];
            }else{
                $patient_clinic_map_temp['clinic_id'] = kcGetDefaultClinicId();
            }
            $this->db->insert($this->db->prefix.'kc_patient_clinic_mappings',$patient_clinic_map_temp);
            if(kcPatientUniqueIdEnable('status')){
                update_user_meta( $user, 'patient_unique_id',generatePatientUniqueIdRegister());
            }

            if ( isset($parameters['custom_fields']) ) {
                $parameters['custom_fields'] = $this->customFieldFileUpload($parameters['custom_fields']);
                if(!empty($parameters['custom_fields'])){
                    kvSaveCustomFields('patient_module', $user, $parameters['custom_fields']);
                }
            }

            $auth_success = '';
            if ( $user ) {
                // hook for patient save
                do_action( 'kc_patient_save', $u->ID );

                $auth_success = wp_authenticate( $u->user_email, $password );
                wp_set_current_user( $u->ID, $u->user_login );
                wp_set_auth_cookie(  $u->ID );
                do_action( 'wp_login',$u->user_login, $u );

                $user_email_param = kcCommonNotificationUserData($u->ID,$password);
                kcSendEmail($user_email_param);
                if(kcCheckSmsOptionEnable() || kcCheckWhatsappOptionEnable()){
                    $sms = apply_filters('kcpro_send_sms', [
                        'type' => 'patient_register',
                        'user_data' => $user_email_param,
                    ]);
                }
            }

			if($user) {
				$status = true ;
				$message = esc_html__( "Patient registration successful. Check your email for login credentials." , 'kc-lang' );
			} else {
				$status = false ;
				$message = esc_html__( "Patient registration failed. Please try again." , 'kc-lang' );
			}
            
            $register_redirect_url = kcGetLogoinRedirectSetting('patient');

            wp_send_json([
				'status'  => $status,
				'message' => $message,
                'data'    => $auth_success,
				'token' => [
					'get' => wp_create_nonce('ajax_get'),
					'post' => wp_create_nonce('ajax_post'),
                ],
                'register_redirect_url' => apply_filters(
                    'kivicare_register_redirect_url',
                    !empty($register_redirect_url)
                    ? esc_url($register_redirect_url)
                    : esc_url(admin_url('admin.php?page=dashboard')),
                    $auth_success
                ),
			]);


		} catch ( Exception $e ) {

			$code    = $e->getCode();
			$message =  $e->getMessage();

			header( "Status: $code $message" );

			wp_send_json( [
				'status'  => false,
				'message' => $message
			] );
		}
	}

    public function logout()
    {

        wp_logout();
	    wp_send_json([
            'status' => true,
            'message' => esc_html__('Logout successful.', 'kc-lang'),
        ]);
    }

    //user login and redirect to dashboard by login/register shortcode
    public function loginNewUser() {

        $parameters = $this->request->getInputs();
        try {

            $errors = kcValidateRequest( [
                'username' => 'required',
                'password' => 'required',
            ], $parameters );


            if ( count( $errors ) ) {
	            wp_send_json( [
                    'status'  => false,
                    'message' => $errors[0],
                ] );
            }

            $auth_success = wp_authenticate( $parameters['username'], $parameters['password'] );

	        if ( is_wp_error($auth_success) ) {
		        wp_send_json( [
			        'status'  => false,
			        'message' => $auth_success->get_error_message(),
		        ] );
	        }

            $user_meta = get_userdata((int)$auth_success->data->ID);

            if (!in_array($user_meta->roles[0], [
                $this->getPatientRole(),
                $this->getDoctorRole(),
                $this->getReceptionistRole(),
                $this->getClinicAdminRole()
            ] ) ) {
	            wp_send_json( [
                    'status'  => false,
                    'message' => esc_html__( 'Login user is not kivicare user', 'kc-lang' ),
                ] );
            }

            wp_set_current_user( $auth_success->data->ID, $auth_success->data->user_login );
            wp_set_auth_cookie( $auth_success->data->ID );
            do_action( 'wp_login', $auth_success->data->user_login, $auth_success );

            if ( isset( $user_meta->roles ) && is_array( $user_meta->roles ) ) {
                // check for other user roles...
                if (in_array( $this->getClinicAdminRole(), $user_meta->roles) ) {
                    $login_redirect_url =  kcGetLogoinRedirectSetting('clinic_admin');
                } elseif (in_array( $this->getReceptionistRole(), $user_meta->roles)) {
                    $login_redirect_url =  kcGetLogoinRedirectSetting('receptionist');
                } elseif (in_array( $this->getDoctorRole(), $user_meta->roles)) {
                    $login_redirect_url =  kcGetLogoinRedirectSetting('doctor');
                } elseif (in_array( $this->getPatientRole(), $user_meta->roles)) {
                    $login_redirect_url =  kcGetLogoinRedirectSetting('patient');
                }
            }

            wp_send_json( [
                'status'  => true,
                'message' => esc_html__( 'Logged in successfully', 'kc-lang' ),
                'data'    => $auth_success,
                'login_redirect_url' => apply_filters(
                    'kivicare_login_redirect_url',
                    !empty( $login_redirect_url ) ? esc_url( $login_redirect_url ) : esc_url( admin_url( 'admin.php?page=dashboard' ) ),
                    $auth_success // Pass additional context if needed
                ),
            ] );

        } catch ( Exception $e ) {

            $code    = $e->getCode();
            $message = $e->getMessage();

            header( "Status: $code $message" );

	        wp_send_json( [
                'status'  => false,
                'message' => $message
            ] );

        }

    }

    //user registration by login/register shortcode
    public function registerNewUser(){
        $parameters = $this->request->getInputs();
        $parameters['country_code'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($parameters['country_code']),true));
        $countrycodedata = $parameters['country_code'];
        if(!empty($parameters['custom_fields'])){
            $parameters['custom_fields'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($parameters['custom_fields']),true));
        }

        $json_country_code = file_get_contents(KIVI_CARE_DIR . 'assets/helper_assets/CountryCodes.json');
        $country_code = json_decode($json_country_code, true);

        foreach ($country_code as $id => $code) {
            if ($countrycodedata['countryCallingCode'] === $code['dial_code'] && $countrycodedata['countryCode'] === $code['code']) {
                $sanitize_country_calling_code = ltrim($countrycodedata['countryCallingCode'], '+');
                $sanitize_country_code = $countrycodedata['countryCode'];
            }
        }

        if ($sanitize_country_calling_code == null) {
	        wp_send_json([
                'status'  => false,
                'message' => esc_html__("Invalid country code", 'kc-lang')
            ]);
        }
        $commonConditionField = [
            'first_name' => 'required',
            'last_name' => 'required',
            'user_email' => 'required|email',
            'mobile_number' => 'required',
            'user_role' => 'required',
            'user_clinic' => 'required',
            'country_code' => 'required',
            'gender' => 'required'
        ];

        if(kcGoogleCaptchaData('status') === 'on'){
            $commonConditionField['g-recaptcha-response'] = 'required';
        }

        $data = get_option(KIVI_CARE_PREFIX . 'user_registration_shortcode_role_setting', true);
        $data = !empty($data) && is_array($data) ? $data : [];

        $userList = [];

        if(!empty($data)){
            foreach ($data as $key => $value) {
                if($value === 'on'){
                    if($key === 'kiviCare_patient'){
                        $userList[$key] = __("Patient", "kc-lang");
                    } elseif($key === 'kiviCare_doctor'){
                        $userList[$key] = __('Doctor',"kc-lang");
                    } elseif($key === 'kiviCare_receptionist'){
                        $userList[$key] = __('Receptionist',"kc-lang");
                    }
                }
            }
        }else{
            $userList = [
                'kiviCare_patient' => __("Patient", "kc-lang"),
                'kiviCare_doctor'  => __('Doctor',"kc-lang"),
                'kiviCare_receptionist'  => __('Receptionist',"kc-lang"),
            ];
        }

        if (!array_key_exists($parameters['user_role'], $userList)) {
            wp_send_json([
                'status' => false,
                'message' => esc_html__('Invalid user role selected.', 'kc-lang')
            ]);
        }

        
        $errors = kcValidateRequest($commonConditionField, $parameters );


        if ( count( $errors ) ) {
	        wp_send_json( [
                'status'  => false,
                'message' => $errors[0],
            ] );
        }

        $recaptcha = $this->googleRecaptchaVerify($parameters);
        if(empty($recaptcha['status'])){
	        wp_send_json( $recaptcha );
        }

        if(email_exists($parameters['user_email'])) {
	        wp_send_json([
                'status'  => false,
                'message' => esc_html__( "Email already exists. Please use a different email." , 'kc-lang' )
            ]);
        }

        $redirect = '';
        $emailstatus = false;
        $sms = '';
        $user_table = $this->db->base_prefix.'users';
        $clinic_table = $this->db->base_prefix . 'kc_' . 'clinics';

        $query = "SELECT `id` FROM {$clinic_table} WHERE `status` = '1'";
        $results = $this->db->get_results($query, OBJECT);

        $results = array_map(function($item) {
            return $item->id;
        }, $results);
        
        if(!in_array((int)$parameters['user_clinic'], $results)){
            wp_send_json( [
                'status'  => false,
                'message' => esc_html__( "Clinic id is not proper. Please contact admin." , 'kc-lang' )
            ] );

            return;
        }

        // Remove parentheses
        $parameters['mobile_number'] = str_replace(['(', ')'], '', $parameters['mobile_number']);

        // Remove dashes
        $parameters['mobile_number'] = str_replace('-', '', $parameters['mobile_number']);

        // Remove extra spaces
        $parameters['mobile_number'] = preg_replace('/\s+/', '', $parameters['mobile_number']);

        $user_ids = [];
        $users = get_users();
        $user_meta_data = [];
        foreach ($users as $user) {
            $user_ids[] = $user->ID;
            $country_calling_code = get_user_meta($user->ID, 'country_calling_code', true);
            $basic_data_json = get_user_meta($user->ID, 'basic_data', true);
            $basic_data = !empty($basic_data_json) ? json_decode($basic_data_json, true) : null;
            $mobile_number = isset($basic_data['mobile_number']) ? $basic_data['mobile_number'] : null;
            $country_calling_code = isset($country_calling_code) ? $country_calling_code : null;
            $user_meta_data[] = [
                'user_id' => $user->ID,
                'mobile_number' => $mobile_number,
                'country_calling_code' => "+".$country_calling_code
            ];
        }
        foreach ($user_meta_data as $user_data) {
            if (($user_data['mobile_number'] !== null && $user_data['mobile_number'] === $parameters['mobile_number']) &&
                ($user_data['country_calling_code'] !== null && $user_data['country_calling_code'] === $countrycodedata['countryCallingCode'])) {
                wp_send_json([
                    'status'  => false,
                    'message' => esc_html__( "User already exists with the same mobile number and country calling code.", 'kc-lang' )
                ]);
            }
        }

        try {
            $temp = [ 'mobile_number'  => str_replace(" ","",$parameters['mobile_number']),
            'gender' =>  $parameters['gender']
         ];

            $username = kcGenerateUsername($parameters['first_name']);

            $password = kcGenerateString(12);

            $user = wp_create_user( $username, $password, sanitize_email($parameters['user_email'] ) );

            $u               = new WP_User( $user );

            $u->display_name = $parameters['first_name'] . ' ' .$parameters['last_name'];

            wp_insert_user( $u );
            update_user_meta($user, 'country_calling_code', $sanitize_country_calling_code);
            update_user_meta($user, 'country_code', $sanitize_country_code);
            update_user_meta( $user, 'basic_data', json_encode( $temp ) );
            update_user_meta($user, 'first_name',$parameters['first_name']);
            update_user_meta($user, 'last_name',$parameters['last_name']) ;

            $auth_success = '';
            $user_email_param = [
                'id' => $u->ID,
                'username' => $username,
                'user_email' => $parameters['user_email'],
                'password' => $password,
            ];
            switch ($parameters['user_role']){
                case $this->getPatientRole():
                    if(kcPatientUniqueIdEnable('status')){
                        update_user_meta( $user, 'patient_unique_id',generatePatientUniqueIdRegister());
                    }
                    if (isset($parameters['custom_fields']) ) {
                        $parameters['custom_fields'] = $this->customFieldFileUpload($parameters['custom_fields']);
                        if(!empty($parameters['custom_fields'])){
                            kvSaveCustomFields('patient_module', $user, $parameters['custom_fields']);
                        }
                    }
                    $u->set_role( $this->getPatientRole() );
                    $new_temp = [
                        'patient_id' => $u->ID,
                        'clinic_id' => (int)$parameters['user_clinic'],
                        'created_at' => current_time('Y-m-d H:i:s')
                    ];
                    $this->db->insert($this->db->prefix.'kc_patient_clinic_mappings',$new_temp);
                    $user_email_param['patient_name'] = $parameters['first_name'] . ' ' .$parameters['last_name'];
                    $templateType = 'patient_register';
                    $redirect =  kcGetLogoinRedirectSetting('patient');
                    if(kcGetUserRegistrationShortcodeSetting('patient') !== 'on'){
                        $this->db->update($user_table,['user_status' => 1],['ID' => $u->ID]);
                    }
                    if ( $user && kcGetUserRegistrationShortcodeSetting('patient') === 'on' ) {
                        $this->authenticateAndLogin($u);
                    } else {
                        $redirect = apply_filters('kc_register_redirect_url_for_inactive_user', $redirect, $u->ID, $parameters);
                    }
                    do_action( 'kc_patient_save', $u->ID );
                    break;
                case $this->getDoctorRole():
                    if($this->getLoginUserRole() !== 'administrator' && kcGetUserRegistrationShortcodeSetting('doctor') !== 'on'){
                        update_user_meta((int)$u->ID,'kivicare_user_account_status','no');
                        $this->db->update($user_table,['user_status' => 1],['ID' => (int)$u->ID]);
                    }else{
                        $this->db->update($user_table,['user_status' => 0],['ID' => (int)$u->ID]);
                    }
                    $u->set_role( $this->getDoctorRole());
                    $this->db->insert($this->db->prefix.'kc_doctor_clinic_mappings',[
                        'doctor_id' => (int)$u->ID,
                        'clinic_id' => (int)$parameters['user_clinic'],
                        'owner' => 0,
                        'created_at' => current_time('Y-m-d H:i:s')
                    ]);
                    if (isset($parameters['custom_fields'])) {
                        $parameters['custom_fields'] = $this->customFieldFileUpload($parameters['custom_fields']);
                        if(!empty($parameters['custom_fields'])){
                            kvSaveCustomFields('doctor_module', $user, $parameters['custom_fields']);
                        }
                    }
                    $user_email_param['doctor_name'] = $parameters['first_name'] . ' ' .$parameters['last_name'];
                    $templateType = 'doctor_registration';
                    $redirect =  kcGetLogoinRedirectSetting('doctor');
                    if ( $user && kcGetUserRegistrationShortcodeSetting('doctor') === 'on' ) {
                        $this->authenticateAndLogin($u);
                    } else {
                        $redirect = apply_filters('kc_register_redirect_url_for_inactive_user', $redirect, $u->ID, $parameters);
                    }
                    do_action( 'kc_doctor_save', $u->ID );
                    break;
                case $this->getReceptionistRole():
                    if($this->getLoginUserRole() !== 'administrator' && kcGetUserRegistrationShortcodeSetting('receptionist') !== 'on'){
                        update_user_meta((int)$u->ID,'kivicare_user_account_status','no');
                        $this->db->update($user_table,['user_status' => 1],['ID' => (int)$u->ID]);
                    }else{
                        $this->db->update($user_table,['user_status' => 0],['ID' => (int)$u->ID]);
                    }
                    $u->set_role($this->getReceptionistRole() );
                    $user_email_param['Receptionist_name'] = $parameters['first_name'] . ' ' .$parameters['last_name'];
                    $templateType = 'receptionist_register';
                    $this->db->insert($this->db->prefix.'kc_receptionist_clinic_mappings',[
                        'receptionist_id' => (int)$u->ID,
                        'clinic_id'       => (int)$parameters['user_clinic'],
                        'created_at'      =>   current_datetime('Y-m-d H:i:s' )
                    ]);
                    $redirect =  kcGetLogoinRedirectSetting('receptionist');
                    if ( $user && kcGetUserRegistrationShortcodeSetting('receptionist') === 'on' ) {
                        $this->authenticateAndLogin($u);
                    } else {
                        $redirect = apply_filters('kc_register_redirect_url_for_inactive_user', $redirect, $u->ID, $parameters);
                    }
                    do_action( 'kc_receptionist_save', $u->ID );
                    break;
            }

            if(!empty($templateType) && !empty($user_email_param)){
                $user_email_param['email_template_type'] = $templateType;
                $emailstatus = kcSendEmail($user_email_param);
                if(kcCheckSmsOptionEnable() || kcCheckWhatsappOptionEnable()){
                    $sms = apply_filters('kcpro_send_sms', [
                        'type' => $templateType,
                        'user_data' => $user_email_param,
                    ]);
                }
            }

            $adminemailstatus = false;
            $adminsms = [];

            if($user) {

                if($this->getLoginUserRole() !== 'administrator'){
                    $admin_email_param = [
                        'user_contact' => $parameters['mobile_number'],
                        'user_role' => $parameters['user_role'],
                        'username' => $parameters['first_name'] . ' ' .$parameters['last_name'],
                        'user_email' => $parameters['user_email'],
                        'email_template_type' => 'admin_new_user_register'
                    ];
                    $adminemailstatus = kcSendEmail($admin_email_param);
                    if(kcCheckSmsOptionEnable() || kcCheckWhatsappOptionEnable()){
                        $adminsms = apply_filters('kcpro_send_sms', [
                            'type' => 'admin_new_user_register',
                            'user_data' => $admin_email_param,
                        ]);
                    }
                }
                $status = true ;
                $message = esc_html__( "User registration successfully. Check your email for login credentials." , 'kc-lang' );
            } else {
                $status = false ;
                $message = esc_html__( "User registration not success." , 'kc-lang' );
            }


	        wp_send_json([
                'status'  => $status,
                'message' => $message,
                'data'    => $auth_success,
                'redirect' => $redirect,
                'notification' => [
                    'sms' => $sms,
                    'email' => $emailstatus
                ],
                'adminNotification' =>[
                    'sms' => $adminsms,
                    'email' => $adminemailstatus
                ]
            ]);

        } catch ( Exception $e ) {

            $code    = $e->getCode();
            $message = $e->getMessage();

            header( "Status: $code $message" );

	        wp_send_json( [
                'status'  => false,
                'message' => $message
            ] );

        }
    }

    //verify doctor and receptionist by admin and send notification to verify user
    public function verifyUser(){
		if($this->getLoginUserRole() !== 'administrator'){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
        $parameters = $this->request->getInputs();

        if(empty($parameters['data']['ID'])){
	        wp_send_json( [
                'status'  => false,
                'message' => __('User id not found','kc-lang')
            ] );
        }

        $id = (int)$parameters['data']['ID'];
        update_user_meta($id,'kivicare_user_account_status','yes');
        $this->db->update($this->db->base_prefix.'users',['user_status' => 0],['ID' => $id]);
        $user_role = $this->getUserRoleById($id);
        switch ($user_role){
            case $this->getDoctorRole();
                do_action('kc_doctor_update', $id);
                break;
            case $this->getReceptionistRole();
                do_action('kc_receptionist_update', $id);
                break;
            case $this->getPatientRole();
                do_action('kc_patient_update', $id);
                break;
        }

        $user_email_param = [
            'user_contact' => $parameters['data']['mobile_number'],
            'user_email' => $parameters['data']['user_email'],
            'email_template_type' => 'user_verified'
        ];
        $sms = [];
        $emailstatus = kcSendEmail($user_email_param);
        if(kcCheckSmsOptionEnable() || kcCheckWhatsappOptionEnable()){
            $sms = apply_filters('kcpro_send_sms', [
                'type' => 'user_verified',
                'user_data' => $user_email_param,
            ]);
        }
	    wp_send_json( [
            'status'  => true,
            'message' => __('User Verified Successfully','kc-lang'),
            'notification' => [
                'sms' => $sms,
                "email" => $emailstatus
            ]
        ] );
    }

    public function googleRecaptchaVerify($parameters){
        if(kcGoogleCaptchaData('status') === 'on'){
            $recaptchaSecret   = kcGoogleCaptchaData('secret_key');
            $response = file_get_contents(
                "https://www.google.com/recaptcha/api/siteverify?secret=" . $recaptchaSecret . "&response=" . $parameters['g-recaptcha-response'] . "&remoteip=" . $_SERVER['REMOTE_ADDR']
            );
            $response = json_decode($response);
            if ($response->success === false || (!empty($response->score) && $response->score < 0.5)) {
                return [
                    'status'  => false,
                    'message' => __('Invalid Google recaptcha Value'),
                    'data' => $response
                ];
            }
        }

        return [
            'status'  => true,
        ];
    }

    public function customFieldFileUpload($custom_field_parameter){
        $custom_field_model = new KCCustomField();
        $_temp_files = $_FILES;
        foreach ($_temp_files as $key => $val){
            if(str_contains($key,'custom_field_')){
                if((int)$val['size'] > wp_max_upload_size()){
                    unset($custom_field_parameter[$key]);
                    continue;
                }
                $custom_field_id = (int)str_replace('custom_field_','',$key);
                $custom_field_data = $custom_field_model->get_var(['id' => $custom_field_id],'fields');
                if(empty($custom_field_data)){
                    unset($custom_field_parameter[$key]);
                    continue;
                }
                $custom_field_data = json_decode($custom_field_data,true);
                $supported_file_type = collect($custom_field_data['file_upload_type'])->pluck('id')->toArray();
                if(!in_array($val['type'],$supported_file_type)){
                    unset($custom_field_parameter[$key]);
                    continue;
                }
                $_FILES = array($key => $val);
                $attachment_id = media_handle_upload($key, 0);
                if(is_wp_error($attachment_id)){
                    unset($custom_field_parameter[$key]);
                    continue;
                }
                $custom_field_parameter[$key] = [
                    'id' => $attachment_id,
                    'url' => wp_get_attachment_url($attachment_id),
                    'name' => get_the_title($attachment_id)
                ];
            }
        }
        return $custom_field_parameter;
    }

    public function authenticateAndLogin($user) {
        if (!is_user_logged_in()) {
            do_action('kc_before_user_authenticate');
            $auth_success = wp_authenticate($user->user_email, $user->user_pass);
            wp_set_current_user($user->ID, $user->user_login);
            wp_set_auth_cookie($user->ID);
            do_action('wp_login', $user->user_login, $user);
        }
    }
}
