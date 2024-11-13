<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCMedicalHistory;
use App\models\KCPatientEncounter;
use Exception;

class KCPatientMedicalHistoryController extends KCBase {

	public $db;

	/**
	 * @var KCRequest
	 */
	private $request;

	private $medical_history;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->request = new KCRequest();

		$this->medical_history = new KCMedicalHistory();

        parent::__construct();

	}

	public function index() {

		if ( ! kcCheckPermission( 'medical_records_list' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse());
		}

		$request_data = $this->request->getInputs();

		if ( empty( $request_data['encounter_id'] ) ) {
			wp_send_json( [
				'status'  => false,
				'message' =>  esc_html__('Encounter not found', 'kc-lang'),
				'data'    => []
			] );
		}

        if(!((new KCPatientEncounter())->encounterPermissionUserWise($request_data['encounter_id']))){
            wp_send_json( [
                'status'      => false,
                'status_code' => 403,
                'message'     => esc_html__('You do not have permission to access', 'kc-lang'),
                'data'        => []
            ] );
        }
		$encounter_id = (int)$request_data['encounter_id'];

		$medical_history = collect( $this->medical_history->get_by( [
			'encounter_id' => $encounter_id,
		] ) );

		if ( ! count( $medical_history ) ) {
			wp_send_json( [
				'status'  => false,
				'message' =>  esc_html__('No medical history found', 'kc-lang'),
				'data'    => []
			] );
		}

        $medical_history = $medical_history->groupBy('type');

        $medical_history = apply_filters('kivicare_encounter_clinical_details_items',$medical_history,$encounter_id);

		wp_send_json( [
			'status'  => true,
			'message' =>  esc_html__('Medical history', 'kc-lang'),
			'data'    => $medical_history,
		] );
	}

	public function save() {

		if ( ! kcCheckPermission( 'medical_records_add' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		$rules = [
			'encounter_id' => 'required',
			'type'         => 'required',
			'title'        => 'required',
		];

		$errors = kcValidateRequest( $rules, $request_data );

		if ( count( $errors ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => $errors[0]
			] );
		}

        if(!((new KCPatientEncounter())->encounterPermissionUserWise($request_data['encounter_id']))){
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
		$patient_encounter = ( new KCPatientEncounter )->get_by( [ 'id' => (int)$request_data['encounter_id'] ], '=', true );
		$patient_id        = $patient_encounter->patient_id;

		if ( empty( $patient_encounter ) ) {
			wp_send_json( [
				'status'  => false,
				'message' =>  esc_html__("No encounter found", 'kc-lang')
			] );
		}

		$temp = [
			'encounter_id' => (int)$request_data['encounter_id'],
			'patient_id'   => (int)$patient_id,
			'type'         => $request_data['type'],
			'title'        => $request_data['title'],
		];

        $already_exists = $this->medical_history->get_var( $temp, 'id' );

        if( !empty( $already_exists ) && ( !isset($request_data['id']) || $already_exists == $request_data['id'] ) ) {
            $error_message = esc_html__('Same data already exists', 'kc-lang');
            if($request_data['type'] === 'problem'){
                $error_message =  esc_html__('Same problem already exists', 'kc-lang');
            }else if($request_data['type'] === 'observation'){
                $error_message =  esc_html__('Same observations already exists', 'kc-lang');
            }else if($request_data['type'] === 'note'){
                $error_message =  esc_html__('Same notes already exists', 'kc-lang');
            }
            wp_send_json( [
                'status'  => false,
                'message' =>  $error_message
            ] );
        }

		if ( ! isset( $request_data['id'] ) ) {

			$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
			$temp['added_by']   = get_current_user_id();
			$id                 = $this->medical_history->insert( $temp );

		} else {
			$id     = $request_data['id'];
			$this->medical_history->update( $temp, array( 'id' => (int)$request_data['id'] ) );
		}

		$data = $this->medical_history->get_by( [ 'id' => (int)$id ], '=', true );


		wp_send_json( [
			'status'  => true,
			'message' =>  esc_html__('Medical history saved successfully', 'kc-lang'),
			'data'    => $data
		] );

	}

	public function delete() {


		if ( ! kcCheckPermission( 'medical_records_delete' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}

			$id = (int)$request_data['id'];
            $medical_history_encounter_id = (new KCMedicalHistory())->get_var(['id' =>$id],'encounter_id');
            if(!empty($medical_history_encounter_id) && !((new KCPatientEncounter())->encounterPermissionUserWise($medical_history_encounter_id))){
	            wp_send_json(kcUnauthorizeAccessResponse(403));
            }

			$medical_history_type = (new KCMedicalHistory())->get_var(['id' =>$id],'type');

			$results = $this->medical_history->delete( [ 'id' => $id ] );

			if($medical_history_type === 'problem'){
            	$message =  esc_html__('Problems deleted successfully', 'kc-lang');
			}else if($medical_history_type === 'observation'){
            	$message =  esc_html__('Observations deleted successfully', 'kc-lang');
			}else if($medical_history_type === 'note'){
            	$message =  esc_html__('Notes deleted successfully', 'kc-lang');
			}

			if ( $results ) {	
				wp_send_json( [
					'status'  => true,
					'message' =>  $message,
				] );
			} else {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Failed to delete Medical history.', 'kc-lang'), 400 ));
			}


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
}