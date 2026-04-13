<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');

class AddDefaultModuleConfig extends KCAbstractMigration {
    public function run() {
        // Create an instance to access non-static methods
        $prefix = KIVI_CARE_PREFIX;

        // Add default option for module configuration
        $modules = [
            'module_config' => [
                [
                    'name' => 'receptionist',
                    'label' => 'Receptionist',
                    'status' => '1'
                ],
                [
                    'name' => 'billing',
                    'label' => 'Billing',
                    'status' => '1'
                ],
                [
                    'name' => 'custom_fields',
                    'label' => 'Custom Fields',
                    'status' => '1'
                ]
            ],
            'common_setting' => [],
            'notification' => []
        ];

        delete_option($prefix . 'modules');
        add_option($prefix . 'modules', json_encode($modules));
    }

    public function rollback() {
        return true;
    }
}