# GTIMS Tenant Operations Runbook

> **Last Updated:** 2026-02-27
> **Scope:** Country-wide multi-tenant SaaS — all PH provinces and barangays as first-class tenants.

## Tenant Hierarchy

| Role | Scope | Description |
|------|-------|-------------|
| **Moderator** | Platform | Manages subscriptions, SaaS settings, creates/manages Super Admin accounts |
| **Super Admin** | Province | Handler for an assigned province; manages Admins and users under it |
| **Admin** | Barangay | Manages operations within a single barangay |
| **Staff / Users** | Barangay | Operate within strictly isolated tenant data |

All tenant data (inventories, orders, tracking, logs, analytics, notifications, etc.) is isolated by `province_id` + `barangay_id`.

---

## 1. Deployment

1. Enable maintenance mode: `php artisan down`.
2. Run migrations: `php artisan migrate --force`.
3. Run RBAC sync: `php artisan tenant:sync-rbac`.
4. Seed/update tenant records:
   - `php artisan db:seed --class=DemoSeeder` (idempotent, safe to rerun).
   - Seeds all 82 PH provinces and 42,000+ barangays for lookup tables.
   - Generates business demo data for the configurable subset defined in `config/tenancy.php` → `seeder.demo_provinces`.
5. Run reconciliation:
   - `php artisan tenant:health-check`
   - `php artisan tenant:usage:report`
   - `php artisan tenant:migration reconcile`
   - `php artisan tenant:migration null-scan`
6. Run quality cycle:
   - `composer dump-autoload`
   - `php artisan route:clear && php artisan config:clear && php artisan cache:clear`
   - `php vendor/bin/phpunit --stop-on-failure`
   - `php artisan tenant:smoke-test` (slug resolution + cross-tenant denial)
7. Validate routes:
   - `/{provinceSlug}/{barangaySlug}/dashboard` — tenant access
   - `/moderator/dashboard` — Moderator portal
8. Disable maintenance mode: `php artisan up`.

## 2. Seeder Operations

### DemoSeeder Entry Point

All seeders are organized via `database/seeders/DemoSeeder.php` which orchestrates:

1. **ProvinceBarangaySeeder** — Seeds all 82 PH provinces and their barangays with unique stable slugs (from PSGC dataset or equivalent).
2. **ModeratorSeeder** — Creates platform-level Moderator account.
3. **SuperAdminSeeder** — Creates Super Admin accounts mapped to provinces.
4. **AdminUserSeeder** — Creates Admins and staff under each Super Admin.
5. **TenantDemoDataSeeder** — Seeds per-tenant sample data (products, inventory, suppliers, orders, movements, audit/history logs, low stock settings, notifications) only for demo provinces.

### Seeder Safety

- All seeders are **idempotent** — use `firstOrCreate`, `updateOrCreate`, or conditional inserts.
- Use **chunk inserts** (500–1000 rows) and **factory batches** for performance.
- Demo data generation limited to configurable subset (`config('tenancy.seeder.demo_provinces')`).
- All seeded relationships remain within the same `province_id` + `barangay_id` scope.

### Seeder Commands

```bash
# Full seed (safe to rerun)
php artisan db:seed --class=DemoSeeder

# Seed only province/barangay lookup tables
php artisan db:seed --class=ProvinceBarangaySeeder

# Seed demo data for a specific province
php artisan tenant:seed-demo --province=bulacan

# Verify seeder integrity
php artisan tenant:migration null-scan
```

## 3. Queue Worker Drain / Restart

1. Pause worker supervisor process.
2. Wait for in-flight jobs to finish: `php artisan queue:work --stop-when-empty`.
3. Deploy code and run migrations.
4. Restart workers.
5. Check failed jobs and tenant context logs:
   - `php artisan queue:failed`
   - `php artisan tenant:queue:validate-deploy`
   - Inspect `storage/logs/security.log` and `storage/logs/tenant.log`.
6. Confirm all queued jobs carry tenant context (`province_id`, `barangay_id`, `scope_type`).

