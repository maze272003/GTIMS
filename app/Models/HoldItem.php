<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HoldItem extends Model
{
    use HasFactory;

    protected $fillable = ['hold_id', 'product_id', 'inventory_id', 'quantity'];

    public function hold()
    {
        return $this->belongsTo(Hold::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }
}
