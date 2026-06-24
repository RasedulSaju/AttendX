<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class AnalyticsModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ─── Workforce Overview ─────────────────────────────────────────────────────

    public function getWorkforceSummary(): array
    {
        return $this->db->query("
            SELECT
                COUNT(*) AS total_employees,
                SUM(status = 'active') AS active,
                SUM(status = 'inactive') AS inactive,
                SUM(status = 'terminated') AS terminated,
                SUM(gender = 'male') AS male,
                SUM(gender = 'female') AS female,
                AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) AS avg_age,
                SUM(TIMESTAMPDIFF(YEAR, join_date, CURDATE()) < 1) AS joined_this_year
            FROM employees
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getHeadcountByDepartment(): array
    {
        return $this->db->query("
            SELECT d.name AS department, COUNT(e.id) AS headcount,
                   SUM(e.gender = 'male') AS male, SUM(e.gender = 'female') AS female
            FROM departments d
            LEFT JOIN employees e ON e.department_id = d.id AND e.status = 'active'
            GROUP BY d.id
            ORDER BY headcount DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHeadcountTrend(int $months = 12): array
    {
        $stmt = $this->db->prepare("
            SELECT DATE_FORMAT(join_date,'%Y-%m') AS month, COUNT(*) AS joined
            FROM employees
            WHERE join_date >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
            GROUP BY month
            ORDER BY month
        ");
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTurnoverRate(int $year): array
    {
        $stmt = $this->db->prepare("
            SELECT
                MONTH(updated_at) AS month,
                COUNT(*) AS terminated
            FROM employees
            WHERE status = 'terminated'
              AND YEAR(updated_at) = :year
            GROUP BY month
            ORDER BY month
        ");
        $stmt->execute([':year' => $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Attendance Analytics ───────────────────────────────────────────────────

    public function getAttendanceSummaryByPeriod(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT ar.employee_id) AS employees_tracked,
                SUM(ar.status = 'present') AS present,
                SUM(ar.status = 'absent') AS absent,
                SUM(ar.status = 'late') AS late,
                SUM(ar.status = 'half_day') AS half_day,
                SUM(ar.status = 'on_leave') AS on_leave,
                ROUND(SUM(ar.status = 'present') / COUNT(*) * 100, 2) AS attendance_rate,
                AVG(ar.overtime_minutes) AS avg_overtime_minutes
            FROM attendance_records ar
            WHERE ar.attendance_date BETWEEN :from AND :to
        ");
        $stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDailyAttendanceTrend(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ar.attendance_date AS date,
                COUNT(*) AS total,
                SUM(ar.status = 'present') AS present,
                SUM(ar.status = 'absent') AS absent,
                SUM(ar.status = 'late') AS late,
                ROUND(SUM(ar.status IN ('present','late')) / COUNT(*) * 100, 2) AS rate
            FROM attendance_records ar
            WHERE ar.attendance_date BETWEEN :from AND :to
            GROUP BY ar.attendance_date
            ORDER BY ar.attendance_date
        ");
        $stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAttendanceByDepartment(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare("
            SELECT
                d.name AS department,
                COUNT(*) AS total_records,
                SUM(ar.status = 'present') AS present,
                SUM(ar.status = 'absent') AS absent,
                SUM(ar.status = 'late') AS late,
                ROUND(SUM(ar.status IN ('present','late')) / COUNT(*) * 100, 2) AS rate
            FROM attendance_records ar
            JOIN employees e ON e.id = ar.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE ar.attendance_date BETWEEN :from AND :to
            GROUP BY d.id
            ORDER BY rate DESC
        ");
        $stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLateArrivalsTopEmployees(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT e.employee_code, e.full_name, d.name AS department,
                   COUNT(*) AS late_count,
                   AVG(ar.late_minutes) AS avg_late_minutes,
                   SUM(ar.late_minutes) AS total_late_minutes
            FROM attendance_records ar
            JOIN employees e ON e.id = ar.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE ar.attendance_date BETWEEN :from AND :to
              AND ar.late_minutes > 0
            GROUP BY e.id
            ORDER BY late_count DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':from', $dateFrom);
        $stmt->bindValue(':to', $dateTo);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOvertimeSummary(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare("
            SELECT
                d.name AS department,
                COUNT(DISTINCT ar.employee_id) AS employees,
                SUM(ar.overtime_minutes) AS total_overtime_minutes,
                AVG(ar.overtime_minutes) AS avg_overtime_minutes
            FROM attendance_records ar
            JOIN employees e ON e.id = ar.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE ar.attendance_date BETWEEN :from AND :to
              AND ar.overtime_minutes > 0
            GROUP BY d.id
            ORDER BY total_overtime_minutes DESC
        ");
        $stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Leave Analytics ────────────────────────────────────────────────────────

    public function getLeaveSummaryByType(int $year): array
    {
        $stmt = $this->db->prepare("
            SELECT lt.name AS leave_type,
                   COUNT(la.id) AS applications,
                   SUM(la.total_days) AS total_days,
                   SUM(la.status = 'approved') AS approved,
                   SUM(la.status = 'rejected') AS rejected,
                   SUM(la.status = 'pending') AS pending
            FROM leave_types lt
            LEFT JOIN leave_applications la ON la.leave_type_id = lt.id
                AND YEAR(la.from_date) = :year
            GROUP BY lt.id
            ORDER BY total_days DESC
        ");
        $stmt->execute([':year' => $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeaveUtilizationByDepartment(int $year): array
    {
        $stmt = $this->db->prepare("
            SELECT d.name AS department,
                   SUM(la.total_days) AS days_taken,
                   COUNT(DISTINCT la.employee_id) AS employees_on_leave
            FROM leave_applications la
            JOIN employees e ON e.id = la.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE YEAR(la.from_date) = :year AND la.status = 'approved'
            GROUP BY d.id
            ORDER BY days_taken DESC
        ");
        $stmt->execute([':year' => $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Device Analytics ───────────────────────────────────────────────────────

    public function getDeviceActivitySummary(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare("
            SELECT d.name AS device_name, d.ip_address,
                   COUNT(al.id) AS total_punches,
                   COUNT(DISTINCT DATE(al.punch_time)) AS active_days
            FROM devices d
            LEFT JOIN attendance_logs al ON al.device_id = d.id
                AND DATE(al.punch_time) BETWEEN :from AND :to
            GROUP BY d.id
            ORDER BY total_punches DESC
        ");
        $stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Payroll Analytics ──────────────────────────────────────────────────────

    public function getPayrollTrend(int $months = 6): array
    {
        $stmt = $this->db->prepare("
            SELECT pp.name AS period,
                   pp.period_from,
                   COUNT(ps.id) AS employees,
                   SUM(ps.gross_earnings) AS gross,
                   SUM(ps.total_deductions) AS deductions,
                   SUM(ps.net_salary) AS net
            FROM payroll_periods pp
            LEFT JOIN payroll_slips ps ON ps.period_id = pp.id
            WHERE pp.status IN ('approved','paid','closed')
            GROUP BY pp.id
            ORDER BY pp.period_from DESC
            LIMIT :months
        ");
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getSalaryDistribution(): array
    {
        return $this->db->query("
            SELECT
                CASE
                    WHEN es.basic_salary < 20000 THEN 'Under 20K'
                    WHEN es.basic_salary < 40000 THEN '20K-40K'
                    WHEN es.basic_salary < 60000 THEN '40K-60K'
                    WHEN es.basic_salary < 100000 THEN '60K-100K'
                    ELSE 'Above 100K'
                END AS bracket,
                COUNT(*) AS employees
            FROM employee_salary es
            WHERE es.effective_to IS NULL
            GROUP BY bracket
            ORDER BY MIN(es.basic_salary)
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── KPI Dashboard ──────────────────────────────────────────────────────────

    public function getKpiSnapshot(string $date): array
    {
        $monthStart = date('Y-m-01', strtotime($date));
        $monthEnd   = $date;

        // Monthly attendance rate
        $monthly = $this->getAttendanceSummaryByPeriod($monthStart, $monthEnd);

        // Today-specific punch counts (from processed records for $date)
        $todayStmt = $this->db->prepare("
            SELECT
                SUM(status = 'present')                 AS present_today,
                SUM(status = 'absent')                  AS absent_today,
                SUM(status = 'late')                    AS late_today,
                COUNT(*)                                AS total_today
            FROM attendance_records
            WHERE attendance_date = :date
        ");
        $todayStmt->execute([':date' => $date]);
        $today = $todayStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $workforce  = $this->getWorkforceSummary();

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS pending_leaves FROM leave_applications WHERE status = 'pending'"
        );
        $stmt->execute();
        $leaves = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS offline_devices FROM devices WHERE status = 'offline' AND is_active = 1"
        );
        $stmt->execute();
        $devices = $stmt->fetch(PDO::FETCH_ASSOC);

        // Attendance rate: if today records exist use today, otherwise use monthly
        $totalToday = (int)($today['total_today'] ?? 0);
        if ($totalToday > 0) {
            $presentAndLate = (int)($today['present_today'] ?? 0) + (int)($today['late_today'] ?? 0);
            $attendanceRate = round($presentAndLate / $totalToday * 100, 2);
        } else {
            $attendanceRate = $monthly['attendance_rate'] ?? 0;
        }

        return [
            'attendance_rate'  => $attendanceRate,
            'present_today'    => (int)($today['present_today'] ?? 0),
            'absent_today'     => (int)($today['absent_today']  ?? 0),
            'late_today'       => (int)($today['late_today']    ?? 0),
            'total_employees'  => (int)($workforce['total_employees'] ?? 0),
            'active_employees' => (int)($workforce['active']          ?? 0),
            'pending_leaves'   => (int)($leaves['pending_leaves']     ?? 0),
            'offline_devices'  => (int)($devices['offline_devices']   ?? 0),
        ];
    }
}
