<?php
// core/Model.php
// ═══════════════════════════════════════════════════════════════════════════════
//  Rocky HRIS + Payroll System — Model Facade v3.0
//
//  This file is now a BACKWARD-COMPATIBILITY FACADE.
//  All business logic has been moved to focused domain models in core/models/:
//
//    UserModel          — users table
//    DepartmentModel    — departments + positions
//    EmployeeModel      — employees, documents, salary history
//    AttendanceModel    — attendance
//    LeaveModel         — leave_requests
//    RecruitmentModel   — job_postings + applicants
//    PayrollModel       — payroll records, period helpers, 13th month, settings
//    AnnouncementModel  — announcements
//    HolidayModel       — holidays
//    ActivityLogModel   — activity_logs
//    DashboardModel     — cross-table aggregates for dashboard widgets
//
//  HOW TO MIGRATE:
//    Existing code calling Model::someMethod() continues to work unchanged.
//    New code should call the domain model directly, e.g. PayrollModel::create().
//    Over time, update callers file by file and remove delegations from here.
//
// ═══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/models/BaseModel.php';
require_once __DIR__ . '/models/UserModel.php';
require_once __DIR__ . '/models/DepartmentModel.php';
require_once __DIR__ . '/models/EmployeeModel.php';
require_once __DIR__ . '/models/AttendanceModel.php';
require_once __DIR__ . '/models/LeaveModel.php';
require_once __DIR__ . '/models/RecruitmentModel.php';
require_once __DIR__ . '/models/PayrollModel.php';
require_once __DIR__ . '/models/AnnouncementModel.php';
require_once __DIR__ . '/models/HolidayModel.php';
require_once __DIR__ . '/models/ActivityLogModel.php';
require_once __DIR__ . '/models/DashboardModel.php';
require_once __DIR__ . '/models/ReimbursementModel.php';

class Model
{
    // ════════════════════════════════════════════════════════
    //  USERS  →  UserModel
    // ════════════════════════════════════════════════════════

    public static function getAllUsers(): array                           { return UserModel::getAll(); }
    public static function findUserByUsername(string $u): ?array         { return UserModel::findByUsername($u); }
    public static function findUserById(int $id): ?array                 { return UserModel::findById($id); }
    public static function findUserByEmployeeId(int $id): ?array         { return UserModel::findByEmployeeId($id); }
    public static function findUserByEmail(string $email): ?array        { return UserModel::findByEmail($email); }
    public static function createUser(array $data): bool                 { return UserModel::create($data); }
    public static function updateUser(int $id, array $data): bool        { return UserModel::update($id, $data); }
    public static function updateUserPassword(int $id, string $pw): bool { return UserModel::updatePassword($id, $pw); }
    public static function updateUserStatus(int $id, string $s): bool    { return UserModel::updateStatus($id, $s); }
    public static function generateEmployeeUsername(string $n): string   { return UserModel::generateEmployeeUsername($n); }
    public static function createResetToken(int $uid, string $tok, int $mins = 30): bool { return UserModel::createResetToken($uid, $tok, $mins); }
    public static function consumeResetToken(string $tok): ?array        { return UserModel::consumeResetToken($tok); }
    public static function isResetTokenValid(string $tok): bool          { return UserModel::isResetTokenValid($tok); }

    // ════════════════════════════════════════════════════════
    //  DEPARTMENTS & POSITIONS  →  DepartmentModel
    // ════════════════════════════════════════════════════════

    public static function getAllDepartments(): array                         { return DepartmentModel::getAll(); }
    public static function findDepartmentById(int $id): ?array               { return DepartmentModel::findById($id); }
    public static function createDepartment(string $name): bool              { return DepartmentModel::create($name); }
    public static function updateDepartment(int $id, string $name): bool     { return DepartmentModel::update($id, $name); }
    public static function deleteDepartment(int $id): bool                   { return DepartmentModel::delete($id); }
    public static function countEmployeesInDepartment(int $id): int          { return DepartmentModel::countActiveEmployees($id); }
    public static function getAllPositions(): array                           { return DepartmentModel::getAllPositions(); }
    public static function getPositionsByDepartment(int $deptId): array      { return DepartmentModel::getPositionsByDepartment($deptId); }
    public static function createPosition(int $deptId, string $name): bool   { return DepartmentModel::createPosition($deptId, $name); }
    public static function deletePosition(int $id): bool                     { return DepartmentModel::deletePosition($id); }
    public static function countEmployeesInPosition(int $posId): int         { return DepartmentModel::countActiveEmployeesInPosition($posId); }

    // ════════════════════════════════════════════════════════
    //  EMPLOYEES  →  EmployeeModel
    // ════════════════════════════════════════════════════════

