<?php
/**
 * e-Karamchari Employee Management API
 * Handles employee CRUD operations
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
        getEmployees();
        break;
    case 'get':
        getEmployee();
        break;
    case 'create':
        createEmployee();
        break;
    case 'update':
        updateEmployee();
        break;
    case 'delete':
        deleteEmployee();
        break;
    case 'profile':
        getProfile();
        break;
    case 'update-profile':
        updateProfile();
        break;
    case 'change-password':
        changePassword();
        break;
    case 'departments':
        getDepartments();
        break;
    case 'designations':
        getDesignations();
        break;
    case 'stats':
        getEmployeeStats();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Get all employees (Admin only)
 */
function getEmployees() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(MAX_PAGE_SIZE, max(1, (int)($_GET['limit'] ?? DEFAULT_PAGE_SIZE)));
    $offset = ($page - 1) * $limit;
    
    $search = sanitize($_GET['search'] ?? '');
    $department = sanitize($_GET['department'] ?? '');
    $status = sanitize($_GET['status'] ?? '');
    
    $where = "r.role_code = 'EMPLOYEE'";
    $params = [];
    
    if ($search) {
        $where .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.employee_id LIKE :search OR u.email LIKE :search)";
        $params['search'] = "%{$search}%";
    }
    
    if ($department) {
        $where .= " AND u.department_id = :department";
        $params['department'] = $department;
    }
    
    if ($status !== '') {
        $where .= " AND u.is_active = :status";
        $params['status'] = $status;
    }
    
    $countSql = "SELECT COUNT(*) as total FROM users u JOIN roles r ON u.role_id = r.id WHERE {$where}";
    $total = $db->fetch($countSql, $params)['total'];
    
    $sql = "SELECT u.id, u.employee_id, u.email, u.first_name, u.last_name, u.phone,
                   u.date_of_joining, u.is_active, u.last_login,
                   u.department_id, u.designation_id,
                   d.dept_name, des.designation_name, des.grade_pay
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN designations des ON u.designation_id = des.id
            WHERE {$where}
            ORDER BY u.created_at DESC
            LIMIT {$limit} OFFSET {$offset}";
    
    $employees = $db->fetchAll($sql, $params);
    
    successResponse([
        'employees' => $employees,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get single employee
 */
function getEmployee() {
    Auth::requireAdmin();
    
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        errorResponse('Employee ID is required');
    }
    
    $db = Database::getInstance();
    
    $sql = "SELECT u.*, d.dept_name, des.designation_name, r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN designations des ON u.designation_id = des.id
            WHERE u.id = :id";
    
    $employee = $db->fetch($sql, ['id' => $id]);
    
    if (!$employee) {
        errorResponse('Employee not found', 404);
    }
    
    unset($employee['password_hash']);
    successResponse($employee);
}

/**
 * Generate unique Employee ID
 */
function generateEmployeeId($db) {
    $prefix = 'EMP';
    $sql = "SELECT employee_id FROM users WHERE employee_id LIKE :prefix ORDER BY id DESC LIMIT 1";
    $last = $db->fetch($sql, ['prefix' => $prefix . '%']);
    
    if ($last) {
        $lastNum = (int) preg_replace('/[^0-9]/', '', $last['employee_id']);
        $newNum = $lastNum + 1;
    } else {
        $newNum = 1;
    }
    
    return $prefix . str_pad($newNum, 3, '0', STR_PAD_LEFT);
}

/**
 * Create new employee (Admin only)
 */
function createEmployee() {
    Auth::requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['email', 'password', 'first_name', 'last_name'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            errorResponse("Field '{$field}' is required");
        }
    }
    
    $db = Database::getInstance();
    
    // Check if email already exists
    $existing = $db->fetch(
        "SELECT id FROM users WHERE email = :email",
        ['email' => $input['email']]
    );
    
    if ($existing) {
        errorResponse('Email already exists');
    }
    
    // Validate password
    if (strlen($input['password']) < PASSWORD_MIN_LENGTH) {
        errorResponse('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters');
    }
    
    // Handle "Other" department - create new if provided
    $departmentId = $input['department_id'] ?? null;
    if (!empty($input['other_department'])) {
        $otherDeptName = sanitize($input['other_department']);
        
        // Check if department already exists
        $existingDept = $db->fetch(
            "SELECT id FROM departments WHERE dept_name = :name",
            ['name' => $otherDeptName]
        );
        
        if ($existingDept) {
            $departmentId = $existingDept['id'];
        } else {
            // Create new department
            $deptCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $otherDeptName), 0, 10));
            $codeExists = $db->fetch("SELECT id FROM departments WHERE dept_code = :code", ['code' => $deptCode]);
            if ($codeExists) {
                $deptCode = $deptCode . rand(10, 99);
            }
            
            $departmentId = $db->insert('departments', [
                'dept_code' => $deptCode,
                'dept_name' => $otherDeptName,
                'description' => 'Created during employee registration',
                'is_active' => 1
            ]);
        }
    }
    
    // Handle "Other" designation - create new if provided
    $designationId = $input['designation_id'] ?? null;
    if (!empty($input['other_designation'])) {
        $otherDesigName = sanitize($input['other_designation']);
        
        // Check if designation already exists
        $existingDesig = $db->fetch(
            "SELECT id FROM designations WHERE designation_name = :name",
            ['name' => $otherDesigName]
        );
        
        if ($existingDesig) {
            $designationId = $existingDesig['id'];
        } else {
            // Create new designation
            $desigCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $otherDesigName), 0, 10));
            $codeExists = $db->fetch("SELECT id FROM designations WHERE designation_code = :code", ['code' => $desigCode]);
            if ($codeExists) {
                $desigCode = $desigCode . rand(10, 99);
            }
            
            $designationId = $db->insert('designations', [
                'designation_code' => $desigCode,
                'designation_name' => $otherDesigName,
                'grade_pay' => 0,
                'is_active' => 1
            ]);
        }
    }
    
    // Auto-generate Employee ID
    $employeeId = generateEmployeeId($db);
    
    // Get employee role
    $employeeRole = $db->fetch("SELECT id FROM roles WHERE role_code = 'EMPLOYEE'");
    
    try {
        $employeeData = [
            'employee_id' => $employeeId,
            'email' => sanitize($input['email']),
            'password_hash' => password_hash($input['password'], PASSWORD_BCRYPT),
            'role_id' => $employeeRole['id'],
            'first_name' => sanitize($input['first_name']),
            'last_name' => sanitize($input['last_name']),
            'phone' => sanitize($input['phone'] ?? ''),
            'department_id' => $departmentId,
            'designation_id' => $designationId,
            'date_of_birth' => $input['date_of_birth'] ?? null,
            'date_of_joining' => $input['date_of_joining'] ?? date('Y-m-d'),
            'gender' => $input['gender'] ?? null,
            'address' => sanitize($input['address'] ?? ''),
            'emergency_contact' => sanitize($input['emergency_contact'] ?? ''),
            'blood_group' => sanitize($input['blood_group'] ?? '')
        ];
        
        $id = $db->insert('users', $employeeData);
        
        logActivity($_SESSION['user_id'], 'CREATE_EMPLOYEE', 'EMPLOYEES', 
                   "Created employee: {$employeeId}", null, $employeeData);
        
        successResponse(['id' => $id, 'employee_id' => $employeeId], 'Employee created successfully');
    } catch (Exception $e) {
        errorResponse('Failed to create employee');
    }
}

