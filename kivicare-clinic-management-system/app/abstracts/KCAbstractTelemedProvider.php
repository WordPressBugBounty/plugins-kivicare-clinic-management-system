<?php
namespace App\abstracts;

use App\baseClasses\KCErrorLogger;
use KCTApp\models\KCTAppointmentZoomMapping;
use KCGMApp\models\KCGMAppointmentGoogleMeetMapping;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Abstract Telemed Provider
 * Base class for all telemedical service providers
 */
abstract class KCAbstractTelemedProvider
{
    /**
     * Provider settings key
     * @var string
     */
    protected $settings_key;

    /**
     * Provider configuration
     * @var array
     */
    protected $config;

    /**
     * Provider ID
     * @var string
     */
    protected $provider_id;

    /**
     * Provider name
     * @var string
     */
    protected $provider_name;

    /**
     * Constructor
     * @param string $settings_key Settings key for this provider
     */
    public function __construct($settings_key)
    {
        $this->settings_key = $settings_key;
        $this->config = $this->get_config();
        $this->init();
    }

    /**
     * Initialize provider
     * Called after construction, can be overridden by child classes
     */
    protected function init()
    {
        // Override in child classes for specific initialization
    }

    /**
     * Initialize hooks
     * Called by factory to set up WordPress hooks
     */
    public function init_hook()
    {
        // Add any WordPress hooks here
        add_action('wp_ajax_kc_test_' . $this->get_provider_id() . '_connection', array($this, 'test_connection_ajax'));
        add_action('wp_ajax_kc_' . $this->get_provider_id() . '_webhook', array($this, 'handle_webhook'));
        add_action('wp_ajax_nopriv_kc_' . $this->get_provider_id() . '_webhook', array($this, 'handle_webhook'));
    }

    /**
     * Get provider configuration from database
     * @return array Provider configuration
     */
    public function get_config()
    {
        $default_config = $this->get_default_config();
        $saved_config = get_option($this->settings_key, array());
        
        return wp_parse_args($saved_config, $default_config);
    }

    /**
     * Get default configuration
     * Must be implemented by child classes
     * @return array Default configuration
     */
    abstract protected function get_default_config();

    /**
     * Get provider ID
     * Must be implemented by child classes
     * @return string Provider identifier
     */
    abstract public function get_provider_id();

    /**
     * Get provider name
     * Must be implemented by child classes
     * @return string Provider display name
     */
    abstract public function get_provider_name();

    /**
     * Check if provider is enabled
     * @return bool True if provider is enabled
     */
    public function is_enabled()
    {
        return !empty($this->config['enabled']) && $this->is_configured();
    }

    /**
     * Check if provider is properly configured
     * Must be implemented by child classes
     * @return bool True if provider has required configuration
     */
    abstract public function is_configured();

    /**
     * Create a meeting
     * Must be implemented by child classes
     * @param array $meeting_data Meeting configuration
     * @return array|\WP_Error Meeting details or WP_Error on failure
     */
    abstract public function create_meeting($meeting_data = array());

    /**
     * Update a meeting
     * Must be implemented by child classes
     * @param string $meeting_id Meeting ID
     * @param array $meeting_data Updated meeting configuration
     * @return array|false Updated meeting details or false on failure
     */
    abstract public function update_meeting($meeting_id, $meeting_data = array());

    /**
     * Delete a meeting
     * Must be implemented by child classes
     * @param string $meeting_id Meeting ID
     * @return bool True on success, false on failure
     */
    abstract public function delete_meeting($meeting_id);

    /**
     * Get meeting details
     * Must be implemented by child classes
     * @param string $meeting_id Meeting ID
     * @return array|false Meeting details or false if not found
     */
    abstract public function get_meeting($meeting_id);

    /**
     * Test connection to provider API
     * Must be implemented by child classes
     * @param array $data Connection data
     * @param int $doctor_id Doctor ID
     * @return array Connection test result with success status and message
     */
    abstract public function test_connection($data = [], $doctor_id = 0);

    /**
     * Update doctor configuration
     * @param int $doctor_id Doctor ID
     * @param array $config Configuration data
     * @return bool True on success
     */
    abstract public function update_doctor_config(int $doctor_id, array $config): bool;

    /**
     * Get authorization URL for doctor
     * @param int $doctor_id Doctor ID
     * @return string Authorization URL
     */
    abstract public function get_authorization_url($doctor_id): string;

    /**
     * Disconnect doctor from provider
     * @param int $doctor_id Doctor ID
     * @return bool True on success
     */
    public function disconnect_doctor(int $doctor_id): bool
    {
        return false;
    }

    /**
     * Check if provider supports recording
     * @return bool True if recording is supported
     */
    public function supports_recording()
    {
        return false; // Override in child classes if recording is supported
    }

