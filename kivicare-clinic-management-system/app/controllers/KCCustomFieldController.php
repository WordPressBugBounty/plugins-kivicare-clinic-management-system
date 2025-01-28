<?php

namespace App\Controllers;


use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCCustomField;
use App\models\KCCustomFieldData;
use Exception;

class KCCustomFieldController extends KCBase {

	public $db;

	/**
	 * @var KCRequest
	 */
	private $request;

	public function __construct() {

		global $wpdb;

		$this->db = $wpdb;

		$this->request = new KCRequest();

        parent::__construct();
	}

	public function index() {

		if ( ! kcCheckPermission( 'custom_field_list' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
        $request_data = $this->request->getInputs();

        $custom_field_table = $this->db->prefix.'kc_custom_fields';

        $conditions = 'WHERE 0=0  ';
        if(!isKiviCareProActive()){
            $conditions.= " AND module_type !='appointment_module'";
        }
        $pagination = ' ';
        if((int)$request_data['perPage'] > 0){
            $perPage = (int)$request_data['perPage'];
            $offset = ((int)$request_data['page'] - 1) * $perPage;
            $pagination = " LIMIT {$perPage} OFFSET {$offset} ";
        }
        $orderByCondition = " ORDER BY id DESC ";
        if(!empty($request_data['sort'])){
            $request_data['sort'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($request_data['sort'][0]),true));
            if(!empty($request_data['sort']['field']) && !empty($request_data['sort']['type']) && $request_data['sort']['type'] !== 'none'){
                $orderByCondition = " ORDER BY ".sanitize_sql_orderby($request_data['sort']['field'])." ".sanitize_sql_orderby(strtoupper($request_data['sort']['type']));
            }
        }
        if(isset($request_data['searchTerm']) && trim($request_data['searchTerm']) !== ''){
            $request_data['searchTerm'] = esc_sql(strtolower(trim($request_data['searchTerm'])));

            // Extract status using regex
            $status = null;
            if (preg_match('/:(active|inactive)/i', $request_data['searchTerm'], $matches)) {
                $status = $matches[1]=='active'?'1':'0';
                // Remove status from search term
                $request_data['searchTerm'] = trim( preg_replace('/:(active|inactive)/i', '', $request_data['searchTerm']));
            }
            

            $conditions.= " AND (id LIKE '%{$request_data['searchTerm']}%' 
                           OR module_type LIKE '%{$request_data['searchTerm']}%' 
                           OR LOWER(JSON_EXTRACT(`fields`,'$.name')) LIKE '%{$request_data['searchTerm']}%' 
                           OR LOWER(JSON_EXTRACT(`fields`,'$.type')) LIKE '%{$request_data['searchTerm']}%'  ) ";
            if(!is_null($status)){
                $conditions.= "AND status LIKE '{$status}'";
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
                    if($column === 'fields'){
                        $column = "JSON_EXTRACT(`fields`,'$.label')";
                    }
                    if($column === 'input_type'){
                        $column = "JSON_EXTRACT(`fields`,'$.type')";
                    }
                    $conditions.= " AND {$column} LIKE '%{$searchValue}%' ";
                }
            }
        }


        $total_custom_field_data = $this->db->get_var( "SELECT count(*) AS count FROM {$custom_field_table} {$conditions}" );

		$custom_field = $this->db->get_results("SELECT * FROM {$custom_field_table} {$conditions} {$orderByCondition} {$pagination}");

		if ( empty($custom_field)) {
			wp_send_json( [
				'status'  => false,
				'message' => esc_html__('No custom fields found', 'kc-lang'),
				'data'    => []
			] );
		}

