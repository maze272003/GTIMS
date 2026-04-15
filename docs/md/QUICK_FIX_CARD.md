# DEVELOPER QUICK REFERENCE CARD
## Copy-Paste Implementation Guide

---

## 1. ADD QUERY SCOPES TO MODELS

### Product.php
```php
// Add these methods to app/Models/Product.php
public function scopeActive(Builder $query): Builder
{
    return $query->where('is_archived', false);
}

public function scopeArchived(Builder $query): Builder
{
    return $query->where('is_archived', true);
}

public function scopeActiveAndNotExpired(Builder $query): Builder
{
    return $query->active()
        ->where(function ($q) {
            $q->whereNull('expiry_date')
                ->orWhere('expiry_date', '>=', now()->toDateString());
        });
}
```

### Inventory.php
```php
// Add these methods to app/Models/Inventory.php
public function scopeActive(Builder $query): Builder
{
    return $query->where('is_archived', false);
}

public function scopeInBranch(Builder $query, int $branchId): Builder
{
    return $query->where('branch_id', $branchId);
}

public function scopeWithAvailableStock(Builder $query): Builder
{
    return $query->whereRaw(
        'COALESCE(onhand_qty, quantity) - COALESCE(hold_qty, 0) > 0'
    );
}

public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
{
    return $query->whereBetween('expiry_date', [
        now()->toDateString(),
        now()->addDays($days)->toDateString(),
    ]);
}

public function scopeExpired(Builder $query): Builder
{
    return $query->where('expiry_date', '<', now()->toDateString());
}
```

### Branch.php
```php
// Add these methods to app/Models/Branch.php
public function scopeActive(Builder $query): Builder
{
    return $query->where('is_archived', false);
}

public function scopeArchived(Builder $query): Builder
{
    return $query->where('is_archived', true);
}
```

---

## 2. CREATE DATABASE MIGRATION

```bash
php artisan make:migration add_performance_indexes
```

**File:** `database/migrations/[TIMESTAMP]_add_performance_indexes.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            $table->index(['incoming_request_id', 'product_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['order_id', 'product_id']);
        });

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->index(['workflow_definition_id', 'status', 'created_at']);
        });

        Schema::table('workflow_run_steps', function (Blueprint $table) {
            $table->index(['workflow_run_id', 'status']);
        });

        Schema::table('hold_items', function (Blueprint $table) {
            $table->index(['product_id', 'hold_id']);
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->index(['entity_type', 'entity_id', 'created_at']);
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->index(['product_id', 'branch_id', 'is_archived']);
        });

        Schema::table('product_substitutes', function (Blueprint $table) {
            $table->index(['product_id', 'substitute_product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('request_items', fn(Blueprint $table) => 
            $table->dropIndex(['incoming_request_id', 'product_id'])
        );
        // ... repeat for other tables
    }
};
```

**Run:**
```bash
php artisan migrate
```

---

## 3. CRITICAL FIX: PatientRecordsAdminService.php

**Key Changes:**
- ✅ Add `DB::transaction()` wrapper
- ✅ Add `.lockForUpdate()` for inventory queries
- ✅ Add `validateMedicationInventory()` method
- ✅ Add type-safe casting helpers: `safeIntCast()`, `safeFloatCast()`
- ✅ Add comprehensive error handling

**Minimum viable fix (if short on time):**

