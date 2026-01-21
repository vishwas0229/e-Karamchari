<?php
/**
 * e-Karamchari Two-Factor Authentication API
 * TOTP-based 2FA using Google Authenticator compatible codes
 */

require_once __DIR__ . '/../middleware/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'status':
        get2FAStatus();
        break;
    case 'setup':
        setup2FA();
        break;
    case 'verify-setup':
        verifySetup();
        break;
    case 'verify':
        verify2FA();
        break;
    case 'disable':
        disable2FA();
        break;
    case 'backup-codes':
        getBackupCodes();
        break;
    case 'regenerate-backup':
        regenerateBackupCodes();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * TOTP Class - Time-based One-Time Password
 */
class TOTP {
    private static $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    
    /**
     * Generate a random secret key
     */
    public static function generateSecret($length = 16) {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[random_int(0, 31)];
        }
        return $secret;
    }
    
    /**
     * Generate TOTP code
     */
    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }
        
        $secretKey = self::base32Decode($secret);
        
        // Pack time into binary string
        $time = chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', $timeSlice);
        
        // Generate HMAC-SHA1 hash
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        
        // Get offset from last nibble
        $offset = ord(substr($hash, -1)) & 0x0F;
        
        // Get 4 bytes from hash starting at offset
        $hashPart = substr($hash, $offset, 4);
        
        // Unpack as unsigned long (big endian)
        $value = unpack('N', $hashPart)[1];
        
        // Only use 31 bits
        $value = $value & 0x7FFFFFFF;
        
        // Get 6-digit code
        $code = $value % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verify TOTP code (with time drift tolerance)
     */
    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);
        
        // Check codes within time window
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate QR code URL for Google Authenticator
     * Returns otpauth URI - frontend will generate QR using JS library
     */
    public static function getQRCodeUrl($secret, $accountName, $issuer = 'e-Karamchari') {
        $url = 'otpauth://totp/' . rawurlencode($issuer . ':' . $accountName);
        $url .= '?secret=' . $secret;
        $url .= '&issuer=' . rawurlencode($issuer);
        $url .= '&algorithm=SHA1';
        $url .= '&digits=6';
        $url .= '&period=30';
        
        return $url;
    }
    
    /**
     * Base32 decode
     */
    private static function base32Decode($input) {
        $input = strtoupper($input);
        $output = '';
        $v = 0;
        $vbits = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $pos = strpos(self::$base32Chars, $input[$i]);
            if ($pos === false) continue;
            
            $v = ($v << 5) | $pos;
            $vbits += 5;
            
            if ($vbits >= 8) {
                $vbits -= 8;
                $output .= chr(($v >> $vbits) & 0xFF);
            }
        }
        
        return $output;
    }
}

/**
 * Generate backup codes
 */
function generateBackupCodes($count = 8) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(4)));
    }
    return $codes;
}

/**
 * Check if 2FA table exists, create if not
 */
