<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Barangay extends Model
{
    use HasFactory;

    protected $fillable = [
        'barangay_name',
        'province_id',
        'slug',
        'is_active',
        'external_code',
        'settings_json',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings_json' => 'array',
        ];
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function patientrecords()
    {
        return $this->hasMany(Patientrecords::class);
    }

    public function memberships()
    {
        return $this->morphMany(TenantMembership::class, 'scope', 'scope_type', 'scope_id')
            ->where('scope_type', 'barangay');
    }

    protected static function booted(): void
    {
        static::saving(function (Barangay $barangay): void {
            $barangay->slug = Str::slug((string) $barangay->slug);
        });
    }
}
