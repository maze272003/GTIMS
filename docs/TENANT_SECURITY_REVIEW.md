# Tenant Security Review

## Authorization and Isolation Coverage

- Tenant route middleware stack includes:
  - `tenant.resolve`
  - `tenant.membership`
  - `tenant.bind`
  - `tenant.modelscope`
  - `tenant.foreign_keys`
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

## API Security

- Versioned route structure: `/api/v1/{provinceSlug}/{barangaySlug}/...`
- Token-based authentication with tenant claims:
  - `tenant_api_tokens` table
  - `tenant.api.auth` middleware
  - `tenant.api.match` claim/route validation
  - `tenant.api.ability` ability enforcement

## File and Export Controls

- Tenant file uploads validated against tenant-scoped storage paths.
- Exports stored in tenant-isolated directories and delivered with scope headers:
  - `X-Tenant-Scope`
  - `X-Tenant-Province-Id`
  - `X-Tenant-Barangay-Id`
  - slug headers for traceability.

## PII Controls

- PII fields defined in tenancy config.
- Sensitive patient fields encrypted at rest via model encryption trait.
- `pii_access_audits` table logs read/export actions.

## Alerts and Audit Trails

- Moderator tenant switching writes immutable `audit_events`.
- Tenancy alerts channel configured via `tenancy_alerts`.
- Health command emits degraded/critical alerts.

