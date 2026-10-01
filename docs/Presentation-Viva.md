# Presentation & Viva Preparation

## Suggested 12-Slide Structure

1. Title — e-Karamchari Employee Self-Service & HR Management Portal
2. Problem statement
3. Objectives and scope
4. Existing vs proposed system
5. Technology stack
6. System architecture
7. Main modules
8. Database / ER model
9. Security architecture
10. Testing and Docker deployment
11. Results, limitations and future scope
12. Live demo and conclusion

## Demo Flow

1. Open employee login.
2. Authenticate an employee account.
3. Show dashboard and current attendance state.
4. Show leave balance and leave workflow.
5. Show grievance tracking.
6. Show salary and service-record views.
7. Log out.
8. Open admin login.
9. Show employee management and approval dashboards.
10. Show attendance/reporting tools.
11. Demonstrate security behavior such as CSRF rejection in the test suite.
12. Show the repository test/CI workflow.

## Viva Questions

### Why PHP and MySQL?

They provide a straightforward server-rendered/API-oriented implementation with a mature relational database suitable for structured HR data.

### Why role-based access control?

Different users require different data visibility and mutation permissions. Authorization is therefore enforced at the backend boundary.

### Why CSRF protection?

Browser sessions use cookies, so state-changing requests need a server-validated anti-CSRF control.

### Why TOTP?

TOTP provides a second authentication factor that does not depend on the primary password alone.

### Why Docker?

Docker provides a reproducible PHP/Apache and MySQL environment and makes CI verification consistent with local development.

### What is the main database entity?

The users table is the canonical account root and is referenced by attendance, leave, grievances, payroll, service records, notifications, sessions, 2FA and audit records.

### What is the security boundary?

The backend API and middleware are the security boundary. UI visibility is not considered authorization.

## Viva Preparation Notes

Be prepared to explain:
- authentication flow
- RBAC checks
- CSRF lifecycle
- rate limiting
- session storage
- database relationships
- Docker Compose networking
- CI regression checks
- known limitations and future work
