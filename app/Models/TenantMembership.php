<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'scope_type',
        'scope_id',
        'is_primary',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForScope($query, string $scopeType, ?int $scopeId = null)
    {
        return $query->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId);
    }

    public function isPlatformScope(): bool
    {
        return $this->scope_type === 'platform';
    }

    public function isProvinceScope(): bool
    {
        return $this->scope_type === 'province';
    }

    public function isBarangayScope(): bool
    {
        return $this->scope_type === 'barangay';
    }
}
