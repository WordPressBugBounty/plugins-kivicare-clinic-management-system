<?php

namespace App\models;
use TenQuality\WP\Database\Abstracts\DataModel;
use TenQuality\WP\Database\Traits\DataModelTrait;

class KCDoctorClinicMappingNewModel extends DataModel
{

    use DataModelTrait;
    /**
     * Data table name in database (without prefix).
     * @var string
     */


    const TABLE = 'kc_doctor_clinic_mappings';
    /**
     * Data table name in database (without prefix).
     * @var string
     */
    protected $table = self::TABLE;
    protected $primary_key = 'id';
}
