<?php


namespace App\models;

use App\baseClasses\KCModel;

class KCBill extends KCModel {

	public function __construct()
	{
		parent::__construct('bills');
	}

}