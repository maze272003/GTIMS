# GTIMS Multi-Tenant SaaS Master Checklist

Consolidated from:

- `docs/MULTI_TENANT_SETUP.md`
- `docs/SAAS_MULTI_TENANT_CONVERSION_PLAN.md`

This checklist merges the implementation plan, setup guide, migration/cutover steps, testing, and operations tasks into one execution list. It also includes added recommendations for reconciliation, rollback rehearsal, canonical slug validation, queue deployment procedure, and feature-flag rollback controls.

## 1. Decisions and Scope Lock (Do First)

- [x] âœ… Confirm business rules for province/barangay relationships and what current `branches` represent in the target model
- [x] âœ… Decide whether to upgrade `barangays` in place (recommended) or replace with a new tenant barangays table
- [x] âœ… Approve RBAC transition path (`user_levels` transition vs direct replacement)
- [x] âœ… Confirm tenant route model: `/{provinceSlug}/{barangaySlug}` and moderator route model (`/moderator` vs admin subdomain)
- [x] âœ… Decide province-only admin route behavior (`/{provinceSlug}/admin` vs derive province from barangay routes)
- [x] âœ… Define canonical slug rules and non-canonical redirect behavior
- [x] âœ… Decide supplier scoping business rule (province-scoped vs barangay-scoped vs hybrid)
- [x] âœ… Decide optional SaaS features for initial release vs later phases (2FA, webhooks, API access, billing)
- [x] âœ… Freeze non-SaaS schema changes during tenancy conversion work

## 2. Architecture and Isolation Baseline

- [x] âœ… Adopt single database / shared schema / row-level tenant partitioning strategy for v1 SaaS
- [x] âœ… Document optional future RLS enhancement (PostgreSQL) if applicable
- [x] âœ… Finalize tenant hierarchy and role scope boundaries:
- [x] âœ… Platform (Moderator)
- [x] âœ… Province (Provincial Administrator)
- [x] âœ… Barangay (Barangay Administrator)
- [x] âœ… Classify data explicitly as shared, tenant-owned, or hybrid/configurable
- [x] âœ… Define strict data isolation defense-in-depth layers (routing, middleware, authz, repositories, services, validation, jobs, exports, audit logs)

## 3. Schema Foundation (Core Tenancy)

- [x] âœ… Create `provinces` table migration with fields/constraints documented in plan (`id`, `name`, `slug`, optional `code`, `is_active`, `settings_json`, timestamps, unique slug)
- [x] âœ… Upgrade `barangays` table with `province_id`, `slug`, `is_active`, `external_code`, `settings_json`
- [x] âœ… Add `barangays` constraints (`unique(province_id, slug)` and optional `unique(province_id, barangay_name)`)
- [x] âœ… Create `tenant_memberships` table migration with documented fields and `unique(user_id, scope_type, scope_id)`
- [x] âœ… Create `roles` table migration for scoped roles (`scope_type`, `is_system_role`, etc.)
- [x] âœ… Create `role_assignments` table migration with `unique(user_id, role_id, scope_type, scope_id)`
- [x] âœ… Reuse/adapt `permissions` and `role_permissions` for scoped RBAC assignments
- [x] âœ… (Optional) Create `tenant_route_bindings` table for custom domains/path aliases

## 4. Schema Expansion (Support Tables)

- [x] âœ… Create `tenant_invitations` table
- [x] âœ… Create `tenant_suspensions` table
- [x] âœ… Create `tenant_subscriptions` table (future-ready)
- [x] âœ… Create `tenant_webhooks` table
- [x] âœ… Create `tenant_features` table
- [x] âœ… Create `tenant_usage` table
- [x] âœ… Create `tenant_health` table
- [x] âœ… Create `tenant_incidents` table
- [x] âœ… Create `tenant_onboarding` table
- [x] âœ… Create `archived_records` table
- [x] âœ… Create `data_subject_requests` table

## 5. Add Tenant Keys to Existing Tables (Critical)

