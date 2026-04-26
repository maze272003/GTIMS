<?php

namespace Tests\Feature\Admin;

use App\Models\Barangay;
use App\Models\Branch;
use App\Models\BranchArchivalRun;
use App\Models\IncomingRequest;
use App\Models\Inventory;
use App\Models\LowStockSetting;
use App\Models\Order;
use App\Models\Patientrecords;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\ReorderRule;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_archive_branch_and_migrate_operational_data(): void
    {
        $mainBranch = Branch::create([
            'name' => 'Main RHU',
            'code' => 'main-rhu',
            'is_main' => true,
            'is_archived' => false,
        ]);

        $sourceBranch = Branch::create([
            'name' => 'Satellite RHU',
            'code' => 'satellite-rhu',
            'is_main' => false,
            'is_archived' => false,
        ]);

        $superAdmin = $this->createSuperAdmin($mainBranch->id);
        $sourceUser = User::factory()->create([
            'user_level_id' => $superAdmin->user_level_id,
            'branch_id' => $sourceBranch->id,
            'email_verified_at' => now(),
        ]);

        $product = Product::factory()->create(['is_archived' => false]);

        $mainInventory = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $mainBranch->id,
            'batch_number' => 'BATCH-001',
            'quantity' => 30,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'is_archived' => false,
        ]);

        $sourceInventory = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $sourceBranch->id,
            'batch_number' => 'BATCH-001',
            'quantity' => 50,
            'expiry_date' => $mainInventory->expiry_date,
            'is_archived' => false,
        ]);

        ProductMovement::create([
            'product_id' => $product->id,
            'inventory_id' => $sourceInventory->id,
            'user_id' => $sourceUser->id,
            'type' => 'OUT',
            'quantity' => 2,
            'quantity_before' => 50,
            'quantity_after' => 48,
            'description' => 'Test movement on source branch inventory',
        ]);

        Order::create([
            'branch_id' => $sourceBranch->id,
            'user_id' => $sourceUser->id,
            'status' => 'pending_admin',
        ]);

        $barangay = Barangay::create(['barangay_name' => 'Sample Barangay']);
        Patientrecords::create([
            'patient_name' => 'Jane Doe',
            'barangay_id' => $barangay->id,
            'purok' => 'Purok 1',
            'category' => 'Adult',
            'date_dispensed' => now()->toDateString(),
            'branch_id' => $sourceBranch->id,
        ]);

        IncomingRequest::create([
            'branch_id' => $sourceBranch->id,
            'requester_id' => $sourceUser->id,
            'department' => 'Pharmacy',
            'priority' => 'high',
            'status' => 'requested',
            'remarks' => 'Urgent transfer',
        ]);

        LowStockSetting::create([
            'is_global' => false,
            'product_id' => $product->id,
            'branch_id' => $sourceBranch->id,
            'threshold' => 5,
        ]);

        ReorderRule::create([
            'product_id' => $product->id,
            'branch_id' => $sourceBranch->id,
            'preferred_supplier_id' => null,
            'reorder_point' => 10,
            'reorder_quantity' => 50,
        ]);

        $response = $this->actingAs($superAdmin)->post(
            route('admin.branches.archive', $sourceBranch->id),
            [
                'target_main_branch_id' => $mainBranch->id,
                'reason' => 'Consolidation test',
            ]
        );

        $response->assertRedirect(route('admin.branches.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('branches', [
            'id' => $sourceBranch->id,
            'is_archived' => 1,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $sourceUser->id,
            'branch_id' => $mainBranch->id,
        ]);

        $this->assertDatabaseHas('orders', [
            'branch_id' => $mainBranch->id,
            'status' => 'pending_admin',
        ]);

        $this->assertDatabaseHas('patientrecords', [
            'branch_id' => $mainBranch->id,
            'patient_name' => 'Jane Doe',
        ]);

        $this->assertDatabaseHas('incoming_requests', [
            'branch_id' => $mainBranch->id,
            'status' => 'requested',
        ]);

        $this->assertDatabaseHas('low_stock_settings', [
            'product_id' => $product->id,
            'branch_id' => $mainBranch->id,
            'threshold' => 5,
        ]);

        $this->assertDatabaseHas('reorder_rules', [
            'product_id' => $product->id,
            'branch_id' => $mainBranch->id,
        ]);

        $this->assertDatabaseMissing('inventories', [
            'id' => $sourceInventory->id,
        ]);

        $this->assertDatabaseHas('inventories', [
            'id' => $mainInventory->id,
            'quantity' => 80,
        ]);

        $this->assertDatabaseHas('product_movements', [
            'inventory_id' => $mainInventory->id,
            'description' => 'Test movement on source branch inventory',
        ]);

        $this->assertDatabaseHas('branch_archival_runs', [
            'source_branch_id' => $sourceBranch->id,
            'target_branch_id' => $mainBranch->id,
            'status' => 'completed',
            'progress_percent' => 100,
        ]);
    }

    public function test_failed_archival_run_can_be_marked_rolled_back(): void
    {
        $mainBranch = Branch::create([
            'name' => 'Main RHU',
            'code' => 'main-rhu',
            'is_main' => true,
            'is_archived' => false,
        ]);

        $sourceBranch = Branch::create([
            'name' => 'Satellite RHU',
            'code' => 'satellite-rhu',
            'is_main' => false,
            'is_archived' => false,
        ]);

        $superAdmin = $this->createSuperAdmin($mainBranch->id);

        $run = BranchArchivalRun::create([
            'source_branch_id' => $sourceBranch->id,
            'target_branch_id' => $mainBranch->id,
            'initiated_by' => $superAdmin->id,
            'status' => 'failed',
            'progress_percent' => 55,
            'started_at' => now()->subMinute(),
            'failed_at' => now()->subSeconds(5),
            'error_message' => 'Checksum mismatch.',
        ]);

        $response = $this->actingAs($superAdmin)->post(
            route('admin.branches.rollback', $run->id),
            ['reason' => 'Manual rollback marker']
        );

        $response->assertRedirect(route('admin.branches.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('branch_archival_runs', [
            'id' => $run->id,
            'status' => 'rolled_back',
        ]);
    }

    private function createSuperAdmin(int $branchId): User
    {
        $level = UserLevel::firstOrCreate(['name' => 'superadmin']);
        $branchPermission = Permission::firstOrCreate(
            ['name' => 'branches.manage'],
            ['group' => 'Settings', 'description' => 'Manage branch lifecycle']
        );
        $superAdminGatePermission = Permission::firstOrCreate(
            ['name' => 'settings.roles'],
            ['group' => 'Settings', 'description' => 'Manage role permissions']
        );
        $level->permissions()->syncWithoutDetaching([$branchPermission->id, $superAdminGatePermission->id]);

        return User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branchId,
        ]);
    }
}
