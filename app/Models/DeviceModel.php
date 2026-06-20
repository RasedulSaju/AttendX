<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class DeviceModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (isset($filters['is_active'])) {
            $where[]              = 'd.is_active = :active';
            $params[':active']    = (int)$filters['is_active'];
        }
        if (!empty($filters['status'])) {
            $where[]              = 'd.status = :status';
            $params[':status']    = $filters['status'];
        }

        $stmt = $this->db->prepare("
            SELECT d.*,
                   COUNT(al.id) AS today_punches
            FROM devices d
            LEFT JOIN attendance_logs al
                ON al.device_id = d.id AND DATE(al.punch_time) = CURDATE()
            WHERE " . implode(' AND ', $where) . "
            GROUP BY d.id
            ORDER BY d.name
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM devices WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getBySerial(string $serial): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM devices WHERE serial_number = ? LIMIT 1");
        $stmt->execute([$serial]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO devices
                (name, serial_number, ip_address, port, model, location, adms_port, is_active, status, created_by)
            VALUES
                (:name, :serial, :ip, :port, :model, :location, :adms_port, :active, 'offline', :created_by)
        ");
        $stmt->execute([
            ':name'       => $data['name'],
            ':serial'     => $data['serial_number'] ?? null,
            ':ip'         => $data['ip_address'] ?? null,
            ':port'       => (int)($data['port'] ?? 4370),
            ':model'      => $data['model'] ?? null,
            ':location'   => $data['location'] ?? null,
            ':adms_port'  => (int)($data['adms_port'] ?? 8080),
            ':active'     => (int)($data['is_active'] ?? 1),
            ':created_by' => (int)$data['created_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE devices SET
                name = :name, ip_address = :ip, port = :port,
                model = :model, location = :location,
                adms_port = :adms_port, is_active = :active,
                updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            ':name'      => $data['name'],
            ':ip'        => $data['ip_address'] ?? null,
            ':port'      => (int)($data['port'] ?? 4370),
            ':model'     => $data['model'] ?? null,
            ':location'  => $data['location'] ?? null,
            ':adms_port' => (int)($data['adms_port'] ?? 8080),
            ':active'    => (int)($data['is_active'] ?? 1),
            ':id'        => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare("DELETE FROM devices WHERE id=?")->execute([$id]);
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->prepare("UPDATE devices SET status=?, last_sync_at=NOW() WHERE id=?")->execute([$status, $id]);
    }

    public function getStats(): array
    {
        return $this->db->query("
            SELECT
                COUNT(*)                    AS total,
                SUM(status='online')        AS online,
                SUM(status='offline')       AS offline,
                SUM(is_active=1)            AS active,
                SUM(is_active=0)            AS inactive
            FROM devices
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRecentLogs(int $deviceId, int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT al.*, e.full_name AS employee_name
            FROM attendance_logs al
            LEFT JOIN employees e ON e.id = al.employee_id
            WHERE al.device_id = ?
            ORDER BY al.punch_time DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $deviceId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,    PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
