<?php

namespace App\models;

use App\baseClasses\KCBaseModel;
use App\baseClasses\KCBase;

defined('ABSPATH') or die('Something went wrong');

class KCBill extends KCBaseModel
{
    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_bills',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'encounterId' => [
                    'column' => 'encounter_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        fn($value) => $value > 0 ? true : 'Invalid encounter ID'
                    ],
                ],
                'appointmentId' => [
                    'column' => 'appointment_id',
                    'type' => 'bigint',
                    'nullable' => true,
                    'sanitizers' => ['intval'],
                ],
                'title' => [
                    'column' => 'title',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'totalAmount' => [
                    'column' => 'total_amount',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'discount' => [
                    'column' => 'discount',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'actualAmount' => [
                    'column' => 'actual_amount',
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
                'paymentStatus' => [
                    'column' => 'payment_status',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
                ],
                'createdAt' => [
                    'column' => 'created_at',
                    'type' => 'datetime',
                    'nullable' => false,
                ],
                'clinicId' => [
                    'column' => 'clinic_id',
                    'type' => 'bigint',
                    'nullable' => true,
                    'sanitizers' => ['intval'],
                ],
            ],
            'timestamps' => false, // We'll handle created_at manually
            'soft_deletes' => false,
        ];
    }

    /**
     * Get the appointment associated with this bill
     */
    public function getAppointment()
    {
        return KCAppointment::find($this->appointmentId);
    }

    /**
     * Get the encounter associated with this bill
     */
    public function getEncounter()
    {
        return KCPatientEncounter::find($this->encounterId);
    }

    /**
     * Get the clinic associated with this bill
     */
    public function getClinic()
    {
        return KCClinic::find($this->clinicId);
    }

    /**
     * Get all bill items for this bill
     */
    public function getBillItems()
    {
        return KCBillItem::query()->where('billId', $this->id)->get();
    }

    /**
     * Get total revenue based on user role and date range
     */
    public static function getTotalRevenue($user_role, $user_id, $date_range = [])
    {
        $kcbase = KCBase::get_instance();
        $total = 0;
        $hasDateRange = isset($date_range['date_range']['start_date']) && isset($date_range['date_range']['end_date']);
        $startDate = $hasDateRange ? $date_range['date_range']['start_date'] : null;
        $endDate = $hasDateRange ? $date_range['date_range']['end_date'] : null;
        // If start and end date are the same and no time is specified, expand to full day
        if ($hasDateRange && $startDate === $endDate && strlen($startDate) === 10 && strlen($endDate) === 10) {
            $startDate .= ' 00:00:00';
            $endDate .= ' 23:59:59';
        }

        if ( $user_role === $kcbase->getReceptionistRole() ){
            $clinic_id = KCReceptionistClinicMapping::getClinicIdByReceptionistId($user_id);
            $query = KCBill::table('kc_bills')
                ->select(['SUM(total_amount) as total_revenue'])
                ->where('paymentStatus', 'paid')
                ->where('clinic_id', $clinic_id);
            if ($hasDateRange) {
                $query = $query->whereBetween('created_at', [$startDate, $endDate]);
            }
            $total = $query->first();
        }elseif($user_role === $kcbase->getClinicAdminRole()){
            $clinic_id = KCClinic::getClinicIdOfClinicAdmin($user_id);
            $query = KCBill::table('kc_bills')
                ->select(['SUM(total_amount) as total_revenue'])
                ->where('paymentStatus', 'paid')
                ->where('clinic_id', $clinic_id);
            if ($hasDateRange) {
                $query = $query->whereBetween('created_at', [$startDate, $endDate]);
            }
            $total = $query->first();
        }else{
            $query = KCBill::table('kc_bills')
                ->select(['SUM(actual_amount) as total_revenue'])
                ->where('paymentStatus', 'paid');
            if ($hasDateRange) {
                $query = $query->whereBetween('created_at', [$startDate, $endDate]);
            }
            $total = $query->first();
        }
        $total = apply_filters('kivicare_total_revenue', $total, $user_role, $user_id, $date_range);
        $currency_format = KCClinic::getClinicCurrencyPrefixAndPostfix();
        $prefix = $currency_format['prefix'] ?? '';
        $postfix = $currency_format['postfix'] ?? '';

        // Format the total with number_format for proper thousand separators
        $formatted_total = $prefix . number_format($total->total_revenue) . $postfix;
        return [
            'count' => $total->total_revenue ?? 0,
            'formatted_count' => $formatted_total
        ];
    }
}