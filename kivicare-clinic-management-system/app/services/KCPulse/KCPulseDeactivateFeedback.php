<?php

namespace App\services\KCPulse;

defined('ABSPATH') or die('Something went wrong');

/**
 * "Why are you deactivating?" dialog shown on the Plugins screen. Captures
 * an optional reason before the real deactivation request fires.
 * consume_reason() is wired into the shared KCPulseTracker's
 * on_deactivate() via the
 * 'kivicare_pulse_deactivate_reason_kivicare-clinic-management-system'
 * filter (see KCApp::init()), so the reason is attached to the Pulse
 * telemetry deactivate event.
 *
 * Deliberately not a survey: WordPress.org guidelines call out
 * deactivation surveys as a source of user frustration, especially for
 * people who are just troubleshooting a site. This dialog never blocks
 * deactivation — "Skip & Deactivate" is as easy to reach as submitting —
 * and every field is optional. Reason order and copy favor the person
 * troubleshooting (temporary/trouble first) over the developer's need
 * for a tidy taxonomy, and the follow-up question changes per reason
 * instead of asking one generic "describe the issue".
 */
final class KCPulseDeactivateFeedback
{
    private const TRANSIENT_KEY = 'kivicare_pulse_deactivate_reason';
    private const AJAX_ACTION = 'kivicare_pulse_deactivate_reason';
    private const NONCE_ACTION = 'kivicare_pulse_deactivate_reason';

    /**
     * Reasons shown in the dialog, in display order. Keys are sent to the
     * server as-is. 'text' => true reveals a contextual, explicitly
     * optional follow-up prompt for that specific reason.
     */
    private const REASONS = [
        'temporary' => [
            'label' => "I'm only deactivating it temporarily",
            'text' => false,
        ],
        'trouble' => [
            'label' => "I'm having trouble getting it to work",
            'text' => true,
            'placeholder' => 'What went wrong? (optional)',
        ],
        'no_longer_needed' => [
            'label' => 'I no longer need KiviCare',
            'text' => false,
        ],
        'found_alternative' => [
            'label' => 'I found another solution',
            'text' => true,
            'placeholder' => 'What made you choose it? (optional)',
        ],
        'missing_feature' => [
            'label' => "It's missing something I need",
            'text' => true,
            'placeholder' => 'What feature would you like to see? (optional)',
        ],
        'other' => [
            'label' => 'Something else',
            'text' => true,
            'placeholder' => 'Tell us what happened (optional)',
        ],
    ];

