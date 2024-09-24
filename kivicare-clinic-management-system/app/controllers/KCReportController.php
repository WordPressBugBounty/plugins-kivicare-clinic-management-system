<?php

namespace App\controllers;

use App\baseClasses\KCRequest;
use DateTime;

class KCReportController extends \App\baseClasses\KCBase
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

		if( ! in_array($this->getLoginUserRole(),[
			'administrator',
			$this->getClinicAdminRole()
		])){
			wp_send_json(kcUnauthorizeAccessResponse(403));
		}
    }

    public function getAllReportType(){

        $data['years']  = $this->getYears(date('Y'));
        $data['months'] = kcGetAllMonth();
        $data['weeks']  = $this->kcGetAllWeeksInVue(date('Y'));
        $data['default_week']  = date('W');
        $data['default_month'] = date('m');
        $data['default_year']  = date('Y');

        $prefix_postfix = kcGetClinicCurrenyPrefixAndPostfix();
        $clinic_currency = [
            'prefix' => !empty($prefix_postfix['prefix']) ? $prefix_postfix['prefix'] : '',
            'postfix' => !empty($prefix_postfix['postfix']) ? $prefix_postfix['postfix'] : ''
        ];

        $color = [
            '#008FFB','#00E396','#FEB019','#FF4560','#775DD0','#546E7A','#A5978B','#C7F464','#C7F464','#C5D86D','#5A2A27','#A300D6', '#2E294E',
            '#ff80ed', '#065535', '#000000', '#133337', '#ffc0cb', '#ffffff', '#ffe4e1', '#008080', '#ff0000', '#e6e6fa', '#ffd700',
            '#00ffff', '#ffa500', '#ff7373', '#0000ff', '#40e0d0', '#d3ffce', '#c6e2ff', '#f0f8ff', '#b0e0e6', '#666666', '#faebd7',
            '#bada55', '#003366', '#fa8072', '#ffb6c1', '#ffff00', '#c0c0c0', '#800000', '#7fffd4', '#800080', '#c39797', '#00ff00',
            '#cccccc', '#eeeeee', '#20b2aa', '#f08080', '#fff68f', '#333333', '#ffc3a0', '#66cdaa', '#c0d6e4', '#ff00ff', '#ff6666',
            '#ffdab9', '#ff7f50', '#cbbeb5', '#468499', '#afeeee', '#008000', '#00ced1', '#f6546a', '#b4eeb4', '#660066', '#b6fcd5',
            '#0e2f44', '#990000', '#f5f5f5', '#808080', '#6897bb', '#000080', '#daa520', '#696969', '#088da5', '#8b0000', '#f5f5dc',
            '#ffff66', '#ccff00', '#8a2be2', '#101010', '#81d8d0', '#0a75ad', '#dddddd', '#ff4040', '#2acaea', '#66cccc', '#420420',
            '#ff1493', '#a0db8e', '#00ff7f', '#cc0000', '#3399ff', '#999999', '#794044'];

        $clinic_colors = apply_filters('kivicare_report_clinic_chart_colors',$color);
        $doctor_colors = apply_filters('kivicare_report_doctor_chart_colors',$color);

	    wp_send_json([
            'status'  => true,
            'data' => $data,
            'clinic_currency' => $clinic_currency,
            'doctor_colors' => $doctor_colors,
            'clinic_colors' => $clinic_colors,
            'message' => esc_html__('Report Type.', 'kc-lang')
        ]);

    }

    public function  getClinicRevenue(){

        if(isKiviCareProActive()){
            $request_data = $this->request->getInputs();
            $response = apply_filters('kcpro_get_clinic_revenue',$request_data);
	        wp_send_json($response);
        }else{
	        wp_send_json([
                'status'  => true,
                'data' => [],
                'labels' => [],
                'message' => esc_html__('Clinic Revenue', 'kc-lang'),
            ]);
        }

    }

    public function getClinicBarChart(){
        if(isKiviCareProActive()){
            $request_data = $this->request->getInputs();
            $response = apply_filters('kcpro_get_clinic_bar_chart',$request_data);
	        wp_send_json($response);
        }else{
	        wp_send_json([
                'status'  => true,
                'date'=> [],
                'data'=>  [],
                'message' => esc_html__('Clinic Revenue', 'kc-lang'),
            ]);
        }

    }

    public function doctorRevenue(){

        if(isKiviCareProActive()){
            $request_data = $this->request->getInputs();
            $response = apply_filters('kcpro_doctor_revenue',$request_data);
	        wp_send_json($response);
        }else{
	        wp_send_json([
                'status'  => true,
                'data'    => [],
                'date'    => [],
                'message' => esc_html__('Clinic Revenue', 'kc-lang'),
            ]);
        }

    }

    public function appointmentCount(){
        if(isKiviCareProActive()){
            $request_data = $this->request->getInputs();
            $response = apply_filters('kcpro_appointment_count',$request_data);
	        wp_send_json($response);
        }else{
	        wp_send_json([
                'status'  => true,
                'data'    => [],
                'date'    => [],
                'message' => esc_html__('doctor Appointments', 'kc-lang'),
            ]);
        }
    }

    public function clinicAppointmentCount(){
        if(isKiviCareProActive()){
            $request_data = $this->request->getInputs();
            $response = apply_filters('kcpro_clinic_appointment_count',$request_data);
	        wp_send_json($response);
        }else{
	        wp_send_json([
                'status'  => true,
                'data'    => [],
                'date'    => [],
                'message' => esc_html__('Clinic Appointments', 'kc-lang'),
            ]);
        }
    }

    public function getYears($end_year = ''){
        $start_year = 2020;
        for ($i = $start_year; $i <= $end_year; $i++)
            $years[$i] = $i;
        return $years;
    }

    public function kcGetAllWeeksInVue($year){

        $date = new DateTime;
        $date->setISODate($year, 53);

        $weeks = ($date->format("W") === "53" ? 53 : 52);
        $data = [];

        for($x=1; $x<=$weeks; $x++){
            $dto = new DateTime();
            $dates['week_start'] = $dto->setISODate($year, $x)->format('Y-m-d');
            $dates['week_end']   = $dto->modify('+6 days')->format('Y-m-d');

            if($x<10) {
                $x = '0'.$x;
            }
            $data[$x] = 'week-'.$x.' ('.$dates['week_start'] .' to '. $dates['week_end'].')';
        }

        return $data;
    }

}