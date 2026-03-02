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
        'idempotency_key',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'trigger_payload' => 'array',
        'context' => 'array',
        'is_dry_run' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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
}
