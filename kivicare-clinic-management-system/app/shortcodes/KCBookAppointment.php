<?php
namespace App\shortcodes;

use App\abstracts\KCShortcodeAbstract;
use App\baseClasses\KCPaymentGatewayFactory;
use App\models\KCOption;
use App\models\KCClinic;
use App\models\KCServiceDoctorMapping;
use App\models\KCDoctor;

class KCBookAppointment extends KCShortcodeAbstract
{
    protected $tag = 'kivicareBookAppointment';
    protected $default_attrs = [
        'title' => '',
        'form_id' => 0,
        'clinic_id' => 0,
        'doctor_id' => 0,
        'service_id' => 0,
        'timezone' => '',
    ];
    protected $assets_dir = KIVI_CARE_DIR . '/dist';
    protected $js_entry = 'app/shortcodes/assets/js/KCBookAppointment.jsx';

    /**
     * CSS Entry file
     * 
     * @var string
     */
    protected $css_entry = 'app/shortcodes/assets/scss/KCBookAppointment.scss';

    /**
     * Script dependencies
     *
     * @var array
     */
    protected $script_dependencies = [];

    /**
     * CSS dependencies
     *
     * @var array
     */
    protected $css_dependencies = [];

    /**
     * Load scripts in footer
     *
     * @var bool
     */
    protected $in_footer = true;

