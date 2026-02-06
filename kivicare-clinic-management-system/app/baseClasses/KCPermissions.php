<?php

namespace App\baseClasses;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class KCPermissions
 * 
 * Handles role-based permissions for KiviCare plugin
 * 
 * @package App\baseClasses
 */
class KCPermissions
{

    /**
     * @var array Custom capabilities for each role with status
     */
    private static $role_capabilities = [
        KIVI_CARE_PREFIX . 'patient'    => [
            'read'                      => ['status' => 1],
            'dashboard'                 => ['status' => 1],
            'patient_dashboard'         => ['status' => 1],
            'patient_profile'           => ['status' => 1],
            'change_password'           => ['status' => 1],
            'service_list'              => ['status' => 0],
            'appointment_list'          => ['status' => 1],
            'appointment_add'           => ['status' => 1],
            'appointment_edit'          => ['status' => 1],
            'appointment_view'          => ['status' => 1],
            'appointment_cancel'        => ['status' => 1],
            'appointment_export'        => ['status' => 1],
            'patient_clinic'            => ['status' => 1],
            'patient_report'            => ['status' => 1],
            'patient_report_add'        => ['status' => 1],
            'patient_report_view'       => ['status' => 1],
            'patient_report_delete'     => ['status' => 1],
            'patient_report_edit'       => ['status' => 1],
            'patient_encounters'        => ['status' => 1],
            'patient_encounter_list'    => ['status' => 1],
            'patient_encounter_view'    => ['status' => 1],
            'patient_encounter_view_billing' => ['status' => 1],
            'patient_encounter_export'  => ['status' => 1],
            'medical_records_list'      => ['status' => 1],
            'medical_records_view'      => ['status' => 1],
            'prescription_list'         => ['status' => 1],
            'prescription_view'         => ['status' => 1],
            'prescription_export'       => ['status' => 1],
            'patient_bill_list'         => ['status' => 1],
            'patient_bill_view'         => ['status' => 1],
            'patient_bill_export'       => ['status' => 1],
            'patient_review_add'        => ['status' => 1],
            'patient_review_edit'       => ['status' => 1],
            'patient_review_delete'     => ['status' => 1],
            'patient_review_get'        => ['status' => 1],
            'home_page'                 => ['status' => 1],
        ],
        KIVI_CARE_PREFIX . 'doctor' => [
            'read'                              => ['status' => 1],
            'dashboard'                         => ['status' => 1],
            'doctor_dashboard'                  => ['status' => 1],
            'settings_view'                     => ['status' => 1],
            'doctor_profile'                    => ['status' => 1],
            'change_password'                   => ['status' => 1],
            'appointment_list'                  => ['status' => 1],
            'appointment_add'                   => ['status' => 1],
            'appointment_edit'                  => ['status' => 1],
            'appointment_view'                  => ['status' => 1],
            'appointment_delete'                => ['status' => 1],
            'appointment_export'                => ['status' => 1],
            'patient_report'                    => ['status' => 1],
            'patient_export'                    => ['status' => 1],
            'patient_report_add'                => ['status' => 1],
            'patient_report_view'               => ['status' => 1],
            'patient_report_delete'             => ['status' => 1],
            'patient_report_edit'               => ['status' => 1],
            'doctor_session_list'               => ['status' => 1],
            'doctor_session_add'                => ['status' => 1],
            'doctor_session_edit'               => ['status' => 1],
            'doctor_session_delete'             => ['status' => 1],
            'doctor_session_export'             => ['status' => 1],
            'static_data_list'                  => ['status' => 1],
            'static_data_add'                   => ['status' => 1],
            'static_data_edit'                  => ['status' => 1],
            'static_data_view'                  => ['status' => 1],
            'static_data_export'                => ['status' => 1],
            'clinic_schedule'                   => ['status' => 1],
            'clinic_schedule_add'               => ['status' => 1],
            'clinic_schedule_edit'              => ['status' => 1],
            'clinic_schedule_delete'            => ['status' => 1],
            'clinic_schedule_export'            => ['status' => 1],
            'service_list'                      => ['status' => 1],
            'service_add'                       => ['status' => 1],
            'service_edit'                      => ['status' => 1],
            'service_view'                      => ['status' => 1],
            'service_delete'                    => ['status' => 1],
            'service_export'                    => ['status' => 1],
            'custom_field_list'                 => ['status' => 1],
            'custom_field_add'                  => ['status' => 1],
            'custom_field_edit'                 => ['status' => 1],
            'custom_field_view'                 => ['status' => 1],
            'custom_field_delete'               => ['status' => 1],
            'patient_encounters'                => ['status' => 1],
            'patient_encounter_list'            => ['status' => 1],
            'patient_encounter_add'             => ['status' => 1],
            'patient_encounter_edit'            => ['status' => 1],
            'patient_encounter_view'            => ['status' => 1],
            'patient_encounter_view_billing'    => ['status' => 1],
            'patient_encounter_delete'          => ['status' => 1],
            'patient_encounter_export'          => ['status' => 1],
            'encounters_template_list'          => ['status' => 1],
            'encounters_template_add'           => ['status' => 1],
            'encounters_template_edit'          => ['status' => 1],
            'encounters_template_view'          => ['status' => 1],
            'encounters_template_delete'        => ['status' => 1],
            'patient_appointment_status_change' => ['status' => 1],
            'patient_list'                      => ['status' => 1],
            'patient_add'                       => ['status' => 1],
            'patient_edit'                      => ['status' => 1],
            'patient_view'                      => ['status' => 1],
            'patient_delete'                    => ['status' => 1],
            'patient_profile'                   => ['status' => 1],
            'patient_appointment'               => ['status' => 1],
            'patient_appointment_report'        => ['status' => 1],
            'patient_encounter'                 => ['status' => 1],
            'patient_resend_credential'         => ['status' => 1],
            'medical_records_list'              => ['status' => 1],
            'medical_records_add'               => ['status' => 1],
            'medical_records_edit'              => ['status' => 1],
            'medical_records_view'              => ['status' => 1],
            'medical_records_delete'            => ['status' => 1],
            'prescription_list'                 => ['status' => 1],
            'prescription_add'                  => ['status' => 1],
            'prescription_edit'                 => ['status' => 1],
            'prescription_view'                 => ['status' => 1],
            'prescription_export'               => ['status' => 1],
            'prescription_delete'               => ['status' => 1],
            'patient_bill_list'                 => ['status' => 1],
            'patient_bill_add'                  => ['status' => 1],
            'patient_bill_edit'                 => ['status' => 1],
            'patient_bill_view'                 => ['status' => 1],
            'patient_bill_delete'               => ['status' => 1],
            'patient_bill_export'               => ['status' => 1],
            'patient_review_get'                => ['status' => 1],
            'dashboard_total_patient'           => ['status' => 1],
            'dashboard_total_appointment'       => ['status' => 1],
            'dashboard_total_today_appointment' => ['status' => 1],
            'dashboard_total_service'           => ['status' => 1],
            'dashboard_total_clinic'            => ['status' => 1],
        ],
        KIVI_CARE_PREFIX . 'receptionist' => [
            'read'                              => ['status' => 1],
            'settings_view'                     => ['status' => 1],
            'dashboard'                         => ['status' => 1],
            'receptionist_dashboard'            => ['status' => 1],
            'receptionist_profile'              => ['status' => 1],
            'change_password'                   => ['status' => 1],
            'doctor_list'                       => ['status' => 1],
            'doctor_add'                        => ['status' => 1],
            'doctor_edit'                       => ['status' => 1],
            'doctor_view'                       => ['status' => 1],
            'doctor_delete'                     => ['status' => 1],
            'doctor_export'                     => ['status' => 1],
            'doctor_service'                    => ['status' => 1],
            'doctor_session'                    => ['status' => 1],
            'doctor_resend_credential'          => ['status' => 1],
            'patient_list'                      => ['status' => 1],
            'patient_add'                       => ['status' => 1],
            'patient_edit'                      => ['status' => 1],
            'patient_view'                      => ['status' => 1],
            'patient_delete'                    => ['status' => 1],
            'patient_profile'                   => ['status' => 1],
            'patient_appointment'               => ['status' => 1],
            'patient_appointment_report'        => ['status' => 1],
            'patient_encounter'                 => ['status' => 1],
            'patient_resend_credential'         => ['status' => 1],
            'patient_export'                    => ['status' => 1],
            'patient_review_get'                => ['status' => 1],
            'clinic_list'                       => ['status' => 0],
            'clinic_add'                        => ['status' => 0],
            'clinic_edit'                       => ['status' => 0],
            'clinic_view'                       => ['status' => 0],
            'clinic_delete'                     => ['status' => 0],
            'clinic_profile'                    => ['status' => 0],
            'clinic_resend_credential'          => ['status' => 1],
            'clinic_export'                     => ['status' => 1],
            'service_list'                      => ['status' => 1],
            'service_add'                       => ['status' => 1],
            'service_edit'                      => ['status' => 1],
            'service_view'                      => ['status' => 1],
            'service_delete'                    => ['status' => 1],
            'service_export'                    => ['status' => 1],
            'appointment_list'                  => ['status' => 1],
            'appointment_add'                   => ['status' => 1],
            'appointment_edit'                  => ['status' => 1],
            'appointment_view'                  => ['status' => 1],
            'appointment_delete'                => ['status' => 1],
            'appointment_export'                => ['status' => 1],
            'patient_encounters'                => ['status' => 1],
            'patient_encounter_list'            => ['status' => 1],
            'patient_encounter_add'             => ['status' => 1],
            'patient_encounter_edit'            => ['status' => 1],
            'patient_encounter_view'            => ['status' => 1],
            'patient_encounter_view_billing'    => ['status' => 1],
            'patient_encounter_delete'          => ['status' => 1],
            'patient_encounter_export'          => ['status' => 1],
            'encounters_template_list'          => ['status' => 1],
            'encounters_template_add'           => ['status' => 1],
            'encounters_template_edit'          => ['status' => 1],
            'encounters_template_view'          => ['status' => 1],
            'encounters_template_delete'        => ['status' => 1],
            'patient_appointment_status_change' => ['status' => 1],
            'medical_records_list'              => ['status' => 1],
            'medical_records_add'               => ['status' => 1],
            'medical_records_edit'              => ['status' => 1],
            'medical_records_view'              => ['status' => 1],
            'medical_records_delete'            => ['status' => 1],
            'prescription_list'                 => ['status' => 1],
            'prescription_add'                  => ['status' => 1],
            'prescription_edit'                 => ['status' => 1],
            'prescription_view'                 => ['status' => 1],
            'prescription_export'               => ['status' => 1],
            'prescription_delete'               => ['status' => 1],
            'patient_bill_list'                 => ['status' => 1],
            'patient_bill_add'                  => ['status' => 1],
            'patient_bill_edit'                 => ['status' => 1],
            'patient_bill_view'                 => ['status' => 1],
            'patient_bill_delete'               => ['status' => 1],
            'patient_bill_export'               => ['status' => 1],
            'clinic_schedule'                   => ['status' => 1],
            'clinic_schedule_add'               => ['status' => 1],
            'clinic_schedule_edit'              => ['status' => 1],
            'clinic_schedule_delete'            => ['status' => 1],
            'clinic_schedule_export'            => ['status' => 1],
            'patient_report'                    => ['status' => 1],
            'patient_report_add'                => ['status' => 1],
            'patient_report_view'               => ['status' => 1],
            'patient_report_delete'             => ['status' => 1],
            'patient_report_edit'               => ['status' => 1],
            'doctor_session_list'               => ['status' => 1],
            'doctor_session_add'                => ['status' => 1],
            'doctor_session_edit'               => ['status' => 1],
            'doctor_session_delete'             => ['status' => 1],
            'doctor_session_export'             => ['status' => 1],
            'static_data_list'                  => ['status' => 1],
            'static_data_add'                   => ['status' => 1],
            'static_data_edit'                  => ['status' => 1],
            'static_data_view'                  => ['status' => 1],
            'static_data_export'                => ['status' => 1],
            'dashboard_total_patient'           => ['status' => 1],
            'dashboard_total_doctor'            => ['status' => 1],
            'dashboard_total_appointment'       => ['status' => 1],
            'dashboard_total_revenue'           => ['status' => 1],
            'dashboard_total_service'           => ['status' => 1],
            'dashboard_total_clinic'            => ['status' => 1],
        ],
        KIVI_CARE_PREFIX . 'clinic_admin' => [
            'read'                              => ['status' => 1],
            'dashboard'                         => ['status' => 1],
            'setting'                           => ['status' => 1],
            'clinic_admin_dashboard'            => ['status' => 1],
            'doctor_list'                       => ['status' => 1],
            'doctor_add'                        => ['status' => 1],
            'doctor_edit'                       => ['status' => 1],
            'doctor_view'                       => ['status' => 1],
            'doctor_delete'                     => ['status' => 1],
            'doctor_export'                     => ['status' => 1],
            'doctor_resend_credential'          => ['status' => 1],
            'doctor_service'                    => ['status' => 1],
            'doctor_session'                    => ['status' => 1],
            'receptionist_list'                 => ['status' => 1],
            'receptionist_add'                  => ['status' => 1],
            'receptionist_edit'                 => ['status' => 1],
            'receptionist_view'                 => ['status' => 1],
            'receptionist_delete'               => ['status' => 1],
            'receptionist_resend_credential'    => ['status' => 1],
            'receptionist_export'               => ['status' => 1],
            'patient_list'                      => ['status' => 1],
            'patient_add'                       => ['status' => 1],
            'patient_edit'                      => ['status' => 1],
            'patient_view'                      => ['status' => 1],
            'patient_delete'                    => ['status' => 1],
            'patient_profile'                   => ['status' => 1],
            'patient_appointment'               => ['status' => 1],
            'patient_appointment_report'        => ['status' => 1],
            'patient_encounter'                 => ['status' => 1],
            'patient_export'                    => ['status' => 1],
            'patient_resend_credential'         => ['status' => 1],
            'clinic_list'                       => ['status' => 0],
            'clinic_add'                        => ['status' => 0],
            'clinic_edit'                       => ['status' => 0],
            'clinic_view'                       => ['status' => 1],
            'clinic_delete'                     => ['status' => 1],
            'clinic_profile'                    => ['status' => 1],
            'clinic_resend_credential'          => ['status' => 1],
            'clinic_export'                     => ['status' => 1],
            'appointment_list'                  => ['status' => 1],
            'appointment_add'                   => ['status' => 1],
            'appointment_edit'                  => ['status' => 1],
            'appointment_view'                  => ['status' => 1],
            'appointment_delete'                => ['status' => 1],
            'appointment_export'                => ['status' => 1],
            'service_list'                      => ['status' => 1],
            'service_add'                       => ['status' => 1],
            'service_edit'                      => ['status' => 1],
            'service_view'                      => ['status' => 1],
            'service_delete'                    => ['status' => 1],
            'service_export'                    => ['status' => 1],
            'settings_view'                     => ['status' => 1],
            'tax_list'                          => ['status' => 1],
            'tax_add'                           => ['status' => 1],
            'tax_edit'                          => ['status' => 1],
            'tax_delete'                        => ['status' => 1],
            'tax_export'                        => ['status' => 1],
            'patient_report'                    => ['status' => 1],
            'patient_report_add'                => ['status' => 1],
            'patient_report_view'               => ['status' => 1],
            'patient_report_delete'             => ['status' => 1],
            'patient_report_edit'               => ['status' => 1],
            'doctor_session_list'               => ['status' => 1],
            'doctor_session_add'                => ['status' => 1],
            'doctor_session_edit'               => ['status' => 1],
            'doctor_session_delete'             => ['status' => 1],
            'doctor_session_export'             => ['status' => 1],
            'custom_form_list'                  => ['status' => 1],
            'custom_form_add'                   => ['status' => 1],
            'custom_form_edit'                  => ['status' => 1],
            'custom_form_view'                  => ['status' => 1],
            'custom_form_delete'                => ['status' => 1],
            'service_view'                      => ['status' => 1],
            'settings_edit'                     => ['status' => 1],
            'static_data_list'                  => ['status' => 1],
            'static_data_add'                   => ['status' => 1],
            'static_data_edit'                  => ['status' => 1],
            'static_data_view'                  => ['status' => 1],
            'static_data_export'                => ['status' => 1],
            'patient_encounters'                => ['status' => 1],
            'patient_encounter_list'            => ['status' => 1],
            'patient_encounter_add'             => ['status' => 1],
            'patient_encounter_edit'            => ['status' => 1],
            'patient_encounter_view'            => ['status' => 1],
            'patient_encounter_view_billing'    => ['status' => 1],
            'patient_encounter_delete'          => ['status' => 1],
            'patient_encounter_export'          => ['status' => 1],
            'encounters_template_list'          => ['status' => 1],
            'encounters_template_add'           => ['status' => 1],
            'encounters_template_edit'          => ['status' => 1],
            'encounters_template_view'          => ['status' => 1],
            'encounters_template_delete'        => ['status' => 1],
            'patient_appointment_status_change' => ['status' => 1],
            'medical_records_list'              => ['status' => 1],
            'medical_records_add'               => ['status' => 1],
            'medical_records_edit'              => ['status' => 1],
            'medical_records_view'              => ['status' => 1],
            'medical_records_delete'            => ['status' => 1],
            'prescription_list'                 => ['status' => 1],
            'prescription_add'                  => ['status' => 1],
            'prescription_edit'                 => ['status' => 1],
            'prescription_view'                 => ['status' => 1],
            'prescription_export'               => ['status' => 1],
            'prescription_delete'               => ['status' => 1],
            'patient_bill_list'                 => ['status' => 1],
            'patient_bill_add'                  => ['status' => 1],
            'patient_bill_edit'                 => ['status' => 1],
            'patient_bill_view'                 => ['status' => 1],
            'patient_bill_delete'               => ['status' => 1],
            'patient_bill_export'               => ['status' => 1],
            'patient_review_delete'             => ['status' => 1],
            'patient_review_get'                => ['status' => 1],
            'custom_field_list'                 => ['status' => 1],
            'custom_field_add'                  => ['status' => 1],
            'custom_field_edit'                 => ['status' => 1],
            'custom_field_view'                 => ['status' => 1],
            'custom_field_delete'               => ['status' => 1],
            'custom_field_export'               => ['status' => 1],
            'terms_condition'                   => ['status' => 1],
            'clinic_schedule'                   => ['status' => 1],
            'clinic_schedule_add'               => ['status' => 1],
            'clinic_schedule_edit'              => ['status' => 1],
            'clinic_schedule_delete'            => ['status' => 1],
            'clinic_schedule_export'            => ['status' => 1],
            'common_settings'                   => ['status' => 1],
            'notification_setting'              => ['status' => 1],
            'change_password'                   => ['status' => 1],
            'dashboard_total_patient'           => ['status' => 1],
            'dashboard_total_doctor'            => ['status' => 1],
            'dashboard_total_appointment'       => ['status' => 1],
            'dashboard_total_revenue'           => ['status' => 1],
            'dashboard_total_service'           => ['status' => 1],
            'dashboard_total_clinic'            => ['status' => 1],
        ],
        'admin' => [
            'read'                              => ['status' => 1],
            'dashboard'                         => ['status' => 1],
            'admin_dashboard'                   => ['status' => 1],
            'setting'                           => ['status' => 1],
            'doctor_list'                       => ['status' => 1],
            'doctor_add'                        => ['status' => 1],
            'doctor_edit'                       => ['status' => 1],
            'doctor_view'                       => ['status' => 1],
            'webhook_list'                      => ['status' => 1],
            'webhook_add'                       => ['status' => 1],
            'webhook_edit'                      => ['status' => 1],
            'webhook_delete'                    => ['status' => 1],
            'webhook_view'                      => ['status' => 1],
            'doctor_delete'                     => ['status' => 1],
            'doctor_export'                     => ['status' => 1],
            'doctor_resend_credential'          => ['status' => 1],            
            'doctor_service'                    => ['status' => 1],
            'doctor_session'                    => ['status' => 1],
            'receptionist_list'                 => ['status' => 1],
            'receptionist_add'                  => ['status' => 1],
            'receptionist_edit'                 => ['status' => 1],
            'receptionist_view'                 => ['status' => 1],
            'receptionist_delete'               => ['status' => 1],
            'receptionist_resend_credential'    => ['status' => 1],
            'receptionist_export'               => ['status' => 1],
            'patient_list'                      => ['status' => 1],
            'patient_add'                       => ['status' => 1],
            'patient_edit'                      => ['status' => 1],
            'patient_view'                      => ['status' => 1],
            'patient_delete'                    => ['status' => 1],
            'patient_profile'                   => ['status' => 1],
            'patient_appointment'               => ['status' => 1],
            'patient_appointment_report'        => ['status' => 1],
            'patient_encounter'                 => ['status' => 1],
            'patient_resend_credential'         => ['status' => 1],
            'patient_export'                    => ['status' => 1],
            'clinic_list'                       => ['status' => 1],
            'clinic_add'                        => ['status' => 1],
            'clinic_edit'                       => ['status' => 1],
            'clinic_view'                       => ['status' => 1],
            'clinic_delete'                     => ['status' => 1],
            'clinic_profile'                    => ['status' => 1],
            'clinic_resend_credential'          => ['status' => 1],
            'clinic_export'                     => ['status' => 1],
            'appointment_list'                  => ['status' => 1],
            'appointment_add'                   => ['status' => 1],
            'appointment_edit'                  => ['status' => 1],
            'appointment_view'                  => ['status' => 1],
            'appointment_delete'                => ['status' => 1],
            'appointment_export'                => ['status' => 1],
            'service_list'                      => ['status' => 1],
            'service_add'                       => ['status' => 1],
            'service_edit'                      => ['status' => 1],
            'settings_view'                     => ['status' => 1],
            'service_delete'                    => ['status' => 1],
            'service_export'                    => ['status' => 1],
            'tax_list'                          => ['status' => 1],
            'tax_add'                           => ['status' => 1],
            'tax_edit'                          => ['status' => 1],
            'tax_delete'                        => ['status' => 1],
            'tax_export'                        => ['status' => 1],
            'patient_report'                    => ['status' => 1],
            'patient_report_add'                => ['status' => 1],
            'patient_report_view'               => ['status' => 1],
            'patient_report_delete'             => ['status' => 1],
            'patient_report_edit'               => ['status' => 1],
            'doctor_session_list'               => ['status' => 1],
            'doctor_session_add'                => ['status' => 1],
            'doctor_session_edit'               => ['status' => 1],
            'doctor_session_delete'             => ['status' => 1],
            'doctor_session_export'             => ['status' => 1],
            'custom_form_list'                  => ['status' => 1],
            'custom_form_add'                   => ['status' => 1],
            'custom_form_edit'                  => ['status' => 1],
            'custom_form_view'                  => ['status' => 1],
            'custom_form_delete'                => ['status' => 1],
            'service_view'                      => ['status' => 1],
            'settings_edit'                     => ['status' => 1],
            'static_data_list'                  => ['status' => 1],
            'static_data_add'                   => ['status' => 1],
            'static_data_edit'                  => ['status' => 1],
            'static_data_view'                  => ['status' => 1],
            'static_data_export'                => ['status' => 1],
            'patient_encounters'                => ['status' => 1],
            'patient_encounter_list'            => ['status' => 1],
            'patient_encounter_add'             => ['status' => 1],
            'patient_encounter_edit'            => ['status' => 1],
            'patient_encounter_view'            => ['status' => 1],
            'patient_encounter_view_billing'    => ['status' => 1],
            'patient_encounter_delete'          => ['status' => 1],
            'patient_encounter_export'          => ['status' => 1],
            'encounters_template_list'          => ['status' => 1],
            'encounters_template_add'           => ['status' => 1],
            'encounters_template_edit'          => ['status' => 1],
            'encounters_template_view'          => ['status' => 1],
            'encounters_template_delete'        => ['status' => 1],
            'patient_appointment_status_change' => ['status' => 1],
            'medical_records_list'              => ['status' => 1],
            'medical_records_add'               => ['status' => 1],
            'medical_records_edit'              => ['status' => 1],
            'medical_records_view'              => ['status' => 1],
            'medical_records_delete'            => ['status' => 1],
            'prescription_list'                 => ['status' => 1],
            'prescription_add'                  => ['status' => 1],
            'prescription_edit'                 => ['status' => 1],
            'prescription_view'                 => ['status' => 1],
            'prescription_export'               => ['status' => 1],
            'prescription_delete'               => ['status' => 1],
            'patient_bill_list'                 => ['status' => 1],
            'patient_bill_add'                  => ['status' => 1],
            'patient_bill_edit'                 => ['status' => 1],
            'patient_bill_view'                 => ['status' => 1],
            'patient_bill_delete'               => ['status' => 1],
            'patient_bill_export'               => ['status' => 1],
            'patient_review_delete'             => ['status' => 1],
            'patient_review_get'                => ['status' => 1],
            'custom_field_list'                 => ['status' => 1],
            'custom_field_add'                  => ['status' => 1],
            'custom_field_edit'                 => ['status' => 1],
            'custom_field_view'                 => ['status' => 1],
            'custom_field_delete'               => ['status' => 1],
            'custom_field_export'               => ['status' => 1],
            'terms_condition'                   => ['status' => 1],
            'clinic_schedule'                   => ['status' => 1],
            'clinic_schedule_add'               => ['status' => 1],
            'clinic_schedule_edit'              => ['status' => 1],
            'clinic_schedule_delete'            => ['status' => 1],
            'clinic_schedule_export'            => ['status' => 1],
            'common_settings'                   => ['status' => 1],
            'notification_setting'              => ['status' => 1],
            'change_password'                   => ['status' => 1],
            'dashboard_total_patient'           => ['status' => 1],
            'dashboard_total_doctor'            => ['status' => 1],
            'dashboard_total_appointment'       => ['status' => 1],
            'dashboard_total_revenue'           => ['status' => 1],
            'dashboard_total_clinic'            => ['status' => 1],
        ],
    ];

