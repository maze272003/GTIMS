# GTIMS Multi-Tenant SaaS Master Checklist

> **Last Updated:** 2026-02-27
> **Scope:** Country-wide multi-tenant SaaS — all PH provinces (82) and barangays (42,000+) as first-class tenants.

Consolidated from:

- `docs/MULTI_TENANT_SETUP.md`
- `docs/SAAS_MULTI_TENANT_CONVERSION_PLAN.md`

This checklist merges the implementation plan, setup guide, migration/cutover steps, testing, and operations tasks into one execution list. It also includes added recommendations for country-wide seeding, DemoSeeder entry point, reconciliation, rollback rehearsal, canonical slug validation, queue deployment procedure, and feature-flag rollback controls.

### Tenant Hierarchy

| Level | Role | Scope |
|-------|------|-------|
| 1 | **Moderator** | Platform — manages subscriptions, SaaS settings, creates/manages Super Admins |
| 2 | **Super Admin** | Province — handler for assigned province; manages Admins and users |
| 3 | **Admin** | Barangay — manages operations within a single barangay |
| 4 | **Staff / Users** | Barangay — strictly isolated tenant data |

## 1. Decisions and Scope Lock (Do First)

- [x] ✅ Confirm business rules for province/barangay relationships and what current `branches` represent in the target model
- [x] ✅ Decide whether to upgrade `barangays` in place (recommended) or replace with a new tenant barangays table
- [x] ✅ Approve RBAC transition path (`user_levels` transition vs direct replacement)
- [x] ✅ Confirm tenant route model: `/{provinceSlug}/{barangaySlug}` and moderator route model (`/moderator` vs admin subdomain)
- [x] ✅ Decide province-only admin route behavior (`/{provinceSlug}/admin` vs derive province from barangay routes)
- [x] ✅ Define canonical slug rules and non-canonical redirect behavior
- [x] ✅ Decide supplier scoping business rule (province-scoped vs barangay-scoped vs hybrid)
- [x] ✅ Decide optional SaaS features for initial release vs later phases (2FA, webhooks, API access, billing)
- [x] ✅ Freeze non-SaaS schema changes during tenancy conversion work

## 2. Architecture and Isolation Baseline

- [x] ✅ Adopt single database / shared schema / row-level tenant partitioning strategy for v1 SaaS
- [x] ✅ Document optional future RLS enhancement (PostgreSQL) if applicable
- [x] ✅ Finalize tenant hierarchy and role scope boundaries:
- [x] ✅ Platform (Moderator) — manages subscriptions, SaaS settings, creates Super Admins
- [x] ✅ Province (Super Admin) — handler for assigned province; manages Admins and users
- [x] ✅ Barangay (Admin) — manages operations within assigned barangay
- [x] ✅ Barangay (Staff/Users) — strictly isolated tenant data
- [x] ✅ Classify data explicitly as shared, tenant-owned, or hybrid/configurable
- [x] ✅ Define strict data isolation defense-in-depth layers (routing, middleware, authz, repositories, services, validation, jobs, exports, audit logs)
- [x] ✅ Confirm all application routes use `/{provinceSlug}/{barangaySlug}/...` pattern (no non-tenant URLs like `/admin/dashboard`)
- [x] ✅ Enforce tenant resolution using `tenant.resolve` middleware that validates slugs, loads tenant context, blocks invalid access
- [x] ✅ Ensure every query is scoped to tenant keys (`province_id` + `barangay_id`) using global scopes and/or tenant service

## 3. Schema Foundation (Core Tenancy)

- [x] ✅ Create `provinces` table migration with fields/constraints documented in plan (`id`, `name`, `slug`, optional `code`, `is_active`, `settings_json`, timestamps, unique slug)
- [x] ✅ Upgrade `barangays` table with `province_id`, `slug`, `is_active`, `external_code`, `settings_json`
- [x] ✅ Add `barangays` constraints (`unique(province_id, slug)` and optional `unique(province_id, barangay_name)`)
- [x] ✅ Create `tenant_memberships` table migration with documented fields and `unique(user_id, scope_type, scope_id)`
- [x] ✅ Create `roles` table migration for scoped roles (`scope_type`, `is_system_role`, etc.)
- [x] ✅ Create `role_assignments` table migration with `unique(user_id, role_id, scope_type, scope_id)`
- [x] ✅ Reuse/adapt `permissions` and `role_permissions` for scoped RBAC assignments
- [x] ✅ (Optional) Create `tenant_route_bindings` table for custom domains/path aliases

## 4. Schema Expansion (Support Tables)

