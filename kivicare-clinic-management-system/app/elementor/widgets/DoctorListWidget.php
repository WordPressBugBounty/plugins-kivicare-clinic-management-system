<?php

namespace App\elementor\widgets;

use App\baseClasses\KCErrorLogger;
use App\models\KCUserMeta;

if (!defined('ABSPATH')) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use App\abstracts\KCElementorWidgetAbstract;
use App\models\KCDoctor;
use App\models\KCClinic;
use App\models\KCClinicSession;
use App\models\KCDoctorClinicMapping;
use App\models\KCOption;
use App\models\KCServiceDoctorMapping;
use App\baseClasses\KCPaymentGatewayFactory;
use function Iqonic\Vite\iqonic_enqueue_asset;


class DoctorListWidget extends KCElementorWidgetAbstract
{
    /**
     * Asset management properties
     */
    protected $assets_dir = KIVI_CARE_DIR . '/dist';
    protected $js_entry = 'app/elementor/widgets/js/DoctorWidget.jsx';
    protected $script_dependencies = ['jquery'];
    protected $css_entry = 'app/elementor/widgets/scss/ElementorWidget.scss';

   
    protected $in_footer = true;

    public function get_name()
    {
        return 'kivicare_doctor_list';
    }

    public function get_title()
    {
        return __('KiviCare Doctor List', 'kivicare-clinic-management-system');
    }

    public function get_icon()
    {
        return 'eicon-user-circle-o';
    }

    public function get_categories()
    {
        return ['kivicare'];
    }

    public function get_keywords()
    {
        return ['doctor', 'list', 'kivicare'];
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
                KCErrorLogger::instance()->error('DoctorListWidget: Failed to enqueue BookAppointment assets: ' . $e->getMessage());
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
            'iq_kivicare_clinic_wise_doctor_section_shortcode',
            [
                'label' => __('Kivicare Clinic Wise Doctor', 'kivicare-clinic-management-system'),
            ]
        );

        $clinics = $this->get_clinics_options();
        $first_clinic_id = array_key_first(array_filter($clinics, function ($key) {
            return $key !== '';
        }, ARRAY_FILTER_USE_KEY));

