<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCAppointment;
use App\models\KCService;
use App\models\KCServiceDoctorMapping;
use App\models\KCUser;

class KCZoomTelemedController extends KCBase
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

    public function saveDoctorServeroauthConfiguration() {
        if($this->getLoginUserRole() !== 'administrator'){
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();
        $response = apply_filters('kct_save_serveroauth_configuration', ['data'=>$request_data]);
	    wp_send_json($response);

    }
    
    private function getZoomAccessToken($accountId, $clientId, $clientSecret) {
        $url = "https://zoom.us/oauth/token";
        $data = [
            "grant_type" => "account_credentials",
            "account_id" => $accountId
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic " . base64_encode($clientId . ":" . $clientSecret),
            "Content-Type: application/x-www-form-urlencoded"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
    

    public function getServerOauthConfig() {
        if ( $this->getLoginUserRole() !== 'administrator') {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $response = apply_filters('kct_get_server_oauth_config', []);
        wp_send_json($response);
    }
    public function saveZoomConfiguration()
    {

        $is_permission = kcCheckPermission('doctor_edit') || kcCheckPermission('doctor_view') || kcCheckPermission('doctor_profile');

        if (!$is_permission) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $request_data = $this->request->getInputs();

        $request_data['enableTeleMed'] = in_array((string)$request_data['enableTeleMed'], ['true', '1']) ? 'true' : 'false';

        $rules = [
            'api_key' => 'required',
            'api_secret' => 'required',
            'doctor_id' => 'required',
        ];

        $errors = kcValidateRequest($rules, $request_data);

        if (count($errors)) {
            wp_send_json([
                'status' => false,
                'message' => $errors[0]
            ]);
        }


        $request_data['doctor_id'] = (int)$request_data['doctor_id'];

        if (!(new KCUser())->doctorPermissionUserWise($request_data['doctor_id'])) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        //save zoom configuration
        $response = apply_filters('kct_save_zoom_configuration', [
            'user_id' => $request_data['doctor_id'],
            'enableTeleMed' => $request_data['enableTeleMed'],
            'api_key' => $request_data['api_key'],
            'api_secret' => $request_data['api_secret']
        ]);

        //if zoom configuration save successfull
        if (!empty($response['status'])) {

            if ($request_data['enableTeleMed'] === 'true') {
                //change googlemeet value if zoom telemed enable
                update_user_meta($request_data['doctor_id'], KIVI_CARE_PREFIX . 'google_meet_connect', 'off');
            }

            wp_send_json([
                'status' => true,
                'message' => esc_html__("Telemed key successfully saved.", 'kc-lang'),
            ]);
        } else {
            wp_send_json([
                'status' => false,
                'message' => esc_html__("Failed to save Telemed key, please check API key and API Secret", 'kc-lang'),
            ]);
        }
    }

    public function getZoomConfiguration()
    {
        $is_permission = kcCheckPermission('doctor_edit') || kcCheckPermission('doctor_view') || kcCheckPermission('doctor_profile');

        if (!$is_permission) {
            wp_send_json(kcUnauthorizeAccessResponse());
        }
        $request_data = $this->request->getInputs();

        $request_data['user_id'] = (int)$request_data['user_id'];
        if (!(new KCUser())->doctorPermissionUserWise($request_data['user_id'])) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $response = apply_filters('kct_get_zoom_configuration', [
            'user_id' => $request_data['user_id'],
        ]);

        //get doctor based zoom configuration data
        wp_send_json([
            'status' => true,
            'message' => esc_html__("Configuration data", 'kc-lang'),
            'data' => !empty($response['data']) ? $response['data'] : []
        ]);
    }

    public function saveZoomServerToServerOauthConfiguration()
    {

        $is_permission = kcCheckPermission('doctor_edit') || kcCheckPermission('doctor_view') || kcCheckPermission('doctor_profile');

        if (!$is_permission) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $request_data = $this->request->getInputs();

        $request_data['enableServerToServerOauthconfig'] = in_array((string)$request_data['enableServerToServerOauthconfig'], ['Yes']) ? 'true' : 'false';

        $rules = [
            'account_id' => 'required',
            'client_id' => 'required',
            'client_secret' => 'required',
            'doctor_id' => 'required',
        ];

        $errors = kcValidateRequest($rules, $request_data);

        if (count($errors)) {
            wp_send_json([
                'status' => false,
                'message' => $errors[0]
            ]);
        }

        $request_data['doctor_id'] = (int)$request_data['doctor_id'];

        if (!(new KCUser())->doctorPermissionUserWise($request_data['doctor_id'])) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        //save zoom configuration
        $response = apply_filters('kct_save_zoom_server_to_server_oauth_configuration', [
            'user_id' => !empty($request_data['doctor_id']) ? $request_data['doctor_id'] : 0,
            'enableServerToServerOauthconfig' => isset($request_data['enableServerToServerOauthconfig']) & !empty($request_data['enableServerToServerOauthconfig']) ? $request_data['enableServerToServerOauthconfig'] : 'No',
            'account_id' => !empty($request_data['account_id']) ? trim($request_data['account_id']) : 0,
            'client_id' => !empty($request_data['client_id']) ? trim($request_data['client_id']) : 0,
            'client_secret' => !empty($request_data['client_secret']) ? trim($request_data['client_secret']) : 0

        ]);

        wp_send_json($response);

    }

    public function getZoomServerToServerOauthConfiguration()
    {
        $is_permission = kcCheckPermission('doctor_edit') || kcCheckPermission('doctor_view') || kcCheckPermission('doctor_profile');

        if (!$is_permission) {
            wp_send_json(kcUnauthorizeAccessResponse());
        }
        $request_data = $this->request->getInputs();

        $request_data['user_id'] = (int)$request_data['user_id'];
        if (!(new KCUser())->doctorPermissionUserWise($request_data['user_id'])) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $response = apply_filters('kct_get_zoom_server_to_server_oaut_configuration', [
            'user_id' => $request_data['user_id'],
        ]);

        //get doctor based zoom configuration data
        wp_send_json([
            'status' => true,
            'message' => esc_html__("Zoom Server To Server Oauth Configuration data", 'kc-lang'),
            'data' => !empty($response['data']) ? $response['data'] : []
        ]);
    }

    public function resendZoomLink()
    {

        if (!kcCheckPermission('appointment_add')) {
            wp_send_json(kcUnauthorizeAccessResponse());
        }
        //resend video appointment meeting link
        $request_data = $this->request->getInputs();

        if (!((new KCAppointment())->appointmentPermissionUserWise($request_data['id']))) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        //googlemeet send google meet link
        if (isKiviCareGoogleMeetActive() && kcCheckDoctorTelemedType($request_data['id']) == 'googlemeet') {
            $res_data = apply_filters('kcgm_save_appointment_event_link_resend', $request_data);
        } else {
            //send zoom meet link
            $res_data = apply_filters('kct_send_resend_zoomlink', $request_data);
        }
        if ($res_data) {
            wp_send_json([
                'status'  => true,
                'message' => esc_html__('Video Conference Link Send', 'kc-lang'),
                'data'    => $res_data,
            ]);
        } else {
            wp_send_json([
                'status'  => false,
                'message' => esc_html__('Video Conference Link not Send', 'kc-lang'),
                'data'    => $res_data,
            ]);
        }
    }

    public function saveOauthConfig()
    {
        if ($this->getLoginUserRole() !== 'administrator') {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();

        if (!has_filter('kct_save_zoom_telemed_oauth_config')) {
            wp_send_json_error(['message' => esc_html__("KiviCare Zoom Telemed Required", 'kc-lang')]);
        }
        $response = apply_filters('kct_save_zoom_telemed_oauth_config', $request_data);

        wp_send_json_success($response);
    }

    public function getZoomTelemedConfig()
    {
        if (!in_array($this->getLoginUserRole(),['administrator',$this->getDoctorRole()])) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();

        if (!has_filter('kct_get_zoom_telemed_oauth_config')) {
            wp_send_json_error(['message' => esc_html__("KiviCare Telemed Required", 'kc-lang')]);
        }
        $response = apply_filters('kct_get_zoom_telemed_oauth_config', $request_data);
        wp_send_json_success($response);
    }

    public function connectAdminZoomOauth()
    {
        if ($this->getLoginUserRole() !== 'administrator') {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();

        if (!has_filter('kct_connect_admin_zoom_oauth')) {
            wp_send_json_error(['message' => esc_html__("KiviCare Telemed Required", 'kc-lang')]);
        }
        $response = apply_filters('kct_connect_admin_zoom_oauth', $request_data);

        wp_send_json_success($response);
    }

    public function connectDoctorServerOauth()
    {
        if ($this->getLoginUserRole() !==  $this->getDoctorRole()) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        if (!has_filter('kct_save_zoom_telemed_oauth_config')) {
            wp_send_json_error(['message' => esc_html__("KiviCare Telemed Required", 'kc-lang')]);
        }
        $response = apply_filters('kct_save_zoom_telemed_oauth_config', $this->request->getInputs());

        wp_send_json_success($response);
        
    }

    public function getDoctorTelemedConfig()
    {
        if ($this->getLoginUserRole() !==  $this->getDoctorRole()) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        global $wpdb;
        $current_user = wp_get_current_user();
        $doctor_id = $current_user->ID;

        // Query the wp_users table to find all administrators
        $admin_users = $wpdb->get_results("
            SELECT u.ID 
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = '{$wpdb->prefix}capabilities' 
            AND um.meta_value LIKE '%administrator%'
        ");

        if (empty($admin_users)) {
            wp_send_json_error(['message' => 'No administrators found.']);
        }
        $admin_user_id = $admin_users[0]->ID;
        $cred = get_option(KIVI_CARE_TELEMED_PREFIX . 'zoom_telemed_setting');
        $request_data = [
            'data' => $cred
        ];

        if (!has_filter('kct_get_zoom_telemed_oauth_config')) {
            wp_send_json_error(['message' => esc_html__("KiviCare Telemed Required", 'kc-lang')]);
        }
        $response = apply_filters('kct_get_zoom_telemed_oauth_config', $request_data);
        wp_send_json_success($response);
    }

    public function generateDoctorZoomOauthToken()
    {
        if ($this->getLoginUserRole() !==  $this->getDoctorRole()) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();

        if (!has_filter('kct_generate_doctor_zoom_oauth_token')) {
            wp_send_json_error(['message' => esc_html__("KiviCare Telemed Required", 'kc-lang')]);
        }

        $response = apply_filters('kct_generate_doctor_zoom_oauth_token', $request_data);

        wp_send_json_success($response);
    }

    public function generateDoctorServerOauthCode()
    {
        if ($this->getLoginUserRole() !==  $this->getDoctorRole()) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $request_data = $this->request->getInputs();
        if (!has_filter('kct_generate_doctor_server_oauth_token')) {
            wp_send_json_error(['message' => esc_html__("KiviCare Telemed Required", 'kc-lang')]);
        }

        $response = apply_filters('kct_generate_doctor_server_oauth_token', $request_data);

        wp_send_json_success($response);

        
    }

    public function disconnectDoctorZoomOauth()
    {
        if ($this->getLoginUserRole() !==  $this->getDoctorRole()) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();

        if (!has_filter('kct_disconnect_doctor_zoom_oauth')) {
            wp_send_json_error(['message' => esc_html__("KiviCare Telemed Required", 'kc-lang')]);
        }
        $response = apply_filters('kct_disconnect_doctor_zoom_oauth', $request_data);

        wp_send_json_success($response);
    }

    public function disconnectDoctorServerOauth()
    {
        if ($this->getLoginUserRole() !==  $this->getDoctorRole()) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();

        if (!has_filter('kct_disconnect_doctor_server_oauth')) {
            wp_send_json_error(['message' => esc_html__("KiviCare Telemed Required", 'kc-lang')]);
        }
        $response = apply_filters('kct_disconnect_doctor_server_oauth', $request_data);

        wp_send_json_success($response);
    }

    public function saveServerToServerOauthStatus()
    {
        if ($this->getLoginUserRole() !== 'administrator') {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();
        
        if (!has_filter('kct_save_zoom_telemed_oauth_config')) {
            wp_send_json_error(['message' => esc_html__("KiviCare Zoom Telemed Required", 'kc-lang')]);
        }
        $response = apply_filters('kct_save_zoom_telemed_server_to_server_oauth_status', $request_data);

        wp_send_json_success($response);
    }

    public function disconnectDoctorZoomServerToServerOauth()
    {
        if ($this->getLoginUserRole() !==  $this->getDoctorRole()) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();

        if (!has_filter('kct_disconnect_doctor_server_to_server_oauth')) {
            wp_send_json_error(['message' => esc_html__("KiviCare Telemed Required", 'kc-lang')]);
        }
        $response = apply_filters('kct_disconnect_doctor_server_to_server_oauth', $request_data);

        wp_send_json_success($response);
    }
 
}
