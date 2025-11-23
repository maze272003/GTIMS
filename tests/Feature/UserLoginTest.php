<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserLevel;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLoginTest extends TestCase
{
    use RefreshDatabase; // I-re-reset nito ang DB kada test

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        // 1. Setup Data (Kailangan dahil sa foreign keys mo)
        $level = UserLevel::create(['name' => 'admin']);
        $branch = Branch::create(['name' => 'Head Office']);

        // 2. Create Verified User
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
            'email_verified_at' => now(), // IMPORTANTE: Verified dapat
        ]);

        // 3. Attempt Login
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'g-recaptcha-response' => 'test-token', // Mock captcha if needed, or disable validation in test env
        ]);

        // 4. Assertions
        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_unverified_users_cannot_login()
    {
        // 1. Setup Data
        $level = UserLevel::create(['name' => 'admin']);
        $branch = Branch::create(['name' => 'Head Office']);

        // 2. Create UNVERIFIED User (null ang email_verified_at)
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
            'email_verified_at' => null, // NOT VERIFIED
        ]);

        // 3. Attempt Login
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'g-recaptcha-response' => 'test-token',
        ]);

        // 4. Assertions
        $this->assertGuest(); // Dapat hindi naka-login
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $level = UserLevel::create(['name' => 'admin']);
        $user = User::factory()->create([
            'user_level_id' => $level->id,
            'email_verified_at' => now(),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'g-recaptcha-response' => 'test-token',
        ]);

        $this->assertGuest();
    }
}