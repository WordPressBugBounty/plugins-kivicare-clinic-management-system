<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCPatientClinicMapping extends KCModel {

	public function __construct()
	{
		parent::__construct('patient_clinic_mappings');
	}


}