# GTIMS Tenant Operations Runbook

## 1. Deployment

1. Enable maintenance mode.
2. Run `php artisan migrate --force`.
3. Run RBAC sync: `php artisan tenant:sync-rbac`.
4. Seed/update tenant records as needed.
5. Run reconciliation:
   - `php artisan tenant:health-check`
   - `php artisan tenant:usage:report`
   - `php artisan tenant:migration reconcile`
   - `php artisan tenant:migration null-scan`
6. Validate routes:
   - `/{provinceSlug}/{barangaySlug}/dashboard`
   - `/moderator/dashboard`
7. Disable maintenance mode.

## 2. Queue Worker Drain / Restart

1. Pause worker supervisor process.
2. Wait for in-flight jobs to finish (`queue:work --stop-when-empty`).
3. Deploy code and run migrations.
4. Restart workers.
5. Check failed jobs and tenant context logs:
   - `php artisan queue:failed`
   - `php artisan tenant:queue:validate-deploy`
   - Inspect `storage/logs/security.log` and `storage/logs/tenant.log`.

## 3. Rollback

1. Re-enable maintenance mode.
2. Roll back app release and database to validated backup.
3. Restart workers with previous release.
4. Run `php artisan tenant:health-check`.
5. Verify moderator and tenant logins.
6. Disable maintenance mode.

## 4. Post-Deploy Validation

1. Verify canonical slug redirects.
2. Validate tenant isolation through smoke tests.
3. Validate export generation and download isolation.
4. Validate tenant-aware password reset and invitation links.
5. Confirm role/membership change invalidates sessions.
6. Validate API token tenant claim matching for `/api/v1/{provinceSlug}/{barangaySlug}` routes.

## 5. Security Monitoring

1. Alert on cross-tenant exceptions (`security` channel).
2. Alert on repeated login failures (tenant + IP).
3. Alert on queue jobs missing tenant context.
4. Use `tenancy_alerts` channel for degraded/critical tenancy health alerts.

## 6. References

- `docs/TENANT_CUTOVER_SIGNOFF_CHECKLIST.md`
- `docs/TENANT_MIGRATION_PHASE_EXECUTION.md`
- `docs/TENANT_SECURITY_REVIEW.md`
- `docs/TENANT_PERFORMANCE_LOAD_TEST_PLAN.md`