/**
 * Update employee (Admin only)
 */
function updateEmployee() {
    Auth::requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    
    if (!$id) {
        errorResponse('Employee ID is required');
    }
    
    $db = Database::getInstance();
    
    $existing = $db->fetch("SELECT * FROM users WHERE id = :id", ['id' => $id]);
    if (!$existing) {
        errorResponse('Employee not found', 404);
    }
    
    // Handle "Other" department - create new if provided
    if (!empty($input['other_department'])) {
        $otherDeptName = sanitize($input['other_department']);
        
        // Check if department already exists
        $existingDept = $db->fetch(
            "SELECT id FROM departments WHERE dept_name = :name",
            ['name' => $otherDeptName]
        );
        
        if ($existingDept) {
            $input['department_id'] = $existingDept['id'];
        } else {
            // Create new department
            $deptCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $otherDeptName), 0, 10));
            $codeExists = $db->fetch("SELECT id FROM departments WHERE dept_code = :code", ['code' => $deptCode]);
            if ($codeExists) {
                $deptCode = $deptCode . rand(10, 99);
            }
            
            $input['department_id'] = $db->insert('departments', [
                'dept_code' => $deptCode,
                'dept_name' => $otherDeptName,
                'description' => 'Created during employee update',
                'is_active' => 1
            ]);
        }
    }
    
    // Handle "Other" designation - create new if provided
    if (!empty($input['other_designation'])) {
        $otherDesigName = sanitize($input['other_designation']);
        
        // Check if designation already exists
        $existingDesig = $db->fetch(
            "SELECT id FROM designations WHERE designation_name = :name",
            ['name' => $otherDesigName]
        );
        
        if ($existingDesig) {
            $input['designation_id'] = $existingDesig['id'];
        } else {
            // Create new designation
            $desigCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $otherDesigName), 0, 10));
            $codeExists = $db->fetch("SELECT id FROM designations WHERE designation_code = :code", ['code' => $desigCode]);
            if ($codeExists) {
                $desigCode = $desigCode . rand(10, 99);
            }
            
            $input['designation_id'] = $db->insert('designations', [
                'designation_code' => $desigCode,
                'designation_name' => $otherDesigName,
                'grade_pay' => 0,
                'is_active' => 1
            ]);
        }
    }
    
    $updateData = [];
    $allowedFields = ['email', 'first_name', 'last_name', 'phone', 'department_id', 'designation_id',
                      'date_of_birth', 'date_of_joining', 'gender', 'address', 'emergency_contact', 'blood_group', 'is_active'];
    
    // Handle password update if provided
    if (!empty($input['password'])) {
        if (strlen($input['password']) < PASSWORD_MIN_LENGTH) {
            errorResponse('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters');
        }
        $updateData['password_hash'] = password_hash($input['password'], PASSWORD_BCRYPT);
    }
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateData[$field] = is_string($input[$field]) ? sanitize($input[$field]) : $input[$field];
        }
    }
    
    if (empty($updateData)) {
        errorResponse('No data to update');
    }
    
    try {
        $db->update('users', $updateData, 'id = :id', ['id' => $id]);
        
        logActivity($_SESSION['user_id'], 'UPDATE_EMPLOYEE', 'EMPLOYEES',
                   "Updated employee ID: {$id}", $existing, $updateData);
        
        successResponse([], 'Employee updated successfully');
    } catch (Exception $e) {
        errorResponse('Failed to update employee');
    }
}

