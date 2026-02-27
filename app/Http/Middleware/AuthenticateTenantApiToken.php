<?php

namespace App\Http\Middleware;

use App\Services\TenantApiTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTenantApiToken
{
    public function __construct(
        protected TenantApiTokenService $tokenService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = (string) $request->bearerToken();
        if ($plainToken === '') {
            return response()->json(['message' => 'Missing API bearer token.'], 401);
        }

        $token = $this->tokenService->findByPlainText($plainToken);
        if (!$token || $token->isExpired()) {
            return response()->json(['message' => 'Invalid or expired API token.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        $token->load('user');

        if (!$token->user) {
            return response()->json(['message' => 'API token user is missing.'], 401);
        }

        Auth::setUser($token->user);
        $request->attributes->set('apiToken', $token);

        return $next($request);
    }
}

