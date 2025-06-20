<?php
global $wpdb;

$clinic_id = kcGetDefaultClinicId();
if(isKiviCareProActive()){
    $clinic_id = collect($wpdb->get_results("SELECT id FROM {$wpdb->prefix}kc_clinics"))->pluck('id')->implode(",");
}
if(!empty($this->getLoginUserRole())){
    $user_id = get_current_user_id();
    if(isKiviCareProActive()){
        if( $this->getLoginUserRole() === 'KiviCare_receptionist'){
            $clinic_id = $wpdb->get_var("SELECT clinic_id FROM {$wpdb->prefix}kc_receptionist_clinic_mappings WHERE receptionist_id={$user_id}");
        }
        if($this->getLoginUserRole() === 'kiviCare_doctor'){
            unset($userList['KiviCare_doctor']);
            unset($userList['KiviCare_receptionist']);
            $clinic_id = collect($wpdb->get_results("SELECT clinic_id FROM {$wpdb->prefix}kc_doctor_clinic_mappings WHERE doctor_id = {$user_id}"))->pluck('clinic_id')->implode(",");
        }
        if($this->getLoginUserRole() === 'kiviCare_clinic_admin'){
            $clinic_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}kc_clinics WHERE clinic_admin_id = {$user_id}");
        }
    }
}
$clinic = [];

if(empty($clinic_id_param)){
    $clinic = collect($wpdb->get_results("SELECT id,name FROM {$wpdb->prefix}kc_clinics WHERE id IN ({$clinic_id}) AND status = 1"))->toArray();
}

$theme_mode = get_option(KIVI_CARE_PREFIX . 'theme_mode');
$rtl_attr = in_array($theme_mode,['1','true']) ? 'rtl' : '';

