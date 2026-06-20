<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\CSRF;
use App\Core\AuditLogger;
use App\Models\ShiftModel;
use App\Models\EmployeeModel;
use App\Models\DepartmentModel;

class ShiftController extends Controller
{
    private ShiftModel $shift;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('attendance');
        $this->shift = new ShiftModel();
    }

    public function index(): void
    {
        $this->view('shifts/index', [
            'title'      => 'Shifts & Roster',
            'shifts'     => $this->shift->getAll(),
            'rosters'    => $this->shift->getAllRosters(),
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function save(): void
    {
        if (!Request::isPost()) { Response::redirect('/shifts'); return; }
        CSRF::verify();

        $data  = Request::post();
        $id    = !empty($data['id']) ? (int)$data['id'] : null;
        $days  = array_map('intval', $data['working_days'] ?? []);
        $rules = [
            'name'       => 'required|min:2|max:100',
            'start_time' => 'required|time',
            'end_time'   => 'required|time',
        ];
        if (!$this->validate($data, $rules)) {
            Session::flash('error', $this->getValidationErrors());
            Response::redirect('/shifts');
            return;
        }

        if ($id) {
            $this->shift->update($id, $data, $days);
            Session::flash('success', 'Shift updated.');
        } else {
            $this->shift->create($data, $days);
            Session::flash('success', 'Shift created.');
        }
        AuditLogger::log('attendance', $id ? 'shift_updated' : 'shift_created', "Shift: {$data['name']}");
        Response::redirect('/shifts');
    }

    public function delete(int $id): void
    {
        CSRF::verify();
        if (!$this->shift->delete($id)) {
            Response::json(['success' => false, 'message' => 'Cannot delete: employees are assigned to this shift.'], 409);
            return;
        }
        Response::json(['success' => true]);
    }

    public function roster(): void
    {
        $this->view('shifts/roster', [
            'title'      => 'Roster Management',
            'shifts'     => $this->shift->getAll(true),
            'rosters'    => $this->shift->getAllRosters(),
            'employees'  => (new EmployeeModel())->getAll(['status' => 'active']),
            'depts'      => (new DepartmentModel())->getAll(true),
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function assign(): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();

        $data  = Request::post();
        $rules = [
            'employee_id' => 'required|integer',
            'shift_id'    => 'required|integer',
            'date_from'   => 'required|date',
        ];
        if (!$this->validate($data, $rules)) {
            Response::json(['success' => false, 'message' => $this->getValidationErrors()], 422);
            return;
        }
        $data['created_by'] = $this->auth->id();
        $this->shift->assignShift($data);
        Response::json(['success' => true]);
    }

    public function unassign(): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $empId = (int)Request::post('employee_id');
        if (!$empId) { Response::json(['success' => false], 422); return; }
        $this->shift->unassignShift($empId);
        Response::json(['success' => true]);
    }

    public function changeRequests(): void
    {
        $status   = Request::get('status', '');
        $requests = $this->shift->getChangeRequests($status);
        $this->view('shifts/change_requests', [
            'title'    => 'Shift Change Requests',
            'requests' => $requests,
            'status'   => $status,
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function approveChangeRequest(int $id): void
    {
        if (!Request::isPost()) { Response::json(['success' => false], 405); return; }
        CSRF::verify();
        $status = Request::post('status', 'approved');
        if (!in_array($status, ['approved','rejected'])) {
            Response::json(['success' => false], 422);
            return;
        }
        $this->shift->approveChangeRequest($id, $this->auth->id(), $status);
        Response::json(['success' => true]);
    }
}
