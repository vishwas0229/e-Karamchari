<?php
/**
 * e-Karamchari Main Configuration
 * Municipal Corporation of Delhi - Employee Self-Service Portal
 */

// Define application constant
define('EKARAMCHARI', true);

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Application Settings
define('APP_NAME', 'e-Karamchari');
define('APP_VERSION', '1.0.0');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/e-Karamchari');

// Session Configuration
define('SESSION_NAME', 'EKARAMCHARI_SESSION');
define('SESSION_LIFETIME', 28800); // 8 hours
define('SESSION_SECURE', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'); // Auto-detect HTTPS
define('SESSION_HTTPONLY', true);

// Security Settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes
define('RATE_LIMIT_REQUESTS', 100); // Max requests per minute
define('RATE_LIMIT_WINDOW', 60); // 1 minute window

// File Upload Settings
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// Pagination
define('DEFAULT_PAGE_SIZE', 10);
define('MAX_PAGE_SIZE', 100);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Include Database Configuration
require_once __DIR__ . '/database.php';

// Security Headers
function setSecurityHeaders() {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    // Enable XSS filter
    header('X-XSS-Protection: 1; mode=block');
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';");
    // Permissions Policy
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// CORS Headers for API
function setCorsHeaders() {
    // Set security headers first
    setSecurityHeaders();
    
    // Allow requests from same origin
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    // Allow localhost with any port/path for development
    if (strpos($origin, 'localhost') !== false || strpos($origin, '127.0.0.1') !== false || empty($origin)) {
        header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    } else {
        header('Access-Control-Allow-Origin: ' . APP_URL);
    }
    
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Access-Control-Allow-Credentials: true');
    header('Content-Type: application/json; charset=UTF-8');
}

// Rate Limiting
function checkRateLimit($identifier = null) {
    $identifier = $identifier ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $cacheFile = __DIR__ . '/../logs/rate_limit_' . md5($identifier) . '.json';
    
    $now = time();
    $requests = [];
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data) {
            // Filter requests within the time window
            $requests = array_filter($data, function($timestamp) use ($now) {
                return ($now - $timestamp) < RATE_LIMIT_WINDOW;
            });
        }
    }
    
    if (count($requests) >= RATE_LIMIT_REQUESTS) {
        errorResponse('Too many requests. Please try again later.', 429);
    }
    
    $requests[] = $now;
    file_put_contents($cacheFile, json_encode(array_values($requests)));
}

// JSON Response Helper
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Error Response Helper
function errorResponse($message, $statusCode = 400, $errors = []) {
    jsonResponse([
        'success' => false,
        'message' => $message,
        'errors' => $errors
    ], $statusCode);
}

// Success Response Helper
function successResponse($data = [], $message = 'Success') {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

// Sanitize Input - Enhanced
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    if (is_null($input)) {
        return null;
    }
    // Remove null bytes
    $input = str_replace(chr(0), '', $input);
    // Strip tags and encode special chars
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Validate Email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Generate Random Token - Cryptographically secure
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Generate Request Number
function generateRequestNumber($prefix = 'REQ') {
    return $prefix . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

// Validate and sanitize integer
function sanitizeInt($value, $min = null, $max = null) {
    $value = filter_var($value, FILTER_VALIDATE_INT);
    if ($value === false) return null;
    if ($min !== null && $value < $min) return $min;
    if ($max !== null && $value > $max) return $max;
    return $value;
}

// Check for SQL injection patterns
function hasSqlInjection($input) {
    $patterns = [
        '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i',
        '/((\%3D)|(=))[^\n]*((\%27)|(\')|(\-\-)|(\%3B)|(;))/i',
        '/\w*((\%27)|(\'))((\%6F)|o|(\%4F))((\%72)|r|(\%52))/i',
        '/((\%27)|(\'))union/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    return false;
}

// Log Activity
function logActivity($userId, $action, $module, $description = '', $oldValues = null, $newValues = null) {
    try {
        $db = Database::getInstance();
        $db->insert('activity_logs', [
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
    } catch (Exception $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

// Log security event
function logSecurityEvent($event, $details = '') {
    $logFile = __DIR__ . '/../logs/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $logEntry = "[{$timestamp}] [{$event}] IP: {$ip} | {$details} | UA: {$userAgent}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
