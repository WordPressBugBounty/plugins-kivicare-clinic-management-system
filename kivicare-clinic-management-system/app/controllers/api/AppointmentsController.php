<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\baseClasses\KCErrorLogger;
use App\baseClasses\KCPaymentGatewayFactory;
use App\baseClasses\KCTelemedFactory;
use App\models\KCAppointment;
use App\models\KCClinic;
use App\models\KCDoctor;
use App\models\KCPatient;
use App\models\KCService;
use App\models\KCStaticData;
use App\models\KCBill;
use App\models\KCBillItem;
use App\models\KCPatientEncounter;
use App\models\KCServiceDoctorMapping;
use App\models\KCAppointmentServiceMapping;
use App\models\KCPaymentsAppointmentMapping;
use App\models\KCUserMeta;
use App\models\KCPatientMedicalReport;
use App\services\KCAppointmentDataService;
use App\services\KCTimeSlotService;
use App\controllers\api\SettingsController\AppointmentSetting;
use App\models\KCCustomFieldData;
use DateInterval;
use DateTime;
use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use KCProApp\controllers\api\GoogleCalendarIntegration;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class AppointmentsController
 * 
 * API Controller for Appointment-related endpoints
 * 
 * @package App\controllers\api
 */
class AppointmentsController extends KCBaseController
{

    /**
     * @var string The base route for this controller
     */
    protected $route = 'appointments';

    /**
     * Get common arguments used across multiple endpoints
     *
     * @return array
     */
    private function getCommonArgs()
    {
        return [
            'id' => [
                'description' => 'Appointment ID',
                'type' => 'integer',
                'required' => true,
                'validate_callback' => 'rest_validate_request_arg',
                'sanitize_callback' => 'absint',
            ],
            'date' => [
                'description' => 'Date (YYYY-MM-DD)',
                'type' => 'string',
                'validate_callback' => [$this, 'validateDate'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'time' => [
                'description' => 'Time (HH:MM)',
                'type' => 'string',
                'validate_callback' => [$this, 'validateTime'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'serviceId' => [
                'description' => 'Service data (array or JSON string)',
                'type' => ['array', 'string'],
                'required' => true,
                'validate_callback' => [$this, 'validateServices'],
                'sanitize_callback' => function ($param) {
                    if (is_string($param)) {
                        return json_decode($param, true);
                    }
                    return $param;
                },
            ],
        ];
    }

    /**
     * Validate date format
     *
     * @param string $param
     * @return bool
     */
    public function validateDate($param)
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
    }

    /**
     * Validate time format
     *
     * @param string $param
     * @return bool
     */
    public function validateTime($param)
    {
        return preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $param);
    }

    /**
     * Validate service data
     *
     * @param mixed $param
     * @return bool
     */
    public function validateServices($param)
    {
        if (is_string($param)) {
            $decoded = json_decode($param, true);
            return json_last_error() === JSON_ERROR_NONE && is_array($decoded);
        }
        return is_array($param);
    }

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        // Get all appointments
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getAppointments'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getListEndpointArgs()
        ]);

        // Get appointment slots
        $this->registerRoute('/' . $this->route . '/slots', [
            'methods' => 'GET',
            'callback' => [$this, 'getAppointmentSlots'],
            'permission_callback' => '__return_true',
            'args' => $this->getSlotsEndpointArgs()
        ]);

