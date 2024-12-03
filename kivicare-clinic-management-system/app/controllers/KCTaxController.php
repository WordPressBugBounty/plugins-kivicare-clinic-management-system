<?php

namespace app\controllers;
use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCTax;
use App\models\KCPatientEncounter;
use Exception;

class KCTaxController extends KCBase{

    /**
     * @var object $db The WordPress database object.
     * @var KCRequest $request The instance of KCRequest class for handling HTTP requests.
     * @var string $filter_not_found_message The message indicating the status of the KiviCare Pro plugin.
     */
    private $db;
    private $request;
    private $filter_not_found_message;

    /**
     * Class constructor.
     */
    public function __construct() {

        global $wpdb;

        $this->db = $wpdb;

        $this->request = new KCRequest();

        // Set the filter_not_found_message based on the activation status of KiviCare Pro plugin
        if ( isKiviCareProActive() ) {
            $this->filter_not_found_message = esc_html__( "Please update kiviCare pro plugin", "kc-lang" );
        } else {
            $this->filter_not_found_message = esc_html__( "Please install kiviCare pro plugin", "kc-lang" );
        }
    }

    /**
     * Retrieve tax records.
     */
    public function index() {

        // Check current login user permission
        if ( ! kcCheckPermission( 'tax_list' ) ) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        // Get request data
        $request_data = $this->request->getInputs();

        // Send JSON response with tax list action filtered
        wp_send_json( apply_filters('kivicare_tax_list', [
            'status'     => false,
            'message'    => $this->filter_not_found_message,
            'data'       => [],
            'total_rows' => 0
        ] ,$request_data ));
    }


    /**
     * Save tax record.
     */
    public function save() {

        // Check current login user permission
        if ( ! kcCheckPermission( 'tax_add' ) ) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        // Get request data
        $request_data = $this->request->getInputs();

        $rules = [
            'name' => 'required',
            'tax_type' => 'required',
            'tax_value' => 'required',
            'status' => 'required',
        ];

        // Validate request data against defined rules
        $errors = kcValidateRequest( $rules, $request_data );

        if ( count( $errors ) ) {
            wp_send_json( [
                'status'  => false,
                'message' => $errors[0]
            ] );
        }

        // Send JSON response with save action filtered
        wp_send_json( apply_filters('kivicare_tax_save', [
            'status'     => false,
            'message'    => $this->filter_not_found_message,
            'data'       => [],
        ] ,$request_data ));
    }


    /**
     * Edit tax record.
     */
    public function edit() {

        // Check current login user permission
        if ( ! kcCheckPermission( 'tax_edit' ) ) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        // Get request data
        $request_data = $this->request->getInputs();

        try {

            // Check if 'id' is set in the request data
            if ( ! isset( $request_data['id'] ) ) {
                wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
            }

            if(!(new KCTax())->checkUserRoleWisePermission($request_data['id'])){
                wp_send_json(kcUnauthorizeAccessResponse(403));
            }
            // Send JSON response with edit action filtered
            wp_send_json( apply_filters('kivicare_tax_edit', [
                'status'     => false,
                'message'    => $this->filter_not_found_message,
                'data'       => [],
            ] ,$request_data ));

        } catch ( Exception $e ) {

            $code    = $e->getCode();
            $message = $e->getMessage();

            header( "Status: $code $message" );

            // Send JSON response with error message
            wp_send_json( [
                'status'  => false,
                'message' => $message
            ] );
        }
    }


    /**
     * Delete tax record.
     */
    public function delete() {

        // Check current login user permission
        if ( ! kcCheckPermission( 'tax_delete' ) ) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        // Get request data
        $request_data = $this->request->getInputs();

        try {

            // Check if 'id' is set in the request data
            if ( ! isset( $request_data['id'] ) ) {
                wp_send_json(kcThrowExceptionResponse( esc_html__('Data not found', 'kc-lang'), 400 ));
            }


            if(!(new KCTax())->checkUserRoleWisePermission($request_data['id'])){
                wp_send_json(kcUnauthorizeAccessResponse(403));
            }

            // Send JSON response with delete action filtered
            wp_send_json( apply_filters('kivicare_tax_delete', [
                'status'     => false,
                'message'    => $this->filter_not_found_message,
                'data'       => [],
            ] ,$request_data ));

        } catch ( Exception $e ) {

            $code    = $e->getCode();
            $message = $e->getMessage();

            header( "Status: $code $message" );

            // Send JSON response with error message
            wp_send_json( [
                'status'  => false,
                'message' => $message
            ] );
        }
    }