    /**
     * @var KCPermissions Instance of this class
     */
    private static $instance = null;

    /**
     * Constructor to ensure roles are updated
     */
    public function __construct()
    {
        // If init has already run (e.g. during REST API call), run immediately
        if (did_action('init')) {
            $this->init_roles_and_capabilities();
        } else {
            // Otherwise hook into init
            add_action('init', [$this, 'init_roles_and_capabilities']);
        }
    }

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize custom roles and capabilities
     */
    public function init_roles_and_capabilities()
    {
        $this->create_custom_roles();
        $this->add_capabilities_to_roles();
    }

    /**
     * Create custom roles
     */
    private function create_custom_roles()
    {
        // Patient role
        if (!get_role(KIVI_CARE_PREFIX . 'patient')) {
            add_role(KIVI_CARE_PREFIX . 'patient', __('Patient', 'kivicare-clinic-management-system'), [
                'read' => true,
                'upload_files' => true
            ]);
        }

        // Doctor role
        if (!get_role(KIVI_CARE_PREFIX . 'doctor')) {
            add_role(KIVI_CARE_PREFIX . 'doctor', __('Doctor', 'kivicare-clinic-management-system'), [
                'read'          => true,
                'upload_files'  => true
            ]);
        }

        // Receptionist role
        if (!get_role(KIVI_CARE_PREFIX . 'receptionist')) {
            add_role(KIVI_CARE_PREFIX . 'receptionist', __('Receptionist', 'kivicare-clinic-management-system'), [
                'read'          => true,
                'upload_files'  => true
            ]);
        }

        // Clinic Admin role
        if (!get_role(KIVI_CARE_PREFIX . 'clinic_admin')) {
            add_role(KIVI_CARE_PREFIX . 'clinic_admin', __('Clinic Admin', 'kivicare-clinic-management-system'), [
                'read'          => true,
                'edit_posts'    => true,
                'delete_posts'  => true,
                'upload_files'  => true
            ]);
        }
    }

