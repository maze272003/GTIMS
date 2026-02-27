<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\EncryptsAttributes;
use App\Tenancy\TenantContext;
use App\Tenancy\ScopedPermissionResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, EncryptsAttributes;

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
        'province_id',
        'barangay_id',
        'two_factor_secret',
        'two_factor_enabled',
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
            'password' => 'hashed',
            'otp_expires_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
        ];
    }

    protected array $encryptable = [
        'two_factor_secret',
    ];

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

    public function tenantMemberships()
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function memberships()
    {
        return $this->tenantMemberships();
    }

    public function roleAssignments()
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function tenantApiTokens()
    {
        return $this->hasMany(TenantApiToken::class);
    }

    public function hasPermission(string $permissionName, ?TenantContext $tenantContext = null, ?array $targetTenant = null): bool
    {
        return app(ScopedPermissionResolver::class)
            ->hasPermission($this, $permissionName, $tenantContext, $targetTenant);
    }

    public function hasActiveMembership(?TenantContext $ctx = null): bool
    {
        if (!$ctx) {
            return $this->tenantMemberships()->where('status', 'active')->exists();
        }

        if ($ctx->isPlatform()) {
            return $this->tenantMemberships()
                ->where('scope_type', 'platform')
                ->where('status', 'active')
                ->exists();
        }

        if ($this->tenantMemberships()
            ->where('scope_type', 'platform')
            ->where('status', 'active')
            ->exists()
        ) {
            return true;
        }

        if ($ctx->isProvince()) {
            return $this->tenantMemberships()
                ->where('scope_type', 'province')
                ->where('scope_id', $ctx->provinceId)
                ->where('status', 'active')
                ->exists();
        }

        return $this->tenantMemberships()
            ->where(function ($query) use ($ctx) {
                $query->where(function ($barangay) use ($ctx) {
                    $barangay->where('scope_type', 'barangay')
                        ->where('scope_id', $ctx->barangayId);
                })->orWhere(function ($province) use ($ctx) {
                    $province->where('scope_type', 'province')
                        ->where('scope_id', $ctx->provinceId);
                });
            })
            ->where('status', 'active')
            ->exists();
    }

    public function isModerator(): bool
    {
        $moderatorSlug = (string) config('tenancy.roles.moderator.slug', 'moderator');

        $hasScopedRole = $this->roleAssignments()
            ->where('scope_type', 'platform')
            ->whereHas('role', function ($query) use ($moderatorSlug) {
                $query->where('slug', $moderatorSlug);
            })
            ->exists();

        if ($hasScopedRole) {
            return true;
        }

        $hasPlatformMembership = $this->tenantMemberships()
            ->where('scope_type', 'platform')
            ->where('status', 'active')
            ->exists();

        if ($hasPlatformMembership) {
            return true;
        }

        if (config('tenancy.rbac.allow_legacy_moderator_fallback', false)) {
            return (bool) ($this->level && $this->level->name === 'superadmin');
        }

        return false;
    }
}
