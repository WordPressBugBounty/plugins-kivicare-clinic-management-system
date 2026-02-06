<?php

namespace App\emails;

use App\baseClasses\KCErrorLogger;
use App\baseClasses\KCNotificationDynamicKeys;
use WP_Post;
use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Email Template Manager - Handles template creation, retrieval, and management
 */
class KCEmailTemplateManager
{
    private string $prefix;
    private string $mailTemplatePostType;
    private string $gcalTemplatePostType;
    private string $gmeetTemplatePostType;
    private static $instance = null;
    private KCNotificationDynamicKeys $dynamicKeys;


    public function __construct()
    {
        $this->prefix = KIVI_CARE_PREFIX;
        $this->mailTemplatePostType = $this->prefix . 'mail_tmp';
        $this->gcalTemplatePostType = $this->prefix . 'gcal_tmp';
        $this->gmeetTemplatePostType = $this->prefix . 'gmeet_tmp';
        $this->dynamicKeys = new KCNotificationDynamicKeys();


        $this->init();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize hooks and actions
     */
    private function init(): void
    {
        add_action('init', [$this, 'registerPostTypes']);
    }

    /**
     * Register custom post types for email templates
     */
    public function registerPostTypes(): void
    {
        // Email Templates
        register_post_type($this->mailTemplatePostType, [
            'labels' => [
                'name' => 'KiviCare Email Templates',
                'singular_name' => 'Email Template',
                'add_new' => 'Add New Email Template',
                'add_new_item' => 'Add New Email Template',
                'edit_item' => 'Edit Email Template',
                'new_item' => 'New Email Template',
                'view_item' => 'View Email Template',
                'search_items' => 'Search Email Templates',
                'not_found' => 'No email templates found',
                'not_found_in_trash' => 'No email templates found in trash'
            ],
            'public' => true,
            'has_archive' => false,
            'rewrite' => ['slug' => 'kivicaremail'],
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'author'],
            'description' => esc_html__('Custom KiviCare Email Templates', 'kivicare-clinic-management-system'),
            'show_ui' => false,
            'show_in_menu' => false,
            'map_meta_cap' => true,
            'capability_type' => 'post',
        ]);

        // Google Calendar Templates
        register_post_type($this->gcalTemplatePostType, [
            'labels' => [
                'name' => 'KiviCare Google Calendar Templates',
                'singular_name' => 'Google Calendar Template'
            ],
            'public' => true,
            'has_archive' => false,
            'rewrite' => ['slug' => 'kivicaregoogleevent'],
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'author'],
            'description' => esc_html__('Custom KiviCare Google Calendar Templates', 'kivicare-clinic-management-system'),
            'show_ui' => false,
            'show_in_menu' => false,
            'map_meta_cap' => true,
            'capability_type' => 'post',
        ]);

