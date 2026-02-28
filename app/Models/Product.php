<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_name',
        'generic_name',
        'form',
        'strength',
        'is_archived'
    ];

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function movements()
    {
        return $this->hasMany(ProductMovement::class);
    }

    // Total stock across all active branches
    public function getTotalRhuStockAttribute()
    {
        return $this->inventories()
            ->whereHas('branch', fn($q) => $q->active())
            ->sum('quantity');
    }

    // Load inventories from active branches only
    public function scopeWithRhuInventory($query)
    {
        return $query->with([
            'inventories' => fn($inventoryQuery) => $inventoryQuery
                ->whereHas('branch', fn($branchQuery) => $branchQuery->active())
                ->with('branch'),
        ]);
    }

    public function substitutes()
    {
        return $this->belongsToMany(Product::class, 'product_substitutes', 'product_id', 'substitute_product_id')
            ->withPivot('priority')
            ->orderByPivot('priority');
    }

    public function suppliers()
    {
        return Supplier::query()
            ->select('suppliers.*')
            ->join('supplier_products', 'supplier_products.supplier_id', '=', 'suppliers.id')
            ->join('inventories', 'inventories.id', '=', 'supplier_products.inventory_id')
            ->where('inventories.product_id', $this->id)
            ->distinct();
    }

    public function supplierProducts()
    {
        return $this->hasManyThrough(
            SupplierProduct::class,
            Inventory::class,
            'product_id',
            'inventory_id',
            'id',
            'id'
        );
    }
}
