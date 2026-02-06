<?php

namespace App\models;

use App\baseClasses\KCBaseModel;

defined('ABSPATH') or die('Something went wrong');

class KCBillItem extends KCBaseModel
{
    /**
     * Initialize the schema with validation rules
     */
    protected static function initSchema(): array
    {
        return [
            'table_name' => 'kc_bill_items',
            'primary_key' => 'id',
            'columns' => [
                'id' => [
                    'column' => 'id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'auto_increment' => true,
                ],
                'billId' => [
                    'column' => 'bill_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        fn($value) => $value > 0 ? true : 'Invalid bill ID'
                    ],
                ],
                'itemId' => [
                    'column' => 'item_id',
                    'type' => 'bigint',
                    'nullable' => false,
                    'sanitizers' => ['intval'],
                    'validators' => [
                        fn($value) => $value > 0 ? true : 'Invalid item ID'
                    ],
                ],
                'qty' => [
                    'column' => 'qty',
                    'type' => 'int',
                    'nullable' => false,
                    'validators' => [
                        fn($value) => $value > 0 ? true : 'Quantity must be greater than 0'
                    ],
                ],
                'price' => [
                    'column' => 'price',
                    'type' => 'varchar',
                    'nullable' => true,
                    'sanitizers' => ['sanitize_text_field'],
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
     * Get the bill this item belongs to
     */
    public function getBill()
    {
        return KCBill::find($this->billId);
    }

    /**
     * Get the item associated with this bill item
     */
    public function getItem()
    {
        return KCService::find($this->itemId);
    }

    /**
     * Calculate the total amount for this item
     */
    public function getTotal(): float
    {
        return floatval($this->price) * $this->qty;
    }
}