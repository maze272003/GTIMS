<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SupplierProduct extends Model
{
    use TenantScoped;

    protected $fillable = ['province_id', 'barangay_id', 'supplier_id', 'inventory_id', 'lead_time_days', 'unit_cost'];

    protected $casts = [
        'unit_cost' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }
}
