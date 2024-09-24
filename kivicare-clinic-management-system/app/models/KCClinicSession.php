<?php


namespace App\models;

use App\baseClasses\KCBase;
use App\baseClasses\KCModel;

class KCClinicSession extends KCModel
{

	public function __construct()
	{
		parent::__construct('clinic_sessions');
	}

	public  function sessionPermissionUserWise($session_id)
	{
		$session_detail = (new KCClinicSession())->get_by(['id' => (int)$session_id], '=', true);
		$permission = false;
		$kcbase = new KCBase();
		$login_user_role = $kcbase->getLoginUserRole();
		switch ($login_user_role) {

			case $kcbase->getReceptionistRole():
				$clinic_id = kcGetClinicIdOfReceptionist();
				if (!empty($session_detail->clinic_id) && (int)$session_detail->clinic_id === $clinic_id) {
					$permission = true;
				}
				break;
			case $kcbase->getClinicAdminRole():
				$clinic_id = kcGetClinicIdOfClinicAdmin();
				if (!empty($session_detail->clinic_id) && (int)$session_detail->clinic_id === $clinic_id) {
					$permission = true;
				}
				break;
			case 'administrator':
				$permission = true;
				break;
			case $kcbase->getDoctorRole():
				if (!empty($session_detail->doctor_id) && (int)$session_detail->doctor_id === get_current_user_id()) {
					$permission = true;
				}
				break;
		}
		return $permission;
	}
}
