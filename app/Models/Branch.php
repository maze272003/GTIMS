<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'is_main',
        'is_archived',
        'archived_at',
        'archived_by',
        'archive_checksum',
        'archive_metadata',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
        'archive_metadata' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function archivedByUser()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function archivalRunsAsSource()
    {
        return $this->hasMany(BranchArchivalRun::class, 'source_branch_id');
    }

    public function archivalRunsAsTarget()
    {
        return $this->hasMany(BranchArchivalRun::class, 'target_branch_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('is_archived', true);
    }

    public function scopeMain(Builder $query): Builder
    {
        return $query->where('is_main', true);
    }
}
