<?php
/**
 * Planeazzy — Doctor Registration (3-step onboarding)
 * Step 1: Personal & Contact Details
 * Step 2: Professional Credentials & Practice Info
 * Step 3: Security (password)
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
require_once dirname(__DIR__, 2) . '/services/Mailer.php';
Security::startSession();

if (!empty($_SESSION['doctor_id']) && !empty($_SESSION['is_doctor'])) {
    header('Location: /doctors/dashboard.php'); exit;
}

$csrf  = Security::csrfToken();
$error = '';
$step  = (int)($_SESSION['doc_reg_step'] ?? 1);
$fd    = $_SESSION['doc_reg_data']   ?? [];

/*  Counties  */
$counties = ['Baringo','Bomet','Bungoma','Busia','Elgeyo-Marakwet','Embu','Garissa','Homa Bay',
 'Isiolo','Kajiado','Kakamega','Kericho','Kiambu','Kilifi','Kirinyaga','Kisii','Kisumu',
 'Kitui','Kwale','Laikipia','Lamu','Machakos','Makueni','Mandera','Marsabit','Meru',
 'Migori','Mombasa','Murang\'a','Nairobi','Nakuru','Nandi','Narok','Nyamira','Nyandarua',
 'Nyeri','Samburu','Siaya','Taita-Taveta','Tana River','Tharaka-Nithi','Trans Nzoia',
 'Turkana','Uasin Gishu','Vihiga','Wajir','West Pokot'];

/*  Specialties  */
$specialties = ['General Practitioner','Internal Medicine','Pediatrician','Gynecologist & Obstetrician',
 'Cardiologist','Neurologist','Orthopedic Surgeon','General Surgeon','Dermatologist',
 'Ophthalmologist','ENT Specialist (Otolaryngologist)','Psychiatrist & Mental Health',
 'Radiologist','Oncologist','Anesthesiologist','Gastroenterologist','Pulmonologist',
 'Endocrinologist','Rheumatologist','Urologist','Nephrologist','Hematologist',
 'Pathologist','Emergency Medicine','Family Medicine','Sports Medicine',
 'Plastic & Reconstructive Surgeon','Dentist & Oral Surgeon','Physiotherapist','Nutritionist','Other'];

/*  Languages  */
$allLangs = ['English','Swahili','Kikuyu','Luo','Luhya','Kamba','Kalenjin','Meru','Somali','Mijikenda','Taita','Maasai','Arabic','French','Other'];

