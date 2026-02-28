<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\SystemAnalyticsService;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\Hold;
use App\Models\HoldItem;
use App\Models\IncomingRequest;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\User;
use App\Models\UserLevel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SystemAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SystemAnalyticsService $service;
    protected User $user;
    protected Branch $branch;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SystemAnalyticsService();

        $level = UserLevel::create(['name' => 'admin']);
        $this->branch = Branch::create(['name' => 'RHU 1']);
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->product = Product::factory()->create();
    }

    // -------------------------------------------------------
    // getSystemOverview
    // -------------------------------------------------------

    public function test_system_overview_returns_all_expected_keys(): void
    {
        $overview = $this->service->getSystemOverview();

        $this->assertArrayHasKey('total_products', $overview);
        $this->assertArrayHasKey('total_batches', $overview);
        $this->assertArrayHasKey('total_stock', $overview);
        $this->assertArrayHasKey('expiring_in_30_days', $overview);
        $this->assertArrayHasKey('expired_batches', $overview);
        $this->assertArrayHasKey('pending_requests', $overview);
        $this->assertArrayHasKey('active_holds', $overview);
        $this->assertArrayHasKey('today_movements', $overview);
        $this->assertArrayHasKey('recent_audit_events', $overview);
    }

    public function test_system_overview_counts_products(): void
    {
        Product::factory()->count(3)->create();

        $overview = $this->service->getSystemOverview();

        // 1 from setUp + 3 new = 4
        $this->assertEquals(4, $overview['total_products']);
    }

    public function test_system_overview_counts_inventory_batches(): void
    {
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
        ]);

        $overview = $this->service->getSystemOverview();

        $this->assertEquals(1, $overview['total_batches']);
        $this->assertEquals(100, $overview['total_stock']);
    }

    public function test_system_overview_counts_expiring_batches(): void
    {
        // Expiring in 15 days (should count)
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 50,
            'expiry_date' => Carbon::now()->addDays(15),
        ]);

        // Expiring in 60 days (should not count)
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 30,
            'expiry_date' => Carbon::now()->addDays(60),
        ]);

        // Already expired (should count as expired)
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 10,
            'expiry_date' => Carbon::now()->subDays(5),
        ]);

        $overview = $this->service->getSystemOverview();

        $this->assertEquals(1, $overview['expiring_in_30_days']);
        $this->assertEquals(1, $overview['expired_batches']);
    }

    public function test_system_overview_counts_pending_requests(): void
    {
        IncomingRequest::factory()->create([
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'status' => 'draft',
        ]);
        IncomingRequest::factory()->create([
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'status' => 'requested',
        ]);
        IncomingRequest::factory()->create([
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'status' => 'closed',
        ]);

        $overview = $this->service->getSystemOverview();

        $this->assertEquals(2, $overview['pending_requests']);
    }

    public function test_system_overview_counts_active_holds(): void
    {
        Hold::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
        ]);
        Hold::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->user->id,
            'status' => 'approved',
        ]);
        Hold::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->user->id,
            'status' => 'released',
        ]);

        $overview = $this->service->getSystemOverview();

        $this->assertEquals(2, $overview['active_holds']);
    }

    public function test_system_overview_filters_by_branch(): void
    {
        $branch2 = Branch::create(['name' => 'RHU 2']);

        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
        ]);
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $branch2->id,
            'quantity' => 200,
        ]);

        $overview = $this->service->getSystemOverview($this->branch->id);

        $this->assertEquals(100, $overview['total_stock']);
    }

    // -------------------------------------------------------
    // getInventoryMovementTrends
    // -------------------------------------------------------

    public function test_inventory_movement_trends_returns_expected_structure(): void
    {
        $result = $this->service->getInventoryMovementTrends();

        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('to', $result);
        $this->assertArrayHasKey('group_by', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('day', $result['group_by']);
    }

    public function test_inventory_movement_trends_aggregates_data(): void
    {
        $inventory = Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
        ]);

        ProductMovement::factory()->create([
            'product_id' => $this->product->id,
            'inventory_id' => $inventory->id,
            'user_id' => $this->user->id,
            'type' => 'IN',
            'quantity' => 50,
            'created_at' => Carbon::today(),
        ]);
        ProductMovement::factory()->create([
            'product_id' => $this->product->id,
            'inventory_id' => $inventory->id,
            'user_id' => $this->user->id,
            'type' => 'OUT',
            'quantity' => 20,
            'created_at' => Carbon::today(),
        ]);

        $result = $this->service->getInventoryMovementTrends(
            Carbon::today()->subDay(),
            Carbon::today()->addDay()
        );

        $this->assertNotEmpty($result['data']);
        $dayData = $result['data'][0];
        $this->assertEquals(50, $dayData['total_in']);
        $this->assertEquals(20, $dayData['total_out']);
        $this->assertEquals(2, $dayData['movement_count']);
    }

    public function test_inventory_movement_trends_respects_date_range(): void
    {
        $inventory = Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
        ]);

        ProductMovement::factory()->create([
            'product_id' => $this->product->id,
            'inventory_id' => $inventory->id,
            'user_id' => $this->user->id,
            'type' => 'IN',
            'quantity' => 50,
            'created_at' => Carbon::now()->subDays(60),
        ]);

        $result = $this->service->getInventoryMovementTrends(
            Carbon::now()->subDays(7),
            Carbon::now()
        );

        $this->assertEmpty($result['data']);
    }

    public function test_inventory_movement_trends_filters_by_branch(): void
    {
        $branch2 = Branch::create(['name' => 'RHU 2']);

        $inv1 = Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
        ]);
        $inv2 = Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $branch2->id,
            'quantity' => 100,
        ]);

        ProductMovement::factory()->create([
            'product_id' => $this->product->id,
            'inventory_id' => $inv1->id,
            'user_id' => $this->user->id,
            'type' => 'IN',
            'quantity' => 50,
            'created_at' => Carbon::today(),
        ]);
        ProductMovement::factory()->create([
            'product_id' => $this->product->id,
            'inventory_id' => $inv2->id,
            'user_id' => $this->user->id,
            'type' => 'IN',
            'quantity' => 30,
            'created_at' => Carbon::today(),
        ]);

        $result = $this->service->getInventoryMovementTrends(
            Carbon::today()->subDay(),
            Carbon::today()->addDay(),
            $this->branch->id
        );

        $this->assertCount(1, $result['data']);
        $this->assertEquals(50, $result['data'][0]['total_in']);
    }

    // -------------------------------------------------------
    // getStockLevelDistribution
    // -------------------------------------------------------

    public function test_stock_level_distribution_returns_product_data(): void
    {
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
        ]);

        $result = $this->service->getStockLevelDistribution();

        $this->assertNotEmpty($result);
        $this->assertEquals($this->product->id, $result[0]['product_id']);
        $this->assertEquals(100, $result[0]['total_on_hand']);
        $this->assertEquals(100, $result[0]['available']);
        $this->assertEquals(0, $result[0]['held']);
    }

    public function test_stock_level_distribution_accounts_for_holds(): void
    {
        $inventory = Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
        ]);

        $hold = Hold::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->user->id,
            'status' => 'approved',
        ]);
        HoldItem::create([
            'hold_id' => $hold->id,
            'product_id' => $this->product->id,
            'inventory_id' => $inventory->id,
            'quantity' => 30,
        ]);

        $result = $this->service->getStockLevelDistribution();

        $this->assertEquals(100, $result[0]['total_on_hand']);
        $this->assertEquals(30, $result[0]['held']);
        $this->assertEquals(70, $result[0]['available']);
    }

    public function test_stock_level_distribution_excludes_archived(): void
    {
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
            'is_archived' => true,
        ]);

        $result = $this->service->getStockLevelDistribution();

        $this->assertEmpty($result);
    }

    // -------------------------------------------------------
    // getExpiryTracking
    // -------------------------------------------------------

    public function test_expiry_tracking_categorizes_batches(): void
    {
        // Expired
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 10,
            'expiry_date' => Carbon::now()->subDays(5),
        ]);
        // Within 30 days
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 20,
            'expiry_date' => Carbon::now()->addDays(15),
        ]);
        // Within 90 days
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 30,
            'expiry_date' => Carbon::now()->addDays(60),
        ]);
        // Within 180 days
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 40,
            'expiry_date' => Carbon::now()->addDays(120),
        ]);
        // Beyond 180 days
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 50,
            'expiry_date' => Carbon::now()->addDays(365),
        ]);

        $result = $this->service->getExpiryTracking();

        $this->assertEquals(1, $result['summary']['expired']);
        $this->assertEquals(1, $result['summary']['within_30_days']);
        $this->assertEquals(1, $result['summary']['within_90_days']);
        $this->assertEquals(1, $result['summary']['within_180_days']);
        $this->assertEquals(1, $result['summary']['beyond_180_days']);

        $this->assertCount(1, $result['expired']);
        $this->assertCount(1, $result['within_30_days']);
    }

    public function test_expiry_tracking_returns_expected_item_keys(): void
    {
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 10,
            'expiry_date' => Carbon::now()->addDays(10),
        ]);

        $result = $this->service->getExpiryTracking();

        $item = $result['within_30_days'][0];
        $this->assertArrayHasKey('inventory_id', $item);
        $this->assertArrayHasKey('product_name', $item);
        $this->assertArrayHasKey('batch_number', $item);
        $this->assertArrayHasKey('quantity', $item);
        $this->assertArrayHasKey('expiry_date', $item);
        $this->assertArrayHasKey('days_until_expiry', $item);
    }

    public function test_expiry_tracking_excludes_zero_quantity(): void
    {
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 0,
            'expiry_date' => Carbon::now()->addDays(10),
        ]);

        $result = $this->service->getExpiryTracking();

        $this->assertEquals(0, $result['summary']['within_30_days']);
    }

    // -------------------------------------------------------
    // getRequestStatusDistribution
    // -------------------------------------------------------

    public function test_request_status_distribution_counts_by_status(): void
    {
        IncomingRequest::factory()->count(3)->create([
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'status' => 'draft',
        ]);
        IncomingRequest::factory()->count(2)->create([
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'status' => 'approved',
        ]);
        IncomingRequest::factory()->create([
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'status' => 'closed',
        ]);

        $result = $this->service->getRequestStatusDistribution();

        $this->assertEquals(6, $result['total']);
        $this->assertCount(3, $result['distribution']);

        $statuses = array_column($result['distribution'], 'status');
        $this->assertContains('draft', $statuses);
        $this->assertContains('approved', $statuses);
        $this->assertContains('closed', $statuses);
    }

    public function test_request_status_distribution_filters_by_branch(): void
    {
        $branch2 = Branch::create(['name' => 'RHU 2']);

        IncomingRequest::factory()->count(3)->create([
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'status' => 'draft',
        ]);
        IncomingRequest::factory()->count(2)->create([
            'branch_id' => $branch2->id,
            'requester_id' => $this->user->id,
            'status' => 'draft',
        ]);

        $result = $this->service->getRequestStatusDistribution($this->branch->id);

        $this->assertEquals(3, $result['total']);
    }

    public function test_request_status_distribution_empty_when_no_requests(): void
    {
        $result = $this->service->getRequestStatusDistribution();

        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['distribution']);
    }

    // -------------------------------------------------------
    // getRequestVolumeTrends
    // -------------------------------------------------------

    public function test_request_volume_trends_returns_expected_structure(): void
    {
        $result = $this->service->getRequestVolumeTrends();

        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('to', $result);
        $this->assertArrayHasKey('group_by', $result);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_request_volume_trends_counts_by_priority(): void
    {
        IncomingRequest::factory()->create([
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'priority' => 'urgent',
            'created_at' => Carbon::today(),
        ]);
        IncomingRequest::factory()->create([
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'priority' => 'high',
            'created_at' => Carbon::today(),
        ]);
        IncomingRequest::factory()->create([
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'priority' => 'normal',
            'created_at' => Carbon::today(),
        ]);

        $result = $this->service->getRequestVolumeTrends(
            Carbon::today()->subDay(),
            Carbon::today()->addDay()
        );

        $this->assertNotEmpty($result['data']);
        $dayData = $result['data'][0];
        $this->assertEquals(3, $dayData['total_requests']);
        $this->assertEquals(1, $dayData['urgent_count']);
        $this->assertEquals(1, $dayData['high_count']);
        $this->assertEquals(1, $dayData['normal_count']);
    }

    // -------------------------------------------------------
    // getHoldAnalytics
    // -------------------------------------------------------

    public function test_hold_analytics_returns_expected_structure(): void
    {
        $result = $this->service->getHoldAnalytics();

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('by_status', $result);
        $this->assertArrayHasKey('by_type', $result);
    }

    public function test_hold_analytics_groups_by_status_and_type(): void
    {
        Hold::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
            'type' => 'reservation',
        ]);
        Hold::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
            'type' => 'quarantine',
        ]);
        Hold::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->user->id,
            'status' => 'approved',
            'type' => 'reservation',
        ]);

        $result = $this->service->getHoldAnalytics();

        $this->assertEquals(3, $result['total']);

        $statuses = array_column($result['by_status'], 'status');
        $this->assertContains('pending', $statuses);
        $this->assertContains('approved', $statuses);

        $types = array_column($result['by_type'], 'type');
        $this->assertContains('reservation', $types);
        $this->assertContains('quarantine', $types);
    }

    public function test_hold_analytics_filters_by_branch(): void
    {
        $branch2 = Branch::create(['name' => 'RHU 2']);

        Hold::factory()->count(3)->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->user->id,
        ]);
        Hold::factory()->count(2)->create([
            'branch_id' => $branch2->id,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getHoldAnalytics($this->branch->id);

        $this->assertEquals(3, $result['total']);
    }

    // -------------------------------------------------------
    // getUserActivityTrends
    // -------------------------------------------------------

    public function test_user_activity_trends_returns_expected_structure(): void
    {
        $result = $this->service->getUserActivityTrends();

        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('to', $result);
        $this->assertArrayHasKey('group_by', $result);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_user_activity_trends_aggregates_events(): void
    {
        AuditEvent::create([
            'action' => 'create',
            'entity_type' => 'product',
            'entity_id' => 1,
            'user_id' => $this->user->id,
            'created_at' => Carbon::today(),
        ]);
        AuditEvent::create([
            'action' => 'update',
            'entity_type' => 'inventory',
            'entity_id' => 1,
            'user_id' => $this->user->id,
            'created_at' => Carbon::today(),
        ]);

        $result = $this->service->getUserActivityTrends(
            Carbon::today()->subDay(),
            Carbon::today()->addDay()
        );

        $this->assertNotEmpty($result['data']);
        $dayData = $result['data'][0];
        $this->assertEquals(2, $dayData['total_events']);
        $this->assertEquals(1, $dayData['unique_users']);
    }

    public function test_user_activity_trends_counts_unique_users(): void
    {
        $user2 = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => UserLevel::first()->id,
            'branch_id' => $this->branch->id,
        ]);

        AuditEvent::create([
            'action' => 'create',
            'entity_type' => 'product',
            'entity_id' => 1,
            'user_id' => $this->user->id,
            'created_at' => Carbon::today(),
        ]);
        AuditEvent::create([
            'action' => 'create',
            'entity_type' => 'product',
            'entity_id' => 2,
            'user_id' => $user2->id,
            'created_at' => Carbon::today(),
        ]);

        $result = $this->service->getUserActivityTrends(
            Carbon::today()->subDay(),
            Carbon::today()->addDay()
        );

        $this->assertEquals(2, $result['data'][0]['unique_users']);
    }

    // -------------------------------------------------------
    // getAuditEventDistribution
    // -------------------------------------------------------

    public function test_audit_event_distribution_groups_by_action_and_entity(): void
    {
        AuditEvent::create([
            'action' => 'create',
            'entity_type' => 'product',
            'entity_id' => 1,
            'user_id' => $this->user->id,
        ]);
        AuditEvent::create([
            'action' => 'create',
            'entity_type' => 'inventory',
            'entity_id' => 1,
            'user_id' => $this->user->id,
        ]);
        AuditEvent::create([
            'action' => 'update',
            'entity_type' => 'product',
            'entity_id' => 1,
            'user_id' => $this->user->id,
        ]);

        $result = $this->service->getAuditEventDistribution();

        $this->assertArrayHasKey('by_action', $result);
        $this->assertArrayHasKey('by_entity', $result);

        $actions = array_column($result['by_action'], 'action');
        $this->assertContains('create', $actions);
        $this->assertContains('update', $actions);

        $entities = array_column($result['by_entity'], 'entity_type');
        $this->assertContains('product', $entities);
        $this->assertContains('inventory', $entities);
    }

    public function test_audit_event_distribution_respects_date_range(): void
    {
        // Use DB::table to set created_at to 60 days ago since AuditEvent is immutable
        $event = AuditEvent::create([
            'action' => 'create',
            'entity_type' => 'product',
            'entity_id' => 1,
            'user_id' => $this->user->id,
        ]);
        \Illuminate\Support\Facades\DB::table('audit_events')
            ->where('id', $event->id)
            ->update(['created_at' => Carbon::now()->subDays(60)]);

        $result = $this->service->getAuditEventDistribution(
            Carbon::now()->subDays(7),
            Carbon::now()
        );

        $this->assertEmpty($result['by_action']);
        $this->assertEmpty($result['by_entity']);
    }

    // -------------------------------------------------------
    // getInventoryTurnover
    // -------------------------------------------------------

    public function test_inventory_turnover_returns_expected_structure(): void
    {
        $result = $this->service->getInventoryTurnover();

        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('to', $result);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_inventory_turnover_calculates_rate(): void
    {
        $inventory = Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
        ]);

        ProductMovement::factory()->create([
            'product_id' => $this->product->id,
            'inventory_id' => $inventory->id,
            'user_id' => $this->user->id,
            'type' => 'OUT',
            'quantity' => 50,
            'created_at' => Carbon::today(),
        ]);
        ProductMovement::factory()->create([
            'product_id' => $this->product->id,
            'inventory_id' => $inventory->id,
            'user_id' => $this->user->id,
            'type' => 'IN',
            'quantity' => 30,
            'created_at' => Carbon::today(),
        ]);

        $result = $this->service->getInventoryTurnover(
            Carbon::today()->subDay(),
            Carbon::today()->addDay()
        );

        $this->assertNotEmpty($result['data']);
        $item = $result['data'][0];
        $this->assertEquals($this->product->id, $item['product_id']);
        $this->assertEquals(30, $item['total_in']);
        $this->assertEquals(50, $item['total_out']);
        $this->assertEquals(100, $item['current_stock']);
        $this->assertEquals(0.5, $item['turnover_rate']); // 50/100
    }

    public function test_inventory_turnover_excludes_archived_products(): void
    {
        $archivedProduct = Product::factory()->create(['is_archived' => true]);
        $inventory = Inventory::factory()->create([
            'product_id' => $archivedProduct->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
        ]);

        ProductMovement::factory()->create([
            'product_id' => $archivedProduct->id,
            'inventory_id' => $inventory->id,
            'user_id' => $this->user->id,
            'type' => 'OUT',
            'quantity' => 50,
            'created_at' => Carbon::today(),
        ]);

        $result = $this->service->getInventoryTurnover(
            Carbon::today()->subDay(),
            Carbon::today()->addDay()
        );

        $this->assertEmpty($result['data']);
    }

    public function test_inventory_turnover_sorted_by_rate_descending(): void
    {
        $product2 = Product::factory()->create();

        $inv1 = Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
        ]);
        $inv2 = Inventory::factory()->create([
            'product_id' => $product2->id,
            'branch_id' => $this->branch->id,
            'quantity' => 50,
        ]);

        ProductMovement::factory()->create([
            'product_id' => $this->product->id,
            'inventory_id' => $inv1->id,
            'user_id' => $this->user->id,
            'type' => 'OUT',
            'quantity' => 20,
            'created_at' => Carbon::today(),
        ]);
        ProductMovement::factory()->create([
            'product_id' => $product2->id,
            'inventory_id' => $inv2->id,
            'user_id' => $this->user->id,
            'type' => 'OUT',
            'quantity' => 40,
            'created_at' => Carbon::today(),
        ]);

        $result = $this->service->getInventoryTurnover(
            Carbon::today()->subDay(),
            Carbon::today()->addDay()
        );

        $this->assertCount(2, $result['data']);
        // product2 has higher turnover: 40/50 = 0.8 vs 20/100 = 0.2
        $this->assertEquals($product2->id, $result['data'][0]['product_id']);
    }

    // -------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------

    public function test_all_methods_work_with_empty_database(): void
    {
        // Only setUp data exists (1 user, 1 branch, 1 product, no inventory/movements)
        $overview = $this->service->getSystemOverview();
        $this->assertEquals(1, $overview['total_products']);
        $this->assertEquals(0, $overview['total_batches']);

        $movements = $this->service->getInventoryMovementTrends();
        $this->assertEmpty($movements['data']);

        $stock = $this->service->getStockLevelDistribution();
        $this->assertEmpty($stock);

        $expiry = $this->service->getExpiryTracking();
        $this->assertEquals(0, $expiry['summary']['expired']);

        $requests = $this->service->getRequestStatusDistribution();
        $this->assertEquals(0, $requests['total']);

        $requestVolume = $this->service->getRequestVolumeTrends();
        $this->assertEmpty($requestVolume['data']);

        $holds = $this->service->getHoldAnalytics();
        $this->assertEquals(0, $holds['total']);

        $activity = $this->service->getUserActivityTrends();
        $this->assertEmpty($activity['data']);

        $audit = $this->service->getAuditEventDistribution();
        $this->assertEmpty($audit['by_action']);

        $turnover = $this->service->getInventoryTurnover();
        $this->assertEmpty($turnover['data']);
    }

    public function test_group_by_month_works_for_movement_trends(): void
    {
        $inventory = Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
        ]);

        ProductMovement::factory()->create([
            'product_id' => $this->product->id,
            'inventory_id' => $inventory->id,
            'user_id' => $this->user->id,
            'type' => 'IN',
            'quantity' => 50,
            'created_at' => Carbon::today(),
        ]);

        $result = $this->service->getInventoryMovementTrends(
            Carbon::today()->subDay(),
            Carbon::today()->addDay(),
            null,
            'month'
        );

        $this->assertEquals('month', $result['group_by']);
        $this->assertNotEmpty($result['data']);
    }

    public function test_group_by_week_works_for_request_volume(): void
    {
        IncomingRequest::factory()->create([
            'branch_id' => $this->branch->id,
            'requester_id' => $this->user->id,
            'created_at' => Carbon::today(),
        ]);

        $result = $this->service->getRequestVolumeTrends(
            Carbon::today()->subDay(),
            Carbon::today()->addDay(),
            null,
            'week'
        );

        $this->assertEquals('week', $result['group_by']);
        $this->assertNotEmpty($result['data']);
    }
}
