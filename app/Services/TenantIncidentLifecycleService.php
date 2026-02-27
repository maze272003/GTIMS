<?php

namespace App\Services;

use App\Models\TenantIncident;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;

class TenantIncidentLifecycleService
{
    public function detect(TenantContext $tenantContext, string $type, string $description, string $severity = 'medium'): TenantIncident
    {
        return TenantIncident::create([
            'province_id' => $tenantContext->provinceId,
            'barangay_id' => $tenantContext->barangayId,
            'incident_type' => $type,
            'severity' => $severity,
            'status' => 'open',
            'description' => $description,
            'reported_by' => auth()->id() ?? 0,
        ]);
    }

    public function investigate(TenantIncident $incident): TenantIncident
    {
        return tap($incident)->update(['status' => 'investigating'])->fresh();
    }

    public function contain(TenantIncident $incident, ?string $notes = null): TenantIncident
    {
        return tap($incident)->update([
            'status' => 'contained',
            'resolution' => $notes ?: $incident->resolution,
        ])->fresh();
    }

    public function resolve(TenantIncident $incident, ?string $resolution = null): TenantIncident
    {
        return tap($incident)->update([
            'status' => 'resolved',
            'resolution' => $resolution ?: $incident->resolution,
            'resolved_at' => now(),
        ])->fresh();
    }

    public function postMortem(TenantIncident $incident, string $summary): void
    {
        Log::channel('security')->info('Tenant incident post-mortem recorded.', [
            'incident_id' => $incident->id,
            'province_id' => $incident->province_id,
            'barangay_id' => $incident->barangay_id,
            'summary' => $summary,
        ]);
    }

    public function harden(TenantIncident $incident, array $actions = []): void
    {
        Log::channel('security')->info('Tenant incident hardening actions logged.', [
            'incident_id' => $incident->id,
            'actions' => $actions,
        ]);
    }
}

