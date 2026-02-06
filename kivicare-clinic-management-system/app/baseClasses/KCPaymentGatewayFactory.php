<?php
namespace App\baseClasses;

use App\abstracts\KCAbstractPaymentGateway;

defined ('ABSPATH') || exit;

/**
 * Payment Gateway Factory
 * Factory class to create payment gateway instances
 */
class KCPaymentGatewayFactory
{

    private static $gateways = array();

    /**
     * Register available gateways
     */
    public static function init()
    {
        $gateways = [
            'paypal' => [
                'class'        => 'App\\paymentGateways\\KCPaypal',
                'settings_key' => KIVI_CARE_PREFIX . 'paypalConfig'
            ],
            'manual' => [
                'class' => 'App\\paymentGateways\\KCPayLater',
                'settings_key' => KIVI_CARE_PREFIX . 'local_payment_status'
            ],
            'knit_pay' => [
                'class'        => 'App\\paymentGateways\\KCKnitPay',
                'settings_key' => KIVI_CARE_PREFIX . 'knit_pay_config'
            ]
        ];

        self::$gateways = apply_filters('kc_payment_gateways', $gateways);

        self::get_available_gateways(false);
    }

    /**
     * Create payment gateway instance
     * @param string $gateway_id Gateway identifier
     * @return \App\abstracts\KCAbstractPaymentGateway|null Gateway instance
     */
    public static function create_gateway($gateway_id)
    {
        // Handle Dynamic Knit Pay IDs (knit_pay_123)
        if (strpos($gateway_id, 'knit_pay_') === 0 && isset(self::$gateways['knit_pay'])) {
            $config_id        = substr($gateway_id, 9);
            $gateway_config   = self::$gateways['knit_pay'];
            $gateway_class    = $gateway_config['class'];
            $gateway_settings = $gateway_config['settings_key'];

            if (class_exists($gateway_class)) {
                $instance = new $gateway_class($gateway_settings);
                if (method_exists($instance, 'set_config_id')) {
                    $instance->set_config_id($config_id);
                }
                return $instance;
            }
        }

        if (!isset(self::$gateways[$gateway_id])) {
            return null;
        }

        $gateway_config = self::$gateways[$gateway_id];
        $gateway_class = $gateway_config['class'];
        $gateway_settings = $gateway_config['settings_key'];

        if (class_exists($gateway_class)) {
            return new $gateway_class($gateway_settings);
        }
        return null;
    }

    /**
     * Get available gateways
     * @param bool $for_frontend Return simplified data for frontend if true
     * @return array List of available gateways
     */
    public static function get_available_gateways($for_frontend = false)
    {
        $available = array();

        // Ensure gateways are initialized
        if (empty(self::$gateways)) {
            self::init();
        }

        foreach (self::$gateways as $gateway_id => $gateway_config) {

            // Special handling for Knit Pay to expand configs on frontend
            if ($gateway_id === 'knit_pay') {
                $gateway = self::create_gateway($gateway_id);
                if ($gateway && (!$for_frontend || $gateway->is_enabled())) {

                    if (!$for_frontend) {
                        // Admin side: keep single main entry
                        $gateway->init_hook();
                        $gateway_data = array(
                            'id'          => $gateway->get_gateway_id(),
                            'name'        => $gateway->get_gateway_name(),
                            'logo'        => $gateway->get_gateway_logo(),
                            'description' => $gateway->get_gateway_description(),
                            'instance'    => $gateway
                        );
                        $available[$gateway_id] = $gateway_data;
                    } else {
                        // Frontend side: expand enabled configs
                        if (method_exists($gateway, 'get_enabled_configs')) {
                            $configs = $gateway->get_enabled_configs();
                            foreach ($configs as $config) {
                                $available[] = [
                                    'id'          => 'knit_pay_' . $config['id'],
                                    'name'        => $config['label'],
                                    'logo'        => $config['icon'],
                                    'description' => $config['description'] ,
                                ];
                            }
                        }
                    }
                }
                continue;
            }

            $gateway = self::create_gateway($gateway_id);

            if ($for_frontend && (!$gateway || !$gateway->is_enabled())) {
                continue;
            }

            $gateway_data = array(
                'id' => $gateway->get_gateway_id(),
                'name' => $gateway->get_gateway_name(),
                'logo' => $gateway->get_gateway_logo(),
                'description' => $gateway->get_gateway_description(),
            );

            $gateway->init_hook();

            if (!$for_frontend) {
                $gateway_data['instance'] = $gateway;
                $available[$gateway_id] = $gateway_data;
            } else {
                $available[] = $gateway_data;
            }
        }

        return $available;
    }

    /**
     * Get available gateway by gateway id
     * @param string $gateway_id Gateway identifier
     * @return \App\abstracts\KCAbstractPaymentGateway|null Gateway data or null if not found/enabled
     */
    public static function get_available_gateway($gateway_id): KCAbstractPaymentGateway|null
    {
        // Ensure gateways are initialized
        if (empty(self::$gateways)) {
            self::init();
        }

        // Allow dynamic Knit Pay IDs (e.g., knit_pay_65) to bypass the exact key check
        if (strpos($gateway_id, 'knit_pay_') === 0 && isset(self::$gateways['knit_pay'])) {
            return self::create_gateway($gateway_id);
        }

        if (!isset(self::$gateways[$gateway_id])) {
            return null;
        }

        return self::create_gateway($gateway_id);
    }
}