/**
 * Delete/Deactivate employee (Admin only)
 */
function deleteEmployee() {
    Auth::requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    
    if (!$id) {
        errorResponse('Employee ID is required');
    }
    
    $db = Database::getInstance();
    
    // Soft delete - just deactivate
    $db->update('users', ['is_active' => 0], 'id = :id', ['id' => $id]);
    
    logActivity($_SESSION['user_id'], 'DELETE_EMPLOYEE', 'EMPLOYEES', "Deactivated employee ID: {$id}");
    
    successResponse([], 'Employee deactivated successfully');
}

/**
 * Get current user profile
 */
function getProfile() {
    Auth::requireAuth();
    
    $user = Auth::user();
    
    $db = Database::getInstance();
    
    // Get leave balance
    $leaveBalance = $db->fetchAll(
        "SELECT lt.leave_name, lb.total_allocated, lb.used, 
                (lb.total_allocated - lb.used + lb.carried_forward) as available
         FROM leave_balance lb
         JOIN leave_types lt ON lb.leave_type_id = lt.id
         WHERE lb.employee_id = :id AND lb.year = YEAR(CURDATE())",
        ['id' => $user['id']]
    );
    
    successResponse([
        'profile' => [
            'id' => $user['id'],
            'employee_id' => $user['employee_id'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'department' => $user['dept_name'],
            'designation' => $user['designation_name'],
            'date_of_birth' => $user['date_of_birth'],
            'date_of_joining' => $user['date_of_joining'],
            'gender' => $user['gender'],
            'address' => $user['address'],
            'emergency_contact' => $user['emergency_contact'],
            'blood_group' => $user['blood_group'],
            'profile_photo' => $user['profile_photo']
        ],
        'leave_balance' => $leaveBalance
    ]);
}

/**
 * Update own profile
 */
function updateProfile() {
    Auth::requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'];
    
    $db = Database::getInstance();
    
    $updateData = [];
    $allowedFields = ['phone', 'address', 'emergency_contact'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateData[$field] = sanitize($input[$field]);
        }
    }
    
    if (empty($updateData)) {
        errorResponse('No data to update');
    }
    
    $db->update('users', $updateData, 'id = :id', ['id' => $userId]);
    
    logActivity($userId, 'UPDATE_PROFILE', 'PROFILE', 'Updated own profile');
    
    successResponse([], 'Profile updated successfully');
}

