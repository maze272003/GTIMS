# GTIMS - Government Transaction/Supply Item Management System

## Overview

GTIMS is a Laravel-based inventory and supply chain management system designed for government healthcare facilities. It manages pharmaceutical products, handles incoming requests, manages holds/pullouts, and provides analytics for reorder optimization.

## Technology Stack

- **Framework**: Laravel 11.x
- **Database**: MySQL/SQLite
- **Frontend**: Blade templates + Vanilla JavaScript
- **Authentication**: Session-based with OTP login
- **PHP Version**: 8.2+

## System Architecture

### Core Modules

#### 1. Inventory & Products Management
- **Product Catalog**: Generic name, brand name, form, strength
- **Batch-level Inventory**: Per-branch tracking with expiry dates
- **Stock Operations**: Add, edit, transfer with movement logging
- **Archive Capability**: Soft-delete for products

#### 2. Holds/Pullout Management
- Hold types: Reservation, Quarantine/Pullout, Recall
- Per-SKU and batch-level holds with partial quantities
- Status: Pending, Approved, Released, Expired
- Auto-expiry with scheduled jobs
- Reduces available quantity without affecting on-hand

#### 3. Incoming Requests Workflow
**States**: Draft → Requested → Review → Approved/Denied → Fulfill → Close

Features:
- Priority levels (Normal, High, Critical)
- Attachments and comments
- Substitution preference
- Auto-availability checking
- Reserve stock via holds on approval
- FEFO (First Expired First Out) batch allocation
- Partial fulfillment and backorder support
- Full status history tracking

#### 4. Low Stock Management
- Global configurable threshold
- Per-item threshold overrides
- Optional per-branch settings
- Available stock calculation (on-hand minus holds)
- Email notifications
- Reorder suggestions with supplier mapping and lead time

#### 5. Role-Based Access Control (RBAC)
- User levels with permissions
- Permission matrix for sensitive actions
- Middleware-based authorization
- Rate limiting on auth and sensitive endpoints

#### 6. Audit & Compliance
- Immutable audit logging
- Before/after metadata capture
- Critical actions: holds, approvals, fulfillments, settings

#### 7. Analytics & Reporting
- SLA metrics (request cycle time, approval time, fulfillment time)
- Reorder optimization analytics
- Stock KPIs
- Export capabilities (PDF, Excel)

## Database Models

### Core Entities

| Model | Description |
|-------|-------------|
| User | Authentication and user management |
| UserLevel | RBAC levels (Admin, Doctor, Nurse, etc.) |
| Permission | Granular permission definitions |
| RolePermission | Maps permissions to user levels |
| Product | Product catalog with generic/brand/form/strength |
| Inventory | Batch-level stock per branch |
| ProductMovement | IN/OUT movement logs |
| Hold | Hold/pullout records |
| HoldItem | Individual SKU holds within a hold |
| HoldStatusHistory | Hold status transitions |
| IncomingRequest | Request headers |
| RequestItem | Individual items in requests |
| RequestComment | Comments on requests |
| RequestAttachment | File attachments |
| RequestStatusHistory | Request state transitions |
| Supplier | Supplier information |
| SupplierProduct | Product-supplier mappings |
| ReorderRule | Reorder rules per product |
| LowStockSetting | Threshold configuration |
| Notification | System notifications |
| NotificationPreference | User notification settings |
| AuditEvent | Immutable audit trail |
| Branch | Organizational branches |
| Order | Order management |
| OrderItem | Order line items |
| Patientrecords | Patient medication records |
| HistoryLog | Human-readable activity logs |
| IdempotencyKey | Endpoint idempotency keys |

## User Levels

Based on UserLevel model:
1. **Super Admin** - Full system access
2. **Admin** - Administrative functions
3. **Nurse/Staff** - Inventory and patient records
4. **Doctor** - Limited to specific workflows

## Key Services

