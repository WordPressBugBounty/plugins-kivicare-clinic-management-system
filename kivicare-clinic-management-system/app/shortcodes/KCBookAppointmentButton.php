<?php
namespace App\shortcodes;

use App\abstracts\KCShortcodeAbstract;
use App\baseClasses\KCPaymentGatewayFactory;
use App\models\KCOption;
use App\models\KCClinic;

class KCBookAppointmentButton extends KCShortcodeAbstract
{
    protected $tag = 'kivicareBookAppointmentButton';
    protected $default_attrs = [
        'button_text' => 'Book Appointment',
        'button_class' => '',
        'clinic_id' => 0,
        'doctor_id' => 0,
        'service_id' => 0,
    ];
    protected $assets_dir = KIVI_CARE_DIR . '/dist';
    protected $js_entry = 'app/shortcodes/assets/js/KCBookAppointment.jsx';
    protected $css_entry = 'app/shortcodes/assets/scss/KCBookAppointmentButton.scss';
    protected $in_footer = true;

    protected function render($id, $atts, $content = null)
    {
        if (is_user_logged_in() && in_array($this->kcbase->getLoginUserRole(), ['administrator', $this->kcbase->getDoctorRole(), $this->kcbase->getReceptionistRole(), $this->kcbase->getClinicAdminRole()])) {
            echo esc_html__('Current user can not view the widget. Please open this page in incognito mode or use another browser.', 'kivicare-clinic-management-system');
            return;
        }

        $button_text = !empty($atts['button_text']) ? esc_html($atts['button_text']) : __('Book Appointment', 'kivicare-clinic-management-system');
        $button_class = !empty($atts['button_class']) ? esc_attr($atts['button_class']) : '';
        $selected_doctor_id = !empty($atts['doctor_id']) ? absint($atts['doctor_id']) : '';
        $selected_clinic_id = !empty($atts['clinic_id']) ? absint($atts['clinic_id']) : '';
        $selected_service_id = !empty($atts['service_id']) ? absint($atts['service_id']) : '';
        $modal_id = 'kc-modal-' . uniqid();

        $widgetSettingsOption = KCOption::get('widgetSetting');
        $widgetSettings = [];
        if (!empty($widgetSettingsOption)) {
            $widgetSettings = is_string($widgetSettingsOption) ? json_decode($widgetSettingsOption, true) : $widgetSettingsOption;
        }

        // Get widget order from the correct option
        $widgetOrder = KCOption::get('widget_order_list', []);
        
    
        $paymentGateways = KCPaymentGatewayFactory::get_available_gateways(true);
        $show_print_button = isset($widgetSettings['widget_print']) ? filter_var($widgetSettings['widget_print'], FILTER_VALIDATE_BOOLEAN) : false;

        $timezone_string = get_option('timezone_string') ?: 'UTC';
        
        // Get default clinic ID
        $default_clinic_id = KCClinic::kcGetDefaultClinicId();

        $data_attrs = [
            'data-form-id' => '0',
            'data-title' => '',
            'data-is-kivicare-pro' => defined('KIVI_CARE_PRO_VERSION') ? 'true' : 'false',
            'data-widget-order' => is_array($widgetOrder) ? esc_attr(wp_json_encode($widgetOrder)) : esc_attr('[]'),
            'data-user-login' => get_current_user_id() ? '1' : '0',
            'data-payment-gateways' => esc_attr(wp_json_encode($paymentGateways)),
            'data-current-user-id' => get_current_user_id(),
            'data-page-id' => get_the_ID(),
            'data-show-print-button' => $show_print_button ? 'true' : 'false',
            'data-query-params' => esc_attr(wp_json_encode([])),
            'data-clinic-id' => $selected_clinic_id,
            'data-doctor-id' => $selected_doctor_id,
            'data-service-id' => $selected_service_id,
            'data-timezone' => esc_attr($timezone_string),
            'data-default-clinic-id' => esc_attr($default_clinic_id),
        ];

        $data_attrs_string = '';
        foreach ($data_attrs as $key => $value) {
            if (!empty($value) || $value === '0') {
                $data_attrs_string .= ' ' . $key . '="' . $value . '"';
            }
        }
        ?>
        <div class="kc-appointment-button-wrapper">
            <button class="iq-button iq-button-primary kc-book-appointment-button <?php echo esc_attr(trim($button_class)); ?>" type="button" onclick="document.getElementById('<?php echo esc_attr($modal_id); ?>').style.display='flex'; document.body.classList.add('kc-modal-open');">
                <?php echo esc_html($button_text); ?>
            </button>
        </div>

        <div id="<?php echo esc_attr($modal_id); ?>" class="kc-modal-overlay" style="display:none;">
            <div class="kc-modal-content">
                <button class="kc-modal-close" type="button" onclick="document.getElementById('<?php echo esc_attr($modal_id); ?>').style.display='none'; document.body.classList.remove('kc-modal-open');">&times;</button>
                <div class="kc-appointment-widget-container">
                    <div class="kc-book-appointment-container kivi-widget" <?php echo $data_attrs_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                        <div class="kc-loading">
                            <div class="double-lines-spinner"></div>
                            <p><?php esc_html_e('Loading...', 'kivicare-clinic-management-system'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
        (function() {
            setTimeout(function() {
                if (window.initBookAppointment) {
                    window.initBookAppointment();
                }
            }, 100);
        })();
        </script>
        <?php
    }
}
