<?php

namespace App\paymentGateways;

use App\abstracts\KCAbstractPaymentGateway;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Knit Pay Payment Gateway Integration
 */
class KCKnitPay extends KCAbstractPaymentGateway
{
    /**
     * @var string
     */
    private $config_id;

    /**
     * @var string
     */
    private $current_transaction_config_id;

    /**
     * Set Configuration ID for current transaction
     * 
     * @param string $id
     */
    public function set_config_id($id)
    {
        $this->current_transaction_config_id = $id;
    }

    /**
     * Initialize Gateway
     * 
     * Registers settings, hooks, and validates plugin dependencies.
     */
    protected function init()
    {        
        $this->gateway_id          = 'knit_pay';
        $this->gateway_name        = __('Knit Pay', 'kivicare-clinic-management-system');
        $this->gateway_logo        = KIVI_CARE_DIR_URI . 'assets/images/knit-pay-logo.png';
        $this->gateway_description = __('Pay securely using Knit Pay supported gateways.', 'kivicare-clinic-management-system');

        // Add Filter to intercept redirect URL
        add_filter('pronamic_payment_redirect_url', [$this, 'filter_redirect_url'], 10, 2);

        // Check if Knit Pay is active
        if (!class_exists('\Pronamic\WordPress\Pay\Plugin')) {
            $this->is_enabled      = false;
            $this->settings_fields = [];
            return;
        }

        $this->is_enabled = !empty($this->settings['enableKnitPay']);
        $this->test_mode  = false;

        $this->settings_fields = [
            [
                'name'        => 'enableKnitPay',
                'type'        => 'checkbox',
                'label'       => __('Enable Knit Pay', 'kivicare-clinic-management-system'),
                'default'     => 'off',
                'description' => __('Enable or disable Knit Pay payment gateway.', 'kivicare-clinic-management-system'),
            ],
            [
                'name'        => 'currency',
                'label'       => __('Currency', 'kivicare-clinic-management-system'),
                'type'        => 'select',
                'options'     => kcCountryCurrencyList(),
                'default'     => 'USD',
                'description' => __('Select the currency for transactions.', 'kivicare-clinic-management-system'),
                'validation'  => ['required' => __('Currency is required', 'kivicare-clinic-management-system')]
            ]
        ];

        // Repeater Field for Configurations
        $this->settings_fields[] = [
            'name'            => 'knit_pay_configs',
            'type'            => 'repeater',
            'label'           => __('Payment Configurations', 'kivicare-clinic-management-system'),
            'add_button_text' => __('Add Configuration', 'kivicare-clinic-management-system'),
            'fields'          => [
                [
                    'name'       => 'config_id',
                    'label'      => __('Configuration', 'kivicare-clinic-management-system'),
                    'type'       => 'select',
                    'options'    => $this->get_knit_pay_configs(),
                    'col'        => 'md-6',
                    'group'      => 'main',
                    'validation' => ['required' => __('Please select a configuration', 'kivicare-clinic-management-system')]
                ],
                [
                    'name'       => 'label',
                    'label'      => __('Label', 'kivicare-clinic-management-system'),
                    'type'       => 'text',
                    'col'        => 'md-6',
                    'group'      => 'main',
                    'validation' => ['required' => __('Label is required', 'kivicare-clinic-management-system')]
                ],
                [
                    'name'  => 'description',
                    'label' => __('Description', 'kivicare-clinic-management-system'),
                    'type'  => 'textarea',
                    'col'   => 'md-12',
                    'group' => 'main'
                ],
                [
                    'name'  => 'icon',
                    'label' => __('Icon', 'kivicare-clinic-management-system'),
                    'type'  => 'image',
                    'col'   => 'md-12',
                    'group' => 'side'
                ]
            ]
        ];
    }

    /**
     * Get Enabled Configs
     * 
     * @return array
     */
    public function get_enabled_configs()
    {
        $enabled = [];
        $configs = isset($this->settings['knit_pay_configs']) ? $this->settings['knit_pay_configs'] : [];

        if (is_array($configs)) {
            foreach ($configs as $config) {
                if (!empty($config['config_id'])) {
                    $enabled[] = [
                        'id'    => $config['config_id'],
                        'label' => !empty($config['label']) ? $config['label'] : $config['config_id'],
                        'icon'  => !empty($config['icon']) ? $config['icon'] : '',
                        'description' => !empty($config['description']) ? $config['description'] : ''
                    ];
                }
            }
        }
        
        return $enabled;
    }

    /**
     * Filter Redirect URL
     * 
     * Intercepts the Knit Pay redirect URL to ensure it hits the KiviCare REST API endpoint.
     * 
     * @param string $url The original redirect URL.
     * @param \Pronamic\WordPress\Pay\Payments\Payment $payment The payment object.
     * @return string The modified redirect URL.
     */
    public function filter_redirect_url($url, $payment)
    {
        if ('kivicare' === $payment->get_source()) {
            $custom_url = $payment->get_meta('approved_appointment_url');
            if (!empty($custom_url)) {
                return $custom_url;
            }
        }
        return $url;
    }

