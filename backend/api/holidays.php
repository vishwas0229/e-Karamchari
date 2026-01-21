<?php
/**
 * e-Karamchari Holidays API
 * Handles holiday management with recurring templates
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
        getHolidays();
        break;
    case 'monthly':
        getMonthlyHolidays();
        break;
    case 'upcoming':
        getUpcomingHolidays();
        break;
    case 'templates':
        Auth::requireAdmin();
        getTemplates();
        break;
    case 'generate':
        Auth::requireAdmin();
        generateYearHolidays();
        break;
    case 'create':
        Auth::requireAdmin();
        createHoliday();
        break;
    case 'update':
        Auth::requireAdmin();
        updateHoliday();
        break;
    case 'delete':
        Auth::requireAdmin();
        deleteHoliday();
        break;
    case 'update-template':
        Auth::requireAdmin();
        updateTemplate();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Get holidays for a year
 */
function getHolidays() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $year = (int)($_GET['year'] ?? date('Y'));
    
    $holidays = $db->fetchAll(
        "SELECT * FROM holidays 
         WHERE YEAR(holiday_date) = :year AND is_active = 1
         ORDER BY holiday_date",
        ['year' => $year]
    );
    
    // Group by month
    $byMonth = [];
    foreach ($holidays as $h) {
        $month = (int)date('n', strtotime($h['holiday_date']));
        if (!isset($byMonth[$month])) {
            $byMonth[$month] = [];
        }
        $byMonth[$month][] = $h;
    }
    
    successResponse([
        'holidays' => $holidays,
        'by_month' => $byMonth,
        'year' => $year,
        'total' => count($holidays)
    ]);
}

/**
 * Get holidays for a specific month
 */
function getMonthlyHolidays() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $month = (int)($_GET['month'] ?? date('n'));
    $year = (int)($_GET['year'] ?? date('Y'));
    
    $holidays = $db->fetchAll(
        "SELECT * FROM holidays 
         WHERE MONTH(holiday_date) = :month 
         AND YEAR(holiday_date) = :year 
         AND is_active = 1
         ORDER BY holiday_date",
        ['month' => $month, 'year' => $year]
    );
    
    $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 
                   'July', 'August', 'September', 'October', 'November', 'December'];
    
    successResponse([
        'holidays' => $holidays,
        'month' => $month,
        'month_name' => $monthNames[$month],
        'year' => $year,
        'total' => count($holidays)
    ]);
}

/**
 * Get upcoming holidays (next 30 days)
 */
function getUpcomingHolidays() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $days = (int)($_GET['days'] ?? 30);
    $days = min(90, max(7, $days)); // Between 7 and 90 days
    
    $holidays = $db->fetchAll(
        "SELECT * FROM holidays 
         WHERE holiday_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
         AND is_active = 1
         ORDER BY holiday_date",
        ['days' => $days]
    );
    
    // Add days remaining
    foreach ($holidays as &$h) {
        $h['days_remaining'] = (int)((strtotime($h['holiday_date']) - strtotime(date('Y-m-d'))) / 86400);
    }
    
    successResponse([
        'holidays' => $holidays,
        'total' => count($holidays)
    ]);
}

/**
 * Get holiday templates
 */
function getTemplates() {
    $db = Database::getInstance();
    
    // Check if templates table exists
    $tableExists = $db->fetch(
        "SELECT COUNT(*) as cnt FROM information_schema.tables 
         WHERE table_schema = DATABASE() AND table_name = 'holiday_templates'"
    );
    
    if (!$tableExists || $tableExists['cnt'] == 0) {
        successResponse([
            'templates' => [],
            'message' => 'Holiday templates table not found. Please run the migration.'
        ]);
        return;
    }
    
    $templates = $db->fetchAll(
        "SELECT * FROM holiday_templates WHERE is_active = 1 ORDER BY fixed_month, fixed_day, holiday_name"
    );
    
    successResponse([
        'templates' => $templates,
        'fixed_count' => count(array_filter($templates, fn($t) => $t['date_type'] === 'Fixed')),
        'floating_count' => count(array_filter($templates, fn($t) => $t['date_type'] === 'Floating'))
    ]);
}