        // Get single appointment
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getAppointment'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => ['id' => $this->getCommonArgs()['id']]
        ]);

        // Get appointment details
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/view', [
            'methods' => 'GET',
            'callback' => [$this, 'getAppointmentDetails'],
            'permission_callback' => [$this, 'checkPermission'],
            // 'args' => ['id' => $this->getCommonArgs()['id']]
        ]);


        // Create appointment
        $this->registerRoute('/' . $this->route, [
            'methods' => 'POST',
            'callback' => [$this, 'createAppointment'],
            'permission_callback' => [$this, 'checkCreatePermission'],
            'args' => $this->getCreateEndpointArgs()
        ]);

        // Update appointment
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateAppointment'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => array_merge(
                ['id' => $this->getCommonArgs()['id']],
                $this->getUpdateEndpointArgs()
            )
        ]);
        // Delete appointment
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteAppointment'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => ['id' => $this->getCommonArgs()['id']]
        ]);

        // Bulk delete appointments
        $this->registerRoute('/' . $this->route . '/bulk/delete', [
            'methods' => 'POST',
            'callback' => [$this, 'bulkDeleteAppointments'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getBulkActionEndpointArgs()
        ]);

        // Update appointment status
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateStatus'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'id' => $this->getCommonArgs()['id'],
                'status' => [
                    'Appointment status (0: Cancelled, 1: Booked, 2: Pending, 3: Check-Out, 4: Check-In)',
                    'type' => 'integer',
                    'required' => true,
                    'validate_callback' => [$this, 'validateStatus'],
                    'sanitize_callback' => 'absint',
                ]
            ]
        ]);

        // Get appointment summary/billing details
        $this->registerRoute('/' . $this->route . '/summary', [
            'methods' => 'GET',
            'callback' => [$this, 'getAppointmentSummary'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'services' => [
                    'description' => 'Array of service IDs',
                    'type' => 'array',
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_array($param) && !empty($param) &&
                            array_all($param, function ($id) {
                                return is_numeric($id) && $id > 0;
                            });
                    },
                    'sanitize_callback' => function ($param) {
                        return array_map('absint', $param);
                    }
                ],
                'doctorId' => [
                    'description' => 'Doctor ID',
                    'type' => 'integer',
                    'required' => $this->kcbase->getLoginUserRole() !== $this->kcbase->getDoctorRole(),
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'default' => $this->kcbase->getLoginUserRole() !== $this->kcbase->getDoctorRole() ? 0 : get_current_user_id(),
                    'sanitize_callback' => 'absint',
                ],
                'clinicId' => [
                    'description' => 'Clinic ID for summary calculation',
                    'type' => 'integer',
                    'required' => (
                        !in_array(
                            $this->kcbase->getLoginUserRole(),
                            [
                                $this->kcbase->getClinicAdminRole(),
                                $this->kcbase->getReceptionistRole()
                            ]
                        ) && isKiviCareProActive()
                    ),
                    'default' => (
                        in_array(
                            $this->kcbase->getLoginUserRole(),
                            [
                                $this->kcbase->getClinicAdminRole(),
                                $this->kcbase->getReceptionistRole()
                            ]
                        ) && isKiviCareProActive()
                    ) ? KCClinic::getClinicIdForCurrentUser() : 0,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                    'sanitize_callback' => function ($val) {
                        if (!isKiviCareProActive()) {
                            return absint(KCClinic::kcGetDefaultClinicId());
                        }
                        if (
                            in_array(
                                $this->kcbase->getLoginUserRole(),
                                [
                                    $this->kcbase->getClinicAdminRole(),
                                    $this->kcbase->getReceptionistRole()
                                ]
                            )
                        ) {
                            return absint(KCClinic::getClinicIdForCurrentUser());
                        }
                        return absint($val);
                    },
                ],
            ]
        ]);

        // Payment success callback
        $this->registerRoute('/' . $this->route . '/payment-success', [
            'methods' => 'GET, POST',
            'callback' => [$this, 'handlePaymentSuccess'],
            'permission_callback' => '__return_true',
            'args' => [
                'appointment_id' => [
                    'description' => 'Appointment ID',
                    'type' => 'integer',
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint',
                ],
                'gateway' => [
                    'description' => 'Payment Gateway',
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => function ($param) {
                        $available_gateways = array_map(function ($gateway) {
                            return $gateway['id'];
                        }, KCPaymentGatewayFactory::get_available_gateways(true));
                        return in_array($param, $available_gateways) || $param === 'knit_pay';
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                // specific parameters
                'paymentId' => [
                    'description' => 'Payment ID',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'PayerID' => [
                    'description' => 'Payer ID',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'token' => [
                    'description' => 'Payment Token',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ]
            ]
        ]);

        // Payment failure/cancel callback
        $this->registerRoute('/' . $this->route . '/payment-cancel', [
            'methods' => 'GET',
            'callback' => [$this, 'handlePaymentCancel'],
            'permission_callback' => '__return_true', // Public endpoint for payment gateway callbacks
            'args' => [
                'appointment_id' => [
                    'description' => 'Appointment ID',
                    'type' => 'integer',
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint',
                ],
                'gateway' => [
                    'description' => 'Payment Gateway',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'token' => [
                    'description' => 'Payment Token',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ]
            ]
        ]);

        // Payment verification endpoint
        $this->registerRoute('/' . $this->route . '/payment-verify', [
            'methods' => 'POST',
            'callback' => [$this, 'handlePaymentVerification'],
            'permission_callback' => '__return_true', // Public endpoint for payment verification
            'args' => [
                'payment_status' => [
                    'description' => 'Payment Status',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'payment_id' => [
                    'description' => 'Payment ID from gateway',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'appointment_id' => [
                    'description' => 'Appointment ID',
                    'type' => 'integer',
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return empty($param) || (is_numeric($param) && $param > 0);
                    },
                    'sanitize_callback' => 'absint',
                ],
                'message' => [
                    'description' => 'Payment message',
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ]
            ]
        ]);

        // Regenerate video conference link
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/regenerate-video-conference', [
            'methods' => 'GET',
            'callback' => [$this, 'handleRegenerateVideoLink'],
            'permission_callback' => function (WP_REST_Request $request) {

                return match ($this->kcbase->getLoginUserRole()) {
                    'administrator' => true,
                    $this->kcbase->getDoctorRole() => function ($param) use ($request) {
                            $appointment = KCAppointment::find($request->get_param('id'));
                            if (!$appointment) {
                                return false;
                            }
                            // Doctors can only regenerate their own appointments
                            return $appointment->doctorId === get_current_user_id();
                        },
                    $this->kcbase->getClinicAdminRole() => function ($param) use ($request) {
                            $appointment = KCAppointment::find($request->get_param('id'));
                            if (!$appointment) {
                                return false;
                            }
                            return $appointment->clinicId === KCClinic::getClinicIdForCurrentUser();
                        },
                    $this->kcbase->getReceptionistRole() => function ($param) use ($request) {
                            $appointment = KCAppointment::find($request->get_param('id'));
                            if (!$appointment) {
                                return false;
                            }
                            // Receptionists can regenerate any appointment in their clinic
                            return $appointment->clinicId === KCClinic::getClinicIdForCurrentUser();
                        },
                    default => false
                };

            },
            'args' => [
                'id' => $this->getCommonArgs()['id'],
            ]
        ]);

        // Export appointments
        $this->registerRoute('/' . $this->route . '/export', [
            'methods' => 'GET',
            'callback' => [$this, 'exportAppointments'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getExportEndpointArgs()
        ]);

    }

    /**
     * Get arguments for the slots endpoint
     */
    private function getSlotsEndpointArgs()
    {
        return [
            'date' => array_merge($this->getCommonArgs()['date'], ['required' => true]),
            'doctor_id' => [
                'description' => 'Doctor ID for slot generation',
                'type' => 'integer',
                'required' => $this->kcbase->getLoginUserRole() !== $this->kcbase->getDoctorRole(),
                'default' => $this->kcbase->getLoginUserRole() !== $this->kcbase->getDoctorRole() ? 0 : get_current_user_id(),
                'validate_callback' => function ($param) {
                    return is_numeric($param) && $param > 0;
                },
                'sanitize_callback' => 'absint',
            ],
            'clinic_id' => [
                'description' => 'Clinic ID for slot generation',
                'type' => 'integer',
                'required' => (
                    !in_array(
                        $this->kcbase->getLoginUserRole(),
                        [
                            $this->kcbase->getClinicAdminRole(),
                            $this->kcbase->getReceptionistRole()
                        ]
                    ) && isKiviCareProActive()
                ),
                'default' => (
                    in_array(
                        $this->kcbase->getLoginUserRole(),
                        [
                            $this->kcbase->getClinicAdminRole(),
                            $this->kcbase->getReceptionistRole()
                        ]
                    ) && isKiviCareProActive()
                ) ? KCClinic::getClinicIdForCurrentUser() : 0,
                'validate_callback' => function ($param) {
                    return is_numeric($param);
                },
                'sanitize_callback' => function ($val) {
                    if (!isKiviCareProActive()) {
                        return absint(KCClinic::kcGetDefaultClinicId());
                    }
                    if (
                        in_array(
                            $this->kcbase->getLoginUserRole(),
                            [
                                $this->kcbase->getClinicAdminRole(),
                                $this->kcbase->getReceptionistRole()
                            ]
                        )
                    ) {
                        return absint(KCClinic::getClinicIdForCurrentUser());
                    }
                    return absint($val);
                },
            ],
            'service_id' => [
                'description' => 'Service data (array or JSON string)',
                'type' => ['array', 'string'],
                'required' => true,
                'validate_callback' => [$this, 'validateServices'],
                'sanitize_callback' => function ($param) {
                    if (is_string($param)) {
                        return json_decode($param, true);
                    }
                    return $param;
                },
            ],
            'appointment_id' => [
                'description' => 'Appointment ID to skip (for editing)',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    return empty($param) || (is_numeric($param) && $param > 0);
                },
                'sanitize_callback' => 'absint',
            ],
            'only_available_slots' => [
                'description' => 'Return only future available slots',
                'type' => 'boolean',
                'default' => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ]
        ];
    }

    /**
     * Validate appointment status
     *
     * @param mixed $param
     * @return bool
     */
    public function validateStatus($param)
    {
        return in_array(intval($param), [0, 1, 2, 4, 3]);
    }

    /**
     * Check if user has permission to access slot endpoints
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkSlotsPermission($request)
    {
        // Check basic read permission
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Check if user can view appointments
        return $this->checkResourceAccess('appointment', 'view');
    }

    /**
     * Get arguments for the list endpoint
     */
    private function getListEndpointArgs()
    {
        return [
            'search' => [
                'description' => 'Search term to filter results',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'date' => $this->getCommonArgs()['date'],
            'date_from' => $this->getCommonArgs()['date'],
            'date_to' => $this->getCommonArgs()['date'],
            'clinic' => [
                'description' => 'Filter by clinic ID',
                'type' => 'integer',
                'validate_callback' => 'rest_validate_request_arg',
                'sanitize_callback' => 'absint',
            ],
            'doctor' => [
                'description' => 'Filter by doctor ID',
                'type' => 'integer',
                'validate_callback' => 'rest_validate_request_arg',
                'sanitize_callback' => 'absint',
            ],
            'patient' => [
                'description' => 'Filter by patient ID',
                'type' => 'integer',
                'validate_callback' => 'rest_validate_request_arg',
                'sanitize_callback' => 'absint',
            ],
            'status' => [
                'description' => 'Filter by appointment status (0: cancel, 1: Booked, 2: Check-In, 3: Check-Out, 4: Pending)',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    return in_array(intval($param), [0, 1, 2, 3, 4]);
                },
                'sanitize_callback' => 'absint',
            ],
            'orderby' => [
                'description' => 'Sort results by specified field',
                'type' => 'string',
                'validate_callback' => function ($param) {
                    $allowed_fields = [
                        'id',
                        'appointment_start_date',
                        'description',
                        'status',
                        'appointment_start_time',
                        'appointment_end_date',
                        'appointment_end_time',
                        'clinicId',
                        'doctorId',
                        'patientId',
                        'created_at'
                    ];
                    return in_array($param, $allowed_fields);
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'order' => [
                'description' => 'Sort direction (asc or desc)',
                'type' => 'string',
                'validate_callback' => function ($param) {
                    return in_array(strtolower($param), ['asc', 'desc']);
                },
                'sanitize_callback' => function ($param) {
                    return strtolower(sanitize_text_field($param));
                },
            ],
            'page' => [
                'description' => 'Current page of results',
                'type' => 'integer',
                'default' => 1,
                'validate_callback' => function ($param) {
                    return is_numeric($param);
                },
                'sanitize_callback' => 'absint',
            ],
            'perPage' => [
                'description' => 'Number of results per page',
                'type' => 'string',
                'default' => 10,
                'validate_callback' => function ($param) {
                    // Allow "all" as a valid option
                    if (strtolower($param) === 'all') {
                        return true;
                    }
                    // Allow numeric values
                    if (is_numeric($param) && intval($param) > 0) {
                        return true;
                    }
                    return false;
                },
                'sanitize_callback' => function ($param) {
                    return strtolower($param) === 'all' ? 'all' : absint($param);
                },
            ]
        ];
    }

    /**
     * Get arguments for the create endpoint
     */
    private function getCreateEndpointArgs()
    {
        $args = [
            'appointmentStartDate' => array_merge($this->getCommonArgs()['date'], ['required' => true]),
            'appointmentStartTime' => array_merge($this->getCommonArgs()['time'], ['required' => true]),
            'clinicId' => [
                'description' => 'Clinic ID',
                'type' => 'integer',
                'required' => (
                    !in_array(
                        $this->kcbase->getLoginUserRole(),
                        [
                            $this->kcbase->getClinicAdminRole(),
                            $this->kcbase->getReceptionistRole()
                        ]
                    ) && isKiviCareProActive()
                ),
                'default' => (
                    in_array(
                        $this->kcbase->getLoginUserRole(),
                        [
                            $this->kcbase->getClinicAdminRole(),
                            $this->kcbase->getReceptionistRole()
                        ]
                    ) && isKiviCareProActive()
                ) ? KCClinic::getClinicIdForCurrentUser() : 0,
                'validate_callback' => function ($param) {
                    return is_numeric($param);
                },
                'sanitize_callback' => function ($val) {
                    if (!isKiviCareProActive()) {
                        return absint(KCClinic::kcGetDefaultClinicId());
                    }
                    if (
                        in_array(
                            $this->kcbase->getLoginUserRole(),
                            [
                                $this->kcbase->getClinicAdminRole(),
                                $this->kcbase->getReceptionistRole()
                            ]
                        )
                    ) {
                        return absint(KCClinic::getClinicIdForCurrentUser());
                    }
                    return absint($val);
                },
            ],
            'doctorId' => [
                'description' => 'Doctor ID',
                'type' => 'integer',
                'required' => $this->kcbase->getLoginUserRole() !== $this->kcbase->getDoctorRole(),
                'default' => $this->kcbase->getLoginUserRole() !== $this->kcbase->getDoctorRole() ? 0 : get_current_user_id(),
                'validate_callback' => function ($param) {
                    return is_numeric($param) && $param > 0;
                },
                'sanitize_callback' => function ($param) {
                    if ($this->kcbase->getLoginUserRole() === $this->kcbase->getDoctorRole()) {
                        return absint(get_current_user_id());
                    }
                    return absint($param);
                },
            ],
            'serviceId' => $this->getCommonArgs()['serviceId'],
            'patientId' => [
                'description' => 'Patient ID',
                'type' => 'integer',
                'required' => $this->kcbase->getLoginUserRole() !== $this->kcbase->getPatientRole(),
                'default' => $this->kcbase->getLoginUserRole() !== $this->kcbase->getPatientRole() ? 0 : get_current_user_id(),
                'validate_callback' => function ($param) {
                    return is_numeric($param) && $param > 0;
                },
                'sanitize_callback' => function ($param, WP_REST_Request $request) {
                    if ($this->kcbase->getLoginUserRole() === $this->kcbase->getPatientRole()) {
                        return absint(get_current_user_id());
                    }
                    return absint($param);
                },
            ],
            'paymentGateway' => [
                'description' => 'Payment Gateway',
                'type' => 'string',
                'validate_callback' => function ($param, $request, $key) {
                    // Allow empty/null values - requirement check is done in controller based on grand_total
                    if (empty($param) || $param === 'null') {
                        return true;
                    }

                    if ($this->kcbase->getLoginUserRole() === $this->kcbase->getPatientRole()) {
                        $available_gateways = array_map(function ($gateway) {
                            return $gateway['id'];
                        }, KCPaymentGatewayFactory::get_available_gateways(true));
                        if (!in_array($param, $available_gateways) && !str_starts_with($param, 'knit_pay')) {
                            return new WP_Error('invalid_payment_gateway', __('Invalid payment method selected.', 'kivicare-clinic-management-system'));
                        }
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'description' => [
                'description' => 'Appointment description',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'description' => 'Appointment status (0: Cancelled, 1: Booked, 2: Pending, 3: Check-Out, 4: Check-In)',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    return in_array(intval($param), [0, 1, 2, 3, 4]);
                },
                'sanitize_callback' => 'absint',
            ],
            'page_id' => [
                'description' => 'Page ID for frontend appointment booking',
                'type' => 'integer',
                'required' => false,
                'validate_callback' => function ($param) {
                    return empty($param) || (is_numeric($param) && $param > 0);
                },
                'sanitize_callback' => 'absint',
            ],
        ];

        return apply_filters('kc_appointment_create_endpoint_args', $args);
    }

    /**
     * Get arguments for the update endpoint
     */
    private function getUpdateEndpointArgs()
    {
        $args = [
            'appointmentStartDate' => $this->getCommonArgs()['date'],
            'appointmentStartTime' => $this->getCommonArgs()['time'],
            'description' => [
                'description' => 'Appointment description',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'description' => 'Appointment status (0: Cancelled, 1: Booked, 2: Pending, 3: Check-Out, 4: Check-In)',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    return in_array(intval($param), [0, 1, 2, 3, 4]);
                },
                'sanitize_callback' => 'absint',
            ]
        ];

        return apply_filters('kc_appointment_update_endpoint_args', $args);
    }

    /**
     * Get arguments for the export endpoint
     *
     * @return array
     */
    private function getExportEndpointArgs()
    {
        return [
            'format' => [
                'description' => 'Export format (csv, xls, pdf)',
                'type' => 'string',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (!in_array($param, ['csv', 'xls', 'pdf'])) {
                        return new WP_Error('invalid_format', __('Format must be csv, xls, or pdf', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'search' => [
                'description' => 'Search term to filter results',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'date' => $this->getCommonArgs()['date'],
            'date_from' => [
                'description' => 'Filter appointments from this date (YYYY-MM-DD)',
                'type' => 'string',
                'validate_callback' => function ($param) {
                    if (!empty($param)) {
                        $date = DateTime::createFromFormat('Y-m-d', $param);
                        if (!$date || $date->format('Y-m-d') !== $param) {
                            return new WP_Error('invalid_date', __('Invalid date format. Use YYYY-MM-DD', 'kivicare-clinic-management-system'));
                        }
                        // Special logic: Date ≤ current date validation
                        $currentDate = new DateTime();
                        if ($date > $currentDate) {
                            return new WP_Error('future_date', __('Date cannot be in the future', 'kivicare-clinic-management-system'));
                        }
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'date_to' => [
                'description' => 'Filter appointments to this date (YYYY-MM-DD)',
                'type' => 'string',
                'validate_callback' => function ($param) {
                    if (!empty($param)) {
                        $date = DateTime::createFromFormat('Y-m-d', $param);
                        if (!$date || $date->format('Y-m-d') !== $param) {
                            return new WP_Error('invalid_date', __('Invalid date format. Use YYYY-MM-DD', 'kivicare-clinic-management-system'));
                        }
                        // Special logic: Date ≤ current date validation
                        $currentDate = new DateTime();
                        if ($date > $currentDate) {
                            return new WP_Error('future_date', __('Date cannot be in the future', 'kivicare-clinic-management-system'));
                        }
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'clinic' => [
                'description' => 'Filter by clinic ID',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && (!is_numeric($param) || $param <= 0)) {
                        return new WP_Error('invalid_clinic_id', __('Invalid clinic ID', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
            'doctor' => [
                'description' => 'Filter by doctor ID',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && (!is_numeric($param) || $param <= 0)) {
                        return new WP_Error('invalid_doctor_id', __('Invalid doctor ID', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
            'patient' => [
                'description' => 'Filter by patient ID',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && (!is_numeric($param) || $param <= 0)) {
                        return new WP_Error('invalid_patient_id', __('Invalid patient ID', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
            'status' => [
                'description' => 'Filter by appointment status (0: Cancelled, 1: Booked, 2: Pending, 3: Check-Out, 4: Check-In)',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && !in_array(intval($param), [0, 1, 2, 3, 4])) {
                        return new WP_Error('invalid_status', __('Status must be 0 (Cancelled), 1 (Booked), 2 (Pending), 3 (Check-Out), or 4 (Check-In)', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
            'timeFrame' => [
                'description' => 'Time frame filter (all, upcoming, past)',
                'type' => 'string',
                'validate_callback' => function ($param) {
                    if (!empty($param) && !in_array($param, ['all', 'upcoming', 'past'])) {
                        return new WP_Error('invalid_timeframe', __('Time frame must be all, upcoming, or past', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'page' => [
                'description' => 'Current page of results',
                'type' => 'integer',
                'default' => 1,
                'validate_callback' => function ($param) {
                    return is_numeric($param) && $param > 0;
                },
                'sanitize_callback' => 'absint',
            ],
            'perPage' => [
                'description' => 'Number of results per page',
                'type' => 'string',
                'default' => 10,
                'validate_callback' => function ($param) {
                    if (strtolower($param) === 'all') {
                        return true;
                    }
                    if (is_numeric($param) && intval($param) > 0) {
                        return true;
                    }
                    return false;
                },
                'sanitize_callback' => function ($param) {
                    return strtolower($param) === 'all' ? 'all' : absint($param);
                },
            ]
        ];
    }

    public function checkPermissionPaymentAccept($request)
    {
        $appointment_id = $request->get_param('appointment_id');
        // This endpoint is public for payment gateway callbacks
        $current_user_role = $this->kcbase->getLoginUserRole();

        $appointment = KCAppointment::find($appointment_id);
        if (!$appointment) {
            return false;
        }

        // If the user is a patient, they can only access their own appointments
        if ($current_user_role === $this->kcbase->getPatientRole()) {
            return $appointment->patientId === get_current_user_id();
        }

        if ($request->get_param('gateway') === 'woocommerce') {
            return true;
        }

        // For other roles, check if they have permission to view the appointment
        return $this->checkResourceAccess('appointment', 'view');
    }

    /**
     * Check if user has permission to access appointment endpoints
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkPermission($request)
    {
        $current_user_role = $this->kcbase->getLoginUserRole();

        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        $method = $request->get_method();
        $route = $request->get_route();

        // Check specific permissions based on HTTP method and route
        switch ($method) {
            case 'GET':
                // For status endpoint
                if (strpos($route, '/status') !== false) {
                    return $this->checkResourceAccess('appointment', 'view');
                }

                // For single appointment view
                if (isset($request['id'])) {
                    return $this->checkResourceAccess('appointment', 'view');
                }

                // For appointment list - check appointment_list capability
                return $this->checkResourceAccess('appointment', 'list');

            case 'POST':
                // For creating appointments
                return $this->checkResourceAccess('appointment', 'add');

            case 'PUT':
                // For status updates
                if (strpos($route, '/status') !== false) {
                    if ($current_user_role === $this->kcbase->getPatientRole()) {
                        return $this->checkResourceAccess('appointment', 'edit');
                    }
                    return $this->checkResourceAccess('patient_appointment_status', 'change');
                }

                // For appointment updates
                return $this->checkResourceAccess('appointment', 'edit');

            case 'DELETE':
                // For appointment deletion
                return $this->checkResourceAccess('appointment', 'delete');

            default:
                return false;
        }
    }

    /**
     * Check if user has permission to create an appointment
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkCreatePermission($request)
    {
        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Check appointment add permission
        return $this->checkResourceAccess('appointment', 'add');
    }

    /**
     * Get appointment summary with billing details
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getAppointmentSummary(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();

            // Validate doctor exists
            $doctor = KCDoctor::find($request->get_param('doctorId'));
            if (!$doctor) {
                return $this->response(
                    ['error' => 'Doctor not found'],
                    __('Doctor not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            // Validate clinic exists - use default clinic for non-pro version
            $clinicId = $request->get_param('clinicId');

            // For non-pro version, always use default clinic if clinic_id is 0 or null
            if (!isKiviCareProActive() && (empty($clinicId) || $clinicId == 0)) {
                $clinicId = KCClinic::kcGetDefaultClinicId();
            }

            $clinic = KCClinic::find($clinicId);
            if (!$clinic && !isKiviCareProActive()) {
                $defaultClinicId = KCClinic::kcGetDefaultClinicId();
                $clinic = KCClinic::find($defaultClinicId);
                $clinicId = $defaultClinicId;
            }

            if (!$clinic) {
                return $this->response(
                    ['error' => 'Clinic not found'],
                    __('Clinic not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            // Get services details
            $services = KCServiceDoctorMapping::table('dsm')
                ->leftJoin(KCService::class, 'dsm.service_id', '=', 'c.id', 'c')
                ->whereIn('dsm.id', $request->get_param('services'))->get();
            if ($services->isEmpty()) {
                return $this->response(
                    ['error' => 'No valid services found'],
                    __('No valid services found for the provided IDs', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            // Calculate totals
            $subtotal = 0;
            $serviceDetails = [];

            $services->each(function ($service) use (&$subtotal, &$serviceDetails) {
                $serviceDetails[] = [
                    'title' => $service->name,
                    'price' => $service->charges
                ];
                $subtotal += $service->charges;
            });

            $summaryData = [
                'services' => $serviceDetails,
                'subtotal' => $subtotal,
                'grand_total' => $subtotal
            ];

            do_action_ref_array('kc_appointment_summary_data', [&$summaryData, $params, $services]);

            return $this->response(
                $summaryData,
                __('Appointment summary retrieved successfully', 'kivicare-clinic-management-system'),
                true,
                200
            );
        } catch (\Exception $e) {
            // Log the error for debugging
            KCErrorLogger::instance()->error('Appointment Summary Error: ' . $e->getMessage());
            KCErrorLogger::instance()->error('Stack trace: ' . $e->getTraceAsString());

            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to generate appointment summary', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Get appointment slots for a specific date, doctor, and clinic
     * Supports both single date (Y-m-d) and month format (MM-YYYY or YYYY-MM)
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getAppointmentSlots(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Get and validate parameters
            $params = $request->get_params();

            $settings = AppointmentSetting::kcAppointmentRestrictionData();
            $is_same_day = in_array($settings['only_same_day_book'] ?? '', [true, 'on', 1, '1'], true);

            // 'today' keyword automatically sets time to 00:00:00
            $today = new DateTime('today', wp_timezone());

            $requested = new DateTime($params['date'], wp_timezone());

            $requested->setTime(0, 0, 0);

            if ($is_same_day) {
                if ($requested != $today) {
                    return $this->response(['error' => 'Only same-day booking is allowed.'], __('Only same-day booking is allowed.', 'kivicare-clinic-management-system'), false, 400);
                }
            }
            
            // Detect if date is in month format (MM-YYYY, M-YYYY, YYYY-MM, or YYYY-M)
            if (preg_match('/^(\d{1,4})-(\d{1,4})$/', $params['date'], $matches)) {
                $first = (int) $matches[1];
                $second = (int) $matches[2];

                // Determine format based on value ranges
                if ($first > 12 && $first <= 9999) {
                    // First part is year (YYYY-MM format)
                    $year = $first;
                    $month = $second;
                } elseif ($second > 12 && $second <= 9999) {
                    // Second part is year (MM-YYYY format)
                    $month = $first;
                    $year = $second;
                } else {
                    // Ambiguous or invalid - default to MM-YYYY if both are <= 12
                    $month = $first;
                    $year = $second;
                }

                // Validate month and year
                if ($month >= 1 && $month <= 12 && $year >= 1000 && $year <= 9999) {
                    // Handle month-wide slot retrieval
                    return $this->getMonthSlotAvailability($params, $month, $year);
                }

            } else {
                // Not a month format, treat as single date
                $month = null;
                $year = null;
            }

            $settings = (new \App\controllers\api\SettingsController\AppointmentSetting())->kcAppointmentRestrictionData();
            $is_same_day = in_array($settings['only_same_day_book'] ?? '', [true, 'on', 1, '1'], true);

            // 'today' keyword automatically sets time to 00:00:00
            $today = new DateTime('today', wp_timezone());

            $requested = new DateTime($params['date'] ,     wp_timezone());
            
            $requested->setTime(0, 0, 0); 

            if ($is_same_day) {
                if ($requested != $today) {
                    return $this->response(['error' => 'Only same-day booking is allowed.'], __('Only same-day booking is allowed.', 'kivicare-clinic-management-system'), false,   400);
                }
            }
            
            // Validate month and year
            if ($month >= 1 && $month <= 12 && $year >= 1000 && $year <= 9999) {
                // Handle month-wide slot retrieval
                return $this->getMonthSlotAvailability($params, $month, $year);
            }
        
            // Continue with single-date slot retrieval
            return $this->getSingleDateSlots($params);

        } catch (\Exception $e) {
            // Log the error for debugging
            KCErrorLogger::instance()->error('AppointmentSlotGenerator Error: ' . $e->getMessage());
            KCErrorLogger::instance()->error('Stack trace: ' . $e->getTraceAsString());

            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to generate appointment slots', 'kivicare-clinic-management-system'),
                false,
                200
            );
        }
    }

    /**
     * Get slot availability for entire month
     * 
     * @param array $params
     * @param int $month Month (1-12)
     * @param int $year Year (e.g., 2026)
     * @return \WP_REST_Response
     */
    private function getMonthSlotAvailability($params, $month, $year)
    {

        // Validate doctor and clinic
        $doctor = KCDoctor::find($params['doctor_id']);
        if (!$doctor) {
            return $this->response(
                ['error' => 'Doctor not found'],
                __('Doctor not found', 'kivicare-clinic-management-system'),
                false,
                404
            );
        }

        // Handle clinic ID
        if (!isKiviCareProActive() && (empty($params['clinic_id']) || $params['clinic_id'] == 0)) {
            $params['clinic_id'] = KCClinic::kcGetDefaultClinicId();
        }

        $clinic = KCClinic::find($params['clinic_id']);
        if (!$clinic && !isKiviCareProActive()) {
            $defaultClinicId = KCClinic::kcGetDefaultClinicId();
            $clinic = KCClinic::find($defaultClinicId);
            if ($clinic) {
                $params['clinic_id'] = $defaultClinicId;
            }
        }

        if (!$clinic) {
            return $this->response(
                ['error' => 'Clinic not found'],
                __('Clinic not found', 'kivicare-clinic-management-system'),
                false,
                404
            );
        }

        if (empty($params['service_id'])) {
            return $this->response(
                ['error' => 'service is required'],
                __('Missing service data', 'kivicare-clinic-management-system'),
                false,
                400
            );
        }

        // Get booking restrictions
        $settings = (new \App\controllers\api\SettingsController\AppointmentSetting())->kcAppointmentRestrictionData();
        $is_same_day = in_array($settings['only_same_day_book'] ?? '', [true, 'on', 1, '1'], true);
        $pre = (int) ($settings['pre_book'] ?? 0);
        $post = (int) ($settings['post_book'] ?? 365);

        $today = new DateTime('today');
        $start = (clone $today)->modify("+{$pre} days");
        $end = (clone $today)->modify("+{$post} days");

        // Calculate days in month
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $monthSlots = [];

        // Loop through each day in the month
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $currentDate = new DateTime($dateString);
            $currentDate->setTime(0, 0, 0);

            // Skip if outside booking window
            if ($is_same_day && $currentDate != $today) {
                continue;
            }

            if (!$is_same_day && ($currentDate < $start || $currentDate > $end)) {
                continue;
            }

            // Skip past dates
            if ($currentDate < $today) {
                continue;
            }

            // Prepare params for this specific date
            $dateParams = $params;
            $dateParams['date'] = $dateString;
            $dateParams['only_available_slots'] = false;

            try {
                // Prepare data for slot generation
                $slotData = KCAppointmentDataService::prepareSlotGenerationData($dateParams);

                // Create the slot generator
                $slotGenerator = new KCTimeSlotService($slotData);

                // Generate slots
                $slots = $slotGenerator->generateSlots();

                // Count total and available slots
                // Note: Booked appointment times are already excluded by splitSessionIntoChunks()
                // The 'available' flag indicates if the slot is in the future (not in the past)
                $totalSlots = 0;
                $availableSlots = 0;

                foreach ($slots as $session) {
                    foreach ($session as $slot) {
                        $totalSlots++;
                        // Only count future slots as available
                        if (isset($slot['available']) && $slot['available'] === true) {
                            $availableSlots++;
                        }
                    }
                }

                // Only add to response if there are slots
                if ($totalSlots > 0) {
                    $monthSlots[$dateString] = [
                        'total_count' => $totalSlots,
                        'available_count' => $availableSlots,
                        'date' => $dateString,
                        'day_of_week' => (int) $currentDate->format('w')
                    ];
                }
            } catch (\Exception $e) {
                // Skip this date if it fails
                KCErrorLogger::instance()->error("Failed to generate slots for {$dateString}: " . $e->getMessage());
                continue;
            }
        }

        return $this->response(
            [
                'month' => sprintf('%02d-%04d', $month, $year),
                'doctor_id' => (int) $params['doctor_id'],
                'clinic_id' => (int) $params['clinic_id'],
                'doctor_name' => $doctor->display_name ?? 'Unknown Doctor',
                'clinic_name' => $clinic->name ?? 'Unknown Clinic',
                'dates' => $monthSlots,
                'total_dates_with_slots' => count($monthSlots)
            ],
            __('Month slot availability retrieved successfully', 'kivicare-clinic-management-system'),
            true,
            200
        );
    }

    /**
     * Get slots for a single date
     * 
     * @param array $params
     * @return \WP_REST_Response
     */
    private function getSingleDateSlots($params)
    {
        $settings = (new \App\controllers\api\SettingsController\AppointmentSetting())->kcAppointmentRestrictionData();
        $is_same_day = in_array($settings['only_same_day_book'] ?? '', [true, 'on', 1, '1'], true);

        // 'today' keyword automatically sets time to 00:00:00
        $today = new DateTime('today');
        $requested = new DateTime($params['date']);
        $requested->setTime(0, 0, 0);

        if ($is_same_day) {
            if ($requested != $today) {
                return $this->response(['error' => 'Only same-day booking is allowed.'], __('Only same-day booking is allowed.', 'kivicare-clinic-management-system'), false, 400);
            }
        } else {
            $pre = (int) ($settings['pre_book'] ?? 0);
            $post = (int) ($settings['post_book'] ?? 365);

            $start = (clone $today)->modify("+{$pre} days");
            $end = (clone $today)->modify("+{$post} days");

            if ($requested < $start || $requested > $end) {
                return $this->response(['error' => 'Booking is not available for the selected date.'], __('Booking is not available for the selected date.', 'kivicare-clinic-management-system'), false, 400);
            }
        }

        // Validate doctor and clinic exist and are active
        $doctor = KCDoctor::find($params['doctor_id']);
        if (!$doctor) {
            return $this->response(
                ['error' => 'Doctor not found'],
                __('Doctor not found', 'kivicare-clinic-management-system'),
                false,
                404
            );
        }

        // For non-pro version, always use default clinic if clinic_id is 0 or null
        if (!isKiviCareProActive() && (empty($params['clinic_id']) || $params['clinic_id'] == 0)) {
            $params['clinic_id'] = KCClinic::kcGetDefaultClinicId();
        }

        // For non-pro version, use default clinic if not found
        $clinic = KCClinic::find($params['clinic_id']);
        if (!$clinic && !isKiviCareProActive()) {
            $defaultClinicId = KCClinic::kcGetDefaultClinicId();
            $clinic = KCClinic::find($defaultClinicId);
            if ($clinic) {
                $params['clinic_id'] = $defaultClinicId;
            }
        }

        if (!$clinic) {
            return $this->response(
                ['error' => 'Clinic not found'],
                __('Clinic not found', 'kivicare-clinic-management-system'),
                false,
                404
            );
        }

        if (empty($params['service_id'])) {
            return $this->response(
                ['error' => 'service is required'],
                __('Missing service data', 'kivicare-clinic-management-system'),
                false,
                400
            );
        }

        // Prepare data for slot generation using the data service
        $slotData = KCAppointmentDataService::prepareSlotGenerationData($params);

        // Create the slot generator with prepared data
        $slotGenerator = new KCTimeSlotService($slotData);

        // Generate slots
        $slots = $slotGenerator->generateSlots();

        // Get additional metadata
        $debugInfo = $slotGenerator->getDebugInfo();
        // Format response data
        $responseData = [
            'date' => $params['date'],
            'doctor_id' => (int) $params['doctor_id'],
            'clinic_id' => (int) $params['clinic_id'],
            'doctor_name' => $doctor->display_name ?? 'Unknown Doctor',
            'clinic_name' => $clinic->name ?? 'Unknown Clinic',
            'total_sessions' => count($slots),
            'total_slots' => array_sum(array_map('count', $slots)),
            'service_duration_minutes' => $debugInfo['service_duration_sum'],
            'slots_by_session' => $slots,
            'metadata' => [
                'weekday' => $debugInfo['weekday'],
                'sessions_found' => $debugInfo['sessions_count'],
                'existing_appointments' => $debugInfo['appointments_count']
            ]
        ];

        // If no slots found, provide helpful message
        if (empty($slots) || array_sum(array_map('count', $slots)) === 0) {
            return $this->response(
                $responseData,
                __('No available slots found for the selected date and doctor', 'kivicare-clinic-management-system'),
                true,
                200
            );
        }

        return $this->response(
            $responseData,
            __('Appointment slots retrieved successfully', 'kivicare-clinic-management-system'),
            true,
            200
        );
    }

    /**
     * Get all appointments
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getAppointments(WP_REST_Request $request): WP_REST_Response
    {
        // Process request parameters
        $params = $request->get_params();

        // Build the base query with joins using our new table() method for better readability
        $query = KCAppointment::table('a')
            ->select([
                "a.*",
                'c.name as clinic_name',
                'c.email as clinic_email',
                'c.profile_image as clinic_profile_image',
                'c.address as clinic_address',
                'p.ID as patient_user_id',
                'd.ID as doctor_user_id',
                'ppi.meta_value as patient_profile_image',
                'dpi.meta_value as doctor_profile_image',
                'pam.payment_mode as payment_mode',
                'pam.payment_status as payment_status',
                'pam.amount as payment_amount',
                'pam.currency as payment_currency',
                'pe.id as encounter_id',
                'pe.status as encounter_status'
            ])
            ->leftJoin(KCClinic::class, "a.clinic_id", '=', 'c.id', 'c')
            ->leftJoin(KCDoctor::class, "a.doctor_id", '=', 'd.id', 'd')
            ->leftJoin(KCPatient::class, "a.patient_id", '=', 'p.id', 'p')
            ->leftJoin(KCUserMeta::class, function ($join) {
                $join->on('p.ID', '=', 'ppi.user_id')
                    ->onRaw("ppi.meta_key = 'patient_profile_image'");
            }, null, null, 'ppi')
            ->leftJoin(KCUserMeta::class, function ($join) {
                $join->on('d.ID', '=', 'dpi.user_id')
                    ->onRaw("dpi.meta_key = 'doctor_profile_image'");
            }, null, null, 'dpi')
            ->leftJoin(KCPaymentsAppointmentMapping::class, "a.id", '=', 'pam.appointment_id', 'pam')
            ->leftJoin(KCPatientEncounter::class, "a.id", '=', 'pe.appointment_id', 'pe')
            ->leftJoin(KCAppointmentServiceMapping::class, "a.id", '=', 'asm.appointment_id', 'asm')
            ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
            ->leftJoin(KCServiceDoctorMapping::class, 's.id', '=', 'dsm.service_id', 'dsm');
        // Apply role-based filtering
        $current_user_id = get_current_user_id();
        $current_user_role = $this->kcbase->getLoginUserRole();

        // Doctor role filtering
        if ($current_user_role === $this->kcbase->getDoctorRole()) {
            $query->where("a.doctor_id", '=', $current_user_id);
        } else if (!empty($params['doctor'])) {
            // If not a doctor but doctor filter is provided, apply it
            $query->where("a.doctor_id", '=', (int) $params['doctor']);
        }

        // Patient role filtering
        if ($current_user_role === $this->kcbase->getPatientRole()) {
            $query->where("a.patient_id", '=', $current_user_id);
        } else if (!empty($params['patient'])) {
            // If not a patient but patient filter is provided, apply it
            $query->where("a.patient_id", '=', (int) $params['patient']);
        }


        // Clinic-based role filtering (Pro version)
        if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
            switch ($current_user_role) {
                case $this->kcbase->getClinicAdminRole():
                    $clinic_id = KCClinic::getClinicIdOfClinicAdmin();
                    if ($clinic_id) {
                        $query->where("a.clinic_id", '=', $clinic_id);
                    }
                    break;

                case $this->kcbase->getReceptionistRole():
                    $clinic_id = KCClinic::getClinicIdOfReceptionist();
                    if ($clinic_id) {
                        $query->where("a.clinic_id", '=', $clinic_id);
                    }
                    break;
            }
        }


        // Apply filters
        if (!empty($params['search'])) {
            $searchTerm = $params['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where("a.description", 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere("p.display_name", 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere("p.user_email", 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere("d.display_name", 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere("d.user_email", 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere("c.name", 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere("s.name", 'LIKE', '%' . $searchTerm . '%');
            });
        }

        if (!empty($params['date'])) {
            $date = trim(sanitize_text_field($params['date']));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $dateTime = DateTime::createFromFormat('Y-m-d', $date);
                if ($dateTime && $dateTime->format('Y-m-d') === $date) {
                    $query->where("a.appointment_start_date", '=', $date);
                }
            }
        }

        if (!empty($params['date_from'])) {
            $dateFrom = trim(sanitize_text_field($params['date_from']));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
                $dateTime = DateTime::createFromFormat('Y-m-d', $dateFrom);
                if ($dateTime && $dateTime->format('Y-m-d') === $dateFrom) {
                    $query->where("a.appointment_start_date", '>=', $dateFrom);
                }
            }
        }

        if (!empty($params['date_to'])) {
            $dateTo = trim(sanitize_text_field($params['date_to']));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                $dateTime = DateTime::createFromFormat('Y-m-d', $dateTo);
                if ($dateTime && $dateTime->format('Y-m-d') === $dateTo) {
                    $query->where("a.appointment_start_date", '<=', $dateTo);
                }
            }
        }

        // Apply timeFrame filter (all, upcoming, past)
        $timeFrame = strtolower($params['timeFrame'] ?? 'all');
        if ($timeFrame !== 'all') {
            // Get current date and time in MySQL format
            $currentDate = current_time('Y-m-d');
            $currentTime = current_time('H:i:s');

            if ($timeFrame === 'upcoming') {
                // Upcoming: Appointments that are today with time in future, or any future date
                $query->where(function ($q) use ($currentDate, $currentTime) {
                    $q->where(function ($innerQ) use ($currentDate, $currentTime) {
                        // Today's appointments with time later than now
                        $innerQ->where('a.appointment_start_date', '=', $currentDate)
                            ->where('a.appointment_start_time', '>', $currentTime);
                    })->orWhere('a.appointment_start_date', '>', $currentDate);
                    $q->where('a.status', '=', KCAppointment::STATUS_BOOKED); // Exclude cancelled appointments
                });
            } elseif ($timeFrame === 'past') {
                // Past: Appointments with dates before today, or today with time already passed
                $query->where(function ($q) use ($currentDate, $currentTime) {
                    $q->where('a.appointment_start_date', '<', $currentDate)
                        ->orWhere(function ($innerQ) use ($currentDate, $currentTime) {
                            $innerQ->where('a.appointment_start_date', '=', $currentDate)
                                ->where('a.appointment_start_time', '<', $currentTime);
                        });
                });
            }
        }

        // Handle clinic filter - support both 'clinic' and 'clinic_id' parameters
        $clinicId = null;
        if (!empty($params['clinic_id'])) {
            $clinicId = (int) $params['clinic_id'];
        } elseif (!empty($params['clinic'])) {
            $clinicId = (int) $params['clinic'];
        }

        if ($clinicId !== null) {
            // Validate that the clinic exists
            $clinic = KCClinic::find($clinicId);
            if (!$clinic) {
                // Clinic doesn't exist, return empty result
                $perPage = isset($params['perPage']) && (int) $params['perPage'] > 0 ? (int) $params['perPage'] : 10;
                $page = isset($params['page']) && (int) $params['page'] > 0 ? (int) $params['page'] : 1;

                return $this->response(
                    [
                        'appointments' => [],
                        'pagination' => [
                            'total' => 0,
                            'perPage' => $perPage,
                            'currentPage' => $page,
                            'lastPage' => 1,
                        ],
                    ],
                    __('Clinic not found', 'kivicare-clinic-management-system'),
                    true,
                    200
                );
            }
            // Apply clinic filter
            $query->where("a.clinic_id", '=', $clinicId);
        }

        if (!empty($params['patient'])) {
            $query->where("a.patient_id", '=', (int) $params['patient']);
        }

        if (!empty($params['doctor'])) {
            $query->where("a.doctor_id", '=', (int) $params['doctor']);
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where("a.status", '=', $params['status']);
        }

        // Apply sorting if orderby parameter is provided
        if (!empty($params['orderby'])) {
            $orderby = $params['orderby'];
            $direction = !empty($params['order']) && strtolower($params['order']) === 'desc' ? 'DESC' : 'ASC';

            // Handle sorting by related fields
            switch ($orderby) {
                case 'patientId':
                    $query->orderBy('p.display_name', $direction);
                    break;
                case 'doctorId':
                    $query->orderBy('d.display_name', $direction);
                    break;
                case 'clinicId':
                    $query->orderBy('c.name', $direction);
                    break;
                case 'appointment_start_date':
                    $query->orderBy('a.appointment_start_date', $direction)
                        ->orderBy('a.appointment_start_time', $direction);
                    break;
                default:
                    // Handle direct appointment table fields
                    $columnMapping = [
                        'appointment_start_date' => 'a.appointment_start_date',
                        'appointment_start_time' => 'a.appointment_start_time',
                        'appointment_end_date' => 'a.appointment_end_date',
                        'appointment_end_time' => 'a.appointment_end_time',
                        'clinicId' => 'a.clinic_id',
                        'doctorId' => 'a.doctor_id',
                        'patientId' => 'a.patient_id',
                        'created_at' => 'a.created_at',
                        'id' => 'a.id',
                        'description' => 'a.description',
                        'status' => 'a.status'
                    ];

                    $column = $columnMapping[$orderby] ?? "a.{$orderby}";
                    $query->orderBy($column, $direction);
                    break;
            }
        } else {
            // Default sorting by id descending if no sort specified
            $query->orderBy("a.id", 'DESC');
        }

        // Get total count for pagination
        $totalQuery = clone $query;
        $total = $totalQuery->countDistinct('a.id');
        // Apply pagination with validation
        $perPageParam = isset($params['perPage']) ? $params['perPage'] : 10;
        // Handle "all" option for perPage
        $showAll = (strtolower($perPageParam) === 'all');
        $perPage = $showAll ? null : (int) $perPageParam;

        if (!$showAll && $perPage <= 0) {
            $perPage = 10;
        }

        $page = isset($params['page']) ? (int) $params['page'] : 1;
        if ($page <= 0) {
            $page = 1;
        }

        if ($showAll) {
            $perPage = $total > 0 ? $total : 1;
            $page = 1;
        }

        $totalPages = $perPage > 0 ? ceil($total / $perPage) : 1;
        $offset = ($page - 1) * $perPage;

        if (!$showAll) {
            $query->limit($perPage)->offset($offset);
        }

        do_action('kc_appointments_list_query', $query, $params);
        $query->groupBy('a.id');
        // Get paginated results
        $appointments = $query->get();

        // Get clinic currency settings once before the loop
        $clinicCurrencySetting = KCClinic::getClinicCurrencyPrefixAndPostfix();
        $prefix = $clinicCurrencySetting['prefix'] ?? '';
        $postfix = $clinicCurrencySetting['postfix'] ?? '';
        $decimal_places = $clinicCurrencySetting['decimal_places'] ?? 2;

        // Prepare the appointment data
        $appointmentsData = [];
        foreach ($appointments as $appointment) {

            // Get doctor and patient WordPress user data 
            $doctorUser = $appointment->doctor_user_id ? get_userdata($appointment->doctor_user_id) : null;
            $patientUser = $appointment->patient_user_id ? get_userdata($appointment->patient_user_id) : null;

            // Calculate Tax data BEFORE formatting the string
            $taxData = [];
            $taxAmount = 0;
            if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
                $taxData = apply_filters('kivicare_get_tax_data', $appointment->id);
                $taxAmount = isset($taxData['total_tax']) ? (float) $taxData['total_tax'] : 0;
            }


            $paymentStatus = '';
            $paymentAmount = $appointment->payment_amount;
            $paymentCurrency = $appointment->payment_currency;
            $bill = null;
            if (!empty($appointment->encounter_id)) {
                $bill = KCBill::query()
                    ->where('encounter_id', $appointment->encounter_id)
                    ->first();
            }

            if ($bill) {
                if (!empty($bill->paymentStatus)) {
                    $paymentStatus = $bill->paymentStatus;
                } else {
                    $paymentStatus = KCPaymentsAppointmentMapping::getPaymentStatusByAppointmentId($appointment->id);
                }

                if (isset($bill->actualAmount) && $bill->actualAmount !== null) {
                    $paymentAmount = $bill->actualAmount;
                } elseif (isset($bill->totalAmount) && $bill->totalAmount !== null) {
                    $paymentAmount = $bill->totalAmount;
                }

                if (isset($bill->currency) && $bill->currency !== null && $bill->currency !== '') {
                    $paymentCurrency = $bill->currency;
                }
            } else {
                $paymentStatus = KCPaymentsAppointmentMapping::getPaymentStatusByAppointmentId($appointment->id);
            }

            //compare corrent date and appointment start date 
            $currentDate = kcGetFormatedDate(current_time('Y-m-d'));
            $appointmentStartDate = kcGetFormatedDate($appointment->appointmentStartDate);
            $isToday = $currentDate === $appointmentStartDate;

            // Check if appointment is in the past
            $appointmentDateTime = strtotime($appointment->appointmentStartDate . ' ' . $appointment->appointmentStartTime);
            $currentDateTime = current_time('timestamp');
            $isPast = $appointmentDateTime <= $currentDateTime;

            // Format start and end datetime as local ISO strings
            $startDateTime = new DateTime($appointment->appointmentStartDate . ' ' . $appointment->appointmentStartTime);
            $endDateTime = new DateTime($appointment->appointmentEndDate . ' ' . $appointment->appointmentEndTime);

            $servicesQuery = KCAppointmentServiceMapping::table('asm')
                ->select([
                    'asm.appointment_id',
                    's.name as service_name',
                    'dsm.charges as service_price',
                    'dsm.image as service_image'
                ])
                ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
                ->leftJoin(KCServiceDoctorMapping::class, 's.id', '=', 'dsm.service_id', 'dsm')
                ->where('asm.appointment_id', '=', $appointment->id)
                ->where('dsm.doctor_id', '=', $appointment->doctorId)
                ->where('dsm.clinic_id', '=', $appointment->clinicId)
                ->get();
            $services = [];
            foreach ($servicesQuery as $service) {
                if (!isset($services[$service->appointmentId])) {
                    $services[$service->appointmentId] = [
                        'names' => [],
                        'total_charges' => 0,
                        'images' => []
                    ];
                }
                $services[$service->appointmentId]['names'][] = $service->service_name;
                $services[$service->appointmentId]['total_charges'] += (float) $service->service_price;
                if ($service->service_image) {
                    $services[$service->appointmentId]['images'][] = $service->service_image;
                }
            }
            $total_charges = $services[$appointment->id]['total_charges'] ?? 0;
            // Calculate final total including tax
            $final_amount = $total_charges + $taxAmount;

            // Format appointment data
            $appointmentData = [
                'id' => $appointment->id,
                'start' => $startDateTime->format('Y-m-d\TH:i:s'),
                'end' => $endDateTime->format('Y-m-d\TH:i:s'),
                'appointmentStartDateWF' => ($appointment->appointmentStartDate),
                'appointmentStartDate' => kcGetFormatedDate($appointment->appointmentStartDate),
                'appointmentStartTime' => kcGetFormatedTime($appointment->appointmentStartTime),
                'appointmentStartTimeWF' => ($appointment->appointmentStartTime),
                'appointmentEndDate' => kcGetFormatedDate($appointment->appointmentEndDate),
                'appointmentEndDateWF' => ($appointment->appointmentEndDate),
                'appointmentEndTime' => kcGetFormatedTime($appointment->appointmentEndTime),
                'clinicId' => (int) $appointment->clinicId,
                'clinicName' => $appointment->clinic_name, // Direct access to joined column
                'clinicEmail' => $appointment->clinic_email, // Direct access to joined column
                'clinicAddress' => $appointment->clinic_address, // Direct access to joined column
                'clinic_image_url' => $appointment->clinic_profile_image ? wp_get_attachment_url((int) $appointment->clinic_profile_image) : '',
                'doctorId' => (int) $appointment->doctorId,
                'doctorName' => $doctorUser ? $doctorUser->display_name : null,
                'doctorEmail' => $doctorUser ? $doctorUser->user_email : null,
                'doctor_image_url' => $appointment->doctor_profile_image ? wp_get_attachment_url((int) $appointment->doctor_profile_image) : '',
                'patientId' => (int) $appointment->patientId,
                'patientName' => $patientUser ? $patientUser->display_name : null,
                'patientEmail' => $patientUser ? $patientUser->user_email : null,
                'patient_image_url' => $appointment->patient_profile_image ? wp_get_attachment_url((int) $appointment->patient_profile_image) : '',
                'description' => $appointment->description,
                'services' => isset($services[$appointment->id]) ? implode(', ', $services[$appointment->id]['names']) : '',
                'service_image_url' => (isset($services[$appointment->id]) && !empty($services[$appointment->id]['images'])) ? wp_get_attachment_url($services[$appointment->id]['images'][0]) : '',
                'charges' => $prefix . number_format((float) $final_amount, $decimal_places) . $postfix,
                'payment' => [
                    'mode' => KCPaymentsAppointmentMapping::getPaymentModeByAppointmentId($appointment->id),
                    'status' => $paymentStatus,
                    'amount' => $paymentAmount,
                    'currency' => $paymentCurrency
                ],
                'status' => (int) $appointment->status,
                'createdAt' => $appointment->createdAt,
                'encounterId' => !empty($appointment->encounter_id) ? $appointment->encounter_id : 0,
                'encounterStatus' => !empty($appointment->encounter_status) ? $appointment->encounter_status : '',
                'isToday' => $isToday,
                'isPast' => $isPast,
                'taxData' => $taxData,
                'video_consultation' => apply_filters('kc_is_video_consultation', false, absint($appointment->id))
            ];
            if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
                $appointmentData['taxData'] = apply_filters('kivicare_get_tax_data', $appointment->id);
            }

            $appointmentsData[] = apply_filters('kc_appointment_list_item', $appointmentData, $appointment);
        }

        // Return the formatted data with pagination
        $data = [
            'appointments' => $appointmentsData,
            'pagination' => [
                'total' => $total,
                'perPage' => $perPage,
                'currentPage' => $page,
                'lastPage' => $totalPages,
            ],
        ];

        return $this->response($data, 'Appointments retrieved successfully');
    }

    /**
     * Get single appointment by ID
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getAppointment($request)
    {
        $id = $request->get_param('id');

        try {
            // Build comprehensive query with all related data for editing
            $appointment = KCAppointment::table('a')
                ->select([
                    'a.*',
                    'c.name as clinic_name',
                    'c.email as clinic_email',
                    'c.telephone_no as mobile_no',
                    'c.profile_image as clinic_profile_image',
                    'p.ID',
                    'p.display_name as patient_name',
                    'p.user_email as patient_email',
                    'd.ID',
                    'd.display_name as doctor_name',
                    'd.user_email as doctor_email',
                    "pbd.meta_value as patient_basic_data",
                    "ppi.meta_value as patient_profile_image",
                    "dbd.meta_value as doctor_basic_data",
                    "dpi.meta_value as doctor_profile_image",
                    "s.name as service_name"
                ])
                ->leftJoin(KCDoctor::class, "a.doctor_id", '=', 'd.id', 'd')
                ->leftJoin(KCPatient::class, "a.patient_id", '=', 'p.id', 'p')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'pbd.user_id')
                        ->onRaw("pbd.meta_key = 'basic_data'");
                }, null, null, 'pbd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'ppi.user_id')
                        ->onRaw("ppi.meta_key = 'patient_profile_image'");
                }, null, null, 'ppi')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'dbd.user_id')
                        ->onRaw("dbd.meta_key = 'basic_data'");
                }, null, null, 'dbd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'dpi.user_id')
                        ->onRaw("dpi.meta_key = 'doctor_profile_image'");
                }, null, null, 'dpi')
                ->leftJoin(KCClinic::class, "a.clinic_id", '=', 'c.id', 'c')
                ->leftJoin(KCAppointmentServiceMapping::class, "a.id", '=', 'asm.appointment_id', 'asm')
                ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
                ->where('a.id', '=', $id)
                ->first();

            if (!$appointment) {
                return $this->response(
                    ['error' => 'Appointment not found'],
                    __('Appointment not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            $encounter = KCPatientEncounter::table('pe')
                ->select([
                    'pe.id',
                    'pe.status as encounter_status',
                    'pe.description as encounter_description'
                ])
                ->where('pe.appointment_id', '=', $id)
                ->first();

            $patientBasicData = [];
            if (!empty($appointment->patient_basic_data)) {
                $patientBasicData = json_decode($appointment->patient_basic_data, true);
            }

            $doctorBasicData = [];
            if (!empty($appointment->doctor_basic_data)) {
                $doctorBasicData = json_decode($appointment->doctor_basic_data, true);
            }

            // Get All Services with detailed information
            $servicesData = KCAppointment::table('a')
                ->select([
                    'a.id',
                    's.name as service_name',
                    's.id as service_id',           // actual KCService ID
                    'dsm.id as mapping_id',         // dsm.id = what's stored in asm.serviceId
                    'dsm.image as service_image',
                    'dsm.charges'
                ])
                ->leftJoin(KCAppointmentServiceMapping::class, 'a.id', '=', 'asm.appointment_id', 'asm')
                ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
                ->leftJoin(KCServiceDoctorMapping::class, 'dsm.service_id', '=', 's.id', 'dsm')
                ->where('a.id', '=', $appointment->id)
                ->where('dsm.doctor_id', '=', $appointment->doctorId)
                ->where('dsm.clinic_id', '=', $appointment->clinicId)
                ->get();

            $service_array = $service_list = [];
            $service_charges = 0.00;

            foreach ($servicesData as $service) {
                $service_array[] = $service->service_name;
                $service_list[] = [
                    'id' => $service->mapping_id,
                    'title' => $service->service_name,
                    'service_image_url' => $service->service_image ? wp_get_attachment_url((int) $service->service_image) : '',
                    'price' => round((float) $service->charges, 3)
                ];
                $service_charges += $service->charges;
            }

            $taxData = [];
            if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
                $taxData = apply_filters('kivicare_get_tax_data', $appointment->id);
            }

            // Get service IDs array for form population
            $serviceIds = $servicesData->pluck('mapping_id')->toArray();

            $appointmentsData = [
                'id' => $appointment->id,
                'appointmentStartDate' => $appointment->appointmentStartDate,
                'appointmentFormatedStartDate' => kcGetFormatedDate($appointment->appointmentStartDate),
                'appointmentStartTime' => $appointment->appointmentStartTime,
                'appointmentFormatedStartTime' => kcGetFormatedTime($appointment->appointmentStartTime),
                'appointmentEndDate' => $appointment->appointmentEndDate,
                'appointmentEndTime' => $appointment->appointmentEndTime,
                'patientId' => $appointment->patientId,
                'patientName' => $appointment->patient_name,
                'patientEmail' => $appointment->patient_email,
                'patientMobileNo' => !empty($patientBasicData['mobile_number']) ? $patientBasicData['mobile_number'] : '',
                'doctorId' => $appointment->doctorId,
                'doctorName' => $appointment->doctor_name,
                'doctorEmail' => $appointment->doctor_email,
                'doctorMobileNo' => !empty($doctorBasicData['mobile_number']) ? $doctorBasicData['mobile_number'] : '',
                'clinicId' => $appointment->clinicId,
                'clinicName' => $appointment->clinic_name,
                'clinicEmail' => $appointment->clinic_email,
                'clinicMobileNo' => !empty($appointment->mobile_no) ? $appointment->mobile_no : '',
                'description' => $appointment->description,
                'visitType' => $appointment->visitType ?? '',
                'status' => $appointment->status,
                'createdAt' => $appointment->createdAt,
                'patient_image_url' => $appointment->patient_profile_image ? wp_get_attachment_url((int) $appointment->patient_profile_image) : '',
                'doctor_image_url' => $appointment->doctor_profile_image ? wp_get_attachment_url((int) $appointment->doctor_profile_image) : '',
                'clinic_image_url' => $appointment->clinic_profile_image ? wp_get_attachment_url((int) $appointment->clinic_profile_image) : '',
                'serviceName' => implode(', ', array_column($service_list, 'title')),
                'paymentMode' => KCPaymentsAppointmentMapping::getPaymentModeByAppointmentId($appointment->id),
                'appointmentDateTime' => $appointment->appointmentStartDate . '/' . $appointment->appointmentStartTime,
                'encounterId' => $encounter?->id,
                'encounterStatus' => $encounter?->encounter_status,
                'encounterDescription' => $encounter?->encounter_description,
                'subtotal' => $service_charges,
                'service_list' => $service_list,
                'serviceIds' => $serviceIds, // Array of service mapping IDs for form
                'taxItems' => $taxData,
                'video_consultation' => apply_filters('kc_is_video_consultation', false, absint($appointment->id))
            ];

            if ($appointment->appointmentReport) {
                $reportIds = json_decode($appointment->appointmentReport, true);
                if (is_array($reportIds)) {
                    $reports = [];
                    foreach ($reportIds as $id) {
                        $url = wp_get_attachment_url((int) $id);
                        $filename = get_the_title($id);
                        $reports[] = [
                            'id' => $id,
                            'url' => $url,
                            'filename' => $filename
                        ];
                    }
                    $appointmentsData['appointmentReport'] = $reports;
                }
            }

            $appointmentsData = apply_filters('kc_appointment_data', $appointmentsData, $appointment->id);

            return $this->response($appointmentsData, __('Appointment retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve appointment', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Get appointment details by ID
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getAppointmentDetails(WP_REST_Request $request): WP_REST_Response
    {
        try {

            $params = $request->get_params();
            $id = $request->get_param('id');

            // Build the base query for appointments
            $appointment = KCAppointment::table('a')
                ->select([
                    'a.*',
                    'c.name as clinic_name',
                    'c.email as clinic_email',
                    'c.telephone_no as mobile_no',
                    'c.profile_image as clinic_profile_image',
                    'p.ID',
                    'p.display_name as patient_name',
                    'p.user_email as patient_email',
                    'd.ID',
                    'd.display_name as doctor_name',
                    'd.user_email as doctor_email',
                    "pbd.meta_value as patient_basic_data",
                    "ppi.meta_value as patient_profile_image",
                    "dbd.meta_value as doctor_basic_data",
                    "dpi.meta_value as doctor_profile_image",
                    "s.name as service_name"
                ])
                ->leftJoin(KCDoctor::class, "a.doctor_id", '=', 'd.id', 'd')
                ->leftJoin(KCPatient::class, "a.patient_id", '=', 'p.id', 'p')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'pbd.user_id')
                        ->onRaw("pbd.meta_key = 'basic_data'");
                }, null, null, 'pbd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'ppi.user_id')
                        ->onRaw("ppi.meta_key = 'patient_profile_image'");
                }, null, null, 'ppi')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'dbd.user_id')
                        ->onRaw("dbd.meta_key = 'basic_data'");
                }, null, null, 'dbd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'dpi.user_id')
                        ->onRaw("dpi.meta_key = 'doctor_profile_image'");
                }, null, null, 'dpi')
                ->leftJoin(KCClinic::class, "a.clinic_id", '=', 'c.id', 'c')
                ->leftJoin(KCAppointmentServiceMapping::class, "a.id", '=', 'asm.appointment_id', 'asm')
                ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
                ->where('a.id', '=', $id)
                ->first();

            $encounter = KCPatientEncounter::table('pe')
                ->select([
                    'pe.id',
                    'pe.status as encounter_status',
                    'pe.description as encounter_description'
                ])
                ->where('pe.appointment_id', '=', $id)
                ->first();

            $patientBasicData = [];
            if (!empty($appointment->patient_basic_data)) {
                $patientBasicData = json_decode($appointment->patient_basic_data, true);
            }

            $doctorBasicData = [];
            if (!empty($appointment->doctor_basic_data)) {
                $doctorBasicData = json_decode($appointment->doctor_basic_data, true);
            }

            // Get All Services
            $servicesData = KCAppointment::table('a')
                ->select([
                    'a.id',
                    's.name as service_name',
                    's.id as service_id',           // actual KCService ID
                    'dsm.id as mapping_id',         // dsm.id = what's stored in asm.serviceId
                    'dsm.image as service_image',
                    'dsm.charges',
                    'sd.label as service_category'
                ])
                ->leftJoin(KCAppointmentServiceMapping::class, 'a.id', '=', 'asm.appointment_id', 'asm')
                ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
                ->leftJoin(KCServiceDoctorMapping::class, 'dsm.service_id', '=', 's.id', 'dsm')
                ->leftJoin(KCStaticData::class, 's.type', '=', 'sd.value', 'sd')
                ->where('a.id', '=', $appointment->id)
                ->where('dsm.doctor_id', '=', $appointment->doctorId)
                ->where('dsm.clinic_id', '=', $appointment->clinicId)
                ->groupBy('s.id')
                ->get();

            $service_array = $service_list = [];
            $service_charges = 0.00;

            foreach ($servicesData as $service) {
                $service_array[] = $service->service_name;
                $service_list[] = [
                    'id' => $service->mapping_id,
                    'title' => $service->service_name,
                    'service_image_url' => $service->service_image ? wp_get_attachment_url((int) $service->service_image) : '',
                    'price' => round((float) $service->charges, 3),
                    'service_category' => $service->service_category ?? ''
                ];
                $service_charges += $service->charges;
            }

            $taxData = [];
            if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
                $taxData = apply_filters('kivicare_get_tax_data', $appointment->id);
            }

            $paymentMode = KCPaymentsAppointmentMapping::getPaymentModeByAppointmentId($appointment->id);

            $paymentStatus = '';
            $bill = null;
            if (!empty($encounter?->id)) {
                $bill = KCBill::query()
                    ->where('encounter_id', $encounter->id)
                    ->first();
            }

            if ($bill && !empty($bill->paymentStatus)) {
                $paymentStatus = $bill->paymentStatus;
            } else {
                $paymentStatus = KCPaymentsAppointmentMapping::getPaymentStatusByAppointmentId($appointment->id);
            }


            $appointmentsData = [
                'id' => $appointment->id,
                'start' => date('Y-m-d\TH:i:s', strtotime($appointment->appointmentStartDate . ' ' . $appointment->appointmentStartTime)),
                'end' => date('Y-m-d\TH:i:s', strtotime($appointment->appointmentEndDate . ' ' . $appointment->appointmentEndTime)),
                'appointmentStartDateWF' => $appointment->appointmentStartDate,
                'appointmentStartTimeWF' => $appointment->appointmentStartTime,
                'appointmentEndDateWF' => $appointment->appointmentEndDate,
                'appointmentEndTimeWF' => $appointment->appointmentEndTime,
                'appointmentStartDate' => kcGetFormatedDate($appointment->appointmentStartDate),
                'appointmentStartTime' => kcGetFormatedTime($appointment->appointmentStartTime),
                'appointmentEndDate' => kcGetFormatedDate($appointment->appointmentEndDate),
                'appointmentEndTime' => kcGetFormatedTime($appointment->appointmentEndTime),
                'patientId' => $appointment->patientId,
                'patientName' => $appointment->patient_name,
                'patientEmail' => $appointment->patient_email,
                'patientMobileNo' => !empty($patientBasicData['mobile_number']) ? $patientBasicData['mobile_number'] : '',
                'doctorId' => $appointment->doctorId,
                'doctorName' => $appointment->doctor_name,
                'doctorEmail' => $appointment->doctor_email,
                'doctorMobileNo' => !empty($doctorBasicData['mobile_number']) ? $doctorBasicData['mobile_number'] : '',
                'clinicId' => $appointment->clinicId,
                'clinicName' => $appointment->clinic_name,
                'clinicEmail' => $appointment->clinic_email,
                'clinicMobileNo' => !empty($appointment->mobile_no) ? $appointment->mobile_no : '',
                'description' => $appointment->description,
                'visitType' => $appointment->visitType ?? '',
                'status' => $appointment->status,
                'createdAt' => $appointment->createdAt,
                'patient_image_url' => $appointment->patient_profile_image ? wp_get_attachment_url((int) $appointment->patient_profile_image) : '',
                'doctor_image_url' => $appointment->doctor_profile_image ? wp_get_attachment_url((int) $appointment->doctor_profile_image) : '',
                'clinic_image_url' => $appointment->clinic_profile_image ? wp_get_attachment_url((int) $appointment->clinic_profile_image) : '',
                'serviceName' => implode(', ', array_column($service_list, 'title')),
                'paymentMode' => $paymentMode,
                'paymentStatus' => $paymentStatus,
                'appointmentDateTime' => kcGetFormatedDate($appointment->appointmentStartDate) . ' ' . kcGetFormatedTime($appointment->appointmentStartTime),
                'encounterId' => $encounter?->id,
                'encounterStatus' => $encounter?->encounter_status,
                'encounterDescription' => $encounter?->encounter_description,
                'subtotal' => $service_charges,
                'service_list' => $service_list,
                'taxItems' => $taxData,
                'video_consultation' => apply_filters('kc_is_video_consultation', false, absint($appointment->id))
            ];

            if ($appointment->appointmentReport) {
                $reportIds = json_decode($appointment->appointmentReport, true);
                if (is_array($reportIds)) {
                    $reports = [];
                    foreach ($reportIds as $id) {
                        $url = wp_get_attachment_url((int) $id);
                        $filename = get_the_title((int) $id);
                        $reports[] = [
                            'id' => $id,
                            'url' => $url,
                            'filename' => $filename
                        ];
                    }
                    $appointmentsData['appointmentReport'] = $reports;
                }
            }

            // Fetch Patient Medical Reports (Uploaded Reports)
            $medicalReports = KCPatientMedicalReport::query()->where('patient_id', $appointment->patientId)->get();
            $formattedMedicalReports = [];

            foreach ($medicalReports as $report) {
                $fileUrl = '';
                $fileId = '';
                
                // Handle different storage formats for upload_report (ID or URL)
                if (is_numeric($report->uploadReport)) {
                    $fileUrl = wp_get_attachment_url((int) $report->uploadReport);
                    $fileId = $report->uploadReport;
                } else {
                    $fileUrl = $report->uploadReport;
                }

                $formattedMedicalReports[] = [
                    'id' => $report->id,
                    'name' => $report->name,
                    'date' => $report->date,
                    'upload_report' => $fileUrl,
                    'upload_report_id' => $fileId
                ];
            }

            $appointmentsData['patient_medical_reports'] = $formattedMedicalReports;

            $appointmentsData = apply_filters('kc_appointment_details_data', $appointmentsData, $appointment);

            return $this->response($appointmentsData, __('Appointments retrieved successfully.', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve appointments', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Create new appointment
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function createAppointment(WP_REST_Request $request)
    {
        global $wpdb;
        try {
            $wpdb->query('START TRANSACTION'); // Start transaction

            $params = $request->get_params();

            // If the user is a patient use their own ID for security and reliability.
            if ($this->kcbase->getLoginUserRole() === $this->kcbase->getPatientRole()) {
                $params['patientId'] = get_current_user_id();
            }

            /**
             * Allow devs to modify appointment request params before processing
             */
            $params = apply_filters('kivicare_before_create_appointment_params', $params, $request);

            // 3. Plugin Activation Checks
            $proPluginActive = function_exists('isKiviCareProActive') && isKiviCareProActive();

            // 5. Set Clinic ID Based on Role
            $current_user_role = $this->kcbase->getLoginUserRole();
            $clinicId = $params['clinicId'];

            // For non-pro version, always use default clinic ID if not provided or invalid
            if (!$proPluginActive && (empty($clinicId) || $clinicId == 0)) {
                $clinicId = KCClinic::kcGetDefaultClinicId();
            }

            // Validate entities exist
            $doctor = KCDoctor::find($params['doctorId']);
            if (!$doctor) {
                return $this->response(
                    ['error' => 'Doctor not found'],
                    __('Doctor not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            $clinic = KCClinic::find($clinicId);
            if (!$clinic) {
                // For non-pro version, try to use default clinic if current clinic not found
                if (!$proPluginActive) {
                    $defaultClinicId = KCClinic::kcGetDefaultClinicId();
                    $clinic = KCClinic::find($defaultClinicId);
                    if ($clinic) {
                        $clinicId = $defaultClinicId;
                    }
                }

                if (!$clinic) {
                    return $this->response(
                        ['error' => 'Clinic not found'],
                        __('Clinic not found', 'kivicare-clinic-management-system'),
                        false,
                        404
                    );
                }
            }

            $patient = KCPatient::find($params['patientId']);
            if (!$patient) {
                return $this->response(
                    ['error' => 'Patient not found'],
                    __('Patient not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            do_action('kivicare_before_create_appointment', $params);

            if (isset($params['serviceId'])) {
                if (is_string($params['serviceId'])) {
                    $decoded = json_decode($params['serviceId'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $params['serviceId'] = $decoded;
                    } else {
                        $params['serviceId'] = array_map('intval', array_filter(explode(',', $params['serviceId'])));
                    }
                } elseif (!is_array($params['serviceId'])) {
                    $params['serviceId'] = (array) $params['serviceId'];
                }
            }

            $mappingIds = $params['serviceId'] ?? [];

            // 6. Slot Duration Fetching & Calculate End Time
            $appointmentDate = $params['appointmentStartDate'];
            $appointmentStartTime = $params['appointmentStartTime'];

            // Get doctor sessions for the day
            $slotData = KCAppointmentDataService::prepareSlotGenerationData([
                'doctor_id' => $params['doctorId'],
                'clinic_id' => $clinicId,
                'date' => $appointmentDate,
                'service_id' => $mappingIds
            ]);

            // Create the slot generator with prepared data
            $slotGenerator = new KCTimeSlotService($slotData);
            if (!$slotGenerator->isSlotAvailable($appointmentDate . ' ' . $appointmentStartTime)) {
                return $this->response(
                    ['error' => 'Selected time slot is not available'],
                    __('Selected time slot is not available', 'kivicare-clinic-management-system'),
                    false,
                    409
                );
            }


            // Calculate end time
            $startDateTime = new DateTime($appointmentDate . ' ' . $appointmentStartTime);

            $appointmentStartDate = $startDateTime->format('Y-m-d');
            $appointmentStartTime = $startDateTime->format('H:i:s');

            $endDateTime = clone $startDateTime;
            $endDateTime->add(new DateInterval('PT' . $slotData['service_duration_sum'] . 'M'));

            $appointmentEndDate = $endDateTime->format('Y-m-d');
            $appointmentEndTime = $endDateTime->format('H:i:s');

            // 10. Service Mapping (Moved up to calculate total for payment check)
            // Get services details
            $services = KCServiceDoctorMapping::table('dsm')
                ->select([
                    'dsm.*',
                    'c.name'
                ])
                ->leftJoin(KCService::class, 'dsm.service_id', '=', 'c.id', 'c')
                ->whereIn('dsm.id', $mappingIds)->get();

            if ($services->isEmpty()) {
                return $this->response(
                    ['error' => 'No valid services found'],
                    __('No valid services found for the provided IDs', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            // Check if telemed addon is active and prevent booking telemed services if inactive
            if (!isKiviCareTelemedActive() && !isKiviCareGoogleMeetActive()) {
                $telemedServices = $services->filter(function ($service) {
                    return $service->telemedService === 'yes';
                });

                if ($telemedServices->isNotEmpty()) {
                    $wpdb->query('ROLLBACK'); // Rollback transaction
                    return $this->response(
                        ['error' => 'Telemed services cannot be booked as telemed addon is not active'],
                        __('Telemed services cannot be booked as telemed addon is not active', 'kivicare-clinic-management-system'),
                        false,
                        400
                    );
                }
            }

            // Calculate totals
            $subtotal = 0;
            $serviceDetails = [];

            $services->each(function ($service) use (&$subtotal, &$serviceDetails) {
                $serviceDetails[] = [
                    'id' => $service->id,
                    'title' => $service->name,
                    'price' => $service->charges,
                    'image' => $service->image,
                    'extra' => $service->extra
                ];
                $subtotal += $service->charges;
            });

            $summaryData = [
                'services' => $serviceDetails,
                'subtotal' => $subtotal,
                'grand_total' => $subtotal
            ];

            // Allow devs to modify summary data (Calculates Tax/Total) before validation
            do_action_ref_array('kc_appointment_summary_data', [&$summaryData, $params, $services]);

            // 7. Payment Mode Check and Logic
            $paymentMode = !empty($params['paymentGateway']) ? $params['paymentGateway'] : 'offline';
            $status = $params['status'] ?? 0;

            // Enforce payment gateway requirement for Patients IF grand_total > 0
            if ($this->kcbase->getLoginUserRole() === $this->kcbase->getPatientRole()) {
                // Check grand_total
                if (($summaryData['grand_total'] ?? 0) > 0) {
                    // Check if paymentGateway is empty or "null" string (some frontends might send "null")
                    if (empty($params['paymentGateway']) || $params['paymentGateway'] === 'null') {
                        $wpdb->query('ROLLBACK');
                        return $this->response(
                            ['error' => 'payment_gateway_required'],
                            __('Please select a payment method to proceed.', 'kivicare-clinic-management-system'),
                            false,
                            400
                        );
                    }
                    $status = KCAppointment::STATUS_PENDING;
                } else {
                    // Free appointment (0 price)
                    $paymentMode = 'offline';
                    $status = KCAppointment::STATUS_BOOKED; // Auto-confirm free appointments
                }
            } else {
                // For other roles, if payment gateway selected, set pending
                if ($paymentMode !== 'offline') {
                    $status = KCAppointment::STATUS_PENDING;
                }
            }

            $appointmentReport = !empty($params['appointmentFileId']) ? json_encode($params['appointmentFileId']) : null;

            // 9. Insert Appointment
            $appointmentData = [
                'appointmentStartDate' => $appointmentStartDate,
                'appointmentStartTime' => $appointmentStartTime,
                'appointmentEndDate' => $appointmentEndDate,
                'appointmentEndTime' => $appointmentEndTime,
                'clinicId' => $clinicId,
                'doctorId' => $params['doctorId'],
                'patientId' => $params['patientId'],
                'description' => $params['description'] ?? '',
                'status' => $status,
                'visitType' => implode(',', $request->get_param('serviceId')),
                'createdAt' => current_time('mysql'),
                'appointmentReport' => $appointmentReport
            ];

            /**
             * Allow devs to modify appointment insert data
             */
            $appointmentData = apply_filters('kivicare_appointment_data', $appointmentData, $params);

            $appointment = KCAppointment::create($appointmentData);

            if (!$appointment) {
                throw new Exception(__('Failed to create appointment', 'kivicare-clinic-management-system'));
            }

            $appointmentId = $appointment->id ?? $appointment;

            if (!empty($params['tax_data']) && function_exists('isKiviCareProActive') && isKiviCareProActive()) {
                // Save tax data
                do_action('kivicare_save_appointment_tax_data', $appointmentId, $params['tax_data']);
            }

            if (!empty($mappingIds)) {
                foreach ($mappingIds as $serviceId) {
                    $serviceTableIds = KCServiceDoctorMapping::find($serviceId);

                    KCAppointmentServiceMapping::create([
                        'appointmentId' => $appointmentId,
                        'serviceId' => $serviceTableIds->serviceId ?: $serviceTableIds->service_id
                    ]);
                }
            }


            // 11. Custom Fields & Tax Saving
            // Implement This Later

            // 12. Telemed Link Generation
            $telemed_services = $services
                ->where('telemedService', '=', 'yes')
                ->where('clinicId', '=', $clinicId);

            if ($telemed_services->count() > 0) {
                $telemedProvider = KCTelemedFactory::get_provider_by_doctor_id($params['doctorId']);

                if (!$telemedProvider) {
                    throw new Exception(__('Telemed provider not found for the selected doctor', 'kivicare-clinic-management-system'));
                }

                $appointment_datetime_string = $appointmentStartDate . ' ' . $appointmentStartTime;

                $is_meeting_link = $telemedProvider->create_meeting(array(
                    'topic' => $telemed_services->map(fn($service) => $service->name)->join(', ') ?? 'Telemed Service',
                    'type' => 'scheduled',
                    'start_time' => $appointment_datetime_string,
                    'duration' => $slotData['service_duration_sum'],
                    'timezone' => wp_timezone_string(),
                    'password' => '',
                    'waiting_room' => false,
                    'auto_recording' => false,
                    'host_video' => true,
                    'participant_video' => true,
                    'mute_upon_entry' => true,
                    'patient_id' => $params['patientId'],
                    'doctor_id' => $params['doctorId'],
                    'appointment_id' => $appointmentId
                ));
                if (!$is_meeting_link) {
                    throw new Exception(__('Failed to create telemed meeting link', 'kivicare-clinic-management-system'));
                }
            }

            // 13. Cancel Flow (Status = 0)
            if ($status == 0 && !empty($params['id'])) {
                // This is for appointment cancellation - handle if editing existing appointment
                // Send cancellation email and delete telemed links
                do_action('kc_appointment_cancelled', $params['id']);
            }

            // 13. Encounter & Billing Flow (Status = 4 - Completed)
            if ($status == 4) {
                // Create patient encounter
                $encounterData = [
                    'appointmentId' => $appointmentId,
                    'patientId' => $params['patientId'],
                    'doctorId' => $params['doctorId'],
                    'clinicId' => $clinicId,
                    'addedBy' => get_current_user_id(),
                    'createdAt' => current_time('mysql'),
                    'status' => 1
                ];

                $encounter = KCPatientEncounter::create($encounterData);
                $encounterId = $encounter->id ?? $encounter;

                // Create Bill
                $bill = $this->createBill($appointmentId, $clinicId, $encounterId, $services);

                // Use filter to create tax
                do_action('kc_appointment_create_tax', $appointmentId, $appointmentData, $params, $services, $encounterId, $bill->id);
            }

            // 16. Final Response
            $responseData = [
                'appointment_id' => $appointmentId,
                'appointment_start_date' => $appointmentDate,
                'appointment_start_time' => $appointmentStartTime,
                'status' => $status,
            ];

            // 15. Payment Mode Handling
            $gateway = KCPaymentGatewayFactory::get_available_gateway($paymentMode);

            // Process payment only if gateway exists AND grand_total > 0
            if ($gateway && ($summaryData['grand_total'] ?? 0) > 0) {

                // Dynamically get currency code from the active gateway's settings
                $currency_code = 'USD'; // Default fallback
                $gateway_settings = $gateway->get_settings();
                if (isset($gateway_settings['currency'])) {
                    if (is_array($gateway_settings['currency']) && isset($gateway_settings['currency']['value'])) {
                        $currency_code = $gateway_settings['currency']['value'];
                    } elseif (is_string($gateway_settings['currency'])) {
                        $currency_code = $gateway_settings['currency'];
                    }
                }

                $gatewayResponce = $gateway->process_payment([
                    'appointment_id' => $appointmentId,
                    'amount' => $summaryData['grand_total'] ?? 0,
                    'service_name' => 'Iqonic Test Service',
                    'currency' => $currency_code,
                    'patient_id' => $params['patientId'],
                    'patient_name' => $patient->display_name,
                    'patient_email' => $patient->email,
                    'doctor_id' => $params['doctorId'],
                    'clinic_id' => $clinicId,
                    'services' => $summaryData['services'],
                    'widget_type' => 'phpWidget',
                    'is_app' => isset($params['is_app']) ? $params['is_app'] : false,
                    'stripe_version' => $params['stripe_version'] ?? null,
                    ...$summaryData
                ]);
                if ($gatewayResponce['status'] === 'failed') {
                    throw new Exception($gatewayResponce['message'], 1);
                }

                do_action('kivicare_before_payment_processed', $appointmentId, $gatewayResponce);

                $user_role = $this->kcbase->getPatientRole();
                $redirectUrl = kc_get_dashboard_url($user_role);

                $inserted_appointment_mapping_id = KCPaymentsAppointmentMapping::create([
                    'appointmentId' => $appointmentId,
                    'paymentMode' => $gatewayResponce['gateway'] ?? ($gateway ? $gateway->get_gateway_name() : ''),
                    'paymentStatus' => $gatewayResponce['status'] ?? '',
                    'amount' => $summaryData['grand_total'] ?? 0,
                    'currency' => $currency_code,
                    'transactionId' => $gatewayResponce['data']['transaction_ref'] ?? '',
                    'paymentId' => $gatewayResponce['data']['payment_id'] ?? '',
                    'payerId' => '',
                    'payerEmail' => '',
                    'extra' => json_encode(['is_from_frontend' => $request->get_param('page_id') ?? $redirectUrl]),
                    'notificationStatus' => 0,
                    'createdAt' => current_time('mysql'),
                    'updatedAt' => current_time('mysql')
                ]);
                if ($inserted_appointment_mapping_id == 0) {
                    throw new Exception(__('Failed to create payment mapping', 'kivicare-clinic-management-system'));
                }
                $responseData['payment_response'] = $gatewayResponce;
            }

            do_action('kc_after_create_appointment', $appointmentId, $appointmentData, $request);

            $wpdb->query('COMMIT'); // Commit transaction

            // After successful commit, add to Google Calendar if connected (non-transactional, ignore errors)
            if (isKiviCareProActive()) {
                GoogleCalendarIntegration::getInstance()->addAppointmentToGoogleCalendars($appointmentId, $appointmentData, $clinic, $patient, $doctor, $services);
            }
            return $this->response(
                $responseData,
                __('Appointment created successfully', 'kivicare-clinic-management-system'),
                true,
                201
            );
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK'); // Rollback transaction on error
            KCErrorLogger::instance()->error('Create Appointment Error: ' . $e->getMessage());
            KCErrorLogger::instance()->error('Stack trace: ' . $e->getTraceAsString());

            $status = ($e->getCode() === 1) ? 400 : 500;
            return $this->response(
                ['error' => $e->getMessage()],
                /* translators: %s: Error message */
                sprintf(__('Failed to create appointment: %s', 'kivicare-clinic-management-system'), $e->getMessage()),
                false,
                $status
            );
        }
    }

    /**
     * Update existing appointment (date and time only)
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function updateAppointment($request)
    {
        global $wpdb;
        try {
            $wpdb->query('START TRANSACTION'); // Start transaction

            $params = $request->get_params();


            if (empty($params['appointmentStartDate']) || empty($params['appointmentStartTime'])) {
                return $this->response(
                    ['error' => 'New appointment date and time are required'],
                    __('New appointment date and time are required', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Validate appointment exists
            $appointment = KCAppointment::find($params['id']);
            if (!$appointment) {
                return $this->response(
                    ['error' => 'Appointment not found'],
                    __('Appointment not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            // Validate entities still exist
            $doctor = KCDoctor::find($appointment->doctorId);
            if (!$doctor) {
                return $this->response(
                    ['error' => 'Doctor not found'],
                    __('Doctor not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            $clinic = KCClinic::find($appointment->clinicId);
            if (!$clinic) {
                return $this->response(
                    ['error' => 'Clinic not found'],
                    __('Clinic not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            $patient = KCPatient::find($appointment->patientId);
            if (!$patient) {
                return $this->response(
                    ['error' => 'Patient not found'],
                    __('Patient not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            // Get appointment services to calculate duration and for Google sync
            $appointmentServices = KCAppointmentServiceMapping::query()->where('appointmentId', $params['id'])->get();
            if ($appointmentServices->isEmpty()) {
                return $this->response(
                    ['error' => 'No services found for this appointment'],
                    __('No services found for this appointment', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            // Get service IDs for slot validation
            $serviceIds = $appointmentServices->pluck('serviceId')->toArray();

            // Prepare new appointment date and time
            $newAppointmentDate = $params['appointmentStartDate'];
            $newAppointmentStartTime = $params['appointmentStartTime'];

            // Get doctor sessions and service duration for the new date
            $slotData = KCAppointmentDataService::prepareSlotGenerationData([
                'doctor_id' => $appointment->doctorId,
                'clinic_id' => $appointment->clinicId,
                'date' => $newAppointmentDate,
                'service_id' => $serviceIds,
                'appointment_id' => $params['id']
            ]);

            // Create the slot generator with prepared data
            $slotGenerator = new KCTimeSlotService($slotData);
            $newSlotDateTime = $newAppointmentDate . ' ' . $newAppointmentStartTime;

            // Check if the appointment is being moved to a different time slot
            $currentSlotDateTime = $appointment->appointmentStartDate . ' ' . $appointment->appointmentStartTime;

            // Compare timestamps to avoid format issues (e.g. H:i vs H:i:s)
            if (strtotime($newSlotDateTime) !== strtotime($currentSlotDateTime)) {
                // Only check availability if the slot is actually changing
                if (!$slotGenerator->isSlotAvailable($newSlotDateTime)) {
                    return $this->response(
                        ['error' => 'Selected time slot is not available'],
                        __('Selected time slot is not available', 'kivicare-clinic-management-system'),
                        false,
                        409
                    );
                }
            }

            // Calculate new end time
            $startDateTime = new DateTime($newAppointmentDate . ' ' . $newAppointmentStartTime);
            $newAppointmentStartDate = $startDateTime->format('Y-m-d');
            $newAppointmentStartTime = $startDateTime->format('H:i:s');

            $endDateTime = clone $startDateTime;
            $endDateTime->add(new DateInterval('PT' . $slotData['service_duration_sum'] . 'M'));

            $newAppointmentEndDate = $endDateTime->format('Y-m-d');
            $newAppointmentEndTime = $endDateTime->format('H:i:s');

            // Check for telemed services and update Google Meet event if applicable
            $telemedServices = KCAppointmentServiceMapping::table('asm')
                ->select(['sdm.*', 's.name as service_name'])
                ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
                ->leftJoin(KCServiceDoctorMapping::class, 'sdm.service_id', '=', 's.id', 'sdm')
                ->where('asm.appointment_id', $params['id'])
                ->where('sdm.telemed_service', '=', 'yes')
                ->where('sdm.clinic_id', '=', $appointment->clinicId)
                ->get();

            if ($telemedServices->count() > 0) {
                $doctorId = $appointment->doctorId;
                $telemedProvider = KCTelemedFactory::get_provider_by_doctor_id($doctorId);

                if ($telemedProvider && $telemedProvider->get_provider_id() === 'googlemeet' && class_exists('\KCGMApp\models\KCGMAppointmentGoogleMeetMapping')) {
                    // Get the existing Google Meet event ID using the ORM safely
                    $meetingMapping = \KCGMApp\models\KCGMAppointmentGoogleMeetMapping::query()
                        ->where('appointmentId', (int) $params['id'])
                        ->select(['eventId'])
                        ->first();

                    if ($meetingMapping && !empty($meetingMapping->eventId)) {
                        $patientUser = get_userdata($appointment->patientId);
                        $doctorUser = get_userdata($appointment->doctorId);

                        // Extract service names from telemed services
                        $serviceNames = array_filter(array_column($telemedServices->toArray(), 'service_name'));

                        // Create topic and summary
                        $topic = !empty($serviceNames)
                            ? implode(', ', $serviceNames)
                            : 'Telemed Appointment';

                        // Get description from appointment if available, otherwise create default

                        $meetingData = [
                            'appointment_id' => $params['id'],
                            'topic' => $topic,
                            'summary' => $appointment->description,
                            'attendees' => array_filter([
                                $patientUser->user_email ?? '',
                                $doctorUser->user_email ?? ''
                            ]),
                            'doctor_id' => $doctorId,
                            'start_time' => $startDateTime->format('Y-m-d\TH:i:sP'),
                            'end_time' => $endDateTime->format('Y-m-d\TH:i:sP'),
                            'timezone' => wp_timezone_string()
                        ];

                        $meetingResult = $telemedProvider->update_meeting($meetingMapping->eventId, $meetingData);
                        if (!$meetingResult) {
                            throw new Exception(__('Failed to update Google Meet event', 'kivicare-clinic-management-system'));
                        }
                    } else {
                        KCErrorLogger::instance()->error('No Google Meet event found for appointment ID: ' . $params['id']);
                    }
                } elseif ($telemedProvider && $telemedProvider->get_provider_id() === 'zoom') {
                    $mapping = $telemedProvider->get_meeting_by_appointment($params['id']);

                    if (!empty($mapping->zoomId)) {
                        $telemedProvider->update_meeting($mapping->zoomId, [
                            'start_time' => "{$newAppointmentStartDate} {$newAppointmentStartTime}",
                            'duration'   => $slotData['service_duration_sum'] ?? 30,
                            'doctor_id'  => $doctorId,
                        ]);
                    }
                }
            }

            // Update appointment with new date and time only
            $updateData = [
                'appointmentStartDate' => $newAppointmentStartDate,
                'appointmentStartTime' => $newAppointmentStartTime,
                'appointmentEndDate' => $newAppointmentEndDate,
                'appointmentEndTime' => $newAppointmentEndTime,
                'description' => $params['description'] ?? '',
                'updatedAt' => current_time('mysql'),
            ];

            // Include status if provided
            if (isset($params['status'])) {
                $updateData['status'] = $params['status'];
            }

            $updated = KCAppointment::query()->where('id', $params['id'])->update($updateData);

            if ($updated === false) {
                throw new Exception(__('Failed to update appointment', 'kivicare-clinic-management-system'));
            }

            // Reload appointment to get updated data
            $appointment = KCAppointment::find($params['id']);

            // If status is 4 and no encounter exists, create encounter, bill, and tax
            if ($appointment->status == 4 && empty($appointment->encounterId)) {
                // Create patient encounter
                $encounterData = [
                    'appointmentId' => $appointment->id,
                    'patientId' => $appointment->patientId,
                    'clinicId' => $appointment->clinicId,
                    'doctorId' => $appointment->doctorId,
                    'encounterDate' => $appointment->appointmentStartDate,
                    'createdAt' => current_time('mysql'),
                    'description' => $appointment->description ?? '',
                    'addedBy' => get_current_user_id(),
                    'status' => 1, // Active encounter
                ];
                $encounter = KCPatientEncounter::create($encounterData);
                $encounterId = $encounter->id ?? $encounter;

                // Get services for billing
                $services = KCServiceDoctorMapping::table('dsm')
                    ->select([
                        'dsm.*',
                        'c.name'
                    ])
                    ->leftJoin(KCService::class, 'dsm.service_id', '=', 'c.id', 'c')
                    ->whereIn('dsm.id', $params['serviceId'])->get();
                ;
                // Create Bill
                $bill = $this->createBill($params['id'], $appointment->clinicId, $encounterId, $services);

                // Use filter to create tax
                do_action('kc_appointment_create_tax', $params['id'], $appointment->toArray(), $params, $services, $encounterId, $bill->id);
            }

            if ($updated === false) {
                throw new Exception(__('Failed to update appointment', 'kivicare-clinic-management-system'));
            }

            // Trigger hook for appointment update
            do_action('kc_appointment_updated', $params['id'], $updateData, $appointment, $request);

            $wpdb->query('COMMIT'); // Commit transaction

            if (isKiviCareProActive()) {
                GoogleCalendarIntegration::getInstance()->updateAppointmentToGoogleCalendars($params['id'], $updateData, $clinic, $patient, $doctor, $appointmentServices);
            }
            // Prepare response data
            $responseData = [
                'appointment_id' => $params['id'],
                'appointment_start_date' => $newAppointmentStartDate,
                'appointment_start_time' => $newAppointmentStartTime,
                'appointment_end_date' => $newAppointmentEndDate,
                'appointment_end_time' => $newAppointmentEndTime,
                'previous_start_date' => $appointment->appointmentStartDate,
                'previous_start_time' => $appointment->appointmentStartTime,
            ];

            return $this->response(
                $responseData,
                __('Appointment updated successfully', 'kivicare-clinic-management-system'),
                true,
                200
            );
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK'); // Rollback transaction on error
            KCErrorLogger::instance()->error('Update Appointment Error: ' . $e->getMessage());
            KCErrorLogger::instance()->error('Stack trace: ' . $e->getTraceAsString());

            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update appointment', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Delete appointment
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function deleteAppointment($request)
    {
        $id = $request->get_param('id');

        // Find the patient
        $appointment = KCAppointment::find($id);

        $bill = KCBill::query()->where('appointment_id', $id)->first();

        if (!$appointment) {
            return $this->response(null, __('appointment not found', 'kivicare-clinic-management-system'), false, 404);
        }

        KCAppointmentServiceMapping::query()->where('appointment_id', $id)->delete();
        KCPatientEncounter::query()->where('appointment_id', $id)->delete();
        if ($bill) {
            KCBillItem::query()->where('bill_id', $bill->id)->delete();
        }
        KCBill::query()->where('appointment_id', $id)->delete();
        KCPaymentsAppointmentMapping::query()->where('appointment_id', $id)->delete();

        // Delete custom field data

        KCCustomFieldData::query()
            ->where('module_type', 'appointment_module')
            ->where('module_id', $id)
            ->delete();


        if ($telemed_provider = KCTelemedFactory::get_provider_by_doctor_id($appointment->doctorId)) {
            $telemed_provider?->cancel_meeting_by_appointment($id);
        }

        $result = $appointment->delete();

        if (!$result) {
            return $this->response(null, __('Failed to delete Appointment', 'kivicare-clinic-management-system'), false, 500);
        }

        do_action('kc_appointment_deleted', $id, $appointment);

        // Your logic to delete appointment from the database
        return $this->response(['id' => $id], 'Appointment deleted successfully');
    }

    /**
     * Update appointment status
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function updateStatus($request)
    {
        $id = $request->get_param('id');
        $status = $request->get_param('status');

        $appointment = KCAppointment::find($id);

        if (!$appointment) {
            return $this->response(null, __('Appointment not found', 'kivicare-clinic-management-system'), false, 404);
        }

        $encounter = KCPatientEncounter::table('e')
            ->select([
                'e.id'
            ])
            ->where('e.appointment_id', $id)
            ->first();

        $appointmentDoctorId = $appointment->doctorId;
        if ($status === 4) {
            // Create encounter if not present
            if (!empty($appointment) && empty($encounter)) {
                $encounter = KCPatientEncounter::create([
                    'encounterDate' => gmdate('Y-m-d'),
                    'clinicId' => $appointment->clinicId,
                    'doctorId' => $appointment->doctorId,
                    'patientId' => $appointment->patientId,
                    'appointmentId' => $id,
                    'description' => $appointment->description,
                    'status' => 1,
                    'addedBy' => get_current_user_id(),
                    'createdAt' => current_time('mysql', true),
                ]);
                $encounterId = $encounter->id ?? $encounter;
            } else {
                $encounterId = $encounter->id ?? $encounter;
            }

            // If encounter exists, delegate bill and tax creation to the pro hook
            if (!empty($encounterId)) {
                $services = KCAppointmentServiceMapping::table('asm')
                    ->select([
                        'asm.id as mapping_id',
                        'asm.service_id',
                        'sdm.id',
                        'sdm.service_id as serviceId',
                        'sdm.charges',
                        's.name',
                        's.id as service_id_ref'
                    ])
                    ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
                    ->leftJoin(KCServiceDoctorMapping::class, 'sdm.service_id', '=', 's.id', 'sdm')
                    ->where('asm.appointment_id', $id)
                    ->where('sdm.doctor_id', $appointmentDoctorId)
                    ->where('sdm.clinic_id', $appointment->clinicId)
                    ->get();

                $paramsForTax = [
                    'clinicId' => $appointment->clinicId,
                    'doctorId' => $appointment->doctorId,
                    'services' => $services,
                    // When updating status completion via this flow, we want to create bill and items — also save tax data
                    'save_tax' => true,
                ];

                $appointmentData = [
                    'clinicId' => $appointment->clinicId,
                    'doctorId' => $appointment->doctorId,
                    'patientId' => $appointment->patientId,
                    'description' => $appointment->description ?? '',
                ];

                // Create Bill
                $bill = $this->createBill($id, $appointment->clinicId, $encounterId, $services);

                // Use action so pro module can handle tax creation
                do_action('kc_appointment_create_tax', $id, $appointmentData, $paramsForTax, $services, $encounterId, $bill->id);
            }
        }

        // Update appointment status to confirmed
        KCAppointment::find($id)->update([
            'status' => $status
        ]);

        do_action('kc_appointment_status_update', $id, $status, $appointment);

        return $this->response(
            ['id' => $id, 'status' => $status],
            'Appointment status updated successfully'
        );
    }


    /**
     * Handle payment success callback
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handlePaymentSuccess(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            $params = $request->get_params();
            $appointmentId = $params['appointment_id'];
            $gateway = $params['gateway'];
            $payment_id = $params['payment_id'] ?? '';

            // Find the appointment
            $appointment = KCAppointment::find($appointmentId);
            if (!$appointment) {
                return $this->response(
                    ['error' => 'Appointment not found'],
                    __('Appointment not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            // Get the payment gateway instance
            $paymentGateway = KCPaymentGatewayFactory::get_available_gateway($gateway);
            if (!$paymentGateway) {
                return $this->response(
                    ['error' => 'Payment gateway not found'],
                    __('Payment gateway not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            // Process the payment callback
            $paymentResult = $paymentGateway->handle_payment_callback($params);
            if ($paymentResult['status'] === "failed") {
                throw new Exception($paymentResult['message'], 1);
            }

            // Update appointment status to confirmed
            KCAppointment::find($appointmentId)->update([
                'status' => 1 // Confirmed status
            ]);

            // update payment mapping
            $paymentData = [
                'paymentStatus' => 'completed',
                'transactionId' => $paymentResult['data']['transaction_id'] ?? null,
                'paymentId' => $payment_id,
                'paymentDate' => current_time('mysql'),
                'createdAt' => current_time('mysql')
            ];

            // Check if payment mapping already exists
            $existingPayment = KCPaymentsAppointmentMapping::query()->where('appointment_id', $appointmentId)->first();

            if ($existingPayment) {
                KCPaymentsAppointmentMapping::query()
                    ->where('appointment_id', $appointmentId)
                    ->first()
                    ->update($paymentData);
            }

            $telemed_services = KCAppointmentServiceMapping::table('asm')
                ->select(['sdm.*', 'asm.*'])
                ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
                ->leftJoin(KCServiceDoctorMapping::class, 'sdm.service_id', '=', 's.id', 'sdm')
                ->where('appointment_id', $appointmentId)
                ->where('sdm.telemed_service', '=', 'yes')
                ->where('sdm.clinic_id', '=', $appointment->clinicId)
                ->get();


            if ($telemed_services->count() > 0) {

                $telemedProvider = KCTelemedFactory::get_provider_by_doctor_id($appointment->doctorId);

                $is_meeting_link = $telemedProvider?->create_meeting(array(
                    'topic' => $telemed_services->map(fn($service) => $service->name)->join(', ') ?? 'Telemed Service',
                    'type' => 'scheduled',
                    'start_time' => current_time('mysql'),
                    'duration' => $appointment->appointmentEndTime - $appointment->appointmentStartTime,
                    'timezone' => wp_timezone_string(),
                    'password' => '',
                    'waiting_room' => false,
                    'auto_recording' => false,
                    'host_video' => true,
                    'participant_video' => true,
                    'mute_upon_entry' => true,
                    'patient_id' => $appointment->patientId,
                    'doctor_id' => $appointment->doctorId,
                    'appointment_id' => $appointmentId
                ));
            }


            // Trigger payment success hook
            do_action('kc_appointment_payment_completed', $appointmentId, $paymentResult['data']);

            $wpdb->query('COMMIT');

            // Redirect to success page or return success response
            $redirectUrl = home_url('/appointment-success/?appointment_id=' . $appointmentId);
            $page_id = json_decode($existingPayment->extra ?? '{}', true)['is_from_frontend'];

            if ($page_id !== false) {
                if (is_numeric($page_id)) {
                    $redirectUrl = get_permalink($page_id);
                } else {
                    $redirectUrl = $page_id;
                }
            }
            if ($request->get_method() === 'POST') {
                return $this->response([
                    'status' => 'success',
                    'message' => __('Payment completed successfully', 'kivicare-clinic-management-system'),
                    'data' => [
                        'appointment_id' => $appointmentId,
                        'payment_id' => $payment_id,
                    ],
                    'redirect_url' => add_query_arg([
                        'payment_status' => 'completed',
                        'payment_id' => $payment_id,
                        'message' => __('Payment completed successfully', 'kivicare-clinic-management-system')
                    ], $page_id !== false && is_numeric($page_id) ? $redirectUrl : $redirectUrl . '/appointments/view/' . $appointmentId)
                ]);
            }
            wp_safe_redirect(add_query_arg([
                'payment_status' => 'completed',
                'payment_id' => $payment_id,
                'message' => 'Payment completed successfully'
            ], $page_id !== false && is_numeric($page_id) ? $redirectUrl : $redirectUrl . '/appointments/view/' . $appointmentId));
            exit;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            // Check if payment mapping already exists
            $existingPayment = KCPaymentsAppointmentMapping::query()->where('appointment_id', $request->get_param('appointment_id'))->first();

            // Redirect to success page or return success response
            $page_id = json_decode($existingPayment->extra ?? '{}', true)['is_from_frontend'];
            $redirectUrl = home_url(); // Default fallback

            if ($page_id !== false) {
                if (is_numeric($page_id)) {
                    $redirectUrl = get_permalink($page_id);
                } else {
                    $redirectUrl = $page_id;
                }
            } else {
                $user_role = $this->kcbase->getPatientRole();
                $redirectUrl = kc_get_dashboard_url($user_role);
            }

            if ($request->get_method() === 'POST') {
                return $this->response([
                    'status' => 'success',
                    'message' => __('Payment completed successfully', 'kivicare-clinic-management-system'),
                    'data' => [
                        'appointment_id' => $appointmentId,
                        'payment_id' => $existingPayment->paymentId ?? null,
                    ],
                    'redirect_url' => add_query_arg([
                        'payment_status' => 'failed',
                        'error' => $e->getMessage(),
                    ], $page_id !== false && is_numeric($page_id) ? $redirectUrl : $redirectUrl . '/appointments/view/' . $appointmentId)
                ]);
            }

            wp_safe_redirect(add_query_arg([
                'payment_status' => 'failed',
                'error' => $e->getMessage(),
            ], $page_id !== false && is_numeric($page_id) ? $redirectUrl : $redirectUrl . '/appointments/view/' . $appointmentId));
            exit;
        }
    }


    /**
     * Handle payment cancellation/failure callback
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handlePaymentCancel(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();
        $appointmentId = $params['appointment_id'];
        $gateway = $params['gateway'];

        // Find the appointment
        $appointment = KCAppointment::find($appointmentId);
        if (!$appointment) {
            return $this->response(
                ['error' => 'Appointment not found'],
                __('Appointment not found', 'kivicare-clinic-management-system'),
                false,
                404
            );
        }

        // Check if payment mapping already exists
        $existingPayment = KCPaymentsAppointmentMapping::query()->where('appointment_id', $appointmentId)->first();

        if ($existingPayment) {
            KCPaymentsAppointmentMapping::query()
                ->where('appointment_id', $appointmentId)
                ->first()
                ->update(['payment_status' => 'cancelled']);
        }

        // Cancel the appointment when payment is not successful
        KCAppointment::query()->where('id', $appointmentId)->first()->update([
            'status' => 0 // Set to cancelled (0) when payment fails or user backs out
        ]);

        // Trigger payment cancellation hook
        do_action('kc_appointment_payment_cancelled', $appointmentId);

        $page_id = json_decode($existingPayment->extra ?? '{}', true)['is_from_frontend'];
        $redirectUrl = home_url();

        if ($page_id !== false) {
            // Check if numeric ID or URL string
            if (is_numeric($page_id)) {
                $redirectUrl = get_permalink($page_id);
            } else {
                $redirectUrl = $page_id;
            }
        } else {
            $user_role = $this->kcbase->getPatientRole();
            $redirectUrl = kc_get_dashboard_url($user_role);
        }

        // Correct redirection path based on context
        $finalUrl = ($page_id !== false && is_numeric($page_id))
            ? $redirectUrl
            : $redirectUrl . '/appointments/add';

        wp_safe_redirect(add_query_arg([
            'payment_status' => 'cancelled',
            'message' => __('Payment was cancelled', 'kivicare-clinic-management-system')
        ], $finalUrl));
        exit;
    }

    /**
     * Handle payment verification
     * This endpoint is used to verify payment status and return appointment details
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handlePaymentVerification(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();
            $payment_status = $params['payment_status'];
            $payment_id = $params['payment_id'];
            $appointment_id = $params['appointment_id'] ?? null;
            $message = $params['message'] ?? '';

            // Log the verification request
            KCErrorLogger::instance()->error("Payment verification requested - Payment ID: {$payment_id}, Status: {$payment_status}");

            // Initialize response data
            $response_data = [
                'payment_id' => $payment_id,
                'payment_status' => $payment_status,
                'message' => $message
            ];

            // If payment status is completed, try to get appointment details
            if ($payment_status === 'completed') {
                if ($appointment_id) {
                    // Get appointment details
                    $appointment = KCAppointment::table('a')
                        ->leftJoin(KCAppointmentServiceMapping::class, 'a.id', '=', 'asm.appointment_id', 'asm')
                        ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
                        ->leftJoin(KCPatient::class, 'a.patient_id', '=', 'p.ID', 'p')
                        ->leftJoin(KCDoctor::class, 'a.doctor_id', '=', 'd.ID', 'd')
                        ->leftJoin(KCClinic::class, 'a.clinic_id', '=', 'c.id', 'c')
                        ->select([
                            'a.id',
                            'a.appointment_start_date',
                            'a.appointment_start_time',
                            'a.appointment_end_time',
                            'a.status',
                            'p.display_name as patient_name',
                            'p.user_email as patient_email',
                            'd.display_name as doctor_name',
                            'c.name as clinic_name',
                            's.name as service_name',
                            's.price as service_price'
                        ])
                        ->where('a.id', $appointment_id)
                        ->first();

                    if ($appointment) {
                        $response_data['appointment'] = $appointment;
                        $response_data['status'] = 'success';

                        return $this->response(
                            $response_data,
                            __('Payment verified successfully', 'kivicare-clinic-management-system')
                        );
                    } else {
                        return $this->response(
                            $response_data,
                            __('Appointment not found', 'kivicare-clinic-management-system'),
                            false,
                            404
                        );
                    }
                } else {
                    // No appointment ID provided, just verify payment status
                    $response_data['status'] = 'success';

                    return $this->response(
                        $response_data,
                        __('Payment status verified', 'kivicare-clinic-management-system')
                    );
                }
            } else {
                // Payment not completed
                $response_data['status'] = 'failed';

                return $this->response(
                    $response_data,
                    __('Payment verification failed', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

        } catch (Exception $e) {
            KCErrorLogger::instance()->error('Payment Verification Error: ' . $e->getMessage());

            return $this->response(
                ['error' => 'Payment verification failed'],
                __('Payment verification failed', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Create a new appointment
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleRegenerateVideoLink(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        try {
            $wpdb->query('START TRANSACTION'); // Start transaction

            $appointmentId = $request->get_param('id');

            // Validate appointment exists
            $appointment = KCAppointment::find($appointmentId);
            if (!$appointment) {
                return $this->response(
                    ['error' => 'Appointment not found'],
                    __('Appointment not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            // Get the telemed provider for the doctor
            $telemedProvider = KCTelemedFactory::get_provider_by_doctor_id($appointment->doctorId);
            if (!$telemedProvider) {
                throw new Exception(__('Telemed provider not found for the selected doctor', 'kivicare-clinic-management-system'));
            }

            // Regenerate the meeting link
            $is_meeting_link = $telemedProvider->create_meeting(array(
                'topic' => 'Regenerated Telemed Service',
                'type' => 'scheduled',
                'start_time' => current_time('mysql'),
                'duration' => 30, // Default duration, adjust as needed
                'timezone' => wp_timezone_string(),
                'password' => '',
                'waiting_room' => false,
                'auto_recording' => false,
                'host_video' => true,
                'participant_video' => true,
                'mute_upon_entry' => true,
                'patient_id' => $appointment->patientId,
                'doctor_id' => $appointment->doctorId,
                'appointment_id' => $appointmentId
            ));

            if (is_wp_error($is_meeting_link)) {
                throw new Exception($is_meeting_link->get_error_message());
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            return $this->response(
                ['status' => 'success', 'message' => __('Telemed link regenerated successfully', 'kivicare-clinic-management-system')],
                __('Telemed link regenerated successfully', 'kivicare-clinic-management-system'),
                true,
                200
            );

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK'); // Rollback transaction on error
            KCErrorLogger::instance()->error('Regenerate Telemed Link Error: ' . $e->getMessage());

            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to regenerate telemed link', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Export appointments data
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function exportAppointments(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Get request parameters
            $params = $request->get_params();
            $format = $params['format'];

            // Use the same query logic as getAppointments but without pagination
            $query = KCAppointment::table('a')
                ->select([
                    "a.*",
                    'c.name as clinic_name',
                    'c.email as clinic_email',
                    'c.profile_image as clinic_profile_image',
                    'c.address as clinic_address',
                    'c.city as clinic_city',
                    'c.state as clinic_state',
                    'c.country as clinic_country',
                    'c.postal_code as clinic_postal_code',
                    'p.ID as patient_user_id',
                    'p.display_name as patient_name',
                    'p.user_email as patient_email',
                    'd.ID as doctor_user_id',
                    'd.display_name as doctor_name',
                    'd.user_email as doctor_email',
                    'pam.payment_mode as payment_mode',
                    'pam.payment_status as payment_status',
                    'pam.amount as payment_amount',
                    'pam.currency as payment_currency',
                    'pe.id as encounter_id',
                    'pe.status as encounter_status',
                    "pbd.meta_value as patient_basic_data",
                    "ppi.meta_value as patient_profile_image",
                    "dbd.meta_value as doctor_basic_data",
                    "dpi.meta_value as doctor_profile_image"
                ])
                ->leftJoin(KCClinic::class, "a.clinic_id", '=', 'c.id', 'c')
                ->leftJoin(KCDoctor::class, "a.doctor_id", '=', 'd.id', 'd')
                ->leftJoin(KCPatient::class, "a.patient_id", '=', 'p.id', 'p')
                ->leftJoin(KCPaymentsAppointmentMapping::class, "a.id", '=', 'pam.appointment_id', 'pam')
                ->leftJoin(KCPatientEncounter::class, "a.id", '=', 'pe.appointment_id', 'pe')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'pbd.user_id')
                        ->onRaw("pbd.meta_key = 'basic_data'");
                }, null, null, 'pbd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('p.ID', '=', 'ppi.user_id')
                        ->onRaw("ppi.meta_key = 'patient_profile_image'");
                }, null, null, 'ppi')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'dbd.user_id')
                        ->onRaw("dbd.meta_key = 'basic_data'");
                }, null, null, 'dbd')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('d.ID', '=', 'dpi.user_id')
                        ->onRaw("dpi.meta_key = 'doctor_profile_image'");
                }, null, null, 'dpi');


            // Apply role-based filtering (same as getAppointments)
            $current_user_id = get_current_user_id();
            $current_user_role = $this->kcbase->getLoginUserRole();

            if ($current_user_role === $this->kcbase->getDoctorRole()) {
                $query->where("a.doctor_id", '=', $current_user_id);
            } else if (!empty($params['doctor'])) {
                $query->where("a.doctor_id", '=', (int) $params['doctor']);
            }

            if ($current_user_role === $this->kcbase->getPatientRole()) {
                $query->where("a.patient_id", '=', $current_user_id);
            } else if (!empty($params['patient'])) {
                $query->where("a.patient_id", '=', (int) $params['patient']);
            }


            // Apply filters
            if (!empty($params['search'])) {
                $searchTerm = $params['search'];
                $query->where(function ($q) use ($searchTerm) {
                    $q->where("a.description", 'LIKE', '%' . $searchTerm . '%')
                        ->orWhere("p.display_name", 'LIKE', '%' . $searchTerm . '%')
                        ->orWhere("p.user_email", 'LIKE', '%' . $searchTerm . '%')
                        ->orWhere("d.display_name", 'LIKE', '%' . $searchTerm . '%')
                        ->orWhere("d.user_email", 'LIKE', '%' . $searchTerm . '%')
                        ->orWhere("c.name", 'LIKE', '%' . $searchTerm . '%');
                });
            }

            if (!empty($params['date'])) {
                $query->where("a.appointment_start_date", '=', $params['date']);
            }

            if (!empty($params['date_from'])) {
                $query->where("a.appointment_start_date", '>=', $params['date_from']);
            }

            if (!empty($params['date_to'])) {
                $query->where("a.appointment_start_date", '<=', $params['date_to']);
            }

            // Apply timeFrame filter (all, upcoming, past)
            $timeFrame = strtolower($params['timeFrame'] ?? 'all');
            if ($timeFrame !== 'all') {
                // Get current date and time in MySQL format
                $currentDate = current_time('Y-m-d');
                $currentTime = current_time('H:i:s');

                if ($timeFrame === 'upcoming') {
                    // Upcoming: Appointments that are today with time in future, or any future date
                    $query->where(function ($q) use ($currentDate, $currentTime) {
                        $q->where(function ($innerQ) use ($currentDate, $currentTime) {
                            // Today's appointments with time later than now
                            $innerQ->where('a.appointment_start_date', '=', $currentDate)
                                ->where('a.appointment_start_time', '>', $currentTime);
                        })->orWhere('a.appointment_start_date', '>', $currentDate);
                        // $q->where('a.status', '=', KCAppointment::STATUS_BOOKED); // REMOVED: Caused conflict with status filter
                    });
                } elseif ($timeFrame === 'past') {
                    // Past: Appointments with dates before today, or today with time already passed
                    $query->where(function ($q) use ($currentDate, $currentTime) {
                        $q->where('a.appointment_start_date', '<', $currentDate)
                            ->orWhere(function ($innerQ) use ($currentDate, $currentTime) {
                                $innerQ->where('a.appointment_start_date', '=', $currentDate)
                                    ->where('a.appointment_start_time', '<', $currentTime);
                            });
                    });
                }
            }

            if (!empty($params['clinic'])) {
                $query->where("a.clinic_id", '=', (int) $params['clinic']);
            }

            if (isset($params['status']) && $params['status'] !== '') {
                $query->where("a.status", '=', (int) $params['status']);
            }

            // Apply sorting (default by id desc)
            $query->orderBy("a.id", 'DESC');

            // Apply pagination if provided
            $perPageParam = isset($params['perPage']) ? $params['perPage'] : 'all';
            $showAll = (strtolower($perPageParam) === 'all');

            if (!$showAll) {
                $perPage = (int) $perPageParam;
                if ($perPage <= 0) {
                    $perPage = 10;
                }

                $page = isset($params['page']) ? (int) $params['page'] : 1;
                if ($page <= 0) {
                    $page = 1;
                }

                $offset = ($page - 1) * $perPage;
                $query->limit($perPage)->offset($offset);
            }

            // Group by appointment ID to ensure unique rows
            $query->groupBy('a.id');

            // Execute query
            $results = $query->get();

            if ($results->isEmpty()) {
                return $this->response(
                    ['appointments' => []],
                    __('No appointments found', 'kivicare-clinic-management-system'),
                    true,
                    200
                );
            }

            // CUSTOM FIELDS PREPARATION
            $appointmentIds = $results->pluck('id')->toArray();

            // Get all custom field definitions for the appointment module
            $customFieldDefs = \App\models\KCCustomField::query()
                ->where('module_type', 'appointment_module')
                ->where('status', 1)
                ->get();

            $customFieldHeaders = [];
            $defaultCustomFields = [];

            foreach ($customFieldDefs as $def) {
                // Safely handle json fields
                $fieldMeta = is_string($def->fields) ? json_decode($def->fields, true) : $def->fields;

                if (isset($fieldMeta['label']) && !empty($fieldMeta['label'])) {
                    $label = $fieldMeta['label'];
                    $customFieldHeaders[$def->id] = $label;
                    $defaultCustomFields[$label] = '-';
                }
            }

            // Fetch custom field data for these appointments
            $allCustomData = collect();
            if (!empty($appointmentIds) && !empty($customFieldHeaders)) {
                $allCustomData = \App\models\KCCustomFieldData::query()
                    ->where('module_type', 'appointment_module')
                    ->whereIn('module_id', $appointmentIds)
                    ->whereIn('field_id', array_keys($customFieldHeaders))
                    ->get();
            }

            // Group data by appointment ID
            $groupedCustomData = $allCustomData->groupBy('moduleId');

            // Process results for export
            $exportData = [];
            foreach ($results as $appointment) {
                // Parse basic data
                $patientBasicData = !empty($appointment->patient_basic_data) ? json_decode($appointment->patient_basic_data, true) : [];
                $doctorBasicData = !empty($appointment->doctor_basic_data) ? json_decode($appointment->doctor_basic_data, true) : [];

                // Get profile image URLs
                $patientProfileImageUrl = '';
                if (!empty($appointment->patient_profile_image)) {
                    $patientProfileImageUrl = wp_get_attachment_url($appointment->patient_profile_image);
                }

                // Build clinic full address
                $clinicAddressParts = [];
                if (!empty($appointment->clinic_address))
                    $clinicAddressParts[] = $appointment->clinic_address;
                if (!empty($appointment->clinic_city))
                    $clinicAddressParts[] = $appointment->clinic_city;
                if (!empty($appointment->clinic_state))
                    $clinicAddressParts[] = $appointment->clinic_state;
                if (!empty($appointment->clinic_country))
                    $clinicAddressParts[] = $appointment->clinic_country;
                if (!empty($appointment->clinic_postal_code))
                    $clinicAddressParts[] = $appointment->clinic_postal_code;
                $clinicFullAddress = !empty($clinicAddressParts) ? implode(', ', $clinicAddressParts) : '-';

                // Get patient contact number
                $patientContactNo = isset($patientBasicData['mobile_number']) ? $patientBasicData['mobile_number'] : '-';

                // Get appointment services
                $services = KCAppointmentServiceMapping::table('asm')
                    ->select(['s.name', 'sdm.charges as service_charges'])
                    ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
                    ->leftJoin(KCServiceDoctorMapping::class, 'sdm.service_id', '=', 's.id', 'sdm')
                    ->where('asm.appointment_id', '=', $appointment->id)
                    ->get();

                $serviceNames = $services->pluck('name')->toArray();
                $serviceCharges = $services->pluck('service_charges')->toArray();
                $totalCharges = array_sum($serviceCharges);

                // Format appointment date
                $appointmentFormattedStartDate = !empty($appointment->appointmentStartDate) ? gmdate('Y-m-d', strtotime($appointment->appointmentStartDate)) : '#';

                // Get status text
                $statusMap = [
                    0 => 'Cancelled',
                    1 => 'Booked',
                    2 => 'Pending',
                    3 => 'Check-Out',
                    4 => 'Check-In',

                ];
                $statusText = $statusMap[$appointment->status] ?? 'Unknown';

                // Get reports (encounters)
                $reports = $appointment->encounter_id ? 'Yes' : 'No';

                // Get custom forms (placeholder - would need actual implementation)
                $customForms = ''; // This would need to be implemented based on your custom forms structure

                // Calculate cancellation buffer (placeholder - would need actual business logic)
                $cancellationBuffer = ''; // This would need to be implemented based on your cancellation policy

                // CUSTOM FIELDS ROW PROCESSING
                $appointmentCustomData = $defaultCustomFields;
                if (isset($groupedCustomData[$appointment->id])) {
                    foreach ($groupedCustomData[$appointment->id] as $customData) {
                        $header = $customFieldHeaders[$customData->fieldId] ?? null;
                        if ($header) {
                            $value = $customData->fieldsData;
                            $decodedValue = json_decode($value, true);

                            // Handle arrays (from multi-select, etc.) by joining them
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedValue)) {
                                if (isset($decodedValue['name'])) {
                                    $appointmentCustomData[$header] = $decodedValue['name'];
                                } else if (!empty($decodedValue)) {
                                    $appointmentCustomData[$header] = implode(', ', $decodedValue);
                                } else {
                                    $appointmentCustomData[$header] = '-';
                                }
                            } else {
                                $appointmentCustomData[$header] = $value ?: '-';
                            }
                        }
                    }
                }

                // Create standard data array
                $standardData = [
                    'id' => $appointment->id,
                    'date' => gmdate('Y-m-d', strtotime($appointment->appointmentStartDate)) ?: '-',
                    'time' => gmdate('h:i A', strtotime($appointment->appointmentStartTime)) ?: '-',
                    'description' => $appointment->description ?: '-',
                    'status' => $statusText,
                    'created_at' => $appointment->created_at ? gmdate('Y-m-d H:i:s', strtotime($appointment->created_at)) : '-',
                    'reports' => $reports,
                    'doctor_name' => $appointment->doctor_name ?: '-',
                    'patient_name' => $appointment->patient_name ?: '-',
                    'clinic_full_address' => $clinicFullAddress,
                    'clinic_name' => $appointment->clinic_name ?: '-',
                    'appointment_formated_start_date' => $appointmentFormattedStartDate,
                    'payment_mode' => $appointment->payment_mode ?: '-',
                    'cancellation_buffer' => $cancellationBuffer,
                    'patient_contact_no' => $patientContactNo,
                    'patient_image_url' => $patientProfileImageUrl ?: '-',
                    'custom_forms' => $customForms,
                    'charges' => $totalCharges ?: '-',
                    'services' => !empty($serviceNames) ? implode(', ', $serviceNames) : '-',
                    'service_array' => !empty($serviceNames) ? $serviceNames : [],
                ];

                // Merge and append
                $exportData[] = array_merge($standardData, $appointmentCustomData);
            }

            return $this->response(
                ['appointments' => $exportData],
                __('Appointments data retrieved successfully', 'kivicare-clinic-management-system'),
                true,
                200
            );

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to export appointments data', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }

    /**
     * Bulk delete appointments
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function bulkDeleteAppointments(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $ids = $request->get_param('ids');

            $success_count = 0;
            $failed_count = 0;
            $failed_ids = [];

            foreach ($ids as $id) {
                $appointment = KCAppointment::find($id);

                if (!$appointment) {
                    $failed_count++;
                    $failed_ids[] = [
                        'id' => $id,
                        'reason' => __('Appointment not found', 'kivicare-clinic-management-system')
                    ];
                    continue;
                }

                try {
                    // Delete related data
                    $bill = KCBill::query()->where('appointment_id', $id)->first();

                    KCAppointmentServiceMapping::query()->where('appointment_id', $id)->delete();
                    KCPatientEncounter::query()->where('appointment_id', $id)->delete();
                    KCBill::query()->where('appointment_id', $id)->delete();
                    if ($bill) {
                        KCBillItem::query()->where('bill_id', $bill->id)->delete();
                    }
                    KCPaymentsAppointmentMapping::query()->where('appointment_id', $id)->delete();

                    // Delete custom field data

                    KCCustomFieldData::query()
                        ->where('module_type', 'appointment_module')
                        ->where('module_id', $id)
                        ->delete();


                    // Cancel telemed meeting if exists
                    if ($telemed_provider = KCTelemedFactory::get_provider_by_doctor_id($appointment->doctorId)) {
                        $telemed_provider?->cancel_meeting_by_appointment($id);
                    }

                    // Delete the appointment
                    $result = $appointment->delete();

                    if (!$result) {
                        $failed_count++;
                        $failed_ids[] = [
                            'id' => $id,
                            'reason' => __('Failed to delete appointment', 'kivicare-clinic-management-system')
                        ];
                        continue;
                    }

                    do_action('kc_appointment_deleted', $id, $appointment);
                    $success_count++;
                } catch (\Exception $e) {
                    $failed_count++;
                    $failed_ids[] = [
                        'id' => $id,
                        'reason' => $e->getMessage()
                    ];
                }
            }

            if ($success_count > 0) {
                return $this->response(
                    [
                        'success_count' => $success_count,
                        'failed_count' => $failed_count,
                        'failed_ids' => $failed_ids
                    ],
                    /* translators: %d: number of appointments */
                    sprintf(__('%d appointment has been deleted.', 'kivicare-clinic-management-system'), $success_count),
                    true,
                    200
                );
            } else {
                return $this->response(
                    [
                        'success_count' => 0,
                        'failed_count' => $failed_count,
                        'failed_ids' => $failed_ids
                    ],
                    __('Failed to delete appointments', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }
        } catch (\Exception $e) {
            return $this->response(null, $e->getMessage(), false, 500);
        }
    }

    /**
     * Create Bill for appointment
     * 
     * @param int $appointmentId
     * @param int $clinicId
     * @param int $encounterId
     * @param \Illuminate\Support\Collection|array $services
     * @return KCBill
     */
    public function createBill($appointmentId, $clinicId, $encounterId, $services)
    {
        // Check if bill already exists
        $existingBill = KCBill::query()->where('appointment_id', $appointmentId)->first();
        if ($existingBill) {
            return $existingBill;
        }

        // Create bill
        $bill = new KCBill();
        $bill->encounterId = $encounterId;
        $bill->clinicId = $clinicId;
        $bill->appointmentId = $appointmentId;
        $bill->status = 0;
        $paymentStatus = KCPaymentsAppointmentMapping::getPaymentStatusByAppointmentId($appointmentId);
        $bill->paymentStatus = (in_array(strtolower($paymentStatus), ['paid', 'completed'])) ? 'paid' : 'unpaid';
        $bill->totalAmount = 0; // Will be updated
        $bill->discount = 0;
        $bill->actualAmount = 0; // Will be updated
        $bill->createdAt = current_time('mysql');
        $bill->save();

        $totalAmount = 0;

        // Create bill items for each service
        foreach ($services as $service) {
            // Handle different object structures (collection of models vs stdClass from joins)
            $serviceId = $service->serviceId ?: $service->service_id ?? $service->id;

            // For charges, prioritize specific property, fallback to model default if needed
            $price = $service->charges ?? $service->price ?? 0;

            if ($serviceId) {
                $billItem = new KCBillItem();
                $billItem->billId = $bill->id;
                $billItem->itemId = $serviceId;
                $billItem->qty = 1;
                $billItem->price = $price;
                $billItem->createdAt = current_time('mysql');
                $billItem->save();

                $totalAmount += (float) $price;
            }
        }

        // Update bill with totals
        $bill->totalAmount = $totalAmount;
        $bill->actualAmount = $totalAmount;
        $bill->save();

        return $bill;
    }

    /**
     * Get arguments for bulk action endpoints
     *
     * @return array
     */
    private function getBulkActionEndpointArgs()
    {
        return [
            'ids' => [
                'description' => 'Array of appointment IDs',
                'type' => 'array',
                'items' => [
                    'type' => 'integer',
                ],
                'required' => true,
                'validate_callback' => function ($param) {
                    if (!is_array($param) || empty($param)) {
                        return new WP_Error('invalid_ids', __('Appointment IDs are required', 'kivicare-clinic-management-system'));
                    }
                    foreach ($param as $id) {
                        if (!is_numeric($id) || intval($id) <= 0) {
                            return new WP_Error('invalid_id', __('Invalid appointment ID in array', 'kivicare-clinic-management-system'));
                        }
                    }
                    return true;
                }
            ]
        ];
    }
}
