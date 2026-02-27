<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantModelScope
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var TenantContext|null $tenantContext */
        $tenantContext = $request->attributes->get('tenantContext');

        if (!$tenantContext || $tenantContext->isPlatform()) {
            return $next($request);
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter => $value) {
            if (!$value instanceof Model) {
                continue;
            }

            if (!$value->getAttribute('province_id')) {
                continue;
            }

            if (TenantScope::modelBelongsToTenant($value, $tenantContext)) {
                continue;
            }

            Log::channel('security')->warning('Blocked cross-tenant route model access.', [
                'route' => $request->route()?->getName(),
                'parameter' => $parameter,
                'model' => get_class($value),
                'model_id' => $value->getKey(),
                'tenant_scope' => $tenantContext->scopeType,
                'tenant_province_id' => $tenantContext->provinceId,
                'tenant_barangay_id' => $tenantContext->barangayId,
                'user_id' => $request->user()?->id,
            ]);

            abort(403, 'The selected resource does not belong to your tenant scope.');
        }

        return $next($request);
    }
}

