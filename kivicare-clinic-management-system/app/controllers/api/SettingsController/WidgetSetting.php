<?php

namespace App\controllers\api\SettingsController;

use App\controllers\api\SettingsController;
use App\models\KCOption;
use App\baseClasses\KCErrorLogger;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * WidgetSetting handles REST API settings for widget config
 */
class WidgetSetting extends SettingsController
{
    private static ?self $instance = null;
    protected $route = 'settings/widget-setting';


    /**
     * Instantiate the object
     */
    public function __construct()
    {
        parent::__construct();
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register REST API routes for widget settings
     */
    public function registerRoutes(): void
    {
        $this->registerRoute('/' . $this->route, [
            'methods'             => 'GET',
            'callback'            => [$this, 'getWidgetSetting'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        $this->registerRoute('/' . $this->route, [
            'methods'             => ['PUT', 'POST'],
            'callback'            => [$this, 'updateWidgetSetting'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
        ]);
    }

    /**
     * Get Widget Setting
     */
    public function getWidgetSetting(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $prefix = defined('KIVI_CARE_PREFIX') ? KIVI_CARE_PREFIX : 'kiviCare_';

            // Get widget order from the option set by KCActivate
            $widgetOrderFromDB = get_option($prefix . 'widget_order_list');
            
            // Default order (fallback if option doesn't exist)
            $defaultOrder = [
                ['name' => 'Choose a Clinic', 'fixed' => false, 'att_name' => 'clinic'],
                ['name' => 'Choose Your Doctor', 'fixed' => false, 'att_name' => 'doctor'],
                ['name' => 'Services from Category', 'fixed' => false, 'att_name' => 'category'],
                ['name' => 'Select Date and Time', 'fixed' => true, 'att_name' => 'date-time'],
                ['name' => 'User Detail Information', 'fixed' => true, 'att_name' => 'detail-info'],
                ['name' => 'Appointment Extra Data', 'fixed' => true, 'att_name' => 'file-uploads-custom'],
                ['name' => 'Confirmation', 'fixed' => true, 'att_name' => 'confirm'],
            ];

            // Use widget order from DB or default
            $widgetOrder = $widgetOrderFromDB ?: $defaultOrder;

            // Allow pro version to override order
            if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
                $proOrder = apply_filters('kcpro_get_widget_order_list', []);
                if (!empty($proOrder['data']) && is_array($proOrder['data'])) {
                    $widgetOrder = $proOrder['data'];
                }
            }

            // Retrieve settings from the option set by KCActivate
            $widgetSettingFromDB = get_option($prefix . 'widgetSetting');

            // Default settings matching KCActivate.php structure
            $defaultSettings = [
                'showClinicImage'          => true,
                'showClinicAddress'        => true,
                'clinicContactDetails'     => [
                    'id'    => 3,
                    'label' => 'Show email address'
                ],
                'showDoctorImage'          => true,
                'showDoctorExperience'     => true,
                'doctorContactDetails'     => [
                    'id'    => 3,
                    'label' => 'Show email address'
                ],
                'showDoctorSpeciality'     => true,
                'showDoctorDegree'         => true,
                'showDoctorRating'         => true,
                'showServiceImage'         => true,
                'showServicetype'          => true,
                'showServicePrice'         => true,
                'showServiceDuration'      => true,
                'primaryColor'             => '#7093e5',
                'primaryHoverColor'        => '#4367b9',
                'secondaryColor'           => '#f68685',
                'secondaryHoverColor'      => '#df504e',
                'widget_print'             => true,
                'afterWoocommerceRedirect' => true,
                'skip_service_when_single' => false,
                'widgetOrder'              => $widgetOrder,
            ];

            // Decode and merge settings from DB if exist
            if ($widgetSettingFromDB) {
                $decodedSettings = is_string($widgetSettingFromDB)
                    ? json_decode($widgetSettingFromDB, true)
                    : $widgetSettingFromDB;

                if ($decodedSettings && is_array($decodedSettings)) {
                    // Convert string boolean values to actual booleans
                    $booleanFields = [
                        'showClinic',
                        'showClinicImage',
                        'showClinicAddress',
                        'showDoctorImage',
                        'showDoctorExperience',
                        'showDoctorSpeciality',
                        'showDoctorDegree',
                        'showDoctorRating',
                        'showServiceImage',
                        'showServicetype',
                        'showServicePrice',
                        'showServiceDuration',
                        'widget_print',
                        'afterWoocommerceRedirect',
                        'skip_service_when_single'
                    ];

                    foreach ($booleanFields as $field) {
                        if (isset($decodedSettings[$field])) {
                            // Handle string '1' or '0' from KCActivate
                            $decodedSettings[$field] = filter_var($decodedSettings[$field], FILTER_VALIDATE_BOOLEAN);
                        }
                    }

                    // Merge with defaults
                    $defaultSettings = array_merge($defaultSettings, $decodedSettings);
                }
            }

            return $this->response(
                $defaultSettings,
                esc_html__('Widget Setting data', 'kivicare-clinic-management-system'),
                true
            );
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Widget Setting Error: ' . $e->getMessage());
            return $this->response(
                ['error' => 'Failed to retrieve widget settings'],
                esc_html__('Widget Setting data retrieval failed', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Update Widget Setting
     */
    public function updateWidgetSetting(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $requestData = $request->get_json_params();

            if (empty($requestData) || !is_array($requestData)) {
                return $this->response(
                    null,
                    esc_html__('Invalid request data', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            $prefix = defined('KIVI_CARE_PREFIX') ? KIVI_CARE_PREFIX : 'kiviCare_';

            // Save widgetOrder to the option used by KCActivate
            if (isset($requestData['widgetOrder']) && is_array($requestData['widgetOrder'])) {
                // Save to core option
                update_option($prefix . 'widget_order_list', $requestData['widgetOrder']);
                
                // Also notify pro version if active
                if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
                    do_action('kcpro_save_widget_order_list', [
                        'list' => $requestData['widgetOrder'],
                    ]);
                }
                
                // Remove from request data as it's saved separately
                unset($requestData['widgetOrder']);
            }

            // Convert boolean fields to '1' or '' format (matching KCActivate structure)
            $booleanFields = [
                'showClinic',
                'showClinicImage',
                'showClinicAddress',
                'showDoctorImage',
                'showDoctorExperience',
                'showDoctorSpeciality',
                'showDoctorDegree',
                'showDoctorRating',
                'showServiceImage',
                'showServicetype',
                'showServicePrice',
                'showServiceDuration',
                'widget_print',
                'afterWoocommerceRedirect',
                'skip_service_when_single'
            ];

            foreach ($booleanFields as $field) {
                if (isset($requestData[$field])) {
                    $requestData[$field] = $requestData[$field] ? '1' : '0';
                }
            }

            // Save settings as JSON to the option used by KCActivate
            update_option($prefix . 'widgetSetting', json_encode($requestData));

            return $this->response(
                null,
                esc_html__('Widget Setting Saved Successfully', 'kivicare-clinic-management-system'),
                true
            );
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Widget Setting Update Error: ' . $e->getMessage());
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update Widget settings', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }
}
