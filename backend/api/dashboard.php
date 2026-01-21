<?php
/**
 * e-Karamchari Dashboard API
 * Provides dashboard statistics and data
 */

require_once __DIR__ . '/../middleware/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? 'stats';

switch ($action) {
    case 'admin-stats':
        getAdminDashboardStats();
        break;
    case 'employee-stats':
        getEmployeeDashboardStats();
        break;
    case 'notifications':
        getNotifications();
        break;
    case 'mark-read':
        markNotificationRead();
        break;
    case 'recent-activity':
        getRecentActivity();
        break;
    case 'test-notification':
        createTestNotification();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Get admin dashboard statistics
 */
function getAdminDashboardStats() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    $today = date('Y-m-d');
    
    // Check if today is Sunday or Holiday
    $dayOfWeek = date('w');
    $isSunday = ($dayOfWeek == 0);
    
    $holiday = $db->fetch(
        "SELECT * FROM holidays WHERE holiday_date = :date AND is_active = 1",
        ['date' => $today]
    );
    $isHoliday = $holiday ? true : false;
    $holidayName = $holiday['holiday_name'] ?? null;
    
    // Employee counts (including admins)
    $totalEmployees = $db->count('users u JOIN roles r ON u.role_id = r.id', 
                                 "r.role_code IN ('EMPLOYEE', 'ADMIN', 'SUPER_ADMIN', 'OFFICER')");
    $activeEmployees = $db->count('users u JOIN roles r ON u.role_id = r.id', 
                                  "r.role_code IN ('EMPLOYEE', 'ADMIN', 'SUPER_ADMIN', 'OFFICER') AND u.is_active = 1");
    
    // Today's attendance (only if working day)
    $presentToday = 0;
    if (!$isSunday && !$isHoliday) {
        $presentToday = $db->count('attendance', 
                                   "attendance_date = :date AND status IN ('Present', 'Half Day')",
                                   ['date' => $today]);
    }
    
    // Pending leaves
    $pendingLeaves = $db->count('leave_requests', "status = 'Pending'");
    
    // Open grievances
    $openGrievances = $db->count('grievances', "status IN ('Open', 'In Progress')");
    
    // Recent leave requests
    $recentLeaves = $db->fetchAll(
        "SELECT lr.id, lr.request_number, lr.start_date, lr.end_date, lr.status,
                CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                lt.leave_name
         FROM leave_requests lr
         JOIN users u ON lr.employee_id = u.id
         JOIN leave_types lt ON lr.leave_type_id = lt.id
         WHERE lr.status = 'Pending'
         ORDER BY lr.created_at DESC
         LIMIT 5"
    );
    
    // Recent grievances
    $recentGrievances = $db->fetchAll(
        "SELECT g.id, g.grievance_number, g.subject, g.priority, g.status,
                CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                gc.category_name
         FROM grievances g
         JOIN users u ON g.employee_id = u.id
         JOIN grievance_categories gc ON g.category_id = gc.id
         WHERE g.status IN ('Open', 'In Progress')
         ORDER BY 
            CASE g.priority 
                WHEN 'Critical' THEN 1 
                WHEN 'High' THEN 2 
                WHEN 'Medium' THEN 3 
                ELSE 4 
            END,
            g.created_at DESC
         LIMIT 5"
    );
    
    // Department-wise employee count
    $departmentStats = $db->fetchAll(
        "SELECT d.dept_name, COUNT(u.id) as count
         FROM departments d
         LEFT JOIN users u ON d.id = u.department_id
         JOIN roles r ON u.role_id = r.id
         WHERE r.role_code IN ('EMPLOYEE', 'ADMIN', 'SUPER_ADMIN', 'OFFICER') AND u.is_active = 1
         GROUP BY d.id
         ORDER BY count DESC
         LIMIT 5"
    );
    
    // Weekly attendance trend (include all days with Sunday/Holiday info)
    $attendanceTrend = [];
    $startDate = date('Y-m-d', strtotime('-6 days'));
    $endDate = date('Y-m-d');
    
    // Get all attendance data for the week
    $attendanceData = $db->fetchAll(
        "SELECT DATE(attendance_date) as date,
                COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
                COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent
         FROM attendance
         WHERE attendance_date BETWEEN :start AND :end
         GROUP BY DATE(attendance_date)",
        ['start' => $startDate, 'end' => $endDate]
    );
    
    // Index by date
    $attendanceByDate = [];
    foreach ($attendanceData as $row) {
        $attendanceByDate[$row['date']] = $row;
    }
    
    // Get holidays for the week
    $holidays = $db->fetchAll(
        "SELECT holiday_date, holiday_name FROM holidays 
         WHERE holiday_date BETWEEN :start AND :end AND is_active = 1",
        ['start' => $startDate, 'end' => $endDate]
    );
    $holidaysByDate = [];
    foreach ($holidays as $h) {
        $holidaysByDate[$h['holiday_date']] = $h['holiday_name'];
    }
    
    // Build trend for each day
    $current = strtotime($startDate);
    $end = strtotime($endDate);
    while ($current <= $end) {
        $dateStr = date('Y-m-d', $current);
        $dayOfWeek = date('w', $current);
        $isSunday = ($dayOfWeek == 0);
        $isHoliday = isset($holidaysByDate[$dateStr]);
        
        $dayData = [
            'date' => $dateStr,
            'present' => 0,
            'absent' => 0,
            'is_sunday' => $isSunday,
            'is_holiday' => $isHoliday,
            'holiday_name' => $isHoliday ? $holidaysByDate[$dateStr] : null,
            'is_off' => $isSunday || $isHoliday
        ];
        
        if (!$isSunday && !$isHoliday && isset($attendanceByDate[$dateStr])) {
            $dayData['present'] = (int)$attendanceByDate[$dateStr]['present'];
            $dayData['absent'] = (int)$attendanceByDate[$dateStr]['absent'];
        }
        
        $attendanceTrend[] = $dayData;
        $current = strtotime('+1 day', $current);
    }
    
    successResponse([
        'counts' => [
            'total_employees' => $totalEmployees,
            'active_employees' => $activeEmployees,
            'present_today' => $presentToday,
            'pending_leaves' => $pendingLeaves,
            'open_grievances' => $openGrievances
        ],
        'today_info' => [
            'is_sunday' => $isSunday,
            'is_holiday' => $isHoliday,
            'holiday_name' => $holidayName,
            'is_working_day' => !$isSunday && !$isHoliday
        ],
        'recent_leaves' => $recentLeaves,
        'recent_grievances' => $recentGrievances,
        'department_stats' => $departmentStats,
        'attendance_trend' => $attendanceTrend
    ]);
}

