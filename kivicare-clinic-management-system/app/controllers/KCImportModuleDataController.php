<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCClinic;
use App\models\KCDoctorClinicMapping;
use App\models\KCPatientEncounter;
use function Clue\StreamFilter\fun;

class KCImportModuleDataController extends KCBase
{
    /**
     * @var KCRequest
     */
    public $db;

    private $request;

    public function __construct()
    {
        global $wpdb;

        $this->db = $wpdb;

        $this->request = new KCRequest();

        parent::__construct();
    }

    public function import(){

        $request_data = $this->request->getInputs();

        $rules=[
            'url' => 'required',
            'type' => 'required',
            'module_type' => 'required',
            'required_field' => 'required'
        ];

        $errors = kcValidateRequest($rules, $request_data);

        if (count($errors)) {

	        wp_send_json([
                'status' => false,
                'message' => $errors[0]
            ]);

        }

        $request_data['current_user_role'] = $this->getLoginUserRole();
        $permission = false;
        switch ($request_data['module_type']){
	        case 'appointment':
		        $permission = kcCheckPermission( 'appointment_add' ) ;
		        break;
            case 'static_data':
                $permission = kcCheckPermission( 'static_data_add' ) ;
                break;
            case 'service':
                if($request_data['current_user_role'] === $this->getReceptionistRole()){
                    $request_data['valid_doctor_id'] = collect((new KCDoctorClinicMapping())->get_by(['clinic_id' => kcGetClinicIdOfReceptionist()],'=',false))->pluck('doctor_id')->toArray();
                }elseif ($request_data['current_user_role'] === $this->getClinicAdminRole()){
                    $request_data['valid_doctor_id'] = collect((new KCDoctorClinicMapping())->get_by(['clinic_id' => kcGetClinicIdOfClinicAdmin()],'=',false))->pluck('doctor_id')->toArray();;
                }elseif ($request_data['current_user_role'] === $this->getDoctorRole()){
                    $request_data['valid_doctor_id'] = [get_current_user_id()];
                }elseif ($request_data['current_user_role'] === 'administrator'){
                    $request_data['valid_doctor_id'] = collect(get_users(['role' => $this->getDoctorRole() ,'fields' => ['ID']]))->pluck('ID')->toArray();
                }
                $request_data['valid_doctor_id'] = !empty($request_data['valid_doctor_id']) ? $request_data['valid_doctor_id'] : [-1];
                $permission = kcCheckPermission( 'service_add' ) ;
                break;
            case 'customField':
                $permission = kcCheckPermission( 'custom_field_add' ) ;
                break;
            case 'clinic':
                $permission = kcCheckPermission( 'clinic_add' ) ;
                break;
            case 'receptionist':
                $request_data['valid_clinic_id'] = $this->getClinicIDUserWise($request_data['current_user_role']);
                $permission = kcCheckPermission( 'receptionist_add' ) ;
                break;
            case 'doctor':
                $request_data['valid_clinic_id'] = $this->getClinicIDUserWise($request_data['current_user_role']);
                $permission = kcCheckPermission( 'doctor_add' ) ;
                break;
            case 'patient':
                $request_data['valid_clinic_id'] = $this->getClinicIDUserWise($request_data['current_user_role']);
                $permission = kcCheckPermission( 'patient_add' ) ;
                break;
            case 'prescription':
                if(empty($request_data['encounter_id'])){
	                wp_send_json( [
                        'status'      => false,
                        'message'     => __("Encounter id not found","kc-lang"),
                        'data'        => []
                    ] );
                }
                $condition = ['id' => (int)$request_data['encounter_id']];
                if($request_data['current_user_role'] === $this->getReceptionistRole()){
                    $condition['clinic_id'] = kcGetClinicIdOfReceptionist();
                }elseif ($request_data['current_user_role'] === $this->getClinicAdminRole()){
                    $condition['clinic_id'] = kcGetClinicIdOfClinicAdmin();
                }elseif ($request_data['current_user_role'] === $this->getDoctorRole()){
                    $condition['doctor_id'] = get_current_user_id();
                }
                $encounter_detail = (new KCPatientEncounter())->get_by($condition,'=',true);
                if(empty($encounter_detail)){
	                wp_send_json( [
                        'status'      => false,
                        'message'     => __("Encounter id not valid","kc-lang"),
                        'data'        => []
                    ] );
                }

                if(isset($encounter_detail) && (int)$encounter_detail != 1){
	                wp_send_json( [
                        'status'      => false,
                        'message'     => __("Encounter already closed","kc-lang"),
                        'data'        => []
                    ] );
                }

                $permission = kcCheckPermission( 'prescription_add' ) ;
                break;
        }
        if (!$permission) {
	        wp_send_json( [
                'status'      => false,
                'status_code' => 403,
                'message'     => $this->permission_message,
                'data'        => []
            ] );
        }

        $request_data['required_field'] = array_map(function ($v){
            $v = kcRecursiveSanitizeTextField(json_decode(stripslashes($v),true));
            return $v['value'];
        },$request_data['required_field']);

        $response = apply_filters('kcpro_import_module_wise_data', [
            'data' => $request_data,
        ]);

	    wp_send_json($response);
    }

    public function demoFiles(){

        if(!is_user_logged_in()){
	        wp_send_json( kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();

        $rules=[
            'module_type' => 'required',
        ];

        $errors = kcValidateRequest($rules, $request_data);

        if (count($errors)) {

	        wp_send_json([
                'status' => false,
                'message' => $errors[0]
            ]);

        }
        $response = apply_filters('kcpro_import_demo_files', [
            'data' => $request_data,
        ]);

	    wp_send_json($response);

    }

    public function getClinicIDUserWise($current_user_role){
        $clinic_ids = [];
        if($current_user_role === $this->getReceptionistRole()){
            $clinic_ids = kcGetClinicIdOfReceptionist();
        }elseif ($current_user_role === $this->getClinicAdminRole()){
            $clinic_ids = kcGetClinicIdOfClinicAdmin();
        }elseif ($current_user_role === $this->getDoctorRole()){
            $clinic_ids = collect((new KCDoctorClinicMapping())->get_by(['doctor_id' => get_current_user_id()],'=',false))->pluck('clinic_id')->toArray();
        }elseif ($current_user_role === 'administrator'){
            $clinic_ids =  collect((new KCClinic())->get_all())->pluck('id')->toArray();
        }
        return !empty($clinic_ids) ?  $clinic_ids : [-1];
    }
}