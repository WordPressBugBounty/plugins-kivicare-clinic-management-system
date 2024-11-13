<?php

namespace App\Controllers;

use App\baseClasses\KCActivate;
use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCClinic;
use App\models\KCClinicSession;
use App\models\KCDoctorClinicMapping;
use App\models\KCReceptionistClinicMapping;
use App\models\KCServiceDoctorMapping;
use WP_User;

class KCSetupController extends KCBase {

	public $db;

	private $request ;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->request = new KCRequest();

        parent::__construct();

		if ( $this->getLoginUserRole() !== 'administrator' ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

	}

    public function clinic() {
		$request_data = $this->request->getInputs();

    	$request_data['specialties'] = json_decode(stripslashes($request_data['specialties']));

		if($request_data['profile_image'] != '' && isset($request_data['profile_image']) && $request_data['profile_image'] != null ){
            $request_data['profile_image'] = media_handle_upload('profile_image', 0);
        }
        $temp1 = [
            'name' => $request_data['name'] ,
            'email' => $request_data['email'],
            'telephone_no' => $request_data['telephone_no'] ,
            'address' => $request_data['address'] ,
            'city' => $request_data['city'] ,
            'state' => $request_data['state'] ,
            'country' => $request_data['country'] ,
            'postal_code' => $request_data['postal_code'] ,
            'specialties' => json_encode($request_data['specialties']),
            'status' => 1,
			'profile_image'=> $request_data['profile_image']
		];

		$clinic = new KCClinic;

        $currency_prefix = !empty($request_data['currency_prefix']) ? $request_data['currency_prefix'] : '';
        $currency_postfix = !empty($request_data['currency_postfix']) ? $request_data['currency_postfix'] : '';

        $currency = [
            'currency_prefix' => $currency_prefix,
            'currency_postfix' => $currency_postfix
        ];

        $temp1['extra'] = json_encode($currency);
        $temp1['country_calling_code'] = $request_data['country_calling_code'];
        $temp1['country_code'] = $request_data['country_code'];

        if ( !isset( $request_data['id']) || $request_data['id'] === null || $request_data['id'] === '') {
            $temp1['created_at'] = current_time('Y-m-d H:i:s');
            $insert_id = $clinic->insert($temp1);
            if ($insert_id) {
                $step_detail = array( 'step' => 1, 'name' => 'clinic', 'id' => [$insert_id], 'status' => true);
                $encoded_status_data = json_encode($step_detail);
                add_option('setup_step_1', $encoded_status_data);
                update_option('clinic_setup_wizard', 1);
            }

            $message =  esc_html__('Clinic saved successfully', 'kc-lang');

            do_action('kcpro_clinic_save',$insert_id);
        } else {
            $insert_id = 0 ;
            $status = $clinic->update($temp1, array( 'id' => (int)$request_data['id'] ));
	        $message = esc_html__('Clinic updated successfully', 'kc-lang');
            do_action('kcpro_clinic_update',$request_data['id']);
        }

	    wp_send_json([
            'status' => true,
            'message' => $message,
	        'data' => array('insert_id' => $insert_id )
        ]);

    }
    public function clinicAdmin() {
        
        $request_data = $this->request->getInputs();

        $clinic_id = kcGetDefaultClinicId();
        if($request_data['profile_image'] != '' && isset($request_data['profile_image']) && $request_data['profile_image'] != null ){
            $request_data['profile_image'] = media_handle_upload('profile_image', 0);
        }
        $rules = [
            'first_name' => 'required',
            'last_name'   => 'required',
            'user_email' => 'required|email',
            'mobile_number' => 'required',
            'gender' => 'required',
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

        $request_data['user_pass'] = kcGenerateString(12);

        if(!empty($request_data['selected_demo_user']) && strpos($request_data['selected_demo_user'], ',') !== false){
            $request_data['selected_demo_user'] = explode(',',$request_data['selected_demo_user']);
            $this->createDemoUser($request_data);
        }

        $temp = [
            'first_name'    =>  $request_data['first_name'] ,
            'last_name'     =>  $request_data['last_name'] ,
            'user_email'    =>  $request_data['user_email'],
            'dob'           =>  $request_data['doc_birthdate'] ,
            'mobile_number' => str_replace(' ', '', $request_data['mobile_number']) ,
            'gender'        =>  $request_data['gender'] ,
            'profile_image' => $request_data['profile_image']
        ];
        if ( ! isset( $request_data['ID'] ) || $request_data['ID'] === null || $request_data['ID'] === '') {

            $request_data['username'] = kcGenerateUsername( $request_data['first_name'] );

            $user = wp_create_user($request_data['username'], $request_data['user_pass'], sanitize_email( $request_data['user_email']) );

            $u    = new WP_User( $user );

            $u->display_name = $request_data['first_name'] . ' ' . $request_data['last_name'] ;

            wp_insert_user($u);

            $u->set_role($this->getClinicAdminRole());

            if($user) {
                $clinic_mapping = new KCClinic;

                $new_temp = [
                    'clinic_admin_id' => $user,
                    'created_at'=> current_time('Y-m-d H:i:s')
                ];

                $clinic_mapping->update($new_temp,array( 'id' => $clinic_id ));

                $user_email_param = array (
                    'username' => $request_data['username'] ,
                    'user_email' => $request_data['user_email'] ,
                    'password' => $request_data['user_pass'] ,
                    'email_template_type' => 'clinic_admin_registration'
                );

                kcSendEmail($user_email_param);
            }
            update_user_meta( $user, 'first_name', $request_data['first_name'] ) ;
            update_user_meta( $user, 'last_name', $request_data['last_name'] ) ;
            update_user_meta( $user, 'basic_data', json_encode( $temp ) );
            update_user_meta($user, 'country_code', $request_data['country_code']);
            update_user_meta($user, 'country_calling_code', $request_data['country_calling_code']);

        } else {

            $request_data['ID'] = (int)$request_data['ID'];
            wp_update_user(
                array(
                    'ID'         => $request_data['ID'],
                    'user_login' => $request_data['username'],
                    'user_email' => sanitize_email( $request_data['user_email'] ),
                    'display_name' => $request_data['first_name'] . ' ' . $request_data['last_name']
                )
            );

            update_user_meta( $request_data['ID'], 'basic_data', json_encode( $temp ) );
            update_user_meta($request_data['ID'], 'country_code', $request_data['country_code']);
            update_user_meta($request_data['ID'], 'country_calling_code', $request_data['country_calling_code']);

        }

        $step_detail = array( 'step' => 4, 'name' => 'clinic_admin', 'status' => true);
        $encoded_status_data = json_encode($step_detail);
        add_option('setup_step_4', $encoded_status_data);

        if ( !empty($user) && is_wp_error( $user ) ) {

	        wp_send_json( [
                'status'  => false,
                'message' => $user->get_error_message() ? $user->get_error_message() : esc_html__('Failed to save Clinic admin data', 'kc-lang')
            ] );

        } else {

            do_action('kcpro_clinic_update',$clinic_id);
	        wp_send_json( [
                'status'  => true,
                'message' => esc_html__('Clinic Admin has been saved successfully', 'kc-lang'),
            ] );

        }

    }
    public function doctor() {

	    $request_data = $this->request->getInputs();

	    $rules = [
		    'first_name' => 'required',
		    'last_name'   => 'required',
		    'user_email' => 'required|email',
		    'mobile_number' => 'required',
		    'dob' => 'required',
		    'gender' => 'required',
	    ];

	    $errors = kcValidateRequest( $rules, $request_data );

	    if ( count( $errors ) ) {
		    wp_send_json( [
			    'status'  => false,
			    'message' => $errors[0]
		    ] );
	    }

	    $temp = [
            'mobile_number'  => str_replace(" ","",$request_data['mobile_number']),
            'gender'         => $request_data['gender'],
            'dob'            => $request_data['dob'],
            'address'        => $request_data['address'],
            'city'           => $request_data['city'],
            'state'          => $request_data['state'],
            'country'        => $request_data['country'],
            'postal_code'    => $request_data['postal_code'],
            'qualifications' => $request_data['qualifications'],
            'specialties'    => $request_data['specialties'],
            'price'          => $request_data['price'],
            'price_type'     => $request_data['price_type'],
            'time_slot'      => $request_data['time_slot'],
        ];

	    if ( isset( $request_data['price_type'] ) && $request_data['price_type'] === "range" ) {
		    $temp['price'] = $request_data['minPrice'] . '-' . $request_data['maxPrice'];
	    }

	    if ( ! isset( $request_data['ID'] ) || $request_data['ID'] === null || $request_data['ID'] === '') {

		    $request_data['username'] = kcGenerateUsername( $request_data['first_name']) ;

            $request_data['password'] = kcGenerateString(12);

            $user = wp_create_user( $request_data['username'] , $request_data['password'], $request_data['user_email'] );

	        $u    = new WP_User( $user );
	        $u->display_name = $request_data['first_name'] . ' ' . $request_data['last_name'] ;

	        wp_insert_user($u);

	        $u->set_role( $this->getDoctorRole() );

	         if($user) {

	             $user_email_param = array (
	                 'username' => $request_data['username'],
		             'user_email' => sanitize_email( $request_data['user_email'] ),
		             'password' => $request_data['password'],
		             'email_template_type' => 'doctor_registration'
	             );

		        kcSendEmail($user_email_param);

	         }

	        update_user_meta( $user, 'basic_data', json_encode( $temp ) );

		    $doctor_ids = [];

		    foreach ( $u as $key => $val ) {
			    if ( $val->ID !== '' && $val->ID !== null ) {
				    array_push( $doctor_ids, (int) $val->ID );
			    }
		    }

		    $current_step_status = get_option( 'setup_step_2' );

		    $clinic  =  get_option('setup_step_1');

		    $clinic = json_decode($clinic);

		    // Insert Doctor Clinic mapping...
		    $doctor_mapping = new KCDoctorClinicMapping;

		    $new_temp = [
			    'doctor_id' => (int)$u->ID,
			    'clinic_id' => (int)$clinic->id[0],
			    'created_at'=> current_time('Y-m-d H:i:s')
		    ];

		    $doctor_mapping->insert($new_temp);

		    if ( $current_step_status ) {

			    $step_detail = json_decode( $current_step_status );

			    delete_option( 'setup_step_2' );

			    array_push( $step_detail->id, $doctor_ids[0]);

		    } else {

			    $step_detail = array( 'step' => 2, 'name' => 'doctor', 'id' => $doctor_ids, 'status' => true );

		    }

		    $encoded_status_data = json_encode($step_detail);
		    add_option('setup_step_2', $encoded_status_data);
		    $message = __('Doctor has been saved successfully' ,'kc-lang');

	    } else {
            $request_data['ID'] = (int)$request_data['ID'] ;

		    wp_update_user(
			    array(
				    'ID'         => $request_data['ID'],
				    'user_login' => $request_data['username'],
				    'user_email' => $request_data['user_email'],
				    'display_name' => $request_data['first_name'] . ' ' . $request_data['last_name']
			    )
		    );

		    $user_meta_data  = get_user_meta($request_data['ID'], 'basic_data', true);
		    $basic_data = json_decode($user_meta_data);

		    if (isset($basic_data->time_slot)) {
		    	if($basic_data->time_slot !== $request_data['time_slot']) {
				    $this->resetDoctorSession($request_data['ID']);
			    }
		    }

		    update_user_meta($request_data['ID'], 'basic_data', json_encode( $temp ) ) ;

		    $message = esc_html__('Doctor updated successfully', 'kc-lang');

	    }

        if ( !empty($user->errors) ) {

	        wp_send_json( [
                'status'  => false,
                'message' => $user->get_error_message() ? $user->get_error_message() : esc_html__('Failed to save Doctor data.', 'kc-lang')
            ] );

        } else {

	        wp_send_json( [
		        'status'  => true,
		        'message' => $message,
	        ] );

        }

    }

    public function clinicSession() {
		$clinic  =  get_option('setup_step_1');

		$clinic = json_decode($clinic);

	    $session_parent_ids = [] ;

	    $request_data = $this->request->getInputs();

	    $clinic_session = new KCClinicSession();

        $clinic_id = $clinic->id[0];
	    $clinic_session->delete(['clinic_id' => (int)$clinic->id[0] ]);

        if (count($request_data['clinic_sessions'])){
            foreach ($request_data['clinic_sessions'] as $key => $session) {
                $parent_id = 0;
                foreach ($session['days'] as $day) {

                    $start_time = date('H:i:s', strtotime($session['s_one_start_time']['HH'] . ':' . $session['s_one_start_time']['mm']));
                    $end_time = date('H:i:s', strtotime($session['s_one_end_time']['HH'] . ':' . $session['s_one_end_time']['mm']));

                    $session_temp = [
                        'clinic_id' => (int)$clinic_id,
                        'doctor_id' => (int)$session['doctors']['id'],
                        'day' => $day,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                        'time_slot' => $session['time_slot'],
                        'created_at' => current_time('Y-m-d H:i:s'),
                        'parent_id' => (int)$parent_id === 0 ? null : (int)$parent_id
                    ];

                    if ((int)$parent_id === 0) {
                        $parent_id = $clinic_session->insert($session_temp);
                    } else {
                        $clinic_session->insert($session_temp);
                    }

                    if ($session['s_two_start_time']['HH'] !== null && $session['s_two_end_time']['HH'] !== null) {

                        $session_temp['start_time'] = date('H:i:s', strtotime($session['s_two_start_time']['HH'] . ':' . $session['s_two_start_time']['mm']));
                        $session_temp['end_time'] = date('H:i:s', strtotime($session['s_two_end_time']['HH'] . ':' . $session['s_two_end_time']['mm']));
                        $session_temp['parent_id'] = (int)$parent_id;

                        $clinic_session->insert($session_temp);
                    }
                }

            }

            $step_detail = array( 'step' => 3, 'name' => 'clinic_session', 'id' => $session_parent_ids, 'status' => true);
            $encoded_status_data = json_encode($step_detail);
            add_option('setup_step_3', $encoded_status_data);

        }

	    wp_send_json([
		    'status' => true,
		    'message' => esc_html__('Clinic session saved successfully', 'kc-lang'),
	    ]);
    }

    public function getSetupStepStatus () {

	    $request_data = $this->request->getInputs();

	    $step = (int)$request_data['step'];
        $setup_wizard_status = get_option('setup_step_'.$step);

        if ($setup_wizard_status) {
            switch ($step) {
                case 1:
	                $clinic = new KCClinic();
                    $clinic_setup_data =  json_decode($setup_wizard_status);
                    $clinic_setup_detail = $clinic->get_by( [ 'id' => (int)$clinic_setup_data->id ], '=', true );
                    $status = true;
                    $step_detail = $clinic_setup_detail;
                    $currency_data = json_decode($step_detail->extra);
                    $step_detail->currency_prefix = $currency_data->currency_prefix;
                    $step_detail->currency_postfix = $currency_data->currency_postfix;
                    break;
                case 2:
                    $step_detail = get_option('setup_step_2');
                    if(!$step_detail) {
                        $doctors = [] ;
	                    wp_send_json( [
                            'status'  => false,
                            'message' => esc_html__('No doctors found', 'kc-lang'),
                            'data' => []
                        ]);
                    } else {
                        $step_detail = json_decode($step_detail);
                        $args = [
                            'include' => $step_detail->id
                        ];

                        $doctors = get_users( $args );

                    }

                    $data = [];

                    if (!count($doctors)) {
	                    wp_send_json( [
                            'status'  => false,
                            'message' => esc_html__('No doctors found', 'kc-lang'),
                            'data' => []
                        ]);
                    }

                    foreach ($doctors as $key => $doctor) {

                        $user_meta = get_user_meta( $doctor->ID, 'basic_data', true );

                        $data[$key]['ID'] = $doctor->ID;
                        $data[$key]['display_name'] = $doctor->data->display_name;
                        $data[$key]['user_email'] = $doctor->data->user_email;
                        $data[$key]['user_status'] = $doctor->data->user_status;
                        $data[$key]['user_registered'] = $doctor->data->user_registered;

                        if ($user_meta != null) {
                            $basic_data = json_decode($user_meta);
                            $data[$key]['mobile_number'] = $basic_data->mobile_number;
                            $data[$key]['gender'] = $basic_data->gender;
                            $data[$key]['dob'] = $basic_data->dob;
                            $data[$key]['address'] = $basic_data->address;
                            $data[$key]['specialties'] = $basic_data->specialties;
                            $data[$key]['time_slot'] = $basic_data->time_slot;

	                        if (isset($basic_data->price_type)) {
		                        if ( $basic_data->price_type === "range" ) {
			                        $price          = explode( "-", $basic_data->price);
			                        $data[$key]['minPrice'] = isset( $price[0] ) ? $price[0] : 0;
			                        $data[$key]['maxPrice'] = isset( $price[1] ) ? $price[1] : 0;
			                        $data[$key]['price']    = 0;
		                        } else {
			                        $data[$key]['price'] = $basic_data->price ;
		                        }
	                        } else {
		                        $data[$key]['price_type'] = "range";
	                        }
                        }

                    }

	                $clinic_id = kcGetDefaultClinicId();
	                $doctor_data['doctors'] = $data ;
	                $doctor_data['clinic_session'] = kcGetClinicSessions($clinic_id);
                    $status = true;
                    $step_detail = $doctor_data ;
                    break;

                case 3:
                    $status = true;
                    $clinic_id = kcGetDefaultClinicId();
                    $step_detail = kcGetClinicSessions($clinic_id);
                    break;
                case 4:
                    $data = [];

                    $receptionists = get_users([
                        'role' => 'receptionist'
                    ]);

                    if (!count($receptionists)) {
	                    wp_send_json( [
                            'status'  => false,
                            'message' => esc_html__('No receptionist found', 'kc-lang'),
                            'data' => []
                        ]);
                    }

                    foreach ($receptionists as $key => $receptionist) {

                        $user_meta = get_user_meta( $receptionist->ID, 'basic_data', true );
                        $first_name = get_user_meta( $receptionist->ID, 'first_name', true );
                        $last_name = get_user_meta( $receptionist->ID, 'last_name', true );

                        $data[$key]['ID'] = $receptionist->ID;
                        $data[$key]['display_name'] = $receptionist->data->display_name;
                        $data[$key]['user_email'] = $receptionist->data->user_email;
                        $data[$key]['user_status'] = $receptionist->data->user_status;
                        $data[$key]['user_registered'] = $receptionist->data->user_registered;
                        $data[$key]['username'] = $receptionist->data->user_login;

                        if($first_name !== null) {
                            $data[$key]['first_name'] = $first_name;
                        }

                        if($last_name !== null) {
                            $data[$key]['last_name'] = $last_name;
                        }

                        if ($user_meta !== null) {
                            $basic_data = json_decode($user_meta);
                            $data[$key]['mobile_number'] = $basic_data->mobile_number;
                            $data[$key]['gender'] = $basic_data->gender;
                            $data[$key]['dob'] = $basic_data->dob;
                            $data[$key]['address'] = $basic_data->address;
                            $data[$key]['state'] = $basic_data->state;
                            $data[$key]['city'] = $basic_data->city;
                            $data[$key]['postal_code'] = $basic_data->postal_code;
                            $data[$key]['country'] = $basic_data->country;
                        }

                    }

                    $status = true;
                    $step_detail = $data ;
                    break;

                default:
                    $status = false;
                    $step_detail = [];
            }

        } else {
            $status = false;
            $step_detail = [];
        }

	    $data = [
		    'status' => $status,
		    'message' => esc_html__('Setup step found', 'kc-lang'),
		    'data' => $step_detail
	    ];

	    wp_send_json($data);

    }

	public function receptionist () {

		$request_data = $this->request->getInputs();

		$rules = [
			'first_name' => 'required',
			'last_name'   => 'required',
			'user_email' => 'required|email',
			'mobile_number' => 'required',
			'dob' => 'required',
			'gender' => 'required',
		];

		$errors = kcValidateRequest( $rules, $request_data );

		if ( count( $errors ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => $errors[0]
			] );
		}

		$step = 'setup_step_4' ;

		$temp = [
            'mobile_number'  => str_replace(" ","",$request_data['mobile_number']),
			'gender'         => $request_data['gender'],
			'dob'            => $request_data['dob'],
			'address'        => $request_data['address'],
			'city'           => $request_data['city'],
			'state'          => $request_data['state'],
			'country'        => $request_data['country'],
			'postal_code'    => $request_data['postal_code']
		];

		if ( ! isset( $request_data['ID'] ) || $request_data['ID'] === null || $request_data['ID'] === '') {

			$request_data['username'] = kcGenerateUsername( $request_data['first_name']) ;

            $request_data['user_pass'] = kcGenerateString(12);

			$user = wp_create_user( $request_data['username'] , $request_data['user_pass'] , sanitize_email( $request_data['user_email']) );

			$u    = new WP_User( $user );

			$u->display_name = $request_data['first_name'] . ' ' . $request_data['last_name'];

			wp_insert_user($u);

			$u->set_role( $this->getReceptionistRole() );

			if($user) {

				$receptionist_mapping = new KCReceptionistClinicMapping;

				$new_temp = [
					'receptionist_id' => (int)$user,
					'clinic_id' => kcGetDefaultClinicId(),
					'created_at'=> current_time('Y-m-d H:i:s')
				];

				$receptionist_mapping->insert($new_temp);

				$user_email_param = array (
					'username' => $request_data['username'],
					'user_email' => sanitize_email( $request_data['user_email'] ),
					'password' => $request_data['user_pass'],
					'email_template_type' => 'receptionist_registration'
				);

				kcSendEmail($user_email_param);
			}

			update_user_meta( $user, 'first_name', $request_data['first_name'] );
			update_user_meta( $user, 'last_name', $request_data['last_name'] );
			update_user_meta( $user, 'basic_data', json_encode( $temp ) );

			$doctor_ids = [];

			foreach ( $u as $key => $val ) {
				if ( $val->ID !== '' && $val->ID !== null ) {
					array_push( $doctor_ids, (int) $val->ID );
				}
			}

			$current_step_status = get_option( $step );

			if ( $current_step_status ) {

				$step_detail = json_decode( $current_step_status );

				delete_option( $step );

				array_push( $step_detail->id, $doctor_ids[0] );

			} else {

				$step_detail = array( 'step' => 4, 'name' => 'receptionist', 'id' => $doctor_ids, 'status' => true );

			}

			$encoded_status_data = json_encode($step_detail);

			add_option($step, $encoded_status_data);

		} else {
            $request_data['ID'] = (int)$request_data['ID'];
			wp_update_user(
				array(
					'ID'         => $request_data['ID'],
					'user_login' => $request_data['username'],
					'user_email' => $request_data['user_email'] ,
					'display_name' => $request_data['first_name'] . ' ' . $request_data['last_name']
				)
			);

			update_user_meta( $request_data['ID'], 'basic_data', json_encode( $temp ) );
		}

		if ( !empty($user->errors) ) {

			wp_send_json( [
				'status'  => false,
				'message' => $user->get_error_message() ? $user->get_error_message() : esc_html__(' Failed to save Receptionist data', 'kc-lang')
			] );

		} else {

			wp_send_json( [
				'status'  => true,
				'message' => esc_html__('Receptionist saved successfully', 'kc-lang'),
			] );

		}

	}

