<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomingRequest extends Model
{
    use HasFactory;

    protected $table = 'incoming_requests';

    protected $fillable = [
        'branch_id', 'requester_id', 'department', 'priority', 'status', 'remarks',
    ];

    public const STATUS_TRANSITIONS = [
        'draft' => ['requested'],
        'requested' => ['review'],
        'review' => ['approved', 'denied'],
        'approved' => ['fulfilling'],
        'fulfilling' => ['fulfilled'],
        'fulfilled' => ['closed'],
        'denied' => [],
        'closed' => [],
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function items()
    {
        return $this->hasMany(RequestItem::class, 'incoming_request_id');
    }

    public function comments()
    {
        return $this->hasMany(RequestComment::class, 'incoming_request_id');
    }

    public function attachments()
    {
        return $this->hasMany(RequestAttachment::class, 'incoming_request_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(RequestStatusHistory::class, 'incoming_request_id');
    }

    /**
     * Query scope for filtering by status.
     * PERFORMANCE: Used in dashboard and list views
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Query scope for filtering by priority.
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Query scope for filtering by branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Query scope for filtering by created date range.
     */
    public function scopeCreatedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Query scope for pending requests (awaiting action).
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'requested', 'review']);
    }

    /**
     * Query scope for fulfilled requests.
     */
    public function scopeFulfilled($query)
    {
        return $query->whereIn('status', ['fulfilled', 'closed']);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::STATUS_TRANSITIONS[$this->status] ?? [];
        return in_array($newStatus, $allowed);
    }
}
