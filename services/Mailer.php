<?php
/**
 * Planeazzy — Mailer.php v6 (SendGrid)
 * All emails sent via SendGrid Web API v3.
 * No SMTP dependency required.
 * All sends logged to email_log table.
 */
if (!defined('APP_NAME')) require_once dirname(__DIR__) . '/config/config.php';

class Mailer
{
    // ── Public API ────────────────────────────────────────────

    public static function sendOtp(string $to, string $name, string $otp): bool
    {
        $subject = APP_NAME . ' — Your Verification Code';
        $html    = self::tplOtp($name, $otp, 'Patient');
        $sent    = self::sg($to, $name, $subject, $html);
        self::log($to, 'patient', null, 'otp_verification', $subject, $sent);
        self::devLog("OTP [$to] ($name): $otp | SG:" . ($sent ? 'OK' : 'FAIL'));
        return $sent;
    }

    public static function sendProviderOtp(string $to, string $name, string $otp): bool
    {
        $subject = APP_NAME . ' Partner — Verification Code';
        $html    = self::tplOtp($name, $otp, 'Provider');
        $sent    = self::sg($to, $name, $subject, $html);
        self::log($to, 'provider', null, 'otp_verification', $subject, $sent);
        self::devLog("PROVIDER OTP [$to] ($name): $otp | SG:" . ($sent ? 'OK' : 'FAIL'));
        return $sent;
    }

    public static function sendWelcome(string $to, string $name, int $patientId = 0): void
    {
        $subject = 'Welcome to ' . APP_NAME . '! 🎉';
        $html    = self::tplWelcome($name);
        $sent    = self::sg($to, $name, $subject, $html);
        self::log($to, 'patient', $patientId ?: null, 'welcome', $subject, $sent);
    }

    public static function sendAppointmentConfirmation(
        string $to, string $patName, int $patientId, array $appt
    ): bool {
        $subject = APP_NAME . ' — Appointment Confirmed ✓';
        $html    = self::tplAppointmentConfirm($patName, $appt);
        $sent    = self::sg($to, $patName, $subject, $html);
        self::log($to, 'patient', $patientId, 'appointment_confirmation', $subject, $sent);
        return $sent;
    }

    public static function sendProviderNewBooking(
        string $provEmail, string $provName,
        array $appt, ?string $insuranceNote = null
    ): bool {
        $subject = APP_NAME . ' — New Patient Booking';
        $html    = self::tplProviderNewBooking($provName, $appt, $insuranceNote);
        $sent    = self::sg($provEmail, $provName, $subject, $html);
        self::log($provEmail, 'provider', null, 'provider_new_booking', $subject, $sent);
        return $sent;
    }

    public static function sendInsuranceReceivedAlert(
        string $provEmail, string $provName,
        string $patName, array $insDoc, int $apptId
    ): bool {
        $subject = APP_NAME . ' — Insurance Document Received';
        $html    = self::tplInsuranceAlert($provName, $patName, $insDoc, $apptId);
        $sent    = self::sg($provEmail, $provName, $subject, $html);
        self::log($provEmail, 'provider', null, 'insurance_received', $subject, $sent);
        return $sent;
    }

    public static function sendAppointmentReminder(
        string $to, string $name, int $patientId, array $appt
    ): bool {
        $subject = APP_NAME . ' — Appointment Reminder Tomorrow';
        $html    = self::tplReminder($name, $appt);
        $sent    = self::sg($to, $name, $subject, $html);
        self::log($to, 'patient', $patientId, 'appointment_reminder', $subject, $sent);
        return $sent;
    }

    public static function sendAppointmentCancelled(
        string $to, string $name, int $patientId,
        array $appt, string $reason = ''
    ): bool {
        $subject = APP_NAME . ' — Appointment Cancelled';
        $html    = self::tplCancelled($name, $appt, $reason);
        $sent    = self::sg($to, $name, $subject, $html);
        self::log($to, 'patient', $patientId, 'appointment_cancellation', $subject, $sent);
        return $sent;
    }

    // ── SendGrid Web API v3 ───────────────────────────────────

