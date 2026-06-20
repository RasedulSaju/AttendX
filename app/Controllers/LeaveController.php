<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\CSRF;
use App\Core\Auth;
use App\Core\AuditLogger;
use App\Models\LeaveModel;
use App\Models\EmployeeModel;
use App\Models\DepartmentModel;
use App\Models\NotificationModel;

class LeaveController extends Controller
{
    private LeaveModel $leave;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('leave');
        $this->leave = new LeaveModel();
    }

    public function index(): void
    {
        $page    = max(1, (int)Request::get('page', 1));
        $perPage = 20;
        $filters = array_filter([
            'status'        => Request::get('status', ''),
            'department_id' => (int)Request::get('department_id'),
            'leave_type_id' => (int)Request::get('leave_type_id'),
            'year'          => (int)Request::get('year', date('Y')),
        ], fn($v) => $v !== '' && $v !== 0);

        $apps  = $this->leave->getApplications($filters, $perPage, ($page - 1) * $perPage);
        $types = $this->leave->getLeaveTypes(true);
        $depts = (new DepartmentModel())->getAll(true);

        $this->view('leave/index', [
            'title'      => 'Leave Management',
            'apps'       => $apps,
            'total'      => count($apps),
            'page'       => $page,
            'perPage'    => $perPage,
            'filters'    => $filters,
            'types'      => $types,
            'depts'      => $depts,
            'pending'    => $this->leave->countPending(),
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function apply(): void
    {
        if (Request::isPost()) {
            CSRF::verify();
            $data  = Request::post();
            $rules = [
                'leave_type_id' => 'required|integer',
                'from_date'     => 'required|date',
                'to_date'       => 'required|date',
                'reason'        => 'required|min:10|max:500',
            ];
            if (!$this->validate($data, $rules)) {
                Session::flash('error', $this->getValidationErrors());
                Response::redirect('/leave/apply');
                return;
            }
            if ($data['from_date'] > $data['to_date']) {
                Session::flash('error', 'From date must be before To date.');
                Response::redirect('/leave/apply');
                return;
            }

            // Determine employee
            $empId = (int)($data['employee_id'] ?? 0);
            if (!$empId) {
                // Self-apply: find linked employee
                $db   = \App\Core\Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT id FROM employees WHERE user_id=? AND status='active' LIMIT 1");
                $stmt->execute([Auth::id()]);
                $empId = (int)$stmt->fetchColumn();
            }
            if (!$empId) {
                Session::flash('error', 'No employee profile linked to your account.');
                Response::redirect('/leave/apply');
                return;
            }

            if ($this->leave->hasOverlappingLeave($empId, $data['from_date'], $data['to_date'])) {
                Session::flash('error', 'A leave application already exists for this date range.');
                Response::redirect('/leave/apply');
                return;
            }

            $days = $this->leave->calculateWorkingDays($data['from_date'], $data['to_date']);
            if ($days <= 0) {
                Session::flash('error', 'Selected dates contain no working days.');
                Response::redirect('/leave/apply');
                return;
            }

            // Handle document upload
            $docPath = null;
            if (Request::hasFile('document')) {
                $docPath = $this->uploadDocument($_FILES['document']);
            }

            $id = $this->leave->createApplication([
                'employee_id'   => $empId,
                'leave_type_id' => (int)$data['leave_type_id'],
                'from_date'     => $data['from_date'],
                'to_date'       => $data['to_date'],
                'total_days'    => $days,
                'half_day'      => (int)($data['half_day'] ?? 0),
                'reason'        => strip_tags($data['reason']),
                'document_path' => $docPath,
                'applied_by'    => Auth::id(),
            ]);

            // Notify HR
            (new NotificationModel())->sendToRole(2, 'leave_application', 'New Leave Application',
                "A new leave application has been submitted.", ['application_id' => $id]);

            AuditLogger::log('leave', 'leave_applied', "Leave application #$id created for employee #$empId");
            Session::flash('success', "Leave application submitted for $days day(s).");
            Response::redirect('/leave');
            return;
        }

        $empModel  = new EmployeeModel();
        $employees = Auth::can('leave.approve') ? $empModel->getAll(['status' => 'active']) : [];

        $this->view('leave/apply', [
            'title'      => 'Apply for Leave',
            'types'      => $this->leave->getLeaveTypes(true),
            'employees'  => $employees,
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function show(int $id): void
    {
        $app = $this->leave->getApplicationById($id);
        if (!$app) Response::abort(404);
        Response::json(['success' => true, 'application' => $app]);
    }

    public function approve(int $id): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $this->requirePermission('leave.approve');
        $remarks = strip_tags(Request::post('remarks', ''));
        $app     = $this->leave->getApplicationById($id);
        if (!$app || $app['status'] !== 'pending') {
            Response::json(['success' => false, 'message' => 'Application cannot be approved.'], 422);
            return;
        }
        $this->leave->updateStatus($id, 'approved', Auth::id(), $remarks);
        AuditLogger::log('leave', 'leave_approved', "Approved leave #$id");
        Response::json(['success' => true]);
    }

    public function reject(int $id): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $this->requirePermission('leave.approve');
        $remarks = strip_tags(Request::post('remarks', ''));
        if (empty($remarks)) { Response::json(['success' => false, 'message' => 'Rejection reason required.'], 422); return; }
        $app = $this->leave->getApplicationById($id);
        if (!$app || $app['status'] !== 'pending') {
            Response::json(['success' => false, 'message' => 'Cannot reject this application.'], 422);
            return;
        }
        $this->leave->updateStatus($id, 'rejected', Auth::id(), $remarks);
        AuditLogger::log('leave', 'leave_rejected', "Rejected leave #$id. Reason: $remarks");
        Response::json(['success' => true]);
    }

    public function cancel(int $id): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $app = $this->leave->getApplicationById($id);
        if (!$app || $app['status'] !== 'pending') {
            Response::json(['success' => false, 'message' => 'Only pending applications can be cancelled.'], 422);
            return;
        }
        $this->leave->updateStatus($id, 'cancelled', null);
        Response::json(['success' => true]);
    }

    public function manage(): void
    {
        $this->requirePermission('leave.manage');
        $this->view('leave/manage', [
            'title'      => 'Leave Type Management',
            'types'      => $this->leave->getLeaveTypes(),
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function saveType(): void
    {
        if (!Request::isPost()) { Response::redirect('/leave/manage'); return; }
        CSRF::verify();
        $this->requirePermission('leave.manage');

        $data  = Request::post();
        $id    = !empty($data['id']) ? (int)$data['id'] : null;
        $rules = [
            'name'         => 'required|min:2|max:100',
            'code'         => 'required|min:2|max:20',
            'days_allowed' => 'required|numeric',
        ];
        if (!$this->validate($data, $rules)) {
            Session::flash('error', $this->getValidationErrors());
            Response::redirect('/leave/manage');
            return;
        }
        if ($id) {
            $this->leave->updateLeaveType($id, $data);
            Session::flash('success', 'Leave type updated.');
        } else {
            $this->leave->createLeaveType($data);
            Session::flash('success', 'Leave type created.');
        }
        Response::redirect('/leave/manage');
    }

    public function deleteType(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('leave.manage');
        \App\Core\Database::getInstance()->getConnection()->prepare("DELETE FROM leave_types WHERE id=?")->execute([$id]);
        Response::json(['success' => true]);
    }

    public function holidays(): void
    {
        $year     = (int)Request::get('year', date('Y'));
        $holidays = $this->leave->getHolidays($year);
        $this->view('leave/holidays', [
            'title'      => 'Holidays',
            'holidays'   => $holidays,
            'year'       => $year,
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function saveHoliday(): void
    {
        if (!Request::isPost()) { Response::redirect('/leave/holidays'); return; }
        CSRF::verify();
        $this->requirePermission('leave.manage');
        $data  = Request::post();
        $rules = ['name' => 'required', 'holiday_date' => 'required|date'];
        if (!$this->validate($data, $rules)) {
            Session::flash('error', $this->getValidationErrors());
            Response::redirect('/leave/holidays');
            return;
        }
        $this->leave->createHoliday($data);
        Session::flash('success', 'Holiday added.');
        Response::redirect('/leave/holidays?year=' . date('Y', strtotime($data['holiday_date'])));
    }

    public function deleteHoliday(int $id): void
    {
        CSRF::verify();
        \App\Core\Database::getInstance()->getConnection()->prepare("DELETE FROM holidays WHERE id=?")->execute([$id]);
        Response::json(['success' => true]);
    }

    public function allocateBalance(): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $this->requirePermission('leave.manage');
        $data = Request::post();
        $this->leave->allocateBalance((int)$data['employee_id'], (int)$data['leave_type_id'], (int)$data['year'], (float)$data['days']);
        Response::json(['success' => true]);
    }

    public function export(): void
    {
        $this->requirePermission('reports.export');
        $apps = $this->leave->getApplications(['year' => (int)Request::get('year', date('Y'))], 5000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leave_export_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Employee Code','Employee Name','Department','Leave Type','From','To','Days','Status','Reason']);
        foreach ($apps as $a) {
            fputcsv($out, [$a['employee_code']??'',$a['full_name']??'',$a['department_name']??'',$a['leave_type_name']??'',$a['from_date'],$a['to_date'],$a['total_days'],$a['status'],strip_tags($a['reason']??'')]);
        }
        fclose($out);
        exit;
    }

    private function uploadDocument(array $file): ?string
    {
        $allowed = ['application/pdf','image/jpeg','image/png'];
        if ($file['error'] !== UPLOAD_ERR_OK || !in_array($file['type'], $allowed) || $file['size'] > 5*1024*1024) return null;
        $dir  = base_path('storage/uploads/leave_docs');
        if (!is_dir($dir)) mkdir($dir, 0750, true);
        $name = 'leave_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        return move_uploaded_file($file['tmp_name'], $dir.'/'.$name)
            ? 'storage/uploads/leave_docs/'.$name : null;
    }
}
