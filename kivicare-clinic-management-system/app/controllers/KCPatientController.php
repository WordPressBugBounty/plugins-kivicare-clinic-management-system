<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCAppointment;
use App\models\KCAppointmentServiceMapping;
use App\models\KCCustomField;
use App\models\KCCustomFieldData;
use App\models\KCPatientEncounter;
use App\models\KCMedicalHistory;
use App\models\KCMedicalRecords;
use App\models\KCPatientClinicMapping;
use App\models\KCReceptionistClinicMapping;
use App\models\KCDoctorClinicMapping;
use App\models\KCClinic;
use App\models\KCUser;
use Exception;
use WP_User;
use WP_User_Query;

class KCPatientController extends KCBase {

	public $db;

	/**
	 * @var KCRequest
	 */
	private $request;

	public function __construct() {

        parent::__construct();
		global $wpdb;

		$this->db = $wpdb;

		$this->request = new KCRequest();

	}

	public function index() {

        //check current login user permission
		if ( ! kcCheckPermission( 'patient_list' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
        $request_data = $this->request->getInputs();
        $patients = [];
        $patient_clinic_mapping_table = $this->db->prefix . 'kc_patient_clinic_mappings';

        //get user args
		$args['role']           = $this->getPatientRole();
        $args['orderby']        = 'ID';
        $args['order']          = 'DESC';
        $args['fields']          = ['ID','display_name','user_email','user_registered','user_status'];
        if((int)$request_data['perPage'] > 0){
            $args['page'] = (int)$request_data['page'];
            $args['number'] = $request_data['perPage'];
            $args['offset'] = ((int)$request_data['page'] - 1) * (int)$request_data['perPage'];
        }

        $search_condition = '';
        $total = 0;
        $current_user_role = $this->getLoginUserRole();
		if(!empty($request_data['sort'])){
            $request_data['sort'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($request_data['sort'][0]),true));
            if(!empty($request_data['sort']['field']) && !empty($request_data['sort']['type']) && $request_data['sort']['type'] !== 'none'){
                if($request_data['sort']['field'] == 'uid'){
                    if(!kcPatientUniqueIdEnable('status')){
                        $args['orderby']        = 'ID';
                        $args['order']          = esc_sql(strtoupper($request_data['sort']['type']));
                    }
                }else{
                    $args['orderby']        = esc_sql($request_data['sort']['field']);
                    $args['order']          = esc_sql(strtoupper($request_data['sort']['type']));
                }
            }
        }

        //global filter
        if(isset($request_data['searchTerm']) && $request_data['searchTerm'] !== ''){
            $args['search_columns'] = ['user_email','ID','display_name','user_status'];
            $args['search'] = '*'.esc_sql(strtolower(trim($request_data['searchTerm']))).'*' ;
        }else{
            //column wise filter
            if(!empty($request_data['columnFilters'])){
                $request_data['columnFilters'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($request_data['columnFilters']),true));
                if (isset($request_data['columnFilters']['mobile_number']) && !empty($request_data['columnFilters']['mobile_number'])){
                    $mobile_number = $request_data['columnFilters']['mobile_number'];
                    // $search_condition .= " AND WHERE meta_key = 'basic_data' AND json_extract(meta_value, '$.mobile_number') LIKE '%{$mobile_number}%'; ";
                    // unset($request_data['columnFilters']['mobile_number']);
                }
                foreach ($request_data['columnFilters'] as $column => $searchValue){
                    $searchValue = !empty($searchValue) ? $searchValue : '';
                    $searchValue = esc_sql(strtolower(trim($searchValue)));
                    if($column !== 'user_status' && empty($searchValue) ){
                        continue;
                    }
                    $column = $column === 'uid' ? 'ID' : esc_sql($column);
                    if($column === 'ID' && kcPatientUniqueIdEnable('status')){
                        $args['meta_query'] = [
                            [
                                'key' => 'patient_unique_id',
                                'value' => $searchValue,
                                'compare' => 'LIKE'
                            ]
                        ];
                        continue;
                    }else if($column === 'clinic_name' && isKiviCareProActive()){
                        $search_condition .= " AND {$this->db->users}.id IN (SELECT patient_id FROM {$patient_clinic_mapping_table} WHERE clinic_id={$searchValue}) ";
                        continue;
                    }
                    else if($column === 'mobile_number'){
                        $args['meta_query'] =[
                            'relation' => 'OR',
                            [
                                'key' => 'basic_data',
                                'value' => $searchValue,
                                'compare' => 'LIKE'
                            ],
                            [
                                'key' => 'country_calling_code',
                                'value' => $searchValue,
                                'compare' => 'LIKE'
                            ]
                        ];
                       
                        continue;
                    }
                    $search_condition .= " AND {$column} LIKE '%{$searchValue}%' ";

                }
            }
        }


        // check current user is super admin
		if(current_user_can('administrator')){
            $results = $this->getUserData($args,$search_condition);
            //total patient count
            $total = $results['total'];

            // patient list
            $patients = $results['list'];

		}else{
            //get login user role wise patient
            if(isKiviCareProActive()){
                switch ($current_user_role) {
                    case $this->getReceptionistRole():
                        $clinic_id = kcGetClinicIdOfReceptionist();
                        $query = "SELECT DISTINCT `patient_id` FROM {$patient_clinic_mapping_table} WHERE `clinic_id` ={$clinic_id}" ;
                        break;
                    case $this->getClinicAdminRole():
                        $clinic_id =kcGetClinicIdOfClinicAdmin();
                        $query = "SELECT DISTINCT `patient_id` FROM {$patient_clinic_mapping_table} WHERE `clinic_id` ={$clinic_id}";
                        break;
                }
                if(!empty($query)){
                    $result = collect($this->db->get_results($query))->pluck('patient_id')->toArray();
                    $args['include'] = !empty($result) ? $result : [-1];
                    $results = $this->getUserData($args,$search_condition);
                    //total patient count
                    $total = $results['total'];

                    // patient list
                    $patients = $results['list'];
                }
            }else{
                //get all user if pro plugin not active
                if(in_array($current_user_role , [$this->getReceptionistRole(),$this->getClinicAdminRole()]) ){
                    $results = $this->getUserData($args,$search_condition);
                    //total patient count
                    $total = $results['total'];

                    // patient list
                    $patients = $results['list'];

                }
            }

            //get doctor patient (patient who have appointment/encounter with doctor or added by doctor
            if ($this->getDoctorRole() === $current_user_role) {
                $all_user = kcDoctorPatientList();
                $args['include'] = !empty($all_user) ? $all_user : [-1];
                $results = $this->getUserData($args,$search_condition);
                //total patient count
                $total = $results['total'];

                // patient list
                $patients = $results['list'];
            }
		}

		if ( ! count( $patients ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => esc_html__('No patient found', 'kc-lang'),
				'data'    => []
			] );
		}

