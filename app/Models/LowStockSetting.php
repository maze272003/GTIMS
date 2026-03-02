<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LowStockSetting extends Model
{
    use HasFactory;

    public const SOURCE_BRANCH_OVERRIDE = 'branch_override';
    public const SOURCE_GLOBAL_OVERRIDE = 'global_override';
    public const SOURCE_DEFAULT_THRESHOLD = 'default_threshold';

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
        return (int) self::resolveThresholdFor($productId, $branchId)['threshold'];
    }

    /**
     * Resolve the effective threshold and source metadata.
     *
     * Resolution order:
     * 1) Branch override (product+branch)
     * 2) Branch override default (all products at a branch)
     * 3) Global override (product across all branches)
     * 4) Default threshold (global default row, or fallback 10)
     */
    public static function resolveThresholdFor(?int $productId = null, ?int $branchId = null): array
    {
        if ($productId && $branchId) {
            $branchProduct = self::query()
                ->where('is_global', false)
                ->where('product_id', $productId)
                ->where('branch_id', $branchId)
                ->first();

            if ($branchProduct) {
                return [
                    'threshold' => (int) $branchProduct->threshold,
                    'source' => self::SOURCE_BRANCH_OVERRIDE,
                ];
            }
        }

        if ($branchId) {
            $branchDefault = self::query()
                ->where('is_global', false)
                ->whereNull('product_id')
                ->where('branch_id', $branchId)
                ->first();

            if ($branchDefault) {
                return [
                    'threshold' => (int) $branchDefault->threshold,
                    'source' => self::SOURCE_BRANCH_OVERRIDE,
                ];
            }
        }

        if ($productId) {
            $globalProduct = self::query()
                ->where('is_global', false)
                ->where('product_id', $productId)
                ->whereNull('branch_id')
                ->first();

            if ($globalProduct) {
                return [
                    'threshold' => (int) $globalProduct->threshold,
                    'source' => self::SOURCE_GLOBAL_OVERRIDE,
                ];
            }
        }

        $global = self::query()->where('is_global', true)->first();

        return [
            'threshold' => (int) ($global?->threshold ?? 10),
            'source' => self::SOURCE_DEFAULT_THRESHOLD,
        ];
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
        $branches = Branch::query()->active()->get();
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
