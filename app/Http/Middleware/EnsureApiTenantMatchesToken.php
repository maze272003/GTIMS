<?php

namespace App\Http\Middleware;

use App\Models\TenantApiToken;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTenantMatchesToken
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var TenantApiToken|null $token */
        $token = $request->attributes->get('apiToken');
        /** @var TenantContext|null $tenantContext */
        $tenantContext = $request->attributes->get('tenantContext');

        if (!$token || !$tenantContext) {
            return response()->json(['message' => 'Missing API token or tenant context.'], 401);
        }

        $tokenProvince = (int) ($token->province_id ?? 0);
        $tokenBarangay = (int) ($token->barangay_id ?? 0);
        $routeProvince = (int) ($tenantContext->provinceId ?? 0);
        $routeBarangay = (int) ($tenantContext->barangayId ?? 0);

        if ($tokenProvince !== $routeProvince) {
            return response()->json(['message' => 'API token does not match request tenant province.'], 403);
        }

        if ($tokenBarangay !== 0 && $tokenBarangay !== $routeBarangay) {
            return response()->json(['message' => 'API token does not match request tenant barangay.'], 403);
        }

        return $next($request);
    }
}

