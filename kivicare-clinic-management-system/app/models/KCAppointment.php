<?php


namespace App\models;

use App\baseClasses\KCBase;
use App\baseClasses\KCModel;

class KCAppointment extends KCModel {

	public function __construct()
	{
		parent::__construct('appointments');
	}

	public static function getDoctorAppointments($doctor_id) {
		return collect(( new self() )->get_by(['doctor_id' =>(int)$doctor_id]));
	}


    public static function appointmentPermissionUserWise($appointment){
        $appointment_detail = (new KCAppointment())->get_by(['id' => (int)$appointment],'=',true);
        $kcbase = (new KCBase());

        $login_user_role = $kcbase->getLoginUserRole();
        $permission = false;
        switch ($login_user_role){

            case $kcbase->getReceptionistRole():
                $clinic_id = kcGetClinicIdOfReceptionist();
                if(!empty($appointment_detail->clinic_id) && (int)$appointment_detail->clinic_id === $clinic_id ){
                    $permission = true;
                }
                break;
            case $kcbase->getClinicAdminRole():
                $clinic_id = kcGetClinicIdOfClinicAdmin();
                if(!empty($appointment_detail->clinic_id) && (int)$appointment_detail->clinic_id === $clinic_id ){
                    $permission = true;
                }
                break;
            case 'administrator':
                $permission = true;
                break;
            case $kcbase->getDoctorRole():
                if(!empty($appointment_detail->doctor_id) && (int)$appointment_detail->doctor_id === get_current_user_id() ){
                    $permission = true;
                }
                break;
            case $kcbase->getPatientRole():
                if(!empty($appointment_detail->patient_id) && (int)$appointment_detail->patient_id === get_current_user_id() ){
                    $permission = true;
                }
                break;
        }
        return $permission;
    }

    public function loopAndDelete($condition,$encounter_delete){
        $instance = (new self());
        $all_appointments = $instance->get_by($condition);
        $telemed_active = isKiviCareTelemedActive();
        $sms_enable = kcCheckSmsOptionEnable();
        $whatsapp_enable = kcCheckWhatsappOptionEnable();
        $google_meet_enable = kcCheckGoogleCalendarEnable();
        $google_calendar_enable = kcCheckGoogleCalendarEnable();
        $woocommerce_enable = iskcWooCommerceActive();
        if(!empty($all_appointments)){
            foreach ($all_appointments as $res){
                do_action('kc_appointment_delete', $res->id);
                //check if appointment date and time greater than current time and status is booked
                if($res->appointment_start_date >= current_time('Y-m-d') && in_array($res->status,['1',1])){
                    $email_data = kcCommonNotificationData($res,[],'','cancel_appointment');
                    //send cancel email
                    kcSendEmail($email_data);
                    if(!empty($sms_enable) || !empty($whatsapp_enable)){
                        apply_filters('kcpro_send_sms', [
                            'type' => 'cancel_appointment',
                            'appointment_id' => $res->id,
                        ]);
                    }
                }
                //cancel zoom meeting
                if($telemed_active){
                    apply_filters('kct_delete_appointment_meeting', ['id'=>$res->id]);
                }
                //remove google calendar event
                if($google_calendar_enable){
                    apply_filters('kcpro_remove_appointment_event', ['appoinment_id'=>$res->id]);
                }

                //remove google meet event
                if($google_meet_enable){
                    apply_filters('kcgm_remove_appointment_event',['appoinment_id' => $res->id]);
                }
                //delete appointment service mappings
                (new KCAppointmentServiceMapping())->delete(['appointment_id' => $res->id]);

                //delete appointment custom fields
                (new KCCustomFieldData())->delete(['module_type' => 'appointment_module','module_id' => $res->id]);

                (new KCAppointmentReminder())->delete(['appointment_id' => $res->id]);

                if($woocommerce_enable){
                    $order_id = kcAppointmentIsWoocommerceOrder($res->id);
                    if(!empty($order_id)){
                        wp_delete_post($order_id,true);
                    }
                }
                if($encounter_delete){
                    (new KCPatientEncounter())->loopAndDelete(['appointment_id' => $res->id],false);
                }
                (new KCAppointmentPayment())->delete(['appointment_id' => $res->id]);
                do_action('kivicare_custom_form_data_delete', 'appointment_module', $res->id);
            }
        }
        return $instance->delete($condition);
    }
}