/**
 * Generate holidays for a year from templates
 */
function generateYearHolidays() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $year = (int)($input['year'] ?? date('Y'));
    $overwrite = (bool)($input['overwrite'] ?? false);
    
    $db = Database::getInstance();
    
    // Check if templates table exists
    $tableExists = $db->fetch(
        "SELECT COUNT(*) as cnt FROM information_schema.tables 
         WHERE table_schema = DATABASE() AND table_name = 'holiday_templates'"
    );
    
    if (!$tableExists || $tableExists['cnt'] == 0) {
        // Generate from hardcoded list if no templates
        return generateDefaultHolidays($db, $year, $overwrite);
    }
    
    // Get fixed date templates
    $fixedTemplates = $db->fetchAll(
        "SELECT * FROM holiday_templates WHERE date_type = 'Fixed' AND is_active = 1"
    );
    
    $created = 0;
    $skipped = 0;
    
    // Delete existing if overwrite
    if ($overwrite) {
        $db->query("DELETE FROM holidays WHERE YEAR(holiday_date) = :year", ['year' => $year]);
    }
    
    // Generate fixed holidays
    foreach ($fixedTemplates as $template) {
        $date = sprintf('%04d-%02d-%02d', $year, $template['fixed_month'], $template['fixed_day']);
        
        // Check if already exists
        $exists = $db->fetch(
            "SELECT id FROM holidays WHERE holiday_date = :date",
            ['date' => $date]
        );
        
        if ($exists && !$overwrite) {
            $skipped++;
            continue;
        }
        
        if (!$exists) {
            $db->insert('holidays', [
                'holiday_date' => $date,
                'holiday_name' => $template['holiday_name'],
                'holiday_type' => $template['holiday_type'],
                'is_active' => 1
            ]);
            $created++;
        }
    }
    
    logActivity($_SESSION['user_id'], 'GENERATE_HOLIDAYS', 'SETTINGS', 
               "Generated {$created} holidays for year {$year}");
    
    successResponse([
        'created' => $created,
        'skipped' => $skipped,
        'year' => $year,
        'message' => "Generated {$created} fixed holidays. Floating holidays need manual dates."
    ]);
}

/**
 * Generate default holidays without templates
 */
function generateDefaultHolidays($db, $year, $overwrite) {
    // Default Indian holidays with fixed dates
    $defaultHolidays = [
        ['month' => 1, 'day' => 1, 'name' => 'New Year', 'type' => 'Optional'],
        ['month' => 1, 'day' => 14, 'name' => 'Makar Sankranti / Pongal', 'type' => 'Regional'],
        ['month' => 1, 'day' => 26, 'name' => 'Republic Day', 'type' => 'National'],
        ['month' => 2, 'day' => 19, 'name' => 'Chhatrapati Shivaji Maharaj Jayanti', 'type' => 'Regional'],
        ['month' => 4, 'day' => 14, 'name' => 'Dr. Ambedkar Jayanti', 'type' => 'National'],
        ['month' => 5, 'day' => 1, 'name' => 'May Day / Labour Day', 'type' => 'National'],
        ['month' => 8, 'day' => 15, 'name' => 'Independence Day', 'type' => 'National'],
        ['month' => 10, 'day' => 2, 'name' => 'Gandhi Jayanti', 'type' => 'National'],
        ['month' => 10, 'day' => 31, 'name' => 'Sardar Vallabhbhai Patel Jayanti', 'type' => 'Optional'],
        ['month' => 11, 'day' => 14, 'name' => 'Children\'s Day', 'type' => 'Optional'],
        ['month' => 12, 'day' => 25, 'name' => 'Christmas', 'type' => 'National'],
    ];
    
    if ($overwrite) {
        $db->query("DELETE FROM holidays WHERE YEAR(holiday_date) = :year", ['year' => $year]);
    }
    
    $created = 0;
    $skipped = 0;
    
    foreach ($defaultHolidays as $h) {
        $date = sprintf('%04d-%02d-%02d', $year, $h['month'], $h['day']);
        
        $exists = $db->fetch("SELECT id FROM holidays WHERE holiday_date = :date", ['date' => $date]);
        
        if ($exists) {
            $skipped++;
            continue;
        }
        
        $db->insert('holidays', [
            'holiday_date' => $date,
            'holiday_name' => $h['name'],
            'holiday_type' => $h['type'],
            'is_active' => 1
        ]);
        $created++;
    }
    
    logActivity($_SESSION['user_id'], 'GENERATE_HOLIDAYS', 'SETTINGS', 
               "Generated {$created} default holidays for year {$year}");
    
    successResponse([
        'created' => $created,
        'skipped' => $skipped,
        'year' => $year,
        'message' => "Generated {$created} fixed holidays. Add floating holidays (Diwali, Holi, Eid etc.) manually."
    ]);
}

