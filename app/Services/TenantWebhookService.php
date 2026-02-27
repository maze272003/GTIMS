<?php

namespace App\Services;

use App\Models\TenantWebhook;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantWebhookService
{
    /**
     * @return \Illuminate\Support\Collection<int, TenantWebhook>
     */
    public function list(TenantContext $tenantContext)
    {
        return $this->queryForContext($tenantContext)->orderBy('event_type')->get();
    }

    public function configure(TenantContext $tenantContext, array $payload): TenantWebhook
    {
        $eventType = (string) ($payload['event_type'] ?? '');
        if (!in_array($eventType, (array) config('tenancy.webhooks.events', []), true)) {
            throw new \InvalidArgumentException('Webhook event is not in the configured event catalog.');
        }

        $endpoint = (string) ($payload['endpoint_url'] ?? '');
        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Webhook endpoint is not a valid URL.');
        }

        return TenantWebhook::updateOrCreate(
            [
                'province_id' => $tenantContext->provinceId,
                'barangay_id' => $tenantContext->barangayId,
                'event_type' => $eventType,
                'endpoint_url' => $endpoint,
            ],
            [
                'secret' => (string) ($payload['secret'] ?? Str::random(64)),
                'is_active' => (bool) ($payload['is_active'] ?? true),
            ]
        );
    }

    public function deactivate(TenantContext $tenantContext, int $webhookId): bool
    {
        return $this->queryForContext($tenantContext)
            ->where('id', $webhookId)
            ->update(['is_active' => false]) > 0;
    }

    public function deliver(TenantContext $tenantContext, string $eventType, array $payload = []): void
    {
        $hooks = $this->queryForContext($tenantContext)
            ->where('event_type', $eventType)
            ->where('is_active', true)
            ->get();

        foreach ($hooks as $hook) {
            $body = [
                'event' => $eventType,
                'tenant' => [
                    'province_id' => $tenantContext->provinceId,
                    'barangay_id' => $tenantContext->barangayId,
                    'province_slug' => $tenantContext->provinceSlug,
                    'barangay_slug' => $tenantContext->barangaySlug,
                    'scope_type' => $tenantContext->scopeType,
                ],
                'payload' => $payload,
                'sent_at' => now()->toIso8601String(),
            ];

            $raw = json_encode($body, JSON_UNESCAPED_SLASHES);
            $signature = hash_hmac('sha256', (string) $raw, (string) $hook->secret);

            try {
                $response = Http::timeout((int) config('tenancy.webhooks.delivery_timeout_seconds', 10))
                    ->retry((int) config('tenancy.webhooks.max_retries', 3), 200)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-GTIMS-Event' => $eventType,
                        'X-GTIMS-Signature' => "sha256={$signature}",
                    ])
                    ->post($hook->endpoint_url, $body);

                $hook->forceFill([
                    'last_triggered_at' => now(),
                    'failure_count' => $response->successful() ? 0 : ($hook->failure_count + 1),
                ])->save();
            } catch (\Throwable $e) {
                $hook->forceFill(['failure_count' => $hook->failure_count + 1])->save();

                Log::channel('security')->warning('Tenant webhook delivery failed.', [
                    'webhook_id' => $hook->id,
                    'event_type' => $eventType,
                    'endpoint' => $hook->endpoint_url,
                    'error' => $e->getMessage(),
                    'province_id' => $tenantContext->provinceId,
                    'barangay_id' => $tenantContext->barangayId,
                ]);
            }
        }
    }

    protected function queryForContext(TenantContext $tenantContext)
    {
        $query = TenantWebhook::query()->where('province_id', $tenantContext->provinceId);

        if ($tenantContext->isBarangay()) {
            $query->where(function ($scope) use ($tenantContext) {
                $scope->where('barangay_id', $tenantContext->barangayId)
                    ->orWhereNull('barangay_id');
            });
        } else {
            $query->whereNull('barangay_id');
        }

        return $query;
    }
}

