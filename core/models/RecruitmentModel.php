<?php
// core/models/RecruitmentModel.php
// ─────────────────────────────────────────────────────────────────────────────
//  Handles job_postings and applicants table operations.
//  Extracted from Model.php (God Class split).
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/BaseModel.php';

class RecruitmentModel extends BaseModel
{
    // ── Job Postings ─────────────────────────────────────────────────────────

    public static function getAllPostings(string $status = ''): array
    {
        if ($status) {
            $stmt = self::db()->prepare('
                SELECT jp.*, d.name AS department_name, u.name AS posted_by_name,
                       (SELECT COUNT(*) FROM applicants WHERE job_posting_id = jp.id) AS applicant_count
                FROM job_postings jp
                JOIN departments d ON d.id = jp.department_id
                LEFT JOIN users u ON u.id = jp.posted_by
                WHERE jp.status = ? ORDER BY jp.created_at DESC
            ');
            $stmt->execute([$status]);
        } else {
            $stmt = self::db()->query('
                SELECT jp.*, d.name AS department_name, u.name AS posted_by_name,
                       (SELECT COUNT(*) FROM applicants WHERE job_posting_id = jp.id) AS applicant_count
                FROM job_postings jp
                JOIN departments d ON d.id = jp.department_id
                LEFT JOIN users u ON u.id = jp.posted_by
                ORDER BY jp.created_at DESC
            ');
        }
        return $stmt->fetchAll();
    }

    public static function findPostingById(int $id): ?array
    {
        $stmt = self::db()->prepare('
            SELECT jp.*, d.name AS department_name
            FROM job_postings jp JOIN departments d ON d.id = jp.department_id
            WHERE jp.id = ? LIMIT 1
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function createPosting(array $data): bool
    {
        $stmt = self::db()->prepare('
            INSERT INTO job_postings
              (department_id, position_id, title, description, requirements,
               slots, salary_min, salary_max, employment_type, deadline, posted_by)
            VALUES
              (:department_id, :position_id, :title, :description, :requirements,
               :slots, :salary_min, :salary_max, :employment_type, :deadline, :posted_by)
        ');
        return (bool) $stmt->execute([
            ':department_id'   => $data['department_id'],
            ':position_id'     => $data['position_id']    ?? null,
            ':title'           => $data['title'],
            ':description'     => $data['description']    ?? null,
            ':requirements'    => $data['requirements']   ?? null,
            ':slots'           => $data['slots']          ?? 1,
            ':salary_min'      => $data['salary_min']     ?? null,
            ':salary_max'      => $data['salary_max']     ?? null,
            ':employment_type' => $data['employment_type'] ?? 'regular',
            ':deadline'        => $data['deadline']       ?? null,
            ':posted_by'       => $data['posted_by']      ?? null,
        ]);
    }

    public static function updatePostingStatus(int $id, string $status): bool
    {
        $stmt = self::db()->prepare('UPDATE job_postings SET status = ? WHERE id = ?');
        return (bool) $stmt->execute([$status, $id]);
    }

    public static function deletePosting(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM job_postings WHERE id = ?');
        return (bool) $stmt->execute([$id]);
    }

    public static function countOpenPostings(): int
    {
        $row = self::db()->query("SELECT COUNT(*) AS cnt FROM job_postings WHERE status = 'open'")->fetch();
        return (int) $row['cnt'];
    }

    // ── Applicants ───────────────────────────────────────────────────────────

    public static function getApplicantsByJob(int $jobId): array
    {
        $stmt = self::db()->prepare('
            SELECT a.*, u.name AS processed_by_name
            FROM applicants a LEFT JOIN users u ON u.id = a.processed_by
            WHERE a.job_posting_id = ? ORDER BY a.applied_at DESC
        ');
        $stmt->execute([$jobId]);
        return $stmt->fetchAll();
    }

    public static function findApplicantById(int $id): ?array
    {
        $stmt = self::db()->prepare('
            SELECT a.*, jp.title AS job_title, d.name AS department_name
            FROM applicants a
            JOIN job_postings jp ON jp.id = a.job_posting_id
            JOIN departments d ON d.id = jp.department_id
            WHERE a.id = ? LIMIT 1
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function createApplicant(array $data): bool
    {
        $stmt = self::db()->prepare('
            INSERT INTO applicants (job_posting_id, name, email, phone, source, notes)
            VALUES (:job_posting_id, :name, :email, :phone, :source, :notes)
        ');
        return (bool) $stmt->execute([
            ':job_posting_id' => $data['job_posting_id'],
            ':name'           => $data['name'],
            ':email'          => $data['email']  ?? null,
            ':phone'          => $data['phone']  ?? null,
            ':source'         => $data['source'] ?? 'walk_in',
            ':notes'          => $data['notes']  ?? null,
        ]);
    }

    public static function updateApplicantStatus(int $id, string $status, int $processedBy, string $notes = '', ?string $interviewDate = null): bool
    {
        $stmt = self::db()->prepare('
            UPDATE applicants
            SET status = :status, processed_by = :processed_by,
                notes = :notes, interview_date = :interview_date
            WHERE id = :id
        ');
        return (bool) $stmt->execute([
            ':status'         => $status,
            ':processed_by'   => $processedBy,
            ':notes'          => $notes,
            ':interview_date' => $interviewDate,
            ':id'             => $id,
        ]);
    }

    public static function countApplicantsForJob(int $jobId): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) AS cnt FROM applicants WHERE job_posting_id = ?');
        $stmt->execute([$jobId]);
        return (int) $stmt->fetch()['cnt'];
    }

    public static function countNewApplicants(): int
    {
        $row = self::db()->query("SELECT COUNT(*) AS cnt FROM applicants WHERE status = 'new'")->fetch();
        return (int) $row['cnt'];
    }
}
