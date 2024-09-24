<?php


namespace App\models;

use App\baseClasses\KCBase;
use App\baseClasses\KCModel;

class KCPatientEncounter extends KCModel {

	public function __construct()
	{
		parent::__construct('patient_encounters');
	}

	public static function createEncounter($appointment_id) {
		$appointment = (new KCAppointment())->get_by([ 'id' => (int)$appointment_id], '=', true);

		$encounter = (new self())->get_var(['appointment_id' => (int)$appointment_id], 'id');

		if (!empty($appointment) && empty($encounter)) {
			$id =  (new self())->insert([
				'encounter_date' => date('Y-m-d'),
				'clinic_id' => (int)$appointment->clinic_id,
				'doctor_id' => (int)$appointment->doctor_id,
				'patient_id' => (int)$appointment->patient_id,
				'appointment_id' => (int)$appointment_id,
				'description' => $appointment->description,
				'added_by' => get_current_user_id(),
				'status' => 1,
				'created_at' => current_time( 'Y-m-d H:i:s' )
			]);
            do_action('kc_encounter_save', $id);
            return $id;
		} else {
			return $encounter;
		}

	}
	public static function closeEncounter($appointment_id,$appointment_status) {

        $encounter_id = (new self())->get_var(['appointment_id' => $appointment_id], 'id');

		if (!empty($encounter_id)) {
            $billStatus = 0;
            $billPayment = 'paid';
            if($appointment_status === '0'){
                $billStatus = 1;
                $billPayment = "unpaid";
            }
            ( new KCBill() )->update( [ 'status' => $billStatus ,'payment_status' => $billPayment], [ 'encounter_id' => (int)$encounter_id ] );
			return (new self())->update( [ 'status' => '0' ], array( 'id' => $encounter_id ) );
		}

	}

    public static function encounterPermissionUserWise($encounter_id){
        $encounter_detail = (new KCPatientEncounter())->get_by(['id' => (int)$encounter_id],'=',true);
        $kcbase = (new KCBase());
        $login_user_role = $kcbase->getLoginUserRole();
        $permission = false;
        switch ($login_user_role){
            case $kcbase->getReceptionistRole():
                $clinic_id = kcGetClinicIdOfReceptionist();
                if(!empty($encounter_detail->clinic_id) && (int)$encounter_detail->clinic_id === $clinic_id ){
                    $permission = true;
                }
                break;
            case $kcbase->getClinicAdminRole():
                $clinic_id = kcGetClinicIdOfClinicAdmin();
                if(!empty($encounter_detail->clinic_id) && (int)$encounter_detail->clinic_id === $clinic_id ){
                    $permission = true;
                }
                break;
            case 'administrator':
                $permission = true;
                break;
            case $kcbase->getDoctorRole():
                if(!empty($encounter_detail->doctor_id) && (int)$encounter_detail->doctor_id === get_current_user_id() ){
                    $permission = true;
                }
                break;
            case $kcbase->getPatientRole():
                if(!empty($encounter_detail->patient_id) && (int)$encounter_detail->patient_id === get_current_user_id() ){
                    $permission = true;
                }
                break;
        }
        return $permission;
    }

    public function loopAndDelete($condition, $appointment_delete){
        $instance = (new self());
        $all_encounters = $instance->get_by($condition);
        foreach ($all_encounters as $encounter) {
            $encounterBill = (new KCBill())->get_var(['encounter_id' => $encounter->id],"id");
            if(!empty($encounterBill)){
                (new KCBillItem())->delete(['bill_id' => $encounterBill]);
                (new KCBill())->delete(['id' => $encounterBill]);
            }
            if($appointment_delete && !empty($encounter->appointment_id)){
                (new KCAppointment())->loopAndDelete(['id' => $encounter->appointment_id],false);
            }
            do_action('kivicare_custom_form_data_delete', 'patient_encounter_module', $encounter->id);
            do_action('kc_encounter_delete', $encounter->id);
        }
        return $instance->delete($condition);
    }
}