    /**
     * Render the book appointment button
     *
     * @param string $id Unique ID for this shortcode instance
     * @param array $atts Shortcode attributes
     * @param string|null $content Shortcode content
     * @return void
     */
    protected function render($id, $atts, $content = null)
    {
        // Get widget settings and ensure it's an array
        $widgetSettingsOption = KCOption::get('widgetSetting');
        $widgetSettings = [];
        if (!empty($widgetSettingsOption)) {
            $widgetSettings = is_string($widgetSettingsOption) ? json_decode($widgetSettingsOption, true) : $widgetSettingsOption;
        }

        // Get available payment gateways
        $paymentGateways = [];
        $paymentGateways = KCPaymentGatewayFactory::get_available_gateways(true);

        if (in_array($this->kcbase->getLoginUserRole(), ['administrator', $this->kcbase->getDoctorRole(), $this->kcbase->getReceptionistRole(), $this->kcbase->getClinicAdminRole()])) {
            echo esc_html__('Current user can not view the widget. Please open this page in incognito mode or use another browser.', 'kivicare-clinic-management-system');
            return;
        }

        // Get widget order from the correct option
        $widgetOrder = KCOption::get('widget_order_list', []);

        // Ensure it's an array
        if (empty($widgetOrder) || !is_array($widgetOrder)) {
            $widgetOrder = [];
        }

        $show_print_button = isset($widgetSettings['widget_print']) ? filter_var($widgetSettings['widget_print'], FILTER_VALIDATE_BOOLEAN) : false;

        $service_ids = array_filter(array_map('absint', explode(',', (string) ($atts['service_id'] ?? ''))));

        // Get default clinic ID
        $default_clinic_id = KCClinic::kcGetDefaultClinicId();

        // fix: Auto-select clinic if only one active clinic is available when Pro is active, or use default clinic
        $clinic_id = !empty($atts['clinic_id']) ? $atts['clinic_id'] : 0;
        if (empty($clinic_id)) {
            if (isKiviCareProActive()) {
                if (!empty($service_ids)) {
                    $single_clinic_id = $this->get_single_clinic_id_for_services($service_ids);
                    if (!empty($single_clinic_id)) {
                        $clinic_id = $single_clinic_id;
                    }
                } else {
                    $clinics = KCClinic::query()->where('status', 1)->get();
                    if ($clinics->count() === 1) {
                        $clinic_id = $clinics->first()->id;
                    }
                }
                // improvement: If a default clinic is explicitly set and only one clinic is found or user wants skip, we honor it.
                // However, we only auto-skip if it's the only choice to avoid restricting multi-clinic setups unless forced.
            } else {
                $clinic_id = $default_clinic_id;
            }
        }

        // Get timezone from shortcode parameter, or fallback to admin/site timezone
        $timezone_string = '';
        if (!empty($atts['timezone'])) {
            $timezone_string = $atts['timezone'];
        } else {
            $timezone_string = wp_timezone_string();
            if ($default_clinic_id) {
                $default_clinic = KCClinic::find($default_clinic_id);
                if ($default_clinic && !empty($default_clinic->clinicAdminId)) {
                    $admin_timezone = get_user_meta($default_clinic->clinicAdminId, 'timezone', true);
                    if (!empty($admin_timezone)) {
                        $timezone_string = $admin_timezone;
                    }
                }
            }
        }

        $current_url = set_url_scheme('http://' . sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? '')) . sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '')));

        $service_ids_attr = implode(',', $service_ids);
        $doctor_id = !empty($atts['doctor_id']) ? absint($atts['doctor_id']) : 0;

        if (empty($doctor_id) && !empty($clinic_id) && !empty($service_ids)) {
            $doctor_id = $this->get_single_doctor_id_for_services($service_ids, (int) $clinic_id);
        }

        $data_attrs = [
            'data-form-id' => esc_attr($atts['form_id']),
            'data-title' => esc_attr($atts['title']),
            'data-is-kivicare-pro' => defined('KIVI_CARE_PRO_VERSION') ? 'true' : 'false',
            'data-widget-order' => is_array($widgetOrder) ? esc_attr(wp_json_encode($widgetOrder)) : esc_attr('[]'),
            'data-user-login' => get_current_user_id() ? '1' : '0',
            'data-payment-gateways' => esc_attr(wp_json_encode($paymentGateways)),
            'data-current-user-id' => get_current_user_id(),
            'data-page-id' => get_the_ID(),
            'data-page-url' => esc_url($current_url),
            'data-show-print-button' => $show_print_button ? 'true' : 'false',
            'data-clinic-id' => esc_attr($clinic_id),
            'data-doctor-id' => esc_attr($doctor_id),
            'data-service-id' => esc_attr($service_ids_attr),
            'data-timezone' => esc_attr($timezone_string),
            'data-default-clinic-id' => esc_attr($default_clinic_id),
            'data-primary-color' => isset($widgetSettings['primaryColor']) ? esc_attr($widgetSettings['primaryColor']) : '',
            'data-primary-hover-color' => isset($widgetSettings['primaryHoverColor']) ? esc_attr($widgetSettings['primaryHoverColor']) : '',
            'data-secondary-color' => isset($widgetSettings['secondaryColor']) ? esc_attr($widgetSettings['secondaryColor']) : '',
            'data-secondary-hover-color' => isset($widgetSettings['secondaryHoverColor']) ? esc_attr($widgetSettings['secondaryHoverColor']) : '',
            'data-show-other-gender' => KCOption::get('user_registration_form_setting', 'off'),
            'data-default-country' => KCOption::get('country_code', 'us'),
            'data-show-register-tab' => (($registration_status_settings = KCOption::get('user_registration_shortcode_setting')) && isset($registration_status_settings['patient']) && $registration_status_settings['patient'] === 'on') ? 'true' : 'false',
        ];

        // Add query parameters if they exist
        $query_params = [];

        // Check for payment_status parameter
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['payment_status']) && !empty($_GET['payment_status'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $query_params['payment_status'] = sanitize_text_field(wp_unslash($_GET['payment_status']));
        }

        // Check for payment_id parameter
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['payment_id']) && sanitize_text_field(wp_unslash($_GET['payment_id'])) !== '') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $query_params['payment_id'] = sanitize_text_field(wp_unslash($_GET['payment_id']));
        }

        // Check for message parameter
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['message']) && sanitize_text_field(wp_unslash($_GET['message'])) !== '') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $query_params['message'] = sanitize_text_field(wp_unslash($_GET['message']));
        }

        // Check for appointment_id parameter
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['appointment_id']) && sanitize_text_field(wp_unslash($_GET['appointment_id'])) !== '') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $query_params['appointment_id'] = sanitize_text_field(wp_unslash($_GET['appointment_id']));
        }

        // Add query params to data attributes if any exist
        if (!empty($query_params)) {
            $data_attrs['data-query-params'] = esc_attr(wp_json_encode($query_params));
        }

        if ($this->kcbase->getLoginUserRole() === $this->kcbase->getPatientRole()) {
            $data_attrs['data-user-login'] = true;
        }

        $data_attrs_string = '';
        foreach ($data_attrs as $key => $value) {
            if (!empty($value)) {
                $data_attrs_string .= ' ' . $key . '="' . $value . '"';
            }
        }
        ?>
        <div id="<?php echo esc_attr($id); ?>" class="kc-book-appointment-container" <?php echo $data_attrs_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if (!empty($atts['title'])): ?>
                <h3 class="kc-book-appointment-title"><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>

            <div class="kivi-widget">
                <div class="container-fluid" id="kivicare-widget-main-content">
                    <div class="widget-layout" id="widgetOrders">
                        <div class="iq-card iq-card-lg iq-bg-primary widget-tabs" style="overflow: hidden;">
                            <ul class="tab-list" id="kivicare-animate-ul">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <?php $this->render_loading_tab(); ?>
                                <?php endfor; ?>
                            </ul>
                        </div>
                        <div class="widget-pannel alert-relative">
                            <div class="iq-card iq-card-sm tab-content" id="wizard-tab">
                                <div class="iq-fade active">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                                            <div class="iq-kivi-tab-panel-title-animation">
                                                <h3 class="iq-kivi-tab-panel-title">
                                                    <div class="skeleton-element " style="width: 150px; height: 30px;"
                                                        role="status" aria-label="Loading content"></div>
                                                </h3>
                                            </div>
                                            <div class="iq-kivi-search">
                                                <div class="skeleton-element "
                                                    style="width: 200px; height: 40px; border-radius: 10px;" role="status"
                                                    aria-label="Loading content"></div>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="widget-content">
                                            <div class="card-list-data flex-column gap-2 h-100">
                                                <div class="d-flex flex-column gap-1 pt-2">
                                                    <div class="card-list-data text-center pt-2 mb-3">
                                                        <div class="card-list pe-2 pt-1">
                                                            <div class="kc-card-list">
                                                                <?php for ($i = 0; $i < 3; $i++): ?>
                                                                    <?php $this->render_loading_card(); ?>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_loading_tab(): void
    {
        ?>
        <li class="tab-item">
            <div class="tab-link" style="cursor: default;"><span class="sidebar-heading-text">
                    <div class="skeleton-element " style="width: 120px; height: 20px;" role="status"
                        aria-label="Loading content"></div>
                </span>
                <div class="mb-0 mt-1">
                    <div class="skeleton-element " style="width: 180px; height: 14px;" role="status"
                        aria-label="Loading content"></div>
                </div>
            </div>
        </li>
        <?php
    }

    private function render_loading_card(): void
    {
        ?>
        <div class="iq-client-widget">
            <div class="btn-border01 w-100">
                <div class="iq-card iq-card-lg iq-fancy-design iq-card-border p-3">
                    <div class="d-flex justify-content-center align-items-center mb-3">
                        <div class="skeleton-element " style="width: 90px; height: 90px; border-radius: 50%;" role="status" aria-label="Loading content">
                        </div>
                    </div>
                    <div class="d-flex justify-content-center mb-2">
                        <div class="skeleton-element " style="width: 60%; height: 24px;" role="status" aria-label="Loading content">
                        </div>
                    </div>
                    <div class="d-flex justify-content-center mb-3">
                        <div class="skeleton-element " style="width: 80%; height: 16px;" role="status" aria-label="Loading content">
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="skeleton-element " style="width: 40px; height: 16px;" role="status" aria-label="Loading content"></div>
                            <div class="skeleton-element " style="width: 120px; height: 16px;" role="status" aria-label="Loading content"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Resolve a single active clinic that supports every preselected service.
     *
     * @param array $service_ids KC service IDs.
     * @return int
     */
    private function get_single_clinic_id_for_services(array $service_ids): int
    {
        $service_count = count($service_ids);
        if ($service_count === 0) {
            return 0;
        }

        $mappings = KCServiceDoctorMapping::query()
            ->whereIn('service_id', $service_ids)
            ->where('status', 1)
            ->select(['clinic_id', 'service_id'])
            ->get()
            ->groupBy('clinicId')
            ->filter(function ($clinic_mappings) use ($service_count) {
                return $clinic_mappings->pluck('serviceId')->unique()->count() === $service_count;
            });
        if ($mappings->count() !== 1) {
            return 0;
        }

        $clinic_id = (int) $mappings->keys()->first();
        $clinic = KCClinic::find($clinic_id);

        return $clinic && (int) $clinic->status === 1 ? $clinic_id : 0;
    }

    /**
     * Resolve a single active doctor in a clinic that supports every service.
     *
     * @param array $service_ids KC service IDs.
     * @param int   $clinic_id Clinic ID.
     * @return int
     */
    private function get_single_doctor_id_for_services(array $service_ids, int $clinic_id): int
    {
        $service_count = count($service_ids);
        if ($service_count === 0 || $clinic_id <= 0) {
            return 0;
        }

        $mappings = KCServiceDoctorMapping::query()
            ->whereIn('service_id', $service_ids)
            ->where('clinic_id', $clinic_id)
            ->where('status', 1)
            ->select(['doctor_id', 'service_id'])
            ->get()
            ->groupBy('doctorId')
            ->filter(function ($doctor_mappings) use ($service_count) {
                return $doctor_mappings->pluck('serviceId')->unique()->count() === $service_count;
            });

        if ($mappings->count() !== 1) {
            return 0;
        }

        $doctor_id = (int) $mappings->keys()->first();
        $doctor = KCDoctor::find($doctor_id);

        return $doctor && (int) $doctor->userStatus === 0 ? $doctor_id : 0;
    }
}
