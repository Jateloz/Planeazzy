<?php
require_once dirname(__DIR__). '/config/config.php';
require_once __DIR__ . '/Database.php';

class Security {
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
    }
    public static function regenerateSession(): void { session_regenerate_id(true); }
    public static function destroySession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            $p = session_get_cookie_params();
            setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
            session_destroy();
        }
    }
    public static function isAuthenticated(): bool {
        self::startSession();
        return !empty($_SESSION['patient_id']) && !empty($_SESSION['authenticated']);
    }
    public static function requireAuth(string $redirect = '/patients/login.php'): void {
        self::startSession();
        if (!self::isAuthenticated()) { header('Location: '.$redirect); exit; }
        if (isset($_SESSION['last_activity']) && (time()-$_SESSION['last_activity']) > SESSION_LIFETIME) {
            self::destroySession(); header('Location: '.$redirect.'?reason=timeout'); exit;
        }
        $_SESSION['last_activity'] = time();
    }
    public static function csrfToken(): string {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    public static function verifyCsrf(string $token): bool {
        self::startSession();
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    public static function hashPassword(string $p): string {
        return password_hash($p, PASSWORD_BCRYPT, ['cost'=>BCRYPT_COST]);
    }
    public static function verifyPassword(string $p, string $h): bool {
        return password_verify($p, $h);
    }
    public static function passwordErrors(string $p): array {
        $e=[];
        if (strlen($p)<PASSWORD_MIN_LENGTH) $e[]='At least '.PASSWORD_MIN_LENGTH.' characters.';
        if (!preg_match('/[A-Z]/',$p)) $e[]='Must contain an uppercase letter.';
        if (!preg_match('/[a-z]/',$p)) $e[]='Must contain a lowercase letter.';
        if (!preg_match('/[0-9]/',$p)) $e[]='Must contain a number.';
        if (!preg_match('/[^A-Za-z0-9]/',$p)) $e[]='Must contain a special character.';
        return $e;
    }
    public static function generateOtp(): string {
        return str_pad((string)random_int(0,(10**OTP_LENGTH)-1), OTP_LENGTH,'0',STR_PAD_LEFT);
    }
    public static function hashOtp(string $otp): string {
        return hash_hmac('sha256', $otp, APP_SECRET);
    }
    public static function verifyOtp(string $input, string $stored): bool {
        return hash_equals($stored, self::hashOtp($input));
    }
    public static function clean(string $v): string {
        return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES|ENT_HTML5, 'UTF-8');
    }
    public static function cleanEmail(string $v): string|false {
        $v = filter_var(trim($v), FILTER_SANITIZE_EMAIL);
        return filter_var($v, FILTER_VALIDATE_EMAIL) ? strtolower($v) : false;
    }
    public static function cleanPhone(string $v): string {
        return preg_replace('/[^0-9+\-\s()]/', '', trim($v));
    }
    public static function rateLimit(string $key, int $max=20, int $window=60): bool {
        $db=Database::getInstance(); $now=time();
        $db->query('DELETE FROM rate_limits WHERE expires_at < :n',[':n'=>$now]);
        $row=$db->fetchOne('SELECT request_count FROM rate_limits WHERE `key`=:k',[':k'=>$key]);
        if (!$row) { $db->query('INSERT INTO rate_limits (`key`,request_count,expires_at) VALUES (:k,1,:e)',[':k'=>$key,':e'=>$now+$window]); return true; }
        if ($row['request_count']>=$max) return false;
        $db->query('UPDATE rate_limits SET request_count=request_count+1 WHERE `key`=:k',[':k'=>$key]);
        return true;
    }
    public static function recordAttempt(string $email, string $ip): void {
        Database::getInstance()->query('INSERT INTO login_attempts (email,ip_address,attempted_at) VALUES (:e,:i,:t)',[':e'=>$email,':i'=>$ip,':t'=>time()]);
    }
    public static function isLockedOut(string $email, string $ip): bool {
        $since=time()-LOCKOUT_DURATION;
        $row=Database::getInstance()->fetchOne('SELECT COUNT(*) as c FROM login_attempts WHERE (email=:e OR ip_address=:i) AND attempted_at>:s',[':e'=>$email,':i'=>$ip,':s'=>$since]);
        return (int)($row['c']??0)>=MAX_LOGIN_ATTEMPTS;
    }
    public static function clearAttempts(string $email): void {
        Database::getInstance()->query('DELETE FROM login_attempts WHERE email=:e',[':e'=>$email]);
    }
    public static function ip(): string {
        foreach (['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) { $ip=trim(explode(',',$_SERVER[$k])[0]); if (filter_var($ip,FILTER_VALIDATE_IP)) return $ip; }
        }
        return '0.0.0.0';
    }
}
