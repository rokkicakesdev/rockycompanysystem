-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 04, 2026 at 08:09 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rocky_payroll`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-03-27 11:08:18'),
(2, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-03-28 14:08:32'),
(3, 1, 'CREATE_EMPLOYEE', 'Created employee: Chichi The Dog (EMP-001) | User account: chichi.emp', '::1', '2026-03-28 14:36:55'),
(4, 1, 'SAVE_ATTENDANCE', 'Saved attendance for 1 employees on 2026-01-05 | Errors: 0', '::1', '2026-03-28 14:59:00'),
(5, 1, 'SAVE_ATTENDANCE', 'Saved attendance for 1 employees on 2026-01-06 | Errors: 0', '::1', '2026-03-28 15:05:32'),
(6, 1, 'SAVE_ATTENDANCE', 'Saved attendance for 1 employees on 2026-01-07 | Errors: 0', '::1', '2026-03-28 15:05:58'),
(7, 1, 'SAVE_ATTENDANCE', 'Saved attendance for 1 employees on 2026-01-08 | Errors: 0', '::1', '2026-03-28 15:06:04'),
(8, 1, 'SAVE_ATTENDANCE', 'Saved attendance for 1 employees on 2026-01-09 | Errors: 0', '::1', '2026-03-28 15:06:10'),
(9, 1, 'SAVE_ATTENDANCE', 'Saved attendance for 1 employees on 2026-01-09 | Errors: 0', '::1', '2026-03-28 15:06:18'),
(10, 1, 'SAVE_ATTENDANCE', 'Saved attendance for 1 employees on 2026-01-12 | Errors: 0', '::1', '2026-03-28 15:06:24'),
(11, 1, 'SAVE_ATTENDANCE', 'Saved attendance for 1 employees on 2026-01-13 | Errors: 0', '::1', '2026-03-28 15:06:37'),
(12, 1, 'SAVE_ATTENDANCE', 'Saved attendance for 1 employees on 2026-01-14 | Errors: 0', '::1', '2026-03-28 15:06:41'),
(13, 1, 'SAVE_ATTENDANCE', 'Saved attendance for 1 employees on 2026-01-15 | Errors: 0', '::1', '2026-03-28 15:06:47'),
(14, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-1: 1 records created, 0 skipped.', '::1', '2026-03-28 15:31:02'),
(15, 1, 'RELEASE_PAYROLL', 'Released payroll ID:1 for Chichi The Dog period 2026-01-1', '::1', '2026-03-28 15:49:34'),
(16, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-03-28 16:16:31'),
(17, 1, 'CREATE_EMPLOYEE', 'Created employee: Chicken the Cat (EMP-002) | User account: chicken.emp', '::1', '2026-03-28 16:54:12'),
(18, 6, 'LOGIN', 'User \'chicken.emp\' logged in from ::1', '::1', '2026-03-28 16:54:36'),
(19, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-03-28 16:55:07'),
(20, 1, 'EDIT_PAYROLL_STATUS', 'Changed payroll ID:1 (Chichi The Dog) status from released to modification for period 2026-01-1 | Note: May updates lang po', '::1', '2026-03-28 16:55:41'),
(21, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-03-28 17:28:35'),
(22, 1, 'UPDATE_USER', 'Updated user ID:5', '::1', '2026-03-28 17:28:58'),
(23, 5, 'LOGIN', 'User \'chichi.emp\' logged in from ::1', '::1', '2026-03-28 17:29:18'),
(24, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-03-28 17:31:24'),
(25, 5, 'LOGIN', 'User \'chichi.emp\' logged in from ::1', '::1', '2026-03-28 17:31:50'),
(26, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-03-28 17:39:40'),
(27, 5, 'LOGIN', 'User \'chichi.emp\' logged in from ::1', '::1', '2026-03-28 17:49:22'),
(28, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-03-29 01:30:41'),
(29, 5, 'LOGIN', 'User \'chichi.emp\' logged in from ::1', '::1', '2026-03-29 02:04:38'),
(30, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-03-29 02:44:46'),
(31, 1, 'DELETE_HOLIDAY', 'Deleted ID:23', '::1', '2026-03-29 03:00:59'),
(32, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-03-29 14:24:36'),
(33, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-2: 1 records created, 0 skipped.', '::1', '2026-03-29 14:37:31'),
(34, 1, 'EDIT_PAYROLL_STATUS', 'Changed payroll ID:2 (Chichi The Dog) status from pending to modification for period 2026-01-2 | Note: Update lang po', '::1', '2026-03-29 14:38:00'),
(35, 5, 'LOGIN', 'User \'chichi.emp\' logged in from ::1', '::1', '2026-03-29 15:22:37'),
(36, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-03-30 03:31:08'),
(37, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-03-31 09:11:33'),
(38, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-1: 1 records created, 0 skipped.', '::1', '2026-03-31 09:25:41'),
(39, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-03-31 15:24:04'),
(40, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-1: 1 records created, 0 skipped.', '::1', '2026-03-31 15:24:44'),
(41, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-2: 1 records created, 0 skipped.', '::1', '2026-03-31 15:24:52'),
(42, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-03-31 15:40:00'),
(43, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-04-01 01:22:13'),
(44, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-1: 1 records created, 0 skipped.', '::1', '2026-04-01 01:23:37'),
(45, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-2: 1 records created, 0 skipped.', '::1', '2026-04-01 01:23:46'),
(46, 1, 'SAVE_ATTENDANCE', 'Saved attendance for 1 employees on 2026-01-02 | Errors: 0', '::1', '2026-04-01 01:46:53'),
(47, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-04-01 14:24:07'),
(48, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-2: 1 records created, 0 skipped.', '::1', '2026-04-01 14:29:41'),
(49, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-1: 1 records created, 0 skipped.', '::1', '2026-04-01 14:30:20'),
(50, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-1: 1 records created, 0 skipped.', '::1', '2026-04-01 14:31:52'),
(51, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-2: 1 records created, 0 skipped.', '::1', '2026-04-01 14:32:04'),
(52, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-04-02 03:52:16'),
(53, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-2: 1 records created, 0 skipped.', '::1', '2026-04-02 06:57:02'),
(54, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-04-02 13:46:23'),
(55, 1, 'DELETE_PAYROLL', 'Deleted payroll ID:12 for Chichi The Dog period 2026-01-2', '::1', '2026-04-02 13:46:32'),
(56, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-1: 1 records created, 0 skipped.', '::1', '2026-04-02 13:46:54'),
(57, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-04-03 01:53:17'),
(58, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-04-03 07:13:21'),
(59, 1, 'LOGIN', 'User \'admin1\' logged in from ::1', '::1', '2026-04-04 04:26:25'),
(60, 1, 'DELETE_PAYROLL', 'Deleted payroll ID:13 for Chichi The Dog period 2026-01-1', '::1', '2026-04-04 04:27:28'),
(61, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-1: 1 records created, 0 skipped.', '::1', '2026-04-04 04:27:35'),
(62, 1, 'UPDATE_EMPLOYEE', 'Updated employee ID:1', '::1', '2026-04-04 04:28:53'),
(63, 1, 'UPDATE_EMPLOYEE', 'Updated employee ID:2', '::1', '2026-04-04 04:29:07'),
(64, 1, 'DELETE_PAYROLL', 'Deleted payroll ID:14 for Chichi The Dog period 2026-01-1', '::1', '2026-04-04 04:29:12'),
(65, 1, 'GENERATE_PAYROLL', 'Generated payroll for period 2026-01-1: 1 records created, 0 skipped.', '::1', '2026-04-04 04:29:19'),
(66, 1, 'UPDATE_EMPLOYEE', 'Updated employee ID:2', '::1', '2026-04-04 04:44:17'),
(67, 1, 'SAVE_ATTENDANCE', 'Saved attendance for 2 employees on 2026-01-14 | Errors: 0', '::1', '2026-04-04 04:44:57');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `type` enum('general','payroll','leave','holiday','urgent') NOT NULL DEFAULT 'general',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` date DEFAULT NULL,
  `posted_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `id` int(10) UNSIGNED NOT NULL,
  `job_posting_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `resume_path` varchar(255) DEFAULT NULL,
  `source` enum('walk_in','referral','jobstreet','linkedin','indeed','other') DEFAULT 'walk_in',
  `status` enum('new','screening','interview','exam','job_offer','hired','rejected','withdrawn') NOT NULL DEFAULT 'new',
  `interview_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `processed_by` int(10) UNSIGNED DEFAULT NULL,
  `hired_as_employee_id` int(10) UNSIGNED DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('present','absent','half_day','late','on_leave','holiday','rest_day') NOT NULL DEFAULT 'present',
  `leave_type` varchar(30) DEFAULT NULL COMMENT 'sick,vacation,maternity,etc. if status=on_leave',
  `remarks` varchar(255) DEFAULT NULL,
  `hours_worked` decimal(5,2) DEFAULT NULL,
  `is_overtime` tinyint(1) NOT NULL DEFAULT 0,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `employee_id`, `date`, `time_in`, `time_out`, `status`, `leave_type`, `remarks`, `hours_worked`, `is_overtime`, `overtime_hours`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-01-05', '08:00:00', '17:00:00', 'present', '', '', 9.00, 0, 0.00, 1, '2026-03-28 14:59:00', '2026-03-28 14:59:00'),
(2, 1, '2026-01-06', '08:00:00', '17:00:00', 'present', '', '', 9.00, 0, 0.00, 1, '2026-03-28 15:05:32', '2026-03-28 15:05:32'),
(3, 1, '2026-01-07', '08:00:00', '17:00:00', 'present', '', '', 9.00, 0, 0.00, 1, '2026-03-28 15:05:58', '2026-03-28 15:05:58'),
(4, 1, '2026-01-08', '08:00:00', '17:00:00', 'present', '', '', 9.00, 0, 0.00, 1, '2026-03-28 15:06:04', '2026-03-28 15:06:04'),
(5, 1, '2026-01-09', '08:00:00', '17:00:00', 'present', '', '', 9.00, 1, 0.00, 1, '2026-03-28 15:06:10', '2026-03-28 15:06:18'),
(7, 1, '2026-01-12', '08:00:00', '17:00:00', 'present', '', '', 9.00, 0, 0.00, 1, '2026-03-28 15:06:24', '2026-03-28 15:06:24'),
(8, 1, '2026-01-13', '08:00:00', '17:00:00', 'present', '', '', 9.00, 0, 0.00, 1, '2026-03-28 15:06:37', '2026-03-28 15:06:37'),
(9, 1, '2026-01-14', '08:00:00', '17:00:00', 'present', '', '', 9.00, 0, 0.00, 1, '2026-03-28 15:06:41', '2026-03-28 15:06:41'),
(10, 1, '2026-01-15', '08:00:00', '17:00:00', 'present', '', '', 9.00, 0, 0.00, 1, '2026-03-28 15:06:47', '2026-03-28 15:06:47'),
(13, 2, '2026-01-14', '08:00:00', '17:00:00', 'present', '', '', 9.00, 0, 0.00, 1, '2026-04-04 04:44:57', '2026-04-04 04:44:57');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `created_at`) VALUES
(1, 'Engineering', '2026-02-24 10:54:28'),
(2, 'Accounting & Finance', '2026-02-24 10:54:28'),
(3, 'Human Resources', '2026-02-24 10:54:28'),
(4, 'Marketing', '2026-02-24 10:54:28'),
(5, 'Operations', '2026-02-24 10:54:28');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_no` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `civil_status` enum('single','married','widowed','separated') DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `birthplace` varchar(200) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT 'Filipino',
  `address` text DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `sss_no` varchar(30) DEFAULT NULL,
  `philhealth_no` varchar(30) DEFAULT NULL,
  `pagibig_no` varchar(30) DEFAULT NULL,
  `tin_no` varchar(30) DEFAULT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `position_id` int(10) UNSIGNED NOT NULL,
  `basic_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `date_hired` date NOT NULL,
  `date_start` date DEFAULT NULL COMMENT 'Actual first day of work (may differ from date_hired). Used for payslip proration.',
  `employment_type` enum('regular','probationary','contractual','part_time') DEFAULT 'regular',
  `date_regularized` date DEFAULT NULL,
  `date_separated` date DEFAULT NULL,
  `separation_reason` text DEFAULT NULL,
  `status` enum('active','inactive','resigned','terminated') NOT NULL DEFAULT 'active',
  `sick_leave_balance` decimal(5,2) NOT NULL DEFAULT 10.00,
  `vacation_leave_balance` decimal(5,2) NOT NULL DEFAULT 10.00,
  `bereavement_leave_balance` decimal(5,2) NOT NULL DEFAULT 5.00,
  `emergency_leave_balance` decimal(5,2) NOT NULL DEFAULT 5.00,
  `sil_balance` decimal(5,2) NOT NULL DEFAULT 5.00,
  `maternity_leave_balance` decimal(6,2) NOT NULL DEFAULT 105.00,
  `paternity_leave_balance` decimal(5,2) NOT NULL DEFAULT 7.00,
  `solo_parent_leave_balance` decimal(5,2) NOT NULL DEFAULT 7.00,
  `vawc_leave_balance` decimal(5,2) NOT NULL DEFAULT 10.00,
  `magna_carta_leave_balance` decimal(6,2) NOT NULL DEFAULT 60.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relation` varchar(50) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `cutoff1_fixed_amount` decimal(12,2) DEFAULT NULL COMMENT 'If set, overrides 50/50 split. This amount is paid on 1st cutoff. NULL = basic_salary/2.',
  `tax_method` enum('half_monthly','bir_table') NOT NULL DEFAULT 'half_monthly' COMMENT 'half_monthly = monthly_tax/2 per cutoff | bir_table = fresh BIR compute on half salary',
  `gov_deduction_mode` enum('second_cutoff','split') NOT NULL DEFAULT 'second_cutoff' COMMENT 'second_cutoff = full gov deductions on 2nd cutoff only | split = half each cutoff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_no`, `name`, `gender`, `civil_status`, `birthdate`, `birthplace`, `nationality`, `address`, `email`, `phone`, `sss_no`, `philhealth_no`, `pagibig_no`, `tin_no`, `department_id`, `position_id`, `basic_salary`, `allowance`, `date_hired`, `date_start`, `employment_type`, `date_regularized`, `date_separated`, `separation_reason`, `status`, `sick_leave_balance`, `vacation_leave_balance`, `bereavement_leave_balance`, `emergency_leave_balance`, `sil_balance`, `maternity_leave_balance`, `paternity_leave_balance`, `solo_parent_leave_balance`, `vawc_leave_balance`, `magna_carta_leave_balance`, `created_at`, `updated_at`, `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_relation`, `profile_photo`, `cutoff1_fixed_amount`, `tax_method`, `gov_deduction_mode`) VALUES
