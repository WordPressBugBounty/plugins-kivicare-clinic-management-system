<?php

namespace App\controllers\filters;

use App\models\KCCustomFieldData;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

class KCPatientControllerFilters
{
    private static ?KCPatientControllerFilters $instance = null;

    public function __construct()
    {
        add_action('kc_patient_save', [$this, 'handlePatientSave'], 10, 2);
        add_action('kc_patient_update', [$this, 'handlePatientUpdate'], 10, 4);
        add_filter('kc_patient_data', [$this, 'addCustomFieldDataToResponse'], 10, 2);
    }

    public static function get_instance(): ?KCPatientControllerFilters
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function handlePatientSave(array $patientData, WP_REST_Request $request): void
    {
        $patientId = (int) ($patientData['id'] ?? 0);
        if ($patientId <= 0) {
            return;
        }

        if ($request->has_param('customFields')) {
            $this->saveCustomFieldData(
                'patient_module',
                (array) $request->get_param('customFields'),
                $patientId
            );
        }
    }

    public function handlePatientUpdate($patientData, $request, $responseData = null, $unused = null): void
    {
        $patientId = is_array($patientData)
            ? (int) ($patientData['id'] ?? 0)
            : (int) $patientData;

        if ($patientId <= 0 || !$request instanceof WP_REST_Request) {
            return;
        }

        $params = (array) $request->get_params();
        if (!empty($params['customFields']) && is_array($params['customFields'])) {
            $this->handleModuleUpdate($patientId, $params);
        }
    }

    public function saveCustomFieldData(string $moduleType, array $customFields, int $patientId): void
    {
        if (empty($customFields) || empty($patientId)) {
            return;
        }

        foreach ($customFields as $fieldId => $value) {
            $fieldId = (int) $fieldId;
            if ($fieldId <= 0) {
                continue;
            }

            $value = is_array($value) || is_object($value)
                ? wp_json_encode($value)
                : sanitize_text_field((string) $value);

            $existing = KCCustomFieldData::query()
                ->where('module_type', $moduleType)
                ->where('module_id', $patientId)
                ->where('field_id', $fieldId)
                ->first();

            if ($existing) {
                $existing->fieldsData = $value;
                $existing->save();
            } else {
                $data = new KCCustomFieldData();
                $data->moduleType = $moduleType;
                $data->moduleId = $patientId;
                $data->fieldId = $fieldId;
                $data->fieldsData = $value;
                $data->createdAt = current_time('mysql');
                $data->save();
            }
        }
    }

    public function addCustomFieldDataToResponse(array $responseData, int $patientId): array
    {
        if (empty($patientId)) {
            return $responseData;
        }

        $records = KCCustomFieldData::query()
            ->where('module_type', 'patient_module')
            ->where('module_id', $patientId)
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

    public function handleModuleUpdate(int $patientId, array $params): void
    {
        if ($patientId <= 0 || empty($params['customFields']) || !is_array($params['customFields'])) {
            return;
        }

        $moduleType = 'patient_module';
        $customFieldPayload = $params['customFields'];

        $existingRecords = KCCustomFieldData::query()
            ->where('module_type', $moduleType)
            ->where('module_id', $patientId)
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
                $record->moduleId = $patientId;
                $record->fieldId = $fieldId;
                $record->fieldsData = $encodedValue;
                $record->createdAt = current_time('mysql');
                $record->save();
            }
        }

        $submittedFieldIds = array_map('intval', array_keys($customFieldPayload));
        KCCustomFieldData::query()
            ->where('module_type', $moduleType)
            ->where('module_id', $patientId)
            ->whereNotIn('field_id', $submittedFieldIds)
            ->delete();
    }
}
