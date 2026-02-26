<?php

namespace App\shortcodes;

use App\abstracts\KCShortcodeAbstract;
use App\baseClasses\KCErrorLogger;
use App\models\KCOption;
use App\baseClasses\KCPaymentGatewayFactory;
use App\models\KCClinic;
use App\models\KCService;
use App\models\KCServiceDoctorMapping;
use App\models\KCUser;
use App\models\KCUserMeta;

class KCClinicListShortcode extends KCShortcodeAbstract
{
    protected $tag = 'kivicare_clinic_list';
    protected $default_attrs = [
        'enable_filter' => 'yes',
        'per_page' => '5',
        'show_image' => 'yes',
        'show_name' => 'yes',
        'show_speciality' => 'yes',
        'show_number' => 'yes',
        'show_email' => 'yes',
        'show_address' => 'yes',
        'show_administrator' => 'yes',
        'show_admin_number' => 'yes',
        'show_admin_email' => 'yes',
        // Label parameters
        'name_label' => 'Name',
        'speciality_label' => 'Speciality',
        'number_label' => 'Contact No',
        'email_label' => 'Email ID',
        'address_label' => 'Address',
        'administrator_label' => 'Administrator',
        'admin_number_label' => 'Contact No',
        'admin_email_label' => 'Email ID',
    ];
    
    protected $assets_dir = KIVI_CARE_DIR . '/dist';
    protected $js_entry = 'app/elementor/widgets/js/ClinicWidget.jsx';
    protected $css_entry = 'app/elementor/widgets/scss/ElementorWidget.scss';
    protected $in_footer = true;
    
    /**
     * Script dependencies
     *
     * @var array
     */
    protected $script_dependencies = ['jquery'];
    
    /**
     * CSS dependencies
     *
     * @var array
     */
    protected $css_dependencies = [];

    /**
     * Render the clinic list shortcode
     */
    protected function render($id, $atts, $content = null)
    {
        // Ensure Book Appointment assets are available when opened from this shortcode (no Elementor required)
        try {
            \Iqonic\Vite\iqonic_enqueue_asset(
                $this->assets_dir,
                'app/shortcodes/assets/js/KCBookAppointment.jsx',
                [
                    'handle' => KIVI_CARE_NAME . 'kivicareBookAppointment',
                    'in-footer' => true,
                ]
            );
        } catch (\Throwable $e) {
            KCErrorLogger::instance()->error('KCClinicListShortcode: Failed to enqueue BookAppointment assets: ' . $e->getMessage());
        }

        if (in_array($this->kcbase->getLoginUserRole(), ['administrator', $this->kcbase->getDoctorRole(), $this->kcbase->getReceptionistRole(), $this->kcbase->getClinicAdminRole()])) {
            echo esc_html__('Current user can not view the widget. Please open this page in incognito mode or use another browser.', 'kivicare-clinic-management-system');
            return;
        }

        // Get clinics data without relying on Elementor
        $clinics_per_page = !empty($atts['per_page']) ? intval($atts['per_page']) : 5;
        $clinics = $this->get_clinics_data([], 1000);

        // Debug: Log clinic data

        if (empty($clinics)) {
            echo '<div class="kivicare-no-clinics">' . esc_html__('No clinics found.', 'kivicare-clinic-management-system') . '</div>';
            return;
        }

        // Prepare settings array similar to Elementor widget
        $settings = [
            'iq_kivicare_clinic_enable_filter' => $atts['enable_filter'],
            'iq_kivicare_clinic_per_page' => $atts['per_page'],
            'iq_kivicare_clinic_image' => $atts['show_image'],
            'iq_kivicare_clinic_name' => $atts['show_name'],
            'iq_kivicare_clinic_speciality' => $atts['show_speciality'],
            'iq_kivicare_clinic_number' => $atts['show_number'],
            'iq_kivicare_clinic_email' => $atts['show_email'],
            'iq_kivicare_clinic_address' => $atts['show_address'],
            'iq_kivicare_clinic_administrator' => $atts['show_administrator'],
            'iq_kivicare_clinic_admin_number' => $atts['show_admin_number'],
            'iq_kivicare_clinic_admin_email' => $atts['show_admin_email'],
            // Label settings
            'iq_kivicare_clinic_name_label' => $atts['name_label'],
            'iq_kivicare_clinic_speciality_label' => $atts['speciality_label'],
            'iq_kivicare_clinic_number_label' => $atts['number_label'],
            'iq_kivicare_clinic_email_label' => $atts['email_label'],
            'iq_kivicare_clinic_address_label' => $atts['address_label'],
            'iq_kivicare_clinic_administrator_label' => $atts['administrator_label'],
            'iq_kivicare_clinic_admin_number_label' => $atts['admin_number_label'],
            'iq_kivicare_clinic_admin_email_label' => $atts['admin_email_label'],
        ];

        // Prepare booking widget configuration for modal
        $widgetSettings = KCOption::get('widgetSetting');
        
        // Get widget order from the correct option
        $widgetOrder = KCOption::get('widget_order_list', []);
        $paymentGateways = [];
        try {
            $paymentGateways = KCPaymentGatewayFactory::get_available_gateways(true);
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KiviCare: Error fetching payment gateways - ' . $e->getMessage());
        }

        $booking_data_attrs = [
            'data-widget-order' => !empty($widgetOrder) ? esc_attr(wp_json_encode($widgetOrder)) : esc_attr(wp_json_encode([])),
            'data-is-kivicare-pro' => defined('KIVI_CARE_PRO_VERSION') ? 'true' : 'false',
            'data-payment-gateways' => esc_attr(wp_json_encode($paymentGateways)),
            'data-user-login' => get_current_user_id() ? '1' : '0',
            'data-current-user-id' => get_current_user_id(),
            'data-page-id' => get_the_ID(),
        ];

        $booking_attr_string = '';
        foreach ($booking_data_attrs as $key => $value) {
            if (!empty($value)) {
                $booking_attr_string .= ' ' . $key . '="' . $value . '"';
            }
        }

        // Global modal (rendered once, hidden by default)
        ?>
        <div id="kivicare-appointment-modal" class="kivicare-modal" style="display:none;">
            <div class="kivicare-modal-content">
                <span class="kivicare-modal-close">&times;</span>
                <div id="kivicare-modal-form-container"<?php echo $booking_attr_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></div>
            </div>
        </div>
        <?php

        // Data attributes for React configuration
        $data_attrs = [
            'data-clinics' => esc_attr(json_encode($clinics)),
            'data-settings' => esc_attr(json_encode($settings)),
            'data-current-user-role' => esc_attr($this->kcbase->getLoginUserRole()),
        ];

        $data_attrs_string = '';
        foreach ($data_attrs as $key => $value) {
            if (!empty($value)) {
                $data_attrs_string .= ' ' . $key . '="' . $value . '"';
            }
        }

        // Shortcode container
        ?>
        <div id="<?php echo esc_attr($id); ?>"
             class="kivicare-clinic-list-container kivicare-shortcode-container" <?php echo $data_attrs_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="kivicare-loading"><?php esc_html_e('Loading clinics...', 'kivicare-clinic-management-system'); ?></div>
        </div>
        <?php
    }