    private static function sg(
        string $to, string $toName, string $subject, string $html
    ): bool {
        if (!filter_var(trim($to), FILTER_VALIDATE_EMAIL)) {
            error_log("[Mailer] Invalid email: $to"); return false;
        }
        $payload = [
            'personalizations' => [[
                'to'      => [['email' => $to, 'name' => $toName]],
                'subject' => $subject,
            ]],
            'from'    => [
                'email' => SENDGRID_FROM_EMAIL,
                'name'  => SENDGRID_FROM_NAME,
            ],
            'reply_to' => [
                'email' => SENDGRID_FROM_EMAIL,
                'name'  => SENDGRID_FROM_NAME,
            ],
            'content' => [
                ['type' => 'text/plain', 'value' => strip_tags(str_replace(['<br>','<br/>','</p>','</li>','</div>'], "\n", $html))],
                ['type' => 'text/html',  'value' => $html],
            ],
        ];

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . SENDGRID_API_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CAINFO         => defined('CURL_CA_BUNDLE') ? CURL_CA_BUNDLE :
                                      (file_exists('C:\\xampp\\php\\extras\\ssl\\cacert.pem')
                                       ? 'C:\\xampp\\php\\extras\\ssl\\cacert.pem' : ''),
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        $ok = $code >= 200 && $code < 300;
        if (!$ok) {
            error_log("[Mailer SG] HTTP $code — $err — to=$to — body=" . substr((string)$body, 0, 300));
        }
        return $ok;
    }

    // ── DB logging ────────────────────────────────────────────

    private static function log(
        string $email, string $recipType, ?int $recipId,
        string $type, string $subject, bool $sent
    ): void {
        try {
            if (!class_exists('Database')) require_once dirname(__DIR__) . '/services/Database.php';
            $db = Database::getInstance();
            $db->query(
                'INSERT INTO email_log
                 (recipient_email,recipient_type,recipient_id,email_type,subject,status,sent_at)
                 VALUES (:em,:rt,:rid,:et,:sub,:st,NOW())',
                [':em'=>$email,':rt'=>$recipType,':rid'=>$recipId,
                 ':et'=>$type,':sub'=>$subject,':st'=>$sent?'sent':'failed']
            );
        } catch (Throwable $e) {
            error_log('[Mailer::log] ' . $e->getMessage());
        }
    }

    private static function devLog(string $msg): void
    {
        $line = date('[Y-m-d H:i:s]') . ' ' . $msg . PHP_EOL;
        foreach ([ROOT_DIR . '/logs/', sys_get_temp_dir() . '/planeazzy_logs/'] as $dir) {
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            if (is_dir($dir) && is_writable($dir)) {
                @file_put_contents($dir . 'mail_dev.log',  $line, FILE_APPEND | LOCK_EX);
                @file_put_contents($dir . 'otp_codes.txt', $line, FILE_APPEND | LOCK_EX);
                return;
            }
        }
        error_log('[Planeazzy] ' . $msg);
    }

    // ── Email templates ───────────────────────────────────────

    private static function wrap(string $body): string
    {
        $app = htmlspecialchars(APP_NAME); $yr = date('Y');
        return <<<HTML
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>$app</title></head>
<body style="margin:0;padding:0;background:#f0f4ff;font-family:'Segoe UI',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4ff;padding:32px 16px">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(25,120,229,.10)">
<tr><td style="background:linear-gradient(135deg,#1978e5 0%,#0e7490 100%);padding:28px 40px;text-align:center">
  <p style="margin:0;font-size:24px;font-weight:900;color:#fff;letter-spacing:-0.5px">$app</p>
  <p style="margin:4px 0 0;font-size:11px;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:1px">Healthcare Platform · Kenya</p>
</td></tr>
<tr><td style="padding:36px 40px 28px">$body</td></tr>
<tr><td style="background:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #f1f5f9">
  <p style="margin:0 0 4px;font-size:12px;color:#94a3b8">&copy; $yr $app Ltd · hello@planeazzy.co.ke · Nairobi, Kenya</p>
  <p style="margin:0;font-size:11px;color:#cbd5e1">This is an automated message. Please do not reply directly to this email.</p>
</td></tr>
</table>
</td></tr></table>
</body></html>
HTML;
    }

