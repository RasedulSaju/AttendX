<?php
declare(strict_types=1);

/**
 * AttendX – Web Installer
 * Access via: http://your-domain/setup.php
 * DELETE THIS FILE after installation completes.
 */

define('BASE_PATH', dirname(__DIR__));
session_start();

if (file_exists(BASE_PATH . '/.env') && !isset($_GET['force'])) {
    die('<div style="font-family:sans-serif;padding:40px;background:#0a0c14;color:#e2e8f0;min-height:100vh">'
        . '<h2>AttendX is already installed.</h2>'
        . '<p>A .env file already exists. To reinstall, add <code>?force=1</code> to the URL, '
        . 'or delete the .env file manually.</p>'
        . '<p><strong>Security:</strong> Please delete this setup.php file now.</p></div>');
}

$step   = (int)($_GET['step'] ?? 1);
$error  = '';
$success = '';

// ─── Step 2: Test DB connection & save to session ────────────────────────────

if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';

    try {
        $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $_SESSION['install_db'] = compact('dbHost', 'dbPort', 'dbName', 'dbUser', 'dbPass');
        header('Location: setup.php?step=3');
        exit;
    } catch (PDOException $e) {
        $error = 'Database connection failed: ' . htmlspecialchars($e->getMessage());
    }
}

// ─── Step 3: Run schema + finalize ────────────────────────────────────────────

if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = $_SESSION['install_db'] ?? null;
    if (!$db) {
        header('Location: setup.php?step=1');
        exit;
    }

    $appUrl  = rtrim(trim($_POST['app_url'] ?? ''), '/');
    $appName = trim($_POST['app_name'] ?? 'AttendX');
    $appTz   = trim($_POST['app_tz'] ?? 'Asia/Dhaka');
    $admsPort= trim($_POST['adms_port'] ?? '8080');
    $sdkPort = trim($_POST['sdk_port'] ?? '4370');

    try {
        $pdo = new PDO(
            "mysql:host={$db['dbHost']};port={$db['dbPort']};dbname={$db['dbName']};charset=utf8mb4",
            $db['dbUser'], $db['dbPass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $sqlFile = BASE_PATH . '/database/install.sql';
        if (!file_exists($sqlFile)) {
            throw new RuntimeException('database/install.sql not found.');
        }

        $sql = file_get_contents($sqlFile);
        $statements = array_filter(array_map('trim', explode(";\n", $sql)));
        $executed = 0;

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--')) continue;
            try {
                $pdo->exec($stmt);
                $executed++;
            } catch (PDOException $e) {
                if (!str_contains($e->getMessage(), 'already exists') && !str_contains($e->getMessage(), 'Duplicate')) {
                    throw $e;
                }
            }
        }

        // Generate keys
        $appKey = bin2hex(random_bytes(16));
        $jwtKey = bin2hex(random_bytes(32));

        $envContent = <<<ENV
APP_NAME={$appName}
APP_ENV=production
APP_DEBUG=false
APP_URL={$appUrl}
APP_KEY={$appKey}
APP_TZ={$appTz}
APP_LOCALE=en

DB_HOST={$db['dbHost']}
DB_PORT={$db['dbPort']}
DB_NAME={$db['dbName']}
DB_USER={$db['dbUser']}
DB_PASS={$db['dbPass']}

SESSION_NAME=attendx_session
SESSION_LIFETIME=7200
SESSION_SECURE=false

JWT_SECRET={$jwtKey}
JWT_TTL=86400
JWT_REFRESH_TTL=604800
JWT_MOBILE_TTL=604800

MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_NAME={$appName}
MAIL_FROM_EMAIL=

ADMS_PORT={$admsPort}
ADMS_PORT_SECONDARY=8081
SDK_PORT={$sdkPort}
DEVICE_PASSWORD=0
PULL_INTERVAL_SEC=300
CONN_TIMEOUT_SEC=15

BACKUP_PATH=storage/backups
UPLOAD_PATH=storage/uploads
LOG_LEVEL=INFO

FCM_SERVER_KEY=
ENV;

        file_put_contents(BASE_PATH . '/.env', $envContent);
        if (stripos(PHP_OS_FAMILY, 'Windows') === false) {
            @chmod(BASE_PATH . '/.env', 0640);
        }

        // Persist app/company name into system_settings
        try {
            $pdo->prepare("UPDATE system_settings SET `value`=? WHERE `key`='app.name'")->execute([$appName]);
            $pdo->prepare("UPDATE system_settings SET `value`=? WHERE `key`='company.name'")->execute([$appName]);
            $pdo->prepare("UPDATE system_settings SET `value`=? WHERE `key`='mail.from_name'")->execute([$appName]);
        } catch (Throwable) {}

        foreach (['logs','backups','uploads','uploads/avatars','uploads/documents','uploads/leave_docs','cache'] as $dir) {
            $path = BASE_PATH . '/storage/' . $dir;
            if (!is_dir($path)) mkdir($path, 0750, true);
        }

        unset($_SESSION['install_db']);
        header('Location: setup.php?step=4&executed=' . $executed);
        exit;

    } catch (Throwable $e) {
        $error = 'Installation failed: ' . htmlspecialchars($e->getMessage());
        $step = 2; // back to db step
    }
}

// ─── Render ────────────────────────────────────────────────────────────────────

