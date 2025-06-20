<?php  $elementId= wp_generate_uuid4(); ?>
<div class="kivi-widget" id="kivi-appointment-widget-<?php echo esc_attr($elementId); ?>">
    <?php if($popup) {
        ?><div class="kivi-position-relative">
        <button class="kivi-widget-close"><i class="fas fa-times"></i></button>
    </div>
    <?php 
    }
    $theme_mode = get_option(KIVI_CARE_PREFIX . 'theme_mode');
    $rtl_attr = in_array($theme_mode,['1','true']) ? 'rtl' : '';
    ?>
    
    <div class="container-fluid d-none" id="kivicare-widget-main-content" dir='<?php echo esc_html($rtl_attr); ?>'>
        <div class="widget-layout" id="widgetOrders">
            <?php
            $services_data = $service_data = new \stdClass();
            if($shortcode_service_id != 0){
                $service_condition_query = " ";
                if ($shortcode_clinic_id != 0) {
                    $shortcode_clinic_id_query = (int)$shortcode_clinic_id;
                    $service_condition_query .= " AND map.clinic_id = {$shortcode_clinic_id_query} ";
                }
                if ($shortcode_doctor_id != 0) {
                    $shortcode_doctor_id_query = implode(',',array_filter(array_map('absint', explode(',', $shortcode_doctor_id))));
                    $service_condition_query .= " AND map.doctor_id IN ({$shortcode_doctor_id_query}) ";
                }
                global $wpdb;
                $service_query = "SELECT map.*,ser.type, ser.name FROM {$wpdb->prefix}kc_service_doctor_mapping AS map
                JOIN {$wpdb->prefix}kc_services AS ser ON ser.id = map.service_id  
                WHERE map.status=1 AND map.service_id={$shortcode_service_id} {$service_condition_query} ";
                $services_data = $wpdb->get_results($service_query);
            }

            $printConfirmPage = !empty($_GET['confirm_page']) ? sanitize_text_field(wp_unslash($_GET['confirm_page'])) : 'off';
            $user_id = get_current_user_id();

            $googleRecaptchaStatus = kcGoogleCaptchaData('status');
            if(get_option(KIVI_CARE_PREFIX.'widget_order_list')){
                $options = get_option(KIVI_CARE_PREFIX.'widget_order_list');
            }else{
                $options = kcDefaultAppointmentWidgetOrder();
            }

            foreach ($options as $key => $option) {

                //remove login/register detail tab if user already login
                if(is_user_logged_in()){
                    if ($option['att_name'] == 'detail-info') {
                        unset($options[$key]);
                    }
                }
                //remove clinic if it's preselected
                // if ($shortcode_clinic_id != 0) {
                //     if(count(explode(",",$shortcode_clinic_id)) == 1){
                //         if ($option['att_name'] == 'clinic') {
                //             unset($options[$key]);
                //         }
                //     }
                // }

                if ($shortcode_clinic_id_single) {
                    if ($option['att_name'] == 'clinic') {
                        unset($options[$key]);
                    }
                }

                //remove doctor if it's preselected
                if ($shortcode_doctor_id_single) {
                    if ($option['att_name'] == 'doctor') {
                        unset($options[$key]);
                    }
                }
                //remove clinic if it's preselected
                if (count((array)$services_data) > 0 ) {
                    $service_data = $services_data[0];
                    if ($option['att_name'] == 'category') {
                        unset($options[$key]);
                    }
                }
                //remove extra appointment detail tab if condition false
                if(!kcCheckExtraTabConditionInAppointmentWidget('all')){
                    if ($option['att_name'] == 'file-uploads-custom') {
                        unset($options[$key]);
                    }
                }
            }
            //left side order list
            ?>
            <div class="iq-card iq-card-lg iq-bg-primary widget-tabs" style="overflow: hidden;">
                <ul class="tab-list mb-2" id="kivicare-animate-ul">
                    <?php
                    $options = array_values($options);
                    foreach ($options as $key => $option) {
                        $activeClass = $key == 0 ? "active" : "";
                        ?>
                        <li class="tab-item <?php echo esc_html($activeClass) ?>" data-check="false">
                            <?php
                            require KIVI_CARE_DIR . 'app/baseClasses/bookAppointment/components/' . $option['att_name'] . '/tab.php';
                            ?>
                        </li>
                        <?php
                    }
                    ?>
                </ul>
                <div class="" style="margin-top: auto;">
                    <button id="kivicare_logout_btn"
                            class="iq-button iq-button-secondary w-100 mt-auto <?php echo esc_html(is_user_logged_in() ? '' : 'd-none'); ?>">
                        <?php echo esc_html__('Logout', 'kc-lang' ); ?>
                    </button>
                </div>
            </div>
            <div class="widget-pannel alert-relative">
                <span id="kivicare_server_error_msg" class="alert alert-popup alert-danger alert-left error" style="display:none">&nbsp;</span>
                <span id="kivicare_success_msg" class="alert alert-popup alert-success alert-left" style="display:none;"></span>
                <div class="iq-card iq-card-sm tab-content" id="wizard-tab">
                    <?php
                    $allPaymentMethod = kcAllPaymentMethodList();
                    $options = array_values($options);
                    foreach ($options as $key => $option) {
                        if($option['att_name'] !== 'confirm'){
                            $formEnctype=" ";
                            if ($option['att_name'] === 'clinic') {
                                $activeClass = $key == 0 ? 'active ' : '';
                                $footer = kcWidgetFooterContent('kivicare_error_msg_clinic',$activeClass);
                            } elseif ($option['att_name'] === 'doctor') {
                                $activeClass = $key == 0  ? 'active ' : '';
                                $footer = kcWidgetFooterContent('kivicare_error_msg_doctor',$activeClass);
                            } elseif ($option['att_name'] === 'category') {
                                $activeClass = $key == 0 ? "active" : "";
                                $footer = kcWidgetFooterContent('kivicare_error_msg_category',$activeClass);
                            }else if($option['att_name'] === 'date-time'){
                                $activeClass = $key == 0  ? "active" : "";
                                $footer = kcWidgetFooterContent('kivicare_error_msg_date_time',$activeClass);
                            }else if($option['att_name'] === 'file-uploads-custom'){
                                $activeClass = $key == 0  ? "active" : "";
                                $footer = kcWidgetFooterContent('kivicare_error_msg',$activeClass);
                                $formEnctype = ' enctype="multipart/form-data"';
                            }  else if($option['att_name'] === 'detail-info'){
                                $activeClass = $key == 0 ? "active" : "";
                                $formEnctype = ' enctype="multipart/form-data"';
                                $footer = kcWidgetFooterContent('kivicare_error_msg_login_register',$activeClass);
                            }
                            ?>
                            <div id="<?php echo esc_html($option['att_name']); ?>"
                                 class="iq-fade iq-tab-pannel <?php echo esc_html($activeClass); ?>">
                                <form <?php echo esc_html($formEnctype);?>  id="<?php echo esc_html(in_array($option['att_name'],['detail-info','file-uploads-custom']) ? ( $option['att_name'] === 'detail-info' ? 'kiviLoginRegister' : 'kivicare-file-upload-form' ) : ''); ?>"
                                                                            action="<?php echo '#' . esc_html(!empty($options[$key + 1]['att_name']) ? $options[$key + 1]['att_name'] : ''); ?>"
                                                                            data-prev="<?php echo '#' . esc_html(!empty($options[$key - 1]['att_name']) ? $options[$key - 1]['att_name'] : ''); ?>">
                                    <?php
                                    require KIVI_CARE_DIR . "app/baseClasses/bookAppointment/components/" . $option['att_name'] . "/tab-panel.php";
                                    echo $footer;
                                    ?>
                                </form>
                            </div>
                            <?php

                        }else{

                            $nextTarget = '#confirmed';
                            if(count($allPaymentMethod) > 1){
                                $nextTarget = '#payment_mode';
                            }
                            $footer = kcWidgetFooterContent('kivicare_error_msg_confirm','');
                            ?>
                            <div id="<?php echo esc_html($option['att_name']); ?>" class="iq-fade iq-tab-pannel">
                                <form id="confirm_detail_form" action="<?php echo esc_html($nextTarget); ?>"
                                      data-prev="#clinic">
                                    <?php
                                    require KIVI_CARE_DIR . "app/baseClasses/bookAppointment/components/" . $option['att_name'] . "/tab-panel.php";
                                    echo $footer;
                                    ?>
                                </form>
                            </div>
                            <?php
                            if(count($allPaymentMethod) > 1){
                                ?>
                                <div id="payment_mode" class="iq-fade iq-tab-pannel">
                                    <form id="payment_mode_form" action="#confirmed"
                                          data-prev="#confirm">
                                        <?php
                                        require KIVI_CARE_DIR . "app/baseClasses/bookAppointment/components/confirm-pay/tab-panel.php";
                                        echo kcWidgetFooterContent('kivicare_payment_mode_confirm','');
                                        ?>
                                    </form>
                                </div>
                                <div id="payment_error" class="iq-fade iq-tab-pannel">
                                    <form id="payment_error_form" action=""
                                          data-prev="#payment_mode">
                                        <?php
                                        require KIVI_CARE_DIR . "app/baseClasses/bookAppointment/components/payment-error/tab-panel.php";
                                        ?>
                                    </form>
                                </div>
                                <?php
                            }
                            ?>
                            <div id="confirmed" class="iq-fade iq-tab-pannel">
                                <?php
                                require KIVI_CARE_DIR . "app/baseClasses/bookAppointment/components/confirmed.php";
                                ?>
                            </div>
                            <?php

                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <span id="kivicare-main-page-loader" style="background:#fff; display: flex;align-items: center;justify-content: center;">
           <?php if (isLoaderCustomUrl()) { ?>
               <img src="<?php echo esc_url(kcAppointmentWidgetLoader()); ?>">
           <?php } else { ?>
               <div class="double-lines-spinner"></div>
           <?php } ?>
    </span>
    
</div>


<script>
    <?php        
        $widgetSetting = json_decode( get_option(KIVI_CARE_PREFIX.'widgetSetting'),true );

        $bookAppointmentWidgetData = array(
            "ajax_url" => esc_url(admin_url('admin-ajax.php')),
            "ajax_post_nonce" => esc_js(wp_create_nonce('ajax_post')),
            "ajax_get_nonce" => esc_js(wp_create_nonce('ajax_get')),
            "message" => array(
                "route_not_found" => esc_html__("Route not found", "kc-lang"),
                "internal_server_msg" => esc_html__("Internal server error", "kc-lang"),
                "loading" => esc_html__('Loading....', 'kc-lang'),
                "logout" => esc_html__('Logout', 'kc-lang'),
                "login" => esc_html__('Login', 'kc-lang'),
                "register" => esc_html__('Register', 'kc-lang'),
                "select_clinic" => esc_html__('Select Clinic', 'kc-lang'),
                "select_doctor" => esc_html__('Select Doctor', 'kc-lang'),
                "select_category" => esc_html__('Select Category', 'kc-lang'),
                "select_date_and_time" => esc_html__('Select Date and Time', 'kc-lang'),
                "select_payment_mode" => esc_html__('Select Payment Mode', 'kc-lang'),
                "please_select_date" => esc_html__('Please Select Date', 'kc-lang'),
                "no_doctor_available" => esc_html__('No doctor Available', 'kc-lang'),
                "no_clinic_available" => esc_html__('No clinic Available', 'kc-lang'),
                "no_service_available" => esc_html__('No service Available', 'kc-lang'),
                "next" => esc_html__('Next', 'kc-lang'),
                "confirm" => esc_html__('Confirm', 'kc-lang'),
                "full_calendar" => array(
                    "weekdays" => array(
                        "shorthand" => array(
                            esc_html__('Sun', 'kc-lang'),
                            esc_html__('Mon', 'kc-lang'),
                            esc_html__('Tue', 'kc-lang'),
                            esc_html__('Wed', 'kc-lang'),
                            esc_html__('Thu', 'kc-lang'),
                            esc_html__('Fri', 'kc-lang'),
                            esc_html__('Sat', 'kc-lang')
                        ),
                        "longhand" => array(
                            esc_html__('Sunday', 'kc-lang'),
                            esc_html__('Monday', 'kc-lang'),
                            esc_html__('Tuesday', 'kc-lang'),
                            esc_html__('Wednesday', 'kc-lang'),
                            esc_html__('Thursday', 'kc-lang'),
                            esc_html__('Friday', 'kc-lang'),
                            esc_html__('Saturday', 'kc-lang')
                        )
                    ),
                    "months" => array(
                        "shorthand" => array(
                            esc_html__('Jan', 'kc-lang'),
                            esc_html__('Feb', 'kc-lang'),
                            esc_html__('Mar', 'kc-lang'),
                            esc_html__('Apr', 'kc-lang'),
                            esc_html__('May', 'kc-lang'),
                            esc_html__('Jun', 'kc-lang'),
                            esc_html__('Jul', 'kc-lang'),
                            esc_html__('Aug', 'kc-lang'),
                            esc_html__('Sep', 'kc-lang'),
                            esc_html__('Oct', 'kc-lang'),
                            esc_html__('Nov', 'kc-lang'),
                            esc_html__('Dec', 'kc-lang')
                        ),
                        "longhand" => array(
                            esc_html__('January', 'kc-lang'),
                            esc_html__('February', 'kc-lang'),
                            esc_html__('March', 'kc-lang'),
                            esc_html__('April', 'kc-lang'),
                            esc_html__('May', 'kc-lang'),
                            esc_html__('June', 'kc-lang'),
                            esc_html__('July', 'kc-lang'),
                            esc_html__('August', 'kc-lang'),
                            esc_html__('September', 'kc-lang'),
                            esc_html__('October', 'kc-lang'),
                            esc_html__('November', 'kc-lang'),
                            esc_html__('December', 'kc-lang')
                        )
                    )
                )
            ),
            "print_confirm_page" => esc_js($printConfirmPage),
            "user_login" => is_user_logged_in(),
            "popup_appointment_book" => $popup,
            "google_recaptcha_enable" => $googleRecaptchaStatus === 'on',
            "google_recatcha_site_key" => esc_html(kcGoogleCaptchaData('site_key')),
            "extra_tab_show" => kcCheckExtraTabConditionInAppointmentWidget('all'),
            "preselected_doctor" => esc_attr($shortcode_doctor_id),
            "preselected_service" => esc_attr($shortcode_service_id),
            "preselected_clinic_id" => esc_js($shortcode_clinic_id),
            "preselected_single_doctor_id" => esc_js($shortcode_doctor_id_single),
            "preselected_single_clinic_id" => esc_js($shortcode_clinic_id_single),
            "selected_service_id_data" => !empty((array)$service_data) ? $service_data : null,
            "restriction_data" => kcAppointmentRestrictionData(),
            "loader_by_image" => isLoaderCustomUrl(),
            "loader_image_url" => esc_url(kcAppointmentWidgetLoader()),
            "pro_plugin_active" => esc_js(isKiviCareProActive()),
            "current_user_id" => esc_js(get_current_user_id()),
            "first_payment_method" => esc_js(array_key_first($allPaymentMethod)),
            "skip_service_when_single" => esc_js(empty($widgetSetting['skip_service_when_single']) ? "false" : $widgetSetting['skip_service_when_single']),
            "allowed_file_size" => wp_max_upload_size(),
            "pageId"    =>  get_queried_object_id() ?? "awegaweg",
        );
    
    ?>
        var bookAppointmentWidgetData = <?php echo json_encode($bookAppointmentWidgetData); ?>;
  
    <?php if(!empty($widgetSetting['primaryColor'])){
        ?>
            document.documentElement.style.setProperty("--iq-primary", '<?php echo esc_js( !empty($widgetSetting['primaryColor']) ? $widgetSetting['primaryColor'] : '#7093e5' );?>');
        <?php }

     
    if(!empty($widgetSetting['primaryHoverColor'])){
        ?>
            document.documentElement.style.setProperty("--iq-primary-dark", '<?php echo esc_js( !empty($widgetSetting['primaryHoverColor']) ? $widgetSetting['primaryHoverColor'] : '#4367b9' );?>');
        <?php }
     
    if(!empty($widgetSetting['secondaryColor'])){
        ?>
        document.documentElement.style.setProperty("--iq-secondary", '<?php echo esc_js(!empty($widgetSetting['secondaryColor']) ?  $widgetSetting['secondaryColor'] : '#f68685' );?>');
        <?php }

    if(!empty($widgetSetting['secondaryHoverColor'])){
        ?>
        document.documentElement.style.setProperty("--iq-secondary-dark", '<?php echo esc_js( !empty($widgetSetting['secondaryHoverColor']) ? $widgetSetting['secondaryHoverColor'] : '#df504e' );?>');
        <?php }

  ?>

    if(bookAppointmentWidgetData.popup_appointment_book){
        kcAppointmentBookJsContent("#kivi-appointment-widget-<?php echo $elementId; ?>");
    }else{
        document.addEventListener('readystatechange', event => {
            if (event.target.readyState === "complete") {
                // jQuery('#CountryCode').select2({
                //     dropdownParent: jQuery('.contact-box-inline'),
                //     templateSelection: function(data, container) {
                //         var countrycodedata = JSON.parse(data.id);
                //         return countrycodedata.countryCallingCode;
                //     }
                // });
                'use strict';
                kcAppointmentBookJsContent("#kivi-appointment-widget-<?php echo $elementId; ?>");
                (function($) {
                    const get = (route, data, frontEnd = false) => {
                        data._ajax_nonce = bookAppointmentWidgetData.ajax_get_nonce;
                        validateBookAppointmentWidgetData(bookAppointmentWidgetData);
                        let url = bookAppointmentWidgetData.ajax_url;
                        if (data.action === undefined) {
                            url = bookAppointmentWidgetData.ajax_url + '?action=ajax_get';
                        }

                        if (route === undefined) {
                            return false
                        }

                        url = url + '&route_name=' + route;
                        return new Promise((resolve, reject) => {
                            axios.get(url, {
                                    params: data
                                })
                                .then((data) => {
                                    if (data.data.status_code !== undefined && data.data.status_code === 403) {
                                        kivicareShowErrorMessage('kivicare_server_error_msg', '<?php // echo esc_html__("Route not found", "kc-lang"); ?>');
                                    }
                                    resolve(data)
                                })
                                .catch((error) => {
                                    reject(error)
                                    kivicareShowErrorMessage('kivicare_server_error_msg', '<?php // echo esc_html__("Internal server error", "kc-lang"); ?>');
                                });
                        })
                    }

                    // getUserRegistrationFormData();


                    // function getUserRegistrationFormData() {
                    //     get('get_user_registration_form_settings_data', {})
                    //         .then((response) => {
                    //             console.log(response.data.data.userRegistrationFormSettingData);
                    //             if (response.data.status !== undefined && response.data.status === true) {
                    //                 let userRegistrationFormSettingData = response.data.data.userRegistrationFormSettingData;
                    //                 if (userRegistrationFormSettingData === 'on') {                                   
                    //                     $('#otherGenderOption').show();
                    //                 } else {
                                        
                    //                     $('#otherGenderOption').hide();
                    //                 }
                    //                 return response.data.data.userRegistrationFormSettingData;
                    //             }
                    //         })
                    //         .catch((error) => {
                    //             console.log(error);
                    //             displayErrorMessage(this.formTranslation.common.internal_server_error);
                    //         })
                    // }

                    $('#kc_login').on('click', '#togglePassword', function() {
                        var passwordInput = $(this).siblings('input[type="password"], input[type="text"]');
                        var toggleIcon = $(this);

                        if (passwordInput.attr('type') === 'password') {
                            passwordInput.attr('type', 'text');
                            toggleIcon.removeClass('fa-eye').addClass('fa-eye-slash');
                        } else {
                            passwordInput.attr('type', 'password');
                            toggleIcon.removeClass('fa-eye-slash').addClass('fa-eye');
                        }
                    });

                })(window.jQuery)
            }
        })
    }
</script>