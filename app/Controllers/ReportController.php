<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\AttendanceModel;
use App\Models\LeaveModel;
use App\Models\EmployeeModel;
use App\Models\DepartmentModel;
use App\Models\AnalyticsModel;

class ReportController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('reports');
    }

    // ─── Reports Hub ──────────────────────────────────────────────────────────

    public function index(): void
    {
        $analytics = new AnalyticsModel();
        $today     = date('Y-m-d');
        $monthFrom = date('Y-m-01');

        $this->view('reports/index', [
            'title'       => 'Reports',
            'kpi'         => $analytics->getKpiSnapshot($today),
            'deptData'    => json_encode($analytics->getAttendanceByDepartment($monthFrom, $today)),
        ]);
    }

    // ─── Attendance Report ────────────────────────────────────────────────────

    public function attendance(): void
    {
        $filters = $this->getFilters();
        $records = (new AttendanceModel())->getRecords($filters, 500, 0);
        $summary = (new AnalyticsModel())->getAttendanceSummaryByPeriod($filters['date_from'], $filters['date_to']);

        $this->view('reports/attendance', [
            'title'   => 'Attendance Report',
            'records' => $records,
            'summary' => $summary,
            'filters' => $filters,
            'depts'   => (new DepartmentModel())->getAll(true),
        ]);
    }

    public function exportAttendance(): void
    {
        $this->requirePermission('reports.export');
        $filters = $this->getFilters();
        $records = (new AttendanceModel())->getRecords($filters, 10000, 0);
        $this->exportCsv('attendance_' . date('Ymd'), ['Employee Code','Name','Department','Date','Status','Time In','Time Out','Work(min)','Late(min)','OT(min)'],
            array_map(fn($r) => [$r['employee_code']??'',$r['full_name']??'',$r['department_name']??'',$r['attendance_date'],$r['status'],$r['time_in']??'',$r['time_out']??'',$r['work_minutes'],$r['late_minutes'],$r['overtime_minutes']], $records));
    }

    // ─── Leave Report ─────────────────────────────────────────────────────────

    public function leave(): void
    {
        $year  = (int)Request::get('year', date('Y'));
        $model = new LeaveModel();
        $apps  = $model->getApplications(['year' => $year], 500, 0);
        $byType = $model->getLeaveBalance(0, $year); // placeholder

        $this->view('reports/leave', [
            'title'   => 'Leave Report',
            'apps'    => $apps,
            'year'    => $year,
            'depts'   => (new DepartmentModel())->getAll(true),
            'types'   => $model->getLeaveTypes(true),
        ]);
    }

    public function exportLeave(): void
    {
        $this->requirePermission('reports.export');
        $year  = (int)Request::get('year', date('Y'));
        $apps  = (new LeaveModel())->getApplications(['year' => $year], 10000, 0);
        $this->exportCsv('leave_' . $year, ['Employee Code','Name','Dept','Leave Type','From','To','Days','Status'],
            array_map(fn($a) => [$a['employee_code']??'',$a['full_name']??'',$a['department_name']??'',$a['leave_type_name']??'',$a['from_date'],$a['to_date'],$a['total_days'],$a['status']], $apps));
    }

    // ─── Payroll Report ───────────────────────────────────────────────────────

    public function payroll(): void
    {
        $db     = \App\Core\Database::getInstance()->getConnection();
        $periods = $db->query("
            SELECT pp.*, SUM(ps.net_salary) AS total_net, COUNT(ps.id) AS emp_count
            FROM payroll_periods pp
            LEFT JOIN payroll_slips ps ON ps.period_id = pp.id
            WHERE pp.status IN ('approved','paid','closed')
            GROUP BY pp.id ORDER BY pp.period_from DESC LIMIT 24
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('reports/payroll', [
            'title'   => 'Payroll Report',
            'periods' => $periods,
        ]);
    }

    public function exportPayroll(): void
    {
        $this->requirePermission('reports.export');
        $periodId = (int)Request::get('period_id');
        $db       = \App\Core\Database::getInstance()->getConnection();
        $slips    = $db->prepare("
            SELECT ps.*, e.employee_code, e.full_name, d.name AS dept
            FROM payroll_slips ps
            JOIN employees e ON e.id=ps.employee_id
            LEFT JOIN departments d ON d.id=e.department_id
            WHERE ps.period_id=?
        ");
        $slips->execute([$periodId]);
        $rows = $slips->fetchAll(\PDO::FETCH_ASSOC);
        $this->exportCsv('payroll_' . date('Ymd'), ['Code','Name','Dept','Working Days','Present','Absent','Leave','OT Hrs','Gross','Deductions','Net'],
            array_map(fn($r) => [$r['employee_code']??'',$r['full_name']??'',$r['dept']??'',$r['working_days'],$r['present_days'],$r['absent_days'],$r['leave_days'],$r['overtime_hours'],$r['gross_earnings'],$r['total_deductions'],$r['net_salary']], $rows));
    }

    // ─── Overtime Report ──────────────────────────────────────────────────────

    public function overtime(): void
    {
        $filters = $this->getFilters();
        $records = (new AttendanceModel())->getOvertimeRecords($filters, 200, 0);
        $this->view('reports/overtime', [
            'title'   => 'Overtime Report',
            'records' => $records,
            'filters' => $filters,
            'depts'   => (new DepartmentModel())->getAll(true),
        ]);
    }

    public function exportOvertime(): void
    {
        $this->requirePermission('reports.export');
        $records = (new AttendanceModel())->getOvertimeRecords($this->getFilters(), 5000, 0);
        $this->exportCsv('overtime_' . date('Ymd'), ['Code','Name','Dept','Date','OT Minutes'],
            array_map(fn($r) => [$r['employee_code']??'',$r['full_name']??'',$r['department_name']??'',$r['attendance_date'],$r['overtime_minutes']], $records));
    }

    // ─── Employee Report ──────────────────────────────────────────────────────

    public function employees(): void
    {
        $filters   = array_filter(['status' => Request::get('status','active'), 'department_id' => (int)Request::get('department_id')], fn($v) => $v !== '' && $v !== 0);
        $employees = (new EmployeeModel())->getAll($filters, 500, 0);
        $this->view('reports/employees', [
            'title'     => 'Employee Report',
            'employees' => $employees,
            'filters'   => $filters,
            'depts'     => (new DepartmentModel())->getAll(true),
        ]);
    }

    public function exportEmployees(): void
    {
        $this->requirePermission('reports.export');
        $rows = (new EmployeeModel())->getAll(['status' => Request::get('status','active')], 5000, 0);
        $this->exportCsv('employees_' . date('Ymd'), ['Code','Name','Email','Phone','Gender','DOB','Dept','Designation','Join Date','Status'],
            array_map(fn($r) => [$r['employee_code']??'',$r['full_name']??'',$r['email']??'',$r['phone']??'',$r['gender']??'',$r['date_of_birth']??'',$r['department_name']??'',$r['designation_name']??'',$r['join_date']??'',$r['status']??''], $rows));
    }

    // ─── Late Arrivals Report ─────────────────────────────────────────────────

    public function lateArrivals(): void
    {
        $filters  = $this->getFilters();
        $lateTop  = (new AnalyticsModel())->getLateArrivalsTopEmployees($filters['date_from'], $filters['date_to'], 50);
        $this->view('reports/late_arrivals', [
            'title'   => 'Late Arrivals Report',
            'records' => $lateTop,
            'filters' => $filters,
        ]);
    }

    public function exportLateArrivals(): void
    {
        $this->requirePermission('reports.export');
        $filters = $this->getFilters();
        $rows    = (new AnalyticsModel())->getLateArrivalsTopEmployees($filters['date_from'], $filters['date_to'], 1000);
        $this->exportCsv('late_arrivals_' . date('Ymd'), ['Code','Name','Dept','Late Count','Avg Late (min)','Total Late (min)'],
            array_map(fn($r) => [$r['employee_code']??'',$r['full_name']??'',$r['department']??'',$r['late_count'],round($r['avg_late_minutes'],1),$r['total_late_minutes']], $rows));
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function getFilters(): array
    {
        return array_filter([
            'date_from'     => Request::get('date_from', date('Y-m-01')),
            'date_to'       => Request::get('date_to',   date('Y-m-d')),
            'department_id' => (int)Request::get('department_id'),
            'employee_id'   => (int)Request::get('employee_id'),
            'status'        => Request::get('status', ''),
        ], fn($v) => $v !== '' && $v !== 0);
    }

    private function exportCsv(string $name, array $headers, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$name}.csv\"");
        header('Pragma: no-cache');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $row) fputcsv($out, $row);
        fclose($out);
        exit;
    }
}
