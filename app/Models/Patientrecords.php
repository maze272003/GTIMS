<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Patientrecords extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_name',
        'barangay_id',
        'purok',
        'category',
        'date_dispensed',
    ];

    protected $casts = [
        'date_dispensed' => 'datetime',
    ];

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function dispensedMedications()
    {
        return $this->hasMany(Dispensedmedication::class, 'patientrecord_id');
    }
}