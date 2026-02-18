<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestStatusHistory extends Model
{
    protected $table = 'request_status_history';

    protected $fillable = ['incoming_request_id', 'old_status', 'new_status', 'changed_by', 'reason'];

    public function incomingRequest()
    {
        return $this->belongsTo(IncomingRequest::class, 'incoming_request_id');
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
