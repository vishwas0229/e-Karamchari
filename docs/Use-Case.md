# Use-Case Model

## Actors

- Employee
- Officer
- Admin
- Super Admin

## Employee Use Cases

Employee can:
- authenticate
- complete 2FA verification when enabled
- view dashboard
- check in and check out
- view attendance
- view leave balance
- apply for leave
- view and cancel eligible leave requests
- submit grievances
- track grievance status
- view salary slips
- view service records
- update permitted profile data
- change password
- manage 2FA
- view notifications

## Administrative Use Cases

Officer/Admin can, according to server-side role permissions:
- authenticate
- view dashboard statistics
- view employee records
- create/update employee records where permitted
- manage leave approvals
- manage grievances
- view/mark/report attendance
- manage salary slips
- manage service records
- view reports
- view/manage holidays
- view supported settings and operational records

Super Admin additionally has full system permissions, including protected settings mutations.

## Use-Case Relationship

    Employee --------> (Login)
    Employee --------> (Manage own profile)
    Employee --------> (View attendance)
    Employee --------> (Manage leave)
    Employee --------> (Manage grievances)
    Employee --------> (View salary)
    Employee --------> (View service record)
    Employee --------> (Manage notifications)
    Employee --------> (Manage 2FA)

    Officer ---------> (Review approvals)
    Officer ---------> (Attendance reports)
    Officer ---------> (Grievance workflow)
    Officer ---------> (Service records where permitted)
    Officer ---------> (Reports)

    Admin -----------> (Employee management)
    Admin -----------> (Payroll)
    Admin -----------> (Leave approvals)
    Admin -----------> (Grievance management)
    Admin -----------> (Attendance management)
    Admin -----------> (Reports)
    Admin -----------> (Supported settings)

    Super Admin -----> (All system capabilities)
