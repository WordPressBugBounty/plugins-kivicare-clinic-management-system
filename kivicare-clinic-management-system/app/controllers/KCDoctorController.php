<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCAppointment;
use App\models\KCAppointmentServiceMapping;
use App\models\KCCustomField;
use App\models\KCCustomFieldData;
use App\models\KCDoctorClinicMapping;
use App\models\KCClinicSession;
use App\models\KCClinicSchedule;
use App\models\KCPatientEncounter;
use App\models\KCServiceDoctorMapping;

use App\models\KCUser;
use DateTime;
use Exception;
use WP_User;

class KCDoctorController extends KCBase
{

    public $db;

    private $request;

    public function __construct()
    {

        global $wpdb;

        $this->db = $wpdb;

        $this->request = new KCRequest();

        parent::__construct();
    }

    public function index()
    {

        $table_name = $this->db->prefix . 'kc_doctor_clinic_mappings';

        //check current login user permission
        if (!kcCheckPermission('doctor_list')) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $request_data = $this->request->getInputs();

        //get user args
        $args['role']           = $this->getDoctorRole();
        $args['orderby']        = 'ID';
        $args['order']          = 'DESC';
        $args['fields']          = ['ID','display_name','user_email','user_registered','user_status'];
        if((int)$request_data['perPage'] > 0){
            $args['page'] = (int)$request_data['page'];
            $args['number'] = $request_data['perPage'];
            $args['offset'] = ((int)$request_data['page'] - 1) * (int)$request_data['perPage'];
        }

        $search_condition = '';
        $total = 0;
        if(!empty($request_data['sort'])){
            $request_data['sort'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($request_data['sort'][0]),true));
            if(!empty($request_data['sort']['field']) && !empty($request_data['sort']['type']) && $request_data['sort']['type'] !== 'none'){
                $args['orderby']        = esc_sql($request_data['sort']['field']);
                $args['order']          = esc_sql(strtoupper($request_data['sort']['type']));
            }
        }

