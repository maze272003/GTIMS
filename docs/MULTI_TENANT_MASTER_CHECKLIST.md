# GTIMS Multi-Tenant SaaS Master Checklist

Consolidated from:

- `docs/MULTI_TENANT_SETUP.md`
- `docs/SAAS_MULTI_TENANT_CONVERSION_PLAN.md`

This checklist merges the implementation plan, setup guide, migration/cutover steps, testing, and operations tasks into one execution list. It also includes added recommendations for reconciliation, rollback rehearsal, canonical slug validation, queue deployment procedure, and feature-flag rollback controls.

## 1. Decisions and Scope Lock (Do First)

- [ ] Confirm business rules for province/barangay relationships and what current `branches` represent in the target model
- [ ] Decide whether to upgrade `barangays` in place (recommended) or replace with a new tenant barangays table
- [ ] Approve RBAC transition path (`user_levels` transition vs direct replacement)
- [ ] Confirm tenant route model: `/{provinceSlug}/{barangaySlug}` and moderator route model (`/moderator` vs admin subdomain)
- [ ] Decide province-only admin route behavior (`/{provinceSlug}/admin` vs derive province from barangay routes)
- [ ] Define canonical slug rules and non-canonical redirect behavior
- [ ] Decide supplier scoping business rule (province-scoped vs barangay-scoped vs hybrid)
- [ ] Decide optional SaaS features for initial release vs later phases (2FA, webhooks, API access, billing)
- [ ] Freeze non-SaaS schema changes during tenancy conversion work

## 2. Architecture and Isolation Baseline

- [ ] Adopt single database / shared schema / row-level tenant partitioning strategy for v1 SaaS
- [ ] Document optional future RLS enhancement (PostgreSQL) if applicable
- [ ] Finalize tenant hierarchy and role scope boundaries:
- [ ] Platform (Moderator)
- [ ] Province (Provincial Administrator)
- [ ] Barangay (Barangay Administrator)
- [ ] Classify data explicitly as shared, tenant-owned, or hybrid/configurable
- [ ] Define strict data isolation defense-in-depth layers (routing, middleware, authz, repositories, services, validation, jobs, exports, audit logs)

## 3. Schema Foundation (Core Tenancy)

- [ ] Create `provinces` table migration with fields/constraints documented in plan (`id`, `name`, `slug`, optional `code`, `is_active`, `settings_json`, timestamps, unique slug)
- [ ] Upgrade `barangays` table with `province_id`, `slug`, `is_active`, `external_code`, `settings_json`
- [ ] Add `barangays` constraints (`unique(province_id, slug)` and optional `unique(province_id, barangay_name)`)
- [ ] Create `tenant_memberships` table migration with documented fields and `unique(user_id, scope_type, scope_id)`
- [ ] Create `roles` table migration for scoped roles (`scope_type`, `is_system_role`, etc.)
- [ ] Create `role_assignments` table migration with `unique(user_id, role_id, scope_type, scope_id)`
- [ ] Reuse/adapt `permissions` and `role_permissions` for scoped RBAC assignments
- [ ] (Optional) Create `tenant_route_bindings` table for custom domains/path aliases

## 4. Schema Expansion (Support Tables)

- [ ] Create `tenant_invitations` table
- [ ] Create `tenant_suspensions` table
- [ ] Create `tenant_subscriptions` table (future-ready)
- [ ] Create `tenant_webhooks` table
- [ ] Create `tenant_features` table
- [ ] Create `tenant_usage` table
- [ ] Create `tenant_health` table
- [ ] Create `tenant_incidents` table
- [ ] Create `tenant_onboarding` table
- [ ] Create `archived_records` table
- [ ] Create `data_subject_requests` table

## 5. Add Tenant Keys to Existing Tables (Critical)

