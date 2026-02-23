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

        // Check for branch default (all products at a branch)
        if ($branchId) {
            $setting = self::whereNull('product_id')->where('branch_id', $branchId)->first();
            if ($setting) return $setting->threshold;
        }

        // Fall back to global default
        $global = self::where('is_global', true)->first();
        return $global ? $global->threshold : 10;
    }

    /**
     * Get aggregated stock for a product across multiple batches at a specific branch.
     * Sums all non-archived inventory batch quantities for the product at the branch.
     */
    public static function getAggregatedStockForBranch(int $productId, int $branchId): int
    {
        return (int) Inventory::where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('is_archived', false)
            ->sum('quantity');
    }

    /**
     * Check if a product is low stock at a specific branch,
     * considering all batches at that branch.
     */
    public static function isLowStockAtBranch(int $productId, int $branchId): bool
    {
        $threshold = self::getThresholdFor($productId, $branchId);
        $totalStock = self::getAggregatedStockForBranch($productId, $branchId);

        return $totalStock <= $threshold;
    }

    /**
     * Get low stock products across all branches, properly handling
     * products with multiple batches at different branches.
     *
     * Returns an array of alerts with product, branch, stock, and threshold info.
     */
    public static function getLowStockProducts(): array
    {
        $products = Product::where('is_archived', false)->get();
        $branches = Branch::all();
        $alerts = [];

        foreach ($products as $product) {
            foreach ($branches as $branch) {
                $totalStock = self::getAggregatedStockForBranch($product->id, $branch->id);

                // Skip branches where this product has no inventory
                if ($totalStock === 0) {
                    $hasInventory = Inventory::where('product_id', $product->id)
                        ->where('branch_id', $branch->id)
                        ->exists();
                    if (!$hasInventory) {
                        continue;
                    }
                }

                $threshold = self::getThresholdFor($product->id, $branch->id);

                if ($totalStock <= $threshold) {
                    $alerts[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->generic_name ?? $product->brand_name,
                        'branch_id' => $branch->id,
                        'branch_name' => $branch->name,
                        'total_stock' => $totalStock,
                        'threshold' => $threshold,
                        'batch_count' => Inventory::where('product_id', $product->id)
                            ->where('branch_id', $branch->id)
                            ->where('is_archived', false)
                            ->count(),
                    ];
                }
            }
        }

        return $alerts;
    }

}
