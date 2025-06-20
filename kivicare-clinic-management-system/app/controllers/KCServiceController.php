<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCAppointmentServiceMapping;
use App\models\KCService;
use App\models\KCServiceDoctorMapping;
use App\models\KCReceptionistClinicMapping;
use App\models\KCClinic;
use App\models\KCDoctorClinicMapping;
use Exception;
use WP_User;
use function Clue\StreamFilter\fun;

class KCServiceController extends KCBase {

	public $db;
	/**
	 * @var KCRequest
	 */
	private $request;

    public $exclude_service;
	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->request = new KCRequest();

        parent::__construct();
	}

	public function index() {

        if ( ! kcCheckPermission( 'service_list' ) && is_user_logged_in()) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
		$request_data      = $this->request->getInputs();
		$service_table     = $this->db->prefix . 'kc_services';
		$service_doctor_mapping  = $this->db->prefix . 'kc_service_doctor_mapping' ;
		$users_table       = $this->db->base_prefix . 'users';
		$clinic_doctor_mapping = $this->db->prefix.'kc_doctor_clinic_mappings';
        $clinic_table = $this->db->prefix.'kc_clinics';
        //current login user role
        $current_login_user_role = $this->getLoginUserRole();

        //current login user id
        $current_login_user_id = get_current_user_id();

        //default query condition value
        $search_condition = $doctor_condition  = $clinic_condition =  $paginationCondition = $clinic_service_condition = " ";
        $orderByCondition = " ORDER BY {$service_doctor_mapping}.id  DESC ";

        //check request is from new appointment book shortcode/widget
        $request_from_new_appointment_widget = !empty($request_data['widgetType']) && $request_data['widgetType'] === 'phpWidget';

        //check request is from new appointment book shortcode/widget and check doctor id empty or not valid id
        $request_from_new_appointment_widget_and_service_first = $request_from_new_appointment_widget && (empty($request_data['doctor_id']) || in_array($request_data['doctor_id'],[0,'0']));

        //check request from service module (listing)
        $request_from_service_module =  !empty($request_data['type']) && $request_data['type'] === 'list' ;

        //check request is from new appointment book shortcode/widget
        if($request_from_new_appointment_widget){
            if (!empty($request_data['searchKey'])){
                $request_data['searchKey'] = esc_sql($request_data['searchKey']);
                $searchKey = $request_data['searchKey'];
                //search query condition
                $search_condition = " AND ({$service_table}.name LIKE '%{$searchKey}%' OR {$service_table}.type LIKE '%{$searchKey}%' OR {$service_doctor_mapping}.charges LIKE '%{$searchKey}%')";
            }
        }else if($request_from_service_module){
            if((int)$request_data['perPage'] > 0){
                $perPage = (int)$request_data['perPage'];
                $offset = ((int)$request_data['page'] - 1) * $perPage;
                $paginationCondition = " LIMIT {$perPage} OFFSET {$offset} ";
            }
            $orderByCondition = " ORDER BY id DESC ";
            if(!empty($request_data['sort'])){
                $request_data['sort'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($request_data['sort'][0]),true));
                if(!empty($request_data['sort']['field']) && !empty($request_data['sort']['type']) && $request_data['sort']['type'] !== 'none'){
                    $sortField = sanitize_sql_orderby($request_data['sort']['field']);
                    $sortByValue = sanitize_sql_orderby(strtoupper($request_data['sort']['type']));
                    switch($request_data['sort']['field']){
                        case 'charges':
                        case 'status':
                        case 'id':
                        case 'duration':
                        case 'service_id':
                            $orderByCondition = " ORDER BY {$service_doctor_mapping}.{$sortField} {$sortByValue} ";
                            break;
                        case 'name':
                            $orderByCondition = " ORDER BY {$service_table}.{$sortField} {$sortByValue} ";
                            break;
                        case 'doctor_name':
                            $orderByCondition = " ORDER BY {$users_table}.display_name {$sortByValue} ";
                            break;
                        case 'service_type':
                            $orderByCondition = " ORDER BY {$service_table}.type {$sortByValue} ";
                            break;
                    }
                }
            }

            if(isset($request_data['searchTerm']) && trim($request_data['searchTerm']) !== ''){
                $request_data['searchTerm'] = esc_sql(strtolower(trim($request_data['searchTerm'])));
                $status=null;
                // Extract status using regex
                if (preg_match('/:(active|inactive)/i', $request_data['searchTerm'], $matches)) {
                    $status = $matches[1]=='active'?'1':'0';
                    // Remove the matched status from the search term and trim
                    $request_data['searchTerm'] = trim( preg_replace('/:(active|inactive)/i', '', $request_data['searchTerm']));
                }
                $search_condition.= " AND (
                           {$service_doctor_mapping}.id LIKE '%{$request_data['searchTerm']}%' 
                           OR {$service_table}.name LIKE '%{$request_data['searchTerm']}%' 
                           OR {$users_table}.display_name LIKE '%{$request_data['searchTerm']}%' 
                           OR {$service_doctor_mapping}.charges LIKE '%{$request_data['searchTerm']}%' 
                           OR {$service_table}.type LIKE '%{$request_data['searchTerm']}%' 
                           OR {$service_doctor_mapping}.status LIKE '%{$request_data['searchTerm']}%' 
                           ) ";
                if(!is_null($status)){
                    $search_condition.= " AND {$service_doctor_mapping}.status LIKE '{$status}' ";
                }
            }else{
                if(!empty($request_data['columnFilters'])){
                    $request_data['columnFilters'] = json_decode(stripslashes($request_data['columnFilters']),true);
                    foreach ($request_data['columnFilters'] as $column => $searchValue){
                        $searchValue = !empty($searchValue) ? $searchValue : '';
                        $searchValue = esc_sql(strtolower(trim($searchValue)));
                        $column = esc_sql($column);
                        if($searchValue === ''){
                            continue;
                        }
                        switch($column){
                            case 'charges':
                            case 'id':
                                $search_condition.= " AND {$service_doctor_mapping}.{$column} LIKE '%{$searchValue}%' ";
                                break;
                            case 'duration':
                                list($hours, $minutes) = explode(":", $searchValue);
                                $searchValue = ((int)$hours * 60) + (int)$minutes;
                                $search_condition.= " AND {$service_doctor_mapping}.{$column} LIKE '%{$searchValue}%' ";
                                break;
                            case 'status':
                                if($searchValue === 'inactive'){
                                    $searchValue = '';
                                }
                                $search_condition.= " AND {$service_doctor_mapping}.{$column} = '{$searchValue}' ";
                                break;
                            case 'service_id':
                                $search_condition.= " AND {$service_doctor_mapping}.{$column} LIKE '%{$searchValue}%' ";
                                break;
                            case 'name':
                                $search_condition.= " AND {$service_table}.{$column} LIKE '%{$searchValue}%' ";
                                break;
                            case 'doctor_name':
                                $search_condition.= " AND {$users_table}.display_name LIKE '%{$searchValue}%' ";
                                break;
                            case 'service_type':
                                $search_condition.= " AND {$service_table}.type LIKE '%{$searchValue}%'";
                                break;
                            case 'clinic_name':
                                $search_condition.= " AND {$clinic_table}.name LIKE '%{$searchValue}%'";
                                break;    
                        }
                    }
                }
            }
        }

        //check if login user is doctor or request data have valid doctor id
		if(($this->getDoctorRole() === $current_login_user_role) || (isset($request_data['doctor_id']) && !in_array($request_data['doctor_id'],[0,'0']))) {

            //doctor id
            $doctor_id = $this->getDoctorRole() === $current_login_user_role ? $current_login_user_id : $request_data['doctor_id'];
            //doctor query condition
            if(str_contains($doctor_id,',')){
                $doctor_id = implode(',',array_map('absint',explode(',',$doctor_id)));
                $doctor_condition = " AND {$service_doctor_mapping}.doctor_id IN ({$doctor_id}) " ;
            }else{
                $doctor_id = (int)$doctor_id;
                $doctor_condition = " AND {$service_doctor_mapping}.doctor_id = {$doctor_id} " ;
            }            

        }

        $telemed_condition = " AND ({$service_doctor_mapping}.telemed_service != 'yes' OR $service_doctor_mapping.telemed_service IS NULL )  ";
        if(isKiviCareTelemedActive() || isKiviCareGoogleMeetActive()){
            // if(!$request_from_new_appointment_widget_and_service_first){
            //     $all_doctor = collect(get_users(['role' => $this->getDoctorRole(),'fields' => ['ID']]))->filter(function ($v){
            //         return !kcDoctorTelemedServiceEnable($v->ID);
            //     })->pluck('ID');
                
            //     $all_doctor = $all_doctor->isNotEmpty() ? $all_doctor->toArray() : [-1];
            //     $all_doctor= implode(',',$all_doctor);
                
            //     if(!empty($all_doctor)){
            //         $telemed_condition = " AND {$service_doctor_mapping}.id NOT IN (SELECT id  FROM {$service_doctor_mapping} WHERE doctor_id IN ({$all_doctor}) AND telemed_service = 'yes') ";
            //     }
            // }else{
            //     $telemed_condition = "  ";
            // }
            $telemed_condition = "  ";
        }

        // get only active service list in appointment book
        $active_services = $request_from_service_module ? " " : " AND {$service_table}.status = '1' AND {$service_doctor_mapping}.status = '1' ";
        $full_service_name = " {$service_table}.name,{$service_table}.type,{$service_doctor_mapping}.doctor_id,{$service_doctor_mapping}.clinic_id ";
        if($request_from_new_appointment_widget){
            if($request_from_new_appointment_widget_and_service_first ||
                (isset($request_data['doctor_id']) && !in_array($request_data['doctor_id'],[0,'0'])
                    && (empty($request_data['doctor_id']) || in_array($request_data['clinic_id'],[0,'0'])))){
                $full_service_name = " {$service_table}.name,{$service_table}.type " ;
            }
        }

        if($request_from_new_appointment_widget && isKiviCareProActive()){
            //get clinic id wise service list
            if(!empty($request_data['clinic_id']) && !in_array($request_data['clinic_id'],['0',0]) ){
                $request_data['clinic_id'] = (int)$request_data['clinic_id'];
                $clinic_service_condition = " AND {$service_doctor_mapping}.clinic_id = {$request_data['clinic_id']} ";
            }
        }else{
            switch($current_login_user_role){
                case $this->getDoctorRole():
                case 'administrator':
                    if($request_from_service_module){
                        $request_data['clinic_id'] = '';
                    }else{
                        $request_data['clinic_id'] = !empty($request_data['clinic_id']) ? $request_data['clinic_id'] : kcGetDefaultClinicId();
                    }
                    break;
                case $this->getClinicAdminRole():
                    $request_data['clinic_id'] = kcGetClinicIdOfClinicAdmin();
                    break;
                case $this->getReceptionistRole():
                    $request_data['clinic_id'] = kcGetClinicIdOfReceptionist();
                    break;
                case $this->getPatientRole():
                    $request_data['clinic_id'] = !empty($request_data['clinic_id']) ? $request_data['clinic_id'] : kcGetDefaultClinicId();
                    break;  
            }
            if(!empty($request_data['clinic_id'])){
                $request_data['clinic_id'] = (int)$request_data['clinic_id'];
                $clinic_service_condition = " AND {$service_doctor_mapping}.clinic_id = {$request_data['clinic_id']} ";
            }
        }

        $preselected_service_condition = ' ';
        if(!empty($request_data['preselected_service'])){
            $request_data['preselected_service'] = implode(',',array_filter(array_map('absint',explode(',',$request_data['preselected_service']))));
            if(!empty($request_data['preselected_service'])){
                $preselected_service_condition = " AND {$service_doctor_mapping}.service_id IN ({$request_data['preselected_service']}) ";
            }
        }

        //query for service list
        $query = "SELECT {$service_doctor_mapping}.*,{$service_doctor_mapping}.charges as service_base_price,
                  CONCAT({$full_service_name}) AS full_service_name,
                  {$service_table}.name AS name, {$service_table}.type AS service_type, {$service_table}.created_at AS created_at,
                  {$users_table}.display_name AS doctor_name ,{$clinic_table}.name AS clinic_name
                   FROM {$service_doctor_mapping}
                  JOIN {$service_table} ON {$service_doctor_mapping}.service_id = {$service_table}.id
                  JOIN {$clinic_table} ON {$service_doctor_mapping}.clinic_id = {$clinic_table}.id
                  JOIN {$users_table} ON {$users_table}.ID = {$service_doctor_mapping}.doctor_id
                  JOIN {$clinic_doctor_mapping} ON {$clinic_doctor_mapping}.doctor_id = {$service_doctor_mapping}.doctor_id 
                  AND {$clinic_doctor_mapping}.clinic_id = {$service_doctor_mapping}.clinic_id 
                  WHERE 0 = 0 {$doctor_condition} {$clinic_condition} {$clinic_service_condition} {$active_services} {$telemed_condition} {$search_condition} {$preselected_service_condition}
                 {$orderByCondition}" ;

        $total = 0;
        if($request_from_service_module){
            $total = $this->db->get_var( "SELECT count(*)  FROM {$service_doctor_mapping}
                  JOIN {$service_table} ON {$service_doctor_mapping}.service_id = {$service_table}.id
                  JOIN {$users_table} ON {$users_table}.ID = {$service_doctor_mapping}.doctor_id
                  JOIN {$clinic_table} ON {$service_doctor_mapping}.clinic_id = {$clinic_table}.id
                  JOIN {$clinic_doctor_mapping} ON {$clinic_doctor_mapping}.doctor_id = {$service_doctor_mapping}.doctor_id 
                  AND {$clinic_doctor_mapping}.clinic_id = {$service_doctor_mapping}.clinic_id 
                  WHERE 0 = 0 {$doctor_condition} {$clinic_condition} {$clinic_service_condition} {$active_services} {$telemed_condition} {$search_condition} " );

            $query .= $paginationCondition;
        }

        $clinicCurrenySetting = kcGetClinicCurrenyPrefixAndPostfix();
        $clinic_prefix = !empty($clinicCurrenySetting['prefix']) ? $clinicCurrenySetting['prefix'] : '';
        $clinic_postfix = !empty($clinicCurrenySetting['postfix']) ? $clinicCurrenySetting['postfix'] : '';
        //get unique service (full_service_name = service_name + service_category_name + service_doctor_id)


		$services = collect($this->db->get_results( $query ))->unique('full_service_name')->map( function ( $services )use($clinic_prefix,$clinic_postfix,$request_data,$request_from_new_appointment_widget_and_service_first)  {
           $services->charges = round((float)$services->charges, 2);
           $services->clinic_name = decodeSpecificSymbols($services->clinic_name);
           $services->service_base_price = round((float)$services->service_base_price, 2);
            //service image
            $services->image = !empty($services->image) ? wp_get_attachment_url($services->image) : '';
			//service category name format
            $services->service_type = !empty( $services->service_type ) ? str_replace( '_', ' ', $services->service_type) : "";
            //check if service name is telemed
            if($services->telemed_service === 'yes'){
                //get category name of telemed service (updated category name of telemed service)
                $services->service_type = !empty($services->service_name_alias) ? str_replace("_"," ",$services->service_name_alias) :  $services->service_type;
            }

            if($request_from_new_appointment_widget_and_service_first){
                //change service charges as base service price
                $services->charges = $clinic_prefix.$services->service_base_price.$clinic_postfix;
            }else{
                if(empty($request_data['without_currency']) || (!empty($request_data['without_currency']) && $request_data['without_currency'] !== 'yes')){
                    $services->charges = $clinic_prefix.$services->charges.$clinic_postfix;
                }
            }

            return $services;
        } )->values();

        if (empty($services) || count($services) < 1 ) {
	        wp_send_json( [
				'status'  => false,
				'message' => esc_html__('No services found', 'kc-lang'),
				'data'    => []
			] );
		}else{
            $request_data['request_from_new_appointment_widget_and_service_first'] = $request_from_new_appointment_widget_and_service_first;
	        wp_send_json( [
                'status'     => true,
                'message'    => esc_html__('Service list', 'kc-lang'),
                'data'       => $services,
                'total_rows' => $request_from_service_module ? $total : count( $services ),
                'html' => $request_from_new_appointment_widget ?  $this->kcCreateServiceListHtml($services,$request_data) : ''
            ] );
        }

	}

	public function save() {

		global $wpdb;

		if ( ! kcCheckPermission( 'service_add' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

        $request_data = $this->request->getInputs();

        if (isset($request_data['name'])) {
            $request_data['name'] = html_entity_decode($request_data['name'], ENT_QUOTES, 'UTF-8');
        }
        
        $request_data['price'] = round((float)$request_data['price'], 2);
        $doctor_service_table = $this->db->prefix . 'kc_service_doctor_mapping';
        $service_table =  $this->db->prefix .'kc_services';
        $pro_plugin_active = isKiviCareProActive();
		$service = new KCService();
		$service_doctor_mapping = new KCServiceDoctorMapping();

        //current login user role
        $current_login_user_role = $this->getLoginUserRole();

        //current login user id
        $current_login_user_id = get_current_user_id();


        //service data
        $temp = [
			'name'   => $request_data['name'],
			'price'  => $request_data['price'],
			'type'   => str_replace(' ', '_', strtolower($request_data['type']['label'])),
			'status' => $request_data['status']['id'],
		];
        $telemedTemp =$temp;

        //change category name of telemed service to default
        // if($request_data['telemed_service'] === 'yes'){
        //     $temp['type'] = 'system_service';
        // }

        //get clinic_id
        $clinic_id = kcGetDefaultClinicId();
        if(isKiviCareProActive()){
	        switch ($current_login_user_role){
                case $this->getReceptionistRole():
	                $clinic_id = kcGetClinicIdOfReceptionist();
                    $request_data['clinic_id'] = [$clinic_id];
                    break;
		        case $this->getClinicAdminRole():
			        $clinic_id = kcGetClinicIdOfClinicAdmin();
                    $request_data['clinic_id'] = [$clinic_id];
			        break;
                default:
                    if(is_array($request_data['clinic_id']) && !empty($request_data['clinic_id'][0]['id'])){
                        $request_data['clinic_id'] = collect($request_data['clinic_id'])->pluck('id')->map(function($v){
                            return (int)$v;
                        })->toArray();
                    }else{
                        if(!empty($request_data['clinic_id']['id'])){
                            $clinic_id = (int)$request_data['clinic_id']['id'];
                        }
                        $request_data['clinic_id'] = [$clinic_id];
                    }
                    break;
	        }
        }

        //validation query to check same data exists
        $validationQuery = "SELECT map.id FROM {$doctor_service_table} AS map LEFT JOIN 
        {$service_table} AS ser ON ser.id=map.service_id WHERE 0=0 ";

        //service image attachment id
        $attachment_id = 0;
        if(isset($request_data['profile_image']) && !empty((int)$request_data['profile_image'])) {
            $attachment_id = $request_data['profile_image'];
        }

		if (empty($request_data['id'])) {
            //get doctor id from request data
            if($current_login_user_role === $this->getDoctorRole()){
                $doctor_id = [$current_login_user_id];
            }else{
                $doctor_id = collect($request_data['doctor_id'])->pluck('id')->map(function($v){
                    return (int)$v;
                })->toArray();
            }
            $implode_clinic_condition = '';
            if(!empty($request_data['clinic_id'])){
                //implode clinic id for query
                $implode_clinic_id = implode(',',$request_data['clinic_id']);
                $implode_clinic_condition = " clinic_id IN ({$implode_clinic_id}) AND ";
            }
            //implode doctor id for query
            $implode_doctor_id = implode(',',$doctor_id);
            //clinic wise doctor array
            $clinic_doctors = collect($this->db->get_results("SELECT GROUP_CONCAT(doctor_id) AS doctor_id, clinic_id FROM 
            {$this->db->prefix}kc_doctor_clinic_mappings WHERE $implode_clinic_condition doctor_id IN ({$implode_doctor_id}) GROUP BY clinic_id"))->keyBy('clinic_id')->map(function($v){
                return explode(',',$v->doctor_id);
            })->toArray();
            
            //check if same service exists with same doctor
            foreach($clinic_doctors as $clinic_doctor_key => $clinic_doctor_val){
                $doctor_ids = implode(',',$clinic_doctor_val);
                $validationRow = $wpdb->get_var($validationQuery." AND map.doctor_id 
                IN ({$doctor_ids}) AND map.clinic_id = $clinic_doctor_key
                 AND ser.type ='{$temp['type']}' AND ser.name='{$request_data['name']}'");
                if(!empty($validationRow)){
                    wp_send_json( [
                        'status'      => false,
                        'message'     => esc_html__('Same Service Already Exists,Please select Different category or service name', 'kc-lang'),
                        'data'        => []
                    ] );
                } 
            }

			$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
            $service_id = $wpdb->get_var("SELECT id FROM {$service_table} WHERE type='{$temp['type']}' AND name='{$temp['name']}'");
            if(empty($service_id)){
                $service_id = $service->insert( $temp );
            }

            if ($service_id) {
                foreach($clinic_doctors as $clinic_id => $clinic_doctor_val){
                    foreach ($clinic_doctor_val as $doctor){
                        $service_mapping_data = [
                            'service_id' => (int)$service_id,
                            'clinic_id'  => (int)$clinic_id,
                            'doctor_id'  => (int)$doctor,
                            'charges'    => $request_data['price'],
                            'status'     => (int)$request_data['status']['id'],
                            'image'       => $attachment_id,
                            'multiple'  =>$request_data['multiservice']['id'],
                            'telemed_service' => $request_data['telemed_service']['id'],
                            'service_name_alias' => $request_data['telemed_service']['id'] === 'yes' ? $request_data['type']['label'] :  $temp['type']
                        ];
                        
		                $service_mapping_data = apply_filters('kivicare_update_service_mapping_save_fields', $service_mapping_data, $request_data);
    
                        if($pro_plugin_active && !empty($request_data['duration'])){
                            $service_mapping_data['duration'] = (int)$request_data['duration'];
                        }
                        $service_doctor_mapping->insert($service_mapping_data);
                        // hook for service add.
                        do_action( 'kc_service_add', $service_mapping_data );
                    }   
                }
            }

			$message  = esc_html__('Service saved successfully', 'kc-lang');

		} else {
            $request_data['id'] = (int)$request_data['id'];
            if(!(new KCServiceDoctorMapping())->serviceUserPermission($request_data['id'])){
                wp_send_json(kcUnauthorizeAccessResponse(403));
            }

            $clinic_id = isset($request_data['clinic_id'][0]) && !empty($request_data['clinic_id'][0])
                            ? (int)$request_data['clinic_id'][0]
                            : (isset($request_data['clinic_id']['id']) && !empty($request_data['clinic_id']['id'])
                                ? (int)$request_data['clinic_id']['id']
                                : kcGetDefaultClinicId());

            $doctor_id = $current_login_user_role === $this->getDoctorRole() ? $current_login_user_id : (int)$request_data['doctor_id']['id'];
        
            //check if same service exists with same doctor
            $validationRow = $wpdb->get_var($validationQuery." AND map.doctor_id = {$doctor_id}
             AND map.clinic_id = {$clinic_id} AND ser.type ='{$temp['type']}' 
            AND ser.name='{$request_data['name']}' AND map.id != {$request_data['id']} ");
            if(!empty($validationRow)){
                wp_send_json( [
                    'status'      => false,
                    'message'     => esc_html__('Same Service Already Exists,Please select Different category or service name', 'kc-lang'),
                    'data'        => []
                ] );
            }

            $service_id = $wpdb->get_var("SELECT id FROM {$service_table} WHERE type='{$temp['type']}' AND name='{$temp['name']}'");
            if(empty($service_id)){
                $temp['created_at'] = current_time( 'Y-m-d H:i:s' );
                $request_data['service_id'] = $service->insert( $temp );
            }else{
                $request_data['service_id'] = (int)$request_data['service_id'];
                $service->update([
                    'name' => $request_data['name']
                ], array('id' => $request_data['service_id']));
            }

			$service_mapping_update_data  = [
				'service_id' => $request_data['service_id'],
                'clinic_id' => $clinic_id,
				'doctor_id' => $doctor_id,
				'charges'    => $request_data['price'],
                'status'    => (int)$request_data['status']['id'],
                'multiple'  =>$request_data['multiservice']['id'],
                'telemed_service' => $request_data['telemed_service']['id']
			];

            $service_mapping_update_data = apply_filters('kivicare_update_service_mapping_save_fields', $service_mapping_update_data, $request_data);
    
            if($pro_plugin_active && !empty($request_data['duration'])){
                $service_mapping_update_data['duration'] = (int)$request_data['duration'];
            }
            if(!empty($attachment_id)){
                $service_mapping_update_data['image'] = $attachment_id;
            }

            if($request_data['telemed_service']['id'] === 'yes'){
                $service_mapping_update_data['service_name_alias'] = $telemedTemp['type'];
            }

            $old_service_id_before_update = $this->db->get_var("SELECT service_id FROM {$doctor_service_table} WHERE id={$request_data['id']}");

            if($request_data['service_id'] !== (int)$old_service_id_before_update){
                (new KCAppointmentServiceMapping())->update(['service_id' => $request_data['service_id']],['service_id' => (int)$old_service_id_before_update]);
            }

            $service_doctor_mapping->update($service_mapping_update_data, array('id' => $request_data['id']));

            $product_id = $this->getProductIdOfService($request_data['id']);
            if(!empty($product_id)  &&  get_post_status( $product_id )){
                update_post_meta($product_id,'_price', $request_data['price']);
                update_post_meta($product_id,'_sale_price', $request_data['price']);
                if(!empty($attachment_id)){
                    update_post_meta( $product_id, '_thumbnail_id', $attachment_id );
                }

                $my_post = array(
                    'ID'           => $product_id,
                    'post_title'   => $request_data['name'],
                );
                wp_update_post( $my_post );
            }

			$service_mapping_update_data['id'] = $request_data['id'];

			do_action( 'kc_service_update', $service_mapping_update_data);

            $message = esc_html__('Service updated successfully', 'kc-lang');

		}

		wp_send_json( [
			'status'  => true,
			'message' => $message
		] );

	}

	public function edit() {

		
		if ( ! kcCheckPermission( 'service_edit' ) || ! kcCheckPermission( 'service_view' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}

			$edit_id = (int)$request_data['id'];

			if(!(new KCServiceDoctorMapping())->serviceUserPermission($edit_id)){
				wp_send_json(kcUnauthorizeAccessResponse(403));
			}
			$service_table     = $this->db->prefix . 'kc_services';
			$service_doctor_mapping  = $this->db->prefix . 'kc_service_doctor_mapping' ;
			$users_table       = $this->db->base_prefix . 'users';
			$clinic_table       = $this->db->prefix . 'kc_clinics';

			$query = " SELECT {$service_doctor_mapping}.id AS mapping_id, {$service_table}.id AS service_id, 
                              {$service_doctor_mapping}.*, {$service_table}.* , {$users_table}.display_name AS doctor_name, 
                              {$service_doctor_mapping}.charges AS doctor_charges, 
                              {$service_doctor_mapping}.status AS mapping_status,
                              {$clinic_table}.name AS clinic_name
                       FROM  {$service_doctor_mapping} 
					   JOIN  {$users_table} ON {$users_table}.ID = {$service_doctor_mapping}.doctor_id
                       JOIN  {$clinic_table} ON {$clinic_table}.id = {$service_doctor_mapping}.clinic_id
					   JOIN  {$service_table} ON {$service_table}.id = {$service_doctor_mapping}.service_id  
					   WHERE {$service_doctor_mapping}.id = {$edit_id} ";


			$service = $this->db->get_row( $query );

			if ( count( (array)$service ) ) {

                $status =  new \stdClass();
				$status->id =  0 ;
				$status->label = 'Inactive' ;
				
				if((int) $service->mapping_status === 1) {
					$status->id = 1 ;
					$status->label = 'Active' ;
				}

				$temp = [
					'id'     => $service->mapping_id,
					'service_id' => $service->service_id,
					'name'   => $service->name,
					'price'  => round((float)$service->doctor_charges, 2),
                    'doctor_id' =>  [
						'id' 	=>  $service->doctor_id,
						'label' =>  $service->doctor_name
					],
					'clinic_id' =>  [
						'id' 	=>  $service->clinic_id,
						'label' =>  decodeSpecificSymbols( $service->clinic_name)
					],
                    // 'duration'=> !empty($service->duration) ? $service->duration : '',
					'type'   => [
						'id'    => $service->type,
						'label' => str_replace('_'," ",$service->type)
					],
                    'telemed_service' => $service->telemed_service,
					'status' => $status,
                    'image' => !empty($service->image) ? wp_get_attachment_url($service->image) : ''
				];

                if(!empty($service->multiple)){
                    $temp['multiservice'] = [
                        'id' =>  strtolower($service->multiple),
                        'label' =>  $service->multiple
                    ];
                }else{
                    $temp['multiservice'] = [
                        'id' =>  'yes' ,
                        'label' =>  __("yes","kc-lang")
                    ];
                }
                if($service->telemed_service === 'yes'){
                    if(!empty($service->service_name_alias)){
                        $temp['type'] = [
                            'id' =>  str_replace(" ","_",strtolower($service->service_name_alias)) ,
                            'label' =>  $service->service_name_alias
                        ];
                    }else{
                        $temp['type'] = [
                            'id' =>  $service->type ,
                            'label' =>  str_replace('_'," ",$service->type)
                        ];
                    }
                }

                $hours = floor($service->duration / 60);
                $minutes = (int)$service->duration % 60;
                $temp['duration'] = sprintf("%02d:%02d", $hours, $minutes);
				$temp = apply_filters('kivicare_update_service_edit_fields', $temp, $service );
				wp_send_json( [
					'status'  => true,
					'message' => esc_html__('Service data', 'kc-lang'),
					'data'    => $temp
				] );

			} else {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}


		} catch ( Exception $e ) {
			$code    = $e->getCode();
			$message = $e->getMessage();
			header( "Status: $code $message" );
			wp_send_json( [
				'status'  => false,
				'message' => $e->getMessage()
			] );
		}
	}

	public function delete() {

		if ( ! kcCheckPermission( 'service_delete' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
			}

            $service_doctor_mapping = new KCServiceDoctorMapping();

            $request_data['id'] = (int)$request_data['id'];

			if(!(new KCServiceDoctorMapping())->serviceUserPermission($request_data['id'])){
				wp_send_json(kcUnauthorizeAccessResponse(403));
			}
            $product_id = $this->getProductIdOfService($request_data['id']);
            if(!empty($product_id) && get_post_status( $product_id )){
				do_action( 'kc_woocoomerce_service_delete', $product_id );
                wp_delete_post($product_id);
            }

			$id = $request_data['id'];
			$results = $service_doctor_mapping->delete( [ 'id' => $id ] );

			if ( $results ) {
				do_action( 'kc_service_delete', $id);
				wp_send_json( [
					'status'  => true,
					'message' => esc_html__('Service has been deleted successfully', 'kc-lang'),
				] );
			} else {
				wp_send_json(kcThrowExceptionResponse( esc_html__('Failed to delete Service', 'kc-lang'), 400 ));
			}


		} catch ( Exception $e ) {

			$code    = $e->getCode();
			$message = $e->getMessage();

			header( "Status: $code $message" );

			wp_send_json( [
				'status'  => false,
				'message' => $message
			] );
		}
	}

//	public function clinicService () {
//	    $table = $this->db->prefix  . 'kc_services' ;
//        $query = "SELECT `id`, `type`, `name`, `price` FROM {$table} " ;
//        $services = $this->db->get_results( $query, OBJECT );
//        wp_send_json([
//            'status' => true,
//            'message' => esc_html__('Clinic service list', 'kc-lang'),
//            'data' => $services
//        ]);
//    }

	public function getProductIdOfService($id){
        $id = (int)$id;
		$product_id = '';
		$appointments_service_table =  $this->db->prefix . 'kc_service_doctor_mapping';
		$data = $this->db->get_var('select extra from '.$appointments_service_table.' where id='.$id);
        if($data != null){
			$data = json_decode($data);
            $product_id = $data->product_id;
		}	
		return $product_id;
	}

    public function kcCreateServiceListHtml($services,$request_data){
        $services = $services->sortBy('service_type')->groupBy('service_type');
        $showServicetImageStatus = kcGetSingleWidgetSetting('showServiceImage');
        $showServicetypeStatus = kcGetSingleWidgetSetting('showServicetype');
        $showServicePriceStatus = kcGetSingleWidgetSetting('showServicePrice');
        $showServiceDurationStatus = kcGetSingleWidgetSetting('showServiceDuration');
        $total_service_category = count($services);
        ob_start();
        foreach( $services as $key => $main_services){
            ?>
            <div class="d-flex flex-column gap-1 pt-2">
                <?php if($showServicetypeStatus){
                    ?>
                    <h5 class="iq-color-secondary iq-letter-spacing-1 pl-1"><?php echo esc_html(ucwords($key)); ?></h5>
                    <?php
                } ?>
                <div class="text-center iq-category-list" >
                    <?php
                    $total_service = count($main_services);
                    foreach ($main_services as $service) {
                        $singleServiceClass = '';
                        if(!empty($service->multiple) && $service->multiple == 'no'){
                            $singleServiceClass = ' selected-service-single';
                        }
                        $prechecked = ' ';
                        if($total_service == 1 && $total_service_category == 1){
                            $singleServiceClass = ' selected-service-single';
                            $prechecked = ' checked ';
                        }
                        if(!empty($request_data['request_from_new_appointment_widget_and_service_first'])){
	                        $singleServiceClass = ' selected-service-single';
                        }
                        $image = !empty($service->image) ? $service->image : KIVI_CARE_DIR_URI .'assets/images/kc-demo-img.png' ;
                        ?>
                        <div class="iq-client-widget">
                            <input type="checkbox" <?php echo esc_html($prechecked); ?> class="card-checkbox selected-service <?php echo esc_html($singleServiceClass);?>" name="card_main"
                                   id="service_<?php echo esc_html($service->id); ?>"
                                   value="<?php echo esc_html($service->id); ?>"
                                   service_id="<?php echo esc_html($service->service_id); ?>"
                                   service_name="<?php echo esc_html($service->name); ?>"
                                   service_price="<?php echo esc_html($service->charges); ?>"
                                   doctor_id="<?php echo esc_html($service->doctor_id); ?>"
                                   clinic_id="<?php echo esc_html($service->clinic_id); ?>"
                                   status="<?php echo esc_html($service->status); ?>"
                                   created_at="<?php echo esc_html($service->created_at); ?>"
                                   doctor_name="<?php echo esc_html($service->doctor_name); ?>"
                                   service_type="<?php echo esc_html($service->service_type); ?>"
                                   multipleService="<?php echo esc_html(!empty($service->multiple) && $service->multiple == 'no' ? 'no' : 'yes')?>"
                                   telemed_service="<?php echo esc_html(!empty($service->telemed_service) ? $service->telemed_service : 'no')?>" >
                            <label class="btn-border01 service-content" for="service_<?php echo esc_html($service->id); ?>">
                                <div class="iq-card iq-card-border iq-fancy-design service-content gap-1 kc-service-card">
                                <div class="iq-top-left-ribbon-service" style="display:<?php echo esc_html($service->telemed_service === 'yes' ? 'block' : 'none'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" viewBox="0 0 20 20" fill="none">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M13.5807 12.9484C13.6481 14.4752 12.416 15.7662 10.8288 15.8311C10.7119 15.836 5.01274 15.8245 5.01274 15.8245C3.43328 15.9444 2.05094 14.8094 1.92636 13.2884C1.91697 13.1751 1.91953 7.06 1.91953 7.06C1.84956 5.53163 3.08002 4.23733 4.66801 4.16998C4.78661 4.16424 10.4781 4.17491 10.4781 4.17491C12.0653 4.05665 13.4519 5.19984 13.5747 6.72821C13.5833 6.83826 13.5807 12.9484 13.5807 12.9484Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path d="M13.5834 8.31621L16.3275 6.07037C17.0075 5.51371 18.0275 5.99871 18.0267 6.87621L18.0167 13.0004C18.0159 13.8779 16.995 14.3587 16.3167 13.802L13.5834 11.5562" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                </div>
                                    <?php
                                    if($showServicetImageStatus){
                                        ?>
                                        <div class="d-flex align-items-center justify-content-center">
                                            <div class="avatar-70 avatar icon-img">
                                                <img src="<?php echo esc_url($image); ?>" alt="service_image" class="avatar-70 rounded-circle"/>
                                            </div>
                                        </div>
                                    <?php } ?>
                                    <div class="d-flex flex-column">
                                        <div class="kc-service-name">
                                            <h6><?php echo esc_html($service->name); ?></h6>
                                        </div>
                                        <?php
                                        if($showServicePriceStatus){
                                            ?>
                                            <p class="iq-dentist-price">
                                                <?php
                                                if(empty($request_data['doctor_id']) || in_array($request_data['doctor_id'],[0,'0'])){
                                                    echo esc_html__("Base Price: ","kc-lang"). esc_html( $service->charges);
                                                }else{
                                                    echo esc_html( $service->charges );
                                                }
                                                ?>
                                            </p>
                                            <?php
                                        }
                                        if(isKiviCareProActive() && $showServiceDurationStatus){
                                            ?>
                                            <p class="iq-dentist-price">
                                                <?php
                                                if(empty($request_data['doctor_id']) || in_array($request_data['doctor_id'],[0,'0'])){
                                                    echo !empty($service->duration) ? esc_html__("Service Duration: ","kc-lang"). esc_html( $service->duration) : '';
                                                }else{
                                                    echo !empty($service->duration) ? esc_html( $service->duration ) . esc_html__(" min","kc-lang") : '';
                                                }
                                                ?>
                                            </p>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php
        }
        return ob_get_clean();
    }
}


