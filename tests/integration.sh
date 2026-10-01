#!/usr/bin/env bash
set -euo pipefail

PROJECT="ekaramchari-integration"
BASE_URL="http://127.0.0.1:8080"
EMPLOYEE_ID="ITEST$RANDOM"
ADMIN_ID="IADMIN$RANDOM"
PASSWORD="IntegrationTest!2026"
ADMIN_PASSWORD="AdminIntegrationTest!2026"
COOKIE_FILE="/tmp/ekaramchari-cookie-$.txt"
ADMIN_COOKIE_FILE="/tmp/ekaramchari-admin-cookie-$.txt"

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

echo "[1/8] Starting isolated test stack"
compose up -d --build

echo "[2/8] Waiting for HTTP endpoint"
for _ in $(seq 1 60); do
  if curl -fsS "$BASE_URL/frontend/employee-login.html" >/dev/null 2>&1; then break; fi
  sleep 2
done
curl -fsS "$BASE_URL/frontend/employee-login.html" >/dev/null

echo "[3/8] Seeding temporary employee and admin"
HASH=$(compose exec -T -e TEST_PASSWORD="$PASSWORD" app php -r 'echo password_hash(getenv("TEST_PASSWORD"), PASSWORD_DEFAULT);')
ADMIN_HASH=$(compose exec -T -e TEST_PASSWORD="$ADMIN_PASSWORD" app php -r 'echo password_hash(getenv("TEST_PASSWORD"), PASSWORD_DEFAULT);')
c_tmp="__ADMIN_HASH_PLACEHOLDER__"
: "$c_tmp"
compose exec -T db sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" ekaramchari' <<SQL
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
STATUS=$(curl -sS -o "/tmp/ekaramchari-csrf-response-$$.json" -w '%{http_code}' \
  -b "$COOKIE_FILE" -H 'Content-Type: application/json' \
  -d '{"reason":"integration test","start_date":"2099-01-02","end_date":"2099-01-02","leave_type_id":1}' \
  "$BASE_URL/backend/api/leaves.php?action=apply")
test "$STATUS" = "403"

echo "[8/11] Testing employee CSRF-protected mutation"
STATUS=$(curl -sS -o "/tmp/ekaramchari-leave-response-$$.json" -w '%{http_code}' \
  -b "$COOKIE_FILE" -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" \
  -d '{"reason":"integration test","start_date":"2099-01-02","end_date":"2099-01-02","leave_type_id":1}' \
  "$BASE_URL/backend/api/leaves.php?action=apply")
test "$STATUS" = "200"

rm -f "/tmp/ekaramchari-csrf-response-$.json" "/tmp/ekaramchari-leave-response-$.json"

echo "[9/11] Testing admin login"
curl -fsS -c "$ADMIN_COOKIE_FILE" "$BASE_URL/backend/api/auth.php?action=csrf" | grep -q '"success":true'
ADMIN_LOGIN=$(curl -fsS -b "$ADMIN_COOKIE_FILE" -c "$ADMIN_COOKIE_FILE" \
  -H 'Content-Type: application/json' \
  -d '{"employee_id":"'"$ADMIN_ID"'","password":"'"$ADMIN_PASSWORD"'"}' \
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
ADMIN_STATUS=$(curl -sS -o "/tmp/ekaramchari-admin-csrf-response-$.json" -w '%{http_code}' \
  -b "$ADMIN_COOKIE_FILE" -H 'Content-Type: application/json' \
  -d '{"key":"integration_test","value":"denied"}' \
  "$BASE_URL/backend/api/settings.php?action=update")
test "$ADMIN_STATUS" = "403"

rm -f "/tmp/ekaramchari-admin-csrf-response-$.json"
echo "Integration tests passed."
