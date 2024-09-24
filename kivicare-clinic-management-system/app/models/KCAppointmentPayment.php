<?php


namespace App\models;

use App\baseClasses\KCBase;
use App\baseClasses\KCModel;

class KCAppointmentPayment extends KCModel
{

    public function __construct()
    {
        parent::__construct('payments_appointment_mappings');
    }
}