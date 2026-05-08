<?php
/**
 * Planeazzy — Mailer.php (Production Ready)
 * Email: SendGrid primary → SMTP fallback → php mail() last resort
 * All sends logged to: email_log table + logs/mail_dev.log + logs/otp_codes.txt
 */
if (!defined('APP_NAME')) require_once dirname(__DIR__) . '/config/config.php';

class Mailer
{
    //  Public sending methods 

    public static function sendOtp(string $to, string $name, string $otp): bool
    {
        $subject = APP_NAME . ' — Your Verification Code';
        //  Log OTP FIRST so it's always retrievable from logs/otp_codes.txt 
        self::fileLog("OTP:patient to=$to name=$name code=$otp", true);
        $html = self::tplOtp($name, $otp, 'Patient');
        $sent = self::send($to, $name, $subject, $html);
        self::dbLog($to, 'patient', null, 'otp_verification', $subject, $sent);
        return $sent;
    }

    public static function sendDoctorOtp(string $to, string $name, string $otp): bool
    {
        $subject = APP_NAME . ' — Doctor Verification Code';
        //  Log OTP FIRST so it's always retrievable from logs/otp_codes.txt 
        self::fileLog("OTP:doctor to=$to name=$name code=$otp", true);
        $html = self::tplOtp($name, $otp, 'Doctor');
        $sent = self::send($to, $name, $subject, $html);
        self::dbLog($to, 'doctor', null, 'otp_verification', $subject, $sent);
        return $sent;
    }

    public static function sendProviderOtp(string $to, string $name, string $otp): bool
    {
        $subject = APP_NAME . ' Partner — Verification Code';
        //  Log OTP FIRST so it's always retrievable from logs/otp_codes.txt 
        self::fileLog("OTP:provider to=$to name=$name code=$otp", true);
        $html = self::tplOtp($name, $otp, 'Provider');
        $sent = self::send($to, $name, $subject, $html);
        self::dbLog($to, 'provider', null, 'otp_verification', $subject, $sent);
        return $sent;
    }

    public static function sendHospitalOtp(string $to, string $name, string $otp): bool
    {
        $subject = APP_NAME . ' — Hospital Verification Code';
        //  Log OTP FIRST so it's always retrievable from logs/otp_codes.txt 
        self::fileLog("OTP:hospital to=$to name=$name code=$otp", true);
        $html = self::tplOtp($name, $otp, 'Hospital');
        $sent = self::send($to, $name, $subject, $html);
        self::dbLog($to, 'hospital', null, 'otp_verification', $subject, $sent);
        return $sent;
    }

    public static function sendWelcome(string $to, string $name, int $patientId = 0): void
    {
        $subject = 'Welcome to ' . APP_NAME . '!';
        $html    = self::tplWelcome($name);
        $sent    = self::send($to, $name, $subject, $html);
        self::dbLog($to, 'patient', $patientId ?: null, 'welcome', $subject, $sent);
        self::fileLog("WELCOME to=$to name=$name sent=" . ($sent ? 'YES' : 'NO'));
    }

    public static function sendAppointmentConfirmation(
        string $to, string $patName, int $patientId, array $appt
    ): bool {
        $subject = APP_NAME . ' — Appointment Confirmed';
        $html    = self::tplAppointmentConfirm($patName, $appt);
        $sent    = self::send($to, $patName, $subject, $html);
        self::dbLog($to, 'patient', $patientId, 'appointment_confirmation', $subject, $sent);
        self::fileLog("APPT_CONFIRM to=$to patient=$patName sent=" . ($sent ? 'YES' : 'NO'));
        return $sent;
    }

    public static function sendProviderNewBooking(
        string $provEmail, string $provName, array $appt, ?string $insuranceNote = null
    ): bool {
        $subject = APP_NAME . ' — New Patient Booking';
        $html    = self::tplProviderNewBooking($provName, $appt, $insuranceNote);
        $sent    = self::send($provEmail, $provName, $subject, $html);
        self::dbLog($provEmail, 'provider', null, 'provider_new_booking', $subject, $sent);
        self::fileLog("PROVIDER_BOOKING to=$provEmail sent=" . ($sent ? 'YES' : 'NO'));
        return $sent;
    }

    public static function sendAppointmentRescheduled(
        string $to, string $name, int $patientId, array $appt
    ): bool {
        $subject = APP_NAME . ' — Appointment Rescheduled';
        $html    = self::tplRescheduled($name, $appt);
        $sent    = self::send($to, $name, $subject, $html);
        self::dbLog($to, 'patient', $patientId, 'appointment_rescheduled', $subject, $sent);
        self::fileLog("RESCHEDULED to=$to sent=" . ($sent ? 'YES' : 'NO'));
        return $sent;
    }