- [ ] Add `province_id` and `barangay_id` to all tenant-owned tables (nullable first during transition)
- [ ] Apply tenant keys to `users` (if needed during transition before full membership-driven scoping)
- [ ] Apply tenant keys to `branches` (or replace branch scoping with barangay/facility mapping)
- [ ] Apply tenant keys to inventory and order domain tables (`inventories`, `orders`, `order_items`)
- [ ] Apply tenant keys to patient domain tables (`patientrecords`, `dispensedmedications`)
- [ ] Apply tenant keys to hold/request tables (`holds`, `hold_items`, `incoming_requests`, `request_items`, `request_comments`, `request_attachments`)
- [ ] Apply tenant keys to supplier tables (`suppliers`, `supplier_products`)
- [ ] Apply tenant keys to settings/rules tables (`low_stock_settings`, `reorder_rules`)
- [ ] Apply tenant keys to events/logging tables (`notifications`, `audit_events`, `history_logs`, `product_movements`)
- [ ] Apply tenant keys to `idempotency_keys`
- [ ] Decide and implement denormalized tenant keys on child tables where recommended for join performance and simpler scoping

## 6. Indexing, Constraints, and DB Performance

- [ ] Add standard tenant composite index to tenant-owned high-read tables: `(province_id, barangay_id)`
- [ ] Add reporting index `(province_id, created_at)` where needed
- [ ] Add operational index `(barangay_id, created_at)` where needed
- [ ] Add module-specific tenant-first indexes (inventory, patient records, requests, product movements, etc.)
- [ ] Add required module-specific indexes from setup guide examples:
- [ ] `inventories(province_id, barangay_id, product_id)`
- [ ] `patientrecords(province_id, barangay_id, date_dispensed)`
- [ ] `incoming_requests(province_id, barangay_id, status, created_at)`
- [ ] Follow the documented database migration order (core tables -> tenant keys -> support tables -> indexes -> post-backfill constraints)
- [ ] Stage strict constraints: keep new columns nullable initially, then enforce `NOT NULL` and FKs after backfill/cutover validation

## 7. Tenant Context, Routing, and Middleware Core

- [ ] Implement `App\Tenancy\TenantContext` immutable value object
- [ ] Implement `App\Tenancy\TenantResolver` service (slug -> tenant context, membership validation)
- [ ] Implement tenant resolution middleware (`tenant.resolve` / `ResolveTenantFromSlug`)
- [ ] Implement tenant membership enforcement middleware (`tenant.membership` / `EnforceTenantMembership`)
- [ ] Implement tenant context binding middleware (`tenant.bind` / `BindTenantContext`)
- [ ] Bind tenant context to container, session, and views/layouts
- [ ] Add `current_tenant()` helper
- [ ] Implement canonical slug redirect behavior
- [ ] Validate route/slug collision handling before pilot rollout
- [ ] Create parallel route groups during transition (new tenant routes + legacy `/admin`)
- [ ] Set up moderator route group (`/moderator/...`)
- [ ] Set up tenant route group (`/{provinceSlug}/{barangaySlug}/...`)
- [ ] (Optional) Set up province-only admin routes if adopted
- [ ] Retire or restrict legacy `/admin` routes after cutover

## 8. Route Generation and URL Safety

- [ ] Create `tenant_route()` helper
- [ ] Create `moderator_route()` helper
- [ ] Refactor views/controllers/services to stop hardcoding `/admin/...` URLs
- [ ] Ensure tenant route generation supports current context and explicit alternate context
- [ ] Ensure moderator route generation is isolated from tenant prefixes
- [ ] Update password reset, email verification, and invite links to include tenant context where applicable
- [ ] Test tenant-aware links for slug preservation and invalid/expired contexts

## 9. Authentication, Sessions, and Login Flows

- [ ] Implement moderator login portal (`/moderator/login`; optional admin subdomain login)
- [ ] Restrict moderator login to Moderator role accounts only
- [ ] Implement tenant login entry point `/{provinceSlug}/{barangaySlug}/login`
- [ ] Resolve tenant before rendering tenant login form
- [ ] Show tenant branding/name on tenant login form
- [ ] Validate account membership and scoped role assignment during tenant login
- [ ] Store tenant context in session (`tenant.province_id`, `tenant.barangay_id`, `tenant.scope_type`, route slug keys)
- [ ] Refactor login controllers/services to accept tenant slug route params
- [ ] Update `AuthSessionService` redirect logic to be tenant-aware
- [ ] Preserve moderator global redirect behavior
- [ ] Update new-login notification emails with tenant name/scope
- [ ] Implement membership-aware login behavior for invited/suspended states

## 10. RBAC Migration and Scope-Aware Authorization

