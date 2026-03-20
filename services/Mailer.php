<?php
/**
 * Planeazzy — Mailer.php  (fixed v4)
 * Pure-PHP SMTP. Uses Mailtrap credentials from config.php.
 * OTP always logged to logs/mail_dev.log as backup.
 */
require_once dirname(__DIR__). '/config/config.php';

class Mailer {

    // ── Public send methods ───────────────────────────────────
    public static function sendOtp(string $to, string $name, string $otp): bool {
        $sent = self::smtpSend($to, APP_NAME . ' — Verification Code', self::otpHtml($name, $otp));
        self::log("OTP for $to ($name): $otp | SMTP:" . ($sent ? 'OK' : 'FAILED'));
        return $sent;
    }

    public static function sendProviderOtp(string $to, string $name, string $otp): bool {
        $sent = self::smtpSend($to, APP_NAME . ' Provider — Verification Code', self::otpHtml($name, $otp));
        self::log("PROVIDER OTP for $to ($name): $otp | SMTP:" . ($sent ? 'OK' : 'FAILED'));
        return $sent;
    }

    public static function sendWelcome(string $to, string $name): void {
        self::smtpSend($to, 'Welcome to ' . APP_NAME . '!', self::welcomeHtml($name));
    }

    // ── Log helper ────────────────────────────────────────────
    private static function log(string $msg): void {
        // Try multiple locations so it works on all systems
        $candidates = [
            ROOT_DIR . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR,
            sys_get_temp_dir()  . DIRECTORY_SEPARATOR . 'planeazzy_logs' . DIRECTORY_SEPARATOR,
        ];

        $line = date('[Y-m-d H:i:s]') . ' ' . $msg . PHP_EOL;

        foreach ($candidates as $dir) {
            if (!is_dir($dir)) {
                // Try to create — suppress error if not possible
                @mkdir($dir, 0775, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                @file_put_contents($dir . 'mail_dev.log', $line, FILE_APPEND | LOCK_EX);
                // Also write a simple readable copy
                @file_put_contents($dir . 'otp_codes.txt', $line, FILE_APPEND | LOCK_EX);
                return;
            }
        }
        // Last resort — PHP error log
        error_log('[Planeazzy OTP] ' . $msg);
    }

    // ── Pure-PHP SMTP sender ──────────────────────────────────
    private static function smtpSend(string $to, string $subject, string $html): bool {
        $host     = MAIL_HOST;
        $port     = MAIL_PORT;
        $user     = MAIL_USER;
        $pass     = MAIL_PASS;
        $from     = MAIL_FROM;
        $fromName = MAIL_FROM_NAME;

        $to      = filter_var(trim($to), FILTER_SANITIZE_EMAIL);
        $subject = str_replace(["\r", "\n"], '', $subject);
        $from    = filter_var(trim($from), FILTER_SANITIZE_EMAIL);

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("[Mailer] Invalid recipient: $to");
            return false;
        }

        try {
            $errno = 0; $errstr = '';
            $sock  = @fsockopen($host, $port, $errno, $errstr, 12);
            if (!$sock) {
                error_log("[Mailer] Cannot connect to $host:$port — $errstr ($errno)");
                return false;
            }
            stream_set_timeout($sock, 12);

            $banner = self::read($sock);
            if (!str_starts_with($banner, '220')) { fclose($sock); return false; }

            // EHLO
            $ehlo = self::cmd($sock, 'EHLO planeazzy.local');
            if (!str_starts_with($ehlo, '250')) { fclose($sock); return false; }

            // STARTTLS (optional — graceful fallback)
            $tls = self::cmd($sock, 'STARTTLS');
            if (str_starts_with($tls, '220')) {
                @stream_socket_enable_crypto($sock, true,
                    STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT |
                    STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT |
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                );
                self::cmd($sock, 'EHLO planeazzy.local');
            }

            // AUTH LOGIN
            $r1 = self::cmd($sock, 'AUTH LOGIN');
            if (!str_starts_with($r1, '334')) { fclose($sock); return false; }

            $r2 = self::cmd($sock, base64_encode($user));
            if (!str_starts_with($r2, '334')) {
                error_log("[Mailer] Username rejected: $r2");
                fclose($sock); return false;
            }

            $r3 = self::cmd($sock, base64_encode($pass));
            if (!str_starts_with($r3, '235')) {
                error_log("[Mailer] Auth failed: $r3");
                fclose($sock); return false;
            }

            // Envelope
            $mf = self::cmd($sock, "MAIL FROM:<$from>");
            if (!str_starts_with($mf, '250')) { fclose($sock); return false; }

            $rc = self::cmd($sock, "RCPT TO:<$to>");
            if (!str_starts_with($rc, '250')) { fclose($sock); return false; }

            $dt = self::cmd($sock, 'DATA');
            if (!str_starts_with($dt, '354')) { fclose($sock); return false; }

            // Message
            $boundary  = 'pz_' . bin2hex(random_bytes(8));
            $plainText = strip_tags(str_replace(
                ['<br>', '<br/>', '</p>', '</div>', '</li>'], "\n", $html
            ));
            $fn  = mb_encode_mimeheader($fromName, 'UTF-8', 'B', "\r\n");
            $sub = mb_encode_mimeheader($subject,  'UTF-8', 'B', "\r\n");

            $msg  = "From: $fn <$from>\r\n";
            $msg .= "To: <$to>\r\n";
            $msg .= "Subject: $sub\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
            $msg .= "Date: " . date('r') . "\r\n";
            $msg .= "Message-ID: <" . bin2hex(random_bytes(12)) . "@planeazzy.com>\r\n";
            $msg .= "X-Mailer: Planeazzy/4.0\r\n\r\n";
            $msg .= "--$boundary\r\n";
            $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $msg .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $msg .= quoted_printable_encode($plainText) . "\r\n\r\n";
            $msg .= "--$boundary\r\n";
            $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
            $msg .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $msg .= quoted_printable_encode($html) . "\r\n\r\n";
            $msg .= "--$boundary--\r\n\r\n.";

            fputs($sock, $msg . "\r\n");
            $resp = self::read($sock);
            $ok   = str_starts_with($resp, '250');

            self::cmd($sock, 'QUIT');
            fclose($sock);
            return $ok;

        } catch (\Throwable $e) {
            error_log("[Mailer] " . $e->getMessage());
            return false;
        }
    }