    private static function tplOtp(string $name, string $otp, string $type): string
    {
        $n = htmlspecialchars($name); $app = htmlspecialchars(APP_NAME); $exp = OTP_EXPIRY_MINUTES;
        return self::wrap(<<<HTML
<p style="margin:0 0 6px;font-size:18px;font-weight:700;color:#0f172a">Hi $n,</p>
<p style="margin:0 0 24px;font-size:14px;color:#64748b;line-height:1.7">
  Use the code below to verify your <strong>$app $type</strong> account. Expires in <strong>$exp minutes</strong>.
</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px">
<tr><td align="center" style="background:#eff6ff;border:2px dashed #bfdbfe;border-radius:16px;padding:32px 20px">
  <p style="margin:0 0 8px;font-size:11px;font-weight:700;color:#1978e5;text-transform:uppercase;letter-spacing:2px">Verification Code</p>
  <p style="margin:0;font-size:56px;font-weight:900;letter-spacing:18px;color:#1978e5;font-family:'Courier New',monospace;line-height:1">$otp</p>
</td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px">
<tr><td style="background:#fefce8;border-left:4px solid #f59e0b;border-radius:0 10px 10px 0;padding:14px 18px">
  <p style="margin:0;font-size:13px;color:#92400e">⏱ Expires in <strong>$exp minutes</strong> &nbsp;&bull;&nbsp; 🔐 Never share this code with anyone</p>
</td></tr></table>
<p style="margin:0;font-size:12px;color:#94a3b8">If you didn't request this, you can safely ignore this email.</p>
HTML);
    }

    private static function tplWelcome(string $name): string
    {
        $n = htmlspecialchars($name); $app = htmlspecialchars(APP_NAME);
        return self::wrap(<<<HTML
<p style="margin:0 0 6px;font-size:18px;font-weight:700;color:#0f172a">Welcome, $n! 🎉</p>
<p style="margin:0 0 22px;font-size:14px;color:#64748b;line-height:1.7">
  Your <strong>$app</strong> patient account is now verified and ready to use.
</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
<tr><td style="background:#f0f9ff;border-radius:14px;padding:22px 24px">
  <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#0f172a">What you can do now:</p>
  <p style="margin:0 0 8px;font-size:13px;color:#475569">✅ &nbsp;Find &amp; book verified doctors, hospitals &amp; clinics</p>
  <p style="margin:0 0 8px;font-size:13px;color:#475569">✅ &nbsp;HD video telehealth consultations from home</p>
  <p style="margin:0 0 8px;font-size:13px;color:#475569">✅ &nbsp;Emergency ambulance SOS dispatch</p>
  <p style="margin:0;font-size:13px;color:#475569">✅ &nbsp;Upload &amp; share insurance documents instantly</p>
</td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center">
  <a href="https://planeazzy.co.ke/patients/search.php"
     style="display:inline-block;background:linear-gradient(135deg,#1978e5,#0d9488);color:#fff;padding:15px 36px;border-radius:12px;font-size:15px;font-weight:700;text-decoration:none">
    Start Exploring Healthcare →
  </a>
</td></tr></table>
HTML);
    }

    private static function tplAppointmentConfirm(string $patName, array $appt): string
    {
        $n    = htmlspecialchars($patName);
        $prov = htmlspecialchars($appt['provider_name'] ?? 'Your provider');
        $date = htmlspecialchars($appt['date'] ?? '');
        $time = htmlspecialchars($appt['time'] ?? '');
        $svc  = htmlspecialchars(ucfirst(str_replace('_', ' ', $appt['service_type'] ?? 'appointment')));
        $loc  = ($appt['location_type'] ?? '') === 'telehealth' ? '📹 Telehealth (Video Call)' : '🏥 In-Person Visit';
        $ref  = '#' . str_pad($appt['id'] ?? 0, 6, '0', STR_PAD_LEFT);
        $ins  = !empty($appt['insurance_shared'])
            ? '<p style="margin:10px 0 0;font-size:12px;color:#065f46">✅ Insurance document shared with provider</p>'
            : '';
        return self::wrap(<<<HTML
<p style="margin:0 0 6px;font-size:18px;font-weight:700;color:#0f172a">Appointment Confirmed ✓</p>
<p style="margin:0 0 24px;font-size:14px;color:#64748b;line-height:1.7">Hi <strong>$n</strong>, your appointment has been successfully booked.</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
<tr><td style="background:#f0f9ff;border-radius:14px;padding:22px 24px">
  <table width="100%" cellpadding="0" cellspacing="0">
  <tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b;width:40%">Reference</td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:700;color:#0f172a">$ref</td></tr>
  <tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b">Provider</td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:700;color:#0f172a">$prov</td></tr>
  <tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b">Date</td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:700;color:#0f172a">$date</td></tr>
  <tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b">Time</td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:700;color:#0f172a">$time</td></tr>
  <tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b">Service</td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:700;color:#0f172a">$svc</td></tr>
  <tr><td style="padding:8px 0;font-size:13px;color:#64748b">Type</td><td style="padding:8px 0;font-size:13px;font-weight:700;color:#0f172a">$loc</td></tr>
  </table>$ins
</td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px">
<tr><td style="background:#fefce8;border-left:4px solid #f59e0b;border-radius:0 10px 10px 0;padding:14px 18px">
  <p style="margin:0;font-size:13px;color:#92400e">📅 A reminder will be sent 24 hours before your appointment.</p>
</td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center">
  <a href="https://planeazzy.co.ke/patients/dashboard.php?tab=appointments"
     style="display:inline-block;background:#1978e5;color:#fff;padding:14px 32px;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none">
    View My Appointments →
  </a>
</td></tr></table>
HTML);
    }

