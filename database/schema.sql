-- e-Karamchari Database Schema
-- Municipal Corporation of Delhi - Employee Self-Service Portal
-- Version: 1.0.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+05:30";

-- --------------------------------------------------------
-- Table: roles
-- --------------------------------------------------------
CREATE TABLE `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_name` VARCHAR(50) NOT NULL,
  `role_code` VARCHAR(20) NOT NULL UNIQUE,
  `description` TEXT,
  `permissions` JSON,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_role_code` (`role_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: departments
-- --------------------------------------------------------
CREATE TABLE `departments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dept_code` VARCHAR(20) NOT NULL UNIQUE,
  `dept_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `head_employee_id` INT UNSIGNED NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_dept_code` (`dept_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: designations
-- --------------------------------------------------------
CREATE TABLE `designations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `designation_code` VARCHAR(20) NOT NULL UNIQUE,
  `designation_name` VARCHAR(100) NOT NULL,
  `grade_pay` DECIMAL(10,2) DEFAULT 0.00,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` VARCHAR(20) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `phone` VARCHAR(15),
  `department_id` INT UNSIGNED,
  `designation_id` INT UNSIGNED,
  `date_of_birth` DATE,
  `date_of_joining` DATE,
  `gender` ENUM('Male', 'Female', 'Other'),
  `address` TEXT,
  `profile_photo` VARCHAR(255),
  `emergency_contact` VARCHAR(15),
  `blood_group` VARCHAR(5),
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
  `failed_login_attempts` INT DEFAULT 0,
  `last_login` TIMESTAMP NULL,
  `password_changed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_employee_id` (`employee_id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role_id`),
  INDEX `idx_department` (`department_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`designation_id`) REFERENCES `designations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: leave_types
-- --------------------------------------------------------
CREATE TABLE `leave_types` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `leave_code` VARCHAR(10) NOT NULL UNIQUE,
  `leave_name` VARCHAR(50) NOT NULL,
  `max_days_per_year` INT DEFAULT 0,
  `is_paid` TINYINT(1) DEFAULT 1,
  `requires_document` TINYINT(1) DEFAULT 0,
  `description` TEXT,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: leave_requests
-- --------------------------------------------------------
CREATE TABLE `leave_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_number` VARCHAR(30) NOT NULL UNIQUE,
  `employee_id` INT UNSIGNED NOT NULL,
  `leave_type_id` INT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `total_days` INT NOT NULL,
  `reason` TEXT NOT NULL,
  `document_path` VARCHAR(255),
  `status` ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') DEFAULT 'Pending',
  `approved_by` INT UNSIGNED NULL,
  `approved_at` TIMESTAMP NULL,
  `rejection_reason` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_request_number` (`request_number`),
  INDEX `idx_employee` (`employee_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_dates` (`start_date`, `end_date`),
  FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: leave_balance
-- --------------------------------------------------------
CREATE TABLE `leave_balance` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL,
  `leave_type_id` INT UNSIGNED NOT NULL,
  `year` YEAR NOT NULL,
  `total_allocated` INT DEFAULT 0,
  `used` INT DEFAULT 0,
  `carried_forward` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_balance` (`employee_id`, `leave_type_id`, `year`),
  FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: grievance_categories
-- --------------------------------------------------------
CREATE TABLE `grievance_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_code` VARCHAR(20) NOT NULL UNIQUE,
  `category_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `priority_level` ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
  `sla_days` INT DEFAULT 7,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: grievances
-- --------------------------------------------------------
CREATE TABLE `grievances` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grievance_number` VARCHAR(30) NOT NULL UNIQUE,
  `employee_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `subject` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `priority` ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
  `status` ENUM('Open', 'In Progress', 'Resolved', 'Closed', 'Reopened') DEFAULT 'Open',
  `assigned_to` INT UNSIGNED NULL,
  `document_path` VARCHAR(255),
  `resolution` TEXT,
  `resolved_by` INT UNSIGNED NULL,
  `resolved_at` TIMESTAMP NULL,
  `closed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_grievance_number` (`grievance_number`),
  INDEX `idx_employee` (`employee_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_priority` (`priority`),
  FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `grievance_categories`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: grievance_comments
-- --------------------------------------------------------
CREATE TABLE `grievance_comments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grievance_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `comment` TEXT NOT NULL,
  `is_internal` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_grievance` (`grievance_id`),
  FOREIGN KEY (`grievance_id`) REFERENCES `grievances`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: attendance
-- --------------------------------------------------------
CREATE TABLE `attendance` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL,
  `attendance_date` DATE NOT NULL,
  `check_in_time` TIME NULL,
  `check_out_time` TIME NULL,
  `status` ENUM('Present', 'Absent', 'Half Day', 'On Leave', 'Holiday', 'Weekend') DEFAULT 'Absent',
  `work_hours` DECIMAL(4,2) DEFAULT 0.00,
  `overtime_hours` DECIMAL(4,2) DEFAULT 0.00,
  `remarks` VARCHAR(255),
  `ip_address` VARCHAR(45),
  `location` VARCHAR(100),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`employee_id`, `attendance_date`),
  INDEX `idx_date` (`attendance_date`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: service_records
-- --------------------------------------------------------
CREATE TABLE `service_records` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL,
  `record_type` ENUM('Promotion', 'Transfer', 'Training', 'Award', 'Disciplinary', 'Increment', 'Demotion', 'Other') NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `effective_date` DATE NOT NULL,
  `end_date` DATE NULL,
  `previous_designation_id` INT UNSIGNED NULL,
  `new_designation_id` INT UNSIGNED NULL,
  `previous_department_id` INT UNSIGNED NULL,
  `new_department_id` INT UNSIGNED NULL,
  `document_path` VARCHAR(255),
  `order_number` VARCHAR(50),
  `remarks` TEXT,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_employee` (`employee_id`),
  INDEX `idx_record_type` (`record_type`),
  INDEX `idx_effective_date` (`effective_date`),
  FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`previous_designation_id`) REFERENCES `designations`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`new_designation_id`) REFERENCES `designations`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`previous_department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`new_department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: activity_logs
-- --------------------------------------------------------
CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `description` TEXT,
  `old_values` JSON,
  `new_values` JSON,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_module` (`module`),
  INDEX `idx_created` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: two_factor_auth
-- --------------------------------------------------------
CREATE TABLE `two_factor_auth` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `secret_key` VARCHAR(32) NOT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `backup_codes` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_2fa` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: sessions
-- --------------------------------------------------------
CREATE TABLE `sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `session_token` VARCHAR(255) NOT NULL UNIQUE,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(255),
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_token` (`session_token`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_expires` (`expires_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: system_settings
-- --------------------------------------------------------
CREATE TABLE `system_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `setting_type` ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
  `description` VARCHAR(255),
  `is_editable` TINYINT(1) DEFAULT 1,
  `updated_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_key` (`setting_key`),
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: holidays
-- --------------------------------------------------------
CREATE TABLE `holidays` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `holiday_date` DATE NOT NULL,
  `holiday_name` VARCHAR(100) NOT NULL,
  `holiday_type` ENUM('National', 'Regional', 'Restricted', 'Optional') DEFAULT 'National',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_holiday` (`holiday_date`),
  INDEX `idx_date` (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: notifications
-- --------------------------------------------------------
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('Info', 'Success', 'Warning', 'Error') DEFAULT 'Info',
  `link` VARCHAR(255),
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_read` (`is_read`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: salary_slips
-- --------------------------------------------------------
CREATE TABLE `salary_slips` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL,
  `month` TINYINT NOT NULL,
  `year` YEAR NOT NULL,
  `basic_pay` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `grade_pay` DECIMAL(10,2) DEFAULT 0.00,
  `da` DECIMAL(10,2) DEFAULT 0.00,
  `hra` DECIMAL(10,2) DEFAULT 0.00,
  `ta` DECIMAL(10,2) DEFAULT 0.00,
  `other_allowances` DECIMAL(10,2) DEFAULT 0.00,
  `gross_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `pf_deduction` DECIMAL(10,2) DEFAULT 0.00,
  `tax_deduction` DECIMAL(10,2) DEFAULT 0.00,
  `other_deductions` DECIMAL(10,2) DEFAULT 0.00,
  `total_deductions` DECIMAL(12,2) DEFAULT 0.00,
  `net_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_date` DATE,
  `payment_status` ENUM('Pending', 'Paid', 'Hold') DEFAULT 'Pending',
  `remarks` TEXT,
  `generated_by` INT UNSIGNED,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_salary` (`employee_id`, `month`, `year`),
  INDEX `idx_employee` (`employee_id`),
  INDEX `idx_month_year` (`month`, `year`),
  FOREIGN KEY (`employee_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Insert Default Roles
-- --------------------------------------------------------
INSERT INTO `roles` (`role_name`, `role_code`, `description`, `permissions`) VALUES
('Super Admin', 'SUPER_ADMIN', 'Full system access with all permissions', '{"all": true}'),
('Admin', 'ADMIN', 'Administrative access for managing employees and approvals', '{"employees": true, "leaves": true, "grievances": true, "attendance": true, "reports": true, "settings": false}'),
('Officer', 'OFFICER', 'Department officer with approval rights', '{"employees": false, "leaves": true, "grievances": true, "attendance": true, "reports": true}'),
('Employee', 'EMPLOYEE', 'Regular employee with self-service access', '{"self_service": true}');

-- --------------------------------------------------------
-- Insert Default Leave Types
-- --------------------------------------------------------
INSERT INTO `leave_types` (`leave_code`, `leave_name`, `max_days_per_year`, `is_paid`, `requires_document`, `description`, `is_active`) VALUES
('CL', 'Casual Leave', 45, 1, 0, 'Casual leave for personal matters', 1),
('EL', 'Earned Leave', 30, 1, 0, 'Earned/Privilege leave accumulated over service', 0),
('ML', 'Medical Leave', 15, 1, 1, 'Leave for medical reasons with certificate', 0),
('MAT', 'Maternity Leave', 180, 1, 1, 'Maternity leave for female employees', 0),
('PAT', 'Paternity Leave', 15, 1, 1, 'Paternity leave for male employees', 0),
('LWP', 'Leave Without Pay', 0, 0, 0, 'Unpaid leave when other leaves exhausted', 0),
('CCL', 'Child Care Leave', 730, 1, 0, 'Child care leave for female employees', 0),
('SPL', 'Special Leave', 0, 1, 1, 'Special leave for extraordinary circumstances', 0);

-- --------------------------------------------------------
-- Insert Default Grievance Categories
-- --------------------------------------------------------
INSERT INTO `grievance_categories` (`category_code`, `category_name`, `description`, `priority_level`, `sla_days`) VALUES
('SAL', 'Salary & Allowances', 'Issues related to salary, allowances, and deductions', 'High', 7),
('LEAVE', 'Leave Related', 'Issues related to leave applications and balance', 'Medium', 5),
('TRANS', 'Transfer Related', 'Issues related to transfer orders and postings', 'Medium', 10),
('PROM', 'Promotion Related', 'Issues related to promotions and seniority', 'High', 15),
('WORK', 'Workplace Issues', 'Issues related to workplace environment and facilities', 'Medium', 7),
('HARASS', 'Harassment', 'Complaints related to harassment at workplace', 'Critical', 3),
('IT', 'IT & Technical', 'Issues related to IT systems and technical support', 'Low', 5),
('OTHER', 'Other', 'Other miscellaneous grievances', 'Low', 10);

-- --------------------------------------------------------
-- Insert Default Departments (including "Others")
-- --------------------------------------------------------
INSERT INTO `departments` (`dept_code`, `dept_name`, `description`) VALUES
('ADMIN', 'Administration', 'Administrative Department'),
('HR', 'Human Resources', 'HR Department'),
('FIN', 'Finance', 'Finance & Accounts Department'),
('IT', 'Information Technology', 'IT Department'),
('ENG', 'Engineering', 'Engineering Department'),
('HEALTH', 'Health', 'Health & Sanitation Department'),
('EDU', 'Education', 'Education Department'),
('REV', 'Revenue', 'Revenue Department'),
('OTHER', 'Others', 'Other Departments');

-- --------------------------------------------------------
-- Insert Default Designations
-- --------------------------------------------------------
INSERT INTO `designations` (`designation_code`, `designation_name`, `grade_pay`) VALUES
('DIR', 'Director', 10000.00),
('JD', 'Joint Director', 8000.00),
('DD', 'Deputy Director', 7000.00),
('AD', 'Assistant Director', 6000.00),
('SO', 'Section Officer', 5000.00),
('ASO', 'Assistant Section Officer', 4500.00),
('UDC', 'Upper Division Clerk', 4000.00),
('LDC', 'Lower Division Clerk', 3500.00),
('MTS', 'Multi Tasking Staff', 3000.00);

COMMIT;
