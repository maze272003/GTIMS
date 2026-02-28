<?php

namespace App\Models;

use App\Tenancy\TenantContext;

class Moderator extends User
{
    protected $table = 'moderators';

    public function isModerator(): bool
    {
        return true;
    }

    public function hasPermission(string $permissionName, ?TenantContext $tenantContext = null, ?array $targetTenant = null): bool
    {
        return true;
    }

    public function hasActiveMembership(?TenantContext $ctx = null): bool
    {
        return true;
    }
}
