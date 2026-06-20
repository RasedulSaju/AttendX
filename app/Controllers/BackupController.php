<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\CSRF;
use App\Core\AuditLogger;
use App\Core\Database;
use PDO;

class BackupController extends Controller
{
    private string $backupPath;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('system.backup');
        $this->backupPath = rtrim(config('backup.path', storage_path('backups')), '/\\');
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0750, true);
        }
    }

    // ─── Index ───────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $backups = $this->listBackups();

        $this->view('system/backup', [
            'title'      => 'Backup & Restore',
            'backups'    => $backups,
            'disk_free'  => disk_free_space($this->backupPath),
            'csrf_token' => CSRF::generate(),
        ]);
    }

    // ─── Create Backup ────────────────────────────────────────────────────────────

    public function create(): void
    {
        if (!Request::isPost()) {
            Response::json(['success' => false], 405);
            return;
        }
        CSRF::verify();

        $type   = Request::post('type', 'full'); // full | db_only | files_only
        $result = match ($type) {
            'db_only'    => $this->backupDatabase(),
            'files_only' => $this->backupFiles(),
            default      => $this->backupFull(),
        };

        if ($result['success']) {
            AuditLogger::log('system', 'backup_created', "Backup created: {$result['filename']} (type: $type)");
        }

        Response::json($result);
    }

    // ─── Download ─────────────────────────────────────────────────────────────────

    public function download(string $filename): void
    {
        $filename = basename($filename); // Prevent path traversal
        $path     = $this->backupPath . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($path) || !str_ends_with($filename, '.sql.gz') && !str_ends_with($filename, '.tar.gz') && !str_ends_with($filename, '.sql')) {
            Session::flash('error', 'Backup file not found.');
            Response::redirect('/system/backup');
            return;
        }

        AuditLogger::log('system', 'backup_downloaded', "Backup downloaded: $filename");

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        header('Pragma: no-cache');
        readfile($path);
        exit;
    }

    // ─── Delete ───────────────────────────────────────────────────────────────────

    public function delete(): void
    {
        if (!Request::isPost()) {
            Response::json(['success' => false], 405);
            return;
        }
        CSRF::verify();

        $filename = basename(Request::post('filename', ''));
        $path     = $this->backupPath . DIRECTORY_SEPARATOR . $filename;

        if (!$filename || !file_exists($path)) {
            Response::json(['success' => false, 'message' => 'File not found.'], 404);
            return;
        }

        unlink($path);
        AuditLogger::log('system', 'backup_deleted', "Backup deleted: $filename");
        Response::json(['success' => true]);
    }

    // ─── Restore ──────────────────────────────────────────────────────────────────

    public function restore(): void
    {
        if (!Request::isPost()) {
            Response::json(['success' => false], 405);
            return;
        }
        CSRF::verify();
        $this->requirePermission('system.backup.restore');

        $filename = basename(Request::post('filename', ''));
        $path     = $this->backupPath . DIRECTORY_SEPARATOR . $filename;

        if (!$filename || !file_exists($path)) {
            Response::json(['success' => false, 'message' => 'File not found.'], 404);
            return;
        }

        if (!str_ends_with($filename, '.sql') && !str_ends_with($filename, '.sql.gz')) {
            Response::json(['success' => false, 'message' => 'Only SQL backups can be restored.'], 422);
            return;
        }

        try {
            $cfg = config('database');
            $sqlFile = $path;

            if (str_ends_with($filename, '.gz')) {
                $sqlFile = $this->backupPath . DIRECTORY_SEPARATOR . 'restore_tmp.sql';
                file_put_contents($sqlFile, gzdecode(file_get_contents($path)));
            }

            $cmd = sprintf(
                'mysql -u%s -p%s %s < %s 2>&1',
                escapeshellarg($cfg['username']),
                escapeshellarg($cfg['password']),
                escapeshellarg($cfg['database']),
                escapeshellarg($sqlFile)
            );

            exec($cmd, $output, $exitCode);

            if (isset($sqlFile) && $sqlFile !== $path && file_exists($sqlFile)) {
                unlink($sqlFile);
            }

            if ($exitCode !== 0) {
                throw new \RuntimeException('Restore failed: ' . implode("\n", $output));
            }

            AuditLogger::log('system', 'backup_restored', "Backup restored from: $filename");
            Response::json(['success' => true, 'message' => 'Database restored successfully.']);

        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Upload Backup ────────────────────────────────────────────────────────────

    public function upload(): void
    {
        if (!Request::isPost()) {
            Response::json(['success' => false], 405);
            return;
        }
        CSRF::verify();

        if (empty($_FILES['backup_file'])) {
            Response::json(['success' => false, 'message' => 'No file uploaded.'], 422);
            return;
        }

        $file = $_FILES['backup_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::json(['success' => false, 'message' => 'Upload error.'], 422);
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['sql', 'gz'])) {
            Response::json(['success' => false, 'message' => 'Only .sql and .sql.gz files allowed.'], 422);
            return;
        }

        if ($file['size'] > 500 * 1024 * 1024) { // 500MB limit
            Response::json(['success' => false, 'message' => 'File too large (max 500MB).'], 422);
            return;
        }

        $filename = 'uploaded_' . date('Ymd_His') . '.' . $ext;
        $dest     = $this->backupPath . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Response::json(['success' => false, 'message' => 'Failed to save uploaded file.'], 500);
            return;
        }

        Response::json(['success' => true, 'filename' => $filename]);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────────

    private function backupDatabase(): array
    {
        try {
            $cfg      = config('database');
            $filename = 'db_backup_' . date('Ymd_His') . '.sql.gz';
            $path     = $this->backupPath . DIRECTORY_SEPARATOR . $filename;

            $cmd = sprintf(
                'mysqldump --single-transaction --routines --triggers -u%s -p%s %s 2>/dev/null | gzip > %s',
                escapeshellarg($cfg['username']),
                escapeshellarg($cfg['password']),
                escapeshellarg($cfg['database']),
                escapeshellarg($path)
            );

            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || !file_exists($path) || filesize($path) < 100) {
                return ['success' => false, 'message' => 'Database backup failed.'];
            }

            return [
                'success'  => true,
                'filename' => $filename,
                'size'     => filesize($path),
                'message'  => 'Database backup created successfully.',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function backupFiles(): array
    {
        try {
            $appRoot  = base_path();
            $filename = 'files_backup_' . date('Ymd_His') . '.tar.gz';
            $path     = $this->backupPath . DIRECTORY_SEPARATOR . $filename;

            $exclude = '--exclude=' . escapeshellarg($this->backupPath)
                     . ' --exclude=' . escapeshellarg($appRoot . '/vendor')
                     . ' --exclude=' . escapeshellarg($appRoot . '/node_modules');

            $cmd = sprintf('tar czf %s %s -C %s . 2>/dev/null', escapeshellarg($path), $exclude, escapeshellarg($appRoot));
            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0) {
                return ['success' => false, 'message' => 'File backup failed.'];
            }

            return [
                'success'  => true,
                'filename' => $filename,
                'size'     => filesize($path),
                'message'  => 'File backup created successfully.',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function backupFull(): array
    {
        $db    = $this->backupDatabase();
        $files = $this->backupFiles();

        if (!$db['success']) return $db;
        if (!$files['success']) return $files;

        return [
            'success'  => true,
            'filename' => $db['filename'],
            'message'  => 'Full backup created: DB + Files.',
            'files'    => [$db['filename'], $files['filename']],
        ];
    }

    private function listBackups(): array
    {
        $files = glob($this->backupPath . DIRECTORY_SEPARATOR . '*.{sql,sql.gz,tar.gz}', GLOB_BRACE) ?: [];
        $out   = [];
        foreach ($files as $f) {
            $out[] = [
                'filename' => basename($f),
                'size'     => filesize($f),
                'modified' => filemtime($f),
                'type'     => str_contains(basename($f), 'db_') ? 'Database' : (str_contains(basename($f), 'files_') ? 'Files' : 'Uploaded'),
            ];
        }
        usort($out, fn($a, $b) => $b['modified'] <=> $a['modified']);
        return $out;
    }
}
