<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataSubjectRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id',
        'barangay_id',
        'request_type',
        'status',
        'requested_by_email',
        'verified_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }
}
