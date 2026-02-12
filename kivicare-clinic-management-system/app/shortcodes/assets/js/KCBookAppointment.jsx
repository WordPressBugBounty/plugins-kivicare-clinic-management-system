import React, { useState, useRef, useEffect, useCallback } from 'react';
import { createRoot } from 'react-dom/client';
import { setLocaleData, __ } from '@wordpress/i18n';
import Swal from "sweetalert2-neutral";
import { QueryClient, QueryClientProvider, useQueryClient } from '@tanstack/react-query';
import { ReactQueryDevtools } from "@tanstack/react-query-devtools";
import { useForm, FormProvider, useFormContext } from 'react-hook-form';
import { CSSTransition, SwitchTransition } from 'react-transition-group';
import Flatpickr from 'react-flatpickr';
import 'flatpickr/dist/flatpickr.css';
import { PhoneInput } from 'react-international-phone';
import { PhoneNumberUtil } from 'google-libphonenumber';
import 'react-international-phone/style.css';
import '@shortcodes/assets/scss/KCBookAppointment.scss';
import { useFrontendClinics, useFrontendDoctors, useFrontendBookAppointmentServices, useFrontendBookAppointmentConfirmation, useFrontendWidgetSettings, frontendBookAppointmentKeys } from '@api/hooks/useFrontendBookAppointment';
import { useUnavailableSchedule } from '@api/hooks/useClinicSchedules';
import { formatDateLocal, GetLocalizedCalendarLabels, convertPhpDateFormatToFlatpickr, getLocalizedNumber } from '@app/utils/helper';
import dayjs from 'dayjs';
import { useAvailableSlots, useCreateAppointment, usePaymentVerify, usePrintInvoice, appointmentKeys } from '@api/hooks/useAppointments';
import appointmentsService from '@app/api/services/appointmentsService';
import { useLogin, useRegister, useLogout } from '@api/hooks/useAuth';
import { ToastProvider, useToast, ToastContainer } from '@app/components/KCToast';
import StripeCheckout from '@app/views/paymentGateways/StripeCheckout';
import RazorpayCheckout from '@app/views/paymentGateways/RazorpayCheckout';
import { AddToCalendarButton } from 'add-to-calendar-button-react';
import { useCustomFieldsByModule, useSaveCustomFieldData, useCustomFieldData } from '@api/hooks/useCustomFields';
import { useCustomFormsRender } from '@api/hooks/useCustomForms';
import CustomFieldRenderer from '@app/components/CustomFieldRenderer';
import CustomFormRenderer from '@app/views/settings/customForm/CustomFormRenderer';
import MultiFileUploader from '../../../dashboard/components/MultiFileUploader';
import {
    ClinicSelectionSkeleton,
    DoctorSelectionSkeleton,
    ServiceSelectionSkeleton,
    TimeSlotsSkeleton,
    ConfirmationStepSkeleton,
    BookAppointmentFormSkeleton
} from '../../../dashboard/components/skeletons/BookingSkeletons';
// Phone validation utility
const phoneUtil = PhoneNumberUtil.getInstance();

const isPhoneValid = (phone) => {
    try {
        return phoneUtil.isValidNumber(phoneUtil.parseAndKeepRawInput(phone));
    } catch (error) {
        return false;
    }
};

// Initialize the QueryClient
const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            refetchOnWindowFocus: false,
            retry: 1
        }
    }
});