        //global filter
        if(!empty($request_data['searchTerm'])){
            $args['search_columns'] = ['user_email','ID','display_name','user_status'];
            $args['search'] = '*'.esc_sql(strtolower(trim($request_data['searchTerm']))).'*' ;
        }else{
            //column wise filter
            if(!empty($request_data['columnFilters'])){   
                $request_data['columnFilters'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($request_data['columnFilters']),true));
                foreach ($request_data['columnFilters'] as $column => $searchValue){
                    $searchValue = !empty($searchValue) ? $searchValue : '';
                    $searchValue = esc_sql(strtolower(trim($searchValue)));
                    if( empty($searchValue) && $column !== 'user_status'){
                        continue;
                    }
                    $column = esc_sql($column);
                    if($column === 'specialties'){
                        $args['meta_query'] = [
                            [
                                'key' => 'basic_data',
                                'value' => $searchValue,
                                'compare' => 'LIKE'
                            ]
                        ];
                        continue;
                    }else if($column === 'clinic_name' && isKiviCareProActive()){
                        $search_condition .= " AND {$this->db->users}.id IN (SELECT doctor_id FROM {$table_name} WHERE clinic_id={$searchValue}) ";
                        continue;
                    }
                    else if($column === 'mobile_number'){
                        $args['meta_query'] =[
                            'relation' => 'OR',
                            [
                                'key' => 'basic_data',
                                'value' => $searchValue,
                                'compare' => 'LIKE'
                            ],
                            [
                                'key' => 'country_calling_code',
                                'value' => $searchValue,
                                'compare' => 'LIKE'
                            ]
                        ];
                       
                        continue;
                    }
                    $search_condition .= " AND {$column} LIKE '%{$searchValue}%' ";

                }
            }
        }
        $current_user_role = $this->getLoginUserRole();
        $doctors = [];
        if (current_user_can('administrator')) {
            $results = $this->getUserData($args,$search_condition);
            //total doctors count
            $total = $results['total'];

            // doctors list
            $doctors = $results['list'];
        } else {
            if(isKiviCareProActive()){
                switch ($current_user_role) {
                    case $this->getReceptionistRole():
                        $clinic_id = kcGetClinicIdOfReceptionist();
                        $query = "SELECT DISTINCT `doctor_id` FROM {$table_name} WHERE `clinic_id` ={$clinic_id}";
                        break;
                    case $this->getClinicAdminRole():
                        $clinic_id = kcGetClinicIdOfClinicAdmin();
                        $query = "SELECT DISTINCT `doctor_id` FROM {$table_name} WHERE `clinic_id` ={$clinic_id}";
                        break;
                }

                if(!empty($query)){
                    // get role wise doctor id from mapping table
                    $result = collect($this->db->get_results($query))->pluck('doctor_id')->toArray();
                    // include mapping doctor in args array
                    $args['include'] = !empty($result) ? $result : [-1];
                    $results = $this->getUserData($args,$search_condition);
                    //total doctors count
                    $total = $results['total'];

                    // doctors list
                    $doctors = $results['list'];
                }
            }else{
                if(in_array($current_user_role , [$this->getReceptionistRole(),$this->getClinicAdminRole()]) ){
                    $results = $this->getUserData($args,$search_condition);
                    //total doctors count
                    $total = $results['total'];

                    // doctors list
                    $doctors = $results['list'];
                }
            }
        }

        if (!count($doctors)) {
	        wp_send_json([
                'status' => false,
                'message' => esc_html__('No doctors found', 'kc-lang'),
                'data' => []
            ]);
        }

        $custom_forms = apply_filters('kivicare_custom_form_list',[],['type' => 'doctor_module']);
        $data = [];
        foreach ($doctors as $key => $doctor) {
            $doctor->ID = (int)$doctor->ID;
            $allUserMeta = get_user_meta( $doctor->ID);
            $data[$key]['ID'] = $doctor->ID;
            $data[ $key ]['profile_image'] =!empty($allUserMeta['doctor_profile_image'][0]) ? wp_get_attachment_url($allUserMeta['doctor_profile_image'][0]) : '';
            $data[$key]['display_name'] = $doctor->display_name;
            //doctor clinic name
            $clinics = $this->db->get_row("SELECT GROUP_CONCAT(clinic.name) AS clinic_name,GROUP_CONCAT(clinic.id) AS clinic_id FROM {$this->db->prefix}kc_clinics AS clinic LEFT JOIN {$this->db->prefix}kc_doctor_clinic_mappings AS dr_clinic ON dr_clinic.clinic_id = clinic.id WHERE dr_clinic.doctor_id ={$doctor->ID} GROUP BY dr_clinic.doctor_id ");
            $data[$key]['clinic_name'] = !empty($clinics->clinic_name) ? decodeSpecificSymbols($clinics->clinic_name) : "";
            $data[$key]['clinic_id'] = !empty($clinics->clinic_id) ? $clinics->clinic_id : "";
            $data[$key]['user_email'] = $doctor->user_email;
            $data[$key]['user_status'] = $doctor->user_status;
            $data[$key]['user_registered'] = $doctor->user_registered;
            $data[$key]['custom_forms'] = $custom_forms;
            $data[$key]['user_registered_formated']= date("Y-m-d", strtotime($doctor->user_registered));
            $user_deactivate_status = !empty($allUserMeta['kivicare_user_account_status'][0]) ? $allUserMeta['kivicare_user_account_status'][0] : '';
            // verify doctor by shortcode condition
            $data[$key]['user_deactivate'] =!empty($user_deactivate_status) ? $user_deactivate_status : 'yes';
            //doctor country code
            $country_calling_code = !empty($allUserMeta['country_calling_code'][0]) ? '+' . $allUserMeta['country_calling_code'][0] : '';
            //doctor other basic data
            $user_meta = !empty($allUserMeta['basic_data'][0]) ? $allUserMeta['basic_data'][0] : false ;
            if(!empty($user_meta)) {
                $basic_data = json_decode($user_meta,true);
                if(!empty($basic_data)) {
                    foreach ($basic_data as $basic_data_key => $basic_data_value){
                        if($basic_data_key === 'specialties'){
                            $data[$key][$basic_data_key] =   collect($basic_data_value)->pluck('label')->implode(',') ;
                        }else if($basic_data_key === 'qualifications'){
                            $data[$key][$basic_data_key] = collect($basic_data_value)->map(function($v){
                                return $v->degree .'( '.$v->university.'-'.$v->year.' )';
                            })->implode(',');
                        }else if ($basic_data_key === 'mobile_number') {
                            $data[$key][$basic_data_key] =  $country_calling_code . ' ' . $basic_data_value;
                        }else{
                            $data[$key][$basic_data_key] = $basic_data_value;
                        }
                    }
                }
            }
            foreach (['dob','address','city','country','postal_code','gender','blood_group','mobile_number'] as $item){
                if(!array_key_exists($item,$data[ $key ])){
                    $data[ $key ][$item] = '-';
                }
            }
            $data[ $key ]['full_address'] = ('#').(!empty($data[ $key ]['address']) && $data[ $key ]['address'] !== '-' ? $data[ $key ]['address'].',' : '' ) .
                (!empty($data[ $key ]['city']) && $data[ $key ]['city'] !== '-' ? $data[ $key ]['city'].',' : '').
                (!empty($data[ $key ]['postal_code']) && $data[ $key ]['postal_code'] !== '-' ? $data[ $key ]['postal_code'].',' : '').
                (!empty($data[ $key ]['country'])  && $data[ $key ]['country'] !== '-' ? $data[ $key ]['country'] : '');
                $data[$key] = apply_filters('kivicare_doctor_lists',$data[$key],$doctor,$allUserMeta);
        }

	    wp_send_json([
            'status' => true,
            'message' => esc_html__('Doctors list', 'kc-lang'),
            'data' => $data,
            'total_rows' => $total
        ]);
    }

    public function save()
    {

        $request_data = $this->request->getInputs();

        $profile_permission = false;
        //profile permission
        if( kcCheckPermission( 'doctor_profile' )
            && !empty($request_data['ID']) && (new KCUser())->doctorPermissionUserWise($request_data['ID'] ) ) {
            $profile_permission = true;
        }

        //check current login user permission
        if (! ( kcCheckPermission('doctor_add')  || $profile_permission)) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        global $wpdb;
        $current_user_role = $this->getLoginUserRole();

        $rules = [
            'first_name' => 'required',
            'last_name' => 'required',
            'user_email' => 'required|email',
            'mobile_number' => 'required',
            'gender' => 'required',
            'country_code' => 'required',
            'country_calling_code' => 'required',
        ];

        $errors = kcValidateRequest($rules, $request_data);

        if (!empty(count($errors))) {
	        wp_send_json([
                'status' => false,
                'message' => $errors[0]
            ]);
        }

        //check email condition
        $email_condition = kcCheckUserEmailAlreadyUsed($request_data);
        if(empty($email_condition['status'])){
	        wp_send_json($email_condition);
        }

        // Remove parentheses
        $request_data['mobile_number'] = str_replace(['(', ')'], '', $request_data['mobile_number']);

        // Remove dashes
        $request_data['mobile_number'] = str_replace('-', '', $request_data['mobile_number']);

        // Remove extra spaces
        $request_data['mobile_number'] = preg_replace('/\s+/', '', $request_data['mobile_number']);

        $temp = [
            'mobile_number' => str_replace(' ', '', $request_data['mobile_number']) ,
            'gender' => $request_data['gender'],
            'dob' => $request_data['dob'],
            'address' => $request_data['address'],
            'city' => $request_data['city'],
            'state' => !empty($request_data['state']) ? $request_data['state'] : '',
            'country' => $request_data['country'],
            'postal_code' => $request_data['postal_code'],
            'qualifications' => !empty($request_data['qualifications']) ? $request_data['qualifications'] : [] ,
            'price_type' => $request_data['price_type'],
            'price' => $request_data['price'],
            'no_of_experience' => $request_data['no_of_experience'],
            'video_price' => isset($request_data['video_price']) ? $request_data['video_price'] : 0,
            'specialties' => !empty($request_data['specialties']) ? $request_data['specialties'] : [],
            'time_slot' => $request_data['time_slot']
        ];

        if (isset($request_data['price_type']) && $request_data['price_type'] === "range") {
            $temp['price'] = $request_data['minPrice'] . '-' . $request_data['maxPrice'];
        }

//        $service_doctor_mapping = new KCServiceDoctorMapping();
        if (!isset($request_data['ID'])) {

            // create new user
            $password = kcGenerateString(12);
            $user = wp_create_user(kcGenerateUsername($request_data['first_name']), $password, sanitize_email( $request_data['user_email']) );
            $u = new WP_User($user);
            $u->display_name = $request_data['first_name'] . ' ' . $request_data['last_name'];
            wp_insert_user($u);
            //add doctor role to user
            $u->set_role($this->getDoctorRole());

            $user_id = $u->ID;

            //clinic id based on role
            if ($current_user_role == $this->getReceptionistRole()) {
                $request_data['clinic_id'] = kcGetClinicIdOfReceptionist();
            }
            if($current_user_role == $this->getClinicAdminRole()){
                $request_data['clinic_id'] = kcGetClinicIdOfClinicAdmin();
            }

            if (isKiviCareProActive()) {
                if ($current_user_role == $this->getReceptionistRole()) {
                    $this->saveDoctorClinic($user_id,kcGetClinicIdOfReceptionist());
                }else if($current_user_role == $this->getClinicAdminRole()){
                    $this->saveDoctorClinic($user_id,kcGetClinicIdOfClinicAdmin());
                }else{
                    foreach ($request_data['clinic_id'] as $value) {
                        $this->saveDoctorClinic($user_id,$value['id']);
                    }
                }
            } else {
                //if pro not active default clinic id in mapping table
                $this->saveDoctorClinic($user_id,kcGetDefaultClinicId());
            }

            //get email/sms/whatsapp template dynamic key array
            $user_email_param = kcCommonNotificationUserData((int)$user_id,$password);

            //send email after doctor save
            kcSendEmail($user_email_param);

            //send sms/whatsapp after doctor save
            if(!empty(kcCheckSmsOptionEnable() || kcCheckWhatsappOptionEnable())){
                $sms = apply_filters('kcpro_send_sms', [
                    'type' => 'doctor_registration',
                    'user_data' => $user_email_param,
                ]);
            }

            $message = esc_html__('Doctor has been saved successfully', 'kc-lang');

        } else {

	        //request doctor detail is not from valid user redirect to 403 page
	        if(!(new KCUser())->doctorPermissionUserWise($request_data['ID'])){
		        wp_send_json(kcUnauthorizeAccessResponse(403));
	        }
            //update doctor user
            wp_update_user(
                array(
                    'ID' => (int)$request_data['ID'],
                    'user_email' => sanitize_email( $request_data['user_email'] ),
                    'display_name' => $request_data['first_name'] . ' ' . $request_data['last_name']
                )
            );
            $request_data['ID'] = (int)$request_data['ID'];
            $user_id = $request_data['ID'];
            if(in_array($current_user_role,['administrator',$this->getDoctorRole()])){
	            (new KCDoctorClinicMapping())->delete(['doctor_id' => $request_data['ID']]);
	            if (isKiviCareProActive()) {
                    // Check if the current user role is 'administrator'
                    if ($current_user_role == 'administrator') {
                        // Get the IDs of all clinic_ids from the request data and join them as a comma-separated string
                        $all_clinic_ids = collect($request_data['clinic_id'])->pluck('id')->implode(',');

                        // Build the delete condition query
                        $delete_condition_query = "doctor_id = {$user_id} AND clinic_id NOT IN ({$all_clinic_ids})";

                        // Delete records from kc_clinic_sessions table based on the delete condition query
                        $clinic_sessions_table = "{$this->db->prefix}kc_clinic_sessions";
                        $this->db->query("DELETE FROM {$clinic_sessions_table} WHERE {$delete_condition_query}");

                        // Delete records from kc_service_doctor_mapping table based on the delete condition query
                        $service_doctor_mapping_table = "{$this->db->prefix}kc_service_doctor_mapping";
                        $this->db->query("DELETE FROM {$service_doctor_mapping_table} WHERE {$delete_condition_query}");
                    }
		            foreach ($request_data['clinic_id'] as $value) {
                        $this->saveDoctorClinic($user_id,$value['id']);
                    }
                    do_action('kcpro_update_user',$request_data);
                }else{
                    //if pro not active default clinic id in mapping table
		            $this->saveDoctorClinic($user_id,kcGetDefaultClinicId());
                }
            }

            $message = __('Doctor updated successfully','kc-lang');

        }

        if ($user_id) {

            // Zoom telemed Service entry save
            if(isKiviCareTelemedActive()) {

                //get doctor telemed service
                if($request_data['enableTeleMed'] == '' || empty($request_data['enableTeleMed'])){
                    $request_data['enableTeleMed'] = 'false';
                }
                $request_data['enableTeleMed'] = in_array((string)$request_data['enableTeleMed'],['true','1']) ? 'true' : 'false';

                //save zoom configurations details
                if(!empty($request_data['api_key']) && !empty($request_data['api_secret'])){
                    apply_filters('kct_save_zoom_configuration', [
                        'user_id' => (int)$user_id,
                        'enableTeleMed' => $request_data['enableTeleMed'],
                        'api_key' => $request_data['api_key'],
                        'api_secret' => $request_data['api_secret']
                    ]);
                }
            }

            //update user firstname
            update_user_meta($user_id, 'first_name', $request_data['first_name']);

            //update/save user lastname
            update_user_meta($user_id, 'last_name', $request_data['last_name']);

            //update/save user other details
            update_user_meta($user_id, 'basic_data', json_encode($temp, JSON_UNESCAPED_UNICODE));

            //update/save user country calling code
            update_user_meta($user_id, 'country_calling_code', $request_data['country_calling_code']);

            //update/save user country code
            update_user_meta($user_id, 'country_code', $request_data['country_code']);

            //update/save user description
            if(isset($request_data['description']) && !empty($request_data['description'])){
                update_user_meta($user_id, 'doctor_description',$request_data['description'] );
            }

            //update/save user custom field
            if (!empty($request_data['custom_fields'])) {
                kvSaveCustomFields('doctor_module', $user_id, $request_data['custom_fields']);
            }

            //update/save user digital signature
            $doc_signature = !empty($request_data['signature']) ? $request_data['signature'] : '';
            update_user_meta($user_id ,'doctor_signature',$doc_signature);

            //update/save doctor profile image
            if(isset($request_data['profile_image']) && !empty((int)$request_data['profile_image']) ) {
                update_user_meta( $user_id, 'doctor_profile_image',  (int)$request_data['profile_image'] );
            }

            //update/save user status
            $wpdb->update($wpdb->base_prefix . 'users', ['user_status' => $request_data['user_status']], ['ID' => (int)$user_id]);
        }


        if (!empty($user->errors)) {
	        wp_send_json([
                'status' => false,
                'message' => $user->get_error_message() ? $user->get_error_message() : esc_html__('Failed to save Doctor data.', 'kc-lang')
            ]);
        } else {
            if(!empty($request_data['ID'])){
                do_action( 'kc_doctor_update', $user_id );
            }else{
                do_action( 'kc_doctor_save', $user_id );
            }
	        wp_send_json(
                [
                    'status' => true,
                    'message' => $message,
                    'choose_language_updated' => apply_filters('kcpro_update_user_choose_language_updated',false,$request_data)
                ]
        );
        }
    }

    public function edit()
    {

        //check current login user permission
        if (!(kcCheckPermission('doctor_edit') || kcCheckPermission('doctor_view') || kcCheckPermission('doctor_profile'))) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $request_data = $this->request->getInputs();

        
        try {

            if (!isset($request_data['id'])) {
	            wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
            }


            $id = (int)$request_data['id'];
			//request doctor detail is not from valid user redirect to 403 page
	        if(!(new KCUser())->doctorPermissionUserWise($id)){
		        wp_send_json(kcUnauthorizeAccessResponse(403));
	        }
            $user = get_userdata($id);
            unset($user->user_pass);
            $allUserMetaData = get_user_meta( $id);
            $user_data  = !empty($allUserMetaData['basic_data'][0]) ? $allUserMetaData['basic_data'][0] : [];

            //remove null value from object
            if(!empty($user_data)){
                $user_data = array_map(function ($v){
                    if(is_null($v) || $v == 'null'){
                        $v = '';
                    }
                    return $v;
                },(array)json_decode($user_data));
            }else{
                $user_data = [];
            }
            $user_data['mobile_number'] = !empty($user_data['mobile_number']) ? (string)$user_data['mobile_number'] : '';
            $user_image_url = !empty($allUserMetaData['doctor_profile_image'][0]) ? wp_get_attachment_url($allUserMetaData['doctor_profile_image'][0]) : '';
            $first_name = !empty($allUserMetaData['first_name'][0]) ? $allUserMetaData['first_name'][0] : '';
            $last_name  = !empty($allUserMetaData['last_name'][0]) ? $allUserMetaData['last_name'][0] : '';
            $description = !empty($allUserMetaData['doctor_description'][0]) ? $allUserMetaData['doctor_description'][0] : '';
           
            if(!empty($allUserMetaData['country_code'][0])){
                $country_code = $allUserMetaData['country_code'][0];
            }
            else if(!get_option( KIVI_CARE_PREFIX . 'default_country_code_for_new_plugin' )) {
                $country_code = 'GB';
                update_option(KIVI_CARE_PREFIX . 'default_country_code_for_new_plugin', 'yes');
            }
            else if(get_option( KIVI_CARE_PREFIX . 'country_code' )){
                $country_code = get_option(KIVI_CARE_PREFIX . 'country_code', '');
            }
            else{
                $country_code = 'GB';
            }

            if(!empty($allUserMetaData['country_calling_code'][0])){
                $country_calling_code = $allUserMetaData['country_calling_code'][0];
            }
            else if(!get_option( KIVI_CARE_PREFIX . 'default_country_calling_code_for_new_plugin' )) {
                $country_calling_code = '44';
                update_option(KIVI_CARE_PREFIX . 'default_country_calling_code_for_new_plugin', 'yes');
            }
            else if(get_option( KIVI_CARE_PREFIX . 'country_calling_code' )){
                $country_calling_code = get_option(KIVI_CARE_PREFIX . 'country_calling_code', '');
            }
            else{
                $country_calling_code = '44';
            }      

            $data = (object)array_merge((array)$user->data, $user_data);
            $data->first_name = $first_name;
            $data->username = $data->user_login;
            $data->description = !empty($description) ? $description : '';
            $data->last_name = $last_name;
            $data->qualifications = !empty($data->qualifications) ? $data->qualifications : [];
            $data->specialties = !empty($data->specialties) ? $data->specialties : [];
            $doctor_rating = kcCalculateDoctorReview($id,'list');
            $data->rating = $doctor_rating['star'];
            $data->total_rating = $doctor_rating['total_rating'];
            $data->is_enable_doctor_gmeet = isKiviCareGoogleMeetActive() && get_user_meta($id, KIVI_CARE_PREFIX.'google_meet_connect',true) == 'on' &&  get_user_meta($id,'telemed_type',true) === 'googlemeet';
            //doctor clinic
            $clinics = collect($this->db->get_results("SELECT clinic.id AS id ,clinic.name AS label FROM {$this->db->prefix}kc_clinics AS clinic 
            LEFT JOIN {$this->db->prefix}kc_doctor_clinic_mappings AS doctor_clinic ON doctor_clinic.clinic_id =clinic.id 
            WHERE doctor_clinic.doctor_id={$id}"))
                ->map(function ($v){
                    return ['id' => $v->id ,'label' => decodeSpecificSymbols($v->label)];
                })->toArray();
            $data->clinic_id = $clinics;
            if (isset($data->price_type)) {
                if ($data->price_type === "range") {
                    $price = explode("-", $data->price);
                    $data->minPrice = isset($price[0]) ? $price[0] : 0;
                    $data->maxPrice = isset($price[1]) ? $price[1] : 0;
                    $data->price = 0;
                }
            } else {
                $data->price_type = "range";
            }

            //doctor telemed descriptions
            if(isKiviCareTelemedActive()) {

                $config_data = apply_filters('kct_get_zoom_configuration', [
                    'user_id' => $id,
                ]);

                if (isset($config_data['status']) && $config_data['status']) {
                    $data->enableTeleMed = !empty($config_data['data']->enableTeleMed) && ($config_data['data']->enableTeleMed === 'true' || $config_data['data']->enableTeleMed === true) ? $config_data['data']->enableTeleMed : 'false';
                    $data->api_key = !empty($config_data['data']->api_key) && $config_data['data']->api_key !== 'null' ? $config_data['data']->api_key : '';
                    $data->api_secret = !empty($config_data['data']->api_secret) && $config_data['data']->api_secret !== 'null' ? $config_data['data']->api_secret : '';
                    $data->zoom_id = !empty($config_data['data']->zoom_id) && $config_data['data']->zoom_id !== 'null' ? $config_data['data']->zoom_id : '';
                }
            }

            //doctor digital details
            $data->signature = '';
            $doctor_signature =!empty($allUserMetaData['doctor_signature'][0]) ? $allUserMetaData['doctor_signature'][0] : '';
            if(!empty($doctor_signature)){
                $data->signature = strval($doctor_signature);
            }
            //doctor country calling code
            $data->country_calling_code = $country_calling_code;

            //doctor country code
            $data->country_code = $country_code;
            
            //doctor custom field
            $custom_filed = kcGetCustomFields('doctor_module', $id);
            $data->user_profile =$user_image_url;
            $data->custom_forms = apply_filters('kivicare_custom_form_list',[],['type' => 'doctor_module']);

            if(isKiviCareProActive()){
                $data->choose_language =get_user_locale() ;
            }

            if ($data) {
                $data = apply_filters('kivicare_doctor_edit_data',$data,$allUserMetaData);
	            wp_send_json([
                    'status' => true,
                    'message' => 'Doctor data',
                    'id' => $id,
                    'user_data' => $user_data,
                    'data' => $data,
                    'custom_filed'=>$custom_filed
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

    public function delete()
    {

        //check current login user permission
        if (!kcCheckPermission('doctor_delete')) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $request_data = $this->request->getInputs();

        try {

            if (!isset($request_data['id'])) {
	            wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
            }

            $id = (int)$request_data['id'];

			if(!(new KCUser())->doctorPermissionUserWise($id)){
				wp_send_json(kcUnauthorizeAccessResponse(403));
			}
            //delete doctor zoom telemed service
            if (isKiviCareTelemedActive()) {
                apply_filters('kct_delete_patient_meeting', ['doctor_id' => $id]);
            }

            // hook for doctor delete
            do_action( 'kc_doctor_delete', $id );

            //delete all doctor related entry

            //delete doctor holiday
            (new KCClinicSchedule())->delete(['module_id' => $id, 'module_type' => 'doctor']);

            //delete doctor clinic session
            (new KCClinicSession())->delete(['doctor_id' => $id]);

            //doctor clinic mapping entry
            (new KCDoctorClinicMapping())->delete(['doctor_id' => $id]);
            //delete doctor appointment
            (new KCAppointment())->loopAndDelete(['doctor_id' => $id],false);
            //delete doctor encounter
            (new KCPatientEncounter())->loopAndDelete(['doctor_id' => $id],false);

            // delete woocommerce product on service delete
            collect((new KCServiceDoctorMapping())->get_by(['doctor_id' => $id]))->pluck('id')->map(function($v){
                $product_id = kivicareGetProductIdOfService($v);
                if($product_id != null && get_post_status( $product_id )){
                    do_action( 'kc_woocoomerce_service_delete', $product_id );
                    wp_delete_post($product_id);
                }
                return $v;
            });
            //delete doctor service
            (new KCServiceDoctorMapping())->delete(['doctor_id' => $id]);
            //delete current doctor custom field
            (new KCCustomField())->delete(['module_type' => 'appointment_module','module_id' => $id]);
            (new KCCustomFieldData())->delete(['module_type' => 'doctor_module','module_id' => $id]);

            //delete doctor usermeta
            delete_user_meta($id, 'basic_data');
            delete_user_meta($id, 'first_name');
            delete_user_meta($id, 'last_name');
            //delete user
            $results = wp_delete_user($id);
            if ($results) {
	            wp_send_json([
                    'status' => true,
                    'message' => esc_html__('Doctor has been deleted successfully', 'kc-lang'),
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

    public function getDoctorWorkdays(){
        $request_data = $this->request->getInputs();
        $results = [];
        $status = false;
        $login_user_role = $this->getLoginUserRole();

        if($this->getDoctorRole() === $login_user_role) {
            $request_data['doctor_id'] = get_current_user_id() ;
        }

        if(isKiviCareProActive()){
            if($login_user_role == 'kiviCare_clinic_admin'){
                $request_data['clinic_id'] = kcGetClinicIdOfClinicAdmin();
            }elseif ($login_user_role == 'kiviCare_receptionist') {
                $request_data['clinic_id'] = kcGetClinicIdOfReceptionist();
            }
        }


        //check doctor and clini id exists in request data
        if(isset($request_data['clinic_id']) && $request_data['clinic_id'] != '' &&
            isset($request_data['doctor_id']) && $request_data['doctor_id'] != ''){

            $request_data['doctor_id'] = (int)$request_data['doctor_id'];
            $request_data['clinic_id'] = (int)$request_data['clinic_id'];


            //get doctor session days
            $results = collect($this->db->get_results("SELECT DISTINCT day FROM {$this->db->prefix}kc_clinic_sessions where doctor_id={$request_data['doctor_id']} AND clinic_id={$request_data['clinic_id']}"))->pluck('day')->toArray();
            $leaves = collect($this->db->get_results("SELECT * FROM {$this->db->prefix}kc_clinic_schedule where module_id ={$request_data['doctor_id']} AND module_type = 'doctor'"))->toArray();

            //week day for php vue appointment dashboard
            $days = [1 => 'sun', 2 => 'mon', 3 =>'tue', 4 => 'wed', 5 => 'thu', 6 => 'fri', 7 => 'sat'];
            //week day for php shortcode widget
            if(!empty($request_data['type']) && $request_data['type'] === 'flatpicker'){
                $days = [0 => 'sun', 1 => 'mon', 2 =>'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat'];
            }


            if(count($results) > 0){
                // get unavilable  days
               $results = array_diff(array_values($days),$results);
               //get key of unavilable days
               $results = array_map(function ($v) use ($days){
                   return array_search($v,$days);
               },$results);
               $results = array_values($results);
            }
            else{
                //get all days keys
                $results = array_keys($days);
            }
            $status = true;
        }
	    wp_send_json([
            'status' => $status,
             'data' => $results,
             'holiday' => $leaves
        ]);
    }

    public function getDoctorWorkdayAndSession(){
        $request_data = $this->request->getInputs();
        $doctors_sessions = doctorWeeklyAvailability(['clinic_id'=>$request_data['clinic_id'],'doctor_id'=>$request_data['doctor_id']]);
	    wp_send_json([
            'data' => $doctors_sessions,
            'status' => true
        ]);
    }

    public function saveDoctorClinic($doctor_id,$clinic_id){
        //save/update doctor clinic mappings
        $doctor_mapping = new KCDoctorClinicMapping;
        $new_temp = [
            'doctor_id' => (int)$doctor_id,
            'clinic_id' => (int)$clinic_id,
            'owner' => 0,
            'created_at' => current_time('Y-m-d H:i:s')
        ];
        $doctor_mapping->insert($new_temp);
    }

}
