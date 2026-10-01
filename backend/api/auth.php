<?php
/**
 * e-Karamchari Authentication API
 * Handles login, logout, and session management
 */

require_once __DIR__ . '/../middleware/auth.php';

setCorsHeaders();

// Apply the general API rate limit before dispatching the request.
// Authentication endpoints also apply a stricter per-identifier limit.
checkRateLimit();

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
        if ($identifier !== '') {
            checkRateLimit('login:' . strtolower($identifier));
        }

        if (empty($identifier) || empty($password)) {
            errorResponse('Employee ID/Email and password are required');
        }

        // Use the canonical authentication path. Session creation is deferred
        // until after 2FA, so credentials are verified only once.
        $result = Auth::login($identifier, $password, false, false);
        if (!$result['success']) {
            errorResponse($result['message'], isset($result['redirect']) ? 403 : 401);
        }

        $user = $result['user'];

        $db = Database::getInstance();
        $twoFA = null;
        try {
            $twoFA = $db->fetch(
                "SELECT is_enabled FROM two_factor_auth WHERE user_id = :user_id AND is_enabled = 1",
                ['user_id' => $user['id']]
            );
        } catch (Exception $e) {
            // Table might not exist yet.
        }

        if ($twoFA && $twoFA['is_enabled']) {
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
        }

        $result = Auth::completeLogin($user);
        if ($result['success']) {
            successResponse($result['user'], $result['message']);
        }

        errorResponse($result['message'], 401);
    } catch (Exception $e) {
        error_log("Login Error: " . $e->getMessage());
        errorResponse('Login failed', 500);
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

    if ($identifier !== '') {
        checkRateLimit('admin-login:' . strtolower($identifier));
    }

    if (empty($identifier) || empty($password)) {
        errorResponse('Admin ID/Email and password are required');
    }

    // Use the same canonical authentication path as employee login.
    // The role boundary is enforced by Auth::login().
    $result = Auth::login($identifier, $password, true, false);
    if (!$result['success']) {
        errorResponse($result['message'], isset($result['redirect']) ? 403 : 401);
    }

    $user = $result['user'];
    $db = Database::getInstance();

    $twoFA = null;
    try {
        $twoFA = $db->fetch(
            "SELECT is_enabled FROM two_factor_auth WHERE user_id = :user_id AND is_enabled = 1",
            ['user_id' => $user['id']]
        );
    } catch (Exception $e) {
        // Table might not exist yet.
    }

    if ($twoFA && $twoFA['is_enabled']) {
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
    }

    $result = Auth::completeLogin($user);
    if ($result['success']) {
        successResponse($result['user'], $result['message']);
    }

    errorResponse($result['message'], 401);
}

/**
 * Handle logout
 */
function handleLogout() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

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