    /**
     * Update Settings
     * 
     * Processes and saves the gateway settings with server-side validation.
     * 
     * @param array $settings The settings to update.
     * @return array The processed settings.
     */
    public function update_settings($settings)
    {
        // First, check if the user is attempting to enable the gateway.
        $is_attempting_to_enable = isset($settings['enableKnitPay']) && filter_var($settings['enableKnitPay'], FILTER_VALIDATE_BOOLEAN);

        if ($is_attempting_to_enable) {
            // If they are, perform the primary dependency check immediately.
            if (!class_exists('\Pronamic\WordPress\Pay\Plugin')) {
                throw new \Exception(esc_html__('Cannot enable Knit Pay. The Knit Pay plugin is not active.', 'kivicare-clinic-management-system'));
            }
        }

        // Initialize fields to process all incoming data correctly.
        if (empty($this->settings_fields)) {
            $this->init();
        }
        
        // Merge incoming settings with existing ones.
        $processed = $this->settings;

        foreach ($this->settings_fields as $field) {
            if (isset($field['type']) && $field['type'] === 'header') continue;

            if (isset($settings[$field['name']])) {
                $val = $settings[$field['name']];

                if (is_array($val) && isset($val['value'])) {
                    $val = $val['value'];
                }

                if ($field['type'] === 'checkbox' || $field['type'] === 'checkbox_switch') {
                    $processed[$field['name']] = filter_var($val, FILTER_VALIDATE_BOOLEAN);
                } else {
                    $processed[$field['name']] = $val;
                }
            }
        }
        
        // Re-check based on processed data for the main validation logic.
        $is_enabling = isset($processed['enableKnitPay']) && $processed['enableKnitPay'] === true;

        if ($is_enabling) {

            // Validate that at least one configuration has been added.
            if (empty($processed['knit_pay_configs']) || !is_array($processed['knit_pay_configs'])) {
                throw new \Exception(esc_html__('You must add at least one payment configuration to enable Knit Pay.', 'kivicare-clinic-management-system'));
            }

            // Validate other required fields.
            $required = ['currency' => __('Currency', 'kivicare-clinic-management-system')];

            foreach ($required as $field_key => $field_label) {
                $val = $processed[$field_key] ?? null;
                if (empty($val)) {
                    /* translators: %s: Field label */
                    $error_msg = sprintf(__('%s is required to enable Knit Pay.', 'kivicare-clinic-management-system'), $field_label);
                    throw new \Exception(esc_html($error_msg));
                }
            }
        }

        $this->settings = $processed;
        $this->save_settings();
        return $this->settings;
    }

    /**
     * Get Knit Pay Configurations
     * 
     * Retrieves available payment configurations from Pronamic Pay.
     * 
     * @return array List of configurations in label/value format.
     */
    private function get_knit_pay_configs()
    {
        if (!class_exists('\Pronamic\WordPress\Pay\Plugin')) {
            return [];
        }

        $options = \Pronamic\WordPress\Pay\Plugin::get_config_select_options('knit_pay');
        $formatted_options = [];

        if (empty($options)) {
            $formatted_options[] = [
                'value' => '',
                'label' => __('No configurations found', 'kivicare-clinic-management-system')
            ];
        } else {
            foreach ($options as $id => $label) {
                $formatted_options[] = [
                    'value' => $id,
                    'label' => $label
                ];
            }
        }

        return $formatted_options;
    }

