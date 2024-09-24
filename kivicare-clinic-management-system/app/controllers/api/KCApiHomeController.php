<?php

namespace App\Controllers\Api;

use WP_REST_Response;
use WP_User;

use WP_REST_Server;

class KCApiHomeController {

	public $module = 'patient-appointment';

	public $nameSpace = 'wp-medical';

	public function __construct() {

		add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace. '/api/v1/' .$this->module , '/get-static-data', array(
				'methods' => WP_REST_Server::ALLMETHODS,
				'callback' => [$this, 'getStaticData'],
				'permission_callback' => '__return_true'
			));

			register_rest_route( $this->nameSpace. '/api/v1/' .$this->module, '/get-doctors-data', array(
				'methods' => WP_REST_Server::ALLMETHODS,
				'callback' => [$this, 'getDoctor'],
				'permission_callback' => '__return_true'
			));

			register_rest_route( $this->nameSpace. '/api/v1/' .$this->module, '/test', array(
				'methods' => WP_REST_Server::ALLMETHODS,
				'callback' => [$this, 'testApi'],
				'permission_callback' => '__return_true'
			));

		} );
	}

	public function getStaticData ($request) {

		$parameters = $request->get_params();

		$data = [
			'status' => false,
			'message' => esc_html__('Datatype not found', 'kc-lang')
		];

		if (isset($parameters['data_type'])) {

			global $wpdb;
			$table_name = $wpdb->prefix . 'kc_static_data';
			$type = sanitize_text_field($parameters['data_type']);

			switch ($type) {

				case "static_data":
					$static_data_type = sanitize_text_field($parameters['static_data_type']);
					$query = "SELECT id, label FROM $table_name WHERE type = '$static_data_type' AND status = '1'";
					$results = $wpdb->get_results($query, OBJECT);
					break;

				case "static_data_with_label":
					$static_data_type = sanitize_text_field($parameters['static_data_type']);
					$query = "SELECT `value` as id, label FROM $table_name WHERE type = '$static_data_type' AND status = '1'";
                    $results = collect($wpdb->get_results($query, OBJECT))->unique('id')->toArray();
					break;

				case "static_data_types":
					$query = "SELECT `type` as id, `type` FROM $table_name GROUP BY `type`";
					$results = $wpdb->get_results($query, OBJECT);
					break;

				case "clinics":
					$table_name = $wpdb->prefix . 'kc_clinics';
					$query = "SELECT `id`, `name` as `label` FROM {$table_name} WHERE `status` = '1'";
					$results = $wpdb->get_results($query, OBJECT);
					break;

				case "services_with_price":
					$table_name = $wpdb->prefix . 'kc_services';
					$query = "SELECT `id`, `price`, `name` as `label` FROM {$table_name} WHERE `status` = '1'";
					$results = $wpdb->get_results($query, OBJECT);
					break;

				case "clinic_doctors":
					$table_name = $wpdb->prefix . 'kc_doctor_clinic_mappings';
					$clinic_id = (int)$parameters['clinic_id'];

					$query = "SELECT * FROM {$table_name} WHERE `clinic_id` = '{$clinic_id}' ";
					$clinic_data = $wpdb->get_results($query, OBJECT);

					$results = [];
					$doctor_ids = [];

					if (count($clinic_data)) {
						foreach ($clinic_data as $clinic_map_data) {
							if (isset($clinic_map_data->doctor_id)) {
								$doctor_ids[] = $clinic_map_data->doctor_id;
							}
						}

						if (count($doctor_ids)) {
							$users_table = $wpdb->base_prefix . 'users';
							$new_query = "SELECT `ID` as `id`, `display_name` as `label`  FROM {$users_table} WHERE `ID` IN ('" . implode(',', $doctor_ids) . "') AND `user_status` = '0'";

							$results = $wpdb->get_results($new_query, OBJECT);
						}
					}

					break;

				case "users":
					$results = [];
					$users = get_users([
						'role' => sanitize_text_field($parameters['user_type'])
					]);

					if (count($users)) {
						foreach ($users as $key => $user) {
							$results[$key]['id'] = $user->ID;
							$results[$key]['text'] = $user->data->display_name;
						}
					}

					break;

				default:
					$results = [];
			}

			$data['status'] = true;
			$data['message'] = esc_html__('Datatype found', 'kc-lang');
			$data['data'] = $results;
		}

		return new WP_REST_Response($data);

	}

	public function getDoctor () {
		global $wpdb;

		$doctors = get_users([
			'role' => 'doctor'
		]);

		if (!count($doctors)) {
			return new WP_REST_Response( [
				'status'  => false,
				'message' => esc_html__('No doctors found', 'kc-lang'),
				'data' => []
			]);
		}

		$data = [];

		foreach ($doctors as $key => $doctor) {
			$doctor_id = (int)$doctor->ID;
			$result = $wpdb->get_row( "SELECT clinic_id FROM ".$wpdb->prefix . "kc_doctor_clinic_mappings WHERE doctor_id =".$doctor_id, 'OBJECT' );
			$user_meta = get_user_meta( $doctor->ID, 'basic_data', true );
			$data[$key]['ID'] = $doctor->ID;
			$data[$key]['clinic_id'] = $result->clinic_id;
			$data[$key]['display_name'] = $doctor->data->display_name;
			$data[$key]['user_email'] = $doctor->data->user_email;
			$data[$key]['user_status'] = $doctor->data->user_status;
			$data[$key]['user_registered'] = $doctor->data->user_registered;

			if ($user_meta !== null) {
				$basic_data = json_decode($user_meta);
				$data[$key]['mobile_number'] = $basic_data->mobile_number;
				$data[$key]['gender'] = $basic_data->gender;
				$data[$key]['dob'] = $basic_data->dob;
				$data[$key]['address'] = $basic_data->address;
				$data[$key]['specialties'] = $basic_data->specialties;
			}
		}

		return new WP_REST_Response( [
			'status'  => true,
			'message' => esc_html__('Doctors list', 'kc-lang'),
			'data' => $data
		]);
	}

}