    /**
     * Check if provider supports waiting room
     * @return bool True if waiting room is supported
     */
    public function supports_waiting_room()
    {
        return false; // Override in child classes if waiting room is supported
    }

    /**
     * Check if provider supports password protection
     * @return bool True if password protection is supported
     */
    public function supports_password()
    {
        return false; // Override in child classes if password protection is supported
    }

    /**
     * Get supported meeting types
     * @return array Array of supported meeting types
     */
    public function get_supported_meeting_types()
    {
        return array('instant', 'scheduled'); // Override in child classes for specific types
    }

    /**
     * Get maximum meeting duration (in minutes)
     * @return int Maximum duration in minutes, 0 for unlimited
     */
    public function get_max_duration()
    {
        return 0; // Override in child classes for specific limits
    }

    /**
     * Get maximum participants
     * @return int Maximum participants, 0 for unlimited
     */
    public function get_max_participants()
    {
        return 0; // Override in child classes for specific limits
    }

    /**
     * Format meeting data for API
     * @param array $meeting_data Raw meeting data
     * @return array Formatted meeting data
     */
    protected function format_meeting_data($meeting_data)
    {
        $defaults = array(
            'topic' => 'Telemedical Consultation',
            'type' => 'scheduled',
            'start_time' => current_time('mysql'),
            'duration' => 30,
            'timezone' => wp_timezone_string(),
            'password' => '',
            'waiting_room' => false,
            'auto_recording' => false,
            'host_video' => true,
            'participant_video' => true,
            'mute_upon_entry' => true,
            'patient_id' => '',
            'doctor_id' => '',
            'appointment_id' => ''
        );

        return wp_parse_args($meeting_data, $defaults);
    }

