<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dispensedmedication extends Model
{
    use HasFactory;

    protected $fillable = [
        'patientrecord_id',
        'batch_number',
        'generic_name',
        'brand_name',
        'strength',
        'form',
        'quantity',
    ];

    public function patientrecord()
    {
        return $this->belongsTo(Patientrecords::class, 'patientrecord_id');
    }
}