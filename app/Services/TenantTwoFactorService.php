<?php

namespace App\Services;

use App\Models\User;
use App\Tenancy\TenantContext;

class TenantTwoFactorService
{
    public function isRequiredForTenant(?TenantContext $tenantContext): bool
    {
        if (!$tenantContext || $tenantContext->isPlatform()) {
            return false;
        }

        return (bool) config('tenancy.features.defaults.require_2fa', false);
    }

    public function enable(User $user): string
    {
        $secret = $this->generateSecret();
        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
        ]);

        return $secret;
    }

    public function disable(User $user): void
    {
        $user->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
        ]);
    }

    public function verify(User $user, string $code, int $window = 1): bool
    {
        if (!$user->two_factor_enabled || !$user->two_factor_secret) {
            return false;
        }

        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $secret = $user->two_factor_secret;
        $timeStep = 30;
        $counter = (int) floor(time() / $timeStep);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->totp($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    protected function generateSecret(int $length = 32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        return collect(range(1, $length))
            ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
            ->implode('');
    }

    protected function totp(string $base32Secret, int $counter): string
    {
        $secret = $this->base32Decode($base32Secret);
        $binaryCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $secret, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    protected function base32Decode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $value = strtoupper($value);
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        foreach (str_split($value) as $char) {
            $index = strpos($alphabet, $char);
            if ($index === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $index;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
