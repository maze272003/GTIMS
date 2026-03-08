<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'branch_id',
        'user_id',
        'status',
        'admin_approved_at',
        'finance_approved_at',
        'remarks',
    ];

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
