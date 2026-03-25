<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hold extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id', 'barangay_id', 'type', 'reason_code', 'remarks',
        'created_by', 'approved_by', 'status', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
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

    /**
     * Query scope for active holds (pending or approved).
     * PERFORMANCE: Used in expiry tracking and availability checks
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'approved']);
    }

    /**
     * Query scope for expired holds.
     * PERFORMANCE: Used when cleaning up expired holds
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Query scope for holds by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Query scope for holds by branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
