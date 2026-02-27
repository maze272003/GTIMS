<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenantLogContext
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var TenantContext|null $tenantContext */
        $tenantContext = $request->attributes->get('tenantContext');

        if (!$tenantContext) {
            $tenantContext = TenantContext::fromSession([
                'tenant.scope_type' => $request->session()->get('tenant.scope_type'),
                'tenant.province_id' => $request->session()->get('tenant.province_id'),
                'tenant.barangay_id' => $request->session()->get('tenant.barangay_id'),
                'tenant.route_slug_province' => $request->session()->get('tenant.route_slug_province'),
                'tenant.route_slug_barangay' => $request->session()->get('tenant.route_slug_barangay'),
            ]);
        }

        $context = [
            'request_id' => $request->headers->get('X-Request-ID'),
            'ip' => $request->ip(),
            'route_name' => optional($request->route())->getName(),
            'method' => $request->method(),
            'path' => '/' . ltrim($request->path(), '/'),
            'user_id' => $request->user()?->id,
        ];

        if ($tenantContext) {
            $context['tenant_province_id'] = $tenantContext->provinceId;
            $context['tenant_barangay_id'] = $tenantContext->barangayId;
            $context['tenant_scope'] = $tenantContext->scopeType;
            $context['tenant_province_slug'] = $tenantContext->provinceSlug;
            $context['tenant_barangay_slug'] = $tenantContext->barangaySlug;
        }

        Log::withContext($context);

        return $next($request);
    }
}

