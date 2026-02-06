<?php

namespace App\models;

use App\baseClasses\KCBaseModel;
use App\baseClasses\KCQueryBuilder;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');
require_once(ABSPATH . 'wp-admin/includes/user.php');
use App\models\traits\KCWPUser;
use function wp_delete_user as wpDeleteUser;
class KCReceptionist extends KCBaseModel
{
    use KCWPUser;
    /**
     * User role for the receptionist
     */
    protected static string $userRole = KIVI_CARE_PREFIX . 'receptionist';

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
            $this->setRole(static::$userRole);
            $this->userRole = static::$userRole;
        }

        // Collect basic data into a single JSON object
        $basicData = [
            'mobile_number' => $this->contactNumber ?? '',
            'gender' => $this->gender ?? 'male',
            'dob' => $this->dob ?? '',
            'address' => $this->address ?? '',
            'city' => $this->city ?? '',
            'state' => $this->state ?? '',
            'country' => $this->country ?? '',
            'postal_code' => $this->postalCode ?? ''
        ];

        // Update user meta for first_name and last_name
        update_user_meta($this->id, 'first_name', $this->firstName);
        update_user_meta($this->id, 'last_name', $this->lastName);

        // Save all basic data in a single meta field
        update_user_meta($this->id, 'basic_data', json_encode($basicData, JSON_UNESCAPED_UNICODE));

        // Save profile image if provided
        if (isset($this->profileImage) && !empty((int) $this->profileImage)) {
            update_user_meta($this->id, 'receptionist_profile_image', (int) $this->profileImage);
        }

        return $this->id;
    }


    public function getClinics()
    {
        return KCReceptionistClinicMapping::query()
            ->where('receptionistId', $this->id)
            ->get()
            ->map(function ($mapping) {
                return $mapping->getClinic();
            });
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
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
        print_r($user_status, true);
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

        // Get from basic_data JSON for additional fields
        $basicData = $this->getMeta('basic_data');
        if (!empty($basicData)) {
            $basicData = is_string($basicData) ? json_decode($basicData, true) : $basicData;

            switch ($property) {
                case 'contactNumber':
                    return $basicData['mobile_number'] ?? '';
                case 'gender':
                    return $basicData['gender'] ?? '';
                case 'dob':
                    return $basicData['dob'] ?? '';
                case 'address':
                    return $basicData['address'] ?? '';
                case 'city':
                    return $basicData['city'] ?? '';
                case 'state':
                    return $basicData['state'] ?? '';
                case 'country':
                    return $basicData['country'] ?? '';
                case 'postalCode':
                    return $basicData['postal_code'] ?? '';
            }
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

    public static function getAllReceptionists()
    {
        global $wpdb;
        return static::query()
            ->join(KCUserMeta::class, 'ID', '=', 'user_id')
            ->where('meta_key', $wpdb->prefix."capabilities")
            ->where('meta_value', 'LIKE', '%"' . self::$userRole . '"%')
            ->get();
    }
}