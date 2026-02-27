<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use TenantScoped;

    protected $guarded = [];

    public function items() {
        return $this->hasMany(OrderItem::class);
    }

    public function branch() {
        return $this->belongsTo(Branch::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
