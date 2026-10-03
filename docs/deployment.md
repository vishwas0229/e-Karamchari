# e-Karamchari Deployment Guide

## Supported modes
1. Local PHP/MySQL
2. Docker Compose development
3. Docker production override
4. Temporary HTTPS tunnel for demos
5. VPS/server deployment
6. Separate frontend/backend deployment

## Docker development
Use `docker compose up -d --build`. The application is available on port 8080 and the development database is optionally exposed on port 3307.

## Docker production
Use:
```bash
docker compose -f compose.yaml -f compose.production.yaml up -d --build
```

Set `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `APP_URL` and `SESSION_SAMESITE` explicitly. The production override does not publish MySQL.

## Attendance automation

Manual attendance automation remains available to authenticated administrators.

For unattended server-to-server automation, set `ATTENDANCE_CRON_SECRET` as a deployment secret and send it in the `X-Cron-Secret` header. The secret is intentionally not accepted in a query parameter.

Example:

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-Cron-Secret: $ATTENDANCE_CRON_SECRET" \
  -d '{"date":"2026-10-01"}' \
  "$APP_URL/backend/api/attendance.php?action=auto-mark"
```

The cron secret must be long and randomly generated. Leaving `ATTENDANCE_CRON_SECRET` empty disables unauthenticated cron execution; normal browser/admin requests still require authentication and CSRF protection.

`ATTENDANCE_AUTO_CHECKOUT_TIME` is the shared automatic checkout time and defaults to `18:00:00`.

## Temporary tunnel
A tunnel is appropriate only for temporary demos/testing. Terminate the tunnel after the demo and do not use it as the permanent production edge.

The tunnel must forward to the application HTTP port (normally 8080). The public URL must be placed in `APP_URL`. If the frontend and backend are deployed separately, set `window.EKARAMCHARI_API_BASE_URL` to the backend API base URL.

## VPS/server
1. Install PHP 8.2+, Apache 2.4+, MySQL 8.0+ or use Docker.
2. Import `database/schema.sql` into a new database.
3. Set database credentials through environment variables.
4. Configure `APP_URL` to the HTTPS origin.
5. Put Apache/Nginx/reverse proxy in front of the application.
6. Enable HTTPS and secure firewall rules.
7. Keep MySQL private; allow application-to-database traffic only.
8. Mount or persist `backend/logs` and `backend/uploads`.
9. Configure backups before production use.

## Separate frontend/backend
Set the frontend API base URL before loading the application:
```html
<script>window.EKARAMCHARI_API_BASE_URL = 'https://api.example.com/backend/api';</script>
```

The backend must allow the exact frontend origin through CORS and use HTTPS when cookies are sent cross-site. Review `SESSION_SAMESITE` and cookie security before deployment.

## Pre-deployment checklist
- Strong database passwords
- HTTPS enabled
- MySQL not publicly exposed
- `APP_URL` correct
- CORS restricted to trusted origins
- `SESSION_SAMESITE` intentionally selected
- Logs/uploads persisted
- Database backup verified
- Docker image rebuilt from the intended Git commit
- Login, 2FA and one representative mutation tested