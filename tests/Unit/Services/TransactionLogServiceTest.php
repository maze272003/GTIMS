<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TransactionLogService;
use App\Models\User;
use App\Models\UserLevel;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class TransactionLogServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionLogService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionLogService();

        $level = UserLevel::create(['name' => 'admin']);
        $branch = Branch::create(['name' => 'RHU 1']);
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_log_creates_notification_entry()
    {
        $this->service->log('create', 'inventory', [
            'product_name' => 'Paracetamol',
            'quantity' => 100,
        ], $this->user);

        $this->assertDatabaseCount('notifications', 1);

        $notification = DB::table('notifications')->first();
        $this->assertEquals('App\\Notifications\\TransactionLog', $notification->type);
        $this->assertEquals($this->user->id, $notification->notifiable_id);

        $data = json_decode($notification->data, true);
        $this->assertEquals('create', $data['action_type']);
        $this->assertEquals('inventory', $data['category']);
        $this->assertEquals('Paracetamol', $data['details']['product_name']);
        $this->assertEquals(100, $data['details']['quantity']);
        $this->assertEquals($this->user->id, $data['user_id']);
        $this->assertEquals($this->user->name, $data['user_name']);
        $this->assertNotNull($data['timestamp']);
    }

    public function test_log_inventory_create()
    {
        $this->service->logInventoryCreate([
            'product_name' => 'Ibuprofen',
            'batch_number' => 'BATCH-001',
            'quantity' => 50,
            'branch' => 'RHU 1',
        ], $this->user);

        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals('create', $data['action_type']);
        $this->assertEquals('inventory', $data['category']);
        $this->assertEquals('Ibuprofen', $data['details']['product_name']);
    }

    public function test_log_inventory_update()
    {
        $this->service->logInventoryUpdate([
            'product_name' => 'Amoxicillin',
            'quantity_before' => 30,
            'quantity_after' => 50,
        ], $this->user);

        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals('update', $data['action_type']);
        $this->assertEquals('inventory', $data['category']);
        $this->assertEquals(30, $data['details']['quantity_before']);
        $this->assertEquals(50, $data['details']['quantity_after']);
    }

    public function test_log_inventory_delete()
    {
        $this->service->logInventoryDelete([
            'product_name' => 'Expired Med',
            'reason' => 'Expired',
        ], $this->user);

        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals('delete', $data['action_type']);
        $this->assertEquals('inventory', $data['category']);
    }

    public function test_log_inventory_transfer()
    {
        $this->service->logInventoryTransfer([
            'product_name' => 'Cetirizine',
            'quantity' => 20,
            'from_branch' => 'RHU 1',
            'to_branch' => 'RHU 2',
        ], $this->user);

        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals('transfer', $data['action_type']);
        $this->assertEquals('inventory', $data['category']);
        $this->assertEquals('RHU 1', $data['details']['from_branch']);
        $this->assertEquals('RHU 2', $data['details']['to_branch']);
    }

    public function test_log_supplier_request()
    {
        $this->service->logSupplierRequest('create', [
            'request_id' => 5,
            'supplier' => 'PharmaCorp',
        ], $this->user);

        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals('create', $data['action_type']);
        $this->assertEquals('supplier_request', $data['category']);
    }

    public function test_log_stock_hold()
    {
        $this->service->logStockHold('create', [
            'hold_id' => 1,
            'type' => 'reservation',
            'quantity' => 10,
        ], $this->user);

        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals('create', $data['action_type']);
        $this->assertEquals('stock_hold', $data['category']);
    }

    public function test_log_pull_out()
    {
        $this->service->logPullOut([
            'product_name' => 'Losartan',
            'quantity' => 15,
            'reason' => 'Recalled',
        ], $this->user);

        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals('create', $data['action_type']);
        $this->assertEquals('pull_out', $data['category']);
    }

    public function test_log_adjustment()
    {
        $this->service->logAdjustment([
            'product_name' => 'Metformin',
            'quantity_before' => 100,
            'quantity_after' => 95,
            'reason' => 'Count correction',
        ], $this->user);

        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals('update', $data['action_type']);
        $this->assertEquals('adjustment', $data['category']);
    }

    public function test_log_approval()
    {
        $this->service->logApproval('supplier_request', [
            'request_id' => 3,
        ], $this->user);

        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals('approve', $data['action_type']);
        $this->assertEquals('supplier_request', $data['category']);
    }

    public function test_log_rejection()
    {
        $this->service->logRejection('stock_hold', [
            'hold_id' => 2,
            'reason' => 'Insufficient stock',
        ], $this->user);

        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals('reject', $data['action_type']);
        $this->assertEquals('stock_hold', $data['category']);
    }

    public function test_log_login()
    {
        $this->service->logLogin($this->user);

        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals('login', $data['action_type']);
        $this->assertEquals('auth', $data['category']);
    }

    public function test_log_logout()
    {
        $this->service->logLogout($this->user);

        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals('logout', $data['action_type']);
        $this->assertEquals('auth', $data['category']);
    }

    public function test_log_low_stock_alert()
    {
        $this->service->logLowStockAlert([
            'product_name' => 'Aspirin',
            'available' => 3,
            'threshold' => 10,
            'branch' => 'RHU 1',
        ], $this->user);

        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals('create', $data['action_type']);
        $this->assertEquals('low_stock_alert', $data['category']);
        $this->assertEquals(3, $data['details']['available']);
    }

    public function test_log_stores_ip_address()
    {
        $this->service->log('create', 'inventory', [], $this->user);

        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $this->assertArrayHasKey('ip_address', $data);
    }

    public function test_log_does_nothing_without_user()
    {
        $this->service->log('create', 'inventory', []);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_multiple_logs_create_separate_entries()
    {
        $this->service->logLogin($this->user);
        $this->service->logInventoryCreate(['product_name' => 'Test'], $this->user);
        $this->service->logLogout($this->user);

        $this->assertDatabaseCount('notifications', 3);
    }

    public function test_notification_has_uuid_id()
    {
        $this->service->log('create', 'inventory', [], $this->user);

        $notification = DB::table('notifications')->first();
        // UUID format: 8-4-4-4-12 hex chars
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $notification->id
        );
    }

    public function test_notification_read_at_is_null()
    {
        $this->service->log('create', 'inventory', [], $this->user);

        $notification = DB::table('notifications')->first();
        $this->assertNull($notification->read_at);
    }
}
