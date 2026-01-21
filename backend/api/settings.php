<?php
/**
 * e-Karamchari System Settings API
 * Handles system configuration and security settings
 */

require_once __DIR__ . '/../middleware/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

Auth::requireAdmin();

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        getSettings();
        break;
    case 'get':
        getSetting();
        break;
    case 'update':
        updateSetting();
        break;
    case 'departments':
        manageDepartments();
        break;
    case 'designations':
        manageDesignations();
        break;
    case 'holidays':
        manageHolidays();
        break;
    case 'leave-types':
        manageLeaveTypes();
        break;
    case 'activity-logs':
        getActivityLogs();
        break;
    case 'sessions':
        getActiveSessions();
        break;
    case 'terminate-session':
        terminateSession();
        break;
    case 'reset-password':
        resetUserPassword();
        break;
    case 'unlock-account':
        unlockAccount();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Get all settings
 */
function getSettings() {
    $db = Database::getInstance();
    
    $settings = $db->fetchAll(
        "SELECT setting_key, setting_value, setting_type, description, is_editable
         FROM system_settings
         ORDER BY setting_key"
    );
    
    // Convert to key-value format
    $formatted = [];
    foreach ($settings as $setting) {
        $value = $setting['setting_value'];
        if ($setting['setting_type'] === 'boolean') {
            $value = $value === '1' || $value === 'true';
        } elseif ($setting['setting_type'] === 'number') {
            $value = (float)$value;
        } elseif ($setting['setting_type'] === 'json') {
            $value = json_decode($value, true);
        }
        
        $formatted[$setting['setting_key']] = [
            'value' => $value,
            'type' => $setting['setting_type'],
            'description' => $setting['description'],
            'editable' => (bool)$setting['is_editable']
        ];
    }
    
    successResponse($formatted);
}

/**
 * Get single setting
 */
function getSetting() {
    $key = sanitize($_GET['key'] ?? '');
    
    if (empty($key)) {
        errorResponse('Setting key is required');
    }
    
    $db = Database::getInstance();
    
    $setting = $db->fetch(
        "SELECT * FROM system_settings WHERE setting_key = :key",
        ['key' => $key]
    );
    
    if (!$setting) {
        errorResponse('Setting not found', 404);
    }
    
    successResponse($setting);
}

/**
 * Update setting
 */
function updateSetting() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    // Only super admin can update settings
    if (!Auth::hasRole(['SUPER_ADMIN'])) {
        errorResponse('Only Super Admin can modify settings', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $key = sanitize($input['key'] ?? '');
    $value = $input['value'] ?? '';
    
    if (empty($key)) {
        errorResponse('Setting key is required');
    }
    
    $db = Database::getInstance();
    
    $setting = $db->fetch(
        "SELECT * FROM system_settings WHERE setting_key = :key",
        ['key' => $key]
    );
    
    if (!$setting) {
        errorResponse('Setting not found', 404);
    }
    
    if (!$setting['is_editable']) {
        errorResponse('This setting cannot be modified');
    }
    
    // Convert value based on type
    if ($setting['setting_type'] === 'json') {
        $value = json_encode($value);
    } elseif ($setting['setting_type'] === 'boolean') {
        $value = $value ? '1' : '0';
    }
    
    $db->update('system_settings', [
        'setting_value' => $value,
        'updated_by' => $_SESSION['user_id']
    ], 'setting_key = :key', ['key' => $key]);
    
    logActivity($_SESSION['user_id'], 'UPDATE_SETTING', 'SETTINGS', "Updated setting: {$key}");
    
    successResponse([], 'Setting updated successfully');
}

/**
 * Manage departments
 */
function manageDepartments() {
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $departments = $db->fetchAll(
            "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as head_name,
                    (SELECT COUNT(*) FROM users WHERE department_id = d.id) as employee_count
             FROM departments d
             LEFT JOIN users u ON d.head_employee_id = u.id
             ORDER BY d.dept_name"
        );
        successResponse($departments);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'create';
        
        if ($action === 'create') {
            if (empty($input['dept_code']) || empty($input['dept_name'])) {
                errorResponse('Department code and name are required');
            }
            
            $id = $db->insert('departments', [
                'dept_code' => sanitize($input['dept_code']),
                'dept_name' => sanitize($input['dept_name']),
                'description' => sanitize($input['description'] ?? ''),
                'head_employee_id' => $input['head_employee_id'] ?? null
            ]);
            
            logActivity($_SESSION['user_id'], 'CREATE_DEPARTMENT', 'SETTINGS', "Created department: {$input['dept_name']}");
            successResponse(['id' => $id], 'Department created successfully');
        }
        
        if ($action === 'update') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                errorResponse('Department ID is required');
            }
            
            $updateData = [];
            if (isset($input['dept_name'])) $updateData['dept_name'] = sanitize($input['dept_name']);
            if (isset($input['description'])) $updateData['description'] = sanitize($input['description']);
            if (isset($input['head_employee_id'])) $updateData['head_employee_id'] = $input['head_employee_id'];
            if (isset($input['is_active'])) $updateData['is_active'] = $input['is_active'];
            
            $db->update('departments', $updateData, 'id = :id', ['id' => $id]);
            
            logActivity($_SESSION['user_id'], 'UPDATE_DEPARTMENT', 'SETTINGS', "Updated department ID: {$id}");
            successResponse([], 'Department updated successfully');
        }
        
        if ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                errorResponse('Department ID is required');
            }
            
            // Check if department has employees
            $count = $db->count('users', 'department_id = :id', ['id' => $id]);
            if ($count > 0) {
                errorResponse('Cannot delete department with assigned employees');
            }
            
            $db->delete('departments', 'id = :id', ['id' => $id]);
            
            logActivity($_SESSION['user_id'], 'DELETE_DEPARTMENT', 'SETTINGS', "Deleted department ID: {$id}");
            successResponse([], 'Department deleted successfully');
        }
    }
}

