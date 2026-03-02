<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowDefinition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'status',
        'created_by',
        'updated_by',
        'branch_id',
        'current_version',
        'max_concurrency',
    ];

    protected $attributes = [
        'status' => 'draft',
        'current_version' => 0,
        'max_concurrency' => 5,
    ];

    protected $casts = [
        'current_version' => 'integer',
        'max_concurrency' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(WorkflowPermission::class);
    }

    public function publishedVersion()
    {
        return $this->versions()->where('status', 'published')->latest('version_number')->first();
    }
}
