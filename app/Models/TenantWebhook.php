<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantWebhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id',
        'barangay_id',
        'event_type',
        'endpoint_url',
        'secret',
        'is_active',
        'last_triggered_at',
        'failure_count',
    ];

    protected $hidden = [
        'secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }
}
