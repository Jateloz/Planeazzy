<?php
/**
 * Planeazzy Healthcare — config.php
 * Central configuration. Edit DB credentials here.
 */

// ── App ───────────────────────────────────────────────────────────────────────
define('APP_NAME',    'Planeazzy');
define('APP_ENV',     'development');   // change to 'production' on live server
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE','Africa/Nairobi');

date_default_timezone_set(APP_TIMEZONE);

// ── Database ──────────────────────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'planeazzy_db');
define('DB_USER',    'root');
define('DB_PASS',    '');               // ← your MySQL root password (blank = no password)
define('DB_CHARSET', 'utf8mb4');

// ── Security ──────────────────────────────────────────────────────────────────
define('APP_SECRET',          'change_this_to_a_random_64char_string_in_production');
define('CSRF_TOKEN_LENGTH',   64);
define('SESSION_LIFETIME',    3600);          // 1 hour
define('MAX_LOGIN_ATTEMPTS',  5);
define('LOCKOUT_DURATION',    900);           // 15 minutes in seconds
define('OTP_EXPIRY_MINUTES',  10);
define('OTP_LENGTH',          6);
define('PASSWORD_MIN_LENGTH', 8);
define('BCRYPT_COST',         12);

// ── Paths ─────────────────────────────────────────────────────────────────────
// ROOT_DIR = the folder containing index.php (your document root)
define('ROOT_DIR',    dirname(__DIR__));
define('LOG_DIR',     ROOT_DIR . '/logs/');
define('UPLOAD_DIR',  ROOT_DIR . '/storage/uploads/');

// ── Rate Limiting ─────────────────────────────────────────────────────────────
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW',   60);

// ── Email / SMTP ──────────────────────────────────────────────────────────────
define('MAIL_HOST',      'sandbox.smtp.mailtrap.io');   // ← change to your SMTP host
define('MAIL_PORT',      2525);
define('MAIL_USER',      '42955697eb37f6');                   // ← SMTP username
define('MAIL_PASS',      '9b51a6ad5b7f26');                   // ← SMTP password
define('MAIL_FROM',      'odindojatelo@gmail.com');
define('MAIL_FROM_NAME', APP_NAME);

// ── Session hardening ─────────────────────────────────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure',   APP_ENV === 'production' ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime',  SESSION_LIFETIME);
session_name('PLANEAZZY_SESS');

// ── Error reporting ───────────────────────────────────────────────────────────
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_DIR . 'php_errors.log');
}

// ── Security headers (call at top of every page) ──────────────────────────────
function send_security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (APP_ENV === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