- [x] ✅ Create `tenant_invitations` table
- [x] ✅ Create `tenant_suspensions` table
- [x] ✅ Create `tenant_subscriptions` table (future-ready)
- [x] ✅ Create `tenant_webhooks` table
- [x] ✅ Create `tenant_features` table
- [x] ✅ Create `tenant_usage` table
- [x] ✅ Create `tenant_health` table
- [x] ✅ Create `tenant_incidents` table
- [x] ✅ Create `tenant_onboarding` table
- [x] ✅ Create `archived_records` table
- [x] ✅ Create `data_subject_requests` table

## 5. Add Tenant Keys to Existing Tables (Critical)

- [x] ✅ Add `province_id` and `barangay_id` to all tenant-owned tables (nullable first during transition)
- [x] ✅ Apply tenant keys to `users` (if needed during transition before full membership-driven scoping)
- [x] ✅ Apply tenant keys to `branches` (or replace branch scoping with barangay/facility mapping)
- [x] ✅ Apply tenant keys to inventory and order domain tables (`inventories`, `orders`, `order_items`)
- [x] ✅ Apply tenant keys to patient domain tables (`patientrecords`, `dispensedmedications`)
- [x] ✅ Apply tenant keys to hold/request tables (`holds`, `hold_items`, `incoming_requests`, `request_items`, `request_comments`, `request_attachments`)
- [x] ✅ Apply tenant keys to supplier tables (`suppliers`, `supplier_products`)
- [x] ✅ Apply tenant keys to settings/rules tables (`low_stock_settings`, `reorder_rules`)
- [x] ✅ Apply tenant keys to events/logging tables (`notifications`, `audit_events`, `history_logs`, `product_movements`)
- [x] ✅ Apply tenant keys to `idempotency_keys`
- [x] ✅ Decide and implement denormalized tenant keys on child tables where recommended for join performance and simpler scoping

## 6. Indexing, Constraints, and DB Performance

- [x] ✅ Add standard tenant composite index to tenant-owned high-read tables: `(province_id, barangay_id)`
- [x] ✅ Add reporting index `(province_id, created_at)` where needed
- [x] ✅ Add operational index `(barangay_id, created_at)` where needed
- [x] ✅ Add module-specific tenant-first indexes (inventory, patient records, requests, product movements, etc.)
- [x] ✅ Add required module-specific indexes from setup guide examples:
- [x] ✅ `inventories(province_id, barangay_id, product_id)`
- [x] ✅ `patientrecords(province_id, barangay_id, date_dispensed)`
- [x] ✅ `incoming_requests(province_id, barangay_id, status, created_at)`
- [x] ✅ Follow the documented database migration order (core tables -> tenant keys -> support tables -> indexes -> post-backfill constraints)
- [x] ✅ Stage strict constraints: keep new columns nullable initially, then enforce `NOT NULL` and FKs after backfill/cutover validation

## 7. Tenant Context, Routing, and Middleware Core

- [x] ✅ Implement `App\Tenancy\TenantContext` immutable value object
- [x] ✅ Implement `App\Tenancy\TenantResolver` service (slug -> tenant context, membership validation)
- [x] ✅ Implement tenant resolution middleware (`tenant.resolve` / `ResolveTenantFromSlug`)
- [x] ✅ Implement tenant membership enforcement middleware (`tenant.membership` / `EnforceTenantMembership`)
- [x] ✅ Implement tenant context binding middleware (`tenant.bind` / `BindTenantContext`)
- [x] ✅ Bind tenant context to container, session, and views/layouts
- [x] ✅ Add `current_tenant()` helper
- [x] ✅ Implement canonical slug redirect behavior
- [x] ✅ Validate route/slug collision handling before pilot rollout
- [x] ✅ Create parallel route groups during transition (new tenant routes + legacy `/admin`)
- [x] ✅ Set up moderator route group (`/moderator/...`)
- [x] ✅ Set up tenant route group (`/{provinceSlug}/{barangaySlug}/...`)
- [x] ✅ (Optional) Set up province-only admin routes if adopted
- [x] ✅ Retire or restrict legacy `/admin` routes after cutover

## 8. Route Generation and URL Safety

- [x] ✅ Create `tenant_route()` helper
- [x] ✅ Create `moderator_route()` helper
- [x] ✅ Refactor views/controllers/services to stop hardcoding `/admin/...` URLs
- [x] ✅ Ensure tenant route generation supports current context and explicit alternate context
- [x] ✅ Ensure moderator route generation is isolated from tenant prefixes
- [x] ✅ Update password reset, email verification, and invite links to include tenant context where applicable
- [x] ✅ Test tenant-aware links for slug preservation and invalid/expired contexts

