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
        'expiry_date',
        'is_archived'
    ];

    protected $casts = [
        'expiry_date' => 'date', // ensures Laravel converts it to a Carbon instance
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
        $held = $this->holdItems()
            ->whereHas('hold', function ($q) {
                $q->whereIn('status', ['pending', 'approved']);
            })
            ->sum('quantity');
        return max(0, $this->quantity - $held);
    }
}
