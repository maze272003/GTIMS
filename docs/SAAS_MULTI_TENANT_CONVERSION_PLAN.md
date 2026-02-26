# GTIMS Multi-Tenant SaaS Conversion Plan

## Document Purpose

This implementation plan describes how to convert the current GTIMS Laravel application into a multi-tenant SaaS platform with strict tenant data isolation and hierarchical administration.

Target hierarchy:

1. `Moderator` (Super Admin / global oversight)
2. `Provincial Administrator`
3. `Barangay Administrator`

Target access pattern:

- `https://domain.name/{province-slug}/{barangay-slug}` for tenant-scoped login and app access
- `https://domain.name/moderator` (or `https://admin.domain.name`) for Moderator portal

This plan is tailored to the current codebase structure (Laravel + Vite + service/repository pattern) and modules already present (inventory, patient records, holds, requests, orders, suppliers, analytics, notifications, audit logs).

## Codebase Scan Summary (Current State)

The current system is a single-instance application with branch-based separation and some barangay-level references:

1. `users` currently uses `branch_id` and `user_level_id` (`app/Models/User.php`)
2. `branches` table exists and is used heavily for operational scope (`inventories`, `orders`, `incoming_requests`, `holds`, `patientrecords`, etc.)
3. `barangays` table exists but is not tenant-hierarchical yet (no province linkage)
4. Role/permission model uses `user_levels`, `permissions`, and `role_permissions`
5. Auth redirect logic is permission-based (`app/Services/AuthSessionService.php`)
6. Many services hardcode branch-scoped filtering and assumptions (notably analytics, inventory, orders, patient records, dashboard)
7. Routes are under a shared `/admin` prefix and are not tenant-slug aware

This is a good starting point, but SaaS conversion requires a formal tenant model, slug-based tenant resolution, and consistent query isolation across all modules.

## Target SaaS Architecture (Hierarchical Multi-Tenant)

## 1. Tenant Hierarchy Model

Use a hierarchical tenancy model with explicit entities:

1. `Platform` (implicit, system-wide)
2. `Province` (top-level tenant container)
3. `Barangay` (child tenant under a province)

Role scope:

1. `Moderator` can access all provinces/barangays and system-wide operations
2. `Provincial Administrator` can access only their province and all barangays under it
3. `Barangay Administrator` can access only their assigned barangay tenant

## 2. Tenancy Strategy (Recommended)

Use a **single database, shared schema, row-level tenant partitioning** with strict application-level scoping.

Why this fits this codebase:

1. Current app already uses shared tables and service/repository layers
2. Faster migration from current branch-based structure
3. Lower operational complexity than database-per-tenant for the first SaaS version
4. Can evolve later to hybrid tenancy for larger provinces if needed

Optional future enhancement:

- PostgreSQL Row-Level Security (RLS) if database engine and deployment architecture support it

## 3. Tenant URL / Slug Routing Model

Tenant access path examples:

1. `https://domain.name/bulacan/malolos`
2. `https://domain.name/nueva-ecija/cabanatuan-city`
3. `https://domain.name/moderator`

Routing rules:

1. Moderator portal uses dedicated prefix (`/moderator`) or subdomain (`admin.domain.name`)
2. Tenant routes use `/{provinceSlug}/{barangaySlug}` prefix
3. Province-only admin routes can use `/{provinceSlug}/admin` or still keep both slugs and derive province from barangay
4. Slugs must be canonical and unique per scope

Recommendation:

1. Barangay slug uniqueness enforced within province (`unique(province_id, slug)`)
2. Province slug globally unique (`unique(slug)`)
3. Redirect non-canonical slugs to canonical URLs

## SaaS Database Schema Design (Strict Data Isolation)

## 1. New Core Tables

### `provinces`

Fields:

1. `id`
2. `name`
3. `slug` (unique)
4. `code` (optional standard code)
5. `is_active`
6. `settings_json` (optional JSON config)
7. `created_at`, `updated_at`

### `tenant_barangays` (or upgrade existing `barangays`)

Recommended approach:

- Upgrade existing `barangays` table instead of creating a duplicate table

New/updated fields:

1. `province_id` (FK to `provinces`)
2. `slug`
3. `is_active`
4. `external_code` (optional)
5. `settings_json` (optional)

Constraints:

1. `unique(province_id, slug)`
2. optional `unique(province_id, barangay_name)`

### `tenant_memberships` (recommended)

Purpose:

- Assign users to province/barangay scopes without overloading `users`

Fields:

1. `id`
2. `user_id`
3. `scope_type` (`platform`, `province`, `barangay`)
4. `scope_id` (nullable for platform)
5. `is_primary`
6. `status` (`active`, `invited`, `suspended`)
7. `created_at`, `updated_at`

Constraints:

1. `unique(user_id, scope_type, scope_id)`

### `roles` and `role_assignments` (RBAC modernization)

Current system uses `user_levels`. For SaaS, keep `permissions` but move to scoped assignments.

`roles`:

1. `id`
2. `name`
3. `slug`
4. `scope_type` (`platform`, `province`, `barangay`)
5. `is_system_role`
6. `created_at`, `updated_at`

`role_assignments`:

1. `id`
2. `user_id`
3. `role_id`
4. `scope_type`
5. `scope_id`
6. `created_at`, `updated_at`

Constraints:

1. `unique(user_id, role_id, scope_type, scope_id)`

Note:

- `permissions` and `role_permissions` can be reused with minor changes if roles replace `user_levels`

### `tenant_route_bindings` (optional, for custom domains/path aliases)

Fields:

1. `id`
2. `province_id`
3. `barangay_id` (nullable for province-only portal)
4. `host` (nullable if path-based only)
5. `path_prefix`
6. `is_active`

Use this if you later support custom domains per province/barangay.

## 2. Add Tenant Keys to Existing Tables (Critical for Isolation)

Every tenant-owned data row must carry explicit scope fields.

### Minimum target columns for tenant-owned transactional tables

Add:

1. `province_id`
2. `barangay_id` (nullable only for province-wide rows where applicable)

Applies to (based on current code scan):

1. `users` (if not fully using memberships yet)
2. `branches` (or replace branch role with barangay/health facility mapping)
3. `inventories`
4. `orders`
5. `order_items` (optional denormalized tenant keys for faster joins)
6. `patientrecords`
7. `dispensedmedications`
8. `holds`
9. `hold_items` (optional denormalized)
10. `incoming_requests`
11. `request_items` (optional denormalized)
12. `request_comments` (inherit from parent but denormalized key recommended)
13. `request_attachments` (inherit from parent but denormalized key recommended)
14. `suppliers`
15. `supplier_products`
16. `low_stock_settings`
17. `reorder_rules`
18. `notifications`
19. `audit_events`
20. `history_logs`
21. `product_movements`
22. `idempotency_keys`

## 3. Shared vs Tenant-Owned Data Classification

This must be explicit to prevent leakage and avoid unnecessary duplication.

### Shared (global/platform-managed)

1. `products` (master catalog, if product definitions are standardized)
2. `permissions`
3. `roles` system templates (or `user_levels` during transition)
4. Some analytics/report definitions
5. static config templates

### Tenant-owned / tenant-scoped

1. inventory stock and batch records
2. patient records and dispensed medications
3. requests / holds / orders
4. suppliers and supplier-to-inventory links
5. notifications and audit logs
6. low stock settings and reorder rules (province/barangay scoped)

### Hybrid / configurable

1. suppliers may become province-scoped or barangay-scoped based on business rule
2. user accounts may have multi-scope memberships

## 4. Indexing and Constraints for Isolation and Performance

Add composite indexes to all tenant-owned high-read tables:

1. `index(province_id, barangay_id)`
2. `index(province_id, created_at)` for reporting
3. `index(barangay_id, created_at)` for operational lists
4. module-specific indexes with tenant first (example: `inventories(province_id, barangay_id, product_id)`)

Examples:

1. `patientrecords`: `index(province_id, barangay_id, date_dispensed)`
2. `inventories`: `index(province_id, barangay_id, product_id, is_archived)`
3. `incoming_requests`: `index(province_id, barangay_id, status, created_at)`
4. `product_movements`: `index(province_id, barangay_id, inventory_id, created_at)`

