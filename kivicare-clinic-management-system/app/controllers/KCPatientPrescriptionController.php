<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCPatientEncounter;
use App\models\KCPrescription;
use Exception;

class KCPatientPrescriptionController extends KCBase {

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

	public function index() {

		if ( ! kcCheckPermission( 'prescription_list' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse());
		}

		$request_data = $this->request->getInputs();

		if ( ! isset( $request_data['encounter_id'] ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => esc_html__('Encounter not found', 'kc-lang'),
				'data'    => []
			] );
		}

		$encounter_id       = (int)$request_data['encounter_id'];
		$prescription_table = $this->db->prefix . 'kc_prescription';
        if(!((new KCPatientEncounter())->encounterPermissionUserWise($encounter_id))){
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
		
		//prescription dropdown
		$search_query = isset($request_data['search']) ? sanitize_text_field($request_data['search']) : '';
		$static_data_table = $this->db->prefix . 'kc_static_data';

		$options_query = "SELECT `label` as id, label FROM {$static_data_table} WHERE status = 1 AND `type`='prescription_medicine'";
		$query_params = [];

		if (!empty($search_query)) {
			$options_query .= " AND label LIKE %s";
			$query_params[] = '%' . $this->db->esc_like($search_query) . '%';
			$options_query .= " LIMIT 20"; 
		} else {
			$options_query .= " LIMIT 20"; 
		}

		$prescriptions_name_dropdown_options = collect(
			!empty($query_params)
				? $this->db->get_results($this->db->prepare($options_query, $query_params), OBJECT)
				: $this->db->get_results($options_query, OBJECT)
		)->unique('id')->toArray();

		$query = "SELECT * FROM  {$prescription_table} WHERE encounter_id = {$encounter_id}";

		$prescriptions = collect( $this->db->get_results( $query, OBJECT ) )->map( function ( $data ) {
			$data->name = [
				'id'    => $data->name,
				'label' => $data->name
			];
			return $data;
		} );

		$total_rows = count( $prescriptions );

		if ( ! count( $prescriptions ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => esc_html__('No prescription found', 'kc-lang'),
				'data'    => [],
                'prescriptionNames'  => array_values($prescriptions_name_dropdown_options)
			] );
		}

