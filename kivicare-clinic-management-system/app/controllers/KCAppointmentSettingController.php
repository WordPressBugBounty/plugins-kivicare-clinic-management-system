<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;

class KCAppointmentSettingController extends KCBase
{
    public $db;

    private $request;

	public $all_user;
    public function __construct() {

        global $wpdb;

        $this->db = $wpdb;

        $this->request = new KCRequest();

		$this->all_user = $this->KCGetRoles();
		$this->all_user[] = 'administrator';
        parent::__construct();
    }

    public function restrictAppointmentSave(){
	    if( $this->getLoginUserRole() !== 'administrator' ){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();
        $message = esc_html__('Failed to update', 'kc-lang');
        $status =false;
        if(isset($request_data['pre_book']) && isset($request_data['post_book'])){
            if((int)$request_data['pre_book'] < 0 && (int)$request_data['post_book'] < 0 ){
                wp_send_json( [
                    'status'  => false,
                    'message' => esc_html__('Pre or Post Book Days Must Be Greater than Zero ', 'kc-lang'),
                ] );
            }
            update_option(KIVI_CARE_PREFIX .'restrict_appointment',['post' => (int)$request_data['post_book'] ,'pre' =>(int)$request_data['pre_book']]);
            update_option(KIVI_CARE_PREFIX .'restrict_only_same_day_book_appointment', $request_data['only_same_day_book']);
            
            $status = true;
            $message = esc_html__('Appointment restrict days saved successfully', 'kc-lang');
        }
        wp_send_json( [
            'status'  => $status,
            'message' => $message,
        ] );
    }

    public function restrictAppointmentEdit(){
	    if( !in_array($this->getLoginUserRole(),$this->all_user) ){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        wp_send_json( [
            'status'  =>  true,
            'data' => kcAppointmentRestrictionData(),
        ] );
    }

    public function getMultifileUploadStatus(){
	    if( !in_array($this->getLoginUserRole(), $this->all_user) ){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $data = get_option(KIVI_CARE_PREFIX . 'multifile_appointment',true);

        if(gettype($data) != 'boolean'){
            $temp = $data;
        }else{
            $temp = 'off';
        }

        wp_send_json( [
            'status'  => true,
            'data' => $temp,
        ] );
    }

    public function saveMultifileUploadStatus(){

	    if( $this->getLoginUserRole() !== 'administrator' ){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }

        $request_data = $this->request->getInputs();
        $message = esc_html__('Failed to update', 'kc-lang');
        $status =false;
        if(isset($request_data['status']) && !empty($request_data['status']) ){
            $table_name = $this->db->prefix . 'kc_appointments';
            kcUpdateFields($table_name,[ 'appointment_report' => 'longtext NULL']);
            update_option(KIVI_CARE_PREFIX . 'multifile_appointment',$request_data['status']);
            $message = esc_html__('File Upload Setting Saved.', 'kc-lang');
            $status = true;
        }
        wp_send_json( [
            'status'  => $status ,
            'message' => $message,
        ] );
    }

    public function appointmentReminderNotificatioSave(){
	    if( $this->getLoginUserRole() !== 'administrator' ){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();
        $message = esc_html__('Failed to update', 'kc-lang');
        $status =false;
        if(isset($request_data['status']) && !empty($request_data['status']) && isset($request_data['time'])){
            update_option(KIVI_CARE_PREFIX . 'email_appointment_reminder',[
                    "status" =>$request_data['status'],
                    "time" =>$request_data['time'],
                    "sms_status"=>isset($request_data['sms_status']) ? $request_data['sms_status'] : 'off' ,
                    "whatapp_status" => isset($request_data['whatapp_status']) ? $request_data['whatapp_status']: 'off' ]
            );
            $message = esc_html__('Email Appointment Reminder Setting Saved', 'kc-lang');
            $status = true;
            if($request_data['status'] == 'off' && isset($request_data['sms_status']) && $request_data['sms_status'] == 'off' && isset($request_data['whatapp_status']) && $request_data['whatapp_status'] == 'off' ){
                wp_clear_scheduled_hook("kivicare_patient_appointment_reminder");
            }
        }
        wp_send_json( [
            'status'  => $status,
            'message' => $message,
        ] );
    }

    public function getAppointmentReminderNotification(){
	    if( !in_array($this->getLoginUserRole(), $this->all_user) ){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $data = get_option(KIVI_CARE_PREFIX . 'email_appointment_reminder',true);

        //create table
        require KIVI_CARE_DIR . 'app/database/kc-appointment-reminder-db.php';

        if(gettype($data) != 'boolean'){
            $temp = $data;
            if( !isKiviCareProActive()){
                if(is_array($temp ) ){
                    $temp['sms_status'] = 'off';
                    $temp['whatapp_status'] = 'off';
                }
            }
        }else{
            $temp = ["status" => 'off',"sms_status"=> 'off',"time" => '24',"whatapp_status" => 'off'];
        }

	    wp_send_json( [
            'status'  => true,
            'data' => $temp,
        ] );
    }

    public function appointmentTimeFormatSave(){
	    if( $this->getLoginUserRole() !== 'administrator' ){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();
        $message = esc_html__('Failed to update', 'kc-lang');
        $status =false;
        if(isset($request_data['timeFormat']) ){
            update_option(KIVI_CARE_PREFIX . 'appointment_time_format',$request_data['timeFormat']);
            $message = esc_html__('Appointment Time Format Saved', 'kc-lang');
            $status = true;
        }
        wp_send_json( [
            'status'  => $status ,
            'message' => $message,
        ] );
    }

    public function enableDisableAppointmentDescription () {
	    if( $this->getLoginUserRole() !== 'administrator' ){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();
        $update_status = update_option(KIVI_CARE_PREFIX.'appointment_description_config_data', $request_data['status']);
        wp_send_json([
            'data' => $request_data['status'],
            'status'  => true,
            'message' => esc_html__('Appointment Description status changed successfully.', 'kc-lang'),
        ]);
    }

    public function getAppointmentDescription () {
	    if( !in_array($this->getLoginUserRole(), $this->all_user) ){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $get_status = get_option(KIVI_CARE_PREFIX.'appointment_description_config_data');
        $enableAppointmentDescription = gettype($get_status) == 'boolean' ? 'off' : $get_status;

        wp_send_json([
            'data' => $enableAppointmentDescription,
            'status'  => true
        ]);
    }

    public function enableDisableAppointmentPatientInfo () {
	    if( $this->getLoginUserRole() !== 'administrator' ){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();
        $update_status = update_option(KIVI_CARE_PREFIX.'appointment_patient_info_config_data', $request_data['status']);
        wp_send_json([
            'data' => $request_data['status'],
            'status'  => true,
            'message' => esc_html__('Appointment Patient Info visibility status changed successfully.', 'kc-lang'),
        ]);
    }

    public function getAppointmentPatientInfo () {
	    if( !in_array($this->getLoginUserRole(), $this->all_user) ){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $get_status = get_option(KIVI_CARE_PREFIX.'appointment_patient_info_config_data');
        $enablePatientInfo = gettype($get_status) == 'boolean' ? 'on' : $get_status;

        wp_send_json([
            'data' => $enablePatientInfo,
            'status'  => true
        ]);
    }

    public function appointmentCancellationBufferSave(){
	    if( $this->getLoginUserRole() !== 'administrator' ){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();

        $message = esc_html__('Failed to update', 'kc-lang');
        $status =false;
        if(isset($request_data['status']) && !empty($request_data['status']) && isset($request_data['time'])){
            update_option(KIVI_CARE_PREFIX . 'appointment_cancellation_buffer',[
                    "status" =>$request_data['status'],
                    "time" =>$request_data['time'],]
            );
            $message = esc_html__('Appointment Cancellation Buffer Setting Saved', 'kc-lang');
            $status = true;
        }
        wp_send_json( [
            'status'  => $status,
            'message' => $message,
        ] );
    }

    public function getAppointmentCancellationBuffer(){
	    if( !in_array($this->getLoginUserRole(), $this->all_user) ){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }

        $request_data = $this->request->getInputs();
        $type = $request_data['type'];

        $data = get_option(KIVI_CARE_PREFIX . 'appointment_cancellation_buffer',true);
        
        if(gettype($data) != 'boolean'){
            if($type === 'setting'){
                $temp = $data;
            }else{
                $temp = !empty($data) && !empty($data['time']['value']) ? $data['time']['value'] : 0;
            }
        }

	    wp_send_json( [
            'status'  => true,
            'data' => $temp,
        ] );
    }
}