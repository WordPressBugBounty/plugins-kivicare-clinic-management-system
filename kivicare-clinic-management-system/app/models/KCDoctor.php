<?php

namespace App\models;

use App\baseClasses\KCBase;
use App\baseClasses\KCBaseModel;
use App\baseClasses\KCErrorLogger;
use App\baseClasses\KCQueryBuilder;
use WP_Error;
use App\models\KCUserMeta;
use App\models\KCClinic;
use App\models\KCDoctorClinicMapping;

defined('ABSPATH') or die('Something went wrong');

use App\models\traits\KCWPUser;

/**
 * Class KCDoctor
 * 
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string|null $password
 * @property int $status
 * @property string $firstName
 * @property string $lastName
 * @property string $displayName
 * @property string $speciality
 * @property string $qualification
 * @property string $contactNumber
 * @property int $experience
 * @property float $fees
 * @property int $timeSlot
 * @property string $gender
 * @property string $address
 * @property string $city
 * @property string $country
 * @property string $postalCode
 */
class KCDoctor extends KCBaseModel
{
    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;
    use KCWPUser;

    /**
     * User role for the doctor
     */
    protected static string $userRole = KIVI_CARE_PREFIX . 'doctor';

    protected static string $tableAlias = 'doctors';

    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        global $wpdb;
        return [
            'user_table_name' => $wpdb->users,
            'primary_key' => 'ID',
            'columns' => [
                'id' => [
                    'column' => 'ID',
                    'type' => 'int',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'username' => [
                    'column' => 'user_login',
                    'type' => 'string',
                    'nullable' => false,
                    'sanitizers' => ['sanitize_user'],
                ],
                'email' => [
                    'column' => 'user_email',
                    'type' => 'string',
                    'nullable' => false,
                    'sanitizers' => ['sanitize_email'],
                    'validators' => [
                        fn($value) => is_email($value) ? true : 'Invalid email format'
                    ],
                ],
                'password' => [
                    'column' => 'user_pass',
                    'type' => 'string',
                    'nullable' => true,
                ],
                'status' => [
                    'column' => 'user_status',
                    'type' => 'int',
                    'nullable' => false,
                    'default' => 1,
                ],
            ],
            'timestamps' => false,
            'soft_deletes' => false,
        ];
    }


    public function save(): int|WP_Error
    {
        try {
            $isNew = empty($this->id);

            // Validate required fields
            if (empty($this->email)) {
                return new WP_Error('validation_error', __('Email is required', 'kivicare-clinic-management-system'));
            }

            // Save WordPress user
            $userResult = $this->saveWordPressUser([
                'username' => $this->username,
                'email' => $this->email,
                'password' => $this->password ?? null,
                'firstName' => $this->firstName ?? '',
                'lastName' => $this->lastName ?? '',
                'displayName' => $this->displayName ?? null,
                'status' => $this->status
            ]);

            if (is_wp_error($userResult)) {
                return $userResult;
            }

            if (!$userResult) {
                return new WP_Error('save_error', __('Failed to save WordPress user', 'kivicare-clinic-management-system'));
            }

            // Set ID from WordPress user if new
            if ($isNew) {
                $this->id = $userResult;
                // Set doctor role for new user
                $this->setRole(self::$userRole);
            }

            // Prepare basic_data array
            $basicData = [
                'mobile_number' => str_replace(' ', '', $this->contactNumber ?? ''),
                'gender' => $this->gender ?? '',
                'dob' => $this->dob ?? '',
                'address' => $this->address ?? '',
                'city' => $this->city ?? '',
                'country' => $this->country ?? '',
                'postal_code' => $this->postalCode ?? '',
                'qualifications' => !empty($this->qualifications) ? $this->qualifications : [],
                'no_of_experience' => $this->experience ?? '',
                'specialties' => !empty($this->specialties) ? $this->specialties : [],
                'temp_password' => $this->password ?? '' // Store temporary password for welcome email
            ];

            // Update user meta
            $metaUpdates = [
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'basic_data' => json_encode($basicData, JSON_UNESCAPED_UNICODE)
            ];

            if (!empty($this->description)) {
                $metaUpdates['doctor_description'] = $this->description;
            }

            if (!empty($this->signature) && is_string($this->signature)) {
                $metaUpdates['doctor_signature'] = $this->signature;
            }

            if (!empty($this->profileImage)) {
                $metaUpdates['doctor_profile_image'] = (int) $this->profileImage;
            }

            // Save all meta data
            foreach ($metaUpdates as $key => $value) {
                update_user_meta($this->id, $key, $value);
            }

            // Update user status in users table if status is set
            if (isset($this->status)) {
                global $wpdb;
                $userStatus = ($this->status === 'active' || $this->status === 1 || $this->status === '1') ? 1 : 0;
                $wpdb->update(
                    $wpdb->base_prefix . 'users',
                    ['user_status' => $userStatus],
                    ['ID' => (int) $this->id]
                );
            }

            return $this->id;

        } catch (\Exception $e) {
            KCErrorLogger::instance()->error('KCDoctor save error: ' . $e->getMessage());
            return new WP_Error('save_error', $e->getMessage());
        }
    }


