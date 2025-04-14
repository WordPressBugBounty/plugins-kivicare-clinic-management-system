<?php

namespace App\controllers;

use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;
use App\models\KCAppointment;
use App\models\KCAppointmentServiceMapping;
use Omnipay\Omnipay;
use Exception;
class KCPaymentController extends KCBase
{
    /**
     * @var KCRequest
     */
    private $request;

    public $db;

    public $returnUrl;

    public $cancelUrl;

    public function __construct()
    {
        $this->request = new KCRequest();

        $this->returnUrl = get_site_url().'?kivicare_payment=success&appointment_id=';
        $this->cancelUrl = get_site_url().'?kivicare_payment=failed&appointment_id=';

        global $wpdb;

        $this->db = $wpdb;

        parent::__construct();
    }

    public function changeWooCommercePaymentStatus () {
        if ( $this->getLoginUserRole() !== 'administrator' ) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $data = $this->request->getInputs();
        $status = false;
        $message = esc_html__('Woocommerce status can\'t change.', 'kc-lang');

        if(kcLocalPaymentGatewayEnable() == 'off'
            && empty(kcPaypalSettingData('enablePaypal'))
            && !(apply_filters('kivicare_razorpay_enable',false))
            && !(apply_filters('kivicare_stripepay_enable',false))
            && $data['status'] == 'off'){
	        wp_send_json([
                'status'  => false,
                'message' => esc_html__('Atleast One Payment Gateway should be enable', 'kc-lang'),
            ]);
        }

        if(!iskcWooCommerceActive()){
	        wp_send_json([
                'status'  => false,
                'message' => esc_html__('Woocommerce Plugin Is Not Active', 'kc-lang'),
            ]);
        }

        if(isKiviCareTelemedActive()){
             apply_filters('kct_change_woocommerce_module_status', [
                'status' => $data['status']
            ]);
            $status = true;
            $message = esc_html__('Woocommerce change status.', 'kc-lang');
        }elseif (isKiviCareGoogleMeetActive()){
             apply_filters('kcgm_change_woocommerce_module_status', [
                'status' => $data['status']
            ]);
            $status = true;
            $message = esc_html__('Woocommerce change status.', 'kc-lang');
        }
        elseif(isKiviCareProActive()){
             apply_filters('kcpro_change_woocommerce_module_status', [
                'status' => $data['status']
            ]);
            $status = true;
            $message = esc_html__('Woocommerce change status.', 'kc-lang');
        }

	    wp_send_json([
            'status'  => $status,
            'message' => $message
        ]);
    }

    public function savePaypalConfig(){

        if ( $this->getLoginUserRole() !== 'administrator' ) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $request_data = $this->request->getInputs();
        $request_data['data'] = stripslashes($request_data['data']);

        if(!empty($request_data['data'])){
            if(kcLocalPaymentGatewayEnable() == 'off'
                && kcWoocommercePaymentGatewayEnable() === 'off'
                && !(apply_filters('kivicare_razorpay_enable',false))
                && empty($request_data['data']['enablePaypal']) && !(apply_filters('kivicare_stripepay_enable',false))){
                $response = [
                    'status' => false,
                    'message' => esc_html__('Atleast One Payment Gateway should be enable', 'kc-lang'),
                ];
            }else{
                update_option(KIVI_CARE_PREFIX.'paypalConfig', $request_data['data']);
                $response = [
                    'status' => true,
                    'message' => esc_html__('Paypal Setting Saved Successfully', 'kc-lang'),
                ];
            }
        }else{
            $response = [
                'status' => false,
                'message' => esc_html__('Paypal Setting Update Failed', 'kc-lang'),
            ];
        }

	    wp_send_json($response);
    }

    public function saveRazorpayConfig(){
        if ( $this->getLoginUserRole() !== 'administrator' ) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $request_data = $this->request->getInputs();
        
        $request_data = $request_data['data'];

        $rules = [
			'enable'    => 'required',
			'api_key'    => 'required',
			'secret_key' => 'required',
			'currency'   => 'required',
		];

		$errors = kcValidateRequest( $rules, $request_data );

		if ( count( $errors ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => $errors[0]
			] );
		}

