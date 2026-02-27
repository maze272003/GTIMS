# Tenant Performance and Load Test Plan

## Objectives

- Measure province-level aggregation performance.
- Measure tenant route resolution overhead.
- Measure queue throughput with tenant-tagged jobs.
- Measure export path generation and storage overhead.

## Command

- `php artisan tenant:load-test --iterations=100`

## Interpreting Results

- `route_resolution` average latency should remain stable as tenant volume grows.
- `analytics_kpis` average latency should stay within acceptable dashboard SLA.

## Recommended Extended Tests

1. Increase iterations to 500/1000 in staging.
2. Run concurrent queue workers and monitor failed-job growth by tenant.
3. Trigger repeated export operations and verify tenant storage boundaries.
4. Capture baseline and post-optimization metrics in release notes.

