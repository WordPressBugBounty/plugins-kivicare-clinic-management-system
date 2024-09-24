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
        if ($this->getLoginUserRole() !== $this->getDoctorRole()) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();

        if (!has_filter('kct_save_zoom_telemed_oauth_config')) {
            wp_send_json_error(['message' => "KiviCare Zoom Telemed Required"]);
        }
        $response = apply_filters('kct_save_zoom_telemed_oauth_config', $request_data);

        wp_send_json_success($response);
    }

    public function getZoomTelemedConfig()
    {
        if ($this->getLoginUserRole() !== $this->getDoctorRole()) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();

        if (!has_filter('kct_get_zoom_telemed_oauth_config')) {
            wp_send_json_error(['message' => "KiviCare Telemed Required"]);
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
            wp_send_json_error(['message' => "KiviCare Telemed Required"]);
        }

        $response = apply_filters('kct_generate_doctor_zoom_oauth_token', $request_data);

        wp_send_json_success($response);
    }
    public function disconnectDoctorZoomOauth()
    {
        if ($this->getLoginUserRole() !==  $this->getDoctorRole()) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();

        if (!has_filter('kct_disconnect_doctor_zoom_oauth')) {
            wp_send_json_error(['message' => "KiviCare Telemed Required"]);
        }
        $response = apply_filters('kct_disconnect_doctor_zoom_oauth', $request_data);

        wp_send_json_success($response);
    }
}
