<?php
/**
 * e-Karamchari Attendance Management API
 * Handles attendance tracking and reports
 */

require_once __DIR__ . '/../middleware/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        getAttendanceRecords();
        break;
    case 'my-attendance':
        getMyAttendance();
        break;
    case 'check-in':
        checkIn();
        break;
    case 'check-out':
        checkOut();
        break;
    case 'admin-checkout':
        adminCheckOut();
        break;
    case 'today':
        getTodayAttendance();
        break;
    case 'mark':
        markAttendance();
        break;
    case 'report':
        getAttendanceReport();
        break;
    case 'summary':
        getAttendanceSummary();
        break;
    case 'stats':
        getAttendanceStats();
        break;
    case 'holidays':
        getHolidays();
        break;
    case 'auto-mark':
        autoMarkAttendance();
        break;
    case 'finalize-day':
        finalizeDayAttendance();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Get attendance records (Admin only)
 * Automatically marks absent/finalizes for past dates
 */
function getAttendanceRecords() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(MAX_PAGE_SIZE, max(1, (int)($_GET['limit'] ?? 100)));
    $offset = ($page - 1) * $limit;
    
    $date = sanitize($_GET['date'] ?? date('Y-m-d'));
    $department = sanitize($_GET['department'] ?? '');
    $status = sanitize($_GET['status'] ?? '');
    
    // Auto-mark attendance for past dates (not today, not future, not Sunday, not holidays)
    $today = date('Y-m-d');
    $dayOfWeek = date('w', strtotime($date));
    $isWeekend = ($dayOfWeek == 0); // Only Sunday is off
    $isPast = strtotime($date) < strtotime($today);
    
    // Check if holiday
    $holiday = $db->fetch("SELECT * FROM holidays WHERE holiday_date = :date AND is_active = 1", ['date' => $date]);
    $isHoliday = $holiday ? true : false;
    
    // Auto-mark for past working days
    if ($isPast && !$isWeekend && !$isHoliday) {
        autoMarkForDate($db, $date);
    }
    
    // Get all active employees with their attendance
    $where = "r.role_code IN ('EMPLOYEE', 'ADMIN', 'SUPER_ADMIN', 'OFFICER') AND u.is_active = 1";
    $params = ['date' => $date];
    
    if ($department) {
        $where .= " AND u.department_id = :department";
        $params['department'] = $department;
    }
    
    // Determine default status based on date type
    $defaultStatus = 'Pending'; // Default for today until 5 PM
    if ($isWeekend) {
        $defaultStatus = 'Weekend';
    } else if ($isHoliday) {
        $defaultStatus = 'Holiday';
    } else if ($isPast) {
        $defaultStatus = 'Absent'; // Past dates without check-in = Absent
    }
    
    // Get all employees with their attendance for the date
    $sql = "SELECT 
                u.id as user_id,
                u.employee_id as emp_code,
                CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                COALESCE(d.dept_name, 'N/A') as dept_name,
                COALESCE(des.designation_name, 'N/A') as designation_name,
                a.id,
                a.attendance_date,
                a.check_in_time,
                a.check_out_time,
                a.work_hours,
                a.overtime_hours,
                COALESCE(a.status, :default_status) as status,
                a.remarks
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN designations des ON u.designation_id = des.id
            LEFT JOIN attendance a ON u.id = a.employee_id AND a.attendance_date = :date
            WHERE {$where}
            ORDER BY u.first_name, u.last_name
            LIMIT {$limit} OFFSET {$offset}";
    
    $params['default_status'] = $defaultStatus;
    $records = $db->fetchAll($sql, $params);
    
    // Filter by status if specified
    if ($status) {
        $records = array_filter($records, function($r) use ($status) {
            return $r['status'] === $status;
        });
        $records = array_values($records);
    }
    
    // Count total
    $countParams = [];
    if ($department) {
        $countParams['department'] = $department;
    }
    $countWhere = "r.role_code IN ('EMPLOYEE', 'ADMIN', 'SUPER_ADMIN', 'OFFICER') AND u.is_active = 1";
    if ($department) {
        $countWhere .= " AND u.department_id = :department";
    }
    $countSql = "SELECT COUNT(*) as total FROM users u JOIN roles r ON u.role_id = r.id WHERE {$countWhere}";
    $total = $db->fetch($countSql, $countParams)['total'];
    
    successResponse([
        'attendance' => $records ?: [],
        'date' => $date,
        'is_holiday' => $isHoliday,
        'holiday_name' => $holiday['holiday_name'] ?? null,
        'is_weekend' => $isWeekend,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Auto mark attendance for a specific date (internal function)
 * Called automatically when viewing past dates
 */
function autoMarkForDate($db, $date) {
    // Get all active employees (including admins)
    $employees = $db->fetchAll(
        "SELECT u.id FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE r.role_code IN ('EMPLOYEE', 'ADMIN', 'SUPER_ADMIN', 'OFFICER') AND u.is_active = 1"
    );
    
    foreach ($employees as $emp) {
        $attendance = $db->fetch(
            "SELECT * FROM attendance WHERE employee_id = :emp_id AND attendance_date = :date",
            ['emp_id' => $emp['id'], 'date' => $date]
        );
        
        if (!$attendance) {
            // No check-in between 8 AM - 5 PM = Absent
            $db->insert('attendance', [
                'employee_id' => $emp['id'],
                'attendance_date' => $date,
                'status' => 'Absent',
                'remarks' => 'Auto-marked absent (no check-in)'
            ]);
        } else if ($attendance['check_in_time'] && !$attendance['check_out_time']) {
            // Checked in but didn't checkout - auto checkout at 17:00 (5 PM)
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
        }
    }
}

/**
 * Get current user's attendance
 */
function getMyAttendance() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    $month = (int)($_GET['month'] ?? date('m'));
    $year = (int)($_GET['year'] ?? date('Y'));
    
    $sql = "SELECT a.*, 
                   CASE WHEN h.id IS NOT NULL THEN h.holiday_name ELSE NULL END as holiday_name
            FROM attendance a
            LEFT JOIN holidays h ON a.attendance_date = h.holiday_date
            WHERE a.employee_id = :user_id 
            AND MONTH(a.attendance_date) = :month 
            AND YEAR(a.attendance_date) = :year
            ORDER BY a.attendance_date DESC";
    
    $records = $db->fetchAll($sql, [
        'user_id' => $userId,
        'month' => $month,
        'year' => $year
    ]);
    
    // Get summary
    $summary = $db->fetch(
        "SELECT 
            COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
            COUNT(CASE WHEN status = 'Half Day' THEN 1 END) as half_day,
            COUNT(CASE WHEN status = 'On Leave' THEN 1 END) as on_leave,
            SUM(work_hours) as total_hours,
            SUM(overtime_hours) as total_overtime
         FROM attendance
         WHERE employee_id = :user_id 
         AND MONTH(attendance_date) = :month 
         AND YEAR(attendance_date) = :year",
        ['user_id' => $userId, 'month' => $month, 'year' => $year]
    );
    
    successResponse([
        'attendance' => $records,
        'summary' => $summary,
        'month' => $month,
        'year' => $year
    ]);
}

/**
 * Check in
 */
function checkIn() {
    Auth::requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    $today = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    // Check if Sunday (weekly off)
    $dayOfWeek = date('w');
    if ($dayOfWeek == 0) {
        errorResponse('आज Sunday है - Weekly Off। Check-in नहीं हो सकता।');
    }
    
    // Check if holiday
    $holiday = $db->fetch(
        "SELECT * FROM holidays WHERE holiday_date = :date AND is_active = 1",
        ['date' => $today]
    );
    if ($holiday) {
        errorResponse("आज {$holiday['holiday_name']} की छुट्टी है। Check-in नहीं हो सकता।");
    }
    
    // Time restriction only for employees (8 AM - 5 PM), Admin can check-in anytime
    $isAdmin = Auth::isAdmin();
    if (!$isAdmin) {
        if (strtotime($currentTime) < strtotime('08:00:00')) {
            errorResponse('Check-in सुबह 8:00 बजे से शुरू होता है।');
        }
        if (strtotime($currentTime) > strtotime('17:00:00')) {
            errorResponse('Check-in का समय समाप्त हो गया है (शाम 5:00 बजे तक)।');
        }
    }
    
    // Check if already checked in
    $existing = $db->fetch(
        "SELECT * FROM attendance WHERE employee_id = :user_id AND attendance_date = :date",
        ['user_id' => $userId, 'date' => $today]
    );
    
    if ($existing && $existing['check_in_time']) {
        errorResponse('Already checked in today');
    }
    
    $checkInTime = date('H:i:s');
    $status = 'Present'; // Always mark Present on check-in, status will be updated on checkout based on work hours
    
    if ($existing) {
        $db->update('attendance', [
            'check_in_time' => $checkInTime,
            'status' => $status,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ], 'id = :id', ['id' => $existing['id']]);
    } else {
        $db->insert('attendance', [
            'employee_id' => $userId,
            'attendance_date' => $today,
            'check_in_time' => $checkInTime,
            'status' => $status,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
    
    logActivity($userId, 'CHECK_IN', 'ATTENDANCE', "Checked in at {$checkInTime}");
    
    successResponse([
        'check_in_time' => $checkInTime,
        'status' => $status
    ], 'Checked in successfully');
}

/**
 * Check out
 */
function checkOut() {
    Auth::requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    $today = date('Y-m-d');
    
    $attendance = $db->fetch(
        "SELECT * FROM attendance WHERE employee_id = :user_id AND attendance_date = :date",
        ['user_id' => $userId, 'date' => $today]
    );
    
    if (!$attendance || !$attendance['check_in_time']) {
        errorResponse('Please check in first');
    }
    
    if ($attendance['check_out_time']) {
        errorResponse('Already checked out today');
    }
    
    $checkOutTime = date('H:i:s');
    
    // Calculate work hours
    $checkIn = strtotime($attendance['check_in_time']);
    $checkOut = strtotime($checkOutTime);
    $workHours = round(($checkOut - $checkIn) / 3600, 2);
    
    // Calculate overtime (after 8 hours)
    $overtimeHours = max(0, $workHours - 8);
    
    // Auto-determine status based on work hours
    // Less than 6 hours = Half Day
    // 6 hours or more = Present
    if ($workHours >= 6) {
        $status = 'Present';
    } else if ($workHours >= 4) {
        $status = 'Half Day';
    } else {
        $status = 'Half Day'; // Even less than 4 hours counts as half day if they showed up
    }
    
    $db->update('attendance', [
        'check_out_time' => $checkOutTime,
        'work_hours' => $workHours,
        'overtime_hours' => $overtimeHours,
        'status' => $status
    ], 'id = :id', ['id' => $attendance['id']]);
    
    logActivity($userId, 'CHECK_OUT', 'ATTENDANCE', "Checked out at {$checkOutTime}, worked {$workHours} hrs, status: {$status}");
    
    successResponse([
        'check_out_time' => $checkOutTime,
        'work_hours' => $workHours,
        'overtime_hours' => $overtimeHours,
        'status' => $status
    ], 'Checked out successfully');
}

/**
 * Get today's attendance status
 */
function getTodayAttendance() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    $today = date('Y-m-d');
    
    $attendance = $db->fetch(
        "SELECT * FROM attendance WHERE employee_id = :user_id AND attendance_date = :date",
        ['user_id' => $userId, 'date' => $today]
    );
    
    // Check if today is holiday
    $holiday = $db->fetch(
        "SELECT * FROM holidays WHERE holiday_date = :date",
        ['date' => $today]
    );
    
    successResponse([
        'date' => $today,
        'attendance' => $attendance,
        'is_holiday' => $holiday ? true : false,
        'holiday' => $holiday
    ]);
}

/**
 * Mark attendance (Admin only)
 */
function markAttendance() {
    Auth::requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $employeeId = (int)($input['employee_id'] ?? 0);
    $date = sanitize($input['date'] ?? '');
    $status = sanitize($input['status'] ?? '');
    
    if (!$employeeId || !$date || !$status) {
        errorResponse('Employee ID, date, and status are required');
    }
    
    $validStatuses = ['Present', 'Absent', 'Half Day', 'On Leave', 'Holiday', 'Weekend'];
    if (!in_array($status, $validStatuses)) {
        errorResponse('Invalid status');
    }
    
    $db = Database::getInstance();
    
    $existing = $db->fetch(
        "SELECT id FROM attendance WHERE employee_id = :emp_id AND attendance_date = :date",
        ['emp_id' => $employeeId, 'date' => $date]
    );
    
    $data = [
        'status' => $status,
        'check_in_time' => $input['check_in_time'] ?? null,
        'check_out_time' => $input['check_out_time'] ?? null,
        'work_hours' => $input['work_hours'] ?? 0,
        'remarks' => sanitize($input['remarks'] ?? '')
    ];
    
    if ($existing) {
        $db->update('attendance', $data, 'id = :id', ['id' => $existing['id']]);
    } else {
        $data['employee_id'] = $employeeId;
        $data['attendance_date'] = $date;
        $db->insert('attendance', $data);
    }
    
    logActivity($_SESSION['user_id'], 'MARK_ATTENDANCE', 'ATTENDANCE', 
               "Marked attendance for employee {$employeeId} on {$date}");
    
    successResponse([], 'Attendance marked successfully');
}

/**
 * Get attendance report (Admin only)
 */
function getAttendanceReport() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    
    $startDate = sanitize($_GET['start_date'] ?? date('Y-m-01'));
    $endDate = sanitize($_GET['end_date'] ?? date('Y-m-d'));
    $department = sanitize($_GET['department'] ?? '');
    
    $where = "a.attendance_date BETWEEN :start AND :end";
    $params = ['start' => $startDate, 'end' => $endDate];
    
    if ($department) {
        $where .= " AND u.department_id = :department";
        $params['department'] = $department;
    }
    
    $sql = "SELECT 
                u.employee_id as emp_code,
                CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                d.dept_name,
                COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_days,
                COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_days,
                COUNT(CASE WHEN a.status = 'Half Day' THEN 1 END) as half_days,
                COUNT(CASE WHEN a.status = 'On Leave' THEN 1 END) as leave_days,
                SUM(a.work_hours) as total_hours,
                SUM(a.overtime_hours) as overtime_hours
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN attendance a ON u.id = a.employee_id AND {$where}
            WHERE r.role_code = 'EMPLOYEE' AND u.is_active = 1
            GROUP BY u.id
            ORDER BY u.first_name";
    
    $report = $db->fetchAll($sql, $params);
    
    successResponse([
        'report' => $report,
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]
    ]);
}

/**
 * Get attendance summary for dashboard
 */
function getAttendanceSummary() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $today = date('Y-m-d');
    
    if (Auth::isAdmin()) {
        // Admin summary
        $totalEmployees = $db->count('users u JOIN roles r ON u.role_id = r.id', 
                                     "r.role_code = 'EMPLOYEE' AND u.is_active = 1");
        
        $presentToday = $db->count('attendance', 
                                   "attendance_date = :date AND status IN ('Present', 'Half Day')",
                                   ['date' => $today]);
        
        $onLeaveToday = $db->count('attendance',
                                   "attendance_date = :date AND status = 'On Leave'",
                                   ['date' => $today]);
        
        successResponse([
            'total_employees' => $totalEmployees,
            'present_today' => $presentToday,
            'absent_today' => $totalEmployees - $presentToday - $onLeaveToday,
            'on_leave_today' => $onLeaveToday,
            'date' => $today
        ]);
    } else {
        // Employee summary
        $userId = $_SESSION['user_id'];
        $month = date('m');
        $year = date('Y');
        
        $summary = $db->fetch(
            "SELECT 
                COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
                COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
                COUNT(CASE WHEN status = 'Half Day' THEN 1 END) as half_day,
                COUNT(CASE WHEN status = 'On Leave' THEN 1 END) as on_leave
             FROM attendance
             WHERE employee_id = :user_id 
             AND MONTH(attendance_date) = :month 
             AND YEAR(attendance_date) = :year",
            ['user_id' => $userId, 'month' => $month, 'year' => $year]
        );
        
        $todayStatus = $db->fetch(
            "SELECT * FROM attendance WHERE employee_id = :user_id AND attendance_date = :date",
            ['user_id' => $userId, 'date' => $today]
        );
        
        successResponse([
            'monthly_summary' => $summary,
            'today' => $todayStatus,
            'month' => $month,
            'year' => $year
        ]);
    }
}

