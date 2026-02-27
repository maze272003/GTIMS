<?php

namespace App\Services;

use App\Models\IncomingRequest;
use App\Models\RequestItem;
use App\Models\RequestStatusHistory;
use App\Models\AuditEvent;
use App\Models\IdempotencyKey;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

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
    public function createRequest(array $data, array $items, int $userId, ?TenantContext $tenantContext = null): IncomingRequest
    {
        $tenantContext = $tenantContext ?: (app()->bound(TenantContext::class) ? app(TenantContext::class) : null);

        return DB::transaction(function () use ($data, $items, $userId, $tenantContext) {
            if ($tenantContext && !$tenantContext->isPlatform()) {
                $data['province_id'] = $tenantContext->provinceId;
                if ($tenantContext->isBarangay()) {
                    $data['barangay_id'] = $tenantContext->barangayId;
                }
            }

            $request = IncomingRequest::create(array_merge($data, [
                'requester_id' => $userId,
                'status' => 'draft',
            ]));

            foreach ($items as $item) {
                if ($tenantContext && !$tenantContext->isPlatform()) {
                    $item['province_id'] = $tenantContext->provinceId;
                    if ($tenantContext->isBarangay()) {
                        $item['barangay_id'] = $tenantContext->barangayId;
                    }
                }

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

            return $request;
        });
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

            return $request->fresh();
        });
    }

    /**
     * Fulfill a request (allocate batches using FEFO).
     */
    public function fulfillRequest(IncomingRequest $request, int $userId, ?string $idempotencyKey = null): IncomingRequest
    {
        $tenantContext = app()->bound(TenantContext::class) ? app(TenantContext::class) : null;

        if ($idempotencyKey) {
            $existing = IdempotencyKey::where('key', $idempotencyKey)
                ->where('user_id', $userId)
                ->first();
            if ($existing) {
                return $request->fresh();
            }
        }

        return DB::transaction(function () use ($request, $userId, $idempotencyKey, $tenantContext) {
            $request = $this->transitionStatus($request, 'fulfilling', $userId, 'Starting fulfillment');

            $allFulfilled = true;
            foreach ($request->items as $item) {
                    $allocations = $this->availabilityService->allocateFEFO(
                        $item->product_id,
                        $item->quantity_requested - $item->quantity_fulfilled,
                        $request->branch_id,
                        $tenantContext
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

            return $request->fresh();
        });
    }

    /**
     * Check availability for all items in a request.
     */
    public function checkAvailability(IncomingRequest $request): array
    {
        $tenantContext = app()->bound(TenantContext::class) ? app(TenantContext::class) : null;
        $result = [];
        foreach ($request->items as $item) {
            $available = $this->availabilityService->getAvailable(
                $item->product_id,
                $request->branch_id,
                $tenantContext
            );
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
