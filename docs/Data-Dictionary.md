# Database Data Dictionary

The dictionary summarizes the canonical entities in database/schema.sql.

| Table | Primary key | Main purpose | Important references |
|---|---|---|---|
| roles | id | user roles and permission metadata | users.role_id |
| departments | id | departments | users.department_id, service_records.*_department_id |
| designations | id | job/designation catalog | users.designation_id, service_records.*_designation_id |
| users | id | accounts and profile data | root entity |
| leave_types | id | leave catalog | leave_requests.leave_type_id, leave_balance.leave_type_id |
| leave_requests | id | leave applications and approval state | users, leave_types |
| leave_balance | id | yearly leave balances | users, leave_types |
| grievance_categories | id | grievance classifications | grievances.category_id |
| grievances | id | grievances and lifecycle | users, grievance_categories |
| grievance_comments | id | grievance comments | grievances, users |
| attendance | id | employee/date attendance | users |
| service_records | id | employment/service events | users, departments, designations |
| activity_logs | id | audit trail | users |
| two_factor_auth | id | TOTP settings and backup data | users |
| sessions | id | server-side sessions | users |
| system_settings | id | configurable settings | users.updated_by |
| holidays | id | holiday calendar | standalone catalog |
| notifications | id | user notifications | users |
| salary_slips | id | monthly payroll | users |

## Core Field Groups

### users

Employee identity: employee_id, email, first_name, last_name.

Security: password_hash, role_id, is_active, is_locked, failed_login_attempts, last_login, password_changed_at.

HR profile: phone, department_id, designation_id, date_of_birth, date_of_joining, gender, address, profile_photo, emergency_contact, blood_group.

### leave_requests

Identity and lifecycle: request_number, employee_id, leave_type_id, start_date, end_date, total_days, status.

Approval: approved_by, approved_at, rejection_reason.

### attendance

Identity and date: employee_id, attendance_date.

Time: check_in_time, check_out_time, work_hours, overtime_hours.

State: status, remarks.

Audit context: ip_address, location.

### grievances

Identity: grievance_number, employee_id, category_id.

Content: subject, description, priority.

Workflow: status, assigned_to, resolution, resolved_by, resolved_at, closed_at.

### salary_slips

Payroll: month, year, basic_pay, grade_pay, da, hra, ta, other_allowances, gross_salary.

Deductions: pf_deduction, tax_deduction, other_deductions, total_deductions.

Payment: net_salary, payment_date, payment_status, remarks.

## Constraints

Unique keys and foreign keys defined in schema.sql are part of the data model and should be preserved during migrations.
