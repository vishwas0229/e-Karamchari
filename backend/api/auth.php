<?php
/**
 * e-Karamchari Authentication API
 * Handles login, logout, and session management
 */

require_once __DIR__ . '/../middleware/auth.php';

setCorsHeaders();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'admin-login':
        handleAdminLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check':
        checkSession();
        break;
    case 'csrf':
        getCsrfToken();
        break;
    case 'user':
        getCurrentUser();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Handle employee login
 */
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $identifier = sanitize($input['employee_id'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($identifier) || empty($password)) {
            errorResponse('Employee ID/Email and password are required');
        }
        
        // First verify credentials - support both employee_id and email
        $db = Database::getInstance();
        
        $sql = "SELECT u.*, r.role_code, r.role_name 
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE (u.employee_id = :emp_id OR u.email = :email) AND u.is_active = 1";
        
        $user = $db->fetch($sql, ['emp_id' => $identifier, 'email' => $identifier]);
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            errorResponse('Invalid credentials', 401);
        }
        
        // Check if 2FA is enabled
        $twoFA = null;
        try {
            $twoFA = $db->fetch(
                "SELECT is_enabled FROM two_factor_auth WHERE user_id = :user_id AND is_enabled = 1",
                ['user_id' => $user['id']]
            );
        } catch (Exception $e) {
            // Table might not exist yet
        }
        
        if ($twoFA && $twoFA['is_enabled']) {
            // 2FA enabled - create pending session
            Auth::initSession();
            $tempToken = generateToken();
            
            $_SESSION['2fa_pending'] = [
                'user_id' => $user['id'],
                'token' => $tempToken,
                'time' => time(),
                'user_data' => [
                    'id' => $user['id'],
                    'employee_id' => $user['employee_id'],
                    'role_code' => $user['role_code'],
                    'role_name' => $user['role_name'],
                    'full_name' => $user['first_name'] . ' ' . $user['last_name'],
                    'email' => $user['email']
                ]
            ];
            
            logActivity($user['id'], 'LOGIN_2FA_PENDING', 'AUTH', '2FA verification required');
            
            successResponse([
                'requires_2fa' => true,
                'user_id' => $user['id'],
                'temp_token' => $tempToken
            ], '2FA verification required');
            return;
        }
        
        // No 2FA - proceed with normal login
        $result = Auth::login($identifier, $password, false);
        
        if ($result['success']) {
            // Mark attendance for employee
            Auth::markAttendance($user['id'], $user['role_code']);
            successResponse($result['user'], $result['message']);
        } else {
            errorResponse($result['message'], 401);
        }
    } catch (Exception $e) {
        error_log("Login Error: " . $e->getMessage());
        errorResponse('Login failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle admin login
 */
function handleAdminLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $identifier = sanitize($input['identifier'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($identifier) || empty($password)) {
        errorResponse('Admin ID/Email and password are required');
    }
    
    // First verify credentials without creating full session
    $db = Database::getInstance();
    
    $sql = "SELECT u.*, r.role_code, r.role_name, r.permissions, d.dept_name, des.designation_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN designations des ON u.designation_id = des.id
            WHERE (u.email = :email OR u.employee_id = :emp_id)
            AND u.is_active = 1";
    
    $user = $db->fetch($sql, ['email' => $identifier, 'emp_id' => $identifier]);
    
    if (!$user) {
        logActivity(null, 'LOGIN_FAILED', 'AUTH', "Failed login attempt for: {$identifier}");
        errorResponse('Invalid credentials', 401);
    }
    
    // Check if account is locked
    if ($user['is_locked']) {
        errorResponse('Account is locked. Please contact administrator.', 401);
    }
    
    // Check failed login attempts
    if ($user['failed_login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $db->update('users', ['is_locked' => 1], 'id = :id', ['id' => $user['id']]);
        errorResponse('Account locked due to multiple failed attempts.', 401);
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        $db->query("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id", 
                  ['id' => $user['id']]);
        errorResponse('Invalid credentials', 401);
    }
    
    // Check role for admin login
    if (!in_array($user['role_code'], ['SUPER_ADMIN', 'ADMIN', 'OFFICER'])) {
        logActivity($user['id'], 'UNAUTHORIZED_ACCESS', 'AUTH', 'Employee tried to access admin portal');
        errorResponse('Unauthorized access', 403);
    }
    
    // Reset failed attempts
    $db->update('users', ['failed_login_attempts' => 0], 'id = :id', ['id' => $user['id']]);
    
    // Check if 2FA is enabled for this user
    $twoFA = null;
    try {
        $twoFA = $db->fetch(
            "SELECT is_enabled FROM two_factor_auth WHERE user_id = :user_id AND is_enabled = 1",
            ['user_id' => $user['id']]
        );
    } catch (Exception $e) {
        // Table might not exist yet, ignore
    }
    
    if ($twoFA && $twoFA['is_enabled']) {
        // 2FA is enabled - create pending session
        Auth::initSession();
        $tempToken = generateToken();
        
        $_SESSION['2fa_pending'] = [
            'user_id' => $user['id'],
            'token' => $tempToken,
            'time' => time(),
            'user_data' => [
                'id' => $user['id'],
                'employee_id' => $user['employee_id'],
                'role_code' => $user['role_code'],
                'role_name' => $user['role_name'],
                'full_name' => $user['first_name'] . ' ' . $user['last_name'],
                'email' => $user['email']
            ]
        ];
        
        logActivity($user['id'], 'LOGIN_2FA_PENDING', 'AUTH', '2FA verification required');
        
        successResponse([
            'requires_2fa' => true,
            'user_id' => $user['id'],
            'temp_token' => $tempToken
        ], '2FA verification required');
        return;
    }
    
    // No 2FA - proceed with normal login
    $result = Auth::login($identifier, $password, true);
    
    if ($result['success']) {
        // Mark attendance for admin
        Auth::markAttendance($user['id'], $user['role_code']);
        successResponse($result['user'], $result['message']);
    } else {
        $statusCode = isset($result['redirect']) ? 403 : 401;
        errorResponse($result['message'], $statusCode);
    }
}

/**
 * Handle logout
 */
function handleLogout() {
    Auth::logout();
    successResponse([], 'Logged out successfully');
}

/**
 * Check session status
 */
function checkSession() {
    if (Auth::check()) {
        $user = Auth::user();
        $userData = null;
        
        if ($user) {
            $userData = [
                'id' => $user['id'],
                'employee_id' => $user['employee_id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'email' => $user['email'],
                'role' => $user['role_code'],
                'role_name' => $user['role_name'],
                'department' => $user['dept_name'],
                'designation' => $user['designation_name']
            ];
        }
        
        successResponse([
            'authenticated' => true,
            'role' => $_SESSION['role_code'],
            'user' => $userData,
            'expires_in' => SESSION_LIFETIME - (time() - $_SESSION['login_time'])
        ]);
    } else {
        successResponse(['authenticated' => false]);
    }
}

/**
 * Get CSRF token
 */
function getCsrfToken() {
    Auth::initSession();
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken();
    }
    
    successResponse(['csrf_token' => $_SESSION['csrf_token']]);
}

/**
 * Get current user details
 */
function getCurrentUser() {
    Auth::requireAuth();
    
    $user = Auth::user();
    
    if ($user) {
        successResponse([
            'id' => $user['id'],
            'employee_id' => $user['employee_id'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'role' => $user['role_code'],
            'role_name' => $user['role_name'],
            'department' => $user['dept_name'],
            'designation' => $user['designation_name'],
            'profile_photo' => $user['profile_photo'],
            'date_of_joining' => $user['date_of_joining']
        ]);
    } else {
        errorResponse('User not found', 404);
    }
}