- [x] âœ… Add `province_id` and `barangay_id` to all tenant-owned tables (nullable first during transition)
- [x] âœ… Apply tenant keys to `users` (if needed during transition before full membership-driven scoping)
- [x] âœ… Apply tenant keys to `branches` (or replace branch scoping with barangay/facility mapping)
- [x] âœ… Apply tenant keys to inventory and order domain tables (`inventories`, `orders`, `order_items`)
- [x] âœ… Apply tenant keys to patient domain tables (`patientrecords`, `dispensedmedications`)
- [x] âœ… Apply tenant keys to hold/request tables (`holds`, `hold_items`, `incoming_requests`, `request_items`, `request_comments`, `request_attachments`)
- [x] âœ… Apply tenant keys to supplier tables (`suppliers`, `supplier_products`)
- [x] âœ… Apply tenant keys to settings/rules tables (`low_stock_settings`, `reorder_rules`)
- [x] âœ… Apply tenant keys to events/logging tables (`notifications`, `audit_events`, `history_logs`, `product_movements`)
- [x] âœ… Apply tenant keys to `idempotency_keys`
- [x] âœ… Decide and implement denormalized tenant keys on child tables where recommended for join performance and simpler scoping

## 6. Indexing, Constraints, and DB Performance

- [x] âœ… Add standard tenant composite index to tenant-owned high-read tables: `(province_id, barangay_id)`
- [x] âœ… Add reporting index `(province_id, created_at)` where needed
- [x] âœ… Add operational index `(barangay_id, created_at)` where needed
- [x] âœ… Add module-specific tenant-first indexes (inventory, patient records, requests, product movements, etc.)
- [x] âœ… Add required module-specific indexes from setup guide examples:
- [x] âœ… `inventories(province_id, barangay_id, product_id)`
- [x] âœ… `patientrecords(province_id, barangay_id, date_dispensed)`
- [x] âœ… `incoming_requests(province_id, barangay_id, status, created_at)`
- [x] âœ… Follow the documented database migration order (core tables -> tenant keys -> support tables -> indexes -> post-backfill constraints)
- [x] âœ… Stage strict constraints: keep new columns nullable initially, then enforce `NOT NULL` and FKs after backfill/cutover validation

## 7. Tenant Context, Routing, and Middleware Core

- [x] âœ… Implement `App\Tenancy\TenantContext` immutable value object
- [x] âœ… Implement `App\Tenancy\TenantResolver` service (slug -> tenant context, membership validation)
- [x] âœ… Implement tenant resolution middleware (`tenant.resolve` / `ResolveTenantFromSlug`)
- [x] âœ… Implement tenant membership enforcement middleware (`tenant.membership` / `EnforceTenantMembership`)
- [x] âœ… Implement tenant context binding middleware (`tenant.bind` / `BindTenantContext`)
- [x] âœ… Bind tenant context to container, session, and views/layouts
- [x] âœ… Add `current_tenant()` helper
- [x] âœ… Implement canonical slug redirect behavior
- [x] âœ… Validate route/slug collision handling before pilot rollout
- [x] âœ… Create parallel route groups during transition (new tenant routes + legacy `/admin`)
- [x] âœ… Set up moderator route group (`/moderator/...`)
- [x] âœ… Set up tenant route group (`/{provinceSlug}/{barangaySlug}/...`)
- [x] ✅ (Optional) Set up province-only admin routes if adopted
- [x] âœ… Retire or restrict legacy `/admin` routes after cutover

## 8. Route Generation and URL Safety

- [x] âœ… Create `tenant_route()` helper
- [x] âœ… Create `moderator_route()` helper
- [x] ✅ Refactor views/controllers/services to stop hardcoding `/admin/...` URLs
- [x] âœ… Ensure tenant route generation supports current context and explicit alternate context
- [x] âœ… Ensure moderator route generation is isolated from tenant prefixes
- [x] âœ… Update password reset, email verification, and invite links to include tenant context where applicable
- [x] ✅ Test tenant-aware links for slug preservation and invalid/expired contexts

## 9. Authentication, Sessions, and Login Flows

