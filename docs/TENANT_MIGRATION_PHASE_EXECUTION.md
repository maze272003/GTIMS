# Tenant Migration Phase Execution

This document captures executable commands for phases 0-7 in the SaaS migration plan.

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
- Backfill in batches:
  - `php artisan tenant:migration backfill --dry-run`
  - `php artisan tenant:migration backfill --chunk=500`

## Phase 3

- Populate memberships and assignments:
  - `php artisan tenant:sync-rbac`

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
  - Tenant routes + module smoke checks
  - Leakage tests using multi-tenant feature tests

## Phase 6

- Enforce strict constraints once reconciliation passes:
  - Apply follow-up migration for `NOT NULL` + FK constraints.
- Make tenant routes default and restrict legacy `/admin`.

## Phase 7

- Remove compatibility code and legacy permission fallback:
  - `TENANCY_ALLOW_LEGACY_PERMISSIONS=false`
  - `TENANCY_ALLOW_LEGACY_MODERATOR_FALLBACK=false`
- Update factories/seeders/docs for tenancy-first setup.

