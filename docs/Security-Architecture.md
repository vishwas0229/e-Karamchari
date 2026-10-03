# Security Architecture

## 1. Authentication

The authentication API delegates credential verification to the centralized Auth implementation. Employee and administrative login use the same credential verification path with an explicit administrative-role boundary.

The application supports an optional TOTP second factor. Session creation is completed only after the required authentication flow is satisfied.

## 2. Session Security

The application uses server-side session records and configurable cookie attributes. The session configuration supports SameSite selection and enables secure cookies when HTTPS is detected or SameSite=None is deliberately configured.

## 3. Authorization

Authorization is enforced in PHP middleware and individual endpoint handlers. Employee actions are scoped to the current user where required. Administrative endpoints use explicit role checks.

Frontend menus are not the security boundary.

## 4. CSRF Protection

State-changing API requests are protected by server-side CSRF token validation. Authentication bootstrap actions are explicitly exempt until a session exists.

The frontend obtains the token from auth.php?action=csrf and sends it as X-CSRF-Token for protected mutations.

## 5. Rate Limiting

The configuration defines general and authentication-specific request limits plus an additional sensitive state-change limiter. Runtime rate-limit state is stored in a writable temporary runtime directory rather than the persistent logs mount.

## 6. Password Storage

The integration test seeds accounts using PHP password_hash with PASSWORD_DEFAULT. Application authentication expects password hashes rather than plaintext passwords.

## 7. Security Headers

Security headers are configured centrally. The current policy includes same-origin defaults, no object embedding, frame-ancestor restrictions, form-action restrictions and a restricted script source policy.

## 8. Database Security

Database access occurs through the PHP/PDO application layer. Production Compose keeps MySQL private to the Docker network and avoids publishing its port to the host.

## 9. File Storage

Logs and uploads are mounted separately in Docker. Runtime state should not be committed to Git.

## 10. Auditability

Administrative/security activity can be recorded in activity_logs and notifications provide user-facing workflow feedback.

## 11. Security Verification

CI verifies PHP syntax, Docker integration, authentication, representative authenticated reads and CSRF rejection/acceptance. Production deployments still require secure credentials, HTTPS, restricted CORS and validated backup/recovery procedures.
