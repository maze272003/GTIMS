<?php

namespace App\Policies;

use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;

abstract class TenantPolicy
{
    /**
     * @return array{
     *   view?: string,
     *   create?: string,
     *   update?: string,
     *   delete?: string,
     *   export?: string
     * }
     */
    abstract protected function permissionMap(): array;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isModerator()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->authorize($user, null, 'view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->authorize($user, $model, 'view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, null, 'create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->authorize($user, $model, 'update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->authorize($user, $model, 'delete');
    }

    public function export(User $user): bool
    {
        return $this->authorize($user, null, 'export');
    }

    protected function authorize(User $user, ?Model $model, string $ability): bool
    {
        $permission = $this->permissionMap()[$ability] ?? $this->permissionMap()['view'] ?? null;
        if (!$permission) {
            return false;
        }

        $tenantContext = $this->tenantContext();
        if (!$user->hasPermission($permission, $tenantContext)) {
            return false;
        }

        if ($tenantContext && !$tenantContext->isPlatform() && !$user->hasActiveMembership($tenantContext)) {
            return false;
        }

        if ($model && !TenantScope::modelBelongsToTenant($model, $tenantContext)) {
            return false;
        }

        return true;
    }

    protected function tenantContext(): ?TenantContext
    {
        if (app()->bound(TenantContext::class)) {
            return app(TenantContext::class);
        }

        return TenantContext::fromSession([
            'tenant.scope_type' => session('tenant.scope_type'),
            'tenant.province_id' => session('tenant.province_id'),
            'tenant.barangay_id' => session('tenant.barangay_id'),
            'tenant.route_slug_province' => session('tenant.route_slug_province'),
            'tenant.route_slug_barangay' => session('tenant.route_slug_barangay'),
        ]);
    }
}

