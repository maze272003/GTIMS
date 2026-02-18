<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $fillable = ['user_level_id', 'permission_id'];

    public function userLevel()
    {
        return $this->belongsTo(UserLevel::class);
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }
}
