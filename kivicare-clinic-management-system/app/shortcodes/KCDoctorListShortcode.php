<?php

namespace App\shortcodes;

use App\abstracts\KCShortcodeAbstract;
use App\baseClasses\KCErrorLogger;
use App\models\KCOption;
use App\baseClasses\KCPaymentGatewayFactory;
use App\models\KCDoctor;
use App\models\KCClinicSession;
use App\models\KCDoctorClinicMapping;
use App\models\KCUserMeta;

class KCDoctorListShortcode extends KCShortcodeAbstract
{
    protected $tag = 'kivicare_doctor_list';
    protected $default_attrs = [
        'clinic_id' => '',
        'selected_doctors' => '',
        'enable_filter' => 'yes',
        'per_page' => '5',
        'show_image' => 'yes',
        'show_name' => 'yes',
        'show_speciality' => 'yes',
        'show_number' => 'yes',
        'show_email' => 'yes',
        'show_qualification' => 'yes',
        'show_session' => 'yes',
        // Label parameters
        'name_label' => 'Name',
        'speciality_label' => 'Speciality',
        'number_label' => 'Contact No',
        'email_label' => 'Email ID',
        'qualification_label' => 'Qualification',
        'session_label' => 'Schedule Appointment',
    ];
    
    protected $assets_dir = KIVI_CARE_DIR . '/dist';
    protected $js_entry = 'app/elementor/widgets/js/DoctorWidget.jsx';
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
     * Render the doctor list shortcode
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
            KCErrorLogger::instance()->error('KCDoctorListShortcode: Failed to enqueue BookAppointment assets: ' . $e->getMessage());
        }

        if (in_array($this->kcbase->getLoginUserRole(), ['administrator', $this->kcbase->getDoctorRole(), $this->kcbase->getReceptionistRole(), $this->kcbase->getClinicAdminRole()])) {
            echo esc_html__('Current user can not view the widget. Please open this page in incognito mode or use another browser.', 'kivicare-clinic-management-system');
            return;
        }

        // Get doctors data without relying on Elementor
        $clinic_id = !empty($atts['clinic_id']) ? $atts['clinic_id'] : '';
        $specific_doctors = [];
        
        // Handle specific doctor selection
        if (!empty($atts['selected_doctors'])) {
            // Parse selected_doctors - can be comma-separated string or array
            if (is_string($atts['selected_doctors'])) {
                $specific_doctors = array_filter(array_map('trim', explode(',', $atts['selected_doctors'])));
            } elseif (is_array($atts['selected_doctors'])) {
                $specific_doctors = array_filter($atts['selected_doctors']);
            }
        }
        
        $doctors_per_page = !empty($atts['per_page']) ? intval($atts['per_page']) : 5;
        $doctors = $this->get_doctors_data($clinic_id, $specific_doctors, 1000);


        if (empty($doctors)) {
            echo '<div class="kivicare-no-doctors">' . esc_html__('No doctors found.', 'kivicare-clinic-management-system') . '</div>';
            return;
        }

        // Prepare settings array similar to Elementor widget
        $settings = [
            'iq_kivicare_doctor_clinic_id' => $atts['clinic_id'],
            'selected_doctors' => $specific_doctors,
            'iq_kivicare_doctor_enable_filter' => $atts['enable_filter'],
            'iq_kivicare_doctor_par_page' => $atts['per_page'],
            'iq_kivicare_doctor_image' => $atts['show_image'],
            'iq_kivicare_doctor_name' => $atts['show_name'],
            'iq_kivicare_doctor_speciality' => $atts['show_speciality'],
            'iq_kivicare_doctor_number' => $atts['show_number'],
            'iq_kivicare_doctor_email' => $atts['show_email'],
            'iq_kivicare_doctor_qualification' => $atts['show_qualification'],
            'iq_kivicare_doctor_session' => $atts['show_session'],
            // Label settings
            'iq_kivicare_doctor_name_label' => $atts['name_label'],
            'iq_kivicare_doctor_speciality_label' => $atts['speciality_label'],
            'iq_kivicare_doctor_number_label' => $atts['number_label'],
            'iq_kivicare_doctor_email_label' => $atts['email_label'],
            'iq_kivicare_doctor_qualification_label' => $atts['qualification_label'],
            'iq_kivicare_doctor_session_label' => $atts['session_label'],
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
            'data-doctors' => esc_attr(json_encode($doctors)),
            'data-settings' => esc_attr(json_encode($settings)),
            'data-clinic-id' => esc_attr($clinic_id),
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
             class="kivicare-doctor-list-container kivicare-shortcode-container" <?php echo $data_attrs_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="kivicare-loading"><?php esc_html_e('Loading doctors...', 'kivicare-clinic-management-system'); ?></div>
        </div>
        <?php
    }

    /**
     * Fetch doctors data (ported from Elementor widget to avoid Elementor dependency)
     */
    private function get_doctors_data($clinic_id = '', $specific_doctors = [], $per_page = 5)
    {
        $doctors = [];
        try {
            $query = KCDoctor::table('d')
                ->select([
                    'd.ID as id',
                    'd.display_name',
                    'd.user_email',
                    'um_first.meta_value as first_name',
                    'um_last.meta_value as last_name',
                    'bd.meta_value as basic_data',
                    'pi.meta_value as profile_image_id',
                ])
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'um_first.user_id')
                        ->onRaw("um_first.meta_key = 'first_name'");
                }, null, null, 'um_first')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'um_last.user_id')
                        ->onRaw("um_last.meta_key = 'last_name'");
                }, null, null, 'um_last')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'bd.user_id')
                        ->onRaw("bd.meta_key = 'basic_data'");
                }, null, null, 'bd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'pi.user_id')
                        ->onRaw("pi.meta_key = 'doctor_profile_image'");
                }, null, null, 'pi');

            if (!empty($clinic_id)) {
                $query->join(KCDoctorClinicMapping::class, 'd.ID', '=', 'dcm.doctor_id', 'dcm')
                    ->where('dcm.clinic_id', '=', $clinic_id);
            }

            if (!empty($specific_doctors)) {
                $query->whereIn('d.ID', $specific_doctors);
            }

            $query->groupBy('d.ID');

            $results = $query->get();

            foreach ($results as $result) {
                $basic_data = json_decode($result->basic_data, true) ?? [];

                $doctorId = !empty($result->id) ? (int) $result->id : (!empty($result->ID) ? (int) $result->ID : null);
                if (empty($doctorId)) {
                    KCErrorLogger::instance()->error('KiviCare Doctor List Shortcode: Missing doctor ID for result, skipping.');
                    continue;
                }

                $qualifications = [];
                if (!empty($basic_data['qualifications'])) {
                    foreach ($basic_data['qualifications'] as $qual) {
                        if (!empty($qual['degree'])) {
                            $qual_parts = [$qual['degree']];
                            if (!empty($qual['university'])) {
                                $college_part = $qual['university'];
                                if (!empty($qual['year'])) {
                                    $college_part .= ' - ' . $qual['year'];
                                }
                                $qual_parts[] = '(' . $college_part . ')';
                            }
                            $qualifications[] = implode(' ', $qual_parts);
                        }
                    }
                }
                $qualification_str = implode(', ', array_filter($qualifications));

                $emailAddress = !empty($result->email)
                    ? $result->email
                    : ($basic_data['email'] ?? '');

                $services = [];
                if (!empty($basic_data['services'])) {
                    if (is_array($basic_data['services'])) {
                        foreach ($basic_data['services'] as $serviceItem) {
                            if (is_array($serviceItem)) {
                                $label = $serviceItem['label'] ?? $serviceItem['name'] ?? '';
                                if (!empty($label)) {
                                    $services[] = $label;
                                }
                            } elseif (!empty($serviceItem)) {
                                $services[] = (string) $serviceItem;
                            }
                        }
                    } elseif (is_string($basic_data['services'])) {
                        $services = array_filter(array_map('trim', explode(',', $basic_data['services'])));
                    }
                }

                $doctors[] = [
                    'id' => $doctorId,
                    'name' => $result->display_name,
                    'speciality' => isset($basic_data['specialties'][0]['label']) ? $basic_data['specialties'][0]['label'] : '',
                    'services' => $services,
                    'qualification' => $qualification_str,
                    'number' => $basic_data['mobile_number'] ?? '',
                    'email' => $emailAddress,
                    'image' => !empty($result->profile_image_id) ?
                        wp_get_attachment_url($result->profile_image_id) :
                        'https://placehold.co/120x120/3498db/white?text=' . substr($result->display_name, 0, 2),
                    'sessions' => $this->get_doctor_sessions($doctorId, $clinic_id)
                ];
            }
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KiviCare Doctor List Shortcode Error: ' . $e->getMessage());
        }
        return $doctors;
    }

    private function get_doctor_sessions($doctor_id, $clinic_id = null)
    {
        try {
            if (empty($doctor_id) || !is_numeric($doctor_id)) {
                return [];
            }

            $query = KCClinicSession::query()
                ->where('doctor_id', '=', $doctor_id);

            if (!empty($clinic_id)) {
                $query->where('clinic_id', '=', $clinic_id);
            }

            $sessions = $query->get(['day', 'startTime', 'endTime', 'timeSlot', 'parentId']);
            $grouped_sessions = [];
            $formatted_sessions = [];

            if ($sessions->isNotEmpty()) {
                foreach ($sessions as $session) {
                    $day = ucfirst(substr($session->day, 0, 3));
                    $time_slot = gmdate('g:i A', strtotime($session->startTime)) . ' - ' .
                        gmdate('g:i A', strtotime($session->endTime));

                    if (!isset($grouped_sessions[$day])) {
                        $grouped_sessions[$day] = [];
                    }

                    $grouped_sessions[$day][] = $time_slot;
                }

                foreach ($grouped_sessions as $day => $time_slots) {
                    $formatted_sessions[] = [
                        'day' => $day,
                        'time' => implode(', ', $time_slots),
                        'timeSlots' => $time_slots,
                        'isMultiple' => count($time_slots) > 1
                    ];
                }
            }

            return $formatted_sessions;
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Error getting doctor sessions (shortcode): ' . $e->getMessage());
            return [];
        }
    }
}