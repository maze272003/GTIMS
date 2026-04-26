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
        'received_at',
        'received_by',
        'remarks',
    ];

    protected $casts = [
        'admin_approved_at' => 'datetime',
        'finance_approved_at' => 'datetime',
        'received_at' => 'datetime',
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

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
