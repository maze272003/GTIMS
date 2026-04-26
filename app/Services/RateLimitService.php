<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RateLimitService
{
    private array $config;
    private string $driver;
    private bool $enabled;
    private bool $debug;

    public function __construct()
    {
        $this->config = config('rate_limit');
        $this->driver = $this->config['driver'] ?? 'redis';
        $this->enabled = $this->config['enabled'] ?? true;
        $this->debug = $this->config['debug'] ?? false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getIdentifier($request): string
    {
        $identifiersConfig = $this->config['identifiers'] ?? [];

        if (($identifiersConfig['use_user_id'] ?? true) && auth()->check()) {
            $userId = auth()->id();
            if ($userId) {
                return "user:{$userId}";
            }
        }

        if ($identifiersConfig['use_ip'] ?? true) {
            $ip = $this->getClientIp($request);
            if ($ip) {
                return "ip:{$ip}";
            }
        }

        if ($identifiersConfig['fallback_to_ip'] ?? true) {
            $ip = $request->ip();
            return "ip:" . ($ip ?? 'unknown');
        }

        $ip = $request->ip();
        return "ip:" . ($ip ?? 'unknown');
    }

    private function getClientIp($request): ?string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            $value = $request->header($header);
            if ($value) {
                $ips = explode(',', $value);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $request->ip();
    }

    public function attempt(string $key, int $maxAttempts, int $decaySeconds): array
    {
        if (!$this->enabled) {
            return $this->formatResponse(true, $maxAttempts, 0, 0);
        }

        if ($this->driver === 'redis') {
            return $this->attemptRedis($key, $maxAttempts, $decaySeconds);
        }

        return $this->attemptLocal($key, $maxAttempts, $decaySeconds);
    }

    private function attemptRedis(string $key, int $maxAttempts, int $decaySeconds): array
    {
        $prefix = $this->config['redis']['prefix'] ?? 'ratelimit:';
        $fullKey = $prefix . $key;
        $connection = $this->config['redis']['connection'] ?? 'default';

        try {
            $now = microtime(true);
            $windowStart = $now - $decaySeconds;

            Redis::connection($connection)->zRemrangebyscore($fullKey, '-inf', $windowStart);

            $currentCount = Redis::connection($connection)->zCard($fullKey);

            if ($currentCount >= $maxAttempts) {
                $oldestEntry = Redis::connection($connection)->zRange($fullKey, 0, 0, true);
                $retryAfter = 0;

                if (!empty($oldestEntry)) {
                    $oldestTimestamp = (float) array_values($oldestEntry)[0];
                    $retryAfter = ceil($oldestTimestamp + $decaySeconds - $now);
                }

                $this->logRateLimitExceeded($key, $currentCount, $maxAttempts, $retryAfter);

                return $this->formatResponse(
                    false,
                    $maxAttempts,
                    $currentCount,
                    max(0, $retryAfter)
                );
            }

            Redis::connection($connection)->zAdd($fullKey, $now, uniqid('rl:', true));
            Redis::connection($connection)->expire($fullKey, $decaySeconds);

            $remaining = $maxAttempts - ($currentCount + 1);

            return $this->formatResponse(true, $maxAttempts, $currentCount + 1, 0, $remaining);
        } catch (\Exception $e) {
            Log::error('Rate limit Redis error', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            if ($this->debug) {
                throw $e;
            }

            return $this->formatResponse(true, $maxAttempts, 0, 0);
        }
    }

    private function attemptLocal(string $key, int $maxAttempts, int $decaySeconds): array
    {
        $cacheKey = "ratelimit:{$key}";
        $now = microtime(true);
        $windowStart = $now - $decaySeconds;

        $attempts = cache()->get($cacheKey, []);

        $attempts = array_filter($attempts, fn($timestamp) => $timestamp > $windowStart);

        $currentCount = count($attempts);

        if ($currentCount >= $maxAttempts) {
            $oldestTimestamp = !empty($attempts) ? min($attempts) : $now;
            $retryAfter = ceil($oldestTimestamp + $decaySeconds - $now);

            $this->logRateLimitExceeded($key, $currentCount, $maxAttempts, $retryAfter);

            return $this->formatResponse(
                false,
                $maxAttempts,
                $currentCount,
                max(0, $retryAfter)
            );
        }

        $attempts[] = $now;
        cache()->put($cacheKey, $attempts, $decaySeconds);

        $remaining = $maxAttempts - ($currentCount + 1);

        return $this->formatResponse(true, $maxAttempts, $currentCount + 1, 0, $remaining);
    }

    private function formatResponse(
        bool $allowed,
        int $maxAttempts,
        int $attempts,
        int $retryAfter,
        ?int $remaining = null
    ): array {
        return [
            'allowed' => $allowed,
            'limit' => $maxAttempts,
            'attempts' => $attempts,
            'retry_after' => $retryAfter,
            'remaining' => $remaining ?? ($allowed ? $maxAttempts - $attempts : 0),
        ];
    }

    private function logRateLimitExceeded(
        string $key,
        int $currentCount,
        int $maxAttempts,
        int $retryAfter
    ): void {
        if (!($this->config['log']['enabled'] ?? true)) {
            return;
        }

        Log::channel($this->config['log']['channel'] ?? 'daily')->warning('Rate limit exceeded', [
            'key' => $key,
            'attempts' => $currentCount,
            'limit' => $maxAttempts,
            'retry_after' => $retryAfter,
        ]);
    }

    public function clear(string $key): void
    {
        if ($this->driver === 'redis') {
            $prefix = $this->config['redis']['prefix'] ?? 'ratelimit:';
            $fullKey = $prefix . $key;
            $connection = $this->config['redis']['connection'] ?? 'default';
            Redis::connection($connection)->del($fullKey);
        } else {
            cache()->forget("ratelimit:{$key}");
        }
    }

    public function getLimit(string $group): array
    {
        $limits = $this->config['limits'] ?? [];

        if (isset($limits[$group])) {
            return [
                'max_requests' => $limits[$group]['max_requests'] ?? 60,
                'decay_seconds' => $limits[$group]['decay_seconds'] ?? 60,
            ];
        }

        return [
            'max_requests' => 60,
            'decay_seconds' => 60,
        ];
    }
}