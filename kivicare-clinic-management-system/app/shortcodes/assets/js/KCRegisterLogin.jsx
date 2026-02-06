import React, { useState, useEffect, useRef, useMemo, useCallback } from 'react';
import { createRoot } from 'react-dom/client';
import { setLocaleData, __ } from '@wordpress/i18n';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { PhoneInput } from 'react-international-phone';
import 'react-international-phone/style.css';
import { Controller } from 'react-hook-form';
import { PhoneNumberUtil } from 'google-libphonenumber';

// Import styles
import '@app/assets/scss/kivicare.scss';
import '@app/assets/scss/custom.scss';

// Import auth hooks and role utils
import { useLogin, useRegister } from '../../../dashboard/api/hooks/useAuth';
import { useFrontendClinics } from '@api/hooks/useFrontendBookAppointment';
import { getDoctorRole, getPatientRole, getReceptionistRole } from '../../../dashboard/utils/roleUtils';

// Get KiviCare prefix from WordPress localization data
const getKiviCarePrefix = () => {
    if (typeof window !== 'undefined' && window.kc_frontend?.prefix) {
        return window.kc_frontend.prefix;
    }
    return 'kiviCare_';
};

const KIVI_CARE_PREFIX = getKiviCarePrefix();

// Phone validation utility
const phoneUtil = PhoneNumberUtil.getInstance();
const isPhoneValid = (phone) => {
    try {
        return phoneUtil.isValidNumber(phoneUtil.parseAndKeepRawInput(phone));
    } catch {
        return false;
    }
};

