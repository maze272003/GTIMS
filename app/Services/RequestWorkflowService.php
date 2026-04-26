<?php

namespace App\Services;

use App\Models\IncomingRequest;
use App\Models\RequestItem;
use App\Models\RequestStatusHistory;
use App\Models\AuditEvent;
use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequestWorkflowService
{
    protected AvailabilityService $availabilityService;

    public function __construct(AvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    /**
     * Create a new request (draft).
     */
    public function createRequest(array $data, array $items, int $userId): IncomingRequest
    {
        try {
            return DB::transaction(function () use ($data, $items, $userId) {
                $request = IncomingRequest::create(array_merge($data, [
                    'requester_id' => $userId,
                    'status' => 'draft',
                ]));

                foreach ($items as $item) {
                    RequestItem::create(array_merge($item, [
                        'incoming_request_id' => $request->id,
                    ]));
                }

                RequestStatusHistory::create([
                    'incoming_request_id' => $request->id,
                    'old_status' => null,
                    'new_status' => 'draft',
                    'changed_by' => $userId,
                    'reason' => 'Request created',
                ]);

                Log::info('Request created', ['request_id' => $request->id, 'user_id' => $userId]);

                return $request;
            });
        } catch (\Exception $e) {
            Log::error('Failed to create request', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Transition request to a new status with validation.
     */
    public function transitionStatus(IncomingRequest $request, string $newStatus, int $userId, ?string $reason = null, ?string $idempotencyKey = null): IncomingRequest
    {
        // Idempotency check
        if ($idempotencyKey) {
            $existing = IdempotencyKey::where('key', $idempotencyKey)
                ->where('user_id', $userId)
                ->first();
            if ($existing) {
                return $request->fresh();
            }
        }

        if (!$request->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition from '{$request->status}' to '{$newStatus}'"
            );
        }

        try {
            return DB::transaction(function () use ($request, $newStatus, $userId, $reason, $idempotencyKey) {
                $before = $request->toArray();
                $oldStatus = $request->status;

                $request->update(['status' => $newStatus]);

                RequestStatusHistory::create([
                    'incoming_request_id' => $request->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'changed_by' => $userId,
                    'reason' => $reason,
                ]);

                AuditEvent::create([
                    'action' => "request.{$newStatus}",
                    'entity_type' => 'incoming_request',
                    'entity_id' => $request->id,
                    'user_id' => $userId,
                    'before' => $before,
                    'after' => $request->fresh()->toArray(),
                    'reason' => $reason,
                ]);

                if ($idempotencyKey) {
                    IdempotencyKey::create([
                        'key' => $idempotencyKey,
                        'user_id' => $userId,
                        'action' => "request.{$newStatus}",
                        'response' => ['request_id' => $request->id, 'status' => $newStatus],
                    ]);
                }

                Log::info('Request status transitioned', [
                    'request_id' => $request->id,
                    'from' => $oldStatus,
                    'to' => $newStatus,
                    'user_id' => $userId,
                ]);

                return $request->fresh();
            });
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to transition request status', [
                'request_id' => $request->id,
                'new_status' => $newStatus,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Fulfill a request (allocate batches using FEFO).
     */
    public function fulfillRequest(IncomingRequest $request, int $userId, ?string $idempotencyKey = null): IncomingRequest
    {
        if ($idempotencyKey) {
            $existing = IdempotencyKey::where('key', $idempotencyKey)
                ->where('user_id', $userId)
                ->first();
            if ($existing) {
                return $request->fresh();
            }
        }

        try {
            return DB::transaction(function () use ($request, $userId, $idempotencyKey) {
                $request = $this->transitionStatus($request, 'fulfilling', $userId, 'Starting fulfillment');

                $allFulfilled = true;
                foreach ($request->items as $item) {
                    $allocations = $this->availabilityService->allocateFEFO(
                        $item->product_id,
                        $item->quantity_requested - $item->quantity_fulfilled,
                        $request->branch_id
                    );

                    $totalAllocated = array_sum(array_column($allocations, 'quantity'));

                    if ($totalAllocated > 0) {
                        $this->availabilityService->deductStock(
                            $allocations,
                            $item->product_id,
                            $userId,
                            "Request #{$request->id} fulfillment"
                        );

                        $item->update([
                            'quantity_fulfilled' => $item->quantity_fulfilled + $totalAllocated,
                        ]);
                    }

                    if (!$item->fresh()->isFullyFulfilled()) {
                        $allFulfilled = false;
                    }
                }

                if ($allFulfilled) {
                    $request = $this->transitionStatus($request, 'fulfilled', $userId, 'All items fulfilled');
                }

                if ($idempotencyKey) {
                    IdempotencyKey::create([
                        'key' => $idempotencyKey,
                        'user_id' => $userId,
                        'action' => 'request.fulfill',
                        'response' => ['request_id' => $request->id],
                    ]);
                }

                Log::info('Request fulfillment completed', [
                    'request_id' => $request->id,
                    'fully_fulfilled' => $allFulfilled,
                    'user_id' => $userId,
                ]);

                return $request->fresh();
            });
        } catch (\Exception $e) {
            Log::error('Failed to fulfill request', [
                'request_id' => $request->id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check availability for all items in a request.
     */
    public function checkAvailability(IncomingRequest $request): array
    {
        $result = [];
        foreach ($request->items as $item) {
            $available = $this->availabilityService->getAvailable($item->product_id, $request->branch_id);
            $result[] = [
                'item_id' => $item->id,
                'product_id' => $item->product_id,
                'requested' => $item->quantity_requested,
                'available' => $available,
                'sufficient' => $available >= $item->quantity_requested,
            ];
        }
        return $result;
    }
}
