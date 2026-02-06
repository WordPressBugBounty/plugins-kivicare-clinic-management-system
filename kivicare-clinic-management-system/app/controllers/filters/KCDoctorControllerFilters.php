<?php

namespace App\controllers\filters;

use App\models\KCCustomFieldData;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

class KCDoctorControllerFilters
{
    private static ?KCDoctorControllerFilters $instance = null;

    public function __construct()
    {
        add_action('kc_doctor_save', [$this, 'handleDoctorSave'], 10, 2);
        add_action('kc_doctor_update', [$this, 'handleDoctorUpdate'], 10, 2);
        add_filter('kc_doctor_data', [$this, 'addCustomFieldDataToResponse'], 10, 2);
    }

    public static function get_instance(): ?KCDoctorControllerFilters
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function handleDoctorSave(array $doctorData, WP_REST_Request $request): void
    {
        $doctorId = (int) ($doctorData['id'] ?? 0);
        if ($doctorId <= 0) {
            return;
        }

        if ($request->has_param('customFields')) {
            $this->saveCustomFieldData(
                'doctor_module',
                (array) $request->get_param('customFields'),
                $doctorId
            );
        }
    }

    public function handleDoctorUpdate($doctorId, $request): void
    {
        $doctorId = (int) $doctorId;
        if ($doctorId <= 0 || !$request instanceof WP_REST_Request) {
            return;
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = (array) $params;
        }

        if (!empty($params['customFields']) && is_array($params['customFields'])) {
            $this->handleModuleUpdate($doctorId, $params);
        }
    }

    public function saveCustomFieldData(string $moduleType, array $customFields, int $doctorId): void
    {
        if (empty($customFields) || empty($doctorId)) {
            return;
        }

        foreach ($customFields as $fieldId => $value) {
            $value = is_array($value) || is_object($value)
                ? wp_json_encode($value)
                : sanitize_text_field((string) $value);

            $existing = KCCustomFieldData::query()
                ->where('module_type', $moduleType)
                ->where('module_id', $doctorId)
                ->where('field_id', (int) $fieldId)
                ->first();

            if ($existing) {
                $existing->fieldsData = $value;
                $existing->save();
            } else {
                $data = new KCCustomFieldData();
                $data->moduleType = $moduleType;
                $data->moduleId = $doctorId;
                $data->fieldId = (int) $fieldId;
                $data->fieldsData = $value;
                $data->createdAt = current_time('mysql');
                $data->save();
            }
        }
    }

    public function addCustomFieldDataToResponse(array $responseData, int $doctorId): array
    {
        if (empty($doctorId)) {
            return $responseData;
        }

        $records = KCCustomFieldData::query()
            ->where('module_type', 'doctor_module')
            ->where('module_id', $doctorId)
            ->get();

        if ($records->isEmpty()) {
            $responseData['customFields'] = [];
            return $responseData;
        }

        $customFields = [];
        foreach ($records as $record) {
            $value = $record->fieldsData;
            $decoded = json_decode($value, true);
            $customFields[$record->fieldId] = json_last_error() === JSON_ERROR_NONE
                ? $decoded
                : $value;
        }

        $responseData['customFields'] = $customFields;
        return $responseData;
    }

    public function handleModuleUpdate(int $doctorId, array $params): void
    {
        if (empty($doctorId) || empty($params['customFields']) || !is_array($params['customFields'])) {
            return;
        }

        $moduleType = 'doctor_module';
        $customFieldPayload = $params['customFields'];

        $existingRecords = KCCustomFieldData::query()
            ->where('module_type', $moduleType)
            ->where('module_id', $doctorId)
            ->get()
            ->keyBy('fieldId');

        foreach ($customFieldPayload as $fieldId => $value) {
            $fieldId = (int) $fieldId;
            if ($fieldId <= 0) {
                continue;
            }

            $encodedValue = is_array($value) || is_object($value)
                ? wp_json_encode($value)
                : sanitize_text_field((string) $value);

            if (isset($existingRecords[$fieldId])) {
                $record = $existingRecords[$fieldId];
                $record->fieldsData = $encodedValue;
                $record->save();
            } else {
                $record = new KCCustomFieldData();
                $record->moduleType = $moduleType;
                $record->moduleId = $doctorId;
                $record->fieldId = $fieldId;
                $record->fieldsData = $encodedValue;
                $record->createdAt = current_time('mysql');
                $record->save();
            }
        }

        $submittedFieldIds = array_map('intval', array_keys($customFieldPayload));
        KCCustomFieldData::query()
            ->where('module_type', $moduleType)
            ->where('module_id', $doctorId)
            ->whereNotIn('fieldId', $submittedFieldIds)
            ->delete();
    }
}