- [ ] Define system roles (`moderator`, `province_admin`, `barangay_admin`, optional `barangay_staff`, optional `auditor`)
- [ ] Define/confirm permission set (tenant management, users, inventory, patient records, requests, suppliers, analytics, audit, etc.)
- [ ] Implement scope-aware permission evaluation using:
- [ ] permission name
- [ ] role assignment scope
- [ ] current tenant context
- [ ] target record tenant ownership
- [ ] Implement policies for tenant-owned models with role + scope checks
- [ ] Keep `user_levels` during transition
- [ ] Map existing `user_levels` to scoped roles
- [ ] Populate scoped `roles` and `role_assignments`
- [ ] Replace permission resolution from legacy `level.permissions` to scoped role resolver
- [ ] Remove `user_levels` dependency after full cutover

## 11. Backend Core Refactor (Services, Repositories, Models)

- [ ] Create/refactor core backend tenancy components listed in plan (`TenantContext`, `TenantResolver`, route generator helper, middleware, scope-aware policies)
- [ ] Refactor services to accept/use `TenantContext` instead of implicit `Auth::user()->branch_id`
- [ ] Centralize tenant filter application in service/repository layers
- [ ] Remove hardcoded branch IDs and RHU-specific assumptions
- [ ] Ensure tenant-owned repositories default to scoped queries
- [ ] Reserve unscoped queries for explicit Moderator workflows only
- [ ] Enforce tenant scope in exports and analytics repositories before user filters
- [ ] Implement model traits/scopes:
- [ ] `TenantScoped`
- [ ] (If used) `BelongsToProvince`
- [ ] (If used) `BelongsToBarangay`
- [ ] Add model query scopes (`forTenant`, `forProvince`, `forBarangay`)
- [ ] Update validation rules for tenant constraints (`BelongsToCurrentProvince`, `BelongsToCurrentBarangay`, `BelongsToCurrentTenant`)
- [ ] Reject cross-tenant foreign key references at validation/service layer
- [ ] Add tenant metadata to audit logs and application events

## 12. Hotspot Service Refactors (High Priority)

- [ ] Refactor `DashboardAdminService`
- [ ] Refactor `AnalyticsService`
- [ ] Refactor `InventoryAdminService`
- [ ] Refactor `PatientRecordsAdminService`
- [ ] Refactor `OrderAdminService`
- [ ] Refactor `RequestWorkflowService`
- [ ] Refactor `AvailabilityService`
- [ ] Refactor `ManageAccountAdminService`
- [ ] Refactor `NotificationService`
- [ ] Refactor `AuthSessionService`

## 13. Module-by-Module Backend Checklist (Do Not Miss)

- [ ] Authentication / Accounts: tenant slug resolver middleware, membership-aware login, scoped redirects, invite/password reset tenant links, session tenant binding/validation
- [ ] Users / Manage Account: replace branch assignment UX with province/barangay memberships, moderator creation of tenant admins, activation/suspension controls, scoped role assignment UI
- [ ] Inventory: tenant keys on inventory/movements, remove `branch_id` assumptions, restrict transfer boundaries, tenant-scoped exports
- [ ] Patient Records: tenant-scope patient tables, validate barangay belongs to current province, tenant-scoped exports, update dashboard joins
- [ ] Orders / Holds / Requests: tenant keys on parent/child records, prevent cross-tenant references, province cross-barangay workflows (if enabled), scoped analytics/lists
- [ ] Suppliers: tenant-scope `suppliers` and `supplier_products`, ensure linked inventory is same tenant, tenant-aware exports/dashboards
- [ ] Analytics / Dashboard: replace branch filters, add province aggregation queries, add Moderator global analytics, tenant-prefixed cache keys
- [ ] Notifications / Audit / History: tenant metadata on events/logs, scoped feeds, moderator cross-tenant audit search, alerts for suspicious cross-tenant access
- [ ] Exports / Reports: enforce tenant-safe query scoping, include tenant scope in export headers, block arbitrary ID filters escaping scope, explicit export leakage tests

## 14. Background Jobs, Events, Notifications, and Queues

