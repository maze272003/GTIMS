<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\IncomingRequest;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAccessIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_create_inventory_for_another_branch(): void
    {
        [$branchOne, $branchTwo] = $this->createBranches();
        $user = $this->createUserWithPermissions($branchOne, ['inventory.view', 'inventory.add', 'dashboard.view']);
        $product = Product::factory()->create(['is_archived' => false]);

        $response = $this->actingAs($user)->post(route('admin.inventory.addstock'), [
            'product_id' => $product->id,
            'branch_id' => $branchTwo->id,
            'batchnumber' => 'BATCH-CROSS',
            'quantity' => 25,
            'expiry' => now()->addYear()->format('Y-m-d'),
        ]);

        $response->assertForbidden();
        $response->assertSee('contact the superadmin', false);

        $this->assertDatabaseMissing('inventories', [
            'branch_id' => $branchTwo->id,
            'batch_number' => 'BATCH-CROSS',
        ]);
    }

    public function test_user_cannot_open_request_from_another_branch(): void
    {
        [$branchOne, $branchTwo] = $this->createBranches();
        $viewer = $this->createUserWithPermissions($branchOne, ['requests.view']);
        $requester = $this->createUserWithPermissions($branchTwo, ['requests.view']);

        $incomingRequest = IncomingRequest::create([
            'branch_id' => $branchTwo->id,
            'requester_id' => $requester->id,
            'department' => 'Pharmacy',
            'priority' => 'normal',
            'status' => 'draft',
            'remarks' => 'Cross-branch isolation test',
        ]);

        $response = $this->actingAs($viewer)->get(route('admin.requests.show', $incomingRequest));

        $response->assertForbidden();
        $response->assertSee('contact the superadmin', false);
    }

    public function test_analytics_overview_defaults_to_users_branch(): void
    {
        [$branchOne, $branchTwo] = $this->createBranches();
        $user = $this->createUserWithPermissions($branchOne, ['reports.view']);
        $product = Product::factory()->create(['is_archived' => false]);

        Inventory::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $branchOne->id,
            'quantity' => 40,
            'is_archived' => false,
        ]);

        Inventory::factory()->create([
            'product_id' => $product->id,
            'branch_id' => $branchTwo->id,
            'quantity' => 90,
            'is_archived' => false,
        ]);

        $response = $this->actingAs($user)->getJson(route('admin.analytics.overview'));

        $response->assertOk();
        $response->assertJson([
            'total_batches' => 1,
            'total_stock' => 40,
        ]);
    }

    public function test_analytics_overview_rejects_other_branch_filter(): void
    {
        [$branchOne, $branchTwo] = $this->createBranches();
        $user = $this->createUserWithPermissions($branchOne, ['reports.view']);

        $response = $this->actingAs($user)->get(route('admin.analytics.overview', [
            'branch_id' => $branchTwo->id,
        ]));

        $response->assertForbidden();
        $response->assertSee('contact the superadmin', false);
    }

    /**
     * @return array{0: Branch, 1: Branch}
     */
    private function createBranches(): array
    {
        $branchOne = Branch::create([
            'name' => 'RHU 1',
            'code' => 'rhu-1',
            'is_archived' => false,
        ]);

        $branchTwo = Branch::create([
            'name' => 'RHU 2',
            'code' => 'rhu-2',
            'is_archived' => false,
        ]);

        return [$branchOne, $branchTwo];
    }

    /**
     * @param array<int, string> $permissionNames
     */
    private function createUserWithPermissions(Branch $branch, array $permissionNames): User
    {
        $level = UserLevel::create([
            'name' => 'branch-level-'.UserLevel::count(),
        ]);

        $permissionIds = collect($permissionNames)
            ->map(fn (string $name) => Permission::firstOrCreate([
                'name' => $name,
            ], [
                'group' => 'Test',
                'description' => $name,
            ])->id)
            ->all();

        $level->permissions()->sync($permissionIds);

        return User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
            'uses_custom_permissions' => false,
        ]);
    }
}