(1, 'EMP-001', 'Chichi The Dog', 'female', 'single', '2000-11-11', NULL, 'Filipino', 'Dasma Cavite', 'chichi@rocky.com', '09123456789', '1234567890', '123456789012', '123456789012', '123456789012', 4, 12, 100000.00, 10000.00, '2026-01-02', '2026-01-05', 'probationary', NULL, NULL, NULL, 'active', 10.00, 10.00, 5.00, 5.00, 5.00, 105.00, 7.00, 7.00, 10.00, 60.00, '2026-03-28 14:36:55', '2026-04-04 04:28:53', 'Nami the Dog', '09123456789', 'Daughter', NULL, NULL, 'half_monthly', 'second_cutoff'),
(2, 'EMP-002', 'Chicken the Cat', 'male', 'single', '2000-12-02', NULL, 'Filipino', 'Dasma Cavite', 'chicken@rocky.com', '09123456710', '1234567001', '123456789001', '123456789001', '123456789001', 1, 2, 200000.00, 10000.00, '2026-01-14', '2026-01-19', 'probationary', NULL, NULL, NULL, 'active', 10.00, 10.00, 5.00, 5.00, 5.00, 105.00, 7.00, 7.00, 10.00, 60.00, '2026-03-28 16:54:12', '2026-04-04 04:44:17', 'Goku the Cat', '09203456710', 'Son', NULL, NULL, 'half_monthly', 'second_cutoff');