    /**
     * Update doctor status
     *
     * @param int|string $status 0 or 1, or 'active'/'inactive'
     * @return bool
     */
    public function updateStatus($status): bool
    {
        if (empty($this->id)) {
            return false;
        }

        global $wpdb;
        // Accept 0/1 or 'active'/'inactive'
        $user_status = ($status === 'active' || $status === 1 || $status === '1') ? 1 : 0;
        $result = $wpdb->update(
            $wpdb->base_prefix . 'users',
            ['user_status' => $user_status],
            ['ID' => (int) $this->id]
        );

        if ($result !== false) {
            $this->status = $user_status;
            return true;
        }
        return false;
    }


    public function getClinics()
    {
        return KCDoctorClinicMapping::query()
            ->where('doctorId', $this->id)
            ->get()
            ->map(function ($mapping) {
                return $mapping->getClinic();
            });
    }

    public function getServices()
    {
        return KCServiceDoctorMapping::query()
            ->where('doctorId', $this->id)
            ->get()
            ->map(function ($mapping) {
                return $mapping->getService();
            });
    }

    public function getAppointments($status = null)
    {
        $query = KCAppointment::query()
            ->where('doctorId', $this->id)
            ->orderBy('appointmentStartDate', 'DESC');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function getSessions()
    {
        return KCClinicSession::query()
            ->where('doctorId', $this->id)
            ->orderBy('sessionDate', 'DESC')
            ->get();
    }

    public function __get(string $property)
    {
        $value = parent::__get($property);
        if ($value !== null) {
            return $value;
        }

        $user = $this->getWPUser();
        if ($user) {
            switch ($property) {
                case 'firstName':
                    return $user->first_name;
                case 'lastName':
                    return $user->last_name;
                case 'displayName':
                    return $user->display_name;
            }
        }

        // Get from user meta for additional fields

        $metaFields = [
            'speciality',
            'qualification',
            'contactNumber',
            'experience',
            'fees',
            'timeSlot',
            'gender',
            'address',
            'city',
            'country',
            'postalCode'
        ];

        if (in_array($property, $metaFields, true)) {
            return $this->getMeta(kc_snake_case($property));
        }

        return null;
    }

    public static function table(string $alias): KCQueryBuilder
    {
        global $wpdb;
        return (new KCQueryBuilder(static::class))->setTableAlias($alias)
            ->join(KCUserMeta::class, function ($join) use ($alias,$wpdb) {
                $join->on("$alias.ID", '=', 'um.user_id')
                    ->onRaw("um.meta_key = '".$wpdb->prefix."capabilities'")
                    ->on('um.meta_value', 'LIKE', '"%' . self::$userRole . '%"');
            }, null, null, 'um');
    }

    public static function getAllDoctors()
    {
        global $wpdb;
        return static::query()
            ->join(KCUserMeta::class, 'ID', '=', 'user_id')
            ->where('meta_key', $wpdb->prefix."capabilities")
            ->where('meta_value', 'LIKE', '%"' . self::$userRole . '"%')
            ->get();
    }

    public static function getCount(string $user_role, int $user_id, array $filters = []): int
    {
        $kcbase = KCBase::get_instance();

        if ($user_role === 'administrator') {
            // Count all users with the doctor role using the KCDoctor model itself.
            return (int) self::query()->count();

        } elseif ($user_role === $kcbase->getClinicAdminRole()) {
            // Get clinic IDs managed by this admin
            $clinic_ids = KCClinic::query()
                ->where('clinic_admin_id', $user_id)
                ->get() // Get the full collection of clinic models
                ->map(function ($clinic) {
                    return $clinic->id; // Manually extract the 'id' from each model
                })
                ->toArray();

            if (empty($clinic_ids)) {
                return 0;
            }
            
            // Count distinct doctors in those clinics using the mapping model
            return (int) KCDoctorClinicMapping::query()
                ->whereIn('clinicId', $clinic_ids)
                ->countDistinct('doctor_id');
        }

        return 0;
    }
}