- [ ] Implement tenant-aware queued job base class (`TenantAwareJob`) carrying `province_id`, `barangay_id`, `scope_type`
- [ ] Rehydrate `TenantContext` inside queued jobs
- [ ] Add job middleware to bind tenant context before handling job logic
- [ ] Namespace cache keys used by jobs by tenant
- [ ] Namespace notification queries by tenant
- [ ] Include tenant metadata in job-generated audit logs
- [ ] Validate queue worker deployment procedure (drain/restart) for tenant-aware code updates
- [ ] Verify queue jobs fail safely when tenant context is missing/invalid

## 15. Storage, File Access, and Exports

- [ ] Create `TenantStorageService` for tenant-scoped path generation
- [ ] Implement storage directory isolation for provinces and barangays (`storage/app/tenants/...`)
- [ ] Override direct `Storage::disk()` usage where tenant file paths are required
- [ ] Validate file access belongs to current tenant before read/download/delete
- [ ] Add signed URLs for secure file downloads
- [ ] Ensure exports/reports are stored under tenant-scoped directories
- [ ] Validate export isolation for moderator, province, and barangay scopes
- [ ] Include tenant headers/scope metadata in exports

## 16. Cache, Rate Limiting, and Performance Controls

- [ ] Implement tenant cache key namespacing pattern (platform/province/barangay)
- [ ] Create `TenantCacheService` (or equivalent cache wrapper) for tenant-aware remember/forget operations
- [ ] Implement tenant cache invalidation when tenant data changes
- [ ] Implement cache/session invalidation on role or membership changes
- [ ] Configure per-tenant rate limiting (`tenant-api`, `tenant-login`) using tenant scope + IP
- [ ] Apply throttle middleware to tenant API and login routes
- [ ] Add DDoS / abuse prevention checks (per-IP + per-tenant login attempt tracking)
- [ ] Load test province aggregations, exports, route resolution, and queue throughput under multi-tenant load

## 17. Frontend and UI Refactor (Tenant-Aware + Role-Based)

- [ ] Create tenant-aware navigation
- [ ] Create moderator navigation (separate from tenant navigation)
- [ ] Add current tenant badge to layouts/header
- [ ] Add moderator tenant switcher (and optional provincial multi-barangay switcher if enabled)
- [ ] Update route generation in Blade/views to use tenant-aware helpers
- [ ] Implement role-based menu/module rendering (with server-side auth enforcement retained)
- [ ] Provide `CurrentAccessContext` payload to layouts/view models
- [ ] Build moderator login page
- [ ] Build tenant login page with slug + tenant branding
- [ ] Build province/barangay onboarding pages (Moderator access)
- [ ] Build invite acceptance flow with tenant-aware redirects
- [ ] Ensure forms/dropdowns are backend-filtered by tenant (inventory, suppliers, barangays, user assignments, export filters)
- [ ] Build tenant settings UI (province/barangay as applicable)
- [ ] Implement role/scope-based dashboard widget composition
- [ ] Implement tenant-aware frontend analytics endpoints and empty states for new tenants

## 18. Dashboards by Role (Feature Breakdown)

- [ ] Moderator dashboard: province/barangay status, active users, usage metrics, health summary, data integrity alerts, security/audit events, onboarding wizard, support tools, future billing hooks
- [ ] Moderator pages: province management, barangay management, memberships/user assignments, role/permission templates, global catalog, platform audit/observability
- [ ] Provincial admin dashboard: KPI summary across barangays, comparative metrics, low-stock alerts, activity feed, province-scoped user management, suppliers (if allowed), audit logs, notifications center
- [ ] Provincial admin pages: barangay directory/activation, province user management/roles, aggregated reports/exports, cross-barangay workflows (if enabled), province settings
- [ ] Barangay admin dashboard: local inventory summary, low stock/expiring batches, orders/requests/holds queues, patient summaries, local suppliers, notifications/audit history, settings
- [ ] Barangay admin pages: inventory, patient records, orders/holds/requests, suppliers/batch links, reports/exports, delegated user management (if enabled)

## 19. API, Webhooks, Feature Flags, and Integrations

