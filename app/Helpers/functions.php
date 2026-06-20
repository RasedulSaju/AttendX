<?php
declare(strict_types=1);

/**
 * AttendX Global Helper Functions
 * Auto-loaded via composer autoload or require in bootstrap.
 */

// ─── Environment Helper ───────────────────────────────────────────────────────

if (!function_exists('env')) {
    /**
     * Get an environment variable with optional default.
     * Reads from $_ENV, $_SERVER, and getenv().
     * Parses .env file on first call if not already loaded.
     */
    function env(string $key, mixed $default = null): mixed
    {
        static $loaded = false;
        if (!$loaded) {
            $loaded  = true;
            $envFile = defined('BASE_PATH') ? BASE_PATH . '/.env' : dirname(__DIR__, 2) . '/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) continue;
                    if (!str_contains($line, '=')) continue;
                    [$k, $v] = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v, " \t\"'");
                    if (!isset($_ENV[$k])) {
                        $_ENV[$k] = $v;
                        putenv("$k=$v");
                    }
                }
            }
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false) return $default;

        return match (strtolower((string)$value)) {
            'true','(true)'   => true,
            'false','(false)' => false,
            'null','(null)'   => null,
            'empty',''        => '',
            default           => $value,
        };
    }
}

// ─── Path Helpers ─────────────────────────────────────────────────────────────

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        return $path ? $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\') : $base;
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return base_path('resources' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

// ─── Config Helper ────────────────────────────────────────────────────────────

if (!function_exists('config')) {
    /**
     * Get a config value using dot notation.
     * e.g. config('database.host'), config('jwt.secret')
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $cache = [];

        $parts  = explode('.', $key, 2);
        $file   = $parts[0];
        $subKey = $parts[1] ?? null;

        if (!isset($cache[$file])) {
            $configPath = base_path("config/{$file}.php");
            if (file_exists($configPath)) {
                $cache[$file] = require $configPath;
            } else {
                $cache[$file] = [];
            }
        }

        if ($subKey === null) {
            return $cache[$file] ?? $default;
        }

        // Support nested dot notation within the file
        $value = $cache[$file];
        foreach (explode('.', $subKey) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

// ─── Array Helpers ────────────────────────────────────────────────────────────

if (!function_exists('collect_by_key')) {
    /**
     * Find first array item where $keyField === $value.
     * Used for O(n) lookups in small arrays (leave balance, etc.)
     */
    function collect_by_key(array $items, string $keyField, mixed $value): ?array
    {
        foreach ($items as $item) {
            if (isset($item[$keyField]) && $item[$keyField] == $value) {
                return $item;
            }
        }
        return null;
    }
}

if (!function_exists('array_pluck')) {
    /**
     * Extract a column from an array of associative arrays.
     * Optionally key by another field.
     */
    function array_pluck(array $array, string $valueField, ?string $keyField = null): array
    {
        $result = [];
        foreach ($array as $item) {
            $v = $item[$valueField] ?? null;
            if ($keyField !== null) {
                $result[$item[$keyField]] = $v;
            } else {
                $result[] = $v;
            }
        }
        return $result;
    }
}

if (!function_exists('array_group_by')) {
    /**
     * Group an array of rows by a key field.
     */
    function array_group_by(array $array, string $key): array
    {
        $result = [];
        foreach ($array as $item) {
            $result[$item[$key]][] = $item;
        }
        return $result;
    }
}

// ─── String Helpers ───────────────────────────────────────────────────────────

if (!function_exists('str_limit')) {
    function str_limit(string $value, int $limit = 100, string $end = '…'): string
    {
        return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit) . $end;
    }
}

if (!function_exists('slug')) {
    function slug(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\-_]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }
}

if (!function_exists('mask_email')) {
    function mask_email(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $masked = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2));
        return $masked . '@' . $domain;
    }
}

// ─── Date Helpers ─────────────────────────────────────────────────────────────

if (!function_exists('format_date')) {
    function format_date(?string $date, string $fallback = '—'): string
    {
        if (!$date) return $fallback;
        try {
            return (new DateTime($date))->format(config('app.date_format', 'd/m/Y'));
        } catch (\Exception $e) {
            return $fallback;
        }
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $dt, string $fallback = '—'): string
    {
        if (!$dt) return $fallback;
        try {
            $fmt = config('app.date_format', 'd/m/Y') . ' ' . config('app.time_format', 'H:i');
            return (new DateTime($dt))->format($fmt);
        } catch (\Exception $e) {
            return $fallback;
        }
    }
}

if (!function_exists('time_ago')) {
    function time_ago(string $datetime): string
    {
        $diff = time() - strtotime($datetime);
        return match (true) {
            $diff < 60     => 'just now',
            $diff < 3600   => floor($diff / 60) . 'm ago',
            $diff < 86400  => floor($diff / 3600) . 'h ago',
            $diff < 604800 => floor($diff / 86400) . 'd ago',
            default        => date(config('app.date_format', 'd/m/Y'), strtotime($datetime)),
        };
    }
}

if (!function_exists('minutes_to_hm')) {
    /**
     * Convert minutes to H:MM string. e.g. 90 → "1:30"
     */
    function minutes_to_hm(int $minutes): string
    {
        $h = intdiv(abs($minutes), 60);
        $m = abs($minutes) % 60;
        return ($minutes < 0 ? '-' : '') . $h . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
    }
}

// ─── Number Helpers ───────────────────────────────────────────────────────────

if (!function_exists('money')) {
    function money(float $amount, int $decimals = 2): string
    {
        return config('app.currency', '৳') . number_format($amount, $decimals);
    }
}

if (!function_exists('percentage')) {
    function percentage(float $value, float $total, int $decimals = 1): string
    {
        if ($total == 0) return '0%';
        return round($value / $total * 100, $decimals) . '%';
    }
}

// ─── Security Helpers ─────────────────────────────────────────────────────────

if (!function_exists('e')) {
    /**
     * HTML-encode a string for safe output.
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('clean')) {
    /**
     * Strip tags and trim. Use for text inputs.
     */
    function clean(?string $value, int $maxLen = 255): string
    {
        return mb_substr(strip_tags(trim((string)$value)), 0, $maxLen);
    }
}

if (!function_exists('generate_code')) {
    /**
     * Generate a unique code (e.g. employee code, badge).
     */
    function generate_code(string $prefix = '', int $length = 6): string
    {
        return strtoupper($prefix) . str_pad((string)random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }
}

// ─── Response Helpers ─────────────────────────────────────────────────────────

if (!function_exists('redirect')) {
    function redirect(string $url, int $code = 302): never
    {
        http_response_code($code);
        header("Location: $url");
        exit;
    }
}

if (!function_exists('json_response')) {
    function json_response(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// ─── Validation Helpers ───────────────────────────────────────────────────────

if (!function_exists('validate_date')) {
    function validate_date(string $date, string $format = 'Y-m-d'): bool
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

if (!function_exists('validate_time')) {
    function validate_time(string $time): bool
    {
        return (bool)preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time);
    }
}

// ─── Debugging (disabled in production) ──────────────────────────────────────

if (!function_exists('dd')) {
    function dd(mixed ...$vars): never
    {
        if (config('app.debug', false)) {
            foreach ($vars as $v) {
                echo '<pre>';
                var_dump($v);
                echo '</pre>';
            }
        }
        exit;
    }
}

if (!function_exists('log_debug')) {
    function log_debug(string $message, array $context = []): void
    {
        if (!config('app.debug', false)) return;
        $line = date('Y-m-d H:i:s') . ' [DEBUG] ' . $message;
        if ($context) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        error_log($line);
    }
}
