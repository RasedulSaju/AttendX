<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\CSRF;
use App\Core\AuditLogger;
use App\Models\DepartmentModel;
use App\Models\DesignationModel;
use App\Models\EmployeeModel;

class DepartmentController extends Controller
{
    private DepartmentModel  $dept;
    private DesignationModel $des;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('employees');
        $this->dept = new DepartmentModel();
        $this->des  = new DesignationModel();
    }

    // ─── Departments ──────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->view('departments/index', [
            'title'        => 'Departments & Designations',
            'departments'  => $this->dept->getAll(),
            'designations' => $this->des->getAll(),
            'csrf_token'   => CSRF::generate(),
        ]);
    }

    public function save(): void
    {
        if (!Request::isPost()) { Response::redirect('/departments'); return; }
        CSRF::verify();
        $this->requirePermission('employees.create');

        $data  = Request::post();
        $id    = !empty($data['id']) ? (int)$data['id'] : null;
        $rules = ['name' => 'required|min:2|max:100'];

        if (!$this->validate($data, $rules)) {
            Session::flash('error', $this->getValidationErrors());
            Response::redirect('/departments');
            return;
        }

        if ($id) {
            $old = $this->dept->getById($id);
            $this->dept->update($id, $data);
            AuditLogger::logChange('employees', 'department_updated', $old ?: [], $data);
            Session::flash('success', 'Department updated.');
        } else {
            $this->dept->create($data);
            AuditLogger::log('employees', 'department_created', "Created department: {$data['name']}");
            Session::flash('success', 'Department created.');
        }
        Response::redirect('/departments');
    }

    public function delete(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('employees.delete');
        if (!$this->dept->delete($id)) {
            Response::json(['success' => false, 'message' => 'Cannot delete: active employees are assigned to this department.'], 409);
            return;
        }
        AuditLogger::log('employees', 'department_deleted', "Deleted department #$id");
        Response::json(['success' => true]);
    }

    // ─── Designations ─────────────────────────────────────────────────────────

    public function designations(): void
    {
        $this->view('departments/index', [
            'title'        => 'Departments & Designations',
            'departments'  => $this->dept->getAll(),
            'designations' => $this->des->getAll(),
            'csrf_token'   => CSRF::generate(),
            'activeTab'    => 'designations',
        ]);
    }

    public function saveDesignation(): void
    {
        if (!Request::isPost()) { Response::redirect('/departments'); return; }
        CSRF::verify();
        $this->requirePermission('employees.create');

        $data  = Request::post();
        $id    = !empty($data['id']) ? (int)$data['id'] : null;
        $rules = ['name' => 'required|min:2|max:100'];

        if (!$this->validate($data, $rules)) {
            Session::flash('error', $this->getValidationErrors());
            Response::redirect('/departments');
            return;
        }

        if ($id) {
            $this->des->update($id, $data);
            Session::flash('success', 'Designation updated.');
        } else {
            $this->des->create($data);
            Session::flash('success', 'Designation created.');
        }
        Response::redirect('/departments');
    }

    public function deleteDesignation(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('employees.delete');
        if (!$this->des->delete($id)) {
            Response::json(['success' => false, 'message' => 'Cannot delete: active employees hold this designation.'], 409);
            return;
        }
        Response::json(['success' => true]);
    }
}
