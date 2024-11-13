<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;

class KCProSettingController extends KCBase
{

    /**
     * @var KCRequest
     */
    public $db;

    private $request;

    public $filter_not_found_message;

    public function __construct()
    {
        global $wpdb;

        $this->db = $wpdb;

        $this->request = new KCRequest();

        parent::__construct();// Set the filter_not_found_message based on the activation status of KiviCare Pro plugin
        if ( isKiviCareProActive() ) {
            $this->filter_not_found_message = esc_html__( "Please update kivicare pro plugin", "kc-lang" );
        } else {
            $this->filter_not_found_message = esc_html__( "Please install kivicare pro plugin", "kc-lang" );
        }

    }
    public function editAllProSettingData() {
	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $wordpressLogoResponse = apply_filters('kcpro_get_wordpress_logo', []);
        if (!empty($wordpressLogoResponse) && is_array($wordpressLogoResponse)) {
            $wordpressLogoData =  $wordpressLogoResponse['data'];
        } else {
            $wordpressLogoData = [
                'status' =>  0,
                'logo' => KIVI_CARE_DIR_URI . 'assets/images/wp-logo.png?version=22'
            ];
        }

	    wp_send_json([
            'sms' => apply_filters('kcpro_edit_sms_config', [
                'current_user' => get_current_user_id(),
            ]),
            'whatsapp' => apply_filters('kcpro_edit_whatsapp_config', [
                'current_user' => get_current_user_id(),
            ]),
            'google_calendar' => apply_filters('kcpro_edit_google_cal', []),
            'patient_calendar' => apply_filters('kcpro_patient_edit_google_cal', []),
            'encounter_clinical_detail' => apply_filters('kcpro_get_clinical_detail_in_prescription', []),
            'wordpress_logo_data' => $wordpressLogoData,
            'custom_notification' => apply_filters('kcpro_custom_notification_setting_get',[
                'enableSMS' => 'no',
                'enableWhatsapp' => 'no',
            ])
        ]);
    }

    public function uploadLogo()
    {
	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();
        if (!isset($_FILES['file']) && empty($request_data['site_logo'])) {
            wp_send_json(['message'=>esc_html__('File no found', 'kc-lang')]);
        }
        if(isset($request_data['site_logo']) && !empty($request_data['site_logo'])){
            $attachment_id = $request_data['site_logo'];
        }else if(isset($_FILES['file'])){
            $attachment_id = media_handle_upload('file', 0);
        }
        $response = apply_filters('kcpro_upload_logo', [
            'site_logo' => $attachment_id,
        ]);
        wp_send_json($response);
    }

    public function uploadLoader()
    {
	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();
        if (!isset($_FILES['file']) && empty($request_data['site_loader'])) {
            wp_send_json(['message'=>esc_html__('File no found', 'kc-lang')]);
        }
        if(isset($request_data['site_loader']) && !empty($request_data['site_loader'])){
            $attachment_id = $request_data['site_loader'];
        }else if(isset($_FILES['file'])){
            $attachment_id = media_handle_upload('file', 0);
        }
        $response = apply_filters('kcpro_upload_loader', [
            'site_loader' => $attachment_id,
        ]);
	    wp_send_json($response);
    }

