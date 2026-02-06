<?php

namespace App\models;

use App\baseClasses\KCBaseModel;
use Illuminate\Support\Collection;

defined('ABSPATH') or die('Something went wrong');

class KCServiceDoctorMapping extends KCBaseModel
{
    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array{
        return [
            'table_name' => 'kc_service_doctor_mapping',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                    'sanitizers' => ['intval'],
                ],
                'serviceId' => [
                    'column' => 'service_id',
                    'type' => 'int',
                    'nullable' => true,
                    'default' => 0,
                    'sanitizers' => ['intval'],
                ],
                'doctorId' => [
                    'column' => 'doctor_id',
                    'type' => 'bigint',
                    'nullable' => true,
                    'default' => 0,
                    'sanitizers' => ['intval'],
                ],
                'clinicId' => [
                    'column' => 'clinic_id',
                    'type' => 'int',
                    'nullable' => true,
                    'default' => 0,
                    'sanitizers' => ['intval'],
                ],
                'charges' => [
                    'column' => 'charges',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['doubleval'],
                ],
                'extra' => [
                    'column' => 'extra',
                    'type' => 'longtext',
                    'nullable' => true,
                ],
                'telemedService' => [
                    'column' => 'telemed_service',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'serviceNameAlias' => [
                    'column' => 'service_name_alias',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'multiple' => [
                    'column' => 'multiple',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'image' => [
                    'column' => 'image',
                    'type' => 'bigint',
                    'nullable' => true,
                    'sanitizers' => ['intval'],
                ],
                'status' => [
                    'column' => 'status',
                    'type' => 'int',
                    'nullable' => true,
                    'default' => 1,
                    'sanitizers' => ['intval'],
                ],
                'createdAt' => [
                    'column' => 'created_at',
                    'type' => 'datetime',
                    'nullable' => false,
                ],
                'duration' => [
                    'column' => 'duration',
                    'type' => 'int',
                    'nullable' => true,
                    'sanitizers' => ['intval'],
                ],
            ],
            'timestamps' => false, // We'll handle created_at manually
            'soft_deletes' => false,
        ];
    }

    /**
     * Get the service associated with this mapping
     */
    public function getService()
    {
        return KCService::find($this->serviceId);
    }

    /**
     * Get the doctor associated with this mapping
     */
    public function getDoctor()
    {
        return KCDoctor::find($this->doctorId);
    }

    /**
     * Get the clinic associated with this mapping
     */
    public function getClinic()
    {
        return KCClinic::find($this->clinicId);
    }

    /**
     * Get services with detailed information
     * 
     * @param array $args Optional filter arguments:
     *                    'doctor_id' - Filter by doctor ID
     *                    'clinic_id' - Filter by clinic ID
     *                    'status' - Filter by status (0 or 1)
     *                    'service_type' - Filter by service type
     *                    'telemed_only' - If true, only return telemed services
     *                    'include_base_service' - If true, join and include base service data
     * @return Collection Collection of service mappings with service data
     */
    public static function getServices(array $args = []): Collection
    {
        $query = self::table('sdm')
            ->select([
                'sdm.*',
                's.name as service_name',
                's.type as service_type'
            ])
            ->leftJoin(KCService::class, 'sdm.service_id', '=', 's.id', 's');
        
        // Filter by doctor_id if provided
        if (!empty($args['doctor_id'])) {
            $query->where('sdm.doctor_id', '=', $args['doctor_id']);
        }
        
        // Filter by doctor_ids if provided (array)
        if (!empty($args['doctor_ids']) && is_array($args['doctor_ids'])) {
            $query->whereIn('sdm.doctor_id', $args['doctor_ids']);
        }
        
        // Filter by clinic_id if provided
        if (!empty($args['clinic_id'])) {
            $query->where('sdm.clinic_id', '=', $args['clinic_id']);
        }
        
        // Filter by status if provided
        if (isset($args['status'])) {
            $query->where('sdm.status', '=', $args['status']);
        }
        
        // Filter by service_type if provided
        if (!empty($args['service_type'])) {
            $query->where('s.type', '=', $args['service_type']);
        }
        
        // Filter telemed services if requested
        if (isset($args['telemed_only'])) {
            if ($args['telemed_only'] === true) {
                $query->where('sdm.telemed_service', '=', 'yes');
            } elseif ($args['telemed_only'] === false) {
                $query->where(function ($q) {
                    $q->whereNull('sdm.telemed_service')
                        ->orWhere('sdm.telemed_service', '=', 'no')
                        ->orWhere('sdm.telemed_service', '=', '');
                });
            }
        }
        
        // Order by service name by default
        $query->orderBy('s.name');
        
        // Execute the query
        $services = $query->get();
        
        // Transform the data if needed
        return $services->map(function($service) {
            // Calculate any additional fields or format data if needed
            $service->price = $service->charges;
            $service->isTelemed = $service->telemedService === 'yes';
            $service->allowMultiple = $service->multiple === 'yes';
            $service->imageUrl = $service->image ? wp_get_attachment_url($service->image) : null;
            
            return $service;
        });
    }
    
    /**
     * Get active services for a specific doctor and clinic
     * 
     * @param int|array $doctorIds The doctor ID or array of doctor IDs
     * @param int $clinicId The clinic ID
     * @param bool $telemedOnly Whether to return only telemed services
     * @return Collection Collection of active services
     */
    public static function getActiveDoctorServices($doctorIds, int $clinicId, bool|null $telemedOnly = null): Collection
    {
        $args = [
            'clinic_id' => $clinicId,
            'status' => 1, // Active services only
        ];
        
        if (is_array($doctorIds)) {
            $args['doctor_ids'] = $doctorIds;
        } elseif ($doctorIds) {
            $args['doctor_id'] = $doctorIds;
        }
        
        if (!is_null($telemedOnly)) {
            $args['telemed_only'] = $telemedOnly;
        }
        
        return self::getServices($args);
    }
}