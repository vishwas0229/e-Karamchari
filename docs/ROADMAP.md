# e-Karamchari Roadmap

This roadmap separates completed stabilization work from future product enhancements.

## Completed

### Foundation and security
- PHP/Apache + MySQL Docker deployment
- canonical users-based schema
- session hardening and configurable SameSite behavior
- centralized authentication and optional TOTP 2FA
- server-side RBAC
- CSRF enforcement for state-changing API requests
- general and sensitive-operation rate limiting
- production Compose database isolation
- security headers and CSP hardening

### HR modules
- employee management
- attendance
- leave management
- grievance management
- salary/payroll records
- service records
- holiday management
- notifications
- reports
- administration/settings

### Quality and documentation
- Application/database health endpoint and Docker app health status
- Docker asset verification in CI
- PHP syntax validation
- frontend JavaScript syntax validation
- Docker integration tests
- responsive/accessibility source checks
- responsive/accessibility defect remediation
- architecture, SRS, DFD, ER model, use cases and data dictionary
- security architecture
- API, deployment and backup/recovery guides
- Phase 1 audit record
- project report and viva/presentation notes

## Current validation boundary

Automated CI covers source checks, Docker startup, asset delivery, authentication, representative employee/admin API reads, and CSRF behavior.

Real-browser/device and assistive-technology evaluation remains a manual release activity. This roadmap does not mark those checks as completed merely because source-level tests pass.

## Future scope

### Product
- richer dashboard analytics
- configurable approval workflows
- advanced notification preferences
- search/filter improvements
- expanded payroll calculations and exports
- broader employee self-service workflows

### Quality
- browser-level end-to-end testing with Playwright or an equivalent tool
- automated accessibility engine checks such as axe/Lighthouse
- expanded integration fixtures for every write workflow
- dependency/security scanning
- performance/load testing
- richer observability and structured application metrics beyond the basic health endpoint

### Platform
- hardened VPS/cloud deployment templates
- managed database support
- external identity provider integration
- mobile/native application clients
- centralized audit and operational monitoring

## Working rule

Feature development should proceed through focused branches and pull requests. CI must pass before merging changes that affect application behavior.
