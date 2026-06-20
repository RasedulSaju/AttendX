<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class DepartmentModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(bool $activeOnly = false): array
    {
        $sql = "
            SELECT d.*,
                   p.name AS parent_name,
                   COUNT(e.id) AS employee_count
            FROM departments d
            LEFT JOIN departments p ON p.id = d.parent_id
            LEFT JOIN employees e ON e.department_id = d.id AND e.status = 'active'
        ";
        if ($activeOnly) $sql .= " WHERE d.is_active = 1";
        $sql .= " GROUP BY d.id ORDER BY d.name";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO departments (name, code, parent_id, manager_id, is_active, description)
            VALUES (:name, :code, :parent_id, :manager_id, :active, :desc)
        ");
        $stmt->execute([
            ':name'       => $data['name'],
            ':code'       => strtoupper($data['code'] ?? ''),
            ':parent_id'  => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            ':manager_id' => !empty($data['manager_id']) ? (int)$data['manager_id'] : null,
            ':active'     => (int)($data['is_active'] ?? 1),
            ':desc'       => $data['description'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE departments SET
                name = :name, code = :code, parent_id = :parent_id,
                manager_id = :manager_id, is_active = :active,
                description = :desc, updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            ':name'       => $data['name'],
            ':code'       => strtoupper($data['code'] ?? ''),
            ':parent_id'  => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            ':manager_id' => !empty($data['manager_id']) ? (int)$data['manager_id'] : null,
            ':active'     => (int)($data['is_active'] ?? 1),
            ':desc'       => $data['description'] ?? null,
            ':id'         => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $count = $this->db->prepare("SELECT COUNT(*) FROM employees WHERE department_id=? AND status='active'");
        $count->execute([$id]);
        if ((int)$count->fetchColumn() > 0) return false;
        return $this->db->prepare("DELETE FROM departments WHERE id=?")->execute([$id]);
    }

    public function getTree(): array
    {
        $all  = $this->getAll();
        $tree = [];
        $map  = [];
        foreach ($all as &$d) { $map[$d['id']] = &$d; $d['children'] = []; }
        foreach ($all as &$d) {
            if ($d['parent_id'] && isset($map[$d['parent_id']])) {
                $map[$d['parent_id']]['children'][] = &$d;
            } else {
                $tree[] = &$d;
            }
        }
        return $tree;
    }
}
