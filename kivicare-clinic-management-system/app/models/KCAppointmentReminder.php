<?php


namespace App\models;

use App\baseClasses\KCBase;
use App\baseClasses\KCModel;

class KCAppointmentReminder extends KCModel
{

    public function __construct()
    {
        parent::__construct('appointment_reminder_mapping');
    }
}