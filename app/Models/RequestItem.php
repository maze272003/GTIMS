<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'incoming_request_id', 'product_id', 'quantity_requested',
        'quantity_fulfilled', 'allow_substitution', 'substituted_product_id',
    ];

    protected $casts = [
        'allow_substitution' => 'boolean',
    ];

    public function incomingRequest()
    {
        return $this->belongsTo(IncomingRequest::class, 'incoming_request_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function substitutedProduct()
    {
        return $this->belongsTo(Product::class, 'substituted_product_id');
    }

    public function isFullyFulfilled(): bool
    {
        return $this->quantity_fulfilled >= $this->quantity_requested;
    }
}