// Using named export for component to work better with Fast Refresh
export function BookAppointmentForm({ id, formId, title, isKivicarePro, widgetOrder, userLogin, paymentGateways, currentUserId, pageId, queryParams, selectedDoctorId, selectedClinicId, showPrintButton, doctorId, clinicId, serviceId, timezone, containerId, defaultClinicId, showOtherGender, defaultCountry }) {

    const parsedWidgetOrder = JSON.parse(widgetOrder || '[]');
    const parsedPaymentGateways = JSON.parse(paymentGateways || '[]');
    const toast = useToast(); // Add toast functionality

    const queryClient = useQueryClient(); // Add query client for cache invalidation
    const [isLoading, setIsLoading] = useState(false);
    const [transitionDirection, setTransitionDirection] = useState('next'); // 'next' or 'prev'
    const [isAuthenticated, setIsAuthenticated] = useState(userLogin === '1'); // Track authentication state
    // Handle logout
    const logoutMutation = useLogout();

    const [isLogin, setIsLogin] = useState(false); // Track login/register tab state
    const [bookingStatus, setBookingStatus] = useState(null); // Track booking status: 'success', 'payment-failed', null
    const nodeRef = useRef(null);
    const stripeCheckoutRef = useRef(null); // Add stripe checkout ref
    const razorpayCheckoutRef = useRef(null);
    const verifiedPaymentId = useRef(null); // Track which payment ID has been verified
    const [finalAppointmentId, setFinalAppointmentId] = useState(null);
    const [grandTotal, setGrandTotal] = useState(0); // Track grand total

    // Initialize pre-selected data
    const [preSelectedDoctor, setPreSelectedDoctor] = useState(null);
    const [preSelectedClinic, setPreSelectedClinic] = useState(null);
    const [preSelectedService, setPreSelectedService] = useState(null);

    // Custom Form Refs & State
    const customFormRefs = useRef({});
    const [customFormsDataState, setCustomFormsDataState] = useState({});
    const [errorTabs, setErrorTabs] = useState([]);

    // Initialize React Hook Form
    const methods = useForm({
        defaultValues: {
            selectedClinic: null,
            selectedDoctor: null,
            selectedService: null,
            selectedDate: null,
            selectedTime: null,
            description: '',
            selectedPaymentGateway: parsedPaymentGateways.length === 1 ? parsedPaymentGateways[0] : null,
            userDetails: {
                firstName: '',
                lastName: '',
                email: '',
                contact: '',
                gender: ''
            }
        },
        mode: 'onChange'
    });
    const params = JSON.parse(queryParams || '{}');

    // Apply widget colors
    useEffect(() => {
        const container = document.getElementById(containerId);
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
    }, [containerId]);

    // Helper function to convert hex to RGB
    const hexToRgb = (hex) => {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}` : '';
    };

    const { watch, setValue, getValues, trigger, formState: { errors, isValid } } = methods;

    // Fetch pre-selected doctor data if provided (support both selectedDoctorId and doctorId)
    const doctorIdToFetch = selectedDoctorId || doctorId;
    const { data: preSelectedDoctorData } = useFrontendDoctors(
        { id: doctorIdToFetch },
        {
            enabled: !!doctorIdToFetch,
            staleTime: 5 * 60 * 1000,
            cacheTime: 10 * 60 * 1000
        }
    );


    // Fetch pre-selected clinic data if provided (support both selectedClinicId and clinicId)
    // If Pro is not active, use default clinic ID
    const clinicIdToFetch = selectedClinicId || clinicId || (isKivicarePro !== "true" ? defaultClinicId : null);
    const { data: preSelectedClinicData } = useFrontendClinics(
        { id: clinicIdToFetch },
        {
            enabled: !!clinicIdToFetch,
            staleTime: 5 * 60 * 1000,
            cacheTime: 10 * 60 * 1000
        }
    );

    // Fetch pre-selected service data if provided
    const serviceIdToFetch = serviceId;
    const { data: preSelectedServiceData } = useFrontendBookAppointmentServices(
        { id: serviceIdToFetch },
        {
            enabled: !!serviceIdToFetch,
            staleTime: 5 * 60 * 1000,
            cacheTime: 10 * 60 * 1000
        }
    );

    // Fetch centralized widget settings
    const { data: widgetSettingsData, isLoading: widgetSettingsLoading } = useFrontendWidgetSettings();

    // Extract widget settings
    const widgetSettings = widgetSettingsData?.data || {
        clinic: {
            showClinicImage: false,
            showClinicAddress: false,
            clinicContactDetails: { id: 1 }
        },
        doctor: {
            showDoctorImage: false,
            showDoctorExperience: false,
            showDoctorSpeciality: false,
            showDoctorDegree: false,
            doctorContactDetails: { id: 1 },
            showDoctorRating: false,
        },
        service: {
            showServiceImage: false,
            showServicetype: false,
            showServicePrice: false,
            showServiceDuration: false,
            skip_service_when_single: false
        },
        appointment: {
            is_uploadfile_appointment: false,
            is_appointment_description_config_data: false
        }
    };

    const is_appointment_description_config_data = widgetSettings?.appointment?.is_appointment_description_config_data || false;
    const is_uploadfile_appointment = widgetSettings?.appointment?.is_uploadfile_appointment || false;

    // Set pre-selected data when available
    useEffect(() => {
        if (preSelectedDoctorData?.data && preSelectedDoctorData.data.length > 0) {
            const doctor = preSelectedDoctorData.data[0];
            setPreSelectedDoctor(doctor);
            setValue('selectedDoctor', doctor, { shouldValidate: true });
        } else if (preSelectedDoctorData && preSelectedDoctorData.data && preSelectedDoctorData.data.length === 0) {
            console.warn('No doctor found for ID:', doctorIdToFetch);
        }
    }, [preSelectedDoctorData, setValue, doctorIdToFetch]);

    const [currentDoctorId, setCurrentDoctorId] = useState(null);
    const [appointmentId, setAppointmentId] = useState(null);

    useEffect(() => {
        const id = preSelectedDoctor?.id || watch('selectedDoctor')?.id;
        setCurrentDoctorId(id);
    }, [preSelectedDoctor, watch('selectedDoctor')]);

    useEffect(() => {
        if (preSelectedClinicData?.data && preSelectedClinicData.data.length > 0) {
            const clinic = preSelectedClinicData.data[0];
            setPreSelectedClinic(clinic);
            setValue('selectedClinic', clinic, { shouldValidate: true });
        } else if (preSelectedClinicData && preSelectedClinicData.data && preSelectedClinicData.data.length === 0) {
            console.warn('No clinic found for ID:', clinicIdToFetch);
        }
    }, [preSelectedClinicData, setValue, clinicIdToFetch]);

    useEffect(() => {
        if (preSelectedServiceData?.data && preSelectedServiceData.data.length > 0) {
            const service = preSelectedServiceData.data[0];
            setPreSelectedService(service);
            setValue('selectedService', service, { shouldValidate: true });
        } else if (preSelectedServiceData && preSelectedServiceData.data && preSelectedServiceData.data.length === 0) {
            console.warn('No service found for ID:', serviceIdToFetch);
        }
    }, [preSelectedServiceData, setValue, serviceIdToFetch]);

    // Initialize booking mutation using existing appointment API
    const bookingMutation = useCreateAppointment();

    // Initialize payment verification mutation
    const paymentVerifyMutation = usePaymentVerify();

    // Initialize custom field save mutation
    const saveCustomFieldMutation = useSaveCustomFieldData();

    // Fetch custom forms for appointment module
    const { data: customFormsData } = useCustomFormsRender(
        { 
            module_type: 'appointment_module', 
            status: 1 
        }, 
        { enabled: String(isKivicarePro) === "true" }
    );

    // Filter Custom Forms based on selected clinic
    const customForms = React.useMemo(() => {
        const forms = customFormsData?.data?.forms || [];

        const currentClinicValue = watch('selectedClinic');
        
        if (!currentClinicValue?.id) {
            return forms;
        }
        
        const filteredForms = forms.filter(form => {
            const clinics = form.conditions?.clinics || [];
            const isMatch = clinics.length === 0 || clinics.includes(parseInt(currentClinicValue.id));
            return isMatch;
        });
        return filteredForms;
    }, [customFormsData, watch('selectedClinic')]);

    // Helper to get all custom form data
    const getCustomFormData = () => {
        return customFormsDataState;
    };

    // Custom validation function for each step
    const validateCurrentStep = async () => {
        const formData = getValues();

        switch (currentStep) {
            case 'clinic':
                if (isKivicarePro === "true" && !formData.selectedClinic) {
                    toast.error(__('Please select a clinic to continue.', 'kivicare-clinic-management-system'));
                    return false;
                }
                return true;

            case 'doctor':
                if (!formData.selectedDoctor) {
                    toast.error(__('Please select a doctor to continue.', 'kivicare-clinic-management-system'));
                    return false;
                }
                return true;

            case 'category':
                if (!formData.selectedService) {
                    toast.error(__('Please select a service to continue.', 'kivicare-clinic-management-system'));
                    return false;
                }
                return true;

            case 'date-time':
                if (!formData.selectedDate) {
                    toast.error(__('Please select a date to continue.', 'kivicare-clinic-management-system'));
                    return false;
                }
                if (!formData.selectedTime) {
                    toast.error(__('Please select a time slot to continue.', 'kivicare-clinic-management-system'));
                    return false;
                }
                return true;

            case 'file-uploads-custom':
                // Validate Custom Forms
                let allFormsValid = true;
                const newErrorTabs = [];

                // Validate main form fields (specifically custom fields)
                const isMainFormValid = await trigger();
                
                // Check if any custom fields have errors
                const currentErrors = methods.formState.errors;
                const hasCustomFieldErrors = Object.keys(currentErrors).some(key => key.startsWith('custom_field_'));
                
                if (hasCustomFieldErrors) {
                    allFormsValid = false;
                    newErrorTabs.push('custom_field');
                }
                
                for (const form of customForms) {
                    const formRef = customFormRefs.current[form.id];
                    if (formRef) {
                        const validationResult = await formRef.triggerValidation();
                        if (!validationResult.isValid) {
                            allFormsValid = false;
                            newErrorTabs.push(form.id);
                        }
                    }
                }
                
                setErrorTabs(newErrorTabs);

                if (!allFormsValid) {
                     toast.error(__('Please fill all required fields in the form.', 'kivicare-clinic-management-system'));
                     return false;
                }
                
                return true;

            case 'detail-info':
                const { userDetails } = formData;

                if (!isLogin) {
                    if (!userDetails.firstName || !userDetails.lastName || !userDetails.email || !userDetails.contact || !userDetails.gender) {
                        toast.error(__('Please fill in all required user details to continue.', 'kivicare-clinic-management-system'));
                        return false;
                    }

                    // Basic email validation
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(userDetails.email)) {
                        toast.error(__('Please enter a valid email address.', 'kivicare-clinic-management-system'));
                        return false;
                    }
                    // Phone validation using google-libphonenumber
                    if (!userDetails.contact || userDetails.contact.length < 10) {
                        toast.error(__('Please enter a valid contact number.', 'kivicare-clinic-management-system'));
                        return false;
                    }
                    // Validate phone number format
                    if (!isPhoneValid(userDetails.contact)) {
                        toast.error(__('Please enter a valid phone number.', 'kivicare-clinic-management-system'));
                        return false;
                    }
                    return true;
                } else {
                    // Check authentication status only if user is in login tab
                    console.log(isAuthenticated, 'auth');

                    // if (!isAuthenticated) {
                    //     toast.error(__('Please login to continue.', 'kivicare-clinic-management-system'));
                    //     return false;
                    // }
                }
                // For registration tab, user doesn't need to be authenticated yet
                return true;

            case 'confirm':
                // Validate payment gateway selection if multiple gateways are available
                if (grandTotal > 0 && parsedPaymentGateways.length > 1 && !formData.selectedPaymentGateway) {
                    toast.error(__('Please select a payment method to continue.', 'kivicare-clinic-management-system'));
                    return false;
                }
                return true;

            default:
                return true;
        }
    };

    // Fetch custom fields for appointment module to check if any exist
    const { data: customFieldsResponse } = useCustomFieldsByModule(
        'appointment_module',
        doctorIdToFetch,
        {
            enabled: !!doctorIdToFetch && isKivicarePro === "true"
        }
    );
    const hasCustomFields = (customFieldsResponse?.data?.length || 0) > 0;

    // Dynamically build steps based on widgetOrder and isKivicarePro
    const getAllSteps = () => {
        // Define all possible steps with their configurations
        const allSteps = {
            'clinic': { id: 'clinic', label: __('Choose a Clinic', 'kivicare-clinic-management-system'), description: __('Please select a clinic from below options', 'kivicare-clinic-management-system') },
            'doctor': { id: 'doctor', label: __('Choose Your Doctor', 'kivicare-clinic-management-system'), description: __('Please select a doctor from available options', 'kivicare-clinic-management-system') },
            'category': { id: 'category', label: __('Doctor Services', 'kivicare-clinic-management-system'), description: __('Please select a service from below options', 'kivicare-clinic-management-system') },
            'date-time': { id: 'date-time', label: __('Select Date and Time', 'kivicare-clinic-management-system'), description: __('Select date to see a timeline of available slots', 'kivicare-clinic-management-system') },
            'detail-info': { id: 'detail-info', label: __('User Detail Information', 'kivicare-clinic-management-system'), description: __('Please provide you contact details', 'kivicare-clinic-management-system') },
            'file-uploads-custom': { id: 'file-uploads-custom', label: __('Appointment Extra Data', 'kivicare-clinic-management-system'), description: __('Upload file and description about appointment', 'kivicare-clinic-management-system') },
            'confirm': { id: 'confirm', label: __('Confirmation', 'kivicare-clinic-management-system'), description: __('Confirm your booking', 'kivicare-clinic-management-system') },
            'booking-success': { id: 'booking-success', label: __('Booking Confirmed', 'kivicare-clinic-management-system'), description: __('Your appointment is booked successfully', 'kivicare-clinic-management-system') },
            'payment-failed': { id: 'payment-failed', label: __('Payment Failed', 'kivicare-clinic-management-system'), description: __('Payment transaction failed', 'kivicare-clinic-management-system') }
        };

        // If booking is completed, show only the result step
        if (bookingStatus === 'success') {
            return [allSteps['booking-success']];
        }
        if (bookingStatus === 'payment-failed') {
            return [allSteps['payment-failed']];
        }

        // Sort steps based on widgetOrder array
        const sortedSteps = parsedWidgetOrder
            .filter(orderItem => {


                // Skip clinic step if clinic is pre-selected or doctor is pre-selected (we can infer clinic later)
                if (orderItem.att_name === 'clinic' && (clinicIdToFetch || doctorIdToFetch)) {
                    return false;
                }
                // Skip doctor step only if doctor is pre-selected
                if (orderItem.att_name === 'doctor' && doctorIdToFetch) {
                    return false;
                }
                // Skip service step if service is pre-selected
                if (orderItem.att_name === 'category' && serviceIdToFetch) {
                    return false;
                }
                // Include clinic step only if isKivicarePro is true
                if ((orderItem.att_name === 'clinic' && isKivicarePro !== "true") || orderItem.att_name === 'detail-info' && isAuthenticated) {
                    return false;
                }
                // Skip file-uploads-custom if all conditions are false
                if (orderItem.att_name === 'file-uploads-custom' && !is_appointment_description_config_data && !is_uploadfile_appointment && !hasCustomFields) {
                    return false;
                }
                // Include step if it exists in allSteps (exclude result steps from normal flow)
                const shouldInclude = allSteps[orderItem.att_name] && !['booking-success', 'payment-failed'].includes(orderItem.att_name);
                return shouldInclude;
            })
            .map(orderItem => allSteps[orderItem.att_name]);

        return sortedSteps;
    };

    const steps = getAllSteps();

    // Set initial step to the first step in the sorted order
    const [currentStep, setCurrentStep] = useState('');

    // Set the initial step once steps are available
    useEffect(() => {
        if (steps.length > 0 && !currentStep) {

            // If doctor is pre-selected, skip doctor selection step
            if (doctorIdToFetch && preSelectedDoctor) {
                const doctorStepIndex = steps.findIndex(step => step.id === 'doctor');
                if (doctorStepIndex !== -1 && doctorStepIndex < steps.length - 1) {
                    // Skip to the next step after doctor selection
                    const nextStep = steps[doctorStepIndex + 1].id;
                    setCurrentStep(nextStep);
                } else {
                    setCurrentStep(steps[0].id);
                }
            }
            // If clinic is pre-selected (but no doctor), skip clinic selection step
            else if (clinicIdToFetch && preSelectedClinic && !doctorIdToFetch) {
                const clinicStepIndex = steps.findIndex(step => step.id === 'clinic');
                if (clinicStepIndex !== -1 && clinicStepIndex < steps.length - 1) {
                    // Skip to the next step after clinic selection
                    const nextStep = steps[clinicStepIndex + 1].id;
                    setCurrentStep(nextStep);
                } else {
                    setCurrentStep(steps[0].id);
                }
            }
            // If clinic is pre-selected but clinic data is not yet loaded, wait for it
            else if (clinicIdToFetch && !preSelectedClinic && !doctorIdToFetch) {
                setCurrentStep(steps[0].id);
            } else {
                setCurrentStep(steps[0].id);
            }
        }
    }, [steps, currentStep, doctorIdToFetch, clinicIdToFetch, preSelectedDoctor, preSelectedClinic]);

    // Re-evaluate step when clinic data is loaded
    useEffect(() => {
        if (clinicIdToFetch && preSelectedClinic && currentStep === 'clinic' && !doctorIdToFetch) {
            const clinicStepIndex = steps.findIndex(step => step.id === 'clinic');
            if (clinicStepIndex !== -1 && clinicStepIndex < steps.length - 1) {
                const nextStep = steps[clinicStepIndex + 1].id;
                setCurrentStep(nextStep);
            }
        }
    }, [preSelectedClinic, clinicIdToFetch, doctorIdToFetch, currentStep, steps]);

    // Update current step when booking status changes
    // useEffect(() => {
    //     if (bookingStatus === 'success') {
    //         setCurrentStep('booking-success');
    //     } else if (bookingStatus === 'payment-failed') {
    //         setCurrentStep('payment-failed');
    //     }
    // }, [bookingStatus]);

    // Handle payment verification when payment_status is completed
    useEffect(() => {
        if (params?.payment_status === 'completed' &&
            params?.payment_id &&
            verifiedPaymentId.current !== params.payment_id) {

            setIsLoading(true);
            verifiedPaymentId.current = params.payment_id; // Mark this payment ID as being processed

            // Prepare payment data for verification
            const paymentData = {
                payment_status: params.payment_status,
                payment_id: params.payment_id,
                message: params.message || 'Payment completed successfully',
                appointment_id: params.appointment_id,
                // Add any other payment-related params
                ...params
            };

            // Call payment verification API
            paymentVerifyMutation.mutateAsync(paymentData)
                .then((response) => {
                    console.log('Payment verification successful:', response);

                    // Check if payment is verified and appointment is confirmed
                    if (response?.data?.status === 'success' || response?.data?.payment_status === 'completed') {
                        // Set booking status to success to show booking-success step
                        setBookingStatus('success');
                        setCurrentStep('booking-success');

                        // Show success toast
                        toast.success(__('Your payment has been verified and appointment is confirmed.', 'kivicare-clinic-management-system'));
                    } else {
                        // Payment verification failed
                        setBookingStatus('payment-failed');


                        toast.error(__('Unable to verify your payment. Please contact support.', 'kivicare-clinic-management-system'));
                    }

                    setIsLoading(false);
                })
                .catch((error) => {
                    console.error('Payment verification failed:', error);

                    // Set to payment failed status
                    setBookingStatus('payment-failed');

                    toast.error(__('An error occurred while verifying your payment.', 'kivicare-clinic-management-system'));

                    setIsLoading(false);
                });

        }
    }, [params?.payment_status, params?.payment_id]);

    const handleStepChange = async (stepId, direction = 'next') => {
        const currentIndex = steps.findIndex(step => step.id === currentStep);
        const targetIndex = steps.findIndex(step => step.id === stepId);

        // If moving forward, validate current step
        if (targetIndex > currentIndex) {
            const isStepValid = await validateCurrentStep();
            if (!isStepValid) return;
        }

        setTransitionDirection(targetIndex > currentIndex ? 'next' : 'prev');
        setCurrentStep(stepId);
    };

    const handleNext = async () => {
        // Validate current step before proceeding
        const isStepValid = await validateCurrentStep();
        if (!isStepValid) return;

        const currentIndex = steps.findIndex(step => step.id === currentStep);
        if (currentIndex < steps.length - 1) {
            setTransitionDirection('next');
            setCurrentStep(steps[currentIndex + 1].id);
        }
    };

    const handleBack = () => {
        const currentIndex = steps.findIndex(step => step.id === currentStep);
        if (currentIndex > 0) {
            setTransitionDirection('prev');
            setCurrentStep(steps[currentIndex - 1].id);
        }
    };

    // Watch form values for real-time updates
    const formData = watch();

    // Get current step index for progress indication
    const getCurrentStepIndex = () => {
        return steps.findIndex(step => step.id === currentStep);
    };

    // Helper function to check if a step is completed
    const isStepCompleted = (stepId) => {
        const formData = getValues();

        switch (stepId) {
            case 'clinic':
                // Mark as completed if pre-selected or manually selected
                return isKivicarePro !== "true" || formData.selectedClinic || (clinicIdToFetch && preSelectedClinic) || clinicIdToFetch;
            case 'doctor':
                // Mark as completed if pre-selected or manually selected
                return formData.selectedDoctor || (doctorIdToFetch && preSelectedDoctor);
            case 'category':
                return formData.selectedService;
            case 'date-time':
                // Check if both date and time are selected
                return formData.selectedDate && formData.selectedTime;
            case 'file-uploads-custom':
                return true; // Optional step
            case 'detail-info':
                const { userDetails } = formData;
                // For login tab, require authentication; for registration tab, just require form fields
                const basicFieldsCompleted = userDetails.firstName && userDetails.lastName && userDetails.email && userDetails.contact && userDetails.gender;
                return isAuthenticated ? false : (basicFieldsCompleted && (isLogin ? isAuthenticated : true));
            case 'confirm':
                // For confirmation step, check if payment gateway is selected when multiple are available AND total > 0
                if (grandTotal <= 0) return true;
                return parsedPaymentGateways.length <= 1 || formData.selectedPaymentGateway;
            default:
                return false;
        }
    };
    const renderStepContent = () => {
        switch (currentStep) {
            case 'clinic':
                return isKivicarePro === "true" ? (
                    <ClinicSelection
                        selectedDoctor={formData.selectedDoctor}
                        widgetSettings={widgetSettings}
                    />
                ) : null;
            case 'doctor':
                return <DoctorSelection
                    isKivicarePro={isKivicarePro === "true"}
                    widgetSettings={widgetSettings}
                    clinicId={clinicId}
                />;
            case 'category':
                return <ServiceSelection
                    onNext={handleNext}
                    widgetSettings={widgetSettings}
                    doctorId={doctorId}
                />;
            case 'date-time':
                return <DateTimeSelection timezone={timezone} doctorId={doctorId} clinicId={clinicIdToFetch} />;
            case 'file-uploads-custom':
                return <AppointmentExtraData 
                    doctorId={doctorId} 
                    widgetSettings={widgetSettings} 
                    containerId={containerId} 
                    isKivicarePro={isKivicarePro}
                    customForms={customForms}
                    customFormRefs={customFormRefs} 
                    customFormsDataState={customFormsDataState}
                    setCustomFormsDataState={setCustomFormsDataState}
                    errorTabs={errorTabs}
                />;
            case 'detail-info':
                return !isAuthenticated ? (
                    <UserDetailsForm
                        isAuthenticated={isAuthenticated}
                        setIsAuthenticated={setIsAuthenticated}
                        isLogin={isLogin}
                        setIsLogin={setIsLogin}
                        onNext={handleNext}
                        clinicId={clinicIdToFetch}
                        containerId={containerId}
                        showOtherGender={showOtherGender}
                        defaultCountry={defaultCountry}
                    />
                ) : (
                    <div className="text-center p-4">
                        <p>{__('You are already logged in. Please proceed to the next step.', 'kivicare-clinic-management-system')}</p>
                    </div>
                );
            case 'confirm':
                return <ConfirmationStep
                    paymentGateways={parsedPaymentGateways}
                    doctorId={doctorId}
                    clinicId={clinicIdToFetch}
                    serviceId={serviceId}
                    widgetSettings={widgetSettings}
                    setGrandTotal={setGrandTotal}
                    grandTotal={grandTotal}
                    isKivicarePro={isKivicarePro}
                    customForms={customForms}
                    customFormsDataState={customFormsDataState}
                />;
            case 'booking-success':
                return <BookingSuccessStep onBookMore={handleBookMore} appointmentId={finalAppointmentId} showPrintSetting={showPrintButton === 'true'} />;
            case 'payment-failed':
                return <PaymentFailedStep onTryAgain={handleTryAgain} onBookMore={handleBookMore} />;
            default:
                return null;
        }
    };

    const handleSubmit = async () => {
        // Final validation before submission
        const isStepValid = await validateCurrentStep();
        if (!isStepValid) return;

        setIsLoading(true);

        try {
            const formValues = getValues();

            // Additional final validation - use same logic as ConfirmationStep
            const finalClinicId = clinicIdToFetch || formValues.selectedClinic?.id;
            if (isKivicarePro === "true" && !finalClinicId) {
                toast.error(__('Please select a clinic before booking.', 'kivicare-clinic-management-system'));
                setIsLoading(false);
                return;
            }

            if (!formValues.selectedDoctor && !doctorIdToFetch) {
                toast.error(__('Please select a doctor before booking.', 'kivicare-clinic-management-system'));
                setIsLoading(false);
                return;
            }

            if (!formValues.selectedService) {
                toast.error(__('Please select a service before booking.', 'kivicare-clinic-management-system'));
                setIsLoading(false);
                return;
            }

            if (!formValues.selectedDate || !formValues.selectedTime) {
                toast.error(__('Please select both date and time before booking.', 'kivicare-clinic-management-system'));
                setIsLoading(false);
                return;
            }

            // Validate payment gateway selection if required
            if (grandTotal > 0 && parsedPaymentGateways.length > 1 && !formValues.selectedPaymentGateway) {
                toast.error(__('Please select a payment method before booking.', 'kivicare-clinic-management-system'));
                setIsLoading(false);
                return;
            }

            // Collect custom field data
            const customFieldData = {};
            Object.keys(formValues).forEach(key => {
                if (key.startsWith('custom_field_')) {
                    const fieldId = key.replace('custom_field_', '');
                    customFieldData[fieldId] = formValues[key];
                }
            });

            // Prepare appointment data for standard appointment API
            const appointmentData = {
                clinicId: finalClinicId,
                doctorId: doctorIdToFetch || formValues.selectedDoctor?.id,
                serviceId: [formValues.selectedService?.id],
                appointmentStartDate: formValues.selectedDate,
                appointmentStartTime: formValues.selectedTime,
                description: formValues.description || '',
                // patientId: isAuthenticated && currentUserId ? parseInt(currentUserId) : null, // Use current user ID if logged in
                // Patient details for new patients (when not logged in)
                patientFirstName: formValues.userDetails?.firstName,
                patientLastName: formValues.userDetails?.lastName,
                patientEmail: formValues.userDetails?.email,
                patientContact: formValues.userDetails?.contact,
                patientGender: formValues.userDetails?.gender,
                // Payment gateway info - send empty string for 0-cost appointments
                paymentGateway: grandTotal > 0
                    ? (formValues.selectedPaymentGateway?.id || (parsedPaymentGateways.length === 1 ? parsedPaymentGateways[0]?.id : ''))
                    : '',
                page_id: pageId || null, // Pass the current
                customForm: getCustomFormData()
            };
            if (formValues.appointmentFiles && formValues.appointmentFiles.length > 0) {
                appointmentData.appointmentFileId = formValues.appointmentFiles.map(file => file.id);
            }
            console.log('Booking appointment with data:', appointmentData);

            // Call the booking API with success/error handling
            bookingMutation.mutate(appointmentData, {
                onSuccess: async (response) => {
                    // Invalidate queries to refresh data
                    queryClient.invalidateQueries({ queryKey: ['appointments'] });

                    if (response?.data?.status || response?.status) {
                        toast.success(__('Appointment booked successfully!', 'kivicare-clinic-management-system'));

                        if (response?.data?.appointment_id) {
                            setFinalAppointmentId(response.data.appointment_id);
                        }

                        // Save custom field data if appointment was created successfully and we have custom fields
                        if (response?.data?.appointment_id && Object.keys(customFieldData).length > 0) {
                            try {
                                await saveCustomFieldMutation.mutateAsync({
                                    moduleType: 'appointment_module',
                                    moduleId: response.data.appointment_id,
                                    fieldsData: customFieldData
                                });
                            } catch (customFieldError) {
                                console.error('Failed to save custom field data:', customFieldError);
                                // Don't fail the entire booking for custom field errors
                            }
                        }

                        // Handle payment gateway responses based on gateway type
                        const gateway = response?.data?.payment_response?.gateway;

                        if (gateway) {
                            // Check if gateway STARTS WITH 'knit_pay' to handle dynamic IDs
                            const gateway_base = gateway.startsWith('knit_pay') ? 'knit_pay' : gateway;

                            switch (gateway_base) {
                                case 'paypal':
                                case 'woocommerce':
                                case 'knit_pay':
                                    if (response.data.payment_response.data?.redirect_url) {
                                        window.location.href = response.data.payment_response.data.redirect_url;
                                    } else {
                                        // Show success step if no redirect
                                        setBookingStatus('success');
                                        setCurrentStep('booking-success');
                                    }
                                    break;
                                case 'stripe':
                                    if (stripeCheckoutRef?.current?.updateAppointmentData) {
                                        stripeCheckoutRef.current.updateAppointmentData(response.data);
                                    }
                                    break;
                                case 'razorpay':
                                    // Handle Razorpay specific logic here if needed
                                    if (razorpayCheckoutRef?.current?.updateAppointmentData) {
                                        razorpayCheckoutRef.current.updateAppointmentData(response.data);
                                    }
                                    break;
                                default:
                                    // Handle other payment gateways or fallback
                                    if (response?.data?.payment_url) {
                                        setTimeout(() => {
                                            window.location.href = response.data.payment_url;
                                        }, 1500);
                                    } else {
                                        // Show success step if no payment URL
                                        setBookingStatus('success');
                                        setCurrentStep('booking-success');
                                    }
                                    break;
                            }
                        } else if (response?.data?.payment_url) {
                            // Fallback for legacy payment_url handling
                            setTimeout(() => {
                                window.location.href = response.data.payment_url;
                            }, 1500);
                        } else {
                            // No payment required, show success step
                            setBookingStatus('success');
                            setCurrentStep('booking-success');
                        }
                    } else {
                        toast.error(response?.message || __('Failed to book appointment.', 'kivicare-clinic-management-system'));
                        setBookingStatus('payment-failed');
                        setCurrentStep('payment-failed');
                    }

                    setIsLoading(false);
                },
                onError: (error) => {
                    setIsLoading(false);
                    console.error('Booking error:', error);
                    console.error('Error response:', error?.response?.data);
                    const errorMessage = error?.response?.data?.message || error?.message || __('An error occurred while booking the appointment. Please try again.', 'kivicare-clinic-management-system');
                    toast.error(errorMessage);
                    setBookingStatus('payment-failed');
                    setCurrentStep('payment-failed');
                }
            });

        } catch (error) {
            console.error('Booking error:', error);
            toast.error(__('An error occurred while booking the appointment. Please try again.', 'kivicare-clinic-management-system'));
            setIsLoading(false);
        }
    };

    // Handle Stripe payment success callback
    const handleStripePaymentSuccess = (response) => {
        toast.success(__('Payment completed successfully! Your appointment is confirmed.', 'kivicare-clinic-management-system'));
        setBookingStatus('success');
        setCurrentStep('booking-success');
    };

    // Handle Stripe payment cancel callback
    const handleStripePaymentCancel = () => {
        toast.error(__('Payment was cancelled. Please try again.', 'kivicare-clinic-management-system'));
        setBookingStatus('payment-failed');
        setCurrentStep('payment-failed');
    };

    // Handle booking more appointments
    const handleBookMore = () => {
        // Reset form and booking status
        setBookingStatus(null);
        verifiedPaymentId.current = null; // Reset verified payment tracking
        const firstStep = parsedWidgetOrder.find(orderItem =>
            orderItem.att_name !== 'clinic' || isKivicarePro === "true"
        );
        if (firstStep) {
            setCurrentStep(firstStep.att_name);
        }
        // Reset form values including custom fields
        const resetValues = {
            selectedClinic: null,
            selectedDoctor: null,
            selectedService: null,
            selectedDate: null,
            selectedTime: null,
            description: '',
            selectedPaymentGateway: parsedPaymentGateways.length === 1 ? parsedPaymentGateways[0] : null,
            userDetails: {
                firstName: '',
                lastName: '',
                email: '',
                contact: '',
                gender: ''
            }
        };

        // Clear any custom field values
        const currentValues = methods.getValues();
        Object.keys(currentValues).forEach(key => {
            if (key.startsWith('custom_field_')) {
                resetValues[key] = null;
            }
        });

        methods.reset(resetValues);
    };

    // Handle trying again after payment failure
    const handleTryAgain = () => {
        setBookingStatus(null);
        verifiedPaymentId.current = null; // Reset verified payment tracking
        setCurrentStep('confirm');
    };

    if (widgetSettingsLoading) {
        return <BookAppointmentFormSkeleton />;
    }

    return (
        <FormProvider {...methods}>
            <div className="kivi-widget" id={`kivi-appointment-widget-${id}`}>
                <div className="container-fluid" id="kivicare-widget-main-content">
                    <div className="widget-layout" id="widgetOrders" style={{ position: 'relative' }}>

                        <div className="iq-card iq-card-lg iq-bg-primary widget-tabs" style={{ overflow: 'hidden' }}>
                            {/* Tab Navigation */}
                            <ul className="tab-list" id="kivicare-animate-ul">
                                {steps.map((step, index) => {
                                    const currentIndex = getCurrentStepIndex();
                                    const isCompleted = index < currentIndex && isStepCompleted(step.id);
                                    const isCurrent = index === currentIndex;

                                    return (
                                        <li
                                            key={step.id}
                                            className={`tab-item ${isCurrent ? 'active' : ''} ${isCompleted ? 'completed' : ''}`}
                                            data-check={isCompleted ? "true" : "false"}
                                        >
                                            <a
                                                className="tab-link"
                                                href={`#${step.id}`}
                                                onClick={async (e) => {
                                                    e.preventDefault();
                                                    const currentIndex = steps.findIndex(s => s.id === currentStep);
                                                    const targetIndex = steps.findIndex(s => s.id === step.id);
                                                    const direction = targetIndex > currentIndex ? 'next' : 'prev';
                                                    await handleStepChange(step.id, direction);
                                                }}
                                                data-iq-toggle="tab"
                                                data-iq-tab="prevent"
                                                id={`${step.id}-tab`}
                                            >
                                                <span className="sidebar-heading-text">{step.label}</span>
                                                <p>{step.description}</p>
                                            </a>
                                        </li>
                                    );
                                })}
                            </ul>
                            {/* Logout Button (if needed) */}
                            {isAuthenticated && (
                                <div className="kc-logout-btn">
                                    <button
                                        id="kivicare_logout_btn"
                                        className="iq-button iq-button-secondary w-100 mt-auto"
                                        onClick={() => {
                                            // Clear authentication state
                                            setIsAuthenticated(false);
                                            // Clear form data
                                            setValue('userDetails', {
                                                firstName: '',
                                                lastName: '',
                                                email: '',
                                                contact: '',
                                                gender: ''
                                            });
                                            logoutMutation.mutate(undefined, {
                                                onSuccess: (response) => {
                                                    // Show success message
                                                    toast.success(__('Logged out successfully', 'kivicare-clinic-management-system'));
                                                    window.location.href = response.data
                                                },
                                                onError: (error) => {
                                                    console.error('Logout error:', error);
                                                    const basePath = window.location.pathname.split('/')[1];
                                                    window.location.href = window.location.origin + '/' + basePath + '/wp-login.php';
                                                }
                                            });
                                        }}
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#fff" viewBox="0 0 256 256"><path d="M120,216a8,8,0,0,1-8,8H48a8,8,0,0,1-8-8V40a8,8,0,0,1,8-8h64a8,8,0,0,1,0,16H56V208h56A8,8,0,0,1,120,216Zm109.66-93.66-40-40a8,8,0,0,0-11.32,11.32L204.69,120H112a8,8,0,0,0,0,16h92.69l-26.35,26.34a8,8,0,0,0,11.32,11.32l40-40A8,8,0,0,0,229.66,122.34Z" /></svg>
                                    </button>
                                </div>
                            )}
                        </div>

                        {/* Main Content Panel */}
                        <div className="widget-pannel alert-relative">
                            {/* Tab Content */}
                            <div className="iq-card iq-card-sm tab-content" id="wizard-tab">
                                <SwitchTransition mode="out-in">
                                    <CSSTransition
                                        key={currentStep}
                                        nodeRef={nodeRef}
                                        timeout={300}
                                        classNames={{
                                            enter: transitionDirection === 'next' ? 'step-enter-next' : 'step-enter-prev',
                                            enterActive: transitionDirection === 'next' ? 'step-enter-active-next' : 'step-enter-active-prev',
                                            exit: transitionDirection === 'next' ? 'step-exit-next' : 'step-exit-prev',
                                            exitActive: transitionDirection === 'next' ? 'step-exit-active-next' : 'step-exit-active-prev'
                                        }}
                                        unmountOnExit
                                    >
                                        <div ref={nodeRef} className="iq-fade iq-tab-pannel active">
                                            <StepWrapper
                                                currentStep={currentStep}
                                                steps={steps}
                                                onBack={handleBack}
                                                onNext={handleNext}
                                                onSubmit={handleSubmit}
                                                isLoading={isLoading}
                                                isStepCompleted={isStepCompleted}
                                                isAuthenticated={isAuthenticated}
                                                setIsAuthenticated={setIsAuthenticated}
                                                isLogin={isLogin}
                                                setIsLogin={setIsLogin}
                                            >
                                                {renderStepContent()}
                                            </StepWrapper>
                                        </div>
                                    </CSSTransition>
                                </SwitchTransition>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Stripe Checkout Modal */}
            <StripeCheckout
                ref={stripeCheckoutRef}
                onPaymentSuccess={handleStripePaymentSuccess}
                onPaymentCancel={handleStripePaymentCancel}
            />
            {/* Razorpay Checkout Modal */}
            <RazorpayCheckout
                ref={razorpayCheckoutRef}
                onPaymentSuccess={handleStripePaymentSuccess}
                onPaymentCancel={handleStripePaymentCancel}
            />
        </FormProvider>
    );
}