## 9. Authentication, Sessions, and Login Flows

- [x] ✅ Implement moderator login portal (`/moderator/login`; optional admin subdomain login)
- [x] ✅ Restrict moderator login to Moderator role accounts only
- [x] ✅ Implement tenant login entry point `/{provinceSlug}/{barangaySlug}/login`
- [x] ✅ Resolve tenant before rendering tenant login form
- [x] ✅ Show tenant branding/name on tenant login form
- [x] ✅ Validate account membership and scoped role assignment during tenant login
- [x] ✅ Store tenant context in session (`tenant.province_id`, `tenant.barangay_id`, `tenant.scope_type`, route slug keys)
- [x] ✅ Refactor login controllers/services to accept tenant slug route params
- [x] ✅ Update `AuthSessionService` redirect logic to be tenant-aware
- [x] ✅ Preserve moderator global redirect behavior
- [x] ✅ Update new-login notification emails with tenant name/scope
- [x] ✅ Implement membership-aware login behavior for invited/suspended states

## 10. RBAC Migration and Scope-Aware Authorization

- [x] ✅ Define system roles (`moderator`, `province_admin`, `barangay_admin`, optional `barangay_staff`, optional `auditor`)
- [x] ✅ Define/confirm permission set (tenant management, users, inventory, patient records, requests, suppliers, analytics, audit, etc.)
- [x] ✅ Implement scope-aware permission evaluation using:
- [x] ✅ permission name
- [x] ✅ role assignment scope
- [x] ✅ current tenant context
- [x] ✅ target record tenant ownership
- [x] ✅ Implement policies for tenant-owned models with role + scope checks
- [x] ✅ Keep `user_levels` during transition
- [x] ✅ Map existing `user_levels` to scoped roles
- [x] ✅ Populate scoped `roles` and `role_assignments`
- [x] ✅ Replace permission resolution from legacy `level.permissions` to scoped role resolver
- [x] ✅ Remove `user_levels` dependency after full cutover

## 11. Backend Core Refactor (Services, Repositories, Models)

- [x] ✅ Create/refactor core backend tenancy components listed in plan (`TenantContext`, `TenantResolver`, route generator helper, middleware, scope-aware policies)
- [x] ✅ Refactor services to accept/use `TenantContext` instead of implicit `Auth::user()->branch_id`
- [x] ✅ Centralize tenant filter application in service/repository layers
- [x] ✅ Remove hardcoded branch IDs and RHU-specific assumptions
- [x] ✅ Ensure tenant-owned repositories default to scoped queries
- [x] ✅ Reserve unscoped queries for explicit Moderator workflows only
- [x] ✅ Enforce tenant scope in exports and analytics repositories before user filters
- [x] ✅ Implement model traits/scopes:
- [x] ✅ `TenantScoped`
- [x] ✅ (If used) `BelongsToProvince`
- [x] ✅ (If used) `BelongsToBarangay`
- [x] ✅ Add model query scopes (`forTenant`, `forProvince`, `forBarangay`)
- [x] ✅ Update validation rules for tenant constraints (`BelongsToCurrentProvince`, `BelongsToCurrentBarangay`, `BelongsToCurrentTenant`)
- [x] ✅ Reject cross-tenant foreign key references at validation/service layer
- [x] ✅ Add tenant metadata to audit logs and application events

## 12. Hotspot Service Refactors (High Priority)

- [x] ✅ Refactor `DashboardAdminService`
- [x] ✅ Refactor `AnalyticsService`
- [x] ✅ Refactor `InventoryAdminService`
- [x] ✅ Refactor `PatientRecordsAdminService`
- [x] ✅ Refactor `OrderAdminService`
- [x] ✅ Refactor `RequestWorkflowService`
- [x] ✅ Refactor `AvailabilityService`
- [x] ✅ Refactor `ManageAccountAdminService`
- [x] ✅ Refactor `NotificationService`
- [x] ✅ Refactor `AuthSessionService`

## 13. Module-by-Module Backend Checklist (Do Not Miss)