- [ ] Define multi-tenant API versioning and route structure
- [ ] Implement tenant-scoped API authentication (Sanctum/Passport or chosen approach)
- [ ] Store tenant context in token abilities/claims
- [ ] Validate API token tenant matches request tenant
- [ ] Create `tenant_webhooks` table and webhook event catalog
- [ ] Implement per-tenant webhook configuration
- [ ] Implement webhook delivery security and tenant scoping
- [ ] Create `tenant_features` table and feature flag service
- [ ] Define default feature flags and tenant overrides
- [ ] Add environment-driven feature flags for phased rollout and emergency disable (kill switches)

## 20. Email and Notification Tenant Customization

- [ ] Implement tenant email settings support (branding/from name, etc. as needed)
- [ ] Make mailables tenant-aware
- [ ] Ensure invite/password reset/email verification/new-login emails preserve tenant context
- [ ] Test tenant-aware email links end to end in staging and production smoke tests

## 21. Security and Compliance

- [ ] Enforce authorization policies on all tenant routes
- [ ] Never trust client-supplied tenant IDs; resolve from route/session/context
- [ ] Validate all tenant foreign key references belong to current tenant
- [ ] Validate file uploads remain within tenant storage boundaries
- [ ] Include tenant context in all background jobs and logs
- [ ] Log all moderator tenant switching/impersonation actions
- [ ] Rate limit by tenant + IP
- [ ] Add account lockouts / alerts for repeated login failures
- [ ] Log and alert cross-tenant access attempts
- [ ] Implement tenant-aware exception handling and structured logging with tenant metadata
- [ ] Configure critical alerts for tenancy-related failures (leakage attempts, queue failures, login abuse, etc.)
- [ ] (Optional/Planned) Add 2FA support per tenant / sensitive actions
- [ ] (Optional/Planned) Add encrypted 2FA secrets and enforcement policies
- [ ] Implement session security and moderator tenant switching safeguards (session isolation / fixation protections)
- [ ] Implement data archiving and retention policies
- [ ] Create archive tracking (`archived_records`) and restore capability for moderators
- [ ] Identify and protect PII fields
- [ ] Encrypt sensitive patient data at rest where required
- [ ] Audit PII access
- [ ] Support data subject request export/delete workflows (`data_subject_requests`)

## 22. Tenant Usage, Health, and Incident Operations

- [ ] Define quota configuration for barangay/province limits (users, inventory, records, storage, API calls)
- [ ] Implement `tenant_usage` tracking table and usage service
- [ ] Enforce quota checks in relevant create/update workflows
- [ ] Create `tenant_health` table and health monitoring service/command
- [ ] Implement `tenant:health-check` command and scoped variants
- [ ] Track health metrics (DB connectivity, queue success, API response time, storage usage, error rates, user activity)
- [ ] Create `tenant_incidents` table and incident response workflow
- [ ] Define incident types (breach suspicion, cross-tenant access, corruption, degradation, exploited vulnerability)
- [ ] Implement incident lifecycle (detect, record, notify, investigate, contain, resolve, post-mortem, harden)

## 23. Tenant Onboarding, Suspension, and Data Portability

- [ ] Implement tenant onboarding workflow (Provisioning -> Configuration -> Activation)
- [ ] Create onboarding state machine + `tenant_onboarding` table
- [ ] Create onboarding checklist and moderator approval steps
- [ ] Implement tenant invitation system (`tenant_invitations`) and invitation acceptance flow
- [ ] Implement invitation expiration configuration and service
- [ ] Implement tenant suspension/deactivation flow and `tenant_suspensions` table
- [ ] Support suspension types (voluntary, payment, compliance, security, administrative)
- [ ] Implement tenant export/import services for onboarding/migrations/incidents
- [ ] Support tenant-targeted data export for support/incident response
- [ ] Consider logical tenant export/import tooling for migrations and restores

## 24. Billing and Subscription (Future-Ready)

- [ ] Create `tenant_subscriptions` schema and plan configuration (future-ready)
- [ ] Add billing integration hooks without blocking core SaaS cutover
- [ ] Keep billing toggled/disabled until product/business readiness

## 25. Data Migration Strategy (Existing Data -> SaaS)

