<?php

use App\baseClasses\KCSidebarManager;
use App\admin\KCDashboardPermalinkHandler;
use App\models\KCOption;

defined('ABSPATH') or die('Something went wrong');

/**
 * Lazy string replacement function that only evaluates closures when needed
 * 
 * @param array $search Array of search strings
 * @param array $replace Array of replacement values (can include closures)
 * @param string $subject The string to perform replacements on
 * @return string The string with replacements performed
 */
function kc_str_replace_lazy(array $search, array $replace, string $subject): string
{
    // Validate input arrays have same length
    if (count($search) !== count($replace)) {
        throw new InvalidArgumentException('Search and replace arrays must have the same length');
    }

    $result = $subject;

    // Process each search/replace pair
    for ($i = 0; $i < count($search); $i++) {
        $searchKey = $search[$i];
        $replaceValue = $replace[$i];

        // Only process if the search key exists in the current result
        if (strpos($result, $searchKey) !== false) {
            // If replacement is a closure, evaluate it
            if ($replaceValue instanceof Closure) {
                $replaceValue = $replaceValue();
            }

            // Perform the replacement
            $result = str_replace($searchKey, $replaceValue, $result);
        }
    }

    return $result;
}

/**
 * Alternative API that's more intuitive - accepts associative array
 */
function kc_str_replace_lazy_assoc(string $subject, array $replacements): string
{
    $result = $subject;

    foreach ($replacements as $search => $replace) {
        // Only process if the search key exists in the current result
        if (strpos($result, $search) !== false) {
            // If replacement is a closure, evaluate it
            if ($replace instanceof Closure) {
                $replace = $replace();
            }

            // Perform the replacement
            $result = str_replace($search, $replace, $result);
        }
    }

    return $result;
}


/**
 * Get KiviCare dashboard URL for specific user role
 * 
 * @param string $role User role (administrator, clinic_admin, doctor, receptionist, patient)
 * @return string Dashboard URL
 */
function kc_get_dashboard_url($role = null)
{
    if (empty($role)) {
        // Get current user role
        $current_user = wp_get_current_user();
        $role = get_user_meta($current_user->ID, 'kivicare_user_role', true);

        if (empty($role)) {
            $role = 'patient'; // Default role
        }
    }

    $permalink_handler = KCDashboardPermalinkHandler::instance();
    return $permalink_handler->get_dashboard_url($role);
}

/**
 * Check if current request is for a KiviCare dashboard
 * 
 * @return bool
 */
function kc_is_dashboard_request()
{
    $permalink_handler = KCDashboardPermalinkHandler::instance();
    return $permalink_handler->is_dashboard_request();
}

/**
 * Get current dashboard type
 * 
 * @return string|null
 */
function kc_get_current_dashboard_type()
{
    $permalink_handler = KCDashboardPermalinkHandler::instance();
    return $permalink_handler->get_current_dashboard_type();
}

/**
 * Redirect current user to their appropriate dashboard
 * 
 * @return void
 */
function kc_redirect_to_user_dashboard()
{
    $permalink_handler = KCDashboardPermalinkHandler::instance();
    $permalink_handler->redirect_to_user_dashboard();
}

/**
 * Get dashboard sidebar array for specific user roles
 * Updated to use OOP approach with KCSidebarManager
 * 
 * @param array|null $user_roles Optional user roles array
 * @return array
 */
function kcDashboardSidebarArray($user_roles = null)
{
    return KCSidebarManager::getInstance()->getDashboardSidebar($user_roles);
}

/**
 * Check if WooCommerce is active.
 *
 * @return bool True if WooCommerce is active, false otherwise.
 */
function iskcWooCommerceActive()
{
    return class_exists('WooCommerce');
}

/**
 * Get KiviCare Pro plugin version using defined constant
 *
 * @return string|null The KiviCare Pro plugin version or null if not defined
 */
function getKiviCareProVersion()
{
    return defined('KIVI_CARE_PRO_VERSION') ? KIVI_CARE_PRO_VERSION : null;
}

/**
 * Check if KiviCare Pro plugin is active
 *
 * @return bool True if KiviCare Pro is active, false otherwise
 */
function isKiviCareProActive()
{
    return defined('KIVI_CARE_PRO_VERSION') ? true : false;
}

/**
 * Check if KiviCare Telemed addon is active
 *
 * @return bool True if KiviCare Telemed addon is active, false otherwise
 */