		wp_send_json( [
			'status'  => true,
			'message' => esc_html__('Custom fields records', 'kc-lang'),
			'data'    => $custom_field,
            'total' => $total_custom_field_data
		] );
	}

	public function save() {

		if ( ! kcCheckPermission( 'custom_field_add' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
        $request_data = $this->request->getInputs();
        $custome_field = new KCCustomField();
        $fields = [];
        $user_id = get_current_user_id();
        if ( count( $request_data['fields'] ) ) {
            foreach ( $request_data['fields'] as $key => $field ) {
                $field['name'] = $field['label'];
                $field['type'] =  $field['type']['id'];
                $field['status'] =  $field['status']['id'];
                $fields[] = $field;
            }
        }  
        if($this->getLoginUserRole() == $this->getDoctorRole() && $request_data['module_type']['id'] == 'patient_module'){
            $request_data['module_id'] = $user_id;
        }else if($this->getLoginUserRole() == $this->getDoctorRole() && $request_data['module_type']['id'] == 'patient_encounter_module'){
            $request_data['module_id'] = $user_id;
        }
		else if($this->getLoginUserRole() == $this->getDoctorRole() && $request_data['module_type']['id'] == 'appointment_module'){
            $request_data['module_id'] = $user_id;
        }
        // else{
        //     $request_data['module_id'] = isset($request_data['module_id']['id']) ? $request_data['module_id']['id'] :0;
        // }

        $status = isset($field['status']) ? $field['status'] : 1 ;

        if ( ! isset( $request_data['id'] )){

            if(empty($request_data['module_id'])){
                $module_id = 0;
                $this->savecustomField($request_data['module_type']['id'], $module_id, json_encode( $fields[0]), $status);
            }
            else{
                foreach ($request_data['module_id'] as $doctor){
                    $module_id = $doctor['id'];
                    $this->savecustomField($request_data['module_type']['id'], $module_id, json_encode( $fields[0]), $status);
                }
            }
        
            $message = esc_html__('Custom fields has been saved successfully', 'kc-lang') ;
        
        } else {
        
            $temp = [
                'module_type' => $request_data['module_type']['id'],
                'module_id' => !empty($request_data['module_id']['id']) ? (int)$request_data['module_id']['id'] : 0,
                'fields'      => json_encode( $fields[0] ),
                'status'      => $status
            ];
        
            $message = esc_html__('Custom fields has been updated successfully', 'kc-lang') ;
            $custome_field->update( $temp, array( 'id' => (int)$request_data['id'] ) );
        }

		wp_send_json( [
            'status'  => true,
            'message' => $message
        ] );


    }

    public function savecustomField($module_type,$module_id, $fields, $status){
        //save custom field
        $custome_field = new KCCustomField();

        $temp = [
            'module_type' => $module_type,
            'module_id'   => $module_id,
            'fields'      => $fields,
            'status'      => $status
        ];

        $temp['created_at'] = current_time( 'Y-m-d H:i:s' );
        $custome_field->insert( $temp );
        
    }

	public function edit() {
		if ( ! kcCheckPermission( 'custom_field_edit' ) || !kcCheckPermission('custom_field_view') ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

        if (!isset($request_data['id'])) {
	        wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
        }

        $id = (int)$request_data['id'];

        $custom_field = (new KCCustomField())->get_by(['id' => $id], '=', true);

		if(empty($custom_field)){
			wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
		}

        if (!empty($custom_field->module_id)) {
            $user_data = get_userdata((int)$custom_field->module_id);
            if (isset($user_data->data)) {
                $custom_field->module_id = [
                    'id' => $user_data->data->ID,
                    'label' => $user_data->data->display_name
                ];
            }
        } else {
            $custom_field->module_id = '';
        }

        $fields = json_decode($custom_field->fields);

        $fields->type = [
            'id' => $fields->type,
            'label' => ucfirst(str_replace("_", " ", $fields->type))
        ];

        $fields->status = [
            'id' => in_array($custom_field->status,[1,'1']) ? 1 : 0 ,
            'label' => in_array($custom_field->status,[1,'1']) ? esc_html__('Active','kc-lang') : esc_html__('Inactive','kc-lang')
        ];

		$temp = [
			'id' => $custom_field->id,
			'module_type' => [
				'id' => $custom_field->module_type,
				'label' => str_replace("_", " ", $custom_field->module_type)
			],
			'module_id' => $custom_field->module_id,
			'fields' => $fields,
			'status' => $custom_field->status
		];

		wp_send_json([
			'status' => true,
			'message' => esc_html__('Custom field record', 'kc-lang'),
			'data' => $temp
		]);

	}

	public function delete() {

		if ( ! kcCheckPermission( 'custom_field_delete' ) ) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if ( ! isset( $request_data['id'] ) ) {
				wp_send_json( [
					'status'  => false,
					'message' => esc_html__('Data not found', 'kc-lang'),
				] );
			}

			$id = (int)$request_data['id'];

			$results = ( new KCCustomField() )->delete( [ 'id' => $id ] );
            (new KCCustomFieldData())->delete(['field_id' => $id]);

			if ( $results ) {
				wp_send_json( [
					'status'  => true,
					'message' => esc_html__('Custom field has been deleted successfully', 'kc-lang'),
				] );
			} else {
				wp_send_json( [
					'status'  => false,
					'message' => esc_html__('Custom field delete failed', 'kc-lang'),
				] );
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

    public function getCustomFields()
    {
        if(!$this->userHasKivicareRole()){
            wp_send_json( kcUnauthorizeAccessResponse(403) );
        }
        $request_data = $this->request->getInputs();
        $custom_field_table =   $this->db->prefix . 'kc_custom_fields';
        try {
            if (!isset($request_data['module_type'])) {
	            wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
            }
            $user_id = get_current_user_id();
            $current_user_role = $this->getLoginUserRole();
            $request_data = $this->request->getInputs();
            $module_type = $request_data['module_type'];
            $module_id = $request_data['module_id'] ;

            if($request_data['module_type'] == 'patient_module') {
                if($current_user_role == $this->getDoctorRole()){
                    $module_id = $user_id ;
                    $module_id  = " AND module_id IN($module_id,0) ";
                }
                else{
                    $module_id = (int)$request_data['module_id'] ;
                    $module_id  = " AND module_id = {$module_id} " ;
                }
            }

            if($request_data['module_type'] === 'doctor_module') {
                $module_id = (int)$request_data['module_id'] ;
                $module_id  = " AND module_id = {$module_id} " ;
            }

            if($request_data['module_type'] == 'appointment_module'){
                if($current_user_role == $this->getDoctorRole()){
                    $module_id = $user_id ;
                    $module_id  = " AND module_id IN($module_id,0) ";
                } elseif (!empty($request_data['doctor_id'])) {
                    if(gettype($request_data['doctor_id']) === 'string') {
                        $doctor_id  = (int)$request_data['doctor_id'];
                    } else {
                        $doctor_id  = (int)$request_data['doctor_id']['id'];
                    }

                    $module_id  = " AND module_id IN ($doctor_id,0) " ;

                } else {
                    $module_id  = " AND module_id = 0 " ;
                }
            }

            if( isset($module_id) && $request_data['module_type'] !== 'patient_encounter_module'  ){
                $query = "SELECT * FROM {$custom_field_table} WHERE module_type = '{$module_type}'  $module_id" ;
            }else{
                $module_id_temp = $module_id;
                if($current_user_role == $this->getDoctorRole()){
                    $module_id = $user_id ;
                    $module_id  = " AND p.module_id IN($module_id,0) ";
                } elseif (!empty($request_data['doctor_id'])) {
                    if(gettype($request_data['doctor_id']) === 'string') {
                        $doctor_id  = (int)$request_data['doctor_id'];
                    } else {
                        $doctor_id  = (int)$request_data['doctor_id']['id'];
                    }

                    $module_id  = " AND p.module_id IN ($doctor_id,0) " ;

                } else {
                    $module_id  = " AND p.module_id = 0 " ;
                }
                $query = "SELECT p.*, u.fields_data FROM {$custom_field_table} AS p 
                 LEFT JOIN  (SELECT * FROM {$this->db->prefix}kc_custom_fields_data WHERE module_id = {$module_id_temp} ) AS u ON p.id = u.field_id 
                          WHERE p.module_type ='{$module_type}' {$module_id}";
            }
            $custom_module  = $this->db->get_results( $query );

            $fields = [] ;
            if(count($custom_module) > 0) {
                foreach ($custom_module as $key => $value) {
                    $field_data  = '' ;
                    if(!empty($value->fields_data)){
                        $decode_value = json_decode($value->fields);
                        if($decode_value->type != null && 
                        in_array($decode_value->type,['checkbox','file_upload','multiselect'])){
                            $value->fields_data = json_decode($value->fields_data);
                        }
                        $field_data = $value->fields_data ;
                    }
                    $fields[] = array_merge(json_decode($value->fields,true), ['field_data'=> $field_data], ['id'=> $value->id]);
                }
            }

	        wp_send_json([
                'status' => true,
                'message' => esc_html__('Custom fields', 'kc-lang'),
                'data' => array_values($fields)
            ]);


        } catch (Exception $e) {

            $code =$e->getCode();
            $message = $e->getMessage();

            header("Status: $code $message");

	        wp_send_json([
                'status' => false,
                'message' => $message
            ]);
        }

    }

    public function customFieldFileUploadData() {
        if(!$this->userHasKivicareRole()){
            wp_send_json( kcUnauthorizeAccessResponse(403) );
        }
        // Define the size options for file uploads
        $kb = 1024 * 1024;
        $max_file_size = wp_max_upload_size();
        $size_options = [
            ['text' => esc_html__("1MB", "kc-lang"), 'id' => 1 * $kb],
            ['text' => esc_html__("2MB", "kc-lang"), 'id' => 2 * $kb],
            ['text' => esc_html__("5MB", "kc-lang"), 'id' => 5 * $kb],
            ['text' => esc_html__("10MB", "kc-lang"), 'id' => 10 * $kb],
            ['text' => esc_html__("20MB", "kc-lang"), 'id' => 20 * $kb],
            ['text' => esc_html__("50MB", "kc-lang"), 'id' => 50 * $kb],
        ];
    
        // Allow filtering of size options
        $size_options = apply_filters("kivicare_custom_field_upload_file_size_options", $size_options);

        $size_options = array_map(function ($v)use($max_file_size){
            if($v['id'] > $max_file_size){
                $v['$isDisabled'] = true;
            }
            return $v;
        },$size_options);
        // Prepare the type options for file uploads
        $type_options = [];
        foreach (get_allowed_mime_types() as $key => $value) {
            $type_options[] = [
                'text' => str_replace('|',' ',$key), 
                'id' => $value
            ]; 
        }
        global $wp_roles;
        $roles = $wp_roles->roles;
        $all_roles = [];
        foreach ($roles as $role_id => $role_info) {
            if (in_array($role_id, ['administrator', 'kiviCare_clinic_admin', 'kiviCare_doctor', 'kiviCare_patient', 'kiviCare_receptionist'])) {
                $all_roles[] = [
                    'id' => $role_id,
                    'name' => $role_info['name']
                ];
            }
        }
        // Send the JSON response
        wp_send_json([
            'status' => true,
            'data' => [
                'file_size_options' => $size_options,
                'file_type_options' => $type_options,
                'allowed_size' => esc_html__("Current file upload size supported is limited to ","kc-lang").$max_file_size/(1024 * 1024) .esc_html__("MB","kc-lang")
            ],
            'all_roles'=>$all_roles
        ]);
    }
    
}
