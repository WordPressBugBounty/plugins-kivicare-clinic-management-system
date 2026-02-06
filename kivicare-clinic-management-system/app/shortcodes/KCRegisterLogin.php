<?php
namespace App\shortcodes;

use App\abstracts\KCShortcodeAbstract;
use App\models\KCOption;

class KCRegisterLogin extends KCShortcodeAbstract
{
    protected $tag = 'kivicareRegisterLogin';
    protected $default_attrs = [
        'initial_tab' => '', // 'login' or 'register'
        'redirect_url' => '',
        'login_text' => '',
        'register_text' => '',
        'patient_role_only' => '', // 'yes' or 'no'
        'clinic_id' => '',
        'userroles' => '',
    ];
    protected $assets_dir = KIVI_CARE_DIR . '/dist';
    protected $js_entry = 'app/shortcodes/assets/js/KCRegisterLogin.jsx';


    /**
     * Script dependencies
     *
     * @var array
     */
    protected $script_dependencies = ['wp-element', 'wp-api-fetch', 'wp-i18n'];

    /**
     * CSS dependencies
     *
     * @var array
     */
    protected $css_dependencies = [];

    /**
     * Load scripts in footer
     *
     * @var bool
     */
    protected $in_footer = true;

    /**
     * Render the login/register component
     *
     * @param string $id Unique ID for this shortcode instance
     * @param array $atts Shortcode attributes
     * @param string|null $content Shortcode content
     * @return void
     */
    protected function render($id, $atts, $content = null)
    {
        // Check if user is already logged in
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            ?>
            <div class="kc-already-logged-in">
                <p>
                    <?php
                    echo wp_kses_post(sprintf(
                        /* translators: 1: User's display name, 2: Log out URL */
                        __('You are currently logged in as %1$s. <a href="%2$s">Log out</a>', 'kivicare-clinic-management-system'),
                        esc_html($current_user->display_name),
                        esc_url(wp_logout_url(get_permalink()))
                    ));
                    ?>
                </p>
            </div>
            <?php
            return;
        }

        // Get reCAPTCHA settings
        $recaptcha_settings = KCOption::get('google_recaptcha', []);
        $recaptcha_enabled = ($recaptcha_settings['status'] ?? 'off') === 'on' ? 'yes' : 'no';
        $recaptcha_site_key = $recaptcha_settings['site_key'] ?? '';

        $default_country_code = KCOption::get('country_code', 'us');
        $show_other_gender = KCOption::get('user_registration_form_setting', 'off');

        $enabled_user_roles = array_filter(KCOption::get('user_registration_shortcode_role_setting', []), fn($role) => $role == 'on');
        
        // If patient_role_only is 'yes', only enable patient role
        if ($atts['patient_role_only'] === 'yes') {
            $enabled_user_roles = ['patient' => 'on'];
        }
        
        // Filter roles by userroles attribute if provided
        if (!empty($atts['userroles'])) {
            // Remove all whitespace from the string first to handle "kivicare_ doctor"
            $clean_roles = preg_replace('/\s+/', '', $atts['userroles']);
            $requested_roles = explode(',', $clean_roles);
            $filtered_roles = [];
            foreach ($requested_roles as $role) {
                 $filtered_roles[$role] = 'on';
            }
            if (!empty($filtered_roles)) {
                $enabled_user_roles = $filtered_roles;
            }
        }
        
        // Pre-select clinic if clinic_id attribute is provided
        $preselect_clinic_id = !empty($atts['clinic_id']) ? (int)$atts['clinic_id'] : 0;

        // Normalize text values - remove smart quotes and other formatting
        $login_text = isset($atts['login_text']) ? $atts['login_text'] : '';
        $register_text = isset($atts['register_text']) ? $atts['register_text'] : '';
        


        $data_attrs = [
            'data-show-other-gender' => $show_other_gender,
            'data-default-country' => $default_country_code,
            'data-initial-tab' => $atts['initial_tab'],
            'data-redirect-url' => $atts['redirect_url'],
            'data-login-text' => sanitize_text_field($login_text),
            'data-register-text' => sanitize_text_field($register_text),
            'data-recaptcha-enabled' => $recaptcha_enabled,
            'data-recaptcha-site-key' => $recaptcha_site_key,
            'data-wp-nonce' => wp_create_nonce('wp_rest'),
            'data-rest-url' => rest_url(),
            'data-enable-user-roles' => wp_json_encode(array_keys($enabled_user_roles)),
            'data-preselect-clinic-id' => $preselect_clinic_id,
            'data-site-logo' => !empty(KCOption::get('site_logo')) ? wp_get_attachment_url(KCOption::get('site_logo')) : (defined('KIVI_CARE_DIR_URI') ? KIVI_CARE_DIR_URI . 'assets/images/logo.png' : ''),
        ];

        // Get widget settings for colors
        $widgetSettingsOption = KCOption::get('widgetSetting');
        $widgetSettings = [];
        if (!empty($widgetSettingsOption)) {
            $widgetSettings = is_string($widgetSettingsOption) ? json_decode($widgetSettingsOption, true) : $widgetSettingsOption;
        }

        if (!empty($widgetSettings)) {
            $data_attrs['data-primary-color'] = isset($widgetSettings['primaryColor']) ? esc_attr($widgetSettings['primaryColor']) : '';
            $data_attrs['data-primary-hover-color'] = isset($widgetSettings['primaryHoverColor']) ? esc_attr($widgetSettings['primaryHoverColor']) : '';
            $data_attrs['data-secondary-color'] = isset($widgetSettings['secondaryColor']) ? esc_attr($widgetSettings['secondaryColor']) : '';
            $data_attrs['data-secondary-hover-color'] = isset($widgetSettings['secondaryHoverColor']) ? esc_attr($widgetSettings['secondaryHoverColor']) : '';
        }

        $data_attrs_string = '';
        foreach ($data_attrs as $key => $value) {
            if ($value !== '' && $value !== null) {
                $data_attrs_string .= ' ' . $key . '="' . esc_attr($value) . '"';
            }
        }

        ?>
        <div id="<?php echo esc_attr($id); ?>" class="kc-register-login-container" <?php echo $data_attrs_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>


            <?php if ($recaptcha_enabled === 'no' && $this->kcbase->getLoginUserRole() === 'administrator'): ?>
                <div class="mb-2">
                    <a class="iq-color-secondary"
                        href="<?php echo esc_url(admin_url('admin.php?page=dashboard#/general-settings')); ?>" target="_blank">
                        <?php echo esc_html__("Note: Click here to enable Google reCAPTCHA v3", "kivicare-clinic-management-system"); ?>
                    </a>
                </div>
            <?php endif; ?>

            <div class="kc-register-login-forms">
                <div class="kc-loading"><?php esc_html_e('Loading...', 'kivicare-clinic-management-system'); ?></div>
            </div>
        </div>
        <?php
    }
}