/**
 * Manage designations
 */
function manageDesignations() {
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $designations = $db->fetchAll(
            "SELECT d.*, (SELECT COUNT(*) FROM users WHERE designation_id = d.id) as employee_count
             FROM designations d
             ORDER BY d.designation_name"
        );
        successResponse($designations);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'create';
        
        if ($action === 'create') {
            if (empty($input['designation_code']) || empty($input['designation_name'])) {
                errorResponse('Designation code and name are required');
            }
            
            $id = $db->insert('designations', [
                'designation_code' => sanitize($input['designation_code']),
                'designation_name' => sanitize($input['designation_name']),
                'grade_pay' => $input['grade_pay'] ?? 0
            ]);
            
            logActivity($_SESSION['user_id'], 'CREATE_DESIGNATION', 'SETTINGS', "Created designation: {$input['designation_name']}");
            successResponse(['id' => $id], 'Designation created successfully');
        }
        
        if ($action === 'update') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                errorResponse('Designation ID is required');
            }
            
            $updateData = [];
            if (isset($input['designation_name'])) $updateData['designation_name'] = sanitize($input['designation_name']);
            if (isset($input['grade_pay'])) $updateData['grade_pay'] = $input['grade_pay'];
            if (isset($input['is_active'])) $updateData['is_active'] = $input['is_active'];
            
            $db->update('designations', $updateData, 'id = :id', ['id' => $id]);
            
            logActivity($_SESSION['user_id'], 'UPDATE_DESIGNATION', 'SETTINGS', "Updated designation ID: {$id}");
            successResponse([], 'Designation updated successfully');
        }
    }
}

/**
 * Manage holidays
 */
function manageHolidays() {
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $year = (int)($_GET['year'] ?? date('Y'));
        
        $holidays = $db->fetchAll(
            "SELECT * FROM holidays WHERE YEAR(holiday_date) = :year ORDER BY holiday_date",
            ['year' => $year]
        );
        successResponse($holidays);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'create';
        
        if ($action === 'create') {
            if (empty($input['holiday_date']) || empty($input['holiday_name'])) {
                errorResponse('Holiday date and name are required');
            }
            
            $id = $db->insert('holidays', [
                'holiday_date' => $input['holiday_date'],
                'holiday_name' => sanitize($input['holiday_name']),
                'holiday_type' => $input['holiday_type'] ?? 'National'
            ]);
            
            logActivity($_SESSION['user_id'], 'CREATE_HOLIDAY', 'SETTINGS', "Created holiday: {$input['holiday_name']}");
            successResponse(['id' => $id], 'Holiday created successfully');
        }
        
        if ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                errorResponse('Holiday ID is required');
            }
            
            $db->delete('holidays', 'id = :id', ['id' => $id]);
            
            logActivity($_SESSION['user_id'], 'DELETE_HOLIDAY', 'SETTINGS', "Deleted holiday ID: {$id}");
            successResponse([], 'Holiday deleted successfully');
        }
    }
}

/**
 * Get activity logs
 */
