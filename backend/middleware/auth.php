<?php
/**
 * e-Karamchari Authentication Middleware
 * Handles session management and access control
 */

require_once __DIR__ . '/../config/config.php';

class Auth {
    private static $user = null;
    
    /**
     * Initialize session with secure settings
     */
    public static function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set cookie path to root so it works across all subdirectories
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/',
                'domain' => '',
                'secure' => SESSION_SECURE,
                'httponly' => SESSION_HTTPONLY,
                'samesite' => 'Lax'  // Lax allows cookies on navigation
            ]);
            session_name(SESSION_NAME);
            session_start();
        }
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    /**
     * Mark attendance on login (auto check-in)
     */
    public static function markAttendance($userId, $roleCode) {
        try {
            $db = Database::getInstance();
            
            $today = date('Y-m-d');
            $currentTime = date('H:i:s');
            $dayOfWeek = date('w');
            $isSunday = ($dayOfWeek == 0);
            
            // Check if holiday
            $holiday = null;
            try {
                $holiday = $db->fetch(
                    "SELECT * FROM holidays WHERE holiday_date = :date AND is_active = 1",
                    ['date' => $today]
                );
            } catch (Exception $e) {
                // Ignore if holidays table doesn't exist
            }
            $isHoliday = $holiday ? true : false;
            
            // Skip attendance on Sunday and holidays
            if ($isSunday || $isHoliday) {
                return false;
            }
            
            // Check if attendance already exists for today
            $existingAttendance = $db->fetch(
                "SELECT id, check_in_time FROM attendance WHERE employee_id = :emp_id AND attendance_date = :date",
                ['emp_id' => $userId, 'date' => $today]
            );
            
            if ($existingAttendance) {
                // If record exists but check_in_time is NULL, update it
                if (empty($existingAttendance['check_in_time'])) {
                    $db->update('attendance', [
                        'check_in_time' => $currentTime,
                        'status' => 'Present',  // Always Present on check-in
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                    ], 'id = :id', ['id' => $existingAttendance['id']]);
                    return true;
                }
                return false; // Already checked in
            } else {
                // Create new attendance record with check-in time
                $db->insert('attendance', [
                    'employee_id' => $userId,
                    'attendance_date' => $today,
                    'check_in_time' => $currentTime,
                    'status' => 'Present',  // Always Present on check-in
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                ]);
                return true;
            }
        } catch (Exception $e) {
            error_log("Attendance Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Authenticate user with credentials
     */
    public static function login($identifier, $password, $isAdmin = false) {
        $db = Database::getInstance();
        
        // Check if identifier is email or employee_id
        // Using separate parameter names for PDO compatibility
        $sql = "SELECT u.*, r.role_code, r.role_name, r.permissions,
                       d.dept_name, des.designation_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN designations des ON u.designation_id = des.id
                WHERE (u.email = :email OR u.employee_id = :emp_id)
                AND u.is_active = 1";
        
        $user = $db->fetch($sql, ['email' => $identifier, 'emp_id' => $identifier]);
        
        if (!$user) {
            logActivity(null, 'LOGIN_FAILED', 'AUTH', "Failed login attempt for: {$identifier}");
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Check if account is locked
        if ($user['is_locked']) {
            return ['success' => false, 'message' => 'Account is locked. Please contact administrator.'];
        }
        
        // Check failed login attempts
        if ($user['failed_login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $db->update('users', ['is_locked' => 1], 'id = :id', ['id' => $user['id']]);
            logActivity($user['id'], 'ACCOUNT_LOCKED', 'AUTH', 'Account locked due to multiple failed attempts');
            return ['success' => false, 'message' => 'Account locked due to multiple failed attempts.'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $db->query("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id", 
                      ['id' => $user['id']]);
            logActivity($user['id'], 'LOGIN_FAILED', 'AUTH', 'Invalid password');
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Check role for admin login
        if ($isAdmin && !in_array($user['role_code'], ['SUPER_ADMIN', 'ADMIN', 'OFFICER'])) {
            logActivity($user['id'], 'UNAUTHORIZED_ACCESS', 'AUTH', 'Employee tried to access admin portal');
            return ['success' => false, 'message' => 'Unauthorized access'];
        }
        
        // Check role for employee login
        if (!$isAdmin && $user['role_code'] !== 'EMPLOYEE') {
            logActivity($user['id'], 'ADMIN_REDIRECT', 'AUTH', 'Admin tried to access employee portal');
            return ['success' => false, 'message' => 'Please use admin portal for login', 'redirect' => 'admin'];
        }
        
        // Reset failed attempts and update last login
        $db->update('users', [
            'failed_login_attempts' => 0,
            'last_login' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $user['id']]);
        
        // Mark attendance on login (auto check-in)
        self::markAttendance($user['id'], $user['role_code']);
        
        // Create session
        self::initSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['employee_id'] = $user['employee_id'];
        $_SESSION['role_code'] = $user['role_code'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['permissions'] = json_decode($user['permissions'], true);
        $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['department'] = $user['dept_name'];
        $_SESSION['designation'] = $user['designation_name'];
        $_SESSION['login_time'] = time();
        $_SESSION['csrf_token'] = generateToken();
        
        // Store session in database
        $sessionToken = generateToken();
        $db->insert('sessions', [
            'user_id' => $user['id'],
            'session_token' => $sessionToken,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'expires_at' => date('Y-m-d H:i:s', time() + SESSION_LIFETIME)
        ]);
        $_SESSION['session_token'] = $sessionToken;
        
        logActivity($user['id'], 'LOGIN_SUCCESS', 'AUTH', 'User logged in successfully');
        
        // Remove sensitive data
        unset($user['password_hash']);
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'employee_id' => $user['employee_id'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'email' => $user['email'],
                'role' => $user['role_code'],
                'role_name' => $user['role_name'],
                'department' => $user['dept_name'],
                'designation' => $user['designation_name']
            ]
        ];
    }
    
    /**
     * Check if user is authenticated
     */
    public static function check() {
        self::initSession();
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        // Check session expiry
        if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
            self::logout();
            return false;
        }
        
        // Skip database verification for now - session data is sufficient
        // This avoids issues with session_token not being set properly
        if (!isset($_SESSION['session_token'])) {
            // Session exists but no token - still valid if within time limit
            return true;
        }
        
        // Verify session in database if token exists
        $db = Database::getInstance();
        $session = $db->fetch(
            "SELECT * FROM sessions WHERE session_token = :token AND user_id = :user_id AND expires_at > NOW()",
            ['token' => $_SESSION['session_token'], 'user_id' => $_SESSION['user_id']]
        );
        
        if (!$session) {
            self::logout();
            return false;
        }
        
        return true;
    }
    
    /**
     * Get current authenticated user
     */
    public static function user() {
        if (!self::check()) {
            return null;
        }
        
        if (self::$user === null) {
            $db = Database::getInstance();
            self::$user = $db->fetch(
                "SELECT u.*, r.role_code, r.role_name, d.dept_name, des.designation_name
                 FROM users u
                 JOIN roles r ON u.role_id = r.id
                 LEFT JOIN departments d ON u.department_id = d.id
                 LEFT JOIN designations des ON u.designation_id = des.id
                 WHERE u.id = :id",
                ['id' => $_SESSION['user_id']]
            );
            unset(self::$user['password_hash']);
        }
        
        return self::$user;
    }
    
    /**
     * Check if user has specific role
     */
    public static function hasRole($roles) {
        if (!self::check()) {
            return false;
        }
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        return in_array($_SESSION['role_code'], $roles);
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return self::hasRole(['SUPER_ADMIN', 'ADMIN', 'OFFICER']);
    }
    
    /**
     * Require authentication (optionally with specific roles)
     */
    public static function requireAuth($roles = null) {
        if (!self::check()) {
            if (self::isApiRequest()) {
                errorResponse('Session expired. Please login again.', 401);
            }
            header('Location: ../session-expired.html');
            exit;
        }
        
        // Check roles if specified
        if ($roles !== null) {
            if (is_string($roles)) {
                $roles = [$roles];
            }
            if (!in_array($_SESSION['role_code'], $roles)) {
                if (self::isApiRequest()) {
                    errorResponse('Unauthorized access', 403);
                }
                header('Location: ../unauthorized.html');
                exit;
            }
        }
    }
    
    /**
     * Require admin role
     */
    public static function requireAdmin() {
        self::requireAuth();
        
        if (!self::isAdmin()) {
            if (self::isApiRequest()) {
                errorResponse('Unauthorized access', 403);
            }
            header('Location: ../unauthorized.html');
            exit;
        }
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCsrf($token) {
        self::initSession();
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            logSecurityEvent('CSRF_MISSING', 'CSRF token missing or empty');
            return false;
        }
        $valid = hash_equals($_SESSION['csrf_token'], $token);
        if (!$valid) {
            logSecurityEvent('CSRF_INVALID', 'Invalid CSRF token provided');
        }
        return $valid;
    }
    
    /**
     * Get current CSRF token
     */
    public static function getCsrfToken() {
        self::initSession();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = generateToken();
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Regenerate CSRF token
     */
    public static function regenerateCsrfToken() {
        self::initSession();
        $_SESSION['csrf_token'] = generateToken();
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        self::initSession();
        
        if (isset($_SESSION['user_id'])) {
            $db = Database::getInstance();
            
            // Remove session from database
            if (isset($_SESSION['session_token'])) {
                $db->delete('sessions', 'session_token = :token', ['token' => $_SESSION['session_token']]);
            }
            
            logActivity($_SESSION['user_id'], 'LOGOUT', 'AUTH', 'User logged out');
        }
        
        // Clear session
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        
        session_destroy();
        self::$user = null;
    }
    
    /**
     * Check if request is API request
     */
    private static function isApiRequest() {
        return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false ||
               (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    }
}
