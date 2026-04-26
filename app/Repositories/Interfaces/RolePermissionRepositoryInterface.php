<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface RolePermissionRepositoryInterface extends RepositoryInterface
{
    public function getUsersWithPermissions(?string $search = null): Collection;

    public function getPermissionsOrdered(): Collection;

    public function findUserWithPermissions(int $userId): User;

    public function syncUserPermissions(User $user, array $permissionIds): void;
}
