# GTIMS Tenant Onboarding SOP

> **Last Updated:** 2026-02-27
> **Scope:** Country-wide multi-tenant SaaS — all PH provinces and barangays as first-class tenants.

## Tenant Hierarchy

| Role | Scope | Onboarding Responsibility |
|------|-------|---------------------------|
| **Moderator** | Platform | Creates provinces, assigns Super Admins, manages subscriptions/SaaS settings |
| **Super Admin** | Province | Manages Admins and users within assigned province; configures province settings |
| **Admin** | Barangay | Operates within a single barangay under the Super Admin's province |
| **Staff / Users** | Barangay | Invited by Admin or Super Admin; strictly isolated tenant data |

---

## 1. Country-Wide Provisioning (Initial Setup)

### Province & Barangay Seeding

All 82 PH provinces and 42,000+ barangays are seeded as lookup/reference data via:

```bash
php artisan db:seed --class=ProvinceBarangaySeeder
```

- Sources from PSGC (Philippine Standard Geographic Code) or equivalent dataset.
- Generates unique, stable slugs per province (globally unique) and per barangay (unique within province).
- Idempotent — safe to rerun without duplicating records.
- All provinces/barangays seeded with `is_active = false` by default until explicitly activated by Moderator.

### Demo Data Seeding

```bash
php artisan db:seed --class=DemoSeeder
```

- Orchestrates all seeders via a single `DemoSeeder` entry point.
- Seeds demo business data (products, inventory, suppliers, orders, movements, logs, notifications) only for configurable subset (`config('tenancy.seeder.demo_provinces')`).
- Uses chunk inserts (500–1000 rows) and factory batches for performance.
- All seeded relationships stay within the same `province_id` + `barangay_id` scope.

---

## 2. Province Onboarding (Moderator Action)

### Provisioning

1. Moderator activates province record in `provinces` (`is_active = true`).
2. Moderator activates target barangays under the province.
3. Create onboarding record (`tenant_onboarding`) with initial state `provisioning`.
4. Record onboarding in Moderator Onboarding page (`/moderator/onboarding`).

### Super Admin Assignment

1. Moderator creates a **Super Admin** account mapped to the province.
2. Assign `super_admin` role with `scope_type = 'province'`, `scope_id = {province_id}`.
3. Create `tenant_membership` record for the Super Admin.
4. Send invitation email via `tenant_invitations` with tenant-aware link: `/{provinceSlug}/accept-invite/{token}`.

### Configuration

1. Configure tenant feature flags in `tenant_features` for the province.
2. Configure quotas (default from `config/tenancy.php)`.
3. Configure subscription plan (future-ready via `tenant_subscriptions`).
4. Configure route binding overrides if needed (`tenant_route_bindings`).

---

## 3. Barangay Setup (Super Admin Action)

### Barangay Activation

1. Super Admin activates barangays under their province from the seeded dataset.
2. Each activated barangay gets a `/{provinceSlug}/{barangaySlug}` route automatically.

### Admin Assignment

1. Super Admin creates **Admin** accounts for each active barangay.
2. Assign `barangay_admin` role with `scope_type = 'barangay'`, `scope_id = {barangay_id}`.
3. Create `tenant_membership` record.
4. Send invitation via tenant URL: `/{provinceSlug}/{barangaySlug}/invite/accept/{token}`.

### Staff/User Invitation

1. Admin (or Super Admin) invites staff users via `tenant_invitations`.
2. Users accept invitation via tenant URL.
3. Verify membership + role assignment created with correct `province_id` + `barangay_id`.

---

## 4. Activation Checklist

### Per-Province

- [ ] Province record exists and `is_active = true`.
- [ ] At least one Super Admin assigned with `province_admin` / `super_admin` role.
- [ ] Super Admin can log in via `/{provinceSlug}/{firstBarangaySlug}/login` or province-level route.
- [ ] Province health check status is `healthy`: `php artisan tenant:health-check --province={id}`.
- [ ] Feature flags and quotas configured.

### Per-Barangay

- [ ] Barangay record exists with correct `province_id` and `is_active = true`.
- [ ] Slug is unique within province and URL-safe.
- [ ] Tenant login works via `/{provinceSlug}/{barangaySlug}/login`.
- [ ] Dashboard renders with tenant badge (Province / Barangay name).
- [ ] Tenant health check status is `healthy`.
- [ ] Basic module smoke tests pass:
  - Inventory CRUD
  - Patient records
  - Suppliers
  - Orders / Requests / Holds
  - Exports (scoped to tenant)
- [ ] Tenant email settings and feature flags configured under tenant settings.
- [ ] (Optional) 2FA requirement validated for sensitive tenant scopes.

### Data Isolation Verification

- [ ] User from Barangay A cannot see Barangay B data (same province).
- [ ] User from Province A cannot see Province B data.
- [ ] All queries scoped by `province_id` + `barangay_id` via `TenantScoped` trait / global scopes.
- [ ] Exports contain only data from requesting tenant scope.
- [ ] Queue jobs carry correct tenant context.

---

## 5. Seeder Integrity Verification

After any seeding operation, run:

```bash
# Verify no orphan records or null tenant keys
php artisan tenant:migration null-scan

# Validate all slugs are unique and canonical
php artisan tenant:validate-slugs

# Run full health check
php artisan tenant:health-check
```

---

## 6. Post-Onboarding Quality Cycle

```bash
# Build / Autoload
composer dump-autoload

# Clear caches
php artisan route:clear && php artisan config:clear && php artisan cache:clear

# Run tests
php vendor/bin/phpunit --stop-on-failure

# Tenant smoke tests
php artisan tenant:smoke-test
```

---

## 7. References

- `docs/TENANT_OPERATIONS_RUNBOOK.md`
- `docs/TENANT_CUTOVER_SIGNOFF_CHECKLIST.md`
- `docs/TENANT_INCIDENT_RESPONSE_SOP.md`
- `docs/SAAS_MULTI_TENANT_CONVERSION_PLAN.md`
- `docs/MULTI_TENANT_SETUP.md`
