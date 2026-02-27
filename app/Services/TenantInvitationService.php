<?php

namespace App\Services;

use App\Models\RoleAssignment;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantInvitationService
{
    public function create(TenantContext $ctx, string $email, int $roleId): TenantInvitation
    {
        $expireDays = config('tenancy.invitation.expires_days', 7);

        return TenantInvitation::create([
            'province_id' => $ctx->provinceId,
            'barangay_id' => $ctx->barangayId,
            'email' => $email,
            'role_id' => $roleId,
            'token' => Str::random(64),
            'invited_by' => Auth::id(),
            'status' => 'pending',
            'expires_at' => now()->addDays($expireDays),
        ]);
    }

    public function accept(string $token, User $user): TenantInvitation
    {
        $invitation = TenantInvitation::where('token', $token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        DB::transaction(function () use ($invitation, $user) {
            $scopeType = $invitation->barangay_id ? 'barangay' : 'province';
            $scopeId = $invitation->barangay_id ?? $invitation->province_id;

            TenantMembership::firstOrCreate([
                'user_id' => $user->id,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
            ], [
                'status' => 'active',
                'is_primary' => false,
            ]);

            RoleAssignment::firstOrCreate([
                'user_id' => $user->id,
                'role_id' => $invitation->role_id,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
            ]);

            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'accepted_by' => $user->id,
            ]);
        });

        return $invitation->fresh();
    }

    public function cancel(TenantInvitation $invitation): bool
    {
        if ($invitation->status !== 'pending') {
            return false;
        }
        return $invitation->update(['status' => 'cancelled']);
    }

    public function expireOld(): int
    {
        return TenantInvitation::where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }
}
