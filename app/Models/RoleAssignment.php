<?php

namespace App\Models;

use App\Services\TenantSessionSecurityService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role_id',
        'scope_type',
        'scope_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(TenantRole::class, 'role_id');
    }

    protected static function booted(): void
    {
        $invalidate = function (RoleAssignment $assignment): void {
            app(TenantSessionSecurityService::class)
                ->invalidateAfterRoleChange((int) $assignment->user_id);
        };

        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }
}
