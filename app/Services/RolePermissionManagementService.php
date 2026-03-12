<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\RolePermissionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class RolePermissionManagementService
{
    public function __construct(
        protected RolePermissionRepositoryInterface $rolePermissionRepository
    ) {
    }

    public function getIndexData(?int $selectedUserId = null, ?string $search = null): array
    {
        $users = $this->rolePermissionRepository->getUsersWithPermissions($search);
        $permissions = $this->rolePermissionRepository->getPermissionsOrdered();
        $selectedUser = $this->resolveSelectedUser($users, $selectedUserId);
        $assignedPermissions = $selectedUser
            ? $selectedUser->getEffectivePermissions()->sortBy('name')->values()
            : collect();
        $selectedPermissionIds = $assignedPermissions->pluck('id')->all();

        return [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'permissions' => $permissions,
            'selectedPermissionIds' => $selectedPermissionIds,
            'permissionSections' => $this->buildPermissionSections($permissions, $selectedPermissionIds),
            'assignedPermissions' => $assignedPermissions,
            'selectedUserInitials' => $this->buildSelectedUserInitials($selectedUser),
            'search' => $search,
        ];
    }

    public function updatePermissions(int $userId, array $permissionIds): User
    {
        $user = $this->rolePermissionRepository->findUserWithPermissions($userId);
        $this->rolePermissionRepository->syncUserPermissions($user, $permissionIds);

        return $this->rolePermissionRepository->findUserWithPermissions($userId);
    }

    protected function resolveSelectedUser(Collection $users, ?int $selectedUserId): ?User
    {
        if ($users->isEmpty()) {
            return null;
        }

        if ($selectedUserId) {
            $selectedUser = $users->firstWhere('id', $selectedUserId);
            if ($selectedUser) {
                return $selectedUser;
            }
        }

        return $users->first();
    }

    protected function buildPermissionSections(Collection $permissions, array $selectedPermissionIds): array
    {
        $sections = collect($this->sectionDefinitions())->map(function (array $definition, string $key) use ($permissions, $selectedPermissionIds) {
            $items = $permissions
                ->filter(fn ($permission) => in_array($permission->group, $definition['groups'], true))
                ->map(function ($permission) use ($selectedPermissionIds) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'label' => ucwords(str_replace(['.', '_'], ' ', $permission->name)),
                        'description' => $permission->description,
                        'group' => $permission->group ?? 'General',
                        'assigned' => in_array($permission->id, $selectedPermissionIds, true),
                    ];
                })
                ->values();

            return [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'permissions' => $items,
                'assigned_count' => $items->where('assigned', true)->count(),
                'total_count' => $items->count(),
            ];
        })->filter(fn (array $section) => $section['permissions']->isNotEmpty())->values();

        $assignedGroups = collect($this->sectionDefinitions())
            ->flatMap(fn (array $definition) => $definition['groups'])
            ->values()
            ->all();

        $unmapped = $permissions
            ->filter(fn ($permission) => !in_array($permission->group, $assignedGroups, true))
            ->map(function ($permission) use ($selectedPermissionIds) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'label' => ucwords(str_replace(['.', '_'], ' ', $permission->name)),
                    'description' => $permission->description,
                    'group' => $permission->group ?? 'General',
                    'assigned' => in_array($permission->id, $selectedPermissionIds, true),
                ];
            })
            ->values();

        if ($unmapped->isNotEmpty()) {
            $sections->push([
                'key' => 'other-access',
                'label' => 'Other Access',
                'description' => 'Additional permissions that do not fit a primary section.',
                'icon' => 'fa-solid fa-grid-2',
                'permissions' => $unmapped,
                'assigned_count' => $unmapped->where('assigned', true)->count(),
                'total_count' => $unmapped->count(),
            ]);
        }

        return $sections->all();
    }

    protected function buildSelectedUserInitials(?User $user): string
    {
        if (!$user) {
            return 'UA';
        }

        return collect(explode(' ', $user->name))
            ->filter()
            ->take(2)
            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
            ->implode('');
    }

    protected function sectionDefinitions(): array
    {
        return [
            'patient-management' => [
                'label' => 'Patient Management',
                'description' => 'Clinical workflows, patient records, holds, and incoming requests.',
                'icon' => 'fa-solid fa-notes-medical',
                'groups' => ['Patients', 'Holds', 'Requests'],
            ],
            'billing-orders' => [
                'label' => 'Billing & Orders',
                'description' => 'Ordering, approvals, and finance-related operational access.',
                'icon' => 'fa-solid fa-file-invoice-dollar',
                'groups' => ['Orders'],
            ],
            'inventory-supply' => [
                'label' => 'Inventory & Supply',
                'description' => 'Inventory controls, stock movements, and supplier coordination.',
                'icon' => 'fa-solid fa-boxes-stacked',
                'groups' => ['Inventory', 'Movements', 'Suppliers'],
            ],
            'reports-monitoring' => [
                'label' => 'Reports & Monitoring',
                'description' => 'Dashboards, exports, auditing, and history visibility.',
                'icon' => 'fa-solid fa-chart-line',
                'groups' => ['Dashboard', 'Reports', 'Audit', 'History'],
            ],
            'settings' => [
                'label' => 'Settings',
                'description' => 'System configuration, alerts, and branch-level setup controls.',
                'icon' => 'fa-solid fa-sliders',
                'groups' => ['Settings', 'Notifications'],
            ],
            'administrative-functions' => [
                'label' => 'Administrative Functions',
                'description' => 'User administration, workflow design, and advanced system controls.',
                'icon' => 'fa-solid fa-user-shield',
                'groups' => ['Users', 'Workflows'],
            ],
        ];
    }
}
