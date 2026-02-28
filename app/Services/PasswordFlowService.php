<?php

namespace App\Services;

use App\Models\Moderator;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordFlowService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository
    ) {
    }

    public function confirmPassword(User $user, string $password): bool
    {
        $guard = $user instanceof Moderator ? 'moderator' : 'web';

        return Auth::guard($guard)->validate([
            'email' => $user->email,
            'password' => $password,
        ]);
    }

    public function sendResetLink(array $credentials, string $broker = 'users'): string
    {
        return Password::broker($broker)->sendResetLink($credentials);
    }

    public function resetPassword(array $payload, string $broker = 'users'): string
    {
        return Password::broker($broker)->reset(
            $payload,
            function (User $user) use ($payload) {
                $user->forceFill([
                    'password' => Hash::make($payload['password']),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );
    }

    public function updatePassword(User $user, string $plainPassword): void
    {
        if ($user instanceof Moderator) {
            $user->forceFill([
                'password' => Hash::make($plainPassword),
            ])->save();

            return;
        }

        $this->userRepository->update($user->id, [
            'password' => Hash::make($plainPassword),
        ]);
    }
}
