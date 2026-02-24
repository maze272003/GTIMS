<?php

namespace App\Models\Traits;

use App\Models\Barangay;

trait BelongsToBarangay
{
    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function scopeForBarangay($query, int $provinceId, int $barangayId)
    {
        return $query->where($this->getTable() . '.province_id', $provinceId)
            ->where($this->getTable() . '.barangay_id', $barangayId);
    }
}