    private static function tplProviderNewBooking(string $provName, array $appt, ?string $insuranceNote): string
    {
        $n    = htmlspecialchars($provName);
        $pat  = htmlspecialchars($appt['patient_name']  ?? 'Patient');
        $date = htmlspecialchars($appt['date']           ?? '');
        $time = htmlspecialchars($appt['time']           ?? '');
        $svc  = htmlspecialchars(ucfirst(str_replace('_', ' ', $appt['service_type'] ?? 'appointment')));
        $loc  = ($appt['location_type'] ?? '') === 'telehealth' ? '📹 Telehealth' : '🏥 In-Person';
        $ins  = $insuranceNote
            ? "<p style=\"margin:12px 0 0;font-size:13px;color:#065f46\">🛡️ $insuranceNote</p>"
            : '';
        return self::wrap(<<<HTML
<p style="margin:0 0 6px;font-size:18px;font-weight:700;color:#0f172a">New Booking — $pat</p>
<p style="margin:0 0 22px;font-size:14px;color:#64748b;line-height:1.7">Hi <strong>$n</strong>, a new appointment has been booked at your facility.</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
<tr><td style="background:#f0fdf4;border-radius:14px;padding:22px 24px;border:1px solid #bbf7d0">
  <table width="100%" cellpadding="0" cellspacing="0">
  <tr><td style="padding:8px 0;border-bottom:1px solid #dcfce7;font-size:13px;color:#64748b;width:40%">Patient</td><td style="padding:8px 0;border-bottom:1px solid #dcfce7;font-size:13px;font-weight:700">$pat</td></tr>
  <tr><td style="padding:8px 0;border-bottom:1px solid #dcfce7;font-size:13px;color:#64748b">Date</td><td style="padding:8px 0;border-bottom:1px solid #dcfce7;font-size:13px;font-weight:700">$date</td></tr>
  <tr><td style="padding:8px 0;border-bottom:1px solid #dcfce7;font-size:13px;color:#64748b">Time</td><td style="padding:8px 0;border-bottom:1px solid #dcfce7;font-size:13px;font-weight:700">$time</td></tr>
  <tr><td style="padding:8px 0;border-bottom:1px solid #dcfce7;font-size:13px;color:#64748b">Service</td><td style="padding:8px 0;border-bottom:1px solid #dcfce7;font-size:13px;font-weight:700">$svc</td></tr>
  <tr><td style="padding:8px 0;font-size:13px;color:#64748b">Visit Type</td><td style="padding:8px 0;font-size:13px;font-weight:700">$loc</td></tr>
  </table>$ins
</td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center">
  <a href="https://planeazzy.co.ke/providers/dashboard.php?tab=appointments"
     style="display:inline-block;background:#0d9488;color:#fff;padding:14px 32px;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none">
    Manage Appointments →
  </a>
</td></tr></table>
HTML);
    }