    /**
     * Add capabilities to roles with prefix
     */
    private function add_capabilities_to_roles()
    {
        $prefix = defined('KIVI_CARE_PREFIX') ? KIVI_CARE_PREFIX : KIVI_CARE_PREFIX . 'kiviCare_';

        foreach (self::$role_capabilities as $role_name => $capabilities) {
            $role_key = $role_name === 'admin' ? 'administrator' : $role_name;
            $role = get_role($role_key);

            if ($role) {
                foreach ($capabilities as $capability => $config) {
                    if ($config['status'] == 1) {
                        $cap_name = $capability === 'read' ? 'read' : $prefix . $capability;
                        $role->add_cap($cap_name);
                    }
                }
            }
        }
    }

    /**
     * Check if current user has specific permission
     * 
     * @param string $capability The capability to check (without prefix)
     * @param int|null $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function has_permission($capability, $user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }

        $prefix = defined('KIVI_CARE_PREFIX') ? KIVI_CARE_PREFIX : KIVI_CARE_PREFIX . 'kc_';
        $cap_name = $capability === 'read' ? 'read' : $prefix . $capability;

        // Check if user has the specific capability
        if (user_can($user, $cap_name)) {
            return true;
        }

        // Check if user is administrator (has all permissions)
        if (user_can($user, 'administrator')) {
            return true;
        }

        return false;
    }

    /**
     * Get permissions for a specific role
     * 
     * @param string $role Role name (patient, doctor, receptionist, clinic_admin, admin)
     * @return \Illuminate\Support\Collection|null
     */
    public static function get_role_permissions($role)
    {
        if (!isset(self::$role_capabilities[$role])) {
            return null;
        }

        $prefix = defined('KIVI_CARE_PREFIX') ? KIVI_CARE_PREFIX : 'kiviCare_';
        $permissions = [];

        foreach (self::$role_capabilities[$role] as $capability => $config) {
            $cap_name = $capability === 'read' ? 'read' : $prefix . $capability;
            $permissions[$capability] = [
                'name' => $cap_name,
                'status' => $config['status']
            ];
        }

        return collect($permissions);
    }

