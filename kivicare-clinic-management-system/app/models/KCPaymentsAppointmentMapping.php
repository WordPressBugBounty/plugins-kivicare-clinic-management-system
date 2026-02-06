<?php

namespace App\models;

use App\baseClasses\KCBaseModel;
use App\baseClasses\KCBase;
use App\models\KCClinic;
use App\models\KCReceptionistClinicMapping;
use App\models\KCAppointment;



defined('ABSPATH') or die('Something went wrong');

class KCPaymentsAppointmentMapping extends KCBaseModel
{
    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_payments_appointment_mappings',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'int',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'paymentMode' => [
                    'column' => 'payment_mode',
                    'type' => 'varchar',
                    'nullable' => true,
                    'default' => '',
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'paymentId' => [
                    'column' => 'payment_id',
                    'type' => 'varchar',
                    'nullable' => true,
                    'default' => '',
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'payerId' => [
                    'column' => 'payer_id',
                    'type' => 'varchar',
                    'nullable' => true,
                    'default' => '',
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'payerEmail' => [
                    'column' => 'payer_email',
                    'type' => 'varchar',
                    'nullable' => true,
                    'default' => '',
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'appointmentId' => [
                    'column' => 'appointment_id',
                    'type' => 'bigint',
                    'nullable' => true,
                    'sanitizers' => ['intval'],
                ],
                'amount' => [
                    'column' => 'amount',
                    'type' => 'bigint',
                    'nullable' => true,
                    'sanitizers' => ['intval'],
                ],
                'currency' => [
                    'column' => 'currency',
                    'type' => 'varchar',
                    'nullable' => true,
                    'default' => '',
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'paymentStatus' => [
                    'column' => 'payment_status',
                    'type' => 'varchar',
                    'nullable' => true,
                    'default' => '',
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'requestPageUrl' => [
                    'column' => 'request_page_url',
                    'type' => 'varchar',
                    'nullable' => true,
                    'default' => '',
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'extra' => [
                    'column' => 'extra',
                    'type' => 'longtext',
                    'nullable' => true,
                ],
                'notificationStatus' => [
                    'column' => 'notification_status',
                    'type' => 'bigint',
                    'nullable' => true,
                    'default' => 0,
                    'sanitizers' => ['intval'],
                ],
                'createdAt' => [
                    'column' => 'created_at',
                    'type' => 'datetime',
                    'nullable' => true,
                ],
                'updatedAt' => [
                    'column' => 'updated_at',
                    'type' => 'datetime',
                    'nullable' => true,
                ],
            ],
            'timestamps' => false, // We'll handle created_at and updated_at manually
            'soft_deletes' => false,
        ];
    }
    /**
     * Get the appointment this payment is for
     */
    public function getAppointment()
    {
        return KCAppointment::find($this->appointmentId);
    }

    /**
     * Get metadata as array
     */
    public function getMetadata(): array
    {
        return json_decode($this->metadata, true) ?? [];
    }

    /**
     * Get payment mode for an appointment
     * 
     * @param int $appointment_id Appointment ID
     * @return string Payment mode display text
     */
    public static function getPaymentModeByAppointmentId(int $appointment_id): string
    {

        // Check if appointment is linked to a WooCommerce order
        $order_id = kcGetWoocommerceOrderIdByAppointmentId($appointment_id);

        if (!empty($order_id) && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);

            if (!empty($order)) {
                return 'Woocommerce - ' . ucfirst($order->get_payment_method());
            }

            return 'Woocommerce';
        }

        // Query our own payment mappings table
        $payment = self::query()
            ->select(['payment_mode'])
            ->where('appointment_id', $appointment_id)
            ->first();


        // Map stored values to user-friendly labels
        $gateway_map = [
            'paypal' => __('PayPal', 'kivicare-clinic-management-system'),
            'razorpay'    => __('Razorpay', 'kivicare-clinic-management-system'),
            'stripe'      => __('Stripe', 'kivicare-clinic-management-system'),
            'manual'      => __('Pay Later', 'kivicare-clinic-management-system'),
        ];

        if (!empty($payment->paymentMode) && isset($gateway_map[$payment->paymentMode])) {
            return $gateway_map[$payment->paymentMode];
        }

        // Default fallback
        return __('Manual', 'kivicare-clinic-management-system');
    }
    /**
     * Get payment status for an appointment
     * 
     * @param int $appointment_id Appointment ID
     * @return string Payment status (e.g., 'succeeded', 'pending', 'captured', 'completed')
     */
    // Add this method to your KCPaymentsAppointmentMapping class
    public static function getPaymentStatusByAppointmentId(int $appointment_id): string
    {
        // First check if it's a WooCommerce order
        $order_id = kcGetWoocommerceOrderIdByAppointmentId($appointment_id);

        if (!empty($order_id) && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            if (!empty($order)) {
                return $order->get_status();
            }
        }

        // Check in our payment mappings table
        $payment = self::query()
            ->select(['payment_status'])
            ->where('appointment_id', $appointment_id)
            ->first();

        if (!empty($payment->paymentStatus)) {
            // Map payment status to user-friendly labels
            $status_map = [
                'completed' => __('Paid', 'kivicare-clinic-management-system'),
                'pending' => __('Pending', 'kivicare-clinic-management-system'),
                'failed' => __('Failed', 'kivicare-clinic-management-system'),
                'refunded' => __('Refunded', 'kivicare-clinic-management-system'),
                'cancelled' => __('Cancelled', 'kivicare-clinic-management-system'),
                'processing' => __('Processing', 'kivicare-clinic-management-system'),
                'on-hold' => __('On Hold', 'kivicare-clinic-management-system'),
            ];

            return $status_map[$payment->paymentStatus] ?? ucfirst($payment->paymentStatus);
        }

        return __('Pending', 'kivicare-clinic-management-system');
    }
}
