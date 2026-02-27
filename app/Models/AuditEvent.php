<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    use TenantScoped;

    protected $fillable = [
        'province_id', 'barangay_id',
        'action', 'entity_type', 'entity_id', 'user_id',
        'before', 'after', 'reason', 'metadata',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function boot()
    {
        parent::boot();

        static::updating(function () {
            throw new \RuntimeException('Audit events are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Audit events are immutable and cannot be deleted.');
        });
    }
}
