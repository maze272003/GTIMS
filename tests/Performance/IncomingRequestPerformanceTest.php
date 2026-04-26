<?php

namespace Tests\Performance;

use App\Models\Branch;
use App\Models\IncomingRequest;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\RequestItem;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class IncomingRequestPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'name' => 'RHU 1',
            'code' => 'rhu-1',
            'is_archived' => false,
        ]);

        $this->user = $this->createAuthorizedUser([
            'requests.view',
        ]);
    }

    public function test_show_view_query_count_below_threshold(): void
    {
        $incomingRequest = $this->createIncomingRequestWithItems(100, 20);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->user)->get(route('admin.requests.show', $incomingRequest));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertLessThan(25, $queryCount, "Expected fewer than 25 queries, got {$queryCount}.");
    }

    public function test_large_request_memory_usage_reasonable(): void
    {
        $incomingRequest = $this->createIncomingRequestWithItems(520, 25);

        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }

        $before = memory_get_usage(true);
        $response = $this->actingAs($this->user)->get(route('admin.requests.show', $incomingRequest));
        $peakDelta = memory_get_peak_usage(true) - $before;

        $response->assertOk();
        $this->assertLessThan(50 * 1024 * 1024, $peakDelta, "Expected memory delta below 50MB, got {$peakDelta} bytes.");
    }

    public function test_rate_limiting_prevents_abuse(): void
    {
        $incomingRequest = $this->createIncomingRequestWithItems(5, 2);
        $rateLimitKey = "view-request:{$this->user->id}:{$incomingRequest->id}";

        RateLimiter::clear($rateLimitKey);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = $this->actingAs($this->user)
                ->from(route('admin.requests.index'))
                ->get(route('admin.requests.show', $incomingRequest));

            $response->assertOk();
        }

        $blockedResponse = $this->actingAs($this->user)
            ->from(route('admin.requests.index'))
            ->get(route('admin.requests.show', $incomingRequest));

        $blockedResponse->assertRedirect(route('admin.requests.index'));
        $blockedResponse->assertSessionHas('error', 'Too many requests');
    }

    public function test_show_handles_empty_product_list(): void
    {
        $incomingRequest = IncomingRequest::create([
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'department' => 'Pharmacy',
            'priority' => 'normal',
            'status' => 'draft',
            'remarks' => 'No items yet',
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.requests.show', $incomingRequest));

        $response->assertOk();
        $response->assertSee("Request #{$incomingRequest->id}");
    }

    private function createAuthorizedUser(array $permissionNames): User
    {
        $level = UserLevel::create([
            'name' => 'admin',
        ]);

        $level->permissions()->sync(
            collect($permissionNames)
                ->map(fn (string $name): int => Permission::firstOrCreate(
                    ['name' => $name],
                    ['group' => 'Requests', 'description' => $name]
                )->id)
                ->all()
        );

        return User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    private function createIncomingRequestWithItems(int $itemCount, ?int $uniqueProductCount = null): IncomingRequest
    {
        $incomingRequest = IncomingRequest::create([
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'department' => 'Pharmacy',
            'priority' => 'normal',
            'status' => 'draft',
            'remarks' => 'Performance test request',
        ]);

        $uniqueProductCount = min($itemCount, max(1, $uniqueProductCount ?? $itemCount));
        $originalProducts = [];

        for ($index = 1; $index <= $uniqueProductCount; $index++) {
            $genericName = "Medicine {$index}";
            $originalProduct = Product::factory()->create([
                'generic_name' => $genericName,
                'brand_name' => "{$genericName} Original",
                'form' => 'Tablet',
                'strength' => '500mg',
                'is_archived' => false,
            ]);
            $originalProducts[] = $originalProduct;

            $equivalentProduct = Product::factory()->create([
                'generic_name' => $genericName,
                'brand_name' => "{$genericName} Equivalent",
                'form' => 'Tablet',
                'strength' => '500mg',
                'is_archived' => false,
            ]);

            Inventory::create([
                'product_id' => $originalProduct->id,
                'branch_id' => $this->branch->id,
                'batch_number' => "REQ-{$index}",
                'quantity' => 12,
                'onhand_qty' => 12,
                'hold_qty' => 0,
                'expiry_date' => now()->addMonths(3)->toDateString(),
                'is_archived' => false,
            ]);

            Inventory::create([
                'product_id' => $equivalentProduct->id,
                'branch_id' => $this->branch->id,
                'batch_number' => "SUB-{$index}",
                'quantity' => 20,
                'onhand_qty' => 20,
                'hold_qty' => 0,
                'expiry_date' => now()->addMonths(3)->toDateString(),
                'is_archived' => false,
            ]);
        }

        for ($index = 1; $index <= $itemCount; $index++) {
            $originalProduct = $originalProducts[($index - 1) % $uniqueProductCount];

            RequestItem::create([
                'incoming_request_id' => $incomingRequest->id,
                'product_id' => $originalProduct->id,
                'quantity_requested' => 5,
                'allow_substitution' => true,
            ]);
        }

        return $incomingRequest;
    }
}
