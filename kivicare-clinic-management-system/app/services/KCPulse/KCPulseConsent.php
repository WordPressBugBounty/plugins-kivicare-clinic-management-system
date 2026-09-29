<?php

namespace App\services\KCPulse;

use App\baseClasses\KCErrorLogger;

defined('ABSPATH') or die('Something went wrong');

/**
 * Explicit opt-in consent for the IQonic Pulse telemetry client.
 *
 * Replaces the previous opt-out model with two independent, unticked
 * checkboxes: one for basic installation telemetry, one for marketing
 * email. Both default to refused. The plugin is fully functional
 * regardless of the site owner's choice.
 *
 * Consent state is stored in three wp_options per flag (granted / refused /
 * never-asked) plus an audit-trail meta option. A one-time migration
 * handles the transition from the old opt-out option.
 *
 * KCPulseTracker checks is_telemetry_allowed() / is_marketing_allowed()
 * before every server call.
 */
final class KCPulseConsent
{
    // ── Consent flags (tri-state: '1' = granted, '0' = refused, absent = never asked) ──
    public const OPTION_CONSENT_TELEMETRY  = 'kivicare-clinic-management-system_pulse_consent_telemetry';
    public const OPTION_CONSENT_MARKETING = 'kivicare-clinic-management-system_pulse_consent_marketing';
    public const OPTION_CONSENT_ASKED     = 'kivicare-clinic-management-system_pulse_consent_asked';
    public const OPTION_CONSENT_META      = 'kivicare-clinic-management-system_pulse_consent_meta';

    // ── Legacy opt-out (kept as kill-switch for one release) ──
    private const OPTION_LEGACY_OPTOUT = 'kivicare-clinic-management-system_pulse_optout';

    // ── Migration guard ──
    private const OPTION_MIGRATED = 'kivicare-clinic-management-system_pulse_consent_migrated';

    // ── Consent version for audit trail ──
    public const CONSENT_VERSION = '2.0';

    // ── Set on activation; consumed by the one-time modal, then deleted ──
    private const OPTION_SHOW_ACTIVATION_MODAL = 'kivicare-clinic-management-system_pulse_show_modal';

    private const NONCE_ACTION = 'kivicare_pulse_consent';

    public static function init()
    {
        add_action('admin_init', [self::class, 'maybe_migrate_legacy']);
        add_action('admin_footer', [self::class, 'maybe_render_modal']);
        add_action('admin_post_kivicare_pulse_consent', [self::class, 'handle_consent_save']);
        add_action('admin_init', [self::class, 'register_privacy_policy_content']);
        add_action('admin_init', [self::class, 'register_privacy_settings_section']);
    }

    /**
     * Called from KCActivate::activate(). Marks that the next relevant
     * admin page load should show the one-time consent modal. This is the
     * only opt-in prompt — there is no persistent fallback notice. If the
     * site owner closes the modal without answering, the next chance to
     * grant/revoke consent is Settings → Privacy, not another prompt.
     */
    public static function flag_for_activation_modal(): void
    {
        update_option(self::OPTION_SHOW_ACTIVATION_MODAL, '1', false);
    }

