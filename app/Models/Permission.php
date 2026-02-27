<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'group', 'description'];

    public function userLevels()
    {
        return $this->belongsToMany(UserLevel::class, 'role_permissions');
    }

    public function tenantRoles()
    {
        return $this->belongsToMany(TenantRole::class, 'tenant_role_permissions', 'permission_id', 'role_id')
            ->withTimestamps();
    }
}
