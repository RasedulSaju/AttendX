<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\AnalyticsModel;

class AnalyticsController extends Controller
{
    private AnalyticsModel $analytics;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('reports');
        $this->analytics = new AnalyticsModel();
    }

    // ─── Main Dashboard ──────────────────────────────────────────────────────────

    public function dashboard(): void
    {
        $today     = date('Y-m-d');
        $monthFrom = date('Y-m-01');

        $kpi        = $this->analytics->getKpiSnapshot($today);
        $deptData   = $this->analytics->getAttendanceByDepartment($monthFrom, $today);
        $trend      = $this->analytics->getDailyAttendanceTrend(
            date('Y-m-d', strtotime('-29 days')),
            $today
        );
        $payrollTrend = $this->analytics->getPayrollTrend(6);

        $this->view('analytics/dashboard', [
            'title'        => 'Analytics Dashboard',
            'kpi'          => $kpi,
            'deptData'     => json_encode($deptData),
            'trend'        => json_encode($trend),
            'payrollTrend' => json_encode($payrollTrend),
        ]);
    }

    // ─── Workforce ───────────────────────────────────────────────────────────────

    public function workforce(): void
    {
        $year     = (int)Request::get('year', date('Y'));
        $summary  = $this->analytics->getWorkforceSummary();
        $byDept   = $this->analytics->getHeadcountByDepartment();
        $trend    = $this->analytics->getHeadcountTrend(12);
        $turnover = $this->analytics->getTurnoverRate($year);
        $salaryDist = $this->analytics->getSalaryDistribution();

        $this->view('analytics/workforce', [
            'title'      => 'Workforce Analytics',
            'year'       => $year,
            'summary'    => $summary,
            'byDept'     => json_encode($byDept),
            'trend'      => json_encode($trend),
            'turnover'   => json_encode($turnover),
            'salaryDist' => json_encode($salaryDist),
        ]);
    }

    // ─── Attendance ──────────────────────────────────────────────────────────────

    public function attendance(): void
    {
        $dateFrom = Request::get('date_from', date('Y-m-01'));
        $dateTo   = Request::get('date_to', date('Y-m-d'));

        $summary    = $this->analytics->getAttendanceSummaryByPeriod($dateFrom, $dateTo);
        $daily      = $this->analytics->getDailyAttendanceTrend($dateFrom, $dateTo);
        $byDept     = $this->analytics->getAttendanceByDepartment($dateFrom, $dateTo);
        $lateTop    = $this->analytics->getLateArrivalsTopEmployees($dateFrom, $dateTo, 10);
        $overtime   = $this->analytics->getOvertimeSummary($dateFrom, $dateTo);

        $this->view('analytics/attendance', [
            'title'     => 'Attendance Analytics',
            'dateFrom'  => $dateFrom,
            'dateTo'    => $dateTo,
            'summary'   => $summary,
            'daily'     => json_encode($daily),
            'byDept'    => json_encode($byDept),
            'lateTop'   => $lateTop,
            'overtime'  => json_encode($overtime),
        ]);
    }

    // ─── Leave ───────────────────────────────────────────────────────────────────

    public function leave(): void
    {
        $year      = (int)Request::get('year', date('Y'));
        $byType    = $this->analytics->getLeaveSummaryByType($year);
        $byDept    = $this->analytics->getLeaveUtilizationByDepartment($year);

        $this->view('analytics/leave', [
            'title'  => 'Leave Analytics',
            'year'   => $year,
            'byType' => json_encode($byType),
            'byDept' => json_encode($byDept),
        ]);
    }

    // ─── Payroll ─────────────────────────────────────────────────────────────────

    public function payroll(): void
    {
        $trend    = $this->analytics->getPayrollTrend(12);
        $salDist  = $this->analytics->getSalaryDistribution();

        $this->view('analytics/payroll', [
            'title'   => 'Payroll Analytics',
            'trend'   => json_encode($trend),
            'salDist' => json_encode($salDist),
        ]);
    }

    // ─── Device Activity ─────────────────────────────────────────────────────────

    public function devices(): void
    {
        $dateFrom   = Request::get('date_from', date('Y-m-01'));
        $dateTo     = Request::get('date_to', date('Y-m-d'));
        $deviceData = $this->analytics->getDeviceActivitySummary($dateFrom, $dateTo);

        $this->view('analytics/devices', [
            'title'      => 'Device Activity Analytics',
            'dateFrom'   => $dateFrom,
            'dateTo'     => $dateTo,
            'deviceData' => $deviceData,
        ]);
    }

    // ─── JSON Data Endpoints ──────────────────────────────────────────────────────

    public function kpiData(): void
    {
        $date = Request::get('date', date('Y-m-d'));
        Response::json($this->analytics->getKpiSnapshot($date));
    }

    public function attendanceTrend(): void
    {
        $from = Request::get('from', date('Y-m-d', strtotime('-29 days')));
        $to   = Request::get('to', date('Y-m-d'));
        Response::json($this->analytics->getDailyAttendanceTrend($from, $to));
    }

    public function departmentData(): void
    {
        $from = Request::get('from', date('Y-m-01'));
        $to   = Request::get('to', date('Y-m-d'));
        Response::json($this->analytics->getAttendanceByDepartment($from, $to));
    }

    public function payrollTrend(): void
    {
        $months = min(24, max(1, (int)Request::get('months', 6)));
        Response::json($this->analytics->getPayrollTrend($months));
    }
}
