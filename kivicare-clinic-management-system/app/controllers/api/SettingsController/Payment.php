<?php

namespace App\controllers\api\SettingsController;

use App\baseClasses\KCPaymentGatewayFactory;
use App\controllers\api\SettingsController;
use App\models\KCOption;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class Payment
 * 
 * @package App\controllers\api\SettingsController
 */
class Payment extends SettingsController
{
    private static $instance = null;

    protected $route_payment_singular = 'settings/payment-gateway';
    protected $route_payment_plural = 'settings/payments-gateway';


    public function __construct()
    {
        parent::__construct();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        $this->registerRoute('/' . $this->route_payment_plural, [
            'methods' => 'GET',
            'callback' => [$this, 'getPaymentGateway'],
            'permission_callback' => function (): bool|WP_Error {
                // Check user permissions
                if ($this->kcbase->getLoginUserRole() !== 'administrator') {
                    return new WP_Error('rest_forbidden', esc_html__('You do not have permission to access this resource.', 'kivicare-clinic-management-system'), ['status' => 403]);
                }
                return true;
            }
        ]);

        $this->registerRoute('/' . $this->route_payment_singular . '/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getPaymentGateway'],
            'permission_callback' => function (): bool {
                return current_user_can('manage_options');
            },
        ]);

        // Update Payment Gateway
        $this->registerRoute('/' . $this->route_payment_singular . '/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => ['PUT'],
            'callback' => [$this, 'updatePaymentGateway'],
            'permission_callback' => function (): bool {
                return current_user_can('manage_options');
            },
        ]);
    }
    /**
     * Get Payment settings
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getPaymentGateway(WP_REST_Request $request): WP_REST_Response
    {
        if (!is_null($request->get_param('id'))) {
            if ($paymentGateway = KCPaymentGatewayFactory::get_available_gateway($request->get_param('id'))) {
                $data = [
                    'fields' => $paymentGateway->get_fields(),
                    'settings' => $paymentGateway->get_settings(),
                ];
                return $this->response($data, __('Payment Gateway retrieved successfully', 'kivicare-clinic-management-system'), true);
            } else {
                return $this->response(null, __('Payment Gateway not found', 'kivicare-clinic-management-system'), false);
            }
        }

        $gateways = KCPaymentGatewayFactory::get_available_gateways();
        $gateways = collect($gateways)->map(function ($gateway) {
            $gateway['status'] = $gateway['instance']->is_enabled();
            unset($gateway['instance']);
            return $gateway;
        })->values()->toArray();
        return $this->response($gateways, __('Payment Gateways retrieved successfully', 'kivicare-clinic-management-system'), true);

    }

    public function updatePaymentGateway(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $data = $request->get_params();
        if (empty($data)) {
            return new WP_Error('invalid_data', __('Invalid data provided', 'kivicare-clinic-management-system'), ['status' => 400]);
        }

        if (!isset($data['id']) || empty($data['id'])) {
            return new WP_Error('missing_id', __('Payment Gateway ID is required', 'kivicare-clinic-management-system'), ['status' => 400]);
        }

        $gateway = KCPaymentGatewayFactory::get_available_gateway($data['id']);
        if (!$gateway) {
            return new WP_Error('gateway_not_found', __('Payment Gateway not found', 'kivicare-clinic-management-system'), ['status' => 404]);
        }

        try {
            $gateway->update_settings($data);
            return $this->response(null, __('Payment Gateway updated successfully', 'kivicare-clinic-management-system'), true);
        } catch (\Exception $e) {
            return new WP_Error('rest_update_failed', $e->getMessage(), ['status' => 400]);
        }
    }
}
