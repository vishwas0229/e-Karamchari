<?php
/**
 * e-Karamchari Leave Management API
 * Handles leave requests, approvals, and balance
 */

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/notifications.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        getLeaveRequests();
        break;
    case 'get':
        getLeaveDetails();
        break;
    case 'my-leaves':
        getMyLeaves();
        break;
    case 'apply':
        applyLeave();
        break;
    case 'approve':
        approveLeave();
        break;
    case 'reject':
        rejectLeave();
        break;
    case 'cancel':
        cancelLeave();
        break;
    case 'balance':
        getLeaveBalance();
        break;
    case 'types':
        getLeaveTypes();
        break;
    case 'stats':
        getLeaveStats();
        break;
    case 'pending-count':
        getPendingCount();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Get all leave requests (Admin only)
 */
function getLeaveRequests() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(MAX_PAGE_SIZE, max(1, (int)($_GET['limit'] ?? DEFAULT_PAGE_SIZE)));
    $offset = ($page - 1) * $limit;
    
    $status = sanitize($_GET['status'] ?? '');
    $department = sanitize($_GET['department'] ?? '');
    
    $where = "1=1";
    $params = [];
    
    if ($status) {
        $where .= " AND lr.status = :status";
        $params['status'] = $status;
    }
    
    if ($department) {
        $where .= " AND u.department_id = :department";
        $params['department'] = $department;
    }
    
    $countSql = "SELECT COUNT(*) as total 
                 FROM leave_requests lr 
                 JOIN users u ON lr.employee_id = u.id 
                 WHERE {$where}";
    $total = $db->fetch($countSql, $params)['total'];
    
    $sql = "SELECT lr.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                   u.employee_id as emp_code,
                   d.dept_name,
                   lt.leave_name,
                   CONCAT(a.first_name, ' ', a.last_name) as approved_by_name
            FROM leave_requests lr
            JOIN users u ON lr.employee_id = u.id
            LEFT JOIN departments d ON u.department_id = d.id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN users a ON lr.approved_by = a.id
            WHERE {$where}
            ORDER BY lr.created_at DESC
            LIMIT {$limit} OFFSET {$offset}";
    
    $leaves = $db->fetchAll($sql, $params);
    
    successResponse([
        'leaves' => $leaves,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get single leave request details
 */
function getLeaveDetails() {
    Auth::requireAuth();
    
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        errorResponse('Leave ID is required');
    }
    
    $db = Database::getInstance();
    
    $sql = "SELECT lr.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                   u.employee_id as emp_code,
                   d.dept_name,
                   lt.leave_name,
                   CONCAT(a.first_name, ' ', a.last_name) as approved_by_name
            FROM leave_requests lr
            JOIN users u ON lr.employee_id = u.id
            LEFT JOIN departments d ON u.department_id = d.id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN users a ON lr.approved_by = a.id
            WHERE lr.id = :id";
    
    $leave = $db->fetch($sql, ['id' => $id]);
    
    if (!$leave) {
        errorResponse('Leave request not found', 404);
    }
    
    // Check permission - only admin or the employee can view
    if (!Auth::isAdmin() && $leave['employee_id'] != $_SESSION['user_id']) {
        errorResponse('Unauthorized', 403);
    }
    
    successResponse($leave);
}

/**
 * Get current user's leave requests
 */
function getMyLeaves() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(MAX_PAGE_SIZE, max(1, (int)($_GET['limit'] ?? DEFAULT_PAGE_SIZE)));
    $offset = ($page - 1) * $limit;
    
    $status = sanitize($_GET['status'] ?? '');
    
    $where = "lr.employee_id = :user_id";
    $params = ['user_id' => $userId];
    
    if ($status) {
        $where .= " AND lr.status = :status";
        $params['status'] = $status;
    }
    
    $total = $db->count('leave_requests lr', $where, $params);
    
    $sql = "SELECT lr.*, lt.leave_name,
                   CONCAT(a.first_name, ' ', a.last_name) as approved_by_name
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN users a ON lr.approved_by = a.id
            WHERE {$where}
            ORDER BY lr.created_at DESC
            LIMIT {$limit} OFFSET {$offset}";
    
    $leaves = $db->fetchAll($sql, $params);
    
    successResponse([
        'leaves' => $leaves,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Apply for leave
 */
function applyLeave() {
    Auth::requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['leave_type_id', 'start_date', 'end_date', 'reason'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            errorResponse("Field '{$field}' is required");
        }
    }
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    $startDate = $input['start_date'];
    $endDate = $input['end_date'];
    
    // Validate dates
    if (strtotime($startDate) > strtotime($endDate)) {
        errorResponse('End date must be after start date');
    }
    
    if (strtotime($startDate) < strtotime(date('Y-m-d'))) {
        errorResponse('Cannot apply leave for past dates');
    }
    
    // Calculate total days
    $totalDays = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24) + 1;
    
    // Maximum 30 days leave at a time
    if ($totalDays > 30) {
        errorResponse('Maximum 30 days leave can be applied at a time');
    }
    
    // Check leave balance
    $balance = $db->fetch(
        "SELECT (total_allocated - used + carried_forward) as available
         FROM leave_balance
         WHERE employee_id = :user_id AND leave_type_id = :type_id AND year = YEAR(CURDATE())",
        ['user_id' => $userId, 'type_id' => $input['leave_type_id']]
    );
    
    $leaveType = $db->fetch("SELECT * FROM leave_types WHERE id = :id", ['id' => $input['leave_type_id']]);
    
    // If no balance entry exists, use max_days_per_year as available balance
    $availableBalance = $balance ? $balance['available'] : $leaveType['max_days_per_year'];
    
    if ($leaveType['leave_code'] !== 'LWP' && $availableBalance < $totalDays) {
        errorResponse('Insufficient leave balance');
    }
    
    // Check for overlapping leaves
    $overlap = $db->fetch(
        "SELECT id FROM leave_requests 
         WHERE employee_id = :user_id 
         AND status IN ('Pending', 'Approved')
         AND ((start_date BETWEEN :start1 AND :end1) OR (end_date BETWEEN :start2 AND :end2)
              OR (start_date <= :start3 AND end_date >= :end3))",
        ['user_id' => $userId, 'start1' => $startDate, 'end1' => $endDate, 
         'start2' => $startDate, 'end2' => $endDate, 'start3' => $startDate, 'end3' => $endDate]
    );
    
    if ($overlap) {
        errorResponse('You already have a leave request for these dates');
    }
    
    try {
        $requestNumber = generateRequestNumber('LV');
        
        $leaveData = [
            'request_number' => $requestNumber,
            'employee_id' => $userId,
            'leave_type_id' => $input['leave_type_id'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'reason' => sanitize($input['reason']),
            'status' => 'Pending'
        ];
        
        $id = $db->insert('leave_requests', $leaveData);
        
        logActivity($userId, 'APPLY_LEAVE', 'LEAVES', "Applied leave: {$requestNumber}");
        
        // Notify all admins about new leave request
        $employeeName = getUserName($userId);
        notifyAdmins(
            'New Leave Request',
            "{$employeeName} has applied for leave ({$requestNumber})",
            'Info',
            'leave-approvals.html'
        );
        
        successResponse([
            'id' => $id,
            'request_number' => $requestNumber
        ], 'Leave application submitted successfully');
    } catch (Exception $e) {
        errorResponse('Failed to submit leave application');
    }
}

/**
 * Approve leave request (Admin only)
 */
function approveLeave() {
    Auth::requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    
    if (!$id) {
        errorResponse('Leave request ID is required');
    }
    
    $db = Database::getInstance();
    
    $leave = $db->fetch("SELECT * FROM leave_requests WHERE id = :id", ['id' => $id]);
    
    if (!$leave) {
        errorResponse('Leave request not found', 404);
    }
    
    if ($leave['status'] !== 'Pending') {
        errorResponse('Only pending requests can be approved');
    }
    
    try {
        $db->beginTransaction();
        
        // Update leave request
        $db->update('leave_requests', [
            'status' => 'Approved',
            'approved_by' => $_SESSION['user_id'],
            'approved_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $id]);
        
        // Check if leave balance entry exists
        $balanceExists = $db->fetch(
            "SELECT id FROM leave_balance WHERE employee_id = :emp_id AND leave_type_id = :type_id AND year = YEAR(CURDATE())",
            ['emp_id' => $leave['employee_id'], 'type_id' => $leave['leave_type_id']]
        );
        
        if ($balanceExists) {
            // Update existing balance
            $db->query(
                "UPDATE leave_balance 
                 SET used = used + :days 
                 WHERE employee_id = :emp_id AND leave_type_id = :type_id AND year = YEAR(CURDATE())",
                ['days' => $leave['total_days'], 'emp_id' => $leave['employee_id'], 'type_id' => $leave['leave_type_id']]
            );
        } else {
            // Create new balance entry with max_days_per_year as allocated
            $leaveType = $db->fetch("SELECT max_days_per_year FROM leave_types WHERE id = :id", ['id' => $leave['leave_type_id']]);
            $db->insert('leave_balance', [
                'employee_id' => $leave['employee_id'],
                'leave_type_id' => $leave['leave_type_id'],
                'year' => date('Y'),
                'total_allocated' => $leaveType['max_days_per_year'],
                'used' => $leave['total_days'],
                'carried_forward' => 0
            ]);
        }
        
        // Create notification
        $db->insert('notifications', [
            'user_id' => $leave['employee_id'],
            'title' => 'Leave Approved',
            'message' => "Your leave request ({$leave['request_number']}) has been approved.",
            'type' => 'Success',
            'link' => 'leave-status.html'
        ]);
        
        $db->commit();
        
        logActivity($_SESSION['user_id'], 'APPROVE_LEAVE', 'LEAVES', "Approved leave: {$leave['request_number']}");
        
        successResponse([], 'Leave request approved successfully');
    } catch (Exception $e) {
        $db->rollback();
        errorResponse('Failed to approve leave request');
    }
}

/**
 * Reject leave request (Admin only)
 */
function rejectLeave() {
    Auth::requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $reason = sanitize($input['reason'] ?? '');
    
    if (!$id) {
        errorResponse('Leave request ID is required');
    }
    
    if (empty($reason)) {
        errorResponse('Rejection reason is required');
    }
    
    $db = Database::getInstance();
    
    $leave = $db->fetch("SELECT * FROM leave_requests WHERE id = :id", ['id' => $id]);
    
    if (!$leave) {
        errorResponse('Leave request not found', 404);
    }
    
    if ($leave['status'] !== 'Pending') {
        errorResponse('Only pending requests can be rejected');
    }
    
    $db->update('leave_requests', [
        'status' => 'Rejected',
        'approved_by' => $_SESSION['user_id'],
        'approved_at' => date('Y-m-d H:i:s'),
        'rejection_reason' => $reason
    ], 'id = :id', ['id' => $id]);
    
    // Create notification
    $db->insert('notifications', [
        'user_id' => $leave['employee_id'],
        'title' => 'Leave Rejected',
        'message' => "Your leave request ({$leave['request_number']}) has been rejected. Reason: {$reason}",
        'type' => 'Error',
        'link' => 'leave-status.html'
    ]);
    
    logActivity($_SESSION['user_id'], 'REJECT_LEAVE', 'LEAVES', "Rejected leave: {$leave['request_number']}");
    
    successResponse([], 'Leave request rejected');
}

/**
 * Cancel leave request
 */
function cancelLeave() {
    Auth::requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    
    if (!$id) {
        errorResponse('Leave request ID is required');
    }
    
    $db = Database::getInstance();
    
    $leave = $db->fetch(
        "SELECT * FROM leave_requests WHERE id = :id AND employee_id = :user_id",
        ['id' => $id, 'user_id' => $_SESSION['user_id']]
    );
    
    if (!$leave) {
        errorResponse('Leave request not found', 404);
    }
    
    if (!in_array($leave['status'], ['Pending', 'Approved'])) {
        errorResponse('This leave request cannot be cancelled');
    }
    
    // If approved, restore balance
    if ($leave['status'] === 'Approved') {
        $db->query(
            "UPDATE leave_balance 
             SET used = used - :days 
             WHERE employee_id = :emp_id AND leave_type_id = :type_id AND year = YEAR(CURDATE())",
            ['days' => $leave['total_days'], 'emp_id' => $leave['employee_id'], 'type_id' => $leave['leave_type_id']]
        );
    }
    
    $db->update('leave_requests', ['status' => 'Cancelled'], 'id = :id', ['id' => $id]);
    
    logActivity($_SESSION['user_id'], 'CANCEL_LEAVE', 'LEAVES', "Cancelled leave: {$leave['request_number']}");
    
    successResponse([], 'Leave request cancelled');
}

/**
 * Get leave balance
 */
function getLeaveBalance() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $userId = $_GET['employee_id'] ?? $_SESSION['user_id'];
    
    // Only admin can view other's balance
    if ($userId != $_SESSION['user_id'] && !Auth::isAdmin()) {
        errorResponse('Unauthorized', 403);
    }
    
    $balance = $db->fetchAll(
        "SELECT lt.id, lt.leave_code, lt.leave_name, lt.max_days_per_year,
                COALESCE(lb.total_allocated, 0) as allocated,
                COALESCE(lb.used, 0) as used,
                COALESCE(lb.carried_forward, 0) as carried_forward,
                COALESCE(lb.total_allocated - lb.used + lb.carried_forward, lt.max_days_per_year) as available
         FROM leave_types lt
         LEFT JOIN leave_balance lb ON lt.id = lb.leave_type_id 
              AND lb.employee_id = :user_id AND lb.year = YEAR(CURDATE())
         WHERE lt.is_active = 1
         ORDER BY lt.leave_name",
        ['user_id' => $userId]
    );
    
    successResponse($balance);
}

/**
 * Get leave types
 */
function getLeaveTypes() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $types = $db->fetchAll(
        "SELECT id, leave_code, leave_name, max_days_per_year, is_paid, requires_document, description
         FROM leave_types WHERE is_active = 1 ORDER BY leave_name"
    );
    
    successResponse($types);
}

/**
 * Get leave statistics (Admin only)
 */
function getLeaveStats() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    
    $pending = $db->count('leave_requests', "status = 'Pending'");
    $approved = $db->count('leave_requests', "status = 'Approved' AND MONTH(created_at) = MONTH(CURDATE())");
    $rejected = $db->count('leave_requests', "status = 'Rejected' AND MONTH(created_at) = MONTH(CURDATE())");
    
    $byType = $db->fetchAll(
        "SELECT lt.leave_name, COUNT(lr.id) as count
         FROM leave_types lt
         LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id 
              AND lr.status = 'Approved' AND YEAR(lr.created_at) = YEAR(CURDATE())
         GROUP BY lt.id
         ORDER BY count DESC"
    );
    
    $monthlyTrend = $db->fetchAll(
        "SELECT MONTH(created_at) as month, COUNT(*) as count
         FROM leave_requests
         WHERE YEAR(created_at) = YEAR(CURDATE()) AND status = 'Approved'
         GROUP BY MONTH(created_at)
         ORDER BY month"
    );
    
    successResponse([
        'pending' => $pending,
        'approved_this_month' => $approved,
        'rejected_this_month' => $rejected,
        'by_type' => $byType,
        'monthly_trend' => $monthlyTrend
    ]);
}

/**
 * Get pending leave count
 */
function getPendingCount() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    $count = $db->count('leave_requests', "status = 'Pending'");
    
    successResponse(['count' => $count]);
}
