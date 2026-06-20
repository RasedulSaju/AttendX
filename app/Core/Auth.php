<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Database;
use PDO;

class Auth
{
    private static ?array $user = null;

    // ─── Login / Logout ───────────────────────────────────────────────────────

    public static function attempt(string $email, string $password): bool
    {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT u.*, r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.email = ? AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        // Update last login
        $db->prepare("UPDATE users SET last_login_at=NOW(), last_login_ip=? WHERE id=?")
           ->execute([Request::ip(), $user['id']]);

        self::loginUser($user);
        return true;
    }

    public static function loginUser(array $user): void
    {
        Session::regenerate();
        Session::set('user_id',   $user['id']);
        Session::set('user_name', $user['name']);
        Session::set('user_email',$user['email']);
        Session::set('user_role', $user['role_name'] ?? '');
        Session::set('role_id',   $user['role_id']   ?? null);
        self::$user = $user;

        AuditLogger::logAuth('login', $user['email'], true, (int)$user['id']);
    }

    public static function logout(): void
    {
        $user = self::user();
        if ($user) {
            AuditLogger::logAuth('logout', $user['email'], true, (int)$user['id']);
        }
        Session::destroy();
        self::$user = null;
    }

    // ─── Check ────────────────────────────────────────────────────────────────

    public static function check(): bool
    {
        return Session::has('user_id');
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    // ─── Current User ─────────────────────────────────────────────────────────

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        if (!Session::has('user_id')) {
            return null;
        }

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT u.*, r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.id = ? AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([Session::get('user_id')]);
        self::$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return self::$user;
    }

    public static function id(): ?int
    {
        return Session::has('user_id') ? (int)Session::get('user_id') : null;
    }

    public static function role(): string
    {
        return Session::get('user_role', '');
    }

    // ─── Permissions ──────────────────────────────────────────────────────────

    /**
     * Check if current user has a permission.
     * Supports dot-notation: 'payroll.approve'
     */
    public static function can(string $permission): bool
    {
        $user = self::user();
        if (!$user) return false;

        // Super admin has all permissions
        if (($user['role_name'] ?? '') === 'Super Admin') return true;

        $roleId = (int)($user['role_id'] ?? 0);
        if (!$roleId) return false;

        // Cache permissions per request
        static $cache = [];
        if (!isset($cache[$roleId])) {
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT p.name FROM role_permissions rp
                JOIN permissions p ON p.id = rp.permission_id
                WHERE rp.role_id = ?
            ");
            $stmt->execute([$roleId]);
            $cache[$roleId] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $perms = $cache[$roleId];

        // Exact match
        if (in_array($permission, $perms, true)) return true;

        // Parent permission grants children: 'payroll' grants 'payroll.approve'
        $parent = explode('.', $permission)[0];
        return in_array($parent, $perms, true);
    }

    public static function cannot(string $permission): bool
    {
        return !self::can($permission);
    }

    /**
     * Abort with 403 if permission missing.
     */
    public static function authorize(string $permission): void
    {
        if (!self::can($permission)) {
            Response::abort(403, 'You do not have permission to perform this action.');
        }
    }

    // ─── Password ─────────────────────────────────────────────────────────────

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function checkPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
