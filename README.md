<div align="center">

# 🏛️ e-Karamchari

### Employee Self-Service & HR Management Portal

**A PHP + MySQL employee management platform with role-based access, attendance, leave, grievances, payroll, service records, notifications and TOTP-based 2FA.**

[Features](#-features) • [Architecture](#-architecture) • [Docker](#-docker-quick-start) • [Database](#-database) • [API](#-api-reference) • [Security](#-security) • [Roadmap](#-issue-tracker)

---

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Apache](https://img.shields.io/badge/Apache-2.4+-D22128?style=for-the-badge&logo=apache&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![Status](https://img.shields.io/badge/Status-Active-success?style=for-the-badge)

</div>

---

## 📌 Overview

**e-Karamchari** is an Employee Self-Service / HR Management System designed around a government-organization workflow. It provides separate employee and administrative portals backed by a PHP REST-style API and MySQL database.

The application is organized into:

- **Employee Portal** — self-service access to attendance, leaves, grievances, salary slips, service records and profile information.
- **Admin Portal** — employee management, approvals, reports, payroll, holiday management, settings and administrative workflows.
- **Security Layer** — sessions, role-based access control, password hashing, 2FA, CSRF support, rate limiting and audit logging.
- **Notification Layer** — in-app notifications for important employee/admin actions.
- **Docker Environment** — reproducible PHP/Apache + MySQL local deployment for development and demonstrations.

> **Implementation note:** The canonical repository schema currently uses the `users` table for employee accounts. Older local databases may use a different table name/schema and should be migrated rather than mixed with the current application code. See [Issue #23](https://github.com/vishwas0229/e-Karamchari/issues/23).

---

## ✨ Features

### 👨‍💼 Employee Portal

| Module | Functionality |
|---|---|
| 📊 Dashboard | Personal attendance, leave and account overview |
| ⏰ Attendance | View attendance and perform check-in/check-out |
| 📝 Leave | Apply, view and cancel leave requests |
| 🎫 Grievances | Submit grievances and track their status |
| 💰 Salary | View monthly salary slips |
| 📁 Service Record | View employment/service history |
| 👤 Profile | Update profile and change password |
| 🔐 2FA | TOTP setup, verification, disable and backup-code management |
| 🔔 Notifications | View and mark notifications as read |

### 👨‍💻 Admin / Officer Portal

| Module | Functionality |
|---|---|
| 📈 Dashboard | Organization-level statistics and activity |
| 👥 Employees | List, create, update and manage employees |
| ✅ Leave Approvals | Review and approve/reject employee leave |
| 🎫 Grievances | Review, assign and resolve grievances |
| ⏰ Attendance | Attendance listing, marking and reporting |
| 💰 Salary | Generate, update, list and manage salary slips |
| 📁 Service Records | Manage employee service records |
| 📊 Reports | Overview, monthly, attendance, leave, grievance, employee and department reports |
| 📅 Holidays | List, create, update, delete and generate holidays |
| ⚙️ Settings | Departments, designations, leave types, holidays, activity logs and sessions |
| 🔐 2FA | Administrative account protection |
| 🔔 Notifications | Administrative notification workflow |

---

## 🏗️ Architecture

```text
                         ┌─────────────────────────┐
                         │        Browser          │
                         │ Employee / Admin Portal  │
                         └────────────┬────────────┘
                                      │ HTTP/HTTPS
                                      ▼
                         ┌─────────────────────────┐
                         │     Apache + PHP 8.2    │
                         │       e-Karamchari      │
                         │                         │
                         │  Frontend HTML/CSS/JS   │
                         │  Backend REST-style API │
                         │  Auth / RBAC / 2FA      │
                         └────────────┬────────────┘
                                      │ PDO / MySQL
                                      ▼
                         ┌─────────────────────────┐
                         │       MySQL 8.0         │
                         │      ekaramchari DB     │
                         └─────────────────────────┘
```

### Application layers

1. **Frontend** — static HTML, CSS and JavaScript under `frontend/`.
2. **API** — PHP endpoints under `backend/api/`.
3. **Middleware** — authentication, sessions and notifications.
4. **Configuration** — database, security, CORS and application settings.
5. **Database** — normalized MySQL schema under `database/schema.sql`.
6. **Runtime storage** — logs and uploads are mounted separately in Docker and ignored by Git.

---

## 🛠️ Technology Stack

| Technology | Current project usage |
|---|---|
| PHP | 8.2 in the Docker image |
| Apache | 2.4+ via `php:8.2-apache` |
| MySQL | 8.0 in Docker |
| HTML5 | Frontend pages |
| CSS3 | Shared and module-specific styling |
| JavaScript | ES6+ frontend/API client |
| PDO | MySQL database access |
| Docker | Local/reproducible deployment |
| Docker Compose | Application + database orchestration |
| TOTP | Two-factor authentication |
| Git/GitHub | Source control and issue/PR workflow |

---

# 📚 Project Documentation

- [Roadmap](docs/ROADMAP.md)
- [System architecture](docs/Architecture.md)
- [Software requirements specification](docs/SRS.md)
- [Data-flow diagrams](docs/DFD.md)
- [Entity-relationship model](docs/ER-Diagram.md)
- [Use-case model](docs/Use-Case.md)
- [Database data dictionary](docs/Data-Dictionary.md)
- [Security architecture](docs/Security-Architecture.md)
- [Testing strategy](docs/Testing.md)
- [UI responsive/accessibility QA](docs/ui-qa.md)
- [Phase 1 audit record](docs/phase1-audit.md)
- [Deployment guide](docs/deployment.md)
- [API reference](docs/api-reference.md)
- [Backup and recovery](docs/backup-recovery.md)
- [Project report](docs/Project-Report.md)
- [Presentation and viva preparation](docs/Presentation-Viva.md)

# ⚙️ Configuration

The main configuration is in:

```text
backend/config/config.php
backend/config/database.php
```

### Important environment variables

| Variable | Purpose | Development example |
|---|---|---|
| `DB_HOST` | MySQL hostname | `db` |
| `DB_NAME` | Database name | `ekaramchari` |
| `DB_USER` | Database user | `ekaramchari` |
| `DB_PASS` | Database password | local secret |
| `APP_URL` | Application/frontend origin used by backend configuration | `http://localhost:8080` |
| `SESSION_SAMESITE` | Intended session cookie SameSite policy | `Lax` |

For a separated frontend/backend deployment, the frontend can use:

```javascript
window.EKARAMCHARI_API_BASE_URL
```

to point API requests at the backend deployment.

For cross-site authenticated deployments, use HTTPS and configure the backend cookie/CORS settings deliberately. See [Issue #26](https://github.com/vishwas0229/e-Karamchari/issues/26) and [Issue #33](https://github.com/vishwas0229/e-Karamchari/issues/33).

---

# 📁 Project Structure

```text
e-Karamchari/
├── .dockerignore
├── .gitignore
├── Dockerfile
├── compose.yaml
├── LICENSE
├── README.md
├── index.html
│
├── backend/
│   ├── api/
│   │   ├── attendance.php
│   │   ├── auth.php
│   │   ├── dashboard.php
│   │   ├── employees.php
│   │   ├── grievances.php
│   │   ├── holidays.php
│   │   ├── leaves.php
│   │   ├── reports.php
│   │   ├── salary.php
│   │   ├── service-records.php
│   │   ├── settings.php
│   │   └── two-factor.php
│   ├── config/
│   ├── middleware/
│   ├── logs/
│   └── uploads/
│
├── database/
│   └── schema.sql
│
└── frontend/
    ├── admin-login.html
    ├── employee-login.html
    ├── index.html
    ├── session-expired.html
    ├── terms.html
    ├── unauthorized.html
    ├── admin/
    ├── employee/
    ├── css/
    ├── js/
    └── chatbot/
```

Runtime logs and uploaded files should remain outside Git.

---

# 🗄️ Database

The repository schema is defined in:

```text
database/schema.sql
```

The canonical employee account table is `users`. If an older deployment still uses an `employees` table, do not mix it with the current application code. Follow [the legacy migration guide](database/migrations/002_legacy_employees_migration.md) and keep a database backup before migration.

### Core tables

| Table | Purpose |
|---|---|
| `users` | Employee/user accounts and profile data |
| `roles` | SUPER_ADMIN, ADMIN, OFFICER, EMPLOYEE roles |
| `departments` | Organization departments |
| `designations` | Job titles and grade pay |
| `leave_types` | Leave categories |
| `leave_requests` | Employee leave applications |
| `leave_balance` | Yearly leave allocation/usage |
| `grievance_categories` | Grievance classifications |
| `grievances` | Employee grievances |
| `grievance_comments` | Grievance discussion/history |
| `attendance` | Daily attendance |
| `service_records` | Promotion, transfer, training and service history |
| `salary_slips` | Monthly salary/payroll records |
| `holidays` | Holiday calendar |
| `activity_logs` | Audit trail |
| `two_factor_auth` | TOTP configuration and backup codes |
| `sessions` | Server-side authenticated sessions |
| `notifications` | In-app notifications |
| `system_settings` | Configurable application settings |

### Database relationships

```text
roles ───────────────┐
departments ─────────┤
designations ────────┤
                     ▼
                   users
                     │
       ┌─────────────┼─────────────────┐
       ▼             ▼                 ▼
 attendance     leave_requests    grievances
       │             │                 │
       │             ▼                 ▼
       │        leave_balance   grievance_comments
       │
       ├──────── salary_slips
       ├──────── service_records
       ├──────── notifications
       ├──────── sessions
       ├──────── two_factor_auth
       └──────── activity_logs
```

---

# 👥 Role-Based Access

| Role | General access |
|---|---|
| **SUPER_ADMIN** | Full system access |
| **ADMIN** | Administrative employee/approval/report workflows |
| **OFFICER** | Department-oriented approval/report workflows |
| **EMPLOYEE** | Self-service functionality |

Role authorization is enforced server-side through the authentication middleware. Frontend visibility should not be treated as the security boundary.

---

# 🔌 API Reference

The backend exposes action-based PHP API endpoints under:

```text
/backend/api/
```

Most requests use the pattern:

```text
/api/<file>.php?action=<action>
```

## Authentication — `auth.php`

| Action | Purpose |
|---|---|
| `login` | Employee authentication |
| `admin-login` | Admin/Officer/Super Admin authentication |
| `logout` | End current session |
| `check` | Check current session |
| `csrf` | Obtain CSRF token |
| `user` | Get current user |

## Dashboard — `dashboard.php`

`admin-stats`, `employee-stats`, `notifications`, `mark-read`, `recent-activity`, `test-notification`

## Employees — `employees.php`

`list`, `get`, `create`, `update`, `delete`, `profile`, `update-profile`, `change-password`, `departments`, `designations`, `stats`

## Leaves — `leaves.php`

`list`, `get`, `my-leaves`, `apply`, `approve`, `reject`, `cancel`, `balance`, `types`, `stats`, `pending-count`

## Grievances — `grievances.php`

`list`, `my-grievances`, `get`, `submit`, `update-status`, `assign`, `resolve`, `add-comment`, `categories`, `stats`, `pending-count`

## Attendance — `attendance.php`

`list`, `my-attendance`, `check-in`, `check-out`, `admin-checkout`, `today`, `mark`, `report`, `summary`, `stats`, `holidays`, `auto-mark`, `finalize-day`

## Salary — `salary.php`

`my-slips`, `view`, `list`, `generate`, `update`, `delete`, `bulk-generate`

## Service Records — `service-records.php`

`list`, `my-records`, `get`, `create`, `update`, `delete`, `types`

## Reports — `reports.php`

`overview`, `monthly`, `attendance`, `leaves`, `grievances`, `employees`, `department`

## Settings — `settings.php`

`list`, `get`, `update`, `departments`, `designations`, `holidays`, `leave-types`, `activity-logs`, `sessions`, `terminate-session`, `reset-password`, `unlock-account`

## Holidays — `holidays.php`

`list`, `monthly`, `upcoming`, `templates`, `generate`, `create`, `update`, `delete`, `update-template`

## Two-Factor Authentication — `two-factor.php`

`status`, `setup`, `verify-setup`, `verify`, `disable`, `backup-codes`, `regenerate-backup`

> API documentation is intentionally listed at action level here. Full request/response schemas are tracked in [Issue #34](https://github.com/vishwas0229/e-Karamchari/issues/34).

---

# 🔐 Security

Current security-related mechanisms include:

- Password hashing using PHP password hashing APIs.
- Server-side role checks.
- Session-backed authentication.
- Database-backed session tokens.
- Session ID regeneration.
- CSRF token generation/verification support.
- Authentication rate limiting.
- Temporary failed-login lockout.
- TOTP-based 2FA.
- Backup codes for 2FA.
- Prepared database queries through the application database layer.
- Security response headers.
- Content Security Policy.
- Activity/audit logging.
- Runtime secrets excluded through `.gitignore`.

### CSP hardening

The API security headers now add `base-uri 'self'`, `object-src 'none'`, `frame-ancestors 'none'`, and `form-action 'self'`. The unnecessary `script-src 'unsafe-inline'` directive has been removed. `style-src 'unsafe-inline'` remains temporarily because the public landing page currently contains inline CSS and uses Tailwind's browser Play CDN; Tailwind documents the Play CDN as a development-oriented, browser-runtime approach rather than the recommended production build workflow. Official reference: https://tailwindcss.com/docs/installation/play-cdn

### Security work still tracked

The security implementation is not considered a substitute for a production security review. Open security hardening work includes:

- CSP hardening — [Issue #36](https://github.com/vishwas0229/e-Karamchari/issues/36)

---

# 🔐 Two-Factor Authentication

The project supports TOTP-based 2FA.

### Typical setup

1. Login to the appropriate portal.
2. Open the Profile page.
3. Start 2FA setup.
4. Scan the generated QR code with an authenticator application.
5. Verify the generated 6-digit code.
6. Store backup codes securely.

### Important

Backup codes and TOTP secrets are sensitive credentials. Do not commit them to Git, screenshots, issue descriptions or public documentation.

---

# 🔔 Notification System

Notifications are stored in the `notifications` table and surfaced through the dashboard.

Typical workflows include:

- Leave application → administrative notification.
- Grievance submission → administrative notification.
- Leave status change → employee notification.
- Service-record changes → relevant-user notification.

Notification helpers are implemented under:

```text
backend/middleware/notifications.php
```

### Database troubleshooting

```sql
SELECT * FROM notifications
ORDER BY created_at DESC
LIMIT 10;

SELECT user_id, COUNT(*) AS notification_count
FROM notifications
GROUP BY user_id;

SELECT COUNT(*) AS unread_count
FROM notifications
WHERE is_read = 0;
```

---

# 📅 Holiday Management

Holiday records are stored in:

```text
holidays
```

The API supports:

- Year-wise listing
- Month-wise listing
- Upcoming holidays
- Create/update/delete
- Year generation
- Template-based generation when template support is available

### Current work

The default generator now uses the official 2026 GNCTD general-holiday schedule, including the separately notified 11 September 2026 BRICS Summit holiday. Year-specific floating/restricted holidays should continue to be managed through the holiday templates/admin workflow. The official 2026 notification declares 18 general holidays and a separate restricted-holiday schedule. Official source: https://dkvib.delhi.gov.in/sites/default/files/DKVIB/circulars-orders/govtholidays2026.pdf

---

# 🌐 Deployment Options

## 1. Local Docker

Best for development and demonstrations:

```text
Browser → localhost:8080 → Docker PHP/Apache → Docker MySQL
```

## 2. Same LAN

If Docker is running on a machine reachable from the local network:

```text
http://<LAN-IP>:8080/frontend/
```

The host computer and client device must be able to reach each other and the firewall must permit the application port.

## 3. Temporary tunnel

For a short demo, a tunnel can expose the local HTTP application:

```text
Internet
   ↓
Tunnel provider
   ↓
localhost:8080
   ↓
Docker application
```

Do not treat a temporary tunnel as a production deployment.

## 4. VPS / production server

Recommended production architecture:

```text
HTTPS Domain
     ↓
Reverse Proxy / Tunnel
     ↓
PHP + Apache Container
     ↓
Private Docker Network
     ↓
MySQL Container
```

MySQL should remain private to the application network.

See [Issue #30](https://github.com/vishwas0229/e-Karamchari/issues/30) and [Issue #33](https://github.com/vishwas0229/e-Karamchari/issues/33).

---

# 🧪 Testing

The project currently relies heavily on manual and static verification. A complete automated integration suite is planned.

### Basic smoke test

After startup:

```bash
docker compose ps
curl -I http://localhost:8080/frontend/css/common.css
curl -I http://localhost:8080/frontend/css/auth.css
```

Expected result for static CSS:

```text
HTTP/1.1 200 OK
Content-Type: text/css
```

Then verify:

- Main page loads.
- Admin login loads with CSS/JS.
- Employee login loads with CSS/JS.
- API requests do not return 404.
- Login/session flow works.
- Database records are created correctly.

Automated coverage is tracked in [Issue #32](https://github.com/vishwas0229/e-Karamchari/issues/32).

---

# 🐛 Troubleshooting

## CSS or JavaScript is not loading

First test the actual HTTP response:

```bash
curl -I http://localhost:8080/frontend/css/common.css
curl -I http://localhost:8080/frontend/css/auth.css
```

If these return 404:

```bash
docker compose down
docker compose build --no-cache
docker compose up -d
```

Then inspect the container:

```bash
docker exec ekaramchari-app ls -lah /var/www/html/frontend/css/
```

Also check the browser **DevTools → Network** tab for failed asset requests.

Tracked in [Issue #31](https://github.com/vishwas0229/e-Karamchari/issues/31).

## Database connection error

Check:

```bash
docker compose ps
docker compose logs db
docker compose logs app
```

For Docker, the application database host is:

```text
db
```

not `localhost`.

## Database schema does not match the application

Check:

```sql
SHOW TABLES;
DESCRIBE users;
```

The repository backend currently expects `users`. If your database contains `employees` instead, do not randomly rename tables; use the migration/schema strategy tracked in [Issue #23](https://github.com/vishwas0229/e-Karamchari/issues/23).

## Login/session problems

Check:

- Browser cookies.
- `APP_URL`.
- HTTPS/Secure cookie configuration.
- `SESSION_SAMESITE`.
- Server-side `sessions` records.
- PHP session logs.
- Browser Network responses from `auth.php`.

## MySQL schema changes are not appearing

Remember that `database/schema.sql` is used to initialize a new MySQL volume. Existing Docker volumes are not automatically recreated.

For a disposable development database:

```bash
docker compose down -v
docker compose up -d --build
```

---

# 🧾 Logs

Runtime logs are stored under:

```text
backend/logs/
```

Docker mounts this directory from the host so logs survive container recreation.

Do not commit runtime logs, session/lockout files, uploads, or environment secrets.

---

# 💾 Backup & Recovery

Production deployments should back up:

1. MySQL database.
2. Uploaded files.
3. Relevant configuration/secrets using a secure secret-management process.

A backup is only useful if it can be restored.

The formal recovery procedure is tracked in [Issue #35](https://github.com/vishwas0229/e-Karamchari/issues/35).

---

# 🤝 Development Workflow

Use a dedicated branch for normal changes:

```bash
git switch -c feature/your-feature
```

or:

```bash
git switch -c fix/your-fix
```

Then:

```bash
git add .
git commit -m "feat: describe the change"
git push -u origin feature/your-feature
```

Open a Pull Request into `main`.

### Development rules

- Do not commit passwords, API keys or production secrets.
- Do not commit runtime logs or uploads.
- Avoid direct commits to `main` for application changes.
- Keep database migrations compatible with deployed data.
- Update documentation when behavior or deployment changes.
- Add or update tests for security-sensitive changes.

---

# 📋 Issue Tracker

The repository maintains issues for bugs, security hardening, documentation, deployment, testing and future improvements.

### Existing project issues

- [#1 — Project Roadmap](https://github.com/vishwas0229/e-Karamchari/issues/1)
- [#2 — Phase 1 Audit & Stabilization](https://github.com/vishwas0229/e-Karamchari/issues/2)
- [#3 — Project Documentation](https://github.com/vishwas0229/e-Karamchari/issues/3)
- [#5 — Consolidate Authentication Flow](https://github.com/vishwas0229/e-Karamchari/issues/5)
- [#6 — CSRF Protection Audit](https://github.com/vishwas0229/e-Karamchari/issues/6)
- [#7 — Rate Limiting Audit](https://github.com/vishwas0229/e-Karamchari/issues/7)

### Current backlog generated from the repository audit

- [#23 — Database schema compatibility](https://github.com/vishwas0229/e-Karamchari/issues/23)
- [#24 — 2026 Delhi holiday data](https://github.com/vishwas0229/e-Karamchari/issues/24)
- [#25 — Holiday templates](https://github.com/vishwas0229/e-Karamchari/issues/25)
- [#26 — Configurable SESSION_SAMESITE](https://github.com/vishwas0229/e-Karamchari/issues/26)
- [#27 — CSRF enforcement](https://github.com/vishwas0229/e-Karamchari/issues/27)
- [#28 — Extended rate limiting](https://github.com/vishwas0229/e-Karamchari/issues/28)
- [#29 — Holiday validation](https://github.com/vishwas0229/e-Karamchari/issues/29)
- [#30 — Docker production hardening](https://github.com/vishwas0229/e-Karamchari/issues/30)
- [#31 — Docker static asset verification](https://github.com/vishwas0229/e-Karamchari/issues/31)
- [#32 — Automated integration testing](https://github.com/vishwas0229/e-Karamchari/issues/32)
- [#33 — Deployment/tunnel documentation](https://github.com/vishwas0229/e-Karamchari/issues/33)
- [#34 — Complete API documentation](https://github.com/vishwas0229/e-Karamchari/issues/34)
- [#35 — Database backup/recovery](https://github.com/vishwas0229/e-Karamchari/issues/35)
- [#36 — CSP hardening](https://github.com/vishwas0229/e-Karamchari/issues/36)
- [#37 — Responsive/accessibility QA](https://github.com/vishwas0229/e-Karamchari/issues/37)

---

# 📚 Project Documentation

For the academic/project documentation work, see [Issue #3](https://github.com/vishwas0229/e-Karamchari/issues/3).

Recommended documentation set:

- Problem statement
- Objectives and scope
- Functional requirements
- Non-functional requirements
- System architecture
- DFD Level 0/1/2
- ER diagram
- Use-case diagram
- Database/data dictionary
- Security architecture
- API documentation
- Test plan and test cases
- Deployment guide
- Future scope
- Final project report
- Presentation/viva material

Documentation should clearly distinguish **implemented functionality** from **planned/future functionality**.

---

# 📄 License

This project is licensed under the **MIT License**. See [LICENSE](LICENSE).

---

<div align="center">

### 🏆 Developed for Hack4Delhi

**e-Karamchari — Employee Self-Service & HR Management System**

Made with ❤️ by Vishwas

</div>
