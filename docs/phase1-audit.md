# Phase 1 Audit & Stabilization Record

## Audit scope

The audit covers repository structure, frontend pages, backend APIs, database schema, authentication/RBAC, Docker deployment, representative HR workflows, security controls, and UI source-level accessibility.

## Repository and runtime

- Frontend: static HTML/CSS/JavaScript under frontend/
- Backend: PHP action APIs under backend/api/
- Shared security/configuration: backend/middleware and backend/config
- Database: canonical schema in database/schema.sql
- Runtime: PHP 8.2 + Apache with MySQL 8.0 under Docker Compose

## Authentication and RBAC

Findings addressed in previous stabilization PRs:
- duplicate authentication verification consolidated
- centralized session completion after optional 2FA
- CSRF enforcement added for state-changing requests
- authentication and sensitive mutation rate limits added
- logout changed to a POST operation
- configurable SameSite session behavior
- production database exposure hardened

## API audit

PHP syntax validation is automated for every backend PHP file.

The current API surface includes:
- authentication
- dashboard
- employees
- attendance
- leaves
- grievances
- salary
- service records
- reports
- settings
- holidays
- two-factor authentication

Action-level contracts are documented in docs/api-reference.md.

## Database audit

The canonical users table is the account root. Foreign keys enforce relationships with roles, departments, designations and HR transaction tables.

The schema includes:
- identity and access
- attendance
- leave
- grievances
- payroll
- service records
- holidays
- notifications
- audit logs
- sessions
- 2FA
- system settings

Legacy employees-table migration guidance is documented under database/migrations/.

## Functional verification

The Docker integration suite verifies:
- application availability
- employee authentication
- session check
- employee profile
- attendance
- leave balance and leave history
- grievances
- salary slips
- service records
- employee dashboard statistics
- notifications
- 2FA status
- holidays
- CSRF rejection without a token
- CSRF-protected leave mutation
- administrative authentication
- admin dashboard
- employee listing
- attendance listing
- leave pending count
- grievance listing
- salary listing
- service record listing
- reporting overview
- settings read
- administrative 2FA status
- administrative CSRF rejection

## Deployment audit

Docker Compose provides the reference runtime. Static CSS and JavaScript delivery is covered by CI. Production Compose keeps MySQL private and requires explicit database credentials.

Backup and recovery procedures are documented in docs/backup-recovery.md.

## UI audit

Responsive and accessibility source checks cover every major employee/admin page and four system pages. Findings are tracked as separate issues and addressed in the UI accessibility batch.

See docs/ui-qa.md.

## Remaining manual boundary

Source and integration automation cannot replace human browser/device and assistive-technology validation. Those checks remain explicit release-validation tasks.

## Acceptance

Phase 1 can be considered stabilized only when the corresponding CI workflows pass on the merged main branch and no known blocking issue from this audit remains unresolved.
