<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_name',
        'generic_name',
        'form',
        'strength',
        'is_archived',
    ];

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function movements()
    {
        return $this->hasMany(ProductMovement::class);
    }

    /**
     * Query scope for active (non-archived) products.
     * PERFORMANCE: Use instead of repeated ->where('is_archived', false)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    /**
     * Query scope for archived products.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('is_archived', true);
    }

    /**
     * Query scope for active products that still have at least one active,
     * non-expired inventory batch.
     */
    public function scopeActiveAndNotExpired(Builder $query): Builder
    {
        return $query->active()->whereHas('inventories', function (Builder $inventoryQuery): void {
            $inventoryQuery
                ->active()
                ->where(function (Builder $expiryQuery): void {
                    $expiryQuery
                        ->whereNull('expiry_date')
                        ->orWhereDate('expiry_date', '>=', now()->toDateString());
                });
        });
    }

    /**
     * Query scope to filter by generic name, form, and strength.
     * PERFORMANCE: Common grouping for equivalent products
     */
    public function scopeByCharacteristics(Builder $query, string $genericName, string $form, string $strength): Builder
    {
        return $query->where('generic_name', $genericName)
            ->where('form', $form)
            ->where('strength', $strength);
    }

    // Total stock across all active branches
    public function getTotalRhuStockAttribute()
    {
        return $this->inventories()
            ->whereHas('branch', fn ($q) => $q->active())
            ->sum('quantity');
    }

    // Load inventories from active branches only
    public function scopeWithRhuInventory(Builder $query): Builder
    {
        return $query->with([
            'inventories' => fn ($inventoryQuery) => $inventoryQuery
                ->whereHas('branch', fn ($branchQuery) => $branchQuery->active())
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