    /**
     * Save meeting record using appropriate model
     * @param array $meeting_data Meeting data to save
     * @return int|false Meeting record ID or false on failure
     */
    protected function save_meeting_record($meeting_data)
    {
        try {
            $provider_id = $this->get_provider_id();
            KCErrorLogger::instance()->error($provider_id );
            
            switch ($provider_id) {
                case 'zoom':
                    return $this->save_zoom_meeting_record($meeting_data);
                    
                case 'googlemeet':
                    return $this->save_google_meet_record($meeting_data);
                    
                default:
                    // For other providers, you can extend this or create generic models
                    $this->log('warning', "No specific model found for provider: {$provider_id}");
                    return false;
            }
        } catch (\Exception $e) {
            $this->log('error', 'Failed to save meeting record: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save Zoom meeting record
     * @param array $meeting_data Meeting data
     * @return int|false
     */
    abstract protected function save_zoom_meeting_record($meeting_data);

    /**
     * Save Google Meet record
     * @param array $meeting_data Meeting data
     * @return int|false
     */
    protected function save_google_meet_record($meeting_data)
    {
        try {

            $google_meet_mapping = KCGMAppointmentGoogleMeetMapping::create([
                'eventId' => $meeting_data['id'] ?? $meeting_data['event_id'] ?? null,
                'appointmentId' => $meeting_data['appointment_id'] ?? 0,
                'url' => $meeting_data['join_url'] ?? $meeting_data['url'] ?? null,
                'password' => $meeting_data['password'] ?? null,
                'eventUrl' => $meeting_data['event_url'] ?? null,
            ]);

            if (!$google_meet_mapping) {
                $this->log('error', __('Failed to save Google Meet record', 'kivicare-clinic-management-system'), [
                    'meeting_data' => $meeting_data
                ]);
                return false;
            }

            $this->log('info', __('Google Meet record saved', 'kivicare-clinic-management-system'), [
                'meeting_data' => $meeting_data,
                'record_id' => $google_meet_mapping
            ]);
            return $google_meet_mapping;
        } catch (\Exception $e) {
            $this->log('error', __('Failed to save Google Meet record: ', 'kivicare-clinic-management-system') . $e->getMessage(), [
                'meeting_data' => $meeting_data
            ]);
            return false;
        }
    }

    /**
     * Update meeting record using appropriate model
     * @param string $meeting_id Meeting ID
     * @param array $meeting_data Updated meeting data
     * @return bool True on success, false on failure
     */
    protected function update_meeting_record($meeting_id, $meeting_data)
    {
        try {
            $provider_id = $this->get_provider_id();
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
            KCErrorLogger::instance()->error('update_meeting_record'.print_r($meeting_data));
            switch ($provider_id) {
                case 'zoom':
                    return $this->update_zoom_meeting_record($meeting_id, $meeting_data);
                    
                case 'googlemeet':
                    return $this->update_google_meet_record($meeting_id, $meeting_data);
                    
                default:
                    $this->log('warning', "No specific model found for provider: {$provider_id}");
                    return false;
            }
        } catch (\Exception $e) {
            $this->log('error', 'Failed to update meeting record: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update Zoom meeting record
     * @param string $meeting_id Zoom meeting ID
     * @param array $meeting_data Updated data
     * @return bool
     */
    protected function update_zoom_meeting_record($meeting_id, $meeting_data)
    {
        $zoom_mapping = KCTAppointmentZoomMapping::query()
            ->where('zoomId', $meeting_id)
            ->first();

        if (!$zoom_mapping) {
            return false;
        }

        $update_data = [];
        if (isset($meeting_data['start_url'])) {
            $update_data['startUrl'] = $meeting_data['start_url'];
        }
        if (isset($meeting_data['join_url'])) {
            $update_data['joinUrl'] = $meeting_data['join_url'];
        }
        if (isset($meeting_data['password'])) {
            $update_data['password'] = $meeting_data['password'];
        }

        return !empty($update_data) ? $zoom_mapping->update($update_data) : true;
    }

    /**
     * Update Google Meet record
     * @param string $meeting_id Event ID
     * @param array $meeting_data Updated data
     * @return bool
     */
    protected function update_google_meet_record($meeting_id, $meeting_data)
    {
        $google_meet_mapping = KCGMAppointmentGoogleMeetMapping::query()
            ->where('eventId', $meeting_id)
            ->first();

        if (!$google_meet_mapping) {
            return false;
        }

        $update_data = [];
        if (isset($meeting_data['url']) || isset($meeting_data['join_url'])) {
            $update_data['url'] = $meeting_data['url'] ?? $meeting_data['join_url'];
        }
        if (isset($meeting_data['password'])) {
            $update_data['password'] = $meeting_data['password'];
        }
        if (isset($meeting_data['event_url'])) {
            $update_data['eventUrl'] = $meeting_data['event_url'];
        }

        return !empty($update_data) ? $google_meet_mapping->update($update_data) : true;
    }

    /**
     * Delete meeting record using appropriate model
     * @param string $meeting_id Meeting ID
     * @return bool True on success, false on failure
     */
    abstract protected function delete_meeting_record($meeting_id);
           

    /**
     * Get meeting records by appointment ID
     * @param int $appointment_id Appointment ID
     * @return object|null Meeting record or null if not found
     */
    abstract public function get_meeting_by_appointment($appointment_id);


    /**
     * Handle AJAX connection test
     */
    public function test_connection_ajax()
    {
        // Verify nonce for security
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ''; 

        if ( ! wp_verify_nonce( $nonce, 'kc_telemed_test_connection' ) ) {
            wp_die( esc_html__( 'Security check failed', 'kivicare-clinic-management-system' ) );
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $result = $this->test_connection();
        wp_send_json($result);
    }

    /**
     * Handle webhook from provider
     * Override in child classes for specific webhook handling
     */
    public function handle_webhook()
    {
        // Override in child classes for specific webhook handling
        wp_send_json_success();
    }

    /**
     * Log provider activity
     * @param string $level Log level (info, warning, error)
     * @param string $message Log message
     * @param array $context Additional context data
     */
    protected function log($level, $message, $context = array())
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = sprintf(
                '[%s] %s: %s',
                $this->get_provider_name(),
                strtoupper($level),
                $message
            );
            
            if (!empty($context)) {
                $log_message .= ' Context: ' . wp_json_encode($context);
            }
            
            KCErrorLogger::instance()->error($log_message);
        }
    }

    /**
     * Get configuration field value
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    protected function get_config_value($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * Update configuration
     * @param array $new_config New configuration data
     * @return bool True on success, false on failure
     */
    public function update_config($new_config)
    {
        $this->config = wp_parse_args($new_config, $this->get_default_config());
        return update_option($this->settings_key, $this->config);
    }

    /**
     * Get provider capabilities
     * @return array Array of provider capabilities
     */
    public function get_capabilities()
    {
        return array(
            'recording' => $this->supports_recording(),
            'waiting_room' => $this->supports_waiting_room(),
            'password' => $this->supports_password(),
            'meeting_types' => $this->get_supported_meeting_types(),
            'max_duration' => $this->get_max_duration(),
            'max_participants' => $this->get_max_participants()
        );
    }
    /**
     * Get provider ID
     * @return bool True Appointment Meeting is cancle is set, false otherwise
     */
    public abstract function cancel_meeting_by_appointment($appointment_id);

    /**
     * Get doctor access token
     * @param int $doctor_id Doctor ID
     * @return string|null Access token or null if not found
     */
    public abstract function get_doctor_access_token($doctor_id);

    public abstract function is_doctor_telemed_connected(): bool;
}