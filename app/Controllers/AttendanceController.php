<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\CSRF;
use App\Core\AuditLogger;
use App\Models\AttendanceModel;
use App\Models\EmployeeModel;
use App\Models\ShiftModel;
use App\Models\DepartmentModel;

class AttendanceController extends Controller
{
    private AttendanceModel $attendance;
    private EmployeeModel   $employee;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('attendance');
        $this->attendance = new AttendanceModel();
        $this->employee   = new EmployeeModel();
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $page    = max(1, (int)Request::get('page', 1));
        $perPage = 25;
        $filters = array_filter([
            'date_from'     => Request::get('date_from', date('Y-m-d')),
            'date_to'       => Request::get('date_to',   date('Y-m-d')),
            'department_id' => (int)Request::get('department_id'),
            'employee_id'   => (int)Request::get('employee_id'),
            'status'        => Request::get('status', ''),
        ], fn($v) => $v !== '' && $v !== 0);

        $records = $this->attendance->getRecords($filters, $perPage, ($page - 1) * $perPage);
        $total   = $this->attendance->countRecords($filters);
        $depts   = (new DepartmentModel())->getAll(true);

        $this->view('attendance/index', [
            'title'      => 'Attendance',
            'records'    => $records,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'filters'    => $filters,
            'depts'      => $depts,
            'csrf_token' => CSRF::generate(),
        ]);
    }

    // ─── Process Attendance ───────────────────────────────────────────────────

    public function process(): void
    {
        $this->requirePermission('attendance.process');

        if (Request::isPost()) {
            CSRF::verify();
            $date = Request::post('date', date('Y-m-d'));
            if (!\DateTime::createFromFormat('Y-m-d', $date)) {
                Session::flash('error', 'Invalid date.');
                Response::redirect('/attendance/process');
                return;
            }
            $this->runProcessing($date);
            Session::flash('success', "Attendance processed for $date.");
            Response::redirect('/attendance?date_from=' . $date . '&date_to=' . $date);
            return;
        }

        $pending = $this->attendance->getPendingQueue();
        $this->view('attendance/process', [
            'title'      => 'Process Attendance',
            'pending'    => $pending,
            'csrf_token' => CSRF::generate(),
        ]);
    }

    // ─── Employee Attendance Detail ───────────────────────────────────────────

    public function employeeAttendance(int $id): void
    {
        $emp = $this->employee->getById($id);
        if (!$emp) Response::abort(404);

        $dateFrom = Request::get('date_from', date('Y-m-01'));
        $dateTo   = Request::get('date_to',   date('Y-m-d'));
        $records  = $this->attendance->getRecords(['employee_id' => $id, 'date_from' => $dateFrom, 'date_to' => $dateTo], 100, 0);
        $stats    = $this->attendance->getAttendanceStats($id, $dateFrom, $dateTo);
        $punches  = $this->attendance->getPunchLogs($id, date('Y-m-d'));

        $this->view('attendance/employee', [
            'title'    => $emp['full_name'] . ' – Attendance',
            'emp'      => $emp,
            'records'  => $records,
            'stats'    => $stats,
            'punches'  => $punches,
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
            'csrf_token' => CSRF::generate(),
        ]);
    }

    // ─── Manual Punch ────────────────────────────────────────────────────────

    public function manualPunch(): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $this->requirePermission('attendance.edit');

        $data  = Request::post();
        $rules = [
            'employee_id' => 'required|integer',
            'punch_time'  => 'required',
            'punch_type'  => 'required|in:in,out',
        ];
        if (!$this->validate($data, $rules)) {
            Response::json(['success' => false, 'message' => $this->getValidationErrors()], 422);
            return;
        }

        $emp = $this->employee->getById((int)$data['employee_id']);
        if (!$emp) { Response::json(['success' => false, 'message' => 'Employee not found.'], 404); return; }

        $logId = $this->attendance->insertManualPunch([
            'employee_id'   => (int)$data['employee_id'],
            'punch_time'    => $data['punch_time'],
            'punch_type'    => $data['punch_type'],
            'punch_type_int'=> $data['punch_type'] === 'out' ? 1 : 0,
            'badge_number'  => $emp['badge_number'],
            'source'        => 'manual',
            'note'          => $data['note'] ?? null,
        ]);

        AuditLogger::log('attendance', 'manual_punch', "Manual {$data['punch_type']} for {$emp['full_name']} at {$data['punch_time']}");
        Response::json(['success' => true, 'log_id' => $logId]);
    }

    // ─── Edit Record ──────────────────────────────────────────────────────────

    public function editRecord(int $id): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $this->requirePermission('attendance.edit');

        $data = Request::post();
        $allowed = ['status','time_in','time_out','work_minutes','late_minutes','overtime_minutes','note'];
        $update  = array_intersect_key($data, array_flip($allowed));

        if (empty($update)) { Response::json(['success' => false, 'message' => 'Nothing to update.'], 422); return; }

        $this->attendance->updateRecord($id, $update);
        AuditLogger::log('attendance', 'record_edited', "Attendance record #$id manually edited.");
        Response::json(['success' => true]);
    }

    // ─── Delete Record ────────────────────────────────────────────────────────

    public function deleteRecord(int $id): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $this->requirePermission('attendance.edit');
        $this->attendance->deleteRecord($id);
        AuditLogger::log('attendance', 'record_deleted', "Attendance record #$id deleted.");
        Response::json(['success' => true]);
    }

    // ─── Export ───────────────────────────────────────────────────────────────

    public function export(): void
    {
        $this->requirePermission('reports.export');
        $filters = array_filter([
            'date_from'     => Request::get('date_from', date('Y-m-01')),
            'date_to'       => Request::get('date_to',   date('Y-m-d')),
            'department_id' => (int)Request::get('department_id'),
            'employee_id'   => (int)Request::get('employee_id'),
            'status'        => Request::get('status', ''),
        ], fn($v) => $v !== '' && $v !== 0);

        $records = $this->attendance->getRecords($filters, 5000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance_' . date('Ymd') . '.csv"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Employee Code','Employee Name','Department','Date','Status','Time In','Time Out','Work (min)','Late (min)','OT (min)']);
        foreach ($records as $r) {
            fputcsv($out, [
                $r['employee_code'] ?? '',
                $r['full_name']     ?? '',
                $r['department_name'] ?? '',
                $r['attendance_date'],
                $r['status'],
                $r['time_in']    ?? '',
                $r['time_out']   ?? '',
                $r['work_minutes'],
                $r['late_minutes'],
                $r['overtime_minutes'],
            ]);
        }
        fclose($out);
        exit;
    }

    // ─── Overtime ─────────────────────────────────────────────────────────────

    public function overtime(): void
    {
        $filters = array_filter([
            'date_from'   => Request::get('date_from', date('Y-m-01')),
            'date_to'     => Request::get('date_to',   date('Y-m-d')),
            'employee_id' => (int)Request::get('employee_id'),
        ], fn($v) => $v !== '' && $v !== 0);

        $records = $this->attendance->getOvertimeRecords($filters, 50, 0);

        $this->view('attendance/overtime', [
            'title'    => 'Overtime',
            'records'  => $records,
            'filters'  => $filters,
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function approveOvertime(int $id): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $db = \App\Core\Database::getInstance()->getConnection();
        $db->prepare("UPDATE overtime_applications SET status='approved', approved_by=?, approved_at=NOW() WHERE id=? AND status='pending'")
           ->execute([$this->auth->id(), $id]);
        Response::json(['success' => true]);
    }

    // ─── Private: Process ────────────────────────────────────────────────────

    private function runProcessing(string $date): void
    {
        $shiftModel   = new ShiftModel();
        $employees    = $this->employee->getAll(['status' => 'active']);
        $dayCfg       = $this->loadConfig();

        foreach ($employees as $emp) {
            $shift     = $shiftModel->getEmployeeShift($emp['id'], $date);
            $punchLogs = $this->attendance->getPunchLogs($emp['id'], $date);

            if (empty($punchLogs)) {
                $this->attendance->upsertRecord([
                    'employee_id'    => $emp['id'],
                    'attendance_date'=> $date,
                    'shift_id'       => $shift['id'] ?? null,
                    'status'         => 'absent',
                    'work_minutes'   => 0,
                    'late_minutes'   => 0,
                    'overtime_minutes'=> 0,
                ]);
                continue;
            }

            $timeIn  = null;
            $timeOut = null;
            foreach ($punchLogs as $log) {
                $pt = (int)$log['punch_type'];
                if ($pt === 0 && !$timeIn)  $timeIn  = $log['punch_time'];
                if ($pt === 1)               $timeOut = $log['punch_time'];
            }
            if (!$timeIn) $timeIn = $punchLogs[0]['punch_time'];
            if (!$timeOut && count($punchLogs) > 1) $timeOut = end($punchLogs)['punch_time'];

            $workMin  = 0;
            $lateMin  = 0;
            $otMin    = 0;
            $status   = 'present';

            if ($shift) {
                $shiftStart = strtotime($date . ' ' . $shift['start_time']);
                $punchIn    = strtotime($timeIn);
                $grace      = (int)($shift['grace_minutes'] ?? $dayCfg['late_grace']);

                $lateMin = max(0, (int)(($punchIn - $shiftStart) / 60) - $grace);
                if ($lateMin > 0) $status = 'late';

                if ($timeOut) {
                    $workMin = max(0, (int)((strtotime($timeOut) - $punchIn) / 60));
                    $halfDay = (int)($shift['half_day_minutes'] ?? $dayCfg['half_day_min']);
                    $minWork = (int)($shift['work_minutes'] ?? $dayCfg['min_work']);
                    if ($workMin > 0 && $workMin < $halfDay) $status = 'half_day';

                    $shiftEnd = strtotime($date . ' ' . $shift['end_time']);
                    $otThresh = (int)($shift['ot_threshold'] ?? $dayCfg['ot_threshold']);
                    $otMin    = max(0, (int)((strtotime($timeOut) - $shiftEnd) / 60) - $otThresh);
                }
            } elseif ($timeOut) {
                $workMin = max(0, (int)((strtotime($timeOut) - strtotime($timeIn)) / 60));
            }

            $this->attendance->upsertRecord([
                'employee_id'     => $emp['id'],
                'attendance_date' => $date,
                'shift_id'        => $shift['id'] ?? null,
                'status'          => $status,
                'time_in'         => date('H:i:s', strtotime($timeIn)),
                'time_out'        => $timeOut ? date('H:i:s', strtotime($timeOut)) : null,
                'work_minutes'    => $workMin,
                'late_minutes'    => $lateMin,
                'overtime_minutes'=> max(0, $otMin),
            ]);
        }

        $queue = $this->attendance->getPendingQueue();
        foreach ($queue as $q) {
            $this->attendance->markQueueProcessed($q['device_id']);
        }
    }

    private function loadConfig(): array
    {
        $db   = \App\Core\Database::getInstance()->getConnection();
        $rows = $db->query("SELECT `key`,`value` FROM system_settings WHERE `group`='attendance'")->fetchAll(\PDO::FETCH_KEY_PAIR);
        return [
            'late_grace'    => (int)($rows['attendance.late_grace_minutes']    ?? 15),
            'ot_threshold'  => (int)($rows['attendance.ot_threshold_minutes']  ?? 30),
            'min_work'      => (int)(($rows['attendance.min_work_hours']       ?? 8) * 60),
            'half_day_min'  => (int)(($rows['attendance.half_day_hours']       ?? 4) * 60),
        ];
    }
}
