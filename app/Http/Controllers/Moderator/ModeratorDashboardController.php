<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\Province;
use App\Models\RoleAssignment;
use App\Models\TenantHealth;
use App\Models\TenantIncident;
use App\Models\TenantMembership;
use App\Models\TenantOnboarding;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\TenantIncidentLifecycleService;
use App\Services\TenantOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ModeratorDashboardController extends Controller
{
    public function __construct(
        protected TenantOnboardingService $onboardingService,
        protected TenantIncidentLifecycleService $incidentLifecycleService,
    ) {
    }

    public function dashboard(): View
    {
        $latestChecks = TenantHealth::query()
            ->select('status')
            ->latest('checked_at')
            ->limit(200)
            ->get();

        $healthSummary = [
            'healthy' => $latestChecks->where('status', 'healthy')->count(),
            'degraded' => $latestChecks->where('status', 'degraded')->count(),
            'critical' => $latestChecks->where('status', 'critical')->count(),
        ];

        $widgets = [
            'provinces' => Province::where('is_active', true)->count(),
            'barangays' => Barangay::where('is_active', true)->count(),
            'active_users' => TenantMembership::where('status', 'active')->distinct('user_id')->count('user_id'),
            'open_incidents' => TenantIncident::where('status', 'open')->count(),
            'onboarding_pending' => TenantOnboarding::whereIn('status', ['pending', 'in_progress'])->count(),
        ];

        $failedJobsByTenant = $this->failedJobsByTenant();

        return view('moderator.dashboard', compact('widgets', 'healthSummary', 'failedJobsByTenant'));
    }

    public function provinces(): View
    {
        $provinces = Province::query()
            ->withCount(['barangays'])
            ->with(['barangays' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->paginate(20);

        return view('moderator.provinces', compact('provinces'));
    }

    public function storeProvince(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
        ]);

        $province = Province::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'code' => $validated['code'] ?? null,
            'is_active' => true,
        ]);

        $this->onboardingService->getOrCreateForProvince($province, (int) $request->user()->id);

        return back()->with('success', 'Province created and onboarding initialized.');
    }

    public function barangays(Request $request): View
    {
        $provinceId = $request->integer('province_id');
        $barangays = Barangay::query()
            ->with('province')
            ->when($provinceId, fn ($q) => $q->where('province_id', $provinceId))
            ->orderBy('barangay_name')
            ->paginate(20);

        $provinces = Province::query()->where('is_active', true)->orderBy('name')->get();

        return view('moderator.barangays', compact('barangays', 'provinces', 'provinceId'));
    }

    public function storeBarangay(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'province_id' => ['required', 'exists:provinces,id'],
            'barangay_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'external_code' => ['nullable', 'string', 'max:100'],
        ]);

        Barangay::create([
            'province_id' => (int) $validated['province_id'],
            'barangay_name' => $validated['barangay_name'],
            'slug' => $validated['slug'],
            'external_code' => $validated['external_code'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Barangay created.');
    }

    public function memberships(): View
    {
        $memberships = TenantMembership::query()
            ->with('user')
            ->latest('id')
            ->paginate(20);
        $users = User::query()->orderBy('name')->limit(200)->get(['id', 'name', 'email']);
        $roles = TenantRole::query()->orderBy('name')->get(['id', 'name', 'scope_type']);
        $provinces = Province::query()->with('barangays')->orderBy('name')->get();

        return view('moderator.memberships', compact('memberships', 'users', 'roles', 'provinces'));
    }

    public function storeMembership(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'scope_type' => ['required', 'in:platform,province,barangay'],
            'scope_id' => ['nullable', 'integer'],
            'role_id' => ['required', 'exists:tenant_roles,id'],
        ]);

        $scopeId = $validated['scope_type'] === 'platform' ? null : (int) $validated['scope_id'];

        TenantMembership::updateOrCreate(
            [
                'user_id' => (int) $validated['user_id'],
                'scope_type' => $validated['scope_type'],
                'scope_id' => $scopeId,
            ],
            ['status' => 'active', 'is_primary' => true]
        );

        RoleAssignment::updateOrCreate(
            [
                'user_id' => (int) $validated['user_id'],
                'role_id' => (int) $validated['role_id'],
                'scope_type' => $validated['scope_type'],
                'scope_id' => $scopeId,
            ],
            []
        );

        return back()->with('success', 'Membership and role assignment saved.');
    }

    public function onboarding(): View
    {
        $records = TenantOnboarding::query()
            ->with('province')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('moderator.onboarding', compact('records'));
    }

    public function advanceOnboarding(Request $request, TenantOnboarding $onboarding): RedirectResponse
    {
        $validated = $request->validate([
            'step' => ['required', 'string'],
        ]);

        $this->onboardingService->completeStep($onboarding, $validated['step']);

        return back()->with('success', 'Onboarding step updated.');
    }

    public function incidents(): View
    {
        $incidents = TenantIncident::query()
            ->with('province')
            ->latest('created_at')
            ->paginate(20);

        return view('moderator.incidents', compact('incidents'));
    }

    public function updateIncident(Request $request, TenantIncident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:open,investigating,contained,resolved,closed'],
            'resolution' => ['nullable', 'string'],
        ]);

        match ($validated['status']) {
            'investigating' => $this->incidentLifecycleService->investigate($incident),
            'contained' => $this->incidentLifecycleService->contain($incident, $validated['resolution'] ?? null),
            'resolved', 'closed' => $this->incidentLifecycleService->resolve($incident, $validated['resolution'] ?? null),
            default => $incident->update([
                'status' => $validated['status'],
                'resolution' => $validated['resolution'] ?? $incident->resolution,
            ]),
        };

        if (in_array($validated['status'], ['resolved', 'closed'], true)) {
            $this->incidentLifecycleService->postMortem($incident, (string) ($validated['resolution'] ?? 'Resolved by moderator'));
            $this->incidentLifecycleService->harden($incident, ['review_policies', 'run_leakage_tests']);
        }

        return back()->with('success', 'Incident status updated.');
    }

    /**
     * @return array<int, array{scope:string, total:int}>
     */
    protected function failedJobsByTenant(): array
    {
        if (!Schema::hasTable('failed_jobs')) {
            return [];
        }

        $grouped = [];
        $rows = DB::table('failed_jobs')->latest('id')->limit(500)->get(['payload']);
        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload, true);
            $provinceId = data_get($payload, 'data.command.tenantContext.provinceId')
                ?? data_get($payload, 'data.command.province_id');
            $barangayId = data_get($payload, 'data.command.tenantContext.barangayId')
                ?? data_get($payload, 'data.command.barangay_id');
            $scope = $provinceId ? "province:{$provinceId}" : 'platform';
            if ($barangayId) {
                $scope .= "/barangay:{$barangayId}";
            }

            $grouped[$scope] = ($grouped[$scope] ?? 0) + 1;
        }

        return collect($grouped)
            ->map(fn ($total, $scope) => ['scope' => $scope, 'total' => $total])
            ->sortByDesc('total')
            ->values()
            ->all();
    }
}
