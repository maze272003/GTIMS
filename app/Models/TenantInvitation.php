<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id',
        'barangay_id',
        'email',
        'role_id',
        'token',
        'invited_by',
        'status',
        'expires_at',
        'accepted_at',
        'accepted_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function role()
    {
        return $this->belongsTo(TenantRole::class, 'role_id');
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeValid($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '>', now());
    }
}
