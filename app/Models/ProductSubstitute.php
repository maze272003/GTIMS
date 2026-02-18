<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSubstitute extends Model
{
    protected $fillable = ['product_id', 'substitute_product_id', 'priority'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function substituteProduct()
    {
        return $this->belongsTo(Product::class, 'substitute_product_id');
    }
}
