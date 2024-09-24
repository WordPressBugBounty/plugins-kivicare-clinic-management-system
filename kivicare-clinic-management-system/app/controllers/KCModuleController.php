<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;

class KCModuleController extends KCBase {

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
		if($this->getLoginUserRole() !== 'administrator'){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
	}

	public function index() {

		$modules = kcGetModules();

		if($modules !== '') {
			$data = $modules;
			$message = __('modules found.','kc-lang') ;
			$status = true ;
		} else {
			$data = [] ;
			$message = __('modules not found.' ,'kc-lang');
			$status = false ;
		}

		wp_send_json([
			'status'  => $status,
			'message' => $message,
			'data'    => $data,
            'encounter' => $this->encounterModules(),
            'prescriptionModules' => $this->prescriptionModules()
		] );

	}

	public function encounterModules(){
		$active_domain =$this->getAllActivePlugin();
		if($active_domain === $this->kiviCareProOnName()){
			$response = apply_filters('kcpro_get_list',[]);
			return $response;
		}
	}
	public function prescriptionModules(){
		$active_domain =$this->getAllActivePlugin();
		if($active_domain === $this->kiviCareProOnName()){
			$response = apply_filters('kcpro_get_prescription_list',[]);
			return $response;
		}
	}
	public function save () {

		$request_data = $this->request->getInputs();
		$module_config = collect($request_data['modules']['module_config'])->values();
        $receptionist = $module_config->where('name', 'receptionist')->where('status', 1);

		$step_config = collect(kcGetStepConfig());


        if(isset($request_data['request_help'])){
            update_option(KIVI_CARE_PREFIX.'request_help',strval($request_data['request_help']));
        }
		$active_domain =$this->getAllActivePlugin();
		if($active_domain === $this->kiviCareProOnName()){
			if(isset($request_data['encounter_modules'])){
				$encounter_module_config = collect($request_data['encounter_modules'])->values();
				$response = apply_filters('kcpro_get_encounter_setting', [
					'encounter_module' => $request_data['encounter_modules'],
				]);
				// wp_send_json($response);
			}
			if(isset($request_data['prescription_module'])){
				$encounter_module_config = collect($request_data['prescription_module'])->values();
				$response = apply_filters('kcpro_save_prescription_setting', [
					'prescription_module' => $request_data['prescription_module'],
				]);
//				wp_send_json($response);
			}

			if (count($receptionist)) {
				$row_count = $step_config->where('name','receptionist');
				if (count($row_count) === 0) {
					$receptionist_config = [
						'icon' => "fa fa-info fa-lg",
						'name' => "receptionist",
						'title' => "Receptionist",
						'prevStep' => 'setup.step5',
						'routeName' => 'setup.step6',
						'nextStep' => 'finish',
						'subtitle' => "",
						'completed' => false
					];

					$step_config->push($receptionist_config);

				}

				$update_receptionist_status['role'] = $this->getReceptionistRole() ;
				$update_receptionist_status['status'] = 0 ;


			} else {

				$update_receptionist_status['role'] = $this->getReceptionistRole() ;
				$update_receptionist_status['status'] = 4 ;

				$step_config = $step_config->where('name', '!=','receptionist');

			}

			$this->updateUserStatus($update_receptionist_status);

			$prefix = KIVI_CARE_PREFIX;

			if (!isset($request_data['updateStep']) || (bool) $request_data['updateStep'] === true) {
				update_option( $this->getSetupConfig(), json_encode($step_config->toArray()));

			}
			update_option( $prefix. 'modules', json_encode($request_data['modules']));

		}else{
			if (count($receptionist)) {
				$row_count = $step_config->where('name','receptionist');
				if (count($row_count) === 0) {
					$receptionist_config = [
						'icon' => "fa fa-info fa-lg",
						'name' => "receptionist",
						'title' => "Receptionist",
						'prevStep' => 'setup.step5',
						'routeName' => 'setup.step6',
						'nextStep' => 'finish',
						'subtitle' => "",
						'completed' => false
					];

					$step_config->push($receptionist_config);

				}

				$update_receptionist_status['role'] = $this->getReceptionistRole() ;
				$update_receptionist_status['status'] = 0 ;


			} else {

				$update_receptionist_status['role'] = $this->getReceptionistRole() ;
				$update_receptionist_status['status'] = 4 ;

				$step_config = $step_config->where('name', '!=','receptionist');

			}

			$this->updateUserStatus($update_receptionist_status);

			$prefix = KIVI_CARE_PREFIX;

			if (!isset($request_data['updateStep']) || (bool) $request_data['updateStep'] === true) {
				update_option( $this->getSetupConfig(), json_encode($step_config->toArray()));

			}
			update_option( $prefix. 'modules', json_encode($request_data['modules']));
			wp_send_json([
				'status'  => true,
				'message' => esc_html__('Module configuration updated successfully', 'kc-lang')
			] );
		}
		wp_send_json($response);
	}

	public function updateUserStatus ($data) {

		global $wpdb ;

		$users = get_users([
			'role' => $data['role']
		]);

		foreach ($users as $user) {
			$receptionist_ids[] = (int)$user->data->ID ;
			if(isset($user->data->ID) && $user->data->ID !== null) {

				$update_user_status = "UPDATE {$wpdb->base_prefix}users SET user_status = {$data['status']} WHERE ID = {$user->data->ID}" ;
				$wpdb->query($update_user_status);

			}
		}

		return true;

	}
}

