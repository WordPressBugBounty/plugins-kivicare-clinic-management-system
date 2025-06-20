<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCAppointment;
use App\models\KCBillItem;
use App\models\KCClinicSession;
use App\models\KCClinic;
use App\models\KCPatientEncounter;
use App\models\KCAppointmentServiceMapping;
use App\controllers\KCPaymentController;

use DateTime;
use Exception;

class KCBookAppointmentWidgetController extends KCBase {

	public $db;

	private $request;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->request = new KCRequest();

        parent::__construct();
	}

	public function getDoctors () {
		$request_data = $this->request->getInputs();
		$doctor_role = $this->getDoctorRole();
		$table_name = $this->db->prefix . 'kc_doctor_clinic_mappings';
        $prefix = '';
        $postfix = '';
		
		$request_data['clinic_id'] =  kcRecursiveSanitizeTextField(json_decode( stripslashes( $request_data['clinic_id']), true));

		if( !empty($request_data['clinic_id']['id']) ){

            $request_data['clinic_id']['id'] = (int)$request_data['clinic_id']['id'];
            $doctor_condition = ' ';
            if(!empty($request_data['props_doctor_id']) && !in_array($request_data['props_doctor_id'],[0,'0'])){
                if(strpos($request_data['props_doctor_id'], ',') !== false){
                    $request_data['props_doctor_id'] = implode( ',',array_map('absint', explode(',', sanitize_text_field($request_data['props_doctor_id']))));
                    if(isKiviCareProActive()){
                        $doctor_condition = ' AND doctor_id IN ('.$request_data['props_doctor_id'].')';
                    }else{
                        $doctor_condition = ' WHERE ID IN ('.$request_data['props_doctor_id'].')';
                    }
                }else{
                    if(isKiviCareProActive()){
                        $doctor_condition = ' AND doctor_id ='.(int)$request_data['props_doctor_id'];
                    }else{
                        $doctor_condition = ' WHERE ID ='.(int)$request_data['props_doctor_id'];
                    }
                }
            }
            if(!empty($request_data['props_clinic_id']) && !in_array($request_data['props_clinic_id'],[0,'0'])){
                $request_data['clinic_id']['id'] = (int)$request_data['props_clinic_id'];
            }

            if(isKiviCareProActive()){
                $query = "SELECT `doctor_id` FROM {$table_name} WHERE `clinic_id` =".$request_data['clinic_id']['id'].$doctor_condition ;
            }else{
                $query = "SELECT `ID` FROM {$this->db->base_prefix}users {$doctor_condition}" ;
            }

            if(isKiviCareProActive()){
                $result = collect($this->db->get_results($query))->unique('doctor_id')->pluck('doctor_id');
            }else{
                $result = collect($this->db->get_results($query))->unique('ID')->pluck('ID');
            }

			$users = get_users([ 'role' => $doctor_role ,'user_status' => '0']);
			$users = collect($users)->whereIn('ID',$result)->values();
            $prefix_postfix = $this->db->get_var('select extra from '.$this->db->prefix.'kc_clinics where id='.$request_data['clinic_id']['id']);
		    if($prefix_postfix != null){
                $prefix_postfix = json_decode( $prefix_postfix);
                $prefix = isset($prefix_postfix->currency_prefix) ? $prefix_postfix->currency_prefix : '';
                $postfix = isset($prefix_postfix->currency_postfix) ? $prefix_postfix->currency_postfix : '';
            }
        }
		$results = [];
		if (!empty($users) && count($users) > 0) {
			foreach ($users as $key => $user) {
				$image_attachment_id = get_user_meta($user->ID,'doctor_profile_image',true);
				$user_image_url = wp_get_attachment_url($image_attachment_id);
				$results[$key]['id'] = $user->ID;
				$results[$key]['display_name'] = $user->data->display_name;
				$user_data = get_user_meta($user->ID, 'basic_data', true);
				if ($user_data) {
					$user_data = json_decode($user_data);
					$results[$key]['description'] = get_user_meta($user->ID, 'doctor_description', true);
					$results[$key]['address'] = isset($user_data->address) ? $user_data->address : "";
					$results[$key]['city'] = isset($user_data->city) ? $user_data->city : "";
					$results[$key]['state'] = isset($user_data->state) ? $user_data->state : "";
					$results[$key]['country'] = isset($user_data->country) ? $user_data->country : "";
                    $results[$key]['currency'] = '';
					$results[$key]['postal_code'] = isset($user_data->postal_code) ? $user_data->postal_code : "";
					$results[$key]['timeSlot'] = isset($user_data->time_slot) ? $user_data->time_slot : "";
					$results[$key]['price'] = isset($user_data->price) ? $user_data->price : "";
					$results[$key]['gender'] = isset($user_data->gender) ? $user_data->gender : "";
					$results[$key]['qualifications'] = isset($user_data->qualifications) ? $user_data->qualifications : "";
					$results[$key]['specialties'] = isset($user_data->specialties) ? $user_data->specialties : [];
					$results[$key]['enableTeleMed'] = false;
					$results[$key]['custom_fields'] = kcGetCustomFields('appointment_module', $user->ID);
					$results[$key]['user_profile'] =$user_image_url;
                    $results[$key]['contact_no'] = !empty($user_data->mobile_number) ? $user_data->mobile_number : '';
                    $results[$key]['dob'] = !empty($user_data->dob) ? $user_data->dob : '';
                    $results[$key]['specialties_all'] = !empty($user_data->specialties) &&  is_array($user_data->specialties)? collect($user_data->specialties)->pluck('label')->implode(',') : [];
                    $results[$key]['full_address'] = (!empty($user_data->address) ? $user_data->address : "").','.(!empty($user_data->city) ? $user_data->city : '').','.
                        (!empty($user_data->state) ? $user_data->state : "").','.(!empty($user_data->country) ? $user_data->country : "");
                    $results[$key]['enableTeleMed'] = kcDoctorTelemedServiceEnable($user->ID);
				}
			}
			wp_send_json([
				'status' => true,
				'message' => __('Doctor details', 'kc-lang'),
				'data' => $results,
                'prefix' => $prefix,
                'postfix' =>$postfix ,
			]);
		}else{
			wp_send_json([
				'status' => false,
				'message' => __('Doctor details', 'kc-lang'),
				'data' => []
			]);
		}

		

	}
	public function getClinic () {
        $response = apply_filters('kcpro_get_clinic_data',['data' => $this->request->getInputs()]);
		wp_send_json($response);
	}

	public function getTimeSlots() {

		$formData = $this->request->getInputs();;

		$timeSlots = kvGetTimeSlots([
			'date' => $formData['date'],
			'doctor_id' => $formData['doctor_id'],
			'clinic_id' => $formData['clinic_id'],
            'service' => $formData['service'],
            "widgetType" => 'phpWidget'
		], "", true);
        $htmldata = '';
		if (count($timeSlots)) {
			$status = true;
			$message = __('Time slots', 'kc-lang' );
            if(!empty($formData['widgetType']) && $formData['widgetType'] === 'phpWidget'){
                ob_start();
                foreach ($timeSlots as $sessions){
                    foreach ($sessions as $time_slot){
                        ?>
                        <div class="iq-client-widget iq-time-slot">
                            <input type="radio" class="card-checkbox selected-time" name="card_main" id="time_slot_<?php echo esc_html($time_slot['time']); ?>" value="<?php echo esc_html($time_slot['time']) ; ?>">
                            <label class="iq-button iq-button-white" for="time_slot_<?php echo esc_html($time_slot['time']); ?>">
                                <?php echo esc_html($time_slot['time']); ?>
                            </label>
                        </div>
                        <?php
                    }
                }
                $htmldata = ob_get_clean();
            }
		} else {
			$status = false;
			$message = __('Doctor is not available for this date', 'kc-lang' );
		}

		wp_send_json( [
			'status'      => $status,
			'message'     => $message,
			'data'     => $timeSlots,
            'html' => $htmldata
		] );

	}

	public function saveAppointment() {

        if(!is_user_logged_in()) {
	        wp_send_json([
                "status" => false,
                "message" => __('Sign in to book appointment', 'kc-lang')
            ]);
        }
        if ($this->getPatientRole() !== $this->getLoginUserRole()) {
	        wp_send_json([
                    "status" => false,
                "message" => __('User must be patient to book appointment', 'kc-lang')
            ]);
        }

		global $wpdb;

		$formData = $this->request->getInputs();       
        $proPluginActive = isKiviCareProActive();
        $telemedZoomPluginActive = isKiviCareTelemedActive();
        $telemedGooglemeetPluginActive = isKiviCareGoogleMeetActive();

		try {
            //check if service is single or multiple, if single create array
            if(empty(array_filter($formData['visit_type'], 'is_array'))){
                $formData['visit_type'] = [$formData['visit_type']];
            };
            $clinic_id = (int)(isset($formData['clinic_id']['id']) ? $formData['clinic_id']['id']: kcGetDefaultClinicId());
            $formData['doctor_id']['id'] = (int)$formData['doctor_id']['id'];
            $appointment_day = strtolower(date('l', strtotime($formData['appointment_start_date']))) ;
            $day_short = substr($appointment_day, 0, 3);

            $doctor_time_slot = $wpdb->get_var("SELECT time_slot FROM {$wpdb->prefix}kc_clinic_sessions  
				WHERE `doctor_id` = {$formData['doctor_id']['id']} AND `clinic_id` ={$clinic_id}  
				AND ( `day` = '{$day_short}' OR `day` = '{$appointment_day}') ");

            $time_slot             = !empty($doctor_time_slot) ? $doctor_time_slot : 15;

			$end_time             = strtotime( "+" . $time_slot . " minutes", strtotime( $formData['appointment_start_time'] ) );
			$appointment_end_time = date( 'H:i:s', $end_time );
			$appointment_date     = date( 'Y-m-d', strtotime( $formData['appointment_start_date'] ) );
            $appointment_start_time = date('H:i:s', strtotime($formData['appointment_start_time']));           
            if(isKiviCareProActive()){
                $verifyTimeslot = apply_filters('kcpro_verify_appointment_timeslot',$formData);
                if(is_array($verifyTimeslot) && array_key_exists('end_time',$verifyTimeslot) && !empty($verifyTimeslot['end_time'])){
                    if(empty($verifyTimeslot['status'])){
	                    wp_send_json($verifyTimeslot);
                    }
                    $appointment_end_time = date( 'H:i:s', $verifyTimeslot['end_time'] );
                }
            }
            if(isset($formData['payment_mode']) && $formData['payment_mode'] !== 'paymentOffline'){
                $formData['status'] = 0;
            }
			// appointment data
            $tempAppointmentData = [
                'appointment_start_date' => $appointment_date,
                'appointment_start_time' => $appointment_start_time,
                'appointment_end_date' => $appointment_date,
                'appointment_end_time' => $appointment_end_time,
                'visit_type' => $formData['visit_type'],
                'clinic_id' => $clinic_id,
                'doctor_id' => $formData['doctor_id']['id'],
                'patient_id' => get_current_user_id(),
                'description' => $formData['description'],
                'status' => $formData['status'],
                'created_at' => current_time('Y-m-d H:i:s')
            ];


            if(isset($formData['file']) && is_array($formData['file']) && count($formData['file']) > 0){
                kcUpdateFields($wpdb->prefix . 'kc_appointments',[ 'appointment_report' => 'longtext NULL']);
                $tempAppointmentData['appointment_report'] = json_encode($formData['file']);
            }

			$patient_appointment_id = (new KCAppointment())->insert($tempAppointmentData);

            if($patient_appointment_id) {
                $formData['id'] = $patient_appointment_id;
                if (isset($formData['custom_fields']) && $formData['custom_fields'] !== []) {
                    kvSaveCustomFields('appointment_module',$patient_appointment_id, $formData['custom_fields']);
                }
                if( !empty($formData['tax'])){
                    apply_filters('kivicare_save_tax_data', [
                        'type' => 'appointment',
                        'id' => $patient_appointment_id,
                        'tax_data' => $formData['tax']
                    ]);
                }
                $message = __('Appointment has been booked successfully', 'kc-lang');
                $status  = true ;
            } else {
                $message = __('Appointment booking failed.', 'kc-lang');
                $status  = false ;
            }

            $doctorTelemedType = kcCheckDoctorTelemedType($patient_appointment_id);
            $notification = '';
            $telemed_service_include = false;
            $all_appointment_service_name = [];
            if (gettype($formData['visit_type']) === 'array') {
                $telemed_service_in_appointment_service = collect($formData['visit_type'])->map(function ($v)use($formData,$clinic_id){
                    $temp_service_id = (int)$v['service_id'];
                    return $this->db->get_var("SELECT telemed_service FROM {$this->db->prefix}kc_service_doctor_mapping WHERE service_id = {$temp_service_id} AND clinic_id={$clinic_id} AND doctor_id=".(int)$formData['doctor_id']['id']);
                })->toArray();
                foreach ($formData['visit_type'] as $key => $value) {
                    $service = strtolower($value['name']);
                    $all_appointment_service_name[] = $service;            
                    if ($value['telemed_service'] === 'yes') {
                        if ($telemedZoomPluginActive || $telemedGooglemeetPluginActive) {
                            $formData['appointment_id'] = $patient_appointment_id;
							$formData['time_slot'] = $time_slot;
                            
                            if($formData['payment_mode'] !== 'paymentWoocommerce'){
                                if($doctorTelemedType == 'googlemeet'){
                                    $telemed_res_data = apply_filters('kcgm_save_appointment_event',['appoinment_id' => $patient_appointment_id,'service' => kcServiceListFromRequestData($formData)]);
                                }else{
                                    $telemed_res_data = apply_filters('kct_create_appointment_meeting', $formData);
                                }
                                if(empty($telemed_res_data['status'])) {
                                    ( new KCAppointmentServiceMapping() )->delete( [ 'appointment_id' =>  (int)$patient_appointment_id] );
                                    ( new KCAppointment() )->delete( [ 'id' =>  (int)$patient_appointment_id] );
                                    do_action('kc_appointment_cancel',$patient_appointment_id);
                                    wp_send_json([
                                        'status'  => false,
                                        'message' => __('Failed to generate Video Meeting.', 'kc-lang'),
                                    ]);
                                }
                                // send zoom link
                                $telemed_service_include = true;
                            }
                        }
                    }

                    if($patient_appointment_id) {
                        (new KCAppointmentServiceMapping())->insert([
                            'appointment_id' => (int)$patient_appointment_id,
                            'service_id' => (int)$value['service_id'],
                            'created_at' => current_time('Y-m-d H:i:s'),
							'status' => 1
                        ]);
                    }
                }      
            }

			if ( in_array((string)$formData['status'],['2','4'])) {
				KCPatientEncounter::createEncounter( $patient_appointment_id );
                KCBillItem::createAppointmentBillItem($patient_appointment_id );
			}

            if(!empty($patient_appointment_id) && $patient_appointment_id !== 0) {
                // hook for appointment booked
                do_action( 'kc_appointment_book', $patient_appointment_id );
            }
            $formData['calender_content'] = '';
            if($proPluginActive && $formData['status'] == '1'){

                $clinic_data = $this->db->get_row("SELECT name, CONCAT(address, ', ',city,', '
		           ,postal_code,', ',country) AS clinic_full_address FROM {$this->db->prefix}kc_clinics WHERE id={$clinic_id}");

                $appointment_data = [
                    "clinic_name" => !empty($clinic_data->name) ? $clinic_data->name : '',
                    "clinic_address" =>  !empty($clinic_data->clinic_full_address) ? $clinic_data->clinic_full_address : '',
                    "id" => $patient_appointment_id,
                    "start_date" => $appointment_date,
                    "start_time" =>$appointment_start_time,
                    "end_date" => $appointment_date,
                    "end_time" => $appointment_end_time,
                    "appointment_service" =>implode(",",$all_appointment_service_name),
                    "extra" => $formData
                ];

                $formData['calender_content'] = kcAddToCalendarContent($appointment_data);
            }
            switch($formData['payment_mode']){
                case 'paymentWoocommerce':
                    $woocommerce_response  = kcWoocommerceRedirect($patient_appointment_id, $formData);
                    if(isset($woocommerce_response['status']) && $woocommerce_response['status']) {
                        if(!empty($woocommerce_response['woocommerce_cart_data'])) {
	                        wp_send_json($woocommerce_response);
                        }
                    }
                    break;
                case 'paymentPaypal':
                    $this->db->update($this->db->prefix."kc_appointments",['status' => 0],['id' => $patient_appointment_id]);
                    $paypal_response = (new KCPaymentController())->makePaypalPayment($formData,$patient_appointment_id);
                    if(empty($paypal_response['status'])) {
                        (new KCAppointment())->loopAndDelete(['id' => $patient_appointment_id],true);
                    }
                    $paypal_response['appointment_id'] = $patient_appointment_id;
                    $paypal_response['data'] = $formData;
	                wp_send_json($paypal_response);
                    break;
                case 'paymentStripepay':
                    $this->db->update($this->db->prefix."kc_appointments",['status' => 0],['id' => $patient_appointment_id]);
                    $stripepay_response = apply_filters('kivicare_create_stripepay_order',[],$formData,$patient_appointment_id);
                    if(empty($stripepay_response['status'])) {
                        (new KCAppointment())->loopAndDelete(['id' => $patient_appointment_id],true);
                    }
                    $stripepay_response['appointment_id'] = $patient_appointment_id;
                    $stripepay_response['data'] = $formData;
                    wp_send_json($stripepay_response);
                    break;
                case 'paymentRazorpay':
                    $this->db->update($this->db->prefix."kc_appointments",['status' => 0],['id' => $patient_appointment_id]);
                    $formData['appointment_id'] = $patient_appointment_id;
                    $formData['page'] = 'dashboard';
                    $razorpay_response = apply_filters('kivicare_create_razorpay_order',$formData);
                    if(is_array($razorpay_response) && array_key_exists('checkout_detail',$razorpay_response) && !empty($razorpay_response['status'])){
                        $razorpay_response['appointment_id'] = $patient_appointment_id;
                        $razorpay_response['data'] = $formData;
	                    wp_send_json($razorpay_response);
                    }else{
                        (new KCAppointment())->loopAndDelete(['id' => $patient_appointment_id],true);
                        wp_send_json(['status' => false,
                            'message' => esc_html__('Failed to create razorpay payment link','kc-lang'),
                            'error_message' => is_array($razorpay_response) && !empty($razorpay_response['message']) ? $razorpay_response['message'] : '']);
                    }
                    break;
                case 'paymentOffline':
                    $service_name = kcServiceListFromRequestData($formData);
                    if($proPluginActive || $telemedZoomPluginActive || $telemedGooglemeetPluginActive){
                        $notification = kcProAllNotification($patient_appointment_id,$service_name,$telemed_service_include);
                    }else{
                        $notification = kivicareCommonSendEmailIfOnlyLitePluginActive($patient_appointment_id,$service_name);
                    }
                    break;
            }

			wp_send_json([
				'status'      => $status,
				'message'     => $message,
				'data' 		  => $formData,
                'notification' =>$notification,
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

	public function getClinicSelectedArray () {
		$request_data = $this->request->getInputs();
        $table_name = $this->db->prefix . 'kc_doctor_clinic_mappings';
        $preselected_doctor = '';
        if(!empty($request_data['preselected_doctor'])){
            $preselected_doctor = implode(',',array_filter(array_map('absint',explode(',',$request_data['preselected_doctor']))));
        }
        if(!empty($request_data['service_id'])){
            $request_data['service_id'] = implode(",",array_map('absint',$request_data['service_id']));
            $doctor_preselect_condition = ' ';
            if(!empty($preselected_doctor)){
                $doctor_preselect_condition = " AND doctor_id IN ($preselected_doctor) ";
            }
            $service_doctor = collect($this->db->get_results("SELECT doctor_id FROM {$this->db->prefix}kc_service_doctor_mapping WHERE service_id IN ({$request_data['service_id']}) {$doctor_preselect_condition}"))->pluck('doctor_id')->toArray();
            if(!empty($service_doctor) && !empty($request_data['clinic_id']) && !in_array($request_data['clinic_id'],['0',0])){
                $request_data['clinic_id'] = (int)$request_data['clinic_id'];
                $service_doctor = implode(",",$service_doctor);
                $result = collect($this->db->get_results("SELECT doctor_id FROM {$table_name} WHERE doctor_id IN ({$service_doctor}) AND clinic_id = {$request_data['clinic_id']}"))->unique('doctor_id')->pluck('doctor_id')->toArray();
            }else{
                $result =  $service_doctor;
            }
            $all_service_name = collect($this->db->get_results("SELECT telemed_service from {$this->db->prefix}kc_service_doctor_mapping WHERE service_id IN ({$request_data['service_id']}) "))->pluck('telemed_service')->toArray();
            if(in_array('yes',$all_service_name)){
                $result = array_filter($result,function($v){
                    return kcDoctorTelemedServiceEnable($v);
                });
            }
        }else{
            $doctor_preselect_condition = ' ';
            if(!empty($preselected_doctor)){
                $doctor_preselect_condition = " AND doctor_id IN ($preselected_doctor) ";
            }
            if(!empty($request_data['clinic_id']) && !in_array($request_data['clinic_id'],['0',0])){
                $clinic_id = (int)$request_data['clinic_id'];
                $query = "SELECT `doctor_id` FROM {$table_name} WHERE `clinic_id` = {$clinic_id} {$doctor_preselect_condition}" ;
            }else{
                $query = "SELECT `doctor_id` FROM {$table_name} WHERE 0=0 {$doctor_preselect_condition}";
            }
            $result = collect($this->db->get_results($query))->unique('doctor_id')->pluck('doctor_id')->toArray();
        }
        $defaultQueryOption =  [
            'role' => $this->getDoctorRole(),
            'user_status' => '0',
            'orderby' => 'display_name',
            'include' => !empty($result) ? $result : [-1]
        ];


        ob_start();
        $users = collect(get_users($defaultQueryOption))->map(function ($v) use ($request_data) {
            $v->user_image = $v->basic_data = $v->description = $v->mobile_number = $v->specialties_all = '';
            $v->no_of_experience = 0;
            $v->telemed = false;
            if (!empty($v->data->ID)) {
                $v->telemed = kcDoctorTelemedServiceEnable($v->data->ID);
                $allUserMetaData = get_user_meta( $v->data->ID);                
                $v->user_image = !empty($allUserMetaData['doctor_profile_image'][0]) ? wp_get_attachment_url($allUserMetaData['doctor_profile_image'][0]) : '';
                $v->description = !empty($allUserMetaData['doctor_description'][0]) ? $allUserMetaData['doctor_description'][0] : '';
                $user_data  = !empty($allUserMetaData['basic_data'][0]) ? $allUserMetaData['basic_data'][0] : [];
                if (!empty($user_data)) {
                    $user_data = json_decode($user_data);
                    $v->specialties_all = !empty($user_data->specialties) ? collect($user_data->specialties)->pluck('label')->implode(',') : '';
                    $v->mobile_number = !empty($user_data->mobile_number) ? $user_data->mobile_number : '';
                    $v->no_of_experience = !empty($user_data->no_of_experience) ? $user_data->no_of_experience : 0;
                    $v->qualifications = !empty($user_data->qualifications) ? collect($user_data->qualifications)->pluck('degree')->implode(',') : '';
                }
            }
            if (!empty($request_data['searchKey'])) {
                $searchKey = mb_strtolower($request_data['searchKey']);
                if (strpos(mb_strtolower($v->data->user_email), $searchKey) !== false
                    || strpos(mb_strtolower($v->data->display_name), $searchKey) !== false
                    || strpos(mb_strtolower($v->specialties_all), $searchKey) !== false
                    || strpos(strtolower($v->mobile_number), $searchKey) !== false) {
                     $this->doctorHtmlContent($v);
                    return $v;
                };
            } else {
                $this->doctorHtmlContent($v);
                return $v;
            }
        })->values()->toArray();        


        $users = array_filter($users);

        if (empty($users)) {
	        wp_send_json([
                'status'      => false,
                'data' 		  => __('No Doctor Available For This Clinic', 'kc-lang'),
            ] );
        }

		wp_send_json([
            'status'      => true,
            'data' 		  => ob_get_clean()
        ] );

	}

    public function getClinicArray(){

        $request_data = $this->request->getInputs();
        $searchKey = esc_sql($request_data['searchKey']);
        $clinic_selected_condition = ' ';
        $clinics_table = $this->db->prefix . 'kc_clinics';
	    $clinic_doctor_table = $this->db->prefix . 'kc_doctor_clinic_mappings';

        if(!isKiviCareProActive()){
            $clinics[] = kcGetDefaultClinicId();
        }else{
            $clinics = collect($this->db->get_results("SELECT id FROM {$clinics_table} "))->pluck('id')->toArray();
        }

        $preselected_clinic = '';
        if(!empty($request_data['preselected_clinic'])){
            $preselected_clinic = array_filter(array_map('absint',explode(',',$request_data['preselected_clinic'])));
            $clinics = $preselected_clinic;
        }

	    if (!empty($request_data['doctor_id'])) {
		    $doctor_id = (int)$request_data['doctor_id'];
		    $clinics = collect($this->db->get_results("SELECT clinic_id FROM {$clinic_doctor_table} WHERE doctor_id = {$doctor_id} "))->pluck('clinic_id')->toArray();
	    }
	    if(!empty($request_data['service_id'])){
		    $request_data['service_id'] = implode(",",array_map('absint',$request_data['service_id']));
		    $doctor_condition = !empty($request_data['doctor_id']) ? " AND doctor_id=".(int)$request_data['doctor_id']." " : ' ';
		    $clinics = collect($this->db->get_results("SELECT clinic_id 
            FROM {$this->db->prefix}kc_service_doctor_mapping
            WHERE service_id IN ({$request_data['service_id']}) {$doctor_condition} "))->pluck('clinic_id')->toArray();
	    }
        if(!empty($clinics)){
            $clinics = array_unique($clinics);
            $clinics = implode(',',$clinics);
            $clinic_selected_condition = " AND id IN ({$clinics}) ";
        }
        $query = "SELECT *,CONCAT(address, ', ', city,', ',postal_code,', ',country) AS clinic_full_addres,
       email AS user_email,telephone_no AS mobile_number  FROM {$clinics_table} WHERE status='1' 
       {$clinic_selected_condition} AND ( name like '%$searchKey%' OR email LIKE '%$searchKey%' OR telephone_no LIKE '%$searchKey%' OR city LIKE '%$searchKey%' OR postal_code LIKE '%$searchKey%' OR address LIKE '%$searchKey%' OR country LIKE '%$searchKey%' OR specialties LIKE '%$searchKey%' OR address LIKE '%$searchKey%') ORDER BY name";
        $clinicList = $this->db->get_results($query);
        if (empty($clinicList)) {
	        wp_send_json([
                'status' => false,
                'data' => '',
            ]);
        }
        ob_start();
        foreach ($clinicList as $clinic) {
            ?>
            <div class="iq-client-widget">
                <input type="radio" class="card-checkbox selected-clinic" name="card_main"
                       id="clinic_<?php echo esc_html($clinic->id); ?>" value="<?php echo esc_html($clinic->id); ?>"
                       clinicName="<?php echo esc_html($clinic->name); ?>"
                       clinicAddress="<?php echo esc_html($clinic->clinic_full_addres); ?>">
                <label class="btn-border01 w-100" for="clinic_<?php echo esc_html($clinic->id); ?>">
                    <div class="iq-card iq-card-lg iq-fancy-design iq-card-border">

                        <?php
                        if (kcGetSingleWidgetSetting('showClinicImage')) {
                            ?>
                            <div class="d-flex justify-content-center align-items-center">
                                <img src="<?php echo esc_url(!empty($clinic->profile_image) ? wp_get_attachment_url($clinic->profile_image) : KIVI_CARE_DIR_URI . '/assets/images/kc-demo-img.png'); ?>"
                                     class="avatar-90 rounded-circle object-cover"
                                     alt="<?php echo esc_html($clinic->id); ?>">
                            </div>

                            <?php
                        }
                        ?>
                        <h3 class="kc-clinic-name"><?php echo esc_html($clinic->name); ?></h3>
                        <?php
                        if (kcGetSingleWidgetSetting('showClinicAddress')) {

                            ?>
                            <p class="kc-clinic-address">  <?php echo esc_html($clinic->clinic_full_addres) ?>
                                <a class="" target="_blank" href="<?php echo add_query_arg( 'q', $clinic->clinic_full_addres,'https://www.google.com/maps' ); ?>">
                                    <svg xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" viewBox="0 -256 1850 1850" id="svg3025" version="1.1" inkscape:version="0.48.3.1 r9886" width="20px" height="20px" sodipodi:docname="external_link_font_awesome.svg">
                                      <g transform="matrix(1,0,0,-1,30.372881,1426.9492)" id="g3027">
                                        <path fill="var(--iq-primary)" d="M 1408,608 V 288 Q 1408,169 1323.5,84.5 1239,0 1120,0 H 288 Q 169,0 84.5,84.5 0,169 0,288 v 832 Q 0,1239 84.5,1323.5 169,1408 288,1408 h 704 q 14,0 23,-9 9,-9 9,-23 v -64 q 0,-14 -9,-23 -9,-9 -23,-9 H 288 q -66,0 -113,-47 -47,-47 -47,-113 V 288 q 0,-66 47,-113 47,-47 113,-47 h 832 q 66,0 113,47 47,47 47,113 v 320 q 0,14 9,23 9,9 23,9 h 64 q 14,0 23,-9 9,-9 9,-23 z m 384,864 V 960 q 0,-26 -19,-45 -19,-19 -45,-19 -26,0 -45,19 L 1507,1091 855,439 q -10,-10 -23,-10 -13,0 -23,10 L 695,553 q -10,10 -10,23 0,13 10,23 l 652,652 -176,176 q -19,19 -19,45 0,26 19,45 19,19 45,19 h 512 q 26,0 45,-19 19,-19 19,-45 z" id="path3029" inkscape:connector-curvature="0" style="fill:currentColor"/>
                                      </g>
                                    </svg>
                                </a>
                            </p>
                            <?php
                        }
                        $this->widgetUserProfileCardExtraDetail('clinic',$clinic)
                        ?>
                    </div>
                </label>
            </div>
            <?php
        }
	    wp_send_json([
            'status' => true,
            'data' => ob_get_clean(),
        ]);
    }
    public function appointmentConfirmPage(){
        $request_data = $this->request->getInputs();
        
        $field_id = 0;
        $label_list = [];
        if(!empty($request_data['custom_field']) && count($request_data['custom_field'])){
            foreach($request_data['custom_field'] as $custom_key => $custom){
                $field_id = (int)str_replace("custom_field_","",$custom_key);
                $query = "SELECT fields FROM {$this->db->prefix}kc_custom_fields WHERE id = {$field_id}";
                $label_list[$custom_key] = collect($this->db->get_results($query))->pluck('fields')->map(function($x) use($custom){
                    return !empty(json_decode($x)->label) ? json_decode($x)->label : '';
                })->toArray();
            }
        }
        
        $request_data['doctor_id'] = (int)$request_data['doctor_id'];
        $request_data['clinic_id'] = (int)$request_data['clinic_id'];
        $doctor_name =  $this->db->get_var("SELECT display_name FROM {$this->db->base_prefix}users WHERE ID = {$request_data['doctor_id']}");
        $request_data['service_list_data'] = $request_data['service_list'];
        $request_data['service_list_data'] = array_map('absint', $request_data['service_list_data']);
        $request_data['service_list'] = implode(",",array_map('absint',$request_data['service_list']));
        $request_data['tax_details'] = apply_filters('kivicare_calculate_tax',['status' => false,
        'message' => '',
        'data' => []
        ],[
            "id" => '',
            "type" => 'appointment',
            "doctor_id" => $request_data['doctor_id'],
            "clinic_id" => $request_data['clinic_id'],
            "service_id" => $request_data['service_list_data'],
            "total_charge" => $this->db->get_var("SELECT SUM(charges) FROM {$this->db->prefix}kc_service_doctor_mapping
                                        WHERE doctor_id = {$request_data['doctor_id']} AND  clinic_id = {$request_data['clinic_id']} 
                                         AND service_id IN ({$request_data['service_list']}) " ),
            'extra_data' => $request_data
        ]);
        $patient_id = get_current_user_id();
        $patient_data = $this->db->get_row("SELECT * FROM {$this->db->base_prefix}users WHERE ID = {$patient_id}");
        $clinic_currency_detail = kcGetClinicCurrenyPrefixAndPostfix();
        $patient_basic_data = json_decode(get_user_meta($patient_id,'basic_data',true));

        $service_list_data = $this->db->get_results("SELECT service.*, doctor_service.charges FROM {$this->db->prefix}kc_services AS service 
                                                        LEFT JOIN {$this->db->prefix}kc_service_doctor_mapping AS doctor_service ON doctor_service.service_id = service.id
                                                        WHERE service.id IN ({$request_data['service_list']} ) AND doctor_service.clinic_id= {$request_data['clinic_id']}
                                                          AND  doctor_service.doctor_id={$request_data['doctor_id']}");

        $name = $address= '';
        $patient_country_calling_code  = get_user_meta($patient_id, 'country_calling_code', true);
        $country_calling_code = !empty($patient_country_calling_code) ? '+' . $patient_country_calling_code : '';

        if(!isKiviCareProActive()){
            $data =  kcClinicDetail(kcGetDefaultClinicId());
        }else{
            $data =  kcClinicDetail((int)$request_data['clinic_id']);
        }

        if(!empty($data)){
            $name = $data->name;
            $address = $data->address .', '. $data->postal_code .', '. $data->city .', '. $data->country;
        }
        ob_start();
        ?>
        <div class="kivi-col-6 pr-4">
            <div class="kc-confirmation-info-section">
                <h6 class="iq-text-uppercase iq-color-secondary iq-letter-spacing-1 mb-2"><?php echo esc_html__('Clinic info', 'kc-lang'); ?></h6>
                <div class="iq-card iq-preview-details">
                <table class="iq-table-border mb-0" style="border:0;">
                        <tr>
                            <td>
                                <h6 style="width: 15em;"><?php echo esc_html($name);?></h6>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <p style="width: 15em;"><?php echo esc_html(!empty($address) ? $address : '');?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="kc-confirmation-info-section">
                <h6 class="iq-text-uppercase iq-color-secondary iq-letter-spacing-1 mb-2"><?php echo esc_html__('Patient info', 'kc-lang'); ?></h6>
                <div class="iq-card iq-preview-details kc-patient-info">
                    <table class="iq-table-border mb-0" style="border:0;">
                        <tr>
                            <td>
                                <h6><?php echo esc_html__('Name', 'kc-lang'); ?>:</h6>
                            </td>
                            <td id="patientName">
                                <p><?php echo esc_html(!empty($patient_data->display_name) ? $patient_data->display_name : '');?></p>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <h6><?php echo esc_html__('Number', 'kc-lang'); ?>:</h6>
                            </td>
                            <td id="patientTelephone">
                                <p><?php echo esc_html(!empty($patient_basic_data->mobile_number) ? $country_calling_code . ' ' . $patient_basic_data->mobile_number : '');?></p>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <h6><?php echo esc_html__('Email', 'kc-lang'); ?>:</h6>
                            </td>
                            <td id="patientEmail">
                                <p><?php echo esc_html(!empty($patient_data->user_email) ? $patient_data->user_email : '');?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
        ?>
        <div class="item-img-1 kivi-col-6 mb-2 pr-4">
            <h6 class="iq-text-uppercase iq-color-secondary iq-letter-spacing-1"><?php echo esc_html__('Appointment summary', 'kc-lang'); ?></h6>
            <div class="iq-card iq-card-border mt-3">
                <div class="d-flex justify-content-between align-items-center">
                    <p><?php echo esc_html__('Doctor', 'kc-lang'); ?> :</p>
                    <h6 id="doctorname"><?php echo esc_html($doctor_name);?></h6>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <p><?php echo esc_html__('Date ', 'kc-lang'); ?> :</p>
                    <h6><span id="dateOfAppointment"><?php echo esc_html(kcGetFormatedDate($request_data['date']));?></span></h6>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <p><?php echo esc_html__('Time ', 'kc-lang'); ?> :</p>
                    <h6><span id="timeOfAppointment"><?php echo esc_html($request_data['time']);?></span></h6>
                </div>
                <div class="iq-card iq-preview-details mt-4">
                    <h6><?php echo esc_html__('Services', 'kc-lang'); ?></h6>

                    <span id="services_list">
                        <?php
                        if(!empty($service_list_data) && count($service_list_data) > 0){
                            $service_total_charge = array_sum(collect($service_list_data)->pluck('charges')->toArray());
                            foreach($service_list_data as $service_data){
                                ?>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                <p> <?php echo esc_html($service_data->name);?></p>
                                <h6><?php echo esc_html((!empty($clinic_currency_detail['prefix']) ? $clinic_currency_detail['prefix'] : '').$service_data->charges.(!empty($clinic_currency_detail['postfix']) ? $clinic_currency_detail['postfix'] : '' ));?></h6>
                            </div>
                                <?php
                            }
                        }
                        ?>
                    </span>
                    <?php 
                    if(!empty($request_data['tax_details']['data'])){
                        ?>
                        <h6 style="padding-top: 16px;"><?php echo esc_html__('Taxes', 'kc-lang'); ?></h6>
                        <?php
                        foreach($request_data['tax_details']['data'] as $tax){
                            ?>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <p> <?php echo esc_html($tax->name);?></p>
                                <h6><?php echo esc_html((!empty($clinic_currency_detail['prefix']) ? $clinic_currency_detail['prefix'] : '').$tax->charges.(!empty($clinic_currency_detail['postfix']) ? $clinic_currency_detail['postfix'] : '' ));?></h6>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
                <?php 
                $request_data['service_total_charge'] = $service_total_charge;
                $request_data['clinic_currency_detail'] = $clinic_currency_detail;
                $this->appointmentTaxDetailHtml($request_data); 
                ?>
            </div>
        </div>
        
        <?php 
                    if (kcAppointmentMultiFileUploadEnable() && !empty($request_data['file'])) {
                        $request_data['file'] = array_map('absint',$request_data['file']);
                ?>
        <div class="kivi-col-6 pr-4">
            <h6 class="iq-text-uppercase iq-color-secondary iq-letter-spacing-1"><?php echo esc_html__('Uploaded files', 'kc-lang'); ?></h6>
            <div class="iq-card iq-preview-details mt-3">
                <table class="iq-table-border" style="border: 0;">
                    <?php 
                        foreach ($request_data['file'] as $key => $file){
                    ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(wp_get_attachment_url($file)); ?>" target="_blank" alt="<?php echo esc_html(get_the_title($file));?>">
                                <i class="fas fa-external-link-alt"></i><?php echo ' '.esc_html(get_the_title($file)) ?> 
                                </a>
                            </td>
                        </tr>
                    <?php 
                        }
                    ?>
                </table>
            </div>
        </div>
        <?php 
                    }
                    $custom_field_values = [];
                    if(!empty($request_data['custom_field'])){
                        foreach($request_data['custom_field'] as $key => $val){
                            if(!empty($val) ){
                                array_push($custom_field_values, $val);
                            }
                        }
                    }
                    if ((kcCheckExtraTabConditionInAppointmentWidget('description') && !empty($request_data['description'])) || (!empty($request_data['custom_field']) && !empty($custom_field_values))) {
                ?>
        <div class="kivi-col-6 pr-4">
            <h6 class="iq-text-uppercase iq-color-secondary iq-letter-spacing-1"><?php echo esc_html__('Other info', 'kc-lang'); ?></h6>
            <div class="iq-card iq-preview-details mt-3">
                <table class="iq-table-border" style="border: 0;">
                <?php if(!empty($request_data['description'])){ ?>
                    <tr>
                        <td>
                            <h6><?php echo esc_html__('Description', 'kc-lang'); ?>:</h6>
                        </td>
                        <td id="AppointmentDescription">
                            <?php echo esc_html(!empty($request_data['description']) ? $request_data['description'] : '');?>
                        </td>
                    </tr>
                <?php 
                }
                if(!empty($label_list)){
                    foreach($label_list as $label_key => $label_value){
                        if(!empty($request_data['custom_field'][$label_key])){
                            if(is_array($request_data['custom_field'][$label_key]) &&
                             isset($request_data['custom_field'][$label_key][0]['text'])){
                                $request_data['custom_field'][$label_key] = collect($request_data['custom_field'][$label_key])->pluck('text')->implode(', ');
                            }else{
                                $request_data['custom_field'][$label_key] = is_array($request_data['custom_field'][$label_key])
                                ? implode(', ',$request_data['custom_field'][$label_key]):$request_data['custom_field'][$label_key];

                            }
                ?>  
                    <tr>
                        <td>
                            <h6><?php echo esc_html($label_value[0]); ?>:</h6>
                        </td>
                        <td>
                            <?php echo esc_html(!empty($request_data['custom_field'][$label_key]) ? $request_data['custom_field'][$label_key] : '');?>
                        </td>
                    </tr>
                <?php
                        }
                    } 
                }
                ?>
                </table>
            </div>
        </div>
        <?php
        }

        $htmldata = ob_get_clean();

	    wp_send_json([
            'status' => true,
            'message' => __('confirm page details', 'kc-lang'),
            'data' => $htmldata,
            'service_charges' => ($request_data['service_total_charge'] ?? 0),
            'tax_details' => !empty($request_data['tax_details']['data']) ? $request_data['tax_details']['data'] : []
        ]);
    }

    /**
     * @throws Exception
     */
    public function getAppointmentPrint(){
        $request_data = $this->request->getInputs();
        if(empty($request_data['id'])){
            wp_send_json([
                'data' => '',
                'status' => false
            ]);
        }
        $appointment_id = (int)$request_data['id'];

        if(!((new KCAppointment())->appointmentPermissionUserWise($appointment_id))){
            wp_send_json( kcUnauthorizeAccessResponse(403) );
        }

        $patient_appointment_table = $this->db->prefix . 'kc_appointments';
        $clinics_table           = $this->db->prefix . 'kc_clinics';
        $users_table             = $this->db->base_prefix . 'users';
        $appointments_service_table = $this->db->prefix . 'kc_appointment_service_mapping';
        $service_table = $this->db->prefix . 'kc_services';


        $patient_condition = "";
        if($this->getLoginUserRole() ==  $this->getPatientRole()){
            $patient_id = get_current_user_id();
            $patient_condition = " AND {$patient_appointment_table}.patient_id = {$patient_id} ";
        }
        $query = "SELECT {$patient_appointment_table}.*,
                       {$patient_appointment_table}.status AS appointment_status,
                       doctors.display_name  AS doctor_name,
                       doctors.user_email AS doctor_email,    
                       patients.display_name AS patient_name,
                       patients.user_email AS patient_email,
                       GROUP_CONCAT({$service_table}.name) AS all_services_name,
                       {$clinics_table}.*, 
                       CONCAT({$clinics_table}.address, ', ', {$clinics_table}.city,', '
		             ,{$clinics_table}.postal_code,', ',{$clinics_table}.country) AS clinic_address
                    FROM  {$patient_appointment_table}
                       LEFT JOIN {$users_table} doctors
                              ON {$patient_appointment_table}.doctor_id = doctors.id
                      LEFT JOIN {$users_table} patients
                              ON {$patient_appointment_table}.patient_id = patients.id
                       LEFT JOIN {$clinics_table}
                              ON {$patient_appointment_table}.clinic_id = {$clinics_table}.id
                      LEFT JOIN {$appointments_service_table} 
                          ON {$patient_appointment_table}.id = {$appointments_service_table}.appointment_id 
                      LEFT JOIN {$service_table} 
				            ON {$appointments_service_table}.service_id = {$service_table}.id
                    WHERE {$patient_appointment_table}.id = {$appointment_id} {$patient_condition} GROUP BY {$patient_appointment_table}.id";

        $encounter = $this->db->get_row( $query);

        $patient_id_match = false;
        if($this->getLoginUserRole() ==  $this->getPatientRole()){
            $patient_id = get_current_user_id();
            // $patient_id_match = false;
            // $parient_condition = " AND {$patient_appointment_table}.patient_id = {$patient_id} ";
            if($encounter->patient_id == $patient_id){
                $patient_id_match = true;
            }
        }

        if ( !empty( $encounter ) ) {
            $encounter->medical_history = '';
            $encounter->prescription = '';
            $basic_data = get_user_meta((int)$encounter->doctor_id, 'basic_data', true);
            $basic_data = json_decode($basic_data);
            $basic_data->qualifications = !empty($basic_data->qualifications) ? $basic_data->qualifications : [];
            foreach ($basic_data->qualifications as $q) {
                $qualifications[] = $q->degree;
                $qualifications[] = $q->university;
            }
            $patient_basic_data = json_decode(get_user_meta((int)$encounter->patient_id, 'basic_data', true));
            $encounter->patient_gender = !empty($patient_basic_data->gender)
                ? ($patient_basic_data->gender === 'female'
                    ? 'F' : 'M') : '';
            $encounter->patient_address = (!empty($patient_basic_data->address) ? $patient_basic_data->address : '');
            $encounter->patient_city = (!empty($patient_basic_data->city) ? $patient_basic_data->city : '');
            $encounter->patient_state = (!empty($patient_basic_data->state) ? $patient_basic_data->state : '');
            $encounter->patient_country = (!empty($patient_basic_data->country) ? $patient_basic_data->country : '');
            $encounter->patient_postal_code = (!empty($patient_basic_data->postal_code) ? $patient_basic_data->postal_code : '');
            $encounter->contact_no = (!empty($patient_basic_data->mobile_number) ? $patient_basic_data->mobile_number : '');
            $encounter->patient_add = $encounter->patient_address . ',' . $encounter->patient_city
                . ',' . $encounter->patient_state . ',' . $encounter->patient_country . ',' . $encounter->patient_postal_code;
            $encounter->date = current_time('Y-m-d');
            $encounter->patient_age = '';
            if (!empty($patient_basic_data->dob)) {
                try {
                    $from = new DateTime($patient_basic_data->dob);
                    $to   = new DateTime('today');
                    $years = $from->diff($to)->y;
                    $months = $from->diff($to)->m;
                    $days = $from->diff($to)->d;
                    if(empty($months) && empty($years)){
                        $encounter->patient_age = $days .esc_html__(' Days', 'kc-lang');
                    }else if(empty($years)){
                        $encounter->patient_age = $months .esc_html__(' Months', 'kc-lang');
                    }else{
                        $encounter->patient_age = $years .esc_html__(' Years', 'kc-lang');
                    }
                } catch (Exception $e) {
	                wp_send_json([
                        'data' => '',
                        'status' => false,
                        'calendar_content' =>'',
                        'message' => $e->getMessage()
                    ]);
                }
            }
            $encounter->qualifications = !empty($qualifications) ? '(' . implode(", ", $qualifications) . ')' : '';
            $encounter->clinic_logo = !empty($encounter->profile_image) ? wp_get_attachment_url($encounter->profile_image) : KIVI_CARE_DIR_URI .'assets/images/kc-demo-img.png';
            $encounter->calendar_enable = !empty($request_data['calendar_enable']);
        }

        $calender_content = '';
        if(isKiviCareProActive() && !empty($request_data['calendar_enable'])
            && $request_data['calendar_enable'] == 'yes'
            && (string)$encounter->appointment_status !== '0'){
	        if( in_array($this->getLoginUserRole(),[$this->getDoctorRole(), $this->getPatientRole()])){
		        $appointment_data = [
			        "clinic_name" => $encounter->name,
			        "clinic_address" => $encounter->clinic_address,
			        "id" => $appointment_id,
			        "start_date" => $encounter->appointment_start_date,
			        "start_time" => $encounter->appointment_start_time,
			        "end_date" => $encounter->appointment_end_date,
			        "end_time" => $encounter->appointment_end_time,
			        "appointment_service" =>$encounter->all_services_name,
			        "extra" => $encounter
		        ];
		        $calender_content = kcAddToCalendarContent($appointment_data);
		        if(!empty($calender_content)){
			        $calender_content = array_merge($calender_content,[
				        "options" => [
					        "Apple",
					        "Google",
					        "iCal",
					        "Microsoft365",
					        "MicrosoftTeams",
					        "Outlook.com",
					        "Yahoo"
				        ],
				        "trigger" => "click",
			        ]);
		        }
	        }
        }

	    wp_send_json([
            'data' => kcPrescriptionHtml($encounter,$appointment_id,'appointment'),
            'status' => true,
            'calendar_content' =>$calender_content,
            'patient_id_match' => $patient_id_match
        ]);
    }

    public function getAppointmentCustomField(){
        $request_data = $this->request->getInputs();
        ob_start();
        if(!empty($request_data['user_role'])){
            if($request_data['user_role'] === 'kiviCare_doctor'){
                kcGetCustomFieldsList('doctor_module',0);
            }else if($request_data['user_role'] == 'kiviCare_patient'){
                kcGetCustomFieldsList('patient_module',0);
            }
        }else{
            kcGetCustomFieldsList('appointment_module',$request_data['doctor_id']);
        }
        $data = ob_get_clean();

	    wp_send_json([
            'data' => $data,
            'status' => true
        ]);
    }

    public function getWidgetPaymentOptions(){
        if(!$this->userHasKivicareRole()){
            wp_send_json( kcUnauthorizeAccessResponse(403) );
        }
        $request_data = $this->request->getInputs();
        $request_data['doctor_id'] = (int)$request_data['doctor_id'];
        $request_data['clinic_id'] = (int)$request_data['clinic_id'];
        $doctor_name =  $this->db->get_var("SELECT display_name FROM {$this->db->base_prefix}users WHERE ID = {$request_data['doctor_id']}");
        $clinic_currency_detail = kcGetClinicCurrenyPrefixAndPostfix();
        $service_total_charge = 0;
        $prefix = !empty($clinic_currency_detail['prefix']) ? $clinic_currency_detail['prefix'] : '';
        $postfix = !empty($clinic_currency_detail['postfix']) ? $clinic_currency_detail['postfix'] : '';
        $request_data['service_list_data'] = collect($request_data['service_list'])->pluck('service_id')->map(function($id){ return (int)$id; })->toArray();
        $request_data['service_list'] = array_map(function($v) use ($request_data){
            $temp = absint($v['service_id']);
            $v['charges'] = $this->db->get_var("SELECT charges FROM {$this->db->prefix}kc_service_doctor_mapping WHERE service_id = {$temp} AND clinic_id = {$request_data['clinic_id']} AND doctor_id = {$request_data['doctor_id']}");
            return $v;
        }, $request_data['service_list']);
        ob_start();
        $implode_service_ids = implode(',',$request_data['service_list_data']);
        $request_data['tax_details'] = apply_filters('kivicare_calculate_tax',['status' => false,
        'message' => '',
        'data' => []
        ],[
            "id" => '',
            "type" => 'appointment',
            "doctor_id" => $request_data['doctor_id'],
            "clinic_id" => $request_data['clinic_id'],
            "service_id" => $request_data['service_list_data'],
            "total_charge" => $this->db->get_var("SELECT SUM(charges) FROM {$this->db->prefix}kc_service_doctor_mapping
                                        WHERE doctor_id = {$request_data['doctor_id']} AND  clinic_id = {$request_data['clinic_id']} 
                                         AND service_id IN ({$implode_service_ids}) " ),
            'extra_data' => $request_data
        ]);
        $i = 0;
        ?>
        <div class="kivi-col-6 pr-4">
            <div class="kc-confirmation-info-section">
                <h6 class="iq-text-uppercase iq-color-secondary iq-letter-spacing-1 mb-2"><?php echo esc_html__('Select Payment', 'kc-lang'); ?></h6>
                <div>
                    <div class="iq-card iq-bg-primary-light card-list mt-3">
                        <?php foreach (kcAllPaymentMethodList() as $key => $value){
                            $payment_logo = apply_filters('kivicare_payment_option_logo', KIVI_CARE_DIR_URI . 'assets/images/'.$key.'.png', $key);
                           ?>
                            <div class="iq-client-widget">
                                <input type="radio" class="card-checkbox" <?php echo $i === 0 ? 'checked' : ''; ?> name="payment_option" id="<?php echo esc_html($key);?>"  value='<?php echo esc_html($key);?>'>
                                <label class="btn-border01 w-100" for="<?php echo esc_html($key);?>">
                                    <div class="iq-card iq-fancy-design iq-bg-white iq-card-border iq-btn-lg text-center">
                                        <div class=row>
                                            <div class="col-4">
                                                <img src="<?php echo esc_url($payment_logo);?>" />
                                            </div>
                                            <div class="col-8 my-auto">
                                                <?php echo esc_html($value) ;?>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <?php
                            $i++;
                        }?>
                    </div>
                </div>
            </div>
        </div>
        <div class="item-img-1 kivi-col-6 mb-2 pr-4">
            <h6 class="iq-text-uppercase iq-color-secondary iq-letter-spacing-1"><?php echo esc_html__('Appointment summary', 'kc-lang'); ?></h6>
            <div class="iq-card iq-card-border mt-3">
                <div class="d-flex justify-content-between align-items-center">
                    <p><?php echo esc_html__('Doctor', 'kc-lang'); ?> :</p>
                    <h6 id="doctorname"><?php echo esc_html($doctor_name);?></h6>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <p><?php echo esc_html__('Date ', 'kc-lang'); ?> :</p>
                    <h6><span id="dateOfAppointment"><?php echo esc_html( kcGetFormatedDate($request_data['date']));?></span></h6>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <p><?php echo esc_html__('Time ', 'kc-lang'); ?> :</p>
                    <h6><span id="timeOfAppointment"><?php echo esc_html($request_data['time'] );?></span></h6>
                </div>
                <div class="iq-card iq-preview-details mt-4">
                    <h6><?php echo esc_html__('Services', 'kc-lang'); ?></h6>
                    <span id="services_list">
                        <?php
                        if(!empty($request_data['service_list'])){
                            foreach($request_data['service_list'] as $service_data){
                                $service_total_charge += $service_data['charges'];
                                ?>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                <p> <?php echo esc_html($service_data['name']);?></p>
                                <h6><?php echo esc_html($prefix.$service_data['charges'].$postfix);?></h6>
                            </div>
                                <?php
                            }
                        }
                        ?>
                    </span>
                    <?php 
                    if(!empty($request_data['tax_details']['data'])){
                        ?>
                        <h6 style="padding-top: 16px;"><?php echo esc_html__('Taxes', 'kc-lang'); ?></h6>
                        <?php
                        foreach($request_data['tax_details']['data'] as $tax){
                            ?>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <p> <?php echo esc_html($tax->name);?></p>
                                <h6><?php echo esc_html((!empty($clinic_currency_detail['prefix']) ? $clinic_currency_detail['prefix'] : '').$tax->charges.(!empty($clinic_currency_detail['postfix']) ? $clinic_currency_detail['postfix'] : '' ));?></h6>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
                <?php
                $request_data['service_total_charge'] = $service_total_charge;
                $request_data['clinic_currency_detail'] = $clinic_currency_detail;
                 $this->appointmentTaxDetailHtml($request_data); 
                 ?>
            </div>
        </div>
        <?php
        $data = ob_get_clean();

		wp_send_json([
            'data' => $data,
            'status' => true
        ]);
    }

    public function doctorHtmlContent($user){
        ?><div class="iq-client-widget " data-index="<?php echo esc_html($user->ID); ?>">
        <input type="radio" class="card-checkbox selected-doctor kivicare-doctor-widget" data-index="<?php echo esc_html($user->ID); ?>" name="card_main" id="doctor_<?php echo esc_html($user->ID); ?>" value="<?php echo esc_html($user->ID); ?>" doctorName="<?php echo esc_html($user->data->display_name); ?>">
        <label class="btn-border01 w-100" for="doctor_<?php echo esc_html($user->ID); ?>">
            <div class="iq-card iq-card-border iq-fancy-design iq-doctor-widget">
                <?php
                if(kcGetSingleWidgetSetting('showDoctorImage')){
                    ?>
                    <div class="iq-navbar-header" style="height: 100px;">
                        <div class="profile-bg"></div>
                    </div>
                    <?php
                }
                ?>
                <div class="iq-top-left-ribbon" style="display:<?php echo esc_html($user->telemed != 'true' ? 'none' : 'block'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" viewBox="0 0 20 20" fill="none">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M13.5807 12.9484C13.6481 14.4752 12.416 15.7662 10.8288 15.8311C10.7119 15.836 5.01274 15.8245 5.01274 15.8245C3.43328 15.9444 2.05094 14.8094 1.92636 13.2884C1.91697 13.1751 1.91953 7.06 1.91953 7.06C1.84956 5.53163 3.08002 4.23733 4.66801 4.16998C4.78661 4.16424 10.4781 4.17491 10.4781 4.17491C12.0653 4.05665 13.4519 5.19984 13.5747 6.72821C13.5833 6.83826 13.5807 12.9484 13.5807 12.9484Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M13.5834 8.31621L16.3275 6.07037C17.0075 5.51371 18.0275 5.99871 18.0267 6.87621L18.0167 13.0004C18.0159 13.8779 16.995 14.3587 16.3167 13.802L13.5834 11.5562" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </div>
                <?php
                if(kcGetSingleWidgetSetting('showDoctorImage')){
                    ?><div class="media d-flex justify-content-center align-items-center">
                    <img src="<?php echo esc_url(!empty($user->user_image) ? $user->user_image : KIVI_CARE_DIR_URI.'/assets/images/kc-demo-img.png'); ?>" class="avatar-90 rounded-circle object-cover" alt="<?php echo esc_html($user->ID); ?>">
                    </div>
                    <?php
                }

                ?>
                <h5 class="mt-2"><?php echo esc_html($user->display_name); ?></h5>                
                <?php
                    if(kcGetSingleWidgetSetting('showDoctorSpeciality')){
                    ?>
                <span class="iq-letter-spacing-1 iq-text-uppercase mb-1"><?php echo esc_html($user->specialties_all); ?></span>
                <?php } ?>
                <?php
                    if(kcGetSingleWidgetSetting('showDoctorDegree')){
                    ?>
                <span class="iq-letter-spacing-1 iq-text-uppercase mb-1"><?php echo !empty($user->qualifications) ? esc_html($user->qualifications) : ''; ?></span>
                <?php } ?>
                <?php
                    if(kcGetSingleWidgetSetting('showDoctorRating')){
                    ?>
                <span><?php kcCalculateDoctorReview($user->ID); ?></span>
                <?php } ?>
                <?php

                if(kcGetSingleWidgetSetting('showDoctorExperience')){
                    ?>
                    <div class="my-2 iq-doctor-badge">
                        <span class="iq-badge iq-bg-secondary iq-color-white"> <?php echo esc_html__('Exp : ','kc-lang').esc_html($user->no_of_experience).esc_html__('yr','kc-lang'); ?> </span>
                    </div>
                    <?php
                }
                $this->widgetUserProfileCardExtraDetail('doctor',$user);
                ?>
            </div>
        </label>
        </div>
        <?php
    }

    public function widgetUserProfileCardExtraDetail($type,$user){
        $temp = kcGetSingleWidgetSetting($type.'ContactDetails');
        if (!empty($temp->id)) {
            switch ((int)$temp->id) {
                case 1:
                    ?>
                    <div class="mt-2">
                        <div class="d-flex align-items-center justify-content-sm-between flex-sm-row flex-column flex-wrap">
                            <h6><?php echo esc_html__('Email','kc-lang'); ?></h6>
                            <p class=""><?php echo esc_html($user->user_email); ?></p>
                        </div>
                        <?php
                        if(!empty($user->mobile_number)){
                            ?>
                            <div class="d-flex align-items-center justify-content-sm-between flex-sm-row flex-column flex-wrap mt-md-0 mt-1">
                                <h6><?php echo esc_html__('Contact','kc-lang'); ?></h6>
                                <p class=""><?php echo esc_html('+' . $user->country_calling_code . ' ' . $user->mobile_number); ?></p>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                    <?php
                    break;
                case 2:
                    ?>
                    <div class="mt-2">
                        <?php
                        if(!empty($user->mobile_number)){
                            ?>
                            <div class="d-flex align-items-center justify-content-sm-between flex-sm-row flex-column flex-wrap">
                                <h6><?php echo esc_html__('Contact','kc-lang'); ?></h6>
                                <p class=""><?php echo esc_html('+' . $user->country_calling_code . ' ' . $user->mobile_number); ?></p>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                    <?php
                    break;
                case 3:
                    ?>
                    <div class="mt-2">
                        <div class="d-flex align-items-center justify-content-sm-between flex-sm-row flex-column flex-wrap">
                            <h6><?php echo esc_html__('Email','kc-lang'); ?></h6>
                            <p class=""><?php echo esc_html($user->user_email); ?></p>
                        </div>
                    </div>
                    <?php
                    break;
            }
        }
    }

    public function appointmentTaxDetailHtml($request_data,$appointment_id = ''){
        $service_total_charge = $request_data['service_total_charge'];
        $clinic_currency_detail = $request_data['clinic_currency_detail'];
        $tax_details = $request_data['tax_details'];
     
        if(!empty($tax_details['tax_total'])){
            $service_total_charge += $tax_details['tax_total'];
        }
        ?>
        <hr class="mb-0">
        <div class="d-flex justify-content-between align-items-center kc-total-price mt-4">
            <h5><?php echo esc_html__('Total Price', 'kc-lang'); ?></h5>
            <h5 class="iq-color-primary kc-services-total" id="services_total"> <?php echo esc_html((!empty($clinic_currency_detail['prefix']) ? $clinic_currency_detail['prefix'] : '').$service_total_charge.(!empty($clinic_currency_detail['postfix']) ? $clinic_currency_detail['postfix'] : '' ));?></h5>
        </div>
        <?php
    }
}
