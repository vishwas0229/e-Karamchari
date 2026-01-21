<?php
/**
 * Salary API
 * e-Karamchari - Employee Self-Service Portal
 */

require_once __DIR__ . '/../middleware/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    $db = Database::getInstance();
    
    switch ($action) {
        case 'my-slips':
            Auth::requireAuth();
            getMySlips($db);
            break;
        case 'view':
            Auth::requireAuth();
            viewSlip($db);
            break;
        case 'list':
            Auth::requireAuth(['SUPER_ADMIN', 'ADMIN', 'OFFICER']);
            listAllSlips($db);
            break;
        case 'generate':
            Auth::requireAuth(['SUPER_ADMIN', 'ADMIN']);
            generateSlip($db);
            break;
        case 'update':
            Auth::requireAuth(['SUPER_ADMIN', 'ADMIN']);
            updateSlip($db);
            break;
        case 'delete':
            Auth::requireAuth(['SUPER_ADMIN', 'ADMIN']);
            deleteSlip($db);
            break;
        case 'bulk-generate':
            Auth::requireAuth(['SUPER_ADMIN', 'ADMIN']);
            bulkGenerateSlips($db);
            break;
        default:
            errorResponse('Invalid action', 400);
    }
} catch (Exception $e) {
    error_log("Salary API Error: " . $e->getMessage());
    errorResponse('Server error: ' . $e->getMessage(), 500);
}

function getMySlips($db) {
    $userId = $_SESSION['user_id'];
    $year = $_GET['year'] ?? date('Y');
    
    $slips = $db->fetchAll(
        "SELECT id, month, year, basic_pay, grade_pay, da, hra, ta, other_allowances,
                gross_salary, pf_deduction, tax_deduction, other_deductions, 
                total_deductions, net_salary, payment_date, payment_status, remarks,
                created_at
         FROM salary_slips WHERE employee_id = :uid AND year = :year
         ORDER BY year DESC, month DESC",
        ['uid' => $userId, 'year' => $year]
    );
    
    $years = $db->fetchAll(
        "SELECT DISTINCT year FROM salary_slips WHERE employee_id = :uid ORDER BY year DESC",
        ['uid' => $userId]
    );
    
    // Get available years list (current year + past 5 years if no slips exist)
    $availableYears = array_column($years ?: [], 'year');
    if (empty($availableYears)) {
        $currentYear = (int)date('Y');
        for ($i = 0; $i <= 5; $i++) {
            $availableYears[] = (string)($currentYear - $i);
        }
    }
    
    successResponse([
        'slips' => $slips ?: [],
        'years' => $availableYears,
        'current_year' => (int)$year
    ]);
}

function viewSlip($db) {
    $slipId = (int)($_GET['id'] ?? 0);
    
    if (!$slipId) {
        errorResponse('Salary slip ID is required', 400);
    }
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role_code'] ?? '';
    
    $params = ['id' => $slipId];
    $query = "SELECT s.*, u.employee_id as emp_code, u.first_name, u.last_name,
                     u.email, u.phone, u.date_of_joining,
                     d.dept_name, des.designation_name,
                     CONCAT(g.first_name, ' ', g.last_name) as generated_by_name
              FROM salary_slips s
              JOIN users u ON s.employee_id = u.id
              LEFT JOIN departments d ON u.department_id = d.id
              LEFT JOIN designations des ON u.designation_id = des.id
              LEFT JOIN users g ON s.generated_by = g.id
              WHERE s.id = :id";
    
    if (!in_array($role, ['SUPER_ADMIN', 'ADMIN', 'OFFICER'])) {
        $query .= " AND s.employee_id = :uid";
        $params['uid'] = $userId;
    }
    
    $slip = $db->fetch($query, $params);
    
    if (!$slip) {
        errorResponse('Salary slip not found or access denied', 404);
    }
    
    // Format null values
    $slip['dept_name'] = $slip['dept_name'] ?? 'Not Assigned';
    $slip['designation_name'] = $slip['designation_name'] ?? 'Not Assigned';
    $slip['basic_pay'] = $slip['basic_pay'] ?? 0;
    $slip['grade_pay'] = $slip['grade_pay'] ?? 0;
    $slip['da'] = $slip['da'] ?? 0;
    $slip['hra'] = $slip['hra'] ?? 0;
    $slip['ta'] = $slip['ta'] ?? 0;
    $slip['other_allowances'] = $slip['other_allowances'] ?? 0;
    $slip['pf_deduction'] = $slip['pf_deduction'] ?? 0;
    $slip['tax_deduction'] = $slip['tax_deduction'] ?? 0;
    $slip['other_deductions'] = $slip['other_deductions'] ?? 0;
    
    successResponse($slip);
}