- [x] ✅ Authentication / Accounts: tenant slug resolver middleware, membership-aware login, scoped redirects, invite/password reset tenant links, session tenant binding/validation
- [x] ✅ Users / Manage Account: replace branch assignment UX with province/barangay memberships, moderator creation of tenant admins, activation/suspension controls, scoped role assignment UI
- [x] ✅ Inventory: tenant keys on inventory/movements, remove `branch_id` assumptions, restrict transfer boundaries, tenant-scoped exports
- [x] ✅ Patient Records: tenant-scope patient tables, validate barangay belongs to current province, tenant-scoped exports, update dashboard joins
- [x] ✅ Orders / Holds / Requests: tenant keys on parent/child records, prevent cross-tenant references, province cross-barangay workflows (if enabled), scoped analytics/lists
- [x] ✅ Suppliers: tenant-scope `suppliers` and `supplier_products`, ensure linked inventory is same tenant, tenant-aware exports/dashboards
- [x] ✅ Analytics / Dashboard: replace branch filters, add province aggregation queries, add Moderator global analytics, tenant-prefixed cache keys
- [x] ✅ Notifications / Audit / History: tenant metadata on events/logs, scoped feeds, moderator cross-tenant audit search, alerts for suspicious cross-tenant access
- [x] ✅ Exports / Reports: enforce tenant-safe query scoping, include tenant scope in export headers, block arbitrary ID filters escaping scope, explicit export leakage tests

## 14. Background Jobs, Events, Notifications, and Queues

- [x] ✅ Implement tenant-aware queued job base class (`TenantAwareJob`) carrying `province_id`, `barangay_id`, `scope_type`
- [x] ✅ Rehydrate `TenantContext` inside queued jobs
- [x] ✅ Add job middleware to bind tenant context before handling job logic
- [x] ✅ Namespace cache keys used by jobs by tenant
- [x] ✅ Namespace notification queries by tenant
- [x] ✅ Include tenant metadata in job-generated audit logs
- [x] ✅ Validate queue worker deployment procedure (drain/restart) for tenant-aware code updates
- [x] ✅ Verify queue jobs fail safely when tenant context is missing/invalid

## 15. Storage, File Access, and Exports

- [x] ✅ Create `TenantStorageService` for tenant-scoped path generation
- [x] ✅ Implement storage directory isolation for provinces and barangays (`storage/app/tenants/...`)
- [x] ✅ Override direct `Storage::disk()` usage where tenant file paths are required
- [x] ✅ Validate file access belongs to current tenant before read/download/delete
- [x] ✅ Add signed URLs for secure file downloads
- [x] ✅ Ensure exports/reports are stored under tenant-scoped directories
- [x] ✅ Validate export isolation for moderator, province, and barangay scopes
- [x] ✅ Include tenant headers/scope metadata in exports

## 16. Cache, Rate Limiting, and Performance Controls

- [x] ✅ Implement tenant cache key namespacing pattern (platform/province/barangay)
- [x] ✅ Create `TenantCacheService` (or equivalent cache wrapper) for tenant-aware remember/forget operations
- [x] ✅ Implement tenant cache invalidation when tenant data changes
- [x] ✅ Implement cache/session invalidation on role or membership changes
- [x] ✅ Configure per-tenant rate limiting (`tenant-api`, `tenant-login`) using tenant scope + IP
- [x] ✅ Apply throttle middleware to tenant API and login routes
- [x] ✅ Add DDoS / abuse prevention checks (per-IP + per-tenant login attempt tracking)
- [x] ✅ Load test province aggregations, exports, route resolution, and queue throughput under multi-tenant load

## 17. Frontend and UI Refactor (Tenant-Aware + Role-Based)

- [x] ✅ Create tenant-aware navigation
- [x] ✅ Create moderator navigation (separate from tenant navigation)
- [x] ✅ Add current tenant badge to layouts/header
- [x] ✅ Add moderator tenant switcher (and optional provincial multi-barangay switcher if enabled)
- [x] ✅ Update route generation in Blade/views to use tenant-aware helpers
- [x] ✅ Implement role-based menu/module rendering (with server-side auth enforcement retained)
- [x] ✅ Provide `CurrentAccessContext` payload to layouts/view models
- [x] ✅ Build moderator login page
- [x] ✅ Build tenant login page with slug + tenant branding
- [x] ✅ Build province/barangay onboarding pages (Moderator access)
- [x] ✅ Build invite acceptance flow with tenant-aware redirects
- [x] ✅ Ensure forms/dropdowns are backend-filtered by tenant (inventory, suppliers, barangays, user assignments, export filters)
- [x] ✅ Build tenant settings UI (province/barangay as applicable)
- [x] ✅ Implement role/scope-based dashboard widget composition
- [x] ✅ Implement tenant-aware frontend analytics endpoints and empty states for new tenants

## 18. Dashboards by Role (Feature Breakdown)

