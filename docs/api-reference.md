# e-Karamchari API Reference

## Conventions
Base path: `/backend/api/`.
Action routing uses `?action=<name>`.
JSON requests use `Content-Type: application/json`.
Authenticated browser mutations require `X-CSRF-Token`.
Authentication and role checks are enforced server-side.

## Common responses
Success:
```json
{"success":true,"message":"Success","data":{}}
```

Error:
```json
{"success":false,"message":"Error message","errors":[]}
```

Common status codes: `200` success, `400` validation, `401` unauthenticated/session expired, `403` forbidden or invalid CSRF, `405` method not allowed, `429` rate limit, `500` server error.

## Authentication
### `auth.php`
| Action | Method | Auth | Purpose |
|---|---|---|---|
| `login` | POST | Public | Employee login |
| `admin-login` | POST | Public | Admin/Officer/Super Admin login |
| `logout` | POST | Session | End current session |
| `check` | GET | Optional | Check session |
| `csrf` | GET | Session/bootstrap | Obtain CSRF token |
| `user` | GET | Authenticated | Current user |

### CSRF
The frontend obtains `auth.php?action=csrf` and sends the returned token in `X-CSRF-Token` for state-changing requests. Login bootstrap is exempt until a session exists.

## Employees — `employees.php`
Actions: `list`, `get`, `create`, `update`, `delete`, `profile`, `update-profile`, `change-password`, `departments`, `designations`, `stats`.
Mutations use POST and require authentication plus the role permitted by the handler.

## Leaves — `leaves.php`
Actions: `list`, `get`, `my-leaves`, `apply`, `approve`, `reject`, `cancel`, `balance`, `types`, `stats`, `pending-count`.
Employee mutations are scoped to the current user; approval actions require administrative authorization.

## Grievances — `grievances.php`
Actions: `list`, `my-grievances`, `get`, `submit`, `update-status`, `assign`, `resolve`, `add-comment`, `categories`, `stats`, `pending-count`.
Administrative status/assignment actions are role protected.

## Attendance — `attendance.php`
Actions: `list`, `my-attendance`, `check-in`, `check-out`, `admin-checkout`, `today`, `mark`, `report`, `summary`, `stats`, `holidays`, `auto-mark`, `finalize-day`.
State-changing actions require CSRF and appropriate authorization.

## Salary — `salary.php`
Actions: `my-slips`, `view`, `list`, `generate`, `update`, `delete`, `bulk-generate`.
Salary management mutations are administrative; employee reads are scoped to permitted records.

## Service records — `service-records.php`
Actions: `list`, `my-records`, `get`, `create`, `update`, `delete`, `types`.
Create/update/delete operations are authorization protected.

## Reports — `reports.php`
Actions: `overview`, `monthly`, `attendance`, `leaves`, `grievances`, `employees`, `department`.
Reports are read-only but remain authenticated/role protected.

## Settings — `settings.php`
Actions: `list`, `get`, `update`, `departments`, `designations`, `holidays`, `leave-types`, `activity-logs`, `sessions`, `terminate-session`, `reset-password`, `unlock-account`.
Administrative mutations require CSRF and role authorization.

## Holidays — `holidays.php`
Actions: `list`, `monthly`, `upcoming`, `templates`, `generate`, `create`, `update`, `delete`, `update-template`.
Read actions require authentication; management actions require admin authorization and CSRF.

## Two-factor — `two-factor.php`
Actions: `status`, `setup`, `verify-setup`, `verify`, `disable`, `backup-codes`, `regenerate-backup`.
2FA setup and management are session protected. Login verification uses the temporary 2FA session established by the authentication flow.

## Rate limiting
The API has a general per-client limiter plus an additional endpoint/user-scoped limiter for state-changing requests. Login also has an identifier-specific stricter limit.

This document describes the current action-level contract. Endpoint-specific field validation remains implemented in the PHP handlers.