    private static function cmd($sock, string $c): string {
        fputs($sock, $c . "\r\n");
        return self::read($sock);
    }

    private static function read($sock): string {
        $resp = '';
        while ($line = fgets($sock, 1024)) {
            $resp .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return trim($resp);
    }

    // ── OTP email template ────────────────────────────────────
    private static function otpHtml(string $name, string $otp): string {
        $n = htmlspecialchars($name);
        $a = htmlspecialchars(APP_NAME);
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4ff;font-family:'Segoe UI',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4ff;padding:40px 20px">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:500px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 20px 60px rgba(25,120,229,.12)">
<tr><td style="background:linear-gradient(135deg,#1978e5 0%,#0e7490 100%);padding:32px 40px;text-align:center">
  <p style="margin:0;font-size:24px;font-weight:900;color:#fff;letter-spacing:-0.5px">&#10022; {$a}</p>
  <p style="margin:6px 0 0;font-size:13px;color:rgba(255,255,255,.75)">Healthcare Platform</p>
</td></tr>
<tr><td style="padding:36px 40px">
  <p style="margin:0 0 6px;font-size:16px;font-weight:600;color:#0f172a">Hi {$n},</p>
  <p style="margin:0 0 22px;font-size:14px;color:#64748b;line-height:1.7">
    Use the code below to verify your email address for <strong>{$a}</strong>.
  </p>
  <table width="100%" cellpadding="0" cellspacing="0">
  <tr><td align="center" style="background:#eff6ff;border-radius:14px;padding:28px 20px">
    <p style="margin:0;font-size:50px;font-weight:900;letter-spacing:14px;color:#1978e5;font-family:'Courier New',Courier,monospace">{$otp}</p>
  </td></tr></table>
  <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px">
  <tr><td align="center" style="background:#fefce8;border-radius:10px;padding:11px 18px">
    <p style="margin:0;font-size:13px;color:#92400e">&#9200; Expires in <strong>10 minutes</strong> &nbsp;&bull;&nbsp; &#128274; Never share this code</p>
  </td></tr></table>
  <p style="margin:26px 0 0;font-size:12px;color:#94a3b8;line-height:1.7">
    If you didn't create a {$a} account, you can safely ignore this email.
  </p>
</td></tr>
<tr><td style="background:#f8fafc;padding:18px 40px;text-align:center;border-top:1px solid #f1f5f9">
  <p style="margin:0;font-size:12px;color:#94a3b8">&copy; 2025 {$a} Healthcare Solutions. All rights reserved.</p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>
HTML;
    }

    // ── Welcome email template ────────────────────────────────
    private static function welcomeHtml(string $name): string {
        $n = htmlspecialchars($name);
        $a = htmlspecialchars(APP_NAME);
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f4ff;font-family:'Segoe UI',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4ff;padding:40px 20px">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:500px;background:#fff;border-radius:18px;overflow:hidden">
<tr><td style="background:linear-gradient(135deg,#1978e5,#0e7490);padding:36px;text-align:center;color:#fff">
  <h1 style="margin:0;font-size:26px;font-weight:900">Welcome, {$n}! &#127881;</h1>
  <p style="margin:8px 0 0;opacity:.85;font-size:14px">Your account is now active</p>
</td></tr>
<tr><td style="padding:36px;color:#475569;font-size:14px;line-height:1.8">
  <p>Your <strong>{$a}</strong> account is ready. Book appointments, consult doctors, and access emergency services across Kenya.</p>
</td></tr>
<tr><td style="background:#f8fafc;padding:18px;text-align:center">
  <p style="font-size:12px;color:#94a3b8;margin:0">&copy; 2025 {$a} Healthcare Solutions</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }
}
