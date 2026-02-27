# GTIMS Tenant Incident Response SOP

## 1. Detection

1. Monitor `security.log` for cross-tenant access attempts.
2. Monitor failed jobs for tenant-context errors.
3. Open an incident record in `tenant_incidents`.

## 2. Containment

1. Suspend affected tenant scope if needed (`tenant_suspensions`).
2. Revoke impacted user sessions.
3. Disable risky feature flags via kill switches.

## 3. Investigation

1. Correlate request ID, tenant IDs/slugs, user ID, and route.
2. Review audit trail and history logs for affected records.
3. Validate no export leakage occurred.

## 4. Resolution

1. Patch scope validation/policy gaps.
2. Re-run leakage regression tests.
3. Restore tenant access and document mitigation.

## 5. Post-Mortem

1. Record root cause and corrective actions.
2. Update runbooks/tests to prevent recurrence.
3. Close incident with timeline and approvals.
