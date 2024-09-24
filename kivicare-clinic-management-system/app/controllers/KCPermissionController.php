<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;

class KCPermissionController extends KCBase
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
	    if ( $this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
    }

    public function allPermissionList(){
        $request_data = $this->request->getInputs();
        if(isKiviCareProActive()){
            $response = apply_filters('kcpro_get_all_permission',$request_data);
            if(is_array($response) && array_key_exists('data',$response)){
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

    public function savePermissionList(){
        $request_data = $this->request->getInputs();
        if(isKiviCareProActive()){
            $response = apply_filters('kcpro_save_permission_list',$request_data);
            if(is_array($response) && array_key_exists('status',$response)){
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