<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCReceptionistClinicMapping;
use App\models\KCClinic;
use App\models\KCUser;
use Exception;
use WP_User;

class KCReceptionistController extends KCBase {

	public $db;

	private $request;

	public function __construct() {
		$this->request = new KCRequest();
        parent::__construct();
	}

	public function index() {

		global $wpdb;

		if (! kcCheckPermission('receptionist_list')) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();
		$table_name = $wpdb->prefix . 'kc_receptionist_clinic_mappings';

		$args['role']           = $this->getReceptionistRole();
        $args['orderby']        = 'ID';
        $args['order']          = 'DESC';
        if((int)$request_data['perPage'] > 0){
            $args['page'] = (int)$request_data['page'];
            $args['number'] = $request_data['perPage'];
            $args['offset'] = ((int)$request_data['page'] - 1) * (int)$request_data['perPage'];
        }


        $search_condition = '';
        if(!empty($request_data['sort'])){
            $request_data['sort'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($request_data['sort'][0]),true));
            if(!empty($request_data['sort']['field']) && !empty($request_data['sort']['type']) && $request_data['sort']['type'] !== 'none'){
                $args['orderby']        = esc_sql($request_data['sort']['field']);
                $args['order']          = esc_sql(strtoupper($request_data['sort']['type']));
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
                foreach ($request_data['columnFilters'] as $column => $searchValue){
					$searchValue = !empty($searchValue) ? $searchValue : '';
                    $searchValue = esc_sql(strtolower(trim($searchValue)));
                    if($column !== 'user_status' && empty($searchValue) ){
                        continue;
                    }
                    $column = esc_sql($column);
                    if($column === 'clinic_name' && isKiviCareProActive()){
                        $search_condition .= " AND {$wpdb->users}.id IN (SELECT receptionist_id FROM {$table_name} WHERE clinic_id={$searchValue}) ";
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

		$args['include'] = array();
		if(!current_user_can('administrator')) {
            $query = "SELECT `receptionist_id` FROM {$table_name} WHERE `clinic_id` =" . kcGetClinicIdOfClinicAdmin();
            $result = collect($wpdb->get_results($query))->unique('receptionist_id')->pluck('receptionist_id')->toArray();
			if(!empty($result)){
				foreach($result as $receptionist_id) {
					array_push($args['include'], $receptionist_id);
				}
			}else{
				$args['include'] = [-1];
			}
        }
        $results = $this->getUserData($args,$search_condition);
        //total receptionists count
        $total = $results['total'];

        // receptionists list
        $receptionists = $results['list'];

        if ( ! count( $receptionists ) ) {
	        wp_send_json( [
				'status'  => false,
				'message' => esc_html__('No receptionist found', 'kc-lang'),
				'data'    => []
			] );
		}

		$data = [];

		foreach ( $receptionists as $key => $receptionist ) {

            $receptionist->ID = (int)$receptionist->ID;
            $allUserMeta = get_user_meta( $receptionist->ID);
			$clinic_mapping = (new KCReceptionistClinicMapping())->get_var([ 'receptionist_id' => $receptionist->ID],'clinic_id');
            $clinic_id = !empty($clinic_mapping) ? (int)$clinic_mapping : -1 ;
            $clinic_name =  (new KCClinic())->get_var([ 'id' => $clinic_id ],'name');
			$data[ $key ]['ID']              = $receptionist->ID;
			$image_attachment_id = !empty($allUserMeta['receptionist_profile_image'][0]) ? $allUserMeta['receptionist_profile_image'][0] : '';
			$data[ $key ]['profile_image'] = (!empty($image_attachment_id) && $image_attachment_id != '') ? wp_get_attachment_url($image_attachment_id) : '';
			$data[ $key ]['display_name']    = $receptionist->display_name;
			$data[ $key ]['user_email']      = $receptionist->user_email;
			$data[ $key ]['user_status']     = $receptionist->user_status;
			$data[ $key ]['user_registered'] = $receptionist->user_registered;
			$data[$key]['clinic_id'] = $clinic_id;
            $data[$key]['clinic_name'] = decodeSpecificSymbols($clinic_name);
            $data[$key]['user_registered_formated']= date("Y-m-d", strtotime($receptionist->user_registered));
            $user_deactivate = 'yes';
            $user_deactivate_status = !empty($allUserMeta['kivicare_user_account_status'][0]) ? $allUserMeta['kivicare_user_account_status'][0] : '';
            if(!empty($user_deactivate_status)){
                $user_deactivate = $user_deactivate_status;
            }
            $data[$key]['user_deactivate'] = $user_deactivate;
			$country_calling_code = !empty($allUserMeta['country_calling_code'][0]) ? '+' . $allUserMeta['country_calling_code'][0] : '';
            $user_meta = !empty($allUserMeta['basic_data'][0]) ? $allUserMeta['basic_data'][0] : false ;
            if (!empty($user_meta)) {
                $basic_data = json_decode( $user_meta,true );
                foreach ( $basic_data as $basic_data_key => $basic_data_value){
                    if ($basic_data_key === 'mobile_number') {
						$data[$key][$basic_data_key] =  $country_calling_code . ' ' . $basic_data_value;
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

            $data[ $key ]['full_address'] = ('#').(!empty($data[ $key ]['address']) && $data[ $key ]['address'] !== '-' ? $data[ $key ]['address'].',' : '' ) .
                (!empty($data[ $key ]['city']) && $data[ $key ]['city'] !== '-' ? $data[ $key ]['city'].',' : '').
                (!empty($data[ $key ]['postal_code']) && $data[ $key ]['postal_code'] !== '-' ? $data[ $key ]['postal_code'].',' : '').
                (!empty($data[ $key ]['country'])  && $data[ $key ]['country'] !== '-' ? $data[ $key ]['country'] : '');
		}

		wp_send_json([
			'status'     => true,
			'message'    => esc_html__('Receptionist list', 'kc-lang'),
			'data'       => array_values($data),
			'total_rows' => $total
		]);

	}

	public function save() {

		global $wpdb;
        $request_data = $this->request->getInputs();
        $profile_permission = false;

        //profile permission
        if( kcCheckPermission( 'receptionist_profile' )
            && !empty($request_data['ID']) && (new KCUser())->receptionistPermissionUserWise($request_data['ID'] ) ) {
            $profile_permission = true;
        }

		if ( ! ( $profile_permission || kcCheckPermission( 'receptionist_add' )  ) ) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$rules = [
			'first_name'    => 'required',
			'user_email'    => 'required|email',
			'mobile_number' => 'required',
			//'dob'           => 'required',
			'gender'        => 'required',
			'country_calling_code' => 'required',
			'country_code' => 'required',
		];

		$errors = kcValidateRequest( $rules, $request_data );

		if ( count( $errors ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => $errors[0]
			] );
		}

        //check email condition
        $email_condition = kcCheckUserEmailAlreadyUsed($request_data);
        if(empty($email_condition['status'])){
	        wp_send_json($email_condition);
        }

        $username = kcGenerateUsername( $request_data['first_name'] );

        $password = kcGenerateString( 12 );

        $current_login_user_role = $this->getLoginUserRole();

		// Remove parentheses
        $request_data['mobile_number'] = str_replace(['(', ')'], '', $request_data['mobile_number']);

        // Remove dashes
        $request_data['mobile_number'] = str_replace('-', '', $request_data['mobile_number']);

        // Remove extra spaces
        $request_data['mobile_number'] = preg_replace('/\s+/', '', $request_data['mobile_number']);

		$temp = [
			'mobile_number' => str_replace(' ', '', $request_data['mobile_number']) ,
			'gender'        => $request_data['gender'],
			'dob'           => !empty($request_data['dob']) ? $request_data['dob'] : '',
			'address'       => $request_data['address'],
			'city'          => $request_data['city'],
			'state'         => '',
			'country'       => $request_data['country'],
			'postal_code'   => $request_data['postal_code'],
		];

        if(isKiviCareProActive()){
            if($current_login_user_role === $this->getClinicAdminRole()) {
                $clinic_id = kcGetClinicIdOfClinicAdmin();
            }else{
                if(isset($request_data['clinic_id'][0]['id']) ){
                    $clinic_id = (int)$request_data['clinic_id'][0]['id'];
                }else if( isset($request_data['clinic_id']['id']) ){
                    $clinic_id = (int)$request_data['clinic_id']['id'];
                }else{
                    $clinic_id = kcGetDefaultClinicId();
                }
            }
        }else{
            $clinic_id = kcGetDefaultClinicId();
        }


		if ( empty($request_data['ID']) ) {

			$user = wp_create_user( $username, $password, sanitize_email( $request_data['user_email'] ) );

			$u               = new WP_User( $user );
			$u->display_name = $request_data['first_name'] . ' ' . $request_data['last_name'];
			wp_insert_user( $u );

			$u->set_role( $this->getReceptionistRole() );

			$user_id = $u->ID;
			update_user_meta($user, 'first_name', $request_data['first_name'] );
			update_user_meta($user, 'last_name', $request_data['last_name'] );
			update_user_meta( $user, 'basic_data', json_encode( $temp, JSON_UNESCAPED_UNICODE ) );
			update_user_meta($user, 'country_calling_code', $request_data['country_calling_code']);
			update_user_meta($user, 'country_code', $request_data['country_code']);

			// Insert Doctor Clinic mapping...
			$receptionist_mapping = new KCReceptionistClinicMapping;

			$new_temp = [
				'receptionist_id' => $user_id,
				'clinic_id'       => $clinic_id,
				'created_at'      =>   current_datetime('Y-m-d H:i:s' )
			];

			$receptionist_mapping->insert( $new_temp );

            $user_email_param = kcCommonNotificationUserData($user_id,$password);

            kcSendEmail($user_email_param);
            if(kcCheckSmsOptionEnable() || kcCheckWhatsappOptionEnable()){
                $sms = apply_filters('kcpro_send_sms', [
                    'type' => 'receptionist_register',
                    'user_data' => $user_email_param,
                ]);
            }

			$message = esc_html__('Receptionist saved successfully', 'kc-lang');

		} else {

			$receptionist_mapping = new KCReceptionistClinicMapping;

            $request_data['ID'] = (int)$request_data['ID'];
			if( ! (new KCUser())->receptionistPermissionUserWise($request_data['ID'])){
				wp_send_json(kcUnauthorizeAccessResponse(403));
			}
			wp_update_user(
				array(
					'ID'           => $request_data['ID'],
					'user_email'   => sanitize_email( $request_data['user_email']),
					'display_name' => $request_data['first_name'] . ' ' . $request_data['last_name']
				)
			);

			$user_id = $request_data['ID'];
			update_user_meta($user_id, 'first_name', $request_data['first_name']);
			update_user_meta($user_id, 'last_name' , $request_data['last_name']);
			update_user_meta( $user_id, 'basic_data', json_encode( $temp, JSON_UNESCAPED_UNICODE ));
			update_user_meta($user_id, 'country_calling_code', $request_data['country_calling_code']);
			update_user_meta($user_id, 'country_code', $request_data['country_code']);

            (new KCReceptionistClinicMapping())->delete(['receptionist_id' => $user_id]);
            $receptionist_mapping->insert([
                'receptionist_id' => $user_id,
                'clinic_id' => $clinic_id,
                'created_at' => current_time('Y-m-d H:i:s')
            ]);
			$message = esc_html__('Receptionist has been updated successfully', 'kc-lang');

		}

		if ( $user_id ) {
			$user_table_name = $wpdb->base_prefix . 'users';
			$user_status     = $request_data['user_status'];
			$wpdb->update( $user_table_name, [ 'user_status' => $user_status ], [ 'ID' => $user_id ] );
		}
		if(isset($request_data['profile_image']) && !empty((int)$request_data['profile_image']) ){
            update_user_meta( $user_id, 'receptionist_profile_image',  $request_data['profile_image']  );
        }
		if ( !empty($user->errors) ) {
			wp_send_json( [
				'status'  => false,
				'message' => $user->get_error_message() ? $user->get_error_message() : esc_html__(' Failed to save Receptionist data', 'kc-lang')
			] );
		} else {
            if(!empty($request_data['ID'])){
                do_action( 'kc_receptionist_update', $user_id);
            }else{
                do_action( 'kc_receptionist_save', $user_id );
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

		if ( kcCheckPermission( 'receptionist_profile' ) || kcCheckPermission( 'receptionist_edit' ) ) {
			$is_permission = true;
		}

		if ( ! $is_permission ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();
        try {

			if ( !isset( $request_data['id'] ) ) {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}

            $table_name = collect((new KCClinic)->get_all());

            $request_data['id'] = (int)$request_data['id'];
            $clinics = collect((new KCReceptionistClinicMapping)->get_by(['receptionist_id' =>$request_data['id']]))->pluck('clinic_id')->toArray();
            $clinics = $table_name->whereIn('id', $clinics);

            $id = $request_data['id'];

			$user = get_userdata( $id );
			unset( $user->user_pass );

            $allUserMetaData = get_user_meta( $id);
            $user_data  = !empty($allUserMetaData['basic_data'][0]) ? $allUserMetaData['basic_data'][0] : [];
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
            $user_image_url = !empty($allUserMetaData['receptionist_profile_image'][0]) ? wp_get_attachment_url($allUserMetaData['receptionist_profile_image'][0]) : '';
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

			$data             = (object) array_merge( (array) $user->data, $user_data );
			$data->first_name = $first_name;
			$data->username   = $data->user_login;
			$data->last_name  = $last_name;
            $clinic_id_array = $list = [];
			foreach($clinics as $d ){
                $list[] = [
                    'id'    => $d->id,
                     'label' => decodeSpecificSymbols($d->name),
                 ];
            }
            $data->clinic_id = $list;
			$data->user_profile =$user_image_url;
			$data->country_calling_code = $country_calling_code;
			$data->country_code = $country_code;
			if(isKiviCareProActive()){
                $data->choose_language =get_user_locale() ;
            }
			if ( $data ) {
				if( ! (new KCUser())->receptionistPermissionUserWise($id)){
					wp_send_json(kcUnauthorizeAccessResponse(403));
				}
				wp_send_json( [
					'status'    => true,
					'message'   => 'Receptionist data found',
					'id'        => $id,
					'user_data' => $user_data,
					'data'      => $data
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
				'message' => $e->getMessage()
			] );
		}
	}

	public function delete() {

		if ( ! kcCheckPermission( 'receptionist_delete' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}

			$id = (int)$request_data['id'];

			if( ! (new KCUser())->receptionistPermissionUserWise($id)){
				wp_send_json(kcUnauthorizeAccessResponse(403));
			}
            // hook for Receptionist delete
            do_action( 'kc_receptionist_delete', $id );

			delete_user_meta( $id, 'basic_data' );
			delete_user_meta( $id, 'first_name' );
			delete_user_meta( $id, 'last_name' );

            (new KCReceptionistClinicMapping())->delete(['receptionist_id' => $id]);

			$results = wp_delete_user( $id );

			if ( $results ) {
				wp_send_json( [
					'status'  => true,
					'message' => esc_html__('Receptionist deleted successfully', 'kc-lang'),
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

	public function changeEmail() {

		//unused function
		wp_send_json([]);

		$request_data = $this->request->getInputs();

		wp_send_json( [
			'status'  => true,
			'data'    => $request_data,
			'message' => esc_html__('Email has been changed', 'kc-lang'),
		] );

	}
}