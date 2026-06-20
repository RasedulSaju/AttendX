<?php
declare(strict_types=1);

namespace App\Core;

class CSRF
{
    private const TOKEN_KEY = '_csrf_token';
    private const TOKEN_LEN = 32;

    public static function generate(): string
    {
        if (!Session::has(self::TOKEN_KEY)) {
            Session::set(self::TOKEN_KEY, bin2hex(random_bytes(self::TOKEN_LEN)));
        }
        return Session::get(self::TOKEN_KEY);
    }

    public static function verify(): void
    {
        $token = $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? '';

        if (!self::valid($token)) {
            http_response_code(419);
            // If AJAX request, return JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'CSRF token mismatch.', 'code' => 419]);
            } else {
                Session::flash('error', 'Session expired. Please try again.');
                $ref = $_SERVER['HTTP_REFERER'] ?? '/dashboard';
                header('Location: ' . $ref);
            }
            exit;
        }

        // Rotate token after use
        self::rotate();
    }

    public static function valid(string $token): bool
    {
        $stored = Session::get(self::TOKEN_KEY, '');
        if (empty($stored) || empty($token)) {
            return false;
        }
        return hash_equals($stored, $token);
    }

    public static function rotate(): void
    {
        Session::set(self::TOKEN_KEY, bin2hex(random_bytes(self::TOKEN_LEN)));
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::generate()) . '">';
    }

    public static function meta(): string
    {
        return '<meta name="csrf-token" content="' . htmlspecialchars(self::generate()) . '">';
    }
}
