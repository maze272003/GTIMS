# GTIMS Codebase Documentation

This document describes the current GTIMS codebase structure, core modules, and runtime behavior based on the existing Laravel application.

## Purpose and Scope

GTIMS (Government Transaction/Supply Item Management System) is a Laravel-based inventory and supply chain system for government healthcare facilities. It manages product catalogs, batch inventory, holds and pullouts, incoming requests, orders, patient records, and audit-compliant activity logs.

## Technology Stack

- Backend: Laravel 12.x with PHP 8.2+
- Frontend: Blade templates with vanilla JavaScript
- Build tooling: Vite + Tailwind CSS + Alpine.js
- Database: MySQL or SQLite
- Reporting: Dompdf and Excel exports

Key dependency references:
- `composer.json`
- `package.json`

## Quick Start

Use the built-in scripts for a standard setup and local development.

1. Initial setup

```bash
composer run setup
```

2. Local development (app server, queue, Vite)

```bash
composer run dev
```

3. Run tests

```bash
composer run test
```

## High-Level Architecture

The system is a traditional Laravel MVC app using controllers for HTTP workflows, Eloquent models for persistence, and service classes to encapsulate domain logic. Most user-facing functionality is under a shared `/admin` namespace with role-based access control.

Core domains include:
- Inventory and batch-level stock management
- Holds and pullouts with approval and release workflows
- Incoming requests with state transitions and fulfillment
- Low stock thresholds and reorder analytics
- Orders, patient records, and activity logs

Reference overview: `SYSTEM_DOCUMENTATION.md`

## Directory Layout

Key paths in this repository:

- `app/Http/Controllers/` HTTP controllers
- `app/Models/` Eloquent data models
- `app/Services/` Domain services for workflow logic
- `routes/` Route definitions
- `resources/views/` Blade templates
- `public/` Public assets (compiled JS/CSS)
- `database/` Migrations, factories, and seeders
- `config/` Application configuration

## Routing and Workflows

Primary routing is defined in `routes/web.php`, `routes/auth.php`, and `routes/db.php`.

### Authentication

- OTP login endpoints:
  - `POST /send-otp`
  - `POST /verify-otp`
- Standard Breeze auth flows in `routes/auth.php` (register, login, password reset, email verification)

### Admin Module (Shared Panel)

Base admin routes are under `/admin` and protected by `auth`, `verified`, and `level.all` middleware.

Major areas include:
- Dashboard: `/admin/dashboard`
- Orders: `/admin/orders`
- Inventory: `/admin/inventory`
- Product movements: `/admin/product-movements`
- Holds: `/admin/holds`
- Requests: `/admin/requests`
- Low stock settings: `/admin/low-stock-settings`
- Suppliers: `/admin/suppliers`
- Audit logs: `/admin/audit`
- Analytics endpoints: `/admin/analytics/*`
- Notifications: `/admin/notifications`
- User and role management: `/admin/manageaccount`, `/admin/roles`

See `routes/web.php` for the full list and exact HTTP methods.

### Dangerous Maintenance Endpoint

`routes/db.php` exposes a database reset route:

- `GET /dangerous-db-reset?key=resetdb`

This runs destructive commands like `migrate:fresh --seed`. It is guarded by a query key and should be protected or removed in production.

## Controllers

Controllers are organized into two main namespaces:

- `app/Http/Controllers/AdminController/` for dashboard, inventory, orders, and patient records
- `app/Http/Controllers/Admin/` for workflow-heavy modules (holds, requests, low stock, suppliers, audit, analytics, notifications)

Authentication controllers live in `app/Http/Controllers/Auth/` and include OTP-specific logic in `OtpLoginController`.

## Domain Services

Service classes encapsulate business rules and shared logic:

- `app/Services/AvailabilityService.php`
- `app/Services/HoldService.php`
- `app/Services/RequestWorkflowService.php`
- `app/Services/SubstitutionService.php`
- `app/Services/NotificationService.php`
- `app/Services/AuditService.php`
- `app/Services/AnalyticsService.php`

These services are used for FEFO allocation, hold lifecycle management, request state transitions, notifications, auditing, and analytics calculations.

## Data Models

Core models live in `app/Models/` and include:

- `Product`, `Inventory`, `ProductMovement`
- `Hold`, `HoldItem`, `HoldStatusHistory`
- `IncomingRequest`, `RequestItem`, `RequestComment`, `RequestAttachment`, `RequestStatusHistory`
- `Supplier`, `SupplierProduct`, `ReorderRule`, `LowStockSetting`
- `Order`, `OrderItem`, `Patientrecords`, `Dispensedmedication`
- `User`, `UserLevel`, `Permission`, `RolePermission`
- `AuditEvent`, `HistoryLog`, `Notification`, `NotificationPreference`
- `Branch`, `Barangay`, `IdempotencyKey`

## Scheduled Jobs and Console Commands

`routes/console.php` defines commands and scheduling:

- `holds:expire` runs hourly to expire holds via `HoldService`
- `jm` starts the built-in PHP server

## Frontend Assets

Vite is used for asset bundling. Tailwind CSS and Alpine.js are included.

Key build config files:

- `vite.config.js`
- `tailwind.config.js`
- `postcss.config.js`

## Testing

Tests run through `php artisan test` via the composer `test` script.

## Environment Notes

Configure environment variables in `.env` for database, mail, and queue settings. The `setup` script copies `.env.example`, generates an app key, and migrates the database.
