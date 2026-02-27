# GTIMS Tenant Incident Response SOP

> **Last Updated:** 2026-02-27
> **Scope:** Country-wide multi-tenant SaaS — all PH provinces and barangays as first-class tenants.

## Tenant Hierarchy Context

| Role | Scope | Incident Responsibility |
|------|-------|------------------------|
| **Moderator** | Platform | Owns all incident response; can suspend any tenant |
| **Super Admin** | Province | Escalates incidents within province; assists investigation |
| **Admin** | Barangay | Reports incidents; provides local context |

All tenant data is strictly isolated by `province_id` + `barangay_id`. Cross-tenant access is a critical incident.

---

## 1. Detection

1. Monitor `storage/logs/security.log` for cross-tenant access attempts.
2. Monitor failed jobs for tenant-context errors (`storage/logs/tenant.log`).
3. Monitor `tenancy_alerts` channel for degraded/critical health alerts.
4. Run automated detection:
   - `php artisan tenant:health-check` — checks all provinces/barangays.
   - `php artisan tenant:migration null-scan` — detects missing tenant keys on new writes.
5. Alert triggers:
   - Cross-tenant data read/write attempt detected.
   - Queue job executed without tenant context.
   - User from Province A accesses Province B route/data.
   - Export contains data outside requesting tenant scope.
   - Repeated login failures (tenant + IP threshold exceeded).
   - Super Admin attempts to manage users outside assigned province.
6. Open an incident record in `tenant_incidents` table.

## 2. Classification

| Severity | Description | Response Time |
|----------|-------------|---------------|
| **Critical** | Confirmed cross-tenant data leakage or breach | Immediate (< 15 min) |
| **High** | Suspected leakage; queue jobs with wrong tenant context | < 1 hour |
| **Medium** | Degraded tenant health; missing tenant keys on new writes | < 4 hours |
| **Low** | Configuration drift; non-critical feature flag issue | < 24 hours |

## 3. Containment

1. Suspend affected tenant scope if needed (`tenant_suspensions`):
   - Province-level suspension blocks all barangays under it.
   - Barangay-level suspension blocks only that barangay.
2. Revoke impacted user sessions.
3. Disable risky feature flags via kill switches:
   - `FEATURE_TENANT_ROUTES`
   - `FEATURE_TENANT_EXPORTS`
   - `FEATURE_TENANT_NOTIFICATIONS`
   - `FEATURE_API_ACCESS`
4. Drain queue workers for affected tenant scope: `php artisan queue:work --stop-when-empty`.
5. Block affected API tokens via `tenant_api_tokens` status update.

## 4. Investigation

1. Correlate request ID, tenant IDs/slugs, user ID, and route from structured logs.
2. Review audit trail and history logs for affected records:
   - `audit_events` table filtered by `province_id` + `barangay_id`.
   - `pii_access_audits` for patient data access.
3. Validate no export leakage occurred:
   - Check `storage/app/tenants/` directory isolation.
   - Verify export headers (`X-Tenant-Scope`, `X-Tenant-Province-Id`, `X-Tenant-Barangay-Id`).
4. Run cross-tenant leakage tests:
   ```bash
   php vendor/bin/phpunit tests/Feature/Tenancy/CrossTenantAccessTest.php
   ```
5. Verify seeder integrity if data corruption suspected:
   ```bash
   php artisan tenant:migration null-scan
   php artisan tenant:migration reconcile
   ```

## 5. Resolution

1. Patch scope validation/policy gaps:
   - Ensure `TenantScoped` trait applied to affected models.
   - Verify `tenant.resolve` middleware on affected routes.
   - Add missing `province_id`/`barangay_id` constraints.
2. Re-run leakage regression tests:
   ```bash
   php vendor/bin/phpunit tests/Feature/Tenancy/
   ```
3. Restore tenant access and document mitigation.
4. Run quality cycle:
   ```bash
   composer dump-autoload
   php vendor/bin/phpunit --stop-on-failure
   php artisan tenant:smoke-test
   ```

## 6. Post-Mortem

1. Record root cause and corrective actions in `tenant_incidents`.
2. Update runbooks/tests to prevent recurrence.
3. Add new automated tests for the specific failure vector.
4. Update `docs/TENANT_SECURITY_REVIEW.md` if new attack vector discovered.
5. Close incident with timeline and approvals.
6. Notify affected Super Admins/Admins of resolution.

## 7. Incident Log Commands

```bash
# Check tenant health across all provinces
php artisan tenant:health-check

# Check specific province
php artisan tenant:health-check --province=1

# Scan for null tenant keys
php artisan tenant:migration null-scan

# Reconcile tenant data
php artisan tenant:migration reconcile

# Run cross-tenant isolation tests
php vendor/bin/phpunit tests/Feature/Tenancy/CrossTenantAccessTest.php

# Run full tenant test suite
php vendor/bin/phpunit tests/Feature/Tenancy/
```

## 8. References

- `docs/TENANT_OPERATIONS_RUNBOOK.md`
- `docs/TENANT_SECURITY_REVIEW.md`
- `docs/TENANT_CUTOVER_SIGNOFF_CHECKLIST.md`
- `docs/MULTI_TENANT_MASTER_CHECKLIST.md`
