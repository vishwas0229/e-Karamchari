<?php
/**
 * e-Karamchari Grievance Management API
 * Handles grievance submission, tracking, and resolution
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
        getGrievances();
        break;
    case 'my-grievances':
        getMyGrievances();
        break;
    case 'get':
        getGrievance();
        break;
    case 'submit':
        submitGrievance();
        break;
    case 'update-status':
        updateGrievanceStatus();
        break;
    case 'assign':
        assignGrievance();
        break;
    case 'resolve':
        resolveGrievance();
        break;
    case 'add-comment':
        addComment();
        break;
    case 'categories':
        getCategories();
        break;
    case 'stats':
        getGrievanceStats();
        break;
    case 'pending-count':
        getPendingCount();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Get all grievances (Admin only)
 */
function getGrievances() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(MAX_PAGE_SIZE, max(1, (int)($_GET['limit'] ?? DEFAULT_PAGE_SIZE)));
    $offset = ($page - 1) * $limit;
    
    $status = sanitize($_GET['status'] ?? '');
    $priority = sanitize($_GET['priority'] ?? '');
    $category = sanitize($_GET['category'] ?? '');
    
    $where = "1=1";
    $params = [];
    
    if ($status) {
        $where .= " AND g.status = :status";
        $params['status'] = $status;
    }
    
    if ($priority) {
        $where .= " AND g.priority = :priority";
        $params['priority'] = $priority;
    }
    
    if ($category) {
        $where .= " AND g.category_id = :category";
        $params['category'] = $category;
    }
    
    $countSql = "SELECT COUNT(*) as total FROM grievances g WHERE {$where}";
    $total = $db->fetch($countSql, $params)['total'];
    
    $sql = "SELECT g.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                   u.employee_id as emp_code,
                   d.dept_name,
                   gc.category_name,
                   CONCAT(a.first_name, ' ', a.last_name) as assigned_to_name
            FROM grievances g
            JOIN users u ON g.employee_id = u.id
            LEFT JOIN departments d ON u.department_id = d.id
            JOIN grievance_categories gc ON g.category_id = gc.id
            LEFT JOIN users a ON g.assigned_to = a.id
            WHERE {$where}
            ORDER BY 
                CASE g.priority 
                    WHEN 'Critical' THEN 1 
                    WHEN 'High' THEN 2 
                    WHEN 'Medium' THEN 3 
                    ELSE 4 
                END,
                g.created_at DESC
            LIMIT {$limit} OFFSET {$offset}";
    
    $grievances = $db->fetchAll($sql, $params);
    
    successResponse([
        'grievances' => $grievances,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get current user's grievances
 */
function getMyGrievances() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(MAX_PAGE_SIZE, max(1, (int)($_GET['limit'] ?? DEFAULT_PAGE_SIZE)));
    $offset = ($page - 1) * $limit;
    
    $status = sanitize($_GET['status'] ?? '');
    
    $where = "g.employee_id = :user_id";
    $params = ['user_id' => $userId];
    
    if ($status) {
        $where .= " AND g.status = :status";
        $params['status'] = $status;
    }
    
    $total = $db->count('grievances g', $where, $params);
    
    $sql = "SELECT g.*, gc.category_name,
                   CONCAT(a.first_name, ' ', a.last_name) as assigned_to_name
            FROM grievances g
            JOIN grievance_categories gc ON g.category_id = gc.id
            LEFT JOIN users a ON g.assigned_to = a.id
            WHERE {$where}
            ORDER BY g.created_at DESC
            LIMIT {$limit} OFFSET {$offset}";
    
    $grievances = $db->fetchAll($sql, $params);
    
    successResponse([
        'grievances' => $grievances,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get single grievance with comments
 */
function getGrievance() {
    Auth::requireAuth();
    
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        errorResponse('Grievance ID is required');
    }
    
    $db = Database::getInstance();
    
    $sql = "SELECT g.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                   u.employee_id as emp_code,
                   d.dept_name,
                   gc.category_name,
                   CONCAT(a.first_name, ' ', a.last_name) as assigned_to_name,
                   CONCAT(r.first_name, ' ', r.last_name) as resolved_by_name
            FROM grievances g
            JOIN users u ON g.employee_id = u.id
            LEFT JOIN departments d ON u.department_id = d.id
            JOIN grievance_categories gc ON g.category_id = gc.id
            LEFT JOIN users a ON g.assigned_to = a.id
            LEFT JOIN users r ON g.resolved_by = r.id
            WHERE g.id = :id";
    
    $grievance = $db->fetch($sql, ['id' => $id]);
    
    if (!$grievance) {
        errorResponse('Grievance not found', 404);
    }
    
    // Check access
    if (!Auth::isAdmin() && $grievance['employee_id'] != $_SESSION['user_id']) {
        errorResponse('Unauthorized', 403);
    }
    
    // Get comments
    $comments = $db->fetchAll(
        "SELECT gc.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, r.role_name
         FROM grievance_comments gc
         JOIN users u ON gc.user_id = u.id
         JOIN roles r ON u.role_id = r.id
         WHERE gc.grievance_id = :id
         ORDER BY gc.created_at ASC",
        ['id' => $id]
    );
    
    // Filter internal comments for non-admin
    if (!Auth::isAdmin()) {
        $comments = array_filter($comments, function($c) { return !$c['is_internal']; });
        $comments = array_values($comments);
    }
    
    successResponse([
        'grievance' => $grievance,
        'comments' => $comments
    ]);
}

/**
 * Submit new grievance
 */
function submitGrievance() {
    Auth::requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['category_id', 'subject', 'description'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            errorResponse("Field '{$field}' is required");
        }
    }
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    // Get category for priority
    $category = $db->fetch("SELECT * FROM grievance_categories WHERE id = :id", ['id' => $input['category_id']]);
    if (!$category) {
        errorResponse('Invalid category');
    }
    
    try {
        $grievanceNumber = generateRequestNumber('GR');
        
        $grievanceData = [
            'grievance_number' => $grievanceNumber,
            'employee_id' => $userId,
            'category_id' => $input['category_id'],
            'subject' => sanitize($input['subject']),
            'description' => sanitize($input['description']),
            'priority' => $input['priority'] ?? $category['priority_level'],
            'status' => 'Open'
        ];
        
        $id = $db->insert('grievances', $grievanceData);
        
        logActivity($userId, 'SUBMIT_GRIEVANCE', 'GRIEVANCES', "Submitted grievance: {$grievanceNumber}");
        
        // Notify all admins about new grievance
        $employeeName = getUserName($userId);
        $notifType = ($input['priority'] ?? $category['priority_level']) === 'Critical' ? 'Error' : 
                     (($input['priority'] ?? $category['priority_level']) === 'High' ? 'Warning' : 'Info');
        
        notifyAdmins(
            'New Grievance Submitted',
            "{$employeeName} has submitted a grievance: {$input['subject']} ({$grievanceNumber})",
            $notifType,
            'grievances.html'
        );
        
        successResponse([
            'id' => $id,
            'grievance_number' => $grievanceNumber
        ], 'Grievance submitted successfully');
    } catch (Exception $e) {
        errorResponse('Failed to submit grievance');
    }
}

/**
 * Update grievance status (Admin only)
 */
function updateGrievanceStatus() {
    Auth::requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $status = sanitize($input['status'] ?? '');
    
    if (!$id || !$status) {
        errorResponse('Grievance ID and status are required');
    }
    
    $validStatuses = ['Open', 'In Progress', 'Resolved', 'Closed', 'Reopened'];
    if (!in_array($status, $validStatuses)) {
        errorResponse('Invalid status');
    }
    
    $db = Database::getInstance();
    
    $grievance = $db->fetch("SELECT * FROM grievances WHERE id = :id", ['id' => $id]);
    if (!$grievance) {
        errorResponse('Grievance not found', 404);
    }
    
    $updateData = ['status' => $status];
    
    if ($status === 'Closed') {
        $updateData['closed_at'] = date('Y-m-d H:i:s');
    }
    
    $db->update('grievances', $updateData, 'id = :id', ['id' => $id]);
    
    // Notify employee
    $db->insert('notifications', [
        'user_id' => $grievance['employee_id'],
        'title' => 'Grievance Status Updated',
        'message' => "Your grievance ({$grievance['grievance_number']}) status changed to: {$status}",
        'type' => 'Info',
        'link' => 'grievance-status.html'
    ]);
    
    logActivity($_SESSION['user_id'], 'UPDATE_GRIEVANCE_STATUS', 'GRIEVANCES', 
               "Updated grievance {$grievance['grievance_number']} to {$status}");
    
    successResponse([], 'Status updated successfully');
}

/**
 * Assign grievance to officer (Admin only)
 */
function assignGrievance() {
    Auth::requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $assignTo = (int)($input['assign_to'] ?? 0);
    
    if (!$id || !$assignTo) {
        errorResponse('Grievance ID and assignee are required');
    }
    
    $db = Database::getInstance();
    
    $db->update('grievances', [
        'assigned_to' => $assignTo,
        'status' => 'In Progress'
    ], 'id = :id', ['id' => $id]);
    
    $grievance = $db->fetch("SELECT grievance_number, employee_id FROM grievances WHERE id = :id", ['id' => $id]);
    
    // Notify employee
    $db->insert('notifications', [
        'user_id' => $grievance['employee_id'],
        'title' => 'Grievance Assigned',
        'message' => "Your grievance ({$grievance['grievance_number']}) has been assigned to an officer.",
        'type' => 'Info'
    ]);
    
    logActivity($_SESSION['user_id'], 'ASSIGN_GRIEVANCE', 'GRIEVANCES', 
               "Assigned grievance {$grievance['grievance_number']} to user {$assignTo}");
    
    successResponse([], 'Grievance assigned successfully');
}

/**
 * Resolve grievance (Admin only)
 */
function resolveGrievance() {
    Auth::requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $resolution = sanitize($input['resolution'] ?? '');
    
    if (!$id || empty($resolution)) {
        errorResponse('Grievance ID and resolution are required');
    }
    
    $db = Database::getInstance();
    
    $grievance = $db->fetch("SELECT * FROM grievances WHERE id = :id", ['id' => $id]);
    if (!$grievance) {
        errorResponse('Grievance not found', 404);
    }
    
    $db->update('grievances', [
        'status' => 'Resolved',
        'resolution' => $resolution,
        'resolved_by' => $_SESSION['user_id'],
        'resolved_at' => date('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $id]);
    
    // Notify employee
    $db->insert('notifications', [
        'user_id' => $grievance['employee_id'],
        'title' => 'Grievance Resolved',
        'message' => "Your grievance ({$grievance['grievance_number']}) has been resolved.",
        'type' => 'Success',
        'link' => 'grievance-status.html'
    ]);
    
    logActivity($_SESSION['user_id'], 'RESOLVE_GRIEVANCE', 'GRIEVANCES', 
               "Resolved grievance: {$grievance['grievance_number']}");
    
    successResponse([], 'Grievance resolved successfully');
}

/**
 * Add comment to grievance
 */
function addComment() {
    Auth::requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $grievanceId = (int)($input['grievance_id'] ?? 0);
    $comment = sanitize($input['comment'] ?? '');
    $isInternal = Auth::isAdmin() ? (bool)($input['is_internal'] ?? false) : false;
    
    if (!$grievanceId || empty($comment)) {
        errorResponse('Grievance ID and comment are required');
    }
    
    $db = Database::getInstance();
    
    $grievance = $db->fetch("SELECT * FROM grievances WHERE id = :id", ['id' => $grievanceId]);
    if (!$grievance) {
        errorResponse('Grievance not found', 404);
    }
    
    // Check access
    if (!Auth::isAdmin() && $grievance['employee_id'] != $_SESSION['user_id']) {
        errorResponse('Unauthorized', 403);
    }
    
    $db->insert('grievance_comments', [
        'grievance_id' => $grievanceId,
        'user_id' => $_SESSION['user_id'],
        'comment' => $comment,
        'is_internal' => $isInternal
    ]);
    
    successResponse([], 'Comment added successfully');
}

/**
 * Get grievance categories
 */
function getCategories() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $categories = $db->fetchAll(
        "SELECT id, category_code, category_name, description, priority_level, sla_days
         FROM grievance_categories WHERE is_active = 1 ORDER BY category_name"
    );
    
    successResponse($categories);
}

/**
 * Get grievance statistics (Admin only)
 */
function getGrievanceStats() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    
    $total = $db->count('grievances');
    $open = $db->count('grievances', "status = 'Open'");
    $inProgress = $db->count('grievances', "status = 'In Progress'");
    $resolved = $db->count('grievances', "status IN ('Resolved', 'Closed')");
    
    $byCategory = $db->fetchAll(
        "SELECT gc.category_name, COUNT(g.id) as count
         FROM grievance_categories gc
         LEFT JOIN grievances g ON gc.id = g.category_id
         GROUP BY gc.id
         ORDER BY count DESC"
    );
    
    $byPriority = $db->fetchAll(
        "SELECT priority, COUNT(*) as count
         FROM grievances
         WHERE status NOT IN ('Resolved', 'Closed')
         GROUP BY priority"
    );
    
    $avgResolutionTime = $db->fetch(
        "SELECT AVG(DATEDIFF(resolved_at, created_at)) as avg_days
         FROM grievances
         WHERE resolved_at IS NOT NULL"
    );
    
    successResponse([
        'total' => $total,
        'open' => $open,
        'in_progress' => $inProgress,
        'resolved' => $resolved,
        'by_category' => $byCategory,
        'by_priority' => $byPriority,
        'avg_resolution_days' => round($avgResolutionTime['avg_days'] ?? 0, 1)
    ]);
}

/**
 * Get pending grievance count
 */
function getPendingCount() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    $count = $db->count('grievances', "status IN ('Open', 'In Progress')");
    
    successResponse(['count' => $count]);
}
