# Multi-Tenant SaaS Setup Guide

> **Last Updated:** 2026-02-27
> **Scope:** Country-wide multi-tenant SaaS — all PH provinces and barangays as first-class tenants.

## Architecture Overview

GTIMS uses a **single database, shared schema, row-level tenant partitioning** approach for multi-tenancy, supporting all Philippine provinces (82) and barangays (42,000+) as first-class tenants.

### Tenant Hierarchy

| Level | Role | Scope | Responsibilities |
|-------|------|-------|------------------|
| 1 | **Moderator** | Platform | Manages subscriptions, SaaS business settings, creates/manages Super Admin accounts |
| 2 | **Super Admin** | Province | Handler for assigned province; manages Admins and users under their province |
| 3 | **Admin** | Barangay | Manages operations within a single barangay |
| 4 | **Staff / Users** | Barangay | Operate within strictly isolated tenant data |

### URL Structure

| Route Pattern | Role | Example |
|---|---|---|
| `/{provinceSlug}/{barangaySlug}/...` | Tenant access | `/bulacan/malolos/dashboard` |
| `/moderator/...` | Moderator portal | `/moderator/dashboard` |

> **Important:** The system does NOT use non-tenant URLs like `/admin/dashboard`. All application routes and views are accessed using URL path slugs. This is a SaaS with tenant-aware navigation.

### Data Isolation Strategy

- All tenant-owned data includes `province_id` and `barangay_id` columns.
- Queries are automatically scoped by tenant context using global scopes and `TenantScoped` trait.
- Shared data (products, permissions) is globally accessible.
- Inventories, orders, tracking records, logs, analytics, notifications, and all module data are accessible **only** within the correct `province_id` + `barangay_id` scope.
- Defense-in-depth: middleware (`tenant.resolve`) + policies/gates + global scopes + service-level guardrails.

## Database Schema

### New Tables

| Table | Purpose |
|-------|---------|
| `provinces` | Province definitions with slugs |
| `tenant_memberships` | User-to-scope assignments |
| `roles` | Scoped role definitions |
| `role_assignments` | User-to-role-to-scope assignments |
| `tenant_invitations` | User invitation management |
| `tenant_suspensions` | Suspension tracking |
| `tenant_subscriptions` | Billing/subscription (future) |
| `tenant_webhooks` | Webhook configurations |
| `tenant_features` | Feature flags per tenant |
| `tenant_usage` | Usage tracking for quotas |
| `tenant_health` | Health monitoring |
| `tenant_incidents` | Incident tracking |
| `tenant_onboarding` | Onboarding state |
| `archived_records` | Data archiving |
| `data_subject_requests` | Compliance/privacy requests |

### Modified Tables

#### `barangays` — Added Fields

- `province_id` (FK to provinces)
- `slug` (unique within province)
- `is_active`
- `external_code`
- `settings_json`

#### Tenant-Owned Tables — Added Columns

All tenant-owned tables require:

- `province_id` (BIGINT, indexed)
- `barangay_id` (BIGINT NULLABLE, indexed)

Affected tables:

- `users`, `branches`
- `inventories`, `orders`, `order_items`
- `patientrecords`, `dispensedmedications`
- `holds`, `hold_items`
- `incoming_requests`, `request_items`, `request_comments`, `request_attachments`
- `suppliers`, `supplier_products`
- `notifications`, `audit_events`
- `history_logs`, `product_movements`
- `low_stock_settings`, `reorder_rules`
- `idempotency_keys`

### Required Indexes

```sql
-- Standard tenant indexes
CREATE INDEX idx_table_tenant ON table_name(province_id, barangay_id);
CREATE INDEX idx_table_province_date ON table_name(province_id, created_at);
CREATE INDEX idx_table_barangay_date ON table_name(barangay_id, created_at);

-- Module-specific indexes
CREATE INDEX idx_inventories_tenant_product ON inventories(province_id, barangay_id, product_id);
CREATE INDEX idx_patientrecords_tenant_date ON patientrecords(province_id, barangay_id, date_dispensed);
CREATE INDEX idx_requests_tenant_status ON incoming_requests(province_id, barangay_id, status, created_at);
```

## Local Setup

### 1. Run Migrations

```bash
php artisan migrate
```

### 2. Seed Country-Wide Data (DemoSeeder)

All seeders are organized via a single `DemoSeeder` entry point:

```bash
php artisan db:seed --class=DemoSeeder
```

This runs in order:

