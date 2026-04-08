# 🪨 Rocky Company — HRIS + Payroll System

> **Version 1.0.0** — A web-based Human Resource Information System and Payroll platform built with PHP and MySQL, tailored for Philippine-based companies with full **2026 statutory deduction compliance** (SSS, PhilHealth, Pag-IBIG, BIR TRAIN Law).

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
- [Modules — Admin](#-modules--admin)
- [Modules — Employee Self-Service](#-modules--employee-self-service)
- [Philippine Statutory Deductions (2026)](#-philippine-statutory-deductions-2026)
- [Security](#-security)
- [License](#-license)

---

## 🔍 Overview

**Rocky Company HRIS + Payroll System** is a comprehensive internal web application managing the full employee lifecycle — from recruitment and onboarding through payroll processing and offboarding. Built with a lightweight custom PHP MVC architecture, it is specifically designed for **2026 Philippine labor and statutory compliance**, including semi-monthly payroll cutoffs, BIR TRAIN Law withholding tax, SSS Circular 2024-006, PhilHealth PA2025-0002, and HDMF Circular 460.

The system supports three user roles: **Admin**, **Management**, and **Employee**, each with dedicated dashboards and scoped access.

---

## ✨ Features

| Module | Admin | Management | Employee |
|---|:---:|:---:|:---:|
| Dashboard & Statistics | ✅ | ✅ | ✅ |
| Employee Management | ✅ | 👁️ | — |
| Attendance Tracking | ✅ | 👁️ | 👁️ |
| Leave Management | ✅ | 👁️ | ✅ |
| Payroll Processing | ✅ | 👁️ | — |
| Payslips | ✅ | — | ✅ |
| 13th Month Pay | ✅ | 👁️ | — |
| Salary Deductions | ✅ | — | — |
| Reimbursements | ✅ | — | ✅ |
| Payroll Settings | ✅ | — | — |
| Holidays | ✅ | — | — |
| Recruitment | ✅ | 👁️ | — |
| Announcements | ✅ | — | 👁️ |
| Departments & Positions | ✅ | — | — |
| User Management | ✅ | — | — |
| Activity Log | ✅ | — | — |

✅ = Full access · 👁️ = View only · — = No access

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.x (custom lightweight MVC — no framework) |
| Database | MySQL 8.x via PDO with prepared statements |
| Frontend | AdminLTE 3.2, Bootstrap 4.6, jQuery 3.6 |
| Icons | Font Awesome 6.4 |
| Fonts | Google Fonts — Plus Jakarta Sans |
| Architecture | MVC-inspired (Router → Controller → Model → View) |
| Config | `.env` file for environment management |
| PDF Export | Native PHP HTML rendering (payslip export) |

---

## 📁 Project Structure

```
rocky-company-system/
├── index.php                        # Application entry point & login page
├── logout.php                       # Session destruction
├── reset_password.php               # Password reset handler
├── clear_session.php                # Force session clear utility
├── .env                             # Local environment config (NOT in Git)
├── .env.example                     # Environment config template
│
├── config/
│   ├── config.php                   # App constants, roles, leave types, employment types
│   └── database.php                 # .env loader & PDO connection constants
│
├── core/
│   ├── Database.php                 # PDO singleton connection class
│   ├── Model.php                    # Central facade — proxies to all sub-models
│   ├── Controller.php               # Base controller (auth, CSRF, flash, JSON helpers)
│   ├── PhilippineDeductions.php     # 2026 SSS / PhilHealth / Pag-IBIG / BIR engine
│   └── models/
│       ├── BaseModel.php            # DB accessor base class
│       ├── ActivityLogModel.php     # Audit trail queries
│       ├── AnnouncementModel.php    # Announcements CRUD
│       ├── AttendanceModel.php      # Attendance records & cutoff summary
│       ├── DashboardModel.php       # Aggregated KPI stats
│       ├── DepartmentModel.php      # Departments & positions
│       ├── EmployeeModel.php        # Employee CRUD, salary history, documents
│       ├── HolidayModel.php         # Philippine holidays table
│       ├── LeaveModel.php           # Leave requests & balance management
│       ├── PayrollModel.php         # Payroll records, deductions, YTD, 13th month
│       ├── RecruitmentModel.php     # Job postings & applicant pipeline
│       ├── ReimbursementModel.php   # Expense reimbursement requests
│       └── UserModel.php            # User accounts & authentication
│
├── app/
│   ├── controllers/
│   │   └── AuthController.php       # Login / logout / session management
│   ├── ajax/
│   │   ├── payroll_preview.php      # Live net pay preview (AJAX endpoint)
│   │   ├── check_holidays.php       # Holiday date lookup (AJAX)
│   │   └── pending_count.php        # Pending leave badge count (AJAX polling)
│   └── views/
│       ├── admin/                   # Admin panel — 17 pages
│       │   ├── dashboard.php
│       │   ├── employees.php
│       │   ├── attendance.php
│       │   ├── leave.php
│       │   ├── payroll.php
│       │   ├── payroll_export.php
│       │   ├── payroll_settings.php
│       │   ├── payslip.php
│       │   ├── payslip_export.php
│       │   ├── thirteenth_month.php
│       │   ├── reimbursements.php
│       │   ├── holidays.php
│       │   ├── recruitment.php
│       │   ├── announcements.php
│       │   ├── departments.php
│       │   ├── users.php
│       │   └── activity_log.php
│       ├── employee/                # Employee portal — 7 pages
│       │   ├── dashboard.php
│       │   ├── my_payslips.php
│       │   ├── payslip_pdf.php
│       │   ├── my_attendance.php
│       │   ├── my_leaves.php
│       │   ├── my_reimbursements.php
│       │   └── profile.php
│       ├── management/
│       │   └── dashboard.php
│       └── layouts/
│           ├── admin_header.php
│           ├── admin_footer.php
│           ├── employee_header.php
│           └── employee_footer.php
│
└── assets/
    ├── css/
    │   ├── common.css               # Shared custom styles
    │   └── admin.css                # Admin-specific overrides
    ├── dist/                        # AdminLTE compiled assets
    └── plugins/                     # Bootstrap, jQuery, OverlayScrollbars
```

---

## 📦 Requirements

- **PHP** 8.0 or higher
- **MySQL** 8.0 or higher
- Web server: **XAMPP**, **WAMP**, **Laragon**, or Apache + PHP
- Modern web browser (Chrome, Firefox, or Edge recommended)

---

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/rokkicakesdev/rockycompanysystem.git
cd rockycompanysystem
```

Or download and extract the ZIP into your web server root (e.g., `htdocs/` for XAMPP).

### 2. Create the Database

```sql
CREATE DATABASE rocky_payroll
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 3. Import the SQL Schema

```bash
mysql -u root -p rocky_payroll < database/rocky_payroll.sql
```

### 4. Configure Environment

```bash
cp .env.example .env
# Edit .env with your actual DB credentials and settings
```

### 5. Start Your Server

```
http://localhost/rocky-company-system/
```

---

## ⚙️ Configuration

All settings are managed through `.env`. **Never commit this file.**

```env
# ── Database ────────────────────────────────────────────────────
DB_HOST=localhost
DB_PORT=3306
DB_NAME=rocky_payroll
DB_USER=root
DB_PASS=yourpassword
DB_CHARSET=utf8mb4

# ── Application ─────────────────────────────────────────────────
APP_ENV=development          # development | production
APP_NAME=Rocky Company System
APP_VERSION=1.0.0

# ── URLs ────────────────────────────────────────────────────────
BASE_URL=http://localhost/rocky-company-system
ASSETS_URL=/assets

# ── Company ─────────────────────────────────────────────────────
COMPANY_NAME=Rocky Company
COMPANY_ADDRESS=Paranaque City, Metro Manila

# ── Business Rules ──────────────────────────────────────────────
WORKING_DAYS=22              # Standard monthly working days denominator
WORK_HOURS=8                 # Standard hours per day
RECORDS_PER_PAGE=15          # Pagination size
```

---

## 👥 User Roles

| Role | Description | Access |
|---|---|---|
| `admin` | HR Administrator | Full access to all modules |
| `management` | Manager / Executive | Read-only dashboard and KPI stats |
| `employee` | Regular Employee | Self-service portal only |

### Default Login Setup

```sql
INSERT INTO users (name, username, email, password, role, status)
VALUES ('Administrator', 'admin', 'admin@company.com',
        '$2y$10$<bcrypt_hash>', 'admin', 'active');
```

---

## 🖥️ Modules — Admin

### 1. Dashboard
- **KPI Tiles:** Active employees, total departments, pending leaves, open job postings, new applicants
- **Payroll summary:** Current and last month total net pay
- **Headcount by department** bar chart
- **Recent activity log** feed
- **Pending leave requests** quick list

### 2. Employee Management
- Add, edit, and soft-delete employee records
- Fields: personal info, government IDs (SSS, PhilHealth, Pag-IBIG, TIN), emergency contact, profile photo
- Employment types: Regular, Probationary, Contractual, Part-Time
- Status tracking: Active, Inactive, Resigned, Terminated, On Leave
- **Salary history** — automatic log on every salary update
- Employee search and department filter
- Leave balance management per employee per type (11 leave types)
- Employee document storage

### 3. Attendance Tracking
Three view modes:
- **Daily View** — Record attendance for all employees on a selected date. Statuses: Present, Absent, Late, Half-Day, On Leave (with leave type), Holiday, Rest Day. Time-in/Time-out, OT hours, notes.
- **Monthly Summary** — Tabular count per employee: Present, Absent, Late, On Leave, Half-Day, Holiday, OT hours.
- **Calendar View** *(new)* — Full month grid per employee. Color-coded cells for every day. Holiday names displayed inside cells. Hover tooltips with time details. Prev/next month navigation.

Features: Holiday auto-detection, weekend auto-set, inline update with audit notes, bulk save with confirmation modal.

### 4. Leave Management
- View all leave requests with filters by status and type
- 11 leave types: Sick, Vacation, Bereavement, Emergency, SIL, Maternity, Paternity, Solo Parent, VAWC, Magna Carta, Unpaid
- **Approve / Reject** with admin review notes
- Automatic leave balance deduction on approval
- Unpaid leave automatically flags attendance as `on_leave (unpaid)` → deducted from payroll
- Pending leave badge counter (live AJAX polling)

### 5. Payroll Processing
- **Semi-monthly periods:** `YYYY-MM-1` (1st–15th) and `YYYY-MM-2` (16th–end)
- **Generate payroll** for selected employees and period
- Missing attendance guard — blocks generation if no attendance logs exist
- Automatic computation of all statutory deductions via `PhilippineDeductions.php`
- **Proration** for new hires (days before `date_start` generate a proration deduction, not an absence)
- Absent deduction, unpaid leave deduction, overtime pay, holiday premium pay (200% regular / 130% special)
- Manual **salary deductions** with reason categories: lost asset, damaged asset, loan, cash advance, other
- Payroll notes per record
- **Pending → Released** status workflow with per-record or bulk release
- Export payroll list to **Excel** and **PDF**
- Column breakdown: Basic Salary, Allowance, Gross Pay, SSS, PhilHealth, Pag-IBIG, W.Tax, Absent/Unpaid, Total Deductions, Net Pay, Status

### 6. Payslip
- Full official payslip per employee per period
- Sections: Earnings, Deductions, Net Pay banner, Attendance Summary (Scheduled/Worked/Absent/On Leave), Year-to-Date Summary (Earnings YTD, Deductions YTD, Employer Contributions YTD)
- Late Start / Absent Deduction clearly labelled
- Government deduction note (1st cutoff = none; 2nd cutoff = full or split)
- **Print** and **Export PDF** actions
- Processed-by attribution (Payroll Administrator name)

### 7. 13th Month Pay (PD 851)
- Year selector for batch computation
- Auto-calculates per employee: `Total Basic Earned ÷ 12`
- Eligibility: employees who have worked at least one cutoff in the selected year
- **Generate** saves pending records; **Release** marks as paid (individual or bulk)
- Integrates into December 1st cutoff payroll gross pay automatically
- Summary table: employee, months worked, total basic earned, 13th month amount, status

### 8. Payroll Settings
- Per-employee payroll configuration:
  - **1st Cutoff Fixed Amount** — override for non-standard salary splits
  - **Tax Method** — `half_monthly` (monthly tax ÷ 2) or `bir_table` (fresh annualised computation)
  - **Gov Deduction Mode** — `second_cutoff` (full SSS/PhilHealth/Pag-IBIG on 2nd) or `split` (half each cutoff)
- Live **net pay preview** via AJAX as you type
- BIR-compliant annualised withholding tax method used throughout

### 9. Salary Deductions
- Add manual deductions to any payroll record
- Reason categories: Lost Asset, Damaged Asset, Loan Repayment, Cash Advance, Other
- Description, amount, and notes fields
- Edit and delete with activity log entries
- Deductions appear on payslip as line items

### 10. Reimbursements
- Admin reviews all submitted reimbursement requests
- Types: Transportation, Meal/Per Diem, Medical, Communication, Office Supplies, Training, Other
- **Approve / Reject** with review notes and timestamp
- Filter by status (Pending, Approved, Rejected)
- Date submitted, amount, receipt description visible

### 11. Holidays
- Maintain the Philippine public holiday calendar
- Types: Regular Holiday, Special Non-Working, Special Working
- Recurring flag for year-over-year holidays
- Holiday list used by payroll engine for proration and premium pay computation
- 20 pre-loaded 2026 Philippine holidays

### 12. Recruitment
- **Job Postings:** Title, department, position, salary range, slots, deadline, status (Open/Closed/Draft)
- **Applicant Pipeline:** New → Interviewed → Hired / Rejected
- Interview date scheduling, processing notes
- Applicant count per posting
- Open postings and new applicants reflected in dashboard KPIs

### 13. Announcements
- Company-wide broadcast with title, body, type, and optional expiry date
- Pinned announcement support
- Visible to all roles (admin, management, employee portals)
- CRUD management by admin

### 14. Departments & Positions
- Create and manage departments
- Create positions linked to departments
- Employee headcount per department visible in dashboard

### 15. User Management
- Create, edit, activate, and deactivate system user accounts
- Roles: Admin, Management, Employee
- Secure password management (bcrypt)
- Link user account to employee record

### 16. Activity Log
- System-wide audit trail for all admin actions
- Captures: user, action type, description, IP address, timestamp
- Logged actions: attendance saves/updates, payroll generation/release, salary deductions, leave reviews, 13th month operations, and more

---

## 👤 Modules — Employee Self-Service

### 1. My Dashboard
- Latest payslip net pay and period
- Attendance summary for current month
- Leave balance summary for all 11 leave types
- Pending leave requests status
- Latest company announcements

### 2. My Payslips
- List of all payslips by period (semi-monthly)
- Detailed payslip view matching admin format
- **Export to PDF** for personal records

### 3. My Attendance
- Monthly attendance records with status indicators
- Hours worked per day, overtime hours
- Monthly summary totals

### 4. My Leaves
- File new leave requests (type, date range, reason)
- Real-time leave balance display per type
- Track approval status (Pending, Approved, Rejected) with admin notes

### 5. My Reimbursements
- Submit reimbursement requests with type, amount, and description
- Track submission and approval status
- View admin review notes

### 6. My Profile
- Update personal information: address, phone, emergency contact
- View employment details (read-only): position, department, date started, employment type

---

## 🇵🇭 Philippine Statutory Deductions (2026)

All deductions are computed by `core/PhilippineDeductions.php` and are fully up to date for **2026**.

### SSS — SSS Circular No. 2024-006 (effective Jan 2025, unchanged 2026)
| Component | Rate | Monthly Cap |
|---|---|---|
| Employee (EE) | 5.0% of MSC | ₱1,750 (MSC ₱35,000) |
| Employer (ER) | 10.0% of MSC | ₱3,500 (MSC ₱35,000) |
| MSC Range | ₱5,000 – ₱35,000 | — |

### PhilHealth — PA2025-0002 (unchanged 2026)
| Component | Rate | Floor / Ceiling |
|---|---|---|
| Employee (EE) | 2.5% of MBS | Floor ₱10,000 / Ceiling ₱100,000 |
| Employer (ER) | 2.5% of MBS | Max EE+ER = ₱5,000/month |

### Pag-IBIG — HDMF Circular No. 460 (effective Feb 2024, unchanged 2026)
| Salary | EE Rate | Max EE | Max ER |
|---|---|---|---|
| ≤ ₱1,500 | 1% | ₱200/month | ₱200/month |
| > ₱1,500 | 2% | ₱200/month | ₱200/month |
| MFS Cap | — | ₱10,000 | ₱10,000 |

### BIR Withholding Tax — TRAIN Law RR 13-2023 (unchanged 2026)
Computed using the **annualised method** (semi-monthly taxable × 24 → annual bracket → ÷ 24).

| Annual Taxable Income | Tax |
|---|---|
| ₱0 – ₱250,000 | 0% |
| ₱250,001 – ₱400,000 | 15% of excess over ₱250,000 |
| ₱400,001 – ₱800,000 | ₱22,500 + 20% of excess over ₱400,000 |
| ₱800,001 – ₱2,000,000 | ₱102,500 + 25% of excess over ₱800,000 |
| ₱2,000,001 – ₱8,000,000 | ₱402,500 + 30% of excess over ₱2,000,000 |
| Above ₱8,000,000 | ₱2,402,500 + 35% of excess over ₱8,000,000 |

### Semi-Monthly Payroll Rules
| Cutoff | Gov Deductions | Withholding Tax |
|---|---|---|
| 1st (1–15th) | None | Annualised on basic earned (no gov deds yet) |
| 2nd (16–end) | Full month SSS + PhilHealth + Pag-IBIG | Annualised on basic minus gov deds |

**Gov Deduction Mode:**
- `second_cutoff` — full SSS/PhilHealth/Pag-IBIG collected on 2nd cutoff only
- `split` — half of each government contribution per cutoff

### Year-End Tax Reconciliation
Computed on December 2nd cutoff. Compares annualised actual tax owed vs. total withheld year-to-date. Positive = additional tax deducted; Negative = refund added to net pay.

---

## 🔒 Security

| Feature | Implementation |
|---|---|
| SQL Injection Prevention | All queries use PDO prepared statements |
| Password Hashing | `password_hash()` with bcrypt cost 10 |
| XSS Prevention | `htmlspecialchars()` on all rendered output |
| CSRF Protection | Per-session token on all POST forms |
| Session Timeout | 30-minute inactivity auto-logout |
| Role-Based Access Control | Per-role, per-view guards on every page |
| Credentials in `.env` | Never committed to version control |
| Session Cookie Security | `httponly`, `SameSite=Strict`, `use_strict_mode` |
| Dynamic SQL Whitelist | Applied on leave balance field lookups |
| Login Attempt Logging | Failed logins recorded with IP in `login_attempts` |

---

## 📄 License

This project is proprietary software developed for **Rocky Company** internal use.

© 2026 Rocky Company — All rights reserved.

Unauthorized copying, distribution, or modification of this software is strictly prohibited.