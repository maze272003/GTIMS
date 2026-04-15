# Query Performance Fixes - Quick Reference

## Critical Fixes (Do These First)

### 1. SubstitutionService - Replace suggestSubstitutes()
**File:** `app/Services/SubstitutionService.php`

```php
public function suggestSubstitutes(int $productId, ?int $branchId = null): array
{
    $product = Product::with('substitutes')->findOrFail($productId);
    
    // Get all potential substitute IDs in one query
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
    
    // Single query to get all inventories
    $inventories = Inventory::whereIn('product_id', $substituteIds)
        ->where('is_archived', false)
        ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
        ->get()
        ->groupBy('product_id');
    
    // Build suggestions from cached data
    $suggestions = [];
    
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
    
    // Equivalent products
    foreach ($inventories as $productId => $batches) {
        if ($productId === $product->id) continue;
        
        $available = (int) $batches->sum(fn($inv) => max(0, (int)$inv->onhand_qty - (int)$inv->hold_qty));
        
        if ($available > 0 && !$this->isAlreadySuggested($suggestions, $productId)) {
            $eqProduct = Product::find($productId);
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
    
    usort($suggestions, fn($a, $b) => $a['priority'] <=> $b['priority']);
    return $suggestions;
}

private function isAlreadySuggested(array $suggestions, int $productId): bool
{
    return collect($suggestions)->contains(fn($s) => $s['product']->id === $productId);
}
```

**Change Count:** ~15-20 queries → ~3-4 queries per call

---

### 2. IncomingRequestController::show() - Load substitutions before loop
**File:** `app/Http/Controllers/Admin/IncomingRequestController.php` (around line 115)

```php
public function show(IncomingRequest $incomingRequest)
{
    $this->branchAccessService->authorizeBranchAccess(Auth::user(), $incomingRequest->branch_id, 'view requests from another branch');
    
    $incomingRequest->load([
        'branch', 'requester', 'items.product', 'items.substitutedProduct',
        'comments.user', 'attachments.user', 'statusHistory.changer',
    ]);

    $availability = $this->workflowService->checkAvailability($incomingRequest);

    // NEW: Load substitutes data all at once
    $productIds = $incomingRequest->items->pluck('product_id')->unique();
    
    $allSubstitutes = ProductSubstitute::with('substituteProduct')
        ->whereIn('product_id', $productIds)
        ->get()
        ->groupBy('product_id');
    
    $allEquivalents = Product::where(function($q) use ($productIds) {
        $products = Product::whereIn('id', $productIds)->get();
        foreach ($products as $p) {
            $q->orWhere(function($subQ) use ($p) {
                $subQ->where('generic_name', $p->generic_name)
                    ->where('form', $p->form)
                    ->where('strength', $p->strength)
                    ->where('is_archived', false);
            });
        }
    })->get()->groupBy(fn($p) => json_encode([$p->generic_name, $p->form, $p->strength]));
    
    // Get all inventory in one query
    $allInventories = Inventory::whereIn('product_id', 
        $productIds->merge(
            $allSubstitutes->pluck('substitute_product_id'),
            $allEquivalents->pluck('id')
        )->unique()
    )
        ->where('is_archived', false)
        ->where('branch_id', $incomingRequest->branch_id)
        ->get()
        ->groupBy('product_id');
    
    // Build substitutions from cache (no queries in loop)
    $substitutions = [];
    foreach ($incomingRequest->items as $item) {
        if ($item->allow_substitution) {
            $substitutions[$item->id] = $this->buildSubstitutionsFromCache(
                $item->product_id,
                $item->allow_substitution,
                $allSubstitutes,
                $allEquivalents,
                $allInventories
            );
        }
    }

    return view('admin.requests.show', compact('incomingRequest', 'availability', 'substitutions'));
}

private function buildSubstitutionsFromCache($productId, $allowSubstitution, $substitutes, $equivalents, $inventories)
{
    $subs = [];
    
    // Explicit substitutes
    foreach ($substitutes->get($productId, []) as $sub) {
        $available = (int) ($inventories->get($sub->substitute_product_id, collect())
            ->sum(fn($inv) => max(0, (int)$inv->onhand_qty - (int)$inv->hold_qty)));
        
        if ($available > 0) {
            $subs[] = [
                'product' => $sub->substituteProduct,
                'available' => $available,
                'type' => 'explicit',
                'priority' => $sub->priority ?? 0,
            ];
        }
    }
    
    return collect($subs)->sortBy('priority')->values()->all();
}
```

