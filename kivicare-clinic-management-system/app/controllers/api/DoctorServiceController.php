<?php

namespace App\controllers\api;

use App\baseClasses\KCBaseController;
use App\baseClasses\KCTelemedFactory;
use App\models\KCService;
use App\models\KCServiceDoctorMapping;
use App\models\KCDoctorClinicMapping;
use App\models\KCDoctor;
use App\models\KCClinic;
use App\models\KCUser;
use App\models\KCStaticData;
use App\models\KCUserMeta;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') or die('Something went wrong');

/**
 * Class DoctorServiceController
 * 
 * API Controller for Service-related endpoints
 * 
 * @package App\controllers\api
 */
class DoctorServiceController extends KCBaseController
{

    /**
     * @var string The base route for this controller
     */
    protected $route = 'doctor-services';

    /**
     * Get common arguments used across multiple endpoints
     *
     * @return array
     */
    private function getCommonArgs()
    {
        return [
            'id' => [
                'description' => 'ID',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
            'serviceId' => [
                'description' => 'Service ID',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
            'name' => [
                'description' => 'Service name',
                'type' => 'string',
                'validate_callback' => [$this, 'validateName'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'type' => [
                'description' => 'Service type',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
            'price' => [
                'description' => 'Service price',
                'type' => 'number', // Accepts float values
                'validate_callback' => [$this, 'validatePrice'],
                'sanitize_callback' => function ($value) {
                    return floatval($value);
                },
            ],
            'duration' => [
                'description' => 'Service duration in minutes',
                'type' => 'integer',
                'default' => 0, // Default duration if not provided
                'validate_callback' => [$this, 'validateDuration'],
                'sanitize_callback' => 'absint',
            ],
            'status' => [
                'description' => 'Service status (0: Inactive, 1: Active)',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateStatus'],
                'sanitize_callback' => 'absint',
            ]
        ];
    }

    /**
     * Validate ID parameter
     *
     * @param mixed $param
     * @return bool|WP_Error
     */
    public function validateId($param)
    {
        if (!is_numeric($param) || $param <= 0) {
            return new WP_Error('invalid_id', __('Invalid ID parameter', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Validate service name
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateName($param)
    {
        if (empty($param)) {
            return new WP_Error('invalid_name', __('Service name is required', 'kivicare-clinic-management-system'));
        }

        if (strlen($param) < 2) {
            return new WP_Error('invalid_name', __('Service name must be at least 2 characters long', 'kivicare-clinic-management-system'));
        }

        if (strlen($param) > 100) {
            return new WP_Error('invalid_name', __('Service name cannot exceed 100 characters', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Validate service type
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateType($param)
    {
        $allowed_types = ['consultation', 'procedure', 'diagnostic', 'therapy', 'surgery', 'other'];
        if (!in_array(strtolower($param), $allowed_types)) {
            return new WP_Error('invalid_type', __('Invalid service type', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Validate service price
     *
     * @param mixed $param
     * @return bool|WP_Error
     */
    public function validatePrice($param)
    {
        if (!is_numeric($param) || $param < 0) {
            return new WP_Error('invalid_price', __('Price must be a positive number', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Validate service duration
     *
     * @param mixed $param
     * @return bool|WP_Error
     */
    public function validateDuration($param)
    {
        if (!is_numeric($param) || $param < 0 || $param > 1440) { // Max 24 hours
            return new WP_Error('invalid_duration', __('Duration must be between 1 and 1440 minutes', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Validate service status
     *
     * @param mixed $param
     * @return bool|WP_Error
     */
    public function validateStatus($param)
    {
        if (!in_array(intval($param), [0, 1])) {
            return new WP_Error('invalid_status', __('Status must be 0 (inactive) or 1 (active)', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Validate orderby field
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateOrderBy($param)
    {
        $allowed_fields = [
            'id',
            'serviceId',
            'name',
            'serviceName',
            'clinicName',
            'doctorName',
            'charges',
            'category',
            'type',
            'price',
            'duration',
            'status',
            'created_at'
        ];

        if (!in_array($param, $allowed_fields)) {
            return new WP_Error('invalid_orderby', __('Invalid sort field', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Validate order direction
     *
     * @param string $param
     * @return bool|WP_Error
     */
    public function validateOrder($param)
    {
        if (!in_array(strtolower($param), ['asc', 'desc'])) {
            return new WP_Error('invalid_order', __('Order must be asc or desc', 'kivicare-clinic-management-system'));
        }
        return true;
    }

    /**
     * Validate per page parameter
     *
     * @param mixed $param
     * @return bool|WP_Error
     */
    public function validatePerPage($param)
    {
        $allowed = [5, 10, 25, 50, 100];
        if (strtolower($param) === 'all' || in_array(intval($param), $allowed)) {
            return true;
        }
        return new WP_Error('invalid_per_page', __('Invalid per page value. Allowed values: 5, 10, 25, 50, 100, all', 'kivicare-clinic-management-system'));
    }

    /**
     * Register routes for this controller
     */
    public function registerRoutes()
    {
        // Get all services
        $this->registerRoute('/' . $this->route, [
            'methods' => 'GET',
            'callback' => [$this, 'getServices'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getListEndpointArgs()
        ]);

        // Get single service
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getService'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getSingleEndpointArgs()
        ]);

        // Create service
        $this->registerRoute('/' . $this->route, [
            'methods' => 'POST',
            'callback' => [$this, 'createService'],
            'permission_callback' => [$this, 'checkCreatePermission'],
            'args' => $this->getCreateEndpointArgs()
        ]);

        // Update service
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateService'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getUpdateEndpointArgs()
        ]);

        // Delete service
        $this->registerRoute('/' . $this->route . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteService'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getDeleteEndpointArgs()
        ]);

        // Bulk delete doctor services
        $this->registerRoute('/' . $this->route . '/bulk/delete', [
            'methods' => 'POST',
            'callback' => [$this, 'bulkDeleteDoctorServices'],
            'permission_callback' => [$this, 'checkDeletePermission'],
            'args' => $this->getBulkActionEndpointArgs()
        ]);

        // Bulk update doctor service status
        $this->registerRoute('/' . $this->route . '/bulk/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'bulkUpdateDoctorServiceStatus'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
            'args' => $this->getBulkStatusUpdateEndpointArgs()
        ]);

        // Export services
        $this->registerRoute('/' . $this->route . '/export', [
            'methods' => 'GET',
            'callback' => [$this, 'exportServices'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getExportEndpointArgs()
        ]);
    }

    /**
     * Get arguments for the list endpoint
     *
     * @return array
     */
    private function getListEndpointArgs()
    {
        return [
            'search' => [
                'description' => 'Search term to filter results',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'clinic' => [
                'description' => 'Filter by clinic ID',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
            'status' => $this->getCommonArgs()['status'],
            'category' => [
                'description' => 'ID',
                'type' => 'integer',
                'validate_callback' => [$this, 'validateId'],
                'sanitize_callback' => 'absint',
            ],
            'orderby' => [
                'description' => 'Sort results by specified field',
                'type' => 'string',
                'validate_callback' => [$this, 'validateOrderBy'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'order' => [
                'description' => 'Sort direction (asc or desc)',
                'type' => 'string',
                'validate_callback' => [$this, 'validateOrder'],
                'sanitize_callback' => function ($param) {
                    return strtolower(sanitize_text_field($param));
                },
            ],
            'page' => [
                'description' => 'Current page of results',
                'type' => 'integer',
                'default' => 1,
                'validate_callback' => function ($param) {
                    return is_numeric($param) && $param > 0;
                },
                'sanitize_callback' => 'absint',
            ],
            'perPage' => [
                'description' => 'Number of results per page',
                'type' => 'string',
                'default' => 10,
                'validate_callback' => [$this, 'validatePerPage'],
                'sanitize_callback' => function ($param) {
                    return strtolower($param) === 'all' ? 'all' : absint($param);
                },
            ]
        ];
    }

    /**
     * Get arguments for single item endpoints
     *
     * @return array
     */
    private function getSingleEndpointArgs()
    {
        return [
            'id' => array_merge($this->getCommonArgs()['id'], ['required' => true])
        ];
    }

    /**
     * Get arguments for the create endpoint
     *
     * @return array
     */
    private function getCreateEndpointArgs()
    {
        return [
            'name' => array_merge(
                $this->getCommonArgs()['name'],
                [
                    'required' => true,
                    'sanitize_callback' => function ($param) {
                        return KCdecodeHtmlEntities(sanitize_text_field($param));
                    }
                ]
            ),
            'category' => array_merge($this->getCommonArgs()['type'], ['required' => true]),
            'price' => array_merge($this->getCommonArgs()['price'], ['required' => true]),
            'duration' => array_merge($this->getCommonArgs()['duration']),
            'doctors' => [
                'description' => 'Doctor IDs to assign service to',
                'type' => 'array',
                'required' => true
            ],
            'clinics' => [
                'description' => 'Clinic IDs to assign service to',
                'type' => 'array',
                'required' => true
            ],
            'status' => array_merge($this->getCommonArgs()['status'], ['default' => 1]),
            'profile_image' => [
                'description' => 'Doctor service profile image ID',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && (!is_numeric($param) || $param <= 0)) {
                        return new WP_Error('invalid_image_id', __('Invalid image ID', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
            'telemed_service' => [
                'description' => 'Is telemed service (yes or no)',
                'type' => 'string',
                'enum' => ['yes', 'no'],
                'sanitize_callback' => function ($param) {
                    return in_array($param, ['yes', 'no']) ? $param : 'no';
                },
            ],
            'allow_multi' => [
                'description' => 'Allow multi selection while booking (yes or no)',
                'type' => 'string',
                'enum' => ['yes', 'no'],
                'sanitize_callback' => function ($param) {
                    return in_array($param, ['yes', 'no']) ? $param : 'no';
                },
            ],

        ];
    }

    /**
     * Get arguments for the update endpoint
     *
     * @return array
     */
    private function getUpdateEndpointArgs()
    {
        $args = $this->getCreateEndpointArgs();

        // Remove required flag from all fields for update
        foreach ($args as $key => $arg) {
            if (isset($args[$key]['required'])) {
                unset($args[$key]['required']);
            }
        }

        // Add ID parameter as required
        $args['id'] = array_merge($this->getCommonArgs()['id'], ['required' => true]);

        return $args;
    }

    /**
     * Get arguments for the delete endpoint
     *
     * @return array
     */
    private function getDeleteEndpointArgs()
    {
        return [
            'id' => array_merge($this->getCommonArgs()['id'], ['required' => true])
        ];
    }

    /**
     * Check if user has permission to access service endpoints
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkPermission($request)
    {
        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        return $this->checkResourceAccess('service', 'view');
    }

    /**
     * Check if user has permission to create a service
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkCreatePermission($request)
    {
        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Check service add permission
        return $this->checkResourceAccess('service', 'add');
    }

    /**
     * Check if user has permission to update a service
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkUpdatePermission($request)
    {
        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Check service edit permission
        return $this->checkResourceAccess('service', 'edit');
    }

    /**
     * Check if user has permission to delete a service
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkDeletePermission($request)
    {
        // Check basic read permission first
        if (!$this->checkCapability('read')) {
            return false;
        }

        // Check service delete permission
        return $this->checkResourceAccess('service', 'delete');
    }

    /**
     * Get all services
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getServices(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Process request parameters
            $params = $request->get_params();

            // Build the base query for services
            $query = KCServiceDoctorMapping::table('sdm')
                ->select([
                    'sdm.*',
                    's.name as name',
                    'sd.label as service_type',
                    'sd.id as category_id',
                    's.created_at as created_at',
                    'u.display_name as doctor_name',
                    'u.user_email as doctor_email',
                    'pi.meta_value as doctor_profile_image',
                    'c.name as clinic_name',
                    'c.clinic_logo as clinic_profile_image',
                    'c.email as clinic_email',
                ])
                ->leftJoin(KCService::class, 'sdm.service_id', '=', 's.id', 's')
                ->leftJoin(KCClinic::class, 'sdm.clinic_id', '=', 'c.id', 'c')
                ->leftJoin(KCUser::class, 'u.ID', '=', 'sdm.doctor_id', 'u')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('u.ID', '=', 'fn.user_id')
                        ->onRaw("fn.meta_key = 'first_name'");
                }, null, null, 'fn')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('u.ID', '=', 'ln.user_id')
                        ->onRaw("ln.meta_key = 'last_name'");
                }, null, null, 'ln')
                ->leftJoin(KCUserMeta::class, function ($join) {
                    $join->on('u.ID', '=', 'pi.user_id')
                        ->onRaw("pi.meta_key = 'doctor_profile_image'");
                }, null, null, 'pi')


                ->leftJoin(KCDoctorClinicMapping::class, function ($join) {
                    $join->on('dcm.doctor_id', '=', 'sdm.doctor_id')
                        ->on('dcm.clinic_id', '=', 'sdm.clinic_id');
                }, null, null, 'dcm')
                ->leftJoin(KCStaticData::class, function ($join) {
                    $join->on('s.type', '=', 'sd.value')
                        ->onRaw("sd.type = 'service_type'");
                }, null, null, 'sd');

            $current_user_id = get_current_user_id();
            $current_user_role = $this->kcbase->getLoginUserRole();
            $admin_role = 'administrator';

            // Apply restrictions unless the user is a WordPress administrator
            if ($current_user_role !== $admin_role) {
                if ($current_user_role === $this->kcbase->getClinicAdminRole()) {

                    $clinic_id = KCClinic::getClinicIdOfClinicAdmin($current_user_id);
                    if ($clinic_id) {
                        $query->where('sdm.clinic_id', '=', $clinic_id);
                    } else {
                        // This admin is not assigned to a clinic, so they see nothing.
                        $query->whereRaw('1 = 0');
                    }
                } elseif ($current_user_role === $this->kcbase->getReceptionistRole()) {

                    $clinic_id = KCClinic::getClinicIdOfReceptionist($current_user_id);
                    if ($clinic_id) {
                        $query->where('sdm.clinic_id', '=', $clinic_id);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                } elseif ($current_user_role === $this->kcbase->getDoctorRole()) {

                    // doctor can only see their own services.
                    $query->where('sdm.doctor_id', '=', $current_user_id);
                } else {
                    // Any other role (e.g., patient) should not see any services through this endpoint.
                    $query->whereRaw('1 = 0');
                }
            }

            // Secure the query for doctors
            $current_user_role = $this->kcbase->getLoginUserRole();
            if ($current_user_role === $this->kcbase->getDoctorRole()) {
                $query->where('sdm.doctor_id', '=', get_current_user_id());
            }

            // Apply filters
            if (!empty($params['search'])) {
                $query->where(function ($q) use ($params) {
                    $q->where("u.display_name", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("fn.meta_value", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("ln.meta_value", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("sdm.id", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("s.name", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("sd.id", '=', $params['search'])
                        ->orWhere("sdm.charges", 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere("sdm.status", '=', $params['search']);
                });
            }

            if (isset($params['status']) && $params['status'] !== '') {
                $query->where("sdm.status", '=', $params['status']);
            }

            if (isset($params['id']) && $params['id'] !== '') {
                $query->where("sdm.id", 'LIKE', '%' . $params['id'] . '%');
            }

            if (isset($params['serviceName']) && $params['serviceName'] !== '') {
                $query->where("s.name", 'LIKE', '%' . $params['serviceName'] . '%');
            }

            if (isset($params['clinicName']) && $params['clinicName'] !== '') {
                $query->where("c.name", 'LIKE', '%' . $params['clinicName'] . '%');
            }

            if (isset($params['doctorName']) && $params['doctorName'] !== '') {
                $query->where(function ($q) use ($params) {
                    $q->where("u.display_name", 'LIKE', '%' . $params['doctorName'] . '%')
                        ->orWhere("fn.meta_value", 'LIKE', '%' . $params['doctorName'] . '%')
                        ->orWhere("ln.meta_value", 'LIKE', '%' . $params['doctorName'] . '%');
                });
            }

            if (isset($params['charges']) && $params['charges'] !== '') {
                $query->where('sdm.charges', $params['charges']);
            }

            if (isset($params['category']) && $params['category'] !== '') {
                $query->where("sd.id", '=', $params['category']);
            }

            if (isset($params['clinic_id']) && $params['clinic_id'] !== '') {
                $query->where("c.id", '=', $params['clinic_id']);
            }

            if (isset($params['doctor_id']) && $params['doctor_id'] !== '') {
                $query->where("dcm.doctor_id", '=', $params['doctor_id']);
            }

            // if (isset($params['duration']) && $params['duration'] !== '') {
            //     $query->where("sdm.duration", '=', $params['duration']);
            // }

            // Apply sorting if orderby parameter is provided
            if (!empty($params['orderby'])) {
                $orderby = $params['orderby'];
                $direction = !empty($params['order']) && strtolower($params['order']) === 'desc' ? 'DESC' : 'ASC';

                switch ($orderby) {
                    case 'serviceId':
                        $query->orderBy("sdm.service_id", $direction);
                        break;
                    case 'serviceName':
                    case 'name':
                        $query->orderBy("s.name", $direction);
                        break;
                    case 'clinicName':
                        $query->orderBy("c.name", $direction);
                        break;
                    case 'doctorName':
                        $query->orderBy("u.display_name", $direction);
                        break;
                    case 'charges':
                    case 'price':
                        $query->orderBy("CAST(sdm.charges AS DECIMAL(10,2))", $direction);
                        break;
                    case 'duration':
                        $query->orderBy("sdm.duration", $direction);
                        break;
                    case 'category':
                    case 'type':
                        $query->orderBy("sd.label", $direction);
                        break;
                    case 'status':
                        $query->orderBy("sdm.status", $direction);
                        break;
                    case 'id':
                    default:
                        $query->orderBy("sdm.id", $direction);
                        break;
                }
            } else {
                // Default sorting by id descending if no sort specified
                $query->orderBy("sdm.id", 'DESC');
            }

            // Get total count for pagination
            $totalQuery = clone $query;
            $total = $totalQuery->count();

            // Apply pagination with validation
            $page = isset($params['page']) ? (int) $params['page'] : 1;
            $perPageParam = isset($params['perPage']) ? $params['perPage'] : 10;

            // Handle "all" option for perPage
            $showAll = (strtolower($perPageParam) === 'all');
            $perPage = $showAll ? null : (int) $perPageParam;
            $perPage = isset($params['perPage']) ? (int) $params['perPage'] : 10;

            if ($perPage <= 0) {
                $perPage = 10;
            }

            $page = isset($params['page']) ? (int) $params['page'] : 1;
            if ($page <= 0) {
                $page = 1;
            }

            if ($showAll) {
                $perPage = $total > 0 ? $total : 1;
                $page = 1;
            }

            $totalPages = $total > 0 ? ceil($total / $perPage) : 1;
            $offset = ($page - 1) * $perPage;

            if (!$showAll) {
                $query->limit($perPage)->offset($offset);
            }

            // Get paginated results
            $services = $query->get();

            // Prepare the service data
            $servicesData = [];
            foreach ($services as $service) {

                // Format service data
                $serviceData = [
                    'id' => $service->id,
                    'serviceId' => $service->serviceId,
                    'serviceName' => $service->name,
                    'clinicId' => $service->clinicId,
                    'clinicName' => $service->clinic_name,
                    'clinicProfileImage' => $service->clinic_profile_image ? wp_get_attachment_url($service->clinic_profile_image) : '',
                    'clinicEmail' => $service->clinic_email,
                    'doctorId' => $service->doctorId,
                    'doctorName' => $service->doctor_name,
                    'doctorEmail' => $service->doctor_email,
                    'doctorProfileImage' => $service->doctor_profile_image ? wp_get_attachment_url($service->doctor_profile_image) : '',
                    'charges' => $service->charges,
                    'duration' => $this->formatDurationInMinutes($service->duration),
                    'category' => $service->service_type,
                    'category_id' => $service->category_id,
                    'status' => $service->status,
                    'telemedService' => $service->telemedService,
                    'allowMultiple' => $service->multiple,
                    'service_image_url' => $service->image ? wp_get_attachment_url($service->image) : '',
                    'extra' => $service->extra,
                    'created_at' => $service->createdAt,
                ];

                $servicesData[] = $serviceData;
            }

            // Return the formatted data with pagination
            $data = [
                'doctorServices' => $servicesData,
                'pagination' => [
                    'total' => $total,
                    'perPage' => $perPage,
                    'currentPage' => $page,
                    'lastPage' => $totalPages,
                ],
            ];

            return $this->response($data, __('Services retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve services', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Get single service by ID
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getService(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');

            if (empty($id) || !is_numeric($id)) {
                return $this->response(
                    ['error' => 'Invalid ID'],
                    __('Invalid ID provided', 'kivicare-clinic-management-system'),
                    false,
                    400
                );
            }

            $serviceQuery = KCServiceDoctorMapping::table('sdm')
                ->select([
                    'sdm.*',
                    's.name as name',
                    's.type as service_type',
                    'sd.label as service_type_label',
                    'sd.id as category',
                    'sd.value as category_value',
                    'c.name as clinic_name',
                    'c.profile_image as clinic_profile_image',
                    'u.display_name as doctor_name'
                ])
                ->leftJoin(KCService::class, 'sdm.service_id', '=', 's.id', 's')
                ->leftJoin(KCClinic::class, 'sdm.clinic_id', '=', 'c.id', 'c')
                ->leftJoin(KCUser::class, 'u.ID', '=', 'sdm.doctor_id', 'u')
                ->leftJoin(KCStaticData::class, 's.type', '=', 'sd.value', 'sd')
                ->where('sdm.id', '=', $id);

            $service = $serviceQuery->first();

            if (!$service) {
                return $this->response(
                    null,
                    __('Service not found', 'kivicare-clinic-management-system'),
                    false,
                    404
                );
            }

            // Format clinics and doctors arrays
            $clinicImageId = $service->clinic_profile_image ?: null;
            $clinicImageUrl = $clinicImageId ? wp_get_attachment_url($clinicImageId) : '';

            $clinicsArray = [
                [
                    'value' => $service->clinicId,
                    'label' => $service->clinic_name,
                    'clinic_image' => $clinicImageUrl,
                ]
            ];

            $doctorsArray = [
                [
                    'value' => $service->doctorId,
                    'label' => $service->doctor_name
                ]
            ];

            $access_check = $this->canUserAccessService($service);
            if (is_wp_error($access_check)) {
                return $this->response(null, $access_check->get_error_message(), false, 403);
            }

            // Format service data
            $serviceData = [
                'id' => $service->id,
                'service_id' => $service->serviceId,
                'type' => $service->category,
                'service_category' => ['label' => $service->service_type_label, 'id' => $service->category, 'value' => $service->category_value],
                'name' => $service->name,
                'price' => $service->charges,
                'telemed_service' => $service->telemedService,
                'allow_multi' => $service->multiple,
                'doctor_id' => $service->doctorId,
                'clinic_id' => $service->clinicId,
                'clinics' => $clinicsArray,
                'doctors' => $doctorsArray,
                'duration' => $service->duration,
                'status' => $service->status,
                'service_image_url' => $service->image ? wp_get_attachment_url($service->image) : '',
                'service_image_id' => $service->image,
            ];

            return $this->response($serviceData, __('Service retrieved successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to retrieve service', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Get arguments for bulk action endpoints.
     *
     * Returns the argument schema for bulk actions such as bulk delete.
     * Expects an 'ids' parameter as an array of doctor service IDs.
     *
     * @return array The argument schema for bulk action endpoints.
     *
     */
    private function getBulkActionEndpointArgs()
    {
        return [
            'ids' => [
                'description' => 'Array of doctor service IDs',
                'type' => 'array',
                'required' => true,
                'items' => [
                    'type' => 'integer',
                ],
                'validate_callback' => function ($param) {
                    return is_array($param) && count($param) > 0;
                }
            ]
        ];
    }

    /**
     * Get arguments for bulk status update endpoint.
     *
     * Returns the argument schema for bulk status update actions.
     * Expects an 'ids' parameter as an array of doctor service IDs and a 'status' parameter as the new status (0 or 1).
     *
     * @return array The argument schema for bulk status update endpoint.
     *
     */
    private function getBulkStatusUpdateEndpointArgs()
    {
        return array_merge(
            $this->getBulkActionEndpointArgs(),
            [
                'status' => [
                    'description' => 'Status to set (0: Inactive, 1: Active)',
                    'type' => 'integer',
                    'required' => true,
                    'validate_callback' => [$this, 'validateStatus'],
                    'sanitize_callback' => 'absint',
                ]
            ]
        );
    }

    /**
     * Bulk delete doctor services.
     *
     * Deletes multiple doctor service mappings based on an array of IDs.
     *
     * @param WP_REST_Request $request The REST API request object. Expects 'ids' as an array of service mapping IDs.
     * @return WP_REST_Response Returns a response with the number of deleted services and a success message,
     *                          or an error message and status code on failure.
     *
     */
    public function bulkDeleteDoctorServices(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $ids = $request->get_param('ids');
            $success = 0;
            foreach ($ids as $id) {
                $service = KCServiceDoctorMapping::find($id);
                if ($service) {
                    $service->delete();
                    $success++;
                }
            }
            return $this->response(
                ['deleted' => $success],
                /* translators: %d: number of doctor services */
                sprintf(__('%d doctor service(s) deleted successfully', 'kivicare-clinic-management-system'), $success)
            );
        } catch (\Exception $e) {
            return $this->response(['error' => $e->getMessage()], __('Failed to delete doctor services', 'kivicare-clinic-management-system'), false, 500);
        }
    }

    /**
     * Bulk update doctor service status.
     *
     * Updates the status (active/inactive) for multiple doctor service mappings based on an array of IDs.
     *
     * @param WP_REST_Request $request The REST API request object. Expects 'ids' as an array of service mapping IDs and 'status' as the new status (0 or 1).
     * @return WP_REST_Response Returns a response with the number of updated services and a success message,
     *                          or an error message and status code on failure.
     *
     */
    public function bulkUpdateDoctorServiceStatus(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $ids = $request->get_param('ids');
            $status = $request->get_param('status');
            $success = 0;
            foreach ($ids as $id) {
                $service = KCServiceDoctorMapping::find($id);
                if ($service) {
                    $service->status = $status;
                    $service->save();
                    $success++;
                }
            }
            return $this->response(
                ['updated' => $success],
                /* translators: %d: number of doctor services */
                sprintf(__('%d doctor service(s) updated successfully', 'kivicare-clinic-management-system'), $success)
            );
        } catch (\Exception $e) {
            return $this->response(['error' => $e->getMessage()], __('Failed to update doctor services status', 'kivicare-clinic-management-system'), false, 500);
        }
    }

    /**
     * Create new doctor service
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function createService(WP_REST_Request $request): WP_REST_Response
    {
        try {

            $params = $request->get_params();

            // Determine clinic ID based on user role
            $current_user_role = $this->kcbase->getLoginUserRole();
            $current_user_id = get_current_user_id();

            if ($current_user_role === 'administrator') {
                // Admin can specify clinics
                $clinic_id = $params['clinics'];
            } else if (isKiviCareProActive()) {
                if ($current_user_role === $this->kcbase->getClinicAdminRole()) {
                    $clinic_id = KCClinic::getClinicIdOfClinicAdmin($current_user_id);
                } elseif ($current_user_role === $this->kcbase->getReceptionistRole()) {
                    $clinic_id = KCClinic::getClinicIdOfReceptionist($current_user_id);
                } elseif ($current_user_role === $this->kcbase->getDoctorRole()) {
                    $clinic_id = KCClinic::getClinicIdOfDoctor($current_user_id);
                } else {
                    $clinic_id = [];
                }
            } else {
                // Default clinic id if pro not active (single clinic mode)
                $clinic_id = KCClinic::kcGetDefaultClinicId();
            }

            if ($current_user_role == $this->kcbase->getDoctorRole()) {
                $doctor_id = get_current_user_id();
            } else {
                $doctor_id = $params['doctors'];
            }

            // Build the base query for get service_type
            $type = KCStaticData::table('sd')
                ->select([
                    'value'
                ])
                ->where('id', '=', $params['category'])
                ->first();

            $serviceData = [
                'name' => $params['name'],
                'type' => $type->value,
                'price' => $params['price'],
                'status' => $params['status'],
                'createdAt' => current_time('mysql')
            ];

            // Create the clinic
            $service_id = KCService::create($serviceData);

            if (!$service_id) {
                return $this->response(null, __('Failed to create doctor service', 'kivicare-clinic-management-system'), false, 500);
            } else {

                $clinic_ids = [];
                if (is_array($clinic_id)) {
                    foreach ($clinic_id as $clinic) {
                        // Handles array of objects like { 'value': 1 } from the frontend select component.
                        if (is_array($clinic) && isset($clinic['value'])) {
                            $clinic_ids[] = (int) $clinic['value'];
                        }
                        // Handles a simple array of IDs like [1, 2] from backend functions (e.g., KCClinic::getClinicIdOfDoctor).
                        elseif (is_numeric($clinic)) {
                            $clinic_ids[] = (int) $clinic;
                        }
                    }
                } elseif (is_numeric($clinic_id)) {
                    // Handles a single numeric ID.
                    $clinic_ids[] = (int) $clinic_id;
                }

                // Filter out duplicates and zero-values, then create the final string for the query.
                $clinic_id = implode(',', array_unique(array_filter($clinic_ids)));

                $doctor_ids = [];

                if (is_array($doctor_id)) {
                    foreach ($doctor_id as $doctor) {
                        if (isset($doctor['value'])) {
                            $doctor_ids[] = (int) $doctor['value'];
                        }
                    }
                } else {
                    $doctor_ids[] = (int) $doctor_id;
                }

                $doctor_id = implode(',', $doctor_ids);

                // check for empty IDs before running queries
                if (empty($clinic_id) || empty($doctor_id)) {
                    return $this->response(
                        null,
                        __('The selected doctors are not associated with the selected clinics. Please correct your selection.', 'kivicare-clinic-management-system'),
                        false,
                        400
                    );
                }

                $existingServiceMappings = KCServiceDoctorMapping::table('sdm')
                    ->select([
                        "sdm.id"
                    ])
                    ->leftJoin(KCService::class, 'sdm.service_id', '=', 's.id', 's')
                    ->where(function ($q) use ($type, $params, $clinic_id, $doctor_id) {
                        $q->whereRaw("s.type = '" . esc_sql($type->value) . "'")
                            ->whereRaw("s.name = '" . esc_sql($params['name']) . "'")
                            ->whereRaw("sdm.clinic_id IN ($clinic_id)")
                            ->whereRaw("sdm.doctor_id IN ($doctor_id)");
                    })
                    ->groupBy('sdm.ID')
                    ->get();

                if (count($existingServiceMappings)) {
                    return $this->response($serviceData, __('Same Service Already Exists,Please select Different category or service name', 'kivicare-clinic-management-system'), false, 500);
                }

                $clinic_doctors_raw = KCDoctorClinicMapping::table('dcm')
                    ->select([
                        "GROUP_CONCAT(DISTINCT doctor_id) as doctor_ids",
                        "clinic_id"
                    ])
                    ->where(function ($q) use ($clinic_id, $doctor_id) {
                        $q->whereRaw("clinic_id IN ($clinic_id)")
                            ->whereRaw("doctor_id IN ($doctor_id)");
                    })
                    ->groupBy('clinic_id')
                    ->get();

                if ($clinic_doctors_raw->isEmpty()) {
                    return $this->response(
                        null,
                        __('The selected doctors are not associated with the selected clinics. Please correct your selection.', 'kivicare-clinic-management-system'),
                        false,
                        400
                    );
                }

                $clinic_doctors = $clinic_doctors_raw
                    ->keyBy(function ($item) {
                        return $item->clinic_id ?? $item->clinicId;
                    })
                    ->map(function ($v) {
                        return explode(',', $v->doctor_ids);
                    })
                    ->toArray();

                foreach ($clinic_doctors as $clinic_id_loop => $clinic_doctor_val) {
                    foreach ($clinic_doctor_val as $doctor) {
                        $serviceMappingData = [
                            'serviceId' => $service_id,
                            'clinicId' => (int) $clinic_id_loop,
                            'doctorId' => (int) $doctor,
                            'charges' => $params['price'],
                            'status' => $params['status'],
                            'image' => !empty($params['profile_image']) ? (int) $params['profile_image'] : '',
                            'multiple' => $params['allow_multi'],
                            'telemedService' => $params['telemed_service'],
                            'serviceNameAlias' => $type->value,
                            'createdAt' => current_time('mysql'),
                        ];

                        if (isKiviCareProActive() && !empty($params['duration'])) {
                            $serviceMappingData['duration'] = $params['duration'];
                        }
                        // Create the service-doctor mapping
                        KCServiceDoctorMapping::create($serviceMappingData);
                    }
                }

                $serviceData = [
                    'id' => $service_id,
                    'name' => $params['name'],
                    'type' => $type->value,
                    'price' => $params['price'],
                    'telemed_service' => $params['telemed_service'],
                    'allow_multi' => $params['allow_multi'],
                    'duration' => $params['duration'],
                    'status' => $params['status'],
                    'created_at' => current_time('mysql'),
                    'clinic_doctor' => $clinic_doctors_raw,
                    'service_image_url' => $params['profile_image'] ? wp_get_attachment_url($params['profile_image']) : '',
                ];

                // hook for service add.
                do_action('kc_service_add', $serviceData);

                // Save clinic and doctor mappings if needed (implement as per your DB structure)
                return $this->response($serviceData, __('Service created successfully', 'kivicare-clinic-management-system'), true, 201);
            }
        } catch (\Exception $e) {
            return $this->response(['error' => $e->getMessage()], __('Failed to create doctor service', 'kivicare-clinic-management-system'), false, 500);
        }
    }

    /**
     * Update existing doctor service
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function updateService(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();
            $id = $request->get_param('id');

            // Find the service mapping
            $serviceMapping = KCServiceDoctorMapping::find($id);
            if (!$serviceMapping) {
                return $this->response(null, __('Service not found', 'kivicare-clinic-management-system'), false, 404);
            }

            $access_check = $this->canUserAccessService($serviceMapping);
            if (is_wp_error($access_check)) {
                return $this->response(null, $access_check->get_error_message(), false, 403);
            }

            // Find the main service
            $service = KCService::find($serviceMapping->serviceId);
            if (!$service) {
                return $this->response(null, __('Service record not found', 'kivicare-clinic-management-system'), false, 404);
            }
            $type = KCStaticData::table('sd')
                ->select([
                    'value'
                ])
                ->where('id', '=', $params['category'])
                ->first();
            // Try to find existing service with the same name and type
            $existingService = KCService::query()
                ->where('name', '=', $params['name'])
                ->where('type', '=', $type->value)
                ->first();

            if ($existingService) {
                $service = $existingService;
                // Optionally update service metadata to keep it in sync
                $service->name = $params['name'];
                // Only update price if provided and greater than zero to avoid overwriting meaningful defaults
                if (isset($params['price'])) {
                    $service->price = $params['price'];
                }
                $service->save();
            } else {
                // Create a new KCService record and point mapping to it
                $serviceData = [
                    'name' => $params['name'],
                    'type' => $type->value,
                    'price' => $params['price'] ?? 0,
                    'status' => $params['status'] ?? 1,
                    'createdAt' => current_time('mysql')
                ];
                $createdServiceId = KCService::create($serviceData);
                if (!$createdServiceId) {
                    return $this->response(null, __('Failed to create service record', 'kivicare-clinic-management-system'), false, 500);
                }
                $service = KCService::find($createdServiceId);
            }

            $current_user_role = $this->kcbase->getLoginUserRole();
            $current_user_id = get_current_user_id();
            $clinic_id = null; // Initialize clinic_id

            if ($current_user_role === 'administrator') {
                // Handle different formats of clinic parameter (array, object, or direct value)
                if (isset($params['clinics'][0]['value'])) {
                    $clinic_id = (int) $params['clinics'][0]['value'];
                } elseif (isset($params['clinics']['value'])) {
                    $clinic_id = (int) $params['clinics']['value'];
                } elseif (isset($params['clinics']) && is_numeric($params['clinics'])) {
                    $clinic_id = (int) $params['clinics'];
                } else {
                    $clinic_id = $serviceMapping->clinicId;
                }
            } else if (isKiviCareProActive()) {
                if ($current_user_role === $this->kcbase->getClinicAdminRole()) {
                    $clinic_id = KCClinic::getClinicIdOfClinicAdmin($current_user_id);
                } elseif ($current_user_role === $this->kcbase->getReceptionistRole()) {
                    $clinic_id = KCClinic::getClinicIdOfReceptionist($current_user_id);
                } else {
                    $clinic_id = $serviceMapping->clinicId;
                }
            } else {
                // Default clinic ID if pro is not active (single clinic mode)
                $clinic_id = KCClinic::kcGetDefaultClinicId();
            }

            $doctor_id = null;
            if ($current_user_role === $this->kcbase->getDoctorRole()) {
                $doctor_id = get_current_user_id();
            } elseif (isset($params['doctors'][0]['value'])) {
                $doctor_id = $params['doctors'][0]['value'];
            } elseif (isset($params['doctors']['value'])) {
                $doctor_id = $params['doctors']['value'];
            }

            // Prevent duplicate service for same doctor/clinic/type/name (except current mapping)
            $existing = KCServiceDoctorMapping::table('map')
                ->leftJoin(KCService::class, 'map.service_id', '=', 'ser.id', 'ser')
                ->leftJoin(KCStaticData::class, 'ser.type', '=', 'sd.value', 'sd')
                ->where('map.doctor_id', '=', $doctor_id)
                ->where('map.clinic_id', '=', $clinic_id)
                ->where('sd.id', '=', $params['category']) // Compare by static data id directly
                ->where('ser.name', '=', $params['name'])
                ->where('map.id', '!=', $id)
                ->first();

            if ($existing) {
                return $this->response(
                    null,
                    __('Same Service Already Exists, Please select Different category or service name', 'kivicare-clinic-management-system'),
                    false,
                    409
                );
            }

            // Update main service
            $service->name = $params['name'];
            $service->save();

            // Prepare mapping update data
            $serviceMapping->id = $id;
            $serviceMapping->serviceId = $service->id;
            $serviceMapping->clinicId = $clinic_id;
            $serviceMapping->doctorId = $doctor_id;
            $serviceMapping->charges = $params['price'];
            $serviceMapping->status = $params['status'];
            $serviceMapping->multiple = $params['allow_multi'];
            $serviceMapping->telemedService = $params['telemed_service'];
            $serviceMapping->duration = $params['duration'];

            // Handle service image update/removal
            if (!empty($params['profile_image'])) {
                $serviceMapping->image = (int) $params['profile_image'];
            } else {
                $serviceMapping->image = null;
            }

            $serviceMapping->save();

            // Prepare response data
            $serviceData = [
                'id' => $serviceMapping->id,
                'service_id' => $service->id,
                'type' => $service->type,
                'name' => $service->name,
                'price' => $service->price,
                'telemed_service' => $serviceMapping->telemedService,
                'allow_multi' => $serviceMapping->multiple,
                'doctor_id' => $serviceMapping->doctorId,
                'clinic_id' => $serviceMapping->clinicId,
                'duration' => $serviceMapping->duration,
                'status' => $serviceMapping->status,
                'service_image_url' => $serviceMapping->image ? wp_get_attachment_url($serviceMapping->image) : '',
                'service_image_id' => $serviceMapping->image,
                'updated_at' => current_time('mysql')
            ];

            // hook for service update.
            do_action('kc_service_update', $serviceData);

            return $this->response($serviceData, __('Service updated successfully', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to update service', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }

    /**
     * Delete doctor service and all related data
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function deleteService(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');

            // Find the service mapping
            $serviceMapping = KCServiceDoctorMapping::find($id);
            if (!$serviceMapping) {
                return $this->response(null, __('Service not found', 'kivicare-clinic-management-system'), false, 404);
            }

            $access_check = $this->canUserAccessService($serviceMapping);
            if (is_wp_error($access_check)) {
                return $this->response(null, $access_check->get_error_message(), false, 403);
            }

            // Delete service data
            $deleted = $serviceMapping->delete();

            if (!$deleted) {
                return $this->response(null, __('Failed to delete service', 'kivicare-clinic-management-system'), false, 500);
            }

            do_action('kc_service_delete', $id);

            return $this->response(
                ['id' => $id],
                __('Service deleted successfully', 'kivicare-clinic-management-system')
            );
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to delete service', 'kivicare-clinic-management-system') . ': ' . $e->getMessage(),
                false,
                500
            );
        }
    }

    /**
     * Get arguments for the export endpoint
     *
     * @return array
     */
    private function getExportEndpointArgs()
    {
        return [
            'format' => [
                'description' => 'Export format (csv, xls, pdf)',
                'type' => 'string',
                'required' => true,
                'validate_callback' => function ($param) {
                    if (!in_array($param, ['csv', 'xls', 'pdf'])) {
                        return new WP_Error('invalid_format', __('Format must be csv, xls, or pdf', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'search' => [
                'description' => 'Search term to filter results',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'clinic' => [
                'description' => 'Filter by clinic ID',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && (!is_numeric($param) || $param <= 0)) {
                        return new WP_Error('invalid_clinic_id', __('Invalid clinic ID', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
            'status' => [
                'description' => 'Filter by status (0: Inactive, 1: Active)',
                'type' => 'integer',
                'validate_callback' => function ($param) {
                    if (!empty($param) && !in_array(intval($param), [0, 1])) {
                        return new WP_Error('invalid_status', __('Status must be 0 (inactive) or 1 (active)', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Export services data
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function exportServices(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();

            $query = KCServiceDoctorMapping::table('sdm')
                ->select([
                    'sdm.id',
                    'sdm.charges',
                    'sdm.telemed_service',
                    'sdm.multiple as allow_multiple',
                    'sdm.image',
                    'sdm.status',
                    's.name as name',
                    's.type as service_category_id_val',
                    'sd.label as service_category',
                    'u.ID as doctor_id',
                    'u.display_name as doctor_name',
                ])
                ->leftJoin(KCService::class, 'sdm.service_id', '=', 's.id', 's')
                ->leftJoin(KCStaticData::class, 's.type', '=', 'sd.id', 'sd')
                ->leftJoin(KCUser::class, 'sdm.doctor_id', '=', 'u.ID', 'u')
                ->where('s.status', '=', 1);

            // Apply filters based on the JOINed data
            if (!empty($params['search'])) {
                $search = sanitize_text_field($params['search']);
                $query->where(function ($q) use ($search) {
                    $q->where('s.name', 'LIKE', '%' . $search . '%')
                        ->orWhere('u.display_name', 'LIKE', '%' . $search . '%')
                        ->orWhere('sd.label', 'LIKE', '%' . $search . '%');
                });
            }

            if (isset($params['status']) && $params['status'] !== '') {
                $query->where('sdm.status', '=', (int) $params['status']);
            }

            if (isset($params['clinic']) && !empty($params['clinic'])) {
                $query->where('sdm.clinic_id', '=', (int) $params['clinic']);
            }

            $results = $query->get();

            if ($results->isEmpty()) {
                return $this->response(
                    ['services' => []],
                    __('No services found to export', 'kivicare-clinic-management-system'),
                    true
                );
            }

            $exportData = $results->map(function ($service) {
                return [
                    'id' => $service->id ?? '',
                    'name' => $service->name ?? '',
                    'service_category' => $service->service_category ?? '',
                    'service_category_id' => $service->service_category_id_val ?? '',
                    'doctor_name' => $service->doctor_name ?? '',
                    'doctor_id' => $service->doctor_id ?? '',
                    'charges' => $service->charges ?? '',
                    'telemed_service' => ($service->telemed_service === 'yes') ? 'Yes' : 'No',
                    'allow_multiple' => ($service->allow_multiple === 'yes') ? 'Yes' : 'No',
                    'image' => $service->image ? wp_get_attachment_url($service->image) : '',
                    'status' => $service->status == 1 ? 'Active' : 'Inactive',
                ];
            })->toArray();

            return $this->response(
                ['services' => $exportData],
                __('Services data retrieved successfully for export', 'kivicare-clinic-management-system')
            );
        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to export services data', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    /**
     * Unified security check to determine if the current user can access a specific service mapping.
     *
     * @param object $serviceMapping The service mapping object to check.
     * @return bool|\WP_Error True if access is allowed, WP_Error otherwise.
     */
    private function canUserAccessService($serviceMapping)
    {
        $current_user_id = get_current_user_id();
        $current_user_role = $this->kcbase->getLoginUserRole();

        if ($current_user_role === 'administrator') {
            return true;
        }

        if ($current_user_role === $this->kcbase->getDoctorRole()) {
            if ($serviceMapping->doctorId === $current_user_id) {
                return true;
            }
        }

        if (in_array($current_user_role, [$this->kcbase->getClinicAdminRole(), $this->kcbase->getReceptionistRole()])) {
            $user_clinic_id = 0;
            if ($current_user_role === $this->kcbase->getClinicAdminRole()) {
                $user_clinic_id = KCClinic::getClinicIdOfClinicAdmin($current_user_id);
            } else {
                $user_clinic_id = KCClinic::getClinicIdOfReceptionist($current_user_id);
            }

            if ($user_clinic_id > 0 && (int) $serviceMapping->clinicId === (int) $user_clinic_id) {
                return true;
            }
        }

        return new \WP_Error(
            'kc_permission_denied',
            __('You do not have permission to access this service resource.', 'kivicare-clinic-management-system'),
            ['status' => 403]
        );
    }

    /**
     * Formats a total number of minutes into a human-readable string (e.g., "1 hr 30 min").
     *
     * @param int|null $totalMinutes The total minutes to format.
     * @return string The formatted duration string.
     */
    private function formatDurationInMinutes($totalMinutes)
    {
        if (!is_numeric($totalMinutes) || $totalMinutes < 0) {
            return __('N/A', 'kivicare-clinic-management-system');
        }

        if ($totalMinutes == 0) {
            return '0 ' . __('min', 'kivicare-clinic-management-system');
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = "{$hours} " . __('hr', 'kivicare-clinic-management-system');
        }

        if ($minutes > 0) {
            $parts[] = "{$minutes} " . __('min', 'kivicare-clinic-management-system');
        }

        return implode(' ', $parts);
    }
}