		$data = [];

        $custom_forms = apply_filters('kivicare_custom_form_list',[],['type' => 'patient_module']);

		foreach ( $patients as $key => $patient ) {
            $patient->ID = (int)$patient->ID;
            $allUserMeta = get_user_meta( $patient->ID);
            //patient unique id
            $data[ $key ]['u_id'] = !empty($allUserMeta['patient_unique_id'][0]) ? $allUserMeta['patient_unique_id'][0] : '-';
            $data[ $key ]['ID']              = $patient->ID;
            $data[ $key ]['profile_image'] = !empty($allUserMeta['patient_profile_image'][0]) ? wp_get_attachment_url($allUserMeta['patient_profile_image'][0]) : '';
            $data[ $key ]['display_name']    = $patient->display_name;
            //patient clinic list if available or empty

             $condition = '';
            if ($clinic_id != $data[$key]['clinic_id']) {
                $condition = "AND clinic.id = {$clinic_id}";
            } else {
                $condition = "GROUP BY patient_clinic.patient_id";
            }
            $patient_clinic = $this->db->get_row("SELECT GROUP_CONCAT(clinic.name) AS name, GROUP_CONCAT(clinic.id) AS id FROM {$this->db->prefix}kc_clinics AS clinic LEFT JOIN 
            {$patient_clinic_mapping_table} AS patient_clinic ON patient_clinic.clinic_id = clinic.id WHERE patient_clinic.patient_id = {$patient->ID} {$condition}");  
            $data[$key]['clinic_name'] = !empty($patient_clinic->name) ? decodeSpecificSymbols($patient_clinic->name) : '';
            $data[$key]['clinic_id'] = !empty($patient_clinic->id) ? explode(',',$patient_clinic->id) : [];
			$data[ $key ]['user_email'] = $patient->user_email;
            $data[$key]['custom_forms'] = $custom_forms;
            //patient country code
            $country_calling_code = !empty($allUserMeta['country_calling_code'][0]) ? '+' . $allUserMeta['country_calling_code'][0] : '';
            //patient other basic data
            $user_meta = !empty($allUserMeta['basic_data'][0]) ? $allUserMeta['basic_data'][0] : false ;
            if (!empty($user_meta)) {
				$basic_data = json_decode( $user_meta,true );
                foreach ( $basic_data as $basic_data_key => $basic_data_value){
                    if ($basic_data_key === 'mobile_number') {
                        $data[$key][$basic_data_key] = $country_calling_code . ' ' . $basic_data_value;
                    } else {
                        $data[$key][$basic_data_key] = $basic_data_value;
                    }
                }
			}
            foreach (['dob','address','city','country','postal_code','gender','blood_group','mobile_number'] as $item){
                if(!array_key_exists($item,$data[ $key ])){
                    $data[ $key ][$item] = '-';
                }
            }
            $data[ $key ]['user_registered'] = kcGetFormatedDate($patient->user_registered);
            $data[ $key ]['user_status']     = $patient->user_status;
            $data[ $key ]['full_address'] = ('#').(!empty($data[ $key ]['address']) && $data[ $key ]['address'] !== '-' ? $data[ $key ]['address'].',' : '' ) .
                (!empty($data[ $key ]['city']) && $data[ $key ]['city'] !== '-' ? $data[ $key ]['city'].',' : '').
                (!empty($data[ $key ]['postal_code']) && $data[ $key ]['postal_code'] !== '-' ? $data[ $key ]['postal_code'].',' : '').
                (!empty($data[ $key ]['country'])  && $data[ $key ]['country'] !== '-' ? $data[ $key ]['country'] : '');
                $data[$key] = apply_filters('kivicare_patient_lists',$data[$key],$patient,$allUserMeta);
		}

		wp_send_json( [
			'status'     => true,
			'message'    => esc_html__('Patient list', 'kc-lang'),
			'data'       => array_values($data),
			'total_rows' => $total
		] );
	}