function ensure2FATable() {
    $db = Database::getInstance();
    
    // Always try to create table if not exists
    $db->query("
        CREATE TABLE IF NOT EXISTS `two_factor_auth` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `secret_key` VARCHAR(32) NOT NULL,
            `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
            `backup_codes` JSON,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_user_2fa` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Get 2FA status for current user
 */
function get2FAStatus() {
    Auth::requireAuth();
    ensure2FATable();
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    $twoFA = $db->fetch(
        "SELECT is_enabled, created_at FROM two_factor_auth WHERE user_id = :user_id",
        ['user_id' => $userId]
    );
    
    successResponse([
        'enabled' => $twoFA ? (bool)$twoFA['is_enabled'] : false,
        'setup_date' => $twoFA ? $twoFA['created_at'] : null
    ]);
}

/**
 * Setup 2FA - Generate secret and QR code
 */
function setup2FA() {
    try {
        Auth::requireAuth();
        ensure2FATable();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 405);
        }
        
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        
        // Check if already enabled
        $existing = $db->fetch(
            "SELECT is_enabled FROM two_factor_auth WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
        
        if ($existing && $existing['is_enabled']) {
            errorResponse('2FA is already enabled. Disable it first to reconfigure.');
        }
        
        // Generate new secret
        $secret = TOTP::generateSecret();
        $backupCodes = generateBackupCodes();
        
        // Get user email for QR code
        $user = $db->fetch("SELECT email, employee_id FROM users WHERE id = :id", ['id' => $userId]);
        
        if (!$user) {
            errorResponse('User not found');
        }
        
        $accountName = $user['employee_id'] ?: $user['email'];
        
        // Store secret (not enabled yet)
        if ($existing) {
            $db->update('two_factor_auth', [
                'secret_key' => $secret,
                'backup_codes' => json_encode($backupCodes),
                'is_enabled' => 0
            ], 'user_id = :user_id', ['user_id' => $userId]);
        } else {
            $db->insert('two_factor_auth', [
                'user_id' => $userId,
                'secret_key' => $secret,
                'backup_codes' => json_encode($backupCodes),
                'is_enabled' => 0
            ]);
        }
        
        // Generate QR code URL (otpauth URI)
        $otpauthUrl = TOTP::getQRCodeUrl($secret, $accountName);
        
        successResponse([
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
            'backup_codes' => $backupCodes,
            'account_name' => $accountName
        ], 'Scan the QR code with Google Authenticator app');
        
    } catch (Exception $e) {
        error_log("2FA Setup Error: " . $e->getMessage());
        errorResponse('Error setting up 2FA: ' . $e->getMessage(), 500);
    }
}

/**
 * Verify setup and enable 2FA
 */
function verifySetup() {
    Auth::requireAuth();
    ensure2FATable();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $code = $input['code'] ?? '';
    
    if (empty($code) || strlen($code) !== 6) {
        errorResponse('Please enter a valid 6-digit code');
    }
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    $twoFA = $db->fetch(
        "SELECT secret_key FROM two_factor_auth WHERE user_id = :user_id",
        ['user_id' => $userId]
    );
    
    if (!$twoFA) {
        errorResponse('Please setup 2FA first');
    }
    
    // Verify the code
    if (!TOTP::verifyCode($twoFA['secret_key'], $code)) {
        logSecurityEvent('2FA_SETUP_FAILED', "User ID: {$userId} - Invalid verification code");
        errorResponse('Invalid code. Please try again.');
    }
    
    // Enable 2FA
    $db->update('two_factor_auth', ['is_enabled' => 1], 'user_id = :user_id', ['user_id' => $userId]);
    
    logActivity($userId, '2FA_ENABLED', 'SECURITY', 'Two-factor authentication enabled');
    logSecurityEvent('2FA_ENABLED', "User ID: {$userId}");
    
    successResponse([], 'Two-factor authentication enabled successfully!');
}

/**
 * Verify 2FA code during login
 */
function verify2FA() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = (int)($input['user_id'] ?? 0);
    $code = trim($input['code'] ?? '');
    $tempToken = $input['temp_token'] ?? '';
    
    if (!$userId || empty($code)) {
        errorResponse('User ID and code are required');
    }
    
    // Verify temp token (stored in session during login)
    Auth::initSession();
    if (!isset($_SESSION['2fa_pending']) || 
        $_SESSION['2fa_pending']['user_id'] !== $userId ||
        $_SESSION['2fa_pending']['token'] !== $tempToken) {
        logSecurityEvent('2FA_INVALID_TOKEN', "User ID: {$userId} - Invalid temp token");
        errorResponse('Invalid or expired verification session. Please login again.');
    }
    
    // Check if temp token expired (5 minutes)
    if (time() - $_SESSION['2fa_pending']['time'] > 300) {
        unset($_SESSION['2fa_pending']);
        errorResponse('Verification session expired. Please login again.');
    }
    
    ensure2FATable();
    $db = Database::getInstance();
    
    $twoFA = $db->fetch(
        "SELECT secret_key, backup_codes FROM two_factor_auth WHERE user_id = :user_id AND is_enabled = 1",
        ['user_id' => $userId]
    );
    
    if (!$twoFA) {
        errorResponse('2FA not configured for this user');
    }
    
    $verified = false;
    
    // Check if it's a TOTP code (6 digits)
    if (strlen($code) === 6 && ctype_digit($code)) {
        $verified = TOTP::verifyCode($twoFA['secret_key'], $code);
    }
    // Check if it's a backup code (8 characters)
    elseif (strlen($code) === 8) {
        $backupCodes = json_decode($twoFA['backup_codes'], true) ?: [];
        $codeUpper = strtoupper($code);
        
        $index = array_search($codeUpper, $backupCodes);
        if ($index !== false) {
            // Remove used backup code
            unset($backupCodes[$index]);
            $db->update('two_factor_auth', [
                'backup_codes' => json_encode(array_values($backupCodes))
            ], 'user_id = :user_id', ['user_id' => $userId]);
            
            $verified = true;
            logActivity($userId, '2FA_BACKUP_USED', 'SECURITY', 'Backup code used for login');
        }
    }
    
    if (!$verified) {
        logSecurityEvent('2FA_FAILED', "User ID: {$userId} - Invalid code");
        errorResponse('Invalid verification code');
    }
    
    // 2FA verified - complete login
    $userData = $_SESSION['2fa_pending']['user_data'];
    unset($_SESSION['2fa_pending']);
    
    // Mark attendance on login (auto check-in)
    Auth::markAttendance($userData['id'], $userData['role_code']);
    
    // Set session data
    $_SESSION['user_id'] = $userData['id'];
    $_SESSION['employee_id'] = $userData['employee_id'];
    $_SESSION['role_code'] = $userData['role_code'];
    $_SESSION['role_name'] = $userData['role_name'];
    $_SESSION['full_name'] = $userData['full_name'];
    $_SESSION['email'] = $userData['email'];
    $_SESSION['login_time'] = time();
    $_SESSION['csrf_token'] = generateToken();
    
    // Store session in database
    $sessionToken = generateToken();
    $db->insert('sessions', [
        'user_id' => $userData['id'],
        'session_token' => $sessionToken,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'expires_at' => date('Y-m-d H:i:s', time() + SESSION_LIFETIME)
    ]);
    $_SESSION['session_token'] = $sessionToken;
    
    logActivity($userData['id'], 'LOGIN_2FA_SUCCESS', 'AUTH', '2FA verification successful');
    logSecurityEvent('2FA_SUCCESS', "User ID: {$userId}");
    
    successResponse([
        'user' => [
            'id' => $userData['id'],
            'employee_id' => $userData['employee_id'],
            'name' => $userData['full_name'],
            'email' => $userData['email'],
            'role' => $userData['role_code'],
            'role_name' => $userData['role_name']
        ]
    ], 'Login successful');
}

