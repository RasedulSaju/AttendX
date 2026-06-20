<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class LeaveModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ─── Leave Types ──────────────────────────────────────────────────────────

    public function getLeaveTypes(bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM leave_types";
        if ($activeOnly) $sql .= " WHERE is_active = 1";
        $sql .= " ORDER BY name";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeaveTypeById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM leave_types WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createLeaveType(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO leave_types (name, code, days_allowed, carry_forward, requires_document, is_active, description)
            VALUES (:name, :code, :days, :carry, :doc, :active, :desc)
        ");
        $stmt->execute([
            ':name'  => $data['name'],
            ':code'  => strtoupper($data['code']),
            ':days'  => (int)($data['days_allowed'] ?? 0),
            ':carry' => (int)($data['carry_forward'] ?? 0),
            ':doc'   => (int)($data['requires_document'] ?? 0),
            ':active'=> (int)($data['is_active'] ?? 1),
            ':desc'  => $data['description'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateLeaveType(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE leave_types SET
                name = :name, code = :code, days_allowed = :days,
                carry_forward = :carry, requires_document = :doc,
                is_active = :active, description = :desc
            WHERE id = :id
        ");
        return $stmt->execute([
            ':name'  => $data['name'],
            ':code'  => strtoupper($data['code']),
            ':days'  => (int)($data['days_allowed'] ?? 0),
            ':carry' => (int)($data['carry_forward'] ?? 0),
            ':doc'   => (int)($data['requires_document'] ?? 0),
            ':active'=> (int)($data['is_active'] ?? 1),
            ':desc'  => $data['description'] ?? null,
            ':id'    => $id,
        ]);
    }

    // ─── Leave Balance ────────────────────────────────────────────────────────

    public function getLeaveBalance(int $employeeId, int $year): array
    {
        $stmt = $this->db->prepare("
            SELECT
                lt.id AS leave_type_id,
                lt.name AS leave_type,
                lt.days_allowed,
                COALESCE(lb.allocated_days, lt.days_allowed) AS allocated,
                COALESCE(
                    (SELECT SUM(total_days)
                     FROM leave_applications
                     WHERE employee_id = :emp_id
                       AND leave_type_id = lt.id
                       AND status = 'approved'
                       AND YEAR(from_date) = :year), 0
                ) AS used,
                COALESCE(lb.carried_forward, 0) AS carried_forward
            FROM leave_types lt
            LEFT JOIN leave_balances lb ON lb.employee_id = :emp_id2
                AND lb.leave_type_id = lt.id
                AND lb.year = :year2
            WHERE lt.is_active = 1
            ORDER BY lt.name
        ");
        $stmt->execute([
            ':emp_id'  => $employeeId,
            ':year'    => $year,
            ':emp_id2' => $employeeId,
            ':year2'   => $year,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['remaining'] = max(0, $row['allocated'] + $row['carried_forward'] - $row['used']);
        }

        return $rows;
    }

    public function getApprovedLeaveDays(int $employeeId, string $dateFrom, string $dateTo): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(total_days), 0)
            FROM leave_applications
            WHERE employee_id = ?
              AND status = 'approved'
              AND from_date <= ?
              AND to_date >= ?
        ");
        $stmt->execute([$employeeId, $dateTo, $dateFrom]);
        return (float)$stmt->fetchColumn();
    }

    public function allocateBalance(int $employeeId, int $leaveTypeId, int $year, float $days): void
    {
        $this->db->prepare("
            INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days)
            VALUES (:emp, :type, :year, :days)
            ON DUPLICATE KEY UPDATE allocated_days = VALUES(allocated_days)
        ")->execute([':emp' => $employeeId, ':type' => $leaveTypeId, ':year' => $year, ':days' => $days]);
    }

    // ─── Applications ─────────────────────────────────────────────────────────

    public function getApplications(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['employee_id'])) {
            $where[]               = 'la.employee_id = :emp_id';
            $params[':emp_id']     = $filters['employee_id'];
        }
        if (!empty($filters['status'])) {
            $where[]               = 'la.status = :status';
            $params[':status']     = $filters['status'];
        }
        if (!empty($filters['leave_type_id'])) {
            $where[]               = 'la.leave_type_id = :type_id';
            $params[':type_id']    = $filters['leave_type_id'];
        }
        if (!empty($filters['year'])) {
            $where[]               = 'YEAR(la.from_date) = :year';
            $params[':year']       = $filters['year'];
        }
        if (!empty($filters['date_from'])) {
            $where[]               = 'la.from_date >= :date_from';
            $params[':date_from']  = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]               = 'la.to_date <= :date_to';
            $params[':date_to']    = $filters['date_to'];
        }
        if (!empty($filters['department_id'])) {
            $where[]               = 'e.department_id = :dept_id';
            $params[':dept_id']    = $filters['department_id'];
        }

        $stmt = $this->db->prepare("
            SELECT la.*,
                   e.employee_code, e.full_name,
                   d.name  AS department_name,
                   lt.name AS leave_type_name,
                   u.name  AS approved_by_name
            FROM leave_applications la
            JOIN employees e   ON e.id  = la.employee_id
            JOIN leave_types lt ON lt.id = la.leave_type_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN users u  ON u.id  = la.approved_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY la.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getApplicationById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT la.*,
                   e.employee_code, e.full_name, e.email,
                   lt.name AS leave_type_name, lt.requires_document,
                   d.name  AS department_name
            FROM leave_applications la
            JOIN employees e    ON e.id  = la.employee_id
            JOIN leave_types lt ON lt.id = la.leave_type_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE la.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createApplication(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO leave_applications
                (employee_id, leave_type_id, from_date, to_date, total_days,
                 half_day, reason, document_path, status, applied_by)
            VALUES
                (:employee_id, :leave_type_id, :from_date, :to_date, :total_days,
                 :half_day, :reason, :document_path, 'pending', :applied_by)
        ");
        $stmt->execute([
            ':employee_id'   => (int)$data['employee_id'],
            ':leave_type_id' => (int)$data['leave_type_id'],
            ':from_date'     => $data['from_date'],
            ':to_date'       => $data['to_date'],
            ':total_days'    => (float)$data['total_days'],
            ':half_day'      => (int)($data['half_day'] ?? 0),
            ':reason'        => $data['reason'],
            ':document_path' => $data['document_path'] ?? null,
            ':applied_by'    => (int)($data['applied_by'] ?? $data['employee_id']),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?int $approvedBy, ?string $remarks = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE leave_applications
            SET status = :status, approved_by = :approved_by,
                approved_at = NOW(), remarks = :remarks
            WHERE id = :id
        ");
        return $stmt->execute([
            ':status'      => $status,
            ':approved_by' => $approvedBy,
            ':remarks'     => $remarks,
            ':id'          => $id,
        ]);
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    public function hasOverlappingLeave(int $employeeId, string $from, string $to, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM leave_applications
            WHERE employee_id = ?
              AND status IN ('pending','approved')
              AND id != ?
              AND from_date <= ? AND to_date >= ?
        ");
        $stmt->execute([$employeeId, $excludeId, $to, $from]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function calculateWorkingDays(string $from, string $to): float
    {
        $start = new \DateTime($from);
        $end   = new \DateTime($to);
        $days  = 0.0;

        // Fetch holidays in range
        $stmt = $this->db->prepare("
            SELECT holiday_date FROM holidays
            WHERE holiday_date BETWEEN ? AND ?
        ");
        $stmt->execute([$from, $to]);
        $holidays = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'holiday_date');

        $current = clone $start;
        while ($current <= $end) {
            $dow     = (int)$current->format('N'); // 1=Mon, 7=Sun
            $dateStr = $current->format('Y-m-d');
            if ($dow <= 5 && !in_array($dateStr, $holidays)) {
                $days += 1.0;
            }
            $current->modify('+1 day');
        }
        return $days;
    }

    // ─── Pending Summary ──────────────────────────────────────────────────────

    public function countPending(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM leave_applications WHERE status='pending'")->fetchColumn();
    }

    public function getPendingForApprover(int $managerId): array
    {
        $stmt = $this->db->prepare("
            SELECT la.*, e.full_name, e.employee_code, lt.name AS leave_type_name
            FROM leave_applications la
            JOIN employees e    ON e.id  = la.employee_id
            JOIN leave_types lt ON lt.id = la.leave_type_id
            WHERE la.status = 'pending'
              AND e.manager_id = ?
            ORDER BY la.from_date
        ");
        $stmt->execute([$managerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Holiday Management ───────────────────────────────────────────────────

    public function getHolidays(int $year): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM holidays WHERE YEAR(holiday_date) = ? ORDER BY holiday_date
        ");
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createHoliday(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO holidays (name, holiday_date, type, description)
            VALUES (:name, :date, :type, :desc)
        ");
        $stmt->execute([
            ':name' => $data['name'],
            ':date' => $data['holiday_date'],
            ':type' => $data['type'] ?? 'public',
            ':desc' => $data['description'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }
}
