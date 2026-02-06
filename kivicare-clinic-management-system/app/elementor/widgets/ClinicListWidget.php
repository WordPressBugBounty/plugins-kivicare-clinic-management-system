<?php

namespace App\elementor\widgets;

use App\baseClasses\KCErrorLogger;
use App\models\KCUser;
use App\models\KCUserMeta;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use App\abstracts\KCElementorWidgetAbstract;
use App\models\KCClinic;
use App\models\KCService;
use App\models\KCServiceDoctorMapping;
use App\models\KCOption;
use App\baseClasses\KCPaymentGatewayFactory;
use function Iqonic\Vite\iqonic_enqueue_asset;


class ClinicListWidget extends KCElementorWidgetAbstract
{
    /**
     * Asset management properties
     */
    protected $assets_dir = KIVI_CARE_DIR . '/dist';
    protected $js_entry = 'app/elementor/widgets/js/ClinicWidget.jsx';
    protected $script_dependencies = ['jquery'];
    protected $css_entry = 'app/elementor/widgets/scss/ElementorWidget.scss';
    protected $in_footer = true;

    public function get_name()
    {
        return 'kivicare_clinic_list';
    }

    public function get_title()
    {
        return __('KiviCare Clinic List', 'kivicare-clinic-management-system');
    }

    public function get_icon()
    {
        return 'eicon-plus';
    }

    public function get_categories()
    {
        return ['kivicare'];
    }

    public function get_keywords()
    {
        return ['clinic', 'list', 'kivicare'];
    }

    public function get_script_depends() {
        return [KIVI_CARE_NAME . $this->get_name(), 'kivicare-clinic-management-system'];
    }

