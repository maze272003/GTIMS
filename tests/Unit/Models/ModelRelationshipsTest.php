<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Dispensedmedication;
use App\Models\HistoryLog;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserLevel;
use App\Models\Branch;
use App\Models\Barangay;
use App\Models\Patientrecords;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $branch;
    protected $barangay;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();
        $level = UserLevel::create(['name' => 'admin']);
        $this->branch = Branch::create(['name' => 'RHU 1']);
        $this->barangay = Barangay::create(['barangay_name' => 'Test Barangay']);
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->product = Product::create([
            'brand_name' => 'Test Brand',
            'generic_name' => 'Test Generic',
            'form' => 'Tablet',
            'strength' => '500mg',
        ]);
    }

    public function test_dispensedmedication_belongs_to_barangay()
    {
        $patientRecord = Patientrecords::create([
            'patient_name' => 'Test Patient',
            'barangay_id' => $this->barangay->id,
            'purok' => '1',
            'category' => 'Adult',
            'date_dispensed' => now(),
            'branch_id' => $this->branch->id,
        ]);

        $medication = Dispensedmedication::create([
            'patientrecord_id' => $patientRecord->id,
            'batch_number' => 'BATCH-001',
            'generic_name' => 'Test Generic',
            'brand_name' => 'Test Brand',
            'strength' => '500mg',
            'form' => 'Tablet',
            'quantity' => 10,
            'barangay_id' => $this->barangay->id,
        ]);

        $this->assertNotNull($medication->barangay);
        $this->assertEquals($this->barangay->id, $medication->barangay->id);
        $this->assertEquals('Test Barangay', $medication->barangay->barangay_name);
    }

    public function test_dispensedmedication_belongs_to_patientrecord()
    {
        $patientRecord = Patientrecords::create([
            'patient_name' => 'Test Patient',
            'barangay_id' => $this->barangay->id,
            'purok' => '1',
            'category' => 'Adult',
            'date_dispensed' => now(),
            'branch_id' => $this->branch->id,
        ]);

        $medication = Dispensedmedication::create([
            'patientrecord_id' => $patientRecord->id,
            'batch_number' => 'BATCH-001',
            'generic_name' => 'Test Generic',
            'brand_name' => 'Test Brand',
            'strength' => '500mg',
            'form' => 'Tablet',
            'quantity' => 10,
            'barangay_id' => $this->barangay->id,
        ]);

        $this->assertNotNull($medication->patientrecord);
        $this->assertEquals($patientRecord->id, $medication->patientrecord->id);
    }

    public function test_history_log_belongs_to_user()
    {
        $log = HistoryLog::create([
            'action' => 'test_action',
            'description' => 'Test description',
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
        ]);

        $this->assertNotNull($log->user);
        $this->assertEquals($this->user->id, $log->user->id);
        $this->assertEquals($this->user->name, $log->user->name);
    }

    public function test_order_item_belongs_to_order()
    {
        $order = Order::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'status' => 'pending_admin',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity_requested' => 100,
        ]);

        $this->assertNotNull($orderItem->order);
        $this->assertEquals($order->id, $orderItem->order->id);
    }

    public function test_order_item_belongs_to_product()
    {
        $order = Order::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'status' => 'pending_admin',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity_requested' => 100,
        ]);

        $this->assertNotNull($orderItem->product);
        $this->assertEquals($this->product->id, $orderItem->product->id);
    }
}
