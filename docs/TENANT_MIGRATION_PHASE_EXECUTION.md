# Tenant Migration Phase Execution

> **Last Updated:** 2026-02-27
> **Scope:** Country-wide multi-tenant SaaS — all PH provinces (82) and barangays (42,000+) as first-class tenants.

This document captures executable commands for phases 0-7 in the SaaS migration plan.

## Tenant Hierarchy

| Role | Scope |
|------|-------|
| **Moderator** | Platform — manages subscriptions, SaaS settings, creates/manages Super Admins |
| **Super Admin** | Province — handler for assigned province; manages Admins and users |
| **Admin** | Barangay — manages operations within a single barangay |
| **Staff / Users** | Barangay — strictly isolated tenant data |

---

## Phase 0

- Inventory tenant-owned tables and null-key exposure:
  - `php artisan tenant:migration inventory`
- Canonical mapping report:
  - `php artisan tenant:migration mapping`
- Backup/restore rehearsal:
  - Execute backup provider snapshot and restore drill in staging (tracked in deployment logbook).

## Phase 1-2

- Introduce core tenancy schema/migrations:
  - `php artisan migrate`
- Seed all PH provinces and barangays (country-wide lookup tables):
  - `php artisan db:seed --class=ProvinceBarangaySeeder`
  - Verifies all 82 provinces and 42,000+ barangays seeded with unique stable slugs.
  - Province slugs globally unique; barangay slugs unique within province.
  - All records `is_active = false` by default until Moderator activates.
- Backfill in batches:
  - `php artisan tenant:migration backfill --dry-run`
  - `php artisan tenant:migration backfill --chunk=500`

## Phase 3

- Seed Moderator, Super Admin, Admin accounts via DemoSeeder:
  - `php artisan db:seed --class=DemoSeeder`
  - Moderator: platform-level account with `moderator` role.
  - Super Admins: mapped to demo provinces with `super_admin` / `province_admin` role.
  - Admins: mapped to barangays under each Super Admin with `barangay_admin` role.
  - Staff: operational users with limited permissions.
- Populate memberships and assignments:
  - `php artisan tenant:sync-rbac`
- Seed demo tenant data per module (for configurable subset of provinces):
  - Products, inventory, suppliers, orders, movements, audit/history logs, low stock settings, notifications.
  - All seeded relationships within same `province_id` + `barangay_id` scope.
  - Uses chunk inserts (500–1000 rows) and factory batches.

## Phase 4

- Monitor writes missing tenant keys:
  - `php artisan tenant:migration monitor --hours=24`
- Add alerts for missing keys:
  - `logging.channel=tenancy_alerts` with security + slack integration.

## Phase 4/5

- Reconciliation checks:
  - `php artisan tenant:migration reconcile`
  - `php artisan tenant:migration null-scan`

## Phase 5

- Pilot validation:
  - Tenant routes + module smoke checks across multiple provinces.
  - Leakage tests using multi-tenant feature tests.
  - `php artisan tenant:smoke-test`
  - Verify Super Admin can only access assigned province.
  - Verify Admin can only access assigned barangay.
  - No non-tenant URLs like `/admin/dashboard` remain accessible.

## Phase 6

- Enforce strict constraints once reconciliation passes:
  - Apply follow-up migration for `NOT NULL` + FK constraints.
- Make tenant routes default and restrict legacy `/admin`.
- Run quality cycle:
  ```bash
  composer dump-autoload
  npm run build
  php artisan route:clear && php artisan config:clear && php artisan cache:clear
  php vendor/bin/phpunit --stop-on-failure
  php artisan tenant:smoke-test
  ```

## Phase 7

- Remove compatibility code and legacy permission fallback:
  - `TENANCY_ALLOW_LEGACY_PERMISSIONS=false`
  - `TENANCY_ALLOW_LEGACY_MODERATOR_FALLBACK=false`
- Update factories/seeders/docs for tenancy-first setup.
- Verify idempotent DemoSeeder re-runs produce no duplicates.
- Final quality cycle must pass all tests.
- Final seeder integrity check:
  - `php artisan tenant:migration null-scan`
  - `php artisan tenant:validate-slugs`

## References

- `docs/TENANT_OPERATIONS_RUNBOOK.md`
- `docs/TENANT_CUTOVER_SIGNOFF_CHECKLIST.md`
- `docs/SAAS_MULTI_TENANT_CONVERSION_PLAN.md`
- `docs/MULTI_TENANT_MASTER_CHECKLIST.md`