- [x] âœ… Implement moderator login portal (`/moderator/login`; optional admin subdomain login)
- [x] âœ… Restrict moderator login to Moderator role accounts only
- [x] âœ… Implement tenant login entry point `/{provinceSlug}/{barangaySlug}/login`
- [x] âœ… Resolve tenant before rendering tenant login form
- [x] âœ… Show tenant branding/name on tenant login form
- [x] âœ… Validate account membership and scoped role assignment during tenant login
- [x] âœ… Store tenant context in session (`tenant.province_id`, `tenant.barangay_id`, `tenant.scope_type`, route slug keys)
- [x] âœ… Refactor login controllers/services to accept tenant slug route params
- [x] âœ… Update `AuthSessionService` redirect logic to be tenant-aware
- [x] âœ… Preserve moderator global redirect behavior
- [x] âœ… Update new-login notification emails with tenant name/scope
- [x] âœ… Implement membership-aware login behavior for invited/suspended states

## 10. RBAC Migration and Scope-Aware Authorization

- [x] âœ… Define system roles (`moderator`, `province_admin`, `barangay_admin`, optional `barangay_staff`, optional `auditor`)
- [x] âœ… Define/confirm permission set (tenant management, users, inventory, patient records, requests, suppliers, analytics, audit, etc.)
- [x] âœ… Implement scope-aware permission evaluation using:
- [x] âœ… permission name
- [x] âœ… role assignment scope
- [x] âœ… current tenant context
- [x] âœ… target record tenant ownership
- [x] ✅ Implement policies for tenant-owned models with role + scope checks
- [x] âœ… Keep `user_levels` during transition
- [x] âœ… Map existing `user_levels` to scoped roles
- [x] âœ… Populate scoped `roles` and `role_assignments`
- [x] âœ… Replace permission resolution from legacy `level.permissions` to scoped role resolver
- [x] ✅ Remove `user_levels` dependency after full cutover

## 11. Backend Core Refactor (Services, Repositories, Models)

- [x] âœ… Create/refactor core backend tenancy components listed in plan (`TenantContext`, `TenantResolver`, route generator helper, middleware, scope-aware policies)
- [x] ✅ Refactor services to accept/use `TenantContext` instead of implicit `Auth::user()->branch_id`
- [x] ✅ Centralize tenant filter application in service/repository layers
- [x] ✅ Remove hardcoded branch IDs and RHU-specific assumptions
- [x] ✅ Ensure tenant-owned repositories default to scoped queries
- [x] ✅ Reserve unscoped queries for explicit Moderator workflows only
- [x] ✅ Enforce tenant scope in exports and analytics repositories before user filters
- [x] âœ… Implement model traits/scopes:
- [x] âœ… `TenantScoped`
- [x] âœ… (If used) `BelongsToProvince`
- [x] âœ… (If used) `BelongsToBarangay`
- [x] âœ… Add model query scopes (`forTenant`, `forProvince`, `forBarangay`)
- [x] âœ… Update validation rules for tenant constraints (`BelongsToCurrentProvince`, `BelongsToCurrentBarangay`, `BelongsToCurrentTenant`)
- [x] âœ… Reject cross-tenant foreign key references at validation/service layer
- [x] âœ… Add tenant metadata to audit logs and application events

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

- [x] âœ… Implement tenant-aware queued job base class (`TenantAwareJob`) carrying `province_id`, `barangay_id`, `scope_type`
- [x] âœ… Rehydrate `TenantContext` inside queued jobs
- [x] âœ… Add job middleware to bind tenant context before handling job logic
- [x] âœ… Namespace cache keys used by jobs by tenant
- [x] âœ… Namespace notification queries by tenant
- [x] âœ… Include tenant metadata in job-generated audit logs
- [x] ✅ Validate queue worker deployment procedure (drain/restart) for tenant-aware code updates
- [x] âœ… Verify queue jobs fail safely when tenant context is missing/invalid

## 15. Storage, File Access, and Exports

- [x] âœ… Create `TenantStorageService` for tenant-scoped path generation
- [x] âœ… Implement storage directory isolation for provinces and barangays (`storage/app/tenants/...`)
- [x] âœ… Override direct `Storage::disk()` usage where tenant file paths are required
- [x] âœ… Validate file access belongs to current tenant before read/download/delete
- [x] âœ… Add signed URLs for secure file downloads
- [x] ✅ Ensure exports/reports are stored under tenant-scoped directories
- [x] ✅ Validate export isolation for moderator, province, and barangay scopes
- [x] ✅ Include tenant headers/scope metadata in exports

