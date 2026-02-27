<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TenantSessionSecurityService
{
    public function invalidateAfterMembershipChange(int $userId): void
    {
        if (!config('tenancy.session_security.invalidate_on_membership_change', true)) {
            return;
        }

        $this->invalidateUserSessions($userId, 'membership_changed');
    }

    public function invalidateAfterRoleChange(int $userId): void
    {
        if (!config('tenancy.session_security.invalidate_on_role_change', true)) {
            return;
        }

        $this->invalidateUserSessions($userId, 'role_changed');
    }

    protected function invalidateUserSessions(int $userId, string $reason): void
    {
        if (config('session.driver') === 'database') {
            $table = (string) config('session.table', 'sessions');

            if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                DB::table($table)->where('user_id', $userId)->delete();
            }
        }

        Cache::forget("auth:permissions:user:{$userId}");
        Cache::forget("auth:memberships:user:{$userId}");

        Log::channel('security')->warning('User sessions invalidated after tenancy security change', [
            'user_id' => $userId,
            'reason' => $reason,
        ]);
    }
}

