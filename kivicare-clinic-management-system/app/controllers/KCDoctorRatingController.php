<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;

class KCDoctorRatingController extends KCBase{
    public $db;

    /**
     * @var KCRequest
     */
    private $request;

    public function __construct() {

        global $wpdb;

        $this->db = $wpdb;

        $this->request = new KCRequest();

        parent::__construct();
    }

    public function getReview(){
        //check current login user permission
        if ( ! kcCheckPermission( 'patient_review_get' ) ) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $request_data = $this->request->getInputs();

	    if (isKiviCareProActive()) {
		    $response = apply_filters('kcpro_get_doctor_review', $request_data);
		    if (is_array($response) && isset($response['data'])) {
			    wp_send_json($response);
		    }

		    wp_send_json([
			    'status' => false,
			    'message' => esc_html__('Please use the latest Pro plugin', 'kc-lang'),
			    'data' => []
		    ]);
	    }

	    wp_send_json([
		    'status' => false,
		    'message' => esc_html__('Pro plugin not active', 'kc-lang'),
		    'data' => []
	    ]);

    }

    public function saveReview(){
        //check current login user permission
        if ( ! kcCheckPermission( 'patient_review_add' )  || ! kcCheckPermission( 'patient_review_edit' ) ) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $request_data = $this->request->getInputs();
        if(isKiviCareProActive()){
            $rules = [
                'patient_id' => 'required',
                'doctor_id' => 'required'
            ];

            $errors = kcValidateRequest($rules, $request_data);

            if (!empty(count($errors))) {
	            wp_send_json([
                    'status' => false,
                    'message' => $errors[0]
                ]);
            }
            $doctor_patient_list = kcDoctorPatientList($request_data['doctor_id']);
            $doctor_patient_list = !empty($doctor_patient_list) ? $doctor_patient_list : [-1];
            if(!in_array($request_data['patient_id'],$doctor_patient_list)){
	            wp_send_json(kcUnauthorizeAccessResponse(403));
            }

            $response = apply_filters('kcpro_save_doctor_review',$request_data);
            if(is_array($response) && array_key_exists('data',$response)){
	            wp_send_json($response);
            }

	        wp_send_json( [
		        'status'  => false,
		        'message' => esc_html__('Please use latest Pro plugin', 'kc-lang'),
		        'data'    => []
	        ] );

        }

	    wp_send_json( [
		    'status'  => false,
		    'message' => esc_html__('Pro plugin not active', 'kc-lang'),
		    'data'    => []
	    ] );
    }
    public function doctorReviewDetail(){
        //check current login user permission
        if ( ! kcCheckPermission( 'patient_review_get' ) ) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $request_data = $this->request->getInputs();
        if(isKiviCareProActive()){
            $response = apply_filters('kcpro_get_doctor_review_detail',$request_data);
            if(is_array($response) && array_key_exists('data',$response)){
	            wp_send_json($response);
            }

	        wp_send_json( [
		        'status'  => false,
		        'message' => esc_html__('Please use latest Pro plugin', 'kc-lang'),
		        'data'    => []
	        ] );

        }

	    wp_send_json( [
		    'status'  => false,
		    'message' => esc_html__('Pro plugin not active', 'kc-lang'),
		    'data'    => []
	    ] );
    }
}