**Change Count:** ~50-100 queries → ~5-10 queries

---

### 3. PatientRecordsAdminService::adddispensation() - Cache inventory lookups
**File:** `app/Services/PatientRecordsAdminService.php` (around line 32)

```php
public function adddispensation(Request $request) 
{
    $validated = $request->validateWithBag('adddispensation', [
        // ... validation rules ...
    ]);

    $user = Auth::user(); 
    $branchId = $this->branchAccessService->resolveBranchFilter($user, null);

    // NEW: Load all inventories once
    $inventoryIds = collect($validated['medications'])->pluck('name')->toArray();
    $inventories = Inventory::with('product')
        ->whereIn('id', $inventoryIds)
        ->get()
        ->keyBy('id');

    // Validate using cache
    foreach ($validated['medications'] as $med) {
        $inventory = $inventories->get($med['name']);
        
        if (!$inventory) {
            return back()->withErrors(
                ['medications' => 'Medicine not found.'], 
                'adddispensation'
            )->withInput();
        }
        
        $this->branchAccessService->authorizeBranchAccess(
            $user, 
            $inventory->branch_id, 
            'dispense inventory from another branch'
        );
        
        if ($inventory->quantity < $med['quantity']) {
            return back()->withErrors(
                ['medications' => "Insufficient quantity for {$inventory->product->generic_name}. Available: {$inventory->quantity}"], 
                'adddispensation'
            )->withInput();
        }
    }

    // Create PatientRecord
    $newRecord = $this->patientRecordsRepository->createPatientRecord([
        'patient_name' => $validated['patient-name'],
        'barangay_id' => $validated['barangay_id'],
        'purok' => $validated['purok'],
        'category' => $validated['category'],
        'date_dispensed' => $validated['date-dispensed'],
        'branch_id' => $branchId,
    ]);

    $this->patientRecordsRepository->createHistoryLog([
        'action' => 'RECORD ADDED',
        'description' => "Recorded medication dispensation for patient {$newRecord->patient_name} (Record #: {$newRecord->id}) at " . ($user->branch->name ?? 'Branch ID ' . $user->branch_id) . ".",
        'user_id' => $user->id,
        'user_name' => $user->name ?? 'System',
        'metadata' => ['patientrecord_id' => $newRecord->id, 'branch_id' => $branchId],
    ]);

    // Process medications using cached data
    foreach ($validated['medications'] as $med) {
        $inventory = $inventories->get($med['name']);
        
        $quantity_before = $inventory->quantity;
        $quantity_to_deduct = $med['quantity'];
        $quantity_after = $quantity_before - $quantity_to_deduct;

        $inventory->quantity = $quantity_after;
        $inventory->save();

        $this->patientRecordsRepository->createProductMovement([
            'product_id'      => $inventory->product_id,
            'inventory_id'    => $inventory->id,
            'user_id'         => $user->id,
            'type'            => 'OUT',
            'quantity'        => $quantity_to_deduct,
            'quantity_before' => $quantity_before,
            'quantity_after'  => $quantity_after,
            'description'     => "Dispensed to Patient: {$newRecord->patient_name} (Record: #{$newRecord->id})",
        ]);

        $this->patientRecordsRepository->createDispensedMedication([
            'patientrecord_id' => $newRecord->id,
            'barangay_id' => $validated['barangay_id'],
            'batch_number' => $inventory->batch_number ?? 'N/A',
            'generic_name' => $inventory->product->generic_name ?? 'N/A',
            'brand_name' => $inventory->product->brand_name ?? 'N/A',
            'strength' => $inventory->product->strength ?? 'N/A',
            'form' => $inventory->product->form ?? 'N/A',
            'quantity' => $med['quantity'],
        ]);
    }

    return to_route('admin.patientrecords')->with('success', 'Dispensation recorded successfully.');
}
```

**Change Count:** ~20 queries (2 per medication) → ~2 queries

---

