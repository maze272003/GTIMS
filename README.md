<p align="center">
    <img src="/images/gtlogo.png" alt="GTIMS Logo" width="200">
</p>

<h1 align="center">GTIMS</h1>

<p align="center">
    <strong>Government Transaction/Supply Item Management System</strong>
</p>

<p align="center">
    A comprehensive Laravel-based inventory and supply chain management system designed for government healthcare facilities.
</p>

---

## 📋 Overview

GTIMS is a robust inventory management system built with Laravel 12.x that manages pharmaceutical products, handles incoming requests, manages holds/pullouts, and provides analytics for reorder optimization. It is specifically designed for government healthcare facilities to ensure efficient supply chain management with full audit compliance.

### Key Capabilities

- **Inventory Management**: Batch-level stock tracking with expiry dates per branch
- **Request Workflow**: Complete lifecycle from draft to fulfillment with state transitions
- **Hold/Pullout Management**: Reservation, quarantine, and recall management
- **Low Stock Alerts**: Configurable thresholds with email notifications
- **Analytics & Reporting**: SLA metrics, reorder optimization, and KPI dashboards
- **Role-Based Access Control**: Granular permissions for different user levels
- **Audit Compliance**: Immutable audit trail for all critical actions

---

## 🛠️ Technology Stack

| Component | Technology |
|-----------|------------|
| **Backend** | Laravel 12.x |
| **PHP Version** | 8.2+ |
| **Database** | MySQL / SQLite |
| **Frontend** | Blade Templates + Vanilla JavaScript Tool** | V |
| **Buildite |
| **CSS Framework** | Tailwind CSS |
| **JS Interactivity** | Alpine.js |
| **PDF Generation** | Dompdf |
| **Excel Export** | Maatwebsite Excel |

---

## 📦 Installation

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js 18+ and npm
- MySQL or SQLite database

### Quick Setup

Run the following commands to set up the project:

```bash
# Install PHP dependencies
composer install

# Copy environment file and generate application key
cp .env.example .env
php artisan key:generate

# Run database migrations
php artisan migrate

# Install frontend dependencies
npm install

# Build frontend assets
npm run build
```

### Using the Setup Script

The project includes a convenient setup script:

```bash
composer run setup
```

This will:
1. Install PHP dependencies
2. Copy `.env.example` to `.env`
3. Generate application key
4. Run migrations
5. Install npm packages
6. Build frontend assets

---

## 🚀 Development Server

### Start Development Environment

```bash
# Run all services concurrently (server, queue, Vite)
composer run dev
```

This starts:
- PHP development server on `http://localhost:8000`
- Queue worker for background jobs
- Vite hot reload for frontend changes

### Individual Services

```bash
# Start PHP server only
php artisan serve

# Run queue worker
php artisan queue:listen --tries=1

# Start Vite dev server
npm run dev
```

---

## 📖 Usage Examples

### Authentication

GTIMS uses OTP (One-Time Password) login for enhanced security:

```bash
# Send OTP to user's email
POST /send-otp
Body: { "email": "user@example.com" }

# Verify OTP and login
POST /verify-otp
Body: { "email": "user@example.com", "otp": "123456" }
```

### Inventory Management

Access the admin panel at `/admin/dashboard` after authentication.

#### Product Catalog
- Add products with generic name, brand name, form, and strength
- Products can be archived (soft-deleted)

#### Stock Operations
- Add, edit, and transfer inventory
- Batch-level tracking with expiry dates
- Movement logging (IN/OUT)

### Request Workflow

Requests follow this state machine:

```
Draft → Requested → Review → Approved/Denied → Fulfill → Close
```

Features include:
- Priority levels (Normal, High, Critical)
- Attachments and comments
- Substitution preferences
- Auto-availability checking
- FEFO (First Expired First Out) batch allocation
- Partial fulfillment support

### Hold Management

Hold types:
- **Reservation**: Stock reservations for pending requests
- **Quarantine/Pullout**: Quality holds
- **Recall**: Product recalls

Status flow:
```
Pending → Approved → Released/Expired
```

Auto-expiry via scheduled jobs.

---

## 🔐 Security Features

- **Password Hashing**: bcrypt for secure password storage
- **OTP Authentication**: Time-based one-time passwords
- **Role-Based Access Control**: Permission matrix for sensitive actions
- **Input Validation**: Request validation on all endpoints
- **Rate Limiting**: Protection against brute-force attacks
- **Idempotent Endpoints**: Prevents duplicate operations
- **Row-Level Locking**: Database-level concurrency control
- **Immutable Audit Trail**: All critical actions logged

---

## 📂 Project Structure

```
GTIMS/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # Workflow controllers
│   │   │   ├── AdminController/ # Dashboard controllers
│   │   │   └── Auth/           # Authentication
│   │   └── Middleware/         # Auth & permission middleware
│   ├── Models/                 # Eloquent models
│   ├── Services/               # Business logic
│   ├── Repositories/           # Data access layer
│   ├── Mail/                   # Email classes
│   └── Notifications/          # Notification classes
├── resources/
│   └── views/                  # Blade templates
├── routes/
│   ├── web.php                 # Main routes
│   └── auth.php                # Auth routes
├── public/
│   ├── js/                     # JavaScript files
│   ├── css/                    # Stylesheets
│   └── images/                 # Images & logos
├── database/
│   ├── migrations/            # Database migrations
│   ├── factories/              # Model factories
│   └── seeders/                # Database seeders
└── config/                     # Configuration files
```

---

## 🧪 Testing

Run the test suite:

```bash
composer run test
```

---

## 📄 API Routes

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/send-otp` | Send OTP to email |
| POST | `/verify-otp` | Verify OTP and login |

### Dashboard
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard` | Redirect to role-based dashboard |

### Admin Panel (`/admin`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard` | Admin dashboard |
| GET/POST | `/orders` | Order management |
| GET/POST | `/inventory` | Inventory management |
| GET | `/product-movements` | Movement history |
| GET/POST | `/holds` | Hold/pullout management |
| GET/POST | `/requests` | Incoming requests |
| GET/POST | `/low-stock-settings` | Low stock configuration |
| GET/POST | `/suppliers` | Supplier management |
| GET | `/audit` | Audit log viewing |
| GET | `/analytics` | Analytics endpoints |
| GET | `/notifications` | Notification center |
| GET/POST | `/manageaccount` | User account management (Super Admin) |
| GET/POST | `/roles` | Role/permission management (Super Admin) |

---

## 👥 User Levels

| Level | Role | Access |
|-------|------|--------|
| 1 | Super Admin | Full system access |
| 2 | Admin | Administrative functions |
| 3 | Nurse/Staff | Inventory and patient records |
| 4 | Doctor | Limited to specific workflows |

---

## 📱 Key Services

| Service | Responsibility |
|---------|----------------|
| [`AvailabilityService`](app/Services/AvailabilityService.php) | Stock calculations, FEFO allocation |
| [`HoldService`](app/Services/HoldService.php) | Hold lifecycle, approval, expiry |
| [`RequestWorkflowService`](app/Services/RequestWorkflowService.php) | Request state machine, transitions |
| [`SubstitutionService`](app/Services/SubstitutionService.php) | Product equivalence suggestions |
| [`NotificationService`](app/Services/NotificationService.php) | In-app and email notifications |
| [`AuditService`](app/Services/AuditService.php) | Immutable audit event recording |
| [`AnalyticsService`](app/Services/AnalyticsService.php) | SLA metrics, reorder suggestions |

---

## 📝 License

The GTIMS project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

<p align="center">
    <strong>Built with ❤️ using Laravel</strong>
</p>
