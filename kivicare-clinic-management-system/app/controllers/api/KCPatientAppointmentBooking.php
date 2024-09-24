<?php

namespace App\Controllers\Api;

use App\baseClasses\KCBase;
use App\models\KCAppointment;
use App\models\KCDoctorClinicMapping;
use WP_REST_Response;
use WP_REST_Server;
use Exception;

class KCPatientAppointmentBooking extends KCBase {

	public $module = 'patient';

	public $nameSpace;

	public function __construct() {

		$this->nameSpace = 'wp-medical';

		add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/book-appointment', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'patientAppointmentBooking' ],
				'permission_callback' => '__return_true'
			) );

		} );
	}

	public function patientAppointmentBooking( $request ) {

		if( ! kcCheckPermission('appointment_add')){
			return new WP_REST_Response( kcUnauthorizeAccessResponse());
		}
		$postData = $request->get_params();

		$rules = [
			'appointment_start_date' => 'required',
			'appointment_start_time' => 'required',
			'visit_type'             => 'required',
			'clinic_id'              => 'required',
			'doctor_id'              => 'required',
			'patient_id'             => 'required',
			'status'                 => 'required',

		];

		$message = [
			'status'     => esc_html__('Status is required', 'kc-lang'),
			'patient_id' => esc_html__('Patient is required', 'kc-lang'),
			'clinic_id'  => esc_html__('Clinic is required', 'kc-lang'),
			'doctor_id'  => esc_html__('Doctor is required', 'kc-lang'),
		];

		$errors = kcValidateRequest( $rules, $postData, $message );

		if ( count( $errors ) ) {
			return new WP_REST_Response( [
				'status'  => false,
				'message' => $errors[0]
			]);
		}

		$end_time             = strtotime( "+15 minutes", strtotime( sanitize_text_field($postData['appointment_start_time']) ) );
		$appointment_end_time = date( 'h:i:s', $end_time );
		$appointment_date     = date( 'Y-m-d', strtotime( sanitize_text_field($postData['appointment_start_date']) ) );

		$temp = [
			'appointment_start_date' => $appointment_date,
			'appointment_start_time' => date( 'H:i:s', strtotime( sanitize_text_field($postData['appointment_start_time']) ) ),
			'appointment_end_date'   => $appointment_date,
			'appointment_end_time'   => $appointment_end_time,
			'visit_type'             => sanitize_text_field($postData['visit_type']),
			'clinic_id'              => (int)$postData['clinic_id'],
			'doctor_id'              => (int)$postData['doctor_id'],
			'patient_id'             => (int)$postData['patient_id'],
			'description'            => sanitize_text_field($postData['description']),
			'status'                 => sanitize_text_field($postData['status'])
		];

		$appointment = new KCAppointment();

		$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
		$appointment->insert( $temp );
		$message = __('Appointment has been added successfully','kc-lang');

		$response = new WP_REST_Response( [
			'status'  => true,
			'message' => $message,
			'data'    => $postData
		] );

		$response->set_status( 200 );

		return $response;
	}

}


