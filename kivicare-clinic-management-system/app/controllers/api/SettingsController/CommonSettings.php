<?php

namespace App\controllers\api\SettingsController;

use App\controllers\api\SettingsController;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class CommonSettings
 * 
 * @package App\controllers\api\SettingsController
 */
class CommonSettings extends SettingsController
{
    private static $instance = null;

    public function __construct()
    {
        parent::__construct();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function changeModuleValueStatus(WP_REST_Request $request): WP_REST_Response
    {
        $request_data = $request->get_json_params()['settings'];
        $rules = [
            'module_type' => 'required',
            'id' => 'required',
            'value' => 'required',
        ];

        $errors = kcValidateRequest($rules, $request_data);
        if (!empty(count($errors))) {
            return $this->response(null, $errors[0], false);
        }
        $request_data['value'] = esc_sql($request_data['value']);
        $request_data['id'] = (int) $request_data['id'];
        $current_user_role = $this->kcbase->getLoginUserRole();
        switch ($request_data['module_type']) {
            case 'static_data':

                $this->db->update($this->db->prefix . 'kc_static_data', ['status' => $request_data['value']], ['id' => $request_data['id']]);
                break;
            case 'custom_field':

                $customFieldTable = $this->db->prefix . 'kc_custom_fields';
                $results = $this->db->get_var("SELECT fields FROM {$customFieldTable} WHERE id={$request_data['id']}");
                if (!empty($results)) {
                    $results = json_decode($results);
                    $results->status = strval($request_data['value']);
                    $this->db->update($customFieldTable, ['status' => $request_data['value'], 'fields' => json_encode($results)], ['id' => $request_data['id']]);
                }
                break;
            case 'doctor_service':


                $this->db->update($this->db->prefix . 'kc_service_doctor_mapping', ['status' => $request_data['value']], ['id' => $request_data['id']]);
                break;
            case 'clinics':


                $this->db->update($this->db->prefix . 'kc_clinics', ['status' => $request_data['value']], ['id' => $request_data['id']]);
                do_action('kcpro_clinic_update', $request_data['id']);
                break;
            case 'doctors':

                $this->db->update($this->db->base_prefix . 'users', ['user_status' => $request_data['value']], ['ID' => $request_data['id']]);
                do_action('kc_doctor_update', $request_data['id']);
                break;
            case 'receptionists':

                $this->db->update($this->db->base_prefix . 'users', ['user_status' => $request_data['value']], ['ID' => $request_data['id']]);
                do_action('kc_receptionist_update', $request_data['id']);
                break;
            case 'patients':

                $this->db->update($this->db->base_prefix . 'users', ['user_status' => $request_data['value']], ['ID' => $request_data['id']]);
                do_action('kc_patient_update', $request_data['id']);
                break;
            case 'tax':

                $this->db->update($this->db->prefix . 'kc_taxes', ['status' => $request_data['value']], ['id' => $request_data['id']]);
                break;
            case 'custom_form':

                $this->db->update($this->db->prefix . 'kc_custom_forms', ['status' => $request_data['value']], ['id' => $request_data['id']]);
                break;
            default:
                do_action('kc_change_module_value_status', $request_data);
                break;
        }
        return $this->response(null, esc_html__('Status Changes Successfully', 'kivicare-clinic-management-system'));
    }
}
