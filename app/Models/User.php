<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'otp', // Idagdag ito
        'otp_expires_at', // Idagdag ito
        'branch_id',
        'user_level_id',
        'uses_custom_permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'password' => 'hashed',
            'uses_custom_permissions' => 'boolean',
        ];
    }

    public function level()
    {
        return $this->belongsTo(UserLevel::class, 'user_level_id');
    }

    // ... existing functions ...

    public function productMovements()
    {
        return $this->hasMany(ProductMovement::class);
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withTimestamps();
    }

    public function getEffectivePermissions()
    {
        if ($this->uses_custom_permissions) {
            if (!$this->relationLoaded('permissions')) {
                $this->load('permissions');
            }

            return $this->permissions;
        }

        if (!$this->level) {
            return collect();
        }

        if (!$this->relationLoaded('level') || !$this->level->relationLoaded('permissions')) {
            $this->load('level.permissions');
        }

        return $this->level->permissions;
    }

    public function hasPermission(string $permissionName): bool
    {
        return $this->getEffectivePermissions()->contains('name', $permissionName);
    }

    public function syncDirectPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);

        if (!$this->uses_custom_permissions) {
            $this->forceFill(['uses_custom_permissions' => true])->save();
        }

        $this->unsetRelation('permissions');
    }

    public function scopeWhereHasPermission($query, string|array $permissions)
    {
        foreach ((array) $permissions as $permissionName) {
            $query->where(function ($permissionQuery) use ($permissionName) {
                $permissionQuery
                    ->where(function ($customPermissionQuery) use ($permissionName) {
                        $customPermissionQuery
                            ->where('uses_custom_permissions', true)
                            ->whereHas('permissions', function ($relationQuery) use ($permissionName) {
                                $relationQuery->where('name', $permissionName);
                            });
                    })
                    ->orWhere(function ($rolePermissionQuery) use ($permissionName) {
                        $rolePermissionQuery
                            ->where('uses_custom_permissions', false)
                            ->whereHas('level.permissions', function ($relationQuery) use ($permissionName) {
                                $relationQuery->where('name', $permissionName);
                            });
                    });
            });
        }

        return $query;
    }
}
