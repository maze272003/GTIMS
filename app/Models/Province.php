<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Province extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'code',
        'is_active',
        'settings_json',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings_json' => 'array',
        ];
    }

    public function barangays()
    {
        return $this->hasMany(Barangay::class);
    }

    public function memberships()
    {
        return $this->morphMany(TenantMembership::class, 'scope', 'scope_type', 'scope_id')
            ->where('scope_type', 'province');
    }

    protected static function booted(): void
    {
        static::saving(function (Province $province): void {
            $province->slug = Str::slug((string) $province->slug);

            $reserved = [
                strtolower((string) config('tenancy.moderator_prefix', 'moderator')),
                'admin',
                'api',
            ];

            if (in_array(strtolower((string) $province->slug), $reserved, true)) {
                throw new InvalidArgumentException('Province slug is reserved and cannot be used.');
            }
        });
    }
}
