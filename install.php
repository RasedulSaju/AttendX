<?php
declare(strict_types=1);

/**
 * AttendX – CLI Installer
 * Usage: php install.php
 *
 * Creates .env, runs database/install.sql, verifies PHP extensions,
 * sets up storage directories, and prints default admin credentials.
 */

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from the command line.\n");
}

define('BASE_PATH', __DIR__);

function out(string $msg, string $type = 'info'): void
{
    $colors = ['info' => "\033[36m", 'ok' => "\033[32m", 'warn' => "\033[33m", 'err' => "\033[31m"];
    $reset  = "\033[0m";
    $prefix = ['info' => '[*]', 'ok' => '[✓]', 'warn' => '[!]', 'err' => '[✗]'];
    echo ($colors[$type] ?? '') . $prefix[$type] . ' ' . $msg . $reset . "\n";
}

function prompt(string $label, string $default = ''): string
{
    $suffix = $default !== '' ? " [{$default}]" : '';
    echo "{$label}{$suffix}: ";
    $line = trim(fgets(STDIN));
    return $line === '' ? $default : $line;
}

function promptSecret(string $label): string
{
    echo "{$label}: ";
    // Windows does not support stty; just read plaintext
    if (stripos(PHP_OS_FAMILY, 'Windows') !== false || stripos(PHP_OS, 'WIN') === 0) {
        return trim(fgets(STDIN));
    }
    system('stty -echo');
    $value = trim(fgets(STDIN));
    system('stty echo');
    echo "\n";
    return $value;
}

echo "\n";
echo "==================================================\n";
echo "   AttendX Installation Wizard v1.0.0\n";
echo "==================================================\n\n";

// ─── Step 1: PHP Version & Extensions ─────────────────────────────────────────

out('Checking system requirements...');

if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    out('PHP 8.2 or higher is required. Current: ' . PHP_VERSION, 'err');
    exit(1);
}
out('PHP version: ' . PHP_VERSION, 'ok');

$requiredExt = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'session', 'fileinfo', 'gd'];
$missing = [];
foreach ($requiredExt as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}
if (!empty($missing)) {
    out('Missing PHP extensions: ' . implode(', ', $missing), 'err');
    exit(1);
}
out('All required PHP extensions present.', 'ok');

// ─── Step 2: Check if already installed ───────────────────────────────────────

if (file_exists(BASE_PATH . '/.env')) {
    $confirm = prompt('A .env file already exists. Overwrite? (yes/no)', 'no');
    if (strtolower($confirm) !== 'yes') {
        out('Installation cancelled.', 'warn');
        exit(0);
    }
}

// ─── Step 3: Application Configuration ────────────────────────────────────────

echo "\n--- Application Configuration ---\n";
$appName = prompt('Company / Application Name', 'AttendX');
$appUrl  = prompt('Application URL', 'http://localhost');
$appTz   = prompt('Timezone', 'Asia/Dhaka');

// ─── Step 4: Database Configuration ───────────────────────────────────────────

echo "\n--- Database Configuration ---\n";
$dbHost = prompt('Database Host', '127.0.0.1');
$dbPort = prompt('Database Port', '3306');
$dbName = prompt('Database Name', 'attendx');
$dbUser = prompt('Database User', 'root');
$dbPass = promptSecret('Database Password');

out('Testing database connection...');
try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    out('Database connection successful.', 'ok');
} catch (PDOException $e) {
    out('Connection failed: ' . $e->getMessage(), 'err');
    exit(1);
}

// Create database if not exists
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
out("Database '{$dbName}' ready.", 'ok');
$pdo->exec("USE `{$dbName}`");

// ─── Step 5: Run install.sql ──────────────────────────────────────────────────

echo "\n--- Installing Schema ---\n";
$sqlFile = BASE_PATH . '/database/install.sql';
if (!file_exists($sqlFile)) {
    out('database/install.sql not found.', 'err');
    exit(1);
}

out('Running database/install.sql...');
$sql = file_get_contents($sqlFile);

