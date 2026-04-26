<?php

namespace App\Http\Middleware;

use App\Services\RateLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    private RateLimitService $rateLimitService;
    private array $config;

    public function __construct(RateLimitService $rateLimitService)
    {
        $this->rateLimitService = $rateLimitService;
        $this->config = config('rate_limit');
    }

    public function handle(Request $request, Closure $next, string $group = 'api'): Response
    {
        if (!$this->rateLimitService->isEnabled()) {
            return $next($request);
        }

        $limitConfig = $this->rateLimitService->getLimit($group);
        $maxRequests = $limitConfig['max_requests'];
        $decaySeconds = $limitConfig['decay_seconds'];

        $identifier = $this->rateLimitService->getIdentifier($request);
        $key = "{$group}:{$identifier}";

        $result = $this->rateLimitService->attempt($key, $maxRequests, $decaySeconds);

        $response = $next($request);

        if (!$result['allowed']) {
            $response = $this->buildRateLimitedResponse($request, $result, $group);
        }

        return $this->addRateLimitHeaders($response, $result);
    }

    private function buildRateLimitedResponse(Request $request, array $result, string $group): Response
    {
        $statusCode = $this->config['response']['status_code'] ?? 429;
        $message = $this->config['response']['message'] ?? 'Too many requests. Please try again later.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
                'rate_limit' => [
                    'group' => $group,
                    'limit' => $result['limit'],
                    'remaining' => 0,
                    'retry_after' => $result['retry_after'],
                ],
            ], $statusCode);
        }

        if ($this->config['debug'] ?? false) {
            return response()->view('errors.rate_limit', [
                'message' => $message,
                'limit' => $result['limit'],
                'retry_after' => $result['retry_after'],
                'group' => $group,
            ], $statusCode);
        }

        return response($message, $statusCode);
    }

    private function addRateLimitHeaders(Response $response, array $result): Response
    {
        $response->headers->set('X-RateLimit-Limit', (string) $result['limit']);
        $response->headers->set('X-RateLimit-Remaining', (string) $result['remaining']);

        if ($result['retry_after'] > 0) {
            $response->headers->set('Retry-After', (string) $result['retry_after']);
        }

        return $response;
    }
}