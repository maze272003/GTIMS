<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantRole extends Model
{
    use HasFactory;

    protected $table = 'tenant_roles';

    protected $fillable = [
        'name',
        'slug',
        'scope_type',
        'is_system_role',
    ];

    protected function casts(): array
    {
        return [
            'is_system_role' => 'boolean',
        ];
    }

    public function assignments()
    {
        return $this->hasMany(RoleAssignment::class, 'role_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'tenant_role_permissions', 'role_id', 'permission_id')
            ->withTimestamps();
    }
}
