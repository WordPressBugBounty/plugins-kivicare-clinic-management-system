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
            <button class="iq-button iq-button-primary kc-book-appointment-button <?php echo esc_attr(trim($button_class)); ?>" type="button" id="kc-open-<?php echo esc_attr($modal_id); ?>">
                <?php echo esc_html($button_text); ?>
            </button>
        </div>

        <!-- Hidden container: JS will move its inner content into the overlay -->
        <div id="<?php echo esc_attr($modal_id); ?>-content" style="display:none;">
            <div class="kc-appointment-widget-container">
                <div class="kc-book-appointment-container kivi-widget" <?php echo $data_attrs_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                    <div class="kc-loading">
                        <div class="double-lines-spinner"></div>
                        <p><?php esc_html_e('Loading...', 'kivicare-clinic-management-system'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var modalId = '<?php echo esc_js($modal_id); ?>';
            var overlay = null;

            function createOverlay() {
                // Create overlay div with inline styles — immune to parent CSS
                overlay = document.createElement('div');
                overlay.id = modalId + '-overlay';
                // Use cssText with !important to override any theme CSS
                overlay.style.cssText = 
                    'position:fixed !important;' +
                    'top:0 !important;' +
                    'left:0 !important;' +
                    'width:100% !important;' +
                    'height:100% !important;' +
                    'z-index:2147483647 !important;' +
                    'background-color:rgba(0,0,0,0.6) !important;' +
                    'display:flex !important;' +
                    'align-items:center !important;' +
                    'justify-content:center !important;' +
                    'padding:2rem !important;' +
                    'margin:0 !important;' +
                    'box-sizing:border-box !important;' +
                    'overflow-y:auto !important;' +
                    'transform:none !important;' +
                    'filter:none !important;' +
                    'opacity:1 !important;' +
                    'visibility:visible !important;'
                ;

                // Create dialog box
                var dialog = document.createElement('div');
                dialog.className = 'kc-modal-dialog';

                // Create close button
                var closeBtn = document.createElement('button');
                closeBtn.className = 'kc-modal-close';
                closeBtn.type = 'button';
                closeBtn.innerHTML = '&times;';
                closeBtn.addEventListener('click', closeModal);

                // Move widget content into dialog
                var contentHolder = document.getElementById(modalId + '-content');
                if (contentHolder) {
                    // Move all children
                    while (contentHolder.firstChild) {
                        dialog.appendChild(contentHolder.firstChild);
                    }
                }

                dialog.insertBefore(closeBtn, dialog.firstChild);
                overlay.appendChild(dialog);

                // Click on backdrop to close
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) closeModal();
                });

                // Append directly to body
                document.body.appendChild(overlay);
            }

            function openModal() {
                if (!overlay) createOverlay();
                overlay.style.setProperty('display', 'flex', 'important');
                document.body.classList.add('kc-modal-open');

                // Init React widget if not already done
                setTimeout(function() {
                    if (window.initBookAppointment) window.initBookAppointment();
                }, 50);
            }

            function closeModal() {
                if (overlay) overlay.style.setProperty('display', 'none', 'important');
                document.body.classList.remove('kc-modal-open');
            }

            // Open button
            var openBtn = document.getElementById('kc-open-' + modalId);
            if (openBtn) openBtn.addEventListener('click', openModal);

            // Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && overlay && getComputedStyle(overlay).display !== 'none') closeModal();
            });

            // Init React widget on page load too (for non-button shortcode)
            setTimeout(function() {
                if (window.initBookAppointment) window.initBookAppointment();
            }, 100);
        })();
        </script>
        <?php
    }
}
