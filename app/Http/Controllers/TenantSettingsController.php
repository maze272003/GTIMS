<?php

namespace App\Http\Controllers;

use App\Services\TenantEmailSettingsService;
use App\Services\TenantFeatureService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantSettingsController extends Controller
{
    public function __construct(
        protected TenantEmailSettingsService $emailSettingsService,
        protected TenantFeatureService $featureService,
    ) {
    }

    public function index(Request $request): View
    {
        $tenantContext = $this->tenantContext($request);
        $email = $this->emailSettingsService->get($tenantContext);
        $features = collect(config('tenancy.features.defaults', []))
            ->mapWithKeys(fn ($enabled, $key) => [$key => $this->featureService->isEnabled($tenantContext, $key)])
            ->all();

        return view('tenant.settings', compact('tenantContext', 'email', 'features'));
    }

    public function update(Request $request): RedirectResponse
    {
        $tenantContext = $this->tenantContext($request);
        $validated = $request->validate([
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'features' => ['nullable', 'array'],
            'features.*' => ['boolean'],
        ]);

        $this->emailSettingsService->update($tenantContext, [
            'from_name' => $validated['from_name'] ?? null,
            'from_address' => $validated['from_address'] ?? null,
        ]);

        foreach ((array) ($validated['features'] ?? []) as $feature => $enabled) {
            if ((bool) $enabled) {
                $this->featureService->enable($tenantContext, (string) $feature);
            } else {
                $this->featureService->disable($tenantContext, (string) $feature);
            }
        }

        return back()->with('success', 'Tenant settings updated.');
    }

    protected function tenantContext(Request $request): TenantContext
    {
        /** @var TenantContext|null $tenantContext */
        $tenantContext = $request->attributes->get('tenantContext');
        if (!$tenantContext) {
            abort(422, 'Tenant context is missing.');
        }

        return $tenantContext;
    }
}

