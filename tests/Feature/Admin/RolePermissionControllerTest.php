<?php

namespace Tests\Feature\Admin;

use App\Mail\NewUserCredentials;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RolePermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_page_renders_selected_users_individual_access(): void
    {
        $actor = $this->createAccessManager();
        $targetLevel = UserLevel::create(['name' => 'doctor']);
        $patientsView = Permission::create([
            'name' => 'patients.view',
            'group' => 'Patients',
            'description' => 'View patient records',
        ]);
        $reportsView = Permission::create([
            'name' => 'reports.view',
            'group' => 'Reports',
            'description' => 'View reports',
        ]);

        $targetUser = User::factory()->create([
            'name' => 'Dr. Maria Santos',
            'email_verified_at' => now(),
            'user_level_id' => $targetLevel->id,
            'branch_id' => $actor->branch_id,
            'uses_custom_permissions' => true,
        ]);
        $targetUser->permissions()->sync([$patientsView->id, $reportsView->id]);

        $response = $this->actingAs($actor)->get(route('admin.roles.index', ['user' => $targetUser->id]));

        $response->assertOk();
        $response->assertSee('User Access Management');
        $response->assertSee('Dr. Maria Santos');
        $response->assertSee('Patient Management');
        $response->assertSee('Current Access');
        $response->assertSee('Patients View');
    }

    public function test_permissions_page_can_return_ajax_fragments_without_full_reload(): void
    {
        $actor = $this->createAccessManager();
        $targetLevel = UserLevel::create(['name' => 'doctor']);
        $patientsView = Permission::create([
            'name' => 'patients.view',
            'group' => 'Patients',
            'description' => 'View patient records',
        ]);

        $targetUser = User::factory()->create([
            'name' => 'Dr. Ajax User',
            'email_verified_at' => now(),
            'user_level_id' => $targetLevel->id,
            'branch_id' => $actor->branch_id,
            'uses_custom_permissions' => true,
        ]);
        $targetUser->permissions()->sync([$patientsView->id]);

        $response = $this->actingAs($actor)->getJson(route('admin.roles.index', ['user' => $targetUser->id]));

        $response->assertOk();
        $response->assertJsonFragment(['selected_user_id' => $targetUser->id]);
        $response->assertJsonStructure([
            'header_actions_html',
            'directory_html',
            'workspace_html',
            'selected_user_id',
            'search',
            'url',
        ]);
        $this->assertStringContainsString('Dr. Ajax User', $response->json('workspace_html'));
    }

    public function test_permissions_page_defers_user_changes_to_user_management(): void
    {
        $actor = $this->createAccessManager();
        $targetLevel = UserLevel::create(['name' => 'doctor']);

        $targetUser = User::factory()->create([
            'name' => 'Doctor Linked User',
            'email' => 'doctor.linked@example.com',
            'email_verified_at' => now(),
            'user_level_id' => $targetLevel->id,
            'branch_id' => $actor->branch_id,
            'uses_custom_permissions' => true,
        ]);

        $response = $this->actingAs($actor)->get(route('admin.roles.index', ['user' => $targetUser->id]));

        $response->assertOk();
        $response->assertDontSee('Add User');
        $response->assertSee('Open User Management');
        $response->assertSee(route('admin.manageaccount', ['search' => $targetUser->email]), false);
    }

    public function test_updating_with_no_permissions_revokes_role_based_access_for_one_user(): void
    {
        $actor = $this->createAccessManager();
        $doctorLevel = UserLevel::create(['name' => 'doctor']);
        $ordersView = Permission::create([
            'name' => 'orders.view',
            'group' => 'Orders',
            'description' => 'View orders',
        ]);

        $doctorLevel->permissions()->sync([$ordersView->id]);

        $targetUser = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $doctorLevel->id,
            'branch_id' => $actor->branch_id,
            'uses_custom_permissions' => false,
        ]);

        $this->assertTrue($targetUser->fresh()->hasPermission('orders.view'));

        $response = $this->actingAs($actor)->post(route('admin.roles.update'), [
            'user_id' => $targetUser->id,
            'permissions' => [],
        ]);

        $response->assertRedirect(route('admin.roles.index', ['user' => $targetUser->id]));

        $targetUser = $targetUser->fresh()->load('permissions', 'level.permissions');

        $this->assertTrue($targetUser->uses_custom_permissions);
        $this->assertCount(0, $targetUser->permissions);
        $this->assertFalse($targetUser->hasPermission('orders.view'));
    }

    public function test_updating_permissions_can_return_ajax_fragments(): void
    {
        $actor = $this->createAccessManager();
        $doctorLevel = UserLevel::create(['name' => 'doctor']);
        $ordersView = Permission::create([
            'name' => 'orders.view',
            'group' => 'Orders',
            'description' => 'View orders',
        ]);

        $targetUser = User::factory()->create([
            'name' => 'Nurse Fragment',
            'email_verified_at' => now(),
            'user_level_id' => $doctorLevel->id,
            'branch_id' => $actor->branch_id,
            'uses_custom_permissions' => true,
        ]);

        $response = $this->actingAs($actor)->postJson(route('admin.roles.update'), [
            'user_id' => $targetUser->id,
            'permissions' => [$ordersView->id],
            'search' => 'nurse',
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'selected_user_id' => $targetUser->id,
            'search' => 'nurse',
            'message' => 'Permissions updated for Nurse Fragment.',
        ]);
        $this->assertStringContainsString('Nurse Fragment', $response->json('workspace_html'));

        $targetUser = $targetUser->fresh()->load('permissions');
        $this->assertEquals([$ordersView->id], $targetUser->permissions->pluck('id')->all());
    }

    public function test_creating_user_from_permissions_flow_seeds_individual_permissions(): void
    {
        Mail::fake();

        $actor = $this->createAccessManager();
        $doctorLevel = UserLevel::create(['name' => 'doctor']);
        $patientsView = Permission::create([
            'name' => 'patients.view',
            'group' => 'Patients',
            'description' => 'View patient records',
        ]);

        $doctorLevel->permissions()->sync([$patientsView->id]);

        $response = $this->actingAs($actor)->post(route('admin.manageaccount.store'), [
            'name' => 'Nurse Jamie Cruz',
            'email' => 'nurse.jamie@example.com',
            'user_level_id' => $doctorLevel->id,
            'branch_id' => $actor->branch_id,
            'password' => 'Passw0rd!',
            'redirect_to_permissions' => 1,
            'user_form_mode' => 'create',
        ]);

        $createdUser = User::where('email', 'nurse.jamie@example.com')->firstOrFail();

        $response->assertRedirect(route('admin.roles.index', ['user' => $createdUser->id]));
        $this->assertTrue($createdUser->uses_custom_permissions);
        $this->assertEquals([$patientsView->id], $createdUser->permissions()->pluck('permissions.id')->all());
        Mail::assertSent(NewUserCredentials::class);
    }

    public function test_user_management_lists_a_link_to_view_a_users_permissions(): void
    {
        $actor = $this->createAccessManager();
        $doctorLevel = UserLevel::create(['name' => 'doctor']);

        $targetUser = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $doctorLevel->id,
            'branch_id' => $actor->branch_id,
        ]);

        $response = $this->actingAs($actor)->get(route('admin.manageaccount'));

        $response->assertOk();
        $response->assertSee('View Permissions');
        $response->assertSee(route('admin.roles.index', ['user' => $targetUser->id]), false);
    }

    private function createAccessManager(): User
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'is_archived' => false,
        ]);

        $level = UserLevel::create(['name' => 'superadmin']);
        $settingsPermission = Permission::create([
            'name' => 'settings.roles',
            'group' => 'Settings',
            'description' => 'Manage user permissions',
        ]);
        $usersPermission = Permission::create([
            'name' => 'users.manage',
            'group' => 'Users',
            'description' => 'Manage user accounts',
        ]);

        $level->permissions()->sync([$settingsPermission->id, $usersPermission->id]);

        return User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
            'uses_custom_permissions' => false,
        ]);
    }
}