    public static function sendCancellationNotice(
        string $to, string $name, int $patientId, array $appt, string $reason = ''
    ): bool {
        $subject = APP_NAME . ' — Appointment Cancelled';
        $html    = self::tplCancellation($name, $appt, $reason);
        $sent    = self::send($to, $name, $subject, $html);
        self::dbLog($to, 'patient', $patientId, 'appointment_cancelled', $subject, $sent);
        self::fileLog("CANCELLATION to=$to sent=" . ($sent ? 'YES' : 'NO'));
        return $sent;
    }

    /**
     * Alias for sendCancellationNotice — used by manage-appointment.php and update-appointment.php
     */
    public static function sendAppointmentCancelled(
        string $to, string $name, int $patientId, array $appt, string $reason = ''
    ): bool {
        return self::sendCancellationNotice($to, $name, $patientId, $appt, $reason);
    }

    public static function sendHospitalConfirmation(
        string $to, string $patName, string $facName, string $apptDate,
        string $dept = '', int $apptId = 0, string $docName = ''
    ): bool {
        $subject = APP_NAME . ' — Appointment Confirmed at ' . $facName;
        $html    = self::tplHospConfirm($patName, $facName, $apptDate, $dept, $docName);
        $sent    = self::send($to, $patName, $subject, $html);
        self::dbLog($to, 'patient', null, 'hospital_confirmation', $subject, $sent);
        self::fileLog("HOSP_CONFIRM to=$to facility=$facName date=$apptDate sent=" . ($sent ? 'YES' : 'NO'));
        return $sent;
    }

    public static function sendHospitalCancellation(
        string $to, string $patName, string $facName, string $apptDate, string $reason = ''
    ): bool {
        $subject = APP_NAME . ' — Appointment Cancelled by ' . $facName;
        $html    = self::tplHospCancel($patName, $facName, $apptDate, $reason);
        $sent    = self::send($to, $patName, $subject, $html);
        self::dbLog($to, 'patient', null, 'hospital_cancellation', $subject, $sent);
        self::fileLog("HOSP_CANCEL to=$to facility=$facName sent=" . ($sent ? 'YES' : 'NO'));
        return $sent;
    }

    public static function sendInsuranceReceivedAlert(
        string $provEmail, string $provName, string $patName, array $insDoc, int $apptId
    ): bool {
        $subject = APP_NAME . ' — Insurance Document Received';
        $html    = self::tplInsuranceAlert($provName, $patName, $insDoc, $apptId);
        $sent    = self::send($provEmail, $provName, $subject, $html);
        self::dbLog($provEmail, 'provider', null, 'insurance_received', $subject, $sent);
        return $sent;
    }

    //  Core send engine 

    private static function send(
        string $to, string $toName, string $subject, string $html
    ): bool {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::fileLog("INVALID_EMAIL to=$to subject=$subject");
            return false;
        }

        $plain = strip_tags(str_replace(
            ['<br>', '<br/>', '<br />', '</p>', '</li>', '</div>', '<tr>', '</tr>'],
            "\n", $html
        ));

        // 1. SendGrid
        $sgKey = defined('SENDGRID_API_KEY') ? SENDGRID_API_KEY : '';
        if (strlen($sgKey) > 40 && $sgKey !== 'YOUR_SENDGRID_API_KEY_HERE' && function_exists('curl_init')) {
            $result = self::sendViaSendGrid($to, $toName, $subject, $html, $plain, $sgKey);
            if ($result === true) return true;
            self::fileLog("SG_FAIL to=$to error=$result");
        }

        // 2. SMTP (Mailtrap / production SMTP)
        if (defined('MAIL_HOST') && MAIL_HOST && defined('MAIL_USER') && MAIL_USER) {
            $result = self::sendViaSmtp($to, $toName, $subject, $html, $plain);
            if ($result === true) return true;
            self::fileLog("SMTP_FAIL to=$to error=$result");
        }

