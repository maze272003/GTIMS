<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_definition_id',
        'user_id',
        'permission',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
