<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\RoleAssignment;
use App\Models\TenantMembership;
use App\Models\TenantRole;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TenantSyncRbacCommand extends Command
{
    protected $signature = 'tenant:sync-rbac {--dry-run : Show planned changes without writing}';

    protected $description = 'Map legacy user_levels to scoped tenant roles and populate role assignments/memberships.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Running RBAC sync in dry-run mode.' : 'Running RBAC sync.');

        $stats = DB::transaction(function () use ($dryRun) {
            [$moderatorRole, $provinceRole, $barangayRole] = $this->ensureSystemRoles($dryRun);
            $this->syncRolePermissions($moderatorRole, $provinceRole, $barangayRole, $dryRun);

            return $this->syncUsers($moderatorRole, $provinceRole, $barangayRole, $dryRun);
        });

        $this->table(['Metric', 'Value'], [
            ['roles_created_or_updated', $stats['roles']],
            ['role_permission_links', $stats['role_permissions']],
            ['memberships_created', $stats['memberships']],
            ['role_assignments_created', $stats['assignments']],
            ['users_skipped_no_scope', $stats['skipped']],
        ]);

        $this->info('RBAC sync complete.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: TenantRole, 1: TenantRole, 2: TenantRole}
     */
    protected function ensureSystemRoles(bool $dryRun): array
    {
        $rolesConfig = config('tenancy.roles');

        $upsert = function (string $key) use ($rolesConfig, $dryRun): TenantRole {
            $payload = $rolesConfig[$key];

            if ($dryRun) {
                return new TenantRole([
                    'name' => $payload['name'],
                    'slug' => $payload['slug'],
                    'scope_type' => $payload['scope_type'],
                    'is_system_role' => true,
                ]);
            }

            return TenantRole::updateOrCreate(
                ['slug' => $payload['slug']],
                [
                    'name' => $payload['name'],
                    'scope_type' => $payload['scope_type'],
                    'is_system_role' => true,
                ]
            );
        };

        return [$upsert('moderator'), $upsert('province_admin'), $upsert('barangay_admin')];
    }

    protected function syncRolePermissions(
        TenantRole $moderatorRole,
        TenantRole $provinceRole,
        TenantRole $barangayRole,
        bool $dryRun
    ): void {
        $superadminPermissions = $this->permissionsForLegacyLevel('superadmin');
        $adminPermissions = $this->permissionsForLegacyLevel('admin');

        if ($superadminPermissions->isEmpty()) {
            $superadminPermissions = Permission::query()->pluck('id');
        }

        if ($adminPermissions->isEmpty()) {
            $adminPermissions = Permission::query()->pluck('id');
        }

        if ($dryRun) {
            return;
        }

        $moderatorRole->permissions()->syncWithoutDetaching($superadminPermissions->all());
        $provinceRole->permissions()->syncWithoutDetaching($adminPermissions->all());
        $barangayRole->permissions()->syncWithoutDetaching($adminPermissions->all());
    }

    /**
     * @return array{roles:int, role_permissions:int, memberships:int, assignments:int, skipped:int}
     */
    protected function syncUsers(
        TenantRole $moderatorRole,
        TenantRole $provinceRole,
        TenantRole $barangayRole,
        bool $dryRun
    ): array {
        $memberships = 0;
        $assignments = 0;
        $skipped = 0;

        User::query()->with('level')->chunkById(200, function ($users) use (
            $moderatorRole,
            $provinceRole,
            $barangayRole,
            $dryRun,
            &$memberships,
            &$assignments,
            &$skipped
        ) {
            foreach ($users as $user) {
                $levelName = strtolower((string) optional($user->level)->name);

                if ($levelName === 'superadmin' || $user->isModerator()) {
                    if (!$dryRun) {
                        TenantMembership::firstOrCreate(
                            ['user_id' => $user->id, 'scope_type' => 'platform', 'scope_id' => null],
                            ['is_primary' => true, 'status' => 'active']
                        );

                        RoleAssignment::firstOrCreate([
                            'user_id' => $user->id,
                            'role_id' => $moderatorRole->id,
                            'scope_type' => 'platform',
                            'scope_id' => null,
                        ]);
                    }

                    $memberships++;
                    $assignments++;
                    continue;
                }

                $provinceId = $user->province_id;
                $barangayId = $user->barangay_id;

                if ($barangayId) {
                    if (!$dryRun) {
                        TenantMembership::firstOrCreate(
                            ['user_id' => $user->id, 'scope_type' => 'barangay', 'scope_id' => $barangayId],
                            ['is_primary' => true, 'status' => 'active']
                        );

                        RoleAssignment::firstOrCreate([
                            'user_id' => $user->id,
                            'role_id' => $barangayRole->id,
                            'scope_type' => 'barangay',
                            'scope_id' => $barangayId,
                        ]);
                    }

                    $memberships++;
                    $assignments++;
                    continue;
                }

                if ($provinceId) {
                    if (!$dryRun) {
                        TenantMembership::firstOrCreate(
                            ['user_id' => $user->id, 'scope_type' => 'province', 'scope_id' => $provinceId],
                            ['is_primary' => true, 'status' => 'active']
                        );

                        RoleAssignment::firstOrCreate([
                            'user_id' => $user->id,
                            'role_id' => $provinceRole->id,
                            'scope_type' => 'province',
                            'scope_id' => $provinceId,
                        ]);
                    }

                    $memberships++;
                    $assignments++;
                    continue;
                }

                $skipped++;
            }
        });

        return [
            'roles' => 3,
            'role_permissions' => 0, // computed by sync without deterministic delta
            'memberships' => $memberships,
            'assignments' => $assignments,
            'skipped' => $skipped,
        ];
    }

    protected function permissionsForLegacyLevel(string $levelName)
    {
        $level = UserLevel::query()->where('name', $levelName)->first();

        if (!$level) {
            return collect();
        }

        return $level->permissions()->pluck('permissions.id');
    }
}

