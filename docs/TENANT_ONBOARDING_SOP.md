# GTIMS Tenant Onboarding SOP

## Provisioning

1. Create province record (`provinces`).
2. Create barangay record (`barangays`) linked to province.
3. Create onboarding record (`tenant_onboarding`) with initial state.

## Configuration

1. Configure tenant feature flags in `tenant_features`.
2. Configure quotas (default from `config/tenancy.php`).
3. Configure route binding overrides if needed (`tenant_route_bindings`).

## Access Setup

1. Invite initial tenant admin (`tenant_invitations`).
2. Accept invitation via tenant URL.
3. Verify membership + role assignment created.

## Activation Checklist

1. Tenant login works via `/{provinceSlug}/{barangaySlug}/login`.
2. Dashboard renders with tenant badge.
3. Tenant health check status is `healthy`.
4. Basic module smoke tests pass (inventory, requests, suppliers).
