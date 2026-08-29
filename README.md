<div align="center">

# 🏛️ e-Karamchari

### Employee Self-Service Portal | Municipal Corporation of Delhi

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Status](https://img.shields.io/badge/Status-Active-success?style=for-the-badge)

**A modern and secure HR management system with Two-Factor Authentication**

[Features](#-features) • [Installation](#-quick-start) • [Security](#-security) • [Notifications](#-notification-system) • [API](#-api-reference)

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
| 💰 Payroll | Salary slip generation |
| 📅 Holidays | Holiday calendar management |
| ⚙️ Settings | System configuration |
| 🤖 Chatbot | AI assistant for help |

---

## 🔐 Security

| Feature | Description |
|:--------|:------------|
| 🔐 Two-Factor Auth | TOTP with Google Authenticator |
| 🔒 Password Hashing | Bcrypt with auto-salt |
| 🛡️ CSRF Protection | Token-based validation |
| 🚫 XSS Prevention | Input sanitization |
| 💉 SQL Injection | Prepared statements |
| ⏱️ Rate Limiting | Brute-force protection |
| 🍪 Session Security | Secure cookies |
| 📋 Audit Logging | Complete activity trail |

---

## 🔔 Notification System

Notifications are generated for important employee/admin actions. Leave applications and grievance submissions notify active administrators; leave status changes notify employees; service-record updates can notify relevant users. Notification helpers are implemented in `backend/middleware/notifications.php`.

### Quick Test

After logging in, open the browser console and run:

```javascript
API.dashboard.createTestNotification().then(() => loadNotifications());
```

Then open the 🔔 notification panel. For end-to-end testing, apply a leave or submit a grievance as an employee and verify the admin notification.

### Troubleshooting

1. Check **F12 → Console** for `[Notifications]` or `[Dashboard]` logs.
2. Check the session with `API.auth.checkSession()`.
3. Check notifications with `API.dashboard.getNotifications({limit: 10})`.
4. Verify the notification panel/list elements and frontend JavaScript errors.
5. Check recent database records:

```sql
SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10;
SELECT user_id, COUNT(*) AS count FROM notifications GROUP BY user_id;
SELECT COUNT(*) FROM notifications WHERE is_read = 0;
```

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

## 🚀 Quick Start

### Prerequisites

- XAMPP / WAMP / LAMP
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite

### Installation Steps

**Step 1: Clone the repository**

```bash
git clone https://github.com/vishwas0229/e-Karamchari.git
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

Edit `backend/config/config.php` with your local database credentials.

**Step 5: Create an administrator account**

Do not use a password or reusable administrator credential from the repository. Generate a unique password locally and store only its bcrypt hash. Never commit plaintext passwords or reusable production credentials.

### Access URLs

| Portal | URL |
|:-------|:----|
| 🏠 Home | http://localhost/e-Karamchari/ |
| 👨‍💻 Admin Login | http://localhost/e-Karamchari/admin-login.html |
| 👨‍💼 Employee Login | http://localhost/e-Karamchari/employee-login.html |

---

## 📁 Project Structure

```text
e-Karamchari/
├── index.html
├── admin-login.html
├── employee-login.html
├── admin/
├── employee/
├── backend/
│   ├── api/
│   ├── config/
│   ├── middleware/
│   └── logs/
├── frontend/
│   ├── css/
│   ├── js/
│   └── chatbot/
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
| notifications | In-app notifications |
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
| Notifications not showing | Check session, API response, console logs, and database records |

---

## ✅ Production Checklist

- [ ] Enable HTTPS/SSL
- [ ] Create unique administrator credentials during deployment
- [ ] Set SESSION_SECURE = true in config
- [ ] Configure firewall rules
- [ ] Setup automated database backups
- [ ] Enable error logging
- [ ] Verify notification delivery
- [ ] Remove test notifications before production use

---

## 🤝 Contributing

1. Fork the repository
2. Create feature branch: `git checkout -b feature/AmazingFeature`
3. Commit changes: `git commit -m 'Add AmazingFeature'`
4. Push to GitHub
5. Open Pull Request

All normal code changes should be made on a separate feature/fix branch and merged through a Pull Request rather than directly into `main`.

---

## 📄 License

This project is licensed under the MIT License.

---

<div align="center">

### 🏆 Developed for Hack4Delhi Hackathon

**Made with ❤️ by Vishwas**

</div>
