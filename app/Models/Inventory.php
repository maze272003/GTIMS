<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'branch_id',
        'batch_number',
        'quantity',
        'onhand_qty',
        'hold_qty',
        'expiry_date',
        'is_archived'
    ];

    protected $casts = [
        'expiry_date' => 'date', // ensures Laravel converts it to a Carbon instance
        'onhand_qty' => 'integer',
        'hold_qty' => 'integer',
    ];


    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function movements()
    {
        return $this->hasMany(ProductMovement::class);
    }

    public function getBranchNameAttribute()
    {
        return $this->branch?->name ?? 'Unknown Branch';
    }

    public function holdItems()
    {
        return $this->hasMany(HoldItem::class);
    }

    public function supplierProducts()
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'supplier_products')
            ->withPivot('lead_time_days', 'unit_cost')
            ->withTimestamps();
    }

    public function getAvailableQuantityAttribute(): int
    {
        $onHand = (int) ($this->attributes['onhand_qty'] ?? $this->attributes['quantity'] ?? 0);
        $held = (int) ($this->attributes['hold_qty'] ?? 0);

        if ($held === 0) {
            // Backward-compatible fallback for old rows/tests that still rely on active hold_items.
            $held = (int) $this->holdItems()
                ->whereHas('hold', function ($q) {
                    $q->whereIn('status', ['pending', 'approved']);
                })
                ->sum('quantity');
        }

        return max(0, $onHand - $held);
    }

    public function setQuantityAttribute($value): void
    {
        $normalized = max(0, (int) $value);
        $this->attributes['quantity'] = $normalized;
        $this->attributes['onhand_qty'] = $normalized;
    }

    public function setOnhandQtyAttribute($value): void
    {
        $normalized = max(0, (int) $value);
        $this->attributes['onhand_qty'] = $normalized;
        $this->attributes['quantity'] = $normalized;
    }

    public function setHoldQtyAttribute($value): void
    {
        $this->attributes['hold_qty'] = max(0, (int) $value);
    }
}