- [x] ✅ Moderator dashboard: province/barangay status, active users, usage metrics, health summary, data integrity alerts, security/audit events, onboarding wizard, support tools, future billing hooks
- [x] ✅ Moderator pages: province management, barangay management, memberships/user assignments, role/permission templates, global catalog, platform audit/observability
- [x] ✅ Provincial admin dashboard: KPI summary across barangays, comparative metrics, low-stock alerts, activity feed, province-scoped user management, suppliers (if allowed), audit logs, notifications center
- [x] ✅ Provincial admin pages: barangay directory/activation, province user management/roles, aggregated reports/exports, cross-barangay workflows (if enabled), province settings
- [x] ✅ Barangay admin dashboard: local inventory summary, low stock/expiring batches, orders/requests/holds queues, patient summaries, local suppliers, notifications/audit history, settings
- [x] ✅ Barangay admin pages: inventory, patient records, orders/holds/requests, suppliers/batch links, reports/exports, delegated user management (if enabled)

## 19. API, Webhooks, Feature Flags, and Integrations

- [x] ✅ Define multi-tenant API versioning and route structure
- [x] ✅ Implement tenant-scoped API authentication (Sanctum/Passport or chosen approach)
- [x] ✅ Store tenant context in token abilities/claims
- [x] ✅ Validate API token tenant matches request tenant
- [x] ✅ Create `tenant_webhooks` table and webhook event catalog
- [x] ✅ Implement per-tenant webhook configuration
- [x] ✅ Implement webhook delivery security and tenant scoping
- [x] ✅ Create `tenant_features` table and feature flag service
- [x] ✅ Define default feature flags and tenant overrides
- [x] ✅ Add environment-driven feature flags for phased rollout and emergency disable (kill switches)

## 20. Email and Notification Tenant Customization

- [x] ✅ Implement tenant email settings support (branding/from name, etc. as needed)
- [x] ✅ Make mailables tenant-aware
- [x] ✅ Ensure invite/password reset/email verification/new-login emails preserve tenant context
- [x] ✅ Test tenant-aware email links end to end in staging and production smoke tests

## 21. Security and Compliance

- [x] ✅ Enforce authorization policies on all tenant routes
- [x] ✅ Never trust client-supplied tenant IDs; resolve from route/session/context
- [x] ✅ Validate all tenant foreign key references belong to current tenant
- [x] ✅ Validate file uploads remain within tenant storage boundaries
- [x] ✅ Include tenant context in all background jobs and logs
- [x] ✅ Log all moderator tenant switching/impersonation actions
- [x] ✅ Rate limit by tenant + IP
- [x] ✅ Add account lockouts / alerts for repeated login failures
- [x] ✅ Log and alert cross-tenant access attempts
- [x] ✅ Implement tenant-aware exception handling and structured logging with tenant metadata
- [x] ✅ Configure critical alerts for tenancy-related failures (leakage attempts, queue failures, login abuse, etc.)
- [x] ✅ (Optional/Planned) Add 2FA support per tenant / sensitive actions
- [x] ✅ (Optional/Planned) Add encrypted 2FA secrets and enforcement policies
- [x] ✅ Implement session security and moderator tenant switching safeguards (session isolation / fixation protections)
- [x] ✅ Implement data archiving and retention policies
- [x] ✅ Create archive tracking (`archived_records`) and restore capability for moderators
- [x] ✅ Identify and protect PII fields
- [x] ✅ Encrypt sensitive patient data at rest where required
- [x] ✅ Audit PII access
- [x] ✅ Support data subject request export/delete workflows (`data_subject_requests`)

## 22. Tenant Usage, Health, and Incident Operations

- [x] ✅ Define quota configuration for barangay/province limits (users, inventory, records, storage, API calls)
- [x] ✅ Implement `tenant_usage` tracking table and usage service
- [x] ✅ Enforce quota checks in relevant create/update workflows
- [x] ✅ Create `tenant_health` table and health monitoring service/command
- [x] ✅ Implement `tenant:health-check` command and scoped variants
- [x] ✅ Track health metrics (DB connectivity, queue success, API response time, storage usage, error rates, user activity)
- [x] ✅ Create `tenant_incidents` table and incident response workflow
- [x] ✅ Define incident types (breach suspicion, cross-tenant access, corruption, degradation, exploited vulnerability)
- [x] ✅ Implement incident lifecycle (detect, record, notify, investigate, contain, resolve, post-mortem, harden)

## 23. Tenant Onboarding, Suspension, and Data Portability

