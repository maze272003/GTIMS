<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReorderRule extends Model
{
    protected $fillable = [
        'product_id', 'branch_id', 'preferred_supplier_id',
        'reorder_point', 'reorder_quantity',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function preferredSupplier()
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }
}
