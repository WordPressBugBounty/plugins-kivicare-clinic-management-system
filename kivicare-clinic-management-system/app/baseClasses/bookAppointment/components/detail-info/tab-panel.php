<style>
.custom-control.custom-radio.custom-control-inline {
    margin-right: 12px;
}
</style>
<div class="d-flex align-items-center justify-content-between">
    <div class="iq-kivi-tab-panel-title-animation">
       <h3 class="iq-kivi-tab-panel-title"> <?php echo esc_html__('Enter Details', 'kc-lang'); ?> </h3>
    </div>
</div>
<hr>
<ul class="nav-tabs">
    <li class="tab-item active">
        <a href="#kc_register" class="tab-link" id="register-tab"
           data-iq-toggle="tab"> <?php echo esc_html__('Register', 'kc-lang'); ?> </a>
    </li>
    <li class="tab-item">
        <a href="#kc_login" class="tab-link" id="login-tab"
           data-iq-toggle="tab"> <?php echo esc_html__('Login', 'kc-lang'); ?> </a>
    </li>
</ul>
<div class="widget-content">
    <div id="login-register-panel" class="card-list-data">
        <div id="kc_register" class="iq-tab-pannel kivicare-register-form-data iq-fade active authActive card-list" >
            <div id="kivicare-register-form">
                <div class="d-grid grid-template-2"  id="kivicare-register">
                    <?php if(kcGoogleCaptchaData('status') === 'on'){
                        ?>
                        <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">
                        <input type="hidden" name="captcha_action" value="validate_captcha">
                        <?php
                    }?>
                    <input type="hidden" name="widgettype" value="new_appointment_widget" id="widgettype">
                    <div class="form-group">
                        <input type="hidden" id="registerClinicId">
                        <label class="form-label" for="firstName"><?php echo esc_html__('First Name', 'kc-lang'); ?>
                            <span>*</span></label>
                        <input type="text" name="first_name" class="iq-kivicare-form-control" id="firstName"
                            placeholder="<?php echo esc_html__('Enter your first name', 'kc-lang'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="lastName"> <?php echo esc_html__('Last Name', 'kc-lang'); ?>
                            <span>*</span></label>
                        <input type="text" name="last_name" class="iq-kivicare-form-control" id="lastName"
                            placeholder="<?php echo esc_html__('Enter your last name', 'kc-lang'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="userEmail"><?php echo esc_html__('Email', 'kc-lang'); ?>
                            <span>*</span></label>
                        <input type="email" name="user_email" class="iq-kivicare-form-control" id="userEmail"
                            placeholder="<?php echo esc_html__('Enter your email', 'kc-lang'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="userContact"> <?php echo esc_html__('Contact', 'kc-lang'); ?>
                            <span>*</span></label>
                            <div class="contact-box-inline">
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
                            <?php
                            } else {
                            ?>
                                <input type="text" name="country_code" class="iq-kivicare-form-control" id="txt_CountryCode" placeholder="<?php echo esc_html__('Enter your country code', 'kc-lang'); ?>" required>
                            <?php
                            }
                            ?>


                            <input type="tel" name="mobile_number" class="iq-kivicare-form-control" id="userContact" placeholder="<?php echo esc_html__('Enter your contact number', 'kc-lang'); ?>" required>
                        </div>

                    </div>

                    <div class="form-group">
                        <label class="form-label" for="gender"><?php echo esc_html__('Gender', 'kc-lang'); ?>
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
                     do_action('kivicare_widget_register_form_field_add');
                    ?>
                </div>
                <div  id="customFieldsList">

                    <?php
                        kcGetCustomFieldsList('patient_module',0);
                    ?>

                </div>
            </div>
        </div>
        <div id="kc_login" class="iq-tab-pannel kivicare-login-form-data iq-fade authActive" >
            <div id="kivicare-login-form">
                <div class="d-grid grid-template-2" id="kivicare-login">
                    <div class="form-group">
                        <label class="form-label" for="loginUsername"><?php echo esc_html__('Username or Email', 'kc-lang'); ?>
                            <span>*</span></label>
                        <input type="text" name="username" class="iq-kivicare-form-control" id="loginUsername"
                            placeholder="<?php echo esc_html__('Enter your username or email', 'kc-lang'); ?>" >
                    </div>                  
                    <div class="form-group">
                        <label class="form-label" for="loginPassword"><?php echo esc_html__('Password', 'kc-lang'); ?>
                            <span>*</span></label>
                        <div class="password-container" style="position: relative;">
                        <input type="password" name="password" class="iq-kivicare-form-control" id="loginPassword" placeholder="***********" >
                        <i class="password-toggle fas fa-eye" id="togglePassword" style="position: absolute; top: 50%; right: 10px; transform: translateY(-50%); cursor: pointer;"></i>
                        </div>
                    </div>

                </div>
                <div class="d-flex justify-content-end w-100">
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" target="_blank" class="iq-color-secondary"><i><?php echo esc_html__('Forgot Password ?', 'kc-lang'); ?></i></a>
                </div>
            </div>
        </div>
    </div>
</div>
