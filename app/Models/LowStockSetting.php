<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LowStockSetting extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'branch_id', 'threshold', 'is_global'];

    protected $casts = [
        'is_global' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public static function getThresholdFor(?int $productId = null, ?int $branchId = null): int
    {
        // Check for product+branch-specific override first
        if ($productId && $branchId) {
            $setting = self::where('product_id', $productId)->where('branch_id', $branchId)->first();
            if ($setting) return $setting->threshold;
        }

        // Check for product-specific override
        if ($productId) {
            $setting = self::where('product_id', $productId)->whereNull('branch_id')->first();
            if ($setting) return $setting->threshold;
        }

        // Fall back to global default
        $global = self::where('is_global', true)->first();
        return $global ? $global->threshold : 10;
    }
}
