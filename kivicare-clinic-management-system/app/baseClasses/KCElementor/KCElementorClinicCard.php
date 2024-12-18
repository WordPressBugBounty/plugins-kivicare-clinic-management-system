<?php

use App\baseClasses\KCBase;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

class KCElementorClinicCard extends \Elementor\Widget_Base {

    public function __construct($data = [], $args = null)
    {

        wp_register_style( 'kc_elementor', KIVI_CARE_DIR_URI . 'assets/css/kcElementor.css', array(), KIVI_CARE_VERSION );
        wp_register_script( 'kc_axios',KIVI_CARE_DIR_URI . 'assets/js/axios.min.js' ,['jquery'], KIVI_CARE_VERSION, true );
        wp_register_script( 'kc_flatpicker',KIVI_CARE_DIR_URI . 'assets/js/flatpicker.min.js' ,[], KIVI_CARE_VERSION, true );
        wp_register_style( 'kc_book_appointment',KIVI_CARE_DIR_URI . 'assets/css/book-appointment.css' ,[], KIVI_CARE_VERSION, false );
        wp_register_style( 'kc_flatpicker',KIVI_CARE_DIR_URI . 'assets/css/flatpicker.min.css' ,[], KIVI_CARE_VERSION, false );
        wp_register_script( 'kc_print',KIVI_CARE_DIR_URI . 'assets/js/jquery-print.min.js' ,['jquery'], KIVI_CARE_VERSION, true );
        wp_register_script( 'kc_calendar',KIVI_CARE_DIR_URI . 'assets/js/calendar.min.js' ,[], KIVI_CARE_VERSION, true );
        wp_register_style( 'kc_calendar',KIVI_CARE_DIR_URI . 'assets/css/calendar.min.css' ,[], KIVI_CARE_VERSION, false );
        wp_register_style( 'kc_popup', KIVI_CARE_DIR_URI . 'assets/css/magnific-popup.min.css', [], KIVI_CARE_VERSION, false );
        wp_register_script( 'kc_popup', KIVI_CARE_DIR_URI . 'assets/js/magnific-popup.min.js', ['jquery'], KIVI_CARE_VERSION,true );
        wp_register_script( 'kc_bookappointment_widget', KIVI_CARE_DIR_URI . 'assets/js/book-appointment-widget.js', ['jquery'], KIVI_CARE_VERSION,true );
        parent::__construct($data, $args);
    }

    public function get_style_depends() {
        $temp = [ 'kc_elementor','kc_popup','kc_book_appointment','kc_flatpicker'];
        if(isKiviCareProActive()){
            $temp[] = 'kc_calendar';
        }
        return $temp;
    }
    public function get_script_depends() {
        $temp = ['kc_popup','kc_axios','kc_flatpicker','kc_bookappointment_widget'];
        if(kcGetSingleWidgetSetting('widget_print')){
            $temp[] = 'kc_print';
        }
        if(isKiviCareProActive()){
            $temp[] = 'kc_calendar';
        }
        return $temp;
    }

    public function get_name() {
        return 'kivicare-clinic-card';
    }

    public function get_title() {
        return __( 'Kivicare Clinic List', 'kc-lang' );
    }

    public function get_icon() {
        return 'fas fa-hospital';
    }