- [x] ✅ Implement tenant onboarding workflow (Provisioning -> Configuration -> Activation)
- [x] ✅ Create onboarding state machine + `tenant_onboarding` table
- [x] ✅ Create onboarding checklist and moderator approval steps
- [x] ✅ Implement tenant invitation system (`tenant_invitations`) and invitation acceptance flow
- [x] ✅ Implement invitation expiration configuration and service
- [x] ✅ Implement tenant suspension/deactivation flow and `tenant_suspensions` table
- [x] ✅ Support suspension types (voluntary, payment, compliance, security, administrative)
- [x] ✅ Implement tenant export/import services for onboarding/migrations/incidents
- [x] ✅ Support tenant-targeted data export for support/incident response
- [x] ✅ Consider logical tenant export/import tooling for migrations and restores

## 24. Billing and Subscription (Future-Ready)

- [x] ✅ Create `tenant_subscriptions` schema and plan configuration (future-ready)
- [x] ✅ Add billing integration hooks without blocking core SaaS cutover
- [x] ✅ Keep billing toggled/disabled until product/business readiness

## 25. Data Migration Strategy (Existing Data -> SaaS)

- [x] ✅ Phase 0: Inventory all tables and identify tenant-owned rows
- [x] ✅ Phase 0: Decide canonical branch -> province/barangay mapping
- [x] ✅ Phase 0: Define default province for existing deployment data
- [x] ✅ Phase 0: Back up production DB and validate restore procedure
- [x] ✅ Phase 0: Create migration rehearsal environment with production-like snapshot
- [x] ✅ Phase 1: Introduce core tenant tables (`provinces`, `barangays` upgrades, memberships, roles, assignments) with non-breaking nullable changes
- [x] ✅ Phase 2: Add tenant columns to existing tenant-owned tables (nullable first)
- [x] ✅ Phase 2: Add indexes before strict constraints
- [x] ✅ Phase 2: Backfill in batches
- [x] ✅ Phase 2/3: Derive `province_id` and `barangay_id` using documented mapping rules (including province-scoped rows with `barangay_id = null`)
- [x] ✅ Phase 3: Map moderator accounts and existing admins to scoped roles
- [x] ✅ Phase 3: Populate `tenant_memberships`
- [x] ✅ Phase 3: Populate `role_assignments`
- [x] ✅ Phase 3: Keep legacy `user_level_id` temporarily
- [x] ✅ Phase 4: Implement dual-read/dual-write tenancy application layer (where needed)
- [x] ✅ Phase 4: Monitor for missing tenant keys on new writes
- [x] ✅ Phase 4: Add alerts for writes missing tenant keys during transition
- [x] ✅ Phase 4/5: Run validation scripts and reconciliation checks (counts + sampled records)
- [x] ✅ Phase 5: Create tenant-slug routes and enable limited pilot (one province + selected barangays)
- [x] ✅ Phase 5: Validate pilot modules (login, dashboard, inventory, patient records, orders/requests/holds, suppliers, exports)
- [x] ✅ Phase 5: Run leakage tests between pilot tenants
- [x] ✅ Phase 6: Enforce `NOT NULL` and FK constraints (where applicable)
- [x] ✅ Phase 6: Remove/deprecate direct `branch_id` assumptions in services/routes
- [x] ✅ Phase 6: Make tenant routes default entry point
- [x] ✅ Phase 6: Retire legacy `/admin` path or restrict to moderator redirect only
- [x] ✅ Phase 7: Remove compatibility code
- [x] ✅ Phase 7: Retire `user_levels` after full cutover
- [x] ✅ Phase 7: Remove hardcoded branch constants and RHU assumptions
- [x] ✅ Phase 7: Update seeders, factories, tests for tenant keys
- [x] ✅ Phase 7: Update docs and onboarding SOPs

## 26. Testing and Hardening (Mandatory)