function listAllSlips($db) {
    $month = $_GET['month'] ?? date('n');
    $year = $_GET['year'] ?? date('Y');
    $department = $_GET['department'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $where = "s.month = :month AND s.year = :year";
    $params = ['month' => $month, 'year' => $year];
    
    if ($department) {
        $where .= " AND u.department_id = :dept";
        $params['dept'] = $department;
    }
    
    if ($status) {
        $where .= " AND s.payment_status = :status";
        $params['status'] = $status;
    }
    
    if ($search) {
        $where .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search2 OR u.employee_id LIKE :search3)";
        $params['search'] = "%{$search}%";
        $params['search2'] = "%{$search}%";
        $params['search3'] = "%{$search}%";
    }
    
    $slips = $db->fetchAll(
        "SELECT s.id, s.month, s.year, s.basic_pay, s.grade_pay, s.da, s.hra, s.ta,
                s.other_allowances, s.gross_salary, s.pf_deduction, s.tax_deduction,
                s.other_deductions, s.total_deductions, s.net_salary, s.payment_status,
                s.payment_date, s.remarks, s.created_at,
                u.employee_id as emp_code, u.first_name, u.last_name,
                d.dept_name, des.designation_name,
                CONCAT(g.first_name, ' ', g.last_name) as generated_by_name
         FROM salary_slips s
         JOIN users u ON s.employee_id = u.id
         LEFT JOIN departments d ON u.department_id = d.id
         LEFT JOIN designations des ON u.designation_id = des.id
         LEFT JOIN users g ON s.generated_by = g.id
         WHERE {$where}
         ORDER BY u.first_name, u.last_name",
        $params
    );
    
    // Get summary stats
    $totalGross = 0;
    $totalNet = 0;
    $paidCount = 0;
    $pendingCount = 0;
    
    foreach ($slips ?: [] as $slip) {
        $totalGross += (float)$slip['gross_salary'];
        $totalNet += (float)$slip['net_salary'];
        if ($slip['payment_status'] === 'Paid') $paidCount++;
        else $pendingCount++;
    }
    
    successResponse([
        'slips' => $slips ?: [],
        'summary' => [
            'total_slips' => count($slips ?: []),
            'total_gross' => $totalGross,
            'total_net' => $totalNet,
            'paid_count' => $paidCount,
            'pending_count' => $pendingCount
        ]
    ]);
}

function generateSlip($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['employee_id']) || empty($data['month']) || empty($data['year']) || empty($data['basic_pay'])) {
        errorResponse('Missing required fields');
    }
    
    $gross = ($data['basic_pay'] ?? 0) + ($data['grade_pay'] ?? 0) + ($data['da'] ?? 0) + 
             ($data['hra'] ?? 0) + ($data['ta'] ?? 0) + ($data['other_allowances'] ?? 0);
    $deductions = ($data['pf_deduction'] ?? 0) + ($data['tax_deduction'] ?? 0) + ($data['other_deductions'] ?? 0);
    $net = $gross - $deductions;
    
    // Check if exists
    $existing = $db->fetch(
        "SELECT id FROM salary_slips WHERE employee_id = :eid AND month = :m AND year = :y",
        ['eid' => $data['employee_id'], 'm' => $data['month'], 'y' => $data['year']]
    );
    
    if ($existing) {
        $db->update('salary_slips', [
            'basic_pay' => $data['basic_pay'] ?? 0, 'grade_pay' => $data['grade_pay'] ?? 0,
            'da' => $data['da'] ?? 0, 'hra' => $data['hra'] ?? 0, 'ta' => $data['ta'] ?? 0,
            'other_allowances' => $data['other_allowances'] ?? 0, 'gross_salary' => $gross,
            'pf_deduction' => $data['pf_deduction'] ?? 0, 'tax_deduction' => $data['tax_deduction'] ?? 0,
            'other_deductions' => $data['other_deductions'] ?? 0, 'total_deductions' => $deductions,
            'net_salary' => $net, 'payment_status' => $data['payment_status'] ?? 'Pending',
            'payment_date' => $data['payment_date'] ?: null, 'remarks' => $data['remarks'] ?? null
        ], 'id = :id', ['id' => $existing['id']]);
    } else {
        $db->insert('salary_slips', [
            'employee_id' => $data['employee_id'], 'month' => $data['month'], 'year' => $data['year'],
            'basic_pay' => $data['basic_pay'] ?? 0, 'grade_pay' => $data['grade_pay'] ?? 0,
            'da' => $data['da'] ?? 0, 'hra' => $data['hra'] ?? 0, 'ta' => $data['ta'] ?? 0,
            'other_allowances' => $data['other_allowances'] ?? 0, 'gross_salary' => $gross,
            'pf_deduction' => $data['pf_deduction'] ?? 0, 'tax_deduction' => $data['tax_deduction'] ?? 0,
            'other_deductions' => $data['other_deductions'] ?? 0, 'total_deductions' => $deductions,
            'net_salary' => $net, 'payment_status' => $data['payment_status'] ?? 'Pending',
            'payment_date' => $data['payment_date'] ?: null, 'remarks' => $data['remarks'] ?? null,
            'generated_by' => $_SESSION['user_id']
        ]);
    }
    successResponse([], 'Salary slip generated successfully');
}

