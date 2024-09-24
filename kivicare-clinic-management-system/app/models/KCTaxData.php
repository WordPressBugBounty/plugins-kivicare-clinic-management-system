<?php

namespace App\models;

use App\baseClasses\KCModel;

class KCTaxData extends KCModel
{
    public function __construct()
    {
        parent::__construct('tax_data');
    }

    public function get_tax($data)
    {


        if ($data['module_type'] == 'encounter') {

            $appointment_id = (new KCPatientEncounter)->get_var([
                "id" => $data['module_id']
            ], 'appointment_id', true);


            return collect(array_merge($this->get_by([
                'module_id' => $data['module_id'],
                'module_type' => $data['module_type']
            ]), $this->get_by([
                'module_id' => $appointment_id,
                'module_type' => 'appointment'
            ])))->unique('name')->values()->toArray();
        }

        return $this->get_by([
            'module_id' => $data['module_id'],
            'module_type' => $data['module_type']
        ]);
    }
}
