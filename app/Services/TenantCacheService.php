<?php

namespace App\Services;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\Cache;

class TenantCacheService
{
    public function key(string $key, TenantContext $ctx): string
    {
        if ($ctx->isBarangay()) {
            return "tenant:{$ctx->provinceId}:{$ctx->barangayId}:{$key}";
        }
        if ($ctx->isProvince()) {
            return "tenant:{$ctx->provinceId}:*:{$key}";
        }
        return "platform:{$key}";
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
        // Pattern-based cache invalidation depends on the cache driver.
        // For drivers that support tags (e.g., Redis, Memcached), use tagged caches.
        // For the array/file driver, this is a no-op; use Cache::flush() as a fallback.
        logger()->info('Tenant cache invalidation requested', [
            'province_id' => $ctx->provinceId,
            'barangay_id' => $ctx->barangayId,
            'scope_type' => $ctx->scopeType,
        ]);
    }
}
