<?php


namespace App\models;

use App\baseClasses\KCModel;
use TenQuality\WP\Database\Abstracts\DataModel;
use TenQuality\WP\Database\Traits\DataModelTrait;


class KCPrescription extends KCModel {

	public function __construct()
	{
		parent::__construct('prescription');
	}

}