<div align="center">

# 🏛️ e-Karamchari

### Employee Self-Service Portal | Municipal Corporation of Delhi

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Status](https://img.shields.io/badge/Status-Active-success?style=for-the-badge)

**A modern and secure HR management system with Two-Factor Authentication**

[Features](#-features) • [Installation](#-quick-start) • [Security](#-security) • [API](#-api-reference)

---

</div>

## 🎯 About

**e-Karamchari** is a comprehensive Employee Self-Service Portal designed for government organizations. It streamlines HR operations including leave management, grievance handling, attendance tracking, and payroll management.

---

## ✨ Features

### 👨‍💼 Employee Portal

| Feature | Description |
|:--------|:------------|
| 📊 Dashboard | Personal overview with quick stats |
| 📝 Leave Management | Apply and track leave requests |
| 🎫 Grievances | Submit and monitor complaints |
| ⏰ Attendance | View daily attendance records |
| 💰 Salary Slip | Download monthly pay slips |
| 📁 Service Record | Complete employment history |
| 👤 Profile | Manage personal information |
| 🔐 2FA | Google Authenticator support |

### 👨‍💻 Admin Portal

| Feature | Description |
|:--------|:------------|
| 📈 Dashboard | Organization-wide analytics |
| 👥 Employees | Add, edit, manage staff |
| ✅ Approvals | Leave and grievance actions |
| 📊 Reports | Attendance and salary reports |
| � Payrollp | Salary slip generation |
| 📅 Holidays | Holiday calendar management |
| ⚙️ Settings | System configuration |
| 🤖 Chatbot | AI assistant for help |

---

## 🔐 Security

| Feature | Description |
|:--------|:------------|
| � Two-Factoor Auth | TOTP with Google Authenticator |
| 🔒 Password Hashing | Bcrypt with auto-salt |
| �️ CS RF Protection | Token-based validation |
| 🚫 XSS Prevention | Input sanitization |
| 💉 SQL Injection | Prepared statements |
| ⏱️ Rate Limiting | Brute-force protection |
| � DSession Security | Secure cookies |
| �  Audit Logging | Complete activity trail |

---

## 🛠️ Tech Stack

| Technology | Version |
|:-----------|:--------|
| HTML5 | Latest |
| CSS3 | Latest |
| JavaScript | ES6+ |
| PHP | 7.4+ |
| MySQL | 5.7+ |
| Apache | 2.4+ |

---

## � Quick Setart

### Prerequisites

- XAMPP / WAMP / LAMP
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite

### Installation Steps

**Step 1: Clone the repository**

```bash
git clone https://github.com/vishwas-2/e-Karamchari.git
cd e-Karamchari
```

**Step 2: Create database**

```sql
CREATE DATABASE ekaramchari;
```

**Step 3: Import schema**

```bash
mysql -u root -p ekaramchari < database/schema.sql
```

Or import `database/schema.sql` via phpMyAdmin.

**Step 4: Configure database**

Edit `backend/config/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ekaramchari');
define('DB_USER', 'root');
define('DB_PASS', '');
```

**Step 5: Create an administrator account**

Do not use a password or reusable administrator credential from this README. Generate a unique password locally and store only its bcrypt hash in the database. For example, use PHP's `password_hash()` function:

```php
<?php
echo password_hash('REPLACE_WITH_A_UNIQUE_PASSWORD', PASSWORD_BCRYPT);
```

Use the generated hash when creating the administrator record in your local database, and choose a unique email address and employee ID for the environment. Never commit the plaintext password or a reusable production credential to the repository.

For production deployments, create a unique administrator credential during deployment or through a secure password-reset/setup process, then change it immediately after initial setup.

### Access URLs

| Portal | URL |
|:-------|:----|
| 🏠 Home | http://localhost/e-Karamchari/ |
| 👨‍💻 Admin Login | http://localhost/e-Karamchari/admin-login.html |
| 👨‍💼 Employee Login | http://localhost/e-Karamchari/employee-login.html |

---

## 📁 Project Structure

```
e-Karamchari/
├── index.html
├── admin-login.html
├── employee-login.html
│
├── admin/
│   ├── dashboard.html
│   ├── employees.html
│   ├── add-employee.html
│   ├── leave-approvals.html
│   ├── grievances.html
│   ├── attendance.html
│   ├── salary.html
│   ├── settings.html
│   └── profile.html
│
├── employee/
│   ├── dashboard.html
│   ├── apply-leave.html
│   ├── leave-status.html
│   ├── submit-grievance.html
│   ├── attendance.html
│   ├── salary-slip.html
│   └── profile.html
│
├── backend/
│   ├── api/
│   ├── config/
│   ├── middleware/
│   └── logs/
│
├── frontend/
│   ├── css/
│   ├── js/
│   └── chatbot/
│
└── database/
    └── schema.sql
```

---

## 🗄️ Database Tables

| Table | Description |
|:------|:------------|
| users | User accounts |
| roles | SUPER_ADMIN, ADMIN, OFFICER, EMPLOYEE |
| departments | Organization departments |
| designations | Job titles and grades |
| leave_types | CL, EL, ML, etc. |
| leave_requests | Leave applications |
| leave_balance | Employee leave balances |
| grievances | Complaints and issues |
| grievance_categories | Grievance types |
| attendance | Daily attendance records |
| salary_slips | Monthly payroll |
| two_factor_auth | 2FA secrets and backup codes |
| holidays | Holiday calendar |
| sessions | Active user sessions |
| activity_logs | Audit trail |

---

## 🔐 Two-Factor Authentication

### Setup Process

1. Go to **Profile** page
2. Click **Enable 2FA** button
3. Scan QR code with **Google Authenticator** app
4. Enter 6-digit code to verify
5. Save **backup codes** securely

### Login with 2FA

- Enter your credentials as usual
- Enter 6-digit code from authenticator app
- Or use 8-character backup code

---

## 👥 User Roles

| Role | Employees | Approvals | Reports | Settings |
|:-----|:---------:|:---------:|:-------:|:--------:|
| SUPER_ADMIN | Full | All | All | Yes |
| ADMIN | Full | All | All | No |
| OFFICER | View | Dept | Dept | No |
| EMPLOYEE | No | No | Self | No |

---

## 🔌 API Reference

### Authentication

| Method | Endpoint | Description |
|:-------|:---------|:------------|
| POST | /api/auth.php?action=login | Employee login |
| POST | /api/auth.php?action=admin-login | Admin login |
| GET | /api/auth.php?action=check | Verify session |
| POST | /api/auth.php?action=logout | Logout user |

### Two-Factor Auth

| Method | Endpoint | Description |
|:-------|:---------|:------------|
| GET | /api/two-factor.php?action=status | Get 2FA status |
| POST | /api/two-factor.php?action=setup | Setup 2FA |
| POST | /api/two-factor.php?action=verify | Verify code |
| POST | /api/two-factor.php?action=disable | Disable 2FA |

### Employees

| Method | Endpoint | Description |
|:-------|:---------|:------------|
| GET | /api/employees.php?action=list | List employees |
| POST | /api/employees.php?action=create | Add employee |
| POST | /api/employees.php?action=update | Update employee |

### Leaves

| Method | Endpoint | Description |
|:-------|:---------|:------------|
| GET | /api/leaves.php?action=list | List requests |
| POST | /api/leaves.php?action=apply | Apply leave |
| POST | /api/leaves.php?action=approve | Approve/Reject |

---

## 🐛 Troubleshooting

| Issue | Solution |
|:------|:---------|
| Database connection error | Check MySQL is running and credentials are correct |
| 404 on API calls | Enable Apache mod_rewrite |
| Session issues | Clear browser cookies |
| 2FA QR not showing | Check internet connection |

---

## ✅ Production Checklist

- [ ] Enable HTTPS/SSL
- [ ] Create unique administrator credentials during deployment
- [ ] Set SESSION_SECURE = true in config
- [ ] Configure firewall rules
- [ ] Setup automated database backups
- [ ] Enable error logging

---

## 🤝 Contributing

1. Fork the repository
2. Create feature branch: `git checkout -b feature/AmazingFeature`
3. Commit changes: `git commit -m 'Add AmazingFeature'`
4. Push to branch: `git push origin feature/AmazingFeature`
5. Open Pull Request

---

## 📄 License

This project is licensed under the MIT License.

---

<div align="center">

### 🏆 Developed for Hack4Delhi Hackathon

**Made with ❤️ by Vishwas**

</div>
