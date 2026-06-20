<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class DesignationModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(bool $activeOnly = false): array
    {
        $sql = "
            SELECT des.*,
                   d.name AS department_name,
                   COUNT(e.id) AS employee_count
            FROM designations des
            LEFT JOIN departments d ON d.id = des.department_id
            LEFT JOIN employees e ON e.designation_id = des.id AND e.status = 'active'
        ";
        if ($activeOnly) $sql .= " WHERE des.is_active = 1";
        $sql .= " GROUP BY des.id ORDER BY des.name";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByDepartment(int $departmentId, bool $activeOnly = true): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM designations
            WHERE department_id = ? " . ($activeOnly ? "AND is_active = 1" : "") . "
            ORDER BY name
        ");
        $stmt->execute([$departmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM designations WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO designations (name, department_id, grade, is_active)
            VALUES (:name, :dept_id, :grade, :active)
        ");
        $stmt->execute([
            ':name'    => $data['name'],
            ':dept_id' => !empty($data['department_id']) ? (int)$data['department_id'] : null,
            ':grade'   => $data['grade'] ?? null,
            ':active'  => (int)($data['is_active'] ?? 1),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE designations SET
                name = :name, department_id = :dept_id,
                grade = :grade, is_active = :active
            WHERE id = :id
        ");
        return $stmt->execute([
            ':name'    => $data['name'],
            ':dept_id' => !empty($data['department_id']) ? (int)$data['department_id'] : null,
            ':grade'   => $data['grade'] ?? null,
            ':active'  => (int)($data['is_active'] ?? 1),
            ':id'      => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $count = $this->db->prepare("SELECT COUNT(*) FROM employees WHERE designation_id=? AND status='active'");
        $count->execute([$id]);
        if ((int)$count->fetchColumn() > 0) return false;
        return $this->db->prepare("DELETE FROM designations WHERE id=?")->execute([$id]);
    }
}
