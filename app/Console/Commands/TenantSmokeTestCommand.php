<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\Province;
use App\Models\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class TenantSmokeTestCommand extends Command
{
    protected $signature = 'tenant:smoke-test';

    protected $description = 'Run quick tenant readiness checks (slug resolution, route names, and membership boundaries).';

    public function __construct(protected TenantResolver $tenantResolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $rows = [];
        $failed = false;

        $check = function (string $name, bool $ok, string $detail) use (&$rows, &$failed): void {
            $rows[] = [$name, $ok ? 'PASS' : 'FAIL', $detail];
            $failed = $failed || !$ok;
        };

        $check(
            'route_name_tenant.dashboard',
            Route::has('tenant.dashboard'),
            Route::has('tenant.dashboard') ? 'Route name exists.' : 'Missing route name.'
        );

        $check(
            'route_name_moderator.dashboard',
            Route::has('moderator.dashboard'),
            Route::has('moderator.dashboard') ? 'Route name exists.' : 'Missing route name.'
        );

        $pair = Barangay::query()
            ->with('province')
            ->whereNotNull('slug')
            ->where('is_active', true)
            ->whereHas('province', fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->first();

        if (!$pair || !$pair->province) {
            $check('active_slug_pair_available', false, 'No active province/barangay slug pair found.');
            $this->table(['Check', 'Status', 'Detail'], $rows);
            return self::FAILURE;
        }

        $check(
            'active_slug_pair_available',
            true,
            "{$pair->province->slug}/{$pair->slug}"
        );

        $ctx = $this->tenantResolver->fromSlugs($pair->province->slug, $pair->slug);
        $check(
            'slug_resolution',
            $ctx !== null,
            $ctx ? "Resolved province_id={$ctx->provinceId}, barangay_id={$ctx->barangayId}" : 'Resolver returned null.'
        );

        $invalidCtx = $this->tenantResolver->fromSlugs('__invalid__', '__invalid__');
        $check(
            'invalid_slug_rejection',
            $invalidCtx === null,
            $invalidCtx === null ? 'Invalid slugs rejected.' : 'Invalid slugs unexpectedly resolved.'
        );

        $this->checkMembershipBoundary($check);

        $this->table(['Check', 'Status', 'Detail'], $rows);

        if ($failed) {
            $this->error('Tenant smoke test failed.');
            return self::FAILURE;
        }

        $this->info('Tenant smoke test passed.');
        return self::SUCCESS;
    }

    /**
     * @param callable(string,bool,string):void $check
     */
    protected function checkMembershipBoundary(callable $check): void
    {
        $membership = TenantMembership::query()
            ->where('scope_type', 'barangay')
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->whereNotNull('user_level_id'))
            ->orderBy('id')
            ->first();

        if (!$membership) {
            $check('cross_tenant_membership_denial', true, 'Skipped (no active barangay membership to validate).');
            return;
        }

        $user = User::query()->find($membership->user_id);
        if (!$user) {
            $check('cross_tenant_membership_denial', true, 'Skipped (membership user not found).');
            return;
        }

        $sourceBarangay = Barangay::query()
            ->with('province')
            ->where('id', (int) $membership->scope_id)
            ->whereNotNull('slug')
            ->where('is_active', true)
            ->whereHas('province', fn ($query) => $query->where('is_active', true))
            ->first();

        if (!$sourceBarangay || !$sourceBarangay->province) {
            $check('cross_tenant_membership_denial', true, 'Skipped (membership tenant has no active slug route).');
            return;
        }

        $other = Barangay::query()
            ->with('province')
            ->where('id', '!=', $sourceBarangay->id)
            ->whereNotNull('slug')
            ->where('is_active', true)
            ->whereHas('province', fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->first();

        if (!$other || !$other->province) {
            $check('cross_tenant_membership_denial', true, 'Skipped (requires at least two active tenant routes).');
            return;
        }

        $targetCtx = $this->tenantResolver->fromSlugs($other->province->slug, $other->slug);
        if (!$targetCtx) {
            $check('cross_tenant_membership_denial', false, 'Second tenant slug pair failed to resolve.');
            return;
        }

        $denied = !$this->tenantResolver->userHasMembership($user, $targetCtx);
        $detail = $denied
            ? "User #{$user->id} denied for {$other->province->slug}/{$other->slug}."
            : "User #{$user->id} unexpectedly has access to another tenant.";

        $check('cross_tenant_membership_denial', $denied, $detail);
    }
}
