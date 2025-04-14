<?php

namespace App\baseClasses;

class KCRoutes
{

    //all routes array
    public function routes()
    {

        $routes = array(

            // Setup wizard module start here...
            'get_setup_step_status'      => ['method' => 'post', 'action' => 'KCSetupController@getSetupStepStatus'],
            'setup_step_status'          => ['method' => 'get', 'action' => 'KCHomeController@step_status'],
            'setup_clinic'               => ['method' => 'post', 'action' => 'KCSetupController@clinic'],
            'setup_clinic_admin'               => ['method' => 'post', 'action' => 'KCSetupController@clinicAdmin'],
            'setup_doctor'               => ['method' => 'post', 'action' => 'KCSetupController@doctor'],
            'setup_receptionist'         => ['method' => 'post', 'action' => 'KCSetupController@receptionist'],
            'setup_clinic_session'       => ['method' => 'post', 'action' => 'KCSetupController@clinicSession'],
            'setup_finish'               => ['method' => 'post', 'action' => 'KCSetupController@setupFinish'],
            'update_setup_step'          => ['method' => 'post', 'action' => 'KCSetupController@updateSetupStep'],


            //initial page route
            'get_user'                   => ['method' => 'get', 'action' => 'KCHomeController@getUser'],

            //dashboard module route
            'get_dashboard'              => ['method' => 'get', 'action' => 'KCHomeController@getDashboard'],
            'get_weekly_appointment'     => ['method' => 'get', 'action' => 'KCHomeController@getWeeklyAppointment'],
            'get_version_data'     => ['method' => 'get', 'action' => 'KCHomeController@getVersionData'],


            // Appointment module routes starts here...
            'appointment_list'           => ['method' => 'get', 'action' => 'KCAppointmentController@index'],
            'get_appointment_queue'      => ['method' => 'get', 'action' =>  'KCAppointmentController@getAppointmentQueue'],
            'appointment_save'           => ['method' => 'post', 'action' => 'KCAppointmentController@save'],
            'appointment_delete'         => ['method' => 'get', 'action' => 'KCAppointmentController@delete'],
            'appointment_multiple_delete' => ['method' => 'post', 'action' => 'KCAppointmentController@delete_multiple'],
            'appointment_update_status'  => ['method' => 'get', 'action' => 'KCAppointmentController@updateStatus'],
            'get_appointment_slots'      => ['method' => 'get', 'action' => 'KCAppointmentController@getAppointmentSlots'],
            'upload_multiple_report' => ['method' => 'post', 'action' => 'KCAppointmentController@uploadMedicalReport', 'nonce' => 0],
            'appointment_details'           => ['method' => 'get', 'action' => 'KCAppointmentController@getAppointmentDetails'],

            // encounter module routes starts here...
            'patient_encounter_list'     => ['method' => 'get', 'action' => 'KCPatientEncounterController@index'],
            'patient_encounter_save'     => ['method' => 'post', 'action' => 'KCPatientEncounterController@save'],
            'patient_encounter_edit'     => ['method' => 'get', 'action' => 'KCPatientEncounterController@edit'],
            'patient_encounter_delete'   => ['method' => 'get', 'action' => 'KCPatientEncounterController@delete'],
            'patient_encounter_details'  => ['method' => 'get', 'action' => 'KCPatientEncounterController@details'],
            'save_custom_patient_encounter_field'  => ['method' => 'post', 'action' => 'KCPatientEncounterController@saveCustomField'],
            'patient_encounter_update_status'  => ['method' => 'post', 'action' => 'KCPatientEncounterController@updateStatus'],
            'print_encounter_bill_detail'    =>   ['method' => 'get', 'action' => 'KCPatientEncounterController@printEncounterBillDetail'],
            //'send_bill_to_patient'    =>   ['method' => 'get', 'action' => 'KCPatientEncounterController@sendBillToPatient'],
            'encounter_extra_clinical_detail_fields'    =>   ['method' => 'get', 'action' => 'KCPatientEncounterController@encounterExtraClinicalDetailFields'],
            'get_encounter_print'           => ['method' => 'get', 'action' => 'KCPatientEncounterController@getEncounterPrint'],

            // Medical records routes starts here...
            'prescription_list'          => ['method' => 'get', 'action' => 'KCPatientPrescriptionController@index'],
            'prescription_save'          => ['method' => 'post', 'action' => 'KCPatientPrescriptionController@save'],
            'prescription_edit'          => ['method' => 'post', 'action' => 'KCPatientPrescriptionController@edit'],
            'prescription_delete'        => [
                'method' => 'get',
                'action' => 'KCPatientPrescriptionController@delete'
            ],
            'prescription_mail'         =>  ['method' => 'get', 'action' => 'KCPatientPrescriptionController@mailPrescription'],
            'get_prescription_print'           => ['method' => 'get', 'action' => 'KCPatientPrescriptionController@getPrescriptionPrint'],

            // Medical records routes starts here...
            'medical_records_list'       => ['method' => 'post', 'action' => 'KCPatientMedicalRecordsController@index'],
            'medical_records_save'       => ['method' => 'post', 'action' => 'KCPatientMedicalRecordsController@save'],
            'medical_records_edit'       => ['method' => 'post', 'action' => 'KCPatientMedicalRecordsController@edit'],
            'medical_records_delete'     => [
                'method' => 'post',
                'action' => 'KCPatientMedicalRecordsController@delete'
            ],

            // Medical history routes starts here...
            'medical_history_list'       => ['method' => 'get', 'action' => 'KCPatientMedicalHistoryController@index'],
            'medical_history_save'       => ['method' => 'post', 'action' => 'KCPatientMedicalHistoryController@save'],
            'medical_history_delete'     => [
                'method' => 'get',
                'action' => 'KCPatientMedicalHistoryController@delete'
            ],

            // Patient bill module routes starts here...
            'patient_bill_list'          => ['method' => 'post', 'action' => 'KCPatientBillController@index'],
            'patient_bill_save'          => ['method' => 'post', 'action' => 'KCPatientBillController@save'],
            'patient_bill_edit'          => ['method' => 'post', 'action' => 'KCPatientBillController@edit'],
            'patient_bill_detail'        => ['method' => 'get', 'action' => 'KCPatientBillController@details'],
            'patient_bill_update_status' => ['method' => 'post', 'action' => 'KCPatientBillController@updateStatus'],
            'patient_bill_delete'        => ['method' => 'post', 'action' => 'KCPatientBillController@delete'],
            'patient_bill_item_delete'   => ['method' => 'post', 'action' => 'KCPatientBillController@deleteBillItem'],
            'billing_record_list'   => ['method' => 'get', 'action' => 'KCPatientBillController@billList'],
            'get_without_bill_encounter_list'   => ['method' => 'get', 'action' => 'KCPatientBillController@getWithoutBillEncounterList'],
            'send_payment_link'             => ['method' => 'post', 'action' => 'KCPatientBillController@sendPaymentLink'],

            // patient report module route
            'get_patient_report'   => ['method' => 'get', 'action' => 'KCPatientReportController@getPatientReport', 'nonce' => 0],
            'upload_patient_report'   => ['method' => 'post', 'action' => 'KCPatientReportController@uploadPatientReport', 'nonce' => 0],
            'edit_patient_report'   => ['method' => 'post', 'action' => 'KCPatientReportController@editPatientReport', 'nonce' => 0],
            'view_patient_report'   => ['method' => 'get', 'action' => 'KCPatientReportController@viewPatientReport', 'nonce' => 0],
            'delete_patient_report'   => ['method' => 'get', 'action' => 'KCPatientReportController@deletePatientReport', 'nonce' => 0],
            'patient_report_mail' => ['method' => 'post', 'action' => 'KCPatientReportController@patientReportMail', 'nonce' => 0],


            // Clinics module routes starts here...
            'clinic_list'                => ['method' => 'get', 'action' => 'KCClinicController@index'],
            'clinic_save'                => ['method' => 'post', 'action' => 'KCClinicController@save'],
            'clinic_edit'                => ['method' => 'get', 'action' => 'KCClinicController@edit'],
            'clinic_delete'              => ['method' => 'post', 'action' => 'KCClinicController@delete'],
            'clinic_admin_edit'                => ['method' => 'post', 'action' => 'KCClinicController@clinicAdminEdit'],
            'clinic_doctor_wise_list'           => ['method' => 'get', 'action' => 'KCClinicController@getDoctorWiseClinic'],
            'patient_clinic_check_out'   => ['method' => 'post', 'action' => 'KCClinicController@patientClinicCheckOut', 'nonce' => 0],

            // Doctor module routes starts here...
            'doctor_list'                => ['method' => 'get', 'action' => 'KCDoctorController@index'],
            'doctor_save'                => ['method' => 'post', 'action' => 'KCDoctorController@save'],
            'doctor_edit'                => ['method' => 'get', 'action' => 'KCDoctorController@edit'],
            'doctor_delete'              => ['method' => 'get', 'action' => 'KCDoctorController@delete'],
            'doctor_change_email'        => ['method' => 'post', 'action' => 'KCDoctorController@changeEmail'],
            'get_doctor_workdays'         => ['method' => 'get', 'action' => 'KCDoctorController@getDoctorWorkdays'],
            'get_doctor_workdays_and_session'         => ['method' => 'post', 'action' => 'KCDoctorController@getDoctorWorkdayAndSession'],

            // Patient module routes starts here...
            'patient_list'               => ['method' => 'get', 'action' => 'KCPatientController@index'],
            'patient_save'               => ['method' => 'post', 'action' => 'KCPatientController@save'],
            'patient_edit'               => ['method' => 'get', 'action' => 'KCPatientController@edit'],
            'patient_delete'             => ['method' => 'get', 'action' => 'KCPatientController@delete'],
            'patient_profile_view_details' => ['method' => 'get', 'action' => 'KCPatientController@patientProfileViewDetails'],
            'get_hide_fields_array_from_filter' => ['method' => 'get', 'action' => 'KCPatientController@getHideFieldsArrayFromFilter'],


            // doctor rating controller
            'patient_get_review'             => ['method' => 'get', 'action' => 'KCDoctorRatingController@getReview'],
            'patient_save_review'             => ['method' => 'post', 'action' => 'KCDoctorRatingController@saveReview'],
            'doctor_review_detail'             => ['method' => 'get', 'action' => 'KCDoctorRatingController@doctorReviewDetail'],


            // Receptionist module routes starts here...
            'receptionist_list'          => ['method' => 'get', 'action' => 'KCReceptionistController@index'],
            'receptionist_edit'          => ['method' => 'get', 'action' => 'KCReceptionistController@edit'],
            'receptionist_save'          => ['method' => 'post', 'action' => 'KCReceptionistController@save'],
            'receptionist_delete'        => ['method' => 'get', 'action' => 'KCReceptionistController@delete'],


            // Clinic session routes starts here...
            'clinic_session_list'        => ['method' => 'get', 'action' => 'KCClinicSessionController@index'],
            'clinic_session_save'        => ['method' => 'post', 'action' => 'KCClinicSessionController@save'],
            'clinic_session_delete'      => ['method' => 'post', 'action' => 'KCClinicSessionController@delete'],
            'save_time_zone_option' =>  ['method' => 'post', 'action' => 'KCClinicSessionController@saveTimeZoneOption'],


            // Services module routes starts here...
            'service_list'               => ['method' => 'get', 'action' => 'KCServiceController@index'],
            'service_save'               => ['method' => 'post', 'action' => 'KCServiceController@save'],
            'service_edit'               => ['method' => 'get', 'action' => 'KCServiceController@edit'],
            'service_delete'             => ['method' => 'get', 'action' => 'KCServiceController@delete'],
//            'get_clinic_service'         => ['method' => 'post', 'action' => 'KCServiceController@clinicService'],

            // tax module routes
            'tax_list'               => ['method' => 'get', 'action' => 'KCTaxController@index'],
            'tax_save'               => ['method' => 'post', 'action' => 'KCTaxController@save'],
            'tax_edit'               => ['method' => 'get', 'action' => 'KCTaxController@edit'],
            'tax_delete'             => ['method' => 'post', 'action' => 'KCTaxController@delete'],
            'tax_calculated_data'    => ['method' => 'post', 'action' => 'KCTaxController@getTaxData'],
            'tax_calculated_encounter_data'    => ['method' => 'post', 'action' => 'KCTaxController@getEncounterTaxData'],


            //report module route
            'get_clinic_revenue'     => ['method' => 'get', 'action' => 'KCReportController@getClinicRevenue'],
            'get_clinic_bar_revenue'     => ['method' => 'get', 'action' => 'KCReportController@getClinicBarChart'],
            'get_doctor_wise_revenue'     => ['method' => 'get', 'action' => 'KCReportController@doctorRevenue'],
            'get_appointment_count'     => ['method' => 'get', 'action' => 'KCReportController@appointmentCount'],
            'get_clinic_appointment_count'     => ['method' => 'get', 'action' => 'KCReportController@clinicAppointmentCount'],
            'get_all_report_type'           => ['method' => 'get', 'action' => 'KCReportController@getAllReportType'],

            //common route
            'terms_condition_save'       => ['method' => 'post', 'action' => 'KCHomeController@saveTermsCondition'],
            'terms_condition_list'       => ['method' => 'get', 'action' => 'KCHomeController@getTermsCondition'],
            'get_country_list'           => ['method' => 'get', 'action' => 'KCHomeController@getCountryCurrencyList'],
            'logout'                     => ['method' => 'post', 'action' => 'KCHomeController@logout'],
            'change_password'            => ['method' => 'post', 'action' => 'KCHomeController@changePassword'],
            'resend_credential'          => ['method' => 'post', 'action' => 'KCHomeController@resendUserCredential'],
            'change_module_value_status' =>  ['method' => 'post', 'action' => 'KCHomeController@changeModuleValueStatus'],
            'module_wise_multiple_data_update' =>  ['method' => 'post', 'action' => 'KCHomeController@moduleWiseMultipleDataUpdate'],
            'get_country_code_settings_data' =>  ['method' => 'get', 'action' => 'KCHomeController@getCountryCodeSettingsData'],
            'get_user_registration_form_settings_data' =>  ['method' => 'get', 'action' => 'KCHomeController@getUserRegistrationFormSettingsData'],

            //general setting route
            'save_request_helper_status'      => ['method' => 'post', 'action' => 'KCGeneralSettingController@saveRequestHelperStatus'],
            'get_request_helper_status'      => ['method' => 'post', 'action' => 'KCGeneralSettingController@getRequestHelperStatus'],
            'save_clinic_currency' => ['method' => 'post', 'action' => 'KCGeneralSettingController@saveClinicCurrency', 'nonce' => 0],
            'save_country_code' => ['method' => 'post', 'action' => 'KCGeneralSettingController@saveCountryCode', 'nonce' => 0],
            // 'save_date_format' => ['method' => 'post', 'action' => 'KCGeneralSettingController@saveDateFormat', 'nonce' => 0],
            'save_registration_shortcode_setting' => ['method' => 'post', 'action' => 'KCGeneralSettingController@saveUserRegistrationShortcodeSetting', 'nonce' => 0],
            'get_all_general_setting' => ['method' => 'get', 'action' => 'KCGeneralSettingController@getAllGeneralSettingsData', 'nonce' => 0],
            'save_google_recaptcha_setting'      => ['method' => 'post', 'action' => 'KCGeneralSettingController@saveGoogleRecaptchaSetting'],
            'save_fullcalendar_setting'      => ['method' => 'post', 'action' => 'KCGeneralSettingController@saveFullcalendarSetting'],
            'save_logout_redirect_setting'      => ['method' => 'post', 'action' => 'KCGeneralSettingController@saveLogoutRedirectSetting'],
            'save_login_redirect_setting'      => ['method' => 'post', 'action' => 'KCGeneralSettingController@saveLoginRedirectSetting'],
            'reset_plugin_data' =>  ['method' => 'post', 'action' => 'KCGeneralSettingController@KCResetPluginData'],
            'save_registration_form_setting' =>  ['method' => 'post', 'action' => 'KCGeneralSettingController@saveUserRegistrationFormSetting'],
            'save_encounter_setting' =>  ['method' => 'post', 'action' => 'KCGeneralSettingController@saveEncounterSetting'],

            // Clinic schedule module routes start here...
            'clinic_schedule_list'       => ['method' => 'get', 'action' => 'KCClinicScheduleController@index'],
            'clinic_schedule_save'       => ['method' => 'post', 'action' => 'KCClinicScheduleController@save'],
            'clinic_schedule_edit'       => ['method' => 'get', 'action' => 'KCClinicScheduleController@edit'],
            'clinic_schedule_delete'     => ['method' => 'get', 'action' => 'KCClinicScheduleController@delete'],


            // Module configuration start here...
            'module_list'      => ['method' => 'get', 'action' => 'KCModuleController@index'],
            'encounter_module_list'      => ['method' => 'post', 'action' => 'KCModuleController@encounterModules'],
            'prescription_module_list'      => ['method' => 'post', 'action' => 'KCModuleController@prescriptionModules'],
            'module_save'      => ['method' => 'post', 'action' => 'KCModuleController@save'],

            // email and sms setting route
            'get_email_template'         => ['method' => 'get', 'action' => 'KCNotificationTemplateController@getEmailTemplate'],
            'save_email_template'        => ['method' => 'post', 'action' => 'KCNotificationTemplateController@saveEmailTemplate'],
            'get_sms_template'         => ['method' => 'get', 'action' => 'KCNotificationTemplateController@getSMSTemplate'],
            'get_twillio_sms_template'         => ['method' => 'get', 'action' => 'KCNotificationTemplateController@getTwillioSMSTemplate'],
            'save_sms_template'        => ['method' => 'post', 'action' => 'KCNotificationTemplateController@saveSMSTemplate'],
            'send_test_notification' =>    ['method' => 'post', 'action' => 'KCNotificationTemplateController@sendTestNotification'],
            'custom_notification_save_dynamic_keys' =>    ['method' => 'post', 'action' => 'KCNotificationTemplateController@saveDynamicKeys'],
            'custom_notification_get_dynamic_keys' =>    ['method' => 'get', 'action' => 'KCNotificationTemplateController@getDynamicKeys'],
            'custom_notification_save_api_configuration_list' =>    ['method' => 'post', 'action' => 'KCNotificationTemplateController@saveApiConfigurationList'],
            'custom_notification_get_api_configuration_list' =>    ['method' => 'get', 'action' => 'KCNotificationTemplateController@getApiConfigurationList'],

            //patient unique id setting
            'patient_id_config' => ['method' => 'post', 'action' => 'KCPatientUniqueIdController@savePatientSetting'],
            'edit_patient_id_config' => ['method' => 'get', 'action' => 'KCPatientUniqueIdController@editPatientSetting'],
            'get_unique_id' => ['method' => 'post', 'action' => 'KCPatientUniqueIdController@getPatientUid'],

            //widget setting route
            'widget_setting_save'   => ['method' => 'post', 'action' => 'KCWidgetSettingController@saveWidgetSetting', 'nonce' => 0],
            'get_widget_setting'   => ['method' => 'get', 'action' => 'KCWidgetSettingController@getWidgetSetting', 'nonce' => 0],
            'upload_widget_loader'   => ['method' => 'post', 'action' => 'KCWidgetSettingController@saveWidgetLoader', 'nonce' => 0],
            'get_widget_loader' =>  ['method' => 'get', 'action' => 'KCWidgetSettingController@getWidgetLoader'],

            // GOOGLE CALENDER setting ROUTES
            'connect_doctor' => ['method' => 'post', 'action' => 'KCGoogleCalenderController@connectDoctor'],
            'diconnect_doctor' => ['method' => 'post', 'action' => 'KCGoogleCalenderController@disconnectDoctor'],
            'get_google_event_template'        => ['method' => 'get', 'action' => 'KCGoogleCalenderController@getGoogleEventTemplate'],
            'save_google_event_template'        => ['method' => 'post', 'action' => 'KCGoogleCalenderController@saveGoogleEventTemplate'],

            //doctor telemed  route
            'save_doctor_zoom_configuration'   => ['method' => 'post', 'action' => 'KCZoomTelemedController@saveZoomConfiguration'],
            'save_doctor_server_Oauth_configuration' => ['method' => 'post', 'action' => 'KCZoomTelemedController@saveDoctorServeroauthConfiguration'],
            'get_server_auth_config'         =>['method' => 'get', 'action' => 'KCZoomTelemedController@getServerOauthConfig'],
            'get_doctor_zoom_configuration'   => ['method' => 'get', 'action' => 'KCZoomTelemedController@getZoomConfiguration'],
            'resend_zoom_link_patient'      => ['method' => 'post', 'action' => 'KCZoomTelemedController@resendZoomLink'],
            'save_doctor_zoom_server_to_server_oauth_configuration' => ['method' => 'post', 'action' => 'KCZoomTelemedController@saveZoomServerToServerOauthConfiguration'],           
            'get_doctor_zoom_server_to_server_oauth_configuration'   => ['method' => 'get', 'action' => 'KCZoomTelemedController@getZoomServerToServerOauthConfiguration'],

            //GOOGLE MEET ROUTES
            'google_meet_config' => ['method' => 'post', 'action' => 'KCGooglemeetController@saveGoogleMeetConfig'],
            'get_google_meet_event_template_and_config'        => ['method' => 'get', 'action' => 'KCGooglemeetController@getGoogleMeetEventTemplateAndConfigData'],
            'save_google_meet_event_template'        => ['method' => 'post', 'action' => 'KCGooglemeetController@saveGoogleMeetEventTemplate'],
            'connect_meet_doctor' => ['method' => 'post', 'action' => 'KCGooglemeetController@connectGoogleMeetDoctor'],
            'diconnect_meet_doctor' => ['method' => 'post', 'action' => 'KCGooglemeetController@disconnectMeetDoctor'],


            // Static data module routes start here...
            'get_static_data'            => ['method' => 'get', 'action' => 'KCStaticDataController@getStaticData'],
            'static_data_list'           => ['method' => 'get', 'action' => 'KCStaticDataController@index'],
            'static_data_save'           => ['method' => 'post', 'action' => 'KCStaticDataController@save'],
            'static_data_edit'           => ['method' => 'get', 'action' => 'KCStaticDataController@edit'],
            'static_data_delete'         => ['method' => 'post', 'action' => 'KCStaticDataController@delete'],

            // Custom field routes starts here...
            'custom_form_list'          => ['method' => 'get', 'action' => 'KCCustomFormController@index'],
            'custom_form_save'          => ['method' => 'post', 'action' => 'KCCustomFormController@save'],
            'custom_form_edit'          => ['method' => 'get', 'action' => 'KCCustomFormController@edit'],
            'custom_form_delete'        => ['method' => 'post', 'action' => 'KCCustomFormController@delete'],
            'custom_form_data_get'          => ['method' => 'get', 'action' => 'KCCustomFormController@formDataGet'],
            'custom_form_data_save'          => ['method' => 'post', 'action' => 'KCCustomFormController@formDataSave'],

            // Custom field routes starts here...
            'custom_field_list'          => ['method' => 'get', 'action' => 'KCCustomFieldController@index'],
            'custom_field_save'          => ['method' => 'post', 'action' => 'KCCustomFieldController@save'],
            'custom_field_edit'          => ['method' => 'get', 'action' => 'KCCustomFieldController@edit'],
            'custom_field_delete'        => ['method' => 'get', 'action' => 'KCCustomFieldController@delete'],
            'get_custom_fields'          => ['method' => 'get', 'action' => 'KCCustomFieldController@getCustomFields'],
            'custom_field_file_upload_data'          => ['method' => 'get', 'action' => 'KCCustomFieldController@customFieldFileUploadData'],
            //pro setting route
            'get_all_pro_settings_value'   => ['method' => 'get', 'action' => 'KCProSettingController@editAllProSettingData', 'nonce' => 0],
            'upload_logo'   => ['method' => 'post', 'action' => 'KCProSettingController@uploadLogo', 'nonce' => 0],
            'upload_loader' => ['method' => 'post', 'action' => 'KCProSettingController@uploadLoader', 'nonce' => 0],
            'update_theme_color'   => ['method' => 'post', 'action' => 'KCProSettingController@updateThemeColor', 'nonce' => 0],
            'update_theme_rtl'   => ['method' => 'get', 'action' => 'KCProSettingController@updateRTLMode', 'nonce' => 0],
            'save_wordpress_logo' =>  ['method' => 'post', 'action' => 'KCProSettingController@wordpresLogo'],
            'whatsapp_config_save'   => ['method' => 'post', 'action' => 'KCProSettingController@saveWhatsAppConfig', 'nonce' => 0],
            'sms_config_save'   => ['method' => 'post', 'action' => 'KCProSettingController@saveSmsConfig', 'nonce' => 0],
            'google_calender_config' => ['method' => 'post', 'action' => 'KCProSettingController@saveConfig'],
            'save_patient_google_cal' => ['method' => 'get', 'action' => 'KCProSettingController@googleCalPatient'],
            'edit_clinical_detail_include' => ['method' => 'get', 'action' => 'KCProSettingController@editClinicalDetailInclude', 'nonce' => 0],
            'edit_encounter_custom_field_include' => ['method' => 'get', 'action' => 'KCProSettingController@editEncounterCustomFieldInclude', 'nonce' => 0],
            'edit_clinical_detail_hide_in_patient' => ['method' => 'get', 'action' => 'KCProSettingController@editClinicalDetailHideInPatient', 'nonce' => 0],
            'save_copy_right_text' =>  ['method' => 'get', 'action' => 'KCProSettingController@saveCopyRightText', 'nonce' => 0],
            'save_custom_notification_setting' =>  ['method' => 'post', 'action' => 'KCProSettingController@saveCustomNotificationSetting', 'nonce' => 0],
            'send_custom_notification_request' =>    ['method' => 'post', 'action' => 'KCProSettingController@sendCustomNotificationRequest'],

            /* Defining a route for the get_encounter_templates method in the KCProSettingController. */
            'get_encounter_templates' => ['method' => 'get', 'action' => 'KCProSettingController@getEncounterTemplates', 'nonce' => 0],
            'add_encounter_temp' => ['method' => 'post', 'action' => 'KCProSettingController@addEncounterTemp', 'nonce' => 0],
            'delete_encounter_temp' => ['method' => 'get', 'action' => 'KCProSettingController@deleteEncounterTemp', 'nonce' => 0],
            'insert_template_to_encounter' => ['method' => 'post', 'action' => 'KCProSettingController@insertTemplateToEncounter', 'nonce' => 0],
            'medical_history_list_from_template' => ['method' => 'get', 'action' => 'KCProSettingController@medicalHistoryListFromTemplate', 'nonce' => 0],
            'save_encounter_template_medical_history' => ['method' => 'post', 'action' => 'KCProSettingController@saveEncounterTemplateMedicalHistory', 'nonce' => 0],
            'delete_encounter_template_medical_history' => ['method' => 'get', 'action' => 'KCProSettingController@deleteEncounterTemplateMedicalHistory', 'nonce' => 0],
            'patient_encounter_template_details' => ['method' => 'get', 'action' => 'KCProSettingController@patientEncounterTemplateDetails', 'nonce' => 0],
            
            'get_encounter_template_prescription_list' => ['method' => 'get', 'action' => 'KCProSettingController@getEncounterTemplatePrescriptionList', 'nonce' => 0],
            'delete_encounter_template_prescription' => ['method' => 'get', 'action' => 'KCProSettingController@deleteEncounterTemplatePrescription', 'nonce' => 0],
            'save_encounter_template_prescription' => ['method' => 'post', 'action' => 'KCProSettingController@saveEncounterTemplatePrescription', 'nonce' => 0],

            //payment setting route
            'paypal_config_save'   => ['method' => 'get', 'action' => 'KCPaymentController@savePaypalConfig', 'nonce' => 0],
            'razorpay_config_save'   => ['method' => 'post', 'action' => 'KCPaymentController@saveRazorpayConfig', 'nonce' => 0],
            'stripepay_config_save' => ['method' => 'post', 'action' => 'KCPaymentController@saveStripepayConfig','nonce' => 0],
            'change_local_payment_status'   => ['method' => 'get', 'action' => 'KCPaymentController@changeLocalPaymentStatus', 'nonce' => 0],
            'change_woocommerce_payment_status' => ['method' => 'get', 'action' => 'KCPaymentController@changeWooCommercePaymentStatus'],
            'get_payment_status_all'         => ['method' => 'get', 'action' => 'KCPaymentController@getPaymentStatusAll'],
            'get_appointment_payment_status'  => ['method' => 'get', 'action' => 'KCPaymentController@getAppointmentPaymentStatus', 'nonce' => 0],
            'get_razorpay_currency_list'   => ['method' => 'get', 'action' => 'KCPaymentController@getRazorpayCurrencyList', 'nonce' => 0],

            //langauge setting route
            'get_json_file'         =>               ['method' => 'post', 'action' => 'KCLanguageController@getJosnFile'],
            'save_json_data'         =>               ['method' => 'post', 'action' => 'KCLanguageController@saveJsonData'],
            'save_loco_translate'         =>               ['method' => 'post', 'action' => 'KCLanguageController@saveLocoTranslate'],
            'get_loco_translate'         =>               ['method' => 'post', 'action' => 'KCLanguageController@getLocoTranslate'],
            'i_understnad_loco_translate' =>             ['method' => 'post', 'action' => 'KCLanguageController@iUnderstand'],
            'get_all_lang_option'         =>               ['method' => 'post', 'action' => 'KCLanguageController@getAllLang'],
            'update_language'   => ['method' => 'post', 'action' => 'KCLanguageController@updateLang', 'nonce' => 0],

            //appointment setting route
            'restrict_appointment_save' => ['method' => 'post', 'action' => 'KCAppointmentSettingController@restrictAppointmentSave'],
            'restrict_appointment_edit' => ['method' => 'get', 'action' => 'KCAppointmentSettingController@restrictAppointmentEdit'],
            'get_multifile_upload_status' => ['method' => 'get', 'action' => 'KCAppointmentSettingController@getMultifileUploadStatus'],
            'change_multifile_upload_status' => ['method' => 'get', 'action' => 'KCAppointmentSettingController@saveMultifileUploadStatus'],
            'appointment_reminder_notificatio_save' => ['method' => 'post', 'action' => 'KCAppointmentSettingController@appointmentReminderNotificatioSave'],
            'get_appointment_reminder_notification' => ['method' => 'get', 'action' => 'KCAppointmentSettingController@getAppointmentReminderNotification'],
            'update_appointment_time_format' => ['method' => 'post', 'action' => 'KCAppointmentSettingController@appointmentTimeFormatSave'],
            'get_appointment_description_status' => ['method' => 'get', 'action' => 'KCAppointmentSettingController@getAppointmentDescription', 'nonce' => 0],
            'appointment_description_status_change' => ['method' => 'post', 'action' => 'KCAppointmentSettingController@enableDisableAppointmentDescription', 'nonce' => 0],
            'appointment_patient_info_status_change' => ['method' => 'post', 'action' => 'KCAppointmentSettingController@enableDisableAppointmentPatientInfo', 'nonce' => 0],
            'get_appointment_patient_info_status' => ['method' => 'get', 'action' => 'KCAppointmentSettingController@getAppointmentPatientInfo', 'nonce' => 0],
            'appointment_cancellation_buffer_save' => ['method' => 'post', 'action' => 'KCAppointmentSettingController@appointmentCancellationBufferSave'],
            'get_appointment_cancellation_buffer' => ['method' => 'get', 'action' => 'KCAppointmentSettingController@getAppointmentCancellationBuffer'],

            //appointment button widget route
            'render_shortcode'     => ['method' => 'get', 'action' => 'KCHomeController@renderShortcode'],

            //permission setting
            'all_permission_list' => ['method' => 'get', 'action' => 'KCPermissionController@allPermissionList'],
            'save_permission_list' => ['method' => 'post', 'action' => 'KCPermissionController@savePermissionList'],

            //dashboard sidebar route
            'get_dashbaord_sidebar_data' => ['method' => 'get', 'action' => 'KCDashboardSidebarController@index'],
            'save_dashbaord_sidebar_data' => ['method' => 'post', 'action' => 'KCDashboardSidebarController@save'],

            ///////////////////// Front-end Routes starts here /////////////////////
            'get_clinic_detail'   => ['method' => 'get', 'action' => 'KCBookAppointmentWidgetController@getClinic', 'nonce' => 0],
            'get_doctor_details'   => ['method' => 'get', 'action' => 'KCBookAppointmentWidgetController@getDoctors', 'nonce' => 0],
            'get_clinic_selected_details'   => ['method' => 'get', 'action' => 'KCBookAppointmentWidgetController@getClinicSelectedArray', 'nonce' => 0],
            'get_clinic_details_appointment'   => ['method' => 'get', 'action' => 'KCBookAppointmentWidgetController@getClinicArray', 'nonce' => 0],
            'get_time_slots'   => ['method' => 'get', 'action' => 'KCBookAppointmentWidgetController@getTimeSlots', 'nonce' => 0],
            'get_time_slots_appointment'   => ['method' => 'get', 'action' => 'KCBookAppointmentWidgetController@getTimeSlotsList', 'nonce' => 0],
            'save_appointment'   => ['method' => 'post', 'action' => 'KCBookAppointmentWidgetController@saveAppointment', 'nonce' => 0],
            'login'   => ['method' => 'post', 'action' => 'KCAuthController@patientLogin', 'nonce' => 0],
            'appointmentLogin'   => ['method' => 'post', 'action' => 'KCAuthController@appointmentPatientLogin', 'nonce' => 0],
            'login_new_user'   => ['method' => 'post', 'action' => 'KCAuthController@loginNewUser', 'nonce' => 0],
            'register_new_user'   => ['method' => 'post', 'action' => 'KCAuthController@registerNewUser', 'nonce' => 0],
            'verify_user'   => ['method' => 'post', 'action' => 'KCAuthController@verifyUser', 'nonce' => 0],
            'register'   => ['method' => 'post', 'action' => 'KCAuthController@patientRegister', 'nonce' => 0],
            'login_user_detail'  =>  ['method' => 'get', 'action' => 'KCPatientDashboardWidget@getPatientDetail', 'nonce' => 0],
            'appointment_confirm_page' =>  ['method' => 'post', 'action' => 'KCBookAppointmentWidgetController@appointmentConfirmPage', 'nonce' => 0],
            'get_appointment_print' =>  ['method' => 'get', 'action' => 'KCBookAppointmentWidgetController@getAppointmentPrint', 'nonce' => 0],
            'get_appointment_custom_field' =>  ['method' => 'get', 'action' => 'KCBookAppointmentWidgetController@getAppointmentCustomField', 'nonce' => 0],
            'get_widget_payment_options' =>  ['method' => 'post', 'action' => 'KCBookAppointmentWidgetController@getWidgetPaymentOptions', 'nonce' => 0],

            // All Appointment
            'all-appointment'            => ['method' => 'post', 'action' => 'KCAppointmentController@allAppointment', 'nonce' => 0],

            // Patient-Dashboard-widget

            //data import route
            'import_module_data' => ['method' => 'get', 'action' => 'KCImportModuleDataController@import', 'nonce' => 0],
            'import_demo_files' => ['method' => 'get', 'action' => 'KCImportModuleDataController@demoFiles', 'nonce' => 0],

            //elementor widget routes
            'doctor_widget_list' => ['method' => 'post', 'action' => 'KCElementorController@doctorIndex'],
            'clinic_widget_list' => ['method' => 'post', 'action' => 'KCElementorController@clinicIndex'],


            // Zoom Telemed 
            'zoom_telemed_save_oauth_config' => ['method' => 'post', 'action' => 'KCZoomTelemedController@saveOauthConfig'],
            'get_zoom_telemed_config'        => ['method' => 'get', 'action' => 'KCZoomTelemedController@getZoomTelemedConfig'],
            'generate_doctor_zoom_oauth_token'=> ['method' => 'post', 'action' => 'KCZoomTelemedController@generateDoctorZoomOauthToken'],
            'generate_doctor_serveroauth_code'=> ['method' => 'post', 'action' => 'KCZoomTelemedController@generateDoctorServerOauthCode'],
            'connect_admin_zoom_oauth'=> ['method' => 'post', 'action' => 'KCZoomTelemedController@connectAdminZoomOauth'],
            'connect_doctor_serveroauth'=> ['method' => 'post', 'action' => 'KCZoomTelemedController@connectDoctorServerOauth'],
            'get_doctor_telemed_config'=> ['method' => 'post', 'action' => 'KCZoomTelemedController@getDoctorTelemedConfig'],
            'disconnect_doctor_zoom_oauth'=> ['method' => 'get', 'action' => 'KCZoomTelemedController@disconnectDoctorZoomOauth'],
            'disconnect_doctor_serveroauth'=> ['method' => 'post', 'action' => 'KCZoomTelemedController@disconnectDoctorServerOauth'],
            'save_zoom_telemed_server_to_server_oauth_status' => ['method' => 'post', 'action' => 'KCZoomTelemedController@saveServerToServerOauthStatus'],     
            'disconnect_doctor_zoom_server_to_server_oauth'=> ['method' => 'post', 'action' => 'KCZoomTelemedController@disconnectDoctorZoomServerToServerOauth'],      
            // 'save_google_meet_event_template'        => ['method' => 'post', 'action' => 'KCGooglemeetController@saveGoogleMeetEventTemplate'],
            // 'connect_meet_doctor' => ['method' => 'post', 'action' => 'KCGooglemeetController@connectGoogleMeetDoctor'],
            // 'diconnect_meet_doctor' => ['method' => 'post', 'action' => 'KCGooglemeetController@disconnectMeetDoctor'],
            // 'save_doctor_googlemeet_data_save' => ['method' => 'post', 'action' => 'KCGooglemeetController@saveDoctorGooglemeetDataSave'],


            // App Configuaration
            'save_app_config' => ['method' => 'post', 'action' => 'KCGeneralSettingController@saveAppConfig', 'nonce' => 0],
            'get_app_config' => ['method' => 'get', 'action' => 'KCGeneralSettingController@getAppConfig', 'nonce' => 0],
            
            'refresh_dashboard_locale' => ['method' => 'get', 'action' => 'KCHomeController@refreshDashboardLocale', 'nonce' => 0],
            
        );
        
        return apply_filters('kivicare_route_lists',$routes);
    }
}
