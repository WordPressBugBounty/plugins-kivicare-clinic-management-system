<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use WP_User;

class KCGoogleCalenderController extends KCBase
{
    /**
     * @var KCRequest
     */
    private $request;

    public function __construct()
    {
        $this->request = new KCRequest();
        parent::__construct();
    }
    

    public function connectDoctor(){
		if( ! in_array($this->getLoginUserRole(),[
			$this->getReceptionistRole(),
			$this->getDoctorRole()
		])){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
	    $request_data = $this->request->getInputs();
	    if((int)$request_data['doctor_id'] !== get_current_user_id()){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $response = apply_filters('kcpro_connect_doctor', [
            'id'=>(int)$request_data['doctor_id'],
            'code'=>$request_data['code'],
        ]);
	    wp_send_json($response);
    }
    public function disconnectDoctor(){
	    if( ! in_array($this->getLoginUserRole(),[
		    $this->getReceptionistRole(),
		    $this->getDoctorRole()
	    ])){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
	    $request_data = $this->request->getInputs();
	    if((int)$request_data['doctor_id'] !== get_current_user_id()){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $response = apply_filters('kcpro_disconnect_doctor', [
            'id'=>(int)$request_data['doctor_id']
        ]);
	    wp_send_json($response);
    }
    public function getGoogleEventTemplate () {
        if($this->getLoginUserRole() !== 'administrator'){
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $prefix = KIVI_CARE_PREFIX;
        $google_event_template = $prefix.'gcal_tmp' ;
        $args['post_type'] = strtolower($google_event_template);
        $gogle_template_result = get_posts($args);
        $gogle_template_result = collect($gogle_template_result)->unique('post_title')->sortBy('ID');
        if ($gogle_template_result) {
            $response = [
                'status' => true,
                'data'=> $gogle_template_result,
            ];
        } else {
            $response = [
                'status' => false,
                'data'=> [],
            ];
        }
	    wp_send_json($response);
    }
    public function saveGoogleEventTemplate(){
        if($this->getLoginUserRole() !== 'administrator'){
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();
        $response = apply_filters('kcpro_save_google_event_template', [
            'data'=>$request_data['data']
        ]);
	    wp_send_json($response);

    }
}