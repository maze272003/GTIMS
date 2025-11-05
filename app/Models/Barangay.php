<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Barangay extends Model
{
    use HasFactory;

    protected $fillable = [
        'barangay_name',
    ];

    public function patientrecords()
    {
        return $this->hasMany(Patientrecords::class);
    }
}