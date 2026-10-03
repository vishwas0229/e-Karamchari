# e-Karamchari Project Report

## Abstract

e-Karamchari is a web-based employee self-service and HR management system implemented with PHP, MySQL, HTML, CSS and JavaScript. It separates employee self-service workflows from administrative HR workflows while applying server-side role authorization and common authentication/security middleware.

## Problem Statement

Traditional HR workflows can require employees and administrators to depend on manual forms, disconnected records and repeated office communication. A centralized portal can consolidate common employee services such as attendance, leave, grievances, salary slips and service history while giving authorized staff a single administrative interface.

## Objectives

1. Provide authenticated employee self-service.
2. Centralize administrative HR operations.
3. Maintain a canonical relational HR database.
4. Enforce role-aware access control.
5. Protect state-changing requests against CSRF and abuse.
6. Provide reproducible Docker deployment and automated regression checks.

## Existing vs Proposed System

### Existing/manual workflow characteristics

- paper or manually submitted leave requests
- attendance records maintained in separate systems
- grievance progress communicated manually
- employee payroll/service information distributed across records
- limited centralized reporting

### Proposed system

The implemented portal centralizes these workflows behind role-aware browser interfaces and a common PHP API.

## Implemented Modules

- authentication and sessions
- employee management
- attendance
- leave management
- grievance management
- salary/payroll
- service records
- holidays
- notifications
- reports
- system settings
- TOTP-based 2FA

## System Architecture

See docs/Architecture.md.

## Data Model

See docs/ER-Diagram.md and docs/Data-Dictionary.md.

## Security Model

See docs/Security-Architecture.md.

## Testing

The project includes:
- PHP syntax validation
- UI responsive/accessibility source checks
- Docker asset verification
- Docker integration tests for authentication, representative employee/admin reads and CSRF behavior

See docs/Testing.md.

## Deployment

Docker Compose is the reference deployment for development and repeatable verification. Production guidance is documented in docs/deployment.md and compose.production.yaml.

## Results

The repository contains a working multi-module application with documented deployment, API behavior, backup/recovery and regression-testing procedures. Remaining manual activities are clearly separated from automated verification.

## Limitations

The current implementation is a lightweight PHP application rather than a full enterprise framework. Browser/device accessibility validation beyond automated source checks requires human testing with real browsers and assistive technologies.

## Future Scope

- broader browser E2E coverage
- richer analytics
- external identity provider integration
- configurable workflow approvals
- production observability
- expanded mobile/native application support
