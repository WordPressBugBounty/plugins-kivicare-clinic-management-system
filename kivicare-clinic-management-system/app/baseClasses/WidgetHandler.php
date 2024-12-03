<?php

namespace App\baseClasses;
use App\controllers\KCBookAppointmentWidgetController;
use App\controllers\KCServiceController;
use App\models\KCServiceDoctorMapping;
use function Clue\StreamFilter\fun;

class WidgetHandler extends KCBase {

    public $message = '';
    public $db;
	public function init() {
        add_action('init',function(){
            $this->message = __("Current user can not view the widget. Please open this page in incognito mode or use another browser.","kc-lang");
        });
        global $wpdb;
        $this->db = $wpdb;
        add_shortcode('bookAppointment', [$this, 'bookAppointmentWidget']);
        add_shortcode('patientDashboard', [$this, 'patientDashboardWidget']);
        add_shortcode('kivicareBookAppointment', [$this, 'kivicareBookAppointmentWidget']);
        add_shortcode('kivicareBookAppointmentButton', [$this, 'kivicareBookAppointmentButtonWidget']);
        add_shortcode('kivicareRegisterLogin', [$this, 'kivicareRegisterLogin']);
    }



    //button appointment widget
    public function kivicareBookAppointmentButtonWidget($param){
        wp_enqueue_script( 'country-code-select2-js', plugins_url('kivicare-clinic-management-system/assets/js/select2.min.js'),['jquery'],KIVI_CARE_VERSION, true);
        if( !(isset($_REQUEST['action']) && $_REQUEST['action'] =='elementor') ){
            wp_enqueue_style('country-code-select2-css', plugins_url('kivicare-clinic-management-system/assets/css/select2.min.css'), array(), KIVI_CARE_VERSION, false);
        }
        ob_start();
        if(empty($this->getLoginUserRole()) || $this->getLoginUserRole() === $this->getPatientRole()){
            wp_print_styles("kc_book_appointment");
            $this->shortcodeScript('kivicarePopUpBookAppointment');
            $this->shortcodeScript('kivicareBookAppointmentWidget');
            wp_enqueue_script( 'country-code-select2-js', $this->plugin_url . 'assets/js/select2.min.js',['jquery'],KIVI_CARE_VERSION, true);
            wp_enqueue_script('kc_bookappointment_widget');
            require KIVI_CARE_DIR . 'app/baseClasses/popupBookAppointment/bookAppointment.php';
        }else{
            echo esc_html($this->message);
        }
        return ob_get_clean();
    }

    //old appointment widget (vuejs)
    public function bookAppointmentWidget ($param) {
        ob_start();
        if(empty($this->getLoginUserRole()) || $this->getLoginUserRole() === $this->getPatientRole()){

            // sanitize parameters of shortcode
            $temp = $this->sanitizeShortCodeData($param,'bookAppointmentWidget');
            $doctor_id = $temp['doctor'];
            $clinic_id = $temp['clinic'];
            $service_id = $temp['service'];
            $user_id = get_current_user_id();

            // condition if clinic id provide in shortcode parameter or $_Get is available
            if($clinic_id != 0){
                if($this->checkIfPreSelectClinicExists($clinic_id)){
                    echo esc_html__('Selected clinic not available', 'kc-lang');
                    return ob_get_clean();
                }
            }else{
                // condition if anyy clinic is available in system and if only 1 available preselect it
                $kiviCareclinic = $this->checkIfKiviCareAnyClinicAvailable($clinic_id);
                if($kiviCareclinic['status']){
                    echo esc_html__('No clinic available', 'kc-lang');
                    return ob_get_clean();
                }else{
                    $clinic_id = $kiviCareclinic['clinic'];
                }
            }

            // condition if clinic id provide in shortcode parameter or $_Get is available
            if($doctor_id != 0){
                if($clinic_id == 0){
                    echo esc_html__('Please Provide clinic id', 'kc-lang');
                    return ob_get_clean();
                }
                if($this->checkIfPreSelectDoctorExists($doctor_id)){
                    echo esc_html__('Select Doctor Not available', 'kc-lang');
                    return ob_get_clean();
                }
            }else{
                // condition if anyy doctor is available in system and if only 1 available preselect it
                $kiviCareDoctor = $this->checkIfKiviCareAnyDoctorAvailable($doctor_id);
                if($kiviCareDoctor['status']){
                    echo esc_html__('No Doctor  available', 'kc-lang');
                    return ob_get_clean();
                }else{
                    if($clinic_id != 0){
                        $doctor_id = $kiviCareDoctor['doctor'];
                    }
                }
            }

            $this->shortcodeScript('bookAppointmentWidget');
            echo "<div id='app' class='kivi-care-appointment-booking-container kivi-widget' >
            <book-appointment-widget v-bind:user_id='$user_id' v-bind:doctor_id='$doctor_id' v-bind:clinic_id='$clinic_id' v-bind:service_id='$service_id' >
            </book-appointment-widget></div>";
        }else{
            echo esc_html($this->message);
        }
        return ob_get_clean();
    }

