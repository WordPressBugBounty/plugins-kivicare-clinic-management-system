<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\baseClasses\KCErrorLogger;
use App\models\KCClinic;
use App\models\KCDoctor;
use App\models\KCPatient;
use App\models\KCDoctorClinicMapping;
use App\models\KCReceptionistClinicMapping;
use App\models\KCPatientClinicMapping;
use App\models\KCServiceDoctorMapping;
use App\models\KCService;
use App\models\KCClinicSession;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') or die('Something went wrong');
class SetupWizardController extends KCBaseController
{
    /**
     * Register REST API routes for the setup wizard
     */
    public function registerRoutes()
    {
        // Setup clinic endpoint
        $this->registerRoute('/setup-wizard/clinic', [
            'methods' => 'POST',
            'callback' => [$this, 'setupClinic'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => $this->getSetupClinicArgs()
        ]);

        // Step completion endpoint
        $this->registerRoute('/setup-wizard/step-complete', [
            'methods' => 'POST',
            'callback' => [$this, 'updateStepCompletion'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'step' => [
                    'description' => 'Step number',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ]
            ]
        ]);
    }

    /**
     * Get arguments for setup clinic endpoint
     *
     * @return array
     */
    private function getSetupClinicArgs()
    {
        return [
            'clinic_name' => [
                'description' => 'Clinic name',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'clinic_email' => [
                'description' => 'Clinic email',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_email',
            ],
            'clinic_contact' => [
                'description' => 'Clinic contact number',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'address' => [
                'description' => 'Clinic address',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'city' => [
                'description' => 'City',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'postal_code' => [
                'description' => 'Postal code',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'country' => [
                'description' => 'Country',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'specialties' => [
                'description' => 'Clinic specialties (array of specialty IDs)',
                'type' => 'array',
            ],
            'clinic_image_id' => [
                'description' => 'Clinic image ID',
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'firstName' => [
                'description' => 'Admin first name',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'lastName' => [
                'description' => 'Admin last name',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'adminEmail' => [
                'description' => 'Admin email',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_email',
            ],
            'adminContact' => [
                'description' => 'Admin contact number',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'dob' => [
                'description' => 'Admin date of birth',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'gender' => [
                'description' => 'Admin gender',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'adminProfile' => [
                'description' => 'Admin profile image data',
                'type' => 'object',
            ],
            'roles' => [
                'description' => 'Demo user roles to create',
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'currencyPrefix' => [
                'description' => 'Currency Prefix',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'currencyPostfix' => [
                'description' => 'Currency Postfix',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /**
     * Setup clinic with admin details
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function setupClinic(WP_REST_Request $request): WP_REST_Response
    {
        // 🔐 Security Guard: Prevent re-execution if setup is already completed
        if (get_option('kc_setup_wizard_completed')) {
            return $this->response(
                ['error' => 'Forbidden'],
                __('Setup wizard has already been completed.', 'kivicare-clinic-management-system'),
                false,
                403
            );
        }

        try {
            $params = $request->get_params();

            // Start transaction
            global $wpdb;
            $wpdb->query('START TRANSACTION');

            // Create clinic admin user
            $username = sanitize_user(strtolower($params['firstName'] . $params['lastName']));
            $email = sanitize_email($params['adminEmail']);

            // Check if user already exists
            if (email_exists($email)) {
                $wpdb->query('ROLLBACK');
                return $this->response(
                    ['error' => 'Email already exists'],
                    __('Admin email already exists', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Generate random password
            $password = wp_generate_password(12, true, true);

            // Create WordPress user
            $user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                $wpdb->query('ROLLBACK');
                return $this->response(
                    ['error' => $user_id->get_error_message()],
                    __('Failed to create admin user', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Set user role to clinic admin
            $user = new \WP_User($user_id);
            $user->set_role($this->kcbase->getClinicAdminRole());

            // Prepare admin profile image ID
            $adminProfileImageId = '';
            if (!empty($params['adminProfile']) && is_array($params['adminProfile'])) {
                $adminProfileImageId = $params['adminProfile']['id'] ?? '';
            }

            // Save user meta data
            $user_meta = [
                'first_name' => sanitize_text_field($params['firstName']),
                'last_name' => sanitize_text_field($params['lastName']),
                'mobile_number' => sanitize_text_field($params['adminContact']),
                'contact_number' => sanitize_text_field($params['adminContact']),
                'gender' => sanitize_text_field($params['gender']),
                'dob' => !empty($params['dob']) ? sanitize_text_field($params['dob']) : '',
                'profile_image' => $adminProfileImageId,
            ];

            update_user_meta($user_id, 'basic_data', json_encode($user_meta));
            update_user_meta($user_id, 'first_name', $user_meta['first_name']);
            update_user_meta($user_id, 'last_name', $user_meta['last_name']);

            // Prepare currency settings
            $currency = [
                'currency_prefix' => $params['currencyPrefix'] ?? '',
                'currency_postfix' => $params['currencyPostfix'] ?? '',
            ];

            // Create clinic
            $clinic_data = [
                'name' => sanitize_text_field($params['clinic_name']),
                'email' => sanitize_email($params['clinic_email']),
                'telephoneNo' => sanitize_text_field($params['clinic_contact']),
                'address' => !empty($params['address']) ? sanitize_text_field($params['address']) : '',
                'city' => !empty($params['city']) ? sanitize_text_field($params['city']) : '',
                'postalCode' => !empty($params['postal_code']) ? sanitize_text_field($params['postal_code']) : '',
                'country' => !empty($params['country']) ? sanitize_text_field($params['country']) : '',
                'specialties' => $params['specialties'] ?? '',
                'profileImage' => !empty($params['clinic_image_id']) ? absint($params['clinic_image_id']) : 0,
                'clinicLogo' => 0, // Required field with default 0
                'clinicAdminId' => $user_id,
                'status' => 1,
                'extra' => json_encode($currency, JSON_UNESCAPED_UNICODE),
                'createdAt' => current_time('mysql'),
            ];

            try {
                $clinic = KCClinic::create($clinic_data);
            } catch (\Exception $createException) {
                $wpdb->query('ROLLBACK');
                return $this->response(
                    ['error' => $createException->getMessage()],
                    __('Failed to create clinic: ', 'kivicare-clinic-management-system') . $createException->getMessage(),
                    false,
                    500
                );
            }

            // Handle both cases: create() might return ID (int) or model object
            if (!$clinic) {
                $wpdb->query('ROLLBACK');
                return $this->response(
                    ['error' => 'Failed to create clinic'],
                    __('Failed to create clinic', 'kivicare-clinic-management-system'),
                    false,
                    500
                );
            }

            // Get clinic ID - handle both int and object returns
            $clinic_id = is_object($clinic) ? $clinic->id : $clinic;

            // Store the clinic ID for default clinic reference
            add_option('setup_step_1', json_encode(['step' => 1, 'name' => 'clinic', 'id' => [$clinic_id], 'status' => true]));

            // Update setup wizard completion status (use old key for backward compatibility)
            update_option('clinic_setup_wizard', 1);

            // Store clinic admin detail
            add_option('setup_step_4', json_encode(['step' => 4, 'name' => 'clinic_admin', 'status' => true]));

            update_option('kc_setup_wizard_completed', true);
            // Create optional demo users based on selected roles
            $roles = isset($params['roles']) && is_array($params['roles']) ? array_map('sanitize_text_field', $params['roles']) : [];
            $created_demo_users = [];
            foreach ($roles as $role_key) {
                if ($role_key === 'doctor') {
                    $created_demo_users[$role_key] = $this->createDemoDoctor($clinic_id, false, false); // No notifications for demo
                } elseif ($role_key === 'receptionist') {
                    $created_demo_users[$role_key] = $this->createDemoReceptionist($clinic_id, false, false); // No notifications for demo
                } elseif ($role_key === 'patient') {
                    $created_demo_users[$role_key] = $this->createDemoPatient($clinic_id, false, false); // No notifications for demo
                }
            }
            // Commit transaction
            $wpdb->query('COMMIT');

            // Send credentials email (optional)
            // You can implement email sending here if needed

            return $this->response(
                [
                    'clinic_id' => $clinic_id,
                    'admin_id' => $user_id,
                    'username' => $username,
                ],
                __('Clinic setup completed successfully', 'kivicare-clinic-management-system'),
                true,
                201
            );

        } catch (\Exception $e) {
            global $wpdb;
            $wpdb->query('ROLLBACK');

            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to setup clinic', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }
    private function generateDemoUserCredentials($role_key)
    {
        $base_user = 'demo_' . $role_key;
        if (function_exists('kcGenerateUsername')) {
            $username_candidate = kcGenerateUsername($base_user);
        } else {
            $username_candidate = sanitize_user($base_user);
            $suffix = 1;
            while (username_exists($username_candidate)) {
                $username_candidate = $base_user . $suffix;
                $suffix++;
            }
        }
        // Use kivicare.com domain for uniqueness
        $email_candidate = $username_candidate . '@kivicare.com';
        $email_suffix = 1;
        while (email_exists($email_candidate)) {
            $email_candidate = $username_candidate . $email_suffix . '@kivicare.com';
            $email_suffix++;
        }
        $user_pass = function_exists('kcGenerateRandomString') ? kcGenerateRandomString(12) : wp_generate_password(12, true, true);
        return [$username_candidate, $email_candidate, $user_pass];
    }
    /**
     * Create demo doctor using logic adapted from KCImportController::createDoctor
     *
     * @param int $clinic_id
     * @param bool $sendEmailNotification
     * @param bool $sendSmsNotification
     * @return int|false User ID on success, false on failure
     */
    private function createDemoDoctor($clinic_id, $sendEmailNotification = false, $sendSmsNotification = false)
    {
        global $wpdb;
        try {
            // Start transaction for this doctor creation
            $wpdb->query('START TRANSACTION');
            // Demo doctor data (adapted from import controller)
            $demo_doctor_data = [
                'first_name' => 'Doctor',
                'last_name' => 'Demo',
                'email' => '', // Will be set from generateDemoUserCredentials
                'mobile_number' => '+919876543210', // Demo contact
                'gender' => 'male',
                'clinic_id' => $clinic_id,
                'specialties' => [['label' => 'Dermatology']],
                'status' => 0, // Active
                'address' => 'Demo Address',
                'city' => 'Demo City',
                'country' => 'Demo Country',
                'postal_code' => '100001',
                'dob' => '1990-01-01',
                'experience_years' => '5',
                'blood_group' => 'O+',
                'description' => 'Demo Doctor',
            ];
            // Generate credentials
            list($username, $email, $password) = $this->generateDemoUserCredentials('doctor');
            $demo_doctor_data['email'] = $email;
            // Create new KCDoctor instance (adapted from createDoctor in import controller)
            $doctor = new KCDoctor();
            // Set doctor properties
            $doctor->username = $username;
            $doctor->password = $password;
            $doctor->email = sanitize_email($demo_doctor_data['email']);
            $doctor->firstName = $demo_doctor_data['first_name'];
            $doctor->lastName = $demo_doctor_data['last_name'];
            $doctor->displayName = $demo_doctor_data['first_name'] . ' ' . $demo_doctor_data['last_name'];
            $doctor->gender = $demo_doctor_data['gender'];
            $doctor->bloodGroup = $demo_doctor_data['blood_group'];
            $doctor->contactNumber = $demo_doctor_data['mobile_number'];
            $doctor->dob = $demo_doctor_data['dob'];
            $doctor->experience = $demo_doctor_data['experience_years'];
            $doctor->signature = '';
            $doctor->description = $demo_doctor_data['description'];
            $doctor->address = $demo_doctor_data['address'];
            $doctor->city = $demo_doctor_data['city'];
            $doctor->country = $demo_doctor_data['country'];
            $doctor->postalCode = $demo_doctor_data['postal_code'];
            $doctor->status = $demo_doctor_data['status'];
            $doctor->qualifications = [];
            $doctor->specialties = $demo_doctor_data['specialties'];
            $doctor->clinicId = $demo_doctor_data['clinic_id'];

            // Save doctor
            if (!$doctor->save()) {
                $wpdb->query('ROLLBACK');
                return false;
            }
            // Save doctor clinic mapping (adapted from saveDoctorClinic)
            $this->saveDoctorClinic($doctor->id, $demo_doctor_data['clinic_id']);

            // Commit transaction for this doctor
            $wpdb->query('COMMIT');

            $doctorData = [
                'id' => $doctor->id,
                'user_id' => $doctor->id,
                'first_name' => $doctor->firstName,
                'last_name' => $doctor->lastName,
                'email' => $doctor->email,
                'username' => $doctor->username,
                'temp_password' => $doctor->password,
                'contact_number' => $doctor->contactNumber,
                'clinic_id' => $doctor->clinicId,
                'dob' => $doctor->dob,
                'gender' => $doctor->gender,
                'blood_group' => $doctor->bloodGroup,
                'experience' => $doctor->experience,
                'signature' => $doctor->signature,
                'description' => $doctor->description,
                'address' => $doctor->address,
                'city' => $doctor->city,
                'country' => $doctor->country,
                'postal_code' => $doctor->postalCode,
                'status' => $doctor->status,
                'qualifications' => $doctor->qualifications,
                'specialties' => $doctor->specialties,
                'doctor_image_url' => $doctor->profileImage ? wp_get_attachment_url($doctor->profileImage) : '',
                'created_at' => current_time('mysql'),
            ];
            // Set WP user role
            $wp_user = new \WP_User($doctor->id);
            $wp_user->set_role('kiviCare_doctor');
            // Send notifications if requested (adapted from createDoctor)
            if ($sendEmailNotification) {
                do_action('kc_doctor_save', $doctorData, new WP_REST_Request());
            } elseif ($sendSmsNotification) {
                do_action('kc_doctor_register', $doctorData);
            }
            // Create demo service for this doctor
            $this->createDemoService($doctor->id, $clinic_id);
            // Create demo sessions for this doctor
            $this->createDoctorSessions($doctor->id, $clinic_id);
            return $doctor->id;
        } catch (\Exception $e) {
            // Rollback transaction on error
            $wpdb->query('ROLLBACK');
            KCErrorLogger::instance()->error("Failed to create demo doctor: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Create demo service for doctor (separate function)
     *
     * @param int $doctor_id
     * @param int $clinic_id
     * @return bool
     */
    private function createDemoService($doctor_id, $clinic_id)
    {
        try {
            // Demo service data (adapted from createServiceFromImport in import controller)
            $service_type = 'general_dentistry'; // Demo type
            $service_data = [
                'name' => 'Demo Service',
                'type' => $service_type,
                'price' => 100,
                'status' => 1,
                'createdAt' => current_time('mysql')
            ];
            $service = KCService::create($service_data);
            $service_id = is_object($service) ? $service->id : $service;
            if (!$service_id) {
                return false;
            }
            // Create service-doctor mapping
            $service_mapping_data = [
                'serviceId' => $service_id,
                'clinicId' => $clinic_id,
                'doctorId' => $doctor_id,
                'charges' => 100,
                'status' => 1,
                'image' => null,
                'multiple' => 'no',
                'telemedService' => 'no',
                'serviceNameAlias' => $service_type,
                'createdAt' => current_time('mysql'),
            ];
            KCServiceDoctorMapping::create($service_mapping_data);
            do_action('kc_service_add', $service_id);
            return true;
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Failed to create demo service: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Create demo doctor sessions (separate function)
     *
     * @param int $doctor_id
     * @param int $clinic_id
     * @return bool
     */
    private function createDoctorSessions($doctor_id, $clinic_id)
    {
        try {
            // Check if this is the first time creating sessions
            if (gettype(get_option('kivicare_session_first_time', true)) === 'boolean') {
                $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                $s_one_start_time = ["HH" => "09", "mm" => "00"];
                $s_one_end_time = ["HH" => "18", "mm" => "00"];
                foreach ($days as $day) {
                    $start_time = gmdate('H:i:s', strtotime($s_one_start_time['HH'] . ':' . $s_one_start_time['mm']));
                    $end_time = gmdate('H:i:s', strtotime($s_one_end_time['HH'] . ':' . $s_one_end_time['mm']));
                    $session_data = [
                        'clinicId' => $clinic_id,
                        'doctorId' => $doctor_id,
                        'day' => $day,
                        'startTime' => $start_time,
                        'endTime' => $end_time,
                        'timeSlot' => 30,
                        'createdAt' => current_time('mysql'),
                        'parentId' => null,
                        'status' => 1
                    ];
                    KCClinicSession::create($session_data);
                }
                update_option('kivicare_session_first_time', 'yes');
            }
            return true;
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Failed to create demo doctor sessions: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Save doctor clinic mapping (adapted from import controller)
     *
     * @param int $doctor_id
     * @param int $clinic_id
     * @return void
     */
    private function saveDoctorClinic($doctor_id, $clinic_id)
    {
        try {
            $mapping_data = [
                'doctorId' => (int) $doctor_id,
                'clinicId' => (int) $clinic_id,
                'owner' => 0,
                'createdAt' => current_time('mysql')
            ];

            KCDoctorClinicMapping::create($mapping_data);
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Failed to add doctor to clinic: " . $e->getMessage());
        }
    }
    /**
     * Create demo receptionist using logic adapted from KCImportController::createReceptionist
     *
     * @param int $clinic_id
     * @param bool $sendEmailNotification
     * @param bool $sendSmsNotification
     * @return int|false User ID on success, false on failure
     */
    private function createDemoReceptionist($clinic_id, $sendEmailNotification = false, $sendSmsNotification = false)
    {
        global $wpdb;
        try {
            // Start transaction for this receptionist creation
            $wpdb->query('START TRANSACTION');
            // Demo receptionist data (adapted from prepareReceptionistData in import controller)
            $demo_recep_data = [
                'first_name' => 'Receptionist',
                'last_name' => 'Demo',
                'email' => '', // Will be set from generateDemoUserCredentials
                'mobile_number' => '+919876543120', // Demo contact
                'gender' => 'male',
                'clinic_id' => $clinic_id,
                'status' => 0, // Active
                'address' => 'Demo Reception Address',
                'city' => 'Demo City',
                'country' => 'Demo Country',
                'postal_code' => '100002',
                'dob' => '1991-01-01',
                'password' => '' // Will be generated
            ];
            // Generate credentials
            list($username, $email, $password) = $this->generateDemoUserCredentials('receptionist');
            $demo_recep_data['email'] = $email;
            $demo_recep_data['password'] = $password;
            // Create WordPress user (adapted from createReceptionist in import controller)
            $user_id = wp_create_user(
                $username,
                $password,
                $demo_recep_data['email']
            );
            if (is_wp_error($user_id)) {
                $wpdb->query('ROLLBACK');
                return false;
            }
            // Update user meta (adapted from createReceptionist)
            update_user_meta($user_id, 'first_name', $demo_recep_data['first_name']);
            update_user_meta($user_id, 'last_name', $demo_recep_data['last_name']);
            update_user_meta($user_id, 'status', $demo_recep_data['status']);
            // Store basic data in JSON format
            $basic_data = [
                'mobile_number' => $demo_recep_data['mobile_number'],
                'gender' => $demo_recep_data['gender'],
                'address' => $demo_recep_data['address'],
                'city' => $demo_recep_data['city'],
                'country' => $demo_recep_data['country'],
                'postal_code' => $demo_recep_data['postal_code'],
                'dob' => $demo_recep_data['dob']
            ];
            update_user_meta($user_id, 'basic_data', json_encode($basic_data));
            // Set user role
            $user = new \WP_User($user_id);
            $user->set_role('kiviCare_receptionist');
            // Save receptionist clinic mapping (adapted from saveReceptionistClinic)
            $this->saveReceptionistClinic($user_id, $demo_recep_data['clinic_id']);
            // Commit transaction
            $wpdb->query('COMMIT');
            $receptionistData = [
                'id' => $user_id,
                'user_id' => $user_id,
                'first_name' => $demo_recep_data['first_name'],
                'last_name' => $demo_recep_data['last_name'],
                'email' => $demo_recep_data['email'],
                'user_name' => $username,
                'user_password' => $password,
                'contact_number' => $demo_recep_data['mobile_number'],
                'clinic_id' => $demo_recep_data['clinic_id'],
                'dob' => $demo_recep_data['dob'],
                'created_at' => current_time('mysql'),
            ];
            // Send notifications if requested
            if ($sendEmailNotification) {
                do_action('kc_receptionist_save', $receptionistData);
            } else if ($sendSmsNotification) {
                do_action('kc_receptionist_register', $receptionistData);
            }
            return $user_id;
        } catch (\Exception $e) {
            // Rollback transaction on error
            $wpdb->query('ROLLBACK');
            KCErrorLogger::instance()->error("Failed to create demo receptionist: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Save receptionist clinic mapping (adapted from import controller)
     *
     * @param int $receptionist_id
     * @param int $clinic_id
     * @return void
     */
    private function saveReceptionistClinic($receptionist_id, $clinic_id)
    {
        try {
            $mapping_data = [
                'receptionistId' => (int) $receptionist_id,
                'clinicId' => (int) $clinic_id,
                'createdAt' => current_time('mysql')
            ];

            KCReceptionistClinicMapping::create($mapping_data);
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Failed to add receptionist to clinic: " . $e->getMessage());
        }
    }
    /**
     * Create demo patient using logic adapted from KCImportController::createPatient
     *
     * @param int $clinic_id
     * @param bool $sendEmailNotification
     * @param bool $sendSmsNotification
     * @return int|false User ID on success, false on failure
     */
    private function createDemoPatient($clinic_id, $sendEmailNotification = false, $sendSmsNotification = false)
    {
        global $wpdb;
        try {
            // Demo patient data (adapted from preparePatientData in import controller)
            $demo_patient_data = [
                'first_name' => 'Patient',
                'last_name' => 'Demo',
                'email' => '', // Will be set from generateDemoUserCredentials
                'mobile_number' => '+919876543201', // Demo contact
                'gender' => 'male',
                'clinic_id' => $clinic_id,
                'status' => 0, // Active
                'address' => 'Demo Patient Address',
                'city' => 'Demo City',
                'country' => 'Demo Country',
                'postal_code' => '100003',
                'dob' => '1992-01-01',
                'blood_group' => 'A+',
                'password' => wp_generate_password(12, false) // Generated
            ];
            // Generate credentials
            list($username, $email, $password) = $this->generateDemoUserCredentials('patient');
            $demo_patient_data['email'] = $email;
            $demo_patient_data['password'] = $password;
            // Check if patient with same email already exists (adapted from patientExists)
            $existingPatient = KCPatient::table('p')
                ->where('p.user_email', '=', $demo_patient_data['email'])
                ->first();
            if ($existingPatient) {
                return false;
            }
            // Clean contact number
            $demo_patient_data['mobile_number'] = preg_replace('/[^0-9+]/', '', $demo_patient_data['mobile_number']);
            // Create new KCPatient instance (adapted from createPatient)
            $patient = new KCPatient();
            // Set patient properties
            $patient->username = $username;
            $patient->password = $demo_patient_data['password'];
            $patient->email = sanitize_email($demo_patient_data['email']);
            $patient->firstName = $demo_patient_data['first_name'];
            $patient->lastName = $demo_patient_data['last_name'];
            $patient->displayName = $demo_patient_data['first_name'] . ' ' . $demo_patient_data['last_name'];
            $patient->status = $demo_patient_data['status'];
            $patient->gender = $demo_patient_data['gender'];
            $patient->bloodGroup = $demo_patient_data['blood_group'];
            $patient->contactNumber = $demo_patient_data['mobile_number'];
            $patient->dob = $demo_patient_data['dob'];
            $patient->address = $demo_patient_data['address'];
            $patient->city = $demo_patient_data['city'];
            $patient->country = $demo_patient_data['country'];
            $patient->postalCode = $demo_patient_data['postal_code'];
            // Save patient
            if (!$patient->save()) {
                return false;
            }
            // Save patient clinic mapping (adapted from savePatientClinic)
            $this->savePatientClinic($patient->id, $clinic_id);

            // Set WP user role
            $wp_user = new \WP_User($patient->id);
            $wp_user->set_role('kiviCare_patient');

            $patientData = [
                'id' => $patient->id,
                'first_name' => $patient->firstName,
                'last_name' => $patient->lastName,
                'email' => $patient->email,
                'username' => $patient->username,
                'contact_number' => $patient->contactNumber,
                'dob' => $patient->dob,
                'gender' => $patient->gender,
                'blood_group' => $patient->bloodGroup,
                'address' => $patient->address,
                'city' => $patient->city,
                'country' => $patient->country,
                'postal_code' => $patient->postalCode,
                'status' => (int) $patient->status,
                'patient_image_url' => $patient->profileImage ? wp_get_attachment_url($patient->profileImage) : '',
                'clinics' => $clinic_id,
                'created_at' => current_time('mysql'),
                'temp_password' => $patient->password
            ];
            // Send notifications if enabled (adapted from createPatient)
            if ($sendEmailNotification) {
                do_action('kc_patient_save', $patientData, new WP_REST_Request());
            }
            if ($sendSmsNotification) {
                do_action('kivicare_patient_registered', $patientData);
            }
            return $patient->id;
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Failed to create demo patient: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Save patient clinic mapping (adapted from import controller)
     *
     * @param int $patient_id
     * @param int $clinic_id
     * @return void
     */
    private function savePatientClinic($patient_id, $clinic_id)
    {
        try {
            $new_temp = [
                'patientId' => (int) $patient_id,
                'clinicId' => (int) $clinic_id,
                'createdAt' => current_time('mysql')
            ];
            KCPatientClinicMapping::create($new_temp);
        } catch (\Exception $e) {
            KCErrorLogger::instance()->error("Failed to add patient to clinic: " . $e->getMessage());
        }
    }
    /**
     * Update step completion status
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function updateStepCompletion(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $step = $request->get_param('step');

            // Validate step number
            if (!in_array($step, [1, 2, 3])) {
                return $this->response(
                    ['error' => 'Invalid step number'],
                    __('Invalid step number. Must be 1, 2, or 3', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            // Get current completed steps
            $completedSteps = get_option('kc_setup_wizard_completed_steps', []);

            // Ensure it's an array
            if (!is_array($completedSteps)) {
                $completedSteps = [];
            }

            // Add the step if not already completed
            if (!in_array($step, $completedSteps)) {
                $completedSteps[] = $step;
                update_option('kc_setup_wizard_completed_steps', $completedSteps);
            }

            return $this->response(
                [
                    'step' => $step,
                    'completed_steps' => $completedSteps
                ],
                sprintf(
                    /* translators: %d: step number */
                    __('Step %d marked as completed', 'kivicare-clinic-management-system'),
                    $step
                ),
                true,
                200
            );

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update step completion', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

}
