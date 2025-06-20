function kcAppointmentBookJsContent(elementID) {
    var ShortcodeElement= document.querySelector(elementID);
    
    
    (function ($) {
        window.name = 'kivicareWidget';
        $(elementID+' #kivicare-widget-main-content').removeClass('d-none');
        $(elementID+' #kivicare-main-page-loader').addClass('d-none');
        if (bookAppointmentWidgetData.popup_appointment_book) {
            $(elementID+' .kivi-widget-close').on("click", function () {
                $.magnificPopup.close();
            });
        }


        if (window.matchMedia('(max-width: 768px)').matches) {
            $(elementID+'.kivi-widget').css('padding', '8px');
        } else {
            $(elementID+'.kivi-widget').css('padding', '16px');
        }
        const post = (route, data = {}, frontEnd = false, headers = {
            headers: { 'Content-Type': 'application/json' }
        }) => {

            validateBookAppointmentWidgetData(bookAppointmentWidgetData);

            window.ajaxurl = bookAppointmentWidgetData.ajax_url;
            window.nonce = bookAppointmentWidgetData.ajax_post_nonce;

            let url = ajaxurl;
            if (data.action === undefined) {
                url = ajaxurl + '?action=ajax_post';
            }

            if (route === undefined) {
                return false
            }

            if (data.append !== undefined) {
                data.append('route_name', route);
                data.append('_ajax_nonce', nonce)
            } else {
                data.route_name = route;
                data._ajax_nonce = nonce;
            }

            return new Promise((resolve, reject, headers) => {
                axios.post(url, data, headers)
                    .then((data) => {
                        if (data.data.status_code !== undefined && data.data.status_code === 403) {
                            kivicareShowErrorMessage('kivicare_server_error_msg', bookAppointmentWidgetData.message.route_not_found);
                        }
                        resolve(data)
                    })
                    .catch((error) => {
                        reject(error)
                        kivicareShowErrorMessage('kivicare_server_error_msg', bookAppointmentWidgetData.message.internal_server_msg);
                    });
            })
        }

        const get = (route, data, frontEnd = false) => {

            validateBookAppointmentWidgetData(bookAppointmentWidgetData);

            window.ajaxurl = bookAppointmentWidgetData.ajax_url;
            window.nonce = bookAppointmentWidgetData.ajax_get_nonce;

            data._ajax_nonce = bookAppointmentWidgetData.ajax_get_nonce;
            let url = ajaxurl;
            if (data.action === undefined) {
                url = ajaxurl + '?action=ajax_get';
            }

            if (route === undefined) {
                return false
            }

            url = url + '&route_name=' + route;
            return new Promise((resolve, reject) => {
                axios.get(url, { params: data })
                    .then((data) => {
                        if (data.data.status_code !== undefined && data.data.status_code === 403) {
                            kivicareShowErrorMessage('kivicare_server_error_msg', bookAppointmentWidgetData.message.route_not_found);
                        }
                        resolve(data)
                    })
                    .catch((error) => {
                        reject(error)
                        kivicareShowErrorMessage('kivicare_server_error_msg', bookAppointmentWidgetData.message.internal_server_msg);
                    });
            })
        }

        getCountryCodeData();
        getUserRegistrationFormData();
        jQuery('#CountryCode').select2({
            dropdownParent: jQuery('.contact-box-inline'),
            templateSelection: function(data, container) {
                var countrycodedata = JSON.parse(data.id);
                return countrycodedata.countryCallingCode;
            }
        });

        kivicareLoadConfirmPage(bookAppointmentWidgetData.print_confirm_page);
        
        kcInitMultiselectElement('customFieldsList');

        var timer = '';

        var appointmentUploadFiles = [];

        var appointment_custom_fields = {};

        var appointmentDate = '';

        var child = '';

        var payment_status = '';

        var appointment_id = '';

        var payment_select_mode = ''

        var userLogin = bookAppointmentWidgetData.user_login

        var service_clinic = [];

        var tax_details = []
        if (bookAppointmentWidgetData.print_confirm_page === 'off') {
            switch ($(elementID+' .iq-fade.iq-tab-pannel.active').attr('id')) {
                case 'clinic':
                    kivicareGetClinicsLists()
                    break;
                case 'doctor':
                    kivicareGetDoctorLists()
                    break;
                case 'category':
                    kivicareGetServiceLists()
                    break;
                case 'date-time':
                    kivicareGetDoctorWeekday(kivicareGetSelectedItem('selected-doctor'));
                    break;
            }
        }

        
        //logout button click event
        $(elementID).on('click', '#kivicare_logout_btn', function () {
            let logoutElement = $(elementID+" #kivicare_logout_btn");
            logoutElement.prop('disabled', true);
            logoutElement.html(bookAppointmentWidgetData.message.loading);
            post('logout', {}).then((response) => {
                if (response.data.status !== undefined && response.data.status === true) {
                    //wait 1 sec before reload
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                } else {
                    logoutElement.prop('disabled', false);
                    logoutElement.html(bookAppointmentWidgetData.message.logout);
                }
            }).catch((error) => {
                logoutElement.prop('disabled', false);
                logoutElement.html(bookAppointmentWidgetData.message.logout);
                kivicareShowErrorMessage('kivicare_error_msg', bookAppointmentWidgetData.message.internal_server_msg);
            })
        })

        $(elementID).on('click', '[data-toggle="active"]', function () {
            $(this).toggleClass('active');
        })

        //clinic search event
        $(elementID+" #clinicSearch").keyup(function () {
            kivicareGetClinicsLists(this.value)
        });

        //doctor search event
        $(elementID+" #doctorSearch").keyup(function () {
            kivicareGetDoctorLists(this.value)
        });

        //service search event
        $(elementID+" #serviceSearch").keyup(function () {
            kivicareGetServiceLists(this.value)
        });

        //check if google recaptcha enable
        if (bookAppointmentWidgetData.google_recaptcha_enable) {
            //add recaptcha in login/register tab
            grecaptcha.ready(function () {
                let tabs = ["detail-info"];
                for (let i = 0; i < tabs.length; i++) {
                    kcCreateGoogleRecaptcha(tabs[i])
                }
            });
        }

        function kcInitMultiselectElement(id){

            $(elementID+' #'+id).find('.appointment_widget_multiselect').each(function() {
                $(this).select2({
                    placeholder: $(this).attr('placeholder'),
                    allowClear:true,
                    dropdownCssClass: 'kivicare-custom-dropdown-width'
                });
            } );
        }

    /**
        * It's a function that creates a Google Recaptcha token for the current tab
        * @param currentTab - The ID of the tab you want to create the reCAPTCHA for.
        */
        function kcCreateGoogleRecaptcha(currentTab) {
            if (bookAppointmentWidgetData.google_recaptcha_enable) {
                grecaptcha.execute(bookAppointmentWidgetData.google_recatcha_site_key, { action: 'validate_captcha' })
                    .then(function (token) {
                        var tab = ShortcodeElement.querySelector("#"+currentTab);
                        $(tab).find("#g-recaptcha-response").val(token);
                    });
            }
        }

        //next button click event
        $(elementID).off('click', '[data-step="next"]');
        $(elementID).on('click', '[data-step="next"]',  (e)=> {
            let _this=e.currentTarget
            var perviousTab = $(elementID+' .iq-tab-pannel.active').find('form').attr('data-prev');
            var target = $(elementID+' .iq-tab-pannel.active').find('form').attr('action');
            removeTabActiveLink($(elementID+` [href="${target}"]`).closest('.tab-item'));
            var currentTab = $(elementID+' .iq-tab-pannel.active').find('form').closest('.iq-tab-pannel').attr('id')


            switch (currentTab) {
                case 'clinic':
                    e.preventDefault();
                    var selected_clinic = kivicareGetSelectedItem('selected-clinic');
                    if (selected_clinic == 0) {
                        kivicareShowErrorMessage('kivicare_error_msg_clinic', bookAppointmentWidgetData.message.select_clinic);
                        return;
                    } else {
                        if (target == '#doctor') {
                            kivicareGetDoctorLists()
                        } else if (target == '#category') {
                            service_clinic = [];
                            kivicareGetServiceLists()
                        }
                    }
                    break;
                case 'doctor':
                    e.preventDefault();
                    var selected_doctor = kivicareGetSelectedItem('selected-doctor');
                    if (selected_doctor == 0) {
                        kivicareShowErrorMessage('kivicare_error_msg_doctor', bookAppointmentWidgetData.message.select_doctor);
                        return;
                    } else {
                        if (target == '#category') {
                            service_clinic = [];
                            kivicareGetServiceLists()
                        } else if (target == '#clinic') {
                            kivicareGetClinicsLists()
                        }
                    }
                    break;
                case 'category':
                    e.preventDefault();
                    service_clinic = [];
                    var service_data = kivicareGetSelectedServie('single', 'doctor_id');
                    var selected = service_data.length;
                    if (selected == 0) {
                        kivicareShowErrorMessage('kivicare_error_msg_category', bookAppointmentWidgetData.message.select_category);
                        return;
                    } else {
                        if (target == '#doctor') {
                            kivicareGetDoctorLists()
                        } else if (target == '#clinic') {
                            kivicareGetClinicsLists()
                        }
                    }
                    break;
                case 'date-time':
                    e.preventDefault();
                    var select_time = kivicareGetSelectedItem('selected-time');
                    if (select_time == 0) {
                        kivicareShowErrorMessage('kivicare_error_msg_date_time', bookAppointmentWidgetData.message.select_date_and_time);
                        return;
                    }
                    if (userLogin) {
                        if (bookAppointmentWidgetData.extra_tab_show) {
                            target = '#file-uploads-custom';
                        } else {
                            target = '#confirm';
                        }
                    }
                    break;
                case 'file-uploads-custom':
                    $(elementID+' #customFieldsListAppointment .kivicare-required').prop('required', true);
                    $.each($(elementID+' #customFieldsListAppointment').find(':input:checkbox').parent().parent(),  (key, value)=> {
                        let cbx_group = $(value).find(':input:checkbox');
                        if (cbx_group.is(":checked")) {
                            cbx_group.prop('required', false);
                        }
                    });

                    if (!$(elementID+" #kivicare-file-upload-form")[0].checkValidity()) {
                        return
                    }
                    appointment_custom_fields = kivicareCustomFieldsData('customFieldsListAppointment');
                    if (userLogin) {
                        target = '#confirm';
                    }
                    window.requestAnimationFrame(function () {
                        var element = ShortcodeElement.querySelector("#CountryCode").parentElement;
                        var elementwidth = element.offsetWidth;
                        element.style.setProperty('--kc-country-code-width', elementwidth + 'px');
                    });
                    e.preventDefault();
                    break;
                case 'detail-info':

                    var formName = document.getElementsByClassName('authActive active')
                    for (var i = 0; i < formName.length; i++) {
                        if (formName[i].id == 'kc_login') {
                            $(elementID+' #kc_login #kivicare-login-form input').prop('required', true);
                            $(elementID+' #kc_register #kivicare-register-form input,textarea, select').prop('required', false);

                            if (!$(elementID+" #kiviLoginRegister")[0].checkValidity()) {
                                return
                            }

                            var result = {};
                            $.each($(elementID+' #kc_login #kivicare-login :input').serializeArray(), function (index, field) {
                                result[field.name] = field.value;
                            });
                            e.preventDefault();
                            kivicareButtonDisableChangeText('#kiviLoginRegister', true, bookAppointmentWidgetData.message.loading)
                            post('appointmentLogin', result, true)
                                .then((response) => {
                                    kivicareButtonDisableChangeText('#kiviLoginRegister', false, bookAppointmentWidgetData.message.login);
                                    if (response.data.status) {
                                        validateBookAppointmentWidgetData(bookAppointmentWidgetData);
                                        bookAppointmentWidgetData.ajax_get_nonce = response.data.token.get;
                                        bookAppointmentWidgetData.ajax_post_nonce = response.data.token.post;
                                        userLogin = true
                                        $(elementID+" #kivicare_logout_btn").removeClass('d-none');
                                        kivicareShowErrorMessage('kivicare_success_msg', response.data.message);
                                        showConfirmPage(target, currentTab)
                                        $(elementID+` [href="#${currentTab}"]`).closest('.tab-item').attr('data-check', true)
                                        $(elementID+` [href="${target}"]`).closest('.tab-item').addClass('active')
                                        tabShow(elementID+' '+target);

                                    } else {
                                        kivicareShowErrorMessage('kivicare_error_msg_login_register', response.data.message);
                                    }
                                }).catch((error) => {
                                    kivicareButtonDisableChangeText('#kiviLoginRegister', false, bookAppointmentWidgetData.message.login)
                                    console.log(error);
                                    kivicareShowErrorMessage('kivicare_error_msg_login_register', bookAppointmentWidgetData.message.internal_server_msg);
                                })
                        }
                        if (formName[i].id == 'kc_register') {
                            $(elementID+' #kc_login #kivicare-login-form input').prop('required', false);
                            $(elementID+' #kc_register #kivicare-register input').prop('required', true);
                            $(elementID+' #customFieldsList .kivicare-required').prop('required', true);
                            $.each($(elementID+' #customFieldsList').find(':input:checkbox').parent().parent(), function (key, value) {
                                let cbx_group = $(value).find(':input:checkbox');
                                if (cbx_group.is(":checked")) {
                                    cbx_group.prop('required', false);
                                }
                            });

                            if (!$(elementID+" #kiviLoginRegister")[0].checkValidity()) {
                                return
                            }

                            var result = {};
                            $.each($(elementID+' #kc_register #kivicare-register :input').serializeArray(), function () {
                                result[_this.name] = _this.value;
                            });

                            var custom_fields = kivicareCustomFieldsData('customFieldsList');
                            kivicareButtonDisableChangeText('#kiviLoginRegister', true, bookAppointmentWidgetData.message.loading)
                            e.preventDefault();
                            result['clinic'] = [
                                {
                                    id: kivicareGetSelectedItem('selected-clinic')
                                }
                            ];
                            const registerData = {...result, ...{custom_fields}};
                            let formData = new FormData(ShortcodeElement.querySelector('#kiviLoginRegister'))
                            $.each(registerData, function (key, value) {
                                if (typeof (value) === 'object') {
                                    value = JSON.stringify(value)
                                }
                                formData.append(key, value)
                            });
                            post('register', formData, true)
                                .then((response) => {
                                    kivicareButtonDisableChangeText('#kiviLoginRegister', false, bookAppointmentWidgetData.message.register)
                                    if (response.data.status) {
                                        validateBookAppointmentWidgetData(bookAppointmentWidgetData);
                                        bookAppointmentWidgetData.ajax_get_nonce = response.data.token.get;
                                        bookAppointmentWidgetData.ajax_post_nonce = response.data.token.post;
                                        userLogin = true
                                        $(elementID+" #kivicare_logout_btn").removeClass('d-none');
                                        kivicareShowErrorMessage('kivicare_success_msg', response.data.message);
                                        showConfirmPage(target, currentTab)
                                        $(elementID+` [href="#${currentTab}"]`).closest('.tab-item').attr('data-check', true)
                                        $(elementID+` [href="${target}"]`).closest('.tab-item').addClass('active')
                                        tabShow(elementID+' '+target);
                                    } else {
                                        kivicareShowErrorMessage('kivicare_error_msg_login_register', response.data.message);
                                        kcCreateGoogleRecaptcha(currentTab);
                                    }
                                }).catch((error) => {
                                    kivicareButtonDisableChangeText('#kiviLoginRegister', false, bookAppointmentWidgetData.message.register)
                                    console.log(error);
                                    kcCreateGoogleRecaptcha(currentTab);
                                    kivicareShowErrorMessage('kivicare_error_msg_login_register', bookAppointmentWidgetData.message.internal_server_msg);
                                })
                        }
                    }

                    return;
                    break;
                case 'confirm':
                    e.preventDefault();
                    if (target !== '#payment_mode') {
                        var result = [];
                        $.each($(elementID+' #confirm_detail_form :input').serializeArray(), function () {
                            result[_this.name] = _this.value;
                        });
                        kivicareBookAppointment(_this, bookAppointmentWidgetData.first_payment_method, result);
                        return;
                    } else {
                        showPaymentPage();
                    }
                    break;
                case 'payment_mode':
                    e.preventDefault();
                    if ($(elementID+' #payment_mode input:radio[name="payment_option"]:checked').length == 0) {
                        kivicareShowErrorMessage('kivicare_payment_mode_confirm', bookAppointmentWidgetData.message.select_payment_mode);
                    } else {
                        var result = [];
                        $.each($(elementID+' #payment_mode_form :input').serializeArray(), function () {
                            result[_this.name] = _this.value;
                        });
                        kivicareBookAppointment(_this, $(elementID+' #payment_mode input:radio[name="payment_option"]:checked').attr('id'), result);
                    }
                    return
                    break;
            }

            switch (target) {
                case '#confirm':
                    showConfirmPage(target, currentTab);
                    break;
                case '#file-uploads-custom':
                    if (bookAppointmentWidgetData.pro_plugin_active) {
                        ShortcodeElement.querySelector('#customFieldsListAppointment').innerHTML = ' ';
                        get('get_appointment_custom_field', { doctor_id: kivicareGetSelectedItem('selected-doctor'),service_id: kivicareGetSelectedServie('single', 'service_id') }, true)
                            .then((res) => {
                                if (res.data.status !== undefined && res.data.status) {
                                    ShortcodeElement.querySelector('#customFieldsListAppointment').innerHTML = validateDOMData(res.data.data);
                                    kcInitMultiselectElement('customFieldsListAppointment')
                                }
                            }).catch((error) => {
                                console.log(error);
                            })
                    }
                    break;
                case '#date-time':
                    kivicareGetDoctorWeekday(kivicareGetSelectedItem('selected-doctor'));
                    break;
                case '#confirmed':
                    jQuery('[href="#confirm"]').closest('.tab-item').removeClass('active');
                    break;
            }

            $(elementID+` [href="#${currentTab}"]`).closest('.tab-item').attr('data-check', true)
            $(elementID+` [href="${target}"]`).closest('.tab-item').addClass('active')
            
            if (target != '#confirm') {
                tabShow(elementID+' '+target);
            }
        })

        //previous button click event
        $(elementID).off('click', '[data-step="prev"]');
        $(elementID).on('click', '[data-step="prev"]', function (e) {
            e.preventDefault();
            let target = $(elementID+' .iq-tab-pannel.active').find('form').attr('data-prev');
            const currentTab = $(elementID+' .iq-tab-pannel.active').find('form').closest('.iq-tab-pannel').attr('id')
            $(elementID+` [href="#${currentTab}"]`).closest('.tab-item').attr('data-check', false)
            $(elementID+` [href="#${currentTab}"]`).closest('.tab-item').removeClass('active')
            if (currentTab == 'confirm') {
                if (bookAppointmentWidgetData.extra_tab_show) {
                    target = '#file-uploads-custom';
                } else {
                    target = '#date-time';
                }
            } else if (currentTab == 'detail-info') {
                if (bookAppointmentWidgetData.extra_tab_show) {
                    target = '#file-uploads-custom';
                } else {
                    target = '#date-time';
                }
            } else if (currentTab == 'clinic' || currentTab == 'doctor') {
                $(elementID+' .iq-tab-pannel.active').find('form').find('input[type="radio"]').prop('checked', false);
            } else if (currentTab == 'category') {
                $(elementID+' .iq-tab-pannel.active').find('form').find('input[type="checkbox"]').prop('checked', false);
                tabShow(elementID+' '+target);
            } else if (currentTab == 'date-time') {
                let timeslot = ShortcodeElement.querySelector("#timeSlotLists")
                timeslot.classList.remove('d-grid')
                timeslot.style.height = '100%';
                timeslot.parentNode.style.height = '400px';
                timeslot.innerHTML = `<p class="loader-class">` + bookAppointmentWidgetData.message.please_select_date + `</p>`
            }
            $(elementID+` [href="${target}"]`).closest('.tab-item').addClass('active')
            $(elementID+` [href="${target}"]`).closest('.tab-item').attr('data-check', false)
            tabShow(elementID+' '+target);

        })


        $(elementID).off('change', '.selected-service');
        $(elementID).on('change', '.selected-service', function (e){
            const selected_service_clinic = $(this).attr('clinic_id');
            const service_id = $(this).attr('value')
            if (this.checked) {
                if(service_clinic.length > 0){
                    if(service_clinic.findIndex(a => a.service_id === service_id && a.clinic_id === selected_service_clinic ) === -1) {
                        if(service_clinic.findIndex(a => a.clinic_id === selected_service_clinic ) === -1){
                            $(this).prop('checked', false);
                            return;
                        }
                    }else{
                        $(this).prop('checked', false);
                        return;
                    }
                }
                service_clinic.push({service_id:service_id,clinic_id:selected_service_clinic});
            }else{
                service_clinic.splice(service_clinic.findIndex(a => a.service_id === service_id && a.clinic_id === selected_service_clinic ) , 1)
            }
        });
        //change tab if service is single select
        $(elementID).off('change', '.selected-service-single');
        $(elementID).on('change', '.selected-service-single', function (e) {
            if (this.checked) {
                $(elementID+' .selected-service').prop('checked', false);
                $(this).prop('checked', true);
                if ($(this).attr('multipleservice') == 'no') {
                    $(elementID+' .selected-service').prop('disabled', true);
                    $(this).prop('disabled', false);
                }
                //move to next tab
                $(elementID+' .iq-tab-pannel.active').find('form').find('[data-step="next"]').trigger('click');
            } else {
                if ($(this).attr('multipleservice') == 'no') {
                    $(elementID+' .selected-service').prop('disabled', false);
                }
            }
        })

        //move to next tab when doctor select
        $(elementID).off('change', '.kivicare-doctor-widget');
        $(elementID).on('change', '.kivicare-doctor-widget', function (e) {
            $(elementID+' .iq-tab-pannel.active').find('form').find('[data-step="next"]').trigger('click');
        })

        //move to next tab when timeslot selected
        $(elementID).off('change', '.selected-time');
        $(elementID).on('change', '.selected-time', function (e) {
            $(elementID+' .iq-tab-pannel.active').find('form').find('[data-step="next"]').trigger('click');
        });

        //move to next tab when clinic selected
        $(elementID).off('change', '.selected-clinic');
        $(elementID).on('change', '.selected-clinic', function (e) {
            $(elementID+' .iq-tab-pannel.active').find('form').find('[data-step="next"]').trigger('click');
        })

        //get appointment confirmation page details
        function showConfirmPage(target, currentTab) {
            var selectedService = kivicareGetSelectedServie('single', 'service_id');;
            let description = ShortcodeElement.querySelector('#appointment-descriptions-field');
            description = description !== null ? description.value : ''
            $(elementID+' #kivi_confirm_page').addClass('d-none')
            $(elementID+' #confirm_loader').removeClass('d-none')
            ShortcodeElement.querySelector('#kivi_confirm_page').innerHTML = ''
            setTimeout(() => {
                post('appointment_confirm_page', { clinic_id: kivicareGetSelectedItem('selected-clinic'), doctor_id: kivicareGetSelectedItem('selected-doctor'), service_list: selectedService, time: kivicareGetSelectedItem('selected-time'), date: appointmentDate, description: description, file: appointmentUploadFiles, custom_field: appointment_custom_fields })
                    .then((response) => {
                        $(elementID+' #kivi_confirm_page').removeClass('d-none')
                        $(elementID+' #confirm_loader').addClass('d-none')
                        if (response.data.status) {
                            ShortcodeElement.querySelector('#kivi_confirm_page').innerHTML = validateDOMData(response.data.data);
                            tax_details = response.data.tax_details
                            if (response.data.service_charges == 0) {
                                $('#confirm_detail_form button[name="submit"]').text('Confirm');
                                $('#confirm_detail_form').attr('action', '#confirmed');
                            };
                        }
                    }).catch((error) => {
                        $(elementID+' #kivi_confirm_page').removeClass('d-none')
                        $(elementID+' #confirm_loader').addClass('d-none')
                        console.log(error);
                        kivicareShowErrorMessage('kivicare_error_msg_confirm', bookAppointmentWidgetData.message.internal_server_msg);
                    })

                $(elementID+` [href="#${currentTab}"]`).closest('.tab-item').attr('data-check', true)
                $(elementID+` [href="${target}"]`).closest('.tab-item').addClass('active')
                tabShow(elementID+' '+target);
            }, bookAppointmentWidgetData.user_login ? 100 : 1000)
        }

        // register/login tab navbar change event
        $(elementID+' [data-iq-toggle="tab"]').on('click', function (e) {
            if ($(this).attr('href') === '#kc_login') {
                $(this).closest('form').find('button[data-step="next"]').html(bookAppointmentWidgetData.message.login);
            }
            if ($(this).attr('href') === '#kc_register') {
                $(this).closest('form').find('button[data-step="next"]').html(bookAppointmentWidgetData.message.register);
            }

            e.preventDefault();
            const tab_id = $(this).attr('href');
            if ($(this).attr('data-iq-tab') !== 'prevent') {
                $(this).closest('.tab-item').find('.tab-link.active').removeClass('active')
                activeNavItem($(this));
                tabShow(elementID+' '+tab_id);
                removeTabActiveLink($(this));
            }
        });

        //add active navitem class
        function activeNavItem(navlink) {
            $(navlink).addClass('active');
            $(navlink).closest('.tab-item').addClass('active');
        }

        //remove not active navitems class
        function removeTabActiveLink(target) {
            $(target).closest('.tab-item').siblings().removeClass('active')
        }

        // Model script
        $(elementID).on('click', '[data-toggle="modal"]', function () {
            const target = $(this).data('target');
            showModal(target);
        })

        function showModal(target) {
            $(elementID+' .modal').removeClass('show');
            $(target).addClass('show');
            const event = new CustomEvent('modalShown', {
                detail: {
                    target: target,
                },
            })
            document.dispatchEvent(event);
        }


        //get doctor list from api
        function kivicareGetDoctorLists(searchKey_in = '') {
            let doctorLists = ShortcodeElement.querySelector("#doctorLists");
            doctorLists.classList.remove('card-list');
            kivicareAddLoader(doctorLists);
            var service_id = kivicareGetSelectedServie('single', 'service_id');
            if(service_id.length == 0 && bookAppointmentWidgetData.preselected_service != 0){
                service_id = [bookAppointmentWidgetData.preselected_service]
            }
            get('get_clinic_selected_details', {
                clinic_id: kivicareGetSelectedItem('selected-clinic'),
                service_id: service_id,
                searchKey: searchKey_in,
                preselected_doctor: bookAppointmentWidgetData.preselected_doctor
            }, true)
                .then((res) => {
                    doctorLists.innerHTML = '';
                    let html;
                    if (res.data.status !== undefined && res.data.status) {
                        doctorLists.classList.add('card-list');
                        doctorLists.innerHTML = validateDOMData(res.data.data);
                    } else {
                        doctorLists.innerHTML = `<p class="loader-class">` + bookAppointmentWidgetData.message.no_doctor_available + `</p>`
                    }
                }).catch((error) => {
                    console.log(error);
                    kivicareShowErrorMessage('kivicare_error_msg', bookAppointmentWidgetData.message.internal_server_msg);
                })
        }

        function kivicareGetClinicsLists(searchKey_in = '') {
            
            let clinicCard = ShortcodeElement.querySelector("#clinicLists");
            console.log(clinicCard);
            
            clinicCard.classList.remove('card-list');
            kivicareAddLoader(clinicCard);
            var service_id = kivicareGetSelectedServie('single', 'service_id');
            if(service_id.length == 0 && bookAppointmentWidgetData.preselected_service != 0){
                service_id = [bookAppointmentWidgetData.preselected_service]
            }
            get('get_clinic_details_appointment', {
                doctor_id: kivicareGetSelectedItem('selected-doctor'),
                service_id: service_id,
                searchKey: searchKey_in,
                preselected_clinic: bookAppointmentWidgetData.preselected_clinic_id
            }, true)
                .then((res) => {
                    clinicCard.innerHTML = '';
                    if (res.data.status !== undefined && res.data.status) {
                        clinicCard.classList.add('card-list');
                        clinicCard.innerHTML = validateDOMData(res.data.data);
                    } else {
                        clinicCard.innerHTML = `<p class="loader-class"> ` + bookAppointmentWidgetData.message.no_clinic_available + ` </p>`
                    }
                }).catch((error) => {
                    console.log(error);
                    kivicareShowErrorMessage('kivicare_error_msg', bookAppointmentWidgetData.message.internal_server_msg);
                })
        }

        //get service list from api
        function kivicareGetServiceLists(searchKey_in) {
            let serviceLists = ShortcodeElement.querySelector("#serviceLists");
            
            kivicareAddLoader(serviceLists);
            get('service_list', {
                doctor_id: kivicareGetSelectedItem('selected-doctor'),
                searchKey: searchKey_in,
                widgetType: 'phpWidget',
                clinic_id: kivicareGetSelectedItem('selected-clinic'),
                preselected_service: bookAppointmentWidgetData.preselected_service
            }, true)
                .then((res) => {
                    serviceLists.innerHTML = '';
                    if (res.data.status !== undefined && res.data.status) {
                        serviceLists.innerHTML = validateDOMData(res.data.html);
                        var service_data = kivicareGetSelectedServie('single', 'doctor_id');

                        if(bookAppointmentWidgetData.skip_service_when_single == true && service_data.length !== 0){
                            if(searchKey_in == undefined || searchKey_in == null || searchKey_in == ''){
                                ShortcodeElement.querySelector('[data-step="next"]').click();
                            }
                        }
                        
                    } else {
                        serviceLists.innerHTML = `<p class="loader-class">` + bookAppointmentWidgetData.message.no_service_available + `</p>`
                    }
                }).catch((error) => {
                    console.log(error);
                    kivicareShowErrorMessage('kivicare_error_msg', bookAppointmentWidgetData.message.internal_server_msg);
                })
        }

        //get selected/checked value id (clinic/doctor)
        function kivicareGetSelectedItem(element) {
            let defaultSelected = 0;
            if (element === 'selected-clinic') {
                if (bookAppointmentWidgetData.preselected_single_clinic_id) {
                    return bookAppointmentWidgetData.preselected_clinic_id
                }
            }

            if (element === 'selected-doctor') {
                //return doctor id already in pass in shortcode or by query parameters
                if (bookAppointmentWidgetData.preselected_single_doctor_id) {
                    return bookAppointmentWidgetData.preselected_doctor
                }
            }

            let tempElement = $(elementID+' .' + element);
            if (tempElement.length > 0) {
                for (let i = 0; i < tempElement.length; i++) {
                    if (tempElement[i].checked == true) {
                        defaultSelected = tempElement[i].value;
                    }
                }
            }
            return defaultSelected;
        }

        //get selected service
        function kivicareGetSelectedServie(type = 'all', value = '') {   
            var service_id = [];
            var visit_type;
            if(bookAppointmentWidgetData.selected_service_id_data != null){
                let service_single_data = bookAppointmentWidgetData.selected_service_id_data;
                if (type === 'all') {
                    visit_type = {
                        'id': service_single_data.id,
                        'service_id': service_single_data.service_id,
                        'name': service_id.name,
                        'charges': service_id.charges
                    }
                    service_id.push(visit_type)
                } else {
                    service_single_data.service_id && service_id.push(service_single_data.service_id)
                }
                return service_id;
            }else{
                var name = $(elementID+' .selected-service');
                if (name.length > 0) {
                    for (var i = 0; i < name.length; i++) {
                        if (name[i].checked == true) {
                            if (type === 'all') {
                                visit_type = {
                                    'id': name[i].value,
                                    'service_id': name[i].attributes.service_id.nodeValue,
                                    'name': name[i].attributes.service_name.nodeValue,
                                    'charges': name[i].attributes.service_price.nodeValue,
                                    'telemed_service': name[i].attributes.telemed_service.nodeValue,
                                    
                                }
                                service_id.push(visit_type)
                            } else {
                                service_id.push(name[i].attributes[value].nodeValue)
                            }
                        }
                    }
                }

                return service_id;
            }
        }


        //show error message
        function kivicareShowErrorMessage(element, message) {
            ShortcodeElement.querySelector("#"+element).style.display = 'block';
            if (message !== '') {
                ShortcodeElement.querySelector("#"+element).innerHTML = message;
            }
            setTimeout(() => {
                ShortcodeElement.querySelector("#"+element).style.display = 'none';
            }, 3000);
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
                    kivicareShowErrorMessage('kivicare_error_msg', bookAppointmentWidgetData.message.internal_server_msg);
                })
        }

        function getUserRegistrationFormData() {
            get('get_user_registration_form_settings_data', {})
                .then((response) => {
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
                    kivicareShowErrorMessage('kivicare_error_msg', bookAppointmentWidgetData.message.internal_server_msg);
                })
        }

        //get doctor working days array
        function kivicareGetDoctorWeekday(id) {
            $(elementID+' #doctor-datepicker-loader').removeClass('d-none');
            $(elementID+' .doctor-session-error').addClass('d-none');
            $(elementID+' .iq-kivi-calendar-slot').addClass('d-none')
            let selected_clinic = kivicareGetSelectedItem('selected-clinic');
            let doctorWorkdayajaxData = {
                clinic_id: selected_clinic, 
                doctor_id: id,
                type: 'flatpicker'
            }
            get('get_doctor_workdays', doctorWorkdayajaxData)
                .then((response) => {
                    if (response.data.status !== undefined && response.data.status === true) {
                        let restrictionData = bookAppointmentWidgetData.restriction_data;
                        let days = response.data.data;
                        let leaves = response.data.holiday;

                        // If doctor does not have any working days, show message and hide date picker
                        if ([0, 1, 2, 3, 4, 5, 6].every(r => days.includes(r))) {
                            $(elementID+' .doctor-session-error').removeClass('d-none');
                            $(elementID+' .doctor-session-loader').addClass('d-none');
                            $(elementID+' #doctor-datepicker-loader').addClass('d-none');
                            $(elementID+' .iq-kivi-calendar-slot').addClass('d-none');
                        } else {
                            $(elementID+' .iq-kivi-calendar-slot').removeClass('d-none');
                            $(elementID+' .doctor-session-loader').addClass('d-none');
                            $(elementID+' .doctor-session-error').addClass('d-none');
                            $(elementID+' #doctor-datepicker-loader').addClass('d-none');

                            // Initialize datepicker
                            flatpickr(".iq-inline-datepicker", {
                                // Inline flatpicker
                                inline: true,
                                minDate: restrictionData.only_same_day_book === 'on' ? new Date() : new Date().fp_incr(restrictionData.pre_book),
                                maxDate: restrictionData.only_same_day_book === 'on' ? new Date() : new Date().fp_incr(restrictionData.post_book),
                                disable: [
                                    function (date) {
                                        // Disable days based on working days
                                        return ((days.includes(0) && date.getDay() === 0)
                                            || (days.includes(1) && date.getDay() === 1)
                                            || (days.includes(2) && date.getDay() === 2)
                                            || (days.includes(3) && date.getDay() === 3)
                                            || (days.includes(4) && date.getDay() === 4)
                                            || (days.includes(5) && date.getDay() === 5)
                                            || (days.includes(6) && date.getDay() === 6)
                                        );
                                    },
                                    // Disable leave dates
                                    ...leaves.map(leave => new Date(leave))
                                ],
                                locale: bookAppointmentWidgetData.message.full_calendar,
                                shorthandCurrentMonth: true,
                                onChange: function (selectedDates, dateStr, instance) { // Event when date selected in calendar
                                    let timeSlotListsElement = ShortcodeElement.querySelector("#timeSlotLists");
                                    timeSlotListsElement.classList.remove('d-grid');
                                    kivicareAddLoader(timeSlotListsElement);
                                    let selected_clinic = kivicareGetSelectedItem('selected-clinic');
                                    let selected_doctor = kivicareGetSelectedItem('selected-doctor');
                                    $(elementID+' #timeSlotLists').css('height', '100%');
                                    $(elementID+' #timeSlotLists').parent().css('height', '400px');
                                    appointmentDate = dateStr;
                                    var visit_type_data = kivicareGetSelectedServie('all', '');
                                    let timeSlotAjaxData = {
                                        doctor_id: selected_doctor,
                                        clinic_id: selected_clinic,
                                        date: dateStr,
                                        widgetType: 'phpWidget',
                                        service: visit_type_data
                                    };
                                    // Get selected date time slots
                                    get('get_time_slots', timeSlotAjaxData)
                                        .then((res) => {
                                            if (res.data.status !== undefined && res.data.status) {
                                                $(elementID+' #timeSlotLists').css('height', '');
                                                $(elementID+' #timeSlotLists').parent().css('height', '');
                                                timeSlotListsElement.classList.add('d-grid');
                                                timeSlotListsElement.innerHTML = validateDOMData(res.data.html);
                                            } else if (res.data.status !== undefined && !res.data.status) {
                                                timeSlotListsElement.innerHTML = `<p class="loader-class">` + res.data.message + `</p>`;
                                            }
                                        })
                                        .catch((error) => {
                                            console.log(error);
                                            kivicareShowErrorMessage('kivicare_error_msg', bookAppointmentWidgetData.message.internal_server_msg);
                                        });

                                    $(elementID+' .iq-inline-datepicker').addClass('d-none');
                                },
                            });
                        }
                    }
                })
                .catch((error) => {
                    console.log(error);
                    kivicareShowErrorMessage('kivicare_error_msg', bookAppointmentWidgetData.message.internal_server_msg);
                });

        }

        //function to get loader content
        function kivicareAddLoader(ele) {
            let load = bookAppointmentWidgetData.loader_by_image ? '<img src="' + bookAppointmentWidgetData.loader_image_url + '">' : '<div class="double-lines-spinner"></div>';
            ele.innerHTML = `<span class="loader-class" id="doctor_loader">` + load + `</span>`
        }

        //appointment file upload
        $(elementID).off('change', '#kivicareaddMedicalReport');
        $(elementID).on('change', '#kivicareaddMedicalReport', function (e) {
            ShortcodeElement.querySelector('#kivicare_file_upload_review').innerHTML = '';
            appointmentUploadFiles = [];
            let form_id = ShortcodeElement.querySelector('#kivicare-file-upload-form')
            let formData = new FormData(form_id);
            kivicareButtonDisableChangeText('#kivicare-file-upload-form', true, bookAppointmentWidgetData.message.loading)
            //api to upload files and get upload files attachment ids
            post('upload_multiple_report', formData)
                .then((response) => {
                    kivicareButtonDisableChangeText('#kivicare-file-upload-form', false, bookAppointmentWidgetData.message.next)
                    if (response.data.status !== undefined && response.data.status === true) {
                        if (response.data.data.length > 0) {
                            kivicareShowErrorMessage('kivicare_success_msg', response.data.message);
                            appointmentUploadFiles = response.data.data
                            ShortcodeElement.querySelector('#kivicare_file_upload_review').innerHTML = validateDOMData(response.data.html);
                        }
                    } else {
                        kivicareShowErrorMessage('kivicare_error_msg', response.data.message);
                    }
                })
                .catch((error) => {
                    kivicareButtonDisableChangeText('#kivicare-file-upload-form', false, bookAppointmentWidgetData.message.next)
                    kivicareShowErrorMessage('kivicare_error_msg', bookAppointmentWidgetData.message.internal_server_msg);
                })
        })

        //get custom fields data
        function kivicareCustomFieldsData(ele) {
            var custom_fields = {};
            $.each($(elementID+' #' + ele).find('select, textarea, :input:not(:checkbox)').serializeArray(), function () {
                custom_fields[this.name] = this.value;
            });
            var temp = [];
            var temp2 = '';
            $.each($(elementID+' #' + ele).find(':input:checkbox').serializeArray(), function (key, value) {
                if (temp2 !== value.name) {
                    temp = [];
                }
                temp.push(value.value)
                custom_fields[value.name] = temp;
                temp2 = value.name;
            });
            $(elementID+' #'+ele).find('.appointment_widget_multiselect').each(function() {
                custom_fields[$(this).attr('name')] = $(this).val().map((index)=>{
                    return { 'id': index, 'text' : index}
                });                                                 
            });
            return custom_fields;
        }

        //save appointment function
        function kivicareBookAppointment(_this, payment_mode, result = []) {
            payment_select_mode = payment_mode;
            var visit_type_data = kivicareGetSelectedServie('all', '');
            let description = ShortcodeElement.querySelector('#appointment-descriptions-field');
            description = description !== null ? description.value : ''

            let formElement = $(_this).parents('form');
            let opacityChangeElement = formElement;
            let messageSpanElementId = $(formElement).find('.card-widget-footer span').attr('id');
            let overlaySpinElement = $(formElement).find('.kivi-overlay-spinner');
            if (formElement.attr('id') !== 'payment_mode_form') {
                opacityChangeElement = $(formElement).find('.kivi-col-6')
            }
            $(opacityChangeElement).css('opacity', '0.5')
            kivicareButtonDisableChangeText(formElement, true, bookAppointmentWidgetData.message.loading);
            kivicareButtonDisableBackButton(formElement, true);
            $(overlaySpinElement).removeClass('d-none');
            console.log(messageSpanElementId);
            
            ShortcodeElement.querySelector("#"+messageSpanElementId).style.display = 'none';

            post('save_appointment', {
                appointment_start_date: appointmentDate,
                appointment_start_time: kivicareGetSelectedItem('selected-time'),
                visit_type: visit_type_data,
                patient_id: bookAppointmentWidgetData.current_user_id,
                doctor_id: { id: kivicareGetSelectedItem('selected-doctor') },
                clinic_id: { id: kivicareGetSelectedItem('selected-clinic') },
                status: 1,
                enableTeleMed: '',
                file: appointmentUploadFiles,
                custom_fields: appointment_custom_fields,
                description: description,
                widgetType: bookAppointmentWidgetData.popup_appointment_book ? 'popupPhpWidget' : 'phpWidget',
                payment_mode: payment_mode,
                g_recaptcha_response: result ? result["g-recaptcha-response"] ? result["g-recaptcha-response"] : "" : "",
                tax:tax_details,
                pageId: bookAppointmentWidgetData.pageId
            })
                .then((response) => {
                    if (response.data.status !== undefined && response.data.status === true) {
                        let checkWoocommerceCart = response.data;
                        if (checkWoocommerceCart.woocommerce_cart_data !== undefined) {
                            if (checkWoocommerceCart.woocommerce_cart_data.woocommerce_redirect !== undefined) {
                                if (payment_mode === 'paymentPaypal') {
                                    location.href = checkWoocommerceCart.woocommerce_cart_data.woocommerce_redirect;
                                    appointment_id = response.data.data.id
                                    return;
                                } else {
                                    location.href = checkWoocommerceCart.woocommerce_cart_data.woocommerce_redirect;
                                }
                            }
                        } else {
                            if (payment_mode === 'paymentRazorpay') {
                                if (response.data.checkout_detail) {
                                    kivicareCreateRazorpayCheckoutPage(response.data.checkout_detail)
                                } else {
                                    kivicareShowErrorMessage(messageSpanElementId, response.data.message);
                                }
                            } else if (payment_mode === 'paymentStripepay') {
                                if (response.data.checkout_detail) {
                                    // Open Stripe Checkout in a tab
                                    appointment_id = response.data.data.id;
                                    window.location.href = response.data.checkout_detail.stripe_redirect_url;
                                    return;
                                } else {
                                    kivicareShowErrorMessage(messageSpanElementId, response.data.message);
                                }
                            }else {
                                kivicareLoadConfirmPage(response.data.data.id)
                            }
                        }
                    } else {
                        let message = response.data.message !== undefined ? response.data.message : bookAppointmentWidgetData.message.internal_server_msg;
                        kivicareShowErrorMessage(messageSpanElementId, message);
                    }
                    kivicareButtonDisableChangeText(formElement, false, bookAppointmentWidgetData.message.confirm);
                    $(opacityChangeElement).css('opacity', '');
                    $(overlaySpinElement).addClass('d-none');
                }).catch((error) => {
                    kivicareButtonDisableChangeText(formElement, false, bookAppointmentWidgetData.message.confirm);
                    $(opacityChangeElement).css('opacity', '');
                    $(overlaySpinElement).addClass('d-none');
                    kivicareShowErrorMessage(messageSpanElementId, bookAppointmentWidgetData.message.internal_server_msg);
                    console.log(error);
                });
        }

        //check child window close (open for paypay payment )
        function checkChildWindow() {
            //if child window closed and payment failed
            if (child.closed && payment_status === '') {
                clearInterval(timer);
                let formElement = jQuery('#payment_mode_form')
                kivicareButtonDisableChangeText(formElement, false, bookAppointmentWidgetData.message.confirm);
                jQuery(formElement).css('opacity', '');
                jQuery(formElement).find('.kivi-overlay-spinner').addClass('d-none');
                tabShow(elementID+' '+'#payment_error');
            }
        }

        //close child window if parent window close
        window.onbeforeunload = function () {
            if (child !== '') {
                child.close()
            }
        }

        //focus child window on loader click
        $(elementID).on('click', '#payment_mode_confirm_loader', function (e) {
            e.preventDefault();
            if (child !== '') {
                child.focus()
            }
        })

        //get appointment payment page content
        function showPaymentPage(target, currentTab) {
            var selectedService = kivicareGetSelectedServie('all', '');
            let description = ShortcodeElement.querySelector('#appointment-descriptions-field');
            description = description !== null ? description.value : ''
            post('get_widget_payment_options', { clinic_id: kivicareGetSelectedItem('selected-clinic'), doctor_id: kivicareGetSelectedItem('selected-doctor'), service_list: selectedService, time: kivicareGetSelectedItem('selected-time'), date: appointmentDate, description: description, file: appointmentUploadFiles, custom_field: appointment_custom_fields })
                .then((response) => {
                    $(elementID+' #kivi_confirm_payment_page').removeClass('d-none')
                    $(elementID+' #confirm_loader').addClass('d-none')
                    if (response.data.status) {
                        ShortcodeElement.querySelector('#kivi_confirm_payment_page').innerHTML = validateDOMData(response.data.data);
                    }
                }).catch((error) => {
                    $(elementID+' #kivi_confirm_payment_page').removeClass('d-none')
                    $(elementID+' #confirm_loader').addClass('d-none')
                    console.log(error);
                    kivicareShowErrorMessage('kivicare_error_msg_confirm', bookAppointmentWidgetData.message.internal_server_msg);
                })

            $(elementID+` [href="#${currentTab}"]`).closest('.tab-item').attr('data-check', true)
            $(elementID+` [href="${target}"]`).closest('.tab-item').addClass('active')
            tabShow(elementID+' '+target);
        }

    })(window.jQuery)
}

