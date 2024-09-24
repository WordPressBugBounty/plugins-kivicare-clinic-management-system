<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use Exception;

class KCPatientUniqueIdController extends KCBase
{
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

    public function savePatientSetting(){
		if($this->getLoginUserRole() !== 'administrator'){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
        $setting = $this->request->getInputs();
        try{
            if(isset($setting)){
                $config = array(
                    'prefix_value' =>$setting['prefix_value'],
                    'postfix_value'=>$setting['postfix_value'],
                    'enable'=>$setting['enable'],
                    'only_number' => $setting['only_number']
                );
                update_option( KIVI_CARE_PREFIX . 'patient_id_setting',$config );
	            wp_send_json( [
                    'status' => true,
                    'message' => esc_html__('Unique id setting saved successfully', 'kc-lang')
                ] );
            }
        }catch (Exception $e) {
	        wp_send_json( [
                'status' => false,
                'message' => esc_html__('Failed to save Unique id settings.', 'kc-lang')
            ] );
        }

    }
    public function editPatientSetting(){

	    if($this->getLoginUserRole() !== 'administrator'){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $get_patient_data = get_option(KIVI_CARE_PREFIX . 'patient_id_setting',true);

        if ( gettype($get_patient_data) != 'boolean' ) {

            $get_patient_data['enable'] = in_array( (string)$get_patient_data['enable'], ['true', '1']) ? true : false;
            $get_patient_data['only_number'] = in_array( (string)$get_patient_data['only_number'], ['true','1']) ? true : false;

	        wp_send_json( [
                'data'=> $get_patient_data,
                'status' => true,
            ] );
        } else {
	        wp_send_json( [
                'data'=> [],
                'status' => false,
            ] );
        }
    }
    public function getPatientUid() {
	    wp_send_json( [
            'data'=> generatePatientUniqueIdRegister(),
            'status' => true,
        ] );
    }
}