function getActivityLogs() {
    $db = Database::getInstance();
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    $module = sanitize($_GET['module'] ?? '');
    $userId = (int)($_GET['user_id'] ?? 0);
    $startDate = sanitize($_GET['start_date'] ?? '');
    $endDate = sanitize($_GET['end_date'] ?? '');
    
    $where = "1=1";
    $params = [];
    
    if ($module) {
        $where .= " AND al.module = :module";
        $params['module'] = $module;
    }
    
    if ($userId) {
        $where .= " AND al.user_id = :user_id";
        $params['user_id'] = $userId;
    }
    
    if ($startDate) {
        $where .= " AND DATE(al.created_at) >= :start_date";
        $params['start_date'] = $startDate;
    }
    
    if ($endDate) {
        $where .= " AND DATE(al.created_at) <= :end_date";
        $params['end_date'] = $endDate;
    }
    
    $total = $db->count('activity_logs al', $where, $params);
    
    $logs = $db->fetchAll(
        "SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.employee_id
         FROM activity_logs al
         LEFT JOIN users u ON al.user_id = u.id
         WHERE {$where}
         ORDER BY al.created_at DESC
         LIMIT {$limit} OFFSET {$offset}",
        $params
    );
    
    successResponse([
        'logs' => $logs,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get active sessions
 */
function getActiveSessions() {
    $db = Database::getInstance();
    
    $sessions = $db->fetchAll(
        "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, 
                u.employee_id, r.role_name
         FROM sessions s
         JOIN users u ON s.user_id = u.id
         JOIN roles r ON u.role_id = r.id
         WHERE s.expires_at > NOW()
         ORDER BY s.created_at DESC"
    );
    
    successResponse($sessions);
}

/**
 * Terminate user session
 */
function terminateSession() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    // Only super admin can terminate sessions
    if (!Auth::hasRole(['SUPER_ADMIN'])) {
        errorResponse('Only Super Admin can terminate sessions', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = (int)($input['session_id'] ?? 0);
    
    if (!$sessionId) {
        errorResponse('Session ID is required');
    }
    
    $db = Database::getInstance();
    
    $session = $db->fetch("SELECT * FROM sessions WHERE id = :id", ['id' => $sessionId]);
    if (!$session) {
        errorResponse('Session not found', 404);
    }
    
    $db->delete('sessions', 'id = :id', ['id' => $sessionId]);
    
    logActivity($_SESSION['user_id'], 'TERMINATE_SESSION', 'SECURITY', 
               "Terminated session for user ID: {$session['user_id']}");
    
    successResponse([], 'Session terminated successfully');
}

/**
 * Reset user password (Admin only)
 */
function resetUserPassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = (int)($input['user_id'] ?? 0);
    $newPassword = $input['new_password'] ?? '';
    
    if (!$userId || empty($newPassword)) {
        errorResponse('User ID and new password are required');
    }
    
    if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        errorResponse('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters');
    }
    
    $db = Database::getInstance();
    
    $db->update('users', [
        'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
        'password_changed_at' => date('Y-m-d H:i:s'),
        'failed_login_attempts' => 0,
        'is_locked' => 0
    ], 'id = :id', ['id' => $userId]);
    
    // Invalidate all sessions for this user
    $db->delete('sessions', 'user_id = :id', ['id' => $userId]);
    
    logActivity($_SESSION['user_id'], 'RESET_PASSWORD', 'SECURITY', "Reset password for user ID: {$userId}");
    
    successResponse([], 'Password reset successfully');
}

/**
 * Unlock user account
 */
function unlockAccount() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = (int)($input['user_id'] ?? 0);
    
    if (!$userId) {
        errorResponse('User ID is required');
    }
    
    $db = Database::getInstance();
    
    $db->update('users', [
        'is_locked' => 0,
        'failed_login_attempts' => 0
    ], 'id = :id', ['id' => $userId]);
    
    logActivity($_SESSION['user_id'], 'UNLOCK_ACCOUNT', 'SECURITY', "Unlocked account for user ID: {$userId}");
    
    successResponse([], 'Account unlocked successfully');
}

/**
 * Manage leave types
 */
function manageLeaveTypes() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $types = $db->fetchAll("SELECT * FROM leave_types ORDER BY leave_name");
        successResponse($types);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'update';
        
        if ($action === 'update') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                errorResponse('Leave type ID is required');
            }
            
            $updateData = [];
            if (isset($input['max_days_per_year'])) $updateData['max_days_per_year'] = (int)$input['max_days_per_year'];
            if (isset($input['is_active'])) $updateData['is_active'] = $input['is_active'] ? 1 : 0;
            if (isset($input['is_paid'])) $updateData['is_paid'] = $input['is_paid'] ? 1 : 0;
            if (isset($input['requires_document'])) $updateData['requires_document'] = $input['requires_document'] ? 1 : 0;
            
            $db->update('leave_types', $updateData, 'id = :id', ['id' => $id]);
            
            logActivity($_SESSION['user_id'], 'UPDATE_LEAVE_TYPE', 'SETTINGS', "Updated leave type ID: {$id}");
            successResponse([], 'Leave type updated successfully');
        }
    }
}