/**
 * Get attendance statistics (Admin only)
 */
function getAttendanceStats() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    
    $weeklyTrend = $db->fetchAll(
        "SELECT attendance_date, 
                COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
                COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent
         FROM attendance
         WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY attendance_date
         ORDER BY attendance_date"
    );
    
    $departmentWise = $db->fetchAll(
        "SELECT d.dept_name,
                COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present,
                COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent
         FROM departments d
         LEFT JOIN users u ON d.id = u.department_id
         LEFT JOIN attendance a ON u.id = a.employee_id AND a.attendance_date = CURDATE()
         GROUP BY d.id"
    );
    
    successResponse([
        'weekly_trend' => $weeklyTrend,
        'by_department' => $departmentWise
    ]);
}

/**
 * Get holidays
 */
function getHolidays() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $year = (int)($_GET['year'] ?? date('Y'));
    
    $holidays = $db->fetchAll(
        "SELECT * FROM holidays 
         WHERE YEAR(holiday_date) = :year AND is_active = 1
         ORDER BY holiday_date",
        ['year' => $year]
    );
    
    successResponse($holidays);
}

/**
 * Admin mark check-out for any employee
 */
function adminCheckOut() {
    Auth::requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    
    if (!$id) {
        errorResponse('Attendance ID is required');
    }
    
    $db = Database::getInstance();
    
    $attendance = $db->fetch("SELECT * FROM attendance WHERE id = :id", ['id' => $id]);
    
    if (!$attendance) {
        errorResponse('Attendance record not found', 404);
    }
    
    if ($attendance['check_out_time']) {
        errorResponse('Already checked out');
    }
    
    $checkOutTime = date('H:i:s');
    $checkInTime = $attendance['check_in_time'];
    
    // Calculate work hours
    $workHours = 0;
    if ($checkInTime) {
        $in = strtotime($checkInTime);
        $out = strtotime($checkOutTime);
        $workHours = round(($out - $in) / 3600, 2);
    }
    
    $db->update('attendance', [
        'check_out_time' => $checkOutTime,
        'work_hours' => $workHours
    ], 'id = :id', ['id' => $id]);
    
    logActivity($_SESSION['user_id'], 'ADMIN_CHECKOUT', 'ATTENDANCE', "Marked check-out for attendance ID: {$id}");
    
    successResponse(['work_hours' => $workHours], 'Check-out marked successfully');
}


