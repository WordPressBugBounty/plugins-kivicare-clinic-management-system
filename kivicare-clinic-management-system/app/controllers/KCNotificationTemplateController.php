<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;

class KCNotificationTemplateController extends KCBase
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

        parent::__construct();
	    if($this->getLoginUserRole() !== 'administrator'){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        // Set the filter_not_found_message based on the activation status of KiviCare Pro plugin
        if ( isKiviCareProActive() ) {
            $this->filter_not_found_message = esc_html__( "Please update kivicare pro plugin", "kc-lang" );
        } else {
            $this->filter_not_found_message = esc_html__( "Please install kivicare pro plugin", "kc-lang" );
        }
    }

    public function getEmailTemplate () {
	    wp_send_json([
            'status' => true,
            'data' => kcGetNotificationTemplateLists('mail'),
            'labels' => [
                'patient' => __("Patient Templates","kc-lang"),
                'doctor' => __("Doctor Templates","kc-lang"),
                'clinic' => __("Clinic Templates","kc-lang"),
                'receptionist' => __("Receptionist Templates","kc-lang"),
                'common' => __("Common Templates","kc-lang"),
            ],
            'dynamicKey' => kcGetEmailSmsDynamicKeys()
        ]);
    }
    public function saveEmailTemplate () {

        $request_data = $this->request->getInputs();
        $request_data['data'] = collect($request_data['data'])->flatten(1);
        foreach ($request_data['data'] as $key => $value) {
            wp_update_post($value);
        }

	    wp_send_json([
            'status' => true,
            'message' => esc_html__('Email template saved successfully.', 'kc-lang')
        ]);

    }
    public function getTwillioSMSTemplate () {
        $request_data = $this->request->getInputs();
        wp_send_json([
            'status' => true,
            'data'=> apply_filters('kcpro_get_twilio_template',$request_data['content_sid']),
        ]);
    }
    public function getSMSTemplate () {
	    wp_send_json([
            'status' => true,
            'data'=> kcGetNotificationTemplateLists('sms'),
            'labels' => [
                'patient' => __("Patient Templates","kc-lang"),
                'doctor' => __("Doctor Templates","kc-lang"),
                'clinic' => __("Clinic Templates","kc-lang"),
                'receptionist' => __("Receptionist Templates","kc-lang"),
                'common' => __("Common Templates","kc-lang"),
            ],
            'dynamicKey' => kcGetEmailSmsDynamicKeys()
        ]);
    }
    public function saveSMSTemplate () {

        $request_data = $this->request->getInputs();
        $request_data['data'] = $request_data['data'] = collect($request_data['data'])->flatten(1);
        $response = apply_filters('kcpro_save_sms_template', [
            'data'=>$request_data['data']
        ]);
		wp_send_json($response);
    }
    public function sendTestNotification () {
        $data = $this->request->getInputs();
        $response = (object)[];
        $message = esc_html__('Failed to send test ', 'kc-lang') .$data['type'];
        $status = false;
        switch($data['type']){
            case 'email':
                $email_status = wp_mail($data['recieverDetails'], 'Kivicare test mail', $data['content']);
                $message = esc_html__('Failed to send test email. Please check your SMTP setup.', 'kc-lang');
                if($email_status) {
                    $status = true ;
                    $message = esc_html__('Test email sent successfully.', 'kc-lang');
                    update_option( KIVI_CARE_PREFIX . 'is_email_working' , $email_status);
                }
                break;
            case 'sms':
                $response = apply_filters('kcpro_send_sms_directly',$data['recieverDetails'],$data['content'],0);
                $message = esc_html__('Failed to send test sms. Please check your Twillo sms setup.', 'kc-lang');
                if(!empty($response->status)){
                    if(in_array($response->status,['sent','queued','delivered'])){
                        $status = true ;
                        $message = esc_html__('Test Sms sent successfully.', 'kc-lang');
                    }else if(!empty($response->error)){
                        $message =  $response->error;
                    }
                }
                break;
            case 'whatsapp':
                $response = apply_filters('kcpro_send_whatsapp_directly',$data['recieverDetails'],$data['content']);
                $message = esc_html__('Failed to send test sms. Please check your Twillo whatsapp setup.', 'kc-lang');
                if(!empty($response->status)){
                    if(in_array($response->status,['sent','queued','delivered'])){
                        $status = true ;
                        $message =  esc_html__('Test whatsapp sent successfully.', 'kc-lang');
                    }else if(!empty($response->error)){
                        $message =  $response->error;
                    }
                }
                break;
        }

	    wp_send_json([
            'status' => $status,
            'message' => $message,
            'response' => $response
        ]);
    }

    public function saveDynamicKeys(){

        // Get request data
        $request_data = $this->request->getInputs();

        // Send JSON response with tax list action filtered
        wp_send_json( apply_filters('kcpro_custom_notification_dynamic_keys_save', [
            'status'     => false,
            'message'    => $this->filter_not_found_message,
            'data'       => [],
        ] ,$request_data ));
    }

    public function getDynamicKeys(){
        
        // Get request data
        $request_data = $this->request->getInputs();

        // Send JSON response with tax list action filtered
        wp_send_json( apply_filters('kcpro_custom_notification_dynamic_keys_list', [
            'status'     => false,
            'message'    => $this->filter_not_found_message,
            'data'       => [],
        ] ,$request_data ));

    }

    public function saveApiConfigurationList(){
        // Get request data
        $request_data = $this->request->getInputs();

        // Send JSON response with tax list action filtered
        wp_send_json( apply_filters('kcpro_custom_notification_api_configuration_list_save', [
            'status'     => false,
            'message'    => $this->filter_not_found_message,
            'data'       => [],
        ] ,$request_data ));
    }

    public function getApiConfigurationList(){
         // Get request data
         $request_data = $this->request->getInputs();

         // Send JSON response with tax list action filtered
         wp_send_json( apply_filters('kcpro_custom_notification_api_configuration_list', [
             'status'     => false,
             'message'    => $this->filter_not_found_message,
             'data'       => [],
         ] ,$request_data ));
    }
}