<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class AccessControlModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ─── Zones ──────────────────────────────────────────────────────────────────

    public function getAllZones(bool $activeOnly = false): array
    {
        $sql = "SELECT az.*, COUNT(ad.id) AS door_count
                FROM access_zones az
                LEFT JOIN access_doors ad ON ad.zone_id = az.id";
        if ($activeOnly) {
            $sql .= " WHERE az.is_active = 1";
        }
        $sql .= " GROUP BY az.id ORDER BY az.security_level DESC, az.name";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getZoneById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM access_zones WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createZone(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO access_zones (name, description, zone_code, security_level, is_active)
            VALUES (:name, :description, :zone_code, :security_level, :is_active)
        ");
        $stmt->execute([
            ':name'           => $data['name'],
            ':description'    => $data['description'] ?? null,
            ':zone_code'      => strtoupper($data['zone_code']),
            ':security_level' => (int)$data['security_level'],
            ':is_active'      => (int)($data['is_active'] ?? 1),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateZone(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE access_zones SET
                name = :name, description = :description, zone_code = :zone_code,
                security_level = :security_level, is_active = :is_active
            WHERE id = :id
        ");
        return $stmt->execute([
            ':name'           => $data['name'],
            ':description'    => $data['description'] ?? null,
            ':zone_code'      => strtoupper($data['zone_code']),
            ':security_level' => (int)$data['security_level'],
            ':is_active'      => (int)($data['is_active'] ?? 1),
            ':id'             => $id,
        ]);
    }

    public function deleteZone(int $id): bool
    {
        // Only delete if no rules or logs depend on it
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM access_rules WHERE zone_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            return false;
        }
        return $this->db->prepare("DELETE FROM access_zones WHERE id = ?")->execute([$id]);
    }

    // ─── Doors ──────────────────────────────────────────────────────────────────

    public function getDoorsByZone(int $zoneId): array
    {
        $stmt = $this->db->prepare("
            SELECT ad.*, dev.name AS device_name, dev.serial_number
            FROM access_doors ad
            JOIN devices dev ON dev.id = ad.device_id
            WHERE ad.zone_id = ?
            ORDER BY ad.name
        ");
        $stmt->execute([$zoneId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllDoors(): array
    {
        return $this->db->query("
            SELECT ad.*, az.name AS zone_name, dev.name AS device_name
            FROM access_doors ad
            JOIN access_zones az ON az.id = ad.zone_id
            JOIN devices dev ON dev.id = ad.device_id
            ORDER BY az.name, ad.name
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createDoor(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO access_doors (zone_id, device_id, name, door_index, direction, is_active)
            VALUES (:zone_id, :device_id, :name, :door_index, :direction, :is_active)
        ");
        $stmt->execute([
            ':zone_id'    => (int)$data['zone_id'],
            ':device_id'  => (int)$data['device_id'],
            ':name'       => $data['name'],
            ':door_index' => (int)($data['door_index'] ?? 1),
            ':direction'  => $data['direction'] ?? 'both',
            ':is_active'  => (int)($data['is_active'] ?? 1),
        ]);
        return (int)$this->db->lastInsertId();
    }

    // ─── Access Rules ───────────────────────────────────────────────────────────

    public function getRulesByZone(int $zoneId): array
    {
        $stmt = $this->db->prepare("
            SELECT ar.*, acs.name AS schedule_name
            FROM access_rules ar
            LEFT JOIN access_schedules acs ON acs.id = ar.schedule_id
            WHERE ar.zone_id = ?
            ORDER BY ar.permission DESC, ar.rule_type
        ");
        $stmt->execute([$zoneId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRulesForEmployee(int $employeeId): array
    {
        // Rules that apply directly or via department/designation
        $stmt = $this->db->prepare("
            SELECT ar.*, az.name AS zone_name, az.zone_code, az.security_level
            FROM access_rules ar
            JOIN access_zones az ON az.id = ar.zone_id
            JOIN employees e ON e.id = :emp_id
            WHERE ar.permission = 'allow'
              AND (ar.valid_from IS NULL OR ar.valid_from <= CURDATE())
              AND (ar.valid_to IS NULL OR ar.valid_to >= CURDATE())
              AND (
                (ar.rule_type = 'all')
                OR (ar.rule_type = 'employee' AND ar.ref_id = :emp_id)
                OR (ar.rule_type = 'department' AND ar.ref_id = e.department_id)
                OR (ar.rule_type = 'designation' AND ar.ref_id = e.designation_id)
              )
        ");
        $stmt->execute([':emp_id' => $employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createRule(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO access_rules (zone_id, rule_type, ref_id, schedule_id, permission, valid_from, valid_to, created_by)
            VALUES (:zone_id, :rule_type, :ref_id, :schedule_id, :permission, :valid_from, :valid_to, :created_by)
        ");
        $stmt->execute([
            ':zone_id'     => (int)$data['zone_id'],
            ':rule_type'   => $data['rule_type'],
            ':ref_id'      => !empty($data['ref_id']) ? (int)$data['ref_id'] : null,
            ':schedule_id' => !empty($data['schedule_id']) ? (int)$data['schedule_id'] : null,
            ':permission'  => $data['permission'] ?? 'allow',
            ':valid_from'  => $data['valid_from'] ?? null,
            ':valid_to'    => $data['valid_to'] ?? null,
            ':created_by'  => (int)$data['created_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function deleteRule(int $id): bool
    {
        return $this->db->prepare("DELETE FROM access_rules WHERE id = ?")->execute([$id]);
    }

    // ─── Access Logs ────────────────────────────────────────────────────────────

    public function getLogs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['employee_id'])) {
            $where[] = 'al.employee_id = :employee_id';
            $params[':employee_id'] = $filters['employee_id'];
        }
        if (!empty($filters['zone_id'])) {
            $where[] = 'al.zone_id = :zone_id';
            $params[':zone_id'] = $filters['zone_id'];
        }
        if (!empty($filters['event_type'])) {
            $where[] = 'al.event_type = :event_type';
            $params[':event_type'] = $filters['event_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'al.event_time >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'al.event_time <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql = "
            SELECT al.*,
                   e.full_name AS employee_name, e.employee_code,
                   az.name AS zone_name, dev.name AS device_name,
                   ad.name AS door_name
            FROM access_logs al
            LEFT JOIN employees e ON e.id = al.employee_id
            LEFT JOIN access_zones az ON az.id = al.zone_id
            LEFT JOIN devices dev ON dev.id = al.device_id
            LEFT JOIN access_doors ad ON ad.id = al.door_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY al.event_time DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countLogs(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['employee_id'])) {
            $where[] = 'employee_id = :employee_id';
            $params[':employee_id'] = $filters['employee_id'];
        }
        if (!empty($filters['zone_id'])) {
            $where[] = 'zone_id = :zone_id';
            $params[':zone_id'] = $filters['zone_id'];
        }
        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = :event_type';
            $params[':event_type'] = $filters['event_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'event_time >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'event_time <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM access_logs WHERE " . implode(' AND ', $where));
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function insertLog(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO access_logs
                (device_id, door_id, zone_id, employee_id, badge_number, event_time, event_type, direction, deny_reason, raw_data)
            VALUES
                (:device_id, :door_id, :zone_id, :employee_id, :badge_number, :event_time, :event_type, :direction, :deny_reason, :raw_data)
        ");
        $stmt->execute([
            ':device_id'    => (int)$data['device_id'],
            ':door_id'      => !empty($data['door_id']) ? (int)$data['door_id'] : null,
            ':zone_id'      => !empty($data['zone_id']) ? (int)$data['zone_id'] : null,
            ':employee_id'  => !empty($data['employee_id']) ? (int)$data['employee_id'] : null,
            ':badge_number' => $data['badge_number'] ?? null,
            ':event_time'   => $data['event_time'],
            ':event_type'   => $data['event_type'],
            ':direction'    => $data['direction'] ?? 'unknown',
            ':deny_reason'  => $data['deny_reason'] ?? null,
            ':raw_data'     => !empty($data['raw_data']) ? json_encode($data['raw_data']) : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getAccessStats(string $date): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_events,
                SUM(event_type = 'access_granted') AS granted,
                SUM(event_type = 'access_denied') AS denied,
                SUM(event_type = 'alarm') AS alarms,
                SUM(event_type = 'tamper') AS tampers
            FROM access_logs
            WHERE DATE(event_time) = ?
        ");
        $stmt->execute([$date]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ─── Visitor Passes ─────────────────────────────────────────────────────────

    public function getAllVisitorPasses(int $limit = 30, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT vp.*, e.full_name AS host_name
            FROM visitor_passes vp
            JOIN employees e ON e.id = vp.host_employee_id
            ORDER BY vp.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVisitorPassByBadge(string $badge): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM visitor_passes WHERE badge_code = ?");
        $stmt->execute([$badge]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createVisitorPass(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO visitor_passes
                (visitor_name, visitor_company, visitor_phone, visitor_id_type, visitor_id_no,
                 host_employee_id, purpose, zones_allowed, valid_from, valid_to, badge_code, created_by)
            VALUES
                (:visitor_name, :visitor_company, :visitor_phone, :visitor_id_type, :visitor_id_no,
                 :host_employee_id, :purpose, :zones_allowed, :valid_from, :valid_to, :badge_code, :created_by)
        ");
        $stmt->execute([
            ':visitor_name'     => $data['visitor_name'],
            ':visitor_company'  => $data['visitor_company'] ?? null,
            ':visitor_phone'    => $data['visitor_phone'] ?? null,
            ':visitor_id_type'  => $data['visitor_id_type'],
            ':visitor_id_no'    => $data['visitor_id_no'],
            ':host_employee_id' => (int)$data['host_employee_id'],
            ':purpose'          => $data['purpose'],
            ':zones_allowed'    => !empty($data['zones_allowed']) ? json_encode($data['zones_allowed']) : null,
            ':valid_from'       => $data['valid_from'],
            ':valid_to'         => $data['valid_to'],
            ':badge_code'       => $data['badge_code'],
            ':created_by'       => (int)$data['created_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateVisitorStatus(int $id, string $status, ?string $checkin = null, ?string $checkout = null): bool
    {
        $params = [':status' => $status, ':id' => $id];
        $fields = 'status = :status';
        if ($checkin) {
            $fields .= ', checked_in_at = :checkin';
            $params[':checkin'] = $checkin;
        }
        if ($checkout) {
            $fields .= ', checked_out_at = :checkout';
            $params[':checkout'] = $checkout;
        }
        return $this->db->prepare("UPDATE visitor_passes SET $fields WHERE id = :id")->execute($params);
    }

    // ─── Schedules ──────────────────────────────────────────────────────────────

    public function getAllSchedules(): array
    {
        return $this->db->query("SELECT * FROM access_schedules WHERE is_active = 1 ORDER BY name")
                        ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createSchedule(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO access_schedules (name, time_from, time_to, days_of_week)
            VALUES (:name, :time_from, :time_to, :days)
        ");
        $stmt->execute([
            ':name'      => $data['name'],
            ':time_from' => $data['time_from'],
            ':time_to'   => $data['time_to'],
            ':days'      => is_array($data['days_of_week']) ? implode(',', $data['days_of_week']) : $data['days_of_week'],
        ]);
        return (int)$this->db->lastInsertId();
    }
}
