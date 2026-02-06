<?php

namespace App\blocks;

use App\baseClasses\KCBase;
use App\baseClasses\KCErrorLogger;
use App\models\KCClinic;
use App\models\KCDoctorClinicMapping;
use App\models\KCDoctor;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class KCBlocksRegister extends KCBase
{
    public static function register()
    {
        add_action('init', [self::class, 'register_blocks']);
        add_action('enqueue_block_editor_assets', [self::class, 'enqueue_editor_assets']);
        add_filter('block_categories_all', [self::class, 'register_block_category'], 10, 2);
    }

    public static function register_block_category($categories, $post)
    {
        return array_merge(
            $categories,
            [
                [
                    'slug'  => 'kivi-appointment-widget',
                    'title' => 'KiVi Care'
                ]
            ]
        );
    }

    public static function register_blocks()
    {
        // Book Appointment Block
        register_block_type('kivi-care/book-appointment-widget', [
            'editor_script' => 'kivicare-book-appointment-block',
            'attributes' => [
                'short_code' => [
                    'type' => 'string',
                    'default' => '[kivicareBookAppointment]'
                ],
                'clinicId' => [
                    'type' => 'integer',
                    'default' => 0
                ],
                'doctorId' => [
                    'type' => 'string',
                    'default' => ''
                ]
            ]
        ]);

        // Book Appointment Button Block
        register_block_type('kivi-care/popup-book-appointment-widget', [
            'editor_script' => 'kivicare-book-appointment-button-block',
            'attributes' => [
                'short_code' => [
                    'type' => 'string',
                    'default' => '[kivicareBookAppointmentButton]'
                ],
                'clinicId' => [
                    'type' => 'integer',
                    'default' => 0
                ],
                'doctorId' => [
                    'type' => 'string',
                    'default' => ''
                ]
            ]
        ]);

        // Register Login Block
        register_block_type('kivi-care/register-login', [
            'editor_script' => 'kivicare-register-login-block',
            'attributes' => [
                'short_code' => [
                    'type' => 'string',
                    'default' => '[kivicareRegisterLogin]'
                ],
                'initial_tab' => [
                    'type' => 'string',
                    'default' => ''
                ],
                'userRole' => [
                    'type' => 'string',
                    'default' => ''
                ]
            ]
        ]);
    }

    public static function enqueue_editor_assets()
    {
        wp_register_script(
            'kivicare-book-appointment-block',
            KIVI_CARE_DIR_URI . 'assets/js/kc-book-appointment-block.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-i18n', 'wp-components']
        );

        $clinicsmappingForShortCode = [];
        $clinicsListForShortCode = [];
        if (isKiviCareProActive()) {
            $clinicsmappingForShortCode = array_map(function($item) {
                return [
                    'id' => $item->id,
                    'doctor_id' => $item->doctorId,
                    'clinic_id' => $item->clinicId
                ];
            }, KCDoctorClinicMapping::query()->get()->all());
            $clinicsListForShortCode = array_map(function($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name
                ];
            }, KCClinic::query()->get()->all());
        } else {
            KCErrorLogger::instance()->error('KiviCare Pro is not active.');
            $clinicsListForShortCode = [KCClinic::getDefaultClinic()];
        }

        // doctor role fix
        $base = new KCBase();
        $doctorsListForShortCode = get_users([
            'role' => $base->getDoctorRole(),
            'user_status' => '0'
        ]);

        wp_localize_script('kivicare-book-appointment-block', 'clincDataNew', [
            'clinics' => $clinicsListForShortCode,
            'doctors' => $doctorsListForShortCode,
            'mappingData' => $clinicsmappingForShortCode,
            'proActive' => (bool) isKiviCareProActive()
        ]);

        wp_enqueue_script('kivicare-book-appointment-block');

        // Book Appointment Button Block Script
        wp_register_script(
            'kivicare-book-appointment-button-block',
            KIVI_CARE_DIR_URI . 'assets/js/kc-book-appointment-button-block.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-i18n', 'wp-components']
        );
        wp_localize_script('kivicare-book-appointment-button-block', 'clincDataNew', [
            'clinics' => $clinicsListForShortCode,
            'doctors' => $doctorsListForShortCode,
            'mappingData' => $clinicsmappingForShortCode,
            'proActive' => (bool) isKiviCareProActive()
        ]);
        wp_enqueue_script('kivicare-book-appointment-button-block');

        // Register Login Block Script
        wp_register_script(
            'kivicare-register-login-block',
            KIVI_CARE_DIR_URI . 'assets/js/kc-register-login-block.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-i18n', 'wp-components']
        );
        wp_enqueue_script('kivicare-register-login-block');
    }
}
