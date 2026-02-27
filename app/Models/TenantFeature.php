<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id',
        'barangay_id',
        'feature_key',
        'enabled',
        'settings_json',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'settings_json' => 'array',
        ];
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }
}