	public function save() {
		$is_permission = false;

        $request_data = $this->request->getInputs();

        //check current login user permission
		if ( kcCheckPermission( 'patient_add' ) || ( kcCheckPermission( 'patient_profile' )
                && !empty($request_data['ID']) && (new KCUser())->patientPermissionUserWise($request_data['ID'])  )  ) {
			$is_permission = true;
		}

		if ( ! $is_permission ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$rules = [
			'first_name'    => 'required',
			'last_name'     => 'required',
			'user_email'    => 'required|email',
			'mobile_number' => 'required',
			'gender'        => 'required',
            'country_code'  => 'required',
            'country_calling_code' => 'required',
		];

		$errors = kcValidateRequest( $rules, $request_data );

		if ( count( $errors ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => $errors[0]
			] );
		}

        //check patient unique id not use by other patient
        if( kcPatientUniqueIdEnable('status') && $this->getLoginUserRole() !== $this->getPatientRole()){
            if(empty($request_data['u_id']) && $request_data['u_id'] == null ){
	            wp_send_json( [
                    'status'  => false,
                    'message' => esc_html__('Patient Unique ID is required', 'kc-lang')
                ] );
            }
			$condition = '';
			if(isset( $request_data['ID'])){
				$condition = ' and user_id !='.(int)$request_data['ID'];
			}
			$patient_unique_id = $request_data['u_id'];
			$patient_unique_id_exist = $this->db->get_var("SELECT  count(*) FROM  ".$this->db->base_prefix."usermeta WHERE  meta_key = 'patient_unique_id'  AND  meta_value ='".$patient_unique_id."'".$condition);
            if($patient_unique_id_exist > 0 ){
	            wp_send_json( [
					'status' => false,
                    'message' => esc_html__('Patient Unique ID is already used', 'kc-lang')
                ] );
            }
        }


        //check email condition
        $email_condition = kcCheckUserEmailAlreadyUsed($request_data);
        if(empty($email_condition['status'])){
	        wp_send_json($email_condition);
        }

        // Remove parentheses
        $request_data['mobile_number'] = str_replace(['(', ')'], '', $request_data['mobile_number']);

        // Remove dashes
        $request_data['mobile_number'] = str_replace('-', '', $request_data['mobile_number']);

        // Remove extra spaces
        $request_data['mobile_number'] = preg_replace('/\s+/', '', $request_data['mobile_number']);

		$temp = [
			'mobile_number' => str_replace(' ', '', $request_data['mobile_number']) ,
			'gender'        => $request_data['gender'],
			'dob'           => $request_data['dob'],
			'address'       => $request_data['address'],
			'city'          => $request_data['city'],
			'state'         => '',
			'country'       => $request_data['country'],
			'postal_code'   => $request_data['postal_code'],
			'blood_group'   => !empty($request_data['blood_group']) && $request_data['blood_group'] !== 'default' ? $request_data['blood_group'] : '',
		];

        $current_user_role = $this->getLoginUserRole();
        if(isKiviCareProActive()){
            if($current_user_role == $this->getClinicAdminRole()){
                $clinic_id = kcGetClinicIdOfClinicAdmin();
            }elseif ($current_user_role == $this->getReceptionistRole()) {
                $clinic_id = kcGetClinicIdOfReceptionist();
            }else{
                $clinic_id = $request_data['clinic_id'];
                // if(isset($request_data['clinic_id'][0]['id'])){
                //     $clinic_id = (int)$request_data['clinic_id'][0]['id'];
                // }else{
                //     $clinic_id =isset($request_data['clinic_id']['id']) ? (int)$request_data['clinic_id']['id']: kcGetDefaultClinicId();
                // }
            }
        }else{
            //default clinic id if pro not active
            $clinic_id = kcGetDefaultClinicId();
        }
		if ( ! isset( $request_data['ID'] ) ) {

            // create new user
            $password = kcGenerateString( 12 );
			$user            = wp_create_user( kcGenerateUsername( $request_data['first_name'] ), $password, sanitize_email( $request_data['user_email'] ) );
			$u               = new WP_User( $user );
			$u->display_name = $request_data['first_name'] . ' ' . $request_data['last_name'];
			wp_insert_user( $u );
			// add patient role to create user
            $u->set_role( $this->getPatientRole() );

            $this->savePatientClinic($user, $clinic_id);

            // // Insert Patient Clinic mapping...
            // $new_temp = [
            //     'patient_id' => $user,
            //     'clinic_id' => $clinic_id,
            //     'created_at' => current_time('Y-m-d H:i:s')
            // ];
            // $patient_mapping = new KCPatientClinicMapping;
            // $patient_mapping->insert($new_temp);

            //patient added  by
            update_user_meta( $user, 'patient_added_by', get_current_user_id() );

            //get email/sms/whatsapp template dynamic key array
            $user_email_param =  kcCommonNotificationUserData($u->ID,$password);

            //send email after patient save
            kcSendEmail($user_email_param);

            //send sms/whatsapp after patient save
            if(kcCheckSmsOptionEnable() || kcCheckWhatsappOptionEnable()){
                $sms = apply_filters('kcpro_send_sms', [
                    'type' => 'patient_register',
                    'user_data' => $user_email_param,
                ]);
            }
			$message = esc_html__('Patient has been saved successfully', 'kc-lang');
			$user_id = $user ;
		} else {

            $request_data['ID'] = (int)$request_data['ID'];

			if(!(new KCUser())->patientPermissionUserWise($request_data['ID'])){
				wp_send_json(kcUnauthorizeAccessResponse(403));
			}
			wp_update_user(
				array(
					'ID'           => $request_data['ID'],
					'user_email'   => sanitize_email( $request_data['user_email'] ),
					'display_name' => $request_data['first_name'] . ' ' . $request_data['last_name']
				)
			);

// 			$new_temp = [
// 				'patient_id' => $request_data['ID'],
// 				'clinic_id' => $clinic_id,
// 				'created_at' => current_time('Y-m-d H:i:s')
// 			];
// //
            $patient_mapping = new KCPatientClinicMapping;
            $patient_mapping->delete( [ 'patient_id' => $request_data['ID'] ] );
            // $patient_mapping->insert($new_temp);

            $this->savePatientClinic($request_data['ID'], $clinic_id);


			$user_id = $request_data['ID'] ;
			$message = esc_html__('Patient has been updated successfully', 'kc-lang');
		}

        if(!empty($user_id)){

            update_user_meta($user_id, 'first_name',$request_data['first_name'] );
            update_user_meta($user_id, 'last_name', $request_data['last_name'] );
            update_user_meta( $user_id, 'basic_data', json_encode( $temp, JSON_UNESCAPED_UNICODE ));
            update_user_meta( $user_id, 'patient_unique_id',$request_data['u_id']) ;
            update_user_meta($user_id, 'country_calling_code', $request_data['country_calling_code']);
            update_user_meta($user_id, 'country_code', $request_data['country_code']);

            // save/update patient custom field data
            if(!empty($request_data['custom_fields'])) {
                if (isset($request_data['custom_fields']) && $request_data['custom_fields'] !== []) {
                    kvSaveCustomFields('patient_module', $user_id, $request_data['custom_fields']);
                }
            }

            //save patient profile image
            if( isset($request_data['profile_image']) && !empty((int)$request_data['profile_image'])  ){
                update_user_meta( $user_id , 'patient_profile_image',  (int)$request_data['profile_image']  );
            }
        }

		if ( !empty($user->errors)) {
			wp_send_json( [
				'status'  => false,
				'message' => $user->get_error_message() ? $user->get_error_message() : __('Patient save operation has been failed','kc-lang')
			] );
		} else {
            if(!empty($request_data['ID'])){
                do_action( 'kc_patient_update', $user_id );
            }else{
                do_action( 'kc_patient_save', $user_id );
            }
			wp_send_json( [
				'status'  => true,
				'message' => $message,
                'choose_language_updated' => apply_filters('kcpro_update_user_choose_language_updated',false,$request_data)
			] );
		}
	}

