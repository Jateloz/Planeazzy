<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
Security::startSession();
if (Security::isAuthenticated()) { header('Location: /patients/dashboard.php'); exit; }
$noSidebar = true; $pageTitle = 'Verify Your Email';
include dirname(__DIR__) . '/includes/header.php';
$csrf   = Security::csrfToken();
$devOtp = '';
if (APP_ENV === 'development') {
    foreach ([ROOT_DIR.'/logs/mail_dev.log', sys_get_temp_dir().'/planeazzy_logs/mail_dev.log'] as $lf) {
        if (file_exists($lf)) {
            $lines = array_reverse(file($lf, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES));
            foreach ($lines as $line) { if (preg_match('/OTP \[[^\]]+\] \([^)]+\): (\d{4,8})/', $line, $m)) { $devOtp = $m[1]; break 2; } }
        }
    }
}
?>
<style>
.auth-wrap{flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px;background:var(--slate-50)}
.auth-card{background:#fff;border-radius:20px;padding:40px;box-shadow:0 12px 40px rgba(0,0,0,.08);width:100%;max-width:480px}
@media(max-width:480px){.auth-card{padding:28px 20px}}
</style>
<main class="auth-wrap">
  <div class="auth-card slide-up">
    <!-- Step progress -->
    <div class="step-wrap">
      <div class="step-row">
        <span class="step-badge"><i class="fa-solid fa-envelope-circle-check"></i> <span data-en="Verify Email" data-sw="Thibitisha Barua Pepe">Verify Email</span></span>
        <span class="step-pct" data-en="Step 2 of 3" data-sw="Hatua 2 ya 3">Step 2 of 3</span>
      </div>
      <div class="prog-track"><div class="prog-fill" style="width:66%"></div></div>
    </div>

    <div style="text-align:center;margin-bottom:24px">
      <div style="width:56px;height:56px;border-radius:14px;background:var(--primary-10);color:var(--primary);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:24px;border:1px solid var(--primary-20)">
        <i class="fa-solid fa-envelope-circle-check"></i>
      </div>
      <h2 style="font-size:1.375rem;font-weight:900;color:var(--slate-900);margin-bottom:8px;letter-spacing:-.03em"
          data-en="Check your email" data-sw="Angalia barua pepe yako">Check your email</h2>
      <p style="font-size:.875rem;color:var(--slate-500);line-height:1.65">
        <span data-en="We sent a 6-digit verification code to" data-sw="Tulituma nambari ya uthibitisho ya tarakimu 6 kwa">We sent a 6-digit verification code to</span>
        <strong id="emailShow" style="color:var(--slate-900)">your email</strong>.
        <span data-en="It expires in" data-sw="Inaisha baada ya">It expires in</span> <?= OTP_EXPIRY_MINUTES ?> <span data-en="minutes." data-sw="dakika.">minutes.</span>
      </p>
    </div>

    <?php if ($devOtp): ?>
    <div style="background:#fefce8;border:1.5px dashed #d97706;border-radius:10px;padding:14px;text-align:center;margin-bottom:16px">
      <div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">⚡ Dev Mode — OTP Code</div>
      <div style="font-family:monospace;font-size:34px;font-weight:900;letter-spacing:12px;color:var(--primary)"><?= htmlspecialchars($devOtp) ?></div>
      <div style="font-size:11px;color:#92400e;margin-top:5px">Copy into boxes below · <a href="/dev-otp.php" style="color:var(--primary)">View all codes</a></div>
    </div>
    <?php endif; ?>

    <div id="alertBox" class="alert hidden"></div>

    <div class="otp-row" id="otpGrid">
      <?php for ($i = 0; $i < 6; $i++): ?>
      <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" autocomplete="off" <?= $i === 0 ? 'autofocus' : '' ?>>
      <?php endfor; ?>
    </div>

    <button id="verifyBtn" class="btn btn-primary btn-full btn-lg" disabled onclick="doVerify()">
      <i class="fa-solid fa-check-circle"></i>
      <span data-en="Verify Email" data-sw="Thibitisha Barua Pepe">Verify Email</span>
    </button>

    <div style="margin-top:16px;text-align:center;font-size:.875rem;color:var(--slate-500)">
      <span data-en="Didn't receive a code?" data-sw="Hukupokea nambari?">Didn't receive a code?</span>
      <button onclick="doResend()" style="background:none;border:none;cursor:pointer;color:var(--primary);font-weight:700;font-family:inherit;font-size:.875rem;text-decoration:underline"
              data-en="Resend code" data-sw="Tuma tena nambari">Resend code</button>
    </div>

    <div style="margin-top:20px;padding:12px;border-radius:8px;background:var(--primary-10);border:1px solid var(--primary-20);display:flex;align-items:flex-start;gap:10px;font-size:.75rem;color:var(--slate-600);line-height:1.6">
      <i class="fa-solid fa-shield-halved" style="color:var(--primary);flex-shrink:0;margin-top:1px"></i>
      <span data-en="Your code is valid for <?= OTP_EXPIRY_MINUTES ?> minutes. Never share it with anyone."
            data-sw="Nambari yako inafaa kwa dakika <?= OTP_EXPIRY_MINUTES ?>. Kamwe usishiriki na mtu yeyote.">
        Your code is valid for <?= OTP_EXPIRY_MINUTES ?> minutes. Never share it with anyone.
      </span>
    </div>
  </div>
</main>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
<script>
OTP.init('#otpGrid');
const storedEmail = sessionStorage.getItem('pz_pat_email');
if (storedEmail) { const el = document.getElementById('emailShow'); if (el) el.textContent = storedEmail; }

async function doVerify() {
  const code = OTP.value('#otpGrid');
  const id   = sessionStorage.getItem('pz_pat_id') || 0;
  const r    = await post('/api/auth/verify-otp.php', {
    csrf_token: document.getElementById('csrfToken').value,
    patient_id: parseInt(id), otp: code
  }, 'verifyBtn', 'alertBox');
  if (!r) return;
  if (r.success) {
    UI.alert('ok', document.documentElement.lang === 'sw' ? 'Barua pepe imethibitishwa! Inaendelea…' : 'Email verified! Setting up your account…', 'alertBox');
    setTimeout(() => location.href = '/patients/preferences.php', 1000);
  } else {
    UI.alert('err', r.message || 'Invalid code. Please try again.', 'alertBox');
  }
}
async function doResend() {
  const id = sessionStorage.getItem('pz_pat_id') || 0;
  const r  = await post('/api/auth/resend-otp.php', {
    csrf_token: document.getElementById('csrfToken').value, patient_id: parseInt(id)
  }, null, 'alertBox');
  if (r?.success) UI.alert('ok', document.documentElement.lang === 'sw' ? 'Nambari mpya imetumwa.' : 'A new code has been sent.', 'alertBox');
  else UI.alert('err', r?.message || 'Could not resend. Try again.', 'alertBox');
}
</script>
