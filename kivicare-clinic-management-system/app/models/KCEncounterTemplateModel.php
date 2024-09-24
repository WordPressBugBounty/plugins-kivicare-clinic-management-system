<?php

namespace App\models;
use TenQuality\WP\Database\Abstracts\DataModel;
use TenQuality\WP\Database\Traits\DataModelTrait;

class KCEncounterTemplateModel extends DataModel
{

    use DataModelTrait;
    /**
     * Data table name in database (without prefix).
     * @var string
     */



    const TABLE = 'kc_patient_encounters_template';
    /**
     * Data table name in database (without prefix).
     * @var string
     */
    protected $table = self::TABLE;
    protected $primary_key = 'id';

    // protected $attributes = [
    //     'created_at',
    //     'updated_at',
    // ];

}
