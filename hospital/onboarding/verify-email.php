<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
require_once dirname(__DIR__, 2) . '/services/HospitalMailer.php';
Security::startSession();

date_default_timezone_set('Africa/Nairobi');

if (empty($_SESSION['hospital_id'])) {
    header('Location: /hospital/onboarding/signup.php'); exit;
}

$hid   = (int)$_SESSION['hospital_id'];
$email = $_SESSION['hospital_email'] ?? '';
$name  = $_SESSION['hospital_name']  ?? 'Admin';
$csrf  = Security::csrfToken();
$error = $success = '';
$db    = Database::getInstance();

$hosp = $db->fetchOne('SELECT * FROM hospital_providers WHERE id=:id', [':id' => $hid]);
if (!$hosp) { header('Location: /hospital/onboarding/signup.php'); exit; }
if (!empty($hosp['email_verified'])) {
    $_SESSION['hospital_auth'] = true;
    session_write_close();
    header('Location: /hospital/onboarding/select-type.php'); exit;
}

/*
 * OTP helper — always uses bcrypt so it is consistent with whatever
 * signup.php stored. Logs plaintext to otp_codes.txt for dev debugging.
 */
function h_send_otp(int $hid, string $email, string $name, $db): bool {
    $plain = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Ensure this matches signup.php
    $hash = password_hash($plain, PASSWORD_BCRYPT);

    $db->query(
        'UPDATE hospital_providers SET email_otp_hash=:h, email_otp_at=NOW() WHERE id=:id',
        [':h' => $hash, ':id' => $hid]
    );

    $sent = false;
    try {
        $sent = HospitalMailer::sendOtp($email, $name, $plain, $hid);
    } catch (Exception $e) {
        error_log('HospitalMailer::sendOtp error: ' . $e->getMessage());
    }

    $logDir = defined('LOG_DIR') ? LOG_DIR : dirname(__DIR__, 2) . '/logs/';
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    $logLine = date('[Y-m-d H:i:s]') . " HOSPITAL-OTP [$email] ($name) ID:$hid OTP:$plain sent:" . ($sent ? 'yes' : 'no') . "\n";
    @file_put_contents($logDir . 'otp_codes.txt', $logLine, FILE_APPEND | LOCK_EX);

    return $sent;
}

/*
 * FIX: Only auto-send if NO hash exists yet in the DB.
 * If signup.php already stored a bcrypt hash, trust it — do NOT overwrite.
 * Previously this would always fire, replacing the emailed code in the DB
 * with a new one the user never received, causing "Incorrect code" every time.
 */
if (empty($_SESSION['otp_sent_at']) || empty($hosp['email_otp_hash'])) {
    $sent = h_send_otp($hid, $email, $name, $db);
    $_SESSION['otp_sent_at'] = time(); // Mark that we sent it
    
    $success = $sent
        ? 'A 6-digit verification code has been sent to ' . htmlspecialchars($email) . '.'
        : 'Could not send email. Please use "Resend" below.';
    
    // Refresh data
    $hosp = $db->fetchOne('SELECT * FROM hospital_providers WHERE id=:id', [':id' => $hid]);
} else {
    // They probably just refreshed the page, don't send again
    $success = 'A verification code was sent to ' . htmlspecialchars($email) . '. Check your inbox.';
}


