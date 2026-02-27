<?php

namespace App\Services;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\Cache;

class TenantCacheService
{
    protected function tenantNamespace(TenantContext $ctx): string
    {
        if ($ctx->isBarangay()) {
            return "tenant:{$ctx->provinceId}:{$ctx->barangayId}";
        }
        if ($ctx->isProvince()) {
            return "tenant:{$ctx->provinceId}:province";
        }
        return 'platform';
    }

    protected function tenantVersion(TenantContext $ctx): int
    {
        return (int) Cache::get($this->tenantNamespace($ctx) . ':__version', 1);
    }

    public function key(string $key, TenantContext $ctx): string
    {
        return $this->tenantNamespace($ctx) . ':v' . $this->tenantVersion($ctx) . ':' . $key;
    }

    public function remember(TenantContext $ctx, string $key, int $ttl, Closure $callback): mixed
    {
        return Cache::remember($this->key($key, $ctx), $ttl, $callback);
    }

    public function get(TenantContext $ctx, string $key, mixed $default = null): mixed
    {
        return Cache::get($this->key($key, $ctx), $default);
    }

    public function put(TenantContext $ctx, string $key, mixed $value, int $ttl): bool
    {
        return Cache::put($this->key($key, $ctx), $value, $ttl);
    }

    public function forget(TenantContext $ctx, string $key): bool
    {
        return Cache::forget($this->key($key, $ctx));
    }

    public function forgetTenant(TenantContext $ctx): void
    {
        $versionKey = $this->tenantNamespace($ctx) . ':__version';
        Cache::increment($versionKey);

        logger()->info('Tenant cache invalidation requested', [
            'province_id' => $ctx->provinceId,
            'barangay_id' => $ctx->barangayId,
            'scope_type' => $ctx->scopeType,
            'version_key' => $versionKey,
        ]);
    }
}
