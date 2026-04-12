<?php
// core/Mailer.php
// ─────────────────────────────────────────────────────────────────────────────
//  Simple mailer for transactional system emails (password reset, notifications).
//
//  Uses PHP's built-in mail() with a configurable SMTP backend via php.ini
//  or falls back to a direct socket SMTP send if MAIL_HOST is configured.
//
//  Config (.env):
//    MAIL_FROM_ADDRESS = noreply@rockycompany.com
//    MAIL_FROM_NAME    = Rocky HRIS
//    MAIL_HOST         = smtp.gmail.com        (empty = use PHP mail())
//    MAIL_PORT         = 587
//    MAIL_USER         = your@gmail.com
//    MAIL_PASS         = your_app_password
//    MAIL_ENCRYPTION   = tls                   (tls | ssl | none)
//
//  Security:
//   - All user-supplied values are header-sanitised (no CRLF injection)
//   - Email addresses are validated with FILTER_VALIDATE_EMAIL before sending
//   - Tokens are generated with random_bytes(32) — 256 bits of entropy
// ─────────────────────────────────────────────────────────────────────────────

declare(strict_types=1);

final class Mailer
{
    // ── Send a plain-text + HTML email ────────────────────────────────────────
    /**
     * @param string $toAddress   Recipient email address (validated internally)
     * @param string $toName      Recipient display name
     * @param string $subject     Email subject
     * @param string $htmlBody    HTML body content
     * @param string $textBody    Plain-text fallback (auto-generated if empty)
     * @return bool               True on success, false on failure
     */
    public static function send(
        string $toAddress,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = ''
    ): bool {
        // Validate recipient
        if (!filter_var($toAddress, FILTER_VALIDATE_EMAIL)) {
            error_log("Mailer::send — invalid recipient address: {$toAddress}");
            return false;
        }

        $fromAddress = self::getEnv('MAIL_FROM_ADDRESS', 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $fromName    = self::getEnv('MAIL_FROM_NAME', COMPANY_NAME ?? 'Rocky HRIS');
        $mailHost    = self::getEnv('MAIL_HOST', '');

        // Sanitise all header values — prevent CRLF injection
        $toAddress   = self::sanitiseHeader($toAddress);
        $toName      = self::sanitiseHeader($toName);
        $fromAddress = self::sanitiseHeader($fromAddress);
        $fromName    = self::sanitiseHeader($fromName);
        $subject     = self::sanitiseHeader($subject);

        if ($textBody === '') {
            $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $htmlBody));
            $textBody = html_entity_decode($textBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Build MIME multipart email
        $boundary = '==Rocky_' . bin2hex(random_bytes(8));
        $headers  = self::buildHeaders($fromAddress, $fromName, $toAddress, $toName, $boundary);
        $body     = self::buildBody($textBody, $htmlBody, $boundary);
        $to       = self::encodeHeader($toName) . ' <' . $toAddress . '>';

        // Choose send method
        if ($mailHost !== '') {
            return self::sendSmtp($mailHost, $toAddress, $fromAddress, $subject, $headers, $body);
        }

        // Fall back to PHP mail() — works on servers with sendmail configured
        return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    }

    // ── SMTP sender (native socket, no external dependencies) ─────────────────
    private static function sendSmtp(
        string $host,
        string $toAddress,
        string $fromAddress,
        string $subject,
        string $headers,
        string $body
    ): bool {
        $port       = (int)self::getEnv('MAIL_PORT', '587');
        $user       = self::getEnv('MAIL_USER', '');
        $pass       = self::getEnv('MAIL_PASS', '');
        $encryption = strtolower(self::getEnv('MAIL_ENCRYPTION', 'tls'));

        try {
            // Open socket
            $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
            $errno  = 0; $errstr = '';
            $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
            if (!$socket) {
                error_log("Mailer SMTP connect failed: {$errstr} ({$errno})");
                return false;
            }
            stream_set_timeout($socket, 10);

            $read = function() use ($socket): string {
                return fgets($socket, 515) ?: '';
            };
            $write = function(string $cmd) use ($socket): void {
                fwrite($socket, $cmd . "\r\n");
            };

            $read(); // 220 banner

            $write('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $resp = '';
            while ($line = $read()) {
                $resp .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }

            // STARTTLS upgrade
            if ($encryption === 'tls') {
                $write('STARTTLS');
                $read();
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($socket);
                    error_log('Mailer: STARTTLS failed');
                    return false;
                }
                $write('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                while ($line = $read()) { if (substr($line, 3, 1) === ' ') break; }
            }

            // AUTH LOGIN
            if ($user !== '' && $pass !== '') {
                $write('AUTH LOGIN');
                $read();
                $write(base64_encode($user));
                $read();
                $write(base64_encode($pass));
                $authResp = $read();
                if (strpos($authResp, '235') === false) {
                    fclose($socket);
                    error_log('Mailer: SMTP AUTH failed — check MAIL_USER / MAIL_PASS in .env');
                    return false;
                }
            }

            $write('MAIL FROM:<' . $fromAddress . '>');
            $read();
            $write('RCPT TO:<' . $toAddress . '>');
            $rcptResp = $read();
            if (strpos($rcptResp, '250') === false && strpos($rcptResp, '251') === false) {
                fclose($socket);
                error_log('Mailer: RCPT TO rejected — ' . trim($rcptResp));
                return false;
            }

            $write('DATA');
            $read();

            // Full message (headers + blank line + body)
            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $message  = "Subject: {$encodedSubject}\r\n";
            $message .= $headers . "\r\n";
            $message .= $body . "\r\n.\r\n";
            fwrite($socket, $message);
            $dataResp = $read();

            $write('QUIT');
            fclose($socket);

            return strpos($dataResp, '250') !== false;

        } catch (\Throwable $e) {
            error_log('Mailer SMTP exception: ' . $e->getMessage());
            return false;
        }
    }

    // ── Email builders ────────────────────────────────────────────────────────
    private static function buildHeaders(
        string $fromAddress, string $fromName,
        string $toAddress,   string $toName,
        string $boundary
    ): string {
        $from = self::encodeHeader($fromName) . ' <' . $fromAddress . '>';
        $to   = self::encodeHeader($toName)   . ' <' . $toAddress   . '>';
        return implode("\r\n", [
            'From: '          . $from,
            'To: '            . $to,
            'Reply-To: '      . $from,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: RockyHRIS/1.0',
            'X-Priority: 3',
        ]);
    }

    private static function buildBody(string $text, string $html, string $boundary): string
    {
        return "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "\r\n"
            . "--{$boundary}--";
    }

    // ── Utilities ─────────────────────────────────────────────────────────────
    private static function sanitiseHeader(string $value): string
    {
        // Remove CR, LF, and null bytes to prevent header injection
        return str_replace(["\r", "\n", "\0"], '', $value);
    }

    private static function encodeHeader(string $value): string
    {
        // RFC 2047 encoding for non-ASCII display names
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        // ASCII names with special chars need quoting
        if (preg_match('/[",;()<>@\[\]\\\\]/', $value)) {
            return '"' . addslashes($value) . '"';
        }
        return $value;
    }

    private static function getEnv(string $key, string $default = ''): string
    {
        return (string)(getenv($key) ?: $_ENV[$key] ?? $_SERVER[$key] ?? $default);
    }

    // ── Template: Password Reset Email ────────────────────────────────────────
    public static function sendPasswordReset(string $toAddress, string $toName, string $resetLink): bool
    {
        $companyName = defined('COMPANY_NAME') ? COMPANY_NAME : 'Rocky HRIS';
        $expiresMins = defined('PASSWORD_RESET_EXPIRY_MINUTES') ? PASSWORD_RESET_EXPIRY_MINUTES : 30;

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;">
  <div style="max-width:560px;margin:32px auto;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);">
    <div style="background:#1a2744;padding:28px 32px;text-align:center;">
      <h2 style="color:#ffffff;margin:0;font-size:1.4rem;letter-spacing:.04em;">{$companyName}</h2>
      <p style="color:#93c5fd;margin:4px 0 0;font-size:.85rem;">HRIS + Payroll System</p>
    </div>
    <div style="padding:32px;">
      <h3 style="color:#1e293b;margin:0 0 12px;">Password Reset Request</h3>
      <p style="color:#475569;line-height:1.6;margin:0 0 20px;">
        Hi <strong>{$toName}</strong>,<br><br>
        We received a request to reset the password for your account. Click the button below to set a new password.
      </p>
      <div style="text-align:center;margin:24px 0;">
        <a href="{$resetLink}"
           style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:13px 32px;border-radius:6px;font-weight:bold;font-size:1rem;letter-spacing:.02em;">
          Reset My Password
        </a>
      </div>
      <p style="color:#64748b;font-size:.85rem;line-height:1.5;margin:0 0 12px;">
        Or copy and paste this link into your browser:<br>
        <a href="{$resetLink}" style="color:#2563eb;word-break:break-all;">{$resetLink}</a>
      </p>
      <p style="color:#64748b;font-size:.85rem;margin:0;">
        <strong>This link expires in {$expiresMins} minutes.</strong><br>
        If you did not request a password reset, you can safely ignore this email — your password will not change.
      </p>
    </div>
    <div style="background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e2e8f0;">
      <p style="color:#94a3b8;font-size:.78rem;margin:0;">
        &copy; {$companyName} &mdash; This is an automated message, please do not reply.
      </p>
    </div>
  </div>
</body>
</html>
HTML;

        $text = "Password Reset Request\n\n"
              . "Hi {$toName},\n\n"
              . "We received a request to reset your password.\n"
              . "Click the link below to set a new password:\n\n"
              . "{$resetLink}\n\n"
              . "This link expires in {$expiresMins} minutes.\n\n"
              . "If you did not request this, ignore this email.\n\n"
              . "— {$companyName}";

        return self::send($toAddress, $toName, "Reset Your Password — {$companyName}", $html, $text);
    }
}
