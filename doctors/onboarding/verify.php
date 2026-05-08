<?php
/**
 * Planeazzy — Doctor Email Verification
 * /doctors/onboarding/verify.php
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
require_once dirname(__DIR__, 2) . '/services/Mailer.php';
Security::startSession();

if (!empty($_SESSION['doctor_id']) && !empty($_SESSION['is_doctor'])) {
    header('Location: /doctors/dashboard.php'); exit;
}

$csrf    = Security::csrfToken();
$docId   = (int)($_SESSION['doctor_otp_id']    ?? 0);
$email   = $_SESSION['doctor_otp_email'] ?? '';
$reason  = $_GET['reason'] ?? '';
$error   = '';
$success = '';

// Redirect if no session
if (!$docId || !$email) {
    header('Location: /doctors/onboarding/register.php'); exit;
}

/*  Resend OTP  */
/*  Resend OTP  */
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    try {
        $db  = Database::getInstance();
        $doc = $db->fetchOne('SELECT first_name, last_name, phone FROM doctors WHERE id=:id', [':id' => $docId]);
        
        if ($doc) {
            $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $otpHash = hash('sha256', $otp);
            
            // We simplify the query to ONLY use columns we know exist based on your register.php
            $db->query(
                'UPDATE doctors SET email_otp_hash=:h, email_otp_at=:at WHERE id=:id',
                [
                    ':h'  => $otpHash, 
                    ':at' => date('Y-m-d H:i:s'), 
                    ':id' => $docId
                ]
            );

            $name = trim($doc['first_name'] . ' ' . $doc['last_name']);

            // Email OTP
            $sent = false;
            try { 
                // Using the specific Doctor method we found in your Mailer.php
                $sent = Mailer::sendDoctorOtp($email, $name, $otp); 
            } catch (Exception $me) {
                error_log("Mailer Error: " . $me->getMessage());
            }

            // SMS OTP
            if (!empty($doc['phone'])) {
                try {
                    require_once dirname(__DIR__, 2) . '/services/SmsService.php';
                    SmsService::sendOtp($doc['phone'], $name, $otp);
                } catch (Exception $se) {}
            }

            // Always write to log
            $logDir = defined('LOG_DIR') ? LOG_DIR : dirname(__DIR__, 2) . '/logs/';
            if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
            $logLine = date('[Y-m-d H:i:s]') . " OTP-RESEND [$email] ($name) ID:$docId OTP:$otp sent:" . ($sent ? 'yes' : 'no') . "\n";
            @file_put_contents($logDir . 'otp_codes.txt', $logLine, FILE_APPEND | LOCK_EX);

            $success = 'A new verification code has been sent to <strong>' . htmlspecialchars($email) . '</strong>';
        }
    } catch (Exception $e) {
        // This is where you were getting the "Failed to resend code" error.
        // Temporarily change the line below to see the REAL error if it still fails:
        // die($e->getMessage()); 
        $error = 'Failed to resend code. Please try again.';
    }
}

/*  POST: verify OTP  */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please refresh the page.';
    } else {
        // Collect OTP from either individual digit inputs or single field
        $digits = '';
        for ($i = 1; $i <= 6; $i++) {
            $digits .= preg_replace('/[^0-9]/', '', $_POST['d' . $i] ?? '');
        }
        if (!$digits) {
            $digits = preg_replace('/[^0-9]/', '', $_POST['otp_full'] ?? '');
        }

        if (strlen($digits) !== 6) {
            $error = 'Please enter the complete 6-digit code.';
        } else {
            try {
                $db  = Database::getInstance();
                $doc = $db->fetchOne(
                    'SELECT id, email_otp_hash, email_otp_at FROM doctors WHERE id=:id',
                    [':id' => $docId]
                );

                if (!$doc) {
                    $error = 'Account not found. Please register again.';
                } elseif (hash('sha256', $digits) !== $doc['email_otp_hash']) {
                    $error = 'Incorrect code. Please check and try again, or <a href="?resend=1" style="color:#059669;font-weight:700">request a new code</a>.';
                } elseif (strtotime($doc['email_otp_at']) + (OTP_EXPIRY_MINUTES * 60) < time()) {
                    $error = 'This code has expired. <a href="?resend=1" style="color:#059669;font-weight:700">Request a new code →</a>';
                } else {
                    // Activate account
                    $db->query(
                        'UPDATE doctors SET email_verified=1, is_active=1, status="active", email_otp_hash=NULL WHERE id=:id',
                        [':id' => $docId]
                    );

                    // Log the user in immediately
                    unset($_SESSION['doctor_otp_id'], $_SESSION['doctor_otp_email']);
                    $_SESSION['doctor_id'] = $docId;
                    $_SESSION['is_doctor'] = true;

                    // Send welcome SMS
                    $docFull = $db->fetchOne('SELECT first_name, last_name, phone FROM doctors WHERE id=:id', [':id' => $docId]);
                    if ($docFull && !empty($docFull['phone'])) {
                        try {
                            require_once dirname(__DIR__, 2) . '/services/SmsService.php';
                            $name = trim($docFull['first_name'] . ' ' . $docFull['last_name']);
                            SmsService::send($docFull['phone'],
                                "Welcome to Planeazzy, Dr. $name! Your account is now active. Patients can find you in search. Manage your appointments at planeazzy.co.ke/doctors",
                                'doctor_welcome');
                        } catch (Exception $se) {}
                    }

                    header('Location: /doctors/dashboard.php?welcome=1'); exit;
                }
            } catch (Exception $e) {
                $error = 'Verification failed. Please try again.';
                error_log('[Doctor Verify] ' . $e->getMessage());
            }
        }
    }
}

