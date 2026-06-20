<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\CSRF;
use App\Core\AuditLogger;
use App\Models\EmployeeModel;
use App\Models\DepartmentModel;
use App\Models\DesignationModel;
use App\Models\ShiftModel;
use App\Core\Database;
use PDO;

class EmployeeController extends Controller
{
    private EmployeeModel $employee;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('employees');
        $this->employee = new EmployeeModel();
    }

    public function index(): void
    {
        $page    = max(1, (int)Request::get('page', 1));
        $perPage = 20;
        $filters = array_filter([
            'status'        => Request::get('status', 'active'),
            'department_id' => (int)Request::get('department_id'),
            'search'        => Request::get('search', ''),
        ], fn($v) => $v !== '' && $v !== 0);

        $employees = $this->employee->getAll($filters, $perPage, ($page - 1) * $perPage);
        $total     = $this->employee->count($filters);
        $depts     = (new DepartmentModel())->getAll(true);

        $this->view('employees/index', [
            'title'     => 'Employees',
            'employees' => $employees,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => $perPage,
            'filters'   => $filters,
            'depts'     => $depts,
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('employees.create');

        if (Request::isPost()) {
            CSRF::verify();
            $data  = Request::post();
            $rules = [
                'full_name'  => 'required|min:2|max:150',
                'email'      => 'required|email|max:150',
                'join_date'  => 'required|date',
                'gender'     => 'required|in:male,female,other',
            ];
            if (!$this->validate($data, $rules)) {
                Session::flash('error', $this->getValidationErrors());
                Response::redirect('/employees/create');
                return;
            }

            // Auto-generate employee code if blank
            if (empty($data['employee_code'])) {
                $data['employee_code'] = $this->generateCode();
            }

            // Check unique email
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM employees WHERE email=?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetchColumn()) {
                Session::flash('error', 'Email already in use.');
                Response::redirect('/employees/create');
                return;
            }

            $data['created_by'] = $this->auth->id();
            $id = $this->employee->create($data);

            // Handle profile photo
            if (Request::hasFile('profile_photo')) {
                $path = $this->uploadPhoto($_FILES['profile_photo'], $id);
                if ($path) {
                    $this->employee->update($id, ['profile_photo' => $path]);
                }
            }

            AuditLogger::log('employees', 'employee_created', "Created employee: {$data['full_name']} ({$data['employee_code']})");
            Session::flash('success', 'Employee created successfully.');
            Response::redirect('/employees/' . $id);
            return;
        }

        $this->view('employees/create', [
            'title'        => 'Add Employee',
            'depts'        => (new DepartmentModel())->getAll(true),
            'designations' => (new DesignationModel())->getAll(true),
            'shifts'       => (new ShiftModel())->getAll(true),
            'csrf_token'   => CSRF::generate(),
        ]);
    }

    public function show(int $id): void
    {
        $emp = $this->employee->getById($id);
        if (!$emp) {
            Response::abort(404, 'Employee not found.');
        }

        $docs      = $this->employee->getDocuments($id);
        $transfers = $this->employee->getTransferHistory($id);
        $shifts    = (new ShiftModel())->getAll(true);

        $db        = Database::getInstance()->getConnection();
        $salary    = $db->prepare("
            SELECT es.*, ss.name AS structure_name
            FROM employee_salary es JOIN salary_structures ss ON ss.id=es.structure_id
            WHERE es.employee_id=? AND es.effective_to IS NULL ORDER BY es.effective_from DESC LIMIT 1
        ");
        $salary->execute([$id]);
        $currentSalary = $salary->fetch(PDO::FETCH_ASSOC);

        $this->view('employees/show', [
            'title'         => $emp['full_name'],
            'emp'           => $emp,
            'docs'          => $docs,
            'transfers'     => $transfers,
            'currentSalary' => $currentSalary,
            'shifts'        => $shifts,
            'csrf_token'    => CSRF::generate(),
        ]);
    }

    public function edit(int $id): void
    {
        $this->requirePermission('employees.edit');
        $emp = $this->employee->getById($id);
        if (!$emp) Response::abort(404, 'Employee not found.');

        if (Request::isPost()) {
            CSRF::verify();
            $data  = Request::post();
            $rules = [
                'full_name' => 'required|min:2|max:150',
                'email'     => 'required|email|max:150',
                'join_date' => 'required|date',
                'gender'    => 'required|in:male,female,other',
            ];
            if (!$this->validate($data, $rules)) {
                Session::flash('error', $this->getValidationErrors());
                Response::redirect('/employees/' . $id . '/edit');
                return;
            }

            // Unique email excluding self
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM employees WHERE email=? AND id!=?");
            $stmt->execute([$data['email'], $id]);
            if ($stmt->fetchColumn()) {
                Session::flash('error', 'Email already in use by another employee.');
                Response::redirect('/employees/' . $id . '/edit');
                return;
            }

            if (Request::hasFile('profile_photo')) {
                $path = $this->uploadPhoto($_FILES['profile_photo'], $id);
                if ($path) $data['profile_photo'] = $path;
            }

            AuditLogger::logChange('employees', 'employee_updated', $emp, $data, "Updated employee: {$emp['full_name']}");
            $this->employee->update($id, $data);
            Session::flash('success', 'Employee updated.');
            Response::redirect('/employees/' . $id);
            return;
        }

        $this->view('employees/edit', [
            'title'        => 'Edit: ' . $emp['full_name'],
            'emp'          => $emp,
            'depts'        => (new DepartmentModel())->getAll(true),
            'designations' => (new DesignationModel())->getAll(true),
            'csrf_token'   => CSRF::generate(),
        ]);
    }

    public function updateStatus(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('employees.edit');
        $status = Request::post('status', '');
        $allowed = ['active','inactive','on_leave','terminated'];
        if (!in_array($status, $allowed)) {
            Response::json(['success' => false, 'message' => 'Invalid status.'], 422);
            return;
        }
        $emp = $this->employee->getById($id);
        if (!$emp) { Response::json(['success' => false], 404); return; }

        $this->employee->updateStatus($id, $status);
        AuditLogger::log('employees', 'status_changed', "Employee {$emp['full_name']} status → $status");
        Response::json(['success' => true]);
    }

    public function transfer(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('employees.edit');
        $emp  = $this->employee->getById($id);
        if (!$emp) { Response::json(['success' => false], 404); return; }

        $data  = Request::post();
        $rules = [
            'effective_date' => 'required|date',
        ];
        if (!$this->validate($data, $rules)) {
            Response::json(['success' => false, 'message' => $this->getValidationErrors()], 422);
            return;
        }
        $data['employee_id']         = $id;
        $data['from_department_id']  = $emp['department_id'];
        $data['from_designation_id'] = $emp['designation_id'];
        $data['created_by']          = $this->auth->id();
        $this->employee->addTransfer($data);
        AuditLogger::log('employees', 'employee_transferred', "Employee {$emp['full_name']} transferred.");
        Response::json(['success' => true]);
    }

    public function uploadDocument(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('employees.edit');
        $emp = $this->employee->getById($id);
        if (!$emp) { Response::json(['success' => false], 404); return; }

        if (!Request::hasFile('document')) {
            Response::json(['success' => false, 'message' => 'No file uploaded.'], 422);
            return;
        }

        $file    = $_FILES['document'];
        $allowed = ['application/pdf','image/jpeg','image/png','application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

        if ($file['error'] !== UPLOAD_ERR_OK || !in_array($file['type'], $allowed) || $file['size'] > 10 * 1024 * 1024) {
            Response::json(['success' => false, 'message' => 'Invalid file. PDF/Image/Word, max 10MB.'], 422);
            return;
        }

        $docType = strip_tags(Request::post('document_type', 'other'));
        $docName = strip_tags(Request::post('document_name', basename($file['name'])));
        $expiry  = Request::post('expiry_date') ?: null;
        $ext     = pathinfo($file['name'], PATHINFO_EXTENSION);
        $dir     = base_path("storage/uploads/documents/emp_{$id}");

        if (!is_dir($dir)) mkdir($dir, 0750, true);

        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $docType) . '_' . time() . '.' . $ext;
        $dest     = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Response::json(['success' => false, 'message' => 'File save failed.'], 500);
            return;
        }

        $path = "storage/uploads/documents/emp_{$id}/{$filename}";
        $this->employee->addDocument([
            'employee_id'   => $id,
            'document_type' => $docType,
            'document_name' => $docName,
            'file_path'     => $path,
            'expiry_date'   => $expiry,
            'created_by'    => $this->auth->id(),
        ]);
        Response::json(['success' => true, 'message' => 'Document uploaded.']);
    }

    public function deleteDocument(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('employees.edit');
        $docId = (int)Request::post('document_id');
        if (!$docId) { Response::json(['success' => false], 422); return; }
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM employee_documents WHERE id=? AND employee_id=?");
        $stmt->execute([$docId, $id]);
        $doc  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) { Response::json(['success' => false, 'message' => 'Document not found.'], 404); return; }
        $path = base_path($doc['file_path']);
        if (file_exists($path)) @unlink($path);
        $db->prepare("DELETE FROM employee_documents WHERE id=?")->execute([$docId]);
        Response::json(['success' => true]);
    }

    public function search(): void
    {
        $q    = trim(Request::get('q', ''));
        $data = $q ? $this->employee->search($q, 15) : [];
        Response::json(['data' => $data]);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function generateCode(): string
    {
        $db   = Database::getInstance()->getConnection();
        $last = $db->query("SELECT employee_code FROM employees ORDER BY id DESC LIMIT 1")->fetchColumn();
        $num  = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1001;
        return 'EMP' . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }

    private function uploadPhoto(array $file, int $empId): ?string
    {
        $allowed = ['image/jpeg','image/png','image/webp'];
        if ($file['error'] !== UPLOAD_ERR_OK || !in_array($file['type'], $allowed) || $file['size'] > 2 * 1024 * 1024) {
            return null;
        }
        $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
        $dir  = base_path('storage/uploads/avatars');
        if (!is_dir($dir)) mkdir($dir, 0750, true);
        $name = "emp_{$empId}_" . time() . '.' . $ext;
        $dest = $dir . '/' . $name;
        return move_uploaded_file($file['tmp_name'], $dest)
            ? 'storage/uploads/avatars/' . $name
            : null;
    }
}
