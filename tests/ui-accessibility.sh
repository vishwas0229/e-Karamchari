#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

employee_pages=(
  frontend/employee-login.html
  frontend/employee/dashboard.html
  frontend/employee/apply-leave.html
  frontend/employee/leave-status.html
  frontend/employee/attendance.html
  frontend/employee/submit-grievance.html
  frontend/employee/grievance-status.html
  frontend/employee/profile.html
  frontend/employee/salary-slip.html
  frontend/employee/service-record.html
)

admin_pages=(
  frontend/admin-login.html
  frontend/admin/dashboard.html
  frontend/admin/add-employee.html
  frontend/admin/edit-employee.html
  frontend/admin/employees.html
  frontend/admin/employee-services.html
  frontend/admin/attendance.html
  frontend/admin/leave-approvals.html
  frontend/admin/grievances.html
  frontend/admin/profile.html
  frontend/admin/salary.html
  frontend/admin/service-records.html
  frontend/admin/reports.html
  frontend/admin/settings.html
)

system_pages=(
  frontend/employee/session-expired.html
  frontend/employee/unauthorized.html
  frontend/admin/session-expired.html
  frontend/admin/unauthorized.html
)

check_page() {
  local page="$1"
  test -f "$page" || { echo "Missing page: $page" >&2; exit 1; }

  grep -Eiq '<html[^>]+lang="[^"]+"' "$page" || {
    echo "Missing language declaration: $page" >&2
    exit 1
  }

  grep -Eiq '<meta[^>]+name="viewport"[^>]+content="width=device-width, initial-scale=1(\.0)?"' "$page" || {
    echo "Missing responsive viewport: $page" >&2
    exit 1
  }

  grep -Eiq '<title>[^<]+' "$page" || {
    echo "Missing document title: $page" >&2
    exit 1
  }
}

echo "Checking employee pages..."
for page in "${employee_pages[@]}"; do check_page "$page"; done

echo "Checking admin pages..."
for page in "${admin_pages[@]}"; do check_page "$page"; done

echo "Checking system pages..."
for page in "${system_pages[@]}"; do check_page "$page"; done

echo "Checking shared responsive/accessibility primitives..."
grep -q ':focus-visible' frontend/css/common.css
grep -q 'prefers-reduced-motion' frontend/css/common.css
grep -q 'prefers-contrast' frontend/css/common.css
grep -q '\.skip-link' frontend/css/common.css
grep -q '\.table-responsive' frontend/css/common.css
grep -q 'min-width: 44px' frontend/css/common.css

echo "Checking runtime accessibility enhancement..."
grep -q 'function initAccessibility' frontend/js/utils.js
grep -q 'Open navigation menu' frontend/js/utils.js
grep -q 'aria-haspopup' frontend/js/utils.js
grep -q 'Skip to main content' frontend/js/utils.js

echo "Checking login password controls..."
grep -q 'id="toggle-password" aria-label="Show password" aria-pressed="false"' frontend/employee-login.html
grep -q 'id="toggle-password" aria-label="Show password" aria-pressed="false"' frontend/admin-login.html
grep -q "setAttribute('aria-label', 'Hide password')" frontend/employee-login.html
grep -q "setAttribute('aria-label', 'Hide password')" frontend/admin-login.html

echo "Checking mobile-safe admin attendance layout..."
if grep -q 'flex-wrap: nowrap' frontend/admin/attendance.html; then
  echo "Admin attendance summary must not force nowrap on mobile." >&2
  exit 1
fi

echo "UI accessibility source checks passed."