function checkRequirement(string $label, bool $ok): string
{
    $icon = $ok ? '✓' : '✗';
    $color = $ok ? '#22c55e' : '#ef4444';
    return "<div style='display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06)'>"
        . "<span>{$label}</span><span style='color:{$color};font-weight:600'>{$icon}</span></div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AttendX Setup</title>
<style>
*{box-sizing:border-box}
body{font-family:'Segoe UI',Arial,sans-serif;background:#0a0c14;color:#e2e8f0;min-height:100vh;margin:0;padding:40px 20px}
.wrap{max-width:600px;margin:0 auto}
.brand{text-align:center;font-size:1.8rem;font-weight:700;color:#6366f1;margin-bottom:8px}
.brand span{color:#e2e8f0}
.subtitle{text-align:center;color:#6b7280;margin-bottom:32px;font-size:.9rem}
.steps{display:flex;justify-content:center;gap:8px;margin-bottom:32px}
.step-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:600;background:rgba(255,255,255,.08);color:#6b7280}
.step-dot.active{background:#6366f1;color:#fff}
.step-dot.done{background:#22c55e;color:#fff}
.card{background:#111827;border:1px solid rgba(255,255,255,.07);border-radius:16px;padding:32px}
label{display:block;font-size:.85rem;color:#9ca3af;margin-bottom:6px;margin-top:16px}
label:first-child{margin-top:0}
input,select{width:100%;padding:10px 14px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#e2e8f0;font-size:.9rem}
input:focus,select:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.2)}
.btn{display:inline-block;width:100%;margin-top:24px;padding:12px;background:#6366f1;border:none;border-radius:8px;color:#fff;font-weight:600;font-size:.95rem;cursor:pointer}
.btn:hover{background:#4f46e5}
.error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:.85rem}
.success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#86efac;padding:16px;border-radius:8px;margin-bottom:20px}
.credentials{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.3);border-radius:8px;padding:16px;margin:16px 0}
.credentials code{color:#a78bfa;font-size:1rem}
.warn{color:#fbbf24;font-size:.85rem;margin-top:16px}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">Attend<span>X</span></div>
  <p class="subtitle">Installation Wizard</p>

  <div class="steps">
    <div class="step-dot <?= $step==1?'active':($step>1?'done':'') ?>">1</div>
    <div class="step-dot <?= $step==2?'active':($step>2?'done':'') ?>">2</div>
    <div class="step-dot <?= $step==3?'active':($step>3?'done':'') ?>">3</div>
    <div class="step-dot <?= $step==4?'active':'' ?>">4</div>
  </div>

  <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>

  <div class="card">
    <?php if ($step === 1): ?>
      <h3 style="margin-top:0">System Requirements</h3>
      <?= checkRequirement('PHP Version ≥ 8.2', version_compare(PHP_VERSION, '8.2.0', '>=')) ?>
      <?= checkRequirement('PDO Extension', extension_loaded('pdo')) ?>
      <?= checkRequirement('PDO MySQL Driver', extension_loaded('pdo_mysql')) ?>
      <?= checkRequirement('Mbstring Extension', extension_loaded('mbstring')) ?>
      <?= checkRequirement('JSON Extension', extension_loaded('json')) ?>
      <?= checkRequirement('GD Extension', extension_loaded('gd')) ?>
      <?= checkRequirement('Fileinfo Extension', extension_loaded('fileinfo')) ?>
      <?= checkRequirement('Storage Directory Writable', is_writable(BASE_PATH) || is_writable(BASE_PATH . '/storage')) ?>
      <a href="setup.php?step=2" class="btn" style="text-decoration:none;text-align:center;display:block">Continue →</a>

    <?php elseif ($step === 2): ?>
      <h3 style="margin-top:0">Database Configuration</h3>
      <form method="POST" action="setup.php?step=2">
        <label>Database Host</label><input type="text" name="db_host" value="127.0.0.1" required>
        <label>Database Port</label><input type="text" name="db_port" value="3306" required>
        <label>Database Name</label><input type="text" name="db_name" value="attendx" required>
        <label>Database Username</label><input type="text" name="db_user" value="root" required>
        <label>Database Password</label><input type="password" name="db_pass">
        <button type="submit" class="btn">Test Connection & Continue →</button>
      </form>

    <?php elseif ($step === 3): ?>
      <h3 style="margin-top:0">Application Settings</h3>
      <form method="POST" action="setup.php?step=3">
        <label>Company / Application Name</label><input type="text" name="app_name" placeholder="e.g. Acme Corp HR" value="AttendX" required>
        <label>Application URL</label><input type="text" name="app_url" value="http://<?= $_SERVER['HTTP_HOST'] ?? 'localhost' ?>" required>
        <label>Timezone</label><input type="text" name="app_tz" value="Asia/Dhaka" required>
        <label>ADMS Push Port (device → server)</label><input type="text" name="adms_port" value="8080" required>
        <label>SDK Pull Port (server → device)</label><input type="text" name="sdk_port" value="4370" required>
        <button type="submit" class="btn">Install Database & Finish →</button>
      </form>

    <?php elseif ($step === 4): ?>
      <div class="success">
        <h3 style="margin-top:0">Installation Complete! 🎉</h3>
        <p>AttendX has been successfully installed (<?= (int)($_GET['executed'] ?? 0) ?> SQL statements executed).</p>
      </div>
      <div class="credentials">
        <p style="margin:0 0 8px"><strong>Login Credentials:</strong></p>
        <p style="margin:4px 0">Email: <code>admin@attendx.com</code></p>
        <p style="margin:4px 0">Password: <code>Admin@1234</code></p>
      </div>
      <p class="warn">⚠ Change the admin password immediately after first login.</p>
      <p class="warn">⚠ DELETE this setup.php file now for security.</p>
      <a href="/login" class="btn" style="text-decoration:none;text-align:center;display:block">Go to Login →</a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
