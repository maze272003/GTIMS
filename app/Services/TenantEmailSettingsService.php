<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\Province;
use App\Tenancy\TenantContext;

class TenantEmailSettingsService
{
    public function get(TenantContext $tenantContext): array
    {
        if ($tenantContext->isBarangay()) {
            $barangay = Barangay::query()->find($tenantContext->barangayId);
            $province = Province::query()->find($tenantContext->provinceId);

            $barangaySettings = (array) ($barangay?->settings_json['email'] ?? []);
            $provinceSettings = (array) ($province?->settings_json['email'] ?? []);

            return array_merge($provinceSettings, $barangaySettings);
        }

        $province = Province::query()->find($tenantContext->provinceId);
        return (array) ($province?->settings_json['email'] ?? []);
    }

    public function update(TenantContext $tenantContext, array $settings): void
    {
        if ($tenantContext->isBarangay()) {
            $barangay = Barangay::query()->findOrFail($tenantContext->barangayId);
            $json = (array) $barangay->settings_json;
            $json['email'] = $settings;
            $barangay->update(['settings_json' => $json]);
            return;
        }

        $province = Province::query()->findOrFail($tenantContext->provinceId);
        $json = (array) $province->settings_json;
        $json['email'] = $settings;
        $province->update(['settings_json' => $json]);
    }

    public function apply(TenantContext $tenantContext): void
    {
        $settings = $this->get($tenantContext);
        if (empty($settings)) {
            return;
        }

        if (!empty($settings['from_address'])) {
            config(['mail.from.address' => $settings['from_address']]);
        }

        if (!empty($settings['from_name'])) {
            config(['mail.from.name' => $settings['from_name']]);
        }
    }
}

