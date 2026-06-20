<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\JwtAuth;
use App\Models\EmployeeModel;
use App\Models\AttendanceModel;
use App\Models\LeaveModel;
use PDO;

class ApiController extends Controller
{
    private PDO $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance()->getConnection();
        $this->requireApiAuth();
    }

    // ─── Authentication ───────────────────────────────────────────────────────────

    public function token(): void
    {
        // Public endpoint - no auth needed here; called before requireApiAuth
        if (!Request::isPost()) {
            Response::json(['error' => 'Method not allowed.'], 405);
            return;
        }

        $input = $this->getJsonBody();
        if (empty($input['email']) || empty($input['password'])) {
            Response::json(['error' => 'Email and password required.'], 422);
            return;
        }

        $email    = strtolower(trim($input['email']));
        $password = $input['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'Invalid credentials.'], 401);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role_name FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.email = ? AND u.status = 'active'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            Response::json(['error' => 'Invalid credentials.'], 401);
            return;
        }

        $token = JwtAuth::generate([
            'sub'   => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role_name'],
        ]);

        Response::json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => config('jwt.ttl', 86400),
            'user'         => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role_name'],
            ],
        ]);
    }

    // ─── Employees ────────────────────────────────────────────────────────────────

    public function employees(): void
    {
        $page    = max(1, (int)Request::get('page', 1));
        $perPage = min(100, max(1, (int)Request::get('per_page', 20)));
        $status  = Request::get('status', 'active');
        $deptId  = (int)Request::get('department_id');

        $model     = new EmployeeModel();
        $filters   = array_filter(['status' => $status, 'department_id' => $deptId ?: null]);
        $employees = $model->getAll($filters, $perPage, ($page - 1) * $perPage);
        $total     = $model->count($filters);

        Response::json([
            'data'  => array_map([$this, 'formatEmployee'], $employees),
            'meta'  => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => ceil($total / $perPage),
            ],
        ]);
    }

    public function employee(int $id): void
    {
        $model = new EmployeeModel();
        $emp   = $model->getById($id);
        if (!$emp) {
            Response::json(['error' => 'Employee not found.'], 404);
            return;
        }
        Response::json(['data' => $this->formatEmployee($emp)]);
    }

    // ─── Attendance ───────────────────────────────────────────────────────────────

    public function attendance(): void
    {
        $empId    = (int)Request::get('employee_id');
        $dateFrom = Request::get('date_from', date('Y-m-01'));
        $dateTo   = Request::get('date_to', date('Y-m-d'));

        if (!$this->validateDate($dateFrom) || !$this->validateDate($dateTo)) {
            Response::json(['error' => 'Invalid date format. Use Y-m-d.'], 422);
            return;
        }

        $model   = new AttendanceModel();
        $records = $model->getRecords([
            'employee_id' => $empId ?: null,
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
        ], 500, 0);

        Response::json([
            'data' => array_map(fn($r) => [
                'id'              => $r['id'],
                'employee_id'     => $r['employee_id'],
                'employee_code'   => $r['employee_code'] ?? null,
                'employee_name'   => $r['full_name'] ?? null,
                'date'            => $r['attendance_date'],
                'status'          => $r['status'],
                'time_in'         => $r['time_in'],
                'time_out'        => $r['time_out'],
                'late_minutes'    => $r['late_minutes'],
                'overtime_minutes' => $r['overtime_minutes'],
                'work_minutes'    => $r['work_minutes'],
            ], $records),
            'meta' => [
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'count'     => count($records),
            ],
        ]);
    }

    public function attendancePunch(): void
    {
        if (!Request::isPost()) {
            Response::json(['error' => 'Method not allowed.'], 405);
            return;
        }

        $input = $this->getJsonBody();
        $rules = [
            'employee_id' => 'required|integer',
            'punch_time'  => 'required',
            'punch_type'  => 'required|in:in,out,break_in,break_out',
        ];

        if (!$this->validateInput($input, $rules)) {
            Response::json(['error' => 'Validation failed.', 'details' => $this->apiErrors], 422);
            return;
        }

        $model = new AttendanceModel();

        // Validate employee exists
        $emp = (new EmployeeModel())->getById((int)$input['employee_id']);
        if (!$emp) {
            Response::json(['error' => 'Employee not found.'], 404);
            return;
        }

        $logId = $model->insertManualPunch([
            'employee_id' => (int)$input['employee_id'],
            'punch_time'  => $input['punch_time'],
            'punch_type'  => $input['punch_type'],
            'source'      => 'api',
            'note'        => $input['note'] ?? null,
        ]);

        Response::json(['success' => true, 'log_id' => $logId], 201);
    }

    // ─── Leave ────────────────────────────────────────────────────────────────────

    public function leaveBalance(int $employeeId): void
    {
        $emp = (new EmployeeModel())->getById($employeeId);
        if (!$emp) {
            Response::json(['error' => 'Employee not found.'], 404);
            return;
        }

        $model   = new LeaveModel();
        $year    = (int)Request::get('year', date('Y'));
        $balance = $model->getLeaveBalance($employeeId, $year);

        Response::json(['data' => $balance, 'year' => $year]);
    }

    public function leaveApplications(): void
    {
        $empId  = (int)Request::get('employee_id');
        $status = Request::get('status', '');
        $year   = (int)Request::get('year', date('Y'));

        $model  = new LeaveModel();
        $apps   = $model->getApplications([
            'employee_id' => $empId ?: null,
            'status'      => $status ?: null,
            'year'        => $year,
        ], 100, 0);

        Response::json(['data' => $apps]);
    }

    // ─── Departments & Designations ──────────────────────────────────────────────

    public function departments(): void
    {
        $rows = $this->db->query("SELECT id, name, code, parent_id FROM departments WHERE is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        Response::json(['data' => $rows]);
    }

    public function designations(): void
    {
        $rows = $this->db->query("SELECT id, name, department_id FROM designations WHERE is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        Response::json(['data' => $rows]);
    }

    // ─── Devices (Read-only status) ──────────────────────────────────────────────

    public function devices(): void
    {
        $rows = $this->db->query("
            SELECT id, name, serial_number, ip_address, model, status, last_sync_at
            FROM devices ORDER BY name
        ")->fetchAll(PDO::FETCH_ASSOC);
        Response::json(['data' => $rows]);
    }

    // ─── Health ───────────────────────────────────────────────────────────────────

    public function health(): void
    {
        try {
            $this->db->query("SELECT 1");
            $dbOk = true;
        } catch (\Exception $e) {
            $dbOk = false;
        }

        Response::json([
            'status'    => $dbOk ? 'ok' : 'degraded',
            'timestamp' => date('c'),
            'checks'    => [
                'database' => $dbOk ? 'ok' : 'error',
            ],
        ], $dbOk ? 200 : 503);
    }

    // ─── Private ─────────────────────────────────────────────────────────────────

    private array $apiErrors = [];

    private function requireApiAuth(): void
    {
        // Skip for token endpoint
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_ends_with(parse_url($uri, PHP_URL_PATH), '/api/token')) {
            return;
        }

        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            Response::json(['error' => 'Authentication required.'], 401);
            exit;
        }

        $token   = substr($header, 7);
        $payload = JwtAuth::verify($token);

        if (!$payload) {
            Response::json(['error' => 'Invalid or expired token.'], 401);
            exit;
        }

        // Check user still active
        $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$payload['sub']]);
        if (!$stmt->fetchColumn()) {
            Response::json(['error' => 'Account deactivated.'], 401);
            exit;
        }
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return Request::post() ?: [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function validateInput(array $data, array $rules): bool
    {
        $this->apiErrors = [];
        foreach ($rules as $field => $ruleStr) {
            $ruleList = explode('|', $ruleStr);
            $value    = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $this->apiErrors[$field][] = "$field is required.";
                } elseif ($rule === 'integer' && $value !== null && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->apiErrors[$field][] = "$field must be an integer.";
                } elseif (str_starts_with($rule, 'in:')) {
                    $allowed = explode(',', substr($rule, 3));
                    if ($value !== null && !in_array($value, $allowed)) {
                        $this->apiErrors[$field][] = "$field must be one of: " . implode(', ', $allowed);
                    }
                }
            }
        }
        return empty($this->apiErrors);
    }

    private function validateDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    private function formatEmployee(array $emp): array
    {
        return [
            'id'              => $emp['id'],
            'employee_code'   => $emp['employee_code'],
            'full_name'       => $emp['full_name'],
            'email'           => $emp['email'],
            'phone'           => $emp['phone'],
            'gender'          => $emp['gender'],
            'department_id'   => $emp['department_id'],
            'department_name' => $emp['department_name'] ?? null,
            'designation_id'  => $emp['designation_id'],
            'designation_name'=> $emp['designation_name'] ?? null,
            'join_date'       => $emp['join_date'],
            'status'          => $emp['status'],
            'badge_number'    => $emp['badge_number'] ?? null,
        ];
    }
}