	public function edit() {

		$is_permission = false;
        //check current login user permission
		if ( kcCheckPermission( 'patient_edit' ) || kcCheckPermission( 'patient_view' ) || kcCheckPermission( 'patient_profile' ) ) {
			$is_permission = true;
		}

		if ( ! $is_permission ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();
		
		try {

			if ( ! isset( $request_data['id'] ) ) {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}

			$id = (int)$request_data['id'];
            if(!(new KCUser())->patientPermissionUserWise($id)){
                wp_send_json(kcUnauthorizeAccessResponse(403));
            }
			$user = get_userdata( $id );
			unset( $user->user_pass );
            $allUserMetaData = get_user_meta( $id);
            $user_data  = !empty($allUserMetaData['basic_data'][0]) ? $allUserMetaData['basic_data'][0] : [];
            //remove null value from object
            if(!empty($user_data)){
                $user_data = array_map(function ($v){
                    if(is_null($v) || $v == 'null'){
                        $v = '';
                    }
                    return $v;
                },(array)json_decode($user_data));
            }else{
                $user_data = [];
            }

			$first_name = !empty($allUserMetaData['first_name'][0]) ? $allUserMetaData['first_name'][0] : '';
			$last_name  = !empty($allUserMetaData['last_name'][0]) ? $allUserMetaData['last_name'][0] : '';

            if(!empty($allUserMetaData['country_code'][0])){
                $country_code = $allUserMetaData['country_code'][0];
            }
            else if(!get_option( KIVI_CARE_PREFIX . 'default_country_code_for_new_plugin' )) {
                $country_code = 'GB';
                update_option(KIVI_CARE_PREFIX . 'default_country_code_for_new_plugin', 'yes');
            }
            else if(get_option( KIVI_CARE_PREFIX . 'country_code' )){
                $country_code = get_option(KIVI_CARE_PREFIX . 'country_code', '');
            }
            else{
                $country_code = 'GB';
            }

            if(!empty($allUserMetaData['country_calling_code'][0])){
                $country_calling_code = $allUserMetaData['country_calling_code'][0];
            }
            else if(!get_option( KIVI_CARE_PREFIX . 'default_country_calling_code_for_new_plugin' )) {
                $country_calling_code = '44';
                update_option(KIVI_CARE_PREFIX . 'default_country_calling_code_for_new_plugin', 'yes');
            }
            else if(get_option( KIVI_CARE_PREFIX . 'country_calling_code' )){
                $country_calling_code = get_option(KIVI_CARE_PREFIX . 'country_calling_code', '');
            }
            else{
                $country_calling_code = '44';
            }      

			$data             = (object) array_merge( (array) $user->data, (array)$user_data );
			$data->first_name = $first_name;
			$data->username   = $data->user_login;
			$data->last_name  = $last_name;

            //patient clinic
            $clinics = collect($this->db->get_results("SELECT clinic.id AS id ,clinic.name AS label FROM {$this->db->prefix}kc_clinics AS clinic 
            LEFT JOIN {$this->db->prefix}kc_patient_clinic_mappings AS patient_clinic ON patient_clinic.clinic_id =clinic.id 
            WHERE patient_clinic.patient_id={$id}"))
                ->map(function ($v){
                    return ['id' => $v->id ,'label' => decodeSpecificSymbols($v->label)];
                })->toArray();
            $data->clinic_id = $clinics;

            //patient custom field
			$custom_filed = kcGetCustomFields('patient_module', $id);

            //patient profile image
			$data->user_profile = !empty($allUserMetaData['patient_profile_image'][0]) ? wp_get_attachment_url($allUserMetaData['patient_profile_image'][0]) : '';

            //patient unique id
            $data->u_id   = !empty($allUserMetaData['patient_unique_id'][0]) ? $allUserMetaData['patient_unique_id'][0] : '';
            $data->country_calling_code   = $country_calling_code;
            $data->country_code   = $country_code;
            $data->custom_forms = apply_filters('kivicare_custom_form_list',[],['type' => 'patient_module']);

            if(isKiviCareProActive()){
                $data->choose_language =get_user_locale() ;
            }

            if ($data) {
                $data = apply_filters('kivicare_patient_edit_data',$data,$allUserMetaData);
	            wp_send_json([
                    'status' => true,
                    'message' => 'Patient data',
                    'data' => $data,
                    'custom_filed' => $custom_filed
                ]);
            } else {
	            wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
			}


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

	public function delete() {

        //check current login user permission
		if ( ! kcCheckPermission( 'patient_delete' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}


			$id = (int)$request_data['id'];

			if(!(new KCUser())->patientPermissionUserWise($id)){
				wp_send_json(kcUnauthorizeAccessResponse(403));
			}
            //delete patient zoom appointment
			if (isKiviCareTelemedActive()) {
				apply_filters('kct_delete_patient_meeting', ['patient_id' => $id]);
			}

            //delete all patient medical history
            (new KCMedicalHistory())->delete(['patient_id' => $id]);

            //delete all patient medical record
            (new KCMedicalRecords())->delete(['patient_id' => $id]);

            (new KCPatientClinicMapping())->delete(['patient_id' => $id]);

            //delete all patient appointment
            (new KCAppointment())->loopAndDelete(['patient_id' => $id],false);

            //delete all patient encounter entry
            (new KCPatientEncounter())->loopAndDelete(['patient_id' => $id],false);

            //delete current patient custom field
            (new KCCustomFieldData())->delete(['module_type' => 'patient_module','module_id' => $id]);
            // hook for patient delete.
            do_action( 'kc_patient_delete', $id );

            //delete patient user meta
            delete_user_meta( $id, 'basic_data' );
            delete_user_meta( $id, 'first_name' );
            delete_user_meta( $id, 'last_name' );

            //delete requested patient
            $results = wp_delete_user( $id );

			if ( $results ) {
				wp_send_json( [
					'status'  => true,
					'message' =>  esc_html__('Patient deleted successfully', 'kc-lang'),
				] );
			} else {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}


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


    public function getHideFieldsArrayFromFilter(){

        //for future implementation
        $data = apply_filters('kivicare_hide_patients_optional_fields',[]);
	    wp_send_json([
            'status'=> true,
            'data' => $data
        ]);
    }

    public function patientProfileViewDetails(){

        //patient profile view page (future use)
	    $request_data = $this->request->getInputs();
	    if(empty($request_data['id'])){
            wp_send_json([
                'data' => [],
                'status' => false,
                'message' => __('Id Not Found','kc-lang')
            ]);
        }

		if(!(new KCUser())->patientPermissionUserWise($request_data['id'])){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

	    $user_id = get_current_user_id();
	    $user_role = $this->getLoginUserRole();

	    $data = [];
	    $clinic_id = collect($this->db->get_results("SELECT id FROM {$this->db->prefix}kc_clinics"))->pluck('id')->implode(',');
	    if(isKiviCareProActive()){
            switch ($user_role) {
                case $this->getClinicAdminRole():
                    $clinic_id = kcGetClinicIdOfClinicAdmin();
                    break;
                case $this->getReceptionistRole():
                    $clinic_id = kcGetClinicIdOfReceptionist();
                    break;
                case $this->getDoctorRole():
                    $clinic_id = collect($this->db->get_results("SELECT clinic_id FROM {$this->db->prefix}kc_doctor_clinic_mappings WHERE doctor_id={$user_id}"))->pluck('clinic_id')->implode(',');
                    break;
                default:
                    # code...
                    break;
            }
        }

	    if(empty($clinic_id)){
		    wp_send_json([
                'data' =>  [
                    'total_appointment' => 0,
                    'upcoming_appointment' => 0,
                    'total_encounters' => 0,
                    'upcoming_encounters' => 0
                ],
                'status' => true,
                'message' => __('Patients Details','kc-lang')
            ]);
        }

	    $id = (int)$request_data['id'];
	    $appointment_table = $this->db->prefix.'kc_appointments';
	    $encounter_table = $this->db->prefix.'kc_patient_encounters';
	    $patient_condition = " AND patient_id = {$id} AND clinic_id IN ({$clinic_id})";
	    $data['total_appointment'] = $this->db->get_var("SELECT COUNT(*) FROM {$appointment_table} WHERE 0 = 0 {$patient_condition}");
        $data['upcoming_appointment'] = $this->db->get_var( "SELECT count(*) from {$appointment_table} WHERE 0 = 0  {$patient_condition}  AND status = 1 AND appointment_start_date > CURDATE() OR ( appointment_start_date = CURDATE() AND appointment_start_time > CURTIME())");
        $data['total_encounters'] = $this->db->get_var("SELECT COUNT(*) FROM {$encounter_table} WHERE 0 = 0 {$patient_condition} ");
        $data['upcoming_encounters'] = $this->db->get_var( "SELECT count(*) from {$encounter_table} WHERE 0 = 0   {$patient_condition}  AND status = 1 AND encounter_date > CURDATE() OR  encounter_date = CURDATE()");

	    wp_send_json([
	        'data' => $data,
            'status' => true,
            'message' => __('Patients Details','kc-lang')
        ]);
    }

    public function savePatientClinic($patient_id,$clinic_id){
        
        $patient_mapping = new KCPatientClinicMapping;

        //save/update doctor clinic mappings
        if(is_array($clinic_id)){
            foreach ($clinic_id as $value) {
                $new_temp = [
                    'patient_id' => (int)$patient_id,
                    'clinic_id' => $value['id'],
                    'created_at' => current_time('Y-m-d H:i:s')
                ];
                $patient_mapping->insert($new_temp);
            }
        } else{
            $new_temp = [
                'patient_id' => (int)$patient_id,
                'clinic_id' => $clinic_id,
                'created_at' => current_time('Y-m-d H:i:s')
            ];
            $patient_mapping->insert($new_temp);
        }
    }

}
