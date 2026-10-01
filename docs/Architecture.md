# e-Karamchari System Architecture

## 1. Overview

e-Karamchari is a browser-based employee self-service and HR management system. The current implementation is a layered PHP + MySQL application served through Apache, with static HTML/CSS/JavaScript portals communicating with action-based PHP API endpoints.

## 2. Logical Architecture

    [Employee Browser]        [Admin/Officer Browser]
             |                         |
             +----------- HTTPS/HTTP --+
                         |
                         v
                [Apache + PHP 8.2]
                         |
          +--------------+---------------+
          |                              |
          v                              v
    [Frontend assets]              [PHP API layer]
          |                         | Auth / RBAC
          |                         | CSRF / Rate limit
          |                         | Business operations
          |                         v
          |                     [PDO / MySQL]
          |                              |
          +------------------------------+
                                         v
                                [ekaramchari DB]

## 3. Frontend Layer

Location: frontend/

Responsibilities:
- employee and admin login flows
- dashboard navigation
- forms and validation
- tables, reports and status views
- session/notification presentation
- API client communication
- TOTP/2FA interaction

Shared frontend components are provided by common CSS and JavaScript utility modules.

## 4. Backend Layer

Location: backend/

API modules:
- auth.php
- dashboard.php
- employees.php
- attendance.php
- leaves.php
- grievances.php
- holidays.php
- salary.php
- service-records.php
- reports.php
- settings.php
- two-factor.php

Security middleware provides session authentication, role authorization, CSRF validation, rate limiting and security headers.

## 5. Authentication and Authorization Flow

    Browser
      |
      | login credentials
      v
    auth.php
      |
      +--> credential verification
      |
      +--> optional TOTP verification
      |
      +--> server session
      |
      v
    authenticated API requests
      |
      +--> requireAuth()
      +--> role checks
      +--> CSRF check for state changes
      +--> rate limiting
      |
      v
    module handler
      |
      v
    MySQL

Frontend state is used for navigation only. Authorization is enforced in the backend.

## 6. Data Layer

The canonical schema is database/schema.sql. The users table is the account root and is related to role, department and designation records. HR transactions reference users through foreign keys.

Major functional domains:
- identity and access
- attendance
- leave management
- grievance management
- payroll
- service records
- notifications
- holidays
- audit logging
- system settings

## 7. Deployment Architecture

### Development

    Browser -> localhost:8080 -> Apache/PHP container -> MySQL container

### Production

    Browser -> HTTPS reverse proxy -> PHP/Apache container
                                         |
                                         v
                                    private MySQL

The production Compose override keeps MySQL off the public host interface.

## 8. Security Boundaries

Trust boundaries are defined at the backend API:
1. unauthenticated public entry points
2. authenticated session boundary
3. role authorization boundary
4. CSRF boundary for state-changing requests
5. database access boundary

The browser must never be treated as the authority for role or permission decisions.

## 9. External and Operational Dependencies

- Docker / Docker Compose for reproducible local deployment
- MySQL 8.0 database
- PHP 8.2 runtime
- Apache HTTP server
- browser storage and cookies for session continuity
- optional external QR/CDN resources documented in the security configuration

## 10. Failure and Recovery

Operational recovery is documented in docs/backup-recovery.md. Database backup, uploads, logs and Docker volumes must be treated as separate recovery assets.

## 11. Architecture Constraints

The current repository is intentionally lightweight and does not use a framework such as Laravel. API action routing is therefore implemented directly in PHP files. Future framework migration should preserve the existing RBAC, CSRF, rate limiting and audit requirements.
