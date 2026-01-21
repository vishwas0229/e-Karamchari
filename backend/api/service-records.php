<?php
/**
 * e-Karamchari Service Records API
 * Handles employee service history and records
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
        getServiceRecords();
        break;
    case 'my-records':
        getMyServiceRecords();
        break;
    case 'get':
        getServiceRecord();
        break;
    case 'create':
        createServiceRecord();
        break;
    case 'update':
        updateServiceRecord();
        break;
    case 'delete':
        deleteServiceRecord();
        break;
    case 'types':
        getRecordTypes();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Get all service records (Admin only)
 */
function getServiceRecords() {
    Auth::requireAdmin();
    
    $db = Database::getInstance();
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(MAX_PAGE_SIZE, max(1, (int)($_GET['limit'] ?? DEFAULT_PAGE_SIZE)));
    $offset = ($page - 1) * $limit;
    
    $employeeId = (int)($_GET['employee_id'] ?? 0);
    $recordType = sanitize($_GET['type'] ?? '');
    
    $where = "1=1";
    $params = [];
    
    if ($employeeId) {
        $where .= " AND sr.employee_id = :employee_id";
        $params['employee_id'] = $employeeId;
    }
    
    if ($recordType) {
        $where .= " AND sr.record_type = :type";
        $params['type'] = $recordType;
    }
    
    $countSql = "SELECT COUNT(*) as total FROM service_records sr WHERE {$where}";
    $total = $db->fetch($countSql, $params)['total'];
    
    $sql = "SELECT sr.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                   u.employee_id as emp_code,
                   pd.designation_name as prev_designation,
                   nd.designation_name as new_designation,
                   pdept.dept_name as prev_department,
                   ndept.dept_name as new_department,
                   CONCAT(c.first_name, ' ', c.last_name) as created_by_name
            FROM service_records sr
            JOIN users u ON sr.employee_id = u.id
            LEFT JOIN designations pd ON sr.previous_designation_id = pd.id
            LEFT JOIN designations nd ON sr.new_designation_id = nd.id
            LEFT JOIN departments pdept ON sr.previous_department_id = pdept.id
            LEFT JOIN departments ndept ON sr.new_department_id = ndept.id
            JOIN users c ON sr.created_by = c.id
            WHERE {$where}
            ORDER BY sr.effective_date DESC
            LIMIT {$limit} OFFSET {$offset}";
    
    $records = $db->fetchAll($sql, $params);
    
    successResponse([
        'records' => $records,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get current user's service records
 */
function getMyServiceRecords() {
    Auth::requireAuth();
    
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    $sql = "SELECT sr.*, 
                   pd.designation_name as prev_designation,
                   nd.designation_name as new_designation,
                   pdept.dept_name as prev_department,
                   ndept.dept_name as new_department
            FROM service_records sr
            LEFT JOIN designations pd ON sr.previous_designation_id = pd.id
            LEFT JOIN designations nd ON sr.new_designation_id = nd.id
            LEFT JOIN departments pdept ON sr.previous_department_id = pdept.id
            LEFT JOIN departments ndept ON sr.new_department_id = ndept.id
            WHERE sr.employee_id = :user_id
            ORDER BY sr.effective_date DESC";
    
    $records = $db->fetchAll($sql, ['user_id' => $userId]);
    
    // Get employee details
    $employee = $db->fetch(
        "SELECT u.*, d.dept_name, des.designation_name
         FROM users u
         LEFT JOIN departments d ON u.department_id = d.id
         LEFT JOIN designations des ON u.designation_id = des.id
         WHERE u.id = :id",
        ['id' => $userId]
    );
    
    unset($employee['password_hash']);
    
    // Calculate service duration
    $joiningDate = $employee['date_of_joining'];
    if ($joiningDate) {
        $joining = new DateTime($joiningDate);
        $now = new DateTime();
        $diff = $joining->diff($now);
        $employee['service_duration'] = [
            'years' => $diff->y,
            'months' => $diff->m,
            'days' => $diff->d
        ];
    }
    
    successResponse([
        'employee' => $employee,
        'records' => $records
    ]);
}

/**
 * Get single service record
 */
function getServiceRecord() {
    Auth::requireAuth();
    
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        errorResponse('Record ID is required');
    }
    
    $db = Database::getInstance();
    
    $sql = "SELECT sr.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                   u.employee_id as emp_code,
                   pd.designation_name as prev_designation,
                   nd.designation_name as new_designation,
                   pdept.dept_name as prev_department,
                   ndept.dept_name as new_department
            FROM service_records sr
            JOIN users u ON sr.employee_id = u.id
            LEFT JOIN designations pd ON sr.previous_designation_id = pd.id
            LEFT JOIN designations nd ON sr.new_designation_id = nd.id
            LEFT JOIN departments pdept ON sr.previous_department_id = pdept.id
            LEFT JOIN departments ndept ON sr.new_department_id = ndept.id
            WHERE sr.id = :id";
    
    $record = $db->fetch($sql, ['id' => $id]);
    
    if (!$record) {
        errorResponse('Record not found', 404);
    }
    
    // Check access
    if (!Auth::isAdmin() && $record['employee_id'] != $_SESSION['user_id']) {
        errorResponse('Unauthorized', 403);
    }
    
    successResponse($record);
}

/**
 * Create service record (Admin only)
 */
function createServiceRecord() {
    Auth::requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['employee_id', 'record_type', 'title', 'effective_date'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            errorResponse("Field '{$field}' is required");
        }
    }
    
    $validTypes = ['Promotion', 'Transfer', 'Training', 'Award', 'Disciplinary', 'Increment', 'Demotion', 'Other'];
    if (!in_array($input['record_type'], $validTypes)) {
        errorResponse('Invalid record type');
    }
    
    $db = Database::getInstance();
    
    try {
        // Handle "Other" designation - create new designation if provided
        $newDesignationId = $input['new_designation_id'] ?? null;
        if (!empty($input['other_designation'])) {
            $otherDesignationName = sanitize($input['other_designation']);
            
            // Check if designation already exists
            $existingDesignation = $db->fetch(
                "SELECT id FROM designations WHERE designation_name = :name",
                ['name' => $otherDesignationName]
            );
            
            if ($existingDesignation) {
                $newDesignationId = $existingDesignation['id'];
            } else {
                // Create new designation with code
                $designationCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $otherDesignationName), 0, 10));
                
                // Ensure unique code
                $codeExists = $db->fetch("SELECT id FROM designations WHERE designation_code = :code", ['code' => $designationCode]);
                if ($codeExists) {
                    $designationCode = $designationCode . rand(10, 99);
                }
                
                // Determine grade_pay based on record type
                $employee = $db->fetch(
                    "SELECT u.designation_id, d.grade_pay FROM users u 
                     LEFT JOIN designations d ON u.designation_id = d.id 
                     WHERE u.id = :id",
                    ['id' => $input['employee_id']]
                );
                $currentGradePay = $employee['grade_pay'] ?? 0;
                
                // For promotion, set higher grade_pay; for demotion, set lower
                if ($input['record_type'] === 'Promotion') {
                    $newGradePay = $currentGradePay + 500;
                } elseif ($input['record_type'] === 'Demotion') {
                    $newGradePay = max(0, $currentGradePay - 500);
                } else {
                    $newGradePay = $currentGradePay;
                }
                
                $newDesignationId = $db->insert('designations', [
                    'designation_code' => $designationCode,
                    'designation_name' => $otherDesignationName,
                    'grade_pay' => $newGradePay,
                    'is_active' => 1
                ]);
            }
        }
        
        // Handle "Other" department - create new department if provided
        $newDepartmentId = $input['new_department_id'] ?? null;
        if (!empty($input['other_department'])) {
            $otherDepartmentName = sanitize($input['other_department']);
            
            // Check if department already exists
            $existingDepartment = $db->fetch(
                "SELECT id FROM departments WHERE dept_name = :name",
                ['name' => $otherDepartmentName]
            );
            
            if ($existingDepartment) {
                $newDepartmentId = $existingDepartment['id'];
            } else {
                // Create new department with code
                $deptCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $otherDepartmentName), 0, 10));
                
                // Ensure unique code
                $codeExists = $db->fetch("SELECT id FROM departments WHERE dept_code = :code", ['code' => $deptCode]);
                if ($codeExists) {
                    $deptCode = $deptCode . rand(10, 99);
                }
                
                $newDepartmentId = $db->insert('departments', [
                    'dept_code' => $deptCode,
                    'dept_name' => $otherDepartmentName,
                    'description' => 'Created via service record',
                    'is_active' => 1
                ]);
            }
        }
        
        // Get employee's current designation and department for record
        $employee = $db->fetch(
            "SELECT designation_id, department_id FROM users WHERE id = :id",
            ['id' => $input['employee_id']]
        );
        
        $recordData = [
            'employee_id' => (int)$input['employee_id'],
            'record_type' => $input['record_type'],
            'title' => sanitize($input['title']),
            'description' => sanitize($input['description'] ?? ''),
            'effective_date' => $input['effective_date'],
            'end_date' => $input['end_date'] ?? null,
            'previous_designation_id' => $employee['designation_id'] ?? null,
            'new_designation_id' => $newDesignationId,
            'previous_department_id' => $employee['department_id'] ?? null,
            'new_department_id' => $newDepartmentId,
            'order_number' => sanitize($input['order_number'] ?? ''),
            'remarks' => sanitize($input['remarks'] ?? ''),
            'created_by' => $_SESSION['user_id']
        ];
        
        $id = $db->insert('service_records', $recordData);
        
        // Update employee's current designation/department if it's a promotion, demotion or transfer
        if (in_array($input['record_type'], ['Promotion', 'Demotion', 'Transfer'])) {
            $updateData = [];
            if (!empty($newDesignationId)) {
                $updateData['designation_id'] = $newDesignationId;
            }
            if (!empty($newDepartmentId)) {
                $updateData['department_id'] = $newDepartmentId;
            }
            if (!empty($updateData)) {
                $db->update('users', $updateData, 'id = :id', ['id' => $input['employee_id']]);
            }
        }
        
        // Notify employee
        $db->insert('notifications', [
            'user_id' => $input['employee_id'],
            'title' => 'New Service Record',
            'message' => "A new {$input['record_type']} record has been added to your service history.",
            'type' => 'Info',
            'link' => 'service-record.html'
        ]);
        
        logActivity($_SESSION['user_id'], 'CREATE_SERVICE_RECORD', 'SERVICE_RECORDS', 
                   "Created {$input['record_type']} record for employee {$input['employee_id']}");
        
        successResponse(['id' => $id], 'Service record created successfully');
    } catch (Exception $e) {
        errorResponse('Failed to create service record: ' . $e->getMessage());
    }
}

