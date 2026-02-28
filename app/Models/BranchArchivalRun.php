<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchArchivalRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_branch_id',
        'target_branch_id',
        'initiated_by',
        'status',
        'progress_percent',
        'steps',
        'before_metrics',
        'after_metrics',
        'before_checksum',
        'after_checksum',
        'error_message',
        'metadata',
        'started_at',
        'completed_at',
        'failed_at',
        'rolled_back_at',
    ];

    protected $casts = [
        'steps' => 'array',
        'before_metrics' => 'array',
        'after_metrics' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    public function sourceBranch()
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function targetBranch()
    {
        return $this->belongsTo(Branch::class, 'target_branch_id');
    }

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}