    public function get_style_depends() {
        return [KIVI_CARE_NAME . $this->get_name() . '-css'];
    }

    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);

        if (!is_admin()) {
            $this->registerAssets();
            // Ensure Book Appointment assets are available when opened from this widget
            try {
               iqonic_enqueue_asset(
                    $this->assets_dir,
                    'app/shortcodes/assets/js/KCBookAppointment.jsx',
                    [
                        'handle' => KIVI_CARE_NAME . 'kivicareBookAppointment',
                        'in-footer' => true,
                    ]
                );
            } catch (\Throwable $e) {
                // Silently fail - widget will still render; just log for debugging
                KCErrorLogger::instance()->error('ClinicListWidget: Failed to enqueue BookAppointment assets: ' . $e->getMessage());
            }
        }
    }

    protected function getWidgetName()
    {
        return $this->get_name();
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'iq_kivicare_clinic_card_shortcode',
            [
                'label' => __('KiviCare Clinic Card', 'kivicare-clinic-management-system'),
            ]
        );

        $this->add_control(
            'iq_kivicare_clinic_enable_filter',
            [
                'label' => __('Enable Filter', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'label_off' => __('Hide', 'kivicare-clinic-management-system'),
                'label_on' => __('Show', 'kivicare-clinic-management-system'),
            ]
        );

        $this->add_control(
            'iq_kivicare_clinic_per_page',
            [
                'label' => __('Clinic per page ', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 2,
                'min' => 0
            ]
        );


        $this->add_control(
            'iq_kivicare_clinic_gap_between_card',
            [
                'label' => __('Hide Space Between Clinics', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_off' => __('Hide', 'kivicare-clinic-management-system'),
                'label_on' => __('Show', 'kivicare-clinic-management-system'),
            ]
        );

        $this->add_control(
            'iq_kivicare_clinic_image',
            [
                'label' => __('Profile Image', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'label_off' => __('Hide', 'kivicare-clinic-management-system'),
                'label_on' => __('Show', 'kivicare-clinic-management-system'),
            ]
        );

        $this->commonControl($this, 'name', 'clinic');
        $this->commonControl($this, 'speciality', 'clinic');
        $this->commonControl($this, 'number', 'clinic');
        $this->commonControl($this, 'email', 'clinic');
        $this->commonControl($this, 'address', 'clinic');

        $this->commonControl($this, 'administrator', 'clinic');
        $this->commonControl($this, 'admin_number', 'clinic');
        $this->commonControl($this, 'admin_email', 'clinic');

        $this->end_controls_section();

        $this->start_controls_section(
            'iq_kivicare_card_style_sections',
            [
                'label' => __('Card style', 'kivicare-clinic-management-system'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'iq_card_background',
                'label' => __('Card Background', 'kivicare-clinic-management-system'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .kivicare-clinic-card',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'iq_card_box_shadow',
                'label' => __('Card Box Shadow', 'kivicare-clinic-management-system'),
                'selector' => '{{WRAPPER}} .kivicare-clinic-card',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'iq_card_border',
                'label' => __('Card Border', 'kivicare-clinic-management-system'),
                'selector' => '{{WRAPPER}} .kivicare-clinic-card',
            ]
        );

        $this->add_control(
            'iq_card_border_radius',
            [
                'label' => __('Card Border Radius', 'kivicare-clinic-management-system'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .kivicare-clinic-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};overflow:hidden;',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'iq_kivicare_image_style_sections',
            [
                'label' => __('Image style', 'kivicare-clinic-management-system'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'iq_kivicare_clinic_image' => 'yes'
                ],
            ]
        );

        $this->add_control(
            'iq_kivicare_clinic_image_height',
            [
                'label' => __('Image Height', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivicare_clinic_image' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-clinic-avtar' => 'height: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_clinic_image_width',
            [
                'label' => __('Image width', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivicare_clinic_image' => 'yes'
                ],
                'min' => 0,
                'selectors' => [
                    '{{WRAPPER}} .kivicare-clinic-avtar' => 'width: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_clinic_image_border',
            [
                'label' => __('Image Border', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivicare_clinic_image' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-clinic-avtar' => 'border-width: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_clinic_image_border_radius',
            [
                'label' => __('Image Border Radius', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivicare_clinic_image' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-clinic-avtar' => 'border-radius: {{VALUE}}%;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_clinic_image_border_style',
            [
                'label' => __('Image Border style', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'condition' => [
                    'iq_kivicare_clinic_image' => 'yes'
                ],
                'options' => [
                    'solid' => __('solid', 'kivicare-clinic-management-system'),
                    'dashed' => __('dashed', 'kivicare-clinic-management-system'),
                    'dotted' => __('dotted', 'kivicare-clinic-management-system'),
                    'double' => __('double', 'kivicare-clinic-management-system'),
                    'groove' => __('groove', 'kivicare-clinic-management-system'),
                    'ridge' => __('ridge', 'kivicare-clinic-management-system'),
                    'inset' => __('inset', 'kivicare-clinic-management-system'),
                    'outset' => __('outset', 'kivicare-clinic-management-system'),
                    'none' => __('none', 'kivicare-clinic-management-system'),
                    'hidden' => __('hidden', 'kivicare-clinic-management-system'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-clinic-avtar' => 'border-style: {{VALUE}};',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_clinic_image_border_color',
            [
                'label' => __('Image Border Color', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivicare_clinic_image' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-clinic-avtar' => 'border-color: {{VALUE}};',
                ]
            ]
        );
        $this->end_controls_section();

        $userType = 'clinic';
        $this->fontStyleControl($this, 'name', $userType);
        $this->fontStyleControl($this, 'speciality', $userType);
        $this->fontStyleControl($this, 'number', $userType);
        $this->fontStyleControl($this, 'email', $userType);
        $this->fontStyleControl($this, 'address', $userType);
        $this->fontStyleControl($this, 'admin_number', $userType);
        $this->fontStyleControl($this, 'admin_email', $userType);

        $this->start_controls_section(
            'iq_kivicare_administrator_style_sections',
            [
                'label' => __('Administrator style', 'kivicare-clinic-management-system'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'iq_kivicare_clinic_administrator' => 'yes'
                ],
            ]
        );

        $this->add_control(
            'iq_kivicare_clinic_administrator-label-color',
            [
                'label' => __('Label Color', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivicare_clinic_administrator' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .clinic-administrator .kivicare-clinic-label' => 'color: {{VALUE}};',
                ]
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'iq_kivicare_clinic_administrator-label-typography',
                'label' => __('Label Typography', 'kivicare-clinic-management-system'),
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
                ],
                'selector' => '{{WRAPPER}} .clinic-administrator .kivicare-clinic-label',
                'condition' => [
                    'iq_kivicare_clinic_administrator' => 'yes'
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_clinic_administrator-label-margin',
            [
                'label' => __('Label Margin', 'kivicare-clinic-management-system'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivicare_clinic_administrator' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .clinic-administrator .kivicare-clinic-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'iq_kivicare_clinic_administrator-label-padding',
            [
                'label' => __('Label Padding', 'kivicare-clinic-management-system'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivicare_clinic_administrator' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .clinic-administrator .kivicare-clinic-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'iq_kivicare_clinic_administrator-label-align',
            [
                'label' => __('Label Alignment', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'left' => __('Left', 'kivicare-clinic-management-system'),
                    'center' => __('Center', 'kivicare-clinic-management-system'),
                    'right' => __('Right', 'kivicare-clinic-management-system')
                ],
                'default' => 'left',
                'condition' => [
                    'iq_kivicare_clinic_administrator' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .clinic-administrator .kivicare-clinic-label' => 'text-align: {{VALUE}} !important;',
                ]
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'iq_kivicare_book_appointment_button',
            [
                'label' => __('Appointment Book Button style', 'kivicare-clinic-management-system'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'iq_kivicare_book_appointment_button_color',
            [
                'label' => __('Button Color', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .kivicare-book-appointment-btn' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'iq_kivicare_book_appointment_button_text_color',
            [
                'label' => __('Button Text Color', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .kivicare-book-appointment-btn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'iq_kivicare_book_appointment_button_typography',
                'label' => __('Button Typography', 'kivicare-clinic-management-system'),
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
                ],
                'selector' => '{{WRAPPER}} .kivicare-book-appointment-btn',
            ]
        );

        $this->add_control(
            'iq_kivicare_book_appointment_button_border_radius',
            [
                'label' => __('Button Border Radius', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                    '%' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-book-appointment-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'iq_kivicare_book_appointment_button_padding',
            [
                'label' => __('Button Padding', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-book-appointment-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->end_controls_section();
    }

    protected function commonControl($this_ele, $type, $userType)
    {
        $this_ele->add_control(
            'iq_kivicare_' . $userType . '_' . $type,
            [
                'label' => $type === 'email' ? ucfirst($type) . __(' ID', 'kivicare-clinic-management-system') : ucfirst($type),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'label_off' => __('Hide', 'kivicare-clinic-management-system'),
                'label_on' => __('Show', 'kivicare-clinic-management-system'),
            ]
        );

        switch ($type) {
            case 'name':
            case 'speciality':
                $value = ucfirst($type);
                break;
            case 'email':
                $value = ucfirst($type) . ' ID';
                break;
            case 'number':
            case 'admin_number':
                $value = 'Contact No';
                break;
            case 'admin_email':
                $value = 'Admin ' . ucfirst('email') . ' ID';
                break;
            case 'administrator':
                $value = 'Administrator';
                break;
            default:
                $value = ucfirst($type);
                break;
        }

        $this_ele->add_control(
            'iq_kivicare_' . $userType . '_' . $type . '_label',
            [
                'label' => __('label ', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => $value,
                'condition' => [
                    'iq_kivicare_' . $userType . '_' . $type => 'yes'
                ],
                'dynamic' => [
                    'active' => true,
                ],
            ]
        );
    }

    protected function fontStyleControl($this_ele, $type, $userType)
    {
        $type = $userType === 'clinic' ? $type : $type . '_clinic';
        $this_ele->start_controls_section(
            'iq_kivicare_' . $type . '_style_sections',
            [
                'label' => ucfirst($type) . __(' style', 'kivicare-clinic-management-system'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'iq_kivicare_clinic_' . $type => 'yes'
                ],
            ]
        );

        // Build selectors with higher specificity for admin specific fields so they can
        // override the generic Administrator style when both are set.
        $labelSelector = '{{WRAPPER}} .clinic-' . $type . ' .kivicare-clinic-label';
        if (in_array($type, ['admin_number', 'admin_email'], true)) {
            $labelSelector = '{{WRAPPER}} .clinic-administrator .clinic-' . $type . ' .kivicare-clinic-label';
        }

        $this_ele->add_control(
            'iq_kivicare_clinic_' . $type . '-label-color',
            [
                'label' => __('Label Color', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivicare_clinic_' . $type => 'yes'
                ],
                'selectors' => [
                    $labelSelector => 'color: {{VALUE}};',
                ]
            ]
        );

        $this_ele->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'iq_kivicare_clinic_' . $type . '-label-typography',
                'label' => __('Label Typography', 'kivicare-clinic-management-system'),
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
                ],
                'selector' => $labelSelector,
                'condition' => [
                    'iq_kivicare_clinic_' . $type => 'yes'
                ]
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_clinic_' . $type . '-label-margin',
            [
                'label' => __('Label Margin', 'kivicare-clinic-management-system'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivicare_clinic_' . $type => 'yes'
                ],
                'selectors' => [
                    $labelSelector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_clinic_' . $type . '-label-padding',
            [
                'label' => __('Label Padding', 'kivicare-clinic-management-system'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivicare_clinic_' . $type => 'yes'
                ],
                'selectors' => [
                    $labelSelector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_clinic_' . $type . '-label-align',
            [
                'label' => __('Label Alignment', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'left' => __('Left', 'kivicare-clinic-management-system'),
                    'center' => __('Center', 'kivicare-clinic-management-system'),
                    'right' => __('Right', 'kivicare-clinic-management-system')
                ],
                'default' => 'left',
                'condition' => [
                    'iq_kivicare_clinic_' . $type => 'yes'
                ],
                'selectors' => [
                    $labelSelector => 'text-align: {{VALUE}} !important;',
                ]
            ]
        );

        $valueSelector = '';
        $valueTypographySelector = '';
        if ($type === 'name') {
            $valueSelector = '{{WRAPPER}} .kivicare-clinic-name';
            $valueTypographySelector = '{{WRAPPER}} .kivicare-clinic-name';
        } elseif ($type === 'speciality') {
            $valueSelector = '{{WRAPPER}} .kivicare-clinic-speciality';
            $valueTypographySelector = '{{WRAPPER}} .kivicare-clinic-speciality';
        } else {
            if (in_array($type, ['admin_number', 'admin_email'], true)) {
                $valueSelector = '{{WRAPPER}} .clinic-administrator .clinic-' . $type . ' .kivi-clinic-information-content, {{WRAPPER}} .clinic-administrator .clinic-' . $type . ' .kivicare-clinic-value';
                $valueTypographySelector = '{{WRAPPER}} .clinic-administrator .clinic-' . $type . ' .kivicare-clinic-value';
            } else {
                $valueSelector = '{{WRAPPER}} .clinic-' . $type . ' .kivi-clinic-information-content, {{WRAPPER}} .clinic-' . $type . ' .kivicare-clinic-value';
                $valueTypographySelector = '{{WRAPPER}} .clinic-' . $type . ' .kivicare-clinic-value';
            }
        }

        $this_ele->add_control(
            'iq_kivicare_clinic_' . $type . '-value-color',
            [
                'label' => __('Value Color', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivicare_clinic_' . $type => 'yes'
                ],
                'selectors' => [
                    $valueSelector => 'color: {{VALUE}};',
                ]
            ]
        );

        $this_ele->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'iq_kivicare_clinic_' . $type . '-value-typography',
                'label' => __('Value Typography', 'kivicare-clinic-management-system'),
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
                ],
                'selector' => $valueTypographySelector,
                'condition' => [
                    'iq_kivicare_clinic_' . $type => 'yes'
                ]
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_clinic_' . $type . '-value-margin',
            [
                'label' => __('Value Margin', 'kivicare-clinic-management-system'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivicare_clinic_' . $type => 'yes'
                ],
                'selectors' => [
                    $valueSelector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_clinic_' . $type . '-value-padding',
            [
                'label' => __('Value Padding', 'kivicare-clinic-management-system'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivicare_clinic_' . $type => 'yes'
                ],
                'selectors' => [
                    $valueSelector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_clinic_' . $type . '-value-align',
            [
                'label' => __('Value Alignment', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'left' => __('Left', 'kivicare-clinic-management-system'),
                    'center' => __('Center', 'kivicare-clinic-management-system'),
                    'right' => __('Right', 'kivicare-clinic-management-system')
                ],
                'default' => 'left',
                'condition' => [
                    'iq_kivicare_clinic_' . $type => 'yes'
                ],
                'selectors' => [
                    $valueSelector => 'text-align: {{VALUE}} !important;',
                ]
            ]
        );
        $this->end_controls_section();
    }

    protected function get_clinics_options()
    {
        $clinics = ['' => __('Select Clinic', 'kivicare-clinic-management-system')];
        try {
            $results = KCClinic::table('c')
                ->select([
                    "c.*",
                ])
                ->get();
            if ($results) {
                foreach ($results as $clinic) {
                    $clinics[$clinic->id] = $clinic->name;
                }
            }
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Error getting clinics: ' . $e->getMessage());
        }
        return $clinics;
    }

    public function get_clinics_data($exclude_clinics = [], $per_page = 2)
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

            // Add limit if per_page is set
            if ($per_page > 0) {
                $query->limit($per_page);
            }

            $results = $query->get();


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

                $services = KCServiceDoctorMapping::getServices([
                    'clinic_id' => $result->id,
                    'status' => 1
                ])->pluck('service_name')->unique()->toArray();
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
            KCErrorLogger::instance()->error('KiviCare Clinic List Widget Error: ' . $e->getMessage());
        }
        return $clinics;
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        
        // Ensure settings is an array and provide defaults
        if (!is_array($settings)) {
            $settings = [];
        }
        
        // Set default values for required settings
        $default_settings = [
            'iq_kivicare_clinic_per_page' => 2
        ];
        
        $settings = array_merge($default_settings, $settings);

        $clinics_per_page = $settings['iq_kivicare_clinic_per_page'] ? intval($settings['iq_kivicare_clinic_per_page']) : 2;

        // Load all clinics for frontend pagination
        $clinics = $this->get_clinics_data([], 1000); // Load more clinics for frontend pagination

        if (empty($clinics)) {
            echo '<div class="kivicare-no-clinics">' . esc_html__('No clinics found.', 'kivicare-clinic-management-system') . '</div>';
            return;
        }

        // Prepare booking widget configuration for modal
        $paymentGateways = [];
        try {
            $paymentGateways = KCPaymentGatewayFactory::get_available_gateways(true);
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KiviCare: Error fetching payment gateways - ' . $e->getMessage());
        }
        
        $widgetOrder = KCOption::get('widget_order_list', []);

        $booking_data_attrs = [
            'data-widget-order' => esc_attr(wp_json_encode($widgetOrder)),
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

        // Widget container
        $containerId = 'kivicare-clinic-list-container-' . uniqid();
        ?>
        <div id="<?php echo esc_attr($containerId); ?>"
             class="kivicare-clinic-list-container" <?php echo $data_attrs_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="kivicare-loading"><?php esc_html_e('Loading clinics...', 'kivicare-clinic-management-system'); ?></div>
        </div>
        <?php
    }

    protected function _content_template() {}
    
    protected function isWidgetPresent() {
        return true;
    }
}