<?php

namespace App\controllers\api\SettingsController;

use App\controllers\api\SettingsController;
use App\models\KCClinic;
use App\models\KCStaticData;
use App\models\KCDoctorClinicMapping;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Class ListingData
 * @package App\controllers\api\SettingsController
 */
class ListingData extends SettingsController
{
    private static ?self $instance = null;
    protected $route = 'settings/listing';

    public function __construct()
    {
        parent::__construct();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function registerRoutes(): void
    {
        $this->registerRoute("/{$this->route}", [
            'methods' => 'GET',
            'callback' => [$this, 'getListingData'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getListingEndpointArgs(),
        ]);

        $this->registerRoute("/{$this->route}/static-data", [
            'methods' => 'GET',
            'callback' => [$this, 'getListingData'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getListingEndpointArgs(),
        ]);

        $this->registerRoute("/{$this->route}/edit", [
            'methods' => 'GET',
            'callback' => [$this, 'dataEdit'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        $this->registerRoute("/{$this->route}/delete", [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'dataDelete'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
        ]);

        $this->registerRoute("/{$this->route}/update", [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'dataUpdate'],
            'permission_callback' => [$this, 'checkUpdatePermission'],
        ]);

        // Export Listings
        $this->registerRoute("/{$this->route}/export", [
            'methods' => 'GET',
            'callback' => [$this, 'exportListings'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getExportEndpointArgs()
        ]);
    }

    /**
     * Get arguments for listing endpoints.
     *
     * @return array
     */
    private function getListingEndpointArgs(): array
    {
        return [
            'searchTerm' => [
                'description'       => __('Search term to filter listing data.', 'kivicare-clinic-management-system'),
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'type' => [
                'description'       => __('Static data type filter.', 'kivicare-clinic-management-system'),
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'description'       => __('Filter by status. Use 0 for inactive or 1 for active.', 'kivicare-clinic-management-system'),
                'type'              => ['integer', 'string', 'null'],
                'required'          => false,
                'validate_callback' => function ($param) {
                    if ($param === null || $param === '') {
                        return true;
                    }

                    if (!is_numeric($param) || !in_array((int) $param, [0, 1], true)) {
                        return new WP_Error('invalid_status', __('Status must be 0 (inactive) or 1 (active).', 'kivicare-clinic-management-system'));
                    }

                    return true;
                },
                'sanitize_callback' => [$this, 'sanitizeListingStatusArg'],
            ],
            'page' => [
                'description'       => __('Current pagination page.', 'kivicare-clinic-management-system'),
                'type'              => 'integer',
                'required'          => false,
                'default'           => 1,
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
            ],
            'perPage' => [
                'description'       => __('Number of records per page.', 'kivicare-clinic-management-system'),
                'type'              => 'integer',
                'required'          => false,
                'default'           => 10,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
            ],
            'sort' => [
                'description'       => __('Sort configuration JSON. Example: [{"field":"id","type":"desc"}].', 'kivicare-clinic-management-system'),
                'type'              => ['string', 'array'],
                'required'          => false,
                'validate_callback' => [$this, 'validateListingSortArg'],
                'sanitize_callback' => [$this, 'sanitizeListingSortArg'],
            ],
        ];
    }

    /**
     * Sanitize listing status while preserving empty/null as no filter.
     *
     * @param mixed $param Status request parameter.
     * @return int|string|null
     */
    public function sanitizeListingStatusArg($param)
    {
        if ($param === null || $param === '') {
            return $param;
        }

        return (int) $param;
    }

    /**
     * Validate the sort argument used by listing endpoints.
     *
     * @param mixed $param Sort request parameter.
     * @return true|WP_Error
     */
    public function validateListingSortArg($param)
    {
        if (empty($param)) {
            return true;
        }

        $sort = $this->parseListingSortArg($param);
        if (empty($sort)) {
            return new WP_Error('invalid_sort', __('Sort must be valid JSON.', 'kivicare-clinic-management-system'));
        }

        $field = $sort['field'] ?? '';
        $type = strtolower($sort['type'] ?? '');

        if ($field === '' || $type === '' || $type === 'none') {
            return true;
        }

        if (!in_array($field, $this->getListingSortableFields(), true)) {
            return new WP_Error('invalid_sort_field', __('Invalid sort field.', 'kivicare-clinic-management-system'));
        }

        if (!in_array($type, ['asc', 'desc'], true)) {
            return new WP_Error('invalid_sort_type', __('Sort type must be asc, desc, or none.', 'kivicare-clinic-management-system'));
        }

        return true;
    }

    /**
     * Sanitize the sort argument while preserving the existing frontend format.
     *
     * @param mixed $param Sort request parameter.
     * @return array|string
     */
    public function sanitizeListingSortArg($param)
    {
        if (empty($param)) {
            return [];
        }

        $sort = $this->parseListingSortArg($param);
        if (empty($sort)) {
            return [];
        }

        $field = sanitize_text_field($sort['field'] ?? '');
        $type = strtolower(sanitize_key($sort['type'] ?? ''));

        if (!in_array($field, $this->getListingSortableFields(), true) || !in_array($type, ['asc', 'desc', 'none'], true)) {
            return [];
        }

        return [wp_json_encode([
            'field' => $field,
            'type' => $type,
        ])];
    }

    /**
     * Parse sort argument from array/string input.
     *
     * @param mixed $param Sort request parameter.
     * @return array
     */
    private function parseListingSortArg($param): array
    {
        $rawSort = is_array($param) ? reset($param) : $param;
        if (!is_string($rawSort) || trim($rawSort) === '') {
            return [];
        }

        $decoded = json_decode(stripslashes($rawSort), true);
        return is_array($decoded) ? kcRecursiveSanitizeTextField($decoded) : [];
    }

    /**
     * Allowed sortable database fields for listing data.
     *
     * @return string[]
     */
    private function getListingSortableFields(): array
    {
        return ['id', 'type', 'value', 'label', 'status', 'createdAt'];
    }

    public function getListingData(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();
        $query = KCStaticData::query();

        $status = null;
        $searchTerm = strtolower(trim($params['searchTerm'] ?? ''));

        if ($searchTerm !== '') {
            if (preg_match('/:(active|inactive)/i', $searchTerm, $matches)) {
                $status = $matches[1] === 'active' ? '1' : '0';
                $searchTerm = trim(preg_replace('/:(active|inactive)/i', '', $searchTerm));
            }

            global $wpdb;
            $like = '%' . $wpdb->esc_like($searchTerm) . '%';
            $query->where(function ($q) use ($searchTerm, $like) {
                if (is_numeric($searchTerm)) {
                    $q->where('id', $searchTerm)
                      ->orWhereRaw('LOWER(type) LIKE %s', [$like])
                      ->orWhereRaw('LOWER(value) LIKE %s', [$like]);
                } else {
                    $q->whereRaw('LOWER(type) LIKE %s', [$like])
                      ->orWhereRaw('LOWER(value) LIKE %s', [$like]);
                }
            });
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if (!empty($params['type'])) {
            $query->where('type', 'LIKE', '%' . strtolower(trim($params['type'])) . '%');
        }

        if ($request->has_param('status') && $params['status'] !== '' && $params['status'] !== null) {
            $query->where('status', $params['status']);
        }

        $total = $query->count();

        if (!empty($params['sort'])) {
            $sort = kcRecursiveSanitizeTextField(json_decode(stripslashes($params['sort'][0]), true));
            if (!empty($sort['field']) && !empty($sort['type']) && $sort['type'] !== 'none') {
                $query->orderBy($sort['field'], strtoupper($sort['type']));
            } else {
                $query->orderBy('id', 'DESC');
            }
        } else {
            $query->orderBy('id', 'DESC');
        }

        $page = (int)($params['page'] ?? 1);
        $perPage = (int)($params['perPage'] ?? 10);
        $offset = ($page - 1) * $perPage;

        $query->limit($perPage)->offset($offset);

        $data = $query->get()->map(function($item) {
            $raw_type = is_array($item->type) ? ($item->type['id'] ?? $item->type['type']) : $item->type;
            return (object)[
                'id' => $item->id,
                'type' => kcGetStaticDataTypeLabel($raw_type),
                'raw_type' => $raw_type,
                'label' => $item->label,
                'value' => $item->value,
                'parent_id' => $item->parentId,
                'status' => $item->status,
                'created_at' => $item->createdAt,
            ];
        })->toArray();

        if (empty($data)) {
            return $this->response([], esc_html__('No services found', 'kivicare-clinic-management-system'), false);
        }

        return $this->response([
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'perPage' => $perPage,
                'currentPage' => $page,
                'lastPage' => max(1, ceil($total / max(1, $perPage)))
            ]
        ], esc_html__('Service list', 'kivicare-clinic-management-system'));
    }

    public function dataDelete(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');
            if (!$id) {
                return $this->response(null, esc_html__('ID is required.', 'kivicare-clinic-management-system'), false);
            }

            $deleted = KCStaticData::query()->where('id', $id)->delete();

            if ($deleted) {
                return $this->response(['tableReload' => true], esc_html__('Static data deleted successfully', 'kivicare-clinic-management-system'));
            }

            return $this->response(null, esc_html__('Data not found', 'kivicare-clinic-management-system'), false);
        } catch (\Exception $e) {
            return $this->response(['error' => $e->getMessage()], $e->getMessage(), false, 500);
        }
    }

    public function dataEdit(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id = $request->get_param('id');
            if (!$id) {
                return $this->response(null, esc_html__('ID is required.', 'kivicare-clinic-management-system'), false);
            }

            $item = KCStaticData::get_by(['id' => (int)$id], '=', true);
            if (!$item) {
                return $this->response(null, esc_html__('Data not found', 'kivicare-clinic-management-system'), false);
            }

            $item->status = [
                'id' => (int)$item->status,
                'label' => $item->status ? 'Active' : 'Inactive'
            ];

            $item->type = [
                'id' => $item->type,
                'type' => kcGetStaticDataTypeLabel($item->type),
            ];

            return $this->response($item, esc_html__('Static data', 'kivicare-clinic-management-system'));
        } catch (\Exception $e) {
            return $this->response(['error' => $e->getMessage()], $e->getMessage(), false, 500);
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
                'description'       => 'Export format (csv, xls, pdf)',
                'type'              => 'string',
                'required'          => true,
                'validate_callback' => function ($param) {
                    if (!in_array($param, ['csv', 'xls', 'pdf'])) {
                        return new WP_Error('invalid_format', __('Format must be csv, xls, or pdf', 'kivicare-clinic-management-system'));
                    }
                    return true;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'searchTerm' => [
                'description'       => 'Search term to filter results',
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'type' => [
                'description'       => 'Filter by type',
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'description'       => 'Filter by status (0: Inactive, 1: Active)',
                'type'              => 'integer',
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
     * Export listings data
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function exportListings(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_params();
            $query = KCStaticData::query();

            $status = null;
            $searchTerm = strtolower(trim($params['searchTerm'] ?? ''));

            // Search logic (reused from getListingData)
            if ($searchTerm !== '') {
                if (preg_match('/:(active|inactive)/i', $searchTerm, $matches)) {
                    $status = $matches[1] === 'active' ? '1' : '0';
                    $searchTerm = trim(preg_replace('/:(active|inactive)/i', '', $searchTerm));
                }

                global $wpdb;
                $like = '%' . $wpdb->esc_like($searchTerm) . '%';
                $query->where(function ($q) use ($searchTerm, $like) {
                    if (is_numeric($searchTerm)) {
                        $q->where('id', $searchTerm)
                          ->orWhereRaw('LOWER(type) LIKE %s', [$like])
                          ->orWhereRaw('LOWER(value) LIKE %s', [$like]);
                    } else {
                        $q->whereRaw('LOWER(type) LIKE %s', [$like])
                          ->orWhereRaw('LOWER(value) LIKE %s', [$like]);
                    }
                });
            }

            // Status filter
            if ($status !== null) {
                $query->where('status', $status);
            }

            // Type filter
            if (!empty($params['type'])) {
                $query->where('type', 'LIKE', '%' . strtolower(trim($params['type'])) . '%');
            }

            // Status filter (from params)
            if (isset($params['status']) && is_numeric($params['status'])) {
                $query->where('status', (int)$params['status']);
            }

            // Order by ID descending
            $query->orderBy('id', 'DESC');

            // Get all results (no pagination for export)
            $results = $query->get();

            if ($results->isEmpty()) {
                return $this->response(
                    ['listings' => []],
                    __('No listings found to export', 'kivicare-clinic-management-system'),
                    true
                );
            }

            // Format data for export
            $exportData = $results->map(function($item) {
                return [
                    'id'     => $item->id,
                    'type'   => kcGetStaticDataTypeLabel($item->type),
                    'label'  => $item->label,
                    'value'  => $item->value,
                    'status' => $item->status == 1 ? 'Active' : 'Inactive',
                ];
            })->toArray();

            return $this->response(
                ['listings' => $exportData],
                __('Listings data retrieved successfully for export', 'kivicare-clinic-management-system'),
                true
            );

        } catch (\Exception $e) {
            return $this->response(
                ['error' => $e->getMessage()],
                __('Failed to export listings data', 'kivicare-clinic-management-system'),
                false,
                500
            );
        }
    }

    public function dataUpdate(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_json_params();

            if (isset($params['label'])) {
                $params['label'] = html_entity_decode($params['label'], ENT_QUOTES, 'UTF-8');
            }

            $value = str_replace(' ', '_', strtolower($params['label']));
            $type = $params['type']['value'] ?? $params['type'];
            $status = (int)($params['status']['id'] ?? $params['status']);

            $query = KCStaticData::query()
                ->where('type', $type)
                ->where('label', $params['label']);

            if (!empty($params['id'])) {
                $query->where('id', '!=', (int)$params['id']);
            }
            
            $exists = $query->first();

            if ($exists) {
                return $this->response(null, esc_html__('Listing data already exists.', 'kivicare-clinic-management-system'), false);
            }

            $data = [
                'label' => $params['label'],
                'type' => $type,
                'value' => $value,
                'status' => $status
            ];

            if (empty($params['id'])) {
                $data['created_at'] = current_time('Y-m-d H:i:s');
                $insert_id = KCStaticData::create($data);
                $message = esc_html__('Listing data saved successfully', 'kivicare-clinic-management-system');
            } else {
                $record = KCStaticData::find((int)$params['id']);
                if (!$record) {
                    return $this->response(null, esc_html__('Listing data not found.', 'kivicare-clinic-management-system'), false);
                }

                foreach ($data as $key => $value) {
                    $record->$key = $value;
                }

                $record->save();
                $insert_id = $record->id;
                $message = esc_html__('Listing data updated successfully', 'kivicare-clinic-management-system');
            }

            return $this->response(['insert_id' => $insert_id], $message);
        } catch (\Exception $e) {
            return $this->response(['error' => $e->getMessage()], __('Failed to update settings', 'kivicare-clinic-management-system'), false, 500);
        }
    }
}
