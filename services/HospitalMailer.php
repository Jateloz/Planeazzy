<?php
/**
 * Planeazzy — HospitalMailer.php
 * SendGrid API email service for hospital provider onboarding.
 * Uses SendGrid Web API v3 (no SDK required).
 */
if (!defined('APP_NAME')) require_once dirname(__DIR__, 2) . '/config/config.php';

class HospitalMailer
{
    // ── SendGrid config — resolved at call time, not parse time ──
    private static function apiKey(): string    { return defined('SENDGRID_API_KEY')    ? SENDGRID_API_KEY    : ''; }
    private static function fromEmail(): string { return defined('SENDGRID_FROM_EMAIL') ? SENDGRID_FROM_EMAIL : ''; }
    private static function fromName(): string  { return defined('SENDGRID_FROM_NAME')  ? SENDGRID_FROM_NAME  : 'Planeazzy'; }

    /* ── Public send methods ──────────────────────────────── */

    /** Send OTP verification email */
    public static function sendOtp(
        string $to, string $name, string $otp, int $hospitalId = 0
    ): bool {
        $subject = 'Clinical Precision — Your Verification Code';
        $html    = self::tplOtp($name, $otp);
        $sent    = self::send($to, $name, $subject, $html);
        self::log($hospitalId, $to, 'otp', $subject, $sent);
        return $sent;
    }

    /** Welcome email after email verified */
    public static function sendWelcome(
        string $to, string $name, string $facilityType, int $hospitalId = 0
    ): bool {
        $subject = 'Welcome to Clinical Precision — Complete Your Setup';
        $html    = self::tplWelcome($name, $facilityType);
        $sent    = self::send($to, $name, $subject, $html);
        self::log($hospitalId, $to, 'welcome', $subject, $sent);
        return $sent;
    }

    /** Profile submitted — under review */
    public static function sendUnderReview(
        string $to, string $name, string $facilityName, int $hospitalId = 0
    ): bool {
        $subject = 'Clinical Precision — Your Application is Under Review';
        $html    = self::tplUnderReview($name, $facilityName);
        $sent    = self::send($to, $name, $subject, $html);
        self::log($hospitalId, $to, 'under_review', $subject, $sent);
        return $sent;
    }

    /** Approved and activated */
    public static function sendApproved(
        string $to, string $name, string $facilityName,
        string $loginUrl, int $hospitalId = 0
    ): bool {
        $subject = 'Clinical Precision — Your Facility is Now Active ✓';
        $html    = self::tplApproved($name, $facilityName, $loginUrl);
        $sent    = self::send($to, $name, $subject, $html);
        self::log($hospitalId, $to, 'approved', $subject, $sent);
        return $sent;
    }

    /** New booking alert to hospital */
    public static function sendNewBooking(
        string $to, string $facilityName, array $booking, int $hospitalId = 0
    ): bool {
        $subject = 'Clinical Precision — New Patient Booking';
        $html    = self::tplNewBooking($facilityName, $booking);
        $sent    = self::send($to, $facilityName, $subject, $html);
        self::log($hospitalId, $to, 'new_booking', $subject, $sent);
        return $sent;
    }

    /* ── Core SendGrid send ───────────────────────────────── */

    private static function send(
        string $to, string $toName, string $subject, string $html
    ): bool {
        // Guard: validate config before attempting send
        $key = self::apiKey();
        if (empty($key) || strlen($key) < 40 || str_starts_with($key, 'YOUR_')) {
            error_log("[HospitalMailer] SENDGRID_API_KEY is not configured in config.php");
            return false;
        }
        if (!filter_var(self::fromEmail(), FILTER_VALIDATE_EMAIL)) {
            error_log("[HospitalMailer] SENDGRID_FROM_EMAIL is not a valid email in config.php");
            return false;
        }
        if (!filter_var(trim($to), FILTER_VALIDATE_EMAIL)) {
            error_log("[HospitalMailer] Invalid recipient email: $to");
            return false;
        }

        $payload = [
            'personalizations' => [[
                'to'      => [['email' => $to, 'name' => $toName]],
                'subject' => $subject,
            ]],
            'from'    => ['email' => self::fromEmail(), 'name' => self::fromName()],
            'content' => [['type' => 'text/html', 'value' => $html]],
        ];

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . self::apiKey(),
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            // --- FIXED FOR HOSTINGER ---
            CURLOPT_SSL_VERIFYPEER => false, // Disables the "trust anchor" check
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        $ok = $httpCode >= 200 && $httpCode < 300;
        if (!$ok) {
            error_log("[HospitalMailer] SendGrid HTTP $httpCode | cURL: $err | to=$to | body=" . substr((string)$body, 0, 300));
        } else {
            // Also log OTP to file in development for easy retrieval
            if (defined('APP_ENV') && APP_ENV === 'development') {
                self::devLog("SENT [$httpCode] to=$to subject=$subject");
            }
        }
        return $ok;
    }