## 4. Rollback

1. Re-enable maintenance mode.
2. Roll back app release and database to validated backup.
3. Restart workers with previous release.
4. Run `php artisan tenant:health-check`.
5. Verify Moderator, Super Admin, and tenant logins.
6. Disable maintenance mode.

## 5. Post-Deploy Validation

1. Verify canonical slug redirects across multiple provinces (e.g., `/bulacan/malolos/dashboard`, `/cebu/cebu-city/dashboard`).
2. Validate tenant isolation through smoke tests:
   - `php artisan tenant:smoke-test`
   - Confirm user from Province A cannot read/modify Province B data.
3. Validate export generation and download isolation.
4. Validate tenant-aware password reset and invitation links.
5. Confirm role/membership change invalidates sessions.
6. Validate API token tenant claim matching for `/api/v1/{provinceSlug}/{barangaySlug}` routes.
7. Verify Super Admin can manage Admins only within their assigned province.
8. Verify Moderator can manage all provinces and Super Admin accounts.

## 6. Quality Cycle (Run Until Stable)

Execute this cycle after every deployment or code change:

```bash
# Step 1: Build / Autoload
composer dump-autoload
npm run build

# Step 2: Syntax / Config Check
php artisan route:clear
php artisan config:clear
php artisan cache:clear

# Step 3: Unit / Integration Tests
php vendor/bin/phpunit --stop-on-failure

# Step 4: Tenant Smoke Tests
php artisan tenant:smoke-test
```

### Automated Tests That Must Pass

| Test Category | What It Validates |
|---------------|-------------------|
| Slug Resolution | `/{provinceSlug}/{barangaySlug}` resolves correctly for multiple PH provinces |
| Role-Based Access | Moderator → all; Super Admin → own province only; Admin → own barangay only |
| Cross-Tenant Denial | User from Province A gets 403/404 when accessing Province B data |
| Seeder Integrity | All seeded data has correct `province_id` + `barangay_id`; no orphan records |
| Export Isolation | Exports contain only data from the requesting tenant scope |
| Queue Context | Background jobs carry and restore tenant context correctly |

## 7. Security Monitoring

1. Alert on cross-tenant exceptions (`security` channel).
2. Alert on repeated login failures (tenant + IP).
3. Alert on queue jobs missing tenant context.
4. Use `tenancy_alerts` channel for degraded/critical tenancy health alerts.
5. Monitor Super Admin actions across barangays within their province.
6. Log and alert on any attempt to access a province/barangay outside assigned scope.

## 8. Artisan Command Reference

```bash
# Health & Monitoring
php artisan tenant:health-check
php artisan tenant:health-check --province=1
php artisan tenant:health-check --barangay=5
php artisan tenant:usage:report

# Seeding
php artisan db:seed --class=DemoSeeder
php artisan tenant:seed-demo --province=bulacan

# Reconciliation
php artisan tenant:migration reconcile
php artisan tenant:migration null-scan
php artisan tenant:validate-slugs

# Cache
php artisan tenant:cache:clear
php artisan tenant:cache:clear --province=1

# Queue Validation
php artisan tenant:queue:validate-deploy

# Testing
php artisan tenant:smoke-test
php vendor/bin/phpunit tests/Feature/Tenancy/
```

## 9. References

- `docs/TENANT_CUTOVER_SIGNOFF_CHECKLIST.md`
- `docs/TENANT_MIGRATION_PHASE_EXECUTION.md`
- `docs/TENANT_SECURITY_REVIEW.md`
- `docs/TENANT_PERFORMANCE_LOAD_TEST_PLAN.md`
- `docs/TENANT_ONBOARDING_SOP.md`
- `docs/TENANT_INCIDENT_RESPONSE_SOP.md`
- `docs/SAAS_MULTI_TENANT_CONVERSION_PLAN.md`
- `docs/MULTI_TENANT_SETUP.md`
- `docs/MULTI_TENANT_MASTER_CHECKLIST.md`
