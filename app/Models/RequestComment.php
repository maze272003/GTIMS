<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class RequestComment extends Model
{
    use TenantScoped;

    protected $fillable = ['province_id', 'barangay_id', 'incoming_request_id', 'user_id', 'comment'];

    public function incomingRequest()
    {
        return $this->belongsTo(IncomingRequest::class, 'incoming_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
