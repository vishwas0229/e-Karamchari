<?php
/**
 * Notification Helper Functions
 * e-Karamchari System
 */

if (!defined('EKARAMCHARI')) {
    define('EKARAMCHARI', true);
}

require_once __DIR__ . '/../config/database.php';

/**
 * Notify all admins
 */
function notifyAdmins($title, $message, $type = 'Info', $link = null) {
    $db = Database::getInstance();
    
    try {
        $admins = $db->fetchAll(
            "SELECT u.id FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE r.role_code IN ('SUPER_ADMIN', 'ADMIN', 'OFFICER') AND u.is_active = 1"
        );
        
        foreach ($admins as $admin) {
            $db->insert('notifications', [
                'user_id' => $admin['id'],
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'link' => $link,
                'is_read' => 0
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to notify admins: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify specific user
 */
function notifyUser($userId, $title, $message, $type = 'Info', $link = null) {
    $db = Database::getInstance();
    
    try {
        $db->insert('notifications', [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
            'is_read' => 0
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to notify user {$userId}: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify users by role
 */
function notifyByRole($roleCodes, $title, $message, $type = 'Info', $link = null) {
    $db = Database::getInstance();
    
    try {
        $placeholders = implode(',', array_fill(0, count($roleCodes), '?'));
        
        $users = $db->fetchAll(
            "SELECT u.id FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE r.role_code IN ({$placeholders}) AND u.is_active = 1",
            $roleCodes
        );
        
        foreach ($users as $user) {
            $db->insert('notifications', [
                'user_id' => $user['id'],
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'link' => $link,
                'is_read' => 0
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to notify by role: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify users in department
 */
function notifyDepartment($departmentId, $title, $message, $type = 'Info', $link = null) {
    $db = Database::getInstance();
    
    try {
        $users = $db->fetchAll(
            "SELECT id FROM users WHERE department_id = :dept_id AND is_active = 1",
            ['dept_id' => $departmentId]
        );
        
        foreach ($users as $user) {
            $db->insert('notifications', [
                'user_id' => $user['id'],
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'link' => $link,
                'is_read' => 0
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to notify department: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's full name
 */
function getUserName($userId) {
    $db = Database::getInstance();
    $user = $db->fetch(
        "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = :id",
        ['id' => $userId]
    );
    return $user ? $user['name'] : 'Unknown User';
}
