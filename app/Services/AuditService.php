<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\Log;

class AuditService
{
    /**
     * Record an audit event.
     */
    public function record(string $action, string $entityType, int $entityId, int $userId, ?array $before = null, ?array $after = null, ?string $reason = null, ?array $metadata = null): AuditEvent
    {
        $event = AuditEvent::create([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);

        Log::channel('daily')->info("Audit: {$action}", [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId,
        ]);

        return $event;
    }
}
