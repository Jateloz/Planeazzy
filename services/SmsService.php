<?php
/**
 * Planeazzy — SmsService.php
 * SMS delivery via Africa's Talking API (primary Kenyan SMS gateway).
 * Falls back gracefully if not configured — never throws, just logs.
 * Supports sandbox mode automatically in development.
 */
if (!defined('APP_NAME')) require_once dirname(__DIR__) . '/config/config.php';

class SmsService
{
    /*  Public send methods  */

    /**
     * Send booking confirmation SMS to guest/patient.
     */
    public static function sendBookingConfirmation(
        string $phone,
        string $name,
        string $providerName,
        string $dateTime,
        string $serviceType,
        int    $bookingId
    ): bool {
        $fname = explode(' ', trim($name))[0];
        $msg   = "Hi $fname, your Planeazzy booking #$bookingId is confirmed!\n"
               . "Service: " . ucfirst($serviceType) . "\n"
               . "Provider: $providerName\n"
               . "Date: $dateTime\n"
               . "Reply CANCEL to cancel. Planeazzy.com";
        return self::send($phone, $msg, 'booking_confirm');
    }

    /**
     * Send OTP via SMS (for logged-in patient flows).
     */
    public static function sendOtp(string $phone, string $name, string $otp): bool {
        $msg = "Planeazzy: Your verification code is $otp. Expires in " . OTP_EXPIRY_MINUTES . " minutes. Do not share.";
        return self::send($phone, $msg, 'otp');
    }

    /**
     * Send appointment reminder SMS.
     */
    public static function sendReminder(
        string $phone,
        string $name,
        string $providerName,
        string $dateTime
    ): bool {
        $fname = explode(' ', trim($name))[0];
        $msg   = "Hi $fname, reminder: your Planeazzy appointment with $providerName is $dateTime. "
               . "Need to reschedule? Visit planeazzy.com";
        return self::send($phone, $msg, 'reminder');
    }

    /**
     * Send guest booking enquiry acknowledgement.
     */
    public static function sendGuestAck(
        string $phone,
        string $name,
        string $providerName,
        string $bookingRef
    ): bool {
        $fname = explode(' ', trim($name))[0];
        $msg   = "Hi $fname, Planeazzy received your booking enquiry ($bookingRef) with $providerName. "
               . "We'll confirm via email within 2 hours. Questions? Reply here.";
        return self::send($phone, $msg, 'guest_ack');
    }

    /**
     * Send new booking notification to provider/hospital.
     */
    public static function sendProviderBookingAlert(
        string $phone,
        string $providerName,
        string $patientName,
        string $patientPhone,
        string $serviceType,
        string $dateTime,
        int    $bookingId
    ): bool {
        $fname = explode(' ', trim($patientName))[0];
        $msg   = "Planeazzy: New booking #$bookingId from $fname ({$patientPhone}). "
               . "Service: " . ucfirst($serviceType) . ". Date: $dateTime. "
               . "Login to your dashboard to confirm.";
        return self::send($phone, $msg, 'provider_booking_alert');
    }

    /**
     * Send appointment reminder to patient (24hr before).
     */
    public static function sendPatientReminder(
        string $phone,
        string $name,
        string $providerName,
        string $dateTime
    ): bool {
        $fname = explode(' ', trim($name))[0];
        $msg   = "Hi $fname, reminder: your Planeazzy appointment with $providerName "
               . "is tomorrow at $dateTime. Need to reschedule? Visit planeazzy.com or reply HELP.";
        return self::send($phone, $msg, 'patient_reminder');
    }

    /**
     * Send appointment reminder to provider (24hr before their patient visit).
     */
    public static function sendProviderReminder(
        string $phone,
        string $providerName,
        string $patientName,
        string $dateTime,
        int    $bookingId
    ): bool {
        $fname = explode(' ', trim($patientName))[0];
        $msg   = "Planeazzy reminder: $fname has an appointment with you tomorrow at $dateTime. "
               . "Booking #$bookingId. Login to dashboard for details.";
        return self::send($phone, $msg, 'provider_reminder');
    }


    /**
     * Doctor → Patient: generic appointment status update SMS.
     */
    public static function sendAppointmentStatusUpdate(
        string $phone,
        string $patientName,
        string $doctorName,
        string $dateTime,
        string $status,  // confirmed | cancelled | rescheduled | completed
        string $reason   = ''
    ): bool {
        $fname = explode(' ', trim($patientName))[0];
        $msgs  = [
            'confirmed'   => "Hi $fname, your appointment with Dr. $doctorName on $dateTime is CONFIRMED. Please arrive 10 mins early. Planeazzy",
            'cancelled'   => "Hi $fname, your appointment with Dr. $doctorName on $dateTime has been CANCELLED.".($reason?" Reason: $reason":"")." Rebook at planeazzy.com",
            'rescheduled' => "Hi $fname, your appointment with Dr. $doctorName has been RESCHEDULED to $dateTime. Login to planeazzy.com for details.",
            'completed'   => "Hi $fname, your appointment with Dr. $doctorName is now complete. We hope you received great care! Planeazzy",
        ];
        $msg = $msgs[$status] ?? "Hi $fname, your appointment with Dr. $doctorName on $dateTime has been updated to: ".strtoupper($status).". Planeazzy";
        return self::send($phone, $msg, 'doc_appt_'.$status);
    }