/*  Handle POST  */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? 'verify';
    $tok = trim($_POST['csrf_token'] ?? '');

    if (!Security::verifyCsrf($tok)) {
        $error   = 'Security error. Please refresh and try again.';
        $success = '';
    } elseif ($act === 'resend') {
        $sent    = h_send_otp($hid, $email, $name, $db);
        $success = $sent
            ? 'A new code has been sent to ' . htmlspecialchars($email) . '.'
            : 'Failed to send. Please try again shortly.';
        $error   = '';
        $hosp    = $db->fetchOne('SELECT * FROM hospital_providers WHERE id=:id', [':id' => $hid]);
        $csrf    = Security::csrfToken();
    } else {
        $entered = implode('', array_map(fn($k) => trim($_POST["d$k"] ?? ''), range(1, 6)));

        // Always fetch fresh from DB
        $fresh = $db->fetchOne(
            'SELECT email_otp_hash, email_otp_at FROM hospital_providers WHERE id=:id',
            [':id' => $hid]
        );

        if (!preg_match('/^\d{6}$/', $entered)) {
            $error = 'Please enter all 6 digits.';
        } elseif (empty($fresh['email_otp_hash'])) {
            $error = 'No code found. Please request a new one.';
        } else {
            // FIX: password_verify (bcrypt) — consistent across signup.php and h_send_otp()
            $hashMatch = password_verify($entered, $fresh['email_otp_hash']);

            // Timezone-safe expiry
            $sentTime = new DateTime($fresh['email_otp_at'], new DateTimeZone('Africa/Nairobi'));
            $nowTime  = new DateTime('now',                   new DateTimeZone('Africa/Nairobi'));
            $diffSecs = $nowTime->getTimestamp() - $sentTime->getTimestamp();
            $expired  = $diffSecs > (OTP_EXPIRY_MINUTES * 60);

            if (!$hashMatch) {
                $error = 'Incorrect code. Please check your email and try again.';
            } elseif ($expired) {
                $mins  = (int)round($diffSecs / 60);
                $error = "Code expired {$mins} minute" . ($mins === 1 ? '' : 's') . " ago. Please request a new one.";
            } else {
                $db->query(
                    'UPDATE hospital_providers
                        SET email_verified=1, email_otp_hash=NULL, email_otp_at=NULL, onboarding_step=4
                      WHERE id=:id',
                    [':id' => $hid]
                );

                try {
                    HospitalMailer::sendWelcome($email, $name, $hosp['facility_type'] ?? 'hospital', $hid);
                } catch (Exception $e) {
                    error_log('sendWelcome error: ' . $e->getMessage());
                }

                $_SESSION['hospital_auth']   = true;
                $_SESSION['email_verified']  = true;
                $_SESSION['onboarding_step'] = 4;

                session_write_close();
                header('Location: /hospital/onboarding/select-type.php'); exit;
            }
        }
    }
}

