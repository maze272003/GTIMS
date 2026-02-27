# GTIMS Tenant Cutover Sign-off Checklist

## 1. Pre-Deployment

- [x] Run `php artisan migrate --force` in staging and production maintenance windows.
- [x] Seed province/barangay reference records.
- [x] Create tenant memberships and scoped role assignments.
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

## 2. Deployment Window

- [x] Enter maintenance mode.
- [x] Run DB migrations.
- [x] Apply RBAC sync (`php artisan tenant:sync-rbac`).
- [x] Run reconciliation and health checks:
  - `php artisan tenant:migration reconcile`
  - `php artisan tenant:health-check`
- [x] Validate tenant and moderator routes.
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

## 4. Rollback Trigger Criteria

- [x] Cross-tenant leakage confirmed in production.
- [x] Sustained queue failures for tenant-aware jobs > 10 minutes.
- [x] Tenant login failure rate > 20% after cutover.
- [x] Reconciliation detects unexpected null tenant keys on new writes.

## 5. Rollback Execution Plan

- [x] Enable maintenance mode.
- [x] Disable tenant feature flags (`FEATURE_TENANT_*`).
- [x] Deploy previous known-good app release.
- [x] Restore DB snapshot if data integrity is impacted.
- [x] Restart queue workers on rollback release.
- [x] Run `php artisan tenant:health-check` and smoke tests before exiting maintenance.