    public static function init()
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [self::class, 'handle_ajax']);
    }

    public static function enqueue($hook)
    {
        if ($hook !== 'plugins.php') {
            return;
        }

        wp_register_script('kivicare-pulse-deactivate-feedback', false, [], KIVI_CARE_VERSION, true);
        wp_enqueue_script('kivicare-pulse-deactivate-feedback');

        wp_localize_script('kivicare-pulse-deactivate-feedback', 'kcPulseDeactivateFeedback', [
            'pluginSlug' => KIVI_CARE_BASE_NAME,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action' => self::AJAX_ACTION,
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'reasons' => self::REASONS,
            'i18n' => [
                'title' => __('Help us improve KiviCare', 'kivicare-clinic-management-system'),
                'subtitle' => __("We're sorry to see you go. If you have a moment, what made you decide to deactivate KiviCare?", 'kivicare-clinic-management-system'),
                'privacy' => __("Your feedback helps us improve KiviCare. We won't use it for marketing.", 'kivicare-clinic-management-system'),
                'submit' => __('Send Feedback & Deactivate', 'kivicare-clinic-management-system'),
                'skip' => __('Skip & Deactivate', 'kivicare-clinic-management-system'),
                'cancel' => __('Cancel', 'kivicare-clinic-management-system'),
                'maxLength' => __('Maximum 50 characters reached.', 'kivicare-clinic-management-system'),
            ],
        ]);

        add_action('admin_print_footer_scripts', [self::class, 'print_dialog_markup']);
        add_action('admin_print_footer_scripts', [self::class, 'print_inline_script']);
    }

    public static function print_dialog_markup()
    {
        ?>
        <div id="kc-pulse-deactivate-feedback-overlay" class="kc-pulse-deactivate-feedback-overlay" style="display:none;">
            <div class="kc-pulse-deactivate-feedback-dialog" role="dialog" aria-modal="true" aria-labelledby="kc-pulse-deactivate-feedback-title">
                <h2 id="kc-pulse-deactivate-feedback-title"></h2>
                <p class="kc-pulse-deactivate-feedback-subtitle"></p>
                <div id="kc-pulse-deactivate-feedback-body"></div>
                <p class="kc-pulse-deactivate-feedback-privacy"></p>
                <div class="kc-pulse-deactivate-feedback-actions">
                    <button type="button" class="button-link" id="kc-pulse-deactivate-feedback-cancel"></button>
                    <span class="kc-pulse-deactivate-feedback-actions-right">
                        <button type="button" class="button" id="kc-pulse-deactivate-feedback-skip"></button>
                        <button type="button" class="button button-primary" id="kc-pulse-deactivate-feedback-submit"></button>
                    </span>
                </div>
            </div>
        </div>
        <style>
            .kc-pulse-deactivate-feedback-overlay {
                position: fixed; inset: 0; background: rgba(0, 0, 0, .6);
                z-index: 100000; display: flex; align-items: center; justify-content: center;
            }
            .kc-pulse-deactivate-feedback-dialog {
                background: #fff; border-radius: 4px; width: 100%; max-width: 460px;
                max-height: 90vh; overflow-y: auto; padding: 20px 26px; box-shadow: 0 5px 15px rgba(0,0,0,.3);
            }
            .kc-pulse-deactivate-feedback-dialog h2 { margin: 0 0 6px; font-size: 18px; }
            .kc-pulse-deactivate-feedback-subtitle { color: #555; margin: 0 0 14px; line-height: 1.4; }
            .kc-pulse-deactivate-feedback-input-wrapper { margin: 0 0 8px; }
            .kc-pulse-deactivate-feedback-input-wrapper label { margin-left: 6px; cursor: pointer; }
            .kc-pulse-deactivate-feedback-text {
                display: block; width: 100%; margin: 4px 0 0 24px; max-width: calc(100% - 24px);
                height: 30px;
            }
            .kc-pulse-deactivate-feedback-warning {
                display: none; color: #b32d2e; font-size: 11px; margin: 4px 0 0 24px;
            }
            .kc-pulse-deactivate-feedback-privacy {
                color: #888; font-size: 12px; margin: 12px 0 0;
            }
            .kc-pulse-deactivate-feedback-actions {
                display: flex; justify-content: space-between; align-items: center;
                margin-top: 14px; padding-top: 12px; border-top: 1px solid #ddd;
            }
        </style>
        <?php
    }

    public static function print_inline_script()
    {
        ?>
        <script>
        (function () {
            var cfg = window.kcPulseDeactivateFeedback;
            if (!cfg) {
                return;
            }

            document.addEventListener('DOMContentLoaded', function () {
                var row = document.querySelector('tr[data-plugin="' + cfg.pluginSlug + '"]');
                if (!row) {
                    return;
                }

                var deactivateLink = row.querySelector('.deactivate a');
                if (!deactivateLink) {
                    return;
                }

                var overlay = document.getElementById('kc-pulse-deactivate-feedback-overlay');
                var body = document.getElementById('kc-pulse-deactivate-feedback-body');
                var deactivateUrl = deactivateLink.getAttribute('href');

                document.getElementById('kc-pulse-deactivate-feedback-title').textContent = cfg.i18n.title;
                document.querySelector('.kc-pulse-deactivate-feedback-subtitle').textContent = cfg.i18n.subtitle;
                document.querySelector('.kc-pulse-deactivate-feedback-privacy').textContent = cfg.i18n.privacy;
                document.getElementById('kc-pulse-deactivate-feedback-cancel').textContent = cfg.i18n.cancel;
                document.getElementById('kc-pulse-deactivate-feedback-skip').textContent = cfg.i18n.skip;
                document.getElementById('kc-pulse-deactivate-feedback-submit').textContent = cfg.i18n.submit;

                Object.keys(cfg.reasons).forEach(function (key) {
                    var reason = cfg.reasons[key];

                    var wrapper = document.createElement('div');
                    wrapper.className = 'kc-pulse-deactivate-feedback-input-wrapper';

                    var input = document.createElement('input');
                    input.type = 'radio';
                    input.name = 'kc_reason_key';
                    input.value = key;
                    input.id = 'kc-pulse-feedback-' + key;

                    var label = document.createElement('label');
                    label.setAttribute('for', input.id);
                    label.textContent = reason.label;

                    wrapper.appendChild(input);
                    wrapper.appendChild(label);

                    if (reason.text) {
                        var text = document.createElement('input');
                        text.type = 'text';
                        text.className = 'kc-pulse-deactivate-feedback-text regular-text';
                        text.name = 'kc_reason_text_' + key;
                        text.placeholder = reason.placeholder || '';
                        text.maxLength = 50;
                        text.style.display = 'none';
                        wrapper.appendChild(text);

                        var warning = document.createElement('div');
                        warning.className = 'kc-pulse-deactivate-feedback-warning';
                        warning.textContent = cfg.i18n.maxLength;
                        wrapper.appendChild(warning);

                        text.addEventListener('input', function () {
                            warning.style.display = text.value.length >= 50 ? 'block' : 'none';
                        });

                        input.addEventListener('change', function () {
                            text.style.display = 'block';
                            text.focus();
                        });
                    }

                    input.addEventListener('change', function () {
                        body.querySelectorAll('.kc-pulse-deactivate-feedback-text').forEach(function (t) {
                            if (t !== (wrapper.querySelector('.kc-pulse-deactivate-feedback-text'))) {
                                t.style.display = 'none';
                                t.value = '';
                            }
                        });
                        body.querySelectorAll('.kc-pulse-deactivate-feedback-warning').forEach(function (w) {
                            w.style.display = 'none';
                        });
                    });

                    body.appendChild(wrapper);
                });

                function proceedToDeactivate() {
                    window.location.href = deactivateUrl;
                }

                function closeDialog() {
                    overlay.style.display = 'none';
                }

                function submitReason() {
                    var checked = body.querySelector('input[name="kc_reason_key"]:checked');

                    if (!checked) {
                        proceedToDeactivate();
                        return;
                    }

                    var reasonKey = checked.value;
                    var textInput = body.querySelector('input[name="kc_reason_text_' + reasonKey + '"]');
                    var reasonText = textInput ? textInput.value : '';

                    var formData = new FormData();
                    formData.append('action', cfg.action);
                    formData.append('nonce', cfg.nonce);
                    formData.append('reason_key', reasonKey);
                    formData.append('reason_text', reasonText);

                    fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
                        .catch(function () {})
                        .finally(proceedToDeactivate);
                }

                deactivateLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    overlay.style.display = 'flex';
                });

                document.getElementById('kc-pulse-deactivate-feedback-cancel').addEventListener('click', closeDialog);
                document.getElementById('kc-pulse-deactivate-feedback-skip').addEventListener('click', proceedToDeactivate);
                document.getElementById('kc-pulse-deactivate-feedback-submit').addEventListener('click', submitReason);
            });
        })();
        </script>
        <?php
    }

    /**
     * AJAX handler: stashes the reported reason in a short-lived transient
     * keyed to the current user, read back via consume_reason() on the
     * actual deactivation request that follows immediately after.
     */
    public static function handle_ajax()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('activate_plugins')) {
            wp_send_json_error('forbidden', 403);
        }

        $reason_key = isset($_POST['reason_key']) ? sanitize_key(wp_unslash($_POST['reason_key'])) : '';
        $reason_text = isset($_POST['reason_text']) ? sanitize_text_field(wp_unslash($_POST['reason_text'])) : '';

        if (!isset(self::REASONS[$reason_key])) {
            wp_send_json_error('invalid_reason', 400);
        }

        if (mb_strlen($reason_text) > 50) {
            wp_send_json_error('reason_text_too_long', 400);
        }

        set_transient(self::transient_key(), [
            'reason_key' => $reason_key,
            'reason_text' => $reason_text,
        ], MINUTE_IN_SECONDS * 5);

        wp_send_json_success();
    }

    /**
     * Reads and clears the stashed reason, if any. Called once via the
     * 'kivicare_pulse_deactivate_reason_kivicare-clinic-management-system'
     * filter.
     *
     * @return array{reason_key: string, reason_text: string}|null
     */
    public static function consume_reason(): ?array
    {
        $key = self::transient_key();
        $data = get_transient($key);
        delete_transient($key);

        return is_array($data) ? $data : null;
    }

    private static function transient_key(): string
    {
        return self::TRANSIENT_KEY . '_' . get_current_user_id();
    }
}
