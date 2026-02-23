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

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::STATUS_TRANSITIONS[$this->status] ?? [];
        return in_array($newStatus, $allowed);
    }
}
