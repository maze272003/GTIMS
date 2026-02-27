<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class HistoryLog extends Model
{
    use TenantScoped;

    protected $table = 'history_logs';

    protected $fillable = [
        'province_id',
        'barangay_id',
        'action',
        'description',
        'user_id',
        'user_name',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
