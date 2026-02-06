<?php

namespace App\models;

use App\baseClasses\KCBase;
use App\baseClasses\KCBaseModel;

defined('ABSPATH') or die('Something went wrong');

// Required model imports for role-based service counting
use App\models\KCServiceDoctorMapping;
use App\models\KCClinic;

class KCService extends KCBaseModel
{
    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_services',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'type' => [
                    'column' => 'type',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'name' => [
                    'column' => 'name',
                    'type' => 'varchar',
                    'nullable' => false,
                    'sanitizers' => ['sanitize_text_field'],
                    'validators' => [
                        fn($value) => !empty($value) ? true : 'Service name is required'
                    ],
                ],
                'price' => [
                    'column' => 'price',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'status' => [
                    'column' => 'status',
                    'type' => 'bigint',
                    'nullable' => false,
                    'validators' => [
                        fn($value) => is_numeric($value) ? true : 'Status must be numeric'
                    ],
                ],
                'createdAt' => [
                    'column' => 'created_at',
                    'type' => 'datetime',
                    'nullable' => false,
                ],
            ],
            'timestamps' => false, // We'll handle created_at manually
            'soft_deletes' => false,
        ];
    }

    /**
     * Get all doctors who provide this service
     */
    public function getDoctors()
    {
        return KCServiceDoctorMapping::query()
            ->where('service_id', $this->id)
            ->get()
            ->map(function ($mapping) {
                return $mapping->getDoctor();
            });
    }

    /**
     * Get metadata as array
     */
    public function getMetadata(): array
    {
        return json_decode($this->metadata ?? '{}', true) ?? [];
    }

    /**
     * Get Active Service Count based on user role
     * 
     * @param string $user_role The role of the current user
     * @param int $user_id The ID of the current user
     * @param array $filters Optional filters including date range
     * @return int The count of active services accessible to this user
     */
    public static function getCount(string $user_role, int $user_id, $filters = []): int
    {
        // Extract date range parameters if provided
        $start_date = null;
        $end_date = null;
        $kcbase = KCBase::get_instance();

        if (!empty($filters['date_range']) && !empty($filters['date_range']['start_date']) && !empty($filters['date_range']['end_date'])) {
            $start_date = sanitize_text_field($filters['date_range']['start_date']);
            $end_date = sanitize_text_field($filters['date_range']['end_date']);

            // Format for date comparison
            $start_date = gmdate('Y-m-d 00:00:00', strtotime($start_date));
            $end_date = gmdate('Y-m-d 23:59:59', strtotime($end_date));
        }

        if ($user_role === 'administrator') {
            // For administrators, get all active services
            $query = KCServiceDoctorMapping::query()->where('status', 1);

            // Apply date filter if provided
            if ($start_date && $end_date) {
                $query->where('created_at', '>=', $start_date)
                    ->where('created_at', '<=', $end_date);
            }

            return $query->count();
        } elseif ($user_role === $kcbase->getClinicAdminRole()) {
            $clinic_ids = KCClinic::query()
                ->select(['id'])
                ->where('clinic_admin_id', $user_id)
                ->get()
                ->map(function ($clinic) {
                    return $clinic->id;
                })
                ->toArray();

            if (!empty($clinic_ids)) {
                $service_mapping_query = KCServiceDoctorMapping::query()
                    ->select(['service_id'])
                    ->whereIn('clinic_id', $clinic_ids)
                    ->where('status', 1);

                // Apply date filter to service doctor mappings if provided
                if ($start_date && $end_date) {
                    $service_mapping_query->where('createdAt', '>=', $start_date)
                        ->where('createdAt', '<=', $end_date);
                }

                $service_ids = $service_mapping_query->get()
                    ->map(function ($mapping) {
                        return $mapping->serviceId;
                    })
                    ->unique()
                    ->toArray();

                if (!empty($service_ids)) {
                    // Count active services from the service IDs
                    $query = static::query()
                        ->whereIn('id', $service_ids)
                        ->where('status', 1);

                    // Apply additional date filter to services table if provided
                    if ($start_date && $end_date) {
                        $query->where(function ($q) use ($start_date, $end_date) {
                            $q->where('createdAt', '>=', $start_date)
                                ->where('createdAt', '<=', $end_date);
                        });
                    }

                    return $query->count();
                }
            }
            return 0;
        } elseif ($user_role === $kcbase->getDoctorRole()) {
            $service_mapping_query = KCServiceDoctorMapping::query()
                ->select(['service_id'])
                ->where('doctor_id', $user_id)
                ->where('status', 1);

            // Apply date filter to service doctor mappings if provided
            if ($start_date && $end_date) {
                $service_mapping_query->where('createdAt', '>=', $start_date)
                    ->where('createdAt', '<=', $end_date);
            }

            $service_ids = $service_mapping_query->get()
                ->map(function ($mapping) {
                    return $mapping->serviceId;
                })
                ->unique()
                ->toArray();

            if (!empty($service_ids)) {
                // Count active services from the service IDs
                $query = static::query()
                    ->whereIn('id', $service_ids)
                    ->where('status', 1);

                // Apply additional date filter to services table if provided
                if ($start_date && $end_date) {
                    $query->where(function ($q) use ($start_date, $end_date) {
                        $q->where('createdAt', '>=', $start_date)
                            ->where('createdAt', '<=', $end_date);
                    });
                }

                return $query->count();
            }
            return 0;
        } elseif ($user_role === $kcbase->getReceptionistRole()) {
            // Select only the clinic_id column to minimize data transfer
            $clinic_ids = KCReceptionistClinicMapping::query()
                ->select(['clinic_id'])
                ->where('receptionist_id', $user_id)
                ->get()
                ->map(function ($mapping) {
                    return $mapping->clinicId;
                })
                ->toArray();

            if ($clinic_ids) {
                // Select only the serviceId column to minimize data transfer
                $service_mapping_query = KCServiceDoctorMapping::query()
                    ->select(['service_id'])
                    ->whereIn('clinic_id', $clinic_ids)
                    ->where('status', 1);

                // Apply date filter to service doctor mappings if provided
                if ($start_date && $end_date) {
                    $service_mapping_query->where('createdAt', '>=', $start_date)
                        ->where('createdAt', '<=', $end_date);
                }

                $service_ids = $service_mapping_query->get()
                    ->map(function ($mapping) {
                        return $mapping->serviceId;
                    })
                    ->unique()
                    ->toArray();

                if (!empty($service_ids)) {
                    // Count active services from the service IDs
                    $query = static::query()
                        ->whereIn('id', $service_ids)
                        ->where('status', 1);

                    // Apply additional date filter to services table if provided
                    if ($start_date && $end_date) {
                        $query->where(function ($q) use ($start_date, $end_date) {
                            $q->where('createdAt', '>=', $start_date)
                                ->where('createdAt', '<=', $end_date);
                        });
                    }

                    return $query->count();
                }
            }
        }

        // Default fallback - return all active services
        $query = static::query()->where('status', 1);

        // Apply date filter if provided
        if ($start_date && $end_date) {
            $query->where('createdAt', '>=', $start_date)
                ->where('createdAt', '<=', $end_date);
        }

        return $query->count();
    }
}
