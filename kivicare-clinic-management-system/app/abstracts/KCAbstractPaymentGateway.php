<?php
namespace App\abstracts;

use App\baseClasses\KCErrorLogger;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * Abstract Payment Gateway Class
 * Base class for all payment gateway implementations
 */
abstract class KCAbstractPaymentGateway {
    
    protected $gateway_id;
    protected $gateway_name;
    protected $gateway_description;

    protected $gateway_logo;
    protected $is_enabled;
    protected $test_mode;
    protected $settings;
    protected $appointment_data;

    protected $settings_fields = [];

    protected $settings_keys;
    
    /**
     * Constructor
     * @param string $setting_key Gateway specific settings
     */
    public function __construct($setting_key) {
        $this->settings_keys = $setting_key;
        $settings = get_option($this->settings_keys, []);
        
        // If settings is a string, try to decode it as JSON
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);

            if(!empty($decoded)){
                // Only use decoded value if it's valid JSON and results in an array
                $settings =  json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
            }
        }

        $this->settings = $settings;
        $this->init();
    }
    
    /**
     * Initialize gateway specific settings
     */
    abstract protected function init();
    
    /**
     * Process the payment
     * @param array $appointment_data Appointment booking data
     * @return array Payment response
     */
    abstract public function process_payment($appointment_data);
    
    /**
     * Validate payment data
     * @param array $payment_data Payment form data
     * @return bool|WP_Error
     */
    abstract public function validate_payment_data($payment_data);
    
    /**
     * Handle payment callback/webhook
     * @param array $callback_data Callback data from payment provider
     * @return array Response data
     */
    abstract public function handle_payment_callback($callback_data);
    
    /**
     * Get gateway ID
     * @return string
     */
    public function get_gateway_id() {
        return $this->gateway_id;
    }
    
    /**
     * Get gateway name
     * @return string
     */
    public function get_gateway_name() {
        return $this->gateway_name;
    }

    public function get_gateway_logo() {
        return $this->gateway_logo;
    }

    public function get_gateway_description() {
        return $this->gateway_description;
    }
    
    /**
     * Check if gateway is enabled
     * @return bool
     */
    public function is_enabled() {
        return $this->is_enabled;
    }
    
    /**
     * Check if in test mode
     * @return bool
     */
    public function is_test_mode() {
        return $this->test_mode;
    }
    
    /**
     * Get setting value
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    /**
     * Set setting value
     * @param string $key Setting key
     * @param mixed $value Setting value
     */
    public function get_settings(){
        return $this->settings;
    }
    
    /**
     * Log gateway activity
     * @param string $message Log message
     * @param string $level Log level (info, error, debug)
     */
    protected function log($message, $level = 'info') {
        KCErrorLogger::instance()->error("[{$this->gateway_id}] {$level}: {$message}");
    }
    
    /**
     * Format amount for payment processing
     * @param float $amount Amount to format
     * @return float
     */
    protected function format_amount($amount) {
        return round($amount, 2);
    }
    
    /**
     * Generate transaction reference
     * @param int $appointment_id Appointment ID
     * @return string
     */
    protected function generate_transaction_ref($appointment_id) {
        return $this->gateway_id . '_' . $appointment_id . '_' . time();
    }
    
    /**
     * Create payment response array
     * @param string $status Payment status (success, failed, pending)
     * @param string $message Response message
     * @param array $data Additional response data
     * @return array
     */
    protected function create_payment_response($status, $message, $data = []) {
        return array(
            'status' => $status,
            'message' => $message,
            'gateway' => $this->gateway_id,
            'data' => $data,
            'timestamp' => current_time('timestamp')
        );
    }
    /**
     * Get return URL after payment
     * @param int $appointment_id Appointment ID
     * @return string Return URL
     */
    protected function get_return_url($appointment_id) {
        return rest_url('kivicare/v1/appointments/payment-success?appointment_id=' . $appointment_id . '&gateway='.$this->gateway_id);   
    }
    
    /**
     * Get cancel URL
     * @param int $appointment_id Appointment ID
     * @return string Cancel URL
     */
    protected function get_cancel_url($appointment_id) {
        return rest_url('kivicare/v1/appointments/payment-cancel?appointment_id=' . $appointment_id . '&gateway='.$this->gateway_id);
    }
    
    public function init_hook(){} 

    public function get_fields(): array {
        return $this->settings_fields;
    }
    public abstract function update_settings($settings);

    protected function save_settings()
    {
        $json_settings = json_encode($this->settings);
        update_option($this->settings_keys, $json_settings);
    }
}