<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Inventory extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'product_id',
        'batch_number',
        'quantity',
        'expiry_date',
        'is_archived'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
