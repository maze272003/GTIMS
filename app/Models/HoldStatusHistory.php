<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoldStatusHistory extends Model
{
    protected $table = 'hold_status_history';

    protected $fillable = ['hold_id', 'old_status', 'new_status', 'changed_by', 'reason'];

    public function hold()
    {
        return $this->belongsTo(Hold::class);
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
