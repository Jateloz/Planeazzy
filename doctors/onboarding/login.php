<?php
/**
 * Planeazzy — Doctor Portal Login
 * /doctors/onboarding/login.php
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
Security::startSession();

if (!empty($_SESSION['doctor_id']) && !empty($_SESSION['is_doctor'])) {
    header('Location: /doctors/dashboard.php'); exit;
}

$csrf  = Security::csrfToken();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = trim($_POST['csrf_token'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!Security::verifyCsrf($token)) {
        $error = 'Security token invalid. Please refresh the page.';
    } elseif (!$email || !$password) {
        $error = 'Email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $db  = Database::getInstance();
            $doc = $db->fetchOne('SELECT * FROM doctors WHERE email=:e LIMIT 1', [':e' => $email]);

            if (!$doc || !password_verify($password, $doc['password_hash'])) {
                $error = 'Invalid email or password.';
            } elseif (!$doc['email_verified']) {
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $db->query('UPDATE doctors SET email_otp_hash=:h,email_otp_at=NOW() WHERE id=:id',
                    [':h' => hash('sha256', $otp), ':id' => $doc['id']]);
                require_once dirname(__DIR__, 2) . '/services/Mailer.php';
                $name = trim($doc['first_name'] . ' ' . $doc['last_name']);
                Mailer::sendOtp($email, $name, $otp);
                $logDir = defined('LOG_DIR') ? LOG_DIR : dirname(__DIR__, 2) . '/logs/';
                if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
                @file_put_contents($logDir . 'otp_codes.txt',
                    date('[Y-m-d H:i:s]') . " OTP-RESEND [$email] ($name): $otp\n", FILE_APPEND | LOCK_EX);
                $_SESSION['doctor_otp_id'] = $doc['id'];
                $_SESSION['doctor_otp_email'] = $email;
                header('Location: /doctors/onboarding/verify.php?reason=unverified'); exit;
            } elseif ($doc['status'] === 'suspended') {
                $error = 'Your account has been suspended. Contact info@planeazzy.com';
            } else {
                $_SESSION['doctor_id']  = (int)$doc['id'];
                $_SESSION['is_doctor']  = true;
                $db->query('UPDATE doctors SET last_login=NOW() WHERE id=:id', [':id' => $doc['id']]);
                header('Location: /doctors/dashboard.php'); exit;
            }
        } catch (Exception $e) {
            $error = 'Service temporarily unavailable. Please try again.';
            error_log('[Doctor Login] ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title>Doctor Login — Planeazzy</title>
  <link rel="icon" type="image/png" href="/assets/images/favicon.png">
  <link rel="apple-touch-icon" href="/assets/images/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;background:#f0fdf4}
.left{width:420px;flex-shrink:0;background:linear-gradient(160deg,#059669 0%,#0d9488 50%,#0369a1 100%);display:flex;flex-direction:column;padding:48px 40px;position:relative;overflow:hidden}
.left::before{content:'';position:absolute;top:-80px;right:-80px;width:300px;height:300px;border-radius:50%;background:rgba(255,255,255,.06);pointer-events:none}
.left::after{content:'';position:absolute;bottom:-60px;left:-60px;width:250px;height:250px;border-radius:50%;background:rgba(255,255,255,.04);pointer-events:none}
.left-logo{display:flex;align-items:center;gap:10px;margin-bottom:auto}
.left-logo img{height:32px;filter:brightness(0) invert(1)}
.left-logo-text{font-size:1.125rem;font-weight:900;color:#fff;letter-spacing:-.03em}
.left-center{position:relative;z-index:1}
.left-icon{width:64px;height:64px;border-radius:18px;background:rgba(255,255,255,.12);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;font-size:26px;margin-bottom:20px;border:1.5px solid rgba(255,255,255,.2)}
.left h1{font-size:2rem;font-weight:900;color:#fff;letter-spacing:-.04em;line-height:1.1;margin-bottom:10px}
.left p{font-size:.9375rem;color:rgba(255,255,255,.75);line-height:1.6;margin-bottom:28px}
.feature{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.feature-dot{width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.feature span{font-size:.8125rem;color:rgba(255,255,255,.85);font-weight:500}
.left-footer{margin-top:auto;padding-top:24px;border-top:1px solid rgba(255,255,255,.12)}
.left-footer a{font-size:.75rem;color:rgba(255,255,255,.6);text-decoration:none;margin-right:12px}
.left-footer a:hover{color:#fff}
.right{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px}
.form-card{background:#fff;border-radius:24px;box-shadow:0 4px 32px rgba(0,0,0,.08);width:100%;max-width:420px;padding:40px}
.form-title{font-size:1.5rem;font-weight:800;color:#111827;letter-spacing:-.04em;margin-bottom:4px}
.form-sub{font-size:.875rem;color:#6b7280;margin-bottom:28px}
.err-box{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:12px;padding:12px 15px;font-size:.8125rem;color:#991b1b;margin-bottom:18px;line-height:1.5;display:flex;align-items:flex-start;gap:8px}
.field-group{margin-bottom:16px}
.field-label{font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#374151;margin-bottom:6px;display:block}
.field-input{width:100%;padding:11px 14px;background:#f9fafb;border:2px solid #e5e7eb;border-radius:11px;font-family:inherit;font-size:.9375rem;color:#111827;outline:none;transition:all .2s}
.field-input:focus{background:#fff;border-color:#059669;box-shadow:0 0 0 4px rgba(5,150,105,.1)}
.btn-primary{width:100%;padding:13px;background:linear-gradient(135deg,#059669,#0d9488);color:#fff;border:none;border-radius:12px;font-family:inherit;font-size:.9375rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:4px}
.btn-primary:hover{opacity:.92;transform:translateY(-1px)}
.divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:#9ca3af;font-size:.75rem}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#e5e7eb}
.links-row{display:flex;justify-content:space-between;align-items:center;margin-top:18px;font-size:.8125rem}
.links-row a{color:#059669;font-weight:600;text-decoration:none}
.links-row a:hover{}
.portal-links{display:flex;gap:10px;margin-top:14px;justify-content:center;flex-wrap:wrap}
.portal-link{font-size:.75rem;color:#9ca3af;text-decoration:none;padding:5px 12px;border-radius:8px;border:1px solid #e5e7eb;transition:all .15s}
.portal-link:hover{border-color:#059669;color:#059669}
@media(max-width:768px){.left{display:none}.right{background:#fff}.form-card{box-shadow:none}}
</style>
</head>
<body>
<div class="left">
  <div class="left-logo">
    <img src="/assets/images/favicon1.png" alt="Planeazzy" onerror="this.style.display='none'">
  </div>
  <div class="left-center">
    <div class="left-icon"><i class="fa-solid fa-user-doctor"></i></div>
    <h1>Doctor Portal</h1>
    <p>Your professional platform for managing patients, appointments, and your practice across Kenya.</p>
    <div class="feature"><div class="feature-dot"><i class="fa-regular fa-calendar"></i></div><span>Manage appointments &amp; availability</span></div>
    <div class="feature"><div class="feature-dot"><i class="fa-solid fa-users"></i></div><span>Track your patient records</span></div>
    <div class="feature"><div class="feature-dot"><i class="fa-solid fa-mobile-screen"></i></div><span>SMS &amp; email alerts on every booking</span></div>
    <div class="feature"><div class="feature-dot"><i class="fa-solid fa-chart-bar"></i></div><span>Analytics &amp; practice insights</span></div>
    <div class="feature"><div class="feature-dot"><i class="fa-solid fa-magnifying-glass"></i></div><span>Listed in the Planeazzy search directory</span></div>
  </div>
  <div class="left-footer">
    <a href="/privacy.php">Privacy</a>
    <a href="/terms.php">Terms</a>
    <a href="mailto:support@planeazzy.co.ke">Support</a>
  </div>
</div>
<div class="right">
  <div class="form-card">
    <div class="form-title">Welcome back, Doctor</div>
    <div class="form-sub">Sign in to your Planeazzy doctor account</div>

    <?php if ($error): ?>
    <div class="err-box"><i class="fa-solid fa-circle-exclamation" style="flex-shrink:0;margin-top:1px"></i><span><?= $error ?></span></div>
    <?php endif; ?>

    <form method="POST" action="" novalidate autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <div class="field-group">
        <label class="field-label" for="email">Email Address</label>
        <input id="email" class="field-input" type="email" name="email" required
               autocomplete="email" placeholder="doctor@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="field-group">
        <label class="field-label" for="password">Password</label>
        <input id="password" class="field-input" type="password" name="password"
               required autocomplete="current-password" placeholder="Your password">
      </div>
      <button class="btn-primary" type="submit">Sign In to Doctor Portal →</button>
    </form>

    <div class="divider">new to planeazzy?</div>
    <div class="links-row">
      <a href="/doctors/onboarding/register.php">Create Doctor Account →</a>
      <a href="/doctors/onboarding/verify.php" style="color:#6b7280;font-weight:500">Verify Email</a>
    </div>
    <div class="portal-links">
      <a href="/hospital/onboarding/login.php" class="portal-link"><i class="fa-solid fa-hospital"></i> Hospital Portal</a>
      <a href="/patients/login.php" class="portal-link"><i class="fa-solid fa-user"></i> Patient Login</a>
    </div>
  </div>
</div>
</body>
</html>