    public function updateThemeColor()
    {
	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_change_themecolor', [
            'color' => $request_data['color'],
        ]);
	    wp_send_json($response);
    }

    public function updateRTLMode()
    {
	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_change_mode', [
            'mode' => $request_data['rtl'],
        ]);
	    wp_send_json($response);
    }

    public function wordpresLogo()
    {
	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $requestData = $this->request->getInputs();
        $response = apply_filters('kcpro_save_wordpress_logo', $requestData);
        if (!empty($response) && is_array($response)) {
	        wp_send_json($response);
        } else {
	        wp_send_json([
                'status' => false,
                'message' => esc_html__('Filter Not Found', 'kc-lang')
            ]);
        }
    }

    public function saveSmsConfig()
    {
	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_save_sms_config', [
            'config_data' => $request_data,
        ]);
	    wp_send_json($response);
    }

    public function saveWhatsAppConfig()
    {
	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
		$request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_save_whatsapp_config', [
            'config_data' => $request_data,
        ]);
	    wp_send_json($response);
    }

    public function saveConfig()
    {
	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_saved_google_config', ['data' => $request_data]);
	    wp_send_json($response);
    }

    public function googleCalPatient()
    {
	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
		$request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_patient_google_cal', [
            'data' => $request_data
        ]);
	    wp_send_json($response);
    }

    public function editClinicalDetailInclude()
    {
	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }

        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_edit_clinical_detail_in_prescription', [
            'status' => $request_data['status'],
        ]);
	    wp_send_json($response);
    }

    public function editEncounterCustomFieldInclude()
    {

	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_edit_encounter_custom_field_in_prescription', [
            'status' => $request_data['status'],
        ]);
	    wp_send_json($response);
    }

    public function editClinicalDetailHideInPatient()
    {
	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();
        apply_filters('kcpro_save_pro_option_value', $request_data, 'hide_clinical_detail_in_patient');

	    wp_send_json([
            'status' => true,
            'message' => esc_html__('Setting Saved', 'kc-lang')
        ]);
    }

    public function saveCopyRightText()
    {
	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $request_data = $this->request->getInputs();
        apply_filters('kcpro_save_pro_option_value', $request_data, 'copyrightText');
	    wp_send_json(['status' => true, 'message' => __('CopyRight Text Saved Successfully', 'kc-lang')]);
    }

    public function getEncounterTemplates()
    {
        $request_data = $this->request->getInputs();
        do_action('kcpro_get_encounter_templates',$request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }
    public function insertTemplateToEncounter()
    {
        $request_data = $this->request->getInputs();
        do_action('kcpro_insert_template_to_encounter',$request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }
    public function deleteEncounterTemp()
    {
        $request_data = $this->request->getInputs();
        do_action('kcpro_delete_encounter_temp',$request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }
    public function addEncounterTemp()
    {
        $request_data = $this->request->getInputs();
        do_action('kcpro_add_encounter_temp',$request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }
    public function medicalHistoryListFromTemplate()
    {
        $request_data = $this->request->getInputs();
        do_action('kcpro_medical_history_list_from_template',$request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }
    public function saveEncounterTemplateMedicalHistory()
    {
        $request_data = $this->request->getInputs();
        do_action('kcpro_save_encounter_template_medical_history',$request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }
    public function deleteEncounterTemplateMedicalHistory()
    {
        $request_data = $this->request->getInputs();
        do_action('kcpro_delete_encounter_template_medical_history',$request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }
    public function patientEncounterTemplateDetails()
    {
        $request_data = $this->request->getInputs();
        do_action('kcpro_patient_encounter_template_details',$request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }
    public function getEncounterTemplatePrescriptionList()
    {
        $request_data = $this->request->getInputs();
        do_action('kcpro_get_encounter_template_prescription_list',$request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }
    public function saveEncounterTemplatePrescription()
    {
        $request_data = $this->request->getInputs();
        do_action('kcpro_save_encounter_template_prescription',$request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }
    public function deleteEncounterTemplatePrescription()
    {
        $request_data = $this->request->getInputs();
        do_action('kcpro_delete_encounter_template_prescription',$request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }

    public function saveCustomNotificationSetting(){
        if ( $this->getLoginUserRole() !== 'administrator' ) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();
        wp_send_json( apply_filters('kcpro_custom_notification_setting_save', [
            'status'     => false,
            'message'    => $this->filter_not_found_message,
            'data'       => [],
        ] ,$request_data ));
    }
    public function sendCustomNotificationRequest(){

        $request_data = $this->request->getInputs();

        if(isKiviCareProActive()){
            $response = apply_filters('kcpro_send_custom_notification_request',$request_data);
            if(is_array($response) &&  array_key_exists('body',$response)){
                wp_send_json($response);
            }else{
                wp_send_json( [
                    'status'  => false,
                    'message' => esc_html__('Please use latest Pro plugin', 'kc-lang'),
                    'data'    => []
                ] );
            }
        }else{
            wp_send_json( [
                'status'  => false,
                'message' => esc_html__('Pro plugin not active', 'kc-lang'),
                'data'    => []
            ] );
        }

    }
}
