<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
require_once dirname(__DIR__, 2) . '/services/HospitalMailer.php';
Security::startSession();

if (empty($_SESSION['hospital_id'])) { header('Location: /hospital/onboarding/signup.php'); exit; }
$hid   = (int)$_SESSION['hospital_id'];
$email = $_SESSION['hospital_email'] ?? '';
$name  = $_SESSION['hospital_name']  ?? 'Provider';
$csrf  = Security::csrfToken();
$error = $success = '';
$db    = Database::getInstance();
$hosp  = $db->fetchOne('SELECT * FROM hospital_providers WHERE id=:id', [':id' => $hid]);
if (!$hosp) { header('Location: /hospital/onboarding/signup.php'); exit; }
if (!empty($hosp['email_verified'])) { header('Location: /hospital/onboarding/profile.php'); exit; }

/* ── OTP helpers ─────────────────────────────────────────────── */
function h_send_otp(int $hid, string $email, string $name, $db): void {
    $plain = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash  = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 10]);
    
    $db->query(
        'UPDATE hospital_providers SET email_otp_hash=:h, email_otp_at=:at WHERE id=:id',
        [
            ':h'  => $hash, 
            ':id' => $hid,
            ':at' => date('Y-m-d H:i:s')
        ]
    );
    
    HospitalMailer::sendOtp($email, $name, $plain, $hid);
}

/* ── Auto-send OTP on first visit ────────────────────────────── */
if (empty($hosp['email_otp_hash'])) {
    h_send_otp($hid, $email, $name, $db);
    $success = 'A 6-digit verification code has been sent to ' . htmlspecialchars($email) . '. Check your inbox (and spam folder).';
}

