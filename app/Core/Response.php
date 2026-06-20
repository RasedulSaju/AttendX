<?php
declare(strict_types=1);

namespace App\Core;

class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    public static function back(string $fallback = '/dashboard'): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? $fallback;
        self::redirect($ref);
    }

    public static function html(string $content, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }

    public static function text(string $content, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $content;
        exit;
    }

    public static function download(string $filePath, string $filename = '', int $chunkSize = 8192): void
    {
        if (!file_exists($filePath)) {
            self::abort(404, 'File not found.');
        }

        $filename = $filename ?: basename($filePath);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        $fp = fopen($filePath, 'rb');
        while (!feof($fp)) {
            echo fread($fp, $chunkSize);
            flush();
        }
        fclose($fp);
        exit;
    }

    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }

    public static function abort(int $code, string $message = ''): void
    {
        http_response_code($code);

        $isApi = str_contains(Request::path(), '/api/') || Request::isJson() || Request::isAjax();
        if ($isApi) {
            self::json(['error' => $message ?: self::statusText($code), 'code' => $code], $code);
        }

        $labels = [
            403 => '403 Forbidden',
            404 => '404 Not Found',
            419 => '419 Session Expired',
            500 => '500 Server Error',
        ];

        $view = base_path("app/Views/errors/{$code}.php");
        if (file_exists($view)) {
            include $view;
        } else {
            $title = $labels[$code] ?? "Error $code";
            echo "<!DOCTYPE html><html><head><title>$title</title></head><body style='font-family:sans-serif;padding:40px'>"
                . "<h1>$title</h1>"
                . ($message ? "<p>" . htmlspecialchars($message) . "</p>" : '')
                . "<a href='/dashboard'>← Back to Dashboard</a></body></html>";
        }
        exit;
    }

    public static function setHeader(string $name, string $value): void
    {
        header("$name: $value");
    }

    public static function setSecurityHeaders(): void
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    private static function statusText(int $code): string
    {
        return match ($code) {
            200 => 'OK', 201 => 'Created', 204 => 'No Content',
            301 => 'Moved Permanently', 302 => 'Found',
            400 => 'Bad Request', 401 => 'Unauthorized',
            403 => 'Forbidden', 404 => 'Not Found',
            405 => 'Method Not Allowed', 409 => 'Conflict',
            419 => 'Session Expired', 422 => 'Unprocessable Entity',
            429 => 'Too Many Requests', 500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            default => 'Unknown',
        };
    }
}
