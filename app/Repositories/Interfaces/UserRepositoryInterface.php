<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findByEmailWithRelations(string $email, array $relations = []): ?User;

    public function getAllOrderedByName(array $columns = ['*']): Collection;

    public function updateLoginMetadata(int $userId, ?string $ip): bool;

    public function updateOtp(int $userId, ?string $otp, mixed $expiresAt): bool;
}

