<?php

namespace KiviCare\Migrations;

use App\baseClasses\KCErrorLogger;
use App\database\classes\KCAbstractMigration;

defined('ABSPATH') or die('Something went wrong');

/**
 * Optimization migration for KiviCare Core Plugin tables
 * Adds proper indexing and optimization to existing core tables
 */
class OptimizeKiviCareCoreTables extends KCAbstractMigration 
{
    /**
     * Run the migration - Add indexes and optimize existing tables
     */
    public function run() 
    {
        $this->optimizeAppointmentsTable();
        $this->optimizeAppointmentMappingTables();
        $this->optimizeBillsTables();
        $this->optimizeClinicsTable();
        $this->optimizeClinicMappingTables();
        $this->optimizeCustomFieldsTables();
        $this->optimizeDoctorMappingTables();
        $this->optimizeMedicalTables();
        $this->optimizePatientTables();
        $this->optimizePrescriptionTables();
        $this->optimizeServiceTables();
        $this->optimizeStaticDataTable();
        $this->optimizeTaxesTables();
        $this->optimizeWebhookTables();
        $this->optimizeNotificationTables();
    }

    /**
     * Rollback the migration - Remove indexes
     */
    public function rollback() 
    {
        $this->removeOptimizationsFromAllTables();
    }

    /**
     * Optimize appointments table
     */
    private function optimizeAppointmentsTable() 
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_appointments';
        
        if (!$this->tableExists($table_name)) return;

        $indexes = [
            'idx_appointment_start_date' => 'appointment_start_date',
            'idx_appointment_end_date' => 'appointment_end_date',
            'idx_clinic_id' => 'clinic_id',
            'idx_doctor_id' => 'doctor_id',
            'idx_patient_id' => 'patient_id',
            'idx_status' => 'status',
            'idx_created_at' => 'created_at',
            'idx_visit_type' => 'visit_type',
            'idx_doctor_date' => 'doctor_id, appointment_start_date',
            'idx_clinic_date' => 'clinic_id, appointment_start_date',
            'idx_patient_date' => 'patient_id, appointment_start_date',
            'idx_status_date' => 'status, appointment_start_date'
        ];

