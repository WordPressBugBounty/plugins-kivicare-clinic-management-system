<?php
namespace App\paymentGateways;

use App\abstracts\KCAbstractPaymentGateway;
use Exception;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * PayPal Payment Gateway Implementation using REST API
 * Uses WordPress HTTP functions instead of cURL for better WordPress integration
 */
class KCPaypal extends KCAbstractPaymentGateway
{

    private $client_id;
    private $client_secret;
    private $api_endpoint;
    private $access_token;

    private $currency = 'USD'; // Default currency


    /**
     * Initialize PayPal gateway
     */
    protected function init()
    {
        $this->gateway_id = 'paypal';
        $this->gateway_name = __('PayPal', 'kivicare-clinic-management-system');
        $this->gateway_logo = KIVI_CARE_DIR_URI . 'assets/images/paypal-logo.png';
        $this->gateway_description = __('Pay securely using PayPal', 'kivicare-clinic-management-system');

        // Set API credentials
        $this->client_id = $this->get_setting('client_id');
        $this->client_secret = $this->get_setting('client_secret');

        /**
         * Load and validate Stripe settings
         */

        // Get all settings
        $settings = $this->settings;

        // Set enabled status
        $this->is_enabled = isset($settings['enablePaypal']) && $settings['enablePaypal'] === true;

        // Set test mode - handle both array and string/numeric values
        if (isset($settings['mode'])) {
            if (is_array($settings['mode']) && isset($settings['mode']['id'])) {
                $this->test_mode = $settings['mode']['id'] == '0';
            } else {
                $this->test_mode = ($settings['mode'] == '0' || $settings['mode'] === 0);
            }
        } else {
            $this->test_mode = false;
        }

        // Set currency - handle both array and string values
        if (isset($settings['currency'])) {
            if (is_array($settings['currency']) && isset($settings['currency']['id'])) {
                $this->currency = $settings['currency']['id'];
            } else {
                $this->currency = $settings['currency'];
            }
        } else {
            $this->currency = 'USD';
        }

        // Validate critical settings
        // if (empty($this->client_secret)) {
        //     $this->log('Paypal API key is missing', 'error');
        // }

        // Set API endpoint based on test mode
        $this->api_endpoint = $this->test_mode
            ? 'https://api.sandbox.paypal.com'
            : 'https://api.paypal.com';

        $this->settings_fields = [
            [
                'name' => 'enablePaypal',
                'type' => 'checkbox',
                'label' => __('Enable PayPal', 'kivicare-clinic-management-system'),
                'default' => 'off',
                'description' => __('Enable or disable PayPal payment gateway.', 'kivicare-clinic-management-system'),
            ],
            [
                'name' => 'mode',
                'label' => __('Mode', 'kivicare-clinic-management-system'),
                'type' => 'select',
                'options' => [
                    ['value' => '0', 'label' => __('Sandbox (Test)', 'kivicare-clinic-management-system')],
                    ['value' => '1', 'label' => __('Live', 'kivicare-clinic-management-system')],
                ],
                'default' => 0,
                'description' => __('Select the mode for PayPal payments.', 'kivicare-clinic-management-system'),
                'validation' => ['required' => __('Mode is required', 'kivicare-clinic-management-system')]
            ],
            [
                'name' => 'client_id',
                'label' => __('Client ID', 'kivicare-clinic-management-system'),
                'placeholder' => 'Client ID',
                'type' => 'input',
                'inputType' => 'text',
                'validation' => ['required' => __('Client ID is required', 'kivicare-clinic-management-system')]
            ],
            [
                'name' => 'client_secret',
                'label' => __('Client Secret', 'kivicare-clinic-management-system'),
                'placeholder' => 'App Secret',
                'type' => 'input',
                'inputType' => 'password',
                'validation' => ['required' => __('Client secret is required', 'kivicare-clinic-management-system')]
            ],
            [
                'name' => 'currency',
                'label' => __('Currency', 'kivicare-clinic-management-system'),
                'type' => 'select',
                'options' => kcCountryCurrencyList(),
                'validation' => ['required' => __('Currency is required', 'kivicare-clinic-management-system')]
            ]
        ];
    }