- [ ] Phase 0: Inventory all tables and identify tenant-owned rows
- [ ] Phase 0: Decide canonical branch -> province/barangay mapping
- [ ] Phase 0: Define default province for existing deployment data
- [ ] Phase 0: Back up production DB and validate restore procedure
- [ ] Phase 0: Create migration rehearsal environment with production-like snapshot
- [ ] Phase 1: Introduce core tenant tables (`provinces`, `barangays` upgrades, memberships, roles, assignments) with non-breaking nullable changes
- [ ] Phase 2: Add tenant columns to existing tenant-owned tables (nullable first)
- [ ] Phase 2: Add indexes before strict constraints
- [ ] Phase 2: Backfill in batches
- [ ] Phase 2/3: Derive `province_id` and `barangay_id` using documented mapping rules (including province-scoped rows with `barangay_id = null`)
- [ ] Phase 3: Map moderator accounts and existing admins to scoped roles
- [ ] Phase 3: Populate `tenant_memberships`
- [ ] Phase 3: Populate `role_assignments`
- [ ] Phase 3: Keep legacy `user_level_id` temporarily
- [ ] Phase 4: Implement dual-read/dual-write tenancy application layer (where needed)
- [ ] Phase 4: Monitor for missing tenant keys on new writes
- [ ] Phase 4: Add alerts for writes missing tenant keys during transition
- [ ] Phase 4/5: Run validation scripts and reconciliation checks (counts + sampled records)
- [ ] Phase 5: Create tenant-slug routes and enable limited pilot (one province + selected barangays)
- [ ] Phase 5: Validate pilot modules (login, dashboard, inventory, patient records, orders/requests/holds, suppliers, exports)
- [ ] Phase 5: Run leakage tests between pilot tenants
- [ ] Phase 6: Enforce `NOT NULL` and FK constraints (where applicable)
- [ ] Phase 6: Remove/deprecate direct `branch_id` assumptions in services/routes
- [ ] Phase 6: Make tenant routes default entry point
- [ ] Phase 6: Retire legacy `/admin` path or restrict to moderator redirect only
- [ ] Phase 7: Remove compatibility code
- [ ] Phase 7: Retire `user_levels` after full cutover
- [ ] Phase 7: Remove hardcoded branch constants and RHU assumptions
- [ ] Phase 7: Update seeders, factories, tests for tenant keys
- [ ] Phase 7: Update docs and onboarding SOPs

## 26. Testing and Hardening (Mandatory)

- [ ] Unit tests: tenant resolver
- [ ] Unit tests: tenant context builder
- [ ] Unit tests: scoped permission evaluator
- [ ] Unit tests: tenant-aware repository filters
- [ ] Feature tests: moderator access to all tenant dashboards
- [ ] Feature tests: provincial admin blocked from other provinces
- [ ] Feature tests: barangay admin blocked from other barangays (same province included)
- [ ] Feature tests: tenant login via slug
- [ ] Feature tests: invalid slug -> 404 or branded error page
- [ ] Feature tests: cross-tenant ID submission rejected (422/403)
- [ ] Regression tests: inventory CRUD
- [ ] Regression tests: patient records CRUD
- [ ] Regression tests: orders/holds/requests workflow
- [ ] Regression tests: supplier linking
- [ ] Regression tests: exports (inventory/patient/supplier)
- [ ] Regression tests: analytics endpoints
- [ ] Data leakage matrix across at least 2 provinces x 2 barangays:
- [ ] list endpoints
- [ ] detail pages
- [ ] create/update/delete
- [ ] exports
- [ ] AJAX analytics APIs
- [ ] background notifications
- [ ] audit logs
- [ ] Performance/load testing: province dashboard aggregation
- [ ] Performance/load testing: tenant route resolution overhead
- [ ] Performance/load testing: export generation under multi-tenant load
- [ ] Performance/load testing: queue throughput with tenant-tagged jobs
- [ ] Security audit (authz coverage, leakage paths, export filters, logging/alerts)
- [ ] Test tenant-aware password reset, email verification, and invitation links
- [ ] Test moderator tenant switching/impersonation with audit trail and session isolation
- [ ] Test cache/session invalidation after role or membership changes
- [ ] Test canonical slug redirects and route collision handling

## 27. Local Setup and Smoke Validation

