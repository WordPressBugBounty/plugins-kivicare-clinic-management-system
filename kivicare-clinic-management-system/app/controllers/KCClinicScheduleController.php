<?php

namespace App\Controllers;


use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCClinic;
use App\models\KCClinicSchedule;
use App\models\KCReceptionistClinicMapping;
use Exception;
use WP_User;

class KCClinicScheduleController extends KCBase {

	public $db;

	public $table_name;

	public $db_config;

	/**
	 * @var KCRequest
	 */
	private $request;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->table_name = $wpdb->prefix . 'kc_clinic_schedule';

		$this->request = new KCRequest();

        parent::__construct();
	}

	public function index() {

        if (  !kcCheckPermission( 'clinic_schedule' ) ) {
	        wp_send_json(kcUnauthorizeAccessResponse());
        }

		$request_data = $this->request->getInputs();
		$clinic_schedule_table = $this->db->prefix . 'kc_clinic_schedule';
        $user_table = $this->db->base_prefix. 'users';
        $clinic_table = $this->db->prefix. 'kc_clinics';
        $paginationCondition = ' ';
        if((int)$request_data['perPage'] > 0){
            $perPage = (int)$request_data['perPage'];
            $offset = ((int)$request_data['page'] - 1) * $perPage;
            $paginationCondition = " LIMIT {$perPage} OFFSET {$offset} ";
        }
        $orderByCondition = " ORDER BY {$clinic_schedule_table}.id DESC ";
        $conditions = '';
        if(!empty($request_data['sort'])){
            $request_data['sort'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($request_data['sort'][0]),true));
            if(!empty($request_data['sort']['field']) && !empty($request_data['sort']['type']) && $request_data['sort']['type'] !== 'none'){
                switch ($request_data['sort']['field']){
                    case 'module_type':
                    case 'id':
                    case 'start_date':
                    case 'end_date':
                        $orderByCondition = " ORDER BY {$clinic_schedule_table}.".sanitize_sql_orderby($request_data['sort']['field'])." ".sanitize_sql_orderby(strtoupper($request_data['sort']['type']));
                        break;
                }
            }
        }
        if(isset($request_data['searchTerm']) && trim($request_data['searchTerm']) !== ''){
            $request_data['searchTerm'] = esc_sql(strtolower(trim($request_data['searchTerm'])));
            $conditions.= " AND ({$clinic_schedule_table}.id LIKE '%{$request_data['searchTerm']}%' 
                           OR {$clinic_schedule_table}.module_type LIKE '%{$request_data['searchTerm']}%' 
                           OR {$user_table}.display_name LIKE '%{$request_data['searchTerm']}%' 
                           OR {$clinic_table}.name LIKE '%{$request_data['searchTerm']}%'  ) ";
        }else{
            if(!empty($request_data['columnFilters'])){
                $request_data['columnFilters'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($request_data['columnFilters']),true));
                foreach ($request_data['columnFilters'] as $column => $searchValue){
					$searchValue = !empty($searchValue) ? $searchValue : '';
                    $searchValue = esc_sql(strtolower(trim($searchValue)));
                    $column = esc_sql($column);
                    if($searchValue === ''){
                        continue;
                    }
                    if($column === 'name'){
                        $conditions.= " AND ( {$user_table}.display_name LIKE '%{$searchValue}%' 
                           OR {$clinic_table}.name LIKE '%{$searchValue}%' ) ";
                    }else{
                        $conditions.= " AND {$clinic_schedule_table}.{$column} LIKE '%{$searchValue}%' ";
                    }
                }
            }
        }

		if($this->getLoginUserRole() === $this->getDoctorRole()){
            $conditions.= " AND module_id=".get_current_user_id();
		}else{
            if(!isKiviCareProActive()){
                $conditions.= ' AND module_type = "doctor"  OR ( module_id ='.kcGetDefaultClinicId().' AND module_type="clinic") ';
            }else{
                if($this->getLoginUserRole() === $this->getReceptionistRole()) {
                    $clinic_id = kcGetClinicIdOfReceptionist() ;
                    $doctor_id_array = collect($this->db->get_results("SELECT * FROM {$this->db->prefix}kc_doctor_clinic_mappings WHERE clinic_id={$clinic_id}"))->pluck('doctor_id')->implode(',');
                    $doctor_include_condition = !empty($doctor_id_array) ? " AND module_id IN ({$doctor_id_array}) " : ' AND module_id = -1';
                    $conditions.= ' AND (module_type = "doctor" '.$doctor_include_condition.')  OR ( module_id ='.$clinic_id.' AND module_type="clinic") ';
                }
                else if($this->getLoginUserRole() === $this->getClinicAdminRole()) {
                    $clinic_id = kcGetClinicIdOfClinicAdmin();
                    $doctor_id_array = collect($this->db->get_results("SELECT * FROM {$this->db->prefix}kc_doctor_clinic_mappings WHERE clinic_id={$clinic_id}"))->pluck('doctor_id')->implode(',');
                    $doctor_include_condition = !empty($doctor_id_array) ? " AND module_id IN ({$doctor_id_array}) " : ' AND module_id = -1';
                    $conditions.= ' AND (module_type = "doctor" '. $doctor_include_condition.')  OR ( module_id ='.$clinic_id.' AND module_type="clinic") ';
                }
            }
		}

		$moduleTypeLabels = [
			'doctor' => esc_html__('Doctor', 'kc-lang'),
			'clinic' => esc_html__('Clinic', 'kc-lang'),
		];

        $query = "SELECT {$clinic_schedule_table}.*,{$user_table}.display_name AS doctor_name,{$clinic_table}.name as clinic_name  FROM {$clinic_schedule_table}
                    LEFT JOIN {$user_table} ON {$user_table}.Id = {$clinic_schedule_table}.module_id
                    AND {$clinic_schedule_table}.module_type = 'doctor'
                    LEFT JOIN {$clinic_table} ON {$clinic_table}.Id = {$clinic_schedule_table}.module_id
                    AND {$clinic_schedule_table}.module_type = 'clinic'";


        $total_rows  = $this->db->get_var(  "SELECT count(*) FROM {$clinic_schedule_table}
                    LEFT JOIN {$user_table} ON {$user_table}.Id = {$clinic_schedule_table}.module_id
                    AND {$clinic_schedule_table}.module_type = 'doctor'
                    LEFT JOIN {$clinic_table} ON {$clinic_table}.Id = {$clinic_schedule_table}.module_id
                    AND {$clinic_schedule_table}.module_type = 'clinic' WHERE 0=0 {$conditions} ");

		$clinic_schedule = collect($this->db->get_results( "{$query} WHERE 0=0 {$conditions} {$orderByCondition} {$paginationCondition} "))->map(function($v){
            $v->name =  $v->module_type === 'clinic' ? $v->clinic_name : $v->doctor_name;
			$v->start_date = kcGetFormatedDate($v->start_date);
			$v->end_date = kcGetFormatedDate($v->end_date);
			if (isset($v->module_type)) {
				$v->module_type_label = $moduleTypeLabels[$v->module_type] ?? $v->module_type;
			}
            return $v;
        });
		
        if (empty($clinic_schedule)) {
	        wp_send_json([
                'status' => false,
                'message' => esc_html__('No holidays found', 'kc-lang'),
                'data' => []
            ]);
        }

		wp_send_json([
			'status'  => true,
			'message' => esc_html__('Schedule list', 'kc-lang'),
			'data'    => $clinic_schedule,
			'total_rows' =>  $total_rows
		]);
	}

	public function save() {


        if (  !kcCheckPermission( 'clinic_schedule' ) ) {
	        wp_send_json( kcUnauthorizeAccessResponse(403) );
        }


		$request_data = $this->request->getInputs();
		$status = true;
		$temp = [
			'module_type' => $request_data['module_type']['id'],
			'start_date' => date('Y-m-d', strtotime($request_data['scheduleDate']['start'])),
			'end_date' => date('Y-m-d', strtotime($request_data['scheduleDate']['end'])),
			'module_id' => (int)$request_data['module_id']['id'],
			'description' => $request_data['description'],
			'status' => 1
		];

		$data = [
			'start_date' => $temp['start_date'],
			'end_date' => $temp['end_date']
		];

		if ($temp['module_type'] === 'doctor') {
			$data['doctor_id'] = $temp['module_id'];
		} else {
			$data['clinic_id'] = $temp['module_id'];
		}
		$clinic_schedule = new KCClinicSchedule;
		if(!$clinic_schedule->checkClinicSchedulePermission($temp['module_id'],$temp['module_type'])){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		// Cancel appointment if exist...
		kcCancelAppointments($data);


		$holiday_already_exist = 0;
		if (!isset($request_data['id'])) {
			$temp['created_at'] = current_time('Y-m-d H:i:s');
			$holiday_already_exist = $this->db->get_var("SELECT  count(*) FROM  ".$this->table_name." WHERE module_id='".$temp['module_id']."' AND module_type ='".$temp['module_type']."' AND ((start_date BETWEEN '".$temp['start_date']."' AND '".$temp['end_date']."') OR (end_date BETWEEN '".$temp['start_date']."' AND '".$temp['end_date']."'))");
			if($holiday_already_exist == 0){
				$clinic_schedule->insert($temp);
				if($temp['module_type'] === 'doctor'){
					$message = esc_html__("Doctor holiday scheduled successfully.", 'kc-lang');
				}else{
					$message = esc_html__("Clinic holiday scheduled successfully.", 'kc-lang');
				}
			}else{
				if($temp['module_type'] === 'doctor'){
					$message = esc_html__("Doctor already has holiday scheduled.", 'kc-lang');
					$status = false;
				}else{
					$message = esc_html__("Clinic already has holiday scheduled.", 'kc-lang');
					$status = false;
				}
			}

		} else {
			$holiday_already_exist = $this->db->get_var("SELECT  count(*) FROM  ".$this->table_name." WHERE id !='".$request_data['id']."' AND module_id='".$temp['module_id']."' AND module_type ='".$temp['module_type']."' AND ((start_date BETWEEN '".$temp['start_date']."' AND '".$temp['end_date']."') OR (end_date BETWEEN '".$temp['start_date']."' AND '".$temp['end_date']."'))");
			if($holiday_already_exist == 0){
				$clinic_schedule->update($temp, array( 'id' => (int)$request_data['id'] ));
				if($temp['module_type'] === 'doctor'){
					$message = esc_html__("Doctor holiday schedule updated successfully.", 'kc-lang');
				}else{
					$message = esc_html__("Clinic holiday schedule updated successfully.", 'kc-lang');
				}
			}else{
				if($temp['module_type'] === 'doctor'){
					$message = esc_html__("Doctor already has holiday scheduled.", 'kc-lang');
					$status = false;
				}else{
					$message = esc_html__("Clinic already has holiday scheduled.", 'kc-lang');
					$status = false;
				}
			}
		}

		wp_send_json([
			'status' => $status,
			'message' => $message
		]);

	}

	public function edit() {

        if (  !kcCheckPermission( 'clinic_schedule' ) ) {
	        wp_send_json( kcUnauthorizeAccessResponse(403));
        }

		$request_data = $this->request->getInputs();

		try {

			if (!isset($request_data['id'])) {
				wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
			}

			$id = (int)$request_data['id'];

			$clinic_schedule = (new KCClinicSchedule)->get_by([ 'id' => $id], '=', true);

			if (!empty($clinic_schedule)) {

				if(isset($clinic_schedule->module_type) && isset($clinic_schedule->module_id)) {
					if(!(new KCClinicSchedule)->checkClinicSchedulePermission($clinic_schedule->module_id,$clinic_schedule->module_type)){
						wp_send_json(kcUnauthorizeAccessResponse(403));
					}
					if (  $clinic_schedule->module_type === 'doctor') {
						$clinic_schedule->module_id = $this->getDoctorOption($clinic_schedule->module_id);
					} elseif ($clinic_schedule->module_type === 'clinic') {
						$clinic_schedule->module_id = [
							'id' => kcClinicDetail($clinic_schedule->module_id) -> id,
							'label' => kcClinicDetail($clinic_schedule->module_id) -> name
						];
					}

					$clinic_schedule->module_type = [
						'id' => $clinic_schedule->module_type,
						'label' => $clinic_schedule->module_type === 'doctor' ? 'Doctor' : 'Clinic'
					];

				} else {
					$clinic_schedule->module_type = [
						'id' => '',
						'label' => ''
					];
				}

			}

			$clinic_schedule->scheduleDate = [
				'start' => $clinic_schedule->start_date,
				'end' => $clinic_schedule->end_date
			];

			if ($clinic_schedule) {
				wp_send_json([
					'status' => true,
					'message' => esc_html__('Static data', 'kc-lang'),
					'data' => $clinic_schedule
				]);
			} else {
				wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
			}


		} catch (Exception $e) {

			$code = $e->getCode();
			$message = $e->getMessage();

			header("Status: $code $message");

			wp_send_json([
				'status' => false,
				'message' => $message
			]);
		}
	}

	public function delete() {

        if (  !kcCheckPermission( 'clinic_schedule' ) ) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        
		$request_data = $this->request->getInputs();

		try {

			if (!isset($request_data['id'])) {
				wp_send_json(kcThrowExceptionResponse(__('Data not found','kc-lang'), 400));
			}

			$id = (int)$request_data['id'];

			$clinic_schedule_data = (new KCClinicSchedule)->get_by([ 'id' => $id], '=', true);

			$clinic_schedule = new KCClinicSchedule;

			if(!empty($clinic_schedule_data)){
				if(!$clinic_schedule->checkClinicSchedulePermission($clinic_schedule_data->module_id,$clinic_schedule_data->module_type)){
					wp_send_json(kcUnauthorizeAccessResponse(403));
				}
			}
			$results = $clinic_schedule->delete(['id' => $id]);

			if ($results) {
				wp_send_json([
					'status' => true,
					'tableReload' => true,
					'message' => esc_html__('Clinic schedule deleted successfully', 'kc-lang'),
				]);
			} else {
				wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
			}


		} catch (Exception $e) {

			$code = $e->getCode();
			$message = $e->getMessage();

			header("Status: $code $message");

			wp_send_json([
				'status' => false,
				'message' => $message
			]);
		}
	}

	public function saveTermsCondition () {

		if($this->getLoginUserRole() !== 'administrator'){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
		$request_data = $this->request->getInputs();

		delete_option('terms_condition_content');
		delete_option('is_term_condition_visible');

		add_option( 'terms_condition_content', $request_data['content']);
		add_option( 'is_term_condition_visible', $request_data['isVisible']) ;

		wp_send_json([
			'status' => true,
			'message' => esc_html__('Terms & Condition saved successfully', 'kc-lang')
		]);
	}

	public function getTermsCondition () {
		if($this->getLoginUserRole() !== 'administrator'){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
		$term_condition = get_option( 'terms_condition_content');
		$term_condition_status = get_option( 'is_term_condition_visible') ;
		wp_send_json([
			'status' => true,
			'data' => array( 'isVisible' => $term_condition_status,
			                 'content' => $term_condition)
		]);
	}

    public function getDoctorOption($doctor_id)
    {
        $temp = [];
        $doctor = WP_User::get_data_by('ID', $doctor_id);

        if ($doctor) {
            $temp = [
                'id' => (int)$doctor->ID,
                'label' => $doctor->display_name,
            ];

            $user_data = get_user_meta($doctor->ID, 'basic_data', true);
            $user_data = json_decode($user_data);

            $temp['timeSlot'] = isset($user_data->time_slot) ? $user_data->time_slot : "";
        }

        return $temp;

    }
}
