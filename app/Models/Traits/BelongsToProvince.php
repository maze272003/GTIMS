<?php

namespace App\Models\Traits;

use App\Models\Province;

trait BelongsToProvince
{
    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function scopeForProvince($query, int $provinceId)
    {
        return $query->where($this->getTable() . '.province_id', $provinceId);
    }
}
