<?php

use App\baseClasses\KCBase;
use App\models\KCAppointment;
use App\models\KCAppointmentServiceMapping;
use App\models\KCClinicSession;
use App\models\KCCustomField;
use App\models\KCCustomFieldData;
use App\models\KCDoctorClinicMapping;
use App\models\KCPatientClinicMapping;
use App\models\KCPatientEncounter;
use App\models\KCService;
use App\models\KCCustomForm;
use App\models\KCCustomFormData;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

function kcUpdateFields($table_name, $new_fields)
{
    foreach ($new_fields as $key => $nf) {
        $new_field = "ALTER TABLE `{$table_name}` ADD `{$key}` {$nf};";
        require_once ABSPATH . '/wp-admin/includes/upgrade.php';
        maybe_add_column($table_name, $key, $new_field);
    }
}


function kcUpdateFieldsDataType($table_name, $new_fields)
{
    global $wpdb;
    foreach ($new_fields as $key => $nf) {
        $new_field = "ALTER TABLE `{$table_name}` MODIFY COLUMN `{$key}` {$nf};";
        $wpdb->query($new_field);
    }
}

function kcValidateRequest($rules, $request, $message = [])
{
    $error_messages = [];
    $required_message = __(' field is required', 'kc-lang');
    $email_message = __(' has invalid email address', 'kc-lang');
    $date_message  = __(' has invalid date','kc-lang');
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

function kcRecursiveSanitizeTextField($array,$allow_key_sanitize=['email'])
{
    $filterParameters = [];
    foreach ($array as $key => $value) {

        if ($value === '') {
            $filterParameters[$key] = null;
        } else {
            if (is_array($value)) {
                $filterParameters[$key] = kcRecursiveSanitizeTextField($value);
            } else {
                if (is_object($value)) {
                    $filterParameters[$key] = $value;
                } else if (preg_match("/<[^<]+>/", $value, $m) !== 0) {
                    $filterParameters[$key] = kcSanitizeHTML($value);
                } elseif (in_array(['email'],$allow_key_sanitize) && strpos(strtolower($key), 'email') !== false) {
                    $filterParameters[$key] = kcSanitizeHTML(sanitize_email($value));
                } elseif ( strpos(strtolower($key), '_ajax_nonce') !== false) {
                    $filterParameters[$key] = kcSanitizeHTML(sanitize_key($value));
                } else {
                    $filterParameters[$key] = kcSanitizeHTML(sanitize_text_field($value));
                }
            }
        }
    }

    return $filterParameters;
}

function kcSanitizeHTML($text)
{
    return wp_kses($text, kcAllowedHtml());
}
function kcRemoveBlankKeyFromArray($data)
{
    foreach ($data as $key => $value) {
        if ($key === null || $key === '') {
            unset($data[$key]);
        }
    }
    return $data;
}

function kcGenerateString($length_of_string = 10)
{
    // String of all alphanumeric character
    $str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    return substr(str_shuffle($str_result), 0, $length_of_string);
}

function kcGenerateUsername($first_name)
{

    if (empty($first_name)) {
        return "";
    }
    $randomString = kcGenerateString(6);
    $first_name = str_replace(' ', '_', $first_name);
    return apply_filters('kivicare_generate_username', $first_name . '_' . $randomString);
}

function kcDebugLog($log, $module = 'Module -1')
{

    $file_log = $log;
    if (gettype($file_log) !== 'string') {
        $file_log = json_encode($file_log);
    }
    $log_detail = array(
        'Log Module' => $module,
        'Variable Type' => gettype($log)
    );
    if (file_exists(KIVI_CARE_DIR . '/log/kivicare_log.txt')) {
        error_log(
            "\n" . json_encode($log_detail) .
            "\n" . $file_log .
            "\n" . "----------------------------------------------",
            3,
            KIVI_CARE_DIR . '/log/kivicare_log.txt'
        );
    } else {
        fopen(KIVI_CARE_DIR . '/log/kivicare_log.txt', 'w');

        error_log(
            "\n" . json_encode($log_detail) .
            "\n" . $file_log .
            "\n" . "----------------------------------------------",
            3,
            KIVI_CARE_DIR . '/log/kivicare_log.txt'
        );
    }
}

function kcGetUserData($user_id)
{

    $userObj = new WP_User($user_id);
    $user = $userObj->data;
    $user_data = get_user_meta($userObj->ID, 'basic_data', true);
    if ($user_data) {
        $user_data = json_decode($user_data);
        $user->basicData = $user_data;
    }

    unset($user->user_pass);
    return $user;
}

function kcGetSetupWizardOptions()
{
    return collect([
        [
            'icon' => "fa fa-info fa-lg",
            'name' => "getting_started",
            'title' => __("Welcome", "kc-lang"),
            'subtitle' => "",
            'prevStep' => '',
            'routeName' => 'setup.step1',
            'nextStep' => 'setup.step3',
            'completed' => false
        ],
        [
            'icon' => "fa fa-clinic-medical fa-lg",
            'name' => "clinic",
            'title' => __("Clinic Detail", "kc-lang"),
            'prevStep' => 'setup.step1',
            'routeName' => 'setup.step3',
            'nextStep' => 'setup.clinic.admin',
            'subtitle' => "",
            'completed' => false
        ],
        [
            'icon' => "fa fa-user fa-lg",
            'name' => "clinic_admin",
            'title' => __("Clinic Admin", "kc-lang"),
            'prevStep' => 'setup.step3',
            'routeName' => 'setup.clinic.admin',
            'nextStep' => 'setup.step6',
            'subtitle' => "",
            'completed' => false
        ]
    ]);
}

function kcGetModules()
{
    $prefix = KIVI_CARE_PREFIX;
    $modules = get_option($prefix . 'modules');
    if ($modules) {
        return json_decode($modules);
    } else {
        return '';
    }
}

function kcGetStepConfig()
{
    $prefix = KIVI_CARE_PREFIX;
    $modules = get_option($prefix . 'setup_config');
    if ($modules) {
        return json_decode($modules);
    } else {
        return '';
    }
}

function kcGetStartOfWeek()
{
    $start_of_week = get_option('start_of_week');
    if ($start_of_week) {
        return json_decode($start_of_week);
    } else {
        return '';
    }
}

function kcCountryCurrencyList($search = '')
{
    return array(
        'AED' => 'United Arab Emirates dirham',
        'AFN' => 'Afghan afghani',
        'ALL' => 'Albanian lek',
        'AMD' => 'Armenian dram',
        'ANG' => 'Netherlands Antillean guilder',
        'AOA' => 'Angolan kwanza',
        'ARS' => 'Argentine peso',
        'AUD' => 'Australian dollar',
        'AWG' => 'Aruban florin',
        'AZN' => 'Azerbaijani manat',
        'BAM' => 'Bosnia and Herzegovina convertible mark',
        'BBD' => 'Barbadian dollar',
        'BDT' => 'Bangladeshi taka',
        'BGN' => 'Bulgarian lev',
        'BHD' => 'Bahraini dinar',
        'BIF' => 'Burundian franc',
        'BMD' => 'Bermudian dollar',
        'BND' => 'Brunei dollar',
        'BOB' => 'Bolivian boliviano',
        'BRL' => 'Brazilian real',
        'BSD' => 'Bahamian dollar',
        'BTN' => 'Bhutanese ngultrum',
        'BWP' => 'Botswana pula',
        'BYR' => 'Belarusian ruble (old)',
        'BYN' => 'Belarusian ruble',
        'BZD' => 'Belize dollar',
        'CAD' => 'Canadian dollar',
        'CDF' => 'Congolese franc',
        'CHF' => 'Swiss franc',
        'CLP' => 'Chilean peso',
        'CNY' => 'Chinese yuan',
        'COP' => 'Colombian peso',
        'CRC' => 'Costa Rican col&oacute;n',
        'CUC' => 'Cuban convertible peso',
        'CUP' => 'Cuban peso',
        'CVE' => 'Cape Verdean escudo',
        'CZK' => 'Czech koruna',
        'DJF' => 'Djiboutian franc',
        'DKK' => 'Danish krone',
        'DOP' => 'Dominican peso',
        'DZD' => 'Algerian dinar',
        'EGP' => 'Egyptian pound',
        'ERN' => 'Eritrean nakfa',
        'ETB' => 'Ethiopian birr',
        'EUR' => 'Euro',
        'FJD' => 'Fijian dollar',
        'FKP' => 'Falkland Islands pound',
        'GBP' => 'Pound sterling',
        'GEL' => 'Georgian lari',
        'GGP' => 'Guernsey pound',
        'GHS' => 'Ghana cedi',
        'GIP' => 'Gibraltar pound',
        'GMD' => 'Gambian dalasi',
        'GNF' => 'Guinean franc',
        'GTQ' => 'Guatemalan quetzal',
        'GYD' => 'Guyanese dollar',
        'HKD' => 'Hong Kong dollar',
        'HNL' => 'Honduran lempira',
        'HRK' => 'Croatian kuna',
        'HTG' => 'Haitian gourde',
        'HUF' => 'Hungarian forint',
        'IDR' => 'Indonesian rupiah',
        'ILS' => 'Israeli new shekel',
        'IMP' => 'Manx pound',
        'INR' => 'Indian rupee',
        'IQD' => 'Iraqi dinar',
        'IRR' => 'Iranian rial',
        'IRT' => 'Iranian toman',
        'ISK' => 'Icelandic kr&oacute;na',
        'JEP' => 'Jersey pound',
        'JMD' => 'Jamaican dollar',
        'JOD' => 'Jordanian dinar',
        'JPY' => 'Japanese yen',
        'KES' => 'Kenyan shilling',
        'KGS' => 'Kyrgyzstani som',
        'KHR' => 'Cambodian riel',
        'KMF' => 'Comorian franc',
        'KPW' => 'North Korean won',
        'KRW' => 'South Korean won',
        'KWD' => 'Kuwaiti dinar',
        'KYD' => 'Cayman Islands dollar',
        'KZT' => 'Kazakhstani tenge',
        'LAK' => 'Lao kip',
        'LBP' => 'Lebanese pound',
        'LKR' => 'Sri Lankan rupee',
        'LRD' => 'Liberian dollar',
        'LSL' => 'Lesotho loti',
        'LYD' => 'Libyan dinar',
        'MAD' => 'Moroccan dirham',
        'MDL' => 'Moldovan leu',
        'MGA' => 'Malagasy ariary',
        'MKD' => 'Macedonian denar',
        'MMK' => 'Burmese kyat',
        'MNT' => 'Mongolian t&ouml;gr&ouml;g',
        'MOP' => 'Macanese pataca',
        'MRU' => 'Mauritanian ouguiya',
        'MUR' => 'Mauritian rupee',
        'MVR' => 'Maldivian rufiyaa',
        'MWK' => 'Malawian kwacha',
        'MXN' => 'Mexican peso',
        'MYR' => 'Malaysian ringgit',
        'MZN' => 'Mozambican metical',
        'NAD' => 'Namibian dollar',
        'NGN' => 'Nigerian naira',
        'NIO' => 'Nicaraguan c&oacute;rdoba',
        'NOK' => 'Norwegian krone',
        'NPR' => 'Nepalese rupee',
        'NZD' => 'New Zealand dollar',
        'OMR' => 'Omani rial',
        'PAB' => 'Panamanian balboa',
        'PEN' => 'Sol',
        'PGK' => 'Papua New Guinean kina',
        'PHP' => 'Philippine peso',
        'PKR' => 'Pakistani rupee',
        'PLN' => 'Polish z&#x142;oty',
        'PRB' => 'Transnistrian ruble',
        'PYG' => 'Paraguayan guaran&iacute;',
        'QAR' => 'Qatari riyal',
        'RON' => 'Romanian leu',
        'RSD' => 'Serbian dinar',
        'RUB' => 'Russian ruble',
        'RWF' => 'Rwandan franc',
        'SAR' => 'Saudi riyal',
        'SBD' => 'Solomon Islands dollar',
        'SCR' => 'Seychellois rupee',
        'SDG' => 'Sudanese pound',
        'SEK' => 'Swedish krona',
        'SGD' => 'Singapore dollar',
        'SHP' => 'Saint Helena pound',
        'SLL' => 'Sierra Leonean leone',
        'SOS' => 'Somali shilling',
        'SRD' => 'Surinamese dollar',
        'SSP' => 'South Sudanese pound',
        'STN' => 'S&atilde;o Tom&eacute; and Pr&iacute;ncipe dobra',
        'SYP' => 'Syrian pound',
        'SZL' => 'Swazi lilangeni',
        'THB' => 'Thai baht',
        'TJS' => 'Tajikistani somoni',
        'TMT' => 'Turkmenistan manat',
        'TND' => 'Tunisian dinar',
        'TOP' => 'Tongan pa&#x2bb;anga',
        'TRY' => 'Turkish lira',
        'TTD' => 'Trinidad and Tobago dollar',
        'TWD' => 'New Taiwan dollar',
        'TZS' => 'Tanzanian shilling',
        'UAH' => 'Ukrainian hryvnia',
        'UGX' => 'Ugandan shilling',
        'USD' => 'United States (US) dollar',
        'UYU' => 'Uruguayan peso',
        'UZS' => 'Uzbekistani som',
        'VEF' => 'Venezuelan bol&iacute;var',
        'VES' => 'Bol&iacute;var soberano',
        'VND' => 'Vietnamese &#x111;&#x1ed3;ng',
        'VUV' => 'Vanuatu vatu',
        'WST' => 'Samoan t&#x101;l&#x101;',
        'XAF' => 'Central African CFA franc',
        'XCD' => 'East Caribbean dollar',
        'XOF' => 'West African CFA franc',
        'XPF' => 'CFP franc',
        'YER' => 'Yemeni rial',
        'ZAR' => 'South African rand',
        'ZMW' => 'Zambian kwacha',
    );
}

function kcCountryCurrencySymbolsList()
{
    return array(
        'AED' => '&#x62f;.&#x625;',
        'AFN' => '&#x60b;',
        'ALL' => 'L',
        'AMD' => 'AMD',
        'ANG' => '&fnof;',
        'AOA' => 'Kz',
        'ARS' => '&#36;',
        'AUD' => '&#36;',
        'AWG' => 'Afl.',
        'AZN' => 'AZN',
        'BAM' => 'KM',
        'BBD' => '&#36;',
        'BDT' => '&#2547;&nbsp;',
        'BGN' => '&#1083;&#1074;.',
        'BHD' => '.&#x62f;.&#x628;',
        'BIF' => 'Fr',
        'BMD' => '&#36;',
        'BND' => '&#36;',
        'BOB' => 'Bs.',
        'BRL' => '&#82;&#36;',
        'BSD' => '&#36;',
        'BTC' => '&#3647;',
        'BTN' => 'Nu.',
        'BWP' => 'P',
        'BYR' => 'Br',
        'BYN' => 'Br',
        'BZD' => '&#36;',
        'CAD' => '&#36;',
        'CDF' => 'Fr',
        'CHF' => '&#67;&#72;&#70;',
        'CLP' => '&#36;',
        'CNY' => '&yen;',
        'COP' => '&#36;',
        'CRC' => '&#x20a1;',
        'CUC' => '&#36;',
        'CUP' => '&#36;',
        'CVE' => '&#36;',
        'CZK' => '&#75;&#269;',
        'DJF' => 'Fr',
        'DKK' => 'DKK',
        'DOP' => 'RD&#36;',
        'DZD' => '&#x62f;.&#x62c;',
        'EGP' => 'EGP',
        'ERN' => 'Nfk',
        'ETB' => 'Br',
        'EUR' => '&euro;',
        'FJD' => '&#36;',
        'FKP' => '&pound;',
        'GBP' => '&pound;',
        'GEL' => '&#x20be;',
        'GGP' => '&pound;',
        'GHS' => '&#x20b5;',
        'GIP' => '&pound;',
        'GMD' => 'D',
        'GNF' => 'Fr',
        'GTQ' => 'Q',
        'GYD' => '&#36;',
        'HKD' => '&#36;',
        'HNL' => 'L',
        'HRK' => 'kn',
        'HTG' => 'G',
        'HUF' => '&#70;&#116;',
        'IDR' => 'Rp',
        'ILS' => '&#8362;',
        'IMP' => '&pound;',
        'INR' => '&#8377;',
        'IQD' => '&#x639;.&#x62f;',
        'IRR' => '&#xfdfc;',
        'IRT' => '&#x062A;&#x0648;&#x0645;&#x0627;&#x0646;',
        'ISK' => 'kr.',
        'JEP' => '&pound;',
        'JMD' => '&#36;',
        'JOD' => '&#x62f;.&#x627;',
        'JPY' => '&yen;',
        'KES' => 'KSh',
        'KGS' => '&#x441;&#x43e;&#x43c;',
        'KHR' => '&#x17db;',
        'KMF' => 'Fr',
        'KPW' => '&#x20a9;',
        'KRW' => '&#8361;',
        'KWD' => '&#x62f;.&#x643;',
        'KYD' => '&#36;',
        'KZT' => '&#8376;',
        'LAK' => '&#8365;',
        'LBP' => '&#x644;.&#x644;',
        'LKR' => '&#xdbb;&#xdd4;',
        'LRD' => '&#36;',
        'LSL' => 'L',
        'LYD' => '&#x644;.&#x62f;',
        'MAD' => '&#x62f;.&#x645;.',
        'MDL' => 'MDL',
        'MGA' => 'Ar',
        'MKD' => '&#x434;&#x435;&#x43d;',
        'MMK' => 'Ks',
        'MNT' => '&#x20ae;',
        'MOP' => 'P',
        'MRU' => 'UM',
        'MUR' => '&#x20a8;',
        'MVR' => '.&#x783;',
        'MWK' => 'MK',
        'MXN' => '&#36;',
        'MYR' => '&#82;&#77;',
        'MZN' => 'MT',
        'NAD' => 'N&#36;',
        'NGN' => '&#8358;',
        'NIO' => 'C&#36;',
        'NOK' => '&#107;&#114;',
        'NPR' => '&#8360;',
        'NZD' => '&#36;',
        'OMR' => '&#x631;.&#x639;.',
        'PAB' => 'B/.',
        'PEN' => 'S/',
        'PGK' => 'K',
        'PHP' => '&#8369;',
        'PKR' => '&#8360;',
        'PLN' => '&#122;&#322;',
        'PRB' => '&#x440;.',
        'PYG' => '&#8370;',
        'QAR' => '&#x631;.&#x642;',
        'RMB' => '&yen;',
        'RON' => 'lei',
        'RSD' => '&#1088;&#1089;&#1076;',
        'RUB' => '&#8381;',
        'RWF' => 'Fr',
        'SAR' => '&#x631;.&#x633;',
        'SBD' => '&#36;',
        'SCR' => '&#x20a8;',
        'SDG' => '&#x62c;.&#x633;.',
        'SEK' => '&#107;&#114;',
        'SGD' => '&#36;',
        'SHP' => '&pound;',
        'SLL' => 'Le',
        'SOS' => 'Sh',
        'SRD' => '&#36;',
        'SSP' => '&pound;',
        'STN' => 'Db',
        'SYP' => '&#x644;.&#x633;',
        'SZL' => 'L',
        'THB' => '&#3647;',
        'TJS' => '&#x405;&#x41c;',
        'TMT' => 'm',
        'TND' => '&#x62f;.&#x62a;',
        'TOP' => 'T&#36;',
        'TRY' => '&#8378;',
        'TTD' => '&#36;',
        'TWD' => '&#78;&#84;&#36;',
        'TZS' => 'Sh',
        'UAH' => '&#8372;',
        'UGX' => 'UGX',
        'USD' => '&#36;',
        'UYU' => '&#36;',
        'UZS' => 'UZS',
        'VEF' => 'Bs F',
        'VES' => 'Bs.S',
        'VND' => '&#8363;',
        'VUV' => 'Vt',
        'WST' => 'T',
        'XAF' => 'CFA',
        'XCD' => '&#36;',
        'XOF' => 'CFA',
        'XPF' => 'Fr',
        'YER' => '&#xfdfc;',
        'ZAR' => '&#82;',
        'ZMW' => 'ZK',
    );
}

function kcGetAllWeeks($year)
{

    $date = new DateTime;
    $date->setISODate($year, 53);

    $weeks = ($date->format("W") === "53" ? 53 : 52);
    $data = [];

    for ($x = 1; $x <= $weeks; $x++) {
        $dto = new DateTime();
        $dates['week_start'] = $dto->setISODate($year, $x)->format('Y-m-d');
        $dates['week_end'] = $dto->modify('+6 days')->format('Y-m-d');
        if ($x < 10) {
            $x = '0' . $x;
        }
        $data[date('m', strtotime($dates['week_start']))][$x] = $dates;
    }
    return $data;
}

function kcGetAllMonth()
{
    $month = [];
    $monthsArray = kcMonthsTranslate();
    for ($i = 1; $i < 13; $i++) {
        $date = strtotime('2021-' . $i . '-01');
        $formatDate = date('F', $date);
        $month[date('m', $date)] = !empty($monthsArray[$formatDate]) ? $monthsArray[$formatDate] : $formatDate;
    }
    return $month;
}

function kcMonthsTranslate()
{
    return array(
        'January' => esc_html__('January', 'kc-lang'),
        'February' => esc_html__('February', 'kc-lang'),
        'March' => esc_html__('March', 'kc-lang'),
        'April' => esc_html__('April', 'kc-lang'),
        'May' => esc_html__('May', 'kc-lang'),
        'June' => esc_html__('June', 'kc-lang'),
        'July' => esc_html__('July', 'kc-lang'),
        'August' => esc_html__('August', 'kc-lang'),
        'September' => esc_html__('September', 'kc-lang'),
        'October' => esc_html__('October', 'kc-lang'),
        'November' => esc_html__('November', 'kc-lang'),
        'December' => esc_html__('December', 'kc-lang')
    );
}

function kcMonthsWeeksArray($month)
{
    $year = date('Y');
    $totalDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $list = $weeks = [];
    for ($d = 1; $d <= $totalDays; $d++) {
        $time = mktime(12, 0, 0, $month, $d, $year);
        if (date('m', $time) == $month) {
            $list[] = date('Y-m-d', $time);
        }
    }
    if (!empty($list) && count($list) > 0) {
        $weeks = array_chunk($list, 7);
    }
    return $weeks;
}

function kvSaveCustomFields($module_type, $module_id, $data)
{
    $module_id = !empty($module_id) ? (int) $module_id : '';
    $customFieldData = new KCCustomFieldData();
    $data = kcRemoveBlankKeyFromArray($data);
    foreach ($data as $key => $value) {
        $field_id = str_replace("custom_field_", "", $key);

        $fieldObj = $customFieldData->get_by(['module_type' => $module_type, 'module_id' => $module_id, 'field_id' => $field_id], '=', true);
        if (gettype($value) === 'array') {
            $value = json_encode($value);
        }
        $temp = [
            'module_type' => $module_type,
            'module_id' => $module_id,
            'fields_data' => $value,
            'field_id' => (int) $field_id
        ];
        if (empty($fieldObj)) {
            $customFieldData->insert($temp);
        } else {
            $customFieldData->update($temp, ['id' => (int) $fieldObj->id]);
        }
    }
}

function kcGetCustomFields($module_type, $module_id, $data_module_id = 0, $exclude_null_field_data = false)
{
    global $wpdb;
    $module_id = (int) $module_id;
    $user_id = get_current_user_id();
    $userObj = new WP_User($user_id);
    $data = [];
    $id = '';
    $custom_field_table = $wpdb->prefix . 'kc_custom_fields';
    $custom_field_data_table = $wpdb->prefix . 'kc_custom_fields_data';
    $type = "'$module_type'";

    switch ($module_type) {
        case 'doctor_module':
            $query = "SELECT p.*, u.fields_data " .
                "FROM {$custom_field_table} AS p " .
                "LEFT JOIN (SELECT * FROM {$custom_field_data_table} WHERE module_id=" . $module_id . " AND module_type=" . $type . " ) AS u ON p.id = u.field_id WHERE p.status !=0 AND p.module_type =" . $type;
            break;
        case 'patient_module':
            if (current_user_can('administrator')) {
                $id = "AND p.module_id =0";
            }
            if ($userObj->roles[0] == 'kiviCare_doctor') {
                $id = "AND p.module_id IN($user_id,0)";
            }
            $query = "SELECT p.*, u.fields_data " .
                "FROM {$custom_field_table} AS p " .
                "LEFT JOIN (SELECT * FROM {$custom_field_data_table} WHERE module_id=" . $module_id . " AND module_type=" . $type . " ) AS u ON p.id = u.field_id WHERE p.status !=0 AND p.module_type =" . $type . $id;
            break;
        case 'appointment_module':
            //            if (current_user_can('administrator')) {
            //                $id = "AND p.module_id =0";
            //            }
            //            if (!empty($userObj->roles) && $userObj->roles[0] == 'kiviCare_doctor') {
            //                $id = "AND p.module_id IN($user_id,0)";
            //            }
            $nullCondition = '';
            if ($exclude_null_field_data) {
                $nullCondition = ' AND u.fields_data != "" ';
            }
            $query = "SELECT p.*, u.fields_data " .
                "FROM {$custom_field_table} AS p " .
                "LEFT JOIN (SELECT * FROM {$custom_field_data_table} WHERE module_id=" . $module_id . " AND module_type=" . $type . " ) AS u ON p.id = u.field_id WHERE p.module_type =" . $type .
                " AND p.module_id IN($data_module_id,0) {$nullCondition}";
            break;
        case 'patient_encounter_module':
            if ($userObj->roles[0] == 'kiviCare_doctor') {
                $id = " AND p.module_id IN($user_id,0)";
            }
            $query = "SELECT p.*, u.fields_data " .
                "FROM {$custom_field_table} AS p " .
                "LEFT JOIN (SELECT * FROM {$custom_field_data_table} WHERE module_id=" . $module_id . " AND module_type=" . $type . " ) AS u ON p.id = u.field_id WHERE p.module_type =" . $type . $id;
            break;
    }

    $customData = !empty($query) ? $wpdb->get_results($query) : [];

    if (empty($customData)) {
        return $data;
    }
    $fields = [];
    if ($customData !== []) {
        foreach ($customData as $value) {
            $fields[] = array_merge(json_decode($value->fields, true), ['field_data' => $value->fields_data], ['id' => $value->id]);
        }
        $data = $fields;
    }
    if ($data === [] || count($customData) === 0) {
        $customField = (new KCCustomField())->get_by(['module_type' => $module_type], '=', true);
        if ($customField !== []) {
            $fields = $customField;
            foreach ($fields as $key => $field) {
                $field_detail = json_decode($field->fields);
                if (in_array($field_detail->type, ["checkbox", "multiselect"])) {
                    $data[][$field_detail->name] = [];
                } else {
                    $data[][$field_detail->name] = "";
                }
            }
        }
    }

    if (is_array($data) && count($data) > 0) {
        $data = array_map(function ($v) {
            if (!empty($v['type']) && in_array($v['type'], ['checkbox', 'file_upload', 'multiselect']) && !empty($v['field_data'])) {
                $v['field_data'] = json_decode($v['field_data']);
                if($v['type'] === 'file_upload'){
                    $v['field_data']->type = $v['type'];
                } elseif($v['type'] === 'multiselect'){
                    $v['field_data'] = array_map(function ($item) {
                        return $item->text; 
                    }, (array) $v['field_data']);
                }
            }
            return $v;
        }, $data);
    }

    return $data;
}

function kcGetCustomFieldsList($module_type, $doctor_id)
{

    global $wpdb;
    $condition = '';
    if ($module_type == 'appointment_module') {
        $condition = ' AND module_id IN ( 0 ,' . (int) $doctor_id . ')';
    }
    $query = "SELECT * FROM {$wpdb->prefix}kc_custom_fields WHERE status = 1 AND module_type = '" . esc_sql($module_type) . "' {$condition}";
    $custom_module = $wpdb->get_results($query);

    $fields = [];
    if (count($custom_module) > 0) {
        foreach ($custom_module as $key => $value) {
            $field_data = '';
            if (!empty($value->fields_data)) {
                $temp = json_decode($value->fields);
                if (
                    $temp->type != null
                    && in_array($temp->type, ['file_upload', 'checkbox', 'multiselect'])
                ) {
                    $value->fields_data = $temp;
                }
                $field_data = $value->fields_data;
            }
            $fields[] = array_merge(json_decode($value->fields, true), ['field_data' => $field_data], ['id' => $value->id]);
        }
    }

    $divopen = true;
    $max = wp_max_upload_size();
    if (!empty($fields)) {
        foreach ($fields as $keynew => $customField) {
            ?>
            <?php if ($keynew % 2 === 0) {
                $divClose = true;
                ?>
                <div class="kivi-row">
                    <?php
            } ?>
                <div class="form-group kivi-col-6 mt-2 <?php echo esc_html($customField['type']); ?>">
                    <label class="form-label" for="<?php echo esc_html($customField['name'] . '_' . $customField['id']); ?>"
                        class="form-control-label mb-2">

                        <?php echo esc_html($customField['label']); ?>

                        <?php

                        if ($customField['isRequired'] === 1 || $customField['isRequired'] === '1') {
                            ?>
                            <span>*</span>
                            <?php
                        }
                        ?>
                    </label>
                    <?php

                    if ($customField['type'] === 'text' || $customField['type'] === 'number' || $customField['type'] === 'calendar') {
                        ?>
                        <input id="<?php echo esc_html($customField['name'] . '_' . $customField['id']); ?>"
                            placeholder="<?php echo esc_html($customField['placeholder']); ?>"
                            name="custom_field_<?php echo esc_html($customField['id']); ?>"
                            type="<?php echo esc_html($customField['type'] === 'calendar' ? 'date' : $customField['type']); ?>"
                            class="iq-kivicare-form-control <?php echo esc_html(in_array($customField['isRequired'], [1, '1']) ? 'kivicare-required' : ''); ?>" />
                        <?php
                    }
                    if ($customField['type'] === 'file_upload') {
                        $supported_file_type = collect($customField['file_upload_type'])->pluck('id')->implode(', ');
                        ?>
                        <input id="<?php echo esc_html($customField['name'] . '_' . $customField['id']); ?>"
                            placeholder="<?php echo esc_html($customField['placeholder']); ?>"
                            name="custom_field_<?php echo esc_html($customField['id']); ?>" type="file"
                            class="iq-kivicare-form-control
                             <?php echo esc_html(in_array($customField['isRequired'], [1, '1']) ? 'kivicare-required' : ''); ?>" accept="<?php echo esc_html($supported_file_type); ?>"
                            onchange="kivicareFileUploadSizeCheck(event,this)" />
                        <div style="display:none;margin-top:4px">
                            <span style="color:var(--iq-secondary-dark)">
                                <?php echo esc_html__("Invalid file, Please select file size upto ", "kc-lang") . esc_html($max > 0 ? $max / (1024 * 1024) : 0) . esc_html__('MB', 'kc-lang') ?></span>
                        </div>
                        <?php
                    }
                    if ($customField['type'] === 'multiselect' && !empty($customField['options'])) {
                        ?>
                        <select
                            class="appointment_widget_multiselect iq-kivicare-form-control 
                        <?php echo esc_html(in_array($customField['isRequired'], [1, '1']) ? ' kivicare-required' : ''); ?>"
                            multiple id="<?php echo esc_html($customField['name'] . '_' . $customField['id']); ?>"
                            placeholder="<?php echo esc_html($customField['placeholder']); ?>"
                            name="custom_field_<?php echo esc_html($customField['id']); ?>">
                            <?php
                            foreach ($customField['options'] as $key => $option) {
                                ?>
                                <option value="<?php echo esc_html($option['text']); ?>" key="<?php echo esc_html($key); ?>">
                                    <?php echo esc_html($option['text']); ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }
                    if ($customField['type'] === 'select') {
                        ?>
                        <select id="<?php echo esc_html($customField['name'] . '_' . $customField['id']); ?>"
                            class="iq-kivicare-form-control text-capitalize <?php echo esc_html(in_array($customField['isRequired'], [1, '1']) ? 'kivicare-required' : ''); ?>"
                            name="custom_field_<?php echo esc_html($customField['id']); ?>">
                            <option value=""> <?php echo esc_html__('Select Option', 'kc-lang'); ?> </option>
                            <?php
                            foreach ($customField['options'] as $key => $option) {
                                ?>
                                <option value="<?php echo esc_html($option['text']); ?>" key="<?php echo esc_html($key); ?>">
                                    <?php echo esc_html($option['text']); ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                        <?php
                    }

                    if ($customField['type'] === 'radio') {
                        if (!empty($customField['placeholder'])) {
                            ?>
                            <p><?php echo esc_html($customField['placeholder']); ?> </p>
                            <?php
                        }
                        ?>
                        <div class="d-flex flex-wrap">
                            <?php

                            foreach ($customField['options'] as $key => $option) {
                                ?>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="<?php echo esc_html($customField['name'] . '_' . $key); ?>"
                                        name="custom_field_<?php echo esc_html($customField['id']); ?>"
                                        value="<?php echo esc_html($option['text']); ?>"
                                        class="custom-control-input <?php echo esc_html(in_array($customField['isRequired'], [1, '1']) ? 'kivicare-required' : ''); ?>">
                                    <label class="custom-control-label" for="<?php echo esc_html($customField['name'] . '_' . $key); ?>">
                                        <?php echo esc_html($option['text']); ?>
                                    </label>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        <?php
                    }

                    if ($customField['type'] === 'checkbox') {
                        ?>
                        <div class="d-flex flex-wrap justify-content-space " style="gap:5px;">
                            <?php

                            foreach ($customField['options'] as $key => $option) {
                                ?>
                                <div class="custom-control custom-checkbox custom-control-inline d-flex align-items-center"
                                    style="gap:5px;">
                                    <input type="checkbox" id="<?php echo esc_html($customField['name'] . '_' . $key); ?>"
                                        name="custom_field_<?php echo esc_html($customField['id']); ?>"
                                        value="<?php echo esc_html($option['id']); ?>"
                                        class="custom-control-input <?php echo esc_html(in_array($customField['isRequired'], [1, '1']) ? 'kivicare-required' : ''); ?>">
                                    <label class="custom-control-label" for="<?php echo esc_html($customField['name'] . '_' . $key); ?>">
                                        <?php echo esc_html($option['text']); ?>
                                    </label>
                                </div>
                                <?php
                            }

                            ?>
                        </div>
                        <?php
                    }

                    if ($customField['type'] === 'textarea') {
                        ?>
                        <textarea id="<?php echo esc_html($customField['name'] . '_' . $customField['id']); ?>"
                            name="custom_field_<?php echo esc_html($customField['id']); ?>"
                            placeholder="<?php echo esc_html($customField['placeholder']); ?>"
                            class="iq-kivicare-form-control <?php echo esc_html(in_array($customField['isRequired'], [1, '1']) ? 'kivicare-required' : ''); ?>"></textarea>
                        <?php
                    }

                    ?>
                </div>
                <?php if ($keynew % 2 !== 0) {
                    $divClose = false;
                    ?>
                </div>
                <?php
                }
        }
        if ($divClose) {
            ?>
            </div>
            <?php
        }
    }
}

function kcCheckSetupStatus()
{
    // return false is setup is not complete
    $prefix = KIVI_CARE_PREFIX;
    $modules = get_option($prefix . 'modules');
    $total_steps = get_option('total_setup_steps');
    for ($i = 1; $i <= $total_steps; $i++) {
        $current_step_json = get_option('setup_step_' . $i);
        $current_step_array = json_decode($current_step_json);
        if ($modules['module_config']['name'] === 'receptionist' && $modules['module_config']['status'] === '1') {
            continue;
        }

        if ($current_step_array->status === false && $current_step_array->status === null || !$current_step_array->status === '') {
            return false;
        }
    }

    return true;
}

function kcGetAdminPermissions()
{

    $prefix = KIVI_CARE_PREFIX;

    return apply_filters('kivicare_admin_permission_list', collect([

        'read' => ['name' => 'read', 'status' => 1],
        'dashboard' => ['name' => $prefix . 'dashboard', 'status' => 1],
        'setting' => ['name' => $prefix . 'settings', 'status' => 1],
        'doctor_dashboard' => ['name' => $prefix . 'doctor_dashboard', 'status' => 1],
        'patient_dashboard' => ['name' => $prefix . 'doctor_dashboard', 'status' => 1],

        'doctor_list' => ['name' => $prefix . 'doctor_list', 'status' => 1],
        'doctor_add' => ['name' => $prefix . 'doctor_add', 'status' => 1],
        'doctor_edit' => ['name' => $prefix . 'doctor_edit', 'status' => 1],
        'doctor_view' => ['name' => $prefix . 'doctor_view', 'status' => 1],
        'doctor_delete' => ['name' => $prefix . 'doctor_delete', 'status' => 1],
        'doctor_resend_credential' => ['name' => $prefix . 'doctor_resend_credential', 'status' => 1],

        'receptionist_list' => ['name' => $prefix . 'receptionist_list', 'status' => 1],
        'receptionist_add' => ['name' => $prefix . 'receptionist_add', 'status' => 1],
        'receptionist_edit' => ['name' => $prefix . 'receptionist_edit', 'status' => 1],
        'receptionist_view' => ['name' => $prefix . 'receptionist_view', 'status' => 1],
        'receptionist_delete' => ['name' => $prefix . 'receptionist_delete', 'status' => 1],
        'receptionist_resend_credential' => ['name' => $prefix . 'receptionist_resend_credential', 'status' => 1],

        'patient_list' => ['name' => $prefix . 'patient_list', 'status' => 1],
        'patient_add' => ['name' => $prefix . 'patient_add', 'status' => 1],
        'patient_edit' => ['name' => $prefix . 'patient_edit', 'status' => 1],
        'patient_view' => ['name' => $prefix . 'patient_view', 'status' => 1],
        'patient_delete' => ['name' => $prefix . 'patient_delete', 'status' => 1],
        'patient_profile' => ['name' => $prefix . 'patient_profile', 'status' => 1],
        'patient_resend_credential' => ['name' => $prefix . 'patient_resend_credential', 'status' => 1],

        'clinic_list' => ['name' => $prefix . 'clinic_list', 'status' => 1],
        'clinic_add' => ['name' => $prefix . 'clinic_add', 'status' => 1],
        'clinic_edit' => ['name' => $prefix . 'clinic_edit', 'status' => 1],
        'clinic_view' => ['name' => $prefix . 'clinic_view', 'status' => 1],
        'clinic_delete' => ['name' => $prefix . 'clinic_delete', 'status' => 1],
        'clinic_profile' => ['name' => $prefix . 'clinic_profile', 'status' => 1],
        'clinic_resend_credential' => ['name' => $prefix . 'clinic_resend_credential', 'status' => 1],

        'appointment_list' => ['name' => $prefix . 'appointment_list', 'status' => 1],
        'appointment_add' => ['name' => $prefix . 'appointment_add', 'status' => 1],
        'appointment_edit' => ['name' => $prefix . 'appointment_edit', 'status' => 1],
        'appointment_view' => ['name' => $prefix . 'appointment_view', 'status' => 1],
        'appointment_delete' => ['name' => $prefix . 'appointment_delete', 'status' => 1],

        'service_list' => ['name' => $prefix . 'service_list', 'status' => 1],
        'service_add' => ['name' => $prefix . 'service_add', 'status' => 1],
        'service_edit' => ['name' => $prefix . 'service_edit', 'status' => 1],
        'service_view' => ['name' => $prefix . 'service_view', 'status' => 1],
        'service_delete' => ['name' => $prefix . 'service_delete', 'status' => 1],

        'patient_clinic' => ['name' => $prefix . 'patient_clinic', 'status' => 0],

        'tax_list' => ['name' => $prefix . 'tax_list', 'status' => 1],
        'tax_add' => ['name' => $prefix . 'tax_add', 'status' => 1],
        'tax_edit' => ['name' => $prefix . 'tax_edit', 'status' => 1],
        'tax_delete' => ['name' => $prefix . 'tax_delete', 'status' => 1],

        'patient_report' => ['name' => $prefix . 'patient_report', 'status' => 1],
        'patient_report_add' => ['name' => $prefix . 'patient_report_add', 'status' => 1],
        'patient_report_view' => ['name' => $prefix . 'patient_report_view', 'status' => 1],
        'patient_report_delete' => ['name' => $prefix . 'patient_report_delete', 'status' => 1],

        'doctor_session_list' => ['name' => $prefix . 'doctor_session_list', 'status' => 1],
        'doctor_session_add' => ['name' => $prefix . 'doctor_session_add', 'status' => 1],
        'doctor_session_edit' => ['name' => $prefix . 'doctor_session_edit', 'status' => 1],
        'doctor_session_delete' => ['name' => $prefix . 'doctor_session_delete', 'status' => 1],

        'custom_form_list' => ['name' => $prefix . 'custom_form_list', 'status' => 1],
        'custom_form_add' => ['name' => $prefix . 'custom_form_add', 'status' => 1],
        'custom_form_edit' => ['name' => $prefix . 'custom_form_edit', 'status' => 1],
        'custom_form_delete' => ['name' => $prefix . 'custom_form_delete', 'status' => 1],

        'static_data_list' => ['name' => $prefix . 'static_data_list', 'status' => 1],
        'static_data_add' => ['name' => $prefix . 'static_data_add', 'status' => 1],
        'static_data_edit' => ['name' => $prefix . 'static_data_edit', 'status' => 1],
        'static_data_view' => ['name' => $prefix . 'static_data_view', 'status' => 1],
        'static_data_delete' => ['name' => $prefix . 'static_data_delete', 'status' => 1],

        'patient_encounters' => ['name' => $prefix . 'patient_encounters', 'status' => 1],
        'patient_encounter_list' => ['name' => $prefix . 'patient_encounter_list', 'status' => 1],
        'patient_encounter_add' => ['name' => $prefix . 'patient_encounter_add', 'status' => 1],
        'patient_encounter_edit' => ['name' => $prefix . 'patient_encounter_edit', 'status' => 1],
        'patient_encounter_view' => ['name' => $prefix . 'patient_encounter_view', 'status' => 1],
        'patient_encounter_delete' => ['name' => $prefix . 'patient_encounter_delete', 'status' => 1],

        /* Creating a list of permissions for the patient encounters module. */
        'encounters_template_list' => ['name' => $prefix . 'encounters_template_list', 'status' => 1],
        'encounters_template_add' => ['name' => $prefix . 'encounters_template_add', 'status' => 1],
        'encounters_template_edit' => ['name' => $prefix . 'encounters_template_edit', 'status' => 1],
        'encounters_template_view' => ['name' => $prefix . 'encounters_template_view', 'status' => 1],
        'encounters_template_delete' => ['name' => $prefix . 'encounters_template_delete', 'status' => 1],

        'patient_appointment_status_change' => ['name' => $prefix . 'patient_appointment_status_change', 'status' => 1],

        'medical_records_list' => ['name' => $prefix . 'medical_records_list', 'status' => 1],
        'medical_records_add' => ['name' => $prefix . 'medical_records_add', 'status' => 1],
        'medical_records_edit' => ['name' => $prefix . 'medical_records_edit', 'status' => 1],
        'medical_records_view' => ['name' => $prefix . 'medical_records_view', 'status' => 1],
        'medical_records_delete' => ['name' => $prefix . 'medical_records_delete', 'status' => 1],

        'prescription_list' => ['name' => $prefix . 'prescription_list', 'status' => 1],
        'prescription_add' => ['name' => $prefix . 'prescription_add', 'status' => 1],
        'prescription_edit' => ['name' => $prefix . 'prescription_edit', 'status' => 1],
        'prescription_view' => ['name' => $prefix . 'prescription_view', 'status' => 1],
        'prescription_delete' => ['name' => $prefix . 'prescription_delete', 'status' => 1],

        'patient_bill_list' => ['name' => $prefix . 'patient_bill_list', 'status' => 1],
        'patient_bill_add' => ['name' => $prefix . 'patient_bill_add', 'status' => 1],
        'patient_bill_edit' => ['name' => $prefix . 'patient_bill_edit', 'status' => 1],
        'patient_bill_view' => ['name' => $prefix . 'patient_bill_view', 'status' => 1],
        'patient_bill_delete' => ['name' => $prefix . 'patient_bill_delete', 'status' => 1],

        'patient_review_delete' => ['name' => $prefix . 'patient_review_delete', 'status' => 1],
        'patient_review_get' => ['name' => $prefix . 'patient_review_get', 'status' => 1],

        'custom_field_list' => ['name' => $prefix . 'custom_field_list', 'status' => 1],
        'custom_field_add' => ['name' => $prefix . 'custom_field_add', 'status' => 1],
        'custom_field_edit' => ['name' => $prefix . 'custom_field_edit', 'status' => 1],
        'custom_field_view' => ['name' => $prefix . 'custom_field_view', 'status' => 1],
        'custom_field_delete' => ['name' => $prefix . 'custom_field_delete', 'status' => 1],

        'terms_condition' => ['name' => $prefix . 'terms_condition', 'status' => 1],
        'clinic_schedule' => ['name' => $prefix . 'clinic_schedule', 'status' => 1],
        'common_settings' => ['name' => $prefix . 'common_settings', 'status' => 1],
        'notification_setting' => ['name' => $prefix . 'notification_setting', 'status' => 1],
        'change_password' => ['name' => $prefix . 'change_password', 'status' => 1],

        'dashboard_total_patient' => ['name' => $prefix . 'dashboard_total_patient', 'status' => 1],
        'dashboard_total_doctor' => ['name' => $prefix . 'dashboard_total_doctor', 'status' => 1],
        'dashboard_total_appointment' => ['name' => $prefix . 'dashboard_total_appointment', 'status' => 1],
        'dashboard_total_revenue' => ['name' => $prefix . 'dashboard_total_revenue', 'status' => 1],
    ]));
}

function kcGetDoctorPermission()
{

    $prefix = KIVI_CARE_PREFIX;

    return apply_filters('kivicare_doctor_permission_list', collect([

        'read' => ['name' => 'read', 'status' => 1],

        'dashboard' => ['name' => $prefix . 'dashboard', 'status' => 1],

        'settings' => ['name' => $prefix . 'settings', 'status' => 1],

        'doctor_dashboard' => ['name' => $prefix . 'doctor_dashboard', 'status' => 1],
        'doctor_profile' => ['name' => $prefix . 'doctor_profile', 'status' => 1],
        'change_password' => ['name' => $prefix . 'change_password', 'status' => 1],

        'appointment_list' => ['name' => $prefix . 'appointment_list', 'status' => 1],
        'appointment_add' => ['name' => $prefix . 'appointment_add', 'status' => 1],
        'appointment_edit' => ['name' => $prefix . 'appointment_edit', 'status' => 1],
        'appointment_view' => ['name' => $prefix . 'appointment_view', 'status' => 1],
        'appointment_delete' => ['name' => $prefix . 'appointment_delete', 'status' => 1],

        'patient_clinic' => ['name' => $prefix . 'patient_clinic', 'status' => 0],

        'patient_report' => ['name' => $prefix . 'patient_report', 'status' => 1],
        'patient_report_add' => ['name' => $prefix . 'patient_report_add', 'status' => 1],
        'patient_report_view' => ['name' => $prefix . 'patient_report_view', 'status' => 1],
        'patient_report_delete' => ['name' => $prefix . 'patient_report_delete', 'status' => 1],

        'doctor_session_list' => ['name' => $prefix . 'doctor_session_list', 'status' => 1],
        'doctor_session_add' => ['name' => $prefix . 'doctor_session_add', 'status' => 1],
        'doctor_session_edit' => ['name' => $prefix . 'doctor_session_edit', 'status' => 1],
        'doctor_session_delete' => ['name' => $prefix . 'doctor_session_delete', 'status' => 1],

        'static_data_list' => ['name' => $prefix . 'static_data_list', 'status' => 1],
        'static_data_add' => ['name' => $prefix . 'static_data_add', 'status' => 1],
        'static_data_edit' => ['name' => $prefix . 'static_data_edit', 'status' => 1],
        'static_data_view' => ['name' => $prefix . 'static_data_view', 'status' => 1],
        'static_data_delete' => ['name' => $prefix . 'static_data_delete', 'status' => 1],

        'clinic_schedule' => ['name' => $prefix . 'clinic_schedule', 'status' => 1],

        'service_list' => ['name' => $prefix . 'service_list', 'status' => 1],
        'service_add' => ['name' => $prefix . 'service_add', 'status' => 1],
        'service_edit' => ['name' => $prefix . 'service_edit', 'status' => 1],
        'service_view' => ['name' => $prefix . 'service_view', 'status' => 1],
        'service_delete' => ['name' => $prefix . 'service_delete', 'status' => 1],

        'custom_field_list' => ['name' => $prefix . 'custom_field_list', 'status' => 1],
        'custom_field_add' => ['name' => $prefix . 'custom_field_add', 'status' => 1],
        'custom_field_edit' => ['name' => $prefix . 'custom_field_edit', 'status' => 1],
        'custom_field_view' => ['name' => $prefix . 'custom_field_view', 'status' => 1],
        'custom_field_delete' => ['name' => $prefix . 'custom_field_delete', 'status' => 1],

        'patient_encounters' => ['name' => $prefix . 'patient_encounters', 'status' => 1],
        'patient_encounter_list' => ['name' => $prefix . 'patient_encounter_list', 'status' => 1],
        'patient_encounter_add' => ['name' => $prefix . 'patient_encounter_add', 'status' => 1],
        'patient_encounter_edit' => ['name' => $prefix . 'patient_encounter_edit', 'status' => 1],
        'patient_encounter_view' => ['name' => $prefix . 'patient_encounter_view', 'status' => 1],
        'patient_encounter_delete' => ['name' => $prefix . 'patient_encounter_delete', 'status' => 1],

        /* Creating a list of permissions for the patient encounters module. */
        'encounters_template_list' => ['name' => $prefix . 'encounters_template_list', 'status' => 1],
        'encounters_template_add' => ['name' => $prefix . 'encounters_template_add', 'status' => 1],
        'encounters_template_edit' => ['name' => $prefix . 'encounters_template_edit', 'status' => 1],
        'encounters_template_view' => ['name' => $prefix . 'encounters_template_view', 'status' => 1],
        'encounters_template_delete' => ['name' => $prefix . 'encounters_template_delete', 'status' => 1],

        'patient_appointment_status_change' => ['name' => $prefix . 'patient_appointment_status_change', 'status' => 1],

        'patient_list' => ['name' => $prefix . 'patient_list', 'status' => 1],
        'patient_add' => ['name' => $prefix . 'patient_add', 'status' => 1],
        'patient_edit' => ['name' => $prefix . 'patient_edit', 'status' => 1],
        'patient_view' => ['name' => $prefix . 'patient_view', 'status' => 1],
        'patient_delete' => ['name' => $prefix . 'patient_delete', 'status' => 1],
        'patient_profile' => ['name' => $prefix . 'patient_profile', 'status' => 1],
        'patient_resend_credential' => ['name' => $prefix . 'patient_resend_credential', 'status' => 1],

        'medical_records_list' => ['name' => $prefix . 'medical_records_list', 'status' => 1],
        'medical_records_add' => ['name' => $prefix . 'medical_records_add', 'status' => 1],
        'medical_records_edit' => ['name' => $prefix . 'medical_records_edit', 'status' => 1],
        'medical_records_view' => ['name' => $prefix . 'medical_records_view', 'status' => 1],
        'medical_records_delete' => ['name' => $prefix . 'medical_records_delete', 'status' => 1],

        'prescription_list' => ['name' => $prefix . 'prescription_list', 'status' => 1],
        'prescription_add' => ['name' => $prefix . 'prescription_add', 'status' => 1],
        'prescription_edit' => ['name' => $prefix . 'prescription_edit', 'status' => 1],
        'prescription_view' => ['name' => $prefix . 'prescription_view', 'status' => 1],
        'prescription_delete' => ['name' => $prefix . 'prescription_delete', 'status' => 1],

        'patient_bill_list' => ['name' => $prefix . 'patient_bill_list', 'status' => 1],
        'patient_bill_add' => ['name' => $prefix . 'patient_bill_add', 'status' => 1],
        'patient_bill_edit' => ['name' => $prefix . 'patient_bill_edit', 'status' => 1],
        'patient_bill_view' => ['name' => $prefix . 'patient_bill_view', 'status' => 1],
        'patient_bill_delete' => ['name' => $prefix . 'patient_bill_delete', 'status' => 1],

        'patient_review_get' => ['name' => $prefix . 'patient_review_get', 'status' => 1],

        'dashboard_total_patient' => ['name' => $prefix . 'dashboard_total_patient', 'status' => 1],
        'dashboard_total_appointment' => ['name' => $prefix . 'dashboard_total_appointment', 'status' => 1],
        'dashboard_total_today_appointment' => ['name' => $prefix . 'dashboard_total_today_appointment', 'status' => 1],
        'dashboard_total_service' => ['name' => $prefix . 'dashboard_total_service', 'status' => 1],
    ]));
}

function kcGetPatientPermissions()
{

    $prefix = KIVI_CARE_PREFIX;

    return apply_filters('kivicare_patient_permission_list', collect([

        'read' => ['name' => 'read', 'status' => 1],
        'dashboard' => ['name' => $prefix . 'dashboard', 'status' => 1],
        'patient_dashboard' => ['name' => $prefix . 'doctor_dashboard', 'status' => 1],
        'patient_profile' => ['name' => $prefix . 'patient_profile', 'status' => 1],
        'change_password' => ['name' => $prefix . 'change_password', 'status' => 1],

        'service_list' => ['name' => $prefix . 'service_list', 'status' => 1],

        'appointment_list' => ['name' => $prefix . 'appointment_list', 'status' => 1],
        'appointment_add' => ['name' => $prefix . 'appointment_add', 'status' => 1],
        'appointment_edit' => ['name' => $prefix . 'appointment_edit', 'status' => 1],
        'appointment_view' => ['name' => $prefix . 'appointment_view', 'status' => 1],
        'appointment_cancel' => ['name' => $prefix . 'appointment_cancel', 'status' => 1],

        'patient_clinic' => ['name' => $prefix . 'patient_clinic', 'status' => 1],

        'patient_report' => ['name' => $prefix . 'patient_report', 'status' => 1],
        'patient_report_add' => ['name' => $prefix . 'patient_report_add', 'status' => 0],
        'patient_report_view' => ['name' => $prefix . 'patient_report_view', 'status' => 1],
        // 'patient_bill_edit' => ['name' => $prefix . 'patient_bill_edit', 'status' => 1],
        'patient_report_delete' => ['name' => $prefix . 'patient_report_delete', 'status' => 0],

        // 'doctor_session_list' => ['name' => $prefix . 'doctor_session_list', 'status' => 0],
        // 'doctor_session_add' => ['name' => $prefix . 'doctor_session_add', 'status' => 0],
        // 'doctor_session_edit' => ['name' => $prefix . 'doctor_session_edit', 'status' => 0],
        // 'doctor_session_delete' => ['name' => $prefix . 'doctor_session_delete', 'status' => 0],

        'patient_encounters' => ['name' => $prefix . 'patient_encounters', 'status' => 1],
        'patient_encounter_list' => ['name' => $prefix . 'patient_encounter_list', 'status' => 1],
        'patient_encounter_add' => ['name' => $prefix . 'patient_encounter_add', 'status' => 1],
        'patient_encounter_edit' => ['name' => $prefix . 'patient_encounter_edit', 'status' => 1],
        'patient_encounter_view' => ['name' => $prefix . 'patient_encounter_view', 'status' => 1],
        'patient_encounter_delete' => ['name' => $prefix . 'patient_encounter_delete', 'status' => 1],


        'medical_records_list' => ['name' => $prefix . 'medical_records_list', 'status' => 1],
        'medical_records_view' => ['name' => $prefix . 'medical_records_view', 'status' => 1],

        'prescription_list' => ['name' => $prefix . 'prescription_list', 'status' => 1],
        'prescription_view' => ['name' => $prefix . 'prescription_view', 'status' => 1],

        'patient_bill_list' => ['name' => $prefix . 'patient_bill_list', 'status' => 1],
        'patient_bill_view' => ['name' => $prefix . 'patient_bill_view', 'status' => 1],

        'patient_review_add' => ['name' => $prefix . 'patient_review_add', 'status' => 1],
        'patient_review_edit' => ['name' => $prefix . 'patient_review_edit', 'status' => 1],
        'patient_review_delete' => ['name' => $prefix . 'patient_review_delete', 'status' => 1],
        'patient_review_get' => ['name' => $prefix . 'patient_review_get', 'status' => 1],

    ]));
}

function kcGetReceptionistPermission()
{

    $prefix = KIVI_CARE_PREFIX;

    return apply_filters('kivicare_receptionist_permission_list', collect([

        'read' => ['name' => 'read', 'status' => 1],

        'settings' => ['name' => $prefix . 'settings', 'status' => 1],

        'dashboard' => ['name' => $prefix . 'dashboard', 'status' => 1],
        'doctor_dashboard' => ['name' => $prefix . 'doctor_dashboard', 'status' => 1],
        'receptionist_profile' => ['name' => $prefix . 'receptionist_profile', 'status' => 1],
        'change_password' => ['name' => $prefix . 'change_password', 'status' => 1],

        'doctor_list' => ['name' => $prefix . 'doctor_list', 'status' => 1],
        'doctor_add' => ['name' => $prefix . 'doctor_add', 'status' => 0],
        'doctor_edit' => ['name' => $prefix . 'doctor_edit', 'status' => 0],
        'doctor_view' => ['name' => $prefix . 'doctor_view', 'status' => 1],
        'doctor_delete' => ['name' => $prefix . 'doctor_delete', 'status' => 0],
        'doctor_resend_credential' => ['name' => $prefix . 'doctor_resend_credential', 'status' => 1],

        'patient_list' => ['name' => $prefix . 'patient_list', 'status' => 1],
        'patient_add' => ['name' => $prefix . 'patient_add', 'status' => 0],
        'patient_edit' => ['name' => $prefix . 'patient_edit', 'status' => 1],
        'patient_view' => ['name' => $prefix . 'patient_view', 'status' => 1],
        'patient_delete' => ['name' => $prefix . 'patient_delete', 'status' => 1],
        'patient_profile' => ['name' => $prefix . 'patient_profile', 'status' => 1],
        'patient_resend_credential' => ['name' => $prefix . 'patient_resend_credential', 'status' => 1],

        'clinic_list' => ['name' => $prefix . 'clinic_list', 'status' => 0],
        'clinic_add' => ['name' => $prefix . 'clinic_add', 'status' => 0],
        'clinic_edit' => ['name' => $prefix . 'clinic_edit', 'status' => 0],
        'clinic_view' => ['name' => $prefix . 'clinic_view', 'status' => 0],
        'clinic_delete' => ['name' => $prefix . 'clinic_delete', 'status' => 0],
        'clinic_profile' => ['name' => $prefix . 'clinic_profile', 'status' => 0],
        'clinic_resend_credential' => ['name' => $prefix . 'clinic_resend_credential', 'status' => 1],

        'service_list' => ['name' => $prefix . 'service_list', 'status' => 1],
        'service_add' => ['name' => $prefix . 'service_add', 'status' => 1],
        'service_edit' => ['name' => $prefix . 'service_edit', 'status' => 1],
        'service_view' => ['name' => $prefix . 'service_view', 'status' => 1],
        'service_delete' => ['name' => $prefix . 'service_delete', 'status' => 1],

        'appointment_list' => ['name' => $prefix . 'appointment_list', 'status' => 1],
        'appointment_add' => ['name' => $prefix . 'appointment_add', 'status' => 1],
        'appointment_edit' => ['name' => $prefix . 'appointment_edit', 'status' => 1],
        'appointment_view' => ['name' => $prefix . 'appointment_view', 'status' => 1],
        'appointment_delete' => ['name' => $prefix . 'appointment_delete', 'status' => 1],

        'patient_encounters' => ['name' => $prefix . 'patient_encounters', 'status' => 1],
        'patient_encounter_list' => ['name' => $prefix . 'patient_encounter_list', 'status' => 1],
        'patient_encounter_add' => ['name' => $prefix . 'patient_encounter_add', 'status' => 1],
        'patient_encounter_edit' => ['name' => $prefix . 'patient_encounter_edit', 'status' => 1],
        'patient_encounter_view' => ['name' => $prefix . 'patient_encounter_view', 'status' => 1],
        'patient_encounter_delete' => ['name' => $prefix . 'patient_encounter_delete', 'status' => 1],

        /* Creating a list of permissions for the patient encounters module. */
        'encounters_template_list' => ['name' => $prefix . 'encounters_template_list', 'status' => 1],
        'encounters_template_add' => ['name' => $prefix . 'encounters_template_add', 'status' => 1],
        'encounters_template_edit' => ['name' => $prefix . 'encounters_template_edit', 'status' => 1],
        'encounters_template_view' => ['name' => $prefix . 'encounters_template_view', 'status' => 1],
        'encounters_template_delete' => ['name' => $prefix . 'encounters_template_delete', 'status' => 1],

        'patient_appointment_status_change' => ['name' => $prefix . 'patient_appointment_status_change', 'status' => 1],

        'medical_records_list' => ['name' => $prefix . 'medical_records_list', 'status' => 1],
        'medical_records_add' => ['name' => $prefix . 'medical_records_add', 'status' => 1],
        'medical_records_edit' => ['name' => $prefix . 'medical_records_edit', 'status' => 1],
        'medical_records_view' => ['name' => $prefix . 'medical_records_view', 'status' => 1],
        'medical_records_delete' => ['name' => $prefix . 'medical_records_delete', 'status' => 1],

        'prescription_list' => ['name' => $prefix . 'prescription_list', 'status' => 1],
        'prescription_add' => ['name' => $prefix . 'prescription_add', 'status' => 1],
        'prescription_edit' => ['name' => $prefix . 'prescription_edit', 'status' => 1],
        'prescription_view' => ['name' => $prefix . 'prescription_view', 'status' => 1],
        'prescription_delete' => ['name' => $prefix . 'prescription_delete', 'status' => 1],

        'patient_bill_list' => ['name' => $prefix . 'patient_bill_list', 'status' => 1],
        'patient_bill_add' => ['name' => $prefix . 'patient_bill_add', 'status' => 1],
        'patient_bill_edit' => ['name' => $prefix . 'patient_bill_edit', 'status' => 1],
        'patient_bill_view' => ['name' => $prefix . 'patient_bill_view', 'status' => 1],
        'patient_bill_delete' => ['name' => $prefix . 'patient_bill_delete', 'status' => 1],

        'clinic_schedule' => ['name' => $prefix . 'clinic_schedule', 'status' => 1],

        'patient_clinic' => ['name' => $prefix . 'patient_clinic', 'status' => 0],

        'patient_report' => ['name' => $prefix . 'patient_report', 'status' => 1],
        'patient_report_add' => ['name' => $prefix . 'patient_report_add', 'status' => 1],
        'patient_report_view' => ['name' => $prefix . 'patient_report_view', 'status' => 1],
        'patient_report_delete' => ['name' => $prefix . 'patient_report_delete', 'status' => 1],

        'doctor_session_list' => ['name' => $prefix . 'doctor_session_list', 'status' => 1],
        'doctor_session_add' => ['name' => $prefix . 'doctor_session_add', 'status' => 1],
        'doctor_session_edit' => ['name' => $prefix . 'doctor_session_edit', 'status' => 1],
        'doctor_session_delete' => ['name' => $prefix . 'doctor_session_delete', 'status' => 1],

        'static_data_list' => ['name' => $prefix . 'static_data_list', 'status' => 1],
        'static_data_add' => ['name' => $prefix . 'static_data_add', 'status' => 1],
        'static_data_edit' => ['name' => $prefix . 'static_data_edit', 'status' => 1],
        'static_data_view' => ['name' => $prefix . 'static_data_view', 'status' => 1],
        'static_data_delete' => ['name' => $prefix . 'static_data_delete', 'status' => 1],

        'dashboard_total_patient' => ['name' => $prefix . 'dashboard_total_patient', 'status' => 1],
        'dashboard_total_doctor' => ['name' => $prefix . 'dashboard_total_doctor', 'status' => 1],
        'dashboard_total_appointment' => ['name' => $prefix . 'dashboard_total_appointment', 'status' => 1],
        'dashboard_total_revenue' => ['name' => $prefix . 'dashboard_total_revenue', 'status' => 1],
    ]));
}

function kcCheckPermission($permission_name)
{

    $user_id = get_current_user_id();

    $userObj = (new WP_User($user_id));

    $kc_roles = [
        "administrator" => "kcGetAdminPermissions",
        KIVI_CARE_PREFIX . "clinic_admin" => "kcGetAdminPermissions",
        KIVI_CARE_PREFIX . "receptionist" => "kcGetReceptionistPermission",
        KIVI_CARE_PREFIX . "doctor" => "kcGetDoctorPermission",
        KIVI_CARE_PREFIX . "patient" => "kcGetPatientPermissions"
    ];

    $user_kc_roles = array_intersect(array_keys($kc_roles), $userObj->roles);

    if (!empty($user_kc_roles)) {
        $kc_role = array_pop( array_reverse($user_kc_roles));
        $permissions = $kc_roles[$kc_role]()->toArray();
        if(has_filter('kcpro_get_all_permission')){
            $permission =apply_filters('kcpro_get_all_permission','');
            // Initialize an empty array for the output
            $get_pero_permissions = [];

            // Flatten the capabilities into the required format
            $capabilities = $permission['data'][$kc_role]['capabilities']->flatMap(function ($module) {
                return $module;
            });

            // Transform the collection into the desired output format
            $capabilities->each(function ($status, $name) use (&$get_pero_permissions) {
                $get_pero_permissions[str_replace(KIVI_CARE_PREFIX,'',$name)] = [
                    'name' => $name,
                    'status' => $status ? 1 : 0
                ];
            });

            $permissions= array_merge($permissions,$get_pero_permissions);
        }
    }



    return isset($permissions[$permission_name]['name']) && current_user_can($permissions[$permission_name]['name']);
}

function kcGetPermission($permission_name)
{
    $permissions = kcGetAdminPermissions()->toArray();

    return $permissions[$permission_name]['name'];
}

function kcGetPluginVersion($pluginDomain)
{
    if (!function_exists('get_plugins')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugins = get_plugins();
    foreach ($plugins as $key => $value) {
        if ($value['TextDomain'] === $pluginDomain) {
            return $value['Version'];
        }
    }
    return '0';
}
function getKiviCareProVersion()
{

    return kcGetPluginVersion('kiviCare-clinic-&-patient-management-system-pro');
}

function isKiviCareProActive()
{
    return kcCheckPluginActive('kiviCare-clinic-&-patient-management-system-pro');
}

function isKiviCareTelemedActive()
{
    return kcCheckPluginActive('kiviCare-telemed-addon');
}

function isKiviCareGoogleMeetActive()
{
    return kcCheckPluginActive('kc-googlemeet');
}


function isKiviCareRazorpayActive()
{
    return kcCheckPluginActive('kivicare-razorpay-addon');
}
function isKiviCareStripepayActive()
{
    return kcCheckPluginActive('kivicare-stripepay-addon');
}
function isKiviCareAPIActive()
{
    return kcCheckPluginActive('kivicare-api');
}

function isKiviCareBodyChartActive()
{
    return kcCheckPluginActive('kivicare-body-chart-addon');
}

function isKiviCareWebhooksAddonActive()
{
    return kcCheckPluginActive('kivicare-webhooks-addon');
}

function iskcWooCommerceActive()
{
    return kcCheckPluginActive('woocommerce');
}

function kcCheckPluginActive($pluginTextDomain)
{

    if (!function_exists('get_plugins')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugins = get_plugins();

    foreach ($plugins as $key => $value) {

        if ($value['TextDomain'] === $pluginTextDomain) {
            if (is_plugin_active($key)) {
                return true;
            }
        }
    }
    return false;
}

function kcCommonTemplate($type)
{
    $prefix = KIVI_CARE_PREFIX;
    $mail_template = $type === "sms" ? $prefix . 'sms_tmp' : $prefix . 'mail_tmp';
    $data = [
        [
            'post_name' => $prefix . 'patient_register',
            'post_content' => '<p>Welcome to KiviCare ,</p><p>Your registration process with {{user_email}} is successfully completed, and your password is  {{user_password}} </p><p>Thank you.</p>',
            'post_title' => 'Patient Registration Template',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'receptionist_register',
            'post_content' => '<p>Welcome to KiviCare ,</p><p>Your registration process with {{user_email}} is successfully completed, and your password is  {{user_password}} </p><p>Thank you.</p>',
            'post_title' => 'Receptionist Registration Template',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'doctor_registration',
            'post_content' => '<p>Welcome to KiviCare ,</p><p>You are successfully registered with  </p><p> Your  email:  {{user_email}}  ,  username: {{user_name}} and password: {{user_password}}  </p><p>Thank you.</p>',
            'post_title' => 'Doctor Registration Template',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'doctor_book_appointment',
            'post_content' => '<p> New appointment </p><p> You have new appointment on </p><p> Date: {{appointment_date}}  , Time : {{appointment_time}} ,Patient : {{patient_name}} </p><p> Thank you. </p>',
            'post_title' => 'Doctor Booked Appointment Template',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'resend_user_credential',
            'post_content' => '<p> Welcome to KiviCare ,</p><p> Your kivicare account user credential </p><p> Your  email:  {{user_email}}  ,  username: {{user_name}} and password: {{user_password}}  </p><p>Thank you.</p>',
            'post_title' => 'Resend user credentials',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'cancel_appointment',
            'post_content' => '<p> Welcome to KiviCare ,</p><p> Your appointment Booking is cancel. </p><p> Date: {{appointment_date}}  , Time : {{appointment_time}}   </p><p>Clinic: {{clinic_name}} Doctor: {{doctor_name}}</p><p>Thank you.</p>',
            'post_title' => 'Cancel appointment',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'zoom_link',
            'post_content' => '<p> Zoom video conference </p><p> Your have new appointment on </p><p> Date: {{appointment_date}}  , Time : {{appointment_time}} ,Doctor : {{doctor_name}} , Zoom Link : {{zoom_link}} </p><p> Thank you. </p>',
            'post_title' => 'Video Conference appointment Template',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'add_doctor_zoom_link',
            'post_content' => '<p> Zoom video conference </p><p> Your have new appointment on </p><p> Date: {{appointment_date}}  , Time : {{appointment_time}} ,Patient : {{patient_name}} , Zoom Link : {{add_doctor_zoom_link}} </p><p> Thank you. </p>',
            'post_title' => 'Doctor Zoom Video Conference appointment Template',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'clinic_admin_registration',
            'post_content' => '<p> Welcome to Clinic, </p><p> You are successfully registered as clinic admin </p><p> Your email:  {{user_email}}  ,  username: {{user_name}} and password: {{user_password}} </p> <p>Thank you.</p>',
            'post_title' => 'Clinic Admin Registration',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'clinic_book_appointment',
            'post_content' => '<p> New appointment </p><p> New appointment Book on {{current_date}} </p><p> For Date: {{appointment_date}}  , Time : {{appointment_time}} , Patient : {{patient_name}} , Doctor : {{doctor_name}}  </p><p> Thank you. </p>',
            'post_title' => 'Clinic Booked Appointment Template',
            'post_type' => $mail_template,
            'post_status' => 'publish'
        ],
        [
            'post_name' => $prefix . 'book_appointment_reminder',
            'post_content' => '<p> Welcome to KiviCare ,</p><p> You Have appointment  on </p><p> {{appointment_date}}  , Time : {{appointment_time}}  </p><p> Thank you. </p>',
            'post_title' => 'Patient Appointment Reminder',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'user_verified',
            'post_content' => '<p> Your Account Has been Verified By admin On Date: {{current_date}} </p><p> Login Page: {{login_url}} </p><p> Thank you. </p>',
            'post_title' => 'User Verified By Admin',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'admin_new_user_register',
            'post_content' => '<p> New User Register On site {{site_url}} On Date: {{current_date}} </p><p> Name : {{user_name}} </p><p> Email :{{user_email}} </p><p> Contact No :{{user_contact}} </p><p> User Role :{{user_role}} </p><p> Thank you. </p>',
            'post_title' => 'New User Register On site',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'meet_link',
            'post_content' => '<p> Google Meet conference </p><p> Your have new appointment on </p><p> Date: {{appointment_date}}  , Time : {{appointment_time}} ,Doctor : {{doctor_name}} , Google Meet Link : {{meet_link}} </p><p> Event Link {{meet_event_link}} </p><p> Thank you. </p>',
            'post_title' => 'Google Meet Video Conference appointment Template',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'add_doctor_meet_link',
            'post_content' => '<p> Google Meet conference </p><p> Your have new appointment on </p><p> Date: {{appointment_date}}  , Time : {{appointment_time}} ,Patient : {{patient_name}} , Google Meet Link : {{meet_link}} </p><p> Event Link {{meet_event_link}} </p><p> Thank you. </p>',
            'post_title' => 'Doctor Google Meet Video Conference appointment Template',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'patient_clinic_check_in_check_out',
            'post_content' => '<p> Welcome to KiviCare ,</p><p> New Patient Check In to Clinic </p> <p> Patient: {{patient_name}} </p> <p> Patient Email: {{patient_email}}</p><p> Check In Date: {{current_date}}</p><p> Thank you. </p>',
            'post_title' => 'Patient Clinic In',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $mail_template === 'kiviCare_sms_tmp' ? $prefix . 'add_appointment' : $prefix . 'book_appointment',
            'post_content' => '<p> Welcome to KiviCare ,</p><p> Your appointment has been booked  successfully on </p><p> {{appointment_date}}  , Time : {{appointment_time}}  </p><p> Thank you. </p>',
            'post_title' => 'Patient Appointment Booking Template',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ],
        [
            'post_name' => $prefix . 'payment_pending',
            'post_content' => '<p> Appointment Payment ,</p><p> Your Appointment is cancelled due to pending payment </p><p>Thank you.</p>',
            'post_title' => 'Appointment Payment Pending Template',
            'post_type' => $mail_template,
            'post_status' => 'publish',
        ]
    ];

    if ($mail_template === 'kivicare_sms_tmp') {
        $temp = [
            [
                'post_name' => $prefix . 'encounter_close',
                'post_content' => '<p> Welcome to Clinic ,</p><p> Your appointment has been closed with your total amount {{total_amount}}  </p><p>Thank you.</p>',
                'post_title' => 'Encounter SMS Template',
                'post_type' => $mail_template,
                'post_status' => 'publish',
            ]
        ];
    } else {
        $temp = [
            [
                'post_name' => $prefix . 'patient_report',
                'post_content' => '<p> Welcome to KiviCare ,</p><p> Find your Report in attachment </p><p> Thank you. </p>',
                'post_title' => 'Patient Report',
                'post_type' => $mail_template,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $prefix . 'book_prescription',
                'post_content' => '<p> Welcome to KiviCare ,</p><p> You Have Medicine Prescription on </p><p> Clinic : {{clinic_name}}</p><p>Doctor : {{doctor_name}}</p><p>Prescription :{{prescription}} </p><p> Thank you. </p>',
                'post_title' => 'Patient Prescription Reminder',
                'post_type' => $mail_template,
                'post_status' => 'publish',
            ]
        ];
    }
    $data = apply_filters('kivicare_notification_template_post_array', $data, $mail_template, $prefix);
    return array_merge($data, $temp);
}

function kcAddMailSmsPosts($default_template)
{
    global $wpdb;
    $postTable = $wpdb->prefix . 'posts';
    $query = "SELECT LOWER(CONCAT(post_type,post_name)) AS post_join_name FROM {$postTable}  where post_type IN ('kivicare_sms_tmp','kivicare_mail_tmp','kivicare_gcal_tmp','kivicare_gmeet_tmp')";
    $results = collect($wpdb->get_results($query))->pluck('post_join_name')->toArray();
    foreach ($default_template as $email_template) {
        $post_join_name = strtolower($email_template['post_type'] . $email_template['post_name']);
        if (!in_array($post_join_name, $results)) {
            wp_insert_post($email_template);
        }
    }
}

function kcGetEmailSmsDynamicKeys()
{
    $data = [
        'kivicare_book_prescription' => [
            '{{prescription}}',
            '{{clinic_name}}',
            '{{clinic_email}}',
            '{{clinic_contact_number}}',
            '{{clinic_address}}',
            '{{doctor_name}}',
            '{{doctor_email}}',
            '{{doctor_contact_number}}',
            '{{current_date}}',
            '{{current_date_time}}',
            '{{service_name}}',
        ],
        'kivicare_payment_pending' => [
            '{{current_date}}',
            '{{current_date_time}}'
        ],
        'kivicare_meet_link' => [
            '{{appointment_date}}',
            '{{appointment_time}}',
            '{{patient_name}}',
            '{{meet_link}}',
            '{{meet_event_link}}',
            '{{clinic_name}}',
            '{{clinic_email}}',
            '{{clinic_contact_number}}',
            '{{clinic_address}}',
            '{{doctor_name}}',
            '{{doctor_email}}',
            '{{doctor_contact_number}}',
            '{{current_date}}',
            '{{current_date_time}}',
        ],
        'kivicare_add_doctor_meet_link' => [
            '{{appointment_date}}',
            '{{appointment_time}}',
            '{{meet_link}}',
            '{{meet_event_link}}',
            '{{clinic_name}}',
            '{{clinic_email}}',
            '{{clinic_contact_number}}',
            '{{clinic_address}}',
            '{{patient_name}}',
            '{{patient_email}}',
            '{{patient_contact_number}}',
            '{{current_date}}',
            '{{current_date_time}}',
        ],
        'kivicare_book_appointment' => [
            '{{appointment_date}}',
            '{{appointment_time}}',
            '{{service_name}}',
            '{{clinic_name}}',
            '{{clinic_email}}',
            '{{clinic_contact_number}}',
            '{{clinic_address}}',
            '{{doctor_name}}',
            '{{doctor_email}}',
            '{{doctor_contact_number}}',
            '{{current_date}}',
            '{{current_date_time}}',
            '{{service_name}}',
        ],
        'kivicare_book_appointment_reminder' => [
            '{{appointment_date}}',
            '{{appointment_time}}',
            '{{clinic_name}}',
            '{{clinic_email}}',
            '{{clinic_contact_number}}',
            '{{clinic_address}}',
            '{{doctor_name}}',
            '{{doctor_email}}',
            '{{doctor_contact_number}}',
            '{{current_date}}',
            '{{current_date_time}}',
        ],
        'kivicare_book_appointment_reminder_for_doctor' => [
            '{{appointment_date}}',
            '{{appointment_time}}',
            '{{clinic_name}}',
            '{{clinic_email}}',
            '{{clinic_contact_number}}',
            '{{clinic_address}}',
            '{{doctor_name}}',
            '{{doctor_email}}',
            '{{patient_name}}',
            '{{doctor_contact_number}}',
            '{{current_date}}',
            '{{current_date_time}}',
        ],
        'kivicare_patient_register' => [
            '{{user_email}}',
            '{{user_password}}',
            '{{login_url}}',
            '{{widgets_login_url}}',
            '{{current_date}}',
            '{{current_date_time}}',
            '{{appointment_page_url}}'
        ],
        'kivicare_receptionist_register' => [
            '{{user_email}}',
            '{{user_password}}',
            '{{login_url}}',
            '{{current_date}}',
            '{{current_date_time}}'
        ],
        'kivicare_doctor_registration' => [
            '{{user_email}}',
            '{{user_name}}',
            '{{user_password}}',
            '{{login_url}}',
            '{{current_date}}',
            '{{current_date_time}}'
        ],
        'kivicare_doctor_book_appointment' => [
            '{{appointment_date}}',
            '{{appointment_time}}',
            '{{service_name}}',
            '{{patient_name}}',
            '{{patient_email}}',
            '{{clinic_name}}',
            '{{clinic_email}}',
            '{{clinic_contact_number}}',
            '{{clinic_address}}',
            '{{current_date}}',
            '{{current_date_time}}',
        ],
        'kivicare_resend_user_credential' => [
            '{{user_email}}',
            '{{user_name}}',
            '{{user_password}}',
            '{{login_url}}',
            '{{current_date}}',
            '{{current_date_time}}'
        ],
        'kivicare_cancel_appointment' => [
            '{{appointment_date}}',
            '{{appointment_time}}',
            '{{clinic_name}}',
            '{{clinic_email}}',
            '{{clinic_contact_number}}',
            '{{clinic_address}}',
            '{{doctor_name}}',
            '{{doctor_email}}',
            '{{doctor_contact_number}}',
            '{{current_date}}',
            '{{current_date_time}}',
        ],
        'kivicare_zoom_link' => [
            '{{appointment_date}}',
            '{{appointment_time}}',
            '{{zoom_link}}',
            '{{clinic_name}}',
            '{{clinic_email}}',
            '{{clinic_contact_number}}',
            '{{clinic_address}}',
            '{{doctor_name}}',
            '{{doctor_email}}',
            '{{doctor_contact_number}}',
            '{{current_date}}',
            '{{current_date_time}}',
        ],
        'kivicare_add_doctor_zoom_link' => [
            '{{appointment_date}}',
            '{{appointment_time}}',
            '{{add_doctor_zoom_link}}',
            '{{patient_name}}',
            '{{patient_email}}',
            '{{patient_contact_number}}',
            '{{clinic_name}}',
            '{{clinic_email}}',
            '{{clinic_contact_number}}',
            '{{clinic_address}}',
            '{{current_date}}',
            '{{current_date_time}}',
        ],
        'kivicare_clinic_admin_registration' => [
            '{{user_email}}',
            '{{user_name}}',
            '{{user_password}}',
            '{{login_url}}',
            '{{current_date}}',
            '{{current_date_time}}'
        ],
        'kivicare_clinic_book_appointment' => [
            '{{appointment_date}}',
            '{{appointment_time}}',
            '{{service_name}}',
            '{{patient_name}}',
            '{{patient_email}}',
            '{{patient_contact_number}}',
            '{{doctor_name}}',
            '{{doctor_email}}',
            '{{doctor_contact_number}}',
            '{{current_date}}',
            '{{current_date_time}}',
        ],
        'kivicare_encounter_close' => [
            '{{total_amount}}',
            '{{current_date}}',
            '{{current_date_time}}'
        ],
        'kivicare_patient_report' => [
            '{{current_date}}',
            '{{current_date_time}}',
        ],
        'kivicare_add_appointment' => [
            '{{appointment_date}}',
            '{{appointment_time}}',
            '{{current_date}}',
            '{{current_date_time}}',
            '{{clinic_name}}',
            '{{clinic_email}}',
            '{{clinic_contact_number}}',
            '{{clinic_address}}',
            '{{doctor_name}}',
            '{{doctor_email}}',
            '{{doctor_contact_number}}',
            '{{service_name}}',
        ]

    ];

    return apply_filters('kivicare_template_dynamic_keys', $data);
}

function kcGetNotificationTemplateLists($template_type)
{
    $prefix = KIVI_CARE_PREFIX;
    $args['post_type'] = strtolower($prefix . $template_type . '_tmp');
    $args['nopaging'] = true;
    $args['post_status'] = 'any';
    $template_result = get_posts($args);
    $user_wise_template['patient'] = [
        'kivicare_patient_register',
        'kivicare_book_appointment_reminder',
        'kivicare_book_appointment',
        'kivicare_add_appointment',
        'kivicare_cancel_appointment',
        'kivicare_encounter_close',
        'kivicare_zoom_link',
        'kivicare_meet_link',
        'kivicare_patient_clinic_check_in_check_out',
        'kivicare_patient_report',
        'kivicare_book_prescription',
    ];


    $user_wise_template['doctor'] = [
        'kivicare_doctor_registration',
        'kivicare_doctor_book_appointment',
        'kivicare_add_doctor_zoom_link',
        'kivicare_add_doctor_meet_link',
        'kivicare_book_appointment_reminder_for_doctor',
    ];
    $user_wise_template['clinic'] = [
        'kivicare_clinic_admin_registration',
        'kivicare_clinic_book_appointment'
    ];
    $user_wise_template['receptionist'] = [
        'kivicare_receptionist_register'
    ];

    $user_wise_template['common'] = [
        'kivicare_resend_user_credential',
        'kivicare_user_verified',
        'kivicare_admin_new_user_register',
        'kivicare_payment_pending',
        'kivicare_patient_invoice'
    ];

    foreach ($template_result as $post) {
        $post->content_sid = get_post_meta($post->ID, 'content_sid', true);
    }

    $user_wise_template = apply_filters('kivicare_user_wise_notification_template', $user_wise_template);

    // Filter out templates that don't follow the correct naming pattern
    $valid_templates = [];
    $seen_base_names = [];

    foreach ($template_result as $post) {
        // Check if the post_name matches the exact pattern (no suffixes)
        $is_valid_name = in_array($post->post_name, array_merge(
            $user_wise_template['patient'],
            $user_wise_template['doctor'],
            $user_wise_template['clinic'],
            $user_wise_template['receptionist'],
            $user_wise_template['common']
        ));
        
        // If it's a valid exact match, add it to results
        if ($is_valid_name) {
            if (!in_array($post->post_name, $seen_base_names)) {
                $seen_base_names[] = $post->post_name;
                $valid_templates[] = $post;
            }
        }
    }

    $template_result = collect($valid_templates)->sortBy('ID')->map(function ($value) use ($user_wise_template) {
        if (in_array($value->post_name, $user_wise_template['patient'])) {
            $value->user_type = 'patient';
        }
        if (in_array($value->post_name, $user_wise_template['doctor'])) {
            $value->user_type = 'doctor';
        }
        if (in_array($value->post_name, $user_wise_template['clinic'])) {
            $value->user_type = 'clinic';
        }
        if (in_array($value->post_name, $user_wise_template['receptionist'])) {
            $value->user_type = 'receptionist';
        }
        if (in_array($value->post_name, $user_wise_template['common'])) {
            $value->user_type = 'common';
        }
        if (empty($value->user_type)) {
            $value->user_type = 'common';
        }
        $value->post_content = wp_kses($value->post_content, kcAllowedHtml());
        return $value;
    });

    return $template_result->groupBy('user_type')->sortKeys();
}

function kcAllowedHtml()
{

    return array(
        'a' => array(
            'class' => array(),
            'href' => array(),
            'rel' => array(),
            'title' => array(),
            'style' => array(),
        ),
        'abbr' => array(
            'title' => array(),
        ),
        'b' => array(
            'style' => array(),
        ),
        'blockquote' => array(
            'cite' => array(),
        ),
        'cite' => array(
            'title' => array(),
            'style' => array(),
        ),
        'code' => array(),
        'del' => array(
            'datetime' => array(),
            'title' => array(),
        ),
        'dd' => array(),
        'div' => array(
            'class' => array(),
            'title' => array(),
            'style' => array(),
        ),
        'dl' => array(),
        'dt' => array(),
        'em' => array(),
        'h1' => array(
            'style' => array(),
        ),
        'h2' => array(
            'style' => array(),
        ),
        'h3' => array(
            'style' => array(),
        ),
        'h4' => array(
            'style' => array(),
        ),
        'h5' => array(
            'style' => array(),
        ),
        'h6' => array(
            'style' => array(),
        ),
        'i' => array(),
        'img' => array(
            'alt' => array(),
            'class' => array(),
            'height' => array(),
            'src' => array(),
            'width' => array(),
            'style' => array(),
        ),
        'li' => array(
            'class' => array(),
            'style' => array(),
        ),
        'ol' => array(
            'class' => array(),
            'style' => array(),
        ),
        'p' => array(
            'class' => array(),
            'style' => array(),
        ),
        'q' => array(
            'cite' => array(),
            'title' => array(),
        ),
        'span' => array(
            'class' => array(),
            'title' => array(),
            'style' => array(),
        ),
        'strike' => array(),
        'strong' => array(),
        'ul' => array(
            'class' => array(),
            'style' => array(),
        ),
        'svg' => array(
            'class' => true,
            'aria-hidden' => true,
            'aria-labelledby' => true,
            'role' => true,
            'xmlns' => true,
            'width' => true,
            'height' => true,
            'viewbox' => true // <= Must be lower case!
        ),
        'g' => array('fill' => true),
        'title' => array('title' => true),
        'path' => array(
            'd' => true,
            'fill' => true
        ),
        'table' => array(
            'class' => array(),
            'style' => array(),
            'border' => array(),
            'cellpadding' => array(),
            'cellspacing' => array(),
        ),
        'tr' => array(
            'class' => array(),
            'style' => array(),
        ),
        'td' => array(
            'class' => array(),
            'style' => array(),
            'colspan' => array(),
            'rowspan' => array(),
        ),
        'th' => array(
            'class' => array(),
            'style' => array(),
            'colspan' => array(),
            'rowspan' => array(),
        )
    );
}
/**
 * // Data param required date
 *
 * @param $data
 *
 * @return bool
 */

function kcSendEmail($data)
{

    if (!function_exists('wp_mail')) {
        return false;
    }
    global $wpdb;
    $args['post_name'] = strtolower(KIVI_CARE_PREFIX . $data['email_template_type']);
    $args['post_type'] = strtolower(KIVI_CARE_PREFIX . 'mail_tmp');

    $query = "SELECT * FROM {$wpdb->prefix}posts WHERE `post_name` = '" . $args['post_name'] . "' AND `post_type` = '" . $args['post_type'] . "' AND post_status = 'publish' ";

    $check_exist_post = $wpdb->get_row($query, ARRAY_A);


    if (!empty($check_exist_post)) {

        $email_content = $check_exist_post['post_content'];
        $post_title = !empty($check_exist_post['post_title']) ? $check_exist_post['post_title'] : '';
        $email_content = kcEmailContentKeyReplace($email_content, $data);
        $small_prefix = strtolower(KIVI_CARE_PREFIX);

        
        switch ($args['post_name']) {
            case $small_prefix . 'doctor_registration':
                $email_title = esc_html__('Doctor Registration', 'kc-lang');
                break;
            case $small_prefix . 'patient_registration':
                $email_title = esc_html__('Patient Registration', 'kc-lang');
                break;
            case $small_prefix . 'receptionist_registration':
                $email_title = esc_html__('Receptionist Registration', 'kc-lang');
                break;
            case $small_prefix . 'book_appointment':
                $email_title = esc_html__('Patient Appointment Booking', 'kc-lang');
                break;
            case $small_prefix . 'doctor_book_appointment':
                $email_title = esc_html__('Doctor Appointment Booking', 'kc-lang');
                break;
            case $small_prefix . 'clinic_book_appointment':
                $email_title = esc_html__('Clinic Appointment Booking', 'kc-lang');
                break;
            case $small_prefix . 'zoom_link':
            case $small_prefix . 'meet_link':
                $email_title = esc_html__('Patient Telemed Appointment Booking', 'kc-lang');
                break;
            case $small_prefix . 'clinic_admin_registration':
                $email_title = esc_html__('Clinic Admin Registration', 'kc-lang');
                break;
            case $small_prefix . 'payment_pending':
                $email_title = esc_html__('Patient Appointment Payment', 'kc-lang');
                break;
            case $small_prefix . 'add_doctor_zoom_link':
            case $small_prefix . 'add_doctor_meet_link':
                $email_title = esc_html__('Doctor Telemed Appointment Booking', 'kc-lang');
                break;
            case $small_prefix . 'book_appointment_reminder':
                $email_title = esc_html__('Patient Booked Appointment Reminder', 'kc-lang');
                break;
            case $small_prefix . 'book_appointment_reminder_for_doctor':
                $email_title = esc_html__('Patient Booked Appointment Reminder for Doctor', 'kc-lang');
                break;
            case $small_prefix . 'book_prescription':
                $email_title = esc_html__('Patient Prescription', 'kc-lang');
                break;
            case $small_prefix . 'cancel_appointment':
                $email_title = esc_html__('Patient Appointment Cancel', 'kc-lang');
                break;
            case $small_prefix . 'patient_report':
                $email_title = esc_html__('Patient Report', 'kc-lang');
                break;
            case $small_prefix . 'patient_clinic_check_in_check_out':
                $email_title = esc_html__('Patient Check In', 'kc-lang');
                break;
            case $small_prefix . 'admin_new_user_register':
                $email_title = esc_html__('Admin New User Register', 'kc-lang');
                break;
            case $small_prefix . 'user_verified':
                $email_title = esc_html__('Account Verified', 'kc-lang');
                break;
            default:
                $email_title = esc_html__('Welcome To Clinic ', 'kc-lang');
        }

        $email_title = !empty($post_title) ? $post_title : $email_title;

        $email_title = apply_filters('kivicare_email_subject_title', $email_title, $small_prefix, $data);

        do_action("kc_before_send_email", $args['post_name'], $data);

        if ($data['email_template_type'] == 'admin_new_user_register') {
            $admins = get_users(['role' => 'administrator', 'number' => 1]);
            $data['user_email'] = [];
            foreach ($admins as $admin) {
                $data['user_email'][] = $admin->data->user_email;
            }
        }

        $email_content = wp_kses($email_content, kcAllowedHtml());
        if (isset($data['attachment']) && $data['attachment']) {
            $email_status = wp_mail($data['user_email'], $email_title, $email_content, '', isset($data['attachment_file']) ? $data['attachment_file'] : '');
        } else {
            $email_status = wp_mail($data['user_email'], $email_title, $email_content);
        }

        if ($email_status) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

/**
 * // Data param required content
 *
 * @param $content - email content for replace email template key
 *
 * @return string
 *
 */

function kcEmailContentKeyReplace($content, $data,$whatsApp=false)
{

    $keys = [
        '{{user_name}}' => isset($data['username']) ? esc_html($data['username']) : '',
        '{{user_password}}' => isset($data['password']) ? esc_html($data['password']) : '',
        '{{user_email}}' => isset($data['user_email']) ? esc_html($data['user_email']) : '',
        '{{appointment_date}}' => isset($data['appointment_date']) ? esc_html( $data['appointment_date']) : '',
        '{{appointment_time}}' => isset($data['appointment_time']) ?
            (esc_html(date(get_option('time_format'), strtotime($data['appointment_time'])))) : '',
        '{{appointment_time_zone}}' => !empty(get_option('timezone_string')) ? get_option('timezone_string') : '',
        '{{patient_name}}' => isset($data['patient_name']) ? $data['patient_name'] : '',
        '{{doctor_name}}' => isset($data['doctor_name']) ? $data['doctor_name'] : '',
        '{{zoom_link}}' => isset($data['zoom_link']) ? $data['zoom_link'] : '',
        '{{add_doctor_zoom_link}}' => isset($data['add_doctor_zoom_link']) ? $data['add_doctor_zoom_link'] : '',
        '{{service_name}}' => isset($data['service_name']) ? $data['service_name'] : '',
        '{{current_date}}' => current_time(get_option('date_format')),
        '{{current_date_time}}' => current_time(get_option('date_format') . " " . get_option('time_format')),
        '{{total_amount}}' => isset($data['total_amount']) ? $data['total_amount'] : '',
        '{{clinic_name}}' => isset($data['clinic_name']) ? $data['clinic_name'] : '',
        '{{prescription}}' => isset($data['prescription']) ? $data['prescription'] : '',
        '{{meet_link}}' => isset($data['meet_link']) ? $data['meet_link'] : '',
        '{{meet_event_link}}' => isset($data['meet_event_link']) ? $data['meet_event_link'] : '',
        '{{widgets_login_url}}' => kcGetDashboardPageUrl(),
        '{{login_url}}' => wp_login_url(),
        '{{appointment_page_url}}' => kcGetAppointmentPageUrl(),
        '{{doctor_email}}' => isset($data['doctor_email']) ? $data['doctor_email'] : '',
        '{{doctor_contact_number}}' => isset($data['doctor_contact_number']) ? $data['doctor_contact_number'] : '',
        '{{clinic_email}}' => isset($data['clinic_email']) ? $data['clinic_email'] : '',
        '{{clinic_contact_number}}' => isset($data['clinic_contact_number']) ? $data['clinic_contact_number'] : '',
        '{{patient_contact_number}}' => isset($data['patient_contact_number']) ? $data['patient_contact_number'] : '',
        '{{clinic_address}}' => isset($data['clinic_address']) ? $data['clinic_address'] : '',
        '{{patient_email}}' => isset($data['patient_email']) ? $data['patient_email'] : '',
        '{{user_contact}}' => isset($data['user_contact']) ? $data['user_contact'] : '',
        '{{site_url}}' => get_site_url(),
        '{{user_role}}' => isset($data['user_role']) ? $data['user_role'] : '',
        '{{appointment_desc}}' => isset($data['description']) ? $data['description'] : ''
    ];

    $keys = apply_filters('kivicare_template_dynamic_keys_value', $keys, $data);
    if ($whatsApp) {
        $result = [];
        foreach ($keys as $key => $value) {
            if (strpos($content, $key) !== false) {
                $result[trim($key, '{}')] = $value;
            }
        }
        return $result;
    }
    return str_replace(array_keys($keys), array_values($keys), $content);
}

function kcCheckUserEmailAlreadyUsed($request_data, $clinic = false)
{
    $status = true;
    $message = '';
    //check if email already used
    if($clinic){
        global $wpdb;
        $clinic_table_name = $wpdb->prefix . 'kc_clinics';
        $email_exists = $wpdb->get_var(" SELECT COUNT(*) FROM {$clinic_table_name}  WHERE `email` = '{$request_data['user_email']}' ")>0;
    }else{
        $email_exists = email_exists($request_data['user_email']);
        if (!empty($email_exists)) {
            //if editing user and email is used by editing user only
            if (!empty($request_data['ID']) && (int) $request_data['ID'] == $email_exists) {
                $email_exists = false;
            }
        }
    }
    
    if ($email_exists) {
        $status = false;
        $message = $clinic ? esc_html__('There already exists an user registered with this email address,please use other email ID for clinic email', 'kc-lang') : esc_html__('There already exists an User registered with this email address,please use other email ID', 'kc-lang');
    }
    return [
        'status' => $status,
        'message' => $message
    ];
}

function kcSendAppointmentSmsOnPro($appointment_id, $service = '')
{
    $sms1 = $sms2 = $sms3 = '';
    $appointment_id = (int) $appointment_id;
    if (kcCheckSmsOptionEnable() || kcCheckWhatsappOptionEnable()) {
        $sms1 = apply_filters('kcpro_send_sms', [
            'type' => 'clinic_book_appointment',
            'appointment_id' => $appointment_id,
            'service' => $service
        ]);

        $sms2 = apply_filters('kcpro_send_sms', [
            'type' => 'doctor_book_appointment',
            'appointment_id' => $appointment_id,
            'service' => $service
        ]);

        $sms3 = apply_filters('kcpro_send_sms', [
            'type' => 'add_appointment',
            'appointment_id' => $appointment_id,
            'service' => $service
        ]);
    }

    return [
        'clinic_book_appointment' => $sms1,
        'doctor_book_appointment' => $sms2,
        'add_appointment' => $sms3
    ];
}

function kcSendAppointmentZoomSms($appointment_id)
{
    $sms = $sms1 = '';
    $appointment_id = (int) $appointment_id;
    if (kcCheckSmsOptionEnable() || kcCheckWhatsappOptionEnable()) {
        $sms = apply_filters('kcpro_send_sms', [
            'type' => 'zoom_link',
            'appointment_id' => $appointment_id,
        ]);

        $sms1 = apply_filters('kcpro_send_sms', [
            'type' => 'add_doctor_zoom_link',
            'appointment_id' => $appointment_id,
        ]);
    }
    return [
        'zoom_link' => $sms,
        'add_doctor_zoom_link' => $sms1
    ];
}

function kcSendAppointmentMeetSms($appointment_id)
{
    $sms = $sms1 = '';
    $appointment_id = (int) $appointment_id;
    if (kcCheckSmsOptionEnable() || kcCheckWhatsappOptionEnable()) {
        $sms = apply_filters('kcpro_send_sms', [
            'type' => 'meet_link',
            'appointment_id' => $appointment_id,
        ]);

        $sms1 = apply_filters('kcpro_send_sms', [
            'type' => 'add_doctor_meet_link',
            'appointment_id' => $appointment_id,
        ]);
    }
    return [
        'zoom_link' => $sms,
        'add_doctor_zoom_link' => $sms1
    ];
}

function kcTelemedSms($filterData)
{

    $status = false;
    if (isKiviCareTelemedActive()) {
        if (!empty($filterData) && !empty($filterData['appointment_id'])) {
            $status = kcCommonEmailFunction($filterData['appointment_id'], 'Telemed', 'zoom_doctor');
            $status2 = kcCommonEmailFunction($filterData['appointment_id'], 'Telemed', 'zoom_patient');
            $smsResponse = kcSendAppointmentZoomSms($filterData['appointment_id']);
            if ($status && $status2) {
                $status = true;
            } else {
                $status = false;
            }
        }
    }
    return [
        'status' => $status,
        'message' => esc_html__('Meetings has email send', 'kc-lang')
    ];
}

function kcGetDefaultClinicId()
{
    $option = get_option('setup_step_1');
    if ($option) {
        $option = json_decode($option);
        return (int) $option->id[0];
    } else {
        return 0;
    }
}

function kcGetDefaultClinic()
{
    global $wpdb;
    $clinic_table_name = $wpdb->prefix . 'kc_clinics';
    $clinic_id = kcGetDefaultClinicId();
    $clinic_query = "SELECT * FROM {$clinic_table_name}  WHERE `id` = {$clinic_id} ";
    return $wpdb->get_results($clinic_query, ARRAY_A);
}

/**
 * @since 2.3.0
 * @return array
 */

function kcClinicList()
{
    global $wpdb;
    $clinic_table = $wpdb->prefix . 'kc_clinics';
    $clinic_query = "SELECT * FROM {$clinic_table}";
    $clinic_list = $wpdb->get_results($clinic_query);
    if (!empty($clinic_list)) {
        $clinic_list = collect($clinic_list);
        return $clinic_list;
    } else {
        return [];
    }
}

function kcDoctorList()
{
    global $wpdb;
    $doctor_table = $wpdb->prefix . 'kc_doctor_clinic_mappings';
    $user_table = $wpdb->prefix . 'users';
    $doctor_query = "SELECT `doctor_id` FROM {$doctor_table}";
    $doctor_list = $wpdb->get_results($doctor_query);
    if (!empty($doctor_list)) {
        $doctor_ids = array_column($doctor_list, 'doctor_id');
        $doctor_ids_placeholder = implode(',', array_map('intval', $doctor_ids));
        $doctor_query = "SELECT * FROM {$user_table} WHERE ID IN ($doctor_ids_placeholder)";
        $doctor_details = $wpdb->get_results($doctor_query);
        return $doctor_details;
    } else {
        return [];
    }
}



/**
 * @param int $id
 * @since 2.3.0
 * @return object,bool
 *
 */

function kcClinicDetail($id)
{
    global $wpdb;
    if (!empty($id)) {
        $id = (int) $id;
        $clinic_table = $wpdb->prefix . 'kc_clinics';
        $clinic_query = "SELECT * FROM {$clinic_table} WHERE id = " . $id;
        $clinic_detail = $wpdb->get_row($clinic_query);
        if (!empty($clinic_detail)) {
            return $clinic_detail;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function kcGetClinicSessions($clinic_id)
{

    $clinic_id = (int) $clinic_id;
    $clinic_sessions = collect((new KCClinicSession())->get_by(['clinic_id' => $clinic_id]));
    $doctors = collect((new KCDoctorClinicMapping())->get_by(['clinic_id' => $clinic_id]))->map(function ($mapping) {
        $doctor = WP_User::get_data_by('ID', $mapping->doctor_id);
        $mapping->doctor_id = [
            'id' => (int) $doctor->ID,
            'label' => $doctor->display_name,
        ];

        $user_data = get_user_meta($doctor->ID, 'basic_data', true);
        $user_data = json_decode($user_data);

        $mapping->doctor_id['timeSlot'] = isset($user_data->time_slot) ? $user_data->time_slot : "";

        return $mapping;
    })->pluck('doctor_id')->toArray();

    $sessions = collect([]);
    if (count($clinic_sessions)) {
        foreach ($clinic_sessions as $session) {
            if ($session->parent_id === null || $session->parent_id === "") {
                $days = [];
                $session_doctors = [];
                $sec_start_time = "";
                $sec_end_time = "";

                $all_clinic_sessions = collect($clinic_sessions);

                $child_session = $all_clinic_sessions->where('parent_id', $session->id);

                //$child_session->all();

                if (count($child_session) > 0) {
                    foreach ($clinic_sessions as $child_session) {
                        if ($child_session->parent_id !== null && $session->id === $child_session->parent_id) {
                            array_push($days, substr($child_session->day, 0, 3));
                            array_push($session_doctors, $child_session->doctor_id);

                            if ($session->start_time !== $child_session->start_time || $session->start_time == $session->end_time) {
                                $sec_start_time = $child_session->start_time;
                                $sec_end_time = $child_session->end_time;
                            }
                        }
                    }
                } else {

                    array_push($session_doctors, $session->doctor_id);
                    array_push($days, substr($session->day, 0, 3));
                }


                if ($session->start_time == $session->end_time) {
                    $start_time = ['', ''];
                } else {

                    $start_time = explode(":", date('H:i', strtotime($session->start_time)));
                }

                if ($session->start_time == $session->end_time) {

                    $end_time = ['', ''];
                } else {

                    $end_time = explode(":", date('H:i', strtotime($session->end_time)));
                }


                $session_doctors = array_unique($session_doctors);

                if (count($session_doctors) === 0 && count($days) === 0) {
                    $session_doctors[] = $session->doctor_id;
                    $days[] = substr($session->day, 0, 3);
                } else {
                    $sec_start_time = $sec_start_time !== "" ? explode(":", date('H:i', strtotime($sec_start_time))) : "";
                    $sec_end_time = $sec_end_time !== "" ? explode(":", date('H:i', strtotime($sec_end_time))) : "";
                }

                $new_doctors = [];

                foreach ($session_doctors as $doctor_id) {
                    foreach ($doctors as $doctor) {
                        if ((int) $doctor['id'] === (int) $doctor_id) {
                            $new_doctors = $doctor;
                        }
                    }
                }

                $new_session = [
                    'id' => $session->id,
                    'clinic_id' => $session->clinic_id,
                    'doctor_id' => $session->doctor_id,
                    'days' => array_values(array_unique($days)),
                    'doctors' => $new_doctors,
                    'time_slot' => $session->time_slot,
                    's_one_start_time' => [
                        "HH" => $start_time[0],
                        "mm" => $start_time[1],
                    ],
                    's_one_end_time' => [
                        "HH" => $end_time[0],
                        "mm" => $end_time[1],
                    ],
                    's_two_start_time' => [
                        "HH" => isset($sec_start_time[0]) ? $sec_start_time[0] : "",
                        "mm" => isset($sec_start_time[1]) ? $sec_start_time[1] : "",
                    ],
                    's_two_end_time' => [
                        "HH" => isset($sec_end_time[0]) ? $sec_end_time[0] : "",
                        "mm" => isset($sec_end_time[1]) ? $sec_end_time[1] : "",
                    ]
                ];

                $sessions->push($new_session);
            }
        }
    }

    return $sessions;
}

function kcGetAllClinicHaveSession()
{

    global $wpdb;
    $status = false;
    $message = esc_html__('All Clinic Have Doctor Session', 'kc-lang');
    $doctor_session_table = $wpdb->prefix . 'kc_clinic_sessions';
    $clinic_table = $wpdb->prefix . 'kc_clinics';
    $msg2 = __('Clinic ', 'kc-lang');
    if (isKiviCareProActive()) {
        $clinic_id = collect($wpdb->get_results('select * from ' . $clinic_table))->pluck('id')->toArray();
        $clinic_session_id = collect($wpdb->get_results('select * from ' . $doctor_session_table))->unique('clinic_id')->pluck('clinic_id')->toArray();
        $result = array_diff($clinic_id, $clinic_session_id);
        if (!empty($result) && count($result) > 0) {
            $clinic_name = collect($wpdb->get_results('select name from ' . $clinic_table . ' where id in (' . implode(',', $result) . ')'))->pluck('name')->toArray();
            if (!empty($clinic_name) && count($clinic_name) > 0) {
                $status = true;
                $clinic_name1 = implode(',', $clinic_name);

                /* translators: clinic name*/
                $message = $msg2 . $clinic_name1 . __(' do not have a doctor session', 'kc-lang');
            }
        }
    } else {
        $clinic_session_id = $wpdb->get_var("select count(*) from {$doctor_session_table} where clinic_id=" . kcGetDefaultClinicId());
        if (!empty($clinic_session_id) && $clinic_session_id < 1) {
            $status = true;
            $data = $wpdb->get_var("select name from {$clinic_table} where id=" . kcGetDefaultClinicId());

            /* translators: doctor name*/
            $message = $msg2 . $data . __(' do not have a doctor session', 'kc-lang');
        }
    }
    return [
        'status' => $status,
        'message' => $message,
    ];
}

function kcGetClinicCurrenyPrefixAndPostfix()
{
    global $wpdb;
    $clinic_prefix = '';
    $clinic_postfix = '';
    $prefix_postfix = $wpdb->get_var("SELECT extra FROM {$wpdb->prefix}kc_clinics");
    if (!empty($prefix_postfix)) {
        $prefix_postfix = json_decode($prefix_postfix);
        $clinic_prefix = (!empty($prefix_postfix->currency_prefix) && $prefix_postfix->currency_prefix !== 'null') ? $prefix_postfix->currency_prefix : '';
        $clinic_postfix = (!empty($prefix_postfix->currency_postfix) && $prefix_postfix->currency_postfix !== 'null') ? $prefix_postfix->currency_postfix : '';
    }
    return [
        'prefix' => $clinic_prefix,
        'postfix' => $clinic_postfix
    ];
}

function kcGetClinicIdOfClinicAdmin()
{
    $current_user_id = get_current_user_id();
    global $wpdb;
    $clinic_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}kc_clinics WHERE clinic_admin_id={$current_user_id}");
    if (isKiviCareProActive()) {
        return !empty($clinic_id) ? (int) $clinic_id : 0;
    } else {
        return kcGetDefaultClinicId();
    }
}

function kcGetClinicIdOfPatient()
{
    $current_user_id = get_current_user_id();
    global $wpdb;
    $clinic_id = $wpdb->get_var("SELECT clinic_id FROM {$wpdb->prefix}kc_patient_clinic_mappings WHERE patient_id={$current_user_id}");
    if (isKiviCareProActive()) {
        return !empty($clinic_id) ? (int) $clinic_id : 0;
    } else {
        return kcGetDefaultClinicId();
    }
}

function kcGetClinicIdOfReceptionist()
{
    $current_user_id = get_current_user_id();
    global $wpdb;
    $clinic_id = $wpdb->get_var("SELECT clinic_id FROM {$wpdb->prefix}kc_receptionist_clinic_mappings WHERE receptionist_id={$current_user_id}");
    if (isKiviCareProActive()) {
        return !empty($clinic_id) ? (int) $clinic_id : 0;
    } else {
        return kcGetDefaultClinicId();
    }
}
function kcGetClinicIdOfDoctor($current_user_id = null)
{
    if (is_null($current_user_id)) {
        $current_user_id = get_current_user_id();
    }

    global $wpdb;
    $clinic_id = $wpdb->get_col("SELECT clinic_id FROM {$wpdb->prefix}kc_doctor_clinic_mappings WHERE doctor_id={$current_user_id}");
    if (isKiviCareProActive()) {
        return !empty($clinic_id) ? $clinic_id : [];
    } else {
        return kcGetDefaultClinicId();
    }
}

function kcDoctorPatientList($doctor_id = '')
{
    global  $wpdb;
    $doctor_id = !empty($doctor_id) ? (int)$doctor_id : get_current_user_id();
    $appointments = collect((new KCAppointment)->get_by(['doctor_id' => $doctor_id]))->pluck('patient_id')->unique()->toArray();
    $get_doctor_patient = collect($wpdb->get_results("SELECT *  FROM {$wpdb->base_prefix}usermeta WHERE `meta_value` = {$doctor_id} AND
                        `meta_key` LIKE 'patient_added_by'"))->pluck('user_id')->unique()->toArray();
    $all_user = array_merge($get_doctor_patient, $appointments);
    $encounters = collect((new KCPatientEncounter())->get_by(['doctor_id' => $doctor_id]))->pluck('patient_id')->unique()->toArray();
    $all_user = array_unique(array_merge($all_user, $encounters));
    $filtered_user_ids = verify_and_filter_user_ids($all_user);
    
    return $filtered_user_ids;
}

function verify_and_filter_user_ids($user_ids) {
    global $wpdb;

    $user_ids_str = implode(',', array_map('intval', $user_ids));

    $query_users = "SELECT ID FROM {$wpdb->users} WHERE ID IN ($user_ids_str)";
    $existing_user_ids = $wpdb->get_col($query_users);

    $filtered_user_ids = array_intersect($user_ids, $existing_user_ids);

    if (empty($filtered_user_ids)) {
        return array();
    }

    $filtered_user_ids_str = implode(',', array_map('intval', $filtered_user_ids));

    $query_mappings = "SELECT patient_id FROM {$wpdb->prefix}kc_patient_clinic_mappings WHERE patient_id IN ($filtered_user_ids_str)";
    $existing_patient_ids = $wpdb->get_col($query_mappings);

    $final_filtered_user_ids = array_intersect($filtered_user_ids, $existing_patient_ids);

    return $final_filtered_user_ids;
}

function kcClinicPatientList($clinic_id = '')
{
    $patient = (new KCPatientClinicMapping)->get_var(['clinic_id' => $clinic_id], 'patient_id', false);
    return $patient;
}

function kcGetUserValueByKey($userType, $user_id, $key)
{
    $data = '';
    switch ($userType) {
        case 'doctor':
        case 'patient':
        case "clinic_admin":
        case 'receptionist':
        case 'common':
            $doctor_detail = !empty($user_id) ? json_decode(get_user_meta($user_id, 'basic_data', true)) : '';
            $data = !empty($doctor_detail->{$key}) ? $doctor_detail->{$key} : '';
            break;
    }

    return $data;
}

function generatePatientUniqueIdRegister()
{
    global $wpdb;
    $patient_unique_id = kcPatientUniqueIdEnable('value');
    $patient_unique_id_exist = $wpdb->get_results("SELECT  meta_value FROM  " . $wpdb->base_prefix . "usermeta WHERE  meta_key = 'patient_unique_id'  AND  meta_value ='" . $patient_unique_id . "' LIMIT 1");
    if ($patient_unique_id_exist === NULL) {
        generatePatientUniqueIdRegister();
    } else {
        return $patient_unique_id;
    }
}

function kcGetServiceCharges($service)
{
    global $wpdb;
    $service_doctor_mapping_table = $wpdb->prefix . 'kc_service_doctor_mapping';
    $service_id = (int) $service['service_id'];
    $doctor_id = (int) $service['doctor_id'];
    $clinic_id = (int) $service['clinic_id'];
    $service_query = "SELECT * FROM  {$service_doctor_mapping_table}  WHERE service_id = {$service_id}  AND doctor_id = {$doctor_id} AND clinic_id={$clinic_id} ";
    $service_charges = $wpdb->get_row($service_query, 'OBJECT');
    return !empty($service_charges) ? $service_charges : [];
}

function kcGetServiceById($service_id)
{
    global $wpdb;
    $service_id = (int) $service_id;
    $service = $wpdb->prefix . 'kc_services';
    $service_query = "SELECT * FROM  {$service} WHERE id = {$service_id} ";
    $service = $wpdb->get_results($service_query, 'OBJECT');
    if (count($service)) {
        return $service[0];
    }
    return [];
}

function getServiceId($data)
{

    global $wpdb;
    $service = $wpdb->prefix . 'kc_services';
    if ($data['type'] == 'Telemed' || $data['type'] == 'telemed') {
        $condition = " AND type = 'system_service' AND name = '{$data['type']}' ";
    } else {
        $condition = " AND name = '{$data['type']}' ";
    }

    $service_query = "SELECT * FROM {$service} WHERE 0 = 0 " . $condition;
    $service_id = $wpdb->get_results($service_query, 'OBJECT');
    if ($service_id) {
        return $service_id;
    } else {
        $data->id = 0;
    }
}

function getDoctorTelemedServiceCharges($doctor_id)
{
    global $wpdb;
    $doctor_id = (int) $doctor_id;
    $table_name = $wpdb->prefix . 'kc_service_doctor_mapping';
    $telemed_service_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}kc_services WHERE name='Telemed' OR name='telemed'");
    if ($telemed_service_id != '') {
        $query = "SELECT charges FROM  {$table_name} charges WHERE doctor_id = {$doctor_id} AND service_id={$telemed_service_id} ";
        $result = $wpdb->get_var($query);
        if ($result != '') {
            return $result;
        } else {
            return '0';
        }
    } else {
        return '0';
    }
}

function kcCheckDoctorTelemedType($appointment_id)
{
    global $wpdb;
    $appointment_id = (int) $appointment_id;
    $doctor_telemed_type = '';
    if (isKiviCareTelemedActive() && isKiviCareGoogleMeetActive()) {
        $doctor_id = $wpdb->get_var("SELECT doctor_id FROM {$wpdb->prefix}kc_appointments WHERE id={$appointment_id}");
        $doctor_telemed_type = get_user_meta($doctor_id, 'telemed_type', true);
    } elseif (isKiviCareGoogleMeetActive()) {
        $doctor_telemed_type = 'googlemeet';
    } elseif (isKiviCareTelemedActive()) {
        $doctor_telemed_type = 'zoom';
    }
    return $doctor_telemed_type == 'googlemeet' ? 'googlemeet' : 'zoom';
}

function kcDoctorTelemedServiceEnable($doctor_id)
{
    $doctor_telemed_enable = false;
    if (isKiviCareTelemedActive()) {
        $zoom_config_data = get_user_meta($doctor_id, 'zoom_config_data', true);
        $zoom_server_to_server_oauth_config_data = get_user_meta( (int)$doctor_id, 'zoom_server_to_server_oauth_config_data', true );

        // Check For zoom JWT On
        if (!empty($zoom_config_data)) {
            $zoom_config_data = json_decode($zoom_config_data);
            $doctor_telemed_enable = !empty($zoom_config_data->enableTeleMed) && strval($zoom_config_data->enableTeleMed) == 'true';
        }
        // Check For Zoom Oauth
        if ($doctor_telemed_enable == false) {
            $doctor_telemed_enable = get_user_meta($doctor_id, KIVI_CARE_PREFIX . 'zoom_telemed_connect', true) == "on";
        }

		if ( !empty($zoom_server_to_server_oauth_config_data) ) {
            $zoom_server_to_server_oauth_config_data = json_decode( $zoom_server_to_server_oauth_config_data );
           
			$doctor_telemed_enable = isset( $zoom_server_to_server_oauth_config_data->enableServerToServerOauthconfig ) && ($zoom_server_to_server_oauth_config_data->enableServerToServerOauthconfig == "true");
        }
    }

    //check doctor googlemeet enable
    if (!$doctor_telemed_enable && isKiviCareGoogleMeetActive()) {
        $googleMeet = get_option(KIVI_CARE_PREFIX . 'google_meet_setting', true);
        if (gettype($googleMeet !== 'boolean') && !empty($googleMeet['enableCal']) && in_array((string) $googleMeet['enableCal'], ['1', 'true', 'Yes'])) {
            $doctor_telemed_enable = get_user_meta($doctor_id, KIVI_CARE_PREFIX . 'google_meet_connect', true) == 'on';
        }
    }

    return $doctor_telemed_enable;
}

function kcServiceListFromRequestData($request_data)
{
    $serviceList = [];
    $serviceName = '';
    if (is_array($request_data['visit_type'])) {
        foreach ($request_data['visit_type'] as $key => $value) {
            $serviceList[] = sanitize_text_field($value['name']);
        }
    }
    if (is_array($serviceList) && count($serviceList) > 0) {
        $serviceName = implode(",", $serviceList);
    }
    return $serviceName;
}

function kcGetTelemedServiceId()
{

    // Add telemed service if not in service list
    $telemed_service_id = getServiceId(['type' => 'Telemed']);

    if (isset($telemed_service_id[0])) {

        $telemed_Service = $telemed_service_id[0]->id;
    } else {

        $service_data = new KCService;
        $services = [
            'type' => 'system_service',
            'name' => 'telemed',
            'price' => 0,
            'status' => 1,
            'created_at' => current_time('Y-m-d H:i:s')
        ];
        $telemed_Service = $service_data->insert($services);
    }

    return (int) $telemed_Service;
}

function kivicareGetProductIdOfService($id)
{
    $id = (int) $id;
    global $wpdb;
    $product_id = '';
    $appointments_service_table = $wpdb->prefix . 'kc_service_doctor_mapping';
    $data = $wpdb->get_var("SELECT extra FROM  {$appointments_service_table} WHERE id ={$id}");
    if (!empty($data)) {
        $data = json_decode($data);
        if (!empty($data->product_id)) {
            $product_id = $data->product_id;
        }
    }
    return $product_id;
}

/**
 * // Data param required date, clinic_id, doctor_id
 *
 * @param $data
 *
 * @param string $new_time_slot
 * @param $only_available_slots
 * @return array
 */

function kvGetTimeSlots($data, $new_time_slot = "", $only_available_slots = false, $from_mobile = false)
{
    global $wpdb;
    $slots = [];

    $clinic_session_table = $wpdb->prefix . 'kc_clinic_sessions';

    if (!isset($data['date']) || !isset($data['doctor_id']) || !isset($data['clinic_id'])) {
        return $slots;
    }
    $data['doctor_id'] = (int) $data['doctor_id'];

    $service_duration = [];

    if($from_mobile === false){
        if (!empty($data['service']) && isKiviCareProActive()) {
            if (is_array($data['service'])) {
                if(!empty($data['widgetType']) && $data['widgetType'] === 'phpWidget'){
                    $service_id = array_map(function ($v) {
                        return (int) $v['service_id'];
                    }, $data['service']);
                }else{
                    $service_id = array_map(function ($v) {
                        $v = json_decode(stripslashes($v));
                        return (int) $v->service_id;
                    }, $data['service']);
                }
            } else {
                $service_id = [json_decode(stripslashes($data['service']))->service_id];
            }

            if (!empty($service_id)) {
                if(is_array($service_id)){
                    foreach ($service_id as $id) {
                        $duration = $wpdb->get_var("SELECT duration FROM {$wpdb->prefix}kc_service_doctor_mapping WHERE service_id={$id} AND doctor_id={$data['doctor_id']} AND clinic_id ={$data['clinic_id']}");
                        $service_duration[] = !empty($duration) ? $duration : false;
                    }
                }
            }
        }
    }else{
        $service_id = json_decode($data['service_id'],true); 
        if (!empty($service_id)) {
            if(is_array($service_id)){
                foreach ($service_id as $id) {
                    $duration = $wpdb->get_var("SELECT duration FROM {$wpdb->prefix}kc_service_doctor_mapping WHERE service_id={$id} AND doctor_id={$data['doctor_id']} AND clinic_id ={$data['clinic_id']}");
                    $service_duration[] = !empty($duration) ? $duration : false;
                }
            }
        }
    }
    $service_duration_sum = array_sum($service_duration);

    $appointment_day = strtolower(date('l', strtotime($data['date'])));

    $day_short = substr($appointment_day, 0, 3);

    $query = "SELECT * FROM {$clinic_session_table}  WHERE `doctor_id` = " . (int) $data['doctor_id'] . " AND `clinic_id` = " . (int) $data['clinic_id'] . "  AND ( `day` = '{$day_short}' OR `day` = '{$appointment_day}') ";
    $clinic_session = collect($wpdb->get_results($query, OBJECT))->sortBy('start_time');

    if (count($clinic_session)) {

        $slot_date = $data['date'];

        $appointment_conditions = ' ';
        if (!empty($data['appointment_id'])) {
            $data['appointment_id'] = (int) $data['appointment_id'];
            $appointment_conditions = " AND id !={$data['appointment_id']} ";
        }

        $appointments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}kc_appointments WHERE appointment_start_date = '" . date("Y-m-d", strtotime($data["date"])) . "' AND status != 0 {$appointment_conditions} AND doctor_id = {$data['doctor_id']} AND `clinic_id` = " . (int) $data['clinic_id'] . " ORDER BY appointment_start_time");
        $results = collect($wpdb->get_results("SELECT * FROM {$wpdb->prefix}kc_clinic_schedule WHERE `start_date` <= '$slot_date' AND `end_date` >= '$slot_date'  AND `status` = 1", OBJECT))->sortBy('start_time');

        $leaves = $results->filter(function ($result) use ($data) {

            if ($result->module_type === "clinic") {
                if ((int) $result->module_id === (int) $data['clinic_id']) {
                    return true;
                }
            } elseif ($result->module_type === "doctor") {
                if ((int) $result->module_id === (int) $data['doctor_id']) {
                    return true;
                }
            } else {
                return false;
            }

            return false;
        });

        if (count($leaves)) {
            return $slots;
        }
        $clinic_session_details = [];
        foreach ($clinic_session as $key => $value) {
            foreach ($service_duration as $index => $duration) {
                if($duration == false){
                    $clinic_session_details[] = [
                        'start_time' => $value->start_time,
                        'end_time' => $value->end_time,
                        'time_slot' => (int)$value->time_slot + (int)$service_duration_sum,
                    ];
                }
            }
        }



        foreach ($clinic_session as $key => $session) {

            $newTimeSlot = "";
            $time_slot = $session->time_slot;
            $session_start_time = DateTime::createFromFormat('h:i a', date('h:i A', strtotime($session->start_time)));
            $session_end_time = DateTime::createFromFormat('h:i a', date('h:i A', strtotime($session->end_time)));

            $chuck_session = [
                ['start' => $session->start_time, 'end' => $session->end_time]
            ];

            //create  chunk doctor session
            foreach ($appointments as $time) {
                //if session time is 45 min then minus those mins from start time

                $current_time_slot_start = DateTime::createFromFormat('h:i a', date('h:i A', strtotime($time->appointment_start_time)));
                $current_time_slot_end = DateTime::createFromFormat('h:i a', date('h:i A', strtotime($time->appointment_end_time)));

                //check if chunk appointment start and end time between actual doctor session start/end time , if between create new chuck session
                if (
                    $current_time_slot_start >= $session_start_time && $current_time_slot_start <= $session_end_time &&
                    $current_time_slot_end >= $session_start_time && $current_time_slot_end <= $session_end_time
                ) {
                    if ($current_time_slot_start == $session_start_time) {
                        $chuck_session = [
                            ['start' => $time->appointment_end_time, 'end' => $chuck_session[0]['end']]
                        ];
                    } else {
                        $last_element = array_key_last($chuck_session);
                        $last = $chuck_session[$last_element]['end'];
                        // $time->appointment_start_time = date("H:i:s", $newTimestamp);
                        $chuck_session[$last_element] = [
                            'start' => $chuck_session[$last_element]['start'],
                            'end' => $time->appointment_start_time
                        ];
                        $chuck_session[] = ['start' => $time->appointment_end_time, 'end' => $last];
                    }
                }
            }

            foreach ($chuck_session as $chunk) {

                //check if chunk session start and end time between actual doctor session start/end time , if not between skip loop iteration
                $start_time_formated = DateTime::createFromFormat('h:i a', date('h:i A', strtotime($chunk['start'])));
                $end_time_formated = DateTime::createFromFormat('h:i a', date('h:i A', strtotime($chunk['end'])));
                if (
                    !($start_time_formated >= $session_start_time && $start_time_formated <= $session_end_time
                        && $end_time_formated >= $session_start_time && $end_time_formated <= $session_end_time)
                ) {
                    continue;
                }

                $start_time = new DateTime($chunk['start']);
                $time_diff = $start_time->diff(new DateTime($chunk['end']));
                foreach ($clinic_session_details as  $details) {
                    if($chunk['start'] == $details['start_time']){
                        $service_duration_sum = $details['time_slot'];
                    }
                }

                
                if (!empty($service_duration_sum)) {
                    $time_slot = $service_duration_sum;
                }
                if ($time_diff->h !== 0) {
                    $time_diff_min = floor(($time_diff->h * 60) / $time_slot);
                    if ($time_diff->i !== 0) {
                        $time_diff_min = $time_diff_min + floor(($time_diff->i / $time_slot));
                    }
                } else {
                    $time_diff_min = floor($time_diff->i / $time_slot);
                }

                for ($i = 0; $i <= $time_diff_min; $i++) {

                    if ($i === 0) {
                        $newTimeSlot = date('H:i', strtotime($chunk['start']));
                    } else {
                        $newTimeSlot = date('H:i', strtotime('+' . $time_slot . ' minutes', strtotime($newTimeSlot)));
                    }

                    if (strtotime('+' . $time_slot . ' minutes', strtotime($newTimeSlot)) > strtotime($chunk['end'])) {
                        if (!empty($service_duration_sum)) {
                            if (strtotime('+' . $service_duration_sum . ' minutes', strtotime($newTimeSlot)) > strtotime($chunk['end'])) {
                                continue;
                            }
                        } 
                        else {
                            continue;
                        }
                    }
                    
                    if (!empty($service_duration_sum)) {
                        if (strtotime('+' . $service_duration_sum . ' minutes', strtotime($newTimeSlot)) > strtotime($chunk['end'])) {
                            continue;
                        }
                    }

                    
                    if (strtotime($newTimeSlot) < strtotime($chunk['end'])) {
                        $temp = [
                            // 'time' => date('h:i A', strtotime($newTimeSlot)),
                            'time' => kcGetFormatedTime(date('h:i A', strtotime($newTimeSlot))),
                            'available' => true
                        ];

                        $currentDateTime = current_time('Y-m-d H:i:s');
                        $newDateTime = date('Y-m-d', strtotime($data['date'])) . ' ' . $newTimeSlot . ':00';

                        if (strtotime($newDateTime) < strtotime($currentDateTime)) {
                            (bool) $temp['available'] = false;
                        }

                        // following condition is for get only available slots
                        if ($only_available_slots !== false) {
                            if ($temp['available'] !== false) {
                                $slots[$key][] = $temp;
                            }
                        } else {
                            $slots[$key][] = $temp;
                        }
                    }
                }
            }
        }
    }
    return array_values($slots);
}

function kcCancelAppointments($data)
{

    $start_date = $data['start_date'];
    $end_date = $data['end_date'];
    global $wpdb;

    $app_table_name = $wpdb->prefix . 'kc_appointments';

    $appointment_condition = " `appointment_start_date` >= '$start_date' AND `appointment_start_date` <= '$end_date' ";

    $query = "UPDATE {$app_table_name} SET `status` = 0  WHERE  {$appointment_condition} AND `status` = 1 ";

    $select_recepients_query = "SELECT CONCAT(\"'\", GROUP_CONCAT(DISTINCT patient_id SEPARATOR \",'\" ), \"'\") AS patient_id FROM {$app_table_name} WHERE {$appointment_condition}";

    $data_condition = '';

    if (isset($data['doctor_id'])) {
        $data['doctor_id'] = (int) $data['doctor_id'];
        $data_condition = " AND doctor_id={$data['doctor_id']}";
        $query = $query . " AND doctor_id = " . $data['doctor_id'];
        $select_recepients_query = $select_recepients_query . " AND doctor_id = " . $data['doctor_id'];
    }

    if (isset($data['clinic_id'])) {
        $data['clinic_id'] = (int) $data['clinic_id'];
        $data_condition = " AND clinic_id={$data['clinic_id']}";
        $query = $query . " AND clinic_id = " . $data['clinic_id'];
        $select_recepients_query = $select_recepients_query . " AND clinic_id = " . $data['clinic_id'];
    }


    //send email to all cancel appointment
    $data_query = "select * from {$app_table_name} where {$appointment_condition} AND status = 1 {$data_condition}";
    $appointment_data = $wpdb->get_results($data_query);
    if ($appointment_data != null) {
        foreach ($appointment_data as $res) {
            $email_data = kcCommonNotificationData($res, [], '', 'cancel_appointment');
            //send cancel email
            kcSendEmail($email_data);
            if (kcCheckSmsOptionEnable() || kcCheckWhatsappOptionEnable()) {
                apply_filters('kcpro_send_sms', [
                    'type' => 'cancel_appointment',
                    'appointment_id' => $res->id,
                ]);
            }
            //cancel zoom meeting
            if (isKiviCareTelemedActive()) {
                apply_filters('kct_delete_appointment_meeting', ['id' => $res->id]);
            }
            //remove google calendar event
            if (kcCheckGoogleCalendarEnable()) {
                apply_filters('kcpro_remove_appointment_event', ['appoinment_id' => $res->id]);
            }

            //remove google meet event
            if (isKiviCareGoogleMeetActive()) {
                apply_filters('kcgm_remove_appointment_event', ['appoinment_id' => $res->id]);
            }
        }
    }

    $receptionist = $wpdb->query($select_recepients_query);

    $wpdb->query($query);
}


function kcAppointmentRestrictionData()
{
    $data = get_option(KIVI_CARE_PREFIX . 'restrict_appointment', true);
    $only_same_day_book = get_option(KIVI_CARE_PREFIX . 'restrict_only_same_day_book_appointment', true);

    if (gettype($data) != 'boolean') {
        $temp = [
            'pre_book' => isset($data['pre']) && !empty($data['pre']) ? $data['pre'] : 0,
            'post_book' => isset($data['post']) && !empty($data['post']) ? $data['post'] : 365,
        ];
    } else {
        $temp = [
            'pre_book' => 0,
            'post_book' => 365,
        ];
    }

    if (gettype($only_same_day_book) != 'boolean') {
        $temp['only_same_day_book'] = isset($only_same_day_book) && !empty($only_same_day_book) ? $only_same_day_book : 'off';
    } else {
        $temp['only_same_day_book'] = 'off';
    }

    return $temp;
}

//used in old telemed plugin version
function kcAppointmentZoomDoctorEmail($appointmentid)
{
    return kcCommonEmailFunction($appointmentid, 'Telemed', 'zoom_doctor');
}
//used in old telemed plugin version
function kcAppointmentZoomPatientEmail($appointmentid)
{
    return kcCommonEmailFunction($appointmentid, 'Telemed', 'zoom_patient');
}

function kcCommonEmailFunction($appointmentid, $service_name, $type)
{
    global $wpdb;
    $service_name = !empty($service_name) ? $service_name : '';
    $appointmentid = (int) $appointmentid;
    if (!empty($appointmentid)) {
        $appointment_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}kc_appointments WHERE id={$appointmentid}");
        $zoom_data = [];
        if (isKiviCareTelemedActive()) {
            $zoom_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}kc_appointment_zoom_mappings WHERE appointment_id={$appointmentid}");
        }
        if (isKiviCareGoogleMeetActive() && kcCheckDoctorTelemedType($appointmentid) == 'googlemeet') {
            $googlemeet_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}kc_appointment_google_meet_mappings WHERE appointment_id=" . $appointmentid);
            if ($googlemeet_data != '') {
                $zoom_data = new stdClass();
                $zoom_data->join_url = $googlemeet_data->url;
                $zoom_data->start_url = $googlemeet_data->url;
                $zoom_data->event_url = $googlemeet_data->event_url;
                $zoom_data->url = $googlemeet_data->url;
            }
        }
        if (!empty($appointment_data)) {

            $commonData = kcCommonNotificationData($appointment_data, $zoom_data, $service_name, $type);

            return kcSendEmail($commonData);
        }
        return false;
    }
    return false;
}

function kcAppointmentCancelMail($appointmentData)
{

    $Status = $appointmentData->status;
    $returnValue = false;
    $date = date($appointmentData->appointment_start_date);
    $time = $appointmentData->appointment_start_time;
    $appointment_time = ("$date" . " " . "$time");
    $current_date = current_time("Y-m-d H:i:s");
    if ($Status != 0) {
        if ($current_date < $appointment_time) {
            $returnValue = kcCommonEmailFunction($appointmentData->id, 'kivicare', 'cancel_appointment');
            if (kcCheckSmsOptionEnable() || kcCheckWhatsappOptionEnable()) {
                $sms_status = apply_filters('kcpro_send_sms', [
                    'type' => 'cancel_appointment',
                    'appointment_id' => $appointmentData->id,
                ]);
            }
        }
    }

    return $returnValue;
}

function kivicareCommonSendEmailIfOnlyLitePluginActive($appointment_id, $service)
{
    $patient_email_status = kcCommonEmailFunction($appointment_id, $service, 'patient');
    $clinic_email_status = kcCommonEmailFunction($appointment_id, $service, 'clinic');
    $doctor_email_status = kcCommonEmailFunction($appointment_id, $service, 'doctor');

    return [
        'patient_email_status' => $patient_email_status,
        'doctor_email_status' => $doctor_email_status,
        'clinic_email_status' => $clinic_email_status
    ];
}

function kcProAllNotification($appointment_id, $service, $telemed_service_include)
{
    $email_notification = kivicareCommonSendEmailIfOnlyLitePluginActive($appointment_id, $service);
    $smsResponse = kcSendAppointmentSmsOnPro($appointment_id, $service);
    $google_event_status = $telemed = false;
    if (kcCheckGoogleCalendarEnable()) {
        $google_event_status = apply_filters('kcpro_save_appointment_event', [
            'appoinment_id' => $appointment_id,
        ]);
    }
    if ($telemed_service_include) {
        if (kcCheckDoctorTelemedType($appointment_id) == 'googlemeet') {
            $telemed = apply_filters('kcgm_save_appointment_event_link_send', ['appoinment_id' => $appointment_id]);
        } else {
            $telemed = apply_filters('kct_send_zoom_link', ['appointment_id' => $appointment_id]);
            ;
        }
    }
    $other_notification = [
        'smsResponse' => $smsResponse,
        'google_event_status' => $google_event_status,
        'telemed' => $telemed
    ];

    return array_merge($other_notification, $email_notification);
}

function kcCommonNotificationData($appointment_data, $zoom_data, $service_name, $type)
{
    global $wpdb;
    $clinic_data = $wpdb->get_row("SELECT *, CONCAT(address, ', ',city,', ' ,postal_code,', ',country) AS clinic_full_address FROM {$wpdb->prefix}kc_clinics WHERE ID={$appointment_data->clinic_id}");
    $patient_id = $appointment_data->patient_id;
    $patient_details = get_user_by('ID', $patient_id);
    $doctor_id = $appointment_data->doctor_id;
    $doctor_details = get_user_by('ID', $doctor_id);

    $commonData = [
        'appointment_date' => kcGetFormatedDate($appointment_data->appointment_start_date),
        'appointment_time' => $appointment_data->appointment_start_time,
        'service_name' => $service_name,
        'current_date' => current_time('Y-m-d'),
        'patient_name' => $patient_details->display_name,
        'patient_email' => $patient_details->user_email,
        'doctor_name' => $doctor_details->display_name,
        'zoom_link' => isset($zoom_data->join_url) ? $zoom_data->join_url : '',
        'add_doctor_zoom_link' => isset($zoom_data->start_url) ? $zoom_data->start_url : '',
        'clinic_name' => isset($clinic_data->name) ? $clinic_data->name : '',
        'meet_link' => isset($zoom_data->url) ? $zoom_data->url : '',
        'meet_event_link' => isset($zoom_data->event_url) ? $zoom_data->event_url : '',
        'clinic_email' => isset($clinic_data->email) ? $clinic_data->email : '',
        'clinic_contact_number' => isset($clinic_data->telephone_no) ? $clinic_data->telephone_no : '',
        'doctor_email' => isset($doctor_details->user_email) ? $doctor_details->user_email : '',
        'doctor_contact_number' => kcGetUserValueByKey('doctor', $doctor_id, 'mobile_number'),
        'clinic_address' => !empty($clinic_data->clinic_full_address) ? $clinic_data->clinic_full_address : '',
        'patient_contact_number' => kcGetUserValueByKey('patient', $patient_id, 'mobile_number'),
        'description' => $appointment_data->description
    ];

    switch ($type) {
        case 'doctor':
            $commonData['user_email'] = $doctor_details->user_email;
            $commonData['email_template_type'] = 'doctor_book_appointment';
            break;
        case 'patient':
            $commonData['user_email'] = $patient_details->user_email;
            $commonData['email_template_type'] = 'book_appointment';
            break;
        case 'zoom_patient':
            if ($zoom_data != null) {
                $commonData['user_email'] = $patient_details->user_email;
                $commonData['email_template_type'] = 'zoom_link';
            }
            break;
        case 'zoom_doctor':
            if ($zoom_data != null) {
                $commonData['user_email'] = $doctor_details->user_email;
                $commonData['email_template_type'] = 'add_doctor_zoom_link';
            }
            break;
        case 'cancel_appointment':
            $commonData['user_email'] = $patient_details->user_email;
            $commonData['email_template_type'] = 'cancel_appointment';
            break;
        case 'clinic':
            $commonData['user_email'] = isset($clinic_data->email) ? $clinic_data->email : 'demo@gmail.com';
            $commonData['email_template_type'] = 'clinic_book_appointment';
            break;
        case 'appointment_reminder':
            $commonData['id'] = $patient_id;
            $commonData['user_email'] = $patient_details->user_email;
            $commonData['email_template_type'] = 'book_appointment_reminder';
            break;
        case 'appointment_reminder_for_doc':
            $commonData['id'] = $doctor_id;
            $commonData['user_email'] = $doctor_details->user_email;
            $commonData['email_template_type'] = 'book_appointment_reminder_for_doctor';
            break;
        case 'meet_doctor':
            /**
             * zoom condition
             */
            if ($zoom_data != null) {
                $commonData['user_email'] = $doctor_details->user_email;
                $commonData['email_template_type'] = 'add_doctor_meet_link';
            }
            break;
        case 'meet_patient':
            if ($zoom_data != null) {
                $commonData['user_email'] = $patient_details->user_email;
                $commonData['email_template_type'] = 'meet_link';
            }
            break;
        case 'patient_clinic_check_in_check_out':
                $commonData['user_email'] = $patient_details->user_email;
                $commonData['email_template_type'] = 'patient_clinic_check_in_check_out';
                break;
    }

    return $commonData;
}

function kcCommonNotificationUserData($id, $password)
{
    $kcbase = new KCBase();
    $data = [];
    $result = get_userdata($id);
    $data['id'] = $id;
    $data['username'] = $result->user_login;
    $data['user_email'] = $result->user_email;
    $data['password'] = $password;
    if (in_array($kcbase->getDoctorRole(), $result->roles)) {

        $data['doctor_name'] = $result->display_name;
        $data['email_template_type'] = 'doctor_registration';
    } elseif (in_array($kcbase->getPatientRole(), $result->roles)) {

        $data['patient_name'] = $result->display_name;
        $data['email_template_type'] = 'patient_register';
    } elseif (in_array($kcbase->getClinicAdminRole(), $result->roles)) {

        $data['clinic_admin_name'] = $result->display_name;
        $data['email_template_type'] = 'clinic_admin_registration';
    } elseif (in_array($kcbase->getReceptionistRole(), $result->roles)) {

        $data['Receptionist_name'] = $result->display_name;
        $data['email_template_type'] = 'receptionist_register';
    }

    return $data;
}

function kcCheckSmsOptionEnable()
{
    $status = false;
    $get_sms_config = get_option('sms_config_data', true);
    if (!empty($get_sms_config)) {
        $get_sms_config = json_decode($get_sms_config);
        if (
            isKiviCareProActive() && !empty($get_sms_config->enableSMS)
            && in_array((string) $get_sms_config->enableSMS, ['1', 'true'])
        ) {
            $status = true;
        }
    }
    return $status || kcCustomNotificationEnable('sms');
}

function kcCheckWhatsappOptionEnable()
{
    $status = false;
    $get_sms_config = get_option('whatsapp_config_data', true);
    if (!empty($get_sms_config)) {
        $get_sms_config = json_decode($get_sms_config);
        if (
            isKiviCareProActive() && getKiviCareProVersion() >= '1.2.0' &&
            !empty($get_sms_config->enableWhatsApp) &&
            in_array((string) $get_sms_config->enableWhatsApp, ['1', 'true'])
        ) {
            $status = true;
        }
    }
    return $status || kcCustomNotificationEnable('whatsapp');
}

function kcCustomNotificationEnable($type)
{
    $response = apply_filters('kcpro_custom_notification_setting_get', [
        'enableSMS' => 'no',
        'enableWhatsapp' => 'no',
    ]);
    if ($type === 'sms') {
        return !empty($response['enableSMS']) && $response['enableSMS'] === 'yes';
    } else {
        return !empty($response['enableWhatsapp']) && $response['enableWhatsapp'] === 'yes';
    }
}

function kcCheckGoogleCalendarEnable()
{
    $status = false;
    $get_googlecal_data = get_option(KIVI_CARE_PREFIX . 'google_cal_setting', true);
    if (isKiviCareProActive() && gettype($get_googlecal_data) != 'boolean') {
        $status = in_array((string) $get_googlecal_data['enableCal'], ['1', 'true']);
    }
    return $status;
}

function kcAppointmentMultiFileUploadEnable()
{
    $data = get_option(KIVI_CARE_PREFIX . 'multifile_appointment', true);
    if (gettype($data) != 'boolean' && $data === 'on') {
        return true;
    } else {
        return false;
    }
}


function kcGetiUnderstand()
{
    $status = get_option(KIVI_CARE_PREFIX . 'i_understnad_loco_translate');
    return !(in_array((string) $status, ['1', 'true']));
}

function kcGetTimeZoneOption()
{

    $wp_timezone_string = wp_timezone_string();

    /* translators: timezone */
    $message = __('Current Timezone: ', 'kc-lang') . esc_html($wp_timezone_string) . __('Your appointment slots work based on your current time zone.', 'kc-lang');
    $status = get_option(KIVI_CARE_PREFIX . 'timezone_understand', true);
    return [
        'status' => true,
        'data' => in_array((string) $status, ['1', 'true']),
        'message' => $message,
    ];
}

function kcPluginLoader()
{
    $get_site_logo = get_option(KIVI_CARE_PREFIX . 'site_loader');
    return isset($get_site_logo) && $get_site_logo != null
        && $get_site_logo != '' ? wp_get_attachment_url($get_site_logo) :
        KIVI_CARE_DIR_URI . 'assets/images/kivi-Loader-400.gif';
}


function kcPatientUniqueIdEnable($type)
{
    $get_unique_id = get_option(KIVI_CARE_PREFIX . 'patient_id_setting');
    $status = false;
    $patient_uid = '';
    if (!empty($get_unique_id)) {
        $status = !empty($get_unique_id['enable']) && in_array((string) $get_unique_id['enable'], ['1', 'true']);
        $randomValue = kcGenerateString(6);
        if (!empty($get_unique_id['only_number']) && in_array((string) $get_unique_id['only_number'], ['1', 'true']) && $get_unique_id['only_number'] !== 'false') {
            $randomValue = sprintf("%06d", mt_rand(1, 999999));
        }
        if (!empty($get_unique_id['prefix_value'])) {
            $patient_uid .= $get_unique_id['prefix_value'] . $randomValue;
        } else {
            $patient_uid .= $randomValue;
        }

        if (!empty($get_unique_id['postfix_value'])) {
            $patient_uid .= $get_unique_id['postfix_value'];
        }
    }

    if ($type === 'status') {
        return $status;
    }

    return $patient_uid;
}

function kcToCheckUserIsNew()
{

    $data = get_option(KIVI_CARE_PREFIX . 'new_user', true);
    if (gettype($data) != 'boolean' && in_array((string) $data, ['1', 'true'])) {
        return true;
    } else {
        return false;
    }
}

function kcWordpressLogostatusAndImage($type)
{

    if ($type == 'status') {
        $status = get_option(KIVI_CARE_PREFIX . 'wordpress_logo_status', true);
        return gettype($status) != 'boolean' && $status == 'on';
    }
    if ($type == 'image') {
        $logoImage = get_option(KIVI_CARE_PREFIX . 'wordpress_logo', true);
        return gettype($logoImage) != 'boolean' && kcWordpressLogostatusAndImage('status') ? wp_get_attachment_url($logoImage) : KIVI_CARE_DIR_URI . 'assets/images/wp-logo.png?version=22';
    }
}

function doctorWeeklyAvailability($data)
{
    if (!empty($data)) {
        global $wpdb;
        $table = $wpdb->prefix . "kc_" . "clinic_sessions";
        $doctor_id = (int) $data['doctor_id'];
        $clinic_condition = '';
        if (!empty($data['clinic_id'])) {
            $clinic_condition = ' AND clinic_id = ' . (int) $data['clinic_id'];
        }
        $result = $wpdb->get_results('select day, start_time, end_time  from ' . $table . ' where 0 = 0 and doctor_id = ' . $doctor_id . $clinic_condition, ARRAY_A);
        if (!empty($result)) {
            $result = collect($result);
            $data = $result->groupBy('day')->toArray();
            return $data;
        } else {
            return [];
        }
    } else {
        return [];
    }
}

function kcNotInPreviewmode()
{
    if (isset($_REQUEST['elementor-preview'])) {
        return false;
    }

    if (isset($_REQUEST['ver'])) {
        return false;
    }

    if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'elementor') {
        return false;
    }

    $url_params = isset($_SERVER['HTTP_REFERER']) ? wp_parse_url(sanitize_url(wp_unslash($_SERVER['HTTP_REFERER'])), PHP_URL_QUERY) : wp_parse_url(sanitize_url(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_QUERY);

    parse_str($url_params, $params);
    if (!empty($params['action']) && $params['action'] == 'elementor') {
        return false;
    }

    if (!empty($params['preview']) && $params['preview'] == 'true') {
        return false;
    }

    if (!empty($params['elementor-preview'])) {
        return false;
    }

    return true;
}

function kcClinicForElementor($type)
{
    $clinic = kcClinicList();
    $clinic = collect($clinic)->pluck('name', 'id')->toArray();
    if ($type === 'all') {
        if (!empty($clinic) && count($clinic) > 0) {
            return isKiviCareProActive() ? $clinic : [kcGetDefaultClinicId() => $clinic[1]];
        } else {
            return ['default' => __('No clinic Found', 'kc-lang')];
        }
    } else {
        if (!empty($clinic) && count($clinic) > 0) {
            $keys = array_keys($clinic);
            return count($keys) > 0 ? $keys[0] : '';
        } else {
            return 'default';
        }
    }
}

function kcDoctorForElementor($type)
{
    $doctors = kcDoctorList();
    $doctors = collect($doctors)->pluck('display_name', 'ID')->toArray();
    if ($type === 'all') {
        if (!empty($doctors) && count($doctors) > 0) {
            return isKiviCareProActive() ? $doctors : null;
        } else {
            return ['default' => __('No doctor found', 'kc-lang')];
        }
    } else {
        if (!empty($doctors) && count($doctors) > 0) {
            $keys = array_keys($doctors);
            return count($keys) > 0 ? $keys[0] : '';
        } else {
            return 'default';
        }
    }
}


function kcElementorAllCommonController($this_ele, $type)
{
    // book_button
    $this_ele->add_control(
        'iq_kivicare_' . $type . '_button_height',
        [
            'label' => esc_html__('Button Height', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'selectors' => [
                '{{WRAPPER}} .appointment_button' => 'height: {{VALUE}}px;',
            ]
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_button_width',
        [
            'label' => esc_html__('Button Width', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'selectors' => [
                '{{WRAPPER}} .appointment_button' => 'width: {{VALUE}}%;',
            ]
        ]
    );

    $this_ele->add_control(
        'iq_card_kivicare_book_appointment_border_radius',
        [
            'label' => esc_html__('Button Radius', 'kc-lang'),
            'size_units' => ['px', '%', 'em'],
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .appointment_button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};overflow:hidden;',
            ],
        ]
    );

    $this_ele->add_control(
        'iq_card_kivicare_book_appointment_margin',
        [
            'label' => esc_html__('Margin', 'kc-lang'),
            'size_units' => ['px', '%', 'em'],
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .appointment_button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]
    );

    $this_ele->add_group_control(
        \Elementor\Group_Control_Typography::get_type(),
        [
            'name' => 'iq_kivicare_' . $type . '_button_font_typography',
            'label' => esc_html__('Font Typography', 'kc-lang'),
            'global' => [
                'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
            ],
            'selector' => '{{WRAPPER}} .appointment_button',
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_button_hover_notice',
        [
            'label' => esc_html__('For hover in Button keep background type classic of button', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::HEADING,
        ]
    );

    /**
     *  Button hover
     */
    $this_ele->start_controls_tabs('iq_kivicare_' . $type . '_button_style');

    $this_ele->start_controls_tab(
        'iq_kivicare_' . $type . '_button_normal',
        [
            'label' => __('Normal', 'kc-lang'),
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_button_font_color',
        [
            'label' => esc_html__('Font Color', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .appointment_button' => 'color: {{VALUE}};',
            ]
        ]
    );

    $this_ele->add_group_control(
        \Elementor\Group_Control_Background::get_type(),
        [
            'name' => 'iq_kivicare_' . $type . '_book_appointment',
            'label' => esc_html__('Button Background', 'kc-lang'),
            'types' => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .appointment_button',
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_form_button_hover_border_size',
        [
            'label' => esc_html__('Button Border', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'selectors' => [
                '{{WRAPPER}} .appointment_button' => 'border: {{VALUE}}px;',
            ]
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_form_button_hover_border_style',
        [
            'label' => esc_html__('Button Border style', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'solid' => esc_html__('solid', 'kc-lang'),
                'dashed' => esc_html__('dashed', 'kc-lang'),
                'dotted' => esc_html__('dotted', 'kc-lang'),
                'double' => esc_html__('double', 'kc-lang'),
                'groove' => esc_html__('groove', 'kc-lang'),
                'ridge' => esc_html__('ridge', 'kc-lang'),
                'inset' => esc_html__('inset', 'kc-lang'),
                'outset' => esc_html__('outset', 'kc-lang'),
                'none' => esc_html__('none', 'kc-lang'),
                'hidden' => esc_html__('hidden', 'kc-lang'),
            ],
            'selectors' => [
                '{{WRAPPER}} .appointment_button' => 'border-style: {{VALUE}};',
            ]
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_form_button_border_color',
        [
            'label' => __('Border Color', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .appointment_button' => ' border-color: {{VALUE}};',
            ]
        ]
    );

    $this_ele->end_controls_tab();

    $this_ele->start_controls_tab(
        'iq_kivicare_' . $type . '_form_button_hover',
        [
            'label' => __('Hover', 'kc-lang'),
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_button_font_color_hover',
        [
            'label' => esc_html__('Font Color', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .appointment_button:hover' => 'color: {{VALUE}};',
            ]
        ]
    );

    $this_ele->add_group_control(
        \Elementor\Group_Control_Background::get_type(),
        [
            'name' => 'iq_kivicare_' . $type . '_book_appointment_hover_button',
            'label' => esc_html__('Button Background', 'kc-lang'),
            'types' => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .appointment_button:hover',
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_form_button_hover_border_size_hover',
        [
            'label' => esc_html__('Button Border', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'selectors' => [
                '{{WRAPPER}} .appointment_button:hover' => 'border: {{VALUE}}px;',
            ]
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_form_button_hover_border_style_hover',
        [
            'label' => esc_html__('Button Border style', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'solid' => esc_html__('solid', 'kc-lang'),
                'dashed' => esc_html__('dashed', 'kc-lang'),
                'dotted' => esc_html__('dotted', 'kc-lang'),
                'double' => esc_html__('double', 'kc-lang'),
                'groove' => esc_html__('groove', 'kc-lang'),
                'ridge' => esc_html__('ridge', 'kc-lang'),
                'inset' => esc_html__('inset', 'kc-lang'),
                'outset' => esc_html__('outset', 'kc-lang'),
                'none' => esc_html__('none', 'kc-lang'),
                'hidden' => esc_html__('hidden', 'kc-lang'),
            ],
            'selectors' => [
                '{{WRAPPER}} .appointment_button:hover' => 'border-style: {{VALUE}};',
            ]
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_form_button_hover_border_color',
        [
            'label' => __('Border Color', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .appointment_button:hover, {{WRAPPER}} .appointment_button:focus' => ' border-color: {{VALUE}};',
            ]
        ]
    );
    $this_ele->end_controls_tab();

    $this_ele->end_controls_tabs();

    $this_ele->end_controls_section();

    $this_ele->start_controls_section(
        'iq_kivicare_button',
        [
            'label' => esc_html__('Pagination Button style', 'kc-lang'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_normal_button_height',
        [
            'label' => esc_html__('Button Height', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'selectors' => [
                '{{WRAPPER}} .iq_kivicare_next_previous' => 'height: {{VALUE}}px;',
            ]
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_normal_button_width',
        [
            'label' => esc_html__('Button Width', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'selectors' => [
                '{{WRAPPER}} .iq_kivicare_next_previous' => 'width: {{VALUE}}px;',
            ]
        ]
    );

    $this_ele->add_group_control(
        \Elementor\Group_Control_Typography::get_type(),
        [
            'name' => 'iq_kivicare_' . $type . '_normal_button_font_typography',
            'label' => esc_html__('Font Typography', 'kc-lang'),
            'global' => [
                'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
            ],
            'selector' => '{{WRAPPER}} .iq_kivicare_next_previous',
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_normal_button_hover_notice',
        [
            'label' => esc_html__('For hover in Button keep background type classic of button', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::HEADING,
        ]
    );

    /**
     *  Button hover
     */
    $this_ele->start_controls_tabs('iq_kivicare_' . $type . '_normal_button_style');

    $this_ele->start_controls_tab(
        'iq_kivicare_' . $type . '_normal_button',
        [
            'label' => __('Normal', 'kc-lang'),
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_normal_button_font_color',
        [
            'label' => esc_html__('Font Color', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .iq_kivicare_next_previous' => 'color: {{VALUE}};',
            ]
        ]
    );

    $this_ele->add_group_control(
        \Elementor\Group_Control_Background::get_type(),
        [
            'name' => 'iq_kivicare_' . $type . '_button',
            'label' => esc_html__('Button Background', 'kc-lang'),
            'types' => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .iq_kivicare_next_previous',
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_form_normal_button_hover_border_size',
        [
            'label' => esc_html__('Button Border', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'selectors' => [
                '{{WRAPPER}} .iq_kivicare_next_previous' => 'border: {{VALUE}}px;',
            ]
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_form_normal_button_hover_border_style',
        [
            'label' => esc_html__('Button Border style', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'solid' => esc_html__('solid', 'kc-lang'),
                'dashed' => esc_html__('dashed', 'kc-lang'),
                'dotted' => esc_html__('dotted', 'kc-lang'),
                'double' => esc_html__('double', 'kc-lang'),
                'groove' => esc_html__('groove', 'kc-lang'),
                'ridge' => esc_html__('ridge', 'kc-lang'),
                'inset' => esc_html__('inset', 'kc-lang'),
                'outset' => esc_html__('outset', 'kc-lang'),
                'none' => esc_html__('none', 'kc-lang'),
                'hidden' => esc_html__('hidden', 'kc-lang'),
            ],
            'selectors' => [
                '{{WRAPPER}} .iq_kivicare_next_previous' => 'border-style: {{VALUE}};',
            ]
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_form_normal_button_border_color',
        [
            'label' => __('Border Color', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .iq_kivicare_next_previous' => ' border-color: {{VALUE}};',
            ]
        ]
    );

    $this_ele->end_controls_tab();

    $this_ele->start_controls_tab(
        'iq_kivicare_' . $type . '_form_normal_button_hover',
        [
            'label' => __('Hover', 'kc-lang'),
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_normal_button_font_color_hover',
        [
            'label' => esc_html__('Font Color', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .iq_kivicare_next_previous:hover' => 'color: {{VALUE}};',
            ]
        ]
    );

    $this_ele->add_group_control(
        \Elementor\Group_Control_Background::get_type(),
        [
            'name' => 'iq_kivicare_' . $type . '_normal_button_hover_button',
            'label' => esc_html__('Button Background', 'kc-lang'),
            'types' => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .iq_kivicare_next_previous:hover',
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_form_normal_button_hover_border_size_hover',
        [
            'label' => esc_html__('Button Border', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'selectors' => [
                '{{WRAPPER}} .iq_kivicare_next_previous:hover' => 'border: {{VALUE}}px;',
            ]
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_form_normal_button_hover_border_style_hover',
        [
            'label' => esc_html__('Button Border style', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'solid' => esc_html__('solid', 'kc-lang'),
                'dashed' => esc_html__('dashed', 'kc-lang'),
                'dotted' => esc_html__('dotted', 'kc-lang'),
                'double' => esc_html__('double', 'kc-lang'),
                'groove' => esc_html__('groove', 'kc-lang'),
                'ridge' => esc_html__('ridge', 'kc-lang'),
                'inset' => esc_html__('inset', 'kc-lang'),
                'outset' => esc_html__('outset', 'kc-lang'),
                'none' => esc_html__('none', 'kc-lang'),
                'hidden' => esc_html__('hidden', 'kc-lang'),
            ],
            'selectors' => [
                '{{WRAPPER}} .iq_kivicare_next_previous:hover' => 'border-style: {{VALUE}};',
            ]
        ]
    );

    $this_ele->add_control(
        'iq_kivicare_' . $type . '_form_normal_button_hover_border_color',
        [
            'label' => __('Border Color', 'kc-lang'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .iq_kivicare_next_previous:hover, {{WRAPPER}} .iq_kivicare_next_previous:focus' => ' border-color: {{VALUE}};',
            ]
        ]
    );

    $this_ele->add_control(
        'iq_card_kivicare_normal_button_border_radius',
        [
            'label' => esc_html__('Button Radius', 'kc-lang'),
            'size_units' => ['px', '%', 'em'],
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .iq_kivicare_next_previous' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};overflow:hidden;',
            ],
        ]
    );
    $this_ele->end_controls_tab();
    $this_ele->end_controls_tabs();
    $this_ele->add_control(
        'iq_card_kivicare_pagination_border_radius',
        [
            'label' => esc_html__('Button Radius', 'kc-lang'),
            'size_units' => ['px', '%', 'em'],
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .iq_kivicare_next_previous' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};overflow:hidden;',
            ],
        ]
    );
    $this_ele->add_control(
        'iq_kivicare_' . $type . '_pagination-margin',
        [
            'label' => esc_html__('Margin', 'kc-lang'),
            'size_units' => ['px', '%', 'em'],
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .kivi-pagination' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]
    );
    $this_ele->add_control(
        'iq_kivicare_' . $type . '_pagination-padding',
        [
            'label' => esc_html__('Padding', 'kc-lang'),
            'size_units' => ['px', '%', 'em'],
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'default' => [
                'top' => '10',
                'right' => '0',
                'bottom' => '0',
                'left' => '0',
                'unit' => 'px',
                'isLinked' => false,
            ],
            'selectors' => [
                '{{WRAPPER}} .kivi-pagination' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]
    );
}

function kcAddCronJob($type, $callback)
{
    add_filter('cron_schedules', function ($schedules) {
        $schedules['every_set_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'kc-lang')
        );
        return $schedules;
    });
    // Schedule an action if it's not already scheduled
    if (!wp_next_scheduled($type)) {
        wp_schedule_event(time(), 'every_set_minutes', $type);
    }

    // Hook into that action that'll fire in set minutes
    add_action($type, $callback);
}

function patientAndDoctorAppointmentReminder()
{
    $reminder_setting = get_option(KIVI_CARE_PREFIX . 'email_appointment_reminder', true);
    global $wpdb;
    $whatsappOptionEnable = kcCheckWhatsappOptionEnable();
    $smsOptionEnable = kcCheckSmsOptionEnable();
    $smsSendFunctionExists = function_exists('kcProSendSms');
    $whatsappSendFunctionExists = function_exists('kcProWhatappSms');
    $successStatus = ['sent', 'queued', 'delivered'];

    if (gettype($reminder_setting) != 'boolean') {
        if (isset($reminder_setting['status']) && isset($reminder_setting['sms_status']) && isset($reminder_setting['whatapp_status']) && isset($reminder_setting['time'])) {
            $date1 = date('Y-m-d H:i:s', strtotime(current_time("Y-m-d H:i:s")) + ((int) $reminder_setting['time'] - 1) * 3600);
            $date2 = date('Y-m-d H:i:s', strtotime(current_time("Y-m-d H:i:s")) + ((int) $reminder_setting['time']) * 3600);
            $query = "SELECT * FROM {$wpdb->prefix}kc_appointments WHERE CAST(CONCAT(appointment_start_date, ' ', appointment_start_time) AS DATETIME ) BETWEEN '{$date1}' AND '{$date2}' AND status=1";
            $appointment_data = collect($wpdb->get_results($query))->toArray();
            if (!empty($appointment_data) && is_array($appointment_data) && count($appointment_data) > 0) {
                $msg_reminder_table = $wpdb->prefix . "kc_appointment_reminder_mapping";
                foreach ($appointment_data as $appoint) {
                    $data_patient = kcCommonNotificationData($appoint, [], '', 'appointment_reminder');
                    $data_doctor = kcCommonNotificationData($appoint, [], '', 'appointment_reminder_for_doc');
                    $appointment_reminder_data_id = $wpdb->get_var("select id from {$msg_reminder_table} where msg_send_date=CURDATE() AND appointment_id=" . (int) $appoint->id);
                    if (empty($appointment_reminder_data_id)) {
                        $temp11 = [
                            'appointment_id' => $appoint->id,
                            'msg_send_date' => current_time('Y-m-d'),
                            'email_status' => 0,
                            'sms_status' => 0,
                            'whatsapp_status' => 0,
                        ];

                        $query2 = "SELECT * FROM {$msg_reminder_table} WHERE appointment_id = {$appoint->id}";
                        $reminder_data = $wpdb->get_results($query2);

                        if (empty($reminder_data)) {
                            $wpdb->insert($msg_reminder_table, $temp11);
                            $appointment_reminder_data_id = $wpdb->insert_id;
                        } else {
                            $appointment_reminder_data_id = $reminder_data[0]->id;
                        }

                    }

                    $appointment_reminder_table_data = $wpdb->get_row("SELECT * FROM {$msg_reminder_table} WHERE id={$appointment_reminder_data_id}");

                    $temp = [
                        'appointment_id' => $appoint->id,
                    ];

                    if (
                        $appointment_reminder_table_data->email_status != 1
                        && $reminder_setting['status'] == 'on'
                    ) {
                        // $temp['email_status'] = (kcSendEmail($data_patient) && kcSendEmail($data_doctor)) ? 1 : 0;
                        kcSendEmail($data_patient);
                        kcSendEmail($data_doctor);
                        $temp['email_status'] = 1;
                    }
                    if (
                        $smsOptionEnable && $smsSendFunctionExists &&
                        $appointment_reminder_table_data->sms_status != 1
                        && $reminder_setting['sms_status'] == 'on'
                    ) {
                        $smsdata = kcProSendSms('book_appointment_reminder', $data_patient);
                        kcProSendSms('book_appointment_reminder_for_doctor', $data_doctor);
                        $temp['sms_status'] = 1;
                        if (
                            isset($smsdata['status']['sms']->status)
                            && in_array(trim($smsdata['status']['sms']->status), $successStatus)
                        ) {
                            //                            $temp['sms_status'] = 1;
                        }
                    }
                    if (
                        $whatsappOptionEnable && $whatsappSendFunctionExists
                        && $appointment_reminder_table_data->whatsapp_status != 1
                        && $reminder_setting['whatapp_status'] == 'on'
                    ) {
                        $whatsappData = kcProWhatappSms('book_appointment_reminder', $data_patient);
                        kcProWhatappSms('book_appointment_reminder_for_doctor', $data_doctor);
                        $temp['whatsapp_status'] = 1;
                        if (
                            isset($whatsappData['status']['whatsapp']->status)
                            && in_array(trim($whatsappData['status']['whatsapp']->status), $successStatus)
                        ) {
                            //                            $temp['whatsapp_status'] = 1;
                        }
                    }

                    $wpdb->update($msg_reminder_table, $temp, ['id' => $appointment_reminder_table_data->id]);
                }
            }
        }
    }
}

function kcGetAppointmentPageUrl()
{
    global $wpdb;
    $data = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' and post_content LIKE '%[kivicareBookAppointment%'", ARRAY_N);
    $appointmentPageUrl = '';
    if (!empty($data)) {
        $appointmentPageUrl = get_permalink($data);
    }
    return $appointmentPageUrl;
}

function kcGetDashboardPageUrl()
{
    global $wpdb;
    $data = $wpdb->get_row("SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' and post_content LIKE '%[patientDashboard%'", ARRAY_N);
    $appointmentPageUrl = '';
    if ($data != null) {
        $appointmentPageUrl = get_permalink(isset($data[0]) ? $data[0] : 0);
    }
    return $appointmentPageUrl;
}

function kcAppointmentIsWoocommerceOrder($appointment_id)
{
    global $wpdb;
    $appointment_id = (int) $appointment_id;
    $postTable = $wpdb->prefix . 'posts';
    $postMetaTable = $wpdb->prefix . 'postmeta';
    $query = "select {$postTable}.ID from {$postTable} 
              left join {$postMetaTable} on {$postMetaTable}.post_id={$postTable}.ID
              where post_type LIKE '%shop_order%' and meta_key='kivicare_appointment_id' and meta_value={$appointment_id}";
    return $wpdb->get_var($query);
}

function kcWoocommerceRedirect($appointment_id, $data)
{
    $kcbase = new KCBase();
    $appointment_id = (int) $appointment_id;
    $message = '';
    $res_data = '';
    $status = false;
    if (
        $kcbase->getLoginUserRole() === $kcbase->getPatientRole() && iskcWooCommerceActive()
        && get_option(KIVI_CARE_PREFIX . 'woocommerce_payment') == 'on'
    ) {
        $status = true;
        $data['doctor_id']['id'] = (int) $data['doctor_id']['id'];
        $temp = [
            'appointment_id' => $appointment_id,
            'doctor_id' => $data['doctor_id']['id']
        ];
        if(isset($data['patient_id'])){
			$temp['patient_id'] =$data['patient_id']; 
		}

        if (!empty($data['widgetType'])) {
            $temp['widgetType'] = $data['widgetType'];
        }
        if (isKiviCareProActive()) {
            $res_data = apply_filters('kcpro_woocommerce_add_to_cart', $temp);
            $message = esc_html__('appointment successfully booked, Please check your email ', 'kc-lang');
        } else if (isKiviCareTelemedActive()) {
            $res_data = apply_filters('kct_woocommerce_add_to_cart', $temp);
            $message = esc_html__('appointment successfully booked, Please check your email for zoom meeting link.', 'kc-lang');
        } else if (isKiviCareGoogleMeetActive()) {
            $res_data = apply_filters('kcgm_woocommerce_add_to_cart', $temp);
            $message = esc_html__('appointment successfully booked, Please check your email ', 'kc-lang');
        }
    }

    return [
        'status' => $status,
        'message' => $message,
        'woocommerce_cart_data' => $res_data
    ];
}

// woocommerce hookup and filter
add_action('woocommerce_cart_calculate_fees', function ($cart) {
    $appointment_id = '';
    // Loop through items in the cart to find the appointment ID
    foreach ($cart->get_cart() as $items) {
        if (!empty($items['kivicare_appointment_id'])) {
            $appointment_id = $items['kivicare_appointment_id'];
            break; // Exit loop after finding the appointment ID
        }
    }
    if (!empty($appointment_id)) {
        // Calculate tax details for the appointment
        $tax_details = apply_filters('kivicare_calculate_tax', [
            'status' => false,
            'message' => '',
            'data' => []
        ], [
            "id" => $appointment_id,
            "type" => 'appointment',
        ]);
        if (!empty($tax_details['data'])) {
            foreach ($tax_details['data'] as $tax) {
                // Add tax as a fee to the cart
                $cart->add_fee($tax->name, $tax->charges);
            }
        }
    }
});

function kivicareWooocommerceAddToCart($filterData)
{
    global $wpdb;
    $status = ['status' => 0];
    $filterData['appointment_id'] = (int) $filterData['appointment_id'];
    $filterData['doctor_id'] = (int) $filterData['doctor_id'];
    $condition = ['id' => $filterData['appointment_id']];
    $wpdb->update($wpdb->prefix . 'kc_appointments', $status, $condition);
    $kiviWooProductId = kivicareWoocommerceProduct($filterData);

    if (defined('REST_REQUEST') && REST_REQUEST) {

        global $woocommerce;
        $filterData['patient_id'] = (int) $filterData['patient_id'];
        $patient_data = kcGetUserData($filterData['patient_id']);

        $address = array(
            'first_name' => $patient_data->first_name,
            'last_name' => $patient_data->last_name,
            'company' => '',
            'email' => $patient_data->user_email,
            'phone' => isset($patient_data->basicData) && isset($patient_data->basicData->mobile_number) ? $patient_data->basicData->mobile_number : null,
            'address_1' => isset($patient_data->basicData) && isset($patient_data->basicData->address) ? $patient_data->basicData->address : null,
            'address_2' => isset($patient_data->basicData) && isset($patient_data->basicData->address) ? $patient_data->basicData->address : null,
            'city' => isset($patient_data->basicData) && isset($patient_data->basicData->city) ? $patient_data->basicData->city : null,
            'state' => "",
            'postcode' => isset($patient_data->basicData) && isset($patient_data->basicData->postal_code) ? $patient_data->basicData->postal_code : null,
            'country' => isset($patient_data->basicData) && isset($patient_data->basicData->country) ? $patient_data->basicData->country : null,
        );

        // Now we create the order
        $order = wc_create_order();

        foreach ($kiviWooProductId as $key => $value) {
            $objProduct = wc_get_product($value);
            $order->add_product($objProduct);
        }

        // This is an existing SIMPLE product
        $order->set_address($address, 'billing');
        $order->set_customer_id($filterData['patient_id']);
        $order->calculate_totals();
        $order->update_status("Completed", 'Imported order', TRUE);

        update_post_meta($order->get_id(), 'kivicare_appointment_id', $filterData['appointment_id']);
        update_post_meta($order->get_id(), 'kivicare_doctor_id', $filterData['doctor_id']);
        // wc_add_order_item_meta($kiviWooProductId, 'kivicare_appointment_id', $filterData['appointment_id'] );
        // wc_add_order_item_meta($kiviWooProductId, 'doctor_id', $filterData['doctor_id'] );

        return [
            'status' => true,
            'woocommerce_redirect' => $order->get_checkout_payment_url()
        ];
    } else {

        KivicareSetWoocommerCustomerCookie();
        WC()->cart->empty_cart();
        $temp = ['kivicare_appointment_id' => $filterData['appointment_id'], 'doctor_id' => $filterData['doctor_id']];
        if (!empty($filterData['widgetType'])) {
            $temp['widgetType'] = $filterData['widgetType'];
        }
        foreach ($kiviWooProductId as $key => $value) {
            WC()->cart->add_to_cart($value, 1, '', '', $temp);
        }
    }

    return [
        'status' => true,
        'woocommerce_redirect' => wc_get_checkout_url()
    ];
}

add_action( 'woocommerce_new_order', 'kc_new_order_created_action', 10, 1 );

function kc_new_order_created_action( $order_id ) {
    $order = wc_get_order( $order_id );
    
    foreach ($order->get_items() as $item_id => $item) {
        $kivicare_appointment_id = $item->get_meta('kivicare_appointment_id');
        $doctor_id = $item->get_meta('doctor_id');
        $paymen_method = $order->get_payment_method();
        if (!empty($kivicare_appointment_id)) {
            update_post_meta($order_id, 'kivicare_appointment_id', $kivicare_appointment_id);
        }
        if (!empty($doctor_id)) {
            update_post_meta($order_id, 'kivicare_doctor_id', $doctor_id);
        }
        if (!empty($paymen_method)) {
            update_post_meta($order_id, '_payment_method_title', $paymen_method);
        }
    }
}

// Function to transfer cart item meta to order item meta
add_action('woocommerce_checkout_create_order_line_item', 'add_cart_item_meta_to_order', 10, 4);
function add_cart_item_meta_to_order($item, $cart_item_key, $values, $order)
{
    if (isset($values['kivicare_appointment_id'])) {
        $item->add_meta_data('kivicare_appointment_id', $values['kivicare_appointment_id']);
    }
   
    if (isset($values['doctor_id'])) {
        $item->add_meta_data('doctor_id', $values['doctor_id']);
    }
}


add_action('woocommerce_order_status_changed', 'kc_custom_change_order_status', 10, 4);

function kc_custom_change_order_status($order_id, $old_status, $new_status, $order) {

    if ($new_status === 'completed' && ($order->get_payment_method() === 'bacs' || $order->get_payment_method() === 'cheque')) {

        global $wpdb;
        if (!empty($order_id) && get_post_status($order_id)) {
            $appointment_id = get_post_meta($order_id, 'kivicare_appointment_id', true);
            if (!empty($appointment_id)) {
                $status = ['status' => 2];
                if (!empty($new_status) && $new_status == 'completed') {
                    $status = ['status' => 1];
                }
                if($status['status'] == 1){
                    kivicareWoocommercePaymentComplete($order_id,'woocommerce');
                }else{
                    $condition = ['id' => $appointment_id];
                    $wpdb->update($wpdb->prefix . 'kc_appointments', $status, $condition);
                }
                do_action('kc_appointment_status_update', $appointment_id, $status['status']);
            }
        }
        
    }

}

function kivicareWoocommerceProduct($filterData)
{

    global $wpdb;
    $appointments_service_table = $wpdb->prefix . 'kc_service_doctor_mapping';

    $id = [];

    $appointment_id = (int) $filterData['appointment_id'];
    $filterData['doctor_id'] = (int) $filterData['doctor_id'];
    $appointment_clinic_id = (new KCAppointment())->get_var(['id' => $appointment_id], 'clinic_id');
    $appointment_services = (new KCAppointmentServiceMapping())->get_by([
        'appointment_id' => $appointment_id,
    ]);

    foreach ($appointment_services as $key => $value) {
        $service_name = kcGetServiceById($value->service_id);

        $service_charges = kcGetServiceCharges([
            'service_id' => $value->service_id,
            'doctor_id' => $filterData['doctor_id'],
            'clinic_id' => $appointment_clinic_id
        ]);
        $product_attachment = !empty($service_charges->image) ? $service_charges->image : '';
        $appointments_service_table = $wpdb->prefix . 'kc_service_doctor_mapping';
        $kiviWooProductId = kivicareGetProductIdOfService($service_charges->id);
        $kiviWooProductId = !empty($kiviWooProductId) ? $kiviWooProductId : 0;

        $product = wc_get_product($kiviWooProductId);

        // Check if the product exists and published
        if ($product && (get_post_status($product->get_id()) === 'publish')) {
            update_post_meta($kiviWooProductId, '_downloadable', 'yes');
            update_post_meta($kiviWooProductId, '_virtual', 'yes');
        } else {
            $kiviWooProductId = wp_insert_post([
                'post_title' => $service_name->name,
                'post_type' => 'product',
                'post_status' => 'publish'
            ]);

            $wpdb->update($appointments_service_table, ['extra' => json_encode(["product_id" => $kiviWooProductId])], ['id' => $service_charges->id]);

            wp_set_object_terms($kiviWooProductId, 'simple', 'product_type');

            update_post_meta($kiviWooProductId, '_visibility', 'hidden');
            update_post_meta($kiviWooProductId, '_thumbnail_id', $product_attachment);
            update_post_meta($kiviWooProductId, '_stock_status', 'instock');
            update_post_meta($kiviWooProductId, 'total_sales', '0');
            update_post_meta($kiviWooProductId, '_downloadable', 'yes');
            update_post_meta($kiviWooProductId, '_virtual', 'yes');
            update_post_meta($kiviWooProductId, '_regular_price', '');
            update_post_meta($kiviWooProductId, '_sale_price', $service_charges->charges);
            update_post_meta($kiviWooProductId, '_purchase_note', '');
            update_post_meta($kiviWooProductId, '_featured', 'no');
            update_post_meta($kiviWooProductId, '_weight', '');
            update_post_meta($kiviWooProductId, '_length', '');
            update_post_meta($kiviWooProductId, '_width', '');
            update_post_meta($kiviWooProductId, '_height', '');
            update_post_meta($kiviWooProductId, '_sku', '');
            update_post_meta($kiviWooProductId, '_product_attributes', []);
            update_post_meta($kiviWooProductId, '_sale_price_dates_from', '');
            update_post_meta($kiviWooProductId, '_sale_price_dates_to', '');
            update_post_meta($kiviWooProductId, '_price', $service_charges->charges);
            update_post_meta($kiviWooProductId, '_sold_individually', 'yes');
            update_post_meta($kiviWooProductId, '_manage_stock', 'no');
            update_post_meta($kiviWooProductId, '_backorders', 'no');
            wc_update_product_stock($kiviWooProductId, 0, 'set');
            update_post_meta($kiviWooProductId, '_stock', '');
            update_post_meta($kiviWooProductId, 'kivicare_service_id', $service_charges->id);
            update_post_meta($kiviWooProductId, 'kivicare_doctor_id', $filterData['doctor_id']);
            update_post_meta($kiviWooProductId, 'kivicare_clinic_id', $appointment_clinic_id);
        }
    }

    foreach ($appointment_services as $key => $value) {
        $service_charges = kcGetServiceCharges([
            'service_id' => $value->service_id,
            'doctor_id' => $filterData['doctor_id'],
            'clinic_id' => $appointment_clinic_id
        ]);
        $kiviWooProductId = kivicareGetProductIdOfService($service_charges->id);
        $kiviWooProductId = !empty($kiviWooProductId) ? $kiviWooProductId : 0;
        if (!empty($kiviWooProductId)) {
            $id[] = $kiviWooProductId;
        }
    }

    return $id;
}

// set cookie for woocommerce payment
function KivicareSetWoocommerCustomerCookie()
{

    if (WC()->session && WC()->session instanceof \WC_Session_Handler && WC()->session->get_session_cookie() === false) {
        WC()->session->set_customer_session_cookie(true);
    }
    return true;
}

// woocommerce cart items
function kivicareGetCartItemsFromSession($item, $values, $key)
{
    if (array_key_exists('kivicare_appointment_id', $values)) {
        $item['kivicare_appointment_id'] = $values['kivicare_appointment_id'];
    }
    if (array_key_exists('doctor_id', $values)) {

        $item['doctor_id'] = $values['doctor_id'];
    }
    if (array_key_exists('widgetType', $values)) {

        $item['widgetType'] = $values['widgetType'];
    }

    return $item;
}

// woocommerce kivicare appointment status change based on woocommerce order status.
function kivicareWooOrderStatusChangeCustom($order_id, $old_status, $new_status, $order)
{
    global $wpdb;

    if (empty($order_id) || !get_post_status($order_id)) {
        return;
    }

    $appointment_id = get_post_meta($order_id, 'kivicare_appointment_id', true);
    if (empty($appointment_id)) {
        return;
    }

    $status = ['status' => 2];
    if ($new_status === 'completed') {
        $status = ['status' => 1];
    } elseif ($new_status === 'canceled') {
        $status = ['status' => 0];
    }

    $condition = ['id' => $appointment_id];
    $wpdb->update($wpdb->prefix . 'kc_appointments', $status, $condition);

    do_action('kc_appointment_status_update', $appointment_id, $status['status']);

    if ($status['status'] == 1 && ($order->get_payment_method() === 'bacs' || $order->get_payment_method() === 'cheque')) {
        kivicareWoocommercePaymentComplete($order_id, 'woocommerce');
    }
}

//function call when payment complete by woocommerce or PayPal gateway
function kivicareWoocommercePaymentComplete($order_id, $type = 'woocommerce')
{
    global $wpdb;
    $appointment_id = $order_id;
    if ($type === 'woocommerce') {
        $appointment_id = '';
        if (!empty($order_id) && get_post_status($order_id)) {
            $appointment_id = get_post_meta((int) $order_id, 'kivicare_appointment_id', true);
        }
    }
    if (empty($appointment_id)) {
        return;
    }
    $appointment_id = (int) $appointment_id;
    $users_table        = $wpdb->base_prefix . 'users';
    $clinics_table      = $wpdb->prefix . 'kc_clinics';
    $service_category_table = $wpdb->prefix . 'kc_services';
    $appointment_table = $wpdb->prefix . 'kc_appointments';
    $service_mapping_table = $wpdb->prefix . 'kc_service_doctor_mapping';
    $appointment_mapping_table = $wpdb->prefix . 'kc_appointment_service_mapping';
    $zoom_mapping_table = $wpdb->prefix . 'kc_appointment_zoom_mappings';
    $google_meet_mapping_table = $wpdb->prefix . 'kc_appointment_google_meet_mappings';
    $get_service_query = "SELECT {$service_mapping_table}.telemed_service,{$service_category_table}.name 
                                FROM {$appointment_mapping_table}
                                JOIN {$appointment_table} 
                                  ON {$appointment_table}.id = {$appointment_mapping_table}.appointment_id  
                                JOIN {$service_mapping_table} 
                                  ON {$service_mapping_table}.service_id= {$appointment_mapping_table}.service_id
                                  AND {$service_mapping_table}.doctor_id = {$appointment_table}.doctor_id AND {$service_mapping_table}.clinic_id = {$appointment_table}.clinic_id
                                JOIN {$service_category_table} 
                                    ON {$service_category_table}.id = {$appointment_mapping_table}.service_id 
                                WHERE {$appointment_mapping_table}.appointment_id = {$appointment_id}";
    $appointment_service_details = collect($wpdb->get_results($get_service_query));

    $get_apointment_query = "
			SELECT {$appointment_table}.*,
		       doctors.display_name  AS doctor_name,
		       patients.display_name AS patient_name,
		       {$clinics_table}.name AS clinic_name
			FROM  {$appointment_table}
		       LEFT JOIN {$users_table} doctors
		              ON {$appointment_table}.doctor_id = doctors.id
		       LEFT JOIN {$users_table} patients
		              ON {$appointment_table}.patient_id = patients.id
		       LEFT JOIN {$clinics_table}
		              ON {$appointment_table}.clinic_id = {$clinics_table}.id
            WHERE {$appointment_table}.id = {$appointment_id}";

    $appointment = collect($wpdb->get_row( $get_apointment_query ));

    $current_date = current_time("Y-m-d H:i:s");
    $appointment_day = esc_sql(strtolower(date('l', strtotime($appointment['appointment_start_date'])))) ;
    $day_short = esc_sql(substr($appointment_day, 0, 3));
    $get_timeslot_query = "SELECT time_slot FROM {$wpdb->prefix}kc_clinic_sessions  WHERE `doctor_id` = ".(int)$appointment['doctor_id']." AND `clinic_id` = ".(int)$appointment['clinic_id']."  AND ( `day` = '{$day_short}' OR `day` = '{$appointment_day}') ";
    $clinic_session_time_slots = $wpdb->get_var($get_timeslot_query);
    $time_slot = !empty($clinic_session_time_slots) ? $clinic_session_time_slots : 15;

    $appointment_data =  [
        'appointment_id' => $appointment_id,
        'doctor_id' => [
            'id' => $appointment['doctor_id'],
            'label' => $appointment['doctor_name']
        ],
        'patient_id' => [
            'id' => $appointment['patient_id'],
            'label' => $appointment['patient_name']
        ],
        'description' => $appointment['description'],
        'appointment_start_date' => $appointment['appointment_start_date'],
        'appointment_start_time' => $appointment['appointment_start_time'], 
        'time_slot' => $time_slot,
    ];

    if (!empty($appointment_service_details)) {
        //hook on appointment payment complete
        do_action('kc_appointment_payment_complete', $appointment_id);
        $telemed_service_yes_no = $appointment_service_details->pluck('telemed_service')->toArray();
        
        $appointmentHaveTelemedService = in_array('yes', $telemed_service_yes_no);
        $serviceName = $appointment_service_details->pluck('name')->implode(',');

        if(($type !== 'status_update' || ($type === 'status_update' && $appointment['status'] == '1')) && $appointmentHaveTelemedService){
            if((kcCheckDoctorTelemedType($appointment_id) == 'googlemeet')){
                $google_meet_data_query = " SELECT * FROM $google_meet_mapping_table WHERE appointment_id = {$appointment_id} ";
                $google_meet_data= $wpdb->get_row($google_meet_data_query);
                if(empty($google_meet_data)){
                    $res_data = apply_filters('kcgm_save_appointment_event', ['appoinment_id' => $appointment_id,'service' => $serviceName, 'type' => $type]);
                }
            }else{
                $zoom_data_query = " SELECT * FROM $zoom_mapping_table WHERE appointment_id = {$appointment_id} ";
		        $zoom_data = $wpdb->get_row($zoom_data_query);
                if(empty($zoom_data)){
                    $res_data = apply_filters('kct_create_appointment_meeting', $appointment_data);
                }
            }
        }
        kcProAllNotification($appointment_id, $serviceName, $appointmentHaveTelemedService);
    }
    if (in_array($type, ['paypal', 'razorpay', 'stripepay'])) {
        $wpdb->update($wpdb->prefix . "kc_payments_appointment_mappings", ['notification_status' => 1], ['appointment_id' => $appointment_id]);
    }
}

function kivicareWoocommerceOrderDataAfterOrderDetails($order)
{
    $orderId = $order->get_id();
    $doctorId = get_post_meta($orderId, 'kivicare_doctor_id', true);
    $appointmentId = get_post_meta($orderId, 'kivicare_appointment_id', true);
    if (!empty($appointmentId) && !empty($doctorId)) {
        global $wpdb;
        $appointmentTable = $wpdb->prefix . 'kc_appointments';

        $appointmentData = $wpdb->get_row('select * from ' . $appointmentTable . ' where id=' . $appointmentId);
        if (empty($appointmentData)) {
            return;
        }
        $doctorName = get_user_by('id', $doctorId);
        $appointment_services = (new KCAppointmentServiceMapping())->get_by([
            'appointment_id' => $appointmentId,
        ]);

        $serviceName = [];
        foreach ($appointment_services as $key => $value) {
            $service_name = kcGetServiceById($value->service_id);
            $serviceName[] = $service_name->name;
        }

        $patientName = get_user_by('id', $appointmentData->patient_id);

        ?>
        <p class="form-field form-field-wide wc-order-status kivicare-orderinfo">
            <label for="kivicare_doctor">
                <strong>
                    <?php
                    /* translators: doctor name*/
                    echo esc_html__('Doctor Name : ', 'kc-lang') . esc_html($doctorName->display_name);
                    ?>
                </strong>
            </label>
            <label for="kivicare_doctor">
                <strong>
                    <?php
                    /* translators: patient name*/
                    echo esc_html__('Patient Name  : ', 'kc-lang') . esc_html($patientName->display_name);
                    ?>
                </strong>
            </label>
            <label for="kivicare_service_name">
                <strong>
                    <?php
                    $serviceNameWoo = implode(", ", $serviceName);
                    /* translators: patient name*/
                    echo esc_html__('Service Name  : ', 'kc-lang') . esc_html($serviceNameWoo);
                    ?>
                </strong>
            </label>
            <label for="kivicare_appointment_date">
                <strong>
                    <?php
                    /* translators: Appointment*/
                    echo esc_html__('Appointment Date : ', 'kc-lang') . esc_html($appointmentData->appointment_start_date);
                    ?>
                </strong>
            </label>
            <label for="kivicare_appointment_time">
                <strong>
                    <?php
                    /* translators: Appointment*/
                    echo esc_html__('Appointment Time : ', 'kc-lang') . esc_html($appointmentData->appointment_start_time);
                    ?>
                </strong>
            </label>
            <label for="kivicare_appointment_print">
                <button class="button button-primary kivicare-order-print-appointment">
                    <strong>
                        <?php
                        /* translators: Appointment*/
                        echo esc_html__('Print Appointment Detail', 'kc-lang');
                        ?>
                    </strong>
                </button>
                <button class="button button-primary kivicare-order-print-appointment-disabled"
                    style="display:none; cursor: not-allowed;pointer-events: none;">
                    <strong>
                        <?php
                        /* translators: Appointment*/
                        echo esc_html__('Loading.....', 'kc-lang');
                        ?>
                    </strong>
                </button>
                <script>
                    jQuery(document).on('click', '.kivicare-order-print-appointment', function (e) {
                        e.preventDefault();
                        jQuery('.kivicare-order-print-appointment').hide();
                        jQuery('.kivicare-order-print-appointment-disabled').show();
                        jQuery.ajax({
                            url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                            type: "get",
                            dataType: "json",
                            data: {
                                action: "ajax_get",
                                id: '<?php echo esc_js($appointmentId); ?>',
                                _ajax_nonce: '<?php echo esc_js(wp_create_nonce('ajax_get')); ?>',
                                route_name: 'get_appointment_print'
                            },
                            success: function (response) {
                                jQuery('.kivicare-order-print-appointment').show();
                                jQuery('.kivicare-order-print-appointment-disabled').hide();
                                if (response.status !== undefined && response.status === true) {
                                    var w = window.screen.availWidth;
                                    var h = window.screen.availHeight;
                                    var WindowObject = window.open('', "PrintWindow", "width=" + w + ",height=" + h + ",top=200,left=200");
                                    WindowObject.document.writeln(response.data);
                                    setTimeout(() => {
                                        WindowObject.document.close();
                                        WindowObject.focus();
                                        WindowObject.print();
                                        WindowObject.close();
                                    }, 500)
                                }
                            },
                            error: function () {
                                jQuery('.kivicare-order-print-appointment').show();
                                jQuery('.kivicare-order-print-appointment-disabled').hide();
                                console.log('fail');
                            }
                        });
                    })
                </script>
            </label>
        </p>
        <?php
    }
}

function kivicareServiceDeleteOnProductDelete($product_id)
{
    if ('product' === get_post_type($product_id)) {
        $serviceId = get_post_meta($product_id, 'kivicare_service_id', true);
        if (!empty($serviceId)) {
            global $wpdb;
            $appointments_service_table = $wpdb->prefix . 'kc_service_doctor_mapping';
            $wpdb->update($appointments_service_table, ['extra' => ''], ['id' => $serviceId]);
            do_action('kc_service_delete', $serviceId);
        }
    }
}

function kivicareServiceUpdateOnProductUpdated($product_id)
{
    global $wpdb;
    $appointments_service_table = $wpdb->prefix . 'kc_service_doctor_mapping';
    $service_table = $wpdb->prefix . 'kc_services';
    $product = wc_get_product($product_id);
    $serviceId = get_post_meta($product_id, 'kivicare_service_id', true);
    $doctorId = get_post_meta($product_id, 'kivicare_doctor_id', true);
    if (!empty($doctorId) && !empty($serviceId)) {
        $id = $wpdb->get_var('select ser.id from ' . $appointments_service_table . ' as map join ' . $service_table . ' as ser on ser.id = map.service_id where map.id=' . (int) $serviceId);
        if (!empty($id)) {
            $service_data = $wpdb->get_results('select id from ' . $appointments_service_table . ' where service_id=' . (int) $id);
            if ($service_data != null && count($service_data) > 0) {
                foreach ($service_data as $s) {
                    $product_mapping_id = kivicareGetProductIdOfService($s->id);
                    if ($product_mapping_id != null && get_post_status($product_mapping_id)) {
                        $my_post = array(
                            'ID' => $product_mapping_id,
                            'post_title' => get_the_title($product_id),
                        );
                        wp_update_post($my_post);
                    }
                }
            }
            $wpdb->update($service_table, ['name' => get_the_title($product_id)], ['id' => $id]);
            do_action('kc_service_update', $id);

        }
        $wpdb->update($appointments_service_table, ['charges' => (int) $product->get_price()], ['id' => (int) $serviceId, 'doctor_id' => (int) $doctorId]);
    }
}

/***
 * woocommerce kivicare appointment save for oreder-kivicare appointment mapping
 */
function kivicareSaveToPostMeta($order_id)
{
    if (WC()->session->get('kivicare_appointment_id') !== 0 && WC()->session->get('doctor_id') !== 0) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            $kivicare_appointment_id = $cart_item['kivicare_appointment_id'];
            $kivicare_doctor_id = $cart_item['doctor_id'];
            $kivicare_widget_type = $cart_item['widgetType'];
        }
        update_post_meta($order_id, 'kivicare_doctor_id', $kivicare_doctor_id);
        update_post_meta($order_id, 'kivicare_appointment_id', $kivicare_appointment_id);
        if (!empty($kivicare_widget_type)) {
            update_post_meta($order_id, 'kivicare_widget_type', $kivicare_widget_type);
        }
    }
}

function kivicareServiceDetailOnWooProductTabs($tabs)
{
    global $post;
    $id = $post->ID;
    if ('product' === get_post_type($id)) {
        $serviceId = get_post_meta($id, 'kivicare_service_id', true);
        $doctorId = get_post_meta($id, 'kivicare_doctor_id', true);
        if (!empty($doctorId) && !empty($serviceId)) {
            $tabs['kivicare'] = array(
                'label' => __('kivicare', 'kiviCare-telemed-addon'),
                'target' => 'kivicare_options',
                'class' => array('kivicare_product_icon'),
                'priority' => 10,
            );
        }
    }

    return $tabs;
}

function kivicareServiceWooProductTabContent()
{

    global $post;
    $id = $post->ID;
    $doctorId = get_post_meta($id, 'kivicare_doctor_id', true);
    if (!empty($doctorId)) {
        $doctorName = get_user_by('id', $doctorId);
        ?>
        <div id='kivicare_options' class='panel woocommerce_options_panel'><?php

        ?>
            <div class='options_group'><?php

            woocommerce_wp_text_input(array(
                'id' => '_valid_for_days',
                'label' => __('Doctor Name:', 'kc-lang'),
                'type' => 'text',
                'value' => $doctorName->display_name,
                'custom_attributes' => array(
                    'readonly' => 'readonly'
                ),
            ));

            ?></div>

        </div><?php
    }
}

function kivicareCheckoutRedirectWidgetPayment($order_id)
{
    $order = wc_get_order($order_id);
    $appointment_id = get_post_meta($order_id, 'kivicare_appointment_id', true);
    
    // Check if this is a KiviCare appointment order and not a failed order
    if (!empty($appointment_id) && !$order->has_status('failed')) {
        // Check if this is a widget booking or a direct booking
        $kivicare_widget_type = get_post_meta($order_id, 'kivicare_widget_type', true);
        
        // If it's a widget booking (from shortcode), use the widget-specific redirect
        if (!empty($kivicare_widget_type)) {
            global $wpdb;
            if ($kivicare_widget_type === 'phpWidget') {
                $appointmentPageId = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' and post_content LIKE '%[kivicareBookAppointment%'");
            } elseif ($kivicare_widget_type === 'popupPhpWidget') {
                $appointmentPageId = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' and post_content LIKE '%[kivicareBookAppointmentButton%'");
            }
            if (!empty($appointmentPageId)) {
                $pageUrl = get_permalink($appointmentPageId);
                $query = wp_parse_url($pageUrl, PHP_URL_QUERY);
                if ($query) {
                    $pageUrl .= '&confirm_page=' . (int) $appointment_id;
                } else {
                    $pageUrl .= '?confirm_page=' . (int) $appointment_id;
                }
                wp_safe_redirect($pageUrl);
                exit;
            }
        } else {
            // For direct bookings (non-widget), redirect to the appointment list page
            $appointment_list_url = admin_url('admin.php?page=dashboard#/all-appointment-list');
            wp_safe_redirect($appointment_list_url);
            exit;
        }
    }
}

function kcWoocommerceFillCheckoutFields($fields)
{
    if (class_exists('WC') && WC()->session->get('kivicare_appointment_id') !== 0 && WC()->session->get('doctor_id') !== 0) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            $kivicare_appointment_id = $cart_item['kivicare_appointment_id'];
        }
        if (!empty($kivicare_appointment_id)) {
            global $wpdb;
            $kivicare_appointment_id = (int) $kivicare_appointment_id;
            $patient_id = $wpdb->get_var("SELECT patient_id FROM {$wpdb->prefix}kc_appointments WHERE id={$kivicare_appointment_id}");
            if (!empty($patient_id)) {
                $first_name = get_user_meta($patient_id, 'first_name', true);
                if (!empty($first_name)) {
                    $fields['billing']['billing_first_name']['default'] = $first_name;
                    $fields['shipping']['shipping_first_name']['default'] = $first_name;
                }
                $last_name = get_user_meta($patient_id, 'last_name', true);
                if (!empty($last_name)) {
                    $fields['billing']['billing_last_name']['default'] = $last_name;
                    $fields['shipping']['shipping_last_name']['default'] = $last_name;
                }

                $basic_data = get_user_meta($patient_id, 'basic_data', true);
                if (!empty($basic_data)) {
                    $basic_data = json_decode($basic_data);
                    if (!empty($basic_data->postal_code)) {

                        $fields['billing']['billing_postcode']['default'] = $basic_data->postal_code;
                        $fields['shipping']['shipping_postcode']['default'] = $basic_data->postal_code;
                    }
                    if (!empty($basic_data->city)) {
                        $fields['billing']['billing_city']['default'] = $basic_data->city;
                        $fields['shipping']['shipping_city']['default'] = $basic_data->city;
                    }
                    if (!empty($basic_data->mobile_number)) {
                        $fields['billing']['billing_phone']['default'] = $basic_data->mobile_number;
                        $fields['shipping']['billing_phone']['default'] = $basic_data->mobile_number;
                    }

                    if (!empty($basic_data->address)) {
                        $fields['billing']['billing_address_1']['default'] = $basic_data->address;
                        $fields['shipping']['shipping_address_1']['default'] = $basic_data->address;
                    }

                    if (!empty($basic_data->country)) {
                        $fields['billing']['billing_country']['default'] = 'IN';
                        $fields['shipping']['shipping_address_1']['default'] = $basic_data->address;
                    }
                }
            }
        }
    }

    return $fields;
}

function kcWoocommercePaymentGatewayEnable()
{
    $response = 'off';
    if (!iskcWooCommerceActive()) {
        update_option(KIVI_CARE_PREFIX . 'woocommerce_payment', $response);
    } else {
        if (isKiviCareTelemedActive()) {
            $response = apply_filters('kct_get_woocommerce_module_status', []);
        } elseif (isKiviCareGoogleMeetActive()) {
            $response = apply_filters('kcgm_get_woocommerce_module_status', []);
        } elseif (isKiviCareProActive()) {
            $response = apply_filters('kcpro_get_woocommerce_module_status', []);
        }
    }

    return $response;
}

function kcLocalPaymentGatewayEnable()
{
    $data = get_option(KIVI_CARE_PREFIX . 'local_payment_status', true);
    return gettype($data) !== 'boolean' && $data == 'on' ? 'on' : 'off';
}

function kcGetSingleWidgetSetting($key_name)
{

    $status = get_option(KIVI_CARE_PREFIX . 'widgetSetting', false);
    $widgetSetting = json_decode($status);
    $data = false;
    if ($status) {

        switch ($key_name) {
            case "clinicContactDetails":
                $data = !empty($widgetSetting->clinicContactDetails) ? $widgetSetting->clinicContactDetails : [];
                break;
            case "showDoctorImage":
                $data = !empty($widgetSetting->showDoctorImage) ? $widgetSetting->showDoctorImage : 0;
                break;
            case "doctorContactDetails":
                $data = !empty($widgetSetting->doctorContactDetails) ? $widgetSetting->doctorContactDetails : [];
                break;
            case "showClinicImage":
                $data = !empty($widgetSetting->showClinicImage) && in_array($widgetSetting->showClinicImage, ['true', true, 1, '1']);
                break;
            case "showClinicAddress":
                $data = !empty($widgetSetting->showClinicAddress) && in_array($widgetSetting->showClinicAddress, ['true', true, 1, '1']);
                break;
            case "showDoctorExperience":
                $data = !empty($widgetSetting->showDoctorExperience) && in_array($widgetSetting->showDoctorExperience, ['true', true, 1, '1']);
                break;
            case "showDoctorSpeciality":
                $data = !empty($widgetSetting->showDoctorSpeciality) && in_array($widgetSetting->showDoctorSpeciality, ['true', true, 1, '1']);
                break;
            case "showDoctorDegree":
                $data = !empty($widgetSetting->showDoctorDegree) && in_array($widgetSetting->showDoctorDegree, ['true', true, 1, '1']);
                break;
            case 'showDoctorRating':
                $data = !empty($widgetSetting->showDoctorRating) && in_array($widgetSetting->showDoctorRating, ['true', true, 1, '1']);
                break;
            case 'showServiceImage':
                $data = !empty($widgetSetting->showServiceImage) && in_array($widgetSetting->showServiceImage, ['true', true, 1, '1']);
                break;
            case 'showServicetype':
                $data = !empty($widgetSetting->showServicetype) && in_array($widgetSetting->showServicetype, ['true', true, 1, '1']);
                break;
            case 'showServicePrice':
                $data = !empty($widgetSetting->showServicePrice) && in_array($widgetSetting->showServicePrice, ['true', true, 1, '1']);
                break;
            case 'showServiceDuration':
                $data = !empty($widgetSetting->showServiceDuration) && in_array($widgetSetting->showServiceDuration, ['true', true, 1, '1']);
                break;
            case 'widget_print':
                $data = !empty($widgetSetting->widget_print) && in_array($widgetSetting->widget_print, ['true', true, 1, '1']);
                break;
            case 'afterWoocommerceRedirect':
                $data = !empty($widgetSetting->afterWoocommerceRedirect) && in_array($widgetSetting->afterWoocommerceRedirect, ['true', true, 1, '1']);
                break;
            default:
                $data = false;
        }
    }

    return $data;
}

function kcDefaultAppointmentWidgetOrder()
{
    return [
        ["name" => __("Choose a Clinic", "kc-lang"), "fixed" => false, "att_name" => 'clinic'],
        ["name" => __("Choose Your Doctor", "kc-lang"), "fixed" => false, "att_name" => 'doctor'],
        ["name" => __("Services from Category", "kc-lang"), "fixed" => false, "att_name" => 'category'],
        ["name" => __("Select Date and Time", "kc-lang"), "fixed" => true, "att_name" => 'date-time'],
        ["name" => __("Appointment Extra Data ", "kc-lang"), "fixed" => true, "att_name" => 'file-uploads-custom'],
        ["name" => __("User Detail Information", "kc-lang"), "fixed" => true, "att_name" => 'detail-info'],
        ["name" => __("Confirmation", "kc-lang"), "fixed" => true, "att_name" => 'confirm']
    ];
}

function kcCheckExtraTabConditionInAppointmentWidget($returnType = 'all')
{
    global $wpdb;
    $query = "SELECT count(*)  FROM {$wpdb->prefix}kc_custom_fields WHERE module_type = 'appointment_module' AND status = 1";
    $custom_module = $wpdb->get_var($query);
    $status = false;
    $option = get_option(KIVI_CARE_PREFIX . 'appointment_description_config_data', true);
    $enableAppointmentDescription = gettype($option) == 'boolean' ? 'on' : $option;

    $custom_module_status = $custom_module > 0 && isKiviCareProActive();

    if ($returnType == 'description') {
        return $enableAppointmentDescription === 'on';
    }
    if (kcAppointmentMultiFileUploadEnable() || $custom_module_status || $enableAppointmentDescription === 'on') {
        $status = true;
    }
    return $status;
}

function kcAppointmentWidgetLoader()
{
    $logoImage = get_option(KIVI_CARE_PREFIX . 'widget_loader', true);
    return gettype($logoImage) != 'boolean' ? wp_get_attachment_url($logoImage) : KIVI_CARE_DIR_URI . 'assets/images/circles-menu-1.gif';
}

function isLoaderCustomUrl()
{
    $logoImageUrl = get_option(KIVI_CARE_PREFIX . 'widget_loader', true);
    return gettype($logoImageUrl) != 'boolean';
}

function kcAppendLanguageInHead($return_value = false)
{
    if (isKiviCareProActive()) {
        $prefix = KIVI_CARE_PREFIX;
        $langType = get_option(KIVI_CARE_PREFIX . 'locoTranslateState');
        if ($langType !== false && in_array((string) $langType, ['1', 'true'])) {
            $var = require KIVI_CARE_DIR . 'resources/assets/lang/temp.php';
        } else {
            $upload_dir = wp_upload_dir();
            $dir_name = $prefix . 'lang';
            $user_dirname = $upload_dir['baseurl'] . '/' . $dir_name;
            $var = json_decode(file_get_contents($user_dirname . '/temp.json'));
        }
    } else {
        $var = require KIVI_CARE_DIR . 'resources/assets/lang/temp.php';
    }

    if ($return_value) {
        return $var;
    }

    // Sanitize the variable before using it in JavaScript
    $encodedVar = json_encode($var, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    // Output the sanitized variable in the JavaScript code
    ?>
    <script>
        window.__kivicarelang = <?php echo $encodedVar; ?>
    </script>
    <?php
}

function kcGoogleCaptchaData($type)
{

    $data = get_option(KIVI_CARE_PREFIX . 'google_recaptcha', true);

    if ($type === 'status') {
        return !empty($data['status']) ? $data['status'] : 'off';
    }

    if ($type === 'site_key') {
        return !empty($data['site_key']) ? $data['site_key'] : '';
    }

    if ($type === 'secret_key') {
        return !empty($data['secret_key']) ? $data['secret_key'] : '';
    }
}

function kcGetLogoutRedirectSetting($type)
{
    $data = get_option(KIVI_CARE_PREFIX . 'logout_redirect');
    $logout_url = wp_login_url();
    if (empty($data['clinic_admin'])) {
        $data = [
            "clinic_admin" => $logout_url,
            "patient" => $logout_url,
            "receptionist" => $logout_url,
            "doctor" => $logout_url
        ];
    }

    switch ($type) {
        case 'all':
            return $data;
            break;
        case 'clinic':
        case 'doctor':
        case 'receptionist':
        case 'patient':
            return $data[$type];
            break;
    }
}

function kcGetLogoinRedirectSetting($type)
{
    $data = get_option(KIVI_CARE_PREFIX . 'login_redirect');
    $login_url = admin_url('admin.php?page=dashboard');
    if (empty($data['clinic_admin'])) {
        $data = [
            "clinic_admin" => $login_url,
            "patient" => $login_url,
            "receptionist" => $login_url,
            "doctor" => $login_url
        ];
    }

    switch ($type) {
        case 'all':
            return $data;
            break;
        case 'clinic_admin':
        case 'doctor':
        case 'receptionist':
        case 'patient':
            return $data[$type];
            break;
    }
}

function kcPaypalSettingData($type = 'all')
{
    $status = get_option(KIVI_CARE_PREFIX . 'paypalConfig', true);
    if (gettype($status) !== 'boolean') {
        $status = json_decode($status);
        $status->mode = !empty($status->mode) ? $status->mode : [];
        $status->client_id = !empty($status->client_id) ? $status->client_id : '';
        $status->client_secret = !empty($status->client_secret) ? $status->client_secret : '';
        $status->currency = !empty($status->currency) ? $status->currency : 'USD';
        $status->enablePaypal = !empty($status->enablePaypal) && in_array($status->enablePaypal, ['true', true, 1, '1']) ? true : false;
    } else {
        $status = false;
    }
    if ($type == 'all') {
        return $status;
    }

    return !empty($status->{$type}) ? $status->{$type} : false;
}

function kcAllPaymentMethodList()
{
    $temp = [
        'paymentOffline' => __("Pay Later", "kc-lang")
    ];
    $woocommerce = kcWoocommercePaymentGatewayEnable();
    if ($woocommerce === 'on') {
        $temp['paymentWoocommerce'] = __("Woocommerce", "kc-lang");
    }
    if (kcPaypalSettingData('enablePaypal')) {
        $temp['paymentPaypal'] = __("Paypal", "kc-lang");
    }
    $razorpay = apply_filters('kivicare_razorpay_enable', false);
    if ($razorpay) {
        $temp['paymentRazorpay'] = __("Razorpay", "kc-lang");
    }
    $stripepay = apply_filters('kivicare_stripepay_enable', false);
    if ($stripepay) {
        $temp['paymentStripepay'] = __("Stripe", "kc-lang");
    }
    if (kcLocalPaymentGatewayEnable() == 'off') {
        unset($temp['paymentOffline']);
    }
    $temp = apply_filters('kivicare_enable_payment_method_lists', $temp);
    return $temp;
}

function kcGetDateFormat()
{
    //Changed the KiviCare date format to the WordPress default date format
    $data = get_option('date_format', true);
    return gettype($data) !== 'boolean' ? $data : 'D-MM-YYYY';
}

function kcPrescriptionHtml($data, $id, $type = "encounter")
{
    if (empty($data)) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }

    global $wpdb;
    $kcBase = new KCBase();
    $current_user_role = $kcBase->getLoginUserRole();
    $all_role = $kcBase->KCGetRoles();
    $all_role[] = 'administrator';
    if (!in_array($current_user_role, $all_role)) {
        return;
    }
    $data->appointment_start_time = kcGetFormatedTime($data->appointment_start_time);
    $data->appointment_start_date = kcGetFormatedDate($data->appointment_start_date);
    $data->print_type = $type;
    $show_invoice_id = 'none';
    $invoice_id = '';
    if ($data->print_type == 'bill_detail') {
        $bill_results = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}kc_bills WHERE encounter_id={$id}");
        $invoice_id = $bill_results->id;
        $show_invoice_id = '';
    }
    $themeColor = gettype(get_option(KIVI_CARE_PREFIX . 'theme_color', true)) != 'boolean' ? get_option(KIVI_CARE_PREFIX . 'theme_color') : '#4874dc';
    $bootstrapCssPath = KIVI_CARE_DIR_URI . 'assets/css/bootstrap.min.css?v=' . KIVI_CARE_VERSION;
    $bootstrapCss = file_get_contents(KIVI_CARE_DIR_URI . 'assets/css/bootstrap.min.css');
    ob_start();
    ?>
    <div>
        <style>
            <?php echo $bootstrapCss; ?>
        </style>

        <style>
            @media print {
                .container-kivicare {
                    font-size: 1rem;
                    line-height: 1.75;
                    background-color: unset;
                    color: #6e7990;
                    font-family: Roboto, sans-serif;
                    display: flex;
                    justify-content: space-between;
                    flex-direction: column;
                    height: 100vh;
                }

                h1,
                h2,
                h3,
                h4,
                h5,
                h6 {
                    font-family: Heebo, sans-serif;
                }

                h1 {
                    font-size: 4.209rem;
                }

                h2 {
                    font-size: 3.157rem;
                }

                h3 {
                    font-size: 2.369rem;
                }

                h4 {
                    font-size: 1.777rem;
                }

                h5 {
                    font-size: 1.333rem;
                }

                h6 {
                    font-size: 1rem;
                }

                .main-content {
                    display: flex;
                    align-items: start;
                    height: 100%;
                    flex-direction: column;
                }

                .table thead th,
                .table-bordered th,
                .table-bordered td {
                    border-color:
                        <?php echo esc_attr($themeColor); ?>
                    ;
                }

                p {
                    color: #171C26;
                }

                p span {
                    <?php echo !empty($data->calendar_enable) ? 'font-size: 18px;' : 'font-size: 18px;font-weight: bold;'; ?>
                }

                .border-right-5 {
                    border-right: 5px solid
                        <?php echo esc_attr($themeColor); ?>
                    ;
                }

                .border-top-5 {
                    border-top: 5px solid
                        <?php echo esc_attr($themeColor); ?>
                    ;
                }

                table thead tr th {
                    font-size: 14px !important;
                }

                table .border-primary {
                    border-color:
                        <?php echo esc_attr($themeColor); ?>
                        !important;
                }

                * {
                    -webkit-print-color-adjust: exact !important;
                    /*Chrome, Safari */
                    color-adjust: exact !important;
                    /*Firefox*/
                }
            }
        </style>
        <div class="container-kivicare">
            <div style="padding: 10px;">
                <header>
                    <table style="overflow: hidden; width:100%; padding: 8px 0px;">
                        <tr>
                            <td style="padding: 8px 0px;">
                                <img style="height: 80px; width:80px;" src="<?php echo esc_url($data->clinic_logo); ?>">
                            </td>
                            <td style="text-align: right; padding: 8px 0px;">
                                <strong><?php echo esc_html__('Date:', 'kc-lang') . ' ' . esc_html(kcGetFormatedDate($data->date)); ?></strong>
                            </td>
                        </tr>
                    </table>
                    <?php do_action('kivicare_print_after_clinic_data', $data, $id); ?>
                    <table style="width:100%; padding: 8px 0px;">
                        <td style="padding: 8px 0px; margin: 0;">
                            <span style="margin: 0; font-weight:600; font-size: 40px;">
                                <?php echo esc_html($data->name); ?></span>
                        </td>
                        <tr>
                            <td style="padding: 8px 0px;">
                                <strong><?php echo esc_html__('Dr.', 'kc-lang') . esc_html($data->doctor_name); ?></strong>
                            </td>
                            <td style="text-align: right; padding: 8px 0px;">
                                <strong><?php echo esc_html__('Contact No: ', 'kc-lang'); ?></strong>
                                <?php echo esc_html($data->telephone_no); ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0px;">
                                <strong><?php echo esc_html__('Address: ', 'kc-lang'); ?></strong>
                                <?php echo esc_html($data->clinic_address); ?>
                            </td>
                            <td style="text-align: right; padding: 8px 0px;">
                                <strong><?php echo esc_html__('Email: ', 'kc-lang'); ?></strong>
                                <?php echo esc_html($data->email); ?>
                            </td>
                        </tr>
                        <?php if(!empty($invoice_id)) {?>
                        <tr>
                            <td style="padding: 8px 0px;">
                                <strong><?php echo esc_html__('Invoice Id: ', 'kc-lang'); ?></strong>
                                <?php echo esc_html($invoice_id); ?>
                            </td>
                            <td style="text-align: right; padding: 8px 0px;">
                                <strong><?php echo esc_html__('Payment Status: ', 'kc-lang'); ?></strong>
                                <span style="color: white; background-color: <?php echo $data->payment_status == 'paid' ? 'green' : '#dc3545'; ?>; padding: 4px; border-radius: 4px;">
                                    <?php echo $data->payment_status == 'paid' ? esc_html__('Paid', 'kc-lang') : esc_html__('Unpaid', 'kc-lang') ?>
                                </span>
                            </td>
                        </tr>
                        <?php } ?>
                    </table>
                    <?php do_action('kivicare_print_after_doctor_data', $data, $id); ?>
                    <hr style="height: 4px; background: rgba(0, 0, 0, 0.1);">
                    <table style="width:100%; padding: 8px 0px;">
                        <tr>
                            <td style="padding: 8px 0px;">
                                <strong><?php echo esc_html__('Patient Name:', 'kc-lang'); ?></strong>
                                <?php echo esc_html($data->patient_name); ?>
                            </td>
                            <?php if (!empty($data->patient_age)) { ?>
                                <td style="text-align: right; padding: 8px 0px;">
                                    <strong><?php echo esc_html__('Age:', 'kc-lang'); ?></strong>
                                    <?php echo esc_html($data->patient_age); ?>
                                </td>
                            <?php } ?>
                        </tr>
                        <tr>
                            <?php if (!empty($data->patient_email)) { ?>
                                <td style="padding: 8px 0px;">
                                    <strong><?php echo esc_html__('Email:', 'kc-lang'); ?></strong>
                                    <?php echo esc_html($data->patient_email); ?>
                                </td>
                            <?php } ?>
                            <?php if (!empty($data->patient_gender)) { ?>
                                <td style="text-align: right; padding: 8px 0px;">
                                    <strong><?php echo esc_html__('Gender:', 'kc-lang'); ?></strong>
                                    <?php echo esc_html($data->patient_gender); ?>
                                </td>
                            <?php } ?>
                        </tr>
                    </table>
                </header>
                <div class="main-content">
                    <div class="container-fluid">
                        <?php
                        if ($type === 'encounter' || $type === 'mail_prescription') {
                            kcEncounterPrintTableContent($themeColor, $data, $id, $current_user_role);
                        }
                        if ($type === 'appointment') {
                            kcAppointmentPrintContent($themeColor, $data, $id, $current_user_role);
                        }
                        if ($type == 'bill_detail') {
                            kcEncounterBillDetailsPrintContent($themeColor, $data, $id, $current_user_role);
                        }
                        ?>
                    </div>
                </div>
                <?php if ($type === 'encounter') { ?>
                    <footer>
                        <div class="container-fluid">
                            <hr class="m-4 border-top-5">
                            <div class="row m-2 ">
                                <div class="col-8"></div>
                                <div class="col-4 text-center">
                                    <b>
                                        <p class="text-dark font-weight-bold">
                                            <?php echo esc_html__('Doctor Signature', 'kc-lang') ?>
                                        </p>
                                    </b>
                                    <?php
                                    $signature = get_user_meta($data->doctor_id, 'doctor_signature', true);
                                    if (!empty($signature)) {
                                        ?>
                                        <img src="<?php echo esc_html($signature); ?>" alt="signature">
                                        <?php
                                    }
                                    ?>
                                    <p class="border-bottom border-dark"></p>
                                </div>
                            </div>
                        </div>
                    </footer>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php
    do_action('kivicare_print_after_doctor_signature', $data, $id);
    $htmlContent = ob_get_clean();
    $htmlContent = apply_filters('kivicare_print_html_content', $htmlContent, $data, $id);
    return $htmlContent;
}

function kcEncounterHtml($data, $id, $type = "encounter")
{
    if (empty($data)) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }
    global $wpdb;
    $kcBase = new KCBase();
    $current_user_role = $kcBase->getLoginUserRole();
    $all_role = $kcBase->KCGetRoles();
    $all_role[] = 'administrator';
    if (!in_array($current_user_role, $all_role)) {
        return;
    }

    $data->appointment_start_time = kcGetFormatedTime($data->appointment_start_time);
    $data->appointment_start_date = kcGetFormatedDate($data->appointment_start_date);
    $data->print_type = $type;
    $show_invoice_id = 'none';
    $invoice_id = '';
    if ($data->print_type == 'bill_detail') {
        $bill_results = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}kc_bills WHERE encounter_id={$id}");
        $invoice_id = $bill_results->id;
        $show_invoice_id = '';
    }
    $themeColor = gettype(get_option(KIVI_CARE_PREFIX . 'theme_color', true)) != 'boolean' ? get_option(KIVI_CARE_PREFIX . 'theme_color') : '#4874dc';
    ob_start();
    ?>
   
    <div class="container pt-5">
        <style>
            :root {
                /* colors */
                --border-color: #ededed;
                --body-backgroud: #fff;
                --heading-color: #363636;
                --body-font-color: #636363;
                --font-color: green;
                /* Fonts */
                --body-font-family: Arial, sans-serif;
            }

            body {
                font-family: var(--body-font-family);
                padding: 0;
            }

            hr {
                display: block;
                height: 1px;
                border: 0;
                border-top: 1px solid;
                padding: 0;
                border-color: var(--border-color);
            }

            h4 {
                color: var(--heading-color);
            }

            h2 {
                color: var(--body-font-color);
            }

            .section {
                margin-bottom: 20px;
            }

            .section h3 {
                color: var(--heading-color);
                margin-bottom: 10px;
            }

            .section p {
                margin: 5px 0;
            }

            .list-item {
                margin-left: 20px;
            }

            .signature {
                margin-top: 30px;
            }
        </style>
        <h2 style="text-align: center;"><?php echo esc_html__('Encounter Details', 'kc-lang') ?></h2>
        <hr style="border-top: 2px solid rgba(0, 0, 0, 0.1);"></hr>
        <div style="background-color: var(--body-backgroud); border-radius: 5px; padding-bottom: 10px;">
            <div style="display:flex;">
                <div class="section" style="margin-right: auto;">
                    <p><strong><?php echo esc_html__('Name: ', 'kc-lang') ?></strong><?php echo esc_html($data->patient_name) ?>
                    </p>
                    <p><strong><?php echo esc_html__('Email: ', 'kc-lang') ?></strong>
                        <?php echo esc_html($data->patient_email); ?></p>
                    <p><strong><?php echo esc_html__('Encounter Date:', 'kc-lang') . ' ' . esc_html(kcGetFormatedDate($data->encounter_date)); ?>
                    </p>
                    <p><strong><?php echo esc_html__('Address: ', 'kc-lang') ?></strong>
                    </strong><?php echo !empty( $data->patient_address ) ? esc_html($data->patient_address) : esc_html__('No records found', 'kc-lang'); ?>
                    </p>
                </div>

                <div class="section" style="margin-right: auto;">
                    <p><strong><?php echo esc_html__('Clinic Name: ', 'kc-lang') ?></strong><?php echo esc_html($data->name) ?>
                    </p>
                    <p><strong><?php echo esc_html__('Doctor Name: ', 'kc-lang') ?></strong><?php echo esc_html($data->doctor_name) ?>
                    </p>
                    <p style="margin-bottom: 10px;">
                        <strong><?php echo esc_html__('Description: ', 'kc-lang') ?></strong>
                        <?php 
                            echo !empty($data->description) ? esc_html($data->description) : esc_html__('No records found', 'kc-lang');
                        ?>
                    </p>
                    <h5 style="color: <?php echo $data->Estatus ? 'green' : 'red'; ?>; margin: 0%;">
                        <?php echo $data->Estatus ? esc_html__('ACTIVE','kc-lang') : esc_html__('CLOSED','kc-lang'); ?>
                    </h5>
                </div>
            </div>
            <hr>
        </div>
        <?php
            $hide_clinical_detail_in_patient = false;

            if ($current_user_role === $kcBase->getPatientRole()) {
                $hide_clinical_detail_in_patient = filter_var(
                    get_option(KIVI_CARE_PREFIX . 'hide_clinical_detail_in_patient', false),
                    FILTER_VALIDATE_BOOLEAN
                );
            }

            if (!$hide_clinical_detail_in_patient) {
                ?>
                <div class="section">
                    <div style="border-bottom: 1px solid var(--border-color); margin-bottom: 20px;">
                        <h3><?php echo esc_html__('Clinical Details', 'kc-lang') ?></h3>
                    </div>
                    <?php kcEncounterPrintClinicalDetails($themeColor, $data, $id, $current_user_role, false); ?>
                </div>
                <?php
            }
        ?>

        <div class="section" style="margin-top: 30px ;">
            <div style="border-bottom: 1px solid var(--border-color); margin-bottom: 20px;">
                <h3><?php echo esc_html__('Prescription', 'kc-lang') ?></h3>
            </div>

            <?php
            if ($type === 'encounter' || $type === 'mail_prescription') {
                kcEncounterPrintContent($themeColor, $data, $id, $current_user_role);
            }
            if ($type === 'appointment') {
                kcAppointmentPrintContent($themeColor, $data, $id, $current_user_role);
            }
            if ($type == 'bill_detail') {
                kcEncounterBillDetailsPrintContent($themeColor, $data, $id, $current_user_role);
            }
            ?>
        </div>

        <?php
        $has_patient_encounter_module = false;
        if (!empty($data->custom_fields)) {
            foreach ($data->custom_fields as $field) {
                if ($field->module_type === 'patient_encounter_module') {
                    $has_patient_encounter_module = true;
                    break;
                }
            }
        }

        if ($has_patient_encounter_module) {
            ?>
            <div class="section" style="margin-top: 30px ;">
                <div style="border-bottom: 1px solid var(--border-color); margin-bottom: 20px;">
                    <h3><?php echo esc_html__('Other Information', 'kc-lang') ?></h3>
                </div>

                <div
                    style="background-color: var(--body-background); border-radius: 10px; box-shadow: 0 0 34px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 30px;">
                    <?php
                    foreach ($data->custom_fields as $field) {
                        if ($field->type === 'radio' && !empty($field->options)) {
                            echo "<div>";
                            echo "<p><strong>" . esc_html($field->label) . "</strong></p>";
                            foreach ($field->options as $option) {
                                $checked = '';
                                if (!empty($data->custom_fields_data)) {
                                    foreach ($data->custom_fields_data as $custom_field) {
                                        if ($custom_field->field_id == $field->id && $custom_field->fields_data == $option->text) {
                                            $checked = 'checked';
                                            break;
                                        }
                                    }
                                }
                                echo '<div>';
                                echo '<input type="radio" name="' . esc_html($field->name) . '" value="' . esc_html($option->text) . '" ' . $checked . '> ' . esc_html($option->text) . '<br>';
                                echo '</div>';
                            }
                            echo "</div><br>";
                        } elseif ($field->type === 'text') {
                            $value = '';
                            if (!empty($data->custom_fields_data)) {
                                foreach ($data->custom_fields_data as $custom_field) {
                                    if ($custom_field->field_id == $field->id) {
                                        $value = esc_html($custom_field->fields_data);
                                        break;
                                    }
                                }
                            }
                            echo "<div>";
                            echo "<p><strong>" . esc_html($field->label) . " : </strong> " . $value . " </p>";
                            echo "</div><br>";
                        } elseif ($field->type == 'number') {
                            $value = '';
                            if (!empty($data->custom_fields_data)) {
                                foreach ($data->custom_fields_data as $custom_field) {
                                    if ($custom_field->field_id == $field->id) {
                                        $value = esc_html($custom_field->fields_data);
                                        break;
                                    }
                                }
                            }
                            echo '<div>';
                            echo "<p><strong>" . esc_html($field->label) . " : </strong> " . $value . "</p>";
                            echo '</div><br>';

                        } elseif ($field->type === 'checkbox' && !empty($field->options)) {
                            echo "<div>";
                            echo "<p><strong>" . esc_html($field->label) . "</strong></p>";
                            foreach ($field->options as $option) {
                                $checked = '';
                                if (!empty($data->custom_fields_data)) {
                                    foreach ($data->custom_fields_data as $custom_field) {
                                        if ($custom_field->field_id == $field->id) {
                                            $field_data_array = json_decode($custom_field->fields_data, true);
                                            if (in_array($option->text, $field_data_array)) {
                                                $checked = 'checked';
                                                break;
                                            }
                                        }
                                    }
                                }
                                echo '<div>';
                                echo '<input type="checkbox" name="' . esc_html($field->name) . '[]" value="' . esc_html($option->text) . '" ' . $checked . '> ' . esc_html($option->text) . '<br>';
                                echo '</div>';
                            }
                            echo "</div><br>";
                        } elseif ($field->type === 'textarea') {
                            $value = '';
                            if (!empty($data->custom_fields_data)) {
                                foreach ($data->custom_fields_data as $custom_field) {
                                    if ($custom_field->field_id == $field->id) {
                                        $value = esc_html($custom_field->fields_data);
                                        break;
                                    }
                                }
                            }
                            echo "<div>";
                            echo "<p><strong>" . esc_html($field->label) . " : </strong>" . $value . "</strong></p>";
                            echo "</div><br>";

                        } elseif ($field->type === 'select' && !empty($field->options)) {
                            $value = '';
                            if (!empty($data->custom_fields_data)) {
                                foreach ($data->custom_fields_data as $custom_field) {
                                    if ($custom_field->field_id == $field->id) {
                                        $value = esc_html($custom_field->fields_data);
                                        break;
                                    }
                                }
                            }
                            echo "<div>";
                            echo "<p><strong>" . esc_html($field->label) . " : </strong> " . $value . "</p>";
                            echo "</div><br>";
                        } elseif ($field->type === 'multiselect' && !empty($field->options)) {
                            echo "<div>";
                            echo "<p><strong>" . esc_html($field->label) . " : </strong>";

                            foreach ($data->custom_fields_data as $custom_field) {
                                if ($custom_field->field_id == $field->id) {
                                    $selected_options = json_decode($custom_field->fields_data);
                                    $options_texts = [];

                                    foreach ($selected_options as $selected_option) {
                                        $options_texts[] = esc_html($selected_option->text);
                                    }

                                    echo implode(', ', $options_texts);
                                    break;
                                }
                            }
                            echo "</p>";
                            echo "</div><br>";
                        } elseif ($field->type === 'file_upload') {
                            echo "<div>";
                            echo "<p><strong>" . esc_html($field->label) . "</strong></p>";

                            if (!empty($data->custom_fields_data)) {
                                foreach ($data->custom_fields_data as $custom_field) {
                                    if ($custom_field->field_id == $field->id) {
                                        $field_data = json_decode($custom_field->fields_data);

                                        if (isset($field_data->url)) {
                                            echo esc_url($field_data->url);
                                        }

                                        break;
                                    }
                                }
                            }

                            echo "</div><br>";
                        } elseif ($field->type === 'calendar') {
                            $date = '';
                            if (!empty($data->custom_fields_data)) {
                                foreach ($data->custom_fields_data as $custom_field) {
                                    if ($custom_field->field_id == $field->id) {
                                        $date = esc_html($custom_field->fields_data);
                                        break;
                                    }
                                }
                            }
                            echo "<div>";
                            echo "<p><strong>" . esc_html($field->label) . " : </strong> " . $date . "</p>";
                            echo "</div><br>";
                        }
                    }
                    ?>
                </div>
            </div>
        <?php } ?>



        <div class="signature" style="margin-top: 50px; margin-bottom: 50px;">
            <b>
                <p class="text-dark font-weight-bold"><?php echo esc_html__('Doctor Signature', 'kc-lang') ?></p>
            </b>
        </div>
        <div class="signature-image">
            <?php
            $signature = get_user_meta($data->doctor_id, 'doctor_signature', true);
            if (!empty($signature)) {
                ?>
                <img src="<?php echo esc_html($signature); ?>" alt="signature">
                <?php
            }
            ?>
        </div>
    </div>
    <?php
    do_action('kivicare_print_after_doctor_signature', $data, $id);
    $htmlContent = ob_get_clean();
    $htmlContent = apply_filters('kivicare_print_html_content', $htmlContent, $data, $id);
    return $htmlContent;
}

function kcEncounterBillDetailsPrintContent($themeColor, $data, $id, $current_user_role = '')
{
    ob_start();

    $tax_details = apply_filters('kivicare_calculate_tax', [
        'status' => false,
        'message' => '',
        'data' => [],
        'tax_total' => 0
    ], [
        'id' => $id,
        'type' => 'encounter',
    ]);

    $clinicCurrencyDetails = kcGetClinicCurrenyPrefixAndPostfix();
    $prefix = !empty($clinicCurrencyDetails['prefix']) ? $clinicCurrencyDetails['prefix'] : '';
    $postfix = !empty($clinicCurrencyDetails['postfix']) ? $clinicCurrencyDetails['postfix'] : '';

    $has_bill_items = !empty($data->billItems) && is_array($data->billItems);
    $has_tax_data = !empty($tax_details['data']) && is_array($tax_details['data']);
    $has_discount = !empty($data->discount) && floatval($data->discount) > 0;
    $tax_total = !empty($tax_details['tax_total']) ? floatval($tax_details['tax_total']) : 0;
    $total_amount = !empty($data->total_amount) ? floatval($data->total_amount) : 0;
    $actual_amount = !empty($data->actual_amount) ? floatval($data->actual_amount) : 0;

    // Start main container with controlled spacing
    echo '<div style="width: 100%; margin-top: 20px;">';

    // === 1. SERVICES TABLE ===
    if ($has_bill_items) {
        kcPrintTitle(esc_html__('Services', 'kc-lang'));
        ?>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr>
                    <th style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-transform: uppercase; text-align: left;">
                        <?php echo esc_html__('SR NO', 'kc-lang'); ?>
                    </th>
                    <th style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-transform: uppercase; text-align: right;">
                        <?php echo esc_html__('SERVICE NAME', 'kc-lang'); ?>
                    </th>
                    <th style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-transform: uppercase; text-align: right;">
                        <?php echo esc_html__('PRICE', 'kc-lang'); ?>
                    </th>
                    <th style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-transform: uppercase; text-align: right;">
                        <?php echo esc_html__('QUANTITY', 'kc-lang'); ?>
                    </th>
                    <th style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-transform: uppercase; text-align: right;">
                        <?php echo esc_html__('TOTAL', 'kc-lang'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data->billItems as $key => $item): 
                    $label = !empty($item['item_id']['label']) ? $item['item_id']['label'] : esc_html__('Service', 'kc-lang');
                    $price = floatval($item['price']);
                    $qty = max(1, intval($item['qty']));
                    $total = $price * $qty;
                ?>
                <tr>
                    <td style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: left;">
                        <?php echo esc_html($key + 1); ?>
                    </td>
                    <td style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                        <?php echo esc_html($label); ?>
                    </td>
                    <td style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                        <?php echo esc_html($prefix . number_format($price, 2) . $postfix); ?>
                    </td>
                    <td style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                        <?php echo esc_html($qty); ?>
                    </td>
                    <td style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                        <?php echo esc_html($prefix . number_format($total, 2) . $postfix); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // === 2. TAX TABLE (Only if tax data exists) ===
    if ($has_tax_data) {
        kcPrintTitle(esc_html__('Tax', 'kc-lang'));
        ?>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr>
                    <th style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-transform: uppercase; text-align: left;">
                        <?php echo esc_html__('SR NO', 'kc-lang'); ?>
                    </th>
                    <th style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-transform: uppercase; text-align: center;">
                        <?php echo esc_html__('TAX NAME', 'kc-lang'); ?>
                    </th>
                    <th style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-transform: uppercase; text-align: right;">
                        <?php echo esc_html__('CHARGES', 'kc-lang'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tax_details['data'] as $key => $tax): 
                    $name = !empty($tax->name) ? $tax->name : esc_html__('Tax', 'kc-lang');
                    $charges = !empty($tax->charges) ? floatval($tax->charges) : 0;
                ?>
                <tr>
                    <td style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: left;">
                        <?php echo esc_html($key + 1); ?>
                    </td>
                    <td style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: center;">
                        <?php echo esc_html($name); ?>
                    </td>
                    <td style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                        <?php echo esc_html($prefix . number_format($charges, 2) . $postfix); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // === 3. SUMMARY TABLE (Always show, but conditionally render rows) ===
    ?>
    <table style="width: 100%; border-collapse: collapse;">
        <tbody>
            <!-- Base Total -->
            <?php if ($tax_total > 0): ?>
                <tr>
                    <td colspan="4" style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                        <strong><?php echo esc_html__('Subtotal', 'kc-lang'); ?></strong>
                    </td>
                    <td style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                        <?php echo esc_html($prefix . number_format($total_amount - $tax_total, 2) . $postfix); ?>
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                        <strong><?php echo esc_html__('Total Tax', 'kc-lang'); ?></strong>
                    </td>
                    <td style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                        <?php echo esc_html($prefix . number_format($tax_total, 2) . $postfix); ?>
                    </td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                        <strong><?php echo esc_html__('Total', 'kc-lang'); ?></strong>
                    </td>
                    <td style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                        <?php echo esc_html($prefix . number_format($total_amount, 2) . $postfix); ?>
                    </td>
                </tr>
            <?php endif; ?>

            <!-- Discount (Only if > 0) -->
           
                <tr>
                    <td colspan="4" style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                        <strong><?php echo esc_html__('Discount', 'kc-lang'); ?></strong>
                    </td>
                    <td style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                        <?php echo esc_html($prefix . number_format(floatval($data->discount), 2) . $postfix); ?>
                    </td>
                </tr>

            <!-- Amount Due -->
            <tr>
                <td colspan="4" style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                    <div style="float: right;"><strong><?php echo esc_html__('Amount Due', 'kc-lang'); ?></strong></div>
                </td>
                <td style="border-top: 2px solid rgba(0,0,0,0.1); padding: 12px; text-align: right;">
                    <?php echo esc_html($prefix . number_format($actual_amount, 2) . $postfix); ?>
                </td>
            </tr>
        </tbody>
    </table>
    </div>
    <?php

    $htmlContent = ob_get_clean();

    echo $htmlContent;
}

function kcEncounterPrintTableContent($themeColor, $data, $id, $current_user_role = '',$title = true)
{
    if($title){
        kcPrintTitle(esc_html__('Prescriptions', 'kc-lang'));
    }
    ?>
    <div class="row m-2">
        <div class="col-12">
            <table class="table table-bordered2">
                <thead>
                    <tr>
                        <th class="text-dark"><?php echo esc_html__('Name', 'kc-lang'); ?></th>
                        <th class="text-dark"><?php echo esc_html__('Frequency', 'kc-lang'); ?></th>
                        <th class="text-dark"><?php echo esc_html__('Duration', 'kc-lang'); ?></th>
                        <th class="text-dark"><?php echo esc_html__('Instruction', 'kc-lang'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data->prescription as $pre) {
                        ?>
                        <tr>
                            <td style="border-top: 2px solid rgba(0, 0, 0, 0.1); padding: 12px;">
                                <?php echo esc_html($pre->name); ?>
                            </td>
                            <td style="border-top: 2px solid rgba(0, 0, 0, 0.1); padding: 12px;">
                                <?php echo esc_html($pre->frequency); ?>
                            </td>
                            <td style="border-top: 2px solid rgba(0, 0, 0, 0.1); padding: 12px;">
                                <?php 
                                    echo esc_html($pre->duration) . ' ' . esc_html(_n('day', 'days', $pre->duration, 'kc-lang')); 
                                ?>
                            </td>
                            <td style="border-top: 2px solid rgba(0, 0, 0, 0.1); padding: 12px; white-space: wrap; word-break: break-all;">
                                <?php echo esc_html($pre->instruction); ?>
                            </td>
                        </tr>
                        <?php
                    } ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    do_action('kivicare_print_after_prescription_data', $data, $id);
    if (!empty($data->medical_history['show']) && ($data->medical_history['show'] === 'true' || $data->medical_history['show'] === true)) {
        kcEncounterPrintClinicalDetails( $themeColor, $data, $id, $current_user_role, $title );
    }
    if (!empty($data->include_encounter_custom_field) && $data->include_encounter_custom_field == 'true') {
        $custom_field = kcGetCustomFields('patient_encounter_module', $id, $data->doctor_id, true);
        if (!empty($custom_field)) {
            kcPrintTitle(esc_html__('Other information', 'kc-lang'));
            kcCustomFieldPrint($custom_field, $themeColor);
        }
    }
}

function kcEncounterPrintClinicalDetails( $themeColor, $data, $id, $current_user_role = '',$title = true ){
    if( $title){
        kcPrintTitle(esc_html__('Clinical Detail', 'kc-lang'));
    }
    $encounter_model = json_decode(get_option(KIVI_CARE_PREFIX . 'enocunter_modules'));
    $problem_status = $observation_status = $note_status = 0;
    foreach($encounter_model->encounter_module_config as $config){
        if($config->name == 'problem'){
            $problem_status = $config->status;
        }
        if($config->name == 'observation'){
            $observation_status = $config->status;
        }
        if($config->name == 'note'){
            $note_status = $config->status;
        }
    }
    ?>
    <style>
        @media print {
            .table-bordered2 {
                border-collapse: separate;
                border: 0;
                background-color: #000000 !important;
            }

            .text-dark {
                color: #000000 !important;
            }
        }
    </style>

    <div class="row mt-0 m-2">
    <div class="col-12">
    <table class="table table-bordered2">
            <thead>
                <tr>
                    <?php if($problem_status){
                        ?>
                        <th class="text-dark"><?php echo esc_html__('Problems', 'kc-lang'); ?></th>
                        <?php
                    } ?>
                    <?php if($observation_status){
                        ?>
                        <th class="text-dark"><?php echo esc_html__('Observations', 'kc-lang'); ?></th>
                        <?php
                    } ?>
                    <?php if($note_status){
                        ?>
                        <th class="text-dark"><?php echo esc_html__('Notes', 'kc-lang'); ?></th>
                        <?php
                    } ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = !empty($data->medical_history['count']) ? (int) $data->medical_history['count'] : 0;
                if ($count > 0) {
                    for ($i = 0; $i < $count; $i++) {
                        ?>
                        <tr>
                            <?php if($problem_status){ ?>
                            <td style="border-top: 2px solid rgba(0, 0, 0, 0.1); padding: 12px; white-space: wrap;">
                                <?php echo esc_html(!empty($data->medical_history['problem'][$i]) ? $data->medical_history['problem'][$i] : __('No records found', 'kc-lang')); ?>
                            </td>
                            <?php } ?>
                            <?php if($observation_status){ ?>
                            <td style="border-top: 2px solid rgba(0, 0, 0, 0.1); padding: 12px; white-space: wrap;">
                                <?php echo esc_html(!empty($data->medical_history['observation'][$i]) ? $data->medical_history['observation'][$i] : __('No records found', 'kc-lang')); ?>
                            </td>
                            <?php } ?>
                            <?php if($note_status){ ?>
                            <td style="border-top: 2px solid rgba(0, 0, 0, 0.1); padding: 12px; white-space: wrap; word-break: break-all;">
                                <?php echo esc_html(!empty($data->medical_history['note'][$i]) ? $data->medical_history['note'][$i] : __('No records found', 'kc-lang')); ?>
                            </td>
                            <?php } ?>
                        </tr>
                        <?php
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="3" class="text-center"><?php echo esc_html__('No records found', 'kc-lang'); ?></td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>

        </div>
    </div>

    <?php
    do_action('kivicare_print_after_clinical_detail_data', $data, $id);
}

function kcAppointmentPrintContent($themeColor, $appointmentData, $id, $current_user_role = '')
{
    global $wpdb;
    if (empty($id) && empty($appointmentData)) {
        return;
    }

    $id = (int) $id;

    $appointment_services = (new KCAppointmentServiceMapping())->get_by([
        'appointment_id' => $id,
    ]);

    $serviceCharges = 0;
    $telemedAppointment = false;
    foreach ($appointment_services as $key => $value) {
        $service_charges = kcGetServiceCharges([
            'service_id' => $value->service_id,
            'doctor_id' => $appointmentData->doctor_id,
            'clinic_id' => $appointmentData->clinic_id
        ]);
        if(!empty($service_charges)){
            if ($service_charges->telemed_service === 'yes') {
                $telemedAppointment = true;
            }
            $service_charges->charges = round((float) $service_charges->charges, 3);
            $serviceCharges = $serviceCharges + $service_charges->charges;
        }
    }

    $serviceName = !empty($appointmentData->all_services_name) ? $appointmentData->all_services_name : '';
    $clinicCurrencyDetails = kcGetClinicCurrenyPrefixAndPostfix();
    if (!empty($serviceCharges) && $serviceCharges != 0) {
        $tax_details = apply_filters('kivicare_calculate_tax', [
            'status' => false,
            'message' => '',
            'data' => []
        ], [
            "id" => $id,
            "type" => 'appointment',
        ]);
        if (!empty($tax_details['tax_total'])) {
            $serviceCharges += $tax_details['tax_total'];
        }
        $serviceCharges = (!empty($clinicCurrencyDetails['prefix']) ? $clinicCurrencyDetails['prefix'] : '') . $serviceCharges . (!empty($clinicCurrencyDetails['postfix']) ? $clinicCurrencyDetails['postfix'] : '');
    }
    $custom_field = kcGetCustomFields('appointment_module', $id, (int) $appointmentData->doctor_id, true);
    $Appointment_status = '';
    switch ($appointmentData->appointment_status) {
        case '0':
            $Appointment_status = __('Cancelled', 'kc-lang');
            break;
        case '1':
            $Appointment_status = __('Booked', 'kc-lang');
            break;
        case '3':
            $Appointment_status = __('Check Out', 'kc-lang');
            break;
        case '4':
            $Appointment_status = __('Check In', 'kc-lang');
            break;
    }

    $payment_mode = kcAppointmentPaymentMode($id);

    $data = [
        [
            [
                'name' => __('Appointment Date: ', 'kc-lang'),
                'value' => !empty($appointmentData->appointment_start_date) ? $appointmentData->appointment_start_date : ''
            ],
            [
                'name' => __('Appointment Time: ', 'kc-lang'),
                // 'value' => !empty($appointmentData->appointment_start_time) ? (kcGetAppointmentTimeFormatOption() == 'on' ? date("H:i", strtotime($appointmentData->appointment_start_time)) : date("g:i a", strtotime($appointmentData->appointment_start_time))) : ''
                'value' => !empty($appointmentData->appointment_start_time) ? $appointmentData->appointment_start_time : ''
            ],
            [
                'name' => __('Appointment Status: ', 'kc-lang'),
                'value' => $Appointment_status
            ]
        ],
        [
            [
                'name' => __('Payment Mode: ', 'kc-lang'),
                'value' => $payment_mode
            ],
            [
                'name' => __('Service: ', 'kc-lang'),
                'value' => !empty($serviceName) ? $serviceName : ''
            ],
            [
                'name' => __('Total Bill Payment: ', 'kc-lang'),
                'value' => !empty($serviceCharges) ? $serviceCharges : 0
            ]
        ]
    ];
    do_action('kivicare_print_before_appointment_data', $appointmentData, $id);
    kcPrintTitle(esc_html__('Appointment Detail', 'kc-lang'));
    ?>
    <hr style="height: 1px; background-color: rgba(0, 0, 0, 0.1);"></hr>
    <?php foreach ($data as $value) {
        ?>
        <div class="row m-2">
            <?php foreach ($value as $nested_value) {
                ?>
                <div class="col-4">
                    <p class="mb-0">
                        <span class="text-dark">
                            <?php echo esc_html($nested_value['name']); ?>
                        </span>
                        <?php echo esc_html($nested_value['value']); ?>
                    </p>
                </div>
                <?php
            } ?>
        </div>
        <?php
    }?>
    <div style="margin: 40px 0 0 0;"></div>
    <?php 
    do_action('kivicare_print_after_appointment_data', $appointmentData, $id);
    if (kcCheckExtraTabConditionInAppointmentWidget('all') || !empty($custom_field)) {
        if(!empty($custom_field) || !empty($appointmentData->description)){
            kcPrintTitle(esc_html__('Other Info', 'kc-lang'));
            ?>
            <?php if (kcCheckExtraTabConditionInAppointmentWidget('description') && !empty($appointmentData->description)) {
                ?>
                <hr style="height: 1px; background-color: rgba(0, 0, 0, 0.1);"></hr>
                <div class="row m-2">
                    <div class="col-12">
                        <p class="mb-0">
                            <span class="text-dark">
                                <?php echo esc_html__('Description: ', 'kc-lang'); ?>
                            </span>
                            <?php echo esc_html(!empty($appointmentData->description) ? $appointmentData->description : ''); ?>
                        </p>
                    </div>
                </div>
                <?php
            } ?>
            <?php if (isKiviCareProActive() && !empty($custom_field)) {
                kcCustomFieldPrint($custom_field, $themeColor);
            }
        }
    }
    if ($telemedAppointment && (isKiviCareGoogleMeetActive() || isKiviCareTelemedActive())) {
        $doctor_meeting_type = kcCheckDoctorTelemedType($appointmentData->id);
        if ($doctor_meeting_type === 'zoom') {
            $meeting_data = $wpdb->get_row("SELECT * FROM  {$wpdb->prefix}kc_appointment_zoom_mappings  WHERE appointment_id= {$id}");
            $meetingJoinLink = !empty($meeting_data->join_url) ? $meeting_data->join_url : '';
            $meetingStartLink = !empty($meeting_data->start_url) ? $meeting_data->start_url : '';
        } else {
            $meeting_data = $wpdb->get_row("SELECT * FROM  {$wpdb->prefix}kc_appointment_google_meet_mappings  WHERE appointment_id= {$id}");
            $meetingJoinLink = !empty($meeting_data->url) ? $meeting_data->url : '';
            $meetingStartLink = $meetingJoinLink;
        }
        if (!empty($meetingJoinLink) && !empty($meetingStartLink)) {
            kcPrintTitle(esc_html__('Telemed Meeting Info', 'kc-lang'));
            if (!in_array($current_user_role, ['kiviCare_patient'])) {
                ?>
                <div class="row m-2">
                    <div class="col-12">
                        <p class="mb-0" style="word-wrap: break-word">
                            <span class="text-dark">
                                <?php echo esc_html__('Meeting Start Link: ', 'kc-lang'); ?>
                            </span>
                            <a href="<?php echo esc_url($meetingJoinLink); ?>"
                                target="_blank"><?php echo esc_html($meetingJoinLink); ?></a>
                        </p>
                    </div>
                </div>
                <?php
            }
            if (!in_array($current_user_role, ['kiviCare_doctor'])) {
                ?>
                <div class="row m-2">
                    <div class="col-12">
                        <p class="mb-0" style="word-wrap: break-word">
                            <span class="text-dark">
                                <?php echo esc_html__('Meeting Join Link: ', 'kc-lang'); ?>
                            </span>
                            <a href="<?php echo esc_url($meetingStartLink); ?>"
                                target="_blank"><?php echo esc_html($meetingStartLink); ?></a>
                        </p>
                    </div>
                </div>
                <?php
            }
        ?>
        <?php
        }
    }
}

function kcAppointmentPaymentMode($id)
{
    $id = (int) $id;
    global $wpdb;
    $payment_mode = __('Manual', 'kc-lang');
    if (isKiviCareTelemedActive() || isKiviCareGoogleMeetActive() || isKiviCareProActive()) {
        if (iskcWooCommerceActive()) {
            $order_id = kcAppointmentIsWoocommerceOrder($id);
            if (!empty($order_id)) {
                $order = wc_get_order( $order_id );
                $payment_mode = !empty($order) ? 'Woocommerce-' . $order->get_payment_method() : 'Woocommerce';
            }
        }
    }

    $checkPaymentMode = $wpdb->get_var("SELECT payment_mode FROM {$wpdb->prefix}kc_payments_appointment_mappings WHERE appointment_id={$id}");
    if (!empty($checkPaymentMode)) {
        if ($checkPaymentMode == 'paypal_rest') {
            $payment_mode = esc_html__('Paypal', 'kc-lang');
        } elseif ($checkPaymentMode === 'razorpay') {
            $payment_mode = esc_html__('Razorpay', 'kc-lang');
        } elseif ($checkPaymentMode === 'stripe') {
            $payment_mode = esc_html__('Stripe', 'kc-lang');
        }
    }
    return $payment_mode;
}

function kcPrintTitle($title)
{
    ?>
    <table style="width: 100%; text-align: center; margin-top: 20px;">
        <tr>
            <td style="font-size: 24px; text-align: center;">
                <strong><?php echo esc_html($title) ?></strong>
            </td>
        </tr>
    </table>
    <?php
}

function kcCustomFieldPrint($custom_field, $themeColor)
{
    if (empty($custom_field)) {
        return;
    }
    foreach ($custom_field as $customKey => $customValue) {
        if (!empty($customValue['field_data'])) {
            if ($customKey % 3 == 0) {
                ?>
                <div class="row m-2">
                    <?php
            }
            if (!empty($customValue['type']) && $customValue['type'] === 'checkbox') {
                ?>
                    <div class="col-4">
                        <p class="mb-0 d-flex justify-content-center align-items-center">
                            <span class="text-dark mr-1">
                                <?php echo esc_html(!empty($customValue['label']) ? $customValue['label'] . ':' : ''); ?>
                            </span>
                            <?php
                            foreach ($customValue['options'] as $ckey => $cValue) {
                                ?>
                                <input type="checkbox" class="mr-1" id="<?php echo esc_html($cValue['id']); ?>" <?php echo esc_html(in_array($cValue['text'], $customValue['field_data']) ? 'checked' : ''); ?> readonly
                                    style="height: unset;margin-right:unset;accent-color:<?php echo esc_html($themeColor); ?>;vertical-align:unset">
                                <span class="mr-1"><?php echo esc_html($cValue['text']); ?></span>
                                <?php
                            }
                            ?>
                        </p>
                    </div>
                    <?php
            } else if (!empty($customValue['type']) && $customValue['type'] === 'multiselect') {
                ?>
                        <div class="col-4">
                            <p class="mb-0">
                                <span class="text-dark mr-1">
                                <?php echo esc_html(!empty($customValue['label']) ? $customValue['label'] . ':' : ''); ?>
                                </span>
                            <?php echo is_array($customValue['field_data']) ? collect($customValue['field_data'])->pluck('text')->implode(', ') : '' ?>
                            </p>
                        </div>
                    <?php
            } else {
                ?>
                        <div class="col-4">
                            <p class="mb-0">
                                <span class="text-dark">
                                <?php echo esc_html(!empty($customValue['label']) ? $customValue['label'] . ':' : ''); ?>
                                </span>
                            <?php echo esc_html($customValue['field_data']) ?>
                            </p>
                        </div>
                    <?php
            }
            if (($customKey + 1) % 3 == 0) {
                ?>
                </div>
                <?php
            }
        }
    }
}

function kcWidgetFooterContent($type, $currentActive)
{
    $currentActive = trim($currentActive);
    if ($type == 'kivicare_error_msg_login_register') {
        $buttonText = esc_html__('Register', 'kc-lang');
    } else if ($type == 'kivicare_error_msg_confirm') {
        $allPaymentMethod = kcAllPaymentMethodList();
        if (
            array_key_exists('paymentWoocommerce', $allPaymentMethod)
            || array_key_exists('paymentPaypal', $allPaymentMethod)
            || array_key_exists('paymentStripepay', $allPaymentMethod)

        ) {
            $buttonText = esc_html__('Next', 'kc-lang');
        } else {
            $buttonText = esc_html__('Confirm', 'kc-lang');
        }
    } else if ($type === 'kivicare_payment_mode_confirm') {
        $buttonText = esc_html__('Confirm', 'kc-lang');
    } else {
        $buttonText = esc_html__('Next', 'kc-lang');
    }
    ob_start();
    ?>
    <div class="card-widget-footer">
        <span id="<?php echo esc_html($type); ?>" class="alert alert-popup alert-danger alert-left error"
            style="display:none">&nbsp;</span>
        <div class="d-flex justify-content-end gap-1-5 " style="margin-left: auto;">
            <?php if ($currentActive !== 'active') { ?>
                <button type="button" class="iq-button iq-button-secondary" id="iq-widget-back-button"
                    data-step="prev"><?php echo esc_html__('Back', 'kc-lang'); ?></button>
            <?php } ?>
            <?php if ($type == 'kivicare_error_msg_confirm_next') { ?>
                <button type="button" id="kivicare_confirm_next"
                    class="iq-button iq-button-primary"><?php echo esc_html($buttonText); ?></button>
            <?php } else { ?>
                <button type="submit" name="submit" value="submit" data-step="next"
                    class="iq-button iq-button-primary"><?php echo esc_html($buttonText); ?></button>
            <?php } ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function kcAddToCalendarContent($appointment_data)
{
    global $wpdb;
    $post_table_name = $wpdb->prefix . 'posts';
    $post_name = strtolower(KIVI_CARE_PREFIX . 'default_event_template');
    $post_type = strtolower(KIVI_CARE_PREFIX . 'gcal_tmp');
    $calendar_template = $wpdb->get_row("SELECT * FROM {$post_table_name} WHERE post_name= '{$post_name}' AND post_type ='{$post_type}' AND post_status = 'publish' ", ARRAY_A);
    if (empty($calendar_template)) {
        return;
    }

    $calender_title = $calendar_template['post_title'];
    $calender_content = $calendar_template['post_content'];
    $appointment_id = (int) $appointment_data['id'];
    $appointment = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}kc_appointments WHERE id={$appointment_id}");
    $content_data = kcCommonNotificationData($appointment, [], $appointment_data['appointment_service'], 'clinic');
    $calender_content = kcEmailContentKeyReplace($calender_content, $content_data);
    $calender_title = kcEmailContentKeyReplace($calender_title, $content_data);

    $timezone = get_option('timezone_string');

    // If the timezone string is empty, fall back to the offset
    if (empty($timezone)) {
        $gmt_offset = get_option('gmt_offset');
        $timezone = timezone_name_from_abbr('', $gmt_offset * 3600, 0);
    }

    return [
        "name" => $calender_title,
        "description" => $calender_content,
        "startDate" => $appointment_data['start_date'],
        "endDate" => $appointment_data['end_date'],
        "startTime" => date("H:i", strtotime($appointment_data['start_time'])),
        "endTime" => date("H:i", strtotime($appointment_data['end_time'])),
        "location" => $appointment_data['clinic_address'],
        "timeZone" => $timezone,
        "iCalFileName" => "Reminder-Event",
    ];
}

function kcClinicSessionDoctor($doctor_mapping)
{
    $doctors = $inactive_doctors = [];
    $kcbase = (new KCBase());
    if (!empty($doctor_mapping) && count($doctor_mapping)) {
        $doctorList = get_users(['role' => $kcbase->getDoctorRole(), 'include' => $doctor_mapping]);
        foreach ($doctorList as $key => $doctor) {
            if ((string) $doctor->data->user_status !== '0') {
                $inactive_doctors[] = $doctor->data->ID;
                continue;
            }
            $user_data = get_user_meta($doctor->data->ID, 'basic_data', true);
            $user_data = json_decode($user_data);

            $specialties = !empty($user_data->specialties) ? collect($user_data->specialties)->pluck('label')->implode(",") : '';

            $temp = [
                'id' => $doctor->data->ID,
                'label' => $doctor->data->display_name . "($specialties)"
            ];

            $temp['timeSlot'] = isset($user_data->time_slot) ? $user_data->time_slot : "";

            $doctors[] = $temp;
        }
    }

    return [
        'doctors' => $doctors,
        'inactive_doctors' => $inactive_doctors
    ];
}

function kcClinicSession($clinic_sessions, $inactive_doctors = [], $doctors = [])
{
    $clinic_sessions = collect($clinic_sessions);
    $clinic_sessions = $clinic_sessions->map(function ($session) {
        $session->day = substr($session->day, 0, 3);
        return $session;
    });

    $sessions = [];
    if (count($clinic_sessions)) {

        foreach ($clinic_sessions as $session) {

            if ($session->parent_id === null || $session->parent_id === "") {
                $user_data = get_user_meta($session->doctor_id, 'basic_data', true);
                $specialties = '';
                if (!empty($user_data)) {
                    $user_data = json_decode($user_data);
                    $specialties = !empty($user_data->specialties) ? ("(" . collect($user_data->specialties)->pluck('label')->implode(",") . ")") : '';
                }
                $doctor_name = !empty($session->doctor_name) ? $session->doctor_name : '';
                $clinic_name = !empty($session->clinic_name) ? $session->clinic_name : '';

                $days = [];
                $session_doctors = [];
                $sec_start_time = "";
                $sec_end_time = "";
                $evening_session_start_time = "";
                $evening_session_end_time = "";
                $days[] = substr($session->day, 0, 3);

                $all_clinic_sessions = collect($clinic_sessions);

                $child_session = $all_clinic_sessions->where('parent_id', (int) $session->id);

                if (count($child_session) > 0) {

                    foreach ($clinic_sessions as $child_session) {

                        if ($child_session->parent_id !== null && (int) $session->id === (int) $child_session->parent_id) {

                            $session_doctors[] = $child_session->doctor_id;
                            $days[] = substr($child_session->day, 0, 3);

                            if ($session->start_time !== $child_session->start_time || $session->start_time == $session->end_time) {
                                $sec_start_time = $child_session->start_time;
                                $sec_end_time = $child_session->end_time;
                                $evening_session_start_time = !empty($child_session->start_time) ? kcGetFormatedTime($child_session->start_time) : "";
                                $evening_session_end_time = !empty($child_session->end_time) ? kcGetFormatedTime($child_session->end_time) : "";
                            }
                        }
                    }
                } else {

                    $session_doctors[] = $session->doctor_id;
                    $days[] = substr($session->day, 0, 3);
                }

                if ($session->start_time == $session->end_time) {
                    $start_time = ['', ''];
                } else {

                    $start_time = explode(":", date('H:i', strtotime($session->start_time)));
                }

                if ($session->start_time == $session->end_time) {

                    $end_time = ['', ''];
                } else {

                    $end_time = explode(":", date('H:i', strtotime($session->end_time)));
                }

                $session_doctors = array_unique($session_doctors);

                if (count($session_doctors) === 0 && count($days) === 0) {
                    $session_doctors[] = $session->doctor_id;
                    $days[] = substr($session->day, 0, 3);
                } else {
                    $evening_session_start_time = !empty($evening_session_start_time) ? $evening_session_start_time : "";
                    $evening_session_end_time = !empty($evening_session_end_time) ? $evening_session_end_time : "";
                    $sec_start_time = $sec_start_time !== "" ? explode(":", date('H:i', strtotime($sec_start_time))) : "";
                    $sec_end_time = $sec_end_time !== "" ? explode(":", date('H:i', strtotime($sec_end_time))) : "";
                }

                $evening_session = !empty($evening_session_start_time && $evening_session_end_time) ? $evening_session_start_time . ' ' . esc_html__('to', 'kc-lang') . ' ' . $evening_session_end_time : "-";
                $morning_session = (!empty($session->start_time) && !empty($session->end_time) && $session->start_time != $session->end_time) ? kcGetFormatedTime(date('H:i', strtotime($session->start_time))) . ' ' . esc_html__('to', 'kc-lang') . ' ' . kcGetFormatedTime(date('H:i', strtotime($session->end_time))) : "-";
                $new_session = [
                    'id' => $session->id,
                    'clinic_id' => [
                        'id' => $session->clinic_id,
                        'label' => $clinic_name,
                    ],
                    'clinic_name' => $clinic_name,
                    'doctor_id' => $session->doctor_id,
                    'days' => array_values(array_unique($days)),
                    'doctor_name' => $doctor_name,
                    'doctors' => [
                        'id' => $session->doctor_id,
                        'label' => $doctor_name . $specialties
                    ],
                    'time_slot' => $session->time_slot,
                    's_one_start_time' => [
                        "HH" => $start_time[0],
                        "mm" => $start_time[1],
                    ],
                    's_one_end_time' => [
                        "HH" => $end_time[0],
                        "mm" => $end_time[1],
                    ],
                    's_two_start_time' => [
                        "HH" => isset($sec_start_time[0]) ? $sec_start_time[0] : "",
                        "mm" => isset($sec_start_time[1]) ? $sec_start_time[1] : "",
                    ],
                    's_two_end_time' => [
                        "HH" => isset($sec_end_time[0]) ? $sec_end_time[0] : "",
                        "mm" => isset($sec_end_time[1]) ? $sec_end_time[1] : "",
                    ],
                    'morning_session' => $morning_session,
                    'evening_session' => $evening_session
                ];

                $sessions[] = $new_session;
            }
        }
    }

    return $sessions;
}

function kcCalculateDoctorReview($id, $type = 'html')
{
    if (isKiviCareProActive()) {
        $response = apply_filters('kcpro_calculate_doctor_review', $id, $type);
        if ($type === 'list') {
            if (is_array($response) && array_key_exists('star', $response)) {
                return $response;
            } else {
                return [
                    "star" => 0,
                    'total_rating' => 0
                ];
            }
        } else {
            if (array_key_exists('star', $response)) {
                echo $response['star'];
            }
        }
    } else {
        if ($type === 'list') {
            return [
                "star" => 0,
                'total_rating' => 0
            ];
        }
    }
}

function kcDashboardSidebarArray($user_roles)
{
    foreach ($user_roles as $key => $role) {
        $option_data = get_option($role === 'administrator' ? KIVI_CARE_PREFIX . "{$role}_dashboard_sidebar_data" : "{$role}_dashboard_sidebar_data");

        if (!empty($option_data) && is_array($option_data)) {
            $data = $option_data;
        } else {
            $data = kcAdminSidebarArray();
        }
        return apply_filters("kivicare_{$role}_dashboard_sidebar_data", $data);
    }

    $kcBase = new KCBase();
    $role = array_pop($user_roles);

    $kcDashboardSidebarArrayByRole = [
        "administrator" => "kcAdminSidebarArray",
        $kcBase->getClinicAdminRole() => "kcClinicAdminSidebarArray",
        $kcBase->getReceptionistRole() => "kcReceptionistSidebarArray",
        $kcBase->getDoctorRole() => "kcDoctorSidebarArray",
        $kcBase->getPatientRole() => "kcPatientSidebarArray",
    ];

    $data = apply_filters('kivicare_administrator_dashboard_sidebar_data', $kcDashboardSidebarArrayByRole[$role]());

    switch ($user_roles) {
        case 'administrator':
            $option_data = get_option(KIVI_CARE_PREFIX . 'administrator_dashboard_sidebar_data');
            if (!empty($option_data) && is_array($option_data)) {
                $data = $option_data;
            } else {
                $data = kcAdminSidebarArray();
            }
            $data = apply_filters('kivicare_administrator_dashboard_sidebar_data', $data);
            break;
        case $kcBase->getClinicAdminRole():
            $option_data = get_option(KIVI_CARE_PREFIX . 'clinic_admin_dashboard_sidebar_data');
            if (!empty($option_data) && is_array($option_data)) {
                $data = $option_data;
            } else {
                $data = kcClinicAdminSidebarArray();
            }
            $data = apply_filters('kivicare_clinic_admin_dashboard_sidebar_data', $data);
            break;
        case $kcBase->getReceptionistRole():
            $option_data = get_option(KIVI_CARE_PREFIX . 'receptionist_dashboard_sidebar_data');
            if (!empty($option_data) && is_array($option_data)) {
                $data = $option_data;
            } else {
                $data = kcReceptionistSidebarArray();
            }
            $data = apply_filters('kivicare_receptionist_dashboard_sidebar_data', $data);
            break;
        case $kcBase->getDoctorRole():
            $option_data = get_option(KIVI_CARE_PREFIX . 'doctor_dashboard_sidebar_data');
            if (!empty($option_data) && is_array($option_data)) {
                $data = $option_data;
            } else {
                $data = kcDoctorSidebarArray();
            }
            $data = apply_filters('kivicare_doctor_dashboard_sidebar_data', $data);
            break;
        case $kcBase->getPatientRole():
            $option_data = get_option(KIVI_CARE_PREFIX . 'patient_dashboard_sidebar_data');
            if (!empty($option_data) && is_array($option_data)) {
                $data = $option_data;
            } else {
                $data = kcPatientSidebarArray();
            }
            $data = apply_filters('kivicare_patient_dashboard_sidebar_data', $data);
            break;
    }

    return $data;
}

function kcGetUserRegistrationShortcodeSetting($type)
{
    $data = get_option(KIVI_CARE_PREFIX . 'user_registration_shortcode_setting', true);
    $data = !empty($data) && is_array($data) ? $data : [];

    $user_role = get_option(KIVI_CARE_PREFIX . 'user_registration_shortcode_role_setting', true);
    $user_role = !empty($user_role) && is_array($user_role) ? $user_role : [];

    if ($type === 'patient') {
        return !empty($data['patient']) && in_array($data['patient'], ['on', 'off']) ? $data['patient'] : 'on';
    } elseif ($type === 'doctor') {
        return !empty($data['doctor']) && in_array($data['doctor'], ['on', 'off']) ? $data['doctor'] : 'off';
    } elseif ($type === 'receptionist') {
        return !empty($data['receptionist']) && in_array($data['receptionist'], ['on', 'off']) ? $data['receptionist'] : 'off';
    } elseif ($type === 'kiviCare_patient') {
        return !empty($user_role['kiviCare_patient']) && in_array($user_role['kiviCare_patient'], ['on', 'off']) ? $user_role['kiviCare_patient'] : 'on';
    } elseif ($type === 'kiviCare_doctor') {
        return !empty($user_role['kiviCare_doctor']) && in_array($user_role['kiviCare_doctor'], ['on', 'off']) ? $user_role['kiviCare_doctor'] : 'on';
    } elseif ($type === 'kiviCare_receptionist') {
        return !empty($user_role['kiviCare_receptionist']) && in_array($user_role['kiviCare_receptionist'], ['on', 'off']) ? $user_role['kiviCare_receptionist'] : 'on';
    }

    return !empty($data[$type]) ? $data[$type] : '';
}

function kcAdminSidebarArray()
{
    $translate_lang = require KIVI_CARE_DIR . 'resources/assets/lang/temp.php';
    $data = [
        [
            'label' => isset($translate_lang['dashboard']['dashboard']) ? $translate_lang['dashboard']['dashboard'] : esc_html__('Dashboard', 'kc-lang'),
            'type' => 'route',
            'link' => 'dashboard',
            'iconClass' => 'fa fa-tachometer-alt',
            'routeClass' => 'dashboard',
        ],
        [
            'label' => isset($translate_lang['appointments']['appointments']) ? $translate_lang['appointments']['appointments'] : esc_html__('Appointments', 'kc-lang'),
            'type' => 'route',
            'link' => 'appointment-list.index',
            'iconClass' => 'fas fa-calendar-week',
            'routeClass' => 'appointment_list',
        ],
        [
            'label' => isset($translate_lang['patient_encounter']['encounters']) ? $translate_lang['patient_encounter']['encounters'] : esc_html__('Encounters', 'kc-lang'),
            'type' => 'parent',
            'link' => 'encounter',
            'iconClass' => 'far fa-calendar-times',
            'routeClass' => 'parent',
            'childrens' => [
                [
                    'label' => isset($translate_lang['patient_encounter']['encounters_list']) ? $translate_lang['patient_encounter']['encounters_list'] : esc_html__('Encounters', 'kc-lang'),
                    'type' => 'route',
                    'link' => 'encounter-list',
                    'iconClass' => 'far fa-calendar-times',
                    'routeClass' => 'patient_encounter_list',
                ],
                [
                    'label' => $translate_lang['encounter_template']['encounter_template'] ?? esc_html__('Encounter Templates', 'kc-lang'),
                    'type' => 'route',
                    'link' => 'encounter-template',
                    'iconClass' => 'far fa-calendar',
                    'routeClass' => 'encounter_template',
                ],
            ]
        ],
        [
            'label' => isset($translate_lang['clinic']['clinic']) ? $translate_lang['clinic']['clinic'] : esc_html__('Clinic', 'kc-lang'),
            'type' => 'route',
            'link' => 'clinic',
            'iconClass' => 'fas fa-hospital',
            'routeClass' => 'clinic',
        ],
        [
            'label' => isset($translate_lang['dashboard']['patients']) ? $translate_lang['dashboard']['patients'] : esc_html__('Patients', 'kc-lang'),
            'type' => 'route',
            'link' => 'patient',
            'iconClass' => 'fas fa-hospital-user',
            'routeClass' => 'patient',
        ],
        [
            'label' => isset($translate_lang['common']['doctors']) ? $translate_lang['common']['doctors'] : esc_html__('Doctors', 'kc-lang'),
            'type' => 'route',
            'link' => 'doctor',
            'iconClass' => 'fa fa-user-md',
            'routeClass' => 'doctor',
        ],
        [
            'label' => isset($translate_lang['clinic']['receptionist']) ? $translate_lang['clinic']['receptionist'] : esc_html__('Receptionist', 'kc-lang'),
            'type' => 'route',
            'link' => 'receptionist',
            'iconClass' => 'fa fa-users',
            'routeClass' => 'receptionist',
        ],
        [
            'label' => isset($translate_lang['common']['services']) ? $translate_lang['common']['services'] : esc_html__('Services', 'kc-lang'),
            'type' => 'route',
            'link' => 'service',
            'iconClass' => 'fa fa-server',
            'routeClass' => 'service',
        ],
        [
            'label' => isset($translate_lang['doctor_session']['doc_sessions']) ? $translate_lang['doctor_session']['doc_sessions'] : esc_html__('Doctor Sessions', 'kc-lang'),
            'type' => 'route',
            'link' => 'doctor-session.create',
            'iconClass' => 'fa fa-calendar',
            'routeClass' => 'doctor_session',
        ],
        [
            "label" => "Taxes",
            "type" => "route",
            "link" => "tax",
            "iconClass" => "fas fa-donate",
            "routeClass" => "tax"
        ],
        [
            'label' => isset($translate_lang['patient_bill']['billing_records']) ? $translate_lang['patient_bill']['billing_records'] : esc_html__('Billing records', 'kc-lang'),
            'type' => 'route',
            'link' => 'billings',
            'iconClass' => 'fa fa-file-invoice',
            'routeClass' => 'billings',
        ],
        [
            'label' => isset($translate_lang['reports']['reports']) ? $translate_lang['reports']['reports'] : esc_html__('Reports', 'kc-lang'),
            'type' => 'route',
            'link' => 'clinic-revenue-reports',
            'iconClass' => 'fas fa-chart-line',
            'routeClass' => 'clinic-revenue-reports',
        ],
        [
            'label' => isset($translate_lang['common']['settings']) ? $translate_lang['common']['settings'] : esc_html__('Settings', 'kc-lang'),
            'type' => 'route',
            'link' => 'setting.general-setting',
            'iconClass' => 'fa fa-cogs',
            'routeClass' => 'settings',
        ],
        [
            'label' => esc_html__('Get help', 'kc-lang'),
            'type' => 'route',
            'link' => 'get_help',
            'iconClass' => 'fas fa-question-circle',
            'routeClass' => 'get_help',
        ],
        [
            'label' => esc_html__('Get Pro', 'kc-lang'),
            'type' => 'route',
            'link' => 'get_pro',
            'iconClass' => 'fas fa-question-circle',
            'routeClass' => 'get_pro',
        ],
        [
            'label' => isset($translate_lang['common']['request_features']) ? $translate_lang['common']['request_features'] : esc_html__('Request Features', 'kc-lang'),
            'type' => 'href',
            'link' => 'https://iqonic.design/feature-request/?for_product=kivicare',
            'iconClass' => 'fas fa-external-link-alt',
            'routeClass' => 'request_feature',
        ]
    ];

    return $data;
}

function kcClinicAdminSidebarArray()
{
    $translate_lang = require KIVI_CARE_DIR . 'resources/assets/lang/temp.php';
    $data = [
        [
            'label' => isset($translate_lang['dashboard']['dashboard']) ? $translate_lang['dashboard']['dashboard'] : esc_html__('Dashboard', 'kc-lang'),
            'type' => 'route',
            'link' => 'dashboard',
            'iconClass' => 'fa fa-tachometer-alt',
            'routeClass' => 'dashboard',
        ],
        [
            'label' => isset($translate_lang['appointments']['appointments']) ? $translate_lang['appointments']['appointments'] : esc_html__('Appointments', 'kc-lang'),
            'type' => 'route',
            'link' => 'appointment-list.index',
            'iconClass' => 'fas fa-calendar-week',
            'routeClass' => 'appointment_list',
        ],
        [
            'label' => isset($translate_lang['patient_encounter']['encounters']) ? $translate_lang['patient_encounter']['encounters'] : esc_html__('Encounters', 'kc-lang'),
            'type' => 'parent',
            'link' => 'encounter',
            'iconClass' => 'far fa-calendar-times',
            'routeClass' => 'parent',
            'childrens' => [
                [
                    'label' => isset($translate_lang['patient_encounter']['encounters_list']) ? $translate_lang['patient_encounter']['encounters_list'] : esc_html__('Encounters List', 'kc-lang'),
                    'type' => 'route',
                    'link' => 'encounter-list',
                    'iconClass' => 'far fa-calendar-times',
                    'routeClass' => 'patient_encounter_list',
                ],
                [
                    'label' => $translate_lang['encounter_template']['encounter_template'] ?? esc_html__('Encounter Templates', 'kc-lang'),
                    'type' => 'route',
                    'link' => 'encounter-template',
                    'iconClass' => 'far fa-calendar',
                    'routeClass' => 'encounter_template',
                ],
            ]
        ],
        [
            'label' => isset($translate_lang['dashboard']['patients']) ? $translate_lang['dashboard']['patients'] : esc_html__('Patients', 'kc-lang'),
            'type' => 'route',
            'link' => 'patient',
            'iconClass' => 'fas fa-hospital-user',
            'routeClass' => 'patient',
        ],
        [
            'label' => isset($translate_lang['common']['doctors']) ? $translate_lang['common']['doctors'] : esc_html__('Doctors', 'kc-lang'),
            'type' => 'route',
            'link' => 'doctor',
            'iconClass' => 'fa fa-user-md',
            'routeClass' => 'doctor',
        ],
        [
            'label' => isset($translate_lang['clinic']['receptionist']) ? $translate_lang['clinic']['receptionist'] : esc_html__('Receptionist', 'kc-lang'),
            'type' => 'route',
            'link' => 'receptionist',
            'iconClass' => 'fa fa-users',
            'routeClass' => 'receptionist',
        ],
        [
            'label' => isset($translate_lang['common']['services']) ? $translate_lang['common']['services'] : esc_html__('Services', 'kc-lang'),
            'type' => 'route',
            'link' => 'service',
            'iconClass' => 'fa fa-server',
            'routeClass' => 'service',
        ],
        [
            'label' => isset($translate_lang['doctor_session']['doc_sessions']) ? $translate_lang['doctor_session']['doc_sessions'] : esc_html__('Doctor Sessions', 'kc-lang'),
            'type' => 'route',
            'link' => 'doctor-session.create',
            'iconClass' => 'fa fa-calendar',
            'routeClass' => 'doctor_session',
        ],
        [
            "label" => "Taxes",
            "type" => "route",
            "link" => "tax",
            "iconClass" => "fas fa-donate",
            "routeClass" => "tax"
        ],
        [
            'label' => isset($translate_lang['patient_bill']['billing_records']) ? $translate_lang['patient_bill']['billing_records'] : esc_html__('Billing records', 'kc-lang'),
            'type' => 'route',
            'link' => 'billings',
            'iconClass' => 'fa fa-file-invoice',
            'routeClass' => 'billings',
        ],
        [
            'label' => isset($translate_lang['reports']['reports']) ? $translate_lang['reports']['reports'] : esc_html__('Reports', 'kc-lang'),
            'type' => 'route',
            'link' => 'clinic-revenue-reports',
            'iconClass' => 'fas fa-chart-line',
            'routeClass' => 'clinic-revenue-reports',
        ],
        [
            'label' => isset($translate_lang['common']['settings']) ? $translate_lang['common']['settings'] : esc_html__('Settings', 'kc-lang'),
            'type' => 'route',
            'link' => 'clinic.schedule',
            'iconClass' => 'fa fa-cogs',
            'routeClass' => 'clinic_settings',
        ]
    ];

    return $data;
}

function kcReceptionistSidebarArray()
{
    $translate_lang = require KIVI_CARE_DIR . 'resources/assets/lang/temp.php';
    $data = [
        [
            'label' => isset($translate_lang['dashboard']['dashboard']) ? $translate_lang['dashboard']['dashboard'] : esc_html__('Dashboard', 'kc-lang'),
            'type' => 'route',
            'link' => 'dashboard',
            'iconClass' => 'fa fa-tachometer-alt',
            'routeClass' => 'dashboard',
        ],
        [
            'label' => isset($translate_lang['appointments']['appointments']) ? $translate_lang['appointments']['appointments'] : esc_html__('Appointments', 'kc-lang'),
            'type' => 'route',
            'link' => 'appointment-list.index',
            'iconClass' => 'fas fa-calendar-week',
            'routeClass' => 'appointment_list',
        ],
        [
            'label' => isset($translate_lang['patient_encounter']['encounters']) ? $translate_lang['patient_encounter']['encounters'] : esc_html__('Encounters', 'kc-lang'),
            'type' => 'parent',
            'link' => 'encounter',
            'iconClass' => 'far fa-calendar-times',
            'routeClass' => 'parent',
            'childrens' => [
                [
                    'label' => isset($translate_lang['patient_encounter']['encounters_list']) ? $translate_lang['patient_encounter']['encounters_list'] : esc_html__('Encounters', 'kc-lang'),
                    'type' => 'route',
                    'link' => 'encounter-list',
                    'iconClass' => 'far fa-calendar-times',
                    'routeClass' => 'patient_encounter_list',
                ],
                [
                    'label' => $translate_lang['encounter_template']['encounter_template'] ?? esc_html__('Encounter Templates', 'kc-lang'),
                    'type' => 'route',
                    'link' => 'encounter-template',
                    'iconClass' => 'far fa-calendar',
                    'routeClass' => 'encounter_template',
                ],
            ]
        ],
        [
            'label' => isset($translate_lang['dashboard']['patients']) ? $translate_lang['dashboard']['patients'] : esc_html__('Patients', 'kc-lang'),
            'type' => 'route',
            'link' => 'patient',
            'iconClass' => 'fas fa-hospital-user',
            'routeClass' => 'patient',
        ],
        [
            'label' => isset($translate_lang['common']['doctors']) ? $translate_lang['common']['doctors'] : esc_html__('Doctors', 'kc-lang'),
            'type' => 'route',
            'link' => 'doctor',
            'iconClass' => 'fa fa-user-md',
            'routeClass' => 'doctor',
        ],
        [
            'label' => isset($translate_lang['common']['services']) ? $translate_lang['common']['services'] : esc_html__('Services', 'kc-lang'),
            'type' => 'route',
            'link' => 'service',
            'iconClass' => 'fa fa-server',
            'routeClass' => 'service',
        ],
        [
            'label' => isset($translate_lang['patient_bill']['billing_records']) ? $translate_lang['patient_bill']['billing_records'] : esc_html__('Billing records', 'kc-lang'),
            'type' => 'route',
            'link' => 'billings',
            'iconClass' => 'fa fa-file-invoice',
            'routeClass' => 'billings',
        ],
        [
            'label' => isset($translate_lang['common']['settings']) ? $translate_lang['common']['settings'] : esc_html__('Settings', 'kc-lang'),
            'type' => 'route',
            'link' => 'clinic.schedule',
            'iconClass' => 'fa fa-cogs',
            'routeClass' => 'clinic_settings',
        ]
    ];

    return $data;
}

function kcDoctorSidebarArray()
{
    $translate_lang = require KIVI_CARE_DIR . 'resources/assets/lang/temp.php';
    $data = [
        [
            'label' => isset($translate_lang['dashboard']['dashboard']) ? $translate_lang['dashboard']['dashboard'] : esc_html__('Dashboard', 'kc-lang'),
            'type' => 'route',
            'link' => 'dashboard',
            'iconClass' => 'fa fa-tachometer-alt',
            'routeClass' => 'dashboard',
        ],
        [
            'label' => isset($translate_lang['appointments']['appointments']) ? $translate_lang['appointments']['appointments'] : esc_html__('Appointments', 'kc-lang'),
            'type' => 'route',
            'link' => 'appointment-list.index',
            'iconClass' => 'fas fa-calendar-week',
            'routeClass' => 'appointment_list',
        ],
        [
            'label' => isset($translate_lang['patient_encounter']['encounters']) ? $translate_lang['patient_encounter']['encounters'] : esc_html__('Encounters', 'kc-lang'),
            'type' => 'parent',
            'link' => 'encounter',
            'iconClass' => 'far fa-calendar-times',
            'routeClass' => 'parent',
            'childrens' => [
                [
                    'label' => isset($translate_lang['patient_encounter']['encounters_list']) ? $translate_lang['patient_encounter']['encounters_list'] : esc_html__('Encounters', 'kc-lang'),
                    'type' => 'route',
                    'link' => 'encounter-list',
                    'iconClass' => 'far fa-calendar-times',
                    'routeClass' => 'patient_encounter_list',
                ],
                [
                    'label' => $translate_lang['encounter_template']['encounter_template'] ?? esc_html__('Encounter Templates', 'kc-lang'),
                    'type' => 'route',
                    'link' => 'encounter-template',
                    'iconClass' => 'far fa-calendar',
                    'routeClass' => 'encounters_template_list',
                ],
            ]
        ],
        [
            'label' => isset($translate_lang['dashboard']['patients']) ? $translate_lang['dashboard']['patients'] : esc_html__('Patients', 'kc-lang'),
            'type' => 'route',
            'link' => 'patient',
            'iconClass' => 'fas fa-hospital-user',
            'routeClass' => 'patient',
        ],
        [
            'label' => isset($translate_lang['common']['services']) ? $translate_lang['common']['services'] : esc_html__('Services', 'kc-lang'),
            'type' => 'route',
            'link' => 'service',
            'iconClass' => 'fa fa-server',
            'routeClass' => 'service',
        ],
        [
            'label' => isset($translate_lang['patient_bill']['billing_records']) ? $translate_lang['patient_bill']['billing_records'] : esc_html__('Billing records', 'kc-lang'),
            'type' => 'route',
            'link' => 'billings',
            'iconClass' => 'fa fa-file-invoice',
            'routeClass' => 'billings',
        ],
        [
            'label' => isset($translate_lang['common']['settings']) ? $translate_lang['common']['settings'] : esc_html__('Settings', 'kc-lang'),
            'type' => 'route',
            'link' => 'clinic.schedule',
            'iconClass' => 'fa fa-cogs',
            'routeClass' => 'clinic_settings'
        ]
    ];
    return $data;
}

function kcPatientSidebarArray()
{
    $translate_lang = require KIVI_CARE_DIR . 'resources/assets/lang/temp.php';
    $data = [
        [
            'label' => isset($translate_lang['widgets']['home']) ? $translate_lang['widgets']['home'] : esc_html__('Home', 'kc-lang'),
            'type' => 'href',
            'link' => get_home_url(),
            'iconClass' => 'fas fa-home',
            'routeClass' => 'home',
        ],
        [
            'label' => isset($translate_lang['dashboard']['dashboard']) ? $translate_lang['dashboard']['dashboard'] : esc_html__('Dashboard', 'kc-lang'),
            'type' => 'route',
            'link' => 'dashboard',
            'iconClass' => 'fa fa-tachometer-alt',
            'routeClass' => 'dashboard',
        ],
        [
            'label' => isset($translate_lang['appointments']['appointments']) ? $translate_lang['appointments']['appointments'] : esc_html__('Appointments', 'kc-lang'),
            'type' => 'route',
            'link' => 'appointment-list.index',
            'iconClass' => 'fas fa-calendar-week',
            'routeClass' => 'appointment_list',
        ],
        [
            'label' => isset($translate_lang['patient_encounter']['encounters']) ? $translate_lang['patient_encounter']['encounters'] : esc_html__('Encounters', 'kc-lang'),
            'type' => 'route',
            'link' => 'encounter-list',
            'iconClass' => 'far fa-calendar-times',
            'routeClass' => 'patient_encounter_list',
        ],
        [
            'label' => isset($translate_lang['patient_bill']['billing_records']) ? $translate_lang['patient_bill']['billing_records'] : esc_html__('Billing records', 'kc-lang'),
            'type' => 'route',
            'link' => 'billings',
            'iconClass' => 'fa fa-file-invoice',
            'routeClass' => 'billings',
        ],
        [
            'label' => isset($translate_lang['reports']['reports']) ? $translate_lang['reports']['reports'] : esc_html__('Reports', 'kc-lang'),
            'type' => 'route',
            'link' => 'patient-medical-report_id',
            'iconClass' => 'fa fa-file',
            'routeClass' => 'patient_medical',
        ],
    ];

    return $data;
}
function kcGetUserDefaultPermission($subscriber, $capability_type, $defaut_status)
{
    if (isset($subscriber->capabilities[$capability_type])) {
        return $subscriber->capabilities[$capability_type];
    }
    return $defaut_status;
}

function kcGetContactWithCountryCode($userType, $user_id, $key)
{
    $data = '';
    switch ($userType) {
        case 'doctor':
        case 'patient':
        case "clinic_admin":
        case 'receptionist':
        case 'common':
            $user_detail = !empty($user_id) ? json_decode(get_user_meta($user_id, 'basic_data', true)) : '';
            if (str_starts_with(ltrim($user_detail->{$key}), '+')) {
                $data = !empty($user_detail->{$key}) ? $user_detail->{$key} : '';
                return $data;
            } else {
                $country_calling_code = get_user_meta($user_id, 'country_calling_code', true);
                if (!empty($country_calling_code)) {
                    $data = !empty($user_detail->{$key}) ? '+' . $country_calling_code . $user_detail->{$key} : '';
                    return $data;
                } else {
                    $data = !empty($user_detail->{$key}) ? $user_detail->{$key} : '';
                    return $data;
                }
            }
    }
}


function kcUnauthorizeAccessResponse($status_code = '')
{
    $response = [
        'status' => false,
        'message' => esc_html__('You do not have permission to access', 'kc-lang'),
        'data' => []
    ];
    if (!empty($status_code)) {
        $response['status_code'] = $status_code;
    }
    return $response;
}

function kcThrowExceptionResponse($message, $status)
{
    return [
        'message' => $message,
        'status' => $status,
        'data' => []
    ];
}

function getProductIdOfServiceForMultipleBtn($id)
{
    global $wpdb;
    $id = (int) $id;
    $product_id = '';
    $appointments_service_table = $wpdb->prefix . 'kc_service_doctor_mapping';
    $data = $wpdb->get_var('select extra from ' . $appointments_service_table . ' where id=' . $id);
    if ($data != null) {
        $data = json_decode($data);
        $product_id = $data->product_id;
    }
    return $product_id;
}

add_action('init', function () {
    $kcBase = new KCBase();
    $current_user_role = $kcBase->getLoginUserRole();
    add_filter("kivicare_{$current_user_role}_dashboard_sidebar_data", function ($data) {
        return array_map(function ($row) {
            if ($row['type'] == 'parent') {
                $row['childrens'] = array_map(function ($children) {
                    $children['label'] = [
                        "encounter-list" => __("Encounters List", 'kc-lang'),
                        "encounter-template" => __("Encounter Templates", 'kc-lang'),
                    ][$children['link']];
                    return $children;
                }, $row['childrens']);
            }
            return $row;
        }, $data);
    });
});

function kc_get_multiple_option($options)
{
    global $wpdb;
    return collect($wpdb->get_results("SELECT option_name, option_value
    FROM {$wpdb->prefix}options
    WHERE option_name IN ({$options})"))->mapWithKeys(function ($item) {
        return [$item->option_name => $item->option_value];
    })->toArray();

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

function kcGetFormatedTime($time)
{
    $timeFormat = get_option('time_format');
    return date($timeFormat, strtotime($time));
}


function kcGetFormatedDateAndTime($date_time){

    // Get the WordPress timezone setting
    $timezone = wp_timezone();

    // Create a DateTime object with the given date and set the timezone to WordPress timezone
    $dateTime = new DateTime($date_time, $timezone);

    // Convert to timestamp
    $timestamp = $dateTime->getTimestamp();

    $dateFormat = get_option('date_format', true);
    $timeFormat = get_option('time_format');

    return wp_date("$dateFormat $timeFormat", $timestamp);
}

function kcGetCancellationBufferData($current_date, $appointment_start_date, $appointment_start_time)
{
    $cancellationBufferData = get_option(KIVI_CARE_PREFIX . 'appointment_cancellation_buffer', true);

    $cancellationBufferValue = ((gettype($cancellationBufferData) != 'boolean') && ($cancellationBufferData['status'] == 'on') && !empty($cancellationBufferData['time']['value'])) ? 60 * (float) $cancellationBufferData['time']['value'] : 0;

    // Convert appointment date and time to a unified format (timestamp)
    $appointment_datetime = strtotime("$appointment_start_date $appointment_start_time");

    // Subtract cancellation buffer value (in seconds)
    $buffer_in_seconds = $cancellationBufferValue * 60; // Convert minutes to seconds
    $new_appointment_datetime = $appointment_datetime - $buffer_in_seconds;

    // Convert back to human-readable format
    $new_appointment_date_time = date("F j, Y g:i A", $new_appointment_datetime);

    // Convert $new_appointment_date_time to a Unix timestamp
    $new_appointment_timestamp = strtotime($new_appointment_date_time);

    // Convert $current_date to a Unix timestamp
    $current_timestamp = strtotime($current_date);
    return ($current_timestamp < $new_appointment_timestamp) ? true : false;
}


function decodeSpecificSymbols($input)
{
    $decoded = html_entity_decode($input, ENT_QUOTES, 'UTF-8');

    return $decoded;
}

function kcEncounterPrintContent($themeColor, $data, $id, $current_user_role = '')
{
    if(empty($data->prescription)){
        ?>
        <p class="text-danger text-center"><?php echo esc_html__('No prescription found','kc-lang')?></p>
        <?php
    }else{
        kcEncounterPrintTableContent($themeColor, $data, $id, $current_user_role, false);
    }
}

/**
 * Filter to customize the list of custom forms based on the module type.
 *
 * @param array $response The original response array of custom forms.
 * @param array $data     Additional data including the 'type' of module.
 *
 * @return array The filtered list of custom forms.
 */
add_filter('kivicare_custom_form_list', function ($response, $data) {
    switch ($data['type']) {
        case 'appointment_module':
            // Filter custom forms for 'appointment_module' type.
            return collect((new KCCustomForm())->get_by(['module_type' => 'appointment_module', 'status' => 1]))
                ->filter(function ($v) {
                    $v->conditions = json_decode($v->conditions);
                    $v->fields = json_decode($v->fields);
                    $v->name = json_decode($v->name);
                    $v->clinic_ids = !empty($v->conditions->clinic_ids) ? collect($v->conditions->clinic_ids)->pluck('id')->toArray() : [];
                    $v->appointment_status = !empty($v->conditions->appointment_status) ? collect($v->conditions->appointment_status)->pluck('id')->toArray() : [];
                    $v->show_mode = !empty($v->conditions->show_mode) ? collect($v->conditions->show_mode)->pluck('id')->toArray() : [];
                    return !empty($v->conditions->show_mode) && in_array('appointment', $v->show_mode);
                })->toArray();
            break;
        case 'patient_encounter_module':
        case 'patient_module':
        case 'doctor_module':
            // Filter custom forms for 'patient_encounter_module', 'patient_module', or 'doctor_module' types.
            return collect((new KCCustomForm())->get_by(['module_type' => $data['type'], 'status' => 1]))
                ->filter(function ($v) {
                    $v->conditions = json_decode($v->conditions);
                    $v->fields = json_decode($v->fields);
                    $v->name = json_decode($v->name);
                    $v->clinic_ids = !empty($v->conditions->clinic_ids) ? collect($v->conditions->clinic_ids)->pluck('id')->toArray() : [];
                    return true;
                })->toArray();
            break;
        default:
            // For other module types, return the original response.
            return $response;
    }
}, 10, 2);


/**
 * Delete custom form data associated with a patient when the 'kc_patient_delete' action is triggered.
 *
 * @param int $id The ID of the patient to delete.
 */
add_action('kc_patient_delete', function ($id) {
    // Trigger the 'kivicare_custom_form_data_delete' action for the 'patient_module' with the patient's ID.
    do_action('kivicare_custom_form_data_delete', 'patient_module', $id);
});

/**
 * Delete custom form data associated with a doctor when the 'kc_doctor_delete' action is triggered.
 *
 * @param int $id The ID of the doctor to delete.
 */
add_action('kc_doctor_delete', function ($id) {
    // Trigger the 'kivicare_custom_form_data_delete' action for the 'doctor_module' with the doctor's ID.
    do_action('kivicare_custom_form_data_delete', 'doctor_module', $id);
});


/**
 * Delete custom form data associated with a module when the 'kivicare_custom_form_data_delete' action is triggered.
 *
 * @param string $module_type The type of module (e.g., 'patient_module', 'doctor_module').
 * @param int    $module_id   The ID of the module to delete custom form data for.
 */
add_action('kivicare_custom_form_data_delete', function ($module_type, $module_id) {
    // Check if both module_type and module_id are not empty.
    if (!empty($module_id) && !empty($module_type)) {
        global $wpdb;
        $module_type = esc_sql($module_type);
        $module_id = (int) $module_id;

        // Check if the table exists before attempting to delete data.
        $custom_form_data_table = $wpdb->prefix . 'kc_custom_form_data';
        $custom_forms_table = $wpdb->prefix . 'kc_custom_forms';
        if (($wpdb->get_var("SHOW TABLES LIKE '$custom_form_data_table'") === $custom_form_data_table) && ($wpdb->get_var("SHOW TABLES LIKE '$custom_forms_table'") === $custom_forms_table)) {
            // Table exists; proceed with the DELETE query.
            $wpdb->query("
                DELETE FROM {$wpdb->prefix}kc_custom_form_data
                WHERE form_id IN (
                    SELECT id FROM {$wpdb->prefix}kc_custom_forms
                    WHERE module_type = '{$module_type}'
                ) AND module_id = {$module_id};
            ");
        }
    }
}, 10, 2);

// add_filter('woocommerce_order_item_get_formatted_meta_data', 'custom_hide_order_item_meta', 10, 2);

// function custom_hide_order_item_meta($formatted_meta, $item) {
//     // Define meta keys to hide
//     $meta_keys_to_hide = ['kivicare_appointment_id', 'doctor_id'];

//     foreach ($formatted_meta as $key => $meta) {
//         if (in_array($meta->key, $meta_keys_to_hide)) {
//             unset($formatted_meta[$key]);
//         }
//     }

//     return $formatted_meta;
// }

add_filter('woocommerce_order_item_display_meta_key', 'replace_meta_key_labels', 10, 2);
add_filter('woocommerce_order_item_display_meta_value', 'replace_meta_value_with_doctor_name', 10, 2);

function replace_meta_key_labels($display_key, $meta) {
    if ($display_key === 'kivicare_appointment_id') {
        $display_key = __('Appointment ID', 'kc-lang'); 
    }
    if ($display_key === 'doctor_id') {
        $display_key = __('Doctor Name', 'kc-lang');
    }
    return $display_key;
}

function replace_meta_value_with_doctor_name($display_value, $meta) {
    if ($meta->key === 'doctor_id') {
        $doctor_id = $display_value; // The stored doctor ID

        $doctor_data = get_userdata($doctor_id);

        if ($doctor_data) {
            $doctor_name = $doctor_data->display_name; // Get the display name
        }

        if ($doctor_name) {
            $display_value = $doctor_name; // Replace the value with the doctor's name
        }
    }
    return $display_value;
}