function isKiviCareTelemedActive()
{
    return defined('KIVICARE_TELEMED_VERSION') ? true : false;
}

/**
 * Get addon version by plugin slug using constant naming convention.
 *
 * @param string $slug The addon slug (e.g., 'kivicare-pro')
 * @return string|null The addon version or null if constant not defined
 */
function kcGetAddonVersion($slug)
{
    $const_name = strtoupper(str_replace('-', '_', $slug)) . '_VERSION';

    if (defined($const_name)) {
        return constant($const_name);
    }

    return null;
}

/**
 * Check if KiviCare Google Meet addon is active
 *
 * @return bool True if KiviCare Google Meet addon is active, false otherwise
 */
function isKiviCareGoogleMeetActive()
{
    return defined('KIVICARE_GOOGLE_MEET_ADDON_VERSION') ? true : false;
}

/**
 * Check if KiviCare Razorpay addon is active
 *
 * @return bool True if KiviCare Razorpay addon is active, false otherwise
 */
function isKiviCareRazorpayActive()
{
    return defined('KIVICARE_RAZORPAY_ADDON_VERSION') || defined('KIVI_CARE_RAZORPAY_VERSION');
}

/**
 * Check if KiviCare Stripe Payment addon is active
 *
 * @return bool True if KiviCare Stripe Payment addon is active, false otherwise
 */
function isKiviCareStripepayActive()
{
    return defined('KIVI_CARE_STRIPE_ADDON_VERSION') ? true : false;
}

/**
 * Check if KiviCare API addon is active
 *
 * @return bool True if KiviCare API addon is active, false otherwise
 */
function isKiviCareAPIActive()
{
    return defined('KIVICARE_API_VERSION') ? true : false;
}

/**
 * Check if KiviCare Body Chart addon is active
 *
 * @return bool True if KiviCare Body Chart addon is active, false otherwise
 */
function isKiviCareBodyChartActive()
{
    return defined('KIVICARE_BODYCHART_ADDON_VERSION') ? true : false;
}

/**
 * Check if KiviCare Webhooks addon is active
 *
 * @return bool True if KiviCare Webhooks addon is active, false otherwise
 */
function isKiviCareWebhooksAddonActive()
{
    return defined('KIVICARE_WEBHOOKS_ADDON_VERSION') ? true : false;
}

/**
 * Check if WooCommerce plugin is active
 * 
 * @return bool True if WooCommerce is active, false otherwise
 */
function isKiviCareWooCommerceActive()
{
    return is_plugin_active('woocommerce/woocommerce.php');
}

/**
 * Check if appointment multi-file upload is enabled
 *
 * @return bool True if multi-file upload for appointments is enabled, false otherwise
 */
function kcAppointmentMultiFileUploadEnable()
{
    $option = KCOption::get('appointment_multi_file_upload');
    return !empty($option) && $option === 'on';
}

/**
 * Get enabled KiviCare modules
 *
 * @return array Array of enabled modules
 */
function kcGetModules()
{
    $modules = KCOption::get('modules');
    if (is_string($modules)) {
        $decoded = json_decode($modules, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $modules = $decoded;
        }
    }
    return !empty($modules) ? (array) $modules : [];
}

/**
 * Convert a string to snake case with KC prefix
 *
 * @param string $string The string to convert
 * @return string The snake cased string with KC prefix
 */
function kc_snake_case($string)
{
    // First, convert camelCase to kc_snake_case
    $string = preg_replace('/([a-z])([A-Z])/', '$1_$2', $string);

    // Convert spaces, dashes and special characters to underscores
    $string = preg_replace('/[\s\-]+/', '_', $string);

    // Remove any remaining special characters
    $string = preg_replace('/[^a-zA-Z0-9_]/', '', $string);

    // Convert to lowercase
    $string = strtolower($string);

    // Add kc prefix if not already present
    if (!str_starts_with($string, 'kc_')) {
        $string = 'kc_' . $string;
    }

    return $string;
}

/**
 * Get JSON translation files for a domain
 *
 * @param string $domain The text domain to search for
 * @return array Array of JSON translation file paths
 */
