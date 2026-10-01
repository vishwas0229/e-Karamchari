# Data Flow Diagrams

## Context Diagram

    [Employee]
        |
        | login / HR requests
        v
    [e-Karamchari System] <----> [MySQL Database]
        ^
        |
        | administrative requests
    [Admin / Officer]

## Level 1 Data Flow

    Employee/Admin
         |
         v
    [Authentication]
         |
         +--------------------+
         |                    |
         v                    v
    [Employee/Admin UI]   [Session + RBAC]
         |                    |
         +---------+----------+
                   |
                   v
              [PHP API Layer]
                   |
        +----------+----------+----------+----------+
        |          |          |          |          |
        v          v          v          v          v
    [Attendance] [Leaves] [Grievances] [Payroll] [Records]
        |          |          |          |          |
        +----------+----------+----------+----------+
                   |
                   v
              [MySQL Data Store]
                   |
                   +--> activity_logs
                   +--> notifications
                   +--> sessions
                   +--> two_factor_auth

## Level 1 Security/Data Flows

    Request
      |
      v
    Rate Limit Check
      |
      v
    Session Authentication
      |
      v
    Role Authorization
      |
      v
    CSRF Validation for state-changing requests
      |
      v
    Module Validation
      |
      v
    Database Transaction
      |
      v
    JSON Response

## Main Data Movement

### Authentication

Credentials -> auth.php -> users -> sessions -> browser cookie/session state

### Leave

Employee form -> leaves.php -> leave_requests/leave_balance -> notification/audit -> JSON response

### Attendance

Check-in/out -> attendance.php -> attendance -> dashboard/report queries -> JSON response

### Grievance

Employee submission -> grievances.php -> grievances + comments -> admin workflow -> employee status view

### Payroll

Admin payroll operation -> salary.php -> salary_slips -> employee salary-slip view

### Service Record

Admin service action -> service-records.php -> service_records -> employee service-record view

## Level 2 Data Flows

### Leave Management

    Employee
       |
       v
    Leave form
       |
       v
    leaves.php
       |
       +--> validate dates/type/balance
       |
       +--> leave_requests
       |       |
       |       +--> notification
       |
       +--> leave_balance
       |
       v
    JSON response / employee status

    Admin
       |
       v
    Approval request
       |
       v
    leaves.php
       |
       +--> role/CSRF validation
       +--> update leave_requests
       +--> update leave_balance
       +--> notification
       v
    JSON response

### Grievance Management

    Employee
       |
       v
    grievance form
       |
       v
    grievances.php
       |
       +--> validate category/content
       +--> create grievances row
       +--> optional notification
       v
    status response

    Admin/Officer
       |
       v
    review/assign/resolve
       |
       v
    grievances.php
       |
       +--> role/CSRF validation
       +--> update grievances
       +--> create grievance_comments where applicable
       +--> activity/audit data
       v
    JSON response

### Attendance

    Employee
       |
       +--> check-in/out
       v
    attendance.php
       |
       +--> session + CSRF + rate-limit checks
       +--> validate current date/state
       +--> insert/update attendance
       v
    attendance record

    Admin
       |
       +--> list/mark/report
       v
    attendance.php
       |
       +--> role validation
       +--> holiday/weekend rules
       +--> attendance query/update
       v
    attendance report

### Payroll

    Admin
       |
       v
    salary.php
       |
       +--> role + CSRF validation
       +--> salary_slips
       v
    payroll response
       ^
       |
    Employee salary-slip view
