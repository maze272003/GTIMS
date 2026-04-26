<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\RateLimitService;
use Illuminate\Support\Facades\Cache;
use Mockery;

class RateLimitServiceTest extends TestCase
{
    private RateLimitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RateLimitService();
    }

    public function test_is_enabled_returns_true_by_default(): void
    {
        $this->assertTrue($this->service->isEnabled());
    }

    public function test_get_limit_returns_configured_limits(): void
    {
        $authLimit = $this->service->getLimit('auth');
        $this->assertArrayHasKey('max_requests', $authLimit);
        $this->assertArrayHasKey('decay_seconds', $authLimit);
        $this->assertEquals(5, $authLimit['max_requests']);
    }

    public function test_get_limit_returns_defaults_for_unknown_group(): void
    {
        $defaultLimit = $this->service->getLimit('unknown');
        $this->assertEquals(60, $defaultLimit['max_requests']);
        $this->assertEquals(60, $defaultLimit['decay_seconds']);
    }

    public function test_attempt_allows_requests_under_limit(): void
    {
        config(['rate_limit.enabled' => true]);
        config(['rate_limit.driver' => 'local']);

        Cache::flush();

        $result = $this->service->attempt('test_allow_' . time(), 5, 60);

        $this->assertTrue($result['allowed']);
        $this->assertEquals(5, $result['limit']);
    }

    public function test_attempt_returns_remaining_when_allowed(): void
    {
        config(['rate_limit.enabled' => true]);
        config(['rate_limit.driver' => 'local']);

        Cache::flush();

        $key = 'test_remain_' . time();
        $this->service->attempt($key, 5, 60);
        $result = $this->service->attempt($key, 5, 60);

        $this->assertLessThan(5, $result['remaining']);
    }

    public function test_attempt_passes_when_rate_limiting_is_disabled(): void
    {
        config(['rate_limit.enabled' => false]);

        $result = $this->service->attempt('test_key', 5, 60);

        $this->assertTrue($result['allowed']);
    }

    public function test_get_limit_returns_export_limits(): void
    {
        $exportLimit = $this->service->getLimit('export');
        $this->assertEquals(10, $exportLimit['max_requests']);
        $this->assertEquals(300, $exportLimit['decay_seconds']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Cache::flush();
        parent::tearDown();
    }
}