        $this->addIndexesToTable($table_name, $indexes);
    }

    /**
     * Optimize appointment mapping tables
     */
    private function optimizeAppointmentMappingTables() 
    {
        global $wpdb;
        
        // Appointment reminder mapping
        $reminder_table = $wpdb->prefix . 'kc_appointment_reminder_mapping';
        if ($this->tableExists($reminder_table)) {
            $indexes = [
                'idx_appointment_id' => 'appointment_id',
                'idx_msg_send_date' => 'msg_send_date',
                'idx_email_status' => 'email_status',
                'idx_sms_status' => 'sms_status',
                'idx_whatsapp_status' => 'whatsapp_status',
                'idx_appointment_date' => 'appointment_id, msg_send_date'
            ];
            $this->addIndexesToTable($reminder_table, $indexes);
        }

        // Appointment service mapping
        $service_table = $wpdb->prefix . 'kc_appointment_service_mapping';
        if ($this->tableExists($service_table)) {
            $indexes = [
                'idx_appointment_id' => 'appointment_id',
                'idx_service_id' => 'service_id',
                'idx_status' => 'status',
                'idx_created_at' => 'created_at',
                'idx_appointment_service' => 'appointment_id, service_id'
            ];
            $this->addIndexesToTable($service_table, $indexes);
        }

        // Google Calendar mapping
        $gcal_table = $wpdb->prefix . 'kc_gcal_appointment_mapping';
        if ($this->tableExists($gcal_table)) {
            $indexes = [
                'idx_appointment_id' => 'appointment_id',
                'idx_doctor_id' => 'doctor_id',
                'idx_event_key' => 'event_key',
                'idx_appointment_doctor' => 'appointment_id, doctor_id'
            ];
            $this->addIndexesToTable($gcal_table, $indexes);
        }
    }

    /**
     * Optimize bills tables
     */
    private function optimizeBillsTables() 
    {
        global $wpdb;
        
        // Bills table
        $bills_table = $wpdb->prefix . 'kc_bills';
        if ($this->tableExists($bills_table)) {
            $indexes = [
                'idx_encounter_id' => 'encounter_id',
                'idx_appointment_id' => 'appointment_id',
                'idx_clinic_id' => 'clinic_id',
                'idx_status' => 'status',
                'idx_payment_status' => 'payment_status',
                'idx_created_at' => 'created_at',
                'idx_clinic_date' => 'clinic_id, created_at',
                'idx_encounter_status' => 'encounter_id, status'
            ];
            $this->addIndexesToTable($bills_table, $indexes);
        }

        // Bill items table
        $bill_items_table = $wpdb->prefix . 'kc_bill_items';
        if ($this->tableExists($bill_items_table)) {
            $indexes = [
                'idx_bill_id' => 'bill_id',
                'idx_item_id' => 'item_id',
                'idx_created_at' => 'created_at',
                'idx_bill_item' => 'bill_id, item_id'
            ];
            $this->addIndexesToTable($bill_items_table, $indexes);
        }
    }

    /**
     * Optimize clinics table
     */
    private function optimizeClinicsTable() 
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_clinics';
        
        if (!$this->tableExists($table_name)) return;

        $indexes = [
            'idx_clinic_admin_id' => 'clinic_admin_id',
            'idx_status' => 'status',
            'idx_email' => 'email',
            'idx_telephone_no' => 'telephone_no',
            'idx_city' => 'city',
            'idx_state' => 'state',
            'idx_country' => 'country',
            'idx_created_at' => 'created_at',
            'idx_status_admin' => 'status, clinic_admin_id'
        ];

        $this->addIndexesToTable($table_name, $indexes);
    }

    /**
     * Optimize clinic mapping tables
     */
    private function optimizeClinicMappingTables() 
    {
        global $wpdb;
        
        // Clinic schedule
        $schedule_table = $wpdb->prefix . 'kc_clinic_schedule';
        if ($this->tableExists($schedule_table)) {
            $indexes = [
                'idx_module_id' => 'module_id',
                'idx_module_type' => 'module_type',
                'idx_start_date' => 'start_date',
                'idx_end_date' => 'end_date',
                'idx_status' => 'status',
                'idx_created_at' => 'created_at',
                'idx_module_dates' => 'module_id, start_date, end_date',
                'idx_type_status' => 'module_type, status'
            ];
            $this->addIndexesToTable($schedule_table, $indexes);
        }

        // Clinic sessions
        $sessions_table = $wpdb->prefix . 'kc_clinic_sessions';
        if ($this->tableExists($sessions_table)) {
            $indexes = [
                'idx_clinic_id' => 'clinic_id',
                'idx_doctor_id' => 'doctor_id',
                'idx_day' => 'day',
                'idx_parent_id' => 'parent_id',
                'idx_created_at' => 'created_at',
                'idx_clinic_doctor' => 'clinic_id, doctor_id',
                'idx_doctor_day' => 'doctor_id, day'
            ];
            $this->addIndexesToTable($sessions_table, $indexes);
        }
    }

    /**
     * Optimize custom fields tables
     */
    private function optimizeCustomFieldsTables() 
    {
        global $wpdb;
        
        // Custom fields
        $fields_table = $wpdb->prefix . 'kc_custom_fields';
        if ($this->tableExists($fields_table)) {
            $indexes = [
                'idx_module_type' => 'module_type',
                'idx_module_id' => 'module_id',
                'idx_status' => 'status',
                'idx_created_at' => 'created_at',
                'idx_module_status' => 'module_type, status'
            ];
            $this->addIndexesToTable($fields_table, $indexes);
        }

        // Custom field data
        $data_table = $wpdb->prefix . 'kc_custom_fields_data';
        if ($this->tableExists($data_table)) {
            $indexes = [
                'idx_module_type' => 'module_type',
                'idx_module_id' => 'module_id',
                'idx_field_id' => 'field_id',
                'idx_created_at' => 'created_at',
                'idx_module_field' => 'module_id, field_id'
            ];
            $this->addIndexesToTable($data_table, $indexes);
        }
    }

    /**
     * Optimize doctor mapping tables
     */
    private function optimizeDoctorMappingTables() 
    {
        global $wpdb;
        
        $mapping_table = $wpdb->prefix . 'kc_doctor_clinic_mappings';
        if ($this->tableExists($mapping_table)) {
            $indexes = [
                'idx_doctor_id' => 'doctor_id',
                'idx_clinic_id' => 'clinic_id',
                'idx_owner' => 'owner',
                'idx_created_at' => 'created_at',
                'idx_doctor_clinic' => 'doctor_id, clinic_id',
                'idx_clinic_owner' => 'clinic_id, owner'
            ];
            $this->addIndexesToTable($mapping_table, $indexes);
        }
    }

    /**
     * Optimize medical tables
     */
    private function optimizeMedicalTables() 
    {
        global $wpdb;
        
        // Medical history
        $history_table = $wpdb->prefix . 'kc_medical_history';
        if ($this->tableExists($history_table)) {
            $indexes = [
                'idx_encounter_id' => 'encounter_id',
                'idx_patient_id' => 'patient_id',
                'idx_type' => 'type',
                'idx_added_by' => 'added_by',
                'idx_created_at' => 'created_at',
                'idx_is_from_template' => 'is_from_template',
                'idx_patient_type' => 'patient_id, type',
                'idx_encounter_type' => 'encounter_id, type'
            ];
            $this->addIndexesToTable($history_table, $indexes);
        }

        // Medical problems
        $problems_table = $wpdb->prefix . 'kc_medical_problems';
        if ($this->tableExists($problems_table)) {
            $indexes = [
                'idx_encounter_id' => 'encounter_id',
                'idx_patient_id' => 'patient_id',
                'idx_problem_type' => 'problem_type',
                'idx_outcome' => 'outcome',
                'idx_added_by' => 'added_by',
                'idx_start_date' => 'start_date',
                'idx_end_date' => 'end_date',
                'idx_created_at' => 'created_at',
                'idx_patient_problem' => 'patient_id, problem_type'
            ];
            $this->addIndexesToTable($problems_table, $indexes);
        }
    }

    /**
     * Optimize patient tables
     */
    private function optimizePatientTables() 
    {
        global $wpdb;
        
        // Patient clinic mappings
        $mapping_table = $wpdb->prefix . 'kc_patient_clinic_mappings';
        if ($this->tableExists($mapping_table)) {
            $indexes = [
                'idx_patient_id' => 'patient_id',
                'idx_clinic_id' => 'clinic_id',
                'idx_created_at' => 'created_at',
                'idx_patient_clinic' => 'patient_id, clinic_id'
            ];
            $this->addIndexesToTable($mapping_table, $indexes);
        }

        // Patient encounters
        $encounters_table = $wpdb->prefix . 'kc_patient_encounters';
        if ($this->tableExists($encounters_table)) {
            $indexes = [
                'idx_encounter_date' => 'encounter_date',
                'idx_clinic_id' => 'clinic_id',
                'idx_doctor_id' => 'doctor_id',
                'idx_patient_id' => 'patient_id',
                'idx_appointment_id' => 'appointment_id',
                'idx_status' => 'status',
                'idx_added_by' => 'added_by',
                'idx_template_id' => 'template_id',
                'idx_created_at' => 'created_at',
                'idx_patient_doctor' => 'patient_id, doctor_id',
                'idx_clinic_date' => 'clinic_id, encounter_date',
                'idx_status_date' => 'status, encounter_date'
            ];
            $this->addIndexesToTable($encounters_table, $indexes);
        }

        // Patient encounters template mapping
        $template_mapping_table = $wpdb->prefix . 'kc_patient_encounters_template_mapping';
        if ($this->tableExists($template_mapping_table)) {
            $indexes = [
                'idx_status' => 'status',
                'idx_added_by' => 'added_by',
                'idx_created_at' => 'created_at'
            ];
            $this->addIndexesToTable($template_mapping_table, $indexes);
        }

        // Patient encounters template
        $template_table = $wpdb->prefix . 'kc_patient_encounters_template';
        if ($this->tableExists($template_table)) {
            $indexes = [
                'idx_encounters_template_id' => 'encounters_template_id',
                'idx_clinical_detail_type' => 'clinical_detail_type',
                'idx_added_by' => 'added_by',
                'idx_created_at' => 'created_at'
            ];
            $this->addIndexesToTable($template_table, $indexes);
        }

        // Patient medical report
        $report_table = $wpdb->prefix . 'kc_patient_medical_report';
        if ($this->tableExists($report_table)) {
            $indexes = [
                'idx_patient_id' => 'patient_id',
                'idx_date' => 'date',
                'idx_patient_date' => 'patient_id, date'
            ];
            $this->addIndexesToTable($report_table, $indexes);
        }

        // Patient review
        $review_table = $wpdb->prefix . 'kc_patient_review';
        if ($this->tableExists($review_table)) {
            $indexes = [
                'idx_patient_id' => 'patient_id',
                'idx_doctor_id' => 'doctor_id',
                'idx_review' => 'review',
                'idx_created_at' => 'created_at',
                'idx_updated_at' => 'updated_at',
                'idx_patient_doctor' => 'patient_id, doctor_id'
            ];
            $this->addIndexesToTable($review_table, $indexes);
        }

        // Payments appointment mappings
        $payments_table = $wpdb->prefix . 'kc_payments_appointment_mappings';
        if ($this->tableExists($payments_table)) {
            $indexes = [
                'idx_appointment_id' => 'appointment_id',
                'idx_payment_mode' => 'payment_mode',
                'idx_payment_id' => 'payment_id',
                'idx_payment_status' => 'payment_status',
                'idx_created_at' => 'created_at',
                'idx_appointment_status' => 'appointment_id, payment_status'
            ];
            $this->addIndexesToTable($payments_table, $indexes);
        }
    }

    /**
     * Optimize prescription tables
     */
    private function optimizePrescriptionTables() 
    {
        global $wpdb;
        
        // Prescription
        $prescription_table = $wpdb->prefix . 'kc_prescription';
        if ($this->tableExists($prescription_table)) {
            $indexes = [
                'idx_encounter_id' => 'encounter_id',
                'idx_patient_id' => 'patient_id',
                'idx_added_by' => 'added_by',
                'idx_created_at' => 'created_at',
                'idx_is_from_template' => 'is_from_template',
                'idx_patient_encounter' => 'patient_id, encounter_id'
            ];
            $this->addIndexesToTable($prescription_table, $indexes);
        }

        // Prescription encounter template
        $template_table = $wpdb->prefix . 'kc_prescription_enconter_template';
        if ($this->tableExists($template_table)) {
            $indexes = [
                'idx_encounters_template_id' => 'encounters_template_id',
                'idx_added_by' => 'added_by',
                'idx_created_at' => 'created_at',
                'idx_updated_at' => 'updated_at'
            ];
            $this->addIndexesToTable($template_table, $indexes);
        }
    }

    /**
     * Optimize receptionist clinic mappings
     */
    private function optimizeReceptionistMappings() 
    {
        global $wpdb;
        
        $mapping_table = $wpdb->prefix . 'kc_receptionist_clinic_mappings';
        if ($this->tableExists($mapping_table)) {
            $indexes = [
                'idx_receptionist_id' => 'receptionist_id',
                'idx_clinic_id' => 'clinic_id',
                'idx_created_at' => 'created_at',
                'idx_receptionist_clinic' => 'receptionist_id, clinic_id'
            ];
            $this->addIndexesToTable($mapping_table, $indexes);
        }
    }

    /**
     * Optimize service tables
     */
    private function optimizeServiceTables() 
    {
        global $wpdb;
        
        // Services
        $services_table = $wpdb->prefix . 'kc_services';
        if ($this->tableExists($services_table)) {
            $indexes = [
                'idx_type' => 'type',
                'idx_status' => 'status',
                'idx_created_at' => 'created_at',
                'idx_name' => 'name',
                'idx_type_status' => 'type, status'
            ];
            $this->addIndexesToTable($services_table, $indexes);
        }

        // Service doctor mapping
        $mapping_table = $wpdb->prefix . 'kc_service_doctor_mapping';
        if ($this->tableExists($mapping_table)) {
            $indexes = [
                'idx_service_id' => 'service_id',
                'idx_doctor_id' => 'doctor_id',
                'idx_clinic_id' => 'clinic_id',
                'idx_status' => 'status',
                'idx_telemed_service' => 'telemed_service',
                'idx_created_at' => 'created_at',
                'idx_service_doctor' => 'service_id, doctor_id',
                'idx_doctor_clinic' => 'doctor_id, clinic_id',
                'idx_service_status' => 'service_id, status'
            ];
            $this->addIndexesToTable($mapping_table, $indexes);
        }
    }

    /**
     * Optimize static data table
     */
    private function optimizeStaticDataTable() 
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kc_static_data';
        
        if (!$this->tableExists($table_name)) return;

        $indexes = [
            'idx_type' => 'type',
            'idx_parent_id' => 'parent_id',
            'idx_status' => 'status',
            'idx_created_at' => 'created_at',
            'idx_type_status' => 'type, status',
            'idx_parent_status' => 'parent_id, status'
        ];

        $this->addIndexesToTable($table_name, $indexes);
    }

    /**
     * Optimize taxes tables
     */
    private function optimizeTaxesTables() 
    {
        global $wpdb;
        
        // Taxes
        $taxes_table = $wpdb->prefix . 'kc_taxes';
        if ($this->tableExists($taxes_table)) {
            $indexes = [
                'idx_clinic_id' => 'clinic_id',
                'idx_doctor_id' => 'doctor_id',
                'idx_service_id' => 'service_id',
                'idx_added_by' => 'added_by',
                'idx_status' => 'status',
                'idx_tax_type' => 'tax_type',
                'idx_created_at' => 'created_at',
                'idx_clinic_status' => 'clinic_id, status',
                'idx_doctor_status' => 'doctor_id, status'
            ];
            $this->addIndexesToTable($taxes_table, $indexes);
        }

        // Tax data
        $tax_data_table = $wpdb->prefix . 'kc_tax_data';
        if ($this->tableExists($tax_data_table)) {
            $indexes = [
                'idx_module_type' => 'module_type',
                'idx_module_id' => 'module_id',
                'idx_tax_type' => 'tax_type',
                'idx_module_tax' => 'module_id, tax_type'
            ];
            $this->addIndexesToTable($tax_data_table, $indexes);
        }
    }

    /**
     * Optimize webhook tables
     */
    private function optimizeWebhookTables() 
    {
        global $wpdb;
        
        // Webhooks
        $webhooks_table = $wpdb->prefix . 'kc_webhooks';
        if ($this->tableExists($webhooks_table)) {
            $indexes = [
                'idx_module_name' => 'module_name',
                'idx_event_name' => 'event_name',
                'idx_user_id' => 'user_id',
                'idx_status' => 'status',
                'idx_created_at' => 'created_at',
                'idx_updated_at' => 'updated_at',
                'idx_module_event' => 'module_name, event_name',
                'idx_user_status' => 'user_id, status'
            ];
            $this->addIndexesToTable($webhooks_table, $indexes);
        }

        // Webhook logs
        $logs_table = $wpdb->prefix . 'kc_webhooks_logs';
        if ($this->tableExists($logs_table)) {
            $indexes = [
                'idx_module_id' => 'module_id',
                'idx_webhook_id' => 'webhook_id',
                'idx_created_at' => 'created_at',
                'idx_webhook_date' => 'webhook_id, created_at'
            ];
            $this->addIndexesToTable($logs_table, $indexes);
        }
    }

    /**
     * Optimize notification tables
     */
    private function optimizeNotificationTables() 
    {
        global $wpdb;
        
        $notifications_table = $wpdb->prefix . 'kc_custom_notifications';
        if ($this->tableExists($notifications_table)) {
            $indexes = [
                'idx_server_type' => 'server_type',
                'idx_is_active' => 'is_active',
                'idx_created_by' => 'created_by',
                'idx_created_at' => 'created_at',
                'idx_updated_at' => 'updated_at',
                'idx_type_active' => 'server_type, is_active'
            ];
            $this->addIndexesToTable($notifications_table, $indexes);
        }
    }


    /**
     * Helper method to check if table exists
     */
    private function tableExists($table_name) 
    {
        global $wpdb;
        $table_name = esc_sql($table_name);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }

    /**
     * Helper method to add indexes to a table
     */
    private function addIndexesToTable($table_name, $indexes) 
    {
        global $wpdb;
        
        foreach ($indexes as $index_name => $columns) {
            $esc_table_name = esc_sql($table_name);
            $esc_index_name = esc_sql($index_name);
            $esc_columns = esc_sql($columns);
            
            // Check if index exists using SHOW INDEX which is more reliable than INFORMATION_SCHEMA in some environments
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `{$esc_table_name}` WHERE Key_name = '{$esc_index_name}'");

            if (empty($existing_indexes)) {
                // Columns often contain commas and simpler chars, hard to simple-escape, assuming trusted input for now
                // but usually should be safe if hardcoded. For linting, the table/index are key.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("ALTER TABLE {$esc_table_name} ADD INDEX {$esc_index_name} ({$esc_columns})"); 
            }
        }
    }

    /**
     * Remove all optimizations from tables
     */
    private function removeOptimizationsFromAllTables() 
    {
        global $wpdb;
        
        // Define all tables and their custom indexes to remove
        $tables_indexes = [
            $wpdb->prefix . 'kc_appointments' => [
                'idx_appointment_start_date', 'idx_appointment_end_date', 'idx_clinic_id', 
                'idx_doctor_id', 'idx_patient_id', 'idx_status', 'idx_created_at', 
                'idx_visit_type', 'idx_doctor_date', 'idx_clinic_date', 'idx_patient_date', 
                'idx_status_date'
            ],
            $wpdb->prefix . 'kc_appointment_reminder_mapping' => [
                'idx_appointment_id', 'idx_msg_send_date', 'idx_email_status', 'idx_sms_status',
                'idx_whatsapp_status', 'idx_appointment_date'
            ],
            $wpdb->prefix . 'kc_appointment_service_mapping' => [
                'idx_appointment_id', 'idx_service_id', 'idx_status', 'idx_created_at',
                'idx_appointment_service'
            ],
            $wpdb->prefix . 'kc_gcal_appointment_mapping' => [
                'idx_appointment_id', 'idx_doctor_id', 'idx_event_key', 'idx_appointment_doctor'
            ],
            $wpdb->prefix . 'kc_bills' => [
                'idx_encounter_id', 'idx_appointment_id', 'idx_clinic_id', 'idx_status',
                'idx_payment_status', 'idx_created_at', 'idx_clinic_date', 'idx_encounter_status'
            ],
            $wpdb->prefix . 'kc_bill_items' => [
                'idx_bill_id', 'idx_item_id', 'idx_created_at', 'idx_bill_item'
            ],
            $wpdb->prefix . 'kc_clinics' => [
                'idx_clinic_admin_id', 'idx_status', 'idx_email', 'idx_telephone_no',
                'idx_city', 'idx_state', 'idx_country', 'idx_created_at', 'idx_status_admin'
            ],
            $wpdb->prefix . 'kc_clinic_schedule' => [
                'idx_module_id', 'idx_module_type', 'idx_start_date', 'idx_end_date',
                'idx_status', 'idx_created_at', 'idx_module_dates', 'idx_type_status'
            ],
            $wpdb->prefix . 'kc_clinic_sessions' => [
                'idx_clinic_id', 'idx_doctor_id', 'idx_day', 'idx_parent_id',
                'idx_created_at', 'idx_clinic_doctor', 'idx_doctor_day'
            ],
            $wpdb->prefix . 'kc_custom_fields' => [
                'idx_module_type', 'idx_module_id', 'idx_status', 'idx_created_at', 'idx_module_status'
            ],
            $wpdb->prefix . 'kc_custom_fields_data' => [
                'idx_module_type', 'idx_module_id', 'idx_field_id', 'idx_created_at', 'idx_module_field'
            ],
            $wpdb->prefix . 'kc_doctor_clinic_mappings' => [
                'idx_doctor_id', 'idx_clinic_id', 'idx_owner', 'idx_created_at',
                'idx_doctor_clinic', 'idx_clinic_owner'
            ],
            $wpdb->prefix . 'kc_medical_history' => [
                'idx_encounter_id', 'idx_patient_id', 'idx_type', 'idx_added_by',
                'idx_created_at', 'idx_is_from_template', 'idx_patient_type', 'idx_encounter_type'
            ],
            $wpdb->prefix . 'kc_medical_problems' => [
                'idx_encounter_id', 'idx_patient_id', 'idx_problem_type', 'idx_outcome',
                'idx_added_by', 'idx_start_date', 'idx_end_date', 'idx_created_at', 'idx_patient_problem'
            ],
            $wpdb->prefix . 'kc_patient_clinic_mappings' => [
                'idx_patient_id', 'idx_clinic_id', 'idx_created_at', 'idx_patient_clinic'
            ],
            $wpdb->prefix . 'kc_patient_encounters' => [
                'idx_encounter_date', 'idx_clinic_id', 'idx_doctor_id', 'idx_patient_id',
                'idx_appointment_id', 'idx_status', 'idx_added_by', 'idx_template_id',
                'idx_created_at', 'idx_patient_doctor', 'idx_clinic_date', 'idx_status_date'
            ],
            $wpdb->prefix . 'kc_patient_encounters_template_mapping' => [
                'idx_status', 'idx_added_by', 'idx_created_at'
            ],
            $wpdb->prefix . 'kc_patient_encounters_template' => [
                'idx_encounters_template_id', 'idx_clinical_detail_type', 'idx_added_by', 'idx_created_at'
            ],
            $wpdb->prefix . 'kc_patient_medical_report' => [
                'idx_patient_id', 'idx_date', 'idx_patient_date'
            ],
            $wpdb->prefix . 'kc_patient_review' => [
                'idx_patient_id', 'idx_doctor_id', 'idx_review', 'idx_created_at',
                'idx_updated_at', 'idx_patient_doctor'
            ],
            $wpdb->prefix . 'kc_payments_appointment_mappings' => [
                'idx_appointment_id', 'idx_payment_mode', 'idx_payment_id', 'idx_payment_status',
                'idx_created_at', 'idx_appointment_status'
            ],
            $wpdb->prefix . 'kc_prescription' => [
                'idx_encounter_id', 'idx_patient_id', 'idx_added_by', 'idx_created_at',
                'idx_is_from_template', 'idx_patient_encounter'
            ],
            $wpdb->prefix . 'kc_prescription_enconter_template' => [
                'idx_encounters_template_id', 'idx_added_by', 'idx_created_at', 'idx_updated_at'
            ],
            $wpdb->prefix . 'kc_receptionist_clinic_mappings' => [
                'idx_receptionist_id', 'idx_clinic_id', 'idx_created_at', 'idx_receptionist_clinic'
            ],
            $wpdb->prefix . 'kc_services' => [
                'idx_type', 'idx_status', 'idx_created_at', 'idx_name', 'idx_type_status'
            ],
            $wpdb->prefix . 'kc_service_doctor_mapping' => [
                'idx_service_id', 'idx_doctor_id', 'idx_clinic_id', 'idx_status',
                'idx_telemed_service', 'idx_created_at', 'idx_service_doctor',
                'idx_doctor_clinic', 'idx_service_status'
            ],
            $wpdb->prefix . 'kc_static_data' => [
                'idx_type', 'idx_parent_id', 'idx_status', 'idx_created_at',
                'idx_type_status', 'idx_parent_status'
            ],
            $wpdb->prefix . 'kc_taxes' => [
                'idx_clinic_id', 'idx_doctor_id', 'idx_service_id', 'idx_added_by',
                'idx_status', 'idx_tax_type', 'idx_created_at', 'idx_clinic_status', 'idx_doctor_status'
            ],
            $wpdb->prefix . 'kc_tax_data' => [
                'idx_module_type', 'idx_module_id', 'idx_tax_type', 'idx_module_tax'
            ],
            $wpdb->prefix . 'kc_webhooks' => [
                'idx_module_name', 'idx_event_name', 'idx_user_id', 'idx_status',
                'idx_created_at', 'idx_updated_at', 'idx_module_event', 'idx_user_status'
            ],
            $wpdb->prefix . 'kc_webhooks_logs' => [
                'idx_module_id', 'idx_webhook_id', 'idx_created_at', 'idx_webhook_date'
            ],
            $wpdb->prefix . 'kc_custom_notifications' => [
                'idx_server_type', 'idx_is_active', 'idx_created_by', 'idx_created_at',
                'idx_updated_at', 'idx_type_active'
            ],
        ];

        // Remove indexes from each table
        foreach ($tables_indexes as $table_name => $indexes) {
            if ($this->tableExists($table_name)) {
                $this->removeIndexesFromTable($table_name, $indexes);
            }
        }
    }

    /**
     * Helper method to remove indexes from a table
     */
    private function removeIndexesFromTable($table_name, $indexes) 
    {
        global $wpdb;
        
        foreach ($indexes as $index_name) {
            $esc_table_name = esc_sql($table_name);
            $esc_index_name = esc_sql($index_name);

            // Check if index exists before trying to remove it
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $index_exists = $wpdb->get_var(" SELECT COUNT(*)  FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$esc_table_name}' AND INDEX_NAME = '{$esc_index_name}' "); 

            if ($index_exists) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $result = $wpdb->query("ALTER TABLE {$esc_table_name} DROP INDEX {$esc_index_name}");
                if ($result === false) {
                    KCErrorLogger::instance()->error("Failed to drop index {$index_name} from table {$table_name}");
                }
            }
        }
    }
}