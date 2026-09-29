<?php

namespace App\services\KCPulse;

use App\baseClasses\KCErrorLogger;

defined('ABSPATH') or die('Something went wrong');

/**
 * IQonic Pulse telemetry client, shared by KiviCare Lite, Pro, and every
 * addon plugin. Each product constructs its own instance with its own
 * product slug/version/plugin file — uuid/secret storage and the cron
 * hook are derived per-instance from the product slug, so multiple
 * products can be tracked independently in the same request.
 *
 * Consent is NOT per-instance: every instance reads the one shared
 * decision from KCPulseConsent (Lite-owned) and listens to the same
 * 'kivicare_pulse_consent_changed' action.
 *
 * Registers the installation with the IQonic Pulse server on consent,
 * sends a daily heartbeat, and reports deactivate/uninstall events.
 * All calls are best-effort and fail silently — telemetry must never
 * break the site. See wp-content/plugins/iq-tracker/README.md for the
 * server-side API.
 */
final class KCPulseTracker
{
    private string $product_slug;
    private string $product_version;
    private string $deactivation_hook_file;
    private string $option_uuid;
    private string $option_secret;
    private string $option_last_version;
    private string $cron_hook;
    private string $log_label;

    public function __construct(
        string $product_slug,
        string $product_version,
        string $deactivation_hook_file,
        ?string $log_label = null
    ) {
        $this->product_slug = $product_slug;
        $this->product_version = $product_version;
        $this->deactivation_hook_file = $deactivation_hook_file;
        $this->option_uuid = "{$product_slug}_pulse_uuid";
        $this->option_secret = "{$product_slug}_pulse_secret";
        $this->option_last_version = "{$product_slug}_pulse_last_version";
        $this->cron_hook = "{$product_slug}_pulse_heartbeat";
        $this->log_label = $log_label ?? "KiviCare Pulse ({$product_slug})";
    }

    public function init(): void
    {
        add_action('admin_init', [$this, 'maybe_register']);
        add_action('admin_init', [$this, 'maybe_report_version_change']);
        add_action($this->cron_hook, [$this, 'send_heartbeat']);

        // Schedule cron only if telemetry consent is active.
        if (KCPulseConsent::is_telemetry_allowed()) {
            if (!wp_next_scheduled($this->cron_hook)) {
                wp_schedule_event(time(), 'daily', $this->cron_hook);
            }
        }

        register_deactivation_hook($this->deactivation_hook_file, [$this, 'on_deactivate']);

        // When consent changes, notify the server and manage cron state.
        // Every instantiated tracker independently hooks the same shared
        // action — WordPress supports N callbacks on one action, each
        // scoped to its own instance via $this.
        add_action('kivicare_pulse_consent_changed', [$this, 'on_consent_changed'], 10, 2);
    }

    /**
     * Registers the installation if at least one consent is active and
     * not yet registered. Runs on admin_init so a transient API outage
     * during activation doesn't leave the site permanently unregistered.
     */
    public function maybe_register(): void
    {
        if (!KCPulseConsent::is_any_consent_active()) {
            return;
        }

        // Already registered — consent updates are handled exclusively
        // by the on_consent_changed hook (fired from KCPulseConsent).
        // Do NOT call send_consent_update() here; it would fire on every
        // page load and send stale mid-save values to the server.
        if (get_option($this->option_uuid) && get_option($this->option_secret)) {
            return;
        }

        $installation_id = wp_generate_uuid4();

        $payload = [
            'product'          => $this->product_slug,
            'installation_id'  => $installation_id,
            'consent'          => [
                'telemetry'       => KCPulseConsent::is_telemetry_allowed(),
                'marketing'       => KCPulseConsent::is_marketing_allowed(),
                'consent_version' => KCPulseConsent::CONSENT_VERSION,
            ],
        ];

        if (KCPulseConsent::is_telemetry_allowed()) {
            $payload += [
                'site_url'          => home_url(),
                'plugin_version'    => $this->product_version,
                'wordpress_version' => get_bloginfo('version'),
                'php_version'       => PHP_VERSION,
            ];
        }

        if (KCPulseConsent::is_marketing_allowed()) {
            $payload['admin_email'] = get_option('admin_email');
        }

        $response = $this->request('/installations', $payload);

        if (empty($response['uuid']) || empty($response['secret'])) {
            return;
        }

        update_option($this->option_uuid, $response['uuid'], false);
        update_option($this->option_secret, $response['secret'], false);
        update_option($this->option_last_version, $this->product_version, false);

        $this->log_consent_event('registered', 'Installation registered', [
            'telemetry' => KCPulseConsent::is_telemetry_allowed(),
            'marketing' => KCPulseConsent::is_marketing_allowed(),
        ]);
    }

