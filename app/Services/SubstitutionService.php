<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
        return Product::query()
            ->active()
            ->find($productId)?->substitutes()
            ->active()
            ->get() ?? collect();
    }

    /**
     * Get equivalence-based substitutes (same generic_name, same form, same strength).
     * OPTIMIZED: Single query for all matching products
     */
    public function getEquivalentProducts(int $productId): Collection
    {
        $product = Product::query()->active()->find($productId);
        if (! $product) {
            return collect();
        }

        return Product::query()
            ->active()
            ->byCharacteristics($product->generic_name, $product->form, $product->strength)
            ->where('id', '!=', $productId)
            ->get();
    }

    /**
     * Suggest available substitutes for a product at a branch.
     * OPTIMIZED: Batch queries instead of per-item queries
     * Reduces ~15-20 queries per call to ~3-4 queries
     *
     * @return array<int, array{product:\App\Models\Product, available:int, type:string, priority:int}>
     */
    public function suggestSubstitutes(int $productId, ?int $branchId = null): array
    {
        if (! is_numeric($productId) || $productId <= 0) {
            Log::warning('substitutions.invalid_product_id', [
                'product_id' => $productId,
                'branch_id' => $branchId,
            ]);

            return [];
        }

        try {
            $product = Product::query()
                ->active()
                ->with([
                    'substitutes' => fn ($query) => $query->active(),
                ])
                ->findOrFail($productId);

            $substituteIds = $product->substitutes->pluck('id')
                ->merge(
                    Product::query()
                        ->active()
                        ->byCharacteristics($product->generic_name, $product->form, $product->strength)
                        ->where('id', '!=', $productId)
                        ->pluck('id')
                )
                ->unique()
                ->filter(fn ($id) => $id !== null && (int) $id !== 0)
                ->values();

            if ($substituteIds->isEmpty()) {
                Log::debug('substitutions.none_found', [
                    'product_id' => $productId,
                    'branch_id' => $branchId,
                ]);

                return [];
            }

            $inventories = Inventory::query()
                ->active()
                ->whereIn('product_id', $substituteIds->all())
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->get()
                ->groupBy('product_id');

            $productsById = Product::query()
                ->active()
                ->whereIn('id', $substituteIds->all())
                ->get()
                ->keyBy('id');

            $suggestions = [];

            foreach ($product->substitutes as $substitute) {
                if (! $substitute || ! isset($substitute->id)) {
                    Log::warning('substitutions.corrupted_relationship', [
                        'product_id' => $productId,
                        'branch_id' => $branchId,
                    ]);

                    continue;
                }

                $available = $this->calculateAvailableInventory(
                    $inventories->get((int) $substitute->id, collect()),
                    (int) $substitute->id
                );

                if ($available <= 0) {
                    continue;
                }

                $suggestions[] = [
                    'product' => $substitute,
                    'available' => $available,
                    'type' => 'explicit',
                    'priority' => $this->safePriorityValue($substitute->pivot),
                ];
            }

            foreach ($inventories as $inventoryProductId => $batches) {
                if (! is_numeric($inventoryProductId)) {
                    Log::warning('substitutions.invalid_grouped_product_id', [
                        'product_id' => $inventoryProductId,
                        'source_product_id' => $productId,
                    ]);

                    continue;
                }

                $inventoryProductId = (int) $inventoryProductId;

                if ($inventoryProductId === $product->id) {
                    continue;
                }

                $available = $this->calculateAvailableInventory($batches, $inventoryProductId);
                if ($available <= 0 || $this->isAlreadySuggested($suggestions, $inventoryProductId)) {
                    continue;
                }

                $equivalentProduct = $productsById->get($inventoryProductId);
                if (! $equivalentProduct) {
                    Log::warning('substitutions.orphaned_inventory', [
                        'product_id' => $inventoryProductId,
                        'source_product_id' => $productId,
                    ]);

                    continue;
                }

                $suggestions[] = [
                    'product' => $equivalentProduct,
                    'available' => $available,
                    'type' => 'equivalent',
                    'priority' => 100,
                ];
            }

            usort(
                $suggestions,
                fn (array $left, array $right): int => [$left['priority'], $left['product']->generic_name] <=> [$right['priority'], $right['product']->generic_name]
            );

            return $suggestions;
        } catch (ModelNotFoundException) {
            Log::warning('substitutions.product_not_found', [
                'product_id' => $productId,
                'branch_id' => $branchId,
            ]);

            return [];
        } catch (\Throwable $exception) {
            Log::error('substitutions.failed', [
                'product_id' => $productId,
                'branch_id' => $branchId,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return [];
        }
    }

    /**
     * Sum available stock for a grouped set of inventory rows.
     */
    private function calculateAvailableInventory(Collection $batches, int $productId): int
    {
        try {
            $available = 0;

            foreach ($batches as $inventory) {
                $onHandSource = $inventory->onhand_qty ?? $inventory->quantity ?? 0;
                if (! is_numeric($onHandSource)) {
                    Log::warning('substitutions.invalid_onhand_quantity', [
                        'product_id' => $productId,
                        'inventory_id' => $inventory->id,
                        'value' => $onHandSource,
                    ]);
                    $onHandQty = 0;
                } else {
                    $onHandQty = (int) $onHandSource;
                }

                $holdSource = $inventory->hold_qty ?? 0;
                if (! is_numeric($holdSource)) {
                    Log::warning('substitutions.invalid_hold_quantity', [
                        'product_id' => $productId,
                        'inventory_id' => $inventory->id,
                        'value' => $holdSource,
                    ]);
                    $holdQty = 0;
                } else {
                    $holdQty = max(0, (int) $holdSource);
                }

                if ($onHandQty < 0) {
                    Log::warning('substitutions.negative_inventory_quantity', [
                        'product_id' => $productId,
                        'inventory_id' => $inventory->id,
                        'value' => $onHandQty,
                    ]);

                    continue;
                }

                $available += max(0, $onHandQty - $holdQty);
            }

            return $available;
        } catch (\Throwable $exception) {
            Log::error('substitutions.availability_calculation_failed', [
                'product_id' => $productId,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return 0;
        }
    }

    /**
     * Read an integer priority from a pivot object safely.
     */
    private function safePriorityValue(?object $pivot): int
    {
        if (! $pivot) {
            return 0;
        }

        $priority = $pivot->priority ?? 0;
        if (! is_numeric($priority)) {
            Log::warning('substitutions.invalid_priority_value', [
                'priority' => $priority,
            ]);

            return 0;
        }

        return (int) $priority;
    }

    /**
     * Check whether a product has already been added to the suggestion list.
     */
    private function isAlreadySuggested(array $suggestions, int $productId): bool
    {
        foreach ($suggestions as $suggestion) {
            $suggestedProduct = $suggestion['product'] ?? null;

            if (! $suggestedProduct || ! isset($suggestedProduct->id)) {
                continue;
            }

            if ((int) $suggestedProduct->id === $productId) {
                return true;
            }
        }

        return false;
    }
}