        // Google Meet Templates
        register_post_type($this->gmeetTemplatePostType, [
            'labels' => [
                'name' => 'KiviCare Google Meet Templates',
                'singular_name' => 'Google Meet Template'
            ],
            'public' => true,
            'has_archive' => false,
            'rewrite' => ['slug' => 'kivicaregooglemeetevent'],
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'author'],
            'description' => esc_html__('Custom KiviCare Google Meet Templates', 'kivicare-clinic-management-system'),
            'show_ui' => false,
            'show_in_menu' => false,
            'map_meta_cap' => true,
            'capability_type' => 'post',
        ]);
    }

    /**
     * Get default email templates data
     */
    public function getDefaultTemplatesData(string $type = 'mail'): array
    {
        // $templatePostType = $type === 'sms' ? $this->smsTemplatePostType : $this->mailTemplatePostType;
        switch ($type) {
            case 'gmeet':
                $templatePostType = $this->gmeetTemplatePostType;
                break;

            case 'gcal':
                $templatePostType = $this->gcalTemplatePostType;
                break;

            case 'mail':
                $templatePostType = $this->mailTemplatePostType;
                break;
        }

        if (empty($templatePostType)) {
            return [];
        }

        $data = [
            [
                'post_name' => $this->prefix . 'patient_register',
                'post_content' => '<p>Welcome to KiviCare,</p><p>Your registration process with {{user_email}} is successfully completed, and your password is {{user_password}}</p><p>Thank you.</p>',
                'post_title' => 'Patient Registration Template',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'receptionist_register',
                'post_content' => '<p>Welcome to KiviCare,</p><p>Your registration process with {{user_email}} is successfully completed, and your password is {{user_password}}</p><p>Thank you.</p>',
                'post_title' => 'Receptionist Registration Template',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'doctor_registration',
                'post_content' => '<p>Welcome to KiviCare,</p><p>You are successfully registered</p><p>Your email: {{user_email}}, username: {{user_name}} and password: {{user_password}}</p><p>Thank you.</p>',
                'post_title' => 'Doctor Registration Template',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'doctor_book_appointment',
                'post_content' => '<p>New appointment</p><p>You have new appointment on</p><p>Date: {{appointment_date}}, Time: {{appointment_time}}, Patient: {{patient_name}}</p><p>Thank you.</p>',
                'post_title' => 'Doctor Booked Appointment Template',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'resend_user_credential',
                'post_content' => '<p>Welcome to KiviCare,</p><p>Your KiviCare account user credentials</p><p>Your email: {{user_email}}, username: {{user_name}} and password: {{user_password}}</p><p>Thank you.</p>',
                'post_title' => 'Resend User Credentials',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'cancel_appointment',
                'post_content' => '<p>Welcome to KiviCare,</p><p>Your appointment booking is cancelled.</p><p>Date: {{appointment_date}}, Time: {{appointment_time}}</p><p>Clinic: {{clinic_name}} Doctor: {{doctor_name}}</p><p>Thank you.</p>',
                'post_title' => 'Cancel Appointment',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'zoom_link',
                'post_content' => '<p>Zoom video conference</p><p>Your have new appointment on</p><p>Date: {{appointment_date}}, Time: {{appointment_time}}, Doctor: {{doctor_name}}, Zoom Link: {{zoom_link}}</p><p>Thank you.</p>',
                'post_title' => 'Video Conference Appointment Template',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'add_doctor_zoom_link',
                'post_content' => '<p>Zoom video conference</p><p>Your have new appointment on</p><p>Date: {{appointment_date}}, Time: {{appointment_time}}, Patient: {{patient_name}}, Zoom Link: {{add_doctor_zoom_link}}</p><p>Thank you.</p>',
                'post_title' => 'Doctor Zoom Video Conference Appointment Template',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'book_appointment',
                'post_content' => '<p>Dear {{patient_name}},</p><p>Your appointment has been successfully booked!</p><p><strong>Appointment Details:</strong></p><p>Date: {{appointment_date}}<br>Time: {{appointment_time}}<br>Doctor: {{doctor_name}}<br>Service: {{service_name}}<br>Total Amount: {{total_amount}}</p><p>Clinic: {{clinic_name}}<br>Address: {{clinic_address}}</p><p>Thank you for choosing KiviCare.</p>',
                'post_title' => 'Patient Appointment Booking Confirmation',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'payment_confirmation',
                'post_content' => '<p>Dear {{patient_name}},</p><p>Your payment has been successfully processed.</p><p><strong>Payment Details:</strong></p><p>Amount: {{total_amount}}<br>Appointment Date: {{appointment_date}}<br>Service: {{service_name}}</p><p>Thank you for your payment.</p>',
                'post_title' => 'Payment Confirmation',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'meet_link',
                'post_content' => '<p>Google Meet Conference</p><p>Your appointment is scheduled with video conference.</p><p>Date: {{appointment_date}}, Time: {{appointment_time}}, Doctor: {{doctor_name}}</p><p>Join Meeting: {{meet_link}}</p><p>Thank you.</p>',
                'post_title' => 'Google Meet Link Template',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'add_doctor_meet_link',
                'post_content' => '<p>Google Meet Conference</p><p>Your appointment with patient.</p><p>Patient: {{patient_name}}<br>Date: {{appointment_date}}, Time: {{appointment_time}}</p><p>Join Meeting: {{meet_link}}</p><p>Thank you.</p>',
                'post_title' => 'Doctor Google Meet Link Template',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'encounter_close',
                'post_content' => '<p>Dear {{patient_name}},</p><p>Your medical encounter has been completed.</p><p>Date: {{appointment_date}}<br>Doctor: {{doctor_name}}</p><p>{{prescription}}</p><p>Thank you for visiting us.</p>',
                'post_title' => 'Encounter Close Notification',
                'post_type' => $templatePostType,
                'post_status' => 'publish'
            ],
            [
                'post_name' => $this->prefix . 'clinic_admin_registration',
                'post_content' => '<p>Welcome to Clinic,</p><p>You are successfully registered as clinic admin</p><p>Your email: {{user_email}}, username: {{user_name}} and password: {{user_password}}</p><p>Thank you.</p>',
                'post_title' => 'Clinic Admin Registration',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'clinic_book_appointment',
                'post_content' => '<p>New appointment</p><p>New appointment booked on {{current_date}}</p><p>For Date: {{appointment_date}}, Time: {{appointment_time}}, Patient: {{patient_name}}, Doctor: {{doctor_name}}</p><p>Thank you.</p>',
                'post_title' => 'Clinic Booked Appointment Template',
                'post_type' => $templatePostType,
                'post_status' => 'publish'
            ],
            [
                'post_name' => $this->prefix . 'book_appointment_reminder',
                'post_content' => '<p>Welcome to KiviCare,</p><p>You have appointment on</p><p>{{appointment_date}}, Time: {{appointment_time}}</p><p>Thank you.</p>',
                'post_title' => 'Patient Appointment Reminder',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'book_appointment_reminder_for_doctor',
                'post_content' => '<p>Welcome to KiviCare,</p><p>You have appointment on</p><p>{{appointment_date}}, Time: {{appointment_time}}</p><p>With Patient: {{patient_name}}</p><p>Thank you.</p>',
                'post_title' => 'Doctor Appointment Reminder',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'user_verified',
                'post_content' => '<p>Your Account Has been Verified By admin On Date: {{current_date}}</p><p>Login Page: {{login_url}}</p><p>Thank you.</p>',
                'post_title' => 'User Verified By Admin',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'admin_new_user_register',
                'post_content' => '<p>New User Register On site {{site_url}} On Date: {{current_date}}</p><p>Name: {{user_name}}</p><p>Email: {{user_email}}</p><p>Contact No: {{user_contact}}</p><p>User Role: {{user_role}}</p><p>Thank you.</p>',
                'post_title' => 'New User Register On Site',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'meet_link',
                'post_content' => '<p>Google Meet conference</p><p>Your have new appointment on</p><p>Date: {{appointment_date}}, Time: {{appointment_time}}, Doctor: {{doctor_name}}, Google Meet Link: {{meet_link}}</p><p>Event Link {{meet_event_link}}</p><p>Thank you.</p>',
                'post_title' => 'Google Meet Video Conference Appointment Template',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'add_doctor_meet_link',
                'post_content' => '<p>Google Meet conference</p><p>Your have new appointment on</p><p>Date: {{appointment_date}}, Time: {{appointment_time}}, Patient: {{patient_name}}, Google Meet Link: {{meet_link}}</p><p>Event Link {{meet_event_link}}</p><p>Thank you.</p>',
                'post_title' => 'Doctor Google Meet Video Conference Appointment Template',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'patient_clinic_check_in_check_out',
                'post_content' => '<p>Welcome to KiviCare,</p><p>New Patient Check In to Clinic</p><p>Patient: {{patient_name}}</p><p>Patient Email: {{patient_email}}</p><p>Check In Date: {{current_date}}</p><p>Thank you.</p>',
                'post_title' => 'Patient Clinic In',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $type === 'sms' ? $this->prefix . 'add_appointment' : $this->prefix . 'book_appointment',
                'post_content' => '<p>Welcome to KiviCare,</p><p>Your appointment has been booked successfully on</p><p>{{appointment_date}}, Time: {{appointment_time}}</p><p>Thank you.</p>',
                'post_title' => 'Patient Appointment Booking Template',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'payment_pending',
                'post_content' => '<p>Appointment Payment,</p><p>Your Appointment is cancelled due to pending payment</p><p>Thank you.</p>',
                'post_title' => 'Appointment Payment Pending Template',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ],
            [
                'post_name' => $this->prefix . 'patient_invoice',
                'post_content' => '<p>Welcome to KiviCare,</p><p>Find your Invoice in attachment</p><p>Thank you.</p>',
                'post_title' => 'Patient Invoice',
                'post_type' => $templatePostType,
                'post_status' => 'publish'
            ],
            [
                'post_name' => $this->prefix . 'patient_prescription',
                'post_content' => '<p>Welcome to KiviCare,</p><p>You Have Medicine Prescription on</p><p>Clinic : {{clinic_name}}</p><p>Doctor : {{doctor_name}}</p><p>Prescription :{{prescription}}</p><p>Thank you.</p>',
                'post_title' => 'Patient Prescription Notification Template',
                'post_type' => $templatePostType,
                'post_status' => 'publish'
            ],
        ];


        if ($type === 'gmeet') {
            $data = [
                [
                    'post_name' => KIVI_CARE_PREFIX . 'doctor_gm_event_template',
                    'post_content'  => '<p> New appointment </p><p> Your have new appointment on </p><p> Date: {{appointment_date}}  , Time : {{appointment_time}} ,Patient : {{patient_name}} </p><p> Clinic: {{clinic_name}}. </p><p> Appointment Description: {{appointment_desc}}. </p><p> Thank you. </p>',
                    'post_title' => '{{service_name}}',
                    'post_type' => $templatePostType,
                    'post_status'   => 'publish',
                ]
            ];
        }

        if ($type === 'gcal') {
            $data = [
                [
                    'post_name' => KIVI_CARE_PREFIX . 'default_event_template',
                    'post_content'  => ' Appointment booked at {{clinic_name}}',
                    'post_title' => '{{service_name}}',
                    'post_type' => $templatePostType,
                    'post_status'   => 'publish',
                ]
            ];
        }

        if($type === 'mail'){
            $data[] = [
                'post_name' => KIVI_CARE_PREFIX . 'patient_report',
                'post_content' => '<p> Welcome to KiviCare ,</p><p> Find your Report in attachment </p><p> Thank you. </p>',
                'post_title' => 'Patient Report',
                'post_type' => $templatePostType,
                'post_status' => 'publish',
            ];
        }

        return apply_filters('kivicare_notification_template_post_array', $data, $templatePostType, $this->prefix);
    }

    /**
     * Create default templates
     */
    public function createDefaultTemplates(string $type = 'mail'): bool
    {

        $templates = $this->getDefaultTemplatesData($type);

        foreach ($templates as $template) {
            // Check if template already exists
            if (!$this->templateExists($template['post_name'])) {
                $post_id = wp_insert_post($template);
                if (is_wp_error($post_id)) {
                    KCErrorLogger::instance()->error('Failed to create template: ' . $template['post_name']);
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if template exists
     */
    public function templateExists(string $postName): bool
    {
        $post = get_page_by_path($postName, OBJECT, [$this->mailTemplatePostType]);
        return $post !== null;
    }

    /**
     * Get template by name
     */
    public function getTemplate(string $templateName, string $type = 'mail'): ?WP_Post
    {
        $postType =  $this->mailTemplatePostType;

        $query = new WP_Query([
            'post_type' => $postType,
            'name' => $templateName,
            'post_status' => 'any',
            'posts_per_page' => 1
        ]);

        return $query->have_posts() ? $query->posts[0] : null;
    }

    /**
     * Get template by ID
     */
    public function getTemplateById(int $templateId, string $type = 'mail'): ?WP_Post
    {
        $postType = $this->mailTemplatePostType;

        $query = new WP_Query([
            'post_type' => $postType,
            'p' => $templateId,
            'post_status' => 'any',
            'posts_per_page' => 1
        ]);

        return $query->have_posts() ? $query->posts[0] : null;
    }

    /**
     * Get template data along with dynamic keys by template ID
     */
    public function getTemplateWithKeysById(int $templateId, string $type = 'mail'): ?array
    {
        $templatePost = $this->getTemplateById($templateId, $type);

        if (!$templatePost instanceof \WP_Post) {
            return null;
        }

        $templateName = $templatePost->post_name;

        $dynamicKeys = $this->dynamicKeys->getDynamicKeys($templateName);

        return [
            'templatePost'    => $templatePost,
            'dynamic_keys' => $dynamicKeys,
        ];
    }



    /**
     * Get all templates by type
     */
    public function getTemplatesList(string $templateType = 'mail'): array
    {
        $postType = strtolower($this->prefix . $templateType . '_tmp');

        $args = [
            'post_type' => $postType,
            'numberposts' => -1,
            'post_status' => 'any',
        ];

        $templateResult = get_posts($args);

        $userWiseTemplate = $this->getUserWiseTemplateMapping();

        foreach ($templateResult as $post) {
            $post->content_sid = get_post_meta($post->ID, 'content_sid', true);
        }

        $templateResult = collect($templateResult)->unique('post_name')->sortBy('ID')->map(function ($value) use ($userWiseTemplate) {
            foreach ($userWiseTemplate as $userType => $templates) {
                if (in_array($value->post_name, $templates)) {
                    $value->user_type = $userType;
                    break;
                }
            }

            if (empty($value->user_type)) {
                $value->user_type = 'common';
            }

            $value->post_content = wp_kses($value->post_content, $this->getAllowedHtml());
            return $value;
        });

        return $templateResult->groupBy('user_type')->sortKeys()->toArray();
    }

    /**
     * Get user-wise template mapping
     */
    private function getUserWiseTemplateMapping(): array
    {
        $userWiseTemplate = [
            'patient' => [
                'kivicare_patient_register',
                'kivicare_book_appointment_reminder',
                'kivicare_book_appointment',
                'kivicare_add_appointment',
                'kivicare_cancel_appointment',
                'kivicare_encounter_close',
                'kivicare_zoom_link',
                'kivicare_meet_link',
                'kivicare_payment_confirmation',
                'kivicare_patient_clinic_check_in_check_out',
                'kivicare_encounter_close',
                'kivicare_patient_prescription',
                'kivicare_patient_invoice',
                'kivicare_patient_report'
            ],
            'doctor' => [
                'kivicare_doctor_registration',
                'kivicare_doctor_book_appointment',
                'kivicare_add_doctor_zoom_link',
                'kivicare_add_doctor_meet_link',
                'kivicare_book_appointment_reminder_for_doctor',
            ],
            'clinic' => [
                'kivicare_clinic_admin_registration',
                'kivicare_clinic_book_appointment'
            ],
            'receptionist' => [
                'kivicare_receptionist_register'
            ],
            'common' => [
                'kivicare_resend_user_credential',
                'kivicare_user_verified',
                'kivicare_admin_new_user_register'
            ]
        ];

        return apply_filters('kivicare_user_wise_notification_template', $userWiseTemplate);
    }

    /**
     * Get allowed HTML tags for template content
     */
    protected function getAllowedHtml(): array
    {
        return [
            'p' => [],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'u' => [],
            'a' => [
                'href' => [],
                'title' => [],
                'target' => []
            ],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'h5' => [],
            'h6' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'div' => [
                'class' => [],
                'style' => []
            ],
            'span' => [
                'class' => [],
                'style' => []
            ]
        ];
    }

    /**
     * Update template content
     */
    public function updateTemplate(string $templateName, string $content, string $type = 'mail'): bool
    {
        $template = $this->getTemplate($templateName, $type);

        if (!$template) {
            return false;
        }

        $result = wp_update_post([
            'ID' => $template->ID,
            'post_content' => $content
        ]);

        return !is_wp_error($result);
    }

    /**
     * Delete template
     */
    public function deleteTemplate(string $templateName, string $type = 'mail'): bool
    {
        $template = $this->getTemplate($templateName, $type);

        if (!$template) {
            return false;
        }

        $result = wp_delete_post($template->ID, true);
        return $result !== false;
    }
}