function kc_get_json_translation_files($domain)
{
    $cached_mofiles = [];

    $locations = [
        WP_LANG_DIR . '/themes',
        WP_LANG_DIR . '/plugins',
        KIVI_CARE_DIR . '/languages'
    ];

    foreach ($locations as $location) {
        $mofiles = glob($location . '/*.json');

        if (!$mofiles) {
            continue;
        }

        $cached_mofiles = array_merge($cached_mofiles, $mofiles);
    }

    $locale = determine_locale();

    $result = [];

    foreach ($cached_mofiles as $single_file) {
        if (strpos($single_file, $locale) === false) {
            continue;
        }

        $result[] = $single_file;
    }

    return $result;
}

/**
 * Get JED-formatted locale data for a domain
 *
 * @param string $domain The text domain
 * @return array The locale data in JED format
 */
function kc_get_jed_locale_data($domain)
{
    static $locale = [];

    if (isset($locale[$domain])) {
        return $locale[$domain];
    }

    $translations = get_translations_for_domain($domain);

    $locale[$domain] = [
        '' => [
            'domain' => $domain,
            'lang' => get_user_locale(),
        ]
    ];

    if (!empty($translations->headers['Plural-Forms'])) {
        $locale[$domain]['']['plural_forms'] = $translations->headers['Plural-Forms'];
    }

    foreach ($translations->entries as $msgid => $entry) {
        $locale[$domain][$entry->key()] = $entry->translations;
    }

    foreach (kc_get_json_translation_files($domain) as $file_path) {
        $parsed_json = json_decode(
            call_user_func(
                'file' . '_get_contents',
                $file_path
            ),
            true
        );

        if (
            !$parsed_json
            ||
            !isset($parsed_json['locale_data']['messages'])
        ) {
            continue;
        }

        foreach ($parsed_json['locale_data']['messages'] as $msgid => $entry) {
            if (empty($msgid)) {
                continue;
            }

            $locale[$domain][$msgid] = $entry;
        }
    }

    return $locale[$domain];
}
function kcGetSetupWizardOptions()
{
    // Default steps
    $steps = [
        [
            'name' => 'getting_started',
            'completed' => false
        ],
        [
            'name' => 'clinic',
            'completed' => false
        ],
        [
            'name' => 'clinic_admin',
            'completed' => false
        ]
    ];

    // If a global completion flag is stored, mark all steps completed
    $all_completed_old = (bool) get_option('clinic_setup_wizard', false);
    $all_completed_wpoption = (bool) get_option('kc_setup_wizard_completed', false);
    $all_completed = $all_completed_old || $all_completed_wpoption;
    if ($all_completed) {
        foreach ($steps as &$step) {
            $step['completed'] = true;
        }
        unset($step);
        return $steps;
    }

    // Merge per-step completion from saved config if available
    $saved_config = kcGetStepConfig();
    if (is_array($saved_config) && !empty($saved_config)) {
        $status_by_name = [];

        // Support both associative form: ['clinic' => true] and array of step objects
        if (array_values($saved_config) !== $saved_config) {
            foreach ($saved_config as $name => $completed) {
                $status_by_name[$name] = (bool) $completed;
            }
        } else {
            foreach ($saved_config as $step_item) {
                if (is_array($step_item) && isset($step_item['name'])) {
                    $status_by_name[$step_item['name']] = !empty($step_item['completed']);
                }
            }
        }

        foreach ($steps as &$step) {
            if (isset($status_by_name[$step['name']])) {
                $step['completed'] = (bool) $status_by_name[$step['name']];
            }
        }
        unset($step);
    }

    return $steps;
}


function kcValidateRequest($rules, $request, $message = [])
{
    $error_messages = [];
    $required_message = __(' field is required', 'kivicare-clinic-management-system');
    $email_message = __(' has invalid email address', 'kivicare-clinic-management-system');
    $date_message = __(' has invalid date', 'kivicare-clinic-management-system');
    if (count($rules)) {
        foreach ($rules as $key => $rule) {
            if (strpos($rule, '|') !== false) {
                $ruleArray = explode('|', $rule);
                foreach ($ruleArray as $r) {
                    if ($r === 'required') {
                        if (!isset($request[$key])) {
                            $error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $required_message;
                        }
                    } elseif ($r === 'email') {
                        if (isset($request[$key])) {
                            if (!filter_var($request[$key], FILTER_VALIDATE_EMAIL)) {
                                $error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $email_message;
                            }
                        }
                    } elseif ($r === 'date') {
                        if (isset($request[$key])) {
                            $dateObj = DateTime::createFromFormat('Y-m-d', $request[$key]);
                            if ($dateObj === false || $dateObj->format('Y-m-d') !== $request[$key]) {
                                $error_messages[] = isset($message[$key])
                                    ? $message[$key]
                                    : str_replace('_', ' ', $key) . $date_message;
                            }
                        }
                    }
                }
            } else {
                if ($rule === 'required') {
                    if (!isset($request[$key])) {
                        $error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $required_message;
                    }
                } elseif (!empty($r) && $r === 'email') {
                    if (isset($request[$key])) {
                        if (!filter_var($request[$key], FILTER_VALIDATE_EMAIL)) {
                            $error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $email_message;
                        }
                    }
                }
            }
        }
    }

    return $error_messages;
}