/**
 * Auto mark attendance for a specific date
 * - Marks absent for employees who didn't check in
 * - Auto checkout and set status for those who forgot to checkout
 * Can be called via cron job or manually by admin
 */
function autoMarkAttendance() {
    // Allow both admin call and cron job (no auth for cron)
    $isCron = isset($_GET['cron_key']) && $_GET['cron_key'] === 'your_secret_cron_key_here';
    
    if (!$isCron) {
        Auth::requireAdmin();
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$isCron) {
        errorResponse('Method not allowed', 405);
    }
    
    $input = $isCron ? [] : json_decode(file_get_contents('php://input'), true);
    $date = sanitize($input['date'] ?? $_GET['date'] ?? date('Y-m-d', strtotime('-1 day')));
    
    $db = Database::getInstance();
    
    // Check if it's a holiday
    $holiday = $db->fetch("SELECT * FROM holidays WHERE holiday_date = :date AND is_active = 1", ['date' => $date]);
    if ($holiday) {
        successResponse(['message' => "Skipped - {$date} is a holiday: {$holiday['holiday_name']}"]);
        return;
    }
    
    // Check if it's a weekend (Only Sunday=0 is off)
    $dayOfWeek = date('w', strtotime($date));
    if ($dayOfWeek == 0) {
        successResponse(['message' => "Skipped - {$date} is Sunday"]);
        return;
    }
    
    // Get all active employees (including admins)
    $employees = $db->fetchAll(
        "SELECT u.id, u.employee_id, CONCAT(u.first_name, ' ', u.last_name) as name
         FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE r.role_code IN ('EMPLOYEE', 'ADMIN', 'SUPER_ADMIN', 'OFFICER') AND u.is_active = 1"
    );
    
    $absentMarked = 0;
    $halfDayMarked = 0;
    $presentMarked = 0;
    $autoCheckout = 0;
    $skipped = 0;
    
    foreach ($employees as $emp) {
        // Check existing attendance
        $attendance = $db->fetch(
            "SELECT * FROM attendance WHERE employee_id = :emp_id AND attendance_date = :date",
            ['emp_id' => $emp['id'], 'date' => $date]
        );
        
        if (!$attendance) {
            // No record - mark as Absent
            $db->insert('attendance', [
                'employee_id' => $emp['id'],
                'attendance_date' => $date,
                'status' => 'Absent',
                'remarks' => 'Auto-marked absent (no check-in)'
            ]);
            $absentMarked++;
        } else if ($attendance['check_in_time'] && !$attendance['check_out_time']) {
            // Checked in but didn't checkout - auto checkout at 18:00
            $checkOutTime = '18:00:00';
            $checkIn = strtotime($attendance['check_in_time']);
            $checkOut = strtotime($checkOutTime);
            $workHours = round(($checkOut - $checkIn) / 3600, 2);
            
            // Determine status based on work hours
            $status = $workHours >= 6 ? 'Present' : 'Half Day';
            
            $db->update('attendance', [
                'check_out_time' => $checkOutTime,
                'work_hours' => $workHours,
                'status' => $status,
                'remarks' => 'Auto checkout at 18:00'
            ], 'id = :id', ['id' => $attendance['id']]);
            
            $autoCheckout++;
            if ($status === 'Present') $presentMarked++;
            else $halfDayMarked++;
        } else if ($attendance['status'] === 'Present' || $attendance['status'] === 'Half Day') {
            // Already properly marked
            $skipped++;
        } else {
            $skipped++;
        }
    }
    
    $message = "Auto-marked for {$date}: {$absentMarked} absent, {$autoCheckout} auto-checkout ({$presentMarked} present, {$halfDayMarked} half-day), {$skipped} skipped";
    
    if (!$isCron) {
        logActivity($_SESSION['user_id'], 'AUTO_MARK_ATTENDANCE', 'ATTENDANCE', $message);
    }
    
    successResponse([
        'date' => $date,
        'absent_marked' => $absentMarked,
        'auto_checkout' => $autoCheckout,
        'present_marked' => $presentMarked,
        'half_day_marked' => $halfDayMarked,
        'skipped' => $skipped,
        'message' => $message
    ]);
}