//check payment complete by razorpay or PayPal gateway
function kivicareCheckPaymentStatus(newStatus, newAppointmentID) {
    switch (newStatus) {
        case 'approved':
            kivicareLoadConfirmPage(newAppointmentID)
            break;
        case 'failed':
            let formElement = jQuery('#payment_mode_form')
            kivicareButtonDisableChangeText(formElement, false, bookAppointmentWidgetData.message.confirm);
            jQuery(formElement).css('opacity', '');
            jQuery(formElement).find('.kivi-overlay-spinner').addClass('d-none');
            tabShow('#payment_error');
            break;
    }
}

//load appointment confirmation page
function kivicareLoadConfirmPage(value) {
    if (value !== 'off') {
        kivicarePrintContent(value);
        if (jQuery('#widgetOrders').length > 0) {
            jQuery('#widgetOrders').find('ul li').each(function (key, value) {
                jQuery(this).removeClass('active')
                jQuery(this).attr('data-check', true)
            })
            jQuery('#widgetOrders').find('.widget-pannel #wizard-tab').each(function (key, value) {
                jQuery(this).find('div').removeClass('active')
                if (jQuery(this).find('#confirmed')) {
                    jQuery(this).find('#confirmed').addClass('active')
                }
            })
        }
    }
}

var prr = '';