    /**
     * Enqueues the consent UI stylesheet. Called on-demand right before
     * the notice/modal actually output markup, so it never loads on
     * screens where neither renders.
     */
    private static function enqueue_styles(): void
    {
        if (wp_style_is('kc-pulse-consent', 'enqueued')) {
            return;
        }

        wp_enqueue_style(
            'kc-pulse-consent',
            KIVI_CARE_DIR_URI . 'assets/css/kc-pulse-consent.css',
            [],
            defined('KIVI_CARE_VERSION') ? KIVI_CARE_VERSION : false
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Consent state queries
    // ─────────────────────────────────────────────────────────────

    /**
     * Whether basic installation telemetry is allowed.
     * Fails closed: returns false unless explicitly granted.
     */
    public static function is_telemetry_allowed(): bool
    {
        // Legacy kill-switch: if the old filter forces true, block everything.
        $legacy_blocked = apply_filters('kivicare_pulse_optout', false);
        if ($legacy_blocked) {
            return false;
        }

        return get_option(self::OPTION_CONSENT_TELEMETRY) === '1';
    }

    /**
     * Whether marketing email consent is granted.
     * Fails closed: returns false unless explicitly granted.
     */
    public static function is_marketing_allowed(): bool
    {
        $legacy_blocked = apply_filters('kivicare_pulse_optout', false);
        if ($legacy_blocked) {
            return false;
        }

        return get_option(self::OPTION_CONSENT_MARKETING) === '1';
    }

    /**
     * Whether at least one consent flag is active (telemetry or marketing).
     * Used by KCPulseTracker to decide whether to attempt registration.
     */
    public static function is_any_consent_active(): bool
    {
        return self::is_telemetry_allowed() || self::is_marketing_allowed();
    }

    /**
     * Whether the consent prompt has been answered.
     */
    public static function is_consent_asked(): bool
    {
        return get_option(self::OPTION_CONSENT_ASKED) === '1';
    }

    /**
     * Backward-compatible shim for callers still using the old is_allowed().
     * @deprecated Use is_telemetry_allowed() or is_any_consent_active().
     */
    public static function is_allowed(): bool
    {
        return self::is_telemetry_allowed();
    }

    // ─────────────────────────────────────────────────────────────
    // Migration from old opt-out model (runs once)
    // ─────────────────────────────────────────────────────────────

    /**
     * Migrates the old single optout option into the new tri-state model.
     *
     * Rules:
     * - If old optout was '1' (explicitly opted out): write telemetry='0',
     *   marketing='0', asked='1' — that user already said no, never re-nag.
     * - Otherwise: leave all consent options absent so the site is
     *   re-prompted and telemetry pauses until affirmative consent.
     */
    public static function maybe_migrate_legacy()
    {
        if (get_option(self::OPTION_MIGRATED)) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $old_optout = get_option(self::OPTION_LEGACY_OPTOUT, '');

        if ($old_optout === '1') {
            // User explicitly opted out before — honour that, don't re-prompt.
            update_option(self::OPTION_CONSENT_TELEMETRY, '0', false);
            update_option(self::OPTION_CONSENT_MARKETING, '0', false);
            update_option(self::OPTION_CONSENT_ASKED, '1', false);
            update_option(self::OPTION_CONSENT_META, [
                'granted_at'      => null,
                'withdrawn_at'    => gmdate('c'),
                'consent_version' => self::CONSENT_VERSION,
                'plugin_version'  => defined('KIVI_CARE_VERSION') ? KIVI_CARE_VERSION : 'unknown',
                'migration'       => 'legacy_optout_was_on',
            ], false);

            self::log_consent_event('migration', 'Legacy optout was on — consent set to refused, no re-prompt', [
                'telemetry'  => false,
                'marketing'  => false,
            ]);
        } else {
            // No explicit opt-out history — leave consent absent, will re-prompt.
            self::log_consent_event('migration', 'No legacy optout — will re-prompt for consent', []);
        }

        update_option(self::OPTION_MIGRATED, '1', false);
    }

    // ─────────────────────────────────────────────────────────────
    // Consent UX — activation modal
    // ─────────────────────────────────────────────────────────────

    /**
     * Renders a one-time modal on the first admin page load after
     * activation. Shown once: the OPTION_SHOW_ACTIVATION_MODAL flag is
     * cleared as soon as it renders, regardless of whether the site owner
     * answers or closes it — closing is treated the same as "Not now"
     * (no consent recorded), never as consent. This is the only opt-in
     * prompt; there is no persistent fallback notice. If ignored, the
     * next chance to grant/revoke consent is Settings → Privacy.
     */
    public static function maybe_render_modal()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (self::is_consent_asked()) {
            return;
        }

        if (get_option(self::OPTION_SHOW_ACTIVATION_MODAL) !== '1') {
            return;
        }

        // Only show on KiviCare screens, plugins.php, and the main dashboard.
        $screen = get_current_screen();
        if ($screen) {
            $allowed_screens = [
                'plugins',
                'dashboard',
                'toplevel_page_kivicare-dashboard',
            ];
            $is_kc_screen = in_array($screen->id, $allowed_screens, true)
                || strpos($screen->id, 'kivicare') !== false;

            if (!$is_kc_screen) {
                return;
            }
        }

        // One-time: whether or not the user acts on it, don't show the
        // modal again on a future page load.
        delete_option(self::OPTION_SHOW_ACTIVATION_MODAL);

        self::enqueue_styles();

        $form_action = admin_url('admin-post.php?action=kivicare_pulse_consent');
        ?>
        <div id="kc-pulse-consent-modal-overlay" class="kc-pulse-consent-modal-overlay">
            <div class="kc-pulse-consent-modal" role="dialog" aria-modal="true" aria-labelledby="kc-pulse-modal-title">
                <form method="post" action="<?php echo esc_url($form_action); ?>">
                    <?php wp_nonce_field(self::NONCE_ACTION); ?>


                    <p id="kc-pulse-modal-title" class="kc-pulse-consent-title">
                        <strong><?php esc_html_e('Help improve KiviCare', 'kivicare-clinic-management-system'); ?></strong>
                    </p>
                    <p class="kc-pulse-consent-intro">
                        <?php
                        esc_html_e(
                            'Help us improve KiviCare by sharing basic installation information to improve compatibility, fix issues, and prioritize future updates.',
                            'kivicare-clinic-management-system'
                        );
                        ?>
                    </p>

                    <div class="kc-pulse-consent-card">
                        <p class="kc-pulse-consent-checkbox-row">
                            <label>
                                <input type="checkbox" id="kc-pulse-consent-combined" checked="checked" />
                                <strong><?php esc_html_e('Share anonymous data and receive product updates', 'kivicare-clinic-management-system'); ?></strong>
                            </label>
                        </p>
                        <p class="kc-pulse-consent-detail">
                            <?php
                            esc_html_e(
                                'Includes site URL, KiviCare/WordPress/PHP versions, and plugin activation/deactivation events.',
                                'kivicare-clinic-management-system'
                            );
                            ?>
                        </p>
                        <p class="kc-pulse-consent-reassurance">
                            <span class="kc-pulse-consent-check">&#10003;</span> <strong><?php esc_html_e('No patient, appointment, prescription, or other clinical data is ever collected.', 'kivicare-clinic-management-system'); ?></strong>
                        </p>
                    </div>

                    <!-- Actual consent flags stay separate on the backend even though the
                         UI presents one combined preference — telemetry and marketing email
                         are distinct purposes and must remain independently revocable. -->
                    <input type="hidden" name="kivicare_pulse_consent_telemetry" id="kc-pulse-consent-telemetry-field" value="1" />
                    <input type="hidden" name="kivicare_pulse_consent_marketing" id="kc-pulse-consent-marketing-field" value="1" />

                    <p class="kc-pulse-consent-footnote">
                        <?php
                        printf(
                            /* translators: %s: link to the KiviCare settings section */
                            esc_html__('You can change this preference anytime in %s.', 'kivicare-clinic-management-system'),
                            '<a href="' . esc_url(admin_url('options-reading.php')) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('KiviCare settings', 'kivicare-clinic-management-system') . ' &#8599;</a>'
                        );
                        ?>
                    </p>

                    <p class="kc-pulse-consent-actions">
                        <button type="submit" name="kivicare_pulse_consent_action" value="decline" class="kc-pulse-consent-link-button">
                            <?php esc_html_e('Not now', 'kivicare-clinic-management-system'); ?>
                        </button>
                        <button type="submit" name="kivicare_pulse_consent_action" value="save" class="kc-pulse-consent-primary-button">
                            <?php esc_html_e('Allow & continue', 'kivicare-clinic-management-system'); ?> <span aria-hidden="true">&rarr;</span>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <script>
        (function () {
            var overlay = document.getElementById('kc-pulse-consent-modal-overlay');
            if (!overlay) {
                return;
            }
            overlay.classList.add('is-visible');

            // The modal shows one combined checkbox, but telemetry and
            // marketing consent are stored as separate backend flags — sync
            // the visible checkbox into both hidden fields on submit.
            var combined = document.getElementById('kc-pulse-consent-combined');
            var telemetryField = document.getElementById('kc-pulse-consent-telemetry-field');
            var marketingField = document.getElementById('kc-pulse-consent-marketing-field');
            var saveBtn = overlay.querySelector('button[value="save"]');
            if (combined && telemetryField && marketingField) {
                combined.addEventListener('change', function () {
                    var value = combined.checked ? '1' : '0';
                    telemetryField.value = value;
                    marketingField.value = value;
                    if (saveBtn) {
                        saveBtn.disabled = !combined.checked;
                    }
                });
            }
            if (saveBtn && combined) {
                saveBtn.disabled = !combined.checked;
            }

        })();
        </script>
        <?php
    }

    // ─────────────────────────────────────────────────────────────
    // Consent save handler (admin_post)
    // ─────────────────────────────────────────────────────────────

    /**
     * Handles the consent form submission (both notice and Settings → Privacy).
     * Writes both consent flags plus audit meta, fires action for Pro.
     */
    public static function handle_consent_save()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'kivicare-clinic-management-system'));
        }

        check_admin_referer(self::NONCE_ACTION);

        $action  = isset($_POST['kivicare_pulse_consent_action']) ? sanitize_text_field(wp_unslash($_POST['kivicare_pulse_consent_action'])) : '';
        $telemetry  = ($action === 'save') && !empty($_POST['kivicare_pulse_consent_telemetry']);
        $marketing  = ($action === 'save') && !empty($_POST['kivicare_pulse_consent_marketing']);

        $old_telemetry = self::is_telemetry_allowed();
        $old_marketing = self::is_marketing_allowed();

        update_option(self::OPTION_CONSENT_TELEMETRY, $telemetry ? '1' : '0', false);
        update_option(self::OPTION_CONSENT_MARKETING, $marketing ? '1' : '0', false);
        update_option(self::OPTION_CONSENT_ASKED, '1', false);

        // Build audit meta.
        $now = gmdate('c');
        $meta = [
            'granted_at'      => $telemetry || $marketing ? $now : null,
            'withdrawn_at'    => !$telemetry && !$marketing ? $now : null,
            'consent_version' => self::CONSENT_VERSION,
            'plugin_version'  => defined('KIVI_CARE_VERSION') ? KIVI_CARE_VERSION : 'unknown',
        ];
        update_option(self::OPTION_CONSENT_META, $meta, false);

        self::log_consent_event('consent_saved', 'Consent preferences updated', [
            'telemetry' => $telemetry,
            'marketing' => $marketing,
        ]);

        /**
         * Fires when consent state changes. Pro hooks this to sync its own state.
         *
         * @param bool $telemetry  Whether telemetry consent is now granted.
         * @param bool $marketing  Whether marketing consent is now granted.
         */
        do_action('kivicare_pulse_consent_changed', $telemetry, $marketing);

        $redirect = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : admin_url();
        wp_safe_redirect($redirect);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // Settings → Privacy section (revocation surface)
    // ─────────────────────────────────────────────────────────────

    /**
     * Registers a settings section on WordPress core's Privacy settings
     * screen (options-privacy.php) so site owners can grant/revoke
     * pulse consent from the standard privacy control point.
     */
    public static function register_privacy_settings_section()
    {
        add_settings_section(
            'kivicare_pulse_privacy',
            __('KiviCare Telemetry & Marketing Email', 'kivicare-clinic-management-system'),
            [self::class, 'render_privacy_settings_section'],
            'reading'
        );

        register_setting('reading', self::OPTION_CONSENT_TELEMETRY, [
            'type'              => 'string',
            'sanitize_callback' => [self::class, 'sanitize_consent_telemetry'],
            'default'           => '',
        ]);

        register_setting('reading', self::OPTION_CONSENT_MARKETING, [
            'type'              => 'string',
            'sanitize_callback' => [self::class, 'sanitize_consent_marketing'],
            'default'           => '',
        ]);

        add_settings_field(
            'kivicare_pulse_consent_settings',
            __('Consent Preferences', 'kivicare-clinic-management-system'),
            [self::class, 'render_privacy_settings_fields'],
            'reading',
            'kivicare_pulse_privacy'
        );
    }

    /**
     * Sanitize + save telemetry consent. Uses $wpdb directly to avoid
     * the infinite loop that update_option() triggers inside a sanitize callback.
     *
     * NOTE: No do_action() here — side effects in sanitize callbacks cause
     * race conditions with the Settings API. Consent updates are sent
     * via a wp_loaded hook that fires after all options are saved.
     */
    public static function sanitize_consent_telemetry($value)
    {
        global $wpdb;

        $new = ($value === '1') ? '1' : '0';
        $old = get_option(self::OPTION_CONSENT_TELEMETRY, '');

        if ($new !== $old) {
            $table = $wpdb->options;

            $wpdb->update($table, ['option_value' => '1'], ['option_name' => self::OPTION_CONSENT_ASKED], ['%s'], ['%s']);
            $wpdb->update($table, ['option_value' => $new], ['option_name' => self::OPTION_CONSENT_TELEMETRY], ['%s'], ['%s']);

            $now = gmdate('c');
            $meta = [
                'granted_at'      => $new === '1' ? $now : null,
                'withdrawn_at'    => $new === '0' ? $now : null,
                'consent_version' => self::CONSENT_VERSION,
                'plugin_version'  => defined('KIVI_CARE_VERSION') ? KIVI_CARE_VERSION : 'unknown',
            ];
            $wpdb->update($table, ['option_value' => wp_json_encode($meta)], ['option_name' => self::OPTION_CONSENT_META], ['%s'], ['%s']);

            wp_cache_delete(self::OPTION_CONSENT_ASKED, 'options');
            wp_cache_delete(self::OPTION_CONSENT_TELEMETRY, 'options');
            wp_cache_delete(self::OPTION_CONSENT_META, 'options');

            // Flag that consent changed — wp_loaded hook will send the server update.
            if (!did_action('kivicare_pulse_consent_changed')) {
                add_action('shutdown', [self::class, '_deferred_consent_update']);
            }
        }

        return $new;
    }

    /**
     * Sanitize + save marketing consent. Uses $wpdb directly (same reason as above).
     */
    public static function sanitize_consent_marketing($value)
    {
        global $wpdb;

        $new = ($value === '1') ? '1' : '0';
        $old = get_option(self::OPTION_CONSENT_MARKETING, '');

        if ($new !== $old) {
            $table = $wpdb->options;

            $wpdb->update($table, ['option_value' => '1'], ['option_name' => self::OPTION_CONSENT_ASKED], ['%s'], ['%s']);

            $wpdb->update($table, ['option_value' => $new], ['option_name' => self::OPTION_CONSENT_MARKETING], ['%s'], ['%s']);

            $now = gmdate('c');
            $meta = [
                'granted_at'      => $new === '1' ? $now : null,
                'withdrawn_at'    => $new === '0' ? $now : null,
                'consent_version' => self::CONSENT_VERSION,
                'plugin_version'  => defined('KIVI_CARE_VERSION') ? KIVI_CARE_VERSION : 'unknown',
            ];
            $wpdb->update($table, ['option_value' => wp_json_encode($meta)], ['option_name' => self::OPTION_CONSENT_META], ['%s'], ['%s']);

            wp_cache_delete(self::OPTION_CONSENT_ASKED, 'options');
            wp_cache_delete(self::OPTION_CONSENT_MARKETING, 'options');
            wp_cache_delete(self::OPTION_CONSENT_META, 'options');

            if (!did_action('kivicare_pulse_consent_changed')) {
                add_action('shutdown', [self::class, '_deferred_consent_update']);
            }
        }

        return $new;
    }

    public static function render_privacy_settings_section()
    {
        echo '<p>' . esc_html__(
            'Control what data KiviCare shares with IQonic Design. These settings do not affect KiviCare\'s core functionality.',
            'kivicare-clinic-management-system'
        ) . '</p>';
    }

    public static function render_privacy_settings_fields()
    {
        $current_telemetry = self::is_telemetry_allowed();
        $current_marketing = self::is_marketing_allowed();
        $admin_email = get_option('admin_email');
        ?>
        <label>
            <input type="hidden" name="<?php echo esc_attr(self::OPTION_CONSENT_TELEMETRY); ?>" value="0" />
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_CONSENT_TELEMETRY); ?>" value="1" <?php checked($current_telemetry); ?> />
            <?php esc_html_e('Share basic installation data (site URL, plugin/WP/PHP versions, lifecycle events) with IQonic', 'kivicare-clinic-management-system'); ?>
        </label>
        <br>
        <label>
            <input type="hidden" name="<?php echo esc_attr(self::OPTION_CONSENT_MARKETING); ?>" value="0" />
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_CONSENT_MARKETING); ?>" value="1" <?php checked($current_marketing); ?> />
            <?php
            printf(
                /* translators: %s: admin email address */
                esc_html__('Allow kivicare to send product updates and tips to %s', 'kivicare-clinic-management-system'),
                '<code>' . esc_html($admin_email) . '</code>'
            );
            ?>
        </label>
        <?php
    }

    // ─────────────────────────────────────────────────────────────
    // Privacy Policy Guide content
    // ─────────────────────────────────────────────────────────────

    public static function register_privacy_policy_content()
    {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = '<p class="privacy-policy-tutorial">' .
            esc_html__('KiviCare can share basic installation data with IQonic Design (its developer) to support software updates and inform product decisions. This is entirely optional and requires your explicit consent. If you choose to share, the data includes: your site URL, KiviCare/WordPress/PHP version numbers, and plugin lifecycle events (activation, deactivation, uninstallation). You may also optionally consent to receive product update emails at your site admin email address. No patient, staff, or other end-user data collected by KiviCare is ever included in this telemetry. You can grant or revoke consent at any time under Settings > Privacy.', 'kivicare-clinic-management-system') .
            '</p>';

        wp_add_privacy_policy_content(__('KiviCare', 'kivicare-clinic-management-system'), wp_kses_post($content));
    }

    // ─────────────────────────────────────────────────────────────
    // Deferred consent update — fires once after all options are saved
    // ─────────────────────────────────────────────────────────────

    /**
     * Deferred consent update scheduled from sanitize callbacks.
     * Runs on shutdown so BOTH options are saved before we read values.
     */
    public static function _deferred_consent_update()
    {
        do_action('kivicare_pulse_consent_changed', self::is_telemetry_allowed(), self::is_marketing_allowed());
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Logs a consent-flow event via KCErrorLogger.
     */
    private static function log_consent_event(string $event, string $message, array $context = []): void
    {
        if (class_exists(KCErrorLogger::class)) {
            KCErrorLogger::instance()->info("[PulseConsent] {$event}: {$message}", $context);
        }
    }
}