    /**
     * Detects a plugin version change (an update landed since the last
     * admin page load) and reports it immediately instead of waiting for
     * the next daily heartbeat — so the server's version history reflects
     * the update the same day it happens.
     */
    public function maybe_report_version_change(): void
    {
        if (!KCPulseConsent::is_telemetry_allowed()) {
            return;
        }

        if (!get_option($this->option_uuid) || !get_option($this->option_secret)) {
            return;
        }

        $last_version = get_option($this->option_last_version);

        if ($last_version === $this->product_version) {
            return;
        }

        update_option($this->option_last_version, $this->product_version, false);
        $this->send_heartbeat();
    }

    public function send_heartbeat(): void
    {
        if (!KCPulseConsent::is_telemetry_allowed()) {
            return;
        }

        $this->signed_event('heartbeat', [
            'plugin_version'    => $this->product_version,
            'wordpress_version' => get_bloginfo('version'),
            'php_version'       => PHP_VERSION,
        ]);
    }

    public function on_deactivate(): void
    {
        // Deactivation feedback is reported even without active consent —
        // it's the one signal telling the team why a site is leaving, so
        // it must not depend on a decision that's typically made moot by
        // the deactivation itself.
        // Each product may optionally wire its own deactivation-feedback
        // dialog into this filter (see KCPulseDeactivateFeedback for
        // Lite/Pro's implementation). Products with none registered get
        // a bare deactivate event with empty reason fields.
        $reason = apply_filters("kivicare_pulse_deactivate_reason_{$this->product_slug}", []);

        $this->signed_event('deactivate', array_filter([
            'reason_key' => $reason['reason_key'] ?? '',
            'reason_text' => $reason['reason_text'] ?? '',
        ]));

        $timestamp = wp_next_scheduled($this->cron_hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->cron_hook);
        }
    }

    /**
     * Called from uninstall.php. Reports the uninstall event and clears
     * the stored uuid/secret so a later reinstall registers fresh.
     */
    public function on_uninstall(): void
    {
        $this->signed_event('uninstall');

        delete_option($this->option_uuid);
        delete_option($this->option_secret);
    }

    /**
     * Sends a consent-state update to the server. Called when the site
     * owner changes consent via the notice or Settings → Privacy.
     *
     * This lets the server's admin table correctly show the installation's
     * current consent state instead of just going stale.
     */
    public function send_consent_update(): void
    {
        $uuid   = get_option($this->option_uuid);
        $secret = get_option($this->option_secret);

        if (empty($uuid) || empty($secret)) {
            return;
        }

        $telemetry = KCPulseConsent::is_telemetry_allowed();
        $marketing = KCPulseConsent::is_marketing_allowed();

        $body = [
            'telemetry'       => $telemetry,
            'marketing'       => $marketing,
            'consent_version' => KCPulseConsent::CONSENT_VERSION,
        ];

        if ($marketing) {
            $body['admin_email'] = get_option('admin_email');
        }

        $raw_body = wp_json_encode($body);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$uuid}.{$timestamp}.{$raw_body}", $secret);

        $status_code = null;

        $this->request("/installations/{$uuid}/consent", $body, [
            'X-Pulse-Secret'    => $secret,
            'X-Pulse-Timestamp' => (string) $timestamp,
            'X-Pulse-Signature' => $signature,
        ], $status_code, true);

        // Server no longer recognizes this uuid (e.g. the installation row
        // was deleted) — clear it so the next admin_init re-registers from
        // scratch instead of silently failing every consent update forever.
        if ($status_code === 404) {
            delete_option($this->option_uuid);
            delete_option($this->option_secret);
        }

        // If telemetry was revoked, unschedule the cron.
        if (!KCPulseConsent::is_telemetry_allowed()) {
            $timestamp_scheduled = wp_next_scheduled($this->cron_hook);
            if ($timestamp_scheduled) {
                wp_unschedule_event($timestamp_scheduled, $this->cron_hook);
            }
        } else {
            // Telemetry re-granted: ensure cron is scheduled.
            if (!wp_next_scheduled($this->cron_hook)) {
                wp_schedule_event(time(), 'daily', $this->cron_hook);
            }
        }

        $this->log_consent_event('consent_update_sent', 'Consent update sent to server', [
            'telemetry' => KCPulseConsent::is_telemetry_allowed(),
            'marketing' => KCPulseConsent::is_marketing_allowed(),
        ]);
    }

    /**
     * Called when consent state changes. Sends the update to the server
     * and manages cron scheduling.
     *
     * @param bool $telemetry  Whether telemetry consent is now granted.
     * @param bool $marketing  Whether marketing consent is now granted.
     */
    public function on_consent_changed(bool $telemetry, bool $marketing = false): void
    {
        // If we have a registration, send the consent update to the server.
        if (get_option($this->option_uuid) && get_option($this->option_secret)) {
            $this->send_consent_update();
        }

        // Manage cron state.
        if ($telemetry) {
            if (!wp_next_scheduled($this->cron_hook)) {
                wp_schedule_event(time(), 'daily', $this->cron_hook);
            }
        } else {
            $timestamp = wp_next_scheduled($this->cron_hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $this->cron_hook);
            }
        }
    }

    /**
     * Signs and sends a request for one of the uuid-scoped events
     * (heartbeat/deactivate/uninstall). No-ops if not yet registered.
     *
     * If the server no longer recognizes this uuid (404 iq_pulse_not_found),
     * the stale uuid/secret are cleared so the next admin_init re-registers
     * from scratch instead of failing silently forever.
     */
    private function signed_event(string $event, array $body = []): void
    {
        // 'deactivate' reports its reason regardless of consent state (see
        // on_deactivate()); every other event stays gated on telemetry.
        if ($event !== 'deactivate' && !KCPulseConsent::is_telemetry_allowed()) {
            return;
        }

        $uuid   = get_option($this->option_uuid);
        $secret = get_option($this->option_secret);

        if (empty($uuid) || empty($secret)) {
            return;
        }

        $endpoint_map = [
            'heartbeat'   => "/installations/{$uuid}/heartbeat",
            'deactivate'  => "/installations/{$uuid}/deactivate",
            'uninstall'   => "/installations/{$uuid}/uninstall",
        ];

        $path = $endpoint_map[$event] ?? null;
        if (!$path) {
            return;
        }

        $raw_body  = !empty($body) ? wp_json_encode($body) : '';
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$uuid}.{$timestamp}.{$raw_body}", $secret);

        $status_code = null;

        $this->request($path, $body, [
            'X-Pulse-Secret'    => $secret,
            'X-Pulse-Timestamp' => (string) $timestamp,
            'X-Pulse-Signature' => $signature,
        ], $status_code, $event === 'deactivate');

        if ($status_code === 404) {
            delete_option($this->option_uuid);
            delete_option($this->option_secret);
        }
    }

    /**
     * Performs a best-effort POST to the Pulse API. Returns the decoded
     * JSON body on success, or null on any failure (never throws).
     * The HTTP status code is written back into $status_code when the
     * request reached the server at all (null on a network-level failure).
     *
     * Belt-and-braces: refuses to send if no consent is active, UNLESS
     * $bypass_consent_gate is true. The consent-update call itself must
     * bypass this gate — it is how a revocation (consent going to false)
     * reaches the server at all; gating it on active consent would trap
     * every revocation on the client and leave the server showing stale,
     * previously-granted consent forever.
     */
    private function request(string $path, array $body, array $headers = [], ?int &$status_code = null, bool $bypass_consent_gate = false): ?array
    {
        if (!$bypass_consent_gate && !KCPulseConsent::is_any_consent_active()) {
            return null;
        }

        if (!defined('KIVI_CARE_PULSE_API_URL') || empty(KIVI_CARE_PULSE_API_URL)) {
            return null;
        }

        $response = wp_remote_post(rtrim(KIVI_CARE_PULSE_API_URL, '/') . '/iq-pulse/v1' . $path, [
            'timeout'   => 10,
            'blocking'  => true,
            'headers'   => array_merge(['Content-Type' => 'application/json'], $headers),
            'body'      => !empty($body) ? wp_json_encode($body) : '',
        ]);

        if (is_wp_error($response)) {
            $this->log($path, null, $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $status_code = $code;
        $raw_response_body = wp_remote_retrieve_body($response);

        $this->log($path, $code, $raw_response_body);

        if ($code < 200 || $code >= 300) {
            return null;
        }

        $data = json_decode($raw_response_body, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Logs a Pulse API call outcome via error_log(), gated behind
     * WP_DEBUG_LOG so this never writes in a default production
     * configuration.
     */
    private function log(string $path, ?int $status_code, string $detail): void
    {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }

        $status = $status_code !== null ? (string) $status_code : 'error';

        error_log(sprintf('[%s] %s -> %s: %s', $this->log_label, $path, $status, $detail));
    }

    /**
     * Logs a consent-flow event via KCErrorLogger.
     */
    private function log_consent_event(string $event, string $message, array $context = []): void
    {
        if (class_exists(KCErrorLogger::class)) {
            KCErrorLogger::instance()->info("[{$this->log_label}] {$event}: {$message}", $context);
        }
    }
}