	public function setupFinish () {

		$role_activate = new KCActivate();

		$is_role_activated = $role_activate->migratePermissions();

		if($is_role_activated) {
			wp_send_json( [
				'status'  => true,
				'message' => esc_html__('Clinic Setup steps is completed successfully.', 'kc-lang'),
			] );

		} else {
			wp_send_json( [
				'status'  => false,
				'message' => esc_html__('Failed to complete Clinic setup.', 'kc-lang'),
			] );
		}

	}

	public function updateSetupStep() {

        $request_data = $this->request->getInputs();

        $setup_config = collect(kcGetStepConfig());

        $setup_config = $setup_config->map(function ($step) use ($request_data) {
            if ($request_data['name'] === $step->name) {
                $step->completed = true;
            }
            return $step;
        });

        update_option($this->getSetupConfig(), json_encode($setup_config->toArray()));

		wp_send_json( [
            'status'  => true,
            'message' => esc_html__('Completed step.', 'kc-lang'),
            'data' => $request_data
        ] );

    }

    public function resetDoctorSession ($doctor_id) {

	    $clinic_id = kcGetDefaultClinicId();
	    $clinic_session = new KCClinicSession();
	    $clinic_session->delete(['clinic_id' => $clinic_id, 'doctor_id' => (int)$doctor_id]);

	    $setup_config = collect(kcGetStepConfig());

	    $setup_config = $setup_config->map(function ($step) {
		    if ($step->name === 'clinic_session') {
			    $step->completed = false;
		    }
		    return $step;
	    });

	    update_option($this->getSetupConfig(), json_encode($setup_config->toArray()));

    }

