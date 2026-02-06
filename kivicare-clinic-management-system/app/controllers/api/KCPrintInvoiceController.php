<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\models\KCAppointment;
use App\models\KCClinic;
use App\models\KCAppointmentServiceMapping;
use App\models\KCServiceDoctorMapping;
use App\models\KCService;
use App\models\KCPaymentsAppointmentMapping;
use WP_REST_Request;
use WP_REST_Response;
use Dompdf\Dompdf;
use Dompdf\Options;

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
            $this->output_pdf($html, $appointment_id);
            exit;

        } catch (\Exception $e) {
            return $this->response(
                false,
                $e->getMessage(),
                500
            );
        }
    }

    private function output_pdf($html, $appointment_id): void
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('isRemoteEnabled', true);

        $temp_dir = sys_get_temp_dir() . '/dompdf-cache';
        if (!is_dir($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $options->set('fontDir', $temp_dir);
        $options->set('fontCache', $temp_dir);
        $options->set('tempDir', $temp_dir);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'invoice_' . $appointment_id . '_' . current_time('timestamp') . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Pragma: public');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

        echo $dompdf->output(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

        $status_mapping = [
            KCAppointment::STATUS_CANCELLED => __('Cancelled', 'kivicare-clinic-management-system'),
            KCAppointment::STATUS_BOOKED    => __('Booked', 'kivicare-clinic-management-system'),
            KCAppointment::STATUS_PENDING   => __('Pending', 'kivicare-clinic-management-system'),
            KCAppointment::STATUS_CHECK_OUT => __('Check Out', 'kivicare-clinic-management-system'),
            KCAppointment::STATUS_CHECK_IN  => __('Check In', 'kivicare-clinic-management-system'),
        ];

        return [
            'currency_prefix' => $currency['prefix'],
            'currency_postfix' => $currency['postfix'],
            'appointment' => [
                'id' => $appointment->id,
                'appointmentStartDate' => kcGetFormatedDate($appointment->appointmentStartDate),
                'appointmentStartTime' => kcGetFormatedTime($appointment->appointmentStartTime),
                'status' => $status_mapping[$appointment->status] ?? $appointment->status,
                'paymentMode' => $paymentInfo->payment_mode ?? 'Manual',
                'paymentStatus' => $paymentInfo->payment_status ?? 'pending'
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