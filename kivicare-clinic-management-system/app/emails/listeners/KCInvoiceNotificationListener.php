<?php

namespace App\emails\listeners;

use App\baseClasses\KCErrorLogger;
use App\emails\KCEmailSender;
use App\models\KCPatient;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Invoice Notification Listener - Handles email notifications when invoices are generated
 */
class KCInvoiceNotificationListener
{
    private KCEmailSender $emailSender;
    private static ?KCInvoiceNotificationListener $instance = null;

    public function __construct()
    {
        $this->emailSender = KCEmailSender::get_instance();
        $this->initializeHooks();
    }

    public static function get_instance(): KCInvoiceNotificationListener
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeHooks(): void
    {
        // Hook into invoice generation event
        add_action('kc_invoice_generated', [$this, 'handleInvoiceGenerated'], 10, 1);
    }

    /**
     * Handle invoice generated event
     * 
     * @param array $invoiceData Contains invoice and patient details
     */
    public function handleInvoiceGenerated(array $invoiceData): void
    {
        try {
            if (empty($invoiceData['patient_id'])) {
                KCErrorLogger::instance()->error('KCInvoiceNotificationListener: Missing patient_id in invoice data');
                return;
            }

            // Get patient details
            $patient = KCPatient::find($invoiceData['patient_id']);
            if (!$patient) {
                KCErrorLogger::instance()->error('KCInvoiceNotificationListener: Patient not found for ID: ' . $invoiceData['patient_id']);
                return;
            }

            // Get patient user data
            $patientUser = get_userdata($patient->user_id);
            if (!$patientUser) {
                KCErrorLogger::instance()->error('KCInvoiceNotificationListener: Patient user not found');
                return;
            }

            // Prepare template data
            $templateData = [
                'patient_name' => $patientUser->display_name,
                'invoice_number' => $invoiceData['invoice_number'] ?? '',
                'invoice_date' => $invoiceData['invoice_date'] ?? current_time('mysql'),
                'total_amount' => $invoiceData['total_amount'] ?? '0.00',
                'invoice_url' => $invoiceData['invoice_url'] ?? '',
                'current_date' => current_time('mysql'),
                'patient' => [
                    'id' => $patient->id,
                    'email' => $patientUser->user_email,
                    'name' => $patientUser->display_name,
                ],
                'invoice' => [
                    'number' => $invoiceData['invoice_number'] ?? '',
                    'date' => $invoiceData['invoice_date'] ?? '',
                    'amount' => $invoiceData['total_amount'] ?? '',
                ]
            ];

            // Prepare attachments if invoice PDF path is provided
            $attachments = [];
            if (!empty($invoiceData['invoice_pdf_path']) && file_exists($invoiceData['invoice_pdf_path'])) {
                $attachments[] = $invoiceData['invoice_pdf_path'];
            }

            // Send notification to patient with invoice attachment
            $result = $this->emailSender->sendEmailByTemplate(
                KIVI_CARE_PREFIX . 'patient_invoice',
                $patientUser->user_email,
                $templateData,
                [
                    'attachments' => $attachments
                ]
            );

            if ($result) {
                KCErrorLogger::instance()->error('KCInvoiceNotificationListener: Invoice notification sent successfully to: ' . $patientUser->user_email);
            } else {
                KCErrorLogger::instance()->error('KCInvoiceNotificationListener: Failed to send invoice notification to: ' . $patientUser->user_email);
            }

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KCInvoiceNotificationListener Error: ' . $e->getMessage());
        }
    }
}
