<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCBillItem extends KCModel {

	public function __construct()
	{
		parent::__construct('bill_items');
	}
	public static function createAppointmentBillItem($appointment_id) {
		$appointment_doctor_id = (new KCAppointment())->get_var([ 'id' => (int)$appointment_id], 'doctor_id');
		if(!empty($appointment_doctor_id)){
			$appointment_service = (new KCAppointmentServiceMapping())->get_by([ 'appointment_id' => (int)$appointment_id], '=', false);
            if(!empty($appointment_service)){
				$total_amount = 0;
				foreach ( $appointment_service  as $data ) {
                    $get_mapping_services = (new KCServiceDoctorMapping())->get_by([ 'service_id' => (int)$data->service_id, 'doctor_id'=>(int)$appointment_doctor_id],'=',true);
                    $data->service_charges = (int)$get_mapping_services->charges;
                    $total_amount = $total_amount + (int)$get_mapping_services->charges;
				}
				
				$tax = apply_filters('kivicare_calculate_tax',[
                    'status' => false,
                    'message' => '',
                    'data' => []
                ], [
                    "id" => $appointment_id,
                    "type" => 'appointment',
                ]);
				if(is_array($tax)){
					$total_amount = $tax['tax_total'] + $total_amount;
				}
                $patient_encounter_id = (new KCPatientEncounter())->get_var([ 'appointment_id' => (int)$appointment_id], 'id');
				if(empty($patient_encounter_id)){
                    return;
                }
                $patient_bill = (new KCBill())->insert([
					'encounter_id' =>(int)$patient_encounter_id,
					'appointment_id'=> (int)$appointment_id,
					'total_amount'=>$total_amount,
					'discount'=>0,
					'actual_amount'=>$total_amount,
					'status'=>0,
					'payment_status'=>'unpaid',
					'created_at'=>current_time( 'Y-m-d H:i:s' )
				]);

				if($patient_bill){
                    foreach ( $appointment_service as $key => $data ) {
                        (new self())->insert([
                            'bill_id' => (int)$patient_bill,
                            'price'   => (int)$data->service_charges,
                            'qty'     => 1,
                            'item_id' => (int)$data->service_id,
                            'created_at' => current_time( 'Y-m-d H:i:s' )
                        ]);
                    }
					
				}
			
			}
		}
	}
}