<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * MailService — SMTP email sender.
 * Uses PHP's native SMTP or PHPMailer if available.
 * Falls back to native mail() if SMTP not configured.
 */
class MailService
{
    private array $config;
    private array $to      = [];
    private array $cc      = [];
    private array $bcc     = [];
    private array $attachments = [];
    private string $subject = '';
    private string $body    = '';
    private string $altBody = '';
    private string $fromName;
    private string $fromEmail;

    public function __construct()
    {
        $db = Database::getInstance()->getConnection();

        $rows = $db->query("SELECT `key`, `value` FROM system_settings WHERE `group` = 'mail'")->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->config = [
            'host'       => $rows['mail.host']       ?? config('mail.host',       'smtp.gmail.com'),
            'port'       => (int)($rows['mail.port'] ?? config('mail.port',       587)),
            'encryption' => $rows['mail.encryption'] ?? config('mail.encryption', 'tls'),
            'username'   => $rows['mail.username']   ?? config('mail.username',   ''),
            'password'   => $rows['mail.password']   ?? config('mail.password',   ''),
            'from_name'  => $rows['mail.from_name']  ?? config('mail.from_name',  'AttendX'),
            'from_email' => $rows['mail.from_email'] ?? config('mail.from_email', ''),
        ];

        $this->fromName  = $this->config['from_name'];
        $this->fromEmail = $this->config['from_email'];
    }

    // ─── Builder Methods ──────────────────────────────────────────────────────

