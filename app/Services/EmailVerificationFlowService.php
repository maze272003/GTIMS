<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Verified;

class EmailVerificationFlowService
{
    public function hasVerifiedEmail(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function sendVerificationNotification(User $user): void
    {
        $user->sendEmailVerificationNotification();
    }

    public function verifyUser(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));

            return true;
        }

        return false;
    }
}

