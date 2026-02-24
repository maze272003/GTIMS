<?php

namespace App\Services;

use App\Mail\NewLoginNotification;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthSessionService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository
    ) {
    }

    public function getAuthenticatedRedirectUrl(?TenantContext $tenantContext = null): ?string
    {
        if (!Auth::check()) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        return $this->getRedirectUrl($user, $tenantContext) ?? route('admin.dashboard');
    }

    public function getRedirectUrl(User $user, ?TenantContext $tenantContext = null): ?string
    {
        if (is_null($user->level)) {
            return null;
        }

        // If tenant context is available, generate tenant-scoped routes
        if ($tenantContext && !$tenantContext->isPlatform()) {
            return $this->getTenantRedirectUrl($user, $tenantContext);
        }

        // Moderator / legacy admin redirect
        if ($user->hasPermission('dashboard.view')) {
            return route('admin.dashboard');
        }

        if ($user->hasPermission('orders.view')) {
            return route('admin.orders.index');
        }
        else if ($user->hasPermission('holds.view')) {
            return route('admin.holds.index');
        }
        else if ($user->hasPermission('inventories.view')) {
            return route('admin.inventories.index');
        }
        else if ($user->hasPermission('requests.view')) {
            return route('admin.requests.index');
        }
        else if ($user->hasPermission('suppliers.view')) {
            return route('admin.suppliers.index');
        }
        else if ($user->hasPermission('reports.view')) {
            return route('admin.reports.index');
        }
        else if ($user->hasPermission('users.view')) {
            return route('admin.users.index');
        }
        else if ($user->hasPermission('settings.view')) {
            return route('admin.settings.index');
        }
        else if ($user->hasPermission('notifications.view')) {
            return route('admin.notifications.index');
        }
        else if ($user->hasPermission('logs.view')) {
            return route('admin.logs.index');
        }
        else if ($user->hasPermission('audit_trails.view')) {
            return route('admin.audit-trails.index');
        }
        else if ($user->hasPermission('profile.view')) {
            return route('profile.show');
        }

        return null;
    }

    /**
     * Generate a tenant-scoped redirect URL based on user permissions.
     */
    protected function getTenantRedirectUrl(User $user, TenantContext $tenantContext): ?string
    {
        $slugParams = [
            'provinceSlug' => $tenantContext->provinceSlug,
            'barangaySlug' => $tenantContext->barangaySlug,
        ];

        if ($user->hasPermission('dashboard.view')) {
            return route('tenant.dashboard', $slugParams);
        }

        if ($user->hasPermission('orders.view')) {
            return route('tenant.orders.index', $slugParams);
        }

        if ($user->hasPermission('inventories.view')) {
            return route('tenant.inventory', $slugParams);
        }

        if ($user->hasPermission('requests.view')) {
            return route('tenant.requests.index', $slugParams);
        }

        if ($user->hasPermission('suppliers.view')) {
            return route('tenant.suppliers.index', $slugParams);
        }

        return null;
    }

    public function canAccessApplication(User $user, ?TenantContext $tenantContext = null): array
    {
        if (is_null($user->email_verified_at)) {
            return [
                'ok' => false,
                'error' => 'Your account is not verified yet. Please check your email or contact support.',
                'redirect' => '/login',
            ];
        }

        if (is_null($user->level)) {
            return [
                'ok' => false,
                'error' => 'You are not authorized to access this application.',
                'redirect' => '/',
            ];
        }

        $redirectUrl = $this->getRedirectUrl($user, $tenantContext);
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

    public function processSuccessfulLogin(User $user, ?string $currentIp): void
    {
        if ($currentIp && $user->last_login_ip !== $currentIp) {
            try {
                Mail::to($user->email)->send(new NewLoginNotification($currentIp));
            } catch (\Throwable $e) {
                Log::error('Failed to send new login notification: ' . $e->getMessage());
            }
        }

        $this->userRepository->updateLoginMetadata($user->id, $currentIp);
    }
}
