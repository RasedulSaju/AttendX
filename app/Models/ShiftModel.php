<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class ShiftModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ─── Shifts ───────────────────────────────────────────────────────────────

    public function getAll(bool $activeOnly = false): array
    {
        $sql = "SELECT s.*, COUNT(sa.id) AS assigned_employees
                FROM shifts s
                LEFT JOIN shift_assignments sa ON sa.shift_id = s.id
                  AND (sa.date_to IS NULL OR sa.date_to >= CURDATE())";
        if ($activeOnly) $sql .= " WHERE s.is_active = 1";
        $sql .= " GROUP BY s.id ORDER BY s.name";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM shifts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getShiftDays(int $shiftId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM shift_days WHERE shift_id = ? ORDER BY day_of_week");
        $stmt->execute([$shiftId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data, array $days = []): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO shifts (name, code, start_time, end_time, is_night_shift,
                grace_minutes, ot_threshold, work_minutes, half_day_minutes, is_active)
            VALUES (:name, :code, :start, :end, :night, :grace, :ot, :work, :half, :active)
        ");
        $stmt->execute([
            ':name'   => $data['name'],
            ':code'   => strtoupper($data['code'] ?? ''),
            ':start'  => $data['start_time'],
            ':end'    => $data['end_time'],
            ':night'  => (int)($data['is_night_shift'] ?? 0),
            ':grace'  => (int)($data['grace_minutes'] ?? 15),
            ':ot'     => (int)($data['ot_threshold'] ?? 30),
            ':work'   => (int)($data['work_minutes'] ?? 480),
            ':half'   => (int)($data['half_day_minutes'] ?? 240),
            ':active' => (int)($data['is_active'] ?? 1),
        ]);
        $id = (int)$this->db->lastInsertId();

        if (!empty($days)) {
            $this->syncShiftDays($id, $days);
        }
        return $id;
    }

    public function update(int $id, array $data, array $days = []): bool
    {
        $stmt = $this->db->prepare("
            UPDATE shifts SET name=:name, code=:code, start_time=:start, end_time=:end,
                is_night_shift=:night, grace_minutes=:grace, ot_threshold=:ot,
                work_minutes=:work, half_day_minutes=:half, is_active=:active
            WHERE id=:id
        ");
        $ok = $stmt->execute([
            ':name'   => $data['name'],
            ':code'   => strtoupper($data['code'] ?? ''),
            ':start'  => $data['start_time'],
            ':end'    => $data['end_time'],
            ':night'  => (int)($data['is_night_shift'] ?? 0),
            ':grace'  => (int)($data['grace_minutes'] ?? 15),
            ':ot'     => (int)($data['ot_threshold'] ?? 30),
            ':work'   => (int)($data['work_minutes'] ?? 480),
            ':half'   => (int)($data['half_day_minutes'] ?? 240),
            ':active' => (int)($data['is_active'] ?? 1),
            ':id'     => $id,
        ]);
        if (!empty($days)) $this->syncShiftDays($id, $days);
        return $ok;
    }

    public function delete(int $id): bool
    {
        $count = $this->db->prepare("SELECT COUNT(*) FROM shift_assignments WHERE shift_id=?");
        $count->execute([$id]);
        if ((int)$count->fetchColumn() > 0) return false;
        return $this->db->prepare("DELETE FROM shifts WHERE id=?")->execute([$id]);
    }

    private function syncShiftDays(int $shiftId, array $workingDays): void
    {
        $this->db->prepare("DELETE FROM shift_days WHERE shift_id=?")->execute([$shiftId]);
        $stmt = $this->db->prepare("INSERT INTO shift_days (shift_id, day_of_week, is_working) VALUES (?,?,?)");
        for ($d = 0; $d <= 6; $d++) {
            $stmt->execute([$shiftId, $d, in_array($d, $workingDays) ? 1 : 0]);
        }
    }

    // ─── Assignments ──────────────────────────────────────────────────────────

    public function getEmployeeShift(int $employeeId, string $date): array|false
    {
        // Check individual assignment first, fallback to roster
        $stmt = $this->db->prepare("
            SELECT s.* FROM shift_assignments sa
            JOIN shifts s ON s.id = sa.shift_id
            WHERE sa.employee_id = ? AND sa.date_from <= ? AND (sa.date_to IS NULL OR sa.date_to >= ?)
            ORDER BY sa.date_from DESC LIMIT 1
        ");
        $stmt->execute([$employeeId, $date, $date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) return $result;

        // Fallback: roster
        $stmt = $this->db->prepare("
            SELECT s.* FROM roster_employees re
            JOIN rosters r ON r.id = re.roster_id
            JOIN shifts s ON s.id = r.shift_id
            WHERE re.employee_id = ? AND r.start_date <= ?
              AND (r.end_date IS NULL OR r.end_date >= ?) AND r.is_active = 1
            ORDER BY r.start_date DESC LIMIT 1
        ");
        $stmt->execute([$employeeId, $date, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function assignShift(array $data): int
    {
        // Close existing open assignment
        $this->db->prepare("
            UPDATE shift_assignments SET date_to = DATE_SUB(:from, INTERVAL 1 DAY)
            WHERE employee_id = :emp AND date_to IS NULL
        ")->execute([':from' => $data['date_from'], ':emp' => $data['employee_id']]);

        $stmt = $this->db->prepare("
            INSERT INTO shift_assignments (employee_id, shift_id, date_from, date_to, created_by)
            VALUES (:emp, :shift, :from, :to, :by)
        ");
        $stmt->execute([
            ':emp'   => (int)$data['employee_id'],
            ':shift' => (int)$data['shift_id'],
            ':from'  => $data['date_from'],
            ':to'    => $data['date_to'] ?? null,
            ':by'    => (int)$data['created_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function unassignShift(int $employeeId): bool
    {
        return $this->db->prepare("
            UPDATE shift_assignments SET date_to = CURDATE()
            WHERE employee_id = ? AND date_to IS NULL
        ")->execute([$employeeId]);
    }

    // ─── Rosters ──────────────────────────────────────────────────────────────

    public function getAllRosters(): array
    {
        return $this->db->query("
            SELECT r.*, s.name AS shift_name, COUNT(re.employee_id) AS employee_count
            FROM rosters r
            JOIN shifts s ON s.id = r.shift_id
            LEFT JOIN roster_employees re ON re.roster_id = r.id
            GROUP BY r.id ORDER BY r.start_date DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRosterEmployees(int $rosterId): array
    {
        $stmt = $this->db->prepare("
            SELECT e.id, e.employee_code, e.full_name, d.name AS department_name
            FROM roster_employees re
            JOIN employees e ON e.id = re.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE re.roster_id = ? ORDER BY e.full_name
        ");
        $stmt->execute([$rosterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Shift Change Requests ────────────────────────────────────────────────

    public function getChangeRequests(string $status = ''): array
    {
        $where = $status ? "WHERE rsc.status = '$status'" : '';
        return $this->db->query("
            SELECT rsc.*, e.full_name AS employee_name, e.employee_code,
                   s1.name AS current_shift_name, s2.name AS requested_shift_name
            FROM roster_shift_changes rsc
            JOIN employees e ON e.id = rsc.employee_id
            JOIN shifts s1 ON s1.id = rsc.current_shift_id
            JOIN shifts s2 ON s2.id = rsc.requested_shift_id
            $where ORDER BY rsc.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function approveChangeRequest(int $id, int $userId, string $status): bool
    {
        return $this->db->prepare("
            UPDATE roster_shift_changes SET status=:status, approved_by=:uid, approved_at=NOW()
            WHERE id=:id AND status='pending'
        ")->execute([':status' => $status, ':uid' => $userId, ':id' => $id]);
    }
}