    /**
     * Process Payment
     * 
     * Initiates the payment process with Knit Pay/Pronamic.
     * 
     * @param array $appointment_data Data required for the payment (amount, id, etc.).
     * @return array Payment response indicating success/pending/failure.
     */
    public function process_payment($appointment_data)
    {
        if (!class_exists('\Pronamic\WordPress\Pay\Plugin')) {
            return $this->create_payment_response('failed', 'Knit Pay plugin is not active.');
        }

        try {
            // Use specific config ID if set (via factory), otherwise fallback to checking settings
            $config_id = $this->current_transaction_config_id;

            if (empty($config_id)) {
                return $this->create_payment_response('failed', 'No specific Knit Pay configuration selected.');
            }

            $currency_setting = $this->get_setting('currency');
            $currency_code    = 'USD';

            if (is_array($currency_setting) && isset($currency_setting['value'])) {
                $currency_code = $currency_setting['value'];
            } elseif (is_string($currency_setting) && !empty($currency_setting)) {
                $currency_code = $currency_setting;
            }

            // Initialize Payment Object
            $payment = new \Pronamic\WordPress\Pay\Payments\Payment();
            $payment->source      = 'kivicare';
            $payment->source_id   = $appointment_data['appointment_id'];
            $payment->order_id    = $appointment_data['appointment_id'] . '_' . time();
            $payment->title       = 'Appointment #' . $appointment_data['appointment_id'];
            $payment->config_id   = $config_id;
            $payment->set_description($appointment_data['service_name'] ?? 'Appointment Booking');

            $customer = new \Pronamic\WordPress\Pay\Customer();
            $customer->set_name(new \Pronamic\WordPress\Pay\ContactName($appointment_data['patient_name'] ?? 'Guest'));
            $customer->set_email($appointment_data['patient_email'] ?? '');
            $payment->set_customer($customer);

            $currency = \Pronamic\WordPress\Money\Currency::get_instance($currency_code);
            $payment->set_total_amount(new \Pronamic\WordPress\Money\Money($appointment_data['amount'], $currency));

            // Save immediately to generate Payment ID
            $payment->save();
            $kp_payment_id = $payment->get_id();

            // Construct dynamic gateway ID for the return URL so validation passes
            $specific_gateway_id = 'knit_pay_' . $config_id;

            // Generate REST API Return URLs
            $return_url = add_query_arg([
                'appointment_id' => $appointment_data['appointment_id'],
                'gateway'        => 'knit_pay',
                'kp_payment_id'  => $kp_payment_id
            ], rest_url('kivicare/v1/appointments/payment-success'));

            $cancel_url = add_query_arg([
                'appointment_id' => $appointment_data['appointment_id'],
                'gateway'        => 'knit_pay',
                'kp_payment_id'  => $kp_payment_id
            ], rest_url('kivicare/v1/appointments/payment-cancel'));

            $payment->set_meta('approved_appointment_url', $return_url);
            $payment->set_meta('canceled_appointment_url', $cancel_url);
            $payment->save();

            $payment = \Pronamic\WordPress\Pay\Plugin::start_payment($payment);

            if (!$payment) {
                return $this->create_payment_response('failed', 'Payment initialization failed.');
            }

            $redirect_url = $payment->get_pay_redirect_url();

            if ($redirect_url) {
                return $this->create_payment_response('pending', 'Redirecting...', [
                    'redirect_url' => $redirect_url,
                    'payment_id'   => $kp_payment_id,
                    'currency'     => $currency_code,
                    'gateway'      => $specific_gateway_id,
                ]);
            } else {
                return $this->create_payment_response('failed', 'Could not retrieve payment redirect URL.');
            }
        } catch (\Exception $e) {
            return $this->create_payment_response('failed', 'Payment Error: ' . $e->getMessage());
        }
    }

    /**
     * Validate Payment Data
     * 
     * @param array $payment_data Input data to validate.
     * @return bool|WP_Error True if valid, error object otherwise.
     */
    public function validate_payment_data($payment_data)
    {
        if (empty($payment_data['amount']) || $payment_data['amount'] <= 0) {
            return new WP_Error('invalid_amount', 'Invalid payment amount');
        }
        return true;
    }

    /**
     * Handle Callback
     * 
     * Verifies payment status in Knit Pay/Pronamic using the payment ID from the URL.
     * Implements fallback mechanisms for retrieving the payment object.
     * 
     * @param array $callback_data Data from the return URL (GET params).
     * @return array Standardized payment response.
     */
    public function handle_payment_callback($callback_data)
    {
        $kp_payment_id = isset($callback_data['kp_payment_id']) ? $callback_data['kp_payment_id'] : null;

        if (!$kp_payment_id) {
            return $this->create_payment_response('failed', 'Payment ID missing in callback.');
        }

        $payment = null;

        // method 1: Try using the global helper function
        if (function_exists('get_pronamic_payment')) {
            $payment = \get_pronamic_payment($kp_payment_id);
        }

        // Method 2: Try via Plugin data store
        if (!$payment && class_exists('\Pronamic\WordPress\Pay\Plugin')) {
            try {
                $payment = \Pronamic\WordPress\Pay\Plugin::instance()->payments_data_store->get_payment($kp_payment_id);
            } catch (\Exception $e) {
                // Silently fail to fallback
            }
        }

        // Method 3: Fallback to direct post meta retrieval
        if (!$payment) {
            $status         = get_post_meta($kp_payment_id, '_pronamic_payment_status', true);
            $transaction_id = get_post_meta($kp_payment_id, '_pronamic_payment_transaction_id', true);

            if (empty($status)) {
                return $this->create_payment_response('failed', 'Payment record not found.');
            }
        } else {
            $status         = $payment->get_status();
            $transaction_id = $payment->get_transaction_id();
        }

        $is_success = false;

        // Check using constant if available
        if (defined('\Pronamic\WordPress\Pay\Payments\PaymentStatus::SUCCESS')) {
            if ($status === \Pronamic\WordPress\Pay\Payments\PaymentStatus::SUCCESS) {
                $is_success = true;
            }
        }

        // Fallback string checks for standard meta values
        $success_statuses = ['payment_completed', 'success', 'completed', 'paid'];

        if (in_array($status, $success_statuses)) {
            $is_success = true;
        }

        if ($is_success) {
            return $this->create_payment_response('success', 'Payment successful', [
                'transaction_id' => $transaction_id ? $transaction_id : $kp_payment_id,
                'payment_id'     => $kp_payment_id
            ]);
        }

        return $this->create_payment_response('failed', 'Payment status is: ' . $status);
    }
}