    public function to(string $email, string $name = ''): static
    {
        $this->to[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function cc(string $email, string $name = ''): static
    {
        $this->cc[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function bcc(string $email, string $name = ''): static
    {
        $this->bcc[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $body, string $altText = ''): static
    {
        $this->body    = $body;
        $this->altBody = $altText ?: strip_tags($body);
        return $this;
    }

    public function text(string $body): static
    {
        $this->body    = $body;
        $this->altBody = $body;
        return $this;
    }

    public function attach(string $path, string $name = ''): static
    {
        if (file_exists($path)) {
            $this->attachments[] = [
                'path' => $path,
                'name' => $name ?: basename($path),
            ];
        }
        return $this;
    }

    public function from(string $email, string $name = ''): static
    {
        $this->fromEmail = $email;
        $this->fromName  = $name ?: $this->fromName;
        return $this;
    }

    // ─── Send ─────────────────────────────────────────────────────────────────

    public function send(): bool
    {
        if (empty($this->to)) {
            throw new \InvalidArgumentException('No recipients specified.');
        }
        if (empty($this->subject)) {
            throw new \InvalidArgumentException('Email subject is required.');
        }
        if (empty($this->body)) {
            throw new \InvalidArgumentException('Email body is required.');
        }

        if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            return $this->sendViaPHPMailer();
        }

        return $this->sendViaSocket();
    }

    /**
     * Convenience: send raw HTML email without builder pattern.
     */
    public function sendRaw(string $to, string $subject, string $htmlBody, string $name = ''): bool
    {
        return $this->to($to, $name)->subject($subject)->html($htmlBody)->send();
    }

    // ─── PHPMailer Implementation ─────────────────────────────────────────────

    private function sendViaPHPMailer(): bool
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->Port       = $this->config['port'];
            $mail->SMTPAuth   = !empty($this->config['username']);
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->CharSet    = 'UTF-8';
            $mail->isHTML(true);

            if ($this->config['encryption'] === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->config['encryption'] === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->setFrom($this->fromEmail, $this->fromName);

            foreach ($this->to  as $r) { $mail->addAddress($r['email'], $r['name']); }
            foreach ($this->cc  as $r) { $mail->addCC($r['email'], $r['name']); }
            foreach ($this->bcc as $r) { $mail->addBCC($r['email'], $r['name']); }

            $mail->Subject = $this->subject;
            $mail->Body    = $this->body;
            $mail->AltBody = $this->altBody;

            foreach ($this->attachments as $att) {
                $mail->addAttachment($att['path'], $att['name']);
            }

            $mail->send();
            return true;

        } catch (\PHPMailer\PHPMailer\Exception $e) {
            throw new \RuntimeException('Mailer error: ' . $e->getMessage(), 0, $e);
        }
    }

    // ─── Native Socket SMTP Fallback ──────────────────────────────────────────

    private function sendViaSocket(): bool
    {
        $host       = $this->config['host'];
        $port       = $this->config['port'];
        $username   = $this->config['username'];
        $password   = $this->config['password'];
        $encryption = $this->config['encryption'];

        $prefix = $encryption === 'ssl' ? 'ssl://' : '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 15);

        if (!$socket) {
            throw new \RuntimeException("SMTP connection failed ($host:$port): $errstr");
        }

        $read = fn() => fgets($socket, 512);
        $write = function (string $cmd) use ($socket) {
            fwrite($socket, $cmd . "\r\n");
        };

        $read(); // 220 greeting

        $write("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        while ($line = $read()) {
            if (substr($line, 3, 1) === ' ') break; // End of EHLO response
        }

        if ($encryption === 'tls') {
            $write("STARTTLS");
            $read(); // 220
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $write("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            while ($line = $read()) {
                if (substr($line, 3, 1) === ' ') break;
            }
        }

        if ($username) {
            $write("AUTH LOGIN");
            $read();
            $write(base64_encode($username));
            $read();
            $write(base64_encode($password));
            $resp = $read();
            if (substr($resp, 0, 3) !== '235') {
                fclose($socket);
                throw new \RuntimeException('SMTP authentication failed.');
            }
        }

        $write("MAIL FROM:<{$this->fromEmail}>");
        $read();

        foreach ($this->to as $r) {
            $write("RCPT TO:<{$r['email']}>");
            $read();
        }
        foreach ($this->cc as $r) {
            $write("RCPT TO:<{$r['email']}>");
            $read();
        }

        $write("DATA");
        $read();

        $boundary = md5(uniqid('', true));
        $isHtml   = str_contains($this->body, '<');

        $headers  = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "To: " . implode(', ', array_map(fn($r) => "{$r['name']} <{$r['email']}>", $this->to)) . "\r\n";
        if ($this->cc) {
            $headers .= "CC: " . implode(', ', array_map(fn($r) => "<{$r['email']}>", $this->cc)) . "\r\n";
        }
        $headers .= "Subject: {$this->subject}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Date: " . date('r') . "\r\n";

        if ($isHtml) {
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $msgBody  = "--{$boundary}\r\n";
            $msgBody .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n{$this->altBody}\r\n\r\n";
            $msgBody .= "--{$boundary}\r\n";
            $msgBody .= "Content-Type: text/html; charset=UTF-8\r\n\r\n{$this->body}\r\n\r\n";
            $msgBody .= "--{$boundary}--\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $msgBody  = $this->body . "\r\n";
        }

        $write($headers . "\r\n" . $msgBody . "\r\n.");
        $resp = $read();

        $write("QUIT");
        fclose($socket);

        if (substr($resp, 0, 3) !== '250') {
            throw new \RuntimeException('SMTP message rejected: ' . $resp);
        }

        return true;
    }

    // ─── Template Emails ──────────────────────────────────────────────────────

    public static function sendLeaveStatusUpdate(int $employeeId, string $status, array $leaveApp): bool
    {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT full_name, email FROM employees WHERE id=?");
        $stmt->execute([$employeeId]);
        $emp  = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$emp || empty($emp['email'])) {
            return false;
        }

        $statusLabel = ucfirst($status);
        $subject     = "Leave Application {$statusLabel}";
        $body        = self::renderTemplate('leave_status', [
            'name'         => $emp['full_name'],
            'status'       => $statusLabel,
            'leave_type'   => $leaveApp['leave_type'] ?? 'Leave',
            'from_date'    => $leaveApp['from_date'] ?? '',
            'to_date'      => $leaveApp['to_date'] ?? '',
            'total_days'   => $leaveApp['total_days'] ?? 0,
        ]);

        $mailer = new static();
        return $mailer->to($emp['email'], $emp['full_name'])->subject($subject)->html($body)->send();
    }

    private static function renderTemplate(string $name, array $vars): string
    {
        $tpl = match ($name) {
            'leave_status' => '
                <p>Dear {{name}},</p>
                <p>Your leave application for <strong>{{leave_type}}</strong> from <strong>{{from_date}}</strong>
                   to <strong>{{to_date}}</strong> ({{total_days}} day(s)) has been
                   <strong>{{status}}</strong>.</p>
                <p>Please log in to the HR portal for details.</p>
                <p>Regards,<br>HR Team</p>',
            default        => '<p>{{body}}</p>',
        };

        foreach ($vars as $k => $v) {
            $tpl = str_replace("{{$k}}", htmlspecialchars((string)$v), $tpl);
        }

        return self::wrapTemplate($vars['status'] ?? 'Notification', $tpl);
    }

    private static function wrapTemplate(string $title, string $content): string
    {
        // Attempt to read app name from system_settings; fall back to env/config
        $appName = 'AttendX';
        try {
            $db   = Database::getInstance()->getConnection();
            $row  = $db->query("SELECT value FROM system_settings WHERE `key`='app.name' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            if (!empty($row['value'])) {
                $appName = htmlspecialchars($row['value']);
            }
        } catch (\Throwable) {
            $appName = config('app.name', 'AttendX');
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{$title}</title></head>
<body style="font-family:Arial,sans-serif;background:#f5f5f5;padding:20px">
  <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:32px;border:1px solid #eee">
    <div style="border-bottom:2px solid #6366f1;padding-bottom:16px;margin-bottom:24px">
      <h2 style="margin:0;color:#6366f1">{$appName}</h2>
    </div>
    {$content}
  </div>
</body>
</html>
HTML;
    }
}
