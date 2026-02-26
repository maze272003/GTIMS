<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantUsage extends Model
{
    use HasFactory;

    protected $table = 'tenant_usage';

    public $timestamps = false;

    protected $fillable = [
        'province_id',
        'barangay_id',
        'metric_key',
        'metric_value',
        'period_start',
        'period_end',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'metric_value' => 'integer',
        ];
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }
}
