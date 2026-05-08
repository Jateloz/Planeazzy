<?php
/**
 * Planeazzy — PatientService.php  v5
 * Handles registration, login, OTP, preferences, consent, insurance docs.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Mailer.php';

class PatientService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    //  REGISTRATION 

    public function register(array $d): array
    {
        $fn    = Security::clean($d['first_name'] ?? '');
        $ln    = Security::clean($d['last_name']  ?? '');
        $email = Security::cleanEmail($d['email'] ?? '');
        $phone = Security::cleanPhone($d['phone'] ?? '');
        $pwd   = $d['password'] ?? '';
        $dob   = Security::clean($d['date_of_birth'] ?? $d['dob'] ?? '');
        $gender= Security::clean($d['gender'] ?? '');
        $lang  = Security::clean($d['language'] ?? 'en');

        $errors = [];
        if (strlen($fn) < 2)   $errors[] = 'First name must be at least 2 characters.';
        if (strlen($ln) < 2)   $errors[] = 'Last name must be at least 2 characters.';
        if (!$email)            $errors[] = 'A valid email address is required.';
        if (strlen($phone) < 7) $errors[] = 'A valid phone number is required.';
        if (empty($dob))        $errors[] = 'Date of birth is required.';
        if (empty($gender))     $errors[] = 'Please select a gender.';
        $pwErr = Security::passwordErrors($pwd);
        if ($pwErr) $errors = array_merge($errors, $pwErr);
        if ($errors) return ['success' => false, 'errors' => $errors];

        if ($this->db->fetchOne('SELECT id FROM patients WHERE email=:e', [':e' => $email])) {
            return ['success' => false, 'errors' => ['An account with this email already exists. Please log in.']];
        }

        try {
            $this->db->beginTransaction();
            $otp = $this->_makeOtp();
            $id  = $this->db->insert(
                'INSERT INTO patients
                 (first_name,last_name,email,phone,password_hash,date_of_birth,gender,
                  preferred_language,otp_hash,otp_expiry,is_verified,status,created_at)
                 VALUES (:fn,:ln,:em,:ph,:pw,:db,:gd,:lg,:oh,:oe,0,"pending",NOW())',
                [':fn'=>$fn,':ln'=>$ln,':em'=>$email,':ph'=>$phone,
                 ':pw'=>Security::hashPassword($pwd),':db'=>$dob,':gd'=>$gender,
                 ':lg'=>$lang,':oh'=>$otp['hash'],':oe'=>$otp['expiry']]
            );
            $this->db->commit();
            Mailer::sendOtp($email, $fn, $otp['code']);
            return [
                'success'    => true,
                'patient_id' => $id,
                'email'      => $email,
                'message'    => 'Account created! Check your email for the verification code.',
            ];
        } catch (Throwable $e) {
            try { $this->db->rollback(); } catch (Throwable $rb) {}
            $msg = $e->getMessage();
            error_log('PatientService::register — ' . $msg . ' in ' . $e->getFile() . ':' . $e->getLine());
            $devMsg = (defined('APP_ENV') && APP_ENV === 'production')
                ? 'DB Error: ' . $msg
                : 'Registration failed. Please try again.';
            return ['success' => false, 'errors' => [$devMsg]];
        }
    }

    //  OTP VERIFICATION 

    public function verifyOtp(int $id, string $otp): array
    {
        $p = $this->db->fetchOne(
            'SELECT first_name, last_name, email, otp_hash, otp_expiry, is_verified
             FROM patients WHERE id=:id',
            [':id' => $id]
        );
        if (!$p)             return ['success' => false, 'message' => 'Account not found.'];
        if ($p['is_verified']) return ['success' => true,  'message' => 'Already verified.'];
        if (strtotime($p['otp_expiry']) < time())
            return ['success' => false, 'message' => 'Code expired. Request a new one.'];
        if (!Security::verifyOtp($otp, $p['otp_hash']))
            return ['success' => false, 'message' => 'Invalid code. Please try again.'];

        $this->db->query(
            'UPDATE patients SET is_verified=1, status="active", otp_hash=NULL, otp_expiry=NULL WHERE id=:id',
            [':id' => $id]
        );

        // Send welcome email now that account is verified
        Mailer::sendWelcome($p['email'], $p['first_name'], $id);

        // Grant default consents (data_sharing + insurance_sharing) — user can revoke later
        $this->grantDefaultConsents($id);

        return ['success' => true, 'message' => 'Email verified successfully!'];
    }

    public function resendOtp(int $id): array
    {
        $p = $this->db->fetchOne(
            'SELECT email, first_name FROM patients WHERE id=:id AND is_verified=0',
            [':id' => $id]
        );
        if (!$p) return ['success' => false, 'message' => 'Account not found or already verified.'];
        $otp = $this->_makeOtp();
        $this->db->query(
            'UPDATE patients SET otp_hash=:oh, otp_expiry=:oe WHERE id=:id',
            [':oh' => $otp['hash'], ':oe' => $otp['expiry'], ':id' => $id]
        );
        Mailer::sendOtp($p['email'], $p['first_name'], $otp['code']);
        return ['success' => true, 'message' => 'New code sent to ' . $p['email']];
    }

    //  LOGIN 

    public function login(string $email, string $password, string $ip): array
    {
        $email = Security::cleanEmail($email);
        if (!$email) return ['success' => false, 'message' => 'Invalid email address.'];

        try {
            if (Security::isLockedOut($email, $ip)) {
                return ['success' => false, 'message' => 'Too many failed attempts. Please wait 15 minutes.'];
            }
        } catch (Throwable $e) {
            error_log('[PatientService] isLockedOut error: ' . $e->getMessage());
        }

        $p = $this->db->fetchOne(
            'SELECT id, first_name, last_name, email, password_hash, is_verified, status
             FROM patients WHERE email=:e',
            [':e' => $email]
        );

        if (!$p || !Security::verifyPassword($password, $p['password_hash'])) {
            try { Security::recordAttempt($email, $ip); } catch(Throwable $e) {}
            return ['success' => false, 'message' => 'Incorrect email or password.'];
        }
        if (!$p['is_verified']) {
            return ['success' => false, 'message' => 'Please verify your email first.',
                    'needs_verification' => true, 'patient_id' => $p['id']];
        }
        if ($p['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account suspended. Contact support@planeazzy.co.ke'];
        }

        try { Security::clearAttempts($email); } catch(Throwable $e) {}
        Security::regenerateSession();
        $this->db->query('UPDATE patients SET last_login=NOW() WHERE id=:id', [':id' => $p['id']]);

        $_SESSION['patient_id']    = $p['id'];
        $_SESSION['patient_name']  = trim($p['first_name'] . ' ' . $p['last_name']);
        $_SESSION['patient_email'] = $p['email'];
        $_SESSION['authenticated'] = true;
        $_SESSION['last_activity'] = time();

        return ['success' => true, 'patient' => ['id' => $p['id'], 'name' => $p['first_name']]];
    }

    //  PREFERENCES 

    public function savePreferences(int $id, string $service): array
    {
        $allowed = ['healthcare', 'doctors', 'clinics', 'ambulance'];
        $service = Security::clean($service);
        if (!in_array($service, $allowed)) return ['success' => false, 'message' => 'Invalid service.'];
        $this->db->query(
            'UPDATE patients SET preferred_service=:s, onboarding_complete=1 WHERE id=:id',
            [':s' => $service, ':id' => $id]
        );
        return ['success' => true];
    }

    //  CONSENT MANAGEMENT 

    /**
     * Grant default consents after email verification.
     * Users can revoke these in Settings > Privacy.
     */
    public function grantDefaultConsents(int $patientId): void
    {
        $ip = Security::ip();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $now = date('Y-m-d H:i:s');

        foreach (['data_sharing', 'insurance_sharing'] as $type) {
            $this->db->query(
                'INSERT INTO patient_consents
                 (patient_id, consent_type, granted, ip_address, user_agent, granted_at, consent_version, created_at)
                 VALUES (:pid, :ct, 1, :ip, :ua, :ga, "1.0", NOW())
                 ON DUPLICATE KEY UPDATE
                   granted=1, ip_address=:ip2, user_agent=:ua2, granted_at=:ga2, revoked_at=NULL',
                [':pid'=>$patientId, ':ct'=>$type,
                 ':ip'=>$ip, ':ua'=>substr($ua,0,255), ':ga'=>$now,
                 ':ip2'=>$ip, ':ua2'=>substr($ua,0,255), ':ga2'=>$now]
            );
        }
    }

    /**
     * Update a single consent type for a patient.
     */
    public function updateConsent(int $patientId, string $type, bool $granted): array
    {
        $allowed = ['data_sharing','insurance_sharing','marketing','telehealth','research'];
        if (!in_array($type, $allowed)) return ['success' => false, 'message' => 'Invalid consent type.'];

        $ip  = Security::ip();
        $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $now = date('Y-m-d H:i:s');

        $this->db->query(
            'INSERT INTO patient_consents
             (patient_id, consent_type, granted, ip_address, user_agent, granted_at, revoked_at, consent_version, created_at)
             VALUES (:pid, :ct, :gr, :ip, :ua, :ga, :ra, "1.0", NOW())
             ON DUPLICATE KEY UPDATE
               granted=:gr2, ip_address=:ip2, user_agent=:ua2,
               granted_at=IF(:gr3=1,:ga2,granted_at),
               revoked_at=IF(:gr4=0,:ra2,NULL)',
            [':pid'=>$patientId, ':ct'=>$type, ':gr'=>$granted?1:0,
             ':ip'=>$ip, ':ua'=>$ua, ':ga'=>$granted?$now:null, ':ra'=>$granted?null:$now,
             ':gr2'=>$granted?1:0, ':ip2'=>$ip, ':ua2'=>$ua,
             ':gr3'=>$granted?1:0, ':ga2'=>$now,
             ':gr4'=>$granted?1:0, ':ra2'=>$now]
        );

        return ['success' => true, 'message' => $granted ? 'Consent granted.' : 'Consent revoked.'];
    }

    /**
     * Get all consents for a patient.
     */
    public function getConsents(int $patientId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT consent_type, granted, granted_at, revoked_at
             FROM patient_consents WHERE patient_id=:pid',
            [':pid' => $patientId]
        );
        $map = [];
        foreach ($rows as $r) $map[$r['consent_type']] = (bool)$r['granted'];
        // Defaults for any not yet set
        foreach (['data_sharing','insurance_sharing','marketing','telehealth','research'] as $t) {
            if (!isset($map[$t])) $map[$t] = false;
        }
        return $map;
    }

    //  GETTER 

    public function get(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, first_name, last_name, email, phone, date_of_birth, gender,
                    preferred_language, preferred_service, onboarding_complete,
                    status, last_login, created_at
             FROM patients WHERE id=:id',
            [':id' => $id]
        );
    }

    //  PRIVATE HELPERS 

    private function _makeOtp(): array
    {
        $code = Security::generateOtp();
        return [
            'code'   => $code,
            'hash'   => Security::hashOtp($code),
            'expiry' => date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60),
        ];
    }
}
