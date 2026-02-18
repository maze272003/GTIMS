<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSubstitute;

class SubstitutionService
{
    protected AvailabilityService $availabilityService;

    public function __construct(AvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    /**
     * Get explicit substitutes for a product.
     */
    public function getExplicitSubstitutes(int $productId): \Illuminate\Database\Eloquent\Collection
    {
        return Product::find($productId)?->substitutes ?? collect();
    }

    /**
     * Get equivalence-based substitutes (same generic_name, same form, same strength).
     */
    public function getEquivalentProducts(int $productId): \Illuminate\Database\Eloquent\Collection
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
     */
    public function suggestSubstitutes(int $productId, ?int $branchId = null): array
    {
        $suggestions = [];

        // Explicit substitutes first
        foreach ($this->getExplicitSubstitutes($productId) as $sub) {
            $available = $this->availabilityService->getAvailable($sub->id, $branchId);
            if ($available > 0) {
                $suggestions[] = [
                    'product' => $sub,
                    'available' => $available,
                    'type' => 'explicit',
                    'priority' => $sub->pivot->priority ?? 0,
                ];
            }
        }

        // Equivalence-based substitutes
        foreach ($this->getEquivalentProducts($productId) as $eq) {
            $available = $this->availabilityService->getAvailable($eq->id, $branchId);
            if ($available > 0) {
                // Avoid duplicates
                $existingIds = array_column(array_map(fn($s) => ['id' => $s['product']->id], $suggestions), 'id');
                if (!in_array($eq->id, $existingIds)) {
                    $suggestions[] = [
                        'product' => $eq,
                        'available' => $available,
                        'type' => 'equivalent',
                        'priority' => 100, // Lower priority than explicit
                    ];
                }
            }
        }

        // Sort by priority
        usort($suggestions, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return $suggestions;
    }
}