/**
 * Change password
 */
function changePassword() {
    Auth::requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        errorResponse('All password fields are required');
    }
    
    if ($newPassword !== $confirmPassword) {
        errorResponse('New passwords do not match');
    }
    
    if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        errorResponse('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters');
    }
    
    $db = Database::getInstance();
    $user = $db->fetch("SELECT password_hash FROM users WHERE id = :id", ['id' => $_SESSION['user_id']]);
    
    if (!password_verify($currentPassword, $user['password_hash'])) {
        errorResponse('Current password is incorrect');
    }
    
    $db->update('users', [
        'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
        'password_changed_at' => date('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $_SESSION['user_id']]);
    
    logActivity($_SESSION['user_id'], 'CHANGE_PASSWORD', 'PROFILE', 'Changed password');
    
    successResponse([], 'Password changed successfully');
}

/**
 * Get departments list
 */
function getDepartments() {
    // No auth required for registration page
    $db = Database::getInstance();
    $departments = $db->fetchAll("SELECT id, dept_code, dept_name FROM departments WHERE is_active = 1 ORDER BY dept_name");
    
    successResponse($departments);
}

/**
 * Get designations list
 */
function getDesignations() {
    // No auth required for registration page
    $db = Database::getInstance();
    $designations = $db->fetchAll("SELECT id, designation_code, designation_name FROM designations WHERE is_active = 1 ORDER BY designation_name");
    
    successResponse($designations);
}

/**
 * Get employee statistics (Admin only)
 */
function getEmployeeStats() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    
    $totalEmployees = $db->count('users u JOIN roles r ON u.role_id = r.id', "r.role_code = 'EMPLOYEE'");
    $activeEmployees = $db->count('users u JOIN roles r ON u.role_id = r.id', "r.role_code = 'EMPLOYEE' AND u.is_active = 1");
    
    $departmentWise = $db->fetchAll(
        "SELECT d.dept_name, COUNT(u.id) as count
         FROM departments d
         LEFT JOIN users u ON d.id = u.department_id
         JOIN roles r ON u.role_id = r.id
         WHERE r.role_code = 'EMPLOYEE' AND u.is_active = 1
         GROUP BY d.id
         ORDER BY count DESC"
    );
    
    successResponse([
        'total' => $totalEmployees,
        'active' => $activeEmployees,
        'inactive' => $totalEmployees - $activeEmployees,
        'by_department' => $departmentWise
    ]);
}
