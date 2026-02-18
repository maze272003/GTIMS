<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestAttachment extends Model
{
    protected $fillable = [
        'incoming_request_id', 'user_id', 'filename', 'original_name', 'mime_type', 'size',
    ];

    public function incomingRequest()
    {
        return $this->belongsTo(IncomingRequest::class, 'incoming_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
