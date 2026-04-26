<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\Hold;
use App\Models\IncomingRequest;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\User;
use App\Models\UserLevel;
use App\Models\Permission;
use App\Models\RolePermission;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SystemAnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $level = UserLevel::create(['name' => 'Super Admin']);
        $this->branch = Branch::create(['name' => 'RHU 1']);
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->product = Product::factory()->create();

        // Set up permissions so the user can access admin routes
        $permissions = [
            'dashboard.view',
            'reports.view',
        ];
        foreach ($permissions as $perm) {
            $p = Permission::firstOrCreate(['name' => $perm]);
            RolePermission::firstOrCreate([
                'user_level_id' => $level->id,
                'permission_id' => $p->id,
            ]);
        }
    }

    public function test_overview_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/analytics/overview');

        $response->assertOk()
            ->assertJsonStructure([
                'total_products',
                'total_batches',
                'total_stock',
                'expiring_in_30_days',
                'expired_batches',
                'pending_requests',
                'active_holds',
                'today_movements',
                'recent_audit_events',
            ]);
    }

    public function test_inventory_movement_trends_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/analytics/inventory-movement-trends');

        $response->assertOk()
            ->assertJsonStructure([
                'from',
                'to',
                'group_by',
                'data',
            ]);
    }

    public function test_stock_level_distribution_endpoint(): void
    {
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/admin/analytics/stock-level-distribution');

        $response->assertOk()
            ->assertJsonStructure(['distribution']);
    }

    public function test_expiry_tracking_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/analytics/expiry-tracking');

        $response->assertOk()
            ->assertJsonStructure([
                'summary' => [
                    'expired',
                    'within_30_days',
                    'within_90_days',
                    'within_180_days',
                    'beyond_180_days',
                ],
            ]);
    }

    public function test_request_status_distribution_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/analytics/request-status-distribution');

        $response->assertOk()
            ->assertJsonStructure([
                'total',
                'distribution',
            ]);
    }

    public function test_request_volume_trends_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/analytics/request-volume-trends');

        $response->assertOk()
            ->assertJsonStructure([
                'from',
                'to',
                'group_by',
                'data',
            ]);
    }

    public function test_hold_analytics_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/analytics/hold-analytics');

        $response->assertOk()
            ->assertJsonStructure([
                'total',
                'by_status',
                'by_type',
            ]);
    }

    public function test_user_activity_trends_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/analytics/user-activity-trends');

        $response->assertOk()
            ->assertJsonStructure([
                'from',
                'to',
                'group_by',
                'data',
            ]);
    }

    public function test_audit_event_distribution_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/analytics/audit-event-distribution');

        $response->assertOk()
            ->assertJsonStructure([
                'from',
                'to',
                'by_action',
                'by_entity',
            ]);
    }

    public function test_inventory_turnover_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/analytics/inventory-turnover');

        $response->assertOk()
            ->assertJsonStructure([
                'from',
                'to',
                'data',
            ]);
    }

    public function test_endpoints_accept_query_parameters(): void
    {
        $from = Carbon::now()->subDays(7)->toDateString();
        $to = Carbon::now()->toDateString();

        $response = $this->actingAs($this->user)
            ->getJson("/admin/analytics/inventory-movement-trends?from={$from}&to={$to}&group_by=month&branch_id={$this->branch->id}");

        $response->assertOk();
        $data = $response->json();
        $this->assertEquals('month', $data['group_by']);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        $response = $this->getJson('/admin/analytics/overview');

        $response->assertUnauthorized();
    }
}