-- --------------------------------------------------------

--
-- Table structure for table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `document_type` enum('resume','nbi_clearance','police_clearance','birth_certificate','sss_id','philhealth_id','pagibig_id','tin_id','valid_id','diploma','transcript','certificate','contract','other') NOT NULL,
  `title` varchar(200) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `date` date NOT NULL,
  `type` enum('regular','special_non_working','special_working') NOT NULL DEFAULT 'regular',
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`id`, `name`, `date`, `type`, `is_recurring`, `created_at`) VALUES
(1, 'New Year\'s Day', '2026-01-01', 'regular', 1, '2026-03-09 04:23:15'),
(2, 'Chinese New Year', '2026-01-29', 'special_non_working', 1, '2026-03-09 04:23:15'),
(3, 'EDSA People Power Revolution', '2026-02-25', 'special_non_working', 1, '2026-03-09 04:23:15'),
(4, 'Araw ng Kagitingan', '2026-04-09', 'regular', 1, '2026-03-09 04:23:15'),
(5, 'Maundy Thursday', '2026-04-02', 'regular', 1, '2026-03-09 04:23:15'),
(6, 'Good Friday', '2026-04-03', 'regular', 1, '2026-03-09 04:23:15'),
(7, 'Black Saturday', '2026-04-04', 'special_non_working', 1, '2026-03-09 04:23:15'),
(8, 'Labor Day', '2026-05-01', 'regular', 1, '2026-03-09 04:23:15'),
(9, 'Independence Day', '2026-06-12', 'regular', 1, '2026-03-09 04:23:15'),
(10, 'Ninoy Aquino Day', '2026-08-21', 'special_non_working', 1, '2026-03-09 04:23:15'),
(11, 'National Heroes Day', '2026-08-31', 'regular', 1, '2026-03-09 04:23:15'),
(12, 'All Saints Day', '2026-11-01', 'special_non_working', 1, '2026-03-09 04:23:15'),
(13, 'All Souls Day', '2026-11-02', 'special_non_working', 1, '2026-03-09 04:23:15'),
(14, 'Bonifacio Day', '2026-11-30', 'regular', 1, '2026-03-09 04:23:15'),
(15, 'Immaculate Conception', '2026-12-08', 'special_non_working', 1, '2026-03-09 04:23:15'),
(16, 'Christmas Day', '2026-12-25', 'regular', 1, '2026-03-09 04:23:15'),
(17, 'Rizal Day', '2026-12-30', 'regular', 1, '2026-03-09 04:23:15'),
(18, 'Last Day of the Year', '2026-12-31', 'special_non_working', 1, '2026-03-09 04:23:15'),
(19, 'Eid l Fitr', '2026-03-21', 'regular', 1, '2026-03-09 04:23:15'),
(20, 'Eid l Adha', '2026-05-28', 'regular', 1, '2026-03-09 04:23:15');

