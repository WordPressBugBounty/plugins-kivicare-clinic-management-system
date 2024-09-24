<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCClinic;
use App\models\KCCustomForm;
use App\models\KCCustomFormData;

class KCCustomFormController extends KCBase
{
    public $db;

    private $request;

    /**
     * Class constructor for a custom class.
     */
    public function __construct()
    {
        // Access the global WordPress database object
        global $wpdb;

        // Assign the WordPress database object to the $db property
        $this->db = $wpdb;

        // Create a new instance of the KCRequest class and assign it to the $request property
        $this->request = new KCRequest();

        // Call the constructor of the parent class (assuming this class extends another class)
        parent::__construct();
    }


    public function index()
    {
        // Check permission for listing custom forms
        if (!kcCheckPermission('custom_form_list')) {
            // Send unauthorized access response with HTTP status code 403
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        // Get request data
        $request_data = $this->request->getInputs();

        do_action('kcpro_get_customform_list', $request_data);

        wp_send_json([
            'status' => true,
            'message' => esc_html__('Custom forms records', 'kc-lang'),
            'data' => [],
            'total' => 0
        ]);
    }

    public function save()
    {
        // Check permission for adding custom forms
        if (!kcCheckPermission('custom_form_add')) {
            // Send unauthorized access response with HTTP status code 403
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        // Get request data
        $request_data = $this->request->getInputs();

        do_action('kcpro_custom_form_add', $request_data);

        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }

    public function edit()
    {
        // Check permission for custom form editing
        if (!kcCheckPermission('custom_form_edit')) {
            // Send unauthorized access response with HTTP status code 403
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        // Get request data
        $request_data = $this->request->getInputs();

        do_action('kcpro_custom_form_edit', $request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }

    public function delete()
    {
        // Check permission for custom form deletion
        if (!kcCheckPermission('custom_form_delete')) {
            // Send unauthorized access response with HTTP status code 403
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        // Get request data
        $request_data = $this->request->getInputs();

        do_action('kcpro_custom_form_delete', $request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }

    public function formDataGet()
    {
        // Get request data
        $request_data = $this->request->getInputs();
        do_action('kcpro_get_custom_form_data', $request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }

    public function formDataSave()
    {

        // Get request data
        $request_data = $this->request->getInputs();

        // Define validation rules for request data
        $rules = [
            'form_id' => 'required',
            'module_id' => 'required',
            'form_data' => 'required'
        ];

        // Validate the request data
        $errors = kcValidateRequest($rules, $request_data);

        // Check for validation errors
        if (!empty($errors)) {
            wp_send_json([
                'status' => false,
                'message' => $errors[0] // Assuming you want to send the first error message
            ]);
        }

        do_action('kcpro_custom_form_data_save', $request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }
}