    //patient login/register widget (vuejs)
    public function patientDashboardWidget () {
        $theme_mode = get_option(KIVI_CARE_PREFIX . 'theme_mode');
        $rtl_attr = in_array($theme_mode,['1','true']) ? 'rtl' : '';
        ob_start();
        if(empty($this->getLoginUserRole()) || $this->getLoginUserRole() === $this->getPatientRole()){
            $this->shortcodeScript('patientDashboardWidget');
            echo "<div id='app' class='kivi-care-patient-dashboard-container kivi-widget' dir='{$rtl_attr}'><patient-dashboard-widget></patient-dashboard-widget></div>";
        }else{
            echo esc_html($this->message);
        }
        return ob_get_clean();
    }

    //new book appointment widget
    public function kivicareBookAppointmentWidget($param,$content,$tag){
        wp_enqueue_script( 'country-code-select2-js', plugins_url('kivicare-clinic-management-system/assets/js/select2.min.js'),['jquery'],KIVI_CARE_VERSION, true);
        if( !(isset($_REQUEST['action']) && $_REQUEST['action'] =='elementor') ){
            wp_enqueue_style('country-code-select2-css', plugins_url('kivicare-clinic-management-system/assets/css/select2.min.css'), array(), KIVI_CARE_VERSION, false);
        }
        ob_start();
        if (empty($this->getLoginUserRole()) || $this->getLoginUserRole() === $this->getPatientRole()) {
            // sanitize parameters of shortcode
            $request_data = $this->sanitizeShortCodeData($param,'kivicareBookAppointmentWidget');
            $shortcode_doctor_id = $request_data['doctor'];
            $shortcode_clinic_id = $request_data['clinic'];
            $shortcode_service_id = $request_data['service'];
            $shortcode_doctor_id_single =  false;
            $shortcode_clinic_id_single =  false;

            if(kcGoogleCaptchaData('status') === 'on') {
                $siteKey = kcGoogleCaptchaData('site_key');
                if(empty($siteKey) && empty(kcGoogleCaptchaData('secret_key'))){
                    echo esc_html__('Google Recaptcha Data Not found', 'kc-lang');
                    return ob_get_clean();   
                }
                $this->googleRecaptchaload($siteKey);
            }
 
            // condition if clinic id provide in shortcode parameter or $_Get is available
            if($shortcode_clinic_id != 0){
                if($this->checkIfPreSelectClinicExists($shortcode_clinic_id)){
                    echo esc_html__('Selected clinic not available', 'kc-lang');
                    return ob_get_clean();
                }
                $shortcode_clinic_id_single = !(str_contains($shortcode_clinic_id, ','));
            }else{
                // condition if anyy clinic is available in system and if only 1 available preselect it
                $kiviCareclinic = $this->checkIfKiviCareAnyClinicAvailable($shortcode_clinic_id);
                if($kiviCareclinic['status']){
                    echo esc_html__('No clinic available', 'kc-lang');
                    return ob_get_clean();
                }else{
                    
                    if(empty($shortcode_clinic_id)){
                        $shortcode_clinic_id_single = $kiviCareclinic['single'];
                        $shortcode_clinic_id = $kiviCareclinic['clinic'];
                    }
                }
            }

            // condition if doctor id provide in shortcode parameter or $_Get is available
            if($shortcode_doctor_id != 0){
                if($shortcode_clinic_id == 0){
                    echo esc_html__('Please Provide clinic id', 'kc-lang');
                    return ob_get_clean();
                }
                if($this->checkIfPreSelectDoctorExists($shortcode_doctor_id)){
                    echo esc_html__('Select Doctor Not available', 'kc-lang');
                    return ob_get_clean();
                }
                $shortcode_doctor_id_single = !(str_contains($shortcode_doctor_id, ','));
            }else{
                // condition if any doctor is available in system and if only 1 available preselect it
                $kiviCareDoctor = $this->checkIfKiviCareAnyDoctorAvailable($shortcode_doctor_id);
                if($kiviCareDoctor['status']){
                    echo esc_html__('No Doctor  available', 'kc-lang');
                    return ob_get_clean();
                }else{
                    if($shortcode_clinic_id != 0){
                        $shortcode_doctor_id_single = $kiviCareDoctor['single'];
                        $shortcode_doctor_id = $kiviCareDoctor['doctor'];
                    }
                }
            }

             //condition if service id provide in shortcode parameter or $_GET is available
            if($shortcode_service_id != 0){
                if($this->checkIfPreSelectServiceExists($request_data)){
                    echo esc_html__('Select Service Not available', 'kc-lang');
                    return ob_get_clean();
                }
            }else{
                // condition if any service is available in system and if only 1 available preselect it
                $kiviCareService = $this->checkIfKiviCareAnyServiceAvailable($request_data);
                if($kiviCareService['status']){
                    echo esc_html__('No service  available', 'kc-lang');
                    return ob_get_clean();
                }else{
                    $shortcode_service_id = $kiviCareService['service'];
                }
            }

            $popup = !empty($param['popup']) && $param['popup'] === 'on';
            wp_print_styles("kc_book_appointment");
            $this->shortcodeScript('kivicareBookAppointmentWidget');
            if(!$popup){
                wp_enqueue_script('kc_bookappointment_widget');
            }
            require KIVI_CARE_DIR . 'app/baseClasses/bookAppointment/bookAppointment.php';

        } else {

            echo esc_html($this->message);

        }

        return ob_get_clean();

    }

