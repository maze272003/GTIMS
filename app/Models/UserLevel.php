<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UserLevel extends Model
{
    protected $fillable = ['name']; // Para magamit sa Seeder

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function hasPermission(string $permissionName): bool
    {
        if (!$this->relationLoaded('permissions')) {
            $this->load('permissions');
        }

        return $this->permissions->contains('name', $permissionName);
    }
}