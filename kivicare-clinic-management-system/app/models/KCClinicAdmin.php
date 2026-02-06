<?php

namespace App\models;

use App\baseClasses\KCBaseModel;
use App\baseClasses\KCQueryBuilder;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

use App\models\traits\KCWPUser;

/**
 * Class KCClinicAdmin
 * 
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string|null $password
 * @property int $status
 * @property string $firstName
 * @property string $lastName
 * @property string $displayName
 * @property string $contactNumber
 * @property string $gender
 * @property string $address
 * @property string $city
 * @property string $state
 * @property string $country
 * @property string $postalCode
 */
class KCClinicAdmin extends KCBaseModel
{
    use KCWPUser;

    /**
     * User role for the clinic_admin
     */
    protected static string $userRole = KIVI_CARE_PREFIX . 'clinic_admin';

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
        $isNew = empty($this->id);

        // Save WordPress user
        $saveUser = $this->saveWordPressUser([
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password ?? null,
            'firstName' => $this->firstName ?? '',
            'lastName' => $this->lastName ?? '',
            'displayName' => $this->displayName ?? null,
        ]);
        if (is_wp_error($saveUser)) {
            return $saveUser;
        }

        if ($isNew) {
            $this->setRole(self::$userRole);
        }

        // Save additional clinic admin meta
        $this->updateMeta('contact_number', $this->contactNumber ?? '');
        $this->updateMeta('gender', $this->gender ?? 'male');
        $this->updateMeta('address', $this->address ?? '');
        $this->updateMeta('city', $this->city ?? '');
        $this->updateMeta('state', $this->state ?? '');
        $this->updateMeta('country', $this->country ?? '');
        $this->updateMeta('postal_code', $this->postalCode ?? '');

        return $this->id;
    }


    public function getClinics()
    {
        return KCClinic::query()
            ->where('administratorId', $this->id)
            ->get();
    }

    public function getClinicDoctors()
    {
        $clinicIds = $this->getClinics()->pluck('id');

        return KCDoctorClinicMapping::query()
            ->whereIn('clinicId', $clinicIds)
            ->get()
            ->map(function ($mapping) {
                return $mapping->getDoctor();
            })
            ->unique('id');
    }

    public function getClinicReceptionists()
    {
        $clinicIds = $this->getClinics()->pluck('id');

        return KCReceptionistClinicMapping::query()
            ->whereIn('clinicId', $clinicIds)
            ->get()
            ->map(function ($mapping) {
                return $mapping->getReceptionist();
            })
            ->unique('id');
    }

    public function getClinicAppointments($status = null)
    {
        $clinicIds = $this->getClinics()->pluck('id');

        $query = KCAppointment::query()
            ->whereIn('clinicId', $clinicIds)
            ->orderBy('appointmentStartDate', 'DESC');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
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

        // ✅ CONTACT NUMBER (TWILIO SAFE)
        if ($property === 'contactNumber' && !empty($this->id)) {
            $phone = get_user_meta($this->id, 'contact_number', true);
            return $phone;
        }

        // Other meta fields
        $metaFields = [
            'gender',
            'address',
            'city',
            'state',
            'country',
            'postalCode'
        ];

        if (in_array($property, $metaFields, true) && !empty($this->id)) {
            return get_user_meta($this->id, kc_snake_case($property), true);
        }

        return null;
    }

    public function __isset(string $property): bool
    {
        $exists = parent::__isset($property);
        if (!$exists) {
            $val = $this->__get($property);
            $exists = !empty($val);
        }
        return $exists;
    }

    /**
     * Query helpers
     */
    public static function table(string $alias): KCQueryBuilder
    {
        global $wpdb;
        return (new KCQueryBuilder(static::class))->setTableAlias($alias)
            ->join(KCUserMeta::class, 'ID', '=', 'user_id')
            ->where('meta_key', $wpdb->prefix . 'capabilities')
            ->where('meta_value', 'LIKE', '%"' . self::$userRole . '"%');
    }

    public static function getAllClinicAdmins()
    {
        global $wpdb;
        return static::query()
            ->join(KCUserMeta::class, 'ID', '=', 'user_id')
            ->where('meta_key', $wpdb->prefix . 'capabilities')
            ->where('meta_value', 'LIKE', '%"' . self::$userRole . '"%')
            ->get();
    }
}
