<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowNode extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_version_id',
        'node_id',
        'type',
        'action_type',
        'label',
        'config',
        'position',
    ];

    protected $casts = [
        'config' => 'array',
        'position' => 'array',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }
}
