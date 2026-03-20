<?php
require_once dirname(__DIR__). '/config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Mailer.php';

class PatientService {
    private Database $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function register(array $d): array {
        $fn    = Security::clean($d['first_name']??'');
        $ln    = Security::clean($d['last_name']??'');
        $email = Security::cleanEmail($d['email']??'');
        $phone = Security::cleanPhone($d['phone']??'');
        $pwd   = $d['password']??'';
        $dob   = Security::clean($d['dob']??'');
        $gender= Security::clean($d['gender']??'');
        $lang  = Security::clean($d['language']??'en');

        $errors=[];
        if (strlen($fn)<2) $errors[]='First name must be at least 2 characters.';
        if (strlen($ln)<2) $errors[]='Last name must be at least 2 characters.';
        if (!$email)        $errors[]='A valid email is required.';
        if (strlen($phone)<7) $errors[]='A valid phone number is required.';
        if (empty($dob))    $errors[]='Date of birth is required.';
        if (empty($gender)) $errors[]='Please select a gender.';
        $pwErr = Security::passwordErrors($pwd);
        if ($pwErr) $errors = array_merge($errors, $pwErr);
        if ($errors) return ['success'=>false,'errors'=>$errors];

        if ($this->db->fetchOne('SELECT id FROM patients WHERE email=:e',[':e'=>$email]))
            return ['success'=>false,'errors'=>['An account with this email already exists.']];

        try {
            $this->db->beginTransaction();
            $otp=$this->_makeOtp();
            $id=$this->db->insert(
                'INSERT INTO patients (first_name,last_name,email,phone,password_hash,date_of_birth,gender,preferred_language,otp_hash,otp_expiry,is_verified,status,created_at) VALUES (:fn,:ln,:em,:ph,:pw,:db,:gd,:lg,:oh,:oe,0,"pending",NOW())',
                [':fn'=>$fn,':ln'=>$ln,':em'=>$email,':ph'=>$phone,':pw'=>Security::hashPassword($pwd),':db'=>$dob,':gd'=>$gender,':lg'=>$lang,':oh'=>$otp['hash'],':oe'=>$otp['expiry']]
            );
            $this->db->commit();
            Mailer::sendOtp($email, $fn, $otp['code']);
            return ['success'=>true,'patient_id'=>$id,'email'=>$email,'message'=>'Account created! Check your email for the verification code.'];
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Register: '.$e->getMessage());
            return ['success'=>false,'errors'=>['Registration failed. Please try again.']];
        }
    }

    public function verifyOtp(int $id, string $otp): array {
        $p=$this->db->fetchOne('SELECT otp_hash,otp_expiry,is_verified FROM patients WHERE id=:id',[':id'=>$id]);
        if (!$p) return ['success'=>false,'message'=>'Account not found.'];
        if ($p['is_verified']) return ['success'=>true,'message'=>'Already verified.'];
        if (strtotime($p['otp_expiry'])<time()) return ['success'=>false,'message'=>'Code expired. Request a new one.'];
        if (!Security::verifyOtp($otp,$p['otp_hash'])) return ['success'=>false,'message'=>'Invalid code. Try again.'];
        $this->db->query('UPDATE patients SET is_verified=1,status="active",otp_hash=NULL,otp_expiry=NULL WHERE id=:id',[':id'=>$id]);
        return ['success'=>true,'message'=>'Email verified!'];
    }

    public function resendOtp(int $id): array {
        $p=$this->db->fetchOne('SELECT email,first_name FROM patients WHERE id=:id AND is_verified=0',[':id'=>$id]);
        if (!$p) return ['success'=>false,'message'=>'Account not found or already verified.'];
        $otp=$this->_makeOtp();
        $this->db->query('UPDATE patients SET otp_hash=:oh,otp_expiry=:oe WHERE id=:id',[':oh'=>$otp['hash'],':oe'=>$otp['expiry'],':id'=>$id]);
        Mailer::sendOtp($p['email'],$p['first_name'],$otp['code']);
        return ['success'=>true,'message'=>'New code sent to '.$p['email']];
    }

    public function login(string $email, string $password, string $ip): array {
        $email=Security::cleanEmail($email);
        if (!$email) return ['success'=>false,'message'=>'Invalid email address.'];
        if (Security::isLockedOut($email,$ip)) return ['success'=>false,'message'=>'Too many failed attempts. Wait 15 minutes.'];
        $p=$this->db->fetchOne('SELECT id,first_name,last_name,email,password_hash,is_verified,status FROM patients WHERE email=:e',[':e'=>$email]);
        if (!$p||!Security::verifyPassword($password,$p['password_hash'])) {
            Security::recordAttempt($email,$ip);
            return ['success'=>false,'message'=>'Incorrect email or password.'];
        }
        if (!$p['is_verified']) return ['success'=>false,'message'=>'Please verify your email first.','needs_verification'=>true,'patient_id'=>$p['id']];
        if ($p['status']!=='active') return ['success'=>false,'message'=>'Account suspended. Contact support.'];
        Security::clearAttempts($email);
        Security::regenerateSession();
        $this->db->query('UPDATE patients SET last_login=NOW() WHERE id=:id',[':id'=>$p['id']]);
        $_SESSION['patient_id']=$p['id'];
        $_SESSION['patient_name']=$p['first_name'].' '.$p['last_name'];
        $_SESSION['patient_email']=$p['email'];
        $_SESSION['authenticated']=true;
        $_SESSION['last_activity']=time();
        return ['success'=>true,'patient'=>['id'=>$p['id'],'name'=>$p['first_name']]];
    }

    public function savePreferences(int $id, string $service): array {
        $allowed=['healthcare','doctors','clinics','ambulance'];
        $service=Security::clean($service);
        if (!in_array($service,$allowed)) return ['success'=>false,'message'=>'Invalid service.'];
        $this->db->query('UPDATE patients SET preferred_service=:s,onboarding_complete=1 WHERE id=:id',[':s'=>$service,':id'=>$id]);
        return ['success'=>true];
    }

    public function get(int $id): ?array {
        return $this->db->fetchOne('SELECT id,first_name,last_name,email,phone,date_of_birth,gender,preferred_language,preferred_service,onboarding_complete,status,last_login,created_at FROM patients WHERE id=:id',[':id'=>$id]);
    }

    private function _makeOtp(): array {
        $code=Security::generateOtp();
        return ['code'=>$code,'hash'=>Security::hashOtp($code),'expiry'=>date('Y-m-d H:i:s',time()+OTP_EXPIRY_MINUTES*60)];
    }
}