| Seeder | What It Does |
|--------|--------------|
| `ProvinceBarangaySeeder` | Seeds all 82 PH provinces and 42,000+ barangays with unique/stable slugs |
| `ModeratorSeeder` | Creates platform-level Moderator account |
| `SuperAdminSeeder` | Creates Super Admin accounts mapped to demo provinces |
| `AdminUserSeeder` | Creates Admins and staff under each Super Admin |
| `TenantDemoDataSeeder` | Seeds per-tenant sample data for configurable demo provinces |

All seeders are **idempotent** (safe to rerun) and use chunk inserts for performance.

### Seeder Configuration

```php
// config/tenancy.php -> 'seeder'
'seeder' => [
    'seed_all_geo' => true,                         // Seed ALL provinces + barangays
    'demo_provinces' => ['bulacan', 'cebu', 'davao-del-sur'], // Demo data only for these
    'demo_barangays_per_province' => 5,
    'demo_records_per_module' => 50,
    'chunk_size' => 500,
],
```

### Seed Only Geographic Data

```bash
php artisan db:seed --class=ProvinceBarangaySeeder
```

### Seed Demo Data for a Specific Province

```bash
php artisan tenant:seed-demo --province=bulacan
```

### 3. Verify Seeder Integrity

```bash
php artisan tenant:migration null-scan
php artisan tenant:validate-slugs
php artisan tenant:health-check
```

## Core Components

### TenantContext (`App\Tenancy\TenantContext`)

Immutable value object representing the current tenant scope:

```php
$ctx = current_tenant(); // Helper function

$ctx->isPlatform();      // true for moderators
$ctx->isProvince();      // true for province admins
$ctx->isBarangay();      // true for barangay admins
$ctx->provinceId;        // Current province ID
$ctx->barangayId;        // Current barangay ID (null for province scope)
$ctx->scopeType;         // 'platform', 'province', or 'barangay'
```

### TenantResolver (`App\Tenancy\TenantResolver`)

Resolves tenant context from URL slugs and validates user membership:

```php
$resolver = app(TenantResolver::class);
$ctx = $resolver->fromSlugs('bulacan', 'malolos');
$hasMembership = $resolver->userHasMembership($user, $ctx);
```

### Middleware Stack

| Middleware | Purpose |
|------------|---------|
| `tenant.resolve` | Resolves tenant from URL slugs |
| `tenant.membership` | Validates user has access to tenant |
| `tenant.bind` | Binds TenantContext to container, session, views |

### Model Traits

```php
use App\Models\Traits\TenantScoped;

class Inventory extends Model
{
    use TenantScoped;
}

// Usage:
Inventory::forTenant($ctx)->get();
Inventory::forProvince($provinceId)->get();
Inventory::forBarangay($provinceId, $barangayId)->get();
```

### Route Helpers

```php
// Generate tenant-scoped route
tenant_route('tenant.dashboard');                    // Current context
tenant_route('tenant.orders.index', [], $otherCtx); // Specific context

// Generate moderator route
moderator_route('moderator.dashboard');
```

## Storage Isolation

### Directory Structure

```
storage/app/tenants/
├── provinces/{province_id}/
│   ├── exports/
│   ├── imports/
│   └── documents/
└── barangays/{province_id}/{barangay_id}/
    ├── exports/
    ├── imports/
    ├── attachments/
    └── patient_documents/
```

### Usage

```php
use App\Services\TenantStorageService;

$path = app(TenantStorageService::class)->tenantPath('exports/inventory.csv', $ctx);
Storage::put($path, $content);
```

## Queue and Job Context

### Tenant-Aware Jobs

```php
use App\Jobs\TenantAwareJob;

class ProcessExport extends TenantAwareJob
{
    public function __construct(TenantContext $ctx, array $params)
    {
        parent::__construct($ctx);
        $this->params = $params;
    }

    public function handle(): void
    {
        $ctx = $this->getTenantContext();
        // Job logic with tenant context
    }
}
```

## Cache Namespacing

```php
use App\Services\TenantCacheService;

$cache = app(TenantCacheService::class);

// Get/set with tenant namespace
$value = $cache->remember($ctx, 'dashboard_stats', 3600, function () {
    return $this->calculateStats();
});

// Invalidate tenant cache
$cache->forgetTenant($ctx);
```

## Authentication Flows

### Moderator Login

1. Visit `/moderator/login`
2. Only Moderator role accounts can authenticate
3. Access to global dashboard and tenant management

### Tenant Login

1. Visit `/{provinceSlug}/{barangaySlug}/login`
2. Tenant resolved from URL before showing login form
3. Form displays tenant branding
4. Session stores tenant context

### Session Data

