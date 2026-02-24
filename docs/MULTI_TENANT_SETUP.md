# Multi-Tenant SaaS Setup Guide

## Architecture Overview

GTIMS uses a **single database, shared schema, row-level tenant partitioning** approach for multi-tenancy.

### Tenant Hierarchy

1. **Platform** (implicit, system-wide) — Moderator access
2. **Province** (top-level tenant container) — Provincial Administrator access
3. **Barangay** (child tenant under a province) — Barangay Administrator access

### URL Structure

| Route Pattern | Role | Example |
|---|---|---|
| `/{provinceSlug}/{barangaySlug}/...` | Tenant access | `/bulacan/malolos/dashboard` |
| `/moderator/...` | Moderator portal | `/moderator/dashboard` |
| `/admin/...` | Legacy admin (preserved) | `/admin/dashboard` |

## Database Schema

### New Tables

- `provinces` — Province definitions with slugs
- `tenant_memberships` — User-to-scope assignments (platform/province/barangay)
- `tenant_roles` — Scoped role definitions
- `role_assignments` — User-to-role-to-scope assignments

### Modified Tables

- `barangays` — Added `province_id`, `slug`, `is_active`, `external_code`, `settings_json`
- All tenant-owned tables — Added nullable `province_id` and `barangay_id` columns:
  - `inventories`, `orders`, `patientrecords`, `dispensedmedications`, `holds`
  - `incoming_requests`, `suppliers`, `notifications`, `audit_events`
  - `history_logs`, `product_movements`

## Local Setup

### 1. Run Migrations

```bash
php artisan migrate
```

### 2. Create a Province

```php
use App\Models\Province;

Province::create([
    'name' => 'Bulacan',
    'slug' => 'bulacan',
    'is_active' => true,
]);
```

### 3. Create a Barangay

```php
use App\Models\Barangay;

Barangay::create([
    'barangay_name' => 'Malolos',
    'province_id' => 1, // Bulacan's ID
    'slug' => 'malolos',
    'is_active' => true,
]);
```

### 4. Assign a User to a Tenant

```php
use App\Models\TenantMembership;

// Platform (Moderator)
TenantMembership::create([
    'user_id' => 1,
    'scope_type' => 'platform',
    'scope_id' => null,
    'is_primary' => true,
    'status' => 'active',
]);

// Barangay Admin
TenantMembership::create([
    'user_id' => 2,
    'scope_type' => 'barangay',
    'scope_id' => 1, // Malolos barangay ID
    'is_primary' => true,
    'status' => 'active',
]);
```

### 5. Access Tenant Routes

- Visit `http://localhost:8000/bulacan/malolos/dashboard` as a barangay admin
- Visit `http://localhost:8000/moderator/dashboard` as a moderator

## Key Components

### TenantContext (`App\Tenancy\TenantContext`)

Immutable value object representing the current tenant scope:

```php
$ctx = current_tenant(); // Get current tenant context (helper function)
$ctx->isPlatform();      // true for moderators
$ctx->isProvince();      // true for province admins
$ctx->isBarangay();      // true for barangay admins
$ctx->provinceId;        // Current province ID
$ctx->barangayId;        // Current barangay ID (null for province scope)
```

### TenantResolver (`App\Tenancy\TenantResolver`)

Resolves tenant context from URL slugs and validates user membership:

```php
$resolver = app(TenantResolver::class);
$ctx = $resolver->fromSlugs('bulacan', 'malolos');
$hasMembership = $resolver->userHasMembership($user, $ctx);
```

### Middleware Stack

1. `tenant.resolve` — Resolves tenant from URL slugs
2. `tenant.membership` — Validates user has access to tenant
3. `tenant.bind` — Binds TenantContext to app container, session, and views

### Model Traits

```php
use App\Models\Traits\TenantScoped;

class Inventory extends Model
{
    use TenantScoped; // Adds forTenant(), forProvince(), forBarangay() scopes
}

// Usage:
Inventory::forTenant($ctx)->get();
Inventory::forProvince($provinceId)->get();
Inventory::forBarangay($provinceId, $barangayId)->get();
```

### Route Helpers

```php
// Generate tenant-scoped route
tenant_route('tenant.dashboard'); // Uses current tenant context
tenant_route('tenant.orders.index', [], $specificContext);

// Generate moderator route
moderator_route('moderator.dashboard');
```

## Configuration

See `config/tenancy.php` for:
- Tenant identification strategy
- Moderator route prefix
- System role definitions
- Session key configuration

## Testing

```bash
# Run all tenancy tests
php vendor/bin/phpunit tests/Unit/Tenancy/
php vendor/bin/phpunit tests/Feature/Tenancy/

# Run full test suite
php vendor/bin/phpunit
```

## Production Deployment

1. Run migrations: `php artisan migrate`
2. Seed provinces and barangays
3. Create tenant memberships for users
4. Tenant routes will be available immediately at `/{provinceSlug}/{barangaySlug}/...`
5. Legacy `/admin` routes continue to work for backward compatibility
