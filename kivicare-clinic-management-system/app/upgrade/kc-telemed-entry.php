<?php

use App\models\KCService;
use App\models\KCServiceDoctorMapping;

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$usermeta_table = $wpdb->base_prefix . 'usermeta';

$service_table = $wpdb->prefix . 'kc_' . 'services';

$service_dr_mapping = new KCServiceDoctorMapping;

$get_telemed_service_id_query = "SELECT id FROM {$service_table}  WHERE  `type` = 'system_service' AND  `name` = 'Telemed' " ;

$telemed_service_id = $wpdb->get_row($get_telemed_service_id_query);

$get_telemed_doctor_query = "SELECT user_id, meta_value  FROM {$usermeta_table}  WHERE  `meta_value` LIKE '%enableTeleMed%'" ;

$doctor_telemed_result = $wpdb->get_results($get_telemed_doctor_query);

if(!isset($telemed_service_id->id)) {

    $service_data = new KCService;

    $services = [
        'type' => 'system_service',
        'name' => 'Telemed',
        'price' => 0,
        'status' => 1,
        'created_at' => current_time('Y-m-d H:i:s')
    ];

    $telemed_service_id->id = $service_data->insert($services);

}

$clinic_id = kcGetDefaultClinicId();

if(count($doctor_telemed_result) > 0) { 

    foreach($doctor_telemed_result as $key => $value) {

        $data = json_decode($value->meta_value);

        if (isset($data->enableTeleMed)) {

            $kc_status = "" ;

            if (($data->enableTeleMed === 1 || $data->enableTeleMed === true)) {
                $kc_status = 1 ;
            }

            $service_mapping = [
                'service_id' => $telemed_service_id->id,
                'doctor_id'  => $value->user_id,
                'clinic_id' => $clinic_id,
                'charges' => 0,
                'status' =>  $kc_status,
                'created_at' => current_time('Y-m-d H:i:s')
            ];

            $result = $service_dr_mapping->get_by(['doctor_id' => $value->user_id, 'service_id' => $telemed_service_id->id]);

            if(count($result) == 0) {
                $insert_id =  $service_dr_mapping->insert($service_mapping);
            }

        }

    }
}
