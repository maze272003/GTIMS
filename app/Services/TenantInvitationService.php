<?php

namespace App\Services;

use App\Mail\TenantInvitationMail;
use App\Models\RoleAssignment;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TenantInvitationService
{
    public function create(TenantContext $ctx, string $email, int $roleId): TenantInvitation
    {
        $expireDays = config('tenancy.invitation.expires_days', 7);

        $invitation = TenantInvitation::create([
            'province_id' => $ctx->provinceId,
            'barangay_id' => $ctx->barangayId,
            'email' => $email,
            'role_id' => $roleId,
            'token' => Str::random(64),
            'invited_by' => Auth::id(),
            'status' => 'pending',
            'expires_at' => now()->addDays($expireDays),
        ]);

        $invitation->loadMissing(['province', 'barangay', 'role']);

        $invitationUrl = route('login');
        if ($invitation->province?->slug && $invitation->barangay?->slug) {
            $invitationUrl = route('tenant.invite.accept', [
                'provinceSlug' => $invitation->province->slug,
                'barangaySlug' => $invitation->barangay->slug,
                'token' => $invitation->token,
            ]);
        }

        try {
            Mail::to($email)->send(new TenantInvitationMail($invitation, $invitationUrl));
        } catch (\Throwable $exception) {
            Log::channel('security')->warning('Failed to send tenant invitation email', [
                'invitation_id' => $invitation->id,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }

        return $invitation;
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
