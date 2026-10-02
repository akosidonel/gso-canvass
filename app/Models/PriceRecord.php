<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceRecord extends Model
{
    protected $table = 'price_monitoring_records';

    public const FIELDS = [
        'category' => 'Category',
        'qty' => 'Qty',
        'unit' => 'Unit',
        'particulars' => 'Particulars',
        'amount' => 'Amount',
        'department' => 'Department',
        'control_number' => 'Control number',
        'brand_model' => 'Brand/Model',
        'store' => 'Store',
        'canvasser' => 'Canvasser',
    ];

    public const CATEGORIES = [
        'Motor Vehicle', 'I.T Equipment', 'Printer', 'Copier', 'Desktop Computer',
        'Laptop', 'Office Equipment', 'Office Supplies', 'Bond Paper', 'Ink & Cartridge',
        'Janitorial', 'Heavy Equipment', 'Repair & Maintenance', 'Computer Software',
        'Furniture & Fixtures',
    ];

    // Keep stored duplicate fingerprints independent of the display column order.
    public const FINGERPRINT_FIELDS = ['qty', 'unit', 'brand_model', 'particulars', 'amount', 'department', 'control_number', 'store', 'canvasser'];

    protected $fillable = ['qty', 'unit', 'brand_model', 'particulars', 'amount', 'department', 'control_number', 'store', 'canvasser', 'category', 'fingerprint'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'amount' => 'decimal:2', 'archived_at' => 'datetime'];
    }
}