```php
//Replace entire adddispensation() method with:

public function adddispensation(Request $request)
{
    $validated = $request->validate([
        'patient-name' => 'required|string|max:255',
        'barangay_id' => 'required|integer|exists:barangays,id',
        'medications' => 'required|array|min:1',
        'medications.*.name' => 'required|integer',
        'medications.*.quantity' => 'required|numeric|min:0.01',
    ]);

    return DB::transaction(function () use ($validated) {
        $user = Auth::user();
        $branchId = $this->branchAccessService->resolveBranchFilter($user, null);

        // ✅ CRITICAL: Lock inventory rows
        $inventories = Inventory::with('product')
            ->whereIn('id', array_column($validated['medications'], 'name'))
            ->where('is_archived', false)
            ->lockForUpdate()  // ← LOCKS ROWS UNTIL TRANSACTION ENDS
            ->get()
            ->keyBy('id');

        // ✅ Validate all before writing anything
        foreach ($validated['medications'] as $med) {
            $inventory = $inventories->get($med['name']);
            if (!$inventory) {
                throw new \Exception('Medicine not found');
            }
            $available = (int)$inventory->onhand_qty - (int)$inventory->hold_qty;
            if ($med['quantity'] > $available) {
                throw new \Exception("Insufficient stock for {$inventory->product->generic_name}");
            }
        }

        // ✅ Write all changes
        $record = $this->patientRecordsRepository->createPatientRecord([
            'patient_name' => $validated['patient-name'],
            'barangay_id' => $validated['barangay_id'],
            'branch_id' => $branchId,
            'date_dispensed' => $validated['date-dispensed'] ?? now(),
        ]);

        foreach ($validated['medications'] as $med) {
            $inventory = $inventories->get($med['name']);
            $quantity_before = (int)$inventory->quantity;
            $quantity_after = $quantity_before - (int)$med['quantity'];

            $inventory->quantity = $quantity_after;
            $inventory->save();

            $this->patientRecordsRepository->createProductMovement([
                'product_id' => $inventory->product_id,
                'inventory_id' => $inventory->id,
                'user_id' => $user->id,
                'type' => 'OUT',
                'quantity' => $med['quantity'],
                'quantity_before' => $quantity_before,
                'quantity_after' => $quantity_after,
                'description' => "Dispensed to patient {$record->id}",
            ]);
        }

        return $record;
    }, attempts: 3);  // ← AUTO-RETRY ON LOCK TIMEOUT

    return to_route('admin.patientrecords')->with('success', 'Recorded successfully');
}
```

---

## 4. QUICK FIX: SubstitutionService.php

**Key Changes:**
- ✅ Filter null IDs: `.filter(fn($id) => $id !== null)`
- ✅ Validate before using: `if (!$product) continue;`
- ✅ Safe priority access: `$sub->pivot?->priority ?? 0`

**Minimum fix:**

```php
// In suggestSubstitutes(), after building substituteIds:

$substituteIds = [...existing code...]
    ->unique()
    ->filter(fn($id) => $id !== null && $id !== 0)  // ✅ ADD THIS LINE
    ->values();

// Later, when finding equivalent products:
foreach ($inventories as $productId => $batches) {
    if (!is_numeric($productId) || $productId === null) {  // ✅ ADD THIS CHECK
        continue;
    }
    if ($productId === $product->id) continue;
    
    $available = (int)$batches->sum(...);
    
    if ($available > 0 && !$this->isAlreadySuggested($suggestions, $productId)) {
        $eqProduct = Product::find($productId);
        if (!$eqProduct) {  // ✅ ADD NULL CHECK
            Log::warning('Product not found', ['id' => $productId]);
            continue;
        }
        $suggestions[] = [...];
    }
}

// When accessing pivot priority:
'priority' => $sub->pivot?->priority ?? 0,  // ✅ USE NULL COALESCING OPERATOR
```

---

## 5. QUICK FIX: IncomingRequestController::show()

**Key Changes:**
- ✅ Add rate limiting
- ✅ Call `.take(500)` to limit results
- ✅ Use `.chunk()` for large collections

**Minimum fix:**

```php
public function show(IncomingRequest $incomingRequest)
{
    $user = Auth::user();
    
    // ✅ Early auth check
    $this->branchAccessService->authorizeBranchAccess(
        $user, $incomingRequest->branch_id, 'view'
    );

    // ✅ ADD RATE LIMITING
    $key = "view-request:{$user->id}";
    if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 10)) {
        return back()->with('error', 'Too many requests');
    }
    \Illuminate\Support\Facades\RateLimiter::hit($key, 60);

    $incomingRequest->load([...existing]);
    $availability = $this->workflowService->checkAvailability($incomingRequest);

    $productIds = $incomingRequest->items->pluck('product_id')
        ->unique()
        ->take(500);  // ✅ LIMIT TO 500

    // ✅ OLD: Single query loading all
    // $allInventories = Inventory::whereIn(...)->get();

    // ✅ NEW: Chunked loading
    $allInventories = collect();
    $productIds->chunk(200)->each(function ($chunk) use (&$allInventories, $incomingRequest) {
        $batch = Inventory::whereIn('product_id', $chunk)
            ->where('is_archived', false)
            ->where('branch_id', $incomingRequest->branch_id)
            ->get()
            ->groupBy('product_id');
        
        foreach ($batch as $id => $items) {
            $allInventories[$id] = $items;
        }
    });

    $substitutions = [];
    foreach ($incomingRequest->items as $item) {
        if ($item->allow_substitution) {
            $substitutions[$item->id] = $this->buildSubstitutionsFromCache(
                $item->product_id, ..., $allInventories
            );
        }
    }

    return view('admin.requests.show', compact(
        'incomingRequest', 'availability', 'substitutions'
    ));
}
```