    /**
     * Get user's KiviCare role
     * 
     * @param int|null $user_id User ID (optional, defaults to current user)
     * @return string|null
     */
    public static function get_user_role($user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return null;
        }

        $roles = $user->roles;

        // Check for KiviCare specific roles first
        $kc_roles = [KIVI_CARE_PREFIX . 'patient', KIVI_CARE_PREFIX . 'doctor', KIVI_CARE_PREFIX . 'receptionist', KIVI_CARE_PREFIX . 'clinic_admin'];
        foreach ($kc_roles as $role) {
            if (in_array($role, $roles)) {
                return str_replace(KIVI_CARE_PREFIX . 'kc_', '', $role);
            }
        }

        // Check if administrator
        if (in_array('administrator', $roles)) {
            return 'admin';
        }

        return null;
    }

    /**
     * Check if user can perform specific action on resource
     * 
     * @param string $action Action to check (e.g., 'appointment_list', 'patient_add')
     * @param int|null $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function can_user_perform_action($action, $user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        $user_role = self::get_user_role($user_id);
        if (!$user_role) {
            return false;
        }
        // Check if action exists in role permissions and is enabled
        if (isset(self::$role_capabilities[$user_role][$action])) {
            return self::$role_capabilities[$user_role][$action]['status'] == 1;
        }

        return false;
    }

    /**
     * Get all available permissions for admin role (legacy function support)
     * 
     * @return \Illuminate\Support\Collection
     */
    public static function get_admin_permissions()
    {
        return self::get_role_permissions('admin');
    }

    /**
     * Get all available permissions for doctor role (legacy function support)
     * 
     * @return \Illuminate\Support\Collection
     */
    public static function get_doctor_permissions()
    {
        return self::get_role_permissions('doctor');
    }

    /**
     * Get all available permissions for patient role (legacy function support)
     * 
     * @return \Illuminate\Support\Collection
     */
    public static function get_patient_permissions()
    {
        return self::get_role_permissions('patient');
    }

    /**
     * Get all available permissions for receptionist role (legacy function support)
     * 
     * @return \Illuminate\Support\Collection
     */
    public static function get_receptionist_permissions()
    {
        return self::get_role_permissions('receptionist');
    }

    /**
     * Assign capabilities to existing users
     */
    public function assign_capabilities_to_existing_users()
    {
        // This can be used to update existing users with new capabilities
        // Run only when needed to avoid performance issues
    }

    /**
     * Remove custom roles and capabilities (for plugin deactivation)
     */
    public static function remove_custom_roles()
    {
        $custom_roles = [KIVI_CARE_PREFIX . 'kc_patient', KIVI_CARE_PREFIX . 'kc_doctor', KIVI_CARE_PREFIX . 'kc_receptionist', KIVI_CARE_PREFIX . 'kc_clinic_admin'];

        foreach ($custom_roles as $role) {
            remove_role($role);
        }

        // Remove capabilities from administrator role
        $admin_role = get_role('administrator');
        if ($admin_role && isset(self::$role_capabilities['admin'])) {
            $prefix = defined('KIVI_CARE_PREFIX') ? KIVI_CARE_PREFIX : KIVI_CARE_PREFIX . 'kc_';
            foreach (self::$role_capabilities['admin'] as $capability => $config) {
                $cap_name = $capability === 'read' ? 'read' : $prefix . $capability;
                $admin_role->remove_cap($cap_name);
            }
        }
    }
}
