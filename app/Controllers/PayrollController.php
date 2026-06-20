<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\CSRF;
use App\Models\PayrollModel;
use App\Models\EmployeeModel;
use App\Models\AttendanceModel;
use App\Models\LeaveModel;

class PayrollController extends Controller
{
    private PayrollModel   $payroll;
    private EmployeeModel  $employee;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('payroll');
        $this->payroll  = new PayrollModel();
        $this->employee = new EmployeeModel();
    }

    // ─── Periods (index) ────────────────────────────────────────────────────────

    public function index(): void
    {
        $page    = max(1, (int)Request::get('page', 1));
        $perPage = 15;
        $periods = $this->payroll->getAllPeriods($perPage, ($page - 1) * $perPage);
        $total   = $this->payroll->countPeriods();

        $this->view('payroll/index', [
            'title'      => 'Payroll Management',
            'periods'    => $periods,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'csrf_token' => CSRF::generate(),
        ]);
    }

    // ─── Create Period ───────────────────────────────────────────────────────────

    public function create(): void
    {
        if (Request::isPost()) {
            CSRF::verify();
            $data = $this->validatePeriod(Request::post());
            if ($data === false) {
                Session::flash('error', 'Invalid period data.');
                Response::redirect('/payroll/create');
                return;
            }
            $data['created_by'] = $this->auth->id();
            $id = $this->payroll->createPeriod($data);
            Session::flash('success', 'Payroll period created.');
            Response::redirect('/payroll/process/' . $id);
            return;
        }

        $this->view('payroll/create', [
            'title'      => 'Create Payroll Period',
            'csrf_token' => CSRF::generate(),
        ]);
    }

    // ─── Process ─────────────────────────────────────────────────────────────────

    public function process(int $id): void
    {
        $period = $this->payroll->getPeriodById($id);
        if (!$period) {
            Session::flash('error', 'Period not found.');
            Response::redirect('/payroll');
            return;
        }

        if (Request::isPost()) {
            CSRF::verify();
            $this->requirePermission('payroll.process');
            $this->runPayrollCalculation($period);
            Session::flash('success', 'Payroll processed successfully.');
            Response::redirect('/payroll/view/' . $id);
            return;
        }

        $slips       = $this->payroll->getSlipsByPeriod($id);
        $adjustments = $this->payroll->getAdjustmentsByPeriod($id);
        $summary     = $this->payroll->getPayrollSummary($id);
        $deptSummary = $this->payroll->getDepartmentPayrollSummary($id);

        $this->view('payroll/process', [
            'title'       => 'Process Payroll: ' . $period['name'],
            'period'      => $period,
            'slips'       => $slips,
            'adjustments' => $adjustments,
            'summary'     => $summary,
            'deptSummary' => $deptSummary,
            'csrf_token'  => CSRF::generate(),
        ]);
    }

    // ─── View Period ─────────────────────────────────────────────────────────────

    public function view(int $id): void
    {
        $period = $this->payroll->getPeriodById($id);
        if (!$period) {
            Session::flash('error', 'Period not found.');
            Response::redirect('/payroll');
            return;
        }

        $slips       = $this->payroll->getSlipsByPeriod($id);
        $summary     = $this->payroll->getPayrollSummary($id);
        $deptSummary = $this->payroll->getDepartmentPayrollSummary($id);

        $this->view('payroll/view', [
            'title'       => 'Payroll: ' . $period['name'],
            'period'      => $period,
            'slips'       => $slips,
            'summary'     => $summary,
            'deptSummary' => $deptSummary,
            'csrf_token'  => CSRF::generate(),
        ]);
    }

    // ─── Slip Detail ─────────────────────────────────────────────────────────────

    public function slipDetail(int $slipId): void
    {
        $slip = $this->payroll->getSlipById($slipId);
        if (!$slip) {
            Session::flash('error', 'Payslip not found.');
            Response::redirect('/payroll');
            return;
        }

        $components = $this->payroll->getSlipComponents($slipId);
        $earnings   = array_filter($components, fn($c) => $c['type'] === 'earning');
        $deductions = array_filter($components, fn($c) => $c['type'] === 'deduction');

        $this->view('payroll/detail', [
            'title'      => 'Payslip #' . $slipId,
            'slip'       => $slip,
            'earnings'   => array_values($earnings),
            'deductions' => array_values($deductions),
        ]);
    }

    // ─── Approve ─────────────────────────────────────────────────────────────────

    public function approve(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('payroll.approve');
        $period = $this->payroll->getPeriodById($id);
        if (!$period || $period['status'] !== 'processed') {
            Session::flash('error', 'Period cannot be approved in current state.');
            Response::redirect('/payroll/view/' . $id);
            return;
        }
        $this->payroll->updatePeriodStatus($id, 'approved', $this->auth->id());
        Session::flash('success', 'Payroll period approved.');
        Response::redirect('/payroll/view/' . $id);
    }

    // ─── Mark Paid ───────────────────────────────────────────────────────────────

    public function markPaid(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('payroll.approve');
        $this->payroll->updatePeriodStatus($id, 'paid', $this->auth->id());
        Session::flash('success', 'Payroll marked as paid.');
        Response::redirect('/payroll/view/' . $id);
    }

    // ─── Adjustments ─────────────────────────────────────────────────────────────

    public function addAdjustment(): void
    {
        if (!Request::isPost()) {
            Response::json(['success' => false], 405);
            return;
        }
        CSRF::verify();
        $data = Request::post();

        $rules = [
            'period_id'   => 'required|integer',
            'employee_id' => 'required|integer',
            'type'        => 'required|in:bonus,deduction,advance,reimbursement',
            'amount'      => 'required|numeric|min:0.01',
            'reason'      => 'required|min:5|max:500',
        ];
        if (!$this->validate($data, $rules)) {
            Response::json(['success' => false, 'message' => $this->getValidationErrors()], 422);
            return;
        }

        $data['created_by'] = $this->auth->id();
        $this->payroll->createAdjustment($data);
        Response::json(['success' => true, 'message' => 'Adjustment added.']);
    }

    public function approveAdjustment(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('payroll.approve');
        $status = Request::post('status', 'approved');
        if (!in_array($status, ['approved', 'rejected'])) {
            Response::json(['success' => false], 422);
            return;
        }
        $this->payroll->approveAdjustment($id, $this->auth->id(), $status);
        Response::json(['success' => true]);
    }

    // ─── Structures ──────────────────────────────────────────────────────────────

    public function structures(): void
    {
        $this->view('payroll/structures', [
            'title'      => 'Salary Structures',
            'structures' => $this->payroll->getAllStructures(),
            'components' => $this->payroll->getAllComponents(true),
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function saveStructure(): void
    {
        if (!Request::isPost()) {
            Response::redirect('/payroll/structures');
            return;
        }
        CSRF::verify();
        $this->requirePermission('payroll.manage');

        $data       = Request::post();
        $components = Request::post('components', []);

        $rules = [
            'name' => 'required|min:2|max:150',
        ];
        if (!$this->validate($data, $rules)) {
            Session::flash('error', $this->getValidationErrors());
            Response::redirect('/payroll/structures');
            return;
        }

        $data['created_by'] = $this->auth->id();
        $this->payroll->createStructure($data, $components);
        Session::flash('success', 'Salary structure saved.');
        Response::redirect('/payroll/structures');
    }

    // ─── Components ──────────────────────────────────────────────────────────────

    public function components(): void
    {
        $this->view('payroll/components', [
            'title'      => 'Payroll Components',
            'components' => $this->payroll->getAllComponents(),
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function saveComponent(): void
    {
        if (!Request::isPost()) {
            Response::redirect('/payroll/components');
            return;
        }
        CSRF::verify();
        $this->requirePermission('payroll.manage');

        $data  = Request::post();
        $rules = [
            'name'        => 'required|min:2|max:100',
            'code'        => 'required|min:2|max:30',
            'type'        => 'required|in:earning,deduction,benefit',
            'calculation' => 'required|in:fixed,percentage,formula',
        ];
        if (!$this->validate($data, $rules)) {
            Session::flash('error', $this->getValidationErrors());
            Response::redirect('/payroll/components');
            return;
        }

        $id = !empty($data['id']) ? (int)$data['id'] : null;
        if ($id) {
            $this->payroll->updateComponent($id, $data);
        } else {
            $this->payroll->createComponent($data);
        }
        Session::flash('success', 'Component saved.');
        Response::redirect('/payroll/components');
    }

    // ─── Employee Salary Assignment ──────────────────────────────────────────────

    public function assignSalary(): void
    {
        if (Request::isPost()) {
            CSRF::verify();
            $this->requirePermission('payroll.manage');
            $data = Request::post();
            $rules = [
                'employee_id'    => 'required|integer',
                'structure_id'   => 'required|integer',
                'effective_from' => 'required|date',
                'basic_salary'   => 'required|numeric|min:0',
            ];
            if (!$this->validate($data, $rules)) {
                Session::flash('error', $this->getValidationErrors());
                Response::redirect('/payroll/salary');
                return;
            }
            $data['close_existing'] = true;
            $data['created_by']     = $this->auth->id();
            $this->payroll->assignSalary($data);
            Session::flash('success', 'Salary assigned successfully.');
            Response::redirect('/payroll/salary');
            return;
        }

        $employees  = $this->employee->getAll(['status' => 'active']);
        $structures = $this->payroll->getAllStructures();

        $this->view('payroll/salary', [
            'title'      => 'Salary Assignment',
            'employees'  => $employees,
            'structures' => $structures,
            'csrf_token' => CSRF::generate(),
        ]);
    }

    // ─── Private: Payroll Calculation ────────────────────────────────────────────

    private function runPayrollCalculation(array $period): void
    {
        $this->payroll->updatePeriodStatus($period['id'], 'processing', $this->auth->id());

        $employees     = $this->employee->getAll(['status' => 'active']);
        $attendanceModel = new AttendanceModel();
        $leaveModel    = new LeaveModel();

        $workingDays = $this->calculateWorkingDays($period['period_from'], $period['period_to']);

        foreach ($employees as $emp) {
            $salary = $this->payroll->getEmployeeCurrentSalary($emp['id']);
            if (!$salary) {
                continue; // Skip employees without salary
            }

            $structure  = $this->payroll->getStructureById($salary['structure_id']);
            $components = $this->payroll->getStructureComponents($salary['structure_id']);

            // Attendance stats
            $stats = $attendanceModel->getAttendanceStats(
                $emp['id'],
                $period['period_from'],
                $period['period_to']
            );

            $presentDays   = (float)($stats['present'] ?? 0);
            $leaveDays     = (float)($leaveModel->getApprovedLeaveDays($emp['id'], $period['period_from'], $period['period_to']) ?? 0);
            $absentDays    = max(0, $workingDays - $presentDays - $leaveDays);
            $lateDays      = (float)($stats['late'] ?? 0);
            $overtimeHours = round(($stats['overtime_minutes'] ?? 0) / 60, 2);

            // Calculate component amounts
            $slipComponents = [];
            $grossEarnings  = 0;
            $totalDeductions = 0;

            $basicSalary = (float)$salary['basic_salary'];
            $perDayBasic = $workingDays > 0 ? $basicSalary / $workingDays : 0;

            foreach ($components as $comp) {
                $amount = 0;

                if ($comp['calculation'] === 'fixed') {
                    $amount = (float)$comp['amount'];
                } elseif ($comp['calculation'] === 'percentage') {
                    $base   = $comp['base_component_id'] ? $this->getBaseAmount($slipComponents, $comp['base_component_id']) : $basicSalary;
                    $amount = $base * ((float)$comp['percentage'] / 100);
                } elseif ($comp['calculation'] === 'formula') {
                    $amount = $this->calculateFormula($comp['code'], [
                        'basic'          => $basicSalary,
                        'per_day'        => $perDayBasic,
                        'absent_days'    => $absentDays,
                        'late_days'      => $lateDays,
                        'overtime_hours' => $overtimeHours,
                    ]);
                }

                if ($comp['type'] === 'deduction') {
                    $totalDeductions += $amount;
                } else {
                    $grossEarnings += $amount;
                }

                $slipComponents[] = [
                    'component_id' => $comp['component_id'],
                    'amount'       => round($amount, 2),
                ];
            }

            // Add adjustments
            $adjustments = $this->payroll->getAdjustmentsByPeriod($period['id']);
            foreach ($adjustments as $adj) {
                if ($adj['employee_id'] != $emp['id'] || $adj['status'] !== 'approved') {
                    continue;
                }
                if (in_array($adj['type'], ['bonus', 'reimbursement'])) {
                    $grossEarnings += (float)$adj['amount'];
                } else {
                    $totalDeductions += (float)$adj['amount'];
                }
            }

            $netSalary = max(0, $grossEarnings - $totalDeductions);

            $slipId = $this->payroll->upsertSlip([
                'period_id'        => $period['id'],
                'employee_id'      => $emp['id'],
                'salary_id'        => $salary['id'],
                'working_days'     => $workingDays,
                'present_days'     => $presentDays,
                'absent_days'      => $absentDays,
                'late_days'        => $lateDays,
                'overtime_hours'   => $overtimeHours,
                'leave_days'       => $leaveDays,
                'gross_earnings'   => round($grossEarnings, 2),
                'total_deductions' => round($totalDeductions, 2),
                'net_salary'       => round($netSalary, 2),
                'status'           => 'draft',
            ]);

            $this->payroll->saveSlipComponents($slipId, $slipComponents);
        }

        $this->payroll->updatePeriodStatus($period['id'], 'processed', $this->auth->id());
    }

    private function calculateWorkingDays(string $from, string $to): int
    {
        $start = new \DateTime($from);
        $end   = new \DateTime($to);
        $days  = 0;
        while ($start <= $end) {
            $dow = (int)$start->format('N');
            if ($dow <= 5) { // Mon-Fri
                $days++;
            }
            $start->modify('+1 day');
        }
        return $days;
    }

    private function getBaseAmount(array $components, int $baseComponentId): float
    {
        foreach ($components as $c) {
            if ($c['component_id'] == $baseComponentId) {
                return (float)$c['amount'];
            }
        }
        return 0.0;
    }

    private function calculateFormula(string $code, array $vars): float
    {
        return match (strtoupper($code)) {
            'BASIC'  => (float)$vars['basic'],
            'OT'     => round($vars['overtime_hours'] * ($vars['basic'] / 208), 2), // 26 days * 8hrs
            'PF'     => round($vars['basic'] * 0.05, 2),
            'IT'     => $this->calculateIncomeTax((float)$vars['basic'] * 12) / 12,
            'LATE'   => round($vars['per_day'] * $vars['late_days'] * 0.5, 2),
            'ABSENT' => round($vars['per_day'] * $vars['absent_days'], 2),
            default  => 0.0,
        };
    }

    private function calculateIncomeTax(float $annualIncome): float
    {
        // Bangladesh tax slabs (simplified)
        if ($annualIncome <= 350000) return 0;
        if ($annualIncome <= 450000) return ($annualIncome - 350000) * 0.05;
        if ($annualIncome <= 750000) return 5000 + ($annualIncome - 450000) * 0.10;
        if ($annualIncome <= 1150000) return 35000 + ($annualIncome - 750000) * 0.15;
        if ($annualIncome <= 1650000) return 95000 + ($annualIncome - 1150000) * 0.20;
        return 195000 + ($annualIncome - 1650000) * 0.25;
    }

    // ─── Validation Helper ───────────────────────────────────────────────────────

    private function validatePeriod(array $data): array|false
    {
        $rules = [
            'name'        => 'required|min:3|max:100',
            'period_from' => 'required|date',
            'period_to'   => 'required|date',
        ];
        if (!$this->validate($data, $rules)) {
            return false;
        }
        if ($data['period_from'] > $data['period_to']) {
            return false;
        }
        return [
            'name'        => strip_tags($data['name']),
            'period_from' => $data['period_from'],
            'period_to'   => $data['period_to'],
            'notes'       => isset($data['notes']) ? strip_tags($data['notes']) : null,
        ];
    }
}
