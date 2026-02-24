<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