    //login/register widget
    public function kivicareRegisterLogin($param){
        wp_enqueue_script( 'country-code-select2-js', plugins_url('kivicare-clinic-management-system/assets/js/select2.min.js'),['jquery'],KIVI_CARE_VERSION, true);
        if( !(isset($_REQUEST['action']) && $_REQUEST['action'] =='elementor') ){
            wp_enqueue_style('country-code-select2-css', plugins_url('kivicare-clinic-management-system/assets/css/select2.min.css'), array(), KIVI_CARE_VERSION, false);
        }
        ob_start();
        $this->shortcodeScript('kivicareRegisterLogin');
        if(kcGoogleCaptchaData('status') === 'on') {
            $siteKey = kcGoogleCaptchaData('site_key');
            if(empty($siteKey) && empty(kcGoogleCaptchaData('secret_key'))){
                echo esc_html__('Google Recaptcha Data Not found', 'kc-lang');
                return ob_get_clean();   
            }
            $this->googleRecaptchaload($siteKey);
        }
        $data = get_option(KIVI_CARE_PREFIX . 'user_registration_shortcode_role_setting', true);
        $data = !empty($data) && is_array($data) ? $data : [];

        $userList = [];
        $clinic_id_param = null;

        if(!empty($data)){
            foreach ($data as $key => $value) {
                if($value === 'on'){
                    if($key === 'kiviCare_patient'){
                        $userList[$key] = __("Patient", "kc-lang");
                    } elseif($key === 'kiviCare_doctor'){
                        $userList[$key] = __('Doctor',"kc-lang");
                    } elseif($key === 'kiviCare_receptionist'){
                        $userList[$key] = __('Receptionist',"kc-lang");
                    }
                }
            }
        }else{
            $userList = [
                'kiviCare_patient' => __("Patient", "kc-lang"),
                'kiviCare_doctor'  => __('Doctor',"kc-lang"),
                'kiviCare_receptionist'  => __('Receptionist',"kc-lang"),
            ];
        }

        $receptionist_status = true;
        $modules = get_option(KIVI_CARE_PREFIX . 'modules');
        if ($modules) {
            $modules = json_decode($modules);
            $modules1 = $modules->module_config;

            foreach ($modules1 as $module) {
                if ($module->name === 'receptionist') {
                    $receptionist_status = !empty($module->status) ? true : false;
                }
            }

            if (!$receptionist_status && isset($userList['kiviCare_receptionist'])) {
                unset($userList['kiviCare_receptionist']);
            }
        }

        $login = is_user_logged_in() ? (bool)false : (bool)true;
        $register = (bool)true;
        if( !empty($param) ){
            
            if(isset($param["login"]) || isset($param["register"])){
                $login = (bool)false;
                $register = (bool)false;
            }
            if( !empty($param["login"]) ){
                $login = (bool)$param["login"];
            }
            if( !empty($param["register"]) ){
                $register = (bool)$param["register"];
            }
            if( !empty($param["clinic_id"]) ){
                $clinic_id_param = $param["clinic_id"];
            }
            if( !empty($param["userroles"]) ){
                $userRolesList = explode(",",$param["userroles"]);
                $attr = [];
                if(!empty($userRolesList) && !empty($userList)){
                    foreach($userRolesList as $role){
                        if(!empty($role) && array_key_exists(trim($role),$userList)){
                            $attr[trim($role)] = $userList[trim($role)];
                        }
                    }
                }
                if(!empty($attr)){
                    $userList = $attr;
                }
            }

        }

        

        require KIVI_CARE_DIR . 'app/baseClasses/registerLogin/registerLogin.php';
        return ob_get_clean();
    }

