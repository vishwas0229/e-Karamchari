# Testing Strategy

## 1. Test Layers

### Static checks

- PHP syntax validation for all backend PHP files.
- UI source-level responsive/accessibility checks.
- Legacy frontend path checks.
- Documentation should remain synchronized with the current API and deployment model.

### Integration checks

tests/integration.sh starts an isolated Docker Compose project, waits for the application, seeds a temporary employee, obtains a CSRF token, authenticates, exercises representative employee reads and validates CSRF enforcement on a state-changing leave request.

### CI

.github/workflows/application-quality.yml currently verifies:
1. PHP syntax.
2. UI accessibility source checks.
3. Docker application startup.
4. Static CSS/JavaScript asset delivery and content types.
5. absence of legacy frontend/backend paths.
6. the Docker integration suite.

## 2. Running Tests Locally

### PHP syntax

    find backend -type f -name '*.php' -print0 | xargs -0 -n1 php -l

### UI accessibility source checks

    bash tests/ui-accessibility.sh

### Docker integration

    bash tests/integration.sh

## 3. Test Data Safety

The integration test uses a dedicated Compose project name and cleans its temporary database volume on exit. It should not be pointed at a production database.

## 4. Functional Smoke Coverage

Current automated functional smoke coverage includes:
- application HTTP availability
- employee login
- session check
- employee profile
- attendance today
- leave balance
- CSRF rejection without a token
- CSRF-protected leave mutation

The remaining HR modules are covered by action-level API documentation and should be expanded in future integration batches as test fixtures become available.

## 5. Regression Expectations

Every change to authentication, session, database schema, Docker configuration or API behavior should run:
- PHP syntax checks
- UI source checks when frontend code changes
- Docker integration tests
- the full Application Quality GitHub Actions workflow

## 6. Test Environment

Reference runtime:
- PHP 8.2
- Apache
- MySQL 8.0
- Docker Compose v2
- Linux CI runner

## 7. Acceptance

A change is considered regression-safe only after its relevant test layer passes. Issue closure should be performed only after the acceptance criteria are supported by actual test evidence.
