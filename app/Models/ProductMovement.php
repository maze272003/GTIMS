<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMovement extends Model
{
    use HasFactory;

    protected $fillable = [
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
}