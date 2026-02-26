<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantHealth extends Model
{
    use HasFactory;

    protected $table = 'tenant_health';

    public $timestamps = false;

    protected $fillable = [
        'province_id',
        'barangay_id',
        'check_type',
        'status',
        'details',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }
}
