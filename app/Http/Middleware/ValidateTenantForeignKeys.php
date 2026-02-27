<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateTenantForeignKeys
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var TenantContext|null $tenantContext */
        $tenantContext = $request->attributes->get('tenantContext');
        $rules = (array) config('tenancy.tenant_fk_validation', []);

        if (!$tenantContext || $tenantContext->isPlatform() || empty($rules)) {
            return $next($request);
        }

        $payload = $request->all();

        foreach ($rules as $inputPath => $table) {
            $ids = $this->extractIdsFromInputPath($payload, (string) $inputPath);
            if (empty($ids)) {
                continue;
            }

            $query = DB::table((string) $table)->whereIn('id', $ids);
            if (TenantScope::tenantColumnsExist((string) $table)) {
                $query->where('province_id', (int) $tenantContext->provinceId);

                if ($tenantContext->isBarangay()) {
                    $query->where('barangay_id', (int) $tenantContext->barangayId);
                }
            }

            $count = (int) $query->count();
            if ($count === count($ids)) {
                continue;
            }

            Log::channel('security')->warning('Blocked request containing cross-tenant foreign key.', [
                'route' => $request->route()?->getName(),
                'input_path' => $inputPath,
                'table' => $table,
                'ids' => $ids,
                'tenant_scope' => $tenantContext->scopeType,
                'tenant_province_id' => $tenantContext->provinceId,
                'tenant_barangay_id' => $tenantContext->barangayId,
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'message' => 'One or more referenced records are outside the current tenant scope.',
                'field' => $inputPath,
            ], 422);
        }

        return $next($request);
    }

    /**
     * Extract numeric IDs from dot/wildcard input paths (e.g. items.*.inventory_id).
     *
     * @return int[]
     */
    protected function extractIdsFromInputPath(array $payload, string $path): array
    {
        $value = Arr::get($payload, $path);
        if ($value === null && str_contains($path, '*')) {
            $value = data_get($payload, $path);
        }

        if ($value === null) {
            return [];
        }

        return collect(Arr::flatten((array) $value))
            ->filter(fn ($item) => is_numeric($item) && (int) $item > 0)
            ->map(fn ($item) => (int) $item)
            ->unique()
            ->values()
            ->all();
    }
}