    /**
     * Process PayPal payment using REST API
     * @param array $appointment_data Appointment booking data
     * @return array Payment response
     */
    public function process_payment($appointment_data)
    {
        try {
            $this->appointment_data = $appointment_data;

            // Validate required data
            if (!$this->validate_appointment_data($appointment_data)) {
                return $this->create_payment_response(
                    'failed',
                    'Invalid appointment data provided'
                );
            }

            // Get access token
            $access_token = $this->get_access_token();
            if (!$access_token) {
                return $this->create_payment_response(
                    'failed',
                    'Failed to authenticate with PayPal'
                );
            }

            $amount = $this->format_amount($appointment_data['amount']);
            $currency = isset($appointment_data['currency']) ? $appointment_data['currency'] : 'USD';
            $transaction_ref = $this->generate_transaction_ref($appointment_data['appointment_id']);

            // Prepare payment data for PayPal REST API
            $payment_data = [
                'intent' => 'sale',
                'payer' => [
                    'payment_method' => 'paypal'
                ],
                'transactions' => [
                    [
                        'amount' => [
                            'total' => $amount,
                            'currency' => $currency
                        ],
                        'description' => 'Appointment Booking - ' . $appointment_data['service_name'],
                        'invoice_number' => $transaction_ref,
                        'item_list' => [
                            'items' => [
                                [
                                    'name' => $appointment_data['service_name'],
                                    'description' => 'Appointment booking service',
                                    'quantity' => '1',
                                    'price' => $amount,
                                    'currency' => $currency
                                ]
                            ]
                        ]
                    ]
                ],
                'redirect_urls' => [
                    'return_url' => $this->get_return_url($appointment_data['appointment_id']),
                    'cancel_url' => $this->get_cancel_url($appointment_data['appointment_id'])
                ]
            ];

            // Create payment
            $response = $this->make_paypal_rest_call('/v1/payments/payment', 'POST', $payment_data, $access_token);

            if ($response && isset($response['id']) && $response['state'] === 'created') {
                // Find approval URL
                $approval_url = null;
                foreach ($response['links'] as $link) {
                    if ($link['rel'] === 'approval_url') {
                        $approval_url = $link['href'];
                        break;
                    }
                }

                if ($approval_url) {
                    $this->log("PayPal payment created for appointment {$appointment_data['appointment_id']}");

                    return $this->create_payment_response(
                        'pending',
                        'Redirecting to PayPal for payment',
                        [
                            'redirect_url' => $approval_url,
                            'payment_id' => $response['id'],
                            'transaction_ref' => $transaction_ref
                        ]
                    );
                } else {
                    return $this->create_payment_response(
                        'failed',
                        'Failed to get PayPal approval URL'
                    );
                }
            } else {
                $error_message = isset($response['message']) ? $response['message'] : 'PayPal payment creation failed';
                $this->log("PayPal payment creation error: " . $error_message, 'error');

                return $this->create_payment_response(
                    'failed',
                    'Payment initialization failed: ' . $error_message
                );
            }

        } catch (Exception $e) {
            $this->log("PayPal payment processing error: " . $e->getMessage(), 'error');
            return $this->create_payment_response(
                'failed',
                'Payment processing error occurred'
            );
        }
    }

