<?php

namespace App\models;

use App\baseClasses\KCBaseModel;
use App\baseClasses\KCBase;

defined('ABSPATH') or die('Something went wrong');

class KCClinicSchedule extends KCBaseModel
{
    /**
     * Define table structure and properties
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_clinic_schedule',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'int',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'startDate' => [
                    'column' => 'start_date',
                    'type' => 'date',
                    'nullable' => false,
                    'sanitizers' => ['sanitize_text_field'],
                    'validators' => [
                        fn($value) => self::validateDate($value) ? true : 'Invalid start date format'
                    ],
                ],
                'endDate' => [
                    'column' => 'end_date',
                    'type' => 'date',
                    'nullable' => false,
                    'sanitizers' => ['sanitize_text_field'],
                    'validators' => [
                        fn($value) => self::validateDate($value) ? true : 'Invalid end date format'
                    ],
                ],
                'moduleType' => [
                    'column' => 'module_type',
                    'type' => 'string',
                    'nullable' => false,
                    'sanitizers' => ['sanitize_text_field'],
                    'validators' => [
                        fn($value) => in_array($value, ['doctor', 'clinic']) ? true : 'Invalid module type'
                    ],
                ],
                'moduleId' => [
                    'column' => 'module_id',
                    'type' => 'int',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        fn($value) => $value > 0 ? true : 'Invalid module ID'
                    ],
                ],
                'description' => [
                    'column' => 'description',
                    'type' => 'text',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_textarea_field'],
                ],
                'status' => [
                    'column' => 'status',
                    'type' => 'int',
                    'nullable' => false,
                    'default' => 1,
                    'validators' => [
                        fn($value) => in_array($value, [0, 1]) ? true : 'Status must be 0 or 1'
                    ],
                ],
                'createdAt' => [
                    'column' => 'created_at',
                    'type' => 'datetime',
                    'nullable' => false,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
            ],
            'timestamps' => false, // We're handling created_at manually
            'soft_deletes' => false,
        ];
    }

    /**
     * Validate date format (YYYY-MM-DD)
     */
    private static function validateDate($date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Get the related module (clinic or doctor) based on module_type
     */
    public function getModule()
    {
        switch ($this->moduleType) {
            case 'clinic':
                return KCClinic::find($this->moduleId);
            case 'doctor':
                return KCDoctor::find($this->moduleId);
            default:
                return null;
        }
    }

    /**
     * Get the clinic this schedule belongs to
     * (for backward compatibility)
     */
    public function getClinic()
    {
        if ($this->moduleType === 'clinic') {
            return KCClinic::find($this->moduleId);
        }
        return null;
    }

    /**
     * Get the doctor this schedule belongs to
     * (for backward compatibility)
     */
    public function getDoctor()
    {
        if ($this->moduleType === 'doctor') {
            return KCDoctor::find($this->moduleId);
        }
        return null;
    }

    /**
     * Check if the schedule is active for a given date
     */
    public function isActiveOnDate($date): bool
    {
        $checkDate = \DateTime::createFromFormat('Y-m-d', $date);
        $startDate = \DateTime::createFromFormat('Y-m-d', $this->startDate);
        $endDate = \DateTime::createFromFormat('Y-m-d', $this->endDate);

        return $checkDate >= $startDate && $checkDate <= $endDate && $this->status == 1;
    }

    /**
     * Get all schedules for a specific module
     */
    public static function getByModule($moduleType, $moduleId)
    {
        return static::where([
            'module_type' => $moduleType,
            'module_id' => $moduleId
        ])->get();
    }

    /**
     * Get active schedules for a date range
     */
    public static function getActiveSchedules($startDate, $endDate)
    {
        return static::where([
            'status' => 1
        ])->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->get();
    }

    public static function checkClinicSchedulePermission($module_id, $module_type)
    {
        $kcbase = (new KCBase());
        $permission = false;
        $login_user_role = $kcbase->getLoginUserRole();
        switch ($login_user_role) {
            case 'administrator':
                $permission = true;
                break;
            case $kcbase->getClinicAdminRole():
            case $kcbase->getReceptionistRole():
                $clinic_id = $login_user_role === $kcbase->getClinicAdminRole() ? kcGetClinicIdOfClinicAdmin() : KCClinic::getClinicIdOfReceptionist();
                if ($module_type === 'clinic') {
                    if ((int)$module_id === $clinic_id) {
                        $permission = true;
                    }
                } else {
                    $doctor_clinic = (new KCDoctorClinicMapping())->get_var(
                        [
                            'doctor_id' => (int)$module_id,
                            'clinic_id' => $clinic_id
                        ],
                        'id'
                    );
                    if (!empty($doctor_clinic)) {
                        $permission = true;
                    }
                }
                break;
            case $kcbase->getDoctorRole():
                if ($module_type === 'doctor') {
                    if ((int)$module_id === get_current_user_id()) {
                        $permission = true;
                    }
                }
                break;
        }
        return $permission;
    }
}
