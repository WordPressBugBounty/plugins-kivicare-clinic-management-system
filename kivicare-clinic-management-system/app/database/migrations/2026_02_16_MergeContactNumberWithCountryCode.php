<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');

class MergeContactNumberWithCountryCode extends KCAbstractMigration {
    public function run() {
        global $wpdb;
        $clinics_table = esc_sql($wpdb->prefix . 'kc_clinics');

        // Merge country_calling_code and telephone_no into telephone_no for clinics
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(" UPDATE {$clinics_table} SET telephone_no =  CASE  
            WHEN country_calling_code IS NOT NULL AND country_calling_code != '' 
            THEN CONCAT(
                CASE WHEN country_calling_code LIKE '+%' THEN '' ELSE '+' END, 
                country_calling_code, 
                ' ', 
                telephone_no
            ) 
            ELSE telephone_no END 
        WHERE telephone_no NOT LIKE '+%' "); 

        // Handle user meta data - merge country_calling_code into basic_data.mobile_number
        $usermeta_table = esc_sql($wpdb->prefix . 'usermeta');
        
        // Get all users with basic_data
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $users_with_basic_data = $wpdb->get_results(" SELECT user_id, meta_value FROM {$usermeta_table} WHERE meta_key = 'basic_data' ");

        // Process each user's basic_data
        foreach ($users_with_basic_data as $user_data) {
            $basic_data = json_decode($user_data->meta_value, true);
            
            // Only process if basic_data is valid JSON and has mobile_number
            if (is_array($basic_data) && isset($basic_data['mobile_number'])) {
                
                // Get country_calling_code from usermeta
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $country_code = $wpdb->get_var($wpdb->prepare( "SELECT meta_value  FROM {$usermeta_table} WHERE user_id = %d 
                    AND meta_key = 'country_calling_code'",
                    $user_data->user_id
                ));
                
                // If country code exists and is not empty, merge it with mobile number
                // If country code exists and is not empty, merge it with mobile number
                // If country code exists and is not empty, merge it with mobile number
                if (!empty($country_code)) {
                    $raw_code = ltrim($country_code, '+'); // e.g., "91"
                    $mobile = $basic_data['mobile_number'];

                    // Regex to identify country code at the start:
                    // ^       : Start of string
                    // (\+)?   : Optional plus sign
                    // code    : The country code digits
                    // \s*     : Optional whitespace
                    $pattern = '/^(\+)?' . preg_quote($raw_code, '/') . '\s*/';

                    // remove all leading instances of the country code to fix any previous duplication errors
                    // e.g. "91 91 9898..." -> "9898..."
                    while (preg_match($pattern, $mobile)) {
                        $mobile = preg_replace($pattern, '', $mobile, 1);
                    }
                    
                    // Rebuild with strict format: +<code> <mobile>
                    $new_mobile = '+' . $raw_code . ' ' . $mobile;
                    
                    // Only update if the value has changed
                    if ($new_mobile !== $basic_data['mobile_number']) {
                        $basic_data['mobile_number'] = $new_mobile;
                        
                        // Update the basic_data meta field
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        $wpdb->update( $usermeta_table, ['meta_value' => json_encode($basic_data)], [ 'user_id' => $user_data->user_id, 'meta_key' => 'basic_data' ] );
                    }
                }
            }
        }

        // Optional: Drop the country_calling_code column if you don't need it anymore
        // $wpdb->query("ALTER TABLE {$clinics_table} DROP COLUMN country_calling_code");
    }

    public function rollback() {
        global $wpdb;
        $clinics_table = esc_sql($wpdb->prefix . 'kc_clinics');
        $usermeta_table = esc_sql($wpdb->prefix . 'usermeta');
        
        // for clinics: Since we can't accurately split back the numbers for all cases,
        // we'll create a backup copy of the telephone_no column and note that rollback happened 
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(" UPDATE {$clinics_table} SET telephone_no = CONCAT('ROLLBACK_NOTE: Unable to restore original format - ', telephone_no) ");
        
        // For users with basic_data
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $users_with_basic_data = $wpdb->get_results(" SELECT user_id, meta_value FROM {$usermeta_table} WHERE meta_key = 'basic_data' ");
        
        // Process each user's basic_data
        foreach ($users_with_basic_data as $user_data) {
            $basic_data = json_decode($user_data->meta_value, true);
            
            // Only process if basic_data is valid JSON and has mobile_number
            if (is_array($basic_data) && isset($basic_data['mobile_number'])) {
                $mobile = $basic_data['mobile_number'];
                
                // Try to extract country code - assume first part of the number before a space
                // This is imperfect but reasonable assumption based on how we merged them
                if (strpos($mobile, ' ') !== false) {
                    $parts = explode(' ', $mobile, 2);
                    $country_code = $parts[0];
                    $mobile_number = $parts[1];
                    
                    // Update basic_data with just the mobile number part
                    $basic_data['mobile_number'] = $mobile_number;
                    
                    // Save the modified basic_data
                    $wpdb->update(
                        $usermeta_table,
                        ['meta_value' => json_encode($basic_data)],
                        [
                            'user_id' => $user_data->user_id,
                            'meta_key' => 'basic_data'
                        ]
                    );
                    
                    // Store the country code back to its own meta field
                    update_user_meta($user_data->user_id, 'country_calling_code', $country_code);
                }
            }
        }
        
        return true;
    }
}