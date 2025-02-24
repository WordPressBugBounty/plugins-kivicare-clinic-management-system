<?php

namespace App\Controllers;


use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCBill;
use App\models\KCBillItem;
use App\models\KCClinic;
use App\models\KCPatientEncounter;
use App\models\KCService;
use App\models\KCServiceDoctorMapping;
use App\models\KCAppointmentServiceMapping;
use App\models\KCAppointment;
use App\models\KCTaxData;

use Exception;

class KCPatientBillController extends KCBase {

	public $db;

	public $bill;
	/**
	 * @var KCRequest
	 */
	private $request;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->bill = new KCBill();

		$this->request = new KCRequest();

        parent::__construct();
	}

	public function index() {

		if ( ! kcCheckPermission( 'patient_bill_list' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		$total_rows = count( $request_data );
		if ( ! isset( $request_data['encounter_id'] ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => esc_html__('Encounter not found', 'kc-lang'),
				'data'    => []
			] );
		}
        if( !((new KCPatientEncounter())->encounterPermissionUserWise($request_data['encounter_id']))){
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }

		$encounter_id = (int)$request_data['encounter_id'];
		$bills        = $this->bill->get_by( [ 'encounter_id' => $encounter_id ] );

		if ( empty($bills) ) {
			wp_send_json( [
				'status'     => false,
				'message'    => esc_html__('No bill records found', 'kc-lang'),
				'data'       => [],
				'total_rows' => $total_rows
			] );
		}

		wp_send_json( [
			'status'  => true,
			'message' => esc_html__('Medical records', 'kc-lang'),
			'data'    => $bills
		] );
	}

	public function save() {
		if ( ! kcCheckPermission( 'patient_bill_add' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();
		

		
		$rules = [
			'encounter_id'  => 'required',
			'total_amount'  => 'required',
			'discount'      => 'required',
			'actual_amount' => 'required',
			'billItems'     => 'required'
		];

		$errors = kcValidateRequest( $rules, $request_data );

		if ( count( $errors ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => $errors[0]
			] );
		}

        $request_data['encounter_id'] = (int)$request_data['encounter_id'];
        if( !((new KCPatientEncounter())->encounterPermissionUserWise($request_data['encounter_id']))){
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
		$patient_encounter = ( new KCPatientEncounter )->get_by( [ 'id' => $request_data['encounter_id'] ], '=', true );
		if ( empty( $patient_encounter ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => esc_html__("No encounter found", 'kc-lang')
			] );
		}

		$temp = [
			'title'         => $request_data['title'],
			'encounter_id'  => $request_data['encounter_id'],
			'clinic_id'  => !empty($patient_encounter->clinic_id) ? $patient_encounter->clinic_id : kcGetDefaultClinicId() ,
			'total_amount'  => (float)$request_data['total_amount'],
			'discount'      => (float)$request_data['discount'],
			'actual_amount' => (float)$request_data['actual_amount'],
			'status'        => $request_data['status'],
			'payment_status' => !empty($request_data['payment_status'])? $request_data['payment_status'] : 'unpaid',
			'appointment_id' => !empty($request_data['appointment_id']) ? (int)$request_data['appointment_id']: 0,
		];
		
		if ( empty($request_data['id']) ) {
            //hook when bill generate
            do_action('kc_encounter_bill_generate',$temp);
			$temp['created_at'] = date( 'Y-m-d H:i:s', strtotime( $patient_encounter->encounter_date ) );
            $bill_id = $this->bill->insert( $temp );

		} else {
            //hook when bill paid
            do_action('kc_encounter_bill_update',$temp);
			$bill_id = $request_data['id'];
			$this->bill->update( $temp, array( 'id' => (int)$request_data['id'] ) );
			(new KCTaxData())->delete(['module_type' => 'encounter','module_id' => $request_data['encounter_id']]);

		}

		if(isKiviCareProActive() && !empty($request_data['taxes'])){
			apply_filters('kivicare_save_tax_data', [
				'type' => 'encounter',
				'id' => $request_data['encounter_id'],
				'tax_data' => $request_data['taxes']
			]);
		}

		if ( !empty( $request_data['billItems'] ) && count( $request_data['billItems'] ) ) {
			// insert bill items
            foreach ( $request_data['billItems'] as $key => $bill_item ) {
					$service_object = new KCService();
					$service        = $service_object->get_var( [
						'name' => strtolower( $bill_item['item_id']['label'] )
					], 'id' );
					// here if service not exist then add into service table
					// $service = stripslashes($service);
					
					if ( !empty($service) ) {
						$bill_item['item_id']['id'] = $service;
					} else {
                        $new_service['type']        = 'bill_service';
						$new_service['name']        = strtolower( $bill_item['item_id']['label'] );
						$new_service['status']      = 1;
						$new_service['created_at'] = current_time( 'Y-m-d H:i:s' );
                        $new_service['price'] = (float)$bill_item['price'];
						$service_id                 = $service_object->insert( $new_service );
						$bill_item['item_id']['id'] = $service_id;
                        if ($service_id) {
                            (new KCServiceDoctorMapping())->insert([
                                'service_id' => (int)$service_id,
                                'clinic_id'  => $temp['clinic_id'],
                                'doctor_id'  => (int)$request_data['doctor_id'],
                                'charges'    => (float)$bill_item['price'],
                                'telemed_service' => 'no'
                            ]);

                            if(isset($request_data['appointment_id']) && !empty($request_data['appointment_id'])){
                                ( new KCAppointmentServiceMapping())->insert([
                                    'service_id' => (int)$service_id,
                                    'appointment_id'=> (int)$request_data['appointment_id'],
                                    'status'=> 1
                                ]);
                            }
                        }
					}
				
                $_temp = [
					'bill_id' => (int)$bill_id,
					'price'   => (float)$bill_item['price'],
					'qty'     => (int)$bill_item['qty'],
					'item_id' => (int)$bill_item['item_id']['id'],
				];

				$bill_item_object = new KCBillItem();
				
				if ( empty( $bill_item['id'] ) ) {
					$_temp['created_at'] = current_time( 'Y-m-d H:i:s' );
					$bill_item_object->insert( $_temp );
				} else {
					$bill_item_object->update( $_temp, array( 'id' => (int)$bill_item['id'] ) );
				}
			}
		}
		if(!empty($request_data['payment_status']) && $request_data['payment_status'] == 'paid'){
            //hook when bill paid
			do_action('kc_encounter_bill_paid',$temp);
            do_action('kc_encounter_update', $request_data['encounter_id']);
			( new KCPatientEncounter() )->update( [ 'status' => '0' ], [ 'id' => $request_data['encounter_id'] ] );
			if((string)$request_data['checkOutVal'] === '1'){
				(new KCAppointment() )->update( [ 'status' => '3' ], [ 'id' => (int)$request_data['patientEncounter']['appointment_id'] ] );
				do_action( 'kc_appointment_status_update', $request_data['patientEncounter']['appointment_id'] , '3' );
			}
			if(isKiviCareProActive()){
                if(kcCheckSmsOptionEnable() || kcCheckWhatsappOptionEnable()){
                     apply_filters('kcpro_send_sms', [
                        'type' => 'encounter_close',
                        'encounter_id' => $request_data['encounter_id'],
                        'patient_id'=>$patient_encounter->patient_id
                    ]);
                }
			}

			wp_send_json( [
				'status'  => true,
				'message' => esc_html__('Bill generated successfully', 'kc-lang')
			] );
			
		}else{
            //hook when bill unpaid
            do_action('kc_encounter_bill_unpaid',$temp);
			( new KCPatientEncounter() )->update( [ 'status' => '1' ], [ 'id' => $request_data['encounter_id'] ] );
		}

		wp_send_json( [
			'status'  => true,
			'message' => esc_html__('Encounter saved successfully', 'kc-lang')
		] );

	}

	public function edit() {

		if ( ! kcCheckPermission( 'patient_bill_edit' ) || ! kcCheckPermission( 'patient_bill_view' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}

			$id = (int)$request_data['id'];

			$results = $this->bill->get_by( [ 'id' => $id ], '=', true );

			if ( !empty($results) ) {

				$temp = [
					'id'            => $results->id,
					'title'         => $results->title,
					'encounter_id'  => $results->encounter_id,
					'total_amount'  => $results->total_amount,
					'discount'      => $results->discount,
					'actual_amount' => $results->actual_amount,
					'status'        => $results->status,
					'billItems'     => []
				];

                if( !((new KCPatientEncounter())->encounterPermissionUserWise($temp['encounter_id']))){
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
				$encounter_details = (new KCPatientEncounter())->get_by(['id' => $temp['encounter_id']],'=',true);
				$billItems = ( new KCBillItem )->get_by( [ 'bill_id' => $results->id ], '=', false );

				if ( !empty( $billItems ) ) {
					foreach ( $billItems as $item ) {
						$service = ( new KCService )->get_by( [ 'id' => (int)$item->item_id ], '=', true );
						$price = (new KCServiceDoctorMapping())->get_var([
							'service_id' => $service->id,
							'doctor_id' => $encounter_details->doctor_id,
							'clinic_id' => $encounter_details->clinic_id,
						],'charges');
						$temp['billItems'][] = [
							'bill_id' => $item,
							'id'      => $item->id,
							'price'   => $item->price,
							'qty'     => $item->qty,
							'item_id' => [
								'id'    => $item->item_id,
								'label' => $service->name,
								'price' => !empty($price) ? $price : $service->price,
							],
						];
					}
				}


				wp_send_json( [
					'status'  => true,
					'message' => 'Bill item',
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


	public function details() {

		if ( ! kcCheckPermission( 'patient_bill_view' ) ) {
			wp_send_json( kcUnauthorizeAccessResponse());
		}

		$request_data = $this->request->getInputs();

        global $wpdb;

		try {

			$id           = isset( $request_data['id'] ) ? (int)$request_data['id'] : 0;
			$encounter_id = isset( $request_data['encounter_id'] ) ? (int)$request_data['encounter_id'] : 0;

			if ( $encounter_id !== 0 ) {
                $results =  $wpdb->get_row("SELECT * FROM {$wpdb->prefix}kc_bills WHERE encounter_id={$encounter_id}");
			} else {
                $results = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}kc_bills WHERE id={$id}");
			}


			if (!empty($results)) {

				$patient_encounter = ( new KCPatientEncounter )->get_by( [ 'id' => (int)$results->encounter_id ], '=', true );
				$clinic            = ( new KCClinic )->get_by( [ 'id' => (int)$patient_encounter->clinic_id ], '=', true );
				$clinic->name 	   = decodeSpecificSymbols( $clinic->name);

				$patient_data = kcGetUserData($patient_encounter->patient_id);

				$temp = [
					'id'               => $results->id,
					'title'            => $results->title,
					'encounter_id'     => $results->encounter_id,
					'total_amount'     => round($results->total_amount, 2),
					'discount'         => round($results->discount, 2),
					'actual_amount'    => round($results->actual_amount, 2),
					'status'           => $results->status,
					'payment_status'=>$results->payment_status,
					'created_at'       => kcGetFormatedDate($results->created_at),
					'billItems'        => [],
					'patientEncounter' => $patient_encounter,
					'clinic'           => $clinic,
					'patient'          => [
						'id' => $patient_data->ID,
						'display_name' => $patient_data->display_name,
						'gender' => isset($patient_data->basicData->gender) ? $patient_data->basicData->gender : "",
						'dob' => isset($patient_data->basicData->dob) ? kcGetFormatedDate($patient_data->basicData->dob) : ""
					]
				];

                if( !((new KCPatientEncounter())->encounterPermissionUserWise($temp['encounter_id']))){
	                wp_send_json(kcUnauthorizeAccessResponse(403));
                }
                if(!empty($temp['patient']['gender'])){
                    if($temp['patient']['gender'] === 'male'){
                        $temp['patient']['gender'] = __("Male","kc-lang");
                    }elseif($temp['patient']['gender'] === 'female'){
                        $temp['patient']['gender'] = __("Female","kc-lang");
                    }
                }

				$billItems = ( new KCBillItem )->get_by( [ 'bill_id' => (int)$results->id ], '=', false );
				if (!empty($billItems)) {
					foreach ( $billItems as $item ) {
						$service = ( new KCService )->get_by( [ 'id' => (int)$item->item_id ], '=', true );
						$price = (new KCServiceDoctorMapping())->get_var([
							'service_id' => $service->id,
							'doctor_id' => $patient_encounter->doctor_id,
							'clinic_id' => $patient_encounter->clinic_id,
						],'charges');
						$temp['billItems'][] = [
							'bill_id' => $item,
							'id'      => $item->id,
							'price'   => round($item->price, 2),
							'qty'     => $item->qty,
							'item_id' => [
								'id'    => $item->item_id,
								'label' => $service->name,
								'price' => !empty($price) ? $price : $service->price,
							],
						];
					}
				}
				$temp['tax_total'] = '';
				$temp['taxes'] = [];
				if(isKiviCareProActive()){
					$tax = apply_filters('kivicare_calculate_tax',[
						'status' => false,
						'message' => '',
						'data' => []
					],[
						"id" => $results->encounter_id,
						"type" => 'encounter',
					]);
	
					if(!empty($tax['data']) && is_array($tax['data'])){
						$temp['tax_total'] = $tax['tax_total'];
						$temp['taxes'] = $tax['data'];
					}
				}
				wp_send_json( [
					'status'  => true,
					'message' => esc_html__('Bill item', 'kc-lang'),
					'data'    => $temp
				] );

			} else {
				wp_send_json( [
					'status'  => true,
					'message' => esc_html__('Bill not found', 'kc-lang'),
					'data'    => []
				] );
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

		if ( ! kcCheckPermission( 'patient_bill_delete' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}

			$id = (int)$request_data['id'];
            $encounter_id = (new KCBill())->get_var(['id' => $id],'encounter_id');
            if(!empty($encounter_id) && !((new KCPatientEncounter())->encounterPermissionUserWise($encounter_id))){
	            wp_send_json(kcUnauthorizeAccessResponse(403));
            }
            //hook when bill delete
            do_action('kc_encounter_bill_delete',$id);

			( new KCBillItem() )->delete( [ 'bill_id' => $id ] );

			$results = ( new KCBill() )->delete( [ 'id' => $id ] );

			if ( $results ) {
				wp_send_json( [
					'status'  => true,
					'message' => esc_html__('Bill item deleted successfully', 'kc-lang'),
				] );
			} else {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Failed to delete Bill item.', 'kc-lang'), 400 ));
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

	public function deleteBillItem() {

        if ( ! kcCheckPermission( 'patient_bill_delete' ) ) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['bill_item_id'] ) ) {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}

			$id = (int)$request_data['bill_item_id'];
			$bill_id = (new KCBillItem())->get_var(['id' => $id],'bill_id');
			if(!empty($bill_id)){
				$encounter_id = (new KCBill())->get_var(['id' => $bill_id],'encounter_id');
				if(!empty($encounter_id) && !((new KCPatientEncounter())->encounterPermissionUserWise($encounter_id))){
					wp_send_json(kcUnauthorizeAccessResponse(403));
				}
			}
			$results = ( new KCBillItem() )->delete( [ 'id' => $id ] );

			if ( $results ) {
				wp_send_json( [
					'status'  => true,
					'message' => esc_html__('Bill item deleted successfully', 'kc-lang'),
				] );
			} else {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Failed to delete Bill item.', 'kc-lang'), 400 ));
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

	public function updateStatus() {

		if ( ! kcCheckPermission( 'patient_bill_edit' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}

            $request_data['id'] = (int)$request_data['id'];
			$encounter_id = (new KCBill())->get_var(['id' => $request_data['id']],'encounter_id');
			if(!empty($encounter_id) && !((new KCPatientEncounter())->encounterPermissionUserWise($encounter_id))){
				wp_send_json(kcUnauthorizeAccessResponse(403));
			}
			$results = $this->bill->update( [ 'status' => 1 ], array( 'id' => $request_data['id'] ) );
            //hook when bill generate
            do_action('kc_encounter_bill_status_update', $request_data['id'] );

			if ( $results ) {
				wp_send_json( [
					'status'  => true,
					'message' => esc_html__('Payment status updated successfully', 'kc-lang'),
				] );
			} else {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Failed to update status', 'kc-lang'), 400 ));
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

	public function sendPaymentLink(){
        if ( $this->getPatientRole() !== 'administrator') {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
		wp_send_json( [
			'data' => [],
			'status'  => true,
			'message' => __('link ready to access','kc-lang')
		] );
	}

    public function billList(){

        if ( ! kcCheckPermission( 'patient_bill_list' ) ) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $request_data = $this->request->getInputs();
        if(isKiviCareProActive()){
            $response = apply_filters('kcpro_get_bill_list',$request_data);
            if(is_array($response) &&  array_key_exists('data',$response)){
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

    public function getWithoutBillEncounterList(){

		if( ! kcCheckPermission('patient_encounter_list')){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
        $request_data = $this->request->getInputs();
        if(isKiviCareProActive()){
            $response = apply_filters('kcpro_get_without_bill_encounter_list',$request_data);
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
}