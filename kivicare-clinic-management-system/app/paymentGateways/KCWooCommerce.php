<?php
namespace App\paymentGateways;

use App\abstracts\KCAbstractPaymentGateway;
use App\baseClasses\KCErrorLogger;
use App\models\KCPatient;
use App\models\KCAppointment;
use App\models\KCServiceDoctorMapping;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * WooCommerce Payment Gateway Implementation
 */
class KCWooCommerce extends KCAbstractPaymentGateway {

    /**
     * Initialize WooCommerce gateway
     */
    protected function init() {
        $this->gateway_id = 'woocommerce';
        $this->gateway_name = 'WooCommerce';
        $this->gateway_description = 'Process payment through WooCommerce';
        $this->gateway_logo = KIVI_CARE_DIR_URI . 'assets/images/woo-logo.png';

        
        $this->is_enabled = isset($this->settings) && $this->settings === 'on';

        $this->settings_fields = [
            [
                'name' => 'enableWooCommerce',
                'type' => 'checkbox',
                'label' => __('Enable WooCommerce', 'kivicare-clinic-management-system'),
                'default' => 'off',
                'description' => __('Enable or disable WooCommerce payment gateway.', 'kivicare-clinic-management-system'),
            ] 
        ];
    }

    public function init_hook() {
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'get_cart_items_from_session'], 1, 3);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'add_appointment_meta_to_order'], 10, 2);
        add_action('before_delete_post', [$this, 'handle_product_delete']);
        add_action('woocommerce_new_order', [$this, 'handle_new_order_created'], 10, 1);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_cart_item_meta_to_order'], 10, 4);
        add_action('woocommerce_order_status_changed', [$this, 'handle_order_status_change'], 10, 4);
        add_action('woocommerce_payment_complete', [$this,'handle_payment_complete']);
        add_action('woocommerce_order_status_failed', [$this, 'handle_payment_failure'],10, 2);
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_kivicare_taxes_to_cart']);

        // Register the action for Action Scheduler to auto-cancel appointment
        if (function_exists('add_action')) {
            add_action('kivicare_wc_auto_cancel_appointment', function($args) {
                // Support both array and scalar for $args
                if (is_array($args) && isset($args['appointment_id'])) {
                    $appointment_id = (int)$args['appointment_id'];
                } else if (is_scalar($args)) {
                    $appointment_id = (int)$args;
                } else {
                    $appointment_id = 0;
                }
                if (!$appointment_id) return;

                // Check for offline payment in WooCommerce order
                if (class_exists('WooCommerce')) {
                    $order_id = kcGetWoocommerceOrderIdByAppointmentId($appointment_id);
                    $orders = wc_get_order($order_id);

                    if (!empty($orders)) {
                        $order = reset($orders);
                        // If payment method is offline (COD, BACS, Cheque), do not auto-cancel
                        if (in_array($order->get_payment_method(), ['cod', 'bacs', 'cheque'])) {
                            // Remove scheduled actions for this appointment
                            as_unschedule_all_actions('kivicare_wc_auto_cancel_appointment', ['appointment_id' => $appointment_id]);
                            return;
                        }
                    }
                }
                $appointment = KCAppointment::find($appointment_id);
                if ($appointment && ($appointment->status != KCAppointment::STATUS_BOOKED && $appointment->status != KCAppointment::STATUS_CHECK_IN && $appointment->status != KCAppointment::STATUS_CHECK_OUT)) {
                    // Only cancel if not already booked/checked-in/checked-out
                    $appointment->update(['status' => KCAppointment::STATUS_CANCELLED]);
                    do_action('kc_appointment_status_update', $appointment_id, KCAppointment::STATUS_CANCELLED, $appointment);
                }
            }, 10, 1);
        }
    }
    
    /**
     * Process WooCommerce payment
     * @param array $appointment_data Appointment booking data
     * @return array Payment response
     */
    public function process_payment($appointment_data) {
        try {
            // Validate appointment data to prevent replacement issues
            if (empty($appointment_data['appointment_id']) || !is_numeric($appointment_data['appointment_id'])) {
                return $this->create_payment_response(
                    'failed',
                    'Invalid appointment ID'
                );
            }

            if (!class_exists('WooCommerce')) {
                return $this->create_payment_response(
                    'failed',
                    'WooCommerce is not active'
                );
            }
            
            // Create WooCommerce product for appointment
            $product_ids = $this->create_appointment_product($appointment_data);
            
            if (empty($product_ids)) {
                return $this->create_payment_response(
                    'failed',
                    'Failed to create appointment product'
                );
            }
            
            // Check if this is an API request
            if (!empty($appointment_data['is_app'])) {
                // Create Direct Order for API
                $checkout_url = $this->create_wc_direct_order($product_ids, $appointment_data);
            } else {
                // Use Cart Session for Web
                $checkout_url = $this->create_woocommerce_order($product_ids, $appointment_data);
            }

            // Schedule auto-cancel if payment not completed in 5 minutes
            if (function_exists('as_schedule_single_action')) {
                $appointment_id = $appointment_data['appointment_id'];
                // Remove any previous scheduled actions for this appointment
                as_unschedule_all_actions('kivicare_wc_auto_cancel_appointment', ['appointment_id' => $appointment_id]);
                // Schedule new action 5 minutes from now
                $schedule_time = apply_filters('kivicare_wc_auto_cancel_schedule_time', time() + 300, $appointment_id, $appointment_data);
                as_schedule_single_action($schedule_time, 'kivicare_wc_auto_cancel_appointment', ['appointment_id' => $appointment_id], 'kivicare');
            }

            return $this->create_payment_response(
                'pending',
                'Redirecting to WooCommerce checkout',
                array(
                    'redirect_url' => $checkout_url,
                    'appointment_id' => $appointment_data['appointment_id']
                )
            );
           
            
        } catch (\Throwable $e) {
            $this->log("WooCommerce payment error: " . $e->getMessage(), 'error');
            return $this->create_payment_response(
                'failed',
                'Payment processing error occurred'
            );
        }
    }
    
    /**
     * Validate payment data
     * @param array $payment_data Payment form data
     * @return bool|WP_Error
     */
    public function validate_payment_data($payment_data) {
        if (!class_exists('WooCommerce')) {
            return new \WP_Error('woocommerce_missing', 'WooCommerce is required');
        }
        
        return true;
    }
    
    /**
     * Handle WooCommerce payment callback
     * @param array $callback_data Callback data
     * @return array Response data
     */
    public function handle_payment_callback($callback_data) {
        try {
            // Validate required data
            if (!isset($callback_data['appointment_id']) || empty($callback_data['appointment_id'])) {
                return $this->create_payment_response(
                    'failed',
                    'Missing appointment ID in callback data'
                );
            }

            $appointment_id = (int) $callback_data['appointment_id'];
            $order_id = (int) $callback_data['order_id'];

            $order = wc_get_order($order_id);

            if (empty($order)) {
                return $this->create_payment_response(
                    'failed',
                    'No order found for appointment ID: ' . $appointment_id
                );
            }

            // Check payment status
            if ($order->is_paid()) {
                $this->log("WooCommerce payment completed for appointment: $appointment_id");

                // Get payment details
                $transaction_id = $order->get_transaction_id();
                $amount = $order->get_total();
                $currency = $order->get_currency();
                $payer_email = $order->get_billing_email();

                return $this->create_payment_response(
                    'success',
                    'Payment completed successfully',
                    [
                        'appointment_id' => $appointment_id,
                        'order_id' => $order_id,
                        'transaction_id' => $transaction_id,
                        'amount' => $amount,
                        'currency' => $currency,
                        'payer_email' => $payer_email
                    ]
                );
            } else {
                $status = $order->get_status();
                return $this->create_payment_response(
                    'pending',
                    'Payment not completed. Order status: ' . $status,
                    [
                        'appointment_id' => $appointment_id,
                        'order_id' => $order_id,
                        'order_status' => $status
                    ]
                );
            }

        } catch (\Throwable $e) {
            $this->log("WooCommerce callback error: " . $e->getMessage(), 'error');
            return $this->create_payment_response(
                'failed',
                'Payment callback processing error'
            );
        }
    }
    
    /**
     * Create appointment product in WooCommerce
     * @param array $appointment_data Appointment data
     * @return array|false Product ID
     */
    private function create_appointment_product($appointment_data) {

        $product_ids = [];
        foreach ($appointment_data['services'] as $service) {
            // Create new product for each appointment to avoid configuration conflicts
            $product = new \WC_Product_Simple();
            
            // Basic product setup
            $product->set_name($service['title']);
            $product->set_status('publish');
            // Use individual service price, not total subtotal
            $service_price = $service['price'] ?? $appointment_data['subtotal'];
            $product->set_price($service_price);
            $product->set_regular_price($service_price);
            $product->set_virtual(true);
            $product->set_sold_individually(true);
            
            // Catalog visibility
            $product->set_catalog_visibility('hidden');
            
            // Stock management
            $product->set_stock_status('instock');
            $product->set_manage_stock(false);
            $product->set_backorders('no');
            
            // Product characteristics - services are not downloadable
            $product->set_downloadable(false);
            $product->set_featured(false);
            $product->set_purchase_note('');
            
            // Shipping properties
            $product->set_weight('');
            $product->set_length('');
            $product->set_width('');
            $product->set_height('');
            
            // SKU and attributes
            $product->set_sku('');
            $product->set_attributes([]);
            
            // Custom meta data
            $product->update_meta_data('kivicare_service_id', $service['id']);
            $product->update_meta_data('kivicare_doctor_id', $appointment_data['doctor_id']);
            $product->update_meta_data('kivicare_clinic_id', $appointment_data['clinic_id']);
            $product->update_meta_data('_thumbnail_id', $appointment_data['thumbnail_id'] ?? 0);
            
            $product_ids[] = $product->save();
        }
        return $product_ids; 
    }

    /**
     * Find existing WooCommerce product for service/doctor/clinic combination
     * @param int $service_id Service ID
     * @param int $doctor_id Doctor ID
     * @param int $clinic_id Clinic ID
     * @return int|null Product ID if found, null otherwise
     */
    private function find_existing_product($service_id, $doctor_id, $clinic_id) {
        // Use WooCommerce's built-in product query function
        $products = wc_get_products([
            'status' => 'publish',
            'limit' => 1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'kivicare_service_id',
                    'value' => $service_id,
                    'compare' => '='
                ],
                [
                    'key' => 'kivicare_doctor_id',
                    'value' => $doctor_id,
                    'compare' => '='
                ],
                [
                    'key' => 'kivicare_clinic_id',
                    'value' => $clinic_id,
                    'compare' => '='
                ]
            ]
        ]);

        return !empty($products) ? $products[0]->get_id() : null;
    }
    
    /**
     * Create WooCommerce order
     * @param int $product_id Product ID
     * @param array $appointment_data Appointment data
     * @return int|false Order ID
     */
    private function create_woocommerce_order($product_ids, $appointment_data) {

            // Initialize WooCommerce cart if not already loaded
            if (is_null(WC()->cart)) {
                wc_load_cart();
            }

            // Clear kivicare session data to prevent data mixing between appointments
            $kivicare_keys = [
                'kivicare_appointment_data',
                'kivicare_applied_taxes',
                'kivicare_appointment_id',
                'doctor_id',
                'widgetType'
            ];

            foreach ($kivicare_keys as $key) {
                WC()->session->set($key, null);
            }

            // Also clear any WooCommerce cart data that might persist
            if (!WC()->cart->is_empty()) {
                WC()->cart->empty_cart();
            }

            $temp = [
                'kivicare_appointment_id' => $appointment_data['appointment_id'],
                'doctor_id' => $appointment_data['doctor_id']
            ];

            if (!empty($appointment_data['widgetType'])) {
                $temp['widgetType'] = $appointment_data['widgetType'];
            }
            
            WC()->cart->empty_cart();
            
            foreach ($product_ids as $product_id) {
                WC()->cart->add_to_cart($product_id, 1, '', '', $temp);
            }

            // Store applied taxes in session to be added as fees during cart calculation
            if (!empty($appointment_data['applied_taxes']) && is_array($appointment_data['applied_taxes'])) {
                WC()->session->set('kivicare_applied_taxes', $appointment_data['applied_taxes']);
            }

            WC()->session->set('kivicare_appointment_data', $temp);

            return wc_get_checkout_url();
    }

    /**
     * Create Direct WooCommerce Order for API
     * @param array $product_ids Product IDs
     * @param array $appointment_data Appointment Data
     * @return string Payment URL
     */
    private function create_wc_direct_order($product_ids, $appointment_data) {
        
        $args = [];
        if (!empty($appointment_data['patient_id'])) {
            $args['customer_id'] = $appointment_data['patient_id'];
        }
        
        $order = wc_create_order($args);

        foreach ($product_ids as $product_id) {
            $order->add_product(wc_get_product($product_id), 1);
        }

        // Add Taxes as Fees
        if (!empty($appointment_data['applied_taxes']) && is_array($appointment_data['applied_taxes'])) {
            foreach ($appointment_data['applied_taxes'] as $tax) {
                if (isset($tax['tax_name']) && isset($tax['tax_amount'])) {
                    $tax_name = sanitize_text_field($tax['tax_name']);
                    $tax_amount = floatval($tax['tax_amount']);
                    
                    if ($tax_amount > 0) {
                        $item_fee = new \WC_Order_Item_Fee();
                        $item_fee->set_name($tax_name);
                        $item_fee->set_amount($tax_amount);
                        $item_fee->set_total($tax_amount);
                        $order->add_item($item_fee);
                    }
                }
            }
        }

        // Add Meta Data
        $order->update_meta_data('kivicare_appointment_id', $appointment_data['appointment_id']);
        $order->update_meta_data('kivicare_doctor_id', $appointment_data['doctor_id']);
        if (!empty($appointment_data['widgetType'])) {
            $order->update_meta_data('kivicare_widget_type', $appointment_data['widgetType']);
        }
        
        // Calculate Totals
        $order->calculate_totals();
        $order->save();

        return $order->get_checkout_payment_url();
    }

    public function get_cart_items_from_session($item, $values, $key) {

        // Define all custom keys we want to restore
        $custom_keys = [
            'kivicare_appointment_id',
            'doctor_id',
            'widgetType'
        ];
        
        foreach ($custom_keys as $key_name) {
            if (isset($values[$key_name])) {
                $item[$key_name] = $values[$key_name];
            }
        }

        return $item;
    }

     /**
     * Handle payment complete events
     * @param int $order_id WooCommerce order ID
     */
    public function handle_payment_complete($order_id) {

        $order = wc_get_order($order_id);
        $appointment_id = get_post_meta($order_id, 'kivicare_appointment_id', true);
        $success_url = $this->get_return_url($appointment_id) ?? '';

        if ($appointment_id) {
            if ($order->is_paid()) {
                $redirect_url = add_query_arg('order_id', $order_id, $success_url);
                // Remove scheduled auto-cancel if payment completed
                if (function_exists('as_unschedule_all_actions')) {
                    as_unschedule_all_actions('kivicare_wc_auto_cancel_appointment', ['appointment_id' => $appointment_id]);
                }
            } 
            if ( ! empty( $redirect_url ) ) {
                wp_safe_redirect( esc_url_raw( $redirect_url ) );
                exit;
            }
        }
    }

    public function handle_payment_failure($order_id, $error_message) {

        $appointment_id = get_post_meta($order_id, 'kivicare_appointment_id', true);

        if(!is_admin()){
            // Log the payment failure details for debugging
            KCErrorLogger::instance()->error("Payment failed for order {$order_id}: {$error_message}");

            if (empty($appointment_id)) {
                // Log missing appointment ID and redirect to safe fallback
                KCErrorLogger::instance()->error("No appointment ID found for failed order: {$order_id}");
                $url = wc_get_cart_url() ?: home_url();
                wp_safe_redirect( esc_url_raw( $url ) );
                exit;
            }

            // Get cancellation URL
            $cancel_url = $this->get_cancel_url($appointment_id);

            if (empty($cancel_url)) {
                // Handle missing cancel URL
                KCErrorLogger::instance()->error("Invalid cancel URL for appointment: {$appointment_id} (Order: {$order_id})");
                $url = wc_get_cart_url() ?: home_url();
                wp_safe_redirect( esc_url_raw( $url ) );
                exit;
            }

            // Construct redirect URL with order ID parameter
            $redirect_url = add_query_arg('order_id', $order_id, $cancel_url);

            // Validate the final URL before redirect
            if (wp_http_validate_url($redirect_url)) {
                wp_safe_redirect( esc_url_raw( $redirect_url ) );
            } else {
                // Fallback for invalid URL
                KCErrorLogger::instance()->error("Invalid redirect URL constructed: {$redirect_url}");
                $url = wc_get_cart_url() ?: home_url();
                wp_safe_redirect( esc_url_raw( $url ) );
            }
            exit;
        } 
    }

    /**
     * Add appointment meta to order
     * @param \WC_Order $order WooCommerce order
     */
    public function add_appointment_meta_to_order($order, $data) {
        
        $appointment_data = WC()->session->get('kivicare_appointment_data');

        if ($appointment_data && is_array($appointment_data)) {
            // Extract values with null coalescing for safety
            $kivicare_appointment_id = $appointment_data['kivicare_appointment_id'] ?? '';
            $kivicare_doctor_id = $appointment_data['kivicare_doctor_id'] ?? '';
            $kivicare_widget_type = $appointment_data['kivicare_widget_type'] ?? '';

            // Update post meta directly
            update_post_meta((int) $order, 'kivicare_appointment_id', $kivicare_appointment_id);
            update_post_meta((int)$order, 'kivicare_doctor_id', $kivicare_doctor_id);
            
            if (!empty($kivicare_widget_type)) {
                update_post_meta((int)$order, 'kivicare_widget_type', $kivicare_widget_type);
            }

            // Clear ALL session data to prevent data persistence
            $kivicare_keys = [
                'kivicare_appointment_data',
                'kivicare_applied_taxes',
                'kivicare_appointment_id',
                'doctor_id',
                'widgetType'
            ];

            foreach ($kivicare_keys as $key) {
                WC()->session->set($key, null);
            }
        }
    }

    /**
     * Handle Kivicare service data when a WooCommerce product is deleted
     * 
     * @param int $post_id ID of the post being deleted
     */
    public function handle_product_delete($post_id) {
        // Only process products
        if ('product' !== get_post_type($post_id)) {
            return;
        }

        // Get service ID from product meta
        $service_doctor_mapping_id = get_post_meta($post_id, 'kivicare_service_id', true);
        
        if (empty($service_doctor_mapping_id)) {
            return;
        }

        try {

            KCServiceDoctorMapping::query()
                ->where('id', $service_doctor_mapping_id)
                ->first()
                ->update(['extra' => '']);
            
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Kivicare service cleanup error: ' . $e->getMessage());
        }
    }

    public function get_settings()
    {
        $value = $this->settings;
        $this->settings = [];
        $this->settings['enableWooCommerce'] = ($value ?? 'off')==='on';
        return $this->settings;
    }

    public function update_settings($settings)
    {
        $value = $settings['enableWooCommerce'] ?? 'off';
        $status = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'on' : 'off';

        $this->settings = $status;

        update_option($this->settings_keys, $this->settings);

        return $this->get_settings();
    }

    /**
     * Handle new order creation
     */
    public function handle_new_order_created($order_id) {
        $order = wc_get_order($order_id);

        foreach ($order->get_items() as $item_id => $item) {
           
            $appointment_id = $item->get_meta('kivicare_appointment_id');
            $doctor_id = $item->get_meta('doctor_id');
            $payment_method = $order->get_payment_method();

            if (!empty($appointment_id)) {
                update_post_meta($order_id, 'kivicare_appointment_id', $appointment_id);
            }
            
            if (!empty($doctor_id)) {
                update_post_meta($order_id, 'kivicare_doctor_id', $doctor_id);
            }
            
            if (!empty($payment_method)) {
                update_post_meta($order_id, '_payment_method_title', $payment_method);
            }
        }
    }

    /**
     * Add cart item meta to order
     */
    public function add_cart_item_meta_to_order($item, $cart_item_key, $values, $order) {

        $meta_fields = [
            'kivicare_appointment_id' => $values['kivicare_appointment_id'] ?? null,
            'doctor_id' => $values['doctor_id'] ?? null
        ];

        foreach ($meta_fields as $key => $value) {
            if (!empty($value)) {
                $item->add_meta_data($key, $value);
            }
        }
    }

    public function handle_order_status_change($order_id, $old_status, $new_status, $order) {

        // Validate order exists
        if (empty($order_id) || !get_post_status($order_id)) {
            $this->log("Invalid order ID: $order_id", 'warning');
            return;
        }

        // Get appointment ID from order meta
        $appointment_id = get_post_meta($order_id, 'kivicare_appointment_id', true);
        if (empty($appointment_id)) {
            $this->log("No appointment found for order #$order_id", 'debug');
            return;
        }

        if (!$appointment_id) return;

        $status = ['status' => 2];
        if ($new_status === 'completed') {
            $status = ['status' => 1];
        } elseif ($new_status === 'cancelled' || $new_status === 'failed') {
            $status = ['status' => 0];
        }

        $appointment = KCAppointment::find($appointment_id);
        if ($appointment) {
            $appointment->update($status);
        }

        do_action('kc_appointment_status_update', $appointment_id, $status['status'], $appointment);

    }

    /**
     * Add KiviCare appointment taxes to WooCommerce cart as fees
     * This method is called during cart calculation via woocommerce_cart_calculate_fees hook
     */
    public function add_kivicare_taxes_to_cart() {
        // Check if WooCommerce session is available
        if (!WC()->session) {
            return;
        }

        // Get stored tax data from session
        $applied_taxes = WC()->session->get('kivicare_applied_taxes');

        // If no taxes stored, return early
        if (empty($applied_taxes) || !is_array($applied_taxes)) {
            return;
        }

        // Add each tax as a fee to the cart
        foreach ($applied_taxes as $tax) {
            if (isset($tax['tax_name']) && isset($tax['tax_amount'])) {
                $tax_name = sanitize_text_field($tax['tax_name']);
                $tax_amount = floatval($tax['tax_amount']);
                
                // Only add fee if amount is greater than 0
                if ($tax_amount > 0) {
                    WC()->cart->add_fee($tax_name, $tax_amount, true);
                }
            }
        }
    }
}