// Custom hook for debounced search
const useDebounce = (value, delay) => {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
        const handler = setTimeout(() => {
            setDebouncedValue(value);
        }, delay);

        return () => {
            clearTimeout(handler);
        };
    }, [value, delay]);

    return debouncedValue;
};

// Common Step Wrapper Component
const StepWrapper = ({
    currentStep,
    steps,
    onBack,
    onNext,
    onSubmit,
    isLoading,
    isStepCompleted,
    isAuthenticated,
    setIsAuthenticated,
    isLogin,
    setIsLogin,
    children
}) => {
    const canProceed = isStepCompleted(currentStep);

    return (
        <>
            {children}

            {/* Navigation Buttons Footer */}
            <div className="card-widget-footer">
                {/* Toast Provider positioned at component level for better overlay */}
                <ToastContainer />
                <div className="d-flex align-items-end justify-content-end flex-grow-1 flex-wrap gap-1-5">
                    {steps.length > 0 && currentStep !== steps[0]?.id && (
                        <button
                            type="button"
                            className="iq-button iq-button-secondary"
                            onClick={onBack}
                            disabled={isLoading}
                            data-step="prev"
                        >
                            {__('Back', 'kivicare-clinic-management-system')}
                        </button>
                    )}

                    {/* Show authentication buttons only on detail-info step and when not authenticated */}
                    {currentStep === 'detail-info' && !isAuthenticated && (
                        <AuthenticationButtons
                            setIsAuthenticated={setIsAuthenticated}
                            isLogin={isLogin}
                        />
                    )}

                    {/* Regular navigation buttons */}
                    {currentStep !== 'confirm' && currentStep !== 'booking-success' && currentStep !== 'payment-failed' && (currentStep !== 'detail-info' || isAuthenticated) ? (
                        <button
                            type="button"
                            className={`iq-button iq-button-primary ${!canProceed ? 'disabled' : ''}`}
                            onClick={onNext}
                            data-step="next"
                            disabled={!canProceed}
                            style={{ opacity: canProceed ? 1 : 0.6 }}
                        >
                            {__('Next', 'kivicare-clinic-management-system')}
                        </button>
                    ) : currentStep === 'confirm' ? (
                        <button
                            type="button"
                            className={`iq-button iq-button-primary ${(!canProceed || isLoading) ? 'disabled' : ''}`}
                            onClick={onSubmit}
                            disabled={!canProceed || isLoading}
                            style={{ opacity: (canProceed && !isLoading) ? 1 : 0.6, position: 'relative' }}
                        >
                            {isLoading && (
                                <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            )}
                            {isLoading ? __('Booking...', 'kivicare-clinic-management-system') : __('Confirm Booking', 'kivicare-clinic-management-system')}
                        </button>
                    ) : null}
                </div>
            </div>


        </>
    );
};

// Authentication Buttons Component for StepWrapper
const AuthenticationButtons = ({ setIsAuthenticated, isLogin }) => {
    const { setValue, watch } = useFormContext();
    const userDetails = watch('userDetails') || {};

    // Authentication handlers
    const loginMutation = useLogin();
    const registerMutation = useRegister();

    // Handle form submission by triggering the form submit event
    const handleFormSubmit = () => {
        // Find the appropriate form and trigger its submission
        if (isLogin) {
            // Trigger login form submission
            const loginForm = document.querySelector('.login-register-panel form');
            if (loginForm) {
                // Create and dispatch a submit event
                const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                loginForm.dispatchEvent(submitEvent);
            }
        } else {
            // Trigger register form submission
            const registerForm = document.querySelector('.login-register-panel form');
            if (registerForm) {
                // Create and dispatch a submit event
                const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                registerForm.dispatchEvent(submitEvent);
            }
        }
    };

    return (
        <>
            {isLogin ? (
                <button
                    type="button"
                    className={`iq-button iq-button-primary ${loginMutation.isPending ? 'disabled' : ''}`}
                    disabled={loginMutation.isPending}
                    style={{ opacity: loginMutation.isPending ? 0.6 : 1 }}
                    onClick={handleFormSubmit}
                >
                    {loginMutation.isPending ? __('Logging in...', 'kivicare-clinic-management-system') : __('Login', 'kivicare-clinic-management-system')}
                </button>
            ) : (
                <button
                    type="button"
                    className={`iq-button iq-button-primary ${registerMutation.isPending ? 'disabled' : ''}`}
                    disabled={registerMutation.isPending}
                    style={{ opacity: registerMutation.isPending ? 0.6 : 1 }}
                    onClick={handleFormSubmit}
                >
                    {registerMutation.isPending ? __('Registering...', 'kivicare-clinic-management-system') : __('Register', 'kivicare-clinic-management-system')}
                </button>
            )}
        </>
    );
};

// Common Step Header Component
const StepHeader = ({ title, subtitle, showSearch = false, searchPlaceholder, searchValue, onSearchChange }) => {
    return (
        <>
            <div className="d-flex justify-content-between align-items-center flex-wrap gap-1">
                <div className="iq-kivi-tab-panel-title-animation">
                    <h3 className="iq-kivi-tab-panel-title">{title}</h3>
                    {subtitle && <p className="text-muted">{subtitle}</p>}
                </div>
                {showSearch && (
                    <div className="iq-kivi-search">
                        <svg width="18" height="18" className="iq-kivi-icon" viewBox="0 0 24 24" fill="none">
                            <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="#727d93" fill="none" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"></circle>
                            <path d="M18.0186 18.4851L21.5426 22" stroke="#727d93" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"></path>
                        </svg>
                        <input
                            type="text"
                            className="iq-kivicare-form-control iq-search-bg-color"
                            placeholder={searchPlaceholder}
                            value={searchValue}
                            onChange={onSearchChange}
                        />
                    </div>
                )}
            </div>
            <hr />
        </>
    );
};