| Service | Responsibility |
|---------|----------------|
| AvailabilityService | Stock calculations, FEFO allocation, availability metrics |
| HoldService | Hold lifecycle, approval, expiry, release |
| RequestWorkflowService | Request state machine, transitions, fulfillment |
| SubstitutionService | Product equivalence, substitute suggestions |
| NotificationService | In-app and email notifications |
| AuditService | Immutable audit event recording |
| AnalyticsService | SLA metrics, reorder suggestions, KPIs |

## API Routes

### Authentication
- `POST /send-otp` - Send OTP
- `POST /verify-otp` - Verify OTP and login

### Dashboard
- `GET /dashboard` - Redirect based on permissions

### Admin Panel (`/admin`)
- `/orders` - Order management
- `/patientrecords` - Patient medication records
- `/inventory` - Inventory management
- `/product-movements` - Movement history
- `/historylog` - Activity logs

### Protected Admin Routes (Level 1, 2)
- `/holds` - Hold/pullout management
  - `GET /` - List holds
  - `POST /` - Create hold
  - `POST /{hold}/approve` - Approve hold
  - `POST /{hold}/release` - Release hold

- `/requests` - Incoming requests workflow
  - `GET /` - List requests
  - `POST /` - Create request
  - `POST /{request}/transition` - State transition
  - `POST /{request}/fulfill` - Fulfill request
  - `POST /{request}/comment` - Add comment
  - `POST /{request}/attachment` - Add attachment

- `/low-stock-settings` - Low stock configuration
- `/suppliers` - Supplier management
- `/audit` - Audit log viewing
- `/analytics` - Analytics endpoints
- `/notifications` - Notification center

### Super Admin Only
- `/manageaccount` - User account management
- `/roles` - Role/permission management

## Middleware

- `auth` - Authentication required
- `verified` - Email verified
- `level.all` - Access based on user level

## Workflows

### Stock Transfer
1. Request initiated from inventory screen
2. Batch selection with FEFO
3. Movement records created (OUT from source, IN to destination)
4. History logs updated

### Request Fulfillment
1. Request approved
2. Holds created for reserved quantity
3. On fulfillment: allocate batches (FEFO)
4. Create product movements
5. Update inventory quantities
6. Release holds

### Hold Expiry
1. Scheduled job checks expired holds
2. Releases hold quantities back to available
3. Updates hold status to expired

### Automation Builder (Workflow Engine)

The Automation Builder is a visual DAG-based workflow automation system that allows administrators to create, publish, and execute automated workflows triggered by inventory/order events.

**Architecture:**
- **WorkflowDefinition** — top-level entity with versioning, status (draft/active/disabled), concurrency limits
- **WorkflowVersion** — immutable version snapshots with graph data, nodes, edges
- **WorkflowNode** — individual steps: triggers, conditions, actions
- **WorkflowEdge** — directed connections between nodes (supports condition branches)
- **WorkflowRun** — execution record with status, retry tracking, dead-letter support
- **WorkflowRunStep** — per-node execution log with timing and output snapshots
- **WorkflowPermission** — per-workflow ACL (view, edit, run, publish, delete)

**Trigger Types:**
- `low_stock_reached` — fires when inventory drops below threshold
- `stock_received` — fires when inventory is created
- `order_approved` / `order_created` / `order_canceled` — fires on order state changes
- `expiry_in_x_days` — for scheduled expiry checks
- `daily_schedule` — CRON-based scheduled execution

**Condition Types:**
- `quantity_threshold` — numeric comparison (operators: <, >, <=, >=, ==)
- `product_category` — matches product category
- `branch_match` — matches branch
- `approval_required` — checks approval status

**Action Types:**
- `notify` — sends in-app notifications (supports specific_users, criteria-based, all_admins strategies)
- `create_hold` / `release_hold` — inventory hold management
- `create_reorder_suggestion` — calculates reorder quantity from 3-month usage average
- `auto_allocate_order` — FEFO batch allocation for orders
- `create_transfer_request` — logs transfer request audit events
- `generate_report` — generates Excel reports via Maatwebsite
- `webhook_call` — external HTTP calls with HMAC signing, SSRF protection, URL allowlist
- `log_audit_event` — writes to audit trail
- `create_google_doc` — Google Docs integration (simulated)
- `completion_gate` — checks run completion criteria

