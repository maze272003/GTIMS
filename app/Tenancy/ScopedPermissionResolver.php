<?php

namespace App\Tenancy;

use App\Models\RoleAssignment;
use App\Models\User;

class ScopedPermissionResolver
{
    /**
     * Resolve permission using scoped role assignments first, then legacy user level fallback.
     *
     * @param  array{province_id?: int|null, barangay_id?: int|null}|null  $targetTenant
     */
    public function hasPermission(User $user, string $permissionName, ?TenantContext $context = null, ?array $targetTenant = null): bool
    {
        if ($this->hasScopedPermission($user, $permissionName, $context, $targetTenant)) {
            return true;
        }

        return $this->hasLegacyPermission($user, $permissionName);
    }

    /**
     * Scoped permission checks consider role scope + current context + target tenant ownership.
     *
     * @param  array{province_id?: int|null, barangay_id?: int|null}|null  $targetTenant
     */
    protected function hasScopedPermission(User $user, string $permissionName, ?TenantContext $context = null, ?array $targetTenant = null): bool
    {
        $assignments = RoleAssignment::query()
            ->where('user_id', $user->id)
            ->whereHas('role.permissions', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->with('role:id,slug,scope_type')
            ->get();

        if ($assignments->isEmpty()) {
            return false;
        }

        foreach ($assignments as $assignment) {
            if ($this->assignmentMatchesScope($assignment, $context, $targetTenant)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{province_id?: int|null, barangay_id?: int|null}|null  $targetTenant
     */
    protected function assignmentMatchesScope(RoleAssignment $assignment, ?TenantContext $context = null, ?array $targetTenant = null): bool
    {
        $scopeType = (string) $assignment->scope_type;
        $scopeId = $assignment->scope_id;
        $targetProvince = $targetTenant['province_id'] ?? null;
        $targetBarangay = $targetTenant['barangay_id'] ?? null;

        if ($scopeType === 'platform') {
            return true;
        }

        if ($scopeType === 'province') {
            if ($context && $context->provinceId && (int) $context->provinceId !== (int) $scopeId) {
                return false;
            }

            if ($targetProvince && (int) $targetProvince !== (int) $scopeId) {
                return false;
            }

            return true;
        }

        if ($scopeType === 'barangay') {
            if ($context && $context->barangayId && (int) $context->barangayId !== (int) $scopeId) {
                return false;
            }

            if ($targetBarangay && (int) $targetBarangay !== (int) $scopeId) {
                return false;
            }

            return true;
        }

        return false;
    }

    protected function hasLegacyPermission(User $user, string $permissionName): bool
    {
        if (!$user->level) {
            return false;
        }

        if (!$user->relationLoaded('level') || !$user->level->relationLoaded('permissions')) {
            $user->load('level.permissions');
        }

        if ($user->level->permissions->contains('name', $permissionName)) {
            return true;
        }

        // Backward compatibility with legacy pluralized permission keys used in older services.
        $legacyAliases = [
            'inventories.view' => 'inventory.view',
            'inventories.manage' => 'inventory.edit',
            'logs.view' => 'historylog.view',
            'audit_trails.view' => 'audit.view',
            'notifications.view' => 'notifications.manage',
        ];

        if (isset($legacyAliases[$permissionName])) {
            return $user->level->permissions->contains('name', $legacyAliases[$permissionName]);
        }

        return false;
    }
}