/*  POST handling  */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired. Please refresh the page.';
    } else {
        $action = $_POST['form_action'] ?? '';

        /*  Step 1  Personal & Contact  */
        if ($action === 'step1') {
            $fn   = trim(strip_tags($_POST['first_name'] ?? ''));
            $ln   = trim(strip_tags($_POST['last_name']  ?? ''));
            $em   = strtolower(trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL)));
            $ph   = preg_replace('/[^0-9+\-\s()]/', '', trim($_POST['phone'] ?? ''));
            $gen  = in_array($_POST['gender'] ?? '', ['male','female','other']) ? $_POST['gender'] : 'male';
            $dob  = trim($_POST['dob'] ?? '');
            $cty  = trim(strip_tags($_POST['county'] ?? ''));
            $city = trim(strip_tags($_POST['city']   ?? ''));
            $addr = trim(strip_tags($_POST['address'] ?? ''));

            if (!$fn || !$ln)                              $error = 'First and last name are required.';
            elseif (!filter_var($em, FILTER_VALIDATE_EMAIL)) $error = 'Please enter a valid email address.';
            elseif (strlen($ph) < 9)                       $error = 'Please enter a valid phone number.';
            elseif (!$cty)                                 $error = 'Please select your county.';
            else {
                try {
                    $db  = Database::getInstance();
                    $chk = $db->fetchOne('SELECT id FROM doctors WHERE email=:e', [':e' => $em]);
                    if ($chk) {
                        $error = 'An account with this email already exists. <a href="/doctors/onboarding/login.php" style="color:#059669">Sign in instead &rarr;</a>';
                    } else {
                        $fd = array_merge($fd, compact('fn','ln','em','ph','gen','dob','cty','city','addr'));
                        $_SESSION['doc_reg_data'] = $fd;
                        $_SESSION['doc_reg_step'] = 2;
                        $step = 2;
                    }
                } catch (Exception $e) {
                    $error = 'Service temporarily unavailable. Try again.';
                }
            }

        /*  Step 2  Professional Credentials  */
        } elseif ($action === 'step2') {
            $spec     = trim(strip_tags($_POST['specialty']      ?? ''));
            $lic      = trim(strip_tags($_POST['kmpdc_licence']  ?? ''));
            $yrs      = (int)($_POST['years_exp']  ?? 0);
            $fee      = (float)($_POST['consult_fee'] ?? 0);
            $langs    = trim(strip_tags($_POST['languages']      ?? 'English'));
            $edu      = trim(strip_tags($_POST['education']      ?? ''));
            $workplace= trim(strip_tags($_POST['workplace']      ?? ''));
            $bio      = trim(strip_tags($_POST['bio']            ?? ''));
            $tele     = !empty($_POST['accepts_tele'])   ? 1 : 0;
            $walkin   = !empty($_POST['accepts_walkin']) ? 1 : 0;
            $agree    = !empty($_POST['agree']);

            if (!$spec)  $error = 'Please select your medical specialty.';
            elseif (!$lic) $error = 'KMPDC licence number is required for verification.';
            elseif (!$agree) $error = 'You must agree to the Terms of Service to continue.';
            else {
                $fd = array_merge($fd, compact('spec','lic','yrs','fee','langs','edu','workplace','bio','tele','walkin'));
                $_SESSION['doc_reg_data'] = $fd;
                $_SESSION['doc_reg_step'] = 3;
                $step = 3;
            }

        /*  Step 3  Security  */
        } elseif ($action === 'step3') {
            $pw  = $_POST['password']  ?? '';
            $pw2 = $_POST['password2'] ?? '';

            if (strlen($pw) < 8)        $error = 'Password must be at least 8 characters long.';
            elseif ($pw !== $pw2)       $error = 'Passwords do not match.';
            else {
                $errs = Security::passwordErrors($pw);
                if ($errs) { $error = implode(' ', $errs); }
                else {
                    try {
                        $db   = Database::getInstance();
                        $otp  = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        $hash = hash('sha256', $otp);
                        $name = trim($fd['fn'] . ' ' . $fd['ln']);

$docId = $db->insert(
    'INSERT INTO doctors
     (first_name, last_name, email, phone, password_hash,
      gender, county, city, address,
      specialty, kmpdc_licence, years_exp, consult_fee,
      languages, education, bio,
      accepts_tele, accepts_walkin,
      email_otp_hash, email_otp_at, status, is_active, created_at)
    VALUES
     (:fn,:ln,:em,:ph,:pw,
      :gen,:cty,:city,:addr,
      :sp,:lic,:ye,:fee,
      :lang,:edu,:bio,
      :tele,:walkin,
      :oh, :otp_at, "pending", 0, :created_at)',
    [
        ':fn'    => $fd['fn'],      
        ':ln'    => $fd['ln'],
        ':em'    => $fd['em'],      
        ':ph'    => $fd['ph'],
        ':pw'    => Security::hashPassword($pw),
        ':gen'   => $fd['gen'],     
        ':cty'   => $fd['cty'],
        ':city'  => $fd['city'] ?? '', 
        ':addr'  => $fd['addr'] ?? '',
        ':sp'    => $fd['spec'],    
        ':lic'   => $fd['lic'],
        ':ye'    => $fd['yrs'],     
        ':fee'   => $fd['fee'],
        ':lang'  => $fd['langs'],   
        ':edu'   => $fd['edu'] ?? '',
        ':bio'   => $fd['bio'] ?? '',
        ':tele'  => $fd['tele'],    
        ':walkin' => $fd['walkin'],
        ':oh'    => $hash,
        ':otp_at'     => date('Y-m-d H:i:s'), 
        ':created_at' => date('Y-m-d H:i:s')
    ]
);

                        // Send OTP email
                        $sent = false;
                        try {
                            $sent = Mailer::sendOtp($fd['em'], $name, $otp);
                        } catch (Exception $me) {
                            error_log('[Doctor Reg OTP] ' . $me->getMessage());
                        }

                        // Always write OTP to log file (for retrieval when email not configured)
                        $logDir = defined('LOG_DIR') ? LOG_DIR : dirname(__DIR__, 2) . '/logs/';
                        if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
                        $logLine = date('[Y-m-d H:i:s]') . " OTP-REGISTER [$fd[em]] ($name) ID:$docId OTP:$otp sent:" . ($sent ? 'yes' : 'no') . "\n";
                        @file_put_contents($logDir . 'otp_codes.txt', $logLine, FILE_APPEND | LOCK_EX);
                        @file_put_contents($logDir . 'mail_dev.log',  $logLine, FILE_APPEND | LOCK_EX);

                        // Also send SMS OTP if phone available
                        if (!empty($fd['ph'])) {
                            try {
                                require_once dirname(__DIR__, 2) . '/services/SmsService.php';
                                SmsService::sendOtp($fd['ph'], $name, $otp);
                            } catch (Exception $se) {}
                        }

                        unset($_SESSION['doc_reg_data'], $_SESSION['doc_reg_step']);
                        $_SESSION['doctor_otp_id']    = $docId;
                        $_SESSION['doctor_otp_email'] = $fd['em'];
                        header('Location: /doctors/onboarding/verify.php'); exit;

                    } catch (Exception $e) {
                        $error = 'Registration failed. Please try again. (' . substr($e->getMessage(), 0, 60) . ')';
                        error_log('[Doctor Register] ' . $e->getMessage());
                    }
                }
            }

        /*  Back  */
        } elseif ($action === 'back') {
            $step = max(1, $step - 1);
            $_SESSION['doc_reg_step'] = $step;
        }
    }
}