$maskedEmail = '';
if ($email) {
    $parts = explode('@', $email);
    $maskedEmail = substr($parts[0], 0, 2) . str_repeat('*', max(2, strlen($parts[0]) - 2)) . '@' . ($parts[1] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title>Verify Your Email — Planeazzy Doctor</title>
  <link rel="icon" type="image/png" href="/assets/images/favicon.png">
  <link rel="apple-touch-icon" href="/assets/images/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;background:#f0fdf4;display:flex;align-items:center;justify-content:center;padding:24px 16px}
.card{background:#fff;border-radius:24px;box-shadow:0 4px 32px rgba(0,0,0,.09);width:100%;max-width:440px;overflow:hidden}
.card-top{background:linear-gradient(135deg,#059669,#0d9488);padding:32px 32px 28px;text-align:center}
.card-top .icon{width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 14px}
.card-top h1{font-size:1.375rem;font-weight:800;color:#fff;margin-bottom:6px;letter-spacing:-.03em}
.card-top p{font-size:.875rem;color:rgba(255,255,255,.8);line-height:1.5}
.card-top .em{color:#fff;font-weight:700;word-break:break-all}
.card-body{padding:28px 32px 32px}
.err-box{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:12px;padding:12px 15px;font-size:.8125rem;color:#991b1b;margin-bottom:18px;line-height:1.5;display:flex;align-items:flex-start;gap:8px}
.ok-box{background:#f0fdf4;border:1.5px solid #86efac;border-radius:12px;padding:12px 15px;font-size:.8125rem;color:#166534;margin-bottom:18px;line-height:1.5;display:flex;align-items:flex-start;gap:8px}
.warn-box{background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:12px 15px;font-size:.8125rem;color:#92400e;margin-bottom:18px;line-height:1.5}
.otp-row{display:flex;gap:8px;justify-content:center;margin:20px 0;max-width:340px;margin-left:auto;margin-right:auto;flex-wrap:nowrap;overflow:hidden}.otp-inp{width:44px;max-width:48px;flex:0 0 44px;height:50px;border:2.5px solid #d1d5db;border-radius:10px;text-align:center;font-size:1.25rem;font-weight:800;outline:none;transition:all .2s;font-family:inherit;color:#111827;background:#f9fafb}
.otp-inp:focus{border-color:#059669;background:#fff;box-shadow:0 0 0 4px rgba(5,150,105,.12)}
.otp-inp.filled{border-color:#059669;background:#f0fdf4}
.btn-verify{width:100%;padding:13px;background:linear-gradient(135deg,#059669,#0d9488);color:#fff;border:none;border-radius:12px;font-family:inherit;font-size:.9375rem;font-weight:700;cursor:pointer;transition:all .2s;letter-spacing:-.01em}
.btn-verify:hover{opacity:.92;transform:translateY(-1px)}
.resend-row{text-align:center;margin-top:16px;font-size:.8125rem;color:#6b7280}
.resend-row a{color:#059669;font-weight:700;text-decoration:none}
.resend-row a:hover{text-decoration:underline}
.log-hint{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;margin-top:16px;font-size:.75rem;color:#64748b;line-height:1.5}
.log-hint strong{color:#374151}
.log-hint code{background:#e2e8f0;padding:1px 5px;border-radius:4px;font-family:monospace}
</style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <div class="icon"><i class="fa-solid fa-envelope" style="font-size:26px;color:#fff"></i></div>
    <h1>Verify Your Email</h1>
    <p>We've sent a 6-digit code to<br><span class="em"><?= htmlspecialchars($maskedEmail) ?></span>
    <?php if ($reason === 'unverified'): ?>
    <br><small style="color:rgba(255,255,255,.7);font-size:.75rem">Your account needs to be verified before you can sign in.</small>
    <?php endif; ?>
    </p>
  </div>

  <div class="card-body">
    <?php if ($error): ?><div class="err-box"><i class="fa-solid fa-triangle-exclamation" style="flex-shrink:0;margin-top:1px"></i><span><?= $error ?></span></div><?php endif; ?>
    <?php if ($success): ?><div class="ok-box"><i class="fa-solid fa-circle-check" style="flex-shrink:0;margin-top:1px"></i><span><?= $success ?></span></div><?php endif; ?>
    <?php if (!empty($reason) && !$success && !$error): ?>
    <div class="warn-box">⏳ Please check your email and enter the verification code below to activate your account.</div>
    <?php endif; ?>

    <form method="POST" action="" id="verifyForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="otp_full" id="otpFull">    <div style="text-align:center;font-size:.8125rem;color:#6b7280;margin-bottom:4px">Enter 6-digit verification code</div>

      <div class="otp-row">
        <?php for ($i = 1; $i <= 6; $i++): ?>
        <input type="text" class="otp-inp" id="d<?= $i ?>" name="d<?= $i ?>"
               maxlength="1" inputmode="numeric" pattern="[0-9]"
               autocomplete="<?= $i === 1 ? 'one-time-code' : 'off' ?>">
        <?php endfor; ?>
      </div>

      <button type="submit" class="btn-verify" id="verifyBtn">Verify &amp; Activate Account →</button>
    </form>

    <div class="resend-row">
      Didn't receive it? &nbsp;
      <a href="?resend=1">Resend code</a>
      &nbsp;·&nbsp;
      <a href="/doctors/onboarding/register.php" style="color:#9ca3af">Register again</a>
    </div>

    <div class="log-hint">
      <strong><i class="fa-solid fa-clipboard-list" style="margin-right:4px"></i>Can't receive email or SMS?</strong><br>
      <small>Look for an entry with your email address <strong><?= htmlspecialchars($maskedEmail) ?></strong> and the 6-digit code.</small>
    </div>

    <div style="text-align:center;margin-top:14px;font-size:.75rem;color:#9ca3af">
      Code expires in <?= OTP_EXPIRY_MINUTES ?> minutes ·
      <a href="/doctors/onboarding/login.php" style="color:#9ca3af">Back to login</a>
    </div>
  </div>
</div>

<script>
const inputs = document.querySelectorAll('.otp-inp');

// Auto-advance on input
inputs.forEach((inp, i) => {
  inp.addEventListener('input', e => {
    const v = e.target.value.replace(/\D/g, '');
    e.target.value = v;
    if (v) {
      e.target.classList.add('filled');
      if (i < inputs.length - 1) inputs[i + 1].focus();
    } else {
      e.target.classList.remove('filled');
    }
    updateFull();
  });
  inp.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !inp.value && i > 0) {
      inputs[i - 1].focus();
      inputs[i - 1].classList.remove('filled');
    }
    if (e.key === 'ArrowLeft'  && i > 0) inputs[i - 1].focus();
    if (e.key === 'ArrowRight' && i < inputs.length - 1) inputs[i + 1].focus();
  });
  inp.addEventListener('paste', e => {
    const txt = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
    if (txt) {
      inputs.forEach((x, j) => {
        x.value = txt[j] || '';
        x.classList.toggle('filled', !!x.value);
      });
      inputs[Math.min(txt.length, inputs.length - 1)].focus();
      updateFull();
    }
    e.preventDefault();
  });
});

function updateFull() {
  document.getElementById('otpFull').value = [...inputs].map(x => x.value).join('');
}

// Focus first on load
window.addEventListener('load', () => inputs[0]?.focus());

// Auto-submit when all 6 filled
function checkAutoSubmit() {
  if ([...inputs].every(x => x.value)) {
    setTimeout(() => document.getElementById('verifyForm').submit(), 300);
  }
}
inputs.forEach(inp => inp.addEventListener('input', checkAutoSubmit));
</script>
</body>
</html>