        $response = apply_filters('kivicare_save_razorpay_configurations',$request_data);
        if(is_array($response)){
	        wp_send_json($response);
        }else{
	        wp_send_json([
                'status' => false,
                'message' => esc_html__('Failed to saved razorpay configurations','kc-lang')
            ]);
        }

    }

    public function saveStripepayConfig(){
        if ( $this->getLoginUserRole() !== 'administrator' ) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $request_data = $this->request->getInputs();
        
        $request_data = $request_data['data'];

        $rules = [
			'enable'    => 'required',
            'mode'    => 'required',
			'api_key'    => 'required',
			'currency'   => 'required',
		];

		$errors = kcValidateRequest( $rules, $request_data );

		if ( count( $errors ) ) {
			wp_send_json( [
				'status'  => false,
				'message' => $errors[0]
			] );
		}

        $response = apply_filters('kivicare_save_stripepay_configurations',$request_data);
        if(is_array($response)){
	        wp_send_json($response);
        }else{
	        wp_send_json([
                'status' => false,
                'message' => esc_html__('Failed to saved stripepay configurations','kc-lang')
            ]);
        }

    }

    public function changeLocalPaymentStatus(){
        if ( $this->getLoginUserRole() !== 'administrator' ) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }
        $data = $this->request->getInputs();
        $status = false;

        if(empty(kcPaypalSettingData('enablePaypal')) 
           && kcWoocommercePaymentGatewayEnable() === 'off' 
           && !(apply_filters('kivicare_razorpay_enable',false)) 
           && !(apply_filters('kivicare_stripepay_enable',false))
            && $data['status'] === 'off'){
            $message = esc_html__('Atleast One Payment Gateway should be enable', 'kc-lang');
        }else{
            update_option(KIVI_CARE_PREFIX.'local_payment_status', $data['status']);
            $status = true;
            $message = esc_html__('Local Payment Setting Saved Successfully', 'kc-lang');
        }

	    wp_send_json([
            'status' => $status,
            'message' => $message,
        ]);
    }
    public function getPaymentStatusAll(){

        if ( $this->getLoginUserRole() !== 'administrator' ) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        //paypal payment setting
        $paypalSetting = kcPaypalSettingData('all');

        $response = array(
            'data' => kcWoocommercePaymentGatewayEnable(),
            'paypal' => !empty($paypalSetting) ? $paypalSetting : 'off',
            'local_payment' => kcLocalPaymentGatewayEnable(),
            'razorpay' => apply_filters('kivicare_get_razorpay_configurations',[]),
            'stripepay' => apply_filters('kivicare_get_stripepay_configurations',[])
        );
        $response = apply_filters('kivicare_all_payment_methods_setting',$response);
        $response = !empty($response) && is_array($response) ? $response : [];
        $response = array_merge($response,[
            'status'  => true,
            'message' => esc_html__('Woocommerce status.', 'kc-lang'),
        ]);
	    wp_send_json($response);
    }

    public function makePaypalPayment($request_data,$appointment_id){
        if(empty(kcPaypalSettingData('mode')) || empty(kcPaypalSettingData('client_id'))
        || empty(kcPaypalSettingData('client_secret')) || empty(kcPaypalSettingData('currency'))
        || empty(kcPaypalSettingData('enablePaypal'))){
            return[
                'status' => false,
                'message' => __('Paypal Configuration is not proper')
            ];
        }
        $serviceItems = [];
        $totalPrice = 0;
        $currency = kcPaypalSettingData('currency');
        $currency = $currency->id;
        $request_data['id'] = (int)$appointment_id;
        foreach ($request_data['visit_type'] as $service){
            $tempService = [];
            $price = $this->db->get_var("SELECT charges FROM {$this->db->prefix}kc_service_doctor_mapping WHERE doctor_id =".(int)$request_data['doctor_id']['id']." AND service_id =".(int)$service['service_id']);;
            if(empty($price)){
               $price = 0 ;
            }
            $tempService['price'] = $price;
            $totalPrice += $price;
            $tempService['quantity'] = 1;
            $tempService['name'] = $service['name'];
            $serviceItems[] = $tempService;
        }

        $returnUrl = $this->returnUrl . $request_data['id']; 
        $cancelUrl = $this->cancelUrl.$request_data['id'];

        $query_args = [];

        if (!empty($request_data['is_dashboard'])) {
            $query_args['is_dashboard'] = $request_data['is_dashboard'];
            $returnUrl = add_query_arg($query_args, $returnUrl);
            $cancelUrl = add_query_arg($query_args, $cancelUrl);
            
        } else if (!empty($request_data['pageId'])) {

            $returnUrl = add_query_arg(
                [
                    'kivicare_payment' => 'success',
                    'appointment_id' => $request_data['id']
                ],
                get_permalink($request_data['pageId'])
            );

            $cancelUrl = add_query_arg(
                [
                    'kivicare_payment' => 'failed',
                    'appointment_id' => $request_data['id']
                ],
                get_permalink($request_data['pageId'])
            );
        }

        try {
            $gateway = $this->configPaypalApi();
            $params = array(
                'amount' => $totalPrice,
                'currency' => $currency,
                'returnUrl' => $returnUrl,
                'cancelUrl' => $cancelUrl,
                'transactionId' => $request_data['id'],
            );
            if(!empty($request_data['tax'])){
                $total_tax = collect($request_data['tax'])->sum('charges');
                if(!empty($total_tax)){
                    $totalPrice = $totalPrice + $total_tax;
                    $params['amount'] = $params['amount'] + $total_tax;
                    $params['taxAmount'] = $total_tax;
                }   
            }
            $response = $gateway->purchase($params)->send();
            if ($response->isRedirect()) {
                $this->db->delete($this->db->prefix."kc_payments_appointment_mappings",['appointment_id' => $request_data['id']]);
                $this->db->insert($this->db->prefix."kc_payments_appointment_mappings",[
                        'appointment_id' =>$request_data['id'],
                    'payment_mode' => 'paypal_rest',
                    'amount' => (int)$totalPrice,
                    'request_page_url' => sanitize_text_field(wp_unslash(wp_get_referer())),
                    'currency' => $currency
                ]);
                return[
                    'woocommerce_cart_data' => [ 'woocommerce_redirect' => $response->getRedirectUrl() ],
                    'redirect' => $response->getRedirectUrl(),
                    'status' => true
                ];
            } else {
                return[
                    'status' => false,
                    'message' => $response->getMessage()
                ];
            }
        } catch(Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function paymentSuccess(){
        $payment_status = 'failed';
        $request_data = $this->request->getInputs();
        $appointment_id = $request_data['appointment_id'];
        if (array_key_exists('paymentId', $request_data) && array_key_exists('PayerID', $request_data)) {
            $gateway = $this->configPaypalApi();
            $transaction = $gateway->completePurchase(array(
                'payer_id'             => $request_data['PayerID'],
                'transactionReference' => $request_data['paymentId'],
            ));
            $response = $transaction->send();
            if ($response->isSuccessful()) {
                // The customer has successfully paid.
                $arr_body = $response->getData();
                $payment_id = $arr_body['id'];
                $payer_id = $arr_body['payer']['payer_info']['payer_id'];
                $payer_email = $arr_body['payer']['payer_info']['email'];
                $payment_status = $arr_body['state'];
                $tempData = [
                    'payment_id' => esc_sql($payment_id),
                    'payer_id' => esc_sql($payer_id),
                    'payer_email' => esc_sql($payer_email),
                    'payment_status' => esc_sql($payment_status)
                ];
                $this->db->update($this->db->prefix."kc_payments_appointment_mappings",$tempData,['appointment_id' => $appointment_id]);
                $this->db->update($this->db->prefix."kc_appointments",['status' => 1],['id' => $appointment_id]);
                if(empty($this->db->get_var("SELECT notification_status FROM {$this->db->prefix}kc_payments_appointment_mappings WHERE appointment_id ={$appointment_id}"))){
                    kivicareWoocommercePaymentComplete($appointment_id,'paypal');
                }        
                if(isset($request_data['is_dashboard']) && !empty($request_data['is_dashboard'] ) && $request_data['is_dashboard']  === 'true'){
                    
                    $redirect_url = add_query_arg(
                        [
                            'kivicare_payment' => 'success',
                            'appointment_id' => $appointment_id
                        ],
                        admin_url('admin.php?page=dashboard#all-appointment-list')
                    );
                    wp_safe_redirect($redirect_url);
                    exit;
                }
            }
        }
        ?>
        <script>
            try {
                document.addEventListener('DOMContentLoaded', () => {
                    kivicareCheckPaymentStatus('<?php echo esc_html(trim($payment_status));?>','<?php echo esc_html($appointment_id);?>');
                })
                const url = new URL(window.location.href);
                const paramsToRemove = [
                    'kivicare_payment',
                    'appointment_id',
                    'paymentId',
                    'token',
                    'PayerID'
                ];
                paramsToRemove.forEach(param => url.searchParams.delete(param));
                window.history.replaceState({}, document.title, url.toString());
            } catch (error) {
                console.log(error);
            }
        </script>
    <?php
    }

    public function paymentFailedPage(){
        $appointment_id = 0;
        $request_data = $this->request->getInputs();
        if(array_key_exists('appointment_id',$request_data) && !empty($request_data['appointment_id'])) {
            $appointment_id = (int)$request_data['appointment_id'];
            $payment_status = $request_data['kivicare_payment'];
            (new KCAppointment())->loopAndDelete(['id' => $appointment_id],true);
        }

        if(isset($request_data['is_dashboard']) && !empty($request_data['is_dashboard'] ) && $request_data['is_dashboard']  === 'true'){
                    
            $redirect_url = add_query_arg(
                [
                    'kivicare_payment' => 'failed',
                    'appointment_id' => $appointment_id
                ],
                admin_url('admin.php?page=dashboard#all-appointment-list')
            );
        
            wp_safe_redirect($redirect_url);
            exit;
        }
        
        ?>
        <script>

            try {
                document.addEventListener('DOMContentLoaded', () => {
                    kivicareCheckPaymentStatus('<?php echo esc_html(trim($payment_status));?>','<?php echo esc_html($appointment_id);?>');
                })
                const url = new URL(window.location.href);
                const paramsToRemove = [
                    'kivicare_payment',
                    'appointment_id',
                    'token',
                ];
                paramsToRemove.forEach(param => url.searchParams.delete(param));
                window.history.replaceState({}, document.title, url.toString());
            } catch (error) {
                console.log(error);
            }
        </script>
        <?php
    }

    public function configPaypalApi(){
        $gateway = Omnipay::create('PayPal_Rest');
        $gateway->setClientId(kcPaypalSettingData('client_id'));
        $gateway->setSecret(kcPaypalSettingData('client_secret'));
        $mode = kcPaypalSettingData('mode');
        $developerMode = !empty($mode) && !empty(kcPaypalSettingData('mode')->label) && kcPaypalSettingData('mode')->label == 'Sandbox';
        if($developerMode){
            if (method_exists($gateway, 'setDeveloperMode')) {
                $gateway->setDeveloperMode(true);
            } else {
                $gateway->setTestMode(true);
            }
        }else{
            $gateway->setTestMode(false);
        }

        return $gateway;
    }

    public function getAppointmentPaymentStatus(){
        $request_data = $this->request->getInputs();
        $request_data['id'] = (int)$request_data['id'];
        if(!((new KCAppointment())->appointmentPermissionUserWise($request_data['id']))){
            wp_send_json( kcUnauthorizeAccessResponse(403) );
        }
        $payment_status = $this->db->get_var("SELECT payment_status FROM {$this->db->prefix}kc_payments_appointment_mappings WHERE appointment_id ={$request_data['id']}");
        if(empty($payment_status)){
            $status = 'failed';
        }else{
            $status = $payment_status;
        }

        wp_send_json([
            'data' => $status,
            'status' => true
        ]);
    }

    public function getRazorpayCurrencyList(){

        if ( $this->getLoginUserRole() !== 'administrator' ) {
	        wp_send_json(kcUnauthorizeAccessResponse(403));
        }

        $response = apply_filters('kivicare_razorpay_currency_list', []);
        
        $response = !empty($response) && is_array($response) ? $response : [];

	    wp_send_json($response);
    }
}