    /**
     * Fetch clinics data (ported from Elementor widget to avoid Elementor dependency)
     */
    private function get_clinics_data($exclude_clinics = [], $per_page = 2)
    {
        $clinics = [];
        try {
            $query = KCClinic::table('c')
                ->select([
                    'c.id',
                    'c.name',
                    'c.address',
                    'c.city',
                    'c.postal_code',
                    'c.country',
                    'c.telephone_no',
                    'c.email',
                    'c.profile_image as profile_image_id',
                    'c.specialties as specialties_json',
                    'c.clinic_admin_id',
                    'u.display_name as admin_name',
                    'u.user_email as admin_email',
                    'bd.meta_value as admin_basic_data',
                ])
                ->leftJoin(KCUser::class, function ($join) {
                    $join->on('c.clinic_admin_id', '=', 'u.ID');
                }, null, null, 'u')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('u.ID', '=', 'bd.user_id')
                        ->onRaw("bd.meta_key = 'basic_data'");
                }, null, null, 'bd');

            if (!empty($exclude_clinics)) {
                $query->whereNotIn('c.id', $exclude_clinics);
            }

            if ($per_page > 0) {
                $query->limit($per_page);
            }

            $results = $query->get();

            // Fetch all active services
            $all_services = KCService::query()
                ->where('status', 1)
                ->select(['id', 'name'])
                ->get()
                ->mapWithKeys(function ($service) {
                    return [$service->id => $service->name];
                })
                ->all();

            foreach ($results as $result) {
                $specialties_data = json_decode($result->specialties_json, true) ?? [];
                $specialties = [];
                if (!empty($specialties_data)) {
                    foreach ($specialties_data as $spec) {
                        $label = $spec['label'] ?? '';
                        if (!empty($label)) {
                            $specialties[] = $label;
                        }
                    }
                }
                $specialty_str = implode(', ', $specialties);

                // Fetch services for this clinic via doctor mappings
                $doctor_mappings = KCServiceDoctorMapping::query()
                    ->where('clinic_id', $result->id)
                    ->where('status', 1)
                    ->get();

                $services = [];
                // fix: use correct property name serviceId instead of service_id to display services in dropdown
                foreach ($doctor_mappings as $mapping) {
                    if (isset($all_services[$mapping->serviceId])) {
                        $services[] = $all_services[$mapping->serviceId];
                    }
                }
                $services = array_values(array_unique($services));

                $admin_basic = json_decode($result->admin_basic_data, true) ?? [];
                $admin_number = $admin_basic['mobile_number'] ?? '';

                $full_address = implode(', ', array_filter([$result->address, $result->city, $result->postal_code, $result->country]));

                $clinics[] = [
                    'id' => $result->id,
                    'name' => $result->name,
                    'speciality' => $specialty_str,
                    'specialties' => $specialties,
                    'services' => $services,
                    'number' => $result->telephoneNo ?? '',
                    'email' => $result->email ?? '',
                    'address' => $full_address,
                    'admin_name' => $result->admin_name ?? '',
                    'admin_number' => $admin_number,
                    'admin_email' => $result->admin_email ?? '',
                    'image' => !empty($result->profile_image_id) ?
                        wp_get_attachment_url($result->profile_image_id) :
                        'https://placehold.co/120x120/3498db/white?text=' . substr($result->name, 0, 2),
                ];
            }
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KiviCare Clinic List Shortcode Error: ' . $e->getMessage());
        }
        return $clinics;
    }
}