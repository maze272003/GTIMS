<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hold extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id', 'type', 'reason_code', 'remarks',
        'created_by', 'approved_by', 'status', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(HoldItem::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(HoldStatusHistory::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

}
