<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patientrecords extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_name',
        'barangay',
        'purok',
        'category',
        'date_dispensed',
    ];

    protected $casts = [
        'date_dispensed' => 'date',
    ];

    public function dispensedMedications()
    {
        return $this->hasMany(Dispensedmedication::class, 'patientrecord_id');
    }
}