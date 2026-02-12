<?php
namespace App\paymentGateways;

use App\abstracts\KCAbstractPaymentGateway;
use App\models\KCAppointment;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Pay Later (Manual) Payment Gateway Implementation
 * This gateway simulates a "pay later" option where no immediate payment is processed.
 * It marks the payment as pending/manual and allows the appointment to proceed.
 */
class KCPayLater extends KCAbstractPaymentGateway {

    /**
     * Initialize Pay Later gateway
     */
    protected function init() {
        $this->gateway_id = 'manual'; // Matches the factory key
        $this->gateway_name = __('Pay Later', 'kivicare-clinic-management-system');
        $this->gateway_description = __('Allow patients to book now and pay later (manual payment)', 'kivicare-clinic-management-system');
        $this->gateway_logo = KIVI_CARE_DIR_URI . 'assets/images/paymentOffline.png';
        // Enable based on settings (default to 'on' if not set)
        $this->is_enabled = isset($this->settings) && $this->settings === 'on';

        // Settings fields for admin configuration
        $this->settings_fields = [
            [
                'name' => 'enablePayLater',
                'type' => 'checkbox',
                'label' => __('Enable Pay Later', 'kivicare-clinic-management-system'),
                'default' => 'on',
                'description' => __('Enable or disable the Pay Later (manual) payment option.', 'kivicare-clinic-management-system'),
            ]
        ];
    }

    /**
     * Initialize hooks if needed (e.g., for custom actions)
     */
    public function init_hook() {
        // No specific hooks needed for manual/pay later, but can add if required
        // For example: add_action('some_action', [$this, 'some_method']);
    }

    /**
     * Process Pay Later "payment" (manual mode)
     * @param array $appointment_data Appointment booking data
     * @return array Payment response
     */
    public function process_payment($appointment_data) { 
        try {
            // No actual payment processing - just mark as pending/manual
            $appointment_id = $appointment_data['appointment_id'] ?? 0;
            
            if (empty($appointment_id)) {
                return $this->create_payment_response(
                    'failed',
                    'Missing appointment ID for Pay Later processing'
                );
            }

            // Optionally update appointment status to booked/pending payment
            $appointment = KCAppointment::find($appointment_id);
            if ($appointment) {
                $appointment->update(['status' => 1]); // Assuming 1 is booked/pending
                do_action('kc_appointment_status_update', $appointment_id, 1, $appointment);
            }

            // Log the manual payment selection
            $this->log("Pay Later (manual) selected for appointment: $appointment_id");

            // Return success response with no redirect and no payment data (to skip creating payment record)
            return $this->create_payment_response(
                'pending', // Or 'success' if you consider booking complete
                'Appointment booked successfully. Payment due later (manual).'
            );
            
        } catch (Exception $e) {
            $this->log("Pay Later processing error: " . $e->getMessage(), 'error');
            return $this->create_payment_response(
                'failed',
                'Error processing Pay Later option'
            );
        }
    }

    /**
     * Validate payment data (minimal for manual)
     * @param array $payment_data Payment form data
     * @return bool|WP_Error
     */
    public function validate_payment_data($payment_data) {
        // Minimal validation for pay later - just check required fields
        if (empty($payment_data['appointment_id'])) {
            return new WP_Error('missing_data', 'Appointment ID is required');
        }
        return true;
    }

    /**
     * Handle payment callback (not needed for manual, but implemented for interface)
     * @param array $callback_data Callback data
     * @return array Response data
     */
    public function handle_payment_callback($callback_data) {
        // For pay later/manual, no callback is expected - return pending
        $appointment_id = $callback_data['appointment_id'] ?? 0;
        return $this->create_payment_response(
            'pending',
            'Pay Later option selected - awaiting manual payment',
            ['appointment_id' => $appointment_id]
        );
    }

    /**
     * Get settings (override if needed)
     */
    public function get_settings() {
        $value = $this->settings ?? 'off';
        $this->settings = [];
        $this->settings['enablePayLater'] = ($value === 'on');
        return $this->settings;
    }

    /**
     * Update settings
     * @param array $settings New settings
     * @return array Updated settings
     */
    public function update_settings($settings)
    {
        $value = $settings['enablePayLater'] ?? 'off';
        $status = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'on' : 'off';

        $this->settings = $status;

        update_option($this->settings_keys, $this->settings);

        return $this->get_settings();
    }
}