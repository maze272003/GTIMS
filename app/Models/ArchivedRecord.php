<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArchivedRecord extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'province_id',
        'barangay_id',
        'source_table',
        'record_id',
        'archived_data',
        'archived_at',
        'archived_by',
        'retention_until',
    ];

    protected function casts(): array
    {
        return [
            'archived_data' => 'array',
            'archived_at' => 'datetime',
            'retention_until' => 'date',
        ];
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
}
