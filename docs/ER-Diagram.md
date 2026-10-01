# Entity Relationship Diagram

The canonical relationships below are derived from database/schema.sql.

    roles
      1 |
        | N
      users
       /|\
      / |  \
     /  |   \
    N   N    N
 departments  designations

    users 1 ----- N attendance
    users 1 ----- N leave_requests
    users 1 ----- N leave_balance
    leave_types 1 ----- N leave_requests
    leave_types 1 ----- N leave_balance

    grievance_categories 1 ----- N grievances
    users 1 -------------------- N grievances
    grievances 1 --------------- N grievance_comments
    users 1 -------------------- N grievance_comments

    users 1 ----- N service_records
    users 1 ----- N salary_slips
    users 1 ----- N notifications
    users 1 ----- N sessions
    users 1 ----- 1 two_factor_auth
    users 1 ----- N activity_logs

    designations 1 ----- N service_records
    departments 1 ------ N service_records

## Key Entities

### users

Primary account entity. Links to role, department and designation and is referenced by most HR transactions.

### roles

Defines the role code and permission JSON for SUPER_ADMIN, ADMIN, OFFICER and EMPLOYEE.

### attendance

Stores one attendance record per employee/date through a unique employee/date constraint.

### leave_requests

Stores employee leave applications and approval metadata.

### leave_balance

Stores per-employee, per-leave-type, per-year balance information.

### grievances

Stores employee complaints, priority, assignment and lifecycle status.

### grievance_comments

Stores comments associated with a grievance.

### service_records

Stores employment events such as promotion, transfer, training, award and increment.

### salary_slips

Stores monthly payroll values and payment state.

### two_factor_auth

Stores TOTP configuration and backup-code data for a user.

### sessions

Stores server-side session tokens and expiration.

### activity_logs

Stores auditable actions and old/new value metadata.

### notifications

Stores user-specific informational and workflow notifications.

## Referential Integrity Notes

The schema uses InnoDB foreign keys with a mix of RESTRICT, CASCADE and SET NULL actions. These actions should be preserved during migrations because they define deletion and lifecycle behavior.
