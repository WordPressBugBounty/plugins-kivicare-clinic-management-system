<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use Exception;

class KCWidgetSettingController extends KCBase
{

    /**
     * @var KCRequest
     */
    public $db;

    private $request;

    public function __construct()
    {
        global $wpdb;

        $this->db = $wpdb;

        $this->request = new KCRequest();

        parent::__construct();
	    if ($this->getLoginUserRole() !== 'administrator' ) {
		    wp_send_json(kcUnauthorizeAccessResponse(403));
	    }
    }
    public function saveWidgetSetting(){

        $request_data = $this->request->getInputs();
        if(!empty($request_data['list'])){
            $response = apply_filters('kcpro_save_widget_order_list', [
                'list' => $request_data['list'],
            ]);
        }
        if(!empty($request_data['data'])){
            update_option(KIVI_CARE_PREFIX.'widgetSetting', json_encode($request_data['data']));
            $response = [
                'status' => true,
                'message' => esc_html__('Widget Setting Saved Successfully', 'kc-lang'),
            ];
        }else{
            $response = [
                'status' => false,
                'message' => esc_html__('Widget Setting Update Failed', 'kc-lang'),
            ];
        }

	    wp_send_json($response);
    }

    public function getWidgetSetting(){

        if(isKiviCareProActive()){
            $response = apply_filters('kcpro_get_widget_order_list',[]);
            $widgetOrder = !empty($response['data']) ? $response['data'] : kcDefaultAppointmentWidgetOrder();
        }else{
            $widgetOrder = kcDefaultAppointmentWidgetOrder();
        }

        $data = get_option(KIVI_CARE_PREFIX.'widgetSetting', true);

        if ( gettype($data) !== 'boolean' ) {
            $data = json_decode( $data );
            $data->clinicContactDetails = !empty($data->clinicContactDetails) ? $data->clinicContactDetails : [];
            $data->doctorContactDetails = !empty($data->doctorContactDetails) ? $data->doctorContactDetails : [];
            $data->showClinic = !empty($data->showClinic) && in_array($data->showClinic,['true',true,1,'1']) ? true : false;
            $data->showClinicImage = !empty($data->showClinicImage) && in_array($data->showClinicImage,['true',true,1,'1']) ? true : false;
            $data->showClinicAddress = !empty($data->showClinicAddress) && in_array($data->showClinicAddress,['true',true,1,'1']) ? true : false;
            $data->showDoctorImage = !empty($data->showDoctorImage) && in_array($data->showDoctorImage,['true',true,1,'1']) ? true : false;
            $data->showDoctorExperience = !empty($data->showDoctorExperience) && in_array($data->showDoctorExperience,['true',true,1,'1']) ? true : false;
            $data->showDoctorSpeciality = !empty($data->showDoctorSpeciality) && in_array($data->showDoctorSpeciality,['true',true,1,'1']) ? true : false;
            $data->showDoctorDegree = !empty($data->showDoctorDegree) && in_array($data->showDoctorDegree,['true',true,1,'1']) ? true : false;
            $data->showDoctorRating = !empty($data->showDoctorRating) && in_array($data->showDoctorRating,['true',true,1,'1']) ? true : false;
            $data->showServiceImage = !empty($data->showServiceImage) && in_array($data->showServiceImage,['true',true,1,'1']) ? true : false;
            $data->showServicetype = !empty($data->showServicetype) && in_array($data->showServicetype,['true',true,1,'1']) ? true : false;
            $data->showServicePrice = !empty($data->showServicePrice) && in_array($data->showServicePrice,['true',true,1,'1']) ? true : false;
            $data->showServiceDuration = !empty($data->showServiceDuration) && in_array($data->showServiceDuration,['true',true,1,'1']) ? true : false;
            $data->widget_print = !empty($data->widget_print) && in_array($data->widget_print,['true',true,1,'1']) ? true : false;
            $data->afterWoocommerceRedirect = !empty($data->afterWoocommerceRedirect) && in_array($data->afterWoocommerceRedirect,['true',true,1,'1']) ? true : false;
            $data->skip_service_when_single = !empty($data->skip_service_when_single) && in_array($data->skip_service_when_single,['true',true,1,'1']) ? true : false;
            $response = [
                'status' => true,
                'data' => $data,
                'widgetOrder' => $widgetOrder,
                'message' => esc_html__('Widget Setting data', 'kc-lang'),
            ];
        }else{
            $response = [
                'status' => false,
                'data' => '',
                'widgetOrder' => $widgetOrder,
                'message' => esc_html__('Widget Setting data', 'kc-lang'),
            ];
        }

	    wp_send_json($response);

    }

    public function saveWidgetLoader(){
        $requestData = $this->request->getInputs();

        try{
            if(isset($requestData['widget_loader'])){
                update_option( KIVI_CARE_PREFIX . 'widget_loader',$requestData['widget_loader'] );
                $url = wp_get_attachment_url($requestData['widget_loader']);
	            wp_send_json([
                    'data'=> $url,
                    'status' => true,
                    'message' => esc_html__('Widget logo updated', 'kc-lang')
                ]);
            }
        }catch (Exception $e) {
	        wp_send_json([
                'status' => false,
                'message' => esc_html__('Failed to update Widget logo', 'kc-lang')
            ]);
        }
    }

    public function getWidgetLoader(){
        $logoImageUrl = get_option(KIVI_CARE_PREFIX . 'widget_loader',true);
        $url = '';
        $logo = false;
        if(gettype($logoImageUrl) != 'boolean'){
            $logo = true;
            $url = wp_get_attachment_url($logoImageUrl);
        }
        wp_send_json( [
            'status' => $logo,
            'logo' => isLoaderCustomUrl(),
            'url' => $url
        ]);
    }
}