var config = '';

//get appointment print content after successfull booking
function kivicarePrintContent(id) {

    validateBookAppointmentWidgetData(bookAppointmentWidgetData);

    axios.get(bookAppointmentWidgetData.ajax_url + '?action=ajax_get&route_name=get_appointment_print&id=' + id + '&calendar_enable=yes&_ajax_nonce=' + bookAppointmentWidgetData.ajax_get_nonce)
        .then((response) => {
            if (response.data.status !== undefined && response.data.status === true) {
                prr = response.data.data;

                if (response.data.patient_id_match !== undefined && response.data.patient_id_match === true) {
                    const printButton = document.querySelector('#kivicare_print_detail');
                    if (response.data.calendar_content !== undefined && response.data.calendar_content !== '') {
                        printButton.classList.remove('d-none');
                        config = response.data.calendar_content
                        const button = document.querySelector('#kivicare_add_to_calendar');
                        button.classList.remove('d-none');
                        button.addEventListener('click', () => atcb_action(config, button))
                    }
                }else if (response.data.patient_id_match === false) {
                    // Get the current URL
                    const currentUrl = window.location.href;
                
                    // Check if the URL contains "?confirm_page="
                    if (currentUrl.includes('?confirm_page=')) {
                        // Remove the query parameter and redirect
                        const newUrl = currentUrl.split('?')[0];
                        window.location.href = newUrl;
                    }
                }
            }
        })
        .catch((error) => {
            console.log(error);

        })
}