// Step Components
const ClinicSelection = ({ selectedDoctor, widgetSettings }) => {
    const [searchTerm, setSearchTerm] = useState('');
    const { setValue, watch } = useFormContext();
    const selectedClinic = watch('selectedClinic');

    // Debounce search term to avoid too many API calls
    const debouncedSearchTerm = useDebounce(searchTerm, 500);

    // Use the useFrontendClinics hook to fetch clinics data
    const {
        data: clinicsResponse,
        isLoading,
        isError,
        error
    } = useFrontendClinics({
        search: debouncedSearchTerm,
        per_page: 20, // Limit results for performance
        status: '1' // Only show active clinics
    });

    // Extract clinics, settings, and pagination from the API response
    const responseData = clinicsResponse?.data || {};
    const clinics = responseData.clinics || [];
    const pagination = responseData.pagination || {};
    const settings = widgetSettings?.clinic || {
        showClinicImage: false,
        showClinicAddress: false,
        clinicContactDetails: { id: 1 }
    };

    const handleClinicSelect = (clinic) => {
        setValue('selectedClinic', clinic, { shouldValidate: true });
    };

    const subtitle = selectedDoctor ? `${__('Doctor:', 'kivicare-clinic-management-system')} ${selectedDoctor.name}` : null;

    // Show loading state
    if (isLoading) {
        return (
            <div>
                <StepHeader
                    title={__('Select Clinic', 'kivicare-clinic-management-system')}
                    subtitle={subtitle}
                    showSearch={true}
                    searchPlaceholder={__('Search clinics....', 'kivicare-clinic-management-system')}
                    searchValue={searchTerm}
                    onSearchChange={(e) => setSearchTerm(e.target.value)}
                />
                <ClinicSelectionSkeleton />
            </div>
        );
    }

    // Show error state
    if (isError) {
        return (
            <div>
                <StepHeader
                    title={__('Select Clinic', 'kivicare-clinic-management-system')}
                    subtitle={subtitle}
                    showSearch={true}
                    searchPlaceholder={__('Search clinics....', 'kivicare-clinic-management-system')}
                    searchValue={searchTerm}
                    onSearchChange={(e) => setSearchTerm(e.target.value)}
                />

                <div className="widget-content">
                    <div className="card-list-data flex-column gap-2">
                        <div className="card-list pe-2 pt-1">
                            <div className="text-center p-4">
                                <p className="text-danger">{__('Error loading clinics:', 'kivicare-clinic-management-system')} {error?.message}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div>
            <StepHeader
                title={__('Select Clinic', 'kivicare-clinic-management-system')}
                subtitle={subtitle}
                showSearch={true}
                searchPlaceholder={__('Search clinics....', 'kivicare-clinic-management-system')}
                searchValue={searchTerm}
                onSearchChange={(e) => setSearchTerm(e.target.value)}
            />

            <div className="widget-content">
                <div className="card-list-data flex-column gap-2 h-100">
                    <div className="d-flex flex-column gap-1 pt-2">
                        <div className="card-list-data text-center pt-2 mb-3">
                            <div className="card-list pe-2 pt-1">
                                <div className="kc-card-list">
                                    {clinics.length > 0 ? clinics.map(clinic => {

                                        const contactDetailsId = Number(settings.clinicContactDetails?.id || 1);
                                        const shouldShowPhoneAndEmail = contactDetailsId === 1;
                                        const shouldShowPhone = contactDetailsId === 2;
                                        const shouldShowEmail = contactDetailsId === 3;
                                        const shouldShowAddress = contactDetailsId === 4 || settings.showClinicAddress;

                                        return (
                                            <div key={clinic.id} className="iq-client-widget">
                                                <input
                                                    type="radio"
                                                    className="card-checkbox selected-clinic"
                                                    name="clinic_selection"
                                                    id={`clinic_${clinic.id}`}
                                                    value={clinic.id}
                                                    clinicname={clinic.name}
                                                    clinicaddress={clinic.address}
                                                    checked={selectedClinic?.id === clinic.id}
                                                    onChange={() => handleClinicSelect(clinic)}
                                                />
                                                <label className="btn-border01 w-100" htmlFor={`clinic_${clinic.id}`}>
                                                    <div className="iq-card iq-card-lg iq-fancy-design iq-card-border iq-clinic-widget">

                                                        {settings.showClinicImage && (
                                                            <div className="d-flex justify-content-center align-items-center">
                                                                <img
                                                                    src={clinic.image}
                                                                    className="avatar-90 rounded-circle object-cover"
                                                                    alt={clinic.name}
                                                                />
                                                            </div>
                                                        )}

                                                        <h3 className="kc-clinic-name mb-1">{clinic.name}</h3>

                                                        {shouldShowAddress && clinic.address && (
                                                            <p className="kc-clinic-address">
                                                                {clinic.address}
                                                                <a
                                                                    className=""
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    href={`https://www.google.com/maps?q=${encodeURIComponent(clinic.address)}`}
                                                                >
                                                                    <svg
                                                                        xmlns="http://www.w3.org/2000/svg"
                                                                        viewBox="0 -256 1850 1850"
                                                                        width="20px"
                                                                        height="20px"
                                                                    >
                                                                        <g transform="matrix(1,0,0,-1,30.372881,1426.9492)">
                                                                            <path
                                                                                fill="var(--iq-primary)"
                                                                                d="M 1408,608 V 288 Q 1408,169 1323.5,84.5 1239,0 1120,0 H 288 Q 169,0 84.5,84.5 0,169 0,288 v 832 Q 0,1239 84.5,1323.5 169,1408 288,1408 h 704 q 14,0 23,-9 9,-9 9,-23 v -64 q 0,-14 -9,-23 -9,-9 -23,-9 H 288 q -66,0 -113,-47 -47,-47 -47,-113 V 288 q 0,-66 47,-113 47,-47 113,-47 h 832 q 66,0 113,47 47,47 47,113 v 320 q 0,14 9,23 9,9 23,9 h 64 q 14,0 23,-9 9,-9 9,-23 z m 384,864 V 960 q 0,-26 -19,-45 -19,-19 -45,-19 -26,0 -45,19 L 1507,1091 855,439 q -10,-10 -23,-10 -13,0 -23,10 L 695,553 q -10,10 -10,23 0,13 10,23 l 652,652 -176,176 q -19,19 -19,45 0,26 19,45 19,19 45,19 h 512 q 26,0 45,-19 19,-19 19,-45 z"
                                                                                style={{ fill: 'currentColor' }}
                                                                            />
                                                                        </g>
                                                                    </svg>
                                                                </a>
                                                            </p>
                                                        )}

                                                        <div className="mt-2">
                                                            {(shouldShowPhoneAndEmail || shouldShowPhone) && clinic.contact_no && (
                                                                <div className="d-flex align-items-center justify-content-sm-between flex-sm-row flex-column flex-wrap">
                                                                    <h6>{__('Phone:', 'kivicare-clinic-management-system')}</h6>
                                                                    <p className="">{clinic.contact_no}</p>
                                                                </div>
                                                            )}
                                                            {(shouldShowPhoneAndEmail || shouldShowEmail) && clinic.email && (
                                                                <div className="d-flex align-items-center justify-content-sm-between flex-sm-row flex-column flex-wrap">
                                                                    <h6>{__('Email:', 'kivicare-clinic-management-system')}</h6>
                                                                    <p className="">{clinic.email}</p>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                        )
                                    }) : (
                                        <div className="text-center p-4">
                                            <p>{__('No clinics found.', 'kivicare-clinic-management-system')}</p>
                                            {debouncedSearchTerm && (
                                                <p className="text-muted">{__('Try adjusting your search terms.', 'kivicare-clinic-management-system')}</p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Pagination Info */}
                    {pagination.total > 0 && (
                        <div className="text-center mt-3">
                            <small className="text-muted">
                                {__('Showing', 'kivicare-clinic-management-system')} {clinics.length} {__('of', 'kivicare-clinic-management-system')} {pagination.total} {__('clinics', 'kivicare-clinic-management-system')}
                                {pagination.lastPage > 1 && (
                                    <span> - {__('Page', 'kivicare-clinic-management-system')} {pagination.currentPage} {__('of', 'kivicare-clinic-management-system')} {pagination.lastPage}</span>
                                )}
                            </small>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

const StarRating = ({ rating = 0 }) => {
    const totalStars = 5;
    const fullStars = Math.round(rating);

    return (
        <div className="kc-star-rating">
            {[...Array(totalStars)].map((_, index) => {
                const starValue = index + 1;
                return (
                    <svg
                        key={index}
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill={starValue <= fullStars ? "#FFD700" : "#E0E0E0"}
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27z" />
                    </svg>
                );
            })}
        </div>
    );
};

const DoctorSelection = ({ isKivicarePro, widgetSettings, clinicId }) => {
    const [searchTerm, setSearchTerm] = useState('');
    const [page, setPage] = useState(1);
    const [allDoctors, setAllDoctors] = useState([]);
    const { setValue, watch } = useFormContext();
    const selectedDoctor = watch('selectedDoctor');
    const selectedClinic = watch('selectedClinic');
    const selectedService = watch('selectedService');
    const finalClinicId = clinicId || selectedClinic?.id;
    const scrollContainerRef = useRef(null);

    // Debounce search term to avoid too many API calls
    const debouncedSearchTerm = useDebounce(searchTerm, 500);

    // Prepare API parameters
    const apiParams = {
        ...(isKivicarePro && finalClinicId && { clinic_id: finalClinicId }),
        ...(selectedService && { service_id: selectedService.service_id }),
        ...(debouncedSearchTerm.trim() && { search: debouncedSearchTerm.trim() }),
        page,
        per_page: 10
    };

    // Use the useFrontendDoctors hook to fetch doctors data
    const {
        data: doctorsResponse,
        isLoading,
        error,
        refetch
    } = useFrontendDoctors(apiParams, {
        staleTime: 5 * 60 * 1000, // 5 minutes
        cacheTime: 10 * 60 * 1000, // 10 minutes
    });

    const doctors = doctorsResponse?.data?.doctors || [];
    const pagination = doctorsResponse?.data?.pagination || {};

    useEffect(() => {
        setAllDoctors(page === 1 ? doctors : prev => [...prev, ...doctors]);
    }, [doctors, page]);

    useEffect(() => {
        setPage(1);
    }, [debouncedSearchTerm, finalClinicId, selectedService]);

    const handleScroll = useCallback(() => {
        if (!scrollContainerRef.current || isLoading || !pagination.has_more) return;
        const { scrollTop, scrollHeight, clientHeight } = scrollContainerRef.current;
        if (scrollTop + clientHeight >= scrollHeight - 50) setPage(prev => prev + 1);
    }, [isLoading, pagination.has_more]);

    const settings = widgetSettings?.doctor || {
        showDoctorImage: false,
        showDoctorExperience: false,
        showDoctorSpeciality: false,
        showDoctorDegree: false,
        doctorContactDetails: { id: 1 },
        showDoctorRating: false,
    };

    const handleDoctorSelect = (doctor) => {
        setValue('selectedDoctor', doctor, { shouldValidate: true });
    };

    const subtitle = isKivicarePro && selectedClinic ? `${__('Clinic:', 'kivicare-clinic-management-system')} ${selectedClinic.name}` : null;

    if (error) {
        return (
            <div className="text-center p-4">
                <p className="text-danger">{__('Error loading doctors. Please try again.', 'kivicare-clinic-management-system')}</p>
                <button
                    type="button"
                    className="iq-button iq-button-primary mt-2"
                    onClick={() => refetch()}
                >
                    {__('Retry', 'kivicare-clinic-management-system')}
                </button>
            </div>
        );
    }

    return (
        <div>
            <StepHeader
                title={__('Select Doctor', 'kivicare-clinic-management-system')}
                subtitle={subtitle}
                showSearch={true}
                searchPlaceholder={__('Search doctors....', 'kivicare-clinic-management-system')}
                searchValue={searchTerm}
                onSearchChange={(e) => setSearchTerm(e.target.value)}
            />

            <div className="widget-content">
                {isLoading && page === 1 ? (
                    <DoctorSelectionSkeleton />
                ) : (
                    <div className="card-list-data text-center pt-2 card-list" style={{ maxHeight: '500px', overflowY: 'auto' }} ref={scrollContainerRef} onScroll={handleScroll}>
                        <div className="card-list pe-2 pt-1">
                            <div className="kc-card-list">
                                {allDoctors.map(doctor => {
                                    const contactDetailsId = Number(settings.doctorContactDetails?.id || 1);
                                    const shouldShowPhoneAndEmail = contactDetailsId === 1;
                                    const shouldShowPhone = contactDetailsId === 2;
                                    const shouldShowEmail = contactDetailsId === 3;

                                    return (
                                        <div key={doctor.id} className="iq-client-widget" data-index={doctor.id}>
                                            <input
                                                type="radio"
                                                className="card-checkbox selected-doctor kivicare-doctor-widget"
                                                name="card_main"
                                                id={`doctor_${doctor.id}`}
                                                value={doctor.id}
                                                doctorname={doctor.name}
                                                checked={selectedDoctor?.id === doctor.id}
                                                onChange={() => handleDoctorSelect(doctor)}
                                            />
                                            <label className="btn-border01 w-100" htmlFor={`doctor_${doctor.id}`}>
                                                <div className="iq-card iq-fancy-design iq-doctor-widget">
                                                    <div className="iq-navbar-header" style={{ height: '100px' }}>
                                                        <div className="profile-bg"></div>
                                                    </div>
                                                    {doctor.is_telemed_connected && (
                                                        <div className="iq-top-left-ribbon">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" viewBox="0 0 20 20" fill="none">
                                                                <path fillRule="evenodd" clipRule="evenodd" d="M13.5807 12.9484C13.6481 14.4752 12.416 15.7662 10.8288 15.8311C10.7119 15.836 5.01274 15.8245 5.01274 15.8245C3.43328 15.9444 2.05094 14.8094 1.92636 13.2884C1.91697 13.1751 1.91953 7.06 1.91953 7.06C1.84956 5.53163 3.08002 4.23733 4.66801 4.16998C4.78661 4.16424 10.4781 4.17491 10.4781 4.17491C12.0653 4.05665 13.4519 5.19984 13.5747 6.72821C13.5833 6.83826 13.5807 12.9484 13.5807 12.9484Z" stroke="white" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"></path>
                                                                <path d="M13.5834 8.31621L16.3275 6.07037C17.0075 5.51371 18.0275 5.99871 18.0267 6.87621L18.0167 13.0004C18.0159 13.8779 16.995 14.3587 16.3167 13.802L13.5834 11.5562" stroke="white" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"></path>
                                                            </svg>
                                                        </div>
                                                    )}
                                                    {settings.showDoctorImage && (
                                                        <div className="media d-flex justify-content-center align-items-center">
                                                            <img
                                                                src={doctor.image}
                                                                className="avatar-90 rounded-circle object-cover"
                                                                alt={doctor.id}
                                                                onError={(e) => {
                                                                    e.target.src = 'http://localhost/wp_plugins/kivicare/wp-content/plugins/kivicare-clinic-management-system/assets/images/kc-demo-img.png';
                                                                }}
                                                            />
                                                        </div>
                                                    )}

                                                    <h5 className="mt-2">{doctor.name}</h5>

                                                    {settings.showDoctorSpeciality && doctor.specialties && doctor.specialties.length > 0 && (
                                                        <span className="iq-letter-spacing-1 iq-text-uppercase mb-1">
                                                            {Array.isArray(doctor.specialties)
                                                                ? doctor.specialties.map(spec => spec.label || spec).join(', ')
                                                                : doctor.specialties}
                                                        </span>
                                                    )}

                                                    {settings.showDoctorDegree && doctor.qualifications && doctor.qualifications.length > 0 && (
                                                        <p className="mb-0">
                                                            {Array.isArray(doctor.qualifications)
                                                                ? doctor.qualifications.map(q => q.label || q.degree).join(', ')
                                                                : doctor.qualifications}
                                                        </p>
                                                    )}

                                                    {settings.showDoctorRating && doctor.rating > 0 && (
                                                        <div className="my-1">
                                                            <StarRating rating={doctor.rating} />
                                                        </div>
                                                    )}

                                                    {settings.showDoctorExperience && doctor.experience !== undefined && (
                                                        <div className="my-2 iq-doctor-badge">
                                                            <span className="iq-badge iq-bg-secondary iq-color-white">
                                                                {__('Exp', 'kivicare-clinic-management-system')} : {doctor.experience}yr
                                                            </span>
                                                        </div>
                                                    )}

                                                    <div className="mt-2">
                                                        {(shouldShowPhoneAndEmail || shouldShowPhone) && doctor.contactNumber && (
                                                            <div className="d-flex align-items-center justify-content-sm-between flex-sm-row flex-column flex-wrap">
                                                                <h6>{__('Phone:', 'kivicare-clinic-management-system')}</h6>
                                                                <p className="">{doctor.contactNumber}</p>
                                                            </div>
                                                        )}
                                                        {(shouldShowPhoneAndEmail || shouldShowEmail) && doctor.email && (
                                                            <div className="d-flex align-items-center justify-content-sm-between flex-sm-row flex-column flex-wrap">
                                                                <h6>{__('Email:', 'kivicare-clinic-management-system')}</h6>
                                                                <p className="">{doctor.email}</p>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    )
                                })}
                            </div>
                            {allDoctors.length === 0 && !isLoading && (
                                <div className="text-center p-4">
                                    <p>{__('No doctors found for the selected criteria.', 'kivicare-clinic-management-system')}</p>
                                </div>
                            )}
                            {isLoading && page > 1 && (
                                <div className="text-center p-2">
                                    <div className="spinner-border spinner-border-sm" role="status">
                                        <span className="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

const ServiceSelection = ({ onNext, widgetSettings, doctorId: doctorIdProp }) => {
    const [searchTerm, setSearchTerm] = useState('');
    const { setValue, watch } = useFormContext();
    const selectedService = watch('selectedService');
    const selectedDoctor = watch('selectedDoctor');
    const selectedClinic = watch('selectedClinic');

    // Debounce search term to avoid too many API calls
    const debouncedSearchTerm = useDebounce(searchTerm, 500);

    // Use pre-selected doctor if provided, otherwise form selection
    const doctorParam = doctorIdProp || selectedDoctor?.id;

    // Prepare API parameters
    const apiParams = {
        ...(selectedClinic && { clinic_id: selectedClinic.id }),
        ...(debouncedSearchTerm && { search: debouncedSearchTerm }),
        status: '1'
    };
    if (doctorParam) {
        if (typeof doctorParam === 'string' && doctorParam.includes(',')) {
            apiParams.doctor_ids = doctorParam;
        } else {
            apiParams.doctor_id = doctorParam;
        }
    }

    // Use the useFrontendBookAppointmentServices hook to fetch services data
    const {
        data: servicesResponse,
        isLoading,
        isError,
        error,
        refetch
    } = useFrontendBookAppointmentServices(apiParams, {
        enabled: true,
        staleTime: 5 * 60 * 1000, // 5 minutes
        cacheTime: 10 * 60 * 1000, // 10 minutes
    });

    const services = servicesResponse?.data || {};
    const settings = widgetSettings?.service || {
        showServiceImage: true,
        showServicetype: true,
        showServicePrice: true,
        showServiceDuration: true,
        skip_service_when_single: false
    };

    useEffect(() => {
        if (
            !isLoading &&
            services &&
            services.length === 1 &&
            settings.skip_service_when_single
        ) {
            setValue('selectedService', services[0], { shouldValidate: true });
            onNext();
        }
    }, [services, isLoading, settings.skip_service_when_single, setValue, onNext]);

    const handleServiceSelect = (service) => {
        setValue('selectedService', service, { shouldValidate: true });
    };

    // Construct subtitle based on what's available
    const subtitleParts = [];

    if (selectedClinic) {
        subtitleParts.push(`${__('Clinic:', 'kivicare-clinic-management-system')} ${selectedClinic.name}`);
    }

    if (selectedDoctor) {
        subtitleParts.push(`${__('Doctor:', 'kivicare-clinic-management-system')} ${selectedDoctor.name}`);
    }

    const subtitle = subtitleParts.join(' | ');


    // Show loading state
    if (isLoading) {
        return (
            <div>
                <StepHeader
                    title={__('Select Service', 'kivicare-clinic-management-system')}
                    subtitle={subtitle}
                    showSearch={true}
                    searchPlaceholder={__('Search services....', 'kivicare-clinic-management-system')}
                    searchValue={searchTerm}
                    onSearchChange={(e) => setSearchTerm(e.target.value)}
                />
                <ServiceSelectionSkeleton />
            </div>
        );
    }

    // Show error state
    if (isError) {
        return (
            <div>
                <StepHeader
                    title={__('Select Service', 'kivicare-clinic-management-system')}
                    subtitle={subtitle}
                    showSearch={true}
                    searchPlaceholder={__('Search services....', 'kivicare-clinic-management-system')}
                    searchValue={searchTerm}
                    onSearchChange={(e) => setSearchTerm(e.target.value)}
                />

                <div className="widget-content">
                    <div className="card-list-data flex-column gap-2">
                        <div className="card-list pe-2 pt-1">
                            <div className="text-center p-4">
                                <p className="text-danger">{__('Error loading services:', 'kivicare-clinic-management-system')} {error?.message}</p>
                                <button
                                    type="button"
                                    className="iq-button iq-button-primary mt-2"
                                    onClick={() => refetch()}
                                >
                                    {__('Retry', 'kivicare-clinic-management-system')}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    // Group services by category for better organization
    const servicesByCategory = services.reduce((acc, service) => {
        const category = service.service_type || __('General Services', 'kivicare-clinic-management-system');
        if (!acc[category]) {
            acc[category] = [];
        }
        acc[category].push(service);
        return acc;
    }, {});

    return (
        <div>
            <StepHeader
                title={__('Select Service', 'kivicare-clinic-management-system')}
                subtitle={subtitle}
                showSearch={true}
                searchPlaceholder={__('Search services....', 'kivicare-clinic-management-system')}
                searchValue={searchTerm}
                onSearchChange={(e) => setSearchTerm(e.target.value)}
            />

            <div className="widget-content">
                <div className="card-list-data flex-column gap-2">
                    <div className="card-list d-flex flex-column">
                        <div className="card-list pe-2 pt-1">
                            {Object.keys(servicesByCategory).length > 0 ? (
                                Object.entries(servicesByCategory).map(([category, categoryServices]) => (
                                    <div key={category} className="d-flex flex-column gap-1 pt-2">
                                        {settings.showServicetype && (
                                            <h5 className="iq-color-secondary iq-letter-spacing-1 pl-1">{category}</h5>
                                        )}
                                        <div className="text-center iq-category-list">
                                            <div className="kc-service-card-list">
                                                {categoryServices.map(service => (
                                                    <div key={service.id} className="iq-client-widget">
                                                        <input
                                                            type="radio"
                                                            className="card-checkbox selected-service selected-service-single"
                                                            name="service_selection"
                                                            id={`service_${service.id}`}
                                                            value={service.id}
                                                            checked={selectedService?.id === service.id}
                                                            onChange={() => handleServiceSelect(service)}
                                                        />
                                                        <label className="btn-border01 service-content" htmlFor={`service_${service.id}`}>
                                                            <div className="iq-card iq-fancy-design service-content gap-1 kc-service-card">
                                                                <div className="iq-top-left-ribbon-service" style={{ display: 'none' }}>
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" viewBox="0 0 20 20" fill="none">
                                                                        <path fillRule="evenodd" clipRule="evenodd" d="M13.5807 12.9484C13.6481 14.4752 12.416 15.7662 10.8288 15.8311C10.7119 15.836 5.01274 15.8245 5.01274 15.8245C3.43328 15.9444 2.05094 14.8094 1.92636 13.2884C1.91697 13.1751 1.91953 7.06 1.91953 7.06C1.84956 5.53163 3.08002 4.23733 4.66801 4.16998C4.78661 4.16424 10.4781 4.17491 10.4781 4.17491C12.0653 4.05665 13.4519 5.19984 13.5747 6.72821C13.5833 6.83826 13.5807 12.9484 13.5807 12.9484Z" stroke="white" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"></path>
                                                                        <path d="M13.5834 8.31621L16.3275 6.07037C17.0075 5.51371 18.0275 5.99871 18.0267 6.87621L18.0167 13.0004C18.0159 13.8779 16.995 14.3587 16.3167 13.802L13.5834 11.5562" stroke="white" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"></path>
                                                                    </svg>
                                                                </div>
                                                                {service.telemed_service === "yes" && (
                                                                    <div className="iq-top-left-ribbon">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" viewBox="0 0 20 20" fill="none">
                                                                            <path fillRule="evenodd" clipRule="evenodd" d="M13.5807 12.9484C13.6481 14.4752 12.416 15.7662 10.8288 15.8311C10.7119 15.836 5.01274 15.8245 5.01274 15.8245C3.43328 15.9444 2.05094 14.8094 1.92636 13.2884C1.91697 13.1751 1.91953 7.06 1.91953 7.06C1.84956 5.53163 3.08002 4.23733 4.66801 4.16998C4.78661 4.16424 10.4781 4.17491 10.4781 4.17491C12.0653 4.05665 13.4519 5.19984 13.5747 6.72821C13.5833 6.83826 13.5807 12.9484 13.5807 12.9484Z" stroke="white" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"></path>
                                                                            <path d="M13.5834 8.31621L16.3275 6.07037C17.0075 5.51371 18.0275 5.99871 18.0267 6.87621L18.0167 13.0004C18.0159 13.8779 16.995 14.3587 16.3167 13.802L13.5834 11.5562" stroke="white" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"></path>
                                                                        </svg>
                                                                    </div>
                                                                )}
                                                                {settings.showServiceImage && (
                                                                    <div className="d-flex align-items-center justify-content-center">
                                                                        <div className="avatar-70 avatar icon-img">
                                                                            <img
                                                                                src={service.service_image_url || window.kc_frontend.place_holder_img}
                                                                                alt="service_image"
                                                                                className="avatar-70 rounded-circle"
                                                                                onError={(e) => {
                                                                                    e.target.src = window.kc_frontend.place_holder_img;
                                                                                }}
                                                                            />
                                                                        </div>
                                                                    </div>
                                                                )}
                                                                <div className="d-flex flex-column">
                                                                    <div className="kc-service-name">
                                                                        <h6>{service.name}</h6>
                                                                    </div>
                                                                    {settings.showServicePrice && (
                                                                        <p className="iq-dentist-price">
                                                                            {__('Base Price:', 'kivicare-clinic-management-system')} {service.charges}
                                                                        </p>
                                                                    )}
                                                                    {settings.showServiceDuration && service.duration > 0 && (
                                                                        <p className="iq-service-duration">
                                                                            {__('Duration:', 'kivicare-clinic-management-system')} {service.duration} {__('min', 'kivicare-clinic-management-system')}
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-center p-4">
                                    <p>{__('No services found for the selected doctor.', 'kivicare-clinic-management-system')}</p>
                                    {debouncedSearchTerm && (
                                        <p className="text-muted">{__('Try adjusting your search terms.', 'kivicare-clinic-management-system')}</p>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

const DateTimeSelection = ({ timezone, doctorId: doctorIdProp, clinicId: clinicIdProp }) => {

    const { setValue, watch } = useFormContext();
    const selectedDoctor = watch('selectedDoctor');
    const selectedClinic = watch('selectedClinic');
    const selectedDate = watch('selectedDate');
    const selectedTime = watch('selectedTime');
    const watchedService = watch('selectedService');

    const [localSelectedDate, setLocalSelectedDate] = useState(null);
    const calendarSlotDataRef = useRef({});
    const flatpickrRef = useRef(null);
    const queryClient = useQueryClient();

    const doctorId = doctorIdProp || selectedDoctor?.id;
    const clinicId = clinicIdProp || selectedClinic?.id;

    // Function to fetch and merge month slot data
    const fetchMonthSlots = useCallback(async (year, month) => {
        const serviceId = watchedService?.id;

        // Don't fetch if required data is missing
        if (!doctorId || !serviceId) {
            return;
        }

        const monthString = `${year}-${String(month).padStart(2, '0')}`;

        try {
            // Fetch month slots using appointmentsService
            const params = {
                doctor_id: doctorId,
                clinic_id: clinicId,
                service_id: [serviceId],
                date: monthString,
            };

            // Use queryClient.fetchQuery with proper query keys and service
            const response = await queryClient.fetchQuery({
                queryKey: appointmentKeys.availableSlots(params),
                queryFn: () => appointmentsService.getAvailableSlots(params),
                staleTime: 1000 * 60 * 5, // 5 minutes
            });

            // Merge new month data with existing data
            if (response?.data?.dates) {
                Object.entries(response.data.dates).forEach(([dateString, slotInfo]) => {
                    const cacheKey = `${doctorId}-${clinicId}-${dateString}`;
                    calendarSlotDataRef.current[cacheKey] = {
                        total_count: slotInfo.total_count,
                        available_count: slotInfo.available_count,
                        date: slotInfo.date,
                        day_of_week: slotInfo.day_of_week
                    };
                });

                // Redraw the calendar to reflect new slot data
                if (flatpickrRef.current?.flatpickr) {
                    flatpickrRef.current.flatpickr.redraw();
                }
            }
        } catch (error) {
            console.error('Error fetching month slots:', error);
        }
    }, [doctorId, clinicId, watchedService, queryClient]);


    const handleDateSelect = (selectedDates) => {
        if (selectedDates.length > 0) {
            const date = selectedDates[0];
            setLocalSelectedDate(date);
            // Reset time selection when date changes
            setValue('selectedTime', null, { shouldValidate: true });
            // Update form with selected date
            setValue('selectedDate', formatDateLocal(date), { shouldValidate: true });
        }
    };

    const handleTimeSelect = (time) => {
        setValue('selectedTime', time, { shouldValidate: true });
    };

    // Fetch available slots when doctor and date are selected
    const { data: availableSlotsData, isLoading: isLoadingSlots } = useAvailableSlots({
        doctor_id: doctorId,
        clinic_id: clinicId,
        service_id: [watchedService?.id],
        date: selectedDate
    }, {
        enabled: !!doctorId && !!selectedDate && !!watchedService?.id
    });
    const timeSlots = availableSlotsData?.data?.slots_by_session || [];

    const { data: getHolidaySchedules } = useUnavailableSchedule({
        clinic_id: clinicId,
        doctor_id: doctorId,
    }, {
        enabled: !!doctorId && !!clinicId,
    });
    const holidaySchedulesData = getHolidaySchedules?.data || null;

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Fetch initial month slot data when doctor or service changes
    useEffect(() => {
        if (doctorId && watchedService?.id) {
            const now = new Date();
            const year = now.getFullYear();
            const month = now.getMonth() + 1;

            // Clear previous slot data when doctor/service changes
            calendarSlotDataRef.current = {};

            // Fetch current month data
            fetchMonthSlots(year, month);
        }
    }, [doctorId, watchedService, fetchMonthSlots]);

    return (
        <div>
            <div className="d-flex justify-content-between align-items-center flex-wrap gap-1">
                <div className="iq-kivi-tab-panel-title-animation">
                    <h3 className="iq-kivi-tab-panel-title">{__('Select Date and Time', 'kivicare-clinic-management-system')}</h3>
                </div>
                <div id="iq_kivi_timezone" className="iq-kivi-timezone d-flex align-items-center gap-05">
                    <i class="ph ph-globe"></i>
                    <span>{__('Time Zone:', 'kivicare-clinic-management-system')} </span>{timezone || 'UTC'}
                </div>
            </div>
            <hr />

            <div className="widget-content">
                <div className="card-list-data">
                    <div className="d-grid grid-template-2 card-list-data iq-kivi-calendar-slot">
                        <div>
                            <h5 className='mb-2'>{__('Select Date', 'kivicare-clinic-management-system')}</h5>
                            <div className={["calender-wrap text-center", !selectedDate ? 'error' : ''].join(' ')}>
                                <div className="iq-calendar-card kc-flatpickr-calendar">
                                    <Flatpickr
                                        options={{
                                            inline: true,
                                            dateFormat: convertPhpDateFormatToFlatpickr(window.kc_frontend.date_format),
                                            minDate: today,
                                            disableMobile: true,
                                            locale: GetLocalizedCalendarLabels(),
                                            disable: [
                                                function (date) {
                                                    if (!holidaySchedulesData) return false;
                                                    const { off_days = [], holidays = [] } = holidaySchedulesData;
                                                    if (off_days.includes(String(date.getDay()))) return true;
                                                    const dateString = formatDateLocal(date);
                                                    return holidays.includes(dateString);
                                                }
                                            ],
                                            onMonthChange: (selectedDates, dateStr, instance) => {
                                                // Fetch slots for the new month
                                                const month = instance.currentMonth + 1; // 0-indexed, so add 1
                                                const year = instance.currentYear;
                                                fetchMonthSlots(year, month);
                                            },
                                            onDayCreate: (_dObj, _dStr, fp, dayElem) => {
                                                const date = dayElem.dateObj;
                                                if (!date) return;

                                                // ---- Normalize today ----
                                                const today = new Date();
                                                today.setHours(0, 0, 0, 0);

                                                // ---- Past date: hard stop ----
                                                if (date < today) {
                                                    dayElem.classList.add('past-date');
                                                    return;
                                                }

                                                // ---- Localize day number ----
                                                dayElem.innerHTML = dayElem.innerHTML.replace(
                                                    /\d+/g,
                                                    match => getLocalizedNumber(match)
                                                );

                                                const dateString = formatDateLocal(date);

                                                // ---- Holiday / off-day detection ----
                                                const { off_days = [], holidays = [] } = holidaySchedulesData || {};
                                                const holidayList = Array.isArray(holidays) ? holidays : Object.values(holidays);

                                                const isOffDay = off_days.includes(String(date.getDay()));
                                                const isHoliday = holidayList.includes(dateString);

                                                // ---- Tooltip base ----
                                                const tooltip = document.createElement('div');
                                                tooltip.className = 'day-tooltip';

                                                tooltip.innerHTML = `
                                                    <span class="tooltip-date">
                                                        ${dayjs(date).format('dddd, MMMM D, YYYY')}
                                                    </span>
                                                `;

                                                const tooltipInfo = document.createElement('span');
                                                tooltipInfo.className = 'tooltip-info';

                                                const icon = document.createElement('span');
                                                icon.className = 'day-icon';

                                                // ---- Holiday / off-day states ----
                                                if (isHoliday || isOffDay) {
                                                    dayElem.classList.add(isHoliday ? 'clinic-holiday' : 'doctor-leave');
                                                    dayElem.classList.remove('flatpickr-disabled');
                                                    tooltipInfo.textContent = isHoliday
                                                        ? __('Clinic Holiday', 'kivicare-clinic-management-system')
                                                        : __('Doctor Off Day', 'kivicare-clinic-management-system');

                                                    dayElem.append(icon);
                                                    tooltip.append(tooltipInfo);
                                                    dayElem.append(tooltip);
                                                    return;
                                                }

                                                // ---- Doctor / service guard ----
                                                if (!doctorId || !watchedService?.id) {
                                                    tooltipInfo.textContent = __('Select doctor & service first', 'kivicare-clinic-management-system');
                                                    tooltip.append(tooltipInfo);
                                                    dayElem.append(tooltip);
                                                    return;
                                                }

                                                // ---- Slot availability from ref ----
                                                const cacheKey = `${doctorId}-${clinicId}-${dateString}`;
                                                const slotInfo = calendarSlotDataRef.current?.[cacheKey];

                                                if (!slotInfo) {
                                                    tooltipInfo.textContent = __('Available', 'kivicare-clinic-management-system');
                                                    tooltip.append(tooltipInfo);
                                                    dayElem.append(tooltip);
                                                    return;
                                                }

                                                const indicator = document.createElement('span');
                                                indicator.className = 'slot-indicator';

                                                if (slotInfo.available_count > 0) {
                                                    dayElem.classList.add('available');
                                                    indicator.textContent = `${slotInfo.available_count} ${__('Slots', 'kivicare-clinic-management-system')}`;

                                                    tooltipInfo.textContent = `${slotInfo.available_count} ${__('slots available', 'kivicare-clinic-management-system')}`;

                                                    const action = document.createElement('span');
                                                    action.className = 'tooltip-action';
                                                    action.textContent = __('Click to select', 'kivicare-clinic-management-system');

                                                    tooltip.append(tooltipInfo, action);
                                                    dayElem.append(indicator);
                                                } else if (slotInfo.total_count > 0) {
                                                    dayElem.classList.add('slots-full');
                                                    indicator.textContent = __('Full', 'kivicare-clinic-management-system');

                                                    tooltipInfo.textContent = __('All slots are booked', 'kivicare-clinic-management-system');

                                                    dayElem.append(indicator);
                                                    tooltip.append(tooltipInfo);
                                                } else {
                                                    tooltipInfo.textContent = __('Available', 'kivicare-clinic-management-system');
                                                    tooltip.append(tooltipInfo);
                                                }

                                                dayElem.append(tooltip);
                                            }
                                        }}
                                        onChange={handleDateSelect}
                                        ref={(ref) => {
                                            flatpickrRef.current = ref;
                                        }}
                                    />




                                </div>
                                {/* Calendar Legend */}
                                <div className="calendar-legend">
                                    <div className="calendar-legend-title">{__('Calendar Legend', 'kivicare-clinic-management-system')}</div>
                                    <div className="calendar-legend-items">
                                        <div className="calendar-legend-item available">
                                            <div className="legend-icon"><i className="ph ph-clock"></i></div>
                                            <span className="legend-label">{__('Available', 'kivicare-clinic-management-system')}</span>
                                        </div>
                                        <div className="calendar-legend-item slots-full">
                                            <div className="legend-icon"><i className="ph ph-clock"></i></div>
                                            <span className="legend-label">{__('Slots Full', 'kivicare-clinic-management-system')}</span>
                                        </div>
                                        <div className="calendar-legend-item clinic-holiday">
                                            <div className="legend-icon"><i className="ph ph-hospital"></i></div>
                                            <span className="legend-label">{__('Clinic Holiday', 'kivicare-clinic-management-system')}</span>
                                        </div>
                                        <div className="calendar-legend-item doctor-leave">
                                            <div className="legend-icon"><i className="ph ph-stethoscope"></i></div>
                                            <span className="legend-label">{__('Doctor Leave', 'kivicare-clinic-management-system')}</span>
                                        </div>
                                        <div className="calendar-legend-item past-date">
                                            <div className="legend-icon"><i className="ph ph-x"></i></div>
                                            <span className="legend-label">{__('Past Date', 'kivicare-clinic-management-system')}</span>
                                        </div>
                                    </div>
                                </div>

                                {!selectedDate && (
                                    <p className="select-date-error">{__('Please Select Date', 'kivicare-clinic-management-system')}</p>
                                )}

                            </div>
                        </div>

                        {/* Time Slots Section */}
                        {selectedDate && (
                            <div className="time-slots" id="time-slot">
                                <h5 id="selectedDate" name="selectedDate" className="mb-2">
                                    {__('Available time slots', 'kivicare-clinic-management-system')}
                                </h5>
                                <div className="time-slots-card text-center card-list p-3">

                                    <div className="" id="timeSlotLists" name="timeSlotLists">
                                        {isLoadingSlots ? (
                                            <TimeSlotsSkeleton />
                                        ) : timeSlots.length > 0 ? (
                                            timeSlots.map((session, index) => (
                                                <React.Fragment key={`session-${index}`}>
                                                    <div className="session-divider">
                                                        <span>{__(`Session ${index + 1}`, 'kivicare-clinic-management-system')}</span>
                                                    </div>
                                                    <div className="appointments-slot-select">
                                                        {session.map((timeSlot) => (
                                                            <div key={timeSlot.time} className="iq-client-widget iq-time-slot">
                                                                <input
                                                                    type="radio"
                                                                    className="card-checkbox selected-time"
                                                                    name="time_slot_selection"
                                                                    id={`time_slot_${timeSlot.time.replace(/[:\s]/g, '_')}`}
                                                                    value={timeSlot.time}
                                                                    checked={selectedTime === timeSlot.time}
                                                                    onChange={() => handleTimeSelect(timeSlot.time)}
                                                                />
                                                                <label
                                                                    className="iq-button iq-button-white"
                                                                    htmlFor={`time_slot_${timeSlot.time.replace(/[:\s]/g, '_')}`}
                                                                >
                                                                    {timeSlot.time}
                                                                </label>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </React.Fragment>
                                            ))
                                        ) : (
                                            <div className="text-center p-4">
                                                <p>{__('No time slots available for selected date.', 'kivicare-clinic-management-system')}</p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

const AppointmentExtraData = ({ doctorId, widgetSettings, containerId, isKivicarePro, customForms = [], customFormRefs = {}, customFormsDataState = {}, setCustomFormsDataState = () => {}, errorTabs = [] }) => {
    const { setValue, watch, control, formState: { errors } } = useFormContext();
    const description = watch('description');
    const selectedDoctor = watch('selectedDoctor');
    const appointmentFiles = watch('appointmentFiles') || [];
    const finalDoctorId = doctorId || selectedDoctor?.id;
    const is_uploadfile_appointment = widgetSettings?.appointment?.is_uploadfile_appointment || false;
    const is_appointment_description_config_data = widgetSettings?.appointment?.is_appointment_description_config_data || false;

    const handleDescriptionChange = (value) => {
        setValue('description', value, { shouldValidate: true });
    };

    // Determine initial tab based on availability
    const hasCustomForms = customForms.length > 0;
    const hasCustomFields = isKivicarePro === "true" && finalDoctorId;
    
    // Default to 'custom_field' if available, otherwise first custom form
    const getInitialTab = () => {
        if (hasCustomFields) {
            return 'custom_field';
        }
        if (hasCustomForms) {
            return customForms[0].id;
        }
        return '';
    };

    const [activeTab, setActiveTab] = useState(getInitialTab());

    useEffect(() => {
        if (!activeTab && (hasCustomFields || hasCustomForms)) {
            setActiveTab(getInitialTab());
        }
    }, [hasCustomFields, hasCustomForms, customForms]);

    return (
        <div>
            <StepHeader
                title={__('More About Appointment', 'kivicare-clinic-management-system')}
            />

            <div className="widget-content">
                {is_appointment_description_config_data && (
                    <div className="card-list-data pt-2 pe-2 mb-3">
                        <div className="form-group mb-2">
                            <label className="form-label" htmlFor="appointment-descriptions-field">
                                {__('Appointment Descriptions', 'kivicare-clinic-management-system')}
                            </label>
                            <textarea
                                className="iq-kivicare-form-control"
                                id="appointment-descriptions-field"
                                placeholder={__('Enter Appointment Descriptions', 'kivicare-clinic-management-system')}
                                value={description || ''}
                                onChange={(e) => handleDescriptionChange(e.target.value)}
                            />
                        </div>
                    </div>
                )}
                
                {/* Tab Navigation */}
                {(hasCustomFields || hasCustomForms) && (
                    
                    <div className="kc-extra-data-tabs d-flex gap-3 mb-4 flex-wrap">
                         {hasCustomFields && (
                            <button
                                type="button"
                                className={`kc-extra-data-tab-btn ${activeTab === 'custom_field' ? 'is-active' : ''}`}
                                style={errorTabs.includes('custom_field') ? { border: '1px solid red' } : {}}
                                onClick={() => setActiveTab('custom_field')}
                            >
                                {__('Custom Field', 'kivicare-clinic-management-system')}
                            </button>
                        )}
                        {customForms.map(form => (
                            <button
                                key={form.id}
                                type="button"
                                className={`kc-extra-data-tab-btn ${String(activeTab) === String(form.id) ? 'is-active' : ''}`}
                                style={errorTabs.includes(form.id) ? { border: '1px solid red' } : {}}
                                onClick={() => setActiveTab(form.id)}
                            >
                                {form.name?.text || form.title || form.name || __('Custom Form', 'kivicare-clinic-management-system')}
                            </button>
                        ))}
                    </div>
                )}

                {/* Custom Fields for Appointment Module */}
                {hasCustomFields && (
                    <div 
                        className="custom-fields-section animate__animated animate__fadeIn"
                        style={{ display: activeTab === 'custom_field' ? 'block' : 'none' }}
                    >
                        <CustomFieldRenderer
                            moduleType="appointment_module"
                            moduleId={finalDoctorId}
                            control={control}
                            errors={errors}
                            setValue={setValue}
                            watch={watch}
                        />
                    </div>
                )}

                {/* Custom Forms */}
                {hasCustomForms && (
                    <div className="custom-forms-section">
                        {customForms.map(form => {
                            const isActive = String(activeTab) === String(form.id);
                            return (
                                <div 
                                    key={form.id} 
                                    className="mb-4 animate__animated animate__fadeIn"
                                    style={{ display: isActive ? 'block' : 'none' }}
                                >
                                    <h6 className="iq-text-uppercase iq-color-secondary iq-letter-spacing-1 mb-2">
                                        {form.name?.text || form.title || form.name}
                                    </h6>
                                    <CustomFormRenderer 
                                        ref={el => (customFormRefs.current = { ...customFormRefs.current, [form.id]: el })}
                                        fields={form.fields}
                                        data={customFormsDataState[form.id] || {}}
                                        formId={`custom_form_${form.id}`}
                                        readOnly={false}
                                        onChange={(data) => {
                                            setCustomFormsDataState(prev => ({
                                                ...prev,
                                                [form.id]: data
                                            }));
                                        }}
                                    />
                                </div>
                            );
                        })}
                    </div>
                )}
                {is_uploadfile_appointment && (
                    <div className="col-12">
                        <MultiFileUploader
                            name="appointmentFiles"
                            label={__('Upload Documents', 'kivicare-clinic-management-system')}
                            setValue={setValue}
                            defaultFiles={appointmentFiles}
                            allowFile={true}
                            widgetId={containerId} // Use containerId as widgetId
                        />
                    </div>
                )}
            </div>
        </div>
    );
};

const UserDetailsForm = ({ isAuthenticated, setIsAuthenticated, isLogin, setIsLogin, onNext, clinicId, containerId, showOtherGender, defaultCountry }) => {
    const { setValue, watch } = useFormContext();
    const userDetails = watch('userDetails') || {};

    const onUpdateDetails = (field, value) => {
        setValue(`userDetails.${field}`, value, { shouldValidate: true });
    };

    const switchTab = (tab) => {
        setIsLogin(tab === 'login');
    };

    return (
        <div>
            <StepHeader
                title={__('Enter Details', 'kivicare-clinic-management-system')}
            />

            <ul className="nav-tabs">
                <li className={`tab-item ${!isLogin ? 'active' : ''}`} style={{ margin: 0 }}>
                    <a
                        href="#kc_register"
                        className="tab-link"
                        onClick={(e) => { e.preventDefault(); switchTab('register'); }}
                    >
                        {__('Register', 'kivicare-clinic-management-system')}
                    </a>
                </li>
                <li className={`tab-item ${isLogin ? 'active' : ''}`} style={{ margin: 0 }}>
                    <a
                        href="#kc_login"
                        className="tab-link"
                        onClick={(e) => { e.preventDefault(); switchTab('login'); }}
                    >
                        {__('Login', 'kivicare-clinic-management-system')}
                    </a>
                </li>
            </ul>
            <div className="widget-content login-register-panel ">
                {!isLogin ? (
                    <RegisterForm
                        userDetails={userDetails}
                        onUpdateDetails={onUpdateDetails}
                        switchTab={switchTab}
                        setIsAuthenticated={setIsAuthenticated}
                        handleNext={onNext}
                        clinicId={clinicId}
                        showOtherGender={showOtherGender}
                        defaultCountry={defaultCountry}
                    />
                ) : (
                    <LoginForm
                        userDetails={userDetails}
                        onUpdateDetails={onUpdateDetails}
                        switchTab={switchTab}
                        setIsAuthenticated={setIsAuthenticated}
                        handleNext={onNext}
                        containerId={containerId}
                    />
                )}
            </div>
        </div>
    );
};

const RegisterForm = React.memo(({ userDetails, onUpdateDetails, switchTab, setIsAuthenticated, handleNext, clinicId, showOtherGender, defaultCountry }) => {
    const registerMutation = useRegister();
    const { setValue: setMainFormValue, control, formState: { errors: mainFormErrors }, watch: mainWatch } = useFormContext(); // For setting main form user details
    const toast = useToast(); // Add toast functionality
    const queryClient = useQueryClient(); // For invalidating queries after registration
    const selectedDoctor = mainWatch('selectedDoctor');

    // Create separate form for register form validation
    const {
        register,
        handleSubmit,
        formState: { errors, isValid },
        watch,
        setValue,
        reset
    } = useForm({
        mode: 'onBlur', // Changed from 'onChange' to 'onBlur' for better performance
        defaultValues: {
            username: '',
            password: '',
            confirmPassword: '',
            firstName: userDetails.firstName || '',
            lastName: userDetails.lastName || '',
            email: userDetails.email || '',
            contact: userDetails.contact || '',
            gender: userDetails.gender || '',
            patientRoleOnly: true
        }
    });

    const watchedPassword = watch('password');

    // Remove the expensive real-time sync useEffect - only sync on successful registration

    // Handle registration form submission
    const onSubmit = async (data) => {
        try {

            const response = await registerMutation.mutateAsync({
                username: data.username,
                email: data.email,
                firstName: data.firstName,
                lastName: data.lastName,
                password: data.password,
                mobile_number: data.contact,
                gender: data.gender,
                user_clinic: clinicId || mainWatch('selectedClinic')?.id || null,
                patientRoleOnly: data.patientRoleOnly,
                widget_id: document.querySelector(".kc-book-appointment-container")?.id || "book-appointment"
            });

            if (response.status) {
                toast.success(__('Registration successful! You are now registered and logged in.', 'kivicare-clinic-management-system'));

                // Update global nonce for subsequent requests
                if (response.data && response.data.nonce) {
                    if (window.kc_frontend) {
                        window.kc_frontend.nonce = response.data.nonce;
                    }
                    if (window.wpApiSettings) {
                        window.wpApiSettings.nonce = response.data.nonce;
                    }
                }

                // Set authentication state to true
                setIsAuthenticated(true);

                // Invalidate confirmation query to refetch with new authentication
                queryClient.invalidateQueries({
                    queryKey: frontendBookAppointmentKeys.confirmation()
                });

                // Update main form with user details
                setMainFormValue('userDetails.firstName', data.firstName, { shouldValidate: true });
                setMainFormValue('userDetails.lastName', data.lastName, { shouldValidate: true });
                setMainFormValue('userDetails.email', data.email, { shouldValidate: true });
                setMainFormValue('userDetails.contact', data.contact, { shouldValidate: true });
                setMainFormValue('userDetails.gender', data.gender, { shouldValidate: true });

                // Clear form
                reset();

                handleNext(); // Call the next step handler
            } else {
                toast.error(response.message || __('Registration failed. Please try again.', 'kivicare-clinic-management-system'));
            }
        } catch (error) {
            console.error('Registration error:', error);
            const errorMessage = error?.response?.data?.message || error?.message || __('Registration failed. Please try again.', 'kivicare-clinic-management-system');
            toast.error(errorMessage);
        }
    };

    const handlePhoneChange = useCallback((phone) => {
        // Use debounced setValue to prevent excessive re-renders
        setValue('contact', phone, { shouldValidate: false }); // Don't validate on every keystroke
    }, [setValue]);

    // Phone validation function - optimized with useCallback
    const validatePhone = useCallback((phone) => {
        if (!phone) {
            return __('Contact number is required', 'kivicare-clinic-management-system');
        }

        if (phone.length < 10) {
            return __('Contact number must be at least 10 digits', 'kivicare-clinic-management-system');
        }

        // Use the global phone validation utility
        try {
            if (!isPhoneValid(phone)) {
                return __('Please enter a valid phone number', 'kivicare-clinic-management-system');
            }
        } catch (error) {
            // If validation fails due to format issues, still allow submission
            console.warn('Phone validation error:', error);
        }

        return true;
    }, []);

    return (
        <div>
            <form onSubmit={handleSubmit(onSubmit)}>
                <div className="d-grid grid-template-2">
                    <div className="form-group">
                        <label className="form-label" htmlFor="username">
                            {__('Username', 'kivicare-clinic-management-system')} <span>*</span>
                        </label>
                        <input
                            type="text"
                            className={`iq-kivicare-form-control ${errors.username ? 'is-invalid' : ''}`}
                            id="username"
                            placeholder={__('Enter username', 'kivicare-clinic-management-system')}
                            {...register('username', {
                                required: __('Username is required', 'kivicare-clinic-management-system'),
                                minLength: {
                                    value: 3,
                                    message: __('Username must be at least 3 characters long', 'kivicare-clinic-management-system')
                                },
                                pattern: {
                                    value: /^[a-zA-Z0-9_.-]+$/,
                                    message: __('Username can only contain letters, numbers, dots, dashes and underscores', 'kivicare-clinic-management-system')
                                }
                            })}
                        />
                        {errors.username && (
                            <small className="text-danger">{errors.username.message}</small>
                        )}
                    </div>
                    <div className="form-group">
                        <label className="form-label" htmlFor="password">
                            {__('Password', 'kivicare-clinic-management-system')} <span>*</span>
                        </label>
                        <input
                            type="password"
                            className={`iq-kivicare-form-control ${errors.password ? 'is-invalid' : ''}`}
                            id="password"
                            placeholder={__('Enter password', 'kivicare-clinic-management-system')}
                            {...register('password', {
                                required: __('Password is required', 'kivicare-clinic-management-system'),
                                minLength: {
                                    value: 6,
                                    message: __('Password must be at least 6 characters long', 'kivicare-clinic-management-system')
                                }
                            })}
                        />
                        {errors.password && (
                            <small className="text-danger">{errors.password.message}</small>
                        )}
                    </div>
                    <div className="form-group">
                        <label className="form-label" htmlFor="confirmPassword">
                            {__('Confirm Password', 'kivicare-clinic-management-system')} <span>*</span>
                        </label>
                        <input
                            type="password"
                            className={`iq-kivicare-form-control ${errors.confirmPassword ? 'is-invalid' : ''}`}
                            id="confirmPassword"
                            placeholder={__('Confirm password', 'kivicare-clinic-management-system')}
                            {...register('confirmPassword', {
                                required: __('Please confirm your password', 'kivicare-clinic-management-system'),
                                validate: {
                                    matchesPassword: (value) =>
                                        value === watchedPassword || __('Passwords do not match', 'kivicare-clinic-management-system')
                                }
                            })}
                        />
                        {errors.confirmPassword && (
                            <small className="text-danger">{errors.confirmPassword.message}</small>
                        )}
                    </div>
                    <div className="form-group">
                        <label className="form-label" htmlFor="firstName">
                            {__('First Name', 'kivicare-clinic-management-system')} <span>*</span>
                        </label>
                        <input
                            type="text"
                            className={`iq-kivicare-form-control ${errors.firstName ? 'is-invalid' : ''}`}
                            id="firstName"
                            placeholder={__('Enter your first name', 'kivicare-clinic-management-system')}
                            {...register('firstName', {
                                required: __('First name is required', 'kivicare-clinic-management-system'),
                                minLength: {
                                    value: 2,
                                    message: __('First name must be at least 2 characters long', 'kivicare-clinic-management-system')
                                }
                            })}
                        />
                        {errors.firstName && (
                            <small className="text-danger">{errors.firstName.message}</small>
                        )}
                    </div>
                    <div className="form-group">
                        <label className="form-label" htmlFor="lastName">
                            {__('Last Name', 'kivicare-clinic-management-system')} <span>*</span>
                        </label>
                        <input
                            type="text"
                            className={`iq-kivicare-form-control ${errors.lastName ? 'is-invalid' : ''}`}
                            id="lastName"
                            placeholder={__('Enter your last name', 'kivicare-clinic-management-system')}
                            {...register('lastName', {
                                required: __('Last name is required', 'kivicare-clinic-management-system'),
                                minLength: {
                                    value: 2,
                                    message: __('Last name must be at least 2 characters long', 'kivicare-clinic-management-system')
                                }
                            })}
                        />
                        {errors.lastName && (
                            <small className="text-danger">{errors.lastName.message}</small>
                        )}
                    </div>
                    <div className="form-group">
                        <label className="form-label" htmlFor="userEmail">
                            {__('Email', 'kivicare-clinic-management-system')} <span>*</span>
                        </label>
                        <input
                            type="email"
                            className={`iq-kivicare-form-control ${errors.email ? 'is-invalid' : ''}`}
                            id="userEmail"
                            placeholder={__('Enter your email', 'kivicare-clinic-management-system')}
                            {...register('email', {
                                required: __('Email is required', 'kivicare-clinic-management-system'),
                                pattern: {
                                    value: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                                    message: __('Please enter a valid email address', 'kivicare-clinic-management-system')
                                }
                            })}
                        />
                        {errors.email && (
                            <small className="text-danger">{errors.email.message}</small>
                        )}
                    </div>
                    <div className="form-group">
                        <label className="form-label" htmlFor="userContact">
                            {__('Contact', 'kivicare-clinic-management-system')} <span>*</span>
                        </label>
                        <div className="contact-box-inline">
                            <PhoneInput
                                defaultCountry={defaultCountry || "us"}
                                value={watch('contact')}
                                onChange={handlePhoneChange}
                                defaultMenuIsOpen={true}
                                inputProps={{
                                    id: 'userContact',
                                    className: `iq-kivicare-form-control ${errors.contact ? 'is-invalid' : ''}`,
                                    placeholder: __('Enter your contact number', 'kivicare-clinic-management-system'),
                                    onBlur: () => setValue('contact', watch('contact'), { shouldValidate: true }), // Validate on blur instead
                                }}
                            />
                            <input
                                type="hidden"
                                {...register('contact', {
                                    required: __('Contact number is required', 'kivicare-clinic-management-system'),
                                    validate: {
                                        validPhone: validatePhone // Use object notation for better performance
                                    }
                                })}
                            />
                        </div>
                        {errors.contact && (
                            <small className="text-danger">{errors.contact.message}</small>
                        )}
                    </div>
                    <div className="form-group">
                        <label className="form-label" htmlFor="gender">
                            {__('Gender', 'kivicare-clinic-management-system')} <span>*</span>
                        </label>
                        <div className="d-flex flex-wrap gap-2">
                            <div className="custom-control custom-radio custom-control-inline">
                                <input
                                    type="radio"
                                    id="male"
                                    value="male"
                                    className="custom-control-input"
                                    {...register('gender', {
                                        required: __('Please select your gender', 'kivicare-clinic-management-system')
                                    })}
                                />
                                <label className="custom-control-label" htmlFor="male">{__('Male', 'kivicare-clinic-management-system')}</label>
                            </div>
                            <div className="custom-control custom-radio custom-control-inline">
                                <input
                                    type="radio"
                                    id="female"
                                    value="female"
                                    className="custom-control-input"
                                    {...register('gender', {
                                        required: __('Please select your gender', 'kivicare-clinic-management-system')
                                    })}
                                />
                                <label className="custom-control-label" htmlFor="female">{__('Female', 'kivicare-clinic-management-system')}</label>
                            </div>
                            {showOtherGender === 'on' && (
                                <div className="custom-control custom-radio custom-control-inline">
                                    <input
                                        type="radio"
                                        id="other"
                                        value="other"
                                        className="custom-control-input"
                                        {...register('gender', {
                                            required: __('Please select your gender', 'kivicare-clinic-management-system')
                                        })}
                                    />
                                    <label className="custom-control-label" htmlFor="other">{__('Other', 'kivicare-clinic-management-system')}</label>
                                </div>
                            )}
                        </div>
                        {errors.gender && (
                            <small className="text-danger">{errors.gender.message}</small>
                        )}
                    </div>
                    <CustomFieldRenderer
                        moduleType="patient_module"
                        moduleId={null}
                        control={control}
                        errors={mainFormErrors}
                        setValue={setMainFormValue}
                        watch={mainWatch}
                    />
                </div>
            </form>
        </div>
    );
});

const LoginForm = React.memo(({ userDetails, onUpdateDetails, switchTab, setIsAuthenticated, handleNext, containerId }) => {
    const loginMutation = useLogin();
    const toast = useToast(); // Add toast functionality
    const { setValue: setMainFormValue } = useFormContext(); // For setting main form user details

    // Create separate form for login form validation
    const {
        register,
        handleSubmit,
        formState: { errors, isValid },
        reset
    } = useForm({
        mode: 'onBlur', // Changed from 'onChange' to 'onBlur' for better performance
        defaultValues: {
            username: '',
            password: ''
        }
    });

    // Handle login form submission
    const onSubmit = async (data) => {
        try {
            const response = await loginMutation.mutateAsync({
                username: data.username,
                password: data.password,
                containerId: containerId
            });

            if (response.status) {
                toast.success(__('Login successful! You can now proceed to the next step.', 'kivicare-clinic-management-system'));

                // Update global nonce for subsequent requests
                if (response.data && response.data.nonce) {
                    if (window.kc_frontend) {
                        window.kc_frontend.nonce = response.data.nonce;
                    }
                    if (window.wpApiSettings) {
                        window.wpApiSettings.nonce = response.data.nonce;
                    }
                }

                setIsAuthenticated(true);

                // Update user details with logged in user info if available
                if (response.data) {
                    const userData = {
                        firstName: response.data.first_name || userDetails.firstName,
                        lastName: response.data.last_name || userDetails.lastName,
                        email: response.data.user_email || userDetails.email,
                        contact: response.data.mobile_number || userDetails.contact,
                    };

                    // Update main form with user details
                    setMainFormValue('userDetails.firstName', userData.firstName, { shouldValidate: true });
                    setMainFormValue('userDetails.lastName', userData.lastName, { shouldValidate: true });
                    setMainFormValue('userDetails.email', userData.email, { shouldValidate: true });
                    setMainFormValue('userDetails.contact', userData.contact, { shouldValidate: true });

                    // Update local state through callback
                    Object.entries(userData).forEach(([key, value]) => {
                        if (value) onUpdateDetails(key, value);
                    });
                }

                // Clear form
                reset();
                handleNext();
            } else {
                toast.error(response.message || __('Invalid username/email or password.', 'kivicare-clinic-management-system'));
            }
        } catch (error) {
            console.error('Login error:', error);
            let errorMessage = __('Login failed. Please try again.', 'kivicare-clinic-management-system');
            if (error?.response?.data?.message) {
                errorMessage = error.response.data.message;
            } else if (error?.response?.data && typeof error.response.data === 'string') {
                errorMessage = error.response.data;
            } else if (error?.response?.data?.errors && Array.isArray(error.response.data.errors)) {
                errorMessage = error.response.data.errors.join(', ');
            } else if (error?.message) {
                errorMessage = error.message;
            }
            toast.error(errorMessage);
        }
    };

    return (
        <div>
            <form onSubmit={handleSubmit(onSubmit)}>
                <div className="d-grid grid-template-2">
                    <div className="form-group">
                        <label className="form-label" htmlFor="loginUsername">
                            {__('Username or Email', 'kivicare-clinic-management-system')} <span>*</span>
                        </label>
                        <input
                            type="text"
                            className={`iq-kivicare-form-control ${errors.username ? 'is-invalid' : ''}`}
                            id="loginUsername"
                            placeholder={__('Enter your username or email', 'kivicare-clinic-management-system')}
                            {...register('username', {
                                required: __('Username or email is required', 'kivicare-clinic-management-system'),
                                minLength: {
                                    value: 3,
                                    message: __('Username or email must be at least 3 characters long', 'kivicare-clinic-management-system')
                                }
                            })}
                        />
                        {errors.username && (
                            <small className="text-danger">{errors.username.message}</small>
                        )}
                    </div>
                    <div className="form-group">
                        <label className="form-label" htmlFor="loginPassword">
                            {__('Password', 'kivicare-clinic-management-system')} <span>*</span>
                        </label>
                        <div className="password-container" style={{ position: 'relative' }}>
                            <input
                                type="password"
                                className={`iq-kivicare-form-control ${errors.password ? 'is-invalid' : ''}`}
                                id="loginPassword"
                                placeholder="***********"
                                {...register('password', {
                                    required: __('Password is required', 'kivicare-clinic-management-system'),
                                })}
                            />
                        </div>
                        {errors.password && (
                            <small className="text-danger">{errors.password.message}</small>
                        )}
                        <div className="d-flex justify-content-end mt-2">
                            <a
                                href={`${window.kc_frontend?.home_url}/wp-login.php?action=lostpassword`}
                                className="text-primary"
                                style={{ textDecoration: 'none', cursor: 'pointer' }}
                            >
                                {__('Forgot Password?', 'kivicare-clinic-management-system')}
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    );
});

// Helper to render custom field values safely
const renderCustomFieldValue = (value) => {
    if (!value) return null;

    const isFile = (item) => item && typeof item === 'object' && item.url;
    const getFileName = (file) => file.name || file.filename || file.url.split('/').pop();
    const renderLink = (file, key) => (
        <a key={key} href={file.url} target="_blank" rel="noopener noreferrer" className="text-primary d-block" style={{ textDecoration: 'underline' }}>
            {getFileName(file)}
        </a>
    );

    if (isFile(value)) return renderLink(value);
    if (Array.isArray(value) && value.some(isFile)) {
        return <div className="d-flex flex-column">{value.map((file, i) => isFile(file) ? renderLink(file, i) : null)}</div>;
    }

    return typeof value === 'object' ? (value.label || value.value || JSON.stringify(value)) : value;
};

const ConfirmationStep = ({ paymentGateways = [], customForms = [], customFormsDataState = {}, ...rest }) => {
    const { watch, setValue } = useFormContext();
    const formData = watch();

    // Selected IDs (form or rest)
    const finalClinicId =
        rest.clinicId || formData.selectedClinic?.id;

    const finalDoctorId =
        rest.doctorId || formData.selectedDoctor?.id;

    const finalServiceId =
        rest.serviceId ||
        (formData.selectedServices?.length
            ? formData.selectedServices.map(s => s.id)
            : formData.selectedService?.id
                ? [formData.selectedService.id]
                : []);

    const confirmationParams = {
        clinic_id: finalClinicId,
        doctor_id: finalDoctorId,
        service_id: finalServiceId,
        appointment_date: formData.selectedDate,
        appointment_time: formData.selectedTime
    };
    const { data: confirmationData, isLoading } =
        useFrontendBookAppointmentConfirmation(confirmationParams, {
            enabled:
                !!(
                    finalClinicId &&
                    finalDoctorId &&
                    finalServiceId &&
                    finalServiceId.length > 0
                )
        });
    const { data: customFieldsResponse } = useCustomFieldsByModule(
        'appointment_module',
        finalDoctorId,
        {
            enabled: !!finalDoctorId && rest.isKivicarePro === "true"
        }
    );

    const displayCustomFields = customFieldsResponse?.data ?? [];
    const is_uploadfile_appointment = rest.widgetSettings?.appointment?.is_uploadfile_appointment || false;

    // Update parent about grand total
    useEffect(() => {
        if (confirmationData?.data?.grand_total !== undefined) {
            const rawTotal = confirmationData.data.grand_total;

            let total = 0;
            if (typeof rawTotal === 'number') {
                total = rawTotal;
            } else {
                // Remove currency symbols and other non-numeric chars (keep digits, dots, minus)
                const cleanTotal = String(rawTotal).replace(/[^0-9.-]+/g, "");
                total = parseFloat(cleanTotal);
            }

            if (isNaN(total)) {
                console.error('Failed to parse grand total from:', rawTotal);
                // Don't proceed - show error to user
                toast.error('Unable to calculate appointment total. Please try again.');
                return; // or set a flag to prevent booking
            }

            if (rest.setGrandTotal) {
                rest.setGrandTotal(total);
            }
        }
    }, [confirmationData, rest.setGrandTotal]);

    // Payment selection
    const handlePaymentGatewaySelect = (gateway) => {
        setValue('selectedPaymentGateway', gateway);
    };

    if (isLoading) {
        return <ConfirmationStepSkeleton />;
    }

    return (
        <div>
            <StepHeader
                title={__('Confirmation Detail', 'kivicare-clinic-management-system')}
            />
            <div className="card-list-data" id="kivi_confirm_page" style={{ height: '470px', position: 'relative', overflowY: 'auto', overflowX: 'hidden' }}>
                <div className="card-list pe-2 pt-1 w-100">
                    {/* Payment Gateway Selection Column */}
                    <div className="kc-card-list">
                            <div className="kc-confirmation-info-section">
                            {paymentGateways.length > 0 && rest.grandTotal > 0 && (
                                <>
                                    <h6 className="iq-text-uppercase iq-color-secondary iq-letter-spacing-1 mb-2">
                                        {__('Payment Method', 'kivicare-clinic-management-system')}
                                    </h6>
                                    <div className="iq-card iq-preview-details">
                                        {paymentGateways.length === 1 ? (
                                            <div className="payment-gateway-single">
                                                <div className="d-flex align-items-center flex-wrap p-3">
                                                    <img
                                                        src={paymentGateways[0]?.logo || ''}
                                                        alt={paymentGateways[0]?.name}
                                                        className="me-3"
                                                        style={{ width: '40px', height: '40px', objectFit: 'contain' }}
                                                        onError={(e) => { e.target.style.display = 'none'; }}
                                                    />
                                                    <div>
                                                        <h6 className="mb-0">{paymentGateways[0]?.name}</h6>
                                                        <small className="text-muted">{paymentGateways[0]?.description || ''}</small>
                                                    </div>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="payment-gateway-selection">
                                                {paymentGateways.map((gateway) => (
                                                    <div key={gateway.id} className="payment-gateway-option mb-2">
                                                        <input
                                                            type="radio"
                                                            id={`payment_${gateway.id}`}
                                                            name="payment_gateway"
                                                            value={gateway.id}
                                                            checked={formData.selectedPaymentGateway?.id === gateway.id}
                                                            onChange={() => handlePaymentGatewaySelect(gateway)}
                                                            className="me-2"
                                                        />
                                                        <label
                                                            htmlFor={`payment_${gateway.id}`}
                                                            className="d-flex align-items-center p-2 border rounded cursor-pointer w-100"
                                                            style={{
                                                                cursor: 'pointer',
                                                                backgroundColor: formData.selectedPaymentGateway?.id === gateway.id ? '#f8f9fa' : 'transparent'
                                                            }}
                                                        >
                                                            <img
                                                                src={gateway.logo || ''}
                                                                alt={gateway.name}
                                                                className="me-3"
                                                                style={{ width: '40px', height: '40px', objectFit: 'contain' }}
                                                                onError={(e) => { e.target.style.display = 'none'; }}
                                                            />
                                                            <div>
                                                                <h6 className="mb-0">{gateway.name}</h6>
                                                                <small className="text-muted">{gateway.description || ''}</small>
                                                            </div>
                                                        </label>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                </>
                            )}
                                {/* Patient Info Section */}
                                <div className="kc-confirmation-info-section mt-4">
                                    <h6 className="iq-text-uppercase iq-color-secondary iq-letter-spacing-1 mb-2">{__('Patient info', 'kivicare-clinic-management-system')}</h6>
                                    <div className="iq-card iq-preview-details kc-patient-info">
                                        <table className="iq-table-border mb-0" style={{ border: 0 }}>
                                            <tbody>
                                                <tr>
                                                    <td><h6>{__('Name:', 'kivicare-clinic-management-system')}</h6></td>
                                                    <td id="patientName">
                                                        <p>
                                                            {confirmationData?.data?.patient?.full_name ||
                                                                (formData.userDetails?.firstName && formData.userDetails?.lastName ?
                                                                    `${formData.userDetails.firstName} ${formData.userDetails.lastName}` :
                                                                    __('Not provided', 'kivicare-clinic-management-system'))}
                                                        </p>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><h6>{__('Number:', 'kivicare-clinic-management-system')}</h6></td>
                                                    <td id="patientTelephone">
                                                        <p>
                                                            {confirmationData?.data?.patient?.contact_number ||
                                                                formData.userDetails?.contact ||
                                                                __('Not provided', 'kivicare-clinic-management-system')}
                                                        </p>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><h6>{__('Email:', 'kivicare-clinic-management-system')}</h6></td>
                                                    <td id="patientEmail">
                                                        <p>
                                                            {confirmationData?.data?.patient?.email ||
                                                                formData.userDetails?.email ||
                                                                __('Not provided', 'kivicare-clinic-management-system')}
                                                        </p>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
        
                        {/* Clinic Info Section */}
                        <div className="kc-confirmation-info-section">
                            <h6 className="iq-text-uppercase iq-color-secondary iq-letter-spacing-1 mb-2">{__('Clinic info', 'kivicare-clinic-management-system')}</h6>
                            <div className="iq-card iq-preview-details">
                                <table className="iq-table-border mb-0" style={{ border: 0 }}>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <h6 style={{ width: '15em' }}>
                                                    {confirmationData?.data?.clinic?.name ||
                                                        formData.selectedClinic?.name ||
                                                        formData.selectedClinic?.label ||
                                                        __('Not selected', 'kivicare-clinic-management-system')}
                                                </h6>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <p style={{ width: '15em' }}>
                                                    {confirmationData?.data?.clinic?.address ||
                                                        formData.selectedClinic?.address ||
                                                        __('Address not available', 'kivicare-clinic-management-system')}
                                                </p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div className="item-img-1 my-4">
                                <h6 className="iq-text-uppercase iq-color-secondary iq-letter-spacing-1">{__('Appointment summary', 'kivicare-clinic-management-system')}</h6>
                                <div className="iq-card iq-card-border mt-3">
                                    <div className="d-flex justify-content-between align-items-center">
                                        <p>{__('Doctor:', 'kivicare-clinic-management-system')}</p>
                                        <h6 id="doctorname">
                                            {confirmationData?.data?.doctor?.full_name ||
                                                formData.selectedDoctor?.name ||
                                                formData.selectedDoctor?.label ||
                                                __('Not selected', 'kivicare-clinic-management-system')}
                                        </h6>
                                    </div>
                                    <div className="d-flex justify-content-between align-items-center mt-3">
                                        <p>{__('Date:', 'kivicare-clinic-management-system')}</p>
                                        <h6><span id="dateOfAppointment">
                                            {confirmationData?.data?.appointment_date ||
                                                formData.selectedDate ||
                                                __('Not selected', 'kivicare-clinic-management-system')}
                                        </span></h6>
                                    </div>
                                    <div className="d-flex justify-content-between align-items-center mt-3">
                                        <p>{__('Time:', 'kivicare-clinic-management-system')}</p>
                                        <h6><span id="timeOfAppointment">
                                            {confirmationData?.data?.appointment_time ||
                                                formData.selectedTime ||
                                                __('Not selected', 'kivicare-clinic-management-system')}
                                        </span></h6>
                                    </div>
                                    <div className="iq-card iq-preview-details mt-4">
                                        <h6>{__('Services', 'kivicare-clinic-management-system')}</h6>
                                        <span id="services_list">
                                            {confirmationData?.data?.services && confirmationData?.data?.services.length > 0 ? (
                                                confirmationData?.data.services.map((service, index) => (
                                                    <div key={service.id || index} className="d-flex justify-content-between align-items-center mt-1">
                                                        <p>{service.title || __('Not selected', 'kivicare-clinic-management-system')}</p>
                                                        <h6>{service.price || '0'}</h6>
                                                    </div>
                                                ))
                                            ) : (
                                                // Fallback to form data
                                                formData.selectedService ? (
                                                    <div className="d-flex justify-content-between align-items-center mt-1">
                                                        <p>{formData.selectedService.name || formData.selectedService.label || __('Service selected', 'kivicare-clinic-management-system')}</p>
                                                        <h6>{formData.selectedService.price || '0'}</h6>
                                                    </div>
                                                ) : formData.selectedServices && formData.selectedServices.length > 0 ? (
                                                    formData.selectedServices.map((service, index) => (
                                                        <div key={service.id || index} className="d-flex justify-content-between align-items-center mt-1">
                                                            <p>{service.name || service.label || __('Service selected', 'kivicare-clinic-management-system')}</p>
                                                            <h6>{service.price || '0'}</h6>
                                                        </div>
                                                    ))
                                                ) : (
                                                    <p>{__('No services selected', 'kivicare-clinic-management-system')}</p>
                                                )
                                            )}
                                        </span>
                                        {/* {confirmationData?.data?.applied_taxes.length > 0 && (<h6>{__('Taxes', 'kivicare-clinic-management-system')}</h6>)} */}
                                        <span id="taxes_list">
                                            {confirmationData?.data.applied_taxes && (
                                                confirmationData?.data.applied_taxes.map((tax, index) => (
                                                    <div key={tax.id || index} className="d-flex justify-content-between align-items-center mt-1">
                                                        <p>{tax.tax_name || __('Not selected', 'kivicare-clinic-management-system')}</p>
                                                        <h6>{tax.tax_amount || '0'}</h6>
                                                    </div>
                                                ))
                                            )}
                                        </span>
                                    </div>
                                    <hr className="mb-0" />
                                    <div className="d-flex justify-content-between align-items-center kc-total-price mt-4">
                                        <h5>{__('Total Price', 'kivicare-clinic-management-system')}</h5>
                                        <h5 className="iq-color-primary kc-services-total" id="services_total">
                                            {confirmationData?.data?.grand_total || '0'}
                                        </h5>
                                    </div>

                                    {/* uploaded files */}
                                    {is_uploadfile_appointment && (
                                        <>
                                            <hr className="mb-0" />
                                            <div className="mt-4">
                                                <h5>{__('Uploaded Files', 'kivicare-clinic-management-system')}</h5>
                                                {formData.appointmentFiles && formData.appointmentFiles.length > 0 ? (
                                                    <div className="d-flex flex-column mt-2">
                                                        {formData.appointmentFiles.map((file, index) => {
                                                            const isImage = file.type && file.type.startsWith('image/');
                                                            return (
                                                                <div key={index} className="mb-2">
                                                                    <a
                                                                        href={file.url}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        download={!isImage}
                                                                        className="d-flex align-items-center text-primary"
                                                                        style={{ textDecoration: 'none', cursor: 'pointer' }}
                                                                    >
                                                                        <span className="me-2">
                                                                            {isImage ? (
                                                                                <i className="fas fa-image"></i>
                                                                            ) : (
                                                                                <i className="fas fa-file-alt"></i>
                                                                            )}
                                                                        </span>
                                                                        {file.filename}
                                                                    </a>
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                ) : (
                                                    <p className="text-muted">{__('No files uploaded', 'kivicare-clinic-management-system')}</p>
                                                )}
                                            </div>
                                        </>
                                    )}

                                </div>
                            </div>
                        </div>


                        {/* other Info Section */}
                        {(() => {
                            const customFieldFormData = {};
                            Object.keys(formData).forEach(key => {
                                if (key.startsWith('custom_field_')) {
                                    const fieldId = key.replace('custom_field_', '');
                                    customFieldFormData[fieldId] = formData[key];
                                }
                            });
                            const hasDescription = formData.description && formData.description.trim();
                            const hasCustomFields = Object.keys(customFieldFormData).some(key => customFieldFormData[key] && String(customFieldFormData[key]).trim());

                            if (!hasDescription && !hasCustomFields) return null;

                            return (
                                <div className="kc-confirmation-info-section">
                                    <h6 className="iq-text-uppercase iq-color-secondary iq-letter-spacing-1 mb-2">{__('Other info', 'kivicare-clinic-management-system')}</h6>
                                    <div className="iq-card iq-preview-details kc-patient-info">
                                        {hasDescription && (
                                            <div className="mb-3">
                                                <strong>{__('Appointment Description:', 'kivicare-clinic-management-system')}</strong> <span className="mt-1">{formData.description}</span>
                                            </div>
                                        )}
                                        {displayCustomFields.length > 0 && displayCustomFields.map(field => {
                                            const fieldValue = customFieldFormData[field.id];
                                            if (!fieldValue) return null;
                                            return (
                                                <div key={field.id} className="mb-2">
                                                    <strong>{field.name}:</strong> <div>{renderCustomFieldValue(fieldValue)}</div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            );
                        })()}
                    </div>

                </div>
            </div>
        </div>
    );
};

// Add To Calendar Component
const AddToCalendarComponent = () => {
    const { watch } = useFormContext();
    const formData = watch();

    // Get appointment details
    const appointmentDate = formData.selectedDate;
    const appointmentTime = formData.selectedTime;
    const doctorName = formData.selectedDoctor?.name || '';
    const serviceName = formData.selectedService?.name || '';
    const clinicName = formData.selectedClinic?.name || '';
    const clinicAddress = formData.selectedClinic?.address || '';

    // Format date and time for calendar
    const formatCalendarDateTime = (date, time) => {
        if (!date || !time) return null;

        // Parse the time (assuming format like "10:00 AM")
        const [timePart, period] = time.split(' ');
        const [hours, minutes] = timePart.split(':');
        let hour24 = parseInt(hours);

        if (period === 'PM' && hour24 !== 12) {
            hour24 += 12;
        } else if (period === 'AM' && hour24 === 12) {
            hour24 = 0;
        }

        // Create start date
        const startDate = new Date(date);
        startDate.setHours(hour24, parseInt(minutes), 0, 0);

        // Create end date (1 hour later)
        const endDate = new Date(startDate);
        endDate.setHours(endDate.getHours() + 1);

        return {
            start: startDate.toISOString().slice(0, 19),
            end: endDate.toISOString().slice(0, 19)
        };
    };

    const dateTime = formatCalendarDateTime(appointmentDate, appointmentTime);

    if (!dateTime) {
        return null;
    }

    const calendarOptions = {
        name: `${__('Medical Appointment', 'kivicare-clinic-management-system')} - ${serviceName}`,
        description: `${__('Appointment with Dr.', 'kivicare-clinic-management-system')} ${doctorName}${clinicName ? ` ${__('at', 'kivicare-clinic-management-system')} ${clinicName}` : ''}`,
        startDate: dateTime.start.split('T')[0],
        startTime: dateTime.start.split('T')[1],
        endDate: dateTime.end.split('T')[0],
        endTime: dateTime.end.split('T')[1],
        timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        location: clinicAddress || clinicName || '',
        options: ['Apple', 'Google', 'Outlook.com', 'Yahoo'],
        lightMode: 'light',
        language: 'en',
        forceOverlay: true
    };

    return (
        <AddToCalendarButton
            {...calendarOptions}>
            {__('Add To Calendar', 'kivicare-clinic-management-system')}
        </AddToCalendarButton>
    );
};

// Booking Success Step Component
const BookingSuccessStep = ({ onBookMore, appointmentId, showPrintSetting }) => {

    const printMutation = usePrintInvoice();

    const handlePrint = () => {
        if (!appointmentId) {
            Swal.fire({
                icon: 'error',
                title: __('Error', 'kivicare-clinic-management-system'),
                text: __('Appointment ID not found. Cannot print invoice.', 'kivicare-clinic-management-system'),
            });
            return;
        }

        printMutation.mutate(appointmentId, {
            onSuccess: (response) => {
                // Create a Blob from the PDF data and open it in a new tab
                const blob = new Blob([response], { type: 'application/pdf' });
                const url = URL.createObjectURL(blob);
                window.open(url, '_blank');
                URL.revokeObjectURL(url); // Clean up the object URL after use
            },
            onError: (error) => {
                console.error("PDF Generation API Error:", error);
                Swal.fire({
                    icon: 'error',
                    title: __('Error', 'kivicare-clinic-management-system'),
                    text: error?.response?.data?.message || error?.message || __('Failed to generate invoice PDF.', 'kivicare-clinic-management-system'),
                });
            }
        });
    };

    return (
        <div className="text-center">
            <div className="my-4 d-flex justify-content-center">
                <svg width="100" height="100" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="48.5" fill="#13C39C" stroke="#25FFAE" strokeWidth="3" />
                    <path fillRule="evenodd" clipRule="evenodd"
                        d="M75.1743 34.1417L46.514 69.977L24 51.2131L28.2479 46.1156L45.5582 60.5386L69.9971 30L75.1743 34.1417Z"
                        fill="white" />
                </svg>
            </div>
            <div>
                <h2>{__('Your Appointment is Booked Successfully!', 'kivicare-clinic-management-system')}</h2>
                <h6 className="iq-color-body my-3 fw-normal kc-check-email">
                    {__('Please check your email for verification', 'kivicare-clinic-management-system')}
                </h6>
            </div>
            <hr className="my-4 kc-confirmation-hr" />
            <div className="d-flex flex-wrap gap-1 justify-content-center kc-confirmation-buttons">
                <button
                    type="button"
                    className="iq-button iq-button-primary"
                    onClick={onBookMore}
                >
                    {__('Book More Appointments', 'kivicare-clinic-management-system')}
                </button>
                {showPrintSetting && (
                    <>
                        <button
                            type="button"
                            id="kivicare_print_detail"
                            className="iq-button iq-button-secondary"
                            onClick={handlePrint}
                            disabled={printMutation.isPending}
                        >
                            {printMutation.isPending ? __('Generating...', 'kivicare-clinic-management-system') : __('Print Detail', 'kivicare-clinic-management-system')}
                        </button>
                        <AddToCalendarComponent />
                    </>
                )}
            </div>
        </div>
    );
};

// Payment Failed Step Component
const PaymentFailedStep = ({ onTryAgain, onBookMore }) => {
    return (
        <div className="text-center">
            <div className="my-4 d-flex justify-content-center">
                <svg width="100" height="100" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="48.5" fill="#DC3545" stroke="#FF6B6B" strokeWidth="3" />
                    <path fillRule="evenodd" clipRule="evenodd"
                        d="M35 35L65 65M65 35L35 65"
                        stroke="white" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </div>
            <div>
                <h2>{__('Payment Transaction Failed!', 'kivicare-clinic-management-system')}</h2>
                <h6 className="iq-color-body my-3 fw-normal">
                    {__('There was an issue processing your payment. Please try again.', 'kivicare-clinic-management-system')}
                </h6>
            </div>
            <hr className="my-4 kc-confirmation-hr" />
            <div className="d-flex flex-wrap gap-1 justify-content-center kc-confirmation-buttons">
                <button
                    type="button"
                    className="iq-button iq-button-primary"
                    onClick={onTryAgain}
                >
                    {__('Try Again', 'kivicare-clinic-management-system')}
                </button>
                <button
                    type="button"
                    className="iq-button iq-button-secondary"
                    onClick={onBookMore}
                >
                    {__('Book New Appointment', 'kivicare-clinic-management-system')}
                </button>
            </div>
        </div>
    );
};



// Set up translations if available
const localeData = window.kc_frontend?.locale_data || { '': {} };
setLocaleData(localeData, 'kivicare-clinic-management-system');

// Find all instances of the shortcode on the page
const initBookAppointment = () => {
    // Only find containers that haven't been initialized yet
    const containers = document.querySelectorAll('.kc-book-appointment-container:not([data-react-initialized])');

    if (containers.length === 0) {
        return;
    }


    // Render each instance
    containers.forEach((container, index) => {
        // Mark as initialized to prevent duplicate initialization
        container.setAttribute('data-react-initialized', 'true');

        // Create a root for this container
        const root = createRoot(container);

        // Render the component
        root.render(
            <React.StrictMode>
                <QueryClientProvider client={queryClient}>
                    <ToastProvider>
                        <BookAppointmentForm
                            {...container.dataset}
                            doctorId={container.dataset.doctorId}
                            clinicId={container.dataset.clinicId}
                            timezone={container.dataset.timezone}
                            containerId={container.id}
                            defaultClinicId={container.dataset.defaultClinicId}
                        />
                    </ToastProvider>
                    <ReactQueryDevtools initialIsOpen={false} />
                </QueryClientProvider>
            </React.StrictMode>
        );
    });
};

window.initBookAppointment = initBookAppointment;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initBookAppointment);
