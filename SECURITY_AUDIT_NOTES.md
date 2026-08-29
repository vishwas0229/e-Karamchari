# Security Audit Notes

## Initial findings

### 1. Default administrator credentials are documented in README
The public README contains a default administrator email, employee ID, and password. Even if intended for local/demo setup, publishing reusable credentials in a public repository is unsafe for a deployed application.

**Action:** Replace the documented password with a setup/reset procedure and ensure production deployments create a unique administrator credential.

### 2. Authentication implementation needs consolidation
`backend/api/auth.php` performs an initial credential lookup before calling `Auth::login()`, while `backend/middleware/auth.php` performs another credential lookup and the actual authentication logic. This duplicates authentication logic and increases maintenance/security-review surface.

**Action:** Consolidate credential verification into one authentication path while preserving the existing role and 2FA checks.

### 3. Rate-limit enforcement requires verification
The configuration defines a file-based `checkRateLimit()` helper, but the initial audit did not establish that it is consistently invoked by authentication and other sensitive endpoints.

**Action:** Trace all sensitive endpoints and enforce rate limiting where appropriate; avoid relying on a helper that is never called.

### 4. CSRF enforcement requires endpoint-by-endpoint verification
The authentication middleware provides CSRF token generation and verification, but the initial repository search did not establish complete enforcement across state-changing API endpoints.

**Action:** Audit every state-changing endpoint and require CSRF validation for browser-session requests where applicable.

## Scope
These are initial static-review findings from the repository. Runtime testing against a configured PHP/MySQL environment is still required before marking the audit complete.
