<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCMedicalRecords extends KCModel {

	public function __construct()
	{
		parent::__construct('medical_problems');
	}

}