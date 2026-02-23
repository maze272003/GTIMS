<?php

namespace App\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;

interface RolePermissionRepositoryInterface extends RepositoryInterface
{
    public function getRolesWithPermissions(): Collection;

    public function getPermissionsOrdered(): Collection;

    public function syncRolePermissions(array $permissionsData): void;
}

