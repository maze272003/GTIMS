<?php

namespace App\Services;

use App\Repositories\Interfaces\RolePermissionRepositoryInterface;

class RolePermissionManagementService
{
    public function __construct(
        protected RolePermissionRepositoryInterface $rolePermissionRepository
    ) {
    }

    public function getIndexData(): array
    {
        $roles = $this->rolePermissionRepository->getRolesWithPermissions();
        $permissions = $this->rolePermissionRepository->getPermissionsOrdered();

        return [
            'roles' => $roles,
            'permissions' => $permissions,
            'grouped' => $permissions->groupBy('group'),
        ];
    }

    public function updatePermissions(array $permissionsData): void
    {
        $this->rolePermissionRepository->syncRolePermissions($permissionsData);
    }
}

