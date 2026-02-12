<?php

namespace App\controllers\api\SettingsController;

use App\controllers\api\SettingsController;
use App\models\KCOption;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class AppointmentSetting
 * 
 * @package App\controllers\api\SettingsController
 */
class AppointmentSetting extends SettingsController
{
    private static $instance = null;

    protected $route = 'settings/appointment-setting';

    private $request_data;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get singleton instance of the controller.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getAppointmentSetting'],
            'permission_callback' => [$this, 'checkPermission'],
            //'args' => $this->getSettingsEndpointArgs()
        ]);
        // Update Appointment Setting
        $this->registerRoute('/' . $this->route, [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'updateAppointmentSetting'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            //'args'     => $this->getSettingFieldSchema()['appointment_setting']
        ]);
    }
    /**
     * Get AppointmentSetting settings
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getAppointmentSetting(WP_REST_Request $request): WP_REST_Response
    {
        $restrict_appointment = self::kcAppointmentRestrictionData();
        $options = KCOption::getMultiple([
            'email_appointment_reminder',
            'appointment_cancellation_buffer',
            'multifile_appointment',
            'appointment_description_config_data',
            'appointment_patient_info_config_data'
        ]);

        $appointmentReminder = is_array($options['email_appointment_reminder'])
            ? $options['email_appointment_reminder']
            : [
                'status' => 'off',
                'sms_status' => false,
                'time' => '24',
                'whatsapp_status' => false
            ];

        if (!isKiviCareProActive()) {
            $appointmentReminder['sms_status'] = false;
            $appointmentReminder['whatsapp_status'] = false;
        }

        // Handle Cancellation Buffer Compatibility
        $bufferData = $options['appointment_cancellation_buffer'];
        $bufferStatus = isset($bufferData['status']) && ($bufferData['status'] === 'on' || $bufferData['status'] === true);

        $bufferHours = '';
        if (isset($bufferData['time'])) {
            if (is_array($bufferData['time'])) {
                $bufferHours = $bufferData['time']['value'] ?? '';
            }
        }

        $settings = [
            'only_same_day_book' => $restrict_appointment['only_same_day_book'] ?? 'off',
            'post_book' => $restrict_appointment['post_book'] ?? '',
            'pre_book' => $restrict_appointment['pre_book'] ?? '',
            'fileUploadEnabled' => (in_array($options['multifile_appointment'], ['on', true, 'true', '1', 1], true)) ? 'on' : 'off',
            'emailReminder' => $appointmentReminder['status'] ?? false,
            'emailReminderHours' => $appointmentReminder['time'] ?? '',
            'smsReminder' => $appointmentReminder['sms_status'] ?? false,
            'whatsappReminder' => $appointmentReminder['whatsapp_status'] ?? false,
            'appointmentDescription' => is_bool($options['appointment_description_config_data'])
                ? 'off'
                : $options['appointment_description_config_data'],
            'cancellationBufferEnabled' => $bufferStatus,
            'cancellationBufferHours'   => $bufferHours,
        ];

        // Check Twilio Configuration
        $smsConfigData = get_option('sms_config_data', []);
        if (is_string($smsConfigData)) {
            $smsConfigData = json_decode($smsConfigData, true) ?: [];
        }
        $settings['isTwilioSmsConfigured'] = !empty($smsConfigData['enableSMS']) && ($smsConfigData['enableSMS'] === 'true' || $smsConfigData['enableSMS'] === true) && !empty($smsConfigData['account_id']) && !empty($smsConfigData['auth_token']) && !empty($smsConfigData['to_number']);

        $whatsAppConfigData = get_option('whatsapp_config_data', []);
        if (is_string($whatsAppConfigData)) {
            $whatsAppConfigData = json_decode($whatsAppConfigData, true) ?: [];
        }
        $settings['isTwilioWhatsAppConfigured'] = !empty($whatsAppConfigData['enableWhatsApp']) && ($whatsAppConfigData['enableWhatsApp'] === 'true' || $whatsAppConfigData['enableWhatsApp'] === true) && !empty($whatsAppConfigData['wa_account_id']) && !empty($whatsAppConfigData['wa_auth_token']) && !empty($whatsAppConfigData['wa_to_number']);


        return $this->response($settings);
    }

    /**
     * Update appointment settings.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The response containing update status.
     */
    public function updateAppointmentSetting(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $this->request_data = $request->get_json_params();
            
            $data = [
                'restrict_appointment' => $this->restrictAppointmentSave(),
                'multifile_upload_status' => $this->saveMultifileUploadStatus(),
                'appointment_reminder_notification' => $this->appointmentReminderNotificationSave(),
                'appointment_description_status' => $this->enableDisableAppointmentDescription(),
                'appointment_cancellation_buffer' => $this->appointmentCancellationBufferSave(),
            ];

            return $this->response($data, esc_html__('Appointment settings saved successfully.', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                esc_html__('Failed to save appointment settings.', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    public function restrictAppointmentSave()
    {
        $request_data = $this->request_data;
        $message = esc_html__('Failed to update', 'kivicare-clinic-management-system');
        $status = false;
        if (isset($request_data['pre_book']) && isset($request_data['post_book'])) {
            if ((int)$request_data['pre_book'] < 0 && (int)$request_data['post_book'] < 0) {
                return [
                    'status'  => false,
                    'message' => esc_html__('Pre or Post Book Days Must Be Greater than Zero ', 'kivicare-clinic-management-system'),
                ];
            }

            KCOption::set('restrict_appointment', ['post' => (int)$request_data['post_book'], 'pre' => (int)$request_data['pre_book']]);
            if(!empty($request_data['only_same_day_book']) && ($request_data['only_same_day_book'] == '1' || $request_data['only_same_day_book'] == 'on')){
                KCOption::set('restrict_only_same_day_book_appointment', 'on');
            } else {
                KCOption::set('restrict_only_same_day_book_appointment', 'off');
            }

            $status = true;
            $message = esc_html__('Appointment restrict days saved successfully', 'kivicare-clinic-management-system');
        }
        return [
            'status'  => $status,
            'message' => $message,
        ];
    }

    public function saveMultifileUploadStatus()
    {
        $request_data = $this->request_data;
        $message = esc_html__('Failed to update', 'kivicare-clinic-management-system');
        $status = false;
        if (isset($request_data['fileUploadEnabled'])) {
            $value = ($request_data['fileUploadEnabled'] == '1' || $request_data['fileUploadEnabled'] === true || $request_data['fileUploadEnabled'] === 'on') ? 'on' : 'off';
            KCOption::set('multifile_appointment', $value);
            $message = esc_html__('File Upload Setting Saved.', 'kivicare-clinic-management-system');
            $status = true;
        }
        return [
            'status'  => $status,
            'message' => $message,
        ];
    }

    public function appointmentReminderNotificationSave()
    {
        $request_data = $this->request_data;
        $message = esc_html__('Failed to update', 'kivicare-clinic-management-system');
        $status = false;

        if (isset($request_data['emailReminder'], $request_data['emailReminderHours'])) {
            KCOption::set('email_appointment_reminder', [
                "status" => ($request_data['emailReminder'] === 'true' || $request_data['emailReminder'] === true || $request_data['emailReminder'] === 'on') ? 'on' : 'off',
                "time" => $request_data['emailReminderHours'],
                "sms_status" => (isset($request_data['smsReminder']) && ($request_data['smsReminder'] === 'true' || $request_data['smsReminder'] === true || $request_data['smsReminder'] === 'on')) ? 'on' : 'off',
                "whatsapp_status" => (isset($request_data['whatsappReminder']) && ($request_data['whatsappReminder'] === 'true' || $request_data['whatsappReminder'] === true || $request_data['whatsappReminder'] === 'on')) ? 'on' : 'off'
            ]);

            $message = esc_html__('Email Appointment Reminder Setting Saved', 'kivicare-clinic-management-system');
            $status = true;
        }
        return [
            'status'  => $status,
            'message' => $message,
        ];
    }

    public function enableDisableAppointmentDescription()
    {
        $request_data = $this->request_data;
        if(!empty($request_data['appointmentDescription']) && ($request_data['appointmentDescription'] == '1' || $request_data['appointmentDescription'] == 'on')){
            KCOption::set('appointment_description_config_data', 'on');
        } else {
            KCOption::set('appointment_description_config_data', 'off');
        }
        return [
            'data' => $request_data['appointmentDescription'],
            'status'  => true,
            'message' => esc_html__('Appointment Description status changed successfully.', 'kivicare-clinic-management-system'),
        ];
    }

    public function appointmentCancellationBufferSave()
    {
        $request_data = $this->request_data;
        $message = esc_html__('Failed to update', 'kivicare-clinic-management-system');
        
        $data = [
            "status" => 'off',
            "time"   => [
                "value" => "",
                "label" => ""
            ],
        ];

        if (!empty($request_data['cancellationBufferEnabled'])) {
            $hours = (string)$request_data['cancellationBufferHours'];
            
            $data = [
                "status" => 'on',
                "time"   => [
                    "value" => $hours,
                    "label" => $hours . ' ' . esc_html__('hours', 'kivicare-clinic-management-system')
                ],
            ];
            $message = esc_html__('Appointment Cancellation Buffer Setting Saved', 'kivicare-clinic-management-system');
        }
        
        KCOption::set('appointment_cancellation_buffer', $data);

        return [
            'status'  => true,
            'message' => $message,
        ];
    }

    /**
     * Get appointment restriction data
     * 
     * @return array
     */
    public static function kcAppointmentRestrictionData()
    {
        $data = KCOption::get('restrict_appointment', true);
        $only_same_day_book = KCOption::get('restrict_only_same_day_book_appointment', true);

        $temp = [
            'pre_book' => 0,
            'post_book' => 365,
            'only_same_day_book' => 'off',
        ];

        if (!is_bool($data)) {
            $temp['pre_book'] = $data['pre'] ?? 0;
            $temp['post_book'] = $data['post'] ?? 365;
        }

        if (!is_bool($only_same_day_book)) {
            $temp['only_same_day_book'] = $only_same_day_book ?? 'off';
        }

        return $temp;
    }
}