<?php

namespace App\models;


use App\baseClasses\KCBase;
use App\baseClasses\KCModel;
class KCCustomForm extends KCModel
{

    public function __construct()
    {
        parent::__construct('custom_forms');
    }

    /**
     * Check user permissions for a custom form based on the user's role and the form's creator.
     *
     * @param int $id The ID of the custom form to check permissions for.
     *
     * @return bool Returns true if the user has permission, false otherwise.
     */
    public function customFormPermissionUserWise($id){
        // Get the custom form data based on the provided ID.
        $data = $this->get_by(['id' => $id], '=', true);

        // Create an instance of the KCBase class.
        $kcbase = (new KCBase());

        // Check if the user's role is the clinic admin role.
        $isClinicAdmin = $kcbase->getLoginUserRole() === $kcbase->getClinicAdminRole();

        // Check if the custom form was added by the current user.
        $isAddedByCurrentUser = $data->added_by == get_current_user_id();

        // Determine if the user has permission based on their role and the form's creator.
        return $isClinicAdmin ? $isAddedByCurrentUser : true;
    }

}