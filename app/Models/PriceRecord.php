<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceRecord extends Model
{
    protected $table = 'price_monitoring_records';

    public const FIELDS = [
        'qty' => 'Qty',
        'unit' => 'Unit',
        'brand_model' => 'Brand/Model',
        'particulars' => 'Particulars',
        'amount' => 'Amount',
        'total' => 'Total',
        'department' => 'Department',
        'control_number' => 'Control number',
        'store' => 'Store',
        'canvasser' => 'Canvasser',
    ];

    protected $fillable = ['qty', 'unit', 'brand_model', 'particulars', 'amount', 'total', 'department', 'control_number', 'store', 'canvasser', 'fingerprint'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'amount' => 'decimal:2', 'total' => 'decimal:2'];
    }
}
