<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Models\UserLevel;
use App\Repositories\Interfaces\RolePermissionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class RolePermissionRepository extends BaseRepository implements RolePermissionRepositoryInterface
{
    public function __construct(
        UserLevel $model,
        protected Permission $permissionModel
    ) {
        parent::__construct($model);
    }

    public function getRolesWithPermissions(): Collection
    {
        return $this->model->with('permissions')->get();
    }

    public function getPermissionsOrdered(): Collection
    {
        return $this->permissionModel
            ->orderBy('group')
            ->orderBy('name')
            ->get();
    }

    public function syncRolePermissions(array $permissionsData): void
    {
        foreach ($this->model->all() as $role) {
            $rolePerms = $permissionsData[$role->id] ?? [];
            $role->permissions()->sync($rolePerms);
        }
    }
}

