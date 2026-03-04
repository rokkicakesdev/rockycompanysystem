# 🪨 Rocky HRIS + Payroll System

> **Version 1.0.0** — A web-based Human Resource Information System and Payroll platform built with PHP and MySQL, tailored for Philippine-based companies with full statutory deduction compliance.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [User Roles](#user-roles)
- [Philippine Statutory Deductions](#philippine-statutory-deductions)
- [License](#license)

---

## Overview

Rocky HRIS + Payroll System is a comprehensive internal tool for managing employees, payroll, attendance, leave, recruitment, and announcements — all in one place. It is specifically designed to comply with **2025 Philippine labor and tax regulations**, including SSS, PhilHealth, Pag-IBIG, and BIR TRAIN Law withholding tax computations.

---

## Features

### 👤 Employee Management
- Add, edit, and manage employee records
- Track employment type (Regular, Probationary, Contractual, Part-Time)
- Monitor employee status (Active, Inactive, Resigned, Terminated)
- Department-based organization

### 💰 Payroll
- Automated payroll computation based on basic salary and allowances
- Statutory deduction calculations (SSS, PhilHealth, Pag-IBIG, BIR)
- Payslip generation per employee

### 📅 Attendance
- Track employee attendance records
- Admin-managed attendance view

### 🌴 Leave Management
- Support for 11 leave types:
  - Sick Leave (SL), Vacation Leave (VL), Bereavement, Emergency
  - Service Incentive Leave (SIL), Maternity, Paternity
  - Solo Parent Leave, VAWC Leave, Magna Carta Leave, Leave Without Pay (LWOP)
- Leave balance tracking per employee

### 📢 Announcements
- Company-wide announcement broadcasting

### 🧑‍💼 Recruitment
- Applicant tracking and recruitment pipeline management

### 🔐 User Management
- Role-based access control (Admin / Management)
- Account activation/deactivation

### 📊 Activity Log
- System-wide activity audit trail for administrators

---

## Tech Stack

| Layer       | Technology                        |
|-------------|-----------------------------------|
| Backend     | PHP 8.x                           |
| Database    | MySQL 8.x (via PDO)               |
| Frontend    | AdminLTE 3.2, Bootstrap, Font Awesome 6 |
| Fonts       | Google Fonts – Inter              |
| Architecture| MVC-inspired (custom lightweight) |

---

## Project Structure

```
project/
├── index.php                   # Login page & entry point
├── logout.php                  # Session logout
├── reset_password.php          # Password reset
├── clear_session.php           # Force clear session
│
├── config/
│   ├── config.php              # App constants, roles, leave types, employment types
│   └── database.php            # PDO singleton & DB credentials
│
├── core/
│   ├── Controller.php          # Base controller class
│   ├── Model.php               # Base model / DB query helpers
│   └── PhilippineDeductions.php # Statutory deduction engine (SSS, PhilHealth, Pag-IBIG, BIR)
│
├── app/
│   ├── controllers/
│   │   └── AuthController.php  # Authentication logic
│   └── views/
│       ├── admin/              # Admin panel views
│       │   ├── dashboard.php
│       │   ├── employees.php
│       │   ├── payroll.php
│       │   ├── payslip.php
│       │   ├── attendance.php
│       │   ├── leave.php
│       │   ├── departments.php
│       │   ├── recruitment.php
│       │   ├── announcements.php
│       │   ├── users.php
│       │   └── activity_log.php
│       ├── management/         # Management role views
│       │   └── dashboard.php
│       └── layouts/            # Shared layout partials
│           ├── admin_header.php
│           └── admin_footer.php
│
└── assets/
    └── css/
        └── common.css          # Shared custom styles
```

---

## Requirements

- PHP **8.0** or higher
- MySQL **8.0** or higher
- A local or remote server (e.g., **XAMPP**, **WAMP**, **Laragon**, or **Apache + PHP**)
- Web browser (Chrome, Firefox, Edge)

---

## Installation

1. **Clone or download** this repository into your web server's root directory (e.g., `htdocs/` for XAMPP):

   ```bash
   git clone https://github.com/your-username/rocky-hris.git
   ```

2. **Create the database** in MySQL:

   ```sql
   CREATE DATABASE rocky_payroll CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Import the SQL schema** (if a `.sql` dump file is provided):

   ```bash
   mysql -u root -p rocky_payroll < database/rocky_payroll.sql
   ```

4. **Configure database credentials** in `config/database.php` (see [Configuration](#configuration)).

5. **Start your server** and navigate to:

   ```
   http://localhost/project/
   ```

---

## Configuration

The project uses environment variables for sensitive settings (via a `.env` file) to keep credentials out of version control.

1. **Create a `.env` file** in the project root (copy from `.env.example` if provided):

```

### `config/config.php`

You can customize company-wide settings:

```php
define('COMPANY_NAME',    'Rocky Company');
define('COMPANY_ADDRESS', 'Paranaque City, Metro Manila');
define('WORKING_DAYS',    22);   // Default working days per month
define('WORK_HOURS',       8);   // Hours per day
define('RECORDS_PER_PAGE', 15);  // Pagination limit
```

---

## User Roles

| Role         | Access                                                                 |
|--------------|------------------------------------------------------------------------|
| `admin`      | Full access — employees, payroll, leave, attendance, users, logs, etc. |
| `management` | Limited dashboard view for management-level oversight                  |

---

## Philippine Statutory Deductions

The system uses `core/PhilippineDeductions.php` to automatically compute all government-mandated deductions based on the employee's **monthly basic salary**.

| Contribution | Rate / Rule | Source |
|---|---|---|
| **SSS** | 15% of MSC (5% employee, 10% employer); MSC range ₱5,000–₱35,000 | SSS Circular 2024-006 |
| **PhilHealth** | 5% of MBS (split 50/50); floor ₱10,000, ceiling ₱100,000 | PhilHealth Advisory PA2025-0002 |
| **Pag-IBIG** | 2% employee (1% if salary ≤ ₱1,500); max ₱200/side; MFS capped at ₱10,000 | HDMF Circular No. 460 |
| **BIR Withholding Tax** | TRAIN Law progressive monthly bracket (0%–35%) | RR 13-2023 / RMC 05-2023 |

---

## License

This project is proprietary software developed for **Rocky Company** internal use.  
© 2025 Rocky Company — All rights reserved.