## 5. Strict Data Isolation Enforcement Layers (Do All, Not One)

Isolation must be enforced at multiple layers:

1. Route tenant resolution (`provinceSlug`, `barangaySlug`)
2. Request tenant context middleware (resolve and bind current tenant)
3. Authorization policies (role + scope checks)
4. Repository query scoping (default tenant filters)
5. Service-level guardrails (reject cross-tenant IDs)
6. Validation rules constrained to tenant-owned references
7. Background job context propagation
8. Export/report scoping
9. Audit logging including tenant identifiers

## Authentication and Tenant-Aware Login Flows

## 1. Login Portal Architecture

### Moderator Login

Entry points:

1. `/moderator/login`
2. optional `admin.domain.name/login`

Behavior:

1. only `Moderator` role accounts can authenticate here
2. global dashboard and tenant management access after login

### Tenant Login (Province/Barangay)

Entry point:

1. `/{provinceSlug}/{barangaySlug}/login`

Behavior:

1. resolve tenant from slug path before rendering login form
2. login form shows tenant branding/name to reduce phishing and user confusion
3. authentication validates account + membership/role assignment for that tenant scope
4. session stores selected tenant context (`province_id`, `barangay_id`, `scope_type`)

## 2. Session and Tenant Context Binding

Store in session after login:

1. `tenant.province_id`
2. `tenant.barangay_id`
3. `tenant.scope_type`
4. `tenant.route_slug_province`
5. `tenant.route_slug_barangay`

Required runtime objects:

1. `TenantContext` value object
2. `TenantResolver` service
3. `SetTenantContext` middleware

## 3. Authentication Logic Refactor (Current OTP/Login Flow)

Existing auth features include OTP and permission-based redirects. Refactor steps:

1. Update login controllers/services to accept tenant slug route params
2. Validate user membership/role assignment in the resolved tenant
3. Update `AuthSessionService` redirect logic to be tenant-aware
4. Generate routes with tenant prefix for Provincial/Barangay admins
5. Preserve Moderator global redirect path
6. Update password reset and email verification links to include tenant context when applicable
7. Update new-login notification emails to include tenant name and scope

## 4. URL-Safe Route Generation Strategy

Add helpers:

1. `tenant_route($name, $params = [], ?TenantContext $ctx = null)`
2. `moderator_route($name, $params = [])`

This prevents accidental hardcoded `/admin/...` links that bypass tenant context.

## RBAC Strategy (Moderator / Provincial / Barangay)

## 1. Role Model (Recommended System Roles)

System-defined roles:

1. `moderator` (platform scope)
2. `province_admin` (province scope)
3. `barangay_admin` (barangay scope)
4. optional `barangay_staff` (barangay scope)
5. optional `auditor` (province or platform scope)

## 2. Permission Model

Keep granular permissions (already present in codebase) and assign to roles by scope.

Examples:

1. `tenants.manage`
2. `provinces.manage`
3. `barangays.manage`
4. `users.manage`
5. `inventory.view`, `inventory.manage`
6. `patientrecords.view`, `patientrecords.manage`
7. `requests.manage`
8. `suppliers.manage`
9. `analytics.view`
10. `audit.view`

## 3. Scope-Aware Permission Evaluation

Permission check must consider:

1. permission name
2. role assignment scope
3. current tenant context
4. target record tenant ownership

Examples:

1. Provincial admin can read all barangay records in their province
2. Barangay admin can only read rows with matching `province_id` + `barangay_id`
3. Moderator can read all, but write actions may still be restricted by policy for safety

## 4. Transition Strategy From `user_levels`

Phase approach:

1. Keep `user_levels` during transition
2. Introduce new `roles` and `role_assignments`
3. Map existing `user_levels` to system roles
4. Replace `hasPermission()` source from `level.permissions` to scoped role resolver
5. Remove `user_levels` dependency after full cutover

## Dashboard Feature Breakdown by Role

## 1. Moderator Dashboard (Global Oversight)

Core features:

1. Province list and status overview
2. Barangay account inventory and activation status
3. Total active users by scope (province/barangay)
4. Platform-wide usage metrics (logins, API requests, exports, queue jobs)
5. Health summary (failed jobs, error trends, email delivery status)
6. Data integrity alerts (cross-tenant anomalies, orphaned rows)
7. Audit and security events (suspicious logins, privilege changes)
8. Tenant onboarding wizard (create province + barangay + admin accounts)
9. Subscription/billing hooks (future-ready even if billing is postponed)
10. Support tools (impersonation with full audit trail, session revoke)

Moderator admin pages:

1. Provinces management
2. Barangays management
3. Tenant memberships and user assignments
4. Role templates and permission templates
5. Global catalog management (products, system settings)
6. Platform audit and observability pages

## 2. Provincial Administrator Dashboard

Scope: one province, all barangays under that province

Core features:

1. Province-level KPI summary across all barangays
2. Comparative metrics by barangay (inventory levels, requests, patient counts)
3. Province-wide low stock alerts and reorder suggestions
4. Barangay performance / activity feed
5. Province-scoped user management (invite/manage barangay admins)
6. Province-level suppliers and supplier performance (if business rules allow)
7. Audit logs filtered to province
8. Notifications center (province + child barangay events as configured)

Provincial admin pages:

1. Barangay directory and activation controls
2. Province user management and role assignment
3. Aggregated reports/exports
4. Cross-barangay inventory requests/approvals (if enabled)
5. Province settings (alerts, defaults, integrations)

## 3. Barangay Administrator Dashboard

Scope: one barangay only

Core features:

1. Local inventory stock summary
2. Low stock / expiring batches
3. Orders, requests, holds, and approvals queue
4. Patient records and dispensing summaries
5. Local suppliers and batch-level supplier links
6. Local notifications and audit history
7. Barangay-specific settings (thresholds, preferences)

Barangay admin pages:

1. Inventory management
2. Patient records
3. Orders / holds / incoming requests
4. Suppliers and supplier batch links
5. Reports and exports (barangay-scoped only)
6. User management (only if delegated)

## Backend Refactoring Plan (Tenant Context + Isolation)

## 1. New Core Backend Components

Implement:

1. `App\Tenancy\TenantContext` (immutable context object)
2. `App\Tenancy\TenantResolver` (route/session/user to tenant context)
3. `App\Http\Middleware\ResolveTenantFromSlug`
4. `App\Http\Middleware\EnforceTenantMembership`
5. `App\Http\Middleware\BindTenantContext`
6. `App\Support\TenantRouteGenerator` helper
7. `App\Policies\*` scope-aware policies for tenant-owned models

## 2. Route Refactor Strategy

Current routes are under `/admin`. Refactor into route groups:

1. Moderator routes: `/moderator/...`
2. Tenant routes: `/{provinceSlug}/{barangaySlug}/...`
3. Optional province-only routes: `/{provinceSlug}/province-admin/...`

Implementation steps:

1. Introduce parallel route groups without removing current `/admin` routes initially
2. Add tenant middleware to new routes
3. Migrate links/views/controllers gradually
4. Retire `/admin` routes after cutover

## 3. Service Layer Refactor (High Priority Hotspots Identified in Scan)

Refactor services that directly rely on `branch_id` assumptions:

1. `DashboardAdminService`
2. `AnalyticsService`
3. `InventoryAdminService`
4. `PatientRecordsAdminService`
5. `OrderAdminService`
6. `RequestWorkflowService`
7. `AvailabilityService`
8. `ManageAccountAdminService`
9. `NotificationService`
10. `AuthSessionService`

Refactor pattern:

1. Replace implicit `Auth::user()->branch_id` filters with `TenantContext`
2. Accept `TenantContext` in service methods or constructor injection
3. Centralize common tenant filter application
4. Remove hardcoded branch IDs (`1`, `2`) and RHU assumptions

## 4. Repository Layer Refactor

Add tenant-aware query methods or scopes to repositories:

1. `forTenant(TenantContext $ctx)`
2. `forProvince(int $provinceId)`
3. `forBarangay(int $provinceId, int $barangayId)`

Rules:

1. Tenant-owned repositories must default to scoped queries
2. Unscoped queries should be explicit and reserved for Moderator workflows
3. Exports and analytics repositories must enforce tenant scope before filtering

## 5. Model Scopes / Traits

Introduce reusable traits:

1. `BelongsToProvince`
2. `BelongsToBarangay`
3. `TenantScoped` (query scope helpers)

Examples:

1. `scopeForTenant($query, TenantContext $ctx)`
2. `scopeForProvince($query, int $provinceId)`

## 6. Validation and Cross-Tenant Foreign Keys

Current validation uses `exists:table,id`. This is insufficient for SaaS.

Replace with tenant-aware validation patterns:

1. `exists` + query closures constrained by tenant keys
2. Custom validation rules:
   - `BelongsToCurrentProvince`
   - `BelongsToCurrentBarangay`
   - `BelongsToCurrentTenant`

Examples:

1. Inventory ID selected for supplier link must belong to current tenant
2. Barangay chosen in patient record must belong to current province
3. Supplier selected in reorder rules must be tenant-visible

## 7. Background Jobs, Events, and Notifications

Every queued job/event that touches tenant data must carry tenant context:

1. serialize `province_id`, `barangay_id`, `scope_type`
2. rehydrate `TenantContext` inside `handle()`
3. namespace cache keys by tenant
4. namespace notification queries by tenant
5. include tenant metadata in audit logs

## 8. Exports / Reports Refactor

Current exports (inventory, patient records, suppliers) must become tenant-aware.

Requirements:

1. include tenant header (Province / Barangay)
2. enforce tenant filters server-side regardless of request params
3. Moderator exports support global + province/barangay filters
4. Prevent user-supplied IDs from escaping their scope

## Frontend / UI Refactor Plan (Role-Based + Tenant-Aware)

## 1. Tenant-Aware Navigation and URL Generation

Frontend must stop assuming `/admin/...`.

Changes:

1. Generate links using tenant-aware route helpers
2. Display current tenant badge in header (`Province / Barangay`)
3. Add Moderator portal navigation separate from tenant navigation
4. Add tenant switcher for Moderator (and optionally Provincial Admin if multi-barangay view is enabled)

## 2. Role-Based UI Composition

Implement role-aware menu and dashboard rendering:

1. Moderator sees platform modules only
2. Provincial admin sees province-wide and child-barangay modules
3. Barangay admin sees local operational modules only

Recommendations:

1. centralize permissions in Blade helpers / view models
2. provide a `CurrentAccessContext` payload to layouts
3. hide disabled modules and block server-side access separately (never rely on UI only)

## 3. Tenant-Aware Login Pages

New pages required:

1. Moderator login page
2. Tenant login page with tenant branding and slug context
3. Province/barangay onboarding pages (Moderator access)
4. Invite acceptance flow with tenant-aware redirects

## 4. Forms and Dropdowns (Isolation-Safe UX)

All forms must load only in-scope entities:

1. inventory dropdowns
2. supplier lists
3. barangay lists
4. user assignment lists
5. export filters

Rule:

1. backend filters first
2. frontend only displays what backend returns

## 5. Dashboard Components and Analytics Widgets

Refactor dashboards to support dynamic role widgets:

1. widget registry keyed by role + scope
2. tenant-aware API endpoints
3. consistent empty states for new tenants with no data
4. province aggregation vs barangay detail views

## Step-by-Step Data Migration Strategy (Existing Data -> SaaS)

## Phase 0. Pre-Migration Preparation

1. Freeze schema changes outside SaaS work
2. Inventory all tables and identify tenant-owned rows
3. Decide canonical mapping of current `branches` to future province/barangay model
4. Define default province for existing deployment data
5. Back up production database and validate restore procedure
6. Create migration rehearsal environment with production-like snapshot

## Phase 1. Introduce Tenant Core Tables (Non-Breaking)

1. Create `provinces`
2. Add `province_id`, `slug`, and active flags to `barangays`
3. Create `tenant_memberships`, `roles`, `role_assignments` (or equivalent scoped RBAC tables)
4. Keep all new columns nullable initially

## Phase 2. Add Tenant Columns to Existing Tables (Non-Breaking)

1. Add nullable `province_id` and `barangay_id` to tenant-owned tables
2. Add indexes but not strict `NOT NULL` constraints yet
3. Backfill in batches

Backfill rules:

1. derive `province_id` from mapped branch/barangay data
2. derive `barangay_id` where row is barangay-specific
3. for province-scoped rows, set `barangay_id = null` and document the rule

## Phase 3. Backfill User Scope and Roles

1. Map Moderator account(s) from current superadmin/global admins
2. Map existing branch admins to provisional `barangay_admin` or `province_admin` roles based on business rules
3. Populate `tenant_memberships`
4. Populate `role_assignments`
5. Keep legacy `user_level_id` temporarily

## Phase 4. Dual-Read / Dual-Write Application Layer

1. Introduce `TenantContext` middleware and scoped routes
2. Update services/repositories to read by tenant keys
3. For a short transition, write both legacy branch fields and new tenant fields where needed
4. Add monitoring to detect missing tenant keys on new writes

## Phase 5. Enable Tenant Routes and Limited Pilot

1. Create tenant-slug routes (`/{province}/{barangay}`)
2. Pilot with one province and selected barangays
3. Validate all modules:
   - login
   - dashboard
   - inventory
   - patient records
   - orders / requests / holds
   - suppliers
   - exports
4. Run leakage tests between pilot tenants

## Phase 6. Enforce Constraints and Cutover

1. Make tenant columns `NOT NULL` on tenant-owned tables (where applicable)
2. Add FK constraints to `provinces` / `barangays`
3. Remove or deprecate direct `branch_id` assumptions from services and routes
4. Make tenant routes the default entry point
5. Retire legacy `/admin` path or restrict to Moderator redirect only

## Phase 7. Post-Cutover Cleanup

1. Remove obsolete compatibility code
2. Retire `user_levels` if fully replaced
3. Remove hardcoded branch constants and RHU-specific assumptions
4. Update seeders/factories/tests for tenant keys
5. Update documentation and onboarding SOPs

## Backend Refactoring Checklist by Module (Do Not Miss)

## 1. Authentication / Accounts

1. Tenant slug resolver middleware
2. Membership-aware login
3. Scoped redirects in `AuthSessionService`
4. Invite and password reset tenant-aware links
5. Session tenant binding and validation

## 2. Users / Manage Account

1. Replace branch assignment UX with province/barangay membership assignment
2. Add Moderator account creation for province/barangay admins
3. Add activation/suspension controls per tenant
4. Add scoped role assignment UI

## 3. Inventory

1. Add tenant keys to inventory and movements
2. Replace `branch_id` assumptions with tenant-aware facility scope
3. Restrict transfer operations to allowed tenant boundaries
4. Update inventory exports to be tenant-scoped

## 4. Patient Records

1. Tenant-scope all patient records and dispensed medications
2. Validate barangay belongs to current province
3. Restrict patient exports by tenant scope
4. Update dashboard queries relying on patient records joins

## 5. Orders / Holds / Requests

1. Tenant keys on parent and child records
2. Prevent cross-tenant request/hold references
3. Province-level workflows for cross-barangay operations (if enabled)
4. Scoped analytics and lists

## 6. Suppliers

1. Tenant-scope suppliers and `supplier_products` (now inventory-linked)
2. Ensure linked inventory belongs to same tenant
3. Tenant-aware supplier exports and dashboards

## 7. Analytics / Dashboard

1. Rewrite branch filters to tenant filters
2. Add province aggregation queries
3. Add Moderator global analytics
4. Cache metrics with tenant-prefixed keys

## 8. Notifications / Audit / History

1. Add tenant metadata to all events/logs
2. Scope notification feeds to current tenant
3. Moderator cross-tenant audit search
4. Alerting for suspicious cross-tenant access attempts

## 9. Exports / Reports