/**
 * Update service record (Admin only)
 */
function updateServiceRecord() {
    Auth::requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    
    if (!$id) {
        errorResponse('Record ID is required');
    }
    
    $db = Database::getInstance();
    
    $existing = $db->fetch("SELECT * FROM service_records WHERE id = :id", ['id' => $id]);
    if (!$existing) {
        errorResponse('Record not found', 404);
    }
    
    $updateData = [];
    $allowedFields = ['title', 'description', 'effective_date', 'end_date', 
                      'order_number', 'remarks'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateData[$field] = is_string($input[$field]) ? sanitize($input[$field]) : $input[$field];
        }
    }
    
    if (empty($updateData)) {
        errorResponse('No data to update');
    }
    
    $db->update('service_records', $updateData, 'id = :id', ['id' => $id]);
    
    logActivity($_SESSION['user_id'], 'UPDATE_SERVICE_RECORD', 'SERVICE_RECORDS', 
               "Updated service record ID: {$id}");
    
    successResponse([], 'Service record updated successfully');
}

/**
 * Delete service record (Admin only)
 */
function deleteServiceRecord() {
    Auth::requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    
    if (!$id) {
        errorResponse('Record ID is required');
    }
    
    $db = Database::getInstance();
    
    $db->delete('service_records', 'id = :id', ['id' => $id]);
    
    logActivity($_SESSION['user_id'], 'DELETE_SERVICE_RECORD', 'SERVICE_RECORDS', 
               "Deleted service record ID: {$id}");
    
    successResponse([], 'Service record deleted successfully');
}

/**
 * Get record types
 */
function getRecordTypes() {
    Auth::requireAuth();
    
    $types = [
        ['code' => 'Promotion', 'name' => 'Promotion', 'description' => 'Promotion to higher position'],
        ['code' => 'Transfer', 'name' => 'Transfer', 'description' => 'Transfer to different department/location'],
        ['code' => 'Training', 'name' => 'Training', 'description' => 'Training or certification completed'],
        ['code' => 'Award', 'name' => 'Award', 'description' => 'Award or recognition received'],
        ['code' => 'Disciplinary', 'name' => 'Disciplinary', 'description' => 'Disciplinary action'],
        ['code' => 'Increment', 'name' => 'Increment', 'description' => 'Salary increment'],
        ['code' => 'Other', 'name' => 'Other', 'description' => 'Other service record']
    ];
    
    successResponse($types);
}
