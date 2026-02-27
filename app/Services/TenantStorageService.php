<?php

namespace App\Services;

use App\Tenancy\TenantContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class TenantStorageService
{
    protected function disk(): Filesystem
    {
        return Storage::disk((string) config('tenancy.storage.disk', 'local'));
    }

    protected function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#\.\.+#', '', $path) ?? $path;

        return ltrim($path, '/');
    }

    public function tenantPath(string $path, TenantContext $ctx): string
    {
        $path = $this->normalizeRelativePath($path);

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
        $this->disk()->put($fullPath, $content);
        return $fullPath;
    }

    public function get(string $path, TenantContext $ctx): ?string
    {
        $fullPath = $this->tenantPath($path, $ctx);
        if (!$this->belongsToTenant($fullPath, $ctx)) {
            return null;
        }
        if (!$this->disk()->exists($fullPath)) {
            return null;
        }
        return $this->disk()->get($fullPath);
    }

    public function delete(string $path, TenantContext $ctx): bool
    {
        $fullPath = $this->tenantPath($path, $ctx);
        if (!$this->belongsToTenant($fullPath, $ctx)) {
            return false;
        }

        return $this->disk()->delete($fullPath);
    }

    public function exists(string $path, TenantContext $ctx): bool
    {
        $fullPath = $this->tenantPath($path, $ctx);
        if (!$this->belongsToTenant($fullPath, $ctx)) {
            return false;
        }

        return $this->disk()->exists($fullPath);
    }

    public function url(string $path, TenantContext $ctx): string
    {
        return $this->disk()->url($this->tenantPath($path, $ctx));
    }

    public function temporaryDownloadUrl(string $path, TenantContext $ctx, int $minutes = 15): string
    {
        $fullPath = $this->tenantPath($path, $ctx);

        try {
            return Storage::disk((string) config('tenancy.storage.disk', 'local'))
                ->temporaryUrl($fullPath, now()->addMinutes($minutes));
        } catch (\Throwable) {
            if (!$ctx->provinceSlug || !$ctx->barangaySlug || !Route::has('tenant.storage.download')) {
                return $this->disk()->url($fullPath);
            }

            return URL::temporarySignedRoute(
                'tenant.storage.download',
                now()->addMinutes($minutes),
                [
                    'provinceSlug' => $ctx->provinceSlug,
                    'barangaySlug' => $ctx->barangaySlug,
                    'path' => base64_encode($fullPath),
                ]
            );
        }
    }

    public function belongsToTenant(string $fullPath, TenantContext $ctx): bool
    {
        $prefix = rtrim($this->tenantPath('', $ctx), '/');
        return str_starts_with($fullPath, $prefix);
    }
}