1. Tenant-safe query scoping in all exports
2. Include tenant scope in exported headers
3. Block arbitrary ID filters escaping tenant scope
4. Test export leakage explicitly

## Testing Strategy (Mandatory for SaaS)

## 1. Automated Test Coverage Requirements

### Unit Tests

1. Tenant resolver
2. Tenant context builder
3. Scoped permission evaluator
4. Tenant-aware repository filters

### Feature Tests

1. Moderator can access all tenant dashboards
2. Provincial admin cannot access other provinces
3. Barangay admin cannot access other barangays in same province
4. Tenant login via slug works
5. Invalid slug returns 404 or branded error page
6. Cross-tenant ID submission is rejected (422/403)

### Regression Tests for Current Modules

1. inventory CRUD
2. patient records CRUD
3. orders/holds/requests workflow
4. supplier linking
5. exports (inventory/patient/supplier)
6. analytics endpoints

## 2. Data Leakage Test Matrix (Do Not Skip)

Test each module with at least two provinces and two barangays each.

Verify:

1. list endpoints
2. detail pages
3. create/update/delete actions
4. exports
5. AJAX analytics APIs
6. background notifications
7. audit logs

## 3. Performance and Load Testing

1. Province-level dashboard aggregation performance
2. Tenant route resolution overhead
3. Export generation under multi-tenant load
4. Queue throughput with tenant-tagged jobs

## DevOps / Operations Plan for SaaS Readiness

## 1. Environment and Config

1. Add tenancy config (`config/tenancy.php`)
2. Centralize tenant route settings and slug behavior
3. Add feature flags for phased rollout

## 2. Observability

1. Include tenant IDs/slugs in logs
2. Tag errors by tenant scope
3. Dashboard for failed jobs by tenant
4. Audit trail for Moderator actions and impersonation

## 3. Security Controls

1. Enforce authorization policies on all tenant routes
2. Rate limit login by tenant slug + IP
3. Add account lockouts / alerts for repeated failures
4. Log and alert cross-tenant access attempts

## 4. Backup / Restore and Support

1. Document full restore process
2. Support tenant-targeted data export for incident response
3. Consider logical tenant export/import tooling for onboarding/migrations

## Implementation Roadmap (Phased Delivery)

## Phase A. Foundation (2-4 weeks)

1. Tenant architecture decisions finalized
2. New schema for provinces/barangays/roles/memberships
3. Tenant resolver + middleware prototypes
4. Moderator route group and skeleton pages

Deliverables:

1. tenant schema migrations
2. tenant route middleware
3. Moderator portal shell
4. RBAC design docs and seed templates

## Phase B. Auth + RBAC + Routing (2-4 weeks)

1. Tenant slug login flows
2. Scoped role assignments
3. Tenant-aware redirects
4. Route helper migration

Deliverables:

1. moderator login
2. tenant login by slug
3. scoped authorization layer
4. integration tests for login and route access

## Phase C. Core Module Tenant Refactor (4-8 weeks)

1. Inventory
2. Patient Records
3. Orders / Holds / Requests
4. Suppliers
5. Notifications / Audit / Exports

Deliverables:

1. tenant-safe repositories/services
2. tenant-aware UI pages
3. regression + isolation test suite

## Phase D. Dashboards and Analytics by Role (2-4 weeks)

1. Moderator dashboard
2. Provincial dashboard
3. Barangay dashboard
4. analytics caching and performance tuning

Deliverables:

1. role-specific dashboards
2. province aggregation endpoints
3. monitoring dashboards

## Phase E. Data Migration + Pilot + Cutover (2-6 weeks)

1. production data backfill rehearsal
2. pilot province rollout
3. issue fixes and hardening
4. full cutover

Deliverables:

1. migration scripts
2. runbook
3. rollback plan
4. final sign-off checklist

## Infrastructure and Storage Isolation

## 1. File Storage Tenant Isolation

All tenant-owned files must be isolated by tenant scope.

### Storage Structure

```
storage/app/
├── tenants/
│   ├── provinces/
│   │   └── {province_id}/
│   │       ├── exports/
│   │       ├── imports/
│   │       └── documents/
│   └── barangays/
│       └── {province_id}/
│           └── {barangay_id}/
│               ├── exports/
│               ├── imports/
│               ├── attachments/
│               └── patient_documents/
```

### Implementation

1. Create `TenantStorageService` for path generation
2. Override default `Storage::disk()` calls for tenant files
3. Validate file access belongs to current tenant
4. Add signed URLs for secure file downloads

```php
class TenantStorageService
{
    public function tenantPath(string $path, TenantContext $ctx): string
    {
        if ($ctx->isBarangay()) {
            return "tenants/barangays/{$ctx->provinceId}/{$ctx->barangayId}/{$path}";
        }
        return "tenants/provinces/{$ctx->provinceId}/{$path}";
    }
}
```

## 2. Queue and Job Context Propagation

All queued jobs must carry and restore tenant context.

### Job Base Class

```php
abstract class TenantAwareJob implements ShouldQueue
{
    public int $tenantProvinceId;
    public ?int $tenantBarangayId;
    public string $tenantScopeType;

    public function __construct(TenantContext $ctx)
    {
        $this->tenantProvinceId = $ctx->provinceId;
        $this->tenantBarangayId = $ctx->barangayId;
        $this->tenantScopeType = $ctx->scopeType;
    }

    protected function getTenantContext(): TenantContext
    {
        return new TenantContext(
            $this->tenantScopeType,
            $this->tenantProvinceId,
            $this->tenantBarangayId
        );
    }
}
```

### Job Middleware

```php
class SetTenantContextMiddleware
{
    public function handle($job, $next)
    {
        if ($job instanceof TenantAwareJob) {
            app()->instance(TenantContext::class, $job->getTenantContext());
        }
        $next($job);
    }
}
```

## 3. Cache Tenant Namespacing

All cached data must be namespaced by tenant.

### Cache Key Pattern

```php
class TenantCacheKey
{
    public static function make(string $key, TenantContext $ctx): string
    {
        if ($ctx->isBarangay()) {
            return "tenant:{$ctx->provinceId}:{$ctx->barangayId}:{$key}";
        }
        if ($ctx->isProvince()) {
            return "tenant:{$ctx->provinceId}:*:{$key}";
        }
        return "platform:{$key}";
    }
}
```

### Cache Service

```php
class TenantCacheService
{
    public function remember(TenantContext $ctx, string $key, int $ttl, Closure $callback): mixed
    {
        return Cache::remember(
            TenantCacheKey::make($key, $ctx),
            $ttl,
            $callback
        );
    }

    public function forgetTenant(TenantContext $ctx): void
    {
        $pattern = TenantCacheKey::make('*', $ctx);
        // Use cache tag or pattern-based invalidation
    }
}
```

## 4. Email and Notification Tenant Customization

### Tenant Email Settings

Add to `barangays.settings_json`:

```json
{
    "email": {
        "from_name": "Barangay Malolos Health Center",
        "from_address": "malolos@health.gov.ph",
        "reply_to": "malolos-reply@health.gov.ph",
        "logo_url": "/storage/tenants/.../logo.png"
    }
}
```

### Mailable Tenant Awareness

```php
abstract class TenantMailable extends Mailable
{
    protected TenantContext $tenantContext;

    public function tenantContext(TenantContext $ctx): self
    {
        $this->tenantContext = $ctx;
        return $this;
    }

    protected function getTenantBranding(): array
    {
        $barangay = Barngay::find($this->tenantContext->barangayId);
        return $barangay->settings_json['email'] ?? [];
    }
}
```

## API and Integration Considerations

## 1. API Versioning for Multi-Tenant

### API Route Structure

```
/api/v1/tenant/{province}/{barangay}/...
/api/v1/moderator/...
```

### API Authentication

1. Use Laravel Sanctum or Passport with tenant-scoped tokens
2. Store tenant context in token abilities
3. Validate token tenant matches request tenant

```php
'abilities' => [
    'tenant:read',
    'tenant:write',
    'province:1',
    'barangay:5'
]
```

## 2. Webhook Configuration Per Tenant

### Webhooks Table

