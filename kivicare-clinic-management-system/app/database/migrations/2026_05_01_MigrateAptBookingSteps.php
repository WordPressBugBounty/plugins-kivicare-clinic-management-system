<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');

class MigrateAptBookingSteps extends KCAbstractMigration
{
    /**
     * Run the migration.
     * 
     * Migrates the appointment widget step order to ensure 'file-uploads-custom' 
     * comes after 'detail-info'.
     *
     * @return void
     */
    public function run()
    {
        if (empty(get_option(KIVI_CARE_PREFIX . 'is_appointment_widget_migrated'))) {
            // Migrate appointment widget order if needed
            $widget_order_list = get_option(KIVI_CARE_PREFIX . 'widget_order_list');
            if (!empty($widget_order_list) && is_array($widget_order_list)) {
                $detail_info_index = -1;
                $file_uploads_custom_index = -1;
                
                foreach ($widget_order_list as $index => $step) {
                    if (isset($step['att_name']) && $step['att_name'] === 'detail-info') {
                        $detail_info_index = $index;
                    } elseif (isset($step['att_name']) && $step['att_name'] === 'file-uploads-custom') {
                        $file_uploads_custom_index = $index;
                    }
                }

                if ($detail_info_index !== -1 && $file_uploads_custom_index !== -1 && $file_uploads_custom_index < $detail_info_index) {
                    $file_uploads_custom_step = $widget_order_list[$file_uploads_custom_index];
                    unset($widget_order_list[$file_uploads_custom_index]);
                    $widget_order_list = array_values($widget_order_list);

                    // Re-find detail-info index as it might have shifted
                    foreach ($widget_order_list as $index => $step) {
                        if (isset($step['att_name']) && $step['att_name'] === 'detail-info') {
                            $detail_info_index = $index;
                            break;
                        }
                    }

                    array_splice($widget_order_list, $detail_info_index + 1, 0, [$file_uploads_custom_step]);
                    update_option(KIVI_CARE_PREFIX . 'widget_order_list', $widget_order_list);
                }
            }
            update_option(KIVI_CARE_PREFIX . 'is_appointment_widget_migrated', 'yes');
        }
    }

    /**
     * Rollback the migration.
     * 
     * @return void
     */
    public function rollback()
    {
        // No automated rollback for this data migration
    }
}
