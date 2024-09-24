<?php

namespace App\models;

use App\baseClasses\KCModel;


class KCAppointmentCalenderMapping extends KCModel {
    public function __construct()
    {
        parent::__construct('gcal_appointment_mapping');
    }
    public function save_event_key($meta_key, $meta_value, $appointment_id,$doctor_id){
        $newData = $this;
        return $newData->insert(['appointment_id'=> (int)$appointment_id,'event_key'=>$meta_key,'event_value'=>$meta_value,'doctor_id'=>(int)$doctor_id]);
    }
    public function delete_event_key($meta_key, $appointment_id){
        $getData = (new self())->get_by(['event_key' => $meta_key,'appointment_id'=>(int)$appointment_id], '=', true);
        return true;
    }

}


