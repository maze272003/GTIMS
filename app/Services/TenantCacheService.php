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
        // Pattern-based invalidation - implementation depends on cache driver
        // For tagged caches, use Cache::tags([$this->tenantTag($ctx)])->flush()
    }
}