    public static function getAllEmployees(string $status = ''): array       { return EmployeeModel::getAll($status); }
    public static function findEmployeeById(int $id): ?array                 { return EmployeeModel::findById($id); }
    public static function generateNextEmployeeNo(): string                  { return EmployeeModel::generateNextEmployeeNo(); }
    public static function countActiveEmployees(): int                       { return EmployeeModel::countActive(); }
    public static function countEmployeesByStatus(): array                   { return EmployeeModel::countByStatus(); }
    public static function createEmployee(array $data): bool                 { return EmployeeModel::create($data); }
    public static function updateEmployee(int $id, array $data): bool        { return EmployeeModel::update($id, $data); }
    public static function updateEmployeeProfile(int $id, array $data): bool { return EmployeeModel::updateProfile($id, $data); }
    public static function toggleEmployeeStatus(int $id, string $s): bool    { return EmployeeModel::toggleStatus($id, $s); }
    public static function searchEmployees(string $q): array                 { return EmployeeModel::search($q); }
    public static function getEmployeesByDepartment(int $deptId): array      { return EmployeeModel::getByDepartment($deptId); }
    public static function getDocumentsByEmployee(int $empId): array         { return EmployeeModel::getDocuments($empId); }
    public static function createDocument(array $data): bool                 { return EmployeeModel::createDocument($data); }
    public static function deleteDocument(int $id): bool                     { return EmployeeModel::deleteDocument($id); }
    public static function findDocumentById(int $id): ?array                 { return EmployeeModel::findDocumentById($id); }
    public static function getSalaryHistory(int $empId): array               { return EmployeeModel::getSalaryHistory($empId); }
    public static function createSalaryHistory(array $data): bool            { return EmployeeModel::createSalaryHistory($data); }

    // ════════════════════════════════════════════════════════
    //  ATTENDANCE  →  AttendanceModel
    // ════════════════════════════════════════════════════════

    public static function getAttendanceByMonth(string $ym): array                     { return AttendanceModel::getByMonth($ym); }
    public static function getAttendanceByEmployee(int $id, string $ym = ''): array    { return AttendanceModel::getByEmployee($id, $ym); }
    public static function getAttendanceSummary(int $id, string $ym): array            { return AttendanceModel::getSummary($id, $ym); }
    public static function getCutoffAttendanceSummary(int $id, string $from, string $to, string $dateStart = ''): array { return AttendanceModel::getCutoffSummary($id, $from, $to, $dateStart); }
    public static function getPayrollYTD(int $id, string $period): array               { return PayrollModel::getYTDByEmployee($id, $period); }
    public static function saveAttendance(array $data): bool                           { return AttendanceModel::save($data); }

    // ════════════════════════════════════════════════════════
    //  LEAVE  →  LeaveModel
    // ════════════════════════════════════════════════════════

    public static function getAllLeaveRequests(string $status = ''): array                            { return LeaveModel::getAll($status); }
    public static function getLeaveRequestsByEmployee(int $id): array                                 { return LeaveModel::getByEmployee($id); }
    public static function findLeaveRequestById(int $id): ?array                                      { return LeaveModel::findById($id); }
    public static function createLeaveRequest(array $data): bool                                      { return LeaveModel::create($data); }
    public static function reviewLeaveRequest(int $id, string $s, int $by, string $n = ''): bool      { return LeaveModel::review($id, $s, $by, $n); }
    public static function countPendingLeaves(): int                                                  { return LeaveModel::countPending(); }
    public static function getApprovedLeavesForDate(string $date): array                              { return LeaveModel::getApprovedForDate($date); }

    // ════════════════════════════════════════════════════════
    //  RECRUITMENT  →  RecruitmentModel
    // ════════════════════════════════════════════════════════

    public static function getAllJobPostings(string $status = ''): array                                      { return RecruitmentModel::getAllPostings($status); }
    public static function findJobPostingById(int $id): ?array                                                { return RecruitmentModel::findPostingById($id); }
    public static function createJobPosting(array $data): bool                                                { return RecruitmentModel::createPosting($data); }
    public static function updateJobPostingStatus(int $id, string $s): bool                                   { return RecruitmentModel::updatePostingStatus($id, $s); }
    public static function deleteJobPosting(int $id): bool                                                    { return RecruitmentModel::deletePosting($id); }
    public static function countOpenJobPostings(): int                                                        { return RecruitmentModel::countOpenPostings(); }
    public static function getApplicantsByJob(int $jobId): array                                              { return RecruitmentModel::getApplicantsByJob($jobId); }
    public static function findApplicantById(int $id): ?array                                                 { return RecruitmentModel::findApplicantById($id); }
    public static function createApplicant(array $data): bool                                                 { return RecruitmentModel::createApplicant($data); }
    public static function updateApplicantStatus(int $id, string $s, int $by, string $n = '', ?string $dt = null): bool { return RecruitmentModel::updateApplicantStatus($id, $s, $by, $n, $dt); }
    public static function countApplicantsForJob(int $jobId): int                                             { return RecruitmentModel::countApplicantsForJob($jobId); }
    public static function countNewApplicants(): int                                                          { return RecruitmentModel::countNewApplicants(); }

