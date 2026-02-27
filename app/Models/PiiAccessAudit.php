<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PiiAccessAudit extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'user_id',
        'province_id',
        'barangay_id',
        'resource_type',
        'resource_id',
        'action',
        'metadata',
        'accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'accessed_at' => 'datetime',
        ];
    }
}

