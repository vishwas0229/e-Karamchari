# Software Requirements Specification (SRS)

## 1. Purpose

This SRS defines the functional and non-functional requirements of the e-Karamchari employee self-service and HR management portal.

## 2. Product Scope

The system provides two browser portals backed by a common PHP/MySQL API:

- Employee portal for personal HR self-service.
- Admin/Officer portal for organization-wide HR operations.

Functional domains include authentication, employee profiles, attendance, leave, grievances, payroll, service records, notifications, holidays, reports and system settings.

## 3. User Classes

### Employee

Can access their own profile, attendance, leave balance and requests, grievances, salary slips, service records, notifications and 2FA settings.

### Officer

Can access role-permitted employee and approval/report workflows.

### Admin

Can manage employees, approvals, attendance, payroll, service records, reports and selected settings.

### Super Admin

Has full system permissions.

## 4. Functional Requirements

### FR-01 Authentication

The system shall authenticate employee and administrative users using employee/admin identifiers and passwords.

### FR-02 Session Management

The system shall create a server-side authenticated session after successful login and shall reject expired or missing sessions.

### FR-03 Two-Factor Authentication

The system shall support TOTP-based 2FA, verification, disabling and backup-code workflows for protected accounts.

### FR-04 Employee Management

Authorized administrative users shall be able to list, view, create, update and manage employee records.

### FR-05 Profile Management

Employees shall be able to view and update permitted profile data and change their password.

### FR-06 Attendance

Employees shall be able to check in/check out and view attendance. Authorized staff shall be able to query, mark and report attendance.

### FR-07 Leave Management

Employees shall be able to view balances, apply for leave and review/cancel requests. Authorized staff shall be able to approve or reject requests.

### FR-08 Grievance Management

Employees shall be able to submit and track grievances. Authorized staff shall be able to assign, update and resolve grievances and manage comments.

### FR-09 Payroll

Employees shall be able to view permitted salary slips. Authorized staff shall be able to generate, update, list and delete salary records.

### FR-10 Service Records

Employees shall be able to view service records. Authorized staff shall be able to create, update and delete service history.

### FR-11 Holidays

Authenticated users shall be able to read holiday information. Authorized staff shall manage holiday records and templates.

### FR-12 Notifications

The system shall provide in-app notifications and support read/unread state management.

### FR-13 Reports

Authorized administrative users shall be able to access organizational, attendance, leave, grievance, employee and department reports.

### FR-14 Settings

Authorized administrative users shall be able to manage supported departments, designations, leave types, holidays, activity logs and sessions.

### FR-15 Audit Logging

Security-sensitive or administrative actions shall be recorded in activity logs where implemented by the backend module.

## 5. Security Requirements

- Passwords shall be stored as one-way password hashes.
- Sessions shall be server-side and cookie protected.
- State-changing API calls shall require CSRF validation except for explicitly documented authentication bootstrap actions.
- Authentication and state-changing requests shall be rate limited.
- Role authorization shall be enforced server-side.
- Security response headers shall be configured centrally.
- Production database access shall remain private to the application network.

## 6. Data Requirements

The canonical database schema is database/schema.sql.

Primary entities:
- users
- roles
- departments
- designations
- leave_types
- leave_requests
- leave_balance
- grievance_categories
- grievances
- grievance_comments
- attendance
- service_records
- salary_slips
- holidays
- notifications
- activity_logs
- two_factor_auth
- sessions
- system_settings

## 7. Interface Requirements

### Employee UI

The employee portal shall provide dashboard navigation to attendance, leave, grievances, profile, salary slips and service records.

### Admin UI

The admin portal shall provide dashboard navigation to employee management, approvals, attendance reports, payroll, service records, reports and settings.

### API

The browser API base path is backend/api/. Requests use action query parameters such as action=list or action=profile.

## 8. Non-Functional Requirements

### NFR-01 Availability

The Docker deployment shall start the application and database as a coordinated service stack.

### NFR-02 Maintainability

Shared authentication, API, CSS and utility functionality shall remain centralized rather than duplicated across modules.

### NFR-03 Accessibility

Pages shall use responsive viewport metadata, visible keyboard focus, responsive layouts and accessible names for common interactive controls.

### NFR-04 Security

Authentication, authorization, CSRF controls, rate limiting and secure deployment configuration shall be enforced on the server side.

### NFR-05 Performance

Database queries should remain scoped and indexed using the indexes defined in the canonical schema. Large tables should be presented through constrained responsive containers.

### NFR-06 Portability

The application shall be runnable through Docker Compose without relying on a developer-specific machine path.

## 9. Constraints and Assumptions

- MySQL 8.0 is the reference database image.
- PHP 8.2 and Apache are the reference Docker runtime.
- Browser JavaScript is required for the interactive portals.
- The exact production URL and credentials are deployment-specific.
- Existing legacy databases require the documented migration process.

## 10. Traceability

Functional requirements map to API modules in docs/api-reference.md and to UI pages under frontend/employee and frontend/admin. Integration coverage is documented in tests/integration.sh and CI workflow configuration.
