<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;

class KCGooglemeetController extends KCBase
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
    }


    public function saveGoogleMeetConfig(){
        if($this->getLoginUserRole() !== 'administrator'){
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcgm_saved_googlemeet_config', ['data'=>$request_data]);
	    wp_send_json($response);
    }

    public function getGoogleMeetEventTemplateAndConfigData () {
        if ( $this->getLoginUserRole() !== 'administrator') {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $prefix = KIVI_CARE_PREFIX;
        $google_event_template = $prefix.'gmeet_tmp' ;
        $args['post_type'] = strtolower($google_event_template);
        $gogle_template_result = get_posts($args);
        $gogle_template_result = collect($gogle_template_result)->unique('post_title')->sortBy('ID');
        $config = apply_filters('kcgm_edit_googlemeet', []);
        if ($gogle_template_result) {
            $response =  [
                'status' => true,
                'data'=> $gogle_template_result,
                'config' => $config
            ];
        } else {
            $response = [
                'status' => false,
                'data'=> [],
                'config' => $config
            ];
        }
	    wp_send_json($response);
    }
    public function saveGoogleMeetEventTemplate(){
        if ( $this->getLoginUserRole() !== 'administrator') {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcgm_save_googlemeet_event_template', [
            'data'=>$request_data['data']
        ]);
	    wp_send_json($response);
    }

    public function connectGoogleMeetDoctor(){
		if($this->getLoginUserRole() !== $this->getDoctorRole()){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
        $request_data = $this->request->getInputs();
		if((int)$request_data['doctor_id'] !== get_current_user_id()){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
        $response = apply_filters('kcgm_connect_doctor', [
            'id'=>$request_data['doctor_id'],
            'code'=>$request_data['code'],
        ]);
	    wp_send_json($response);
    }

    public function disconnectMeetDoctor(){

	    if($this->getLoginUserRole() !== $this->getDoctorRole()){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
	    $request_data = $this->request->getInputs();
	    if((int)$request_data['doctor_id'] !== get_current_user_id()){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $response = apply_filters('kcgm_disconnect_doctor', [
            'id'=>$request_data['doctor_id']
        ]);

	    wp_send_json($response);

    }
    
}