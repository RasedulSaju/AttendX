<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Database;
use PDO;

class JwtAuth
{
    private static string $secret;
    private static string $algo = 'HS256';

    private static function secret(): string
    {
        if (!isset(self::$secret)) {
            self::$secret = config('jwt.secret', '');
            if (empty(self::$secret)) {
                throw new \RuntimeException('JWT secret not configured. Set jwt.secret in config.');
            }
        }
        return self::$secret;
    }

    // ─── Token Generation ─────────────────────────────────────────────────────

    public static function generate(array $payload, int $ttl = 0): string
    {
        if ($ttl === 0) {
            $ttl = (int)config('jwt.ttl', 86400);
        }

        $now = time();
        $payload = array_merge($payload, [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(8)),
        ]);

        $header    = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => self::$algo]));
        $body      = self::base64UrlEncode(json_encode($payload));
        $signature = self::base64UrlEncode(self::sign("$header.$body"));

        return "$header.$body.$signature";
    }

    public static function generateRefresh(int $userId): string
    {
        $ttl   = (int)config('jwt.refresh_ttl', 604800); // 7 days default
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);

        $db = Database::getInstance()->getConnection();
        $db->prepare("
            INSERT INTO jwt_refresh_tokens (user_id, token_hash, expires_at)
            VALUES (:uid, :hash, DATE_ADD(NOW(), INTERVAL :ttl SECOND))
            ON DUPLICATE KEY UPDATE token_hash=:hash2, expires_at=DATE_ADD(NOW(), INTERVAL :ttl2 SECOND), revoked=0
        ")->execute([
            ':uid'   => $userId,
            ':hash'  => $hash,
            ':hash2' => $hash,
            ':ttl'   => $ttl,
            ':ttl2'  => $ttl,
        ]);

        return $token;
    }

    // ─── Token Verification ───────────────────────────────────────────────────

    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $sig] = $parts;

        // Verify signature
        $expected = self::base64UrlEncode(self::sign("$header.$body"));
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($body), true);
        if (!is_array($payload)) {
            return null;
        }

        $now = time();

        // Check expiry
        if (isset($payload['exp']) && $payload['exp'] < $now) {
            return null;
        }

        // Check not-before
        if (isset($payload['nbf']) && $payload['nbf'] > $now) {
            return null;
        }

        return $payload;
    }

    public static function verifyRefresh(string $token): ?int
    {
        $hash = hash('sha256', $token);
        $db   = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT user_id FROM jwt_refresh_tokens
            WHERE token_hash = ? AND revoked = 0 AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row['user_id'] : null;
    }

    // ─── Token Revocation ─────────────────────────────────────────────────────

    public static function revokeRefresh(int $userId): void
    {
        $db = Database::getInstance()->getConnection();
        $db->prepare("UPDATE jwt_refresh_tokens SET revoked=1 WHERE user_id=?")->execute([$userId]);
    }

    public static function purgeExpiredTokens(): int
    {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM jwt_refresh_tokens WHERE expires_at < NOW() OR revoked=1");
        $stmt->execute();
        return $stmt->rowCount();
    }

    // ─── Extract Payload Without Verifying (unsafe — use verify() instead) ───

    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = json_decode(self::base64UrlDecode($parts[1]), true);
        return is_array($payload) ? $payload : null;
    }

    // ─── Bearer Token from Request ────────────────────────────────────────────

    public static function fromRequest(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        // Fallback: cookie for web use
        return $_COOKIE['access_token'] ?? null;
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private static function sign(string $data): string
    {
        return hash_hmac('sha256', $data, self::secret(), true);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');
        return base64_decode($padded);
    }
}