    /**
     * Handle PayPal payment callback using REST API
     * @param array $callback_data Callback data from PayPal
     * @return array Response data
     */
    public function handle_payment_callback($callback_data)
    {
        try {
            if (!isset($callback_data['paymentId']) || !isset($callback_data['PayerID'])) {
                return $this->create_payment_response(
                    'failed',
                    'Invalid PayPal callback data'
                );
            }

            // Get access token
            $access_token = $this->get_access_token();
            if (!$access_token) {
                return $this->create_payment_response(
                    'failed',
                    'Failed to authenticate with PayPal'
                );
            }

            // Execute the payment
            $execute_data = [
                'payer_id' => $callback_data['PayerID']
            ];

            $response = $this->make_paypal_rest_call(
                '/v1/payments/payment/' . $callback_data['paymentId'] . '/execute',
                'POST',
                $execute_data,
                $access_token
            );

            if ($response && $response['state'] === 'approved') {
                $transaction = $response['transactions'][0];
                $related_resources = $transaction['related_resources'][0];
                $sale = $related_resources['sale'];

                if ($sale['state'] === 'completed') {
                    $this->log("PayPal payment completed: " . $sale['id']);

                    return $this->create_payment_response(
                        'success',
                        'Payment completed successfully',
                        [
                            'transaction_id' => $sale['id'],
                            'amount' => $sale['amount']['total'],
                            'currency' => $sale['amount']['currency'],
                            'payer_email' => $response['payer']['payer_info']['email']
                        ]
                    );
                } else {
                    return $this->create_payment_response(
                        'failed',
                        'Payment not completed. Status: ' . $sale['state']
                    );
                }
            } else {
                $error_message = isset($response['message']) ? $response['message'] : 'Payment execution failed';
                return $this->create_payment_response(
                    'failed',
                    $error_message
                );
            }

        } catch (Exception $e) {
            $this->log("PayPal callback error: " . $e->getMessage(), 'error');
            return $this->create_payment_response(
                'failed',
                'Payment callback processing error'
            );
        }
    }

