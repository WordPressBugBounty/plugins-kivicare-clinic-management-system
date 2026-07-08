<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\models\KCAppointment;
use App\models\KCClinic;
use App\models\KCAppointmentServiceMapping;
use App\models\KCServiceDoctorMapping;
use App\models\KCService;
use App\models\KCPaymentsAppointmentMapping;
use App\models\KCPatientEncounter;
use App\models\KCBill;
use WP_REST_Request;
use WP_REST_Response;
use App\utils\KCPdfGenerator;

defined('ABSPATH') or die('Something went wrong');

class KCPrintInvoiceController extends KCBaseController
{
    protected $route = 'appointments';

    public function registerRoutes()
    {
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)/print-invoice', [
            'methods' => 'GET',
            'callback' => [$this, 'print'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'id' => [
                    'description' => __('Appointment ID', 'kivicare-clinic-management-system'),
                    'type' => 'integer',
                    'required' => true,
                ],
            ]
        ]);
    }

    public function print(WP_REST_Request $request)
    {
        try {
            $appointment_id = $request->get_param('id');

            if (empty($appointment_id)) {
                return $this->response(
                    false,
                    __('Appointment ID is required', 'kivicare-clinic-management-system'),
                    400
                );
            }

            $appointment = KCAppointment::find($appointment_id);

            if (!$appointment) {
                return $this->response(
                    false,
                    __('Appointment not found', 'kivicare-clinic-management-system'),
                    404
                );
            }

            $appointment_data = $this->prepare_printable_appointment($appointment);
            $html = $this->render_print_template($appointment_data);
            $filename = 'invoice_' . $appointment_id . '_' . current_time('timestamp') . '.pdf';
            
            //// FIX: Prevent PDF generation fatal errors (black screen on download) while maintaining complex language translations (like Gujarati).
            // mPDF 7+ removed the 'UnBatang_0613.ttf' (Korean) font from its core package to save space. 
            // When autoLangToFont is enabled, mPDF sometimes falsely detects a Korean character in the invoice data 
            // and crashes trying to load this missing font. We cannot disable autoLangToFont entirely because 
            // complex languages (like Gujarati) require it to render properly.
            // Instead, we map the 'unbatang' font key to 'freeserif' (a font that IS included and supports many scripts). 
            // This allows mPDF to bypass the missing font bug without crashing, while Gujarati translates and renders correctly.

            $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];
            
            // Map the missing Korean font to freeserif to prevent fatal errors
            // when mPDF falsely detects Korean characters.
            $fontData['unbatang'] = $fontData['freeserif'];

            $config = [
                'autoLangToFont' => true,
                'autoScriptToLang' => true,
                'fontdata' => $fontData
            ];
            
            KCPdfGenerator::generate($html, $filename, 'I', $config);

        } catch (\Exception $e) {
            return $this->response(
                false,
                $e->getMessage(),
                500
            );
        }
    }



    private function prepare_printable_appointment(KCAppointment $appointment): array
    {
        $patient = get_userdata($appointment->patientId);
        $patient_meta = json_decode(get_user_meta($appointment->patientId, 'basic_data', true) ?: '{}', true);
        $doctor = get_userdata($appointment->doctorId);
        $clinic = KCClinic::find($appointment->clinicId);
        $doctor_meta = $doctor ? json_decode(get_user_meta($doctor->ID, 'basic_data', true) ?: '{}', true) : null;
        $clinicLogo = [
            'id'  => $clinic->clinicLogo,
            'url' => $clinic->clinicLogo ? wp_get_attachment_url($clinic->clinicLogo) : '',
        ];

        $services = KCAppointmentServiceMapping::table('asm')
            ->select(['s.name', 'dsm.charges'])
            ->leftJoin(KCService::class, 'asm.service_id', '=', 's.id', 's')
            ->leftJoin(KCServiceDoctorMapping::class, 'dsm.service_id', '=', 's.id', 'dsm')
            ->where('asm.appointment_id', $appointment->id)
            ->where('dsm.doctor_id', $appointment->doctorId)
            ->where('dsm.clinic_id', $appointment->clinicId)
            ->get()
            ->map(function($service) {
                return [
                    'name' => $service->name,
                    'charges' => (float)$service->charges
                ];
            })
            ->toArray();

        $paymentInfo = KCPaymentsAppointmentMapping::query()
            ->where('appointment_id', $appointment->id)
            ->first();

        $total_charges = array_sum(array_column($services, 'charges'));
        $appointmentReport = [];
        if ($appointment->appointmentReport) {
            $reportIds = json_decode($appointment->appointmentReport, true);
            if (is_array($reportIds)) {
                $reports = [];
                foreach ($reportIds as $id) {
                    $url = wp_get_attachment_url((int)$id);
                    $filename = get_the_title($id);
                    $reports[] = [
                        'id' => $id,
                        'url' => $url,
                        'filename' => $filename
                    ];
                }
                $appointmentReport = $reports;
            }
        }
        
        $currency = KCClinic::getClinicCurrencyPrefixAndPostfix();



        $paymentStatus = KCPaymentsAppointmentMapping::getPaymentStatusByAppointmentId($appointment->id);

        if (strtolower($paymentStatus) !== 'paid' && strtolower($paymentStatus) !== 'completed') {
             $encounter = KCPatientEncounter::query()->where('appointment_id', $appointment->id)->first();
             if ($encounter) {
                 $bill = KCBill::query()->where('encounter_id', $encounter->id)->first();
                 if ($bill && $bill->paymentStatus === 'paid') {
                     $paymentStatus = __('Paid', 'kivicare-clinic-management-system');
                 }
             }
        }

        return [
            'currency_prefix' => $currency['prefix'],
            'currency_postfix' => $currency['postfix'],
            'appointment' => [
                'id' => $appointment->id,
                'appointmentStartDate' => kcGetFormatedDate($appointment->appointmentStartDate),
                'appointmentStartTime' => kcGetFormatedTime($appointment->appointmentStartTime),

                'paymentMode' => $paymentInfo->payment_mode ?? 'Manual',
                'paymentStatus' => $paymentStatus
            ],
            'patient' => $patient ? [
                'name' => $patient->display_name,
                'email' => $patient->user_email,
                'address' => $patient_meta['address'] ?? '',
                'city' => $patient_meta['city'] ?? '',
                'country' => $patient_meta['country'] ?? '',
                'postal_code' => $patient_meta['postal_code'] ?? '',
                'phone' => $patient_meta['mobile_number'] ?? '',
                'gender' => $patient_meta['gender'] ?? '',
                'age' => isset($patient_meta['dob']) ? date_diff(date_create($patient_meta['dob']), date_create('today'))->y : '',
                'id' => $patient->ID
            ] : null,
            'doctor' => $doctor ? [
                'name' => $doctor->display_name,
                'signature' => $doctor->getMeta('doctor_signature'),
                'email' => $doctor->user_email,
                'specialization' => $doctor_meta && !empty($doctor_meta['specialties']) ? $doctor_meta['specialties'][0]['label'] : '',
            ] : null,
            'clinic' => $clinic ? [
                'name' => $clinic->name,
                'address' => $clinic->address,
                'city' => $clinic->city ?? '',
                'country' => $clinic->country ?? '',
                'postal_code' => $clinic->postalCode ?? '',
                'phone' => $clinic->telephoneNo ?? '',
                'email' => $clinic->email ?? '',
                'logo' => $clinic->clinicLogo ? wp_get_attachment_url($clinic->clinicLogo) : ''
            ] : null,
            'services' => $services,
            'clinic_logo' => $clinicLogo,
            'tax_items' => apply_filters('kivicare_get_tax_data', $appointment->id),
            'total_charges' => number_format($total_charges, 2),
            'sub_total' => $total_charges,
            'appointmentReport' => $appointmentReport
        ];
    }
    

    private function render_print_template($data): string
    {
        $template_file = $this->get_template_file();

        if (!file_exists($template_file)) {
            throw new \Exception(esc_html__('Invoice template not found', 'kivicare-clinic-management-system'));
        }

        ob_start();
        extract($data);
        include $template_file;
        return ob_get_clean();
    }

    private function get_template_file(): string
    {
        $child_theme_path = get_stylesheet_directory() . '/kivicare/KCInvoicePrintTemplate.php';
        if (file_exists($child_theme_path)) {
            return $child_theme_path;
        }

        $parent_theme_path = get_template_directory() . '/kivicare/KCInvoicePrintTemplate.php';
        if (file_exists($parent_theme_path)) {
            return $parent_theme_path;
        }

        return KIVI_CARE_DIR . 'templates/KCInvoicePrintTemplate.php';
    }
}