```php
session([
    'tenant.province_id' => 1,
    'tenant.barangay_id' => 5,
    'tenant.scope_type' => 'barangay',
    'tenant.route_slug_province' => 'bulacan',
    'tenant.route_slug_barangay' => 'malolos',
]);
```

## Invitation System

### Create Invitation

```php
use App\Services\TenantInvitationService;

$invitation = app(TenantInvitationService::class)
    ->create($ctx, 'user@example.com', $roleId);
```

### Accept Invitation

User visits: `/{province}/{barangay}/invite/accept/{token}`

### Invitation Expiration

Default: 7 days (configurable in `config/tenancy.php`)

## Feature Flags

```php
use App\Services\TenantFeatureService;

$features = app(TenantFeatureService::class);

if ($features->isEnabled($ctx, 'advanced_analytics')) {
    // Show advanced analytics
}
```

### Available Features

| Feature Key | Description |
|-------------|-------------|
| `advanced_analytics` | Province-wide analytics |
| `cross_barangay_requests` | Request inventory from other barangays |
| `custom_branding` | Tenant-specific branding |
| `webhooks` | Webhook integrations |
| `api_access` | API token access |

## Usage Quotas

### Default Quotas

| Metric | Barangay Limit | Province Limit |
|--------|----------------|----------------|
| Users | 10 | 100 |
| Inventory Items | 5,000 | Unlimited |
| Patient Records | 50,000 | Unlimited |
| Storage | 500 MB | 5 GB |
| API Calls/Day | 10,000 | 100,000 |

### Check Quota

```php
use App\Services\TenantUsageService;

$usage = app(TenantUsageService::class);

if ($usage->isOverQuota($ctx, 'inventory_items')) {
    // Prevent new inventory creation
}
```

## Health Monitoring

### Run Health Check

```bash
php artisan tenant:health-check
php artisan tenant:health-check --province=1
php artisan tenant:health-check --barangay=5
```

### Health Metrics

- Database connectivity
- Queue job success rate
- API response times
- Storage usage
- Recent error rates

## Configuration

### config/tenancy.php

```php
return [
    'moderator_prefix' => 'moderator',
    'session_keys' => [
        'province_id' => 'tenant.province_id',
        'barangay_id' => 'tenant.barangay_id',
        'scope_type' => 'tenant.scope_type',
    ],
    'invitation' => [
        'expires_days' => 7,
    ],
    'quotas' => [
        'barangay' => [...],
        'province' => [...],
    ],
    'features' => [
        'defaults' => [...],
    ],
];
```

## Testing

### Run Tests

```bash
# Unit tests
php vendor/bin/phpunit tests/Unit/Tenancy/

# Feature tests
php vendor/bin/phpunit tests/Feature/Tenancy/

# Full suite
php vendor/bin/phpunit
```

### Key Test Cases

- Tenant resolver correctly parses slugs across all PH provinces/barangays
- User membership validation works for Moderator, Super Admin, Admin, Staff
- Cross-tenant access is blocked (Province A user cannot access Province B data)
- Cross-barangay access is blocked within same province
- Data isolation in queries via `TenantScoped` trait and global scopes
- Export isolation
- API rate limiting per tenant
- Tenant-aware password reset / email verification / invitation links preserve tenant slug context
- Moderator tenant switching / impersonation keeps session scope isolated and audit logs complete
- Super Admin can manage Admins only within assigned province
- Cache/session invalidation works after role or membership changes
- Canonical slug redirects work and invalid slugs return expected errors
- Seeder integrity — all seeded data has correct `province_id` + `barangay_id`

### Quality Cycle (Run Until Stable)

```bash
# Step 1: Build / Autoload
composer dump-autoload
npm run build

# Step 2: Syntax / Config Check
php artisan route:clear && php artisan config:clear && php artisan cache:clear

# Step 3: Unit / Integration Tests
php vendor/bin/phpunit --stop-on-failure

# Step 4: Tenant Smoke Tests
php artisan tenant:smoke-test
```

## Production Deployment

### Pre-Deployment Checklist

- [ ] Run migrations
- [ ] Seed provinces and barangays
- [ ] Create tenant memberships for users
- [ ] Backfill existing data with tenant keys
- [ ] Run reconciliation checks (counts + samples) to confirm tenant keys are populated
- [ ] Configure storage paths
- [ ] Set up cache namespacing
- [ ] Enable queue worker with tenant context
- [ ] Configure rate limiting
- [ ] Set up monitoring
- [ ] Validate canonical province/barangay slugs and redirect behavior
- [ ] Rehearse rollback / restore using a recent backup in staging
- [ ] Prepare queue worker drain/restart procedure for deployment
- [ ] Confirm feature-flag rollback switches (if using phased rollout)

