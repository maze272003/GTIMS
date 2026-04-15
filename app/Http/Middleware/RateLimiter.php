<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RateLimiter
{
    private const DEFAULT_MAX_REQUESTS = 60;
    private const DEFAULT_DURATION = 1; // minutes

    public function handle(Request $request, Closure $next, string $type = 'api'): Response
    {
        if (!config('ratelimit.enabled', true)) {
            return $next($request);
        }

        $type = $type ?: 'api';
        $config = config("ratelimit.limits.{$type}", config('ratelimit.limits.api'));

        $maxRequests = $config['max_requests'] ?? self::DEFAULT_MAX_REQUESTS;
        $duration = $config['duration'] ?? self::DEFAULT_DURATION;

        $identifier = $this->resolveIdentifier($request);
        $key = "ratelimit:{$type}:{$identifier}";

        $result = $this->attempt($key, $maxRequests, $duration);

        $response = $next($request);

        if (config('ratelimit.headers.enabled', true)) {
            $response->headers->set('X-RateLimit-Limit', $maxRequests);
            $response->headers->set('X-RateLimit-Remaining', max(0, $result['remaining']));
            
            if ($result['limited']) {
                $response->headers->set('Retry-After', $result['retry_after']);
            }
        }

        if (config('ratelimit.debug', false)) {
            $response->headers->set('X-RateLimit-Debug-Type', $type);
            $response->headers->set('X-RateLimit-Debug-Identifier', $identifier);
        }

        if ($result['limited']) {
            Log::warning('Rate limit exceeded', [
                'type' => $type,
                'identifier' => $identifier,
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            $message = $this->getMessage($type, $result['retry_after']);

            return response()->json([
                'message' => $message,
                'retry_after' => $result['retry_after'],
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $maxRequests,
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => $result['retry_after'],
            ]);
        }

        return $response;
    }

    private function attempt(string $key, int $maxRequests, int $duration): array
    {
        try {
            $redis = Redis::connection('default');
            
            $now = microtime(true);
            $windowStart = $now - ($duration * 60);
            $windowKey = "{$key}:window";
            $counterKey = "{$key}:counter";

            $windowData = $redis->zrangebyscore($windowKey, $windowStart, $now);
            
            $requestCount = count($windowData);
            $remaining = $maxRequests - $requestCount;

            if ($requestCount >= $maxRequests) {
                $oldestTimestamp = min($windowData);
                $retryAfter = ceil(($oldestTimestamp + ($duration * 60) - $now));

                return [
                    'limited' => true,
                    'remaining' => 0,
                    'retry_after' => max(1, (int)$retryAfter),
                ];
            }

            $redis->zadd($windowKey, $now, uniqid('rl:', true));
            $redis->expire($windowKey, $duration * 60);

            return [
                'limited' => false,
                'remaining' => $remaining - 1,
                'retry_after' => 0,
            ];
        } catch (\Exception $e) {
            Log::error('Rate limiter Redis error, falling back to file-based', [
                'error' => $e->getMessage(),
                'key' => $key,
            ]);

            if (config('ratelimit.file_based_fallback', false)) {
                return $this->fileBasedAttempt($key, $maxRequests, $duration);
            }

            return [
                'limited' => false,
                'remaining' => $maxRequests,
                'retry_after' => 0,
            ];
        }
    }

    private function fileBasedAttempt(string $key, int $maxRequests, int $duration): array
    {
        $hash = md5($key);
        $file = storage_path("ratelimit/{$hash[0]}/{$hash}.json");
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $now = time();
        $windowStart = $now - ($duration * 60);

        $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        $data = array_filter($data, fn($timestamp) => $timestamp > $windowStart);

        $requestCount = count($data);

        if ($requestCount >= $maxRequests) {
            $oldestTimestamp = min($data);
            $retryAfter = ceil(($oldestTimestamp + ($duration * 60) - $now) / 60);

            return [
                'limited' => true,
                'remaining' => 0,
                'retry_after' => max(1, (int)$retryAfter),
            ];
        }

        $data[] = $now;
        file_put_contents($file, json_encode($data));

        return [
            'limited' => false,
            'remaining' => $maxRequests - $requestCount - 1,
            'retry_after' => 0,
        ];
    }

    private function resolveIdentifier(Request $request): string
    {
        $identifier = config('ratelimit.identifier', 'ip');

        if ($identifier === 'user' && auth()->check()) {
            return 'user:' . auth()->id();
        }

        $ipHeaders = config('ratelimit.ip_detection.headers', [
            'X-Forwarded-For',
            'X-Real-IP',
            'X-Cluster-Client-IP',
        ]);

        foreach ($ipHeaders as $header) {
            $ip = $request->header($header);
            if ($ip) {
                return 'ip:' . explode(',', $ip)[0];
            }
        }

        return 'ip:' . ($request->ip() ?? 'unknown');
    }

    private function getMessage(string $type, int $retryAfter): string
    {
        $messages = config('ratelimit.messages', []);
        $defaultMessage = $messages['default'] ?? 'Too many requests. Please try again later.';

        if (in_array($type, ['auth', 'otp']) && isset($messages[$type])) {
            $minutes = ceil($retryAfter / 60);
            return str_replace(':minutes', (string)$minutes, $messages[$type]);
        }

        return $defaultMessage;
    }
}