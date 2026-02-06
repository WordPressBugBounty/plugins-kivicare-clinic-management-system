<?php

namespace App\baseClasses;

use App\admin\KCDashboardPermalinkHandler;
use App\database\classes\KCMigrator;
use App\emails\KCEmailTemplateManager;
use App\models\KCStaticData;

/**
 * The code that runs during plugin activation
 */
defined('ABSPATH') or die('Something went wrong');

final class KCActivate
{
    public static function activate()
    {
        KCMigration::migrate();
        KCPermissions::get_instance()->init_roles_and_capabilities();
        KCDashboardPermalinkHandler::flush_rewrite_rules();



        // Create shortcode posts
        KCPostCreator::createShortcodePost();

        // Add default specializations and other static data
        self::addDefaultStaticData();

        // Add all default options

        // add widgetsetting
        self::widgetSettingLoad();
    }


    public static function widgetSettingLoad()
    {
        $data = [
            'showClinicImage' => '1',
            'showClinicAddress' => '1',
            'clinicContactDetails' => [
                'id' => '3',
                'label' => 'Show email address'
            ],
            'showDoctorImage' => '1',
            'showDoctorExperience' => '1',
            'doctorContactDetails' => [
                'id' => '3',
                'label' => 'Show email address'
            ],
            'showDoctorSpeciality' => '1',
            'showDoctorDegree' => '1',
            'showDoctorRating' => '1',
            'showServiceImage' => '1',
            'showServicetype' => '1',
            'showServicePrice' => '1',
            'showServiceDuration' => '1',
            'primaryColor' => '#7093e5',
            'primaryHoverColor' => '#4367b9',
            'secondaryColor' => '#f68685',
            'secondaryHoverColor' => '#df504e',
            'widget_print' => '1',
            'afterWoocommerceRedirect' => '1',
        ];

        update_option(KIVI_CARE_PREFIX . 'widgetSetting', json_encode($data));

        $widgetOrder = [
            ['name' => 'Choose a Clinic', 'fixed' => false, 'att_name' => 'clinic'],
            ['name' => 'Choose Your Doctor', 'fixed' => false, 'att_name' => 'doctor'],
            ['name' => 'Services from Category', 'fixed' => false, 'att_name' => 'category'],
            ['name' => 'Select Date and Time', 'fixed' => true, 'att_name' => 'date-time'],
            ['name' => 'User Detail Information', 'fixed' => true, 'att_name' => 'detail-info'],
            ['name' => 'Appointment Extra Data', 'fixed' => true, 'att_name' => 'file-uploads-custom'],
            ['name' => 'Confirmation', 'fixed' => true, 'att_name' => 'confirm'],
        ];

        if (!get_option(KIVI_CARE_PREFIX . 'widget_order_list')) {
            update_option(KIVI_CARE_PREFIX . 'widget_order_list', $widgetOrder);
        }
    }

    private static function addDefaultStaticData()
    {
        // Check if specializations already exist
        $existing_count = KCStaticData::query()
            ->where('type', 'specialization')
            ->count();
        
        // Only add if no specializations exist
        if ($existing_count == 0) {
            $specializations = [
                ['type' => 'specialization', 'label' => 'Dermatology', 'value' => 'dermatology', 'status' => 1],
                ['type' => 'specialization', 'label' => 'Family Medicine', 'value' => 'family_medicine', 'status' => 1],
                ['type' => 'specialization', 'label' => 'Neurology', 'value' => 'neurology', 'status' => 1],
                ['type' => 'specialization', 'label' => 'Allergy And Immunology', 'value' => 'allergy_and_immunology', 'status' => 1]
            ];
            
            foreach ($specializations as $specialization) {
                KCStaticData::create($specialization);
            }
        }
        
        // Also add default service types if they don't exist
        $existing_service_types = KCStaticData::query()
            ->where('type', 'service_type')
            ->count();
        
        if ($existing_service_types == 0) {
            $service_types = [
                ['type' => 'service_type', 'label' => 'General Dentistry', 'value' => 'general_dentistry', 'status' => 1],
                ['type' => 'service_type', 'label' => 'Weight Management', 'value' => 'weight_management', 'status' => 1],
                ['type' => 'service_type', 'label' => 'Psychology Services', 'value' => 'psychology_services', 'status' => 1]
            ];
            
            foreach ($service_types as $service_type) {
                KCStaticData::create($service_type);
            }
        }
    }
}