/* ── Handle POST ─────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? 'verify';
    $tok = trim($_POST['csrf_token'] ?? '');
    if (!Security::verifyCsrf($tok)) {
        $error = 'Security error. Please refresh and try again.';
    } elseif ($act === 'resend') {
        h_send_otp($hid, $email, $name, $db);
        $success = 'A new code has been sent to ' . htmlspecialchars($email) . '.';
    } else {
        $entered = implode('', array_map(fn($k) => trim($_POST["d$k"] ?? ''), range(1, 6)));
        $fresh   = $db->fetchOne('SELECT email_otp_hash, email_otp_at FROM hospital_providers WHERE id=:id', [':id' => $hid]);

        if (!preg_match('/^\d{6}$/', $entered)) {
            $error = 'Please enter all 6 digits.';
        } elseif (empty($fresh['email_otp_hash'])) {
            $error = 'No code found. Please request a new one.';
        }// --- ADD THE EXPIRY CHECK HERE ---
elseif (strtotime($fresh['email_otp_at']) < (time() - (OTP_EXPIRY_MINUTES * 60))) {
    $error = 'This code has expired (Timezone Mismatch). Please request a new one.';
}  elseif (!password_verify($entered, $fresh['email_otp_hash'])) {
            $error = 'Incorrect code. Please check your email and try again.';
        } elseif (strtotime($fresh['email_otp_at']) < time() - OTP_EXPIRY_MINUTES * 60) {
            $error = 'Code has expired. Please request a new one.';
        } else {
            $db->query(
                'UPDATE hospital_providers SET email_verified=1, email_otp_hash=NULL, email_otp_at=NULL, onboarding_step=4 WHERE id=:id',
                [':id' => $hid]
            );
            HospitalMailer::sendWelcome($email, $name, $hosp['facility_type'] ?? 'hospital', $hid);
            header('Location: /hospital/onboarding/profile.php'); exit;
        }
    }
}

$cpStep = 4; $cpTitle = 'Verify Email';
include __DIR__ . '/_head.php';
?>
<style>
.vfy-wrap{min-height:calc(100vh - 56px);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;background:linear-gradient(135deg,#f7f9fb 0%,#f2f4f6 100%)}
.vfy-card{background:var(--cp-surface-container-lowest);border-radius:24px;padding:48px 44px;max-width:480px;width:100%;box-shadow:0 24px 64px rgba(25,28,30,.08);border:1px solid rgba(193,198,213,.12)}
@media(max-width:520px){.vfy-card{padding:32px 20px;border-radius:18px}}
</style>

<!-- Progress rail -->
<div class="cp-progress-rail"><div class="cp-progress-fill" style="width:50%"></div></div>

<!-- Topnav -->
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
    <!-- Icon -->
    <div style="width:60px;height:60px;border-radius:16px;background:rgba(0,90,180,.08);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;border:1px solid rgba(0,90,180,.15)">
      <span class="material-symbols-outlined msf" style="font-size:28px;color:var(--cp-primary)">mark_email_unread</span>
    </div>

    <h1 class="cp-h2" style="text-align:center;margin-bottom:10px" data-en="Check your email" data-sw="Angalia barua pepe yako">Check your email</h1>
    <p class="cp-body" style="text-align:center;margin-bottom:28px">
      <span data-en="We sent a 6-digit code to" data-sw="Tulituma nambari ya tarakimu 6 kwa">We sent a 6-digit code to</span>
      <strong style="color:var(--cp-on-surface)"><?= htmlspecialchars($email) ?></strong>.
      <span data-en="It expires in" data-sw="Inaisha baada ya">It expires in</span>
      <strong><?= OTP_EXPIRY_MINUTES ?></strong>
      <span data-en="minutes." data-sw="dakika.">minutes.</span>
    </p>

    <!-- Alerts -->
    <?php if ($error): ?>
    <div style="background:rgba(186,26,26,.08);border:1px solid rgba(186,26,26,.2);border-radius:10px;padding:12px 16px;display:flex;gap:10px;align-items:flex-start;margin-bottom:20px;font-size:.875rem;color:#6b0000">
      <span class="material-symbols-outlined" style="font-size:18px;flex-shrink:0">error</span>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div style="background:rgba(0,106,106,.07);border:1px solid rgba(0,106,106,.2);border-radius:10px;padding:12px 16px;display:flex;gap:10px;align-items:flex-start;margin-bottom:20px;font-size:.875rem;color:#014b4b">
      <span class="material-symbols-outlined" style="font-size:18px;flex-shrink:0">check_circle</span>
      <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- OTP inputs -->
    <form method="POST" id="otpForm" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="verify">

      <div class="cp-otp-row" id="otpGrid" style="margin-bottom:28px">
        <?php for ($i = 1; $i <= 6; $i++): ?>
        <input class="cp-otp-input" type="text" inputmode="numeric" name="d<?=$i?>"
               maxlength="1" autocomplete="off"
               pattern="[0-9]" <?= $i === 1 ? 'autofocus' : '' ?>>
        <?php endfor; ?>
      </div>

      <button type="submit" id="verifyBtn" class="cp-btn cp-btn-primary cp-btn-full cp-btn-round" style="font-size:.9375rem;padding:14px" disabled>
        <span class="material-symbols-outlined">verified_user</span>
        <span data-en="Verify Email" data-sw="Thibitisha Barua Pepe">Verify Email</span>
      </button>
    </form>

    <!-- Resend -->
    <form method="POST" style="margin-top:16px;text-align:center">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="resend">
      <span style="font-size:.875rem;color:var(--cp-on-surface-var)"
            data-en="Didn't receive it?" data-sw="Hukupokea?">Didn't receive it?</span>
      <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--cp-primary);font-weight:700;font-family:inherit;font-size:.875rem;text-decoration:underline;margin-left:4px"
              data-en="Resend code" data-sw="Tuma tena">Resend code</button>
    </form>

    <!-- Security note -->
    <div style="margin-top:24px;padding:12px 14px;background:var(--cp-surface-container-low);border-radius:10px;display:flex;gap:10px;align-items:flex-start;font-size:.75rem;color:var(--cp-on-surface-var)">
      <span class="material-symbols-outlined msf" style="font-size:15px;color:var(--cp-secondary);flex-shrink:0;margin-top:1px">shield</span>
      <span data-en="Never share your code with anyone. Planeazzy will never ask for it." data-sw="Usishiriki nambari yako na mtu yeyote. Planeazzy haitawahi kuomba.">
        Never share your code with anyone. Planeazzy will never ask for it.
      </span>
    </div>

    <p style="text-align:center;margin-top:18px;font-size:.8125rem;color:var(--cp-on-surface-var)">
      <a href="/hospital/onboarding/signup.php" style="color:var(--cp-primary);font-weight:600"
         data-en="← Back to Sign Up" data-sw="← Rudi Usajili">← Back to Sign Up</a>
    </p>
  </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (typeof Lang !== 'undefined') Lang.init();
  document.getElementById('langToggle')?.addEventListener('click', () => Lang.toggle());

  const inputs  = [...document.querySelectorAll('.cp-otp-input')];
  const btn     = document.getElementById('verifyBtn');

  // Auto-advance between boxes, enable button when all filled
  inputs.forEach((inp, i) => {
    inp.addEventListener('input', () => {
      inp.value = inp.value.replace(/\D/g, '').slice(-1);
      if (inp.value && i < inputs.length - 1) inputs[i + 1].focus();
      btn.disabled = inputs.some(x => !x.value);
    });
    inp.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !inp.value && i > 0) inputs[i - 1].focus();
    });
    inp.addEventListener('paste', e => {
      e.preventDefault();
      const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
      inputs.forEach((b, j) => { b.value = paste[j] || ''; });
      inputs[Math.min(paste.length, inputs.length) - 1]?.focus();
      btn.disabled = inputs.some(x => !x.value);
    });
  });
});
</script>
</body>
</html>
