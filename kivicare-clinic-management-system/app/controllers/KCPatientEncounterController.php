<?php

namespace App\Controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCAppointment;
use App\models\KCBill;
use App\models\KCCustomField;
use App\models\KCPatientEncounter;
use App\models\KCReceptionistClinicMapping;
use App\models\KCClinic;
use DateTime;
use Exception;
use Dompdf\Dompdf;

class KCPatientEncounterController extends KCBase
{

	public $db;

	/**
	 * @var KCRequest
	 */
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

		$is_permission = false;

		if (kcCheckPermission('patient_encounter_list')) {
			$is_permission = true;
		}

		if (!$is_permission) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();
		if (!isset($request_data['login_id'])) {
			wp_send_json([
				'status' => false,
				'message' => __('Patient id not found', 'kc-lang'),
				'data' => []
			]);
		}

		$login_id = get_current_user_id();
		$patient_encounter_table = $this->db->prefix . 'kc_patient_encounters';
		$clinics_table = $this->db->prefix . 'kc_clinics';
		$users_table = $this->db->base_prefix . 'users';

		$current_user_login_role = $this->getLoginUserRole();
		$patient_user_condition = $doctor_user_condition = $clinic_condition = $search_condition = '';
		$orderByCondition = " ORDER BY {$patient_encounter_table}.id DESC ";
		$paginationCondition = ' ';
		if ((int) $request_data['perPage'] > 0) {
			$perPage = (int) $request_data['perPage'];
			$offset = ((int) $request_data['page'] - 1) * $perPage;
			$paginationCondition = " LIMIT {$perPage} OFFSET {$offset} ";
		}

