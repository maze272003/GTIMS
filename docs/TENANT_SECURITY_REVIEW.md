# Tenant Security Review

> **Last Updated:** 2026-02-27
> **Scope:** Country-wide multi-tenant SaaS — all PH provinces (82) and barangays (42,000+) as first-class tenants.

## Tenant Hierarchy and Security Boundaries

| Role | Scope | Access Boundary |
|------|-------|----------------|
| **Moderator** | Platform | All provinces/barangays; manages subscriptions, SaaS settings, Super Admins |
| **Super Admin** | Province | Only assigned province and its barangays; manages Admins and users |
| **Admin** | Barangay | Only assigned barangay within province |
| **Staff / Users** | Barangay | Strictly isolated tenant data within assigned barangay |

All tenant data (inventories, orders, tracking, logs, analytics, notifications, etc.) is isolated by `province_id` + `barangay_id`.

---

## Authorization and Isolation Coverage

- Tenant route middleware stack includes:
  - `tenant.resolve` — validates `/{provinceSlug}/{barangaySlug}` slugs, loads tenant context, blocks invalid access
  - `tenant.membership` — validates user has membership in resolved tenant
  - `tenant.bind` — binds `TenantContext` to container, session, views
  - `tenant.modelscope` — applies global scopes to tenant-owned models
  - `tenant.foreign_keys` — validates cross-entity references stay within tenant
- Scoped model policies added for:
  - Inventory
  - Patient Records
  - Orders
  - Incoming Requests
  - Suppliers
  - Holds
  - Audit Events
  - History Logs
- Permission middleware applied across tenant route groups.
- Global query scopes enforce `province_id` + `barangay_id` on every tenant-owned model via `TenantScoped` trait.
- Policies/gates prevent cross-tenant leaks even if a query is miswritten.

## Role-Based Access Enforcement

| Scenario | Expected Result |
|----------|----------------|
| Moderator accesses any province/barangay | Allowed |
| Super Admin accesses own province | Allowed |
| Super Admin accesses different province | **Blocked (403)** |
| Admin accesses own barangay | Allowed |
| Admin accesses different barangay (same province) | **Blocked (403)** |
| Admin accesses different province | **Blocked (403/404)** |
| Staff accesses data outside assigned barangay | **Blocked (403)** |
| Non-tenant URL like `/admin/dashboard` | **Not available — all routes are tenant-aware** |

## API Security

- Versioned route structure: `/api/v1/{provinceSlug}/{barangaySlug}/...`
- Token-based authentication with tenant claims:
  - `tenant_api_tokens` table
  - `tenant.api.auth` middleware
  - `tenant.api.match` claim/route validation — token tenant must match route tenant
  - `tenant.api.ability` ability enforcement
- Rate limiting by tenant scope + IP.

## File and Export Controls

- Tenant file uploads validated against tenant-scoped storage paths.
- Exports stored in tenant-isolated directories and delivered with scope headers:
  - `X-Tenant-Scope`
  - `X-Tenant-Province-Id`
  - `X-Tenant-Barangay-Id`
  - Slug headers for traceability.
- Super Admin exports limited to own province data only.
- Moderator exports can span multiple tenants with explicit scope selection.

## PII Controls

- PII fields defined in tenancy config.
- Sensitive patient fields encrypted at rest via model encryption trait.
- `pii_access_audits` table logs read/export actions.
- Data subject request support via `data_subject_requests` table.

## Seeder Security

- All seeded data carries correct `province_id` + `barangay_id`.
- Seeder integrity verified via:
  - `php artisan tenant:migration null-scan` — no orphan records or missing tenant keys.
  - `php artisan tenant:validate-slugs` — all slugs canonical and unique.
- Seeders are idempotent — re-runs do not corrupt data or create duplicates.
- Demo data generation scoped to configurable provinces only.

## Alerts and Audit Trails

- Moderator tenant switching writes immutable `audit_events`.
- Super Admin actions across barangays logged with full context.
- Tenancy alerts channel configured via `tenancy_alerts`.
- Health command emits degraded/critical alerts.
- Cross-tenant access attempts logged to `security` channel and trigger immediate alert.
- Queue jobs missing tenant context trigger alerts.
- Repeated login failures (tenant + IP) trigger lockout and alert.

## Automated Security Tests

| Test | What It Validates |
|------|-------------------|
| `CrossTenantAccessTest` | User from Province A blocked from Province B data |
| `RoleAccessTest` | Moderator → all; Super Admin → own province; Admin → own barangay |
| `ExportIsolationTest` | Exports scoped to requesting tenant only |
| `QueueContextTest` | Jobs carry and restore tenant context |
| `SeederIntegrityTest` | All seeded data has correct tenant keys |
| `SlugResolutionTest` | Slugs resolve correctly across PH provinces/barangays |

## Quality Cycle (Security Gate)

Before any release, the following must pass:

```bash
composer dump-autoload
php vendor/bin/phpunit --stop-on-failure
php artisan tenant:smoke-test
php artisan tenant:migration null-scan
```

## References

- `docs/TENANT_OPERATIONS_RUNBOOK.md`
- `docs/TENANT_INCIDENT_RESPONSE_SOP.md`
- `docs/MULTI_TENANT_MASTER_CHECKLIST.md`
- `docs/TENANT_PERFORMANCE_LOAD_TEST_PLAN.md`