- [ ] Run migrations locally (`php artisan migrate`)
- [ ] Create a sample province and barangay for tenancy smoke testing
- [ ] Create sample tenant memberships (platform + barangay)
- [ ] Verify tenant routes locally (`/{provinceSlug}/{barangaySlug}/dashboard`)
- [ ] Verify moderator routes locally (`/moderator/dashboard`)
- [ ] Smoke-test tenant resolver and `current_tenant()`
- [ ] Smoke-test membership validation and middleware stack
- [ ] Verify key tenancy Artisan commands exist and work (`tenant:health-check`, `tenant:cache:clear`, `tenant:usage:report`)

## 28. Production Deployment, Cutover, and Post-Deploy Ops

- [ ] Pre-deployment: run migrations
- [ ] Pre-deployment: seed provinces and barangays
- [ ] Pre-deployment: create tenant memberships for users
- [ ] Pre-deployment: backfill existing data with tenant keys
- [ ] Pre-deployment: run reconciliation checks (counts + samples) and confirm no unexpected null tenant keys
- [ ] Pre-deployment: configure tenant storage paths
- [ ] Pre-deployment: set up cache namespacing
- [ ] Pre-deployment: enable tenant-aware queue worker/job context
- [ ] Pre-deployment: configure per-tenant rate limiting
- [ ] Pre-deployment: set up monitoring dashboards/alerts
- [ ] Pre-deployment: validate canonical province/barangay slugs and redirect behavior
- [ ] Pre-deployment: rehearse rollback/restore in staging from recent backup
- [ ] Pre-deployment: prepare queue worker drain/restart procedure
- [ ] Pre-deployment: confirm feature-flag rollback switches (if phased rollout)
- [ ] Pre-deployment: approve cutover freeze window and rollback trigger criteria
- [ ] Deployment: run migrations in planned window
- [ ] Deployment: seed provinces/barangays and create memberships/roles
- [ ] Deployment: run reconciliation checks for tenant keys and core tables
- [ ] Deployment: test tenant routes (including canonical redirects)
- [ ] Deployment: verify data isolation and scoped access
- [ ] Deployment: restart/drain queue workers with tenant-aware code
- [ ] Deployment: enable production monitoring
- [ ] Post-deployment: monitor tenant health checks
- [ ] Post-deployment: review audit logs for cross-tenant access attempts
- [ ] Post-deployment: validate export isolation
- [ ] Post-deployment: verify queue job tenant context
- [ ] Post-deployment: run post-cutover reconciliation and null tenant-key scans
- [ ] Post-deployment: verify tenant-aware password reset/invite/email verification links
- [ ] Post-deployment: verify cache/session invalidation after role/membership changes
- [ ] Post-deployment: confirm rollback toggles / feature flags can be disabled quickly if needed

## 29. Observability, Logging, and Support Runbooks

- [ ] Include tenant IDs/slugs in logs
- [ ] Tag errors by tenant scope
- [ ] Implement tenant-aware exception handling in error reporting
- [ ] Implement structured logging with tenant context service / log format
- [ ] Add dashboard for failed jobs by tenant
- [ ] Maintain audit trail for moderator actions and impersonation
- [ ] Create operational runbook (deployment, rollback, queue drain/restart, incident response)
- [ ] Document full restore process
- [ ] Document tenant onboarding SOP
- [ ] Document incident response SOP
- [ ] Create final sign-off checklist and rollback plan for cutover

## 30. Environment and Configuration

- [ ] Create `config/tenancy.php`
- [ ] Centralize moderator prefix, session keys, invitation expiry, quotas, features, route slug behavior
- [ ] Configure tenancy environment variables (moderator prefix, invitation expiry, cache prefix, storage disk, etc.)
- [ ] Configure session settings for tenant-aware security requirements
- [ ] Configure logging settings/format for tenant metadata
- [ ] Configure rate limiting settings
- [ ] Configure feature flags via environment for phased rollout and emergency disable

## 31. Acceptance Criteria (Definition of Done)

- [ ] Moderator, Provincial Admin, and Barangay Admin can log in via intended routes
- [ ] Tenant data isolation is enforced across all modules and exports
- [ ] No tenant-owned table can be written without tenant keys
- [ ] All core workflows operate correctly within tenant context
- [ ] Dashboards are role-specific and scope-accurate
- [ ] Existing data migration is completed and validated
- [ ] Automated tests cover tenant access controls and isolation regressions
- [ ] Operational monitoring and audit trails include tenant metadata
- [ ] Final security review completed