/**
 * Get employee dashboard statistics
 */
function getEmployeeDashboardStats() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    $today = date('Y-m-d');
    $currentMonth = date('m');
    $currentYear = date('Y');
    
    // Today's attendance
    $todayAttendance = $db->fetch(
        "SELECT * FROM attendance WHERE employee_id = :user_id AND attendance_date = :date",
        ['user_id' => $userId, 'date' => $today]
    );
    
    // Monthly attendance summary
    $attendanceSummary = $db->fetch(
        "SELECT 
            COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
            COUNT(CASE WHEN status = 'Half Day' THEN 1 END) as half_day,
            COUNT(CASE WHEN status = 'On Leave' THEN 1 END) as on_leave
         FROM attendance
         WHERE employee_id = :user_id 
         AND MONTH(attendance_date) = :month 
         AND YEAR(attendance_date) = :year",
        ['user_id' => $userId, 'month' => $currentMonth, 'year' => $currentYear]
    );
    
    // Leave balance
    $leaveBalance = $db->fetchAll(
        "SELECT lt.leave_code, lt.leave_name,
                COALESCE(lb.total_allocated - lb.used + lb.carried_forward, lt.max_days_per_year) as available
         FROM leave_types lt
         LEFT JOIN leave_balance lb ON lt.id = lb.leave_type_id 
              AND lb.employee_id = :user_id AND lb.year = :year
         WHERE lt.is_active = 1
         ORDER BY lt.leave_name",
        ['user_id' => $userId, 'year' => $currentYear]
    );
    
    // Pending leave requests
    $pendingLeaves = $db->fetchAll(
        "SELECT lr.id, lr.request_number, lr.start_date, lr.end_date, lr.status, lt.leave_name
         FROM leave_requests lr
         JOIN leave_types lt ON lr.leave_type_id = lt.id
         WHERE lr.employee_id = :user_id AND lr.status = 'Pending'
         ORDER BY lr.created_at DESC
         LIMIT 3",
        ['user_id' => $userId]
    );
    
    // Recent grievances
    $recentGrievances = $db->fetchAll(
        "SELECT g.id, g.grievance_number, g.subject, g.status, gc.category_name
         FROM grievances g
         JOIN grievance_categories gc ON g.category_id = gc.id
         WHERE g.employee_id = :user_id
         ORDER BY g.created_at DESC
         LIMIT 3",
        ['user_id' => $userId]
    );
    
    // Upcoming holidays
    $upcomingHolidays = $db->fetchAll(
        "SELECT * FROM holidays 
         WHERE holiday_date >= CURDATE() AND is_active = 1
         ORDER BY holiday_date
         LIMIT 3"
    );
    
    // Unread notifications count
    $unreadNotifications = $db->count('notifications', 
                                      "user_id = :user_id AND is_read = 0",
                                      ['user_id' => $userId]);
    
    successResponse([
        'today_attendance' => $todayAttendance,
        'attendance_summary' => $attendanceSummary,
        'leave_balance' => $leaveBalance,
        'pending_leaves' => $pendingLeaves,
        'recent_grievances' => $recentGrievances,
        'upcoming_holidays' => $upcomingHolidays,
        'unread_notifications' => $unreadNotifications
    ]);
}

