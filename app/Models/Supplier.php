<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = ['province_id', 'barangay_id', 'name', 'contact_person', 'email', 'phone', 'address', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function supplierProducts()
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function inventories()
    {
        return $this->belongsToMany(Inventory::class, 'supplier_products')
            ->withPivot('lead_time_days', 'unit_cost')
            ->withTimestamps();
    }

    /**
     * Convenience query for distinct products across linked inventory batches.
     */
    public function products(): Builder
    {
        return Product::query()
            ->select('products.*')
            ->join('inventories', 'inventories.product_id', '=', 'products.id')
            ->join('supplier_products', 'supplier_products.inventory_id', '=', 'inventories.id')
            ->where('supplier_products.supplier_id', $this->id)
            ->distinct();
    }
}
