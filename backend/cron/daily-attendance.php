<?php
/**
 * Daily Attendance Cron Job
 * Run this script daily at end of day (e.g., 23:00) to auto-mark attendance
 * 
 * Cron example (run at 11 PM daily):
 * 0 23 * * * php /path/to/backend/cron/daily-attendance.php
 * 
 * Or for Windows Task Scheduler:
 * php C:\xampp\htdocs\e-Karamchari-main\backend\cron\daily-attendance.php
 */

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

echo "=== Daily Attendance Auto-Mark ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = Database::getInstance();
    $today = date('Y-m-d');
    
    // Check if it's a holiday
    $holiday = $db->fetch(
        "SELECT * FROM holidays WHERE holiday_date = :date AND is_active = 1",
        ['date' => $today]
    );
    
    if ($holiday) {
        echo "Skipped - Today is a holiday: {$holiday['holiday_name']}\n";
        exit(0);
    }
    
    // Check if it's Sunday (only Sunday is off)
    $dayOfWeek = date('w');
    if ($dayOfWeek == 0) {
        echo "Skipped - Today is Sunday (Weekly Off)\n";
        exit(0);
    }
    
    // Get all active employees (including admins)
    $employees = $db->fetchAll(
        "SELECT u.id, u.employee_id, CONCAT(u.first_name, ' ', u.last_name) as name
         FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE r.role_code IN ('EMPLOYEE', 'ADMIN', 'SUPER_ADMIN', 'OFFICER') AND u.is_active = 1"
    );
    
    echo "Processing " . count($employees) . " employees...\n\n";
    
    $stats = [
        'absent' => 0,
        'auto_checkout' => 0,
        'present' => 0,
        'half_day' => 0,
        'already_done' => 0
    ];
    
    foreach ($employees as $emp) {
        $attendance = $db->fetch(
            "SELECT * FROM attendance WHERE employee_id = :emp_id AND attendance_date = :date",
            ['emp_id' => $emp['id'], 'date' => $today]
        );
        
        if (!$attendance) {
            // No check-in - mark as Absent
            $db->insert('attendance', [
                'employee_id' => $emp['id'],
                'attendance_date' => $today,
                'status' => 'Absent',
                'remarks' => 'Auto-marked absent (no check-in)'
            ]);
            echo "[ABSENT] {$emp['name']} ({$emp['employee_id']}) - No check-in\n";
            $stats['absent']++;
            
        } else if ($attendance['check_in_time'] && !$attendance['check_out_time']) {
            // Checked in but no checkout - auto checkout at 5 PM
            $checkOutTime = '17:00:00';
            $checkIn = strtotime($attendance['check_in_time']);
            $checkOut = strtotime($checkOutTime);
            $workHours = round(($checkOut - $checkIn) / 3600, 2);
            
            // 6+ hours = Present, less = Half Day
            $status = $workHours >= 6 ? 'Present' : 'Half Day';
            
            $db->update('attendance', [
                'check_out_time' => $checkOutTime,
                'work_hours' => $workHours,
                'status' => $status,
                'remarks' => 'Auto checkout at 17:00'
            ], 'id = :id', ['id' => $attendance['id']]);
            
            echo "[AUTO-CHECKOUT] {$emp['name']} ({$emp['employee_id']}) - {$workHours} hrs - {$status}\n";
            $stats['auto_checkout']++;
            if ($status === 'Present') $stats['present']++;
            else $stats['half_day']++;
            
        } else {
            // Already properly marked
            echo "[OK] {$emp['name']} ({$emp['employee_id']}) - Already marked: {$attendance['status']}\n";
            $stats['already_done']++;
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Absent marked: {$stats['absent']}\n";
    echo "Auto checkout: {$stats['auto_checkout']} (Present: {$stats['present']}, Half Day: {$stats['half_day']})\n";
    echo "Already done: {$stats['already_done']}\n";
    echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
    
    // Log to activity
    $db->insert('activity_logs', [
        'user_id' => null,
        'action' => 'CRON_AUTO_ATTENDANCE',
        'module' => 'ATTENDANCE',
        'description' => "Auto-marked: {$stats['absent']} absent, {$stats['auto_checkout']} auto-checkout",
        'ip_address' => 'CRON'
    ]);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