    /**
     * Get PayPal access token using Client ID and Client Secret
     * @return string|false Access token or false on failure
     */
    private function get_access_token()
    {
        if ($this->access_token) {
            return $this->access_token;
        }

        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept-Language' => 'en_US',
                'Authorization' => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret)
            ],
            'body' => ['grant_type' => 'client_credentials'],
            'sslverify' => !$this->test_mode
        ];

        $response = wp_remote_post($this->api_endpoint . '/v1/oauth2/token', $args);

        if (is_wp_error($response)) {
            $this->log("WordPress HTTP error getting access token: " . $response->get_error_message(), 'error');
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($http_code !== 200) {
            $this->log("HTTP error getting access token: " . $http_code, 'error');
            $this->log('Response body: ' . json_encode($response_body), 'error');
            return false;
        }

        $response_data = json_decode($response_body, true);

        if (isset($response_data['access_token'])) {
            $this->access_token = $response_data['access_token'];
            return $this->access_token;
        }

        return false;
    }

    /**
     * Make REST API call to PayPal using WordPress HTTP functions
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @param string $access_token Access token
     * @return array|false Response data
     */
    private function make_paypal_rest_call($endpoint, $method = 'GET', $data = null, $access_token = null)
    {
        $args = [
            'method' => strtoupper($method),
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token
            ],
            'sslverify' => !$this->test_mode
        ];

        // Add body data for POST/PUT requests
        if (in_array(strtoupper($method), ['POST', 'PUT']) && $data) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($this->api_endpoint . $endpoint, $args);

        if (is_wp_error($response)) {
            $this->log("WordPress HTTP error: " . $response->get_error_message(), 'error');
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if ($http_code >= 200 && $http_code < 300) {
            return $response_data;
        } else {
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown API error';
            $this->log("PayPal API error (HTTP $http_code): " . $error_message, 'error');
            return $response_data; // Return the error response for handling
        }
    }

    /**
     * Validate payment data
     * @param array $payment_data Payment form data
     * @return bool|\WP_Error
     */
    public function validate_payment_data($payment_data)
    {
        $errors = array();

        if (empty($payment_data['amount']) || $payment_data['amount'] <= 0) {
            $errors[] = 'Invalid payment amount';
        }

        if (empty($payment_data['appointment_id'])) {
            $errors[] = 'Appointment ID is required';
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }

        return true;
    }


    /**
     * Validate appointment data
     * @param array $appointment_data Appointment data
     * @return bool
     */
    private function validate_appointment_data($appointment_data)
    {
        $required_fields = ['appointment_id', 'amount', 'service_name'];

        foreach ($required_fields as $field) {
            if (empty($appointment_data[$field])) {
                return false;
            }
        }

        return true;
    }

    public function get_settings()
    {
        $settings = $this->settings;

        // Flatten 'currency' object to scalar ID for frontend
        if (isset($settings['currency']) && is_array($settings['currency'])) {
            $settings['currency'] = $settings['currency']['id'] ?? 'USD';
        } elseif (!isset($settings['currency'])) {
            $settings['currency'] = 'USD';
        }
        // If it's already a string, keep it as is

        if (isset($settings['mode'])) {
            if (is_array($settings['mode'])) {
                // Extract the ID from array structure
                $settings['mode'] = (string) ($settings['mode']['id'] ?? 0);
            } elseif (!is_string($settings['mode'])) {
                // Convert any non-string value to string
                $settings['mode'] = (string) $settings['mode'];
            }
            // Else it's already a string, keep it as is
        } else {
            $settings['mode'] = '0'; // Default to sandbox mode
        }

        $settings['enablePaypal'] = (bool) ($settings['enablePaypal'] ?? false);

        return $settings;
    }

    public function update_settings($settings)
    {
        $processed_settings = $this->settings;

        foreach ($this->settings_fields as $field) {
            $key = $field['name'];

            if (isset($settings[$key])) {
                $value = $settings[$key];

                if (is_array($value) && isset($value['value'])) {
                    $value = $value['value'];
                }

                switch ($field['type']) {
                    case 'input':
                        $sanitized = sanitize_text_field($value);
                        break;

                    case 'select':
                        $selected_label = '';
                        $clean_value = $value;

                        if (isset($field['options']) && is_array($field['options'])) {
                            foreach ($field['options'] as $opt) {
                                if ($opt['value'] == $value) {
                                    $selected_label = $opt['label'];
                                    $clean_value = $opt['value'];
                                    break;
                                }
                            }
                        }

                        $id_val = is_numeric($clean_value) ? $clean_value : $clean_value;

                        $sanitized = [
                            'id' => $id_val,
                            'label' => $selected_label
                        ];
                        break;

                    case 'checkbox':
                        $sanitized = ($value === 'on' || $value === true || $value === 1 || $value === '1');
                        break;

                    default:
                        $sanitized = sanitize_text_field($value);
                }

                $processed_settings[$key] = $sanitized;
            }
        }

        // Stop save if enabling without credentials
        $is_enabling = isset($processed_settings['enablePaypal']) && $processed_settings['enablePaypal'] === true;

        if ($is_enabling) {
            $this->log('Validating PayPal credentials...', 'info');

            $required = [
                'client_id' => __('Client ID', 'kivicare-clinic-management-system'),
                'client_secret' => __('Client Secret', 'kivicare-clinic-management-system'),
                'mode' => __('Mode', 'kivicare-clinic-management-system'),
                'currency' => __('Currency', 'kivicare-clinic-management-system')
            ];

            foreach ($required as $field_key => $field_label) {
                $val = $processed_settings[$field_key] ?? null;

                // Handle Object structure for selects (currency/mode)
                if (is_array($val) && isset($val['id'])) {
                    $val = $val['id'];
                }

                // Check for empty (allow '0' for Sandbox mode)
                if (empty($val) && $val !== '0' && $val !== 0) {
                    $error_msg = sprintf(
                        /* translators: %s: field label */
                        __('%s is required to enable PayPal.', 'kivicare-clinic-management-system'),
                        $field_label
                    );
                    $this->log($error_msg, 'error');
                    throw new \Exception(esc_html($error_msg));
                }
            }
        }

        $this->settings = $processed_settings;
        $this->save_settings();

        return $this->get_settings();
    }

}