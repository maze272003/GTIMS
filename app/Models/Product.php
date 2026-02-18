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

    // Total stock from RHU 1 + RHU 2 only
    public function getTotalRhuStockAttribute()
    {
        return $this->inventories()
            ->whereHas('branch', fn($q) => $q->whereIn('name', ['RHU 1', 'RHU 2']))
            ->sum('quantity');
    }

    // Load inventories from RHU 1 & RHU 2 only
    public function scopeWithRhuInventory($query)
    {
        return $query->with(['inventories.branch' => fn($q) => 
            $q->whereHas('branch', fn($b) => $b->whereIn('name', ['RHU 1', 'RHU 2']))
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
        return $this->belongsToMany(Supplier::class, 'supplier_products')
            ->withPivot('lead_time_days', 'unit_cost')
            ->withTimestamps();
    }
}