<?php

namespace App\Services;

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;

class TenantStorageService
{
    public function tenantPath(string $path, TenantContext $ctx): string
    {
        if ($ctx->isBarangay()) {
            return "tenants/barangays/{$ctx->provinceId}/{$ctx->barangayId}/{$path}";
        }
        if ($ctx->isProvince()) {
            return "tenants/provinces/{$ctx->provinceId}/{$path}";
        }
        return "tenants/platform/{$path}";
    }

    public function store(string $path, string $content, TenantContext $ctx): string
    {
        $fullPath = $this->tenantPath($path, $ctx);
        Storage::put($fullPath, $content);
        return $fullPath;
    }

    public function get(string $path, TenantContext $ctx): ?string
    {
        $fullPath = $this->tenantPath($path, $ctx);
        if (!Storage::exists($fullPath)) {
            return null;
        }
        return Storage::get($fullPath);
    }

    public function delete(string $path, TenantContext $ctx): bool
    {
        return Storage::delete($this->tenantPath($path, $ctx));
    }

    public function exists(string $path, TenantContext $ctx): bool
    {
        return Storage::exists($this->tenantPath($path, $ctx));
    }

    public function url(string $path, TenantContext $ctx): string
    {
        return Storage::url($this->tenantPath($path, $ctx));
    }
}
