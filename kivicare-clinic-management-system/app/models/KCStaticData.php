<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCStaticData extends KCModel {

	public function __construct()
	{
		parent::__construct('static_data');
	}

}