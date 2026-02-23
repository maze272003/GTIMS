<?php

namespace App\Services;

use App\Models\User;

class ProfileAccountService
{
    public function getEditData(User $user): array
    {
        return ['user' => $user];
    }

    public function updateProfile(User $user, array $validated): void
    {
        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
    }

    public function deleteAccount(User $user): void
    {
        $user->delete();
    }
}

