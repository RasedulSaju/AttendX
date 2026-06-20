<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class AttendanceModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ─── Attendance Records ───────────────────────────────────────────────────

    public function getRecords(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['employee_id'])) {
            $where[]               = 'ar.employee_id = :employee_id';
            $params[':employee_id']= $filters['employee_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[]               = 'ar.attendance_date >= :date_from';
            $params[':date_from']  = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]               = 'ar.attendance_date <= :date_to';
            $params[':date_to']    = $filters['date_to'];
        }
        if (!empty($filters['status'])) {
            $where[]               = 'ar.status = :status';
            $params[':status']     = $filters['status'];
        }
        if (!empty($filters['department_id'])) {
            $where[]               = 'e.department_id = :dept_id';
            $params[':dept_id']    = $filters['department_id'];
        }

        $stmt = $this->db->prepare("
            SELECT ar.*,
                   e.employee_code, e.full_name,
                   d.name AS department_name,
                   s.name AS shift_name
            FROM attendance_records ar
            JOIN employees e     ON e.id = ar.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN shifts s   ON s.id = ar.shift_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ar.attendance_date DESC, e.full_name
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecordByEmployeeDate(int $employeeId, string $date): array|false
    {
        $stmt = $this->db->prepare("
            SELECT ar.*, s.name AS shift_name
            FROM attendance_records ar
            LEFT JOIN shifts s ON s.id = ar.shift_id
            WHERE ar.employee_id = ? AND ar.attendance_date = ?
            LIMIT 1
        ");
        $stmt->execute([$employeeId, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function countRecords(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['employee_id'])) {
            $where[]               = 'ar.employee_id = :employee_id';
            $params[':employee_id']= $filters['employee_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[]               = 'ar.attendance_date >= :date_from';
            $params[':date_from']  = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]               = 'ar.attendance_date <= :date_to';
            $params[':date_to']    = $filters['date_to'];
        }
        if (!empty($filters['status'])) {
            $where[]               = 'ar.status = :status';
            $params[':status']     = $filters['status'];
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM attendance_records ar
            JOIN employees e ON e.id = ar.employee_id
            WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ─── Statistics ───────────────────────────────────────────────────────────

    public function getAttendanceStats(int $employeeId, string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*)                                         AS total_days,
                SUM(status = 'present')                         AS present,
                SUM(status = 'absent')                          AS absent,
                SUM(status = 'late')                            AS late,
                SUM(status = 'half_day')                        AS half_day,
                SUM(status = 'on_leave')                        AS on_leave,
                SUM(late_minutes)                               AS late_minutes,
                SUM(overtime_minutes)                           AS overtime_minutes,
                SUM(work_minutes)                               AS work_minutes,
                AVG(NULLIF(work_minutes,0))                     AS avg_work_minutes
            FROM attendance_records
            WHERE employee_id = ?
              AND attendance_date BETWEEN ? AND ?
        ");
        $stmt->execute([$employeeId, $dateFrom, $dateTo]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDailyStats(string $date): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*)                     AS total,
                SUM(status='present')        AS present,
                SUM(status='absent')         AS absent,
                SUM(status='late')           AS late,
                SUM(status='half_day')       AS half_day,
                SUM(status='on_leave')       AS on_leave
            FROM attendance_records
            WHERE attendance_date = ?
        ");
        $stmt->execute([$date]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ─── Raw Punch Logs ───────────────────────────────────────────────────────

    public function getLastPunch(int $employeeId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT al.*, d.name AS device_name
            FROM attendance_logs al
            LEFT JOIN devices d ON d.id = al.device_id
            WHERE al.employee_id = ?
            ORDER BY al.punch_time DESC
            LIMIT 1
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPunchLogs(int $employeeId, string $date): array
    {
        $stmt = $this->db->prepare("
            SELECT al.*, d.name AS device_name
            FROM attendance_logs al
            LEFT JOIN devices d ON d.id = al.device_id
            WHERE al.employee_id = ?
              AND DATE(al.punch_time) = ?
            ORDER BY al.punch_time
        ");
        $stmt->execute([$employeeId, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertManualPunch(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO attendance_logs
                (employee_id, device_id, badge_number, punch_time, punch_type, verify_type, source, note, synced_at)
            VALUES
                (:employee_id, :device_id, :badge_number, :punch_time, :punch_type, :verify_type, :source, :note, NOW())
        ");
        $stmt->execute([
            ':employee_id'  => (int)$data['employee_id'],
            ':device_id'    => !empty($data['device_id']) ? (int)$data['device_id'] : null,
            ':badge_number' => $data['badge_number'] ?? null,
            ':punch_time'   => $data['punch_time'],
            ':punch_type'   => (int)($data['punch_type_int'] ?? ($data['punch_type'] === 'out' ? 1 : 0)),
            ':verify_type'  => 0,
            ':source'       => $data['source'] ?? 'manual',
            ':note'         => $data['note'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    // ─── Record Management ────────────────────────────────────────────────────

    public function upsertRecord(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO attendance_records
                (employee_id, attendance_date, shift_id, status,
                 time_in, time_out, work_minutes, late_minutes, overtime_minutes, note)
            VALUES
                (:employee_id, :date, :shift_id, :status,
                 :time_in, :time_out, :work_minutes, :late_minutes, :overtime_minutes, :note)
            ON DUPLICATE KEY UPDATE
                shift_id         = VALUES(shift_id),
                status           = VALUES(status),
                time_in          = VALUES(time_in),
                time_out         = VALUES(time_out),
                work_minutes     = VALUES(work_minutes),
                late_minutes     = VALUES(late_minutes),
                overtime_minutes = VALUES(overtime_minutes),
                note             = VALUES(note),
                updated_at       = NOW()
        ");
        $stmt->execute([
            ':employee_id'      => (int)$data['employee_id'],
            ':date'             => $data['attendance_date'],
            ':shift_id'         => !empty($data['shift_id']) ? (int)$data['shift_id'] : null,
            ':status'           => $data['status'] ?? 'present',
            ':time_in'          => $data['time_in'] ?? null,
            ':time_out'         => $data['time_out'] ?? null,
            ':work_minutes'     => (int)($data['work_minutes'] ?? 0),
            ':late_minutes'     => (int)($data['late_minutes'] ?? 0),
            ':overtime_minutes' => (int)($data['overtime_minutes'] ?? 0),
            ':note'             => $data['note'] ?? null,
        ]);

        if ($this->db->lastInsertId() > 0) {
            return (int)$this->db->lastInsertId();
        }

        $s = $this->db->prepare("SELECT id FROM attendance_records WHERE employee_id=? AND attendance_date=?");
        $s->execute([$data['employee_id'], $data['attendance_date']]);
        return (int)$s->fetchColumn();
    }

    public function updateRecord(int $id, array $data): bool
    {
        $allowed = ['status','time_in','time_out','work_minutes','late_minutes','overtime_minutes','note','shift_id'];
        $fields  = [];
        $params  = [':id' => $id];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[]      = "$f=:$f";
                $params[":$f"] = $data[$f];
            }
        }
        if (empty($fields)) return false;
        return $this->db->prepare("UPDATE attendance_records SET " . implode(',', $fields) . ", updated_at=NOW() WHERE id=:id")
                        ->execute($params);
    }

    public function deleteRecord(int $id): bool
    {
        return $this->db->prepare("DELETE FROM attendance_records WHERE id=?")->execute([$id]);
    }

    // ─── Processing Queue ─────────────────────────────────────────────────────

    public function getPendingQueue(): array
    {
        return $this->db->query("
            SELECT pq.*, d.name AS device_name
            FROM processing_queue pq
            JOIN devices d ON d.id = pq.device_id
            WHERE pq.processed = 0
            ORDER BY pq.queued_at
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markQueueProcessed(int $deviceId): void
    {
        $this->db->prepare("UPDATE processing_queue SET processed=1 WHERE device_id=?")->execute([$deviceId]);
    }

    // ─── Unprocessed Logs ─────────────────────────────────────────────────────

    public function getUnprocessedLogs(int $deviceId, string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare("
            SELECT al.*, e.id AS emp_id, e.department_id
            FROM attendance_logs al
            LEFT JOIN employees e ON e.badge_number = al.badge_number AND e.status = 'active'
            WHERE al.device_id = ?
              AND DATE(al.punch_time) BETWEEN ? AND ?
            ORDER BY al.punch_time
        ");
        $stmt->execute([$deviceId, $dateFrom, $dateTo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Overtime ─────────────────────────────────────────────────────────────

    public function getOvertimeRecords(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['ar.overtime_minutes > 0'];
        $params = [];

        if (!empty($filters['employee_id'])) {
            $where[]               = 'ar.employee_id = :emp_id';
            $params[':emp_id']     = $filters['employee_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[]               = 'ar.attendance_date >= :date_from';
            $params[':date_from']  = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]               = 'ar.attendance_date <= :date_to';
            $params[':date_to']    = $filters['date_to'];
        }

        $stmt = $this->db->prepare("
            SELECT ar.*, e.employee_code, e.full_name, d.name AS department_name
            FROM attendance_records ar
            JOIN employees e ON e.id = ar.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ar.attendance_date DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Exceptions / Missing ─────────────────────────────────────────────────

    public function getAbsentees(string $date): array
    {
        return $this->db->prepare("
            SELECT e.id, e.employee_code, e.full_name, d.name AS department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE e.status = 'active'
              AND e.id NOT IN (
                  SELECT employee_id FROM attendance_records
                  WHERE attendance_date = ? AND status != 'absent'
              )
            ORDER BY e.full_name
        ")->execute([$date]) ? $this->db->query("
            SELECT e.id, e.employee_code, e.full_name, d.name AS department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE e.status = 'active'
              AND e.id NOT IN (
                  SELECT employee_id FROM attendance_records
                  WHERE attendance_date = '$date' AND status != 'absent'
              )
            ORDER BY e.full_name
        ")->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}
