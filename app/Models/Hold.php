<?php

namespace App\Models;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hold extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id', 'branch_id', 'barangay_id', 'type', 'reason_code', 'remarks',
        'created_by', 'approved_by', 'status', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(HoldItem::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(HoldStatusHistory::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
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
