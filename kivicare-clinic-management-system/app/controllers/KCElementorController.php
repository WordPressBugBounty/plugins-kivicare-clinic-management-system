<?php


namespace App\Controllers;
use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;

class KCElementorController extends KCBase
{

    public $db;

    private $request;

    public function __construct()
    {

        global $wpdb;

        $this->db = $wpdb;

        $this->request = new KCRequest();
        parent::__construct();

    }

    public function doctorIndex()
    {

        $request_data = $this->request->getInputs();
        if (empty($request_data['setting']) || !(is_array($request_data['setting']))) {
            wp_send_json([
                'status' => false,
                'message' => esc_html__('Setting data not found', 'kc-lang')
            ]);
        }
        $setting = $request_data['setting'];
        ob_start();

        $default_clinic_id = kcGetDefaultClinicId();
        $clinic_id = isKiviCareProActive() ? (!empty($setting['iq_kivivare_doctor_clinic_id']) ? $setting['iq_kivivare_doctor_clinic_id'] : $default_clinic_id) : $default_clinic_id;
        $perPage = !empty($setting['iq_kivivare_doctor_par_page']) ? $setting['iq_kivivare_doctor_par_page'] : 1;
        $filter_data = $request_data['filter_data_and_pagination'];
        $isSpecificDoctorRequired = $setting['iq_kivivare_specific_doctor'] === 'yes';
        $doctor_results = $isSpecificDoctorRequired
            ? $this->clinicWiseDoctor($clinic_id, $request_data, $setting['selected_doctors'])
            : $this->clinicWiseDoctor($clinic_id, $request_data);

        $doctors = $doctor_results['current_page'];
        $doctor_count = count($doctors);
        $nextPageDoctorCount = $doctor_results['next_page'];
        $pageNo = !empty($filter_data['page']) && (int) $filter_data['page'] > 1 ? (int) $filter_data['page'] : 1;
        if ($doctor_count < 1) {
            ?>
            <div class="kivicare-doctor-card body">
                <div class="elementor-shortcode" style="text-align: center"> <?php echo esc_html__('No Doctor Found', 'kc-lang'); ?>
                </div>
            </div>
            <?php
        } else {
            foreach ($doctors as $key => $doctor) {
                $id = $doctor->ID;
                $doctors_sessions = doctorWeeklyAvailability(['clinic_id' => $clinic_id, 'doctor_id' => $id]);
                $allUserMeta = get_user_meta($id);
                $user_image_url = !empty($allUserMeta['doctor_profile_image'][0]) ? wp_get_attachment_url($allUserMeta['doctor_profile_image'][0]) : KIVI_CARE_DIR_URI . '/assets/images/kc-demo-img.png';
                $user_data = !empty($allUserMeta['basic_data'][0]) ? json_decode($allUserMeta['basic_data'][0]) : (object) [];
                //$description = !empty($allUserMeta['doctor_description'][0]) ? $allUserMeta['doctor_description'][0] : '';
                $class_doctor_column = 'doctor_column_with_gap';
                $class_doctor_card = 'doctor_card_with_gap';
                if ($setting['iq_kivivare_doctor_gap_between_card'] === 'yes') {
                    $class_doctor_column = '';
                    $class_doctor_card = '';
                    if ($key === 0) {
                        $class_doctor_column = 'first_doctor_column';
                        $class_doctor_card = 'first_doctor_card';
                    } elseif (($key + 1) == $doctor_count) {
                        $class_doctor_column = 'last_doctor_column';
                        $class_doctor_card = 'last_doctor_card';
                    }
                }
                if ($perPage == 1) {
                    $class_doctor_column = 'doctor_column_with_gap';
                    $class_doctor_card = 'doctor_card_with_gap';
                }
                ?>
                <div class="kivicare-doctor-card body <?php echo esc_html($class_doctor_card); ?>">
                    <div class="column-doctor <?php echo esc_html($class_doctor_column); ?>">
                        <div class="image">
                            <?php if ($setting['iq_kivivare_doctor_image'] === 'yes') { ?>
                                <img src="<?php echo esc_url($user_image_url); ?>" class="img kivicare-doctor-avtar" alt="doctor_image">
                            <?php } ?>
                        </div>
                        <div class="details">
                            <?php if ($setting['iq_kivivare_doctor_name'] === 'yes') { ?>
                                <div class="header">
                                    <div>
                                        <h1 class="iq_kivicare_doctor_name-label heading1">
                                            <?php echo esc_html($setting['iq_kivivare_doctor_name_label']); ?>
                                        </h1>
                                        <h1 class="iq_kivicare_doctor_name-value heading1"><?php echo esc_html($doctor->display_name); ?>
                                        </h1>
                                    </div>
                                    <div>
                                        <?php if ($setting['iq_kivivare_doctor_speciality'] === 'yes') { ?>
                                            <h4 class="heading4 iq_kivicare_doctor_speciality-label">
                                                <?php echo esc_html($setting['iq_kivivare_doctor_speciality_label']); ?>
                                            </h4>
                                            <?php if (!empty($user_data->specialties) && is_array($user_data->specialties)) {
                                                ?>
                                                <h4 class="heading4 iq_kivicare_doctor_speciality-value">
                                                    <?php echo esc_html(collect($user_data->specialties)->pluck('label')->implode(', ')); ?>
                                                </h4>
                                                <?php
                                            }
                                            ?>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php
                            }
                            if ($setting['iq_kivivare_doctor_number'] === 'yes') {
                                ?>
                                <div class="flex-container numup">
                                    <div class="detail-header">
                                        <h3 class="iq_kivicare_doctor_number-label heading3">
                                            <?php echo esc_html($setting['iq_kivivare_doctor_number_label']); ?>
                                        </h3>
                                    </div>
                                    <div class="detail-data">
                                        <p class="iq_kivicare_doctor_number-value paragraph">
                                            <?php echo esc_html(!empty($user_data->mobile_number) ? $user_data->mobile_number : ''); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php
                            }
                            if ($setting['iq_kivivare_doctor_email'] === 'yes') {
                                ?>
                                <div class="flex-container">
                                    <div class="detail-header">
                                        <h3 class="iq_kivicare_doctor_email-label heading3">
                                            <?php echo esc_html($setting['iq_kivivare_doctor_email_label']); ?>
                                        </h3>
                                    </div>
                                    <div class="detail-data">
                                        <p class="iq_kivicare_doctor_email-value paragraph">
                                            <?php echo esc_html(!empty($doctor->user_email) ? $doctor->user_email : ''); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php
                            }
                            if ($setting['iq_kivivare_doctor_qualification'] === 'yes') {
                                ?>
                                <div class="flex-container">
                                    <div class="detail-header">
                                        <h3 class="iq_kivicare_doctor_qualification-label heading3">
                                            <?php echo esc_html($setting['iq_kivivare_doctor_qualification_label']); ?>
                                        </h3>
                                    </div>
                                    <?php
                                    $qual = esc_html__('NA', 'kc-lang');
                                    if (!empty($user_data->qualifications) && is_array($user_data->qualifications)) {
                                        $qual = collect($user_data->qualifications)->map(function ($v) {
                                            return $v->degree . '(' . $v->university . '-' . $v->year . ')';
                                        })->implode(', ');
                                        ?>
                                        <?php
                                    } ?>
                                    <div class="detail-data">
                                        <p class="iq_kivicare_doctor_qualification-value paragraph"><?php echo esc_html($qual); ?></p>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                            <div class="">
                                <?php
                                if ($setting['iq_kivivare_doctor_session'] === 'yes') { ?>
                                    <div class="appoin">
                                        <span
                                            class="schedule iq_kivicare_doctor_session-label"><?php echo esc_html($setting['iq_kivivare_doctor_session_label']); ?></span>
                                    </div>
                                    <div class="grid-container iq_kivicare_doctor_session-container">
                                        <?php
                                        $weekdays = array(
                                            'mon' => 1,
                                            'tue' => 2,
                                            'wed' => 3,
                                            'thu' => 4,
                                            'fri' => 5,
                                            'sat' => 6,
                                            'sun' => 0,
                                        );
                                        global $wp_locale;
                                        if (!empty($doctors_sessions) && is_array($doctors_sessions) && count($doctors_sessions) > 0) {

                                            foreach ($doctors_sessions as $key => $value) {
                                                ?>
                                                <div class="iq_kivicare_doctor_session-cell">
                                                    <p class="paragraph iq_kivicare_doctor_session-cell_title day">
                                                        <?php echo esc_html(isset($value[0]['day']) ? $wp_locale->get_weekday_initial($wp_locale->get_weekday($weekdays[$value[0]['day']])) : '') ?>
                                                    </p>
                                                    <?php if (isset($value[0]['start_time'])) { ?>
                                                        <p class="paragraph iq_kivicare_doctor_session-cell_value time">
                                                            <?php
                                                            /*Translator: date text */
                                                            echo esc_html((isset($value[0]['start_time']) ? esc_html__('Morning : ', 'kc-lang') . esc_html(date('H:i ', strtotime($value[0]['start_time']))) : '') . ' - ' . (isset($value[0]['end_time']) ? esc_html(date('H:i ', strtotime($value[0]['end_time']))) : ''));
                                                            ?>
                                                        </p>
                                                    <?php }
                                                    if (isset($value[1]['start_time'])) { ?>
                                                        <p class="paragraph iq_kivicare_doctor_session-cell_value time">
                                                            <?php
                                                            /*Translator: date text */
                                                            echo esc_html((isset($value[1]['start_time']) ? esc_html__('Evening : ', 'kc-lang') . esc_html(date('H:i ', strtotime($value[1]['start_time']))) : '') . ' - ' . (isset($value[1]['end_time']) ? esc_html(date('H:i ', strtotime($value[1]['end_time']))) : '')); ?>
                                                        </p>
                                                    <?php } ?>
                                                </div>
                                                <?php
                                            }
                                        } else {

                                            foreach ($weekdays as $days) {
                                                ?>
                                                <div class="iq_kivicare_doctor_session-cell">
                                                    <p class="paragraph iq_kivicare_doctor_session-cell_title day">
                                                        <?php echo esc_html($wp_locale->get_weekday_initial($wp_locale->get_weekday($days))); ?>
                                                    </p>
                                                    <p class="paragraph iq_kivicare_doctor_session-cell_value time">
                                                        <?php echo esc_html__('Morning : NA ', 'kc-lang'); ?>
                                                    </p>
                                                    <p class="paragraph iq_kivicare_doctor_session-cell_value time">
                                                        <?php echo esc_html__('Evening : NA ', 'kc-lang'); ?>
                                                    </p>
                                                </div>
                                                <?php
                                            }
                                        }
                                        ?>
                                    </div>
                                <?php }
                                ?>
                                <div class="book">
                                    <button clinic_id="<?php echo esc_html($clinic_id); ?>" doctor_id="<?php echo esc_html($id); ?>"
                                        type="button" class="book_button appointment_button kivicare_elementor_popup_appointment_book">
                                        <?php echo esc_html__('Book Appointment', 'kc-lang'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php echo $setting['iq_kivivare_doctor_gap_between_card'] === 'yes' ? '' : "<br>"; ?>
            <?php
            }
        }
        ?>
        <div class="kivi-pagination">
            <a>
                <input style="<?php echo esc_html((int) $pageNo > 1 || !kcNotInPreviewmode() ? '' : 'display:none;'); ?>"
                    class="iq_kivicare_next_previous book_button" type="button" name="previous"
                    value="<?php echo esc_html__('Previous', 'kc-lang') ?>" kc_page="<?php echo esc_html((int) $pageNo - 1) ?>">
            </a>
            <a>
                <input style="<?php echo esc_html($nextPageDoctorCount > 0 || !kcNotInPreviewmode() ? '' : 'display:none;'); ?>"
                    class="iq_kivicare_next_previous book_button" type="button" name="next"
                    value="<?php echo esc_html__('Next', 'kc-lang') ?>" kc_page="<?php echo esc_html((int) $pageNo + 1) ?>">
            </a>
        </div>
        <?php
        $html = ob_get_clean();
        wp_send_json([
            'status' => true,
            'data' => $html
        ]);
    }

    public function clinicWiseDoctor($clinic_id, $request_data, $selected_doctors = null)
    {
        $setting = $request_data['setting'];
        $filter_data = $request_data['filter_data_and_pagination'];
        $perPage = !empty($setting['iq_kivivare_doctor_par_page']) ? $setting['iq_kivivare_doctor_par_page'] : 1;
        $clinic_id = (int) $clinic_id;
        $page = !empty($filter_data['page']) && (int) $filter_data['page'] > 1 ? (int) $filter_data['page'] : 1;
        $args['role'] = $this->getDoctorRole();
        $args['page'] = $page;
        $args['number'] = (int) $perPage;
        $args['offset'] = ($page - 1) * (int) $perPage;
        $args['user_status'] = 0;
        $args['fields'] = ['ID', 'display_name', 'user_email'];
        global $wpdb;
        $doctor_mapping_table = $wpdb->prefix . 'kc_doctor_clinic_mappings where clinic_id=' . $clinic_id;
        $doctor_mapping_query = "SELECT * FROM {$doctor_mapping_table}";
        if (isKiviCareProActive()) {
            if (empty($clinic_id)) {
                return [];
            }
            $doctor_clinic_wise = $selected_doctors ? $selected_doctors : collect($wpdb->get_results($doctor_mapping_query))->pluck('doctor_id')->toArray();
            $args['include'] = !empty($doctor_clinic_wise) ? $doctor_clinic_wise : [-1];
        }
        if (!empty($filter_data['service'])) {
            global $wpdb;
            $query = "SELECT ser_map.doctor_id FROM {$wpdb->prefix}kc_service_doctor_mapping AS ser_map 
                      JOIN {$wpdb->prefix}kc_services AS ser ON ser.id = ser_map.service_id WHERE ser_map.clinic_id={$clinic_id} AND ser.name lIKE '%" . esc_sql(trim($filter_data['service'])) . "%'";
            if (!empty($args['include'])) {
                $doctor_list = implode(",", $args['include']);
                $query .= " AND ser_map.doctor_id IN ({$doctor_list})";
            }
            $doctor_id_service_wise = collect($wpdb->get_results($query))->pluck('doctor_id')->toArray();
            $args['include'] = !empty($doctor_id_service_wise) ? $doctor_id_service_wise : [-1];
        }

        if (!empty($filter_data['speciality'])) {
            $args['meta_query'] = [
                [
                    'key' => 'basic_data',
                    'value' => esc_sql(trim($filter_data['speciality'])),
                    'compare' => 'LIKE'
                ]
            ];
        }
        if (!empty($filter_data['search'])) {
            $args['search_columns'] = ['user_email', 'ID', 'display_name'];
            $args['search'] = '*' . esc_sql(strtolower(trim($filter_data['search']))) . '*';
        }

        $doctors = collect(get_users($args))->toArray();

        //Next page
        $page += 1;
        $args['page'] = $page;
        $args['number'] = $perPage;
        $args['offset'] = ($page - 1) * (int) $perPage;
        $args['fields'] = ['ID'];
        $doctors_next_page = get_users($args);

        return [
            'current_page' => $doctors,
            'next_page' => count($doctors_next_page)
        ];

    }

    public function clinicIndex()
    {

        $request_data = $this->request->getInputs();
        if (empty($request_data['setting']) || !(is_array($request_data['setting']))) {
            wp_send_json([
                'status' => false,
                'message' => esc_html__('Setting data not found', 'kc-lang')
            ]);
        }
        $setting = $request_data['setting'];
        $filter_data = $request_data['filter_data_and_pagination'];
        $perPage = !empty($setting['iq_kivivare_clinic_per_page']) ? $setting['iq_kivivare_clinic_per_page'] : 1;
        $clinics_list = $this->clinicListData($request_data);
        $clinics = $clinics_list['current_page'];
        $nextPageClinicCount = $clinics_list['next_page'];
        $pageNo = !empty($filter_data['page']) && (int) $filter_data['page'] > 1 ? (int) $filter_data['page'] : 1;

        ob_start();
        if (count($clinics) < 1) {
            ?>
            <div class="kivicare-doctor-card body">
                <div class="elementor-shortcode" style="text-align:center;"> <?php echo esc_html__('No Clinic Found', 'kc-lang'); ?>
                </div>
            </div>
            <?php
        } else {
            foreach ($clinics as $key => $clinic) {
                $id = $clinic->id;
                $image_attachment_id = $clinic->profile_image;
                $user_image_url = wp_get_attachment_url($image_attachment_id);
                $clinic_name = $clinic->name;
                $clinic_admin_data = get_user_meta($clinic->clinic_admin_id, 'basic_data', true);
                $clinic_admin_data = json_decode($clinic_admin_data);
                ?>
                <div class="kivicare-doctor-card body">
                    <div class="column">
                        <div class="image">
                            <?php if ($setting['iq_kivivare_clinic_image'] === 'yes') { ?>
                                <img src="<?php echo esc_url(!empty($user_image_url) ? $user_image_url : KIVI_CARE_DIR_URI . '/assets/images/kc-demo-img.png');
                                ; ?>" class="img kivicare-doctor-avtar">
                            <?php } ?>
                        </div>
                        <div class="details">
                            <?php if ($setting['iq_kivivare_clinic_name'] === 'yes') { ?>
                                <div class="header">
                                    <div class="">
                                        <h1 class="iq_kivicare_doctor_name-label heading1">
                                            <?php echo esc_html($setting['iq_kivivare_clinic_name_label']); ?>
                                        </h1>
                                        <h1 class="iq_kivicare_doctor_name-value heading1"><?php echo esc_html($clinic_name); ?></h1>
                                    </div>
                                    <div class="">
                                        <?php if ($setting['iq_kivivare_clinic_speciality'] === 'yes') { ?>
                                            <h4 class="heading4 iq_kivicare_doctor_speciality-label">
                                                <?php echo esc_html($setting['iq_kivivare_clinic_speciality_label']); ?>
                                            </h4>
                                            <?php
                                            if (!empty($clinic->specialties) && is_array(json_decode($clinic->specialties))) {
                                                ?>
                                                <h4 class="heading4 iq_kivicare_doctor_speciality-value">
                                                    <?php echo esc_html(collect(json_decode($clinic->specialties))->pluck('label')->implode(', ')); ?>
                                                </h4>
                                                <?php
                                            }
                                            ?>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php
                            }
                            if ($setting['iq_kivivare_clinic_number'] === 'yes') {
                                ?>
                                <div class="flex-container numup">
                                    <div class="detail-header">
                                        <h3 class="iq_kivicare_doctor_number-label heading3">
                                            <?php echo esc_html($setting['iq_kivivare_clinic_number_label']); ?>
                                        </h3>
                                    </div>
                                    <div class="detail-data">
                                        <p class="iq_kivicare_doctor_number-value paragraph">
                                            <?php echo esc_html(!empty($clinic->telephone_no) ? $clinic->telephone_no : ''); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php
                            }
                            if ($setting['iq_kivivare_clinic_email'] === 'yes') {
                                ?>
                                <div class="flex-container">
                                    <div class="detail-header">
                                        <h3 class="iq_kivicare_doctor_email-label heading3">
                                            <?php echo esc_html($setting['iq_kivivare_clinic_email_label']); ?>
                                        </h3>
                                    </div>
                                    <div class="detail-data">
                                        <p class="iq_kivicare_doctor_email-value paragraph">
                                            <?php echo esc_html(!empty($clinic->email) ? $clinic->email : ''); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php
                            }
                            if ($setting['iq_kivivare_clinic_address'] === 'yes') {
                                ?>
                                <div class="flex-container clinic-address">
                                    <div class="detail-header">
                                        <h3 class="iq_kivicare_doctor_address-label heading3">
                                            <?php echo esc_html($setting['iq_kivivare_clinic_address_label']); ?>
                                        </h3>
                                    </div>
                                    <div class="detail-data">
                                        <p class="iq_kivicare_doctor_address-value paragraph">
                                            <?php echo esc_html((!empty($clinic->address) ? $clinic->address : '')); ?>
                                        </p>
                                        <p class="iq_kivicare_doctor_address-value paragraph">
                                            <?php echo esc_html((!empty($clinic->city) ? $clinic->city : '') . (!empty($clinic->city) ? ', ' : '') . (!empty($clinic->country) ? $clinic->country : '') . (!empty($clinic->country) ? ', ' : '')); ?>
                                        </p>
                                        <p class="iq_kivicare_doctor_address-value paragraph">
                                            <?php echo esc_html((!empty($clinic->postal_code) ? $clinic->postal_code : '')); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php
                            }
                            if ($setting['iq_kivivare_clinic_administrator'] === 'yes') {
                                ?>
                                <div class="appoin">
                                    <span
                                        class="schedule iq_kivicare_doctor_session-label iq_kivicare_doctor_administrator-label"><?php echo esc_html($setting['iq_kivivare_clinic_administrator_label']); ?></span>
                                </div>
                                <?php
                            }
                            if ($setting['iq_kivivare_clinic_admin_number'] === 'yes') {
                                ?>
                                <div class="flex-container numup">
                                    <div class="detail-header">
                                        <h3 class="iq_kivicare_doctor_admin_number-label heading3">
                                            <?php echo esc_html($setting['iq_kivivare_clinic_admin_number_label']); ?>
                                        </h3>
                                    </div>
                                    <div class="detail-data">
                                        <p class="iq_kivicare_doctor_admin_number-value paragraph">
                                            <?php echo esc_html(!empty($clinic_admin_data->mobile_number) ? $clinic_admin_data->mobile_number : ''); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php
                            }
                            if ($setting['iq_kivivare_clinic_admin_email'] === 'yes') {
                                ?>
                                <div class="flex-container">
                                    <div class="detail-header">
                                        <h3 class="iq_kivicare_doctor_admin_email-label heading3">
                                            <?php echo esc_html($setting['iq_kivivare_clinic_admin_email_label']); ?>
                                        </h3>
                                    </div>
                                    <div class="detail-data">
                                        <p class="iq_kivicare_doctor_admin_email-value paragraph">
                                            <?php echo esc_html(!empty($clinic_admin_data->user_email) ? $clinic_admin_data->user_email : ''); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                            <div class="book">
                                <button type="button" clinic_id="<?php echo esc_html($id); ?>"
                                    class="book_button appointment_button kivicare_elementor_clinic_popup_appointment_book"><?php echo esc_html__('Book Appointment', 'kc-lang'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php echo $setting['iq_kivivare_clinic_gap_between_card'] === 'yes' ? '' : "<br>";
            }
        }
        ?>
        <div class="kivi-pagination">
            <a>
                <input style="<?php echo esc_html($pageNo > 1 || !kcNotInPreviewmode() ? '' : 'display:none;'); ?>"
                    class="iq_kivicare_next_previous book_button" type="button" name="previous"
                    value="<?php echo esc_html__('Previous', 'kc-lang') ?>" kc_page="<?php echo esc_html((int) $pageNo - 1) ?>">
            </a>
            <a>
                <input style="<?php echo esc_html($nextPageClinicCount > 0 || !kcNotInPreviewmode() ? '' : 'display:none;'); ?>"
                    class="iq_kivicare_next_previous book_button" type="button" name="next"
                    value="<?php echo esc_html__('Next', 'kc-lang') ?>" kc_page="<?php echo esc_html((int) $pageNo + 1) ?>">
            </a>
        </div>
        <?php
        wp_send_json(['status' => true, 'data' => ob_get_clean()]);
    }
    public function clinicListData($request_data)
    {
        $setting = $request_data['setting'];
        $filter_data = $request_data['filter_data_and_pagination'];
        $perPage = !empty($setting['iq_kivivare_clinic_per_page']) ? $setting['iq_kivivare_clinic_per_page'] : 1;
        $exclude_clinic = [0];
        if (isKiviCareProActive() && !empty($setting['iq_kivivare_clinic_exclude_clinic']) && $setting['iq_kivivare_clinic_exclude_clinic'] === 'yes') {
            if (!empty($setting['iq_kivivare_clinic_exclude_clinic_list']) && count($setting['iq_kivivare_clinic_exclude_clinic_list'])) {
                $exclude_clinic = $setting['iq_kivivare_clinic_exclude_clinic_list'];
            }
        }
        $pageNo = !empty($filter_data['page']) && (int) $filter_data['page'] > 1 ? (int) $filter_data['page'] : 1;
        $perPage = (int) $perPage;
        $page = ($pageNo - 1) * $perPage;
        $limit = ' limit ' . $perPage;
        $offset = ' OFFSET ' . $page;
        $conditions = ' WHERE 0=0 && status = 1';
        global $wpdb;
        $clinic_table = $wpdb->prefix . 'kc_clinics';
        $exclude_clinic_service_table_condition = " ";
        if (!isKiviCareProActive()) {
            $conditions .= ' AND id=' . kcGetDefaultClinicId() . ' ';
        } else {
            if (!empty($exclude_clinic) && count($exclude_clinic)) {
                $exclude_clinic = array_map('absint', $exclude_clinic);
                $conditions .= ' AND id NOT IN (' . implode(',', $exclude_clinic) . ') ';
                $exclude_clinic_service_table_condition = ' AND ser_map.clinic_id NOT IN (' . implode(',', $exclude_clinic) . ') ';
            }
            if (!empty($filter_data['service'])) {
                $service_query_value = esc_sql($filter_data['service']);

                $service_query = "SELECT doc_ser.clinic_id as clinic_id FROM {$wpdb->prefix}kc_service_doctor_mapping AS doc_ser  
                                    LEFT JOIN {$wpdb->prefix}kc_services AS  ser ON ser.id=doc_ser.service_id 
                                    WHERE ser.name LIKE '%{$service_query_value}%' {$exclude_clinic_service_table_condition} ";

                $clinic_id_service_wise = collect($wpdb->get_results($service_query))->unique('clinic_id')->pluck('id')->toArray();
                $clinic_id_service_wise = !empty($clinic_id_service_wise) ? $clinic_id_service_wise : [-1];
                $clinic_id_service_wise = implode(',', $clinic_id_service_wise);
                $conditions .= " AND id IN ({$clinic_id_service_wise}) ";
            }
        }
        if (!empty($filter_data['speciality'])) {
            $speciality = esc_sql($filter_data['speciality']);
            $conditions .= " AND specialties LIKE '%{$speciality}%' ";
        }
        if (!empty($filter_data['search'])) {
            $search = esc_sql($filter_data['search']);
            $conditions .= " AND (name LIKE '%{$search}%' OR email LIKE '%{$search}%' OR telephone_no LIKE '%{$search}%' OR address LIKE '%{$search}%') ";
        }


        $clinic_query = "SELECT * FROM {$clinic_table} {$conditions}{$limit} {$offset}";
        $clinic_list = $wpdb->get_results($clinic_query);

        //next page requests data
        $pageNo += 1;
        $perPage = (int) $perPage;
        $page = ($pageNo - 1) * $perPage;
        $limit = ' limit ' . $perPage;
        $offset = ' OFFSET ' . $page;
        $clinic_query = "SELECT id FROM {$clinic_table} {$conditions}{$limit} {$offset}";
        $clinic_next_page_list = $wpdb->get_results($clinic_query);
        $next_count = !empty($clinic_next_page_list) ? count($clinic_next_page_list) : 0;
        return [
            'current_page' => $clinic_list,
            'next_page' => $next_count
        ];
    }
}