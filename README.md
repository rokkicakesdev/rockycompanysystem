# 🪨 Rocky Company — HRIS + Payroll System

> **Version 1.0.0** — A web-based Human Resource Information System and Payroll platform built with PHP and MySQL, tailored for Philippine-based companies with full 2025 statutory deduction compliance.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?style=flat&logo=mysql&logoColor=white)
![AdminLTE](https://img.shields.io/badge/AdminLTE-3.2-3C8DBC?style=flat)
![License](https://img.shields.io/badge/License-Proprietary-red?style=flat)

---

## 📋 Table of Contents

- [Overview](#-overview)
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Project Structure](#-project-structure)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [User Roles](#-user-roles)
- [Employee Self-Service](#-employee-self-service)
- [Philippine Statutory Deductions](#-philippine-statutory-deductions)
- [Security](#-security)
- [License](#-license)

---

## 🔍 Overview

**Rocky Company HRIS + Payroll System** is a comprehensive internal web application for managing the full employee lifecycle — from onboarding to payroll processing. It is specifically designed to comply with **2025 Philippine labor and tax regulations**, including up-to-date SSS, PhilHealth, Pag-IBIG, and BIR TRAIN Law withholding tax computations.

The system supports three user roles: **Admin**, **Management**, and **Employee**, each with their own dedicated dashboard and access level.

---

## ✨ Features

### 👤 Employee Management
- Add, edit, and manage complete employee records
- Track personal info, government IDs (SSS, PhilHealth, Pag-IBIG, TIN)
- Employment types: Regular, Probationary, Contractual, Part-Time
- Employee status tracking: Active, Inactive, Resigned, Terminated, On Leave
- Department and position management
- Emergency contact information
- Automatic salary history logging on every salary update

### 💰 Payroll Processing
- Automated payroll computation based on basic salary and allowances
- Full 2025 Philippine statutory deduction calculations (SSS, PhilHealth, Pag-IBIG, BIR)
- Monthly payroll period management with pending → released workflow
- Payslip generation and export per employee
- Payroll summary by period with total net pay

### 📅 Attendance Tracking
- Monthly attendance records per employee
- Attendance statuses: Present, Absent, Late, Half-day, On Leave, Holiday
- Overtime hours tracking
- Monthly attendance summary per employee

### 🌴 Leave Management
- Supports **10 leave types**:
  - Sick Leave, Vacation Leave, Bereavement Leave, Emergency Leave
  - Service Incentive Leave (SIL), Maternity Leave, Paternity Leave
  - Solo Parent Leave, VAWC Leave, Magna Carta Leave, Unpaid Leave
- Leave balance tracking per employee per type
- Admin approval/rejection workflow with review notes
- Automatic leave balance deduction upon approval

### 📢 Announcements
- Company-wide announcement broadcasting
- Announcement types with optional expiry dates
- Pinned announcements support
- Visible across all roles

### 🧑‍💼 Recruitment
- Job postings with department, position, salary range, slots, and deadline
- Applicant tracking pipeline: New → Interviewed → Hired / Rejected
- Applicant count per job posting

### 🔐 User Management
- Create and manage system user accounts
- Role-based access control (Admin / Management / Employee)
- Account activation and deactivation
- Secure password management

### 📊 Activity Log
- System-wide audit trail of all admin actions
- Logs user, action, description, IP address, and timestamp

---

## 🛠️ Tech Stack

| Layer        | Technology                                  |
|--------------|---------------------------------------------|
| Backend      | PHP 8.x (custom lightweight MVC)            |
| Database     | MySQL 8.x via PDO (prepared statements)     |
| Frontend     | AdminLTE 3.2, Bootstrap 4, jQuery 3         |
| Icons        | Font Awesome 6                              |
| Fonts        | Google Fonts — Inter                        |
| Architecture | MVC-inspired (no external framework)        |
| Environment  | `.env` file for configuration management    |

---

## 📁 Project Structure

```
rocky-company-system/
├── index.php                        # Login page & application entry point
├── logout.php                       # Session destruction & logout
├── reset_password.php               # Password reset handler
├── clear_session.php                # Force session clear utility
├── .env                             # Local environment config (NOT in Git)
├── .env.example                     # Environment config template
│
├── config/
│   ├── config.php                   # App constants, roles, leave types, employment types
│   └── database.php                 # .env loader & DB credential constants
│
├── core/
│   ├── Database.php                 # PDO singleton connection class
│   ├── Model.php                    # Central model — all database queries
│   ├── Controller.php               # Base controller (auth, CSRF, flash, JSON)
│   └── PhilippineDeductions.php     # SSS, PhilHealth, Pag-IBIG, BIR computation engine
│
├── app/
│   ├── controllers/
│   │   └── AuthController.php       # Authentication flow controller
│   ├── views/
│   │   ├── admin/                   # Admin panel views (11 pages)
│   │   │   ├── dashboard.php
│   │   │   ├── employees.php
│   │   │   ├── payroll.php
│   │   │   ├── payroll_export.php
│   │   │   ├── payslip.php
│   │   │   ├── attendance.php
│   │   │   ├── leave.php
│   │   │   ├── departments.php
│   │   │   ├── recruitment.php
│   │   │   ├── announcements.php
│   │   │   ├── users.php
│   │   │   └── activity_log.php
│   │   ├── employee/                # Employee self-service views (6 pages)
│   │   │   ├── dashboard.php
│   │   │   ├── my_payslips.php
│   │   │   ├── my_attendance.php
│   │   │   ├── my_leaves.php
│   │   │   ├── profile.php
│   │   │   └── announcements.php
│   │   ├── management/              # Management role views
│   │   │   └── dashboard.php
│   │   └── layouts/                 # Shared layout partials
│   │       ├── admin_header.php
│   │       ├── admin_footer.php
│   │       ├── employee_header.php
│   │       └── employee_footer.php
│   └── ajax/
│       └── pending_count.php        # AJAX endpoint for pending leave badge
│
└── assets/
    ├── css/
    │   └── common.css               # Shared custom styles
    ├── dist/                        # AdminLTE compiled assets
    └── plugins/                     # Bootstrap, jQuery, FontAwesome, OverlayScrollbars
```

---

## 📦 Requirements

- **PHP** 8.0 or higher
- **MySQL** 8.0 or higher
- A local or remote web server — **XAMPP**, **WAMP**, **Laragon**, or **Apache + PHP**
- Web browser (Chrome, Firefox, or Edge recommended)

---

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/rokkicakesdev/rockycompanysystem.git
cd rockycompanysystem
```

Or download and extract the ZIP into your web server's root directory (e.g., `htdocs/` for XAMPP).

### 2. Create the Database

Open your MySQL client or phpMyAdmin and run:

```sql
CREATE DATABASE rocky_payroll
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 3. Import the SQL Schema

```bash
mysql -u root -p rocky_payroll < database/rocky_payroll.sql
```

> If a `.sql` dump file is not included, contact the project maintainer.

### 4. Set Up Environment Configuration

Copy the example environment file and fill in your values:

```bash
cp .env.example .env
```

Then edit `.env` with your actual settings (see [Configuration](#-configuration) below).

### 5. Start Your Server

For XAMPP, start Apache and MySQL, then open:

```
http://localhost/rocky-company-system/
```

---

## ⚙️ Configuration

All sensitive settings are managed through the `.env` file in the project root. **Never commit this file to version control.**

```env
# ── Database Connection ────────────────────────────────────────
DB_HOST=localhost
DB_PORT=3306
DB_NAME=your_db_name_here
DB_USER=your_user_here
DB_PASS=your_password_here
DB_CHARSET=utf8mb4

# ── Application Settings ───────────────────────────────────────
APP_ENV=development        # Change to 'production' on live server
APP_NAME=Rocky Company System
APP_VERSION=1.0.0

# ── URLs & Paths ───────────────────────────────────────────────
BASE_URL=http://localhost/rocky-company-system
ASSETS_URL=/assets

# ── Company Info ───────────────────────────────────────────────
COMPANY_NAME=Rocky Company
COMPANY_ADDRESS=Paranaque City, Metro Manila

# ── Business Rules ─────────────────────────────────────────────
WORKING_DAYS=22
WORK_HOURS=8
RECORDS_PER_PAGE=15
```

> ⚠️ Set `APP_ENV=production` on your live server to prevent sensitive error details from being exposed.

---

## 👥 User Roles

| Role | Description | Access Level |
|---|---|---|
| `admin` | HR Administrator | Full access — employees, payroll, leave, attendance, recruitment, users, logs |
| `management` | Manager / Executive | Read-only company dashboard and statistics |
| `employee` | Regular Employee | Self-service portal — own payslips, attendance, leaves, and profile |

### Default Login

Set up your initial admin account directly in the database:

```sql
INSERT INTO users (name, username, email, password, role, status)
VALUES (
  'Administrator',
  'admin',
  'admin@rockycompany.com',
  '$2y$10$...',
  'admin',
  'active'
);
```

---

## 🧑‍💻 Employee Self-Service

Employees can log in with their own credentials to access a dedicated portal:

| Page | Description |
|---|---|
| **Dashboard** | Overview of latest payslip, attendance this month, pending leaves |
| **My Payslips** | View and download payslips by period |
| **My Attendance** | View monthly attendance records and summary |
| **My Leaves** | File leave requests and track approval status |
| **My Profile** | Update personal info, phone, address, emergency contact |
| **Announcements** | View company-wide announcements |

> Employee accounts must be linked to an employee record via `employee_id` in the `users` table.

---

## 🇵🇭 Philippine Statutory Deductions

All deductions are computed automatically by `core/PhilippineDeductions.php` based on the employee's **monthly basic salary**.

| Contribution | Basis | Rate / Rule | Regulation |
|---|---|---|---|
| **SSS** | Monthly Salary Credit (MSC) | 15% total — 5% Employee / 10% Employer; MSC range ₱5,000–₱35,000 | SSS Circular No. 2024-006 |
| **PhilHealth** | Monthly Basic Salary (MBS) | 5% total — split 50/50; floor ₱10,000 / ceiling ₱100,000 | PhilHealth Advisory PA2025-0002 |
| **Pag-IBIG** | Monthly Fund Salary (MFS) | 2% EE (1% if salary ≤ ₱1,500); ER 2%; max ₱200/side; MFS capped at ₱10,000 | HDMF Circular No. 460 |
| **BIR Withholding Tax** | Taxable income after deductions | Progressive 0%–35% monthly bracket (TRAIN Law) | RR 13-2023 / RMC 05-2023 |

### Monthly Tax Brackets (TRAIN Law)

| Taxable Income (Monthly) | Tax |
|---|---|
| ₱0 – ₱20,833 | 0% |
| ₱20,833 – ₱33,332 | 15% of excess over ₱20,833 |
| ₱33,333 – ₱66,666 | ₱2,500 + 20% of excess over ₱33,333 |
| ₱66,667 – ₱166,666 | ₱10,833 + 25% of excess over ₱66,667 |
| ₱166,667 – ₱666,666 | ₱40,833 + 30% of excess over ₱166,667 |
| Above ₱666,667 | ₱200,833 + 35% of excess over ₱666,667 |

---

## 🔒 Security

| Feature | Status |
|---|---|
| SQL Injection Prevention | ✅ All queries use PDO prepared statements |
| Password Hashing | ✅ `password_hash()` with bcrypt |
| XSS Prevention | ✅ `htmlspecialchars()` on all output |
| Session Timeout | ✅ 30-minute inactivity auto-logout |
| Role-Based Access Control | ✅ Per role, per view |
| CSRF Protection | ✅ Token infrastructure in `Controller.php` |
| Credentials in `.env` | ✅ Never committed to Git |
| Dynamic SQL Column Whitelist | ✅ Applied in leave balance deduction |

---

## 📄 License

This project is proprietary software developed for **Rocky Company** internal use.

© 2025 Rocky Company — All rights reserved.

Unauthorized copying, distribution, or modification of this software is strictly prohibited.