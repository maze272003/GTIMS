<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Tenancy\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TenantSwitchController extends Controller
{
    public function __construct(
        protected TenantResolver $tenantResolver,
    ) {
    }

    public function switch(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user || !$user->isModerator()) {
            abort(403, 'Only moderators can switch tenant context.');
        }

        $validated = $request->validate([
            'province_slug' => ['required', 'string'],
            'barangay_slug' => ['nullable', 'string'],
        ]);

        $tenantContext = !empty($validated['barangay_slug'])
            ? $this->tenantResolver->fromSlugs($validated['province_slug'], $validated['barangay_slug'])
            : $this->tenantResolver->fromProvinceSlug($validated['province_slug']);

        if (!$tenantContext) {
            return back()->with('error', 'Unable to resolve tenant from selected slug(s).');
        }

        $request->session()->regenerate();
        foreach ($tenantContext->toSessionData() as $key => $value) {
            $request->session()->put($key, $value);
        }
        $request->session()->put('tenant.switched_from', 'platform');
        $request->session()->put('tenant.switched_by', $user->id);

        AuditEvent::create([
            'province_id' => $tenantContext->provinceId,
            'barangay_id' => $tenantContext->barangayId,
            'action' => 'moderator.tenant_switch',
            'entity_type' => 'tenant_context',
            'entity_id' => $tenantContext->barangayId ?? $tenantContext->provinceId ?? 0,
            'user_id' => $user->id,
            'metadata' => [
                'scope_type' => $tenantContext->scopeType,
                'province_slug' => $tenantContext->provinceSlug,
                'barangay_slug' => $tenantContext->barangaySlug,
            ],
        ]);

        Log::channel('security')->info('Moderator switched tenant context.', [
            'user_id' => $user->id,
            'scope_type' => $tenantContext->scopeType,
            'province_id' => $tenantContext->provinceId,
            'barangay_id' => $tenantContext->barangayId,
        ]);

        return redirect()->to(tenant_route('tenant.dashboard', [], $tenantContext));
    }

    public function switchPlatform(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user || !$user->isModerator()) {
            abort(403, 'Only moderators can switch tenant context.');
        }

        foreach (config('tenancy.session_keys', []) as $sessionKey) {
            $request->session()->forget($sessionKey);
        }
        $request->session()->forget('tenant.switched_from');
        $request->session()->forget('tenant.switched_by');
        $request->session()->regenerate();

        AuditEvent::create([
            'province_id' => null,
            'barangay_id' => null,
            'action' => 'moderator.platform_switch',
            'entity_type' => 'tenant_context',
            'entity_id' => 0,
            'user_id' => $user->id,
            'metadata' => ['switched_to' => 'platform'],
        ]);

        return redirect()->route('moderator.dashboard');
    }
}