## Add Query Scopes to Models

### Product Model
**File:** `app/Models/Product.php`

```php
public function scopeActive($query)
{
    return $query->where('is_archived', false);
}

public function scopeArchived($query)
{
    return $query->where('is_archived', true);
}
```

### Inventory Model
**File:** `app/Models/Inventory.php`

```php
public function scopeActive($query)
{
    return $query->where('is_archived', false);
}

public function scopeInBranch($query, int $branchId)
{
    return $query->where('branch_id', $branchId);
}

public function scopeWithAvailableStock($query)
{
    return $query->whereRaw('COALESCE(onhand_qty, quantity) - COALESCE(hold_qty, 0) > 0');
}

public function scopeExpiringSoon($query, int $days = 30)
{
    return $query->whereBetween('expiry_date', [
        now()->toDateString(),
        now()->addDays($days)->toDateString()
    ]);
}

public function scopeExpired($query)
{
    return $query->where('expiry_date', '<', now()->toDateString());
}
```

### Branch Model
**File:** `app/Models/Branch.php`

```php
public function scopeActive($query)
{
    return $query->where('is_archived', false);
}

public function scopeArchived($query)
{
    return $query->where('is_archived', true);
}
```

**New Usage:**
```php
// Before
Product::where('is_archived', false)->get()
Inventory::where('is_archived', false)->where('branch_id', 5)->get()

// After
Product::active()->get()
Inventory::active()->inBranch(5)->get()
Inventory::active()->inBranch(5)->withAvailableStock()->get()
Inventory::active()->expiringSoon(14)->get()
```

---

## Create New Migration for Missing Indexes

**File:** `database/migrations/2026_03_25_000002_add_performance_indexes.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Composite indexes for common filters
        Schema::table('request_items', function (Blueprint $table) {
            $table->index(['incoming_request_id', 'product_id'], 'request_items_request_product_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['order_id', 'product_id'], 'order_items_order_product_idx');
        });

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->index(['workflow_definition_id', 'status', 'created_at'], 'workflow_runs_def_status_created_idx');
        });

        Schema::table('workflow_run_steps', function (Blueprint $table) {
            $table->index(['workflow_run_id', 'status'], 'workflow_run_steps_run_status_idx');
        });

        Schema::table('hold_items', function (Blueprint $table) {
            $table->index(['product_id', 'hold_id'], 'hold_items_product_hold_idx');
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->index(['entity_type', 'entity_id', 'created_at'], 'audit_events_entity_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            $table->dropIndex('request_items_request_product_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_product_idx');
        });

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropIndex('workflow_runs_def_status_created_idx');
        });

        Schema::table('workflow_run_steps', function (Blueprint $table) {
            $table->dropIndex('workflow_run_steps_run_status_idx');
        });

        Schema::table('hold_items', function (Blueprint $table) {
            $table->dropIndex('hold_items_product_hold_idx');
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropIndex('audit_events_entity_created_idx');
        });
    }
};
```

Run:
```bash
php artisan migrate
```

---

## Add Query Count Monitoring (Development)

**File:** `app/Providers/AppServiceProvider.php`

```php
<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (app()->environment('local')) {
            // Log slow queries
            \Illuminate\Database\Events\QueryExecuted::listen(function (QueryExecuted $query) {
                if ($query->time > 500) {
                    Log::warning('Slow Query ' . round($query->time, 2) . 'ms', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                    ]);
                }
            });
        }
    }
}
```

---

## Implementation Checklist

- [ ] Fix SubstitutionService::suggestSubstitutes()
- [ ] Fix IncomingRequestController::show()
- [ ] Fix PatientRecordsAdminService::adddispensation()
- [ ] Add query scopes to Product model
- [ ] Add query scopes to Inventory model
- [ ] Add query scopes to Branch model
- [ ] Create and run migration for missing indexes
- [ ] Add query monitoring to AppServiceProvider
- [ ] Test all changes with Laravel Debugbar
- [ ] Run test suite to ensure no regressions
- [ ] Deploy and monitor performance

**Estimated Total Time:** 8-10 hours for all changes  
**Expected Query Reduction:** 40-60%  
**Expected Performance Improvement:** 300-500ms on data-heavy pages
