# e-Karamchari Backup and Recovery

## What must be backed up
1. MySQL database
2. `backend/uploads/`
3. Configuration/secrets stored outside Git
4. Any externally managed TLS/reverse-proxy configuration
5. Deployment metadata such as the deployed Git commit

## Logical database backup
For Docker:
```bash
docker exec ekaramchari-db mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers ekaramchari > ekaramchari-$(date +%F).sql
```

For a host MySQL installation:
```bash
mysqldump -u <user> -p --single-transaction --routines --triggers ekaramchari > ekaramchari-$(date +%F).sql
```

Do not commit database dumps to the repository.

## Uploaded files
Back up the upload directory independently:
```bash
tar -czf ekaramchari-uploads-$(date +%F).tar.gz backend/uploads/
```

For Docker bind mounts, back up the host directory that is mounted to `/var/www/html/backend/uploads`.

## Retention
Use a documented retention policy appropriate to the deployment. Keep more than one recent backup and store at least one copy outside the application host. Encrypt backups containing employee or payroll data.

## Verify a backup
A backup is not considered valid until it has been restored into an isolated test database/server and basic checks succeed:
```sql
SHOW TABLES;
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM attendance;
SELECT COUNT(*) FROM leave_requests;
```

Also verify that a test account can authenticate against the restored copy.

## Restore procedure
1. Stop application traffic or place the application in maintenance mode.
2. Create a fresh database or isolated recovery database.
3. Restore the SQL dump.
4. Restore `backend/uploads/`.
5. Restore the required environment variables/secrets through the normal secret-management process.
6. Start the application against the restored database.
7. Verify login, session creation, one read-only API, one mutation, uploads and 2FA state.
8. Record the recovery time and any missing data.

Example SQL restore:
```bash
mysql -u <user> -p ekaramchari < ekaramchari-YYYY-MM-DD.sql
```

## Docker volume recovery
Do not use `docker compose down -v` on a production environment unless the database has been backed up and the volume is intentionally being destroyed. A named volume is runtime storage, not a backup.

## Incident checklist
- Identify the last known-good backup.
- Preserve relevant logs before deleting or rebuilding containers.
- Restore into an isolated environment first.
- Verify schema and row counts.
- Verify authentication and authorization.
- Verify uploads and critical business records.
- Reconnect production traffic only after validation.
- Document the incident and recovery point/time.