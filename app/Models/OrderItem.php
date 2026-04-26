<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'source_branch_id',
        'source_inventory_id',
        'source_batch_number',
        'quantity_requested',
    ];

    public function order() {
        return $this->belongsTo(Order::class);
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function sourceBranch()
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function sourceInventory()
    {
        return $this->belongsTo(Inventory::class, 'source_inventory_id');
    }
}
