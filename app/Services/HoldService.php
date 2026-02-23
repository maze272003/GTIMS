<?php

namespace App\Services;

use App\Models\Hold;
use App\Models\HoldItem;
use App\Models\HoldStatusHistory;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\DB;

class HoldService
{
    /**
     * Create a new hold with items.
     */
    public function createHold(array $data, array $items, int $userId): Hold
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $hold = Hold::create(array_merge($data, [
                'created_by' => $userId,
                'status' => 'pending',
            ]));

            foreach ($items as $item) {
                HoldItem::create(array_merge($item, ['hold_id' => $hold->id]));
            }

            HoldStatusHistory::create([
                'hold_id' => $hold->id,
                'old_status' => null,
                'new_status' => 'pending',
                'changed_by' => $userId,
                'reason' => 'Hold created',
            ]);

            AuditEvent::create([
                'action' => 'hold.created',
                'entity_type' => 'hold',
                'entity_id' => $hold->id,
                'user_id' => $userId,
                'before' => null,
                'after' => $hold->toArray(),
                'reason' => $data['remarks'] ?? null,
            ]);

            return $hold;
        });
    }

    /**
     * Approve a hold.
     */
    public function approveHold(Hold $hold, int $approverId, ?string $reason = null): Hold
    {
        return DB::transaction(function () use ($hold, $approverId, $reason) {
            $before = $hold->toArray();
            $oldStatus = $hold->status;

            $hold->update([
                'status' => 'approved',
                'approved_by' => $approverId,
            ]);

            HoldStatusHistory::create([
                'hold_id' => $hold->id,
                'old_status' => $oldStatus,
                'new_status' => 'approved',
                'changed_by' => $approverId,
                'reason' => $reason ?? 'Hold approved',
            ]);

            AuditEvent::create([
                'action' => 'hold.approved',
                'entity_type' => 'hold',
                'entity_id' => $hold->id,
                'user_id' => $approverId,
                'before' => $before,
                'after' => $hold->fresh()->toArray(),
                'reason' => $reason,
            ]);

            return $hold->fresh();
        });
    }

    /**
     * Release a hold (manual release).
     */
    public function releaseHold(Hold $hold, int $userId, ?string $reason = null): Hold
    {
        return DB::transaction(function () use ($hold, $userId, $reason) {
            $before = $hold->toArray();
            $oldStatus = $hold->status;

            $hold->update(['status' => 'released']);

            HoldStatusHistory::create([
                'hold_id' => $hold->id,
                'old_status' => $oldStatus,
                'new_status' => 'released',
                'changed_by' => $userId,
                'reason' => $reason ?? 'Hold released manually',
            ]);

            AuditEvent::create([
                'action' => 'hold.released',
                'entity_type' => 'hold',
                'entity_id' => $hold->id,
                'user_id' => $userId,
                'before' => $before,
                'after' => $hold->fresh()->toArray(),
                'reason' => $reason,
            ]);

            return $hold->fresh();
        });
    }

    /**
     * Expire holds that have passed their expiry date. Called from scheduler.
     */
    public function expireHolds(): int
    {
        $expired = Hold::whereIn('status', ['pending', 'approved'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($expired as $hold) {
            DB::transaction(function () use ($hold) {
                $oldStatus = $hold->status;
                $hold->update(['status' => 'expired']);

                HoldStatusHistory::create([
                    'hold_id' => $hold->id,
                    'old_status' => $oldStatus,
                    'new_status' => 'expired',
                    'changed_by' => $hold->created_by,
                    'reason' => 'Auto-expired',
                ]);
            });
            $count++;
        }

        return $count;
    }
}
