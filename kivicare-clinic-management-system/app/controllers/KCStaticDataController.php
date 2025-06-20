<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCClinic;
use App\models\KCDoctorClinicMapping;
use App\models\KCPatientClinicMapping;
use App\models\KCReceptionistClinicMapping;
use App\models\KCStaticData;
use Exception;
use WP_User;

class KCStaticDataController extends KCBase {

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

		$this->table_name = $wpdb->prefix . 'kc_static_data';

		$this->db_config = [
			'user' => DB_USER,
			'pass' => DB_PASSWORD,
			'db'   => DB_NAME,
			'host' => DB_HOST
		];

		$this->request = new KCRequest();

        parent::__construct();

	}

	public function index() {

		if ( ! kcCheckPermission( 'static_data_list' ) ) {
			wp_send_json( kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();
		$static_data_table = $this->db->prefix . 'kc_static_data';

        $paginationCondition = ' ';
        if((int)$request_data['perPage'] > 0){
            $perPage = (int)$request_data['perPage'];
            $offset = ((int)$request_data['page'] - 1) * $perPage;
            $paginationCondition = " LIMIT {$perPage} OFFSET {$offset} ";
        }

        $orderByCondition = " ORDER BY id DESC ";
        if(!empty($request_data['sort'])){
            $request_data['sort'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($request_data['sort'][0]),true));
            if(!empty($request_data['sort']['field']) && !empty($request_data['sort']['type']) && $request_data['sort']['type'] !== 'none'){
                $orderByCondition = " ORDER BY ".sanitize_sql_orderby($request_data['sort']['field'])." ".sanitize_sql_orderby(strtoupper($request_data['sort']['type']));
            }
        }
		$condition = ' WHERE 0=0 ';
        if(isset($request_data['searchTerm']) && trim($request_data['searchTerm']) !== ''){
            $request_data['searchTerm'] = esc_sql(strtolower(trim($request_data['searchTerm'])));

            // Extract status using regex
            $status = null;
            if (preg_match('/:(active|inactive)/i', $request_data['searchTerm'], $matches)) {
                $status = $matches[1]=='active'?'1':'0';
                // Remove status from search term
                $request_data['searchTerm'] = trim( preg_replace('/:(active|inactive)/i', '', $request_data['searchTerm']));
            }
        
            $condition.= " AND (`value` LIKE '%{$request_data['searchTerm']}%' 
                           OR `type` LIKE '%{$request_data['searchTerm']}%' ) ";
            if(!is_null($status)){
                $condition.= " AND `status` LIKE '{$status}'";
            }
        }else{
            if(!empty($request_data['columnFilters'])){
                $request_data['columnFilters'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($request_data['columnFilters']),true));
                foreach ($request_data['columnFilters'] as $column => $searchValue){
                    if($column !== 'status'){
                        $searchValue = !empty($searchValue) ? $searchValue : '';
                    }
                    $searchValue = esc_sql(strtolower(trim($searchValue)));
                    $column = esc_sql($column);
                    if($searchValue === ''){
                        continue;
                    }
                    $condition.= " AND `{$column}` LIKE '%{$searchValue}%' ";
                }             
            }
        }


		$total_static_data = $this->db->get_var( "SELECT count(*) AS count FROM {$static_data_table} {$condition}" );


		$static_data_query = "
			SELECT *, REPLACE(type,'_',' ') as type
			FROM  {$static_data_table}  {$condition} {$orderByCondition} {$paginationCondition}";
 
		$static_data = $this->db->get_results( $static_data_query );

        foreach ($static_data as $data) {
            $data->type = __($data->type, 'kc-lang'); 
        }

		if ( empty($static_data)  ) {
			wp_send_json( [
				'status'  => false,
				'message' => esc_html__('No services found', 'kc-lang'),
				'data'    => []
			] );
		}

		wp_send_json( [
			'status'  => true,
			'message' => __('Service list','kc-lang'),
			'data'    => $static_data,
			'total_rows' =>  $total_static_data
		] );
	}

	public function save() {


		if ( ! kcCheckPermission( 'static_data_add' ) ) {
			wp_send_json( kcUnauthorizeAccessResponse(403));
		}

        $message = '';
        $insert_id = '';
		$request_data = $this->request->getInputs();

        // Decode HTML entities 
        if (isset($request_data['label'])) {
            $request_data['label'] = html_entity_decode($request_data['label'], ENT_QUOTES, 'UTF-8');
        }

		$value = str_replace(' ', '_', strtolower($request_data['label']));

		$temp = [
			'label' => $request_data['label'],
			'type' => (isset($request_data['type']['id']) ? $request_data['type']['id'] : $request_data['type']),
			'value' => $value,
			'status' => (isset($request_data['status']['id']) ? (int)$request_data['status']['id'] :  $request_data['status'])
		];

		$check_condition = '';
		if(!empty($request_data['id'])){
			$check_condition = " AND id !=".(int)$request_data['id'];
		}
        $existItem = $this->db->get_var("SELECT id FROM {$this->db->prefix}kc_static_data WHERE `type`='{$temp['type']}' AND `value`='{$value}' {$check_condition }");

        if(!empty($existItem)){
	        wp_send_json([
                'status' => false,
                'message' => esc_html__('Listing data already exists.', 'kc-lang')
            ]);
        }

		$static_data = new KCStaticData;

		if (!isset($request_data['id'])) {
            $temp['created_at'] = current_time('Y-m-d H:i:s');
            $insert_id = $static_data->insert($temp);
            $message = esc_html__('Listing data saved successfully', 'kc-lang');
		} else {
			$static_data->update($temp, array( 'id' => (int)$request_data['id'] ));
			$message = esc_html__('Listing data updated successfully', 'kc-lang');
		}

		wp_send_json([
			'status' => true,
			'message' => $message,
            'insert_id' => $insert_id
		]);

	}

	public function edit() {

		if ( ! kcCheckPermission( 'static_data_edit' ) || ! kcCheckPermission('static_data_view') ) {
			wp_send_json( kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if (!isset($request_data['id'])) {
				wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
			}

			$id = $request_data['id'];

			$static_data = new KCStaticData;

			$results = $static_data->get_by(['id' => (int)$id], '=',true);

			$results->status = [
				'id' => 0,
				'label' => 'Inactive'
			] ;

			if( (int) $results->status === 1) {
				$results->status = [
					'id' => 1,
					'label' => 'Active'
				] ;
			}
			
			$results->type = [
				'id' => $results->type,
				'type' => str_replace('_', ' ', $results->type),
			] ;

			if ($results) {
				wp_send_json([
					'status' => true,
					'message' => esc_html__('Static data', 'kc-lang'),
					'data' => $results
				]);
			} else {
				wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
			}

		} catch (Exception $e) {

			$code    = $e->getCode();
			$message = $e->getMessage();

			header("Status: $code $message");

			wp_send_json([
				'status' => false,
				'message' => $message
			]);
		}
	}

	public function delete() {

		if ( ! kcCheckPermission( 'static_data_delete' ) ) {
			wp_send_json( kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if (!isset($request_data['id'])) {
				wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
			}

			$id = $request_data['id'];

			$static_data = new KCStaticData;

			$results = $static_data->delete(['id' => (int)$id]);

			if ($results) {
				wp_send_json([
					'status' => true,
					'tableReload' => true,
					'message' => esc_html__('Static data deleted successfully', 'kc-lang'),
				]);
			} else {
				wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
			}


		} catch (Exception $e) {

			$code    = $e->getCode();
			$message = $e->getMessage();

			header("Status: $code $message");

			wp_send_json([
				'status' => false,
				'message' => $message
			]);
		}
	}

    public function getStaticData()
    {
        $request_data = $this->request->getInputs();
		$all_allow_roles = $this->KCGetRoles();
		$all_allow_roles[] = 'administrator';
		if( ! in_array($this->getLoginUserRole(),$all_allow_roles)){
            //check if public accessible api
            if(!in_array($request_data['data_type'],['clinics']) ){
                wp_send_json(kcUnauthorizeAccessResponse(403));
            }
		}
        global $wpdb;
        $request_data = $this->request->getInputs();
        $current_user_role = $this->getLoginUserRole();
        if(isKiviCareProActive() && $request_data['data_type'] == 'clinic_list' ) {

            $table_name = $wpdb->prefix . 'kc_' . 'clinics';
            $response = apply_filters('kcpro_get_all_clinic', []);
	        wp_send_json($response);

        }else{
            $data = [
                'status' => false,
                'message' => esc_html__('Datatype not found', 'kc-lang')
            ];

            if (isset($request_data['data_type']) || isset($request_data['type'])) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'kc_' . 'static_data';
                $type = !empty($request_data['data_type']) ? $request_data['data_type'] : $request_data['type'];

                switch ($type) {
                    case "static_data":
                        $static_data_type = $request_data['static_data_type'];
                        $query = "SELECT id, label FROM $table_name WHERE type = '$static_data_type' AND status = '1' GROUP BY $table_name.`value`";
                        $results = $wpdb->get_results($query, OBJECT);
                        break;

                    case "static_data_with_label":
                        $static_data_type = $request_data['static_data_type'];
                        $query = "SELECT `value` as id, label FROM $table_name WHERE type = '$static_data_type' AND status = '1' GROUP BY $table_name.`value` ";
                        $results = collect($wpdb->get_results($query, OBJECT))->unique('id')->toArray();
                        break;

                    case "static_data_types":
                        $query = "SELECT `type` as id, REPLACE(type, '_' , ' ') AS `type` FROM $table_name WHERE status = 1 GROUP BY `type`";
                        $results = $wpdb->get_results($query, OBJECT);
                        break;

                    case "clinics":
                        $table_name = $wpdb->prefix . 'kc_' . 'clinics';
                        $condition = '';
                        $clinic_condition = ' ';
                        if(!isKiviCareProActive()){
                            $condition = ' AND id='.kcGetDefaultClinicId().' ';
                        }else{
                            if($current_user_role === $this->getReceptionistRole()) {
                                $clinic_id = kcGetClinicIdOfReceptionist();
                                $clinic_condition = " AND id={$clinic_id} ";
                            }else if($current_user_role === $this->getClinicAdminRole()) {
                                $clinic_id = kcGetClinicIdOfClinicAdmin();
                                $clinic_condition = " AND id={$clinic_id} ";
                            }
                            if(!empty($request_data['doctor_id'])){
                                $doctor_clinic = collect((new KCDoctorClinicMapping())->get_by([
                                    'doctor_id' => (int)$request_data['doctor_id']
                                ]))->pluck('clinic_id')->toArray();
                                $doctor_clinic = implode(',',$doctor_clinic);      
                                $clinic_condition = " AND id IN ({$doctor_clinic}) ";
                            }
                        }
                        $query = "SELECT `id`, `name` as `label` FROM {$table_name} WHERE `status` = '1' {$condition} {$clinic_condition}";
                        $results = $wpdb->get_results($query, OBJECT);

                        array_walk($results, function (&$result) {
                            $result->label = decodeSpecificSymbols($result->label);
                        });
                        
                        break;
                    case 'patient_clinic':
                        if($current_user_role === $this->getPatientRole() && isKiviCareProActive()){
                            $user_id = get_current_user_id();
                            $results = $wpdb->get_results("SELECT clinic.id , clinic.name AS label FROM  {$wpdb->prefix}kc_clinics AS clinic LEFT JOIN 
                                   {$wpdb->prefix}kc_patient_clinic_mappings AS pcmap ON  pcmap.clinic_id = clinic.id WHERE pcmap.patient_id={$user_id}",OBJECT);
                        }else{
                            $results = [];
                        }
                        break;
                    case "doctors":

                        $clinic_condition = ' ';
                        $table_name = $wpdb->prefix . 'kc_' . 'doctor_clinic_mappings';

                        if(isKiviCareProActive()){
                            if($current_user_role === $this->getReceptionistRole()) {
                                $clinic_id = kcGetClinicIdOfReceptionist() ;
                                $clinic_condition = " AND dcm.clinic_id = {$clinic_id} ";
                            }else if($current_user_role === $this->getClinicAdminRole()) {
                                $clinic_id = kcGetClinicIdOfClinicAdmin() ;
                                $clinic_condition = " AND dcm.clinic_id = {$clinic_id} ";
                            }
                        }

                        $query = " SELECT DISTINCT u.ID as id, u.display_name as label 
                        FROM {$wpdb->users} as u
                        INNER JOIN {$table_name} as dcm ON dcm.doctor_id = u.ID
                        WHERE u.user_status = 0 {$clinic_condition} ";

                        $doctorList = $wpdb->get_results($query, ARRAY_A);
                        $doctor_list = [];
                        if (!empty($doctorList)) {
                            foreach ($doctorList as $doctor) {
                                $doctor_list[] = [
                                    'id'    => $doctor['id'],
                                    'label' => $doctor['label']
                                ];
                            }
                        }

                        $results = $doctor_list;
                        break;

                    case "default_clinic":
                        $table_name = $wpdb->prefix  . 'kc_clinics';
                        $id = kcGetDefaultClinicId();
                        if(!empty($id)) {
                            $query = "SELECT * FROM {$table_name} WHERE  `id` = '{$id}' ";
                            $results = $wpdb->get_results($query, OBJECT);
                            // $results = $results[0];
                            if($results[0]->extra == null) {
                                $results[0]->extra->currency_prefix = '';
                                $results[0]->extra->currency_postfix = '';
                            }
                        } else {
                            $results = [];
                        }
                        break;

                    case "services_with_price":
                        $service_table = $wpdb->prefix . 'kc_' . 'services';
                        $service_doctor_mapping = $wpdb->prefix . 'kc_' . 'service_doctor_mapping';
                        $zoom_config_data = get_user_meta($request_data['doctorId'], 'zoom_config_data', true);
                        $zoom_config_data = json_decode($zoom_config_data);
                        if(isset($request_data['doctorId'])){
                            $request_data['doctorId'] = (int)$request_data['doctorId'];
                            if(!empty($zoom_config_data->enableTeleMed) && $zoom_config_data->enableTeleMed == 1){
                                $query = "SELECT {$service_table}.id ,{$service_doctor_mapping}.charges AS price,{$service_table}.name AS label FROM  {$service_table} 
                                JOIN {$service_doctor_mapping} ON  {$service_table}.id = {$service_doctor_mapping}.service_id 
                                WHERE {$service_table}.status = 1 AND {$service_doctor_mapping}.doctor_id =".$request_data['doctorId'];
                            }else{
                                $query = "SELECT {$service_table}.id ,{$service_doctor_mapping}.charges AS price,{$service_table}.name AS label FROM  {$service_table} 
                                JOIN {$service_doctor_mapping} ON  {$service_table}.id = {$service_doctor_mapping}.service_id 
                                WHERE {$service_table}.status = 1 AND {$service_doctor_mapping}.doctor_id =".$request_data['doctorId']." AND {$service_table}.type != 'system_service' ";
                            }

                        }else{
                            $query = "SELECT `id`, `price`, `name` as `label` FROM {$service_table} WHERE status = 1 ";
                        }
                        $results = $wpdb->get_results($query, OBJECT);
                        break;

                    case "prescriptions":
                        $table_name = $wpdb->prefix . 'kc_' . 'prescription';
                        $query = "SELECT `name` as `id`, `name` as `label` FROM {$table_name}";
                        $results = collect($wpdb->get_results($query, OBJECT))->unique('id')->toArray();
                        $results2 = collect($wpdb->get_results("SELECT `label` as id, label FROM {$wpdb->prefix}kc_static_data WHERE status = 1 AND `type`='prescription_medicine' GROUP BY `type`"))->unique('id')->toArray();
                        $results = collect(array_merge($results,$results2))->unique('label');
                        break;

                    case "email_template_type":
                        $query = "SELECT `id`, `value`, `label` FROM {$table_name} WHERE `status` = '1' AND `type` = 'email_template' ";
                        $results = $wpdb->get_results($query, ARRAY_A);
                        break;
                    case "email_template_key":
                        $results = ['{{user_name}}', '{{user_email}}', '{{user_contact}}'];
                        break;
                    case "get_users_by_clinic":
                        if(empty($request_data['clinic_id'])) {
                            $clinic_id = kcGetDefaultClinicId();
                        } else {
                            if(is_array($request_data['clinic_id'])){
                                $clinic_id = implode(",",array_map('absint',$request_data['clinic_id']));
                            }else{
                                $clinic_id = (int)$request_data['clinic_id'] ;
                            }
                        }
                        $table_name = $wpdb->prefix . 'kc_doctor_clinic_mappings';
                        $query = "SELECT doctor_id FROM {$table_name} WHERE clinic_id IN ({$clinic_id}) ";
                        $doctor_ids = collect($wpdb->get_results($query, OBJECT))->pluck('doctor_id')->toArray();
                        $results = [];
                        if (count($doctor_ids)) {
                            $users_table = $wpdb->base_prefix . 'users';
                            $usermeta_table = $wpdb->base_prefix . 'usermeta';

                            if(isset($request_data['telemed_service']) && $request_data['telemed_service'] === 'yes'){
                                $new_query = "SELECT user.`ID` as `id`, user.`display_name` as `label`
                                FROM {$users_table} user
                                INNER JOIN {$usermeta_table} usermeta ON user.`ID` = usermeta.`user_id`
                                WHERE user.`ID` IN (" . implode(',', $doctor_ids) . ") 
                                AND user.`user_status` = '0'
                                AND (((usermeta.`meta_key` LIKE 'kiviCare_zoom_telemed_connect' OR usermeta.`meta_key` LIKE 'kiviCare_google_meet_connect')
                                AND usermeta.`meta_value` LIKE 'on') OR (usermeta.`meta_key` = 'zoom_server_to_server_oauth_config_data' 
                                AND JSON_EXTRACT(usermeta.`meta_value`, '$.enableServerToServerOauthconfig') = 'true'))";                          
                            }
                            else{
                                $new_query = "SELECT `ID` as `id`, `display_name` as `label`  FROM {$users_table} WHERE `ID` IN (" . implode(',', $doctor_ids) . ") AND `user_status` = '0'";
                            }
                            $results = $wpdb->get_results($new_query, OBJECT);
                        }
                        break;
                    case "clinic_doctors":
                        $telemed_plugins_active = isKiviCareTelemedActive() || isKiviCareGoogleMeetActive(); 
                        $table_name = $wpdb->prefix . 'kc_' . 'doctor_clinic_mappings';

                        if(isKiviCareProActive()){
                            if($current_user_role === $this->getReceptionistRole()) {
                                $clinic_id = kcGetClinicIdOfReceptionist() ;
                            }else if($current_user_role === $this->getClinicAdminRole()) {
                                $clinic_id = kcGetClinicIdOfClinicAdmin() ;
                            }else if($current_user_role === $this->getPatientRole()){
                                $clinic_id = kcGetClinicIdOfPatient();
                            }
                            else{
                                $clinic_id = (!empty($request_data['clinic_id'])? (int)$request_data['clinic_id'] : 1 );
                            }
                            if(is_array($clinic_id)){
                                $clinic_id = !empty($clinic_id['id']) ? $clinic_id['id'] : 1;
                            }
                        }else{
                            $clinic_id = kcGetDefaultClinicId();
                        }
                        $clinic_id = (int)$clinic_id;
                        $prefix_postfix =kcGetClinicCurrenyPrefixAndPostfix();
                        $data['prefix'] = !empty($prefix_postfix['prefix'])? $prefix_postfix['prefix'] : '';
                        $data['postfix'] = !empty($prefix_postfix['postfix'])  ? $prefix_postfix['postfix'] : '';

                        $doctor_session_data = [];
                        if (isset($request_data['module_type']) && $request_data['module_type'] === 'appointment') {
                            $clinic_session_table = $wpdb->prefix. 'kc_' . 'clinic_sessions';
                            $doctor_sessions_query = "SELECT * FROM {$clinic_session_table} WHERE `clinic_id` = '{$clinic_id}' ";
                            $doctor_session_data = collect($wpdb->get_results($doctor_sessions_query, ARRAY_A))->pluck('doctor_id')->unique();
                        }
                        if (!current_user_can('administrator')) {
                            $query = "SELECT * FROM {$table_name} WHERE `clinic_id` = '{$clinic_id}' ";
                        }else{
                            if(!empty($request_data['clinic_id']) && !empty($request_data['module_type'])
                                && $request_data['module_type'] == 'appointment_filter'){
                                $query = "SELECT * FROM {$table_name} WHERE `clinic_id` = '{$clinic_id}' ";
                            }else if(!empty($request_data['clinic_id']) && !empty($request_data['module_type'])
                            && $request_data['module_type'] == 'service_module'){
                                $query = "SELECT * FROM {$table_name} WHERE `clinic_id` = '{$clinic_id}' ";
                            }else{
                                $query = "SELECT * FROM {$table_name}";
                            }
                        }
                        $clinic_data = $wpdb->get_results($query, OBJECT);
                        $results = [];
                        $doctor_ids = [];

                        if (count($clinic_data)) {
                            foreach ($clinic_data as $clinic_map_data) {
                                if (isset($clinic_map_data->doctor_id)) {
                                    if(isset($request_data['module_type']) && $request_data['module_type'] === 'appointment') {
                                        $doctor_session_data = collect($doctor_session_data)->toArray();
                                        if(in_array($clinic_map_data->doctor_id, $doctor_session_data)) {
                                            $doctor_ids[] = $clinic_map_data->doctor_id;
                                        }
                                    } else {
                                        $doctor_ids[] = $clinic_map_data->doctor_id;
                                    }
                                }
                            }

                            if (count($doctor_ids)) {

                                $users_table = $wpdb->base_prefix . 'users';
                                $new_query = "SELECT `ID` as `id` , `display_name` as `label`  FROM {$users_table} WHERE `ID` IN (" . implode(',', $doctor_ids) . ") AND `user_status` = '0'";
                                $results = $wpdb->get_results($new_query, OBJECT);
                                if (count($results)) {
                                    foreach ($results as $result) {
                                        $user_data = get_user_meta($result->id, 'basic_data', true);
                                        if ($user_data) {
                                            $user_data = json_decode($user_data);
                                            $result->timeSlot = isset($user_data->time_slot) ? $user_data->time_slot : "";
                                            $specialties = collect($user_data->specialties)->pluck('label')->toArray();
                                            $result->label .= !empty($specialties) ? " (". implode( ',',$specialties).")" : '';
                                        }
                                        $result->enableTeleMed = $telemed_plugins_active ? kcDoctorTelemedServiceEnable($result->id) : false;
                                    }
                                }
                            }
                        }

                        break;

                    case "users":
                        $results = [];
                        $users = get_users([
                            'role' => $request_data['user_type'],
                            'user_status' => '0',
                            'fields' => ['ID','display_name']
                        ]);

                        if(isKiviCareProActive()){
                            if($current_user_role  !== $this->getPatientRole()){
                                if($current_user_role === $this->getClinicAdminRole()){
                                    $clinic_id = kcGetClinicIdOfClinicAdmin() ;
                                }else if($current_user_role === $this->getReceptionistRole()){
                                    $clinic_id = kcGetClinicIdOfReceptionist() ;
                                }else if($current_user_role === $this->getDoctorRole()){
                                    $clinic_id = collect($this->db->get_results("SELECT clinic_id FROM {$this->db->prefix}kc_doctor_clinic_mappings WHERE doctor_id=".get_current_user_id()))->pluck('clinic_id')->implode(',');
                                }
                                if(in_array($current_user_role, ['administrator',$this->getDoctorRole()]) && !empty($request_data['request_clinic_id'])){
                                    $clinic_id = $request_data['request_clinic_id'];
                                }

                                if(!empty($clinic_id)){
                                    $patient_clinic = $this->db->prefix.'kc_patient_clinic_mappings';
                                    if($current_user_role === $this->getDoctorRole()){
                                        $result = collect($wpdb->get_results("select patient_id from ".$patient_clinic .' where clinic_id  IN ('.$clinic_id.')'))->unique('patient_id')->pluck('patient_id')->toArray();
                                    }else{
                                        $clinic_id = (int)$clinic_id;
                                        $result = collect($wpdb->get_results("select patient_id from ".$patient_clinic .' where clinic_id='.$clinic_id))->unique('patient_id')->pluck('patient_id')->toArray();
                                    }
                                    if(count($result) === 0){
                                        $users = [];
                                    }else{
                                        $users = collect($users)->whereIn('ID',$result)->toArray();
                                    }
                                }
                            }
                        }

                        if ($current_user_role === $this->getDoctorRole()) {
                            $result = kcDoctorPatientList();
                            if(count($result) === 0){
                                $users = [];
                            }else{
                                $users = collect($users)->whereIn('ID',$result)->toArray();
                            }
                        }
                        if (count($users)) {
                            foreach ($users as $key => $user) {
                                $namePostFix = '';
                                if($request_data['user_type'] === $this->getPatientRole() && kcPatientUniqueIdEnable('status')){
                                    $uniqueID = get_user_meta( $user->ID, 'patient_unique_id',true);
                                    $namePostFix = '('.(!empty($uniqueID) ? $uniqueID : '-').')';
                                }
                                $results[$key]['id'] = $user->ID;
                                $results[$key]['label'] = $user->display_name.$namePostFix;
                                if($request_data['user_type'] === $this->getDoctorRole()){
                                    $user_data = get_user_meta($user->ID, 'basic_data', true);
                                    if ($user_data) {
                                        $user_data = json_decode($user_data);
                                        $results[$key]['timeSlot'] = isset($user_data->time_slot) ? $user_data->time_slot : "";
                                    }
                                }
                            }
                        }

                        $results = array_values($results);

                        break;

                    default:
                        $results = [];
                }

                $data['status'] = true;
                $data['message'] = esc_html__('Datatype found', 'kc-lang');
                $data['data'] = $results;
            }
            wp_send_json($data);
        }

    }
}

