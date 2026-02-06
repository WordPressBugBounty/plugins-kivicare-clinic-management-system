<?php

namespace App\models;

use App\baseClasses\KCBase;
use App\baseClasses\KCBaseModel;
use App\baseClasses\KCQueryBuilder;
use App\models\KCPatientClinicMapping;
use WP_Error;
use App\models\KCAppointment;
use App\models\KCPatientEncounter;
use App\models\KCUserMeta;
use App\models\KCClinic;

defined('ABSPATH') or die('Something went wrong');

use App\models\traits\KCWPUser;

/**
 * Class KCPatient
 * 
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string|null $password
 * @property int $status
 * @property string $firstName
 * @property string $lastName
 * @property string $displayName
 * @property string $gender
 * @property string $bloodGroup
 * @property string $contactNumber
 * @property string $address
 * @property string $city
 * @property string $state
 * @property string $country
 * @property string $postalCode
 */
class KCPatient extends KCBaseModel
{
    use KCWPUser;

    /**
     * User role for the patient
     */
    protected static string $userRole = KIVI_CARE_PREFIX . 'patient';
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
                    'nullable' => true,
                    'sanitizers' => ['sanitize_email'],
                    'validators' => [
                        fn($value) => (empty($value) || is_email($value)) ? true : 'Invalid email format'
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
        // Validate required fields
        $isNew = empty($this->id);

        // Save WordPress user
        $result = $this->saveWordPressUser([
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password ?? null,
            'firstName' => $this->firstName ?? '',
            'lastName' => $this->lastName ?? '',
            'displayName' => $this->displayName ?? null,
            'status' => $this->status
        ]);

        // Check if saveWordPressUser failed
        if (is_wp_error($result)) {
            return $result;
        }

        // Ensure we have a valid user ID
        if (empty($this->id)) {
            return new WP_Error('save_failed', 'Failed to create or update WordPress user');
        }

        if ($isNew) {
            // Set patient role for new user
            $this->setRole(self::$userRole);
            $this->userRole = self::$userRole;
            // Set patient_added_by meta for new patients
            update_user_meta($this->id, 'patient_added_by', get_current_user_id());
        }

        $temp = [
            'mobile_number' => $this->contactNumber,
            'gender' => $this->gender,
            'dob' => $this->dob,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'postal_code' => $this->postalCode,
            'blood_group' => $this->bloodGroup,
        ];

        // Update user meta for first_name and last_name
        update_user_meta($this->id, 'first_name', $this->firstName);
        update_user_meta($this->id, 'last_name', $this->lastName);

        // Save basic_data as JSON
        update_user_meta($this->id, 'basic_data', json_encode($temp, JSON_UNESCAPED_UNICODE));

        //save patient profile image
        if (isset($this->profileImage) && !empty((int) $this->profileImage)) {
            update_user_meta($this->id, 'patient_profile_image', (int) $this->profileImage);
        }

        return $this->id;
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
        $user_status = ($status === 'active' || $status === 1 || $status === '1' || $status === 0 || $status === '0')
            ? (in_array($status, [0, '0', 'active']) ? 0 : 1)
            : 1;
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
            'gender',
            'bloodGroup',
            'contactNumber',
            'address',
            'city',
            'state',
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

    /**
     * Get total patients count based on user role and permissions
     * 
     * @param string $user_role The role of the current user
     * @param int $user_id The ID of the current user
     * @return int The count of patients accessible to this user
     */
    public static function getCount(string $user_role, int $user_id, $filters = []): int
    {
        $kcbase = KCBase::get_instance();

        // Base query for all patients.
        $query = self::table('p');

        // Apply date range filter on patient registration date if provided
        if (!empty($filters['date_range']['start_date']) && !empty($filters['date_range']['end_date'])) {
            $start_date = gmdate('Y-m-d 00:00:00', strtotime($filters['date_range']['start_date']));
            $end_date = gmdate('Y-m-d 23:59:59', strtotime($filters['date_range']['end_date']));
            $query->whereBetween('p.user_registered', [$start_date, $end_date]);
        }

        switch ($user_role) {
            case $kcbase->getDoctorRole():
                // Get patient IDs from appointments, encounters, and user meta
                $appointmentPatientIds = KCAppointment::query()->where('doctorId', $user_id)->select(['patient_id'])->get()->pluck('patientId')->unique()->toArray();
                $encounterPatientIds = KCPatientEncounter::query()->where('doctorId', $user_id)->select(['patient_id'])->get()->pluck('patientId')->unique()->toArray();
                $addedPatientIds = KCUserMeta::query()->where('metaKey', 'patient_added_by')->where('metaValue', $user_id)->select(['user_id'])->get()->pluck('userId')->unique()->toArray();
                // Merge all unique patient IDs
                $allPatientIds = array_unique(array_merge($appointmentPatientIds, $encounterPatientIds, $addedPatientIds));

                if (empty($allPatientIds)) {
                    return 0;
                }

                $query->whereIn('p.ID', $allPatientIds);
                break;

            case $kcbase->getReceptionistRole():
            case $kcbase->getClinicAdminRole():
                $clinic_ids = [];
                if ($user_role === $kcbase->getReceptionistRole()) {
                    $clinic_id = KCClinic::getClinicIdOfReceptionist($user_id);
                    if ($clinic_id) {
                        $clinic_ids[] = $clinic_id;
                    }
                } else { // Clinic Admin
                    $clinic_ids = KCClinic::query()->where('clinic_admin_id', $user_id)->get()->map(fn($clinic) => $clinic->id)->toArray();
                }

                if (empty($clinic_ids)) {
                    return 0;
                }
                // Get all patient IDs associated with these clinics
                $patientIds = KCPatientClinicMapping::query()->whereIn('clinicId', $clinic_ids)->select(['patient_id'])->get()->pluck('patientId')->unique()->toArray();
                if (empty($patientIds)) {
                    return 0;
                }

                $query->whereIn('p.ID', $patientIds);
                break;

            case 'administrator':
                // The base query is sufficient as it already filters by patient role and date.
                break;

            default:
                // For any other role, they can't see any patients.
                return 0;
        }

        return (int) $query->count();
    }
}
