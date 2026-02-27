<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMovement extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'province_id',
        'barangay_id',
        'product_id',
        'inventory_id',
        'user_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'description',
    ];

    /**
     * Get the product associated with the movement.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the specific inventory batch associated with the movement.
     */
    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    /**
     * Get the user who caused the movement.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Get branch through inventory relationship
    public function branch()
    {
        return $this->hasOneThrough(
            Branch::class,
            Inventory::class,
            'id',           // Foreign key on inventories table (inventories.id)
            'id',           // Foreign key on branches table (branches.id)
            'inventory_id', // Local key on product_movements table
            'branch_id'     // Local key on inventories table
        );
    }

    // Helper to get branch name easily
    public function getBranchNameAttribute()
    {
        return $this->inventory?->branch?->name ?? 'Unknown';
    }
}
