<?php

namespace App\models;

use App\baseClasses\KCBase;
use App\baseClasses\KCBaseModel;

defined('ABSPATH') or die('Something went wrong');

class KCClinic extends KCBaseModel
{
    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_clinics',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'name' => [
                    'column' => 'name',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                    'validators' => ['validateClinicName'],
                ],
                'email' => [
                    'column' => 'email',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_email'],
                    'validators' => ['validateEmail'],
                ],
                'telephoneNo' => [
                    'column' => 'telephone_no',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'specialties' => [
                    'column' => 'specialties',
                    'type' => 'json',
                    'nullable' => true
                ],
                'address' => [
                    'column' => 'address',
                    'type' => 'text',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'city' => [
                    'column' => 'city',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'state' => [
                    'column' => 'state',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'country' => [
                    'column' => 'country',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'postalCode' => [
                    'column' => 'postal_code',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'status' => [
                    'column' => 'status',
                    'type' => 'tinyint',
                    'nullable' => true,
                    'default' => 0,
                    'validators' => ['validateStatus'],
                ],
                'clinicAdminId' => [
                    'column' => 'clinic_admin_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'default' => 0,
                    'sanitizers' => ['intval'],
                ],
                'clinicLogo' => [
                    'column' => 'clinic_logo',
                    'type' => 'bigint',
                    'nullable' => false,
                    'default' => 0,
                    'sanitizers' => ['intval'],
                ],
                'profileImage' => [
                    'column' => 'profile_image',
                    'type' => 'bigint',
                    'nullable' => true,
                    'sanitizers' => ['intval'],
                ],
                'extra' => [
                    'column' => 'extra',
                    'type' => 'longtext',
                    'nullable' => true,
                ],
                'countryCode' => [
                    'column' => 'country_code',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'createdAt' => [
                    'column' => 'created_at',
                    'type' => 'datetime',
                    'nullable' => true,
                ],
                'countryCallingCode' => [
                    'column' => 'country_calling_code',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
            ],
            'timestamps' => false, // We'll handle created_at manually
            'soft_deletes' => false,
        ];
    }
    /**
     * Validate clinic name
     */
    public static function validateClinicName($value)
    {
        return !empty($value) ? true : 'Clinic name is required';
    }

    /**
     * Validate email
     */
    public static function validateEmail($value)
    {
        return is_email($value) ? true : 'Invalid email format';
    }

    /**
     * Validate administrator ID
     */
    public static function validateAdministratorId($value)
    {
        return $value > 0 ? true : 'Invalid administrator ID';
    }

    /**
     * Validate status
     */
    public static function validateStatus($value)
    {
        return in_array($value, [0, 1]) ? true : 'Status must be 0 or 1';
    }

    /**
     * Get all doctors associated with this clinic
     */
    public function getDoctors()
    {
        return KCDoctorClinicMapping::query()
            ->where('clinic_id', $this->id)
            ->get()
            ->map(function ($mapping) {
                return $mapping->getDoctor();
            });
    }

    /**
     * Get all receptionists associated with this clinic
     */
    public function getReceptionists()
    {
        return KCReceptionistClinicMapping::query()
            ->where('clinic_id', $this->id)
            ->get()
            ->map(function ($mapping) {
                return $mapping->getReceptionist();
            });
    }

    /**
     * Get clinic detail by clinic ID
     *
     * @param int $clinic_id
     * @return KCClinic|null
     */
    public static function getClinicDetailById(int $clinic_id)
    {
        return self::query()->where('id', $clinic_id)->first();
    }

    /**
     * Get all patients associated with this clinic
     */
    public function getPatients()
    {
        return KCPatientClinicMapping::query()
            ->where('clinic_id', $this->id)
            ->get()
            ->map(function ($mapping) {
                return $mapping->getPatient();
            });
    }

    /**
     * Get clinic schedules
     */
    public function getSchedules()
    {
        return KCClinicSchedule::query()
            ->where('clinic_id', $this->id)
            ->get();
    }

    /**
     * Get clinic sessions
     */
    public function getSessions()
    {
        return KCClinicSession::query()
            ->where('clinic_id', $this->id)
            ->orderBy('sessionDate', 'DESC')
            ->get();
    }

    /**
     * Get the default clinic ID from setup configuration
     *
     * @return int Default clinic ID or 0 if not found
     */
    public static function  kcGetDefaultClinicId()
    {
        $option = get_option('setup_step_1');
        if ($option) {
            $option = json_decode($option);
            return (int) $option->id[0];
        } else {
            // Fallback: get the first clinic if setup_step_1 is not set
            $first_clinic = self::query()->orderBy('id', 'ASC')->first();
            if ($first_clinic) {
                // Store it for future use
                update_option('setup_step_1', json_encode(['id' => [$first_clinic->id]]));
                return (int) $first_clinic->id;
            }
            return 0;
        }
    }

    /**
     * Get clinic ID for clinic admin
     * 
     * @param int|null $user_id User ID (optional, defaults to current user)
     * @return int
     */
    public static function getClinicIdOfClinicAdmin($user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $clinic = self::query()
            ->where('clinic_admin_id', $user_id)
            ->first();

        if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
            return !empty($clinic) ? (int) $clinic->id : 0;
        } else {
            return KCClinic::kcGetDefaultClinicId();
        }
    }

    /**
     * Get clinic ID for patient
     * 
     * @param int|null $user_id User ID (optional, defaults to current user)
     * @return int
     */
    public static function getClinicIdOfPatient($user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $mapping = KCPatientClinicMapping::query()
            ->where('patient_id', $user_id)
            ->first();

        if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
            return !empty($mapping) ? (int) $mapping->clinicId : 0;
        } else {
            return self::kcGetDefaultClinicId();
        }
    }

    /**
     * Get clinic ID for receptionist
     * 
     * @param int|null $user_id User ID (optional, defaults to current user)
     * @return int
     */
    public static function getClinicIdOfReceptionist($user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $mapping = KCReceptionistClinicMapping::query()
            ->where('receptionist_id', $user_id)
            ->first();

        if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
            return !empty($mapping) ? (int) $mapping->clinicId : 0;
        } else {
            return KCClinic::kcGetDefaultClinicId();
        }
    }

    /**
     * Get clinic IDs for doctor (can be associated with multiple clinics)
     * 
     * @param int|null $user_id User ID (optional, defaults to current user)
     * @return array|int
     */
    public static function getClinicIdOfDoctor($user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $mappings = KCDoctorClinicMapping::query()
            ->where('doctor_id', $user_id)
            ->get();

        if (function_exists('isKiviCareProActive') && isKiviCareProActive()) {
            return !empty($mappings) ? $mappings->pluck('clinicId')->map('intval')->toArray() : [];
        } else {
            return KCClinic::kcGetDefaultClinicId();
        }
    }

    /**
     * Get clinic ID based on current user role
     * 
     * @param int|null $user_id User ID (optional, defaults to current user)
     * @return int|array
     */
    public static function getClinicIdForCurrentUser($user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return 0;
        }

        $roles = $user->roles;

        // Check for clinic admin
        if (in_array(KCBase::get_instance()->getClinicAdminRole(), $roles)) {
            return self::getClinicIdOfClinicAdmin($user_id);
        }

        // Check for receptionist
        if (in_array(KCBase::get_instance()->getReceptionistRole(), $roles)) {
            return self::getClinicIdOfReceptionist($user_id);
        }

        // Check for doctor
        if (in_array(KCBase::get_instance()->getDoctorRole(), $roles)) {
            return self::getClinicIdOfDoctor($user_id);
        }

        // Check for patient
        if (in_array(KCBase::get_instance()->getClinicAdminRole(), $roles)) {
            return self::getClinicIdOfPatient($user_id);
        }

        // For admin or other roles
        if (in_array('administrator', $roles)) {
            return KCClinic::kcGetDefaultClinicId();
        }

        return 0;
    }

    /**
     * Check if user belongs to specific clinic
     * 
     * @param int $clinic_id
     * @param int|null $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function userBelongsToClinic($clinic_id, $user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $user_clinic_ids = self::getClinicIdForCurrentUser($user_id);

        // Handle array case (for doctors who can belong to multiple clinics)
        if (is_array($user_clinic_ids)) {
            return in_array($clinic_id, $user_clinic_ids);
        }

        // Handle single clinic ID case
        return $user_clinic_ids == $clinic_id;
    }

    /**
     * Get all clinics user has access to
     * 
     * @param int|null $user_id User ID (optional, defaults to current user)
     * @return \Illuminate\Support\Collection
     */
    public static function getClinicsForUser($user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return collect([]);
        }

        // Admin can see all clinics
        if (in_array('administrator', $user->roles)) {
            return self::query()->get();
        }

        $clinic_ids = self::getClinicIdForCurrentUser($user_id);

        // Handle array case (doctors)
        if (is_array($clinic_ids)) {
            if (empty($clinic_ids)) {
                return collect([]);
            }
            return self::query()->whereIn('id', $clinic_ids)->get();
        }

        // Handle single clinic ID case
        if ($clinic_ids > 0) {
            return self::query()->where('id', $clinic_ids)->get();
        }

        return collect([]);
    }

    /**
     * Get default clinic (for non-pro version)
     * 
     * @return KCClinic|null
     */
    public static function getDefaultClinic()
    {
        return self::find(self::kcGetDefaultClinicId());
    }

    /**
     * Check if clinic exists and user has access to it
     * 
     * @param int $clinic_id
     * @param int|null $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function canUserAccessClinic($clinic_id, $user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        // Check if clinic exists
        $clinic = self::query()->where('id', $clinic_id)->first();
        if (!$clinic) {
            return false;
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }

        // Admin can access all clinics
        if (in_array('administrator', $user->roles)) {
            return true;
        }

        // Check if user belongs to this clinic
        return self::userBelongsToClinic($clinic_id, $user_id);
    }

    /**
     * Retrieve clinic currency prefix and postfix from extra field.
     *
     * @return array{
     *     prefix: string,
     *     postfix: string
     * }
     */
    public static function getClinicCurrencyPrefixAndPostfix(): array
    {
        $clinic_prefix  = '';
        $clinic_postfix = '';

        $clinic = self::query()->first();

        if ($clinic && ! empty($clinic->extra)) {
            $data = json_decode($clinic->extra);

            $clinic_prefix  = (! empty($data->currency_prefix) && $data->currency_prefix !== 'null')
                ? $data->currency_prefix
                : '';
            $clinic_postfix = (! empty($data->currency_postfix) && $data->currency_postfix !== 'null')
                ? $data->currency_postfix
                : '';
        }

        return [
            'prefix'  => $clinic_prefix,
            'postfix' => $clinic_postfix,
            'decimal_places' => 2,
        ];
    }
}
