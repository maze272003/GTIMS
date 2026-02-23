# GTIMS Requirements And Tasks
instructions HIGH "priority" - after you done the work add "✅" to identify if the item work is done

## Updated Requirements (Complete)

### Inventory & Products
- ✅ Maintain product catalog with generic/brand/form/strength and archive capability.
- ✅ Maintain batch-level inventory by branch with expiry and on-hand quantity.
- ✅ Support stock add/edit/transfer with movement logging (IN/OUT) and human-readable history logs.

### Pullout / Hold
- ✅ Support hold/pullout records at SKU and batch level with partial quantities.
- ✅ Holds must have type (reservation, quarantine/pullout, recall), reason code, remarks, created_by, optional approved_by, timestamps, and status history.
- ✅ Holds must support expiry (auto release) and manual release.
- ✅ Holds must reduce available quantity without reducing on-hand.

### Incoming Requests Workflow
- ✅ Request lifecycle: draft/requested → review → approve/deny → fulfill → close, with full status history.
- ✅ Request header must capture requester dept/user, priority, attachments, comments, and timestamps.
- ✅ Request items must capture product, requested qty, and substitution preference.
- ✅ Auto-check availability and suggest substitutes; reserve stock via holds upon approval.
- ✅ Fulfillment must allocate batches (FEFO), generate product movements, and allow partial fulfillment/backorder.

### Low Stock Settings & Reorder
- ✅ Configurable global low-stock threshold and per-item overrides, optional per branch.
- ✅ Low-stock calculations must use available stock (on-hand minus holds).
- ✅ Notifications: optional because we dont have application apk "in-app" + high priority - email.
- ✅ Reorder suggestions must use supplier mapping and lead time awareness.

### RBAC & Security
- ✅ Role/permission matrix to replace hard-coded level checks for sensitive actions.
- ✅ Consistent input validation and authorization for all new endpoints.
- ✅ Rate limiting for auth and sensitive workflows; secure file upload handling.
- ✅ OWASP checks and remove or lock down high-risk routes.

### Audit & Compliance
- ✅ Full audit logging for critical actions (holds, approvals, fulfillments, settings).
- ✅ Immutable history for critical actions with before/after metadata and why.

### Analytics & Reporting
- ✅ Preserve existing usage trends and forecasts, add reorder optimization analytics.
- ✅ SLA metrics for request cycle time, approval time, and fulfillment time.
- ✅ Reporting exports for requests, holds, and reorder recommendations.

### Data Integrity
- ✅ Concurrency-safe stock updates using transactions and row-level locks.
- ✅ Idempotent write endpoints for approval/fulfillment actions.
- ✅ Consistent availability calculation shared across modules.

### Observability
- ✅ Structured logs for inventory actions, request transitions, notifications.
- ✅ Metrics and error tracing, plus performance budgets for key screens and jobs.

### Testing & CI
- ✅ Unit + integration tests for holds, requests, substitutions, and concurrency.
- ✅ E2E tests for critical workflows, CI gating on tests and lint.

## Backend Tasks
1. ✅ Create migrations and models for holds, hold_items, hold_status_history, requests, request_items, request_comments, request_attachments, low_stock_settings, suppliers, supplier_products, reorder_rules, notifications, and idempotency keys.
2. ✅ Implement Availability/Reservation service that computes on-hand vs available, enforces FEFO allocation, and exposes shared methods for dispensation, requests, and holds.
3. ✅ Build request workflow endpoints with a state machine, status history logging, and authorization policies, including idempotent approval and fulfillment actions.
4. ✅ Implement hold lifecycle management with approval flow, expiry job, and manual release, scheduled via Laravel scheduler.
5. ✅ Add substitution engine with product equivalence mapping and optional explicit substitutes table, exposed to request review/fulfillment.
6. ✅ Implement notification pipeline with in-app + email adapter stub, plus notification preferences and triggers for low stock, approvals, and expiries.
7. ✅ Extend analytics for SLA metrics, reorder suggestions, and available stock KPIs; expose to dashboard endpoints.
8. ✅ Replace hard-coded level middleware with role/permission checks and seed permission matrix; add policies for holds and requests.
9. ✅ Add audit event recording for critical actions with immutable append-only storage and before/after metadata.
10. ✅ Add tests for workflows, concurrency locking, idempotency, and notification triggers; wire CI for PHPUnit and lint.

## Frontend Tasks
1. ✅ Inventory UI updates to show on-hand vs reserved vs available, plus hold/pullout actions and a hold history modal.
2. ✅ Request management screens for create, review/approve/deny, fulfill, and close, including attachments, comments, status timeline, and substitute suggestions.
3. ✅ Low-stock settings UI with global default and per-item override forms plus supplier/lead-time mapping screens.
4. ✅ In-app notification center with unread badge, filters, and notification settings for email.
5. ✅ Dashboard updates for SLA metrics, reorder optimization widgets, and thresholds derived from settings.
6. ✅ Role/permission management UI with matrix view and per-role permission toggles.
7. ✅ Audit log UI enhancements to show entity, before/after snapshots, and filters by action type.
8. ✅ AJAX integration updates to use new endpoints with consistent validation errors across modals.