/**
 * Create a single holiday
 */
function createHoliday() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['holiday_date']) || empty($input['holiday_name'])) {
        errorResponse('Holiday date and name are required');
    }
    
    $db = Database::getInstance();
    
    // Check if date already has a holiday
    $exists = $db->fetch(
        "SELECT id, holiday_name FROM holidays WHERE holiday_date = :date",
        ['date' => $input['holiday_date']]
    );
    
    if ($exists) {
        errorResponse("A holiday ({$exists['holiday_name']}) already exists on this date");
    }
    
    $id = $db->insert('holidays', [
        'holiday_date' => $input['holiday_date'],
        'holiday_name' => sanitize($input['holiday_name']),
        'holiday_type' => $input['holiday_type'] ?? 'National',
        'is_active' => 1
    ]);
    
    logActivity($_SESSION['user_id'], 'CREATE_HOLIDAY', 'SETTINGS', 
               "Created holiday: {$input['holiday_name']} on {$input['holiday_date']}");
    
    successResponse(['id' => $id], 'Holiday created successfully');
}

/**
 * Update a holiday
 */
function updateHoliday() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    
    if (!$id) {
        errorResponse('Holiday ID is required');
    }
    
    $db = Database::getInstance();
    
    $updateData = [];
    if (isset($input['holiday_name'])) $updateData['holiday_name'] = sanitize($input['holiday_name']);
    if (isset($input['holiday_date'])) $updateData['holiday_date'] = $input['holiday_date'];
    if (isset($input['holiday_type'])) $updateData['holiday_type'] = $input['holiday_type'];
    if (isset($input['is_active'])) $updateData['is_active'] = $input['is_active'] ? 1 : 0;
    
    if (empty($updateData)) {
        errorResponse('No data to update');
    }
    
    $db->update('holidays', $updateData, 'id = :id', ['id' => $id]);
    
    logActivity($_SESSION['user_id'], 'UPDATE_HOLIDAY', 'SETTINGS', "Updated holiday ID: {$id}");
    
    successResponse([], 'Holiday updated successfully');
}

/**
 * Delete a holiday
 */
function deleteHoliday() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    
    if (!$id) {
        errorResponse('Holiday ID is required');
    }
    
    $db = Database::getInstance();
    
    $holiday = $db->fetch("SELECT holiday_name FROM holidays WHERE id = :id", ['id' => $id]);
    
    $db->delete('holidays', 'id = :id', ['id' => $id]);
    
    logActivity($_SESSION['user_id'], 'DELETE_HOLIDAY', 'SETTINGS', 
               "Deleted holiday: {$holiday['holiday_name']}");
    
    successResponse([], 'Holiday deleted successfully');
}

/**
 * Update holiday template
 */
function updateTemplate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    
    if (!$id) {
        errorResponse('Template ID is required');
    }
    
    $db = Database::getInstance();
    
    $updateData = [];
    if (isset($input['holiday_name'])) $updateData['holiday_name'] = sanitize($input['holiday_name']);
    if (isset($input['holiday_type'])) $updateData['holiday_type'] = $input['holiday_type'];
    if (isset($input['is_active'])) $updateData['is_active'] = $input['is_active'] ? 1 : 0;
    
    $db->update('holiday_templates', $updateData, 'id = :id', ['id' => $id]);
    
    successResponse([], 'Template updated successfully');
}
