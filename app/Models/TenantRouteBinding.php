<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantRouteBinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id',
        'barangay_id',
        'host',
        'path_prefix',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
}

