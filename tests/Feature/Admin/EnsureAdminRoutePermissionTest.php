<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureAdminRoutePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_open_admin_page_without_view_permission(): void
    {
        $user = $this->createUserWithPermissions([]);

        $response = $this->actingAs($user)->get(route('admin.inventory'));

        $response->assertForbidden();
        $response->assertSee('This page cannot be accessed');
        $response->assertSee('contact the superadmin', false);
    }

    public function test_user_cannot_access_crud_action_without_required_permission(): void
    {
        $inventoryView = Permission::create([
            'name' => 'inventory.view',
            'group' => 'Inventory',
            'description' => 'View inventory',
        ]);

        $user = $this->createUserWithPermissions([$inventoryView->id]);

        $response = $this->actingAs($user)->post(route('admin.inventory.addproduct'), []);

        $response->assertForbidden();
        $response->assertSee('This page or action cannot be accessed with your account.');
        $response->assertSee('contact the superadmin', false);
    }

    public function test_forbidden_page_points_to_users_first_available_module(): void
    {
        $notificationsPermission = Permission::create([
            'name' => 'notifications.manage',
            'group' => 'Notifications',
            'description' => 'Manage notifications',
        ]);

        $user = $this->createUserWithPermissions([$notificationsPermission->id]);

        $response = $this->actingAs($user)->get(route('admin.inventory'));

        $response->assertForbidden();
        $response->assertSee('Go to Notifications');
        $response->assertSee(route('admin.notifications.index'), false);
    }

    public function test_export_route_requires_both_view_and_export_permissions(): void
    {
        $inventoryView = Permission::create([
            'name' => 'inventory.view',
            'group' => 'Inventory',
            'description' => 'View inventory',
        ]);

        $user = $this->createUserWithPermissions([$inventoryView->id]);

        $response = $this->actingAs($user)->post(route('admin.inventory.export'), []);

        $response->assertForbidden();
        $response->assertSee('contact the superadmin', false);
        $response->assertSee('Go to Inventory');
    }

    public function test_dashboard_redirect_uses_available_module_when_dashboard_is_not_allowed(): void
    {
        $notificationsPermission = Permission::create([
            'name' => 'notifications.manage',
            'group' => 'Notifications',
            'description' => 'Manage notifications',
        ]);

        $user = $this->createUserWithPermissions([$notificationsPermission->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('admin.notifications.index'));
    }

    private function createUserWithPermissions(array $permissionIds): User
    {
        $branch = Branch::create([
            'name' => 'Route Guard Branch '.Branch::count(),
            'is_archived' => false,
        ]);

        $level = UserLevel::create([
            'name' => 'guarded-level-'.UserLevel::count(),
        ]);

        if ($permissionIds !== []) {
            $level->permissions()->sync($permissionIds);
        }

        return User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
            'uses_custom_permissions' => false,
        ]);
    }
}
