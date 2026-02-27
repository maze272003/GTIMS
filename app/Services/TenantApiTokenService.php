<?php

namespace App\Services;

use App\Models\TenantApiToken;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;

class TenantApiTokenService
{
    /**
     * Create a tenant-scoped API token and return the plain-text value once.
     *
     * @return array{token: string, record: TenantApiToken}
     */
    public function createToken(
        User $user,
        string $name,
        ?TenantContext $tenantContext,
        array $abilities = ['*'],
        ?int $ttlMinutes = null
    ): array {
        $plainToken = Str::random(64);
        $hash = hash('sha256', $plainToken);
        $ttl = $ttlMinutes ?: (int) config('tenancy.api.token_ttl_minutes', 1440);

        $record = TenantApiToken::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => $hash,
            'province_id' => $tenantContext?->provinceId,
            'barangay_id' => $tenantContext?->barangayId,
            'abilities' => $abilities,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        return [
            'token' => $plainToken,
            'record' => $record,
        ];
    }

    public function findByPlainText(string $plainToken): ?TenantApiToken
    {
        if ($plainToken === '') {
            return null;
        }

        return TenantApiToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();
    }
}