        $this->add_control(
            'iq_kivicare_doctor_clinic_id',
            [
                'label' => __('Select Clinic', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $clinics,
                'default' => $first_clinic_id,
                'dynamic' => [
                    'active' => true,
                ],
                'label_block' => true
            ]
        );

        $this->add_control(
            'iq_kivicare_specific_doctor',
            [
                'label' => __('Specific Doctor add', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'no',
                'dynamic' => [
                    'active' => true,
                ],
                'label_off' => __('Hide', 'kivicare-clinic-management-system'),
                'label_on' => __('Show', 'kivicare-clinic-management-system'),
            ]
        );

        $doctors = $this->get_doctors_options();
        $first_doctor_id = array_key_first(array_filter($doctors, function ($key) {
            return $key !== '';
        }, ARRAY_FILTER_USE_KEY));

        $this->add_control(
            'selected_doctors',
            [
                'label' => __('Select Doctors', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $doctors,
                'default' => [$first_doctor_id],
                'label_block' => false,
                'dynamic' => [
                    'active' => true,
                ],
                'condition' => [
                    'iq_kivicare_specific_doctor' => 'yes'
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_enable_filter',
            [
                'label' => __('Enable Filter', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'label_off' => __('Hide', 'kivicare-clinic-management-system'),
                'label_on' => __('Show', 'kivicare-clinic-management-system'),
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_par_page',
            [
                'label' => __('Doctor per page ', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 5,
                'min' => 0
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_gap_between_card',
            [
                'label' => __('Hide Space Between Doctors', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_off' => __('Hide', 'kivicare-clinic-management-system'),
                'label_on' => __('Show', 'kivicare-clinic-management-system'),
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_image',
            [
                'label' => __('Profile Image', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'label_off' => __('Hide', 'kivicare-clinic-management-system'),
                'label_on' => __('Show', 'kivicare-clinic-management-system'),
            ]
        );

        $this->commonControl($this, 'name', 'doctor');
        $this->commonControl($this, 'speciality', 'doctor');
        $this->commonControl($this, 'number', 'doctor');
        $this->commonControl($this, 'email', 'doctor');
        $this->commonControl($this, 'qualification', 'doctor');
        $this->commonControl($this, 'session', 'doctor');
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
                'selector' => '{{WRAPPER}} .kivi-doctor',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'iq_card_box_shadow',
                'label' => __('Card Box Shadow', 'kivicare-clinic-management-system'),
                'selector' => '{{WRAPPER}} .kivi-doctor',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'iq_card_border',
                'label' => __('Card Border', 'kivicare-clinic-management-system'),
                'selector' => '{{WRAPPER}} .kivi-doctor',
            ]
        );

        $this->add_control(
            'iq_card_border_radius',
            [
                'label' => __('Card Border Radius', 'kivicare-clinic-management-system'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .kivi-doctor' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};overflow:hidden;',
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
                    'iq_kivicare_doctor_image' => 'yes'
                ],
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_image_height',
            [
                'label' => __('Image Height', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivicare_doctor_image' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .doctor-image' => 'height: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_image_width',
            [
                'label' => __('Image width', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivicare_doctor_image' => 'yes'
                ],
                'min' => 0,
                'selectors' => [
                    '{{WRAPPER}} .doctor-image' => 'width: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_image_border',
            [
                'label' => __('Image Border', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivicare_doctor_image' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .doctor-image' => 'border-width: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_image_border_radius',
            [
                'label' => __('Image Border Radius', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivicare_doctor_image' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .doctor-image' => 'border-radius: {{VALUE}}%;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_image_border_style',
            [
                'label' => __('Image Border style', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'condition' => [
                    'iq_kivicare_doctor_image' => 'yes'
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
                    '{{WRAPPER}} .doctor-image' => 'border-style: {{VALUE}};',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_image_border_color',
            [
                'label' => __('Image Border Color', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivicare_doctor_image' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .doctor-image' => 'border-color: {{VALUE}};',
                ]
            ]
        );
        $this->end_controls_section();

        $userType = 'kivicare_doctor';
        $this->fontStyleControl($this, 'name', $userType);
        $this->fontStyleControl($this, 'speciality', $userType);
        $this->fontStyleControl($this, 'number', $userType);
        $this->fontStyleControl($this, 'email', $userType);
        $this->fontStyleControl($this, 'qualification', $userType);
        $this->fontStyleControl($this, 'session', $userType);

        $this->start_controls_section(
            'iq_kivicare_session_container_style_sections',
            [
                'label' => __('Session Container style', 'kivicare-clinic-management-system'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'iq_kivicare_doctor_session' => 'yes'
                ],
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_container_height',
            [
                'label' => __('Container Height', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivicare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-session-grid' => 'height: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_cell_height',
            [
                'label' => __('Cell Height', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivicare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-session-item' => 'height: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_cell_width',
            [
                'label' => __('Cell width', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivicare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-session-item' => 'width: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'iq_kivicare_doctor_session_cell_background',
                'label' => __('Cell Background', 'kivicare-clinic-management-system'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .kivicare-session-item',
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_cell_border',
            [
                'label' => __('Cell Border', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivicare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-session-item' => 'border-width: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_cell_border_radius',
            [
                'label' => __('Cell Border Radius', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivicare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-session-item' => 'border-radius: {{VALUE}}%;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_cell_border_style',
            [
                'label' => __('Cell Border style', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'condition' => [
                    'iq_kivicare_doctor_session' => 'yes'
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
                    '{{WRAPPER}} .kivicare-session-item' => 'border-style: {{VALUE}};',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_cell_border_color',
            [
                'label' => __('Cell Border Color', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivicare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-session-item' => 'border-color: {{VALUE}};',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_font_title_color',
            [
                'label' => __('Title Font Color', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivicare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-session-day' => 'color: {{VALUE}};',
                ]
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'iq_kivicare_doctor_session_font_title_typography',
                'label' => __('Title Font Typography', 'kivicare-clinic-management-system'),
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
                ],
                'selector' => '{{WRAPPER}} .kivicare-session-day',
                'condition' => [
                    'iq_kivicare_doctor_session' => 'yes'
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_font_value_color',
            [
                'label' => __('Font Color', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivicare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-session-time, {{WRAPPER}} .kivicare-session-time-slot' => 'color: {{VALUE}};',
                ]
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'iq_kivicare_doctor_session_font_value_typography',
                'label' => __('Font Typography', 'kivicare-clinic-management-system'),
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
                ],
                'selector' => '{{WRAPPER}} .kivicare-session-time, {{WRAPPER}} .kivicare-session-time-slot',
                'condition' => [
                    'iq_kivicare_doctor_session' => 'yes'
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
                $value = 'Name';
                break;
            case 'speciality':
                $value = 'Speciality';
                break;
            case 'email':
                $value = ucfirst($type) . ' ID';
                break;
            case 'session':
                $value = 'Schedule Appointment';
                break;
            case 'number':
                $value = 'Contact No';
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
        $type = $userType === 'kivicare_doctor' ? $type : $type . '_clinic';
        $this_ele->start_controls_section(
            'iq_kivicare_' . $type . '_style_sections',
            [
                'label' => ucfirst($type) . __(' style', 'kivicare-clinic-management-system'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'iq_kivicare_doctor_' . $type => 'yes'
                ],
            ]
        );

		// Add label style controls for all types, including 'name' and 'speciality'
		$this_ele->add_control(
			'iq_kivicare_doctor_' . $type . '-label-color',
			[
				'label' => __('Label Color', 'kivicare-clinic-management-system'),
				'type' => \Elementor\Controls_Manager::COLOR,
				'condition' => [
					'iq_kivicare_doctor_' . $type => 'yes'
				],
				'selectors' => [
					'{{WRAPPER}} .doctor-' . $type . ' .kivicare-doctor-label' => 'color: {{VALUE}};',
				]
			]
		);

		$this_ele->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'iq_kivicare_doctor_' . $type . '-label-typography',
				'label' => __('Label Typography', 'kivicare-clinic-management-system'),
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector' => '{{WRAPPER}} .doctor-' . $type . ' .kivicare-doctor-label',
				'condition' => [
					'iq_kivicare_doctor_' . $type => 'yes'
				]
			]
		);

		$this_ele->add_control(
			'iq_kivicare_doctor_' . $type . '-label-margin',
			[
				'label' => __('Label Margin', 'kivicare-clinic-management-system'),
				'size_units' => ['px', '%', 'em'],
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'condition' => [
					'iq_kivicare_doctor_' . $type => 'yes'
				],
				'selectors' => [
					'{{WRAPPER}} .doctor-' . $type . ' .kivicare-doctor-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this_ele->add_control(
			'iq_kivicare_doctor_' . $type . '-label-padding',
			[
				'label' => __('Label Padding', 'kivicare-clinic-management-system'),
				'size_units' => ['px', '%', 'em'],
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'condition' => [
					'iq_kivicare_doctor_' . $type => 'yes'
				],
				'selectors' => [
					'{{WRAPPER}} .doctor-' . $type . ' .kivicare-doctor-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this_ele->add_control(
			'iq_kivicare_doctor_' . $type . '-label-align',
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
					'iq_kivicare_doctor_' . $type => 'yes'
				],
				'selectors' => [
					'{{WRAPPER}} .doctor-' . $type . ' .kivicare-doctor-label' => 'text-align: {{VALUE}};',
				]
			]
		);

        $valueSelector = '';
        $valueTypographySelector = '';
        if ($type === 'name') {
            $valueSelector = '{{WRAPPER}} .kivicare-doctor-name';
            $valueTypographySelector = '{{WRAPPER}} .kivicare-doctor-name';
        } elseif ($type === 'speciality') {
            $valueSelector = '{{WRAPPER}} .kivicare-doctor-speciality';
            $valueTypographySelector = '{{WRAPPER}} .kivicare-doctor-speciality';
        } elseif ($type === 'session') {
            $valueSelector = '{{WRAPPER}} .doctor-session .kivi-appointment-item, {{WRAPPER}} .doctor-session .kivicare-session-day, {{WRAPPER}} .doctor-session .kivicare-session-time, {{WRAPPER}} .doctor-session .kivicare-session-time-slot';
            $valueTypographySelector = '{{WRAPPER}} .doctor-session .kivicare-session-day, {{WRAPPER}} .doctor-session .kivicare-session-time, {{WRAPPER}} .doctor-session .kivicare-session-time-slot';
        } else {
            $valueSelector = '{{WRAPPER}} .doctor-' . $type . ' .kivi-doctor-information-content, {{WRAPPER}} .doctor-' . $type . ' .kivicare-doctor-value';
            $valueTypographySelector = '{{WRAPPER}} .doctor-' . $type . ' .kivicare-doctor-value';
        }

        $this_ele->add_control(
            'iq_kivicare_doctor_' . $type . '-value-color',
            [
                'label' => __('Value Color', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivicare_doctor_' . $type => 'yes'
                ],
                'selectors' => [
                    // Force override so day/time in sessions also follow Value Color
                    $valueSelector => 'color: {{VALUE}} !important;',
                ]
            ]
        );

        $this_ele->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'iq_kivicare_doctor_' . $type . '-value-typography',
                'label' => __('Value Typography', 'kivicare-clinic-management-system'),
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
                ],
                'selector' => $valueTypographySelector,
                'condition' => [
                    'iq_kivicare_doctor_' . $type => 'yes'
                ]
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_' . $type . '-value-margin',
            [
                'label' => __('Value Margin', 'kivicare-clinic-management-system'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivicare_doctor_' . $type => 'yes'
                ],
                'selectors' => [
                    $valueSelector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_' . $type . '-value-padding',
            [
                'label' => __('Value Padding', 'kivicare-clinic-management-system'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivicare_doctor_' . $type => 'yes'
                ],
                'selectors' => [
                    $valueSelector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_' . $type . '-value-align',
            [
                'label' => __('Value Alignment', 'kivicare-clinic-management-system'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'left' => __('Left', 'kivicare-clinic-management-system'),
                    'center' => __('Center', 'kivicare-clinic-management-system'),
                    'right' => __('Right', 'kivicare-clinic-management-system')
                ],
                'condition' => [
                    'iq_kivicare_doctor_' . $type => 'yes'
                ],
                'default' => $type == 'session' ? 'center' : 'left',
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

    protected function get_doctors_options()
    {
        $doctors = ['' => __('Select Doctor', 'kivicare-clinic-management-system')];
        try {
            $results = KCDoctor::table('d')
                ->select(['ID as id', 'display_name as name'])
                ->get();
            if ($results->isNotEmpty()) {
                foreach ($results as $doctor) {
                    $doctors[$doctor->id] = $doctor->name;
                }
            }
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Error getting doctors: ' . $e->getMessage());
        }
        return $doctors;
    }

    public function get_doctors_data($clinic_id = '', $specific_doctors = [], $per_page = 5)
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

            // Add clinic filter if specified
            if (!empty($clinic_id)) {
                $query->join(KCDoctorClinicMapping::class, 'd.ID', '=', 'dcm.doctor_id', 'dcm')
                    ->where('dcm.clinic_id', '=', $clinic_id);
            }

            // Add specific doctors filter
            if (!empty($specific_doctors)) {
                $query->whereIn('d.ID', $specific_doctors);
            }

            // Group by doctor ID to avoid duplicates
            $query->groupBy('d.ID');

            // Execute query
            $results = $query->get();

            // Format doctor data
            foreach ($results as $result) {
                $basic_data = json_decode($result->basic_data, true) ?? [];

                // Resolve doctor id robustly
                $doctorId = null;
                if (!empty($result->id)) {
                    $doctorId = (int) $result->id;
                } elseif (!empty($result->ID)) {
                    $doctorId = (int) $result->ID;
                }
                if (empty($doctorId)) {
                    KCErrorLogger::instance()->error('KiviCare Doctor List Widget: Missing doctor ID for result, skipping.');
                    continue;
                }

                // Handle qualifications
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

                // Prefer WordPress user's email; fall back to email stored in basic_data if needed
                $emailAddress = !empty($result->email)
                    ? $result->email
                    : ($basic_data['email'] ?? '');

                // Get services from database
                // fix: use ->values() before ->toArray() to ensure service data maintains standard indexed arrays
                $services = KCServiceDoctorMapping::getActiveDoctorServices($doctorId, (int) $clinic_id)
                    ->pluck('service_name')
                    ->values()
                    ->toArray();

                $doctor_data = [
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
                
                $doctors[] = $doctor_data;
            }
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KiviCare Doctor List Widget Error: ' . $e->getMessage());
        }
        return $doctors;
    }

    protected function get_doctor_sessions($doctor_id, $clinic_id = null)
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
                // Group sessions by day
                foreach ($sessions as $session) {
                    $day = ucfirst(substr($session->day, 0, 3));
                    $time_slot = gmdate('g:i A', strtotime($session->startTime)) . ' - ' .
                        gmdate('g:i A', strtotime($session->endTime));
                    
                    if (!isset($grouped_sessions[$day])) {
                        $grouped_sessions[$day] = [];
                    }
                    
                    $grouped_sessions[$day][] = $time_slot;
                }

                // Convert grouped sessions to the format expected by the frontend
                $formatted_sessions = [];
                foreach ($grouped_sessions as $day => $time_slots) {
                    // Always group sessions by day, combining multiple time slots
                    $formatted_sessions[] = [
                        'day' => $day,
                        'time' => implode(', ', $time_slots), // Combine multiple time slots with comma
                        'timeSlots' => $time_slots, // Keep individual slots for frontend processing
                        'isMultiple' => count($time_slots) > 1
                    ];
                }
            }

            return $formatted_sessions;
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('Error getting doctor sessions: ' . $e->getMessage());
            return [];
        }
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $clinic_id = $settings['iq_kivicare_doctor_clinic_id'];
        $specific_doctors = [];
        if ($settings['iq_kivicare_specific_doctor'] === 'yes' && !empty($settings['selected_doctors'])) {
            $specific_doctors = $settings['selected_doctors'];
        }
        $doctors_per_page = $settings['iq_kivicare_doctor_par_page'] ? intval($settings['iq_kivicare_doctor_par_page']) : 5;
        // Load all doctors for frontend pagination
        $doctors = $this->get_doctors_data($clinic_id, $specific_doctors, 1000); // Load more doctors for frontend pagination

        if (empty($doctors)) {
            echo '<div class="kivicare-no-doctors">' . esc_html__('No doctors found.', 'kivicare-clinic-management-system') . '</div>';
            return;
        }

        // Prepare booking widget configuration for modal
        $widgetSettings = KCOption::get('widgetSetting');  $widgetOrder = KCOption::get('widget_order_list', []);$paymentGateways = [];
        try {
            $paymentGateways = KCPaymentGatewayFactory::get_available_gateways(true);
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KiviCare: Error fetching payment gateways - ' . $e->getMessage());
        }

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

        // Widget container
        $containerId = 'kivicare-doctor-list-container-' . uniqid();
        ?>
        <div id="<?php echo esc_attr($containerId); ?>"
             class="kivicare-doctor-list-container" <?php echo $data_attrs_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="kivicare-loading"><?php esc_html_e('Loading doctors...', 'kivicare-clinic-management-system'); ?></div>
        </div>
        <?php
    }

    protected function _content_template() {}
    
    protected function isWidgetPresent() {
        return true;
    }
}