```sql
CREATE TABLE tenant_webhooks (
    id BIGINT PRIMARY KEY,
    province_id BIGINT,
    barangay_id BIGINT NULL,
    event_type VARCHAR(100),
    endpoint_url VARCHAR(500),
    secret VARCHAR(100),
    is_active BOOLEAN,
    last_triggered_at TIMESTAMP,
    failure_count INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Webhook Events

1. `inventory.low_stock`
2. `order.created`
3. `order.completed`
4. `request.approved`
5. `patient.dispensed`
6. `user.invited`

## 3. Tenant Feature Flags

### Feature Flags Table

```sql
CREATE TABLE tenant_features (
    id BIGINT PRIMARY KEY,
    province_id BIGINT NULL,
    barangay_id BIGINT NULL,
    feature_key VARCHAR(100),
    enabled BOOLEAN DEFAULT FALSE,
    settings_json JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Feature Service

```php
class TenantFeatureService
{
    public function isEnabled(TenantContext $ctx, string $feature): bool
    {
        $flag = TenantFeature::where('feature_key', $feature)
            ->where(function ($q) use ($ctx) {
                $q->where('barangay_id', $ctx->barangayId)
                  ->orWhere('province_id', $ctx->provinceId);
            })
            ->first();

        return $flag?->enabled ?? config("features.defaults.{$feature}", false);
    }
}
```

## Security and Compliance

## 1. Two-Factor Authentication (2FA) Per Tenant

### 2FA Configuration

```php
// In tenant settings
'two_factor' => [
    'enabled' => true,
    'required_for_roles' => ['barangay_admin', 'province_admin'],
    'methods' => ['totp', 'sms'],
    'remember_device_days' => 30
]
```

### Implementation

1. Use Laravel Fortify or custom 2FA
2. Store 2FA secrets encrypted per user
3. Validate 2FA on tenant-scoped login
4. Option to require 2FA for sensitive operations

## 2. Session Security and Tenant Switching

### Session Configuration

```php
// config/session.php
'tenant' => [
    'bind_to_tenant' => true,
    'invalidate_on_tenant_change' => true,
    'max_sessions_per_tenant' => 3,
]
```

### Session Table Enhancement

```sql
ALTER TABLE sessions ADD COLUMN tenant_province_id BIGINT NULL;
ALTER TABLE sessions ADD COLUMN tenant_barangay_id BIGINT NULL;
ALTER TABLE sessions ADD COLUMN tenant_scope_type VARCHAR(20) NULL;
```

### Moderator Tenant Switching

```php
class TenantSwitchService
{
    public function switchTo(User $moderator, TenantContext $target): void
    {
        if (!$moderator->isModerator()) {
            throw new UnauthorizedException();
        }

        session([
            'tenant.switched_from' => 'platform',
            'tenant.province_id' => $target->provinceId,
            'tenant.barangay_id' => $target->barangayId,
            'tenant.scope_type' => $target->scopeType,
        ]);

        AuditLog::create([
            'action' => 'tenant_switch',
            'user_id' => $moderator->id,
            'target_province_id' => $target->provinceId,
            'target_barangay_id' => $target->barangayId,
        ]);
    }
}
```

## 3. Data Archiving and Retention

### Archiving Strategy

1. Archive inactive tenant data after defined period
2. Move to cold storage (S3 Glacier equivalent)
3. Maintain audit trail of archived records
4. Provide restore functionality for moderators

### Archive Tables

```sql
CREATE TABLE archived_records (
    id BIGINT PRIMARY KEY,
    province_id BIGINT,
    barangay_id BIGINT,
    source_table VARCHAR(100),
    record_id BIGINT,
    archived_data JSON,
    archived_at TIMESTAMP,
    archived_by BIGINT,
    retention_until DATE
);
```

### Retention Policies

```php
// Per-tenant retention configuration
'retention' => [
    'patient_records' => 365 * 7,    // 7 years
    'audit_logs' => 365 * 5,          // 5 years
    'notifications' => 90,            // 90 days
    'exports' => 30,                  // 30 days
]
```

## 4. Compliance Requirements (Data Privacy)

### PII Handling

1. Mark PII fields in models
2. Encrypt sensitive patient data at rest
3. Audit all PII access
4. Support data export for subject access requests
5. Support data deletion for right to be forgotten

### Compliance Table

```sql
CREATE TABLE data_subject_requests (
    id BIGINT PRIMARY KEY,
    province_id BIGINT,
    barangay_id BIGINT,
    request_type ENUM('access', 'deletion', 'correction', 'portability'),
    status ENUM('pending', 'processing', 'completed', 'rejected'),
    requested_by_email VARCHAR(255),
    verified_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    notes TEXT
);
```

## Tenant Usage and Limits

## 1. Usage Quotas Per Tenant

### Quota Configuration

```php
'quotas' => [
    'barangay' => [
        'users' => 10,
        'inventory_items' => 5000,
        'patient_records' => 50000,
        'storage_mb' => 500,
        'api_calls_daily' => 10000,
        'exports_monthly' => 100,
    ],
    'province' => [
        'users' => 100,
        'barangays' => 50,
        'storage_mb' => 5000,
        'api_calls_daily' => 100000,
    ]
]
```

### Usage Tracking Table

```sql
CREATE TABLE tenant_usage (
    id BIGINT PRIMARY KEY,
    province_id BIGINT,
    barangay_id BIGINT NULL,
    metric_key VARCHAR(100),
    metric_value BIGINT,
    period_start DATE,
    period_end DATE,
    updated_at TIMESTAMP
);
```

### Usage Service

```php
class TenantUsageService
{
    public function increment(TenantContext $ctx, string $metric, int $count = 1): void
    {
        TenantUsage::updateOrCreate(
            [
                'province_id' => $ctx->provinceId,
                'barangay_id' => $ctx->barangayId,
                'metric_key' => $metric,
                'period_start' => now()->startOfMonth(),
            ],
            ['metric_value' => DB::raw('metric_value + ' . $count)]
        );
    }

    public function isOverQuota(TenantContext $ctx, string $metric): bool
    {
        $current = $this->getCurrentUsage($ctx, $metric);
        $limit = $this->getQuotaLimit($ctx, $metric);
        return $current >= $limit;
    }
}
```

## 2. Tenant Health Monitoring

### Health Metrics

1. Database query performance
2. Queue job success rate
3. API response times
4. Error rates
5. Storage usage
6. User activity levels

### Health Check Table

```sql
CREATE TABLE tenant_health (
    id BIGINT PRIMARY KEY,
    province_id BIGINT,
    barangay_id BIGINT NULL,
    check_type VARCHAR(50),
    status ENUM('healthy', 'degraded', 'unhealthy'),
    details JSON,
    checked_at TIMESTAMP
);
```

### Health Check Command

```php
php artisan tenant:health-check
php artisan tenant:health-check --province=1
php artisan tenant:health-check --barangay=5
```

## 3. Incident Response for Tenant Issues

### Incident Types

1. Data breach suspected
2. Cross-tenant access detected
3. Tenant data corruption
4. Service degradation for specific tenant
5. Security vulnerability exploited

### Incident Response Table

```sql
CREATE TABLE tenant_incidents (
    id BIGINT PRIMARY KEY,
    province_id BIGINT,
    barangay_id BIGINT NULL,
    incident_type VARCHAR(100),
    severity ENUM('low', 'medium', 'high', 'critical'),
    status ENUM('open', 'investigating', 'resolved', 'closed'),
    description TEXT,
    resolution TEXT,
    reported_by BIGINT,
    assigned_to BIGINT NULL,
    created_at TIMESTAMP,
    resolved_at TIMESTAMP NULL
);
```

### Incident Response Workflow

1. Automated detection via monitoring
2. Create incident record
3. Notify moderators
4. Investigate and document
5. Apply containment measures
6. Resolve and post-mortem
7. Update security measures

## Tenant Onboarding and Offboarding

## 1. New Tenant Onboarding Workflow

### Onboarding Steps

1. **Provisioning** (Moderator initiates)
   - Create province record
   - Configure province settings
   - Set up initial barangays
   - Create admin user accounts
   - Send invitation emails

2. **Configuration** (Provincial Admin)
   - Complete profile setup
   - Configure province-wide settings
   - Add additional barangays
   - Set up suppliers
   - Import initial inventory (if applicable)

3. **Activation** (Moderator approves)
   - Review configuration
   - Enable tenant routes
   - Activate memberships
   - Send welcome notification

### Onboarding State Machine

```
pending -> provisioning -> configured -> review -> active
           ↓                ↓            ↓
        cancelled       draft        rejected
```

### Onboarding Table

```sql
CREATE TABLE tenant_onboarding (
    id BIGINT PRIMARY KEY,
    province_id BIGINT,
    status ENUM('pending', 'provisioning', 'configured', 'review', 'active', 'rejected', 'cancelled'),
    current_step VARCHAR(50),
    completed_steps JSON,
    notes TEXT,
    created_by BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    activated_at TIMESTAMP NULL
);
```

### Onboarding Checklist

```php
'onboarding_checklist' => [
    'province_created' => false,
    'barangays_added' => false,
    'admin_accounts_created' => false,
    'invitations_sent' => false,
    'settings_configured' => false,
    'suppliers_added' => false,
    'inventory_initialized' => false,
    'review_completed' => false,
]
```

## 2. Tenant Deactivation and Suspension Flow

### Suspension Types

1. **Voluntary** - Tenant requests temporary pause
2. **Payment** - Subscription/billing issue
3. **Compliance** - Policy violation
4. **Security** - Security concern
5. **Administrative** - Moderator decision

### Suspension Process

```php
class TenantSuspensionService
{
    public function suspend(int $provinceId, ?int $barangayId, string $reason, string $type): void
    {
        DB::transaction(function () use ($provinceId, $barangayId, $reason, $type) {
            // Update tenant status
            $this->updateTenantStatus($provinceId, $barangayId, 'suspended');

            // Revoke all active sessions
            $this->revokeSessions($provinceId, $barangayId);

            // Suspend memberships
            $this->suspendMemberships($provinceId, $barangayId);

            // Cancel pending jobs
            $this->cancelPendingJobs($provinceId, $barangayId);

            // Create audit record
            $this->createAuditRecord($provinceId, $barangayId, $reason, $type);

            // Send notification
            $this->notifyAffectedUsers($provinceId, $barangayId, $reason);
        });
    }

    public function reactivate(int $provinceId, ?int $barangayId): void
    {
        // Reverse suspension steps
    }
}
```

### Suspension Table

```sql
CREATE TABLE tenant_suspensions (
    id BIGINT PRIMARY KEY,
    province_id BIGINT,
    barangay_id BIGINT NULL,
    suspension_type ENUM('voluntary', 'payment', 'compliance', 'security', 'administrative'),
    reason TEXT,
    suspended_by BIGINT,
    suspended_at TIMESTAMP,
    reactivated_by BIGINT NULL,
    reactivated_at TIMESTAMP NULL
);
```

## 3. Tenant Data Export/Import for Migration

### Export Service

```php
class TenantExportService
{
    public function exportTenantData(TenantContext $ctx, array $options = []): string
    {
        $data = [
            'metadata' => [
                'exported_at' => now()->toIso8601String(),
                'province_id' => $ctx->provinceId,
                'barangay_id' => $ctx->barangayId,
                'version' => '1.0',
            ],
            'province' => $this->exportProvince($ctx->provinceId),
            'barangays' => $this->exportBarangays($ctx),
            'users' => $this->exportUsers($ctx),
            'inventory' => $this->exportInventory($ctx),
            'patients' => $this->exportPatients($ctx),
            'orders' => $this->exportOrders($ctx),
            'suppliers' => $this->exportSuppliers($ctx),
            // ... other entities
        ];

        $filename = "tenant_export_{$ctx->provinceId}_" . now()->format('Ymd_His') . '.json';
        Storage::put("exports/{$filename}", json_encode($data, JSON_PRETTY_PRINT));

        return $filename;
    }
}
```

### Import Service

```php
class TenantImportService
{
    public function importTenantData(string $filePath, array $options = []): ImportResult
    {
        $data = json_decode(Storage::get($filePath), true);

        DB::transaction(function () use ($data, $options) {
            // Validate import data structure
            $this->validateImportData($data);

            // Map old IDs to new IDs
            $idMap = [];

            // Import in dependency order
            $idMap['provinces'] = $this->importProvinces($data['province'], $options);
            $idMap['barangays'] = $this->importBarangays($data['barangays'], $idMap, $options);
            $idMap['users'] = $this->importUsers($data['users'], $idMap, $options);
            // ... continue with dependencies
        });
    }
}
```

## 4. Tenant Invitation System

### Invitation Flow

1. Admin creates invitation with email and role
2. System generates unique token
3. Email sent with tenant-specific invitation link
4. User accepts invitation and creates account
5. Membership and role assignment completed

### Invitations Table

```sql
CREATE TABLE tenant_invitations (
    id BIGINT PRIMARY KEY,
    province_id BIGINT,
    barangay_id BIGINT NULL,
    email VARCHAR(255),
    role_id BIGINT,
    token VARCHAR(64) UNIQUE,
    invited_by BIGINT,
    status ENUM('pending', 'accepted', 'expired', 'cancelled'),
    expires_at TIMESTAMP,
    accepted_at TIMESTAMP NULL,
    accepted_by BIGINT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Invitation Service

```php
class TenantInvitationService
{
    public function create(TenantContext $ctx, string $email, int $roleId): TenantInvitation
    {
        $invitation = TenantInvitation::create([
            'province_id' => $ctx->provinceId,
            'barangay_id' => $ctx->barangayId,
            'email' => $email,
            'role_id' => $roleId,
            'token' => Str::random(64),
            'invited_by' => Auth::id(),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($email)->send(new TenantInvitationMail($invitation));

        return $invitation;
    }

    public function accept(string $token, User $user): void
    {
        $invitation = TenantInvitation::where('token', $token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        DB::transaction(function () use ($invitation, $user) {
            // Create membership
            TenantMembership::create([
                'user_id' => $user->id,
                'scope_type' => $invitation->barangay_id ? 'barangay' : 'province',
                'scope_id' => $invitation->barangay_id ?? $invitation->province_id,
                'status' => 'active',
            ]);

            // Create role assignment
            RoleAssignment::create([
                'user_id' => $user->id,
                'role_id' => $invitation->role_id,
                'scope_type' => $invitation->barangay_id ? 'barangay' : 'province',
                'scope_id' => $invitation->barangay_id ?? $invitation->province_id,
            ]);

            // Mark invitation as accepted
            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'accepted_by' => $user->id,
            ]);
        });
    }
}
```

## Billing and Subscription (Future-Ready)

## 1. Subscription Model

### Subscriptions Table

```sql
CREATE TABLE tenant_subscriptions (
    id BIGINT PRIMARY KEY,
    province_id BIGINT,
    barangay_id BIGINT NULL,
    plan_id VARCHAR(50),
    status ENUM('trial', 'active', 'past_due', 'cancelled', 'expired'),
    billing_cycle ENUM('monthly', 'annual'),
    current_period_start TIMESTAMP,
    current_period_end TIMESTAMP,
    trial_ends_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    stripe_subscription_id VARCHAR(255) NULL,
    stripe_customer_id VARCHAR(255) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Plans Configuration

```php
'plans' => [
    'barangay_basic' => [
        'name' => 'Barangay Basic',
        'price_monthly' => 0,
        'price_annual' => 0,
        'features' => ['inventory', 'patients', 'orders'],
        'limits' => [
            'users' => 5,
            'inventory_items' => 1000,
            'patient_records' => 5000,
        ],
    ],
    'barangay_pro' => [
        'name' => 'Barangay Pro',
        'price_monthly' => 2999,
        'price_annual' => 29990,
        'features' => ['inventory', 'patients', 'orders', 'suppliers', 'analytics', 'exports'],
        'limits' => [
            'users' => 10,
            'inventory_items' => 5000,
            'patient_records' => 50000,
        ],
    ],
    'province_enterprise' => [
        'name' => 'Province Enterprise',
        'price_monthly' => 19999,
        'price_annual' => 199990,
        'features' => ['all'],
        'limits' => [
            'users' => -1,
            'barangays' => -1,
            'inventory_items' => -1,
        ],
    ],
]
```

## 2. Billing Hooks

```php
class BillingService
{
    public function onSubscriptionCreated(TenantSubscription $subscription): void
    {
        // Activate tenant
        // Enable features
        // Send welcome email
    }

    public function onSubscriptionPastDue(TenantSubscription $subscription): void
    {
        // Send payment reminder
        // Apply grace period
    }

    public function onSubscriptionCancelled(TenantSubscription $subscription): void
    {
        // Schedule deactivation
        // Send cancellation notice
        // Retain data for recovery period
    }

    public function onSubscriptionExpired(TenantSubscription $subscription): void
    {
        // Suspend tenant
        // Downgrade to limited access
    }
}
```

## Complete Implementation Checklist

## Phase A: Foundation (Weeks 1-4)

- [ ] Create `provinces` table migration
- [ ] Create `tenant_memberships` table migration
- [ ] Create `roles` and `role_assignments` table migrations
- [ ] Update `barangays` table with province linkage
- [ ] Add tenant columns to all tenant-owned tables
- [ ] Create `TenantContext` value object
- [ ] Create `TenantResolver` service
- [ ] Create tenant resolution middleware
- [ ] Create tenant membership middleware
- [ ] Create tenant context binding middleware
- [ ] Set up `config/tenancy.php`
- [ ] Create `TenantScoped` model trait
- [ ] Create route helpers (`tenant_route()`, `moderator_route()`)
- [ ] Set up moderator route group

## Phase B: Authentication & RBAC (Weeks 5-8)

- [ ] Create moderator login page
- [ ] Create tenant login page with slug resolution
- [ ] Implement tenant-aware authentication
- [ ] Update `AuthSessionService` for tenant redirects
- [ ] Create tenant-aware password reset
- [ ] Create invitation system
- [ ] Implement scoped permission evaluation
- [ ] Create role assignment management
- [ ] Migrate from `user_levels` to scoped roles
- [ ] Add 2FA support (optional)

## Phase C: Core Modules (Weeks 9-16)

- [ ] Refactor `InventoryAdminService` for tenant context
- [ ] Refactor `PatientRecordsAdminService` for tenant context
- [ ] Refactor `OrderAdminService` for tenant context
- [ ] Refactor `RequestWorkflowService` for tenant context
- [ ] Refactor `ManageAccountAdminService` for tenant context
- [ ] Refactor `NotificationService` for tenant context
- [ ] Update all repositories with tenant scopes
- [ ] Update validation rules for tenant constraints
- [ ] Add tenant keys to background jobs
- [ ] Implement tenant-aware exports

## Phase D: Frontend (Weeks 17-20)

- [ ] Create tenant navigation component
- [ ] Create moderator navigation component
- [ ] Add tenant badge to layouts
- [ ] Update all route generation in views
- [ ] Create role-based menu rendering
- [ ] Update form dropdowns for tenant filtering
- [ ] Create tenant onboarding UI
- [ ] Create tenant settings UI

## Phase E: Dashboards (Weeks 21-24)

- [ ] Create Moderator dashboard
- [ ] Create Provincial Admin dashboard
- [ ] Update Barangay Admin dashboard
- [ ] Implement tenant-aware analytics
- [ ] Add tenant caching

## Phase F: Data Migration (Weeks 25-30)

- [ ] Create data backfill scripts
- [ ] Map existing branches to provinces/barangays
- [ ] Backfill user memberships
- [ ] Backfill role assignments
- [ ] Run validation scripts
- [ ] Run pre/post-backfill reconciliation reports (row counts + sampled records) for tenant-owned tables
- [ ] Validate canonical slug redirects and route/slug collision handling before pilot rollout
- [ ] Rehearse cutover and rollback in staging using a production-like snapshot
- [ ] Add alerts for new writes missing tenant keys during dual-write transition
- [ ] Execute pilot migration
- [ ] Perform full migration
- [ ] Add NOT NULL constraints
- [ ] Enable FK constraints

## Phase G: Testing & Hardening (Weeks 31-36)

- [ ] Unit tests for tenant resolver
- [ ] Unit tests for tenant context
- [ ] Feature tests for tenant isolation
- [ ] Feature tests for login flows
- [ ] Feature tests for permissions
- [ ] Data leakage test matrix
- [ ] Performance testing
- [ ] Security audit
- [ ] Test tenant-aware password reset, email verification, and invitation links
- [ ] Test Moderator tenant switching / impersonation with audit trail and session isolation
- [ ] Test cache/session invalidation after role or membership changes

## Phase H: Production Readiness (Weeks 37-40)

- [ ] Add tenant health monitoring
- [ ] Add tenant usage tracking
- [ ] Add incident response workflows
- [ ] Create operational runbook
- [ ] Set up monitoring dashboards
- [ ] Document tenant onboarding SOP
- [ ] Document incident response SOP
- [ ] Perform backup restore drill and verify tenant-targeted recovery/export procedure
- [ ] Document queue worker drain/restart procedure for tenant-aware deployments
- [ ] Configure feature-flag kill switches for staged rollout / rollback
- [ ] Approve cutover freeze window and rollback trigger criteria
- [ ] Final security review

## Rollback and Risk Mitigation Plan

## Key Risks

1. Cross-tenant data leakage due to missed query scopes
2. Broken routes/links after slug migration
3. Export endpoints bypassing tenant filters
4. Analytics performance degradation under province aggregation
5. Incorrect backfill mapping from branches to province/barangay

## Mitigations

1. Tenant scope middleware + repository defaults + policies (defense in depth)
2. Feature flags and staged rollout
3. Dual-read validation logs during transition
4. Data reconciliation scripts and sample audits
5. Dedicated leakage test matrix before pilot and before cutover

## Definition of Done (SaaS Conversion)

The SaaS implementation is complete when all conditions below are true:

1. Moderator, Provincial Admin, and Barangay Admin can log in via their intended routes
2. Tenant data isolation is enforced across all modules and exports
3. No tenant-owned table can be written without tenant keys
4. All existing core workflows operate within tenant context
5. Dashboards are role-specific and scope-accurate
6. Migration of existing data is completed and validated
7. Automated tests cover tenant access controls and isolation regression
8. Operational monitoring and audit trails include tenant metadata

## Recommended Immediate Next Steps (Execution Order)

1. Confirm business rules for province/barangay relationships and what `branch` represents in the new model
2. Decide whether `barangays` table will be upgraded in-place (recommended) or replaced
3. Approve RBAC migration design (`user_levels` transition vs direct replacement)
4. Build `provinces`, `barangays` upgrades, and `tenant_memberships` schema first
5. Implement tenant slug resolver + middleware before touching module logic
6. Pilot refactor on one module first (recommended: `suppliers` or `inventory`) to establish patterns

## Rate Limiting Strategy

## 1. Per-Tenant Rate Limiting

### Laravel Rate Limiter Configuration

```php
// App\Providers\AppServiceProvider.php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot()
{
    RateLimiter::for('tenant-api', function (Request $request) {
        $ctx = app(TenantContext::class);
        $tenantKey = $ctx->provinceId . ':' . ($ctx->barangayId ?? 'province');
        return Limit::perMinute(100)->by($tenantKey . ':' . $request->ip());
    });

    RateLimiter::for('tenant-login', function (Request $request) {
        $province = $request->route('province');
        $barangay = $request->route('barangay');
        $tenantKey = $province . '/' . $barangay;
        return Limit::perMinute(5)->by($tenantKey . ':' . $request->ip());
    });

    RateLimiter::for('moderator-api', function (Request $request) {
        return Limit::perMinute(200)->by($request->user()->id);
    });

    RateLimiter::for('tenant-export', function (Request $request) {
        $ctx = app(TenantContext::class);
        $tenantKey = $ctx->provinceId . ':' . ($ctx->barangayId ?? 'province');
        return Limit::perHour(10)->by($tenantKey . ':' . $request->user()->id);
    });
}
```

### Route Application

```php
// routes/tenant.php
Route::middleware(['throttle:tenant-api'])->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index']);
    // ... other tenant API routes
});

Route::middleware(['throttle:tenant-export'])->group(function () {
    Route::get('/export/inventory', [ExportController::class, 'inventory']);
    Route::get('/export/patients', [ExportController::class, 'patients']);
});

// routes/moderator.php
Route::middleware(['throttle:moderator-api'])->group(function () {
    Route::get('/tenants', [TenantController::class, 'index']);
    // ... moderator routes
});
```

## 2. DDoS and Abuse Prevention

### Per-IP + Per-Tenant Combination

```php
RateLimiter::for('tenant-strict', function (Request $request) {
    $ctx = app(TenantContext::class);
    $tenantKey = $ctx->provinceId . ':' . ($ctx->barangayId ?? 'province');
    
    return [
        Limit::perMinute(60)->by($tenantKey),
        Limit::perMinute(120)->by($request->ip()),
    ];
});
```

### Login Attempt Tracking

```php
// Track failed login attempts per tenant
RateLimiter::for('tenant-login-fail', function (Request $request) {
    $province = $request->route('province');
    $barangay = $request->route('barangay');
    $email = $request->input('email');
    
    return Limit::perMinute(3)
        ->by("{$province}/{$barangay}:{$email}")
        ->response(function () {
            return response()->json([
                'message' => 'Too many login attempts. Please try again later.',
            ], 429);
        });
});
```

## Error Handling and Logging Strategy

## 1. Tenant-Aware Exception Handling

### Exception Handler

```php
// App\Exceptions\Handler.php

public function report(Throwable $exception)
{
    if (app()->bound(TenantContext::class)) {
        $ctx = app(TenantContext::class);
        Log::withContext([
            'tenant_province_id' => $ctx->provinceId,
            'tenant_barangay_id' => $ctx->barangayId,
            'tenant_scope' => $ctx->scopeType,
            'tenant_slug' => $ctx->provinceSlug . '/' . $ctx->barangaySlug,
        ]);
    }
    
    parent::report($exception);
}

public function render($request, Throwable $exception)
{
    if ($exception instanceof TenantNotFoundException) {
        return response()->view('errors.tenant-not-found', [], 404);
    }
    
    if ($exception instanceof TenantSuspendedException) {
        return response()->view('errors.tenant-suspended', [], 403);
    }
    
    if ($exception instanceof CrossTenantAccessException) {
        Log::warning('Cross-tenant access attempt', [
            'user_id' => Auth::id(),
            'target_province' => $request->input('province_id'),
            'target_barangay' => $request->input('barangay_id'),
        ]);
        
        return response()->json(['message' => 'Unauthorized'], 403);
    }
    
    return parent::render($request, $exception);
}
```

## 2. Custom Exception Classes

```php
namespace App\Exceptions;

class TenantNotFoundException extends \Exception {}
class TenantSuspendedException extends \Exception {}
class CrossTenantAccessException extends \Exception {}
class TenantQuotaExceededException extends \Exception {}
class InvalidTenantScopeException extends \Exception {}
```

## 3. Structured Logging

### Log Context Service

```php
class TenantLogContextService
{
    public static function enrich(array $context = []): array
    {
        $base = [];
        
        if (app()->bound(TenantContext::class)) {
            $ctx = app(TenantContext::class);
            $base['tenant'] = [
                'province_id' => $ctx->provinceId,
                'barangay_id' => $ctx->barangayId,
                'scope' => $ctx->scopeType,
            ];
        }
        
        if (Auth::check()) {
            $base['user_id'] = Auth::id();
            $base['user_email'] = Auth::user()->email;
        }
        
        $base['request_id'] = request()->header('X-Request-ID');
        $base['ip'] = request()->ip();
        
        return array_merge($base, $context);
    }
}

// Usage
Log::info('Inventory updated', TenantLogContextService::enrich([
    'inventory_id' => $inventory->id,
    'action' => 'stock_adjustment',
]));
```

### Log Output Format

```json
{
    "level": "INFO",
    "message": "Inventory updated",
    "timestamp": "2024-01-15T10:30:00Z",
    "tenant": {
        "province_id": 1,
        "barangay_id": 5,
        "scope": "barangay"
    },
    "user_id": 42,
    "user_email": "admin@malolos.health.gov.ph",
    "request_id": "abc-123-def",
    "ip": "192.168.1.100",
    "inventory_id": 1234,
    "action": "stock_adjustment"
}
```

## 4. Alerting Configuration

### Critical Alerts

```php
// Alert on suspicious activity
class SecurityAlertService
{
    public function alertCrossTenantAccess(array $context): void
    {
        Log::channel('security')->critical(
            'Cross-tenant access attempt detected',
            TenantLogContextService::enrich($context)
        );
        
        // Send notification to moderators
        Notification::route('mail', 'security@platform.com')
            ->notify(new SecurityAlert($context));
    }
    
    public function alertSuspiciousLoginPattern(array $context): void
    {
        Log::channel('security')->warning(
            'Suspicious login pattern detected',
            TenantLogContextService::enrich($context)
        );
    }
}
```

## Environment Configuration

## 1. Environment Variables

```env
# .env

# Tenancy Configuration
TENANCY_MODERATOR_PREFIX=moderator
TENANCY_INVITATION_EXPIRE_DAYS=7
TENANCY_CACHE_PREFIX=tenant
TENANCY_STORAGE_DISK=local

# Rate Limiting
TENANCY_RATE_LIMIT_API=100
TENANCY_RATE_LIMIT_LOGIN=5
TENANCY_RATE_LIMIT_EXPORT=10

# Session
TENANCY_SESSION_BIND_TENANT=true
TENANCY_SESSION_MAX_PER_TENANT=3

# Logging
TENANCY_LOG_CHANNEL=daily
TENANCY_LOG_SECURITY_CHANNEL=security
```

## 2. Feature Flags via Environment

```env
# Features
FEATURE_TWO_FACTOR_AUTH=true
FEATURE_TENANT_BRANDING=true
FEATURE_WEBHOOKS=false
FEATURE_API_ACCESS=true
FEATURE_CROSS_BARANGAY_REQUESTS=false
```

## Quick Reference Card

## Key Files to Create/Modify

| File | Purpose |
|------|---------|
| `config/tenancy.php` | Tenancy configuration |
| `app/Tenancy/TenantContext.php` | Context value object |
| `app/Tenancy/TenantResolver.php` | Tenant resolution |
| `app/Http/Middleware/ResolveTenantFromSlug.php` | Route middleware |
| `app/Http/Middleware/EnforceTenantMembership.php` | Access control |
| `app/Http/Middleware/BindTenantContext.php` | Context binding |
| `app/Models/Traits/TenantScoped.php` | Model scope trait |
| `app/Services/TenantStorageService.php` | File storage |
| `app/Services/TenantCacheService.php` | Cache namespacing |
| `app/Services/TenantFeatureService.php` | Feature flags |
| `app/Services/TenantUsageService.php` | Quota tracking |
| `app/helpers.php` | Helper functions |

## Key Artisan Commands

```bash
# Health & Monitoring
php artisan tenant:health-check
php artisan tenant:health-check --province=1
php artisan tenant:usage:report
php artisan tenant:usage:report --month=2024-01

# Cache Management
php artisan tenant:cache:clear
php artisan tenant:cache:clear --province=1

# Data Management
php artisan tenant:export --province=1 --barangay=5
php artisan tenant:backfill-tenant-keys

# Testing
php artisan tenant:test-isolation
php artisan tenant:test-leakage
```

## Database Migration Order

1. Create `provinces` table
2. Update `barangays` with `province_id`, `slug`
3. Create `tenant_memberships` table
4. Create `roles` and `role_assignments` tables
5. Add `province_id`/`barangay_id` to tenant-owned tables
6. Create supporting tables (invitations, suspensions, etc.)
7. Add indexes
8. Add foreign key constraints (after backfill)