// Split statements on newline-terminated semicolons
$statements = array_filter(array_map('trim', explode(";\n", $sql)));
$executed   = 0;
$errors     = 0;

foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '' || str_starts_with($stmt, '--')) continue;
    try {
        $pdo->exec($stmt);
        $executed++;
    } catch (PDOException $e) {
        if (!str_contains($e->getMessage(), 'already exists') &&
            !str_contains($e->getMessage(), 'Duplicate entry')) {
            out('SQL Warning: ' . substr($e->getMessage(), 0, 150), 'warn');
            $errors++;
        }
    }
}
out("Executed {$executed} statements ({$errors} warnings).", $errors > 0 ? 'warn' : 'ok');

// Persist company name into system_settings now that schema is loaded
try {
    $pdo->prepare("UPDATE system_settings SET `value`=? WHERE `key`='company.name'")->execute([$appName]);
    $pdo->prepare("UPDATE system_settings SET `value`=? WHERE `key`='app.name'")->execute([$appName]);
    $pdo->prepare("UPDATE system_settings SET `value`=? WHERE `key`='mail.from_name'")->execute([$appName]);
} catch (PDOException $e) {
    out('Could not write company name to settings: ' . $e->getMessage(), 'warn');
}

// ─── Step 6: Device Configuration ─────────────────────────────────────────────

echo "\n--- Device Configuration ---\n";
$admsPort = prompt('ADMS Push Port (device → server)', '8080');
$sdkPort  = prompt('SDK Pull Port  (server → device)', '4370');

// ─── Step 7: Generate Keys ─────────────────────────────────────────────────────

$appKey = bin2hex(random_bytes(16));
$jwtKey = bin2hex(random_bytes(32));

// ─── Step 8: Write .env ────────────────────────────────────────────────────────

$envContent = <<<ENV
APP_NAME={$appName}
APP_ENV=production
APP_DEBUG=false
APP_URL={$appUrl}
APP_KEY={$appKey}
APP_TZ={$appTz}
APP_LOCALE=en

DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_NAME={$dbName}
DB_USER={$dbUser}
DB_PASS={$dbPass}

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
// chmod is a no-op on Windows — suppress warning
if (stripos(PHP_OS_FAMILY, 'Windows') === false) {
    @chmod(BASE_PATH . '/.env', 0640);
}
out('.env file created.', 'ok');

// ─── Step 9: Storage Directories ──────────────────────────────────────────────

echo "\n--- Storage Setup ---\n";
$dirs = [
    'logs', 'backups', 'cache',
    'uploads', 'uploads/avatars', 'uploads/documents', 'uploads/leave_docs',
];
foreach ($dirs as $dir) {
    $path = BASE_PATH . '/storage/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0750, true);
    }
}
out('Storage directories created.', 'ok');

// ─── Step 10: Python hint ─────────────────────────────────────────────────────

echo "\n--- Python Services ---\n";
out('Install Python dependencies: pip install -r python/requirements.txt', 'warn');
out('Then start: python python/adms_listener.py', 'warn');
out('And:        python python/sdk_pull.py', 'warn');

// ─── Done ─────────────────────────────────────────────────────────────────────

echo "\n";
echo "==================================================\n";
echo "   Installation Complete!\n";
echo "==================================================\n\n";
out("Application Name: {$appName}", 'ok');
out("Application URL : {$appUrl}", 'ok');
out('Admin Email     : admin@attendx.com', 'ok');
out('Admin Password  : Admin@1234', 'ok');
echo "\n";
out('CHANGE THE ADMIN PASSWORD IMMEDIATELY AFTER FIRST LOGIN.', 'warn');
echo "\n";
echo "Next steps:\n";
echo "  1. Point your web server document root to: " . BASE_PATH . "/public\n";
echo "  2. pip install -r python/requirements.txt\n";
echo "  3. python python/adms_listener.py\n";
echo "  4. python python/sdk_pull.py\n";
echo "  5. Visit {$appUrl} and log in.\n\n";
