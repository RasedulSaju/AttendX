<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\JwtAuth;
use App\Models\AttendanceModel;
use App\Models\LeaveModel;
use App\Models\EmployeeModel;
use PDO;

class MobileApiController extends Controller
{
    private PDO           $db;
    private array         $currentUser = [];

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance()->getConnection();
        $this->requireMobileAuth();
    }

    // ─── Auth ─────────────────────────────────────────────────────────────────────

    public function login(): void
    {
        if (!Request::isPost()) {
            Response::json(['error' => 'Method not allowed.'], 405);
            return;
        }

        $input = $this->getJson();
        if (empty($input['email']) || empty($input['password'])) {
            Response::json(['error' => 'Email and password required.'], 422);
            return;
        }

        $email = strtolower(trim($input['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'Invalid credentials.'], 401);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role_name, e.id AS employee_id, e.full_name, e.employee_code, e.profile_photo
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            LEFT JOIN employees e ON e.user_id = u.id
            WHERE u.email = ? AND u.status = 'active'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($input['password'], $user['password'])) {
            Response::json(['error' => 'Invalid credentials.'], 401);
            return;
        }

        $payload = [
            'sub'         => $user['id'],
            'email'       => $user['email'],
            'role'        => $user['role_name'],
            'employee_id' => $user['employee_id'],
            'type'        => 'mobile',
        ];

        $token        = JwtAuth::generate($payload, config('jwt.mobile_ttl', 604800)); // 7 days
        $refreshToken = JwtAuth::generateRefresh($user['id']);

        Response::json([
            'access_token'  => $token,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => config('jwt.mobile_ttl', 604800),
            'user'          => [
                'id'            => $user['id'],
                'name'          => $user['full_name'] ?? $user['name'],
                'email'         => $user['email'],
                'role'          => $user['role_name'],
                'employee_id'   => $user['employee_id'],
                'employee_code' => $user['employee_code'],
                'profile_photo' => $user['profile_photo'],
            ],
        ]);
    }

    public function refresh(): void
    {
        if (!Request::isPost()) {
            Response::json(['error' => 'Method not allowed.'], 405);
            return;
        }

        $input        = $this->getJson();
        $refreshToken = $input['refresh_token'] ?? '';

        if (!$refreshToken) {
            Response::json(['error' => 'Refresh token required.'], 422);
            return;
        }

        $userId = JwtAuth::verifyRefresh($refreshToken);
        if (!$userId) {
            Response::json(['error' => 'Invalid or expired refresh token.'], 401);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role_name, e.id AS employee_id
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            LEFT JOIN employees e ON e.user_id = u.id
            WHERE u.id = ? AND u.status = 'active'
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::json(['error' => 'Account not found or deactivated.'], 401);
            return;
        }

        $token = JwtAuth::generate([
            'sub'         => $user['id'],
            'email'       => $user['email'],
            'role'        => $user['role_name'],
            'employee_id' => $user['employee_id'],
            'type'        => 'mobile',
        ], config('jwt.mobile_ttl', 604800));

        Response::json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => config('jwt.mobile_ttl', 604800),
        ]);
    }

    public function logout(): void
    {
        JwtAuth::revokeRefresh($this->currentUser['id'] ?? 0);
        Response::json(['success' => true]);
    }

    // ─── Profile ──────────────────────────────────────────────────────────────────

    public function profile(): void
    {
        $empId = $this->currentUser['employee_id'] ?? null;
        if (!$empId) {
            Response::json(['error' => 'No employee profile linked.'], 404);
            return;
        }

        $model = new EmployeeModel();
        $emp   = $model->getById((int)$empId);

        if (!$emp) {
            Response::json(['error' => 'Employee not found.'], 404);
            return;
        }

        Response::json(['data' => $this->formatProfile($emp)]);
    }

    // ─── My Attendance ────────────────────────────────────────────────────────────

    public function myAttendance(): void
    {
        $empId = $this->getEmployeeId();
        if (!$empId) {
            Response::json(['error' => 'No employee profile linked.'], 403);
            return;
        }

        $dateFrom = Request::get('date_from', date('Y-m-01'));
        $dateTo   = Request::get('date_to', date('Y-m-d'));

        $model   = new AttendanceModel();
        $records = $model->getRecords([
            'employee_id' => $empId,
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
        ], 100, 0);

        $summary = $model->getAttendanceStats($empId, $dateFrom, $dateTo);

        Response::json([
            'data'    => $records,
            'summary' => $summary,
            'period'  => ['from' => $dateFrom, 'to' => $dateTo],
        ]);
    }

    public function todayStatus(): void
    {
        $empId = $this->getEmployeeId();
        if (!$empId) {
            Response::json(['error' => 'No employee profile.'], 403);
            return;
        }

        $model   = new AttendanceModel();
        $today   = date('Y-m-d');
        $record  = $model->getRecordByEmployeeDate($empId, $today);
        $lastLog = $model->getLastPunch($empId);

        Response::json([
            'today'        => $today,
            'record'       => $record,
            'last_punch'   => $lastLog,
            'server_time'  => date('Y-m-d H:i:s'),
        ]);
    }

    // ─── My Leave ─────────────────────────────────────────────────────────────────

    public function myLeave(): void
    {
        $empId = $this->getEmployeeId();
        if (!$empId) {
            Response::json(['error' => 'No employee profile.'], 403);
            return;
        }

        $year    = (int)Request::get('year', date('Y'));
        $model   = new LeaveModel();
        $balance = $model->getLeaveBalance($empId, $year);
        $apps    = $model->getApplications(['employee_id' => $empId, 'year' => $year], 50, 0);
        $types   = $model->getLeaveTypes(true);

        Response::json([
            'balance' => $balance,
            'applications' => $apps,
            'types'   => $types,
            'year'    => $year,
        ]);
    }

    public function applyLeave(): void
    {
        if (!Request::isPost()) {
            Response::json(['error' => 'Method not allowed.'], 405);
            return;
        }

        $empId = $this->getEmployeeId();
        if (!$empId) {
            Response::json(['error' => 'No employee profile.'], 403);
            return;
        }

        $input = $this->getJson();
        $required = ['leave_type_id', 'from_date', 'to_date', 'reason'];
        foreach ($required as $f) {
            if (empty($input[$f])) {
                Response::json(['error' => "$f is required."], 422);
                return;
            }
        }

        if ($input['from_date'] > $input['to_date']) {
            Response::json(['error' => 'from_date must be before to_date.'], 422);
            return;
        }

        $model = new LeaveModel();

        // Check for overlapping leave
        if ($model->hasOverlappingLeave($empId, $input['from_date'], $input['to_date'])) {
            Response::json(['error' => 'You already have a leave application for this period.'], 409);
            return;
        }

        // Check balance
        $days   = $model->calculateWorkingDays($input['from_date'], $input['to_date']);
        $balance = $model->getLeaveBalance($empId, (int)date('Y'));
        $typeBalance = collect_by_key($balance, 'leave_type_id', (int)$input['leave_type_id']);

        if ($typeBalance && ($typeBalance['remaining'] ?? 0) < $days) {
            Response::json(['error' => 'Insufficient leave balance.'], 422);
            return;
        }

        $id = $model->createApplication([
            'employee_id'   => $empId,
            'leave_type_id' => (int)$input['leave_type_id'],
            'from_date'     => $input['from_date'],
            'to_date'       => $input['to_date'],
            'total_days'    => $days,
            'reason'        => strip_tags($input['reason']),
            'half_day'      => (int)($input['half_day'] ?? 0),
        ]);

        Response::json(['success' => true, 'application_id' => $id, 'days' => $days], 201);
    }

    public function cancelLeave(int $id): void
    {
        if (!Request::isPost()) {
            Response::json(['error' => 'Method not allowed.'], 405);
            return;
        }

        $empId = $this->getEmployeeId();
        if (!$empId) {
            Response::json(['error' => 'No employee profile.'], 403);
            return;
        }

        $model = new LeaveModel();
        $app   = $model->getApplicationById($id);

        if (!$app || (int)$app['employee_id'] !== $empId) {
            Response::json(['error' => 'Application not found.'], 404);
            return;
        }

        if (!in_array($app['status'], ['pending'])) {
            Response::json(['error' => 'Only pending applications can be cancelled.'], 422);
            return;
        }

        $model->updateStatus($id, 'cancelled', null);
        Response::json(['success' => true]);
    }

    // ─── My Payslips ──────────────────────────────────────────────────────────────

    public function myPayslips(): void
    {
        $empId = $this->getEmployeeId();
        if (!$empId) {
            Response::json(['error' => 'No employee profile.'], 403);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT ps.id, ps.gross_earnings, ps.total_deductions, ps.net_salary,
                   ps.status, ps.payment_date, ps.present_days, ps.absent_days,
                   pp.name AS period_name, pp.period_from, pp.period_to
            FROM payroll_slips ps
            JOIN payroll_periods pp ON pp.id = ps.period_id
            WHERE ps.employee_id = ? AND ps.status IN ('approved','paid')
            ORDER BY pp.period_from DESC
            LIMIT 12
        ");
        $stmt->execute([$empId]);
        $slips = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json(['data' => $slips]);
    }

    // ─── Notifications ────────────────────────────────────────────────────────────

    public function notifications(): void
    {
        $userId = $this->currentUser['id'] ?? 0;
        $stmt   = $this->db->prepare("
            SELECT * FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 30
        ");
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $unread = array_sum(array_column($notifications, 'is_read') === 0);

        Response::json(['data' => $notifications, 'unread' => $unread]);
    }

    public function markNotificationRead(int $id): void
    {
        if (!Request::isPost()) {
            Response::json(['error' => 'Method not allowed.'], 405);
            return;
        }
        $userId = $this->currentUser['id'] ?? 0;
        $this->db->prepare("UPDATE notifications SET is_read=1, read_at=NOW() WHERE id=? AND user_id=?")->execute([$id, $userId]);
        Response::json(['success' => true]);
    }

    public function markAllNotificationsRead(): void
    {
        if (!Request::isPost()) {
            Response::json(['error' => 'Method not allowed.'], 405);
            return;
        }
        $userId = $this->currentUser['id'] ?? 0;
        $this->db->prepare("UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0")->execute([$userId]);
        Response::json(['success' => true]);
    }

    // ─── Register FCM Token ───────────────────────────────────────────────────────

    public function registerPushToken(): void
    {
        if (!Request::isPost()) {
            Response::json(['error' => 'Method not allowed.'], 405);
            return;
        }

        $input    = $this->getJson();
        $fcmToken = trim($input['fcm_token'] ?? '');
        $platform = $input['platform'] ?? 'unknown'; // android|ios|web

        if (empty($fcmToken)) {
            Response::json(['error' => 'fcm_token is required.'], 422);
            return;
        }

        $userId = $this->currentUser['id'] ?? 0;
        $this->db->prepare("
            INSERT INTO user_push_tokens (user_id, fcm_token, platform)
            VALUES (:uid, :token, :platform)
            ON DUPLICATE KEY UPDATE fcm_token=VALUES(fcm_token), platform=VALUES(platform), updated_at=NOW()
        ")->execute([':uid' => $userId, ':token' => $fcmToken, ':platform' => $platform]);

        Response::json(['success' => true]);
    }

    // ─── Dashboard Summary ────────────────────────────────────────────────────────

    public function dashboardSummary(): void
    {
        $empId  = $this->getEmployeeId();
        $userId = $this->currentUser['id'] ?? 0;

        $today     = date('Y-m-d');
        $monthFrom = date('Y-m-01');

        $attendance = null;
        $leaveBalance = null;

        if ($empId) {
            $attModel   = new AttendanceModel();
            $leaveModel = new LeaveModel();

            $attendance   = $attModel->getRecordByEmployeeDate($empId, $today);
            $lastPunch    = $attModel->getLastPunch($empId);
            $monthStats   = $attModel->getAttendanceStats($empId, $monthFrom, $today);
            $leaveBalance = $leaveModel->getLeaveBalance($empId, (int)date('Y'));
        }

        $unreadCount = (int)$this->db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0")
                                     ->execute([$userId]) ? $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0")->fetchColumn() : 0;

        $notifStmt = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $notifStmt->execute([$userId]);
        $unreadCount = (int)$notifStmt->fetchColumn();

        Response::json([
            'today'          => $today,
            'server_time'    => date('Y-m-d H:i:s'),
            'attendance'     => $attendance,
            'last_punch'     => $lastPunch ?? null,
            'month_stats'    => $monthStats ?? null,
            'leave_balance'  => $leaveBalance,
            'unread_count'   => $unreadCount,
        ]);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────────

    private function requireMobileAuth(): void
    {
        $publicRoutes = ['/mobile/login', '/mobile/refresh'];
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

        foreach ($publicRoutes as $route) {
            if (str_ends_with($uri, $route)) {
                return;
            }
        }

        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            Response::json(['error' => 'Authentication required.'], 401);
            exit;
        }

        $token   = substr($header, 7);
        $payload = JwtAuth::verify($token);

        if (!$payload || ($payload['type'] ?? '') !== 'mobile') {
            Response::json(['error' => 'Invalid or expired token.'], 401);
            exit;
        }

        $stmt = $this->db->prepare("SELECT id, name, email, role_id FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$payload['sub']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::json(['error' => 'Account deactivated.'], 401);
            exit;
        }

        $this->currentUser = array_merge($user, [
            'employee_id' => $payload['employee_id'] ?? null,
        ]);
    }

    private function getEmployeeId(): ?int
    {
        $id = $this->currentUser['employee_id'] ?? null;
        return $id ? (int)$id : null;
    }

    private function getJson(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return Request::post() ?: [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function formatProfile(array $emp): array
    {
        return [
            'id'              => $emp['id'],
            'employee_code'   => $emp['employee_code'],
            'full_name'       => $emp['full_name'],
            'email'           => $emp['email'],
            'phone'           => $emp['phone'],
            'gender'          => $emp['gender'],
            'date_of_birth'   => $emp['date_of_birth'],
            'join_date'       => $emp['join_date'],
            'department_name' => $emp['department_name'] ?? null,
            'designation_name'=> $emp['designation_name'] ?? null,
            'profile_photo'   => $emp['profile_photo'],
            'status'          => $emp['status'],
            'badge_number'    => $emp['badge_number'] ?? null,
            'blood_group'     => $emp['blood_group'] ?? null,
        ];
    }
}
