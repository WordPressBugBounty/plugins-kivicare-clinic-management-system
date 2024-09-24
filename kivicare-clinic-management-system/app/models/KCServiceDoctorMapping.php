<?php

namespace App\models;

use App\baseClasses\KCModel;

class KCServiceDoctorMapping extends KCModel {

    public function __construct()
    {
        parent::__construct('service_doctor_mapping');
    }

	public function serviceUserPermission($service_id){
		$doctor_id = $this->get_var(
			[
				'id' => $service_id
			],
			'doctor_id'
		);
		return (new KCUser())->doctorPermissionUserWise($doctor_id);
	}

}