/**
 * Disable 2FA
 */
function disable2FA() {
    Auth::requireAuth();
    ensure2FATable();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';
    
    if (empty($password)) {
        errorResponse('Password is required to disable 2FA');
    }
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    // Verify password
    $user = $db->fetch("SELECT password_hash FROM users WHERE id = :id", ['id' => $userId]);
    
    if (!password_verify($password, $user['password_hash'])) {
        logSecurityEvent('2FA_DISABLE_FAILED', "User ID: {$userId} - Invalid password");
        errorResponse('Invalid password');
    }
    
    // Disable 2FA
    $db->delete('two_factor_auth', 'user_id = :user_id', ['user_id' => $userId]);
    
    logActivity($userId, '2FA_DISABLED', 'SECURITY', 'Two-factor authentication disabled');
    logSecurityEvent('2FA_DISABLED', "User ID: {$userId}");
    
    successResponse([], 'Two-factor authentication disabled');
}

/**
 * Get remaining backup codes count
 */
function getBackupCodes() {
    Auth::requireAuth();
    ensure2FATable();
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    $twoFA = $db->fetch(
        "SELECT backup_codes FROM two_factor_auth WHERE user_id = :user_id AND is_enabled = 1",
        ['user_id' => $userId]
    );
    
    if (!$twoFA) {
        errorResponse('2FA not enabled');
    }
    
    $codes = json_decode($twoFA['backup_codes'], true) ?: [];
    
    successResponse([
        'remaining_count' => count($codes)
    ]);
}

/**
 * Regenerate backup codes
 */
function regenerateBackupCodes() {
    Auth::requireAuth();
    ensure2FATable();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $code = $input['code'] ?? '';
    
    if (empty($code)) {
        errorResponse('Please enter your current 2FA code');
    }
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    $twoFA = $db->fetch(
        "SELECT secret_key FROM two_factor_auth WHERE user_id = :user_id AND is_enabled = 1",
        ['user_id' => $userId]
    );
    
    if (!$twoFA) {
        errorResponse('2FA not enabled');
    }
    
    // Verify current code
    if (!TOTP::verifyCode($twoFA['secret_key'], $code)) {
        errorResponse('Invalid verification code');
    }
    
    // Generate new backup codes
    $newCodes = generateBackupCodes();
    
    $db->update('two_factor_auth', [
        'backup_codes' => json_encode($newCodes)
    ], 'user_id = :user_id', ['user_id' => $userId]);
    
    logActivity($userId, '2FA_BACKUP_REGENERATED', 'SECURITY', 'Backup codes regenerated');
    
    successResponse([
        'backup_codes' => $newCodes
    ], 'New backup codes generated. Save them securely!');
}