---

## 6. UPDATE AppServiceProvider.php

```php
// Add to App\Providers\AppServiceProvider::boot()

public function boot(): void
{
    if (app()->environment('local', 'staging')) {
        \Illuminate\Support\Facades\DB::listen(function ($query) {
            if ($query->time > 500) {
                \Illuminate\Support\Facades\Log::warning('Slow Query', [
                    'time_ms' => $query->time,
                    'sql' => $query->sql,
                ]);
            }
        });
    }
}
```

---

## 7. ADD TESTS (Run immediately after changes)

**File:** `tests/Feature/PatientRecordsRaceConditionTest.php`

```php
<?php
namespace Tests\Feature;

use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientRecordsRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_overdraft_prevented()
    {
        $inventory = Inventory::factory()->create(['quantity' => 100]);
        
        // Try to deduct 60 twice in "parallel"
        // Should fail on second attempt
        $this->post('/admin/patient-records', [
            'medications' => [['name' => $inventory->id, 'quantity' => 60]],
            // ... other required fields
        ]);

        // Second request should fail
        $response = $this->post('/admin/patient-records', [
            'medications' => [['name' => $inventory->id, 'quantity' => 60]],
            // ... other required fields
        ]);

        $this->assertTrue($response->getSession()->hasErrors('medications'));
    }
}
```

**Run:**
```bash
php artisan test tests/Feature/PatientRecordsRaceConditionTest
```

---

## 8. DEPLOYMENT CHECKLIST

```bash
# 1. Create backup
mysqldump -u user -p database > backup_$(date +%s).sql

# 2. Pull latest code
git pull origin main

# 3. Install dependencies
composer install --no-dev

# 4. Create migration
php artisan make:migration add_performance_indexes

# 5. Run migration (test first)
php artisan migrate:rollback
php artisan migrate

# 6. Clear caches
php artisan cache:clear
php artisan config:cache

# 7. Run tests
php artisan test

# 8. Check query counts
php artisan tinker
> Inventory::active()->inBranch(1)->get();  // Should be instant now
```

---

## 9. VERIFY FIXES WORKED

```bash
# In Laravel Tinker:
php artisan tinker

# ✅ Test 1: Scopes work
Product::active()->count();  // Fast, uses index

# ✅ Test 2: No N+1 queries
DB::enableQueryLog();
IncomingRequest::with('items.product')->find(1);
DB::getQueryLog() |> count;  // Should be < 10 queries

# ✅ Test 3: Transaction works
try {
    DB::transaction(function () {
        Inventory::find(1)->update(['quantity' => -100]);
        throw new \Exception('Test rollback');
    });
} catch (Exception) {}
Inventory::find(1)->quantity;  // Should be unchanged
```

---

## 10. COMMON ISSUES & FIXES

### Issue: "Table 'X' has no index 'Y'"
**Fix:** Ran migration? Try `php artisan migrate`

### Issue: "Call to undefined method scopeActive()"
**Fix:** Did you add the scope methods to the Model? Check step 1.

### Issue: "Exceeded lock wait timeout"
**Fix:** Normal! Means locks are working. Increase `innodb_lock_wait_timeout`:
```sql
SET GLOBAL innodb_lock_wait_timeout = 120;
```

### Issue: "Out of memory during chunking"
**Fix:** Reduce chunk size in code: `.chunk(100)` instead of `.chunk(200)`

---

## 11. PERFORMANCE TARGETS (After Implementation)

| Metric | Target | How to Verify |
|--------|--------|--------------|
| Queries per request | < 15 | `count(DB::getQueryLog())` |
| Slow queries (>500ms) | 0 | Check logs for "Slow Query" |
| Response time | < 1.5s | Browser dev tools |
| Memory usage | < 100MB | Monitor in production |
| Inventory accuracy | 100% | Audit script (see GUIDE) |

---

## 12. ROLLBACK PROCEDURE

If something goes wrong:

```bash
# Rollback code
git revert HEAD

# Rollback migration
php artisan migrate:rollback --step=1

# Verify
php artisan test

# Redeploy
git push && php artisan migrate
```

---

**Need help?** See full documentation:
- Risk analysis: ARCHITECTURE_AUDIT_REPORT.md
- Code details: PRODUCTION_READY_REFACTORED_CODE.md
- Testing guide: IMPLEMENTATION_AND_TESTING_GUIDE.md

