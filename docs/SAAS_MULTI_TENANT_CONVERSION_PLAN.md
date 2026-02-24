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

