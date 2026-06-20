<?php
declare(strict_types=1);

/**
 * AttendX – Front Controller
 * All HTTP requests are routed through this file via Apache mod_rewrite.
 *
 * Apache .htaccess (place in /public):
 *   Options -Indexes
 *   RewriteEngine On
 *   RewriteCond %{REQUEST_FILENAME} !-f
 *   RewriteCond %{REQUEST_FILENAME} !-d
 *   RewriteRule ^ index.php [QSA,L]
 */

// Security: block direct execution outside web server context
if (PHP_SAPI === 'cli') {
    exit('HTTP server only.');
}

// Block access to sensitive files if somehow reached
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$blocked = ['.env', '.git', 'composer.json', 'composer.lock', 'phpunit.xml'];
foreach ($blocked as $b) {
    if (str_contains($requestPath, $b)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// Bootstrap
require dirname(__DIR__) . '/app/bootstrap.php';

// Dispatch
App\Core\Router::dispatch();
