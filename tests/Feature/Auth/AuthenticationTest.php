<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserLevel;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        // 1. Setup Dependencies
        // Gumagamit tayo ng create() direct kung sakaling walang factory ang UserLevel/Branch
        // Kung may factory ka, pwede mong gamitin ang UserLevel::factory()->create()
        $level = UserLevel::create(['name' => 'admin']);
        $branch = Branch::create(['name' => 'Head Office']);

        // 2. Create Verified User
        $user = User::factory()->create([
            'email_verified_at' => now(), // Required sa Controller
            'user_level_id' => $level->id, // Required sa Controller
            'branch_id' => $branch->id,
            'password' => bcrypt('password'),
        ]);

        // 3. Attempt Login
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'g-recaptcha-response' => 'test-token', // Bypass logic
        ]);

        // 4. Assertions
        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        // Create Valid User pa rin para sure na sa PASSWORD lang nagfe-fail
        $level = UserLevel::create(['name' => 'admin']);
        $branch = Branch::create(['name' => 'Head Office']);
        
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'g-recaptcha-response' => 'test-token',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        // Create Valid User
        $level = UserLevel::create(['name' => 'admin']);
        $branch = Branch::create(['name' => 'Head Office']);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}