- [x] ✅ Unit tests: tenant resolver
- [x] ✅ Unit tests: tenant context builder
- [x] ✅ Unit tests: scoped permission evaluator
- [x] ✅ Unit tests: tenant-aware repository filters
- [x] ✅ Feature tests: moderator access to all tenant dashboards
- [x] ✅ Feature tests: provincial admin blocked from other provinces
- [x] ✅ Feature tests: barangay admin blocked from other barangays (same province included)
- [x] ✅ Feature tests: tenant login via slug
- [x] ✅ Feature tests: invalid slug -> 404 or branded error page
- [x] ✅ Feature tests: cross-tenant ID submission rejected (422/403)
- [x] ✅ Regression tests: inventory CRUD
- [x] ✅ Regression tests: patient records CRUD
- [x] ✅ Regression tests: orders/holds/requests workflow
- [x] ✅ Regression tests: supplier linking
- [x] ✅ Regression tests: exports (inventory/patient/supplier)
- [x] ✅ Regression tests: analytics endpoints
- [x] ✅ Data leakage matrix across at least 2 provinces x 2 barangays:
- [x] ✅ list endpoints
- [x] ✅ detail pages
- [x] ✅ create/update/delete
- [x] ✅ exports
- [x] ✅ AJAX analytics APIs
- [x] ✅ background notifications
- [x] ✅ audit logs
- [x] ✅ Performance/load testing: province dashboard aggregation
- [x] ✅ Performance/load testing: tenant route resolution overhead
- [x] ✅ Performance/load testing: export generation under multi-tenant load
- [x] ✅ Performance/load testing: queue throughput with tenant-tagged jobs
- [x] ✅ Security audit (authz coverage, leakage paths, export filters, logging/alerts)
- [x] ✅ Test tenant-aware password reset, email verification, and invitation links
- [x] ✅ Test moderator tenant switching/impersonation with audit trail and session isolation
- [x] ✅ Test cache/session invalidation after role or membership changes
- [x] ✅ Test canonical slug redirects and route collision handling

## 27. Local Setup and Smoke Validation

- [x] ✅ Run migrations locally (`php artisan migrate`)
- [x] ✅ Seed all PH provinces and barangays via `DemoSeeder` entry point
- [x] ✅ Verify Moderator account created
- [x] ✅ Verify Super Admin accounts mapped to demo provinces
- [x] ✅ Verify Admin and staff accounts under each Super Admin
- [x] ✅ Verify demo data seeded per module with correct `province_id` + `barangay_id`
- [x] ✅ Verify seeder is idempotent (rerun produces no duplicates)
- [x] ✅ Verify tenant routes locally (`/{provinceSlug}/{barangaySlug}/dashboard`)
- [x] ✅ Verify moderator routes locally (`/moderator/dashboard`)
- [x] ✅ Smoke-test tenant resolver and `current_tenant()`
- [x] ✅ Smoke-test membership validation and middleware stack
- [x] ✅ Verify key tenancy Artisan commands exist and work (`tenant:health-check`, `tenant:cache:clear`, `tenant:usage:report`)
- [x] ✅ Run quality cycle: `composer dump-autoload` → clear caches → `phpunit` → `tenant:smoke-test`

## 28. Production Deployment, Cutover, and Post-Deploy Ops

- [x] ✅ Pre-deployment: run migrations
- [x] ✅ Pre-deployment: seed provinces and barangays
- [x] ✅ Pre-deployment: create tenant memberships for users
- [x] ✅ Pre-deployment: backfill existing data with tenant keys
- [x] ✅ Pre-deployment: run reconciliation checks (counts + samples) and confirm no unexpected null tenant keys
- [x] ✅ Pre-deployment: configure tenant storage paths
- [x] ✅ Pre-deployment: set up cache namespacing
- [x] ✅ Pre-deployment: enable tenant-aware queue worker/job context
- [x] ✅ Pre-deployment: configure per-tenant rate limiting
- [x] ✅ Pre-deployment: set up monitoring dashboards/alerts
- [x] ✅ Pre-deployment: validate canonical province/barangay slugs and redirect behavior
- [x] ✅ Pre-deployment: rehearse rollback/restore in staging from recent backup
- [x] ✅ Pre-deployment: prepare queue worker drain/restart procedure
- [x] ✅ Pre-deployment: confirm feature-flag rollback switches (if phased rollout)
- [x] ✅ Pre-deployment: approve cutover freeze window and rollback trigger criteria
- [x] ✅ Deployment: run migrations in planned window
- [x] ✅ Deployment: seed provinces/barangays and create memberships/roles
- [x] ✅ Deployment: run reconciliation checks for tenant keys and core tables
- [x] ✅ Deployment: test tenant routes (including canonical redirects)
- [x] ✅ Deployment: verify data isolation and scoped access
- [x] ✅ Deployment: restart/drain queue workers with tenant-aware code
- [x] ✅ Deployment: enable production monitoring
- [x] ✅ Post-deployment: monitor tenant health checks
- [x] ✅ Post-deployment: review audit logs for cross-tenant access attempts
- [x] ✅ Post-deployment: validate export isolation
- [x] ✅ Post-deployment: verify queue job tenant context
- [x] ✅ Post-deployment: run post-cutover reconciliation and null tenant-key scans
- [x] ✅ Post-deployment: verify tenant-aware password reset/invite/email verification links
- [x] ✅ Post-deployment: verify cache/session invalidation after role/membership changes
- [x] ✅ Post-deployment: confirm rollback toggles / feature flags can be disabled quickly if needed

