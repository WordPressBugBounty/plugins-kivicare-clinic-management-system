<?php
namespace App\baseClasses;
use App\abstracts\KCAbstractTelemedProvider;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Telemed Factory
 * Factory class to create telemedical service provider instances
 */
class KCTelemedFactory
{
    private static $providers = array();

    /**
     * Register available telemed providers
     */
    public static function init()
    {
        self::$providers = apply_filters(
            'kc_telemed_providers',
            array(

            )
        );
        self::get_available_providers(false);
    }

    /**
     * Create telemed provider instance
     * @param string $provider_id Provider identifier
     * @return \App\abstracts\KCAbstractTelemedProvider|null Provider instance
     */
    public static function create_provider($provider_id)
    {
        if (!isset(self::$providers[$provider_id])) {
            return null;
        }

        $provider_config = self::$providers[$provider_id];
        $provider_class = $provider_config['class'];
        $provider_settings = $provider_config['settings_key'];

        if (class_exists($provider_class)) {
            return new $provider_class($provider_settings);
        }

        return null;
    }

    /**
     * Get available telemed providers
     * @param bool $for_frontend Return simplified data for frontend if true
     * @return array List of available providers
     */
    public static function get_available_providers($for_frontend = false)
    {
        $available = array();


        foreach (self::$providers as $provider_id => $provider_config) {
            $provider = self::create_provider($provider_id);

            if ($provider === null || ($for_frontend && !$provider->is_enabled())) {
                continue; // Skip if provider class does not exist or not enabled for frontend
            }

            $provider_data = array(
                'id' => $provider->get_provider_id(),
                'name' => $provider->get_provider_name(),
                'description' => $provider_config['description'] ?? '',
            );

            if ($provider) {
                $provider->init_hook();
            }

            if (!$for_frontend) {
                $provider_data['instance'] = $provider;
                $provider_data['config'] = $provider_config;
                $available[$provider_id] = $provider_data;
            } else {
                $provider_data['enabled'] = $provider ? $provider->is_enabled() : false;
                $provider_data['supports_recording'] = $provider ? $provider->supports_recording() : false;
                $provider_data['supports_waiting_room'] = $provider ? $provider->supports_waiting_room() : false;
                $available[] = $provider_data;
            }
        }

        return $available;
    }

    /**
     * Get available provider by provider id
     * @param string $provider_id Provider identifier
     * @return \App\abstracts\KCAbstractTelemedProvider|null Provider instance or null if not found/enabled
     */
    public static function get_available_provider($provider_id): KCAbstractTelemedProvider|null
    {
        // Ensure providers are initialized
        if (empty(self::$providers)) {
            self::init();
        }

        if (!isset(self::$providers[$provider_id])) {
            return null;
        }

        return self::create_provider($provider_id);
    }

    /**
     * Create meeting link for specific provider
     * @param string $provider_id Provider identifier
     * @param array $meeting_data Meeting configuration data
     * @return array|null Meeting details with link or null on failure
     */
    public static function create_meeting($provider_id, $meeting_data = array())
    {
        $provider = self::get_available_provider($provider_id);

        if (!$provider || !$provider->is_enabled()) {
            return null;
        }

        return $provider->create_meeting($meeting_data);
    }

    /**
     * Update meeting for specific provider
     * @param string $provider_id Provider identifier
     * @param string $meeting_id Meeting ID
     * @param array $meeting_data Updated meeting configuration data
     * @return array|null Updated meeting details or null on failure
     */
    public static function update_meeting($provider_id, $meeting_id, $meeting_data = array())
    {
        $provider = self::get_available_provider($provider_id);

        if (!$provider || !$provider->is_enabled()) {
            return null;
        }

        return $provider->update_meeting($meeting_id, $meeting_data);
    }

    /**
     * Get default provider
     * @return string|null Default provider ID
     */
    public static function get_default_provider()
    {
        $default_provider = get_option(KIVI_CARE_PREFIX . 'default_telemed_provider', 'zoom');

        // Verify the default provider is available and enabled
        $provider = self::get_available_provider($default_provider);
        if ($provider && $provider->is_enabled()) {
            return $default_provider;
        }

        // Fall back to first available enabled provider
        $available_providers = self::get_available_providers(true);
        if (!empty($available_providers)) {
            return $available_providers[0]['id'];
        }

        return null;
    }

    /**
     * Set default provider
     * @param string $provider_id Provider identifier
     * @return bool Success status
     */
    public static function set_default_provider($provider_id)
    {
        $provider = self::get_available_provider($provider_id);
        if (!$provider) {
            return false;
        }

        return update_option(KIVI_CARE_PREFIX . 'default_telemed_provider', $provider_id);
    }

    /**
     * Get enabled providers count
     * @return int Number of enabled providers
     */
    public static function get_enabled_providers_count()
    {
        $enabled_count = 0;
        $providers = self::get_available_providers(false);

        foreach ($providers as $provider_data) {
            if (isset($provider_data['instance']) && $provider_data['instance']->is_enabled()) {
                $enabled_count++;
            }
        }

        return $enabled_count;
    }

    /**
     * Check if any telemed provider is configured and enabled
     * @return bool True if at least one provider is enabled
     */
    public static function has_enabled_provider()
    {
        return self::get_enabled_providers_count() > 0;
    }

    public static function get_provider_by_doctor_id($doctor_id)
    {
        $provider_name = self::get_doctor_telemed_provider_name($doctor_id);
        $providers = self::get_available_providers(false);

        if ($provider_name && isset($providers[$provider_name])) {
            $providers = [$provider_name => $providers[$provider_name]] + $providers;
        }

        foreach ($providers as $id => $data) {
            $provider = $data['instance'];
            if (!$provider) continue;

            $config = $provider->get_config();
            
            if ((!empty($config['auth_type']) && $config['auth_type'] === 'server-to-server') || 
                !empty($provider->get_doctor_access_token($doctor_id))) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Retrieves the telemedicine provider name for a given doctor.
     *
     * This method fetches the user object for the specified doctor ID and returns
     * the value of the 'telemed_type' user meta, which indicates the telemedicine
     * provider associated with the doctor. If the user does not exist or the meta
     * value is not set, it returns false.
     *
     * @param int $doctor_id The ID of the doctor whose telemedicine provider name is to be retrieved.
     * @return string|false The telemedicine provider name if set, or false on failure.
     */
    protected static function get_doctor_telemed_provider_name($doctor_id)
    {
        // Assuming you have a method to get the doctor's user object
        $user = get_user_by('id', $doctor_id);
        if (!$user) {
            return false;
        }

        // Return the provider name based on the user's telemed provider setting
        return get_user_meta($doctor_id, 'telemed_type', single: true) ?: false;
    }

}