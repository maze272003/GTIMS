<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RegistrationService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository
    ) {
    }

    public function register(array $data): User
    {
        try {
            return DB::transaction(function () use ($data) {
                /** @var User $user */
                $user = $this->userRepository->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                ]);

                Log::info('User registered', ['user_id' => $user->id, 'email' => $user->email]);

                return $user;
            });
        } catch (\Exception $e) {
            Log::error('User registration failed', [
                'email' => $data['email'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