    public function createDemoUser($request_data){
	    global $wpdb;
	    if(!empty($request_data['selected_demo_user']) && count($request_data['selected_demo_user']) > 0){
	        foreach ($request_data['selected_demo_user'] as $type){
                $email = $type.'@kivicare.com';
                $explode_name = explode("_",$type);
                $username = str_replace("_","",$type);
                if (!email_exists($email)) {
                    // Create the new user
                    $user = wp_create_user($username, $request_data['user_pass'], $email);

                    $u = new WP_User($user);

                    $u->display_name = (!empty($explode_name[0]) ? $explode_name[0] : $type) . ' ' . (!empty($explode_name[1]) ? $explode_name[1] : '' );

                    wp_insert_user($u);

                    $user_id = $u->ID;

                    update_user_meta($user_id, 'first_name', !empty($explode_name[0]) ? $explode_name[0] : $type);
                    update_user_meta($user_id, 'last_name', !empty($explode_name[1]) ? $explode_name[1] : '' );

                    switch($type){
                        case $this->getDoctorRole():
                            $u->set_role($this->getDoctorRole());
                            $new_temp = [
                                'doctor_id' => (int)$user_id,
                                'clinic_id' => kcGetDefaultClinicId(),
                                'owner' => 0,
                                'created_at' => current_time('Y-m-d H:i:s')
                            ];
                            $specialties = collect($wpdb->get_results("SELECT id ,label FROM {$wpdb->prefix}kc_static_data WHERE type='specialization' LIMIT 2"))->toArray();
                            if(!empty($specialties)){
                                $temp = [
                                    'specialties' => $specialties,
                                    'mobile_number' => '7655525750',
                                    'gender' => 'male'
                                ];
                                self::save_demo_user_meta( $user_id, $temp );
                            }
                            $wpdb->insert($wpdb->prefix.'kc_doctor_clinic_mappings',$new_temp);

                            $service_data = [
                                'name'   => __('Demo Service','kc-lang'),
                                'price'  => 100,
                                'type'   => 'general_dentistry',
                                'status' => 1,
                            ];

                            $wpdb->insert($wpdb->prefix.'kc_services',$service_data);

                            $service_mapping_data = [
                                'service_id' => $wpdb->insert_id,
                                'clinic_id'  => kcGetDefaultClinicId(),
                                'doctor_id'  => (int)$user_id,
                                'charges'    => 100,
                                'status'     => 1,
                            ];

                            $service_mapping_id = (new KCServiceDoctorMapping())->insert($service_mapping_data);
                            do_action('kc_service_add',$service_mapping_id);

                            //add doctor session

                            if(gettype(get_option('kivicare_session_first_time',true)) === 'boolean'){
                                global $wpdb;
                                $session['days'] = ['mon','tue','wed','thu','fri','sat','sun'];
                                $session['s_one_start_time'] = [
                                    "HH" => "00",
                                    "mm" => "00"
                                ];
                                $session['s_one_end_time'] = [
                                    "HH" => "23",
                                    "mm" => "55"
                                ];
                                $session['s_two_start_time'] = [
                                    "HH" => "",
                                    "mm" => ""
                                ];
                                $session['s_two_end_time'] = [
                                    "HH" => "",
                                    "mm" => ""
                                ];

                                $parent_id = 0;

                                foreach ($session['days'] as $day) {

                                    $result = 0;

                                    $start_time = date('H:i:s', strtotime($session['s_one_start_time']['HH'] . ':' . $session['s_one_start_time']['mm']));
                                    $end_time = date('H:i:s', strtotime($session['s_one_end_time']['HH'] . ':' . $session['s_one_end_time']['mm']));
                                    $session_temp = [
                                        'clinic_id' => kcGetDefaultClinicId(),
                                        'doctor_id' => (int)$user_id,
                                        'day' => substr($day, 0, 3),
                                        'start_time' => $start_time,
                                        'end_time' => $end_time,
                                        'time_slot' => 30,
                                        'created_at' => current_time('Y-m-d H:i:s'),
                                        'parent_id' => (int)$parent_id === 0 ? null : (int)$parent_id
                                    ];

                                    if ($parent_id === 0) {
                                        $wpdb->insert($wpdb->prefix . 'kc_clinic_sessions',$session_temp);
                                        $parent_id = $wpdb->insert_id;
                                    } else {
                                        $wpdb->insert($wpdb->prefix . 'kc_clinic_sessions',$session_temp);
                                        $result =  $wpdb->insert_id;
                                    }

                                }

                                update_option('kivicare_session_first_time','yes');
                            }
                            do_action('kc_doctor_save',$user_id);
                            break;
                        case $this->getReceptionistRole():
                            $u->set_role( $this->getReceptionistRole() );
                            $temp = [
                                'mobile_number' => '7455532406',
                                'gender' => 'male'
                            ];

                            self::save_demo_user_meta( $user_id, $temp );

                            $new_temp = [
                                'receptionist_id' => (int)$user_id,
                                'clinic_id'       => kcGetDefaultClinicId(),
                                'created_at'      =>   current_datetime('Y-m-d H:i:s' )
                            ];
                            $wpdb->insert($wpdb->prefix.'kc_receptionist_clinic_mappings',$new_temp);
                            do_action('kc_receptionist_save',$user_id);
                            break;
                        case $this->getPatientRole():
                            $u->set_role( $this->getPatientRole() );
                            $temp = [
                                'mobile_number' => '7155530711',
                                'gender' => 'male'
                            ];

                            self::save_demo_user_meta( $user_id, $temp );

                            $new_temp = [
                                'patient_id' => (int)$user_id,
                                'clinic_id'       => kcGetDefaultClinicId(),
                                'created_at'      =>   current_datetime('Y-m-d H:i:s' )
                            ];
                            $wpdb->insert($wpdb->prefix.'kc_patient_clinic_mappings',$new_temp);
                            do_action('kc_patient_save',$user_id);
                            break;
                    }
                }
            }
        }
    }

    public static function save_demo_user_meta( $user_id, $basic_data ){
        update_user_meta($user_id, 'basic_data', json_encode( $basic_data, JSON_UNESCAPED_UNICODE));
        update_user_meta($user_id, 'country_calling_code', '44');
        update_user_meta($user_id, 'country_code', 'GB');
    }

}