-- --------------------------------------------------------

--
-- Table structure for table `job_postings`
--

CREATE TABLE `job_postings` (
  `id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `position_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `slots` int(5) NOT NULL DEFAULT 1,
  `salary_min` decimal(12,2) DEFAULT NULL,
  `salary_max` decimal(12,2) DEFAULT NULL,
  `employment_type` enum('regular','probationary','contractual','part_time') DEFAULT 'regular',
  `status` enum('open','closed','on_hold','filled') NOT NULL DEFAULT 'open',
  `posted_by` int(10) UNSIGNED DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `leave_type` enum('sick','vacation','bereavement','emergency','sil','maternity','paternity','solo_parent','vawc','magna_carta','unpaid') NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `days_applied` decimal(5,1) NOT NULL DEFAULT 1.0,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `filed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `was_successful` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `username`, `ip_address`, `attempted_at`, `was_successful`) VALUES
(1, 'admin1', '::1', '2026-03-27 19:08:18', 1),
(2, 'admin1', '::1', '2026-03-28 22:08:32', 1),
(3, 'admin1', '::1', '2026-03-29 00:16:31', 1),
(4, 'chicken.emp', '::1', '2026-03-29 00:54:36', 1),
(5, 'admin1', '::1', '2026-03-29 00:55:07', 1),
(6, 'chichi.emp', '::1', '2026-03-29 01:28:29', 0),
(7, 'admin1', '::1', '2026-03-29 01:28:35', 1),
(8, 'chichi.emp', '::1', '2026-03-29 01:29:18', 1),
(9, 'admin1', '::1', '2026-03-29 01:31:24', 1),
(10, 'chichi.emp', '::1', '2026-03-29 01:31:50', 1),
(11, 'admin1', '::1', '2026-03-29 01:39:40', 1),
(12, 'chichi.emp', '::1', '2026-03-29 01:49:22', 1),
(13, 'admin1', '::1', '2026-03-29 09:30:41', 1),
(14, 'chichi.emp', '::1', '2026-03-29 10:04:38', 1),
(15, 'admin1', '::1', '2026-03-29 10:44:46', 1),
(16, 'admin1', '::1', '2026-03-29 22:24:36', 1),
(17, 'chichi.emp', '::1', '2026-03-29 23:22:37', 1),
(18, 'admin1', '::1', '2026-03-30 11:31:08', 1),
(19, 'admin1', '::1', '2026-03-31 17:11:33', 1),
(20, 'admin1', '::1', '2026-03-31 23:24:04', 1),
(21, 'admin1', '::1', '2026-03-31 23:40:00', 1),
(22, 'admin1', '::1', '2026-04-01 09:22:13', 1),
(23, 'admin1', '::1', '2026-04-01 22:24:07', 1),
(24, 'admin1', '::1', '2026-04-02 11:52:16', 1),
(25, 'admin1', '::1', '2026-04-02 21:46:23', 1),
(26, 'admin1', '::1', '2026-04-03 09:53:17', 1),
(27, 'admin1', '::1', '2026-04-03 15:13:21', 1),
(28, 'admin1', '::1', '2026-04-04 12:26:25', 1);

-- --------------------------------------------------------

--
-- Table structure for table `payroll_notes`
--

CREATE TABLE `payroll_notes` (
  `id` int(10) UNSIGNED NOT NULL,
  `payroll_id` int(10) UNSIGNED NOT NULL,
  `note` varchar(100) NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_records`
--

CREATE TABLE `payroll_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `period` varchar(9) NOT NULL COMMENT 'YYYY-MM for legacy, YYYY-MM-1 or YYYY-MM-2 for semi-monthly',
  `basic_salary` decimal(12,2) NOT NULL,
  `allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gross_pay` decimal(12,2) NOT NULL,
  `sss_msc` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sss_ee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sss_er` decimal(10,2) NOT NULL DEFAULT 0.00,
  `philhealth_mbs` decimal(10,2) NOT NULL DEFAULT 0.00,
  `philhealth_ee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `philhealth_er` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pagibig_mfs` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pagibig_ee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pagibig_er` decimal(10,2) NOT NULL DEFAULT 0.00,
  `taxable_income` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withholding_tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `other_deductions` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_deductions` decimal(10,2) NOT NULL,
  `net_pay` decimal(12,2) NOT NULL,
  `status` enum('pending','released','cancelled') NOT NULL DEFAULT 'pending',
  `processed_by` int(10) UNSIGNED DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `days_worked` decimal(5,2) DEFAULT NULL COMMENT 'Total days worked (from attendance sheet)',
  `days_absent` decimal(5,2) DEFAULT NULL COMMENT 'Total absent days (A, UA, LWOP)',
  `days_paid_leave` decimal(5,2) DEFAULT NULL COMMENT 'Total paid leave days (SL, VL, ML, etc.)',
  `absent_deduction` decimal(10,2) DEFAULT 0.00 COMMENT 'Salary deduction for absent days',
  `salary_deduction` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Sum of manual salary deductions from salary_deductions table',
  `unpaid_leave_deduction` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Deduction for unpaid/LWOP leave days in the cutoff',
  `working_days_in_month` tinyint(4) DEFAULT 22 COMMENT 'Standard working days used for daily rate'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payroll_records`
--

INSERT INTO `payroll_records` (`id`, `employee_id`, `period`, `basic_salary`, `allowance`, `gross_pay`, `sss_msc`, `sss_ee`, `sss_er`, `philhealth_mbs`, `philhealth_ee`, `philhealth_er`, `pagibig_mfs`, `pagibig_ee`, `pagibig_er`, `taxable_income`, `withholding_tax`, `other_deductions`, `total_deductions`, `net_pay`, `status`, `processed_by`, `released_at`, `remarks`, `created_at`, `updated_at`, `days_worked`, `days_absent`, `days_paid_leave`, `absent_deduction`, `salary_deduction`, `unpaid_leave_deduction`, `working_days_in_month`) VALUES
(15, 1, '2026-01-1', 50000.00, 5000.00, 55000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 50000.00, 8437.50, 0.00, 8437.50, 46562.50, 'pending', 1, NULL, '', '2026-04-04 04:29:19', '2026-04-04 04:29:19', 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 9);

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `department_id`, `name`, `created_at`) VALUES
(1, 1, 'Software Engineer', '2026-02-24 10:54:28'),
(2, 1, 'Senior Software Engineer', '2026-02-24 10:54:28'),
(3, 1, 'Junior Developer', '2026-02-24 10:54:28'),
(4, 1, 'IT Support', '2026-02-24 10:54:28'),
(5, 2, 'Accountant', '2026-02-24 10:54:28'),
(6, 2, 'Finance Officer', '2026-02-24 10:54:28'),
(7, 2, 'Bookkeeper', '2026-02-24 10:54:28'),
(8, 3, 'HR Officer', '2026-02-24 10:54:28'),
(9, 3, 'HR Manager', '2026-02-24 10:54:28'),
(10, 3, 'Recruitment Specialist', '2026-02-24 10:54:28'),
(11, 4, 'Marketing Officer', '2026-02-24 10:54:28'),
(12, 4, 'Marketing Lead', '2026-02-24 10:54:28'),
(13, 4, 'Social Media Manager', '2026-02-24 10:54:28'),
(14, 5, 'Operations Staff', '2026-02-24 10:54:28'),
(15, 5, 'Operations Manager', '2026-02-24 10:54:28');

-- --------------------------------------------------------

--
-- Table structure for table `reimbursements`
--

CREATE TABLE `reimbursements` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(60) NOT NULL COMMENT 'transportation,meal,medical,communication,office_supplies,other',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `receipt_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `receipt_no` varchar(60) DEFAULT NULL,
  `status` enum('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_deductions`
--

CREATE TABLE `salary_deductions` (
  `id` int(10) UNSIGNED NOT NULL,
  `payroll_id` int(10) UNSIGNED NOT NULL,
  `reason` varchar(100) NOT NULL COMMENT 'destroyed_asset,cash_advance,loan,overpayment,other',
  `description` varchar(255) DEFAULT NULL COMMENT 'Dropdown-selected asset or free-text for other',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_history`
--

CREATE TABLE `salary_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `old_basic_salary` decimal(12,2) NOT NULL,
  `new_basic_salary` decimal(12,2) NOT NULL,
  `old_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `new_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(255) DEFAULT NULL,
  `effective_date` date NOT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `thirteenth_month_pay`
--

CREATE TABLE `thirteenth_month_pay` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `year` year(4) NOT NULL,
  `total_basic_earned` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Sum of basic_salary from payroll_records for this year',
  `months_worked` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Number of payroll periods processed for employee in this year',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'total_basic_earned / 12 — PD 851 formula',
  `status` enum('pending','released') NOT NULL DEFAULT 'pending',
  `processed_by` int(10) UNSIGNED DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='13th Month Pay records per employee per year (PD 851)';

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `username` varchar(80) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','management','employee') NOT NULL DEFAULT 'admin',
  `employee_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `password`, `role`, `employee_id`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Rocky Astor', 'admin1', 'admin1@rocky.com', '$2y$10$u8dAA/RaxigZoaqeXehBYucwFP/1zlv5qlsDrpJvxW47BgMmjFGoy', 'admin', NULL, 'active', NULL, '2026-02-24 10:54:28', '2026-03-19 06:55:52'),
(2, 'Jalen Andrino', 'admin2', 'admin2@rocky.com', '$2y$10$u8dAA/RaxigZoaqeXehBYucwFP/1zlv5qlsDrpJvxW47BgMmjFGoy', 'admin', NULL, 'active', NULL, '2026-02-24 10:54:28', '2026-03-19 06:55:52'),
(3, 'Mochi Manager', 'management1', 'management1@rocky.com', '$2y$10$u8dAA/RaxigZoaqeXehBYucwFP/1zlv5qlsDrpJvxW47BgMmjFGoy', 'management', NULL, 'active', NULL, '2026-02-24 10:54:28', '2026-03-19 06:55:52'),
(4, 'Chichi The Dog', 'admin3', 'admin3@rocky.com', '$2y$10$u8dAA/RaxigZoaqeXehBYucwFP/1zlv5qlsDrpJvxW47BgMmjFGoy', 'admin', NULL, 'active', 1, '2026-03-04 01:14:34', '2026-03-19 06:55:52'),
(5, 'Chichi The Dog', 'chichi.emp', 'chichi@rocky.com', '$2y$10$OnvEPm50E7IApL5P.Nf2x.E1LJ2ka1Il6OKoVl1yKV3kcoHqTgU02', 'employee', 1, 'active', 1, '2026-03-28 14:36:55', '2026-03-28 17:28:58'),
(6, 'Chicken the Cat', 'chicken.emp', 'chicken@rocky.com', '$2y$10$kHCdYhq89Mxtq06rNS87huzdp1PZtZgK4EORIQRnuSVufHBoM/bGm', 'employee', 2, 'active', 1, '2026-03-28 16:54:12', '2026-03-28 16:54:12');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_employees`
-- (See below for the actual view)
--
CREATE TABLE `v_employees` (
`id` int(10) unsigned
,`employee_no` varchar(20)
,`name` varchar(150)
,`email` varchar(150)
,`phone` varchar(20)
,`gender` enum('male','female','other')
,`civil_status` enum('single','married','widowed','separated')
,`birthdate` date
,`address` text
,`date_hired` date
,`employment_type` enum('regular','probationary','contractual','part_time')
,`status` enum('active','inactive','resigned','terminated')
,`basic_salary` decimal(12,2)
,`allowance` decimal(12,2)
,`sick_leave_balance` decimal(5,2)
,`vacation_leave_balance` decimal(5,2)
,`bereavement_leave_balance` decimal(5,2)
,`emergency_leave_balance` decimal(5,2)
,`sil_balance` decimal(5,2)
,`maternity_leave_balance` decimal(6,2)
,`paternity_leave_balance` decimal(5,2)
,`solo_parent_leave_balance` decimal(5,2)
,`vawc_leave_balance` decimal(5,2)
,`magna_carta_leave_balance` decimal(6,2)
,`department_id` int(10) unsigned
,`department` varchar(100)
,`position_id` int(10) unsigned
,`position` varchar(100)
,`profile_photo` varchar(255)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_payroll`
-- (See below for the actual view)
--
CREATE TABLE `v_payroll` (
`id` int(10) unsigned
,`employee_id` int(10) unsigned
,`period` varchar(9)
,`basic_salary` decimal(12,2)
,`allowance` decimal(12,2)
,`gross_pay` decimal(12,2)
,`sss_msc` decimal(10,2)
,`sss_ee` decimal(10,2)
,`sss_er` decimal(10,2)
,`philhealth_mbs` decimal(10,2)
,`philhealth_ee` decimal(10,2)
,`philhealth_er` decimal(10,2)
,`pagibig_mfs` decimal(10,2)
,`pagibig_ee` decimal(10,2)
,`pagibig_er` decimal(10,2)
,`taxable_income` decimal(12,2)
,`withholding_tax` decimal(10,2)
,`other_deductions` decimal(10,2)
,`absent_deduction` decimal(10,2)
,`total_deductions` decimal(10,2)
,`net_pay` decimal(12,2)
,`status` enum('pending','released','cancelled')
,`processed_by` int(10) unsigned
,`released_at` timestamp
,`remarks` text
,`days_worked` decimal(5,2)
,`days_absent` decimal(5,2)
,`days_paid_leave` decimal(5,2)
,`working_days_in_month` tinyint(4)
,`created_at` timestamp
,`updated_at` timestamp
,`employee_no` varchar(20)
,`employee_name` varchar(150)
,`department` varchar(100)
,`processed_by_name` varchar(150)
);

-- --------------------------------------------------------

--
-- Structure for view `v_employees`
--
DROP TABLE IF EXISTS `v_employees`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_employees`  AS SELECT `e`.`id` AS `id`, `e`.`employee_no` AS `employee_no`, `e`.`name` AS `name`, `e`.`email` AS `email`, `e`.`phone` AS `phone`, `e`.`gender` AS `gender`, `e`.`civil_status` AS `civil_status`, `e`.`birthdate` AS `birthdate`, `e`.`address` AS `address`, `e`.`date_hired` AS `date_hired`, `e`.`employment_type` AS `employment_type`, `e`.`status` AS `status`, `e`.`basic_salary` AS `basic_salary`, `e`.`allowance` AS `allowance`, `e`.`sick_leave_balance` AS `sick_leave_balance`, `e`.`vacation_leave_balance` AS `vacation_leave_balance`, `e`.`bereavement_leave_balance` AS `bereavement_leave_balance`, `e`.`emergency_leave_balance` AS `emergency_leave_balance`, `e`.`sil_balance` AS `sil_balance`, `e`.`maternity_leave_balance` AS `maternity_leave_balance`, `e`.`paternity_leave_balance` AS `paternity_leave_balance`, `e`.`solo_parent_leave_balance` AS `solo_parent_leave_balance`, `e`.`vawc_leave_balance` AS `vawc_leave_balance`, `e`.`magna_carta_leave_balance` AS `magna_carta_leave_balance`, `e`.`department_id` AS `department_id`, `d`.`name` AS `department`, `e`.`position_id` AS `position_id`, `p`.`name` AS `position`, `e`.`profile_photo` AS `profile_photo` FROM ((`employees` `e` join `departments` `d` on(`d`.`id` = `e`.`department_id`)) join `positions` `p` on(`p`.`id` = `e`.`position_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_payroll`
--
DROP TABLE IF EXISTS `v_payroll`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_payroll`  AS SELECT `pr`.`id` AS `id`, `pr`.`employee_id` AS `employee_id`, `pr`.`period` AS `period`, `pr`.`basic_salary` AS `basic_salary`, `pr`.`allowance` AS `allowance`, `pr`.`gross_pay` AS `gross_pay`, `pr`.`sss_msc` AS `sss_msc`, `pr`.`sss_ee` AS `sss_ee`, `pr`.`sss_er` AS `sss_er`, `pr`.`philhealth_mbs` AS `philhealth_mbs`, `pr`.`philhealth_ee` AS `philhealth_ee`, `pr`.`philhealth_er` AS `philhealth_er`, `pr`.`pagibig_mfs` AS `pagibig_mfs`, `pr`.`pagibig_ee` AS `pagibig_ee`, `pr`.`pagibig_er` AS `pagibig_er`, `pr`.`taxable_income` AS `taxable_income`, `pr`.`withholding_tax` AS `withholding_tax`, `pr`.`other_deductions` AS `other_deductions`, `pr`.`absent_deduction` AS `absent_deduction`, `pr`.`total_deductions` AS `total_deductions`, `pr`.`net_pay` AS `net_pay`, `pr`.`status` AS `status`, `pr`.`processed_by` AS `processed_by`, `pr`.`released_at` AS `released_at`, `pr`.`remarks` AS `remarks`, `pr`.`days_worked` AS `days_worked`, `pr`.`days_absent` AS `days_absent`, `pr`.`days_paid_leave` AS `days_paid_leave`, `pr`.`working_days_in_month` AS `working_days_in_month`, `pr`.`created_at` AS `created_at`, `pr`.`updated_at` AS `updated_at`, `e`.`employee_no` AS `employee_no`, `e`.`name` AS `employee_name`, `d`.`name` AS `department`, `u`.`name` AS `processed_by_name` FROM (((`payroll_records` `pr` join `employees` `e` on(`e`.`id` = `pr`.`employee_id`)) join `departments` `d` on(`d`.`id` = `e`.`department_id`)) left join `users` `u` on(`u`.`id` = `pr`.`processed_by`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `announcements_ibfk_1` (`posted_by`);

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_posting_id` (`job_posting_id`),
  ADD KEY `applicants_ibfk_2` (`processed_by`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_emp_date` (`employee_id`,`date`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `attendance_ibfk_2` (`created_by`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_no` (`employee_no`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `position_id` (`position_id`);

--
-- Indexes for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `employee_documents_ibfk_2` (`uploaded_by`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_holiday_date` (`date`);

--
-- Indexes for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_department_id` (`department_id`),
  ADD KEY `job_postings_ibfk_2` (`posted_by`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date_from` (`date_from`),
  ADD KEY `leave_requests_ibfk_2` (`reviewed_by`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username_time` (`username`,`attempted_at`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempted_at`);

--
-- Indexes for table `payroll_notes`
--
ALTER TABLE `payroll_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payroll_id` (`payroll_id`),
  ADD KEY `payroll_notes_ibfk_2` (`created_by`);

--
-- Indexes for table `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_emp_period` (`employee_id`,`period`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `reimbursements`
--
ALTER TABLE `reimbursements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `salary_deductions`
--
ALTER TABLE `salary_deductions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payroll_id` (`payroll_id`);

--
-- Indexes for table `salary_history`
--
ALTER TABLE `salary_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `salary_history_ibfk_2` (`approved_by`);

--
-- Indexes for table `thirteenth_month_pay`
--
ALTER TABLE `thirteenth_month_pay`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_employee_year` (`employee_id`,`year`),
  ADD KEY `idx_year` (`year`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_processed_by` (`processed_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_employee` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `employee_documents`
--
ALTER TABLE `employee_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `job_postings`
--
ALTER TABLE `job_postings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `payroll_notes`
--
ALTER TABLE `payroll_notes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payroll_records`
--
ALTER TABLE `payroll_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `reimbursements`
--
ALTER TABLE `reimbursements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_deductions`
--
ALTER TABLE `salary_deductions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_history`
--
ALTER TABLE `salary_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `thirteenth_month_pay`
--
ALTER TABLE `thirteenth_month_pay`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `applicants`
--
ALTER TABLE `applicants`
  ADD CONSTRAINT `applicants_ibfk_1` FOREIGN KEY (`job_posting_id`) REFERENCES `job_postings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applicants_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`);

--
-- Constraints for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD CONSTRAINT `employee_documents_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD CONSTRAINT `job_postings_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `job_postings_ibfk_2` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payroll_notes`
--
ALTER TABLE `payroll_notes`
  ADD CONSTRAINT `payroll_notes_ibfk_1` FOREIGN KEY (`payroll_id`) REFERENCES `payroll_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_notes_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD CONSTRAINT `payroll_records_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `payroll_records_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `salary_history`
--
ALTER TABLE `salary_history`
  ADD CONSTRAINT `salary_history_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salary_history_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `thirteenth_month_pay`
--
ALTER TABLE `thirteenth_month_pay`
  ADD CONSTRAINT `thirteenth_month_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `thirteenth_month_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
