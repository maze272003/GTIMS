<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_definition_id',
        'workflow_version_id',
        'status',
        'trigger_type',
        'trigger_payload',
        'context',
        'triggered_by',
        'is_dry_run',
        'retry_attempt',
        'max_retries',
        'next_retry_at',
        'is_dead_letter',
        'parent_run_id',
        'idempotency_key',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'trigger_payload' => 'array',
        'context' => 'array',
        'is_dry_run' => 'boolean',
        'is_dead_letter' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowRunStep::class);
    }

    public function parentRun(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_run_id');
    }

    public function childRuns(): HasMany
    {
        return $this->hasMany(self::class, 'parent_run_id');
    }

    public function scopeDeadLetter($query)
    {
        return $query->where('is_dead_letter', true);
    }

    public function scopeRetryable($query)
    {
        return $query->where('status', 'failed')
            ->where('is_dead_letter', false)
            ->whereColumn('retry_attempt', '<', 'max_retries')
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                  ->orWhere('next_retry_at', '<=', now());
            });
    }
}
