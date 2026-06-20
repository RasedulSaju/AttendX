<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Database;
use PDO;

class AuditLogger
{
    private static bool $enabled = true;

    public static function log(
        string  $module,
        string  $action,
        string  $description = '',
        array   $oldValues   = [],
        array   $newValues   = [],
        ?int    $userId      = null
    ): void {
        if (!self::$enabled) {
            return;
        }

        $userId = $userId ?? self::currentUserId();

        try {
            $db = Database::getInstance()->getConnection();
            $db->prepare("
                INSERT INTO audit_logs
                    (user_id, module, action, description, old_values, new_values, ip_address, user_agent, created_at)
                VALUES
                    (:user_id, :module, :action, :description, :old_values, :new_values, :ip, :ua, NOW())
            ")->execute([
                ':user_id'     => $userId,
                ':module'      => mb_substr($module, 0, 50),
                ':action'      => mb_substr($action, 0, 100),
                ':description' => mb_substr($description, 0, 65535),
                ':old_values'  => !empty($oldValues) ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                ':new_values'  => !empty($newValues) ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                ':ip'          => self::getIp(),
                ':ua'          => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        } catch (\Exception $e) {
            // Fail silently — don't break the app for audit failures
            error_log('[AuditLogger] Failed to write log: ' . $e->getMessage());
        }
    }

    /**
     * Log a model change (create/update/delete).
     * Automatically computes diff between old and new values.
     */
    public static function logChange(
        string  $module,
        string  $action,
        array   $oldData,
        array   $newData,
        string  $description = '',
        ?int    $userId      = null
    ): void {
        $diff = self::diff($oldData, $newData);
        self::log($module, $action, $description, $diff['old'], $diff['new'], $userId);
    }

    /**
     * Log authentication events (login, logout, failed attempts).
     */
    public static function logAuth(string $action, string $email, bool $success, ?int $userId = null): void
    {
        self::log(
            'auth',
            $action,
            ($success ? 'Successful' : 'Failed') . ' ' . $action . ' for ' . $email,
            [],
            [],
            $userId
        );
    }

    /**
     * Compute field-level diff between two arrays.
     * Only includes fields that changed.
     */
    public static function diff(array $old, array $new): array
    {
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'pin'];
        $changedOld = [];
        $changedNew = [];

        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            if ($oldVal === $newVal) {
                continue;
            }

            // Mask sensitive fields
            foreach ($sensitiveFields as $sf) {
                if (str_contains(strtolower((string)$key), $sf)) {
                    $oldVal = $oldVal !== null ? '***' : null;
                    $newVal = $newVal !== null ? '***' : null;
                    break;
                }
            }

            $changedOld[$key] = $oldVal;
            $changedNew[$key] = $newVal;
        }

        return ['old' => $changedOld, 'new' => $changedNew];
    }

    /**
     * Temporarily disable audit logging (e.g. during bulk imports).
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Query audit logs with filters.
     */
    public static function query(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[]            = 'user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }
        if (!empty($filters['module'])) {
            $where[]           = 'module = :module';
            $params[':module'] = $filters['module'];
        }
        if (!empty($filters['action'])) {
            $where[]           = 'action LIKE :action';
            $params[':action'] = '%' . $filters['action'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $where[]              = 'created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]            = 'created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT al.*, u.name AS user_name
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY al.created_at DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private static function currentUserId(): ?int
    {
        try {
            return Session::get('user_id') ? (int)Session::get('user_id') : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function getIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
