<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantSuspension extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id',
        'barangay_id',
        'suspension_type',
        'reason',
        'suspended_by',
        'suspended_at',
        'reactivated_by',
        'reactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'reactivated_at' => 'datetime',
        ];
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function suspendedBy()
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function reactivatedBy()
    {
        return $this->belongsTo(User::class, 'reactivated_by');
    }
}