    public function shortcodeScript($type){
        wp_enqueue_style('kc_font_awesome');
        if($type === 'kivicareBookAppointmentWidget'){
            wp_enqueue_style('kc_book_appointment');
            wp_enqueue_script('kc_axios');
            wp_enqueue_script('kc_flatpicker');
            wp_enqueue_style('kc_flatpicker');
            if(kcGetSingleWidgetSetting('widget_print')){
                wp_enqueue_script('kc_print');
            }
            if(isKiviCareProActive()){
                wp_enqueue_style('kc_calendar');
                wp_enqueue_script('kc_calendar');
            }
            do_action('kivicare_enqueue_script','shortcode_enqueue');
        }elseif($type == 'kivicarePopUpBookAppointment'){
            wp_enqueue_style('kc_popup');
            wp_enqueue_script('kc_popup');
            do_action('kivicare_enqueue_script','shortcode_enqueue');
        }elseif ($type === 'kivicareRegisterLogin'){
            wp_enqueue_style('kc_register_login');
            wp_enqueue_script('kc_axios');
            //wp_enqueue_style('kc_book_appointment');
        }else{
            $color = get_option(KIVI_CARE_PREFIX.'theme_color');
            if(!empty($color) && gettype($color) !== 'boolean' && isKiviCareProActive()){
                ?>
                <script> document.documentElement.style.setProperty("--primary-color", '<?php echo esc_js($color);?>');</script>
                <?php
            }
            kcAppendLanguageInHead();
            wp_enqueue_style('kc_front_app_min_style');
            wp_dequeue_style( 'stylesheet' );
            wp_enqueue_script('kc_custom');
            wp_enqueue_script('kc_front_js_bundle');
            do_action('kivicare_enqueue_script','shortcode_enqueue');
        }
    }

    public function sanitizeShortCodeData($param,$type){

        $request_data = (new KCRequest())->getInputs();
        $shortcode_doctor_id = 0;
        $shortcode_clinic_id = 0;
        $shortcode_service_id = 0;
        if( !empty($param['doctor_id'])){
	        $shortcode_doctor_id = $type === 'kivicareBookAppointmentWidget' ? sanitize_text_field($param['doctor_id']) : '"' . sanitize_text_field($param['doctor_id']) . '"';
        }elseif(!empty($request_data['doctor_id'])){
            $shortcode_doctor_id = sanitize_text_field(wp_unslash($request_data['doctor_id']));
        }

        if(!empty($param['clinic_id'])){
            $shortcode_clinic_id = sanitize_text_field($param['clinic_id']);
        }elseif(!empty($request_data['clinic_id'])){
            $shortcode_clinic_id = sanitize_text_field(wp_unslash($request_data['clinic_id']));
        }

        if(!empty($param['service_id'])){
            $shortcode_service_id = sanitize_text_field($param['service_id']);
        }elseif( !empty($request_data['service_id'])){
	        $shortcode_service_id = sanitize_text_field(wp_unslash($request_data['service_id']));
        }
        $data = [
            'doctor' => $shortcode_doctor_id,
            'clinic' => $shortcode_clinic_id,
            'service' => $shortcode_service_id
        ];
        return apply_filters('kivicare_widget_shortcode_parameter',$data);
    }

    public function checkIfPreSelectClinicExists($clinic_id){
        $status = false;
        // condition if clinic id provide in shortcode parameter or $_Get
        if($clinic_id != 0 ){
            $clinic_id = (int)$clinic_id;
            if(!isKiviCareProActive()){
                if($clinic_id != kcGetDefaultClinicId()){
                    $status = true;
                }
            }else{
                $clinic_count = $this->db->get_var("SELECT count(*) FROM {$this->db->prefix}kc_clinics WHERE id = {$clinic_id} AND status = 1");
                if($clinic_count == 0){
                    $status = true;
                }
            }
        }
        return $status;
    }