//appointment print click event
jQuery(document).on('click', '#kivicare_print_detail', function () {
    jQuery(prr).printArea({});
})

//tabshow function
function tabShow(target) {
    let $ = jQuery;
    jQuery(target).addClass('active').siblings().removeClass('active');
    const tab = jQuery(target).closest('.tab-content').attr('id');
    const event = new CustomEvent(`tabShown-${tab}`, {
        detail: {
            target: target,
        },
    })
    document.dispatchEvent(event);
}

//enable/disable button
function kivicareButtonDisableChangeText(ele, disableEnable, buttonText) {
    let element = jQuery(ele).find('button[type="submit"]');
    element.prop('disabled', disableEnable);
    element.html(buttonText);
}

//disable back button
function kivicareButtonDisableBackButton(ele, disableEnable) {
    let element = jQuery(ele).find('button[id="iq-widget-back-button"]');
    element.prop('disabled', disableEnable);
}

function kivicareFileUploadSizeCheck(event){
    if (event.target.files && event.target.files.length > 0
        && event.target.files[0].size > bookAppointmentWidgetData.allowed_file_size) {
        jQuery(event.target).css('border','1px solid var(--iq-secondary-dark)');
        jQuery(event.target).siblings('div').css('display','block');
        event.target.value = ''; // Clear the file input field
    }else{
        jQuery(event.target).css('border','1px solid #eee');
        jQuery(event.target).siblings('div').css('display','none');
    }
}

function validateBookAppointmentWidgetData(bookAppointmentWidgetData){

    if (!bookAppointmentWidgetData.ajax_url) {
        console.error("ajax_url is required.");
        return;
    }
    
    var urlPattern = /^(?:https?):\/\/[\S]+$/;
    if (!urlPattern.test(bookAppointmentWidgetData.ajax_url)) {
        console.error("ajax_url is not a valid URL:", bookAppointmentWidgetData.ajax_url);
        return;
    }

    if (!bookAppointmentWidgetData.ajax_post_nonce) {
        console.error("ajax_post_nonce is required.");
        return;
    }

    var noncePattern = /^[a-zA-Z0-9_-]{10,}$/;

    if(!noncePattern.test(bookAppointmentWidgetData.ajax_post_nonce)){
        console.error("ajax_post_nonce is not a valid");
        return;
    }

    if (!bookAppointmentWidgetData.ajax_get_nonce) {
        console.error("ajax_get_nonce is required.");
        return;
    }

    if(!noncePattern.test(bookAppointmentWidgetData.ajax_get_nonce)){
        console.error("ajax_get_nonce is not a valid");
        return;
    }
}

function validateDOMData(html){
    html = html.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
    return html.replace(/on\w+="[^"]*"/g, '');
}