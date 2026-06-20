<?php
declare(strict_types=1);

// ─── Base Path ────────────────────────────────────────────────────────────────
define('BASE_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

// ─── Helpers ──────────────────────────────────────────────────────────────────
require BASE_PATH . '/app/Helpers/functions.php';

// ─── Timezone ─────────────────────────────────────────────────────────────────
date_default_timezone_set(env('APP_TZ', 'Asia/Dhaka'));

// ─── Error Handling ───────────────────────────────────────────────────────────
$debug = (bool)env('APP_DEBUG', false);

if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors',     '1');
    ini_set('error_log',      BASE_PATH . '/storage/logs/php_errors.log');
}

set_exception_handler(function (\Throwable $e) use ($debug) {
    $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    http_response_code($code);

    $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') ||
             str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode([
            'error'   => $debug ? $e->getMessage() : 'Server error.',
            'code'    => $code,
        ]);
    } else {
        if ($debug) {
            echo '<pre style="background:#1a1a2e;color:#e2e8f0;padding:20px;font-size:13px">';
            echo htmlspecialchars($e->getMessage()) . "\n\n";
            echo htmlspecialchars($e->getTraceAsString());
            echo '</pre>';
        } else {
            include BASE_PATH . '/app/Views/errors/500.php';
        }
    }
    exit(1);
});

// ─── Autoloader ───────────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $map = [
        'App\\Controllers\\' => BASE_PATH . '/app/Controllers/',
        'App\\Models\\'      => BASE_PATH . '/app/Models/',
        'App\\Core\\'        => BASE_PATH . '/app/Core/',
        'App\\Services\\'    => BASE_PATH . '/app/Services/',
        'App\\Middleware\\'  => BASE_PATH . '/app/Middleware/',
    ];

    foreach ($map as $prefix => $dir) {
        if (!str_starts_with($class, $prefix)) continue;
        $rel  = substr($class, strlen($prefix));
        $file = $dir . str_replace('\\', '/', $rel) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }

    // Composer vendor autoloader fallback
    $vendor = BASE_PATH . '/vendor/autoload.php';
    if (file_exists($vendor)) {
        require_once $vendor;
    }
});

// ─── Session ──────────────────────────────────────────────────────────────────
App\Core\Session::start();

// ─── Storage Directories ──────────────────────────────────────────────────────
foreach (['logs', 'backups', 'uploads', 'cache'] as $dir) {
    $path = BASE_PATH . '/storage/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0750, true);
    }
}

// ─── Routes ───────────────────────────────────────────────────────────────────
require BASE_PATH . '/routes/core.php';
require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/api.php';
