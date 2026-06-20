<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use PDO;

class AuditController extends Controller
{
    private PDO $db;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('system.audit');
        $this->db = Database::getInstance()->getConnection();
    }

    // ─── Audit Log List ──────────────────────────────────────────────────────────

    public function index(): void
    {
        $filters = [
            'user_id'    => (int)Request::get('user_id'),
            'module'     => Request::get('module', ''),
            'action'     => Request::get('action', ''),
            'date_from'  => Request::get('date_from', date('Y-m-d', strtotime('-7 days'))),
            'date_to'    => Request::get('date_to', date('Y-m-d')),
            'ip_address' => Request::get('ip_address', ''),
        ];
        $filters = array_filter($filters, fn($v) => $v !== '' && $v !== 0);

        $page    = max(1, (int)Request::get('page', 1));
        $perPage = 50;
        $logs    = $this->getLogs($filters, $perPage, ($page - 1) * $perPage);
        $total   = $this->countLogs($filters);

        $users   = $this->db->query("SELECT id, name FROM users ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $modules = $this->db->query("SELECT DISTINCT module FROM audit_logs ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);

        $this->view('system/audit_logs', [
            'title'   => 'Audit Logs',
            'logs'    => $logs,
            'filters' => $filters,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'users'   => $users,
            'modules' => $modules,
        ]);
    }

    // ─── Detail ──────────────────────────────────────────────────────────────────

    public function detail(int $id): void
    {
        $stmt = $this->db->prepare("
            SELECT al.*, u.name AS user_name, u.email AS user_email
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE al.id = ?
        ");
        $stmt->execute([$id]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$log) {
            Response::json(['success' => false, 'message' => 'Log not found.'], 404);
            return;
        }

        if (!empty($log['old_values'])) {
            $log['old_values'] = json_decode($log['old_values'], true);
        }
        if (!empty($log['new_values'])) {
            $log['new_values'] = json_decode($log['new_values'], true);
        }

        Response::json(['success' => true, 'log' => $log]);
    }

    // ─── Export ──────────────────────────────────────────────────────────────────

    public function export(): void
    {
        $this->requirePermission('system.audit.export');

        $filters = [
            'user_id'   => (int)Request::get('user_id'),
            'module'    => Request::get('module', ''),
            'date_from' => Request::get('date_from', date('Y-m-d', strtotime('-30 days'))),
            'date_to'   => Request::get('date_to', date('Y-m-d')),
        ];
        $filters = array_filter($filters, fn($v) => $v !== '' && $v !== 0);

        $logs = $this->getLogs($filters, 5000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit_log_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'User', 'Module', 'Action', 'Description', 'IP Address', 'User Agent', 'Created At']);

        foreach ($logs as $row) {
            fputcsv($out, [
                $row['id'],
                $row['user_name'] ?? 'System',
                $row['module'],
                $row['action'],
                $row['description'],
                $row['ip_address'],
                $row['user_agent'],
                $row['created_at'],
            ]);
        }

        fclose($out);
        exit;
    }

    // ─── Statistics ──────────────────────────────────────────────────────────────

    public function stats(): void
    {
        $period = Request::get('period', '7d');
        $dateFrom = match ($period) {
            '30d'  => date('Y-m-d', strtotime('-30 days')),
            '90d'  => date('Y-m-d', strtotime('-90 days')),
            default => date('Y-m-d', strtotime('-7 days')),
        };

        $byModule = $this->db->prepare("
            SELECT module, COUNT(*) AS count
            FROM audit_logs
            WHERE created_at >= :from
            GROUP BY module
            ORDER BY count DESC
        ");
        $byModule->execute([':from' => $dateFrom]);

        $byUser = $this->db->prepare("
            SELECT u.name AS user_name, COUNT(al.id) AS count
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE al.created_at >= :from
            GROUP BY al.user_id
            ORDER BY count DESC
            LIMIT 10
        ");
        $byUser->execute([':from' => $dateFrom]);

        $daily = $this->db->prepare("
            SELECT DATE(created_at) AS date, COUNT(*) AS count
            FROM audit_logs
            WHERE created_at >= :from
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $daily->execute([':from' => $dateFrom]);

        Response::json([
            'by_module' => $byModule->fetchAll(PDO::FETCH_ASSOC),
            'by_user'   => $byUser->fetchAll(PDO::FETCH_ASSOC),
            'daily'     => $daily->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    // ─── Purge Old Logs ──────────────────────────────────────────────────────────

    public function purge(): void
    {
        if (!Request::isPost()) {
            Response::json(['success' => false], 405);
            return;
        }
        \App\Core\CSRF::verify();
        $this->requirePermission('system.audit.purge');

        $days = max(30, (int)Request::post('days', 90));
        $stmt = $this->db->prepare("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        $deleted = $stmt->rowCount();
        \App\Core\AuditLogger::log('system', 'purge_audit_logs', "Purged $deleted audit log entries older than $days days.");

        Response::json(['success' => true, 'deleted' => $deleted]);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────────

    private function getLogs(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $stmt = $this->db->prepare("
            SELECT al.*, u.name AS user_name
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE $where
            ORDER BY al.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function countLogs(array $filters): int
    {
        [$where, $params] = $this->buildFilters($filters);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM audit_logs WHERE $where");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function buildFilters(array $filters): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[]            = 'al.user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }
        if (!empty($filters['module'])) {
            $where[]           = 'al.module = :module';
            $params[':module'] = $filters['module'];
        }
        if (!empty($filters['action'])) {
            $where[]           = 'al.action = :action';
            $params[':action'] = $filters['action'];
        }
        if (!empty($filters['ip_address'])) {
            $where[]              = 'al.ip_address = :ip';
            $params[':ip']        = $filters['ip_address'];
        }
        if (!empty($filters['date_from'])) {
            $where[]             = 'al.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]           = 'al.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        return [implode(' AND ', $where), $params];
    }
}