?>
<div class="wp-block-kivi-care-register-login">
    <div class="kivi-widget"  dir='<?php echo esc_html($rtl_attr); ?>'>
        <div id="kivi-content" style="display:none;">
            <?php if(kcGoogleCaptchaData('status') !== 'on' && $this->getLoginUserRole() === 'administrator') {
                ?>
                <div class="mb-2">
                    <a class="iq-color-secondary" href='<?php echo esc_url(admin_url('admin.php?page=dashboard#/general-setting')); ?>' target="_blank">
                        <?php echo esc_html__("Note:- Click Here To Enable Google V3 captcha","kc-lang");?>
                    </a>
                </div>
                <?php
            }?>
            <ul class="nav-tabs"><?php
                if(!is_user_logged_in()){
                    if(isset($login) && $login){?>
                        <li class="tab-item active">
                            <a href="#login" class="tab-link" id="login-tab" > <?php echo esc_html__('Login', 'kc-lang'); ?> </a>
                        </li><?php
                    }
                } 
                if(isset($register) && $register){?>
                    <li class="tab-item <?php if(isset($login) && !$login){ echo esc_html__("active","kc-lang"); }?>">
                        <a href="#register" class="tab-link" id="register-tabregister"> <?php echo esc_html__('Register', 'kc-lang'); ?> </a>
                    </li><?php
                }
                
                ?>
            </ul>
            <div id="login-register-panel">
                <?php
                    $login_tab_class = (isset($login) && $login) ? 'd-none' : '';
                    $register_tab_class = (isset($register) && $register) ? 'd-none' : '';
                    if(isset($register) && $register){
                ?>
                <form id="kivicare-register-form" class="<?php echo esc_html( $login_tab_class )?>" enctype="multipart/form-data">
                    <div id="register" class="iq-fade autActive">
                        <div>
                            <div  id="kivicare-register">
                                <?php if(kcGoogleCaptchaData('status') === 'on'){
                                    ?>
                                    <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">
                                    <input type="hidden" name="captcha_action" value="validate_captcha">
                                    <?php
                                }?>
                                <div class="form-group">
                                    <input type="hidden" id="registerClinicId">
                                    <label class="form-label"
                                        for="firstName"><?php echo esc_html__('First Name', 'kc-lang'); ?>
                                        <span>*</span></label>
                                    <input type="text" name="first_name" class="iq-kivicare-form-control" id="firstName"
                                        placeholder="<?php echo esc_html__('Enter your first name', 'kc-lang'); ?>"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"
                                        for="lastName"> <?php echo esc_html__('Last Name', 'kc-lang'); ?>
                                        <span>*</span></label>
                                    <input type="text" name="last_name" class="iq-kivicare-form-control" id="lastName"
                                        placeholder="<?php echo esc_html__('Enter your last name', 'kc-lang'); ?>"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="userEmail"><?php echo esc_html__('Email', 'kc-lang'); ?>
                                        <span>*</span></label>
                                    <input type="email" name="user_email" class="iq-kivicare-form-control" id="userEmail"
                                        placeholder="<?php echo esc_html__('Enter your email', 'kc-lang'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"
                                        for="userContact"> <?php echo esc_html__('Contact', 'kc-lang'); ?>
                                        <span>*</span></label>
                                        <div class="contact-box-inline">
                                            <div class="select-state-container">
                                            <?php
                                            if (file_exists(KIVI_CARE_DIR . 'assets/helper_assets/CountryCodes.json')) {
                                                $json_country_code = file_get_contents(KIVI_CARE_DIR . 'assets/helper_assets/CountryCodes.json');
                                                $country_code = json_decode($json_country_code, true);

                                            ?>
                                            
                                                <select name="country_code" class="iq-kivicare-form-control" id="CountryCode">
                                                    <?php
                                                    foreach ($country_code as $id => $code) {
                                                        $valueString = '{"countryCallingCode":"' . $code['dial_code'] . '","countryCode":"' . $code['code'] . '"}';
                                                    ?>
                                                        <option value="<?php echo esc_html__($valueString, 'kc-lang'); ?>" <?php echo esc_html__(($code['code'] == 'US') ? "selected" : "", 'kc-lang') ?>><?php echo esc_html__($code['dial_code'] . " - " . $code['name'], 'kc-lang'); ?></option>
                                                    <?php
                                                    }
                                                    ?>
                                                </select>
                                            </div>    
                                            <?php
                                            } else {
                                            ?>  <div class="enter-number">
                                                <input type="text" name="country_code" class="iq-kivicare-form-control" id="txt_CountryCode" placeholder="<?php echo esc_html__('Enter your country code', 'kc-lang'); ?>" required></div>
                                            <?php
                                            }
                                            ?>

                                            <div class="enter-number"><input type="tel" name="mobile_number" class="iq-kivicare-form-control" id="userContact" placeholder="<?php echo esc_html__('Enter your contact number', 'kc-lang'); ?>" required></div>
                                </div>
                                </div>

                                    <div class="form-group">
                                        <label class="form-label" for="Usergender"><?php echo esc_html__('Gender', 'kc-lang'); ?>
                                            <span>*</span></label>

                                        <div class="d-flex flex-wrap">
                                            <div class="custom-control custom-radio custom-control-inline">
                                                <input type="radio" id="male" name="gender" value="male" class="custom-control-input" required>
                                                <label class="custom-control-label" for="male"><?php echo esc_html__('Male', 'kc-lang'); ?></label>
                                            </div>
                                            <div class="custom-control custom-radio custom-control-inline">
                                                <input type="radio" id="female" name="gender" value="female" class="custom-control-input">
                                                <label class="custom-control-label" for="female"><?php echo esc_html__('Female', 'kc-lang'); ?></label>
                                            </div>
                                            <div class="custom-control custom-radio custom-control-inline" id="otherGenderOption">
                                                <input type="radio" id="other" name="gender" value="other" class="custom-control-input">
                                                <label class="custom-control-label" for="other"><?php echo esc_html__('Other', 'kc-lang'); ?></label>
                                            </div>
                                        </div>
                                    </div>
                                <?php
                                if (!empty($userList) && count($userList)) {
                                    ?>
                                    <div class="form-group">
                                        <label class="form-label"
                                            for="userRole"> <?php echo esc_html__('Select Role', 'kc-lang'); ?>
                                            <span>*</span></label>
                                        <select name="user_role" class="iq-kivicare-form-control" id="userRole"
                                                required>
                                            <?php
                                            $i = 0;
                                            foreach ($userList as $userKey => $userValue) {
                                                ?>
                                                <option value="<?php echo esc_html($userKey); ?>"><?php echo esc_html($userValue); ?></option>
                                                <?php
                                                $i += 1;
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <?php
                                }
                                ?>
                                <?php if (!empty($clinic_id_param)) : ?>
                                    <input type="hidden" name="user_clinic" value="<?php echo esc_html($clinic_id_param); ?>">
                                <?php elseif (!empty($clinic) && count($clinic)) : ?>
                                    <div class="form-group">
                                        <label class="form-label" for="userClinic">
                                            <?php echo esc_html__('Select Clinic', 'kc-lang'); ?> <span>*</span>
                                        </label>
                                        <select name="user_clinic" class="iq-kivicare-form-control" id="userClinic" required>
                                            <?php foreach ($clinic as $key => $value) : ?>
                                                <option value="<?php echo esc_html($value->id); ?>" <?php selected($key == 0); ?>>
                                                    <?php echo esc_html($value->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div id="kcCustomFieldsList" class="kivi-center ">
                                <div class="double-lines-spinner"></div>
                            </div>
                            <div>
                                <button type="submit" name="submit" value="submit"
                                        class="iq-button iq-button-primary"><?php echo esc_html__("Register"); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php } ?>
                <?php if(isset($login) && $login){ ?>
                <form id="kivicare-login-form" >
                    <div id="login" class="iq-fade authActive">
                        <div>
                            <div  id="kivicare-login">
                                <div class="form-group">
                                    <label class="form-label"
                                        for="loginUsername"><?php echo esc_html__('Username or Email', 'kc-lang'); ?>
                                        <span>*</span></label>
                                    <input type="text" name="username" class="iq-kivicare-form-control" id="loginUsername"
                                        placeholder="<?php echo esc_html__('Enter your username or email', 'kc-lang'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"
                                        for="loginPassword"><?php echo esc_html__('Password', 'kc-lang'); ?>
                                        <span>*</span></label>
                                    <div class="password-container" style="position: relative;">
                                        <input type="password" name="password" class="iq-kivicare-form-control"
                                            id="loginPassword" placeholder="***********" required>
                                        <i class="password-toggle fas fa-eye" id="togglePassword" style="position: absolute; top: 50%; right: 10px; transform: translateY(-50%); cursor: pointer;"></i>
                                    </div>
                                </div>
                                <div class="form-group remember-me-content">
                                    <div>
                                        <input type="checkbox" id="remember-me" name="Remember Me">
                                        <label class="custom-control-label" for="remember-me"><?php echo esc_html__('Remember Me', 'kc-lang'); ?></label>
                                    </div>
                                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" target="_blank"
                                    class="iq-color-secondary forgot-password"><i><?php echo esc_html__('Forgot Password ?', 'kc-lang'); ?></i></a>
                                </div>
                                <div>
                                    <button type="submit" name="submit" value="submit"
                                            class="iq-button iq-button-primary"><?php echo esc_html__('Login', 'kc-lang'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                <?php } ?>
            </div>
            <div class="mb-2">
                <div id="kivicare_server_error_msg" class="alert alert-popup alert-danger alert-left error" style="display:none"></div>
                <div id="kivicare_success_msg" class="alert alert-popup alert-success alert-left" style="display:none;"></div>
            </div>
            <div id="kivi-main-loader-overlay" class="d-none">
            <span style="background:#fff; display: flex;align-items: center;justify-content: center;">
            <?php if (isLoaderCustomUrl()) { ?>
                <img src="<?php echo esc_url(kcAppointmentWidgetLoader()); ?>">
            <?php } else { ?>
                <div class="double-lines-spinner"></div>
            <?php } ?>
        </span>
            </div>
        </div>
        <div id="kivi-main-loader">
            <span style="background:#fff; display: flex;align-items: center;justify-content: center;">
            <?php if (isLoaderCustomUrl()) { ?>
                <img src="<?php echo esc_url(kcAppointmentWidgetLoader()); ?>">
            <?php } else { ?>
                <div class="double-lines-spinner"></div>
            <?php } ?>
        </span>
        </div>
    </div>
</div>    
<script>
    <?php
    $widgetSetting = json_decode( get_option(KIVI_CARE_PREFIX.'widgetSetting'),true );
    ?>
    if('<?php echo !empty($widgetSetting['primaryColor']);?>'){
        document.documentElement.style.setProperty("--iq-primary", '<?php echo esc_js( !empty($widgetSetting['primaryColor']) ? $widgetSetting['primaryColor'] : '#7093e5' );?>');
    }

    if('<?php echo !empty($widgetSetting['primaryHoverColor']);?>'){
        document.documentElement.style.setProperty("--iq-primary-dark", '<?php echo esc_js( !empty($widgetSetting['primaryHoverColor']) ? $widgetSetting['primaryHoverColor'] : '#4367b9' );?>');
    }

    if('<?php echo !empty($widgetSetting['secondaryColor']);?>'){
        document.documentElement.style.setProperty("--iq-secondary", '<?php echo esc_js(!empty($widgetSetting['secondaryColor']) ?  $widgetSetting['secondaryColor'] : '#f68685' );?>');
    }

    if('<?php echo !empty($widgetSetting['secondaryHoverColor']);?>'){
        document.documentElement.style.setProperty("--iq-secondary-dark", '<?php echo esc_js( !empty($widgetSetting['secondaryHoverColor']) ? $widgetSetting['secondaryHoverColor'] : '#df504e' );?>');
    }

    function kivicareFileUploadSizeCheck(event){
        const allowedSize = <?php echo wp_max_upload_size(); ?>; //(in bytes)
        if (event.target.files && event.target.files.length > 0
            && event.target.files[0].size > allowedSize) {
            jQuery(event.target).css('border','1px solid var(--iq-secondary-dark)');
            jQuery(event.target).siblings('div').css('display','block');
            event.target.value = ''; // Clear the file input field
        }else{
            jQuery(event.target).css('border','1px solid #eee');
            jQuery(event.target).siblings('div').css('display','none');
        }
    }

    document.addEventListener('readystatechange', event => {
        if (event.target.readyState === "complete") {
            jQuery('#CountryCode').select2();
            jQuery('#userRole').select2({
                minimumResultsForSearch: Infinity
            });
            jQuery('#userClinic').select2({
                minimumResultsForSearch: Infinity
            });
            'use strict';
            (function ($) {

                const post = (route, data = {}, frontEnd = false, headers = {
                    headers: {'Content-Type': 'application/json'}
                }) => {

                    window.ajaxurl = '<?php echo esc_url(admin_url('admin-ajax.php'));?>';
                    window.nonce = '<?php echo esc_js(wp_create_nonce('ajax_post'));?>';

                    let url = window.ajaxurl;
                    if (data.action === undefined) {
                        url = ajaxurl + '?action=ajax_post';
                    }

                    if (route === undefined) {
                        return false
                    }

                    if (data.append !== undefined) {
                        data.append('route_name', route);
                        data.append('_ajax_nonce', window.nonce)
                    } else {
                        data.route_name = route;
                        data._ajax_nonce = window.nonce;
                    }

                    return new Promise((resolve, reject, headers) => {
                        axios.post(url, data, headers)
                            .then((data) => {
                                if (data.data.status_code !== undefined && data.data.status_code === 403) {
                                    kcShowErrorMessage('<?php echo esc_html__("Route not found","kc-lang"); ?>');
                                }
                                resolve(data)
                            })
                            .catch((error) => {
                                reject(error)
                                kcShowErrorMessage('<?php echo esc_html__("Internal server error","kc-lang"); ?>');
                            });
                    })
                }

                const get = (route, data, frontEnd = false) => {
                    data._ajax_nonce = '<?php echo esc_js(wp_create_nonce('ajax_get'));?>';
                    let url = '<?php echo esc_url(admin_url('admin-ajax.php'));?>';
                    if (data.action === undefined) {
                        url = url + '?action=ajax_get';
                    }

                    if (route === undefined) {
                        return false
                    }

                    url = url + '&route_name=' + route;
                    return new Promise((resolve, reject) => {
                        axios.get(url, {params: data})
                            .then((data) => {
                                if (data.data.status_code !== undefined && data.data.status_code === 403) {
                                    kcShowErrorMessage('kivicare_server_error_msg','<?php echo esc_html__("Route not found","kc-lang"); ?>');
                                }
                                resolve(data)
                            })
                            .catch((error) => {
                                reject(error)
                                kcShowErrorMessage('kivicare_server_error_msg','<?php echo esc_html__("Internal server error","kc-lang"); ?>');
                            });
                    })
                }

                if($(document).find('#userRole').val()){
                    kcGetRegisterPageCustomField($(document).find('#userRole').val())
                }
                if (document.getElementById('CountryCode') !== null) {
                    getCountryCodeData();
                }
                
                getUserRegistrationFormData();

                if('<?php echo !is_user_logged_in(); ?>'){
                    $('.wp-block-kivi-care-register-login .kivi-widget ul li.tab-item a').on('click', function (e) {
                        e.preventDefault();
                        const tab_id = $(this).attr('href');
                        $('.wp-block-kivi-care-register-login .kivi-widget li.tab-item').removeClass('active');
                        $(this).parent().addClass('active');
                        $('.wp-block-kivi-care-register-login .kivi-widget form').addClass ('d-none');
                        $(tab_id).parent().removeClass('d-none')
                    });
                }

                if('<?php echo  kcGoogleCaptchaData('status') === 'on';?>'){
                    grecaptcha.ready(function() {
                        kcCreateRecaptcha();
                    });
                }

                function kcCreateRecaptcha(){
                    grecaptcha.execute('<?php echo esc_html(kcGoogleCaptchaData('site_key'));?>', {action:'validate_captcha'})
                        .then(function(token) {
                            // add token value to form
                            document.getElementById('g-recaptcha-response').value = token;
                        });
                }
                $(document).on('submit', '#kivicare-register-form', function (event) {
                    $('#kcCustomFieldsList .kivicare-required').prop('required', true);
                    $.each($('#kcCustomFieldsList').find(':input:checkbox').parent().parent(), function (key, value) {
                        let cbx_group = $(value).find(':input:checkbox');
                        if (cbx_group.is(":checked")) {
                            cbx_group.prop('required', false);
                        }
                    });
                    var result = {};
                    $.each($('#register :input').serializeArray(), function () {
                        result[this.name] = this.value;
                    });
                    var custom_fields = kivicareCustomFieldsData('kcCustomFieldsList');
                    kivicareButtonTextChange(this, '<?php echo esc_html__("Loading..."); ?>', true)
                    event.preventDefault();
                    $('#kivi-content').addClass('kc-position-relative')
                    $('#kivi-main-loader-overlay').removeClass('d-none')
                    $('#kivi-main-loader-overlay').addClass('kc-relative-center')
                    const registerData = {...result, ...{custom_fields}};
                    let formData = new FormData(this)
                    $.each(registerData, function (key, value) {
                    if (typeof (value) === 'object') {
                        value = JSON.stringify(value)
                    }
                        formData.append(key, value)
                    });
                    post('register_new_user',formData, true)
                        .then((response) => {
                            $('#kivi-content').removeClass('kc-position-relative')
                            $('#kivi-main-loader-overlay').addClass('d-none')
                            $('#kivi-main-loader-overlay').removeClass('kc-relative-center')
                            if('<?php echo  kcGoogleCaptchaData('status') === 'on';?>'){
                                kcCreateRecaptcha();
                            }
                            kivicareButtonTextChange(this, '<?php echo esc_html__("Register"); ?>', false)
                            if (response.data.status !== undefined && response.data.status === true) {
                                $('#kivicare-register-form').trigger("reset");
                                $('#kcCustomFieldsList').find('.appointment_widget_multiselect').each(function() {
                                    $(this).val([]).trigger('change');
                                } );
                                if(response.data.redirect !== undefined && response.data.redirect !== ''){
                                    setTimeout(() => {
                                        location.href = response.data.redirect
                                    }, 1000)
                                }else{
                                    kivicareShowSuccessMessage(response.data.message)
                                }
                            } else {
                                kcShowErrorMessage(response.data.message)
                            }
                        }).catch((error) => {
                        $('#kivi-content').removeClass('kc-position-relative')
                        $('#kivi-main-loader-overlay').addClass('d-none')
                        $('#kivi-main-loader-overlay').removeClass('kc-relative-center')
                        if('<?php echo  kcGoogleCaptchaData('status') === 'on';?>'){
                            kcCreateRecaptcha();
                        }
                        kivicareButtonTextChange(this, '<?php echo esc_html__("Register"); ?>', false)
                        kcShowErrorMessage('<?php echo esc_html__("Internal server error","kc-lang"); ?>')
                        console.log(error);
                    })
                })

                $(document).on('submit', '#kivicare-login-form', function (event) {
                    var result = {};
                    $.each($('#login :input').serializeArray(), function () {
                        result[this.name] = this.value;
                    });
                    event.preventDefault();
                    kivicareButtonTextChange(this, '<?php echo esc_html__("Loading..."); ?>', true)
                    $('#kivi-content').addClass('kc-position-relative')
                    $('#kivi-main-loader-overlay').removeClass('d-none')
                    $('#kivi-main-loader-overlay').addClass('kc-relative-center')
                    post('login_new_user', result, true)
                        .then((response) => {
                            $('#kivi-content').removeClass('kc-position-relative')
                            $('#kivi-main-loader-overlay').addClass('d-none')
                            $('#kivi-main-loader-overlay').removeClass('kc-relative-center')
                            kivicareButtonTextChange(this, '<?php echo esc_html__('Login', 'kc-lang'); ?>', false)
                            if (response.data.status !== undefined && response.data.status === true) {
                                setTimeout(() => {
                                    location.href = response.data.login_redirect_url;
                                }, 1000)
                            } else {
                                kcShowErrorMessage(response.data.message)
                            }
                        }).catch((error) => {
                        $('#kivi-content').removeClass('kc-position-relative')
                        $('#kivi-main-loader-overlay').addClass('d-none')
                        $('#kivi-main-loader-overlay').removeClass('kc-relative-center')
                        kivicareButtonTextChange(this, '<?php echo esc_html__('Login', 'kc-lang'); ?>', false)
                        console.log(error);
                        kcShowErrorMessage('<?php echo esc_html__("Internal server error","kc-lang"); ?>')
                    })
                })

                $(document).on('change','#userRole',function(event){
                    kcGetRegisterPageCustomField(this.value);
                })

                $('#kivi-main-loader').css('display','none');
                $('#kivi-content').css('display','');

                function kcGetRegisterPageCustomField(type){
                    let customFieldEle  = document.getElementById('kcCustomFieldsList')
                    customFieldEle.classList.add("kivi-center");
                    customFieldEle.innerHTML = '';
                    //Create spinner element
                    const spinner = document.createElement('div');
                    spinner.className = 'double-lines-spinner';
                    customFieldEle.appendChild(spinner);
                    
                    get('get_appointment_custom_field', {user_role: type}, true)
                        .then((res) => {
                            customFieldEle.classList.remove("kivi-center");
                            customFieldEle.innerHTML = ''
                            if(res.data.status !== undefined && res.data.status){
                                customFieldEle.innerHTML = res.data.data;
                                $('#kcCustomFieldsList').find('.appointment_widget_multiselect').each(function() {
                                    $(this).select2({
                                        placeholder: $(this).attr('placeholder'),
                                        allowClear:true,
                                        dropdownCssClass: 'kivicare-custom-dropdown-width'
                                    });
                                } );
                            }
                        }).catch((error) => {
                        customFieldEle.classList.remove("kivi-center");
                        customFieldEle.innerHTML = ''
                        console.log(error);
                    })
                }
                function getCountryCodeData() {
                    get('get_country_code_settings_data', {})
                        .then((response) => {
                            if (response.data.status !== undefined && response.data.status === true) {
                                var valueString = '{"countryCallingCode":"+' + response.data.data.country_calling_code + '","countryCode":"' + response.data.data.country_code + '"}';
                                jQuery('#CountryCode').val(valueString).trigger('change');
                            }
                        })
                        .catch((error) => {
                            console.log(error);
                            displayErrorMessage(this.formTranslation.common.internal_server_error);
                        })
                }
                function getUserRegistrationFormData() {
                    get('get_user_registration_form_settings_data', {})
                        .then((response) => {
                            console.log(response.data.data.userRegistrationFormSettingData);
                            if (response.data.status !== undefined && response.data.status === true) {
                                let userRegistrationFormSettingData = response.data.data.userRegistrationFormSettingData;
                                if (userRegistrationFormSettingData === 'on') {                                   
                                    $('#otherGenderOption').show();
                                } else {
                                    
                                    $('#otherGenderOption').hide();
                                }
                                return response.data.data.userRegistrationFormSettingData;
                            }
                        })
                        .catch((error) => {
                            console.log(error);
                            displayErrorMessage(this.formTranslation.common.internal_server_error);
                        })
                }
                function kivicareCustomFieldsData(ele){
                    var custom_fields = { };
                    $.each($('#'+ele).find('select, textarea, :input:not(:checkbox)').serializeArray(), function() {
                        custom_fields[this.name] = this.value;
                    });
                    var temp = [];
                    var temp2= '';
                    $.each($('#'+ele).find(':input:checkbox').serializeArray(), function(key,value) {
                        if(temp2 !== value.name){
                            temp = [];
                        }
                        temp.push(value.value)
                        custom_fields[value.name] = temp;
                        temp2 = value.name;
                    });
                    $('#'+ele).find('.appointment_widget_multiselect').each(function() {
                        custom_fields[$(this).attr('name')] = $(this).val().map((index)=>{
                            return { 'id': index, 'text' : index}
                        });                                                 
                    });
                    return custom_fields;
                }

                function kcShowErrorMessage(message){
                    let ele = $('#kivicare_server_error_msg');
                    ele.css('display','');
                    ele.empty();
                    ele.append(message)
                    setTimeout(() => {
                        ele.css('display','none');
                    }, 3000);
                }

                function kivicareShowSuccessMessage(message){
                    let ele = $('#kivicare_success_msg');
                    ele.css('display','');
                    ele.text('')
                    ele.text(message)
                    setTimeout(() => {
                        ele.css('display','none');
                    }, 3000);
                }

                function kivicareButtonTextChange(ele,txt,disabled){
                    $(ele).find('button').text(txt);
                    $(ele).find('button').prop('disabled',disabled);
                }

                $('#togglePassword').on('click', function (e) {
                    var passwordInput = document.getElementById("loginPassword");
                    var toggleIcon = document.getElementById("togglePassword");

                    if (passwordInput.type === "password") {
                        passwordInput.type = "text";
                        toggleIcon.classList.remove("fa-eye");
                        toggleIcon.classList.add("fa-eye-slash");
                    } else {
                        passwordInput.type = "password";
                        toggleIcon.classList.remove("fa-eye-slash");
                        toggleIcon.classList.add("fa-eye");
                    }
                });

                if (jQuery('select').length > 0) {
                    jQuery('select').each(function () {
                        jQuery(this).select2({
                            width: '100%'
                        });
                    });
                    jQuery('.select2-container').addClass('wide');
                }

            })(window.jQuery)
        }
    });
</script>