## 16. Cache, Rate Limiting, and Performance Controls

- [x] âœ… Implement tenant cache key namespacing pattern (platform/province/barangay)
- [x] âœ… Create `TenantCacheService` (or equivalent cache wrapper) for tenant-aware remember/forget operations
- [x] âœ… Implement tenant cache invalidation when tenant data changes
- [x] âœ… Implement cache/session invalidation on role or membership changes
- [x] âœ… Configure per-tenant rate limiting (`tenant-api`, `tenant-login`) using tenant scope + IP
- [x] âœ… Apply throttle middleware to tenant API and login routes
- [x] âœ… Add DDoS / abuse prevention checks (per-IP + per-tenant login attempt tracking)
- [x] ✅ Load test province aggregations, exports, route resolution, and queue throughput under multi-tenant load

## 17. Frontend and UI Refactor (Tenant-Aware + Role-Based)

- [x] âœ… Create tenant-aware navigation
- [x] ✅ Create moderator navigation (separate from tenant navigation)
- [x] âœ… Add current tenant badge to layouts/header
- [x] ✅ Add moderator tenant switcher (and optional provincial multi-barangay switcher if enabled)
- [x] ✅ Update route generation in Blade/views to use tenant-aware helpers
- [x] ✅ Implement role-based menu/module rendering (with server-side auth enforcement retained)
- [x] ✅ Provide `CurrentAccessContext` payload to layouts/view models
- [x] âœ… Build moderator login page
- [x] âœ… Build tenant login page with slug + tenant branding
- [x] ✅ Build province/barangay onboarding pages (Moderator access)
- [x] âœ… Build invite acceptance flow with tenant-aware redirects
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
- [x] âœ… Create `tenant_webhooks` table and webhook event catalog
- [x] ✅ Implement per-tenant webhook configuration
- [x] ✅ Implement webhook delivery security and tenant scoping
- [x] âœ… Create `tenant_features` table and feature flag service
- [x] âœ… Define default feature flags and tenant overrides
- [x] âœ… Add environment-driven feature flags for phased rollout and emergency disable (kill switches)

## 20. Email and Notification Tenant Customization

- [x] ✅ Implement tenant email settings support (branding/from name, etc. as needed)
- [x] âœ… Make mailables tenant-aware
- [x] âœ… Ensure invite/password reset/email verification/new-login emails preserve tenant context
- [x] ✅ Test tenant-aware email links end to end in staging and production smoke tests

## 21. Security and Compliance

- [x] ✅ Enforce authorization policies on all tenant routes
- [x] âœ… Never trust client-supplied tenant IDs; resolve from route/session/context
- [x] ✅ Validate all tenant foreign key references belong to current tenant
- [x] ✅ Validate file uploads remain within tenant storage boundaries
- [x] âœ… Include tenant context in all background jobs and logs
- [x] ✅ Log all moderator tenant switching/impersonation actions
- [x] âœ… Rate limit by tenant + IP
- [x] âœ… Add account lockouts / alerts for repeated login failures
- [x] âœ… Log and alert cross-tenant access attempts
- [x] âœ… Implement tenant-aware exception handling and structured logging with tenant metadata
- [x] ✅ Configure critical alerts for tenancy-related failures (leakage attempts, queue failures, login abuse, etc.)
- [x] ✅ (Optional/Planned) Add 2FA support per tenant / sensitive actions
- [x] ✅ (Optional/Planned) Add encrypted 2FA secrets and enforcement policies
- [x] âœ… Implement session security and moderator tenant switching safeguards (session isolation / fixation protections)
- [x] âœ… Implement data archiving and retention policies
- [x] âœ… Create archive tracking (`archived_records`) and restore capability for moderators
- [x] ✅ Identify and protect PII fields
- [x] ✅ Encrypt sensitive patient data at rest where required
- [x] ✅ Audit PII access
- [x] âœ… Support data subject request export/delete workflows (`data_subject_requests`)

## 22. Tenant Usage, Health, and Incident Operations

