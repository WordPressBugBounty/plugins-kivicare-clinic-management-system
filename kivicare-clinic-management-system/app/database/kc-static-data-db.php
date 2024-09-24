<?php

use App\models\KCService;
use App\models\KCStaticData;

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

$wpdb->query('SET SESSION sql_mode=""');

$kc_charset_collate = $wpdb->get_charset_collate();

$table_name = $wpdb->prefix . 'kc_static_data';
// do not forget about tables prefix

$sql = "CREATE TABLE `{$table_name}` (
    id bigint(20) NOT NULL AUTO_INCREMENT,    
    type varchar(191)  NOT NULL,
    label text NOT NULL,
    value text  NOT NULL,
    parent_id bigint(20) NULL,
    status bigint(4) UNSIGNED NOT NULL,    
    created_at datetime NOT NULL,    
    PRIMARY KEY  (id)
  ) $kc_charset_collate;";

maybe_create_table($table_name,$sql);

$count_query = "select count(*) from ".$table_name;
$num = $wpdb->get_var($count_query);

if($num == 0 ) {
    $temp = [
        ['type' => 'specialization',
            'label' => 'Dermatology',
            'status' => 1,
            'created_at' => current_time('Y-m-d H:i:s')
        ],
        ['type' => 'specialization',
            'label' => 'Family Medicine',
            'status' => 1,
            'created_at' => current_time('Y-m-d H:i:s')
        ],
        ['type' => 'specialization',
            'label' => 'Neurology',
            'status' => 1,
            'created_at' => current_time('Y-m-d H:i:s')
        ],
        ['type' => 'specialization',
            'label' => 'Allergy And Immunology',
            'status' => 1,
            'created_at' => current_time('Y-m-d H:i:s')
        ],
        ['type' => 'service_type',
            'label' => 'General Dentistry',
            'status' => 1,
            'created_at' => current_time('Y-m-d H:i:s')
        ],
        ['type' => 'service_type',
            'label' => 'Weight Management',
            'status' => 1,
            'created_at' => current_time('Y-m-d H:i:s')
        ],
        ['type' => 'service_type',
            'label' => 'Psychology Services',
            'status' => 1,
            'created_at' => current_time('Y-m-d H:i:s')
        ],
    ];

    $static_data = new KCStaticData;

    foreach ($temp as $data) {
        $data['value'] = str_replace(' ', '_', strtolower($data['label']));
        $static_data->insert($data);
    }

    $service_data = new KCService;

    $services = [[
        'type' => 'system_service',
        'name' => 'Telemed',
        'price' => 0,
        'status' => 1,
        'created_at' => current_time('Y-m-d H:i:s')
    ]];

    foreach ($services as $data) {
        $service_data->insert($data);
    }
}