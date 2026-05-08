<?php
/**
 * Planeazzy — ProviderService.php
 * Handles provider registration, OTP, login, and account management.
 */
require_once dirname(__DIR__). '/config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Mailer.php';

class ProviderService {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    //  Register 
    public function register(array $d): array {
        $type    = Security::clean($d['type']    ?? '');
        $name    = Security::clean($d['name']    ?? '');
        $email   = Security::cleanEmail($d['email'] ?? '');
        $phone   = Security::cleanPhone($d['phone'] ?? '');
        $spec    = Security::clean($d['specialty']      ?? '');
        $license = Security::clean($d['license_number'] ?? '');
        $address = Security::clean($d['address']  ?? '');
        $pwd     = $d['password'] ?? '';

        $allowed = ['doctor','clinic','hospital','ambulance','pharmacy','lab'];
        $errors  = [];
        if (!in_array($type, $allowed))       $errors[] = 'Please select a valid provider type.';
        if (strlen($name) < 3)                $errors[] = 'Name must be at least 3 characters.';
        if (!$email)                          $errors[] = 'A valid email address is required.';
        if (strlen($phone) < 7)               $errors[] = 'A valid phone number is required.';
        if (strlen($license) < 3)             $errors[] = 'License / registration number is required.';
        if (empty($address))                  $errors[] = 'Practice address is required.';
        $pwErr = Security::passwordErrors($pwd);
        if ($pwErr) $errors = array_merge($errors, $pwErr);
        if ($errors) return ['success' => false, 'errors' => $errors];

        if ($this->db->fetchOne('SELECT id FROM providers WHERE email=:e', [':e' => $email])) {
            return ['success' => false, 'errors' => ['An account with this email already exists.']];
        }

        try {
            $this->db->beginTransaction();
            $otp  = $this->_makeOtp();
            $id   = $this->db->insert(
                'INSERT INTO providers
                    (type,name,email,phone,password_hash,specialty,license_number,address,
                     otp_hash,otp_expiry,is_verified,status,created_at)
                 VALUES (:ty,:nm,:em,:ph,:pw,:sp,:li,:ad,:oh,:oe,0,"pending",NOW())',
                [
                    ':ty' => $type,  ':nm' => $name,    ':em' => $email,
                    ':ph' => $phone, ':pw' => Security::hashPassword($pwd),
                    ':sp' => $spec,  ':li' => $license, ':ad' => $address,
                    ':oh' => $otp['hash'], ':oe' => $otp['expiry'],
                ]
            );
            $this->db->commit();
            Mailer::sendProviderOtp($email, $name, $otp['code']);
            return [
                'success'     => true,
                'provider_id' => $id,
                'email'       => $email,
                'message'     => 'Account created! Check your email for the verification code.',
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('ProviderService::register — ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Registration failed. Please try again.']];
        }
    }

    //  Verify OTP 
    public function verifyOtp(int $id, string $otp): array {
        $p = $this->db->fetchOne(
            'SELECT otp_hash,otp_expiry,is_verified FROM providers WHERE id=:id',
            [':id' => $id]
        );
        if (!$p)               return ['success' => false, 'message' => 'Account not found.'];
        if ($p['is_verified']) return ['success' => true,  'message' => 'Already verified.'];
        if (strtotime($p['otp_expiry']) < time())
            return ['success' => false, 'message' => 'Code expired. Request a new one.'];
        if (!Security::verifyOtp($otp, $p['otp_hash']))
            return ['success' => false, 'message' => 'Invalid code. Please try again.'];

        $this->db->query(
            'UPDATE providers SET is_verified=1,verified_at=NOW(),otp_hash=NULL,otp_expiry=NULL WHERE id=:id',
            [':id' => $id]
        );
        return ['success' => true, 'message' => 'Email verified! Your account is under review. We will activate it within 24 hours.'];
    }

    //  Resend OTP 
    public function resendOtp(int $id): array {
        $p = $this->db->fetchOne(
            'SELECT email,name FROM providers WHERE id=:id AND is_verified=0',
            [':id' => $id]
        );
        if (!$p) return ['success' => false, 'message' => 'Account not found or already verified.'];
        $otp = $this->_makeOtp();
        $this->db->query(
            'UPDATE providers SET otp_hash=:oh,otp_expiry=:oe WHERE id=:id',
            [':oh' => $otp['hash'], ':oe' => $otp['expiry'], ':id' => $id]
        );
        Mailer::sendProviderOtp($p['email'], $p['name'], $otp['code']);
        return ['success' => true, 'message' => 'New code sent to ' . $p['email']];
    }

    //  Login 
    public function login(string $email, string $password, string $ip): array {
        $email = Security::cleanEmail($email);
        if (!$email) return ['success' => false, 'message' => 'Invalid email address.'];

        if (Security::isLockedOut($email, $ip))
            return ['success' => false, 'message' => 'Too many failed attempts. Wait 15 minutes.'];

        $p = $this->db->fetchOne(
            'SELECT id,name,email,password_hash,is_verified,status FROM providers WHERE email=:e',
            [':e' => $email]
        );

        if (!$p || !Security::verifyPassword($password, $p['password_hash'])) {
            Security::recordAttempt($email, $ip);
            return ['success' => false, 'message' => 'Incorrect email or password.'];
        }

        if (!$p['is_verified']) {
            return [
                'success'           => false,
                'message'           => 'Please verify your email address first.',
                'needs_verification' => true,
                'provider_id'       => $p['id'],
            ];
        }

        if ($p['status'] === 'pending') {
            return ['success' => false, 'message' => 'Your account is pending review. We will email you once it\'s activated (usually within 24 hours).'];
        }

        if ($p['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account suspended or rejected. Contact support@planeazzy.com.'];
        }

        Security::clearAttempts($email);
        Security::regenerateSession();

        $this->db->query('UPDATE providers SET updated_at=NOW() WHERE id=:id', [':id' => $p['id']]);

        $_SESSION['provider_id']    = $p['id'];
        $_SESSION['provider_name']  = $p['name'];
        $_SESSION['provider_email'] = $p['email'];
        $_SESSION['is_provider']    = true;
        $_SESSION['last_activity']  = time();

        return ['success' => true, 'provider' => ['id' => $p['id'], 'name' => $p['name']]];
    }

    //  Get Provider 
    public function get(int $id): ?array {
        return $this->db->fetchOne(
            'SELECT id,name,email,phone,type,specialty,description,license_number,
                    address,city,county,latitude,longitude,rating,review_count,
                    is_available,is_verified,status,opening_hours,services,image_url,created_at
             FROM providers WHERE id=:id',
            [':id' => $id]
        );
    }

    //  Update availability 
    public function setAvailability(int $id, bool $available): void {
        $this->db->query(
            'UPDATE providers SET is_available=:a WHERE id=:id',
            [':a' => (int)$available, ':id' => $id]
        );
    }

    //  Private helpers 
    private function _makeOtp(): array {
        $code = Security::generateOtp();
        return [
            'code'   => $code,
            'hash'   => Security::hashOtp($code),
            'expiry' => date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60),
        ];
    }
}