// Component for the Login/Register tabs
export function RegisterLoginForm({ containerId }) {
    // Get container element and its data attributes
    const container = document.getElementById(containerId);
    const initialTab = container?.dataset?.initialTab || '';
    const redirectUrl = container?.dataset?.redirectUrl || '';
    const loginText = container?.dataset?.loginText || '';
    const registerText = container?.dataset?.registerText || '';
    const recaptchaEnabled = container?.dataset?.recaptchaEnabled === 'yes';
    const recaptchaSiteKey = container?.dataset?.recaptchaSiteKey || '';
    const defaultCountry = container?.dataset?.defaultCountry || 'us';
    const showOtherGender = container?.dataset?.showOtherGender === 'on';
    const preselectClinicId = container?.dataset?.preselectClinicId ? parseInt(container?.dataset?.preselectClinicId, 10) : 0;
    const siteLogo = container?.dataset?.siteLogo || '';
    // Determine if only single form should be shown (no tab switching)
    const isSingleFormMode = initialTab === 'login' || initialTab === 'register';
    let enabledUserRoles = [];
    try {
        enabledUserRoles = JSON.parse(container?.dataset?.enableUserRoles || '[]');
    } catch (e) {
        enabledUserRoles = [];
    }

    // Apply widget colors
    useEffect(() => {
        if (container) {
            const primaryColor = container.dataset.primaryColor;
            const primaryHoverColor = container.dataset.primaryHoverColor;
            const secondaryColor = container.dataset.secondaryColor;
            const secondaryHoverColor = container.dataset.secondaryHoverColor;

            if (primaryColor) {
                // Set Bootstrap variables
                container.style.setProperty('--bs-primary', primaryColor);
                container.style.setProperty('--bs-primary-rgb', hexToRgb(primaryColor));
                // Set custom variables
                container.style.setProperty('--iq-primary', primaryColor);
                container.style.setProperty('--iq-primary-dark', primaryHoverColor || primaryColor);
            }
            if (primaryHoverColor) {
                container.style.setProperty('--iq-primary-hover', primaryHoverColor);
            }
            if (secondaryColor) {
                // Set Bootstrap variables
                container.style.setProperty('--bs-secondary', secondaryColor);
                container.style.setProperty('--bs-secondary-rgb', hexToRgb(secondaryColor));
                // Set custom variables
                container.style.setProperty('--iq-secondary', secondaryColor);
                container.style.setProperty('--iq-secondary-dark', secondaryHoverColor || secondaryColor);
            }
            if (secondaryHoverColor) {
                container.style.setProperty('--iq-secondary-hover', secondaryHoverColor);
            }
        }
    }, [container]);

    // Helper function to convert hex to RGB
    const hexToRgb = (hex) => {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}` : '';
    };

    const [activeTab, setActiveTab] = useState(initialTab || 'login');
    const [isLoading, setIsLoading] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });
    const [roles, setRoles] = useState([]);
    const [showPassword, setShowPassword] = useState(false);
    const [showRegisterPassword, setShowRegisterPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    // Memoized role options
    const availableRoles = useMemo(() => [
        { value: getPatientRole(), label: __('Patient', 'kivicare-clinic-management-system') },
        { value: getDoctorRole(), label: __('Doctor', 'kivicare-clinic-management-system') },
        { value: getReceptionistRole(), label: __('Receptionist', 'kivicare-clinic-management-system') }
    ], []);

    // Filter roles based on enabledUserRoles
    useEffect(() => {
        if (Array.isArray(enabledUserRoles)) {
            const filtered = availableRoles.filter(role => 
                enabledUserRoles.some(enabledRole => {
                    const lowerEnabled = enabledRole.toLowerCase();
                    const lowerRole = role.value.toLowerCase();
                    const lowerPrefix = KIVI_CARE_PREFIX.toLowerCase();
                    
                    return lowerEnabled === lowerRole || 
                           (lowerPrefix + lowerEnabled) === lowerRole;
                })
            );
            setRoles(filtered.length > 0 ? filtered : [{ value: getPatientRole(), label: __('Patient', 'kivicare-clinic-management-system') }]);
        }
    }, [availableRoles]);





    // Fetch clinics
    const { data: clinicsData, isLoading: clinicsLoading } = useFrontendClinics({
        per_page: 20,
        status: '1'
    });

    const clinics = clinicsData?.data?.clinics || [{ id: 1, name: __('Default Clinic', 'kivicare-clinic-management-system') }];

    // Load reCAPTCHA script if enabled
    useEffect(() => {
        if (recaptchaEnabled && recaptchaSiteKey) {
            const script = document.createElement('script');
            script.src = `https://www.google.com/recaptcha/api.js?render=${recaptchaSiteKey}`;
            script.async = true;
            script.defer = true;
            document.body.appendChild(script);

            return () => {
                const existingScript = document.querySelector(`script[src*="${recaptchaSiteKey}"]`);
                if (existingScript) {
                    document.body.removeChild(existingScript);
                }
            };
        }
    }, [recaptchaEnabled, recaptchaSiteKey]);

    // Setup react-hook-form for login
    const {
        register: loginRegister,
        handleSubmit: handleLoginSubmit,
        formState: { errors: loginErrors },
        watch: loginWatch
    } = useForm({
        mode: 'onBlur',
        defaultValues: {
            username: '',
            password: ''
        }
    });

    // Setup react-hook-form for registration
    const {
        register: registerFormRegister,
        handleSubmit: handleRegisterSubmit,
        formState: { errors: registerErrors },
        reset: resetRegisterForm,
        watch: watchRegisterForm,
        setValue: setRegisterFormValue,
        control: registerControl
    } = useForm({
        mode: 'onBlur',
        defaultValues: {
            username: '',
            email: '',
            first_name: '',
            last_name: '',
            password: '',
            confirmPassword: '',
            mobile_number: '',
            gender: '',
            user_role: '',
            user_clinic: ''
        }
    });

    // Set default clinic if preselect_clinic_id is provided
    useEffect(() => {
        if (preselectClinicId > 0) {
            setRegisterFormValue('user_clinic', preselectClinicId.toString());
        }
    }, [preselectClinicId, setRegisterFormValue]);

    // Set default role
    useEffect(() => {
        if (roles.length === 1) {
            setRegisterFormValue('user_role', roles[0].value);
        }
    }, [roles, setRegisterFormValue]);



    // Handle tab switching
    const switchTab = useCallback((tab) => {
        if (tab !== activeTab) {
            setActiveTab(tab);
            setMessage({ type: '', text: '' });
        }
    }, [activeTab]);

    // Get reCAPTCHA token
    const getRecaptchaToken = useCallback(async (action) => {
        if (!recaptchaEnabled || !recaptchaSiteKey) {
            return '';
        }

        try {
            await new Promise((resolve, reject) => {
                const checkRecaptcha = () => {
                    if (window.grecaptcha && window.grecaptcha.ready) {
                        window.grecaptcha.ready(resolve);
                    } else {
                        setTimeout(checkRecaptcha, 100);
                    }
                };
                checkRecaptcha();
                setTimeout(() => reject(new Error('reCAPTCHA loading timeout')), 10000);
            });
            return await window.grecaptcha.execute(recaptchaSiteKey, { action });
        } catch (error) {
            console.error('reCAPTCHA error:', error);
            throw new Error(__('reCAPTCHA verification failed. Please try again.', 'kivicare-clinic-management-system'));
        }
    }, [recaptchaEnabled, recaptchaSiteKey]);

    // Login mutation
    const loginMutation = useLogin();

    // Handle login submission
    const onLoginSubmit = useCallback(async (data) => {
        setIsLoading(true);
        setMessage({ type: '', text: '' });

        try {
            let recaptchaToken = '';
            if (recaptchaEnabled) {
                recaptchaToken = await getRecaptchaToken('login');
            }

            const loginData = {
                username: data.username?.trim(),
                password: data.password,
                remember: data.rememberMe || false,
                recaptchaToken,
            };

            const response = await loginMutation.mutateAsync(loginData);

            if (response.status) {
                setMessage({ type: 'success', text: response.message || __('Login successful. Redirecting...', 'kivicare-clinic-management-system') });
                const targetUrl = redirectUrl || response.data?.redirect_url || window.location.href;
                setTimeout(() => window.location.href = targetUrl, 1000);
            } else {
                setMessage({ type: 'error', text: response.message || __('Invalid username or password.', 'kivicare-clinic-management-system') });
            }
        } catch (error) {
            setMessage({ type: 'error', text: error?.message || __('Invalid username or password.', 'kivicare-clinic-management-system') });
        } finally {
            setIsLoading(false);
        }
    }, [recaptchaEnabled, getRecaptchaToken, redirectUrl, loginMutation]);

    // Register mutation
    const registerMutation = useRegister();

    // Handle registration submission
    const onRegisterSubmit = useCallback(async (data) => {

        setIsLoading(true);
        setMessage({ type: '', text: '' });

        try {
            let recaptchaToken = '';
            if (recaptchaEnabled) {
                recaptchaToken = await getRecaptchaToken('register');
            }

            if (!data.gender?.trim()) throw new Error(__('Gender is required', 'kivicare-clinic-management-system'));
            if (!data.user_clinic) throw new Error(__('Clinic selection is required', 'kivicare-clinic-management-system'));
            if (!data.user_role?.trim()) throw new Error(__('Role selection is required', 'kivicare-clinic-management-system'));

            const registerData = {
                username: data.username.trim(),
                email: data.email.trim(),
                first_name: data.first_name.trim(),
                last_name: data.last_name.trim(),
                password: data.password,
                mobile_number: data.mobile_number,
                gender: data.gender,
                user_role: data.user_role,
                user_clinic: parseInt(data.user_clinic, 10),
                recaptchaToken,
            };


            const response = await registerMutation.mutateAsync(registerData);

            if (response.status) {
                setMessage({ type: 'success', text: response.message || __('Registration successful. You can now log in.', 'kivicare-clinic-management-system') });
                resetRegisterForm();
                setTimeout(() => switchTab('login'), 2000);
            } else {
                setMessage({ type: 'error', text: response.message || __('Registration failed. Please try again.', 'kivicare-clinic-management-system') });
            }
        } catch (error) {
            const errorMessage = error?.response?.data?.message || __('Registration failed. Please try again.', 'kivicare-clinic-management-system');
            setMessage({ type: 'error', text: errorMessage });
        } finally {
            setIsLoading(false);
        }
    }, [recaptchaEnabled, getRecaptchaToken, registerMutation, resetRegisterForm, switchTab]);

    if (clinicsLoading) {
        return <div>{__('Loading...', 'kivicare-clinic-management-system')}</div>;
    }

    return (
        <div className="kc-register-login-form">
            {/* Message display */}
            {message.text && (
                <div className={`alert ${message.type === 'success' ? 'alert-success' : 'alert-danger'} mb-4`} dangerouslySetInnerHTML={{ __html: message.text }} />
            )}

            <div className="card p-0 mb-0">
                <div className="card-body auth-card">
                    {/* Logo image if available */}
                    <div className="logo-img text-center mb-4">
                        <img src={siteLogo || window.kc_frontend?.loader_image?.replace('loader.gif', 'logo.png')}
                            alt="KiviCare" height="45" className="logo-normal" />
                    </div>

                    {/* Login form */}
                    {activeTab === 'login' && (
                        <form className="kc-login-form" onSubmit={handleLoginSubmit(onLoginSubmit)}>
                            <div className="custom-form-field mb-3">
                                <label className="mb-2" htmlFor="login-username">{__('Username', 'kivicare-clinic-management-system')}<span className="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="login-username"
                                    className={`form-control ${loginErrors.username ? 'is-invalid' : ''}`}
                                    placeholder="username@example.com"
                                    {...loginRegister('username', {
                                        required: __('Username is required', 'kivicare-clinic-management-system')
                                    })}
                                />
                                {loginErrors.username && (
                                    <div className="invalid-feedback">{loginErrors.username.message}</div>
                                )}
                            </div>

                            <div className="custom-form-field mb-3 position-relative">
                                <label className="mb-2" htmlFor="login-password">
                                    {__('Password', 'kivicare-clinic-management-system')}<span className="text-danger">*</span>
                                </label>

                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    id="login-password"
                                    className={`form-control ${loginErrors.password ? 'is-invalid' : ''}`}
                                    placeholder="********"
                                    {...loginRegister('password', {
                                        required: __('Password is required', 'kivicare-clinic-management-system'),
                                    })}
                                />

                                {loginWatch('password') && (
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-link position-absolute"
                                        style={{ right: loginErrors.password ? '35px' : '10px', top: '42px' }}
                                        onClick={() => setShowPassword(prev => !prev)}
                                        tabIndex={-1}
                                    >
                                        {showPassword ? <i className="ph ph-eye-slash"></i> : <i className="ph ph-eye"></i>}
                                    </button>
                                )}

                                {loginErrors.password && (
                                    <div className="invalid-feedback">
                                        {loginErrors.password.message}
                                    </div>
                                )}
                            </div>

                            <div className="d-flex align-items-center justify-content-between mb-4">
                                <div className="form-check">
                                    <input
                                        className="form-check-input"
                                        type="checkbox"
                                        id="remember-me"
                                        {...loginRegister('rememberMe')}
                                    />
                                    <label className="form-check-label" htmlFor="remember-me">
                                        {__('Remember Me', 'kivicare-clinic-management-system')}
                                    </label>
                                </div>
                                <a href={`${window.kc_frontend?.home_url}/wp-login.php?action=lostpassword`} className="forgot-pwd fw-semibold fst-italic">
                                    {__('Forgot Password?', 'kivicare-clinic-management-system')}
                                </a>
                            </div>

                            <div className="text-center">
                                <button
                                    type="submit"
                                    className="btn btn-primary w-100"
                                    disabled={isLoading || loginMutation.isPending}
                                >
                                    <span className="text d-inline-block align-middle text-uppercase">
                                        {isLoading || loginMutation.isPending ? __('Loading...', 'kivicare-clinic-management-system') : (loginText || __('Login', 'kivicare-clinic-management-system'))}
                                    </span>
                                </button>

                                {!isSingleFormMode && (
                                    <div className="d-flex align-items-center justify-content-center mt-3">
                                        <p className="text-center mb-0 fw-medium">
                                            <span className="signup-rtl-space">{__("Don't have an account?", 'kivicare-clinic-management-system')} </span>
                                            <a href="#" onClick={(e) => { e.preventDefault(); switchTab('register'); }} className="text-secondary">
                                                {registerText || __('Sign Up', 'kivicare-clinic-management-system')}
                                            </a>
                                        </p>
                                    </div>
                                )}
                            </div>
                        </form>
                    )}

                    {/* Register form */}
                    {activeTab === 'register' && (
                        <form className="kc-register-form" onSubmit={handleRegisterSubmit(onRegisterSubmit)}>
                            <div className="custom-form-field mb-3">
                                <label className="mb-2" htmlFor="register-username">{__('Username', 'kivicare-clinic-management-system')}<span className="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="register-username"
                                    className={`form-control ${registerErrors.username ? 'is-invalid' : ''}`}
                                    placeholder={__('Enter username', 'kivicare-clinic-management-system')}
                                    {...registerFormRegister('username', {
                                        required: __('Username is required', 'kivicare-clinic-management-system')
                                    })}
                                />
                                {registerErrors.username && (
                                    <div className="invalid-feedback">{registerErrors.username.message}</div>
                                )}
                            </div>

                            <div className="custom-form-field mb-3">
                                <label className="mb-2" htmlFor="register-email">{__('Email', 'kivicare-clinic-management-system')}<span className="text-danger">*</span></label>
                                <input
                                    type="email"
                                    id="register-email"
                                    className={`form-control ${registerErrors.email ? 'is-invalid' : ''}`}
                                    placeholder="name@example.com"
                                    {...registerFormRegister('email', {
                                        required: __('Email is required', 'kivicare-clinic-management-system'),
                                        pattern: {
                                            value: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                                            message: __('Please enter a valid email address', 'kivicare-clinic-management-system')
                                        }
                                    })}
                                />
                                {registerErrors.email && (
                                    <div className="invalid-feedback">{registerErrors.email.message}</div>
                                )}
                            </div>

                            <div className="row mb-3">
                                <div className="col-md-6">
                                    <div className="custom-form-field mb-3 mb-md-0">
                                        <label className="mb-2" htmlFor="register-first-name">{__('First Name', 'kivicare-clinic-management-system')}<span className="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            id="register-first-name"
                                            className={`form-control ${registerErrors.first_name ? 'is-invalid' : ''}`}
                                            placeholder={__('First name', 'kivicare-clinic-management-system')}
                                            {...registerFormRegister('first_name', {
                                                required: __('First name is required', 'kivicare-clinic-management-system')
                                            })}
                                        />
                                        {registerErrors.first_name && (
                                            <div className="invalid-feedback">{registerErrors.first_name.message}</div>
                                        )}
                                    </div>
                                </div>

                                <div className="col-md-6">
                                    <div className="custom-form-field">
                                        <label className="mb-2" htmlFor="register-last-name">{__('Last Name', 'kivicare-clinic-management-system')}<span className="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            id="register-last-name"
                                            className={`form-control ${registerErrors.last_name ? 'is-invalid' : ''}`}
                                            placeholder={__('Last name', 'kivicare-clinic-management-system')}
                                            {...registerFormRegister('last_name', {
                                                required: __('Last name is required', 'kivicare-clinic-management-system')
                                            })}
                                        />
                                        {registerErrors.last_name && (
                                            <div className="invalid-feedback">{registerErrors.last_name.message}</div>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="custom-form-field mb-3 position-relative">
                                <label className="mb-2" htmlFor="register-password">{__('Password', 'kivicare-clinic-management-system')}<span className="text-danger">*</span></label>
                                <input
                                    type={showRegisterPassword ? 'text' : 'password'}
                                    id="register-password"
                                    className={`form-control ${registerErrors.password ? 'is-invalid' : ''}`}
                                    placeholder="********"
                                    {...registerFormRegister('password', {
                                        required: __('Password is required', 'kivicare-clinic-management-system'),
                                        minLength: {
                                            value: 8,
                                            message: __('Password must be at least 8 characters', 'kivicare-clinic-management-system')
                                        }
                                    })}
                                />
                                {watchRegisterForm('password') && (
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-link position-absolute"
                                        style={{ right: registerErrors.password ? '35px' : '10px', top: '42px' }}
                                        onClick={() => setShowRegisterPassword(prev => !prev)}
                                        tabIndex={-1}
                                    >
                                        {showRegisterPassword ? <i className="ph ph-eye-slash"></i> : <i className="ph ph-eye"></i>}
                                    </button>
                                )}
                                {registerErrors.password && (
                                    <div className="invalid-feedback">{registerErrors.password.message}</div>
                                )}
                            </div>

                            <div className="custom-form-field mb-3 position-relative">
                                <label className="mb-2" htmlFor="register-confirm-password">{__('Confirm Password', 'kivicare-clinic-management-system')}<span className="text-danger">*</span></label>
                                <input
                                    type={showConfirmPassword ? 'text' : 'password'}
                                    id="register-confirm-password"
                                    className={`form-control ${registerErrors.confirmPassword ? 'is-invalid' : ''}`}
                                    placeholder="********"
                                    {...registerFormRegister('confirmPassword', {
                                        required: __('Please confirm your password', 'kivicare-clinic-management-system'),
                                        validate: value =>
                                            value === watchRegisterForm('password') ||
                                            __('Passwords do not match', 'kivicare-clinic-management-system')
                                    })}
                                />
                                {watchRegisterForm('confirmPassword') && (
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-link position-absolute"
                                        style={{ right: registerErrors.confirmPassword ? '35px' : '10px', top: '42px' }}
                                        onClick={() => setShowConfirmPassword(prev => !prev)}
                                        tabIndex={-1}
                                    >
                                        {showConfirmPassword ? <i className="ph ph-eye-slash"></i> : <i className="ph ph-eye"></i>}
                                    </button>
                                )}
                                {registerErrors.confirmPassword && (
                                    <div className="invalid-feedback">{registerErrors.confirmPassword.message}</div>
                                )}
                            </div>

                            <div className="custom-form-field mb-3">
                                <label className="mb-2">
                                    {__('Contact Number', 'kivicare-clinic-management-system')} *
                                </label>
                                <Controller
                                    name="mobile_number"
                                    control={registerControl}
                                    rules={{
                                        required: __('Contact number is required', 'kivicare-clinic-management-system'),
                                        validate: (value) => {
                                            if (!value) return true;

                                            if (/^\+\d{1,4}$/.test(value)) return true;

                                            return (
                                            isPhoneValid(value) ||
                                            __('Please enter a valid contact number', 'kivicare-clinic-management-system')
                                            );
                                        },
                                    }}
                                    render={({ field }) => (
                                        <PhoneInput
                                            defaultCountry={defaultCountry}
                                            value={field.value}
                                            onChange={field.onChange}
                                            inputStyle={{
                                                width: '100%',
                                            }}
                                            countrySelectorStyleProps={{
                                                buttonStyle: {
                                                    height: '45px'
                                                }
                                            }}
                                            inputProps={{
                                                className: `form-control ${registerErrors.mobile_number ? 'is-invalid' : ''}`,
                                                placeholder: __('Enter contact number', 'kivicare-clinic-management-system'),
                                                style: { height: '45px' }
                                            }}
                                        />
                                    )}
                                />
                                {registerErrors.mobile_number && (
                                    <div className="invalid-feedback d-block">
                                        {registerErrors.mobile_number.message}
                                    </div>
                                )}
                            </div>

                            <div className="custom-form-field mb-3">
                                <label className="mb-2" htmlFor="register-gender">{__('Gender', 'kivicare-clinic-management-system')} *</label>
                                <select
                                    id="register-gender"
                                    className={`form-select ${registerErrors.gender ? 'is-invalid' : ''}`}
                                    {...registerFormRegister('gender', {
                                        required: __('Gender is required', 'kivicare-clinic-management-system')
                                    })}
                                >
                                    <option value="">{__('Select Gender', 'kivicare-clinic-management-system')}</option>
                                    <option value="male">{__('Male', 'kivicare-clinic-management-system')}</option>
                                    <option value="female">{__('Female', 'kivicare-clinic-management-system')}</option>
                                    {showOtherGender && (
                                        <option value="other">{__('Other', 'kivicare-clinic-management-system')}</option>
                                    )}
                                </select>
                                {registerErrors.gender && (
                                    <div className="invalid-feedback">{registerErrors.gender.message}</div>
                                )}
                            </div>

                            <div className="custom-form-field mb-3">
                                <label className="mb-2" htmlFor="register-user-role">
                                    {__('Select Role', 'kivicare-clinic-management-system')} *
                                </label>
                                <select
                                    id="register-user-role"
                                    className={`form-select ${registerErrors.user_role ? 'is-invalid' : ''}`}
                                    {...registerFormRegister('user_role', {
                                        required: __('Role is required', 'kivicare-clinic-management-system'),
                                        validate: (value) => {
                                            if (!value) return __('Please select a role', 'kivicare-clinic-management-system');
                                            return roles.some(role => role.value === value) || __('Selected role is not available', 'kivicare-clinic-management-system');
                                        }
                                    })}
                                    defaultValue=""
                                >
                                    <option value="">{__('Select Role', 'kivicare-clinic-management-system')}</option>
                                    {roles.map((role) => (
                                        <option key={role.value} value={role.value}>
                                            {role.label}
                                        </option>
                                    ))}
                                </select>
                                {registerErrors.user_role && (
                                    <div className="invalid-feedback">{registerErrors.user_role.message}</div>
                                )}
                            </div>

                            {preselectClinicId === 0 && (
                                <div className="custom-form-field mb-4">
                                    <label className="mb-2" htmlFor="register-clinic">{__('Select Clinic', 'kivicare-clinic-management-system')} *</label>
                                    <select
                                        id="register-clinic"
                                        className={`form-select ${registerErrors.user_clinic ? 'is-invalid' : ''}`}
                                        {...registerFormRegister('user_clinic', {
                                            required: __('Clinic is required', 'kivicare-clinic-management-system')
                                        })}
                                    >
                                        <option value="">{__('Select Clinic', 'kivicare-clinic-management-system')}</option>
                                        {clinics.map((clinic) => (
                                            <option key={clinic.id} value={clinic.id}>
                                                {clinic.name}
                                            </option>
                                        ))}
                                    </select>
                                    {registerErrors.user_clinic && (
                                        <div className="invalid-feedback">{registerErrors.user_clinic.message}</div>
                                    )}
                                </div>
                            )}

                            <div className="text-center">
                                <button
                                    type="submit"
                                    className="btn btn-primary w-100"
                                    disabled={isLoading || registerMutation.isPending}
                                >
                                    <span className="text d-inline-block align-middle text-uppercase">
                                        {isLoading || registerMutation.isPending ? __('Loading...', 'kivicare-clinic-management-system') : (registerText || __('Register', 'kivicare-clinic-management-system'))}
                                    </span>
                                </button>

                                {!isSingleFormMode && (
                                    <div className="d-flex align-items-center justify-content-center mt-3">
                                        <p className="text-center mb-0 fw-medium">
                                            <span className="signup-rtl-space">{__("Already have an account?", 'kivicare-clinic-management-system')} </span>
                                            <a href="#" onClick={(e) => { e.preventDefault(); switchTab('login'); }} className="text-secondary">
                                                {loginText || __('Log In', 'kivicare-clinic-management-system')}
                                            </a>
                                        </p>
                                    </div>
                                )}
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
}

// Initialize the QueryClient
const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            refetchOnWindowFocus: false,
            retry: 1
        }
    }
});

// Set up translations if available
const localeData = window.kc_frontend?.locale_data || { '': {} };
setLocaleData(localeData, 'kivicare-clinic-management-system');

// Find all instances of the shortcode on the page
const initRegisterLogin = () => {
    const containers = document.querySelectorAll('.kc-register-login-container');

    // Render each instance
    containers.forEach(container => {
        const containerId = container.id;

        // Create a root for this container
        const root = createRoot(container);

        // Render the component
        root.render(
            <React.StrictMode>
                <QueryClientProvider client={queryClient}>
                    <RegisterLoginForm containerId={containerId} />
                </QueryClientProvider>
            </React.StrictMode>
        );
    });
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initRegisterLogin);