- [x] âœ… Define quota configuration for barangay/province limits (users, inventory, records, storage, API calls)
- [x] âœ… Implement `tenant_usage` tracking table and usage service
- [x] âœ… Enforce quota checks in relevant create/update workflows
- [x] âœ… Create `tenant_health` table and health monitoring service/command
- [x] âœ… Implement `tenant:health-check` command and scoped variants
- [x] ✅ Track health metrics (DB connectivity, queue success, API response time, storage usage, error rates, user activity)
- [x] âœ… Create `tenant_incidents` table and incident response workflow
- [x] âœ… Define incident types (breach suspicion, cross-tenant access, corruption, degradation, exploited vulnerability)
- [x] ✅ Implement incident lifecycle (detect, record, notify, investigate, contain, resolve, post-mortem, harden)

## 23. Tenant Onboarding, Suspension, and Data Portability

- [x] ✅ Implement tenant onboarding workflow (Provisioning -> Configuration -> Activation)
- [x] âœ… Create onboarding state machine + `tenant_onboarding` table
- [x] âœ… Create onboarding checklist and moderator approval steps
- [x] âœ… Implement tenant invitation system (`tenant_invitations`) and invitation acceptance flow
- [x] âœ… Implement invitation expiration configuration and service
- [x] âœ… Implement tenant suspension/deactivation flow and `tenant_suspensions` table
- [x] âœ… Support suspension types (voluntary, payment, compliance, security, administrative)
- [x] ✅ Implement tenant export/import services for onboarding/migrations/incidents
- [x] ✅ Support tenant-targeted data export for support/incident response
- [x] ✅ Consider logical tenant export/import tooling for migrations and restores

## 24. Billing and Subscription (Future-Ready)

- [x] âœ… Create `tenant_subscriptions` schema and plan configuration (future-ready)
- [x] ✅ Add billing integration hooks without blocking core SaaS cutover
- [x] ✅ Keep billing toggled/disabled until product/business readiness

## 25. Data Migration Strategy (Existing Data -> SaaS)

- [x] ✅ Phase 0: Inventory all tables and identify tenant-owned rows
- [x] ✅ Phase 0: Decide canonical branch -> province/barangay mapping
- [x] âœ… Phase 0: Define default province for existing deployment data
- [x] ✅ Phase 0: Back up production DB and validate restore procedure
- [x] ✅ Phase 0: Create migration rehearsal environment with production-like snapshot
- [x] âœ… Phase 1: Introduce core tenant tables (`provinces`, `barangays` upgrades, memberships, roles, assignments) with non-breaking nullable changes
- [x] âœ… Phase 2: Add tenant columns to existing tenant-owned tables (nullable first)
- [x] âœ… Phase 2: Add indexes before strict constraints
- [x] ✅ Phase 2: Backfill in batches
- [x] ✅ Phase 2/3: Derive `province_id` and `barangay_id` using documented mapping rules (including province-scoped rows with `barangay_id = null`)
- [x] âœ… Phase 3: Map moderator accounts and existing admins to scoped roles
- [x] âœ… Phase 3: Populate `tenant_memberships`
- [x] âœ… Phase 3: Populate `role_assignments`
- [x] âœ… Phase 3: Keep legacy `user_level_id` temporarily
- [x] âœ… Phase 4: Implement dual-read/dual-write tenancy application layer (where needed)
- [x] ✅ Phase 4: Monitor for missing tenant keys on new writes
- [x] ✅ Phase 4: Add alerts for writes missing tenant keys during transition
- [x] ✅ Phase 4/5: Run validation scripts and reconciliation checks (counts + sampled records)
- [x] âœ… Phase 5: Create tenant-slug routes and enable limited pilot (one province + selected barangays)
- [x] ✅ Phase 5: Validate pilot modules (login, dashboard, inventory, patient records, orders/requests/holds, suppliers, exports)
- [x] ✅ Phase 5: Run leakage tests between pilot tenants
- [x] ✅ Phase 6: Enforce `NOT NULL` and FK constraints (where applicable)
- [x] ✅ Phase 6: Remove/deprecate direct `branch_id` assumptions in services/routes
- [x] ✅ Phase 6: Make tenant routes default entry point
- [x] âœ… Phase 6: Retire legacy `/admin` path or restrict to moderator redirect only
- [x] ✅ Phase 7: Remove compatibility code
- [x] ✅ Phase 7: Retire `user_levels` after full cutover
- [x] ✅ Phase 7: Remove hardcoded branch constants and RHU assumptions
- [x] ✅ Phase 7: Update seeders, factories, tests for tenant keys
- [x] ✅ Phase 7: Update docs and onboarding SOPs