		wp_send_json( [
			'status'     => true,
			'message'    => esc_html__('Prescription records', 'kc-lang'),
			'data'       => $prescriptions,
			'total_rows' => $total_rows,
            'prescriptionNames'  => array_values($prescriptions_name_dropdown_options)
		] );
	}

	public function save() {

		if ( ! kcCheckPermission( 'prescription_add' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();		

		$rules = [
			'encounter_id' => 'required',
			'name'         => 'required',
			'frequency'    => 'required',
			'duration'     => 'required',
		];

		$errors = kcValidateRequest( $rules, $request_data );

		if ( count( $errors ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => $errors[0]
			] );
		}

		$patient_encounter = ( new KCPatientEncounter )->get_by( [ 'id' => (int)$request_data['encounter_id'] ], '=', true );
		$patient_id        = $patient_encounter->patient_id;

		if ( empty( $patient_encounter ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => esc_html__("No encounter found", 'kc-lang')
			] );
		}

        if(!((new KCPatientEncounter())->encounterPermissionUserWise($request_data['encounter_id']))){
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
		$temp = [
			'encounter_id' => (int)$request_data['encounter_id'],
			'patient_id'   => (int)$patient_id,
			'name'         => $request_data['name']['id'],
			'frequency'    => $request_data['frequency'],
			'duration'     => (int)$request_data['duration'],
			'instruction'  => $request_data['instruction'],
		];

		$prescription = new KCPrescription();

		if ( ! isset( $request_data['id'] ) ) {

			$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
			$temp['added_by']   = get_current_user_id();
			$prescription_id    = $prescription->insert( $temp );
			$message            = esc_html__('Prescription has been saved successfully', 'kc-lang');

		} else {
			$prescription_id = $request_data['id'];
			$status          = $prescription->update( $temp, array( 'id' => (int)$request_data['id'] ) );
			$message         = esc_html__('Prescription has been updated successfully', 'kc-lang');
		}

		$data = $prescription->get_by( [ 'id' => (int)$prescription_id ], '=', true );
		$data->name = [
			'id'    => $data->name,
			'label' => $data->name
		];

		wp_send_json( [
			'status'  => true,
			'message' => $message,
			'data'    => $data
		] );

	}

	public function edit() {

		if ( ! kcCheckPermission( 'prescription_edit' ) || ! kcCheckPermission( 'prescription_view' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}

			$id = (int)$request_data['id'];

			$prescription_table = $this->db->prefix . 'kc_prescription';

			$query = "SELECT * FROM  {$prescription_table} WHERE id = {$id}";

			$prescription = $this->db->get_row( $query );

			if ( count( $prescription ) ) {
                if(!((new KCPatientEncounter())->encounterPermissionUserWise($prescription->encounter_id))){
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
				$temp = [
					'id'           => $prescription->id,
					'patient_id'   => $prescription->patient_id,
					'encounter_id' => $prescription->encounter_id,
					'title'        => $prescription->title,
					'notes'        => $prescription->notes,
					'added_by'     => $prescription->added_by,
				];

				wp_send_json( [
					'status'  => true,
					'message' => esc_html__('Prescription record', 'kc-lang'),
					'data'    => $temp
				] );
			} else {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
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

	public function delete() {

		if ( ! kcCheckPermission( 'prescription_delete' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}

			$id = (int)$request_data['id'];

            $prescription_encounter_id = (new KCPrescription())->get_var(['id' =>$id],'encounter_id');

            if(!empty($prescription_encounter_id) && !((new KCPatientEncounter())->encounterPermissionUserWise($prescription_encounter_id))){
	            wp_send_json(kcUnauthorizeAccessResponse(403));
            }

			$results = ( new KCPrescription() )->delete( [ 'id' => $id ] );

			if ( $results ) {
				wp_send_json( [
					'status'  => true,
					'message' => esc_html__('Prescription has been deleted successfully', 'kc-lang'),
				] );
			} else {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Failed to delete Prescription', 'kc-lang'), 400 ));
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

	public function mailPrescription(){
        $request_data = $this->request->getInputs();
        $precription_table = $this->db->prefix.'kc_prescription';
        $encounter_table = $this->db->prefix.'kc_patient_encounters';
        $status = false;
        $message = esc_html__('Failed to send email', 'kc-lang');
        if(isset($request_data['encounter_id']) && $request_data['encounter_id'] != ''){
            $request_data['encounter_id']  = (int)$request_data['encounter_id'];
            if(!((new KCPatientEncounter())->encounterPermissionUserWise($request_data['encounter_id']))){
	            wp_send_json(kcUnauthorizeAccessResponse(403));
            }
              $results = $this->db->get_results("SELECT pre.* ,enc.*
                                                 FROM {$precription_table} AS pre 
                                                 JOIN {$encounter_table} AS enc ON enc.id=pre.encounter_id WHERE pre.encounter_id={$request_data['encounter_id']} ");

              if($results != null){
                  $doctor_id = collect($results)->pluck('doctor_id')->unique('doctor_id')->toArray();
                  $patient_id = collect($results)->pluck('patient_id')->unique('patient_id')->toArray();
                  $clinic_id = collect($results)->pluck('clinic_id')->unique('clinic_id')->toArray();
                  $doctor_data = isset($doctor_id[0]) ? get_user_by('ID',$doctor_id[0]) : '';
                  $patient_data = isset($patient_id[0]) ? get_user_by('ID',$patient_id[0]) : '';
                  $clinic_data = isset($clinic_id[0]) ? kcClinicDetail($clinic_id[0]) : '';
                  $style = 'border: 1px solid black';
                  ob_start();
                  ?>
                  <table style="<?php echo esc_html($style) ; ?>; width:100%" >
                      <tr>
                          <th style="<?php echo esc_html($style) ; ?>;"><?php echo esc_html__('NAME','kc-lang'); ?></th>
                          <th style="<?php echo esc_html($style) ; ?>;"><?php echo esc_html__('FREQUENCY','kc-lang'); ?></th>
                          <th style="<?php echo esc_html($style) ; ?>;"><?php echo esc_html__('DAYS','kc-lang'); ?></th>
						  <th style="<?php echo esc_html($style) ; ?>;"><?php echo esc_html__('Instruction','kc-lang'); ?></th>
                      </tr>
                  <?php
                 foreach ($results as $temp){
                     ?>
                     <tr>
                         <td style="<?php echo esc_html($style) ; ?>;"><?php echo esc_html(!empty($temp->name) ?$temp->name : '') ; ?></td>
                         <td style="<?php echo esc_html($style) ; ?>;"><?php echo esc_html(!empty($temp->frequency) ?$temp->frequency : ''); ?></td>
                         <td style="<?php echo esc_html($style) ; ?>;"><?php echo esc_html(!empty($temp->duration) ?$temp->duration : ''); ?></td>
						 <td style="<?php echo esc_html($style) ; ?>;"><?php echo esc_html(!empty($temp->duration) ?$temp->instruction : ''); ?></td>
                     </tr>
                     <?php
                 }
                  ?>
                  </table>
                  <?php
                 $data = ob_get_clean();
                 $email_data = [
                     'user_email' => isset($patient_data->user_email) ? $patient_data->user_email:'',
                     'doctor_email' => isset($doctor_data->user_email) ? $doctor_data->user_email:'',
                     'doctor_name' => isset($doctor_data->display_name) ? $doctor_data->display_name:'',
                     'doctor_contact_number' => kcGetUserValueByKey('doctor',!empty($doctor_id[0]) ? $doctor_id[0] : '' ,'mobile_number'),
                     'clinic_name' => isset($clinic_data->name) ? $clinic_data->name:'',
                     'clinic_email' => isset($clinic_data->email) ? $clinic_data->email:'',
                     'clinic_contact_number' => isset($clinic_data->telephone_no) ? $clinic_data->telephone_no:'',
                     'clinic_address' =>  (!empty($clinic_data->address) ? $clinic_data->address : '') .','.(!empty($clinic_data->city) ? $clinic_data->city : '').','.(!empty($clinic_data->country) ? $clinic_data->country : ''),
                     'prescription' => $data,
                     'email_template_type' => 'book_prescription'
                 ];
                 $status = kcSendEmail($email_data);
                 $message = $status ? esc_html__('Prescription send successfully.', 'kc-lang') : esc_html__('Failed to send email', 'kc-lang');
              }
        }

		wp_send_json( [
            'status'  => $status,
            'message' => $message
        ] );
    }

    public function getPrescriptionPrint(){
        $request_data = $this->request->getInputs();
        if(!((new KCPatientEncounter())->encounterPermissionUserWise($request_data['id']))){
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $response = apply_filters('kcpro_get_prescription_print', [
            'encounter_id' => (int)$request_data['id'],
            'clinic_default_logo' => KIVI_CARE_DIR_URI .'assets/images/kc-demo-img.png'
        ]);
	    wp_send_json($response);
    }
}
