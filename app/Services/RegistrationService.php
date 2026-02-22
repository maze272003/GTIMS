<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class RegistrationService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository
    ) {
    }

    public function register(array $data): User
    {
        /** @var User $user */
        $user = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        return $user;
    }
}