    private static function tplInsuranceAlert(string $provName, string $patName, array $doc, int $apptId): string
    {
        $n    = htmlspecialchars($provName);
        $pat  = htmlspecialchars($patName);
        $ins  = htmlspecialchars($doc['provider_name'] ?? 'Insurance');
        $cov  = htmlspecialchars($doc['coverage_type'] ?? 'Health Insurance');
        $pol  = htmlspecialchars($doc['policy_number']  ?? 'N/A');
        $mem  = htmlspecialchars($doc['member_number']  ?? 'N/A');
        $exp  = !empty($doc['expiry_date']) ? htmlspecialchars(date('M d, Y', strtotime($doc['expiry_date']))) : 'N/A';
        return self::wrap(<<<HTML
<p style="margin:0 0 6px;font-size:18px;font-weight:700;color:#0f172a">Insurance Document Received</p>
<p style="margin:0 0 22px;font-size:14px;color:#64748b;line-height:1.7">
  Hi <strong>$n</strong>, patient <strong>$pat</strong> has shared their insurance details for an upcoming appointment.
</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px">
<tr><td style="background:#f0f9ff;border-radius:14px;padding:22px 24px;border:1px solid #bae6fd">
  <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#0f172a">🛡️ Insurance Details</p>
  <table width="100%" cellpadding="0" cellspacing="0">
  <tr><td style="padding:7px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b;width:45%">Insurance Provider</td><td style="padding:7px 0;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:700">$ins</td></tr>
  <tr><td style="padding:7px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b">Coverage Type</td><td style="padding:7px 0;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:700">$cov</td></tr>
  <tr><td style="padding:7px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b">Policy Number</td><td style="padding:7px 0;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:700">$pol</td></tr>
  <tr><td style="padding:7px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b">Member Number</td><td style="padding:7px 0;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:700">$mem</td></tr>
  <tr><td style="padding:7px 0;font-size:13px;color:#64748b">Expiry Date</td><td style="padding:7px 0;font-size:13px;font-weight:700">$exp</td></tr>
  </table>
</td></tr></table>
<p style="font-size:12px;color:#94a3b8;margin-bottom:16px">
  The patient has granted consent for this information to be used for appointment pre-authorisation.
</p>
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center">
  <a href="https://planeazzy.co.ke/providers/dashboard.php?tab=appointments"
     style="display:inline-block;background:#1978e5;color:#fff;padding:14px 32px;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none">
    View Appointment →
  </a>
</td></tr></table>
HTML);
    }

    private static function tplReminder(string $name, array $appt): string
    {
        $n    = htmlspecialchars($name);
        $prov = htmlspecialchars($appt['provider_name'] ?? 'Your provider');
        $date = htmlspecialchars($appt['date'] ?? '');
        $time = htmlspecialchars($appt['time'] ?? '');
        $loc  = ($appt['location_type'] ?? '') === 'telehealth' ? '📹 Telehealth (Video)' : '🏥 In-Person';
        return self::wrap(<<<HTML
<p style="margin:0 0 6px;font-size:18px;font-weight:700;color:#0f172a">Appointment Tomorrow 📅</p>
<p style="margin:0 0 22px;font-size:14px;color:#64748b;line-height:1.7">
  Hi <strong>$n</strong>, this is a reminder that you have an appointment <strong>tomorrow</strong>.
</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
<tr><td style="background:#fefce8;border-radius:14px;padding:22px 24px;border:2px solid #fde68a">
  <p style="margin:0 0 4px;font-size:13px;color:#92400e"><strong>Provider:</strong> $prov</p>
  <p style="margin:0 0 4px;font-size:13px;color:#92400e"><strong>Date:</strong> $date</p>
  <p style="margin:0 0 4px;font-size:13px;color:#92400e"><strong>Time:</strong> $time</p>
  <p style="margin:0;font-size:13px;color:#92400e"><strong>Type:</strong> $loc</p>
</td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center">
  <a href="https://planeazzy.co.ke/patients/dashboard.php?tab=appointments"
     style="display:inline-block;background:#1978e5;color:#fff;padding:14px 32px;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none">
    View Appointment →
  </a>
</td></tr></table>
HTML);
    }

    private static function tplCancelled(string $name, array $appt, string $reason): string
    {
        $n    = htmlspecialchars($name);
        $prov = htmlspecialchars($appt['provider_name'] ?? 'Your provider');
        $date = htmlspecialchars($appt['date'] ?? '');
        $time = htmlspecialchars($appt['time'] ?? '');
        $rsn  = $reason ? '<p style="margin:10px 0 0;font-size:13px;color:#92400e"><strong>Reason:</strong> ' . htmlspecialchars($reason) . '</p>' : '';
        return self::wrap(<<<HTML
<p style="margin:0 0 6px;font-size:18px;font-weight:700;color:#0f172a">Appointment Cancelled</p>
<p style="margin:0 0 22px;font-size:14px;color:#64748b;line-height:1.7">
  Hi <strong>$n</strong>, your appointment has been cancelled.
</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
<tr><td style="background:#fef2f2;border-radius:14px;padding:22px 24px;border:1px solid #fecaca">
  <p style="margin:0 0 4px;font-size:13px;color:#991b1b"><strong>Provider:</strong> $prov</p>
  <p style="margin:0 0 4px;font-size:13px;color:#991b1b"><strong>Date:</strong> $date</p>
  <p style="margin:0;font-size:13px;color:#991b1b"><strong>Time:</strong> $time</p>$rsn
</td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center">
  <a href="https://planeazzy.co.ke/patients/search.php"
     style="display:inline-block;background:#1978e5;color:#fff;padding:14px 32px;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none">
    Book New Appointment →
  </a>
</td></tr></table>
HTML);
    }
}