    /**
     * Hospital → Patient: appointment status SMS.
     */
    public static function sendHospitalAppointmentSms(
        string $phone,
        string $patientName,
        string $facilityName,
        string $dateTime,
        string $status,
        int    $apptId = 0,
        string $department = ''
    ): bool {
        $fname = explode(' ', trim($patientName))[0];
        $ref   = $apptId ? " Ref #$apptId." : '';
        $dept  = $department ? " Dept: $department." : '';
        $msgs  = [
            'confirmed'  => "Hi $fname, your appointment at $facilityName on $dateTime is CONFIRMED.$dept$ref Planeazzy.com",
            'cancelled'  => "Hi $fname, your appointment at $facilityName on $dateTime has been CANCELLED. We apologise for the inconvenience. Rebook at planeazzy.com",
            'completed'  => "Hi $fname, your visit at $facilityName is complete. Thank you for choosing us. Planeazzy",
        ];
        $msg = $msgs[$status] ?? "Hi $fname, your appointment at $facilityName status: ".strtoupper($status).".$ref Planeazzy";
        return self::send($phone, $msg, 'hosp_appt_'.$status);
    }

    /*  Core send  */

    public static function send(string $phone, string $message, string $type = 'generic'): bool
    {
        // Guard: must have API key configured
        if (!defined('AT_API_KEY') || empty(AT_API_KEY) || AT_API_KEY === 'atsk_xxxxxxxxxxxxxxxxxxxxxxxx') {
            error_log("[SmsService] AT_API_KEY not configured — SMS skipped for $phone");
            self::devLog("SMS SKIPPED (no key) [$phone]: " . substr($message, 0, 60));
            return false;
        }

        // Normalise phone to E.164
        $phone = self::normalisePhone($phone);
        if (!$phone) {
            error_log("[SmsService] Invalid phone number — SMS skipped");
            return false;
        }

        $endpoint = defined('AT_SANDBOX') && AT_SANDBOX
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';

        $from = defined('AT_FROM') ? AT_FROM : 'Planeazzy';

        $postFields = http_build_query([
            'username' => defined('AT_USERNAME') ? AT_USERNAME : 'sandbox',
            'to'       => $phone,
            'message'  => $message,
            'from'     => $from,
        ]);

        if (!function_exists('curl_init')) {
            error_log("[SmsService] cURL not available");
            return false;
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'apiKey: ' . AT_API_KEY,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        $ok = false;
        if ($code === 201 || $code === 200) {
            $resp = json_decode($body, true);
            $recipients = $resp['SMSMessageData']['Recipients'] ?? [];
            foreach ($recipients as $r) {
                if (in_array($r['statusCode'] ?? 0, [100, 101, 102])) {
                    $ok = true; break;
                }
            }
            if (!$ok && !empty($recipients)) {
                error_log("[SmsService] AT error: " . ($recipients[0]['status'] ?? $body));
            }
        } else {
            error_log("[SmsService] HTTP $code | cURL: $err | body: " . substr((string)$body, 0, 200));
        }

        self::devLog("SMS " . ($ok ? 'OK' : 'FAIL') . " [$phone] type=$type: " . substr($message, 0, 60));
        return $ok;
    }

    /*  Helpers  */

    /**
     * Normalise phone to E.164 Kenya format (+254XXXXXXXXX).
     * Accepts: 07xx, 01xx, +2547xx, 2547xx
     */
    public static function normalisePhone(string $phone): string
    {
        $clean = preg_replace('/[^0-9+]/', '', $phone);
        // Already E.164
        if (preg_match('/^\+2547\d{8}$/', $clean) || preg_match('/^\+2541\d{8}$/', $clean)) {
            return $clean;
        }
        // Strip leading +
        $clean = ltrim($clean, '+');
        // 2547XXXXXXXX
        if (preg_match('/^2547\d{8}$/', $clean) || preg_match('/^2541\d{8}$/', $clean)) {
            return '+' . $clean;
        }
        // 07XXXXXXXX
        if (preg_match('/^07\d{8}$/', $clean) || preg_match('/^01\d{8}$/', $clean)) {
            return '+254' . substr($clean, 1);
        }
        // 7XXXXXXXX (9 digits)
        if (preg_match('/^7\d{8}$/', $clean) || preg_match('/^1\d{8}$/', $clean)) {
            return '+254' . $clean;
        }
        // International number not Kenya — pass through
        if (strlen($clean) >= 10) {
            return '+' . $clean;
        }
        return '';
    }

        private static function devLog(string $msg): void
    {
        $line    = date('[Y-m-d H:i:s]') . ' [SMS] ' . $msg . PHP_EOL;
        $logDirs = [
            defined('LOG_DIR')  ? rtrim(LOG_DIR,  '/') . '/' : '',
            defined('ROOT_DIR') ? ROOT_DIR . '/logs/'         : '',
            sys_get_temp_dir()  . '/planeazzy_logs/',
        ];
        $written = false;
        foreach ($logDirs as $dir) {
            if (!$dir) continue;
            if (!is_dir($dir) && !@mkdir($dir, 0775, true)) continue;
            if (!is_writable($dir)) continue;
            @file_put_contents($dir . 'sms_dev.log',  $line, FILE_APPEND | LOCK_EX);
            // OTP messages always go to otp_codes.txt for easy dev retrieval
            if (stripos($msg, 'OTP') !== false || stripos($msg, 'code') !== false) {
                @file_put_contents($dir . 'otp_codes.txt', $line, FILE_APPEND | LOCK_EX);
            }
            $written = true;
            break;
        }
        if (!$written) error_log('[Planeazzy-SMS] ' . $msg);
    }
}
