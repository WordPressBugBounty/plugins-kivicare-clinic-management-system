<?php

use App\baseClasses\KCBase;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

class KCElementorClinicWiseDoctor extends \Elementor\Widget_Base
{

    public function __construct($data = [], $args = null)
    {
        wp_register_style('kc_elementor', KIVI_CARE_DIR_URI . 'assets/css/kcElementor.css', array(), KIVI_CARE_VERSION);
        wp_register_script('kc_axios', KIVI_CARE_DIR_URI . 'assets/js/axios.min.js', ['jquery'], KIVI_CARE_VERSION, true);
        wp_register_script('kc_flatpicker', KIVI_CARE_DIR_URI . 'assets/js/flatpicker.min.js', [], KIVI_CARE_VERSION, true);
        wp_register_style('kc_book_appointment', KIVI_CARE_DIR_URI . 'assets/css/book-appointment.css', [], KIVI_CARE_VERSION, false);
        wp_register_style('kc_flatpicker', KIVI_CARE_DIR_URI . 'assets/css/flatpicker.min.css', [], KIVI_CARE_VERSION, false);
        wp_register_script('kc_print', KIVI_CARE_DIR_URI . 'assets/js/jquery-print.min.js', ['jquery'], KIVI_CARE_VERSION, true);
        wp_register_script('kc_calendar', KIVI_CARE_DIR_URI . 'assets/js/calendar.min.js', [], KIVI_CARE_VERSION, true);
        wp_register_style('kc_calendar', KIVI_CARE_DIR_URI . 'assets/css/calendar.min.css', [], KIVI_CARE_VERSION, false);
        wp_register_style('kc_popup', KIVI_CARE_DIR_URI . 'assets/css/magnific-popup.min.css', [], KIVI_CARE_VERSION, false);
        wp_register_script('kc_popup', KIVI_CARE_DIR_URI . 'assets/js/magnific-popup.min.js', ['jquery'], KIVI_CARE_VERSION, true);
        wp_register_script('kc_bookappointment_widget', KIVI_CARE_DIR_URI . 'assets/js/book-appointment-widget.js', ['jquery'], KIVI_CARE_VERSION, true);
        wp_enqueue_script('country-code-select2-js', plugins_url('kivicare-clinic-management-system/assets/js/select2.min.js'), ['jquery'], KIVI_CARE_VERSION, true);
        if( !(isset($_REQUEST['action']) && $_REQUEST['action'] =='elementor') ){
            wp_enqueue_style('country-code-select2-css', plugins_url('kivicare-clinic-management-system/assets/css/select2.min.css'), array(), KIVI_CARE_VERSION, false);
        }
        if (kcGoogleCaptchaData('status') === 'on') {
            $siteKey = kcGoogleCaptchaData('site_key');
            if (empty($siteKey) && empty(kcGoogleCaptchaData('secret_key'))) {
                echo esc_html__('Google Recaptcha Data Not found', 'kc-lang');
                return ob_get_clean();
            }
            wp_enqueue_script('kc-book-appointment-recaptcha', "https://www.google.com/recaptcha/api.js?render={$siteKey}", ['jquery'], KIVI_CARE_VERSION, true);
        }
        parent::__construct($data, $args);
    }

    public function get_style_depends()
    {
        $temp = ['kc_elementor', 'kc_popup', 'kc_book_appointment', 'kc_flatpicker'];
        if (isKiviCareProActive()) {
            array_push($temp, 'kc_calendar');
        }
        return $temp;
    }
    public function get_script_depends()
    {
        $temp = ['kc_popup', 'kc_axios', 'kc_flatpicker', 'kc_bookappointment_widget'];
        if (kcGetSingleWidgetSetting('widget_print')) {
            array_push($temp, 'kc_print');
        }
        if (isKiviCareProActive()) {
            array_push($temp, 'kc_calendar');
        }
        return $temp;
    }
    public function get_name()
    {
        return 'kivicare-clinic-wise-doctor';
    }

    public function get_title()
    {
        return __('KiviCare Doctor List', 'kc-lang');
    }

    public function get_icon()
    {
        return 'fa fa-user-md';
    }

    public function get_categories()
    {
        return ['kivicare-widget-category'];
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'iq_kivicare_clinic_wise_doctor_section_shortcode',
            [
                'label' => __('Kivicare Clinic Wise Doctor', 'kc-lang'),
            ]
        );

