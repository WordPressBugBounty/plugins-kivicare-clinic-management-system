<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCClinicSession;
use mysql_xdevapi\Session;

class KCClinicSessionController extends KCBase
{
    public $db;

    private $request;

    public function __construct() {

        global $wpdb;

        $this->db = $wpdb;

        $this->request = new KCRequest();

        parent::__construct();
    }

    public function index () {

        if ( ! kcCheckPermission( 'doctor_session_list' ) ) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $clinic_sessios_table = $this->db->prefix.'kc_clinic_sessions';
        $user_table = $this->db->base_prefix .'users';
        $clinic_table = $this->db->prefix.'kc_clinics';
        if(isKiviCareProActive()){
            $response = apply_filters('kcpro_clinic_session_list', []);
            if(empty($response['status']) && empty($response['data'])){
	            wp_send_json([
                    'status' => true,
                    'message' => esc_html__('No clinic session list found', 'kc-lang'),
                    'data' => [
                        'clinic_sessions' => []
                    ]
                ]);
            }else{
	            wp_send_json([
                    'status' => true,
                    'message' => esc_html__('Clinic session list', 'kc-lang'),
                    'data' => [
                        'clinic_sessions' => $response['data']
                    ]
                ]);
            }
        }else{
            $query = "SELECT {$clinic_sessios_table}.*,{$user_table}.display_name AS doctor_name,{$clinic_table}.name AS clinic_name FROM {$clinic_sessios_table} 
                      LEFT JOIN {$user_table} ON {$user_table}.ID = {$clinic_sessios_table}.doctor_id 
                      LEFT JOIN {$clinic_table} ON {$clinic_table}.id = {$clinic_sessios_table}.clinic_id ";
            if($this->getLoginUserRole() === $this->getDoctorRole()){
                $where_conditions = " AND {$clinic_sessios_table}.doctor_id=".get_current_user_id();
            }else {
                $where_conditions = " AND {$clinic_sessios_table}.clinic_id=".kcGetDefaultClinicId();
            }

            $clinic_sessions = $this->db->get_results("{$query}  WHERE 0=0 AND {$user_table}.user_status=0 {$where_conditions}");

            if(empty($clinic_sessions)){
	            wp_send_json([
                    'status' => true,
                    'message' => esc_html__('No clinic session list found', 'kc-lang'),
                    'data' =>  [
                        'clinic_sessions' => []
                    ]
                ]);
            }

            $clinic_sessions = kcClinicSession($clinic_sessions);

	        wp_send_json([
                'status' => true,
                'message' => esc_html__('Clinic session list', 'kc-lang'),
                'data' =>  [
                    'clinic_sessions' => $clinic_sessions
                ]
            ]);
        }

    }
    public function save () {

        if ( ! kcCheckPermission( 'doctor_session_add' ) ) {
	        wp_send_json( kcUnauthorizeAccessResponse());
        }

        $request_data = $this->request->getInputs();

        // Insert clinic session...
        switch ($this->getLoginUserRole()) {

            case $this->getReceptionistRole():
                $clinic_id = kcGetClinicIdOfReceptionist();
                break;
            case $this->getClinicAdminRole():
                $clinic_id = kcGetClinicIdOfClinicAdmin();
                break;
            default:
                $clinic_id =  !empty($request_data['clinic_id']['id']) ? $request_data['clinic_id']['id'] :kcGetDefaultClinicId();
                break;
        }

        $validationFalse = false;
        $session = $request_data ;
        $session['doctors']['id'] = $this->getLoginUserRole() === $this->getDoctorRole()? get_current_user_id() : (int)$session['doctors']['id'];
        foreach ($session['days'] as $day1) {            
            $day1 = substr($day1, 0, 3);
            $query_check = $this->db->get_results("SELECT * FROM {$this->db->prefix}kc_clinic_sessions WHERE  doctor_id={$session['doctors']['id']} AND clinic_id={$clinic_id} AND `day` = '{$day1}'");
            if(!empty($query_check)){
                foreach ($query_check as $query_check_value){
                    if(!empty($request_data['id'])){
                        if((int)$query_check_value->id === (int)$request_data['id'] || (int)$query_check_value->parent_id === (int)$request_data['id'] ){
                            continue;
                        }
                    }
                    $new_start_time =date('h:i A', strtotime($query_check_value->start_time));
                    $new_end_time =date('h:i A', strtotime($query_check_value->end_time));
                    if(!empty($session['s_one_start_time']['HH']) && $session['s_one_start_time']['mm']){
                        $startTime = date('h:i A',strtotime($session['s_one_start_time']['HH'] . ':' . $session['s_one_start_time']['mm']));
                        if((strtotime($startTime) > strtotime($new_start_time)) && (strtotime($startTime) < strtotime($new_end_time))){
                            $validationFalse = true;
                        }
                    }
                    if(!empty($session['s_one_end_time']['HH']) && $session['s_one_end_time']['mm']){
                        $endTime = date('h:i A',strtotime($session['s_one_end_time']['HH'] . ':' . $session['s_one_end_time']['mm']));
                        if((strtotime($endTime) > strtotime($new_start_time)) && (strtotime($endTime) < strtotime($new_end_time))){
                            $validationFalse = true;
                        }
                    }

                    if(!empty($session['s_two_start_time']['HH']) && $session['s_two_start_time']['mm']){
                        $startTime = date('h:i A',strtotime($session['s_two_start_time']['HH'] . ':' . $session['s_two_start_time']['mm']));
                        if((strtotime($startTime) > strtotime($new_start_time)) && (strtotime($startTime) < strtotime($new_end_time))){
                            $validationFalse = true;
                        }
                    }
                    if(!empty($session['s_two_end_time']['HH']) && $session['s_two_end_time']['mm']){
                        $endTime = date('h:i A',strtotime($session['s_two_end_time']['HH'] . ':' . $session['s_two_end_time']['mm']));
                        if((strtotime($endTime) > strtotime($new_start_time)) && (strtotime($endTime) < strtotime($new_end_time))){
                            $validationFalse = true;
                        }
                    }

                    if($new_start_time == $startTime && $new_end_time == $endTime){
                        $validationFalse = true;
                    }

                }
            }
        }

        if( $validationFalse){
	        wp_send_json( [
                'status'      => false,
                'message'     =>  esc_html__('Selected Doctor is already added in another session','kc-lang'),
                'data'        => []
            ] );
        }

        $clinic_session = new KCClinicSession();

        if (isset($request_data['id']) && $request_data['id'] !== '' ) {

            if(!((new KCClinicSession())->sessionPermissionUserWise($request_data['id']))){
	            wp_send_json( kcUnauthorizeAccessResponse(403) );
            }

            $request_data['id'] = (int)$request_data['id'];
            // delete parent session
            $clinic_session->delete(['id' => $request_data['id']]);

            // delete child session
            $clinic_session->delete(['parent_id' => $request_data['id']]);

        }

        $session = $request_data ;
        $parent_id = 0;

        foreach ($session['days'] as $day) {

            $result = true ;

            $start_time = date('H:i:s', strtotime($session['s_one_start_time']['HH'] . ':' . $session['s_one_start_time']['mm']));
            $end_time = date('H:i:s', strtotime($session['s_one_end_time']['HH'] . ':' . $session['s_one_end_time']['mm']));
            $session_temp = [
                'clinic_id' => (int)$clinic_id,
                'doctor_id' => $this->getLoginUserRole() === $this->getDoctorRole()? get_current_user_id() : (int)$session['doctors']['id'],
                'day' =>  substr($day, 0, 3),
                'start_time' => $start_time,
                'end_time' => $end_time,
                'time_slot' => $session['time_slot'],
                'created_at' => current_time('Y-m-d H:i:s'),
                'parent_id' => (int) $parent_id === 0 ? null : (int) $parent_id
            ];

            if ($parent_id === 0) {
                $parent_id = (int)$clinic_session->insert($session_temp);
            } else {
                $result =  (int)$clinic_session->insert($session_temp);
            }

            if ($session['s_two_start_time']['HH'] !== null && $session['s_two_end_time']['HH'] !== null) {

                $session_temp['start_time'] = date('H:i:s', strtotime($session['s_two_start_time']['HH'] . ':' . $session['s_two_start_time']['mm']));
                $session_temp['end_time'] = date('H:i:s', strtotime($session['s_two_end_time']['HH'] . ':' . $session['s_two_end_time']['mm']));
                $session_temp['parent_id'] = $parent_id;
                $result =  $clinic_session->insert($session_temp);

            }

            if(!$result) {
	            wp_send_json([
                    'status' => false,
                    'message' => esc_html__('Failed to save clinic session. Please try again.', 'kc-lang')
                ]);
            }

        }

	    wp_send_json([
            'status' => true,
            'message' => esc_html__('Doctor session saved successfully', 'kc-lang')
        ]);
    }

