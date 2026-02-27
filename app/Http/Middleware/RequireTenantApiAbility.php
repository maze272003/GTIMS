<?php

namespace App\Http\Middleware;

use App\Models\TenantApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenantApiAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        /** @var TenantApiToken|null $token */
        $token = $request->attributes->get('apiToken');
        if (!$token) {
            return response()->json(['message' => 'Missing API token context.'], 401);
        }

        $abilities = $token->abilities ?? [];
        if (!in_array('*', $abilities, true) && !in_array($ability, $abilities, true)) {
            return response()->json(['message' => 'Token lacks required ability.'], 403);
        }

        return $next($request);
    }
}