function kcSanitizeData($data)
{
    // If not an array, sanitize single value
    if (!is_array($data)) {
        return kcSanitizeSingleValue($data);
    }

    $sanitized_data = [];

    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $sanitized_data[$key] = kcSanitizeData($value);
        } else {
            $sanitized_data[$key] = kcSanitizeSingleValue($value, $key);
        }
    }

    return $sanitized_data;
}

/**
 * Sanitize a single value based on its type and key context
 *
 * @param mixed  $value The value to sanitize
 * @param string $key   The key name for contextual sanitation
 * @return mixed        The sanitized value
 */
function kcSanitizeSingleValue($value, $key = '')
{
    if (is_bool($value)) {
        return (bool) $value;
    }

    if (is_numeric($value)) {
        return is_float($value + 0) ? (float) $value : (int) $value;
    }

    if (is_string($value)) {
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return sanitize_email($value);
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return esc_url_raw($value);
        }

        if (in_array($key, ['post_content', 'html_content', 'email_body'], true)) {
            return wp_kses_post($value);
        }

        if (preg_match('/(textarea|description|message|comment|note)/i', $key)) {
            return sanitize_textarea_field($value);
        }

        return sanitize_text_field($value);
    }

    return $value; // Return as-is for unsupported types (e.g., array, object)
}


function kcGetStepConfig()
{
    return KCOption::get('setup_config', []);
}


/**
 * Recursively sanitize a mixed array of inputs based on key hints and HTML detection
 *
 * @param array $array Input array to sanitize
 * @param array $allow_key_sanitize List of keywords to specially sanitize (e.g., ['email'])
 * @return array Sanitized array
 */
function kcRecursiveSanitizeTextField($array, $allow_key_sanitize = ['email'])
{
    $sanitized = [];

    foreach ($array as $key => $value) {

        if ($value === '') {
            $sanitized[$key] = null;
        } elseif (is_array($value)) {
            $sanitized[$key] = kcRecursiveSanitizeTextField($value, $allow_key_sanitize);
        } elseif (is_object($value)) {
            $sanitized[$key] = $value;
        } elseif (preg_match("/<[^<]+>/", $value)) {
            // Sanitize HTML (e.g., <p>some content</p>)
            $sanitized[$key] = wp_kses_post($value);
        } elseif (in_array('email', $allow_key_sanitize, true) && stripos($key, 'email') !== false) {
            $sanitized[$key] = sanitize_email($value);
        } elseif (stripos($key, '_ajax_nonce') !== false) {
            $sanitized[$key] = sanitize_key($value);
        } else {
            $sanitized[$key] = sanitize_text_field($value);
        }
    }

    return $sanitized;
}


function kcThrowExceptionResponse($message, $status)
{
    return [
        'message' => $message,
        'status' => $status,
        'data' => []
    ];
}


function kcGetFormatedDate($date)
{
    $dateFormat = get_option('date_format', true);

    // Get the WordPress timezone setting
    $timezone = wp_timezone();

    // Create a DateTime object with the given date and set the timezone to WordPress timezone
    $dateTime = new DateTime($date, $timezone);

    // Convert to timestamp
    $timestamp = $dateTime->getTimestamp();

    // Use wp_date with the correct timestamp and WordPress timezone
    return wp_date($dateFormat, $timestamp);
}

/**
 * Generate unique username
 * 
 * @param string $base_name
 * @return string
 */
function kcGenerateUsername($base_name)
{
    $username = sanitize_user($base_name);
    $counter = 1;

    while (username_exists($username)) {
        $username = sanitize_user($base_name . $counter);
        $counter++;
    }

    return $username;
}

/**
 * Generate random string for password
 * 
 * @param int $length
 * @return string
 */
function kcGenerateRandomString($length = 12)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[wp_rand(0, $charactersLength - 1)];
    }

    return $randomString;
}