    public function delete () {

        if ( ! kcCheckPermission( 'doctor_session_delete' ) ) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $request_data = $this->request->getInputs();

        $clinic_session = new KCClinicSession();

        if (isset($request_data['session_id']) && $request_data['session_id'] !== '' ) {

            if(!((new KCClinicSession())->sessionPermissionUserWise($request_data['session_id']))){
	            wp_send_json(kcUnauthorizeAccessResponse(403));
            }

            $request_data['session_id'] = (int)$request_data['session_id'];
            // delete parent session
            $clinic_session->delete(['id' => $request_data['session_id']]);

            // delete child session
            $clinic_session->delete(['parent_id' => $request_data['session_id']]);

	        wp_send_json([
                'status' => true,
                'message' => esc_html__('Doctor session deleted successfully', 'kc-lang')
            ]);

        }
    }


    public function saveTimeZoneOption(){
		if(  $this->getLoginUserRole() !== 'administrator'){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
        $request_data = $this->request->getInputs();
        $status = false;
        if(isset($request_data['time_status']) && !empty($request_data['time_status']) ){
            update_option(KIVI_CARE_PREFIX.'timezone_understand',$request_data['time_status']);
            $status = true;
        }
        $response = [
            'status' => true,
            'data' => $status,
        ];
	    wp_send_json($response);
    }
}