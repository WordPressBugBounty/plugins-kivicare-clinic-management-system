<?php


namespace App\models;

use App\baseClasses\KCBase;
use App\baseClasses\KCModel;

class KCClinicSchedule extends KCModel {

	public function __construct()
	{
		parent::__construct('clinic_schedule');
	}

	public static function checkClinicSchedulePermission($module_id,$module_type){
		$kcbase = (new KCBase());
		$permission = false;
		$login_user_role = $kcbase->getLoginUserRole();
		switch ($login_user_role){
			case 'administrator':
				$permission = true;
				break;
			case $kcbase->getClinicAdminRole():
			case $kcbase->getReceptionistRole():
				$clinic_id = $login_user_role === $kcbase->getClinicAdminRole() ? kcGetClinicIdOfClinicAdmin() : kcGetClinicIdOfReceptionist();
				if($module_type === 'clinic'){
					if((int)$module_id === $clinic_id){
						$permission = true;
					}
				}else{
					$doctor_clinic = (new KCDoctorClinicMapping())->get_var(
						[
							'doctor_id' => (int)$module_id,
							'clinic_id' => $clinic_id
						],
						'id'
					);
					if(!empty($doctor_clinic)){
						$permission = true;
					}
				}
				break;
			case $kcbase->getDoctorRole():
				if($module_type === 'doctor'){
					if((int)$module_id === get_current_user_id()){
						$permission = true;
					}
				}
				break;
		}
		return $permission;
	}
}