    public function get_categories() {
        return [ 'kivicare-widget-category' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'iq_kivicare_clinic_card_shortcode',
            [
                'label' => __( 'Kivicare Clinic Card', 'kc-lang' ),
            ]
        );

        $this->add_control(
            'iq_kivivare_clinic_enable_filter',
            [
                'label' => esc_html__( 'Enable Filter', 'kc-lang' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'label_off' => esc_html__( 'Hide', 'elementor' ),
                'label_on' => esc_html__( 'Show', 'elementor' ),
            ]
        );

        $this->add_control(
            'iq_kivivare_clinic_per_page',
            [
                'label' => __('Clinic per page ', 'kc-lang' ),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 2,
                'min' => 0
            ]
        );

        if(isKiviCareProActive()){

            $this->add_control(
                'iq_kivivare_clinic_exclude_clinic',
                [
                    'label' => __('Enable Exclude Clinic', 'kc-lang' ),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                ]
            );

            $this->add_control(
                'iq_kivivare_clinic_exclude_clinic_list',
                [
                    'label' => __('Exclude Clinic', 'kc-lang' ),
                    'type' => \Elementor\Controls_Manager::SELECT2,
                    'multiple' => true,
                    'options' => kcClinicForElementor('all'),
                    'label_block' => true,
                    'condition'=>[
                        'iq_kivivare_clinic_exclude_clinic' => 'yes'
                    ]
                ]
            );
        }

        $this->add_control(
            'iq_kivivare_clinic_gap_between_card',
            [
                'label' => esc_html__( 'Hide Space Between Clinics', 'kc-lang' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_off' => esc_html__( 'Hide', 'elementor' ),
                'label_on' => esc_html__( 'Show', 'elementor' ),
            ]
        );

        $this->add_control(
            'iq_kivivare_clinic_image',
            [
                'label' => esc_html__( 'Profile Image', 'kc-lang' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'label_off' => esc_html__( 'Hide', 'elementor' ),
                'label_on' => esc_html__( 'Show', 'elementor' ),
            ]
        );
        
        $this->commonControl($this,'name','clinic');
        $this->commonControl($this,'speciality','clinic');
        $this->commonControl($this,'number','clinic');
        $this->commonControl($this,'email','clinic');
        $this->commonControl($this,'address','clinic');

        $this->commonControl($this,'administrator','clinic');
        $this->commonControl($this,'admin_number','clinic');
        $this->commonControl($this,'admin_email','clinic');

        $this->end_controls_section();
        
        $this->start_controls_section('iq_kivicare_card_style_sections',
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


        $this->end_controls_section();

        $this->start_controls_section('iq_kivicare_image_style_sections',
            [
                'label' => esc_html__('Image style', 'kc-lang'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition'=>[
                    'iq_kivivare_clinic_image' => 'yes'
                ],
            ]
        );


        $this->add_control(
            'iq_kivicare_doctor_image_height',
            [
                'label' => esc_html__('Image Height', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition'=>[
                    'iq_kivivare_clinic_image' => 'yes'
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
                'condition'=>[
                    'iq_kivivare_clinic_image' => 'yes'
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
                'condition'=>[
                    'iq_kivivare_clinic_image' => 'yes'
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
                'condition'=>[
                    'iq_kivivare_clinic_image' => 'yes'
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
                'condition'=>[
                    'iq_kivivare_clinic_image' => 'yes'
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
                'condition'=>[
                    'iq_kivivare_clinic_image' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .kivicare-doctor-avtar' => 'border-color: {{VALUE}};',
                ]
            ]
        );

        $this->end_controls_section();

        $this->fontStyleControl($this,'name');
        $this->fontStyleControl($this,'speciality');
        $this->fontStyleControl($this,'number');
        $this->fontStyleControl($this,'email');
        $this->fontStyleControl($this,'address');
        // $this->fontStyleControl($this,'administrator');
        $this->fontStyleControl($this,'admin_number');
        $this->fontStyleControl($this,'admin_email');

        $this->start_controls_section('iq_kivicare_administrator_style_sections',
            [
                'label' => esc_html__('Administrator style', 'kc-lang'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition'=>[
                    'iq_kivivare_clinic_administrator' => 'yes'
                ],
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_administrator-label-color',
            [
                'label' => esc_html__('Label Color', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition'=>[
                    'iq_kivivare_clinic_administrator' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_administrator-label' => 'color: {{VALUE}};',
                ]
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'iq_kivicare_doctor_administrator-label-typography',
                'label' => esc_html__('Label Typography', 'kc-lang'),
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
            	],
                'selector' => '{{WRAPPER}} .iq_kivicare_doctor_administrator-label',
                'condition' => [
                    'iq_kivivare_clinic_administrator' => 'yes'
                ]
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_administrator-label-margin',
            [
                'label' => esc_html__('Label Margin', 'kc-lang'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivivare_clinic_administrator' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_administrator-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_administrator-label-padding',
            [
                'label' => esc_html__('Label Padding', 'kc-lang'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivivare_clinic_administrator' => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_administrator-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'iq_kivicare_doctor_administrator-label-align',
            [
                'label' => esc_html__('Label Alignment', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'left' => esc_html__('Left', 'kc-lang'),
                    'center' => esc_html__('Center', 'kc-lang'),
                    'right' => esc_html__('Right', 'kc-lang')
                ],
                'condition' => [
                    'iq_kivivare_clinic_administrator' => 'yes'
                ],    
                'selectors' => [
                    '{{WRAPPER}} .appoin' => 'text-align: {{VALUE}};',
                ]
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section('iq_kivicare_book_appointment_button',
            [
                'label' => esc_html__('Book appointment Button style', 'kc-lang'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        kcElementorAllCommonController($this,'clinic_clinic');

        $this->end_controls_section();
        
    }

    protected function commonControl($this_ele,$type,$userType){
        $this_ele->add_control(
            'iq_kivivare_'. $userType .'_'.$type,
            [
                /*Translator: Email text */
                'label' => $type === 'email' ? ucfirst($type).__( ' ID', 'kc-lang' ) : ucfirst($type),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'label_off' => esc_html__( 'Hide', 'elementor' ),
                'label_on' => esc_html__( 'Show', 'elementor' ),
            ]
        );

        $value = '';
        switch($type){
            case 'name':
                case 'speciality' : 
                $value = '';
                break;
            case 'email': 
                $value =  ucfirst($type).' ID';
                break;
            case 'session': 
                $value =  'Schedule Appointment';
                break;
            case 'number':
                case 'admin_number': 
                $value =  'Contact No';
                break;
            case 'admin_email': 
                $value =  ucfirst('email').' ID';
                break;
            default:
                $value = ucfirst($type);
                break;
        }

        $this_ele->add_control(
            'iq_kivivare_'. $userType .'_'.$type.'_label',
            [
                'label' => esc_html__( 'label ', 'kc-lang' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => $value,
                'condition'=>[
                    'iq_kivivare_'. $userType .'_'.$type => 'yes'
                ],
                'dynamic' => [
                    'active' => true,
                ],
            ]
        );
    }

    protected function fontStyleControl($this_ele,$type){
        // $type = $userType === 'clinic' ? $type : $type.'_clinic';
        $this_ele->start_controls_section('iq_kivicare_'.$type.'_style_sections',
            [
                /*Translator: label text */
                'label' => ucfirst($type).__(' style', 'kc-lang'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition'=>[
                    'iq_kivivare_clinic_'.$type => 'yes'
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_'.$type.'-label-color',
            [
                'label' => esc_html__('Label Color', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition'=>[
                    'iq_kivivare_clinic_'.$type => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_'.$type.'-label' => 'color: {{VALUE}};',
                ]
            ]
        );

        $this_ele->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'iq_kivicare_doctor_'.$type.'-label-typography',
                'label' => esc_html__('Label Typography', 'kc-lang'),
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
            	],
                'selector' => '{{WRAPPER}} .iq_kivicare_doctor_'.$type.'-label',
                'condition' => [
                    'iq_kivivare_clinic_'.$type => 'yes'
                ]
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_'.$type.'-label-margin',
            [
                'label' => esc_html__('Label Margin', 'kc-lang'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivivare_clinic_'.$type => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_'.$type.'-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_'.$type.'-label-padding',
            [
                'label' => esc_html__('Label Padding', 'kc-lang'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivivare_clinic_'.$type => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_'.$type.'-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_'.$type.'-label-align',
            [
                'label' => esc_html__('Label Alignment', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'left' => esc_html__('Left', 'kc-lang'),
                    'center' => esc_html__('Center', 'kc-lang'),
                    'right' => esc_html__('Right', 'kc-lang')
                ],
                'condition' => [
                    'iq_kivivare_clinic_'.$type => 'yes'
                ],    
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_'.$type.'-label' => 'text-align: {{VALUE}};',
                ]
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_'.$type.'-value-color',
            [
                'label' => esc_html__('Value Color', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition'=>[
                    'iq_kivivare_clinic_'.$type => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_'.$type.'-value' => 'color: {{VALUE}};',
                ]
            ]
        );

        $this_ele->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'iq_kivicare_doctor_'.$type.'-value-typography',
                'label' => esc_html__('Value Typography', 'kc-lang'),
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
            	],
                'selector' => '{{WRAPPER}} .iq_kivicare_doctor_'.$type.'-value',
                'condition' => [
                    'iq_kivivare_clinic_'.$type => 'yes'
                ]
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_'.$type.'-value-margin',
            [
                'label' => esc_html__('Value Margin', 'kc-lang'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivivare_clinic_'.$type => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_'.$type.'-value' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_'.$type.'-value-padding',
            [
                'label' => esc_html__('Value Padding', 'kc-lang'),
                'size_units' => ['px', '%', 'em'],
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'condition' => [
                    'iq_kivivare_clinic_'.$type => 'yes'
                ],
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_'.$type.'-value' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this_ele->add_control(
            'iq_kivicare_doctor_'.$type.'-value-align',
            [
                'label' => esc_html__('Value Alignment', 'kc-lang'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'left' => esc_html__('Left', 'kc-lang'),
                    'center' => esc_html__('Center', 'kc-lang'),
                    'right' => esc_html__('Right', 'kc-lang')
                ],
                'condition' => [
                    'iq_kivivare_clinic_'.$type => 'yes'
                ],    
                'selectors' => [
                    '{{WRAPPER}} .iq_kivicare_doctor_'.$type.'-value' => 'text-align: {{VALUE}};',
                ]
            ]
        );

        $this->end_controls_section();
    }

    protected function render(){
        $setting = $this->get_settings_for_display();
        $theme_mode = get_option(KIVI_CARE_PREFIX . 'theme_mode');
        $rtl_attr = in_array($theme_mode, ['1', 'true']) ? 'rtl' : '';
        ?>
        <div dir='<?php echo esc_html($rtl_attr); ?>' id="kivicare-clinic-id-main">
            <?php if ($setting['iq_kivivare_clinic_enable_filter'] == 'yes'){
            global $wpdb;
            $speciality_list = collect($wpdb->get_results("SELECT specialties  FROM {$wpdb->prefix}kc_clinics WHERE  status = 1"))->map(function ($v){
                return collect(json_decode($v->specialties))->pluck('label')->unique('label');
            })->flatten(1)->toArray();
            ?>
            <div class="kivicare-doctor-card body" style="margin-bottom: 8px">
                <form id="kivicare_clinic_filter_button">
                    <div class="grid-container filter-component">
                        <?php
                        if (!empty($speciality_list)) {
                            ?>
                            <select name="speciality">
                                <option value=""  >
                                    <?php echo esc_html__('Filter by  Speciality ', 'kc-lang'); ?>
                                </option>
                                <?php foreach ($speciality_list as $spe) {
                                    if (!empty($spe)) {
                                        $spe = trim($spe);
                                        ?>
                                        <option value="<?php echo esc_html($spe); ?>" >
                                            <?php echo esc_html($spe); ?>
                                        </option>
                                        <?php
                                    }
                                } ?>
                            </select>
                            <?php
                        }
                        if(isKiviCareProActive()){
                            $exclude_clinic_condition = ' ';
                            if (!empty($setting['iq_kivivare_clinic_exclude_clinic']) && $setting['iq_kivivare_clinic_exclude_clinic'] === 'yes') {
                                if (!empty($setting['iq_kivivare_clinic_exclude_clinic_list']) && count($setting['iq_kivivare_clinic_exclude_clinic_list'])) {
                                    $exclude_clinic = $setting['iq_kivivare_clinic_exclude_clinic_list'];
                                    $exclude_clinic = array_map('absint',$exclude_clinic);
                                    $exclude_clinic_condition = ' AND ser_map.clinic_id NOT IN ('.implode(',',$exclude_clinic).') ';
                                }
                            }
                            $service_list = $wpdb->get_results("SELECT ser.name, CONCAT(ser.name,'(',REPLACE(ser.type,'_',' '),')') AS service_name FROM {$wpdb->prefix}kc_services AS ser 
                            JOIN {$wpdb->prefix}kc_service_doctor_mapping AS ser_map ON ser_map.service_id = ser.id WHERE ser.status = 1 AND ser_map.status = 1 {$exclude_clinic_condition} ");
                            $service_list = collect($service_list)->unique('name');
                            if (!empty($service_list)) {
                                ?>
                                <select name="service">
                                    <option value="" >
                                        <?php echo esc_html__('Filter by  Service', 'kc-lang'); ?>
                                    </option>
                                    <?php foreach ($service_list as $service) {
                                        if (!empty($service->service_name) && !empty($service->name)) {
                                            ?>
                                            <option value="<?php echo esc_html($service->name); ?>" >
                                                <?php echo esc_html($service->service_name); ?>
                                            </option>
                                            <?php
                                        }
                                    } ?>
                                </select>
                                <?php
                            }
                        }
                         ?>
                        <input name="search" type="search"
                               placeholder="<?php echo esc_html__('Search clinic by name,email and contact no', 'kc-lang'); ?>">
                        <button type="submit"> <?php echo esc_html__('Filter Clinic','kc-lang') ?> </button>
                    </div>
                </form>
            </div>
            <?php
                }
                ?>
                <div id="kivicare_clinic_widget_list">
            </div>
            <div class="mfp-hide white-popup" id="kivi-elementor-clinic-appointment-widget">
            </div>
                <script>
                    (function ($) {
                        'use strict';
                        var filterDataAndPagination = {
                            speciality:'',
                            service:'',
                            search:'',
                            page:1
                        }
                        if (parent.document.querySelector('.elementor-editor-active') !== null) {
                            kivicareClinicFilter();
                            kivicareGetClinicList();
                        }
                        document.addEventListener('readystatechange', event => {
                            if (event.target.readyState === "complete") {
                                kivicareClinicFilter();
                                kivicareGetClinicList();
                            }
                        })

                        function kivicareClinicFilter(){
                            $('#kivicare_clinic_filter_button').unbind().submit(function (e) {
                                e.preventDefault();
                                // Get the Login Name value and trim it
                                var search = $.trim($(this).find('input[name="search"]').val());
                                var service = $.trim($(this).find('select[name="service"]').val());
                                var speciality = $.trim($(this).find('select[name="speciality"]').val());
                                filterDataAndPagination.search = search;
                                filterDataAndPagination.service = service;
                                filterDataAndPagination.speciality = speciality;
                                if (speciality === '' && search === '' && service === ''){
                                    console.log('<?php echo esc_html__("Please select any filter value","kc-lang")?>')
                                }
                                kivicareGetClinicList();
                                
                            })
                        }
                        
                        function kivicareGetClinicList(){
                            $('.double-lines-spinner').parent().removeClass('d-none');
                            $('.double-lines-spinner').parent().addClass('center-position-spinner');
                            $('#kivicare-clinic-id-main').addClass('blur-div');
                            jQuery.ajax({
                                    url: '<?php echo esc_js(admin_url('admin-ajax.php'));?>',
                                    type: "post",
                                    data: {
                                        action: "ajax_post",
                                        _ajax_nonce: '<?php echo esc_js(wp_create_nonce('ajax_post'));?>',
                                        route_name: 'clinic_widget_list',
                                        filter_data_and_pagination:filterDataAndPagination,
                                        setting :<?php echo json_encode($setting); ?>
                                    },
                                    success: function (response) {
                                        // response = JSON.parse(response)
                                        $('.double-lines-spinner').parent().addClass('d-none');
                                        $('.double-lines-spinner').parent().removeClass('center-position-spinner');
                                        $('#kivicare-clinic-id-main').removeClass('blur-div');
                                        if(response.status && response.status === true){
                                            $('#kivicare_clinic_widget_list').html(response.data)
                                            kivicareOpenPopup();
                                            kivicarePaginationButton();
                                        }else{
                                            console.log(response.message)
                                        }
                                    },
                                    error: function () {
                                        $('.double-lines-spinner').parent().addClass('d-none');
                                        $('.double-lines-spinner').parent().removeClass('center-position-spinner');
                                        $('#kivicare-clinic-id-main').removeClass('blur-div');
                                        console.log('<?php echo esc_html__('fail','kc-lang');?>');
                                    }
                                });
                        }
                        function kivicarePaginationButton(){
                            $('.iq_kivicare_next_previous').click(function(e){
                                e.preventDefault();
                                console.log($(this).attr("kc_page"))
                                filterDataAndPagination.page = $(this).attr("kc_page");
                                kivicareGetClinicList();
                            })
                        }

                        function kivicareOpenPopup() {
                            $('.kivicare_elementor_clinic_popup_appointment_book').unbind().click(function (event) {
                                event.preventDefault();
                                let clinic_id = $(this).attr("clinic_id");
                                $(this).text("<?php echo esc_html__('Loading...', 'kc-lang'); ?>");
                                $(this).prop('disabled', true);
                                let _this = this;
                                jQuery.ajax({
                                    url: '<?php echo esc_js(admin_url('admin-ajax.php'));?>',
                                    type: "get",
                                    dataType: "json",
                                    data: {
                                        action: "ajax_get",
                                        _ajax_nonce: '<?php echo esc_js(wp_create_nonce('ajax_get'));?>',
                                        route_name: 'render_shortcode',
                                        confirm_page: 'off',
                                        clinic_id: clinic_id,
                                    },
                                    success: function (response) {
                                        $('#kivi-elementor-clinic-appointment-widget').text('');
                                        $(_this).text("<?php echo esc_html__('Book Appointment', 'kc-lang'); ?>");
                                        $(_this).prop('disabled', false);
                                        if (response.status !== undefined && response.status === true) {
                                            $('#kivi-elementor-clinic-appointment-widget').append(response.data);
                                        }
                                    },
                                    error: function () {
                                        $(_this).text("<?php echo esc_html__('Book Appointment', 'kc-lang'); ?>");
                                        $(_this).prop('disabled', false);
                                        console.log('<?php echo esc_html__('fail','kc-lang');?>');
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
                                                src: $('#kivi-elementor-clinic-appointment-widget')
                                            },
                                        })
                                    }
                                });
                            });
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
// Register widget
\Elementor\Plugin::instance()->widgets_manager->register( new \KCElementorClinicCard() );