<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = ['key', 'user_id', 'action', 'response'];

    protected $casts = [
        'response' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