$cpStep = 4; $cpTitle = 'Verify Email';
include __DIR__ . '/_head.php';
?>
<style>
.vfy-wrap{min-height:calc(100vh - 56px);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;background:linear-gradient(135deg,#f7f9fb 0%,#f2f4f6 100%)}
.vfy-card{background:var(--cp-surface-container-lowest);border-radius:24px;padding:40px 32px;max-width:440px;width:100%;box-shadow:0 24px 64px rgba(25,28,30,.08);border:1px solid rgba(193,198,213,.12);overflow:hidden}
@media(max-width:520px){.vfy-card{padding:24px 16px;border-radius:18px}}
.cp-otp-row{display:flex;gap:8px;justify-content:center;width:100%;max-width:340px;margin:0 auto;overflow:hidden}
.cp-otp-row input{width:44px;height:50px;max-width:48px;flex:0 0 44px;text-align:center;font-size:1.25rem;font-weight:800;border:2px solid #e5e7eb;border-radius:10px;outline:none;transition:all .2s;background:#f8fafc;font-family:inherit}
.cp-otp-row input:focus{border-color:var(--cp-primary);background:#fff;box-shadow:0 0 0 3px rgba(0,90,180,.1)}
.cp-otp-row input.otp-filled{border-color:var(--cp-primary);background:#fff}
.cp-otp-row input.otp-error{border-color:#ba1a1a;background:#fff8f8}
</style>

<!-- Progress rail -->
<div class="cp-progress-rail"><div class="cp-progress-fill" style="width:50%"></div></div>

<header class="cp-topnav">
  <a href="/hospital/onboarding/join.php" class="cp-topnav-brand">Clinical Precision</a>
  <div class="cp-topnav-actions">
    <button class="cp-lang-btn" id="langToggle">
      <span class="material-symbols-outlined" style="font-size:15px">language</span>
      <span id="langLabel">SW</span>
    </button>
  </div>
</header>

<div class="vfy-wrap">
  <div class="vfy-card">

    <div style="width:60px;height:60px;border-radius:16px;background:rgba(0,90,180,.08);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;border:1px solid rgba(0,90,180,.15)">
      <span class="material-symbols-outlined msf" style="font-size:28px;color:var(--cp-primary)">mark_email_unread</span>
    </div>

    <h1 class="cp-h2" style="text-align:center;margin-bottom:10px"
        data-en="Check your email" data-sw="Angalia barua pepe yako">Check your email</h1>

    <p class="cp-body" style="text-align:center;margin-bottom:28px">
      <span data-en="We sent a 6-digit code to" data-sw="Tulituma nambari ya tarakimu 6 kwa">We sent a 6-digit code to</span>
      <strong style="color:var(--cp-on-surface)"><?= htmlspecialchars($email) ?></strong>.
      <span data-en="It expires in" data-sw="Inaisha baada ya">It expires in</span>
      <strong><?= OTP_EXPIRY_MINUTES ?></strong>
      <span data-en="minutes." data-sw="dakika.">minutes.</span>
    </p>

    <?php if ($error): ?>
    <div style="background:rgba(186,26,26,.08);border:1px solid rgba(186,26,26,.2);border-radius:10px;padding:12px 16px;display:flex;gap:10px;align-items:flex-start;margin-bottom:20px;font-size:.875rem;color:#6b0000" role="alert">
      <span class="material-symbols-outlined" style="font-size:18px;flex-shrink:0">error</span>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div style="background:rgba(0,106,106,.07);border:1px solid rgba(0,106,106,.2);border-radius:10px;padding:12px 16px;display:flex;gap:10px;align-items:flex-start;margin-bottom:20px;font-size:.875rem;color:#014b4b" role="status">
      <span class="material-symbols-outlined" style="font-size:18px;flex-shrink:0">check_circle</span>
      <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="otpForm" autocomplete="off" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="verify">

      <div class="cp-otp-row" id="otpGrid" style="margin-bottom:28px"
           role="group" aria-label="6-digit verification code">
        <?php for ($i = 1; $i <= 6; $i++): ?>
        <input class="cp-otp-input"
               type="text" inputmode="numeric"
               name="d<?= $i ?>" id="otp<?= $i ?>"
               maxlength="1" autocomplete="off" pattern="[0-9]"
               aria-label="Digit <?= $i ?>"
               <?= $i === 1 ? 'autofocus' : '' ?>>
        <?php endfor; ?>
      </div>

      <button type="submit" id="verifyBtn"
              class="cp-btn cp-btn-primary cp-btn-full cp-btn-round"
              style="font-size:.9375rem;padding:14px" disabled>
        <span class="material-symbols-outlined">verified_user</span>
        <span data-en="Verify Email" data-sw="Thibitisha Barua Pepe">Verify Email</span>
      </button>
    </form>

    <form method="POST" id="resendForm" style="margin-top:16px;text-align:center">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="resend">
      <span style="font-size:.875rem;color:var(--cp-on-surface-var)"
            data-en="Didn't receive it?" data-sw="Hukupokea?">Didn't receive it?</span>
      <button type="submit" id="resendBtn"
              style="background:none;border:none;cursor:pointer;color:var(--cp-primary);font-weight:700;font-family:inherit;font-size:.875rem;text-decoration:underline;margin-left:4px"
              data-en="Resend code" data-sw="Tuma tena">Resend code</button>
      <span id="resendCooldown" style="display:none;font-size:.8rem;color:var(--cp-on-surface-var);margin-left:4px"></span>
    </form>

    <div style="margin-top:24px;padding:12px 14px;background:var(--cp-surface-container-low);border-radius:10px;display:flex;gap:10px;align-items:flex-start;font-size:.75rem;color:var(--cp-on-surface-var)">
      <span class="material-symbols-outlined msf" style="font-size:15px;color:var(--cp-secondary);flex-shrink:0;margin-top:1px">shield</span>
      <span data-en="Never share your code with anyone. Clinical Precision will never ask for it."
            data-sw="Usishiriki nambari yako na mtu yeyote. Clinical Precision haitawahi kuomba.">
        Never share your code with anyone. Clinical Precision will never ask for it.
      </span>
    </div>

    <p style="text-align:center;margin-top:18px;font-size:.8125rem;color:var(--cp-on-surface-var)">
      <a href="/hospital/onboarding/signup.php" style="color:var(--cp-primary);font-weight:600"
         data-en="Back" data-sw="← Rudi Usajili">← Back</a>
    </p>

  </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (typeof Lang !== 'undefined') Lang.init();
  document.getElementById('langToggle')?.addEventListener('click', () => Lang.toggle());

  const inputs     = [...document.querySelectorAll('.cp-otp-input')];
  const btn        = document.getElementById('verifyBtn');
  const resendBtn  = document.getElementById('resendBtn');
  const cooldownEl = document.getElementById('resendCooldown');
  let   cooldownTimer = null;

  function updateBtn() {
    btn.disabled = !inputs.every(x => /^\d$/.test(x.value));
    inputs.forEach(inp => inp.classList.toggle('otp-filled', /^\d$/.test(inp.value)));
  }

  <?php if ($error && str_contains($error, 'Incorrect')): ?>
  inputs.forEach(inp => inp.classList.add('otp-error'));
  <?php endif; ?>

  inputs.forEach((inp, i) => {
    inp.addEventListener('input', () => {
      inp.value = inp.value.replace(/\D/g, '').slice(-1);
      inp.classList.remove('otp-error');
      if (inp.value && i < inputs.length - 1) inputs[i + 1].focus();
      updateBtn();
    });
    inp.addEventListener('keydown', e => {
      if (e.key === 'Backspace') {
        if (inp.value) { inp.value = ''; updateBtn(); }
        else if (i > 0) { inputs[i-1].focus(); inputs[i-1].value = ''; updateBtn(); }
        e.preventDefault();
      }
      if (e.key === 'ArrowLeft'  && i > 0)               { inputs[i-1].focus(); e.preventDefault(); }
      if (e.key === 'ArrowRight' && i < inputs.length-1) { inputs[i+1].focus(); e.preventDefault(); }
    });
    inp.addEventListener('paste', e => {
      e.preventDefault();
      const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
      inputs.forEach((b, j) => { b.value = paste[j] || ''; });
      inputs[Math.min(paste.length, inputs.length) - 1]?.focus();
      updateBtn();
    });
    inp.addEventListener('focus', () => inp.select());
  });

  document.getElementById('otpForm').addEventListener('submit', () => {
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 1s linear infinite">progress_activity</span> Verifying…';
  });

  function startCooldown() {
    let secs = 30;
    resendBtn.disabled = true;
    resendBtn.style.opacity = '0.45';
    resendBtn.style.cursor  = 'not-allowed';
    cooldownEl.style.display = 'inline';
    cooldownEl.textContent  = `(${secs}s)`;
    clearInterval(cooldownTimer);
    cooldownTimer = setInterval(() => {
      secs--;
      if (secs <= 0) {
        clearInterval(cooldownTimer);
        resendBtn.disabled = false;
        resendBtn.style.opacity = '1';
        resendBtn.style.cursor  = 'pointer';
        cooldownEl.style.display = 'none';
      } else {
        cooldownEl.textContent = `(${secs}s)`;
      }
    }, 1000);
  }

  document.getElementById('resendForm').addEventListener('submit', startCooldown);

  <?php if ($success && str_contains($success, 'new code')): ?>
  startCooldown();
  <?php endif; ?>

  const style = document.createElement('style');
  style.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
  document.head.appendChild(style);

  updateBtn();
});
</script>
</body>
</html>