## 26. Testing and Hardening (Mandatory)

- [x] âœ… Unit tests: tenant resolver
- [x] âœ… Unit tests: tenant context builder
- [x] âœ… Unit tests: scoped permission evaluator
- [x] ✅ Unit tests: tenant-aware repository filters
- [x] ✅ Feature tests: moderator access to all tenant dashboards
- [x] ✅ Feature tests: provincial admin blocked from other provinces
- [x] ✅ Feature tests: barangay admin blocked from other barangays (same province included)
- [x] âœ… Feature tests: tenant login via slug
- [x] âœ… Feature tests: invalid slug -> 404 or branded error page
- [x] âœ… Feature tests: cross-tenant ID submission rejected (422/403)
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
- [x] âœ… Test tenant-aware password reset, email verification, and invitation links
- [x] ✅ Test moderator tenant switching/impersonation with audit trail and session isolation
- [x] ✅ Test cache/session invalidation after role or membership changes
- [x] âœ… Test canonical slug redirects and route collision handling

## 27. Local Setup and Smoke Validation

- [x] âœ… Run migrations locally (`php artisan migrate`)
- [x] âœ… Create a sample province and barangay for tenancy smoke testing
- [x] âœ… Create sample tenant memberships (platform + barangay)
- [x] âœ… Verify tenant routes locally (`/{provinceSlug}/{barangaySlug}/dashboard`)
- [x] âœ… Verify moderator routes locally (`/moderator/dashboard`)
- [x] âœ… Smoke-test tenant resolver and `current_tenant()`
- [x] âœ… Smoke-test membership validation and middleware stack
- [x] âœ… Verify key tenancy Artisan commands exist and work (`tenant:health-check`, `tenant:cache:clear`, `tenant:usage:report`)

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

- [x] âœ… Include tenant IDs/slugs in logs
- [x] âœ… Tag errors by tenant scope
- [x] âœ… Implement tenant-aware exception handling in error reporting
- [x] âœ… Implement structured logging with tenant context service / log format
- [x] ✅ Add dashboard for failed jobs by tenant
- [x] ✅ Maintain audit trail for moderator actions and impersonation
- [x] âœ… Create operational runbook (deployment, rollback, queue drain/restart, incident response)
- [x] âœ… Document full restore process
- [x] âœ… Document tenant onboarding SOP
- [x] âœ… Document incident response SOP
- [x] ✅ Create final sign-off checklist and rollback plan for cutover

## 30. Environment and Configuration

- [x] âœ… Create `config/tenancy.php`
- [x] âœ… Centralize moderator prefix, session keys, invitation expiry, quotas, features, route slug behavior
- [x] âœ… Configure tenancy environment variables (moderator prefix, invitation expiry, cache prefix, storage disk, etc.)
- [x] âœ… Configure session settings for tenant-aware security requirements
- [x] âœ… Configure logging settings/format for tenant metadata
- [x] âœ… Configure rate limiting settings
- [x] âœ… Configure feature flags via environment for phased rollout and emergency disable

## 31. Acceptance Criteria (Definition of Done)

- [x] ✅ Moderator, Provincial Admin, and Barangay Admin can log in via intended routes
- [x] ✅ Tenant data isolation is enforced across all modules and exports
- [x] ✅ No tenant-owned table can be written without tenant keys
- [x] ✅ All core workflows operate correctly within tenant context
- [x] ✅ Dashboards are role-specific and scope-accurate
- [x] ✅ Existing data migration is completed and validated
- [x] ✅ Automated tests cover tenant access controls and isolation regressions
- [x] ✅ Operational monitoring and audit trails include tenant metadata
- [x] ✅ Final security review completed

