<?php

namespace Tests\Unit\Tenancy;

use App\Models\RoleAssignment;
use App\Models\TenantMembership;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TenantSessionInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_is_invalidated_after_membership_change(): void
    {
        $user = User::factory()->create();
        Cache::put("auth:permissions:user:{$user->id}", ['dummy'], 60);
        Cache::put("auth:memberships:user:{$user->id}", ['dummy'], 60);

        TenantMembership::create([
            'user_id' => $user->id,
            'scope_type' => 'platform',
            'scope_id' => null,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->assertFalse(Cache::has("auth:permissions:user:{$user->id}"));
        $this->assertFalse(Cache::has("auth:memberships:user:{$user->id}"));
    }

    public function test_cache_is_invalidated_after_role_assignment_change(): void
    {
        $user = User::factory()->create();
        $role = TenantRole::create([
            'name' => 'Moderator',
            'slug' => 'moderator',
            'scope_type' => 'platform',
            'is_system_role' => true,
        ]);

        Cache::put("auth:permissions:user:{$user->id}", ['dummy'], 60);
        Cache::put("auth:memberships:user:{$user->id}", ['dummy'], 60);

        RoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => 'platform',
            'scope_id' => null,
        ]);

        $this->assertFalse(Cache::has("auth:permissions:user:{$user->id}"));
        $this->assertFalse(Cache::has("auth:memberships:user:{$user->id}"));
    }
}

