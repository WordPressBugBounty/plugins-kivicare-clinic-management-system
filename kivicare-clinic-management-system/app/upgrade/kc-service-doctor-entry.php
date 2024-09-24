<?php

use App\models\KCServiceDoctorMapping;

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$table_name = $wpdb->prefix . 'kc_services'; // do not forget about tables prefix

$all_service = $wpdb->get_results( "SELECT * FROM ". $table_name);

$doctors = get_users(['role' => $this->getDoctorRole()]);

$service_dr_mapping = new KCServiceDoctorMapping;

foreach ($all_service as $kc_s) {
    foreach ($doctors as $d) {

       $data =  [
            'service_id' => (int)$kc_s->id,
            'doctor_id' => (int)$d->ID,
            'clinic_id' => kcGetDefaultClinicId(),
            'charges'=> (int)$kc_s->price,
            'status' => 1,
            'created_at' => current_time('Y-m-d H:i:s')
        ];

        $service_dr_mapping->insert($data);
    }
}
