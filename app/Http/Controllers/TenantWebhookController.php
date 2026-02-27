<?php

namespace App\Http\Controllers;

use App\Services\TenantWebhookService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantWebhookController extends Controller
{
    public function __construct(
        protected TenantWebhookService $webhookService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tenantContext = $this->tenantContext($request);
        return response()->json([
            'events' => config('tenancy.webhooks.events', []),
            'webhooks' => $this->webhookService->list($tenantContext),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantContext = $this->tenantContext($request);
        $payload = $request->validate([
            'event_type' => ['required', 'string'],
            'endpoint_url' => ['required', 'url'],
            'secret' => ['nullable', 'string', 'min:16'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $webhook = $this->webhookService->configure($tenantContext, $payload);

        return response()->json(['webhook' => $webhook], 201);
    }

    public function destroy(Request $request, int $webhook): JsonResponse
    {
        $tenantContext = $this->tenantContext($request);
        $ok = $this->webhookService->deactivate($tenantContext, $webhook);

        return response()->json(['ok' => $ok]);
    }

    public function test(Request $request, int $webhook): JsonResponse
    {
        $tenantContext = $this->tenantContext($request);
        $request->validate(['event_type' => ['required', 'string']]);

        $this->webhookService->deliver($tenantContext, (string) $request->input('event_type'), [
            'test' => true,
            'webhook_id' => $webhook,
        ]);

        return response()->json(['ok' => true, 'message' => 'Webhook test dispatch attempted.']);
    }

    protected function tenantContext(Request $request): TenantContext
    {
        /** @var TenantContext|null $tenantContext */
        $tenantContext = $request->attributes->get('tenantContext');
        if (!$tenantContext) {
            abort(422, 'Tenant context is required.');
        }

        return $tenantContext;
    }
}

