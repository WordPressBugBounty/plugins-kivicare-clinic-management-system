<?php


namespace App\models;

use App\baseClasses\KCBase;
use App\baseClasses\KCModel;

class KCUser extends KCModel {

	public $kcbase;
	public function __construct()
	{
		$this->kcbase = (new KCBase());
		parent::__construct('users');
	}

	public function doctorPermissionUserWise($doctor_id){
		$doctor_id = (int)$doctor_id;
		$permission = false;
		$login_user_role = $this->kcbase->getLoginUserRole();
		switch ($login_user_role){
			case 'administrator':
				$permission = true;
				break;
			case $this->kcbase->getClinicAdminRole();
			case $this->kcbase->getReceptionistRole():
				$clinic_id = $login_user_role === $this->kcbase->getClinicAdminRole() ? kcGetClinicIdOfClinicAdmin() : kcGetClinicIdOfReceptionist();
				$doctor_clinic = (new KCDoctorClinicMapping())->get_var(
					[
						"clinic_id" => $clinic_id,
						'doctor_id' => $doctor_id
					],
					'id'
				);
				if(!empty($doctor_clinic)){
					$permission = true;
				}
				break;
			case $this->kcbase->getDoctorRole():
				if($doctor_id === get_current_user_id()){
					$permission = true;
				}
				break;
		}

		return $permission;
	}


	public function patientPermissionUserWise($patient_id){
		$patient_id = (int)$patient_id;
		$permission = false;
		$login_user_role = $this->kcbase->getLoginUserRole();
		switch ($login_user_role){
			case 'administrator':
				$permission = true;
				break;
			case $this->kcbase->getClinicAdminRole();
			case $this->kcbase->getReceptionistRole():
				$clinic_id = $login_user_role === $this->kcbase->getClinicAdminRole() ? kcGetClinicIdOfClinicAdmin() : kcGetClinicIdOfReceptionist();
				$doctor_clinic = (new KCPatientClinicMapping())->get_var(
					[
						"clinic_id" => $clinic_id,
						'patient_id' => $patient_id
					],
					'id'
				);
				if(!empty($doctor_clinic)){
					$permission = true;
				}
				break;
			case $this->kcbase->getPatientRole():
				if($patient_id === get_current_user_id()){
					$permission = true;
				}
				break;
			case $this->kcbase->getDoctorRole():
				if(in_array($patient_id,kcDoctorPatientList())){
					$permission = true;
				}
				break;
		}

		return $permission;
	}

	public function receptionistPermissionUserWise($receptionist_id){
		$current_user_role = $this->kcbase->getLoginUserRole();
		$receptionist_id = (int)$receptionist_id;
		$permission = false;
		switch ($current_user_role){
			case 'administrator':
				$permission = true;
				break;
			case $this->kcbase->getClinicAdminRole():
				$clinic_id = kcGetClinicIdOfClinicAdmin();
				$receptionist_clinic = (new KCReceptionistClinicMapping())->get_var(
					[
						'clinic_id' => $clinic_id,
						'receptionist_id' => $receptionist_id
					],
					'id'
				);
				if( ! empty( $receptionist_clinic ) ){
					$permission = true;
				}
				break;
			case $this->kcbase->getReceptionistRole():
				if (get_current_user_id() === $receptionist_id) {
					$permission = true;
				}
				break;
		}
		return $permission;

	}
}