		if (!empty($request_data['sort'])) {
			$request_data['sort'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($request_data['sort'][0]), true));
			if (!empty($request_data['sort']['field']) && !empty($request_data['sort']['type']) && $request_data['sort']['type'] !== 'none') {
				$sortField = sanitize_sql_orderby($request_data['sort']['field']);
				$sortByValue = sanitize_sql_orderby(strtoupper($request_data['sort']['type']));
				switch ($request_data['sort']['field']) {
					case 'status':
					case 'encounter_date':
					case 'id':
						$orderByCondition = " ORDER BY {$patient_encounter_table}.{$sortField} {$sortByValue} ";
						break;
					case 'doctor_name':
						$orderByCondition = " ORDER BY doctors.display_name {$sortByValue} ";
						break;
					case 'clinic_name':
						$orderByCondition = " ORDER BY {$clinics_table}.name {$sortByValue} ";
						break;
					case 'patient_name':
						$orderByCondition = " ORDER BY patients.display_name {$sortByValue} ";
						break;
				}

				$orderByCondition = apply_filters('kivicare_orderby_query_filter', $search_condition, $sortByValue, $sortField, 'encounter');
			}
		}

		if (isset($request_data['searchTerm']) && trim($request_data['searchTerm']) !== '') {
			$request_data['searchTerm'] = esc_sql(strtolower(trim($request_data['searchTerm'])));

            $status=null;
            // Extract status using regex
            if (preg_match('/:(active|inactive)/i', $request_data['searchTerm'], $matches)) {
                $status = $matches[1]=='active'?'1':'0';
                // Remove the matched status from the search term and trim
                $request_data['searchTerm'] = trim( preg_replace('/:(active|inactive)/i', '', $request_data['searchTerm']));
            }

			$search_condition .= " AND (
                           {$patient_encounter_table}.id LIKE '%{$request_data['searchTerm']}%' 
                           OR {$clinics_table}.name LIKE '%{$request_data['searchTerm']}%' 
                           OR doctors.display_name LIKE '%{$request_data['searchTerm']}%' 
                           OR patients.display_name LIKE '%{$request_data['searchTerm']}%'  
                           ) ";
			if(!is_null($status)){
				$search_condition.= " AND {$patient_encounter_table}.status LIKE '{$status}' ";
			}
		} else {
			if (!empty($request_data['columnFilters'])) {
				$request_data['columnFilters'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($request_data['columnFilters']), true));
				foreach ($request_data['columnFilters'] as $column => $searchValue) {
					$searchValue = $column !== 'encounter_date' ? esc_sql(strtolower(trim($searchValue))) : $searchValue;
					$column = esc_sql($column);
					if ($searchValue === '') {
						continue;
					}
					switch ($column) {
						case 'status':
						case 'id':
							$search_condition .= " AND {$patient_encounter_table}.{$column} LIKE '%{$searchValue}%' ";
							break;
						case 'doctor_name':
							$search_condition .= " AND doctors.display_name LIKE '%{$searchValue}%' ";
							break;
						case 'clinic_name':
							$search_condition .= " AND {$clinics_table}.name LIKE '%{$searchValue}%' ";
							break;
						case 'patient_name':
							$search_condition .= " AND patients.display_name LIKE '%{$searchValue}%'";
							break;
						case 'encounter_date':
							if (!empty($searchValue['start']) && !empty($searchValue['end'])) {
								$searchValue['start'] = esc_sql(strtolower(trim($searchValue['start'])));
								$searchValue['end'] = esc_sql(strtolower(trim($searchValue['end'])));
								$search_condition .= " AND CAST({$patient_encounter_table}.{$column} AS DATE )  BETWEEN '{$searchValue['start']}' AND '{$searchValue['end']}' ";
							}
							break;
					}

					$search_condition = apply_filters('kivicare_search_query_filter', $search_condition, $searchValue, $column, 'encounter');
				}
			}
		}
		if ($this->getPatientRole() === $current_user_login_role) {
			$patient_upcoming = isset($request_data['type']) && $request_data['type'] === 'upcoming' ? " AND {$patient_encounter_table}.encounter_date  >= CURDATE()" : '';
			$patient_user_condition = " AND {$patient_encounter_table}.patient_id = {$login_id} {$patient_upcoming}";
		}

		if (!empty($request_data['patient_id']) && $request_data['patient_id'] > 0) {
			$request_data['patient_id'] = (int) $request_data['patient_id'];
			$patient_user_condition = " AND {$patient_encounter_table}.patient_id = {$request_data['patient_id']} ";
		}

		if ($this->getDoctorRole() === $current_user_login_role) {
			$doctor_user_condition = " AND {$patient_encounter_table}.doctor_id = {$login_id} ";
		}

		if ($this->getClinicAdminRole() === $current_user_login_role) {
			$clinic_condition = " AND {$patient_encounter_table}.clinic_id=" . kcGetClinicIdOfClinicAdmin();
		}

		if ($this->getReceptionistRole() === $current_user_login_role) {
			$clinic_condition = " AND {$patient_encounter_table}.clinic_id = " . kcGetClinicIdOfReceptionist();
		}


		$common_query = " FROM  {$patient_encounter_table}
		       LEFT JOIN {$users_table} doctors
		              ON {$patient_encounter_table}.doctor_id = doctors.id
		       LEFT JOIN {$users_table} patients
		              ON {$patient_encounter_table}.patient_id = patients.id
		       LEFT JOIN {$clinics_table}
		              ON {$patient_encounter_table}.clinic_id = {$clinics_table}.id
            WHERE 0 = 0  {$patient_user_condition}  {$doctor_user_condition}  {$clinic_condition} {$search_condition} ";

		$encounters = $this->db->get_results("SELECT {$patient_encounter_table}.*,
		       doctors.display_name  AS doctor_name,
		       patients.display_name AS patient_name,
		       {$clinics_table}.name AS clinic_name 
			  {$common_query} {$orderByCondition} {$paginationCondition} ");

		if (!count($encounters)) {
			wp_send_json([
				'status' => false,
				'message' => esc_html__('No encounter found', 'kc-lang'),
				'data' => []
			]);
		}

		$total = $this->db->get_var("SELECT count(*) {$common_query} ");
		$custom_form_appointment = apply_filters('kivicare_custom_form_list', [], ['type' => 'appointment_module']);
		$custom_form_appointment = array_filter($custom_form_appointment, function ($v) {
			return !empty($v->show_mode) && in_array('encounter', $v->show_mode);
		});
		$custom_forms = array_merge($custom_form_appointment, apply_filters('kivicare_custom_form_list', [], ['type' => 'patient_encounter_module']));
		array_walk($encounters, function (&$encounter) use ($custom_forms) {
			$encounter->clinic_name = decodeSpecificSymbols($encounter->clinic_name);
			$encounter->custom_forms = $custom_forms;
			$encounter->encounter_date = kcGetFormatedDate($encounter->encounter_date);
            $encounter->encounter_edit_after_close_status = get_option(KIVI_CARE_PREFIX . 'encounter_edit_after_close_status', 'off') === 'on';
		});

		wp_send_json([
			'status' => true,
			'message' => esc_html__('Encounter list', 'kc-lang'),
			'data' => $encounters,
			'total_rows' => $total,
			"clinic_extra" => kcGetClinicCurrenyPrefixAndPostfix()
		]);
	}

	public function save()
	{

		$is_permission = false;

		if (kcCheckPermission('patient_encounter_add')) {
			$is_permission = true;
		}

		if (!$is_permission) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		if (isKiviCareProActive()) {
			if ($this->getLoginUserRole() == $this->getClinicAdminRole()) {
				$request_data['clinic_id']['id'] = kcGetClinicIdOfClinicAdmin();
			} elseif ($this->getLoginUserRole() == $this->getReceptionistRole()) {
				$request_data['clinic_id']['id'] = kcGetClinicIdOfReceptionist();
			}

			$patient_id = isset($request_data['patient_id']['id']) ? (int) $request_data['patient_id']['id'] : (int) $request_data['patient_id'];

			global $wpdb;
			$clinic_id = $wpdb->get_var("SELECT clinic_id FROM {$wpdb->prefix}kc_patient_clinic_mappings WHERE patient_id={$patient_id}");
			if (isKiviCareProActive()) {
				$request_data['clinic_id']['id'] = !empty($clinic_id) ? (int) $clinic_id : 0;
			} else {
				$request_data['clinic_id']['id'] = kcGetDefaultClinicId();
			}
		}

		$rules = [
			'date' => 'required|date',
			'patient_id' => 'required',
			'status' => 'required',

		];

		$message = [
			'status' => esc_html__('Status is required', 'kc-lang'),
			'patient_id' => esc_html__('Patient is required', 'kc-lang'),
			'doctor_id' => esc_html__('Doctor is required', 'kc-lang'),
		];

		$errors = kcValidateRequest($rules, $request_data, $message);

		if (count($errors)) {
			wp_send_json([
				'status' => false,
				'message' => $errors[0]
			]);
		}
		$temp = [
			'encounter_date' => date('Y-m-d', strtotime($request_data['date'])),
			'patient_id' => isset($request_data['patient_id']['id']) ? (int) $request_data['patient_id']['id'] : (int) $request_data['patient_id'],
			'clinic_id' => isset($request_data['clinic_id']['id']) ? (int) $request_data['clinic_id']['id'] : 1,
			'doctor_id' => isset($request_data['doctor_id']['id']) ? (int) $request_data['doctor_id']['id'] : (int) $request_data['doctor_id'],
			'description' => $request_data['description'],
			'status' => $request_data['status'],
		];

		if ($this->getLoginUserRole() === $this->getDoctorRole() && get_current_user_id() !== $temp['doctor_id']){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$temp = apply_filters('kivicare_update_encounter_save_fields', $temp, $request_data);

		$patient_encounter = new KCPatientEncounter();


		if (!isset($request_data['id'])) {

			$temp['created_at'] = current_time('Y-m-d H:i:s');
			$temp['added_by'] = get_current_user_id();
			$encounter_id = $patient_encounter->insert($temp);
			$message = esc_html__('Patient encounter saved successfully', 'kc-lang');
			do_action('kc_encounter_save', $encounter_id);


		} else {
			$encounter_id = (int) $request_data['id'];
			if (!((new KCPatientEncounter())->encounterPermissionUserWise($encounter_id))) {
				wp_send_json(kcUnauthorizeAccessResponse(403));
			}
			$status = $patient_encounter->update($temp, array('id' => (int) $request_data['id']));
			$message = esc_html__('Patient encounter has been updated successfully', 'kc-lang');
			do_action('kc_encounter_update', $encounter_id);
		}

		wp_send_json([
			'status' => true,
			'message' => $message,
			'data' => $encounter_id
		]);

	}

	public function edit()
	{

		$is_permission = false;

		if (kcCheckPermission('patient_encounter_edit')) {
			$is_permission = true;
		}

		if (!$is_permission) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();
		try {

			if (!isset($request_data['id'])) {
				wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
			}

			if (!((new KCPatientEncounter())->encounterPermissionUserWise($request_data['id']))) {
				wp_send_json(kcUnauthorizeAccessResponse(403));
			}

			$id = (int) $request_data['id'];

			$patient_encounter_table = $this->db->prefix . 'kc_patient_encounters';
			$clinics_table = $this->db->prefix . 'kc_clinics';
			$users_table = $this->db->base_prefix . 'users';

			$query = "
			SELECT {$patient_encounter_table}.*,
			   doctors.display_name  AS doctor_name,
			   patient.display_name  AS patient_name,
		       {$clinics_table}.name AS clinic_name
			FROM  {$patient_encounter_table}
		       LEFT JOIN {$users_table} doctors
					  ON {$patient_encounter_table}.doctor_id = doctors.id
			   LEFT JOIN {$users_table} patient
					ON {$patient_encounter_table}.patient_id = patient.id
		       LEFT JOIN {$clinics_table}
		              ON {$patient_encounter_table}.clinic_id = {$clinics_table}.id
            WHERE {$patient_encounter_table}.id = {$id} LIMIT 1";

			$encounter = $this->db->get_row($query);
			if (!empty($encounter)) {

				$temp = [
					'id' => $encounter->id,
					'date' => $encounter->encounter_date,
					'patient_id' => [
						'id' => $encounter->patient_id,
						'label' => $encounter->patient_name
					],
					'clinic_id' => [
						'id' => $encounter->clinic_id,
						'label' => $encounter->clinic_name
					],
					'doctor_id' => [
						'id' => $encounter->doctor_id,
						'label' => $encounter->doctor_name
					],
					'description' => $encounter->description,
					'status' => $encounter->status,
					'added_by' => $encounter->added_by,
				];

				$temp = apply_filters('kivicare_update_encounter_edit_fields', $temp, $encounter);

				$encounter->custom_forms = array_merge(
					apply_filters('kivicare_custom_form_list', [], ['type' => 'appointment_module']),
					apply_filters('kivicare_custom_form_list', [], ['type' => 'patient_encounter_module'])
				);
				wp_send_json([
					'status' => true,
					'message' => __('Encounter data', 'kc-lang'),
					'data' => $temp
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
				'message' => $e->getMessage()
			]);
		}
	}

	public function delete()
	{

		$is_permission = false;

		if (kcCheckPermission('patient_encounter_delete')) {
			$is_permission = true;
		}

		if (!$is_permission) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();

		try {

			if (!isset($request_data['id'])) {
				wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
			}

			$id = (int) $request_data['id'];

			if (!((new KCPatientEncounter())->encounterPermissionUserWise($request_data['id']))) {
				wp_send_json(kcUnauthorizeAccessResponse(403));
			}

			$results = (new KCPatientEncounter())->loopAndDelete(['id' => $id], true);

			if ($results) {
				wp_send_json([
					'status' => true,
					'message' => esc_html__('Encounter has been deleted successfully', 'kc-lang'),
				]);
			} else {
				wp_send_json(kcThrowExceptionResponse(esc_html__('Patient encounter delete failed', 'kc-lang'), 400));
			}


		} catch (Exception $e) {

			$code = $e->getCode();
			$message = $e->getMessage();

			header("Status: $code $message");

			wp_send_json([
				'status' => false,
				'message' => $e->getMessage()
			]);
		}
	}

	public function details()
	{

		$request_data = $this->request->getInputs();

		try {

			if (!isset($request_data['id'])) {
				wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
			}

			$id = (int) $request_data['id'];

			if (!((new KCPatientEncounter())->encounterPermissionUserWise($request_data['id']))) {
				wp_send_json(kcUnauthorizeAccessResponse(403));
			}

			$encounter = $this->getEncounterData($id);

			$encounter->encounter_date = kcGetFormatedDate($encounter->encounter_date);

			if ($encounter) {
				wp_send_json([
					'status' => true,
					'message' => esc_html__('Encounter details', 'kc-lang'),
					'data' => $encounter,
					'patientDetails' => apply_filters('kivicare_encounter_patient_details', $encounter),
					'hideInPatient' => get_option(KIVI_CARE_PREFIX . 'hide_clinical_detail_in_patient', false)
				]);

			} else {
				wp_send_json(kcThrowExceptionResponse(esc_html__('Encounter not found', 'kc-lang'), 400));
			}

		} catch (Exception $e) {

			$code = $e->getCode();
			$message = $e->getMessage();

			header("Status: $code $message");

			wp_send_json([
				'status' => false,
				'message' => $e->getMessage()
			]);
		}
	}

	public function saveCustomField()
	{

		if (!kcCheckPermission('patient_encounter_add')) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		$request_data = $this->request->getInputs();
		if (!isset($request_data['id'])) {
			wp_send_json([
				'status' => false,
				'status_code' => 404,
				'message' => esc_html__('Encounter id not found', 'kc-lang'),
				'data' => []
			]);
		}

		if (!((new KCPatientEncounter())->encounterPermissionUserWise($request_data['id']))) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}

		//		$custom_fields = KCCustomField::getRequiredFields( 'patient_encounter_module' );

		if (!empty($request_data['custom_fields'])) {
			if (is_array($request_data['custom_fields']) && count($request_data['custom_fields']) > 0) {
				if (strpos(array_key_first($request_data['custom_fields']), 'custom_field_') === false) {
					wp_send_json([
						'status' => true,
						'message' => esc_html__('Encounter data has been saved', 'kc-lang'),
					]);
				}
			}
			// custom field add based on encounter id.
			kvSaveCustomFields('patient_encounter_module', $request_data['id'], $request_data['custom_fields']);
			do_action('kc_encounter_update', $request_data['id']);
		}

		wp_send_json([
			'status' => true,
			'message' => esc_html__('Encounter data has been saved', 'kc-lang'),
		]);
	}

	public function getEncounterData($id)
	{

		$patient_encounter_table = $this->db->prefix . 'kc_patient_encounters';
		$clinics_table = $this->db->prefix . 'kc_clinics';
		$users_table = $this->db->base_prefix . 'users';
		$appointment_table = $this->db->prefix . 'kc_appointments';

		$appointment_report_query = $appointment_report_join_query = '';

		if (kcAppointmentMultiFileUploadEnable()) {
			$appointment_report_query = ", {$appointment_table}.appointment_report";
			$appointment_report_join_query = "LEFT JOIN {$appointment_table} 
			ON {$appointment_table}.id = {$patient_encounter_table}.appointment_id";
		}

		$query = "
			SELECT {$patient_encounter_table}.*,
		       doctors.display_name  AS doctor_name,
		       patients.display_name AS patient_name,
		       patients.user_email AS patient_email,
		       {$clinics_table}.name AS clinic_name,
			   {$clinics_table}.extra AS clinic_extra
			   {$appointment_report_query}
			FROM  {$patient_encounter_table}
		       LEFT JOIN {$users_table} doctors
		              ON {$patient_encounter_table}.doctor_id = doctors.id
              LEFT JOIN {$users_table} patients
		              ON {$patient_encounter_table}.patient_id = patients.id
		       LEFT JOIN {$clinics_table}
		              ON {$patient_encounter_table}.clinic_id = {$clinics_table}.id
			   {$appointment_report_join_query}
            WHERE {$patient_encounter_table}.id = {$id}";

		$encounter = $this->db->get_row($query);



		if (!empty($encounter)) {
			$patient_profile_image = get_user_meta($encounter->patient_id, 'patient_profile_image', true);
			$patient = get_user_meta($encounter->patient_id, 'basic_data', true);
			$patient = json_decode($patient);
			$get_patient_data = get_option(KIVI_CARE_PREFIX . 'patient_id_setting', true);

			if (gettype($get_patient_data) != 'boolean') {
				$encounter->is_patient_unique_id_enable = in_array((string) $get_patient_data['enable'], ['true', '1']) ? true : false;
			}

			$encounter->clinic_name = decodeSpecificSymbols($encounter->clinic_name);
			$encounter->patient_unique_id = get_user_meta((int) $encounter->patient_id, 'patient_unique_id', true) ?? '-';
			$encounter->patient_address = (!empty($patient->address) ? $patient->address : '');
			$encounter->patient_profile_image = !empty($patient_profile_image) ? wp_get_attachment_url($patient_profile_image) : '';
			if (!empty($encounter->appointment_report)) {
				$encounter->appointment_report = array_map(function ($v) {
					$name = !empty(get_the_title($v)) ? get_the_title($v) : '';
					$url = !empty(wp_get_attachment_url($v)) ? wp_get_attachment_url($v) : '';
					return ['url' => $url, 'name' => $name];
				}, json_decode($encounter->appointment_report, true));
			}
			if (!empty($encounter->appointment_id) && isKiviCareProActive()) {
				$encounter->appointment_custom_field = kcGetCustomFields('appointment_module', $encounter->appointment_id, (int) $encounter->doctor_id);
			}
			$encounter->custom_forms = array_merge(
				apply_filters('kivicare_custom_form_list', [], ['type' => 'appointment_module']),
				apply_filters('kivicare_custom_form_list', [], ['type' => 'patient_encounter_module'])
			);

			$encounter->doctor_signature = get_user_meta($encounter->doctor_id, 'doctor_signature', true);
			$encounter->custom_fields = kcGetCustomFields('patient_encounter_module', $encounter->doctor_id);

			$encounter->encounter_edit_after_close_status = (get_option(KIVI_CARE_PREFIX . 'encounter_edit_after_close_status', 'off') === 'on') ? true : false;
			return $encounter;
		} else {
			return null;
		}
	}


	public function updateStatus()
	{

		if (kcCheckPermission('patient_encounter_add')) {
			$is_permission = true;
		}

		$request_data = $this->request->getInputs();



		try {

			if (!isset($request_data['id'])) {
				wp_send_json(kcThrowExceptionResponse(esc_html__('Data not found', 'kc-lang'), 400));
			}

			if (!((new KCPatientEncounter())->encounterPermissionUserWise($request_data['id']))) {
				wp_send_json(kcUnauthorizeAccessResponse(403));
			}

			$id = (int) $request_data['id'];
			$patient_encounter = new KCPatientEncounter();
			$encounter = $patient_encounter->get_by(['id' => $id], '=', true);

			if (empty($encounter)) {
				wp_send_json(kcThrowExceptionResponse(esc_html__('Encounter not found', 'kc-lang'), 400));
			}

			if ((string) $request_data['status'] === '0') {
				if ((string) $request_data['checkOutVal'] === '1') {
					(new KCAppointment())->update(['status' => '3'], ['id' => (int) $encounter->appointment_id]);
				}
				(new KCBill())->update(['status' => 1], ['encounter_id' => (int) $encounter->id]);
			}

			$patient_encounter->update(['status' => $request_data['status']], ['id' => $id]);
			do_action('kc_encounter_update', $id);
			wp_send_json([
				'status' => true,
				'message' => esc_html__('Encounter status has been updated', 'kc-lang')
			]);

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

	public function sendBillToPatient()
	{
		$request_data = $this->request->getInputs();
		$request_data['id'] = (int) $request_data['id'];
	}

	public function printEncounterBillDetail()
	{
		global $wpdb;
		$request_data = $this->request->getInputs();
		$request_data['id'] = (int) $request_data['id'];
		$request_type = $request_data['type'];

		if (!empty($request_data['id']) && !empty($request_data['data'])) {
			if (!((new KCPatientEncounter())->encounterPermissionUserWise($request_data['id']))) {
				wp_send_json(kcUnauthorizeAccessResponse(403));
			}
			$request_data['data'] = kcRecursiveSanitizeTextField(json_decode(stripslashes($request_data['data']), true));
			$patient_encounter_table = $this->db->prefix . 'kc_patient_encounters';
			$clinics_table = $this->db->prefix . 'kc_clinics';
			$users_table = $this->db->base_prefix . 'users';
			$encouter_id = (int) $request_data['id'];
			$query = "SELECT {$patient_encounter_table}.*,
                       doctors.display_name  AS doctor_name,
                       doctors.user_email AS doctor_email,    
                       patients.display_name AS patient_name,
                       patients.user_email AS patient_email,
                       CONCAT({$clinics_table}.address, ', ', {$clinics_table}.city,', '
		              ,{$clinics_table}.postal_code,', ',{$clinics_table}.country) AS clinic_address,
                       {$clinics_table}.* 
                    FROM  {$patient_encounter_table}
                       LEFT JOIN {$users_table} doctors
                              ON {$patient_encounter_table}.doctor_id = doctors.id
                      LEFT JOIN {$users_table} patients
                              ON {$patient_encounter_table}.patient_id = patients.id
                       LEFT JOIN {$clinics_table}
                              ON {$patient_encounter_table}.clinic_id = {$clinics_table}.id
                    WHERE {$patient_encounter_table}.id = {$encouter_id}";

			$encounter = $this->db->get_row($query);
			$qualifications = [];
			if (!empty($encounter)) {
				$encounter->medical_history = '';
				$encounter->prescription = '';
				$basic_data = get_user_meta((int) $encounter->doctor_id, 'basic_data', true);
				$basic_data = json_decode($basic_data);
				if (!empty($basic_data->qualifications)) {
					foreach ($basic_data->qualifications as $q) {
						$qualifications[] = $q->degree;
						$qualifications[] = $q->university;
					}
				}
				$patient_basic_data = json_decode(get_user_meta((int) $encounter->patient_id, 'basic_data', true));
				$encounter->patient_gender = !empty($patient_basic_data->gender)
					? ($patient_basic_data->gender === 'female'
						? 'F' : 'M') : '';
				$encounter->patient_address = (!empty($patient_basic_data->address) ? $patient_basic_data->address : '');
				$encounter->patient_city = (!empty($patient_basic_data->city) ? $patient_basic_data->city : '');
				$encounter->patient_state = (!empty($patient_basic_data->state) ? $patient_basic_data->state : '');
				$encounter->patient_country = (!empty($patient_basic_data->country) ? $patient_basic_data->country : '');
				$encounter->patient_postal_code = (!empty($patient_basic_data->postal_code) ? $patient_basic_data->postal_code : '');
				$encounter->contact_no = (!empty($patient_basic_data->mobile_number) ? $patient_basic_data->mobile_number : '');
				$encounter->patient_add = $encounter->patient_address . ',' . $encounter->patient_city
					. ',' . $encounter->patient_state . ',' . $encounter->patient_country . ',' . $encounter->patient_postal_code;
				$encounter->date = current_time('Y-m-d');
				$encounter->patient_age = '';
				if (!empty($patient_basic_data->dob)) {
					try {
						$from = new DateTime($patient_basic_data->dob);
						$to = new DateTime('today');
						$years = $from->diff($to)->y;
						$months = $from->diff($to)->m;
						$days = $from->diff($to)->d;
						if (empty($months) && empty($years)) {
							$encounter->patient_age = $days . esc_html__(' Days', 'kc-lang');
						} else if (empty($years)) {
							$encounter->patient_age = $months . esc_html__(' Months', 'kc-lang');
						} else {
							$encounter->patient_age = $years . esc_html__(' Years', 'kc-lang');
						}
					} catch (Exception $e) {
						wp_send_json([
							'data' => '',
							'status' => false,
							'calendar_content' => '',
							'message' => $e->getMessage()
						]);
					}
				}
				$encounter->qualifications = !empty($qualifications) ? '(' . implode(", ", $qualifications) . ')' : '';
				$encounter->clinic_logo = !empty($encounter->profile_image) ? wp_get_attachment_url($encounter->profile_image) : KIVI_CARE_DIR_URI . 'assets/images/kc-demo-img.png';
				$encounter->billItems = !empty($request_data['data']['billItems']) ? $request_data['data']['billItems'] : [];
				$encounter->payment_status = !empty($request_data['data']['payment_status']) ? $request_data['data']['payment_status'] : '';
				$encounter->bill_created = !empty($request_data['data']['created_at']) ? $request_data['data']['created_at'] : '';
				$encounter->actual_amount = !empty($request_data['data']['actual_amount']) ? $request_data['data']['actual_amount'] : '';
				$encounter->total_amount = !empty($request_data['data']['total_amount']) ? $request_data['data']['total_amount'] : '';
				$encounter->discount = !empty($request_data['data']['discount']) ? $request_data['data']['discount'] : '0';
			}

			if ($request_type === 'sendBill') {
				$invoice_status = false;
				$print_data = kcPrescriptionHtml($encounter, $encouter_id, 'bill_detail');

				// Instantiate and use the dompdf class
				$dompdf = new Dompdf();
				$dompdf->set_option('isHtml5ParserEnabled', true);
				$dompdf->set_option('isPhpEnabled', true);
				$dompdf->set_option('isRemoteEnabled', true);

				$dompdf->loadHtml('<meta charset="UTF-8">' . $print_data);

				// (Optional) Setup the paper size and orientation
				$dompdf->setPaper('A4', 'portrait');

				// Render the HTML as PDF
				$dompdf->render();

				// Get the generated PDF as a string
				$pdf_output = $dompdf->output();


				$file_name = 'Invoice_' . $encouter_id . '.pdf';

				// Get the WordPress uploads directory path
				$upload_dir = wp_upload_dir();
				$pdf_path = $upload_dir['path'] . '/' . $file_name;

				// Save the PDF to the uploads directory
				file_put_contents($pdf_path, $pdf_output);

				$patient_report[0] = $pdf_path;

				if (true) {
					$user_email = $wpdb->get_var('select user_email from ' . $wpdb->base_prefix . 'users where ID=' . (int) $encounter->patient_id);
					$data = [
						'user_email' => $user_email != null ? $user_email : '',
						'attachment_file' => $patient_report,
						'attachment' => true,
						'email_template_type' => 'patient_invoice'
					];

					$invoice_status = kcSendEmail($data);

					$invoice_message = $invoice_status ? esc_html__('Invoice sent successfully', 'kc-lang') : esc_html__('Failed to send Invoice', 'kc-lang');

					if ($invoice_status) {
						if (file_exists($pdf_path)) {
							unlink($pdf_path);
						}
					}

					wp_send_json([
						'data' => [],
						'status' => $invoice_status,
						'message' => $invoice_message
					]);
				}

			} else {
				wp_send_json([
					'data' => kcPrescriptionHtml($encounter, $encouter_id, 'bill_detail'),
					'status' => true
				]);
			}
		} else {
			wp_send_json([
				'data' => [],
				'status' => false,
				'message' => __('Encounter Id not found', 'kc-lang')
			]);
		}
	}

	public function encounterExtraClinicalDetailFields()
	{
		$request_data = $this->request->getInputs();
		$encounter_id = (int) $request_data['encounter_id'];
		$encounter_status = $request_data['status'];
		wp_send_json(
			[
				'status' => true,
				'data' => [
					[
						"type" => 'disease',
						"title" => 'disease',
						"encounter_id" => $encounter_id,
						"status" => $encounter_status,
						"ref" => 'medical_history_disease'
					],
					[
						"type" => 'report',
						"title" => 'report',
						"encounter_id" => $encounter_id,
						"status" => $encounter_status,
						"ref" => 'medical_history_report'
					],

				]
			]
		);
	}


	public function encounterPermissionUserWise($encounter_id)
	{
		$encounter_detail = (new KCPatientEncounter())->get_by(['id' => (int) $encounter_id], '=', true);
		$permission = false;

		$login_user_role = $this->getLoginUserRole();
		switch ($login_user_role) {
			case $this->getReceptionistRole():
				$clinic_id = kcGetClinicIdOfReceptionist();
				if (!empty($encounter_detail->clinic_id) && (int) $encounter_detail->clinic_id === $clinic_id) {
					$permission = true;
				}
				break;
			case $this->getClinicAdminRole():
				$clinic_id = kcGetClinicIdOfClinicAdmin();
				if (!empty($encounter_detail->clinic_id) && (int) $encounter_detail->clinic_id === $clinic_id) {
					$permission = true;
				}
				break;
			case 'administrator':
				$permission = true;
				break;
			case $this->getDoctorRole():
				if (!empty($encounter_detail->doctor_id) && (int) $encounter_detail->doctor_id === get_current_user_id()) {
					$permission = true;
				}
				break;
			case $this->getPatientRole():
				if (!empty($encounter_detail->patient_id) && (int) $encounter_detail->patient_id === get_current_user_id()) {
					$permission = true;
				}
				break;
		}
		return $permission;
	}

	public function getEncounterPrint()
	{
		$request_data = $this->request->getInputs();
		if (!((new KCPatientEncounter())->encounterPermissionUserWise($request_data['encounter_id']))) {
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
		$response = apply_filters('kcpro_get_encounter_print', [
			'encounter_id' => (int) $request_data['encounter_id'],
			'clinic_default_logo' => KIVI_CARE_DIR_URI . 'assets/images/kc-demo-img.png',
		]);
		wp_send_json($response);
	}
}