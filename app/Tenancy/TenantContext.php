<?php

namespace App\Tenancy;

use App\Models\Barangay;
use App\Models\Province;

/**
 * Immutable value object representing the current tenant context.
 *
 * Stored in the request lifecycle after tenant resolution.
 * Used by services, repositories, and middleware to scope queries.
 */
final class TenantContext
{
    public function __construct(
        public readonly string $scopeType,
        public readonly ?int $provinceId = null,
        public readonly ?int $barangayId = null,
        public readonly ?string $provinceSlug = null,
        public readonly ?string $barangaySlug = null,
    ) {}

    public static function platform(): self
    {
        return new self(scopeType: 'platform');
    }

    public static function forProvince(Province $province): self
    {
        return new self(
            scopeType: 'province',
            provinceId: $province->id,
            provinceSlug: $province->slug,
        );
    }

    public static function forBarangay(Province $province, Barangay $barangay): self
    {
        return new self(
            scopeType: 'barangay',
            provinceId: $province->id,
            barangayId: $barangay->id,
            provinceSlug: $province->slug,
            barangaySlug: $barangay->slug,
        );
    }

    public function isPlatform(): bool
    {
        return $this->scopeType === 'platform';
    }

    public function isProvince(): bool
    {
        return $this->scopeType === 'province';
    }

    public function isBarangay(): bool
    {
        return $this->scopeType === 'barangay';
    }

    public function toArray(): array
    {
        return [
            'scope_type' => $this->scopeType,
            'province_id' => $this->provinceId,
            'barangay_id' => $this->barangayId,
            'province_slug' => $this->provinceSlug,
            'barangay_slug' => $this->barangaySlug,
        ];
    }

    public function toSessionData(): array
    {
        return [
            'tenant.scope_type' => $this->scopeType,
            'tenant.province_id' => $this->provinceId,
            'tenant.barangay_id' => $this->barangayId,
            'tenant.route_slug_province' => $this->provinceSlug,
            'tenant.route_slug_barangay' => $this->barangaySlug,
        ];
    }

    public static function fromSession(array $sessionData): ?self
    {
        $scopeType = $sessionData['tenant.scope_type'] ?? null;
        if (!$scopeType) {
            return null;
        }

        return new self(
            scopeType: $scopeType,
            provinceId: $sessionData['tenant.province_id'] ?? null,
            barangayId: $sessionData['tenant.barangay_id'] ?? null,
            provinceSlug: $sessionData['tenant.route_slug_province'] ?? null,
            barangaySlug: $sessionData['tenant.route_slug_barangay'] ?? null,
        );
    }
}
