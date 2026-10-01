<?php
/**
 * Regression tests for login-triggered attendance behavior.
 */

require_once __DIR__ . '/../backend/middleware/auth.php';

$cases = [
    ['EMPLOYEE', '07:59:59', false, false, false],
    ['EMPLOYEE', '08:00:00', false, false, true],
    ['EMPLOYEE', '17:00:00', false, false, true],
    ['EMPLOYEE', '17:00:01', false, false, false],
    ['EMPLOYEE', '12:00:00', true, false, false],
    ['EMPLOYEE', '12:00:00', false, true, false],
    ['ADMIN', '06:00:00', false, false, true],
    ['OFFICER', '20:00:00', false, false, true],
];

foreach ($cases as [$role, $time, $isSunday, $isHoliday, $expected]) {
    $actual = Auth::shouldAutoMarkAttendance($role, $time, $isSunday, $isHoliday);
    if ($actual !== $expected) {
        fwrite(STDERR, "Attendance regression failed for {$role} at {$time}\n");
        exit(1);
    }
}

echo "Attendance auto-check-in regression tests passed.\n";
