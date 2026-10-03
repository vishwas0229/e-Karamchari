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

if (ATTENDANCE_AUTO_CHECKOUT_TIME !== '18:00:00') {
    fwrite(STDERR, "Unexpected canonical attendance checkout time\n");
    exit(1);
}

$attendanceSource = file_get_contents(__DIR__ . '/../backend/api/attendance.php');
if (substr_count($attendanceSource, 'ATTENDANCE_AUTO_CHECKOUT_TIME') < 3) {
    fwrite(STDERR, "Attendance paths are not using the canonical checkout time\n");
    exit(1);
}

echo "Attendance auto-check-in regression tests passed.\n";
