<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use App\Tenancy\TenantContext;
use App\Traits\EncryptsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Patientrecords extends Model
{
    use HasFactory, TenantScoped, EncryptsAttributes;

    protected $fillable = [
        'patient_name',
        'province_id',
        'barangay_id',
        'purok',
        'category',
        'date_dispensed',
        'branch_id', // <--- Add this
    ];

    protected $casts = [
        'date_dispensed' => 'datetime',
    ];

    protected array $encryptable = [
        'patient_name',
        'purok',
    ];

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function dispensedMedications()
    {
        return $this->hasMany(Dispensedmedication::class, 'patientrecord_id');
    }

public function branch()
{
    return $this->belongsTo(Branch::class);
}

    public function scopeForProvince($query, int $provinceId)
    {
        return $query->where('province_id', $provinceId);
    }

    public function scopeForBarangay($query, int $provinceId, int $barangayId)
    {
        return $query->where('province_id', $provinceId)
            ->where('barangay_id', $barangayId);
    }

    public function scopeForTenant($query, TenantContext $ctx)
    {
        if ($ctx->isPlatform()) {
            return $query;
        }

        $query->where('province_id', $ctx->provinceId);

        if ($ctx->isBarangay()) {
            $query->where('barangay_id', $ctx->barangayId);
        }

        return $query;
    }
}
