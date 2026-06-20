<?php
declare(strict_types=1);

namespace App\Core;

class Request
{
    private static array $routeParams = [];

    // ─── Method ───────────────────────────────────────────────────────────────

    public static function method(): string
    {
        $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_POST['_method'] ?? '';
        return strtoupper($override ?: ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function isPost(): bool   { return self::method() === 'POST'; }
    public static function isGet(): bool    { return self::method() === 'GET'; }
    public static function isPut(): bool    { return self::method() === 'PUT'; }
    public static function isDelete(): bool { return self::method() === 'DELETE'; }
    public static function isAjax(): bool   { return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'; }
    public static function isJson(): bool   { return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'); }

    // ─── Input ────────────────────────────────────────────────────────────────

    public static function get(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') return $_GET;
        return $_GET[$key] ?? $default;
    }

    public static function post(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') return $_POST;
        return $_POST[$key] ?? $default;
    }

    public static function input(string $key = '', mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? null;
        if ($value !== null) return $value;

        // Try JSON body
        static $json = null;
        if ($json === null) {
            $raw  = file_get_contents('php://input');
            $json = json_decode($raw ?? '', true) ?? [];
        }

        return $json[$key] ?? $default;
    }

    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public static function only(array $keys): array
    {
        $all = self::all();
        return array_intersect_key($all, array_flip($keys));
    }

    public static function except(array $keys): array
    {
        $all = self::all();
        return array_diff_key($all, array_flip($keys));
    }

    public static function has(string $key): bool
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }

    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw ?: '', true) ?? [];
    }

    // ─── Route Params ─────────────────────────────────────────────────────────

    public static function setRouteParams(array $params): void
    {
        self::$routeParams = $params;
    }

    public static function param(string $key, mixed $default = null): mixed
    {
        return self::$routeParams[$key] ?? $default;
    }

    // ─── Files ────────────────────────────────────────────────────────────────

    public static function file(string $key): array|null
    {
        return $_FILES[$key] ?? null;
    }

    public static function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK;
    }

    // ─── URL / Path ───────────────────────────────────────────────────────────

    public static function path(): string
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return '/' . trim($path, '/');
    }

    public static function fullUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
    }

    public static function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    public static function header(string $name, string $default = ''): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? $default;
    }

    public static function bearerToken(): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }
}
