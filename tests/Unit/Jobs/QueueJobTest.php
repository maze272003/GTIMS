<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessInventoryAddStockJob;
use App\Jobs\ProcessInventoryTransferJob;
use App\Jobs\ProcessOrderStatusJob;
use Tests\TestCase;

class QueueJobTest extends TestCase
{
    public function test_process_inventory_add_stock_job_has_correct_properties(): void
    {
        $job = new ProcessInventoryAddStockJob(1, 50, 2);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(10, $job->backoff);
        $this->assertEquals(60, $job->timeout);
        $this->assertEquals(1, $job->inventoryId);
        $this->assertEquals(50, $job->quantity);
        $this->assertEquals(2, $job->userId);
        $this->assertEquals(['inventory', 'inventory:1'], $job->tags());
    }

    public function test_process_inventory_transfer_job_has_correct_properties(): void
    {
        $job = new ProcessInventoryTransferJob(1, 2, 30, 5);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(10, $job->backoff);
        $this->assertEquals(60, $job->timeout);
        $this->assertEquals(1, $job->sourceInventoryId);
        $this->assertEquals(2, $job->destinationBranchId);
        $this->assertEquals(30, $job->quantity);
        $this->assertEquals(5, $job->userId);
        $this->assertEquals(['inventory', 'transfer:1', 'branch:2'], $job->tags());
    }

    public function test_process_order_status_job_has_correct_properties(): void
    {
        $job = new ProcessOrderStatusJob(10, 'approved', 3);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(10, $job->backoff);
        $this->assertEquals(60, $job->timeout);
        $this->assertEquals(10, $job->orderId);
        $this->assertEquals('approved', $job->status);
        $this->assertEquals(3, $job->userId);
        $this->assertEquals(['order', 'order:10'], $job->tags());
    }

    public function test_inventory_add_stock_job_queue_name(): void
    {
        $job = new ProcessInventoryAddStockJob(1, 10);
        $this->assertEquals('inventory', $job->queue);
    }

    public function test_inventory_transfer_job_queue_name(): void
    {
        $job = new ProcessInventoryTransferJob(1, 2, 10);
        $this->assertEquals('inventory', $job->queue);
    }

    public function test_order_status_job_queue_name(): void
    {
        $job = new ProcessOrderStatusJob(1, 'pending');
        $this->assertEquals('orders', $job->queue);
    }
}