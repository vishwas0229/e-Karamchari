# Legacy `employees` Schema Migration Guide

The current application uses `users` as the canonical employee-account table. Do not point a current deployment at an older database that still uses a different `employees` schema without checking compatibility first.

## 1. Back up the existing database

```bash
mysqldump -u <user> -p <database> > legacy-backup.sql
```

Keep the backup outside the application repository.

## 2. Inspect the legacy schema

```sql
SHOW TABLES LIKE 'employees';
SHOW CREATE TABLE employees;
DESCRIBE employees;
```

Also inspect foreign keys:

```sql
SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
  AND (TABLE_NAME = 'employees' OR REFERENCED_TABLE_NAME = 'employees');
```

## 3. Do not blindly rename the table

The current schema expects the columns, foreign keys and role relationships defined in `database/schema.sql`. A legacy `employees` table may have a different primary key, password column, role representation or dependent foreign keys.

A blind:

```sql
RENAME TABLE employees TO users;
```

is supported only when the legacy table has first been verified to be structurally compatible with the current `users` definition.

## 4. Recommended migration path

For a structurally different legacy database:

1. Create a fresh database from the current `database/schema.sql`.
2. Export legacy employee records to an intermediate CSV/SQL file.
3. Map legacy fields to the canonical `users` columns.
4. Map legacy roles to `roles.role_code`.
5. Import the transformed records into `users`.
6. Recreate dependent records (`attendance`, `leave_requests`, `grievances`, `salary_slips`, `service_records`, etc.) using the new `users.id` values.
7. Run application smoke tests for login and employee-related APIs.
8. Keep the original legacy backup until the migrated deployment has been verified.

## 5. Canonical verification

After migration:

```sql
SHOW TABLES LIKE 'users';
SHOW CREATE TABLE users;

SELECT COUNT(*) AS user_count FROM users;
SELECT COUNT(*) AS role_count FROM roles;

SELECT u.id, u.employee_id, u.email, r.role_code
FROM users u
JOIN roles r ON r.id = u.role_id
LIMIT 10;
```

The backend authentication and employee APIs should query `users`; no compatibility table should be introduced unless the migration requires a temporary staging layer.

## Supported schema

The authoritative schema is `database/schema.sql`.

Schema changes should be added under `database/migrations/` and documented here before deployment.