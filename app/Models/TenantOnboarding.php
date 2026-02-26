<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantOnboarding extends Model
{
    use HasFactory;

    protected $table = 'tenant_onboarding';

    protected $fillable = [
        'province_id',
        'status',
        'current_step',
        'completed_steps',
        'notes',
        'created_by',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_steps' => 'array',
            'activated_at' => 'datetime',
        ];
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
