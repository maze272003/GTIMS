<?php

namespace App\Jobs;

use App\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class TenantAwareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?int $tenantProvinceId;
    public ?int $tenantBarangayId;
    public string $tenantScopeType;
    public ?string $tenantProvinceSlug;
    public ?string $tenantBarangaySlug;

    public function __construct(TenantContext $ctx)
    {
        $this->tenantProvinceId = $ctx->provinceId;
        $this->tenantBarangayId = $ctx->barangayId;
        $this->tenantScopeType = $ctx->scopeType;
        $this->tenantProvinceSlug = $ctx->provinceSlug;
        $this->tenantBarangaySlug = $ctx->barangaySlug;
    }

    public function getTenantContext(): ?TenantContext
    {
        if ($this->tenantScopeType !== 'platform' && !$this->tenantProvinceId) {
            return null;
        }

        if ($this->tenantScopeType === 'barangay' && !$this->tenantBarangayId) {
            return null;
        }

        return new TenantContext(
            $this->tenantScopeType,
            $this->tenantProvinceId,
            $this->tenantBarangayId,
            $this->tenantProvinceSlug,
            $this->tenantBarangaySlug,
        );
    }

    public function middleware(): array
    {
        return [new \App\Jobs\Middleware\SetTenantContextMiddleware()];
    }
}