    /**
     * Get tax data based on provided input parameters.
     *
     * @return void
     */
    public function getTaxData() {
        // Get request data
        $request_data = $this->request->getInputs();

        // Determine clinic and user roles
        $current_user_role = $this->getLoginUserRole();
        if ($current_user_role == $this->getClinicAdminRole()) {
            $request_data['clinic_id']['id'] = kcGetClinicIdOfClinicAdmin();
        } elseif ($current_user_role == $this->getReceptionistRole()) {
            $request_data['clinic_id']['id'] = kcGetClinicIdOfReceptionist();
        }

        // Check for required data
        if (empty($request_data['clinic_id']['id']) || empty($request_data['doctor_id']['id']) || empty($request_data['visit_type'])) {
            wp_send_json([
                'status' => false,
                'message' => esc_html__("required data missing", "kc-lang")
            ]);
        }

        // Handle visit type data
        if (empty(array_filter($request_data['visit_type'], 'is_array'))) {
            $request_data['visit_type'] = [$request_data['visit_type']];
        }

        // Extract service IDs
        $service_ids = collect($request_data['visit_type'])
                        ->pluck('service_id')
                        ->map(function ($id) {
                            return (int)$id;
                        })
                        ->toArray();
        $implode_service_ids = implode(",", $service_ids);
        $request_data['clinic_id']['id'] = (int) $request_data['clinic_id']['id'];
        $request_data['doctor_id']['id'] = (int) $request_data['doctor_id']['id'];

        // Send JSON response with calculated tax data
        wp_send_json(apply_filters('kivicare_calculate_tax', [
            'status' => false,
            'message' => $this->filter_not_found_message,
            'data' => [],
            'total_tax' => 0
        ], [
            "id" => !empty($request_data['id']) ? $request_data['id'] : '',
            "type" => 'appointment',
            "doctor_id" => $request_data['doctor_id']['id'],
            "clinic_id" => $request_data['clinic_id']['id'],
            "service_id" => $service_ids,
            "total_charge" => $this->db->get_var("SELECT SUM(charges) FROM {$this->db->prefix}kc_service_doctor_mapping
                                        WHERE doctor_id = {$request_data['doctor_id']['id']} AND  clinic_id = {$request_data['clinic_id']['id']} 
                                         AND service_id IN ({$implode_service_ids}) "),
            'extra_data' => $request_data
        ]));
    }


    /**
     * Get encounter tax data based on provided input parameters.
     *
     * @return void
     */
    public function getEncounterTaxData() {
        
        $request_data = $this->request->getInputs();

        // Check encounter permission for the user
        if (!((new KCPatientEncounter())->encounterPermissionUserWise($request_data['encounter_id']))) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        // Get encounter details
        $encounter_details = (new KCPatientEncounter())->get_by(['id' => $request_data['encounter_id']], '=', true);

        if (empty($encounter_details)) {
            wp_send_json(['status' => false, 'message' => esc_html__("encounter detail not found", "kc-lang")]);
        }

        // Check for required data
        if (empty($encounter_details->clinic_id) || empty($encounter_details->doctor_id) || empty($request_data['billItems'])) {
            wp_send_json([
                'status' => false,
                'message' => esc_html__("required data missing", "kc-lang")
            ]);
        }

        // Extract service IDs and calculate service count
        $service_ids = collect($request_data['billItems'])->pluck('item_id')->pluck('id')->toArray();
        $count_service = [];
        foreach ($request_data['billItems'] as $bill) {
            for ($i = 0; $i < $bill['qty']; $i++) {
                $count_service[$bill['item_id']['id']][] = [
                    'price' => $bill['price']
                ];
            }
        }

        // Send JSON response with calculated encounter tax data
        wp_send_json(apply_filters('kivicare_calculate_tax', [
            'status' => false,
            'message' => $this->filter_not_found_message,
            'data' => [],
            'total_tax' => 0
        ], [
            'data' => [],
            "id" => '',
            "type" => 'encounter',
            "doctor_id" => $encounter_details->doctor_id,
            "clinic_id" => $encounter_details->clinic_id,
            "service_id" => $service_ids,
            "service_count" => $count_service,
            "total_charge" => collect($request_data['billItems'])->sum('total'),
            'extra_data' => $request_data
        ]));
    }

}