#!/usr/bin/env bash
set -euo pipefail

PROJECT="ekaramchari-integration"
BASE_URL="http://127.0.0.1:8080"
EMPLOYEE_ID="ITEST$RANDOM"
ADMIN_ID="IADMIN$RANDOM"
PASSWORD="IntegrationTest!2026"
ADMIN_PASSWORD="AdminIntegrationTest!2026"
RUN_ID="$RANDOM"
COOKIE_FILE="/tmp/ekaramchari-cookie-$RUN_ID.txt"
ADMIN_COOKIE_FILE="/tmp/ekaramchari-admin-cookie-$RUN_ID.txt"
CSRF_RESPONSE_FILE="/tmp/ekaramchari-csrf-response-$RUN_ID.json"
LEAVE_RESPONSE_FILE="/tmp/ekaramchari-leave-response-$RUN_ID.json"
ADMIN_CSRF_RESPONSE_FILE="/tmp/ekaramchari-admin-csrf-response-$RUN_ID.json"
ATTENDANCE_CRON_SECRET="integration-cron-secret-$RUN_ID"
export ATTENDANCE_CRON_SECRET

compose() {
  docker compose -p "$PROJECT" -f compose.yaml "$@"
}

cleanup() {
  rm -f "$COOKIE_FILE" "$ADMIN_COOKIE_FILE"
  compose down -v --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

command -v docker >/dev/null || { echo "docker is required" >&2; exit 2; }
command -v curl >/dev/null || { echo "curl is required" >&2; exit 2; }

echo "[1/11] Starting isolated test stack"
compose up -d --build

echo "[2/11] Waiting for HTTP endpoint"
for _ in $(seq 1 60); do
  if curl -fsS "$BASE_URL/frontend/employee-login.html" >/dev/null 2>&1; then break; fi
  sleep 2
done
curl -fsS "$BASE_URL/frontend/employee-login.html" >/dev/null

echo "[3/11] Seeding temporary employee and admin"
HASH=$(compose exec -T -e TEST_PASSWORD="$PASSWORD" app php -r 'echo password_hash(getenv("TEST_PASSWORD"), PASSWORD_DEFAULT);')
ADMIN_HASH=$(compose exec -T -e TEST_PASSWORD="$ADMIN_PASSWORD" app php -r 'echo password_hash(getenv("TEST_PASSWORD"), PASSWORD_DEFAULT);')
compose exec -T db sh -c 'mysql -h 127.0.0.1 -u root -p"$MYSQL_ROOT_PASSWORD" ekaramchari' <<SQL
INSERT INTO users
(employee_id, email, password_hash, role_id, first_name, last_name, department_id, designation_id, is_active, is_locked)
VALUES
('$EMPLOYEE_ID', '$EMPLOYEE_ID@example.test', '$HASH', 4, 'Integration', 'Tester', 4, 5, 1, 0);
INSERT INTO leave_balance (employee_id, leave_type_id, year, total_allocated, used, carried_forward)
SELECT id, 1, YEAR(CURDATE()), 45, 0, 0 FROM users WHERE employee_id = '$EMPLOYEE_ID';
INSERT INTO users
(employee_id, email, password_hash, role_id, first_name, last_name, department_id, designation_id, is_active, is_locked)
VALUES
('$ADMIN_ID', '$ADMIN_ID@example.test', '$ADMIN_HASH', 2, 'Integration', 'Admin', 1, 1, 1, 0);
SQL

echo "[4/11] Checking employee CSRF bootstrap"
curl -fsS -c "$COOKIE_FILE" "$BASE_URL/backend/api/auth.php?action=csrf" | grep -q '"success":true'

echo "[5/11] Testing employee login"
LOGIN=$(curl -fsS -b "$COOKIE_FILE" -c "$COOKIE_FILE" \
  -H 'Content-Type: application/json' \
  -d '{"employee_id":"'"$EMPLOYEE_ID"'","password":"'"$PASSWORD"'"}' \
  "$BASE_URL/backend/api/auth.php?action=login")
echo "$LOGIN" | grep -q '"success":true'

echo "[6/11] Testing employee HR reads"
curl -fsS -b "$COOKIE_FILE" "$BASE_URL/backend/api/auth.php?action=check" | grep -q '"success":true'
curl -fsS -b "$COOKIE_FILE" "$BASE_URL/backend/api/employees.php?action=profile" | grep -q '"success":true'
curl -fsS -b "$COOKIE_FILE" "$BASE_URL/backend/api/attendance.php?action=today" | grep -q '"success":true'
curl -fsS -b "$COOKIE_FILE" "$BASE_URL/backend/api/leaves.php?action=balance" | grep -q '"success":true'
curl -fsS -b "$COOKIE_FILE" "$BASE_URL/backend/api/leaves.php?action=my-leaves" | grep -q '"success":true'
curl -fsS -b "$COOKIE_FILE" "$BASE_URL/backend/api/grievances.php?action=my-grievances" | grep -q '"success":true'
curl -fsS -b "$COOKIE_FILE" "$BASE_URL/backend/api/salary.php?action=my-slips" | grep -q '"success":true'
curl -fsS -b "$COOKIE_FILE" "$BASE_URL/backend/api/service-records.php?action=my-records" | grep -q '"success":true'
curl -fsS -b "$COOKIE_FILE" "$BASE_URL/backend/api/dashboard.php?action=employee-stats" | grep -q '"success":true'
curl -fsS -b "$COOKIE_FILE" "$BASE_URL/backend/api/dashboard.php?action=notifications" | grep -q '"success":true'
curl -fsS -b "$COOKIE_FILE" "$BASE_URL/backend/api/two-factor.php?action=status" | grep -q '"success":true'
curl -fsS -b "$COOKIE_FILE" "$BASE_URL/backend/api/holidays.php?action=upcoming" | grep -q '"success":true'

echo "[7/11] Testing employee CSRF enforcement"
CSRF=$(curl -fsS -b "$COOKIE_FILE" "$BASE_URL/backend/api/auth.php?action=csrf" | sed -n 's/.*"csrf_token":"\([^"]*\)".*/\1/p')
test -n "$CSRF"
STATUS=$(curl -sS -o "$CSRF_RESPONSE_FILE" -w '%{http_code}' \
  -b "$COOKIE_FILE" -H 'Content-Type: application/json' \
  -d '{"reason":"integration test","start_date":"2099-01-02","end_date":"2099-01-02","leave_type_id":1}' \
  "$BASE_URL/backend/api/leaves.php?action=apply")
test "$STATUS" = "403"

echo "[8/11] Testing employee CSRF-protected mutation"
STATUS=$(curl -sS -o "$LEAVE_RESPONSE_FILE" -w '%{http_code}' \
  -b "$COOKIE_FILE" -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" \
  -d '{"reason":"integration test","start_date":"2099-01-02","end_date":"2099-01-02","leave_type_id":1}' \
  "$BASE_URL/backend/api/leaves.php?action=apply")
test "$STATUS" = "200"

rm -f "$CSRF_RESPONSE_FILE" "$LEAVE_RESPONSE_FILE"

echo "[9/11] Testing admin login"
curl -fsS -c "$ADMIN_COOKIE_FILE" "$BASE_URL/backend/api/auth.php?action=csrf" | grep -q '"success":true'
ADMIN_LOGIN=$(curl -fsS -b "$ADMIN_COOKIE_FILE" -c "$ADMIN_COOKIE_FILE" \
  -H 'Content-Type: application/json' \
  -d '{"identifier":"'"$ADMIN_ID"'","password":"'"$ADMIN_PASSWORD"'"}' \
  "$BASE_URL/backend/api/auth.php?action=admin-login")
echo "$ADMIN_LOGIN" | grep -q '"success":true'

echo "[10/11] Testing admin HR reads"
curl -fsS -b "$ADMIN_COOKIE_FILE" "$BASE_URL/backend/api/dashboard.php?action=admin-stats" | grep -q '"success":true'
curl -fsS -b "$ADMIN_COOKIE_FILE" "$BASE_URL/backend/api/employees.php?action=list" | grep -q '"success":true'
curl -fsS -b "$ADMIN_COOKIE_FILE" "$BASE_URL/backend/api/attendance.php?action=list" | grep -q '"success":true'
curl -fsS -b "$ADMIN_COOKIE_FILE" "$BASE_URL/backend/api/leaves.php?action=pending-count" | grep -q '"success":true'
curl -fsS -b "$ADMIN_COOKIE_FILE" "$BASE_URL/backend/api/grievances.php?action=list" | grep -q '"success":true'
curl -fsS -b "$ADMIN_COOKIE_FILE" "$BASE_URL/backend/api/salary.php?action=list" | grep -q '"success":true'
curl -fsS -b "$ADMIN_COOKIE_FILE" "$BASE_URL/backend/api/service-records.php?action=list" | grep -q '"success":true'
curl -fsS -b "$ADMIN_COOKIE_FILE" "$BASE_URL/backend/api/reports.php?action=overview" | grep -q '"success":true'
curl -fsS -b "$ADMIN_COOKIE_FILE" "$BASE_URL/backend/api/settings.php?action=list" | grep -q '"success":true'
curl -fsS -b "$ADMIN_COOKIE_FILE" "$BASE_URL/backend/api/two-factor.php?action=status" | grep -q '"success":true'

echo "[11/11] Testing admin CSRF enforcement"
ADMIN_CSRF=$(curl -fsS -b "$ADMIN_COOKIE_FILE" "$BASE_URL/backend/api/auth.php?action=csrf" | sed -n 's/.*"csrf_token":"\([^"]*\)".*/\1/p')
test -n "$ADMIN_CSRF"
ADMIN_STATUS=$(curl -sS -o "/tmp/ekaramchari-admin-csrf-response-$RANDOM.json" -w '%{http_code}' \
  -b "$ADMIN_COOKIE_FILE" -H 'Content-Type: application/json' \
  -d '{"key":"integration_test","value":"denied"}' \
  "$BASE_URL/backend/api/settings.php?action=update")
test "$ADMIN_STATUS" = "403"

rm -f "$ADMIN_CSRF_RESPONSE_FILE"
echo "[12/12] Checking attendance auto-check-in regression"
compose exec -T app php /var/www/html/tests/auth-attendance-regression.php

echo "[13/13] Verifying approved leave attendance handling"
LEAVE_TEST_DATE="2099-01-08"
compose exec -T db sh -c 'mysql -h 127.0.0.1 -u root -p"$MYSQL_ROOT_PASSWORD" ekaramchari' <<SQL
INSERT INTO leave_requests
(request_number, employee_id, leave_type_id, start_date, end_date, total_days, reason, status, approved_by, approved_at)
SELECT 'TEST-LEAVE-20990108',
       (SELECT id FROM users WHERE employee_id = '$EMPLOYEE_ID' LIMIT 1),
       1, '$LEAVE_TEST_DATE', '$LEAVE_TEST_DATE', 1,
       'Integration attendance regression', 'Approved',
       (SELECT id FROM users WHERE employee_id = '$ADMIN_ID' LIMIT 1), NOW();
SQL
AUTO_LEAVE_STATUS=$(curl -sS -o "/tmp/ekaramchari-auto-leave-$RANDOM.json" -w '%{http_code}'   -b "$ADMIN_COOKIE_FILE" -H 'Content-Type: application/json' -H "X-CSRF-Token: $ADMIN_CSRF"   -d '{"date":"2099-01-08"}'   "$BASE_URL/backend/api/attendance.php?action=auto-mark")
test "$AUTO_LEAVE_STATUS" = "200"
LEAVE_ATTENDANCE_STATUS=$(compose exec -T db sh -c 'mysql -h 127.0.0.1 -u root -p"$MYSQL_ROOT_PASSWORD" -Nse "SELECT status FROM attendance WHERE employee_id = (SELECT id FROM users WHERE employee_id = '\''$EMPLOYEE_ID'\'') AND attendance_date = '\''2099-01-08'\'' LIMIT 1"')
test "$LEAVE_ATTENDANCE_STATUS" = "On Leave"


echo "[14/17] Verifying leave approval balance protection"
BALANCE_REQUEST="TEST-BALANCE-$RUN_ID"
BALANCE_DATE="$(date -d '+1 day' +%Y-%m-%d)"
BALANCE_YEAR="$(date -d "$BALANCE_DATE" +%Y)"
compose exec -T db sh -c 'mysql -h 127.0.0.1 -u root -p"$MYSQL_ROOT_PASSWORD" ekaramchari' <<SQL
INSERT INTO leave_balance (employee_id, leave_type_id, year, total_allocated, used, carried_forward)
SELECT id, 1, $BALANCE_YEAR, 1, 1, 0
FROM users WHERE employee_id = '$EMPLOYEE_ID'
ON DUPLICATE KEY UPDATE total_allocated = 1, used = 1, carried_forward = 0;

INSERT INTO leave_requests
(request_number, employee_id, leave_type_id, start_date, end_date, total_days, reason, status)
SELECT '$BALANCE_REQUEST',
       (SELECT id FROM users WHERE employee_id = '$EMPLOYEE_ID' LIMIT 1),
       1, '$BALANCE_DATE', '$BALANCE_DATE', 1,
       'Integration leave balance regression', 'Pending';
SQL
BALANCE_REQUEST_ID=$(compose exec -T db sh -c "mysql -h 127.0.0.1 -u root -p\\\"\$MYSQL_ROOT_PASSWORD\\\" -Nse \\\"SELECT id FROM leave_requests WHERE request_number = '$BALANCE_REQUEST' LIMIT 1\\\"")
BALANCE_STATUS=$(curl -sS -o "/tmp/ekaramchari-balance-$RANDOM.json" -w '%{http_code}' \
  -b "$ADMIN_COOKIE_FILE" -H 'Content-Type: application/json' -H "X-CSRF-Token: $ADMIN_CSRF" \
  -d '{"id":'"$BALANCE_REQUEST_ID"'}' \
  "$BASE_URL/backend/api/leaves.php?action=approve")
test "$BALANCE_STATUS" = "422"
BALANCE_REQUEST_STATE=$(compose exec -T db sh -c 'mysql -h 127.0.0.1 -u root -p"$MYSQL_ROOT_PASSWORD" -Nse "SELECT status FROM leave_requests WHERE id = '"$BALANCE_REQUEST_ID"' LIMIT 1"')
test "$BALANCE_REQUEST_STATE" = "Pending"
BALANCE_USED=$(compose exec -T db sh -c "mysql -h 127.0.0.1 -u root -p\\\"\$MYSQL_ROOT_PASSWORD\\\" -Nse \\\"SELECT used FROM leave_balance WHERE employee_id = (SELECT id FROM users WHERE employee_id = '$EMPLOYEE_ID') AND leave_type_id = 1 AND year = $BALANCE_YEAR LIMIT 1\\\"")
test "$BALANCE_USED" = "1"

echo "[15/17] Rejecting future attendance auto-mark requests"
FUTURE_STATUS=$(curl -sS -o "/tmp/ekaramchari-future-attendance-$RANDOM.json" -w '%{http_code}' \
  -b "$ADMIN_COOKIE_FILE" -H 'Content-Type: application/json' -H "X-CSRF-Token: $ADMIN_CSRF" \
  -d '{"date":"2099-12-31"}' \
  "$BASE_URL/backend/api/attendance.php?action=auto-mark")
test "$FUTURE_STATUS" = "422"

echo "[16/17] Verifying attendance cron authentication"
LEGACY_CRON_STATUS=$(curl -sS -o "/tmp/ekaramchari-legacy-cron-$RANDOM.json" -w '%{http_code}' \
  "$BASE_URL/backend/api/attendance.php?action=auto-mark&cron_key=your_secret_cron_key_here")
test "$LEGACY_CRON_STATUS" != "200"
NO_SECRET_STATUS=$(curl -sS -o "/tmp/ekaramchari-no-secret-cron-$RANDOM.json" -w '%{http_code}' \
  -X POST -H 'Content-Type: application/json' \
  -d '{"date":"2020-01-02"}' \
  "$BASE_URL/backend/api/attendance.php?action=auto-mark")
test "$NO_SECRET_STATUS" != "200"
VALID_GET_CRON_STATUS=$(curl -sS -o "/tmp/ekaramchari-get-cron-$RANDOM.json" -w '%{http_code}' \
  -H "X-Cron-Secret: $ATTENDANCE_CRON_SECRET" \
  "$BASE_URL/backend/api/attendance.php?action=auto-mark")
test "$VALID_GET_CRON_STATUS" != "200"
CRON_STATUS=$(curl -sS -o "/tmp/ekaramchari-cron-$RANDOM.json" -w '%{http_code}' \
  -X POST -H 'Content-Type: application/json' -H "X-Cron-Secret: $ATTENDANCE_CRON_SECRET" \
  -d '{"date":"2020-01-02"}' \
  "$BASE_URL/backend/api/attendance.php?action=auto-mark")
test "$CRON_STATUS" = "200"

echo "[17/17] Verifying canonical attendance automation time"
compose exec -T app php /var/www/html/tests/auth-attendance-regression.php
echo "Integration tests passed."
