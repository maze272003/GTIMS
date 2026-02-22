<?php

namespace App\Services;

use App\Mail\NewLoginNotification;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        return $this->getRedirectUrl($user) ?? route('admin.dashboard');
    }

    public function getRedirectUrl(User $user): ?string
    {
        if (is_null($user->level)) {
            return null;
        }

        if ($user->hasPermission('dashboard.view')) {
            return route('admin.dashboard');
        }

        if ($user->hasPermission('orders.view')) {
            return route('admin.orders.index');
        }

        return null;
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
}

