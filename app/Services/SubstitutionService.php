<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSubstitute;
use Illuminate\Support\Collection;

class SubstitutionService
{
    protected AvailabilityService $availabilityService;

    public function __construct(AvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    /**
     * Get explicit substitutes for a product.
     * OPTIMIZED: Single query instead of multiple per call
     */
    public function getExplicitSubstitutes(int $productId): Collection
    {
        return Product::find($productId)?->substitutes ?? collect();
    }

    /**
     * Get equivalence-based substitutes (same generic_name, same form, same strength).
     * OPTIMIZED: Single query for all matching products
     */
    public function getEquivalentProducts(int $productId): Collection
    {
        $product = Product::find($productId);
        if (!$product) return collect();

        return Product::where('id', '!=', $productId)
            ->where('generic_name', $product->generic_name)
            ->where('form', $product->form)
            ->where('strength', $product->strength)
            ->where('is_archived', false)
            ->get();
    }

    /**
     * Suggest available substitutes for a product at a branch.
     * OPTIMIZED: Batch queries instead of per-item queries
     * Reduces ~15-20 queries per call to ~3-4 queries
     */
    public function suggestSubstitutes(int $productId, ?int $branchId = null): array
    {
        $product = Product::with('substitutes')->find($productId);
        if (!$product) return [];

        // Get all potential substitute IDs in one collection
        $substituteIds = $product->substitutes->pluck('id')
            ->merge(
                Product::where('generic_name', $product->generic_name)
                    ->where('form', $product->form)
                    ->where('strength', $product->strength)
                    ->where('is_archived', false)
                    ->where('id', '!=', $productId)
                    ->pluck('id')
            )
            ->unique()
            ->values();

        if ($substituteIds->isEmpty()) {
            return [];
        }

        // CRITICAL: Single query to get all inventories for all substitutes
        $inventories = Inventory::whereIn('product_id', $substituteIds)
            ->where('is_archived', false)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->get()
            ->groupBy('product_id');

        // Pre-load all substitute products
        $substitutesMap = Product::whereIn('id', $substituteIds)->get()->groupBy('id');

        // Build suggestions from cached data (no additional queries)
        $suggestions = [];

        // Explicit substitutes
        foreach ($product->substitutes as $sub) {
            $available = (int) ($inventories->get($sub->id, collect())
                ->sum(fn($inv) => max(0, (int)$inv->onhand_qty - (int)$inv->hold_qty)));

            if ($available > 0) {
                $suggestions[] = [
                    'product' => $sub,
                    'available' => $available,
                    'type' => 'explicit',
                    'priority' => $sub->pivot->priority ?? 0,
                ];
            }
        }

        // Equivalent products (from inventories)
        foreach ($inventories as $prodId => $batches) {
            if ($prodId === $product->id) continue;

            // Check if already suggested as explicit
            $alreadySuggested = collect($suggestions)->contains(fn($s) => $s['product']->id === $prodId);
            if ($alreadySuggested) continue;

            $available = (int) $batches->sum(fn($inv) => max(0, (int)$inv->onhand_qty - (int)$inv->hold_qty));

            if ($available > 0) {
                $eqProduct = $substitutesMap->get($prodId)->first();
                if ($eqProduct) {
                    $suggestions[] = [
                        'product' => $eqProduct,
                        'available' => $available,
                        'type' => 'equivalent',
                        'priority' => 100,
                    ];
                }
            }
        }

        // Sort by priority
        usort($suggestions, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return $suggestions;
    }
}