    // ════════════════════════════════════════════════════════
    //  PAYROLL  →  PayrollModel
    // ════════════════════════════════════════════════════════

    // Period helpers
    public static function periodLabel(string $p): string        { return PayrollModel::periodLabel($p); }
    public static function periodBase(string $p): string         { return PayrollModel::periodBase($p); }
    public static function periodYear(string $p): int            { return PayrollModel::periodYear($p); }
    public static function periodCutoff(string $p): int          { return PayrollModel::periodCutoff($p); }
    public static function isDecember1stCutoff(string $p): bool  { return PayrollModel::isDecember1stCutoff($p); }
    public static function isDecember2ndCutoff(string $p): bool  { return PayrollModel::isDecember2ndCutoff($p); }
    public static function periodsForMonth(string $ym): array    { return PayrollModel::periodsForMonth($ym); }

    // Payroll records
    public static function getAllPayroll(): array                                   { return PayrollModel::getAll(); }
    public static function getPayrollByPeriod(string $p): array                    { return PayrollModel::getByPeriod($p); }
    public static function getPayrollByEmployee(int $id): array                    { return PayrollModel::getByEmployee($id); }
    public static function getPayrollRecordsByEmployee(int $id): array             { return PayrollModel::getRecentByEmployee($id); }
    public static function findPayrollById(int $id): ?array                        { return PayrollModel::findById($id); }
    public static function periodExists(string $p): bool                           { return PayrollModel::periodExists($p); }
    public static function employeeExistsInPeriod(int $id, string $p): bool        { return PayrollModel::employeeExistsInPeriod($id, $p); }
    public static function getTotalNetPayForPeriod(string $p): float               { return PayrollModel::getTotalNetPayForPeriod($p); }
    public static function getPayrollPeriods(): array                              { return PayrollModel::getPeriods(); }
    public static function getOldestPayrollPeriod(): ?string                       { return PayrollModel::getOldestPeriod(); }
    public static function getPayrollPeriodsForEmployee(int $id): array           { return PayrollModel::getPeriodsForEmployee($id); }
    public static function getEmployeesWithMissingAttendance(array $ids, string $p): array { return PayrollModel::getEmployeesWithMissingAttendance($ids, $p); }
    public static function deletePayrollRecord(int $id): bool                     { return PayrollModel::deleteRecord($id); }
    public static function hasPayrollBeforeDecember(int $empId, int $year): bool  { return PayrollModel::hasPayrollBeforeDecember($empId, $year); }
    public static function createPayrollRecord(array $d): bool                     { return PayrollModel::create($d); }
    public static function releasePayroll(int $id): bool                           { return PayrollModel::release($id); }
    public static function updatePayrollStatus(int $id, string $status): bool      { return PayrollModel::updateStatus($id, $status); }
    public static function addPayrollNote(int $payrollId, string $note, int $uid): bool { return PayrollModel::addNote($payrollId, $note, $uid); }
    public static function getPayrollNotes(int $payrollId): array                  { return PayrollModel::getNotes($payrollId); }
    public static function releaseAllPayrollForPeriod(string $p): bool             { return PayrollModel::releaseAllForPeriod($p); }
    public static function computePayroll(array $employee): array                  { return PayrollModel::computeForEmployee($employee); }

    // Employee payroll settings
    public static function getEmployeePayrollSettings(int $id): array             { return PayrollModel::getSettings($id); }
    public static function updateEmployeePayrollSettings(int $id, array $s): bool { return PayrollModel::updateSettings($id, $s); }

    public static function addSalaryDeduction(int $payrollId, array $d, int $uid): bool { return PayrollModel::addSalaryDeduction($payrollId, $d, $uid); }
    public static function getSalaryDeductions(int $payrollId): array                    { return PayrollModel::getSalaryDeductions($payrollId); }
    public static function deleteSalaryDeduction(int $dedId): bool                       { return PayrollModel::deleteSalaryDeduction($dedId); }
    public static function updateSalaryDeduction(int $dedId, array $d): bool            { return PayrollModel::updateSalaryDeduction($dedId, $d); }
    public static function deletePayrollNote(int $noteId): bool                          { return PayrollModel::deleteNote($noteId); }

