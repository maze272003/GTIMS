<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RateLimitMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_limit_blocks_requests_after_limit_exceeded(): void
    {
        config(['rate_limit.enabled' => true]);
        config(['rate_limit.driver' => 'local']);
        config(['rate_limit.limits.auth.max_requests' => 3]);

        Cache::flush();

        for ($i = 0; $i < 4; $i++) {
            $response = $this->postJson('/login', [
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            if ($i < 3) {
                $response->assertStatus(422);
            } else {
                $response->assertStatus(429);
            }
        }
    }

    public function test_rate_limit_headers_are_present(): void
    {
        config(['rate_limit.enabled' => true]);
        config(['rate_limit.driver' => 'local']);

        Cache::flush();

        $response = $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_rate_limit_allows_requests_when_disabled(): void
    {
        config(['rate_limit.enabled' => false]);

        $response = $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
    }

    public function test_auth_routes_have_strict_rate_limit(): void
    {
        config(['rate_limit.enabled' => true]);
        config(['rate_limit.driver' => 'local']);
        config(['rate_limit.limits.auth.max_requests' => 2]);

        Cache::flush();

        $this->postJson('/login', ['email' => 'test@example.com', 'password' => 'test']);
        $this->postJson('/login', ['email' => 'test@example.com', 'password' => 'test']);
        $response = $this->postJson('/login', ['email' => 'test@example.com', 'password' => 'test']);

        $response->assertStatus(429);
    }

    public function test_otp_routes_have_strict_rate_limit(): void
    {
        config(['rate_limit.enabled' => true]);
        config(['rate_limit.driver' => 'local']);
        config(['rate_limit.limits.otp.max_requests' => 3]);

        Cache::flush();

        $response = $this->post('/send-otp', ['phone' => '09123456789']);
        $response = $this->post('/send-otp', ['phone' => '09123456789']);
        $response = $this->post('/send-otp', ['phone' => '09123456789']);
        $response = $this->post('/send-otp', ['phone' => '09123456789']);

        $response->assertStatus(429);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}