    /* ── DB log ───────────────────────────────────────────── */

    private static function log(
        int $hospitalId, string $email, string $type, string $subject, bool $sent
    ): void {
        try {
            if (!class_exists('Database')) {
                require_once dirname(__DIR__, 2) . '/services/Database.php';
            }
            $db = Database::getInstance();
            $db->query(
                'INSERT INTO hospital_email_log
                 (hospital_id, recipient_email, type, subject, status, sent_at)
                 VALUES (:hid, :email, :type, :subj, :status, NOW())',
                [
                    ':hid'    => $hospitalId ?: null,
                    ':email'  => $email,
                    ':type'   => $type,
                    ':subj'   => $subject,
                    ':status' => $sent ? 'sent' : 'failed',
                ]
            );
        } catch (Exception $e) {
            error_log('HospitalMailer log error: ' . $e->getMessage());
        }
    }

    /* ── Email Templates ──────────────────────────────────── */

    private static function header(): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8">
<style>
body{font-family:Inter,Arial,sans-serif;margin:0;padding:0;background:#f2f4f6;color:#191c1e}
.wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.08)}
.hdr{background:linear-gradient(135deg,#005ab4,#0873df);padding:32px 40px;text-align:center}
.hdr h1{color:#fff;font-size:22px;font-weight:900;margin:0;letter-spacing:-.02em}
.hdr p{color:rgba(255,255,255,.75);font-size:12px;margin:4px 0 0}
.body{padding:40px}
.footer{background:#f2f4f6;padding:20px 40px;text-align:center;font-size:11px;color:#717785}
.btn{display:inline-block;background:linear-gradient(135deg,#005ab4,#0873df);color:#fff;padding:14px 32px;border-radius:10px;font-weight:700;font-size:15px;text-decoration:none;margin:20px 0}
.otp-box{text-align:center;padding:32px;background:#f2f4f6;border-radius:12px;margin:24px 0}
.otp-code{font-size:48px;font-weight:900;letter-spacing:.2em;color:#005ab4;font-family:monospace}
.info-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f2f4f6}
.badge{display:inline-block;background:rgba(0,90,180,.1);color:#005ab4;padding:3px 10px;border-radius:9999px;font-size:11px;font-weight:700}
</style></head><body><div style="padding:20px">
<div class="wrap">';
    }

    private static function footer(): string
    {
        return '<div class="footer">
<p>© ' . date('Y') . ' Clinical Precision — Planeazzy Healthcare Platform. KEPDA Compliant.</p>
<p style="margin-top:4px">This is an automated message. Please do not reply to this email.</p>
</div></div></div></body></html>';
    }

    private static function tplOtp(string $name, string $otp): string
    {
        return self::header() . '
<div class="hdr">
  <h1>Clinical Precision</h1>
  <p>Provider Email Verification</p>
</div>
<div class="body">
  <p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>
  <p>Use the code below to verify your email address. This code expires in <strong>10 minutes</strong>.</p>
  <div class="otp-box">
    <div class="otp-code">' . htmlspecialchars($otp) . '</div>
    <p style="color:#717785;font-size:12px;margin:8px 0 0">One-time verification code</p>
  </div>
  <p>If you did not request this, please ignore this email or contact our support team.</p>
  <p style="margin-top:24px;color:#717785;font-size:12px">
    <span class="badge">KEPDA</span>&nbsp;
    Your data is protected under the Kenya Data Protection Act 2019.
  </p>
</div>' . self::footer();
    }

    private static function tplWelcome(string $name, string $facilityType): string
    {
        $typeLabel = ucfirst($facilityType);
        return self::header() . '
<div class="hdr">
  <h1>Welcome to Clinical Precision</h1>
  <p>Your healthcare provider portal</p>
</div>
<div class="body">
  <p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>
  <p>Your email has been verified. You are now enrolled as a <strong>' . htmlspecialchars($typeLabel) . '</strong> provider on Clinical Precision.</p>
  <p>Complete the following steps to activate your facility:</p>
  <ol style="line-height:2;color:#414753">
    <li>Set up your Organization Profile</li>
    <li>Activate Departments / Faculties</li>
    <li>Submit Regulatory Documents</li>
    <li>Wait for KEPDA verification (24–48 hrs)</li>
  </ol>
  <p>You can log in to your portal at any time to track your verification progress.</p>
  <p style="margin-top:24px;color:#717785;font-size:12px">© ' . date('Y') . ' Clinical Precision — KEPDA Compliant</p>
</div>' . self::footer();
    }

    private static function tplUnderReview(string $name, string $facilityName): string
    {
        return self::header() . '
<div class="hdr">
  <h1>Application Under Review</h1>
  <p>Clinical Precision — Provider Verification</p>
</div>
<div class="body">
  <p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>
  <p>Your facility <strong>' . htmlspecialchars($facilityName) . '</strong> has been submitted for regulatory verification.</p>
  <p>Our KEPDA compliance team will review your documents within <strong>24–48 business hours</strong>. You will receive an email notification once the review is complete.</p>
  <p>You can check your verification status at any time by logging into your Clinical Precision portal.</p>
  <p style="margin-top:24px">
    <span class="badge">KMPDC</span>&nbsp;<span class="badge">KDPA</span>&nbsp;<span class="badge">KEPDA</span>
  </p>
  <p style="margin-top:16px;color:#717785;font-size:12px">Thank you for choosing Clinical Precision.</p>
</div>' . self::footer();
    }

    private static function tplApproved(
        string $name, string $facilityName, string $loginUrl
    ): string {
        return self::header() . '
<div class="hdr" style="background:linear-gradient(135deg,#006a6a,#0d9488)">
  <h1>Facility Activated ✓</h1>
  <p>Clinical Precision — Provider Admin</p>
</div>
<div class="body">
  <p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>
  <p>Congratulations! <strong>' . htmlspecialchars($facilityName) . '</strong> has been approved and is now <strong>live on Planeazzy</strong>.</p>
  <p>Patients can now find and book appointments at your facility. Log in to your dashboard to:</p>
  <ul style="line-height:2;color:#414753">
    <li>Manage appointments and patient bookings</li>
    <li>View analytics and patient flow trends</li>
    <li>Manage insurance integrations</li>
    <li>Configure departments and doctors</li>
  </ul>
  <div style="text-align:center">
    <a class="btn" href="' . htmlspecialchars($loginUrl) . '">Access Your Dashboard</a>
  </div>
  <p style="margin-top:16px;color:#717785;font-size:12px">Welcome to Clinical Precision.</p>
</div>' . self::footer();
    }

    private static function tplNewBooking(string $facilityName, array $b): string
    {
        return self::header() . '
<div class="hdr">
  <h1>New Patient Booking</h1>
  <p>' . htmlspecialchars($facilityName) . '</p>
</div>
<div class="body">
  <p>A new appointment has been booked at your facility.</p>
  <div style="background:#f2f4f6;border-radius:12px;padding:20px;margin:16px 0">
    <div class="info-row"><span style="color:#717785">Patient</span><strong>' . htmlspecialchars($b['patient_name'] ?? 'N/A') . '</strong></div>
    <div class="info-row"><span style="color:#717785">Date &amp; Time</span><strong>' . htmlspecialchars($b['appointment_at'] ?? 'N/A') . '</strong></div>
    <div class="info-row"><span style="color:#717785">Service</span><strong>' . htmlspecialchars($b['service'] ?? 'General') . '</strong></div>
    <div class="info-row" style="border:none"><span style="color:#717785">Type</span><strong>' . htmlspecialchars($b['visit_type'] ?? 'In-person') . '</strong></div>
  </div>
  <p style="color:#717785;font-size:12px">Log in to your Clinical Precision dashboard to confirm or manage this appointment.</p>
</div>' . self::footer();
    }

    private static function devLog(string $msg): void
    {
        $line = date('[Y-m-d H:i:s]') . ' [HospitalMailer] ' . $msg . PHP_EOL;
        $dirs = [ROOT_DIR . '/logs/', sys_get_temp_dir() . '/planeazzy_logs/'];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            if (is_dir($dir) && is_writable($dir)) {
                @file_put_contents($dir . 'mail_dev.log', $line, FILE_APPEND | LOCK_EX);
                return;
            }
        }
        error_log('[Planeazzy] ' . $msg);
    }
}