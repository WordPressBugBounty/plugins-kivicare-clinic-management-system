<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\models\KCBill;
use App\models\KCPatientEncounter;
use App\models\KCPatient;
use App\models\KCClinic;
use App\models\KCDoctor;
use App\models\KCUserMeta;
use App\models\KCBillItem;
use App\models\KCService;
use App\models\KCAppointment;
use App\models\KCPaymentsAppointmentMapping;
use App\models\KCServiceDoctorMapping;
use App\models\KCAppointmentServiceMapping;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') or die('Something went wrong');
/**
 * Class BillController
 * 
 * API Controller for Bill-related endpoints
 * 
 * @package App\controllers\api
 */
class BillController extends KCBaseController
{
    /**
     * @var string The base route for this controller
     */
    protected $route = 'bills';

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        $this->registerRoute('/' . $this->route . '/by-encounter/(?P<encounter_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getBillByEncounterId'],
            'permission_callback' => [$this, 'checkViewPermission'],
            'args' => [
                'encounter_id' => [
                    'description' => 'Encounter ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ]
            ]
        ]);

        // Get single bill
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getBill'],
            'permission_callback' => [$this, 'checkViewPermission'],
            'args' => $this->getSingleEndpointArgs() // You might need to add this method if it's used
        ]);

         // Create bill
        $this->registerRoute('/' . $this->route, [
            'methods' => 'POST',
            'callback' => [$this, 'createBill'],
            'permission_callback' => [$this, 'checkCreatePermission'],
            'args' => $this->getCreateEndpointArgs()
        ]);

        // Update bill
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateBill'],
            'permission_callback' => [$this, 'checkCreatePermission'],
            'args' => $this->getUpdateEndpointArgs()
        ]);

    }

    
    /**
     * Check if user has permission to create a bill
     */
    public function checkCreatePermission($request)
    {
        if (!$this->isModuleEnabled('billing')) { return false; }
        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Check if user has permission to create a bill
        return $this->checkResourceAccess('patient_bill', 'add');
    }

    /**
     * Get arguments for the create endpoint
     */
    private function getCreateEndpointArgs()
    {
        return [
            'serviceItems' => [
                'description' => 'Array of bill service items',
                'type' => 'array',
                'required' => true,
            ],
            'taxItems' => [
                'description' => 'Array of tax items',
                'type' => 'array',
                'required' => false,
            ],
            'discount' => [
                'description' => 'Discount value',
                'type' => 'number',
                'required' => false,
            ],
            'discountEnabled' => [
                'description' => 'Is discount enabled',
                'type' => 'boolean',
                'required' => false,
            ],
            'status' => [
                'description' => 'Bill status',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'clinic' => [
                'description' => 'Clinic info',
                'type' => 'object',
                'required' => true,
            ],
            'doctor' => [
                'description' => 'Doctor info',
                'type' => 'object',
                'required' => true,
            ],
            'patient' => [
                'description' => 'Patient info',
                'type' => 'object',
                'required' => true,
            ],
            'patientEncounter' => [
                'description' => 'Patient encounter info',
                'type' => 'object',
                'required' => true,
            ],
            'service_total' => [
                'description' => 'Total of all services',
                'type' => 'number',
                'required' => true,
            ],
            'taxTotal' => [
                'description' => 'Total tax amount',
                'type' => 'number',
                'required' => false,
            ],
            'discountValue' => [
                'description' => 'Discount value (for display)',
                'type' => 'number',
                'required' => false,
            ],
            'total_amount' => [
                'description' => 'Total payable amount',
                'type' => 'number',
                'required' => true,
            ],
        ];
    }

    /**
     * Get arguments for the update endpoint
     */
    private function getUpdateEndpointArgs()
    {
        $args = $this->getCreateEndpointArgs();
        foreach ($args as $key => $arg) {
            if (isset($args[$key]['required'])) {
                unset($args[$key]['required']);
            }
        }
        $args['id'] = [
            'description' => 'Bill ID',
            'type' => 'integer',
            'required' => true,
            'sanitize_callback' => 'absint',
        ];
        $args['checkout'] = [
            'description' => 'Whether to checkout the encounter after updating the bill',
            'type' => 'boolean',
            'required' => false,
        ];
        return $args;
    }

    /**
     * Get arguments for single item endpoints
     */
    private function getSingleEndpointArgs()
    {
        return [
            'id' => [
                'description' => 'Bill ID',
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint',
            ]
        ];
    }

    public function checkViewPermission()
    {
        if (!$this->isModuleEnabled('billing')) {
            return false;
        }
        // Check if user has permission to list
        return $this->checkResourceAccess('patient_bill', 'view');
    }
    
    public function checkPermission($request)
    {
        if (!$this->isModuleEnabled('billing')) { return false; }
        return current_user_can('read');
    }

    public function getBillByEncounterId(WP_REST_Request $request): WP_REST_Response
    {
        $encounter_id = $request->get_param('encounter_id');
        if (!$encounter_id) {
            return $this->response(null, __('Encounter ID is required', 'kivicare-clinic-management-system'), false, 400);
        }

        $bill = KCBill::query()->where('encounter_id', $encounter_id)->first();

        if (!$bill) {

            // if bill is not found then send clinic and doctor id for select service.
            $encounter = KCPatientEncounter::query()
                ->select(['clinic_id', 'doctor_id', 'patient_id', 'appointment_id', 'id'])
                ->where('id', $encounter_id)
                ->first();
            $encounterData = [
                'clinic' => [
                    'id' => (int) $encounter->clinicId,
                ],
                'patient' => [
                    'id' => (int) $encounter->patientId,
                ],
                'patientEncounter' => [
                    'id' => (int) $encounter->id,
                    'appointmentId' => (int) $encounter->appointmentId,
                ],
                'doctor' => [
                    'id' => (int) $encounter->doctorId,
                ],
                'serviceItems' => [],
                'status' => 'unpaid'
            ];

            return $this->response($encounterData, __('Bill not found for this encounter', 'kivicare-clinic-management-system'), true, 200);
        }

        // Optionally, you can reuse the formatting logic from getBill()
        $request->set_param('id', $bill->id);
        return $this->getBill($request);
    }

    /**
     * Get single bill by ID
     */
    public function getBill(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');

        $query = KCBill::table('bills')
            ->select([
                'bills.*',
                'patients.display_name as patient_name',
                'patients.user_email as patient_email',
                'pi.meta_value as patient_profile_image',
                'clinics.name as clinic_name',
                'clinics.email as clinic_email',
                'clinics.id as clinic_id',
                'clinics.profile_image as clinic_profile_image',
                'doctors.id as doctor_id',
                'doctors.display_name as doctor_name',
                'doctors.user_email as doctor_email',
                'di.meta_value as doctor_profile_image',
                'pe.id as encounterId',
                'pe.appointment_id as appointmentId',
                'pe.status as encounter_status',
                'doctor_basic_data.meta_value as doctor_basic_data',
                'patient_basic_data.meta_value as patient_basic_data',
                'clinics.address as clinic_address',
                'clinics.telephone_no as telephoneNo',
            ])
            ->leftJoin(KCPatientEncounter::class, 'bills.encounter_id', '=', 'pe.id', 'pe')
            ->leftJoin(KCPatient::class, 'pe.patient_id', '=', 'patients.id', 'patients')
            ->leftJoin(KCUserMeta::class, function ($join) {
                $join->on('patients.ID', '=', 'pi.user_id')
                    ->onRaw("pi.meta_key = 'patient_profile_image'");
            }, null, null, 'pi')
            ->leftJoin(KCClinic::class, 'pe.clinic_id', '=', 'clinics.id', 'clinics')
            ->leftJoin(KCDoctor::class, 'pe.doctor_id', '=', 'doctors.id', 'doctors')
            ->leftJoin(KCUserMeta::class, function ($join) {
                $join->on('doctors.ID', '=', 'di.user_id')
                    ->onRaw("di.meta_key = 'doctor_profile_image'");
            }, null, null, 'di')
            ->leftJoin(KCUserMeta::class, function ($join) {
                $join->on('patients.ID', '=', 'patient_basic_data.user_id')
                    ->onRaw("patient_basic_data.meta_key = 'basic_data'");
            }, null, null, 'patient_basic_data')
            ->leftJoin(KCUserMeta::class, function ($join) {
                $join->on('doctors.ID', '=', 'doctor_basic_data.user_id')
                    ->onRaw("doctor_basic_data.meta_key = 'basic_data'");
            }, null, null, 'doctor_basic_data')
            ->where('bills.id', '=', $id);

        $bill = $query->first();
        if ($bill) {
            $billItems = KCBillItem::query()
                ->where('bill_id', $bill->id)
                ->get();

            // Check if appointment has payment
            $isPaidAppointment = false;
            $paymentMode = '';
            if ($bill->appointmentId && $bill->appointmentId > 0) {
                $payment = KCPaymentsAppointmentMapping::query()
                    ->where('appointmentId', $bill->appointmentId)
                    ->where('paymentStatus', 'completed')
                    ->first();
                
                if ($payment) {
                    $isPaidAppointment = true;
                    // Get user-friendly payment mode label
                    $paymentMode = KCPaymentsAppointmentMapping::getPaymentModeByAppointmentId($bill->appointmentId);
                }
            }

            $serviceData = [];
            $total_service_price = 0;
            foreach ($billItems as $index => $item) {
                $service = KCService::query()
                            ->select(['services.*', 'sdm.image as service_image'])
                            ->setTableAlias('services')
                            ->leftJoin(KCServiceDoctorMapping::class, 'services.id', '=', 'sdm.service_id', 'sdm')
                            ->where('services.id', $item->itemId)
                            ->first();
                $qty = (int) $item->qty;
                $price = (float) $item->price;
                $total = $qty * $price;
                $serviceImage = $service->service_image ? wp_get_attachment_url($service->service_image) : '';
                
                // Determine if THIS specific service is paid:
                // 1. If the bill itself is marked as 'paid', then all services are paid
                // 2. If the bill is 'unpaid' or 'pending', check if this service was part of the original appointment
                $isServicePaid = false;
                
                if ($bill->paymentStatus === 'paid') {
                    // If bill is paid, all services are paid
                    $isServicePaid = true;
                } elseif ($isPaidAppointment && $bill->appointmentId && $bill->appointmentId > 0) {
                    // If appointment was paid, check if this service was part of that appointment
                    $wasInAppointment = KCAppointmentServiceMapping::query()
                        ->where('appointmentId', $bill->appointmentId)
                        ->where('serviceId', $item->service_id)
                        ->first();
                    
                    $isServicePaid = (bool) $wasInAppointment;
                }
                
                $serviceData[] = [
                    'id' => $item->id,
                    'serviceId' => $item->itemId,
                    'service_name' => $service->name,
                    'service_image_url' => $serviceImage,
                    'quantity' => $qty,
                    'price' => $price,
                    'total' => $total,
                    'isPaid' => $isServicePaid,
                    'paymentMode' => $paymentMode,
                ];
                $total_service_price += $total;
            }

            $discount = $bill->discount ?? 0;
            $enableDiscount = $discount > 0 ? true : false;
            
            $total_tax = 0.0;
            $taxData = [];
            
            // Check if KiviCare Pro is active for Tax Logic
            if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
                if(class_exists('KCProApp\models\KCPTaxData')) {
                    $taxCalculator = \KCProApp\models\KCPTaxData::get_tax(['module_id' => $bill->encounterId, 'module_type' => 'encounter']);
                    foreach ($taxCalculator as $tax) {
                        $taxData[] = [
                            'id' => $tax->taxId ?? $tax->id,
                            'tax_name' => $tax->taxName ?? $tax->name,
                            'tax_type' => $tax->taxType ?? $tax->type,
                            'tax_value' => $tax->taxValue,
                            'tax_amount' => $tax->charges ?? 0,
                        ];
                        $total_tax += isset($tax->charges) ? floatval($tax->charges) : 0;
                    }
                }
            }

            // basic Data of Doctor
            $doctorBasicData = json_decode($bill->doctor_basic_data, true);

            // Patient Basic Data
            $patientBasicData = json_decode($bill->patient_basic_data, true);
            
            // Calculate amounts:
            // subTotal = service total only (no tax, no discount)
            // total_amount = service total + tax - discount (final amount)
            $subTotal = (float) $total_service_price;
            $recalculated_total_amount = (float) $total_service_price + (float) $total_tax - (float) $discount;
            
            $billData = [
                'id' => $bill->id,
                'invoiceId' => $bill->id,
                'date' => kcGetFormatedDate(gmdate('Y-m-d', strtotime($bill->createdAt))) . ' ' . kcGetFormatedTime(gmdate('H:i:s', strtotime($bill->createdAt))),
                'status' => $bill->paymentStatus,
                'encounter_status' => $bill->encounter_status,
                'patient' => [
                    'name' => $bill->patient_name,
                    'email' => $bill->patient_email,
                    'dob' => !empty($patientBasicData['dob']) ? kcGetFormatedDate($patientBasicData['dob']) : null,
                    'gender' => $patientBasicData['gender'] ?? null,
                    'patient_image_url' => $bill->patient_profile_image ? wp_get_attachment_url($bill->patient_profile_image) : '',
                ],
                'clinic' => [
                    'id' => $bill->clinic_id ?? $bill->clinicId,
                    'name' => $bill->clinic_name,
                    'email' => $bill->clinic_email,
                    'address' => $bill->clinic_address,
                    'phone' => $bill->telephoneNo,
                    'clinic_image_url' => $bill->clinic_profile_image ? wp_get_attachment_url($bill->clinic_profile_image) : '',
                ],
                'doctor' => [
                    'id' => (int) $bill->doctor_id ?? (int) $bill->doctorId,
                    'phone' => $doctorBasicData['mobile_number'] ?? null,
                    'name' => $bill->doctor_name,
                    'email' => $bill->doctor_email,
                    'doctor_image_url' => $bill->doctor_profile_image ? wp_get_attachment_url($bill->doctor_profile_image) : '',
                ],
                'patientEncounter' => [
                    'id' => (int) $bill->encounterId,
                    'appointmentId' => (int) $bill->appointmentId,
                ],
                'actual_amount' => (float) $bill->actualAmount,
                'serviceItems' => $serviceData,
                'service_total' => (float) $total_service_price,
                'discountEnabled' => $enableDiscount,
                'subTotal' => (float) $subTotal,
                'discount' => (float) $discount,
                'total_amount' => (float) $recalculated_total_amount,
                'totalTax' => (float) $total_tax,
                'taxItems' => $taxData
            ];
            return $this->response($billData, __('Bill retrieved successfully', 'kivicare-clinic-management-system'));
        }
        return $this->response(null, __('Bill not found', 'kivicare-clinic-management-system'), false, 404);
    }

    /**
     * Create bill
     */
    public function createBill(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $params = $request->get_params();
        $encounterId = $params['patientEncounter']['id'] ?? null;

        // Check if bill already exists for this encounter
        if ($encounterId) {
            $existingBill = KCBill::query()->where('encounter_id', $encounterId)->first();
            if ($existingBill) {
                return $this->response(
                    ['id' => $existingBill->id],
                    __('A bill already exists for this encounter.', 'kivicare-clinic-management-system'),
                    false,
                    409 // HTTP 409 Conflict
                );
            }
        }

        $encounter = KCPatientEncounter::find($encounterId);
        if (!$encounter) {
            return $this->response(null, __('Encounter not found', 'kivicare-clinic-management-system'), false, 404);
        }

        // Determine clinic ID based on user role
        $current_user_role = $this->kcbase->getLoginUserRole();
        if (isKiviCareProActive()) {
            if ($current_user_role == $this->kcbase->getClinicAdminRole()) {
                $clinic_id = KCClinic::getClinicIdOfClinicAdmin();
            } elseif ($current_user_role == $this->kcbase->getReceptionistRole()) {
                $clinic_id = KCClinic::getClinicIdOfReceptionist();
            } else {
                $clinic_id = $params['clinic']['id'];
            }
        } else {
            // Default clinic id if pro not active
            $clinic_id = KCClinic::kcGetDefaultClinicId();
        }

        if ($current_user_role == $this->kcbase->getDoctorRole()) {
            $doctor_id = get_current_user_id();
        } else {
            $doctor_id = $params['doctor']['id'];
        }
        $wpdb->query('START TRANSACTION');

        $bill = new KCBIll();
        $bill->totalAmount = (float) $params['service_total'] + (float) $params['taxTotal'];
        $bill->actualAmount = (float) $params['total_amount'];
        $bill->encounterId = (int) $encounter->id;
        $bill->discount = (float) $params['discount'];
        $bill->clinicId = (int) $clinic_id;
        $bill->status = '0';
        $bill->createdAt = current_time('mysql');
        $bill->paymentStatus = $params['status'];


        $result = $bill->save();
        if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
            if(class_exists('\KCProApp\models\KCPTaxData')) {
                \KCProApp\models\KCPTaxData::table('td')
                    ->where('module_id', '=', (int) $encounter->id)
                    ->where('module_type', '=', 'encounter')
                    ->delete();
                if (!empty($params['taxItems']) && is_array($params['taxItems'])) {
                    foreach ($params['taxItems'] as $taxItem) {
                        $taxData = new \KCProApp\models\KCPTaxData();
                        $taxData->moduleId = (int) $encounter->id;
                        $taxData->moduleType = 'encounter';
                        $taxData->name = $taxItem['tax_name'] ?? '';
                        $taxData->taxType = $taxItem['tax_type'] ?? '';
                        $taxData->charges = (float) $taxItem['tax_amount'] ?? 0;
                        $taxData->taxValue = (float) $taxItem['tax_value'] ?? 0;
                        $taxData->save();
                    }
                }
            }
        }


        if (!empty($params['serviceItems']) && is_array($params['serviceItems'])) {
            foreach ($params['serviceItems'] as $item) {
                // Check if item already exists
                $checkExistingService = KCService::find($item['serviceId']);
                if (!$checkExistingService) {
                    $serviceData = new KCService();
                    $serviceData->name = $item['name'];
                    $serviceData->type = 'bill_service';
                    $serviceData->price = (float) $item['price'];
                    $serviceData->status = 1;
                    $serviceData->createdAt = current_time('mysql');
                    $service_id = $serviceData->save();
                    $item['serviceId'] = $service_id;
                    if ($service_id) {
                        KCServiceDoctorMapping::create([
                            'serviceId' => $service_id,
                            'doctorId' => $doctor_id,
                            'clinicId' => $clinic_id,
                            'charges' => $item['price'],
                            'telemedService' => 'no',
                            'createdAt' => current_time('mysql')
                        ]);

                        // Don't add to appointment service mapping - services added during bill creation
                        // should not be associated with the original appointment payment
                    }
                }
                $billItem = null;
                if (!empty($item['id'])) {
                    $billItem = KCBillItem::find($item['id']);
                }
                if (!$billItem) {
                    $billItem = new KCBillItem();
                    $billItem->createdAt = current_time('mysql');
                }
                $billItem->billId = $bill->id;
                $billItem->itemId = (int) $item['serviceId'];
                $billItem->qty = (int) $item['quantity'];
                $billItem->price = (float) $item['price'];
                $billItem->save();
            }
        }

        if (!empty($params['status']) && $params['status'] === 'paid') {
            KCPatientEncounter::query()
                ->where('id', $encounter->id)
                ->update([
                    'status' => '0',
                    'paymentStatus' => 'paid',
                    'updatedAt' => current_time('mysql')
                ]);

            // If the bill is paid, update the appointment status if applicable
            KCAppointment::query()
                ->where('id', (int) $params['patientEncounter']['appointmentId'])
                ->update([
                    'status' => '3',
                    'updatedAt' => current_time('mysql')
                ]);
        } else {
            KCPatientEncounter::query()
                ->where('id', $encounter->id)
                ->update([
                    'status' => '1',
                    'updatedAt' => current_time('mysql')
                ]);
        }
        if ($result) {
            $wpdb->query('COMMIT');
            return $this->response(['id' => $bill->id], __('Bill created successfully', 'kivicare-clinic-management-system'));
        }
        $wpdb->query('ROLLBACK');
        return $this->response(null, __('Failed to create bill', 'kivicare-clinic-management-system'), false, 500);
    }

    /**
     * Update bill
     */
    public function updateBill(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $id = $request->get_param('id');
        $params = $request->get_params();
        $encounter = KCPatientEncounter::find($params['patientEncounter']['id']);

        if (!$encounter) {
            return $this->response(null, __('Encounter not found', 'kivicare-clinic-management-system'), false, 404);
        }

        // Determine clinic ID based on user role
        $current_user_role = $this->kcbase->getLoginUserRole();
        if (isKiviCareProActive()) {
            if ($current_user_role == $this->kcbase->getClinicAdminRole()) {
                $clinic_id = KCClinic::getClinicIdOfClinicAdmin();
            } elseif ($current_user_role == $this->kcbase->getReceptionistRole()) {
                $clinic_id = KCClinic::getClinicIdOfReceptionist();
            } else {
                $clinic_id = $params['clinic']['id'];
            }
        } else {
            // Default clinic id if pro not active
            $clinic_id = KCClinic::kcGetDefaultClinicId();
        }

        if ($current_user_role == $this->kcbase->getDoctorRole()) {
            $doctor_id = get_current_user_id();
        } else {
            $doctor_id = $params['doctor']['id'];
        }
        $wpdb->query('START TRANSACTION');

        $bill = KCBIll::find($id);
        $bill->totalAmount = (float) $params['service_total'] + (float) $params['taxTotal'];
        $bill->actualAmount = (float) $params['total_amount'];
        $bill->discount = (float) $params['discount'];
        $bill->clinicId = (int) $clinic_id;
        $bill->paymentStatus = $params['status'];

        $result = $bill->save();
        if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
            if(class_exists('\KCProApp\models\KCPTaxData')) {
                \KCProApp\models\KCPTaxData::table('td')
                    ->where('moduleId', (int) $encounter->id)
                    ->where('moduleType', 'encounter')
                    ->delete();
                if (!empty($params['taxItems']) && is_array($params['taxItems'])) {
                    foreach ($params['taxItems'] as $taxItem) {
                        $taxData = new \KCProApp\models\KCPTaxData();
                        $taxData->moduleId = (int) $encounter->id;
                        $taxData->moduleType = 'encounter';
                        $taxData->name = $taxItem['tax_name'] ?? '';
                        $taxData->taxType = $taxItem['tax_type'] ?? '';
                        $taxData->charges = (float) $taxItem['tax_amount'] ?? 0;
                        $taxData->taxValue = (float) $taxItem['tax_value'] ?? 0;
                        $taxData->save();
                    }
                }
            }
        }


        if (!empty($params['serviceItems']) && is_array($params['serviceItems'])) {
            foreach ($params['serviceItems'] as $item) {
                // Check if item already exists
                $checkExistingService = KCService::query()->where('name', strtolower($item['name']))->first();
                if (!$checkExistingService) {
                    $serviceData = new KCService();
                    $serviceData->name = $item['name'];
                    $serviceData->type = 'bill_service';
                    $serviceData->price = (float) $item['price'];
                    $serviceData->status = 1;
                    $serviceData->createdAt = current_time('mysql');
                    $service_id = $serviceData->save();
                    $item['serviceId'] = $service_id;
                    if ($service_id) {
                        KCServiceDoctorMapping::create([
                            'serviceId' => $service_id,
                            'doctorId' => $doctor_id,
                            'clinicId' => $clinic_id,
                            'charges' => $item['price'],
                            'telemedService' => 'no',
                            'createdAt' => current_time('mysql')
                        ]);

                        // Don't add to appointment service mapping - services added during bill editing
                        // should not be associated with the original appointment payment
                    }
                    if(isset($params['patientEncounter']['appointmentId']) && !empty($params['patientEncounter']['appointmentId'])){
                        $appointment_services = KCAppointmentServiceMapping::query()
                            ->where('appointmentId', (int) $params['patientEncounter']['appointmentId'])
                            ->where('serviceId', $checkExistingService->id)
                            ->first();
                        if(!$appointment_services){
                            KCAppointmentServiceMapping::create([
                                'appointmentId' => (int) $params['patientEncounter']['appointmentId'],
                                'serviceId' => $checkExistingService->id,
                                'createdAt' => current_time('mysql')
                            ]);
                        }
                    }
                }
                $billItem = null;
                if (!empty($item['id'])) {
                    $billItem = KCBillItem::find((int) $item['id']);
                }
                if (!$billItem) {
                    $billItem = new KCBillItem();
                    $billItem->createdAt = current_time('mysql');
                }
                $billItem->billId = $id;
                $billItem->itemId = (int) $item['serviceId'];
                $billItem->qty = (int) $item['quantity'];
                $billItem->price = (float) $item['price'];
                $billItem->save();
            }
        }

        if (!empty($params['status']) && $params['status'] === 'paid') {
            KCPatientEncounter::query()
                ->where('id', $encounter->id)
                ->update([
                    'status' => '0',
                    'paymentStatus' => 'paid',
                    'updatedAt' => current_time('mysql')
                ]);

            // If checkout is true, update appointment status to 3 (completed)
            if (!empty($params['checkout']) && $params['checkout']) {
                KCAppointment::query()
                    ->where('id', (int) $params['patientEncounter']['appointmentId'])
                    ->update([
                        'status' => '3',
                        'updatedAt' => current_time('mysql')
                    ]);
            }
        } else {
            KCPatientEncounter::query()
                ->where('id', $encounter->id)
                ->update([
                    'status' => '1',
                    'updatedAt' => current_time('mysql')
                ]);
        }
        if ($result) {
            $wpdb->query('COMMIT');
            return $this->response(['id' => $id], __('Bill updated successfully', 'kivicare-clinic-management-system'));
        }
        $wpdb->query('ROLLBACK');
        return $this->response(null, __('Failed to update bill', 'kivicare-clinic-management-system'), false, 500);
    }
}
