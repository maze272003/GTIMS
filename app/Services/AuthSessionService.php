<?php

namespace App\Services;

use App\Mail\NewLoginNotification;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

class AuthSessionService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository
    ) {
    }

    public function getAuthenticatedRedirectUrl(): ?string
    {
        if (!Auth::check()) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        return $this->getRedirectUrl($user);
    }

    public function getRedirectUrl(User $user): ?string
    {
        return $this->getRedirectDestination($user)['url'] ?? null;
    }

    public function getRedirectDestination(?User $user): ?array
    {
        if (!$user || is_null($user->level)) {
            return null;
        }

        foreach ($this->redirectPriorityMap() as $destination) {
            if (!Route::has($destination['route'])) {
                continue;
            }

            foreach ($destination['permissions'] as $permission) {
                if ($user->hasPermission($permission)) {
                    return [
                        'label' => $destination['label'],
                        'route' => $destination['route'],
                        'url' => route($destination['route']),
                        'permissions' => $destination['permissions'],
                    ];
                }
            }
        }

        return null;
    }

    public function getForbiddenMessage(?User $user, ?string $subject = null): string
    {
        $baseMessage = 'This page or action cannot be accessed with your account. Please contact the superadmin for assistance.';

        if (!$user) {
            return $baseMessage;
        }

        $destination = $this->getRedirectDestination($user);

        $message = $subject
            ? 'You do not have permission to '.$subject.'. '.$baseMessage
            : $baseMessage;

        if (!$destination) {
            return $message;
        }

        return $message.' You can continue to '.$destination['label'].' instead.';
    }

    public function canAccessApplication(User $user): array
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

        $redirectUrl = $this->getRedirectUrl($user);
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

    protected function redirectPriorityMap(): array
    {
        return [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'permissions' => ['dashboard.view']],
            ['label' => 'Orders', 'route' => 'admin.orders.index', 'permissions' => ['orders.view']],
            ['label' => 'Inventory', 'route' => 'admin.inventory', 'permissions' => ['inventory.view']],
            ['label' => 'Product Movement', 'route' => 'admin.movements', 'permissions' => ['movements.view']],
            ['label' => 'Records', 'route' => 'admin.patientrecords', 'permissions' => ['patients.view']],
            ['label' => 'Holds / Pullout', 'route' => 'admin.holds.index', 'permissions' => ['holds.view']],
            ['label' => 'Requests', 'route' => 'admin.requests.index', 'permissions' => ['requests.view']],
            ['label' => 'Suppliers', 'route' => 'admin.suppliers.index', 'permissions' => ['suppliers.view']],
            ['label' => 'Analytics', 'route' => 'admin.analytics.overview', 'permissions' => ['reports.view']],
            ['label' => 'Low Stock Settings', 'route' => 'admin.lowstock.index', 'permissions' => ['settings.low_stock']],
            ['label' => 'Notifications', 'route' => 'admin.notifications.index', 'permissions' => ['notifications.manage']],
            ['label' => 'History Logs', 'route' => 'admin.historylog', 'permissions' => ['historylog.view']],
            ['label' => 'Audit Logs', 'route' => 'admin.audit.index', 'permissions' => ['audit.view']],
            ['label' => 'Branches', 'route' => 'admin.branches.index', 'permissions' => ['branches.manage']],
            ['label' => 'Automation', 'route' => 'admin.workflows.index', 'permissions' => ['workflows.view']],
            ['label' => 'Manage Accounts', 'route' => 'admin.manageaccount', 'permissions' => ['users.manage']],
            ['label' => 'User Permissions', 'route' => 'admin.roles.index', 'permissions' => ['settings.roles']],
        ];
    }
}
