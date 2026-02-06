<?php
namespace App\shortcodes;

use App\abstracts\KCShortcodeAbstract;
use App\baseClasses\KCPaymentGatewayFactory;
use App\models\KCOption;
use App\models\KCClinic;

class KCBookAppointment extends KCShortcodeAbstract
{
    protected $tag = 'kivicareBookAppointment';
    protected $default_attrs = [
        'title' => '',
        'form_id' => 0,
        'clinic_id' => 0,
        'doctor_id' => 0,
        'service_id' => 0,
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

        $timezone_string = get_option('timezone_string') ?: 'UTC';

        // Get default clinic ID
        $default_clinic_id = KCClinic::kcGetDefaultClinicId();

        $data_attrs = [
            'data-form-id' => esc_attr($atts['form_id']),
            'data-title' => esc_attr($atts['title']),
            'data-is-kivicare-pro' => defined('KIVI_CARE_PRO_VERSION') ? 'true' : 'false',
            'data-widget-order' => is_array($widgetOrder) ? esc_attr(wp_json_encode($widgetOrder)) : esc_attr('[]'),
            'data-user-login' => get_current_user_id() ? '1' : '0',
            'data-payment-gateways' => esc_attr(wp_json_encode($paymentGateways)),
            'data-current-user-id' => get_current_user_id(),
            'data-page-id' => get_the_ID(),
            'data-show-print-button' => $show_print_button ? 'true' : 'false',
            'data-clinic-id' => esc_attr($atts['clinic_id']),
            'data-doctor-id' => esc_attr($atts['doctor_id']),
            'data-service-id' => esc_attr($atts['service_id']),
            'data-timezone' => esc_attr($timezone_string),
            'data-default-clinic-id' => esc_attr($default_clinic_id),
            'data-primary-color' => isset($widgetSettings['primaryColor']) ? esc_attr($widgetSettings['primaryColor']) : '',
            'data-primary-hover-color' => isset($widgetSettings['primaryHoverColor']) ? esc_attr($widgetSettings['primaryHoverColor']) : '',
            'data-secondary-color' => isset($widgetSettings['secondaryColor']) ? esc_attr($widgetSettings['secondaryColor']) : '',
            'data-secondary-hover-color' => isset($widgetSettings['secondaryHoverColor']) ? esc_attr($widgetSettings['secondaryHoverColor']) : '',
            'data-show-other-gender' => KCOption::get('user_registration_form_setting', 'off'),
            'data-default-country' => KCOption::get('country_code', 'us'),
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
        if (isset($_GET['payment_id']) && sanitize_text_field( wp_unslash( $_GET['payment_id'] ) ) !== '') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $query_params['payment_id'] = sanitize_text_field(wp_unslash($_GET['payment_id']));
        }

        // Check for message parameter
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['message']) && sanitize_text_field( wp_unslash( $_GET['message'] ) ) !== '') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $query_params['message'] = sanitize_text_field(wp_unslash($_GET['message']));
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

        </div>
        <?php
    }
}