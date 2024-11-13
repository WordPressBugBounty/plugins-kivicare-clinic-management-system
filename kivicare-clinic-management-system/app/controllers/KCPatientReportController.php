<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCPatientReport;
use App\models\KCUser;

class KCPatientReportController extends KCBase
{

    public $db;

    private $request;

    public function __construct() {

        global $wpdb;

        $this->db = $wpdb;

        $this->request = new KCRequest();

        parent::__construct();

    }

    public function uploadPatientReport(){
        if ( ! kcCheckPermission( 'patient_report_add' ) ) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
	    $request_data = $this->request->getInputs();
	    if(! (new KCUser())->patientPermissionUserWise($request_data['patient_id'])){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $response = apply_filters('kcpro_upload_patient_report', [
            'upload_data' => $request_data
        ]);
	    wp_send_json($response);

    }
    public function getPatientReport(){
        if ( ! kcCheckPermission( 'patient_report' ) ) {
            wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();
        
		$patient_id = (int)$request_data['patinet']??0;
		if(!empty($data['report_id'])){
            $patient_id = (new KCPatientReport())->get_var(['id' => (int)$data['report_id'] ],'patient_id');
		}
		if(! (new KCUser())->patientPermissionUserWise($patient_id)){
            wp_send_json(kcUnauthorizeAccessResponse(403));
		}
        $response = apply_filters('kcpro_get_patient_report', [
            'pid'=>(int)$request_data['patinet']??0,
            'report_id' => (int)$request_data['report_id']
        ]);
	    wp_send_json($response);
    }
    public function viewPatientReport(){
        if ( ! kcCheckPermission( 'patient_report_view' ) ) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
	    $request_data = $this->request->getInputs();
	    if(! (new KCUser())->patientPermissionUserWise($request_data['patient_id'])){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $response = apply_filters('kcpro_view_patient_report', [
            'pid'=>(int)$request_data['patient_id'],
            'docid'=>(int)$request_data['doc_id']
        ]);
	    wp_send_json($response);
    }
    public function deletePatientReport(){
        if ( ! kcCheckPermission( 'patient_report_delete' ) ) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $request_data = $this->request->getInputs();
	    $patient_id = (new KCPatientReport())->get_var(['id' => (int)$request_data['id'] ],'patient_id');
	    if(! (new KCUser())->patientPermissionUserWise($patient_id)){
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
        $response = apply_filters('kcpro_delete_patient_report', [
            'report_id'=>(int)$request_data['id'],
            'upload_report' => (int)$request_data['upload_report']
        ]);
	    wp_send_json($response);
    }
    public function patientReportMail(){
        $request_data = $this->request->getInputs();
        $status = false;
        $message = esc_html__('Failed to send report', 'kc-lang');

        if( !empty($request_data['data']) && is_array($request_data['data']) && !empty($request_data['patient_id'])){

	        if(! (new KCUser())->patientPermissionUserWise($request_data['patient_id'])){
		        wp_send_json(kcUnauthorizeAccessResponse(403));
	        }
            global $wpdb;
            $patient_report = collect($request_data['data'])->pluck('upload_report')->toArray();
            if(!empty($patient_report) && count($patient_report) > 0){
                $user_email = $wpdb->get_var('select user_email from '.$wpdb->base_prefix.'users where ID='.(int)$request_data['patient_id']);
                $patient_report = array_map(function ($v){
                    return get_attached_file($v);
                },$patient_report);
                $data = [
                    'user_email' => $user_email != null ? $user_email : '',
                    'attachment_file' => $patient_report,
                    'attachment' => true,
                    'email_template_type' => 'patient_report'
                ];

                $status = kcSendEmail($data);
                $message = $status ? esc_html__('Report sent successfully', 'kc-lang') : esc_html__('Failed to send report', 'kc-lang');
            }
        }
        $response = [
            'status' => $status,
            'message' => $message,
        ];
	    wp_send_json($response);
    }
    public function editPatientReport()
    {
        if(!current_user_can(KIVI_CARE_PREFIX.'patient_report_edit')){
            wp_send_json_error(esc_html__('you do not have permission to edit report', 'kc-lang'), 403);
        }

		$request_data = $this->request->getInputs();
	    $patient_id = (new KCPatientReport())->get_var(['id' => (int)$request_data['id'] ],'patient_id');
	    if(! (new KCUser())->patientPermissionUserWise($patient_id)){
		    kcUnauthorizeAccessResponse(403);
	    }
        do_action('kcpro_edit_patient_report',$request_data);
        wp_send_json_error(esc_html__('This API Is Only For KiviCare Pro', 'kc-lang'), 403);
    }
}