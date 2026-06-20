<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class EmployeeModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ─── Read ──────────────────────────────────────────────────────────────────

    public function getAll(array $filters = [], int $limit = 500, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]            = 'e.status = :status';
            $params[':status']  = $filters['status'];
        }
        if (!empty($filters['department_id'])) {
            $where[]                 = 'e.department_id = :dept_id';
            $params[':dept_id']      = $filters['department_id'];
        }
        if (!empty($filters['designation_id'])) {
            $where[]                    = 'e.designation_id = :des_id';
            $params[':des_id']          = $filters['designation_id'];
        }
        if (!empty($filters['search'])) {
            $where[]               = '(e.full_name LIKE :search OR e.employee_code LIKE :search OR e.email LIKE :search)';
            $params[':search']     = '%' . $filters['search'] . '%';
        }

        $sql = "
            SELECT e.*,
                   d.name  AS department_name,
                   des.name AS designation_name
            FROM employees e
            LEFT JOIN departments d   ON d.id   = e.department_id
            LEFT JOIN designations des ON des.id = e.designation_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.full_name
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT e.*,
                   d.name   AS department_name,
                   des.name AS designation_name,
                   u.email  AS user_email
            FROM employees e
            LEFT JOIN departments  d   ON d.id   = e.department_id
            LEFT JOIN designations des ON des.id = e.designation_id
            LEFT JOIN users        u   ON u.id   = e.user_id
            WHERE e.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByCode(string $code): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM employees WHERE employee_code = ? LIMIT 1");
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByBadge(string $badge): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM employees WHERE badge_number = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$badge]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function count(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]           = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['department_id'])) {
            $where[]              = 'department_id = :dept_id';
            $params[':dept_id']   = $filters['department_id'];
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM employees WHERE " . implode(' AND ', $where));
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ─── Write ─────────────────────────────────────────────────────────────────

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO employees
                (employee_code, full_name, email, phone, gender, date_of_birth,
                 department_id, designation_id, join_date, status, badge_number,
                 blood_group, address, emergency_contact, profile_photo, user_id, created_by)
            VALUES
                (:employee_code, :full_name, :email, :phone, :gender, :date_of_birth,
                 :department_id, :designation_id, :join_date, :status, :badge_number,
                 :blood_group, :address, :emergency_contact, :profile_photo, :user_id, :created_by)
        ");
        $stmt->execute([
            ':employee_code'    => $data['employee_code'],
            ':full_name'        => $data['full_name'],
            ':email'            => strtolower(trim($data['email'] ?? '')),
            ':phone'            => $data['phone'] ?? null,
            ':gender'           => $data['gender'] ?? 'male',
            ':date_of_birth'    => $data['date_of_birth'] ?? null,
            ':department_id'    => !empty($data['department_id']) ? (int)$data['department_id'] : null,
            ':designation_id'   => !empty($data['designation_id']) ? (int)$data['designation_id'] : null,
            ':join_date'        => $data['join_date'],
            ':status'           => $data['status'] ?? 'active',
            ':badge_number'     => $data['badge_number'] ?? null,
            ':blood_group'      => $data['blood_group'] ?? null,
            ':address'          => $data['address'] ?? null,
            ':emergency_contact'=> $data['emergency_contact'] ?? null,
            ':profile_photo'    => $data['profile_photo'] ?? null,
            ':user_id'          => !empty($data['user_id']) ? (int)$data['user_id'] : null,
            ':created_by'       => (int)$data['created_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = [
            'full_name','email','phone','gender','date_of_birth','department_id',
            'designation_id','join_date','status','badge_number','blood_group',
            'address','emergency_contact','profile_photo','user_id',
        ];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[]     = "$f = :$f";
                $params[":$f"] = $data[$f];
            }
        }
        if (empty($fields)) return false;
        $stmt = $this->db->prepare("UPDATE employees SET " . implode(', ', $fields) . ", updated_at=NOW() WHERE id=:id");
        return $stmt->execute($params);
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->db->prepare("UPDATE employees SET status=?, updated_at=NOW() WHERE id=?")
                        ->execute([$status, $id]);
    }

    // ─── Transfers & Promotions ───────────────────────────────────────────────

    public function getTransferHistory(int $employeeId): array
    {
        $stmt = $this->db->prepare("
            SELECT et.*, d.name AS dept_name, des.name AS des_name
            FROM employee_transfers et
            LEFT JOIN departments d   ON d.id   = et.to_department_id
            LEFT JOIN designations des ON des.id = et.to_designation_id
            WHERE et.employee_id = ?
            ORDER BY et.effective_date DESC
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addTransfer(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO employee_transfers
                (employee_id, from_department_id, to_department_id, from_designation_id, to_designation_id, effective_date, reason, created_by)
            VALUES (:emp_id, :from_dept, :to_dept, :from_des, :to_des, :eff_date, :reason, :created_by)
        ");
        $stmt->execute([
            ':emp_id'     => $data['employee_id'],
            ':from_dept'  => $data['from_department_id'] ?? null,
            ':to_dept'    => $data['to_department_id'] ?? null,
            ':from_des'   => $data['from_designation_id'] ?? null,
            ':to_des'     => $data['to_designation_id'] ?? null,
            ':eff_date'   => $data['effective_date'],
            ':reason'     => $data['reason'] ?? null,
            ':created_by' => $data['created_by'],
        ]);
        $id = (int)$this->db->lastInsertId();

        // Apply transfer
        $this->update((int)$data['employee_id'], array_filter([
            'department_id'  => $data['to_department_id'] ?? null,
            'designation_id' => $data['to_designation_id'] ?? null,
        ]));
        return $id;
    }

    // ─── Documents ────────────────────────────────────────────────────────────

    public function getDocuments(int $employeeId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM employee_documents WHERE employee_id = ? ORDER BY created_at DESC
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addDocument(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO employee_documents (employee_id, document_type, document_name, file_path, expiry_date, created_by)
            VALUES (:emp_id, :doc_type, :doc_name, :file_path, :expiry, :created_by)
        ");
        $stmt->execute([
            ':emp_id'     => $data['employee_id'],
            ':doc_type'   => $data['document_type'],
            ':doc_name'   => $data['document_name'],
            ':file_path'  => $data['file_path'],
            ':expiry'     => $data['expiry_date'] ?? null,
            ':created_by' => $data['created_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    // ─── Search ───────────────────────────────────────────────────────────────

    public function search(string $query, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT id, employee_code, full_name, email, department_id, status
            FROM employees
            WHERE (full_name LIKE :q OR employee_code LIKE :q OR email LIKE :q)
              AND status = 'active'
            ORDER BY full_name
            LIMIT :limit
        ");
        $stmt->bindValue(':q',     '%' . $query . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
