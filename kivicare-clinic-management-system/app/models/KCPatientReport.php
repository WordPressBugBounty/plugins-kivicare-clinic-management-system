<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCPatientReport extends KCModel {

	public function __construct()
	{
		parent::__construct('patient_medical_report');
	}

}