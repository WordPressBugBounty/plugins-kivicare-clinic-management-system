<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCAppointment;
use App\models\KCBill;
use App\models\KCClinicSession;
use App\models\KCPatientEncounter;
use App\models\KCAppointmentServiceMapping;
use App\models\KCBillItem;
use App\models\KCClinic;
use  App\models\KCReceptionistClinicMapping;
use App\controllers\KCPaymentController;
use DateTime;
use DateTimeZone;
use Exception;

class KCAppointmentController extends KCBase {

	public $db;

	private $request;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->request = new KCRequest();
        parent::__construct();

	}

	public function index() {

		if ( ! kcCheckPermission( 'appointment_list' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse());
		}

		$request_data = $this->request->getInputs();
		$users_table        = $this->db->base_prefix . 'users';
		$appointments_table = $this->db->prefix . 'kc_appointments';
		$clinics_table      = $this->db->prefix . 'kc_clinics';
		$start_date         = esc_sql($request_data['start_date']);
		$end_date           = esc_sql($request_data['end_date']);
        $selected_patient = $selected_doctor = $selected_status = $selected_service =  '';
        if(!empty($request_data['patient_id'])){
            $temp_selected_patient = array_map(function ($v){
                $v =  json_decode(stripslashes($v),true);
                return !empty($v['id']) ? (int)$v['id'] : '';
            },$request_data['patient_id']);
            if(!empty($temp_selected_patient)){
                $temp_selected_patient = array_filter($temp_selected_patient);
                $selected_patient = implode(',',$temp_selected_patient);
            }
        }
        if(!empty($request_data['doctor_id'])){
            $temp_selected_doctor = array_map(function ($v){
                $v =  json_decode(stripslashes($v),true);
                return !empty($v['id']) ? (int)$v['id'] : '';
            },$request_data['doctor_id']);
            if(!empty($temp_selected_doctor)){
                $temp_selected_doctor = array_filter($temp_selected_doctor);
                $selected_doctor = implode(',',$temp_selected_doctor);
            }
        }
        if(!empty($request_data['status'])){
            $temp_selected_status = array_map(function ($v){
                $v =  json_decode(stripslashes($v),true);
                return isset($v['value']) && $v['value'] !== 'all' ? esc_sql($v['value']) : '';
            },$request_data['status']);

            if(!empty($temp_selected_status)){
                // $temp_selected_status = array_filter($temp_selected_status);
                $selected_status = implode(',',$temp_selected_status);
            }
        }
        if(!empty($request_data['service'])){
            $temp_selected_service = array_map(function ($v){
                $v =  json_decode(stripslashes($v),true);
                return !empty($v['service_id']) ? (int)$v['service_id'] : '';
            },$request_data['service']);

            if(!empty($temp_selected_service)){
                $temp_selected_service = array_filter($temp_selected_service);
                $selected_service = implode(',',$temp_selected_service);
            }
        }

		$appointments_service_table = $this->db->prefix . 'kc_appointment_service_mapping';
		$service_table = $this->db->prefix . 'kc_services';
		$query = "
			SELECT {$appointments_table}.*,
		       doctors.display_name  AS doctor_name,
		       patients.display_name AS patient_name,
		       {$clinics_table}.name AS clinic_name
			FROM  {$appointments_table}
		       LEFT JOIN {$users_table} doctors
		              ON {$appointments_table}.doctor_id = doctors.id
		       LEFT JOIN {$users_table} patients
		              ON {$appointments_table}.patient_id = patients.id
		       LEFT JOIN {$clinics_table}
		              ON {$appointments_table}.clinic_id = {$clinics_table}.id
            WHERE {$appointments_table}.appointment_start_date >= '{$start_date}' AND {$appointments_table}.appointment_start_date <= '{$end_date}' ";

        $current_user_id = get_current_user_id();

        $current_user_role = $this->getLoginUserRole();

		if ($current_user_role ===  $this->getDoctorRole() ) {
			$query .= " AND {$appointments_table}.doctor_id = " . $current_user_id;
		}else if(!empty($selected_doctor)){
            $query .= " AND {$appointments_table}.doctor_id IN ($selected_doctor) " ;
        }

        if ( $current_user_role === $this->getPatientRole() ) {
            $query .= " AND {$appointments_table}.patient_id = " . $current_user_id;
        }else if ( !empty($selected_patient) ) {
            $query .= " AND {$appointments_table}.patient_id IN ($selected_patient) " ;
        }


        if($selected_status !== '' && $selected_status !== null){
            $query .= " AND {$appointments_table}.status IN ($selected_status) " ;
        }

        if(!empty($selected_service)){
            $query .= " AND {$appointments_table}.id IN ( SELECT appointment_id FROM {$appointments_service_table} WHERE service_id IN ({$selected_service}) ) ";
        }

        if(isKiviCareProActive()){
            if($current_user_role == $this->getClinicAdminRole()){
                $clinic_id = kcGetClinicIdOfClinicAdmin();
                $query .= " AND {$appointments_table}.clinic_id={$clinic_id} ";
            }elseif ($current_user_role == $this->getReceptionistRole()) {
                $clinic_id = kcGetClinicIdOfReceptionist();
                $query .= " AND {$appointments_table}.clinic_id={$clinic_id} ";
            }
        }

		$appointments     = collect($this->db->get_results( $query ))->unique('id')->values();
        if ( ! count( $appointments ) ) {
            wp_send_json( [
                'status'  => false,
                'message' => esc_html__('No appointments found', 'kc-lang'),
                'data'    => []
            ] );
        }

        $new_appointments = $telemed_data = $googlemeet_data = [];
        $pro_plugin_active = isKiviCareProActive();
        $all_appointment_id = $appointments->pluck('id')->implode(",");

        $all_service_query = "SELECT {$appointments_table}.id AS appointment_id, GROUP_CONCAT({$appointments_service_table}.service_id) AS service_id, GROUP_CONCAT({$service_table}.name) AS service_name FROM {$appointments_table}
				LEFT JOIN {$appointments_service_table} ON {$appointments_table}.id = {$appointments_service_table}.appointment_id JOIN {$service_table} 
				ON {$appointments_service_table}.service_id = {$service_table}.id WHERE 0 = 0 AND {$appointments_service_table}.appointment_id IN ({$all_appointment_id}) GROUP BY {$appointments_table}.id ";

        $all_service_results = collect($this->db->get_results($all_service_query))->keyBy('appointment_id')->toArray();

        if(isKiviCareTelemedActive()){
            $zoom_mapping_table = $this->db->prefix.'kc_appointment_zoom_mappings';
            $telemed_query = "SELECT {$zoom_mapping_table}.* FROM {$zoom_mapping_table} JOIN {$appointments_table} ON {$appointments_table}.id = {$zoom_mapping_table}.appointment_id WHERE 0=0 AND {$zoom_mapping_table}.appointment_id IN ({$all_appointment_id})";
            $telemed_data = collect($this->db->get_results($telemed_query))->keyBy('appointment_id')->toArray();
        }
        if(isKiviCareGoogleMeetActive()){
            $googlemeet_mapping_table = $this->db->prefix.'kc_appointment_google_meet_mappings';
            $telemed_query = "SELECT {$googlemeet_mapping_table}.* FROM {$googlemeet_mapping_table} JOIN {$appointments_table} ON {$appointments_table}.id = {$googlemeet_mapping_table}.appointment_id WHERE 0=0 AND {$googlemeet_mapping_table}.appointment_id IN ({$all_appointment_id})";
            $googlemeet_data = collect($this->db->get_results($telemed_query))->keyBy('appointment_id')->toArray();
        }
        foreach ( $appointments as $key => $appointment ) {

            $new_appointments[ $key ]['id']                     = (int)$appointment->id;
            $new_appointments[ $key ]['date']                   = kcGetFormatedDate($appointment->appointment_start_date) . ' ' . $appointment->appointment_start_time;
            $new_appointments[ $key ]['endDate']                = kcGetFormatedDate($appointment->appointment_end_date) . ' ' . $appointment->appointment_end_time;
            $new_appointments[ $key ]['appointment_start_date'] = kcGetFormatedDate($appointment->appointment_start_date);
            $new_appointments[ $key ]['appointment_start_time'] = kcGetFormatedTime(date( 'h:i A', strtotime( $appointment->appointment_start_time ) ));
            $new_appointments[ $key ]['visit_type']             = $appointment->visit_type;
            $new_appointments[ $key ]['description']            = $appointment->description;
            $new_appointments[ $key ]['title']                  = ($current_user_role === $this->getPatientRole()) ? $appointment->doctor_name : $appointment->patient_name;
            $new_appointments[ $key ]['payment_mode']           = kcAppointmentPaymentMode($appointment->id);
            $new_appointments[ $key ]['clinic_id']              = [
                'id'    => (int)$appointment->clinic_id,
                'label' => decodeSpecificSymbols($appointment->clinic_name)
            ];
            $new_appointments[ $key ]['doctor_id']              = [
                'id'    => (int)$appointment->doctor_id,
                'label' => $appointment->doctor_name
            ];
            $new_appointments[ $key ]['patient_id']             = [
                'id'   => (int)$appointment->patient_id,
                'label' => $appointment->patient_name
            ];
            $new_appointments[ $key ]['clinic_name']            = decodeSpecificSymbols($appointment->clinic_name);
            $new_appointments[ $key ]['doctor_name']            = $appointment->doctor_name;
            $new_appointments[ $key ]['status']                 = $appointment->status;
            $new_appointments[ $key ]['all_services']= !empty($all_service_results[$appointment->id]->service_name) ? $all_service_results[$appointment->id]->service_name : '';
            if ( $appointment->status === '0' ) {
                $new_appointments[ $key ]['color'] = '#f5365c';
            } elseif ($appointment->status === '1') {
                $new_appointments[ $key ]['color'] = '#23a359';
            }elseif ($appointment->status === '2') {
                $new_appointments[ $key ]['color'] = '#efc51c';
            }elseif ($appointment->status === '3') {
                $new_appointments[ $key ]['color'] = '#78c0fb';
            }elseif ($appointment->status === '4') {
                $new_appointments[ $key ]['color'] = '#4874dc';
            }
            $appointment->all_service_details = $all_service_results[$appointment->id];
            $new_appointments[ $key ]['color'] = apply_filters('kivicare_appointment_calendar_color',$new_appointments[ $key ]['color'],$appointment->id,$appointment);
	        $new_appointments[ $key ]['textColor'] = apply_filters('kivicare_appointment_calendar_text_color','#fff',$appointment->id,$appointment);

            $new_appointments[ $key ]['telemed_service'] = false;
            $new_appointments[ $key ]['telemed_meeting_link'] = '';
            if(!empty($telemed_data[$appointment->id])){
                $new_appointments[ $key ]['telemed_service'] = true;
                if(in_array($current_user_role,[$this->getPatientRole(),$this->getDoctorRole()])){
                    if($this->getLoginUserRole() === $this->getPatientRole()){
                        $new_appointments[ $key ]['telemed_meeting_link'] = $telemed_data[$appointment->id]->join_url;
                    }else{
                        $new_appointments[ $key ]['telemed_meeting_link'] = $telemed_data[$appointment->id]->start_url;
                    }
                }
            }
            if(!empty($googlemeet_data[$appointment->id])){
                $new_appointments[ $key ]['telemed_service'] = true;
                if(in_array($current_user_role,[$this->getPatientRole(),$this->getDoctorRole()])){
                    $new_appointments[ $key ]['telemed_meeting_link'] = $googlemeet_data[$appointment->id]->url;
                }
            }

            $date = date("Y-m-d H:i:s", strtotime(date("c", strtotime($appointment->appointment_start_date . $appointment->appointment_start_time))));
            $new_appointments[ $key ]['is_edit_able'] = $date > current_time("Y-m-d H:i:s");
            $new_appointments[ $key ]['tax'] = [];
            $new_appointments[ $key ]['start'] = $appointment->appointment_start_date . ' ' . $appointment->appointment_start_time;
            $new_appointments[ $key ]['end'] = $appointment->appointment_end_date . ' ' . $appointment->appointment_end_time;
            if($pro_plugin_active){
                $tax = apply_filters('kivicare_calculate_tax',[
                    'status' => false,
                    'message' => '',
                    'data' => []
                ], [
                    "id" => $appointment->id,
                    "type" => 'appointment',
                ]);

                if(!empty($tax['data']) && is_array($tax['data'])){
                    $new_appointments[ $key ]['total_tax'] = $tax['tax_total'];
                    $new_appointments[ $key ]['tax'] = $tax['data'];
                }
            }
            $new_appointments[ $key ] = apply_filters('kivicare_appointment_list_data',$new_appointments[ $key ],$appointment);
            
        }

		wp_send_json( [
			'status'  => true,
			'message' => esc_html__('Appointment list','kc-lang'),
			'data'    => $new_appointments
		] );
	}

	public function save() {

		global $wpdb;

		if ( ! kcCheckPermission( 'appointment_add' ) && !kcCheckPermission('appointment_edit') ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();
		$rules = [
			'appointment_start_date' => 'required|date',
			'appointment_start_time' => 'required',
			'clinic_id'              => 'required',
			'doctor_id'              => 'required',
			'patient_id'             => 'required',
			'status'                 => 'required',
		];

		$message = [
			'status'     => esc_html__('Status is required', 'kc-lang'),
			'patient_id' => esc_html__('Patient is required','kc-lang'),
			'clinic_id'  => esc_html__('Clinic is required','kc-lang'),
			'doctor_id'  => esc_html__('Doctor is required','kc-lang'),
		];

		$errors = kcValidateRequest( $rules, $request_data, $message );

		if ( count( $errors ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => $errors[0]
			] );
		}

        $proPluginActive = isKiviCareProActive();
        $telemedZoomPluginActive = isKiviCareTelemedActive();
        $telemedGooglemeetPluginActive = isKiviCareGoogleMeetActive();

        //check if service is single or multiple, if single create array
        if(empty(array_filter($request_data['visit_type'], 'is_array'))){
            $request_data['visit_type'] = [$request_data['visit_type']];
        };

        $current_user_role = $this->getLoginUserRole();
        $current_login_user_id = get_current_user_id();
        if($proPluginActive){
            if($current_user_role == $this->getClinicAdminRole()){
                $request_data['clinic_id']['id'] = kcGetClinicIdOfClinicAdmin();
            }elseif ($current_user_role == $this->getReceptionistRole()) {
                $request_data['clinic_id']['id'] = kcGetClinicIdOfReceptionist();
            }
        }else{
            $request_data['clinic_id'] = [];
            $request_data['clinic_id']['id'] = kcGetDefaultClinicId();
        }
        $notification = '';
        $current_date = current_time("Y-m-d H:i:s");
        $appointment_day = esc_sql(strtolower(date('l', strtotime($request_data['appointment_start_date'])))) ;
        $day_short = esc_sql(substr($appointment_day, 0, 3));
        $query = "SELECT time_slot FROM {$wpdb->prefix}kc_clinic_sessions  WHERE `doctor_id` = ".(int)$request_data['doctor_id']['id']." AND `clinic_id` = ".(int)$request_data['clinic_id']['id']."  AND ( `day` = '{$day_short}' OR `day` = '{$appointment_day}') ";
        $clinic_session_time_slots = $wpdb->get_var($query);
		$time_slot             = !empty($clinic_session_time_slots) ? $clinic_session_time_slots : 15;
		$end_time             = strtotime( "+" . $time_slot . " minutes", strtotime($request_data['appointment_start_time'])  );
		$appointment_end_time = date( 'H:i:s', $end_time );
		$appointment_date     = date( 'Y-m-d', strtotime( $request_data['appointment_start_date'])  );
        $appointment_start_date = esc_sql($appointment_date);
        $appointment_start_time = esc_sql(date( 'H:i:s', strtotime( $request_data['appointment_start_time']) ) );
        if(isset($request_data['payment_mode']) && $request_data['payment_mode'] !== 'paymentOffline'){
            $request_data['status'] = 0;
        }
		$appointment_status = esc_sql($request_data['status']);
        if(isKiviCareProActive()){
            $verifyTimeslot = apply_filters('kcpro_verify_appointment_timeslot',$request_data);
            if(is_array($verifyTimeslot) && array_key_exists('end_time',$verifyTimeslot) && !empty($verifyTimeslot['end_time'])){
                if(empty($verifyTimeslot['status'])){
	                wp_send_json($verifyTimeslot);
                }
                $appointment_end_time = date( 'H:i:s', $verifyTimeslot['end_time'] );
            }
        }

        $clinic_id = (int)$request_data['clinic_id']['id'];
        $doctor_id = (int)$request_data['doctor_id']['id'];
        $patient_id = (int)$request_data['patient_id']['id'];

        if( $current_user_role === $this->getPatientRole() && $patient_id !== $current_login_user_id ){
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        if( $current_user_role === $this->getDoctorRole() && $doctor_id !== $current_login_user_id ){
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $temp = [
			'appointment_start_date' => $appointment_start_date,
			'appointment_start_time' => $appointment_start_time,
			'appointment_end_date'   => esc_sql($appointment_date),
			'appointment_end_time'   => esc_sql($appointment_end_time),
			'clinic_id'              => $clinic_id,
			'doctor_id'              => $doctor_id,
			'patient_id'             => $patient_id,
			'description'            => esc_sql($request_data['description']),
			'status'                 => $appointment_status,
		];

        $appointment_table_name = $this->db->prefix . 'kc_appointments';
        if(isset($request_data['file']) && is_array($request_data['file']) && count($request_data['file']) > 0){
            kcUpdateFields($appointment_table_name,[ 'appointment_report' => 'longtext NULL']);
            $temp['appointment_report'] = json_encode($request_data['file']);
        }

		$appointment = new KCAppointment();
        $oldMappingServiceDelete = false;
        $beforeUpdateAppointmentData = (object)[];
		if ( !empty( $request_data['id'] )) {
            if(!((new KCAppointment())->appointmentPermissionUserWise($request_data['id']))){
                wp_send_json( kcUnauthorizeAccessResponse(403) );
            }
            $appointment_id = (int)$request_data['id'];

            if($current_user_role == $this->getPatientRole()){
                $kcGetCancellationBuffer = kcGetCancellationBufferData($current_date, $appointment_start_date, $appointment_start_time);
                if($kcGetCancellationBuffer === false){
                    $message = esc_html__('This Appointment can not be edited.', 'kc-lang');
                    wp_send_json([
                        'status'  => true,
                        'message' => $message,
                        'notification' =>$notification,
                    ]);
                }
            }

            $beforeUpdateAppointmentData =  $this->db->get_row("SELECT * FROM {$appointment_table_name} WHERE id={$appointment_id}");
            $appointment->update( $temp, array( 'id' => (int)$request_data['id'] ) );
			(new KCPatientEncounter())->update([
				'encounter_date' => $appointment_date,
				'patient_id'             => $patient_id,
				'doctor_id'              => $doctor_id,
				'clinic_id'              => $clinic_id,
				'description'            => esc_sql($request_data['description']),
			], ['appointment_id' => $appointment_id]);
            $encounter_id = (new KCPatientEncounter())->get_var(['appointment_id' => $appointment_id],'id');
            do_action('kc_encounter_update', $encounter_id);
            if (isset($request_data['custom_fields_data']) && $request_data['custom_fields_data'] !== []) {
                kvSaveCustomFields('appointment_module',$appointment_id, $request_data['custom_fields_data']);
            }
			$message = esc_html__('Appointment has been updated successfully', 'kc-lang');
            $reminder_setting = get_option(KIVI_CARE_PREFIX . 'email_appointment_reminder', true);
            if(gettype($reminder_setting) !== 'boolean' && !empty($reminder_setting['time'])){
                $msg_reminder_table = $wpdb->prefix . "kc_appointment_reminder_mapping";
                $temp = [
                    'sms_status' => 0,
                    'email_status' => 0,
                    'whatsapp_status'=> 0
                ];
                $wpdb->update($msg_reminder_table, $temp, ['appointment_id' => (int)$request_data['id']]);
            }

            if($beforeUpdateAppointmentData->appointment_start_date == $appointment_start_date
              && $beforeUpdateAppointmentData->appointment_start_time == $appointment_start_time &&
                $beforeUpdateAppointmentData->status == $appointment_status && (!isset($request_data['custom_fields_data']) && $request_data['custom_fields_data'] == [])){
                wp_send_json([
                    'status'  => true,
                    'message' => $message,
                    'notification' =>$notification,
                ]);
            }

            if(!empty($appointment_id) && $appointment_id !== 0) {
				// hook for appointment update
				do_action( 'kc_appointment_update', $appointment_id );
			}

		} else {


			$temp['created_at'] = current_time('Y-m-d H:i:s');

            $checkAppointmentData =  $this->db->get_row("SELECT * FROM {$appointment_table_name} WHERE appointment_start_date='{$appointment_start_date}' AND appointment_start_time='{$appointment_start_time}' AND appointment_end_date='{$appointment_date}' AND appointment_end_time='{$appointment_end_time}' AND clinic_id={$clinic_id} AND doctor_id={$doctor_id} AND status != '0'");

            if(!empty($checkAppointmentData) ) {
				$message = __('Appointment Already Booked For This Time Slot.','kc-lang');
				wp_send_json([
					'status'  => false,
					'message' => $message,
				]);
			}

			$appointment_id = (int)$appointment->insert( $temp );

			// if appointment is not successfully created. (WP Error handle) 
			if(is_wp_error($appointment_id) || empty($appointment_id) ) {
				$message = __('Appointment booking Failed. Please try again.','kc-lang');
				wp_send_json([
					'status'  => false,
					'message' => $message,
				]);
			}

            $message = esc_html__('Appointment is Successfully booked.','kc-lang');
			if (isset($request_data['custom_fields_data']) && $request_data['custom_fields_data'] !== []) {
                kvSaveCustomFields('appointment_module',$appointment_id, $request_data['custom_fields_data']);
            }
            if($proPluginActive && !empty($request_data['tax'])){
                apply_filters('kivicare_save_tax_data', [
                    'type' => 'appointment',
                    'id' => $appointment_id,
                    'tax_data' => $request_data['tax']
                ]);
            }
		}

        $telemed_service_include = false;
		if (gettype($request_data['visit_type']) === 'array') {
            $telemed_service_in_appointment_service = collect($request_data['visit_type'])->map(function ($v)use($request_data,$clinic_id){
                $temp_service_id = (int)$v['service_id'];
                return $this->db->get_var("SELECT telemed_service FROM {$this->db->prefix}kc_service_doctor_mapping WHERE service_id = {$temp_service_id} AND clinic_id ={$clinic_id} AND doctor_id=".(int)$request_data['doctor_id']['id']);
            })->toArray();
			foreach ($request_data['visit_type'] as $key => $value) {

//			    $service = strtolower($value['name']);


				// generate zoom link request (Telemed AddOn filter)
			    if ($value['telemed_service'] === 'yes') {

                    if ($telemedZoomPluginActive || $telemedGooglemeetPluginActive) {

                        $request_data['appointment_id'] = $appointment_id;
                        $request_data['time_slot'] = $time_slot;

                        if($request_data['payment_mode'] !== 'paymentWoocommerce'){
                            if(kcCheckDoctorTelemedType($appointment_id) == 'googlemeet'){
                                $res_data = apply_filters('kcgm_save_appointment_event', ['appoinment_id' => $appointment_id,'service' => kcServiceListFromRequestData($request_data)]);
                            }else{
                                $res_data = apply_filters('kct_create_appointment_meeting', $request_data);
                            }                         
                            // if zoom meeting is not created successfully
                            if(empty($res_data['status'])) {
                                if(empty($request_data['id'])){
                                    ( new KCAppointmentServiceMapping() )->delete( [ 'appointment_id' => (int)$appointment_id] );
                                    ( new KCAppointment() )->delete( [ 'id' =>  (int)$appointment_id] );
                                    do_action('kc_appointment_cancel',$appointment_id);
                                }
                                wp_send_json([
                                    'status'  => false,
                                    'message' => esc_html__($res_data['message'], 'kc-lang'),
                                    'error' => $res_data
                                ]);

                            }
                            $telemed_service_include = true;
                        }
                    }
                }

				if(!empty($appointment_id) && $appointment_id !== 0){
                    if(!$oldMappingServiceDelete){
                        ( new KCAppointmentServiceMapping() )->delete( [ 'appointment_id' => $appointment_id ] );
                        $oldMappingServiceDelete = true;
                    }
					(new KCAppointmentServiceMapping())->insert([
						'appointment_id' => (int)$appointment_id,
						'service_id' => (int)$value['service_id'],
						'created_at' => current_time('Y-m-d H:i:s'),
						'status'=> 1
					]);
				}
			}

		}

        if((string)$request_data['status'] == '0'){
            if(!empty( $request_data['id'])) {
                kcAppointmentCancelMail($beforeUpdateAppointmentData);
                
                 //zoom telemed entry delete
                 if (isKiviCareTelemedActive()) {
                    apply_filters('kct_delete_appointment_meeting', ['id'=>$appointment_id]);
                }

                //googlemeet telemed entry delete
                if(isKiviCareGoogleMeetActive()){
                    apply_filters('kcgm_remove_appointment_event',['appoinment_id' => $appointment_id]);
                }

                //google calendar event delete
                if(kcCheckGoogleCalendarEnable()){
                    apply_filters('kcpro_remove_appointment_event', ['appoinment_id'=>$appointment_id]);
                }
                do_action('kc_appointment_cancel',$appointment_id);
            }
        }

        if ( in_array((string)$request_data['status'],['4']) ) {
            KCPatientEncounter::createEncounter($appointment_id);
            KCBillItem::createAppointmentBillItem($appointment_id);
        }

        if(empty( $request_data['id'] )) {
            // hook for appointment booked
            do_action( 'kc_appointment_book', $appointment_id );
        }

        switch($request_data['payment_mode']){
            case 'paymentWoocommerce':
                $woocommerce_response  = kcWoocommerceRedirect($appointment_id, $request_data);
                if(isset($woocommerce_response['status']) && $woocommerce_response['status']) {
                    if(!empty($woocommerce_response['woocommerce_cart_data'])) {
                        wp_send_json($woocommerce_response);
                    }
                }
                break;
            case 'paymentPaypal':
                $this->db->update($this->db->prefix."kc_appointments",['status' => 0],['id' => $appointment_id]);
                $paypal_response = (new KCPaymentController())->makePaypalPayment($request_data,$appointment_id);
                if(empty($paypal_response['status'])) {
                    (new KCAppointment())->loopAndDelete(['id' => $appointment_id],true);
                }

                $paypal_response['appointment_id'] = $appointment_id;
                $paypal_response['data'] = $request_data;
                wp_send_json($paypal_response);
                break;
            case 'paymentRazorpay':
                $this->db->update($this->db->prefix."kc_appointments",['status' => 0],['id' => $appointment_id]);
                $request_data['appointment_id'] = $appointment_id;
                $request_data['page'] = 'dashboard';
                $razorpay_response = apply_filters('kivicare_create_razorpay_order',$request_data);
                if(is_array($razorpay_response) && array_key_exists('checkout_detail',$razorpay_response) && !empty($razorpay_response['status'])){
                    $razorpay_response['appointment_id'] = $appointment_id;
                    $razorpay_response['data'] = $request_data;
                    wp_send_json($razorpay_response);
                }else{
                    (new KCAppointment())->loopAndDelete(['id' => $appointment_id],true);
                    wp_send_json(['status' => false,
                        'message' => esc_html__('Failed to create razorpay payment link','kc-lang'),
                        'error_message' => is_array($razorpay_response) && !empty($razorpay_response['message']) ? $razorpay_response['message'] : '']);
                }
                break;
            case 'paymentStripepay':
                $this->db->update($this->db->prefix."kc_appointments",['status' => 0],['id' => $appointment_id]);
                $stripepay_response = apply_filters('kivicare_create_stripepay_order',[],$request_data,$appointment_id);
                $request_data['page'] = 'dashboard';
                if(empty($stripepay_response['status'])) {
                    (new KCAppointment())->loopAndDelete(['id' => $appointment_id],true);
                }
                $stripepay_response['appointment_id'] = $appointment_id;
                $stripepay_response['data'] = $request_data;
                wp_send_json($stripepay_response);
                break;
            case 'paymentOffline':
                if(!(in_array($request_data['status'],[0,2]))){
                    $service_name = kcServiceListFromRequestData($request_data);
                    if($proPluginActive || $telemedZoomPluginActive || $telemedGooglemeetPluginActive){
                        $notification = kcProAllNotification($appointment_id,$service_name,$telemed_service_include);
                    }else{
                        $notification = kivicareCommonSendEmailIfOnlyLitePluginActive($appointment_id,$service_name);
                    }
                }
                break;
        }
		if(!empty($appointment_id) && $appointment_id !== 0) {
			wp_send_json([
				'status'  => true,
				'message' => $message,
                'notification' =>$notification,
			]); 
		} else {
			$message = esc_html__('Appointment booking Failed. Please try again.', 'kc-lang');
			wp_send_json([
				'status'  => false,
				'message' => $message,
                'notification' =>$notification,
			]); 
		}
		
	}

	public function delete() {

		if ( ! kcCheckPermission( 'appointment_delete' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}

			$id = (int)$request_data['id'];

            if(!((new KCAppointment())->appointmentPermissionUserWise($id))){
				wp_send_json( kcUnauthorizeAccessResponse(403) );
			}

            $results = ( new KCAppointment() )->loopAndDelete( [ 'id' => $id ],true );

			if ( $results ) {
                // hook for appointment after cancelled
				wp_send_json( [
					'status'  => true,
					'message' => esc_html__('Appointment is deleted successfully', 'kc-lang'),
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

	public function updateStatus() {

		if ( ! kcCheckPermission( 'appointment_edit' ) ) {
			wp_send_json( kcUnauthorizeAccessResponse(403) );
		}

		$request_data = $this->request->getInputs();

		$rules  = [
			'appointment_id'     => 'required',
			'appointment_status' => 'required',

		];
		$errors = kcValidateRequest( $rules, $request_data );

		if ( count( $errors ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => $errors[0]
			] );
		}

        if(!((new KCAppointment())->appointmentPermissionUserWise($request_data['appointment_id']))){
            wp_send_json( kcUnauthorizeAccessResponse(403) );
        }
        
        $current_date = current_time("Y-m-d H:i:s");
        $appointment_id = (int)$request_data['appointment_id'];
        $appointment_table_name = $this->db->prefix . 'kc_appointments';
        
        if($this->getLoginUserRole() == $this->getPatientRole()){
        $appointmentData =  $this->db->get_row("SELECT * FROM {$appointment_table_name} WHERE id={$appointment_id}");
            $kcGetCancellationBuffer = kcGetCancellationBufferData($current_date, $appointmentData->appointment_start_date, $appointmentData->appointment_start_time);
            if($kcGetCancellationBuffer === false){
                $message = esc_html__('This Appointment can not be edited.', 'kc-lang');
                wp_send_json([
                    'status'  => true,
                    'message' => $message,
                    'notification' => '',
                ]);
            }
        }

		try {

            $request_data['appointment_id'] = (int)$request_data['appointment_id'];
            $request_data['appointment_status'] = (string)$request_data['appointment_status'];
			if ( in_array($request_data['appointment_status'],['4']) ) {
				KCPatientEncounter::createEncounter( $request_data['appointment_id'] );
				KCBillItem::createAppointmentBillItem($request_data['appointment_id']);
                kcCommonEmailFunction($request_data['appointment_id'], 'kivicare', 'patient_clinic_check_in_check_out');
			}
			if (in_array($request_data['appointment_status'] ,['3','0']) ) {
				KCPatientEncounter::closeEncounter( $request_data['appointment_id'],$request_data['appointment_status'] );
			}

            if($request_data['appointment_status'] === '0'){
                $appointmentData   = ( new KCAppointment() )->get_by(['id' => $request_data['appointment_id'] ], '=', true);
                kcAppointmentCancelMail($appointmentData);

                //zoom telemed entry delete
                if (isKiviCareTelemedActive()) {
                    apply_filters('kct_delete_appointment_meeting', ['id'=> $request_data['appointment_id']]);
                }

                //google calendar event delete
                if(kcCheckGoogleCalendarEnable()){
                    apply_filters('kcpro_remove_appointment_event', ['appoinment_id'=>$request_data['appointment_id']]);
                }

                //googlemeet telemed entry delete
                if(isKiviCareGoogleMeetActive()){
                    apply_filters('kcgm_remove_appointment_event',['appoinment_id' => $request_data['appointment_id']]);
                }
                do_action('kc_appointment_cancel',$request_data['appointment_id']);
            }

            ( new KCAppointment() )->update( [ 'status' => $request_data['appointment_status'] ], array( 'id' => $request_data['appointment_id'] ) );

            if($request_data['appointment_status'] === '1'){
                kivicareWoocommercePaymentComplete($request_data['appointment_id'],'status_update');
            }
            
            do_action( 'kc_appointment_status_update', $request_data['appointment_id'] , $request_data['appointment_status'] );

			wp_send_json( [
				'status'  => true,
				'message' => esc_html__('Appointment status is updated successfully', 'kc-lang')
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

	public function getAppointmentSlots() {

		$request_data = $this->request->getInputs();

		if($this->getLoginUserRole() === $this->getDoctorRole()) {
			$request_data['doctor_id'] = get_current_user_id();
		}

        if(isKiviCareProActive()){
            if($this->getLoginUserRole() === $this->getReceptionistRole()) {
                $request_data['clinic_id'] = kcGetClinicIdOfReceptionist();
            }
            if($this->getLoginUserRole() === $this->getClinicAdminRole()){
                $request_data['clinic_id'] = kcGetClinicIdOfClinicAdmin();
            }
        }

		$rules = [
			'date'      => 'required|date',
			'clinic_id' => 'required',
			'doctor_id' => 'required',

		];

		$message = [
			'clinic_id' => esc_html__('Clinic is required', 'kc-lang'),
			'doctor_id' => esc_html__('Doctor is required', 'kc-lang'),
		];

		$errors = kcValidateRequest( $rules, $request_data, $message );

		if ( count( $errors ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => $errors[0]
			] );
		}

		try {

			$slots = kvGetTimeSlots( $request_data,"",true );
			wp_send_json( [
				'status'  => true,
				'message' => esc_html__('Appointment slots', 'kc-lang'),
				'data'    => $slots
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

	public function getAppointmentQueue() {

        if ( ! kcCheckPermission( 'appointment_list' ) ) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
		$request_data = $this->request->getInputs();
		$filterData = isset( $request_data['filterData'] ) ? stripslashes( $request_data['filterData'] ) : [];
		$filterData =  json_decode($filterData, true);
		$request_data['filterData'] =  kcRecursiveSanitizeTextField($filterData);
		$proPluginActive =isKiviCareProActive();
		$appointments_table = $this->db->prefix . 'kc_appointments';
		$users_table   = $this->db->base_prefix . 'users';
		$clinics_table = $this->db->prefix . 'kc_clinics';
        $encounter_table = $this->db->prefix . 'kc_patient_encounters';
        $data_filter = '';
		if (isset( $request_data['start']) && isset( $request_data['end'])) {
            $start_date = $this->appointmentFilterDateFormat($request_data['start']);
            $end_date = $this->appointmentFilterDateFormat($request_data['end']);
            if(!empty($end_date) && !empty($start_date)){
                $data_filter  = " AND {$appointments_table}.appointment_start_date BETWEEN '{$start_date}' AND '{$end_date}' "  ;
            }
        } elseif ( !empty($request_data['filterData']['date']) && !empty($request_data['filterData']['status']) && $request_data['filterData']['status'] !== 'all') {
			if(!empty($request_data['filterData']['date']['start']) && !empty($request_data['filterData']['date']['end'])) {
				$start_date = $this->appointmentFilterDateFormat($request_data['filterData']['date']['start']);
				$end_date = $this->appointmentFilterDateFormat($request_data['filterData']['date']['end']);
                if(!empty($start_date) && !empty($end_date)){
                    $data_filter  = " AND {$appointments_table}.appointment_start_date BETWEEN '{$start_date}' AND '{$end_date}' "  ;
                }
			} else if($request_data['filterData']['status'] == 1) {
                $date = $this->appointmentFilterDateFormat($request_data['filterData']['date']);
                if(!empty($date)){
                    $data_filter  = " AND {$appointments_table}.appointment_start_date >= '{$date}' " ;
                }
			}
        }
        elseif(!empty($request_data['filterData']['status']) && $request_data['filterData']['status'] == 'past'){
            $data_filter  = " AND {$appointments_table}.appointment_start_date < CURDATE() " ;
        } elseif (!empty($request_data['filterData']['date'])) {
            if(!empty($request_data['filterData']['date']['start']) && !empty($request_data['filterData']['date']['end'])) {
                $start_date = $this->appointmentFilterDateFormat($request_data['filterData']['date']['start']);
                $end_date = $this->appointmentFilterDateFormat($request_data['filterData']['date']['end']);
                if(!empty($start_date) && !empty($end_date)){
                    $data_filter  = " AND {$appointments_table}.appointment_start_date BETWEEN '{$start_date}' AND '{$end_date}' "  ;
                }
            }else{
                $date = $this->appointmentFilterDateFormat($request_data['filterData']['date']);
                if(!empty($date)){
                    $data_filter  = " AND {$appointments_table}.appointment_start_date = '{$date}' " ;
                }
            }
        }

		$query = " SELECT DISTINCT {$appointments_table}.*,  
		       doctors.display_name  AS doctor_name,
		       patients.display_name AS patient_name,
		       CONCAT({$clinics_table}.address, ', ', {$clinics_table}.city,', '
		           ,{$clinics_table}.postal_code,', ',{$clinics_table}.country) AS clinic_full_address,
		       {$clinics_table}.name AS clinic_name,
               {$clinics_table}.extra AS clinic_extra,
               {$encounter_table}.id as encounter_id
			FROM  {$appointments_table}
		       LEFT JOIN {$users_table} doctors
		              ON {$appointments_table}.doctor_id = doctors.id
			   LEFT JOIN {$encounter_table}
			          ON {$appointments_table}.id={$encounter_table}.appointment_id
		       LEFT JOIN {$users_table} patients
		              ON {$appointments_table}.patient_id = patients.id
		       LEFT JOIN {$clinics_table}
		              ON {$appointments_table}.clinic_id = {$clinics_table}.id
			WHERE 0 = 0 " . $data_filter ;


        $current_login_user_role = $this->getLoginUserRole();
        $current_login_user_id = get_current_user_id();

        $clinic_id = $doctor_id = $patient_id = '';

        if($this->getClinicAdminRole() == $current_login_user_role){
            $clinic_id =  kcGetClinicIdOfClinicAdmin();
        }else if($this->getReceptionistRole() == $current_login_user_role){
            $clinic_id =  kcGetClinicIdOfReceptionist();
        }else{
            if(isKiviCareProActive() && !empty($request_data['filterData']['clinic_id']['id'])  ){
                $clinic_id  = $request_data['filterData']['clinic_id']['id'];
            }
        }


        if($this->getDoctorRole() === $current_login_user_role){
            $doctor_id = $current_login_user_id;
        }else{
            if(!empty($filterData['doctor_id']['id'])){
                $doctor_id = $filterData['doctor_id']['id'];
            }
        }


        if($this->getPatientRole() == $current_login_user_role){
            $patient_id = $current_login_user_id;
        }else{
            if ( !empty($filterData['patient_id']['id']) ) {
                $patient_id = $filterData['patient_id']['id'];
            }
        }


        //user wise conditions
        if(!empty($clinic_id)){
            $query .= " AND {$appointments_table}.clinic_id = " . (int)$clinic_id;
        }
        if(!empty($doctor_id)){
            $query .= " AND {$appointments_table}.doctor_id = " . (int)$doctor_id;
        }
        if(!empty($patient_id)){
            $query .= " AND {$appointments_table}.patient_id = " .(int)$patient_id;
        }

        $current_date = current_time("Y-m-d");

        if ( !empty($filterData['status']) ) {
            if (isset($filterData['status']['value'])) {
                if($filterData['status']['value'] !== 'all'){
                    $filterData['status']['value'] = esc_sql($filterData['status']['value']);
                    $query .= " AND {$appointments_table}.status = {$filterData['status']['value']} AND {$appointments_table}.appointment_start_date >= '{$current_date}' ";
                }
            } else if($filterData['status'] == 1){
                $query .= " AND {$appointments_table}.status  IN (1,4) ";
            }
		}

		$counts = $this->db->get_results($query);
		$count = !empty($counts) && count($counts) > 0 ? count($counts) : 0;

        //order by condition
        $query .= " ORDER BY {$appointments_table}.appointment_start_date ASC , {$appointments_table}.appointment_start_time ASC";

        if(!empty($filterData['pagination'])){
            $limit = 10;
            $offset = ((int)$filterData['pagination'] - 1) * $limit;
            $query .= " LIMIT {$limit} OFFSET {$offset} ";
        }
//
//        echo  $query;die;

		$appCollection = collect( $this->db->get_results( $query, OBJECT ) )->unique( 'id' );

       
        if (count($appCollection)) {

            $appointment_ids = $appCollection->pluck('id')->implode(',');

            $zoom_mappings = apply_filters('kct_get_meeting_list', [
                'appointment_ids' => $appointment_ids
            ]);

            if (isset($zoom_mappings['appointment_ids'])) {
                $zoom_mappings = collect([]);
            }


//            $telemedPluginActive = isKiviCareTelemedActive();
            $googleMeetPluginActive = isKiviCareGoogleMeetActive();
            $is_patient_enable = in_array((string)get_option( KIVI_CARE_PREFIX . 'patient_cal_setting',true),['1','true']) ? 'on' : 'off';
            $enableAppointmentDescription = get_option(KIVI_CARE_PREFIX.'appointment_description_config_data');
            $enableAppointmentDescription = gettype($enableAppointmentDescription) == 'boolean' ? 'on' : $enableAppointmentDescription;
            $currency_detail = kcGetClinicCurrenyPrefixAndPostfix();
            $currency_prefix = !empty($currency_detail['prefix']) ? $currency_detail['prefix'] : '' ;
            $currency_postfix = !empty($currency_detail['postfix']) ? $currency_detail['postfix'] : '' ;
            $current_date = current_time("Y-m-d H:i:s");
            $custom_forms = apply_filters('kivicare_custom_form_list',[],['type' => 'appointment_module']);

            $appointments = $appCollection->map(function ($appointment) use ($custom_forms,$current_date,$currency_prefix,$currency_postfix,$zoom_mappings,$is_patient_enable,$current_login_user_role,$googleMeetPluginActive,$proPluginActive,$enableAppointmentDescription) {
                $date = date("Y-m-d H:i:s", strtotime(date("c", strtotime($appointment->appointment_start_date . $appointment->appointment_start_time))));
                $appointments_table = $this->db->prefix . 'kc_appointments';
                $appointments_service_table = $this->db->prefix . 'kc_appointment_service_mapping';
                $service_table = $this->db->prefix . 'kc_services';
                $service_doctor_table = $this->db->prefix . 'kc_service_doctor_mapping';
                
                $appointment->clinic_name = decodeSpecificSymbols($appointment->clinic_name);

                $appointment->appointment_start_date = $appointment->appointment_start_date;
                $appointment->appointment_formated_start_date = kcGetFormatedDate($appointment->appointment_start_date);
                $appointment->appointment_end_date = $appointment->appointment_end_date;
                $appointment->appointment_start_time = kcGetFormatedTime(date('h:i A', strtotime($appointment->appointment_start_time)));
                $appointment->appointment_end_time = kcGetFormatedTime(date('h:i A', strtotime($appointment->appointment_end_time)));
                $appointment->payment_mode = kcAppointmentPaymentMode($appointment->id);
                $patient_mobile_number = ' -';
                $patient_user_meta = get_user_meta( $appointment->patient_id, 'basic_data', true );
                if(!empty($patient_user_meta)){
                    $patient_user_meta = json_decode($patient_user_meta);
                    $patient_mobile_number = (!empty($patient_user_meta->mobile_number) ? $patient_user_meta->mobile_number : '  - ');
                }
                
                $appointment->cancellation_buffer = kcGetCancellationBufferData($current_date, $appointment->appointment_start_date, $appointment->appointment_start_time);
                $appointment->patient_contact_no = $patient_mobile_number;
                $patient_profile_image = get_user_meta( $appointment->patient_id, 'patient_profile_image', true );
                $appointment->patient_profile_image = (!empty($patient_profile_image) ? wp_get_attachment_url($patient_profile_image) : '');
                $appointment->clinic_id = [
                    'id' => $appointment->clinic_id,
                    'label' => $appointment->clinic_name
                ];
                $appointment->doctor_id = [
                    'id' => $appointment->doctor_id,
                    'label' => $appointment->doctor_name,
                ];
                $appointment->patient_id = [
                    'id' => $appointment->patient_id,
                    'label' => $appointment->patient_name??""
                ];

                if(!empty($appointment->encounter_id)){
                    $appointment->encounter_detail = $this->db->get_row("SELECT * FROM {$this->db->prefix}kc_patient_encounters WHERE id={$appointment->encounter_id} ");
                }
                $zoom_data = $zoom_mappings->where('appointment_id', (int)$appointment->id)->first();

                if ($googleMeetPluginActive) {
                    $googlemeet_data = $this->db->get_var("SELECT url FROM {$this->db->prefix}kc_appointment_google_meet_mappings WHERE appointment_id=" . (int)$appointment->id);
                    if (!empty($googlemeet_data)) {
                        if(empty($zoom_data)){
                            $zoom_data = (object)[];
                        }
                        $zoom_data->join_url =  $zoom_data->start_url =  $googlemeet_data;
                    }
                }

                $appointment->custom_forms = $custom_forms;
                $appointment->zoom_data = $zoom_data;
                $appointment->clinic_prefix = $currency_prefix;
                $appointment->clinic_postfix = $currency_postfix;
                $appointment->video_consultation = !empty($zoom_data);

                $get_service_query = "SELECT {$appointments_table}.id,{$service_table}.name AS service_name,{$service_table}.id AS service_id,{$service_doctor_table}.charges FROM {$appointments_table}
				LEFT JOIN {$appointments_service_table} ON {$appointments_table}.id = {$appointments_service_table}.appointment_id JOIN {$service_table} 
				ON {$appointments_service_table}.service_id = {$service_table}.id JOIN {$service_doctor_table} ON {$service_doctor_table}.service_id ={$service_table}.id 
                and {$service_doctor_table}.clinic_id={$appointment->clinic_id['id']}
                 and {$service_doctor_table}.doctor_id={$appointment->doctor_id['id']} and {$appointments_service_table}.appointment_id = {$appointment->id}  WHERE 0 = 0";
                $services = $this->db->get_results($get_service_query, OBJECT);
                $service_array = $service_list = [];
                $service_charges = 0;

                foreach ($services as $service) {
                    $service_array[] = $service->service_name;
                    $service_list[] = [
                        'service_id' => $service->service_id,
                        'name' => $service->service_name,
                        'charges' => round((float)$service->charges, 3)
                    ];
                    $service_charges += $service->charges;
                }

                $appointment->descriptionEnable = $enableAppointmentDescription;
                $appointment->all_service_charges = $service_charges;
                $appointment->all_services = implode(", ", $service_array);
                $appointment->visit_type_old = $appointment->visit_type;
                $appointment->visit_type = $service_list;
                $appointment->service_array = $service_array;

                $custom_field_data = [];
                if($proPluginActive){
                    $custom_field_data = kcGetCustomFields('appointment_module', $appointment->id, (int)$appointment->doctor_id['id']);
                }
                $appointment->custom_fields = $custom_field_data;

                if (!empty($appointment->appointment_report)) {
                    $report = json_decode($appointment->appointment_report);
                    if (is_array($report) && count($report) > 0)
                        $appointment->appointment_report = array_map(function ($v) {
                            $name = !empty(get_the_title($v)) ? get_the_title($v) : '';
                            $url = !empty(wp_get_attachment_url($v)) ? wp_get_attachment_url($v) : '';
                            return ['name' => $name,'url' => $url ];
                        }, $report);
                }

                $appointment->tax = [];
                //tax calculate
                if($proPluginActive){

                    $tax = apply_filters('kivicare_calculate_tax', [
                        'status' => false,
                        'message' => '',
                        'data' => []
                    ], [
                        "id" => $appointment->id,
                        "type" => 'appointment',
                    ]);

                    if(!empty($tax['data']) && is_array($tax['data'])){
                        $appointment->all_service_charges += $tax['tax_total'];
                        $appointment->tax = $tax['data'];
                    }
                    $appointment->all_service_charges = round($appointment->all_service_charges, 3);
                }

                if ($appointment->status == '1' && $proPluginActive &&
                    $is_patient_enable == 'on' && $this->getPatientRole() === $current_login_user_role ) {

                    $appointment_data = [
                        "clinic_name" => decodeSpecificSymbols($appointment->clinic_name),
                        "clinic_address" => $appointment->clinic_full_address,
                        "id" => $appointment->id,
                        "start_date" => $appointment->appointment_start_date,
                        "start_time" => $appointment->appointment_start_time,
                        "end_date" => $appointment->appointment_end_date,
                        "end_time" => $appointment->appointment_end_time,
                        "appointment_service" => $appointment->all_services,
                        "extra" => $appointment
                    ];

                    $appointment->calendar_content = kcAddToCalendarContent($appointment_data);
                }

                // $appointment->appointment_start_date = kcGetFormatedDate($appointment->appointment_start_date);
                // $appointment->appointment_end_date = $appointment->appointment_end_date;
                // $appointment->appointment_start_time = kcGetFormatedTime($appointment->appointment_start_time);
                // $appointment->appointment_end_time = kcGetFormatedTime($appointment->appointment_end_time);
                

                $appointment->isEditAble = $date > $current_date;
                return apply_filters('kivicare_appointment_queue_lists',$appointment);

            })->values();
        
        } else {
            $appointments = [];
        }

		wp_send_json( [
			'status'  => true,
			'message' => esc_html__('Appointments', 'kc-lang'),
			'data'    => $appointments,
            'total_rows' => $count,
			'nextPage' => (!empty($request_data['page']) ? (int)$request_data['page'] + 1 :  1 ),
		] );

	}

	public function allAppointment() {

		if( ! kcCheckPermission('appointment_list')){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
		$request_data = $this->request->getInputs();
		$condition    = '';
		$appointments_table = $this->db->prefix . 'appointments';

		$users_table   = $this->db->base_prefix . 'users';
		$static_data_table  = $this->db->prefix . 'static_data';

		if ( $request_data['searchKey'] && $request_data['searchValue']) {
            $request_data['searchKey'] = esc_sql($request_data['searchKey']);
            $request_data['searchValue'] = esc_sql($request_data['searchValue']);
			$condition = " WHERE {$request_data['searchKey']} LIKE  '%{$request_data['searchValue']}%' ";
		}

        $request_data['limit'] = (int)$request_data['limit'];
        $request_data['offset'] = (int)$request_data['offset'];
		$query = "
			SELECT {$appointments_table}.*,
		       doctors.display_name  AS doctor_name,
		       patients.display_name AS patient_name
		       
			FROM  {$appointments_table}
		       LEFT JOIN {$users_table} doctors
		            ON {$appointments_table}.doctor_id = doctors.id
		       LEFT JOIN {$users_table} patients
		            ON {$appointments_table}.patient_id = patients.id
		       {$condition}  
		       ORDER BY {$appointments_table}.appointment_start_date DESC LIMIT {$request_data['limit']} OFFSET {$request_data['offset']}";

		$appointments = collect( $this->db->get_results( $query, OBJECT ) )->unique( 'id' );

		$appointment_count_query = "SELECT count(*) AS count FROM {$appointments_table}";

		$total_appointment = $this->db->get_results( $appointment_count_query, OBJECT );

		if ( $request_data['searchKey'] && $request_data['searchValue'] ) {
			$total_rows = count( $appointments );
		} else {
			$total_rows = $total_appointment[0]->count;
		}

		if ( $total_rows < 0 ) {
			wp_send_json( [
				'status'  => false,
				'message' => esc_html__('No appointment found', 'kc-lang'),
				'data'    => []
			] );
		}

		$visit_type_data =  $appointments->pluck('visit_type')->unique()->implode("','");

		$static_data_query = " SELECT * FROM $static_data_table WHERE value IN ('$visit_type_data') ";

		$static_data = collect($this->db->get_results( $static_data_query, OBJECT ))->pluck('label','value')->toArray();

		foreach ($appointments as $key => $appointment) {
			$appointment->type_label = $static_data[$appointment->visit_type];
		}

		wp_send_json( [
			'status'  => true,
			'message' => esc_html__('Appointments', 'kc-lang'),
			'data'    => $appointments,
			'total_rows' => $total_rows
		] );
	}

	public function delete_multiple(){

		if ( ! kcCheckPermission( 'appointment_delete' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();
        $woocommerce_plugin_active = iskcWooCommerceActive();
		try {
			if(!empty($request_data)){
                $results = false;
				foreach($request_data['id'] as $id){
					if(!empty($id)){
                        $id = (int)$id;

                        if(!((new KCAppointment())->appointmentPermissionUserWise($id))){
	                        wp_send_json( kcUnauthorizeAccessResponse(403));
                        }
                        $results = ( new KCAppointment() )->loopAndDelete( [ 'id' => $id ],true );
					}else{
						wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
                    }
				}
				if ( $results ) {
					// hook for appointment after cancelled
					wp_send_json( [
						'status'  => true,
						'message' => esc_html__('Selected Appointment is deleted successfully', 'kc-lang'),
					] );
				} else {
					wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
				}
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

    public function uploadMedicalReport(){
        $parameters = $this->request->getInputs();
        $attach = [];
        $status = false;
        if(isset($parameters['file_multi']) && $parameters['file_multi'] !== '' && $parameters['file_multi'] != null ) {
            $status =true;
            array_walk($_FILES["file_multi"],function($item1, $key){
                if(is_array($item1)){
                    if($key === 'name'){
                        $item1 = array_map(function ($v){
                            return sanitize_file_name($v);
                        },$item1);
                    }
                    $supported_type = get_allowed_mime_types();
                    if($key === 'type' && !empty($supported_type) &&  is_array($supported_type)){
                        $item1 = array_map(function ($v) use($supported_type){
                            if(!in_array($v,$supported_type)){
                                wp_send_json([
                                    'status'  => false,
                                    'message' => esc_html__( "Failed to upload Medical report.File Type not supported" , 'kc-lang' ),
                                    'data'    => ''
                                ]);
                            }
                            return $v;
                        },$item1);
                    }
                }
                return $item1;
            });
            $files = $_FILES["file_multi"];
            foreach ($files['name'] as $key => $value) {
                if ($files['name'][$key]) {
                    $file = array(
                        'name' => $files['name'][$key],
                        'type' => $files['type'][$key],
                        'tmp_name' => $files['tmp_name'][$key],
                        'error' => $files['error'][$key],
                        'size' => $files['size'][$key]
                    );
                    $_FILES = array("upload_file" => $file);
                    $attachment_id = media_handle_upload("upload_file", 0);
                    $attach[]      = $attachment_id;
                    if(is_wp_error($attachment_id)) {
                        foreach ($attach as $att){
                            wp_delete_attachment($att);
                        }
                        $attach = [];
                        wp_send_json([
                            'status'  => false,
                            'message' => esc_html__( "Failed to upload Medical report." , 'kc-lang' ),
                            'data'    => ''
                        ]);
                    }
                }
            }
        }

        ob_start();
        ?>
        <div>
            <h6 class="iq-letter-spacing-1"><?php echo esc_html__('Uploaded files', 'kc-lang'); ?></h6>
            <div class="iq-card iq-preview-details">
                <table class="iq-table-border" style="border: 0;">
                    <?php
                    foreach ($attach as $key => $file){
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
        $html = ob_get_clean();
        wp_send_json([
            'status'  => $status,
            'message' => $status ?  esc_html__( "Medical report uploaded successfully." , 'kc-lang' ) : esc_html__( "Failed to upload Medical report." , 'kc-lang' ) ,
            'data'    => $attach,
            'html'    => $html
        ]);

    }

    public function appointmentFilterDateFormat($date){
        try {
            return esc_sql((new DateTime($date))->format('Y-m-d'));
        } catch (Exception $e) {
            return '';
        }
    }
    public function getAppointmentDetails() {

        if ( ! kcCheckPermission( 'appointment_list' ) ) {
            wp_send_json(kcUnauthorizeAccessResponse());
        }

        $request_data = $this->request->getInputs();

        if (isset($request_data['appointment_data'])) {
            $appointment_data_json = stripslashes($request_data['appointment_data']);
            $decoded_appointment_data = json_decode($appointment_data_json, true);

            if (isset($decoded_appointment_data['appointment_start_date'])) {
                $decoded_appointment_data['appointment_start_date'] = kcGetFormatedDate($decoded_appointment_data['appointment_start_date']);
            }

            $request_data['appointment_data'] = $decoded_appointment_data;
        }

        wp_send_json([
            'status'  => false,
            'message' => esc_html__("Appointment Date.", 'kc-lang'),
            'data'    => $request_data
        ]);
    }
}