/**
 * Get user notifications
 */
function getNotifications() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
    
    // Only show unread notifications
    $notifications = $db->fetchAll(
        "SELECT * FROM notifications 
         WHERE user_id = :user_id AND is_read = 0
         ORDER BY created_at DESC
         LIMIT {$limit}",
        ['user_id' => $userId]
    );
    
    $unreadCount = $db->count('notifications', "user_id = :user_id AND is_read = 0", ['user_id' => $userId]);
    
    // Page-wise unread notification counts based on link field
    $pageWiseCounts = [
        'leave' => 0,
        'grievance' => 0,
        'attendance' => 0,
        'salary' => 0,
        'profile' => 0,
        'service' => 0
    ];
    
    foreach ($notifications as $n) {
        $link = strtolower($n['link'] ?? '');
        if (strpos($link, 'leave') !== false) {
            $pageWiseCounts['leave']++;
        } elseif (strpos($link, 'grievance') !== false) {
            $pageWiseCounts['grievance']++;
        } elseif (strpos($link, 'attendance') !== false) {
            $pageWiseCounts['attendance']++;
        } elseif (strpos($link, 'salary') !== false) {
            $pageWiseCounts['salary']++;
        } elseif (strpos($link, 'profile') !== false) {
            $pageWiseCounts['profile']++;
        } elseif (strpos($link, 'service') !== false) {
            $pageWiseCounts['service']++;
        }
    }
    
    successResponse([
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
        'page_counts' => $pageWiseCounts
    ]);
}

/**
 * Mark notification as read
 */
function markNotificationRead() {
    Auth::requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    $markAll = $input['mark_all'] ?? false;
    $deleteAfterRead = $input['delete'] ?? false;
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    if ($markAll) {
        if ($deleteAfterRead) {
            $db->query("DELETE FROM notifications WHERE user_id = :user_id AND is_read = 0", ['user_id' => $userId]);
        } else {
            $db->update('notifications', ['is_read' => 1], 'user_id = :user_id', ['user_id' => $userId]);
        }
    } elseif ($id) {
        if ($deleteAfterRead) {
            $db->query("DELETE FROM notifications WHERE id = :id AND user_id = :user_id", 
                      ['id' => $id, 'user_id' => $userId]);
        } else {
            $db->update('notifications', ['is_read' => 1], 'id = :id AND user_id = :user_id', 
                       ['id' => $id, 'user_id' => $userId]);
        }
    } else {
        errorResponse('Notification ID or mark_all flag required');
    }
    
    successResponse([], 'Notification(s) processed successfully');
}

/**
 * Get recent activity (Admin only)
 */
function getRecentActivity() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    
    $activities = $db->fetchAll(
        "SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
         FROM activity_logs al
         LEFT JOIN users u ON al.user_id = u.id
         ORDER BY al.created_at DESC
         LIMIT {$limit}"
    );
    
    successResponse($activities);
}

/**
 * Create test notification (for debugging)
 */
function createTestNotification() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    try {
        $notificationId = $db->insert('notifications', [
            'user_id' => $userId,
            'title' => 'Test Notification',
            'message' => 'This is a test notification created at ' . date('Y-m-d H:i:s'),
            'type' => 'Info',
            'link' => null,
            'is_read' => 0
        ]);
        
        successResponse([
            'notification_id' => $notificationId,
            'message' => 'Test notification created successfully'
        ]);
    } catch (Exception $e) {
        errorResponse('Failed to create test notification: ' . $e->getMessage());
    }
}