### Deployment Steps

1. Run migrations: `php artisan migrate`
2. Seed all provinces and barangays: `php artisan db:seed --class=DemoSeeder`
3. Verify Moderator and Super Admin accounts created
4. Run reconciliation checks for tenant keys and core tenant-owned tables
5. Test tenant routes across multiple provinces (including canonical slug redirects)
6. Verify data isolation: user from Province A blocked from Province B
7. Restart/drain queue workers with tenant-aware job code loaded
8. Enable production monitoring

### Post-Deployment

- Monitor tenant health checks across all active provinces
- Review audit logs for cross-tenant access attempts
- Validate export isolation
- Check queue job tenant context
- Run post-cutover reconciliation and null tenant-key scans
- Verify tenant-aware password reset / invite / email verification links in production
- Verify cache/session invalidation after role or membership changes
- Verify Super Admin can only manage users within assigned province
- Confirm rollback toggles / feature flags can be disabled quickly if needed

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| 404 on tenant routes | Slug not found | Verify province/barangay exists with correct slug |
| Access denied | No membership | Create tenant_membership record |
| Cross-tenant data visible | Missing scope | Add TenantScoped trait to model |
| Queue job fails | Missing context | Extend TenantAwareJob |

### Debug Commands

```bash
# Check tenant context
php artisan tinker
>>> current_tenant()

# Verify membership
>>> User::find(1)->memberships

# Check tenant routes
>>> php artisan route:list --path=tenant
```

## Rate Limiting

### Per-Tenant Rate Limiting

```php
// In App\Providers\AppServiceProvider.php
RateLimiter::for('tenant-api', function (Request $request) {
    $tenantId = current_tenant()->provinceId . ':' . current_tenant()->barangayId;
    return Limit::perMinute(100)->by($tenantId . ':' . $request->ip());
});

RateLimiter::for('tenant-login', function (Request $request) {
    $slug = $request->route('province') . '/' . $request->route('barangay');
    return Limit::perMinute(5)->by($slug . ':' . $request->ip());
});
```

### Apply to Routes

```php
Route::middleware(['throttle:tenant-api'])->group(function () {
    // Tenant API routes
});

Route::middleware(['throttle:tenant-login'])->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
});
```

## Error Handling and Logging

### Tenant-Aware Logging

```php
// In App\Exceptions\Handler.php
public function report(Throwable $exception)
{
    if (app()->bound(TenantContext::class)) {
        $ctx = app(TenantContext::class);
        Log::withContext([
            'tenant_province_id' => $ctx->provinceId,
            'tenant_barangay_id' => $ctx->barangayId,
            'tenant_scope' => $ctx->scopeType,
        ]);
    }
    
    parent::report($exception);
}
```

### Log Format

```
[2024-01-15 10:30:00] production.ERROR: Exception message {"tenant_province_id":1,"tenant_barangay_id":5,"tenant_scope":"barangay","user_id":42}
```

## Security Best Practices

1. Always use `TenantScoped` trait on tenant-owned models
2. Never trust client-supplied tenant IDs
3. Use middleware to enforce tenant context
4. Audit all cross-tenant operations
5. Rate limit by tenant + IP
6. Validate file uploads are within tenant storage
7. Include tenant context in all background jobs
8. Log all tenant switching by moderators
9. Never expose internal tenant IDs in URLs or APIs
10. Validate all foreign key references belong to current tenant

## Quick Reference

### Key Service Classes

| Service | Purpose |
|---------|---------|
| `TenantContext` | Current tenant scope value object |
| `TenantResolver` | Resolve tenant from slugs/session |
| `TenantStorageService` | Tenant file path generation |
| `TenantCacheService` | Tenant-namespaced caching |
| `TenantFeatureService` | Feature flag checks |
| `TenantUsageService` | Quota tracking |
| `TenantInvitationService` | User invitations |
| `TenantSuspensionService` | Tenant suspension management |

### Key Artisan Commands

```bash
php artisan tenant:health-check          # Check tenant health
php artisan tenant:health-check --province=1
php artisan tenant:cache:clear           # Clear tenant cache
php artisan tenant:usage:report          # Generate usage report
```

### Environment Variables

```env
TENANCY_MODERATOR_PREFIX=moderator
TENANCY_INVITATION_EXPIRE_DAYS=7
TENANCY_CACHE_PREFIX=tenant
TENANCY_STORAGE_DISK=local
```
