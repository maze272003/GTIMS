# GTIMS Tenant Cutover Sign-off Checklist

> **Last Updated:** 2026-02-27
> **Scope:** Country-wide multi-tenant SaaS — all PH provinces (82) and barangays (42,000+) as first-class tenants.

### Tenant Hierarchy

| Role | Scope |
|------|-------|
| **Moderator** | Platform — manages subscriptions, SaaS settings, creates/manages Super Admins |
| **Super Admin** | Province — handler for assigned province; manages Admins and users |
| **Admin** | Barangay — manages operations within a single barangay |
| **Staff / Users** | Barangay — strictly isolated tenant data |

---

## 1. Pre-Deployment

- [x] Run `php artisan migrate --force` in staging and production maintenance windows.
- [x] Seed all PH provinces and barangays via `DemoSeeder`:
  - `php artisan db:seed --class=DemoSeeder`
  - Verify all 82 provinces and barangays seeded with unique stable slugs.
  - Verify Moderator, Super Admin, Admin accounts created.
  - Verify demo data seeded for configured provinces with correct `province_id` + `barangay_id`.
  - Verify seeders are idempotent (rerun produces no duplicates).
- [x] Create tenant memberships and scoped role assignments:
  - Moderator → `scope_type = 'platform'`
  - Super Admin → `scope_type = 'province'`, `scope_id = {province_id}`
  - Admin → `scope_type = 'barangay'`, `scope_id = {barangay_id}`
- [x] Run `php artisan tenant:migration backfill --dry-run` then `php artisan tenant:migration backfill`.
- [x] Run reconciliation and null scans:
  - `php artisan tenant:migration reconcile`
  - `php artisan tenant:migration null-scan`
- [x] Validate canonical slugs:
  - `php artisan tenant:validate-slugs`
- [x] Validate queue deployment prerequisites:
  - `php artisan tenant:queue:validate-deploy`
- [x] Confirm feature flag rollback switches:
  - `FEATURE_TENANT_ROUTES`
  - `FEATURE_TENANT_EXPORTS`
  - `FEATURE_TENANT_NOTIFICATIONS`
  - `FEATURE_API_ACCESS`
- [x] Run quality cycle:
  - `composer dump-autoload`
  - `php vendor/bin/phpunit --stop-on-failure`
  - `php artisan tenant:smoke-test`

## 2. Deployment Window

- [x] Enter maintenance mode.
- [x] Run DB migrations.
- [x] Apply RBAC sync (`php artisan tenant:sync-rbac`).
- [x] Run `php artisan db:seed --class=DemoSeeder` (idempotent, safe in deploy).
- [x] Run reconciliation and health checks:
  - `php artisan tenant:migration reconcile`
  - `php artisan tenant:health-check`
- [x] Validate tenant and moderator routes across multiple provinces:
  - `/{provinceSlug}/{barangaySlug}/dashboard`
  - `/moderator/dashboard`
  - No non-tenant URLs like `/admin/dashboard`.
- [x] Drain and restart queue workers after deploy.
- [x] Enable production monitoring.

## 3. Post-Deployment Validation

- [x] Monitor health checks and failed jobs dashboards.
- [x] Review security logs for cross-tenant access attempts.
- [x] Verify tenant-scoped export storage and headers.
- [x] Verify password reset/invite/email verification links by tenant slug.
- [x] Verify session invalidation after role/membership changes.
- [x] Verify API token tenant claim matching.
- [x] Confirm rollback toggles can be flipped within 5 minutes.
- [x] Verify Super Admin can only manage Admins/users within assigned province.
- [x] Verify cross-tenant isolation between provinces and between barangays.
- [x] Verify seeder integrity: `php artisan tenant:migration null-scan`.
- [x] Run quality cycle passes:
  - `php vendor/bin/phpunit --stop-on-failure`
  - `php artisan tenant:smoke-test`

## 4. Rollback Trigger Criteria

- [x] Cross-tenant leakage confirmed in production.
- [x] Sustained queue failures for tenant-aware jobs > 10 minutes.
- [x] Tenant login failure rate > 20% after cutover.
- [x] Reconciliation detects unexpected null tenant keys on new writes.
- [x] Super Admin able to access provinces outside assigned scope.
- [x] Seeder integrity check fails (orphan records or missing tenant keys).

## 5. Rollback Execution Plan

- [x] Enable maintenance mode.
- [x] Disable tenant feature flags (`FEATURE_TENANT_*`).
- [x] Deploy previous known-good app release.
- [x] Restore DB snapshot if data integrity is impacted.
- [x] Restart queue workers on rollback release.
- [x] Run `php artisan tenant:health-check` and smoke tests before exiting maintenance.
- [x] Run quality cycle to confirm stability.

## 6. References

- `docs/TENANT_OPERATIONS_RUNBOOK.md`
- `docs/TENANT_MIGRATION_PHASE_EXECUTION.md`
- `docs/TENANT_INCIDENT_RESPONSE_SOP.md`
- `docs/MULTI_TENANT_MASTER_CHECKLIST.md`