**Retry & Dead-Letter:**
- Failed runs are retried with exponential backoff (default 3 retries)
- After max retries, runs are moved to the dead-letter queue
- Dead-lettered runs can be manually re-run from the UI
- Dry runs skip retry logic

**Event-Driven Execution:**
- `InventoryWorkflowObserver` — fires triggers on inventory create/update
- `OrderWorkflowObserver` — fires triggers on order create/status change
- `WorkflowTriggerService` — matches events to active workflows and dispatches runs

**Scheduled Execution:**
- `workflows:run-scheduled` — runs every minute, checks daily_schedule triggers
- `workflows:retry-failed` — runs every 5 minutes, retries failed runs

**Queue Processing:**
- `ExecuteWorkflowRunJob` — async workflow execution on `workflows` queue
- Run: `php artisan queue:work --queue=workflows`

**Security:**
- `WorkflowDefinitionPolicy` — deny-by-default with superadmin bypass
- Route-level permission middleware (`workflows.view`, `.create`, `.edit`, `.publish`, `.run`, `.delete`)
- Per-workflow ACL via `workflow_permissions` table
- Webhook SSRF protection (blocks internal IPs/hostnames)
- Webhook HMAC-SHA256 signing with per-workflow secrets
- Input validation on all config fields (unknown fields rejected)
- Idempotent save operations

**Frontend:**
- Visual drag-and-drop editor (`workflowEditor()` Alpine.js component)
- Node palette with categorized triggers/conditions/actions
- SVG canvas with draggable nodes and connection handles
- Inspector panel for node configuration
- Version history panel with rollback support
- Dead-letter queue drawer with rerun capability
- 10-second auto-sync polling

## Security Features

- Password hashing (bcrypt)
- OTP-based login
- Role/permission matrix
- Input validation
- Rate limiting
- Idempotent endpoints
- Row-level database locking
- Immutable audit trail

## File Structure

```
GTIMS/
├── app/
│   ├── Http/Controllers/
│   │   ├── Admin/
│   │   │   ├── HoldController.php
│   │   │   ├── IncomingRequestController.php
│   │   │   ├── LowStockSettingController.php
│   │   │   ├── SupplierController.php
│   │   │   ├── RolePermissionController.php
│   │   │   ├── AuditEventController.php
│   │   │   ├── AnalyticsApiController.php
│   │   │   └── NotificationController.php
│   │   ├── AdminController/
│   │   │   ├── DashboardController.php
│   │   │   ├── InventoryController.php
│   │   │   ├── OrderController.php
│   │   │   └── ...
│   │   └── Auth/
│   │       └── OtpLoginController.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Product.php
│   │   ├── Inventory.php
│   │   ├── Hold.php
│   │   ├── IncomingRequest.php
│   │   └── ... (all entities)
│   ├── Services/
│   │   ├── AvailabilityService.php
│   │   ├── HoldService.php
│   │   ├── RequestWorkflowService.php
│   │   ├── SubstitutionService.php
│   │   └── ...
│   └── Mail/
│       ├── SendOtpMail.php
│       ├── NewUserCredentials.php
│       └── NewLoginNotification.php
├── routes/
│   ├── web.php
│   └── auth.php
├── resources/views/
│   ├── layouts/
│   └── profile/
├── public/
│   ├── js/
│   │   ├── inventory.js
│   │   ├── sidebar.js
│   │   └── login.js
│   └── css/
└── config/
```

## Configuration

Key configurations in `config/`:
- `app.php` - Application settings
- `database.php` - Database connection
- `auth.php` - Authentication config
- `mail.php` - Mail settings
- `session.php` - Session config

## Development Notes

- Uses Laravel's built-in authentication with OTP extension
- Frontend uses vanilla JavaScript with AJAX for dynamic updates
- Blade templates for server-side rendering
- CSS styling in `public/css/style.css`
- Exports available in PDF and Excel formats

## Requirements Status

All requirements from `requirements_tasks.md` have been implemented and marked complete ✅
