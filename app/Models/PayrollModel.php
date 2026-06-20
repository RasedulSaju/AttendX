<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class PayrollModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ─── Components ────────────────────────────────────────────────────────────

    public function getAllComponents(bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM payroll_components";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order, name";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getComponentById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM payroll_components WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createComponent(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO payroll_components (name, code, type, calculation, formula, taxable, is_active, sort_order)
            VALUES (:name, :code, :type, :calculation, :formula, :taxable, :is_active, :sort_order)
        ");
        $stmt->execute([
            ':name'        => $data['name'],
            ':code'        => strtoupper($data['code']),
            ':type'        => $data['type'],
            ':calculation' => $data['calculation'],
            ':formula'     => $data['formula'] ?? null,
            ':taxable'     => (int)($data['taxable'] ?? 1),
            ':is_active'   => (int)($data['is_active'] ?? 1),
            ':sort_order'  => (int)($data['sort_order'] ?? 0),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateComponent(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE payroll_components SET
                name = :name, code = :code, type = :type,
                calculation = :calculation, formula = :formula,
                taxable = :taxable, is_active = :is_active, sort_order = :sort_order
            WHERE id = :id
        ");
        return $stmt->execute([
            ':name'        => $data['name'],
            ':code'        => strtoupper($data['code']),
            ':type'        => $data['type'],
            ':calculation' => $data['calculation'],
            ':formula'     => $data['formula'] ?? null,
            ':taxable'     => (int)($data['taxable'] ?? 1),
            ':is_active'   => (int)($data['is_active'] ?? 1),
            ':sort_order'  => (int)($data['sort_order'] ?? 0),
            ':id'          => $id,
        ]);
    }

    // ─── Salary Structures ──────────────────────────────────────────────────────

    public function getAllStructures(): array
    {
        return $this->db->query("
            SELECT s.*, COUNT(sc.id) AS component_count, u.name AS created_by_name
            FROM salary_structures s
            LEFT JOIN salary_structure_components sc ON sc.structure_id = s.id
            LEFT JOIN users u ON u.id = s.created_by
            GROUP BY s.id
            ORDER BY s.name
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStructureById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM salary_structures WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getStructureComponents(int $structureId): array
    {
        $stmt = $this->db->prepare("
            SELECT ssc.*, pc.name AS component_name, pc.code, pc.type, pc.calculation, pc.taxable
            FROM salary_structure_components ssc
            JOIN payroll_components pc ON pc.id = ssc.component_id
            WHERE ssc.structure_id = ?
            ORDER BY pc.sort_order
        ");
        $stmt->execute([$structureId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createStructure(array $data, array $components): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO salary_structures (name, description, is_active, created_by)
                VALUES (:name, :description, :is_active, :created_by)
            ");
            $stmt->execute([
                ':name'        => $data['name'],
                ':description' => $data['description'] ?? null,
                ':is_active'   => (int)($data['is_active'] ?? 1),
                ':created_by'  => $data['created_by'],
            ]);
            $structureId = (int)$this->db->lastInsertId();

            foreach ($components as $comp) {
                $this->addStructureComponent($structureId, $comp);
            }

            $this->db->commit();
            return $structureId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function addStructureComponent(int $structureId, array $comp): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO salary_structure_components (structure_id, component_id, amount, percentage, base_component_id)
            VALUES (:structure_id, :component_id, :amount, :percentage, :base_component_id)
            ON DUPLICATE KEY UPDATE amount = VALUES(amount), percentage = VALUES(percentage)
        ");
        $stmt->execute([
            ':structure_id'      => $structureId,
            ':component_id'      => (int)$comp['component_id'],
            ':amount'            => (float)($comp['amount'] ?? 0),
            ':percentage'        => !empty($comp['percentage']) ? (float)$comp['percentage'] : null,
            ':base_component_id' => !empty($comp['base_component_id']) ? (int)$comp['base_component_id'] : null,
        ]);
    }

    // ─── Employee Salary ────────────────────────────────────────────────────────

    public function getEmployeeCurrentSalary(int $employeeId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT es.*, ss.name AS structure_name
            FROM employee_salary es
            JOIN salary_structures ss ON ss.id = es.structure_id
            WHERE es.employee_id = ?
              AND es.effective_from <= CURDATE()
              AND (es.effective_to IS NULL OR es.effective_to >= CURDATE())
            ORDER BY es.effective_from DESC
            LIMIT 1
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getEmployeeSalaryHistory(int $employeeId): array
    {
        $stmt = $this->db->prepare("
            SELECT es.*, ss.name AS structure_name
            FROM employee_salary es
            JOIN salary_structures ss ON ss.id = es.structure_id
            WHERE es.employee_id = ?
            ORDER BY es.effective_from DESC
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignSalary(array $data): int
    {
        // Close existing salary
        if (!empty($data['close_existing'])) {
            $stmt = $this->db->prepare("
                UPDATE employee_salary SET effective_to = DATE_SUB(:eff, INTERVAL 1 DAY)
                WHERE employee_id = :emp AND effective_to IS NULL
            ");
            $stmt->execute([':eff' => $data['effective_from'], ':emp' => $data['employee_id']]);
        }

        $stmt = $this->db->prepare("
            INSERT INTO employee_salary
                (employee_id, structure_id, effective_from, basic_salary, bank_name, bank_account, bank_branch, payment_method, created_by)
            VALUES
                (:employee_id, :structure_id, :effective_from, :basic_salary, :bank_name, :bank_account, :bank_branch, :payment_method, :created_by)
        ");
        $stmt->execute([
            ':employee_id'    => (int)$data['employee_id'],
            ':structure_id'   => (int)$data['structure_id'],
            ':effective_from' => $data['effective_from'],
            ':basic_salary'   => (float)$data['basic_salary'],
            ':bank_name'      => $data['bank_name'] ?? null,
            ':bank_account'   => $data['bank_account'] ?? null,
            ':bank_branch'    => $data['bank_branch'] ?? null,
            ':payment_method' => $data['payment_method'] ?? 'bank',
            ':created_by'     => (int)$data['created_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    // ─── Payroll Periods ────────────────────────────────────────────────────────

    public function getAllPeriods(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT pp.*,
                   u1.name AS created_by_name,
                   u2.name AS processed_by_name,
                   u3.name AS approved_by_name,
                   COUNT(ps.id) AS slip_count,
                   SUM(ps.net_salary) AS total_payroll
            FROM payroll_periods pp
            LEFT JOIN users u1 ON u1.id = pp.created_by
            LEFT JOIN users u2 ON u2.id = pp.processed_by
            LEFT JOIN users u3 ON u3.id = pp.approved_by
            LEFT JOIN payroll_slips ps ON ps.period_id = pp.id
            GROUP BY pp.id
            ORDER BY pp.period_from DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPeriodById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT pp.*, u1.name AS created_by_name
            FROM payroll_periods pp
            LEFT JOIN users u1 ON u1.id = pp.created_by
            WHERE pp.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createPeriod(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO payroll_periods (name, period_from, period_to, notes, created_by)
            VALUES (:name, :period_from, :period_to, :notes, :created_by)
        ");
        $stmt->execute([
            ':name'        => $data['name'],
            ':period_from' => $data['period_from'],
            ':period_to'   => $data['period_to'],
            ':notes'       => $data['notes'] ?? null,
            ':created_by'  => (int)$data['created_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updatePeriodStatus(int $id, string $status, int $userId): bool
    {
        $fields = ['status = :status'];
        $params = [':status' => $status, ':id' => $id];

        if ($status === 'processing' || $status === 'processed') {
            $fields[] = 'processed_by = :user_id';
            $fields[] = 'processed_at = NOW()';
            $params[':user_id'] = $userId;
        } elseif ($status === 'approved') {
            $fields[] = 'approved_by = :user_id';
            $fields[] = 'approved_at = NOW()';
            $params[':user_id'] = $userId;
        }

        $stmt = $this->db->prepare("UPDATE payroll_periods SET " . implode(', ', $fields) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    // ─── Payroll Slips ──────────────────────────────────────────────────────────

    public function getSlipsByPeriod(int $periodId): array
    {
        $stmt = $this->db->prepare("
            SELECT ps.*,
                   e.employee_code, e.full_name, e.designation_id,
                   d.name AS department_name, des.name AS designation_name
            FROM payroll_slips ps
            JOIN employees e ON e.id = ps.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations des ON des.id = e.designation_id
            WHERE ps.period_id = ?
            ORDER BY e.full_name
        ");
        $stmt->execute([$periodId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSlipById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT ps.*, e.employee_code, e.full_name, e.email, e.phone,
                   d.name AS department_name, des.name AS designation_name,
                   pp.name AS period_name, pp.period_from, pp.period_to
            FROM payroll_slips ps
            JOIN employees e ON e.id = ps.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations des ON des.id = e.designation_id
            JOIN payroll_periods pp ON pp.id = ps.period_id
            WHERE ps.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getSlipComponents(int $slipId): array
    {
        $stmt = $this->db->prepare("
            SELECT psc.*, pc.name AS component_name, pc.code, pc.type, pc.taxable
            FROM payroll_slip_components psc
            JOIN payroll_components pc ON pc.id = psc.component_id
            WHERE psc.slip_id = ?
            ORDER BY pc.sort_order
        ");
        $stmt->execute([$slipId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsertSlip(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO payroll_slips
                (period_id, employee_id, salary_id, working_days, present_days, absent_days,
                 late_days, overtime_hours, leave_days, gross_earnings, total_deductions, net_salary, status)
            VALUES
                (:period_id, :employee_id, :salary_id, :working_days, :present_days, :absent_days,
                 :late_days, :overtime_hours, :leave_days, :gross_earnings, :total_deductions, :net_salary, :status)
            ON DUPLICATE KEY UPDATE
                salary_id = VALUES(salary_id), working_days = VALUES(working_days),
                present_days = VALUES(present_days), absent_days = VALUES(absent_days),
                late_days = VALUES(late_days), overtime_hours = VALUES(overtime_hours),
                leave_days = VALUES(leave_days), gross_earnings = VALUES(gross_earnings),
                total_deductions = VALUES(total_deductions), net_salary = VALUES(net_salary),
                status = VALUES(status)
        ");
        $stmt->execute([
            ':period_id'        => (int)$data['period_id'],
            ':employee_id'      => (int)$data['employee_id'],
            ':salary_id'        => (int)$data['salary_id'],
            ':working_days'     => (int)$data['working_days'],
            ':present_days'     => (float)$data['present_days'],
            ':absent_days'      => (float)$data['absent_days'],
            ':late_days'        => (float)$data['late_days'],
            ':overtime_hours'   => (float)$data['overtime_hours'],
            ':leave_days'       => (float)$data['leave_days'],
            ':gross_earnings'   => (float)$data['gross_earnings'],
            ':total_deductions' => (float)$data['total_deductions'],
            ':net_salary'       => (float)$data['net_salary'],
            ':status'           => $data['status'] ?? 'draft',
        ]);

        if ($this->db->lastInsertId() > 0) {
            return (int)$this->db->lastInsertId();
        }
        // ON DUPLICATE KEY: fetch existing ID
        $s = $this->db->prepare("SELECT id FROM payroll_slips WHERE period_id = ? AND employee_id = ?");
        $s->execute([$data['period_id'], $data['employee_id']]);
        return (int)$s->fetchColumn();
    }

    public function saveSlipComponents(int $slipId, array $components): void
    {
        $this->db->prepare("DELETE FROM payroll_slip_components WHERE slip_id = ?")->execute([$slipId]);
        $stmt = $this->db->prepare("
            INSERT INTO payroll_slip_components (slip_id, component_id, amount)
            VALUES (:slip_id, :component_id, :amount)
        ");
        foreach ($components as $comp) {
            $stmt->execute([
                ':slip_id'      => $slipId,
                ':component_id' => (int)$comp['component_id'],
                ':amount'       => (float)$comp['amount'],
            ]);
        }
    }

    // ─── Adjustments ───────────────────────────────────────────────────────────

    public function getAdjustmentsByPeriod(int $periodId): array
    {
        $stmt = $this->db->prepare("
            SELECT pa.*, e.full_name AS employee_name, e.employee_code,
                   u.name AS created_by_name
            FROM payroll_adjustments pa
            JOIN employees e ON e.id = pa.employee_id
            JOIN users u ON u.id = pa.created_by
            WHERE pa.period_id = ?
            ORDER BY pa.created_at DESC
        ");
        $stmt->execute([$periodId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createAdjustment(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO payroll_adjustments (period_id, employee_id, type, amount, reason, created_by)
            VALUES (:period_id, :employee_id, :type, :amount, :reason, :created_by)
        ");
        $stmt->execute([
            ':period_id'   => (int)$data['period_id'],
            ':employee_id' => (int)$data['employee_id'],
            ':type'        => $data['type'],
            ':amount'      => (float)$data['amount'],
            ':reason'      => $data['reason'],
            ':created_by'  => (int)$data['created_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function approveAdjustment(int $id, int $userId, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE payroll_adjustments SET status = :status, approved_by = :user_id, approved_at = NOW()
            WHERE id = :id AND status = 'pending'
        ");
        return $stmt->execute([':status' => $status, ':user_id' => $userId, ':id' => $id]);
    }

    // ─── Reporting ─────────────────────────────────────────────────────────────

    public function getPayrollSummary(int $periodId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_employees,
                SUM(gross_earnings) AS total_gross,
                SUM(total_deductions) AS total_deductions,
                SUM(net_salary) AS total_net,
                SUM(present_days) AS total_present,
                SUM(absent_days) AS total_absent,
                SUM(overtime_hours) AS total_overtime
            FROM payroll_slips
            WHERE period_id = ?
        ");
        $stmt->execute([$periodId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDepartmentPayrollSummary(int $periodId): array
    {
        $stmt = $this->db->prepare("
            SELECT d.name AS department_name,
                   COUNT(ps.id) AS employees,
                   SUM(ps.net_salary) AS total_net
            FROM payroll_slips ps
            JOIN employees e ON e.id = ps.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE ps.period_id = ?
            GROUP BY d.id
            ORDER BY total_net DESC
        ");
        $stmt->execute([$periodId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPeriods(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM payroll_periods")->fetchColumn();
    }
}