$stepTitles = ['', 'Personal Information', 'Professional Credentials', 'Account Security'];
$stepSubs   = ['', 'Tell us who you are and how patients can reach you.',
                   'Your medical background and practice details.',
                   'Set a strong password to protect your account.'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title>Doctor Registration — Planeazzy</title>
  <link rel="icon" type="image/png" href="/assets/images/favicon.png">
  <link rel="apple-touch-icon" href="/assets/images/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;background:#f0fdf4;display:flex;align-items:flex-start;justify-content:center;padding:24px 16px}
.container{width:100%;max-width:600px}
.top-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.logo{display:flex;align-items:center;gap:8px;text-decoration:none}
.logo img{height:28px}
.logo-text{font-size:1rem;font-weight:900;color:#059669;letter-spacing:-.03em}
.back-link{font-size:.8125rem;color:#6b7280;text-decoration:none}
.back-link:hover{color:#059669}
/* Step indicator */
.steps{display:flex;align-items:center;gap:0;margin-bottom:28px}
.step-item{display:flex;align-items:center;flex:1}
.step-circle{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.6875rem;font-weight:800;flex-shrink:0;transition:all .3s}
.step-circle.done{background:#059669;color:#fff}
.step-circle.active{background:#059669;color:#fff;box-shadow:0 0 0 4px rgba(5,150,105,.2)}
.step-circle.future{background:#e5e7eb;color:#9ca3af}
.step-line{flex:1;height:2px;background:#e5e7eb;margin:0 4px;transition:background .3s}
.step-line.done{background:#059669}
.step-label{display:none}
/* Card */
.card{background:#fff;border-radius:20px;box-shadow:0 4px 24px rgba(0,0,0,.07);overflow:hidden}
.card-header{background:linear-gradient(135deg,#059669,#0d9488);padding:24px 28px}
.card-header-top{display:flex;align-items:center;gap:12px;margin-bottom:4px}
.card-icon{width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff;flex-shrink:0}
.card-step-label{font-size:.6875rem;font-weight:700;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.1em}
.card-title{font-size:1.375rem;font-weight:800;color:#fff;letter-spacing:-.03em;line-height:1.2}
.card-sub{font-size:.8125rem;color:rgba(255,255,255,.75);margin-top:4px}
.card-body{padding:28px}
/* Form */
.err-box{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:12px;padding:12px 15px;font-size:.8125rem;color:#991b1b;margin-bottom:20px;line-height:1.5;display:flex;align-items:flex-start;gap:8px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
.field-group{margin-bottom:16px}
.field-label{font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#374151;margin-bottom:6px;display:block}
.field-label .req{color:#ef4444;margin-left:2px}
.field-input{width:100%;padding:10px 13px;background:#f9fafb;border:2px solid #e5e7eb;border-radius:10px;font-family:inherit;font-size:.875rem;color:#111827;outline:none;transition:all .2s}
.field-input:focus{background:#fff;border-color:#059669;box-shadow:0 0 0 4px rgba(5,150,105,.1)}
.field-input.select-inp{cursor:pointer}
.field-hint{font-size:.6875rem;color:#9ca3af;margin-top:4px;line-height:1.4}
.checkbox-row{display:flex;align-items:flex-start;gap:10px;padding:12px;background:#f0fdf4;border-radius:10px;border:1.5px solid #bbf7d0;margin-bottom:10px}
.checkbox-row input{width:17px;height:17px;accent-color:#059669;flex-shrink:0;margin-top:1px}
.checkbox-row label{font-size:.875rem;color:#065f46;font-weight:500;cursor:pointer;line-height:1.5}
.agree-row{display:flex;align-items:flex-start;gap:10px;padding:14px;background:#fafafa;border-radius:10px;border:1.5px solid #e5e7eb;margin-bottom:16px}
.agree-row input{width:18px;height:18px;accent-color:#059669;flex-shrink:0;margin-top:1px}
.agree-row label{font-size:.8125rem;color:#374151;cursor:pointer;line-height:1.6}
.agree-row a{color:#059669;text-decoration:none;font-weight:600}
.pw-strength{height:4px;border-radius:9999px;background:#e5e7eb;margin-top:6px;overflow:hidden}
.pw-fill{height:100%;border-radius:9999px;transition:all .3s;width:0}
.btn-row{display:flex;gap:10px;margin-top:20px}
.btn{flex:1;padding:12px 18px;border:none;border-radius:12px;font-family:inherit;font-size:.9rem;font-weight:700;cursor:pointer;transition:all .2s;text-align:center}
.btn-primary{background:linear-gradient(135deg,#059669,#0d9488);color:#fff}
.btn-primary:hover{opacity:.92;transform:translateY(-1px)}
.btn-ghost{background:#f3f4f6;color:#374151;border:2px solid #e5e7eb}
.btn-ghost:hover{border-color:#059669;color:#059669}
.footer-link{text-align:center;margin-top:16px;font-size:.8125rem;color:#6b7280}
.footer-link a{color:#059669;font-weight:600;text-decoration:none}
.footer-link a:hover{text-decoration:underline}
.langs-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}
.lang-check{display:flex;align-items:center;gap:6px;padding:6px 8px;border:1.5px solid #e5e7eb;border-radius:8px;cursor:pointer;transition:all .15s;font-size:.8125rem;color:#374151}
.lang-check:hover{border-color:#059669;color:#059669}
.lang-check input{accent-color:#059669;width:14px;height:14px}
.section-head{font-size:.6875rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#059669;margin:20px 0 12px;padding-bottom:6px;border-bottom:2px solid #dcfce7;display:flex;align-items:center;gap:6px}
/* Document Upload Styles */
.doc-upload-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: #fff;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  margin-bottom: 8px;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
}
.doc-upload-item:hover {
  border-color: #059669;
  background: #f0fdf4;
}
.doc-upload-item.uploaded {
  border-color: #059669;
  background: #f0fdf4;
}
.doc-upload-icon {
  width: 36px;
  height: 36px;
  background: #f3f4f6;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  font-size: 16px;
}
.doc-upload-item.uploaded .doc-upload-icon {
  background: #059669;
  color: #fff;
}
.doc-upload-info {
  flex: 1;
}
.doc-upload-name {
  font-size: 0.8125rem;
  font-weight: 700;
  color: #374151;
}
.doc-upload-desc {
  font-size: 0.6875rem;
  color: #6b7280;
}
.doc-upload-status {
  font-size: 0.625rem;
  font-weight: 800;
  text-transform: uppercase;
  padding: 2px 6px;
  border-radius: 4px;
}
.doc-upload-status.req { background: #fee2e2; color: #b91c1c; }
.doc-upload-status.opt { background: #f3f4f6; color: #4b5563; }
.doc-upload-status.done { background: #dcfce7; color: #15803d; }

.doc-upload-action {
  font-size: 0.75rem;
  font-weight: 600;
  color: #059669;
  display: flex;
  align-items: center;
  gap: 4px;
}
.doc-uploaded-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}
.doc-tag {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #059669;
  color: #fff;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 500;
}
.doc-tag-size { opacity: 0.8; font-size: 0.6875rem; }
@media(max-width:560px){.grid2,.grid3{grid-template-columns:1fr}.langs-grid{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<div class="container">

  <!-- Top bar -->
  <div class="top-bar">
    <a href="/" class="logo">
      <img src="/assets/images/favicon1.png" alt="Planeazzy" onerror="this.style.display='none'">
    </a>
    <a href="/doctors/onboarding/login.php" class="back-link">&larr; Back to login</a>
  </div>

  <!-- Step indicator -->
  <div class="steps">
    <?php for ($i = 1; $i <= 3; $i++):
      $cls = $i < $step ? 'done' : ($i === $step ? 'active' : 'future');
    ?>
    <div class="step-item">
      <div class="step-circle <?= $cls ?>"><?= $i < $step ? '<i class="fa-solid fa-check"></i>' : $i ?></div>
      <?php if ($i < 3): ?><div class="step-line <?= $i < $step ? 'done' : '' ?>"></div><?php endif; ?>
    </div>
    <?php endfor; ?>
  </div>

  <!-- Card -->
  <div class="card">
    <div class="card-header">
      <div class="card-header-top">
        <div class="card-icon"><?= ['','<i class="fa-solid fa-user"></i>','<i class="fa-solid fa-stethoscope"></i>','<i class="fa-solid fa-lock"></i>'][$step] ?></div>
        <div class="card-step-label">Step <?= $step ?> of 3</div>
      </div>
      <div class="card-title"><?= htmlspecialchars($stepTitles[$step]) ?></div>
      <div class="card-sub"><?= htmlspecialchars($stepSubs[$step]) ?></div>
    </div>

    <div class="card-body">
      <?php if ($error): ?><div class="err-box"><i class="fa-solid fa-triangle-exclamation" style="flex-shrink:0;margin-top:1px"></i><span><?= $error ?></span></div><?php endif; ?>

      <form method="POST" action="" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <?php if ($step === 1): ?>
        <!--  STEP 1: Personal & Contact  -->
        <input type="hidden" name="form_action" value="step1">

        <div class="section-head"><i class="fa-solid fa-user"></i> Personal Details</div>
        <div class="grid2">
          <div class="field-group">
            <label class="field-label">First Name <span class="req">*</span></label>
            <input class="field-input" type="text" name="first_name" required autocomplete="given-name"
                   placeholder="John" value="<?= htmlspecialchars($fd['fn'] ?? '') ?>">
          </div>
          <div class="field-group">
            <label class="field-label">Last Name <span class="req">*</span></label>
            <input class="field-input" type="text" name="last_name" required autocomplete="family-name"
                   placeholder="Doe" value="<?= htmlspecialchars($fd['ln'] ?? '') ?>">
          </div>
        </div>

        <div class="grid2">
          <div class="field-group">
            <label class="field-label">Gender <span class="req">*</span></label>
            <select class="field-input select-inp" name="gender">
              <option value="male"   <?= ($fd['gen'] ?? 'male') === 'male'   ? 'selected' : '' ?>>Male</option>
              <option value="female" <?= ($fd['gen'] ?? '')      === 'female' ? 'selected' : '' ?>>Female</option>
              <option value="other"  <?= ($fd['gen'] ?? '')      === 'other'  ? 'selected' : '' ?>>Prefer not to say</option>
            </select>
          </div>
          <div class="field-group">
            <label class="field-label">Date of Birth</label>
            <input class="field-input" type="date" name="dob" autocomplete="bday"
                   max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
                   value="<?= htmlspecialchars($fd['dob'] ?? '') ?>">
          </div>
        </div>

        <div class="section-head"><i class="fa-solid fa-phone"></i> Contact Information</div>
        <div class="field-group">
          <label class="field-label">Email Address <span class="req">*</span></label>
          <input class="field-input" type="email" name="email" required autocomplete="email"
                 placeholder="doctor@example.com" value="<?= htmlspecialchars($fd['em'] ?? '') ?>">
          <div class="field-hint">This will be your login email and used for appointment notifications.</div>
        </div>

        <div class="field-group">
          <label class="field-label">Phone Number <span class="req">*</span></label>
          <input class="field-input" type="tel" name="phone" required autocomplete="tel"
                 placeholder="+254 700 000 000" value="<?= htmlspecialchars($fd['ph'] ?? '') ?>">
          <div class="field-hint">Patients and Planeazzy will contact you on this number. Kenya format preferred.</div>
        </div>

        <div class="section-head"><i class="fa-solid fa-location-dot"></i> Location</div>
        <div class="grid2">
          <div class="field-group">
            <label class="field-label">County <span class="req">*</span></label>
            <select class="field-input select-inp" name="county" required>
              <option value="">— Select County —</option>
              <?php foreach ($counties as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>" <?= ($fd['cty'] ?? '') === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field-group">
            <label class="field-label">City / Town</label>
            <input class="field-input" type="text" name="city" placeholder="e.g., Westlands, Nairobi"
                   value="<?= htmlspecialchars($fd['city'] ?? '') ?>">
          </div>
        </div>

        <div class="field-group">
          <label class="field-label">Clinic / Practice Address</label>
          <input class="field-input" type="text" name="address" placeholder="Building name, street, area"
                 value="<?= htmlspecialchars($fd['addr'] ?? '') ?>">
        </div>

        <?php elseif ($step === 2): ?>
        <!--  STEP 2: Professional Credentials  -->
        <input type="hidden" name="form_action" value="step2">

        <div class="section-head"><i class="fa-solid fa-stethoscope"></i> Medical Specialty</div>
        <div class="field-group">
          <label class="field-label">Primary Specialty <span class="req">*</span></label>
          <select class="field-input select-inp" name="specialty" required>
            <option value="">— Select your specialty —</option>
            <?php foreach ($specialties as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>" <?= ($fd['spec'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="field-hint">Your main area of medical practice that patients will search for.</div>
        </div>

        <div class="section-head"><i class="fa-solid fa-id-card"></i> Registration & Credentials</div>
        <div class="grid2">
          <div class="field-group">
            <label class="field-label">KMPDC Licence No. <span class="req">*</span></label>
            <input class="field-input" type="text" name="kmpdc_licence" required
                   placeholder="e.g., KMPDC/0001/2024" value="<?= htmlspecialchars($fd['lic'] ?? '') ?>">
            <div class="field-hint">Kenya Medical Practitioners & Dentists Council licence. Required for verification.</div>
          </div>
          <div class="field-group">
            <label class="field-label">Years of Experience</label>
            <input class="field-input" type="number" name="years_exp" min="0" max="60" placeholder="0"
                   value="<?= htmlspecialchars($fd['yrs'] ?? 0) ?>">
          </div>
        </div>

        <div class="field-group">
          <label class="field-label">Education / Qualifications</label>
          <input class="field-input" type="text" name="education"
                 placeholder="e.g., MBChB, University of Nairobi · MMed Internal Medicine, UoN"
                 value="<?= htmlspecialchars($fd['edu'] ?? '') ?>">
          <div class="field-hint">Degrees, institutions, and any postgraduate qualifications.</div>
        </div>

        <div class="field-group">
          <label class="field-label">Current Workplace / Hospital Affiliation</label>
          <input class="field-input" type="text" name="workplace"
                 placeholder="e.g., Kenyatta National Hospital, or Private Practice"
                 value="<?= htmlspecialchars($fd['workplace'] ?? '') ?>">
        </div>

        <div class="section-head"><i class="fa-solid fa-calendar-check"></i> Practice & Availability</div>
        <div class="grid2">
          <div class="field-group">
            <label class="field-label">Consultation Fee (KES)</label>
            <input class="field-input" type="number" name="consult_fee" min="0" step="100"
                   placeholder="e.g., 2000" value="<?= htmlspecialchars($fd['fee'] ?? '') ?>">
            <div class="field-hint">Typical outpatient/consultation fee in KES.</div>
          </div>
          <div class="field-group">
            <label class="field-label">&nbsp;</label>
            <div class="checkbox-row">
              <input type="checkbox" name="accepts_tele" id="tele" value="1" <?= !empty($fd['tele']) ? 'checked' : '' ?>>
              <label for="tele">I offer <strong>tele-consultations</strong> (video/phone)</label>
            </div>
            <div class="checkbox-row" style="margin-top:6px">
              <input type="checkbox" name="accepts_walkin" id="walkin" value="1" <?= ($fd['walkin'] ?? 1) ? 'checked' : '' ?>>
              <label for="walkin">I accept <strong>walk-in patients</strong></label>
            </div>
          </div>
        </div>

        <div class="section-head"><i class="fa-solid fa-language"></i> Languages Spoken</div>
        <div class="field-group">
          <div class="langs-grid">
            <?php
            $selLangs = array_map('trim', explode(',', $fd['langs'] ?? 'English,Swahili'));
            foreach ($allLangs as $lang): ?>
            <label class="lang-check">
              <input type="checkbox" name="languages[]" value="<?= htmlspecialchars($lang) ?>"
                     <?= in_array($lang, $selLangs) ? 'checked' : '' ?>>
              <?= htmlspecialchars($lang) ?>
            </label>
            <?php endforeach; ?>
          </div>
          <div class="field-hint" style="margin-top:8px">Select all languages you can use to consult patients.</div>
        </div>

        <div class="section-head"><i class="fa-solid fa-file-medical"></i> Professional Bio</div>
        <div class="field-group">
          <label class="field-label">About You (visible to patients)</label>
          <textarea class="field-input" name="bio" rows="4"
                    placeholder="Brief professional biography that patients will see when searching for doctors on Planeazzy…"><?= htmlspecialchars($fd['bio'] ?? '') ?></textarea>
          <div class="field-hint">Describe your expertise, approach to patient care, and anything patients should know.</div>
        </div>

        <div class="agree-row">
          <input type="checkbox" name="agree" id="agree" value="1">
          <label for="agree">
            I confirm that all the information provided is accurate and truthful. I hold a valid KMPDC licence
            and I agree to Planeazzy's <a href="/terms.php" target="_blank">Terms of Service</a> and
            <a href="/privacy.php" target="_blank">Privacy Policy</a>.
          </label>
        </div>

        <?php elseif ($step === 3): ?>
        <!--  STEP 3: Security  -->
        <input type="hidden" name="form_action" value="step3">

        <div style="background:#f0fdf4;border-radius:12px;padding:16px;margin-bottom:20px;border:1.5px solid #bbf7d0">
          <div style="font-size:.875rem;font-weight:700;color:#065f46;margin-bottom:4px"><i class="fa-solid fa-circle-check" style="color:#16a34a"></i> Almost done, Dr. <?= htmlspecialchars($fd['fn'] ?? '') ?>!</div>
          <div style="font-size:.8125rem;color:#16a34a;line-height:1.5">
            Set a strong password to secure your Planeazzy doctor account.
            After registration, you'll receive a verification code by email and SMS.
          </div>
        </div>

        <div class="field-group">
          <label class="field-label">Password <span class="req">*</span></label>
          <input class="field-input" type="password" name="password" id="pwInput" required
                 autocomplete="new-password" placeholder="Minimum 8 characters"
                 oninput="checkStrength(this.value)">
          <div class="pw-strength"><div class="pw-fill" id="pwFill"></div></div>
          <div class="field-hint" id="pwHint">Use 8+ characters with letters and numbers.</div>
        </div>

        <div class="field-group">
          <label class="field-label">Confirm Password <span class="req">*</span></label>
          <input class="field-input" type="password" name="password2" required
                 autocomplete="new-password" placeholder="Repeat your password"
                 oninput="checkMatch()">
          <div class="field-hint" id="matchHint"></div>
        </div>

        <div style="background:#f8fafc;border-radius:12px;padding:14px;border:1px solid #e2e8f0;font-size:.8125rem;color:#64748b;line-height:1.6;margin-bottom:4px">
          <strong style="color:#374151">After registering:</strong><br>
          • A 6-digit OTP will be sent to <strong><?= htmlspecialchars($fd['em'] ?? '') ?></strong>
          <?php if (!empty($fd['ph'])): ?> and <strong><?= htmlspecialchars($fd['ph']) ?></strong><?php endif; ?><br>
          • Enter the code on the next page to activate your account<br>
        </div>

        <!-- Document Upload Section -->
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0">
          <div style="font-size:.875rem;font-weight:700;color:#374151;margin-bottom:4px;display:flex;align-items:center;gap:7px">
            <i class="fa-solid fa-folder-open" style="color:var(--primary)"></i> Verification Documents
          </div>
          <div style="font-size:.75rem;color:#64748b;margin-bottom:12px;line-height:1.5">
            Upload your credentials for admin review. Documents are encrypted and only used for verification — never shared publicly.
          </div>
          <div id="docUploadList">
            <label class="doc-upload-item" id="docRowKmpdc">
              <div class="doc-upload-icon"><i class="fa-solid fa-file-medical"></i></div>
              <div class="doc-upload-info">
                <div class="doc-upload-name">KMPDC Certificate</div>
                <div class="doc-upload-desc">Kenya Medical Practitioners registration</div>
              </div>
              <span class="doc-upload-status req" id="statusKmpdc">Required</span>
              <span class="doc-upload-action"><i class="fa-solid fa-upload"></i> Upload</span>
              <input type="file" name="doc_kmpdc" accept=".pdf,.jpg,.jpeg,.png" style="display:none" onchange="markDocUploaded('kmpdc',this)">
            </label>
            <label class="doc-upload-item" id="docRowDegree">
              <div class="doc-upload-icon"><i class="fa-solid fa-graduation-cap"></i></div>
              <div class="doc-upload-info">
                <div class="doc-upload-name">Medical Degree</div>
                <div class="doc-upload-desc">MBChB, MBBCh or equivalent</div>
              </div>
              <span class="doc-upload-status req" id="statusDegree">Required</span>
              <span class="doc-upload-action"><i class="fa-solid fa-upload"></i> Upload</span>
              <input type="file" name="doc_degree" accept=".pdf,.jpg,.jpeg,.png" style="display:none" onchange="markDocUploaded('degree',this)">
            </label>
            <label class="doc-upload-item" id="docRowId">
              <div class="doc-upload-icon"><i class="fa-solid fa-id-card"></i></div>
              <div class="doc-upload-info">
                <div class="doc-upload-name">National ID / Passport</div>
                <div class="doc-upload-desc">Government-issued photo ID</div>
              </div>
              <span class="doc-upload-status req" id="statusId">Required</span>
              <span class="doc-upload-action"><i class="fa-solid fa-upload"></i> Upload</span>
              <input type="file" name="doc_id" accept=".pdf,.jpg,.jpeg,.png" style="display:none" onchange="markDocUploaded('id',this)">
            </label>
            <label class="doc-upload-item" id="docRowSpecialist">
              <div class="doc-upload-icon"><i class="fa-solid fa-award"></i></div>
              <div class="doc-upload-info">
                <div class="doc-upload-name">Specialist Certificate</div>
                <div class="doc-upload-desc">Optional — for specialist designation</div>
              </div>
              <span class="doc-upload-status opt" id="statusSpecialist">Optional</span>
              <span class="doc-upload-action"><i class="fa-solid fa-upload"></i> Upload</span>
              <input type="file" name="doc_specialist" accept=".pdf,.jpg,.jpeg,.png" style="display:none" onchange="markDocUploaded('specialist',this)">
            </label>
          </div>
          <div class="doc-uploaded-list" id="uploadedDocList"></div>
        </div>
        <script>
        const _uploadedDocs={};
        function markDocUploaded(key,input){
          const file=input.files[0]; if(!file) return;
          if(file.size>5*1024*1024){alert('File too large. Max 5 MB.');input.value='';return;}
          _uploadedDocs[key]=file;
          const row=document.getElementById('docRow'+key.charAt(0).toUpperCase()+key.slice(1));
          if(row) row.classList.add('uploaded');
          const st=document.getElementById('status'+key.charAt(0).toUpperCase()+key.slice(1));
          if(st){st.textContent='Uploaded';st.className='doc-upload-status done';}
          renderDocList();
        }
        function renderDocList(){
          const list=document.getElementById('uploadedDocList');
          list.innerHTML=Object.entries(_uploadedDocs).map(([k,f])=>`
            <div class="doc-tag">
              <i class="fa-solid fa-file-circle-check"></i>
              <span class="doc-tag-name">${f.name}</span>
              <span class="doc-tag-size">${(f.size/1024).toFixed(0)} KB</span>
            </div>`).join('');
        }
        </script>

        <script>
        function checkStrength(v) {
          const fill = document.getElementById('pwFill');
          const hint = document.getElementById('pwHint');
          let score = 0;
          if (v.length >= 8)  score++;
          if (v.length >= 12) score++;
          if (/[A-Z]/.test(v)) score++;
          if (/[0-9]/.test(v)) score++;
          if (/[^A-Za-z0-9]/.test(v)) score++;
          const w = [0,20,40,65,80,100][score];
          const colors = ['#e5e7eb','#ef4444','#f59e0b','#3b82f6','#22c55e','#059669'];
          const labels = ['','Too short','Weak','Fair','Good','Strong'];
          fill.style.width = w + '%';
          fill.style.background = colors[score];
          hint.textContent = labels[score] || '';
          hint.style.color = colors[score];
        }
        function checkMatch() {
          const p1 = document.getElementById('pwInput').value;
          const p2 = event.target.value;
          const hint = document.getElementById('matchHint');
          if (!p2) { hint.textContent = ''; return; }
          hint.innerHTML = p1 === p2 
    ? '<i class="fa-solid fa-circle-check" style="color:#059669"></i> Passwords match' 
    : '<i class="fa-solid fa-circle-xmark" style="color:#dc2626"></i> Passwords do not match';
          hint.style.color = p1 === p2 ? '#059669' : '#ef4444';
        }
        </script>
        <?php endif; ?>

        <!-- Buttons -->
        <div class="btn-row">
          <?php if ($step > 1): ?>
          <button type="submit" name="form_action" value="back" class="btn btn-ghost" style="max-width:120px">&larr; Back</button>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary">
            <?= $step < 3 ? 'Continue &rarr;' : 'Create My Doctor Account &rarr;' ?>
          </button>
        </div>
      </form>

      <div class="footer-link">
        Already have an account? <a href="/doctors/onboarding/login.php">Sign in &rarr;</a>
      </div>
    </div><!-- /card-body -->
  </div><!-- /card -->

  <!-- Bottom links -->
  <div style="text-align:center;margin-top:20px;font-size:.75rem;color:#9ca3af">
    <a href="/hospital/onboarding/signup.php" style="color:#9ca3af;text-decoration:none">Register a Hospital instead &rarr;</a>
    &nbsp;·&nbsp;
    <a href="/" style="color:#9ca3af;text-decoration:none">Back to Planeazzy &rarr;</a>
  </div>

</div><!-- /container -->
<script>
// Auto-join language checkboxes into a comma-separated hidden value before submit
document.querySelector('form')?.addEventListener('submit', function(e) {
  const checks = this.querySelectorAll('input[name="languages[]"]:checked');
  if (checks.length) {
    const vals = [...checks].map(c => c.value).join(', ');
    // Remove individual checkboxes from submission and inject single field
    const hi = document.createElement('input');
    hi.type = 'hidden'; hi.name = 'languages'; hi.value = vals;
    this.appendChild(hi);
    checks.forEach(c => c.disabled = true);
  }
});
</script>
</body>
</html>