/**
 * Decode HTML entities and specific symbols in a string.
 *
 * @param string $input
 * @return string
 */
function KCdecodeHtmlEntities($input)
{
    return html_entity_decode($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Helper function to validate time format (HH:MM)
 */
function validate_time_format($time)
{
    return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
}




function kcCountryCurrencyList($search = '')
{
    return [
        ['value' => 'AED', 'label' => 'United Arab Emirates dirham'],
        ['value' => 'AFN', 'label' => 'Afghan afghani'],
        ['value' => 'ALL', 'label' => 'Albanian lek'],
        ['value' => 'AMD', 'label' => 'Armenian dram'],
        ['value' => 'ANG', 'label' => 'Netherlands Antillean guilder'],
        ['value' => 'AOA', 'label' => 'Angolan kwanza'],
        ['value' => 'ARS', 'label' => 'Argentine peso'],
        ['value' => 'AUD', 'label' => 'Australian dollar'],
        ['value' => 'AWG', 'label' => 'Aruban florin'],
        ['value' => 'AZN', 'label' => 'Azerbaijani manat'],
        ['value' => 'BAM', 'label' => 'Bosnia and Herzegovina convertible mark'],
        ['value' => 'BBD', 'label' => 'Barbadian dollar'],
        ['value' => 'BDT', 'label' => 'Bangladeshi taka'],
        ['value' => 'BGN', 'label' => 'Bulgarian lev'],
        ['value' => 'BHD', 'label' => 'Bahraini dinar'],
        ['value' => 'BIF', 'label' => 'Burundian franc'],
        ['value' => 'BMD', 'label' => 'Bermudian dollar'],
        ['value' => 'BND', 'label' => 'Brunei dollar'],
        ['value' => 'BOB', 'label' => 'Bolivian boliviano'],
        ['value' => 'BRL', 'label' => 'Brazilian real'],
        ['value' => 'BSD', 'label' => 'Bahamian dollar'],
        ['value' => 'BTN', 'label' => 'Bhutanese ngultrum'],
        ['value' => 'BWP', 'label' => 'Botswana pula'],
        ['value' => 'BYR', 'label' => 'Belarusian ruble (old)'],
        ['value' => 'BYN', 'label' => 'Belarusian ruble'],
        ['value' => 'BZD', 'label' => 'Belize dollar'],
        ['value' => 'CAD', 'label' => 'Canadian dollar'],
        ['value' => 'CDF', 'label' => 'Congolese franc'],
        ['value' => 'CHF', 'label' => 'Swiss franc'],
        ['value' => 'CLP', 'label' => 'Chilean peso'],
        ['value' => 'CNY', 'label' => 'Chinese yuan'],
        ['value' => 'COP', 'label' => 'Colombian peso'],
        ['value' => 'CRC', 'label' => 'Costa Rican col&oacute;n'],
        ['value' => 'CUC', 'label' => 'Cuban convertible peso'],
        ['value' => 'CUP', 'label' => 'Cuban peso'],
        ['value' => 'CVE', 'label' => 'Cape Verdean escudo'],
        ['value' => 'CZK', 'label' => 'Czech koruna'],
        ['value' => 'DJF', 'label' => 'Djiboutian franc'],
        ['value' => 'DKK', 'label' => 'Danish krone'],
        ['value' => 'DOP', 'label' => 'Dominican peso'],
        ['value' => 'DZD', 'label' => 'Algerian dinar'],
        ['value' => 'EGP', 'label' => 'Egyptian pound'],
        ['value' => 'ERN', 'label' => 'Eritrean nakfa'],
        ['value' => 'ETB', 'label' => 'Ethiopian birr'],
        ['value' => 'EUR', 'label' => 'Euro'],
        ['value' => 'FJD', 'label' => 'Fijian dollar'],
        ['value' => 'FKP', 'label' => 'Falkland Islands pound'],
        ['value' => 'GBP', 'label' => 'Pound sterling'],
        ['value' => 'GEL', 'label' => 'Georgian lari'],
        ['value' => 'GGP', 'label' => 'Guernsey pound'],
        ['value' => 'GHS', 'label' => 'Ghana cedi'],
        ['value' => 'GIP', 'label' => 'Gibraltar pound'],
        ['value' => 'GMD', 'label' => 'Gambian dalasi'],
        ['value' => 'GNF', 'label' => 'Guinean franc'],
        ['value' => 'GTQ', 'label' => 'Guatemalan quetzal'],
        ['value' => 'GYD', 'label' => 'Guyanese dollar'],
        ['value' => 'HKD', 'label' => 'Hong Kong dollar'],
        ['value' => 'HNL', 'label' => 'Honduran lempira'],
        ['value' => 'HRK', 'label' => 'Croatian kuna'],
        ['value' => 'HTG', 'label' => 'Haitian gourde'],
        ['value' => 'HUF', 'label' => 'Hungarian forint'],
        ['value' => 'IDR', 'label' => 'Indonesian rupiah'],
        ['value' => 'ILS', 'label' => 'Israeli new shekel'],
        ['value' => 'IMP', 'label' => 'Manx pound'],
        ['value' => 'INR', 'label' => 'Indian rupee'],
        ['value' => 'IQD', 'label' => 'Iraqi dinar'],
        ['value' => 'IRR', 'label' => 'Iranian rial'],
        ['value' => 'IRT', 'label' => 'Iranian toman'],
        ['value' => 'ISK', 'label' => 'Icelandic kr&oacute;na'],
        ['value' => 'JEP', 'label' => 'Jersey pound'],
        ['value' => 'JMD', 'label' => 'Jamaican dollar'],
        ['value' => 'JOD', 'label' => 'Jordanian dinar'],
        ['value' => 'JPY', 'label' => 'Japanese yen'],
        ['value' => 'KES', 'label' => 'Kenyan shilling'],
        ['value' => 'KGS', 'label' => 'Kyrgyzstani som'],
        ['value' => 'KHR', 'label' => 'Cambodian riel'],
        ['value' => 'KMF', 'label' => 'Comorian franc'],
        ['value' => 'KPW', 'label' => 'North Korean won'],
        ['value' => 'KRW', 'label' => 'South Korean won'],
        ['value' => 'KWD', 'label' => 'Kuwaiti dinar'],
        ['value' => 'KYD', 'label' => 'Cayman Islands dollar'],
        ['value' => 'KZT', 'label' => 'Kazakhstani tenge'],
        ['value' => 'LAK', 'label' => 'Lao kip'],
        ['value' => 'LBP', 'label' => 'Lebanese pound'],
        ['value' => 'LKR', 'label' => 'Sri Lankan rupee'],
        ['value' => 'LRD', 'label' => 'Liberian dollar'],
        ['value' => 'LSL', 'label' => 'Lesotho loti'],
        ['value' => 'LYD', 'label' => 'Libyan dinar'],
        ['value' => 'MAD', 'label' => 'Moroccan dirham'],
        ['value' => 'MDL', 'label' => 'Moldovan leu'],
        ['value' => 'MGA', 'label' => 'Malagasy ariary'],
        ['value' => 'MKD', 'label' => 'Macedonian denar'],
        ['value' => 'MMK', 'label' => 'Burmese kyat'],
        ['value' => 'MNT', 'label' => 'Mongolian t&ouml;gr&ouml;g'],
        ['value' => 'MOP', 'label' => 'Macanese pataca'],
        ['value' => 'MRU', 'label' => 'Mauritanian ouguiya'],
        ['value' => 'MUR', 'label' => 'Mauritian rupee'],
        ['value' => 'MVR', 'label' => 'Maldivian rufiyaa'],
        ['value' => 'MWK', 'label' => 'Malawian kwacha'],
        ['value' => 'MXN', 'label' => 'Mexican peso'],
        ['value' => 'MYR', 'label' => 'Malaysian ringgit'],
        ['value' => 'MZN', 'label' => 'Mozambican metical'],
        ['value' => 'NAD', 'label' => 'Namibian dollar'],
        ['value' => 'NGN', 'label' => 'Nigerian naira'],
        ['value' => 'NIO', 'label' => 'Nicaraguan c&oacute;rdoba'],
        ['value' => 'NOK', 'label' => 'Norwegian krone'],
        ['value' => 'NPR', 'label' => 'Nepalese rupee'],
        ['value' => 'NZD', 'label' => 'New Zealand dollar'],
        ['value' => 'OMR', 'label' => 'Omani rial'],
        ['value' => 'PAB', 'label' => 'Panamanian balboa'],
        ['value' => 'PEN', 'label' => 'Sol'],
        ['value' => 'PGK', 'label' => 'Papua New Guinean kina'],
        ['value' => 'PHP', 'label' => 'Philippine peso'],
        ['value' => 'PKR', 'label' => 'Pakistani rupee'],
        ['value' => 'PLN', 'label' => 'Polish z&#x142;oty'],
        ['value' => 'PRB', 'label' => 'Transnistrian ruble'],
        ['value' => 'PYG', 'label' => 'Paraguayan guaran&iacute;'],
        ['value' => 'QAR', 'label' => 'Qatari riyal'],
        ['value' => 'RON', 'label' => 'Romanian leu'],
        ['value' => 'RSD', 'label' => 'Serbian dinar'],
        ['value' => 'RUB', 'label' => 'Russian ruble'],
        ['value' => 'RWF', 'label' => 'Rwandan franc'],
        ['value' => 'SAR', 'label' => 'Saudi riyal'],
        ['value' => 'SBD', 'label' => 'Solomon Islands dollar'],
        ['value' => 'SCR', 'label' => 'Seychellois rupee'],
        ['value' => 'SDG', 'label' => 'Sudanese pound'],
        ['value' => 'SEK', 'label' => 'Swedish krona'],
        ['value' => 'SGD', 'label' => 'Singapore dollar'],
        ['value' => 'SHP', 'label' => 'Saint Helena pound'],
        ['value' => 'SLL', 'label' => 'Sierra Leonean leone'],
        ['value' => 'SOS', 'label' => 'Somali shilling'],
        ['value' => 'SRD', 'label' => 'Surinamese dollar'],
        ['value' => 'SSP', 'label' => 'South Sudanese pound'],
        ['value' => 'STN', 'label' => 'S&atilde;o Tom&eacute; and Pr&iacute;ncipe dobra'],
        ['value' => 'SYP', 'label' => 'Syrian pound'],
        ['value' => 'SZL', 'label' => 'Swazi lilangeni'],
        ['value' => 'THB', 'label' => 'Thai baht'],
        ['value' => 'TJS', 'label' => 'Tajikistani somoni'],
        ['value' => 'TMT', 'label' => 'Turkmenistan manat'],
        ['value' => 'TND', 'label' => 'Tunisian dinar'],
        ['value' => 'TOP', 'label' => 'Tongan pa&#x2bb;anga'],
        ['value' => 'TRY', 'label' => 'Turkish lira'],
        ['value' => 'TTD', 'label' => 'Trinidad and Tobago dollar'],
        ['value' => 'TWD', 'label' => 'New Taiwan dollar'],
        ['value' => 'TZS', 'label' => 'Tanzanian shilling'],
        ['value' => 'UAH', 'label' => 'Ukrainian hryvnia'],
        ['value' => 'UGX', 'label' => 'Ugandan shilling'],
        ['value' => 'USD', 'label' => 'United States (US) dollar'],
        ['value' => 'UYU', 'label' => 'Uruguayan peso'],
        ['value' => 'UZS', 'label' => 'Uzbekistani som'],
        ['value' => 'VEF', 'label' => 'Venezuelan bol&iacute;var'],
        ['value' => 'VES', 'label' => 'Bol&iacute;var soberano'],
        ['value' => 'VND', 'label' => 'Vietnamese &#x111;&#x1ed3;ng'],
        ['value' => 'VUV', 'label' => 'Vanuatu vatu'],
        ['value' => 'WST', 'label' => 'Samoan t&#x101;l&#x101;'],
        ['value' => 'XAF', 'label' => 'Central African CFA franc'],
        ['value' => 'XCD', 'label' => 'East Caribbean dollar'],
        ['value' => 'XOF', 'label' => 'West African CFA franc'],
        ['value' => 'XPF', 'label' => 'CFP franc'],
        ['value' => 'YER', 'label' => 'Yemeni rial'],
        ['value' => 'ZAR', 'label' => 'South African rand'],
        ['value' => 'ZMW', 'label' => 'Zambian kwacha'],
    ];
}
/**
 * Get available language translations with country code
 *
 * @return array
 */
function getAvailableLanguages()
{
    $plugin_available_language = wp_get_installed_translations('plugins');

    // Default language (English - United States)
    $kc_lang_array = [
        [
            'lang' => 'en_US',
            'label' => 'English (United States)',
            'countryCode' => 'us',
        ]
    ];
    $lang_countries = include KIVI_CARE_DIR . '/static/KCLangCountry.php';

    if (isset($plugin_available_language['kivicare-clinic-management-system'])) {
        require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        $wp_available_language = wp_get_available_translations();

        foreach ($plugin_available_language['kivicare-clinic-management-system'] as $key => $value) {
            if ($key === 'en_US')
                continue;

            // Handle cases where WP translation data exists
            if (isset($wp_available_language[$key])) {
                $native_name = $wp_available_language[$key]['native_name'];
                $lang_country = $lang_countries[$key] ?? '';
                $kc_lang_array[] = [
                    'label' => $native_name,
                    'lang' => $key,
                    'countryCode' => $lang_country['countryCode'] ?? ''
                ];
            }
        }
    }

    return $kc_lang_array;
}



/**
 * Generate WooCommerce API keys for a client application.
 *
 * @param string $app_name    Name of the application.
 * @param int    $app_user_id User ID for which to generate API keys.
 * @param string $scope       Permission scope (read, write, read_write).
 *
 * @return array API credentials array.
 */
function kc_woo_generate_client_auth($app_name, $app_user_id, $scope)
{

    if (!function_exists('is_plugin_active') || !is_plugin_active('woocommerce/woocommerce.php')) {
        return array(
            'consumer_key' => null,
            'consumer_secret' => null,
        );
    }

    global $wpdb;

    $table_name = $wpdb->prefix . 'woocommerce_api_keys';

    // Delete existing API keys for this user.
    $wpdb->delete(
        $table_name,
        array(
            'user_id' => (int) $app_user_id,
        ),
        array('%d')
    );

    // Clean application name.
    $clean_app_name = wc_trim_string(wc_clean($app_name), 170);

    $description = sprintf(
        '%1$s - API (%2$s)',
        $clean_app_name,
        gmdate('Y-m-d H:i:s')
    );

    // Validate permission scope.
    $valid_scopes = array('read', 'write', 'read_write');
    $permissions = in_array($scope, $valid_scopes, true) ? sanitize_text_field($scope) : 'read';

    // Generate API keys.
    $consumer_key = 'ck_' . wc_rand_hash();
    $consumer_secret = 'cs_' . wc_rand_hash();

    // Insert into database.
    $wpdb->insert(
        $table_name,
        array(
            'user_id' => (int) $app_user_id,
            'description' => $description,
            'permissions' => $permissions,
            'consumer_key' => wc_api_hash($consumer_key),
            'consumer_secret' => $consumer_secret,
            'truncated_key' => substr($consumer_key, -7),
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s')
    );

    return array(
        'key_id' => (int) $wpdb->insert_id,
        'user_id' => (int) $app_user_id,
        'consumer_key' => $consumer_key,
        'consumer_secret' => $consumer_secret,
        'key_permissions' => $permissions,
    );
}
function kcGetFormatedTime($time)
{
    $timeFormat = get_option('time_format');
    
    // Create a DateTime object with WordPress timezone
    try {
        // If time is in H:i:s format, prepend today's date
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time)) {
            $timeStr = gmdate('Y-m-d') . ' ' . $time;
        } else {
            $timeStr = $time;
        }
        
        // Create DateTime with WordPress timezone
        $dateTime = new DateTime($timeStr, wp_timezone());
        
        // Calculate English AM/PM based on the timezone-aware time
        $englishAmPmLower = $dateTime->format('a');
        $englishAmPmUpper = $dateTime->format('A');
        
        // Escape characters for wp_date (e.g., am -> \a\m)
        $escapedLower = '\\' . implode('\\', str_split($englishAmPmLower));
        $escapedUpper = '\\' . implode('\\', str_split($englishAmPmUpper));
        
        // Replace 'a' and 'A' in the format string with their escaped English values
        $customTimeFormat = str_replace(
            ['a', 'A'],
            [$escapedLower, $escapedUpper],
            $timeFormat
        );
        
        // Format using WordPress date function with the custom format
        return wp_date($customTimeFormat, $dateTime->getTimestamp());
    } catch (Exception $e) {
        // Fallback to original behavior if parsing fails
        return wp_date($timeFormat, strtotime($time));
    }
}

/**
 * Get WooCommerce Order ID by Appointment ID
 *
 * @param int $appointment_id The appointment ID
 * @return int|null The WooCommerce Order ID, or null if not found
 */
function kcGetWoocommerceOrderIdByAppointmentId($appointment_id)
{
    global $wpdb;

    $order_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_id 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = %s 
            AND meta_value = %d 
        LIMIT 1",
            'kivicare_appointment_id',
            $appointment_id
        )
    );

    return $order_id ? intval($order_id) : null;
}