function updateSlip($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') { 
        errorResponse('Method not allowed', 405); 
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['id'])) { 
        errorResponse('Slip ID required'); 
    }
    
    // Check if slip exists
    $existing = $db->fetch("SELECT id FROM salary_slips WHERE id = :id", ['id' => $data['id']]);
    if (!$existing) {
        errorResponse('Salary slip not found', 404);
    }
    
    $updateData = [
        'payment_status' => $data['payment_status'] ?? 'Pending',
        'remarks' => $data['remarks'] ?? null
    ];
    
    // Set payment date automatically when status is Paid
    if ($data['payment_status'] === 'Paid' && empty($data['payment_date'])) {
        $updateData['payment_date'] = date('Y-m-d');
    } else {
        $updateData['payment_date'] = $data['payment_date'] ?: null;
    }
    
    $db->update('salary_slips', $updateData, 'id = :id', ['id' => $data['id']]);
    
    logActivity($_SESSION['user_id'], 'UPDATE_SALARY', 'SALARY', "Updated salary slip ID: {$data['id']}");
    
    successResponse([], 'Salary slip updated successfully');
}

function deleteSlip($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') { 
        errorResponse('Method not allowed', 405); 
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
    
    if (!$id) { 
        errorResponse('Slip ID required'); 
    }
    
    // Check if slip exists
    $existing = $db->fetch("SELECT * FROM salary_slips WHERE id = :id", ['id' => $id]);
    if (!$existing) {
        errorResponse('Salary slip not found', 404);
    }
    
    // Don't allow deleting paid slips
    if ($existing['payment_status'] === 'Paid') {
        errorResponse('Cannot delete a paid salary slip');
    }
    
    $db->delete('salary_slips', 'id = :id', ['id' => $id]);
    
    logActivity($_SESSION['user_id'], 'DELETE_SALARY', 'SALARY', "Deleted salary slip ID: {$id}");
    
    successResponse([], 'Salary slip deleted successfully');
}

function bulkGenerateSlips($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['month']) || empty($data['year'])) {
        errorResponse('Month and year are required');
    }
    
    $month = (int)$data['month'];
    $year = (int)$data['year'];
    $departmentId = $data['department_id'] ?? null;
    
    // Get all active employees
    $where = "r.role_code = 'EMPLOYEE' AND u.is_active = 1";
    $params = [];
    
    if ($departmentId) {
        $where .= " AND u.department_id = :dept";
        $params['dept'] = $departmentId;
    }
    
    $employees = $db->fetchAll(
        "SELECT u.id, u.employee_id, u.first_name, u.last_name
         FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE {$where}",
        $params
    );
    
    $generated = 0;
    $skipped = 0;
    
    foreach ($employees as $emp) {
        // Check if slip already exists
        $existing = $db->fetch(
            "SELECT id FROM salary_slips WHERE employee_id = :eid AND month = :m AND year = :y",
            ['eid' => $emp['id'], 'm' => $month, 'y' => $year]
        );
        
        if ($existing) {
            $skipped++;
            continue;
        }
        
        // Generate with default values (admin can edit later)
        $db->insert('salary_slips', [
            'employee_id' => $emp['id'],
            'month' => $month,
            'year' => $year,
            'basic_pay' => $data['default_basic'] ?? 0,
            'grade_pay' => 0,
            'da' => 0,
            'hra' => 0,
            'ta' => 0,
            'other_allowances' => 0,
            'gross_salary' => $data['default_basic'] ?? 0,
            'pf_deduction' => 0,
            'tax_deduction' => 0,
            'other_deductions' => 0,
            'total_deductions' => 0,
            'net_salary' => $data['default_basic'] ?? 0,
            'payment_status' => 'Pending',
            'generated_by' => $_SESSION['user_id']
        ]);
        $generated++;
    }
    
    logActivity($_SESSION['user_id'], 'BULK_GENERATE_SALARY', 'SALARY', 
               "Bulk generated {$generated} salary slips for {$month}/{$year}");
    
    successResponse([
        'generated' => $generated,
        'skipped' => $skipped
    ], "Generated {$generated} salary slips, skipped {$skipped} (already exist)");
}