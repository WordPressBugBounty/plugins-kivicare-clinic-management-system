<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCMedicalHistory extends KCModel {

	public function __construct()
	{
		parent::__construct('medical_history');
	}

}