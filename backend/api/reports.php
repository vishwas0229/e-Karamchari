<?php
/**
 * e-Karamchari Reports & Analytics API
 * Provides various reports and analytics data
 */

require_once __DIR__ . '/../middleware/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

Auth::requireAdmin();

$action = $_GET['action'] ?? 'overview';

switch ($action) {
    case 'overview':
        getOverviewReport();
        break;
    case 'monthly':
        getMonthlyReport();
        break;
    case 'attendance':
        getAttendanceReport();
        break;
    case 'leaves':
        getLeavesReport();
        break;
    case 'grievances':
        getGrievancesReport();
        break;
    case 'employees':
        getEmployeesReport();
        break;
    case 'department':
        getDepartmentReport();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Get overview report
 */
function getOverviewReport() {
    $db = Database::getInstance();
    
    $currentYear = date('Y');
    $currentMonth = date('m');
    
    // Monthly trends
    $monthlyLeaves = $db->fetchAll(
        "SELECT MONTH(created_at) as month, 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved,
                COUNT(CASE WHEN status = 'Rejected' THEN 1 END) as rejected
         FROM leave_requests
         WHERE YEAR(created_at) = :year
         GROUP BY MONTH(created_at)
         ORDER BY month",
        ['year' => $currentYear]
    );
    
    $monthlyGrievances = $db->fetchAll(
        "SELECT MONTH(created_at) as month,
                COUNT(*) as total,
                COUNT(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 END) as resolved
         FROM grievances
         WHERE YEAR(created_at) = :year
         GROUP BY MONTH(created_at)
         ORDER BY month",
        ['year' => $currentYear]
    );
    
    $monthlyAttendance = $db->fetchAll(
        "SELECT MONTH(attendance_date) as month,
                AVG(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) * 100 as attendance_rate
         FROM attendance
         WHERE YEAR(attendance_date) = :year
         GROUP BY MONTH(attendance_date)
         ORDER BY month",
        ['year' => $currentYear]
    );
    
    // Year-to-date summary
    $ytdSummary = [
        'total_leaves_applied' => $db->count('leave_requests', "YEAR(created_at) = :year", ['year' => $currentYear]),
        'total_grievances' => $db->count('grievances', "YEAR(created_at) = :year", ['year' => $currentYear]),
        'new_employees' => $db->count('users u JOIN roles r ON u.role_id = r.id', 
                                      "r.role_code = 'EMPLOYEE' AND YEAR(u.created_at) = :year", 
                                      ['year' => $currentYear])
    ];
    
    successResponse([
        'monthly_leaves' => $monthlyLeaves,
        'monthly_grievances' => $monthlyGrievances,
        'monthly_attendance' => $monthlyAttendance,
        'ytd_summary' => $ytdSummary,
        'year' => $currentYear
    ]);
}

/**
 * Get detailed attendance report
 */
function getAttendanceReport() {
    $db = Database::getInstance();
    
    $startDate = sanitize($_GET['start_date'] ?? date('Y-m-01'));
    $endDate = sanitize($_GET['end_date'] ?? date('Y-m-d'));
    $departmentId = (int)($_GET['department_id'] ?? 0);
    
    $where = "a.attendance_date BETWEEN :start AND :end";
    $params = ['start' => $startDate, 'end' => $endDate];
    
    if ($departmentId) {
        $where .= " AND u.department_id = :dept";
        $params['dept'] = $departmentId;
    }
    
    // Employee-wise attendance
    $employeeAttendance = $db->fetchAll(
        "SELECT u.employee_id, CONCAT(u.first_name, ' ', u.last_name) as name,
                d.dept_name,
                COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present,
                COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent,
                COUNT(CASE WHEN a.status = 'Half Day' THEN 1 END) as half_day,
                COUNT(CASE WHEN a.status = 'On Leave' THEN 1 END) as on_leave,
                ROUND(SUM(a.work_hours), 2) as total_hours,
                ROUND(SUM(a.overtime_hours), 2) as overtime_hours
         FROM users u
         JOIN roles r ON u.role_id = r.id
         LEFT JOIN departments d ON u.department_id = d.id
         LEFT JOIN attendance a ON u.id = a.employee_id AND {$where}
         WHERE r.role_code = 'EMPLOYEE' AND u.is_active = 1
         GROUP BY u.id
         ORDER BY u.first_name",
        $params
    );
    
    // Daily summary
    $dailySummary = $db->fetchAll(
        "SELECT a.attendance_date,
                COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present,
                COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent,
                COUNT(CASE WHEN a.status = 'Half Day' THEN 1 END) as half_day
         FROM attendance a
         JOIN users u ON a.employee_id = u.id
         WHERE {$where}
         GROUP BY a.attendance_date
         ORDER BY a.attendance_date",
        $params
    );
    
    // Department-wise summary
    $departmentSummary = $db->fetchAll(
        "SELECT d.dept_name,
                COUNT(DISTINCT u.id) as total_employees,
                COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_count,
                ROUND(AVG(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) * 100, 2) as attendance_rate
         FROM departments d
         LEFT JOIN users u ON d.id = u.department_id
         JOIN roles r ON u.role_id = r.id
         LEFT JOIN attendance a ON u.id = a.employee_id AND a.attendance_date BETWEEN :start AND :end
         WHERE r.role_code = 'EMPLOYEE' AND u.is_active = 1
         GROUP BY d.id
         ORDER BY d.dept_name",
        ['start' => $startDate, 'end' => $endDate]
    );
    
    successResponse([
        'employee_attendance' => $employeeAttendance,
        'daily_summary' => $dailySummary,
        'department_summary' => $departmentSummary,
        'period' => ['start' => $startDate, 'end' => $endDate]
    ]);
}

/**
 * Get leaves report
 */
function getLeavesReport() {
    $db = Database::getInstance();
    
    $year = (int)($_GET['year'] ?? date('Y'));
    $departmentId = (int)($_GET['department_id'] ?? 0);
    
    $where = "YEAR(lr.created_at) = :year";
    $params = ['year' => $year];
    
    if ($departmentId) {
        $where .= " AND u.department_id = :dept";
        $params['dept'] = $departmentId;
    }
    
    // Leave type distribution
    $byType = $db->fetchAll(
        "SELECT lt.leave_name, lt.leave_code,
                COUNT(lr.id) as total_requests,
                SUM(lr.total_days) as total_days,
                COUNT(CASE WHEN lr.status = 'Approved' THEN 1 END) as approved,
                COUNT(CASE WHEN lr.status = 'Rejected' THEN 1 END) as rejected
         FROM leave_types lt
         LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id AND {$where}
         LEFT JOIN users u ON lr.employee_id = u.id
         GROUP BY lt.id
         ORDER BY total_requests DESC",
        $params
    );
    
    // Monthly distribution
    $monthly = $db->fetchAll(
        "SELECT MONTH(lr.created_at) as month,
                COUNT(*) as total,
                SUM(lr.total_days) as days
         FROM leave_requests lr
         JOIN users u ON lr.employee_id = u.id
         WHERE {$where} AND lr.status = 'Approved'
         GROUP BY MONTH(lr.created_at)
         ORDER BY month",
        $params
    );
    
    // Top leave takers
    $topLeaveTakers = $db->fetchAll(
        "SELECT u.employee_id, CONCAT(u.first_name, ' ', u.last_name) as name,
                d.dept_name,
                COUNT(lr.id) as leave_count,
                SUM(lr.total_days) as total_days
         FROM users u
         JOIN roles r ON u.role_id = r.id
         LEFT JOIN departments d ON u.department_id = d.id
         JOIN leave_requests lr ON u.id = lr.employee_id
         WHERE r.role_code = 'EMPLOYEE' AND lr.status = 'Approved' AND YEAR(lr.created_at) = :year
         GROUP BY u.id
         ORDER BY total_days DESC
         LIMIT 10",
        ['year' => $year]
    );
    
    // Average processing time
    $avgProcessingTime = $db->fetch(
        "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, approved_at)) as avg_hours
         FROM leave_requests
         WHERE status IN ('Approved', 'Rejected') AND approved_at IS NOT NULL
         AND YEAR(created_at) = :year",
        ['year' => $year]
    );
    
    successResponse([
        'by_type' => $byType,
        'monthly' => $monthly,
        'top_leave_takers' => $topLeaveTakers,
        'avg_processing_hours' => round($avgProcessingTime['avg_hours'] ?? 0, 1),
        'year' => $year
    ]);
}

/**
 * Get grievances report
 */
function getGrievancesReport() {
    $db = Database::getInstance();
    
    $year = (int)($_GET['year'] ?? date('Y'));
    
    // By category
    $byCategory = $db->fetchAll(
        "SELECT gc.category_name,
                COUNT(g.id) as total,
                COUNT(CASE WHEN g.status IN ('Resolved', 'Closed') THEN 1 END) as resolved,
                COUNT(CASE WHEN g.status IN ('Open', 'In Progress') THEN 1 END) as pending
         FROM grievance_categories gc
         LEFT JOIN grievances g ON gc.id = g.category_id AND YEAR(g.created_at) = :year
         GROUP BY gc.id
         ORDER BY total DESC",
        ['year' => $year]
    );
    
    // By priority
    $byPriority = $db->fetchAll(
        "SELECT priority,
                COUNT(*) as total,
                COUNT(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 END) as resolved
         FROM grievances
         WHERE YEAR(created_at) = :year
         GROUP BY priority",
        ['year' => $year]
    );
    
    // Monthly trend
    $monthly = $db->fetchAll(
        "SELECT MONTH(created_at) as month,
                COUNT(*) as submitted,
                COUNT(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 END) as resolved
         FROM grievances
         WHERE YEAR(created_at) = :year
         GROUP BY MONTH(created_at)
         ORDER BY month",
        ['year' => $year]
    );
    
    // Resolution time by category
    $resolutionTime = $db->fetchAll(
        "SELECT gc.category_name,
                AVG(DATEDIFF(g.resolved_at, g.created_at)) as avg_days,
                MIN(DATEDIFF(g.resolved_at, g.created_at)) as min_days,
                MAX(DATEDIFF(g.resolved_at, g.created_at)) as max_days
         FROM grievance_categories gc
         JOIN grievances g ON gc.id = g.category_id
         WHERE g.resolved_at IS NOT NULL AND YEAR(g.created_at) = :year
         GROUP BY gc.id",
        ['year' => $year]
    );
    
    // SLA compliance
    $slaCompliance = $db->fetch(
        "SELECT 
            COUNT(*) as total_resolved,
            COUNT(CASE WHEN DATEDIFF(g.resolved_at, g.created_at) <= gc.sla_days THEN 1 END) as within_sla
         FROM grievances g
         JOIN grievance_categories gc ON g.category_id = gc.id
         WHERE g.resolved_at IS NOT NULL AND YEAR(g.created_at) = :year",
        ['year' => $year]
    );
    
    $slaRate = $slaCompliance['total_resolved'] > 0 
        ? round(($slaCompliance['within_sla'] / $slaCompliance['total_resolved']) * 100, 1) 
        : 0;
    
    successResponse([
        'by_category' => $byCategory,
        'by_priority' => $byPriority,
        'monthly' => $monthly,
        'resolution_time' => $resolutionTime,
        'sla_compliance_rate' => $slaRate,
        'year' => $year
    ]);
}

/**
 * Get employees report
 */
function getEmployeesReport() {
    $db = Database::getInstance();
    
    // By department
    $byDepartment = $db->fetchAll(
        "SELECT d.dept_name, COUNT(u.id) as count
         FROM departments d
         LEFT JOIN users u ON d.id = u.department_id
         JOIN roles r ON u.role_id = r.id
         WHERE r.role_code = 'EMPLOYEE' AND u.is_active = 1
         GROUP BY d.id
         ORDER BY count DESC"
    );
    
    // By designation
    $byDesignation = $db->fetchAll(
        "SELECT des.designation_name, COUNT(u.id) as count
         FROM designations des
         LEFT JOIN users u ON des.id = u.designation_id
         JOIN roles r ON u.role_id = r.id
         WHERE r.role_code = 'EMPLOYEE' AND u.is_active = 1
         GROUP BY des.id
         ORDER BY count DESC
         LIMIT 10"
    );
    
    // By gender
    $byGender = $db->fetchAll(
        "SELECT COALESCE(gender, 'Not Specified') as gender, COUNT(*) as count
         FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE r.role_code = 'EMPLOYEE' AND u.is_active = 1
         GROUP BY gender"
    );
    
    // Service duration distribution
    $serviceDuration = $db->fetchAll(
        "SELECT 
            CASE 
                WHEN DATEDIFF(CURDATE(), date_of_joining) < 365 THEN '< 1 Year'
                WHEN DATEDIFF(CURDATE(), date_of_joining) < 1825 THEN '1-5 Years'
                WHEN DATEDIFF(CURDATE(), date_of_joining) < 3650 THEN '5-10 Years'
                ELSE '10+ Years'
            END as duration,
            COUNT(*) as count
         FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE r.role_code = 'EMPLOYEE' AND u.is_active = 1 AND date_of_joining IS NOT NULL
         GROUP BY duration
         ORDER BY 
            CASE duration
                WHEN '< 1 Year' THEN 1
                WHEN '1-5 Years' THEN 2
                WHEN '5-10 Years' THEN 3
                ELSE 4
            END"
    );
    
    // New joinings by month (current year)
    $newJoinings = $db->fetchAll(
        "SELECT MONTH(date_of_joining) as month, COUNT(*) as count
         FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE r.role_code = 'EMPLOYEE' AND YEAR(date_of_joining) = YEAR(CURDATE())
         GROUP BY MONTH(date_of_joining)
         ORDER BY month"
    );
    
    successResponse([
        'by_department' => $byDepartment,
        'by_designation' => $byDesignation,
        'by_gender' => $byGender,
        'service_duration' => $serviceDuration,
        'new_joinings' => $newJoinings
    ]);
}

/**
 * Get department-specific report
 */
function getDepartmentReport() {
    $departmentId = (int)($_GET['department_id'] ?? 0);
    
    if (!$departmentId) {
        errorResponse('Department ID is required');
    }
    
    $db = Database::getInstance();
    
    // Department info
    $department = $db->fetch(
        "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as head_name
         FROM departments d
         LEFT JOIN users u ON d.head_employee_id = u.id
         WHERE d.id = :id",
        ['id' => $departmentId]
    );
    
    if (!$department) {
        errorResponse('Department not found', 404);
    }
    
    // Employee count
    $employeeCount = $db->count('users u JOIN roles r ON u.role_id = r.id',
                                "r.role_code = 'EMPLOYEE' AND u.department_id = :dept AND u.is_active = 1",
                                ['dept' => $departmentId]);
    
    // Attendance rate (current month)
    $attendanceRate = $db->fetch(
        "SELECT ROUND(AVG(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) * 100, 2) as rate
         FROM users u
         JOIN roles r ON u.role_id = r.id
         LEFT JOIN attendance a ON u.id = a.employee_id 
              AND MONTH(a.attendance_date) = MONTH(CURDATE())
              AND YEAR(a.attendance_date) = YEAR(CURDATE())
         WHERE r.role_code = 'EMPLOYEE' AND u.department_id = :dept AND u.is_active = 1",
        ['dept' => $departmentId]
    );
    
    // Leave statistics
    $leaveStats = $db->fetch(
        "SELECT COUNT(*) as total,
                COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved
         FROM leave_requests lr
         JOIN users u ON lr.employee_id = u.id
         WHERE u.department_id = :dept AND YEAR(lr.created_at) = YEAR(CURDATE())",
        ['dept' => $departmentId]
    );
    
    // Grievance statistics
    $grievanceStats = $db->fetch(
        "SELECT COUNT(*) as total,
                COUNT(CASE WHEN status IN ('Open', 'In Progress') THEN 1 END) as open,
                COUNT(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 END) as resolved
         FROM grievances g
         JOIN users u ON g.employee_id = u.id
         WHERE u.department_id = :dept AND YEAR(g.created_at) = YEAR(CURDATE())",
        ['dept' => $departmentId]
    );
    
    // Employees list
    $employees = $db->fetchAll(
        "SELECT u.id, u.employee_id, CONCAT(u.first_name, ' ', u.last_name) as name,
                des.designation_name, u.date_of_joining
         FROM users u
         JOIN roles r ON u.role_id = r.id
         LEFT JOIN designations des ON u.designation_id = des.id
         WHERE r.role_code = 'EMPLOYEE' AND u.department_id = :dept AND u.is_active = 1
         ORDER BY u.first_name",
        ['dept' => $departmentId]
    );
    
    successResponse([
        'department' => $department,
        'employee_count' => $employeeCount,
        'attendance_rate' => $attendanceRate['rate'] ?? 0,
        'leave_stats' => $leaveStats,
        'grievance_stats' => $grievanceStats,
        'employees' => $employees
    ]);
}


/**
 * Get monthly report
 */
function getMonthlyReport() {
    $db = Database::getInstance();
    
    $year = (int)($_GET['year'] ?? date('Y'));
    $month = (int)($_GET['month'] ?? date('m'));
    
    // Get total employees
    $totalEmployees = $db->count('users u JOIN roles r ON u.role_id = r.id', "r.role_code = 'EMPLOYEE' AND u.is_active = 1");
    
    // Calculate working days in month (excluding only Sundays)
    $startDate = "$year-$month-01";
    $endDate = date('Y-m-t', strtotime($startDate));
    $workingDays = 0;
    
    $current = strtotime($startDate);
    $end = strtotime($endDate);
    while ($current <= $end) {
        $dayOfWeek = date('N', $current);
        if ($dayOfWeek != 7) { // Exclude only Sunday (7)
            $workingDays++;
        }
        $current = strtotime('+1 day', $current);
    }
    
    // Get employee attendance summary
    $employeeAttendance = $db->fetchAll("
        SELECT u.id, u.employee_id, CONCAT(u.first_name, ' ', u.last_name) as name,
               d.dept_name as department,
               COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present,
               COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent,
               COUNT(CASE WHEN a.status = 'On Leave' THEN 1 END) as leaves
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN attendance a ON u.id = a.employee_id 
            AND YEAR(a.attendance_date) = :year 
            AND MONTH(a.attendance_date) = :month
        WHERE r.role_code = 'EMPLOYEE' AND u.is_active = 1
        GROUP BY u.id
        ORDER BY u.first_name
    ", ['year' => $year, 'month' => $month]);
    
    // Calculate average attendance
    $totalPresent = 0;
    foreach ($employeeAttendance as $emp) {
        $totalPresent += $emp['present'];
    }
    $avgAttendance = ($totalEmployees > 0 && $workingDays > 0) 
        ? round(($totalPresent / ($totalEmployees * $workingDays)) * 100) 
        : 0;
    
    // Get leaves for the month
    $leaves = $db->fetchAll("
        SELECT lr.*, lt.leave_name,
               CONCAT(u.first_name, ' ', u.last_name) as employee_name
        FROM leave_requests lr
        JOIN users u ON lr.employee_id = u.id
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE (
            (YEAR(lr.start_date) = :year1 AND MONTH(lr.start_date) = :month1)
            OR (YEAR(lr.end_date) = :year2 AND MONTH(lr.end_date) = :month2)
        )
        ORDER BY lr.start_date
    ", ['year1' => $year, 'month1' => $month, 'year2' => $year, 'month2' => $month]);
    
    $totalLeaves = count($leaves);
    
    successResponse([
        'year' => $year,
        'month' => $month,
        'total_employees' => $totalEmployees,
        'working_days' => $workingDays,
        'total_leaves' => $totalLeaves,
        'avg_attendance' => $avgAttendance,
        'employee_attendance' => $employeeAttendance,
        'leaves' => $leaves
    ]);
}
