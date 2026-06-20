<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\CSRF;
use App\Core\Database;
use PDO;

class SystemController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('system');
    }

    // ─── Settings ────────────────────────────────────────────────────────────────

    public function settings(): void
    {
        if (Request::isPost()) {
            CSRF::verify();
            $this->requirePermission('system.settings');
            $data = Request::post();
            $this->saveSettings($data);
            Session::flash('success', 'Settings saved successfully.');
            Response::redirect('/system/settings');
            return;
        }

        $this->view('system/settings', [
            'title'      => 'System Settings',
            'settings'   => $this->getAllSettings(),
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function saveSetting(): void
    {
        if (!Request::isPost()) {
            Response::json(['success' => false], 405);
            return;
        }
        CSRF::verify();
        $this->requirePermission('system.settings');

        $key   = trim(Request::post('key', ''));
        $value = Request::post('value', '');

        if (empty($key) || !preg_match('/^[a-z0-9_.]+$/', $key)) {
            Response::json(['success' => false, 'message' => 'Invalid key.'], 422);
            return;
        }

        $this->updateSetting($key, $value);
        Response::json(['success' => true]);
    }

    // ─── System Info ─────────────────────────────────────────────────────────────

    public function info(): void
    {
        $db    = Database::getInstance()->getConnection();
        $dbVer = $db->query("SELECT VERSION()")->fetchColumn();

        $info = [
            'php_version'     => PHP_VERSION,
            'db_version'      => $dbVer,
            'os'              => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'timezone'        => date_default_timezone_get(),
            'disk_free'       => disk_free_space('/'),
            'disk_total'      => disk_total_space('/'),
            'memory_limit'    => ini_get('memory_limit'),
            'upload_max'      => ini_get('upload_max_filesize'),
            'max_execution'   => ini_get('max_execution_time'),
        ];

        $dbStats = $this->getDatabaseStats();

        $this->view('system/info', [
            'title'   => 'System Information',
            'info'    => $info,
            'dbStats' => $dbStats,
        ]);
    }

    // ─── User Management ──────────────────────────────────────────────────────────

    public function users(): void
    {
        $db    = Database::getInstance()->getConnection();
        $users = $db->query("
            SELECT u.*, r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            ORDER BY u.name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $roles = $db->query("SELECT * FROM roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        $this->view('system/users', [
            'title'      => 'User Management',
            'users'      => $users,
            'roles'      => $roles,
            'csrf_token' => CSRF::generate(),
        ]);
    }

    public function saveUser(): void
    {
        if (!Request::isPost()) {
            Response::redirect('/system/users');
            return;
        }
        CSRF::verify();
        $this->requirePermission('system.users');

        $data  = Request::post();
        $id    = !empty($data['id']) ? (int)$data['id'] : null;

        $rules = [
            'name'    => 'required|min:2|max:100',
            'email'   => 'required|email|max:150',
            'role_id' => 'required|integer',
        ];
        if (!$id) {
            $rules['password'] = 'required|min:8|max:72';
        }

        if (!$this->validate($data, $rules)) {
            Session::flash('error', $this->getValidationErrors());
            Response::redirect('/system/users');
            return;
        }

        $db = Database::getInstance()->getConnection();

        // Check email uniqueness
        $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$data['email'], $id ?? 0]);
        if ($check->fetchColumn()) {
            Session::flash('error', 'Email already in use.');
            Response::redirect('/system/users');
            return;
        }

        if ($id) {
            $params = [
                ':name'    => strip_tags($data['name']),
                ':email'   => strtolower(trim($data['email'])),
                ':role_id' => (int)$data['role_id'],
                ':status'  => $data['status'] ?? 'active',
                ':id'      => $id,
            ];
            $sql = "UPDATE users SET name=:name, email=:email, role_id=:role_id, status=:status";
            if (!empty($data['password'])) {
                $sql .= ", password=:password";
                $params[':password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            }
            $sql .= " WHERE id=:id";
            $db->prepare($sql)->execute($params);
            Session::flash('success', 'User updated.');
        } else {
            $stmt = $db->prepare("
                INSERT INTO users (name, email, password, role_id, status)
                VALUES (:name, :email, :password, :role_id, :status)
            ");
            $stmt->execute([
                ':name'     => strip_tags($data['name']),
                ':email'    => strtolower(trim($data['email'])),
                ':password' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
                ':role_id'  => (int)$data['role_id'],
                ':status'   => 'active',
            ]);
            Session::flash('success', 'User created.');
        }

        Response::redirect('/system/users');
    }

    public function toggleUser(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('system.users');

        // Prevent self-deactivation
        if ($id === $this->auth->id()) {
            Response::json(['success' => false, 'message' => 'Cannot deactivate your own account.'], 403);
            return;
        }

        $db = Database::getInstance()->getConnection();
        $db->prepare("
            UPDATE users SET status = IF(status='active','inactive','active') WHERE id = ?
        ")->execute([$id]);

        Response::json(['success' => true]);
    }

    public function deleteUser(int $id): void
    {
        CSRF::verify();
        $this->requirePermission('system.users');

        if ($id === $this->auth->id()) {
            Response::json(['success' => false, 'message' => 'Cannot delete your own account.'], 403);
            return;
        }

        $db = Database::getInstance()->getConnection();
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        Response::json(['success' => true]);
    }

    // ─── Roles & Permissions ──────────────────────────────────────────────────────

    public function roles(): void
    {
        $db    = Database::getInstance()->getConnection();
        $roles = $db->query("
            SELECT r.*, COUNT(u.id) AS user_count
            FROM roles r
            LEFT JOIN users u ON u.role_id = r.id
            GROUP BY r.id
            ORDER BY r.name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $permissions = $db->query("SELECT * FROM permissions ORDER BY module, name")->fetchAll(PDO::FETCH_ASSOC);
        $rolePerms   = [];
        $rp = $db->query("SELECT * FROM role_permissions")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rp as $row) {
            $rolePerms[$row['role_id']][$row['permission_id']] = true;
        }

        $this->view('system/roles', [
            'title'       => 'Roles & Permissions',
            'roles'       => $roles,
            'permissions' => $permissions,
            'rolePerms'   => $rolePerms,
            'csrf_token'  => CSRF::generate(),
        ]);
    }

    public function saveRole(): void
    {
        if (!Request::isPost()) {
            Response::redirect('/system/roles');
            return;
        }
        CSRF::verify();
        $this->requirePermission('system.roles');

        $data = Request::post();
        $id   = !empty($data['id']) ? (int)$data['id'] : null;

        if (empty($data['name'])) {
            Session::flash('error', 'Role name is required.');
            Response::redirect('/system/roles');
            return;
        }

        $db = Database::getInstance()->getConnection();

        if ($id) {
            $db->prepare("UPDATE roles SET name=:name, description=:desc WHERE id=:id")->execute([
                ':name' => strip_tags($data['name']),
                ':desc' => strip_tags($data['description'] ?? ''),
                ':id'   => $id,
            ]);
        } else {
            $db->prepare("INSERT INTO roles (name, description) VALUES (:name, :desc)")->execute([
                ':name' => strip_tags($data['name']),
                ':desc' => strip_tags($data['description'] ?? ''),
            ]);
            $id = (int)$db->lastInsertId();
        }

        // Sync permissions
        $db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$id]);
        $perms = $data['permissions'] ?? [];
        if (!empty($perms)) {
            $stmt = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ((array)$perms as $permId) {
                $stmt->execute([$id, (int)$permId]);
            }
        }

        Session::flash('success', 'Role saved.');
        Response::redirect('/system/roles');
    }

    // ─── Email Test ───────────────────────────────────────────────────────────────

    public function testEmail(): void
    {
        if (!Request::isPost()) {
            Response::json(['success' => false], 405);
            return;
        }
        CSRF::verify();
        $this->requirePermission('system.settings');

        $to = Request::post('email', '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Response::json(['success' => false, 'message' => 'Invalid email address.'], 422);
            return;
        }

        try {
            $mailer = new \App\Services\MailService();
            $mailer->sendRaw($to, 'AttendX Test Email', '<p>Email configuration is working correctly.</p>');
            Response::json(['success' => true, 'message' => 'Test email sent to ' . $to]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    private function getAllSettings(): array
    {
        $db   = Database::getInstance()->getConnection();
        $rows = $db->query("SELECT `key`, `value`, `group`, `type`, `label` FROM system_settings ORDER BY `group`, `key`")->fetchAll(PDO::FETCH_ASSOC);
        $out  = [];
        foreach ($rows as $r) {
            $out[$r['group']][$r['key']] = $r;
        }
        return $out;
    }

    private function saveSettings(array $data): void
    {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO system_settings (`key`, `value`) VALUES (:key, :value)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ");
        foreach ($data as $key => $value) {
            if (!preg_match('/^[a-z0-9_.]+$/', $key)) {
                continue;
            }
            $stmt->execute([':key' => $key, ':value' => is_array($value) ? json_encode($value) : (string)$value]);
        }
    }

    private function updateSetting(string $key, mixed $value): void
    {
        $db = Database::getInstance()->getConnection();
        $db->prepare("
            INSERT INTO system_settings (`key`, `value`) VALUES (:key, :value)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ")->execute([':key' => $key, ':value' => is_array($value) ? json_encode($value) : (string)$value]);
    }

    private function getDatabaseStats(): array
    {
        $db    = Database::getInstance()->getConnection();
        $stats = $db->query("
            SELECT table_name, table_rows,
                   ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            ORDER BY (data_length + index_length) DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        return $stats;
    }
}
