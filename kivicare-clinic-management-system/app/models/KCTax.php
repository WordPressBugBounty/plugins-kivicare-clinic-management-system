<?php


namespace App\models;

use App\baseClasses\KCBase;
use App\baseClasses\KCModel;

class KCTax extends KCModel {

    public function __construct()
    {
        parent::__construct('taxes');
    }

    public function checkUserRoleWisePermission($tax_id){
        $kcbase = (new KCBase());
        $data = $this->get_var(['id' => (int)$tax_id],'clinic_id');
        if(empty($data)){
            return false;
        }
        if($kcbase->getLoginUserRole() === $kcbase->getClinicAdminRole()){
            $user_clinic_id = kcGetClinicIdOfClinicAdmin();
            return (int)$data === $user_clinic_id;
        }
        return true;
    }

}