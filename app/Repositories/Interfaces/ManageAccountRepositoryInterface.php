<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ManageAccountRepositoryInterface
{
    public function paginateUsersWithRelations(?string $search = null, int $perPage = 10): LengthAwarePaginator;

    public function getLevelsForManage(bool $includeSuperadmin): Collection;

    public function getAllBranches(): Collection;

    public function findUserLevelOrFail(int $id): UserLevel;

    public function createUser(array $data): User;

    public function findUserOrFail(int $id): User;

    public function updateUser(User $user, array $data): bool;

    public function markUserVerifiedNow(User $user): bool;
}