## 29. Observability, Logging, and Support Runbooks

- [x] ✅ Include tenant IDs/slugs in logs
- [x] ✅ Tag errors by tenant scope
- [x] ✅ Implement tenant-aware exception handling in error reporting
- [x] ✅ Implement structured logging with tenant context service / log format
- [x] ✅ Add dashboard for failed jobs by tenant
- [x] ✅ Maintain audit trail for moderator actions and impersonation
- [x] ✅ Create operational runbook (deployment, rollback, queue drain/restart, incident response)
- [x] ✅ Document full restore process
- [x] ✅ Document tenant onboarding SOP
- [x] ✅ Document incident response SOP
- [x] ✅ Create final sign-off checklist and rollback plan for cutover

## 30. Environment and Configuration

- [x] ✅ Create `config/tenancy.php`
- [x] ✅ Centralize moderator prefix, session keys, invitation expiry, quotas, features, route slug behavior
- [x] ✅ Configure tenancy environment variables (moderator prefix, invitation expiry, cache prefix, storage disk, etc.)
- [x] ✅ Configure session settings for tenant-aware security requirements
- [x] ✅ Configure logging settings/format for tenant metadata
- [x] ✅ Configure rate limiting settings
- [x] ✅ Configure feature flags via environment for phased rollout and emergency disable

## 31. Acceptance Criteria (Definition of Done)

- [x] ✅ Moderator, Super Admin, Admin, and Staff can log in via intended routes
- [x] ✅ Super Admin can only manage Admins/users within assigned province
- [x] ✅ Tenant data isolation is enforced across all modules and exports
- [x] ✅ No tenant-owned table can be written without tenant keys
- [x] ✅ All core workflows operate correctly within tenant context
- [x] ✅ Dashboards are role-specific and scope-accurate
- [x] ✅ All 82 PH provinces and barangays seeded for lookup; demo data for configurable subset
- [x] ✅ Seeders are idempotent, organized via DemoSeeder, and use chunk inserts
- [x] ✅ Existing data migration is completed and validated
- [x] ✅ Automated tests cover slug resolution, RBAC, cross-tenant denial, and seeder integrity
- [x] ✅ Quality cycle passes (build/autoload, syntax check, unit/integration tests, tenant smoke tests)
- [x] ✅ Operational monitoring and audit trails include tenant metadata
- [x] ✅ Final security review completed

## 32. Country-Wide Seeder Checklist

- [x] ✅ `DemoSeeder` serves as single entry point orchestrating all seeders
- [x] ✅ `ProvinceBarangaySeeder` seeds all 82 PH provinces and 42,000+ barangays from PSGC dataset
- [x] ✅ Province slugs globally unique; barangay slugs unique within province
- [x] ✅ `ModeratorSeeder` creates platform-level Moderator account
- [x] ✅ `SuperAdminSeeder` creates Super Admin accounts mapped to provinces
- [x] ✅ `AdminUserSeeder` creates Admins and staff under each Super Admin
- [x] ✅ `TenantDemoDataSeeder` seeds complete sample tenant data per module
- [x] ✅ Demo data modules: products, inventory, suppliers, orders, movements, audit/history logs, low stock settings, notifications
- [x] ✅ All seeded relationships remain within same `province_id` + `barangay_id` scope
- [x] ✅ Seeders are idempotent (use `firstOrCreate` / `updateOrCreate`)
- [x] ✅ Seeders use chunk inserts (500–1000 rows), factories, batch operations
- [x] ✅ Demo data generation is configurable via `config('tenancy.seeder.demo_provinces')`
- [x] ✅ Seeder performance acceptable (~2-5 min geo data; ~10-15 min with demo data)

## 33. Quality Cycle Checklist

- [x] ✅ `composer dump-autoload` completes without errors
- [x] ✅ `npm run build` completes without errors
- [x] ✅ `php artisan route:clear && config:clear && cache:clear` succeeds
- [x] ✅ `php vendor/bin/phpunit --stop-on-failure` passes all tests
- [x] ✅ `php artisan tenant:smoke-test` passes slug resolution and cross-tenant denial
- [x] ✅ Slug resolution tests pass across multiple PH provinces/barangays
- [x] ✅ Role-based access tests confirm Moderator → all; Super Admin → own province; Admin → own barangay
- [x] ✅ Cross-tenant access denial tests confirm isolation between provinces and barangays
- [x] ✅ Seeder integrity tests confirm no orphan records or null tenant keys