        // 3. PHP mail() — skip entirely on localhost/dev (no mailserver)
        // OTP codes are ALWAYS written to logs/otp_codes.txt — use the log viewer.
        if (APP_ENV !== 'production') {
            self::fileLog("MAIL_SKIPPED on localhost to=$to subject=$subject — check logs/otp_codes.txt");
            return false;
        }
        // Production only: try php mail()
        $result = self::sendViaMail($to, $toName, $subject, $html, $plain);
        if ($result !== true) {
            self::fileLog("MAIL_FAIL to=$to error=$result subject=$subject");
            return false;
        }
        return true;
    }

    private static function sendViaSendGrid(
        string $to, string $toName, string $subject,
        string $html, string $plain, string $sgKey
    ): bool|string {
        $payload = [
            'personalizations' => [[
                'to'      => [['email' => $to, 'name' => $toName]],
                'subject' => $subject,
            ]],
            'from'    => ['email' => SENDGRID_FROM_EMAIL, 'name' => SENDGRID_FROM_NAME],
            'reply_to'=> ['email' => SENDGRID_FROM_EMAIL, 'name' => SENDGRID_FROM_NAME],
            'content' => [
                ['type' => 'text/plain', 'value' => $plain],
                ['type' => 'text/html',  'value' => $html],
            ],
        ];
        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $sgKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($code >= 200 && $code < 300) return true;
        return "HTTP $code curl=$err body=" . substr((string)$body, 0, 200);
    }

        private static function sendViaSmtp(
        string $to, string $toName, string $subject, string $html, string $plain
    ): bool|string {
        $host     = defined('MAIL_HOST') ? MAIL_HOST : '';
        $port     = (int)(defined('MAIL_PORT') ? MAIL_PORT : 2525);
        $user     = defined('MAIL_USER') ? MAIL_USER : '';
        $pass     = defined('MAIL_PASS') ? MAIL_PASS : '';
        $from     = defined('MAIL_FROM') ? MAIL_FROM : (defined('SENDGRID_FROM_EMAIL') ? SENDGRID_FROM_EMAIL : '');
        $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : APP_NAME;

        if (!$host || !$user) return 'SMTP not configured';

        $errno = 0; $errstr = ''; $timeout = 3; // Short timeout — booking succeeds even if mail server is down
        $conn = @fsockopen('tcp://' . $host, $port, $errno, $errstr, $timeout);
        if (!$conn) {
            $conn = @fsockopen('ssl://' . $host, 465, $errno, $errstr, $timeout);
            if (!$conn) return "SMTP connect failed to $host:$port ($errno: $errstr)";
        }

        $read = function() use ($conn): string {
            $out = '';
            while (($line = fgets($conn, 512)) !== false) {
                $out .= $line;
                if (strlen($line) >= 4 && $line[3] === ' ') break;
            }
            return $out;
        };
        $write = function(string $cmd) use ($conn): void {
            fputs($conn, $cmd . "\r\n");
        };

        $banner = $read();
        if (!str_starts_with($banner, '2')) { fclose($conn); return "Bad banner: " . trim($banner); }

        $domain = parse_url(defined('APP_URL') ? APP_URL : 'http://localhost', PHP_URL_HOST) ?: 'localhost';
        $write("EHLO $domain");
        $ehlo = $read();
        if (!str_starts_with($ehlo, '2')) { fclose($conn); return "EHLO failed: " . trim($ehlo); }

        $write("AUTH LOGIN");
        $r = $read();
        if (!str_starts_with($r, '334')) { fclose($conn); return "AUTH LOGIN failed: " . trim($r); }

        $write(base64_encode($user));
        $r = $read();
        if (!str_starts_with($r, '334')) { fclose($conn); return "Username rejected: " . trim($r); }

        $write(base64_encode($pass));
        $r = $read();
        if (!str_starts_with($r, '235')) { fclose($conn); return "Password rejected: " . trim($r); }

        $write("MAIL FROM:<$from>");
        $r = $read();
        if (!str_starts_with($r, '250')) { fclose($conn); return "MAIL FROM failed: " . trim($r); }

        $write("RCPT TO:<$to>");
        $r = $read();
        if (!str_starts_with($r, '250')) { fclose($conn); return "RCPT TO failed: " . trim($r); }

        $write("DATA");
        $r = $read();
        if (!str_starts_with($r, '354')) { fclose($conn); return "DATA failed: " . trim($r); }

        $bnd = md5(uniqid('', true));
        $msg  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>\r\n";
        $msg .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <$to>\r\n";
        $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: multipart/alternative; boundary=\"$bnd\"\r\n";
        $msg .= "X-Mailer: Planeazzy/" . APP_VERSION . "\r\n";
        $msg .= "\r\n";
        $msg .= "--$bnd\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n";
        $msg .= "\r\n";
        $msg .= chunk_split(base64_encode($plain));
        $msg .= "--$bnd\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n";
        $msg .= "\r\n";
        $msg .= chunk_split(base64_encode($html));
        $msg .= "--$bnd--\r\n";

        // Dot-stuffing: escape lines beginning with a dot
        $msg = preg_replace('/^(\.)$/m', '..', $msg);

        $write($msg);
        $write(".");
        $r = $read();
        $write("QUIT");
        @fclose($conn);

        return str_starts_with($r, '250') ? true : "Message rejected: " . trim($r);
    }

    private static function sendViaMail(
        string $to, string $toName, string $subject, string $html, string $plain
    ): bool|string {
        $from     = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@planeazzy.com';
        $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : APP_NAME;
        $boundary = md5(uniqid());
        $headers  = implode("\r\n", [
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"$boundary\"",
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>",
            "Reply-To: $from",
            "X-Mailer: Planeazzy/" . APP_VERSION,
        ]);
        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($plain)) . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($html)) . "\r\n";
        $body .= "--$boundary--";

        // Suppress PHP's mail() error - it will fail on XAMPP (no local mailserver).
        // OTP codes are always written to logs/otp_codes.txt as backup.
        $ok = @mail("=?UTF-8?B?" . base64_encode($toName) . "?= <$to>",
            "=?UTF-8?B?" . base64_encode($subject) . "?=", $body, $headers);
        return $ok ? true : "php mail() unavailable (XAMPP/localhost) — check logs/otp_codes.txt";
    }

    //  Logging 

    /**
     * Log to files: logs/mail_dev.log (all activity) + logs/otp_codes.txt (OTP only)
     */
    public static function fileLog(string $msg, bool $isOtp = false): void
    {
        $logDirs = [
            defined('LOG_DIR') ? rtrim(LOG_DIR, '/') . '/' : '',
            (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__)) . '/logs/',
            sys_get_temp_dir() . '/planeazzy_logs/',
        ];
        $line    = date('[Y-m-d H:i:s]') . ' [MAIL] ' . $msg . PHP_EOL;
        $written = false;

        foreach ($logDirs as $dir) {
            if (!$dir) continue;
            if (!is_dir($dir) && !@mkdir($dir, 0775, true)) continue;
            if (!is_writable($dir)) continue;
            @file_put_contents($dir . 'mail_dev.log', $line, FILE_APPEND | LOCK_EX);
            // OTPs always go into otp_codes.txt for easy retrieval in dev
            if ($isOtp || stripos($msg, 'OTP') !== false) {
                @file_put_contents($dir . 'otp_codes.txt', $line, FILE_APPEND | LOCK_EX);
            }
            $written = true;
            break;
        }
        if (!$written) {
            error_log('[Planeazzy-Mail] ' . $msg);
        }
    }

    /** Log to database email_log table */
    private static function dbLog(
        string $email, string $recipType, ?int $recipId,
        string $type, string $subject, bool $sent
    ): void {
        try {
            if (!class_exists('Database')) require_once dirname(__DIR__) . '/services/Database.php';
            Database::getInstance()->query(
                'INSERT INTO email_log
                 (recipient_email, recipient_type, recipient_id, email_type, subject, status, sent_at)
                 VALUES (:em, :rt, :rid, :et, :sub, :st, NOW())',
                [
                    ':em'  => $email,
                    ':rt'  => $recipType,
                    ':rid' => $recipId,
                    ':et'  => $type,
                    ':sub' => $subject,
                    ':st'  => $sent ? 'sent' : 'failed',
                ]
            );
        } catch (Throwable $e) {
            error_log('[Mailer::dbLog] ' . $e->getMessage());
        }
    }

    //  Email templates 

    private static function tplBase(string $title, string $body, string $previewText = ''): string
    {
        $logo = defined('APP_URL') ? APP_URL . '/assets/images/logo.png' : '';
        $year = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$title}</title>
  <meta name="description" content="{$previewText}">
  <style>
    body{margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif;color:#1a1c1e}
    .wrap{max-width:580px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
    .header{background:linear-gradient(135deg,#005ab4,#0873df);padding:28px 32px;text-align:center}
    .header h1{color:#fff;font-size:22px;font-weight:800;margin:0;letter-spacing:-.03em}
    .header p{color:rgba(255,255,255,.75);font-size:13px;margin:4px 0 0}
    .body{padding:32px}
    .footer{background:#f8fafc;padding:20px 32px;text-align:center;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8}
    .footer a{color:#005ab4;text-decoration:none}
    .btn{display:inline-block;padding:14px 28px;background:linear-gradient(135deg,#005ab4,#0873df);color:#fff;text-decoration:none;border-radius:10px;font-weight:700;font-size:14px;margin:16px 0}
    .card{background:#f8fafc;border-radius:10px;padding:16px 20px;margin:16px 0;border:1px solid #e2e8f0}
    h2{font-size:18px;font-weight:800;color:#1a1c1e;margin:0 0 8px;letter-spacing:-.03em}
    p{font-size:14px;line-height:1.6;color:#475569;margin:8px 0}
    .pill{display:inline-block;padding:3px 10px;border-radius:9999px;font-size:11px;font-weight:700}
  </style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>Planeazzy</h1>
    <p>Kenya's Healthcare Booking Platform</p>
  </div>
  <div class="body">{$body}</div>
  <div class="footer">
    <p>© {$year} Planeazzy · KEPDA Compliant · <a href="https://planeazzy.com/privacy">Privacy</a> · <a href="https://planeazzy.com/unsubscribe">Unsubscribe</a></p>
    <p>Planeazzy Healthcare Ltd, Nairobi, Kenya</p>
  </div>
</div>
</body>
</html>
HTML;
    }

    private static function tplOtp(string $name, string $otp, string $role = 'Patient'): string
    {
        $year = date('Y');
        $exp  = OTP_EXPIRY_MINUTES;
        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Your OTP Code</title>
<style>body{margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif}
.wrap{max-width:540px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.hdr{background:linear-gradient(135deg,#005ab4,#0873df);padding:28px 32px;text-align:center}
.hdr h1{color:#fff;font-size:22px;font-weight:800;margin:0;letter-spacing:-.03em}
.hdr p{color:rgba(255,255,255,.75);font-size:13px;margin:6px 0 0}
.bdy{padding:32px;text-align:center}
.otp-box{background:#eff6ff;border:2px dashed #005ab4;border-radius:14px;padding:24px;margin:20px 0}
.otp-code{font-size:42px;font-weight:900;letter-spacing:.25em;color:#005ab4;font-family:monospace}
.otp-sub{font-size:12px;color:#64748b;margin-top:8px}
h2{font-size:18px;font-weight:800;color:#1a1c1e;margin:0 0 8px}
p{font-size:14px;line-height:1.6;color:#475569;margin:8px 0}
.warn{background:#fff7ed;border:1px solid #fdba74;border-radius:8px;padding:12px 16px;font-size:12px;color:#92400e;margin-top:16px;text-align:left}
.ftr{background:#f8fafc;padding:20px 32px;text-align:center;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8}
</style></head><body>
<div class="wrap">
  <div class="hdr"><h1>Planeazzy</h1><p>Secure {$role} Verification</p></div>
  <div class="bdy">
    <h2>Hello, {$name}</h2>
    <p>Your one-time verification code for Planeazzy {$role} access:</p>
    <div class="otp-box">
      <div class="otp-code">{$otp}</div>
      <div class="otp-sub">Expires in {$exp} minutes</div>
    </div>
    <p>Enter this code on the verification page to complete your sign-in.</p>
    <div class="warn">
      <strong>Security Notice:</strong> Planeazzy will never ask for your OTP via phone or email.
      Do not share this code with anyone. If you did not request this, please ignore this email.
    </div>
  </div>
  <div class="ftr">© {$year} Planeazzy · KEPDA Compliant · Nairobi, Kenya</div>
</div>
</body></html>
HTML;
    }

    private static function tplWelcome(string $name): string
    {
        $year = date('Y'); $url = defined('APP_URL') ? APP_URL : 'https://planeazzy.com';
        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Welcome</title>
<style>body{margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif}
.wrap{max-width:560px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.hdr{background:linear-gradient(135deg,#005ab4,#0873df);padding:28px 32px;text-align:center}
.hdr h1{color:#fff;font-size:22px;font-weight:800;margin:0}.hdr p{color:rgba(255,255,255,.75);font-size:13px;margin:6px 0 0}
.bdy{padding:32px}.btn{display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#005ab4,#0873df);color:#fff;text-decoration:none;border-radius:10px;font-weight:700;font-size:14px;margin:16px 0}
h2{font-size:18px;font-weight:800;color:#1a1c1e;margin:0 0 10px}p{font-size:14px;line-height:1.6;color:#475569;margin:8px 0}
.feat{display:grid;gap:10px;margin:16px 0}.feat-item{background:#f8fafc;border-radius:10px;padding:12px 16px;border:1px solid #e2e8f0;font-size:13px}
.ftr{background:#f8fafc;padding:20px 32px;text-align:center;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8}
.ftr a{color:#005ab4;text-decoration:none}
</style></head><body>
<div class="wrap">
  <div class="hdr"><h1>Planeazzy</h1><p>Kenya's Healthcare Booking Platform</p></div>
  <div class="bdy">
    <h2>Welcome to Planeazzy, {$name}!</h2>
    <p>Your account is set up and ready. You can now book appointments with hospitals, doctors, clinics and more — all in one place.</p>
    <div class="feat">
      <div class="feat-item"> <strong>Find Care</strong> — Search hospitals, doctors, pharmacies near you</div>
      <div class="feat-item"> <strong>Book Instantly</strong> — Schedule appointments in seconds</div>
      <div class="feat-item"> <strong>Stay Updated</strong> — Get SMS and email reminders</div>
      <div class="feat-item"> <strong>Your Health Data</strong> — Secure, KEPDA compliant storage</div>
    </div>
    <div style="text-align:center"><a href="{$url}/patients/dashboard.php" class="btn">Go to Your Dashboard</a></div>
  </div>
  <div class="ftr"><p>© {$year} Planeazzy · KEPDA Compliant · <a href="{$url}/privacy.php">Privacy Policy</a></p></div>
</div>
</body></html>
HTML;
    }

    private static function tplAppointmentConfirm(string $patName, array $appt): string
    {
        $year    = date('Y');
        $prov    = htmlspecialchars($appt['provider_name'] ?? 'Your Provider');
        $dt      = htmlspecialchars(isset($appt['appointment_at']) && $appt['appointment_at'] ? date('D, M j, Y \a\t g:i A', strtotime($appt['appointment_at'])) : 'Confirmed');
        $type    = htmlspecialchars(ucwords(str_replace('_', ' ', $appt['service_type'] ?? 'Appointment')));
        $ref     = htmlspecialchars($appt['ref'] ?? '');
        $url     = defined('APP_URL') ? APP_URL : 'https://planeazzy.com';
        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Appointment Confirmed</title>
<style>body{margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif}
.wrap{max-width:560px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.hdr{background:linear-gradient(135deg,#005ab4,#0873df);padding:28px 32px;text-align:center}
.hdr h1{color:#fff;font-size:22px;font-weight:800;margin:0}.hdr p{color:rgba(255,255,255,.75);font-size:13px;margin:6px 0 0}
.bdy{padding:32px}.card{background:#f0fdf4;border:1.5px solid #86efac;border-radius:12px;padding:20px;margin:16px 0}
.card-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #dcfce7;font-size:13px}
.card-row:last-child{border-bottom:none}.card-label{color:#64748b;font-weight:600}.card-val{color:#166534;font-weight:700}
h2{font-size:18px;font-weight:800;color:#1a1c1e;margin:0 0 8px}p{font-size:14px;line-height:1.6;color:#475569;margin:8px 0}
.btn{display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#005ab4,#0873df);color:#fff;text-decoration:none;border-radius:10px;font-weight:700;font-size:13px}
.ftr{background:#f8fafc;padding:20px 32px;text-align:center;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8}
</style></head><body>
<div class="wrap">
  <div class="hdr"><h1>Appointment Confirmed</h1><p>Planeazzy Healthcare</p></div>
  <div class="bdy">
    <h2>Hello, {$patName}</h2>
    <p>Your appointment has been <strong style="color:#16a34a">confirmed</strong>. Here are your details:</p>
    <div class="card">
      <div class="card-row"><span class="card-label">Provider</span><span class="card-val">{$prov}</span></div>
      <div class="card-row"><span class="card-label">Date &amp; Time</span><span class="card-val">{$dt}</span></div>
      <div class="card-row"><span class="card-label">Service Type</span><span class="card-val">{$type}</span></div>
      {$ref}
    </div>
    <p>Please arrive 10 minutes early. Bring your ID and any relevant medical documents.</p>
    <div style="text-align:center;margin-top:20px"><a href="{$url}/patients/dashboard.php?tab=appointments" class="btn">View Appointment</a></div>
  </div>
  <div class="ftr">© {$year} Planeazzy · You received this because you booked an appointment.</div>
</div>
</body></html>
HTML;
    }

    private static function tplProviderNewBooking(string $provName, array $appt, ?string $insuranceNote): string
    {
        $year  = date('Y');
        $pat   = htmlspecialchars($appt['patient_name'] ?? 'Patient');
        $dt    = htmlspecialchars((isset($appt['appointment_at']) && $appt['appointment_at']) ? date('D, M j, Y \a\t g:i A', strtotime((isset($appt['appointment_at']) && $appt['appointment_at']) ?? '')) : '');
        $type  = htmlspecialchars(ucfirst($appt['service_type'] ?? 'appointment'));
        $phone = htmlspecialchars($appt['patient_phone'] ?? '');
        $ins   = $insuranceNote ? "<p><strong>Insurance:</strong> " . htmlspecialchars($insuranceNote) . "</p>" : '';
        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>New Patient Booking</title>
<style>body{margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif}
.wrap{max-width:560px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.hdr{background:linear-gradient(135deg,#005ab4,#0873df);padding:24px 32px;text-align:center}
.hdr h1{color:#fff;font-size:20px;font-weight:800;margin:0}
.bdy{padding:28px}.card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin:12px 0;font-size:13px}
h2{font-size:17px;font-weight:800;color:#1a1c1e;margin:0 0 8px}p{font-size:13px;line-height:1.6;color:#475569;margin:6px 0}
.ftr{background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8}
</style></head><body>
<div class="wrap">
  <div class="hdr"><h1>New Patient Booking</h1></div>
  <div class="bdy">
    <h2>Hello, {$provName}</h2>
    <p>A new patient has booked a <strong>{$type}</strong> appointment with you:</p>
    <div class="card">
      <p><strong>Patient:</strong> {$pat}</p>
      <p><strong>Date &amp; Time:</strong> {$dt}</p>
      {$phone}
      {$ins}
    </div>
    <p>Please confirm or manage this appointment from your Planeazzy dashboard.</p>
  </div>
  <div class="ftr">© {$year} Planeazzy</div>
</div>
</body></html>
HTML;
    }

    private static function tplRescheduled(string $name, array $appt): string
    {
        $year = date('Y');
        $dt   = htmlspecialchars((isset($appt['appointment_at']) && $appt['appointment_at']) ? date('D, M j, Y \a\t g:i A', strtotime((isset($appt['appointment_at']) && $appt['appointment_at']) ?? '')) : 'New time TBD');
        $prov = htmlspecialchars($appt['provider_name'] ?? '');
        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Appointment Rescheduled</title>
<style>body{margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif}
.wrap{max-width:540px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.hdr{background:linear-gradient(135deg,#d97706,#f59e0b);padding:24px 32px;text-align:center}
.hdr h1{color:#fff;font-size:20px;font-weight:800;margin:0}
.bdy{padding:28px}.card{background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:16px;margin:12px 0;font-size:13px}
h2{font-size:17px;font-weight:800;margin:0 0 8px}p{font-size:13px;line-height:1.6;color:#475569;margin:6px 0}
.ftr{background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8}
</style></head><body>
<div class="wrap">
  <div class="hdr"><h1>Appointment Rescheduled</h1></div>
  <div class="bdy">
    <h2>Hello, {$name}</h2>
    <p>Your appointment has been rescheduled to a new time:</p>
    <div class="card">
      <p><strong>Provider:</strong> {$prov}</p>
      <p><strong>New Date &amp; Time:</strong> {$dt}</p>
    </div>
    <p>If this new time does not work for you, please contact your provider or log in to reschedule again.</p>
  </div>
  <div class="ftr">© {$year} Planeazzy</div>
</div>
</body></html>
HTML;
    }

    private static function tplCancellation(string $name, array $appt, string $reason): string
    {
        $year   = date('Y');
        $dt     = htmlspecialchars(isset($appt['appointment_at']) && $appt['appointment_at'] ? date('D, M j, Y \a\t g:i A', strtotime($appt['appointment_at'])) : '');
        $prov   = htmlspecialchars($appt['provider_name'] ?? '');
        $reason = $reason ? "<p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>" : '';
        $url    = defined('APP_URL') ? APP_URL : 'https://planeazzy.com';
        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Appointment Cancelled</title>
<style>body{margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif}
.wrap{max-width:540px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.hdr{background:linear-gradient(135deg,#dc2626,#ef4444);padding:24px 32px;text-align:center}
.hdr h1{color:#fff;font-size:20px;font-weight:800;margin:0}
.bdy{padding:28px}.card{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:12px;padding:16px;margin:12px 0;font-size:13px}
h2{font-size:17px;font-weight:800;margin:0 0 8px}p{font-size:13px;line-height:1.6;color:#475569;margin:6px 0}
.btn{display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#005ab4,#0873df);color:#fff;text-decoration:none;border-radius:10px;font-weight:700;font-size:13px}
.ftr{background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8}
</style></head><body>
<div class="wrap">
  <div class="hdr"><h1>Appointment Cancelled</h1></div>
  <div class="bdy">
    <h2>Hello, {$name}</h2>
    <p>We're sorry to inform you that your appointment has been cancelled:</p>
    <div class="card">
      <p><strong>Provider:</strong> {$prov}</p>
      <p><strong>Was scheduled:</strong> {$dt}</p>
      {$reason}
    </div>
    <p>You can rebook at any time from your Planeazzy dashboard.</p>
    <div style="text-align:center;margin-top:16px"><a href="{$url}/patients/book.php" class="btn">Book Again</a></div>
  </div>
  <div class="ftr">© {$year} Planeazzy</div>
</div>
</body></html>
HTML;
    }

    private static function tplHospConfirm(string $patName, string $facName, string $apptDate, string $dept, string $docName = ''): string
    {
        $year = date('Y'); $url = defined('APP_URL') ? APP_URL : 'https://planeazzy.com';
        $dept = $dept ? "<p><strong>Department:</strong> " . htmlspecialchars($dept) . "</p>" : '';
        $docHtml = $docName ? "<p><strong>Your Doctor:</strong> <span style='color:#005ab4;font-weight:700'>" . htmlspecialchars($docName) . "</span></p>" : '';
        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Appointment Confirmed</title>
<style>body{margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif}
.wrap{max-width:540px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.hdr{background:linear-gradient(135deg,#005ab4,#0873df);padding:24px 32px;text-align:center}
.hdr h1{color:#fff;font-size:20px;font-weight:800;margin:0}
.bdy{padding:28px}.card{background:#f0fdf4;border:1.5px solid #86efac;border-radius:12px;padding:16px;margin:12px 0;font-size:13px}
h2{font-size:17px;font-weight:800;margin:0 0 8px}p{font-size:13px;line-height:1.6;color:#475569;margin:6px 0}
.btn{display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#005ab4,#0873df);color:#fff;text-decoration:none;border-radius:10px;font-weight:700;font-size:13px}
.ftr{background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8}
</style></head><body>
<div class="wrap">
  <div class="hdr"><h1>Appointment Confirmed</h1></div>
  <div class="bdy">
    <h2>Hello, {$patName}</h2>
    <p>Your appointment at <strong>{$facName}</strong> is confirmed:</p>
    <div class="card">
      <p><strong>Facility:</strong> {$facName}</p>
      <p><strong>Date &amp; Time:</strong> {$apptDate}</p>
      {$dept}
    </div>
    <p>Please arrive 10 minutes early with a valid ID and any medical records.</p>
    <div style="text-align:center;margin-top:16px"><a href="{$url}/patients/dashboard.php?tab=appointments" class="btn">View Appointment</a></div>
  </div>
  <div class="ftr">© {$year} Planeazzy</div>
</div>
</body></html>
HTML;
    }

    private static function tplHospCancel(string $patName, string $facName, string $apptDate, string $reason): string
    {
        $year   = date('Y'); $url = defined('APP_URL') ? APP_URL : 'https://planeazzy.com';
        $reason = $reason ? "<p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>" : '';
        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Appointment Cancelled</title>
<style>body{margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif}
.wrap{max-width:540px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.hdr{background:linear-gradient(135deg,#dc2626,#ef4444);padding:24px 32px;text-align:center}
.hdr h1{color:#fff;font-size:20px;font-weight:800;margin:0}
.bdy{padding:28px}.card{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:12px;padding:16px;margin:12px 0;font-size:13px}
h2{font-size:17px;font-weight:800;margin:0 0 8px}p{font-size:13px;line-height:1.6;color:#475569;margin:6px 0}
.btn{display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#005ab4,#0873df);color:#fff;text-decoration:none;border-radius:10px;font-weight:700;font-size:13px}
.ftr{background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8}
</style></head><body>
<div class="wrap">
  <div class="hdr"><h1>Appointment Cancelled</h1></div>
  <div class="bdy">
    <h2>Hello, {$patName}</h2>
    <p>Your appointment at <strong>{$facName}</strong> has been cancelled:</p>
    <div class="card">
      <p><strong>Was scheduled:</strong> {$apptDate}</p>
      {$reason}
    </div>
    <p>We apologize for the inconvenience. You can rebook at any time.</p>
    <div style="text-align:center;margin-top:16px"><a href="{$url}/patients/book.php" class="btn">Book Again</a></div>
  </div>
  <div class="ftr">© {$year} Planeazzy</div>
</div>
</body></html>
HTML;
    }

    private static function tplInsuranceAlert(string $provName, string $patName, array $insDoc, int $apptId): string
    {
        $year = date('Y');
        $ins  = htmlspecialchars($insDoc['provider_name'] ?? '');
        $pol  = htmlspecialchars($insDoc['policy_number'] ?? '');
        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Insurance Received</title>
<style>body{margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif}
.wrap{max-width:540px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.hdr{background:linear-gradient(135deg,#005ab4,#0873df);padding:24px 32px;text-align:center}
.hdr h1{color:#fff;font-size:20px;font-weight:800;margin:0}
.bdy{padding:28px}.card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin:12px 0;font-size:13px}
h2{font-size:17px;font-weight:800;margin:0 0 8px}p{font-size:13px;line-height:1.6;color:#475569;margin:6px 0}
.ftr{background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8}
</style></head><body>
<div class="wrap">
  <div class="hdr"><h1>Insurance Document Received</h1></div>
  <div class="bdy">
    <h2>Hello, {$provName}</h2>
    <p>Patient <strong>{$patName}</strong> has shared an insurance document for appointment #{$apptId}:</p>
    <div class="card">
      <p><strong>Insurer:</strong> {$ins}</p>
      <p><strong>Policy No:</strong> {$pol}</p>
    </div>
    <p>Please review this from your Planeazzy dashboard.</p>
  </div>
  <div class="ftr">© {$year} Planeazzy</div>
</div>
</body></html>
HTML;
    }

    /**
     * Send a raw HTML email (used for custom notifications like reschedule)
     */
    public static function sendRaw(string $to, string $toName, string $subject, string $bodyHtml): bool
    {
        $year = date('Y');
        $html = <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<style>body{margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif}
.wrap{max-width:560px;margin:28px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.07)}
.hdr{background:linear-gradient(135deg,#005ab4,#0873df);padding:22px 28px;text-align:center}
.hdr h1{color:#fff;font-size:19px;font-weight:800;margin:0;letter-spacing:-.03em}
.bdy{padding:26px 28px;font-size:14px;line-height:1.65;color:#334155}
.ftr{background:#f8fafc;padding:14px 28px;text-align:center;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8}
</style></head><body>
<div class="wrap">
  <div class="hdr"><h1>Planeazzy</h1></div>
  <div class="bdy">{$bodyHtml}</div>
  <div class="ftr">&copy; {$year} Planeazzy &middot; KEPDA Compliant</div>
</div></body></html>
HTML;
        $sent = self::send($to, $toName, $subject, $html);
        self::fileLog("RAW_EMAIL to=$to subject=$subject sent=".($sent?'YES':'NO'));
        return $sent;
    }

    // Backwards compat aliases
    public static function sendRescheduleNotice(string $to, string $name, int $pid, array $appt): bool
    { return self::sendAppointmentRescheduled($to, $name, $pid, $appt); }
    public static function sendHospitalReschedule(string $to, string $pat, string $fac, string $dt, string $rsn=''): bool
    { return self::sendHospitalConfirmation($to, $pat, $fac, $dt); }
}
