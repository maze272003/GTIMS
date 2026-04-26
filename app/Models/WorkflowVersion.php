<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_definition_id',
        'version_number',
        'status',
        'graph_data',
        'change_summary',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'graph_data' => 'array',
        'version_number' => 'integer',
        'published_at' => 'datetime',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(WorkflowNode::class);
    }

    public function edges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }
}