    // Year-to-date aggregates (used in year-end reconciliation & payslip YTD block)
    public static function getTotalBasicByYear(int $id, int $y): float             { return PayrollModel::getTotalBasicByYear($id, $y); }
    public static function getTotalGovDedsByYear(int $id, int $y): float           { return PayrollModel::getTotalGovDedsByYear($id, $y); }
    public static function getTotalWithholdingTaxByYear(int $id, int $y): float    { return PayrollModel::getTotalWithholdingTaxByYear($id, $y); }

    // ════════════════════════════════════════════════════════
    //  REIMBURSEMENTS  →  ReimbursementModel
    // ════════════════════════════════════════════════════════

    public static function getAllReimbursements(string $status = ''): array             { return ReimbursementModel::getAll($status); }
    public static function getReimbursementsByEmployee(int $empId): array              { return ReimbursementModel::getByEmployee($empId); }
    public static function findReimbursementById(int $id): ?array                      { return ReimbursementModel::findById($id); }
    public static function createReimbursement(array $d): bool                         { return ReimbursementModel::create($d); }
    public static function reviewReimbursement(int $id, string $status, int $by, string $notes = ''): bool { return ReimbursementModel::review($id, $status, $by, $notes); }
    public static function countPendingReimbursements(): int                            { return ReimbursementModel::countPending(); }

    // 13th month pay
    public static function compute13thMonth(int $year): array                              { return PayrollModel::compute13thMonth($year); }
    public static function get13thMonthByYear(int $year): array                            { return PayrollModel::get13thMonthByYear($year); }
    public static function get13thMonthByEmployee(int $empId, int $year): ?array           { return PayrollModel::get13thMonthByEmployee($empId, $year); }
    public static function thirteenthMonthExists(int $year): bool                          { return PayrollModel::thirteenthMonthExists($year); }
    public static function thirteenthMonthExistsForEmployee(int $empId, int $year): bool   { return PayrollModel::thirteenthMonthExistsForEmployee($empId, $year); }
    public static function save13thMonthRecord(array $d): bool                             { return PayrollModel::save13thMonth($d); }
    public static function release13thMonth(int $id): bool                                 { return PayrollModel::release13thMonth($id); }
    public static function releaseAll13thMonth(int $year): bool                            { return PayrollModel::releaseAll13thMonth($year); }

    // ════════════════════════════════════════════════════════
    //  ANNOUNCEMENTS  →  AnnouncementModel
    // ════════════════════════════════════════════════════════

    public static function getActiveAnnouncements(): array                   { return AnnouncementModel::getActive(); }
    public static function findAnnouncementById(int $id): ?array             { return AnnouncementModel::findById($id); }
    public static function createAnnouncement(array $data): bool             { return AnnouncementModel::create($data); }
    public static function updateAnnouncement(int $id, array $data): bool    { return AnnouncementModel::update($id, $data); }
    public static function deleteAnnouncement(int $id): bool                 { return AnnouncementModel::delete($id); }

    // ════════════════════════════════════════════════════════
    //  HOLIDAYS  →  HolidayModel
    // ════════════════════════════════════════════════════════

    public static function getHolidaysByYear(int $year): array                  { return HolidayModel::getByYear($year); }
    public static function isHoliday(string $date): ?array                       { return HolidayModel::isHoliday($date); }
    public static function getHolidaysInRange(string $from, string $to): array   { return HolidayModel::getInRange($from, $to); }
    public static function createHoliday(array $d): bool                         { return HolidayModel::create($d); }
    public static function updateHoliday(int $id, array $d): bool                { return HolidayModel::update($id, $d); }
    public static function deleteHoliday(int $id): bool                          { return HolidayModel::delete($id); }

    // ════════════════════════════════════════════════════════
    //  ACTIVITY LOG  →  ActivityLogModel
    // ════════════════════════════════════════════════════════

    public static function log(?int $userId, string $action, string $desc = ''): void { ActivityLogModel::log($userId, $action, $desc); }
    public static function getActivityLogs(int $limit = 100): array                   { return ActivityLogModel::getRecent($limit); }
    public static function getActivityLogsPaginated(int $limit, int $offset): array   { return ActivityLogModel::getPaginated($limit, $offset); }
    public static function countActivityLogs(): int                                   { return ActivityLogModel::count(); }

    // ════════════════════════════════════════════════════════
    //  DASHBOARD  →  DashboardModel
    // ════════════════════════════════════════════════════════

    public static function getDashboardStats(): array        { return DashboardModel::getStats(); }
    public static function getHeadcountByDepartment(): array { return DashboardModel::getHeadcountByDepartment(); }
}