        $this->add_control(
            'iq_kivivare_doctor_clinic_id',
            [
                'label' => __('Select Clinic', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => kcClinicForElementor('all'),
                'default' => kcClinicForElementor('first'),
                'dynamic' => [
                    'active' => true,
                ],
                'label_block' => true
            ]
        );

        $this->add_control(
            'iq_kivivare_specific_doctor',
            [
                'label' => esc_html__('Specific Doctor add', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'no',
                'dynamic' => [
                    'active' => true,
                ],
                'label_off' => esc_html__('Hide', 'elementor'),
                'label_on' => esc_html__('Show', 'elementor'),
            ]
        );


        $this->add_control(
            'selected_doctors',
            [
                'label' => __('Select Doctors', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => kcDoctorForElementor('all'),
                'default' => kcDoctorForElementor('first'),
                'label_block' => false,
                'dynamic' => [
                    'active' => true,
                ],
                'condition' => [
                    'iq_kivivare_specific_doctor' => 'yes'
                ]
            ]
        );

        $this->add_control(
            'iq_kivivare_doctor_enable_filter',
            [
                'label' => esc_html__('Enable Filter', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'label_off' => esc_html__('Hide', 'elementor'),
                'label_on' => esc_html__('Show', 'elementor'),
            ]
        );

        $this->add_control(
            'iq_kivivare_doctor_par_page',
            [
                'label' => __('Doctor per page ', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 5,
                'min' => 0
            ]
        );

        $this->add_control(
            'iq_kivivare_doctor_gap_between_card',
            [
                'label' => esc_html__('Hide Space Between Doctors', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_off' => esc_html__('Hide', 'elementor'),
                'label_on' => esc_html__('Show', 'elementor'),
            ]
        );

        $this->add_control(
            'iq_kivivare_doctor_image',
            [
                'label' => esc_html__('Profile Image', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'label_off' => esc_html__('Hide', 'elementor'),
                'label_on' => esc_html__('Show', 'elementor'),
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
                'label' => esc_html__('Card style', 'kc-lang'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'iq_card_background',
                'label' => esc_html__('Card Background', 'kc-lang'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .kivicare-doctor-card',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'iq_card_box_shadow',
                'label' => esc_html__('Card Box Shadow', 'kc-lang'),
                'selector' => '{{WRAPPER}} .kivicare-doctor-card',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'iq_card_border',
                'label' => esc_html__('Card Border', 'kc-lang'),
                'selector' => '{{WRAPPER}} .kivicare-doctor-card',
            ]
        );

        $this->add_control(
            'iq_card_border_radius',
            [
                'label' => esc_html__('Card Border Radius', 'kc-lang'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .kivicare-doctor-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};overflow:hidden;',
                ],
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_label_color',
            [
                'label' => esc_html__('Left Side Color', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .column-doctor::before' => 'background-color: {{VALUE}};',
                ]
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'iq_kivicare_image_style_sections',
            [
                'label' => esc_html__('Image style', 'kc-lang'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'iq_kivivare_doctor_image' => 'yes'
                ],
            ]
        );


        $this->add_control(
            'iq_kivicare_doctor_image_height',
            [
                'label' => esc_html__('Image Height', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivivare_doctor_image' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-doctor-avtar' => 'height: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_image_width',
            [
                'label' => esc_html__('Image width', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivivare_doctor_image' => 'yes'
                ],
                'min' => 0,
                'selectors' => [
                    '{{WRAPPER}} .kivicare-doctor-avtar' => 'width: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_image_border',
            [
                'label' => esc_html__('Image Border', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivivare_doctor_image' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-doctor-avtar' => 'border: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_image_border_radius',
            [
                'label' => esc_html__('Image Border Radius', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivivare_doctor_image' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-doctor-avtar' => 'border-radius: {{VALUE}}%;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_image_border_style',
            [
                'label' => esc_html__('Image Border style', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'condition' => [
                    'iq_kivivare_doctor_image' => 'yes'
                ],
                'options' => [
                    'solid' => esc_html__('solid', 'kc-lang'),
                    'dashed' => esc_html__('dashed', 'kc-lang'),
                    'dotted' => esc_html__('dotted', 'kc-lang'),
                    'double' => esc_html__('double', 'kc-lang'),
                    'groove' => esc_html__('groove', 'kc-lang'),
                    'ridge' => esc_html__('ridge', 'kc-lang'),
                    'inset' => esc_html__('inset', 'kc-lang'),
                    'outset' => esc_html__('outset', 'kc-lang'),
                    'none' => esc_html__('none', 'kc-lang'),
                    'hidden' => esc_html__('hidden', 'kc-lang'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-doctor-avtar' => 'border-style: {{VALUE}};',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_image_border_color',
            [
                'label' => esc_html__('Image Border Color', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivivare_doctor_image' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-doctor-avtar' => 'border-color: {{VALUE}};',
                ]
            ]
        );

        $this->end_controls_section();
        $userType = 'doctor';
        $this->fontStyleControl($this, 'name', $userType);
        $this->fontStyleControl($this, 'speciality', $userType);
        $this->fontStyleControl($this, 'number', $userType);
        $this->fontStyleControl($this, 'email', $userType);
        $this->fontStyleControl($this, 'qualification', $userType);
        $this->fontStyleControl($this, 'session', $userType);

        $this->start_controls_section(
            'iq_kivicare_session_container_style_sections',
            [
                'label' => esc_html__('Session Container style', 'kc-lang'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'iq_kivivare_doctor_session' => 'yes'
                ],
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_container_height',
            [
                'label' => esc_html__('Container Height', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivivare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_session-container' => 'height: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_cell_height',
            [
                'label' => esc_html__('Cell Height', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivivare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_session-cell' => 'height: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_cell_width',
            [
                'label' => esc_html__('Cell width', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivivare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_session-cell' => 'width: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'iq_kivicare_doctor_session_cell_background',
                'label' => esc_html__('Cell Background', 'kc-lang'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .iq_kivicare_doctor_session-cell',
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_cell_border',
            [
                'label' => esc_html__('Cell Border', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivivare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_session-cell' => 'border: {{VALUE}}px;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_cell_border_radius',
            [
                'label' => esc_html__('Cell Border Radius', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'iq_kivivare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_session-cell' => 'border-radius: {{VALUE}}%;',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_cell_border_style',
            [
                'label' => esc_html__('Cell Border style', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'condition' => [
                    'iq_kivivare_doctor_session' => 'yes'
                ],
                'options' => [
                    'solid' => esc_html__('solid', 'kc-lang'),
                    'dashed' => esc_html__('dashed', 'kc-lang'),
                    'dotted' => esc_html__('dotted', 'kc-lang'),
                    'double' => esc_html__('double', 'kc-lang'),
                    'groove' => esc_html__('groove', 'kc-lang'),
                    'ridge' => esc_html__('ridge', 'kc-lang'),
                    'inset' => esc_html__('inset', 'kc-lang'),
                    'outset' => esc_html__('outset', 'kc-lang'),
                    'none' => esc_html__('none', 'kc-lang'),
                    'hidden' => esc_html__('hidden', 'kc-lang'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_session-cell' => 'border-style: {{VALUE}};',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_cell_border_color',
            [
                'label' => esc_html__('Cell Border Color', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivivare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_session-cell' => 'border-color: {{VALUE}};',
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_font_title_color',
            [
                'label' => esc_html__('Title Font Color', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivivare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_session-cell_title' => 'color: {{VALUE}};',
                ]
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'iq_kivicare_doctor_session_font_title_typography',
                'label' => esc_html__('Title Font Typography', 'kc-lang'),
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
            	],
                'selector' => '{{WRAPPER}} .iq_kivicare_doctor_session-cell_title',
                'condition' => [
                    'iq_kivivare_doctor_session' => 'yes'
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_session_font_value_color',
            [
                'label' => esc_html__('Font Color', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivivare_doctor_session' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_session-cell_value' => 'color: {{VALUE}};',
                ]
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'iq_kivicare_doctor_session_font_value_typography',
                'label' => esc_html__('Font Typography', 'kc-lang'),
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
            	],
                'selector' => '{{WRAPPER}} .iq_kivicare_doctor_session-cell_value',
                'condition' => [
                    'iq_kivivare_doctor_session' => 'yes'
                ]
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'iq_kivicare_book_appointment_button',
            [
                'label' => esc_html__('Appointment Book Button style', 'kc-lang'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        kcElementorAllCommonController($this, 'clinic_doctor');

        $this->end_controls_section();

    }

    protected function commonControl($this_ele, $type, $userType)
    {
        $this_ele->add_control(
            'iq_kivivare_' . $userType . '_' . $type,
            [
                /*Translator: Email text */
                'label' => $type === 'email' ? ucfirst($type) . __(' ID', 'kc-lang') : ucfirst($type),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'label_off' => esc_html__('Hide', 'elementor'),
                'label_on' => esc_html__('Show', 'elementor'),
            ]
        );

        switch ($type) {
            case 'name':
            case 'speciality':
                $value = '';
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
            'iq_kivivare_' . $userType . '_' . $type . '_label',
            [
                'label' => esc_html__('label ', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => $value,
                'condition' => [
                    'iq_kivivare_' . $userType . '_' . $type => 'yes'
                ],
                'dynamic' => [
                    'active' => true,
                ],
            ]
        );

    }

    protected function fontStyleControl($this_ele, $type, $userType)
    {
        $type = $userType === 'doctor' ? $type : $type . '_clinic';
        $this_ele->start_controls_section(
            'iq_kivicare_' . $type . '_style_sections',
            [
                /*Translator: label text */
                'label' => ucfirst($type) . __(' style', 'kc-lang'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'iq_kivivare_doctor_' . $type => 'yes'
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_' . $type . '-label-color',
            [
                'label' => esc_html__('Label Color', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivivare_doctor_' . $type => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-label' => 'color: {{VALUE}};',
                ]
            ]
        );

        $this_ele->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'iq_kivicare_doctor_' . $type . '-label-typography',
                'label' => esc_html__('Label Typography', 'kc-lang'),
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
            	],
                'selector' => '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-label',
                'condition' => [
                    'iq_kivivare_doctor_' . $type => 'yes'
                ]
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_' . $type . '-label-margin',
            [
                'label' => esc_html__('Label Margin', 'kc-lang'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivivare_doctor_' . $type => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_' . $type . '-label-padding',
            [
                'label' => esc_html__('Label Padding', 'kc-lang'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivivare_doctor_' . $type => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_' . $type . '-label-align',
            [
                'label' => esc_html__('Label Alignment', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'left' => esc_html__('Left', 'kc-lang'),
                    'center' => esc_html__('Center', 'kc-lang'),
                    'right' => esc_html__('Right', 'kc-lang')
                ],
                'condition' => [
                    'iq_kivivare_doctor_' . $type => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-label' => 'text-align: {{VALUE}};',
                    '{{WRAPPER}} .appoin' => 'text-align: {{VALUE}};'
                ]
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_' . $type . '-value-color',
            [
                'label' => esc_html__('Value Color', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'iq_kivivare_doctor_' . $type => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-value' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-cell_title' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-cell_value' => 'color: {{VALUE}};',
                ]
            ]
        );

        $this_ele->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'iq_kivicare_doctor_' . $type . '-value-typography',
                'label' => esc_html__('Value Typography', 'kc-lang'),
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
            	],
                'selector' => '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-value ,iq_kivicare_doctor_' . $type . '-cell_value, iq_kivicare_doctor_' . $type . '-cell_title',
                'condition' => [
                    'iq_kivivare_doctor_' . $type => 'yes'
                ]
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_' . $type . '-value-margin',
            [
                'label' => esc_html__('Value Margin', 'kc-lang'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivivare_doctor_' . $type => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-value' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-cell' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_' . $type . '-value-padding',
            [
                'label' => esc_html__('Value Padding', 'kc-lang'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivivare_doctor_' . $type => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-value' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-cell' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_' . $type . '-value-align',
            [
                'label' => esc_html__('Value Alignment', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'left' => esc_html__('Left', 'kc-lang'),
                    'center' => esc_html__('Center', 'kc-lang'),
                    'right' => esc_html__('Right', 'kc-lang')
                ],
                'condition' => [
                    'iq_kivivare_doctor_' . $type => 'yes'
                ],
                'default' => $type == 'session' ? 'center' : 'left',
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-value' => 'text-align: {{VALUE}};',
                    '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-cell_title' => 'text-align: {{VALUE}};',
                    '{{WRAPPER}} .iq_kivicare_doctor_' . $type . '-cell_value' => 'text-align: {{VALUE}};',
                ]
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $setting = $this->get_settings_for_display();
        ?>
        <?php
        if (!empty($setting['iq_kivivare_doctor_id']) && $setting['iq_kivivare_doctor_id'] == 'default') {
            ?>
            <div class="elementor-shortcode"> <?php echo esc_html__('No Doctor Found', 'kc-lang'); ?></div>
            <?php
        } else {
            $theme_mode = get_option(KIVI_CARE_PREFIX . 'theme_mode');
            $rtl_attr = in_array($theme_mode, ['1', 'true']) ? 'rtl' : '';
            ?>
            <div dir='<?php echo esc_html($rtl_attr); ?>' id="kivicare-doctor-id-main">
                <?php
                if ($setting['iq_kivivare_doctor_enable_filter'] == 'yes') {
                    global $wpdb;
                    $clinic_condition = " ";
                    if (!empty($setting['iq_kivivare_doctor_clinic_id'])) {
                        $clinic_id = (int) $setting['iq_kivivare_doctor_clinic_id'];
                        $clinic_condition = "  AND ser_map.clinic_id={$clinic_id} ";
                    }
                    $service_list = $wpdb->get_results("SELECT ser.name,ser_map.clinic_id, CONCAT(ser.name,'(',REPLACE(ser.type,'_',' '),')') AS service_name FROM {$wpdb->prefix}kc_services AS ser 
                        JOIN {$wpdb->prefix}kc_service_doctor_mapping AS ser_map ON ser_map.service_id = ser.id WHERE ser.status = 1 AND ser_map.status = 1 {$clinic_condition}");
                    $service_list = collect($service_list)->unique('name');
                    $speciality_list = $wpdb->get_results("SELECT value,label  FROM {$wpdb->prefix}kc_static_data WHERE type='specialization' AND status = 1");
                    ?>
                    <div class="kivicare-doctor-card body" style="margin-bottom: 8px">
                        <form id="kivicare_filter_button">
                            <div class="grid-container filter-component">
                                <?php if (!empty($speciality_list)) {
                                    ?>
                                    <select name="speciality">
                                        <option value="">
                                            <?php echo esc_html__('Filter by  Speciality', 'kc-lang'); ?>
                                        </option>
                                        <?php foreach ($speciality_list as $spe) {
                                            if (!empty($spe->value) && !empty($spe->label)) {
                                                ?>
                                                <option value="<?php echo esc_html($spe->label); ?>">
                                                    <?php echo esc_html($spe->label); ?>
                                                </option>
                                                <?php
                                            }
                                        } ?>
                                    </select>
                                    <?php
                                }
                                if (!empty($service_list)) {
                                    ?>
                                    <select name="service">
                                        <option value="">
                                            <?php echo esc_html__('Filter by  Service ', 'kc-lang'); ?>
                                        </option>
                                        <?php foreach ($service_list as $service) {
                                            if (!empty($service->service_name) && !empty($service->name)) {
                                                ?>
                                                <option value="<?php echo esc_html($service->name); ?>">
                                                    <?php echo esc_html($service->service_name); ?>
                                                </option>
                                                <?php
                                            }
                                        } ?>
                                    </select>
                                    <?php
                                } ?>
                                <input name="search" type="search"
                                    placeholder="<?php echo esc_html__('Search doctor by name,email and ID', 'kc-lang'); ?>">
                                <button type="submit"> <?php echo esc_html__('Filter Doctor', 'kc-lang') ?> </button>
                            </div>
                        </form>
                    </div>
                    <?php
                }
                ?>
                <div id="kivicare_doctor_widget_list">
                </div>
                <div class="mfp-hide white-popup" id="kivi-elementor-appointment-widget">
                </div>
                <script>
                    (function ($) {
                        'use strict';
                        var filterDataAndPagination = {
                            speciality: '',
                            service: '',
                            search: '',
                            page: 1
                        }
                        if (parent.document.querySelector('.elementor-editor-active') !== null) {
                            kivicareGetDoctorlist();
                            kivicareDoctorFilter();
                        }
                        document.addEventListener('readystatechange', event => {
                            if (event.target.readyState === "complete") {
                                kivicareGetDoctorlist();
                                kivicareDoctorFilter();
                            }

                        })

                        function kivicareDoctorFilter() {

                            $('#kivicare_filter_button').unbind().submit(function (e) {
                                e.preventDefault();
                                // Get the Login Name value and trim it
                                var search = $.trim($(this).find('input[name="search"]').val());
                                var service = $.trim($(this).find('select[name="service"]').val());
                                var speciality = $.trim($(this).find('select[name="speciality"]').val());
                                filterDataAndPagination.search = search;
                                filterDataAndPagination.service = service;
                                filterDataAndPagination.speciality = speciality;
                                if (speciality === '' && search === '' && service === '') {
                                    console.log('<?php echo esc_html__('Please select any filter value', 'kc-lang') ?>')
                                    // return
                                }
                                kivicareGetDoctorlist();

                            })
                        }
                        function kivicareOpenPopup() {

                            $('.kivicare_elementor_popup_appointment_book').unbind().click(function (event) {
                                event.preventDefault();
                                let doctor_id = $(this).attr("doctor_id");
                                let clinic_id = $(this).attr("clinic_id");
                                $(this).text("<?php echo esc_html__('Loading...', 'kc-lang'); ?>");
                                $(this).prop('disabled', true);
                                let _this = this;
                                jQuery.ajax({
                                    url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                                    type: "get",
                                    dataType: "json",
                                    data: {
                                        action: "ajax_get",
                                        _ajax_nonce: '<?php echo esc_js(wp_create_nonce('ajax_get')); ?>',
                                        route_name: 'render_shortcode',
                                        confirm_page: 'off',
                                        clinic_id: clinic_id,
                                        doctor_id: doctor_id
                                    },
                                    success: function (response) {
                                        $('#kivi-elementor-appointment-widget').text('');
                                        $(_this).text("<?php echo esc_html__('Book Appointment', 'kc-lang'); ?>");
                                        $(_this).prop('disabled', false);
                                        if (response.status !== undefined && response.status === true) {
                                            $('#kivi-elementor-appointment-widget').append(response.data);
                                        }
                                    },
                                    error: function () {
                                        $(_this).text("<?php echo esc_html__('Book Appointment', 'kc-lang'); ?>");
                                        $(_this).prop('disabled', false);
                                        console.log('<?php echo esc_html__('fail', 'kc-lang'); ?>');
                                    },
                                    complete() {
                                        $.magnificPopup.open({
                                            showCloseBtn: true,
                                            mainClass: 'mfp-fade',
                                            type: 'inline',
                                            closeBtnInside: true,
                                            fixedContentPos: true,
                                            midClick: true,
                                            preloader: true,
                                            items: {
                                                src: $('#kivi-elementor-appointment-widget')
                                            },
                                        })
                                    }
                                });
                            });
                        }
                        function kivicareGetDoctorlist() {
                            <?php $setting = $this->get_settings_for_display(); ?>;
                            $('.double-lines-spinner').parent().removeClass('d-none');
                            $('.double-lines-spinner').parent().addClass('center-position-spinner');
                            $('#kivicare-doctor-id-main').addClass('blur-div');
                            jQuery.ajax({
                                url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                                type: "post",
                                data: {
                                    action: "ajax_post",
                                    _ajax_nonce: '<?php echo esc_js(wp_create_nonce('ajax_post')); ?>',
                                    route_name: 'doctor_widget_list',
                                    filter_data_and_pagination: filterDataAndPagination,
                                    setting: <?php echo json_encode($setting); ?>
                                },
                                success: function (response) {
                                    $('.double-lines-spinner').parent().addClass('d-none');
                                    $('.double-lines-spinner').parent().removeClass('center-position-spinner');
                                    $('#kivicare-doctor-id-main').removeClass('blur-div');
                                    if (response.status && response.status === true) {
                                        $('#kivicare_doctor_widget_list').html(response.data)
                                        kivicareOpenPopup();
                                        kivicarePaginationButton();
                                    } else {
                                        console.log(response.message)
                                    }
                                },
                                error: function (jqXHR, textStatus, errorThrown) {
                                    $('.double-lines-spinner').parent().addClass('d-none');
                                    $('.double-lines-spinner').parent().removeClass('center-position-spinner');
                                    $('#kivicare-doctor-id-main').removeClass('blur-div');
                                    console.log('AJAX request failed:');
                                    console.log('Status:', textStatus); 
                                    console.log('Error Thrown:', errorThrown); 
                                    console.log('Response Text:', jqXHR.responseText); 
                                    console.log('Status Code:', jqXHR.status); 
                                    console.log('<?php echo esc_html__('fail', 'kc-lang'); ?>');
                                }
                            });
                        }
                        function kivicarePaginationButton() {
                            $('.iq_kivicare_next_previous').click(function (e) {
                                e.preventDefault();
                                console.log($(this).attr("kc_page"))
                                let page = $(this).attr("kc_page");
                                filterDataAndPagination.page = page;
                                kivicareGetDoctorlist();
                            })
                        }
                    }).apply(this, [jQuery]);
                </script>
            </div>
            <div class="column d-none">
                <div class="double-lines-spinner"></div>
            </div>
            <?php

        }
    }

}

// Register widget.
\Elementor\Plugin::instance()->widgets_manager->register(new \KCElementorClinicWiseDoctor());