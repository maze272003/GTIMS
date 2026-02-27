<?php

namespace App\Services;

use App\Mail\NewLoginNotification;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

class AuthSessionService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected TenantResolver $tenantResolver,
        protected TenantEmailSettingsService $tenantEmailSettingsService,
        protected TenantTwoFactorService $tenantTwoFactorService,
    ) {
    }

    public function getAuthenticatedRedirectUrl(?TenantContext $tenantContext = null, string $loginMode = 'legacy'): ?string
    {
        if (!Auth::check()) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        return $this->getRedirectUrl($user, $tenantContext, $loginMode) ?? route('admin.dashboard');
    }

    public function getRedirectUrl(User $user, ?TenantContext $tenantContext = null, string $loginMode = 'legacy'): ?string
    {
        $hasLegacyLevel = config('tenancy.rbac.allow_legacy_permissions', false) && !is_null($user->level);
        $hasScopedAssignments = $user->roleAssignments()->exists();

        if (!$hasLegacyLevel && !$hasScopedAssignments && !$user->isModerator()) {
            return null;
        }

        if ($loginMode === 'moderator') {
            if (!$user->isModerator()) {
                return null;
            }

            return route('moderator.dashboard');
        }

        if ($tenantContext && !$tenantContext->isPlatform()) {
            return $this->getTenantRedirectUrl($user, $tenantContext);
        }

        if ($user->isModerator() && Route::has('moderator.dashboard')) {
            return route('moderator.dashboard');
        }

        if ($user->hasPermission('dashboard.view', $tenantContext)) {
            return route('admin.dashboard');
        }

        if ($user->hasPermission('orders.view', $tenantContext)) {
            return route('admin.orders.index');
        }

        if ($user->hasPermission('holds.view', $tenantContext)) {
            return route('admin.holds.index');
        }

        if ($user->hasPermission('inventory.view', $tenantContext) || $user->hasPermission('inventories.view', $tenantContext)) {
            return route('admin.inventory');
        }

        if ($user->hasPermission('requests.view', $tenantContext)) {
            return route('admin.requests.index');
        }

        if ($user->hasPermission('suppliers.view', $tenantContext)) {
            return route('admin.suppliers.index');
        }

        if ($user->hasPermission('patients.view', $tenantContext)) {
            return route('admin.patientrecords');
        }

        if ($user->hasPermission('users.view', $tenantContext)) {
            return route('admin.manageaccount');
        }

        if ($user->hasPermission('settings.roles', $tenantContext)) {
            return route('admin.roles.index');
        }

        if ($user->hasPermission('settings.low_stock', $tenantContext)) {
            return route('admin.lowstock.index');
        }

        if ($user->hasPermission('notifications.manage', $tenantContext)) {
            return route('admin.notifications.index');
        }

        if ($user->hasPermission('historylog.view', $tenantContext)) {
            return route('admin.historylog');
        }

        if ($user->hasPermission('audit.view', $tenantContext)) {
            return route('admin.audit.index');
        }

        if ($user->hasPermission('profile.view', $tenantContext)) {
            return route('profile.edit');
        }

        return null;
    }

    /**
     * Generate a tenant-scoped redirect URL based on user permissions.
     */
    protected function getTenantRedirectUrl(User $user, TenantContext $tenantContext): ?string
    {
        if ($user->hasPermission('dashboard.view', $tenantContext)) {
            return tenant_route('tenant.dashboard', [], $tenantContext);
        }

        if ($user->hasPermission('orders.view', $tenantContext)) {
            return tenant_route('tenant.orders.index', [], $tenantContext);
        }

        if ($user->hasPermission('inventory.view', $tenantContext) || $user->hasPermission('inventories.view', $tenantContext)) {
            return tenant_route('tenant.inventory', [], $tenantContext);
        }

        if ($user->hasPermission('patients.view', $tenantContext)) {
            return tenant_route('tenant.patientrecords', [], $tenantContext);
        }

        if ($user->hasPermission('holds.view', $tenantContext)) {
            return tenant_route('tenant.holds.index', [], $tenantContext);
        }

        if ($user->hasPermission('requests.view', $tenantContext)) {
            return tenant_route('tenant.requests.index', [], $tenantContext);
        }

        if ($user->hasPermission('suppliers.view', $tenantContext)) {
            return tenant_route('tenant.suppliers.index', [], $tenantContext);
        }

        if ($user->hasPermission('notifications.manage', $tenantContext)) {
            return tenant_route('tenant.notifications.index', [], $tenantContext);
        }

        if ($user->hasPermission('audit.view', $tenantContext)) {
            return tenant_route('tenant.audit.index', [], $tenantContext);
        }

        return tenant_route('tenant.dashboard', [], $tenantContext);
    }

    public function canAccessApplication(
        User $user,
        ?TenantContext $tenantContext = null,
        string $loginMode = 'legacy',
    ): array
    {
        if (is_null($user->email_verified_at)) {
            return [
                'ok' => false,
                'error' => 'Your account is not verified yet. Please check your email or contact support.',
                'redirect' => '/login',
            ];
        }

        $hasLegacyLevel = !is_null($user->level);
        if (!config('tenancy.rbac.allow_legacy_permissions', false)) {
            $hasLegacyLevel = false;
        }
        $hasScopedAssignments = $user->roleAssignments()->exists();
        if (!$hasLegacyLevel && !$hasScopedAssignments && !$user->isModerator()) {
            return [
                'ok' => false,
                'error' => 'You are not authorized to access this application.',
                'redirect' => '/',
            ];
        }

        if ($loginMode === 'moderator' && !$user->isModerator()) {
            return [
                'ok' => false,
                'error' => 'Only moderator accounts can access this portal.',
                'redirect' => '/moderator/login',
            ];
        }

        if ($loginMode === 'tenant') {
            if (!$tenantContext) {
                return [
                    'ok' => false,
                    'error' => 'Tenant context is missing. Please use the tenant login link.',
                    'redirect' => '/login',
                ];
            }

            $membershipStatus = $this->tenantResolver->resolveMembershipStatus($user, $tenantContext);

            if ($membershipStatus === 'suspended') {
                return [
                    'ok' => false,
                    'error' => 'Your tenant membership is currently suspended.',
                    'redirect' => tenant_route('tenant.login', [], $tenantContext),
                ];
            }

            if ($membershipStatus === 'invited') {
                return [
                    'ok' => false,
                    'error' => 'Your invitation is pending acceptance. Please use your invitation link.',
                    'redirect' => tenant_route('tenant.login', [], $tenantContext),
                ];
            }

            if (!$this->tenantResolver->userHasMembership($user, $tenantContext)) {
                return [
                    'ok' => false,
                    'error' => 'You do not have access to this tenant.',
                    'redirect' => tenant_route('tenant.login', [], $tenantContext),
                ];
            }

            if ($this->tenantTwoFactorService->isRequiredForTenant($tenantContext) && !$user->two_factor_enabled) {
                return [
                    'ok' => false,
                    'error' => 'Two-factor authentication is required for this tenant. Please enable 2FA on your account first.',
                    'redirect' => tenant_route('tenant.login', [], $tenantContext),
                ];
            }
        }

        $redirectUrl = $this->getRedirectUrl($user, $tenantContext, $loginMode);
        if (!$redirectUrl) {
            return [
                'ok' => false,
                'error' => 'Your user role does not have access.',
                'redirect' => '/',
            ];
        }

        return [
            'ok' => true,
            'redirect_url' => $redirectUrl,
        ];
    }

    public function processSuccessfulLogin(User $user, ?string $currentIp, ?TenantContext $tenantContext = null): void
    {
        if ($currentIp && $user->last_login_ip !== $currentIp) {
            try {
                $tenantLabel = null;
                if ($tenantContext) {
                    $this->tenantEmailSettingsService->apply($tenantContext);
                    $tenantLabel = $tenantContext->isBarangay()
                        ? "{$tenantContext->provinceSlug}/{$tenantContext->barangaySlug}"
                        : ($tenantContext->provinceSlug ?? $tenantContext->scopeType);
                }

                Mail::to($user->email)->send(new NewLoginNotification($currentIp, $tenantLabel));
            } catch (\Throwable $e) {
                Log::error('Failed to send new login notification: ' . $e->getMessage());
            }
        }

        $this->userRepository->updateLoginMetadata($user->id, $currentIp);
    }
}