    public function checkIfPreSelectDoctorExists($doctor_id){
        $args['role'] = $this->getDoctorRole();
        $args['user_status'] = '0';
        $args['fields'] = ['ID'];
        $doctor_id = array_filter(array_map('absint',explode(',',$doctor_id)));
        $doctor_id = !empty($doctor_id) ? $doctor_id : [-1];
        $args['include'] = $doctor_id;
        $allDoctor = get_users($args);
        return !(!empty($allDoctor) && count($allDoctor) > 0);
    }

    public function checkIfPreSelectServiceExists($request_data){
        $shortcode_service_id = (int)$request_data['service'];
        $condition_query = " service_id = $shortcode_service_id AND status = 1 ";
        if($request_data['doctor'] != 0){
            $shortcode_doctor_id = implode(",",array_map('absint',explode(',',$request_data['doctor'])));
            if(!empty($shortcode_doctor_id)){
                $condition_query .= " AND doctor_id IN ($shortcode_doctor_id) ";
            }
        }
        if($request_data['clinic'] != 0){
            $shortcode_clinic_id = implode(",",array_map('absint',explode(',',$request_data['clinic'])));
            if(!empty($shortcode_clinic_id)){
                $condition_query .= " AND clinic_id IN ($shortcode_clinic_id) ";
            }
        }
        $query = "SELECT COUNT(*) FROM {$this->db->prefix}kc_service_doctor_mapping WHERE {$condition_query}";
        $service_list = $this->db->get_var($query);
        return empty($service_list);
    }

    public function checkIfKiviCareAnyDoctorAvailable($doctor_id){
        $status = false;
        $single_doctor = false;
        $args['role'] = $this->getDoctorRole();
        $args['user_status'] = '0';
        $args['fields'] = ['ID'];
        $allDoctor = get_users($args);
        if(empty($allDoctor)){
            $status = true;
        }else if($doctor_id == 0 && count($allDoctor) === 1 ){
            $single_doctor = true;
            foreach ($allDoctor as $doc){
                $doctor_id = $doc->ID;
            }
        }

        return [
            'status' => $status,
            'doctor' => $doctor_id,
            'single' => $single_doctor
        ];
    }

    public function checkIfKiviCareAnyClinicAvailable($clinic_id){
        $status = false;
        $single_clinic = false;
        $clinic_count = $this->db->get_var("SELECT count(*) FROM {$this->db->prefix}kc_clinics WHERE status = 1");

        //if no clinic data is found return
        if($clinic_count == 0){
            $status = true;
        }

        // if proactive and clinic id is not provide in shortcode or $_GET
        if(isKiviCareProActive() && $clinic_id === 0){
            // if only one clinic is available default selected
            if($clinic_count == 1){
                $single_clinic = true;
                $clinic_id = $this->db->get_var("SELECT id FROM {$this->db->prefix}kc_clinics WHERE status = 1");
            }
        }

        if(!isKiviCareProActive()){
            $single_clinic = true;
            $clinic_id = kcGetDefaultClinicId();
        }
        return ['status' => $status , 'clinic' => $clinic_id, 'single' => $single_clinic];

    }

    public function googleRecaptchaload($siteKey){
        $siteKey = esc_js($siteKey);
        echo "<script src='https://www.google.com/recaptcha/api.js?render={$siteKey}'></script>";
    }

    public function checkIfKiviCareAnyServiceAvailable($request_data){
        $service_id = $request_data['service'];
        $single_service = false;
        $status = false;
        $condition_query = ' ';
        if($request_data['doctor'] != 0){
            $shortcode_doctor_id = implode(",",array_map('absint',explode(',',$request_data['doctor'])));
            if(!empty($shortcode_doctor_id)){
                $condition_query .= " AND doctor_id IN ($shortcode_doctor_id) ";
            }
        }
        if($request_data['clinic'] != 0){
            $shortcode_clinic_id = implode(",",array_map('absint',explode(',',$request_data['clinic'])));
            if(!empty($shortcode_clinic_id)){
                $condition_query .= " AND clinic_id IN ($shortcode_clinic_id) ";
            }
        }
        $query = "SELECT * FROM {$this->db->prefix}kc_service_doctor_mapping WHERE status = 1 {$condition_query}";
        $service_list = $this->db->get_results($query);
        if(empty($service_list)){
            $status = true;
        }
        return [
            'status' => $status,
            'service' => $service_id,
        ];
    }
}


