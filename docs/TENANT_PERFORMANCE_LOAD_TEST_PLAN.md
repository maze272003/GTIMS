# Tenant Performance and Load Test Plan

> **Last Updated:** 2026-02-27
> **Scope:** Country-wide multi-tenant SaaS — all PH provinces (82) and barangays (42,000+) as first-class tenants.

## Objectives

- Measure province-level aggregation performance across all 82 PH provinces.
- Measure tenant route resolution overhead for `/{provinceSlug}/{barangaySlug}` patterns at country-wide scale.
- Measure queue throughput with tenant-tagged jobs (carrying `province_id`, `barangay_id`, `scope_type`).
- Measure export path generation and storage overhead with tenant-scoped directories.
- Measure seeder performance for country-wide data (82 provinces, 42,000+ barangays).
- Verify data isolation holds under concurrent multi-tenant load.

## Tenant Hierarchy Under Test

| Role | Scope | Load Pattern |
|------|-------|-------------|
| **Moderator** | Platform | Aggregation queries across all provinces |
| **Super Admin** | Province | Province-level KPI queries; cross-barangay views |
| **Admin** | Barangay | Module-level CRUD; exports; patient record operations |

## Commands

```bash
# Standard load test
php artisan tenant:load-test --iterations=100

# Extended staging test
php artisan tenant:load-test --iterations=500

# High-load test
php artisan tenant:load-test --iterations=1000 --concurrent=10
```

## Test Scenarios

### 1. Route Resolution Performance

- Resolve `/{provinceSlug}/{barangaySlug}` for 100+ province/barangay combinations.
- Measure latency distribution (p50, p95, p99).
- Verify resolution remains stable as tenant volume increases to full PH scale.
- Test invalid slug handling (404 response latency).

### 2. Province Aggregation Queries

- Super Admin dashboard: aggregate KPIs across all barangays within a province.
- Moderator dashboard: aggregate metrics across all provinces.
- Measure `analytics_kpis` average latency against dashboard SLA.
- Verify tenant-scoped queries use proper indexes (`province_id, barangay_id`).

### 3. Queue Throughput

- Queue 500+ tenant-aware jobs across multiple provinces.
- Measure job processing rate per worker.
- Verify tenant context preserved through job lifecycle.
- Monitor failed-job growth by tenant.
- Test concurrent queue workers processing jobs from different tenants.

### 4. Export Operations

- Trigger concurrent export operations across multiple tenants.
- Verify tenant storage boundaries maintained under load.
- Measure export generation time for large datasets (5,000+ inventory items).
- Verify export headers contain correct tenant scope.

### 5. Seeder Performance

- Measure time to seed all 82 PH provinces and 42,000+ barangays.
- Measure time to seed demo data for configurable subset of provinces.
- Verify chunk insert performance (500–1000 row batches).
- Target: geo data < 5 min; demo data < 15 min.
- Verify memory usage stays within acceptable limits during seeding.

### 6. Cross-Tenant Isolation Under Load

- Concurrent requests from different tenants to same endpoints.
- Verify no data leakage between provinces or barangays under load.
- Test Super Admin access boundaries under concurrent load.

## Interpreting Results

- `route_resolution` average latency should remain stable as tenant volume grows (< 50ms p95).
- `analytics_kpis` average latency should stay within acceptable dashboard SLA (< 500ms p95).
- `queue_throughput` should process 100+ jobs/min per worker with tenant context intact.
- `export_generation` should complete within 30s for standard-size datasets.
- `seeder_performance` should complete full geo seed in < 5 min.
- No cross-tenant data leakage detected under any load scenario.

## Recommended Extended Tests

1. Increase iterations to 500/1000 in staging.
2. Run concurrent queue workers (4+) and monitor failed-job growth by tenant.
3. Trigger repeated export operations and verify tenant storage boundaries.
4. Simulate full country-wide scale: all 82 provinces active with concurrent users.
5. Capture baseline and post-optimization metrics in release notes.
6. Run seeder idempotency test under load (concurrent DemoSeeder invocations).
7. Test Moderator global aggregation queries with full PH dataset loaded.

## Quality Cycle Integration

After load testing, run the quality cycle to confirm stability:

```bash
composer dump-autoload
php artisan route:clear && php artisan config:clear && php artisan cache:clear
php vendor/bin/phpunit --stop-on-failure
php artisan tenant:smoke-test
```

## References

- `docs/TENANT_OPERATIONS_RUNBOOK.md`
- `docs/MULTI_TENANT_MASTER_CHECKLIST.md`
- `docs/TENANT_SECURITY_REVIEW.md`