/**
 * Finalize attendance for previous day
 * Called at end of day to process all pending attendance
 */
function finalizeDayAttendance() {
    Auth::requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $date = sanitize($input['date'] ?? date('Y-m-d'));
    
    $db = Database::getInstance();
    
    // Get all attendance records for the date that need finalization
    $records = $db->fetchAll(
        "SELECT a.*, u.employee_id as emp_code, CONCAT(u.first_name, ' ', u.last_name) as name
         FROM attendance a
         JOIN users u ON a.employee_id = u.id
         WHERE a.attendance_date = :date AND a.check_in_time IS NOT NULL AND a.check_out_time IS NULL",
        ['date' => $date]
    );
    
    $processed = 0;
    
    foreach ($records as $record) {
        // Auto checkout at current time or 18:00 if past that
        $now = date('H:i:s');
        $checkOutTime = (strtotime($now) > strtotime('18:00:00')) ? '18:00:00' : $now;
        
        $checkIn = strtotime($record['check_in_time']);
        $checkOut = strtotime($checkOutTime);
        $workHours = round(($checkOut - $checkIn) / 3600, 2);
        
        // Determine status: 6+ hours = Present, less = Half Day
        $status = $workHours >= 6 ? 'Present' : 'Half Day';
        
        $db->update('attendance', [
            'check_out_time' => $checkOutTime,
            'work_hours' => $workHours,
            'status' => $status,
            'remarks' => 'Finalized by admin'
        ], 'id = :id', ['id' => $record['id']]);
        
        $processed++;
    }
    
    logActivity($_SESSION['user_id'], 'FINALIZE_ATTENDANCE', 'ATTENDANCE', 
               "Finalized {$processed} attendance records for {$date}");
    
    successResponse([
        'date' => $date,
        'processed' => $processed
    ], "Finalized {$processed} attendance records");
}
