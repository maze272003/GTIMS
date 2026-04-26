<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Models\User;
use App\Repositories\Interfaces\RolePermissionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class RolePermissionRepository extends BaseRepository implements RolePermissionRepositoryInterface
{
    public function __construct(
        User $model,
        protected Permission $permissionModel
    ) {
        parent::__construct($model);
    }

    public function getUsersWithPermissions(?string $search = null): Collection
    {
        return $this->model->newQuery()
            ->with([
                'branch:id,name',
                'level:id,name',
                'level.permissions:id,name,group,description',
                'permissions:id,name,group,description',
            ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhereHas('branch', function ($branchQuery) use ($search) {
                            $branchQuery->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('level', function ($levelQuery) use ($search) {
                            $levelQuery->where('name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->orderBy('name')
            ->get();
    }

    public function getPermissionsOrdered(): Collection
    {
        return $this->permissionModel
            ->orderBy('group')
            ->orderBy('name')
            ->get();
    }

    public function findUserWithPermissions(int $userId): User
    {
        return $this->model->newQuery()
            ->with([
                'branch:id,name',
                'level:id,name',
                'level.permissions:id,name,group,description',
                'permissions:id,name,group,description',
            ])
            ->findOrFail($userId);
    }

    public function syncUserPermissions(User $user, array $permissionIds): void
    {
        $user->syncDirectPermissions($permissionIds);
    }
}
